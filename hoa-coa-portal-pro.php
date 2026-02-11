<?php
/**
 * Plugin Name:       HOA/COA Portal (Pro)
 * Plugin URI:        https://plugins.sunlifetech.com/
 * Description:       Pro edition of the HOA/COA Portal: secure owner portal, compliance binder, elections/voting, and advanced pro tools.
 * Version: 1.0.8
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Sun Life Tech
 * Author URI:        https://sunlifetech.com/
 * Text Domain: hoa-coa-portal-pro
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// If the Free edition is active, block Pro from loading to avoid class/constant collisions.
if ( defined( 'HCP_PLUGIN_FILE' ) || class_exists( 'HCP_Plugin', false ) ) {
    add_action( 'admin_notices', static function () {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-error"><p>' . esc_html__( 'HOA/COA Portal (Pro) cannot run while the Free edition is active. Please deactivate the Free plugin first, then activate Pro.', 'hoa-coa-portal-pro' ) . '</p></div>';
    } );
    return;
}

define( 'HCP_PRO_ACTIVE', true );
define( 'HCP_PRO_VERSION', '1.0.7' );
// Pro shares the same data model/meta keys as Free for smooth upgrade/migration.
define( 'HCP_VERSION', HCP_PRO_VERSION );
define( 'HCP_PLUGIN_FILE', __FILE__ );
define( 'HCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HCP_PLUGIN_DIR . 'includes/class-hcp-plugin.php';

add_action( 'plugins_loaded', function() {
	if ( '' === (string) get_option( 'hcp_preserve_data_on_uninstall', '' ) ) {
		add_option( 'hcp_preserve_data_on_uninstall', 'yes' );
	}
}, 1 );

add_action( 'plugins_loaded', static function () {
    HCP_Plugin::instance();
} );

register_activation_hook( __FILE__, static function () {
    // Hard-block activation if the Free edition is still active.
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( is_plugin_active( 'hoa-coa-portal/hoa-coa-portal.php' ) ) {
        wp_die(
            esc_html__( 'Please deactivate the HOA/COA Portal (Free) plugin before activating HOA/COA Portal (Pro).', 'hoa-coa-portal-pro' ),
            esc_html__( 'Activation blocked', 'hoa-coa-portal-pro' ),
            array( 'response' => 409 )
        );
    }
    HCP_Plugin::activate();
} );
register_deactivation_hook( __FILE__, array( 'HCP_Plugin', 'deactivate' ) );
