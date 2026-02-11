<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_Owner_Docs {

    public static function hooks(): void {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    public static function register(): void {
        // Taxonomy for grouping owner-access documents.
        register_taxonomy(
            'hcp_owner_doc_category',
            array( 'hcp_owner_doc' ),
            array(
                'public'       => false,
                'show_ui'      => false, // managed via our custom admin screens (for now).
                'show_in_menu' => false,
                'show_in_rest' => false,
                'hierarchical' => true,
                'labels'       => array(
                    'name'          => __( 'Owner Document Categories', 'hoa-coa-portal-pro' ),
                    'singular_name' => __( 'Owner Document Category', 'hoa-coa-portal-pro' ),
                ),
            )
        );
    }

    public static function seed_terms(): void {
        if ( taxonomy_exists( 'hcp_owner_doc_category' ) ) {
            $terms = array(
                'governing-docs'   => __( 'Governing Documents', 'hoa-coa-portal-pro' ),
                'budgets-finance'  => __( 'Budgets & Financials', 'hoa-coa-portal-pro' ),
                'contracts'        => __( 'Contracts', 'hoa-coa-portal-pro' ),
                'insurance'        => __( 'Insurance', 'hoa-coa-portal-pro' ),
                'meeting-records'  => __( 'Meeting Records', 'hoa-coa-portal-pro' ),
                'construction'     => __( 'Permits & Construction', 'hoa-coa-portal-pro' ),
                'safety-inspection'=> __( 'Safety / Inspection Reports', 'hoa-coa-portal-pro' ),
                'reserves'         => __( 'Reserves / SIRS', 'hoa-coa-portal-pro' ),
                'policies'         => __( 'Policies & Affidavits', 'hoa-coa-portal-pro' ),
                'other'            => __( 'Other', 'hoa-coa-portal-pro' ),
            );
            foreach ( $terms as $slug => $label ) {
                if ( ! term_exists( $slug, 'hcp_owner_doc_category' ) ) {
                    wp_insert_term( $label, 'hcp_owner_doc_category', array( 'slug' => $slug ) );
                }
            }
        }
    }

    public static function user_can_view_owner_docs( int $user_id ): bool {
        if ( $user_id <= 0 ) {
            return false;
        }
        if ( HCP_Helpers::can_manage() ) {
            return true;
        }
        $unit_ids = HCP_Units::get_primary_unit_ids_for_user( $user_id );
        return ! empty( $unit_ids );
    }
}
