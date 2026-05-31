<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Red_Headed_Filename_Resolver — single source of truth for filename pattern
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
 *     {job_id}       rh_jobs.id of the current run
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
 * v1.4.26 (Red_Headed_Pro Pro+Lite).
 *
 * @package Red_Headed_Pro
 */
class Red_Headed_Filename_Resolver {

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
            /* EU-style export-time tokens (day-month-year). */
            '{date_eu}'     => current_time( 'd-m-Y' ),
            '{datetime_eu}' => current_time( 'd-m-Y-H-i-s' ),
            '{timestamp}' => (string) current_time( 'timestamp' ),
            '{random}'    => wp_generate_password( 6, false ),
            '{digits}'    => str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT ),
            '{order_id}'        => '',
            '{order_number}'    => '',
            '{customer_id}'     => '',
            '{customer_email}'  => '',
            '{customer_name}'   => '',
            /* First-order creation date (EU style) — for one-file-per-order naming. */
            '{order_date}'      => '',
            '{order_time}'      => '',
            '{order_datetime}'  => '',
        );

        $first = isset( $context['first_order'] ) ? $context['first_order'] : null;
        if ( is_a( $first, 'WC_Order' ) ) {
            $repl['{order_id}']       = (string) $first->get_id();
            $repl['{order_number}']   = sanitize_file_name( (string) $first->get_order_number() );
            $repl['{customer_id}']    = (string) $first->get_customer_id();
            $repl['{customer_email}'] = sanitize_file_name( (string) $first->get_billing_email() );
            $name = trim( $first->get_billing_first_name() . ' ' . $first->get_billing_last_name() );
            $repl['{customer_name}']  = $name ? sanitize_file_name( $name ) : '';
            $created = $first->get_date_created();
            if ( $created ) {
                $repl['{order_date}']     = $created->date( 'd-m-Y' );
                $repl['{order_time}']     = $created->date( 'H-i-s' );
                $repl['{order_datetime}'] = $created->date( 'd-m-Y-H-i-s' );
            }
        }

        $resolved = strtr( $pattern, $repl );

        /* v1.5.1 — Dynamic {date:FORMAT} placeholder. Lets users write any PHP
           date() format, e.g. {date:d-m-Y-H-i-s} or {date:Y_m_d}. Filesystem-
           unsafe characters (: / \) are replaced with dashes. */
        $resolved = preg_replace_callback( '/\{date:([^}]+)\}/', function ( $m ) {
            $formatted = current_time( $m[1] );
            return str_replace( array( ':', '/', '\\', ' ' ), '-', $formatted );
        }, $resolved );

        /* v1.5.1 — Dynamic {random:N} and {digits:N} placeholders.
           {random:N} → N alphanumeric chars (1-20, default 6).
           {digits:N} → N numeric digits only (1-20, default 6).
           AOE-compatible: use {digits:9} for a 9-digit numeric suffix. */
        $resolved = preg_replace_callback( '/\{random:(\d+)\}/', function ( $m ) {
            $len = max( 1, min( 20, (int) $m[1] ) );
            return wp_generate_password( $len, false );
        }, $resolved );
        $resolved = preg_replace_callback( '/\{digits:(\d+)\}/', function ( $m ) {
            $len = max( 1, min( 20, (int) $m[1] ) );
            $out = '';
            while ( strlen( $out ) < $len ) {
                $out .= str_pad( (string) wp_rand( 0, 999999999 ), 9, '0', STR_PAD_LEFT );
            }
            return substr( $out, 0, $len );
        }, $resolved );

        if ( $ext && stripos( $resolved, '.' . $ext ) === false ) {
            $resolved .= '.' . $ext;
        }
        return sanitize_file_name( $resolved );
    }

    /** List of placeholders for the helper text / tooltip. */
    public static function placeholders() {
        return array(
            '{profile}'         => __( 'Profile name', 'red-headed-pro' ),
            '{format}'          => __( 'csv | json | xlsx | xml | …', 'red-headed-pro' ),
            '{records}'         => __( 'Row count', 'red-headed-pro' ),
            '{job_id}'          => __( 'Job ID', 'red-headed-pro' ),
            '{date}'            => 'Y-m-d',
            '{time}'            => 'H-i-s',
            '{datetime}'        => 'Y-m-d_H-i-s',
            '{date_eu}'         => 'd-m-Y',
            '{datetime_eu}'     => 'd-m-Y-H-i-s',
            '{date:FORMAT}'     => __( 'Custom date (PHP date format, e.g. {date:d-m-Y-H-i-s})', 'red-headed-pro' ),
            '{timestamp}'       => __( 'Unix epoch', 'red-headed-pro' ),
            '{order_id}'        => __( 'First order WP ID (numeric)', 'red-headed-pro' ),
            '{order_number}'    => __( 'First order number (e.g. e-4123)', 'red-headed-pro' ),
            '{order_date}'      => __( 'First order date (d-m-Y)', 'red-headed-pro' ),
            '{order_time}'      => __( 'First order time (H-i-s)', 'red-headed-pro' ),
            '{order_datetime}'  => __( 'First order date+time (d-m-Y-H-i-s)', 'red-headed-pro' ),
            '{customer_id}'     => __( 'First order customer ID', 'red-headed-pro' ),
            '{customer_email}'  => __( 'First order billing email', 'red-headed-pro' ),
            '{customer_name}'   => __( 'First order billing first + last name', 'red-headed-pro' ),
            '{random}'          => __( '6-char alphanumeric (uniqueness)', 'red-headed-pro' ),
            '{random:N}'        => __( 'N alphanumeric chars (e.g. {random:10})', 'red-headed-pro' ),
            '{digits}'          => __( '6-digit numeric (e.g. 048291)', 'red-headed-pro' ),
            '{digits:N}'        => __( 'N numeric digits (e.g. {digits:9} for AOE compat)', 'red-headed-pro' ),
        );
    }
}
