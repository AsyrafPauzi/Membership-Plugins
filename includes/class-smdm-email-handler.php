<?php
/**
 * Handles SMTP Configuration and Batch Email Blasts via AJAX
 */

class SMDM_Email_Handler {

    public function __construct() {
        // 1. Force SMTP configuration
    add_action('phpmailer_init', [$this, 'configure_smtp'], 999);
    
    // 2. FORCE the "From" email to be yours (Fixes the moby2.sfdns.net issue)
    add_filter('wp_mail_from', [$this, 'force_from_email'], 999);
    add_filter('wp_mail_from_name', [$this, 'force_from_name'], 999);

    // AJAX hook
    add_action('wp_ajax_smdm_send_batch', [$this, 'ajax_send_batch']);
    }
    
    public function force_from_email($original_email_address) {
    $opts = get_option('smdm_smtp_settings');
    return (!empty($opts['smtp_from_email'])) ? $opts['smtp_from_email'] : $original_email_address;
}

public function force_from_name($original_email_from) {
    $opts = get_option('smdm_smtp_settings');
    return (!empty($opts['smtp_from_name'])) ? $opts['smtp_from_name'] : $original_email_from;
}
    /**
     * Force WordPress to use the SMTP settings defined in the plugin
     */
    public function configure_smtp($phpmailer) {
        $options = get_option('smdm_smtp_settings');

        if (empty($options['smtp_host']) || empty($options['smtp_user'])) {
            return; // Fallback to default mailer if not configured
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $options['smtp_host'];
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = $options['smtp_port'];
        $phpmailer->Username   = $options['smtp_user'];
        $phpmailer->Password   = $options['smtp_pass'];
        $phpmailer->SMTPSecure = $options['smtp_enc']; // 'tls' or 'ssl'
        $phpmailer->From       = $options['smtp_from_email'];
        $phpmailer->FromName   = $options['smtp_from_name'];
    }

    /**
     * Render the Email Blast Page (Tabs: Send vs Settings)
     */
    public function render_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'blast';
        ?>
        <div class="wrap">
            <h1>Email Management</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=smdm-email-blast&tab=blast" class="nav-tab <?php echo $active_tab == 'blast' ? 'nav-tab-active' : ''; ?>">Send Email Blast</a>
                <a href="?page=smdm-email-blast&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">SMTP Settings</a>
            </h2>

            <?php 
            if ($active_tab == 'settings') {
                $this->render_settings_form();
            } else {
                $this->render_blast_interface();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Interface for the Email Blast with AJAX Progress Bar
     */
    public function render_blast_interface() {
        $cats = get_terms(['taxonomy' => 'member_category', 'hide_empty' => false]);
        ?>
        <div id="smdm-blast-container">
            <!-- Email Composition Form -->
            <form id="smdm-email-form" class="smdm-form-card" style="margin-top:20px;">
                <div class="form-group">
                    <label><strong>Recipient Group:</strong></label>
                    <select id="recipient_cat" style="max-width:300px;">
                        <option value="all">All Active Members</option>
                        <?php foreach($cats as $cat): ?>
                            <option value="<?php echo $cat->term_id; ?>">Category: <?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><strong>Subject:</strong></label>
                    <input type="text" id="email_subject" class="widefat" placeholder="Enter email subject...">
                </div>

                <div class="form-group">
                    <label><strong>Message:</strong></label>
                    <?php wp_editor('', 'email_content', ['textarea_rows' => 12, 'media_buttons' => false]); ?>
                </div>

                <div style="margin-top:20px;">
                    <button type="button" id="start-blast-btn" class="button button-primary button-large">
                        🚀 Start Sending to Members
                    </button>
                </div>
            </form>

            <!-- AJAX Progress UI (Hidden initially) -->
            <div id="smdm-progress-ui" class="smdm-form-card" style="display:none; margin-top:20px; text-align:center;">
                <h2 id="status-title">Sending in Progress...</h2>
                <div class="smdm-progress-bg">
                    <div id="smdm-progress-bar" style="width: 0%;">0%</div>
                </div>
                <p id="smdm-status-text">Preparing member list...</p>
                <div id="smdm-log-box" style="font-family:monospace; font-size:11px; background:#f0f0f0; padding:10px; height:100px; overflow-y:scroll; text-align:left;">
                    Initializing...<br>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#start-blast-btn').on('click', function() {
                const subject = $('#email_subject').val();
                const content = (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_content')) 
                                ? tinyMCE.get('email_content').getContent() 
                                : $('#email_content').val();
                
                if (!subject || !content) {
                    alert('Please fill in both Subject and Content.');
                    return;
                }

                if (!confirm('Are you sure you want to send this blast to your members?')) return;

                // Hide form, show progress
                $('#smdm-email-form').fadeOut();
                $('#smdm-progress-ui').fadeIn();

                // Initial Data
                const blastData = {
                    action: 'smdm_send_batch',
                    nonce: '<?php echo wp_create_nonce("smdm_blast_nonce"); ?>',
                    subject: subject,
                    content: content,
                    category: $('#recipient_cat').val(),
                    offset: 0
                };

                sendBatch(blastData);
            });

            function sendBatch(data) {
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        const res = response.data;
                        
                        // Update Bar
                        $('#smdm-progress-bar').css('width', res.percentage + '%').text(res.percentage + '%');
                        $('#smdm-status-text').text('Processed ' + res.sent + ' / ' + res.total + ' members.');
                        $('#smdm-log-box').append('Batch successful. Sent to ' + res.batch_count + ' emails...<br>');
                        $('#smdm-log-box').scrollTop($('#smdm-log-box')[0].scrollHeight);

                        if (!res.finished) {
                            data.offset = res.next_offset;
                            // Wait 1.5 seconds between batches to protect SMTP reputation
                            setTimeout(() => { sendBatch(data); }, 1500);
                        } else {
                            $('#status-title').text('✅ Sending Complete!');
                            $('#smdm-status-text').html('<strong>All emails have been sent successfully.</strong>');
                            $('#smdm-log-box').append('<strong>Finished.</strong>');
                        }
                    } else {
                        $('#smdm-status-text').html('<span style="color:red;">Error: ' + response.data + '</span>');
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * AJAX Logic for Batching
     */
    public function ajax_send_batch() {
        check_ajax_referer('smdm_blast_nonce', 'nonce');

        $offset  = intval($_POST['offset']);
        $subject = sanitize_text_field($_POST['subject']);
        $content = wp_kses_post(stripslashes($_POST['content']));
        $cat     = $_POST['category'];
        
        $batch_size = 20; // Send 20 emails per AJAX request

        // Query active members (legacy or dynamic status meta).
        $args = [
            'post_type'      => 'member',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_member_status',
                    'value'   => [ SMDM_Field_Schema::get_active_status_literal(), 'Active' ],
                    'compare' => 'IN',
                ],
                [
                    'key'     => SMDM_Field_Schema::meta_key_for( 'account_status' ),
                    'value'   => [ SMDM_Field_Schema::get_active_status_literal(), 'Active' ],
                    'compare' => 'IN',
                ],
            ],
        ];

        if ($cat !== 'all') {
            $args['tax_query'] = [['taxonomy' => 'member_category', 'field' => 'id', 'terms' => $cat]];
        }

        $all_ids = get_posts($args);
        $total   = count($all_ids);
        
        // Get the specific slice for this batch
        $current_batch_ids = array_slice($all_ids, $offset, $batch_size);
        $actual_sent_this_batch = 0;

        $email_meta = SMDM_Field_Schema::meta_key_for( SMDM_Field_Schema::get_primary_email_field_id() );

        foreach ($current_batch_ids as $id) {
            $email = get_post_meta($id, $email_meta, true);
            if (!$email) {
                $email = get_post_meta($id, '_member_email', true);
            }
            if ($email) {
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                if (wp_mail($email, $subject, $content, $headers)) {
                    $actual_sent_this_batch++;
                }
            }
        }

        $new_offset = $offset + $batch_size;
        $finished   = ($new_offset >= $total);
        $percentage = ($total > 0) ? round(($new_offset / $total) * 100) : 100;

        // If finished, log to database
        if ($finished) {
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'member_email_logs', [
                'date_sent'        => current_time('mysql'),
                'subject'          => $subject,
                'total_recipients' => $total,
                'status'           => 'Success'
            ]);
        }

        wp_send_json_success([
            'next_offset' => $new_offset,
            'percentage'  => min($percentage, 100),
            'total'       => $total,
            'sent'        => min($new_offset, $total),
            'batch_count' => $actual_sent_this_batch,
            'finished'    => $finished
        ]);
    }

