<?php
/**
 * TPAK DQ System - LimeSurvey API Handler
 * 
 * Handles the connection and data retrieval from LimeSurvey API
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_API_Handler {
    
    /**
     * API session key
     */
    private $session_key = null;
    
    /**
     * API URL
     */
    private $api_url = '';
    
    /**
     * API credentials
     */
    private $username = '';
    private $password = '';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_settings();
    }
    
    /**
     * Load API settings from options
     */
    private function load_settings() {
        $options = get_option('tpak_dq_system_options', array());
        
        // Debug: Log raw options
        error_log('TPAK DQ System: Raw options from database: ' . print_r($options, true));
        
        $this->api_url = isset($options['limesurvey_url']) ? $options['limesurvey_url'] : '';
        $this->username = isset($options['limesurvey_username']) ? $options['limesurvey_username'] : '';
        $this->password = isset($options['limesurvey_password']) ? $options['limesurvey_password'] : '';
        
        // Debug: Log loaded settings
        error_log('TPAK DQ System: Loaded API settings - URL: ' . ($this->api_url ? 'Set' : 'Not set') . ', Username: ' . ($this->username ? 'Set' : 'Not set') . ', Password: ' . ($this->password ? 'Set' : 'Not set'));
    }
    
    /**
     * Check if API is configured
     */
    public function is_configured() {
        return !empty($this->api_url) && !empty($this->username) && !empty($this->password);
    }
    
    /**
     * Get API session key
     */
    private function get_session_key() {
        if ($this->session_key) {
            error_log('TPAK DQ System: Using existing session key');
            return $this->session_key;
        }
        
        if (!$this->is_configured()) {
            error_log('TPAK DQ System: API not configured for session key');
            return false;
        }
        
        error_log('TPAK DQ System: Getting new session key for user: ' . $this->username);
        
        $response = $this->make_api_request('get_session_key', array(
            'username' => $this->username,
            'password' => $this->password
        ));
        
        if ($response && isset($response['result'])) {
            $this->session_key = $response['result'];
            error_log('TPAK DQ System: Successfully got session key');
            return $this->session_key;
        }
        
        // Log error for debugging
        if ($response) {
            error_log('TPAK DQ System API Error: ' . json_encode($response));
        } else {
            error_log('TPAK DQ System: No response from get_session_key request');
        }
        
        return false;
    }
    
    /**
     * Make API request to LimeSurvey
     */
    private function make_api_request($method, $params = array()) {
        if (!$this->is_configured()) {
            return false;
        }
        
        $url = rtrim($this->api_url, '/') . '/admin/remotecontrol';
        
        $request_data = array(
            'method' => $method,
            'params' => $params,
            'id' => 1
        );
        
        $args = array(
            'body' => json_encode($request_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30,
            'sslverify' => false
        );
        
        // Debug: Log the request details
        error_log('TPAK DQ System API Request - URL: ' . $url);
        error_log('TPAK DQ System API Request - Method: ' . $method);
        error_log('TPAK DQ System API Request - Params: ' . print_r($params, true));
        error_log('TPAK DQ System API Request - Body: ' . json_encode($request_data));
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            error_log('TPAK DQ System API Error: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        // Debug: Log the response details
        error_log('TPAK DQ System API Response - Status: ' . $status_code);
        error_log('TPAK DQ System API Response - Headers: ' . print_r($headers, true));
        error_log('TPAK DQ System API Response - Body: ' . $body);
        
        if ($status_code !== 200) {
            error_log('TPAK DQ System API Error: HTTP ' . $status_code . ' - ' . $body);
            return false;
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('TPAK DQ System API Error: Invalid JSON response - ' . $body);
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get list of surveys
     */
    public function get_surveys() {
        $session_key = $this->get_session_key();
        if (!$session_key) {
            return false;
        }
        
        $response = $this->make_api_request('list_surveys', array(
            'sSessionKey' => $session_key,
            'sUsername' => $this->username
        ));
        
        if ($response && isset($response['result'])) {
            return $response['result'];
        }
        
        return false;
    }
    
    /**
     * Get survey responses
     */
    public function get_survey_responses($survey_id, $start_date = null, $end_date = null) {
        error_log('TPAK DQ System: Getting survey responses for survey ID: ' . $survey_id);
        
        $session_key = $this->get_session_key();
        if (!$session_key) {
            error_log('TPAK DQ System: Failed to get session key for survey responses');
            return false;
        }
        
        $params = array(
            'sSessionKey' => $session_key,
            'iSurveyID' => $survey_id,
            'sDocumentType' => 'json',
            'sLanguageCode' => 'en'
        );
        
        if ($start_date) {
            $params['sDateFrom'] = $start_date;
        }
        
        if ($end_date) {
            $params['sDateTo'] = $end_date;
        }
        
        error_log('TPAK DQ System: Export responses params: ' . print_r($params, true));
        
        $response = $this->make_api_request('export_responses', $params);
        
        if ($response && isset($response['result'])) {
            error_log('TPAK DQ System: Successfully got survey responses');
            return $response['result'];
        }
        
        error_log('TPAK DQ System: Failed to get survey responses - response: ' . print_r($response, true));
        return false;
    }
    
    /**
     * Get survey structure (questions)
     */
    public function get_survey_structure($survey_id) {
        $session_key = $this->get_session_key();
        if (!$session_key) {
            return false;
        }
        
        $response = $this->make_api_request('list_questions', array(
            'sSessionKey' => $session_key,
            'iSurveyID' => $survey_id
        ));
        
        if ($response && isset($response['result'])) {
            return $response['result'];
        }
        
        return false;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        try {
            if (!$this->is_configured()) {
                return false;
            }
            
            $session_key = $this->get_session_key();
            return $session_key !== false;
        } catch (Exception $e) {
            error_log('TPAK DQ System API Test Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Import survey data to WordPress
     */
    public function import_survey_data($survey_id) {
        error_log('TPAK DQ System: Starting import for survey ID: ' . $survey_id);
        
        // Get survey responses
        $responses = $this->get_survey_responses($survey_id);
        if (!$responses) {
            error_log('TPAK DQ System: Failed to get survey responses for survey ID: ' . $survey_id);
            return false;
        }
        
        error_log('TPAK DQ System: Got ' . count($responses) . ' responses for survey ID: ' . $survey_id);
        
        $imported_count = 0;
        $errors = array();
        
        foreach ($responses as $response) {
            error_log('TPAK DQ System: Processing response ID: ' . $response['id']);
            
            // Check if response already imported
            $existing_post = $this->get_post_by_lime_survey_id($response['id']);
            if ($existing_post) {
                error_log('TPAK DQ System: Response ID ' . $response['id'] . ' already imported, skipping');
                continue; // Skip already imported responses
            }
            
            // Create new verification batch post
            $post_data = array(
                'post_title' => sprintf(__('ชุดข้อมูลตรวจสอบ #%s', 'tpak-dq-system'), $response['id']),
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'verification_batch',
                'meta_input' => array(
                    '_lime_survey_id' => $response['id'],
                    '_survey_data' => json_encode($response),
                    '_audit_trail' => array(),
                    '_import_date' => current_time('mysql')
                )
            );
            
            error_log('TPAK DQ System: Creating post for response ID: ' . $response['id']);
            $post_id = wp_insert_post($post_data);
            
            if ($post_id) {
                error_log('TPAK DQ System: Successfully created post ID: ' . $post_id . ' for response ID: ' . $response['id']);
                
                // Set initial status to pending_a
                wp_set_object_terms($post_id, 'pending_a', 'verification_status');
                
                // Add initial audit trail entry
                $this->add_audit_trail_entry($post_id, array(
                    'user_id' => 0,
                    'user_name' => 'System',
                    'action' => 'imported',
                    'comment' => sprintf(__('นำเข้าข้อมูลจาก LimeSurvey ID: %s', 'tpak-dq-system'), $response['id']),
                    'timestamp' => current_time('mysql')
                ));
                
                $imported_count++;
            } else {
                error_log('TPAK DQ System: Failed to create post for response ID: ' . $response['id']);
                $errors[] = sprintf(__('ไม่สามารถสร้าง Post สำหรับ Response ID: %s', 'tpak-dq-system'), $response['id']);
            }
        }
        
        error_log('TPAK DQ System: Import completed - Imported: ' . $imported_count . ', Errors: ' . count($errors));
        if (!empty($errors)) {
            error_log('TPAK DQ System: Import errors: ' . print_r($errors, true));
        }
        
        return array(
            'imported' => $imported_count,
            'errors' => $errors
        );
    }
    
    /**
     * Get post by LimeSurvey ID
     */
    private function get_post_by_lime_survey_id($lime_survey_id) {
        $posts = get_posts(array(
            'post_type' => 'verification_batch',
            'meta_key' => '_lime_survey_id',
            'meta_value' => $lime_survey_id,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ));
        
        return !empty($posts) ? $posts[0] : false;
    }
    
    /**
     * Add audit trail entry
     */
    private function add_audit_trail_entry($post_id, $entry) {
        $audit_trail = get_post_meta($post_id, '_audit_trail', true);
        if (!is_array($audit_trail)) {
            $audit_trail = array();
        }
        
        $audit_trail[] = $entry;
        update_post_meta($post_id, '_audit_trail', $audit_trail);
    }
    
    /**
     * Get API settings
     */
    public function get_settings() {
        return array(
            'api_url' => $this->api_url,
            'username' => $this->username,
            'password' => $this->password
        );
    }
    
    /**
     * Update API settings
     */
    public function update_settings($settings) {
        $options = get_option('tpak_dq_system_options', array());
        
        $options['limesurvey_url'] = sanitize_url($settings['api_url']);
        $options['limesurvey_username'] = sanitize_text_field($settings['username']);
        $options['limesurvey_password'] = sanitize_text_field($settings['password']);
        
        update_option('tpak_dq_system_options', $options);
        
        // Reload settings
        $this->load_settings();
        
        // Clear session key to force new authentication
        $this->session_key = null;
    }
} 