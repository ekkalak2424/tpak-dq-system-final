<?php
/**
 * TPAK DQ System - Data Validation Helper
 * 
 * Centralized validation functions for the TPAK DQ System
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Validator {
    
    /**
     * Validate email address
     */
    public static function validate_email($email) {
        if (empty($email)) {
            return array('valid' => false, 'message' => __('Email address is required', 'tpak-dq-system'));
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array('valid' => false, 'message' => __('Invalid email address format', 'tpak-dq-system'));
        }
        
        if (strlen($email) > 254) {
            return array('valid' => false, 'message' => __('Email address is too long', 'tpak-dq-system'));
        }
        
        return array('valid' => true, 'email' => sanitize_email($email));
    }
    
    /**
     * Validate URL
     */
    public static function validate_url($url, $required_endpoints = array()) {
        if (empty($url)) {
            return array('valid' => false, 'message' => __('URL is required', 'tpak-dq-system'));
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return array('valid' => false, 'message' => __('Invalid URL format', 'tpak-dq-system'));
        }
        
        // Check for required endpoints (more flexible matching)
        if (!empty($required_endpoints)) {
            $url_clean = rtrim($url, '/');
            $endpoint_found = false;
            
            foreach ($required_endpoints as $endpoint) {
                // More flexible endpoint matching
                $endpoint_clean = trim($endpoint, '/');
                
                // Check if URL contains the endpoint pattern
                if (strpos($url_clean, $endpoint_clean) !== false) {
                    $endpoint_found = true;
                    break;
                }
                
                // Also check for common variations
                if (strpos($url_clean, 'remotecontrol') !== false) {
                    $endpoint_found = true;
                    break;
                }
            }
            
            if (!$endpoint_found) {
                // More user-friendly error message
                return array(
                    'valid' => false, 
                    'message' => __('URL should be a LimeSurvey RemoteControl API endpoint (e.g., https://your-limesurvey.com/index.php/admin/remotecontrol)', 'tpak-dq-system')
                );
            }
        }
        
        return array('valid' => true, 'url' => esc_url_raw($url));
    }
    
    /**
     * Validate numeric ID
     */
    public static function validate_numeric_id($id, $min = 1, $max = 999999999, $field_name = 'ID') {
        if (empty($id) && $id !== '0') {
            return array('valid' => false, 'message' => sprintf(__('%s is required', 'tpak-dq-system'), $field_name));
        }
        
        if (!is_numeric($id)) {
            return array('valid' => false, 'message' => sprintf(__('%s must be numeric', 'tpak-dq-system'), $field_name));
        }
        
        $numeric_id = intval($id);
        
        if ($numeric_id < $min) {
            return array('valid' => false, 'message' => sprintf(__('%s must be at least %d', 'tpak-dq-system'), $field_name, $min));
        }
        
        if ($numeric_id > $max) {
            return array('valid' => false, 'message' => sprintf(__('%s must not exceed %d', 'tpak-dq-system'), $field_name, $max));
        }
        
        return array('valid' => true, 'id' => $numeric_id);
    }
    
    /**
     * Validate percentage
     */
    public static function validate_percentage($percentage, $field_name = 'Percentage') {
        if (!isset($percentage) || $percentage === '') {
            return array('valid' => false, 'message' => sprintf(__('%s is required', 'tpak-dq-system'), $field_name));
        }
        
        if (!is_numeric($percentage)) {
            return array('valid' => false, 'message' => sprintf(__('%s must be numeric', 'tpak-dq-system'), $field_name));
        }
        
        $numeric_percentage = intval($percentage);
        
        if ($numeric_percentage < 1 || $numeric_percentage > 100) {
            return array('valid' => false, 'message' => sprintf(__('%s must be between 1 and 100', 'tpak-dq-system'), $field_name));
        }
        
        return array('valid' => true, 'percentage' => $numeric_percentage);
    }
    
    /**
     * Validate text field
     */
    public static function validate_text($text, $min_length = 0, $max_length = 255, $field_name = 'Text', $required = true) {
        if (empty($text)) {
            if ($required) {
                return array('valid' => false, 'message' => sprintf(__('%s is required', 'tpak-dq-system'), $field_name));
            } else {
                return array('valid' => true, 'text' => '');
            }
        }
        
        $text = trim($text);
        $length = strlen($text);
        
        if ($length < $min_length) {
            return array('valid' => false, 'message' => sprintf(__('%s must be at least %d characters long', 'tpak-dq-system'), $field_name, $min_length));
        }
        
        if ($length > $max_length) {
            return array('valid' => false, 'message' => sprintf(__('%s must not exceed %d characters', 'tpak-dq-system'), $field_name, $max_length));
        }
        
        // Check for suspicious patterns
        if (preg_match('/<script|javascript:|data:|vbscript:|on\w+\s*=/i', $text)) {
            return array('valid' => false, 'message' => sprintf(__('%s contains invalid content', 'tpak-dq-system'), $field_name));
        }
        
        return array('valid' => true, 'text' => sanitize_textarea_field($text));
    }
    
    /**
     * Validate username
     */
    public static function validate_username($username) {
        if (empty($username)) {
            return array('valid' => false, 'message' => __('Username is required', 'tpak-dq-system'));
        }
        
        if (strlen($username) < 3) {
            return array('valid' => false, 'message' => __('Username must be at least 3 characters long', 'tpak-dq-system'));
        }
        
        if (strlen($username) > 50) {
            return array('valid' => false, 'message' => __('Username is too long (maximum 50 characters)', 'tpak-dq-system'));
        }
        
        if (!preg_match('/^[a-zA-Z0-9_@.-]+$/', $username)) {
            return array('valid' => false, 'message' => __('Username contains invalid characters. Only letters, numbers, underscore, at sign, dot, and dash are allowed', 'tpak-dq-system'));
        }
        
        return array('valid' => true, 'username' => sanitize_user($username));
    }
    
    /**
     * Validate password
     */
    public static function validate_password($password, $min_length = 6, $max_length = 100) {
        if (empty($password)) {
            return array('valid' => false, 'message' => __('Password is required', 'tpak-dq-system'));
        }
        
        if (strlen($password) < $min_length) {
            return array('valid' => false, 'message' => sprintf(__('Password must be at least %d characters long', 'tpak-dq-system'), $min_length));
        }
        
        if (strlen($password) > $max_length) {
            return array('valid' => false, 'message' => sprintf(__('Password is too long (maximum %d characters)', 'tpak-dq-system'), $max_length));
        }
        
        return array('valid' => true, 'password' => $password);
    }
    
    /**
     * Validate date
     */
    public static function validate_date($date, $format = 'Y-m-d', $field_name = 'Date') {
        if (empty($date)) {
            return array('valid' => false, 'message' => sprintf(__('%s is required', 'tpak-dq-system'), $field_name));
        }
        
        $date_obj = DateTime::createFromFormat($format, $date);
        
        if (!$date_obj || $date_obj->format($format) !== $date) {
            return array('valid' => false, 'message' => sprintf(__('%s format is invalid. Expected format: %s', 'tpak-dq-system'), $field_name, $format));
        }
        
        // Check if date is reasonable (not too old or in future)
        $current_time = time();
        $ten_years_ago = $current_time - (10 * 365 * 24 * 60 * 60);
        $one_year_future = $current_time + (365 * 24 * 60 * 60);
        $timestamp = $date_obj->getTimestamp();
        
        if ($timestamp < $ten_years_ago) {
            return array('valid' => false, 'message' => sprintf(__('%s is too far in the past', 'tpak-dq-system'), $field_name));
        }
        
        if ($timestamp > $one_year_future) {
            return array('valid' => false, 'message' => sprintf(__('%s is too far in the future', 'tpak-dq-system'), $field_name));
        }
        
        return array('valid' => true, 'date' => $date, 'timestamp' => $timestamp);
    }
    
    /**
     * Validate JSON data
     */
    public static function validate_json($json_string, $max_size = 50000, $field_name = 'JSON data') {
        if (empty($json_string)) {
            return array('valid' => false, 'message' => sprintf(__('%s is required', 'tpak-dq-system'), $field_name));
        }
        
        if (strlen($json_string) > $max_size) {
            return array('valid' => false, 'message' => sprintf(__('%s is too large (maximum %d bytes)', 'tpak-dq-system'), $field_name, $max_size));
        }
        
        $decoded = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('valid' => false, 'message' => sprintf(__('%s is not valid JSON: %s', 'tpak-dq-system'), $field_name, json_last_error_msg()));
        }
        
        return array('valid' => true, 'json' => $json_string, 'decoded' => $decoded);
    }
    
    /**
     * Validate array of values
     */
    public static function validate_array($array, $allowed_values, $field_name = 'Value') {
        if (!is_array($array)) {
            return array('valid' => false, 'message' => sprintf(__('%s must be an array', 'tpak-dq-system'), $field_name));
        }
        
        $invalid_values = array();
        
        foreach ($array as $value) {
            if (!in_array($value, $allowed_values)) {
                $invalid_values[] = $value;
            }
        }
        
        if (!empty($invalid_values)) {
            return array(
                'valid' => false, 
                'message' => sprintf(
                    __('%s contains invalid values: %s. Allowed values: %s', 'tpak-dq-system'), 
                    $field_name, 
                    implode(', ', $invalid_values),
                    implode(', ', $allowed_values)
                )
            );
        }
        
        return array('valid' => true, 'array' => $array);
    }
    
    /**
     * Sanitize and validate file upload
     */
    public static function validate_file_upload($file, $allowed_types = array(), $max_size = 1048576) {
        if (!isset($file) || !is_array($file)) {
            return array('valid' => false, 'message' => __('No file uploaded', 'tpak-dq-system'));
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('valid' => false, 'message' => __('File upload error', 'tpak-dq-system'));
        }
        
        if ($file['size'] > $max_size) {
            return array('valid' => false, 'message' => sprintf(__('File is too large (maximum %d bytes)', 'tpak-dq-system'), $max_size));
        }
        
        if (!empty($allowed_types)) {
            $file_type = wp_check_filetype($file['name']);
            if (!in_array($file_type['type'], $allowed_types)) {
                return array('valid' => false, 'message' => sprintf(__('File type not allowed. Allowed types: %s', 'tpak-dq-system'), implode(', ', $allowed_types)));
            }
        }
        
        return array('valid' => true, 'file' => $file);
    }
}