    /**
     * Settings form for SMTP Credentials
     */
    public function render_settings_form() {
        if (isset($_POST['save_smtp'])) {
            check_admin_referer('smdm_smtp_save', 'smdm_nonce');
            $settings = [
                'smtp_host'       => sanitize_text_field($_POST['smtp_host']),
                'smtp_port'       => sanitize_text_field($_POST['smtp_port']),
                'smtp_user'       => sanitize_text_field($_POST['smtp_user']),
                'smtp_pass'       => $_POST['smtp_pass'], // Password saved as is
                'smtp_enc'        => sanitize_text_field($_POST['smtp_enc']),
                'smtp_from_email' => sanitize_email($_POST['smtp_from_email']),
                'smtp_from_name'  => sanitize_text_field($_POST['smtp_from_name']),
            ];
            update_option('smdm_smtp_settings', $settings);
            echo '<div class="updated"><p>SMTP Configuration Saved!</p></div>';
        }

         $opts = get_option('smdm_smtp_settings', []);
        ?>
        <div class="smdm-content-card">
            <form method="post">
                <?php wp_nonce_field('smdm_smtp_save', 'smdm_nonce'); ?>
                
                <h3 class="smdm-form-section-title">Connection Settings</h3>
                <div class="smdm-form-grid">
                    <div class="form-group smdm-span-2">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo esc_attr($opts['smtp_host'] ?? ''); ?>" placeholder="e.g. mail.itwave.asia" required>
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="text" name="smtp_port" value="<?php echo esc_attr($opts['smtp_port'] ?? '587'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="smtp_enc">
                            <option value="tls" <?php selected($opts['smtp_enc'] ?? '', 'tls'); ?>>TLS (Recommended)</option>
                            <option value="ssl" <?php selected($opts['smtp_enc'] ?? '', 'ssl'); ?>>SSL</option>
                        </select>
                    </div>
                </div>

                <h3 class="smdm-form-section-title" style="margin-top:30px;">Authentication</h3>
                <div class="smdm-form-grid">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="smtp_user" value="<?php echo esc_attr($opts['smtp_user'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="smtp_pass" value="<?php echo esc_attr($opts['smtp_pass'] ?? ''); ?>" required>
                    </div>
                </div>

                <h3 class="smdm-form-section-title" style="margin-top:30px;">Identity</h3>
                <div class="smdm-form-grid">
                    <div class="form-group">
                        <label>From Email Address</label>
                        <input type="email" name="smtp_from_email" value="<?php echo esc_attr($opts['smtp_from_email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" value="<?php echo esc_attr($opts['smtp_from_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div style="margin-top:30px; border-top:1px solid #f1f5f9; padding-top:20px;">
                    <button type="submit" name="save_smtp" class="smdm-btn">Save Configuration</button>
                </div>
            </form>
        </div>
        <?php
    }
}