<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_Plugin {
    private static ?HCP_Plugin $instance = null;

    public static function instance(): HCP_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'load_textdomain' ), 0 );
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-helpers.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-license.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-units.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-tally.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-caps.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-access.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-cpt.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-compliance.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-owner-docs.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-assets.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-admin.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-frontend.php';

        HCP_CPT::hooks();
        HCP_Caps::hooks();
        HCP_Assets::hooks();
        HCP_Admin::hooks();
        HCP_Compliance::hooks();
        HCP_Owner_Docs::hooks();
        HCP_Frontend::hooks();

        // Small compliance check for distribution builds.
        add_action( 'admin_notices', array( $this, 'maybe_notice_stable_tag_mismatch' ) );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain( 'hoa-coa-portal-pro', false, dirname( plugin_basename( HCP_PLUGIN_FILE ) ) . '/languages' );
    }

    public static function activate(): void {
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-caps.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-access.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-cpt.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-compliance.php';
        require_once HCP_PLUGIN_DIR . 'includes/class-hcp-owner-docs.php';

        HCP_CPT::register();
        HCP_Compliance::register();
        HCP_Compliance::seed_terms();
        HCP_Owner_Docs::register();
        HCP_Owner_Docs::seed_terms();
        HCP_Caps::activate();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

public function maybe_notice_stable_tag_mismatch(): void {
    if ( ! current_user_can( 'manage_options' ) ) { return; }

    // Only show on plugin-related screens to reduce noise.
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && ! in_array( $screen->base, array( 'plugins', 'toplevel_page_hcp-dashboard', 'hoa-coa-portal_page_hcp-access-roles' ), true ) ) {
        return;
    }

    $readme = HCP_PLUGIN_DIR . 'readme.txt';
    if ( ! file_exists( $readme ) ) { return; }

    $contents = file_get_contents( $readme );
    if ( false === $contents ) { return; }

    if ( ! preg_match( '/^Stable tag:\s*([^\s]+)\s*$/mi', $contents, $m ) ) { return; }
    $stable = trim( (string) $m[1] );

    if ( defined( 'HCP_VERSION' ) && $stable !== HCP_VERSION ) {
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__( 'HOA/COA Portal: readme.txt Stable tag does not match the plugin Version. For WordPress.org compliance, these must match exactly.', 'hoa-coa-portal-pro' );
        echo ' <code>' . esc_html( $stable ) . ' != ' . esc_html( HCP_VERSION ) . '</code>';
        echo '</p></div>';
    }
}

}