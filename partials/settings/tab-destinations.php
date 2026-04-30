<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Settings → Destinations defaults (server-wide creds, used as defaults inside profiles).
 *
 * @package Pelican
 */
if ( isset( $_POST['pl_dest_save'] ) && check_admin_referer( 'pl_dest_save' ) && current_user_can( 'manage_woocommerce' ) ) {
    update_option( 'pelican_default_email_to',      sanitize_email( $_POST['email_to']      ?? '' ) );
    update_option( 'pelican_default_email_subject', sanitize_text_field( $_POST['email_subject'] ?? '' ) );
    update_option( 'pelican_default_email_body',    wp_kses_post( $_POST['email_body']      ?? '' ) );
    update_option( 'pelican_default_sftp_host',     sanitize_text_field( $_POST['sftp_host'] ?? '' ) );
    update_option( 'pelican_default_sftp_port',     (int) ( $_POST['sftp_port'] ?? 22 ) );
    update_option( 'pelican_default_sftp_user',     sanitize_text_field( $_POST['sftp_user'] ?? '' ) );
    update_option( 'pelican_default_sftp_path',     sanitize_text_field( $_POST['sftp_path'] ?? '/' ) );
    if ( ! empty( $_POST['sftp_pass'] ) ) {
        update_option( 'pelican_default_sftp_pass_enc', Pelican_Destination_Base::encrypt( wp_unslash( $_POST['sftp_pass'] ) ) );
    }
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '✓ Destinations defaults saved.', 'pelican' ) . '</p></div>';
}

$rate = Pelican_Destination_Email::rate_status();
?>
<div class="pl-pane">
    <h3 class="pl-h3"><?php esc_html_e( '📡 Destinations defaults', 'pelican' ); ?></h3>
    <p class="pl-muted"><?php esc_html_e( 'Default credentials used as a starting point for new destinations inside profiles. Per-profile overrides supported.', 'pelican' ); ?></p>

    <form method="post" class="pl-form">
        <?php wp_nonce_field( 'pl_dest_save' ); ?>

        <fieldset class="pl-card">
            <legend class="pl-card-title">✉️ <?php esc_html_e( 'Email', 'pelican' ); ?></legend>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'Default recipient(s)', 'pelican' ); ?></span>
                <input type="email" name="email_to" value="<?php echo esc_attr( get_option( 'pelican_default_email_to', '' ) ); ?>" multiple />
            </label>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'Default subject', 'pelican' ); ?></span>
                <input type="text" name="email_subject" value="<?php echo esc_attr( get_option( 'pelican_email_subject', '🃏 Harlequin export — {{filename}}' ) ); ?>" />
            </label>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'Default body', 'pelican' ); ?></span>
                <textarea name="email_body" rows="4"><?php echo esc_textarea( get_option( 'pelican_email_body', "Bonjour,\n\nVoici l'export généré par Harlequin : {{filename}} ({{records}} commandes).\n\n— The Lion Frog" ) ); ?></textarea>
            </label>
            <p class="pl-muted">
                <?php
                printf(
                    /* translators: 1: sent, 2: limit, 3: edition */
                    esc_html__( '%1$d / %2$d sent (24h sliding) — %3$s.', 'pelican' ),
                    (int) $rate['sent_24h'], (int) $rate['limit'],
                    Pelican_Soft_Lock::is_pro() ? esc_html__( 'Pro: unlimited', 'pelican' ) : esc_html__( 'Lite cap', 'pelican' )
                );
                ?>
            </p>
        </fieldset>

        <fieldset class="pl-card">
            <legend class="pl-card-title">📡 <?php esc_html_e( 'SFTP', 'pelican' ); ?></legend>
            <div class="pl-grid pl-grid-2">
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Host', 'pelican' ); ?></span>
                    <input type="text" name="sftp_host" value="<?php echo esc_attr( get_option( 'pelican_default_sftp_host', '' ) ); ?>" placeholder="sftp.example.com" />
                </label>
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Port', 'pelican' ); ?></span>
                    <input type="number" name="sftp_port" value="<?php echo (int) get_option( 'pelican_default_sftp_port', 22 ); ?>" />
                </label>
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'User', 'pelican' ); ?></span>
                    <input type="text" name="sftp_user" value="<?php echo esc_attr( get_option( 'pelican_default_sftp_user', '' ) ); ?>" />
                </label>
                <label class="pl-field">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Password', 'pelican' ); ?></span>
                    <input type="password" name="sftp_pass" placeholder="<?php echo get_option( 'pelican_default_sftp_pass_enc' ) ? '•••••• ' . esc_attr__( '(stored)', 'pelican' ) : ''; ?>" autocomplete="new-password" />
                </label>
                <label class="pl-field pl-grid-span-2">
                    <span class="pl-field-lbl"><?php esc_html_e( 'Remote path', 'pelican' ); ?></span>
                    <input type="text" name="sftp_path" value="<?php echo esc_attr( get_option( 'pelican_default_sftp_path', '/' ) ); ?>" placeholder="/incoming/orders/" />
                </label>
            </div>
            <p class="pl-muted">🔒 <?php esc_html_e( 'Password is encrypted at rest (AES-256-CBC + wp_salt).', 'pelican' ); ?></p>
        </fieldset>

        <?php if ( Pelican_Soft_Lock::is_pro() ) : ?>
        <fieldset class="pl-card">
            <legend class="pl-card-title">📁 <?php esc_html_e( 'Google Drive', 'pelican' ); ?> <span class="pl-pill pl-pill-pro">PRO</span></legend>
            <p class="pl-muted"><?php esc_html_e( 'OAuth flow — set up under each Pro profile destination. Server-side OAuth client coming in v1.1.', 'pelican' ); ?></p>
        </fieldset>

        <fieldset class="pl-card">
            <legend class="pl-card-title">🔗 <?php esc_html_e( 'REST endpoint', 'pelican' ); ?> <span class="pl-pill pl-pill-pro">PRO</span></legend>
            <p class="pl-muted"><?php esc_html_e( 'Configure URL + auth per profile destination — supports Bearer / Basic / custom header.', 'pelican' ); ?></p>
        </fieldset>
        <?php else : ?>
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
            <button type="submit" name="pl_dest_save" class="pl-btn pl-btn-primary"><?php esc_html_e( '💾 Save defaults', 'pelican' ); ?></button>
        </p>
    </form>
</div>
