<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Local folder destination — copies the raw export file (no zip) into a
 * configurable folder so an external system (an ERP, a watched directory…)
 * can pick it up. The on-disk filename is whatever the engine produced, so it
 * honours the profile "Filename pattern" and stays identical to the emailed
 * copy — one filename, consistent everywhere.
 *
 * Config: [ 'type' => 'local_folder', 'path' => 'order-exports' ]
 *   - Empty path  → wp-content/order-exports
 *   - Relative    → resolved under wp-content/
 *   - Absolute    → allowed, but must stay inside the WordPress root or
 *                   wp-content (no directory traversal).
 *
 * Pro feature (gated by the dispatcher via 'dest_local_folder').
 *
 * @package Pelican
 */
class Pelican_Destination_Local_Folder extends Pelican_Destination_Base {

    public static function ship( $file, $config ) {
        if ( ! $file || ! file_exists( $file ) ) {
            return new \WP_Error( 'no_source', __( 'Export file missing on disk.', 'red-headed-pro' ) );
        }
        $dir = self::resolve_dir( isset( $config['path'] ) ? (string) $config['path'] : '' );
        if ( is_wp_error( $dir ) ) return $dir;

        if ( ! wp_mkdir_p( $dir ) ) {
            return new \WP_Error( 'mkdir_failed', __( 'Could not create the target folder.', 'red-headed-pro' ) );
        }
        if ( ! is_writable( $dir ) ) {
            return new \WP_Error( 'not_writable', __( 'Target folder is not writable.', 'red-headed-pro' ) );
        }
        $dest = trailingslashit( $dir ) . basename( $file );
        if ( ! @copy( $file, $dest ) ) {
            return new \WP_Error( 'copy_failed', __( 'Could not copy the file to the target folder.', 'red-headed-pro' ) );
        }
        return true;
    }

    /**
     * Resolve and harden the destination directory.
     *
     * @param string $path Raw, user-supplied path (relative or absolute).
     * @return string|WP_Error Absolute, traversal-free directory path.
     */
    public static function resolve_dir( $path ) {
        $path = trim( (string) $path );
        if ( $path === '' ) $path = 'order-exports';

        /* Normalise slashes and strip any "../" traversal segments. */
        $path = str_replace( '\\', '/', $path );
        $path = preg_replace( '#\.\.+/#', '', $path );

        $is_abs  = ( isset( $path[0] ) && $path[0] === '/' ) || preg_match( '#^[A-Za-z]:/#', $path );
        $content = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
        $dir     = $is_abs ? $path : trailingslashit( $content ) . ltrim( $path, '/' );
        $dir     = rtrim( $dir, '/' );

        /* Containment: the resolved path (or its nearest existing ancestor) must
           live inside wp-content or the WordPress root. */
        $allowed = array_filter( array(
            defined( 'WP_CONTENT_DIR' ) ? realpath( WP_CONTENT_DIR ) : '',
            realpath( ABSPATH ),
        ) );
        $probe = $dir;
        while ( $probe && ! file_exists( $probe ) ) {
            $parent = dirname( $probe );
            if ( $parent === $probe ) break;
            $probe = $parent;
        }
        $real = $probe ? realpath( $probe ) : false;
        if ( $real ) {
            $ok = false;
            foreach ( $allowed as $base ) {
                if ( $base && strpos( $real, $base ) === 0 ) { $ok = true; break; }
            }
            if ( ! $ok ) {
                return new \WP_Error( 'path_outside', __( 'The folder must be inside wp-content or the WordPress root.', 'red-headed-pro' ) );
            }
        }
        return $dir;
    }
}
