<?php
/**
 * TPAK DQ System - Admin Menu Management
 * 
 * Handles the admin menu and settings pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Admin_Menu {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Ensure post types are registered immediately
        $this->ensure_post_types_registered();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX actions
        add_action('wp_ajax_tpak_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_tpak_import_survey', array($this, 'import_survey_ajax'));
        
        // Debug: Log AJAX action registration
        error_log('TPAK DQ System: AJAX action tpak_test_api registered');
        error_log('TPAK DQ System: AJAX action tpak_import_survey registered');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Ensure post types are registered
        $post_types = new TPAK_DQ_Post_Types();
        $post_types->register_post_types();
        $post_types->register_taxonomies();
        
        // Main menu
        add_menu_page(
            __('TPAK DQ System', 'tpak-dq-system'),
            __('TPAK DQ System', 'tpak-dq-system'),
            'manage_options',
            'tpak-dq-system',
            array($this, 'dashboard_page'),
            'dashicons-clipboard',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'tpak-dq-system',
            __('Dashboard', 'tpak-dq-system'),
            __('Dashboard', 'tpak-dq-system'),
            'manage_options',
            'tpak-dq-system',
            array($this, 'dashboard_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'tpak-dq-system',
            __('Settings', 'tpak-dq-system'),
            __('Settings', 'tpak-dq-system'),
            'manage_options',
            'tpak-dq-settings',
            array($this, 'settings_page')
        );
        
        // Import submenu
        add_submenu_page(
            'tpak-dq-system',
            __('Import Data', 'tpak-dq-system'),
            __('Import Data', 'tpak-dq-system'),
            'manage_options',
            'tpak-dq-import',
            array($this, 'import_page')
        );
        
        // Users submenu
        add_submenu_page(
            'tpak-dq-system',
            __('Manage Users', 'tpak-dq-system'),
            __('Manage Users', 'tpak-dq-system'),
            'manage_options',
            'tpak-dq-users',
            array($this, 'users_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('tpak_dq_settings', 'tpak_dq_system_options');
        
        add_settings_section(
            'tpak_dq_api_settings',
            __('API Settings', 'tpak-dq-system'),
            array($this, 'api_settings_section_callback'),
            'tpak_dq_settings'
        );
        
        add_settings_field(
            'limesurvey_url',
            __('LimeSurvey URL', 'tpak-dq-system'),
            array($this, 'url_field_callback'),
            'tpak_dq_settings',
            'tpak_dq_api_settings'
        );
        
        add_settings_field(
            'limesurvey_username',
            __('Username', 'tpak-dq-system'),
            array($this, 'username_field_callback'),
            'tpak_dq_settings',
            'tpak_dq_api_settings'
        );
        
        add_settings_field(
            'limesurvey_password',
            __('Password', 'tpak-dq-system'),
            array($this, 'password_field_callback'),
            'tpak_dq_settings',
            'tpak_dq_api_settings'
        );
        
        add_settings_section(
            'tpak_dq_cron_settings',
            __('Cron Settings', 'tpak-dq-system'),
            array($this, 'cron_settings_section_callback'),
            'tpak_dq_settings'
        );
        
        add_settings_field(
            'cron_interval',
            __('Import Interval', 'tpak-dq-system'),
            array($this, 'cron_interval_field_callback'),
            'tpak_dq_settings',
            'tpak_dq_cron_settings'
        );
        
        add_settings_field(
            'survey_id',
            __('Survey ID', 'tpak-dq-system'),
            array($this, 'survey_id_field_callback'),
            'tpak_dq_settings',
            'tpak_dq_cron_settings'
        );
        
        add_settings_section(
            'tpak_dq_notification_settings',
            __('Notification Settings', 'tpak-dq-system'),
            array($this, 'notification_settings_section_callback'),
            'tpak_dq_settings'
        );
        
        add_settings_field(
            'email_notifications',
            __('Email Notifications', 'tpak-dq-system'),
            array($this, 'email_notifications_field_callback'),
            'tpak_dq_settings',
            'tpak_dq_notification_settings'
        );
        
        add_settings_field(
            'sampling_percentage',
            __('Sampling Percentage', 'tpak-dq-system'),
            array($this, 'sampling_percentage_field_callback'),
            'tpak_dq_settings',
            'tpak_dq_notification_settings'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'tpak-dq') !== false) {
            wp_enqueue_script('tpak-dq-admin', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), TPAK_DQ_SYSTEM_VERSION, true);
            wp_enqueue_style('tpak-dq-admin', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/css/admin-style.css', array(), TPAK_DQ_SYSTEM_VERSION);
            
            wp_localize_script('tpak-dq-admin', 'tpak_dq_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tpak_workflow_nonce')
            ));
            
            // Debug: Log the nonce
            error_log('TPAK DQ System: Generated nonce: ' . wp_create_nonce('tpak_workflow_nonce'));
        }
    }
    
    /**
     * Import survey via AJAX
     */
    public function import_survey_ajax() {
        // Debug: Log the request
        error_log('TPAK DQ System: AJAX import_survey_ajax called');
        error_log('TPAK DQ System: POST data: ' . print_r($_POST, true));
        
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_import_survey')) {
            error_log('TPAK DQ System: Import survey nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'tpak-dq-system')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            error_log('TPAK DQ System: Import survey permission check failed');
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'tpak-dq-system')));
        }
        
        // Get survey ID
        $survey_id = sanitize_text_field($_POST['survey_id']);
        if (empty($survey_id)) {
            wp_send_json_error(array('message' => __('Survey ID is required', 'tpak-dq-system')));
        }
        
        // Get import type (full or raw)
        $import_type = isset($_POST['import_type']) ? sanitize_text_field($_POST['import_type']) : 'full';
        
        error_log('TPAK DQ System: Importing survey ID: ' . $survey_id . ' with type: ' . $import_type);
        
        // Get API handler
        $api_handler = new TPAK_DQ_API_Handler();
        
        // Import survey data based on type
        if ($import_type === 'raw') {
            $result = $api_handler->import_raw_survey_data($survey_id);
        } else {
            $result = $api_handler->import_survey_data($survey_id);
        }

        if ($result && $result['imported'] > 0) {
            $message = sprintf(__('นำเข้าข้อมูลสำเร็จ %d รายการ', 'tpak-dq-system'), $result['imported']);
            if (!empty($result['errors'])) {
                $message .= ' (' . count($result['errors']) . ' ข้อผิดพลาด)';
            }
            wp_send_json_success(array(
                'message' => $message,
                'imported' => $result['imported'],
                'errors' => count($result['errors'])
            ));
        } else {
            // Check if it's an API issue
            if (!$api_handler->test_connection()) {
                wp_send_json_error(array('message' => __('ไม่สามารถเชื่อมต่อกับ LimeSurvey API ได้ กรุณาตรวจสอบการตั้งค่า', 'tpak-dq-system')));
            } else {
                wp_send_json_error(array('message' => __('ไม่พบข้อมูลในแบบสอบถามนี้ หรือแบบสอบถามไม่มีข้อมูลที่สามารถนำเข้าได้', 'tpak-dq-system')));
            }
        }
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        // Ensure post types are registered before accessing
        $this->ensure_post_types_registered();
        
        $options = get_option('tpak_dq_system_options', array());
        $api_handler = new TPAK_DQ_API_Handler();
        $cron_handler = new TPAK_DQ_Cron();
        
        // Get statistics
        $total_batches = wp_count_posts('verification_batch');
        $pending_a_count = $this->get_posts_by_status('pending_a');
        $pending_b_count = $this->get_posts_by_status('pending_b');
        $pending_c_count = $this->get_posts_by_status('pending_c');
        $finalized_count = $this->get_posts_by_status('finalized') + $this->get_posts_by_status('finalized_by_sampling');
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Ensure post types are registered before accessing
        $this->ensure_post_types_registered();
        
        // Debug: Log POST data
        error_log('TPAK DQ System: POST data in settings_page: ' . print_r($_POST, true));
        
        if (isset($_POST['submit'])) {
            error_log('TPAK DQ System: Submit button clicked, calling save_settings');
            $this->save_settings();
        } else {
            error_log('TPAK DQ System: Submit button not clicked');
        }
        
        $options = get_option('tpak_dq_system_options', array());
        
        // Debug: Log current options
        error_log('TPAK DQ System: Current options in settings page: ' . print_r($options, true));
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Import page
     */
    public function import_page() {
        // Ensure post types are registered before accessing
        $this->ensure_post_types_registered();
        
        $api_handler = new TPAK_DQ_API_Handler();
        $cron_handler = new TPAK_DQ_Cron();
        
        if (isset($_POST['manual_import'])) {
            $result = $cron_handler->manual_import();
        }
        
        if (isset($_POST['fix_data_structure'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'tpak_fix_data_structure')) {
                $survey_id = sanitize_text_field($_POST['survey_id_fix']);
                if (!empty($survey_id)) {
                    $result = $api_handler->fix_existing_data_structure($survey_id);
                    
                    if ($result['fixed'] > 0) {
                        add_settings_error(
                            'tpak_dq_import',
                            'data_structure_fixed',
                            sprintf(__('แก้ไขโครงสร้างข้อมูลสำเร็จ: %d รายการ', 'tpak-dq-system'), $result['fixed']),
                            'updated'
                        );
                    }
                    
                    if (!empty($result['errors'])) {
                        foreach ($result['errors'] as $error) {
                            add_settings_error(
                                'tpak_dq_import',
                                'data_structure_error',
                                $error,
                                'error'
                            );
                        }
                    }
                } else {
                    add_settings_error(
                        'tpak_dq_import',
                        'survey_id_required',
                        __('กรุณาระบุ Survey ID', 'tpak-dq-system'),
                        'error'
                    );
                }
            } else {
                add_settings_error(
                    'tpak_dq_import',
                    'nonce_failed',
                    __('การตรวจสอบความปลอดภัยล้มเหลว', 'tpak-dq-system'),
                    'error'
                );
            }
        }
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/import.php';
    }
    
    /**
     * Users page
     */
    public function users_page() {
        // Ensure post types are registered before accessing
        $this->ensure_post_types_registered();
        
        $roles = new TPAK_DQ_Roles();
        $users = $roles->get_all_verification_users();
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/users.php';
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Debug: Check nonce
        error_log('TPAK DQ System: Nonce verification - _wpnonce: ' . (isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : 'NOT SET'));
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'tpak_dq_settings')) {
            error_log('TPAK DQ System: Nonce verification failed');
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        error_log('TPAK DQ System: Nonce verification passed');
        
        // Debug: Log POST data
        error_log('TPAK DQ System: POST data in save_settings: ' . print_r($_POST, true));
        
        $options = get_option('tpak_dq_system_options', array());
        
        // Debug: Check if form fields exist
        error_log('TPAK DQ System: limesurvey_url in POST: ' . (isset($_POST['limesurvey_url']) ? $_POST['limesurvey_url'] : 'NOT SET'));
        error_log('TPAK DQ System: limesurvey_username in POST: ' . (isset($_POST['limesurvey_username']) ? $_POST['limesurvey_username'] : 'NOT SET'));
        error_log('TPAK DQ System: limesurvey_password in POST: ' . (isset($_POST['limesurvey_password']) ? 'SET' : 'NOT SET'));
        
        $options['limesurvey_url'] = sanitize_url($_POST['limesurvey_url']);
        $options['limesurvey_username'] = sanitize_text_field($_POST['limesurvey_username']);
        $options['limesurvey_password'] = sanitize_text_field($_POST['limesurvey_password']);
        $options['cron_interval'] = sanitize_text_field($_POST['cron_interval']);
        $options['survey_id'] = sanitize_text_field($_POST['survey_id']);
        $options['email_notifications'] = isset($_POST['email_notifications']) ? true : false;
        $options['sampling_percentage'] = intval($_POST['sampling_percentage']);
        
        // Debug: Log options before saving
        error_log('TPAK DQ System: Options to save: ' . print_r($options, true));
        
        $result = update_option('tpak_dq_system_options', $options);
        
        // Debug: Log save result
        error_log('TPAK DQ System: Save result: ' . ($result ? 'Success' : 'Failed'));
        
        // Update cron schedule
        $cron_handler = new TPAK_DQ_Cron();
        $cron_handler->update_cron_settings($options);
        
        add_settings_error(
            'tpak_dq_settings',
            'settings_updated',
            __('Settings saved successfully', 'tpak-dq-system'),
            'updated'
        );
    }
    
    /**
     * Get posts count by status
     */
    private function get_posts_by_status($status) {
        $posts = get_posts(array(
            'post_type' => 'verification_batch',
            'tax_query' => array(
                array(
                    'taxonomy' => 'verification_status',
                    'field' => 'slug',
                    'terms' => $status
                )
            ),
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        return count($posts);
    }
    
    /**
     * Settings field callbacks
     */
    public function api_settings_section_callback() {
        echo '<p>' . __('Configure LimeSurvey API connection settings', 'tpak-dq-system') . '</p>';
    }
    
    public function url_field_callback() {
        $options = get_option('tpak_dq_system_options', array());
        $value = isset($options['limesurvey_url']) ? $options['limesurvey_url'] : '';
        echo '<input type="url" name="limesurvey_url" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter the full URL to your LimeSurvey installation', 'tpak-dq-system') . '</p>';
    }
    
    public function username_field_callback() {
        $options = get_option('tpak_dq_system_options', array());
        $value = isset($options['limesurvey_username']) ? $options['limesurvey_username'] : '';
        echo '<input type="text" name="limesurvey_username" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function password_field_callback() {
        $options = get_option('tpak_dq_system_options', array());
        $value = isset($options['limesurvey_password']) ? $options['limesurvey_password'] : '';
        echo '<input type="password" name="limesurvey_password" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function cron_settings_section_callback() {
        echo '<p>' . __('Configure automatic data import settings', 'tpak-dq-system') . '</p>';
    }
    
    public function cron_interval_field_callback() {
        $options = get_option('tpak_dq_system_options', array());
        $value = isset($options['cron_interval']) ? $options['cron_interval'] : 'hourly';
        
        $intervals = array(
            'hourly' => __('Every hour', 'tpak-dq-system'),
            'twicedaily' => __('Twice daily', 'tpak-dq-system'),
            'daily' => __('Daily', 'tpak-dq-system'),
            'weekly' => __('Weekly', 'tpak-dq-system')
        );
        
        echo '<select name="cron_interval">';
        foreach ($intervals as $key => $label) {
            echo '<option value="' . $key . '"' . selected($value, $key, false) . '>' . $label . '</option>';
        }
        echo '</select>';
    }
    
    public function survey_id_field_callback() {
        $options = get_option('tpak_dq_system_options', array());
        $value = isset($options['survey_id']) ? $options['survey_id'] : '';
        echo '<input type="text" name="survey_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter the Survey ID to import from', 'tpak-dq-system') . '</p>';
    }
    
    public function notification_settings_section_callback() {
        echo '<p>' . __('Configure notification and workflow settings', 'tpak-dq-system') . '</p>';
    }
    
    public function email_notifications_field_callback() {
        $options = get_option('tpak_dq_system_options', array());
        $value = isset($options['email_notifications']) ? $options['email_notifications'] : true;
        echo '<input type="checkbox" name="email_notifications" value="1"' . checked($value, true, false) . ' />';
        echo '<span class="description">' . __('Enable email notifications', 'tpak-dq-system') . '</span>';
    }
    
    public function sampling_percentage_field_callback() {
        $options = get_option('tpak_dq_system_options', array());
        $value = isset($options['sampling_percentage']) ? $options['sampling_percentage'] : 70;
        echo '<input type="number" name="sampling_percentage" value="' . esc_attr($value) . '" min="1" max="100" />';
        echo '<p class="description">' . __('Percentage of batches to finalize by sampling (1-100)', 'tpak-dq-system') . '</p>';
    }
    
    /**
     * Ensure post types are registered
     */
    private function ensure_post_types_registered() {
        if (!post_type_exists('verification_batch')) {
            $post_types = new TPAK_DQ_Post_Types();
            $post_types->register_post_types();
            $post_types->register_taxonomies();
            
            // Force flush rewrite rules
            flush_rewrite_rules();
        }
    }
    
    /**
     * Test API connection via AJAX
     */
    public function test_api_connection() {
        // Debug: Log the request
        error_log('TPAK DQ System: AJAX test_api_connection called');
        error_log('TPAK DQ System: POST data: ' . print_r($_POST, true));
        
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_workflow_nonce')) {
            error_log('TPAK DQ System: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'tpak-dq-system')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            error_log('TPAK DQ System: Permission check failed');
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'tpak-dq-system')));
        }
        
        // Get API handler
        $api_handler = new TPAK_DQ_API_Handler();
        
        // Debug: Check what settings are missing
        $options = get_option('tpak_dq_system_options', array());
        $url = isset($options['limesurvey_url']) ? $options['limesurvey_url'] : '';
        $username = isset($options['limesurvey_username']) ? $options['limesurvey_username'] : '';
        $password = isset($options['limesurvey_password']) ? $options['limesurvey_password'] : '';
        
        error_log('TPAK DQ System: API Settings Debug - URL: ' . ($url ? 'Set' : 'Not set') . ', Username: ' . ($username ? 'Set' : 'Not set') . ', Password: ' . ($password ? 'Set' : 'Not set'));
        
        // Test connection
        if ($api_handler->is_configured()) {
            error_log('TPAK DQ System: API is configured, testing connection');
            if ($api_handler->test_connection()) {
                error_log('TPAK DQ System: API connection successful');
                wp_send_json_success(array('message' => __('API connection successful!', 'tpak-dq-system')));
            } else {
                error_log('TPAK DQ System: API connection failed');
                // Get more detailed error information
                $error_message = __('API connection failed. Please check:', 'tpak-dq-system') . '<br>';
                $error_message .= '- ' . __('URL: ', 'tpak-dq-system') . ($url ? $url : __('Not set', 'tpak-dq-system')) . '<br>';
                $error_message .= '- ' . __('Username: ', 'tpak-dq-system') . ($username ? $username : __('Not set', 'tpak-dq-system')) . '<br>';
                $error_message .= '- ' . __('Password: ', 'tpak-dq-system') . ($password ? __('Set', 'tpak-dq-system') : __('Not set', 'tpak-dq-system'));
                
                wp_send_json_error(array('message' => $error_message));
            }
        } else {
            error_log('TPAK DQ System: API is not configured');
            $error_message = __('API is not configured. Please fill in all required fields:', 'tpak-dq-system') . '<br>';
            $error_message .= '- ' . __('URL: ', 'tpak-dq-system') . ($url ? $url : __('Not set', 'tpak-dq-system')) . '<br>';
            $error_message .= '- ' . __('Username: ', 'tpak-dq-system') . ($username ? $username : __('Not set', 'tpak-dq-system')) . '<br>';
            $error_message .= '- ' . __('Password: ', 'tpak-dq-system') . ($password ? __('Set', 'tpak-dq-system') : __('Not set', 'tpak-dq-system'));
            
            wp_send_json_error(array('message' => $error_message));
        }
    }
} 