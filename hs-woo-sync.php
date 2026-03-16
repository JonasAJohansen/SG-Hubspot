<?php
/**
 * Plugin Name:  HS WooSync
 * Description:  Synker ferdigbetalte WooCommerce-ordrer til HubSpot (mock).
 * Version:      1.0.0
 * Author:       Springtime Group
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'HS_WOOS_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function () {

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'HS WooSync krever at WooCommerce er aktivert.', 'hs-woos' )
                . '</p></div>';
        } );
        return;
    }

    require_once HS_WOOS_DIR . 'includes/class-api-client.php';
    require_once HS_WOOS_DIR . 'includes/class-sync-handler.php';
    require_once HS_WOOS_DIR . 'includes/class-admin-log.php';

    HS_WooS_Admin_Log::init();
    HS_WooS_Sync_Handler::init();

} );

register_activation_hook( __FILE__, function () {
    add_option( 'hs_woos_endpoint', 'https://joinevent.no/mock/?route=ingest' );
    add_option( 'hs_woos_api_token', 'demo-token-123' );
    add_option( 'hs_woos_error_log', [] );
    add_option( 'hs_woos_sync_log',  [] );
} );
