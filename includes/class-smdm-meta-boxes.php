<?php
class SMDM_Meta_Boxes {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_member_meta_boxes']);
        add_action('save_post', [$this, 'save_member_meta']);
    }

    public function add_member_meta_boxes() {
        add_meta_box('member_details', 'Member Details', [$this, 'render_meta_box'], 'member', 'normal', 'high');
    }

    public function render_meta_box($post) {
        wp_nonce_field('smdm_save_meta', 'smdm_meta_nonce');
        $email = get_post_meta($post->ID, '_member_email', true);
        $phone = get_post_meta($post->ID, '_member_phone', true);
        $status = class_exists( 'SMDM_Field_Schema' )
            ? SMDM_Field_Schema::get_member_value( $post->ID, array( 'id' => 'account_status' ) )
            : get_post_meta( $post->ID, '_member_status', true );
        if ( '' === $status ) {
            $status = get_post_meta( $post->ID, '_member_status', true );
        }
        $reg_date = get_post_meta($post->ID, '_member_date_registered', true);

        if (empty($reg_date) && $post->post_status == 'publish') {
            $reg_date = get_the_date('Y-m-d H:i:s', $post->ID);
        }
        $a  = class_exists( 'SMDM_Field_Schema' ) ? SMDM_Field_Schema::get_active_status_literal() : 'Aktif';
        $ia = class_exists( 'SMDM_Field_Schema' ) ? SMDM_Field_Schema::get_inactive_status_literal() : 'Tidak aktif';
        ?>
        <div class="smdm-meta-field">
            <label><?php esc_html_e( 'Email address', 'smdm' ); ?> <span class="description"><?php esc_html_e( '(optional)', 'smdm' ); ?></span></label>
            <input type="email" name="member_email" value="<?php echo esc_attr( $email ); ?>" class="widefat">
        </div>
        <div class="smdm-meta-field">
            <label>Phone Number</label>
            <input type="text" name="member_phone" value="<?php echo esc_attr($phone); ?>" class="widefat">
        </div>
        <div class="smdm-meta-field">
            <label>Status</label>
            <select name="member_status" class="widefat">
                <option value="<?php echo esc_attr( $a ); ?>" <?php selected( in_array( $status, array( $a, 'Active' ), true ) ); ?>><?php echo esc_html( $a ); ?></option>
                <option value="<?php echo esc_attr( $ia ); ?>" <?php selected( in_array( $status, array( $ia, 'Inactive' ), true ) ); ?>><?php echo esc_html( $ia ); ?></option>
            </select>
        </div>
        <div class="smdm-meta-field">
            <label>Date Registered</label>
            <input type="text" name="member_date_registered" value="<?php echo esc_attr($reg_date); ?>" readonly class="widefat" style="background:#eee;">
        </div>
        <?php
    }

    public function save_member_meta($post_id) {
        if (!isset($_POST['smdm_meta_nonce']) || !wp_verify_nonce($_POST['smdm_meta_nonce'], 'smdm_save_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (isset($_POST['member_email'])) {
            update_post_meta($post_id, '_member_email', sanitize_email($_POST['member_email']));
        }
        if (isset($_POST['member_phone'])) {
            update_post_meta($post_id, '_member_phone', sanitize_text_field($_POST['member_phone']));
        }
        if (isset($_POST['member_status'])) {
            update_post_meta($post_id, '_member_status', sanitize_text_field($_POST['member_status']));
        }
        
        // Auto-fill date if empty
        if (!get_post_meta($post_id, '_member_date_registered', true)) {
            update_post_meta($post_id, '_member_date_registered', current_time('mysql'));
        }
    }
}