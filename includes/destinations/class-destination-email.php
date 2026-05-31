<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Email destination — wp_mail with configurable subject, sender, CC/BCC and
 * optional file attachment.
 *
 * Lite: 30 emails / 24h sliding window. Pro: unlimited.
 *
 * v1.5.1: subject / from / CC / BCC configurable per destination; attachment
 *         toggle; subject supports {placeholders} resolved from context.
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
                return new \WP_Error( 'rate_limited', __( 'Email quota reached (30/24h Lite limit). Upgrade to Pro for unlimited emails.', 'red-headed-pro' ) );
            }
        }
        /* ── Recipient (To) ──────────────────────────────────── */
        $to_raw = '';
        if ( ! empty( $config['to'] ) )        $to_raw = (string) $config['to'];
        elseif ( ! empty( $config['email'] ) ) $to_raw = (string) $config['email'];
        else                                   $to_raw = (string) get_option( 'pelican_default_email_to', '' );
        $to = self::sanitize_multi_email( $to_raw );
        if ( ! $to ) return new \WP_Error( 'no_recipient', __( 'No recipient email configured.', 'red-headed-pro' ) );

        /* ── Subject ─────────────────────────────────────────── */
        $subject_raw = '';
        if ( ! empty( $config['subject'] ) )     $subject_raw = (string) $config['subject'];
        else                                     $subject_raw = (string) get_option( 'pelican_default_email_subject', '' );
        if ( $subject_raw === '' ) {
            $subject_raw = __( 'New order received', 'red-headed-pro' );
        }
        $subject = self::resolve_subject( $subject_raw, $file, $config );

        /* ── From ────────────────────────────────────────────── */
        $from_email = ! empty( $config['from_email'] )
            ? sanitize_email( $config['from_email'] )
            : sanitize_email( get_option( 'pelican_default_email_from', '' ) );
        $from_name  = ! empty( $config['from_name'] )
            ? sanitize_text_field( $config['from_name'] )
            : sanitize_text_field( get_option( 'pelican_default_email_from_name', '' ) );

        /* ── Body ────────────────────────────────────────────── */
        $body_raw = isset( $config['email_body'] ) ? (string) $config['email_body'] : '';
        if ( $body_raw === '' ) $body_raw = (string) get_option( 'pelican_default_email_body', '' );
        if ( $body_raw === '' ) $body_raw = __( 'Your Red-Headed order export is attached.', 'red-headed-pro' );
        $body = wp_kses_post( self::resolve_subject( $body_raw, $file, $config ) );

        /* ── Headers ─────────────────────────────────────────── */
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $from_email ) {
            $name = $from_name ?: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
            $headers[] = 'From: ' . $name . ' <' . $from_email . '>';
        }
        $cc  = ! empty( $config['cc'] )  ? self::sanitize_multi_email( $config['cc'] )  : self::sanitize_multi_email( get_option( 'pelican_default_email_cc', '' ) );
        $bcc = ! empty( $config['bcc'] ) ? self::sanitize_multi_email( $config['bcc'] ) : self::sanitize_multi_email( get_option( 'pelican_default_email_bcc', '' ) );
        if ( $cc )  $headers[] = 'Cc: ' . $cc;
        if ( $bcc ) $headers[] = 'Bcc: ' . $bcc;

        /* ── Attachment toggle (default = ON for backward compat) ──── */
        $attach = ! isset( $config['attach_file'] ) || ! empty( $config['attach_file'] );
        $attachments = $attach ? array( $file ) : array();

        $sent = wp_mail( $to, $subject, $body, $headers, $attachments );
        if ( ! $sent ) return new \WP_Error( 'mail_failed', __( 'wp_mail returned false.', 'red-headed-pro' ) );
        if ( ! Pelican_Soft_Lock::is_pro() ) self::increment_rate();
        return true;
    }

    /**
     * Resolve {placeholders} in a subject / body string.
     *
     * Supported: {filename} {records} {order_number} {order_id} {site_name}
     *            {customer_email} {customer_name} {date} {time}
     */
    public static function resolve_subject( $tpl, $file, $config ) {
        $first = isset( $config['_first_order'] ) ? $config['_first_order'] : null;
        $repl = array(
            '{filename}'       => basename( $file ),
            '{records}'        => isset( $config['_records'] ) ? (string) (int) $config['_records'] : '?',
            '{site_name}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
            '{date}'           => current_time( 'Y-m-d' ),
            '{time}'           => current_time( 'H:i' ),
            '{order_number}'   => '',
            '{order_id}'       => '',
            '{customer_email}' => '',
            '{customer_name}'  => '',
        );
        if ( is_a( $first, 'WC_Order' ) ) {
            $repl['{order_number}']   = (string) $first->get_order_number();
            $repl['{order_id}']       = (string) $first->get_id();
            $repl['{customer_email}'] = (string) $first->get_billing_email();
            $repl['{customer_name}']  = trim( $first->get_billing_first_name() . ' ' . $first->get_billing_last_name() );
        }
        /* Legacy {{placeholder}} syntax (used in global defaults textarea). */
        $legacy = array();
        foreach ( $repl as $k => $v ) {
            $legacy[ '{' . trim( $k, '{}' ) . '}' ] = $v;   /* single-brace (canonical) */
            $legacy[ '{{' . trim( $k, '{}' ) . '}}' ] = $v; /* double-brace (legacy) */
        }
        return strtr( $tpl, $legacy );
    }

    /**
     * Accept comma or semicolon-separated emails, return a clean comma-separated
     * string or '' if nothing valid.
     */
    public static function sanitize_multi_email( $raw ) {
        $raw = (string) $raw;
        if ( $raw === '' ) return '';
        $parts = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
        $clean = array();
        foreach ( $parts as $p ) {
            $e = sanitize_email( trim( $p ) );
            if ( $e ) $clean[] = $e;
        }
        return implode( ', ', $clean );
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
