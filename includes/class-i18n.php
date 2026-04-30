<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * i18n compatibility — PolyLang & WPML for Harlequin.
 *
 * @package Pelican
 */
class Pelican_I18n {
    const POLYLANG_GROUP = 'Pelican';

    public static function translatable_options() {
        return array(
            'pelican_email_subject' => array( 'label' => 'Default email subject',  'multiline' => false ),
            'pelican_email_body'    => array( 'label' => 'Default email body',     'multiline' => true  ),
        );
    }
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_polylang_strings' ), 20 );
        foreach ( array_keys( self::translatable_options() ) as $opt ) {
            add_filter( 'option_' . $opt, array( __CLASS__, 'translate_option_value' ), 99, 2 );
        }
    }
    public static function register_polylang_strings() {
        if ( ! function_exists( 'pll_register_string' ) ) return;
        foreach ( self::translatable_options() as $key => $meta ) {
            $value = (string) get_option( $key, '' );
            if ( $value === '' ) continue;
            pll_register_string( $key, $value, self::POLYLANG_GROUP, ! empty( $meta['multiline'] ) );
        }
    }
    public static function translate_option_value( $value, $option_name ) {
        if ( ! is_string( $value ) || $value === '' ) return $value;
        if ( function_exists( 'pll__' ) ) {
            $t = pll__( $value );
            if ( is_string( $t ) && $t !== '' ) return $t;
        }
        if ( function_exists( 'icl_t' ) ) {
            $t = icl_t( 'admin_texts_' . $option_name, $option_name, $value );
            if ( is_string( $t ) && $t !== '' ) return $t;
        }
        return $value;
    }
}
