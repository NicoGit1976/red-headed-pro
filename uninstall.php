<?php
/**
 * Red-Headed Pro — Uninstall
 *
 * Fired when the plugin is deleted from wp-admin > Plugins > Delete.
 * Default behaviour: full data cleanup. Users can opt out via the
 * "Clean on uninstall" toggle in Settings > General > Data hygiene.
 *
 * Owned data:
 *  - Options:    red_headed_settings, red_headed_webhooks, red_headed_*_default_*,
 *                red_headed_email_subject, red_headed_email_body,
 *                red_headed_decimal_separator, red_headed_default_filename_pattern,
 *                red_headed_retention_days, red_headed_register_wc_status_exported,
 *                red_headed_notify_*, red_headed_uninstall_clean,
 *                red_headed_db_version (DB_VERSION_KEY constant)
 *  - Tables:     {prefix}rh_profiles, {prefix}rh_jobs (shared with Lite)
 *  - Cron hook:  red_headed_cron_tick
 *
 * Tables are preserved if the sister edition (red-headed-lite) is still
 * installed, so a customer who switches between editions never loses
 * exports history or saved profiles.
 *
 * @package RedHeadedPro
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

wp_clear_scheduled_hook( 'red_headed_cron_tick' );

$clean = (int) get_option( 'red_headed_uninstall_clean', 1 );
if ( ! $clean ) {
    return;
}

global $wpdb;

$options = [
    'red_headed_settings',
    'red_headed_webhooks',
    'red_headed_decimal_separator',
    'red_headed_default_email_body',
    'red_headed_default_email_subject',
    'red_headed_default_email_to',
    'red_headed_default_filename_pattern',
    'red_headed_default_sftp_host',
    'red_headed_default_sftp_pass_enc',
    'red_headed_default_sftp_path',
    'red_headed_default_sftp_port',
    'red_headed_default_sftp_user',
    'red_headed_email_body',
    'red_headed_email_subject',
    'red_headed_notify_on_failure',
    'red_headed_notify_recipients',
    'red_headed_notify_subject',
    'red_headed_register_wc_status_exported',
    'red_headed_retention_days',
    'red_headed_uninstall_clean',
    'red_headed_db_version',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

/* Per-profile last-run flags use a `red_headed_last_run_<id>` prefix. */
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( 'red_headed_last_run_' ) . '%'
    )
);

/* Tables — only drop when the sister edition is gone. */
$sister = WP_PLUGIN_DIR . '/red-headed-lite/red-headed-lite.php';
if ( ! file_exists( $sister ) ) {
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rh_jobs' );
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rh_profiles' );
}
