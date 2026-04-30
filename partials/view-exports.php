<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Exports — list of jobs, with download / re-run / delete.
 *
 * @package Pelican
 */
global $wpdb;
$jobs_tbl = $wpdb->prefix . 'pl_jobs';

/* Handle one-shot download via signed link */
if ( ! empty( $_GET['pelican_dl'] ) && current_user_can( 'manage_woocommerce' ) ) {
    $jid = (int) $_GET['pelican_dl'];
    $j   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jobs_tbl} WHERE id = %d", $jid ), ARRAY_A );
    if ( $j && ! empty( $j['file_path'] ) ) {
        $u = wp_upload_dir();
        $abs = trailingslashit( $u['basedir'] ) . ltrim( $j['file_path'], '/\\' );
        if ( file_exists( $abs ) ) {
            nocache_headers();
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Disposition: attachment; filename="' . basename( $abs ) . '"' );
            header( 'Content-Length: ' . filesize( $abs ) );
            readfile( $abs );
            exit;
        }
    }
}

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
?>
<div class="pl-wrap wrap">
    <?php include PELICAN_PATH . 'partials/_page-nav.php'; ?>

    <section class="pl-section">
        <div class="pl-section-head">
            <h2 class="pl-h2"><?php esc_html_e( '📦 Exports', 'pelican' ); ?></h2>
            <span class="pl-muted"><?php printf( esc_html__( '%d jobs total', 'pelican' ), (int) $total ); ?></span>
        </div>

        <form class="pl-filters" method="get">
            <input type="hidden" name="page" value="pelican-exports" />
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
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=pelican-exports' ) ); ?>" class="pl-link"><?php esc_html_e( 'Reset', 'pelican' ); ?></a>
        </form>

        <?php if ( empty( $jobs ) ) : ?>
            <div class="pl-empty">
                <div class="pl-empty-icon">🃏</div>
                <p><?php esc_html_e( 'No exports yet. Configure a profile in Settings or run a bulk export from the WC orders list.', 'pelican' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pelican-settings-profiles' ) ); ?>" class="pl-btn pl-btn-primary"><?php esc_html_e( '+ Create profile', 'pelican' ); ?></a>
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
                        $dl = $j['file_path'] ? add_query_arg( array( 'page' => 'pelican-exports', 'pelican_dl' => (int) $j['id'] ), admin_url( 'admin.php' ) ) : '';
                        $err = $j['error_message'] ? esc_attr( $j['error_message'] ) : '';
                    ?>
                        <tr>
                            <td>#<?php echo (int) $j['id']; ?></td>
                            <td><span class="pl-pill"><?php echo esc_html( strtoupper( $j['format'] ) ); ?></span></td>
                            <td><?php echo (int) $j['records_count']; ?></td>
                            <td><?php echo $j['file_size'] ? esc_html( size_format( (int) $j['file_size'] ) ) : '—'; ?></td>
                            <td class="pl-muted"><?php echo esc_html( $j['trigger_source'] ); ?></td>
                            <td class="pl-muted"><?php echo esc_html( $j['started_at'] ); ?></td>
                            <td class="pl-muted"><?php echo $j['duration_ms'] ? esc_html( round( $j['duration_ms'] / 1000, 2 ) . 's' ) : '—'; ?></td>
                            <td>
                                <span class="pl-status pl-status-<?php echo esc_attr( $j['status'] ); ?>" <?php echo $err ? 'title="' . $err . '"' : ''; ?>><?php echo esc_html( $j['status'] ); ?></span>
                            </td>
                            <td>
                                <?php if ( $dl ) : ?>
                                    <a href="<?php echo esc_url( $dl ); ?>" class="pl-btn pl-btn-sm">⬇</a>
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
                        $url = add_query_arg( array( 'page' => 'pelican-exports', 'paged' => $p, 'status' => $f_status, 'format' => $f_format ), admin_url( 'admin.php' ) );
                        $cls = $p === $paged ? 'pl-pager-link pl-pager-cur' : 'pl-pager-link';
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $cls; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
