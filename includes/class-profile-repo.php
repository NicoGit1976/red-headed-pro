<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Profile repository — CRUD on wp_pl_profiles. Lite limit: 1 profile.
 *
 * @package Pelican
 */
class Pelican_Profile_Repo {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'pl_profiles';
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
            if ( Pelican_Soft_Lock::is_locked( 'profile_unlimited' ) ) {
                $count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
                if ( $count >= 1 ) {
                    return new \WP_Error( 'lite_limit', __( 'Lite is capped to 1 export profile. Upgrade to Pro for unlimited profiles.', 'pelican' ) );
                }
            }
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

    protected static function encode( $data ) {
        /* schedule_meta is the catch-all bucket for new profile-level options (post_export_status,
           export_mode, line_item_header_fill) so we don't have to alter the wp_pl_profiles schema. */
        $meta = is_array( $data['schedule_meta'] ?? null ) ? $data['schedule_meta'] : array();
        foreach ( array( 'post_export_status', 'export_mode', 'line_item_header_fill' ) as $k ) {
            if ( isset( $data[ $k ] ) ) $meta[ $k ] = sanitize_text_field( (string) $data[ $k ] );
        }
        return array(
            'name'           => sanitize_text_field( $data['name']     ?? 'Untitled profile' ),
            'format'         => sanitize_key(        $data['format']   ?? 'csv' ),
            'filters'        => wp_json_encode(      $data['filters']  ?? array() ),
            'columns'        => wp_json_encode(      $data['columns']  ?? Pelican_Export_Engine::default_columns() ),
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
        foreach ( array( 'post_export_status', 'export_mode', 'line_item_header_fill' ) as $k ) {
            if ( isset( $row['schedule_meta'][ $k ] ) ) $row[ $k ] = $row['schedule_meta'][ $k ];
        }
        return $row;
    }
}
