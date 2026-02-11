<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Centralized access control for HOA/COA Portal.
 *
 * Design goals:
 * - Never restrict Administrators.
 * - Use explicit plugin capabilities for staff/board.
 * - Voting eligibility is unit-based (Primary Voting Owner assigned).
 */
final class HCP_Access {

    public static function is_admin( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'administrator' );
    }

    public static function is_staff( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return self::is_admin( $user_id ) || user_can( $user_id, HCP_Caps::CAP_STAFF );
    }

    public static function is_board( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return self::is_admin( $user_id ) || user_can( $user_id, HCP_Caps::CAP_BOARD );
    }

    public static function can_manage_settings( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return self::is_admin( $user_id ) || user_can( $user_id, HCP_Caps::CAP_SETTINGS );
    }

    public static function can_manage_units( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return self::is_admin( $user_id ) || user_can( $user_id, HCP_Caps::CAP_MANAGE_UNITS );
    }

    public static function can_verify_owners( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return self::is_admin( $user_id ) || user_can( $user_id, HCP_Caps::CAP_VERIFY_OWNERS );
    }

    public static function can_manage_content( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return self::is_admin( $user_id ) || user_can( $user_id, HCP_Caps::CAP_MANAGE_CONTENT ) || user_can( $user_id, HCP_Caps::CAP_MANAGE );
    }

    public static function can_manage_elections( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return self::is_admin( $user_id ) || user_can( $user_id, HCP_Caps::CAP_MANAGE_ELECTIONS );
    }

    public static function can_finalize_elections( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        return self::is_admin( $user_id ) || user_can( $user_id, HCP_Caps::CAP_FINALIZE_ELECTIONS );
    }

    public static function can_view_portal( ?int $user_id = null ): bool {
        $user_id = $user_id ?? get_current_user_id();
        if ( self::is_admin( $user_id ) || self::is_staff( $user_id ) || self::is_board( $user_id ) ) {
            return true;
        }
        return user_can( $user_id, HCP_Caps::CAP_VIEW ) || user_can( $user_id, HCP_Caps::CAP_VIEW_OWNER_PORTAL );
    }

    /**
     * Whether user can vote for a given unit (unit-based eligibility).
     */
    public static function can_vote_for_unit( int $user_id, int $unit_id ): bool {
        if ( $unit_id <= 0 || $user_id <= 0 ) { return false; }
        if ( self::is_admin( $user_id ) || self::is_staff( $user_id ) || self::is_board( $user_id ) ) {
            // Admin/staff/board can run tests, but voting logic still enforces 1 ballot per unit.
            return true;
        }
        return HCP_Units::user_is_primary_owner( $user_id, $unit_id );
    }

    /**
     * Renters should not see Owner Documents in free core (per product decision).
     */
    public static function renters_can_view_owner_docs(): bool {
        return false;
    }
}
