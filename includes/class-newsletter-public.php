<?php
/**
 * Fixed Newsletter_Public class with proper preview access control
 * File: includes/class-newsletter-public.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_Public {
    
    public function __construct() {
        // Template filters for standard WordPress URLs
        add_filter('single_template', array($this, 'load_newsletter_template'));
        add_filter('template_include', array($this, 'load_newsletter_template_fallback'));
        
        // Handle access control before template loads
        add_action('template_redirect', array($this, 'check_campaign_access'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
    }
    
    /**
     * Check if user can access the campaign
     */
    public function check_campaign_access() {
        if (!is_singular('newsletter_campaign')) {
            return;
        }
        
        global $post;
        
        // Published campaigns - everyone can access
        if ($post->post_status === 'publish') {
            return;
        }
        
        // Draft campaigns - need special access
        if ($post->post_status === 'draft') {
            
            // Check if user is admin/editor
            if (current_user_can('edit_posts')) {
                // Admins can always view drafts
                return;
            }
            
            // Check for valid preview token
            if (isset($_GET['preview_token'])) {
                $provided_token = sanitize_text_field($_GET['preview_token']);
                $is_valid = Newsletter_Email_Sender::validate_preview_hash($post->ID, $provided_token);
                
                if ($is_valid) {
                    // Valid token - allow access
                    return;
                }
            }
            
            // No access - show error
            wp_die(
                '<h1>Newsletter Not Available</h1>
                <p>This newsletter is not published yet and requires a valid preview link to access.</p>
                <p><a href="' . home_url() . '">← Return to website</a></p>',
                'Newsletter Access Denied',
                array('response' => 403)
            );
        }
        
        // Any other status - block access
        wp_die(
            '<h1>Newsletter Not Available</h1>
            <p>This newsletter is not available for viewing.</p>
            <p><a href="' . home_url() . '">← Return to website</a></p>',
            'Newsletter Not Available',
            array('response' => 404)
        );
    }
    
    /**
     * Load custom template for newsletter_campaign post type
     */
    public function load_newsletter_template($template) {
        global $post;
        
        if ($post && $post->post_type === 'newsletter_campaign') {
            $custom_template = NEWSLETTER_PLUGIN_PATH . 'templates/single-newsletter-campaign.php';
            
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Fallback template loader
     */
    public function load_newsletter_template_fallback($template) {
        if (is_singular('newsletter_campaign')) {
            $custom_template = NEWSLETTER_PLUGIN_PATH . 'templates/single-newsletter-campaign.php';
            
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    public function enqueue_public_assets() {
        if (is_singular('newsletter_campaign')) {
            wp_enqueue_style(
                'newsletter-email-preview',
                NEWSLETTER_PLUGIN_URL . 'assets/css/email-preview.css',
                array(),
                NEWSLETTER_PLUGIN_VERSION
            );
        }
    }
}