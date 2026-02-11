<?php
/**
 * Uninstall handler for HOA/COA Portal (Free).
 *
 * By default, data is preserved. Data is only deleted if the admin explicitly opts out.
 *
 * @package HOA_COA_Portal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$preserve = get_option( 'hcp_preserve_data_on_uninstall', 'yes' );
if ( 'yes' === $preserve ) {
	return;
}

// Options.
delete_option( 'hcp_preserve_data_on_uninstall' );
delete_option( 'hcp_portal_page_id' );
delete_option( 'hcp_org_name' );
delete_option( 'hcp_org_address' );
delete_option( 'hcp_org_phone' );
delete_option( 'hcp_org_email' );

// Content (CPTs).
$post_types = array( 'hcp_election', 'hcp_vote', 'hcp_unit', 'hcp_agenda', 'hcp_minute', 'hcp_notice', 'hcp_work_order', 'hcp_compliance_doc' );
foreach ( $post_types as $pt ) {
	$ids = get_posts(
		array(
			'post_type'        => $pt,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			
		)
	);
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
}
