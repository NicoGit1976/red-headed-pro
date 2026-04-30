<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Auto-trigger — fires an export profile when an order's status reaches a
 * configured value. Pro feature.
 *
 *   profile.auto_trigger = [
 *     'on_status'    => ['processing', 'completed'],
 *     'fire_once'    => true,         // dedupe per order
 *     'min_total'    => 0,
 *   ]
 *
 * @package Pelican
 */
class Pelican_Auto_Trigger {
    public static function init() {
        if ( Pelican_Soft_Lock::is_locked( 'auto_trigger' ) ) return;
        add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );
    }
    public static function on_status_changed( $order_id, $from, $to, $order ) {
        if ( ! $order_id ) return;
        foreach ( Pelican_Profile_Repo::all() as $profile ) {
            $rule = $profile['auto_trigger'] ?? array();
            if ( empty( $rule['on_status'] ) ) continue;
            $statuses = array_map( 'sanitize_key', (array) $rule['on_status'] );
            if ( ! in_array( $to, $statuses, true ) ) continue;
            if ( ! empty( $rule['min_total'] ) ) {
                $total = is_object( $order ) ? (float) $order->get_total() : 0;
                if ( $total < (float) $rule['min_total'] ) continue;
            }
            if ( ! empty( $rule['fire_once'] ) ) {
                $key = 'pelican_auto_fired_' . (int) $profile['id'] . '_' . (int) $order_id;
                if ( get_transient( $key ) ) continue;
                set_transient( $key, 1, 30 * DAY_IN_SECONDS );
            }
            /* Run profile with this single order injected as filter override */
            $injected = $profile;
            $injected['filters']['order_ids_override'] = array( (int) $order_id );
            Pelican_Export_Engine::run( $injected, 'auto:status_changed:' . $to );
        }
    }
}
