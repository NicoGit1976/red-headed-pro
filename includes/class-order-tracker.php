<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Red_Headed_Order_Tracker — adds an "Exported" column on the WC orders list
 * (both HPOS and legacy posts table). Source of truth = post/order meta:
 *   _rh_export_count       — how many times the order has been exported
 *   _rh_last_export_at     — datetime of the last export (mysql format)
 *   _rh_last_export_job_id — rh_jobs.id of the last export
 *
 * v1.4.22 (Red_Headed_Pro Pro+Lite).
 *
 * @package Red_Headed_Pro
 */
class Red_Headed_Order_Tracker {

    public static function init() {
        /* Legacy WC orders list (post type shop_order) */
        add_filter( 'manage_edit-shop_order_columns',                   array( __CLASS__, 'add_column' ) );
        add_action( 'manage_shop_order_posts_custom_column',            array( __CLASS__, 'render_column_legacy' ), 10, 2 );
        /* HPOS orders list (custom_order_tables) */
        add_filter( 'woocommerce_shop_order_list_table_columns',        array( __CLASS__, 'add_column' ) );
        add_action( 'woocommerce_shop_order_list_table_custom_column',  array( __CLASS__, 'render_column_hpos' ),   10, 2 );

        /* ── SKU / client-code freeze at order creation (v1.6.1) ────────────────
         * The catalog is only a PROJECTION of the source-of-truth (an external
         * product feed); products can be purged or re-imported under new IDs. So the
         * export must NEVER depend on the live catalog — it must read the eternal
         * keys (SKU = product, user_login = client) from the ORDER itself, which
         * persists in "My orders" untouched by catalog churn.
         * We therefore STAMP those keys onto the order at creation:
         *   _rh_sku            on each line  (was read by the engine but never written)
         *   _rh_customer_code  on the order  (B2B / ERP account code, survives user edits)
         * This makes `line_sku` step 1 actually fire and closes the empty-product-code
         * class of downstream ERP/EDI refusals for all FUTURE orders. */
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'freeze_line_sku' ),     10, 4 );
        add_action( 'woocommerce_checkout_create_order',           array( __CLASS__, 'freeze_customer_code' ), 20, 2 );
    }

    /**
     * Freeze the product SKU (the eternal key) onto the order line at creation,
     * so the export resolves product code from the ORDER — never the volatile
     * catalog. Variations stamp their own SKU (falls back to the parent's).
     *
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     * @param WC_Order              $order
     */
    public static function freeze_line_sku( $item, $cart_item_key, $values, $order ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) return;
        $product = $item->get_product();
        if ( ! $product ) return;
        $sku = (string) $product->get_sku();
        if ( $sku !== '' ) {
            $item->update_meta_data( '_rh_sku', $sku );
        }
    }

    /**
     * Freeze the customer account code (WP user_login = the B2B / ERP account code)
     * onto the order at creation, so ID Client survives a later user rename or
     * deletion — same eternal-key principle as the SKU.
     *
     * @param WC_Order $order
     * @param array    $data
     */
    public static function freeze_customer_code( $order, $data ) {
        $uid = (int) $order->get_customer_id();
        if ( ! $uid ) return;
        $user = get_userdata( $uid );
        if ( $user && (string) $user->user_login !== '' ) {
            $order->update_meta_data( '_rh_customer_code', $user->user_login );
        }
    }

    public static function add_column( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'order_status' ) {
                $new['rh_exported'] = '📦 ' . __( 'Exported', 'red-headed-pro' );
            }
        }
        if ( ! isset( $new['rh_exported'] ) ) {
            $new['rh_exported'] = '📦 ' . __( 'Exported', 'red-headed-pro' );
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
            esc_attr__( 'Exported %1$d time(s), last on %2$s', 'red-headed-pro' ),
            $count,
            $last ?: '—'
        );
        return '<span title="' . esc_attr( $tooltip ) . '" style="display:inline-flex;align-items:center;gap:4px;color:#047857;font-weight:600;">'
            . '✓ <span style="font-size:11px;color:#94a3b8;">' . $count . '×</span>'
            . '</span>';
    }
}
