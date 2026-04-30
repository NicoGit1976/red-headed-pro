<?php
/**
 * Plugin Name:       Red-Headed Pro — WooCommerce Order Export
 * Plugin URI:        https://thelionfrog.com/products/plugins/woo-order-pro
 * Description:       Premium WooCommerce order export. Bulk + auto exports, multi-format (CSV / XLSX / JSON / XML / NDJSON / TSV), multi-destination (Email / SFTP / Google Drive / Download / REST / Local ZIP), cron + status-driven triggers. Mascot: Red-Headed Poison Frog. Pro edition. Part of Ultimate Woo Powertools.
 * Version:           1.4.0
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

define( 'PELICAN_VERSION',  '1.4.0' );
define( 'PELICAN_EDITION',  'pro' );
define( 'PELICAN_FILE',     __FILE__ );
define( 'PELICAN_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PELICAN_URL',      plugin_dir_url( __FILE__ ) );
define( 'PELICAN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PELICAN_SLUG',     'woo-order-pro' );

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
    echo '<p><strong>🃏 ' . esc_html__( 'Harlequin Pro activated.', 'pelican' ) . '</strong></p>';
    echo '<p>' . esc_html__( 'Harlequin Lite has been deactivated automatically. All your data and settings have been preserved.', 'pelican' ) . '</p>';
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
                'page_slug'      => 'pelican',
                'plugin_name'    => 'Harlequin Pro',
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
