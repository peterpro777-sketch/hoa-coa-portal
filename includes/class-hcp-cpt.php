<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_CPT {
    public static function hooks(): void {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    public static function register(): void {
        $common = array(
            'public'              => false,
            'show_ui'             => false, // custom admin screens
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'supports'            => array( 'title', 'editor' ),
        );

        register_post_type( 'hcp_notice', array_merge( $common, array(
            'labels' => array(
                'name'          => __( 'Notices', 'hoa-coa-portal-pro' ),
                'singular_name' => __( 'Notice', 'hoa-coa-portal-pro' ),
            ),
        ) ) );

        register_post_type( 'hcp_minutes', array_merge( $common, array(
            'labels' => array(
                'name'          => __( 'Minutes', 'hoa-coa-portal-pro' ),
                'singular_name' => __( 'Minutes', 'hoa-coa-portal-pro' ),
            ),
        ) ) );

        register_post_type( 'hcp_agenda', array_merge( $common, array(
            'labels' => array(
                'name'          => __( 'Agendas', 'hoa-coa-portal-pro' ),
                'singular_name' => __( 'Agenda', 'hoa-coa-portal-pro' ),
            ),
        ) ) );

        

        register_post_type( 'hcp_owner_doc', array_merge( $common, array(
            'labels' => array(
                'name'          => __( 'Owner Documents', 'hoa-coa-portal-pro' ),
                'singular_name' => __( 'Owner Document', 'hoa-coa-portal-pro' ),
            ),
        ) ) );
register_post_type( 'hcp_election', array_merge( $common, array(
            'labels' => array(
                'name'          => __( 'Elections', 'hoa-coa-portal-pro' ),
                'singular_name' => __( 'Election', 'hoa-coa-portal-pro' ),
            ),
        ) ) );

        
register_post_type( 'hcp_unit', array_merge( $common, array(
    'labels' => array(
        'name'          => __( 'Units', 'hoa-coa-portal-pro' ),
        'singular_name' => __( 'Unit', 'hoa-coa-portal-pro' ),
    ),
    'supports' => array( 'title' ),
) ) );


        register_post_type( 'hcp_vote', array_merge( $common, array(
            'labels' => array(
                'name'          => __( 'Votes', 'hoa-coa-portal-pro' ),
                'singular_name' => __( 'Vote', 'hoa-coa-portal-pro' ),
            ),
            'supports' => array( 'title' ),
        ) ) );
    }
}
