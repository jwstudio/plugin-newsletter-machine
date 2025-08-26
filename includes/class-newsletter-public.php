<?php
/**
 * Updated Newsletter_Public class with auto-preview system (no expiration)
 * File: includes/class-newsletter-public.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_Public {
    
    public function __construct() {
        // Add query var for our preview parameter
        add_filter('query_vars', array($this, 'add_query_var'));
        
        // Hook into the main query to handle preview access
        add_action('pre_get_posts', array($this, 'show_public_preview'));
        
        // Filter posts results to handle draft access
        add_filter('posts_results', array($this, 'set_post_to_publish'), 10, 2);
        
        // Template filters
        add_filter('single_template', array($this, 'load_newsletter_template'));
        add_filter('template_include', array($this, 'load_newsletter_template_fallback'));
        
        // Add no-cache headers for preview
        add_action('wp_head', array($this, 'add_preview_headers'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
    }
    
    /**
     * Add query var for _npp (newsletter public preview)
     */
    public function add_query_var($qv) {
        $qv[] = '_npp';
        return $qv;
    }
    
    /**
     * Show public preview when conditions are met
     */
    public function show_public_preview($query) {
        // Only handle main query on frontend
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Check if this is a preview request for our post type
        if ($query->is_preview() && 
            $query->is_singular() && 
            ($query->get('post_type') === 'newsletter_campaign' || 
             $this->is_newsletter_request($query)) &&
            get_query_var('_npp')) {
            
            // Add no-cache headers
            if (!headers_sent()) {
                nocache_headers();
                header('X-Robots-Tag: noindex');
            }
            
            // Add filter to handle the post
            add_filter('posts_results', array($this, 'set_post_to_publish'), 10, 2);
        }
    }
    
    /**
     * Check if this is a newsletter campaign request
     */
    private function is_newsletter_request($query) {
        // Check various ways the query might indicate a newsletter campaign
        if ($query->get('post_type') === 'newsletter_campaign') {
            return true;
        }
        
        // Check if the name/slug suggests it's a newsletter
        $name = $query->get('name');
        if ($name) {
            $post = get_posts(array(
                'name' => $name,
                'post_type' => 'newsletter_campaign',
                'post_status' => array('draft', 'publish'),
                'numberposts' => 1
            ));
            return !empty($post);
        }
        
        // Check by ID
        $p = $query->get('p');
        if ($p) {
            $post = get_post($p);
            return ($post && $post->post_type === 'newsletter_campaign');
        }
        
        return false;
    }
    
    /**
     * Check if public preview is available
     */
    private function is_public_preview_available($post_id) {
        if (empty($post_id)) {
            return false;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'newsletter_campaign') {
            return false;
        }
        
        // Only allow preview for drafts
        if ($post->post_status !== 'draft') {
            return false;
        }
        
        // Verify the nonce
        $provided_nonce = get_query_var('_npp');
        if (!$provided_nonce) {
            return false;
        }
        
        return Newsletter_Email_Sender::validate_preview_hash($post_id, $provided_nonce);
    }
    
    /**
     * Set post to publish for valid preview requests
     */
    public function set_post_to_publish($posts, $query) {
        // Remove filter to prevent infinite loops
        remove_filter('posts_results', array($this, 'set_post_to_publish'), 10);
        
        // Only handle main query on frontend
        if (is_admin() || !$query->is_main_query()) {
            return $posts;
        }
        
        // Must be a preview request
        if (!$query->is_preview() || !$query->is_singular()) {
            return $posts;
        }
        
        // Must have our preview parameter
        if (!get_query_var('_npp')) {
            return $posts;
        }
        
        // Handle empty posts (draft not found in initial query)
        if (empty($posts)) {
            $post_id = null;
            
            // Try to find by name
            if ($query->get('name')) {
                $found_posts = get_posts(array(
                    'name' => $query->get('name'),
                    'post_type' => 'newsletter_campaign',
                    'post_status' => 'draft',
                    'numberposts' => 1
                ));
                if (!empty($found_posts)) {
                    $posts = $found_posts;
                    $post_id = $found_posts[0]->ID;
                }
            }
            
            // Try to find by ID
            if (empty($posts) && $query->get('p')) {
                $p = intval($query->get('p'));
                $post = get_post($p);
                if ($post && $post->post_type === 'newsletter_campaign' && $post->post_status === 'draft') {
                    $posts = array($post);
                    $post_id = $post->ID;
                }
            }
        } else {
            // Posts found, get the first one
            $post_id = $posts[0]->ID;
        }
        
        // Validate access
        if ($post_id && $this->is_public_preview_available($post_id)) {
            // Set post status to publish so it's visible
            if (!empty($posts)) {
                $posts[0]->post_status = 'publish';
                
                // Disable comments and pings
                add_filter('comments_open', '__return_false');
                add_filter('pings_open', '__return_false');
                
                // Add preview indicator
                add_action('wp_head', array($this, 'add_preview_indicator'));
            }
        } elseif (!empty($posts)) {
            // Invalid or missing preview token
            $this->show_access_denied($post_id);
        }
        
        return $posts;
    }
    
    /**
     * Add preview headers for no-cache and no-index
     */
    public function add_preview_headers() {
        if (is_singular('newsletter_campaign') && get_query_var('_npp')) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
    }
    
    /**
     * Add preview indicator (for debugging)
     */
    public function add_preview_indicator() {
        if (current_user_can('manage_options')) {
            echo '<!-- Newsletter Preview Mode Active -->' . "\n";
        }
    }
    
    /**
     * Show access denied message
     */
    private function show_access_denied($campaign_id = null) {
        $debug_info = '';
        
        if (current_user_can('manage_options') && $campaign_id) {
            $provided_token = get_query_var('_npp');
            $expected_token = Newsletter_Email_Sender::generate_preview_hash($campaign_id);
            
            $debug_info = '<hr><div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <strong>Debug Info (admin only):</strong><br>
                Campaign ID: ' . $campaign_id . '<br>
                Post Status: ' . get_post_status($campaign_id) . '<br>
                Provided Token: ' . ($provided_token ?: 'none') . '<br>
                Expected Token: ' . $expected_token . '<br>
                Match: ' . (hash_equals($expected_token ?: '', $provided_token ?: '') ? 'Yes' : 'No') . '<br>
                Preview Enabled: ' . (Newsletter_Email_Sender::is_public_preview_enabled($campaign_id) ? 'Yes' : 'No') . '
                </div>';
        }
        
        wp_die(
            '<h1>Newsletter Preview Access Required</h1>
            <p>This newsletter is in draft mode and requires a valid preview link to access.</p>
            <p>If you received a preview link in an email, please make sure you\'re using the complete URL including the preview token.</p>
            <p><strong>Note:</strong> Preview links are automatically generated for draft campaigns and expire when the newsletter is published.</p>
            ' . $debug_info . '
            <p><a href="' . home_url() . '" style="text-decoration: none; background: #0073aa; color: white; padding: 10px 20px; border-radius: 3px; display: inline-block;">← Return to website</a></p>',
            'Newsletter Access Required',
            array('response' => 403)
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