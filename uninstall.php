<?php
/**
 * Red-Headed Pro — Uninstall
 *
 * Fired when the plugin is deleted from wp-admin > Plugins > Delete.
 * Default behaviour: full data cleanup. Users can opt out via the
 * "Clean on uninstall" toggle in Settings > General > Data hygiene.
 *
 * Owned data:
 *  - Options:    pelican_settings, pelican_webhooks, pelican_*_default_*,
 *                pelican_email_subject, pelican_email_body,
 *                pelican_decimal_separator, pelican_default_filename_pattern,
 *                pelican_retention_days, pelican_register_wc_status_exported,
 *                pelican_notify_*, pelican_uninstall_clean,
 *                pelican_db_version (DB_VERSION_KEY constant)
 *  - Tables:     {prefix}pl_profiles, {prefix}pl_jobs (shared with Lite)
 *  - Cron hook:  pelican_cron_tick
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

wp_clear_scheduled_hook( 'pelican_cron_tick' );

$clean = (int) get_option( 'pelican_uninstall_clean', 1 );
if ( ! $clean ) {
    return;
}

global $wpdb;

$options = [
    'pelican_settings',
    'pelican_webhooks',
    'pelican_decimal_separator',
    'pelican_default_email_body',
    'pelican_default_email_subject',
    'pelican_default_email_to',
    'pelican_default_filename_pattern',
    'pelican_default_sftp_host',
    'pelican_default_sftp_pass_enc',
    'pelican_default_sftp_path',
    'pelican_default_sftp_port',
    'pelican_default_sftp_user',
    'pelican_email_body',
    'pelican_email_subject',
    'pelican_notify_on_failure',
    'pelican_notify_recipients',
    'pelican_notify_subject',
    'pelican_register_wc_status_exported',
    'pelican_retention_days',
    'pelican_uninstall_clean',
    'pelican_db_version',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

/* Per-profile last-run flags use a `pelican_last_run_<id>` prefix. */
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( 'pelican_last_run_' ) . '%'
    )
);

/* Tables — only drop when the sister edition is gone. */
$sister = WP_PLUGIN_DIR . '/red-headed-lite/red-headed-lite.php';
if ( ! file_exists( $sister ) ) {
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pl_jobs' );
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pl_profiles' );
}
