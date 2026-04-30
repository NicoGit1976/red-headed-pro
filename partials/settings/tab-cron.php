<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Settings → Cron tab. Pro only.
 *
 * @package Pelican
 */
?>
<div class="pl-pane">
    <h3 class="pl-h3">⏰ <?php esc_html_e( 'Cron schedules', 'pelican' ); ?></h3>

    <?php if ( ! Pelican_Soft_Lock::is_pro() ) : ?>
        <div class="pl-locked-pane">
            <div class="pl-locked-icon">🔒</div>
            <h4><?php esc_html_e( 'Cron schedules — Pro feature', 'pelican' ); ?></h4>
            <p class="pl-muted"><?php esc_html_e( 'Schedule any export profile to run hourly, twice daily, daily, weekly, or with a custom WP-Cron interval. Combine with auto-trigger to fire on order status changes.', 'pelican' ); ?></p>
            <ul class="pl-list-check">
                <li>✓ <?php esc_html_e( 'Hourly / twice-daily / daily / weekly presets', 'pelican' ); ?></li>
                <li>✓ <?php esc_html_e( 'Custom interval (n minutes)', 'pelican' ); ?></li>
                <li>✓ <?php esc_html_e( 'Order status auto-trigger (processing → SFTP, completed → email…)', 'pelican' ); ?></li>
                <li>✓ <?php esc_html_e( 'Per-profile fire-once dedupe', 'pelican' ); ?></li>
            </ul>
            <a href="https://thelionfrog.com/products/plugins/woo-order-pro" target="_blank" rel="noopener" class="pl-btn pl-btn-upgrade">⚡ <?php esc_html_e( 'Upgrade to Pro', 'pelican' ); ?></a>
        </div>
    <?php else :
        $profiles = Pelican_Profile_Repo::all();
        $next_tick = wp_next_scheduled( 'pelican_cron_tick' );
    ?>
        <p class="pl-muted">
            <?php
            printf(
                /* translators: %s = next tick datetime */
                esc_html__( 'Master tick: every hour — next run at %s.', 'pelican' ),
                $next_tick ? esc_html( wp_date( 'Y-m-d H:i', $next_tick ) ) : esc_html__( 'not scheduled', 'pelican' )
            );
            ?>
        </p>

        <table class="pl-table pl-table-zebra">
            <thead><tr>
                <th><?php esc_html_e( 'Profile', 'pelican' ); ?></th>
                <th><?php esc_html_e( 'Schedule', 'pelican' ); ?></th>
                <th><?php esc_html_e( 'Last run', 'pelican' ); ?></th>
                <th><?php esc_html_e( 'Status', 'pelican' ); ?></th>
            </tr></thead>
            <tbody>
                <?php
                global $wpdb;
                $jobs_tbl = $wpdb->prefix . 'pl_jobs';
                foreach ( $profiles as $p ) :
                    $last = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jobs_tbl} WHERE profile_id = %d ORDER BY started_at DESC LIMIT 1", (int) $p['id'] ), ARRAY_A );
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
                        <td><span class="pl-pill"><?php echo esc_html( $p['schedule'] ?: 'manual' ); ?></span></td>
                        <td class="pl-muted"><?php echo $last ? esc_html( $last['started_at'] ) : '—'; ?></td>
                        <td><span class="pl-status pl-status-<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( $p['status'] ); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="pl-muted">💡 <?php esc_html_e( 'Edit a profile to change its schedule or auto-trigger.', 'pelican' ); ?></p>
    <?php endif; ?>
</div>
