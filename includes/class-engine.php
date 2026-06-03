<?php
/**
 * Engine — orchestrator for Harlequin.
 *
 * @package Red_Headed_Pro
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Red_Headed_Engine {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    private function load_dependencies() {
        $base = RED_HEADED_PATH . 'includes/';
        require_once $base . 'class-soft-lock.php';
        require_once $base . 'class-installer.php';
        require_once $base . 'class-hub-registry.php';
        require_once $base . 'class-i18n.php';
        require_once $base . 'class-order-tracker.php';
        require_once $base . 'class-filename-resolver.php';
        require_once $base . 'class-expr-evaluator.php';

        require_once $base . 'builders/class-builder-csv.php';
        require_once $base . 'builders/class-builder-json.php';
        require_once $base . 'builders/class-builder-xml.php';
        require_once $base . 'builders/class-builder-xlsx.php';

        require_once $base . 'destinations/class-destination-base.php';
        require_once $base . 'destinations/class-destination-email.php';
        require_once $base . 'destinations/class-destination-sftp.php';
        require_once $base . 'destinations/class-destination-local-zip.php';
        require_once $base . 'destinations/class-destination-local-folder.php';
        require_once $base . 'destinations/class-destination-rest.php';
        require_once $base . 'destinations/class-destination-gdrive.php';
        require_once $base . 'destinations/class-destination-dispatcher.php';

        require_once $base . 'class-export-engine.php';
        require_once $base . 'class-profile-repo.php';
        require_once $base . 'class-connection-repo.php';
        require_once $base . 'class-retry.php';
        require_once $base . 'class-cron.php';
        require_once $base . 'class-auto-trigger.php';
        require_once $base . 'class-rest-api.php';
        require_once $base . 'class-webhooks.php';
        require_once $base . 'class-failure-notifier.php';
        require_once $base . 'class-wc-status.php';
    }
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'admin_init', array( 'Red_Headed_Installer', 'maybe_upgrade' ) );

        /* v1.4.10 — disabled: the Hub is the single source of truth for the
           ecosystem registry (per `feedback_three_layer_ecosystem.md`). This
           plugin's local override duplicated the entry → 2 sidebar menu items. */
        // Red_Headed_Hub_Registry::init();
        Red_Headed_I18n::init();
        Red_Headed_Order_Tracker::init();
        Red_Headed_Cron::init();
        Red_Headed_Auto_Trigger::init();
        Red_Headed_Webhooks::init();
        Red_Headed_REST_API::init();
        Red_Headed_Failure_Notifier::init();
        Red_Headed_WC_Status::init();
    }
    public function on_plugins_loaded() {
        load_plugin_textdomain( 'red-headed-pro', false, dirname( RED_HEADED_BASENAME ) . '/languages' );
    }
    public static function boot_admin() {
        if ( ! is_admin() ) return;
        if ( ! class_exists( 'Red_Headed_Admin' ) ) {
            require_once RED_HEADED_PATH . 'admin/class-admin.php';
        }
        if ( ! class_exists( 'Red_Headed_Bulk_Actions' ) ) {
            require_once RED_HEADED_PATH . 'admin/class-bulk-actions.php';
        }
        new Red_Headed_Admin();
        new Red_Headed_Bulk_Actions();
    }
}
