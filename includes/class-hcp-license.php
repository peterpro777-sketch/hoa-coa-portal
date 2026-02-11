<?php
/**
 * License handling (offline).
 *
 * @package HOA_COA_Portal_Pro
 */

defined( 'ABSPATH' ) || exit;

final class HCP_License {

    public const OPTION_KEY = 'hcp_pro_license_key';

    /**
     * Returns the stored license key.
     */
    public static function get_key(): string {
        $key = get_option( self::OPTION_KEY, '' );
        return is_string( $key ) ? trim( $key ) : '';
    }

    /**
     * Offline activation check.
     *
     * For CodeCanyon/offline validation, we treat a non-empty key as active.
     * You can swap this for a stricter format/checksum later without changing call sites.
     */
    public static function is_active(): bool {
        $key = self::get_key();
        return ( '' !== $key );
    }

    /**
     * Persist a new license key.
     */
    public static function set_key( string $key ): void {
        update_option( self::OPTION_KEY, trim( $key ), false );
    }

    /**
     * Clear stored key.
     */
    public static function clear(): void {
        delete_option( self::OPTION_KEY );
    }
}
