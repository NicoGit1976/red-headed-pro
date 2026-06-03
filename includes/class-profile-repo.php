<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Profile repository — CRUD on wp_pl_profiles. Lite limit: 1 profile.
 *
 * @package Red_Headed_Pro
 */
class Red_Headed_Profile_Repo {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rh_profiles';
    }
    public static function all() {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC', ARRAY_A );
        return array_map( array( __CLASS__, 'decode' ), (array) $rows );
    }
    public static function get( $id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A );
        return $row ? self::decode( $row ) : null;
    }
    public static function save( $data ) {
        global $wpdb;
        if ( ! isset( $data['id'] ) || ! $data['id'] ) {
            if ( Red_Headed_Soft_Lock::is_locked( 'profile_unlimited' ) ) {
                $count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
                if ( $count >= 1 ) {
                    return new \WP_Error( 'lite_limit', __( 'Lite is capped to 1 export profile. Upgrade to Pro for unlimited profiles.', 'red-headed-pro' ) );
                }
            }
        }
        /* v1.5.9 — Secret-preservation. The editor blanks secret fields (SFTP
           password, OAuth tokens) on load for security, so a blank submitted value
           means "unchanged", NOT "clear it". Without this, every profile edit
           (port, path, filename…) re-saves an EMPTY password and silently wipes the
           stored SFTP credential → delivery fails on the next run. Preserve on blank. */
        if ( ! empty( $data['id'] ) ) {
            $data = self::preserve_destination_secrets( $data, (int) $data['id'] );
        }
        $row = self::encode( $data );
        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( self::table(), $row, array( 'id' => (int) $data['id'] ) );
            return (int) $data['id'];
        }
        $wpdb->insert( self::table(), $row );
        return (int) $wpdb->insert_id;
    }
    public static function delete( $id ) {
        global $wpdb;
        return false !== $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
    }

    /**
     * Preserve stored destination secrets when the submitted value is blank.
     * The profile editor never re-renders saved passwords/tokens (security), so an
     * empty field on submit means "unchanged", not "clear it". Matches destinations
     * to the stored ones by type, in submission order. v1.5.9.
     *
     * @param array $data Incoming profile data (destinations may have blank secrets).
     * @param int   $id   Existing profile id to read stored secrets from.
     * @return array      $data with blank secrets backfilled from storage.
     */
    protected static function preserve_destination_secrets( $data, $id ) {
        if ( empty( $data['destinations'] ) || ! is_array( $data['destinations'] ) ) {
            return $data;
        }
        $existing = self::get( $id );
        if ( ! $existing || empty( $existing['destinations'] ) || ! is_array( $existing['destinations'] ) ) {
            return $data;
        }
        $by_type = array();
        foreach ( $existing['destinations'] as $d ) {
            if ( is_array( $d ) ) { $by_type[ $d['type'] ?? '' ][] = $d; }
        }
        $secret_keys = array( 'pass', 'pass_enc', 'token', 'access_token', 'access_token_enc', 'refresh_token', 'secret', 'api_key' );
        $cursor = array();
        foreach ( $data['destinations'] as &$dest ) {
            if ( ! is_array( $dest ) ) { continue; }
            $t   = $dest['type'] ?? '';
            $idx = $cursor[ $t ] ?? 0;
            $cursor[ $t ] = $idx + 1;
            $prev = $by_type[ $t ][ $idx ] ?? null;
            if ( ! is_array( $prev ) ) { continue; }
            foreach ( $secret_keys as $sk ) {
                $incoming = isset( $dest[ $sk ] ) ? (string) $dest[ $sk ] : '';
                if ( '' === $incoming && ! empty( $prev[ $sk ] ) ) {
                    $dest[ $sk ] = $prev[ $sk ];
                }
            }
        }
        unset( $dest );
        return $data;
    }

    protected static function encode( $data ) {
        /* schedule_meta is the catch-all bucket for new profile-level options (post_export_status,
           export_mode, line_item_header_fill) so we don't have to alter the wp_pl_profiles schema. */
        $meta = is_array( $data['schedule_meta'] ?? null ) ? $data['schedule_meta'] : array();
        foreach ( array(
            'post_export_status', 'export_mode', 'line_item_header_fill',
            /* Structured-output suite (Pro): JSON shape + nesting key + bare flag +
               build-time filename pattern + one-file-per-order toggle. */
            'json_shape', 'line_items_key', 'json_bare', 'filename_pattern', 'split_per_order',
            /* Delivery retry (Pro): re-attempt failed shipments until received. */
            'retry_on_fail', 'retry_max',
        ) as $k ) {
            if ( isset( $data[ $k ] ) ) $meta[ $k ] = sanitize_text_field( (string) $data[ $k ] );
        }
        return array(
            'name'           => sanitize_text_field( $data['name']     ?? 'Untitled profile' ),
            'format'         => sanitize_key(        $data['format']   ?? 'csv' ),
            'filters'        => wp_json_encode(      $data['filters']  ?? array() ),
            'columns'        => wp_json_encode(      $data['columns']  ?? Red_Headed_Export_Engine::default_columns() ),
            'destinations'   => wp_json_encode(      $data['destinations'] ?? array() ),
            'schedule'       => sanitize_key(        $data['schedule'] ?? 'manual' ),
            'schedule_meta'  => wp_json_encode(      $meta ),
            'auto_trigger'   => wp_json_encode(      $data['auto_trigger']  ?? array() ),
            'status'         => sanitize_key(        $data['status']   ?? 'active' ),
        );
    }
    protected static function decode( $row ) {
        if ( ! is_array( $row ) ) return $row;
        foreach ( array( 'filters', 'columns', 'destinations', 'schedule_meta', 'auto_trigger' ) as $f ) {
            $row[ $f ] = ! empty( $row[ $f ] ) ? ( json_decode( $row[ $f ], true ) ?: array() ) : array();
        }
        /* Hoist nested profile options to the top level for ergonomic access. */
        foreach ( array(
            'post_export_status', 'export_mode', 'line_item_header_fill',
            'json_shape', 'line_items_key', 'json_bare', 'filename_pattern', 'split_per_order',
            'retry_on_fail', 'retry_max',
        ) as $k ) {
            if ( isset( $row['schedule_meta'][ $k ] ) ) $row[ $k ] = $row['schedule_meta'][ $k ];
        }
        return $row;
    }
}
