<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Bulk action on the WC orders list — "🐸 Export selected orders (Harlequin)".
 * Lite + Pro both expose the action; Lite is capped to CSV + 1 destination.
 *
 * @package Pelican
 */
class Pelican_Bulk_Actions {
    public function __construct() {
        add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk' ) );
        add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk' ), 10, 3 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk' ) );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'maybe_render_result' ) );
    }
    public function register_bulk( $actions ) {
        $actions['pelican_export'] = __( '🐸 Export with Red-Headed', 'red-headed-pro' );
        return $actions;
    }
    /**
     * Handle the bulk action. v1.5.0 — load from a real profile instead of
     * hardcoding CSV + default columns, so the user's chosen format, columns,
     * casts, filename pattern, destinations, etc. are all respected.
     *
     * Precedence: explicit profile_id in request → first saved profile → ad-hoc CSV.
     */
    public function handle_bulk( $redirect, $action, $ids ) {
        if ( $action !== 'pelican_export' ) return $redirect;
        $order_ids = array_map( function ( $id ) {
            return is_object( $id ) ? (int) $id->get_id() : (int) $id;
        }, (array) $ids );

        $profile_id = ! empty( $_REQUEST['pelican_profile_id'] ) ? (int) $_REQUEST['pelican_profile_id'] : 0;
        $dry_run    = ! empty( $_REQUEST['pelican_dry_run'] );
        $profile    = $profile_id ? Pelican_Profile_Repo::get( $profile_id ) : null;
        if ( ! $profile ) {
            $all = Pelican_Profile_Repo::all();
            $profile = ! empty( $all ) ? reset( $all ) : null;
        }
        if ( ! $profile ) {
            /* No profiles exist yet — ad-hoc CSV with default columns. */
            $profile = array(
                'name'         => 'Bulk export',
                'format'       => 'csv',
                'columns'      => Pelican_Export_Engine::default_columns(),
                'destinations' => array(),
            );
        }

        /* Inject selected order IDs as filter override. */
        if ( ! isset( $profile['filters'] ) || ! is_array( $profile['filters'] ) ) {
            $profile['filters'] = array();
        }
        $profile['filters']['order_ids_override'] = $order_ids;
        if ( $dry_run ) {
            $profile['_dry_run'] = true;
        }

        $job = Pelican_Export_Engine::run( $profile, 'bulk_action' );
        $args = is_wp_error( $job )
            ? array( 'pelican_bulk' => 0, 'pelican_err' => urlencode( $job->get_error_message() ) )
            : array( 'pelican_bulk' => 1, 'pelican_job' => (int) $job );
        if ( $dry_run && ! is_wp_error( $job ) ) {
            $args['pelican_dry'] = 1;
        }
        return add_query_arg( $args, $redirect );
    }
    public function maybe_render_result() {
        if ( empty( $_GET['pelican_bulk'] ) ) return;
        if ( $_GET['pelican_bulk'] === '1' ) {
            $job_id  = (int) $_GET['pelican_job'];
            $dry     = ! empty( $_GET['pelican_dry'] );
            $url     = admin_url( 'admin.php?page=red-headed-pro-exports' );
            $badge   = $dry ? '<span style="background:#fbbf24;color:#000;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:700;margin-right:4px;">DRY RUN</span>' : '';
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                /* translators: 1: job id, 2: link to exports list */
                '✓ ' . $badge . esc_html__( 'Red-Headed export #%1$d created. %2$s', 'red-headed-pro' ),
                $job_id,
                '<a href="' . esc_url( $url ) . '" class="button button-primary" style="margin-left:6px;">' . esc_html__( 'Open Exports list', 'red-headed-pro' ) . '</a>'
            );
            echo '</p></div>';
        } else {
            $err = sanitize_text_field( wp_unslash( $_GET['pelican_err'] ?? 'unknown' ) );
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( '⚠ Red-Headed export failed: ' . $err ) . '</p></div>';
        }
    }
}
