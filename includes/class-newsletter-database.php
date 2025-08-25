<?php
/**
 * Newsletter Database Class
 * File: includes/class-newsletter-database.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_Database {
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Contacts table
        $contacts_table = $wpdb->prefix . 'newsletter_contacts';
        $contacts_sql = "CREATE TABLE $contacts_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";
        
        // Audiences table
        $audiences_table = $wpdb->prefix . 'newsletter_audiences';
        $audiences_sql = "CREATE TABLE $audiences_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Audience contacts relationship table
        $audience_contacts_table = $wpdb->prefix . 'newsletter_audience_contacts';
        $audience_contacts_sql = "CREATE TABLE $audience_contacts_table (
            audience_id int(11) NOT NULL,
            contact_id int(11) NOT NULL,
            PRIMARY KEY (audience_id, contact_id),
            KEY audience_id (audience_id),
            KEY contact_id (contact_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($contacts_sql);
        dbDelta($audiences_sql);
        dbDelta($audience_contacts_sql);
        
        // Insert sample data
        $this->insert_sample_data();
    }
    
    private function insert_sample_data() {
        global $wpdb;
        
        $contacts_table = $wpdb->prefix . 'newsletter_contacts';
        $audiences_table = $wpdb->prefix . 'newsletter_audiences';
        $audience_contacts_table = $wpdb->prefix . 'newsletter_audience_contacts';
        
        // Check if sample data already exists
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $contacts_table");
        if ($existing > 0) {
            return;
        }
        
        // Insert sample contacts
        $wpdb->insert($contacts_table, array(
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active'
        ));
        $john_id = $wpdb->insert_id;
        
        $wpdb->insert($contacts_table, array(
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'status' => 'active'
        ));
        $jane_id = $wpdb->insert_id;
        
        $wpdb->insert($contacts_table, array(
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'status' => 'active'
        ));
        $bob_id = $wpdb->insert_id;
        
        // Insert sample audiences
        $wpdb->insert($audiences_table, array(
            'name' => 'General Newsletter',
            'description' => 'Main newsletter subscribers'
        ));
        $general_id = $wpdb->insert_id;
        
        $wpdb->insert($audiences_table, array(
            'name' => 'VIP Customers',
            'description' => 'High-value customers'
        ));
        $vip_id = $wpdb->insert_id;
        
        // Assign contacts to audiences
        $wpdb->insert($audience_contacts_table, array('audience_id' => $general_id, 'contact_id' => $john_id));
        $wpdb->insert($audience_contacts_table, array('audience_id' => $general_id, 'contact_id' => $jane_id));
        $wpdb->insert($audience_contacts_table, array('audience_id' => $general_id, 'contact_id' => $bob_id));
        $wpdb->insert($audience_contacts_table, array('audience_id' => $vip_id, 'contact_id' => $jane_id));
    }
    
    public static function get_audiences() {
        global $wpdb;
        
        $audiences_table = $wpdb->prefix . 'newsletter_audiences';
        $audience_contacts_table = $wpdb->prefix . 'newsletter_audience_contacts';
        
        $sql = "
            SELECT a.*, COUNT(ac.contact_id) as contact_count
            FROM $audiences_table a
            LEFT JOIN $audience_contacts_table ac ON a.id = ac.audience_id
            GROUP BY a.id
            ORDER BY a.name
        ";
        
        return $wpdb->get_results($sql);
    }
    
    public static function get_audience_contacts($audience_id) {
        global $wpdb;
        
        $contacts_table = $wpdb->prefix . 'newsletter_contacts';
        $audience_contacts_table = $wpdb->prefix . 'newsletter_audience_contacts';
        
        $sql = $wpdb->prepare("
            SELECT c.*
            FROM $contacts_table c
            INNER JOIN $audience_contacts_table ac ON c.id = ac.contact_id
            WHERE ac.audience_id = %d AND c.status = 'active'
            ORDER BY c.name
        ", $audience_id);
        
        return $wpdb->get_results($sql);
    }
    
    public static function get_audience($audience_id) {
        global $wpdb;
        
        $audiences_table = $wpdb->prefix . 'newsletter_audiences';
        $audience_contacts_table = $wpdb->prefix . 'newsletter_audience_contacts';
        
        $sql = $wpdb->prepare("
            SELECT a.*, COUNT(ac.contact_id) as contact_count
            FROM $audiences_table a
            LEFT JOIN $audience_contacts_table ac ON a.id = ac.audience_id
            WHERE a.id = %d
            GROUP BY a.id
        ", $audience_id);
        
        return $wpdb->get_row($sql);
    }
}