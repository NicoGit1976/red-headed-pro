<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Destination dispatcher — routes a destination config to the right channel
 * class. Returns true|WP_Error.
 *
 * @package Red_Headed_Pro
 */
class Red_Headed_Destination_Dispatcher {
    public static function ship( $destination, $file, $profile, $format ) {
        $type = isset( $destination['type'] ) ? sanitize_key( $destination['type'] ) : 'email';
        switch ( $type ) {
            case 'email':
                self::inject_context( $destination, $profile, $format );
                return Red_Headed_Destination_Email::ship( $file, $destination );
            case 'sftp':
                self::inject_context( $destination, $profile, $format );
                return Red_Headed_Destination_SFTP::ship( $file, $destination );
            case 'local_zip':
                if ( Red_Headed_Soft_Lock::is_locked( 'dest_local_zip' ) ) return new \WP_Error( 'locked', __( 'Local ZIP requires Pro.', 'red-headed-pro' ) );
                return Red_Headed_Destination_Local_Zip::ship( $file, $destination );
            case 'local_folder':
                if ( Red_Headed_Soft_Lock::is_locked( 'dest_local_folder' ) ) return new \WP_Error( 'locked', __( 'Local folder destination requires Pro.', 'red-headed-pro' ) );
                return Red_Headed_Destination_Local_Folder::ship( $file, $destination );
            case 'rest':
                if ( Red_Headed_Soft_Lock::is_locked( 'dest_rest' ) ) return new \WP_Error( 'locked', __( 'REST destination requires Pro.', 'red-headed-pro' ) );
                return Red_Headed_Destination_REST::ship( $file, $destination );
            case 'gdrive':
                if ( Red_Headed_Soft_Lock::is_locked( 'dest_gdrive' ) ) return new \WP_Error( 'locked', __( 'Google Drive destination requires Pro.', 'red-headed-pro' ) );
                /* v1.4.26 — context for filename pattern resolution. */
                self::inject_context( $destination, $profile, $format );
                return Red_Headed_Destination_GDrive::ship( $file, $destination );
            case 'download':
                /* Download is not really a "ship" — the file already lives on disk and
                   the user grabs it via the Exports list. We just return true so the
                   job logs as success. */
                return true;
            default:
                return new \WP_Error( 'unknown_destination', __( 'Unknown destination type.', 'red-headed-pro' ) );
        }
    }

    /* v1.4.26 — Inject the resolution context into $destination so each ship()
       receives everything Red_Headed_Filename_Resolver needs. */
    private static function inject_context( &$destination, $profile, $format ) {
        $destination['_profile_name'] = isset( $profile['name'] ) ? (string) $profile['name'] : '';
        $destination['_format']       = (string) $format;
        $destination['_job_id']       = isset( $profile['_job_id'] ) ? (int) $profile['_job_id'] : 0;
        $destination['_records']      = isset( $profile['_records'] ) ? (int) $profile['_records'] : 0;
        $destination['_first_order']  = isset( $profile['_first_order'] ) ? $profile['_first_order'] : null;
    }
}
