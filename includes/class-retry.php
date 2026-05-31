<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Retry queue — re-delivers failed destination shipments when the receiving
 * server (ERP / SFTP / REST endpoint…) is momentarily unreachable. Each failed
 * delivery is queued and re-attempted on every cron tick, with backoff, until
 * it succeeds or hits the configured max attempts (0 = keep trying until the
 * server receives it).
 *
 * The built export file stays on disk and is re-shipped as-is (same filename),
 * so retries are exact — no rebuild, no duplicate numbering.
 *
 * @package Red_Headed_Pro
 */
class Red_Headed_Retry {

    const OPTION    = 'red_headed_retry_queue';
    const MAX_QUEUE = 1000; /* safety cap on pending records */

    /** Queue one failed delivery for re-attempt. */
    public static function enqueue( $args ) {
        $file = isset( $args['file'] ) ? (string) $args['file'] : '';
        $dest = isset( $args['dest'] ) ? (array) $args['dest'] : array();
        if ( $file === '' || empty( $dest ) ) return false;

        $q = self::queue();
        if ( count( $q ) >= self::MAX_QUEUE ) array_shift( $q ); /* drop oldest to stay bounded */
        $q[] = array(
            'id'         => uniqid( 'r', true ),
            'job_id'     => isset( $args['job_id'] ) ? (int) $args['job_id'] : 0,
            'dest'       => $dest,
            'file'       => $file,
            'format'     => isset( $args['format'] ) ? (string) $args['format'] : '',
            'retry_max'  => isset( $args['retry_max'] ) ? max( 0, (int) $args['retry_max'] ) : 0,
            'attempts'   => 0,
            'next_at'    => time(), /* eligible on the next tick */
            'last_error' => isset( $args['error'] ) ? substr( (string) $args['error'], 0, 300 ) : '',
            'created'    => time(),
        );
        return self::save( $q );
    }

    /** Process all due retries — called from the cron tick (Pro). */
    public static function process() {
        if ( Red_Headed_Soft_Lock::is_locked( 'cron' ) ) return;
        $q = self::queue();
        if ( empty( $q ) ) return;
        $now  = time();
        $keep = array();

        foreach ( $q as $rec ) {
            if ( (int) ( $rec['next_at'] ?? 0 ) > $now ) { $keep[] = $rec; continue; } /* not due */

            $file = (string) ( $rec['file'] ?? '' );
            if ( $file === '' || ! file_exists( $file ) ) continue; /* source gone → drop */

            /* Re-ship with the file's existing name (strip any per-destination filename
               pattern so it isn't re-resolved against an empty context). */
            $dest = (array) ( $rec['dest'] ?? array() );
            unset( $dest['filename_pattern'] );

            $rec['attempts'] = (int) ( $rec['attempts'] ?? 0 ) + 1;
            $ok = Red_Headed_Destination_Dispatcher::ship( $dest, $file, array(), (string) ( $rec['format'] ?? '' ) );

            if ( ! is_wp_error( $ok ) ) {
                self::note_job( (int) ( $rec['job_id'] ?? 0 ), sprintf( '[Red_Headed_Pro] retry OK after %d attempt(s) — %s', $rec['attempts'], $dest['type'] ?? '?' ) );
                continue; /* delivered → drop from queue */
            }

            $rec['last_error'] = substr( $ok->get_error_message(), 0, 300 );
            $max = (int) ( $rec['retry_max'] ?? 0 );
            if ( $max > 0 && $rec['attempts'] >= $max ) {
                self::note_job( (int) ( $rec['job_id'] ?? 0 ), sprintf( '[Red_Headed_Pro] retry GAVE UP after %d attempts — %s: %s', $rec['attempts'], $dest['type'] ?? '?', $rec['last_error'] ) );
                continue; /* exhausted → drop */
            }
            $rec['next_at'] = $now + self::backoff( $rec['attempts'] );
            $keep[] = $rec;
        }
        self::save( $keep );
    }

    /** Backoff schedule (seconds): 5,5,10,15,30 min then hourly. */
    protected static function backoff( $attempts ) {
        $steps = array( 300, 300, 600, 900, 1800, 3600 );
        $idx   = min( max( 1, (int) $attempts ) - 1, count( $steps ) - 1 );
        return $steps[ $idx ];
    }

    public static function pending_count() { return count( self::queue() ); }

    protected static function queue() {
        $q = get_option( self::OPTION, array() );
        return is_array( $q ) ? $q : array();
    }
    protected static function save( $q ) {
        return update_option( self::OPTION, array_values( (array) $q ), false );
    }
    protected static function note_job( $job_id, $msg ) {
        error_log( $msg );
        if ( ! $job_id ) return;
        global $wpdb;
        $tbl      = $wpdb->prefix . 'rh_jobs';
        $existing = (string) $wpdb->get_var( $wpdb->prepare( "SELECT error_message FROM {$tbl} WHERE id = %d", (int) $job_id ) );
        $wpdb->update( $tbl, array( 'error_message' => substr( trim( $existing . "\n" . $msg ), 0, 1500 ) ), array( 'id' => (int) $job_id ) );
    }
}
