<?php
/**
 * Plugin Name:       Red-Headed Pro — Exports Orders Everywhere, Anytime
 * Plugin URI:        https://thelionfrog.com
 * Description:       Exports WooCommerce orders everywhere, anytime. Bulk + auto exports, multi-format (CSV / XLSX / JSON / XML / NDJSON / TSV), multi-destination (Email / SFTP / Google Drive / Download / REST / Local ZIP), cron + status-driven triggers. Mascot: Red-Headed Poison Frog. Pro edition. Part of Ultimate Woo Powertools (by The Lion Frog).
 * Version:           1.4.16
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            The Lion Frog Team
 * Author URI:        https://thelionfrog.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pelican
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.5
 *
 * @package Pelican
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PELICAN_VERSION', '1.4.16' );
define( 'PELICAN_EDITION',  'pro' );
define( 'PELICAN_FILE',     __FILE__ );
define( 'PELICAN_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PELICAN_URL',      plugin_dir_url( __FILE__ ) );
define( 'PELICAN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PELICAN_SLUG',     'red-headed-pro' );
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'FH_Plugin_Updater' ) ) {
        FH_Plugin_Updater::register( [
            'slug'        => 'red-headed-pro',
            'plugin_file' => __FILE__,
            'name'        => 'Red-Headed Pro',
            'icon_url'    => PELICAN_URL . 'assets/img/mascot-redheaded-v1.svg',
        ] );
    }
    add_filter( 'the_froggy_hub_quick_actions', function ( $actions ) {
        $actions[] = [
            'label'       => __( 'Export orders', 'pelican' ),
            'icon'        => '📦',
            'url'         => admin_url( 'admin.php?page=red-headed-pro-exports&action=new' ),
            'tooltip'     => __( 'Run a manual order export', 'pelican' ),
            'plugin_slug' => 'red-headed-pro',
            'is_primary'  => true,
        ];
        $actions[] = [
            'label'       => __( 'Export history', 'pelican' ),
            'icon'        => '📋',
            'url'         => admin_url( 'admin.php?page=red-headed-pro-exports&tab=history' ),
            'tooltip'     => __( 'View past export jobs and downloads', 'pelican' ),
            'plugin_slug' => 'red-headed-pro',
        ];
        $actions[] = [
            'label'       => __( 'Schedules', 'pelican' ),
            'icon'        => '⏰',
            'url'         => admin_url( 'admin.php?page=red-headed-pro-settings-cron' ),
            'tooltip'     => __( 'Configure automatic export schedules', 'pelican' ),
            'plugin_slug' => 'red-headed-pro',
        ];
        $actions[] = [
            'label'       => __( 'Settings', 'pelican' ),
            'icon'        => '⚙️',
            'url'         => admin_url( 'admin.php?page=red-headed-pro-settings' ),
            'tooltip'     => __( 'Open Red-Headed settings', 'pelican' ),
            'plugin_slug' => 'red-headed-pro',
        ];
        return $actions;
    } );
    /* Hub Quick Actions — KPI strip (cached 5 min). */
    add_filter( 'the_froggy_hub_quick_actions_stats', function ( $stats ) {
        $cached = get_transient( 'fh_qa_stats_red_headed_pro' );
        if ( false !== $cached ) { $stats['red-headed-pro'] = $cached; return $stats; }
        global $wpdb;
        $jobs_t     = $wpdb->prefix . 'pl_jobs';
        $profiles_t = $wpdb->prefix . 'pl_profiles';
        $week_ago   = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        $week       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $jobs_t WHERE created_at >= %s", $week_ago ) );
        $profiles   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $profiles_t" );
        $kpis = [
            [ 'icon' => '📦', 'value' => (string) $week,     'label' => __( 'last 7 days', 'pelican' ), 'tone' => 'success' ],
            [ 'icon' => '⚙️', 'value' => (string) $profiles, 'label' => __( 'profiles', 'pelican' ),    'tone' => 'info' ],
        ];
        set_transient( 'fh_qa_stats_red_headed_pro', $kpis, 5 * MINUTE_IN_SECONDS );
        $stats['red-headed-pro'] = $kpis;
        return $stats;
    } );
}, 5 );
/* Brand-rename — 301 from legacy 'pelican*' admin URLs to new 'red-headed-pro*'
   (preserves bookmarks). Covers dashboard + exports + settings + settings tabs. */
