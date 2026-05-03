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
        add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
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
        add_submenu_page( 'froggy-hub', __( 'Red-Headed Dashboard', 'pelican' ), 'Red-Headed Pro', $cap, 'red-headed-pro', array( $this, 'render_dashboard' ) );
        add_submenu_page( null, __( 'Red-Headed Exports',   'pelican' ), '', $cap, 'red-headed-pro-exports',  array( $this, 'render_exports' ) );
        add_submenu_page( null, __( 'Red-Headed Settings',  'pelican' ), '', $cap, 'red-headed-pro-settings', array( $this, 'render_settings' ) );

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
        wp_send_json_success( array( 'job_id' => $job ) );
    }
}
