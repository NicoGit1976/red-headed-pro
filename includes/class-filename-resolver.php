<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Pelican_Filename_Resolver — single source of truth for filename pattern
 * resolution across all destinations (gdrive, sftp, etc.).
 *
 * Pattern syntax: a string with curly-brace placeholders. Empty pattern → caller
 * uses its default (typically basename($file)).
 *
 * Supported placeholders:
 *   Profile / job
 *     {profile}      Sanitized profile name (e.g. "SFTP")
 *     {format}       File format (csv, json, xlsx, …)
 *     {records}      Number of records in this export
 *     {job_id}       pl_jobs.id of the current run
 *
 *   Time
 *     {date}         Y-m-d (e.g. 2026-05-03)
 *     {time}         H-i-s
 *     {datetime}     Y-m-d_H-i-s
 *     {timestamp}    Unix epoch (uniqueness when {random} is too short)
 *
 *   First order (only meaningful when the export contains orders;
 *   for multi-order exports, resolves to the first one in the result set)
 *     {order_id}        WP order ID (numeric)
 *     {order_number}    WC order number (often e-prefixed, e.g. e-4123)
 *     {customer_id}     WC customer ID (login)
 *     {customer_email}  Billing email
 *     {customer_name}   Billing first + last name (sanitized)
 *
 *   Random
 *     {random}       6-char alphanumeric (security / collision-avoidance)
 *
 * v1.4.26 (Pelican Pro+Lite).
 *
 * @package Pelican
 */
class Pelican_Filename_Resolver {

    /**
     * @param string $pattern Pattern with {placeholders}.
     * @param array  $context Keys: profile_name, format, records, job_id, first_order (WC_Order|null), file (path).
     * @return string Resolved filename (sanitized, extension auto-appended if missing).
     */
    public static function resolve( $pattern, $context = array() ) {
        $pattern = trim( (string) $pattern );
        $file    = isset( $context['file'] ) ? (string) $context['file'] : '';
        if ( $pattern === '' ) return $file ? basename( $file ) : '';

        $ext = $file ? pathinfo( $file, PATHINFO_EXTENSION ) : '';

        $repl = array(
            '{profile}'   => isset( $context['profile_name'] ) ? sanitize_file_name( $context['profile_name'] ) : '',
            '{format}'    => isset( $context['format'] ) ? sanitize_key( $context['format'] ) : ( $ext ?: 'csv' ),
            '{records}'   => isset( $context['records'] ) ? (string) (int) $context['records'] : '0',
            '{job_id}'    => isset( $context['job_id'] ) ? (string) (int) $context['job_id'] : '0',
            '{date}'      => current_time( 'Y-m-d' ),
            '{time}'      => current_time( 'H-i-s' ),
            '{datetime}'  => current_time( 'Y-m-d_H-i-s' ),
            '{timestamp}' => (string) current_time( 'timestamp' ),
            '{random}'    => wp_generate_password( 6, false ),
            '{order_id}'       => '',
            '{order_number}'   => '',
            '{customer_id}'    => '',
            '{customer_email}' => '',
            '{customer_name}'  => '',
        );

        $first = isset( $context['first_order'] ) ? $context['first_order'] : null;
        if ( is_a( $first, 'WC_Order' ) ) {
            $repl['{order_id}']       = (string) $first->get_id();
            $repl['{order_number}']   = sanitize_file_name( (string) $first->get_order_number() );
            $repl['{customer_id}']    = (string) $first->get_customer_id();
            $repl['{customer_email}'] = sanitize_file_name( (string) $first->get_billing_email() );
            $name = trim( $first->get_billing_first_name() . ' ' . $first->get_billing_last_name() );
            $repl['{customer_name}']  = $name ? sanitize_file_name( $name ) : '';
        }

        $resolved = strtr( $pattern, $repl );
        if ( $ext && stripos( $resolved, '.' . $ext ) === false ) {
            $resolved .= '.' . $ext;
        }
        return sanitize_file_name( $resolved );
    }

    /** List of placeholders for the helper text / tooltip. */
    public static function placeholders() {
        return array(
            '{profile}'         => __( 'Profile name', 'pelican' ),
            '{format}'          => __( 'csv | json | xlsx | xml | …', 'pelican' ),
            '{records}'         => __( 'Row count', 'pelican' ),
            '{job_id}'          => __( 'Job ID', 'pelican' ),
            '{date}'            => 'Y-m-d',
            '{time}'            => 'H-i-s',
            '{datetime}'        => 'Y-m-d_H-i-s',
            '{timestamp}'       => __( 'Unix epoch', 'pelican' ),
            '{order_id}'        => __( 'First order WP ID', 'pelican' ),
            '{order_number}'    => __( 'First order number (e.g. e-4123)', 'pelican' ),
            '{customer_id}'     => __( 'First order customer ID', 'pelican' ),
            '{customer_email}'  => __( 'First order billing email', 'pelican' ),
            '{customer_name}'   => __( 'First order billing first + last name', 'pelican' ),
            '{random}'          => __( '6-char alphanumeric (uniqueness)', 'pelican' ),
        );
    }
}
