<?php
/**
 * Plugin Name: Anticipater GA4 Events
 * Description: Manage and track GA4 events with an easy-to-use WordPress admin interface
 * Version: 1.0.2
 * Author: Anticipater
 * Author URI: https://anticipater.com
 * License: Proprietary
 * License URI: https://anticipater.com/license
 * 
 * Copyright (c) 2026 Anticipater. All rights reserved.
 * This plugin is proprietary software. Unauthorized copying, modification,
 * distribution, or use of this software is strictly prohibited.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ANTICIPATER_GA4_VERSION', '1.0.2');
define('ANTICIPATER_GA4_UPDATE_URL', 'https://raw.githubusercontent.com/anticipaterdotcom/wordpress-ga4/main/update.json');

class Anticipater_GA4_Events {
    
    private static $instance = null;
    private $option_name = 'anticipater_ga4_events';
    private $admin;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load dependencies
        require_once plugin_dir_path(__FILE__) . 'includes/class-anticipater-admin.php';
        
        // Initialize Admin UI
        $this->admin = new Anticipater_GA4_Admin($this, $this->option_name);
        
        add_action('admin_notices', [$this, 'check_for_updates']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('wp_ajax_anticipater_export_events', [$this, 'ajax_export_events']);
        add_action('wp_ajax_anticipater_import_events', [$this, 'ajax_import_events']);
        add_action('wp_ajax_anticipater_log_event', [$this, 'ajax_log_event']);
        add_action('wp_ajax_nopriv_anticipater_log_event', [$this, 'ajax_log_event']);
        add_action('wp_ajax_anticipater_clear_log', [$this, 'ajax_clear_log']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('site_transient_update_plugins', [$this, 'push_update']);
        
        register_activation_hook(__FILE__, [$this, 'create_log_table']);
    }
    
    // Admin methods moved to includes/class-anticipater-admin.php
    
    public function create_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'anticipater_event_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_name varchar(100) NOT NULL,
            event_data longtext,
            page_url varchar(500),
            user_agent varchar(500),
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_name (event_name),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function ajax_log_event() {
        // Verify nonce for security (even for public actions)
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'anticipater_log_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $settings = $this->get_settings();
        if (empty($settings['debug_mode'])) {
            wp_send_json_error('Debug mode disabled');
        }
        
        // Simple rate limiting: max 60 events per minute per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $transient_key = 'anticipater_rate_limit_' . md5($ip);
        $request_count = get_transient($transient_key) ?: 0;
        
        if ($request_count > 60) {
            wp_send_json_error('Rate limit exceeded');
        }
        
        set_transient($transient_key, $request_count + 1, 60);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'anticipater_event_log';
        
        $event_data = $_POST['event_data'] ?? '[]';
        if (is_array($event_data)) {
            $event_data = wp_json_encode($event_data);
        }
        
        // Validate JSON
        json_decode($event_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $event_data = '[]';
        }
        
        $wpdb->insert($table_name, [
            'event_name' => sanitize_text_field($_POST['event_name'] ?? ''),
            'event_data' => $event_data, // JSON is safe to store as is, if valid
            'page_url' => esc_url_raw($_POST['page_url'] ?? ''),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'ip_address' => sanitize_text_field($ip),
        ]);
        
        wp_send_json_success();
    }
    
    public function ajax_clear_log() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('anticipater_clear_log', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'anticipater_event_log';
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        wp_send_json_success();
    }
    
    public function get_settings() {
        $settings = get_option($this->option_name, []);
        if (empty($settings)) {
            $settings = [
                'enabled' => 1,
                'events' => []
            ];
        }
        return $settings;
    }

    public function ajax_export_events() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('anticipater_export', 'nonce');
        
        $settings = $this->get_settings();
        $export = [
            'plugin' => 'anticipater-ga4-events',
            'version' => ANTICIPATER_GA4_VERSION,
            'exported' => date('Y-m-d H:i:s'),
            'events' => $settings['events'] ?? []
        ];
        
        wp_send_json_success($export);
    }
    
    public function ajax_import_events() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('anticipater_import', 'nonce');
        
        $json = isset($_POST['import_data']) ? stripslashes($_POST['import_data']) : '';
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['events'])) {
            wp_send_json_error('Invalid import data');
        }
        
        $settings = $this->get_settings();
        $settings['events'] = [];
        
        foreach ($data['events'] as $event) {
            if (!empty($event['name'])) {
                // Basic sanitization for import
                $settings['events'][] = [
                    'name' => sanitize_text_field($event['name']),
                    'enabled' => isset($event['enabled']) ? (int)$event['enabled'] : 1,
                    'type' => sanitize_text_field($event['type'] ?? 'click'),
                    'selector' => sanitize_text_field($event['selector'] ?? ''),
                    'trigger' => sanitize_text_field($event['trigger'] ?? 'click'),
                    // We trust the structure but sanitize values. Admin class handles full sanitization on save.
                    'conditions' => $this->sanitize_recursive($event['conditions'] ?? []),
                    'params' => $this->sanitize_recursive($event['params'] ?? []),
                ];
            }
        }
        
        update_option($this->option_name, $settings);
        wp_send_json_success(['count' => count($settings['events'])]);
    }
    
    private function sanitize_recursive($array) {
        if (!is_array($array)) return [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sanitize_recursive($value);
            } else {
                $array[$key] = sanitize_text_field($value);
            }
        }
        return $array;
    }

    private function get_remote_version_info() {
        $transient_key = 'anticipater_ga4_update_check';
        $remote = get_transient($transient_key);
        
        if ($remote === false) {
            $response = wp_remote_get(ANTICIPATER_GA4_UPDATE_URL, [
                'timeout' => 10,
                'headers' => ['Accept' => 'application/json']
            ]);
            
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return false;
            }
            
            $remote = json_decode(wp_remote_retrieve_body($response));
            set_transient($transient_key, $remote, 12 * HOUR_IN_SECONDS);
        }
        
        return $remote;
    }
    
    public function check_for_updates() {
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        $remote = $this->get_remote_version_info();
        if (!$remote || !isset($remote->version)) {
            return;
        }
        
        if (version_compare(ANTICIPATER_GA4_VERSION, $remote->version, '<')) {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'anticipater') !== false) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Anticipater GA4 Events:</strong> ';
                echo sprintf(
                    'A new version (%s) is available. <a href="%s">Update now</a> or <a href="%s" target="_blank">view changelog</a>.',
                    esc_html($remote->version),
                    esc_url(admin_url('plugins.php')),
                    esc_url($remote->changelog ?? '#')
                );
                echo '</p></div>';
            }
        }
    }
    
    public function push_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote = $this->get_remote_version_info();
        if (!$remote || !isset($remote->version)) {
            return $transient;
        }
        
        $plugin_slug = plugin_basename(__FILE__);
        
        if (version_compare(ANTICIPATER_GA4_VERSION, $remote->version, '<')) {
            $res = new stdClass();
            $res->slug = 'anticipater-ga4-events';
            $res->plugin = $plugin_slug;
            $res->new_version = $remote->version;
            $res->tested = $remote->tested ?? '6.4';
            $res->package = $remote->download_url ?? '';
            $res->url = $remote->homepage ?? 'https://anticipater.com';
            
            $transient->response[$plugin_slug] = $res;
        }
        
        return $transient;
    }
    
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== 'anticipater-ga4-events') {
            return $result;
        }
        
        $remote = $this->get_remote_version_info();
        if (!$remote) {
            return $result;
        }
        
        $info = new stdClass();
        $info->name = 'Anticipater GA4 Events';
        $info->slug = 'anticipater-ga4-events';
        $info->version = $remote->version ?? ANTICIPATER_GA4_VERSION;
        $info->tested = $remote->tested ?? '6.4';
        $info->requires = $remote->requires ?? '5.0';
        $info->author = '<a href="https://anticipater.com">Anticipater</a>';
        $info->author_profile = 'https://anticipater.com';
        $info->download_link = $remote->download_url ?? '';
        $info->trunk = $remote->download_url ?? '';
        $info->last_updated = $remote->last_updated ?? date('Y-m-d');
        $info->sections = [
            'description' => $remote->description ?? 'Manage and track GA4 events with an easy-to-use WordPress admin interface.',
            'changelog' => $remote->changelog_html ?? '<p>See changelog at anticipater.com</p>',
        ];
        
        return $info;
    }

    public function enqueue_frontend_scripts() {
        $settings = $this->get_settings();
        
        if (empty($settings['enabled'])) {
            return;
        }
        
        // Handle UTM persistence server-side via PHP session
        if (!session_id()) {
            session_start();
        }
        
        $utm_params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'];
        foreach ($utm_params as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $_SESSION['anticipater_' . $param] = sanitize_text_field($_GET[$param]);
            }
        }
        
        $utm_data = [];
        foreach ($utm_params as $param) {
            $utm_data[$param] = $_SESSION['anticipater_' . $param] ?? null;
        }
        
        wp_enqueue_script(
            'anticipater-ga4-events',
            plugin_dir_url(__FILE__) . 'assets/frontend.js',
            [],
            '1.0.6',
            true
        );
        
        wp_localize_script('anticipater-ga4-events', 'anticipaterEvents', [
            'events' => array_values(array_filter($settings['events'] ?? [], function($e) {
                return !empty($e['enabled']);
            })),
            'debug' => !empty($settings['debug_mode']),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('anticipater_log_nonce'),
            'utm' => $utm_data
        ]);
    }
}

Anticipater_GA4_Events::get_instance();
