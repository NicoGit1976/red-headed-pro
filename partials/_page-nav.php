<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Shared in-page nav (Harlequin). Dashboard / Exports / Settings.
 *
 * @package Red_Headed_Pro
 */
$_pl_pages = array(
    'red-headed-pro'          => array( 'icon' => '📊', 'label' => __( 'Dashboard', 'red-headed-pro' ) ),
    'red-headed-pro-exports'  => array( 'icon' => '📦', 'label' => __( 'Exports',   'red-headed-pro' ) ),
    'red-headed-pro-settings' => array( 'icon' => '⚙️', 'label' => __( 'Settings',  'red-headed-pro' ) ),
);
$_pl_current = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'red-headed-pro';
if ( strpos( $_pl_current, 'red-headed-pro-settings' ) === 0 ) $_pl_current = 'red-headed-pro-settings';
?>
<nav class="pl-page-nav" role="navigation" aria-label="Red Headed sections">
    <?php foreach ( $_pl_pages as $slug => $meta ) :
        $url = admin_url( 'admin.php?page=' . $slug );
        $is_cur = ( $slug === $_pl_current );
    ?>
        <a href="<?php echo esc_url( $url ); ?>" class="pl-page-nav-item <?php echo $is_cur ? 'pl-page-nav-active' : ''; ?>">
            <span class="pl-page-nav-icon"><?php echo esc_html( $meta['icon'] ); ?></span>
            <span class="pl-page-nav-label"><?php echo esc_html( $meta['label'] ); ?></span>
        </a>
    <?php endforeach; ?>
</nav>
