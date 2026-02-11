<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_Assets {
    public static function hooks(): void {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend' ) );
    }

    public static function admin( string $hook ): void {
        if ( ! is_admin() ) { return; }
        if ( false === str_contains( $hook, 'hcp-' ) ) { return; }
        wp_enqueue_style( 'hcp-admin', HCP_PLUGIN_URL . 'assets/css/admin.css', array(), HCP_VERSION );
        wp_enqueue_media();
        wp_enqueue_script( 'hcp-admin', HCP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), HCP_VERSION, true );
    }

    public static function frontend(): void {
        if ( is_admin() ) { return; }
        global $post;
        if ( ! ( $post instanceof WP_Post ) ) { return; }
        if ( ! has_shortcode( $post->post_content, 'hcp_portal' ) && ! has_shortcode( $post->post_content, 'hoa_coa_portal' ) ) { return; }

        $settings = HCP_Helpers::settings();
        if ( (int) $settings['load_css'] === 1 ) {
            wp_enqueue_style( 'hcp-frontend', HCP_PLUGIN_URL . 'assets/css/frontend.css', array(), HCP_VERSION );
        }
        wp_enqueue_script( 'hcp-frontend', HCP_PLUGIN_URL . 'assets/js/frontend.js', array(), HCP_VERSION, true );
    }
}