add_action( 'admin_init', function () {
    if ( ! isset( $_GET['page'] ) ) return;
    $p = (string) $_GET['page'];
    $map = [
        'pelican'                       => 'red-headed-pro',
        'pelican-exports'               => 'red-headed-pro-exports',
        'pelican-settings'              => 'red-headed-pro-settings',
        'pelican-settings-profiles'     => 'red-headed-pro-settings-profiles',
        'pelican-settings-destinations' => 'red-headed-pro-settings-destinations',
        'pelican-settings-cron'         => 'red-headed-pro-settings-cron',
        'pelican-settings-webhooks'     => 'red-headed-pro-settings-webhooks',
        'pelican-settings-general'      => 'red-headed-pro-settings-general',
        'pelican-settings-schedules'    => 'red-headed-pro-settings-cron',
    ];
    if ( isset( $map[ $p ] ) ) {
        $url = add_query_arg( 'page', $map[ $p ], admin_url( 'admin.php' ) );
        $extras = $_GET; unset( $extras['page'] );
        if ( ! empty( $extras ) ) $url = add_query_arg( $extras, $url );
        wp_safe_redirect( $url, 301 );
        exit;
    }
}, 1 );


/* Composer autoloader (mPDF/PhpSpreadsheet/phpseclib live in vendor when installed). */
if ( file_exists( PELICAN_PATH . 'vendor/autoload.php' ) ) {
    require_once PELICAN_PATH . 'vendor/autoload.php';
}

require_once PELICAN_PATH . 'includes/class-engine.php';

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
    if ( is_plugin_active( 'woo-order-lite/woo-order-lite.php' ) ) {
        deactivate_plugins( 'woo-order-lite/woo-order-lite.php' );
        set_transient( 'pelican_lite_was_deactivated', 1, 30 );
    }
    require_once PELICAN_PATH . 'includes/class-installer.php';
    Pelican_Installer::activate();
} );
register_deactivation_hook( __FILE__, function () {
    require_once PELICAN_PATH . 'includes/class-installer.php';
    Pelican_Installer::deactivate();
} );

add_action( 'admin_notices', function () {
    if ( ! get_transient( 'pelican_lite_was_deactivated' ) ) return;
    delete_transient( 'pelican_lite_was_deactivated' );
    echo '<div class="notice notice-success is-dismissible" style="border-left-color:#10B981;">';
    echo '<p><strong>🃏 ' . esc_html__( 'Red-Headed Pro activated.', 'pelican' ) . '</strong></p>';
    echo '<p>' . esc_html__( 'Red-Headed Lite has been deactivated automatically. All your data and settings have been preserved.', 'pelican' ) . '</p>';
    echo '</div>';
} );

/* Boot — load classes, register filters/hooks (no admin yet). */
Pelican_Engine::instance();

/* Hub Soft Lock at boot.
 *   - Hub active + license valid     → Pelican_Engine::boot_admin()
 *   - Hub active + license invalid   → FH_Soft_Lock::register() (branded landing)
 *   - Hub absent (standalone / dev)  → boot_admin()
 */
add_action( 'plugins_loaded', function () {
    if ( defined( 'FH_PATH' ) && class_exists( 'FH_License_Manager' ) && class_exists( 'FH_Soft_Lock' ) ) {
        $license_manager = new FH_License_Manager();
        $status          = $license_manager->check_license( PELICAN_SLUG );
        if ( FH_Soft_Lock::is_valid( $status ) ) {
            Pelican_Engine::boot_admin();
        } else {
            FH_Soft_Lock::register( PELICAN_SLUG, array(
                'page_slug'      => PELICAN_SLUG, // v1.4.11 — match Hub placeholder slug to dedupe sidebar entry
                'plugin_name'    => 'Red-Headed Pro',
                'plugin_icon'    => PELICAN_URL . 'assets/img/icon.png',
                'license_status' => $status,
                'features'       => array(
                    __( 'Bulk + manual exports of WooCommerce orders', 'pelican' ),
                    __( '6 formats: CSV · XLSX · JSON · NDJSON · XML · TSV', 'pelican' ),
                    __( 'Multi-destinations: Email · SFTP · Google Drive · Download · Local ZIP · REST', 'pelican' ),
                    __( 'Cron triggers: hourly · twice-daily · daily · weekly · custom', 'pelican' ),
                    __( 'Auto-trigger on order status change (rules engine)', 'pelican' ),
                    __( 'Advanced filters: dates · status · payment · shipping · category · SKU · customer role', 'pelican' ),
                    __( 'Field mapper: pick columns, rename headers, transform values', 'pelican' ),
                    __( 'PolyLang & WPML compatibility', 'pelican' ),
                    __( 'REST API + Webhooks (Zapier / n8n / your CRM)', 'pelican' ),
                    __( 'HPOS compatible · Hub auto-register · Lion Frog DNA UI', 'pelican' ),
                ),
                'parent_slug' => 'froggy-hub',
                'shop_url'    => 'https://thelionfrog.com/products/plugins/woo-order-pro',
            ) );
        }
    } else {
        Pelican_Engine::boot_admin();
    }
}, 20 );
