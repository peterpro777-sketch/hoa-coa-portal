<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Compliance Documents (Freemium foundation).
 *
 * Free: basic secure document library.
 * Premium: Florida statute categories, deadline tracking, compliance dashboard (future).
 */
final class HCP_Compliance {

    public const CPT  = 'hcp_compliance_doc';
    public const TAX  = 'hcp_compliance_category';

    public static function hooks(): void {
        add_action( 'init', array( __CLASS__, 'register' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
        add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
        add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_meta' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );

        add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
        add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'handle_sorting' ) );
    }

    public static function register(): void {
        $labels = array(
            'name'               => __( 'Compliance Documents', 'hoa-coa-portal-pro' ),
            'singular_name'      => __( 'Compliance Document', 'hoa-coa-portal-pro' ),
            'add_new'            => __( 'Add New', 'hoa-coa-portal-pro' ),
            'add_new_item'       => __( 'Add Compliance Document', 'hoa-coa-portal-pro' ),
            'edit_item'          => __( 'Edit Compliance Document', 'hoa-coa-portal-pro' ),
            'new_item'           => __( 'New Compliance Document', 'hoa-coa-portal-pro' ),
            'view_item'          => __( 'View Compliance Document', 'hoa-coa-portal-pro' ),
            'search_items'       => __( 'Search Compliance Documents', 'hoa-coa-portal-pro' ),
            'not_found'          => __( 'No compliance documents found.', 'hoa-coa-portal-pro' ),
            'not_found_in_trash' => __( 'No compliance documents found in Trash.', 'hoa-coa-portal-pro' ),
            'menu_name'          => __( 'Compliance Docs', 'hoa-coa-portal-pro' ),
        );

        register_post_type(
            self::CPT,
            array(
                'labels'              => $labels,
                'public'              => false,
                'publicly_queryable'   => true,
                'show_ui'             => true,
                'show_in_menu'        => false, // added under our plugin menu.
                'show_in_rest'        => false,
                'supports'            => array( 'title', 'editor', 'author' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'has_archive'         => false,
                'rewrite'             => array( 'slug' => 'hcp-doc' ),
            )
        );

        register_taxonomy(
            self::TAX,
            self::CPT,
            array(
                'label'             => __( 'Compliance Category', 'hoa-coa-portal-pro' ),
                'public'            => false,
                'show_ui'           => true,
                'show_in_rest'      => false,
                'hierarchical'      => true,
                'rewrite'           => false,
                'capabilities'      => array(
                    'manage_terms' => 'do_not_allow',
                    'edit_terms'   => 'do_not_allow',
                    'delete_terms' => 'do_not_allow',
                    'assign_terms' => 'edit_posts',
                ),
            )
        );
    }

    /**
     * Seed categories on activation (called from plugin activate).
     */
    public static function seed_terms(): void {
        $terms = array(
            'governing-declaration'   => __( 'Declaration / Covenants (Recorded)', 'hoa-coa-portal-pro' ),
            'governing-bylaws'        => __( 'Bylaws (Recorded)', 'hoa-coa-portal-pro' ),
            'governing-articles'      => __( 'Articles of Incorporation', 'hoa-coa-portal-pro' ),
            'rules-regulations'       => __( 'Rules & Regulations', 'hoa-coa-portal-pro' ),
            'minutes-board-12mo'      => __( 'Approved Minutes (Past 12 Months)', 'hoa-coa-portal-pro' ),
            'meeting-recordings-12mo' => __( 'Meeting Recordings (Past 12 Months)', 'hoa-coa-portal-pro' ),
            'active-contracts'        => __( 'Active Contracts List', 'hoa-coa-portal-pro' ),
            'bid-proposals-500'       => __( 'Bid Proposals / Summaries (Over Threshold)', 'hoa-coa-portal-pro' ),
            'annual-budget'           => __( 'Annual Budget (Current/Proposed)', 'hoa-coa-portal-pro' ),
            'financial-reports'       => __( 'Financial Reports (Current/Proposed)', 'hoa-coa-portal-pro' ),
            'director-certifications' => __( 'Director Certifications', 'hoa-coa-portal-pro' ),
            'conflicts-of-interest'   => __( 'Conflicts of Interest', 'hoa-coa-portal-pro' ),
            'meeting-notices-agendas' => __( 'Meeting Notices & Agendas', 'hoa-coa-portal-pro' ),
            'inspection-reports'      => __( 'Structural / Life-Safety Inspection Reports', 'hoa-coa-portal-pro' ),
            'sirs'                    => __( 'Structural Integrity Reserve Study (SIRS)', 'hoa-coa-portal-pro' ),
            'building-permits'        => __( 'Building Permits (Planned/Ongoing Work)', 'hoa-coa-portal-pro' ),
            'statutory-affidavits'    => __( 'Statutory Affidavits', 'hoa-coa-portal-pro' ),
            'investment-policy'       => __( 'Investment Policy & Related Documents', 'hoa-coa-portal-pro' ),
            'insurance-policies'      => __( 'Insurance Policies', 'hoa-coa-portal-pro' ),
        );
        foreach ( $terms as $slug => $name ) {
            if ( ! term_exists( $slug, self::TAX ) ) {
                wp_insert_term( $name, self::TAX, array( 'slug' => $slug ) );
            }
        }
    }

    public static function meta_boxes(): void {
        add_meta_box(
            'hcp_compliance_meta',
            __( 'Compliance Details', 'hoa-coa-portal-pro' ),
            array( __CLASS__, 'render_meta_box' ),
            self::CPT,
            'side',
            'default'
        );
    }

    public static function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'hcp_compliance_save', 'hcp_compliance_nonce' );

