<?php
/**
 * Newsletter Admin Class
 * File: includes/class-newsletter-admin.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_newsletter_send_test', array($this, 'handle_send_test_email'));
        add_action('wp_ajax_newsletter_send_campaign', array($this, 'handle_send_campaign'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=newsletter_campaign',
            'Contacts',
            'Contacts',
            'manage_options',
            'newsletter-contacts',
            array($this, 'contacts_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=newsletter_campaign',
            'Audiences',
            'Audiences',
            'manage_options',
            'newsletter-audiences',
            array($this, 'audiences_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'newsletter') !== false) {
            wp_enqueue_style(
                'newsletter-admin',
                NEWSLETTER_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                NEWSLETTER_PLUGIN_VERSION
            );
        }
    }
    
    public function contacts_page() {
        echo '<div class="wrap">';
        echo '<h1>Newsletter Contacts</h1>';
        echo '<p>Contact management functionality will go here.</p>';
        
        // Display current contacts
        global $wpdb;
        $contacts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}newsletter_contacts ORDER BY name");
        
        if ($contacts) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Created</th></tr></thead>';
            echo '<tbody>';
            foreach ($contacts as $contact) {
                echo '<tr>';
                echo '<td>' . esc_html($contact->name) . '</td>';
                echo '<td>' . esc_html($contact->email) . '</td>';
                echo '<td>' . esc_html($contact->status) . '</td>';
                echo '<td>' . esc_html($contact->created_at) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
    
    public function audiences_page() {
        echo '<div class="wrap">';
        echo '<h1>Newsletter Audiences</h1>';
        echo '<p>Audience management functionality will go here.</p>';
        
        // Display current audiences
        $audiences = Newsletter_Database::get_audiences();
        
        if ($audiences) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Name</th><th>Description</th><th>Contacts</th><th>Created</th></tr></thead>';
            echo '<tbody>';
            foreach ($audiences as $audience) {
                echo '<tr>';
                echo '<td>' . esc_html($audience->name) . '</td>';
                echo '<td>' . esc_html($audience->description) . '</td>';
                echo '<td>' . esc_html($audience->contact_count) . '</td>';
                echo '<td>' . esc_html($audience->created_at) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
    
    /**
     * Handle test email sending
     */
    public function handle_send_test_email() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_send_test')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $test_email = sanitize_email($_POST['test_email']);
        
        if (!$test_email) {
            wp_send_json_error('Invalid email address');
            return;
        }
        
        // Send test email
        $result = Newsletter_Email_Sender::send_test_email($campaign_id, $test_email);
        
        if ($result) {
            wp_send_json_success('Test email sent successfully');
        } else {
            wp_send_json_error('Failed to send test email');
        }
    }
    
    /**
     * Handle campaign sending
     */
    public function handle_send_campaign() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_send_campaign')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        // Get audience
        $audience_id = get_post_meta($campaign_id, '_newsletter_audience', true);
        if (!$audience_id) {
            wp_send_json_error('No audience selected');
            return;
        }
        
        // Check if already sent
        $campaign_status = get_post_meta($campaign_id, '_newsletter_campaign_status', true);
        if ($campaign_status === 'sent') {
            wp_send_json_error('Campaign already sent');
            return;
        }
        
        // Send campaign
        $result = Newsletter_Email_Sender::send_campaign($campaign_id, $audience_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'sent_count' => $result['sent_count']
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }
}