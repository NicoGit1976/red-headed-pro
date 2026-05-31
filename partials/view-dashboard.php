<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Dashboard — Harlequin (Lion Frog DNA).
 *
 * @package Red_Headed_Pro
 */
global $wpdb;
$jobs_tbl = $wpdb->prefix . 'rh_jobs';
$stats = array(
    'total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_tbl}" ),
    'this_month' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_tbl} WHERE started_at >= DATE_FORMAT(NOW(), '%Y-%m-01')" ),
    'success'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_tbl} WHERE status = 'success'" ),
    'failed'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_tbl} WHERE status = 'failed'" ),
);
$by_format = $wpdb->get_results( "SELECT format, COUNT(*) AS n FROM {$jobs_tbl} GROUP BY format", ARRAY_A );
$recent    = $wpdb->get_results( "SELECT id, format, status, records_count, file_size, started_at, trigger_source FROM {$jobs_tbl} ORDER BY started_at DESC LIMIT 8", ARRAY_A ) ?: array();
$is_pro    = Red_Headed_Soft_Lock::is_pro();
$rate      = Red_Headed_Destination_Email::rate_status();
$profiles_n = count( Red_Headed_Profile_Repo::all() );

$formats = array(
    'csv'    => array( 'icon' => '📄', 'label' => 'CSV',    'lock' => null ),
    'tsv'    => array( 'icon' => '📑', 'label' => 'TSV',    'lock' => 'format_tsv' ),
    'json'   => array( 'icon' => '🧬', 'label' => 'JSON',   'lock' => 'format_json' ),
    'ndjson' => array( 'icon' => '🧬', 'label' => 'NDJSON', 'lock' => 'format_ndjson' ),
    'xml'    => array( 'icon' => '🏷️', 'label' => 'XML',    'lock' => 'format_xml' ),
    'xlsx'   => array( 'icon' => '📗', 'label' => 'XLSX',   'lock' => 'format_xlsx' ),
);
$by_format_map = array(); foreach ( (array) $by_format as $r ) $by_format_map[ $r['format'] ] = (int) $r['n'];
?>
<?php
/* Charte v1 §4 — canonical Hub cockpit: header + .fh-tab top nav + 2-col grid.
   Replaces the legacy .pl-wrap full-width layout + .pl-page-nav. */
Red_Headed_Admin::open_cockpit_shell( 'red-headed-pro' );
FH_UI_Helper::open_cockpit_main();

FH_UI_Helper::render_kpi_strip( array(
    array( 'icon' => '📊', 'value' => (int) $stats['total'],      'label' => __( 'Total exports', 'red-headed-pro' ) ),
    array( 'icon' => '📅', 'value' => (int) $stats['this_month'], 'label' => __( 'This month', 'red-headed-pro' ) ),
    array( 'icon' => '✓',  'value' => (int) $stats['success'],    'label' => __( 'Successful', 'red-headed-pro' ), 'tone' => 'success' ),
    array( 'icon' => '⚠️', 'value' => (int) $stats['failed'],     'label' => __( 'Failed', 'red-headed-pro' ), 'tone' => $stats['failed'] > 0 ? 'bad' : 'info' ),
) );
?>

    <section class="pl-section">
        <h2 class="pl-h2"><?php esc_html_e( '🗂️ Available formats', 'red-headed-pro' ); ?></h2>
        <div class="pl-formats">
            <?php foreach ( $formats as $slug => $meta ) :
                $locked = $meta['lock'] && Red_Headed_Soft_Lock::is_locked( $meta['lock'] );
                $count  = $by_format_map[ $slug ] ?? 0;
            ?>
                <div class="pl-format <?php echo $locked ? 'pl-format-locked' : ''; ?>">
                    <div class="pl-format-icon"><?php echo $meta['icon']; ?></div>
                    <div class="pl-format-label">
                        <?php echo esc_html( $meta['label'] ); ?>
                        <?php if ( $locked ) echo wp_kses_post( Red_Headed_Soft_Lock::badge() ); ?>
                    </div>
                    <div class="pl-format-count"><?php echo (int) $count; ?> <?php esc_html_e( 'exports', 'red-headed-pro' ); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="pl-section">
        <div class="pl-section-head">
            <h2 class="pl-h2"><?php esc_html_e( '🕒 Recent exports', 'red-headed-pro' ); ?></h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=red-headed-pro-exports' ) ); ?>" class="pl-link"><?php esc_html_e( 'See all →', 'red-headed-pro' ); ?></a>
        </div>
        <?php if ( empty( $recent ) ) : ?>
            <div class="pl-empty">
                <div class="pl-empty-icon">🐸</div>
                <p><?php esc_html_e( 'No exports yet. Go to Settings → Profiles to create one, or use "🐸 Export with Red-Headed" as a bulk action on the WC orders list.', 'red-headed-pro' ); ?></p>
            </div>
        <?php else : ?>
            <table class="pl-table">
                <thead><tr>
                    <th>#</th>
                    <th><?php esc_html_e( 'Format', 'red-headed-pro' ); ?></th>
                    <th><?php esc_html_e( 'Records', 'red-headed-pro' ); ?></th>
                    <th><?php esc_html_e( 'Size', 'red-headed-pro' ); ?></th>
                    <th><?php esc_html_e( 'Trigger', 'red-headed-pro' ); ?></th>
                    <th><?php esc_html_e( 'When', 'red-headed-pro' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'red-headed-pro' ); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ( $recent as $r ) : ?>
                        <tr>
                            <td>#<?php echo (int) $r['id']; ?></td>
                            <td><span class="pl-pill"><?php echo esc_html( strtoupper( $r['format'] ) ); ?></span></td>
                            <td><?php echo (int) $r['records_count']; ?></td>
                            <td><?php echo $r['file_size'] ? size_format( (int) $r['file_size'] ) : '—'; ?></td>
                            <td class="pl-muted"><?php echo esc_html( $r['trigger_source'] ); ?></td>
                            <td class="pl-muted"><?php echo esc_html( $r['started_at'] ); ?></td>
                            <td><span class="pl-status pl-status-<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( $r['status'] ); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

