<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Settings → Destinations defaults (server-wide creds, used as defaults inside profiles).
 *
 * @package Pelican
 */
if ( isset( $_POST['pl_dest_save'] ) && check_admin_referer( 'pl_dest_save' ) && current_user_can( 'manage_woocommerce' ) ) {
    update_option( 'pelican_default_email_to',        sanitize_text_field( $_POST['email_to']        ?? '' ) );
    update_option( 'pelican_default_email_subject',   sanitize_text_field( $_POST['email_subject']   ?? '' ) );
    update_option( 'pelican_default_email_from',      sanitize_email( $_POST['email_from']           ?? '' ) );
    update_option( 'pelican_default_email_from_name', sanitize_text_field( $_POST['email_from_name'] ?? '' ) );
    update_option( 'pelican_default_email_cc',        sanitize_text_field( $_POST['email_cc']        ?? '' ) );
    update_option( 'pelican_default_email_bcc',       sanitize_text_field( $_POST['email_bcc']       ?? '' ) );
    update_option( 'pelican_default_email_body',      wp_kses_post( $_POST['email_body']             ?? '' ) );
    update_option( 'pelican_default_sftp_host',       sanitize_text_field( $_POST['sftp_host']       ?? '' ) );
    update_option( 'pelican_default_sftp_port',       (int) ( $_POST['sftp_port'] ?? 22 ) );
    update_option( 'pelican_default_sftp_user',       sanitize_text_field( $_POST['sftp_user']       ?? '' ) );
    update_option( 'pelican_default_sftp_path',       sanitize_text_field( $_POST['sftp_path']       ?? '/' ) );
    if ( ! empty( $_POST['sftp_pass'] ) ) {
        update_option( 'pelican_default_sftp_pass_enc', Pelican_Destination_Base::encrypt( wp_unslash( $_POST['sftp_pass'] ) ) );
    }
    update_option( 'pelican_default_local_folder_path', sanitize_text_field( $_POST['local_folder_path'] ?? '' ) );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '✓ Destinations defaults saved.', 'red-headed-pro' ) . '</p></div>';
}

