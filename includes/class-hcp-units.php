<?php
/**
 * Units helper layer.
 *
 * Units are stored as the `hcp_unit` CPT with meta:
 *  - _hcp_primary_owner (int user ID)  Primary Voting Owner
 *  - _hcp_additional_owners (int[] user IDs) Additional/secondary owners (read-only access)
 *  - _hcp_unit_weight (float) Weight for unit-based tally (default 1)
 *  - _hcp_verified_status (string) verification state (optional)
 *
 * @package HOA_COA_Portal
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_Units {

    /**
     * Get unit IDs where the user is assigned (primary or additional owner).
     *
     * @return int[]
     */
    public static function get_user_units( int $user_id ): array {
        $user_id = absint( $user_id );
        if ( $user_id <= 0 ) {
            return array();
        }

        $primary    = self::get_primary_unit_ids_for_user( $user_id );
        $additional = self::get_additional_unit_ids_for_user( $user_id );

        $all = array_unique( array_merge( $primary, $additional ) );
        $all = array_values( array_filter( array_map( 'absint', $all ) ) );

        return $all;
    }

    /**
     * Get unit IDs where the user is the Primary Voting Owner.
     *
     * NOTE: This does NOT require verification. Verification is handled separately.
     *
     * @return int[]
     */
    public static function get_primary_unit_ids_for_user( int $user_id ): array {
        $user_id = absint( $user_id );
        if ( $user_id <= 0 ) {
            return array();
        }

        $ids = get_posts( array(
            'post_type'      => 'hcp_unit',
            'post_status'    => array( 'publish', 'private', 'draft' ),
            'fields'         => 'ids',
            'numberposts'    => 2000,
            'no_found_rows'  => true,
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_hcp_primary_owner',
                    'value'   => $user_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
                array(
                    'key'     => '_hcp_primary_owner_user_id',
                    'value'   => $user_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
        ) );

        return array_values( array_map( 'absint', (array) $ids ) );
    }

    /**
     * Get unit IDs where the user is the Primary Voting Owner AND the unit is verified.
     *
     * @return int[]
     */
    public static function get_verified_primary_unit_ids_for_user( int $user_id ): array {
        $user_id = absint( $user_id );
        if ( $user_id <= 0 ) {
            return array();
        }

        $ids = get_posts( array(
            'post_type'      => 'hcp_unit',
            'post_status'    => array( 'publish', 'private', 'draft' ),
            'fields'         => 'ids',
            'numberposts'    => 2000,
            'no_found_rows'  => true,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_hcp_primary_owner',
                        'value'   => $user_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                    array(
                        'key'     => '_hcp_primary_owner_user_id',
                        'value'   => $user_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                ),
                array(
                    'key'     => '_hcp_verified_status',
                    'value'   => array( 'verified_owner_affirmed', 'verified_board_assigned' ),
                    'compare' => 'IN',
                ),
            ),
        ) );

        return array_values( array_map( 'absint', (array) $ids ) );
    }

    /**
     * Get unit IDs where the user is listed as an additional owner.
     *
     * `_hcp_additional_owners` is stored as a serialized array.
     *
     * @return int[]
     */
    public static function get_additional_unit_ids_for_user( int $user_id ): array {
        $user_id = absint( $user_id );
        if ( $user_id <= 0 ) {
            return array();
        }

        // Serialized arrays contain quotes around integers: s:... or i:...
        // A safe LIKE pattern is to match the quoted integer for array values.
        $needle = '"' . $user_id . '"';

        $ids = get_posts( array(
            'post_type'      => 'hcp_unit',
            'post_status'    => array( 'publish', 'private', 'draft' ),
            'fields'         => 'ids',
            'numberposts'    => 2000,
            'no_found_rows'  => true,
            'meta_query'     => array(
                array(
                    'key'     => '_hcp_additional_owners',
                    'value'   => $needle,
                    'compare' => 'LIKE',
                ),
            ),
        ) );

        return array_values( array_map( 'absint', (array) $ids ) );
    }

    /**
     * True if user is the primary owner of the given unit.
     */
    public static function user_is_primary_owner( int $user_id, int $unit_id ): bool {
        $user_id = absint( $user_id );
        $unit_id = absint( $unit_id );
        if ( $user_id <= 0 || $unit_id <= 0 ) {
            return false;
        }
        $primary = absint( get_post_meta( $unit_id, '_hcp_primary_owner', true ) );
        if ( $primary <= 0 ) {
            $primary = absint( get_post_meta( $unit_id, '_hcp_primary_owner_user_id', true ) );
        }
        return $primary > 0 && $primary === $user_id;
    }

    /**
     * Verification status for a unit.
     *
     * Values:
     *  - unverified (default)
     *  - verified_owner_affirmed
     *  - verified_board_assigned
     */
    public static function get_verification_status( int $unit_id ): string {
        $unit_id = absint( $unit_id );
        if ( $unit_id <= 0 ) {
            return 'unverified';
        }
        $s = (string) get_post_meta( $unit_id, '_hcp_verified_status', true );
        if ( '' === $s ) {
            $s = 'unverified';
        }
        if ( ! in_array( $s, array( 'unverified', 'verified_owner_affirmed', 'verified_board_assigned' ), true ) ) {
            $s = 'unverified';
        }
        return $s;
    }

    /**
     * Whether a unit is verified (owner-affirmed or board-assigned).
     */
    public static function unit_is_verified( int $unit_id ): bool {
        $s = self::get_verification_status( $unit_id );
        return in_array( $s, array( 'verified_owner_affirmed', 'verified_board_assigned' ), true );
    }

    /**
     * Set verification status + audit trail fields.
     */
    public static function set_verification( int $unit_id, string $status, int $by_user_id, string $method ): void {
        $unit_id    = absint( $unit_id );
        $by_user_id = absint( $by_user_id );

        if ( $unit_id <= 0 ) {
            return;
        }

        if ( ! in_array( $status, array( 'unverified', 'verified_owner_affirmed', 'verified_board_assigned' ), true ) ) {
            $status = 'unverified';
        }

        update_post_meta( $unit_id, '_hcp_verified_status', $status );
        update_post_meta( $unit_id, '_hcp_verified_at', time() );
        update_post_meta( $unit_id, '_hcp_verified_by', $by_user_id );
        update_post_meta( $unit_id, '_hcp_verified_method', sanitize_text_field( $method ) );
    }

    /**
     * Get a unit's weight (default 1.0).
     */
    public static function get_unit_weight( int $unit_id ): float {
        $unit_id = absint( $unit_id );
        if ( $unit_id <= 0 ) {
            return 1.0;
        }
        $w = (float) get_post_meta( $unit_id, '_hcp_unit_weight', true );
        return $w > 0 ? $w : 1.0;
    }

    /**
     * Eligible units for voting and quorum calculations.
     *
     * Free core rule:
     * - Eligible = only units with a Primary Voting Owner assigned.
     *
     * @return int[]
     */
    public static function get_eligible_unit_ids(): array {
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key'     => '_hcp_primary_owner',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
            array(
                'key'     => '_hcp_primary_owner_user_id',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
        );

        /**
         * Filter the eligibility meta query.
         *
         * @param array $meta_query Meta query array.
         */
        $meta_query = apply_filters( 'hcp_eligible_units_meta_query', $meta_query );

        $ids = get_posts( array(
            'post_type'      => 'hcp_unit',
            'post_status'    => array( 'publish', 'private', 'draft' ),
            'fields'         => 'ids',
            'numberposts'    => 5000,
            'no_found_rows'  => true,
            'meta_query'     => $meta_query,
        ) );

        return array_values( array_map( 'absint', (array) $ids ) );
    }

    /**
     * Sum weights across provided unit IDs.
     */
    public static function get_total_weight_for_units( array $unit_ids ): float {
        $total = 0.0;
        foreach ( $unit_ids as $uid ) {
            $uid = absint( $uid );
            if ( $uid <= 0 ) { continue; }
            $total += self::get_unit_weight( $uid );
        }
        return (float) $total;
    }

    /**
     * Get a unit "number" (display label) for a unit post.
     * Falls back to post title if no meta set.
     */
    public static function get_unit_number( int $unit_id ): string {
        $unit_id = absint( $unit_id );
        if ( $unit_id <= 0 ) {
            return '';
        }
        $num = (string) get_post_meta( $unit_id, '_hcp_unit_number', true );
        $num = trim( $num );
        if ( '' !== $num ) {
            return $num;
        }
        return (string) get_the_title( $unit_id );
    }

}
