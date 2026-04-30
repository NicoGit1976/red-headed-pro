<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Email destination — wp_mail with file attached.
 *
 * Lite: 30 emails / 24h sliding window. Pro: unlimited.
 *
 * @package Pelican
 */
class Pelican_Destination_Email extends Pelican_Destination_Base {
    const RATE_OPTION = 'pelican_email_rate';
    const RATE_LIMIT_LITE = 30;
    const RATE_WINDOW = DAY_IN_SECONDS;

    public static function ship( $file, $config ) {
        if ( ! Pelican_Soft_Lock::is_pro() ) {
            $rate = self::current_rate();
            if ( $rate >= self::RATE_LIMIT_LITE ) {
                return new \WP_Error( 'rate_limited', __( 'Email quota reached (30/24h Lite limit). Upgrade to Pro for unlimited emails.', 'pelican' ) );
            }
        }
        $to = isset( $config['email'] ) ? sanitize_email( $config['email'] ) : '';
        if ( ! $to ) return new \WP_Error( 'no_recipient', __( 'No recipient email configured.', 'pelican' ) );

        $subject = sprintf(
            /* translators: 1: site name */
            __( '[%1$s] Harlequin export — %2$s', 'pelican' ),
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
            basename( $file )
        );
        $body = isset( $config['email_body'] ) ? wp_kses_post( $config['email_body'] ) : __( 'Your Harlequin order export is attached.', 'pelican' );
        $sent = wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ), array( $file ) );
        if ( ! $sent ) return new \WP_Error( 'mail_failed', __( 'wp_mail returned false.', 'pelican' ) );
        if ( ! Pelican_Soft_Lock::is_pro() ) self::increment_rate();
        return true;
    }

    public static function current_rate() {
        $log = get_option( self::RATE_OPTION, array() );
        if ( ! is_array( $log ) ) $log = array();
        $cutoff = time() - self::RATE_WINDOW;
        $log = array_filter( $log, function ( $ts ) use ( $cutoff ) { return $ts >= $cutoff; } );
        return count( $log );
    }
    public static function increment_rate() {
        $log = get_option( self::RATE_OPTION, array() );
        if ( ! is_array( $log ) ) $log = array();
        $log[] = time();
        update_option( self::RATE_OPTION, array_values( $log ), false );
    }
    public static function rate_status() {
        $sent = self::current_rate();
        return array(
            'sent_24h'  => $sent,
            'limit'     => Pelican_Soft_Lock::is_pro() ? PHP_INT_MAX : self::RATE_LIMIT_LITE,
            'remaining' => Pelican_Soft_Lock::is_pro() ? PHP_INT_MAX : max( 0, self::RATE_LIMIT_LITE - $sent ),
        );
    }
}
