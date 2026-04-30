<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Destination base — shared utilities for all delivery channels.
 *
 * Each destination class implements ::ship( $file_path, $config ) returning
 * true on success / WP_Error on failure.
 *
 * @package Pelican
 */
abstract class Pelican_Destination_Base {
    abstract public static function ship( $file, $config );

    protected static function decrypt( $val ) {
        if ( ! is_string( $val ) || $val === '' ) return '';
        if ( strpos( $val, 'pl1:' ) !== 0 ) return $val; /* not encrypted */
        $iv_b64 = substr( $val, 4, 24 );
        $cipher = substr( $val, 28 );
        $iv     = base64_decode( $iv_b64 );
        $key    = hash( 'sha256', wp_salt( 'auth' ) . 'pelican', true );
        $plain  = openssl_decrypt( $cipher, 'aes-256-cbc', $key, 0, $iv );
        return $plain === false ? '' : $plain;
    }
    public static function encrypt( $plain ) {
        if ( ! is_string( $plain ) || $plain === '' ) return '';
        $iv  = openssl_random_pseudo_bytes( 16 );
        $key = hash( 'sha256', wp_salt( 'auth' ) . 'pelican', true );
        $c   = openssl_encrypt( $plain, 'aes-256-cbc', $key, 0, $iv );
        return 'pl1:' . base64_encode( $iv ) . $c;
    }
}
