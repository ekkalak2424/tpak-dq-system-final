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
        error_log('TPAK DQ System: Getting survey responses for survey ID: ' . $survey_id . ' with date range: ' . ($start_date ?: 'all') . ' to ' . ($end_date ?: 'all'));
        
        // Validate survey ID
        if (empty($survey_id) || !is_numeric($survey_id)) {
            error_log('TPAK DQ System: Invalid survey ID provided: ' . $survey_id);
            return false;
        }
        
        // First, try to validate that the survey exists by getting its structure
        $survey_structure = $this->get_survey_structure($survey_id);
        if (!$survey_structure) {
            error_log('TPAK DQ System: Survey ID ' . $survey_id . ' does not exist or is not accessible');
            return false;
        }
        
        error_log('TPAK DQ System: Survey structure validation passed for survey ID: ' . $survey_id);
        
        // Try different language codes or no language code
        $language_codes = array('', 'th', 'en'); // Try no language first, then Thai, then English
         
         foreach ($language_codes as $lang_code) {
             error_log('TPAK DQ System: Trying language code: ' . ($lang_code ?: 'none'));
             
             // Try to get all responses with improved pagination
             $all_responses = $this->get_survey_responses_paginated($survey_id, $lang_code, $start_date, $end_date);
             
             if ($all_responses && is_array($all_responses) && !empty($all_responses)) {
                 error_log('TPAK DQ System: Successfully got ' . count($all_responses) . ' responses with language ' . ($lang_code ?: 'none'));
                 return $all_responses;
             } else {
                 error_log('TPAK DQ System: Failed to get responses with language ' . ($lang_code ?: 'none') . ' - Response: ' . print_r($all_responses, true));
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
         
         error_log('TPAK DQ System: Starting improved paginated data retrieval for survey ID: ' . $survey_id . ' with language: ' . ($lang_code ?: 'none') . ' (iLimit: ' . $iLimit . ')');
         
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
                 error_log('TPAK DQ System: Added date filter - From: ' . $start_date);
             }
             
             if ($end_date) {
                 $params['sDateTo'] = $end_date;
                 error_log('TPAK DQ System: Added date filter - To: ' . $end_date);
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
                         error_log('TPAK DQ System: No data available - API returned: ' . $response['result']);
                         
                         // Check if it's the specific "survey table does not exist" error
                         if (strpos($response['result'], 'survey table does not exist') !== false) {
                             error_log('TPAK DQ System: Survey table does not exist for survey ID: ' . $survey_id . ' - This may indicate an invalid survey ID or the survey has been deleted');
                             return false; // Return false to try next language or fail completely
                         }
                         
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
                                     $consecutive_errors = 0; // Reset error counter on success
                                     
                                     // Check if we've received all data
                                     if (count($responses_chunk) < $iLimit) {
                                         // Got fewer responses than requested - we've reached the end
                                         $has_more_data = false;
                                         error_log('TPAK DQ System: Page ' . $page_count . ' - Got fewer responses than requested (' . count($responses_chunk) . ' < ' . $iLimit . '), ending pagination');
                                     } elseif (count($responses_chunk) > $iLimit) {
                                         // Got more responses than requested - LimeSurvey ignored iLimit and returned all data
                                         $has_more_data = false;
                                         error_log('TPAK DQ System: Page ' . $page_count . ' - Got more responses than requested (' . count($responses_chunk) . ' > ' . $iLimit . '), LimeSurvey returned all data, ending pagination');
                                     } else {
                                         // Got exactly iLimit responses - might be more data
                                         $iStart += $iLimit;
                                         error_log('TPAK DQ System: Page ' . $page_count . ' - Got exactly ' . $iLimit . ' responses, continuing to next page');
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
                         $consecutive_errors = 0; // Reset error counter on success
                         
                         // Check if we've received all data
                         if (count($responses_chunk) < $iLimit) {
                             // Got fewer responses than requested - we've reached the end
                             $has_more_data = false;
                             error_log('TPAK DQ System: Page ' . $page_count . ' - Got fewer responses than requested (' . count($responses_chunk) . ' < ' . $iLimit . '), ending pagination');
                         } elseif (count($responses_chunk) > $iLimit) {
                             // Got more responses than requested - LimeSurvey ignored iLimit and returned all data
                             $has_more_data = false;
                             error_log('TPAK DQ System: Page ' . $page_count . ' - Got more responses than requested (' . count($responses_chunk) . ' > ' . $iLimit . '), LimeSurvey returned all data, ending pagination');
                         } else {
                             // Got exactly iLimit responses - might be more data
                             $iStart += $iLimit;
                             error_log('TPAK DQ System: Page ' . $page_count . ' - Got exactly ' . $iLimit . ' responses, continuing to next page');
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
         
         error_log('TPAK DQ System: Pagination completed after ' . $page_count . ' pages, total responses: ' . count($all_responses) . ' (expected iLimit per page: ' . $iLimit . ')');
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
     * Validate if a survey ID exists and is accessible
     */
    public function validate_survey_id($survey_id) {
        if (empty($survey_id) || !is_numeric($survey_id)) {
            return array(
                'valid' => false,
                'message' => 'Invalid survey ID format'
            );
        }
        
        $session_key = $this->get_session_key();
        if (!$session_key) {
            return array(
                'valid' => false,
                'message' => 'Cannot authenticate with LimeSurvey API'
            );
        }
        
        // Try to get survey properties to validate it exists
        $response = $this->make_api_request('get_survey_properties', array(
            'sSessionKey' => $session_key,
            'iSurveyID' => $survey_id,
            'aSurveySettings' => array('active', 'title')
        ));
        
        if ($response && isset($response['result'])) {
            if (is_array($response['result']) && isset($response['result']['status'])) {
                return array(
                    'valid' => false,
                    'message' => 'Survey not found: ' . $response['result']['status']
                );
            }
            
            if (is_array($response['result'])) {
                return array(
                    'valid' => true,
                    'message' => 'Survey found and accessible',
                    'data' => $response['result']
                );
            }
        }
        
        return array(
            'valid' => false,
            'message' => 'Unable to validate survey ID'
        );
    }
    
    /**
     * Get response date range for a survey
     */
    public function get_response_date_range($survey_id) {
        error_log('TPAK DQ System: Getting response date range for survey ID: ' . $survey_id);
        
        $responses = $this->get_survey_responses($survey_id);
        if (!$responses || !is_array($responses) || empty($responses)) {
            return array(
                'success' => false,
                'message' => 'No responses found for this survey'
            );
        }
        
        $dates = array();
        foreach ($responses as $response) {
            if (isset($response['submitdate']) && !empty($response['submitdate'])) {
                $dates[] = $response['submitdate'];
            }
        }
        
        if (empty($dates)) {
            return array(
                'success' => false,
                'message' => 'No response dates found'
            );
        }
        
        sort($dates);
        $earliest = $dates[0];
        $latest = end($dates);
        
        return array(
            'success' => true,
            'total_responses' => count($responses),
            'earliest_date' => $earliest,
            'latest_date' => $latest,
            'date_range' => $earliest . ' to ' . $latest
        );
    }
    
    /**
     * Test pagination logic with a small sample
     */
    public function test_pagination($survey_id, $limit = 10) {
        error_log('TPAK DQ System: Testing pagination for survey ID: ' . $survey_id . ' with limit: ' . $limit);
        
        $session_key = $this->get_session_key();
        if (!$session_key) {
            error_log('TPAK DQ System: Failed to get session key for pagination test');
            return false;
        }
        
        // Test with a small limit to see pagination behavior
        $params = array(
            'sSessionKey' => $session_key,
            'iSurveyID' => $survey_id,
            'sDocumentType' => 'json',
            'iStart' => 0,
            'iLimit' => $limit
        );
        
        error_log('TPAK DQ System: Testing pagination with params: ' . print_r($params, true));
        
        $response = $this->make_api_request('export_responses', $params);
        
        if ($response && isset($response['result'])) {
            if (is_string($response['result'])) {
                $decoded = base64_decode($response['result'], true);
                if ($decoded !== false) {
                    $json_data = json_decode($decoded, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                        if (isset($json_data['responses'])) {
                            $response_count = count($json_data['responses']);
                            error_log('TPAK DQ System: Pagination test - Requested: ' . $limit . ', Received: ' . $response_count);
                            
                            return array(
                                'requested' => $limit,
                                'received' => $response_count,
                                'has_more' => $response_count >= $limit,
                                'all_data_returned' => $response_count > $limit
                            );
                        }
                    }
                }
            }
        }
        
        error_log('TPAK DQ System: Pagination test failed');
        return false;
    }
    
         /**
      * Import survey data to WordPress using improved pagination and batch processing
      */
     public function import_survey_data($survey_id, $start_date = null, $end_date = null) {
         // Increase memory and time limits for large imports
         @ini_set('memory_limit', '1G');
         @ini_set('max_execution_time', 600); // 10 minutes
         
         error_log('TPAK DQ System: Starting improved batch import for survey ID: ' . $survey_id . ' with date range: ' . ($start_date ?: 'all') . ' to ' . ($end_date ?: 'all'));
         
         // Get survey responses with improved pagination
         $responses = $this->get_survey_responses($survey_id, $start_date, $end_date);
         if (!$responses) {
             error_log('TPAK DQ System: Failed to get survey responses for survey ID: ' . $survey_id);
             return array(
                 'imported' => 0,
                 'errors' => array('ไม่สามารถดึงข้อมูลจาก LimeSurvey ได้ กรุณาตรวจสอบ Survey ID และการเชื่อมต่อ API')
             );
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
            
            $batch_result = $this->process_import_batch($batch, $survey_id);
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
    private function process_import_batch($responses_batch, $survey_id) {
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
                    '_lime_survey_id' => $survey_id, // Use actual survey ID, not response ID
                    '_lime_response_id' => $response['id'], // Store response ID separately
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
     * Get post by LimeSurvey response ID
     */
    private function get_post_by_lime_survey_id($response_id) {
        $posts = get_posts(array(
            'post_type' => 'verification_batch',
            'meta_key' => '_lime_response_id',
            'meta_value' => $response_id,
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
     * Fix existing data structure for LimeSurvey ID and Response ID
     * This function migrates existing posts that have response IDs stored in _lime_survey_id
     * to the new structure where _lime_survey_id contains the actual survey ID
     * and _lime_response_id contains the response ID
     */
    public function fix_existing_data_structure($survey_id = null) {
        error_log('TPAK DQ System: Starting data structure fix for existing posts');
        
        $args = array(
            'post_type' => 'verification_batch',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_lime_survey_id',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_lime_response_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $posts = get_posts($args);
        $fixed_count = 0;
        $errors = array();
        
        error_log('TPAK DQ System: Found ' . count($posts) . ' posts that need structure fix');
        
        foreach ($posts as $post) {
            $current_lime_survey_id = get_post_meta($post->ID, '_lime_survey_id', true);
            
            // If the current _lime_survey_id looks like a response ID (numeric and not a survey ID)
            // and we have a survey_id parameter, we can fix it
            if (is_numeric($current_lime_survey_id) && $survey_id) {
                // Store the current value as response ID
                update_post_meta($post->ID, '_lime_response_id', $current_lime_survey_id);
                
                // Update with the actual survey ID
                update_post_meta($post->ID, '_lime_survey_id', $survey_id);
                
                // Add audit trail entry
                $this->add_audit_trail_entry($post->ID, array(
                    'user_id' => 0,
                    'user_name' => 'System',
                    'action' => 'data_structure_fix',
                    'comment' => sprintf(__('แก้ไขโครงสร้างข้อมูล: ย้าย Response ID %s ไปยัง _lime_response_id และตั้งค่า Survey ID เป็น %s', 'tpak-dq-system'), $current_lime_survey_id, $survey_id),
                    'timestamp' => current_time('mysql')
                ));
                
                $fixed_count++;
                error_log('TPAK DQ System: Fixed post ID ' . $post->ID . ' - moved response ID ' . $current_lime_survey_id . ' to _lime_response_id');
            } else {
                $errors[] = sprintf(__('ไม่สามารถแก้ไขข้อมูลสำหรับ Post ID %s: ไม่มี Survey ID หรือข้อมูลไม่ถูกต้อง', 'tpak-dq-system'), $post->ID);
            }
        }
        
        error_log('TPAK DQ System: Data structure fix completed - Fixed: ' . $fixed_count . ', Errors: ' . count($errors));
        
        return array(
            'fixed' => $fixed_count,
            'errors' => $errors
        );
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

    /**
      * Get raw survey responses with minimal data
      */
     public function get_raw_survey_responses($survey_id, $start_date = null, $end_date = null) {
         error_log('TPAK DQ System: Getting raw survey responses for survey ID: ' . $survey_id);
         
         // Get full responses first
         $responses = $this->get_survey_responses($survey_id, $start_date, $end_date);
         if (!$responses || !is_array($responses)) {
             error_log('TPAK DQ System: Failed to get survey responses for raw data extraction');
             return false;
         }
         
         // Extract raw data from responses
         $raw_data = array();
         foreach ($responses as $response) {
             $raw_response = $this->extract_raw_data_from_response($response);
             if ($raw_response) {
                 $raw_data[] = $raw_response;
             }
         }
         
         error_log('TPAK DQ System: Extracted raw data from ' . count($raw_data) . ' responses');
         return $raw_data;
     }
     
     /**
      * Extract raw data from a single response
      */
     private function extract_raw_data_from_response($response) {
         if (!isset($response['id'])) {
             return false;
         }
         
         $raw_data = array(
             'response_id' => $response['id'],
             'submit_date' => isset($response['submitteddate']) ? $response['submitteddate'] : '',
             'questions' => array()
         );
         
         // Extract question data
         foreach ($response as $key => $value) {
             // Skip non-question fields
             if (in_array($key, ['id', 'submitteddate', 'startdate', 'datestamp', 'ipaddr', 'refurl', 'Consent', 'ConsentRe'])) {
                 continue;
             }
             
             // Extract question code and answer
             $question_data = $this->parse_question_field($key, $value);
             if ($question_data) {
                 $raw_data['questions'][] = $question_data;
             }
         }
         
         return $raw_data;
     }
     
     /**
      * Parse question field to extract question code and answer
      */
     private function parse_question_field($field_name, $value) {
         // Handle different question formats
         if (preg_match('/^([A-Z]\d+)(?:\[(\d+)\])?$/', $field_name, $matches)) {
             $question_code = $matches[1];
             $sub_question = isset($matches[2]) ? $matches[2] : null;
             
             return array(
                 'question_code' => $question_code,
                 'sub_question' => $sub_question,
                 'raw_answer' => $value,
                 'field_name' => $field_name
             );
         }
         
         return false;
     }
     
     /**
      * Get question mapping for a survey
      */
     public function get_question_mapping($survey_id) {
         // This would typically come from a database or configuration
         // For now, we'll return a basic mapping structure
         $mapping = array(
             'S1' => array(
                 'question_text' => 'จังหวัด',
                 'type' => 'single_choice',
                 'options' => array(
                     '39' => 'กรุงเทพมหานคร',
                     '1' => 'กระบี่',
                     // Add more options as needed
                 )
             ),
             'S2' => array(
                 'question_text' => 'อำเภอ',
                 'type' => 'single_choice'
             ),
             'Q1' => array(
                 'question_text' => 'อายุ',
                 'type' => 'number'
             ),
             'Q2' => array(
                 'question_text' => 'เพศ',
                 'type' => 'single_choice',
                 'options' => array(
                     '35' => 'ชาย',
                     '36' => 'หญิง'
                 )
             ),
             // Add more question mappings as needed
         );
         
         return $mapping;
     }
     
     /**
      * Apply mapping to raw data
      */
     public function apply_mapping_to_raw_data($raw_data, $mapping) {
         $mapped_data = array();
         
         foreach ($raw_data as $response) {
             $mapped_response = array(
                 'response_id' => $response['response_id'],
                 'submit_date' => $response['submit_date'],
                 'questions' => array()
             );
             
             foreach ($response['questions'] as $question) {
                 $question_code = $question['question_code'];
                 
                 if (isset($mapping[$question_code])) {
                     $mapped_question = array(
                         'question_code' => $question_code,
                         'question_text' => $mapping[$question_code]['question_text'],
                         'type' => $mapping[$question_code]['type'],
                         'raw_answer' => $question['raw_answer'],
                         'mapped_answer' => $this->map_answer($question['raw_answer'], $mapping[$question_code])
                     );
                     
                     if ($question['sub_question']) {
                         $mapped_question['sub_question'] = $question['sub_question'];
                     }
                     
                     $mapped_response['questions'][] = $mapped_question;
                 } else {
                     // Keep unmapped questions as raw data
                     $mapped_response['questions'][] = $question;
                 }
             }
             
             $mapped_data[] = $mapped_response;
         }
         
         return $mapped_data;
     }
     
     /**
      * Map raw answer to readable answer
      */
     private function map_answer($raw_answer, $question_mapping) {
         if (empty($raw_answer)) {
             return '';
         }
         
         // Handle different question types
         switch ($question_mapping['type']) {
             case 'single_choice':
                 if (isset($question_mapping['options'][$raw_answer])) {
                     return $question_mapping['options'][$raw_answer];
                 }
                 return $raw_answer;
                 
             case 'multiple_choice':
                 $answers = explode('|', $raw_answer);
                 $mapped_answers = array();
                 foreach ($answers as $answer) {
                     if (isset($question_mapping['options'][$answer])) {
                         $mapped_answers[] = $question_mapping['options'][$answer];
                     } else {
                         $mapped_answers[] = $answer;
                     }
                 }
                 return implode(', ', $mapped_answers);
                 
             case 'number':
                 return $raw_answer;
                 
             case 'text':
                 return $raw_answer;
                 
             default:
                 return $raw_answer;
         }
     }
     
     /**
      * Import raw survey data with mapping
      */
     public function import_raw_survey_data($survey_id) {
         // Increase memory and time limits
         @ini_set('memory_limit', '1G');
         @ini_set('max_execution_time', 600);
         
         error_log('TPAK DQ System: Starting raw data import for survey ID: ' . $survey_id);
         
         // Get raw data
         $raw_data = $this->get_raw_survey_responses($survey_id);
         if (!$raw_data) {
             error_log('TPAK DQ System: Failed to get raw survey data for survey ID: ' . $survey_id);
             return false;
         }
         
         error_log('TPAK DQ System: Got ' . count($raw_data) . ' raw responses for survey ID: ' . $survey_id);
         
         // Get mapping
         $mapping = $this->get_question_mapping($survey_id);
         
         // Apply mapping
         $mapped_data = $this->apply_mapping_to_raw_data($raw_data, $mapping);
         
         // Import mapped data
         $batch_size = 25;
         $batches = array_chunk($mapped_data, $batch_size);
         $total_imported = 0;
         $total_errors = array();
         
         error_log('TPAK DQ System: Processing ' . count($batches) . ' batches of mapped data');
         
         foreach ($batches as $batch_index => $batch) {
             $batch_number = $batch_index + 1;
             error_log('TPAK DQ System: Processing mapped batch ' . $batch_number . ' of ' . count($batches));
             
             $batch_result = $this->process_mapped_import_batch($batch, $survey_id);
             $total_imported += $batch_result['imported'];
             $total_errors = array_merge($total_errors, $batch_result['errors']);
             
             error_log('TPAK DQ System: Mapped batch ' . $batch_number . ' completed - Imported: ' . $batch_result['imported'] . ', Errors: ' . count($batch_result['errors']));
             
             if ($batch_number < count($batches)) {
                 usleep(100000); // 0.1 second delay
             }
         }
         
         error_log('TPAK DQ System: Raw data import completed - Total Imported: ' . $total_imported . ', Total Errors: ' . count($total_errors));
         
         return array(
             'imported' => $total_imported,
             'errors' => $total_errors
         );
     }
     
     /**
      * Process a batch of mapped data for import
      */
     private function process_mapped_import_batch($mapped_batch, $survey_id) {
         $imported_count = 0;
         $errors = array();
         
         foreach ($mapped_batch as $mapped_response) {
             // Check if response already imported
             $existing_post = $this->get_post_by_lime_survey_id($mapped_response['response_id']);
             if ($existing_post) {
                 error_log('TPAK DQ System: Response ID ' . $mapped_response['response_id'] . ' already imported, skipping');
                 continue;
             }
             
             // Create post data with mapped information
             $post_data = array(
                 'post_title' => sprintf(__('ชุดข้อมูลตรวจสอบ #%s', 'tpak-dq-system'), $mapped_response['response_id']),
                 'post_content' => $this->generate_mapped_content($mapped_response),
                 'post_status' => 'publish',
                 'post_type' => 'verification_batch',
                 'meta_input' => array(
                     '_lime_survey_id' => $mapped_response['response_id'],
                     '_survey_id' => $survey_id,
                     '_mapped_data' => json_encode($mapped_response),
                     '_audit_trail' => array(),
                     '_import_date' => current_time('mysql')
                 )
             );
             
             $post_id = wp_insert_post($post_data);
             
             if ($post_id) {
                 error_log('TPAK DQ System: Successfully created mapped post ID: ' . $post_id . ' for response ID: ' . $mapped_response['response_id']);
                 
                 // Set initial status to pending_a
                 wp_set_object_terms($post_id, 'pending_a', 'verification_status');
                 
                 // Add initial audit trail entry
                 $this->add_audit_trail_entry($post_id, array(
                     'user_id' => 0,
                     'user_name' => 'System',
                     'action' => 'imported_mapped',
                     'comment' => sprintf(__('นำเข้าข้อมูลดิบพร้อม mapping จาก LimeSurvey ID: %s', 'tpak-dq-system'), $mapped_response['response_id']),
                     'timestamp' => current_time('mysql')
                 ));
                 
                 $imported_count++;
             } else {
                 error_log('TPAK DQ System: Failed to create mapped post for response ID: ' . $mapped_response['response_id']);
                 $errors[] = 'Failed to create post for response ID: ' . $mapped_response['response_id'];
             }
         }
         
         return array(
             'imported' => $imported_count,
             'errors' => $errors
         );
     }
     
         /**
     * Generate content from mapped data
     */
    private function generate_mapped_content($mapped_response) {
        $content = '<h3>ข้อมูลการตอบแบบสอบถาม</h3>';
        $content .= '<p><strong>รหัสการตอบ:</strong> ' . $mapped_response['response_id'] . '</p>';
        $content .= '<p><strong>วันที่ตอบ:</strong> ' . $mapped_response['submit_date'] . '</p>';
        
        $content .= '<h4>คำถามและคำตอบ:</h4>';
        $content .= '<table class="mapped-questions">';
        $content .= '<tr><th>รหัสคำถาม</th><th>คำถาม</th><th>คำตอบ</th></tr>';
        
        foreach ($mapped_response['questions'] as $question) {
            $question_text = isset($question['question_text']) ? $question['question_text'] : $question['question_code'];
            $answer = isset($question['mapped_answer']) ? $question['mapped_answer'] : $question['raw_answer'];
            
            $content .= '<tr>';
            $content .= '<td>' . $question['question_code'];
            if (isset($question['sub_question'])) {
                $content .= '[' . $question['sub_question'] . ']';
            }
            $content .= '</td>';
            $content .= '<td>' . $question_text . '</td>';
            $content .= '<td>' . $answer . '</td>';
            $content .= '</tr>';
        }
        
        $content .= '</table>';
        
        return $content;
    }
    
    /**
     * Clear verification batch data based on criteria
     */
    public function clear_verification_data($clear_type, $params = array()) {
        error_log('TPAK DQ System: Starting data clear operation - Type: ' . $clear_type);
        
        // Increase memory and time limits for large operations
        @ini_set('memory_limit', '1G');
        @ini_set('max_execution_time', 600);
        
        $deleted_count = 0;
        $errors = array();
        
        try {
            // Create backup before clearing data
            $backup_result = $this->create_backup_before_clear($clear_type, $params);
            if (!$backup_result['success']) {
                error_log('TPAK DQ System: Backup failed - ' . implode(', ', $backup_result['errors']));
                // Continue with clear operation even if backup fails
            } else {
                error_log('TPAK DQ System: Backup created successfully - ' . $backup_result['backup_file']);
            }
            
            switch ($clear_type) {
                case 'all':
                    $deleted_count = $this->clear_all_data();
                    break;
                    
                case 'by_survey':
                    $survey_id = isset($params['survey_id']) ? sanitize_text_field($params['survey_id']) : '';
                    if (empty($survey_id)) {
                        throw new Exception(__('กรุณาระบุ Survey ID', 'tpak-dq-system'));
                    }
                    $deleted_count = $this->clear_data_by_survey($survey_id);
                    break;
                    
                case 'by_status':
                    $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';
                    if (empty($status)) {
                        throw new Exception(__('กรุณาระบุสถานะ', 'tpak-dq-system'));
                    }
                    $deleted_count = $this->clear_data_by_status($status);
                    break;
                    
                case 'by_date':
                    $start_date = isset($params['start_date']) ? sanitize_text_field($params['start_date']) : '';
                    $end_date = isset($params['end_date']) ? sanitize_text_field($params['end_date']) : '';
                    if (empty($start_date) || empty($end_date)) {
                        throw new Exception(__('กรุณาระบุช่วงวันที่ให้ครบถ้วน', 'tpak-dq-system'));
                    }
                    $deleted_count = $this->clear_data_by_date($start_date, $end_date);
                    break;
                    
                default:
                    throw new Exception(__('ประเภทการเคลียร์ข้อมูลไม่ถูกต้อง', 'tpak-dq-system'));
            }
            
            error_log('TPAK DQ System: Data clear operation completed - Deleted: ' . $deleted_count . ' posts');
            
            return array(
                'success' => true,
                'deleted' => $deleted_count,
                'errors' => $errors,
                'backup_file' => isset($backup_result['backup_file']) ? $backup_result['backup_file'] : null
            );
            
        } catch (Exception $e) {
            error_log('TPAK DQ System: Data clear operation failed - ' . $e->getMessage());
            return array(
                'success' => false,
                'deleted' => $deleted_count,
                'errors' => array($e->getMessage())
            );
        }
    }
    
    /**
     * Create backup before clearing data
     */
    private function create_backup_before_clear($clear_type, $params) {
        error_log('TPAK DQ System: Creating backup before clear operation');
        
        try {
            $backup_data = array(
                'timestamp' => current_time('mysql'),
                'clear_type' => $clear_type,
                'params' => $params,
                'user_id' => get_current_user_id(),
                'user_name' => wp_get_current_user()->display_name,
                'posts' => array()
            );
            
            // Get posts to be deleted based on criteria
            $args = array(
                'post_type' => 'verification_batch',
                'posts_per_page' => -1,
                'post_status' => 'any'
            );
            
            switch ($clear_type) {
                case 'all':
                    // Get all posts
                    break;
                    
                case 'by_survey':
                    $args['meta_query'] = array(
                        array(
                            'key' => '_lime_survey_id',
                            'value' => $params['survey_id'],
                            'compare' => '='
                        )
                    );
                    break;
                    
                case 'by_status':
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => $params['status']
                        )
                    );
                    break;
                    
                case 'by_date':
                    $args['date_query'] = array(
                        array(
                            'after' => $params['start_date'],
                            'before' => $params['end_date'] . ' 23:59:59',
                            'inclusive' => true
                        )
                    );
                    break;
            }
            
            $posts = get_posts($args);
            
            foreach ($posts as $post) {
                $post_data = array(
                    'ID' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_status' => $post->post_status,
                    'post_date' => $post->post_date,
                    'meta_data' => array()
                );
                
                // Get all meta data
                $meta_keys = array('_lime_survey_id', '_lime_response_id', '_survey_data', '_audit_trail', '_import_date');
                foreach ($meta_keys as $meta_key) {
                    $meta_value = get_post_meta($post->ID, $meta_key, true);
                    if ($meta_value !== '') {
                        $post_data['meta_data'][$meta_key] = $meta_value;
                    }
                }
                
                // Get terms
                $terms = wp_get_object_terms($post->ID, 'verification_status');
                if (!is_wp_error($terms)) {
                    $post_data['terms'] = array();
                    foreach ($terms as $term) {
                        $post_data['terms'][] = array(
                            'taxonomy' => $term->taxonomy,
                            'term_id' => $term->term_id,
                            'slug' => $term->slug,
                            'name' => $term->name
                        );
                    }
                }
                
                $backup_data['posts'][] = $post_data;
            }
            
            // Create backup directory if it doesn't exist
            $backup_dir = WP_CONTENT_DIR . '/tpak-dq-backups';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }
            
            // Create backup file
            $timestamp = current_time('Y-m-d_H-i-s');
            $backup_filename = 'tpak-dq-backup-' . $clear_type . '-' . $timestamp . '.json';
            $backup_file = $backup_dir . '/' . $backup_filename;
            
            $json_data = json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($backup_file, $json_data) === false) {
                throw new Exception(__('ไม่สามารถสร้างไฟล์ backup ได้', 'tpak-dq-system'));
            }
            
            error_log('TPAK DQ System: Backup created successfully - ' . $backup_file . ' (' . count($backup_data['posts']) . ' posts)');
            
            return array(
                'success' => true,
                'backup_file' => $backup_file,
                'post_count' => count($backup_data['posts'])
            );
            
        } catch (Exception $e) {
            error_log('TPAK DQ System: Backup creation failed - ' . $e->getMessage());
            return array(
                'success' => false,
                'errors' => array($e->getMessage())
            );
        }
    }
    
    /**
     * Clear all verification batch data
     */
    private function clear_all_data() {
        error_log('TPAK DQ System: Clearing all verification batch data');
        
        $args = array(
            'post_type' => 'verification_batch',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        );
        
        $post_ids = get_posts($args);
        $deleted_count = 0;
        
        error_log('TPAK DQ System: Found ' . count($post_ids) . ' posts to delete');
        
        foreach ($post_ids as $post_id) {
            $result = wp_delete_post($post_id, true); // true = force delete
            if ($result) {
                $deleted_count++;
            }
        }
        
        error_log('TPAK DQ System: Successfully deleted ' . $deleted_count . ' posts');
        return $deleted_count;
    }
    
    /**
     * Clear data by survey ID
     */
    private function clear_data_by_survey($survey_id) {
        error_log('TPAK DQ System: Clearing data for survey ID: ' . $survey_id);
        
        $args = array(
            'post_type' => 'verification_batch',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_lime_survey_id',
                    'value' => $survey_id,
                    'compare' => '='
                )
            )
        );
        
        $post_ids = get_posts($args);
        $deleted_count = 0;
        
        error_log('TPAK DQ System: Found ' . count($post_ids) . ' posts for survey ID ' . $survey_id . ' to delete');
        
        foreach ($post_ids as $post_id) {
            $result = wp_delete_post($post_id, true);
            if ($result) {
                $deleted_count++;
            }
        }
        
        error_log('TPAK DQ System: Successfully deleted ' . $deleted_count . ' posts for survey ID ' . $survey_id);
        return $deleted_count;
    }
    
    /**
     * Clear data by status
     */
    private function clear_data_by_status($status) {
        error_log('TPAK DQ System: Clearing data for status: ' . $status);
        
        $args = array(
            'post_type' => 'verification_batch',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'verification_status',
                    'field' => 'slug',
                    'terms' => $status
                )
            )
        );
        
        $post_ids = get_posts($args);
        $deleted_count = 0;
        
        error_log('TPAK DQ System: Found ' . count($post_ids) . ' posts with status ' . $status . ' to delete');
        
        foreach ($post_ids as $post_id) {
            $result = wp_delete_post($post_id, true);
            if ($result) {
                $deleted_count++;
            }
        }
        
        error_log('TPAK DQ System: Successfully deleted ' . $deleted_count . ' posts with status ' . $status);
        return $deleted_count;
    }
    
    /**
     * Clear data by date range
     */
    private function clear_data_by_date($start_date, $end_date) {
        error_log('TPAK DQ System: Clearing data from ' . $start_date . ' to ' . $end_date);
        
        $args = array(
            'post_type' => 'verification_batch',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
            'date_query' => array(
                array(
                    'after' => $start_date,
                    'before' => $end_date . ' 23:59:59',
                    'inclusive' => true
                )
            )
        );
        
        $post_ids = get_posts($args);
        $deleted_count = 0;
        
        error_log('TPAK DQ System: Found ' . count($post_ids) . ' posts in date range to delete');
        
        foreach ($post_ids as $post_id) {
            $result = wp_delete_post($post_id, true);
            if ($result) {
                $deleted_count++;
            }
        }
        
        error_log('TPAK DQ System: Successfully deleted ' . $deleted_count . ' posts in date range');
        return $deleted_count;
    }
} 