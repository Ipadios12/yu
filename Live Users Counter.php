<?php
/**
 * Plugin Name: Real-Time User Counter
 * Plugin URI: https://github.com/Ipadios12
 * Description: Afișează numărul de utilizatori activi pe site în timp real.
 * Version: 1.2
 * Author: Constantinescu Valentin 
 * License: GPL2
 * 123
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealTimeUserCounter {
    private $session_timeout = 30; 

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_update_user_count', [$this, 'update_user_count']);
        add_action('wp_ajax_nopriv_update_user_count', [$this, 'update_user_count']);
        add_action('init', [$this, 'start_session']);
    }

    public function start_session() {
        if (!session_id()) {
            session_start();
        }
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
        echo '<div class="wrap"><h2>Real-Time User Counter</h2><p>Acest plugin afișează numărul de utilizatori conectați în timp real.</p></div>';
    }

    public function update_user_count() {
        $_SESSION['user_active_' . session_id()] = time();
        $this->cleanup_sessions();
        echo json_encode(['count' => count($_SESSION)]);
        wp_die();
    }

    private function cleanup_sessions() {
        foreach ($_SESSION as $key => $timestamp) {
            if (strpos($key, 'user_active_') === 0 && time() - $timestamp > $this->session_timeout) {
                unset($_SESSION[$key]);
            }
        }
    }
}

new RealTimeUserCounter();


file_put_contents(plugin_dir_path(__FILE__) . 'style.css', ".rtuc-counter {position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: #fff; padding: 10px; border-radius: 5px; font-size: 16px; z-index: 9999;}");


file_put_contents(plugin_dir_path(__FILE__) . 'script.js', "jQuery(document).ready(function($) { function updateUserCount() { $.post(rtuc_ajax.ajax_url, {action: 'update_user_count'}, function(response) { $('.rtuc-counter').text('Utilizatori activi: ' + JSON.parse(response).count); }); } setInterval(updateUserCount, 5000); if (!$('.rtuc-counter').length) { $('body').append('<div class=\"rtuc-counter\">Utilizatori activi: 0</div>'); } updateUserCount(); });");
