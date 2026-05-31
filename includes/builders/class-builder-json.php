<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * JSON / NDJSON builder.
 *
 * @package Pelican
 */
class Pelican_Builder_JSON {
    /**
     * @param bool $ndjson Line-delimited JSON (one record per line, always envelope-free).
     * @param bool $bare   When true (and not NDJSON), emit a plain JSON array of records —
     *                     no { meta, orders } wrapper. Useful for ERP / partner feeds
     *                     that expect a top-level array (or object — see below).
     */
    public static function build( $columns, $rows, $path, $ndjson = false, $bare = false ) {
        $fp = fopen( $path, 'w' );
        if ( ! $fp ) throw new \RuntimeException( 'Cannot open ' . $path );
        if ( $ndjson ) {
            foreach ( $rows as $row ) {
                fwrite( $fp, wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
            }
        } elseif ( $bare ) {
            /* Bare mode: a single record is emitted as a top-level object (matches
               one-file-per-order ERP/partner feeds, e.g. {"order_id":…}); 2+ records
               become a top-level array. Compact output (no pretty-print, unescaped
               unicode + slashes) to match fixed downstream parsers byte-for-byte. */
            $vals = array_values( $rows );
            $out  = ( count( $vals ) === 1 ) ? $vals[0] : $vals;
            fwrite( $fp, wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
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
