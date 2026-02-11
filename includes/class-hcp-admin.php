<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HCP_Admin {
    // Hooks for election security

    public static function snapshot_ballot( int $election_id ): void {
        if ( get_post_meta( $election_id, '_hcp_ballot_snapshot', true ) ) { return; }
        $snapshot=array('questions'=>get_post_meta($election_id,'_hcp_questions',true),'choices'=>get_post_meta($election_id,'_hcp_choices',true),'time'=>time());
        update_post_meta($election_id,'_hcp_ballot_snapshot',wp_json_encode($snapshot));
        update_post_meta($election_id,'_hcp_snapshot_hash',hash('sha256',wp_json_encode($snapshot)));
    }

    public static function hooks(): void {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );

        
		
		add_filter( 'manage_edit-hcp_election_columns', [ __CLASS__, 'election_columns' ] );
		add_filter( 'manage_edit-hcp_agenda_columns', [ __CLASS__, 'agenda_columns' ] );
		add_action( 'manage_hcp_agenda_posts_custom_column', [ __CLASS__, 'agenda_column_content' ], 10, 2 );
		add_filter( 'manage_edit-hcp_minutes_columns', [ __CLASS__, 'minutes_columns' ] );
		add_action( 'manage_hcp_minutes_posts_custom_column', [ __CLASS__, 'minutes_column_content' ], 10, 2 );
		add_action( 'manage_hcp_election_posts_custom_column', [ __CLASS__, 'election_column_content' ], 10, 2 );
add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        // Row actions (assignment) for Agendas/Minutes lists.
        add_filter( 'post_row_actions', array( __CLASS__, 'row_actions_assignment' ), 10, 2 );
        add_action( 'admin_post_hcp_assign_to_me', array( __CLASS__, 'handle_assign_to_me' ) );
        add_action( 'admin_post_hcp_clear_assignment', array( __CLASS__, 'handle_clear_assignment' ) );
// Save handlers
        add_action( 'admin_post_hcp_save_unit', array( __CLASS__, 'save_unit' ) );
        add_action( 'admin_post_hcp_import_units_csv', array( __CLASS__, 'import_units_csv' ) );
        add_action( 'admin_post_hcp_assign_primary_owner', array( __CLASS__, 'assign_primary_owner' ) );
        add_action( 'admin_post_hcp_save_license', array( __CLASS__, 'save_license' ) );
        add_action( 'admin_post_hcp_save_notice', array( __CLASS__, 'save_notice' ) );
        add_action( 'admin_post_hcp_save_minutes', array( __CLASS__, 'save_minutes' ) );
        add_action( 'admin_post_hcp_save_agenda', array( __CLASS__, 'save_agenda' ) );
        add_action( 'admin_post_hcp_save_owner_doc', array( __CLASS__, 'save_owner_doc' ) );
        add_action( 'admin_post_hcp_save_election', array( __CLASS__, 'save_election' ) );
        add_action( 'admin_post_hcp_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'admin_post_hcp_repair_roles', array( __CLASS__, 'repair_roles' ) );
        add_action( 'admin_post_hcp_seed_compliance', array( __CLASS__, 'seed_compliance' ) );
        add_action( 'admin_post_hcp_finalize_results', array( __CLASS__, 'finalize_results' ) );
        add_action( 'admin_post_hcp_export_audit_csv', array( __CLASS__, 'export_audit_csv' ) );
        add_action( 'admin_post_hcp_export_audit_pdf', array( __CLASS__, 'export_audit_pdf' ) );
        add_action( 'admin_post_hcp_export_compliance_csv', array( __CLASS__, 'export_compliance_csv' ) );
        add_action( 'admin_post_hcp_set_unit_verification', array( __CLASS__, 'set_unit_verification' ) );
    }

    /**
     * Map a singular slug (e.g., 'notice') or already-plural slug (e.g., 'minutes')
     * to the actual WP admin page slug registered by this plugin.
     *
     * Historically we appended "s" in multiple places. That breaks for slugs that
     * are already plural (e.g., "minutes" -> "minutess"), causing "not allowed".
     */
    private static function page_slug( string $slug ): string {
        $slug = sanitize_key( $slug );
        if ( '' === $slug ) {
            return 'hcp-dashboard';
        }
        // If it already ends with "s", treat as plural.
        // NOTE: We intentionally avoid PHP 8's str_ends_with() so the plugin
        // fails more gracefully on hosts that haven't been upgraded yet.
        $ends_with_s = ( strlen( $slug ) > 0 && 's' === substr( $slug, -1 ) );
        if ( $ends_with_s ) {
            return 'hcp-' . $slug;
        }
        return 'hcp-' . $slug . 's';
    }

    public static function menu(): void {
        // Use manage_options for menu display; office staff will access via direct links if needed.
        add_menu_page(
            __( 'HOA/COA Portal', 'hoa-coa-portal-pro' ),
            __( 'HOA/COA Portal', 'hoa-coa-portal-pro' ),
            'manage_options',
            'hcp-dashboard',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-admin-multisite',
            56
        );

        add_submenu_page( 'hcp-dashboard', __( 'Dashboard', 'hoa-coa-portal-pro' ), __( 'Dashboard', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-dashboard', array( __CLASS__, 'page_dashboard' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Notices', 'hoa-coa-portal-pro' ), __( 'Notices', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-notices', array( __CLASS__, 'page_notices' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Minutes', 'hoa-coa-portal-pro' ), __( 'Minutes', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-minutes', array( __CLASS__, 'page_minutes' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Agendas', 'hoa-coa-portal-pro' ), __( 'Agendas', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-agendas', array( __CLASS__, 'page_agendas' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Elections', 'hoa-coa-portal-pro' ), __( 'Elections', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-elections', array( __CLASS__, 'page_elections' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Results', 'hoa-coa-portal-pro' ), __( 'Results', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-results', array( __CLASS__, 'page_results' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Units', 'hoa-coa-portal-pro' ), __( 'Units', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-units', array( __CLASS__, 'page_units' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Eligibility', 'hoa-coa-portal-pro' ), __( 'Eligibility', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-eligibility', array( __CLASS__, 'page_eligibility' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Verification', 'hoa-coa-portal-pro' ), __( 'Verification', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-verification', array( __CLASS__, 'page_verification' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Owner Access', 'hoa-coa-portal-pro' ), __( 'Owner Access', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-owner-access', array( __CLASS__, 'page_owner_access' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Owner Documents', 'hoa-coa-portal-pro' ), __( 'Owner Documents', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-owner-docs', array( __CLASS__, 'page_owner_docs' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Audit Summary', 'hoa-coa-portal-pro' ), __( 'Audit Summary', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-audit-summary', array( __CLASS__, 'page_audit_summary' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Compliance Docs', 'hoa-coa-portal-pro' ), __( 'Compliance Docs', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-compliance-docs', array( __CLASS__, 'page_compliance_docs' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Compliance Dashboard', 'hoa-coa-portal-pro' ), __( 'Compliance Dashboard', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-compliance-dashboard', array( __CLASS__, 'page_compliance_dashboard' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Compliance Binder', 'hoa-coa-portal-pro' ), __( 'Compliance Binder', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-compliance-binder', 'hcp_page_compliance_binder' );
        add_submenu_page( 'hcp-dashboard', __( 'Access & Roles', 'hoa-coa-portal-pro' ), __( 'Access & Roles', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-access-roles', array( __CLASS__, 'page_access_roles' ) );
        add_submenu_page( 'hcp-dashboard', __( 'Settings', 'hoa-coa-portal-pro' ), __( 'Settings', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-settings', array( __CLASS__, 'page_settings' ) );

        // Pro-only: local (offline) license key storage.
        if ( defined( 'HCP_PRO_ACTIVE' ) && HCP_PRO_ACTIVE ) {
            add_submenu_page( 'hcp-dashboard', __( 'License', 'hoa-coa-portal-pro' ), __( 'License', 'hoa-coa-portal-pro' ), 'manage_options', 'hcp-license', array( __CLASS__, 'page_license' ) );
        }
    }

    public static function page_license(): void {
        self::require_manage();
        if ( ! defined( 'HCP_PRO_ACTIVE' ) || ! HCP_PRO_ACTIVE ) {
            wp_die( esc_html__( 'This screen is available in Pro only.', 'hoa-coa-portal-pro' ) );
        }
        $license = (string) get_option( 'hcp_pro_license_key', '' );
        $masked  = $license ? substr( $license, 0, 4 ) . str_repeat( '•', max( 0, strlen( $license ) - 8 ) ) . substr( $license, -4 ) : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'HOA/COA Portal Pro License', 'hoa-coa-portal-pro' ); ?></h1>
            <p><?php echo esc_html__( 'Enter your license key. This build validates offline (no remote calls).', 'hoa-coa-portal-pro' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'hcp_save_license' ); ?>
                <input type="hidden" name="action" value="hcp_save_license" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="hcp_license"><?php echo esc_html__( 'License key', 'hoa-coa-portal-pro' ); ?></label></th>
                        <td>
                            <input name="hcp_license" id="hcp_license" type="text" class="regular-text" value="<?php echo esc_attr( $license ); ?>" />
                            <?php if ( $masked ) : ?>
                                <p class="description"><?php echo esc_html__( 'Current:', 'hoa-coa-portal-pro' ); ?> <?php echo esc_html( $masked ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save License', 'hoa-coa-portal-pro' ) ); ?>
            </form>
        </div>
        <?php
    }

    public static function save_license(): void {
        self::require_manage();

        // Safer than check_admin_referer() here: some security stacks/WAF rules can strip referer fields
        // and cause an opaque wp_die() screen. We redirect back with a message instead.
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'hcp_save_license' ) ) {
            $redirect = add_query_arg( array( 'page' => 'hcp-license', 'hcp_msg' => 'nonce' ), admin_url( 'admin.php' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( ! defined( 'HCP_PRO_ACTIVE' ) || ! HCP_PRO_ACTIVE ) {
            wp_die( esc_html__( 'This action is available in Pro only.', 'hoa-coa-portal-pro' ) );
        }

        // Accept either the legacy field name or the earlier pro-specific field name.
        $license = '';
        if ( isset( $_POST['hcp_license'] ) ) {
            $license = sanitize_text_field( wp_unslash( $_POST['hcp_license'] ) );
        } elseif ( isset( $_POST['hcp_pro_license_key'] ) ) {
            $license = sanitize_text_field( wp_unslash( $_POST['hcp_pro_license_key'] ) );
        }

        // Offline validation: basic format checks only (no external calls).
        if ( '' !== $license ) {
            $license = preg_replace( '/\s+/', '', $license );
            if ( ! preg_match( '/^[A-Z0-9\-]{16,64}$/i', $license ) ) {
                $redirect = add_query_arg( array( 'page' => 'hcp-license', 'hcp_msg' => 'invalid' ), admin_url( 'admin.php' ) );
                wp_safe_redirect( $redirect );
                exit;
            }
        }

        update_option( 'hcp_pro_license_key', $license, false );
        $redirect = add_query_arg( array( 'page' => 'hcp-license', 'hcp_msg' => 'saved' ), admin_url( 'admin.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    private static function require_manage(): void {
        if ( ! ( class_exists( 'HCP_Helpers' ) ? HCP_Helpers::can_manage() : current_user_can( 'manage_options' ) ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'hoa-coa-portal-pro' ) );
        }

        // CodeCanyon license gate (offline). Allow the License screen without an active key.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'hcp-license' !== $page && class_exists( 'HCP_License' ) && ! HCP_License::is_active() ) {
            $url = admin_url( 'admin.php?page=hcp-license' );
            wp_die( wp_kses_post( sprintf( __( 'Please activate your license to unlock the portal features. <a href="%s">Go to License</a>.', 'hoa-coa-portal-pro' ), esc_url( $url ) ) ) );
        }
    }
    public static function set_unit_verification(): void {
    self::require_manage();
    check_admin_referer( 'hcp_set_unit_verification' );

    $unit_id = isset( $_POST['unit_id'] ) ? absint( wp_unslash( $_POST['unit_id'] ) ) : 0;
    $status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['status'] ) ) : 'unverified';
    if ( $unit_id <= 0 ) {
        wp_safe_redirect( admin_url( 'admin.php?page=hcp-verification&hcp_msg=bad_unit' ) );
        exit;
    }

    $method = 'board_assigned';
    if ( 'unverified' === $status ) {
        $method = 'revoked';
    }
    if ( 'verified_owner_affirmed' === $status ) {
        $method = 'board_override';
    }

    HCP_Units::set_verification( $unit_id, $status, get_current_user_id(), $method );

    wp_safe_redirect( admin_url( 'admin.php?page=hcp-verification&hcp_msg=saved' ) );
    exit;


    }

    public static function seed_compliance(): void {
        self::require_manage();
        check_admin_referer( 'hcp_seed_compliance' );
        HCP_Compliance::seed_terms();
        wp_safe_redirect( admin_url( 'admin.php?page=hcp-compliance-dashboard&hcp_msg=seeded' ) );
        exit;
    }

    public static function page_compliance_dashboard(): void {
        self::require_manage();

        $msg = isset( $_GET['hcp_msg'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hcp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message.
        if ( 'seeded' === $msg ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Compliance categories seeded.', 'hoa-coa-portal-pro' ) . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Compliance Dashboard', 'hoa-coa-portal-pro' ) . '</h1>';
        echo '<p>' . esc_html__( 'Use this checklist to stay on track with Florida online records requirements. Upload documents under Portal → Compliance Documents and assign categories.', 'hoa-coa-portal-pro' ) . '</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin: 12px 0;">';
        echo '<input type="hidden" name="action" value="hcp_seed_compliance" />';
        wp_nonce_field( 'hcp_seed_compliance' );
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Seed Default Categories', 'hoa-coa-portal-pro' ) . '</button>';
        echo '</form>';

        $terms = get_terms(
            array(
                'taxonomy'   => HCP_Compliance::TAX,
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'No categories found. Click “Seed Default Categories” above.', 'hoa-coa-portal-pro' ) . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped" style="max-width: 980px;">';
        echo '<thead><tr><th>' . esc_html__( 'Category', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Docs Uploaded', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Quick Link', 'hoa-coa-portal-pro' ) . '</th></tr></thead><tbody>';

        foreach ( $terms as $term ) {
            $q = new WP_Query(
                array(
                    'post_type'      => HCP_Compliance::CPT,
                    'posts_per_page' => 1,
                    'tax_query'      => array(
                        array(
                            'taxonomy' => HCP_Compliance::TAX,
                            'field'    => 'term_id',
                            'terms'    => (int) $term->term_id,
                        ),
                    ),
                )
            );

            $count = (int) $q->found_posts;
            wp_reset_postdata();

            $link = admin_url( 'edit.php?post_type=' . HCP_Compliance::CPT . '&' . HCP_Compliance::TAX . '=' . rawurlencode( $term->slug ) );
            echo '<tr>';
            echo '<td><strong>' . esc_html( $term->name ) . '</strong></td>';
            echo '<td>' . esc_html( (string) $count ) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url( $link ) . '">' . esc_html__( 'View', 'hoa-coa-portal-pro' ) . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p style="max-width: 980px; margin-top: 16px;"><em>' . esc_html__( 'Reminder: Do not upload unredacted personal data. Redact protected information before posting.', 'hoa-coa-portal-pro' ) . '</em></p>';
        echo '</div>';
    }


public static function row_actions_assignment( array $actions, WP_Post $post ): array {
    if ( ! in_array( $post->post_type, array( 'hcp_agenda', 'hcp_minutes' ), true ) ) {
        return $actions;
    }

    if ( ! current_user_can( HCP_Caps::CAP_MANAGE ) ) {
        return $actions;
    }

    $post_id = (int) $post->ID;
    $nonce   = wp_create_nonce( 'hcp_assign_actions_' . $post_id );

    $assign_url = add_query_arg(
        array(
            'action'   => 'hcp_assign_to_me',
            'post_id'  => $post_id,
            '_wpnonce' => $nonce,
        ),
        admin_url( 'admin-post.php' )
    );

    $clear_url = add_query_arg(
        array(
            'action'   => 'hcp_clear_assignment',
            'post_id'  => $post_id,
            '_wpnonce' => $nonce,
        ),
        admin_url( 'admin-post.php' )
    );

    $actions['hcp_assign_to_me'] = '<a href="' . esc_url( $assign_url ) . '">' . esc_html__( 'Assign to me', 'hoa-coa-portal-pro' ) . '</a>';

    $assigned = (int) get_post_meta( $post_id, '_hcp_assigned_to', true );
    if ( $assigned > 0 ) {
        $actions['hcp_clear_assignment'] = '<a href="' . esc_url( $clear_url ) . '">' . esc_html__( 'Clear assignment', 'hoa-coa-portal-pro' ) . '</a>';
    }

    return $actions;
}

public static function handle_assign_to_me(): void {
    $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    $nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';

    if ( $post_id <= 0 || ! wp_verify_nonce( $nonce, 'hcp_assign_actions_' . $post_id ) ) {
        wp_die( esc_html__( 'Invalid request.', 'hoa-coa-portal-pro' ) );
    }

    if ( ! current_user_can( HCP_Caps::CAP_MANAGE ) ) {
        wp_die( esc_html__( 'You do not have permission to do that.', 'hoa-coa-portal-pro' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post || ! in_array( $post->post_type, array( 'hcp_agenda', 'hcp_minutes' ), true ) ) {
        wp_die( esc_html__( 'Invalid item.', 'hoa-coa-portal-pro' ) );
    }

    update_post_meta( $post_id, '_hcp_assigned_to', get_current_user_id() );

    $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . $post->post_type );
    HCP_Helpers::safe_redirect( $redirect  );
}

public static function handle_clear_assignment(): void {
    $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    $nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';

    if ( $post_id <= 0 || ! wp_verify_nonce( $nonce, 'hcp_assign_actions_' . $post_id ) ) {
        wp_die( esc_html__( 'Invalid request.', 'hoa-coa-portal-pro' ) );
    }

    if ( ! current_user_can( HCP_Caps::CAP_MANAGE ) ) {
        wp_die( esc_html__( 'You do not have permission to do that.', 'hoa-coa-portal-pro' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post || ! in_array( $post->post_type, array( 'hcp_agenda', 'hcp_minutes' ), true ) ) {
        wp_die( esc_html__( 'Invalid item.', 'hoa-coa-portal-pro' ) );
    }

    delete_post_meta( $post_id, '_hcp_assigned_to' );

    $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . $post->post_type );
    HCP_Helpers::safe_redirect( $redirect  );
}

	public static function page_verification(): void {
    self::require_manage();

    $msg = isset( $_GET['hcp_msg'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hcp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message.

    $units = get_posts( array(
        'post_type'      => 'hcp_unit',
        'post_status'    => array( 'publish', 'private', 'draft' ),
        'numberposts'    => 5000,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'     => '_hcp_primary_owner',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
        ),
    ) );

    $total = 0;
    $verified = 0;
    foreach ( $units as $uid ) {
        $total++;
        if ( HCP_Units::unit_is_verified( (int) $uid ) ) {
            $verified++;
        }
    }
    $pct = $total > 0 ? round( ( $verified / $total ) * 100.0, 1 ) : 0;

    echo '<div class="wrap">';
		echo '<div class="hcp-print-actions">';
		echo '<a href="#" class="button button-primary" data-hcp-print-audit="1">' . esc_html__( 'Print', 'hoa-coa-portal-pro' ) . '</a>';
		echo '<span style="opacity:.8;">' . wp_kses_post( '<span class="hcp-screen-only">' . esc_html__( 'Tip: use Print to PDF for board records.', 'hoa-coa-portal-pro' ) . '</span>' ) . '</span>';
		echo '</div>'; // .hcp-print-area

		echo '<div class="hcp-print-area">';
    echo '<h1>' . esc_html__( 'Verification', 'hoa-coa-portal-pro' ) . '</h1>';

    if ( 'saved' === $msg ) {
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Verification updated.', 'hoa-coa-portal-pro' ) . '</p></div>';
    } elseif ( 'bad_unit' === $msg ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid unit.', 'hoa-coa-portal-pro' ) . '</p></div>';
    }

    echo '<p>' . esc_html__( 'Eligible units for voting/quorum are those with a Primary Voting Owner assigned. Verification is recommended for a stronger audit trail and access control.', 'hoa-coa-portal-pro' ) . '</p>';

    echo '<div class="hcp-admin-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin:14px 0;">';
    echo '<div class="hcp-card" style="padding:14px;background:#fff;border:1px solid #dcdcde;border-radius:10px;">';
    echo '<h2 style="margin:0 0 6px 0;font-size:16px;">' . esc_html__( 'Verification Progress', 'hoa-coa-portal-pro' ) . '</h2>';
    echo '<p style="margin:0;">' . esc_html( (string) $verified ) . ' / ' . esc_html( (string) $total ) . ' ' . esc_html__( 'units verified', 'hoa-coa-portal-pro' ) . ' (' . esc_html( (string) $pct ) . '%)</p>';
    echo '</div>';
    echo '</div>';

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Unit', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Primary Voting Owner', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Last Verified', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Action', 'hoa-coa-portal-pro' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $units as $unit_id ) {
        $unit_id = (int) $unit_id;
        $unit_label = HCP_Units::get_unit_number( $unit_id );
        $owner_id = (int) get_post_meta( $unit_id, '_hcp_primary_owner', true );
        $owner = $owner_id ? get_user_by( 'id', $owner_id ) : null;

        $status = HCP_Units::get_verification_status( $unit_id );
        $verified_at = (int) get_post_meta( $unit_id, '_hcp_verified_at', true );
        $verified_at_str = $verified_at ? gmdate( 'Y-m-d H:i:s', $verified_at ) . ' UTC' : '';

        echo '<tr>';
        echo '<td>' . esc_html( $unit_label ) . '</td>';
        echo '<td>' . ( $owner ? esc_html( $owner->user_login ) . ' <code>#' . esc_html( (string) $owner_id ) . '</code>' : esc_html__( '(none)', 'hoa-coa-portal-pro' ) ) . '</td>';

        echo '<td>';
        if ( 'verified_owner_affirmed' === $status ) {
            echo '<span class="badge" style="display:inline-block;padding:3px 8px;border-radius:999px;background:#e6f6e6;">' . esc_html__( 'Verified (Owner)', 'hoa-coa-portal-pro' ) . '</span>';
        } elseif ( 'verified_board_assigned' === $status ) {
            echo '<span class="badge" style="display:inline-block;padding:3px 8px;border-radius:999px;background:#e6f0ff;">' . esc_html__( 'Verified (Board)', 'hoa-coa-portal-pro' ) . '</span>';
        } else {
            echo '<span class="badge" style="display:inline-block;padding:3px 8px;border-radius:999px;background:#fff1e6;">' . esc_html__( 'Unverified', 'hoa-coa-portal-pro' ) . '</span>';
        }
        echo '</td>';

        echo '<td>' . esc_html( $verified_at_str ) . '</td>';

        echo '<td>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px;align-items:center;">';
        echo '<input type="hidden" name="action" value="hcp_set_unit_verification" />';
        echo '<input type="hidden" name="unit_id" value="' . esc_attr( (string) $unit_id ) . '" />';
        wp_nonce_field( 'hcp_set_unit_verification' );
        echo '<select name="status">';
        echo '<option value="unverified"' . selected( $status, 'unverified', false ) . '>' . esc_html__( 'Unverified', 'hoa-coa-portal-pro' ) . '</option>';
        echo '<option value="verified_owner_affirmed"' . selected( $status, 'verified_owner_affirmed', false ) . '>' . esc_html__( 'Verified (Owner)', 'hoa-coa-portal-pro' ) . '</option>';
        echo '<option value="verified_board_assigned"' . selected( $status, 'verified_board_assigned', false ) . '>' . esc_html__( 'Verified (Board)', 'hoa-coa-portal-pro' ) . '</option>';
        echo '</select>';
        echo '<button class="button" type="submit">' . esc_html__( 'Save', 'hoa-coa-portal-pro' ) . '</button>';
        echo '</form>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

public static function page_owner_access(): void {
    self::require_manage();

    echo '<div class="wrap">';
		echo '<div class="hcp-print-actions">';
		echo '<a href="#" class="button button-primary" data-hcp-print-audit="1">' . esc_html__( 'Print', 'hoa-coa-portal-pro' ) . '</a>';
		echo '<span style="opacity:.8;">' . wp_kses_post( '<span class="hcp-screen-only">' . esc_html__( 'Tip: use Print to PDF for board records.', 'hoa-coa-portal-pro' ) . '</span>' ) . '</span>';
		echo '</div>';
    echo '<h1>' . esc_html__( 'Owner Access', 'hoa-coa-portal-pro' ) . '</h1>';
    echo '<p>' . esc_html__( 'Owner Access is the transparency layer for owners: they can see which unit they are authorized to represent and a read-only log of their voting activity.', 'hoa-coa-portal-pro' ) . '</p>';
    echo '<div class="hcp-card" style="padding:14px;background:#fff;border:1px solid #dcdcde;border-radius:10px;max-width:900px;">';
    echo '<h2 style="margin-top:0;">' . esc_html__( 'What owners see', 'hoa-coa-portal-pro' ) . '</h2>';
    echo '<ul>';
    echo '<li>' . esc_html__( 'Assigned unit(s) and verification status.', 'hoa-coa-portal-pro' ) . '</li>';
    echo '<li>' . esc_html__( 'A read-only list of votes cast on their account.', 'hoa-coa-portal-pro' ) . '</li>';
    echo '</ul>';
    echo '<p><strong>' . esc_html__( 'Privacy:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html__( 'Owners only see their own vote history.', 'hoa-coa-portal-pro' ) . '</p>';
    echo '</div>';
    echo '</div>';
}

public static function page_dashboard(): void {
        self::require_manage();

        $settings = HCP_Helpers::settings();
        $portal_url = (int) $settings['portal_page_id'] > 0 ? get_permalink( (int) $settings['portal_page_id'] ) : '';

        $counts = array(
            'notices'   => (int) ( wp_count_posts( 'hcp_notice' )->publish ?? 0 ),
            'minutes'   => (int) ( wp_count_posts( 'hcp_minutes' )->publish ?? 0 ),
            'agendas'   => (int) ( wp_count_posts( 'hcp_agenda' )->publish ?? 0 ),
            'elections' => (int) ( wp_count_posts( 'hcp_election' )->publish ?? 0 ),
        );

        echo '<div class="wrap hcp-admin"><h1>' . esc_html__( 'HOA/COA Portal Dashboard', 'hoa-coa-portal-pro' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Manage your community content in one place: notices, meeting minutes, agendas, and elections.', 'hoa-coa-portal-pro' ) . '</p>';

        // Top cards
        echo '<div class="hcp-cards">';
        self::card( __( 'Notices', 'hoa-coa-portal-pro' ), $counts['notices'], admin_url( 'admin.php?page=hcp-notices' ), admin_url( 'admin.php?page=hcp-notices&action=add' ) );
        self::card( __( 'Minutes', 'hoa-coa-portal-pro' ), $counts['minutes'], admin_url( 'admin.php?page=hcp-minutes' ), admin_url( 'admin.php?page=hcp-minutes&action=add' ) );
        self::card( __( 'Agendas', 'hoa-coa-portal-pro' ), $counts['agendas'], admin_url( 'admin.php?page=hcp-agendas' ), admin_url( 'admin.php?page=hcp-agendas&action=add' ) );
        self::card( __( 'Elections', 'hoa-coa-portal-pro' ), $counts['elections'], admin_url( 'admin.php?page=hcp-elections' ), admin_url( 'admin.php?page=hcp-elections&action=add' ) );
        echo '</div>';

        echo '<div class="hcp-two-col" style="margin-top:16px">';
        echo '<div class="hcp-box">';
        echo '<h2>' . esc_html__( 'Quick Actions', 'hoa-coa-portal-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'Create content, assign it to owners/renters, and publish it to the portal.', 'hoa-coa-portal-pro' ) . '</p>';
        echo '<p>';
        if ( '' !== $portal_url ) {
            echo '<a class="button button-primary" href="' . esc_url( $portal_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Portal', 'hoa-coa-portal-pro' ) . '</a> ';
        }
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=hcp-notices&action=add' ) ) . '">' . esc_html__( 'Add Notice', 'hoa-coa-portal-pro' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=hcp-minutes&action=add' ) ) . '">' . esc_html__( 'Add Minutes', 'hoa-coa-portal-pro' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=hcp-elections&action=add' ) ) . '">' . esc_html__( 'Create Election', 'hoa-coa-portal-pro' ) . '</a>';
        echo '</p>';
        if ( '' === $portal_url ) {
            echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=hcp-settings' ) ) . '">' . esc_html__( 'Set Portal Page', 'hoa-coa-portal-pro' ) . '</a></p>';
        }
        echo '</div>';

        echo '<div class="hcp-box">';
        echo '<h2>' . esc_html__( 'Branding', 'hoa-coa-portal-pro' ) . ' ' . wp_kses_post( self::admin_tooltip( __( 'Help', 'hoa-coa-portal-pro' ), __( 'Set the association name and contact details. These appear in printable audit exports so the report clearly belongs to your HOA/COA.', 'hoa-coa-portal-pro' ) ) ) . '</h2>';
        $assoc_name = trim( (string) $settings['assoc_name'] );
        if ( $assoc_name ) {
            echo '<p><strong>' . esc_html( $assoc_name ) . '</strong></p>';
        } else {
            echo '<p><em>' . esc_html__( 'Add your HOA/COA details so the portal header shows your community name and contact info.', 'hoa-coa-portal-pro' ) . '</em></p>';
        }
        echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=hcp-settings' ) ) . '">' . esc_html__( 'Edit Branding & Settings', 'hoa-coa-portal-pro' ) . '</a></p>';
        echo '<hr/>';
        echo '<p style="margin:0"><small>' . esc_html__( 'Plugin by Sun Life Tech. Documentation will be available at plugins.sunlifetech.com.', 'hoa-coa-portal-pro' ) . '</small></p>';
                echo '<div class="hcp-box">';
        echo '<h2>' . esc_html__( 'Compliance Snapshot', 'hoa-coa-portal-pro' ) . ' ' . wp_kses_post( self::admin_tooltip( __( 'Help', 'hoa-coa-portal-pro' ), __( 'A quick checklist to help you meet Florida portal posting requirements. Upload categories you need and keep them updated.', 'hoa-coa-portal-pro' ) ) ) . '</h2>';

        $assoc_type = isset( $settings['assoc_type'] ) ? (string) $settings['assoc_type'] : 'condo';
        $unit_count = isset( $settings['unit_count'] ) ? absint( $settings['unit_count'] ) : 0;
        $parcel_count = isset( $settings['parcel_count'] ) ? absint( $settings['parcel_count'] ) : 0;

        $requires = false;
        if ( 'condo' === $assoc_type && $unit_count >= 25 ) { $requires = true; }
        if ( 'hoa' === $assoc_type && $parcel_count >= 100 ) { $requires = true; }

        echo '<p class="description" style="margin-top:0;">' . ( $requires ? esc_html__( 'Your association appears to meet the threshold where a secure owner portal is required. Use the list below to track your postings.', 'hoa-coa-portal-pro' ) : esc_html__( 'Even if you are below the statutory threshold, many associations still use these categories as best practice.', 'hoa-coa-portal-pro' ) ) . '</p>';

        $terms = get_terms( array( 'taxonomy' => HCP_Compliance::TAX, 'hide_empty' => false ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            echo '<p><em>' . esc_html__( 'No compliance categories found yet.', 'hoa-coa-portal-pro' ) . '</em></p>';
        } else {
            echo '<ul class="hcp-checklist">';
            foreach ( $terms as $t ) {
                if ( ! $t instanceof WP_Term ) { continue; }
                $cnt = 0;
                $q = new WP_Query(
                    array(
                        'post_type'      => HCP_Compliance::CPT,
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'no_found_rows'  => true,
                        'fields'         => 'ids',
                        'tax_query'      => array(
                            array(
                                'taxonomy' => HCP_Compliance::TAX,
                                'field'    => 'term_id',
                                'terms'    => array( (int) $t->term_id ),
                            ),
                        ),
                    )
                );
                $cnt = (int) $q->found_posts;

                echo '<li>' . ( $cnt > 0 ? '<span class="hcp-badge hcp-badge-ok">' . esc_html__( 'Uploaded', 'hoa-coa-portal-pro' ) . '</span>' : '<span class="hcp-badge hcp-badge-muted">' . esc_html__( 'Missing', 'hoa-coa-portal-pro' ) . '</span>' );
                echo ' <strong>' . esc_html( (string) $t->name ) . '</strong>';
                if ( $cnt > 0 ) {
                    echo ' <span class="description">(' . esc_html( (string) $cnt ) . ')</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . HCP_Compliance::CPT ) ) . '">' . esc_html__( 'Manage Compliance Docs', 'hoa-coa-portal-pro' ) . '</a></p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private static function card( string $title, int $count, string $view_url, string $add_url ): void {
        echo '<div class="hcp-card"><h2>' . esc_html( $title ) . '</h2>';
        echo '<div class="hcp-card-count">' . esc_html( (string) $count ) . '</div>';
        echo '<div><a class="button" href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'hoa-coa-portal-pro' ) . '</a> ';
        echo '<a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add New', 'hoa-coa-portal-pro' ) . '</a></div></div>';
    }

    // ===== List + edit screens =====
    public static function page_notices(): void { self::require_manage(); self::crud_screen( 'hcp_notice', 'notice', __( 'Notices', 'hoa-coa-portal-pro' ) ); }
    public static function page_minutes(): void { self::require_manage(); self::crud_screen( 'hcp_minutes', 'minutes', __( 'Minutes', 'hoa-coa-portal-pro' ) ); }
    public static function page_agendas(): void { self::require_manage(); self::crud_screen( 'hcp_agenda', 'agenda', __( 'Agendas', 'hoa-coa-portal-pro' ) ); }
    public static function page_owner_docs(): void { self::require_manage(); self::crud_screen( 'hcp_owner_doc', 'owner_doc', __( 'Owner Documents', 'hoa-coa-portal-pro' ) ); }
    public static function page_elections(): void { self::require_manage(); self::elections_screen(); }
    public static function page_results(): void { self::require_manage(); self::results_screen(); }
    

public static function page_access_roles(): void {
    self::require_manage();

    $roles = wp_roles();
    $role_map = array(
        'administrator' => __( 'Administrator', 'hoa-coa-portal-pro' ),
        'hcp_staff'     => __( 'HOA/COA Staff', 'hoa-coa-portal-pro' ),
        'hcp_board'     => __( 'HOA/COA Board Member', 'hoa-coa-portal-pro' ),
        'subscriber'    => __( 'Subscriber (typical owner/renter)', 'hoa-coa-portal-pro' ),
    );

    $caps = array(
        'hcp_manage_settings'   => __( 'Manage Settings', 'hoa-coa-portal-pro' ),
        'hcp_manage_units'      => __( 'Manage Units', 'hoa-coa-portal-pro' ),
        'hcp_verify_owners'     => __( 'Verify Owners / Units', 'hoa-coa-portal-pro' ),
        'hcp_manage_content'    => __( 'Manage Notices/Minutes/Agendas/Docs', 'hoa-coa-portal-pro' ),
        'hcp_manage_elections'  => __( 'Create / Edit Elections', 'hoa-coa-portal-pro' ),
        'hcp_finalize_elections'=> __( 'Finalize Results', 'hoa-coa-portal-pro' ),
        'hcp_view_owner_portal' => __( 'View Owner Portal (role gate)', 'hoa-coa-portal-pro' ),
    );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Access & Roles', 'hoa-coa-portal-pro' ) . '</h1>';
    echo '<p>' . esc_html__( 'This page summarizes which WordPress roles have HOA/COA Portal capabilities. Voting eligibility is unit-based: only users assigned as Primary Voting Owner for a unit can vote for that unit.', 'hoa-coa-portal-pro' ) . '</p>';
            if ( isset( $_GET['hcp_repaired'] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Roles & capabilities were repaired successfully.', 'hoa-coa-portal-pro' ) . '</p></div>';
            }
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="hcp_repair_roles" />';
            wp_nonce_field( 'hcp_repair_roles' );
            submit_button( __( 'Repair Roles & Capabilities', 'hoa-coa-portal-pro' ), 'secondary', 'submit', false );
            echo '</form>';
    

    echo '<div class="hcp-grid">';
    echo '<div class="hcp-card"><h2>' . esc_html__( 'Roles', 'hoa-coa-portal-pro' ) . '</h2>';
    echo '<ul class="ul-disc">';
    foreach ( $role_map as $key => $label ) {
        $exists = $roles && $roles->is_role( $key );
        echo '<li><strong>' . esc_html( $label ) . '</strong> — ' . ( $exists ? esc_html__( 'Installed', 'hoa-coa-portal-pro' ) : esc_html__( 'Not present on this site', 'hoa-coa-portal-pro' ) ) . '</li>';
    }
    echo '</ul></div>';

    echo '<div class="hcp-card"><h2>' . esc_html__( 'Capability Matrix', 'hoa-coa-portal-pro' ) . '</h2>';
    echo '<div class="hcp-table-wrap"><table class="widefat striped">';
    echo '<thead><tr><th>' . esc_html__( 'Capability', 'hoa-coa-portal-pro' ) . '</th>';
    foreach ( $role_map as $role_key => $role_label ) {
        echo '<th>' . esc_html( $role_label ) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ( $caps as $cap_key => $cap_label ) {
        echo '<tr><td><strong>' . esc_html( $cap_label ) . '</strong><br><code>' . esc_html( $cap_key ) . '</code></td>';
        foreach ( $role_map as $role_key => $role_label ) {
            $has = false;
            if ( 'administrator' === $role_key ) {
                $has = true; // Admin always effectively has access; WP core uses caps map.
            } else {
                $r = $roles ? $roles->get_role( $role_key ) : null;
                $has = $r ? $r->has_cap( $cap_key ) : false;
            }
            echo '<td>' . ( $has ? '<span class="hcp-badge hcp-badge-ok">' . esc_html__( 'Yes', 'hoa-coa-portal-pro' ) . '</span>' : '<span class="hcp-badge hcp-badge-muted">' . esc_html__( 'No', 'hoa-coa-portal-pro' ) . '</span>' ) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '<p class="description">' . esc_html__( 'Tip: You can assign the Staff or Board Member role in Users → All Users. Owners and renters usually remain Subscribers, with access controlled by unit assignment.', 'hoa-coa-portal-pro' ) . '</p>';
    echo '</div></div>'; // card + grid

    echo '</div>';
}



public static function repair_roles(): void {
    self::require_manage();
    check_admin_referer( 'hcp_repair_roles' );

    if ( class_exists( 'HCP_Caps' ) ) {
        HCP_Caps::activate();
    }

    wp_safe_redirect( admin_url( 'admin.php?page=hcp-access-roles&hcp_repaired=1' ) );
    exit;
}

public static function page_settings(): void { self::require_manage(); self::settings_screen(); }

    private static function crud_screen( string $post_type, string $slug, string $title ): void {
        $action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : 'list';
        $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        echo '<div class="wrap hcp-admin"><h1>' . esc_html( $title ) . '</h1>';

        if ( 'add' === $action || ( 'edit' === $action && $id > 0 ) ) {
            $post = $id > 0 ? get_post( $id ) : null;
            if ( $id > 0 && ( ! $post || $post->post_type !== $post_type ) ) {
                echo '<p>' . esc_html__( 'Item not found.', 'hoa-coa-portal-pro' ) . '</p></div>';
                return;
            }
            self::render_edit_form( $post_type, $slug, $post );
            echo '</div>';
            return;
        }

        // List
        $page_slug = self::page_slug( $slug );
        echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . $page_slug . '&action=add' ) ) . '">' . esc_html__( 'Add New', 'hoa-coa-portal-pro' ) . '</a></p>';

        $q = new WP_Query( array(
            'post_type'      => $post_type,
            'post_status'    => array( 'draft', 'publish' ),
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        echo '<div class="hcp-print-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Status', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Date', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Assigned By', 'hoa-coa-portal-pro' ) . '</th></tr></thead><tbody>';

        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) {
                $q->the_post();
                $edit = admin_url( 'admin.php?page=' . self::page_slug( $slug ) . '&action=edit&id=' . get_the_ID() );
                echo '<tr><td><a href="' . esc_url( $edit ) . '">' . esc_html( get_the_title() ) . '</a></td><td>' . esc_html( get_post_status() ) . '</td><td>' . esc_html( get_the_date() ) . '</td></tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="3"><em>' . esc_html__( 'No items yet.', 'hoa-coa-portal-pro' ) . '</em></td></tr>';
        }

        echo '</tbody></table></div></div>';
    }

    private static function render_edit_form( string $post_type, string $slug, ?WP_Post $post ): void {
        $is_notice  = ( 'hcp_notice' === $post_type );
        $is_minutes = ( 'hcp_minutes' === $post_type );
        $is_agenda  = ( 'hcp_agenda' === $post_type );

        $settings   = get_option( 'hcp_settings', array() );
        $assoc_type = isset( $settings['assoc_type'] ) ? sanitize_key( (string) $settings['assoc_type'] ) : 'hoa';
        if ( ! in_array( $assoc_type, array( 'hoa', 'condo' ), true ) ) {
            $assoc_type = 'hoa';
        }

        $title = $post ? $post->post_title : '';
        $body  = $post ? $post->post_content : '';

        $audience = $post ? (string) get_post_meta( $post->ID, '_hcp_audience', true ) : 'both';
        if ( '' === $audience ) { $audience = 'both'; }

        $meeting_date = $post ? (string) get_post_meta( $post->ID, '_hcp_meeting_date', true ) : '';
        $featured     = $post ? (int) get_post_meta( $post->ID, '_hcp_featured', true ) : 0;
        $pinned       = $post ? (int) get_post_meta( $post->ID, '_hcp_pinned', true ) : 0;

        $attachment_ids = $post ? get_post_meta( $post->ID, '_hcp_attachments', true ) : array();
        if ( ! is_array( $attachment_ids ) ) { $attachment_ids = array(); }
        $attachment_ids = array_values( array_filter( array_map( 'absint', $attachment_ids ) ) );
        $attachment_csv = implode( ',', $attachment_ids );

        $save_action = 'hcp_save_' . $slug;
        $back_url = admin_url( 'admin.php?page=' . self::page_slug( $slug ) );

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'hcp_save_' . $slug );
        echo '<input type="hidden" name="action" value="' . esc_attr( $save_action ) . '"/>';
        echo '<input type="hidden" name="post_type" value="' . esc_attr( $post_type ) . '"/>';
        echo '<input type="hidden" name="id" value="' . esc_attr( $post ? (string) $post->ID : '0' ) . '"/>';

        echo '<div class="hcp-two-col">';
        echo '<div class="hcp-box">';
        echo '<div class="hcp-meta-row"><label for="hcp_title">' . esc_html__( 'Title', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_title" name="hcp_title" type="text" class="widefat" value="' . esc_attr( $title ) . '"/></div>';

        echo '<div class="hcp-meta-row"><label>' . esc_html__( 'Body', 'hoa-coa-portal-pro' ) . '</label>';
        wp_editor( $body, 'hcp_body', array(
            'textarea_name' => 'hcp_body',
            'media_buttons' => false,
            'teeny'         => false,
        ) );
        echo '</div>';

        echo '</div>'; // left

        echo '<div class="hcp-box">';
        echo '<div class="hcp-meta-row"><label>' . esc_html__( 'Audience', 'hoa-coa-portal-pro' ) . '</label>';
        foreach ( array( 'both' => __( 'Owners + Office', 'hoa-coa-portal-pro' ), 'owner' => __( 'Owners only', 'hoa-coa-portal-pro' ), 'office' => __( 'Office only', 'hoa-coa-portal-pro' ) ) as $k => $lab ) {
            echo '<label><input type="radio" name="hcp_audience" value="' . esc_attr( $k ) . '" ' . checked( $audience, $k, false ) . '/> ' . esc_html( $lab ) . '</label><br/>';
        }
        echo '</div>';

        if ( $is_minutes || $is_agenda ) {
            echo '<div class="hcp-meta-row"><label for="hcp_meeting_date">' . esc_html__( 'Meeting date', 'hoa-coa-portal-pro' ) . '</label>';
            echo '<input id="hcp_meeting_date" name="hcp_meeting_date" type="date" class="widefat" value="' . esc_attr( $meeting_date ) . '"/></div>';
        }

        if ( $is_notice ) {
            echo '<div class="hcp-meta-row"><label><input type="checkbox" name="hcp_featured" value="1" ' . checked( 1, $featured, false ) . '/> ' . esc_html__( 'Featured', 'hoa-coa-portal-pro' ) . '</label></div>';
            echo '<div class="hcp-meta-row"><label><input type="checkbox" name="hcp_pinned" value="1" ' . checked( 1, $pinned, false ) . '/> ' . esc_html__( 'Pinned', 'hoa-coa-portal-pro' ) . '</label></div>';
        }

        // Attachments
        echo '<div class="hcp-meta-row hcp-attachments">';
        echo '<label>' . esc_html__( 'Attachments (PDF/images)', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input type="hidden" class="hcp-attachment-ids" name="hcp_attachments" value="' . esc_attr( $attachment_csv ) . '"/>';
        echo '<button class="button hcp-add-files">' . esc_html__( 'Add Files', 'hoa-coa-portal-pro' ) . '</button>';
        echo '<ul class="hcp-attachment-list">';
        if ( empty( $attachment_ids ) ) {
            echo '<li><em>' . esc_html__( 'No files selected.', 'hoa-coa-portal-pro' ) . '</em></li>';
        } else {
            foreach ( $attachment_ids as $aid ) {
                echo '<li data-id="' . esc_attr( (string) $aid ) . '">#' . esc_html( (string) $aid ) . ' <a href="#" class="hcp-remove-file">' . esc_html__( 'Remove', 'hoa-coa-portal-pro' ) . '</a></li>';
            }
        }
        echo '</ul></div>';

        // Status + buttons
        $status = $post ? $post->post_status : 'draft';
        echo '<div class="hcp-meta-row"><label for="hcp_status">' . esc_html__( 'Status', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<select id="hcp_status" name="hcp_status" class="widefat">';
        foreach ( array( 'draft' => __( 'Draft', 'hoa-coa-portal-pro' ), 'publish' => __( 'Published', 'hoa-coa-portal-pro' ) ) as $k => $lab ) {
            echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, $k, false ) . '>' . esc_html( $lab ) . '</option>';
        }
        echo '</select>';
        if ( 'condo' === $assoc_type ) {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida Condo (FS 718): Associations with 25+ units must provide a secure owner website/portal (effective Jan 1, 2026).', 'hoa-coa-portal-pro' ) . '</p>';
        } else {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida HOA (FS 720): Associations with 100+ parcels must provide a secure owner website/portal (effective Jan 1, 2025).', 'hoa-coa-portal-pro' ) . '</p>';
        }
        echo '</div>';

        echo '<div class="hcp-meta-row">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save', 'hoa-coa-portal-pro' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Back', 'hoa-coa-portal-pro' ) . '</a>';
        echo '</div>';

        echo '</div>'; // right
        echo '</div></form>';
    }

    private static function elections_screen(): void {
        $settings   = get_option( 'hcp_settings', array() );
        $assoc_type = isset( $settings['assoc_type'] ) ? sanitize_key( (string) $settings['assoc_type'] ) : 'hoa';
        if ( ! in_array( $assoc_type, array( 'hoa', 'condo' ), true ) ) {
            $assoc_type = 'hoa';
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : 'list';
        $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        echo '<div class="wrap hcp-admin"><h1>' . esc_html__( 'Elections', 'hoa-coa-portal-pro' ) . '</h1>';

        if ( 'add' === $action || ( 'edit' === $action && $id > 0 ) ) {
            $post = $id > 0 ? get_post( $id ) : null;
            if ( $id > 0 && ( ! $post || 'hcp_election' !== $post->post_type ) ) {
                echo '<p>' . esc_html__( 'Election not found.', 'hoa-coa-portal-pro' ) . '</p></div>';
                return;
            }

            $title = $post ? $post->post_title : '';
            $body  = $post ? $post->post_content : '';
            $status = $post ? $post->post_status : 'draft';

            $start = $post ? (int) get_post_meta( $post->ID, '_hcp_start_at', true ) : 0;
            $end   = $post ? (int) get_post_meta( $post->ID, '_hcp_end_at', true ) : 0;
            $e_status = $post ? (string) get_post_meta( $post->ID, '_hcp_status', true ) : 'draft';

            $q_mode = $post ? (string) get_post_meta( $post->ID, '_hcp_quorum_mode', true ) : 'units';
            if ( '' === $q_mode ) { $q_mode = 'units'; }
            $q_percent = $post ? (float) get_post_meta( $post->ID, '_hcp_quorum_percent', true ) : 0.0;
            if ( $q_percent < 0 ) { $q_percent = 0; }
            if ( $q_percent > 100 ) { $q_percent = 100; }


            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'hcp_save_election' );
            echo '<input type="hidden" name="action" value="hcp_save_election"/>';
            echo '<input type="hidden" name="id" value="' . esc_attr( $post ? (string) $post->ID : '0' ) . '"/>';

            echo '<div class="hcp-two-col"><div class="hcp-box">';
            echo '<div class="hcp-meta-row"><label for="hcp_title">' . esc_html__( 'Title', 'hoa-coa-portal-pro' ) . '</label>';
            echo '<input id="hcp_title" name="hcp_title" type="text" class="widefat" value="' . esc_attr( $title ) . '"/></div>';

            echo '<div class="hcp-meta-row"><label>' . esc_html__( 'Description', 'hoa-coa-portal-pro' ) . '</label>';
            wp_editor( $body, 'hcp_body', array(
                'textarea_name' => 'hcp_body',
                'media_buttons' => false,
                'teeny'         => false,
            ) );
            echo '</div></div>';

            echo '<div class="hcp-box">';
            echo '<div class="hcp-meta-row"><label>' . esc_html__( 'Voting window', 'hoa-coa-portal-pro' ) . '</label>';
            echo '<input type="datetime-local" class="widefat" name="hcp_start" value="' . esc_attr( $start ? gmdate( 'Y-m-d\TH:i', $start ) : '' ) . '"/><br/><br/>';
            echo '<input type="datetime-local" class="widefat" name="hcp_end" value="' . esc_attr( $end ? gmdate( 'Y-m-d\TH:i', $end ) : '' ) . '"/></div>';

            echo '<div class="hcp-meta-row"><label for="hcp_e_status">' . esc_html__( 'Election status', 'hoa-coa-portal-pro' ) . '</label>';
            echo '<select id="hcp_e_status" name="hcp_e_status" class="widefat">';
            foreach ( array( 'draft' => __( 'Draft', 'hoa-coa-portal-pro' ), 'published' => __( 'Published', 'hoa-coa-portal-pro' ), 'closed' => __( 'Closed', 'hoa-coa-portal-pro' ) ) as $k => $lab ) {
                echo '<option value="' . esc_attr( $k ) . '" ' . selected( $e_status, $k, false ) . '>' . esc_html( $lab ) . '</option>';
            }
            echo '</select>';
        if ( 'condo' === $assoc_type ) {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida Condo (FS 718): Associations with 25+ units must provide a secure owner website/portal (effective Jan 1, 2026).', 'hoa-coa-portal-pro' ) . '</p>';
        } else {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida HOA (FS 720): Associations with 100+ parcels must provide a secure owner website/portal (effective Jan 1, 2025).', 'hoa-coa-portal-pro' ) . '</p>';
        }
        echo '</div>';

            echo '<div class="hcp-meta-row"><label for="hcp_status">' . esc_html__( 'Post status', 'hoa-coa-portal-pro' ) . '</label>';
            echo '<select id="hcp_status" name="hcp_status" class="widefat">';
            foreach ( array( 'draft' => __( 'Draft', 'hoa-coa-portal-pro' ), 'publish' => __( 'Published', 'hoa-coa-portal-pro' ) ) as $k => $lab ) {
                echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, $k, false ) . '>' . esc_html( $lab ) . '</option>';
            }
            echo '</select>';
        if ( 'condo' === $assoc_type ) {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida Condo (FS 718): Associations with 25+ units must provide a secure owner website/portal (effective Jan 1, 2026).', 'hoa-coa-portal-pro' ) . '</p>';
        } else {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida HOA (FS 720): Associations with 100+ parcels must provide a secure owner website/portal (effective Jan 1, 2025).', 'hoa-coa-portal-pro' ) . '</p>';
        }
        echo '</div>';

            echo '<div class="hcp-meta-row"><p><strong>' . esc_html__( 'Choices', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html__( 'Yes / No / Abstain (free version)', 'hoa-coa-portal-pro' ) . '</p></div>';

            echo '<div class="hcp-meta-row"><label>' . esc_html__( 'Quorum (free version)', 'hoa-coa-portal-pro' ) . '</label>';
            echo '<select name="hcp_quorum_mode" class="widefat" style="max-width:320px">';
            echo '<option value="units" ' . selected( $q_mode, 'units', false ) . '>' . esc_html__( 'By units (count)', 'hoa-coa-portal-pro' ) . '</option>';
            echo '<option value="weight" ' . selected( $q_mode, 'weight', false ) . '>' . esc_html__( 'By voting weight (sum)', 'hoa-coa-portal-pro' ) . '</option>';
            echo '</select><br/><br/>';
            echo '<input type="number" name="hcp_quorum_percent" min="0" max="100" step="0.01" class="widefat" style="max-width:200px" value="' . esc_attr( (string) $q_percent ) . '"/>';
            echo '<p class="description">' . esc_html__( 'Percentage required to meet quorum. Set to 0 for no quorum requirement.', 'hoa-coa-portal-pro' ) . '</p></div>';

            echo '<div class="hcp-meta-row"><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'hoa-coa-portal-pro' ) . '</button> ';
            echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=hcp-elections' ) ) . '">' . esc_html__( 'Back', 'hoa-coa-portal-pro' ) . '</a></div>';

            echo '</div></div></form></div>';
            return;
        }

        echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=hcp-elections&action=add' ) ) . '">' . esc_html__( 'Add New Election', 'hoa-coa-portal-pro' ) . '</a></p>';

        $q = new WP_Query( array(
            'post_type'      => 'hcp_election',
            'post_status'    => array( 'draft', 'publish' ),
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        echo '<div class="hcp-print-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Election status', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Date', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Assigned By', 'hoa-coa-portal-pro' ) . '</th></tr></thead><tbody>';
        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) {
                $q->the_post();
                $e_status = (string) get_post_meta( get_the_ID(), '_hcp_status', true );
                $edit = admin_url( 'admin.php?page=hcp-elections&action=edit&id=' . get_the_ID() );
                echo '<tr><td><a href="' . esc_url( $edit ) . '">' . esc_html( get_the_title() ) . '</a></td><td>' . esc_html( $e_status ? $e_status : 'draft' ) . '</td><td>' . esc_html( get_the_date() ) . '</td></tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="3"><em>' . esc_html__( 'No elections yet.', 'hoa-coa-portal-pro' ) . '</em></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function results_screen(): void {
        echo '<div class="wrap hcp-admin"><h1>' . esc_html__( 'Results', 'hoa-coa-portal-pro' ) . '</h1>';
        echo '<p>' . esc_html__( 'Select an election to view tallied results.', 'hoa-coa-portal-pro' ) . '</p>';

        $elections = get_posts( array(
            'post_type'      => 'hcp_election',
            'post_status'    => array( 'draft', 'publish' ),
            'numberposts'    => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $selected = isset( $_GET['election_id'] ) ? absint( $_GET['election_id'] ) : 0;

        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="hcp-results"/>';
        echo '<select name="election_id" class="widefat" style="max-width:420px">';
        echo '<option value="0">' . esc_html__( 'Choose election…', 'hoa-coa-portal-pro' ) . '</option>';
        foreach ( $elections as $e ) {
            echo '<option value="' . esc_attr( (string) $e->ID ) . '" ' . selected( $selected, $e->ID, false ) . '>' . esc_html( $e->post_title ) . '</option>';
        }
        echo '</select> <button class="button">' . esc_html__( 'View', 'hoa-coa-portal-pro' ) . '</button></form>';

        if ( $selected > 0 ) {
            $t = HCP_Tally::get( $selected );

            echo '<h2>' . esc_html( get_the_title( $selected ) ) . '</h2>';

            $finalized = (int) get_post_meta( $selected, '_hcp_finalized', true );
            $finalized_at = (int) get_post_meta( $selected, '_hcp_finalized_at', true );
            $finalized_by = (int) get_post_meta( $selected, '_hcp_finalized_by', true );

            if ( isset( $_GET['finalized'] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Results finalized.', 'hoa-coa-portal-pro' ) . '</p></div>';
            }

            echo '<div class="hcp-box" style="max-width:920px">';
            echo '<h3>' . esc_html__( 'Official Status', 'hoa-coa-portal-pro' ) . '</h3>';

            if ( $finalized ) {
                $by_name = $finalized_by ? (string) get_the_author_meta( 'display_name', $finalized_by ) : '';
                $at = $finalized_at ? gmdate( 'Y-m-d H:i:s', $finalized_at ) . ' UTC' : '';
                echo '<p><strong>' . esc_html__( 'Finalized', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html__( 'Yes', 'hoa-coa-portal-pro' ) . '</p>';
                if ( '' !== $by_name ) { echo '<p><strong>' . esc_html__( 'Finalized by', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( $by_name ) . '</p>'; }
                if ( '' !== $at ) { echo '<p><strong>' . esc_html__( 'Finalized at', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( $at ) . '</p>'; }
            } else {
                echo '<p><strong>' . esc_html__( 'Finalized', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html__( 'No', 'hoa-coa-portal-pro' ) . '</p>';
                echo '<p class="description">' . esc_html__( 'Results are unofficial until finalized. Finalization is only allowed when quorum is met.', 'hoa-coa-portal-pro' ) . '</p>';
            }

            echo '<div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px">';

            if ( ! $finalized ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'hcp_finalize_results' );
                echo '<input type="hidden" name="action" value="hcp_finalize_results"/>';
                echo '<input type="hidden" name="election_id" value="' . esc_attr( (string) $selected ) . '"/>';
                echo '<button class="button button-primary" ' . ( $t['quorum_met'] ? '' : 'disabled' ) . '>' . esc_html__( 'Finalize Results', 'hoa-coa-portal-pro' ) . '</button>';
                if ( ! $t['quorum_met'] ) {
                    echo ' <span class="description">' . esc_html__( 'Quorum not met', 'hoa-coa-portal-pro' ) . '</span>';
                }
                echo '</form>';
            }

            $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=hcp_export_audit_csv&election_id=' . $selected ), 'hcp_export_audit' );
            $export_pdf_url = wp_nonce_url( admin_url( 'admin-post.php?action=hcp_export_audit_pdf&election_id=' . $selected ), 'hcp_export_audit' );

            echo '<a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export Audit CSV', 'hoa-coa-portal-pro' ) . '</a>';
            echo '<a class="button" href="' . esc_url( $export_pdf_url ) . '">' . esc_html__( 'Export Audit PDF', 'hoa-coa-portal-pro' ) . '</a>';

            echo '</div></div>';


            echo '<div class="hcp-box" style="max-width:920px">';
            echo '<h3>' . esc_html__( 'Quorum', 'hoa-coa-portal-pro' ) . '</h3>';

            $mode_label = ( 'weight' === $t['quorum_mode'] ) ? __( 'By voting weight', 'hoa-coa-portal-pro' ) : __( 'By units', 'hoa-coa-portal-pro' );

            echo '<p><strong>' . esc_html__( 'Mode', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( $mode_label ) . '<br/>';
            echo '<strong>' . esc_html__( 'Required', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( (string) $t['quorum_percent'] ) . "%</p>";

            if ( 'weight' === $t['quorum_mode'] ) {
                echo '<p><strong>' . esc_html__( 'Progress', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( number_format_i18n( (float) $t['voted_weight'], 2 ) ) . ' / ' . esc_html( number_format_i18n( (float) $t['eligible_weight'], 2 ) ) . '</p>';
                if ( $t['required_weight'] > 0 ) {
                    echo '<p><strong>' . esc_html__( 'Threshold', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( number_format_i18n( (float) $t['required_weight'], 2 ) ) . '</p>';
                }
            } else {
                echo '<p><strong>' . esc_html__( 'Progress', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( (string) $t['voted_units'] ) . ' / ' . esc_html( (string) $t['eligible_units'] ) . '</p>';
                if ( $t['required_units'] > 0 ) {
                    echo '<p><strong>' . esc_html__( 'Threshold', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( (string) $t['required_units'] ) . '</p>';
                }
            }

            echo '<p><strong>' . esc_html__( 'Quorum met', 'hoa-coa-portal-pro' ) . ':</strong> ' . ( $t['quorum_met'] ? esc_html__( 'Yes', 'hoa-coa-portal-pro' ) : esc_html__( 'No', 'hoa-coa-portal-pro' ) ) . '</p>';
            echo '</div>';

            echo '<div class="hcp-print-table" style="max-width:920px"><table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__( 'Choice', 'hoa-coa-portal-pro' ) . '</th>';
            echo '<th>' . esc_html__( 'Units', 'hoa-coa-portal-pro' ) . '</th>';
            echo '<th>' . esc_html__( 'Weighted', 'hoa-coa-portal-pro' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( array( 'yes', 'no', 'abstain' ) as $choice ) {
                $u = (int) ( $t['by_choice_units'][ $choice ] ?? 0 );
                $w = (float) ( $t['by_choice_weight'][ $choice ] ?? 0 );
                echo '<tr>';
                echo '<td>' . esc_html( ucfirst( $choice ) ) . '</td>';
                echo '<td>' . esc_html( (string) $u ) . '</td>';
                echo '<td>' . esc_html( number_format_i18n( $w, 2 ) ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</div>';
    }

    
private static function get_audit_rows( int $election_id ): array {
    $rows = array();

    $votes = get_posts( array(
        'post_type'      => 'hcp_vote',
        'post_status'    => array( 'private', 'publish', 'draft' ),
        'numberposts'    => -1,
        'fields'         => 'ids',
        'post_parent'    => $election_id,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ) );

    $seen_units = array();
    foreach ( $votes as $vid ) {
        $vid = (int) $vid;
        $unit_id = (int) get_post_meta( $vid, '_hcp_unit_id', true );
        if ( $unit_id <= 0 || isset( $seen_units[ $unit_id ] ) ) {
            continue;
        }
        $seen_units[ $unit_id ] = true;

        $choice = (string) get_post_meta( $vid, '_hcp_choice', true );
        $weight = (float) get_post_meta( $vid, '_hcp_unit_weight', true );
        if ( $weight <= 0 ) { $weight = HCP_Units::get_unit_weight( $unit_id ); }

        $submitted_at = (int) get_post_meta( $vid, '_hcp_submitted_at', true );
        $submitted_at_str = $submitted_at ? gmdate( 'Y-m-d H:i:s', $submitted_at ) . ' UTC' : '';

        $author_id = (int) get_post_field( 'post_author', $vid );
        $author = $author_id ? get_user_by( 'id', $author_id ) : null;

        $vote_hash = (string) get_post_meta( $vid, '_hcp_vote_hash', true );
if ( '' === $vote_hash ) {
    $payload = $election_id . '|' . (int) $vid . '|' . $unit_id . '|' . $choice . '|' . (string) $weight . '|' . (string) $submitted_at . '|' . (string) $author_id;
    $vote_hash = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
}

$ip = (string) get_post_meta( $vid, '_hcp_ip', true );
$ua = (string) get_post_meta( $vid, '_hcp_user_agent', true );

$rows[] = array(
    'vote_id'        => (int) $vid,
    'unit_id'        => $unit_id,
    'unit_number'    => HCP_Units::get_unit_number( $unit_id ),
    'choice'         => $choice,
    'weight'         => $weight,
    'submitted_at'   => $submitted_at_str,
    'submitted_by'   => $author ? $author->user_login : '',
    'submitted_by_id'=> $author_id,
    'submitted_email'=> $author ? $author->user_email : '',
    'ip'             => $ip,
    'user_agent'     => $ua,
    'vote_hash'      => $vote_hash,
);
}

    return $rows;
}

public static function finalize_results(): void {
    if ( ! HCP_Access::can_finalize_elections() ) {
        wp_die( esc_html__( 'You do not have permission to finalize elections.', 'hoa-coa-portal-pro' ) );
    }
    check_admin_referer( 'hcp_finalize_results' );

    $election_id = isset( $_POST['election_id'] ) ? absint( $_POST['election_id'] ) : 0;
    if ( $election_id <= 0 ) {
        wp_die( esc_html__( 'Invalid election.', 'hoa-coa-portal-pro' ) );
    }

    $t = HCP_Tally::get( $election_id );
    $rows = self::get_audit_rows( $election_id );

    $finalized = (int) get_post_meta( $election_id, '_hcp_finalized', true );
    $finalized_at_ts = (int) get_post_meta( $election_id, '_hcp_finalized_at', true );
    $finalized_at = $finalized_at_ts ? gmdate( 'Y-m-d H:i:s', $finalized_at_ts ) . ' UTC' : '';
    $finalized_by = (int) get_post_meta( $election_id, '_hcp_finalized_by', true );

    $t = HCP_Tally::get( $election_id );
    $election_hash = (string) get_post_meta( $election_id, '_hcp_audit_hash', true );
    if ( '' === $election_hash ) { $election_hash = self::compute_election_audit_hash( $election_id, $t, $rows ); }

    $audit_hash = self::compute_election_audit_hash( $election_id, $t, $rows );
    if ( ! $t['quorum_met'] ) {
        wp_die( esc_html__( 'Quorum is not met. You cannot finalize results yet.', 'hoa-coa-portal-pro' ) );
    }

    update_post_meta( $election_id, '_hcp_finalized', 1 );
    update_post_meta( $election_id, '_hcp_finalized_at', time() );
    update_post_meta( $election_id, '_hcp_finalized_by', get_current_user_id() );
    update_post_meta( $election_id, '_hcp_audit_hash', $audit_hash );
    update_post_meta( $election_id, '_hcp_status', 'closed' );

    wp_safe_redirect( admin_url( 'admin.php?page=hcp-results&election_id=' . $election_id . '&finalized=1' ) );
    exit;
}

private static function compute_election_audit_hash( int $election_id, array $tally, array $rows ): string {
    $parts = array(
        'election_id' => $election_id,
        'finalized'   => (int) get_post_meta( $election_id, '_hcp_finalized', true ),
        'finalized_at'=> (int) get_post_meta( $election_id, '_hcp_finalized_at', true ),
        'finalized_by'=> (int) get_post_meta( $election_id, '_hcp_finalized_by', true ),
        'tally'       => array(
            'eligible_units'  => (int) ( $tally['eligible_units'] ?? 0 ),
            'eligible_weight' => (float) ( $tally['eligible_weight'] ?? 0 ),
            'voted_units'     => (int) ( $tally['voted_units'] ?? 0 ),
            'voted_weight'    => (float) ( $tally['voted_weight'] ?? 0 ),
            'quorum_met'      => (bool) ( $tally['quorum_met'] ?? false ),
            'quorum_percent'  => (float) ( $tally['quorum_percent'] ?? 0 ),
            'quorum_mode'     => (string) ( $tally['quorum_mode'] ?? '' ),
            'results'         => (array) ( $tally['results'] ?? array() ),
        ),
        'votes'       => array_map( static function( $r ) {
            return array(
                'vote_id'   => (int) ( $r['vote_id'] ?? 0 ),
                'unit_id'   => (int) ( $r['unit_id'] ?? 0 ),
                'choice'    => (string) ( $r['choice'] ?? '' ),
                'weight'    => (float) ( $r['weight'] ?? 0 ),
                'submitted' => (string) ( $r['submitted_at'] ?? '' ),
                'hash'      => (string) ( $r['vote_hash'] ?? '' ),
            );
        }, $rows ),
    );

    $json = wp_json_encode( $parts );
    if ( false === $json ) { $json = ''; }
    return hash_hmac( 'sha256', $json, wp_salt( 'auth' ) );
}

public static function export_audit_csv(): void {
    self::require_manage();
    check_admin_referer( 'hcp_export_audit' );

    $election_id = isset( $_GET['election_id'] ) ? absint( $_GET['election_id'] ) : 0;
    if ( $election_id <= 0 ) { wp_die( esc_html__( 'Invalid election.', 'hoa-coa-portal-pro' ) ); }

    $rows = self::get_audit_rows( $election_id );

    $finalized = (int) get_post_meta( $election_id, '_hcp_finalized', true );
    $finalized_at_ts = (int) get_post_meta( $election_id, '_hcp_finalized_at', true );
    $finalized_at = $finalized_at_ts ? gmdate( 'Y-m-d H:i:s', $finalized_at_ts ) . ' UTC' : '';
    $finalized_by = (int) get_post_meta( $election_id, '_hcp_finalized_by', true );

    $t = HCP_Tally::get( $election_id );
    $election_hash = (string) get_post_meta( $election_id, '_hcp_audit_hash', true );
    if ( '' === $election_hash ) { $election_hash = self::compute_election_audit_hash( $election_id, $t, $rows ); }


    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="hcp-audit-election-' . $election_id . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'vote_id','unit_id','unit_number','choice','weight','submitted_at','submitted_by_id','submitted_by','submitted_email','ip','user_agent','vote_hash','election_finalized','election_finalized_at','election_finalized_by','election_audit_hash' ) );
    foreach ( $rows as $r ) {
        fputcsv( $out, array(
            $r['vote_id'],
            $r['unit_id'],
            $r['unit_number'],
            $r['choice'],
            $r['weight'],
            $r['submitted_at'],
            $r['ip'],
            substr( $r['vote_hash'], 0, 12 ),
            $r['submitted_by_id'],
            $r['submitted_by'],
            $r['submitted_by_id'],
            $r['submitted_email'],
            $r['ip'],
            $r['user_agent'],
            $r['vote_hash'],
            $finalized,
            $finalized_at,
            $finalized_by,
            $election_hash,
        ) );
    }
    fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    exit;
}

public static function export_audit_pdf(): void {
    self::require_manage();
    check_admin_referer( 'hcp_export_audit' );

    $election_id = isset( $_GET['election_id'] ) ? absint( $_GET['election_id'] ) : 0;
    if ( $election_id <= 0 ) { wp_die( esc_html__( 'Invalid election.', 'hoa-coa-portal-pro' ) ); }

    $t = HCP_Tally::get( $election_id );
    $rows = self::get_audit_rows( $election_id );

    $finalized = (int) get_post_meta( $election_id, '_hcp_finalized', true );
    $finalized_at_ts = (int) get_post_meta( $election_id, '_hcp_finalized_at', true );
    $finalized_at = $finalized_at_ts ? gmdate( 'Y-m-d H:i:s', $finalized_at_ts ) . ' UTC' : '';
    $finalized_by = (int) get_post_meta( $election_id, '_hcp_finalized_by', true );

    $t = HCP_Tally::get( $election_id );
    $election_hash = (string) get_post_meta( $election_id, '_hcp_audit_hash', true );
    if ( '' === $election_hash ) { $election_hash = self::compute_election_audit_hash( $election_id, $t, $rows ); }

    $audit_hash = self::compute_election_audit_hash( $election_id, $t, $rows );
    $rows = self::get_audit_rows( $election_id );

    $finalized = (int) get_post_meta( $election_id, '_hcp_finalized', true );
    $finalized_at_ts = (int) get_post_meta( $election_id, '_hcp_finalized_at', true );
    $finalized_at = $finalized_at_ts ? gmdate( 'Y-m-d H:i:s', $finalized_at_ts ) . ' UTC' : '';
    $finalized_by = (int) get_post_meta( $election_id, '_hcp_finalized_by', true );

    $t = HCP_Tally::get( $election_id );
    $election_hash = (string) get_post_meta( $election_id, '_hcp_audit_hash', true );
    if ( '' === $election_hash ) { $election_hash = self::compute_election_audit_hash( $election_id, $t, $rows ); }


    $pdf = new HCP_Simple_PDF();
    $pdf->add_line( 'HOA/COA Portal - Audit Report' );
    $settings = HCP_Helpers::settings();
    $assoc = trim( (string) ( $settings['assoc_name'] ?? '' ) );
    if ( '' !== $assoc ) { $pdf->add_line( 'Association: ' . $assoc ); }
    $addr = trim( (string) ( $settings['assoc_address'] ?? '' ) );
    if ( '' !== $addr ) { $pdf->add_line( 'Address: ' . $addr ); }
    $email = trim( (string) ( $settings['assoc_email'] ?? '' ) );
    $phone = trim( (string) ( $settings['assoc_phone'] ?? '' ) );
    if ( '' !== $email || '' !== $phone ) { $pdf->add_line( 'Contact: ' . trim( $phone . ' ' . $email ) ); }

    $pdf->add_line( 'Election: ' . get_the_title( $election_id ) . ' (#' . $election_id . ')' );
    $pdf->add_line( 'Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC' );
    $pdf->add_line( '---' );
    $pdf->add_line( 'Quorum mode: ' . $t['quorum_mode'] . ' | Required: ' . $t['quorum_percent'] . '%' );
    $pdf->add_line( 'Eligible units: ' . $t['eligible_units'] . ' | Eligible weight: ' . number_format( (float) $t['eligible_weight'], 2 ) );
    $pdf->add_line( 'Voted units: ' . $t['voted_units'] . ' | Voted weight: ' . number_format( (float) $t['voted_weight'], 2 ) );
    $pdf->add_line( 'Quorum met: ' . ( $t['quorum_met'] ? 'YES' : 'NO' ) );
    $finalized = (int) get_post_meta( $election_id, '_hcp_finalized', true );
    $finalized_at_ts = (int) get_post_meta( $election_id, '_hcp_finalized_at', true );
    $finalized_at = $finalized_at_ts ? gmdate( 'Y-m-d H:i:s', $finalized_at_ts ) . ' UTC' : '';
    $finalized_by = (int) get_post_meta( $election_id, '_hcp_finalized_by', true );
    $election_hash = (string) get_post_meta( $election_id, '_hcp_audit_hash', true );
    if ( '' === $election_hash ) { $election_hash = self::compute_election_audit_hash( $election_id, $t, $rows ); }
    $pdf->add_line( 'Finalized: ' . ( $finalized ? 'YES' : 'NO' ) . ( $finalized_at ? ' | ' . $finalized_at : '' ) . ( $finalized_by ? ' | by user #' . $finalized_by : '' ) );
    $pdf->add_line( 'Election audit hash: ' . $election_hash );
    $pdf->add_line( '---' );
    $pdf->add_line( 'Certification (for records):' );
    $pdf->add_line( 'I certify that the results above are true and correct to the best of my knowledge.' );
    $pdf->add_line( 'Signature: ______________________________   Date: ____________' );
    $pdf->add_line( 'Name/Title: _____________________________' );
    $pdf->add_line( '---' );
    $pdf->add_line( 'Votes:' );

    foreach ( $rows as $r ) {
        $pdf->add_line( sprintf(
            'Unit %s | %s | w=%s | by=%s (#%s) | %s | ip=%s | %s',
            $r['unit_number'],
            strtoupper( $r['choice'] ),
            number_format( (float) $r['weight'], 2 ),
            $r['submitted_by'],
            $r['submitted_by_id'],
            $r['submitted_at'],
            $r['ip'],
            substr( $r['vote_hash'], 0, 12 )
        ) );
    }

    $pdf->output( 'hcp-audit-election-' . $election_id . '.pdf' );
}

private static 
function export_compliance_csv(): void {
    self::require_manage();
    check_admin_referer( 'hcp_export_compliance' );

    $filename = 'hcp-compliance-index-' . gmdate( 'Y-m-d-His' ) . '.csv';

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );

    $out = fopen( 'php://output', 'w' );
    if ( false === $out ) {
        wp_die( esc_html__( 'Unable to export.', 'hoa-coa-portal-pro' ) );
    }

    // Header row.
    fputcsv(
        $out,
        array(
            'Document ID',
            'Title',
            'Category',
            'Visibility',
            'Posted Date (UTC)',
            'Posted By (User ID)',
            'Posted By (Name)',
            'File URL (Admin)',
        )
    );

    $posts = get_posts(
        array(
            'post_type'      => HCP_Compliance::CPT,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 10000,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        )
    );

    foreach ( $posts as $post_id ) {
        $title = get_the_title( $post_id );

        $terms = wp_get_post_terms( $post_id, HCP_Compliance::TAX, array( 'fields' => 'names' ) );
        $category = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? implode( '; ', $terms ) : '';

        $vis = (string) get_post_meta( $post_id, 'hcp_visibility', true );
        if ( '' === $vis ) { $vis = 'owners'; }

        $posted = get_post_time( 'Y-m-d H:i:s', true, $post_id );

        $author_id = (int) get_post_field( 'post_author', $post_id );
        $author_name = '';
        if ( $author_id > 0 ) {
            $u = get_userdata( $author_id );
            if ( $u ) { $author_name = $u->display_name; }
        }

        $file_id = (int) get_post_meta( $post_id, 'hcp_file_id', true );
        $file_url = $file_id > 0 ? wp_get_attachment_url( $file_id ) : '';

        fputcsv(
            $out,
            array(
                $post_id,
                $title,
                $category,
                $vis,
                $posted,
                $author_id,
                $author_name,
                $file_url,
            )
        );
    }

    fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Writing CSV stream.
    exit;
}


    public static function settings_screen(): void {
        $settings = HCP_Helpers::settings();

        echo '<div class="wrap hcp-admin"><h1>' . esc_html__( 'Settings', 'hoa-coa-portal-pro' ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'hcp_save_settings' );
        echo '<input type="hidden" name="action" value="hcp_save_settings"/>';

        echo '<div class="hcp-box" style="max-width:920px">';

        echo '<h2>' . esc_html__( 'Portal Page', 'hoa-coa-portal-pro' ) . ' ' . wp_kses_post( self::admin_tooltip( __( 'Help', 'hoa-coa-portal-pro' ), __( 'Choose the page that contains the [hoa_coa_portal] shortcode. This is where residents access voting, notices, minutes, and agendas.', 'hoa-coa-portal-pro' ) ) ) . '</h2>';
        echo '<div class="hcp-meta-row"><label for="hcp_portal_page">' . esc_html__( 'Portal page', 'hoa-coa-portal-pro' ) . '</label>';
        wp_dropdown_pages( array(
            'name'              => 'hcp_portal_page_id',
            'id'                => 'hcp_portal_page',
            'show_option_none'  => esc_html__( '— Select —', 'hoa-coa-portal-pro' ),
            'option_none_value' => '0',
            'selected'          => (int) $settings['portal_page_id'],
        ) );
        echo '<p class="description">' . esc_html__( 'Create a page with the shortcode [hcp_portal] (or [hoa_coa_portal]) and select it here.', 'hoa-coa-portal-pro' ) . '</p></div>';

        echo '<div class="hcp-meta-row"><label><input type="checkbox" name="hcp_load_css" value="1" ' . checked( 1, (int) $settings['load_css'], false ) . '/> ' . esc_html__( 'Load plugin CSS on the portal page', 'hoa-coa-portal-pro' ) . '</label></div>';

        echo '<hr/>';
        echo '<h2>' . esc_html__( 'Association Branding', 'hoa-coa-portal-pro' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'This information shows in the portal header so residents know which HOA/COA is publishing the content.', 'hoa-coa-portal-pro' ) . '</p>';

        echo '<div class="hcp-two-col" style="grid-template-columns:1fr 1fr">';
        echo '<div class="hcp-meta-row"><label for="hcp_assoc_name">' . esc_html__( 'Association name', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_assoc_name" name="hcp_assoc_name" type="text" class="widefat" value="' . esc_attr( (string) $settings['assoc_name'] ) . '" placeholder="' . esc_attr__( 'Example: Grand Venezia Condominium Association', 'hoa-coa-portal-pro' ) . '"/></div>';

        echo '<div class="hcp-meta-row"><label for="hcp_assoc_website">' . esc_html__( 'Association website', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_assoc_website" name="hcp_assoc_website" type="url" class="widefat" value="' . esc_attr( (string) $settings['assoc_website'] ) . '" placeholder="https://"/></div>';

        echo '<div class="hcp-meta-row"><label for="hcp_assoc_email">' . esc_html__( 'Email', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_assoc_email" name="hcp_assoc_email" type="email" class="widefat" value="' . esc_attr( (string) $settings['assoc_email'] ) . '"/></div>';

        echo '<div class="hcp-meta-row"><label for="hcp_assoc_phone">' . esc_html__( 'Phone', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_assoc_phone" name="hcp_assoc_phone" type="text" class="widefat" value="' . esc_attr( (string) $settings['assoc_phone'] ) . '"/></div>';
        echo '</div>'; // grid

        echo '<div class="hcp-meta-row"><label for="hcp_assoc_address">' . esc_html__( 'Address', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<textarea id="hcp_assoc_address" name="hcp_assoc_address" class="widefat" rows="3" placeholder="' . esc_attr__( 'Mailing address (optional)', 'hoa-coa-portal-pro' ) . '">' . esc_textarea( (string) $settings['assoc_address'] ) . '</textarea></div>';

        // Logo
        $logo_id = (int) $settings['assoc_logo_id'];
        $logo_url = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        echo '<div class="hcp-meta-row">';
        echo '<label>' . esc_html__( 'Logo', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input type="hidden" name="hcp_assoc_logo_id" class="hcp-logo-id" value="' . esc_attr( (string) $logo_id ) . '"/>';
        echo '<div class="hcp-logo-row">';
        if ( $logo_url ) {
            echo '<img class="hcp-logo-preview" src="' . esc_url( $logo_url ) . '" alt="" style="max-height:64px;max-width:220px;"/>';
        } else {
            echo '<div class="hcp-logo-preview" style="opacity:.7">' . esc_html__( 'No logo selected.', 'hoa-coa-portal-pro' ) . '</div>';
        }
        echo '<p><a href="#" class="button hcp-select-logo">' . esc_html__( 'Select Logo', 'hoa-coa-portal-pro' ) . '</a> ';
        echo '<a href="#" class="button hcp-remove-logo">' . esc_html__( 'Remove', 'hoa-coa-portal-pro' ) . '</a></p>';
        echo '</div></div>';


        echo '<hr/>';
        echo '<h2>' . esc_html__( 'Florida Website Compliance', 'hoa-coa-portal-pro' ) . ' ' . wp_kses_post( self::admin_tooltip( __( 'Help', 'hoa-coa-portal-pro' ), __( 'Set your association type and size to enable the built-in compliance checklist for required online records.', 'hoa-coa-portal-pro' ) ) ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'This does not provide legal advice. It helps you organize records commonly required to be available in a secure owner portal.', 'hoa-coa-portal-pro' ) . '</p>';

        $assoc_type = isset( $settings['assoc_type'] ) ? (string) $settings['assoc_type'] : 'condo';
        echo '<div class="hcp-two-col" style="grid-template-columns:1fr 1fr">';
        echo '<div class="hcp-meta-row"><label for="hcp_assoc_type">' . esc_html__( 'Association type', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<select id="hcp_assoc_type" name="hcp_assoc_type" class="widefat">';
        echo '<option value="condo" ' . selected( 'condo', $assoc_type, false ) . '>' . esc_html__( 'Condominium (Chapter 718)', 'hoa-coa-portal-pro' ) . '</option>';
        echo '<option value="hoa" ' . selected( 'hoa', $assoc_type, false ) . '>' . esc_html__( 'Homeowners Association (Chapter 720)', 'hoa-coa-portal-pro' ) . '</option>';
        echo '</select>';
        if ( 'condo' === $assoc_type ) {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida Condo (FS 718): Associations with 25+ units must provide a secure owner website/portal (effective Jan 1, 2026).', 'hoa-coa-portal-pro' ) . '</p>';
        } else {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida HOA (FS 720): Associations with 100+ parcels must provide a secure owner website/portal (effective Jan 1, 2025).', 'hoa-coa-portal-pro' ) . '</p>';
        }
        echo '</div>';

        echo '<div class="hcp-meta-row"><label for="hcp_unit_count">' . esc_html__( 'Total units / parcels', 'hoa-coa-portal-pro' ) . '</label>';
        $count_val = ( 'hoa' === $assoc_type ) ? (int) $settings['parcel_count'] : (int) $settings['unit_count'];
        echo '<input id="hcp_unit_count" name="hcp_size_count" type="number" min="0" step="1" class="widefat" value="' . esc_attr( (string) $count_val ) . '"/>';
        echo '<p class="description">' . esc_html__( 'Used for the checklist threshold (e.g., 25+ condos, 100+ HOAs).', 'hoa-coa-portal-pro' ) . '</p></div>';
        echo '</div>';

        echo '<p style="margin-top:16px"><button type="submit" class="button button-primary">' . esc_html__( 'Save Settings', 'hoa-coa-portal-pro' ) . '</button></p>';
        echo '</div></form></div>';
    }

    
public static function page_units(): void {
    if ( ! ( class_exists( 'HCP_Helpers' ) ? HCP_Helpers::can_manage() : current_user_can( 'manage_options' ) ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'hoa-coa-portal-pro' ) );
    }

    $action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';
    $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $saved  = isset( $_GET['saved'] ) ? absint( $_GET['saved'] ) : 0;

    echo '<div class="wrap hcp-admin"><h1>' . esc_html__( 'Units', 'hoa-coa-portal-pro' ) . '</h1>';
    if ( $saved ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Unit saved.', 'hoa-coa-portal-pro' ) . '</p></div>';
    }

    if ( 'add' === $action || ( 'edit' === $action && $id > 0 ) ) {
        $unit = $id ? get_post( $id ) : null;
        if ( $id && ( ! $unit || 'hcp_unit' !== $unit->post_type ) ) {
            wp_die( esc_html__( 'Invalid unit.', 'hoa-coa-portal-pro' ) );
        }

        $unit_number = $id ? (string) get_post_meta( $id, '_hcp_unit_number', true ) : '';
        $unit_address = $id ? (string) get_post_meta( $id, '_hcp_unit_address', true ) : '';
        $unit_weight = $id ? (float) get_post_meta( $id, '_hcp_unit_weight', true ) : 1;
        if ( $unit_weight <= 0 ) { $unit_weight = 1; }
        $is_rental = $id ? (int) get_post_meta( $id, '_hcp_is_rental', true ) : 0;
        $primary_owner = $id ? (int) get_post_meta( $id, '_hcp_primary_owner', true ) : 0;
        $additional = $id ? get_post_meta( $id, '_hcp_additional_owners', true ) : array();
        if ( ! is_array( $additional ) ) { $additional = array(); }
        $notes = $id ? (string) get_post_meta( $id, '_hcp_notes', true ) : '';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'hcp_save_unit' );
        echo '<input type="hidden" name="action" value="hcp_save_unit"/>';
        echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '"/>';

        echo '<div class="hcp-box" style="max-width:980px">';
        echo '<div class="hcp-two-col" style="grid-template-columns:1.2fr .8fr">';

        echo '<div>';
        echo '<div class="hcp-meta-row"><label for="hcp_unit_number">' . esc_html__( 'Unit number (required)', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_unit_number" name="hcp_unit_number" type="text" class="widefat" value="' . esc_attr( $unit_number ) . '" placeholder="' . esc_attr__( 'Example: 304', 'hoa-coa-portal-pro' ) . '"/></div>';

        echo '<div class="hcp-meta-row"><label for="hcp_unit_address">' . esc_html__( 'Address (optional)', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_unit_address" name="hcp_unit_address" type="text" class="widefat" value="' . esc_attr( $unit_address ) . '" placeholder="' . esc_attr__( 'Example: 123 Main St, Clearwater, FL', 'hoa-coa-portal-pro' ) . '"/></div>';

        echo '<div class="hcp-meta-row"><label for="hcp_unit_notes">' . esc_html__( 'Internal notes (office only)', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<textarea id="hcp_unit_notes" name="hcp_unit_notes" class="widefat" rows="5">' . esc_textarea( $notes ) . '</textarea></div>';
        echo '</div>';

        echo '<div>';
        echo '<div class="hcp-meta-row"><label for="hcp_unit_weight">' . esc_html__( 'Voting weight', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<input id="hcp_unit_weight" name="hcp_unit_weight" type="number" step="0.01" min="0.01" class="widefat" value="' . esc_attr( (string) $unit_weight ) . '"/></div>';

        echo '<div class="hcp-meta-row"><label><input type="checkbox" name="hcp_is_rental" value="1" ' . checked( 1, $is_rental, false ) . '/> ' . esc_html__( 'This unit is currently a rental', 'hoa-coa-portal-pro' ) . '</label></div>';

        // Users dropdowns: show subscribers + hcp_owner/hcp_office/admin users
        $users = get_users( array( 'number' => 200, 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
        echo '<div class="hcp-meta-row"><label for="hcp_primary_owner">' . esc_html__( 'Primary voting owner', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<select id="hcp_primary_owner" name="hcp_primary_owner" class="widefat">';
        echo '<option value="0">' . esc_html__( '— Select —', 'hoa-coa-portal-pro' ) . '</option>';
        foreach ( $users as $u ) {
            $label = $u->display_name . ' (' . $u->user_email . ')';
            echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . selected( $primary_owner, $u->ID, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select><p class="description">' . esc_html__( 'Only the Primary Voting Owner can vote for this unit. A unit becomes eligible for elections/quorum once a Primary Voting Owner is assigned.', 'hoa-coa-portal-pro' ) . '</p></div>';

        echo '<div class="hcp-meta-row"><label for="hcp_additional_owners">' . esc_html__( 'Additional owners (read-only)', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<select id="hcp_additional_owners" name="hcp_additional_owners[]" class="widefat" multiple size="6">';
        foreach ( $users as $u ) {
            $label = $u->display_name . ' (' . $u->user_email . ')';
            $sel = in_array( (int) $u->ID, array_map( 'absint', $additional ), true );
            echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . selected( true, $sel, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        if ( 'condo' === $assoc_type ) {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida Condo (FS 718): Associations with 25+ units must provide a secure owner website/portal (effective Jan 1, 2026).', 'hoa-coa-portal-pro' ) . '</p>';
        } else {
            echo '<p class="description" style="margin:6px 0 0 0;">' . esc_html__( 'Florida HOA (FS 720): Associations with 100+ parcels must provide a secure owner website/portal (effective Jan 1, 2025).', 'hoa-coa-portal-pro' ) . '</p>';
        }
        echo '</div>';

        echo '</div>'; // right
        echo '</div>'; // grid

        echo '<div class="hcp-meta-row"><button type="submit" class="button button-primary">' . esc_html__( 'Save Unit', 'hoa-coa-portal-pro' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=hcp-units' ) ) . '">' . esc_html__( 'Back', 'hoa-coa-portal-pro' ) . '</a></div>';

        echo '</div></form></div>';
        return;
    }

    echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=hcp-units&action=add' ) ) . '">' . esc_html__( 'Add Unit', 'hoa-coa-portal-pro' ) . '</a></p>';

    $q = new WP_Query( array(
        'post_type'      => 'hcp_unit',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    echo '<div class="hcp-print-table"><table class="widefat striped"><thead><tr>';
    echo '<th>' . esc_html__( 'Unit', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Primary Owner', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Eligible', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Reason', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Verified', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Weight', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Rental', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Updated', 'hoa-coa-portal-pro' ) . '</th>';
    echo '</tr></thead><tbody>';

    if ( $q->have_posts() ) {
        while ( $q->have_posts() ) {
            $q->the_post();
            $uid = get_the_ID();
            $unit_number = (string) get_post_meta( $uid, '_hcp_unit_number', true );
            $primary_owner = (int) get_post_meta( $uid, '_hcp_primary_owner', true );
            $owner_label = $primary_owner ? (string) get_the_author_meta( 'display_name', $primary_owner ) : '—';
            $weight = (string) get_post_meta( $uid, '_hcp_unit_weight', true );
            $weight = $weight !== '' ? $weight : '1';
            $is_rental = (int) get_post_meta( $uid, '_hcp_is_rental', true );
            $edit = admin_url( 'admin.php?page=hcp-units&action=edit&id=' . $uid );
            echo '<tr>';
            echo '<td><a href="' . esc_url( $edit ) . '">' . esc_html( $unit_number ? $unit_number : get_the_title() ) . '</a></td>';
            echo '<td>' . esc_html( $owner_label ) . '</td>';
            $eligible = $primary_owner > 0;
            $verified = (string) get_post_meta( $uid, '_hcp_verified_status', true );
            $is_verified = in_array( $verified, array( 'verified_owner_affirmed', 'verified_board_assigned' ), true );
            $elig_reason = $eligible ? esc_html__( 'Primary Voting Owner assigned', 'hoa-coa-portal-pro' ) : esc_html__( 'No Primary Voting Owner assigned', 'hoa-coa-portal-pro' );
            $elig_tip = $eligible ? esc_attr__( 'This unit is eligible because a Primary Voting Owner is assigned.', 'hoa-coa-portal-pro' ) : esc_attr__( 'Assign a Primary Voting Owner to make this unit eligible to vote.', 'hoa-coa-portal-pro' );
            echo '<td><span class="hcp-badge" title="' . esc_attr( $elig_tip ) . '">' . esc_html( $elig_reason ) . '</span></td>';
            echo '<td>' . ( $is_verified ? '<span class="hcp-badge hcp-badge-info">' . esc_html__( 'Yes', 'hoa-coa-portal-pro' ) . '</span>' : '<span class="hcp-badge">' . esc_html__( 'No', 'hoa-coa-portal-pro' ) . '</span>' ) . '</td>';
            echo '<td>' . esc_html( $weight ) . '</td>';
            echo '<td>' . esc_html( $is_rental ? __( 'Yes', 'hoa-coa-portal-pro' ) : __( 'No', 'hoa-coa-portal-pro' ) ) . '</td>';
            echo '<td>' . esc_html( get_the_modified_date() ) . '</td>';
            echo '</tr>';
        }
        wp_reset_postdata();
    } else {
        echo '<tr><td colspan="7"><em>' . esc_html__( 'No units yet. Add your first unit.', 'hoa-coa-portal-pro' ) . '</em></td></tr>';
    }

    echo '</tbody></table></div></div>';
}


public static function import_units_csv(): void {
    self::save_common( 'hcp_import_units_csv' );
    check_admin_referer( 'hcp_import_units_csv' );
    if ( ! ( class_exists( 'HCP_Helpers' ) ? HCP_Helpers::can_manage() : current_user_can( 'manage_options' ) ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'hoa-coa-portal-pro' ) );
    }

    if ( empty( $_FILES['hcp_units_csv']['tmp_name'] ) ) {
        wp_die( esc_html__( 'Please upload a CSV file.', 'hoa-coa-portal-pro' ) );
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array is validated below.
    $file = isset( $_FILES['hcp_units_csv'] ) ? (array) $_FILES['hcp_units_csv'] : array();

    $upload_error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    $tmp_name     = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

    if ( UPLOAD_ERR_OK !== $upload_error || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
        wp_die( esc_html__( 'Upload failed. Please try again.', 'hoa-coa-portal-pro' ) );
    }

    // Read uploaded CSV using WP_Filesystem to satisfy plugin guidelines.
$contents = '';
require_once ABSPATH . 'wp-admin/includes/file.php';
if ( function_exists( 'WP_Filesystem' ) ) {
WP_Filesystem();
global $wp_filesystem;
if ( is_object( $wp_filesystem ) ) {
$contents = (string) $wp_filesystem->get_contents( $file['tmp_name'] );
}
}

if ( false === $contents || '' === $contents ) {
wp_die( esc_html__( 'CSV file is empty.', 'hoa-coa-portal-pro' ) );
}

$contents = str_replace( array( "\r\n", "\r" ), "\n", $contents );
    $lines = array_values( array_filter( array_map( 'trim', explode( "\n", $contents ) ) ) );
    if ( count( $lines ) < 2 ) {
        wp_die( esc_html__( 'CSV must include a header row and at least one data row.', 'hoa-coa-portal-pro' ) );
    }

    $header = str_getcsv( array_shift( $lines ) );
    $header = array_map( 'sanitize_key', $header );

    $required = array( 'unit_number', 'primary_owner_email' );
    foreach ( $required as $req ) {
        if ( ! in_array( $req, $header, true ) ) {
            wp_die( esc_html__( 'CSV is missing required columns: unit_number, primary_owner_email.', 'hoa-coa-portal-pro' ) );
        }
    }

    $stats = array( 'created_units' => 0, 'updated_units' => 0, 'created_users' => 0, 'skipped' => 0, 'errors' => 0 );

    foreach ( $lines as $line ) {
        $row = str_getcsv( $line );
        if ( empty( $row ) ) {
            continue;
        }

        $data = array();
        foreach ( $header as $i => $key ) {
            $data[ $key ] = isset( $row[ $i ] ) ? sanitize_text_field( $row[ $i ] ) : '';
        }

        $unit_number = isset( $data['unit_number'] ) ? (string) $data['unit_number'] : '';
        $email       = sanitize_email( $data['primary_owner_email'] ?? '' );

        if ( '' === $unit_number || '' === $email ) {
            $stats['skipped']++;
            continue;
        }

        $address  = isset( $data['address'] ) ? (string) $data['address'] : '';
        $weight   = isset( $data['weight'] ) && '' !== $data['weight'] ? (float) $data['weight'] : 1;
        if ( $weight <= 0 ) { $weight = 1; }
        $is_rental = 0;
        if ( isset( $data['is_rental'] ) ) {
            $v = strtolower( (string) $data['is_rental'] );
            $is_rental = ( '1' === $v || 'yes' === $v || 'true' === $v ) ? 1 : 0;
        }

        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            $username = sanitize_user( current( explode( '@', $email ) ), true );
            if ( username_exists( $username ) ) {
                $username .= '_' . wp_generate_password( 4, false );
            }
            $password = wp_generate_password( 16, true, true );
            $user_id = wp_create_user( $username, $password, $email );
            if ( is_wp_error( $user_id ) ) {
                $stats['errors']++;
                continue;
            }
            $stats['created_users']++;
            $user = get_user_by( 'id', (int) $user_id );
            if ( $user && empty( $user->roles ) ) {
                $user->set_role( 'subscriber' );
            }
        }

        $primary_owner = (int) $user->ID;

        $existing = new WP_Query( array(
            'post_type'      => 'hcp_unit',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'meta_key'       => '_hcp_unit_number',
            'meta_value'     => $unit_number,
            'fields'         => 'ids',
        ) );

        $unit_id = 0;
        if ( ! empty( $existing->posts ) ) {
            $unit_id = (int) $existing->posts[0];
        }

        if ( $unit_id > 0 ) {
            $stats['updated_units']++;
            wp_update_post( array( 'ID' => $unit_id, 'post_title' => $unit_number ) );
        } else {
            $unit_id = (int) wp_insert_post( array( 'post_type' => 'hcp_unit', 'post_status' => 'publish', 'post_title' => $unit_number ) );
            if ( $unit_id <= 0 ) {
                $stats['errors']++;
                continue;
            }
            $stats['created_units']++;
        }

        update_post_meta( $unit_id, '_hcp_unit_number', $unit_number );
        update_post_meta( $unit_id, '_hcp_unit_address', $address );
        update_post_meta( $unit_id, '_hcp_unit_weight', $weight );
        update_post_meta( $unit_id, '_hcp_is_rental', $is_rental );
        update_post_meta( $unit_id, '_hcp_primary_owner', $primary_owner );

        if ( ! empty( $data['additional_owner_emails'] ) ) {
            $emails = array_filter( array_map( 'trim', explode( ',', (string) $data['additional_owner_emails'] ) ) );
            $ids = array();
            foreach ( $emails as $e ) {
                $e = sanitize_email( $e );
                if ( '' === $e ) { continue; }
                $u2 = get_user_by( 'email', $e );
                if ( $u2 ) {
                    $ids[] = (int) $u2->ID;
                }
            }
            $ids = array_values( array_unique( array_filter( $ids ) ) );
            update_post_meta( $unit_id, '_hcp_additional_owners', $ids );
        }
    }

    $qs = array(
        'page' => 'hcp-units',
        'imported' => 1,
        'cu' => $stats['created_units'],
        'uu' => $stats['updated_units'],
        'cusr' => $stats['created_users'],
        'sk' => $stats['skipped'],
        'er' => $stats['errors'],
    );

    wp_safe_redirect( add_query_arg( $qs, admin_url( 'admin.php' ) ) );
    exit;
}

public static function save_unit(): void {
    self::save_common( 'hcp_save_unit' );
    check_admin_referer( 'hcp_save_unit' );
    $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
    $unit_number = isset( $_POST['hcp_unit_number'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_unit_number'] ) ) : '';
    $unit_address = isset( $_POST['hcp_unit_address'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_unit_address'] ) ) : '';
    $notes = isset( $_POST['hcp_unit_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['hcp_unit_notes'] ) ) : '';

    $weight = isset( $_POST['hcp_unit_weight'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['hcp_unit_weight'] ) ) : 1;
    if ( $weight <= 0 ) { $weight = 1; }

    $is_rental = isset( $_POST['hcp_is_rental'] ) ? 1 : 0;
    $primary_owner = isset( $_POST['hcp_primary_owner'] ) ? absint( $_POST['hcp_primary_owner'] ) : 0;
    $additional = isset( $_POST['hcp_additional_owners'] ) && is_array( $_POST['hcp_additional_owners'] ) ? array_map( 'absint', $_POST['hcp_additional_owners'] ) : array();
    $additional = array_values( array_unique( array_filter( $additional ) ) );

    if ( '' === $unit_number ) {
        wp_die( esc_html__( 'Unit number is required.', 'hoa-coa-portal-pro' ) );
    }

    $data = array(
        'post_type'   => 'hcp_unit',
        'post_status' => 'publish',
        'post_title'  => $unit_number,
    );

    if ( $id > 0 ) {
        $existing = get_post( $id );
        if ( ! $existing || 'hcp_unit' !== $existing->post_type ) {
            wp_die( esc_html__( 'Invalid unit.', 'hoa-coa-portal-pro' ) );
        }
        $data['ID'] = $id;
        $new_id = wp_update_post( $data, true );
    } else {
        $new_id = wp_insert_post( $data, true );
    }

    if ( is_wp_error( $new_id ) ) {
        wp_die( esc_html( $new_id->get_error_message() ) );
    }

    update_post_meta( (int) $new_id, '_hcp_unit_number', $unit_number );
    update_post_meta( (int) $new_id, '_hcp_unit_address', $unit_address );
    update_post_meta( (int) $new_id, '_hcp_unit_weight', $weight );
    update_post_meta( (int) $new_id, '_hcp_is_rental', $is_rental );
    update_post_meta( (int) $new_id, '_hcp_primary_owner', $primary_owner );
    update_post_meta( (int) $new_id, '_hcp_additional_owners', $additional );
    update_post_meta( (int) $new_id, '_hcp_notes', $notes );

    wp_safe_redirect( admin_url( 'admin.php?page=hcp-units&action=edit&id=' . (int) $new_id . '&saved=1' ) );
    exit;
}


    
public static function page_eligibility(): void {
    if ( ! ( class_exists( 'HCP_Helpers' ) ? HCP_Helpers::can_manage() : current_user_can( 'manage_options' ) ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'hoa-coa-portal-pro' ) );
    }

    $assigned = isset( $_GET['assigned'] ) ? absint( $_GET['assigned'] ) : 0;
    $invited  = isset( $_GET['invited'] ) ? absint( wp_unslash( $_GET['invited'] ) ) : 0;
    $errors   = isset( $_GET['errors'] ) ? absint( wp_unslash( $_GET['errors'] ) ) : 0;

    echo '<div class="wrap hcp-admin"><h1>' . esc_html__( 'Voting Eligibility Wizard', 'hoa-coa-portal-pro' ) . '</h1>';
    echo '<p class="description">' . esc_html__( 'Assign a Primary Voting Owner to each unit. Only units with a Primary Voting Owner are eligible for quorum and voting.', 'hoa-coa-portal-pro' ) . '</p>';

    if ( $assigned || $invited || $errors ) {
        $msg = sprintf(
            // translators: 1: number of assignments, 2: number of invites, 3: number of errors.
            __( 'Done. Assigned: %1$s. Invites sent: %2$s. Errors: %3$s.', 'hoa-coa-portal-pro' ),
            number_format_i18n( (int) $assigned ),
            number_format_i18n( (int) $invited ),
            number_format_i18n( (int) $errors )
        );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
    }

    $ids = array();

    $q_missing = new WP_Query( array(
        'post_type'      => 'hcp_unit',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 200,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_hcp_primary_owner',
                'compare' => 'NOT EXISTS',
            ),
        ),
        'orderby' => 'date',
        'order'   => 'DESC',
    ) );

    foreach ( (array) $q_missing->posts as $pid ) {
        $ids[] = absint( $pid );
    }

    $q_zero = new WP_Query( array(
        'post_type'      => 'hcp_unit',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'posts_per_page' => 200,
        'fields'         => 'ids',
        'meta_key'       => '_hcp_primary_owner',
        'meta_value'     => '0',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    foreach ( (array) $q_zero->posts as $pid ) {
        $ids[] = absint( $pid );
    }

    $unit_ids = array_values( array_unique( array_filter( $ids ) ) );

    if ( empty( $unit_ids ) ) {
        echo '<div class="hcp-box" style="max-width:980px;margin-top:16px;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( 'All set', 'hoa-coa-portal-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'Every unit has a Primary Voting Owner assigned.', 'hoa-coa-portal-pro' ) . '</p>';
        echo '</div></div>';
        return;
    }

    echo '<div class="hcp-box" style="max-width:1100px;margin-top:16px;">';
    echo '<h2 style="margin-top:0;">' . esc_html__( 'Units missing a Primary Voting Owner', 'hoa-coa-portal-pro' ) . '</h2>';
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>' . esc_html__( 'Unit', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Assign by email', 'hoa-coa-portal-pro' ) . '</th>';
    echo '<th>' . esc_html__( 'Actions', 'hoa-coa-portal-pro' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $unit_ids as $unit_id ) {
        $unit_number = (string) get_post_meta( $unit_id, '_hcp_unit_number', true );
        if ( '' === $unit_number ) {
            $p = get_post( $unit_id );
            $unit_number = $p ? (string) $p->post_title : (string) $unit_id;
        }

        echo '<tr>';
        echo '<td><strong>' . esc_html( $unit_number ) . '</strong><div class="description">ID: ' . esc_html( (string) $unit_id ) . '</div></td>';

        echo '<td>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'hcp_assign_primary_owner' );
        echo '<input type="hidden" name="action" value="hcp_assign_primary_owner"/>';
        echo '<input type="hidden" name="unit_id" value="' . esc_attr( (string) $unit_id ) . '"/>';
        echo '<input type="email" name="owner_email" placeholder="' . esc_attr__( 'owner@email.com', 'hoa-coa-portal-pro' ) . '" required style="width:280px;max-width:100%;"/>';
        echo '<label style="margin-left:10px;"><input type="checkbox" name="create_user" value="1" checked/> ' . esc_html__( 'Create user if missing', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<label style="margin-left:10px;"><input type="checkbox" name="send_invite" value="1" checked/> ' . esc_html__( 'Send invite email', 'hoa-coa-portal-pro' ) . '</label>';
        echo '<div class="description" style="margin-top:6px;">' . esc_html__( 'This assigns the unit immediately and emails a secure set-password link.', 'hoa-coa-portal-pro' ) . '</div>';
        echo '</td>';

        echo '<td style="white-space:nowrap;">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Assign', 'hoa-coa-portal-pro' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=hcp-units&action=edit&id=' . $unit_id ) ) . '">' . esc_html__( 'Edit Unit', 'hoa-coa-portal-pro' ) . '</a>';
        echo '</form>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div></div>';
}

public static function assign_primary_owner(): void {
    self::save_common( 'hcp_assign_primary_owner' );
    check_admin_referer( 'hcp_assign_primary_owner' );
    if ( ! ( class_exists( 'HCP_Helpers' ) ? HCP_Helpers::can_manage() : current_user_can( 'manage_options' ) ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'hoa-coa-portal-pro' ) );
    }

    $unit_id = isset( $_POST['unit_id'] ) ? absint( $_POST['unit_id'] ) : 0;
    $email   = isset( $_POST['owner_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['owner_email'] ) ) : '';
    $create  = isset( $_POST['create_user'] );
    $invite  = isset( $_POST['send_invite'] );

    if ( $unit_id <= 0 || '' === $email ) {
        wp_die( esc_html__( 'Invalid request.', 'hoa-coa-portal-pro' ) );
    }

    $unit = get_post( $unit_id );
    if ( ! $unit || 'hcp_unit' !== $unit->post_type ) {
        wp_die( esc_html__( 'Invalid unit.', 'hoa-coa-portal-pro' ) );
    }

    $user = get_user_by( 'email', $email );
    if ( ! $user && $create ) {
        $username = sanitize_user( current( explode( '@', $email ) ), true );
        if ( username_exists( $username ) ) {
            $username .= '_' . wp_generate_password( 4, false );
        }
        $password = wp_generate_password( 20, true, true );
        $user_id  = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'hcp-eligibility', 'errors' => 1 ), admin_url( 'admin.php' ) ) );
            exit;
        }
        $user = get_user_by( 'id', (int) $user_id );
    }

    if ( ! $user ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'hcp-eligibility', 'errors' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    update_post_meta( $unit_id, '_hcp_primary_owner', (int) $user->ID );

    $invited = 0;
    if ( $invite ) {
        $key = get_password_reset_key( $user );
        if ( ! is_wp_error( $key ) ) {
            $link = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
            $unit_number = (string) get_post_meta( $unit_id, '_hcp_unit_number', true );
            if ( '' === $unit_number ) {
                $unit_number = (string) $unit->post_title;
            }

            // translators: %s: unit number.
            $subject = sprintf( __( 'Your HOA/COA Portal Access for Unit %s', 'hoa-coa-portal-pro' ), $unit_number );
            // translators: 1: unit number, 2: reset password link.
            $message = sprintf(
                __( "Hello,\n\nYou have been assigned as the Primary Voting Owner for Unit %1\$s in your HOA/COA portal.\n\nSet your password and sign in here:\n%2\$s\n\nIf you did not expect this email, you can ignore it.\n", 'hoa-coa-portal-pro' ),
                $unit_number,
                $link
            );

            wp_mail( $user->user_email, $subject, $message );
            $invited = 1;
        }
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'hcp-eligibility', 'assigned' => 1, 'invited' => $invited, 'errors' => 0 ), admin_url( 'admin.php' ) ) );
    exit;
}


    // ===== Save handlers =====
    private static function save_common( string $nonce_action, string $nonce_field = '_wpnonce' ): void {
        if ( ! ( class_exists( 'HCP_Helpers' ) ? HCP_Helpers::can_manage() : current_user_can( 'manage_options' ) ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'hoa-coa-portal-pro' ) );
        }
        check_admin_referer( $nonce_action, $nonce_field );
    }

    private static function parse_attachments_from_post(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $csv = isset( $_POST['hcp_attachments'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_attachments'] ) ) : '';
        if ( '' === $csv ) { return array(); }
        $ids = array_filter( array_map( 'absint', explode( ',', $csv ) ) );
        $allowed = array();
        foreach ( $ids as $id ) {
            if ( HCP_Helpers::is_allowed_attachment( (int) $id ) ) {
                $allowed[] = (int) $id;
            }
        }
        return array_values( array_unique( $allowed ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public static function save_notice(): void {
        self::save_common( 'hcp_save_notice' );
        self::save_content_item( 'hcp_notice', 'notice' );
    }

    public static function save_minutes(): void {
        self::save_common( 'hcp_save_minutes' );
        self::save_content_item( 'hcp_minutes', 'minutes' );
    }

    public static function save_agenda(): void {
        self::save_common( 'hcp_save_agenda' );
        self::save_content_item( 'hcp_agenda', 'agenda' );
    }

    

    public static function save_owner_doc(): void {
        // Owner Documents use the generic content save flow.
        self::save_common( 'hcp_save_owner_doc' );
        check_admin_referer( 'hcp_save_owner_doc' );
        self::save_content_item( 'hcp_owner_doc', 'owner_doc' );
    }
private static function save_content_item( string $post_type, string $slug ): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $title = isset( $_POST['hcp_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_title'] ) ) : '';
        $body  = isset( $_POST['hcp_body'] ) ? wp_kses_post( wp_unslash( (string) $_POST['hcp_body'] ) ) : '';
        $status = isset( $_POST['hcp_status'] ) ? sanitize_key( (string) $_POST['hcp_status'] ) : 'draft';
        if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) { $status = 'draft'; }

        $audience = isset( $_POST['hcp_audience'] ) ? sanitize_key( (string) $_POST['hcp_audience'] ) : 'both';
        if ( ! in_array( $audience, array( 'owner', 'office', 'both' ), true ) ) { $audience = 'both'; }

        $data = array(
            'post_type'    => $post_type,
            'post_title'   => $title,
            'post_content' => $body,
            'post_status'  => $status,
        );

        if ( $id > 0 ) {
            $existing = get_post( $id );
            if ( ! $existing || $existing->post_type !== $post_type ) {
                wp_die( esc_html__( 'Invalid item.', 'hoa-coa-portal-pro' ) );
            }
            $data['ID'] = $id;
            $new_id = wp_update_post( $data, true );
        } else {
            $new_id = wp_insert_post( $data, true );
        }

        if ( is_wp_error( $new_id ) ) {
            wp_die( esc_html( $new_id->get_error_message() ) );
        }

        update_post_meta( (int) $new_id, '_hcp_audience', $audience );

        // minutes/agendas meeting date
        if ( in_array( $post_type, array( 'hcp_minutes', 'hcp_agenda' ), true ) ) {
            $meeting_date = isset( $_POST['hcp_meeting_date'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_meeting_date'] ) ) : '';
            if ( '' !== $meeting_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $meeting_date ) ) {
                $meeting_date = '';
            }
            update_post_meta( (int) $new_id, '_hcp_meeting_date', $meeting_date );
        }

        // notices: featured/pinned
        if ( 'hcp_notice' === $post_type ) {
            update_post_meta( (int) $new_id, '_hcp_featured', isset( $_POST['hcp_featured'] ) ? 1 : 0 );
            update_post_meta( (int) $new_id, '_hcp_pinned', isset( $_POST['hcp_pinned'] ) ? 1 : 0 );
        }

        // attachments (PDF/images only)
        $attachments = self::parse_attachments_from_post();
        update_post_meta( (int) $new_id, '_hcp_attachments', $attachments );

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::page_slug( $slug ) . '&action=edit&id=' . (int) $new_id . '&saved=1' ) );
        exit;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public static function save_election(): void {
        if ( ! HCP_Access::can_manage_elections() ) {
            wp_die( esc_html__( 'You do not have permission to manage elections.', 'hoa-coa-portal-pro' ) );
        }
        check_admin_referer( 'hcp_save_election' );

        $id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $title = isset( $_POST['hcp_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_title'] ) ) : '';
        $body  = isset( $_POST['hcp_body'] ) ? wp_kses_post( wp_unslash( (string) $_POST['hcp_body'] ) ) : '';
        $status = isset( $_POST['hcp_status'] ) ? sanitize_key( (string) $_POST['hcp_status'] ) : 'draft';
        if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) { $status = 'draft'; }

        $e_status = isset( $_POST['hcp_e_status'] ) ? sanitize_key( (string) $_POST['hcp_e_status'] ) : 'draft';
        if ( ! in_array( $e_status, array( 'draft', 'published', 'closed' ), true ) ) { $e_status = 'draft'; }

        $start_raw = isset( $_POST['hcp_start'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_start'] ) ) : '';
        $end_raw   = isset( $_POST['hcp_end'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_end'] ) ) : '';
        $start_ts  = $start_raw ? strtotime( $start_raw . ' UTC' ) : 0;
        $end_ts    = $end_raw ? strtotime( $end_raw . ' UTC' ) : 0;

        $data = array(
            'post_type'    => 'hcp_election',
            'post_title'   => $title,
            'post_content' => $body,
            'post_status'  => $status,
        );

        if ( $id > 0 ) {
            $existing = get_post( $id );
            if ( ! $existing || 'hcp_election' !== $existing->post_type ) {
                wp_die( esc_html__( 'Invalid election.', 'hoa-coa-portal-pro' ) );
            }
            if ( (int) get_post_meta( $id, '_hcp_finalized', true ) === 1 ) {
                wp_die( esc_html__( 'Election results are finalized and this election is locked.', 'hoa-coa-portal-pro' ) );
            }
            $data['ID'] = $id;
            $new_id = wp_update_post( $data, true );
        } else {
            $new_id = wp_insert_post( $data, true );
        }

        if ( is_wp_error( $new_id ) ) {
            wp_die( esc_html( $new_id->get_error_message() ) );
        }

        update_post_meta( (int) $new_id, '_hcp_status', $e_status );
        update_post_meta( (int) $new_id, '_hcp_start_at', $start_ts ? (int) $start_ts : 0 );
        update_post_meta( (int) $new_id, '_hcp_end_at', $end_ts ? (int) $end_ts : 0 );

        $q_mode = isset( $_POST['hcp_quorum_mode'] ) ? sanitize_key( (string) $_POST['hcp_quorum_mode'] ) : 'units';
        if ( ! in_array( $q_mode, array( 'units', 'weight' ), true ) ) { $q_mode = 'units'; }
        $q_percent = isset( $_POST['hcp_quorum_percent'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['hcp_quorum_percent'] ) ) : 0.0;
        if ( $q_percent < 0 ) { $q_percent = 0; }
        if ( $q_percent > 100 ) { $q_percent = 100; }
        update_post_meta( (int) $new_id, '_hcp_quorum_mode', $q_mode );
        update_post_meta( (int) $new_id, '_hcp_quorum_percent', $q_percent );


        wp_safe_redirect( admin_url( 'admin.php?page=hcp-elections&action=edit&id=' . (int) $new_id . '&saved=1' ) );
        exit;
    }

    public static function save_settings(): void {
        self::save_common( 'hcp_save_settings' );
    check_admin_referer( 'hcp_save_settings' );
        $page_id = isset( $_POST['hcp_portal_page_id'] ) ? absint( $_POST['hcp_portal_page_id'] ) : 0;
        $load_css = isset( $_POST['hcp_load_css'] ) ? 1 : 0;

        $settings = HCP_Helpers::settings();
        $settings['portal_page_id'] = $page_id;
        $settings['load_css'] = $load_css;

        $settings['assoc_name'] = isset( $_POST['hcp_assoc_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_assoc_name'] ) ) : '';
        $settings['assoc_logo_id'] = isset( $_POST['hcp_assoc_logo_id'] ) ? absint( $_POST['hcp_assoc_logo_id'] ) : 0;
        $settings['assoc_phone'] = isset( $_POST['hcp_assoc_phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_assoc_phone'] ) ) : '';
        $settings['assoc_email'] = isset( $_POST['hcp_assoc_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['hcp_assoc_email'] ) ) : '';
        $settings['assoc_address'] = isset( $_POST['hcp_assoc_address'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['hcp_assoc_address'] ) ) : '';
        $settings['assoc_website'] = isset( $_POST['hcp_assoc_website'] ) ? esc_url_raw( wp_unslash( (string) $_POST['hcp_assoc_website'] ) ) : '';

        // Florida compliance sizing.
        $assoc_type = isset( $_POST['hcp_assoc_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['hcp_assoc_type'] ) ) : 'condo';
        if ( 'hoa' !== $assoc_type ) { $assoc_type = 'condo'; }
        $size_count = isset( $_POST['hcp_size_count'] ) ? absint( $_POST['hcp_size_count'] ) : 0;
        $settings['assoc_type'] = $assoc_type;
        if ( 'hoa' === $assoc_type ) {
            $settings['parcel_count'] = $size_count;
        } else {
            $settings['unit_count'] = $size_count;
        }

        HCP_Helpers::update_settings( $settings );

        HCP_Helpers::safe_redirect( admin_url( 'admin.php?page=hcp-settings&saved=1' ) );
    }
public static function register_election_metaboxes(): void {
    add_meta_box(
        'hcp-election-finalize',
        __( 'Finalize Election (Official Results)', 'hoa-coa-portal-pro' ),
        array( __CLASS__, 'metabox_finalize_election' ),
        'hcp_election',
        'side',
        'high'
    );
}

public static function metabox_finalize_election( \WP_Post $post ): void {
    $election_id = (int) $post->ID;
    $finalized   = (bool) get_post_meta( $election_id, '_hcp_finalized', true );

    echo '<p>' . esc_html__( 'Finalizing locks the election and closes voting. This is intended as an official board action.', 'hoa-coa-portal-pro' ) . '</p>';

    if ( $finalized ) {
        $at = (int) get_post_meta( $election_id, '_hcp_finalized_at', true );
        $by = (int) get_post_meta( $election_id, '_hcp_finalized_by', true );
        echo '<p><strong>' . esc_html__( 'Status:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html__( 'Finalized', 'hoa-coa-portal-pro' ) . '</p>';
        if ( $at ) {
            echo '<p>' . esc_html__( 'Finalized at:', 'hoa-coa-portal-pro' ) . ' <code>' . esc_html( gmdate( 'Y-m-d H:i:s', $at ) . ' UTC' ) . '</code></p>';
        }
        if ( $by ) {
            $u = get_user_by( 'id', $by );
            if ( $u ) {
                echo '<p>' . esc_html__( 'Finalized by:', 'hoa-coa-portal-pro' ) . ' <code>' . esc_html( $u->user_login ) . '</code></p>';
            }
        }

        $csv_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=hcp_export_votes_csv&election_id=' . $election_id ),
            'hcp_export_votes_csv_' . $election_id
        );
        echo '<p><a class="button button-secondary" href="' . esc_url( $csv_url ) . '">' . esc_html__( 'Export Audit CSV', 'hoa-coa-portal-pro' ) . '</a></p>';
        echo '<p style="margin-top:10px;color:#666;">' . esc_html__( 'Edits to ballot questions/choices should not be made after finalization.', 'hoa-coa-portal-pro' ) . '</p>';
        return;
    }

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="hcp_finalize_election" />';
    echo '<input type="hidden" name="election_id" value="' . esc_attr( (string) $election_id ) . '" />';
    wp_nonce_field( 'hcp_finalize_election_' . $election_id, 'hcp_nonce' );

    echo '<p><label><input type="checkbox" name="hcp_confirm_finalize" value="1" /> ' . esc_html__( 'I certify these results are official and authorize finalization.', 'hoa-coa-portal-pro' ) . '</label></p>';
    echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Finalize Results', 'hoa-coa-portal-pro' ) . '</button></p>';
    echo '</form>';
}

public static function handle_finalize_election(): void {
    self::require_manage();

    $election_id = isset( $_POST['election_id'] ) ? absint( $_POST['election_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( ! $election_id ) {
        wp_safe_redirect( admin_url( 'edit.php?post_type=hcp_election' ) );
        exit;
    }

    check_admin_referer( 'hcp_finalize_election_' . $election_id, 'hcp_nonce' );

    $confirm_val = isset( $_POST['hcp_confirm_finalize'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hcp_confirm_finalize'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $confirm     = ( '1' === $confirm_val );
    if ( ! $confirm ) {
        wp_safe_redirect( admin_url( 'post.php?post=' . $election_id . '&action=edit&hcp_notice=finalize_missing_confirm' ) );
        exit;
    }

    HCP_Tally::snapshot_ballot( $election_id ); // Ensure snapshot exists.
    HCP_Tally::finalize_election( $election_id, get_current_user_id() );

    wp_safe_redirect( admin_url( 'post.php?post=' . $election_id . '&action=edit&hcp_notice=finalized' ) );
    exit;
}

public static function handle_export_votes_csv(): void {
    self::require_manage();

    $election_id = isset( $_GET['election_id'] ) ? absint( $_GET['election_id'] ) : 0;
    if ( ! $election_id ) {
        wp_die( esc_html__( 'Missing election ID.', 'hoa-coa-portal-pro' ) );
    }

    check_admin_referer( 'hcp_export_votes_csv_' . $election_id );

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

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=hcp-audit-election-' . $election_id . '.csv' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'vote_id', 'election_id', 'unit_id', 'unit_number', 'user_id', 'submitted_at_utc', 'answers_json', 'snapshot_hash', 'vote_hash', 'prev_hash' ) );

    $prev_hash = '';
    $snapshot_hash = (string) get_post_meta( $election_id, '_hcp_snapshot_hash', true );

    foreach ( $votes as $vote_id ) {
        $vote_id = (int) $vote_id;
        $unit_id = (int) get_post_meta( $vote_id, '_hcp_unit_id', true );
        $user_id = (int) get_post_meta( $vote_id, '_hcp_user_id', true );
        $submitted_at = (int) get_post_meta( $vote_id, '_hcp_submitted_at', true );
        $answers = (string) get_post_meta( $vote_id, '_hcp_answers', true );

        $record = array(
            'vote_id'      => $vote_id,
            'election_id'   => $election_id,
            'unit_id'       => $unit_id,
            'user_id'       => $user_id,
            'submitted_at'  => $submitted_at,
            'answers_json'  => $answers,
            'snapshot_hash' => $snapshot_hash,
        );

        $vote_hash = HCP_Tally::hash_vote_record( $record, $prev_hash );

        fputcsv( $out, array(
            $vote_id,
            $election_id,
            $unit_id,
            method_exists( 'HCP_Units', 'get_unit_number' ) ? HCP_Units::get_unit_number( $unit_id ) : '',
            $user_id,
            $submitted_at ? gmdate( 'Y-m-d H:i:s', $submitted_at ) . ' UTC' : '',
            $answers,
            $snapshot_hash,
            $vote_hash,
            $prev_hash,
        ) );

        $prev_hash = $vote_hash;
    }

    fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    exit;
}


public static function page_audit_summary(): void {
    self::require_manage();

    $election_id = isset( $_GET['election_id'] ) ? absint( $_GET['election_id'] ) : 0;

    echo '<div class="wrap">';
		echo '<div class="hcp-print-actions">';
		echo '<a href="#" class="button button-primary" data-hcp-print-audit="1">' . esc_html__( 'Print', 'hoa-coa-portal-pro' ) . '</a>';
		echo '<span style="opacity:.8;">' . wp_kses_post( '<span class="hcp-screen-only">' . esc_html__( 'Tip: use Print to PDF for board records.', 'hoa-coa-portal-pro' ) . '</span>' ) . '</span>';
		echo '</div>';
    echo '<h1>' . esc_html__( 'Audit Summary (Printable)', 'hoa-coa-portal-pro' ) . '</h1>';
    echo '<p>' . esc_html__( 'Select an election to generate a print-ready audit summary. Use your browser print dialog to save as PDF.', 'hoa-coa-portal-pro' ) . '</p>';

    $elections = get_posts( array(
        'post_type'      => 'hcp_election',
        'post_status'    => array( 'publish', 'private', 'draft' ),
        'numberposts'    => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ) );

    echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin:12px 0;">';
    echo '<input type="hidden" name="page" value="hcp-audit-summary" />';
    echo '<select id="hcp-election-id" name="election_id">';
    echo '<option value="0">' . esc_html__( 'Select an election…', 'hoa-coa-portal-pro' ) . '</option>';
    foreach ( $elections as $eid ) {
        $eid = (int) $eid;
        echo '<option value="' . esc_attr( (string) $eid ) . '"' . selected( $election_id, $eid, false ) . '>' . esc_html( get_the_title( $eid ) ) . '</option>';
    }
    echo '</select> ';
    echo '<label style="margin-left:10px;"><input type="checkbox" name="show_units" value="1" ' . checked( isset( $_GET['show_units'] ) ? (int) $_GET['show_units'] : 0, 1, false ) . ' /> ' . esc_html__( 'Include eligible unit list (admin only)', 'hoa-coa-portal-pro' ) . '</label> ';echo '<button class="button button-primary" type="submit">' . esc_html__( 'Generate', 'hoa-coa-portal-pro' ) . '</button>';
    echo '</form>';

    if ( ! $election_id ) {
        echo '</div>';
        return;
    }

    if ( ! class_exists( 'HCP_Tally' ) || ! method_exists( 'HCP_Tally', 'get_election_audit_summary' ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Audit summary engine is unavailable. Please update the plugin files.', 'hoa-coa-portal-pro' ) . '</p></div>';
        echo '</div>';
        return;
    }

    $summary = HCP_Tally::get_election_audit_summary( $election_id );
    $finalized = ! empty( $summary['finalized'] );

    echo '<style>
        .hcp-print-wrap{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;max-width:1000px}
        .hcp-print-header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
        .hcp-pill{display:inline-block;padding:4px 10px;border-radius:999px;border:1px solid #dcdcde;background:#f6f7f7;font-size:12px}
        .hcp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:12px}
        .hcp-kv{border:1px solid #e5e5e5;border-radius:10px;padding:10px}
        .hcp-kv strong{display:block;margin-bottom:4px}
        .hcp-table{width:100%;border-collapse:collapse;margin-top:12px}
        .hcp-table th,.hcp-table td{padding:8px 6px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
        @media print{
            #adminmenumain,#wpadminbar,.notice,.wrap > p,.wrap form,.wrap h1{display:none !important}
            .wrap{margin:0 !important}
            .hcp-print-wrap{border:none !important}
            a{color:#000;text-decoration:none}
        }
    </style>';

    echo '<p><button type="button" class="button" onclick="window.print();">' . esc_html__( 'Print / Save as PDF', 'hoa-coa-portal-pro' ) . '</button></p>';

    echo '<div class="hcp-print-wrap">';
    echo '<div class="hcp-print-header">';
    echo '<div>';
    echo '<h2 style="margin:0 0 6px 0;">' . esc_html( (string) ( $summary['title'] ?? '' ) ) . '</h2>';
    echo '<span class="hcp-pill">' . esc_html__( 'System: HOA/COA Portal', 'hoa-coa-portal-pro' ) . '</span> ';
    echo '<span class="hcp-pill">' . esc_html__( 'Audit Summary', 'hoa-coa-portal-pro' ) . '</span> ';
    echo '<span class="hcp-pill">' . ( $finalized ? esc_html__( 'Finalized', 'hoa-coa-portal-pro' ) : esc_html__( 'Not Finalized', 'hoa-coa-portal-pro' ) ) . '</span>';
    echo '</div>';
    echo '<div style="text-align:right;">';
    echo '<div class="hcp-pill">' . esc_html__( 'Generated:', 'hoa-coa-portal-pro' ) . ' ' . esc_html( gmdate( 'Y-m-d H:i:s' ) . ' UTC' ) . '</div>';
    echo '</div>';
    echo '</div>';

    $eligible = (int) ( $summary['eligible_units'] ?? 0 );
    $votes_cast = (int) ( $summary['votes_cast'] ?? 0 );
    $quorum_req = (float) ( $summary['quorum_required'] ?? 0 );
    $pct = ( $eligible > 0 ) ? ( ( $votes_cast / $eligible ) * 100.0 ) : 0.0;

    echo '<div class="hcp-grid">';
    echo '<div class="hcp-kv"><strong>' . esc_html__( 'Eligible Units', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $eligible ) . '</div>';
    echo '<div class="hcp-kv"><strong>' . esc_html__( 'Votes Cast', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $votes_cast ) . '</div>';
    echo '<div class="hcp-kv"><strong>' . esc_html__( 'Quorum Required', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $quorum_req ) . '%</div>';
    echo '<div class="hcp-kv"><strong>' . esc_html__( 'Participation', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( number_format_i18n( $pct, 2 ) ) . '%</div>';
    echo '</div>';

    echo '<div class="hcp-grid">';
    echo '<div class="hcp-kv"><strong>' . esc_html__( 'Snapshot Hash', 'hoa-coa-portal-pro' ) . '</strong><code style="word-break:break-all;">' . esc_html( (string) ( $summary['snapshot_hash'] ?? '' ) ) . '</code></div>';
    echo '<div class="hcp-kv"><strong>' . esc_html__( 'Eligibility Rule', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html__( 'Only units with a Primary Voting Owner assigned are eligible.', 'hoa-coa-portal-pro' ) . '</div>';
    echo '</div>';

    if ( $finalized ) {
        $at = (int) ( $summary['finalized_at'] ?? 0 );
        $by = (int) ( $summary['finalized_by'] ?? 0 );
        $u = $by ? get_user_by( 'id', $by ) : null;

        echo '<div class="hcp-grid">';
        echo '<div class="hcp-kv"><strong>' . esc_html__( 'Finalized At', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( $at ? gmdate( 'Y-m-d H:i:s', $at ) . ' UTC' : '' ) . '</div>';
        echo '<div class="hcp-kv"><strong>' . esc_html__( 'Finalized By', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( $u ? $u->user_login : '' ) . '</div>';
        echo '<div class="hcp-kv"><strong>' . esc_html__( 'Quorum Status', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( ! empty( $summary['quorum_met'] ) ? __( 'Met', 'hoa-coa-portal-pro' ) : __( 'Not Met', 'hoa-coa-portal-pro' ) ) . '</div>';
        echo '</div>';
    } else {
        echo '<p style="margin-top:12px;"><strong>' . esc_html__( 'Note:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html__( 'This election has not been finalized. Results are preliminary.', 'hoa-coa-portal-pro' ) . '</p>';
    }

    echo '<h3 style="margin-top:18px;">' . esc_html__( 'Results', 'hoa-coa-portal-pro' ) . '</h3>';

    $results = $summary['results'] ?? array();
    if ( empty( $results ) ) {
        echo '<p>' . esc_html__( 'No results available yet.', 'hoa-coa-portal-pro' ) . '</p>';
    } else {
        echo '<table class="hcp-print-table">';
        echo '<thead><tr><th>' . esc_html__( 'Question', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Choice', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Total', 'hoa-coa-portal-pro' ) . '</th></tr></thead><tbody>';

        foreach ( $results as $q_key => $choices ) {
            $question_label = is_string( $q_key ) ? $q_key : (string) $q_key;

            if ( is_array( $choices ) ) {
                $is_first = true;
                foreach ( $choices as $choice_label => $total ) {
                    echo '<tr>';
                    echo '<td>' . ( $is_first ? esc_html( $question_label ) : '' ) . '</td>';
                    echo '<td>' . esc_html( (string) $choice_label ) . '</td>';
                    echo '<td>' . esc_html( (string) $total ) . '</td>';
                    echo '</tr>';
                    $is_first = false;
                }
            } else {
                echo '<tr><td>' . esc_html( $question_label ) . '</td><td></td><td></td></tr>';
            }
        }

        echo '</tbody></table>';
    }

    // Tamper-evident chain summary (computed from vote records).
$vote_ids = get_posts( array(
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

$chain_first  = '';
$chain_last   = '';
$chain_count  = 0;
$prev_hash    = '';
$snapshot_hash = (string) ( $summary['snapshot_hash'] ?? '' );

if ( class_exists( 'HCP_Tally' ) && method_exists( 'HCP_Tally', 'hash_vote_record' ) ) {
    foreach ( $vote_ids as $vid ) {
        $vid = (int) $vid;

        $unit_id      = (int) get_post_meta( $vid, '_hcp_unit_id', true );
        $user_id      = (int) get_post_meta( $vid, '_hcp_user_id', true );
        $submitted_at = (int) get_post_meta( $vid, '_hcp_submitted_at', true );
        $answers      = (string) get_post_meta( $vid, '_hcp_answers', true );

        $record = array(
            'vote_id'      => $vid,
            'election_id'   => $election_id,
            'unit_id'       => $unit_id,
            'user_id'       => $user_id,
            'submitted_at'  => $submitted_at,
            'answers_json'  => $answers,
            'snapshot_hash' => $snapshot_hash,
        );

        $vote_hash = HCP_Tally::hash_vote_record( $record, $prev_hash );

        if ( 0 === $chain_count ) {
            $chain_first = $vote_hash;
        }
        $chain_last = $vote_hash;

        $prev_hash = $vote_hash;
        $chain_count++;
    }
}

// Eligibility & quorum appendix (defensible math + breakdown).
$show_units = isset( $_GET['show_units'] ) ? ( 1 === absint( $_GET['show_units'] ) ) : false;

$all_units = get_posts( array(
    'post_type'      => 'hcp_unit',
    'post_status'    => 'publish',
    'numberposts'    => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'fields'         => 'ids',
    'no_found_rows'  => true,
) );

$eligible_unit_ids = array();
foreach ( $all_units as $unit_post_id ) {
    $unit_post_id = (int) $unit_post_id;
    $primary_owner_id = (int) get_post_meta( $unit_post_id, '_hcp_primary_owner', true );
    if ( $primary_owner_id > 0 ) {
        $eligible_unit_ids[] = $unit_post_id;
    }
}

$total_units = count( $all_units );
$eligible_units_calc = count( $eligible_unit_ids );
$ineligible_units = max( 0, $total_units - $eligible_units_calc );

echo '<div class="hcp-page-break"></div>';
	echo '<h3 style="margin-top:18px;">' . esc_html__( 'Eligibility & Quorum Appendix', 'hoa-coa-portal-pro' ) . '</h3>';
echo '<p style="color:#555;margin:6px 0 10px 0;">' . esc_html__( 'This appendix documents how eligibility, participation, and quorum are calculated for this election.', 'hoa-coa-portal-pro' ) . '</p>';

echo '<div class="hcp-grid">';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Total Units', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $total_units ) . '</div>';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Eligible Units', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $eligible_units_calc ) . '</div>';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Ineligible Units', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $ineligible_units ) . '</div>';
echo '</div>';

echo '<div class="hcp-grid">';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Eligibility Rule', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html__( 'Eligible = only units with a Primary Voting Owner assigned.', 'hoa-coa-portal-pro' ) . '</div>';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Quorum Rule', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html__( 'Quorum is evaluated using eligible units only.', 'hoa-coa-portal-pro' ) . '</div>';
echo '</div>';

$eligible_for_math = $eligible_units_calc;
$votes_for_math = (int) $votes_cast;
$quorum_percent = (float) $quorum_req;
$participation_pct = ( $eligible_for_math > 0 ) ? ( ( $votes_for_math / $eligible_for_math ) * 100.0 ) : 0.0;

echo '<div class="hcp-grid">';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Votes Cast', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $votes_for_math ) . '</div>';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Participation', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( number_format_i18n( $participation_pct, 2 ) ) . '%</div>';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Quorum Required', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $quorum_percent ) . '%</div>';
echo '</div>';

$quorum_met_calc = ( $eligible_for_math > 0 ) ? ( $participation_pct >= $quorum_percent ) : false;
echo '<p style="margin-top:8px;"><strong>' . esc_html__( 'Quorum Met:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html( $quorum_met_calc ? __( 'Yes', 'hoa-coa-portal-pro' ) : __( 'No', 'hoa-coa-portal-pro' ) ) . '</p>';

echo '<p style="color:#555;margin-top:8px;">' . esc_html__( 'Ineligible units are excluded because they do not have a Primary Voting Owner assigned. This prevents duplicate voting and ensures one official ballot per unit.', 'hoa-coa-portal-pro' ) . '</p>';

if ( $show_units ) {
    echo '<h4 style="margin-top:12px;">' . esc_html__( 'Eligible Unit List (Admin)', 'hoa-coa-portal-pro' ) . '</h4>';
    if ( empty( $eligible_unit_ids ) ) {
        echo '<p>' . esc_html__( 'No eligible units found. A unit becomes eligible once a Primary Voting Owner is assigned.', 'hoa-coa-portal-pro' ) . '</p>';
    } else {
        echo '<table class="hcp-print-table">';
        echo '<thead><tr><th>' . esc_html__( 'Unit', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Primary Voting Owner Assigned', 'hoa-coa-portal-pro' ) . '</th></tr></thead><tbody>';
        foreach ( $eligible_unit_ids as $unit_post_id ) {
            $unit_post_id = (int) $unit_post_id;
            $primary_owner_id = (int) get_post_meta( $unit_post_id, '_hcp_primary_owner', true );
            $u = $primary_owner_id ? get_user_by( 'id', $primary_owner_id ) : null;
            echo '<tr>';
            echo '<td>' . esc_html( get_the_title( $unit_post_id ) ) . '</td>';
            echo '<td>' . esc_html( $u ? $u->user_login : '' ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}

echo '<hr style="margin:18px 0;border:none;border-top:1px dashed #ddd;" />';


echo '<div class="hcp-page-break"></div>';
	echo '<h3 style="margin-top:18px;">' . esc_html__( 'Verification Summary', 'hoa-coa-portal-pro' ) . '</h3>';
echo '<div class="hcp-grid">';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Tamper-evident chain', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html__( 'Enabled', 'hoa-coa-portal-pro' ) . '</div>';
echo '<div class="hcp-kv"><strong>' . esc_html__( 'Votes in chain', 'hoa-coa-portal-pro' ) . '</strong>' . esc_html( (string) $chain_count ) . '</div>';
echo '</div>';

if ( $chain_count > 0 ) {
    echo '<div class="hcp-grid">';
    echo '<div class="hcp-kv"><strong>' . esc_html__( 'First vote hash', 'hoa-coa-portal-pro' ) . '</strong><code style="word-break:break-all;">' . esc_html( $chain_first ) . '</code></div>';
    echo '<div class="hcp-kv"><strong>' . esc_html__( 'Last vote hash', 'hoa-coa-portal-pro' ) . '</strong><code style="word-break:break-all;">' . esc_html( $chain_last ) . '</code></div>';
    echo '</div>';
}

echo '<p style="color:#555;margin-top:8px;">' . esc_html__( 'If any vote record is modified, the hash chain will no longer match and the verification summary will change.', 'hoa-coa-portal-pro' ) . '</p>';

echo '<h3 style="margin-top:18px;">' . esc_html__( 'Board Certification', 'hoa-coa-portal-pro' ) . '</h3>';

if ( $finalized ) {
    $at = (int) ( $summary['finalized_at'] ?? 0 );
    $by = (int) ( $summary['finalized_by'] ?? 0 );
    $u  = $by ? get_user_by( 'id', $by ) : null;

    echo '<p><strong>' . esc_html__( 'Finalized By:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html( $u ? $u->user_login : '' ) . ' &nbsp; <strong>' . esc_html__( 'Finalized At:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html( $at ? gmdate( 'Y-m-d H:i:s', $at ) . ' UTC' : '' ) . '</p>';
} else {
    echo '<p style="color:#8a8a8a;">' . esc_html__( 'This election is not finalized. Certification below is for printing/signature only.', 'hoa-coa-portal-pro' ) . '</p>';
}

echo '<table class="hcp-print-table" style="margin-top:8px;">';
echo '<thead><tr><th>' . esc_html__( 'Role / Name', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Signature', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Date', 'hoa-coa-portal-pro' ) . '</th><th>' . esc_html__( 'Assigned By', 'hoa-coa-portal-pro' ) . '</th></tr></thead><tbody>';
echo '<tr><td>' . esc_html__( 'Board Officer / Manager', 'hoa-coa-portal-pro' ) . '</td><td>______________________________</td><td>______________</td></tr>';
echo '<tr><td>' . esc_html__( 'Witness (Optional)', 'hoa-coa-portal-pro' ) . '</td><td>______________________________</td><td>______________</td></tr>';
echo '</tbody></table>';

echo '<p style="color:#555;margin-top:8px;">' . esc_html__( 'We certify that this audit summary reflects the official election results as recorded by the system.', 'hoa-coa-portal-pro' ) . '</p>';


echo '<hr style="margin:18px 0;border:none;border-top:1px solid #eee;" />';
    echo '<p style="color:#555;">' . esc_html__( 'This report is generated by HOA/COA Portal by Sun Life Tech. Use browser Print to save as PDF.', 'hoa-coa-portal-pro' ) . '</p>';
    echo '</div>';
    echo '</div>';
}




private static function admin_tooltip( string $label, string $text ): string {
    return '<span class="hcp-admin-tooltip"><span class="hcp-admin-tip" role="button" tabindex="0" aria-label="' . esc_attr( $label ) . '">?</span><span class="hcp-admin-tip-panel">' . esc_html( $text ) . '</span></span>';
}

private static function election_status( int $election_id ): string {
    $finalized = (bool) get_post_meta( $election_id, '_hcp_finalized', true );
    if ( $finalized ) {
        return 'closed';
    }

    $start = (int) get_post_meta( $election_id, '_hcp_start_ts', true );
    $end   = (int) get_post_meta( $election_id, '_hcp_end_ts', true );
    $now   = time();

    if ( $start && $end ) {
        if ( $now < $start ) {
            return 'draft';
        }
        if ( $now >= $start && $now <= $end ) {
            return 'open';
        }
        return 'closed';
    }

    return 'draft';
}

private static function render_badge( string $status ): string {
    $status = strtolower( $status );
    $label  = __( 'Draft', 'hoa-coa-portal-pro' );
    $class  = 'hcp-badge--draft';

    if ( 'published' === $status || 'publish' === $status ) {
		$label = __( 'Published', 'hoa-coa-portal-pro' );
		$class = 'hcp-badge--open';
	} elseif ( 'open' === $status || 'active' === $status ) {
        $label = __( 'Open', 'hoa-coa-portal-pro' );
        $class = 'hcp-badge--open';
    } elseif ( 'closed' === $status || 'finalized' === $status ) {
        $label = __( 'Closed', 'hoa-coa-portal-pro' );
        $class = 'hcp-badge--closed';
    }

    return '<span class="hcp-badge ' . esc_attr( $class ) . '"><span class="hcp-badge-dot" aria-hidden="true"></span>' . esc_html( $label ) . '</span>';
}



public static function enqueue_admin_assets( string $hook ): void {
    // Load only on our plugin pages.
    if ( false === strpos( $hook, 'hcp' ) && false === strpos( $hook, 'hoa-coa-portal-pro' ) ) {
        // Still allow on post editor for our CPTs.
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ! in_array( $screen->post_type, [ 'hcp_election', 'hcp_agenda', 'hcp_minutes' ], true ) ) {
            return;
        }
    }

    $ver = defined( 'HCP_VERSION' ) ? HCP_VERSION : '1.0.0';
    wp_enqueue_style( 'hcp-admin', plugins_url( 'assets/css/hcp-admin.css', dirname( __FILE__ ) ), [], $ver );
    wp_enqueue_script( 'hcp-admin', plugins_url( 'assets/js/hcp-admin.js', dirname( __FILE__ ) ), [], $ver, true );
}



public static function election_columns( array $columns ): array {
    $out = array();
    foreach ( $columns as $key => $label ) {
        $out[ $key ] = $label;
        if ( 'title' === $key ) {
            $out['hcp_status'] = __( 'Status', 'hoa-coa-portal-pro' );
        }
    }
    return $out;
}

public static function election_column_content( string $column, int $post_id ): void {
    if ( 'hcp_status' !== $column ) {
        return;
    }
    $status = self::election_status( $post_id );
    echo self::render_badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}


public static function agenda_columns( array $columns ): array {
    // Keep title/date, insert status after title.
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['hcp_status'] = __( 'Status', 'hoa-coa-portal-pro' );
                    $new['hcp_created_by'] = __( 'Created By', 'hoa-coa-portal-pro' );
            $new['hcp_assigned_to'] = __( 'Assigned To', 'hoa-coa-portal-pro' );
            $new['hcp_updated'] = __( 'Updated', 'hoa-coa-portal-pro' );
}
    }
    return $new;
}

public static function agenda_column_content( string $column, int $post_id ): void {
    if ( 'hcp_status' !== $column ) {
        return;
    }

    $status = get_post_status( $post_id );
    echo wp_kses_post( self::render_badge( (string) $status ) );

    if ( 'hcp_created_by' === $column ) {
        $post = get_post( $post_id );
        if ( $post && ! empty( $post->post_author ) ) {
            $u = get_userdata( (int) $post->post_author );
            if ( $u ) {
                echo esc_html( $u->display_name );
                return;
            }
        }
        echo '&mdash;';
        return;
    }

    if ( 'hcp_updated' === $column ) {
        $ts = get_post_modified_time( 'U', false, $post_id );
        if ( $ts ) {
            echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $ts ) );
            return;
        }
        echo '&mdash;';
        return;
    }


}

public static function minutes_columns( array $columns ): array {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['hcp_status'] = __( 'Status', 'hoa-coa-portal-pro' );
                    $new['hcp_created_by'] = __( 'Created By', 'hoa-coa-portal-pro' );
            $new['hcp_assigned_to'] = __( 'Assigned To', 'hoa-coa-portal-pro' );
            $new['hcp_updated'] = __( 'Updated', 'hoa-coa-portal-pro' );
}
    }
    return $new;
}

public static function minutes_column_content( string $column, int $post_id ): void {
    if ( 'hcp_status' !== $column ) {
        return;
    }

    $status = get_post_status( $post_id );
    echo wp_kses_post( self::render_badge( (string) $status ) );

    if ( 'hcp_created_by' === $column ) {
        $post = get_post( $post_id );
        if ( $post && ! empty( $post->post_author ) ) {
            $u = get_userdata( (int) $post->post_author );
            if ( $u ) {
                echo esc_html( $u->display_name );
                return;
            }
        }
        echo '&mdash;';
        return;
    }

    if ( 'hcp_updated' === $column ) {
        $ts = get_post_modified_time( 'U', false, $post_id );
        if ( $ts ) {
            echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $ts ) );
            return;
        }
        echo '&mdash;';
        return;
    }



    if ( 'hcp_assigned_to' === $column ) {
        $uid = (int) get_post_meta( $post_id, '_hcp_assigned_to', true );
        if ( $uid > 0 ) {
            $u = get_userdata( $uid );
            if ( $u ) {
                echo esc_html( $u->display_name );
                return;
            }
        }
        echo '&mdash;';
        return;
    }


}




public static function add_assignment_metaboxes(): void {
    $screens = array( 'hcp_agenda', 'hcp_minutes' );
    foreach ( $screens as $screen ) {
        add_meta_box(
            'hcp_assigned_to',
            __( 'Assignment', 'hoa-coa-portal-pro' ),
            [ __CLASS__, 'render_assignment_metabox' ],
            $screen,
            'side',
            'default'
        );
    }
}

public static function render_assignment_metabox( WP_Post $post ): void {
    if ( ! current_user_can( HCP_Caps::CAP_MANAGE ) ) {
        echo esc_html__( 'You do not have permission to edit assignment.', 'hoa-coa-portal-pro' );
        return;
    }

    wp_nonce_field( 'hcp_save_assignment', 'hcp_assignment_nonce' );

    $assigned_to = (int) get_post_meta( $post->ID, '_hcp_assigned_to', true );

    // Office staff + administrators.
    $users = get_users( array(
        'role__in' => array( 'hcp_office', 'administrator' ),
        'orderby'  => 'display_name',
        'order'    => 'ASC',
        'number'   => 200,
        'fields'   => array( 'ID', 'display_name' ),
    ) );

    echo '<p style="margin:0 0 8px 0;">' . esc_html__( 'Assign this item to a staff member for follow‑up.', 'hoa-coa-portal-pro' ) . '</p>';
    echo '<label class="screen-reader-text" for="hcp_assigned_to_select">' . esc_html__( 'Assigned To', 'hoa-coa-portal-pro' ) . '</label>';
    echo '<select id="hcp_assigned_to_select" name="hcp_assigned_to" style="width:100%;">';
    echo '<option value="0">' . esc_html__( '— Unassigned —', 'hoa-coa-portal-pro' ) . '</option>';
    foreach ( $users as $u ) {
        echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . selected( $assigned_to, (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
    }
    echo '</select>';

    echo '<p class="description" style="margin-top:8px;">' . esc_html__( 'Tip: Use the “Assigned to me / Unassigned” filter on the list screen.', 'hoa-coa-portal-pro' ) . '</p>';
}

public static function save_assignment_meta( int $post_id, WP_Post $post ): void {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( 'hcp_agenda' !== $post->post_type && 'hcp_minutes' !== $post->post_type ) {
        return;
    }

    if ( ! isset( $_POST['hcp_assignment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['hcp_assignment_nonce'] ) ), 'hcp_save_assignment' ) ) {
        return;
    }

    if ( ! current_user_can( HCP_Caps::CAP_MANAGE ) ) {
        return;
    }

    $assigned_to = isset( $_POST['hcp_assigned_to'] ) ? (int) $_POST['hcp_assigned_to'] : 0;

    if ( $assigned_to > 0 ) {
        update_post_meta( $post_id, '_hcp_assigned_to', $assigned_to );
    } else {
        delete_post_meta( $post_id, '_hcp_assigned_to' );
    }
}


public static function agenda_sortable_columns( array $columns ): array {
    $columns['hcp_assigned_to'] = 'hcp_assigned_to';
    return $columns;
}

public static function minutes_sortable_columns( array $columns ): array {
    $columns['hcp_assigned_to'] = 'hcp_assigned_to';
    return $columns;
}

public static function assigned_filter_ui(): void {
    if ( ! is_admin() ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ( 'edit-hcp_agenda' !== $screen->id && 'edit-hcp_minutes' !== $screen->id ) ) {
        return;
    }

    $val = isset( $_GET['hcp_assigned_filter'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hcp_assigned_filter'] ) ) : '';

    echo '<label class="screen-reader-text" for="hcp_assigned_filter">' . esc_html__( 'Assigned filter', 'hoa-coa-portal-pro' ) . '</label>';
    echo '<select name="hcp_assigned_filter" id="hcp_assigned_filter">';
    echo '<option value="">' . esc_html__( 'All assignments', 'hoa-coa-portal-pro' ) . '</option>';
    echo '<option value="me"' . selected( $val, 'me', false ) . '>' . esc_html__( 'Assigned to me', 'hoa-coa-portal-pro' ) . '</option>';
    echo '<option value="unassigned"' . selected( $val, 'unassigned', false ) . '>' . esc_html__( 'Unassigned', 'hoa-coa-portal-pro' ) . '</option>';
    echo '</select>';
}

public static function assigned_filter_query( WP_Query $query ): void {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ( 'edit-hcp_agenda' !== $screen->id && 'edit-hcp_minutes' !== $screen->id ) ) {
        return;
    }

    $val = isset( $_GET['hcp_assigned_filter'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hcp_assigned_filter'] ) ) : '';
    if ( '' === $val ) {
        return;
    }

    $meta_query = (array) $query->get( 'meta_query' );

    if ( 'me' === $val ) {
        $meta_query[] = array(
            'key'     => '_hcp_assigned_to',
            'value'   => get_current_user_id(),
            'compare' => '=',
            'type'    => 'NUMERIC',
        );
    } elseif ( 'unassigned' === $val ) {
        $meta_query[] = array(
            'key'     => '_hcp_assigned_to',
            'compare' => 'NOT EXISTS',
        );
    } else {
        return;
    }

    $query->set( 'meta_query', $meta_query );

    // Sort by assignment when requested.
    $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['orderby'] ) ) : '';
    if ( 'hcp_assigned_to' === $orderby ) {
        $query->set( 'meta_key', '_hcp_assigned_to' );
        $query->set( 'orderby', 'meta_value_num' );
    }

}



    public static function page_compliance_docs(): void {
        self::require_manage();

        $settings = HCP_Helpers::settings();
        $assoc_type = isset( $settings['assoc_type'] ) ? (string) $settings['assoc_type'] : 'condo';
        if ( 'hoa' !== $assoc_type ) { $assoc_type = 'condo'; }

        $size = ( 'hoa' === $assoc_type ) ? (int) $settings['parcel_count'] : (int) $settings['unit_count'];
        $threshold = ( 'hoa' === $assoc_type ) ? 100 : 25;

        $required = ( $size >= $threshold );

        $required_slugs = HCP_Compliance::required_category_slugs( $assoc_type );
        $counts = HCP_Compliance::category_doc_counts();

        $missing = array();
        foreach ( $required_slugs as $slug ) {
            $c = isset( $counts[ $slug ] ) ? (int) $counts[ $slug ] : 0;
            if ( $c < 1 ) {
                $missing[] = $slug;
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Compliance Docs', 'hoa-coa-portal-pro' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Organize required association records for secure owner access. Redact protected information before uploading.', 'hoa-coa-portal-pro' ) . '</p>';

        // Export Compliance Index (CSV)
        $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=hcp_export_compliance_csv' ), 'hcp_export_compliance' );
        echo '<p style="margin:12px 0 0">';
        echo '<a class="button button-secondary" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export Compliance Index (CSV)', 'hoa-coa-portal-pro' ) . '</a>';
        echo '</p>';

// Print Compliance Binder
$binder_url = admin_url( 'admin.php?page=hcp-compliance-binder' );
echo '<p style="margin:8px 0 16px">';
echo '<a class="button button-secondary" target="_blank" href="' . esc_url( $binder_url ) . '">' . esc_html__( 'Open Compliance Binder (Print)', 'hoa-coa-portal-pro' ) . '</a>';
echo '</p>';


        echo '<div class="hcp-grid" style="display:grid;grid-template-columns:1fr;gap:16px;max-width:980px">';
        echo '<div class="hcp-card" style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px">';
        echo '<h2 style="margin-top:0">' . esc_html__( 'Checklist Status', 'hoa-coa-portal-pro' ) . '</h2>';

        if ( $required ) {
            echo '<p>' . esc_html__( 'Based on your saved association size, an online owner portal is required at your threshold. Use the checklist below to track common posting requirements.', 'hoa-coa-portal-pro' ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'Based on your saved association size, you may not be required to post everything below, but many communities still choose to organize these records for transparency.', 'hoa-coa-portal-pro' ) . '</p>';
        }

        echo '<p><strong>' . esc_html__( 'Association type:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html( ( 'hoa' === $assoc_type ) ? __( 'HOA (Ch. 720)', 'hoa-coa-portal-pro' ) : __( 'Condominium (Ch. 718)', 'hoa-coa-portal-pro' ) ) . '<br/>';
        echo '<strong>' . esc_html__( 'Saved size:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html( (string) $size ) . '<br/>';
        echo '<strong>' . esc_html__( 'Eligible checklist items:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html( (string) count( $required_slugs ) ) . '</p>';

        if ( empty( $missing ) ) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Nice — your checklist is complete for the selected association type.', 'hoa-coa-portal-pro' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Missing items:', 'hoa-coa-portal-pro' ) . ' ' . esc_html( (string) count( $missing ) ) . '</p></div>';
        }

        echo '<table class="widefat striped" style="margin-top:12px"><thead><tr>';
        echo '<th>' . esc_html__( 'Category', 'hoa-coa-portal-pro' ) . '</th><th style="width:120px">' . esc_html__( 'Docs', 'hoa-coa-portal-pro' ) . '</th><th style="width:160px">' . esc_html__( 'Status', 'hoa-coa-portal-pro' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $required_slugs as $slug ) {
            $label = HCP_Compliance::category_label_for_slug( $slug );
            $c = isset( $counts[ $slug ] ) ? (int) $counts[ $slug ] : 0;
            $ok = $c > 0;
            echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( (string) $c ) . '</td><td>' . ( $ok ? '<span class="hcp-badge hcp-badge-success">' . esc_html__( 'OK', 'hoa-coa-portal-pro' ) . '</span>' : '<span class="hcp-badge hcp-badge-warning">' . esc_html__( 'Missing', 'hoa-coa-portal-pro' ) . '</span>' ) . '</td></tr>';
        }

        echo '</tbody></table>';

        $manage_url = admin_url( 'edit.php?post_type=hcp_compliance_doc' );
        $add_url = admin_url( 'post-new.php?post_type=hcp_compliance_doc' );
        $cats_url = admin_url( 'edit-tags.php?taxonomy=hcp_compliance_category&post_type=hcp_compliance_doc' );
        echo '<p style="margin-top:14px">';
        echo '<a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add Compliance Document', 'hoa-coa-portal-pro' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( $manage_url ) . '">' . esc_html__( 'Manage Documents', 'hoa-coa-portal-pro' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( $cats_url ) . '">' . esc_html__( 'Manage Categories', 'hoa-coa-portal-pro' ) . '</a>';
        echo '</p>';

        echo '</div></div>'; // card + grid
        echo '</div>'; // wrap
    }

    /**
     * Compliance Binder (print-friendly).
     *
     * Free: Generates a structured binder view for governing docs, minutes, budgets, etc.
     * Premium: deadline automation & statute-specific completeness checks (future).
     */
    public static function page_compliance_binder(): void {
        if ( ! ( class_exists( 'HCP_Helpers' ) ? HCP_Helpers::can_manage() : current_user_can( 'manage_options' ) ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'hoa-coa-portal-pro' ) );
        }

        $settings    = HCP_Helpers::settings();
        $assoc_name  = isset( $settings['assoc_name'] ) ? (string) $settings['assoc_name'] : '';
        $assoc_type  = isset( $settings['assoc_type'] ) ? (string) $settings['assoc_type'] : 'condo';
        $generated_u = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
        $generated_l = wp_date( 'Y-m-d H:i:s T' );

        $title = $assoc_name ? $assoc_name : esc_html__( 'Your HOA/COA', 'hoa-coa-portal-pro' );

        // Query docs by category.
        $terms = get_terms(
            array(
                'taxonomy'   => HCP_Compliance::TAX,
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) ) {
            $terms = array();
        }

        echo '<div class="wrap hcp-admin hcp-print-wrap">';
        echo '<h1 class="screen-reader-text">' . esc_html__( 'Compliance Binder', 'hoa-coa-portal-pro' ) . '</h1>';

        echo '<div class="hcp-print-actions no-print">';
        echo '<a class="button button-primary" href="#" onclick="window.print();return false;">' . esc_html__( 'Print / Save PDF', 'hoa-coa-portal-pro' ) . '</a>';
        echo '</div>';

        // Print styles.
        echo '<style>
            .hcp-print-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;max-width:980px}
            .hcp-print-meta{display:flex;gap:18px;flex-wrap:wrap;margin:10px 0 14px}
            .hcp-print-meta div{font-size:13px;color:#50575e}
            .hcp-print-meta strong{color:#1d2327}
            .hcp-binder-section{margin-top:18px}
            .hcp-binder-section h2{font-size:16px;margin:0 0 10px}
            .hcp-binder-list{margin:0;padding-left:18px}
            .hcp-binder-list li{margin:6px 0}
            .hcp-binder-empty{color:#646970;font-style:italic}
            @media print{
                body{background:#fff}
                .no-print, #adminmenumain, #wpadminbar, #adminmenuwrap, #adminmenuback, #screen-meta, .notice{display:none!important}
                #wpcontent{margin-left:0!important}
                .hcp-print-card{border:none;box-shadow:none;max-width:none;padding:0}
                .hcp-print-header{position:fixed;top:0;left:0;right:0;padding:10px 0;border-bottom:1px solid #dcdcde}
                .hcp-print-footer{position:fixed;bottom:0;left:0;right:0;padding:8px 0;border-top:1px solid #dcdcde;font-size:11px;color:#646970}
                .hcp-print-header .inner, .hcp-print-footer .inner{max-width:980px;margin:0 auto;padding:0 12px;display:flex;justify-content:space-between;align-items:center}
                .hcp-print-body{margin-top:54px;margin-bottom:46px}
                .hcp-page-break{page-break-before:always}
                .hcp-page-num:after{content:counter(page)}
                @page{margin:16mm 14mm}
            }
        </style>';

        // Header / Footer for print.
        echo '<div class="hcp-print-header" aria-hidden="true"><div class="inner">';
        echo '<strong>' . esc_html( $title ) . '</strong>';
        echo '<span>' . esc_html__( 'Compliance Binder', 'hoa-coa-portal-pro' ) . '</span>';
        echo '</div></div>';

        echo '<div class="hcp-print-footer" aria-hidden="true"><div class="inner">';
        echo '<span>' . esc_html__( 'Generated by HOA/COA Portal by Sun Life Tech', 'hoa-coa-portal-pro' ) . '</span>';
        echo '<span>' . esc_html__( 'Page', 'hoa-coa-portal-pro' ) . ' <span class="hcp-page-num"></span></span>';
        echo '</div></div>';

        echo '<div class="hcp-print-card hcp-print-body">';

        echo '<h2 style="margin:0 0 6px;font-size:20px;">' . esc_html( $title ) . '</h2>';
        echo '<p style="margin:0 0 10px;color:#646970;">' . esc_html__( 'Organized record set for owner-portal compliance and board readiness.', 'hoa-coa-portal-pro' ) . '</p>';

        echo '<div class="hcp-print-meta">';
        echo '<div><strong>' . esc_html__( 'Association type', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( 'condo' === $assoc_type ? __( 'Condominium (FS 718)', 'hoa-coa-portal-pro' ) : __( 'HOA (FS 720)', 'hoa-coa-portal-pro' ) ) . '</div>';
        echo '<div><strong>' . esc_html__( 'Generated (local)', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( $generated_l ) . '</div>';
        echo '<div><strong>' . esc_html__( 'Generated (UTC)', 'hoa-coa-portal-pro' ) . ':</strong> ' . esc_html( $generated_u ) . '</div>';
        echo '</div>';

        echo '<hr style="border:0;border-top:1px solid #dcdcde;margin:14px 0;">';

        echo '<h3 style="margin:0 0 10px;font-size:15px;">' . esc_html__( 'Index', 'hoa-coa-portal-pro' ) . '</h3>';
        if ( empty( $terms ) ) {
            echo '<p class="hcp-binder-empty">' . esc_html__( 'No categories found yet.', 'hoa-coa-portal-pro' ) . '</p>';
        } else {
            echo '<ol class="hcp-binder-list">';
            foreach ( $terms as $t ) {
                echo '<li>' . esc_html( $t->name ) . '</li>';
            }
            echo '</ol>';
        }

        // Sections by category.
        foreach ( $terms as $t ) {
            echo '<div class="hcp-page-break"></div>';
            echo '<div class="hcp-binder-section">';
            echo '<h2>' . esc_html( $t->name ) . '</h2>';

            $q = new WP_Query(
                array(
                    'post_type'      => HCP_Compliance::CPT,
                    'posts_per_page' => 200,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'tax_query'      => array(
                        array(
                            'taxonomy' => HCP_Compliance::TAX,
                            'field'    => 'term_id',
                            'terms'    => (int) $t->term_id,
                        ),
                    ),
                )
            );

            if ( ! $q->have_posts() ) {
                echo '<p class="hcp-binder-empty">' . esc_html__( 'No documents uploaded in this section yet.', 'hoa-coa-portal-pro' ) . '</p>';
            } else {
                echo '<table class="widefat striped" style="width:100%;border-collapse:collapse">';
                echo '<thead><tr><th style="text-align:left;padding:8px 10px;">' . esc_html__( 'Document', 'hoa-coa-portal-pro' ) . '</th><th style="text-align:left;padding:8px 10px;width:180px;">' . esc_html__( 'Date', 'hoa-coa-portal-pro' ) . '</th></tr></thead><tbody>';
                while ( $q->have_posts() ) {
                    $q->the_post();
                    $doc_date = get_the_date();
                    echo '<tr>';
                    echo '<td style="padding:8px 10px;">' . esc_html( get_the_title() ) . '</td>';
                    echo '<td style="padding:8px 10px;">' . esc_html( $doc_date ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                wp_reset_postdata();
            }

            echo '</div>';
        }

        echo '<div class="hcp-page-break"></div>';
        echo '<h3 style="margin:0 0 8px;font-size:15px;">' . esc_html__( 'Notes / Certifications', 'hoa-coa-portal-pro' ) . '</h3>';
        echo '<table class="widefat" style="width:100%;border-collapse:collapse">';
        echo '<tbody>';
        echo '<tr><td style="padding:10px;width:220px;">' . esc_html__( 'Board Officer / Manager', 'hoa-coa-portal-pro' ) . '</td><td style="padding:10px;">______________________________</td><td style="padding:10px;width:140px;">______________</td></tr>';
        echo '<tr><td style="padding:10px;">' . esc_html__( 'Witness (Optional)', 'hoa-coa-portal-pro' ) . '</td><td style="padding:10px;">______________________________</td><td style="padding:10px;">______________</td></tr>';
        echo '</tbody></table>';
        echo '<p style="margin-top:10px;color:#646970;font-size:12px;">' . esc_html__( 'We certify that this binder reflects the records available in the association portal as of the generated date/time.', 'hoa-coa-portal-pro' ) . '</p>';

        echo '</div>'; // card
        echo '</div>'; // wrap
    }

}



/**
 * Admin page callback wrapper for Compliance Binder.
 * Prevents fatal errors if class method loading differs across environments.
 */
if ( ! function_exists( 'hcp_page_compliance_binder' ) ) {
    function hcp_page_compliance_binder(): void {
        if ( class_exists( 'HCP_Admin' ) && method_exists( 'HCP_Admin', 'page_compliance_binder' ) ) {
            HCP_Admin::page_compliance_binder();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Compliance Binder', 'hoa-coa-portal-pro' ) . '</h1>';
        echo '<p>' . esc_html__( 'The Compliance Binder page is unavailable. Please re-install the plugin to ensure all files are updated.', 'hoa-coa-portal-pro' ) . '</p></div>';
    }
}