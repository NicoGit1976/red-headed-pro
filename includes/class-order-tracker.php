<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Pelican_Order_Tracker — adds an "Exported" column on the WC orders list
 * (both HPOS and legacy posts table). Source of truth = post/order meta:
 *   _rh_export_count       — how many times the order has been exported
 *   _rh_last_export_at     — datetime of the last export (mysql format)
 *   _rh_last_export_job_id — pl_jobs.id of the last export
 *
 * v1.4.22 (Pelican Pro+Lite).
 *
 * @package Pelican
 */
class Pelican_Order_Tracker {

    public static function init() {
        /* Legacy WC orders list (post type shop_order) */
        add_filter( 'manage_edit-shop_order_columns',                   array( __CLASS__, 'add_column' ) );
        add_action( 'manage_shop_order_posts_custom_column',            array( __CLASS__, 'render_column_legacy' ), 10, 2 );
        /* HPOS orders list (custom_order_tables) */
        add_filter( 'woocommerce_shop_order_list_table_columns',        array( __CLASS__, 'add_column' ) );
        add_action( 'woocommerce_shop_order_list_table_custom_column',  array( __CLASS__, 'render_column_hpos' ),   10, 2 );
    }

    public static function add_column( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'order_status' ) {
                $new['rh_exported'] = '📦 ' . __( 'Exported', 'pelican' );
            }
        }
        if ( ! isset( $new['rh_exported'] ) ) {
            $new['rh_exported'] = '📦 ' . __( 'Exported', 'pelican' );
        }
        return $new;
    }

    public static function render_column_legacy( $column, $post_id ) {
        if ( $column !== 'rh_exported' ) return;
        $order = wc_get_order( $post_id );
        if ( ! $order ) return;
        echo self::render_cell( $order );
    }

    public static function render_column_hpos( $column, $order ) {
        if ( $column !== 'rh_exported' ) return;
        if ( ! is_a( $order, 'WC_Order' ) ) return;
        echo self::render_cell( $order );
    }

    private static function render_cell( $order ) {
        $count = (int) $order->get_meta( '_rh_export_count' );
        if ( $count === 0 ) {
            return '<span style="color:#94a3b8;">—</span>';
        }
        $last = $order->get_meta( '_rh_last_export_at' );
        $tooltip = sprintf(
            /* translators: 1: count, 2: datetime */
            esc_attr__( 'Exported %1$d time(s), last on %2$s', 'pelican' ),
            $count,
            $last ?: '—'
        );
        return '<span title="' . esc_attr( $tooltip ) . '" style="display:inline-flex;align-items:center;gap:4px;color:#047857;font-weight:600;">'
            . '✓ <span style="font-size:11px;color:#94a3b8;">' . $count . '×</span>'
            . '</span>';
    }
}
