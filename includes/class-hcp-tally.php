<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Election tally + quorum calculations.
 *
 * Free version:
 * - Choices: yes/no/abstain
 * - Quorum: percentage-based, measured by Units or Weight
 * - Weight snapshot stored on vote submit (_hcp_unit_weight)
 */
final class HCP_Tally {
public static function is_election_finalized( int $election_id ): bool {
    return (bool) get_post_meta( $election_id, '_hcp_finalized', true );
}

public static function finalize_election( int $election_id, int $by_user_id ): void {
    if ( self::is_election_finalized( $election_id ) ) {
        return;
    }
    update_post_meta( $election_id, '_hcp_finalized', 1 );
    update_post_meta( $election_id, '_hcp_finalized_at', time() );
    update_post_meta( $election_id, '_hcp_finalized_by', $by_user_id );
}


    public static function hash_vote_record( array $record, string $prev_hash = '' ): string {
        ksort( $record );
        return hash( 'sha256', wp_json_encode( $record ) . $prev_hash );
    }


    /**
     * Returns a structured tally for an election.
     *
     * @return array{
     *   election_id:int,
     *   quorum_mode:string,
     *   quorum_percent:float,
     *   eligible_units:int,
     *   eligible_weight:float,
     *   voted_units:int,
     *   voted_weight:float,
     *   required_units:int,
     *   required_weight:float,
     *   quorum_met:bool,
     *   by_choice_units:array<string,int>,
     *   by_choice_weight:array<string,float>
     * }
     */
    public static function get( int $election_id ): array {
        $election = get_post( $election_id );
        if ( ! $election || 'hcp_election' !== $election->post_type ) {
            return self::empty( $election_id );
        }

        $mode = (string) get_post_meta( $election_id, '_hcp_quorum_mode', true );
        if ( '' === $mode ) { $mode = 'units'; }
        if ( ! in_array( $mode, array( 'units', 'weight' ), true ) ) { $mode = 'units'; }

        $percent = (float) get_post_meta( $election_id, '_hcp_quorum_percent', true );
        if ( $percent < 0 ) { $percent = 0; }
        if ( $percent > 100 ) { $percent = 100; }

        $eligible_unit_ids = HCP_Units::get_eligible_unit_ids();
        $eligible_units = count( $eligible_unit_ids );
        $eligible_weight = HCP_Units::get_total_weight_for_units( $eligible_unit_ids );

        $by_units = array( 'yes' => 0, 'no' => 0, 'abstain' => 0 );
        $by_weight = array( 'yes' => 0.0, 'no' => 0.0, 'abstain' => 0.0 );

        $voted_units = 0;
        $voted_weight = 0.0;

        $votes = get_posts( array(
            'post_type'      => 'hcp_vote',
            'post_status'    => array( 'private', 'publish', 'draft' ),
            'numberposts'    => -1,
            'fields'         => 'ids',
            'post_parent'    => $election_id,
            'no_found_rows'  => true,
        ) );

        // Deduplicate by unit just in case (should be enforced by submit handler).
        $seen_units = array();

        foreach ( $votes as $vid ) {
            $choice = (string) get_post_meta( (int) $vid, '_hcp_choice', true );
            if ( ! isset( $by_units[ $choice ] ) ) {
                continue;
            }

            $unit_id = (int) get_post_meta( (int) $vid, '_hcp_unit_id', true );
            if ( $unit_id <= 0 ) {
                continue;
            }
            if ( isset( $seen_units[ $unit_id ] ) ) {
                continue;
            }
            $seen_units[ $unit_id ] = true;

            $weight = (float) get_post_meta( (int) $vid, '_hcp_unit_weight', true );
            if ( $weight <= 0 ) {
                $weight = HCP_Units::get_unit_weight( $unit_id );
            }

            $by_units[ $choice ]++;
            $by_weight[ $choice ] += $weight;

            $voted_units++;
            $voted_weight += $weight;
        }

        $required_units = 0;
        $required_weight = 0.0;

        if ( $percent > 0 ) {
            if ( 'units' === $mode ) {
                $required_units = (int) ceil( ( $percent / 100.0 ) * (float) $eligible_units );
            } else {
                $required_weight = ( $percent / 100.0 ) * $eligible_weight;
            }
        }

        $quorum_met = false;
        if ( 0.0 === $percent ) {
            $quorum_met = true;
        } elseif ( 'units' === $mode ) {
            $quorum_met = $eligible_units > 0 ? ( $voted_units >= $required_units ) : false;
        } else {
            $quorum_met = $eligible_weight > 0 ? ( $voted_weight >= $required_weight ) : false;
        }

        return array(
            'election_id' => $election_id,
            'quorum_mode' => $mode,
            'quorum_percent' => $percent,
            'eligible_units' => $eligible_units,
            'eligible_weight' => (float) $eligible_weight,
            'voted_units' => (int) $voted_units,
            'voted_weight' => (float) $voted_weight,
            'required_units' => (int) $required_units,
            'required_weight' => (float) $required_weight,
            'quorum_met' => (bool) $quorum_met,
            'by_choice_units' => $by_units,
            'by_choice_weight' => $by_weight,
        );
    }

