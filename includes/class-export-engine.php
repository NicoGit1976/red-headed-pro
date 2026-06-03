<?php
/**
 * Export Engine — orchestrates a single export run.
 *
 * Pipeline: profile → fetch orders (with filters) → map columns → build file
 * (format-specific builder) → save to uploads/red-headed-pro/exports/ → ship to
 * destinations (one or many) → log job row → fire webhooks.
 *
 * @package Red_Headed_Pro
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Red_Headed_Export_Engine {

    /** @return int|WP_Error  job ID on success, WP_Error on failure */
    public static function run( $profile, $trigger_source = 'manual' ) {
        global $wpdb;
        $jobs_tbl = $wpdb->prefix . 'rh_jobs';
        $started  = (int) round( microtime( true ) * 1000 );
        $profile  = is_array( $profile ) ? $profile : array();

        /* ── Split-per-order fan-out (Pro) ──────────────────────────────────
           When enabled, a multi-order run is expanded into one export per order
           so each order gets its own file, its own filename context and its own
           post-export status change. We reuse the whole pipeline by re-entering
           run() with a single-order override. Recursion is bounded by the count
           check below: a single-order run (auto-trigger, or a split sub-call)
           resolves to one order, falls through, and produces one file — it never
           re-splits. This MUST also fan out when order_ids_override carries
           several IDs (the bulk "Export selected orders" action), so a multi-
           selection yields one file per order, not one file of N orders. */
        if ( ! empty( $profile['split_per_order'] )
             && Red_Headed_Soft_Lock::is_available( 'split_per_order' ) ) {
            $batch = self::fetch_orders( isset( $profile['filters'] ) ? (array) $profile['filters'] : array() );
            if ( count( $batch ) > 1 ) {
                $last = 0;
                foreach ( $batch as $o ) {
                    if ( ! is_a( $o, 'WC_Order' ) ) continue;
                    $sub = $profile;
                    $sub['filters']['order_ids_override'] = array( (int) $o->get_id() );
                    $r = self::run( $sub, $trigger_source . ':split' );
                    if ( ! is_wp_error( $r ) ) $last = (int) $r;
                }
                return $last ? $last : new \WP_Error( 'split_empty', __( 'Split export produced no files.', 'red-headed-pro' ) );
            }
        }

        $job_id = (int) $wpdb->insert( $jobs_tbl, array(
            'profile_id'     => isset( $profile['id'] ) ? (int) $profile['id'] : null,
            'trigger_source' => $trigger_source,
            'format'         => isset( $profile['format'] ) ? sanitize_key( $profile['format'] ) : 'csv',
            'status'         => 'running',
        ) );
        $job_id = (int) $wpdb->insert_id;

        try {
            $orders  = self::fetch_orders( isset( $profile['filters'] ) ? (array) $profile['filters'] : array() );
            $columns = self::normalize_columns(
                ! empty( $profile['columns'] ) ? (array) $profile['columns'] : self::default_columns()
            );
            /* v1.5.0 — B3: safeguard against empty columns (e.g. all entries stripped
               by normalize_columns). Fall back to defaults so the export never produces
               a headerless / empty file. */
            if ( empty( $columns ) ) {
                $columns = self::normalize_columns( self::default_columns() );
            }
            $format = isset( $profile['format'] ) ? sanitize_key( $profile['format'] ) : 'csv';
            $format = self::guard_format( $format );

            /* Structured JSON shapes (Pro): "labeled" = one object per order keyed by
               column labels; "nested" = same + line-item columns grouped into a sub-array
               under line_items_key. Falls back to the flat row/CSV pipeline otherwise. */
            $json_shape = self::json_shape( $profile, $format );
            $mode = isset( $profile['export_mode'] ) ? sanitize_key( $profile['export_mode'] ) : 'per_order';

            if ( $json_shape !== '' ) {
                $li_key = ( isset( $profile['line_items_key'] ) && $profile['line_items_key'] !== '' )
                    ? (string) $profile['line_items_key'] : 'items';
                $nest = ( $json_shape === 'nested' );
                $rows = array();
                foreach ( $orders as $order ) {
                    $rows[] = self::map_row_object( $order, $columns, $nest, $li_key );
                }
            } elseif ( $mode === 'per_line_item' && Red_Headed_Soft_Lock::is_available( 'line_item_export' ) ) {
                $hf   = isset( $profile['line_item_header_fill'] ) && $profile['line_item_header_fill'] === 'first_only' ? 'first_only' : 'every';
                $rows = array();
                foreach ( $orders as $order ) {
                    foreach ( self::map_line_item_rows( $order, $columns, $hf ) as $r ) $rows[] = $r;
                }
            } else {
                $rows = array_map( function ( $order ) use ( $columns ) {
                    return self::map_row( $order, $columns );
                }, $orders );
            }

            /* Inject runtime context BEFORE build so the filename resolver can use
               {records}, {job_id} and {order_*} for the on-disk name (which Local +
               Email then inherit verbatim — one filename, consistent everywhere). */
            $profile['_job_id']      = $job_id;
            $profile['_records']     = count( $rows );
            $profile['_first_order'] = ! empty( $orders ) ? $orders[0] : null;

            $file = self::build_file( $format, $columns, $rows, $profile );

            if ( ! $file || ! file_exists( $file ) ) {
                throw new \RuntimeException( 'File build failed.' );
            }

            /* v1.5.0 — P2 dry-run: build the file but skip delivery entirely.
               v1.6.0 — Empty-export guard: a 0-record run must NOT ship a ghost file
               (the stray "commande-…-.json" of 2 bytes seen on the SFTP). Skip every
               destination and log the job as 'empty' so nothing goes downstream. */
            $is_dry_run = ! empty( $profile['_dry_run'] );
            $is_empty   = ( count( $rows ) === 0 );
            $delivered  = ( $is_dry_run || $is_empty ) ? null : self::deliver( $file, $profile, $format );

            /* v1.6.0 — Delivery visibility: a failed leg (SFTP auth/path, unreachable…)
               must NOT hide behind a green "success". If any destination errored, the
               job is 'partial' (the per-destination error is already appended to
               error_message by deliver()), so silent SFTP failures are visible. */
            $had_failure = is_array( $delivered ) && (bool) array_filter(
                $delivered,
                function ( $d ) { return is_wp_error( isset( $d['ok'] ) ? $d['ok'] : null ); }
            );
            $status = $is_dry_run ? 'dry_run' : ( $is_empty ? 'empty' : ( $had_failure ? 'partial' : 'success' ) );

            $duration = (int) round( microtime( true ) * 1000 ) - $started;
            $uploads  = wp_upload_dir();
            $rel      = ltrim( str_replace( $uploads['basedir'], '', $file ), '/\\' );
            $wpdb->update( $jobs_tbl, array(
                'file_path'     => $rel,
                'file_size'     => @filesize( $file ),
                'records_count' => count( $rows ),
                'status'        => $status,
                'duration_ms'   => $duration,
                'finished_at'   => current_time( 'mysql' ),
            ), array( 'id' => $job_id ) );

            /* v1.4.22 — Mark each exported order so the WC orders list can show
               an "Exported" column. Skipped when 0 rows (no orders processed).
               v1.5.0 — Also skipped during dry-run (file built, but nothing changes on orders). */
            if ( count( $rows ) > 0 && ! $is_dry_run ) {
                $now = current_time( 'mysql' );
                $post_status = isset( $profile['post_export_status'] ) ? sanitize_key( (string) $profile['post_export_status'] ) : '';
                $can_post_status = $post_status !== '' && Red_Headed_Soft_Lock::is_available( 'post_export_status' );
                foreach ( $orders as $order ) {
                    if ( ! is_a( $order, 'WC_Order' ) ) continue;
                    $count = (int) $order->get_meta( '_rh_export_count' );
                    $order->update_meta_data( '_rh_export_count', $count + 1 );
                    $order->update_meta_data( '_rh_last_export_at', $now );
                    $order->update_meta_data( '_rh_last_export_job_id', $job_id );
                    $order->save_meta_data();
                    if ( $can_post_status ) {
                        /* Convert "completed" → "wc-completed" if needed; WC update_status accepts both. */
                        $order->update_status( $post_status, __( 'Set by Red-Headed export', 'red-headed-pro' ) );
                    }
                }
            }

            do_action( 'red_headed_export_generated', $job_id, $profile, $file );
            if ( $delivered ) {
                do_action( 'red_headed_export_delivered', $job_id, $profile, $delivered );
            }

            return $job_id;
        } catch ( \Throwable $e ) {
            $wpdb->update( $jobs_tbl, array(
                'status'        => 'failed',
                'error_message' => substr( $e->getMessage(), 0, 800 ),
                'finished_at'   => current_time( 'mysql' ),
            ), array( 'id' => $job_id ) );
            do_action( 'red_headed_export_failed', $job_id, $profile, $e->getMessage() );
            return new \WP_Error( 'export_failed', $e->getMessage() );
        }
    }

    /* ────────── Filters → orders ────────── */
    public static function fetch_orders( $filters ) {
        if ( ! function_exists( 'wc_get_orders' ) ) return array();
        $args = array( 'limit' => -1, 'orderby' => 'date', 'order' => 'DESC' );
        $args['status'] = isset( $filters['status'] ) && $filters['status'] ? (array) $filters['status'] : array_keys( wc_get_order_statuses() );

        /* Auto-trigger override — single-order fetch path (used by Red_Headed_Auto_Trigger
           and bulk action). Uses `include` (HPOS-safe) instead of legacy `post__in`. */
        if ( ! empty( $filters['order_ids_override'] ) ) {
            $ids = array_map( 'intval', (array) $filters['order_ids_override'] );
            $args['include']  = $ids;   /* HPOS storage */
            $args['post__in'] = $ids;   /* legacy CPT storage — wc_get_orders ignores `include` on some CPT / query-filtering setups */
            $args['status']   = array_keys( wc_get_order_statuses() ); /* don't re-filter by status */
        }

        if ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) ) {
            $from = ! empty( $filters['date_from'] ) ? sanitize_text_field( $filters['date_from'] ) : '1970-01-01';
            $to   = ! empty( $filters['date_to'] )   ? sanitize_text_field( $filters['date_to'] )   : current_time( 'Y-m-d' );
            $args['date_created'] = $from . '...' . $to;
        }
        if ( ! empty( $filters['payment_method'] ) ) $args['payment_method'] = sanitize_key( $filters['payment_method'] );

        /* Customer email — exact match shortcut handled by WC core; "contains" handled post-fetch. */
        if ( ! empty( $filters['customer_email'] ) ) $args['billing_email'] = sanitize_email( $filters['customer_email'] );

        $orders = wc_get_orders( $args );

        /* v1.5.5 — HARD GUARANTEE for targeted exports (bulk action / auto-trigger).
           Some setups (legacy CPT storage, query-filtering plugins) silently ignore
           `include`/`post__in`, which let a single-order export leak ALL matching
           orders to the destination (e.g. 600 orders pushed to SAP instead of 1).
           Enforce the override post-fetch so a targeted export can NEVER contain
           another order, whatever the storage/query layer does. */
        if ( ! empty( $filters['order_ids_override'] ) ) {
            $want = array_flip( array_map( 'intval', (array) $filters['order_ids_override'] ) );
            $orders = array_values( array_filter( (array) $orders, function ( $o ) use ( $want ) {
                return is_a( $o, 'WC_Order' ) && isset( $want[ (int) $o->get_id() ] );
            } ) );
        }

        /* Post-fetch refinement (Pro). All advanced predicates run here so we can short-circuit
           cleanly when the Lite edition hits any locked filter. */
        $advanced_keys = array(
            'shipping_method', 'sku_pattern', 'category',
            'customer_role', 'customer_email_contains',
            'total_min', 'total_max',
            'meta_key', 'meta_value',
            'coupon',
            'billing_city', 'billing_country', 'shipping_city', 'shipping_country',
        );
        $has_advanced = false;
        foreach ( $advanced_keys as $k ) { if ( isset( $filters[ $k ] ) && $filters[ $k ] !== '' && $filters[ $k ] !== array() ) { $has_advanced = true; break; } }
        if ( ! $has_advanced ) return $orders;
        if ( ! Red_Headed_Soft_Lock::is_available( 'filters_advanced' ) ) return $orders;

        return array_values( array_filter( $orders, function ( $o ) use ( $filters ) {
            if ( ! empty( $filters['shipping_method'] ) ) {
                $methods = array();
                foreach ( $o->get_shipping_methods() as $m ) $methods[] = $m->get_method_id();
                if ( ! in_array( $filters['shipping_method'], $methods, true ) ) return false;
            }
            if ( ! empty( $filters['sku_pattern'] ) ) {
                $pattern = (string) $filters['sku_pattern'];
                $hit = false;
                foreach ( $o->get_items() as $it ) {
                    $sku = $it->get_product() ? $it->get_product()->get_sku() : '';
                    if ( $sku && stripos( $sku, $pattern ) !== false ) { $hit = true; break; }
                }
                if ( ! $hit ) return false;
            }
            if ( ! empty( $filters['category'] ) ) {
                $hit = false;
                foreach ( $o->get_items() as $it ) {
                    $pid = $it->get_product() ? $it->get_product()->get_id() : 0;
                    if ( $pid && has_term( (int) $filters['category'], 'product_cat', $pid ) ) { $hit = true; break; }
                }
                if ( ! $hit ) return false;
            }
            if ( ! empty( $filters['customer_role'] ) ) {
                $uid = (int) $o->get_customer_id();
                if ( ! $uid ) return false;
                $u = get_userdata( $uid );
                if ( ! $u || ! in_array( (string) $filters['customer_role'], (array) $u->roles, true ) ) return false;
            }
            if ( ! empty( $filters['customer_email_contains'] ) ) {
                if ( stripos( (string) $o->get_billing_email(), (string) $filters['customer_email_contains'] ) === false ) return false;
            }
            if ( isset( $filters['total_min'] ) && $filters['total_min'] !== '' ) {
                if ( (float) $o->get_total() < (float) $filters['total_min'] ) return false;
            }
            if ( isset( $filters['total_max'] ) && $filters['total_max'] !== '' ) {
                if ( (float) $o->get_total() > (float) $filters['total_max'] ) return false;
            }
            if ( ! empty( $filters['meta_key'] ) ) {
                $val = $o->get_meta( (string) $filters['meta_key'] );
                if ( isset( $filters['meta_value'] ) && $filters['meta_value'] !== '' ) {
                    if ( (string) $val !== (string) $filters['meta_value'] ) return false;
                } else {
                    if ( $val === '' || $val === null ) return false;
                }
            }
            if ( ! empty( $filters['coupon'] ) ) {
                $codes = array_map( 'strtolower', (array) $o->get_coupon_codes() );
                if ( ! in_array( strtolower( (string) $filters['coupon'] ), $codes, true ) ) return false;
            }
            if ( ! empty( $filters['billing_city'] ) ) {
                if ( stripos( (string) $o->get_billing_city(), (string) $filters['billing_city'] ) === false ) return false;
            }
            if ( ! empty( $filters['billing_country'] ) ) {
                if ( strcasecmp( (string) $o->get_billing_country(), (string) $filters['billing_country'] ) !== 0 ) return false;
            }
            if ( ! empty( $filters['shipping_city'] ) ) {
                if ( stripos( (string) $o->get_shipping_city(), (string) $filters['shipping_city'] ) === false ) return false;
            }
            if ( ! empty( $filters['shipping_country'] ) ) {
                if ( strcasecmp( (string) $o->get_shipping_country(), (string) $filters['shipping_country'] ) !== 0 ) return false;
            }
            return true;
        } ) );
    }

    /* ────────── Order → row (columns) ────────── */
    /**
     * Normalize columns to an array of { key, label } objects.
     * Accepts plain string lists (legacy) and { key, label } object lists (v1.2.0+).
     */
    public static function normalize_columns( $columns ) {
        $out = array();
        foreach ( (array) $columns as $col ) {
            if ( is_array( $col ) ) {
                $key = (string) ( $col['key'] ?? '' );
                if ( $key === '' ) continue;
                $entry = array(
                    'key'   => $key,
                    'label' => (string) ( $col['label'] ?? self::default_label_for( $key ) ),
                );
                /* Preserve metadata for computed columns (static + calc). */
                if ( strpos( $key, 'static:' ) === 0 && isset( $col['value'] ) ) $entry['value'] = (string) $col['value'];
                if ( strpos( $key, 'calc:' )   === 0 && isset( $col['expr'] ) )  $entry['expr']  = (string) $col['expr'];
                /* Per-column type / format cast (Pro). e.g. string, number, money2, int,
                   or a date preset "date:d-m-Y H:i". Lets the output match a fixed
                   downstream schema (ERP, partner API…) exactly. */
                if ( isset( $col['cast'] ) && $col['cast'] !== '' ) $entry['cast'] = sanitize_text_field( (string) $col['cast'] );
                $out[] = $entry;
            } else {
                $key = (string) $col;
                if ( $key === '' ) continue;
                $out[] = array( 'key' => $key, 'label' => self::default_label_for( $key ) );
            }
        }
        return $out;
    }

    public static function default_label_for( $key ) {
        $cat = self::column_catalog();
        if ( isset( $cat[ $key ]['label'] ) ) return $cat[ $key ]['label'];
        if ( strpos( $key, 'meta:' ) === 0 ) return 'Meta — ' . substr( $key, 5 );
        return $key;
    }

    public static function map_row( $order, $columns ) {
        $row = array();
        foreach ( $columns as $col ) {
            $key = is_array( $col ) ? ( $col['key'] ?? '' ) : (string) $col;
            /* Computed columns (Pro): static + calc resolve from the column entry itself. */
            if ( is_array( $col ) && strpos( $key, 'static:' ) === 0 ) {
                $row[ $key ] = self::transform_value( $order, $col, $key, isset( $col['value'] ) ? (string) $col['value'] : '' );
                continue;
            }
            if ( is_array( $col ) && strpos( $key, 'calc:' ) === 0 ) {
                $row[ $key ] = self::transform_value( $order, $col, $key, self::resolve_calc( $order, isset( $col['expr'] ) ? (string) $col['expr'] : '' ) );
                continue;
            }
            $row[ $key ] = self::transform_value( $order, $col, $key, self::resolve_column( $order, $key ) );
        }
        return $row;
    }

    /**
     * Resolve the structured-JSON shape for this run, or '' if not applicable.
     * Only meaningful for json / ndjson formats and only when the Pro
     * "json_structure" capability is available.
     *
     * @return string '' | 'labeled' | 'nested'
     */
    public static function json_shape( $profile, $format ) {
        if ( $format !== 'json' && $format !== 'ndjson' ) return '';
        if ( ! Red_Headed_Soft_Lock::is_available( 'json_structure' ) ) return '';
        $s = isset( $profile['json_shape'] ) ? sanitize_key( (string) $profile['json_shape'] ) : '';
        return in_array( $s, array( 'labeled', 'nested' ), true ) ? $s : '';
    }

    /**
     * Map one order to a single associative object keyed by column LABELS — the
     * structured shape used by the "labeled" / "nested" JSON exports.
     *
     * Order-level columns (and static/calc) become top-level keys. When $nest is
     * true, every line-item column (line_*) is grouped into a sub-array under
     * $line_items_key, one sub-object per order line (each keyed by that line
     * column's label). When $nest is false, line columns are skipped at the
     * order level (use per_line_item mode for a flat per-line layout instead).
     *
     * Universal: labels, keys and the line-items key are all user-configurable,
     * so any downstream schema (ERP, partner API, custom importer…) can be matched
     * without touching code.
     */
    public static function map_row_object( $order, $columns, $nest = false, $line_items_key = 'items' ) {
        $obj       = array();
        $line_cols = array();
        $slot_set  = false;
        foreach ( $columns as $col ) {
            $key = is_array( $col ) ? ( $col['key'] ?? '' ) : (string) $col;
            if ( $key === '' ) continue;
            $label = ( is_array( $col ) && isset( $col['label'] ) && $col['label'] !== '' )
                ? (string) $col['label'] : self::default_label_for( $key );
            if ( strpos( $key, 'line_' ) === 0 ) {
                $line_cols[] = array( 'key' => $key, 'label' => $label, 'col' => $col );
                /* Reserve the nested array's slot at the FIRST line column so the
                   output key order mirrors the column layout (e.g. the line-items
                   key sits where the product columns are, not appended at the end). */
                if ( $nest && ! $slot_set ) { $obj[ $line_items_key ] = array(); $slot_set = true; }
                continue;
            }
            if ( is_array( $col ) && strpos( $key, 'static:' ) === 0 ) {
                $obj[ $label ] = self::transform_value( $order, $col, $key, isset( $col['value'] ) ? (string) $col['value'] : '' );
                continue;
            }
            if ( is_array( $col ) && strpos( $key, 'calc:' ) === 0 ) {
                $obj[ $label ] = self::transform_value( $order, $col, $key, self::resolve_calc( $order, isset( $col['expr'] ) ? (string) $col['expr'] : '' ) );
                continue;
            }
            $obj[ $label ] = self::transform_value( $order, $col, $key, self::resolve_column( $order, $key ) );
        }
        if ( $nest && ! empty( $line_cols ) ) {
            $items = method_exists( $order, 'get_items' ) ? $order->get_items() : array();
            $list  = array();
            foreach ( $items as $item ) {
                $line = array();
                foreach ( $line_cols as $lc ) {
                    $line[ $lc['label'] ] = self::transform_value( $order, $lc['col'], $lc['key'], self::resolve_line_column( $order, $item, $lc['key'] ) );
                }
                $list[] = $line;
            }
            $obj[ $line_items_key ] = $list;
        }
        return $obj;
    }

    /**
     * Expand one order to N rows — one per line item — for the per_line_item export mode.
     * Header fill: 'every' (default) repeats order columns on every line; 'first_only' blanks them past row 1.
     */
    public static function map_line_item_rows( $order, $columns, $header_fill = 'every' ) {
        $items = method_exists( $order, 'get_items' ) ? $order->get_items() : array();
        if ( empty( $items ) ) return array( self::map_row( $order, $columns ) ); /* keep behavior consistent for orders without items */
        $rows = array();
        $idx = 0;
        foreach ( $items as $item ) {
            $row = array();
            foreach ( $columns as $col ) {
                $key = is_array( $col ) ? ( $col['key'] ?? '' ) : (string) $col;
                if ( strpos( $key, 'line_' ) === 0 ) {
                    $row[ $key ] = self::transform_value( $order, $col, $key, self::resolve_line_column( $order, $item, $key ) );
                    continue;
                }
                if ( is_array( $col ) && strpos( $key, 'static:' ) === 0 ) {
                    $row[ $key ] = self::transform_value( $order, $col, $key, isset( $col['value'] ) ? (string) $col['value'] : '' );
                    continue;
                }
                if ( is_array( $col ) && strpos( $key, 'calc:' ) === 0 ) {
                    $row[ $key ] = self::transform_value( $order, $col, $key, self::resolve_calc( $order, isset( $col['expr'] ) ? (string) $col['expr'] : '' ) );
                    continue;
                }
                if ( $idx > 0 && $header_fill === 'first_only' ) { $row[ $key ] = ''; continue; }
                $row[ $key ] = self::transform_value( $order, $col, $key, self::resolve_column( $order, $key ) );
            }
            $rows[] = $row;
            $idx++;
        }
        return $rows;
    }

    /**
     * Apply a per-column type / format cast to a resolved value.
     *
     *   ''        raw (no change)
     *   string    (string) value  — e.g. 300 → "300", 1440.0 → "1440"
     *   int       (int) value
     *   number    (float) value    — kept as a JSON number
     *   money2    number_format(value, 2) as string — e.g. 1440 → "1440.00"
     *   date:FMT  re-format a date column with the PHP date() format FMT
     *             (handled in transform_value(), which has the order context)
     */
    public static function apply_cast( $value, $cast ) {
        switch ( $cast ) {
            case 'string': return is_bool( $value ) ? ( $value ? '1' : '' ) : (string) $value;
            case 'int':    return (int) $value;
            case 'number': return is_numeric( $value ) ? (float) $value : $value;
            case 'money2': return number_format( (float) $value, 2, '.', '' );
            default:       return $value;
        }
    }

    /**
     * Resolve a column's cast then return the transformed value. Date columns
     * with a "date:FMT" cast are re-resolved from the order's WC_DateTime so the
     * exact format (e.g. d-m-Y H:i) is honoured.
     */
    protected static function transform_value( $order, $col, $key, $value ) {
        $cast = ( is_array( $col ) && isset( $col['cast'] ) ) ? (string) $col['cast'] : '';
        if ( $cast === '' ) return $value;
        if ( strpos( $cast, 'date:' ) === 0 && in_array( $key, array( 'date_created', 'date_paid' ), true ) ) {
            $fmt = substr( $cast, 5 );
            $dt  = ( $key === 'date_paid' ) ? $order->get_date_paid() : $order->get_date_created();
            return $dt ? $dt->date( $fmt ) : '';
        }
        return self::apply_cast( $value, $cast );
    }

    protected static function resolve_line_column( $order, $item, $key ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) return '';
        $product = $item->get_product();
        switch ( $key ) {
            case 'line_sku':
                /* Robust SKU resolution (SKU = the eternal key, never the WP ID):
                   1. frozen-at-order-time SKU (survives even a hard catalog purge),
                   2. live product SKU,
                   3. _sku postmeta direct (mirrors AOE — survives a trashed/unloadable product),
                   4. catalog re-link by exact product title (re-finds the SKU when a
                      re-import recreated the product under a new ID — orphaning the order line).
                   Returns '' only when no SKU exists anywhere. */
                $frozen = $item->get_meta( '_rh_sku' );
                if ( $frozen !== '' && $frozen !== null ) return (string) $frozen;
                if ( $product && (string) $product->get_sku() !== '' ) return (string) $product->get_sku();
                $pid = $item->get_variation_id() ? (int) $item->get_variation_id() : (int) $item->get_product_id();
                if ( $pid ) { $sku = get_post_meta( $pid, '_sku', true ); if ( $sku !== '' && $sku !== false ) return (string) $sku; }
                /* 4. Catalog re-link (v1.5.8): when a re-import recreates products under NEW
                      post IDs, historical order lines point to a dead ID and 1–3 all miss.
                      The catalog is the eternal source — re-find the SKU by EXACT product
                      title (what AOE / WP All Export-class tools do). Cached per run,
                      filterable (set red_headed_sku_recover_by_title to false to disable). */
                $title = (string) $item->get_name();
                if ( $title !== '' && apply_filters( 'red_headed_sku_recover_by_title', true, $item, $order ) ) {
                    $by_title = self::sku_by_title( $title );
                    if ( $by_title !== '' ) return $by_title;
                }
                return '';
            case 'line_name':      return (string) $item->get_name();
            case 'line_qty':       return (int)    $item->get_quantity();
            case 'line_price':     return $product ? (float) $product->get_price() : ( $item->get_quantity() ? (float) $item->get_subtotal() / max( 1, (int) $item->get_quantity() ) : 0 );
            case 'line_unit_price':
                if ( ! (int) $item->get_quantity() ) return 0;
                /* Net unit price paid (subtotal ÷ qty), rounded to the store's money
                   precision so the division never leaks binary float artifacts
                   (e.g. 149.76/48 → 3.12, not 3.1199999999999997). */
                $dp = function_exists( 'wc_get_price_decimals' ) ? max( 2, (int) wc_get_price_decimals() ) : 2;
                return round( (float) $item->get_subtotal() / max( 1, (int) $item->get_quantity() ), $dp );
            case 'line_total':     return (float) $item->get_total();
            case 'line_subtotal':  return (float) $item->get_subtotal();
            case 'line_tax':       return (float) $item->get_total_tax();
            case 'line_product_id':return (int)   $item->get_product_id();
            case 'line_variation': return $item->get_variation_id() ? (int) $item->get_variation_id() : '';
        }
        return '';
    }

    /**
     * Resolve a product SKU from an EXACT product title — the last-resort catalog
     * re-link for order lines whose product was deleted & recreated under a new ID
     * (catalog re-import). The catalog is the eternal source, so the title still
     * points to the right SKU even when the order's stored product_id is dead.
     * Returns the most-recent published product/variation with that title that
     * actually has a SKU, or '' if none. Cached per request (one query per title).
     *
     * @param  string $title Exact product title (the order line name).
     * @return string        SKU, or '' when no titled product carries one.
     */
    protected static function sku_by_title( $title ) {
        static $cache = array();
        if ( array_key_exists( $title, $cache ) ) return $cache[ $title ];
        global $wpdb;
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status = 'publish' AND post_title = %s
             ORDER BY ID DESC",
            $title
        ) );
        $found = '';
        foreach ( (array) $ids as $pid ) {
            $sku = get_post_meta( $pid, '_sku', true );
            if ( $sku !== '' && $sku !== false ) { $found = (string) $sku; break; }
        }
        return $cache[ $title ] = $found;
    }

    /** Substitute {placeholder} tokens with order field values, then evaluate the math expression. */
    protected static function resolve_calc( $order, $expr ) {
        if ( $expr === '' ) return '';
        if ( ! Red_Headed_Soft_Lock::is_available( 'computed_columns' ) ) return '';
        $sub = preg_replace_callback( '/\{([a-z0-9_:.-]+)\}/i', function ( $m ) use ( $order ) {
            $val = self::resolve_column( $order, $m[1] );
            return is_numeric( $val ) ? (string) $val : '0';
        }, $expr );
        try {
            return Red_Headed_Expr_Evaluator::eval_expr( $sub );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    public static function default_columns() {
        return array(
            'order_id', 'order_number', 'date_created', 'status',
            'billing_first_name', 'billing_last_name', 'billing_email',
            'billing_company', 'billing_country',
            'total', 'currency', 'payment_method',
            'item_count', 'shipping_method',
        );
    }

    /**
     * Catalog of every column the engine knows how to resolve, grouped by
     * category for the admin column picker. Used by the profile editor UI.
     *
     * @return array<string, array{label:string, group:string, hint?:string}>
     */
    public static function column_catalog() {
        $cat = array(
            /* Order */
            'order_id'           => array( 'label' => 'Order ID',           'group' => 'order' ),
            'order_number'       => array( 'label' => 'Order number',       'group' => 'order' ),
            'date_created'       => array( 'label' => 'Date created',       'group' => 'order' ),
            'date_paid'          => array( 'label' => 'Date paid',          'group' => 'order' ),
            'status'             => array( 'label' => 'Status',             'group' => 'order' ),
            'currency'           => array( 'label' => 'Currency',           'group' => 'order' ),
            'item_count'         => array( 'label' => 'Item count',         'group' => 'order' ),
            'customer_id'        => array( 'label' => 'Customer ID',        'group' => 'order' ),
            'customer_login'     => array( 'label' => 'Customer login / code', 'group' => 'order', 'hint' => 'WP user_login (B2B account code)' ),
            'customer_note'      => array( 'label' => 'Customer note',      'group' => 'order' ),

            /* Totals */
            'total'              => array( 'label' => 'Order total',        'group' => 'totals' ),
            'subtotal'           => array( 'label' => 'Subtotal',           'group' => 'totals' ),
            'shipping_total'     => array( 'label' => 'Shipping total',     'group' => 'totals' ),
            'tax_total'          => array( 'label' => 'Tax total',          'group' => 'totals' ),
            'discount_total'     => array( 'label' => 'Discount total',     'group' => 'totals' ),

            /* Payment / shipping */
            'payment_method'     => array( 'label' => 'Payment method',     'group' => 'payment' ),
            'shipping_method'    => array( 'label' => 'Shipping method',    'group' => 'payment' ),

            /* Billing */
            'billing_first_name' => array( 'label' => 'Billing first name', 'group' => 'billing' ),
            'billing_last_name'  => array( 'label' => 'Billing last name',  'group' => 'billing' ),
            'billing_email'      => array( 'label' => 'Billing email',      'group' => 'billing' ),
            'billing_phone'      => array( 'label' => 'Billing phone',      'group' => 'billing' ),
            'billing_company'    => array( 'label' => 'Billing company',    'group' => 'billing' ),
            'billing_address'    => array( 'label' => 'Billing address',    'group' => 'billing', 'hint' => 'address_1 + address_2' ),
            'billing_city'       => array( 'label' => 'Billing city',       'group' => 'billing' ),
            'billing_postcode'   => array( 'label' => 'Billing postcode',   'group' => 'billing' ),
            'billing_country'    => array( 'label' => 'Billing country',    'group' => 'billing' ),

            /* Shipping */
            'shipping_first_name' => array( 'label' => 'Shipping first name', 'group' => 'shipping' ),
            'shipping_last_name'  => array( 'label' => 'Shipping last name',  'group' => 'shipping' ),
            'shipping_address'    => array( 'label' => 'Shipping address',    'group' => 'shipping', 'hint' => 'address_1 + address_2' ),
            'shipping_city'       => array( 'label' => 'Shipping city',       'group' => 'shipping' ),
            'shipping_postcode'   => array( 'label' => 'Shipping postcode',   'group' => 'shipping' ),
            'shipping_country'    => array( 'label' => 'Shipping country',    'group' => 'shipping' ),

            /* Line item — emitted in "one row per line item" mode OR nested under the
               line-items key in the "nested" JSON shape. */
            'line_sku'        => array( 'label' => 'Line — SKU',          'group' => 'line', 'hint' => 'per-line-item or nested JSON' ),
            'line_name'       => array( 'label' => 'Line — Product name', 'group' => 'line', 'hint' => 'per-line-item or nested JSON' ),
            'line_qty'        => array( 'label' => 'Line — Quantity',     'group' => 'line', 'hint' => 'per-line-item or nested JSON' ),
            'line_price'      => array( 'label' => 'Line — Unit price (catalog)', 'group' => 'line', 'hint' => 'product catalog price' ),
            'line_unit_price' => array( 'label' => 'Line — Unit price (paid net)', 'group' => 'line', 'hint' => 'subtotal ÷ qty — actually paid' ),
            'line_subtotal'   => array( 'label' => 'Line — Subtotal',     'group' => 'line', 'hint' => 'per-line-item mode only' ),
            'line_total'      => array( 'label' => 'Line — Total',        'group' => 'line', 'hint' => 'per-line-item mode only' ),
            'line_tax'        => array( 'label' => 'Line — Tax',          'group' => 'line', 'hint' => 'per-line-item mode only' ),
            'line_product_id' => array( 'label' => 'Line — Product ID',   'group' => 'line', 'hint' => 'per-line-item mode only' ),
            'line_variation'  => array( 'label' => 'Line — Variation ID', 'group' => 'line', 'hint' => 'per-line-item mode only' ),
        );
        /* Allow third-party plugins to register custom columns. */
        return apply_filters( 'red_headed_column_catalog', $cat );
    }

    public static function column_groups() {
        return array(
            'order'    => __( 'Order',         'red-headed-pro' ),
            'totals'   => __( 'Totals',        'red-headed-pro' ),
            'payment'  => __( 'Payment & Shipping', 'red-headed-pro' ),
            'billing'  => __( 'Billing address',    'red-headed-pro' ),
            'shipping' => __( 'Shipping address',   'red-headed-pro' ),
            'line'     => __( 'Line item',          'red-headed-pro' ),
            'meta'     => __( 'Custom meta',        'red-headed-pro' ),
        );
    }

    protected static function resolve_column( $order, $key ) {
        if ( ! $order ) return '';
        switch ( $key ) {
            case 'order_id':            return (int) $order->get_id();
            case 'order_number':        return (string) $order->get_order_number();
            case 'date_created':        return $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '';
            case 'date_paid':           return $order->get_date_paid() ? $order->get_date_paid()->date( 'Y-m-d H:i:s' ) : '';
            case 'status':              return (string) $order->get_status();
            case 'currency':            return (string) $order->get_currency();
            case 'total':               return (float) $order->get_total();
            case 'subtotal':            return (float) $order->get_subtotal();
            case 'shipping_total':      return (float) $order->get_shipping_total();
            case 'tax_total':           return (float) $order->get_total_tax();
            case 'discount_total':      return (float) $order->get_discount_total();
            case 'payment_method':      return (string) $order->get_payment_method_title();
            case 'shipping_method':     foreach ( $order->get_shipping_methods() as $m ) return $m->get_method_title(); return '';
            case 'item_count':          return count( $order->get_items() );
            case 'billing_first_name':  return $order->get_billing_first_name();
            case 'billing_last_name':   return $order->get_billing_last_name();
            case 'billing_email':       return $order->get_billing_email();
            case 'billing_phone':       return $order->get_billing_phone();
            case 'billing_company':     return $order->get_billing_company();
            case 'billing_address':     return trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );
            case 'billing_city':        return $order->get_billing_city();
            case 'billing_postcode':    return $order->get_billing_postcode();
            case 'billing_country':     return $order->get_billing_country();
            case 'shipping_first_name': return $order->get_shipping_first_name();
            case 'shipping_last_name':  return $order->get_shipping_last_name();
            case 'shipping_address':    return trim( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() );
            case 'shipping_city':       return $order->get_shipping_city();
            case 'shipping_postcode':   return $order->get_shipping_postcode();
            case 'shipping_country':    return $order->get_shipping_country();
            case 'customer_id':         return (int) $order->get_customer_id();
            case 'customer_login':
                $uid = (int) $order->get_customer_id();
                if ( ! $uid || ! function_exists( 'get_userdata' ) ) return '';
                $u = get_userdata( $uid );
                return $u ? (string) $u->user_login : '';
            case 'customer_note':       return $order->get_customer_note();
            default:
                if ( strpos( $key, 'meta:' ) === 0 ) {
                    return $order->get_meta( substr( $key, 5 ) );
                }
                return apply_filters( 'red_headed_resolve_column', '', $key, $order );
        }
    }

    /* ────────── Format guard ────────── */
    protected static function guard_format( $format ) {
        $allowed = array( 'csv' );
        if ( Red_Headed_Soft_Lock::is_available( 'format_xlsx' ) )   $allowed[] = 'xlsx';
        if ( Red_Headed_Soft_Lock::is_available( 'format_json' ) )   $allowed[] = 'json';
        if ( Red_Headed_Soft_Lock::is_available( 'format_xml' ) )    $allowed[] = 'xml';
        if ( Red_Headed_Soft_Lock::is_available( 'format_ndjson' ) ) $allowed[] = 'ndjson';
        if ( Red_Headed_Soft_Lock::is_available( 'format_tsv' ) )    $allowed[] = 'tsv';
        return in_array( $format, $allowed, true ) ? $format : 'csv';
    }

    /* ────────── Build file ────────── */
    protected static function build_file( $format, $columns, $rows, $profile ) {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . 'red-headed-pro/exports/' . date( 'Y/m' );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            $up = trailingslashit( $uploads['basedir'] ) . 'red-headed-pro';
            if ( ! file_exists( $up . '/.htaccess' ) ) {
                @file_put_contents( $up . '/.htaccess', "Options -Indexes\nOrder Allow,Deny\nDeny from all\n" );
                @file_put_contents( $up . '/index.php',  "<?php // Silence is golden.\n" );
            }
        }
        $base    = isset( $profile['name'] ) ? sanitize_file_name( $profile['name'] ) : 'export';
        $default = $base . '-' . date( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.' . $format;
        $pattern = isset( $profile['filename_pattern'] ) ? trim( (string) $profile['filename_pattern'] ) : '';
        /* v1.5.1 — Fall back to the global default pattern (Settings → General)
           when the profile has no pattern of its own. Normalize legacy {{double-brace}}
           syntax to {single-brace} so the resolver understands them. */
        if ( $pattern === '' ) {
            $pattern = trim( (string) get_option( 'red_headed_default_filename_pattern', '' ) );
            $pattern = preg_replace( '/\{\{([^}]+)\}\}/', '{$1}', $pattern );
        }
        if ( $pattern !== '' && Red_Headed_Soft_Lock::is_available( 'filename_pattern' ) ) {
            $name = Red_Headed_Filename_Resolver::resolve( $pattern, array(
                'profile_name' => isset( $profile['name'] ) ? (string) $profile['name'] : '',
                'format'       => $format,
                'records'      => isset( $profile['_records'] ) ? (int) $profile['_records'] : count( $rows ),
                'job_id'       => isset( $profile['_job_id'] ) ? (int) $profile['_job_id'] : 0,
                'first_order'  => isset( $profile['_first_order'] ) ? $profile['_first_order'] : null,
                'file'         => 'x.' . $format,
            ) );
            if ( $name === '' ) $name = $default;
            /* Never clobber a same-named file already on disk (e.g. two orders sharing
               a timestamp): append a short token before the extension. */
            if ( file_exists( $dir . '/' . $name ) ) {
                $name = preg_replace( '/(\.[a-z0-9]+)$/i', '-' . wp_generate_password( 4, false ) . '$1', $name );
            }
        } else {
            $name = $default;
        }
        $path = $dir . '/' . $name;

        $json_bare = ! empty( $profile['json_bare'] ) && Red_Headed_Soft_Lock::is_available( 'json_structure' );
        switch ( $format ) {
            case 'csv':    Red_Headed_Builder_CSV::build( $columns, $rows, $path, ',' ); break;
            case 'tsv':    Red_Headed_Builder_CSV::build( $columns, $rows, $path, "\t" ); break;
            case 'json':   Red_Headed_Builder_JSON::build( $columns, $rows, $path, false, $json_bare ); break;
            case 'ndjson': Red_Headed_Builder_JSON::build( $columns, $rows, $path, true ); break;
            case 'xml':    Red_Headed_Builder_XML::build( $columns, $rows, $path ); break;
            case 'xlsx':   Red_Headed_Builder_XLSX::build( $columns, $rows, $path ); break;
            default:       Red_Headed_Builder_CSV::build( $columns, $rows, $path, ',' );
        }
        return $path;
    }

    /* ────────── Deliver ────────── */
    protected static function deliver( $file, $profile, $format ) {
        $dest_list = isset( $profile['destinations'] ) ? (array) $profile['destinations'] : array();
        if ( empty( $dest_list ) ) return null; /* manual download path: file lives on disk */
        $delivered = array();
        $multi_ok  = Red_Headed_Soft_Lock::is_available( 'multi_destinations' );
        $i = 0;
        foreach ( $dest_list as $dest ) {
            $i++;
            if ( $i > 1 && ! $multi_ok ) break; /* Lite caps to 1 destination per run */
            $ok = Red_Headed_Destination_Dispatcher::ship( $dest, $file, $profile, $format );
            $delivered[] = array( 'destination' => $dest, 'ok' => $ok );
            /* v1.4.25 — log delivery result so the user can debug silent failures.
               The job stays "success" because the file IS built; but each destination
               outcome is now visible in debug.log + we surface the error in the
               job's error_message so it appears in the Exports list tooltip. */
            if ( is_wp_error( $ok ) ) {
                $msg = '[Red_Headed_Pro] destination ' . ( $dest['type'] ?? '?' ) . ' failed: ' . $ok->get_error_code() . ' — ' . $ok->get_error_message();
                error_log( $msg );
                global $wpdb;
                $jid = isset( $profile['_job_id'] ) ? (int) $profile['_job_id'] : 0;
                if ( $jid ) {
                    $existing = (string) $wpdb->get_var( $wpdb->prepare( "SELECT error_message FROM {$wpdb->prefix}rh_jobs WHERE id = %d", $jid ) );
                    $append = trim( $existing . "\n" . $msg );
                    $wpdb->update( "{$wpdb->prefix}rh_jobs", array( 'error_message' => substr( $append, 0, 1500 ) ), array( 'id' => $jid ) );
                }
                /* Retry on failure (e.g. the SAP/SFTP receiving server is momentarily
                   unreachable): queue this destination for re-delivery on the cron
                   tick, until it succeeds or hits the max attempts. */
                if ( ! empty( $profile['retry_on_fail'] ) && class_exists( 'Red_Headed_Retry' ) ) {
                    Red_Headed_Retry::enqueue( array(
                        'job_id'    => $jid,
                        'dest'      => $dest,
                        'file'      => $file,
                        'format'    => (string) $format,
                        'retry_max' => isset( $profile['retry_max'] ) ? (int) $profile['retry_max'] : 0,
                        'error'     => $ok->get_error_message(),
                    ) );
                }
            } else {
                error_log( '[Red_Headed_Pro] destination ' . ( $dest['type'] ?? '?' ) . ' OK' );
            }
        }
        return $delivered;
    }
}
