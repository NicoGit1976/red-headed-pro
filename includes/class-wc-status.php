<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Pelican_WC_Status — registers an opt-in custom WooCommerce order status `wc-rh-exported`.
 *
 * Toggle: option `pelican_register_wc_status_exported` (set in General settings).
 *
 * Once enabled the status shows up in:
 *   - the WC orders list status filter
 *   - the profile editor "post-export status" dropdown
 *   - any code that calls wc_get_order_statuses()
 *
 * @package Pelican
 */
class Pelican_WC_Status {

    const SLUG = 'wc-rh-exported';

    public static function init() {
        if ( ! get_option( 'pelican_register_wc_status_exported', 0 ) ) return;
        if ( ! Pelican_Soft_Lock::is_available( 'wc_status_exported' ) ) return;
        add_action( 'init', array( __CLASS__, 'register_post_status' ) );
        add_filter( 'wc_order_statuses', array( __CLASS__, 'add_to_wc_statuses' ) );
        add_filter( 'woocommerce_register_shop_order_post_statuses', array( __CLASS__, 'add_to_post_statuses' ) );
    }

    public static function register_post_status() {
        register_post_status( self::SLUG, array(
            'label'                     => _x( 'Exported', 'Order status', 'pelican' ),
            'public'                    => false,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of orders. */
            'label_count'               => _n_noop( 'Exported <span class="count">(%s)</span>', 'Exported <span class="count">(%s)</span>', 'pelican' ),
        ) );
    }

    public static function add_to_wc_statuses( $statuses ) {
        $statuses[ self::SLUG ] = _x( '📦 Exported', 'Order status', 'pelican' );
        return $statuses;
    }

    public static function add_to_post_statuses( $statuses ) {
        $statuses[ self::SLUG ] = array(
            'label'                     => _x( 'Exported', 'Order status', 'pelican' ),
            'public'                    => false,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of orders. */
            'label_count'               => _n_noop( 'Exported <span class="count">(%s)</span>', 'Exported <span class="count">(%s)</span>', 'pelican' ),
        );
        return $statuses;
    }
}
