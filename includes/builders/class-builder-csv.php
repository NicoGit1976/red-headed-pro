<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * CSV / TSV builder.
 *
 * @package Pelican
 */
class Pelican_Builder_CSV {
    public static function build( $columns, $rows, $path, $delim = ',' ) {
        $fp = fopen( $path, 'w' );
        if ( ! $fp ) throw new \RuntimeException( 'Cannot open ' . $path );
        $headers = array_map( function ( $c ) {
            return is_array( $c ) ? ( $c['label'] ?? $c['key'] ?? '' ) : (string) $c;
        }, $columns );
        fputcsv( $fp, $headers, $delim );
        foreach ( $rows as $row ) {
            fputcsv( $fp, array_values( $row ), $delim );
        }
        fclose( $fp );
        return $path;
    }
}