    private static function empty( int $election_id ): array {
        return array(
            'election_id' => $election_id,
            'quorum_mode' => 'units',
            'quorum_percent' => 0.0,
            'eligible_units' => 0,
            'eligible_weight' => 0.0,
            'voted_units' => 0,
            'voted_weight' => 0.0,
            'required_units' => 0,
            'required_weight' => 0.0,
            'quorum_met' => false,
            'by_choice_units' => array( 'yes' => 0, 'no' => 0, 'abstain' => 0 ),
            'by_choice_weight' => array( 'yes' => 0.0, 'no' => 0.0, 'abstain' => 0.0 ),
        );
    }
public static function get_election_audit_summary( int $election_id ): array {
    $summary = array(
        'election_id'      => $election_id,
        'title'            => get_the_title( $election_id ),
        'finalized'        => self::is_election_finalized( $election_id ),
        'finalized_at'     => (int) get_post_meta( $election_id, '_hcp_finalized_at', true ),
        'finalized_by'     => (int) get_post_meta( $election_id, '_hcp_finalized_by', true ),
        'snapshot_hash'    => (string) get_post_meta( $election_id, '_hcp_snapshot_hash', true ),
        'quorum_required'  => (float) get_post_meta( $election_id, '_hcp_quorum_percent', true ),
        'eligible_units'   => (int) self::count_eligible_units( $election_id ),
        'votes_cast'       => (int) self::count_votes_cast( $election_id ),
        'quorum_met'       => false,
        'results'          => array(),
    );

    $summary['quorum_met'] = self::quorum_met( $summary['votes_cast'], $summary['eligible_units'], $summary['quorum_required'] );

    // If there's existing weighted tally method, use it; otherwise compute basic counts from votes meta.
    if ( method_exists( __CLASS__, 'tally_weighted_results' ) ) {
        // @phpstan-ignore-next-line
        $summary['results'] = self::tally_weighted_results( $election_id );
    } else {
        $summary['results'] = self::tally_basic_results( $election_id );
    }

    return $summary;
}

public static function quorum_met( int $votes_cast, int $eligible_units, float $quorum_percent ): bool {
    if ( $eligible_units <= 0 ) { return false; }
    if ( $quorum_percent <= 0 ) { return true; }
    $pct = ( $votes_cast / $eligible_units ) * 100.0;
    return $pct >= $quorum_percent;
}

public static function count_votes_cast( int $election_id ): int {
    $q = new \WP_Query( array(
        'post_type'      => 'hcp_vote',
        'post_status'    => array( 'publish', 'private' ),
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'     => '_hcp_election_id',
                'value'   => $election_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ) );
    return (int) $q->found_posts;
}

public static function count_eligible_units( int $election_id ): int {
    // Eligible = units with Primary Voting Owner assigned (per your rule).
    // If units module provides a count helper, use it.
    if ( class_exists( 'HCP_Units' ) && method_exists( 'HCP_Units', 'count_units_with_primary_owner' ) ) {
        return (int) HCP_Units::count_units_with_primary_owner();
    }

    // Fallback: scan unit posts if they exist.
    $q = new \WP_Query( array(
        'post_type'      => 'hcp_unit',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
    ) );
    $count = 0;
    foreach ( $q->posts as $unit_id ) {
        $uid = (int) get_post_meta( (int) $unit_id, '_hcp_primary_owner_user_id', true );
        if ( $uid > 0 ) { $count++; }
    }
    return $count;
}

public static function tally_basic_results( int $election_id ): array {
    $votes = get_posts( array(
        'post_type'      => 'hcp_vote',
        'post_status'    => array( 'publish', 'private' ),
        'numberposts'    => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'     => '_hcp_election_id',
                'value'   => $election_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ) );

    $counts = array(); // question_key => choice => count
    foreach ( $votes as $vote_id ) {
        $answers_json = (string) get_post_meta( (int) $vote_id, '_hcp_answers', true );
        if ( '' === $answers_json ) { continue; }
        $answers = json_decode( $answers_json, true );
        if ( ! is_array( $answers ) ) { continue; }
        foreach ( $answers as $q_key => $choice ) {
            if ( ! isset( $counts[ $q_key ] ) ) { $counts[ $q_key ] = array(); }
            $choice_key = is_scalar( $choice ) ? (string) $choice : wp_json_encode( $choice );
            $counts[ $q_key ][ $choice_key ] = ( $counts[ $q_key ][ $choice_key ] ?? 0 ) + 1;
        }
    }
    return $counts;
}


}
