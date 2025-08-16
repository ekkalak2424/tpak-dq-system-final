<?php
/**
 * LimeSurvey Iframe Integration Handler
 * สำหรับจัดการการดึงข้อมูลจาก iframe และบันทึกลง WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Iframe_Survey_Handler {
    
    public function __construct() {
        add_action('wp_ajax_save_iframe_survey_data', array($this, 'save_iframe_survey_data'));
        add_action('wp_ajax_load_saved_survey_data', array($this, 'load_saved_survey_data'));
        add_action('wp_ajax_update_survey_response', array($this, 'update_survey_response'));
        
        // Create table for iframe survey data
        add_action('init', array($this, 'create_iframe_tables'));
    }
    
    /**
     * สร้างตารางสำหรับเก็บข้อมูล iframe survey
     */
    public function create_iframe_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_iframe_survey_data';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            survey_id varchar(50) NOT NULL,
            response_id varchar(50) NOT NULL,
            wordpress_post_id int(11) DEFAULT NULL,
            response_data longtext NOT NULL,
            raw_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            user_id int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY survey_response (survey_id, response_id),
            KEY wordpress_post (wordpress_post_id),
            KEY created_date (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // สร้างตารางสำหรับ audit log
        $audit_table = $wpdb->prefix . 'tpak_iframe_audit_log';
        
        $audit_sql = "CREATE TABLE IF NOT EXISTS $audit_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            survey_data_id int(11) NOT NULL,
            action varchar(50) NOT NULL,
            old_data longtext DEFAULT NULL,
            new_data longtext DEFAULT NULL,
            user_id int(11) DEFAULT NULL,
            user_name varchar(100) DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY survey_data (survey_data_id),
            KEY action_time (action, timestamp)
        ) $charset_collate;";
        
        dbDelta($audit_sql);
    }
    
    /**
     * บันทึกข้อมูลจาก iframe
     */
    public function save_iframe_survey_data() {
        // ตรวจสอบ nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_ajax_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $response_id = sanitize_text_field($_POST['response_id']);
        $response_data = $_POST['response_data']; // JSON string
        
        if (empty($survey_id) || empty($response_data)) {
            wp_send_json_error('Missing required data');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_iframe_survey_data';
        
        // ค้นหาข้อมูลเดิม
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE survey_id = %s AND response_id = %s",
            $survey_id,
            $response_id
        ));
        
        // เตรียมข้อมูลสำหรับบันทึก
        $data = array(
            'survey_id' => $survey_id,
            'response_id' => $response_id,
            'response_data' => $response_data,
            'raw_data' => $response_data, // เก็บข้อมูลดิบไว้ด้วย
            'user_id' => get_current_user_id(),
            'updated_at' => current_time('mysql')
        );
        
        if ($existing) {
            // อัพเดทข้อมูลเดิม
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing->id),
                array('%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                // บันทึก audit log
                $this->log_audit_action($existing->id, 'update', $existing->response_data, $response_data);
                
                wp_send_json_success(array(
                    'message' => 'Data updated successfully',
                    'data_id' => $existing->id,
                    'action' => 'update'
                ));
            } else {
                wp_send_json_error('Failed to update data');
            }
        } else {
            // สร้างข้อมูลใหม่
            $data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert($table_name, $data);
            
            if ($result) {
                $new_id = $wpdb->insert_id;
                
                // บันทึก audit log
                $this->log_audit_action($new_id, 'create', null, $response_data);
                
                wp_send_json_success(array(
                    'message' => 'Data saved successfully',
                    'data_id' => $new_id,
                    'action' => 'create'
                ));
            } else {
                wp_send_json_error('Failed to save data');
            }
        }
    }
    
    /**
     * โหลดข้อมูลที่บันทึกไว้
     */
    public function load_saved_survey_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_ajax_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $response_id = sanitize_text_field($_POST['response_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_iframe_survey_data';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE survey_id = %s AND response_id = %s AND status = 'active'",
            $survey_id,
            $response_id
        ));
        
        if ($data) {
            wp_send_json_success(array(
                'data' => $data,
                'response_data' => json_decode($data->response_data, true),
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at
            ));
        } else {
            wp_send_json_error('No data found');
        }
    }
    
    /**
     * อัพเดทคำตอบ (สำหรับระบบแก้ไข)
     */
    public function update_survey_response() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_ajax_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $data_id = intval($_POST['data_id']);
        $field_name = sanitize_text_field($_POST['field_name']);
        $field_value = sanitize_textarea_field($_POST['field_value']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_iframe_survey_data';
        
        // ดึงข้อมูลเดิม
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $data_id
        ));
        
        if (!$existing) {
            wp_send_json_error('Data not found');
            return;
        }
        
        // แปลง JSON และแก้ไขค่า
        $response_data = json_decode($existing->response_data, true);
        $old_value = isset($response_data[$field_name]) ? $response_data[$field_name] : null;
        $response_data[$field_name] = $field_value;
        
        // บันทึกกลับ
        $result = $wpdb->update(
            $table_name,
            array(
                'response_data' => json_encode($response_data),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $data_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // บันทึก audit log สำหรับการแก้ไขแต่ละฟิลด์
            $this->log_audit_action($data_id, 'field_update', 
                json_encode(array($field_name => $old_value)), 
                json_encode(array($field_name => $field_value))
            );
            
            wp_send_json_success(array(
                'message' => 'Field updated successfully',
                'field_name' => $field_name,
                'old_value' => $old_value,
                'new_value' => $field_value
            ));
        } else {
            wp_send_json_error('Failed to update field');
        }
    }
    
    /**
     * บันทึก audit log
     */
    private function log_audit_action($data_id, $action, $old_data, $new_data) {
        global $wpdb;
        
        $audit_table = $wpdb->prefix . 'tpak_iframe_audit_log';
        $current_user = wp_get_current_user();
        
        $wpdb->insert(
            $audit_table,
            array(
                'survey_data_id' => $data_id,
                'action' => $action,
                'old_data' => $old_data,
                'new_data' => $new_data,
                'user_id' => get_current_user_id(),
                'user_name' => $current_user->display_name,
                'timestamp' => current_time('mysql'),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * ดึง IP address ของ client
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * ดึงประวัติการแก้ไข
     */
    public function get_audit_trail($data_id) {
        global $wpdb;
        
        $audit_table = $wpdb->prefix . 'tpak_iframe_audit_log';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $audit_table WHERE survey_data_id = %d ORDER BY timestamp DESC",
            $data_id
        ));
    }
    
    /**
     * ดึงข้อมูลทั้งหมดของ survey
     */
    public function get_survey_data($survey_id, $response_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_iframe_survey_data';
        
        if ($response_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE survey_id = %s AND response_id = %s AND status = 'active'",
                $survey_id,
                $response_id
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE survey_id = %s AND status = 'active' ORDER BY updated_at DESC",
                $survey_id
            ));
        }
    }
}

// Initialize the class
new TPAK_Iframe_Survey_Handler();