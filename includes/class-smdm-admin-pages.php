<?php
class SMDM_Admin_Pages {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus']);
    }

    public function register_menus() {
        add_menu_page('Member Manager', 'Member Manager', 'manage_options', 'smdm-app', [$this, 'render_app_shell'], 'dashicons-groups', 25);
    }

    public function render_app_shell() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        $member_id  = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
        
        // Logic to determine view
        if ($member_id > 0) { $active_tab = 'edit_member'; }

        $tabs = [
            'dashboard'  => ['label' => 'Dashboard', 'icon' => 'dashicons-dashboard'],
            'members'    => ['label' => 'Member List', 'icon' => 'dashicons-admin-users'],
            'categories' => ['label' => 'Categories', 'icon' => 'dashicons-category'],
            'blast'      => ['label' => 'Email Blast', 'icon' => 'dashicons-email-alt'],
            'import'     => ['label' => 'Import/Export', 'icon' => 'dashicons-database-export'],
            'settings'   => ['label' => 'SMTP Settings', 'icon' => 'dashicons-admin-settings'],
        ];

        $email_handler = new SMDM_Email_Handler();
        $ie_handler = new SMDM_Import_Export();
        ?>
        <div class="smdm-app-wrapper">
            <div class="smdm-sidebar">
                <div class="smdm-logo-section">
                    <span class="dashicons dashicons-groups"></span>
                    <h2>MEMBER MANAGER</h2>
                </div>
                <?php foreach($tabs as $slug => $data): ?>
                    <a href="?page=smdm-app&tab=<?php echo $slug; ?>" class="smdm-nav-item <?php echo ($active_tab == $slug || ($slug == 'members' && ($active_tab == 'edit_member' || $active_tab == 'add_member'))) ? 'active' : ''; ?>">
                        <span class="dashicons <?php echo $data['icon']; ?>"></span>
                        <?php echo $data['label']; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="smdm-main">
                <div class="smdm-header-bar">
                    <h1>
                        <?php 
                        if ($active_tab == 'edit_member') echo 'Edit Member';
                        elseif ($active_tab == 'add_member') echo 'Create New Member';
                        elseif ($active_tab == 'categories') echo 'Category Manager';
                        else echo $tabs[$active_tab]['label']; 
                        ?>
                    </h1>
                    <div class="smdm-user-info">Admin Account</div>
                </div>

                <div class="smdm-content-area">
                    <?php 
                    switch($active_tab) {
                        case 'dashboard':   $this->render_dashboard_content(); break;
                        case 'members':     $this->render_members_list(); break;
                        case 'categories':  $this->render_categories_manager(); break;
                        case 'add_member':  $this->render_custom_member_form(); break;
                        case 'edit_member': $this->render_custom_member_form($member_id); break; 
                        case 'blast':       $email_handler->render_blast_interface(); break;
                        case 'import':      $ie_handler->render_page_custom(); break;
                        case 'settings':    $email_handler->render_settings_form(); break;
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * NEW: CATEGORY MANAGER
     * Handles creation and deletion of member categories within the custom UI
     */
    private function render_categories_manager() {
        // Handle Deletion
        if (isset($_GET['delete_cat'])) {
            check_admin_referer('smdm_delete_cat');
            wp_delete_term(intval($_GET['delete_cat']), 'member_category');
            echo '<div class="smdm-alert success">Category deleted successfully.</div>';
        }

        // Handle Creation
        if (isset($_POST['add_new_cat'])) {
            check_admin_referer('smdm_add_cat', 'smdm_nonce');
            $cat_name = sanitize_text_field($_POST['cat_name']);
            if (!empty($cat_name)) {
                $result = wp_insert_term($cat_name, 'member_category');
                if (is_wp_error($result)) {
                    echo '<div class="smdm-alert" style="background:#fee2e2; color:#991b1b;">Error: ' . $result->get_error_message() . '</div>';
                } else {
                    echo '<div class="smdm-alert success">Category "' . $cat_name . '" created successfully!</div>';
                }
            }
        }

        $categories = get_terms(['taxonomy' => 'member_category', 'hide_empty' => false]);
        ?>
        <div class="smdm-stats-grid" style="grid-template-columns: 1fr 2fr;">
            <!-- Create Category Form -->
            <div class="smdm-content-card">
                <h3 class="smdm-form-section-title">Add New Category</h3>
                <form method="post">
                    <?php wp_nonce_field('smdm_add_cat', 'smdm_nonce'); ?>
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="cat_name" placeholder="e.g. VIP Members" required>
                    </div>
                    <div style="margin-top:20px;">
                        <button type="submit" name="add_new_cat" class="smdm-btn" style="width:100%;">Create Category</button>
                    </div>
                </form>
            </div>

            <!-- Category List Table -->
            <div class="smdm-content-card">
                <h3 class="smdm-form-section-title">Existing Categories</h3>
                <table class="smdm-custom-table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Count</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($categories): foreach($categories as $cat): ?>
                        <tr>
                            <td><strong><?php echo esc_html($cat->name); ?></strong></td>
                            <td><?php echo $cat->count; ?> Members</td>
                            <td style="text-align:right">
                                <?php 
                                $delete_url = wp_nonce_url("?page=smdm-app&tab=categories&delete_cat=".$cat->term_id, 'smdm_delete_cat');
                                ?>
                                <a href="<?php echo $delete_url; ?>" class="smdm-btn-small" style="color:#ef4444; border-color:#fee2e2;" onclick="return confirm('Are you sure? Members in this category will become uncategorized.')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="3" style="text-align:center; padding:30px;">No categories created yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Unified Form for Adding and Editing Members
     * Amended to include IC, Gender, DOB, and Malaysian Address
     */
    private function render_custom_member_form($id = 0) {
        if (isset($_POST['save_member_action'])) {
            check_admin_referer('smdm_member_form', 'smdm_nonce');
            
            $member_data = [
                'post_title'  => sanitize_text_field($_POST['m_name']),
                'post_type'   => 'member',
                'post_status' => 'publish',
            ];

            if ($id > 0) {
                $member_data['ID'] = $id;
                wp_update_post($member_data);
            } else {
                $id = wp_insert_post($member_data);
            }

            // Save Personal Meta
            update_post_meta($id, '_member_ic', sanitize_text_field($_POST['m_ic']));
            update_post_meta($id, '_member_gender', sanitize_text_field($_POST['m_gender']));
            update_post_meta($id, '_member_dob', sanitize_text_field($_POST['m_dob']));
            
            // Save Contact Meta
            update_post_meta($id, '_member_email', sanitize_email($_POST['m_email']));
            update_post_meta($id, '_member_phone', sanitize_text_field($_POST['m_phone']));
            
            // Save Address Meta
            update_post_meta($id, '_member_address', sanitize_textarea_field($_POST['m_address']));
            update_post_meta($id, '_member_postcode', sanitize_text_field($_POST['m_postcode']));
            update_post_meta($id, '_member_city', sanitize_text_field($_POST['m_city']));
            update_post_meta($id, '_member_state', sanitize_text_field($_POST['m_state']));
            
            // Save System Meta
            update_post_meta($id, '_member_status', sanitize_text_field($_POST['m_status']));
            
            if (isset($_POST['m_cat'])) {
                wp_set_object_terms($id, (int)$_POST['m_cat'], 'member_category');
            }

            echo '<div class="smdm-alert success">Member saved successfully! <a href="?page=smdm-app&tab=members">View List</a></div>';
        }

        $post = ($id > 0) ? get_post($id) : null;
        $ic = get_post_meta($id, '_member_ic', true);
        $gender = get_post_meta($id, '_member_gender', true);
        $dob = get_post_meta($id, '_member_dob', true);
        $email = get_post_meta($id, '_member_email', true);
        $phone = get_post_meta($id, '_member_phone', true);
        $address = get_post_meta($id, '_member_address', true);
        $postcode = get_post_meta($id, '_member_postcode', true);
        $city = get_post_meta($id, '_member_city', true);
        $state = get_post_meta($id, '_member_state', true);
        $status = ($id > 0) ? get_post_meta($id, '_member_status', true) : 'Active';
        
        $current_cat = ($id > 0) ? wp_get_object_terms($id, 'member_category', ['fields' => 'ids']) : [];
        $categories = get_terms(['taxonomy' => 'member_category', 'hide_empty' => false]);
        
        $msia_states = ["Johor", "Kedah", "Kelantan", "Melaka", "Negeri Sembilan", "Pahang", "Penang", "Perak", "Perlis", "Sabah", "Sarawak", "Selangor", "Terengganu", "W.P. Kuala Lumpur", "W.P. Labuan", "W.P. Putrajaya"];
        ?>
        <div class="smdm-content-card">
            <form method="post">
                <?php wp_nonce_field('smdm_member_form', 'smdm_nonce'); ?>
                
                <h3 class="smdm-form-section-title">Personal Information</h3>
                <div class="smdm-form-grid">
                    <div class="form-group smdm-span-2">
                        <label>Full Name</label>
                        <input type="text" name="m_name" value="<?php echo esc_attr($post ? $post->post_title : ''); ?>" placeholder="Enter member name" required>
                    </div>
                    <div class="form-group">
                        <label>IC / Passport Number</label>
                        <input type="text" name="m_ic" value="<?php echo esc_attr($ic); ?>" placeholder="e.g. 900101015522">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="m_gender">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php selected($gender, 'Male'); ?>>Male</option>
                            <option value="Female" <?php selected($gender, 'Female'); ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="m_dob" value="<?php echo esc_attr($dob); ?>">
                    </div>
                </div>

                <h3 class="smdm-form-section-title" style="margin-top:30px;">Contact Details</h3>
                <div class="smdm-form-grid">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="m_email" value="<?php echo esc_attr($email); ?>" placeholder="email@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="m_phone" value="<?php echo esc_attr($phone); ?>" placeholder="e.g. +6012345678">
                    </div>
                </div>

                <h3 class="smdm-form-section-title" style="margin-top:30px;">Address Information</h3>
                <div class="smdm-form-grid">
                    <div class="form-group smdm-span-2">
                        <label>Street Address</label>
                        <input type="text" name="m_address" value="<?php echo esc_attr($address); ?>" placeholder="Unit, Street Name, etc.">
                    </div>
                    <div class="form-group">
                        <label>Postcode</label>
                        <input type="text" name="m_postcode" value="<?php echo esc_attr($postcode); ?>" placeholder="e.g. 50450">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="m_city" value="<?php echo esc_attr($city); ?>" placeholder="e.g. Kuala Lumpur">
                    </div>
                    <div class="form-group smdm-span-2">
                        <label>State</label>
                        <select name="m_state">
                            <option value="">Select State</option>
                            <?php foreach($msia_states as $ms): ?>
                                <option value="<?php echo $ms; ?>" <?php selected($state, $ms); ?>><?php echo $ms; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h3 class="smdm-form-section-title" style="margin-top:30px;">System Status</h3>
                <div class="smdm-form-grid">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="m_status">
                            <option value="Active" <?php selected($status, 'Active'); ?>>Active</option>
                            <option value="Inactive" <?php selected($status, 'Inactive'); ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="m_cat">
                            <option value="">Select Category</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat->term_id; ?>" <?php selected(($current_cat[0] ?? 0), $cat->term_id); ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top:30px; border-top:1px solid #f1f5f9; padding-top:20px;">
                    <button type="submit" name="save_member_action" class="smdm-btn">Save Member Information</button>
                    <a href="?page=smdm-app&tab=members" class="smdm-btn secondary" style="text-decoration:none;">Cancel</a>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_members_list() {
        $members = get_posts(['post_type' => 'member', 'posts_per_page' => -1, 'post_status' => 'publish']);
        ?>
        <div class="smdm-list-actions">
            <a href="?page=smdm-app&tab=add_member" class="smdm-btn">+ Add New Member</a>
        </div>

        <div class="smdm-content-card">
            <table class="smdm-custom-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>State</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($members): foreach($members as $m): 
                        $email = get_post_meta($m->ID, '_member_email', true);
                        $status = get_post_meta($m->ID, '_member_status', true);
                        $state = get_post_meta($m->ID, '_member_state', true);
                    ?>
                    <tr>
                        <td><strong><?php echo get_the_title($m->ID); ?></strong></td>
                        <td><?php echo esc_html($email); ?></td>
                        <td><span class="smdm-cat-pill"><?php echo $state ? esc_html($state) : 'None'; ?></span></td>
                        <td><span class="smdm-status-badge <?php echo ($status == 'Active' ? 'active' : 'inactive'); ?>"><?php echo $status; ?></span></td>
                        <td style="text-align:right">
                            <a href="?page=smdm-app&member_id=<?php echo $m->ID; ?>" class="smdm-btn-small">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px;">No members found. Click the button above to add one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_dashboard_content() {
        global $wpdb;
        
        // 1. Basic Stats
        $total_members = wp_count_posts('member')->publish;
        $active_count  = (new WP_Query(['post_type'=>'member','meta_key'=>'_member_status','meta_value'=>'Active']))->found_posts;
        $inactive_count = $total_members - $active_count;
        $total_cats    = wp_count_terms('member_category');
        $logs_table    = $wpdb->prefix . 'member_email_logs';
        $logs          = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");

        // 2. Data for Growth Chart (Last 6 Months)
        $growth_data = [];
        $growth_labels = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'member' AND post_status = 'publish' AND post_date LIKE %s",
                $month . '%'
            ));
            $growth_labels[] = date('M Y', strtotime("-$i months"));
            $growth_data[] = (int)$count;
        }

        // 3. Data for Category Chart
        $categories = get_terms(['taxonomy' => 'member_category', 'hide_empty' => false]);
        $cat_labels = [];
        $cat_counts = [];
        foreach($categories as $cat) {
            $cat_labels[] = $cat->name;
            $cat_counts[] = $cat->count;
        }

        // Load Chart.js from CDN
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        ?>
        
        <!-- Stats Grid -->
        <div class="smdm-stats-grid">
            <div class="smdm-stat-card"><h4>Total Database</h4><div class="value"><?php echo $total_members; ?></div></div>
            <div class="smdm-stat-card"><h4>Active Members</h4><div class="value"><?php echo $active_count; ?></div></div>
            <div class="smdm-stat-card"><h4>Categories</h4><div class="value"><?php echo $total_cats; ?></div></div>
            <div class="smdm-stat-card"><h4>Email Blasts</h4><div class="value"><?php echo $logs ? $logs : 0; ?></div></div>
        </div>

        <!-- Charts Section -->
        <div class="smdm-form-grid" style="margin-bottom: 30px;">
            <div class="smdm-content-card">
                <h3 class="smdm-form-section-title">Registration Growth</h3>
                <canvas id="growthChart" height="200"></canvas>
            </div>
            <div class="smdm-content-card">
                <h3 class="smdm-form-section-title">Member Composition</h3>
                <div style="max-width: 250px; margin: 0 auto;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <div class="smdm-content-card">
            <h3 class="smdm-form-section-title">Quick Actions</h3>
            <div style="display:flex; gap:10px; margin-top:15px;">
                <a href="?page=smdm-app&tab=add_member" class="smdm-btn" style="text-decoration:none;">Add Member</a>
                <a href="?page=smdm-app&tab=blast" class="smdm-btn secondary" style="text-decoration:none;">Send Email Blast</a>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Growth Chart (Line)
            new Chart(document.getElementById('growthChart'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($growth_labels); ?>,
                    datasets: [{
                        label: 'New Members',
                        data: <?php echo json_encode($growth_data); ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });

            // Status Chart (Doughnut)
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Inactive'],
                    datasets: [{
                        data: [<?php echo $active_count; ?>, <?php echo $inactive_count; ?>],
                        backgroundColor: ['#10b981', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '70%',
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        });
        </script>
        <?php
    }
}