<?php
/**
 * Replace the Newsletter_Public class entirely with this simpler approach
 * File: includes/class-newsletter-public.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_Public {
    
    public function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_newsletter_requests'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
    }
    
    /**
     * Add custom rewrite rules for newsletter previews
     */
    public function add_rewrite_rules() {
        // Rule for secure preview with token
        add_rewrite_rule(
            '^newsletter/([^/]+)/preview/([^/]+)/?$',
            'index.php?newsletter_slug=$matches[1]&preview_token=$matches[2]',
            'top'
        );
        
        // Rule for published newsletters (normal)
        add_rewrite_rule(
            '^newsletter/([^/]+)/?$',
            'index.php?newsletter_slug=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'newsletter_slug';
        $vars[] = 'preview_token';
        return $vars;
    }
    
    /**
     * Handle newsletter requests
     */
    public function handle_newsletter_requests() {
        $newsletter_slug = get_query_var('newsletter_slug');
        
        if (!$newsletter_slug) {
            return;
        }
        
        $preview_token = get_query_var('preview_token');
        
        // Find the campaign by slug
        $args = array(
            'name' => $newsletter_slug,
            'post_type' => 'newsletter_campaign',
            'post_status' => $preview_token ? array('publish', 'draft') : 'publish',
            'numberposts' => 1
        );
        
        $campaign = get_posts($args);
        
        if (empty($campaign)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }
        
        $campaign = $campaign[0];
        
        // If it's a draft, validate the preview token
        if ($campaign->post_status === 'draft') {
            if (!$preview_token) {
                wp_die('This newsletter is not published yet.', 'Newsletter Not Available', array('response' => 403));
            }
            
            $is_valid = Newsletter_Email_Sender::validate_preview_hash($campaign->ID, $preview_token);
            
            if (!$is_valid) {
                wp_die('Invalid preview token.', 'Access Denied', array('response' => 403));
            }
        }
        
        // Set up the global post object
        global $post;
        $post = $campaign;
        setup_postdata($post);
        
        // Load the template
        $template_path = NEWSLETTER_PLUGIN_PATH . 'templates/single-newsletter-campaign.php';
        
        if (file_exists($template_path)) {
            include $template_path;
            exit;
        }
    }
    
    public function enqueue_public_assets() {
        if (get_query_var('newsletter_slug')) {
            wp_enqueue_style(
                'newsletter-email-preview',
                NEWSLETTER_PLUGIN_URL . 'assets/css/email-preview.css',
                array(),
                NEWSLETTER_PLUGIN_VERSION
            );
        }
    }
}