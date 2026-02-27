<?php
class SMDM_Import_Export {

    public function render_page_custom() {
        if (isset($_POST['smdm_import_csv'])) { $this->handle_import(); }
        if (isset($_POST['smdm_export_csv'])) { $this->handle_export(); }
        ?>
        <div class="smdm-stats-grid" style="grid-template-columns: 1fr 1fr;">
            <!-- EXPORT CARD -->
            <div class="smdm-content-card">
                <h3 class="smdm-form-section-title">Export Database</h3>
                <p style="color:#64748b; margin-bottom:20px;">Download your member list with all Malaysian details (IC, State, etc.) to a CSV file.</p>
                <form method="post">
                    <?php wp_nonce_field('smdm_ie_action', 'smdm_ie_nonce'); ?>
                    <button type="submit" name="smdm_export_csv" class="smdm-btn" style="background:#64748b;">Download CSV Export</button>
                </form>
            </div>

            <!-- IMPORT CARD -->
            <div class="smdm-content-card">
                <h3 class="smdm-form-section-title">Import Members</h3>
                <p style="color:#64748b; margin-bottom:20px;">Upload a CSV file. <br><strong>Format:</strong> name, ic, gender, dob, email, phone, address, postcode, city, state, category, status</p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('smdm_ie_action', 'smdm_ie_nonce'); ?>
                    <input type="file" name="csv_file" accept=".csv" required style="margin-bottom:20px; display:block;">
                    <button type="submit" name="smdm_import_csv" class="smdm-btn">Upload & Import Members</button>
                </form>
            </div>
        </div>
        <?php
    }

    private function handle_export() {
        check_admin_referer('smdm_ie_action', 'smdm_ie_nonce');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="members_full_export_'.date('Y-m-d').'.csv"');
        
        $output = fopen('php://output', 'w');
        // Updated Header Row
        fputcsv($output, ['Name', 'IC', 'Gender', 'DOB', 'Email', 'Phone', 'Address', 'Postcode', 'City', 'State', 'Category', 'Status']);

        $members = get_posts(['post_type' => 'member', 'posts_per_page' => -1]);
        foreach($members as $m) {
            $cats = wp_get_post_terms($m->ID, 'member_category', ['fields' => 'names']);
            fputcsv($output, [
                $m->post_title,
                get_post_meta($m->ID, '_member_ic', true),
                get_post_meta($m->ID, '_member_gender', true),
                get_post_meta($m->ID, '_member_dob', true),
                get_post_meta($m->ID, '_member_email', true),
                get_post_meta($m->ID, '_member_phone', true),
                get_post_meta($m->ID, '_member_address', true),
                get_post_meta($m->ID, '_member_postcode', true),
                get_post_meta($m->ID, '_member_city', true),
                get_post_meta($m->ID, '_member_state', true),
                implode('|', $cats),
                get_post_meta($m->ID, '_member_status', true),
            ]);
        }
        fclose($output);
        exit;
    }

    private function handle_import() {
        check_admin_referer('smdm_ie_action', 'smdm_ie_nonce');
        
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($handle); // Skip header row
            
            $count = 0;
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Mapping: 0:name, 1:ic, 2:gender, 3:dob, 4:email, 5:phone, 6:address, 7:postcode, 8:city, 9:state, 10:category, 11:status
                $post_id = wp_insert_post([
                    'post_title'  => sanitize_text_field($data[0]),
                    'post_type'   => 'member',
                    'post_status' => 'publish'
                ]);

                if ($post_id) {
                    update_post_meta($post_id, '_member_ic', sanitize_text_field($data[1]));
                    update_post_meta($post_id, '_member_gender', sanitize_text_field($data[2]));
                    update_post_meta($post_id, '_member_dob', sanitize_text_field($data[3]));
                    update_post_meta($post_id, '_member_email', sanitize_email($data[4]));
                    update_post_meta($post_id, '_member_phone', sanitize_text_field($data[5]));
                    update_post_meta($post_id, '_member_address', sanitize_text_field($data[6]));
                    update_post_meta($post_id, '_member_postcode', sanitize_text_field($data[7]));
                    update_post_meta($post_id, '_member_city', sanitize_text_field($data[8]));
                    update_post_meta($post_id, '_member_state', sanitize_text_field($data[9]));
                    update_post_meta($post_id, '_member_status', sanitize_text_field($data[11] ?: 'Active'));
                    
                    if (!empty($data[10])) {
                        wp_set_object_terms($post_id, $data[10], 'member_category');
                    }
                    $count++;
                }
            }
            fclose($handle);
            echo "<div class='smdm-alert success'>Successfully imported $count members with Malaysian details.</div>";
        }
    }
}