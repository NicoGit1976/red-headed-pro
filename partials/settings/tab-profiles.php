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
            <h3 class="pl-h3"><?php esc_html_e( 'Export profiles', 'red-headed-pro' ); ?></h3>
            <p class="pl-muted"><?php esc_html_e( 'A profile = format + filters + columns + destinations (+ cron/auto in Pro).', 'red-headed-pro' ); ?></p>
        </div>
        <div>
            <span class="pl-muted"><?php printf( esc_html__( '%1$d / %2$s profiles', 'red-headed-pro' ), count( $profiles ), $is_pro ? '∞' : '1' ); ?></span>
            <button type="button" id="pl-import-profile" class="pl-btn" title="<?php esc_attr_e( 'Import a profile from a JSON file', 'red-headed-pro' ); ?>">⬆ <?php esc_html_e( 'Import', 'red-headed-pro' ); ?></button>
            <button type="button" id="pl-add-profile" class="pl-btn pl-btn-primary" aria-label="<?php esc_attr_e( 'Create a new export profile', 'red-headed-pro' ); ?>" <?php disabled( $cap_hit ); ?>>+ <?php esc_html_e( 'New profile', 'red-headed-pro' ); ?></button>
        </div>
    </div>

    <?php if ( $cap_hit ) : ?>
        <div class="pl-notice pl-notice-info">
            <span><?php esc_html_e( '🐸 Lite is capped to 1 profile. Upgrade to Pro for unlimited profiles, cron schedules and auto-triggers.', 'red-headed-pro' ); ?></span>
            <a href="https://thelionfrog.com/products/plugins/woo-order-pro" target="_blank" rel="noopener" class="pl-btn pl-btn-upgrade">⚡ <?php esc_html_e( 'Upgrade to Pro', 'red-headed-pro' ); ?></a>
        </div>
    <?php endif; ?>

    <?php if ( empty( $profiles ) ) : ?>
        <div class="pl-empty">
            <div class="pl-empty-icon">📁</div>
            <p><?php esc_html_e( 'No profile yet. Create one to start exporting WooCommerce orders.', 'red-headed-pro' ); ?></p>
        </div>
    <?php else : ?>
        <table class="pl-table pl-table-zebra">
            <thead><tr>
                <th><?php esc_html_e( 'Name', 'red-headed-pro' ); ?></th>
                <th><?php esc_html_e( 'Format', 'red-headed-pro' ); ?></th>
                <th><?php esc_html_e( 'Destinations', 'red-headed-pro' ); ?></th>
                <th><?php esc_html_e( 'Schedule', 'red-headed-pro' ); ?></th>
                <th><?php esc_html_e( 'Status', 'red-headed-pro' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'red-headed-pro' ); ?></th>
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
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-preview-profile" data-id="<?php echo (int) $p['id']; ?>" title="<?php esc_attr_e( 'Preview which orders match this profile', 'red-headed-pro' ); ?>">👁</button>
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-run" data-id="<?php echo (int) $p['id']; ?>">▶ <?php esc_html_e( 'Run', 'red-headed-pro' ); ?></button>
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-dry-run" data-id="<?php echo (int) $p['id']; ?>" title="<?php esc_attr_e( 'Build file without delivering', 'red-headed-pro' ); ?>">🧪</button>
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-export-json" data-id="<?php echo (int) $p['id']; ?>" title="<?php esc_attr_e( 'Export profile as JSON', 'red-headed-pro' ); ?>">⬇</button>
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-edit" data-id="<?php echo (int) $p['id']; ?>">✎</button>
                            <button type="button" class="pl-btn pl-btn-sm pl-btn-danger pl-btn-del" data-id="<?php echo (int) $p['id']; ?>">×</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Profile editor (drawer) — JS will populate fields -->
    <div id="pl-profile-editor" class="pl-drawer" role="dialog" aria-modal="true" aria-labelledby="pl-editor-title" hidden>
        <div class="pl-drawer-inner">
            <header class="pl-drawer-head">
                <h3 class="pl-h3" id="pl-editor-title"><?php esc_html_e( 'New profile', 'red-headed-pro' ); ?></h3>
                <button type="button" class="pl-btn pl-btn-sm" id="pl-editor-close" aria-label="<?php esc_attr_e( 'Close', 'red-headed-pro' ); ?>">×</button>
            </header>

            <div class="pl-drawer-body">
                <input type="hidden" id="pl-pf-id" value="" />
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Name', 'red-headed-pro' ); ?></span>
                    <input type="text" id="pl-pf-name" placeholder="<?php esc_attr_e( 'e.g. Daily orders → SFTP', 'red-headed-pro' ); ?>" />
                </label>

                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Format', 'red-headed-pro' ); ?></span>
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
                    <legend class="pl-field-lbl">🔎 <?php esc_html_e( 'Filters', 'red-headed-pro' ); ?></legend>
                    <p class="pl-muted"><?php esc_html_e( 'Restrict which orders are exported by this profile.', 'red-headed-pro' ); ?></p>

                    <label class="pl-field-stack">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'Order statuses', 'red-headed-pro' ); ?></span>
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
                            unset( $wc_statuses['wc-checkout-draft'], $wc_statuses['wc-auto-draft'] );
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
                            <span class="pl-field-sublabel"><?php esc_html_e( 'Date from', 'red-headed-pro' ); ?></span>
                            <input type="date" id="pl-pf-date-from" />
                        </label>
                        <label class="pl-field-stack">
                            <span class="pl-field-sublabel"><?php esc_html_e( 'Date to', 'red-headed-pro' ); ?></span>
                            <input type="date" id="pl-pf-date-to" />
                        </label>
                    </div>

                    <?php
                    /* Advanced filters — Pro feature. In Lite, the whole block is wrapped in
                       Pelican_Soft_Lock::wrap() which dims it and shows a PRO badge overlay. */
                    $adv_render = function () {
                    ?>
                    <details class="pl-field-stack pl-filters-advanced" <?php echo Pelican_Soft_Lock::is_pro() ? '' : 'open'; ?>>
                        <summary class="pl-field-sublabel" style="cursor:pointer;font-weight:600;">⚙️ <?php esc_html_e( 'Advanced filters', 'red-headed-pro' ); ?></summary>
                        <div class="pl-grid pl-grid-2" style="margin-top:10px;">
                            <label class="pl-field-stack" for="pl-pf-sku-pattern">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'SKU contains', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-sku-pattern" placeholder="ACME-" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-category">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Product category (term ID)', 'red-headed-pro' ); ?></span>
                                <input type="number" id="pl-pf-category" min="1" placeholder="42" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-shipping-method">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Shipping method ID', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-shipping-method" placeholder="flat_rate" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-customer-role">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Customer role', 'red-headed-pro' ); ?></span>
                                <select id="pl-pf-customer-role">
                                    <option value=""><?php esc_html_e( '— any —', 'red-headed-pro' ); ?></option>
                                    <?php
                                    if ( function_exists( 'wp_roles' ) ) {
                                        foreach ( wp_roles()->roles as $slug => $info ) {
                                            echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $info['name'] ) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </label>
                            <label class="pl-field-stack" for="pl-pf-customer-email-contains">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Customer email contains', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-customer-email-contains" placeholder="@acme.com" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-coupon">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Coupon used', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-coupon" placeholder="WELCOME10" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-total-min">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Order total min', 'red-headed-pro' ); ?></span>
                                <input type="number" step="0.01" id="pl-pf-total-min" placeholder="0.00" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-total-max">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Order total max', 'red-headed-pro' ); ?></span>
                                <input type="number" step="0.01" id="pl-pf-total-max" placeholder="∞" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-meta-key">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Custom meta key', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-meta-key" placeholder="_vat_number" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-meta-value">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Meta value (= match)', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-meta-value" placeholder="<?php esc_attr_e( 'leave empty = field exists', 'red-headed-pro' ); ?>" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-billing-city">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Billing city', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-billing-city" placeholder="Paris" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-billing-country">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Billing country (2-letter)', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-billing-country" placeholder="FR" maxlength="2" style="text-transform:uppercase;" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-shipping-city">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Shipping city', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-shipping-city" placeholder="Lyon" />
                            </label>
                            <label class="pl-field-stack" for="pl-pf-shipping-country">
                                <span class="pl-field-sublabel"><?php esc_html_e( 'Shipping country (2-letter)', 'red-headed-pro' ); ?></span>
                                <input type="text" id="pl-pf-shipping-country" placeholder="DE" maxlength="2" style="text-transform:uppercase;" />
                            </label>
                        </div>
                    </details>
                    <?php
                    };
                    if ( Pelican_Soft_Lock::is_available( 'filters_advanced' ) ) {
                        $adv_render();
                    } else {
                        Pelican_Soft_Lock::wrap( 'filters_advanced', $adv_render );
                    }
                    ?>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">🗂 <?php esc_html_e( 'Columns', 'red-headed-pro' ); ?></legend>
                    <p class="pl-muted">
                        <?php esc_html_e( 'Pick the order fields to include in the export, then drag them into the order you want.', 'red-headed-pro' ); ?>
                    </p>

                    <!-- v1.4.12 — Active columns full-width + "Browse fields" button opens a centered modal
                                  with the field catalog (multi-column grid, much more readable than
                                  a cramped sidebar). Per `feedback_field_picker_modal_pattern.md`. -->
                    <div class="pl-cols-builder pl-cols-builder--single">
                        <div class="pl-cols-pane pl-cols-pane-selected">
                            <div class="pl-cols-pane-head">
                                <strong><?php esc_html_e( 'Active columns', 'red-headed-pro' ); ?></strong>
                                <span class="pl-cols-count" id="pl-cols-count">0</span>
                                <button type="button" class="pl-btn pl-btn-primary pl-btn-sm" id="pl-cols-open-picker">+ <?php esc_html_e( 'Browse fields', 'red-headed-pro' ); ?></button>
                                <button type="button" class="pl-btn pl-btn-sm pl-cols-defaults" id="pl-cols-defaults"><?php esc_html_e( 'Use defaults', 'red-headed-pro' ); ?></button>
                                <button type="button" class="pl-btn pl-btn-sm pl-cols-clear" id="pl-cols-clear"><?php esc_html_e( 'Clear', 'red-headed-pro' ); ?></button>
                            </div>
                            <ol class="pl-cols-active" id="pl-cols-active">
                                <li class="pl-cols-empty pl-muted"><?php esc_html_e( 'No columns yet. Click "Browse fields" to add them.', 'red-headed-pro' ); ?></li>
                            </ol>
                        </div>
                    </div>

                    <!-- Modal overlay (hidden by default) -->
                    <div class="pl-modal-overlay" id="pl-cols-modal" aria-hidden="true">
                        <div class="pl-modal" role="dialog" aria-modal="true" aria-labelledby="pl-cols-modal-title">
                            <div class="pl-modal-head">
                                <h3 id="pl-cols-modal-title">🗂 <?php esc_html_e( 'Browse export fields', 'red-headed-pro' ); ?></h3>
                                <input type="search" id="pl-cols-search" placeholder="<?php esc_attr_e( 'Search…', 'red-headed-pro' ); ?>" />
                                <button type="button" class="pl-modal-close" id="pl-cols-modal-close" aria-label="<?php esc_attr_e( 'Close', 'red-headed-pro' ); ?>">×</button>
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
                                                    <input type="text" id="pl-meta-key" placeholder="<?php esc_attr_e( 'meta_key (e.g. _vat_number)', 'red-headed-pro' ); ?>" />
                                                    <input type="text" id="pl-meta-label" placeholder="<?php esc_attr_e( 'header label', 'red-headed-pro' ); ?>" />
                                                    <button type="button" class="pl-btn pl-btn-sm" id="pl-meta-add-btn">+ <?php esc_html_e( 'Add meta column', 'red-headed-pro' ); ?></button>
                                                </div>
                                                <div class="pl-meta-add" style="margin-top:14px;border-top:1px solid var(--pl-border);padding-top:12px;">
                                                    <strong style="display:block;margin-bottom:6px;">🔍 <?php esc_html_e( 'Discover meta keys', 'red-headed-pro' ); ?></strong>
                                                    <p class="pl-muted" style="margin:0 0 8px;font-size:11px;"><?php esc_html_e( 'Scan your order meta to find available keys. Click a key to add it as a column.', 'red-headed-pro' ); ?></p>
                                                    <button type="button" class="pl-btn pl-btn-sm" id="pl-meta-discover-btn">🔍 <?php esc_html_e( 'Scan order meta', 'red-headed-pro' ); ?></button>
                                                    <ul id="pl-meta-discovered" style="max-height:200px;overflow-y:auto;margin:8px 0 0;padding:0;list-style:none;"></ul>
                                                </div>
                                                <div class="pl-meta-add" style="margin-top:14px;border-top:1px solid var(--pl-border);padding-top:12px;">
                                                    <strong style="display:block;margin-bottom:6px;">📌 <?php esc_html_e( 'Static field', 'red-headed-pro' ); ?> <?php echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?></strong>
                                                    <input type="text" id="pl-static-key"   placeholder="<?php esc_attr_e( 'key (e.g. vendor)', 'red-headed-pro' ); ?>" />
                                                    <input type="text" id="pl-static-label" placeholder="<?php esc_attr_e( 'header label', 'red-headed-pro' ); ?>" />
                                                    <input type="text" id="pl-static-value" placeholder="<?php esc_attr_e( 'value (constant on every row)', 'red-headed-pro' ); ?>" />
                                                    <button type="button" class="pl-btn pl-btn-sm" id="pl-static-add-btn" <?php disabled( ! Pelican_Soft_Lock::is_available( 'computed_columns' ) ); ?>>+ <?php esc_html_e( 'Add static field', 'red-headed-pro' ); ?></button>
                                                </div>
                                                <div class="pl-meta-add" style="margin-top:14px;border-top:1px solid var(--pl-border);padding-top:12px;">
                                                    <strong style="display:block;margin-bottom:6px;">🧮 <?php esc_html_e( 'Calculated field', 'red-headed-pro' ); ?> <?php echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?></strong>
                                                    <input type="text" id="pl-calc-key"   placeholder="<?php esc_attr_e( 'key (e.g. vat_amount)', 'red-headed-pro' ); ?>" />
                                                    <input type="text" id="pl-calc-label" placeholder="<?php esc_attr_e( 'header label', 'red-headed-pro' ); ?>" />
                                                    <input type="text" id="pl-calc-expr"  placeholder="{total} * 0.20" />
                                                    <button type="button" class="pl-btn pl-btn-sm" id="pl-calc-add-btn" <?php disabled( ! Pelican_Soft_Lock::is_available( 'computed_columns' ) ); ?>>+ <?php esc_html_e( 'Add calculated field', 'red-headed-pro' ); ?></button>
                                                    <p class="pl-muted" style="margin:6px 0 0;font-size:11px;line-height:1.45;">
                                                        <?php esc_html_e( 'Allowed: + - * / parentheses + numeric placeholders {total} {subtotal} {tax_total} {shipping_total} {discount_total}.', 'red-headed-pro' ); ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="pl-modal-foot">
                                <button type="button" class="pl-btn pl-btn-primary" id="pl-cols-modal-done"><?php esc_html_e( 'Done', 'red-headed-pro' ); ?></button>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl"><?php esc_html_e( 'Destinations', 'red-headed-pro' ); ?></legend>
                    <p class="pl-muted"><?php esc_html_e( 'Add one or many destinations. Lite is capped to 1.', 'red-headed-pro' ); ?></p>
                    <div id="pl-pf-destinations"></div>
                    <button type="button" class="pl-btn pl-btn-sm" id="pl-pf-add-dest">+ <?php esc_html_e( 'Add destination', 'red-headed-pro' ); ?></button>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">🔁 <?php esc_html_e( 'Retry on failure', 'red-headed-pro' ); ?> <?php echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?></legend>
                    <p class="pl-muted"><?php esc_html_e( 'If a destination is unreachable (e.g. the receiving ERP/SFTP server is down), re-attempt delivery automatically on each cron tick — with backoff — until it goes through. The export file is re-sent as-is (same filename). Relies on WP-cron / a server cron running.', 'red-headed-pro' ); ?></p>
                    <label class="pl-checkbox" style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" id="pl-pf-retry-on-fail" <?php disabled( ! Pelican_Soft_Lock::is_available( 'cron' ) ); ?> />
                        <span><?php esc_html_e( 'Re-deliver failed exports until received', 'red-headed-pro' ); ?></span>
                    </label>
                    <label class="pl-field-stack">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'Max attempts (0 = keep trying until success)', 'red-headed-pro' ); ?></span>
                        <input type="number" id="pl-pf-retry-max" min="0" step="1" placeholder="0" <?php disabled( ! Pelican_Soft_Lock::is_available( 'cron' ) ); ?> />
                    </label>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">📤 <?php esc_html_e( 'Export mode', 'red-headed-pro' ); ?> <?php echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?></legend>
                    <label class="pl-field-stack">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'Row layout', 'red-headed-pro' ); ?></span>
                        <select id="pl-pf-export-mode" <?php disabled( ! Pelican_Soft_Lock::is_available( 'line_item_export' ) ); ?>>
                            <option value="per_order"><?php esc_html_e( 'One row per order', 'red-headed-pro' ); ?></option>
                            <option value="per_line_item"><?php esc_html_e( 'One row per line item (product)', 'red-headed-pro' ); ?></option>
                        </select>
                    </label>
                    <div class="pl-field-stack" id="pl-pf-line-item-fill-wrap" style="display:none;">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'Fill order columns…', 'red-headed-pro' ); ?></span>
                        <label class="pl-checkbox" style="display:inline-flex;margin-right:14px;"><input type="radio" name="pl-pf-line-item-fill" value="every" checked /> <span><?php esc_html_e( 'on every line', 'red-headed-pro' ); ?></span></label>
                        <label class="pl-checkbox" style="display:inline-flex;"><input type="radio" name="pl-pf-line-item-fill" value="first_only" /> <span><?php esc_html_e( 'first line only', 'red-headed-pro' ); ?></span></label>
                    </div>
                </fieldset>

                <fieldset class="pl-field" id="pl-pf-json-fieldset">
                    <legend class="pl-field-lbl">🧱 <?php esc_html_e( 'JSON structure', 'red-headed-pro' ); ?> <?php echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?></legend>
                    <p class="pl-muted"><?php esc_html_e( 'Only applies to JSON / NDJSON formats. Shape the output to match a downstream schema (ERP, partner API…).', 'red-headed-pro' ); ?></p>
                    <label class="pl-field-stack">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'Shape', 'red-headed-pro' ); ?></span>
                        <select id="pl-pf-json-shape" <?php disabled( ! Pelican_Soft_Lock::is_available( 'json_structure' ) ); ?>>
                            <option value=""><?php esc_html_e( 'Flat rows (internal keys)', 'red-headed-pro' ); ?></option>
                            <option value="labeled"><?php esc_html_e( 'One object per order (column labels as keys)', 'red-headed-pro' ); ?></option>
                            <option value="nested"><?php esc_html_e( 'Labeled + nested line items', 'red-headed-pro' ); ?></option>
                        </select>
                    </label>
                    <label class="pl-field-stack" id="pl-pf-line-items-key-wrap" style="display:none;">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'Line-items key (nested shape)', 'red-headed-pro' ); ?></span>
                        <input type="text" id="pl-pf-line-items-key" placeholder="items" />
                        <small class="pl-muted"><?php esc_html_e( 'The JSON key that holds each order’s product lines — e.g. items, lines, products.', 'red-headed-pro' ); ?></small>
                    </label>
                    <label class="pl-checkbox" style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                        <input type="checkbox" id="pl-pf-json-bare" />
                        <span><?php esc_html_e( 'Bare array — no { meta, orders } wrapper', 'red-headed-pro' ); ?></span>
                    </label>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">📄 <?php esc_html_e( 'Output file', 'red-headed-pro' ); ?> <?php echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?></legend>
                    <label class="pl-field-stack">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'Filename pattern', 'red-headed-pro' ); ?></span>
                        <input type="text" id="pl-pf-filename-pattern" placeholder="export-{order_number}-{date}" <?php disabled( ! Pelican_Soft_Lock::is_available( 'filename_pattern' ) ); ?> />
                        <small class="pl-muted"><?php esc_html_e( 'Placeholders: {order_id} {order_number} {order_datetime} {order_date} {date_eu} {datetime_eu} {date} {time} {date:FORMAT} {customer_id} {customer_email} {customer_name} {records} {job_id} {random} {random:N} {digits} {digits:N} {profile} {format}. Extension auto-appended. Empty = global default.', 'red-headed-pro' ); ?></small>
                    </label>
                    <label class="pl-checkbox" style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                        <input type="checkbox" id="pl-pf-split-per-order" <?php disabled( ! Pelican_Soft_Lock::is_available( 'split_per_order' ) ); ?> />
                        <span><?php esc_html_e( 'One file per order (split a batch into individual files)', 'red-headed-pro' ); ?></span>
                    </label>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">🔄 <?php esc_html_e( 'Post-export action', 'red-headed-pro' ); ?> <?php echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?></legend>
                    <label class="pl-field-stack">
                        <span class="pl-field-sublabel"><?php esc_html_e( 'After a successful export, set order status to', 'red-headed-pro' ); ?></span>
                        <select id="pl-pf-post-export-status" <?php disabled( ! Pelican_Soft_Lock::is_available( 'post_export_status' ) ); ?>>
                            <option value=""><?php esc_html_e( '— do nothing —', 'red-headed-pro' ); ?></option>
                            <?php
                            $wc_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
                            unset( $wc_statuses['wc-checkout-draft'], $wc_statuses['wc-auto-draft'] );
                            foreach ( $wc_statuses as $slug => $label ) {
                                $clean = preg_replace( '/^wc-/', '', $slug );
                                echo '<option value="' . esc_attr( $clean ) . '">' . esc_html( $label ) . '</option>';
                            }
                            ?>
                        </select>
                        <small class="pl-muted"><?php esc_html_e( 'Useful to mark exported orders so they are skipped on the next run.', 'red-headed-pro' ); ?></small>
                    </label>
                </fieldset>

                <?php if ( $is_pro ) : ?>
                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">⏰ <?php esc_html_e( 'Schedule', 'red-headed-pro' ); ?></legend>
                    <select id="pl-pf-schedule">
                        <option value="manual"><?php esc_html_e( 'Manual only', 'red-headed-pro' ); ?></option>
                        <option value="every_5min"><?php esc_html_e( 'Every 5 minutes', 'red-headed-pro' ); ?></option>
                        <option value="every_15min"><?php esc_html_e( 'Every 15 minutes', 'red-headed-pro' ); ?></option>
                        <option value="every_30min"><?php esc_html_e( 'Every 30 minutes', 'red-headed-pro' ); ?></option>
                        <option value="hourly"><?php esc_html_e( 'Hourly', 'red-headed-pro' ); ?></option>
                        <option value="twicedaily"><?php esc_html_e( 'Twice daily', 'red-headed-pro' ); ?></option>
                        <option value="daily"><?php esc_html_e( 'Daily', 'red-headed-pro' ); ?></option>
                        <option value="weekly"><?php esc_html_e( 'Weekly', 'red-headed-pro' ); ?></option>
                    </select>
                    <small class="pl-muted"><?php esc_html_e( 'Interval schedules rely on WP-cron, which only fires on site traffic. For precise timing (e.g. every 5 min), point a real server cron at wp-cron.php. To export the instant an order reaches a status, use Auto-trigger below instead — no cron needed.', 'red-headed-pro' ); ?></small>
                </fieldset>

                <fieldset class="pl-field">
                    <legend class="pl-field-lbl">⚡ <?php esc_html_e( 'Auto-trigger', 'red-headed-pro' ); ?></legend>
                    <p class="pl-muted"><?php esc_html_e( 'Export each order automatically the instant it reaches a status — in real time, no cron needed. e.g. on a classic shop: “processing, completed” to export every paid + completed order. Leave empty to disable.', 'red-headed-pro' ); ?></p>
                    <label><span><?php esc_html_e( 'On status change to (comma-separated)', 'red-headed-pro' ); ?></span>
                        <input type="text" id="pl-pf-auto-status" placeholder="processing, completed" />
                    </label>
                    <label><span><?php esc_html_e( 'Min total (€)', 'red-headed-pro' ); ?></span>
                        <input type="number" step="0.01" id="pl-pf-auto-mintotal" />
                    </label>
                    <label class="pl-checkbox">
                        <input type="checkbox" id="pl-pf-auto-fireonce" />
                        <span><?php esc_html_e( 'Fire only once per order', 'red-headed-pro' ); ?></span>
                    </label>
                </fieldset>
                <?php endif; ?>
            </div>

            <footer class="pl-drawer-foot">
                <button type="button" class="pl-btn" id="pl-editor-cancel"><?php esc_html_e( 'Cancel', 'red-headed-pro' ); ?></button>
                <button type="button" class="pl-btn pl-btn-primary" id="pl-editor-save"><?php esc_html_e( 'Save profile', 'red-headed-pro' ); ?></button>
            </footer>
        </div>
    </div>
</div>
