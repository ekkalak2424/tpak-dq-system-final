<?php
/**
 * TPAK DQ System - Error Handler
 * 
 * Centralized error handling and logging
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Error_Handler {
    
    private static $instance = null;
    private $errors = array();
    private $debug_mode = false;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        // Hook into WordPress error handling
        add_action('wp_loaded', array($this, 'setup_error_handling'));
    }
    
    /**
     * Setup error handling
     */
    public function setup_error_handling() {
        // Register shutdown function to catch fatal errors
        register_shutdown_function(array($this, 'handle_shutdown'));
        
        // Set custom error handler for non-fatal errors
        if ($this->debug_mode) {
            set_error_handler(array($this, 'handle_error'), E_ALL);
        }
    }
    
    /**
     * Log error with context
     */
    public function log_error($message, $context = array(), $level = 'error') {
        $timestamp = current_time('mysql');
        $user_id = get_current_user_id();
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        $log_entry = array(
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => $user_id,
            'request_uri' => $request_uri,
            'trace' => $this->debug_mode ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : null
        );
        
        // Store in memory for admin display
        $this->errors[] = $log_entry;
        
        // WordPress error log
        $formatted_message = sprintf(
            'TPAK DQ System [%s]: %s',
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $formatted_message .= ' | Context: ' . wp_json_encode($context);
        }
        
        error_log($formatted_message);
        
        // Store persistent errors in database for admin review
        if (in_array($level, array('error', 'critical'))) {
            $this->store_persistent_error($log_entry);
        }
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_error($errno, $errstr, $errfile, $errline) {
        // Don't handle suppressed errors
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Skip if not TPAK related
        if (strpos($errfile, 'tpak-dq-system') === false) {
            return false;
        }
        
        $level = $this->get_error_level($errno);
        $context = array(
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        );
        
        $this->log_error($errstr, $context, $level);
        
        return true; // Don't execute PHP internal error handler
    }
    
    /**
     * Handle shutdown errors (fatal errors)
     */
    public function handle_shutdown() {
        $last_error = error_get_last();
        
        if ($last_error && in_array($last_error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            // Check if it's TPAK related
            if (strpos($last_error['file'], 'tpak-dq-system') !== false) {
                $context = array(
                    'file' => $last_error['file'],
                    'line' => $last_error['line'],
                    'type' => $last_error['type']
                );
                
                $this->log_error($last_error['message'], $context, 'critical');
            }
        }
    }
    
    /**
     * Get error level from errno
     */
    private function get_error_level($errno) {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'critical';
                
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'warning';
                
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'notice';
                
            default:
                return 'info';
        }
    }
    
    /**
     * Store persistent error in database
     */
    private function store_persistent_error($log_entry) {
        $errors = get_option('tpak_dq_system_errors', array());
        
        // Keep only last 100 errors
        if (count($errors) >= 100) {
            $errors = array_slice($errors, -99);
        }
        
        $errors[] = $log_entry;
        update_option('tpak_dq_system_errors', $errors);
    }
    
    /**
     * Get stored errors
     */
    public function get_stored_errors($level = null, $limit = 50) {
        $errors = get_option('tpak_dq_system_errors', array());
        
        if ($level) {
            $errors = array_filter($errors, function($error) use ($level) {
                return $error['level'] === $level;
            });
        }
        
        // Sort by timestamp descending
        usort($errors, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($errors, 0, $limit);
    }
    
    /**
     * Clear stored errors
     */
    public function clear_stored_errors() {
        delete_option('tpak_dq_system_errors');
    }
    
    /**
     * Get recent errors from current session
     */
    public function get_recent_errors() {
        return $this->errors;
    }
    
    /**
     * Add user-friendly error display
     */
    public function display_admin_notice($message, $type = 'error') {
        add_action('admin_notices', function() use ($message, $type) {
            $class = $type === 'error' ? 'notice-error' : 'notice-warning';
            echo '<div class="notice ' . $class . ' is-dismissible">';
            echo '<p><strong>TPAK DQ System:</strong> ' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }
    
    /**
     * Log API related errors with specific formatting
     */
    public function log_api_error($action, $survey_id, $error_message, $response_data = null) {
        $context = array(
            'action' => $action,
            'survey_id' => $survey_id,
            'response_data' => $response_data
        );
        
        $message = sprintf('API Error in %s for Survey ID %s: %s', $action, $survey_id, $error_message);
        $this->log_error($message, $context, 'error');
    }
    
    /**
     * Log workflow related errors
     */
    public function log_workflow_error($action, $post_id, $error_message, $user_data = null) {
        $context = array(
            'action' => $action,
            'post_id' => $post_id,
            'user_id' => get_current_user_id(),
            'user_data' => $user_data
        );
        
        $message = sprintf('Workflow Error in %s for Post ID %s: %s', $action, $post_id, $error_message);
        $this->log_error($message, $context, 'error');
    }
    
    /**
     * Log import related errors
     */
    public function log_import_error($import_type, $details, $error_message) {
        $context = array(
            'import_type' => $import_type,
            'details' => $details
        );
        
        $message = sprintf('Import Error in %s: %s', $import_type, $error_message);
        $this->log_error($message, $context, 'error');
    }
}