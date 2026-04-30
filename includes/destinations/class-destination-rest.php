<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * REST destination — POST the file (multipart) or its content (raw) to a URL.
 *
 * Pro feature. Supports Bearer / Basic / custom-header auth.
 *
 * @package Pelican
 */
class Pelican_Destination_REST extends Pelican_Destination_Base {
    public static function ship( $file, $config ) {
        $url = isset( $config['endpoint'] ) ? esc_url_raw( $config['endpoint'] ) : '';
        if ( ! $url ) return new \WP_Error( 'rest_missing', __( 'Missing endpoint URL.', 'pelican' ) );

        $method = strtoupper( $config['method'] ?? 'POST' );
        if ( ! in_array( $method, array( 'POST', 'PUT' ), true ) ) $method = 'POST';

        $headers = array(
            'User-Agent' => 'Pelican/' . PELICAN_VERSION . ' (+' . home_url( '/' ) . ')',
        );
        $auth_type = strtolower( $config['auth_type'] ?? '' );
        $auth_val  = (string) ( isset( $config['auth_value_enc'] ) ? self::decrypt( $config['auth_value_enc'] ) : ( $config['auth_value'] ?? '' ) );
        if ( $auth_val !== '' ) {
            switch ( $auth_type ) {
                case 'bearer': $headers['Authorization'] = 'Bearer ' . $auth_val; break;
                case 'basic':  $headers['Authorization'] = 'Basic ' . base64_encode( $auth_val ); break;
                case 'header':
                    if ( false !== strpos( $auth_val, ':' ) ) {
                        [ $h, $v ] = array_map( 'trim', explode( ':', $auth_val, 2 ) );
                        if ( $h !== '' ) $headers[ $h ] = $v;
                    }
                    break;
            }
        }

        $send_mode = $config['send_mode'] ?? 'raw';
        if ( $send_mode === 'multipart' ) {
            /* Build multipart body manually (wp_remote does not support files
               natively the same way). */
            $boundary = wp_generate_password( 24, false );
            $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
            $body = '';
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file ) . '"' . "\r\n";
            $body .= 'Content-Type: application/octet-stream' . "\r\n\r\n";
            $body .= file_get_contents( $file ) . "\r\n";
            $body .= '--' . $boundary . '--' . "\r\n";
        } else {
            $headers['Content-Type'] = self::guess_mime( $file );
            $body = file_get_contents( $file );
        }

        $resp = wp_remote_request( $url, array(
            'method'  => $method,
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 30,
        ) );
        if ( is_wp_error( $resp ) ) return $resp;
        $code = (int) wp_remote_retrieve_response_code( $resp );
        if ( $code < 200 || $code >= 300 ) {
            return new \WP_Error( 'rest_http_' . $code, 'HTTP ' . $code . ': ' . substr( wp_remote_retrieve_body( $resp ), 0, 200 ) );
        }
        return true;
    }

    protected static function guess_mime( $file ) {
        $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
        return array(
            'csv'    => 'text/csv',
            'tsv'    => 'text/tab-separated-values',
            'json'   => 'application/json',
            'ndjson' => 'application/x-ndjson',
            'xml'    => 'application/xml',
            'xlsx'   => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip'    => 'application/zip',
        )[ $ext ] ?? 'application/octet-stream';
    }
}
