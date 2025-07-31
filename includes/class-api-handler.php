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
            // Check if the result is an error message
            if (is_array($response['result']) && isset($response['result']['status'])) {
                error_log('TPAK DQ System: Session key error: ' . $response['result']['status']);
                return false;
            }
            
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
        
        // Use the API URL directly - it should already contain the full endpoint
        $url = $this->api_url;
        
        // Debug: Log the URL being used
        error_log('TPAK DQ System: Using API URL: ' . $url);
        
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
        error_log('TPAK DQ System: Getting surveys list');
        
        $session_key = $this->get_session_key();
        if (!$session_key) {
            error_log('TPAK DQ System: Failed to get session key for surveys list');
            return false;
        }
        
        $response = $this->make_api_request('list_surveys', array(
            'sSessionKey' => $session_key,
            'sUsername' => $this->username
        ));
        
        if ($response && isset($response['result'])) {
            // Check if the result is an error message
            if (is_array($response['result']) && isset($response['result']['status'])) {
                error_log('TPAK DQ System: API returned error status for surveys: ' . $response['result']['status']);
                return false;
            }
            
            // Check if result is a string (error message)
            if (is_string($response['result'])) {
                error_log('TPAK DQ System: API returned string result for surveys (likely error): ' . $response['result']);
                return false;
            }
            
            // Check if result is an array of surveys
            if (is_array($response['result'])) {
                error_log('TPAK DQ System: Successfully got surveys list - count: ' . count($response['result']));
                return $response['result'];
            }
            
            error_log('TPAK DQ System: Unexpected surveys result type: ' . gettype($response['result']));
            return false;
        }
        
        error_log('TPAK DQ System: Failed to get surveys list - response: ' . print_r($response, true));
        return false;
    }
    
         /**
      * Get survey responses with improved pagination
      */
     public function get_survey_responses($survey_id, $start_date = null, $end_date = null) {
         error_log('TPAK DQ System: Getting survey responses for survey ID: ' . $survey_id);
         
         // Try different language codes or no language code
         $language_codes = array('', 'th', 'en'); // Try no language first, then Thai, then English
         
         foreach ($language_codes as $lang_code) {
             error_log('TPAK DQ System: Trying language code: ' . ($lang_code ?: 'none'));
             
             // Try to get all responses with improved pagination
             $all_responses = $this->get_survey_responses_paginated($survey_id, $lang_code, $start_date, $end_date);
             
             if ($all_responses && is_array($all_responses) && !empty($all_responses)) {
                 error_log('TPAK DQ System: Successfully got ' . count($all_responses) . ' responses with language ' . ($lang_code ?: 'none'));
                 return $all_responses;
             }
         }
         
         error_log('TPAK DQ System: All language codes failed for survey ID: ' . $survey_id);
         return false;
     }
    
         /**
      * Get survey responses with improved pagination
      */
     private function get_survey_responses_paginated($survey_id, $lang_code, $start_date = null, $end_date = null) {
         $session_key = $this->get_session_key();
         if (!$session_key) {
             return false;
         }
         
         $all_responses = array();
         $iStart = 0;
         $iLimit = 500; // Reduced to 500 responses per request for better stability
         $has_more_data = true;
         $consecutive_errors = 0;
         $max_consecutive_errors = 5; // Increased retry attempts
         $page_count = 0;
         $max_pages = 200; // Safety limit for number of pages
         
         error_log('TPAK DQ System: Starting improved paginated data retrieval for survey ID: ' . $survey_id . ' with language: ' . ($lang_code ?: 'none'));
         
         while ($has_more_data && $page_count < $max_pages) {
             $page_count++;
             
             // Get fresh session key for each page to avoid timeout issues
             if ($page_count > 1) {
                 $this->clear_session_key();
                 $session_key = $this->get_session_key();
                 if (!$session_key) {
                     error_log('TPAK DQ System: Failed to get session key for page ' . $page_count);
                     break;
                 }
             }
             
             $params = array(
                 'sSessionKey' => $session_key,
                 'iSurveyID' => $survey_id,
                 'sDocumentType' => 'json',
                 'iStart' => $iStart,
                 'iLimit' => $iLimit
             );
             
             // Only add language code if it's not empty
             if (!empty($lang_code)) {
                 $params['sLanguageCode'] = $lang_code;
             }
             
             if ($start_date) {
                 $params['sDateFrom'] = $start_date;
             }
             
             if ($end_date) {
                 $params['sDateTo'] = $end_date;
             }
             
             error_log('TPAK DQ System: Page ' . $page_count . ' - Requesting responses ' . $iStart . ' to ' . ($iStart + $iLimit) . ' with language: ' . ($lang_code ?: 'none'));
             
             $response = $this->make_api_request('export_responses', $params);
             
             if ($response && isset($response['result'])) {
                 // Check if the result is an error message
                 if (is_array($response['result']) && isset($response['result']['status'])) {
                     error_log('TPAK DQ System: Page ' . $page_count . ' - API returned error status: ' . $response['result']['status']);
                     
                     // If it's a language error, return false to try next language
                     if (strpos($response['result']['status'], 'Language code not found') !== false) {
                         return false;
                     }
                     
                     // For other errors, try to continue with next batch
                     $consecutive_errors++;
                     if ($consecutive_errors >= $max_consecutive_errors) {
                         error_log('TPAK DQ System: Too many consecutive errors, stopping pagination at page ' . $page_count);
                         break;
                     }
                     
                     // Wait a bit before retrying
                     sleep(2);
                     continue;
                 }
                 
                 // Check if result is a string (could be base64 encoded data or error)
                 if (is_string($response['result'])) {
                     error_log('TPAK DQ System: Page ' . $page_count . ' - API returned string result, length: ' . strlen($response['result']));
                     
                     // Check if it's an error message
                     if (strpos($response['result'], 'Language code not found') !== false) {
                         error_log('TPAK DQ System: Language code not found error');
                         return false;
                     }
                     
                     if (strpos($response['result'], 'No Data') !== false) {
                         error_log('TPAK DQ System: No data available');
                         $has_more_data = false;
                         break;
                     }
                     
                     // Try to decode as base64 (this is the normal case for LimeSurvey)
                     $decoded = base64_decode($response['result'], true);
                     if ($decoded !== false) {
                         error_log('TPAK DQ System: Page ' . $page_count . ' - Base64 decode success, length: ' . strlen($decoded));
                         
                         // Try to decode as JSON
                         $json_data = json_decode($decoded, true);
                         if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                             error_log('TPAK DQ System: Page ' . $page_count . ' - JSON decode success, keys: ' . implode(',', array_keys($json_data)));
                             
                             // Check if it has 'responses' key
                             if (isset($json_data['responses']) && is_array($json_data['responses'])) {
                                 $responses_chunk = $json_data['responses'];
                                 error_log('TPAK DQ System: Page ' . $page_count . ' - Found ' . count($responses_chunk) . ' responses in JSON');
                                 
                                 if (!empty($responses_chunk)) {
                                     $all_responses = array_merge($all_responses, $responses_chunk);
                                     $iStart += $iLimit;
                                     $consecutive_errors = 0; // Reset error counter on success
                                     
                                     // If we got fewer responses than requested, we've reached the end
                                     if (count($responses_chunk) < $iLimit) {
                                         $has_more_data = false;
                                         error_log('TPAK DQ System: Page ' . $page_count . ' - Got fewer responses than requested, ending pagination');
                                     }
                                 } else {
                                     $has_more_data = false;
                                     error_log('TPAK DQ System: Page ' . $page_count . ' - Empty responses chunk, ending pagination');
                                 }
                             } else {
                                 error_log('TPAK DQ System: Page ' . $page_count . ' - No responses key found, available keys: ' . implode(',', array_keys($json_data)));
                                 return false;
                             }
                         } else {
                             error_log('TPAK DQ System: Page ' . $page_count . ' - JSON decode failed: ' . json_last_error_msg());
                             return false;
                         }
                     } else {
                         error_log('TPAK DQ System: Page ' . $page_count . ' - Base64 decode failed, treating as error message');
                         return false;
                     }
                 }
                 
                 // Check if result is an array of responses
                 if (is_array($response['result'])) {
                     $responses_chunk = $response['result'];
                     error_log('TPAK DQ System: Page ' . $page_count . ' - Got ' . count($responses_chunk) . ' responses in this chunk');
                     
                     if (!empty($responses_chunk)) {
                         $all_responses = array_merge($all_responses, $responses_chunk);
                         $iStart += $iLimit;
                         $consecutive_errors = 0; // Reset error counter on success
                         
                         // If we got fewer responses than requested, we've reached the end
                         if (count($responses_chunk) < $iLimit) {
                             $has_more_data = false;
                             error_log('TPAK DQ System: Page ' . $page_count . ' - Got fewer responses than requested, ending pagination');
                         }
                     } else {
                         $has_more_data = false;
                         error_log('TPAK DQ System: Page ' . $page_count . ' - Empty responses chunk, ending pagination');
                     }
                 }
                 
             } else {
                 error_log('TPAK DQ System: Page ' . $page_count . ' - No response or invalid response structure');
                 $consecutive_errors++;
                 if ($consecutive_errors >= $max_consecutive_errors) {
                     error_log('TPAK DQ System: Too many consecutive errors, stopping pagination at page ' . $page_count);
                     break;
                 }
                 
                 // Wait a bit before retrying
                 sleep(2);
                 continue;
             }
             
             // Safety check to prevent infinite loop
             if (count($all_responses) > 100000) {
                 error_log('TPAK DQ System: Safety limit reached (100,000 responses), stopping pagination at page ' . $page_count);
                 break;
             }
             
             // Small delay between requests to avoid overwhelming the server
             if ($has_more_data) {
                 usleep(500000); // 0.5 second delay
             }
         }
         
         error_log('TPAK DQ System: Pagination completed after ' . $page_count . ' pages, total responses: ' . count($all_responses));
         return $all_responses;
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
            // Check if the result is an error message
            if (is_array($response['result']) && isset($response['result']['status'])) {
                error_log('TPAK DQ System: API returned error status for survey structure: ' . $response['result']['status']);
                return false;
            }
            
            // Check if result is a string (error message)
            if (is_string($response['result'])) {
                error_log('TPAK DQ System: API returned string result for survey structure (likely error): ' . $response['result']);
                return false;
            }
            
            // Check if result is an array of questions
            if (is_array($response['result'])) {
                error_log('TPAK DQ System: Successfully got survey structure - count: ' . count($response['result']));
                return $response['result'];
            }
            
            error_log('TPAK DQ System: Unexpected survey structure result type: ' . gettype($response['result']));
            return false;
        }
        
        error_log('TPAK DQ System: Failed to get survey structure - response: ' . print_r($response, true));
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
      * Import survey data to WordPress using improved pagination and batch processing
      */
     public function import_survey_data($survey_id) {
         // Increase memory and time limits for large imports
         @ini_set('memory_limit', '1G');
         @ini_set('max_execution_time', 600); // 10 minutes
         
         error_log('TPAK DQ System: Starting improved batch import for survey ID: ' . $survey_id);
         
         // Get survey responses with improved pagination
         $responses = $this->get_survey_responses($survey_id);
         if (!$responses) {
             error_log('TPAK DQ System: Failed to get survey responses for survey ID: ' . $survey_id);
             return false;
         }
         
         // Ensure responses is an array
         if (!is_array($responses)) {
             error_log('TPAK DQ System: Responses is not an array - type: ' . gettype($responses) . ', value: ' . print_r($responses, true));
             return false;
         }
         
         error_log('TPAK DQ System: Successfully retrieved ' . count($responses) . ' responses for survey ID: ' . $survey_id);
         
         // Process in smaller batches for better memory management
         $batch_size = 25; // Reduced batch size for better stability
         $batches = array_chunk($responses, $batch_size);
         $total_imported = 0;
         $total_errors = array();
         
         error_log('TPAK DQ System: Processing ' . count($batches) . ' batches of ' . $batch_size . ' responses each');
         
         foreach ($batches as $batch_index => $batch) {
             $batch_number = $batch_index + 1;
             error_log('TPAK DQ System: Processing batch ' . $batch_number . ' of ' . count($batches) . ' (' . count($batch) . ' responses)');
             
             $batch_result = $this->process_import_batch($batch);
             $total_imported += $batch_result['imported'];
             $total_errors = array_merge($total_errors, $batch_result['errors']);
             
             error_log('TPAK DQ System: Batch ' . $batch_number . ' completed - Imported: ' . $batch_result['imported'] . ', Errors: ' . count($batch_result['errors']));
             
             // Small delay between batches to prevent overwhelming the database
             if ($batch_number < count($batches)) {
                 usleep(100000); // 0.1 second delay
             }
         }
         
         error_log('TPAK DQ System: Improved batch import completed - Total Imported: ' . $total_imported . ', Total Errors: ' . count($total_errors));
         if (!empty($total_errors)) {
             error_log('TPAK DQ System: Import errors: ' . print_r($total_errors, true));
         }
         
         return array(
             'imported' => $total_imported,
             'errors' => $total_errors
         );
     }
    
    /**
     * Process a batch of responses for import
     */
    private function process_import_batch($responses_batch) {
        $imported_count = 0;
        $errors = array();
        
        // Prepare batch data
        $posts_to_insert = array();
        
        foreach ($responses_batch as $response) {
            // Check if response already imported
            $existing_post = $this->get_post_by_lime_survey_id($response['id']);
            if ($existing_post) {
                error_log('TPAK DQ System: Response ID ' . $response['id'] . ' already imported, skipping');
                continue; // Skip already imported responses
            }
            
            // Prepare post data for batch insert
            $posts_to_insert[] = array(
                'post_title' => sprintf(__('ชุดข้อมูลตรวจสอบ #%s', 'tpak-dq-system'), $response['id']),
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'verification_batch',
                'meta_input' => array(
                    '_lime_survey_id' => $response['id'],
                    '_survey_data' => json_encode($response),
                    '_audit_trail' => array(),
                    '_import_date' => current_time('mysql')
                ),
                'response_data' => $response // Store original response for later use
            );
        }
        
        // Batch insert posts
        if (!empty($posts_to_insert)) {
            $inserted_posts = $this->batch_insert_posts($posts_to_insert);
            $imported_count = count($inserted_posts);
            error_log('TPAK DQ System: Batch insert completed - ' . $imported_count . ' posts created');
        }
        
        return array(
            'imported' => $imported_count,
            'errors' => $errors
        );
    }
    
    /**
     * Batch insert posts
     */
    private function batch_insert_posts($posts_data) {
        $inserted_posts = array();
        
        foreach ($posts_data as $post_data) {
            $response_data = $post_data['response_data'];
            unset($post_data['response_data']); // Remove from post data
            
            $post_id = wp_insert_post($post_data);
            
            if ($post_id) {
                error_log('TPAK DQ System: Successfully created post ID: ' . $post_id . ' for response ID: ' . $response_data['id']);
                
                // Set initial status to pending_a
                wp_set_object_terms($post_id, 'pending_a', 'verification_status');
                
                // Add initial audit trail entry
                $this->add_audit_trail_entry($post_id, array(
                    'user_id' => 0,
                    'user_name' => 'System',
                    'action' => 'imported',
                    'comment' => sprintf(__('นำเข้าข้อมูลจาก LimeSurvey ID: %s', 'tpak-dq-system'), $response_data['id']),
                    'timestamp' => current_time('mysql')
                ));
                
                $inserted_posts[] = $post_id;
            } else {
                error_log('TPAK DQ System: Failed to create post for response ID: ' . $response_data['id']);
            }
        }
        
        return $inserted_posts;
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
      * Clear session key to force new authentication
      */
     protected function clear_session_key() {
         $this->session_key = null;
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