<?php
class SMDM_Post_Type {
    public function __construct() {
        add_action('init', [$this, 'register_member_cpt']);
        add_filter('manage_member_posts_columns', [$this, 'add_columns']);
        add_action('manage_member_posts_custom_column', [$this, 'fill_columns'], 10, 2);
        add_filter('manage_edit-member_sortable_columns', [$this, 'sortable_columns']);
        add_action('restrict_manage_posts', [$this, 'add_admin_filters']);
    }

    public function register_member_cpt() {
        $labels = [
            'name' => 'Members',
            'singular_name' => 'Member',
            'add_new' => 'Add New Member',
            'add_new_item' => 'Add New Member',
            'edit_item' => 'Edit Member',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'capabilities' => ['create_posts' => 'do_not_allow'],
            'map_meta_cap' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => true,
            'show_in_menu' => false,
            'supports' => ['title'], // Title is Name
            'menu_icon' => 'dashicons-groups',
        ];
        
        // Allow administrator to create members
        if (current_user_can('administrator')) {
            $args['capabilities'] = ['create_posts' => 'edit_posts'];
        }

        register_post_type('member', $args);
    }

    public function add_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = 'Name';
        $new_columns['email'] = 'Email';
        $new_columns['phone'] = 'Phone';
        $new_columns['member_category'] = 'Category';
        $new_columns['status'] = 'Status';
        $new_columns['date'] = 'Registered';
        return $new_columns;
    }

    public function fill_columns($column, $post_id) {
        switch ($column) {
            case 'email':
                $eid = class_exists( 'SMDM_Field_Schema' ) ? SMDM_Field_Schema::get_primary_email_field_id() : 'email';
                $em  = class_exists( 'SMDM_Field_Schema' ) ? SMDM_Field_Schema::get_member_value( $post_id, array( 'id' => $eid ) ) : get_post_meta( $post_id, '_member_email', true );
                echo esc_html( $em );
                break;
            case 'phone':
                echo esc_html(get_post_meta($post_id, '_member_phone', true));
                break;
            case 'status':
                $status = class_exists( 'SMDM_Field_Schema' ) ? SMDM_Field_Schema::get_member_value( $post_id, array( 'id' => 'account_status' ) ) : get_post_meta( $post_id, '_member_status', true );
                if ( '' === $status ) {
                    $status = get_post_meta( $post_id, '_member_status', true );
                }
                $is_active = class_exists( 'SMDM_Field_Schema' ) ? SMDM_Field_Schema::member_is_active( $post_id ) : ( 'Active' === $status || 'Aktif' === $status );
                $class = $is_active ? 'smdm-status-active' : 'smdm-status-inactive';
                echo '<span class="smdm-status-badge ' . esc_attr( $class ) . '">' . esc_html( $status ) . '</span>';
                break;
            case 'member_category':
                echo get_the_term_list($post_id, 'member_category', '', ', ');
                break;
        }
    }

    public function sortable_columns($columns) {
        $columns['email'] = 'email';
        $columns['status'] = 'status';
        return $columns;
    }

    public function add_admin_filters() {
        global $typenow;
        if ($typenow == 'member') {
            // Status Filter
            $current_status = isset($_GET['member_status']) ? sanitize_text_field( wp_unslash( $_GET['member_status'] ) ) : '';
            $a = class_exists( 'SMDM_Field_Schema' ) ? SMDM_Field_Schema::get_active_status_literal() : 'Aktif';
            $ia = class_exists( 'SMDM_Field_Schema' ) ? SMDM_Field_Schema::get_inactive_status_literal() : 'Tidak aktif';
            ?>
            <select name="member_status">
                <option value=""><?php esc_html_e( 'All Statuses', 'smdm' ); ?></option>
                <option value="<?php echo esc_attr( $a ); ?>" <?php selected( in_array( $current_status, array( $a, 'Active' ), true ) ); ?>><?php echo esc_html( $a ); ?></option>
                <option value="<?php echo esc_attr( $ia ); ?>" <?php selected( in_array( $current_status, array( $ia, 'Inactive' ), true ) ); ?>><?php echo esc_html( $ia ); ?></option>
            </select>
            <?php
            // Taxonomy Filter
            wp_dropdown_categories([
                'show_option_all' => 'All Categories',
                'taxonomy'        => 'member_category',
                'name'            => 'member_category',
                'orderby'         => 'name',
                'selected'        => isset($_GET['member_category']) ? $_GET['member_category'] : '',
                'hierarchical'    => true,
                'depth'           => 3,
                'show_count'      => true,
                'hide_empty'      => false,
                'value_field'     => 'slug'
            ]);
        }
    }
}