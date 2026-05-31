<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * REST API — Harlequin (Pro).
 *
 * Routes:
 *   GET    /pelican/v1/profiles
 *   GET    /pelican/v1/profiles/{id}
 *   POST   /pelican/v1/profiles
 *   DELETE /pelican/v1/profiles/{id}
 *   POST   /pelican/v1/profiles/{id}/run     → fires an export, returns job
 *   GET    /pelican/v1/jobs                  → list with pagination
 *   GET    /pelican/v1/jobs/{id}
 *   GET    /pelican/v1/jobs/{id}/download    → signed PDF/CSV/etc.
 *
 * @package Pelican
 */
class Pelican_REST_API {
    const NS = 'pelican/v1';
    public static function init() {
        if ( Pelican_Soft_Lock::is_locked( 'rest_api' ) ) return;
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
    }
    public static function routes() {
        register_rest_route( self::NS, '/profiles', array(
            array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'list_profiles' ), 'permission_callback' => array( __CLASS__, 'cap_read' ) ),
            array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save_profile' ),  'permission_callback' => array( __CLASS__, 'cap_write' ) ),
        ) );
        register_rest_route( self::NS, '/profiles/(?P<id>\d+)', array(
            array( 'methods' => 'GET',    'callback' => array( __CLASS__, 'get_profile' ),    'permission_callback' => array( __CLASS__, 'cap_read' ) ),
            array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_profile' ), 'permission_callback' => array( __CLASS__, 'cap_write' ) ),
        ) );
        register_rest_route( self::NS, '/profiles/(?P<id>\d+)/run', array(
            'methods' => 'POST', 'callback' => array( __CLASS__, 'run_profile' ), 'permission_callback' => array( __CLASS__, 'cap_write' ),
        ) );
        register_rest_route( self::NS, '/jobs', array(
            'methods' => 'GET', 'callback' => array( __CLASS__, 'list_jobs' ), 'permission_callback' => array( __CLASS__, 'cap_read' ),
        ) );
        register_rest_route( self::NS, '/jobs/(?P<id>\d+)', array(
            'methods' => 'GET', 'callback' => array( __CLASS__, 'get_job' ), 'permission_callback' => array( __CLASS__, 'cap_read' ),
        ) );
        register_rest_route( self::NS, '/jobs/(?P<id>\d+)/download', array(
            'methods' => 'GET', 'callback' => array( __CLASS__, 'download_job' ), 'permission_callback' => array( __CLASS__, 'cap_read' ),
        ) );
    }
    public static function cap_read()  { return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' ); }
    public static function cap_write() { return current_user_can( 'manage_woocommerce' ); }

    public static function list_profiles() { return rest_ensure_response( Pelican_Profile_Repo::all() ); }
    public static function get_profile( $req ) {
        $p = Pelican_Profile_Repo::get( (int) $req->get_param( 'id' ) );
        return $p ? rest_ensure_response( $p ) : new \WP_Error( 'not_found', __( 'Profile not found.', 'red-headed-pro' ), array( 'status' => 404 ) );
    }
    public static function save_profile( $req ) {
        $data = $req->get_json_params();
        $id   = Pelican_Profile_Repo::save( is_array( $data ) ? $data : array() );
        if ( is_wp_error( $id ) ) return $id;
        return rest_ensure_response( Pelican_Profile_Repo::get( $id ) );
    }
    public static function delete_profile( $req ) {
        Pelican_Profile_Repo::delete( (int) $req->get_param( 'id' ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }
    public static function run_profile( $req ) {
        $p = Pelican_Profile_Repo::get( (int) $req->get_param( 'id' ) );
        if ( ! $p ) return new \WP_Error( 'not_found', __( 'Profile not found.', 'red-headed-pro' ), array( 'status' => 404 ) );
        $job = Pelican_Export_Engine::run( $p, 'rest' );
        if ( is_wp_error( $job ) ) return $job;
        return rest_ensure_response( array( 'job_id' => $job ) );
    }
    public static function list_jobs( $req ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'pl_jobs';
        $rows = $wpdb->get_results( 'SELECT * FROM ' . $tbl . ' ORDER BY started_at DESC LIMIT 100', ARRAY_A );
        return rest_ensure_response( $rows ?: array() );
    }
    public static function get_job( $req ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'pl_jobs';
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $tbl . ' WHERE id = %d', (int) $req->get_param( 'id' ) ), ARRAY_A );
        return $row ? rest_ensure_response( $row ) : new \WP_Error( 'not_found', __( 'Job not found.', 'red-headed-pro' ), array( 'status' => 404 ) );
    }
    public static function download_job( $req ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'pl_jobs';
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $tbl . ' WHERE id = %d', (int) $req->get_param( 'id' ) ), ARRAY_A );
        if ( ! $row ) return new \WP_Error( 'not_found', __( 'Job not found.', 'red-headed-pro' ), array( 'status' => 404 ) );
        $uploads = wp_upload_dir();
        $abs = trailingslashit( $uploads['basedir'] ) . ltrim( (string) $row['file_path'], '/' );
        if ( ! file_exists( $abs ) ) return new \WP_Error( 'file_missing', __( 'File missing on disk.', 'red-headed-pro' ), array( 'status' => 410 ) );
        /* Stream the raw bytes and exit BEFORE the REST server JSON-encodes the
           response. Returning the file content as a WP_REST_Response string makes
           the server wrap it in quotes + escape it (double-encoded), which yields
           a malformed download that editors refuse to open. */
        $ext  = strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) );
        $mimes = array( 'json' => 'application/json', 'ndjson' => 'application/x-ndjson', 'csv' => 'text/csv', 'tsv' => 'text/tab-separated-values', 'xml' => 'application/xml', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'zip' => 'application/zip' );
        $mime = isset( $mimes[ $ext ] ) ? $mimes[ $ext ] : 'application/octet-stream';
        if ( function_exists( 'nocache_headers' ) ) nocache_headers();
        while ( ob_get_level() > 0 ) { ob_end_clean(); }
        header( 'Content-Type: ' . $mime . '; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $abs ) ) . '"' );
        header( 'Content-Length: ' . (string) filesize( $abs ) );
        readfile( $abs );
        exit;
    }
}
