<?php
/**
 * Plugin Name:       Red Headed (Pro) — Orders Export Manager
 * Plugin URI:        https://thelionfrog.com
 * Description:       Exports WooCommerce orders everywhere, anytime. Bulk + auto exports, multi-format (CSV / XLSX / JSON / XML / NDJSON / TSV) with structured JSON shapes (labeled, nested line items, bare array), multi-destination (Email / SFTP / Google Drive / Download / REST / Local ZIP / Local folder), custom filename patterns, one-file-per-order split, cron + status-driven triggers. Mascot: Red-Headed Poison Frog. Pro edition. Part of Ultimate Woo Powertools (by The Lion Frog).
 * Version:           1.5.6
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            The Lion Frog Team
 * Author URI:        https://thelionfrog.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       red-headed-pro
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.5
 *
 * @package Red_Headed_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RED_HEADED_VERSION', '1.5.6' );
define( 'RED_HEADED_EDITION',  'pro' );
define( 'RED_HEADED_FILE',     __FILE__ );
define( 'RED_HEADED_PATH',     plugin_dir_path( __FILE__ ) );
define( 'RED_HEADED_URL',      plugin_dir_url( __FILE__ ) );
define( 'RED_HEADED_BASENAME', plugin_basename( __FILE__ ) );
define( 'RED_HEADED_SLUG',     'red-headed-pro' );
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'FH_Plugin_Updater' ) ) {
        FH_Plugin_Updater::register( [
            'slug'        => 'red-headed-pro',
            'plugin_file' => __FILE__,
            'name'        => 'Red Headed Pro',
            'icon_url'    => RED_HEADED_URL . 'assets/img/red-headed-pro.webp',
        ] );
    }
    add_filter( 'the_froggy_hub_quick_actions', function ( $actions ) {
        $actions[] = [
            'label'       => __( 'Export orders', 'red-headed-pro' ),
            'icon'        => '📦',
            'url'         => admin_url( 'admin.php?page=red-headed-pro-exports&action=new' ),
            'tooltip'     => __( 'Run a manual order export', 'red-headed-pro' ),
            'plugin_slug' => 'red-headed-pro',
            'is_primary'  => true,
        ];
        $actions[] = [
            'label'       => __( 'Export history', 'red-headed-pro' ),
            'icon'        => '📋',
            'url'         => admin_url( 'admin.php?page=red-headed-pro-exports&tab=history' ),
            'tooltip'     => __( 'View past export jobs and downloads', 'red-headed-pro' ),
            'plugin_slug' => 'red-headed-pro',
        ];
        $actions[] = [
            'label'       => __( 'Schedules', 'red-headed-pro' ),
            'icon'        => '⏰',
            'url'         => admin_url( 'admin.php?page=red-headed-pro-settings-cron' ),
            'tooltip'     => __( 'Configure automatic export schedules', 'red-headed-pro' ),
            'plugin_slug' => 'red-headed-pro',
        ];
        $actions[] = [
            'label'       => __( 'Settings', 'red-headed-pro' ),
            'icon'        => '⚙️',
            'url'         => admin_url( 'admin.php?page=red-headed-pro-settings' ),
            'tooltip'     => __( 'Open Red-Headed settings', 'red-headed-pro' ),
            'plugin_slug' => 'red-headed-pro',
        ];
        return $actions;
    } );
    /* Hub Quick Actions — KPI strip (cached 5 min). */
    add_filter( 'the_froggy_hub_quick_actions_stats', function ( $stats ) {
        $cached = get_transient( 'fh_qa_stats_red_headed_pro' );
        if ( false !== $cached ) { $stats['red-headed-pro'] = $cached; return $stats; }
        global $wpdb;
        $jobs_t     = $wpdb->prefix . 'rh_jobs';
        $profiles_t = $wpdb->prefix . 'rh_profiles';
        $week_ago   = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        $week       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $jobs_t WHERE started_at >= %s", $week_ago ) );
        $profiles   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $profiles_t" );
        $kpis = [
            [ 'icon' => '📦', 'value' => (string) $week,     'label' => __( 'last 7 days', 'red-headed-pro' ), 'tone' => 'success' ],
            [ 'icon' => '⚙️', 'value' => (string) $profiles, 'label' => __( 'profiles', 'red-headed-pro' ),    'tone' => 'info' ],
        ];
        set_transient( 'fh_qa_stats_red_headed_pro', $kpis, 5 * MINUTE_IN_SECONDS );
        $stats['red-headed-pro'] = $kpis;
        return $stats;
    } );
}, 5 );
/* v1.4.19 — Download handler. Fires at admin_init prio 1 BEFORE WP outputs
   any HTML header. Streams the export file as a clean attachment. */
add_action( 'admin_init', function () {
    $dl = ! empty( $_GET['rh_dl'] ) ? (int) $_GET['rh_dl'] : ( ! empty( $_GET['red_headed_dl'] ) ? (int) $_GET['red_headed_dl'] : 0 );
    if ( ! $dl ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;
    $jobs_tbl = $wpdb->prefix . 'rh_jobs';
    $j = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jobs_tbl} WHERE id = %d", $dl ), ARRAY_A );
    if ( ! $j || empty( $j['file_path'] ) ) return;
    $u = wp_upload_dir();
    $abs = trailingslashit( $u['basedir'] ) . ltrim( $j['file_path'], '/\\' );
    if ( ! file_exists( $abs ) ) return;
    while ( ob_get_level() ) ob_end_clean(); // drop any buffered HTML
    nocache_headers();
    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="' . basename( $abs ) . '"' );
    header( 'Content-Length: ' . filesize( $abs ) );
    readfile( $abs );
    exit;
}, 1 );

