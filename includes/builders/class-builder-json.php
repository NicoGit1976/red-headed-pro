<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * JSON / NDJSON builder.
 *
 * @package Pelican
 */
class Pelican_Builder_JSON {
    public static function build( $columns, $rows, $path, $ndjson = false ) {
        $fp = fopen( $path, 'w' );
        if ( ! $fp ) throw new \RuntimeException( 'Cannot open ' . $path );
        if ( $ndjson ) {
            foreach ( $rows as $row ) {
                fwrite( $fp, wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
            }
        } else {
            $payload = array(
                'meta' => array(
                    'generated_at' => gmdate( 'c' ),
                    'count'        => count( $rows ),
                    'site'         => home_url(),
                    'plugin'       => 'pelican',
                    'version'      => defined( 'PELICAN_VERSION' ) ? PELICAN_VERSION : '1.0.0',
                ),
                'orders' => array_values( $rows ),
            );
            fwrite( $fp, wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
        }
        fclose( $fp );
        return $path;
    }
}
