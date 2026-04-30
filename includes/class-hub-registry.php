<?php
/**
 * Hub Registry — Harlequin.
 *
 * Filter: 'the_froggy_hub_ecosystem' (Hub v1.4.0+ schema).
 * Plus stats hook for the global Hub dashboard.
 *
 * @package Pelican
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Pelican_Hub_Registry {
    public static function init() {
        add_filter( 'the_froggy_hub_ecosystem', array( __CLASS__, 'register' ), 10, 1 );
        add_filter( 'froggy_hub_plugin_stats', array( __CLASS__, 'stats' ), 10, 2 );
    }

    public static function register( $ecosystem ) {
        if ( ! is_array( $ecosystem ) ) $ecosystem = array();
        $ecosystem['pelican'] = array(
            'title' => 'Harlequin',
            'desc'  => __( 'Order Export — bulk + auto-export WooCommerce orders to CSV / XLSX / JSON / XML, deliver via Email / SFTP / Google Drive / Download.', 'pelican' ),
            'lite'  => array(
                'name' => 'The Lion Frog | Harlequin Lite',
                'slug' => 'woo-order-lite',
                'img'  => 'woo-order-lite.webp',
                'url'  => 'admin.php?page=pelican',
            ),
            'pro'   => array(
                'name' => 'The Lion Frog | Harlequin Pro',
                'slug' => 'woo-order-pro',
                'img'  => 'woo-order-pro.webp',
                'url'  => 'admin.php?page=pelican',
                'shop' => 'https://thelionfrog.com/products/plugins/woo-order-pro',
            ),
        );
        return $ecosystem;
    }

    public static function stats( $stats, $slug ) {
        if ( ! in_array( $slug, array( 'woo-order-lite', 'woo-order-pro' ), true ) ) return $stats;
        global $wpdb;
        $jobs = $wpdb->prefix . 'pl_jobs';
        if ( ! is_array( $stats ) ) $stats = array();
        $stats['exports_total']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs}" );
        $stats['exports_this_month'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs} WHERE started_at >= DATE_FORMAT(NOW(), '%Y-%m-01')" );
        $stats['exports_failed']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs} WHERE status = 'failed'" );
        return $stats;
    }
}