/* Legacy 'red-headed-pro*' admin-URL 301 redirects were removed in the de-Red_Headed_Pro
   refactor (v1.5.3). The admin pages have shipped as 'red-headed-pro*' for many
   versions; keeping a self-referential map here would risk a redirect loop. */


/* Composer autoloader (mPDF/PhpSpreadsheet/phpseclib live in vendor when installed). */
if ( file_exists( RED_HEADED_PATH . 'vendor/autoload.php' ) ) {
    require_once RED_HEADED_PATH . 'vendor/autoload.php';
}

require_once RED_HEADED_PATH . 'includes/class-engine.php';

/* HPOS compatibility — declare WC custom_order_tables support. */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/* Activation: deactivate Lite if present, install DB. */
register_activation_hook( __FILE__, function () {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    /* v1.4.42 — path post-rebrand (was legacy 'woo-order-lite/...'). */
    if ( is_plugin_active( 'red-headed-lite/red-headed-lite.php' ) ) {
        deactivate_plugins( 'red-headed-lite/red-headed-lite.php' );
        set_transient( 'red_headed_lite_was_deactivated', 1, 30 );
    }
    require_once RED_HEADED_PATH . 'includes/class-installer.php';
    Red_Headed_Installer::activate();
} );
register_deactivation_hook( __FILE__, function () {
    require_once RED_HEADED_PATH . 'includes/class-installer.php';
    Red_Headed_Installer::deactivate();
} );

add_action( 'admin_notices', function () {
    if ( ! get_transient( 'red_headed_lite_was_deactivated' ) ) return;
    delete_transient( 'red_headed_lite_was_deactivated' );
    echo '<div class="notice notice-success is-dismissible" style="border-left-color:#10B981;">';
    echo '<p><strong>🐸 ' . esc_html__( 'Red Headed Pro activated.', 'red-headed-pro' ) . '</strong></p>';
    echo '<p>' . esc_html__( 'Red Headed Lite has been deactivated automatically. All your data and settings have been preserved.', 'red-headed-pro' ) . '</p>';
    echo '</div>';
} );

/* Boot — load classes, register filters/hooks (no admin yet). */
Red_Headed_Engine::instance();

/* Hub Soft Lock at boot.
 *   - Hub active + license valid     → Red_Headed_Engine::boot_admin()
 *   - Hub active + license invalid   → FH_Soft_Lock::register() (branded landing)
 *   - Hub absent (standalone / dev)  → boot_admin()
 */
add_action( 'plugins_loaded', function () {
    if ( defined( 'FH_PATH' ) && class_exists( 'FH_License_Manager' ) && class_exists( 'FH_Soft_Lock' ) ) {
        $license_manager = new FH_License_Manager();
        $status          = $license_manager->check_license( RED_HEADED_SLUG );
        if ( FH_Soft_Lock::is_valid( $status ) ) {
            Red_Headed_Engine::boot_admin();
        } else {
            FH_Soft_Lock::register( RED_HEADED_SLUG, array(
                'page_slug'      => RED_HEADED_SLUG, // v1.4.11 — match Hub placeholder slug to dedupe sidebar entry
                'plugin_name'    => 'Red Headed',
                'plugin_icon'    => RED_HEADED_URL . 'assets/img/icon.png',
                'license_status' => $status,
                'features'       => array(
                    __( '6 formats: CSV · TSV · JSON · NDJSON · XML · XLSX', 'red-headed-pro' ),
                    __( 'Unlimited email delivery (no 30/24h cap)', 'red-headed-pro' ),
                    __( '6 destinations: Email · SFTP · Google Drive · REST · Local ZIP · Direct download', 'red-headed-pro' ),
                    __( 'Unlimited export profiles', 'red-headed-pro' ),
                    __( 'Multi-destinations per profile (simultaneous fan-out)', 'red-headed-pro' ),
                    __( 'Cron schedules: hourly · daily · weekly · custom interval', 'red-headed-pro' ),
                    __( 'Auto-trigger on WC order status change (dedupe + min-total threshold)', 'red-headed-pro' ),
                    __( 'Advanced filters: date range · payment · category · SKU · min/max amount', 'red-headed-pro' ),
                    __( 'Visual field mapper (drag-and-drop column picker)', 'red-headed-pro' ),
                    __( 'Computed columns (formulas across order fields)', 'red-headed-pro' ),
                    __( 'Line-item export mode (one row per product)', 'red-headed-pro' ),
                    __( 'Post-export status change (auto-set order status after export)', 'red-headed-pro' ),
                    __( 'Custom WC statuses support (non-native order statuses)', 'red-headed-pro' ),
                    __( 'REST API endpoints (/red-headed-pro/v1/profiles, /jobs)', 'red-headed-pro' ),
                    __( 'HMAC SHA-256 signed webhooks with retry x3 exponential', 'red-headed-pro' ),
                    __( 'PolyLang & WPML compatibility', 'red-headed-pro' ),
                ),
                'parent_slug' => 'froggy-hub',
                'shop_url'    => 'https://thelionfrog.com/products/plugins/woo-order-pro',
            ) );
        }
    } else {
        Red_Headed_Engine::boot_admin();
    }
}, 20 );
