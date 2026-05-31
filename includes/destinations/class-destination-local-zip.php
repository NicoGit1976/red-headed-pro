<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Local ZIP destination — wraps the export file in a ZIP archive
 * stored in uploads/red-headed-pro/exports/. Pro feature.
 *
 * @package Red_Headed_Pro
 */
class Red_Headed_Destination_Local_Zip extends Red_Headed_Destination_Base {
    public static function ship( $file, $config ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new \WP_Error( 'no_zip', __( 'PHP ZipArchive extension missing.', 'red-headed-pro' ) );
        }
        $zip_path = preg_replace( '/\.[a-z0-9]+$/i', '.zip', $file );
        $zip = new \ZipArchive();
        if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            return new \WP_Error( 'zip_open', __( 'Cannot open zip for writing.', 'red-headed-pro' ) );
        }
        $zip->addFile( $file, basename( $file ) );
        $zip->close();
        return true;
    }
}
