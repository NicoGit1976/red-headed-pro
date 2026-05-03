<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * SFTP destination — phpseclib3 if available, else native PHP SFTP via SSH2 ext.
 *
 * Both Lite and Pro support SFTP (Lite is capped to 1 destination per profile).
 *
 * @package Pelican
 */
class Pelican_Destination_SFTP extends Pelican_Destination_Base {
    public static function ship( $file, $config ) {
        $host = isset( $config['host'] ) ? sanitize_text_field( $config['host'] ) : '';
        $port = isset( $config['port'] ) ? (int) $config['port'] : 22;
        $user = isset( $config['user'] ) ? sanitize_text_field( $config['user'] ) : '';
        $pass = isset( $config['pass_enc'] ) ? self::decrypt( $config['pass_enc'] ) : ( isset( $config['pass'] ) ? (string) $config['pass'] : '' );
        $dir  = isset( $config['path'] ) ? rtrim( sanitize_text_field( $config['path'] ), '/' ) : '/';
        if ( ! $host || ! $user ) return new \WP_Error( 'sftp_missing', __( 'Missing SFTP host or user.', 'pelican' ) );

        /* v1.4.26 — Optional filename pattern. Falls back to basename($file). */
        $remote_name = Pelican_Filename_Resolver::resolve(
            isset( $config['filename_pattern'] ) ? $config['filename_pattern'] : '',
            array(
                'file'         => $file,
                'profile_name' => $config['_profile_name'] ?? '',
                'format'       => $config['_format']       ?? '',
                'records'      => $config['_records']      ?? 0,
                'job_id'       => $config['_job_id']       ?? 0,
                'first_order'  => $config['_first_order']  ?? null,
            )
        );
        if ( $remote_name === '' ) $remote_name = basename( $file );
        $remote_path = $dir . '/' . $remote_name;

        if ( class_exists( '\phpseclib3\Net\SFTP' ) ) {
            try {
                $sftp = new \phpseclib3\Net\SFTP( $host, $port );
                if ( ! $sftp->login( $user, $pass ) ) {
                    return new \WP_Error( 'sftp_auth', __( 'SFTP authentication failed.', 'pelican' ) );
                }
                if ( ! $sftp->put( $remote_path, $file, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE ) ) {
                    return new \WP_Error( 'sftp_put', __( 'SFTP upload failed.', 'pelican' ) );
                }
                return true;
            } catch ( \Throwable $e ) {
                return new \WP_Error( 'sftp_ex', $e->getMessage() );
            }
        }
        if ( function_exists( 'ssh2_connect' ) ) {
            $conn = @ssh2_connect( $host, $port );
            if ( ! $conn ) return new \WP_Error( 'sftp_connect', __( 'SSH2 connect failed.', 'pelican' ) );
            if ( ! @ssh2_auth_password( $conn, $user, $pass ) ) return new \WP_Error( 'sftp_auth', __( 'SSH2 auth failed.', 'pelican' ) );
            $sftp = @ssh2_sftp( $conn );
            if ( ! $sftp ) return new \WP_Error( 'sftp_subsystem', __( 'SFTP subsystem failed.', 'pelican' ) );
            $stream = @fopen( "ssh2.sftp://{$sftp}{$remote_path}", 'w' );
            if ( ! $stream ) return new \WP_Error( 'sftp_open_remote', __( 'Cannot open remote path.', 'pelican' ) );
            $local = fopen( $file, 'r' );
            stream_copy_to_stream( $local, $stream );
            fclose( $local ); fclose( $stream );
            return true;
        }
        return new \WP_Error( 'sftp_no_lib', __( 'SFTP library missing. Install phpseclib3 (composer install --no-dev) or enable PHP SSH2 extension.', 'pelican' ) );
    }
}
