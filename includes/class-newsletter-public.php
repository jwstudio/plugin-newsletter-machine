<?php
/**
 * Robust Newsletter_Public class that properly handles draft access with tokens
 * File: includes/class-newsletter-public.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_Public {
    
    public function __construct() {
        // Hook early to intercept WordPress query processing
        add_action('parse_request', array($this, 'handle_newsletter_preview_access'), 1);
        add_filter('posts_results', array($this, 'allow_draft_with_token'), 10, 2);
        
        // Template filters
        add_filter('single_template', array($this, 'load_newsletter_template'));
        add_filter('template_include', array($this, 'load_newsletter_template_fallback'));
        
        // Handle access control
        add_action('template_redirect', array($this, 'check_campaign_access'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        
        // Override WordPress's default behavior for our post type
        add_filter('pre_get_posts', array($this, 'modify_newsletter_query'));
    }
    
    /**
     * Modify the main query to allow draft newsletter campaigns with valid tokens
     */
    public function modify_newsletter_query($query) {
        // Only affect main query on frontend for single newsletter campaigns
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Check if this is a newsletter campaign request
        if ($query->get('post_type') === 'newsletter_campaign' || 
            (isset($query->query_vars['name']) && $this->is_newsletter_url_pattern())) {
            
            // If we have a preview token, allow draft posts
            if (isset($_GET['preview_token'])) {
                $query->set('post_status', array('publish', 'draft'));
            }
        }
    }
    
    /**
     * Check if the current URL pattern matches newsletter campaigns
     */
    private function is_newsletter_url_pattern() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($request_uri, '/newsletter-campaign/') !== false;
    }
    
    /**
     * Handle preview access at the parse_request level
     */
    public function handle_newsletter_preview_access($wp) {
        // Check if this is a newsletter campaign with preview token
        if (!isset($_GET['preview_token'])) {
            return;
        }
        
        // Look for newsletter campaign in the request
        if (isset($wp->query_vars['post_type']) && $wp->query_vars['post_type'] === 'newsletter_campaign') {
            // Allow draft access for this request
            $wp->query_vars['post_status'] = array('publish', 'draft');
        } elseif (isset($wp->query_vars['name']) && $this->is_newsletter_url_pattern()) {
            // This might be a pretty permalink request
            $wp->query_vars['post_type'] = 'newsletter_campaign';
            $wp->query_vars['post_status'] = array('publish', 'draft');
        }
    }
    
    /**
     * Filter posts results to allow draft campaigns with valid tokens
     */
    public function allow_draft_with_token($posts, $query) {
        // Only process single newsletter campaign queries
        if (!$query->is_main_query() || is_admin() || 
            !$query->is_singular() || 
            $query->get('post_type') !== 'newsletter_campaign') {
            return $posts;
        }
        
        // If no posts found and we have a preview token, try to find draft
        if (empty($posts) && isset($_GET['preview_token'])) {
            $token = sanitize_text_field($_GET['preview_token']);
            
            // Try to find the campaign by name (for pretty permalinks)
            if ($query->get('name')) {
                $draft_posts = get_posts(array(
                    'name' => $query->get('name'),
                    'post_type' => 'newsletter_campaign',
                    'post_status' => 'draft',
                    'numberposts' => 1
                ));
                
                if (!empty($draft_posts)) {
                    $campaign = $draft_posts[0];
                    
                    // Validate the token
                    if (Newsletter_Email_Sender::validate_preview_hash($campaign->ID, $token)) {
                        // Token is valid - return the draft post
                        return array($campaign);
                    }
                }
            }
            
            // Try to find by ID if name lookup failed
            if ($query->get('p')) {
                $campaign_id = intval($query->get('p'));
                $campaign = get_post($campaign_id);
                
                if ($campaign && $campaign->post_type === 'newsletter_campaign' && $campaign->post_status === 'draft') {
                    if (Newsletter_Email_Sender::validate_preview_hash($campaign_id, $token)) {
                        return array($campaign);
                    }
                }
            }
        }
        
        return $posts;
    }
    
    /**
     * Check if user can access the campaign - now more permissive
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
        
        // For draft campaigns
        if ($post->post_status === 'draft') {
            
            // First check for preview token (highest priority)
            if (isset($_GET['preview_token'])) {
                $provided_token = sanitize_text_field($_GET['preview_token']);
                $is_valid = Newsletter_Email_Sender::validate_preview_hash($post->ID, $provided_token);
                
                if ($is_valid) {
                    // Valid token - allow access
                    return;
                }
            }
            
            // Check if user is admin/editor (second priority)
            if (current_user_can('edit_posts')) {
                return;
            }
            
            // Check for WordPress preview parameter (for admin preview)
            if (isset($_GET['preview']) && current_user_can('edit_post', $post->ID)) {
                return;
            }
            
            // No access - show error
            $this->show_access_denied($post->ID);
        }
        
        // Any other status - block access
        $this->show_not_available();
    }
    
    /**
     * Show access denied message with debug info
     */
    private function show_access_denied($campaign_id = null) {
        $debug_info = '';
        
        if (current_user_can('manage_options') && $campaign_id) {
            $provided_token = isset($_GET['preview_token']) ? sanitize_text_field($_GET['preview_token']) : 'none';
            $expected_token = Newsletter_Email_Sender::generate_preview_hash($campaign_id);
            
            $debug_info = '<hr><p style="background: #f0f0f0; padding: 10px; font-family: monospace; font-size: 12px;">
                <strong>Debug Info (admin only):</strong><br>
                Campaign ID: ' . $campaign_id . '<br>
                Provided Token: ' . $provided_token . '<br>
                Expected Token: ' . $expected_token . '<br>
                Match: ' . (hash_equals($expected_token, $provided_token) ? 'Yes' : 'No') . '
                </p>';
        }
        
        wp_die(
            '<h1>Newsletter Preview Access Required</h1>
            <p>This newsletter is still in draft mode and requires a valid preview link to access.</p>
            <p>If you have a preview link from an email, please make sure you\'re using the complete URL including the preview token.</p>
            ' . $debug_info . '
            <p><a href="' . home_url() . '">← Return to website</a></p>',
            'Newsletter Access Denied',
            array('response' => 403)
        );
    }
    
    /**
     * Show not available message
     */
    private function show_not_available() {
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