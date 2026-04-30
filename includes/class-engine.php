<?php
/**
 * Engine — orchestrator for Harlequin.
 *
 * @package Pelican
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Pelican_Engine {
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
        $base = PELICAN_PATH . 'includes/';
        require_once $base . 'class-soft-lock.php';
        require_once $base . 'class-installer.php';
        require_once $base . 'class-hub-registry.php';
        require_once $base . 'class-i18n.php';

        require_once $base . 'builders/class-builder-csv.php';
        require_once $base . 'builders/class-builder-json.php';
        require_once $base . 'builders/class-builder-xml.php';
        require_once $base . 'builders/class-builder-xlsx.php';

        require_once $base . 'destinations/class-destination-base.php';
        require_once $base . 'destinations/class-destination-email.php';
        require_once $base . 'destinations/class-destination-sftp.php';
        require_once $base . 'destinations/class-destination-local-zip.php';
        require_once $base . 'destinations/class-destination-rest.php';
        require_once $base . 'destinations/class-destination-gdrive.php';
        require_once $base . 'destinations/class-destination-dispatcher.php';

        require_once $base . 'class-export-engine.php';
        require_once $base . 'class-profile-repo.php';
        require_once $base . 'class-cron.php';
        require_once $base . 'class-auto-trigger.php';
        require_once $base . 'class-rest-api.php';
        require_once $base . 'class-webhooks.php';
    }
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'admin_init', array( 'Pelican_Installer', 'maybe_upgrade' ) );

        Pelican_Hub_Registry::init();
        Pelican_I18n::init();
        Pelican_Cron::init();
        Pelican_Auto_Trigger::init();
        Pelican_Webhooks::init();
        Pelican_REST_API::init();
    }
    public function on_plugins_loaded() {
        load_plugin_textdomain( 'pelican', false, dirname( PELICAN_BASENAME ) . '/languages' );
    }
    public static function boot_admin() {
        if ( ! is_admin() ) return;
        if ( ! class_exists( 'Pelican_Admin' ) ) {
            require_once PELICAN_PATH . 'admin/class-admin.php';
        }
        if ( ! class_exists( 'Pelican_Bulk_Actions' ) ) {
            require_once PELICAN_PATH . 'admin/class-bulk-actions.php';
        }
        new Pelican_Admin();
        new Pelican_Bulk_Actions();
    }
}
