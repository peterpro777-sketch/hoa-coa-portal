<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_Caps {
    // Legacy caps used across the plugin.
    public const CAP_VIEW   = 'hcp_view_portal';
    public const CAP_VOTE   = 'hcp_vote';
    public const CAP_MANAGE = 'hcp_manage_portal';
    public const CAP_MANAGE_UNITS = 'hcp_manage_units';

    // New granular caps (free core).
    public const CAP_SETTINGS          = 'hcp_manage_settings';
    public const CAP_VERIFY_OWNERS     = 'hcp_verify_owners';
    public const CAP_MANAGE_CONTENT    = 'hcp_manage_content';
    public const CAP_MANAGE_ELECTIONS  = 'hcp_manage_elections';
    public const CAP_FINALIZE_ELECTIONS= 'hcp_finalize_elections';

    // Role markers.
    public const CAP_STAFF = 'hcp_is_staff';
    public const CAP_BOARD = 'hcp_is_board';

    // Portal view variants.
    public const CAP_VIEW_OWNER_PORTAL = 'hcp_view_owner_portal';
    public const CAP_VIEW_RENTER_PORTAL= 'hcp_view_renter_portal';

    public static function hooks(): void {
        // Intentionally empty: avoid mutating roles/caps on every request.
    }

    private static function ensure_admin_caps(): void {
        $role = get_role( 'administrator' );
        if ( ! $role ) { return; }
        foreach ( self::all_caps_for_admin() as $cap ) {
            $role->add_cap( $cap );
        }
    }

    private static function all_caps_for_admin(): array {
        return array(
            self::CAP_VIEW,
            self::CAP_VOTE,
            self::CAP_MANAGE,
            self::CAP_MANAGE_UNITS,
            self::CAP_SETTINGS,
            self::CAP_VERIFY_OWNERS,
            self::CAP_MANAGE_CONTENT,
            self::CAP_MANAGE_ELECTIONS,
            self::CAP_FINALIZE_ELECTIONS,
            self::CAP_STAFF,
            self::CAP_BOARD,
            self::CAP_VIEW_OWNER_PORTAL,
            self::CAP_VIEW_RENTER_PORTAL,
        );
    }

    public static function activate(): void {
        // Owners: view portal; voting is unit-based (Primary Voting Owner assigned).
        if ( null === get_role( 'hcp_owner' ) ) {
            add_role(
                'hcp_owner',
                __( 'HOA/COA Owner', 'hoa-coa-portal-pro' ),
                array(
                    'read'                     => true,
                    self::CAP_VIEW             => true,
                    self::CAP_VIEW_OWNER_PORTAL=> true,
                    // Keep legacy CAP_VOTE for backwards compatibility; actual eligibility is enforced by unit assignment.
                    self::CAP_VOTE             => true,
                )
            );
        }

        // Staff: can manage units, verification, and content. Cannot create elections or finalize results (product decision).
        $staff_caps = array(
            'read'                  => true,
            self::CAP_VIEW          => true,
            self::CAP_VIEW_OWNER_PORTAL => true,
            self::CAP_MANAGE        => true,
            self::CAP_MANAGE_UNITS  => true,
            self::CAP_VERIFY_OWNERS => true,
            self::CAP_MANAGE_CONTENT=> true,
            self::CAP_STAFF         => true,
        );

        // Migrate/ensure legacy role slug.
        if ( null === get_role( 'hcp_office' ) ) {
            add_role( 'hcp_office', __( 'HOA/COA Staff', 'hoa-coa-portal-pro' ), $staff_caps );
        } else {
            $r = get_role( 'hcp_office' );
            if ( $r ) {
                foreach ( $staff_caps as $cap => $grant ) {
                    if ( $grant ) { $r->add_cap( $cap ); }
                }
            }
        }

        // Board role: can manage elections and finalize results.
        $board_caps = array(
            'read'                       => true,
            self::CAP_VIEW               => true,
            self::CAP_VIEW_OWNER_PORTAL  => true,
            self::CAP_MANAGE_ELECTIONS   => true,
            self::CAP_FINALIZE_ELECTIONS => true,
            self::CAP_BOARD              => true,
        );

        if ( null === get_role( 'hcp_board' ) ) {
            add_role( 'hcp_board', __( 'HOA/COA Board Member', 'hoa-coa-portal-pro' ), $board_caps );
        } else {
            $r = get_role( 'hcp_board' );
            if ( $r ) {
                foreach ( $board_caps as $cap => $grant ) {
                    if ( $grant ) { $r->add_cap( $cap ); }
                }
            }
        }

        // Administrators should always be fully capable.
        self::ensure_admin_caps();
    }
}