        $received = (string) get_post_meta( $post->ID, '_hcp_received_date', true );
        $redacted = (string) get_post_meta( $post->ID, '_hcp_redacted_confirmed', true );

        echo '<p><label for="hcp_received_date"><strong>' . esc_html__( 'Date received/created', 'hoa-coa-portal-pro' ) . '</strong></label></p>';
        echo '<input type="date" id="hcp_received_date" name="hcp_received_date" value="' . esc_attr( $received ) . '" style="width:100%;" />';

        echo '<p style="margin-top:12px;">';
        echo '<label><input type="checkbox" name="hcp_redacted_confirmed" value="yes" ' . checked( $redacted, 'yes', false ) . ' /> ';
        echo esc_html__( 'I confirmed sensitive info is redacted', 'hoa-coa-portal-pro' );
        echo '</label></p>';

        $visibility = (string) get_post_meta( $post->ID, '_hcp_visibility', true );
        if ( '' === $visibility ) { $visibility = 'owners'; }
        $file_id = absint( get_post_meta( $post->ID, '_hcp_file_id', true ) );

        echo '<hr style="margin:16px 0;" />';

        echo '<p><label for="hcp_visibility"><strong>' . esc_html__( 'Visibility', 'hoa-coa-portal-pro' ) . '</strong></label></p>';
        echo '<select id="hcp_visibility" name="hcp_visibility" style="width:100%;">';
        $opts = array(
            'owners' => __( 'Owners & authorized staff', 'hoa-coa-portal-pro' ),
            'staff'  => __( 'Staff only', 'hoa-coa-portal-pro' ),
            'board'  => __( 'Board/management only', 'hoa-coa-portal-pro' ),
        );
        foreach ( $opts as $k => $label ) {
            echo '<option value="' . esc_attr( $k ) . '" ' . selected( $visibility, $k, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description" style="margin-top:6px;">' . esc_html__( 'Choose who can see and download this document in the owner portal. If unsure, keep the default.', 'hoa-coa-portal-pro' ) . '</p>';

        echo '<p style="margin-top:14px;"><strong>' . esc_html__( 'File attachment', 'hoa-coa-portal-pro' ) . '</strong></p>';
        echo '<input type="hidden" id="hcp_file_id" name="hcp_file_id" value="' . esc_attr( (string) $file_id ) . '" />';
        echo '<div id="hcp-file-preview" style="margin:6px 0 10px 0;">';
        if ( $file_id > 0 ) {
            $url = wp_get_attachment_url( $file_id );
            $name = get_the_title( $file_id );
            if ( $url ) {
                echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ? $name : __( 'View file', 'hoa-coa-portal-pro' ) ) . '</a>';
            }
        } else {
            echo '<em>' . esc_html__( 'No file selected yet.', 'hoa-coa-portal-pro' ) . '</em>';
        }
        echo '</div>';
        echo '<p>';
        echo '<button type="button" class="button" id="hcp-file-select">' . esc_html__( 'Select / Upload File', 'hoa-coa-portal-pro' ) . '</button> ';
        echo '<button type="button" class="button button-link-delete" id="hcp-file-clear" style="margin-left:6px;">' . esc_html__( 'Remove', 'hoa-coa-portal-pro' ) . '</button>';
        echo '</p>';
        echo '<p class="description">' . esc_html__( 'Tip: Upload a redacted PDF whenever possible. This keeps owner access simple and consistent.', 'hoa-coa-portal-pro' ) . '</p>';

        echo '<p class="description">' . esc_html__( 'Tip: Upload PDFs only, and redact personal data before posting.', 'hoa-coa-portal-pro' ) . '</p>';
    }

