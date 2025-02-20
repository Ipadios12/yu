<?php
/**
 * Plugin Name: Real-Time User Counter
 * Description: Displays the number of active users on the website in real-time.
 * Version: 1.5
 * Author: Constantinescu Valentin
 * License: GPL2
 */

 
 if (!defined('ABSPATH')) {
    exit;
}

class RealTimeUserCounter {
    private $table_name;
    private $session_timeout = 30; 

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rtuc_online_users';

        register_activation_hook(__FILE__, [$this, 'create_table']);
        add_action('plugins_loaded', [$this, 'create_table']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_update_user_count', [$this, 'update_user_count']);
        add_action('wp_ajax_nopriv_update_user_count', [$this, 'update_user_count']);
        add_action('init', [$this, 'track_user']);
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_hash VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_hash)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('rtuc-style', plugin_dir_url(__FILE__) . 'style.css');
        wp_enqueue_script('rtuc-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);
        wp_localize_script('rtuc-script', 'rtuc_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    public function add_admin_menu() {
        add_menu_page('Real-Time User Counter', 'User Counter', 'manage_options', 'rtuc_settings', [$this, 'settings_page'], 'dashicons-visibility');
    }

    public function settings_page() {
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $this->table_name WHERE last_active > NOW() - INTERVAL $this->session_timeout SECOND");
        echo "<div class='wrap'><h2>Real-Time User Counter</h2><p>Active Users: <strong>$count</strong></p></div>";
    }

    public function update_user_count() {
        global $wpdb;
        header('Content-Type: application/json');
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $this->table_name WHERE last_active > NOW() - INTERVAL $this->session_timeout SECOND");
        error_log("User count fetched: " . $count);
        if ($count === null) {
            $count = 0;
        }
        echo json_encode(['count' => intval($count)]);
        wp_die();
    }

    public function track_user() {
        global $wpdb;
        $ip_address = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
        if ($ip_address == '::1') {
            $ip_address = '127.0.0.1';
        }
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $user_hash = md5($ip_address . $user_agent);

        error_log("Tracking user: " . $ip_address);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $this->table_name WHERE user_hash = %s",
            $user_hash
        ));

        if ($exists) {
            error_log("Updating existing user: " . $ip_address);
            $wpdb->query($wpdb->prepare(
                "UPDATE $this->table_name SET last_active = NOW() WHERE user_hash = %s",
                $user_hash
            ));
        } else {
            error_log("Inserting new user: " . $ip_address);
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $this->table_name (user_hash, ip_address, user_agent, last_active) VALUES (%s, %s, %s, NOW())",
                $user_hash, $ip_address, $user_agent
            ));
        }
    }
}

new RealTimeUserCounter();