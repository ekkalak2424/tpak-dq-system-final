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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
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
        }
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
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
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $options = get_option('tpak_dq_system_options', array());
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Import page
     */
    public function import_page() {
        $api_handler = new TPAK_DQ_API_Handler();
        $cron_handler = new TPAK_DQ_Cron();
        
        if (isset($_POST['manual_import'])) {
            $result = $cron_handler->manual_import();
        }
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/import.php';
    }
    
    /**
     * Users page
     */
    public function users_page() {
        $roles = new TPAK_DQ_Roles();
        $users = $roles->get_all_verification_users();
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/users.php';
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'tpak_dq_settings')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $options = get_option('tpak_dq_system_options', array());
        
        $options['limesurvey_url'] = sanitize_url($_POST['limesurvey_url']);
        $options['limesurvey_username'] = sanitize_text_field($_POST['limesurvey_username']);
        $options['limesurvey_password'] = sanitize_text_field($_POST['limesurvey_password']);
        $options['cron_interval'] = sanitize_text_field($_POST['cron_interval']);
        $options['survey_id'] = sanitize_text_field($_POST['survey_id']);
        $options['email_notifications'] = isset($_POST['email_notifications']) ? true : false;
        $options['sampling_percentage'] = intval($_POST['sampling_percentage']);
        
        update_option('tpak_dq_system_options', $options);
        
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
} 