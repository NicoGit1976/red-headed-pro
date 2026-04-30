<?php
/**
 * Soft Lock — Harlequin.
 * Pro features visible but locked when running Lite. Same pattern as the rest of the suite.
 *
 * @package Pelican
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Pelican_Soft_Lock {
    public static function edition() {
        return defined( 'PELICAN_EDITION' ) ? PELICAN_EDITION : 'lite';
    }
    public static function is_pro() {
        return self::edition() === 'pro';
    }
    public static function locked_features() {
        return array(
            /* Formats — Lite has CSV only */
            'format_xlsx'        => true,
            'format_json'        => true,
            'format_xml'         => true,
            'format_ndjson'      => true,
            'format_tsv'         => true,
            /* Destinations — Lite has Email + SFTP only */
            'dest_gdrive'        => true,
            'dest_download'      => true,  /* still available in Lite as button — see admin */
            'dest_rest'          => true,
            'dest_local_zip'     => true,
            /* Triggers — Lite is manual only */
            'cron'               => true,
            'auto_trigger'       => true,  /* status-driven automation */
            /* Advanced */
            'multi_destinations' => true,  /* Lite: 1 destination per profile */
            'filters_advanced'   => true,
            'field_mapper'       => true,
            'rest_api'           => true,
            'webhooks'           => true,
            'multilingual'       => true,
            'profile_unlimited'  => true,  /* Lite: 1 profile only */
        );
    }
    public static function is_locked( $feature ) {
        if ( self::is_pro() ) return false;
        $l = self::locked_features();
        return isset( $l[ $feature ] ) && $l[ $feature ];
    }
    public static function is_available( $feature ) { return ! self::is_locked( $feature ); }
    public static function badge() {
        if ( self::is_pro() ) return '';
        return '<span class="pl-badge-pro" aria-label="Pro feature">PRO</span>';
    }
    public static function wrap( $feature, $callback ) {
        if ( self::is_available( $feature ) ) { call_user_func( $callback ); return; }
        echo '<div class="pl-locked" data-feature="' . esc_attr( $feature ) . '" tabindex="0" role="button" aria-label="' . esc_attr__( 'Pro feature locked', 'pelican' ) . '">';
        echo '<div class="pl-locked-overlay">' . self::badge() . '</div>';
        echo '<div class="pl-locked-content" aria-hidden="true">';
        call_user_func( $callback );
        echo '</div></div>';
    }
    public static function guard( $feature ) {
        if ( self::is_locked( $feature ) ) {
            wp_send_json_error( array(
                'code'    => 'feature_locked',
                'message' => sprintf(
                    /* translators: %s = feature slug */
                    __( 'This feature (%s) requires Harlequin Pro.', 'pelican' ),
                    $feature
                ),
            ), 403 );
            exit;
        }
    }
}
