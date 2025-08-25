<?php
/**
 * Plugin Name: Newsletter Plugin
 * Description: A simple newsletter plugin with ACF flexible content
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NEWSLETTER_PLUGIN_VERSION', '1.0.0');
define('NEWSLETTER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NEWSLETTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEWSLETTER_PLUGIN_INC', NEWSLETTER_PLUGIN_PATH . 'includes/');

/**
 * Main Plugin Class - Just for bootstrapping
 */
class Newsletter_Plugin {
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Check if ACF is active
        if (!function_exists('acf_add_local_field_group')) {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
            return;
        }
        
        // Load all includes
        $this->load_includes();
        
        // Initialize components
        new Newsletter_Database();
        new Newsletter_CPT();
        new Newsletter_ACF_Fields();
        new Newsletter_Admin();
        new Newsletter_Public();
        
    }
    
    private function load_includes() {
        $includes = array(
            'class-newsletter-database.php',
            'class-newsletter-cpt.php',
            'class-newsletter-acf-fields.php',
            'class-newsletter-blocks.php',
            'class-newsletter-email-sender.php',
            'class-newsletter-admin.php',
            'class-newsletter-public.php'
        );
        
        foreach ($includes as $file) {
            $filepath = NEWSLETTER_PLUGIN_INC . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
    }
    
    public function activate() {
        // Load database class and create tables
        require_once NEWSLETTER_PLUGIN_INC . 'class-newsletter-database.php';
        $database = new Newsletter_Database();
        $database->create_tables();
        
        // Load CPT class and register CPT
        require_once NEWSLETTER_PLUGIN_INC . 'class-newsletter-cpt.php';
        $cpt = new Newsletter_CPT();
        $cpt->register_post_type();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function acf_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Newsletter Plugin:</strong> This plugin requires ACF Pro to be installed and activated.';
        echo '</p></div>';
    }
}

// Initialize the plugin
new Newsletter_Plugin();

// Debug function (keep this in main file for easy access)
add_action('admin_init', function() {
    if (isset($_GET['newsletter_debug']) && current_user_can('manage_options')) {
        echo '<div style="background: white; padding: 20px; margin: 20px; border: 2px solid #0073aa;">';
        echo '<h2>Newsletter Plugin Debug Info</h2>';
        
        // Check if CPT is registered
        echo '<h3>Post Types:</h3>';
        $post_types = get_post_types(['public' => true], 'names');
        if (in_array('newsletter_campaign', $post_types)) {
            echo '<p style="color: green;">✓ newsletter_campaign post type is registered</p>';
        } else {
            echo '<p style="color: red;">✗ newsletter_campaign post type NOT found</p>';
        }
        
        // Check database tables
        global $wpdb;
        echo '<h3>Database Tables:</h3>';
        
        $tables_to_check = [
            'newsletter_contacts',
            'newsletter_audiences', 
            'newsletter_audience_contacts'
        ];
        
        foreach ($tables_to_check as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo '<p style="color: green;">✓ ' . $table_name . ' exists (rows: ' . $count . ')</p>';
            } else {
                echo '<p style="color: red;">✗ ' . $table_name . ' does NOT exist</p>';
            }
        }
        
        // Check ACF
        echo '<h3>ACF Status:</h3>';
        if (function_exists('acf_add_local_field_group')) {
            echo '<p style="color: green;">✓ ACF is active and available</p>';
        } else {
            echo '<p style="color: red;">✗ ACF is NOT available</p>';
        }
        
        echo '<p><a href="' . admin_url() . '" class="button button-primary">Back to Admin</a></p>';
        echo '</div>';
    }
});