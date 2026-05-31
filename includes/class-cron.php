<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Cron — scheduled exports (Pro).
 *
 * Hourly tick reads every active profile and decides whether to fire.
 *
 * @package Pelican
 */
class Pelican_Cron {
    public static function init() {
        add_action( 'pelican_cron_tick', array( __CLASS__, 'tick' ) );
        add_filter( 'cron_schedules', array( __CLASS__, 'register_intervals' ) );
        add_action( 'init', array( __CLASS__, 'maybe_reschedule_tick' ) );
    }
    public static function register_intervals( $schedules ) {
        $schedules['pelican_5min']  = array( 'interval' => 5  * MINUTE_IN_SECONDS, 'display' => __( 'Every 5 minutes (Red-Headed)',  'red-headed-pro' ) );
        $schedules['pelican_15min'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => __( 'Every 15 minutes (Red-Headed)', 'red-headed-pro' ) );
        $schedules['pelican_30min'] = array( 'interval' => 30 * MINUTE_IN_SECONDS, 'display' => __( 'Every 30 minutes (Red-Headed)', 'red-headed-pro' ) );
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Once a week', 'red-headed-pro' ) );
        }
        return $schedules;
    }
    /**
     * Keep the master tick on a 5-minute cadence so sub-hourly profile
     * schedules (every 5/15/30 min) can actually fire — each profile still
     * runs only at its own configured interval (see should_run). Transparently
     * upgrades installs that were scheduled on the old hourly tick.
     *
     * NOTE: like all WP-cron, the tick only fires on site traffic unless a real
     * server cron hits wp-cron.php. For precise intervals, set up a server cron.
     */
    public static function maybe_reschedule_tick() {
        if ( Pelican_Soft_Lock::is_locked( 'cron' ) ) return; /* Lite: leave default tick */
        if ( wp_get_schedule( 'pelican_cron_tick' ) === 'pelican_5min' ) return;
        wp_clear_scheduled_hook( 'pelican_cron_tick' );
        wp_schedule_event( time() + 60, 'pelican_5min', 'pelican_cron_tick' );
    }
    public static function tick() {
        if ( Pelican_Soft_Lock::is_locked( 'cron' ) ) return;
        /* Re-attempt any deliveries that failed (e.g. SAP/SFTP server was down). */
        if ( class_exists( 'Pelican_Retry' ) ) Pelican_Retry::process();
        foreach ( Pelican_Profile_Repo::all() as $profile ) {
            if ( ( $profile['status'] ?? '' ) !== 'active' ) continue;
            $sched = $profile['schedule'] ?? 'manual';
            if ( $sched === 'manual' ) continue;
            if ( ! self::should_run( $profile, $sched ) ) continue;
            Pelican_Export_Engine::run( $profile, 'cron:' . $sched );
            update_option( 'pelican_last_run_' . (int) $profile['id'], time(), false );
        }
    }
    protected static function should_run( $profile, $sched ) {
        $last = (int) get_option( 'pelican_last_run_' . (int) $profile['id'], 0 );
        $now  = time();
        $interval = self::interval_seconds( $sched, $profile['schedule_meta'] ?? array() );
        return ( $now - $last ) >= $interval;
    }
    protected static function interval_seconds( $sched, $meta ) {
        switch ( $sched ) {
            case 'every_5min':  return 5  * MINUTE_IN_SECONDS;
            case 'every_15min': return 15 * MINUTE_IN_SECONDS;
            case 'every_30min': return 30 * MINUTE_IN_SECONDS;
            case 'hourly':     return HOUR_IN_SECONDS;
            case 'twicedaily': return 12 * HOUR_IN_SECONDS;
            case 'daily':      return DAY_IN_SECONDS;
            case 'weekly':     return 7 * DAY_IN_SECONDS;
            case 'custom':     return max( 60, (int) ( $meta['interval'] ?? 3600 ) );
        }
        return DAY_IN_SECONDS;
    }
}