$rate      = Pelican_Destination_Email::rate_status();
$local_dir = get_option( 'pelican_default_local_folder_path', '' );
?>
<div class="pl-pane">
    <h3 class="pl-h3"><?php esc_html_e( '📡 Destinations defaults', 'red-headed-pro' ); ?></h3>
    <p class="pl-muted"><?php esc_html_e( 'Default credentials used as a starting point for new destinations inside profiles. Per-profile overrides supported.', 'red-headed-pro' ); ?></p>

    <form method="post" class="pl-form">
        <?php wp_nonce_field( 'pl_dest_save' ); ?>

        <fieldset class="pl-card">
            <legend class="pl-card-title">✉️ <?php esc_html_e( 'Email', 'red-headed-pro' ); ?></legend>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'Default recipient(s)', 'red-headed-pro' ); ?></span>
                <input type="text" name="email_to" value="<?php echo esc_attr( get_option( 'pelican_default_email_to', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Comma-separated emails', 'red-headed-pro' ); ?>" />
            </label>
            <div class="pl-grid pl-grid-2">
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Default from email', 'red-headed-pro' ); ?></span>
                    <input type="email" name="email_from" value="<?php echo esc_attr( get_option( 'pelican_default_email_from', '' ) ); ?>" placeholder="<?php esc_attr_e( 'WordPress default', 'red-headed-pro' ); ?>" />
                </label>
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Default from name', 'red-headed-pro' ); ?></span>
                    <input type="text" name="email_from_name" value="<?php echo esc_attr( get_option( 'pelican_default_email_from_name', '' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
                </label>
            </div>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'Default subject', 'red-headed-pro' ); ?></span>
                <input type="text" name="email_subject" value="<?php echo esc_attr( get_option( 'pelican_default_email_subject', '' ) ); ?>" placeholder="<?php esc_attr_e( 'New order received', 'red-headed-pro' ); ?>" />
                <small class="pl-muted"><?php esc_html_e( 'Placeholders: {filename} {records} {order_number} {order_id} {customer_email} {site_name} {date} {time}', 'red-headed-pro' ); ?></small>
            </label>
            <div class="pl-grid pl-grid-2">
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Default CC', 'red-headed-pro' ); ?></span>
                    <input type="text" name="email_cc" value="<?php echo esc_attr( get_option( 'pelican_default_email_cc', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Comma-separated', 'red-headed-pro' ); ?>" />
                </label>
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Default BCC', 'red-headed-pro' ); ?></span>
                    <input type="text" name="email_bcc" value="<?php echo esc_attr( get_option( 'pelican_default_email_bcc', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Comma-separated', 'red-headed-pro' ); ?>" />
                </label>
            </div>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'Default body', 'red-headed-pro' ); ?></span>
                <textarea name="email_body" rows="4"><?php echo esc_textarea( get_option( 'pelican_default_email_body', "Hi,\n\nYour Red-Headed export is ready: {filename} ({records} orders).\n\n— The Lion Frog" ) ); ?></textarea>
            </label>
            <p class="pl-muted">
                <?php
                $rate_limit_display = ( $rate['limit'] >= 1e9 ) ? '∞' : number_format( (int) $rate['limit'] );
                printf(
                    /* translators: 1: sent count, 2: limit or ∞, 3: edition label */
                    esc_html__( '%1$d / %2$s sent (24h sliding) — %3$s.', 'red-headed-pro' ),
                    (int) $rate['sent_24h'], $rate_limit_display,
                    Pelican_Soft_Lock::is_pro() ? esc_html__( 'Pro: unlimited', 'red-headed-pro' ) : esc_html__( 'Lite cap', 'red-headed-pro' )
                );
                ?>
            </p>
        </fieldset>

        <fieldset class="pl-card">
            <legend class="pl-card-title">📡 <?php esc_html_e( 'SFTP', 'red-headed-pro' ); ?></legend>
            <div class="pl-grid pl-grid-2">
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Host', 'red-headed-pro' ); ?></span>
                    <input type="text" name="sftp_host" value="<?php echo esc_attr( get_option( 'pelican_default_sftp_host', '' ) ); ?>" placeholder="sftp.example.com" />
                </label>
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Port', 'red-headed-pro' ); ?></span>
                    <input type="number" name="sftp_port" value="<?php echo (int) get_option( 'pelican_default_sftp_port', 22 ); ?>" />
                </label>
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'User', 'red-headed-pro' ); ?></span>
                    <input type="text" name="sftp_user" value="<?php echo esc_attr( get_option( 'pelican_default_sftp_user', '' ) ); ?>" />
                </label>
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Password', 'red-headed-pro' ); ?></span>
                    <input type="password" name="sftp_pass" placeholder="<?php echo get_option( 'pelican_default_sftp_pass_enc' ) ? '•••••• ' . esc_attr__( '(stored)', 'red-headed-pro' ) : ''; ?>" autocomplete="new-password" />
                </label>
                <label class="pl-field pl-grid-span-2">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Remote path', 'red-headed-pro' ); ?></span>
                    <input type="text" name="sftp_path" value="<?php echo esc_attr( get_option( 'pelican_default_sftp_path', '/' ) ); ?>" placeholder="/incoming/orders/" />
                </label>
            </div>
            <p class="pl-muted">🔒 <?php esc_html_e( 'Password is encrypted at rest (AES-256-CBC + wp_salt).', 'red-headed-pro' ); ?></p>
        </fieldset>

        <?php if ( Pelican_Soft_Lock::is_pro() ) : ?>
        <fieldset class="pl-card">
            <legend class="pl-card-title">📂 <?php esc_html_e( 'Local folder', 'red-headed-pro' ); ?> <span class="pl-pill pl-pill-pro">PRO</span></legend>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'Default path', 'red-headed-pro' ); ?></span>
                <input type="text" name="local_folder_path" value="<?php echo esc_attr( $local_dir ); ?>" placeholder="order-exports" />
            </label>
            <p class="pl-muted"><?php esc_html_e( 'Relative to wp-content/ (e.g. "order-exports") or an absolute path inside the WordPress root. Empty = wp-content/order-exports. The raw export file is copied here (no zip).', 'red-headed-pro' ); ?></p>
            <button type="button" class="pl-btn pl-btn-sm" id="pl-dest-create-folder"><?php esc_html_e( 'Create folder if missing', 'red-headed-pro' ); ?></button>
            <span id="pl-dest-folder-status" class="pl-muted" style="margin-left:8px;"></span>
        </fieldset>

        <fieldset class="pl-card">
            <legend class="pl-card-title">📁 <?php esc_html_e( 'Google Drive', 'red-headed-pro' ); ?> <span class="pl-pill pl-pill-pro">PRO</span></legend>
            <p class="pl-muted"><?php esc_html_e( 'OAuth flow — set up under each Pro profile destination. Server-side OAuth client coming in v1.1.', 'red-headed-pro' ); ?></p>
        </fieldset>

        <fieldset class="pl-card">
            <legend class="pl-card-title">🔗 <?php esc_html_e( 'REST endpoint', 'red-headed-pro' ); ?> <span class="pl-pill pl-pill-pro">PRO</span></legend>
            <p class="pl-muted"><?php esc_html_e( 'Configure URL + auth per profile destination — supports Bearer / Basic / custom header.', 'red-headed-pro' ); ?></p>
        </fieldset>
        <?php else : ?>
            <?php Pelican_Soft_Lock::wrap( 'dest_local_folder', function () { ?>
                <fieldset class="pl-card">
                    <legend class="pl-card-title">📂 Local folder</legend>
                    <p class="pl-muted">Copy exports to a watched directory for ERP pickup.</p>
                </fieldset>
            <?php } ); ?>
            <?php Pelican_Soft_Lock::wrap( 'dest_gdrive', function () { ?>
                <fieldset class="pl-card">
                    <legend class="pl-card-title">📁 Google Drive</legend>
                    <p class="pl-muted">OAuth + 1-click upload to a folder.</p>
                </fieldset>
            <?php } ); ?>
            <?php Pelican_Soft_Lock::wrap( 'dest_rest', function () { ?>
                <fieldset class="pl-card">
                    <legend class="pl-card-title">🔗 REST endpoint</legend>
                    <p class="pl-muted">POST/PUT to URL — Bearer / Basic / custom header.</p>
                </fieldset>
            <?php } ); ?>
        <?php endif; ?>

        <p>
            <button type="submit" name="pl_dest_save" class="pl-btn pl-btn-primary"><?php esc_html_e( '💾 Save defaults', 'red-headed-pro' ); ?></button>
        </p>
    </form>
</div>
