<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Google Drive destination — uploads the export to a Drive folder.
 *
 * Pro feature. Stub for v1.0.0 — full OAuth flow implemented in v1.1.0:
 * for now we surface an admin notice asking the user to install the
 * companion plugin "Lion Frog Drive Connect" or paste a service account
 * JSON. The actual upload is a curl POST to the Drive REST API
 * (multipart/related) once a valid access token is configured.
 *
 * @package Pelican
 */
class Pelican_Destination_GDrive extends Pelican_Destination_Base {
    public static function ship( $file, $config ) {
        /* v1.4.23 — accept either pre-encrypted token (access_token_enc) or plain
           token pasted by the user (access_token). Mirrors the SFTP pattern. */
        $token = isset( $config['access_token_enc'] ) ? self::decrypt( $config['access_token_enc'] )
               : ( isset( $config['access_token'] ) ? (string) $config['access_token'] : '' );
        if ( ! $token ) {
            return new \WP_Error( 'gdrive_no_token', __( 'Google Drive: no OAuth access token configured. Paste one in the destination config (helper text under the field shows where to get it).', 'pelican' ) );
        }
        $folder_id = isset( $config['folder_id'] ) ? sanitize_text_field( $config['folder_id'] ) : '';

        $metadata = array( 'name' => basename( $file ) );
        if ( $folder_id ) $metadata['parents'] = array( $folder_id );

        $boundary = wp_generate_password( 24, false );
        $body  = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= wp_json_encode( $metadata ) . "\r\n";
        $body .= "--{$boundary}\r\nContent-Type: " . self::mime( $file ) . "\r\n\r\n";
        $body .= file_get_contents( $file ) . "\r\n";
        $body .= "--{$boundary}--";

        $resp = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'multipart/related; boundary=' . $boundary,
            ),
            'body'    => $body,
            'timeout' => 60,
        ) );
        if ( is_wp_error( $resp ) ) return $resp;
        $code = (int) wp_remote_retrieve_response_code( $resp );
        if ( $code < 200 || $code >= 300 ) {
            return new \WP_Error( 'gdrive_http_' . $code, 'GDrive HTTP ' . $code . ': ' . substr( wp_remote_retrieve_body( $resp ), 0, 200 ) );
        }
        return true;
    }
    protected static function mime( $file ) {
        $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
        return array(
            'csv' => 'text/csv', 'tsv' => 'text/tab-separated-values',
            'json' => 'application/json', 'xml' => 'application/xml',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        )[ $ext ] ?? 'application/octet-stream';
    }
}
