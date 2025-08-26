<?php
/**
 * Updated Newsletter Custom Post Type Class with auto-preview system
 * File: includes/class-newsletter-cpt.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_CPT {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_campaign_meta'));
        
        // Add hooks to prevent unpublishing sent campaigns
        add_action('wp_before_admin_bar_render', array($this, 'modify_admin_bar'));
        add_action('admin_head', array($this, 'hide_publish_options_for_sent'));
        add_filter('wp_insert_post_data', array($this, 'prevent_status_change'), 10, 2);
    }
    
    public function register_post_type() {
        $args = array(
            'labels' => array(
                'name' => __('Newsletter Campaigns', 'newsletter-plugin'),
                'singular_name' => __('Campaign', 'newsletter-plugin'),
                'add_new' => __('Add New Campaign', 'newsletter-plugin'),
                'add_new_item' => __('Add New Campaign', 'newsletter-plugin'),
                'edit_item' => __('Edit Campaign', 'newsletter-plugin'),
                'new_item' => __('New Campaign', 'newsletter-plugin'),
                'view_item' => __('View Campaign', 'newsletter-plugin'),
                'search_items' => __('Search Campaigns', 'newsletter-plugin'),
                'not_found' => __('No campaigns found', 'newsletter-plugin'),
                'not_found_in_trash' => __('No campaigns found in trash', 'newsletter-plugin'),
                'menu_name' => __('Newsletter', 'newsletter-plugin')
            ),
            'public' => true,
            'has_archive' => false,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-email-alt',
            'menu_position' => 25,
            'supports' => array('title'),
            'capability_type' => 'post',
            'hierarchical' => false,
            'show_in_rest' => false,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'exclude_from_search' => true,
            'rewrite' => array(
                'slug' => 'newsletter-campaign',
                'with_front' => false
            )
        );
        
        register_post_type('newsletter_campaign', $args);
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'newsletter_campaign_settings',
            'Campaign Settings',
            array($this, 'campaign_settings_callback'),
            'newsletter_campaign',
            'side',
            'default'
        );
        
        add_meta_box(
            'newsletter_campaign_send',
            'Send Campaign',
            array($this, 'campaign_send_callback'),
            'newsletter_campaign',
            'side',
            'default'
        );
    }
    
    public function campaign_settings_callback($post) {
        wp_nonce_field('newsletter_campaign_nonce', 'newsletter_campaign_nonce');
        
        $selected_audience = get_post_meta($post->ID, '_newsletter_audience', true);
        $audiences = Newsletter_Database::get_audiences();
        $campaign_status = get_post_meta($post->ID, '_newsletter_campaign_status', true);
        $is_locked = get_post_meta($post->ID, '_newsletter_locked', true);
        
        echo '<p><label for="newsletter_audience"><strong>Select Audience:</strong></label></p>';
        
        if ($campaign_status === 'sent' && $is_locked) {
            // Show read-only audience for sent campaigns
            $audience = Newsletter_Database::get_audience($selected_audience);
            echo '<input type="text" value="' . esc_attr($audience->name . ' (' . $audience->contact_count . ' contacts)') . '" style="width: 100%; background: #f8f9fa;" readonly>';
            echo '<input type="hidden" name="newsletter_audience" value="' . esc_attr($selected_audience) . '">';
        } else {
            // Show dropdown for unsent campaigns
            echo '<select name="newsletter_audience" id="newsletter_audience" style="width: 100%;">';
            echo '<option value="">-- Select Audience --</option>';
            
            foreach ($audiences as $audience) {
                $selected = selected($selected_audience, $audience->id, false);
                echo '<option value="' . esc_attr($audience->id) . '" ' . $selected . '>';
                echo esc_html($audience->name) . ' (' . esc_html($audience->contact_count) . ' contacts)';
                echo '</option>';
            }
            echo '</select>';
        }
    }
    
    public function campaign_send_callback($post) {
        $selected_audience = get_post_meta($post->ID, '_newsletter_audience', true);
        $campaign_status = get_post_meta($post->ID, '_newsletter_campaign_status', true);
        $sent_count = get_post_meta($post->ID, '_newsletter_sent_count', true);
        $sent_date = get_post_meta($post->ID, '_newsletter_sent_date', true);
        
        // Get audience info
        $audience = null;
        if ($selected_audience) {
            $audience = Newsletter_Database::get_audience($selected_audience);
        }
        
        echo '<div class="newsletter-send-status">';
        
        // Show current status
        if ($campaign_status === 'sent') {
            echo '<div class="notice notice-success inline" style="margin: 0; padding: 8px 12px;">';
            echo '<p style="margin: 5px 0;"><strong>Campaign Sent!</strong></p>';
            echo '<p style="margin: 5px 0; font-size: 12px;">Sent to ' . esc_html($sent_count) . ' contacts on ' . esc_html($sent_date) . '</p>';
            echo '</div>';
        } elseif ($campaign_status === 'sending') {
            echo '<div class="notice notice-info inline" style="margin: 0; padding: 8px 12px;">';
            echo '<p style="margin: 5px 0;"><strong>Sending in progress...</strong></p>';
            echo '</div>';
        }
        
        // View Online section - ALWAYS show regardless of status
        echo '<div style="border: 1px solid #ddd; padding: 12px; margin: 10px 0; background: #f9f9f9; border-radius: 4px;">';
        echo '<p style="margin: 0 0 8px 0; font-weight: bold; color: #333;">📧 View Online Link:</p>';
        
        $view_online_url = Newsletter_Email_Sender::get_view_online_url($post->ID);
        $post_status = get_post_status($post->ID);
        
        if ($post_status === 'publish') {
            echo '<div style="background: #e8f5e8; padding: 8px; border-radius: 3px; border-left: 3px solid #00a32a; margin-bottom: 8px;">';
            echo '<p style="margin: 0; font-size: 12px; color: #006600;"><strong>✓ Published:</strong> Public URL is live</p>';
            echo '</div>';
            echo '<input type="text" value="' . esc_attr($view_online_url) . '" style="width: 100%; font-size: 11px; background: #fff;" readonly onclick="this.select();">';
        } else {
            echo '<div style="background: #fff3cd; padding: 8px; border-radius: 3px; border-left: 3px solid #ffc107; margin-bottom: 8px;">';
            echo '<p style="margin: 0; font-size: 12px; color: #856404;"><strong>⚠ Draft:</strong> Secure preview link (expires when published)</p>';
            echo '</div>';
            echo '<input type="text" value="' . esc_attr($view_online_url) . '" style="width: 100%; font-size: 11px; background: #fff;" readonly onclick="this.select();">';
        }
        
        echo '<p style="margin: 8px 0 0 0; font-size: 11px; color: #666;">This link is automatically included in all emails and works for anyone.</p>';
        echo '</div>';
        
        // Audience info
        if (!$audience) {
            echo '<div style="margin-top: 10px;">';
            echo '<p style="color: #d63638;"><strong>⚠ No audience selected</strong><br>';
            echo '<small>Please select an audience in Campaign Settings first.</small></p>';
            echo '</div>';
        }
        
        // Send button section
        if ($selected_audience && $campaign_status !== 'sent' && $campaign_status !== 'sending') {
            echo '<div style="margin-top: 15px;">';
            
            // Test email section
            echo '<div style="border: 1px solid #ddd; padding: 12px; margin-bottom: 15px; background: #f9f9f9; border-radius: 4px;">';
            echo '<p style="margin: 0 0 8px 0; font-weight: bold; color: #333;">🧪 Send Test Email:</p>';
            echo '<input type="email" id="test_email" placeholder="test@example.com" style="width: 100%; margin-bottom: 8px; padding: 6px;">';
            echo '<button type="button" id="send_test_email" class="button button-secondary" style="width: 100%;">Send Test Email</button>';
            echo '<p style="margin: 8px 0 0 0; font-size: 11px; color: #666;">Test emails include the view online link and show [TEST] or [DRAFT TEST] in subject.</p>';
            echo '</div>';
            
            // Main send section
            echo '<div style="border: 2px solid #dc3232; padding: 12px; border-radius: 4px; background: #fef7f7;">';
            echo '<p style="margin: 0 0 10px 0; font-weight: bold; color: #dc3232;">🚀 Send Campaign:</p>';
            echo '<p style="margin: 0 0 10px 0; font-size: 12px; color: #666;">Send to all ' . esc_html($audience->contact_count) . ' contacts in "' . esc_html($audience->name) . '"</p>';
            echo '<p style="margin: 0 0 12px 0; font-size: 11px; color: #d63638; font-weight: bold;">⚠ This will publish the campaign and cannot be undone!</p>';
            echo '<button type="button" id="send_campaign" class="button button-primary" style="width: 100%; background: #dc3232; border-color: #dc3232;">Send Campaign Now</button>';
            echo '</div>';
            
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add JavaScript for AJAX handling
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Test email
            $('#send_test_email').on('click', function() {
                var email = $('#test_email').val();
                if (!email || !email.includes('@')) {
                    alert('Please enter a valid email address.');
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('Sending...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'newsletter_send_test',
                        campaign_id: <?php echo intval($post->ID); ?>,
                        test_email: email,
                        nonce: '<?php echo wp_create_nonce('newsletter_send_test'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ Test email sent successfully to ' + email + '!\n\nThe test email includes:\n• View online link that works without login\n• Subject line marked with [TEST] or [DRAFT TEST]\n• Full newsletter content as it will appear');
                            $('#test_email').val('');
                        } else {
                            alert('❌ Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('❌ Network error sending test email. Please try again.');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Send Test Email');
                    }
                });
            });
            
            // Send campaign
            $('#send_campaign').on('click', function() {
                var confirmMsg = '🚨 FINAL CONFIRMATION\n\n';
                confirmMsg += 'This will:\n';
                confirmMsg += '• Publish this campaign (making it publicly visible)\n';
                confirmMsg += '• Send to <?php echo esc_js($audience ? $audience->contact_count : 0); ?> contacts\n';
                confirmMsg += '• Lock the campaign from further editing\n';
                confirmMsg += '• Expire the preview link\n\n';
                confirmMsg += 'This action CANNOT be undone.\n\n';
                confirmMsg += 'Are you absolutely sure?';
                
                if (!confirm(confirmMsg)) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('Publishing & Sending...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'newsletter_send_campaign',
                        campaign_id: <?php echo intval($post->ID); ?>,
                        nonce: '<?php echo wp_create_nonce('newsletter_send_campaign'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('🎉 Campaign sent successfully!\n\n' + response.data.sent_count + ' emails sent to contacts.\n\nThe campaign is now published and locked from editing.');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('❌ Network error sending campaign. Please try again.');
                    },
                    complete: function() {
                        if (button.text() === 'Publishing & Sending...') {
                            button.prop('disabled', false).text('Send Campaign Now');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function save_campaign_meta($post_id) {
        if (!isset($_POST['newsletter_campaign_nonce']) || !wp_verify_nonce($_POST['newsletter_campaign_nonce'], 'newsletter_campaign_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['newsletter_audience'])) {
            update_post_meta($post_id, '_newsletter_audience', sanitize_text_field($_POST['newsletter_audience']));
        }
    }
    
    /**
     * Prevent changing status of sent campaigns
     */
    public function prevent_status_change($data, $postarr) {
        if ($data['post_type'] !== 'newsletter_campaign') {
            return $data;
        }
        
        // Check if this campaign was already sent
        if (isset($postarr['ID'])) {
            $is_sent = get_post_meta($postarr['ID'], '_newsletter_campaign_status', true) === 'sent';
            $is_locked = get_post_meta($postarr['ID'], '_newsletter_locked', true);
            
            if ($is_sent && $is_locked) {
                // Force it to stay published
                $data['post_status'] = 'publish';
            }
        }
        
        return $data;
    }

    /**
     * Hide publish options for sent campaigns
     */
    public function hide_publish_options_for_sent() {
        global $post;
        
        if (!$post || $post->post_type !== 'newsletter_campaign') {
            return;
        }
        
        $is_sent = get_post_meta($post->ID, '_newsletter_campaign_status', true) === 'sent';
        $is_locked = get_post_meta($post->ID, '_newsletter_locked', true);
        
        if ($is_sent && $is_locked) {
            ?>
            <style>
                #misc-publishing-actions .misc-pub-post-status,
                #misc-publishing-actions .misc-pub-visibility,
                #rank-math-lock-modified-date {
                    display: none !important;
                }
                
                #publishing-action #publish {
                    display: none;
                }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                // Disable title editing for sent campaigns
                $('#title').prop('readonly', true).css('background-color', '#f8f9fa');
                
                // Add notice
                $('#titlediv').before('<div class="notice notice-info"><p><strong>🔒 This campaign has been sent and is now locked.</strong> All content is permanently locked to preserve the integrity of the sent campaign.</p></div>');
                
                // Disable ACF fields
                $('.acf-field input, .acf-field textarea, .acf-field select').prop('disabled', true);
                $('.acf-field').css('opacity', '0.6');
            });
            </script>
            <?php
        }
    }

    /**
     * Modify admin bar for sent campaigns
     */
    public function modify_admin_bar() {
        global $wp_admin_bar, $post;
        
        if (!$post || $post->post_type !== 'newsletter_campaign') {
            return;
        }
        
        $is_sent = get_post_meta($post->ID, '_newsletter_campaign_status', true) === 'sent';
        
        if ($is_sent) {
            // Remove edit link from admin bar for sent campaigns viewed publicly
            $wp_admin_bar->remove_menu('edit');
        }
    }
}