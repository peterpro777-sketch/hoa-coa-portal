<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_Frontend {
    public static function hooks(): void {
        add_shortcode( 'hcp_portal', array( __CLASS__, 'shortcode_portal' ) );

        // Back-compat / friendly alias.
        add_shortcode( 'hoa_coa_portal', array( __CLASS__, 'shortcode_portal' ) );

        add_action( 'admin_post_hcp_submit_vote', array( __CLASS__, 'submit_vote' ) );
        add_action( 'admin_post_nopriv_hcp_submit_vote', array( __CLASS__, 'submit_vote' ) );

        // Owner verification (Primary Voting Owner affirmation).
        add_action( 'admin_post_hcp_owner_verify', array( __CLASS__, 'owner_verify' ) );
        add_action( 'admin_post_nopriv_hcp_owner_verify', array( __CLASS__, 'owner_verify' ) );
    }

    public static function shortcode_portal( array $atts = array() ): string {
        HCP_Helpers::require_login_or_redirect();

        if ( ! HCP_Helpers::can_view_portal() ) {
            return HCP_Helpers::not_authorized_message();
        }

        $tabs = array(
            'dashboard' => __( 'Dashboard', 'hoa-coa-portal-pro' ),
            'notices'   => __( 'Notices', 'hoa-coa-portal-pro' ),
            'minutes'   => __( 'Minutes', 'hoa-coa-portal-pro' ),
            'agendas'   => __( 'Agendas', 'hoa-coa-portal-pro' ),
            'compliance_docs' => __( 'Compliance', 'hoa-coa-portal-pro' ),
            'voting'    => __( 'Voting', 'hoa-coa-portal-pro' ),
            'documents' => __( 'Documents', 'hoa-coa-portal-pro' ),
            'owner_access' => __( 'Owner Access', 'hoa-coa-portal-pro' ),
        );

        
        // Tab badges (lightweight counts for better triage).
        $tab_badges = array();
        $uid = get_current_user_id();

        


		// Role flags (used for gating tabs, badges, and portal actions).
		$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'hcp_manage_portal' );
		$is_staff = current_user_can( 'hcp_staff' ) || current_user_can( 'hcp_manage_portal' );
		$is_owner = is_user_logged_in() && ( ! empty( HCP_Units::get_primary_unit_ids_for_user( $uid ) ) ); // primary owner of at least one unit.
// Totals (published) – cheap via wp_count_posts.
        $notices_counts = wp_count_posts( 'hcp_notice' );
        $minutes_counts = wp_count_posts( 'hcp_minutes' );
        $agendas_counts = wp_count_posts( 'hcp_agenda' );

        $tab_badges['notices'] = isset( $notices_counts->publish ) ? (int) $notices_counts->publish : 0;
        $tab_badges['minutes'] = isset( $minutes_counts->publish ) ? (int) $minutes_counts->publish : 0;
        $tab_badges['agendas'] = isset( $agendas_counts->publish ) ? (int) $agendas_counts->publish : 0;

        // If office staff/admin, show "assigned to me" badges for Minutes/Agendas (more useful than total).
        if ( $is_admin || $is_staff ) {
            $assigned_counts = array();

            foreach ( array( 'hcp_agenda' => 'agendas', 'hcp_minutes' => 'minutes' ) as $pt => $key ) {
                $q = new WP_Query(
                    array(
                        'post_type'      => $pt,
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'no_found_rows'  => false,
                        'meta_query'     => array(
                            array(
                                'key'     => '_hcp_assigned_to',
                                'value'   => $uid,
                                'compare' => '=',
                                'type'    => 'NUMERIC',
                            ),
                        ),
                    )
                );
                $assigned_counts[ $key ] = (int) $q->found_posts;
                wp_reset_postdata();
            }

            // Replace totals with assigned-to-me for staff context (still available in tooltip/title).
            $tab_badges['agendas'] = $assigned_counts['agendas'];
            $tab_badges['minutes'] = $assigned_counts['minutes'];
        }

        // Voting badge: open elections (publish) – optional.
        $votes_counts = wp_count_posts( 'hcp_election' );
        $tab_badges['voting'] = isset( $votes_counts->publish ) ? (int) $votes_counts->publish : 0;

$uid = get_current_user_id();
		$is_staff = HCP_Helpers::can_manage();
		$is_owner = HCP_Helpers::can_vote(); // owners have vote cap, office doesn't
		$primary_unit_ids = HCP_Units::get_primary_unit_ids_for_user( $uid );
		$has_voting_units = $is_owner && ! empty( $primary_unit_ids );
		
		$show_voting_tab  = ( $is_staff || $is_owner );
ob_start();
echo '<div class="hcp-portal" data-user="' . esc_attr( (string) $uid ) . '">';
        echo wp_kses_post( self::render_portal_header() );
        echo '<div class="hcp-tabs" role="tablist">';
        foreach ( $tabs as $id => $label ) {
			if ( 'voting' === $id && ! $show_voting_tab ) {
                continue;
            }
            $panel_id = 'hcp-panel-' . $id;
            
            $badge = isset( $tab_badges[ $id ] ) ? (int) $tab_badges[ $id ] : 0;
            $title = '';
            if ( ( $is_admin || $is_staff ) && in_array( $id, array( 'agendas', 'minutes' ), true ) ) {
                $title = __( 'Badge shows items assigned to you', 'hoa-coa-portal-pro' );
            } elseif ( 'voting' === $id ) {
                $title = __( 'Badge shows published elections', 'hoa-coa-portal-pro' );
            } else {
                $title = __( 'Badge shows published items', 'hoa-coa-portal-pro' );
            }

            echo '<button type="button" class="hcp-tab" role="tab" aria-selected="false" data-panel="' . esc_attr( $panel_id ) . '" title="' . esc_attr( $title ) . '">'
                . esc_html( $label )
                . ( $badge > 0 ? ' <span class="hcp-tab__badge" aria-label="' . esc_attr__( 'Count', 'hoa-coa-portal-pro' ) . '">' . (int) $badge . '</span>' : '' )
                . '</button>';

        }
        echo '</div>';

        $before_panels_len = (int) ob_get_length();

        self::panel_dashboard();
        self::panel_notices();
        self::panel_minutes();
        self::panel_agendas();
        self::panel_compliance_docs();
        if ( $show_voting_tab ) {
            if ( $has_voting_units ) {
                self::panel_voting();
            } else {
                self::panel_voting_unassigned();
            }
        }

        $after_panels_len = (int) ob_get_length();
        if ( $after_panels_len <= ( $before_panels_len + 50 ) ) {
            echo wp_kses_post( self::render_access_pending() );
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function render_portal_header(): string {
        $s = HCP_Helpers::settings();
        $name = trim( (string) $s['assoc_name'] );
        $logo_id = (int) $s['assoc_logo_id'];
        $logo_html = '';
        if ( $logo_id ) {
            $logo_html = wp_get_attachment_image( $logo_id, 'medium', false, array(
                'class' => 'hcp-assoc-logo',
                'alt'   => $name ? $name : __( 'Association logo', 'hoa-coa-portal-pro' ),
                'loading' => 'lazy',
            ) );
        }

        $meta_bits = array();
        if ( ! empty( $s['assoc_website'] ) ) {
            $meta_bits[] = '<a href="' . esc_url( (string) $s['assoc_website'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Website', 'hoa-coa-portal-pro' ) . '</a>';
        }
        if ( ! empty( $s['assoc_email'] ) ) {
            $meta_bits[] = '<a href="mailto:' . antispambot( esc_attr( (string) $s['assoc_email'] ) ) . '">' . esc_html__( 'Email', 'hoa-coa-portal-pro' ) . '</a>';
        }
        if ( ! empty( $s['assoc_phone'] ) ) {
            $meta_bits[] = esc_html( (string) $s['assoc_phone'] );
        }

        $out = '<div class="hcp-assoc">';
        if ( $logo_html ) {
            $out .= '<div class="hcp-assoc-media">' . $logo_html . '</div>';
        }
        $out .= '<div class="hcp-assoc-body">';
        $out .= '<div class="hcp-assoc-kicker">' . esc_html__( 'Community Portal', 'hoa-coa-portal-pro' ) . '</div>';
        $out .= '<div class="hcp-assoc-name">' . esc_html( $name ? $name : __( 'Your HOA/COA', 'hoa-coa-portal-pro' ) ) . '</div>';
        if ( ! empty( $s['assoc_address'] ) ) {
            $out .= '<div class="hcp-assoc-address">' . nl2br( esc_html( (string) $s['assoc_address'] ) ) . '</div>';
        }
        if ( ! empty( $meta_bits ) ) {
            $out .= '<div class="hcp-assoc-meta">' . implode( ' • ', $meta_bits ) . '</div>';
        }
        $out .= '</div></div>';
        return $out;
    }

    private static function panel_dashboard(): void {
        echo '<div class="hcp-panel is-active" id="hcp-panel-dashboard">';
        echo '<div class="hcp-card"><h3>' . esc_html__( 'Welcome', 'hoa-coa-portal-pro' ) . '</h3><p>' . esc_html__( 'Use the tabs to view notices, meetings, and voting.', 'hoa-coa-portal-pro' ) . '</p></div>';

        // Featured notice
        $featured = get_posts(
            array(
                'post_type'      => 'hcp_notice',
                'post_status'    => 'publish',
                'numberposts'    => 1,
                'meta_key'       => '_hcp_featured',
                'meta_value'     => 1,
            )
        );
        if ( ! empty( $featured ) ) {
            $n = $featured[0];
            if ( isset( $n->ID ) && self::can_view_post_by_audience( (int) $n->ID ) ) {
                echo '<div class="hcp-card"><h3>' . esc_html__( 'Featured Notice', 'hoa-coa-portal-pro' ) . '</h3>';
                echo '<h4>' . esc_html( $n->post_title ) . '</h4>';
                echo wp_kses_post( wpautop( $n->post_content ) );
                self::render_attachments( (int) $n->ID );
                echo '</div>';
            }
        }

        // Verification prompt (Primary Voting Owners only)
        $uid = (int) get_current_user_id();
        if ( $uid > 0 && HCP_Helpers::can_vote() ) {
            $unit_ids = HCP_Units::get_primary_unit_ids_for_user( $uid );
            $needs    = array();
            foreach ( $unit_ids as $u ) {
                $u = (int) $u;
                if ( $u > 0 && ! HCP_Units::unit_is_verified( $u ) ) {
                    $needs[] = $u;
                }
            }

            if ( ! empty( $needs ) ) {
                $unit_id = (int) $needs[0];
                echo '<div class="hcp-card">';
                echo '<h3>' . esc_html__( 'Verification Required', 'hoa-coa-portal-pro' ) . '</h3>';
                echo '<p>' . esc_html__( 'Before you can vote, your Primary Voting Owner status must be verified for your unit.', 'hoa-coa-portal-pro' ) . '</p>';
                echo '<p><strong>' . esc_html__( 'Unit:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html( HCP_Units::get_unit_number( $unit_id ) ) . '</p>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                echo '<input type="hidden" name="action" value="hcp_owner_verify" />';
                echo '<input type="hidden" name="unit_id" value="' . esc_attr( (string) $unit_id ) . '" />';
                wp_nonce_field( 'hcp_owner_verify' );
                echo '<label style="display:block;margin:10px 0;"><input type="checkbox" required /> ' . esc_html__( 'I affirm that I am the authorized Primary Voting Owner for this unit.', 'hoa-coa-portal-pro' ) . '</label>';
                echo '<button class="button button-primary" type="submit">' . esc_html__( 'Verify Now', 'hoa-coa-portal-pro' ) . '</button>';
                echo '</form>';
                echo '</div>';
            }
        }

        echo '</div>';
    }

    private static function panel_documents(): string {
        $out = '<div class="hcp-panel">';
        $out .= '<h2>' . esc_html__( 'Association Documents', 'hoa-coa-portal-pro' ) . '</h2>';
        $out .= '<p class="description">' . esc_html__( 'This library helps your association meet Florida online records requirements. Upload and categorize official documents in the admin area, then owners can access them here.', 'hoa-coa-portal-pro' ) . '</p>';

        $terms = get_terms(
            array(
                'taxonomy'   => HCP_Compliance::TAX,
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            $out .= '<div class="notice notice-warning inline"><p>' . esc_html__( 'No document categories found yet. An admin can visit Portal → Compliance and run the seed tool, or upload documents and assign categories.', 'hoa-coa-portal-pro' ) . '</p></div>';
            $out .= '</div>';
            return $out;
        }

        foreach ( $terms as $term ) {
            $q = new WP_Query(
                array(
                    'post_type'      => HCP_Compliance::CPT,
                    'posts_per_page' => 20,
                    'orderby'        => 'modified',
                    'order'          => 'DESC',
                    'tax_query'      => array(
                        array(
                            'taxonomy' => HCP_Compliance::TAX,
                            'field'    => 'term_id',
                            'terms'    => (int) $term->term_id,
                        ),
                    ),
                )
            );

            $out .= '<div class="hcp-card hcp-mt">';
            $out .= '<div class="hcp-card__header"><strong>' . esc_html( $term->name ) . '</strong>';
            $out .= '<span class="hcp-badge hcp-badge--muted" title="' . esc_attr__( 'Number of documents in this category', 'hoa-coa-portal-pro' ) . '">' . esc_html( (string) (int) $q->found_posts ) . '</span>';
            $out .= '</div>';

            if ( ! $q->have_posts() ) {
                $out .= '<div class="hcp-card__body"><em>' . esc_html__( 'No documents uploaded yet.', 'hoa-coa-portal-pro' ) . '</em></div>';
                $out .= '</div>';
                continue;
            }

            $out .= '<div class="hcp-card__body"><div class="hcp-table-wrap"><table class="hcp-table"><thead><tr>';
            $out .= '<th>' . esc_html__( 'Document', 'hoa-coa-portal-pro' ) . '</th>';
            $out .= '<th>' . esc_html__( 'Last Updated', 'hoa-coa-portal-pro' ) . '</th>';
            $out .= '<th>' . esc_html__( 'Download', 'hoa-coa-portal-pro' ) . '</th>';
            $out .= '</tr></thead><tbody>';

            while ( $q->have_posts() ) {
                $q->the_post();
                $doc_id = (int) get_the_ID();
                $file_id = (int) get_post_meta( $doc_id, '_hcp_file_id', true );
                $file_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
                $out .= '<tr>';
                $out .= '<td>' . esc_html( get_the_title() ) . '</td>';
                $out .= '<td>' . esc_html( get_the_modified_date( 'Y-m-d' ) ) . '</td>';
                if ( $file_url ) {
                    $out .= '<td><a class="button button-small" href="' . esc_url( $file_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open', 'hoa-coa-portal-pro' ) . '</a></td>';
                } else {
                    $out .= '<td><span class="hcp-muted">' . esc_html__( 'No file', 'hoa-coa-portal-pro' ) . '</span></td>';
                }
                $out .= '</tr>';
            }
            wp_reset_postdata();

            $out .= '</tbody></table></div></div></div>';
        }

        $out .= '</div>';
        return $out;
    }


    private static function panel_notices(): void {
        echo '<div class="hcp-panel" id="hcp-panel-notices">';
        $q = new WP_Query( array(
            'post_type'      => 'hcp_notice',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_query'     => array(
                array(
                    'key'     => '_hcp_pinned',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );
        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) {
                $q->the_post();
                $id = get_the_ID();
                if ( ! self::can_view_post_by_audience( $id ) ) { continue; }
                echo '<div class="hcp-card">';
                echo '<h3>' . esc_html( get_the_title() ) . '</h3>';
                $author = get_the_author();
                $assigned_to_id = (int) get_post_meta( $id, '_hcp_assigned_to', true );
                $assigned_to_name = '';
                if ( $assigned_to_id > 0 ) {
                    $u = get_userdata( $assigned_to_id );
                    if ( $u ) { $assigned_to_name = (string) $u->display_name; }
                }
                echo '<div><small>' . esc_html( get_the_date() ) . ' · ' . esc_html__( 'Assigned by', 'hoa-coa-portal-pro' ) . ' ' . esc_html( $author ) . '</small></div>';
                echo wp_kses_post( wpautop( get_the_content() ) );
                self::render_attachments( $id );
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            echo '<div class="hcp-msg"><em>' . esc_html__( 'No notices yet.', 'hoa-coa-portal-pro' ) . '</em></div>';
        }
        echo '</div>';
    }

    private static function panel_minutes(): void {
        echo '<div class="hcp-panel" id="hcp-panel-minutes">';

			$q = isset( $_GET['hcp_minutes_q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hcp_minutes_q'] ) ) : '';
			echo '<form class="hcp-search-row" method="get" action="">';
			foreach ( $_GET as $k => $v ) {
				if ( 'hcp_minutes_q' === $k ) { continue; }
				if ( is_array( $v ) ) { continue; }
				echo '<input type="hidden" name="' . esc_attr( (string) $k ) . '" value="' . esc_attr( (string) $v ) . '">';
			}
			echo '<label class="screen-reader-text" for="hcp_minutes_q">minutes search</label>';
			echo '<input id="hcp_minutes_q" class="hcp-search-input" type="search" name="hcp_minutes_q" placeholder="' . esc_attr__( 'Search…', 'hoa-coa-portal-pro' ) . '" value="' . esc_attr( $q ) . '">';
			echo '<button class="hcp-search-btn" type="submit">' . esc_html__( 'Search', 'hoa-coa-portal-pro' ) . '</button>';
			if ( '' !== $q ) {
				$clear = remove_query_arg( 'hcp_minutes_q' );
				echo '<a class="hcp-search-clear" href="' . esc_url( $clear ) . '">' . esc_html__( 'Clear', 'hoa-coa-portal-pro' ) . '</a>';
			}
			echo '</form>';

        $q = new WP_Query( array(
            'post_type'      => 'hcp_minutes',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_key'       => '_hcp_meeting_date',
            'orderby'        => array(
                'meta_value' => 'DESC',
                'date'       => 'DESC',
            ),
        ) );
        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) {
                $q->the_post();
                $id = get_the_ID();
                if ( ! self::can_view_post_by_audience( $id ) ) { continue; }
                $date = (string) get_post_meta( $id, '_hcp_meeting_date', true );
                echo '<div class="hcp-card">';
                echo '<h3 style="margin:0 0 6px 0;">' . esc_html( get_the_title() ) . '</h3>';
                $author = get_the_author();
                $assigned_to_id = (int) get_post_meta( $id, '_hcp_assigned_to', true );
                $assigned_to_name = '';
                if ( $assigned_to_id > 0 ) {
                    $u = get_userdata( $assigned_to_id );
                    if ( $u ) { $assigned_to_name = (string) $u->display_name; }
                }
                $bits = array();
                if ( $date ) { $bits[] = __( 'Meeting date:', 'hoa-coa-portal-pro' ) . ' ' . $date; }
                $bits[] = __( 'Assigned by', 'hoa-coa-portal-pro' ) . ' ' . $author;
                $posted = get_the_date();
                $bits2 = array();
                $bits2[] = __( 'Posted', 'hoa-coa-portal-pro' ) . ': ' . $posted;
                if ( $date ) { $bits2[] = __( 'Meeting date:', 'hoa-coa-portal-pro' ) . ' ' . $date; }
                $bits2[] = __( 'Assigned by', 'hoa-coa-portal-pro' ) . ' ' . $author;
                if ( '' !== $assigned_to_name ) { $bits2[] = __( 'Assigned to', 'hoa-coa-portal-pro' ) . ': ' . $assigned_to_name; }
                if ( '' !== $assigned_to_name ) { $bits2[] = __( 'Assigned to', 'hoa-coa-portal-pro' ) . ': ' . $assigned_to_name; }
                echo '<div class="hcp-item-meta">' . esc_html( implode( ' · ', $bits2 ) ) . '</div>';
                echo wp_kses_post( wpautop( get_the_content() ) );
                self::render_attachments( $id );
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            echo '<div class="hcp-msg"><em>' . esc_html__( 'No minutes yet.', 'hoa-coa-portal-pro' ) . '</em></div>';
        }
        echo '</div>';
    }

    private static function panel_agendas(): void {
        echo '<div class="hcp-panel" id="hcp-panel-agendas">';

			$q = isset( $_GET['hcp_agendas_q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hcp_agendas_q'] ) ) : '';
			echo '<form class="hcp-search-row" method="get" action="">';
			foreach ( $_GET as $k => $v ) {
				if ( 'hcp_agendas_q' === $k ) { continue; }
				if ( is_array( $v ) ) { continue; }
				echo '<input type="hidden" name="' . esc_attr( (string) $k ) . '" value="' . esc_attr( (string) $v ) . '">';
			}
			echo '<label class="screen-reader-text" for="hcp_agendas_q">agendas search</label>';
			echo '<input id="hcp_agendas_q" class="hcp-search-input" type="search" name="hcp_agendas_q" placeholder="' . esc_attr__( 'Search…', 'hoa-coa-portal-pro' ) . '" value="' . esc_attr( $q ) . '">';
			echo '<button class="hcp-search-btn" type="submit">' . esc_html__( 'Search', 'hoa-coa-portal-pro' ) . '</button>';
			if ( '' !== $q ) {
				$clear = remove_query_arg( 'hcp_agendas_q' );
				echo '<a class="hcp-search-clear" href="' . esc_url( $clear ) . '">' . esc_html__( 'Clear', 'hoa-coa-portal-pro' ) . '</a>';
			}
			echo '</form>';

        
        // Assignment sub-filters (All / Assigned to me / Unassigned).
        $assigned_filter = isset( $_GET['hcp_assigned'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hcp_assigned'] ) ) : '';
        $base_url = remove_query_arg( array( 'hcp_assigned' ) );
        $url_all = $base_url;
        $url_me  = add_query_arg( array( 'hcp_assigned' => 'me' ), $base_url );
        $url_un  = add_query_arg( array( 'hcp_assigned' => 'unassigned' ), $base_url );

        echo '<div class="hcp-subfilters">';
        echo '<a class="hcp-subfilters__link ' . ( '' === $assigned_filter ? 'is-active' : '' ) . '" href="' . esc_url( $url_all ) . '">' . esc_html__( 'All', 'hoa-coa-portal-pro' ) . '</a>';
        echo '<a class="hcp-subfilters__link ' . ( 'me' === $assigned_filter ? 'is-active' : '' ) . '" href="' . esc_url( $url_me ) . '">' . esc_html__( 'Assigned to me', 'hoa-coa-portal-pro' ) . '</a>';
        echo '<a class="hcp-subfilters__link ' . ( 'unassigned' === $assigned_filter ? 'is-active' : '' ) . '" href="' . esc_url( $url_un ) . '">' . esc_html__( 'Unassigned', 'hoa-coa-portal-pro' ) . '</a>';
        echo '</div>';

$q = new WP_Query( array(
            'post_type'      => 'hcp_agenda',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_key'       => '_hcp_meeting_date',
            'orderby'        => array(
                'meta_value' => 'DESC',
                'date'       => 'DESC',
            ),
        ) );
        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) {
                $q->the_post();
                $id = get_the_ID();
                if ( ! self::can_view_post_by_audience( $id ) ) { continue; }
                $date = (string) get_post_meta( $id, '_hcp_meeting_date', true );
                echo '<div class="hcp-card">';
                $assigned_to_id = (int) get_post_meta( $id, '_hcp_assigned_to', true );
                $assigned_to_name = '';
                if ( $assigned_to_id > 0 ) {
                    $u = get_userdata( $assigned_to_id );
                    if ( $u ) { $assigned_to_name = (string) $u->display_name; }
                }
                if ( '' !== $assigned_to_name ) {
                    echo '<div class="hcp-badge hcp-badge--assigned">' . esc_html__( 'Assigned', 'hoa-coa-portal-pro' ) . '</div>';
                }
                echo '<h3 style="margin:0 0 6px 0;">' . esc_html( get_the_title() ) . '</h3>';
                $author = get_the_author();
                $bits = array();
                if ( $date ) { $bits[] = __( 'Meeting date:', 'hoa-coa-portal-pro' ) . ' ' . $date; }
                $bits[] = __( 'Assigned by', 'hoa-coa-portal-pro' ) . ' ' . $author;
                $posted = get_the_date();
                $bits2 = array();
                $bits2[] = __( 'Posted', 'hoa-coa-portal-pro' ) . ': ' . $posted;
                if ( $date ) { $bits2[] = __( 'Meeting date:', 'hoa-coa-portal-pro' ) . ' ' . $date; }
                $bits2[] = __( 'Assigned by', 'hoa-coa-portal-pro' ) . ' ' . $author;
                if ( '' !== $assigned_to_name ) { $bits2[] = __( 'Assigned to', 'hoa-coa-portal-pro' ) . ': ' . $assigned_to_name; }
                echo '<div class="hcp-item-meta">' . esc_html( implode( ' · ', $bits2 ) ) . '</div>';
                echo wp_kses_post( wpautop( get_the_content() ) );
                self::render_attachments( $id );
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            echo '<div class="hcp-msg"><em>' . esc_html__( 'No agendas yet.', 'hoa-coa-portal-pro' ) . '</em></div>';
        }
        echo '</div>';
    }

    private static function panel_compliance_docs(): void {
        echo '<div id="hcp-panel-compliance_docs" class="hcp-panel" role="tabpanel" style="display:none;">';
        echo '<h2>' . esc_html__( 'Compliance', 'hoa-coa-portal-pro' ) . '</h2>';
        echo '<p class="description" style="margin-top:0;">' . esc_html__( 'Browse required records and compliance documents. Use filters to find what you need quickly.', 'hoa-coa-portal-pro' ) . '</p>';

        $uid = get_current_user_id();
        $is_admin = current_user_can( 'manage_options' ) || current_user_can( 'hcp_manage_portal' );
        $is_staff = current_user_can( 'hcp_staff' ) || current_user_can( 'hcp_manage_portal' );

        // Filters (server-side) – keep lightweight and safe.
        $selected_cat = isset( $_GET['hcp_cd_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['hcp_cd_cat'] ) ) : '';
        $search = isset( $_GET['hcp_cd_s'] ) ? sanitize_text_field( wp_unslash( $_GET['hcp_cd_s'] ) ) : '';

        $terms = get_terms(
            array(
                'taxonomy'   => HCP_Compliance::TAX,
                'hide_empty' => false,
            )
        );

        echo '<form class="hcp-filter" method="get" style="margin:14px 0 10px 0;">';
        // Preserve page vars while switching tab.
        foreach ( array( 'page_id', 'p', 'post', 'preview' ) as $k ) {
            if ( isset( $_GET[ $k ] ) ) {
                echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) ) . '" />';
            }
        }
        echo '<input type="hidden" name="hcp_panel" value="hcp-panel-compliance_docs" />';
        echo '<div class="hcp-filter-row">';
        echo '<label class="screen-reader-text" for="hcp_cd_cat">' . esc_html__( 'Category', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<select id="hcp_cd_cat" name="hcp_cd_cat">';
        echo '<option value="">' . esc_html__( 'All categories', 'hoa-coa-portal-pro' ) . '</option>';
        if ( is_array( $terms ) ) {
            foreach ( $terms as $t ) {
                if ( ! $t instanceof WP_Term ) { continue; }
                echo '<option value="' . esc_attr( (string) $t->slug ) . '" ' . selected( $selected_cat, (string) $t->slug, false ) . '>' . esc_html( (string) $t->name ) . '</option>';
            }
        }
        echo '</select>';

        echo '<label class="screen-reader-text" for="hcp_cd_s">' . esc_html__( 'Search', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_cd_s" name="hcp_cd_s" type="search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search documents…', 'hoa-coa-portal-pro' ) . '" />';

        echo '<button class="button" type="submit">' . esc_html__( 'Filter', 'hoa-coa-portal-pro' ) . '</button>';
        echo '<a class="button button-link-delete" href="' . esc_url( remove_query_arg( array( 'hcp_cd_cat', 'hcp_cd_s' ) ) ) . '">' . esc_html__( 'Reset', 'hoa-coa-portal-pro' ) . '</a>';
        echo '</div>';
        echo '</form>';

        $tax_query = array();
        if ( '' !== $selected_cat ) {
            $tax_query[] = array(
                'taxonomy' => HCP_Compliance::TAX,
                'field'    => 'slug',
                'terms'    => array( $selected_cat ),
            );
        }

        // Visibility gating. Admins see all; staff sees owners+staff; owners see owners only.
        $allowed_visibility = array( 'owners' );
        if ( $is_staff ) { $allowed_visibility[] = 'staff'; }
        if ( $is_admin ) { $allowed_visibility = array( 'owners', 'staff', 'board' ); }

        $meta_query = array(
            array(
                'key'     => '_hcp_visibility',
                'value'   => $allowed_visibility,
                'compare' => 'IN',
            ),
        );

        $args = array(
            'post_type'      => HCP_Compliance::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
            's'              => $search,
            'tax_query'      => $tax_query,
            'meta_query'     => $meta_query,
        );

        $q = new \WP_Query( $args );

        if ( ! $q->have_posts() ) {
            echo '<p class="description">' . esc_html__( 'No matching documents were found.', 'hoa-coa-portal-pro' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="hcp-grid">';

        while ( $q->have_posts() ) {
            $q->the_post();
            $id = (int) get_the_ID();

            $assigned_to_name = ''; // keep compatibility with existing card template patterns.
            $terms_for_post = get_the_terms( $id, HCP_Compliance::TAX );
            $cat = ( is_array( $terms_for_post ) && ! empty( $terms_for_post ) ) ? (string) $terms_for_post[0]->name : __( 'Uncategorized', 'hoa-coa-portal-pro' );

            $received = (string) get_post_meta( $id, '_hcp_received_date', true );
            $redacted = (string) get_post_meta( $id, '_hcp_redacted_confirmed', true );
            $author_id = (int) get_post_field( 'post_author', $id );
            $author_name = $author_id ? (string) get_the_author_meta( 'display_name', $author_id ) : '';

            echo '<div class="hcp-card">';
            echo '<div class="hcp-badge">' . esc_html( $cat ) . '</div>';
            echo '<h3 style="margin:10px 0 6px 0;">' . esc_html( get_the_title() ) . '</h3>';

            echo '<div class="hcp-meta">';
            echo '<span>' . esc_html__( 'Posted', 'hoa-coa-portal-pro' ) . ': ' . esc_html( get_the_date() ) . '</span>';
            if ( $author_name ) {
                echo ' • <span>' . esc_html__( 'By', 'hoa-coa-portal-pro' ) . ': ' . esc_html( $author_name ) . '</span>';
            }
            if ( $received ) {
                echo ' • <span>' . esc_html__( 'Received', 'hoa-coa-portal-pro' ) . ': ' . esc_html( $received ) . '</span>';
            }
            if ( 'yes' === $redacted ) {
                echo ' • <span class="hcp-tip" data-tip="' . esc_attr__( 'Redaction confirmed by admin', 'hoa-coa-portal-pro' ) . '">✅</span>';
            }
            echo '</div>';

            $link = get_permalink( $id );
            echo '<p style="margin-top:10px;"><a class="button button-primary" href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'hoa-coa-portal-pro' ) . '</a></p>';
            echo '</div>';
        }

        wp_reset_postdata();

        echo '</div>';
        echo '</div>';
    }



    private static function panel_voting_unassigned(): void {
    echo '<div class="hcp-card">';
    echo '<h3>' . esc_html__( 'Voting Access Not Yet Set Up', 'hoa-coa-portal-pro' ) . '</h3>';
    echo '<p>' . esc_html__( 'Your account is not assigned as the Primary Voting Owner for any unit. Please contact the association office to be assigned, then complete Verification.', 'hoa-coa-portal-pro' ) . '</p>';
    echo '</div>';
}

private static function panel_voting(): void {
    echo '<div class="hcp-panel" id="hcp-panel-voting">';


    $filter = isset( $_GET['hcp_election_filter'] ) ? sanitize_key( (string) wp_unslash( $_GET['hcp_election_filter'] ) ) : 'all';
    $allowed_filters = array( 'all', 'open', 'closed', 'draft' );
    if ( ! in_array( $filter, $allowed_filters, true ) ) {
        $filter = 'all';
    }

    echo '<div class="hcp-filter-row">';
    echo '<span class="hcp-filter-label">' . esc_html__( 'Filter:', 'hoa-coa-portal-pro' ) . '</span> ';
    foreach ( $allowed_filters as $f ) {
        $url = add_query_arg( 'hcp_election_filter', $f );
        $active = ( $filter === $f ) ? ' is-active' : '';
        $label = strtoupper( $f );
        if ( 'all' === $f ) { $label = __( 'All', 'hoa-coa-portal-pro' ); }
        if ( 'open' === $f ) { $label = __( 'Open', 'hoa-coa-portal-pro' ); }
        if ( 'closed' === $f ) { $label = __( 'Closed', 'hoa-coa-portal-pro' ); }
        if ( 'draft' === $f ) { $label = __( 'Draft', 'hoa-coa-portal-pro' ); }
        echo '<a class="hcp-filter-pill' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</div>';


    if ( ! HCP_Helpers::can_vote() ) {
        if ( HCP_Helpers::can_manage() ) {
            echo '<div class="hcp-card">';
            echo '<h3>' . esc_html__( 'Voting', 'hoa-coa-portal-pro' ) . '</h3>';
			echo '<div style="margin-top:6px;">' . wp_kses_post( self::tooltip( __( 'Help', 'hoa-coa-portal-pro' ), __( 'View active elections, cast your unit’s ballot (single submit), and review results after finalization.', 'hoa-coa-portal-pro' ) ) ) . '</div>';
            echo '<p>' . esc_html__( 'Staff accounts do not cast ballots. Manage elections from WP Admin.', 'hoa-coa-portal-pro' ) . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }
        echo wp_kses_post( HCP_Helpers::not_authorized_message() );
        echo '</div>';
        return;
    }

    $uid = get_current_user_id();
        $is_staff = HCP_Helpers::can_manage();
    $unit_ids = HCP_Units::get_primary_unit_ids_for_user( $uid );

    if ( empty( $unit_ids ) ) {
        echo '<div class="hcp-card"><h3>' . esc_html__( 'Voting', 'hoa-coa-portal-pro' ) . '</h3>';
        echo '<p>' . esc_html__( 'Voting is not assigned to your account. Please contact the office if you believe this is a mistake.', 'hoa-coa-portal-pro' ) . '</p></div></div>';
        return;
    }

    $now = time();
    $elections = get_posts( array(
        'post_type'      => 'hcp_election',
        'post_status'    => 'publish',
        'numberposts'    => 20,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => array(
            array(
                'key'     => '_hcp_status',
                'value'   => 'published',
                'compare' => '=',
            ),
        ),
    ) );

    
			if ( empty( $elections ) ) {
				self::empty_state( __( 'No elections yet', 'hoa-coa-portal-pro' ), __( 'There are no published elections available right now.', 'hoa-coa-portal-pro' ), __( 'Admins: create an election and publish it to show it here.', 'hoa-coa-portal-pro' ) );
				return;
			}
if ( empty( $elections ) ) {
        echo '<div class="hcp-msg"><em>' . esc_html__( 'No open elections right now.', 'hoa-coa-portal-pro' ) . '</em></div></div>';
        return;
    }
    foreach ( $elections as $e ) {
        $status = self::election_status( (int) $e->ID );
        if ( 'all' !== $filter && $filter !== $status ) {
            continue;
        }

echo '<div class="hcp-card">';
        echo '<div class="hcp-item-head">';
        echo '<h3 style="margin:0;">' . esc_html( $e->post_title ) . ' ' . wp_kses_post( self::render_badge( $status ) ) . '</h3>';
        echo '</div>';
        $author_name = '';
        $author_obj  = get_user_by( 'id', (int) $e->post_author );
        if ( $author_obj ) { $author_name = $author_obj->display_name; }
        $posted_date = mysql2date( get_option( 'date_format' ), $e->post_date );
        echo '<div class="hcp-item-meta">' . esc_html__( 'Posted', 'hoa-coa-portal-pro' ) . ': ' . esc_html( $posted_date ) . ' · ' . esc_html__( 'Assigned by', 'hoa-coa-portal-pro' ) . ' ' . esc_html( $author_name ) . '</div>';
        echo wp_kses_post( wpautop( $e->post_content ) );

        $t = HCP_Tally::get( (int) $e->ID );
        $mode_label = ( 'weight' === $t['quorum_mode'] ) ? __( 'Weight', 'hoa-coa-portal-pro' ) : __( 'Units', 'hoa-coa-portal-pro' );
        echo '<p class="hcp-meta"><strong>' . esc_html__( 'Quorum', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( (string) $t['quorum_percent'] ) . '% (' . esc_html( $mode_label ) . ') — ';
        if ( 'weight' === $t['quorum_mode'] ) {
            echo esc_html( number_format_i18n( (float) $t['voted_weight'], 2 ) ) . ' / ' . esc_html( number_format_i18n( (float) $t['eligible_weight'], 2 ) );
        } else {
            echo esc_html( (string) $t['voted_units'] ) . ' / ' . esc_html( (string) $t['eligible_units'] );
        }
        echo ' — ' . ( $t['quorum_met'] ? esc_html__( 'Met', 'hoa-coa-portal-pro' ) : esc_html__( 'Not met yet', 'hoa-coa-portal-pro' ) ) . '</p>';

        echo '<div class="hcp-table"><table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Unit', 'hoa-coa-portal-pro' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'hoa-coa-portal-pro' ) . '</th>';
        echo '<th>' . esc_html__( 'Vote', 'hoa-coa-portal-pro' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $unit_ids as $unit_id ) {
            $unit_label = HCP_Units::get_unit_number( (int) $unit_id );
            $existing_vote_id = self::find_existing_vote_for_unit( (int) $e->ID, (int) $unit_id );

            echo '<tr>';
            echo '<td>' . esc_html( $unit_label ) . '</td>';

            if ( $existing_vote_id ) {
                $choice = (string) get_post_meta( $existing_vote_id, '_hcp_choice', true );
                echo '<td><strong>' . esc_html__( 'Voted', 'hoa-coa-portal-pro' ) . '</strong></td>';
                echo '<td>' . esc_html( strtoupper( $choice ) ) . '</td>';
            } else {
                echo '<td>' . esc_html__( 'Not voted', 'hoa-coa-portal-pro' ) . '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'hcp_submit_vote' );
                echo '<input type="hidden" name="action" value="hcp_submit_vote"/>';
                echo '<input type="hidden" name="election_id" value="' . esc_attr( (string) $e->ID ) . '"/>';
                echo '<input type="hidden" name="unit_id" value="' . esc_attr( (string) $unit_id ) . '"/>';
                echo '<label><input type="radio" name="choice" value="yes" required> ' . esc_html__( 'Yes', 'hoa-coa-portal-pro' ) . '</label> ';
                echo '<label><input type="radio" name="choice" value="no" required> ' . esc_html__( 'No', 'hoa-coa-portal-pro' ) . '</label> ';
                echo '<label><input type="radio" name="choice" value="abstain" required> ' . esc_html__( 'Abstain', 'hoa-coa-portal-pro' ) . '</label> ';
                echo '<button type="submit" class="button button-primary" style="margin-left:8px">' . esc_html__( 'Submit', 'hoa-coa-portal-pro' ) . '</button>';
                echo '</form>';
                echo '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table></div>';
        echo '</div>';
    }

    echo '</div>';
}

    public static function owner_verify(): void {
    if ( ! is_user_logged_in() ) {
        HCP_Helpers::safe_redirect( wp_login_url( wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
    }

    $uid = get_current_user_id();
        $is_staff = HCP_Helpers::can_manage();
    check_admin_referer( 'hcp_owner_verify' );

    $unit_id = isset( $_POST['unit_id'] ) ? absint( wp_unslash( $_POST['unit_id'] ) ) : 0;

    $redirect_back = wp_get_referer() ? wp_get_referer() : home_url( '/' );

    if ( $unit_id <= 0 || ! HCP_Units::user_is_primary_owner( (int) $uid, $unit_id ) ) {
        $url = add_query_arg( 'hcp_msg', 'verify_denied', $redirect_back );
        HCP_Helpers::safe_redirect( $url );
    }

    HCP_Units::set_verification( $unit_id, 'verified_owner_affirmed', (int) $uid, 'owner_affirmed' );

    $url = add_query_arg( 'hcp_msg', 'verified', $redirect_back );
    HCP_Helpers::safe_redirect( $url );
}

public static function submit_vote(): void {
        HCP_Helpers::require_login_or_redirect();

        if ( ! HCP_Helpers::can_vote() ) {
            wp_die( esc_html__( 'Not authorized.', 'hoa-coa-portal-pro' ) );
        }

        check_admin_referer( 'hcp_submit_vote' );

        $election_id = isset( $_POST['election_id'] ) ? absint( $_POST['election_id'] ) : 0;
        $choice = isset( $_POST['choice'] ) ? sanitize_key( (string) $_POST['choice'] ) : '';
        $unit_id = isset( $_POST['unit_id'] ) ? absint( $_POST['unit_id'] ) : 0;

        if ( $election_id <= 0 || $unit_id <= 0 || ! in_array( $choice, array( 'yes', 'no', 'abstain' ), true ) ) {
            wp_die( esc_html__( 'Invalid vote.', 'hoa-coa-portal-pro' ) );
        }

        $election = get_post( $election_id );
        if ( ! $election || 'hcp_election' !== $election->post_type ) {
            wp_die( esc_html__( 'Election not found.', 'hoa-coa-portal-pro' ) );
        }

        
        if ( (int) get_post_meta( $election_id, '_hcp_finalized', true ) === 1 ) {
            wp_die( esc_html__( 'Election is finalized and no longer accepts votes.', 'hoa-coa-portal-pro' ) );
        }
$e_status = (string) get_post_meta( $election_id, '_hcp_status', true );

        $finalized = (int) get_post_meta( $election_id, '_hcp_finalized', true );
        if ( $finalized ) {
            wp_die( esc_html__( 'Election results have been finalized. Voting is closed.', 'hoa-coa-portal-pro' ) );
        }
        if ( 'published' !== $e_status ) {
            wp_die( esc_html__( 'Election is not open.', 'hoa-coa-portal-pro' ) );
        }

        $now = time();
        $start = (int) get_post_meta( $election_id, '_hcp_start_at', true );
        $end   = (int) get_post_meta( $election_id, '_hcp_end_at', true );
        if ( $start && $now < $start ) { wp_die( esc_html__( 'Voting has not started yet.', 'hoa-coa-portal-pro' ) ); }
        if ( $end && $now > $end ) { wp_die( esc_html__( 'Voting has ended.', 'hoa-coa-portal-pro' ) ); }

        $uid = get_current_user_id();
        $is_staff = HCP_Helpers::can_manage();
        if ( ! HCP_Units::user_is_primary_owner( $uid, $unit_id ) ) {
            wp_die( esc_html__( 'Voting is not assigned to your account for this unit.', 'hoa-coa-portal-pro' ) );
        }
        $existing = self::find_existing_vote_for_unit( $election_id, $unit_id );
        if ( $existing ) {
            wp_die( esc_html__( 'You have already voted in this election.', 'hoa-coa-portal-pro' ) );
        }

        $vote_id = wp_insert_post( array(
            'post_type'    => 'hcp_vote',
            'post_status'  => 'private',
            'post_title'   => 'Vote: unit ' . $unit_id . ' / election ' . $election_id,
            'post_parent'  => $election_id,
            'post_author'  => $uid,
        ), true );

        if ( is_wp_error( $vote_id ) ) {
            wp_die( esc_html( $vote_id->get_error_message() ) );
        }

        update_post_meta( (int) $vote_id, '_hcp_choice', $choice );
        update_post_meta( (int) $vote_id, '_hcp_unit_id', $unit_id );
        update_post_meta( (int) $vote_id, '_hcp_unit_weight', HCP_Units::get_unit_weight( $unit_id ) );
        update_post_meta( (int) $vote_id, '_hcp_submitted_at', $now );

$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
if ( strlen( $ua ) > 255 ) { $ua = substr( $ua, 0, 255 ); }

update_post_meta( (int) $vote_id, '_hcp_ip', $ip );
update_post_meta( (int) $vote_id, '_hcp_user_agent', $ua );

$payload = $election_id . '|' . (int) $vote_id . '|' . $unit_id . '|' . $choice . '|' . (string) HCP_Units::get_unit_weight( $unit_id ) . '|' . $now . '|' . $uid;
$hash = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
update_post_meta( (int) $vote_id, '_hcp_vote_hash', $hash );


        $redirect = wp_get_referer();
        if ( ! $redirect ) { $redirect = home_url( '/' ); }
        HCP_Helpers::safe_redirect( add_query_arg( array( 'hcp_voted' => 1 ), $redirect ) );
    }

    private static function find_existing_vote_for_unit( int $election_id, int $unit_id ): int {
    $votes = get_posts( array(
        'post_type'      => 'hcp_vote',
        'post_status'    => array( 'private', 'publish', 'draft' ),
        'numberposts'    => 1,
        'fields'         => 'ids',
        'post_parent'    => $election_id,
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'     => '_hcp_unit_id',
                'value'   => $unit_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ) );
    return ! empty( $votes ) ? (int) $votes[0] : 0;
}


    private static function can_view_post_by_audience( int $post_id ): bool {
        $aud = (string) get_post_meta( $post_id, '_hcp_audience', true );
        if ( '' === $aud ) { $aud = 'both'; }

        $is_owner = HCP_Helpers::can_vote();
        $is_office = HCP_Helpers::can_manage() || current_user_can( 'hcp_manage_portal' );

        if ( 'both' === $aud ) { return true; }
        if ( 'owner' === $aud ) { return $is_owner; }
        if ( 'office' === $aud ) { return $is_office; }
        return false;
    }

    private static function render_attachments( int $post_id ): void {
        $ids = get_post_meta( $post_id, '_hcp_attachments', true );
        if ( ! is_array( $ids ) ) { return; }
        $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
        $ids = array_filter( $ids, static function( $id ){ return HCP_Helpers::is_allowed_attachment( (int) $id ); } );
        if ( empty( $ids ) ) { return; }

        echo '<div class="hcp-attach"><strong>' . esc_html__( 'Attachments:', 'hoa-coa-portal-pro' ) . '</strong><br/>';
        foreach ( $ids as $id ) {
            $url = wp_get_attachment_url( (int) $id );
            if ( ! $url ) { continue; }
            $name = basename( (string) $url );
            echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>';
        }
        echo '</div>';
    
    }

private static function panel_owner_access(): void {
    if ( ! is_user_logged_in() ) {
        echo '<div class="hcp-card"><p>' . esc_html__( 'Please log in to view your owner access details.', 'hoa-coa-portal-pro' ) . '</p></div>';
        return;
    }

    $uid = get_current_user_id();
        $is_staff = HCP_Helpers::can_manage();
    $unit_ids = HCP_Units::get_primary_unit_ids_for_user( (int) $uid );

    echo '<div class="hcp-card">';
    echo '<h3>' . esc_html__( 'Owner Access', 'hoa-coa-portal-pro' ) . '</h3>';
    echo '<p>' . esc_html__( 'This page shows what unit(s) you are authorized to represent and a read-only log of your voting activity.', 'hoa-coa-portal-pro' ) . '</p>';
    echo '</div>';

    echo '<div class="hcp-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">';
    echo '<div class="hcp-card">';
    echo '<h4>' . esc_html__( 'Your Unit Authorization', 'hoa-coa-portal-pro' ) . '</h4>';

    if ( empty( $unit_ids ) ) {
        echo '<p>' . esc_html__( 'You are not assigned as the Primary Voting Owner for any unit.', 'hoa-coa-portal-pro' ) . '</p>';
    } else {
        echo '<ul style="margin:0;padding-left:18px;">';
        foreach ( $unit_ids as $unit_id ) {
            $unit_id = (int) $unit_id;
            $status = HCP_Units::get_verification_status( $unit_id );
            $badge = __( 'Unverified', 'hoa-coa-portal-pro' );
            if ( 'verified_owner_affirmed' === $status ) {
                $badge = __( 'Verified (Owner)', 'hoa-coa-portal-pro' );
            } elseif ( 'verified_board_assigned' === $status ) {
                $badge = __( 'Verified (Board)', 'hoa-coa-portal-pro' );
            }
            echo '<li><strong>' . esc_html( HCP_Units::get_unit_number( $unit_id ) ) . '</strong> — ' . esc_html( $badge ) . '</li>';
        }
        echo '</ul>';
    }

    echo '</div>';

    echo '<div class="hcp-card">';
    echo '<h4>' . esc_html__( 'Your Voting History', 'hoa-coa-portal-pro' ) . '</h4>';

    $votes = get_posts( array(
        'post_type'      => 'hcp_vote',
        'post_status'    => array( 'publish', 'private' ),
        'numberposts'    => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'     => '_hcp_user_id',
                'value'   => (int) $uid,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ) );

    if ( empty( $votes ) ) {
        echo '<p>' . esc_html__( 'No votes recorded on this account yet.', 'hoa-coa-portal-pro' ) . '</p>';
    } else {
        echo '<table class="hcp-table" style="width:100%;border-collapse:collapse;">';
        echo '<thead><tr>';
        echo '<th style="text-align:left;padding:6px 4px;border-bottom:1px solid #e5e5e5;">' . esc_html__( 'Election', 'hoa-coa-portal-pro' ) . '</th>';
        echo '<th style="text-align:left;padding:6px 4px;border-bottom:1px solid #e5e5e5;">' . esc_html__( 'Unit', 'hoa-coa-portal-pro' ) . '</th>';
        echo '<th style="text-align:left;padding:6px 4px;border-bottom:1px solid #e5e5e5;">' . esc_html__( 'Submitted', 'hoa-coa-portal-pro' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $votes as $vote_id ) {
            $vote_id = (int) $vote_id;
            $election_id = (int) get_post_meta( $vote_id, '_hcp_election_id', true );
            $unit_id = (int) get_post_meta( $vote_id, '_hcp_unit_id', true );
            $submitted_at = (int) get_post_meta( $vote_id, '_hcp_submitted_at', true );

            echo '<tr>';
            echo '<td style="padding:6px 4px;border-bottom:1px solid #f0f0f0;">' . esc_html( $election_id ? get_the_title( $election_id ) : __( '(unknown)', 'hoa-coa-portal-pro' ) ) . '</td>';
            echo '<td style="padding:6px 4px;border-bottom:1px solid #f0f0f0;">' . esc_html( $unit_id ? HCP_Units::get_unit_number( $unit_id ) : __( '(unknown)', 'hoa-coa-portal-pro' ) ) . '</td>';
            echo '<td style="padding:6px 4px;border-bottom:1px solid #f0f0f0;">' . esc_html( $submitted_at ? gmdate( 'Y-m-d H:i:s', $submitted_at ) . ' UTC' : '' ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }



    echo '<div class="hcp-card">';
    echo '<h4>' . esc_html__( 'Owner Documents', 'hoa-coa-portal-pro' ) . '</h4>';

    if ( ! HCP_Owner_Docs::user_can_view_owner_docs( (int) $uid ) && ! $is_staff ) {
        echo '<p>' . esc_html__( 'No owner documents are available for your account yet. If you believe this is a mistake, contact the office.', 'hoa-coa-portal-pro' ) . '</p>';
        echo '</div>';
    } else {
        $docs = get_posts( array(
            'post_type'      => 'hcp_owner_doc',
            'post_status'    => array( 'publish' ),
            'numberposts'    => 25,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ) );

        if ( empty( $docs ) ) {
            echo '<p>' . esc_html__( 'No documents have been published yet.', 'hoa-coa-portal-pro' ) . '</p>';
        } else {
            echo '<ul class="hcp-list" style="margin:0;padding-left:18px;">';
            foreach ( $docs as $doc ) {
                $author = get_the_author_meta( 'display_name', (int) $doc->post_author );
                $date   = mysql2date( 'Y-m-d', $doc->post_date );
                echo '<li style="margin:0 0 10px 0;">';
                echo '<details>';
                echo '<summary><strong>' . esc_html( $doc->post_title ) . '</strong> <span style="opacity:.8;">— ' . esc_html( $date ) . ' — ' . esc_html( $author ) . '</span></summary>';
                echo '<div style="margin-top:8px;">' . wp_kses_post( wpautop( $doc->post_content ) ) . '</div>';
                echo '</details>';
                echo '</li>';
            }
            echo '</ul>';
            echo '<p style="margin-top:10px;opacity:.8;">' . esc_html__( 'Tip: expand a document to view details and download links.', 'hoa-coa-portal-pro' ) . '</p>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}


private static function render_access_pending(): string {
    $message  = '<div class="hcp-card hcp-access-pending">';
    $message .= '<h3>' . esc_html__( 'Access Pending', 'hoa-coa-portal-pro' ) . '</h3>';
    $message .= '<p>' . esc_html__( 'Your account is not yet fully set up for portal access.', 'hoa-coa-portal-pro' ) . '</p>';
    $message .= '<ul style="margin-left:18px;">';
    $message .= '<li>' . esc_html__( 'Verify your unit (if required).', 'hoa-coa-portal-pro' ) . '</li>';
    $message .= '<li>' . esc_html__( 'Make sure a Primary Voting Owner is assigned to your unit.', 'hoa-coa-portal-pro' ) . '</li>';
    $message .= '<li>' . esc_html__( 'If you believe this is a mistake, contact the association office.', 'hoa-coa-portal-pro' ) . '</li>';
    $message .= '</ul>';
    $message .= '</div>';
    return $message;
}



private static function render_badge( string $status ): string {
    $status = strtolower( $status );
    $label  = __( 'Draft', 'hoa-coa-portal-pro' );
    $class  = 'hcp-badge--draft';

    if ( 'open' === $status || 'active' === $status ) {
        $label = __( 'Open', 'hoa-coa-portal-pro' );
        $class = 'hcp-badge--open';
    } elseif ( 'closed' === $status || 'finalized' === $status ) {
        $label = __( 'Closed', 'hoa-coa-portal-pro' );
        $class = 'hcp-badge--closed';
    }

    return '<span class="hcp-badge ' . esc_attr( $class ) . '"><span class="hcp-badge-dot" aria-hidden="true"></span>' . esc_html( $label ) . '</span>';
}

private static function tooltip( string $label, string $text ): string {
    return '<span class="hcp-tooltip"><span class="hcp-tip" role="button" tabindex="0" aria-label="' . esc_attr( $label ) . '">?</span><span class="hcp-tip-panel">' . esc_html( $text ) . '</span></span>';
}

private static function empty_state( string $title, string $message, string $hint = '' ): void {
    echo '<div class="hcp-card">';
    echo '<h3 style="margin:0 0 8px 0;">' . esc_html( $title ) . '</h3>';
    echo '<p class="hcp-muted" style="margin:0 0 10px 0;">' . esc_html( $message ) . '</p>';
    if ( '' !== $hint ) {
        echo '<div class="hcp-notice" style="margin:0;">' . esc_html( $hint ) . '</div>';
    }
    echo '</div>';
}




private static function election_status( int $election_id ): string {
    $finalized = (bool) get_post_meta( $election_id, '_hcp_finalized', true );
    if ( $finalized ) {
        return 'closed';
    }

    $start = (int) get_post_meta( $election_id, '_hcp_start_ts', true );
    $end   = (int) get_post_meta( $election_id, '_hcp_end_ts', true );

    // Back-compat for earlier builds that used different meta keys.
    if ( ! $start ) {
        $start = (int) get_post_meta( $election_id, '_hcp_start_at', true );
    }
    if ( ! $end ) {
        $end = (int) get_post_meta( $election_id, '_hcp_end_at', true );
    }

    $now = time();

    if ( $start && $end ) {
        if ( $now < $start ) {
            return 'draft';
        }
        if ( $now >= $start && $now <= $end ) {
            return 'open';
        }
        return 'closed';
    }

    // If we don't have a full window, treat as draft unless finalized.
    return 'draft';
}

private static function handle_compliance_download_request(): void {
    if ( ! isset( $_GET['hcp_download_compliance'] ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        wp_die( esc_html__( 'You must be logged in to download documents.', 'hoa-coa-portal-pro' ), 403 );
    }
    $doc_id = absint( wp_unslash( $_GET['hcp_download_compliance'] ) );
    if ( $doc_id <= 0 ) {
        wp_die( esc_html__( 'Invalid document.', 'hoa-coa-portal-pro' ), 400 );
    }
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'hcp_dl_compliance_' . $doc_id ) ) {
        wp_die( esc_html__( 'Invalid download link.', 'hoa-coa-portal-pro' ), 403 );
    }

    $post = get_post( $doc_id );
    if ( ! $post || 'hcp_compliance_doc' !== $post->post_type ) {
        wp_die( esc_html__( 'Document not found.', 'hoa-coa-portal-pro' ), 404 );
    }

    $visibility = (string) get_post_meta( $doc_id, '_hcp_visibility', true );
    if ( '' === $visibility ) { $visibility = 'owners'; }

    $user_id  = get_current_user_id();
    $is_admin = user_can( $user_id, 'manage_options' );
    $is_staff = user_can( $user_id, 'hcp_staff' ) || user_can( $user_id, 'hcp_manager' );

    $allowed = false;
    if ( $is_admin || $is_staff ) {
        $allowed = true;
    } elseif ( 'owners' === $visibility ) {
        if ( class_exists( 'HCP_Units' ) && method_exists( 'HCP_Units', 'get_user_units' ) ) {
            $units = (array) HCP_Units::get_user_units( $user_id );
            $allowed = ! empty( $units );
        }
    }

    if ( ! $allowed ) {
        wp_die( esc_html__( 'You do not have permission to access this document.', 'hoa-coa-portal-pro' ), 403 );
    }

    $file_id = absint( get_post_meta( $doc_id, '_hcp_file_id', true ) );
    if ( $file_id <= 0 ) {
        wp_die( esc_html__( 'No file attached to this document yet.', 'hoa-coa-portal-pro' ), 404 );
    }

    $file_path = get_attached_file( $file_id );
    if ( ! $file_path || ! file_exists( $file_path ) ) {
        wp_die( esc_html__( 'File not found.', 'hoa-coa-portal-pro' ), 404 );
    }

    $mime = (string) get_post_mime_type( $file_id );
    if ( '' === $mime ) { $mime = 'application/octet-stream'; }
    $filename = basename( $file_path );

    nocache_headers();
    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . (string) filesize( $file_path ) );
    header( 'X-Content-Type-Options: nosniff' );
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    readfile( $file_path );
    exit;
}


}
