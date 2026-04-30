<?php
/**
 * Installer — DB schema for Harlequin.
 *
 * Tables:
 *   wp_pl_profiles  — saved export configurations (filter, columns, format, destinations)
 *   wp_pl_jobs      — every export run (status, file_path, records, duration, trigger source)
 *
 * @package Pelican
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Pelican_Installer {
    const DB_VERSION_KEY = 'pelican_db_version';
    const DB_VERSION     = '1.0';

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $profiles = $wpdb->prefix . 'pl_profiles';
        $jobs     = $wpdb->prefix . 'pl_jobs';

        $sql_profiles = "CREATE TABLE {$profiles} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name            VARCHAR(255)        NOT NULL DEFAULT '',
            format          VARCHAR(16)         NOT NULL DEFAULT 'csv',
            filters         LONGTEXT                     DEFAULT NULL,
            columns         LONGTEXT                     DEFAULT NULL,
            destinations    LONGTEXT                     DEFAULT NULL,
            schedule        VARCHAR(32)         NOT NULL DEFAULT 'manual',
            schedule_meta   LONGTEXT                     DEFAULT NULL,
            auto_trigger    LONGTEXT                     DEFAULT NULL,
            status          VARCHAR(32)         NOT NULL DEFAULT 'active',
            created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY schedule (schedule)
        ) {$charset};";

        $sql_jobs = "CREATE TABLE {$jobs} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_id      BIGINT(20) UNSIGNED          DEFAULT NULL,
            trigger_source  VARCHAR(64)         NOT NULL DEFAULT 'manual',
            format          VARCHAR(16)         NOT NULL DEFAULT 'csv',
            file_path       VARCHAR(255)                 DEFAULT NULL,
            file_size       BIGINT(20) UNSIGNED          DEFAULT NULL,
            records_count   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            status          VARCHAR(32)         NOT NULL DEFAULT 'pending',
            duration_ms     INT UNSIGNED                 DEFAULT NULL,
            error_message   TEXT                         DEFAULT NULL,
            started_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at     DATETIME                     DEFAULT NULL,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY status (status),
            KEY started_at (started_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_profiles );
        dbDelta( $sql_jobs );
        update_option( self::DB_VERSION_KEY, self::DB_VERSION );

        /* Schedule the cron tick (Pro). Lite no-op. */
        if ( ! wp_next_scheduled( 'pelican_cron_tick' ) ) {
            wp_schedule_event( time() + 300, 'hourly', 'pelican_cron_tick' );
        }

        set_transient( 'pelican_just_activated', 1, 30 );
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'pelican_cron_tick' );
    }

    public static function uninstall() {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pl_jobs' );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pl_profiles' );
        delete_option( self::DB_VERSION_KEY );
        delete_option( 'pelican_settings' );
        delete_option( 'pelican_webhooks' );
    }

    public static function maybe_upgrade() {
        $current = get_option( self::DB_VERSION_KEY, '' );
        if ( $current !== self::DB_VERSION ) {
            self::activate();
        }
    }
}
