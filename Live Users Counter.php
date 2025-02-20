<?php
/**
 * Plugin Name: Real-Time User Counter
 * Plugin URI: https://github.com/Ipadios12
 * Description: Afișează numărul de utilizatori activi pe site în timp real.
 * Version: 1.2
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
            session_id VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (session_id)
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
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $this->table_name WHERE last_active > NOW() - INTERVAL $this->session_timeout SECOND");
        echo json_encode(['count' => $count]);
        wp_die();
    }

    public function track_user() {
        global $wpdb;
        if (!session_id()) {
            session_start();
        }
        $session_id = session_id();
        $ip_address = $_SERVER['REMOTE_ADDR'];

        $wpdb->query($wpdb->prepare(
            "INSERT INTO $this->table_name (session_id, ip_address, last_active) 
             VALUES (%s, %s, NOW()) 
             ON DUPLICATE KEY UPDATE last_active = NOW()",
            $session_id, $ip_address
        ));
    }
}

new RealTimeUserCounter();


file_put_contents(plugin_dir_path(__FILE__) . 'style.css', ".rtuc-counter {position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: #fff; padding: 10px; border-radius: 5px; font-size: 16px; z-index: 9999;}");


file_put_contents(plugin_dir_path(__FILE__) . 'script.js', "jQuery(document).ready(function($) { function updateUserCount() { $.post(rtuc_ajax.ajax_url, {action: 'update_user_count'}, function(response) { $('.rtuc-counter').text('Active Users: ' + JSON.parse(response).count); }); } setInterval(updateUserCount, 5000); if (!$('.rtuc-counter').length) { $('body').append('<div class="rtuc-counter">Active Users: 0</div>'); } updateUserCount(); });");
