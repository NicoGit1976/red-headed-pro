<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Settings → Profiles tab. Lite is capped to 1 profile (Soft Lock).
 *
 * @package Pelican
 */
$profiles = Pelican_Profile_Repo::all();
$is_pro   = Pelican_Soft_Lock::is_pro();
$cap_hit  = ! $is_pro && count( $profiles ) >= 1;
?>
<div class="pl-pane">
    <div class="pl-pane-head">
        <div>
            <h3 class="pl-h3"><?php esc_html_e( 'Export profiles', 'pelican' ); ?></h3>
            <p class="pl-muted"><?php esc_html_e( 'A profile = format + filters + columns + destinations (+ cron/auto in Pro).', 'pelican' ); ?></p>
        </div>
        <div>
            <span class="pl-muted"><?php printf( esc_html__( '%1$d / %2$s profiles', 'pelican' ), count( $profiles ), $is_pro ? '∞' : '1' ); ?></span>
            <button type="button" id="pl-add-profile" class="pl-btn pl-btn-primary" <?php disabled( $cap_hit ); ?>>+ <?php esc_html_e( 'New profile', 'pelican' ); ?></button>
        </div>
    </div>

    <?php if ( $cap_hit ) : ?>
        <div class="pl-notice pl-notice-info">
            <span><?php esc_html_e( '🃏 Lite is capped to 1 profile. Upgrade to Pro for unlimited profiles, cron schedules and auto-triggers.', 'pelican' ); ?></span>
            <a href="https://thelionfrog.com/products/plugins/woo-order-pro" target="_blank" rel="noopener" class="pl-btn pl-btn-upgrade">⚡ <?php esc_html_e( 'Upgrade to Pro', 'pelican' ); ?></a>
        </div>
    <?php endif; ?>

    <?php if ( empty( $profiles ) ) : ?>
        <div class="pl-empty">
            <div class="pl-empty-icon">📁</div>
            <p><?php esc_html_e( 'No profile yet. Create one to start exporting WooCommerce orders.', 'pelican' ); ?></p>
        </div>
    <?php else : ?>
        <table class="pl-table pl-table-zebra">
            <thead><tr>
                <th><?php esc_html_e( 'Name', 'pelican' ); ?></th>
                <th><?php esc_html_e( 'Format', 'pelican' ); ?></th>
                <th><?php esc_html_e( 'Destinations', 'pelican' ); ?></th>
                <th><?php esc_html_e( 'Schedule', 'pelican' ); ?></th>
                <th><?php esc_html_e( 'Status', 'pelican' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'pelican' ); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ( $profiles as $p ) :
                    $dests = array_map( function ( $d ) { return $d['type'] ?? '?'; }, (array) $p['destinations'] );
                ?>
                    <tr data-profile-id="<?php echo (int) $p['id']; ?>">
                        <td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
                        <td><span class="pl-pill"><?php echo esc_html( strtoupper( $p['format'] ) ); ?></span></td>
                        <td><?php echo esc_html( implode( ', ', $dests ) ?: '—' ); ?></td>
                        <td><?php echo esc_html( $p['schedule'] ?: 'manual' ); ?></td>
                        <td><span class="pl-status pl-status-<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( $p['status'] ); ?></span></td>
                        <td>
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-run" data-id="<?php echo (int) $p['id']; ?>">▶ <?php esc_html_e( 'Run', 'pelican' ); ?></button>
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-edit" data-id="<?php echo (int) $p['id']; ?>">✎</button>
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-danger pl-btn-del" data-id="<?php echo (int) $p['id']; ?>">×</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Profile editor (drawer) — JS will populate fields -->
    <div id="pl-profile-editor" class="pl-drawer" hidden>
        <div class="pl-drawer-inner">
            <header class="pl-drawer-head">
                <h3 class="pl-h3" id="pl-editor-title"><?php esc_html_e( 'New profile', 'pelican' ); ?></h3>
                <button type="button" class="pl-btn pl-btn-sm" id="pl-editor-close">×</button>
            </header>

            <div class="pl-drawer-body">
                <input type="hidden" id="pl-pf-id" value="" />
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Name', 'pelican' ); ?></span>
                    <input type="text" id="pl-pf-name" placeholder="<?php esc_attr_e( 'e.g. Daily orders → SFTP', 'pelican' ); ?>" />
                </label>

                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Format', 'pelican' ); ?></span>
                    <select id="pl-pf-format">
                        <?php
                        $fmts = array(
                            'csv'    => 'CSV',
                            'tsv'    => 'TSV' . ( Pelican_Soft_Lock::is_locked( 'format_tsv' )    ? ' 🔒' : '' ),
                            'json'   => 'JSON' . ( Pelican_Soft_Lock::is_locked( 'format_json' )   ? ' 🔒' : '' ),
                            'ndjson' => 'NDJSON' . ( Pelican_Soft_Lock::is_locked( 'format_ndjson' ) ? ' 🔒' : '' ),
                            'xml'    => 'XML' . ( Pelican_Soft_Lock::is_locked( 'format_xml' )    ? ' 🔒' : '' ),
                            'xlsx'   => 'XLSX' . ( Pelican_Soft_Lock::is_locked( 'format_xlsx' )   ? ' 🔒' : '' ),
                        );
                        foreach ( $fmts as $k => $lbl ) echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</option>';
                        ?>
                    </select>
                </label>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl"><?php esc_html_e( 'Filters', 'pelican' ); ?></legend>
                    <label><span><?php esc_html_e( 'Statuses (comma-separated WC slugs)', 'pelican' ); ?></span>
                        <input type="text" id="pl-pf-statuses" placeholder="processing, completed" />
                    </label>
                    <label><span><?php esc_html_e( 'Date from', 'pelican' ); ?></span>
                        <input type="date" id="pl-pf-date-from" />
                    </label>
                    <label><span><?php esc_html_e( 'Date to', 'pelican' ); ?></span>
                        <input type="date" id="pl-pf-date-to" />
                    </label>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl"><?php esc_html_e( 'Columns', 'pelican' ); ?></legend>
                    <p class="pl-muted"><?php esc_html_e( 'Comma-separated. Default = the 15 standard columns.', 'pelican' ); ?></p>
                    <input type="text" id="pl-pf-columns" placeholder="<?php echo esc_attr( implode( ', ', Pelican_Export_Engine::default_columns() ) ); ?>" />
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl"><?php esc_html_e( 'Destinations', 'pelican' ); ?></legend>
                    <p class="pl-muted"><?php esc_html_e( 'Add one or many destinations. Lite is capped to 1.', 'pelican' ); ?></p>
                    <div id="pl-pf-destinations"></div>
                    <button type="button" class="pl-btn pl-btn-sm" id="pl-pf-add-dest">+ <?php esc_html_e( 'Add destination', 'pelican' ); ?></button>
                </fieldset>

                <?php if ( $is_pro ) : ?>
                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">⏰ <?php esc_html_e( 'Schedule', 'pelican' ); ?></legend>
                    <select id="pl-pf-schedule">
                        <option value="manual"><?php esc_html_e( 'Manual only', 'pelican' ); ?></option>
                        <option value="hourly"><?php esc_html_e( 'Hourly', 'pelican' ); ?></option>
                        <option value="twicedaily"><?php esc_html_e( 'Twice daily', 'pelican' ); ?></option>
                        <option value="daily"><?php esc_html_e( 'Daily', 'pelican' ); ?></option>
                        <option value="weekly"><?php esc_html_e( 'Weekly', 'pelican' ); ?></option>
                    </select>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">⚡ <?php esc_html_e( 'Auto-trigger', 'pelican' ); ?></legend>
                    <label><span><?php esc_html_e( 'On status change to (comma-separated)', 'pelican' ); ?></span>
                        <input type="text" id="pl-pf-auto-status" placeholder="completed" />
                    </label>
                    <label><span><?php esc_html_e( 'Min total (€)', 'pelican' ); ?></span>
                        <input type="number" step="0.01" id="pl-pf-auto-mintotal" />
                    </label>
                    <label class="pl-checkbox">
                        <input type="checkbox" id="pl-pf-auto-fireonce" />
                        <span><?php esc_html_e( 'Fire only once per order', 'pelican' ); ?></span>
                    </label>
                </fieldset>
                <?php endif; ?>
            </div>

            <footer class="pl-drawer-foot">
                <button type="button" class="pl-btn" id="pl-editor-cancel"><?php esc_html_e( 'Cancel', 'pelican' ); ?></button>
                <button type="button" class="pl-btn pl-btn-primary" id="pl-editor-save"><?php esc_html_e( 'Save profile', 'pelican' ); ?></button>
            </footer>
        </div>
    </div>
</div>
