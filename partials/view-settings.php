<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Settings — tab dispatcher (Profiles / Destinations / Cron / Webhooks / General).
 *
 * @package Pelican
 */
$tabs = array(
    'profiles'     => array( 'icon' => '📁', 'label' => __( 'Profiles',     'pelican' ), 'lock' => null ),
    'destinations' => array( 'icon' => '📡', 'label' => __( 'Destinations', 'pelican' ), 'lock' => null ),
    'cron'         => array( 'icon' => '⏰', 'label' => __( 'Cron',         'pelican' ), 'lock' => 'cron' ),
    'webhooks'     => array( 'icon' => '🔔', 'label' => __( 'Webhooks',     'pelican' ), 'lock' => 'webhooks' ),
    'general'      => array( 'icon' => '⚙️', 'label' => __( 'General',      'pelican' ), 'lock' => null ),
);
$active = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'profiles';
if ( ! isset( $tabs[ $active ] ) ) $active = 'profiles';
?>
<div class="pl-wrap wrap">
    <?php include PELICAN_PATH . 'partials/_page-nav.php'; ?>

    <section class="pl-section">
        <h2 class="pl-h2"><?php esc_html_e( '⚙️ Settings', 'pelican' ); ?></h2>

        <nav class="pl-tabs" role="tablist">
            <?php foreach ( $tabs as $slug => $meta ) :
                $url = admin_url( 'admin.php?page=red-headed-pro-settings-' . $slug );
                $cur = $slug === $active ? 'pl-tab-active' : '';
                $locked = $meta['lock'] && Pelican_Soft_Lock::is_locked( $meta['lock'] );
            ?>
                <a href="<?php echo esc_url( $url ); ?>" class="pl-tab <?php echo $cur; ?> <?php echo $locked ? 'pl-tab-locked' : ''; ?>">
                    <span class="pl-tab-icon"><?php echo $meta['icon']; ?></span>
                    <span class="pl-tab-label"><?php echo esc_html( $meta['label'] ); ?></span>
                    <?php if ( $locked ) echo wp_kses_post( Pelican_Soft_Lock::badge() ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="pl-tab-pane">
            <?php
            $part = PELICAN_PATH . 'partials/settings/tab-' . $active . '.php';
            if ( file_exists( $part ) ) include $part;
            ?>
        </div>
    </section>
</div>
