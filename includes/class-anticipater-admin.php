<?php
if (!defined('ABSPATH')) {
    exit;
}

class Anticipater_GA4_Admin {
    private $main;
    private $option_name;

    public function __construct($main_instance, $option_name) {
        $this->main = $main_instance;
        $this->option_name = $option_name;

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_admin_actions'], 1);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_anticipater_toggle_event', [$this, 'ajax_toggle_event']);
    }

    public function ajax_toggle_event() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('anticipater_toggle_event', 'nonce');
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
        $enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 0;
        
        $settings = $this->main->get_settings();
        
        if (!isset($settings['events'][$index])) {
            wp_send_json_error('Event not found');
        }
        
        $settings['events'][$index]['enabled'] = $enabled;
        update_option($this->option_name, $settings);
        
        wp_send_json_success(['enabled' => $enabled]);
    }

    public function handle_admin_actions() {
        if (!isset($_GET['page']) || strpos($_GET['page'], 'anticipater') !== 0) {
            return;
        }
        
        $settings = $this->main->get_settings();
        
        // Toggle enabled (GA4 Events page)
        if (isset($_GET['toggle_enabled']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'toggle_enabled')) {
            $settings['enabled'] = empty($settings['enabled']) ? 1 : 0;
            update_option($this->option_name, $settings);
            wp_redirect(admin_url('admin.php?page=anticipater-ga4-events'));
            exit;
        }
        
        // Toggle debug (Event Log page)
        if (isset($_GET['toggle_debug']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'toggle_debug')) {
            $settings['debug_mode'] = empty($settings['debug_mode']) ? 1 : 0;
            update_option($this->option_name, $settings);
            wp_redirect(admin_url('admin.php?page=anticipater-event-log'));
            exit;
        }
        
        // Delete event
        if (isset($_GET['delete_event']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_event')) {
            $delete_index = intval($_GET['delete_event']);
            if (isset($settings['events'][$delete_index])) {
                array_splice($settings['events'], $delete_index, 1);
                update_option($this->option_name, $settings);
            }
            wp_redirect(admin_url('admin.php?page=anticipater-ga4-events'));
            exit;
        }
        
        // Enable all events
        if (isset($_GET['enable_all_events']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'enable_all_events')) {
            foreach ($settings['events'] as &$event) {
                $event['enabled'] = 1;
            }
            update_option($this->option_name, $settings);
            wp_redirect(admin_url('admin.php?page=anticipater-ga4-events'));
            exit;
        }
        
        // Disable all events
        if (isset($_GET['disable_all_events']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'disable_all_events')) {
            foreach ($settings['events'] as &$event) {
                $event['enabled'] = 0;
            }
            update_option($this->option_name, $settings);
            wp_redirect(admin_url('admin.php?page=anticipater-ga4-events'));
            exit;
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Anticipater GA4 Events',
            'GA4 Events',
            'manage_options',
            'anticipater-ga4-events',
            [$this, 'render_admin_page'],
            'dashicons-chart-bar',
            100
        );
        
        add_submenu_page(
            'anticipater-ga4-events',
            'Event Log',
            'Event Log',
            'manage_options',
            'anticipater-event-log',
            [$this, 'render_log_page']
        );
        
        add_submenu_page(
            'anticipater-ga4-events',
            'Import / Export',
            'Import / Export',
            'manage_options',
            'anticipater-import-export',
            [$this, 'render_import_export_page']
        );
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name, [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        $sanitized['enabled'] = isset($input['enabled']) ? 1 : 0;
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? 1 : 0;
        $sanitized['events'] = [];
        
        if (isset($input['events']) && is_array($input['events'])) {
            foreach ($input['events'] as $event) {
                if (!empty($event['name'])) {
                    $sanitized['events'][] = [
                        'name' => sanitize_text_field($event['name']),
                        'enabled' => isset($event['enabled']) ? 1 : 0,
                        'type' => sanitize_text_field($event['type']),
                        'selector' => sanitize_text_field($event['selector'] ?? ''),
                        'trigger' => sanitize_text_field($event['trigger'] ?? 'click'),
                        'conditions' => $this->sanitize_conditions($event['conditions'] ?? []),
                        'params' => $this->sanitize_params($event['params'] ?? []),
                    ];
                }
            }
        }
        
        return $sanitized;
    }

    private function sanitize_conditions($conditions) {
        if (!is_array($conditions)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($conditions as $condition) {
            if (!empty($condition['type'])) {
                $sanitized[] = [
                    'type' => sanitize_text_field($condition['type']),
                    'operator' => sanitize_text_field($condition['operator'] ?? '>='),
                    'value' => sanitize_text_field($condition['value'] ?? ''),
                ];
            }
        }
        return $sanitized;
    }

    private function sanitize_params($params) {
        if (!is_array($params)) {
            return [];
        }
        $sanitized = [];
        foreach ($params as $key => $value) {
            $key = sanitize_key($key);
            if (is_array($value)) {
                $sanitized[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        return $sanitized;
    }

    public function enqueue_admin_scripts($hook) {
        $allowed_pages = [
            'toplevel_page_anticipater-ga4-events',
            'ga4-events_page_anticipater-event-log',
            'ga4-events_page_anticipater-import-export'
        ];
        if (!in_array($hook, $allowed_pages)) {
            return;
        }
        
        wp_enqueue_style(
            'anticipater-admin-css',
            plugin_dir_url(dirname(__FILE__)) . 'assets/admin.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'anticipater-admin-js',
            plugin_dir_url(dirname(__FILE__)) . 'assets/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('anticipater-admin-js', 'anticipaterAdmin', [
            'optionName' => $this->option_name,
            'toggleNonce' => wp_create_nonce('anticipater_toggle_event')
        ]);
    }

    public function render_admin_page() {
        $settings = $this->main->get_settings();
        
        // Event detail view
        if (isset($_GET['event'])) {
            $event_name = sanitize_text_field($_GET['event']);
            $this->render_event_config_detail($event_name, $settings);
            return;
        }
        ?>
        <div class="wrap anticipater-admin">
            <h1><span class="dashicons dashicons-chart-bar"></span> Anticipater GA4 Events</h1>
            
            <form method="post" action="options.php" id="anticipater-form">
                <?php settings_fields($this->option_name); ?>
                
                <div class="anticipater-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <label class="anticipater-toggle">
                            <input type="checkbox" id="enabled-toggle" <?php checked($settings['enabled'] ?? 1, 1); ?> onchange="window.location.href='<?php echo admin_url('admin.php?page=anticipater-ga4-events&toggle_enabled=1&_wpnonce=' . wp_create_nonce('toggle_enabled')); ?>'">
                            <span class="slider"></span>
                            <span class="label">Enable GA4 Event Tracking</span>
                        </label>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&enable_all_events=1&_wpnonce=' . wp_create_nonce('enable_all_events')); ?>" class="button button-secondary" style="font-size: 12px;">Enable All</a>
                        <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&disable_all_events=1&_wpnonce=' . wp_create_nonce('disable_all_events')); ?>" class="button button-secondary" style="font-size: 12px;">Disable All</a>
                        <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&event=new_event'); ?>" class="button button-primary">+ Add Event</a>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 50px;">On</th>
                            <th>Event Name</th>
                            <th style="width: 100px;">Type</th>
                            <th style="width: 100px;">Trigger</th>
                            <th style="width: 80px;">Cond.</th>
                            <th style="width: 80px;">Params</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($settings['events'])): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 20px;">No events configured. <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&event=new_event'); ?>">Add your first event</a> or <a href="<?php echo admin_url('admin.php?page=anticipater-import-export'); ?>">import events</a>.</td></tr>
                        <?php else: ?>
                        <?php foreach ($settings['events'] ?? [] as $index => $event): ?>
                        <tr>
                            <td>
                                <label class="anticipater-mini-toggle">
                                    <input type="checkbox" class="event-toggle" data-index="<?php echo $index; ?>" <?php checked($event['enabled'] ?? 0, 1); ?>>
                                    <span class="mini-slider"></span>
                                </label>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&event=' . urlencode($event['name'])); ?>" style="font-weight: 600;">
                                    <?php echo esc_html($event['name']); ?>
                                </a>
                            </td>
                            <td><code style="font-size: 11px;"><?php echo esc_html($event['type']); ?></code></td>
                            <td><code style="font-size: 11px;"><?php echo esc_html($event['trigger'] ?? '-'); ?></code></td>
                            <td style="text-align: center;"><?php echo count($event['conditions'] ?? []); ?></td>
                            <td style="text-align: center;"><?php echo count($event['params'] ?? []); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&event=' . urlencode($event['name'])); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&delete_event=' . $index . '&_wpnonce=' . wp_create_nonce('delete_event')); ?>" 
                                   class="button button-small" style="color: #d63638;" onclick="return confirm('Delete this event?');">×</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }

    private function render_event_config_detail($event_name, $settings) {
        $event = null;
        $event_index = null;
        foreach ($settings['events'] ?? [] as $index => $e) {
            if ($e['name'] === $event_name) {
                $event = $e;
                $event_index = $index;
                break;
            }
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'anticipater_event_log';
        $log_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE event_name = %s", $event_name));
        $recent_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE event_name = %s ORDER BY created_at DESC LIMIT 5",
            $event_name
        ));
        
        $is_new = ($event === null);
        if ($is_new) {
            $event = ['name' => $event_name, 'enabled' => 0, 'type' => 'automatic', 'trigger' => 'pageload', 'selector' => '', 'conditions' => [], 'params' => []];
            $event_index = count($settings['events'] ?? []);
        }
        ?>
        <div class="wrap anticipater-admin">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events'); ?>" style="text-decoration: none;">← GA4 Events</a>
            </h1>
            
            <form method="post" action="options.php" id="event-detail-form">
                <?php settings_fields($this->option_name); ?>
                
                <?php foreach ($settings['events'] ?? [] as $i => $e): if ($i !== $event_index): ?>
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][name]" value="<?php echo esc_attr($e['name']); ?>">
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][enabled]" value="<?php echo $e['enabled'] ? '1' : '0'; ?>">
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][type]" value="<?php echo esc_attr($e['type']); ?>">
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][trigger]" value="<?php echo esc_attr($e['trigger'] ?? ''); ?>">
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][selector]" value="<?php echo esc_attr($e['selector'] ?? ''); ?>">
                <?php if (!empty($e['conditions'])): foreach ($e['conditions'] as $ci => $c): ?>
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][conditions][<?php echo $ci; ?>][type]" value="<?php echo esc_attr($c['type']); ?>">
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][conditions][<?php echo $ci; ?>][operator]" value="<?php echo esc_attr($c['operator']); ?>">
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][conditions][<?php echo $ci; ?>][value]" value="<?php echo esc_attr($c['value']); ?>">
                <?php endforeach; endif; ?>
                <?php if (!empty($e['params'])): foreach ($e['params'] as $pk => $pv): ?>
                <input type="hidden" name="<?php echo $this->option_name; ?>[events][<?php echo $i; ?>][params][<?php echo esc_attr($pk); ?>]" value="<?php echo esc_attr($pv); ?>">
                <?php endforeach; endif; ?>
                <?php endif; endforeach; ?>
                
                <input type="hidden" name="<?php echo $this->option_name; ?>[enabled]" value="<?php echo $settings['enabled'] ?? 1; ?>">
                <input type="hidden" name="<?php echo $this->option_name; ?>[debug_mode]" value="<?php echo $settings['debug_mode'] ?? 0; ?>">
                
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; display: flex; align-items: center; gap: 15px;">
                            <input type="text" name="<?php echo $this->option_name; ?>[events][<?php echo $event_index; ?>][name]" 
                                   value="<?php echo esc_attr($event['name']); ?>" 
                                   style="font-size: 18px; font-weight: 600; color: #2271b1; border: 1px solid #ddd; padding: 5px 10px;">
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 14px; font-weight: normal;">
                                <input type="checkbox" name="<?php echo $this->option_name; ?>[events][<?php echo $event_index; ?>][enabled]" value="1" <?php checked($event['enabled'] ?? 0, 1); ?>>
                                Enabled
                            </label>
                        </h2>
                        <div>
                            <?php submit_button('Save Event', 'primary', 'submit', false); ?>
                            <?php if (!$is_new): ?>
                            <a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&delete_event=' . $event_index . '&_wpnonce=' . wp_create_nonce('delete_event')); ?>" 
                               class="button button-secondary" style="color: #d63638;" 
                               onclick="return confirm('Delete this event?');">Delete</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th style="width: 150px;">Type</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[events][<?php echo $event_index; ?>][type]">
                                    <option value="automatic" <?php selected($event['type'] ?? '', 'automatic'); ?>>Automatic</option>
                                    <option value="click" <?php selected($event['type'] ?? '', 'click'); ?>>Click</option>
                                    <option value="scroll" <?php selected($event['type'] ?? '', 'scroll'); ?>>Scroll</option>
                                    <option value="video" <?php selected($event['type'] ?? '', 'video'); ?>>Video</option>
                                    <option value="form" <?php selected($event['type'] ?? '', 'form'); ?>>Form</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Trigger</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[events][<?php echo $event_index; ?>][trigger]">
                                    <option value="pageload" <?php selected($event['trigger'] ?? '', 'pageload'); ?>>Page Load</option>
                                    <option value="click" <?php selected($event['trigger'] ?? '', 'click'); ?>>Click</option>
                                    <option value="scroll" <?php selected($event['trigger'] ?? '', 'scroll'); ?>>Scroll</option>
                                    <option value="time" <?php selected($event['trigger'] ?? '', 'time'); ?>>Time on Page</option>
                                    <option value="behavior" <?php selected($event['trigger'] ?? '', 'behavior'); ?>>User Behavior</option>
                                    <option value="play" <?php selected($event['trigger'] ?? '', 'play'); ?>>Video Play</option>
                                    <option value="progress" <?php selected($event['trigger'] ?? '', 'progress'); ?>>Video Progress</option>
                                    <option value="ended" <?php selected($event['trigger'] ?? '', 'ended'); ?>>Video End</option>
                                    <option value="wpcf7" <?php selected($event['trigger'] ?? '', 'wpcf7'); ?>>CF7 Submit</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Selector</th>
                            <td>
                                <input type="text" name="<?php echo $this->option_name; ?>[events][<?php echo $event_index; ?>][selector]" 
                                       value="<?php echo esc_attr($event['selector'] ?? ''); ?>" class="regular-text" placeholder=".btn, #element, a[href*='contact']">
                            </td>
                        </tr>
                        <tr>
                            <th>Conditions</th>
                            <td>
                                <div id="conditions-list">
                                    <?php foreach ($event['conditions'] ?? [] as $ci => $cond): ?>
                                    <div class="condition-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                        <select name="<?php echo $this->option_name; ?>[events][<?php echo $event_index; ?>][conditions][<?php echo $ci; ?>][type]">
                                            <optgroup label="Engagement">
                                                <option value="page_views" <?php selected($cond['type'], 'page_views'); ?>>Page Views</option>
                                                <option value="time_on_site" <?php selected($cond['type'], 'time_on_site'); ?>>Time on Site</option>
                                                <option value="scroll_depth" <?php selected($cond['type'], 'scroll_depth'); ?>>Scroll Depth</option>
                                            </optgroup>
                                            <optgroup label="User">
                                                <option value="session_count" <?php selected($cond['type'], 'session_count'); ?>>Session Count</option>
                                                <option value="device_type" <?php selected($cond['type'], 'device_type'); ?>>Device Type</option>
                                                <option value="traffic_source" <?php selected($cond['type'], 'traffic_source'); ?>>Traffic Source</option>
                                            </optgroup>
                                        </select>
                                        <select name="<?php echo $this->option_name; ?>[events][<?php echo $event_index; ?>][conditions][<?php echo $ci; ?>][operator]">
                                            <option value=">=" <?php selected($cond['operator'], '>='); ?>>>=</option>
                                            <option value="<=" <?php selected($cond['operator'], '<='); ?>><=</option>
                                            <option value="==" <?php selected($cond['operator'], '=='); ?>>=</option>
                                            <option value="!=" <?php selected($cond['operator'], '!='); ?>>!=</option>
                                        </select>
                                        <input type="text" name="<?php echo $this->option_name; ?>[events][<?php echo $event_index; ?>][conditions][<?php echo $ci; ?>][value]" 
                                               value="<?php echo esc_attr($cond['value']); ?>" style="width: 100px;">
                                        <button type="button" class="button remove-condition" onclick="this.parentElement.remove();">×</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button" id="add-condition">+ Add Condition</button>
                            </td>
                        </tr>
                        <tr>
                            <th>Parameters</th>
                            <td>
                                <div id="params-list">
                                    <?php foreach ($event['params'] ?? [] as $pk => $pv): ?>
                                    <div class="param-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                        <input type="text" class="param-key" value="<?php echo esc_attr($pk); ?>" placeholder="key" style="width: 150px;">
                                        <input type="text" class="param-value" value="<?php echo esc_attr($pv); ?>" placeholder="value" style="width: 250px;">
                                        <button type="button" class="button remove-param" onclick="this.parentElement.remove();">×</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button" id="add-param">+ Add Parameter</button>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php if ($log_count > 0): ?>
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px;">
                    <h3 style="margin-top: 0;">Recent Activity <span style="font-weight: normal; color: #666;">(<?php echo number_format($log_count); ?> total)</span></h3>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th style="width: 150px;">Time</th>
                                <th>Event Data</th>
                                <th style="width: 200px;">Page URL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><code style="font-size: 11px;"><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_at))); ?></code></td>
                                <td><pre style="margin: 0; font-size: 11px; max-height: 60px; overflow: auto;"><?php 
                                    $data = stripslashes($log->event_data);
                                    $decoded = json_decode($data, true);
                                    if (is_array($decoded)) {
                                        unset($decoded['event']);
                                        echo empty($decoded) ? '-' : esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                    } else {
                                        echo '-';
                                    }
                                ?></pre></td>
                                <td style="font-size: 11px; word-break: break-all;"><?php echo esc_html(parse_url($log->page_url, PHP_URL_PATH)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($log_count > 5): ?>
                    <p style="margin-top: 10px;">
                        <a href="<?php echo admin_url('admin.php?page=anticipater-event-log&filter_event=' . urlencode($event_name)); ?>">View all <?php echo number_format($log_count); ?> logs →</a>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <script>
        jQuery(function($) {
            var eventIndex = <?php echo $event_index; ?>;
            var optionName = '<?php echo $this->option_name; ?>';
            var conditionCount = <?php echo count($event['conditions'] ?? []); ?>;
            
            $('#add-condition').on('click', function() {
                var html = '<div class="condition-row" style="display: flex; gap: 10px; margin-bottom: 10px;">' +
                    '<select name="' + optionName + '[events][' + eventIndex + '][conditions][' + conditionCount + '][type]">' +
                    '<optgroup label="Engagement"><option value="page_views">Page Views</option><option value="time_on_site">Time on Site</option><option value="scroll_depth">Scroll Depth</option></optgroup>' +
                    '<optgroup label="User"><option value="session_count">Session Count</option><option value="device_type">Device Type</option><option value="traffic_source">Traffic Source</option></optgroup></select>' +
                    '<select name="' + optionName + '[events][' + eventIndex + '][conditions][' + conditionCount + '][operator]">' +
                    '<option value=">=">>=</option><option value="<="><=</option><option value="==">=</option><option value="!=">!=</option></select>' +
                    '<input type="text" name="' + optionName + '[events][' + eventIndex + '][conditions][' + conditionCount + '][value]" style="width: 100px;">' +
                    '<button type="button" class="button remove-condition" onclick="this.parentElement.remove();">×</button></div>';
                $('#conditions-list').append(html);
                conditionCount++;
            });
            
            $('#add-param').on('click', function() {
                var html = '<div class="param-row" style="display: flex; gap: 10px; margin-bottom: 10px;">' +
                    '<input type="text" class="param-key" placeholder="key" style="width: 150px;">' +
                    '<input type="text" class="param-value" placeholder="value" style="width: 250px;">' +
                    '<button type="button" class="button remove-param" onclick="this.parentElement.remove();">×</button></div>';
                $('#params-list').append(html);
            });
            
            $('#event-detail-form').on('submit', function() {
                $('#params-list .param-row').each(function(i) {
                    var key = $(this).find('.param-key').val();
                    var value = $(this).find('.param-value').val();
                    if (key) {
                        $(this).find('.param-key').attr('name', optionName + '[events][' + eventIndex + '][params][' + key + ']').val(value);
                        $(this).find('.param-value').remove();
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function render_log_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'anticipater_event_log';
        $settings = $this->main->get_settings();
                
        $per_page = 50;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        $filter_event = isset($_GET['filter_event']) ? sanitize_text_field($_GET['filter_event']) : '';
        
        $where = $filter_event ? $wpdb->prepare(" WHERE event_name = %s", $filter_event) : '';
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name" . $where);
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name" . $where . " ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
        
        $total_pages = ceil($total / $per_page);
        ?>
        <div class="wrap anticipater-admin">
            <h1><span class="dashicons dashicons-list-view"></span> Event Log</h1>
            
            <div class="anticipater-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <label class="anticipater-toggle">
                        <input type="checkbox" id="debug-toggle" <?php checked(!empty($settings['debug_mode'])); ?> onchange="window.location.href='<?php echo admin_url('admin.php?page=anticipater-event-log&toggle_debug=1&_wpnonce=' . wp_create_nonce('toggle_debug')); ?>'">
                        <span class="slider debug"></span>
                        <span class="label">Debug Mode</span>
                    </label>
                    <?php if (empty($settings['debug_mode'])): ?>
                    <span style="color: #999; font-size: 12px;">Events are not being logged</span>
                    <?php endif; ?>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <select id="filter-event" style="min-width: 150px;">
                        <option value="">All Events</option>
                        <?php
                        $event_names = $wpdb->get_col("SELECT DISTINCT event_name FROM $table_name ORDER BY event_name");
                        $current_filter = isset($_GET['filter_event']) ? sanitize_text_field($_GET['filter_event']) : '';
                        foreach ($event_names as $name): ?>
                        <option value="<?php echo esc_attr($name); ?>" <?php selected($current_filter, $name); ?>><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filter-condition" style="min-width: 180px;">
                        <option value="">Filter by Data Field</option>
                        <optgroup label="Engagement">
                            <option value="page_views">Page Views</option>
                            <option value="time_on_site">Time on Site</option>
                            <option value="time_on_page">Time on Page</option>
                            <option value="scroll_depth">Scroll Depth</option>
                            <option value="engagement_time">Engagement Time</option>
                        </optgroup>
                        <optgroup label="User">
                            <option value="session_count">Session Count</option>
                            <option value="device_type">Device Type</option>
                            <option value="traffic_source">Traffic Source</option>
                        </optgroup>
                        <optgroup label="Video">
                            <option value="video_title">Video Title</option>
                            <option value="video_percent">Video Percent</option>
                            <option value="video_url">Video URL</option>
                        </optgroup>
                        <optgroup label="Click">
                            <option value="element_text">Element Text</option>
                            <option value="element_url">Element URL</option>
                            <option value="platform">Platform</option>
                            <option value="file_name">File Name</option>
                            <option value="contact_type">Contact Type</option>
                        </optgroup>
                        <optgroup label="Scroll">
                            <option value="percent_scrolled">Percent Scrolled</option>
                        </optgroup>
                        <optgroup label="Form">
                            <option value="form_id">Form ID</option>
                            <option value="form_name">Form Name</option>
                        </optgroup>
                    </select>
                    <span class="log-count"><?php echo number_format($total); ?> events logged</span>
                    <button type="button" id="clear-log" class="button button-secondary">
                        <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> Clear Log
                    </button>
                    <button type="button" id="refresh-log" class="button button-secondary" onclick="location.reload();">
                        <span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Refresh
                    </button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;" id="event-log-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Time</th>
                        <th style="width: 150px;">
                            <select id="filter-event-col" style="width: 100%; font-weight: normal;">
                                <option value="">Event Name</option>
                                <?php foreach ($event_names as $name): ?>
                                <option value="<?php echo esc_attr($name); ?>" <?php selected($filter_event, $name); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th>
                            <select id="filter-data-col" style="width: 100%; font-weight: normal;">
                                <option value="">Event Data</option>
                                <optgroup label="Engagement">
                                    <option value="page_views">page_views</option>
                                    <option value="time_on_site">time_on_site</option>
                                    <option value="scroll_depth">scroll_depth</option>
                                    <option value="engagement_time">engagement_time</option>
                                    <option value="percent_scrolled">percent_scrolled</option>
                                </optgroup>
                                <optgroup label="User">
                                    <option value="session_count">session_count</option>
                                    <option value="device_type">device_type</option>
                                    <option value="traffic_source">traffic_source</option>
                                </optgroup>
                                <optgroup label="Video">
                                    <option value="video_title">video_title</option>
                                    <option value="video_percent">video_percent</option>
                                    <option value="video_url">video_url</option>
                                </optgroup>
                                <optgroup label="Click">
                                    <option value="element_text">element_text</option>
                                    <option value="element_url">element_url</option>
                                    <option value="platform">platform</option>
                                    <option value="file_name">file_name</option>
                                    <option value="contact_type">contact_type</option>
                                </optgroup>
                                <optgroup label="Form">
                                    <option value="form_id">form_id</option>
                                    <option value="form_name">form_name</option>
                                </optgroup>
                            </select>
                        </th>
                        <th style="width: 200px;">
                            <input type="text" id="filter-url-col" placeholder="Page URL" style="width: 100%; font-weight: normal;">
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 20px;">No events logged yet. Enable debug mode and visit your site.</td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><code><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_at))); ?></code></td>
                        <td><a href="<?php echo admin_url('admin.php?page=anticipater-ga4-events&event=' . urlencode($log->event_name)); ?>" style="color: #2271b1; font-weight: 600; text-decoration: none;"><?php echo esc_html($log->event_name); ?></a></td>
                        <td><pre style="margin: 0; font-size: 11px; max-height: 100px; overflow: auto;"><?php 
                            $data = stripslashes($log->event_data);
                            $decoded = json_decode($data, true);
                            if (is_array($decoded)) {
                                unset($decoded['event']);
                                if (empty($decoded)) {
                                    echo '-';
                                } else {
                                    echo esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                }
                            } else {
                                echo '-';
                            }
                        ?></pre></td>
                        <td style="word-break: break-all; font-size: 11px;"><?php echo esc_html(parse_url($log->page_url, PHP_URL_PATH)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total); ?> items</span>
                    <span class="pagination-links">
                        <?php if ($page > 1): ?>
                        <a class="prev-page button" href="<?php echo add_query_arg('paged', $page - 1); ?>">‹</a>
                        <?php endif; ?>
                        <span class="paging-input"><?php echo $page; ?> of <?php echo $total_pages; ?></span>
                        <?php if ($page < $total_pages): ?>
                        <a class="next-page button" href="<?php echo add_query_arg('paged', $page + 1); ?>">›</a>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php wp_nonce_field('anticipater_clear_log', 'anticipater_clear_log_nonce'); ?>
        </div>
        
        <script>
        jQuery(function($) {
            $('#clear-log').on('click', function() {
                if (!confirm('Clear all logged events?')) return;
                $.post(ajaxurl, {
                    action: 'anticipater_clear_log',
                    nonce: $('#anticipater_clear_log_nonce').val()
                }, function() {
                    location.reload();
                });
            });
            
            function filterTable() {
                var eventFilter = $('#filter-event-col').val().toLowerCase();
                var dataFilter = $('#filter-data-col').val();
                var urlFilter = $('#filter-url-col').val().toLowerCase();
                
                $('#event-log-table tbody tr').each(function() {
                    var $row = $(this);
                    var eventName = $row.find('td:eq(1)').text().toLowerCase();
                    var eventData = $row.find('td:eq(2)').text();
                    var pageUrl = $row.find('td:eq(3)').text().toLowerCase();
                    
                    var show = true;
                    if (eventFilter && eventName.indexOf(eventFilter) === -1) show = false;
                    if (dataFilter && eventData.indexOf('"' + dataFilter + '"') === -1) show = false;
                    if (urlFilter && pageUrl.indexOf(urlFilter) === -1) show = false;
                    
                    $row.toggle(show);
                });
            }
            
            $('#filter-event-col, #filter-data-col').on('change', filterTable);
            $('#filter-url-col').on('input', filterTable);
            
            $('#filter-event, #filter-condition').on('change', function() {
                var $this = $(this);
                if ($this.attr('id') === 'filter-event') {
                    $('#filter-event-col').val($this.val());
                } else {
                    $('#filter-data-col').val($this.val());
                }
                filterTable();
            });
        });
        </script>
        <?php
    }

    public function render_import_export_page() {
        ?>
        <div class="wrap anticipater-admin">
            <h1><span class="dashicons dashicons-database"></span> Import / Export Events</h1>
            
            <div class="anticipater-import-export" style="margin-top: 0;">
                <h2>Export Events</h2>
                <p style="color: var(--anticipater-text-muted); margin-bottom: 16px;">Download all your event configurations as a JSON file for backup or migration.</p>
                <button type="button" id="export-events" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span> Export Events
                </button>
                <?php wp_nonce_field('anticipater_export', 'anticipater_export_nonce'); ?>
            </div>
            
            <div class="anticipater-import-export">
                <h2>Import Events</h2>
                <p style="color: var(--anticipater-text-muted); margin-bottom: 16px;">Import event configurations from a JSON file. This will replace all existing events.</p>
                <div id="import-area" style="display: block; margin-top: 0; padding: 0; background: none; border: none;">
                    <textarea id="import-json" rows="12" class="large-text" placeholder="Paste your JSON configuration here..."></textarea>
                    <p style="margin-top: 16px;">
                        <button type="button" id="do-import" class="button button-primary">
                            <span class="dashicons dashicons-upload"></span> Import Events
                        </button>
                    </p>
                </div>
                <?php wp_nonce_field('anticipater_import', 'anticipater_import_nonce'); ?>
            </div>
            
            <div class="anticipater-import-export">
                <h2>Default Events Template</h2>
                <p style="color: var(--anticipater-text-muted); margin-bottom: 16px;">Load the default Purezza events configuration as a starting point.</p>
                <button type="button" id="load-defaults" class="button button-secondary">
                    <span class="dashicons dashicons-admin-page"></span> Load Default Template
                </button>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            $('#export-events').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { 
                        action: 'anticipater_export_events',
                        nonce: $('#anticipater_export_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            var json = JSON.stringify(response.data, null, 2);
                            var blob = new Blob([json], { type: 'application/json' });
                            var url = URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = 'anticipater-events-' + new Date().toISOString().slice(0,10) + '.json';
                            a.click();
                            URL.revokeObjectURL(url);
                        }
                    }
                });
            });
            
            $('#do-import').on('click', function() {
                var json = $('#import-json').val().trim();
                if (!json) {
                    alert('Please paste JSON data');
                    return;
                }
                
                try {
                    JSON.parse(json);
                } catch(e) {
                    alert('Invalid JSON format');
                    return;
                }
                
                if (!confirm('This will replace all existing events. Continue?')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'anticipater_import_events',
                        nonce: $('#anticipater_import_nonce').val(),
                        import_data: json
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Imported ' + response.data.count + ' events successfully!');
                            $('#import-json').val('');
                        } else {
                            alert('Import failed: ' + response.data);
                        }
                    }
                });
            });
            
            $('#load-defaults').on('click', function() {
                var defaults = <?php 
                    $json_file = plugin_dir_path(dirname(__FILE__)) . 'imports/purezza-events.json';
                    if (file_exists($json_file)) {
                        echo file_get_contents($json_file);
                    } else {
                        echo '{"events":[]}';
                    }
                ?>;
                $('#import-json').val(JSON.stringify(defaults, null, 2));
            });
        });
        </script>
        <?php
    }
}
