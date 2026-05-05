<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Exports — list of jobs, with download / re-run / delete.
 *
 * @package Pelican
 */
global $wpdb;
$jobs_tbl = $wpdb->prefix . 'pl_jobs';

/* v1.4.19 — Download handler moved to an `admin_init` action in the main plugin
   file. It used to run here at view-render time, but by then WP had already
   flushed the admin HTML header → headers() were ignored → user got an HTML
   page mixed with binary, not a clean CSV. The handler now fires BEFORE any
   admin output. See red-headed-pro.php "Download handler" block. */

/* Filters */
$f_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
$f_format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : '';
$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$per      = 20;
$offset   = ( $paged - 1 ) * $per;

$where = array( '1=1' );
$params = array();
if ( $f_status ) { $where[] = 'status = %s'; $params[] = $f_status; }
if ( $f_format ) { $where[] = 'format = %s'; $params[] = $f_format; }
$where_sql = implode( ' AND ', $where );

$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM {$jobs_tbl} WHERE {$where_sql}", $params ) : "SELECT COUNT(*) FROM {$jobs_tbl} WHERE {$where_sql}" );
$jobs  = $wpdb->get_results(
    $params
        ? $wpdb->prepare( "SELECT * FROM {$jobs_tbl} WHERE {$where_sql} ORDER BY started_at DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per, $offset ) ) )
        : $wpdb->prepare( "SELECT * FROM {$jobs_tbl} WHERE {$where_sql} ORDER BY started_at DESC LIMIT %d OFFSET %d", $per, $offset ),
    ARRAY_A
) ?: array();

$total_pages = max( 1, (int) ceil( $total / $per ) );
$is_pro = Pelican_Soft_Lock::is_pro();

/* 24h aggregate stats (mirrors the Waxy Monkey Sync History pill bar). */
$stats_24h = $wpdb->get_row( "SELECT
        SUM( status = 'success' ) AS success_count,
        SUM( status = 'failed' )  AS error_count,
        COUNT(*)                  AS total_syncs,
        ROUND( AVG( duration_ms ), 0 ) AS avg_duration
    FROM {$jobs_tbl}
    WHERE started_at >= DATE_SUB( NOW(), INTERVAL 24 HOUR )", ARRAY_A );

/* Clear-all handler — purges every job row. Disarms guard via wp_nonce + capability. */
if ( isset( $_POST['pl_clear_logs'] ) && check_admin_referer( 'pl_clear_logs' ) && current_user_can( 'manage_woocommerce' ) ) {
    $cleared = (int) $wpdb->query( "DELETE FROM {$jobs_tbl}" );
    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '✓ Cleared %d job rows.', 'pelican' ), $cleared ) . '</p></div>';
    $jobs  = array();
    $total = 0;
    $stats_24h = array( 'success_count' => 0, 'error_count' => 0, 'total_syncs' => 0, 'avg_duration' => 0 );
}
?>
<div class="pl-wrap wrap">
    <?php
    if ( class_exists( 'FH_UI_Helper' ) ) {
        FH_UI_Helper::render_header(
            'Red Headed Pro',
            __( 'Exports Orders Everywhere, Anytime', 'pelican' ),
            'mascot-redheaded-v1.svg',
            array(),
            'red-headed-pro'
        );
    }
    ?>

    <?php include PELICAN_PATH . 'partials/_page-nav.php'; ?>

    <section class="pl-section">
        <div class="pl-section-head">
            <h2 class="pl-h2"><?php esc_html_e( '📦 Exports', 'pelican' ); ?></h2>
            <span class="pl-muted"><?php printf( esc_html__( '%d jobs total', 'pelican' ), (int) $total ); ?></span>
        </div>

        <div class="pl-stats-bar" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
            <span class="pl-stat-pill" style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:600;">✓ <?php printf( esc_html__( '%d success', 'pelican' ), (int) ( $stats_24h['success_count'] ?? 0 ) ); ?></span>
            <span class="pl-stat-pill" style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:600;">⚠ <?php printf( esc_html__( '%d errors', 'pelican' ), (int) ( $stats_24h['error_count'] ?? 0 ) ); ?></span>
            <span class="pl-stat-pill" style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:12px;font-weight:600;"><?php printf( esc_html__( '%d total (24h)', 'pelican' ), (int) ( $stats_24h['total_syncs'] ?? 0 ) ); ?></span>
            <?php if ( ! empty( $stats_24h['avg_duration'] ) ) : ?>
                <span class="pl-stat-pill" style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:12px;font-weight:600;">~<?php echo esc_html( round( ( (int) $stats_24h['avg_duration'] ) / 1000, 2 ) ); ?>s avg</span>
            <?php endif; ?>
        </div>

        <form class="pl-filters" method="get">
            <input type="hidden" name="page" value="red-headed-pro-exports" />
            <select name="status">
                <option value=""><?php esc_html_e( 'All statuses', 'pelican' ); ?></option>
                <?php foreach ( array( 'success', 'running', 'failed' ) as $s ) : ?>
                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $f_status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="format">
                <option value=""><?php esc_html_e( 'All formats', 'pelican' ); ?></option>
                <?php foreach ( array( 'csv', 'tsv', 'json', 'ndjson', 'xml', 'xlsx' ) as $fmt ) : ?>
                    <option value="<?php echo esc_attr( $fmt ); ?>" <?php selected( $f_format, $fmt ); ?>><?php echo esc_html( strtoupper( $fmt ) ); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="pl-btn"><?php esc_html_e( 'Filter', 'pelican' ); ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=red-headed-pro-exports' ) ); ?>" class="pl-link"><?php esc_html_e( 'Reset', 'pelican' ); ?></a>
        </form>

        <?php if ( $total > 0 ) : ?>
            <form method="post" style="display:inline-block;margin:0 0 12px;" onsubmit="return confirm('<?php echo esc_js( __( 'Clear ALL export job rows? Files on disk are kept.', 'pelican' ) ); ?>');">
                <?php wp_nonce_field( 'pl_clear_logs' ); ?>
                <button type="submit" name="pl_clear_logs" class="pl-btn pl-btn-sm" style="color:#991b1b;">🗑 <?php esc_html_e( 'Clear all logs', 'pelican' ); ?></button>
            </form>
        <?php endif; ?>

        <?php if ( empty( $jobs ) ) : ?>
            <div class="pl-empty">
                <div class="pl-empty-icon">🃏</div>
                <p><?php esc_html_e( 'No exports yet. Configure a profile in Settings or run a bulk export from the WC orders list.', 'pelican' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=red-headed-pro-settings-profiles' ) ); ?>" class="pl-btn pl-btn-primary"><?php esc_html_e( '+ Create profile', 'pelican' ); ?></a>
            </div>
        <?php else : ?>
            <table class="pl-table pl-table-zebra">
                <thead><tr>
                    <th>#</th>
                    <th><?php esc_html_e( 'Format', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Records', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Size', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Trigger', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Started', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Duration', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'pelican' ); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ( $jobs as $j ) :
                        $dl = $j['file_path'] ? add_query_arg( array( 'page' => 'red-headed-pro-exports', 'rh_dl' => (int) $j['id'] ), admin_url( 'admin.php' ) ) : '';
                        $err = $j['error_message'] ? esc_attr( $j['error_message'] ) : '';
                    ?>
                        <tr>
                            <td>#<?php echo (int) $j['id']; ?></td>
                            <td><span class="pl-pill"><?php echo esc_html( strtoupper( $j['format'] ) ); ?></span></td>
                            <td>
                                <?php echo (int) $j['records_count']; ?>
                                <?php if ( $j['status'] === 'success' && (int) $j['records_count'] === 0 ) : ?>
                                    <span class="pl-pill pl-pill-warn" title="<?php esc_attr_e( 'No orders matched your filters. Check the profile settings (statuses, dates).', 'pelican' ); ?>" style="background:#fef3c7;color:#92400e;border-color:#fcd34d;font-size:10px;margin-left:4px;">⚠ <?php esc_html_e( 'check filters', 'pelican' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $j['file_size'] ? esc_html( size_format( (int) $j['file_size'] ) ) : '—'; ?></td>
                            <td class="pl-muted"><?php echo esc_html( $j['trigger_source'] ); ?></td>
                            <td class="pl-muted"><?php echo esc_html( $j['started_at'] ); ?></td>
                            <td class="pl-muted"><?php echo $j['duration_ms'] ? esc_html( round( $j['duration_ms'] / 1000, 2 ) . 's' ) : '—'; ?></td>
                            <td>
                                <span class="pl-status pl-status-<?php echo esc_attr( $j['status'] ); ?>" <?php echo $err ? 'title="' . $err . '"' : ''; ?>><?php echo esc_html( $j['status'] ); ?></span>
                            </td>
                            <td>
                                <?php if ( $j['file_path'] && (int) $j['records_count'] > 0 ) : ?>
                                    <button type="button" class="pl-btn pl-btn-sm pl-btn-preview" data-job="<?php echo (int) $j['id']; ?>" title="<?php esc_attr_e( 'Preview the first rows of this export', 'pelican' ); ?>">👁</button>
                                <?php endif; ?>
                                <?php if ( $dl ) : ?>
                                    <a href="<?php echo esc_url( $dl ); ?>" class="pl-btn pl-btn-sm" title="<?php esc_attr_e( 'Download the file', 'pelican' ); ?>">⬇</a>
                                <?php endif; ?>
                                <?php if ( $j['profile_id'] ) : ?>
                                    <button type="button" class="pl-btn pl-btn-sm pl-btn-rerun" data-profile="<?php echo (int) $j['profile_id']; ?>" title="<?php esc_attr_e( 'Re-run profile', 'pelican' ); ?>">↻</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="pl-pager">
                    <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                        $url = add_query_arg( array( 'page' => 'red-headed-pro-exports', 'paged' => $p, 'status' => $f_status, 'format' => $f_format ), admin_url( 'admin.php' ) );
                        $cls = $p === $paged ? 'pl-pager-link pl-pager-cur' : 'pl-pager-link';
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $cls; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
