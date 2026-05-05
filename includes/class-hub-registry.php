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
        /* v1.4.40 — product_key 'red-headed' aligned on the hardcoded
           Hub fallback so the auto-registered entry overrides instead of
           creating a duplicate sidebar card. Title + baseline follow the
           naming convention validated 2026-05-05. */
        $ecosystem['red-headed'] = array(
            'title'    => 'Red Headed',
            'baseline' => 'Orders Export Manager',
            'desc'  => __( 'Exports WooCommerce orders everywhere, anytime. Bulk + auto exports, multi-format, multi-destination, cron + status-driven triggers. Mascot: Red-Headed Poison Frog.', 'pelican' ),
            'lite'  => array(
                'name' => 'The Lion Frog | Red-Headed Lite',
                'slug' => 'woo-order-lite',
                'img'  => 'woo-order-lite.webp',
                'url'  => 'admin.php?page=red-headed-pro',
            ),
            'pro'   => array(
                'name' => 'The Lion Frog | Red-Headed Pro',
                'slug' => 'red-headed-pro',
                'img'  => 'woo-order-pro.webp',
                'url'  => 'admin.php?page=red-headed-pro',
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
