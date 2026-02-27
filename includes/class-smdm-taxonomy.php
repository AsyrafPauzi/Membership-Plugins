<?php
class SMDM_Taxonomy {
    public function __construct() {
        add_action('init', [$this, 'register_member_taxonomy']);
    }

    public function register_member_taxonomy() {
        register_taxonomy('member_category', 'member', [
            'hierarchical' => true,
            'labels' => [
                'name' => 'Member Categories',
                'singular_name' => 'Category',
            ],
            'show_ui' => true,
            'show_admin_column' => false,
            'query_var' => true,
            'rewrite' => ['slug' => 'member-category'],
        ]);
    }
}