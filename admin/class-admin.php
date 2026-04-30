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
        $cap = 'manage_woocommerce';
        /* No top-level menu. All pages register under parent=null with a
           shared in-page nav at the top (Dashboard / Exports / Settings). */
        add_submenu_page( null, __( 'Harlequin Dashboard', 'pelican' ), '', $cap, 'pelican',          array( $this, 'render_dashboard' ) );
        add_submenu_page( null, __( 'Harlequin Exports',   'pelican' ), '', $cap, 'pelican-exports',  array( $this, 'render_exports' ) );
        add_submenu_page( null, __( 'Harlequin Settings',  'pelican' ), '', $cap, 'pelican-settings', array( $this, 'render_settings' ) );

        /* Settings deep-links forced ?tab= */
        foreach ( array( 'profiles', 'destinations', 'cron', 'webhooks', 'general' ) as $tab ) {
            $self = $this;
            add_submenu_page( null, ucfirst( $tab ), '', $cap, 'pelican-settings-' . $tab, function () use ( $self, $tab ) {
                $_GET['tab'] = $tab; $self->render_settings();
            } );
        }
    }
    public function enqueue_assets( $hook ) {
        if ( strpos( (string) $hook, 'pelican' ) === false ) return;
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
