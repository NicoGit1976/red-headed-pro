<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Destination dispatcher — routes a destination config to the right channel
 * class. Returns true|WP_Error.
 *
 * @package Pelican
 */
class Pelican_Destination_Dispatcher {
    public static function ship( $destination, $file, $profile, $format ) {
        $type = isset( $destination['type'] ) ? sanitize_key( $destination['type'] ) : 'email';
        switch ( $type ) {
            case 'email':
                return Pelican_Destination_Email::ship( $file, $destination );
            case 'sftp':
                return Pelican_Destination_SFTP::ship( $file, $destination );
            case 'local_zip':
                if ( Pelican_Soft_Lock::is_locked( 'dest_local_zip' ) ) return new \WP_Error( 'locked', __( 'Local ZIP requires Pro.', 'pelican' ) );
                return Pelican_Destination_Local_Zip::ship( $file, $destination );
            case 'rest':
                if ( Pelican_Soft_Lock::is_locked( 'dest_rest' ) ) return new \WP_Error( 'locked', __( 'REST destination requires Pro.', 'pelican' ) );
                return Pelican_Destination_REST::ship( $file, $destination );
            case 'gdrive':
                if ( Pelican_Soft_Lock::is_locked( 'dest_gdrive' ) ) return new \WP_Error( 'locked', __( 'Google Drive destination requires Pro.', 'pelican' ) );
                return Pelican_Destination_GDrive::ship( $file, $destination );
            case 'download':
                /* Download is not really a "ship" — the file already lives on disk and
                   the user grabs it via the Exports list. We just return true so the
                   job logs as success. */
                return true;
            default:
                return new \WP_Error( 'unknown_destination', __( 'Unknown destination type.', 'pelican' ) );
        }
    }
}
