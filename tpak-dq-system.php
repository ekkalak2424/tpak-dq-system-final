<?php
/**
 * Plugin Name: TPAK DQ System
 * Plugin URI: https://tpak.org
 * Description: ระบบจัดการข้อมูลคุณภาพสำหรับ TPAK Survey System - เชื่อมต่อกับ LimeSurvey API และจัดการกระบวนการตรวจสอบ 3 ขั้นตอน
 * Version: 1.0.0
 * Author: TPAK Development Team
 * License: GPL v2 or later
 * Text Domain: tpak-dq-system
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TPAK_DQ_SYSTEM_VERSION', '1.0.0');
define('TPAK_DQ_SYSTEM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPAK_DQ_SYSTEM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPAK_DQ_SYSTEM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main TPAK DQ System Class
 */
class TPAK_DQ_System {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get a single instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Include required files
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-validator.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-roles.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-api-handler.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-cron.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-workflow.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-notifications.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-question-mapper.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-survey-adapter.php';
        
        // Admin files
        if (is_admin()) {
            require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/class-admin-menu.php';
            require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/class-meta-boxes.php';
            require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/class-admin-columns.php';
        }
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize post types and taxonomies
        new TPAK_DQ_Post_Types();
        
        // Ensure options are up to date
        $this->ensure_options_complete();
        
        // Initialize user roles
        new TPAK_DQ_Roles();
        
        // Initialize admin components
        if (is_admin()) {
            new TPAK_DQ_Admin_Menu();
            new TPAK_DQ_Meta_Boxes();
            new TPAK_DQ_Admin_Columns();
        }
        
        // Initialize API handler
        new TPAK_DQ_API_Handler();
        
        // Initialize cron jobs
        new TPAK_DQ_Cron();
        
        // Initialize workflow
        new TPAK_DQ_Workflow();
        
        // Initialize notifications
        new TPAK_DQ_Notifications();
        
        // Check if we need to flush rewrite rules
        if (get_option('tpak_dq_system_flush_rewrite', false)) {
            flush_rewrite_rules();
            delete_option('tpak_dq_system_flush_rewrite');
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'tpak-dq-system',
            false,
            dirname(TPAK_DQ_SYSTEM_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create user roles
        $roles = new TPAK_DQ_Roles();
        $roles->create_roles();
        
        // Create post types and taxonomies
        $post_types = new TPAK_DQ_Post_Types();
        $post_types->register_post_types();
        $post_types->register_taxonomies();
        
        // Flush rewrite rules to ensure custom post types are recognized
        flush_rewrite_rules();
        
        // Set default options
        $this->set_default_options();
        
        // Force refresh of permalinks
        update_option('tpak_dq_system_flush_rewrite', true);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron events
        wp_clear_scheduled_hook('tpak_dq_cron_import_data');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'limesurvey_url' => '',
            'limesurvey_username' => '',
            'limesurvey_password' => '',
            'cron_interval' => 'hourly',
            'survey_id' => '',
            'sampling_percentage' => 70,
            'email_notifications' => true
        );
        
        add_option('tpak_dq_system_options', $default_options);
    }
    
    /**
     * Ensure all required options exist
     */
    private function ensure_options_complete() {
        $options = get_option('tpak_dq_system_options', array());
        $default_options = array(
            'limesurvey_url' => '',
            'limesurvey_username' => '',
            'limesurvey_password' => '',
            'cron_interval' => 'hourly',
            'survey_id' => '',
            'sampling_percentage' => 70,
            'email_notifications' => true
        );
        
        $updated = false;
        foreach ($default_options as $key => $default_value) {
            if (!isset($options[$key])) {
                $options[$key] = $default_value;
                $updated = true;
            }
        }
        
        if ($updated) {
            update_option('tpak_dq_system_options', $options);
        }
    }
}

/**
 * Initialize the plugin
 */
function tpak_dq_system_init() {
    return TPAK_DQ_System::get_instance();
}

// Start the plugin
tpak_dq_system_init(); 