    public static function save_meta( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( ! isset( $_POST['hcp_compliance_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hcp_compliance_nonce'] ) ), 'hcp_compliance_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

        $received = '';
        if ( isset( $_POST['hcp_received_date'] ) ) {
            $received = sanitize_text_field( wp_unslash( $_POST['hcp_received_date'] ) );
            // Basic YYYY-MM-DD check.
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $received ) ) {
                $received = '';
            }
        }
        update_post_meta( $post_id, '_hcp_received_date', $received );

        $redacted = ( isset( $_POST['hcp_redacted_confirmed'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['hcp_redacted_confirmed'] ) ) ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_hcp_redacted_confirmed', $redacted );

        // Non-blocking reminder: if redaction is not confirmed, show an admin warning after save.
        if ( 'no' === $redacted ) {
            set_transient( 'hcp_redaction_warn_' . get_current_user_id() . '_' . $post_id, 1, 60 );
        }


        $visibility = 'owners';
        if ( isset( $_POST['hcp_visibility'] ) ) {
            $vis = sanitize_key( (string) wp_unslash( $_POST['hcp_visibility'] ) );
            if ( in_array( $vis, array( 'owners', 'staff', 'board' ), true ) ) {
                $visibility = $vis;
            }
        }
        update_post_meta( $post_id, '_hcp_visibility', $visibility );

        $file_id = 0;
        if ( isset( $_POST['hcp_file_id'] ) ) {
            $file_id = absint( wp_unslash( $_POST['hcp_file_id'] ) );
        }
        update_post_meta( $post_id, '_hcp_file_id', $file_id );
    }

    
    public static function admin_assets( string $hook ): void {
        // Only load on Compliance Document edit screens.
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::CPT !== $screen->post_type ) {
            return;
        }
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'hcp-compliance-admin',
            HCP_PLUGIN_URL . 'assets/js/hcp-compliance-admin.js',
            array( 'jquery' ),
            HCP_VERSION,
            true
        );
    }

