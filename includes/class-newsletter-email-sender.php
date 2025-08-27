<?php
/**
 * Updated Newsletter Email Sender Class with auto-generated permanent preview links
 * File: includes/class-newsletter-email-sender.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_Email_Sender {
    
    /**
     * Generate a permanent preview hash for campaigns (similar to Public Post Preview plugin)
     * This creates a consistent token that works until the campaign is published
     */
    public static function generate_preview_hash($campaign_id) {
        $campaign = get_post($campaign_id);
        if (!$campaign) {
            return false;
        }
        
        // Create a permanent hash based on campaign ID and site-specific salt
        // This will be the same every time until the post is published
        $secret = wp_salt('nonce') . '_newsletter_' . $campaign_id . '_' . $campaign->post_date;
        return substr(hash('sha256', $secret), 0, 16); // 16 character hash
    }

    /**
     * Validate preview hash
     */
    public static function validate_preview_hash($campaign_id, $provided_hash) {
        $expected_hash = self::generate_preview_hash($campaign_id);
        if (!$expected_hash) {
            return false;
        }
        return hash_equals($expected_hash, $provided_hash);
    }

    /**
     * Check if a campaign is enabled for public preview
     * Auto-enable for all draft campaigns, disable for published ones
     */
    public static function is_public_preview_enabled($campaign_id) {
        $post_status = get_post_status($campaign_id);
        
        // Auto-enable preview for drafts, disable for published
        return ($post_status === 'draft');
    }

    /**
     * Get the view online URL - always return a URL regardless of status
     */
    public static function get_view_online_url($campaign_id) {
        $post_status = get_post_status($campaign_id);
        
        if ($post_status === 'publish') {
            // Published campaigns - use public URL
            return get_permalink($campaign_id);
        } else {
            // Draft campaigns - use secure preview URL with _npp parameter (like Public Post Preview)
            $preview_hash = self::generate_preview_hash($campaign_id);
            $base_url = get_permalink($campaign_id);
            
            // Use _npp parameter to match the style of Public Post Preview plugin
            $preview_args = array(
                'preview' => 'true',
                '_npp' => $preview_hash
            );
            
            return add_query_arg($preview_args, $base_url);
        }
    }

    /**
     * Generate email HTML with view online link always included
     */
    private static function generate_email_html($campaign_id, $is_test = false) {
        $campaign_title = get_post_field('post_title', $campaign_id);
        $content_blocks = get_field('content_blocks', $campaign_id);
        
        if (!$content_blocks || !is_array($content_blocks)) {
            return false;
        }
        
        // Always get the view online URL regardless of status
        $view_online_url = self::get_view_online_url($campaign_id);
        $post_status = get_post_status($campaign_id);
        $view_online_text = ($post_status === 'publish') ? 'View it online' : 'View online preview';
        
        // Start with proper email HTML structure
        $html = '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>' . esc_html($campaign_title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    
    <!-- Main email wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                
                <!-- 600px email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="width: 600px; max-width: 600px; background-color: #ffffff; margin: 0 auto;">
                    
                    <!-- View Online Bar - Always show -->
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 10px 20px; text-align: center; font-size: 12px; color: #666; border-bottom: 1px solid #ddd;">
                            Having trouble viewing this email? <a href="' . esc_url($view_online_url) . '" style="color: #0073aa; text-decoration: underline;">' . $view_online_text . '</a>
                        </td>
                    </tr>
                    
                    <!-- Content blocks -->';
        
        // Process each content block using the existing renderer
        foreach ($content_blocks as $block) {
            $html .= '<tr><td style="padding: 0;">';
            $html .= Newsletter_Blocks::render_block($block, true);
            $html .= '</td></tr>';
        }
        
        $html .= '
                    <!-- Email Footer -->
                    <tr>
                        <td>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f8f8; border-top: 1px solid #ddd;">
                                <tr>
                                    <td style="padding: 20px; text-align: center; font-size: 14px; color: #666; line-height: 1.4;">
                                        <p style="margin: 0 0 10px 0;">© ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.</p>
                                        <p style="margin: 0;"><a href="#unsubscribe" style="color: #666; text-decoration: underline;">Unsubscribe</a> | 
                                         <a href="' . esc_url($view_online_url) . '" style="color: #666; text-decoration: underline;">' . $view_online_text . '</a></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                </table><!-- End 600px container -->
                
            </td>
        </tr>
    </table><!-- End wrapper -->
    
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Send test email with enhanced messaging
     */
    public static function send_test_email($campaign_id, $test_email) {
        $campaign_title = get_post_field('post_title', $campaign_id);
        
        // Generate email HTML
        $email_html = self::generate_email_html($campaign_id, true);
        
        if (!$email_html) {
            return false;
        }
        
        $subject_prefix = '[TEST] ';
        $subject = $subject_prefix . $campaign_title;
        
        // Set headers for HTML email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        return wp_mail($test_email, $subject, $email_html, $headers);
    }
    
    /**
     * Send campaign to audience - with auto-publish and proper status handling
     */
    public static function send_campaign($campaign_id, $audience_id) {
        // Update status to sending
        update_post_meta($campaign_id, '_newsletter_campaign_status', 'sending');
        
        // Get contacts
        $contacts = Newsletter_Database::get_audience_contacts($audience_id);
        
        if (empty($contacts)) {
            update_post_meta($campaign_id, '_newsletter_campaign_status', 'error');
            return array('success' => false, 'error' => 'No active contacts found in audience');
        }
        
        // PUBLISH THE CAMPAIGN BEFORE SENDING
        // This ensures that view online links in emails work properly and preview links expire
        $campaign_post = array(
            'ID' => $campaign_id,
            'post_status' => 'publish'
        );
        wp_update_post($campaign_post);
        
        // Generate email HTML after publishing (so links are correct)
        $email_html = self::generate_email_html($campaign_id, false);
        
        if (!$email_html) {
            update_post_meta($campaign_id, '_newsletter_campaign_status', 'error');
            return array('success' => false, 'error' => 'No email content found');
        }
        
        $campaign_title = get_post_field('post_title', $campaign_id);
        $subject = $campaign_title;
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        $sent_count = 0;
        $failed_emails = array();
        
        foreach ($contacts as $contact) {
            // Send email using the same HTML for all recipients
            $sent = wp_mail($contact->email, $subject, $email_html, $headers);
            
            if ($sent) {
                $sent_count++;
            } else {
                $failed_emails[] = $contact->email;
            }
            
            // Small delay to prevent overwhelming the server
            usleep(100000); // 0.1 second delay
        }
        
        // Update campaign status
        if ($sent_count > 0) {
            update_post_meta($campaign_id, '_newsletter_campaign_status', 'sent');
            update_post_meta($campaign_id, '_newsletter_sent_count', $sent_count);
            update_post_meta($campaign_id, '_newsletter_sent_date', current_time('mysql'));
            update_post_meta($campaign_id, '_newsletter_locked', true); // Lock the campaign
            
            if (!empty($failed_emails)) {
                update_post_meta($campaign_id, '_newsletter_failed_emails', $failed_emails);
            }
            
            return array(
                'success' => true, 
                'sent_count' => $sent_count,
                'failed_count' => count($failed_emails)
            );
        } else {
            update_post_meta($campaign_id, '_newsletter_campaign_status', 'error');
            return array('success' => false, 'error' => 'Failed to send to any recipients');
        }
    }
}