<?php
/* ── SIDEBAR : CTA / navigation only (operational tables stay in MAIN). ── */
FH_UI_Helper::close_cockpit_main();
FH_UI_Helper::open_cockpit_sidebar( __( 'Quick actions', 'red-headed-pro' ) );

$rh_qa = array(
    array( 'icon' => '📁', 'label' => __( 'Profiles', 'red-headed-pro' ),     'url' => admin_url( 'admin.php?page=red-headed-pro-settings-profiles' ),     'value' => $profiles_n . ' / ' . ( $is_pro ? '∞' : '1' ) ),
    array( 'icon' => '📦', 'label' => __( 'Exports', 'red-headed-pro' ),      'url' => admin_url( 'admin.php?page=red-headed-pro-exports' ) ),
    array( 'icon' => '🛒', 'label' => __( 'WC Orders', 'red-headed-pro' ),    'url' => admin_url( 'admin.php?page=wc-orders' ) ),
    array( 'icon' => '📡', 'label' => __( 'Destinations', 'red-headed-pro' ), 'url' => admin_url( 'admin.php?page=red-headed-pro-settings-destinations' ) ),
);
if ( $is_pro ) {
    $rh_qa[] = array( 'icon' => '⏰', 'label' => __( 'Cron schedules', 'red-headed-pro' ), 'url' => admin_url( 'admin.php?page=red-headed-pro-settings-cron' ) );
    $rh_qa[] = array( 'icon' => '🔔', 'label' => __( 'Webhooks', 'red-headed-pro' ),       'url' => admin_url( 'admin.php?page=red-headed-pro-settings-webhooks' ) );
}
FH_UI_Helper::render_sidebar_card( __( 'Quick actions', 'red-headed-pro' ), $rh_qa );

if ( ! $is_pro ) {
    $q_limit     = ( $rate['limit'] >= 1e9 )     ? '∞' : number_format( (int) $rate['limit'] );
    $q_remaining = ( $rate['remaining'] >= 1e9 ) ? '∞' : number_format( (int) $rate['remaining'] );
    echo '<div class="fh-sb-card">';
    echo '<h3 class="fh-sb-title">' . esc_html__( 'Email quota', 'red-headed-pro' ) . '</h3>';
    echo '<div class="pl-quota-bar"><div class="pl-quota-fill" style="width:' . (int) min( 100, ( $rate['sent_24h'] / max( 1, $rate['limit'] ) ) * 100 ) . '%;"></div></div>';
    echo '<p class="pl-quota-text">' . sprintf(
        /* translators: 1: sent, 2: limit or ∞, 3: remaining or ∞ */
        esc_html__( '%1$d / %2$s emails sent (24h sliding) — %3$s remaining. Pro = unlimited.', 'red-headed-pro' ),
        (int) $rate['sent_24h'], esc_html( $q_limit ), esc_html( $q_remaining )
    ) . '</p>';
    echo '</div>';
}

FH_UI_Helper::close_cockpit_sidebar();
FH_UI_Helper::close_cockpit();
?>
