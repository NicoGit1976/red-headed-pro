<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Admin — registers the menu under Froggy Hub (no top-level WP menu) and
 * enqueues assets on Pelican pages.
 *
 * @package Pelican
 */
class Pelican_Admin {
    public function __construct() {
        /* v1.4.16 — admin_menu prio 110 (was: 11). Hub registers parent 'froggy-hub'
           at prio 98. If Pelican registers BEFORE the parent exists, WP computes
           the wrong hookname (admin_page_* instead of froggy-hub_page_*) → ?page=
           lookup fails → "no permission". Must fire AFTER prio 98. */
        add_action( 'admin_menu', array( $this, 'register_menu' ), 110 );
        add_action( 'wp_ajax_pelican_preview_profile', array( $this, 'ajax_preview_profile' ) );
        add_action( 'wp_ajax_pelican_preview_job',     array( $this, 'ajax_preview_job' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_pelican_save_profile', array( $this, 'ajax_save_profile' ) );
        add_action( 'wp_ajax_pelican_delete_profile', array( $this, 'ajax_delete_profile' ) );
        add_action( 'wp_ajax_pelican_run_profile', array( $this, 'ajax_run_profile' ) );
    }
    public function register_menu() {
        /* v1.4.15 — Cap lowered to 'manage_options' (was 'manage_woocommerce').
           Admin users without WC active (or with custom WC cap mapping) were getting
           "no permission" on ?page=red-headed-pro. Dashboard / settings / exports are
           admin tools — manage_options is the right gate. WC-specific operations
           (run profile, sync orders) keep their own current_user_can('manage_woocommerce')
           checks inside AJAX handlers. */
        $cap = 'manage_options';
        /* v1.4.14 — Dashboard registered under 'froggy-hub' parent (was: null).
           This makes the Hub placeholder dedup catch it via $submenu['froggy-hub']
           and skip creating a duplicate placeholder → no double-entry, no
           placeholder→Pelican redirect loop. Other slugs stay headless (parent=null)
           because they're routed-to via in-page nav, not the sidebar. */
        add_submenu_page( 'froggy-hub', __( 'Red Headed Dashboard', 'pelican' ), 'Red Headed', $cap, 'red-headed-pro', array( $this, 'render_dashboard' ) );
        add_submenu_page( null, __( 'Red Headed Exports',   'pelican' ), '', $cap, 'red-headed-pro-exports',  array( $this, 'render_exports' ) );
        add_submenu_page( null, __( 'Red Headed Settings',  'pelican' ), '', $cap, 'red-headed-pro-settings', array( $this, 'render_settings' ) );

        /* Settings deep-links forced ?tab= */
        foreach ( array( 'profiles', 'destinations', 'cron', 'webhooks', 'general' ) as $tab ) {
            $self = $this;
            add_submenu_page( null, ucfirst( $tab ), '', $cap, 'red-headed-pro-settings-' . $tab, function () use ( $self, $tab ) {
                $_GET['tab'] = $tab; $self->render_settings();
            } );
        }
    }
    public function enqueue_assets( $hook ) {
        if ( strpos( (string) $hook, 'red-headed-pro' ) === false ) return;
        wp_enqueue_style( 'pelican', PELICAN_URL . 'assets/css/pelican.css', array(), PELICAN_VERSION );
        wp_enqueue_script( 'pelican', PELICAN_URL . 'assets/js/pelican.js', array( 'jquery' ), PELICAN_VERSION, true );
        wp_localize_script( 'pelican', 'PelicanData', array(
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'pelican' ),
            'restUrl'   => rest_url(),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'edition'   => Pelican_Soft_Lock::edition(),
        ) );
    }
    public function render_dashboard() { include PELICAN_PATH . 'partials/view-dashboard.php'; }
    public function render_exports()   { include PELICAN_PATH . 'partials/view-exports.php'; }
    public function render_settings()  { include PELICAN_PATH . 'partials/view-settings.php'; }

    /* ────────── AJAX ────────── */
    public function ajax_save_profile() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $data = isset( $_POST['profile'] ) ? json_decode( wp_unslash( $_POST['profile'] ), true ) : null;
        if ( ! is_array( $data ) ) wp_send_json_error( array( 'message' => 'Invalid profile JSON.' ) );
        $id = Pelican_Profile_Repo::save( $data );
        if ( is_wp_error( $id ) ) wp_send_json_error( array( 'message' => $id->get_error_message() ) );
        wp_send_json_success( Pelican_Profile_Repo::get( $id ) );
    }
    public function ajax_delete_profile() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        $ok = Pelican_Profile_Repo::delete( $id );
        wp_send_json_success( array( 'deleted' => $ok ) );
    }
    public function ajax_run_profile() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        $p  = Pelican_Profile_Repo::get( $id );
        if ( ! $p ) wp_send_json_error( array( 'message' => 'Profile not found.' ) );
        $job = Pelican_Export_Engine::run( $p, 'manual' );
        if ( is_wp_error( $job ) ) wp_send_json_error( array( 'message' => $job->get_error_message() ) );
        /* v1.4.20 — surface a warning when the export ran but matched 0 orders. */
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT records_count FROM {$wpdb->prefix}pl_jobs WHERE id = %d", $job ), ARRAY_A );
        $payload = array( 'job_id' => $job, 'records' => isset( $row['records_count'] ) ? (int) $row['records_count'] : 0 );
        if ( $payload['records'] === 0 ) {
            $payload['warning'] = __( 'Export ran but no orders matched your filters. Check the profile settings (statuses, date range).', 'pelican' );
        }
        wp_send_json_success( $payload );
    }

    /* v1.4.20 — Preview profile: dry-run the filters and return the first 5 mapped rows
       (no job created, no file saved). Lets the user check their filters before running. */
    public function ajax_preview_profile() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        $p  = Pelican_Profile_Repo::get( $id );
        if ( ! $p ) wp_send_json_error( array( 'message' => 'Profile not found.' ) );
        $orders  = Pelican_Export_Engine::fetch_orders( isset( $p['filters'] ) ? (array) $p['filters'] : array() );
        $columns = Pelican_Export_Engine::normalize_columns( ! empty( $p['columns'] ) ? (array) $p['columns'] : Pelican_Export_Engine::default_columns() );
        $sample  = array_slice( $orders, 0, 5 );
        /* map_row returns an assoc array keyed by column slug. Normalize to
           indexed values (in column order) so the JS preview can render rows
           with .map() — same shape as the CSV preview path (fgetcsv). */
        $rows = array_map( function ( $o ) use ( $columns ) {
            $assoc = Pelican_Export_Engine::map_row( $o, $columns );
            $vals = array();
            foreach ( $columns as $c ) {
                $k = is_array( $c ) ? ( $c['key'] ?? '' ) : (string) $c;
                $vals[] = isset( $assoc[ $k ] ) ? $assoc[ $k ] : '';
            }
            return $vals;
        }, $sample );
        wp_send_json_success( array(
            'count'   => count( $orders ),
            'columns' => array_map( function ( $c ) { return $c['label'] ?? ( $c['key'] ?? '' ); }, $columns ),
            'rows'    => $rows,
        ) );
    }

    /* v1.4.20 — Preview job: read the first ~10 lines of the export file (CSV/JSON only;
       binary formats fall back to "open file directly"). */
    public function ajax_preview_job() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $jid = (int) ( $_POST['id'] ?? 0 );
        global $wpdb;
        $j = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pl_jobs WHERE id = %d", $jid ), ARRAY_A );
        if ( ! $j || empty( $j['file_path'] ) ) wp_send_json_error( array( 'message' => 'Job or file not found.' ) );
        $u   = wp_upload_dir();
        $abs = trailingslashit( $u['basedir'] ) . ltrim( $j['file_path'], '/\\' );
        if ( ! file_exists( $abs ) ) wp_send_json_error( array( 'message' => 'File missing on disk.' ) );
        $format = strtolower( $j['format'] );
        $rows = array(); $columns = array();
        if ( in_array( $format, array( 'csv', 'tsv' ), true ) ) {
            $sep = $format === 'tsv' ? "\t" : ',';
            if ( ( $fh = fopen( $abs, 'r' ) ) !== false ) {
                $header = fgetcsv( $fh, 0, $sep );
                if ( is_array( $header ) ) $columns = $header;
                $count = 0;
                while ( ( $r = fgetcsv( $fh, 0, $sep ) ) !== false && $count < 10 ) {
                    $rows[] = $r; $count++;
                }
                fclose( $fh );
            }
        } elseif ( $format === 'json' || $format === 'ndjson' ) {
            $fh = fopen( $abs, 'r' ); $count = 0;
            if ( $fh ) {
                if ( $format === 'json' ) {
                    $raw = stream_get_contents( $fh ); $data = json_decode( $raw, true );
                    $sample = is_array( $data ) ? array_slice( $data, 0, 10 ) : array();
                    if ( $sample && is_array( $sample[0] ) ) $columns = array_keys( $sample[0] );
                    foreach ( $sample as $r ) $rows[] = array_values( (array) $r );
                } else {
                    while ( ! feof( $fh ) && $count < 10 ) {
                        $line = trim( fgets( $fh ) ); if ( $line === '' ) continue;
                        $r = json_decode( $line, true );
                        if ( $count === 0 && is_array( $r ) ) $columns = array_keys( $r );
                        $rows[] = array_values( (array) $r ); $count++;
                    }
                }
                fclose( $fh );
            }
        } else {
            wp_send_json_success( array( 'unsupported' => true, 'format' => $format ) );
        }
        wp_send_json_success( array(
            'columns' => $columns,
            'rows'    => $rows,
            'total'   => (int) $j['records_count'],
        ) );
    }
}
