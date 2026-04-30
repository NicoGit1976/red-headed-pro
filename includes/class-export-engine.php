<?php
/**
 * Export Engine — orchestrates a single export run.
 *
 * Pipeline: profile → fetch orders (with filters) → map columns → build file
 * (format-specific builder) → save to uploads/pelican/exports/ → ship to
 * destinations (one or many) → log job row → fire webhooks.
 *
 * @package Pelican
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Pelican_Export_Engine {

    /** @return int|WP_Error  job ID on success, WP_Error on failure */
    public static function run( $profile, $trigger_source = 'manual' ) {
        global $wpdb;
        $jobs_tbl = $wpdb->prefix . 'pl_jobs';
        $started  = (int) round( microtime( true ) * 1000 );
        $profile  = is_array( $profile ) ? $profile : array();

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
            $rows    = array_map( function ( $order ) use ( $columns ) {
                return self::map_row( $order, $columns );
            }, $orders );

            $format = isset( $profile['format'] ) ? sanitize_key( $profile['format'] ) : 'csv';
            $format = self::guard_format( $format );
            $file   = self::build_file( $format, $columns, $rows, $profile );

            if ( ! $file || ! file_exists( $file ) ) {
                throw new \RuntimeException( 'File build failed.' );
            }

            $delivered = self::deliver( $file, $profile, $format );

            $duration = (int) round( microtime( true ) * 1000 ) - $started;
            $uploads  = wp_upload_dir();
            $rel      = ltrim( str_replace( $uploads['basedir'], '', $file ), '/\\' );
            $wpdb->update( $jobs_tbl, array(
                'file_path'     => $rel,
                'file_size'     => @filesize( $file ),
                'records_count' => count( $rows ),
                'status'        => 'success',
                'duration_ms'   => $duration,
                'finished_at'   => current_time( 'mysql' ),
            ), array( 'id' => $job_id ) );

            do_action( 'pelican_export_generated', $job_id, $profile, $file );
            if ( $delivered ) {
                do_action( 'pelican_export_delivered', $job_id, $profile, $delivered );
            }

            return $job_id;
        } catch ( \Throwable $e ) {
            $wpdb->update( $jobs_tbl, array(
                'status'        => 'failed',
                'error_message' => substr( $e->getMessage(), 0, 800 ),
                'finished_at'   => current_time( 'mysql' ),
            ), array( 'id' => $job_id ) );
            do_action( 'pelican_export_failed', $job_id, $profile, $e->getMessage() );
            return new \WP_Error( 'export_failed', $e->getMessage() );
        }
    }

    /* ────────── Filters → orders ────────── */
    public static function fetch_orders( $filters ) {
        if ( ! function_exists( 'wc_get_orders' ) ) return array();
        $args = array( 'limit' => -1, 'orderby' => 'date', 'order' => 'DESC' );
        $args['status'] = isset( $filters['status'] ) && $filters['status'] ? (array) $filters['status'] : array_keys( wc_get_order_statuses() );
        if ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) ) {
            $from = ! empty( $filters['date_from'] ) ? sanitize_text_field( $filters['date_from'] ) : '1970-01-01';
            $to   = ! empty( $filters['date_to'] )   ? sanitize_text_field( $filters['date_to'] )   : current_time( 'Y-m-d' );
            $args['date_created'] = $from . '...' . $to;
        }
        if ( ! empty( $filters['payment_method'] ) ) $args['payment_method'] = sanitize_key( $filters['payment_method'] );
        $orders = wc_get_orders( $args );
        if ( ! empty( $filters['shipping_method'] ) || ! empty( $filters['sku_pattern'] ) || ! empty( $filters['category'] ) ) {
            /* Pro filters — refined post-fetch */
            if ( ! Pelican_Soft_Lock::is_available( 'filters_advanced' ) ) {
                return $orders;
            }
            $orders = array_filter( $orders, function ( $o ) use ( $filters ) {
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
                return true;
            } );
        }
        return $orders;
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
                $out[] = array(
                    'key'   => $key,
                    'label' => (string) ( $col['label'] ?? self::default_label_for( $key ) ),
                );
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
            $row[ $key ] = self::resolve_column( $order, $key );
        }
        return $row;
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
        );
        /* Allow third-party plugins to register custom columns. */
        return apply_filters( 'pelican_column_catalog', $cat );
    }

    public static function column_groups() {
        return array(
            'order'    => __( 'Order',         'pelican' ),
            'totals'   => __( 'Totals',        'pelican' ),
            'payment'  => __( 'Payment & Shipping', 'pelican' ),
            'billing'  => __( 'Billing address',    'pelican' ),
            'shipping' => __( 'Shipping address',   'pelican' ),
            'meta'     => __( 'Custom meta',        'pelican' ),
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
            case 'customer_note':       return $order->get_customer_note();
            default:
                if ( strpos( $key, 'meta:' ) === 0 ) {
                    return $order->get_meta( substr( $key, 5 ) );
                }
                return apply_filters( 'pelican_resolve_column', '', $key, $order );
        }
    }

    /* ────────── Format guard ────────── */
    protected static function guard_format( $format ) {
        $allowed = array( 'csv' );
        if ( Pelican_Soft_Lock::is_available( 'format_xlsx' ) )   $allowed[] = 'xlsx';
        if ( Pelican_Soft_Lock::is_available( 'format_json' ) )   $allowed[] = 'json';
        if ( Pelican_Soft_Lock::is_available( 'format_xml' ) )    $allowed[] = 'xml';
        if ( Pelican_Soft_Lock::is_available( 'format_ndjson' ) ) $allowed[] = 'ndjson';
        if ( Pelican_Soft_Lock::is_available( 'format_tsv' ) )    $allowed[] = 'tsv';
        return in_array( $format, $allowed, true ) ? $format : 'csv';
    }

    /* ────────── Build file ────────── */
    protected static function build_file( $format, $columns, $rows, $profile ) {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . 'pelican/exports/' . date( 'Y/m' );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            $up = trailingslashit( $uploads['basedir'] ) . 'pelican';
            if ( ! file_exists( $up . '/.htaccess' ) ) {
                @file_put_contents( $up . '/.htaccess', "Options -Indexes\nOrder Allow,Deny\nDeny from all\n" );
                @file_put_contents( $up . '/index.php',  "<?php // Silence is golden.\n" );
            }
        }
        $base = isset( $profile['name'] ) ? sanitize_file_name( $profile['name'] ) : 'export';
        $name = $base . '-' . date( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.' . $format;
        $path = $dir . '/' . $name;

        switch ( $format ) {
            case 'csv':    Pelican_Builder_CSV::build( $columns, $rows, $path, ',' ); break;
            case 'tsv':    Pelican_Builder_CSV::build( $columns, $rows, $path, "\t" ); break;
            case 'json':   Pelican_Builder_JSON::build( $columns, $rows, $path, false ); break;
            case 'ndjson': Pelican_Builder_JSON::build( $columns, $rows, $path, true ); break;
            case 'xml':    Pelican_Builder_XML::build( $columns, $rows, $path ); break;
            case 'xlsx':   Pelican_Builder_XLSX::build( $columns, $rows, $path ); break;
            default:       Pelican_Builder_CSV::build( $columns, $rows, $path, ',' );
        }
        return $path;
    }

    /* ────────── Deliver ────────── */
    protected static function deliver( $file, $profile, $format ) {
        $dest_list = isset( $profile['destinations'] ) ? (array) $profile['destinations'] : array();
        if ( empty( $dest_list ) ) return null; /* manual download path: file lives on disk */
        $delivered = array();
        $multi_ok  = Pelican_Soft_Lock::is_available( 'multi_destinations' );
        $i = 0;
        foreach ( $dest_list as $dest ) {
            $i++;
            if ( $i > 1 && ! $multi_ok ) break; /* Lite caps to 1 destination per run */
            $ok = Pelican_Destination_Dispatcher::ship( $dest, $file, $profile, $format );
            $delivered[] = array( 'destination' => $dest, 'ok' => $ok );
        }
        return $delivered;
    }
}
