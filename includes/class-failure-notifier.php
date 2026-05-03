<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Pelican_Failure_Notifier — sends an admin email when an export job fails.
 *
 * Hooks:
 *   pelican_export_failed    (job_id, profile, error_message)
 *   pelican_export_delivered (job_id, profile, delivered_array) — scanned for WP_Error legs
 *
 * Settings:
 *   pelican_notify_on_failure   (bool)   1 = enabled
 *   pelican_notify_recipients   (string) comma-separated email list
 *   pelican_notify_subject      (string) supports {{job_id}} {{profile}} {{site}}
 *
 * @package Pelican
 */
class Pelican_Failure_Notifier {

    public static function init() {
        add_action( 'pelican_export_failed',    array( __CLASS__, 'on_failed' ),    10, 3 );
        add_action( 'pelican_export_delivered', array( __CLASS__, 'on_delivered' ), 10, 3 );
    }

    public static function on_failed( $job_id, $profile, $error_message ) {
        self::send( (int) $job_id, $profile, (string) $error_message );
    }

    /** Walks the per-destination delivery results — fires once if any leg returned a WP_Error. */
    public static function on_delivered( $job_id, $profile, $delivered ) {
        if ( ! is_array( $delivered ) ) return;
        $errs = array();
        foreach ( $delivered as $row ) {
            if ( isset( $row['ok'] ) && is_wp_error( $row['ok'] ) ) {
                $type  = $row['destination']['type'] ?? '?';
                $errs[] = $type . ': ' . $row['ok']->get_error_message();
            }
        }
        if ( ! empty( $errs ) ) {
            self::send( (int) $job_id, $profile, implode( "\n", $errs ) );
        }
    }

    private static function send( $job_id, $profile, $error_message ) {
        if ( ! get_option( 'pelican_notify_on_failure', 0 ) ) return;
        $raw = (string) get_option( 'pelican_notify_recipients', get_option( 'admin_email' ) );
        $to  = array_filter( array_map( 'trim', explode( ',', $raw ) ), 'is_email' );
        if ( ! $to ) return;

        $profile_name = is_array( $profile ) && ! empty( $profile['name'] ) ? $profile['name'] : sprintf( __( 'Profile #%d', 'pelican' ), is_array( $profile ) ? (int) ( $profile['id'] ?? 0 ) : 0 );
        $site         = wp_parse_url( home_url(), PHP_URL_HOST ) ?: get_bloginfo( 'name' );
        $subject_tpl  = (string) get_option( 'pelican_notify_subject', '⚠ Red-Headed export failed — job #{{job_id}}' );
        $subject      = strtr( $subject_tpl, array(
            '{{job_id}}'  => (string) $job_id,
            '{{profile}}' => $profile_name,
            '{{site}}'    => $site,
        ) );

        $job_url = admin_url( 'admin.php?page=' . ( defined( 'PELICAN_SLUG' ) ? PELICAN_SLUG : 'red-headed-pro' ) . '-exports' );
        $body    = sprintf(
            "%s\n\n%s: %s\n%s: %s\n%s: %d\n\n%s\n%s\n\n%s\n%s\n",
            __( 'A Red-Headed export job has failed.', 'pelican' ),
            __( 'Profile', 'pelican' ),    $profile_name,
            __( 'Site',    'pelican' ),    $site,
            __( 'Job ID',  'pelican' ),    $job_id,
            __( 'Error message:', 'pelican' ), $error_message,
            __( 'Open the Exports list:', 'pelican' ), $job_url
        );

        wp_mail( $to, $subject, $body );
    }
}
