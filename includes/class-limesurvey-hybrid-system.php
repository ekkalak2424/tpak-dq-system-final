<?php
/**
 * LimeSurvey Hybrid System
 * ระบบผสมระหว่าง iframe display และ API data fetching
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_LimeSurvey_Hybrid_System {
    
    private static $instance = null;
    private $api_url = 'https://survey.tpak.or.th/index.php/admin/remotecontrol';
    private $survey_url = 'https://survey.tpak.or.th';
    private $session_key = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_hybrid_load_survey', array($this, 'ajax_load_survey'));
        add_action('wp_ajax_hybrid_fetch_response', array($this, 'ajax_fetch_response'));
        add_action('wp_ajax_hybrid_save_response', array($this, 'ajax_save_response'));
        add_action('wp_ajax_hybrid_update_field', array($this, 'ajax_update_field'));
        add_action('wp_ajax_hybrid_sync_to_limesurvey', array($this, 'ajax_sync_to_limesurvey'));
        
        // Create tables
        add_action('init', array($this, 'create_tables'));
        
        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * สร้างตารางสำหรับระบบ Hybrid
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // ตารางหลักสำหรับเก็บ responses
        $table_responses = $wpdb->prefix . 'tpak_hybrid_responses';
        $sql_responses = "CREATE TABLE IF NOT EXISTS $table_responses (
            id int(11) NOT NULL AUTO_INCREMENT,
            survey_id varchar(50) NOT NULL,
            response_id varchar(50) DEFAULT NULL,
            token varchar(100) DEFAULT NULL,
            wordpress_post_id int(11) DEFAULT NULL,
            original_data longtext COMMENT 'ข้อมูลดั้งเดิมจาก LimeSurvey',
            modified_data longtext COMMENT 'ข้อมูลที่แก้ไขแล้ว',
            status enum('draft','completed','synced') DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            modified_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            synced_at datetime DEFAULT NULL,
            created_by int(11) DEFAULT NULL,
            modified_by int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_survey_response (survey_id, response_id),
            KEY idx_token (token),
            KEY idx_status (status)
        ) $charset_collate;";
        
        // ตารางสำหรับ field mappings
        $table_mappings = $wpdb->prefix . 'tpak_hybrid_field_mappings';
        $sql_mappings = "CREATE TABLE IF NOT EXISTS $table_mappings (
            id int(11) NOT NULL AUTO_INCREMENT,
            survey_id varchar(50) NOT NULL,
            field_code varchar(100) NOT NULL,
            field_label text,
            field_type varchar(50),
            field_options text,
            display_order int(11) DEFAULT 0,
            is_required tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY idx_survey_field (survey_id, field_code)
        ) $charset_collate;";
        
        // ตารางสำหรับ sync history
        $table_sync = $wpdb->prefix . 'tpak_hybrid_sync_log';
        $sql_sync = "CREATE TABLE IF NOT EXISTS $table_sync (
            id int(11) NOT NULL AUTO_INCREMENT,
            response_id int(11) NOT NULL,
            action enum('fetch','push','update') NOT NULL,
            status enum('success','failed') NOT NULL,
            details text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            user_id int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_response (response_id),
            KEY idx_timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_responses);
        dbDelta($sql_mappings);
        dbDelta($sql_sync);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'tpak') === false) {
            return;
        }
        
        wp_enqueue_script(
            'tpak-hybrid-system',
            TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/hybrid-system.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('tpak-hybrid-system', 'tpakHybrid', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpak_hybrid_nonce'),
            'strings' => array(
                'loading' => __('กำลังโหลด...', 'tpak-dq-system'),
                'success' => __('สำเร็จ!', 'tpak-dq-system'),
                'error' => __('เกิดข้อผิดพลาด', 'tpak-dq-system')
            )
        ));
    }
    
    /**
     * เชื่อมต่อกับ LimeSurvey API
     */
    private function connect_api($username = null, $password = null) {
        // ถ้ามี session key แล้ว และยังใช้ได้
        if ($this->session_key) {
            return $this->session_key;
        }
        
        // ดึง credentials จาก settings
        if (!$username) {
            $username = get_option('tpak_limesurvey_username');
            $password = get_option('tpak_limesurvey_password');
        }
        
        $request_data = array(
            'method' => 'get_session_key',
            'params' => array($username, $password),
            'id' => 1
        );
        
        $response = wp_remote_post($this->api_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['result']) && !isset($body['error'])) {
            $this->session_key = $body['result'];
            return $this->session_key;
        }
        
        return false;
    }
    
    /**
     * ดึงข้อมูล response จาก LimeSurvey
     */
    public function fetch_response_data($survey_id, $response_id = null, $token = null) {
        $session_key = $this->connect_api();
        
        if (!$session_key) {
            return array('error' => 'Cannot connect to LimeSurvey API');
        }
        
        // เลือก method ตามข้อมูลที่มี
        if ($response_id) {
            $method = 'get_response_by_id';
            $params = array($session_key, $survey_id, $response_id);
        } elseif ($token) {
            $method = 'get_response_by_token';
            $params = array($session_key, $survey_id, $token);
        } else {
            // ดึงทั้งหมด
            $method = 'export_responses';
            $params = array(
                $session_key,
                $survey_id,
                'json',
                'th',
                'complete',
                'code',
                'short'
            );
        }
        
        $request_data = array(
            'method' => $method,
            'params' => $params,
            'id' => 2
        );
        
        $response = wp_remote_post($this->api_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($request_data),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['result'])) {
            // ถ้าเป็น export_responses จะได้ base64
            if ($method === 'export_responses') {
                $decoded = base64_decode($body['result']);
                return json_decode($decoded, true);
            }
            return $body['result'];
        }
        
        return array('error' => 'No data received');
    }
    
    /**
     * บันทึก response ลง WordPress
     */
    public function save_response_to_wp($survey_id, $response_data, $response_id = null, $token = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_hybrid_responses';
        
        // Check if exists
        $existing = null;
        if ($response_id) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE survey_id = %s AND response_id = %s",
                $survey_id,
                $response_id
            ));
        } elseif ($token) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE survey_id = %s AND token = %s",
                $survey_id,
                $token
            ));
        }
        
        $data = array(
            'survey_id' => $survey_id,
            'response_id' => $response_id,
            'token' => $token,
            'original_data' => json_encode($response_data),
            'modified_data' => json_encode($response_data),
            'status' => 'completed',
            'modified_by' => get_current_user_id()
        );
        
        if ($existing) {
            // Update
            $result = $wpdb->update(
                $table,
                $data,
                array('id' => $existing->id)
            );
            
            $this->log_sync_action($existing->id, 'fetch', 'success', 'Updated existing response');
            
            return $existing->id;
        } else {
            // Insert
            $data['created_by'] = get_current_user_id();
            $result = $wpdb->insert($table, $data);
            
            if ($result) {
                $new_id = $wpdb->insert_id;
                $this->log_sync_action($new_id, 'fetch', 'success', 'Created new response');
                return $new_id;
            }
        }
        
        return false;
    }
    
    /**
     * อัพเดทฟิลด์เดียว
     */
    public function update_response_field($response_id, $field_name, $field_value) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_hybrid_responses';
        
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $response_id
        ));
        
        if (!$response) {
            return false;
        }
        
        $modified_data = json_decode($response->modified_data, true);
        $modified_data[$field_name] = $field_value;
        
        $result = $wpdb->update(
            $table,
            array(
                'modified_data' => json_encode($modified_data),
                'status' => 'draft', // เปลี่ยนเป็น draft เพราะมีการแก้ไข
                'modified_by' => get_current_user_id()
            ),
            array('id' => $response_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Sync กลับไป LimeSurvey
     */
    public function sync_to_limesurvey($response_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_hybrid_responses';
        
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $response_id
        ));
        
        if (!$response) {
            return array('error' => 'Response not found');
        }
        
        $session_key = $this->connect_api();
        
        if (!$session_key) {
            return array('error' => 'Cannot connect to LimeSurvey API');
        }
        
        $modified_data = json_decode($response->modified_data, true);
        
        // Update response in LimeSurvey
        $request_data = array(
            'method' => 'update_response',
            'params' => array(
                $session_key,
                $response->survey_id,
                $modified_data
            ),
            'id' => 3
        );
        
        $api_response = wp_remote_post($this->api_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($api_response)) {
            $this->log_sync_action($response_id, 'push', 'failed', $api_response->get_error_message());
            return array('error' => $api_response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($api_response), true);
        
        if (isset($body['result']) && $body['result'] === true) {
            // Update status
            $wpdb->update(
                $table,
                array(
                    'status' => 'synced',
                    'synced_at' => current_time('mysql')
                ),
                array('id' => $response_id)
            );
            
            $this->log_sync_action($response_id, 'push', 'success', 'Synced to LimeSurvey');
            
            return array('success' => true);
        }
        
        $this->log_sync_action($response_id, 'push', 'failed', json_encode($body));
        return array('error' => 'Sync failed');
    }
    
    /**
     * Log sync action
     */
    private function log_sync_action($response_id, $action, $status, $details) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_hybrid_sync_log';
        
        $wpdb->insert($table, array(
            'response_id' => $response_id,
            'action' => $action,
            'status' => $status,
            'details' => $details,
            'user_id' => get_current_user_id()
        ));
    }
    
    /**
     * AJAX: Load survey iframe
     */
    public function ajax_load_survey() {
        check_ajax_referer('tpak_hybrid_nonce', 'nonce');
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        
        $iframe_url = $this->survey_url . '/index.php/' . $survey_id;
        if ($token) {
            $iframe_url .= '?token=' . $token;
        }
        
        wp_send_json_success(array(
            'iframe_url' => $iframe_url,
            'survey_id' => $survey_id
        ));
    }
    
    /**
     * AJAX: Fetch response from LimeSurvey
     */
    public function ajax_fetch_response() {
        check_ajax_referer('tpak_hybrid_nonce', 'nonce');
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $response_id = isset($_POST['response_id']) ? sanitize_text_field($_POST['response_id']) : null;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : null;
        
        $data = $this->fetch_response_data($survey_id, $response_id, $token);
        
        if (isset($data['error'])) {
            wp_send_json_error($data['error']);
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Save response to WordPress
     */
    public function ajax_save_response() {
        check_ajax_referer('tpak_hybrid_nonce', 'nonce');
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $response_data = json_decode(stripslashes($_POST['response_data']), true);
        $response_id = isset($_POST['response_id']) ? sanitize_text_field($_POST['response_id']) : null;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : null;
        
        $saved_id = $this->save_response_to_wp($survey_id, $response_data, $response_id, $token);
        
        if ($saved_id) {
            wp_send_json_success(array(
                'id' => $saved_id,
                'message' => 'Response saved successfully'
            ));
        } else {
            wp_send_json_error('Failed to save response');
        }
    }
    
    /**
     * AJAX: Update single field
     */
    public function ajax_update_field() {
        check_ajax_referer('tpak_hybrid_nonce', 'nonce');
        
        $response_id = intval($_POST['response_id']);
        $field_name = sanitize_text_field($_POST['field_name']);
        $field_value = sanitize_textarea_field($_POST['field_value']);
        
        $result = $this->update_response_field($response_id, $field_name, $field_value);
        
        if ($result) {
            wp_send_json_success('Field updated');
        } else {
            wp_send_json_error('Update failed');
        }
    }
    
    /**
     * AJAX: Sync to LimeSurvey
     */
    public function ajax_sync_to_limesurvey() {
        check_ajax_referer('tpak_hybrid_nonce', 'nonce');
        
        $response_id = intval($_POST['response_id']);
        
        $result = $this->sync_to_limesurvey($response_id);
        
        if (isset($result['success'])) {
            wp_send_json_success('Synced successfully');
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Get all responses for a survey
     */
    public function get_survey_responses($survey_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_hybrid_responses';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE survey_id = %s ORDER BY modified_at DESC",
            $survey_id
        ));
    }
    
    /**
     * Get single response
     */
    public function get_response($response_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_hybrid_responses';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $response_id
        ));
    }
}

// Initialize
TPAK_LimeSurvey_Hybrid_System::getInstance();