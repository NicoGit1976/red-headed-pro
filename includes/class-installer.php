<?php
/**
 * Installer — DB schema for Harlequin.
 *
 * Tables:
 *   wp_pl_profiles  — saved export configurations (filter, columns, format, destinations)
 *   wp_pl_jobs      — every export run (status, file_path, records, duration, trigger source)
 *
 * @package Red_Headed_Pro
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Red_Headed_Installer {
    const DB_VERSION_KEY = 'red_headed_db_version';
    const DB_VERSION     = '1.0';

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $profiles = $wpdb->prefix . 'rh_profiles';
        $jobs     = $wpdb->prefix . 'rh_jobs';

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
        /* Reusable destination connections (refactor Phase 1 — SPEC-red-headed-reusable-connections). */
        if ( ! class_exists( 'Red_Headed_Connection_Repo' ) ) {
            require_once __DIR__ . '/class-connection-repo.php';
        }
        Red_Headed_Connection_Repo::maybe_create_table();
        update_option( self::DB_VERSION_KEY, self::DB_VERSION );

        /* Schedule the master cron tick on a 5-minute cadence (Pro) so sub-hourly
           profile schedules can fire. Falls back gracefully if the custom interval
           isn't registered yet — Red_Headed_Cron::maybe_reschedule_tick() corrects it
           on the next request. Lite no-op (tick returns early when cron is locked). */
        if ( ! wp_next_scheduled( 'red_headed_cron_tick' ) ) {
            wp_schedule_event( time() + 300, 'red_headed_5min', 'red_headed_cron_tick' );
        }

        set_transient( 'red_headed_just_activated', 1, 30 );
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'red_headed_cron_tick' );
    }

    public static function uninstall() {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rh_jobs' );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rh_profiles' );
        delete_option( self::DB_VERSION_KEY );
        delete_option( 'red_headed_settings' );
        delete_option( 'red_headed_webhooks' );
    }

    /**
     * One-shot data migration from the legacy "pelican / pl_" identifiers to the
     * new "red_headed / rh_" namespace. Renames the two custom tables, copies
     * every pelican_* option to its red_headed_* counterpart, and clears the old
     * cron event. Idempotent — guarded by a single flag option, and only acts
     * when legacy data is actually present.
     */
    public static function migrate_from_pelican() {
        if ( 'yes' === get_option( 'red_headed_migrated_v1' ) ) {
            return;
        }
        global $wpdb;

        /* 1. Rename legacy tables pl_* → rh_* (only when the old one exists and the
              new one does not, so a fresh install is never touched). */
        foreach ( array( 'profiles', 'jobs' ) as $t ) {
            $old = $wpdb->prefix . 'pl_' . $t;
            $new = $wpdb->prefix . 'rh_' . $t;
            $old_here = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) );
            $new_here = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) );
            if ( $old_here === $old && $new_here !== $new ) {
                $wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" );
            }
        }

        /* 2. Copy legacy options pelican_* → red_headed_*. db_version is intentionally
              skipped so the version check below recreates the schema + cron via activate(). */
        $names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'pelican\\_%'" );
        foreach ( (array) $names as $old_name ) {
            if ( 'pelican_db_version' === $old_name ) {
                continue;
            }
            $new_name = 'red_headed_' . substr( $old_name, strlen( 'pelican_' ) );
            if ( '__rh_absent__' === get_option( $new_name, '__rh_absent__' ) ) {
                update_option( $new_name, get_option( $old_name ) );
            }
        }

        /* 3. Drop the legacy master cron (old hook + schedule names are gone from code). */
        wp_clear_scheduled_hook( 'pelican_cron_tick' );

        update_option( 'red_headed_migrated_v1', 'yes' );
    }

    public static function maybe_upgrade() {
        self::migrate_from_pelican();
        $current = get_option( self::DB_VERSION_KEY, '' );
        if ( $current !== self::DB_VERSION ) {
            self::activate();
        }
    }
}
