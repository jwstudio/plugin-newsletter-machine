<?php
/**
 * Newsletter Email Sender Class
 * File: includes/class-newsletter-email-sender.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_Email_Sender {
    
/**
 * Generate a secure preview hash for draft campaigns
 */
public static function generate_preview_hash($campaign_id) {
    $secret = wp_salt('nonce') . $campaign_id . get_post_field('post_date', $campaign_id);
    return substr(hash('sha256', $secret), 0, 16); // 16 character hash
}

/**
 * Validate preview hash
 */
public static function validate_preview_hash($campaign_id, $provided_hash) {
    $expected_hash = self::generate_preview_hash($campaign_id);
    return hash_equals($expected_hash, $provided_hash);
}

    /**
 * Updated generate_email_html method with secure preview links
 */
private static function generate_email_html($campaign_id, $is_test = false) {
    $campaign_title = get_post_field('post_title', $campaign_id);
    $content_blocks = get_field('content_blocks', $campaign_id);
    
    if (!$content_blocks || !is_array($content_blocks)) {
        return false;
    }
    
    // Determine the view online URL
    $post_status = get_post_status($campaign_id);
    if ($post_status === 'publish') {
        // Published campaigns use normal permalink
        $view_online_url = get_permalink($campaign_id);
    } else {
        // Draft campaigns use secure hash
        $preview_hash = self::generate_preview_hash($campaign_id);
        $view_online_url = get_permalink($campaign_id) . '?preview_token=' . $preview_hash;
    }
    
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
                    
                    <!-- View Online Bar -->
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 10px 20px; text-align: center; font-size: 12px; color: #666; border-bottom: 1px solid #ddd;">
                            Having trouble viewing this email? <a href="' . esc_url($view_online_url) . '" style="color: #0073aa; text-decoration: underline;">View it online</a>
                        </td>
                    </tr>
                    
                    <!-- Content blocks -->';
    
    // Process each content block using the existing renderer
    foreach ($content_blocks as $block) {
        $html .= '<tr><td style="padding: 0;">';
        $html .= Newsletter_Blocks::render_block($block, true);
        $html .= '</td></tr>';
    }
    
    // Add consistent footer
    $html .= '
                    <!-- Email Footer -->
                    <tr>
                        <td>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f8f8; border-top: 1px solid #ddd;">
                                <tr>
                                    <td style="padding: 20px; text-align: center; font-size: 14px; color: #666; line-height: 1.4;">
                                        <p style="margin: 0 0 10px 0;">© ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.</p>
                                        ' . ($is_test ? 
                                        '<p style="margin: 0; background: #fff3cd; color: #856404; padding: 8px; border: 1px solid #ffeaa7; border-radius: 4px;"><strong>This is a test email</strong></p>' :
                                        '<p style="margin: 0;">
                                            <a href="#unsubscribe" style="color: #666; text-decoration: underline;">Unsubscribe</a> | 
                                            <a href="' . esc_url($view_online_url) . '" style="color: #666; text-decoration: underline;">View Online</a>
                                        </p>'
                                        ) . '
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
     * Send test email - simplified
     */
    public static function send_test_email($campaign_id, $test_email) {
        $campaign_title = get_post_field('post_title', $campaign_id);
        
        // Generate email HTML
        $email_html = self::generate_email_html($campaign_id, true);
        
        if (!$email_html) {
            return false;
        }
        
        $subject = '[TEST] ' . $campaign_title;
        
        // Set headers for HTML email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        return wp_mail($test_email, $subject, $email_html, $headers);
    }
    
    /**
 * Send campaign to audience - with auto-publish
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
    
    // Generate email HTML once
    $email_html = self::generate_email_html($campaign_id, false);
    
    if (!$email_html) {
        update_post_meta($campaign_id, '_newsletter_campaign_status', 'error');
        return array('success' => false, 'error' => 'No email content found');
    }
    
    // PUBLISH THE CAMPAIGN BEFORE SENDING
    $campaign_post = array(
        'ID' => $campaign_id,
        'post_status' => 'publish'
    );
    wp_update_post($campaign_post);
    
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