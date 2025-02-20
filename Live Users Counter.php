<?php
/*
Plugin Name: Real-Time Visitors Counter
Description: Un plugin care arată în timp real câți utilizatori sunt pe pagină și înregistrează IP-urile acestora.
Version: 1.0
Author: Constantinescu Valentin
*/

if (!defined('ABSPATH')) {
    exit; 
}


function rtv_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtv_visitors';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(100) NOT NULL,
        visit_time DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'rtv_create_table');


function rtv_register_visitor() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtv_visitors';
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $wpdb->insert(
        $table_name,
        ['ip_address' => $ip],
        ['%s']
    );
}
add_action('init', 'rtv_register_visitor');


function rtv_count_visitors() {
    session_start();
    if (!isset($_SESSION['rtv_active'])) {
        $_SESSION['rtv_active'] = true;
        update_option('rtv_online_users', get_option('rtv_online_users', 0) + 1);
    }
}
add_action('wp', 'rtv_count_visitors');


function rtv_remove_visitor() {
    session_start();
    if (isset($_SESSION['rtv_active'])) {
        unset($_SESSION['rtv_active']);
        update_option('rtv_online_users', max(0, get_option('rtv_online_users', 1) - 1));
    }
}
add_action('wp_logout', 'rtv_remove_visitor');
add_action('shutdown', 'rtv_remove_visitor');


function rtv_display_counter() {
    $count = get_option('rtv_online_users', 0);
    echo "<div id='rtv-counter' style='position:fixed;bottom:10px;right:10px;background:#000;color:#fff;padding:10px;border-radius:5px;'>$count utilizatori online</div>";
}
add_action('wp_footer', 'rtv_display_counter');


function rtv_create_admin_menu() {
    add_menu_page(
        'Real-Time Visitors',
        'Vizitatori Online',
        'manage_options',
        'rtv-settings',
        'rtv_admin_page',
        'dashicons-admin-users',
        90
    );
}
add_action('admin_menu', 'rtv_create_admin_menu');


function rtv_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtv_visitors';
    $visitors = $wpdb->get_results("SELECT * FROM $table_name ORDER BY visit_time DESC LIMIT 100");
    
    echo "<div class='wrap'><h2>Vizitatori înregistrați</h2><table><tr><th>IP</th><th>Timp</th></tr>";
    foreach ($visitors as $visitor) {
        echo "<tr><td>{$visitor->ip_address}</td><td>{$visitor->visit_time}</td></tr>";
    }
    echo "</table></div>";
}
