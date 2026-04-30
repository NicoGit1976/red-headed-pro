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
    }
    public static function register_intervals( $schedules ) {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array(
                'interval' => 604800,
                'display'  => __( 'Once a week', 'pelican' ),
            );
        }
        return $schedules;
    }
    public static function tick() {
        if ( Pelican_Soft_Lock::is_locked( 'cron' ) ) return;
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
            case 'hourly':     return HOUR_IN_SECONDS;
            case 'twicedaily': return 12 * HOUR_IN_SECONDS;
            case 'daily':      return DAY_IN_SECONDS;
            case 'weekly':     return 7 * DAY_IN_SECONDS;
            case 'custom':     return max( 60, (int) ( $meta['interval'] ?? 3600 ) );
        }
        return DAY_IN_SECONDS;
    }
}
