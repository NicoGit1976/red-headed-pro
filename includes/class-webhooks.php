<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Webhooks dispatcher — Harlequin (Pro).
 *
 * Listens to pelican_export_generated / .delivered / .failed and POSTs JSON
 * to every registered HTTPS endpoint.
 *
 * @package Pelican
 */
class Pelican_Webhooks {
    const OPT = 'pelican_webhooks';
    public static function init() {
        if ( Pelican_Soft_Lock::is_locked( 'webhooks' ) ) return;
        add_action( 'pelican_export_generated', array( __CLASS__, 'on_generated' ), 10, 3 );
        add_action( 'pelican_export_delivered', array( __CLASS__, 'on_delivered' ), 10, 3 );
        add_action( 'pelican_export_failed',    array( __CLASS__, 'on_failed' ),    10, 3 );
    }
    public static function on_generated( $job_id, $profile, $file ) {
        self::dispatch( 'export.generated', array(
            'job_id'     => (int) $job_id,
            'profile_id' => (int) ( $profile['id'] ?? 0 ),
            'file'       => basename( (string) $file ),
        ) );
    }
    public static function on_delivered( $job_id, $profile, $delivered ) {
        self::dispatch( 'export.delivered', array(
            'job_id'     => (int) $job_id,
            'profile_id' => (int) ( $profile['id'] ?? 0 ),
            'destinations' => $delivered,
        ) );
    }
    public static function on_failed( $job_id, $profile, $err ) {
        self::dispatch( 'export.failed', array(
            'job_id'     => (int) $job_id,
            'profile_id' => (int) ( $profile['id'] ?? 0 ),
            'error'      => (string) $err,
        ) );
    }
    public static function register_url( $url, $secret = '', $events = array() ) {
        $url = esc_url_raw( $url );
        if ( ! $url || strpos( $url, 'https://' ) !== 0 ) return false;
        $hooks = get_option( self::OPT, array() );
        if ( ! is_array( $hooks ) ) $hooks = array();
        $hooks[ md5( $url ) ] = array(
            'url'    => $url,
            'secret' => is_string( $secret ) ? $secret : '',
            'events' => is_array( $events ) && ! empty( $events ) ? $events : array( 'export.generated', 'export.delivered', 'export.failed' ),
        );
        update_option( self::OPT, $hooks, false );
        return true;
    }
    public static function unregister_url( $url ) {
        $hooks = get_option( self::OPT, array() );
        if ( ! is_array( $hooks ) ) return false;
        $key = md5( esc_url_raw( $url ) );
        if ( ! isset( $hooks[ $key ] ) ) return false;
        unset( $hooks[ $key ] );
        update_option( self::OPT, $hooks, false );
        return true;
    }
    public static function list_urls() {
        $hooks = get_option( self::OPT, array() );
        return is_array( $hooks ) ? array_values( $hooks ) : array();
    }
    protected static function dispatch( $event, $data ) {
        $hooks = get_option( self::OPT, array() );
        if ( empty( $hooks ) ) return;
        $payload = array(
            'event'        => $event,
            'data'         => $data,
            'site'         => home_url(),
            'timestamp_ms' => (int) round( microtime( true ) * 1000 ),
            'plugin'       => 'pelican',
            'version'      => PELICAN_VERSION,
        );
        $body = wp_json_encode( $payload );
        foreach ( $hooks as $h ) {
            $events = isset( $h['events'] ) ? (array) $h['events'] : array();
            if ( ! empty( $events ) && ! in_array( $event, $events, true ) ) continue;
            $headers = array(
                'Content-Type'  => 'application/json; charset=UTF-8',
                'User-Agent'    => 'Pelican/' . PELICAN_VERSION,
                'X-Pelican-Event' => $event,
                'X-Pelican-Site'  => home_url(),
            );
            if ( ! empty( $h['secret'] ) ) {
                $headers['X-Pelican-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $h['secret'] );
            }
            wp_remote_post( $h['url'], array( 'headers' => $headers, 'body' => $body, 'timeout' => 10 ) );
        }
    }
}
