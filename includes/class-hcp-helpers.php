<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_Helpers {
    public static function settings(): array {
        $defaults = array(
            'portal_page_id' => 0,
            'load_css'       => 1,
            // Branding (association-specific).
            'assoc_name'     => '',
            'assoc_logo_id'  => 0,
            'assoc_phone'    => '',
            'assoc_email'    => '',
            'assoc_address'  => '',
            'assoc_website'  => '',
            // Florida compliance sizing.
            'assoc_type'     => 'condo', // condo|hoa
            'unit_count'     => 0,
            'parcel_count'   => 0,
        );
        $opt = get_option( 'hcp_settings', array() );
        if ( ! is_array( $opt ) ) { $opt = array(); }
        return array_merge( $defaults, $opt );
    }

    public static function update_settings( array $settings ): void {
        update_option( 'hcp_settings', $settings );
    }

    public static function is_allowed_attachment( int $attachment_id ): bool {
        $mime = (string) get_post_mime_type( $attachment_id );
        if ( '' === $mime ) { return false; }
        if ( str_starts_with( $mime, 'image/' ) ) { return true; }
        return ( 'application/pdf' === $mime );
    }

    public static function can_manage(): bool {
        return HCP_Access::can_manage_content();
    }

    public static function can_view_portal(): bool {
        return HCP_Access::can_view_portal();
    }

    public static function can_vote(): bool {
        return current_user_can( 'hcp_vote' );
    }

    public static function require_login_or_redirect(): void {
        if ( is_user_logged_in() ) {
            return;
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server var sanitized for redirect.
        $redirect = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
        $redirect = (string) wp_sanitize_redirect( $redirect );
        wp_safe_redirect( wp_login_url( $redirect ) );
        exit;
    }

    public static function not_authorized_message(): string {
        return '<div class="hcp-msg">' . esc_html__( 'You are not authorized to access this portal.', 'hoa-coa-portal-pro' ) . '</div>';
    }

    /**
     * Redirect safely even if output already started.
     * If headers have been sent (Divi/Elementor sometimes outputs early), we print a link instead of sending headers.
     */
    public static function safe_redirect( string $url ): void {
        $url = esc_url_raw( $url );
        if ( ! $url ) {
            $url = home_url( '/' );
        }

        if ( headers_sent() ) {
            echo '<p>' . esc_html__( 'Redirecting…', 'hoa-coa-portal-pro' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Continue', 'hoa-coa-portal-pro' ) . '</a></p>';
            exit;
        }

        wp_safe_redirect( $url );
        exit;
    }

}
