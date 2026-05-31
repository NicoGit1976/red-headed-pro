<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Settings → Webhooks tab. Pro only.
 *
 * @package Pelican
 */
if ( Pelican_Soft_Lock::is_pro() ) {
    if ( isset( $_POST['pl_hook_add'] ) && check_admin_referer( 'pl_hook_add' ) && current_user_can( 'manage_woocommerce' ) ) {
        $url    = esc_url_raw( wp_unslash( $_POST['url']    ?? '' ) );
        $secret =     sanitize_text_field( wp_unslash( $_POST['secret'] ?? '' ) );
        $events = isset( $_POST['events'] ) ? array_map( 'sanitize_key', (array) $_POST['events'] ) : array();
        if ( Pelican_Webhooks::register_url( $url, $secret, $events ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '✓ Webhook saved.', 'red-headed-pro' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( '⚠ Invalid URL — must be HTTPS.', 'red-headed-pro' ) . '</p></div>';
        }
    }
    if ( isset( $_POST['pl_hook_del'] ) && check_admin_referer( 'pl_hook_del' ) && current_user_can( 'manage_woocommerce' ) ) {
        Pelican_Webhooks::unregister_url( wp_unslash( $_POST['pl_hook_del'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '✓ Webhook removed.', 'red-headed-pro' ) . '</p></div>';
    }
}
?>
<div class="pl-pane">
    <h3 class="pl-h3">🔔 <?php esc_html_e( 'Webhooks', 'red-headed-pro' ); ?></h3>

    <?php if ( ! Pelican_Soft_Lock::is_pro() ) : ?>
        <div class="pl-locked-pane">
            <div class="pl-locked-icon">🔒</div>
            <h4><?php esc_html_e( 'Webhooks — Pro feature', 'red-headed-pro' ); ?></h4>
            <p class="pl-muted"><?php esc_html_e( 'Get notified by HTTP POST every time an export is generated, delivered, or fails. HMAC-signed payloads, per-endpoint event filter.', 'red-headed-pro' ); ?></p>
            <ul class="pl-list-check">
                <li>✓ <code>export.generated</code> · <code>export.delivered</code> · <code>export.failed</code></li>
                <li>✓ <?php esc_html_e( 'HMAC SHA-256 signature in X-Pelican-Signature', 'red-headed-pro' ); ?></li>
                <li>✓ <?php esc_html_e( 'Multiple endpoints, per-endpoint event subscription', 'red-headed-pro' ); ?></li>
            </ul>
            <a href="https://thelionfrog.com/products/plugins/woo-order-pro" target="_blank" rel="noopener" class="pl-btn pl-btn-upgrade">⚡ <?php esc_html_e( 'Upgrade to Pro', 'red-headed-pro' ); ?></a>
        </div>
    <?php else :
        $hooks = Pelican_Webhooks::list_urls();
    ?>
        <form method="post" class="pl-form pl-card">
            <?php wp_nonce_field( 'pl_hook_add' ); ?>
            <h4 class="pl-card-title"><?php esc_html_e( '+ Add webhook endpoint', 'red-headed-pro' ); ?></h4>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'URL (HTTPS only)', 'red-headed-pro' ); ?></span>
                <input type="url" name="url" required placeholder="https://example.com/webhooks/pelican" />
            </label>
            <label class="pl-field">
                <span class="pl-field-lbl"><?php esc_html_e( 'Secret (HMAC SHA-256)', 'red-headed-pro' ); ?></span>
                <input type="text" name="secret" placeholder="<?php esc_attr_e( 'Optional but recommended', 'red-headed-pro' ); ?>" />
            </label>
            <fieldset class="pl-field">
                <legend class="pl-field-lbl"><?php esc_html_e( 'Events', 'red-headed-pro' ); ?></legend>
                <?php foreach ( array( 'export.generated', 'export.delivered', 'export.failed' ) as $ev ) : ?>
                    <label class="pl-checkbox">
                        <input type="checkbox" name="events[]" value="<?php echo esc_attr( $ev ); ?>" checked />
                        <span><code><?php echo esc_html( $ev ); ?></code></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <p>
                <button type="submit" name="pl_hook_add" class="pl-btn pl-btn-primary"><?php esc_html_e( '💾 Save webhook', 'red-headed-pro' ); ?></button>
            </p>
        </form>

        <h4 class="pl-h3"><?php esc_html_e( 'Registered endpoints', 'red-headed-pro' ); ?></h4>
        <?php if ( empty( $hooks ) ) : ?>
            <p class="pl-muted">— <?php esc_html_e( 'None yet.', 'red-headed-pro' ); ?></p>
        <?php else : ?>
            <table class="pl-table pl-table-zebra">
                <thead><tr>
                    <th><?php esc_html_e( 'URL', 'red-headed-pro' ); ?></th>
                    <th><?php esc_html_e( 'Events', 'red-headed-pro' ); ?></th>
                    <th><?php esc_html_e( 'Secret', 'red-headed-pro' ); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                    <?php foreach ( $hooks as $h ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $h['url'] ); ?></code></td>
                            <td><?php echo esc_html( implode( ', ', (array) ( $h['events'] ?? array() ) ) ); ?></td>
                            <td><?php echo ! empty( $h['secret'] ) ? '🔒 ' . esc_html__( 'set', 'red-headed-pro' ) : '—'; ?></td>
                            <td>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field( 'pl_hook_del' ); ?>
                                    <button type="submit" name="pl_hook_del" value="<?php echo esc_attr( $h['url'] ); ?>" class="pl-btn pl-btn-sm pl-btn-danger" onclick="return confirm('<?php echo esc_js( __( 'Remove this webhook?', 'red-headed-pro' ) ); ?>');">×</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <!-- v1.5.0 — P4: Webhook payload sample documentation -->
        <h4 class="pl-h3" style="margin-top:24px;"><?php esc_html_e( 'Payload reference', 'red-headed-pro' ); ?></h4>
        <p class="pl-muted"><?php esc_html_e( 'Every POST request includes these headers and a JSON body. Use the secret for HMAC verification.', 'red-headed-pro' ); ?></p>
        <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-top:12px;">
            <div class="pl-card" style="padding:12px;">
                <strong style="display:block;margin-bottom:6px;"><?php esc_html_e( 'Headers', 'red-headed-pro' ); ?></strong>
                <pre style="background:#1e293b;color:#e2e8f0;padding:10px;border-radius:6px;font-size:11px;line-height:1.6;overflow-x:auto;white-space:pre-wrap;">Content-Type: application/json; charset=UTF-8
User-Agent: Pelican/<?php echo esc_html( PELICAN_VERSION ); ?>

X-Pelican-Event: export.generated
X-Pelican-Site: <?php echo esc_html( home_url() ); ?>

X-Pelican-Signature: sha256=&lt;HMAC-SHA256(body, secret)&gt;</pre>
            </div>
            <div class="pl-card" style="padding:12px;">
                <strong style="display:block;margin-bottom:6px;"><code>export.generated</code></strong>
                <pre style="background:#1e293b;color:#e2e8f0;padding:10px;border-radius:6px;font-size:11px;line-height:1.6;overflow-x:auto;white-space:pre-wrap;">{
  "event": "export.generated",
  "data": {
    "job_id": 42,
    "profile_id": 1,
    "file": "daily-orders-20260527-103015-abc123.json"
  },
  "site": "<?php echo esc_html( home_url() ); ?>",
  "timestamp_ms": 1748345415000,
  "plugin": "pelican",
  "version": "<?php echo esc_html( PELICAN_VERSION ); ?>"
}</pre>
            </div>
            <div class="pl-card" style="padding:12px;">
                <strong style="display:block;margin-bottom:6px;"><code>export.delivered</code></strong>
                <pre style="background:#1e293b;color:#e2e8f0;padding:10px;border-radius:6px;font-size:11px;line-height:1.6;overflow-x:auto;white-space:pre-wrap;">{
  "event": "export.delivered",
  "data": {
    "job_id": 42,
    "profile_id": 1,
    "destinations": [
      {"destination": {"type": "sftp"}, "ok": true}
    ]
  },
  ...
}</pre>
            </div>
            <div class="pl-card" style="padding:12px;">
                <strong style="display:block;margin-bottom:6px;"><code>export.failed</code></strong>
                <pre style="background:#1e293b;color:#e2e8f0;padding:10px;border-radius:6px;font-size:11px;line-height:1.6;overflow-x:auto;white-space:pre-wrap;">{
  "event": "export.failed",
  "data": {
    "job_id": 42,
    "profile_id": 1,
    "error": "Connection timed out"
  },
  ...
}</pre>
            </div>
        </div>
        <p class="pl-muted" style="margin-top:12px;font-size:11px;">
            <?php esc_html_e( 'Verify: hash_hmac("sha256", $raw_body, $your_secret) === str_replace("sha256=","", $header["X-Pelican-Signature"])', 'red-headed-pro' ); ?>
        </p>
    <?php endif; ?>
</div>
