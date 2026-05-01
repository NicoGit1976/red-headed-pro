<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Dashboard — Harlequin (Lion Frog DNA).
 *
 * @package Pelican
 */
global $wpdb;
$jobs_tbl = $wpdb->prefix . 'pl_jobs';
$stats = array(
    'total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_tbl}" ),
    'this_month' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_tbl} WHERE started_at >= DATE_FORMAT(NOW(), '%Y-%m-01')" ),
    'success'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_tbl} WHERE status = 'success'" ),
    'failed'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_tbl} WHERE status = 'failed'" ),
);
$by_format = $wpdb->get_results( "SELECT format, COUNT(*) AS n FROM {$jobs_tbl} GROUP BY format", ARRAY_A );
$recent    = $wpdb->get_results( "SELECT id, format, status, records_count, file_size, started_at, trigger_source FROM {$jobs_tbl} ORDER BY started_at DESC LIMIT 8", ARRAY_A ) ?: array();
$is_pro    = Pelican_Soft_Lock::is_pro();
$rate      = Pelican_Destination_Email::rate_status();
$profiles_n = count( Pelican_Profile_Repo::all() );

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
<div class="pl-wrap wrap">
    <?php include PELICAN_PATH . 'partials/_page-nav.php'; ?>

    <section class="pl-hero">
        <div class="pl-hero-bg"></div>
        <div class="pl-hero-inner">
            <div class="pl-hero-brand">
                <img src="<?php echo esc_url( PELICAN_URL . 'assets/img/mascot-harlequin-v1.svg' ); ?>" alt="Red-Headed frog" class="pl-hero-mascot-svg" width="64" height="64" />
                <div>
                    <h1 class="pl-hero-title">Red-Headed <span class="pl-hero-edition"><?php echo $is_pro ? 'Pro' : 'Lite'; ?></span></h1>
                    <p class="pl-hero-baseline"><?php esc_html_e( 'Exports Orders Everywhere, Anytime', 'pelican' ); ?></p>
                    <p class="pl-hero-tag">
                        <?php echo $is_pro
                            ? '<span class="pl-edition pl-edition-pro">PRO</span> ' . esc_html__( 'Bulk + auto exports · 6 formats · 6 destinations · cron + status triggers.', 'pelican' )
                            : '<span class="pl-edition pl-edition-lite">LITE</span> ' . esc_html__( 'Manual + bulk to CSV via Email or SFTP. Pro features visible & locked.', 'pelican' ); ?>
                    </p>
                </div>
            </div>
            <?php if ( ! $is_pro ) : ?>
                <a href="https://thelionfrog.com/products/plugins/woo-order-pro" target="_blank" rel="noopener" class="pl-btn pl-btn-upgrade">⚡ <?php esc_html_e( 'Upgrade to Pro', 'pelican' ); ?></a>
            <?php endif; ?>
        </div>
    </section>

    <section class="pl-kpis">
        <div class="pl-kpi"><div class="pl-kpi-icon">📊</div><div><div class="pl-kpi-num"><?php echo (int) $stats['total']; ?></div><div class="pl-kpi-lbl"><?php esc_html_e( 'Total exports', 'pelican' ); ?></div></div></div>
        <div class="pl-kpi"><div class="pl-kpi-icon">📅</div><div><div class="pl-kpi-num"><?php echo (int) $stats['this_month']; ?></div><div class="pl-kpi-lbl"><?php esc_html_e( 'This month', 'pelican' ); ?></div></div></div>
        <div class="pl-kpi"><div class="pl-kpi-icon">✓</div><div><div class="pl-kpi-num"><?php echo (int) $stats['success']; ?></div><div class="pl-kpi-lbl"><?php esc_html_e( 'Successful', 'pelican' ); ?></div></div></div>
        <div class="pl-kpi <?php echo $stats['failed'] > 0 ? 'pl-kpi-warn' : ''; ?>"><div class="pl-kpi-icon">⚠️</div><div><div class="pl-kpi-num"><?php echo (int) $stats['failed']; ?></div><div class="pl-kpi-lbl"><?php esc_html_e( 'Failed', 'pelican' ); ?></div></div></div>
    </section>

    <section class="pl-section">
        <h2 class="pl-h2"><?php esc_html_e( '🗂️ Available formats', 'pelican' ); ?></h2>
        <div class="pl-formats">
            <?php foreach ( $formats as $slug => $meta ) :
                $locked = $meta['lock'] && Pelican_Soft_Lock::is_locked( $meta['lock'] );
                $count  = $by_format_map[ $slug ] ?? 0;
            ?>
                <div class="pl-format <?php echo $locked ? 'pl-format-locked' : ''; ?>">
                    <div class="pl-format-icon"><?php echo $meta['icon']; ?></div>
                    <div class="pl-format-label">
                        <?php echo esc_html( $meta['label'] ); ?>
                        <?php if ( $locked ) echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?>
                    </div>
                    <div class="pl-format-count"><?php echo (int) $count; ?> <?php esc_html_e( 'exports', 'pelican' ); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="pl-section">
        <div class="pl-section-head">
            <h2 class="pl-h2"><?php esc_html_e( '🕒 Recent exports', 'pelican' ); ?></h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=pelican-exports' ) ); ?>" class="pl-link"><?php esc_html_e( 'See all →', 'pelican' ); ?></a>
        </div>
        <?php if ( empty( $recent ) ) : ?>
            <div class="pl-empty">
                <div class="pl-empty-icon">🃏</div>
                <p><?php esc_html_e( 'No exports yet. Go to Settings → Profiles to create one, or use "🃏 Export with Red-Headed" as a bulk action on the WC orders list.', 'pelican' ); ?></p>
            </div>
        <?php else : ?>
            <table class="pl-table">
                <thead><tr>
                    <th>#</th>
                    <th><?php esc_html_e( 'Format', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Records', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Size', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Trigger', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'When', 'pelican' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'pelican' ); ?></th>
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

    <section class="pl-section">
        <h2 class="pl-h2"><?php esc_html_e( '⚡ Quick actions', 'pelican' ); ?></h2>
        <div class="pl-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=pelican-settings-profiles' ) ); ?>" class="pl-action">
                <span class="pl-action-icon">📁</span>
                <span class="pl-action-label"><?php esc_html_e( 'Profiles', 'pelican' ); ?></span>
                <span class="pl-action-meta"><?php echo (int) $profiles_n; ?> / <?php echo $is_pro ? '∞' : '1'; ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=pelican-exports' ) ); ?>" class="pl-action">
                <span class="pl-action-icon">📦</span>
                <span class="pl-action-label"><?php esc_html_e( 'Exports', 'pelican' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="pl-action">
                <span class="pl-action-icon">🛒</span>
                <span class="pl-action-label"><?php esc_html_e( 'WC Orders', 'pelican' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=pelican-settings-destinations' ) ); ?>" class="pl-action">
                <span class="pl-action-icon">📡</span>
                <span class="pl-action-label"><?php esc_html_e( 'Destinations', 'pelican' ); ?></span>
            </a>
            <?php if ( $is_pro ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pelican-settings-cron' ) ); ?>" class="pl-action">
                    <span class="pl-action-icon">⏰</span>
                    <span class="pl-action-label"><?php esc_html_e( 'Cron schedules', 'pelican' ); ?></span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pelican-settings-webhooks' ) ); ?>" class="pl-action">
                    <span class="pl-action-icon">🔔</span>
                    <span class="pl-action-label"><?php esc_html_e( 'Webhooks', 'pelican' ); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ( ! $is_pro ) : ?>
    <section class="pl-section">
        <h2 class="pl-h2"><?php esc_html_e( '✉️ Email quota', 'pelican' ); ?></h2>
        <div class="pl-quota-bar"><div class="pl-quota-fill" style="width: <?php echo (int) min( 100, ( $rate['sent_24h'] / max( 1, $rate['limit'] ) ) * 100 ); ?>%;"></div></div>
        <p class="pl-quota-text">
            <?php
            printf(
                /* translators: 1: sent, 2: limit, 3: remaining */
                esc_html__( '%1$d / %2$d emails sent (24h sliding) — %3$d remaining. Pro = unlimited.', 'pelican' ),
                (int) $rate['sent_24h'], (int) $rate['limit'], (int) $rate['remaining']
            );
            ?>
        </p>
    </section>
    <?php endif; ?>
</div>
