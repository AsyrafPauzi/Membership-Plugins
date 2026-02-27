<?php
class SMDM_Frontend {
    public function __construct() {
        add_shortcode('member_directory', [$this, 'render_directory']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style('smdm-frontend-style', SMDM_PLUGIN_URL . 'assets/css/frontend-style.css', array(), SMDM_VERSION);
        wp_enqueue_script('smdm-frontend-js', SMDM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SMDM_VERSION, true);
        wp_enqueue_style('dashicons');
    }

    public function render_directory($atts) {
        $search = isset($_GET['m_search']) ? sanitize_text_field($_GET['m_search']) : '';
        $cat    = isset($_GET['m_cat']) ? sanitize_text_field($_GET['m_cat']) : '';
        $state  = isset($_GET['m_state']) ? sanitize_text_field($_GET['m_state']) : '';
        $city   = isset($_GET['m_city']) ? sanitize_text_field($_GET['m_city']) : '';

        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        
        $meta_query = [['key' => '_member_status', 'value' => 'Active']];
        if (!empty($state)) { $meta_query[] = ['key' => '_member_state', 'value' => $state]; }
        if (!empty($city)) { $meta_query[] = ['key' => '_member_city', 'value' => $city, 'compare' => 'LIKE']; }

        $args = [
            'post_type'      => 'member',
            'posts_per_page' => 12,
            'paged'          => $paged,
            's'              => $search,
            'meta_query'     => $meta_query
        ];

        if (!empty($cat)) {
            $args['tax_query'] = [['taxonomy' => 'member_category', 'field' => 'slug', 'terms' => $cat]];
        }

        $query = new WP_Query($args);
        $msia_states = ["Johor", "Kedah", "Kelantan", "Melaka", "Negeri Sembilan", "Pahang", "Penang", "Perak", "Perlis", "Sabah", "Sarawak", "Selangor", "Terengganu", "W.P. Kuala Lumpur", "W.P. Labuan", "W.P. Putrajaya"];

        ob_start();
        ?>
        <div class="smdm-directory-wrapper">
            
            <div class="smdm-directory-header">
                <form method="get" class="smdm-modern-filter">
                    <!-- Search Input -->
                    <div class="smdm-input-group">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" name="m_search" placeholder="Search name..." value="<?php echo esc_attr($search); ?>">
                    </div>

                    <!-- Category Filter -->
                    <div class="smdm-input-group">
                        <span class="dashicons dashicons-category"></span>
                        <select name="m_cat">
                            <option value="">All Categories</option>
                            <?php
                            $categories = get_terms(['taxonomy' => 'member_category', 'hide_empty' => true]);
                            foreach ($categories as $c) {
                                printf('<option value="%s" %s>%s</option>', esc_attr($c->slug), selected($cat, $c->slug, false), esc_html($c->name));
                            }
                            ?>
                        </select>
                    </div>

                    <!-- State Filter -->
                    <div class="smdm-input-group">
                        <span class="dashicons dashicons-location"></span>
                        <select name="m_state">
                            <option value="">All States</option>
                            <?php foreach($msia_states as $ms): ?>
                                <option value="<?php echo $ms; ?>" <?php selected($state, $ms); ?>><?php echo $ms; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- City Input -->
                    <div class="smdm-input-group">
                        <span class="dashicons dashicons-admin-site"></span>
                        <input type="text" name="m_city" placeholder="City..." value="<?php echo esc_attr($city); ?>">
                    </div>
                    
                    <button type="submit" class="smdm-btn-primary">Filter</button>
                </form>
            </div>

            <div class="smdm-modern-grid">
                <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); 
    $id = get_the_ID();
    
    // Fetch all metadata
    $m_email   = get_post_meta($id, '_member_email', true);
    $m_phone   = get_post_meta($id, '_member_phone', true);
    $m_city    = get_post_meta($id, '_member_city', true);
    $m_state   = get_post_meta($id, '_member_state', true);
    $m_gender  = get_post_meta($id, '_member_gender', true);
    $m_address = get_post_meta($id, '_member_address', true);
    $m_postcode= get_post_meta($id, '_member_postcode', true);
    
    // Get Categories
    $terms = get_the_terms($id, 'member_category');
    $category_name = ($terms && !is_wp_error($terms)) ? $terms[0]->name : 'General';

    // Prepare JSON for the Popup Modal
    $m_json = json_encode([
        'name'    => get_the_title(),
        'email'   => $m_email,
        'phone'   => $m_phone,
        'city'    => $m_city,
        'state'   => $m_state,
        'cat'     => $category_name,
        'gender'  => $m_gender,
        'address' => $m_address,
        'postcode'=> $m_postcode,
    ]);
?>
    <div class="smdm-profile-card">
        <div class="smdm-card-header">
            <div class="smdm-avatar-circle"><?php echo strtoupper(substr(get_the_title(), 0, 1)); ?></div>
            <div class="smdm-status-dot"></div>
        </div>
        
        <div class="smdm-card-body">
            <span class="smdm-tag"><?php echo esc_html($category_name); ?></span>
            <h3><?php the_title(); ?></h3>
            
            <div class="smdm-card-info-list">
                <?php if($m_city || $m_state): ?>
                    <p class="smdm-info-line">
                        <span class="dashicons dashicons-location"></span> 
                        <?php echo esc_html($m_city); ?><?php echo ($m_city && $m_state) ? ', ' : ''; ?><?php echo esc_html($m_state); ?>
                    </p>
                <?php endif; ?>

                <?php if($m_email): ?>
                    <p class="smdm-info-line">
                        <span class="dashicons dashicons-email"></span> 
                        <?php echo esc_html($m_email); ?>
                    </p>
                <?php endif; ?>

                <?php if($m_phone): ?>
                    <p class="smdm-info-line">
                        <span class="dashicons dashicons-phone"></span> 
                        <?php echo esc_html($m_phone); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="smdm-card-footer">
            <button class="smdm-view-details-btn" data-member='<?php echo esc_attr($m_json); ?>'>
                View Full Profile
            </button>
        </div>
    </div>
<?php endwhile; wp_reset_postdata(); ?>
                    <p class="smdm-no-results">No active members found matching those filters.</p>
                <?php endif; ?>
            </div>

            <!-- Modal Structure (Matches your Modal CSS) -->
            <div id="smdm-modal" class="smdm-modal">
                <div class="smdm-modal-content">
                    <span class="smdm-close">&times;</span>
                    <div id="smdm-modal-body"></div>
                </div>
            </div>

            <div class="smdm-modern-pagination">
                <?php echo paginate_links(['total' => $query->max_num_pages, 'current' => $paged]); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}