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
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-preview-profile" data-id="<?php echo (int) $p['id']; ?>" title="<?php esc_attr_e( 'Preview which orders match this profile', 'pelican' ); ?>">👁</button>
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
                    <legend class="pl-field-lbl">🔎 <?php esc_html_e( 'Filters', 'pelican' ); ?></legend>
                    <p class="pl-muted"><?php esc_html_e( 'Restrict which orders are exported by this profile.', 'pelican' ); ?></p>

                    <label class="pl-field-stack">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'Order statuses', 'pelican' ); ?></span>
                        <span class="pl-status-grid">
                            <?php
                            $wc_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array(
                                'wc-pending'    => 'Pending',
                                'wc-processing' => 'Processing',
                                'wc-on-hold'    => 'On hold',
                                'wc-completed'  => 'Completed',
                                'wc-cancelled'  => 'Cancelled',
                                'wc-refunded'   => 'Refunded',
                                'wc-failed'     => 'Failed',
                            );
                            foreach ( $wc_statuses as $slug => $label ) :
                                $clean = preg_replace( '/^wc-/', '', $slug );
                            ?>
                                <label class="pl-status-chip">
                                    <input type="checkbox" name="pl-pf-status[]" value="<?php echo esc_attr( $clean ); ?>" />
                                    <span><?php echo esc_html( $label ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </span>
                    </label>

                    <div class="pl-grid pl-grid-2">
                        <label class="pl-field-stack">
                            <span class="pl-field-sublabel"><?php esc_html_e( 'Date from', 'pelican' ); ?></span>
                            <input type="date" id="pl-pf-date-from" />
                        </label>
                        <label class="pl-field-stack">
                            <span class="pl-field-sublabel"><?php esc_html_e( 'Date to', 'pelican' ); ?></span>
                            <input type="date" id="pl-pf-date-to" />
                        </label>
                    </div>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">🗂 <?php esc_html_e( 'Columns', 'pelican' ); ?></legend>
                    <p class="pl-muted">
                        <?php esc_html_e( 'Pick the order fields to include in the export, then drag them into the order you want.', 'pelican' ); ?>
                    </p>

                    <!-- v1.4.12 — Active columns full-width + "Browse fields" button opens a centered modal
                                  with the field catalog (multi-column grid, much more readable than
                                  a cramped sidebar). Per `feedback_field_picker_modal_pattern.md`. -->
                    <div class="pl-cols-builder pl-cols-builder--single">
                        <div class="pl-cols-pane pl-cols-pane-selected">
                            <div class="pl-cols-pane-head">
                                <strong><?php esc_html_e( 'Active columns', 'pelican' ); ?></strong>
                                <span class="pl-cols-count" id="pl-cols-count">0</span>
                                <button type="button" class="pl-btn pl-btn-primary pl-btn-sm" id="pl-cols-open-picker">+ <?php esc_html_e( 'Browse fields', 'pelican' ); ?></button>
                                <button type="button" class="pl-btn pl-btn-sm pl-cols-defaults" id="pl-cols-defaults"><?php esc_html_e( 'Use defaults', 'pelican' ); ?></button>
                                <button type="button" class="pl-btn pl-btn-sm pl-cols-clear" id="pl-cols-clear"><?php esc_html_e( 'Clear', 'pelican' ); ?></button>
                            </div>
                            <ol class="pl-cols-active" id="pl-cols-active">
                                <li class="pl-cols-empty pl-muted"><?php esc_html_e( 'No columns yet. Click "Browse fields" to add them.', 'pelican' ); ?></li>
                            </ol>
                        </div>
                    </div>

                    <!-- Modal overlay (hidden by default) -->
                    <div class="pl-modal-overlay" id="pl-cols-modal" aria-hidden="true">
                        <div class="pl-modal" role="dialog" aria-modal="true" aria-labelledby="pl-cols-modal-title">
                            <div class="pl-modal-head">
                                <h3 id="pl-cols-modal-title">🗂 <?php esc_html_e( 'Browse export fields', 'pelican' ); ?></h3>
                                <input type="search" id="pl-cols-search" placeholder="<?php esc_attr_e( 'Search…', 'pelican' ); ?>" />
                                <button type="button" class="pl-modal-close" id="pl-cols-modal-close" aria-label="<?php esc_attr_e( 'Close', 'pelican' ); ?>">×</button>
                            </div>
                            <div class="pl-modal-body">
                                <div class="pl-cols-catalog" id="pl-cols-catalog">
                                    <?php
                                    $catalog = Pelican_Export_Engine::column_catalog();
                                    $groups  = Pelican_Export_Engine::column_groups();
                                    $by_group = array();
                                    foreach ( $catalog as $key => $meta ) {
                                        $g = $meta['group'] ?? 'order';
                                        $by_group[ $g ][ $key ] = $meta;
                                    }
                                    foreach ( $groups as $g_key => $g_label ) :
                                        if ( empty( $by_group[ $g_key ] ) && $g_key !== 'meta' ) continue;
                                    ?>
                                        <div class="pl-cols-group" data-group="<?php echo esc_attr( $g_key ); ?>">
                                            <div class="pl-cols-group-title"><?php echo esc_html( $g_label ); ?></div>
                                            <?php if ( ! empty( $by_group[ $g_key ] ) ) : foreach ( $by_group[ $g_key ] as $key => $meta ) : ?>
                                                <label class="pl-col-row" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( $meta['label'] ); ?>">
                                                    <input type="checkbox" class="pl-col-toggle" />
                                                    <span class="pl-col-label"><?php echo esc_html( $meta['label'] ); ?></span>
                                                    <code class="pl-col-key"><?php echo esc_html( $key ); ?></code>
                                                    <?php if ( ! empty( $meta['hint'] ) ) : ?><span class="pl-col-hint" title="<?php echo esc_attr( $meta['hint'] ); ?>">ⓘ</span><?php endif; ?>
                                                </label>
                                            <?php endforeach; endif; ?>
                                            <?php if ( $g_key === 'meta' ) : ?>
                                                <div class="pl-meta-add">
                                                    <input type="text" id="pl-meta-key" placeholder="<?php esc_attr_e( 'meta_key (e.g. _vat_number)', 'pelican' ); ?>" />
                                                    <input type="text" id="pl-meta-label" placeholder="<?php esc_attr_e( 'header label', 'pelican' ); ?>" />
                                                    <button type="button" class="pl-btn pl-btn-sm" id="pl-meta-add-btn">+ <?php esc_html_e( 'Add meta column', 'pelican' ); ?></button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="pl-modal-foot">
                                <button type="button" class="pl-btn pl-btn-primary" id="pl-cols-modal-done"><?php esc_html_e( 'Done', 'pelican' ); ?></button>
                            </div>
                        </div>
                    </div>
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
