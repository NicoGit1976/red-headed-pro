<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * XML builder — flat <orders><order><field>…</field></order></orders>.
 *
 * @package Pelican
 */
class Pelican_Builder_XML {
    public static function build( $columns, $rows, $path ) {
        $dom = new \DOMDocument( '1.0', 'UTF-8' );
        $dom->formatOutput = true;
        $root = $dom->createElement( 'orders' );
        $root->setAttribute( 'generated_at', gmdate( 'c' ) );
        $root->setAttribute( 'count', count( $rows ) );
        $dom->appendChild( $root );
        foreach ( $rows as $row ) {
            $entry = $dom->createElement( 'order' );
            foreach ( $row as $key => $val ) {
                $tag = preg_replace( '/[^a-z0-9_:-]/i', '_', (string) $key );
                $el  = $dom->createElement( $tag );
                $el->appendChild( $dom->createTextNode( (string) $val ) );
                $entry->appendChild( $el );
            }
            $root->appendChild( $entry );
        }
        $ok = $dom->save( $path );
        if ( false === $ok ) throw new \RuntimeException( 'Cannot write ' . $path );
        return $path;
    }
}