public static function columns( array $cols ): array {
        $new = array();
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( 'title' === $k ) {
                $new['hcp_category'] = __( 'Category', 'hoa-coa-portal-pro' );
                $new['hcp_received'] = __( 'Received', 'hoa-coa-portal-pro' );
                $new['hcp_redacted'] = __( 'Redacted', 'hoa-coa-portal-pro' );
            }
        }
        return $new;
    }

    public static function column_content( string $col, int $post_id ): void {
        if ( 'hcp_category' === $col ) {
            $terms = get_the_terms( $post_id, self::TAX );
            if ( is_array( $terms ) && ! empty( $terms ) ) {
                echo esc_html( $terms[0]->name );
            } else {
                echo '<span class="description">' . esc_html__( 'Uncategorized', 'hoa-coa-portal-pro' ) . '</span>';
            }
        } elseif ( 'hcp_received' === $col ) {
            $received = (string) get_post_meta( $post_id, '_hcp_received_date', true );
            echo $received ? esc_html( $received ) : '<span class="description">—</span>';
        } elseif ( 'hcp_redacted' === $col ) {
            $redacted = (string) get_post_meta( $post_id, '_hcp_redacted_confirmed', true );
            echo ( 'yes' === $redacted ) ? '✅' : '—';
        }
    }

    public static function sortable_columns( array $cols ): array {
        $cols['hcp_received'] = 'hcp_received';
        return $cols;
    }

    public static function handle_sorting( \WP_Query $q ): void {
        if ( ! is_admin() || ! $q->is_main_query() ) { return; }
        if ( self::CPT !== $q->get( 'post_type' ) ) { return; }
        if ( 'hcp_received' === $q->get( 'orderby' ) ) {
            $q->set( 'meta_key', '_hcp_received_date' );
            $q->set( 'orderby', 'meta_value' );
        }
    }

    /**
     * Required category slugs (checklist) by association type.
     * Note: This is an organizational checklist and is not legal advice.
     *
     * @param string $assoc_type 'condo' or 'hoa'
     * @return string[]
     */
    public static function required_category_slugs( string $assoc_type ): array {
        $assoc_type = ( 'hoa' === $assoc_type ) ? 'hoa' : 'condo';

        // Condo (Ch. 718) list is more detailed; HOA (Ch. 720) is a practical subset.
        $condo = array(
            'governing-declaration',
            'governing-bylaws',
            'governing-articles',
            'rules-regulations',
            'minutes-board-12mo',
            'meeting-recordings-12mo',
            'active-contracts',
            'bid-proposals-500',
            'annual-budget',
            'financial-reports',
            'director-certifications',
            'conflicts-of-interest',
            'meeting-notices-agendas',
            'inspection-reports',
            'sirs',
            'building-permits',
            'statutory-affidavits',
            'investment-policy',
        );

        $hoa = array(
            'governing-declaration',
            'governing-bylaws',
            'governing-articles',
            'rules-regulations',
            'annual-budget',
            'financial-reports',
            'active-contracts',
            'insurance-policies',
            'meeting-notices-agendas',
            'minutes-board-12mo',
            'bid-proposals-500',
        );

        return ( 'hoa' === $assoc_type ) ? $hoa : $condo;
    }

    /**
     * Label map for known slugs.
     *
     * @param string $slug
     * @return string
     */
    public static function category_label_for_slug( string $slug ): string {
        $map = array(
            'governing-declaration'     => __( 'Declaration / Covenants (Recorded)', 'hoa-coa-portal-pro' ),
            'governing-bylaws'          => __( 'Bylaws (Recorded)', 'hoa-coa-portal-pro' ),
            'governing-articles'        => __( 'Articles of Incorporation', 'hoa-coa-portal-pro' ),
            'rules-regulations'         => __( 'Rules & Regulations', 'hoa-coa-portal-pro' ),
            'minutes-board-12mo'        => __( 'Approved Minutes (Past 12 Months)', 'hoa-coa-portal-pro' ),
            'meeting-recordings-12mo'   => __( 'Meeting Recordings (Past 12 Months)', 'hoa-coa-portal-pro' ),
            'active-contracts'          => __( 'Active Contracts List', 'hoa-coa-portal-pro' ),
            'bid-proposals-500'         => __( 'Bid Proposals / Summaries (Over Threshold)', 'hoa-coa-portal-pro' ),
            'annual-budget'             => __( 'Annual Budget (Current/Proposed)', 'hoa-coa-portal-pro' ),
            'financial-reports'         => __( 'Financial Reports (Current/Proposed)', 'hoa-coa-portal-pro' ),
            'director-certifications'   => __( 'Director Certifications', 'hoa-coa-portal-pro' ),
            'conflicts-of-interest'     => __( 'Conflicts of Interest', 'hoa-coa-portal-pro' ),
            'meeting-notices-agendas'   => __( 'Meeting Notices & Agendas', 'hoa-coa-portal-pro' ),
            'inspection-reports'        => __( 'Structural / Life-Safety Inspection Reports', 'hoa-coa-portal-pro' ),
            'sirs'                      => __( 'Structural Integrity Reserve Study (SIRS)', 'hoa-coa-portal-pro' ),
            'building-permits'          => __( 'Building Permits (Planned/Ongoing Work)', 'hoa-coa-portal-pro' ),
            'statutory-affidavits'      => __( 'Statutory Affidavits', 'hoa-coa-portal-pro' ),
            'investment-policy'         => __( 'Investment Policy & Related Documents', 'hoa-coa-portal-pro' ),
            'insurance-policies'        => __( 'Insurance Policies', 'hoa-coa-portal-pro' ),
        );

        return isset( $map[ $slug ] ) ? $map[ $slug ] : $slug;
    }

    /**
     * Returns counts of published/private compliance docs by category slug.
     *
     * @return array<string,int>
     */
    public static function category_doc_counts(): array {
        $out = array();

        $terms = get_terms( array(
            'taxonomy'   => self::TAX,
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return $out;
        }

        foreach ( $terms as $t ) {
            if ( ! $t instanceof \WP_Term ) { continue; }
            $out[ (string) $t->slug ] = (int) $t->count;
        }

        return $out;
    }


    public static function admin_notices(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && isset( $screen->post_type ) && self::CPT !== (string) $screen->post_type ) {
            // Only show on compliance editing screens.
            return;
        }

        // Detect most recent post id from request context.
        $post_id = 0;
        if ( isset( $_GET['post'] ) ) {
            $post_id = absint( $_GET['post'] );
        } elseif ( isset( $_POST['post_ID'] ) ) {
            $post_id = absint( $_POST['post_ID'] );
        }

        if ( $post_id <= 0 ) {
            return;
        }

        $key = 'hcp_redaction_warn_' . get_current_user_id() . '_' . $post_id;
        if ( ! get_transient( $key ) ) {
            return;
        }

        delete_transient( $key );

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__( 'Redaction reminder:', 'hoa-coa-portal-pro' ) . '</strong> ' . esc_html__( 'You indicated this document may include personal or protected information. Please confirm redaction before publishing to owners, if required.', 'hoa-coa-portal-pro' ) . '</p>';
        echo '</div>';
    }

}
