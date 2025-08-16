<?php
/**
 * TPAK DQ System - Survey Review & Approval Workflow
 * 
 * ระบบ Review และอนุมัติแบบสอบถามพร้อมการส่งต่อข้อมูล
 * รองรับการแก้ไข, ตรวจสอบ, อนุมัติ และส่งต่อไปยัง user ต่างๆ
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Survey_Review_Workflow {
    
    private static $instance = null;
    private $api_handler = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-api-handler.php';
        $this->api_handler = new TPAK_DQ_API_Handler();
        
        // Register custom capabilities
        add_action('init', array($this, 'register_capabilities'));
        
        // AJAX handlers
        add_action('wp_ajax_review_survey', array($this, 'handle_review_survey'));
        add_action('wp_ajax_approve_survey', array($this, 'handle_approve_survey'));
        add_action('wp_ajax_reject_survey', array($this, 'handle_reject_survey'));
        add_action('wp_ajax_forward_survey', array($this, 'handle_forward_survey'));
        add_action('wp_ajax_get_survey_history', array($this, 'get_survey_history'));
        add_action('wp_ajax_export_survey_data', array($this, 'export_survey_data'));
        add_action('wp_ajax_batch_process_surveys', array($this, 'batch_process_surveys'));
        
        // Email notifications
        add_action('tpak_survey_reviewed', array($this, 'send_review_notification'), 10, 2);
        add_action('tpak_survey_approved', array($this, 'send_approval_notification'), 10, 2);
        add_action('tpak_survey_rejected', array($this, 'send_rejection_notification'), 10, 2);
    }
    
    /**
     * Register custom capabilities
     */
    public function register_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('edit_survey_responses');
            $admin->add_cap('review_survey_responses');
            $admin->add_cap('approve_survey_responses');
            $admin->add_cap('export_survey_responses');
            $admin->add_cap('forward_survey_responses');
        }
        
        // สร้าง role สำหรับ reviewer
        if (!get_role('survey_reviewer')) {
            add_role('survey_reviewer', 'Survey Reviewer', array(
                'read' => true,
                'edit_posts' => true,
                'review_survey_responses' => true,
                'export_survey_responses' => true
            ));
        }
        
        // สร้าง role สำหรับ approver
        if (!get_role('survey_approver')) {
            add_role('survey_approver', 'Survey Approver', array(
                'read' => true,
                'edit_posts' => true,
                'review_survey_responses' => true,
                'approve_survey_responses' => true,
                'export_survey_responses' => true,
                'forward_survey_responses' => true
            ));
        }
    }
    
    /**
     * Handle survey review
     */
    public function handle_review_survey() {
        check_ajax_referer('survey_review_nonce', 'nonce');
        
        if (!current_user_can('review_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการตรวจสอบ');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $review_notes = sanitize_textarea_field($_POST['review_notes']);
        $review_status = sanitize_text_field($_POST['review_status']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_survey_responses';
        
        // อัปเดต review status
        $result = $wpdb->update(
            $table_name,
            array(
                'review_status' => $review_status,
                'review_notes' => $review_notes,
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('response_id' => $response_id)
        );
        
        if ($result !== false) {
            // บันทึก audit log
            $this->log_activity($response_id, 'reviewed', array(
                'status' => $review_status,
                'notes' => $review_notes
            ));
            
            // Trigger action สำหรับ notification
            do_action('tpak_survey_reviewed', $response_id, $review_status);
            
            wp_send_json_success(array(
                'message' => 'บันทึกการตรวจสอบเรียบร้อย',
                'status' => $review_status
            ));
        } else {
            wp_send_json_error('เกิดข้อผิดพลาดในการบันทึก');
        }
    }
    
    /**
     * Handle survey approval
     */
    public function handle_approve_survey() {
        check_ajax_referer('survey_approve_nonce', 'nonce');
        
        if (!current_user_can('approve_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการอนุมัติ');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $approval_notes = sanitize_textarea_field($_POST['approval_notes']);
        $forward_to = isset($_POST['forward_to']) ? array_map('intval', $_POST['forward_to']) : array();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_survey_responses';
        
        // อัปเดต approval status
        $result = $wpdb->update(
            $table_name,
            array(
                'review_status' => 'approved',
                'approval_notes' => $approval_notes,
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('response_id' => $response_id)
        );
        
        if ($result !== false) {
            // ส่งต่อไปยัง users
            if (!empty($forward_to)) {
                $this->forward_to_users($response_id, $forward_to);
            }
            
            // บันทึก audit log
            $this->log_activity($response_id, 'approved', array(
                'notes' => $approval_notes,
                'forwarded_to' => $forward_to
            ));
            
            // Trigger action
            do_action('tpak_survey_approved', $response_id, $forward_to);
            
            wp_send_json_success(array(
                'message' => 'อนุมัติแบบสอบถามเรียบร้อย',
                'forwarded_count' => count($forward_to)
            ));
        } else {
            wp_send_json_error('เกิดข้อผิดพลาดในการอนุมัติ');
        }
    }
    
    /**
     * Handle survey rejection
     */
    public function handle_reject_survey() {
        check_ajax_referer('survey_reject_nonce', 'nonce');
        
        if (!current_user_can('approve_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการส่งกลับแก้ไข');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $rejection_reason = sanitize_textarea_field($_POST['rejection_reason']);
        $return_to = intval($_POST['return_to']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_survey_responses';
        
        // อัปเดต rejection status
        $result = $wpdb->update(
            $table_name,
            array(
                'review_status' => 'rejected',
                'rejection_reason' => $rejection_reason,
                'rejected_by' => get_current_user_id(),
                'rejected_at' => current_time('mysql'),
                'returned_to' => $return_to,
                'updated_at' => current_time('mysql')
            ),
            array('response_id' => $response_id)
        );
        
        if ($result !== false) {
            // บันทึก audit log
            $this->log_activity($response_id, 'rejected', array(
                'reason' => $rejection_reason,
                'returned_to' => $return_to
            ));
            
            // Trigger action
            do_action('tpak_survey_rejected', $response_id, $return_to);
            
            wp_send_json_success(array(
                'message' => 'ส่งกลับแก้ไขเรียบร้อย'
            ));
        } else {
            wp_send_json_error('เกิดข้อผิดพลาดในการส่งกลับ');
        }
    }
    
    /**
     * Forward survey to users
     */
    private function forward_to_users($response_id, $user_ids) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_survey_forwards';
        
        // สร้างตารางถ้ายังไม่มี
        $this->ensure_forward_table();
        
        foreach ($user_ids as $user_id) {
            $wpdb->insert(
                $table_name,
                array(
                    'response_id' => $response_id,
                    'forwarded_to' => $user_id,
                    'forwarded_by' => get_current_user_id(),
                    'forwarded_at' => current_time('mysql'),
                    'status' => 'pending',
                    'access_token' => wp_generate_password(32, false)
                )
            );
            
            // ส่ง email แจ้งเตือน
            $this->send_forward_notification($response_id, $user_id);
        }
    }
    
    /**
     * Handle forward survey
     */
    public function handle_forward_survey() {
        check_ajax_referer('survey_forward_nonce', 'nonce');
        
        if (!current_user_can('forward_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการส่งต่อ');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $forward_to = array_map('intval', $_POST['forward_to']);
        $forward_notes = sanitize_textarea_field($_POST['forward_notes']);
        
        // ส่งต่อไปยัง users
        $this->forward_to_users($response_id, $forward_to);
        
        // บันทึก audit log
        $this->log_activity($response_id, 'forwarded', array(
            'to_users' => $forward_to,
            'notes' => $forward_notes
        ));
        
        wp_send_json_success(array(
            'message' => 'ส่งต่อแบบสอบถามเรียบร้อย',
            'forwarded_count' => count($forward_to)
        ));
    }
    
    /**
     * Get survey history
     */
    public function get_survey_history() {
        check_ajax_referer('survey_history_nonce', 'nonce');
        
        $response_id = sanitize_text_field($_POST['response_id']);
        
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $audit_table 
             WHERE response_id = %s 
             ORDER BY created_at DESC",
            $response_id
        ));
        
        $html = '<div class="survey-history">';
        
        if ($history) {
            $html .= '<ul class="history-timeline">';
            foreach ($history as $entry) {
                $user = get_userdata($entry->user_id);
                $action_data = json_decode($entry->action_data, true);
                
                $html .= '<li class="history-item">';
                $html .= '<div class="history-date">' . esc_html($entry->created_at) . '</div>';
                $html .= '<div class="history-action">';
                $html .= '<strong>' . $this->get_action_label($entry->action) . '</strong> ';
                $html .= 'โดย <em>' . esc_html($user ? $user->display_name : 'Unknown') . '</em>';
                
                if ($action_data) {
                    $html .= '<div class="history-details">';
                    $html .= $this->format_action_details($entry->action, $action_data);
                    $html .= '</div>';
                }
                
                $html .= '</div>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>ไม่มีประวัติการแก้ไข</p>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Export survey data
     */
    public function export_survey_data() {
        check_ajax_referer('survey_export_nonce', 'nonce');
        
        if (!current_user_can('export_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการ Export');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $format = sanitize_text_field($_POST['format']);
        
        // ดึงข้อมูลแบบสอบถาม
        $survey_data = $this->get_complete_survey_data($response_id);
        
        if (!$survey_data) {
            wp_send_json_error('ไม่พบข้อมูล');
        }
        
        switch ($format) {
            case 'pdf':
                $file_url = $this->export_to_pdf($survey_data);
                break;
                
            case 'excel':
                $file_url = $this->export_to_excel($survey_data);
                break;
                
            case 'json':
                $file_url = $this->export_to_json($survey_data);
                break;
                
            default:
                wp_send_json_error('รูปแบบไม่ถูกต้อง');
        }
        
        // บันทึก audit log
        $this->log_activity($response_id, 'exported', array(
            'format' => $format
        ));
        
        wp_send_json_success(array(
            'file_url' => $file_url,
            'message' => 'Export เรียบร้อย'
        ));
    }
    
    /**
     * Batch process surveys
     */
    public function batch_process_surveys() {
        check_ajax_referer('batch_process_nonce', 'nonce');
        
        if (!current_user_can('approve_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการประมวลผลแบบกลุ่ม');
        }
        
        $response_ids = array_map('sanitize_text_field', $_POST['response_ids']);
        $action = sanitize_text_field($_POST['batch_action']);
        
        $processed = 0;
        $errors = array();
        
        foreach ($response_ids as $response_id) {
            switch ($action) {
                case 'approve_all':
                    $result = $this->quick_approve($response_id);
                    break;
                    
                case 'forward_all':
                    $forward_to = array_map('intval', $_POST['forward_to']);
                    $result = $this->quick_forward($response_id, $forward_to);
                    break;
                    
                case 'export_all':
                    $result = $this->quick_export($response_id);
                    break;
                    
                default:
                    $result = false;
            }
            
            if ($result) {
                $processed++;
            } else {
                $errors[] = $response_id;
            }
        }
        
        wp_send_json_success(array(
            'processed' => $processed,
            'errors' => $errors,
            'message' => "ประมวลผลสำเร็จ $processed รายการ"
        ));
    }
    
    /**
     * Quick approve
     */
    private function quick_approve($response_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_survey_responses';
        
        return $wpdb->update(
            $table_name,
            array(
                'review_status' => 'approved',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql')
            ),
            array('response_id' => $response_id)
        );
    }
    
    /**
     * Quick forward
     */
    private function quick_forward($response_id, $user_ids) {
        $this->forward_to_users($response_id, $user_ids);
        return true;
    }
    
    /**
     * Quick export
     */
    private function quick_export($response_id) {
        $survey_data = $this->get_complete_survey_data($response_id);
        if ($survey_data) {
            $this->export_to_json($survey_data);
            return true;
        }
        return false;
    }
    
    /**
     * Get complete survey data
     */
    private function get_complete_survey_data($response_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_survey_responses';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE response_id = %s",
            $response_id
        ), ARRAY_A);
        
        if ($data) {
            $data['response_data'] = json_decode($data['response_data'], true);
            $data['audit_trail'] = $this->get_audit_trail($response_id);
            return $data;
        }
        
        return null;
    }
    
    /**
     * Get audit trail
     */
    private function get_audit_trail($response_id) {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $audit_table 
             WHERE response_id = %s 
             ORDER BY created_at DESC",
            $response_id
        ), ARRAY_A);
    }
    
    /**
     * Export to PDF
     */
    private function export_to_pdf($survey_data) {
        // ใช้ library เช่น TCPDF หรือ mPDF
        // ตัวอย่างเบื้องต้น
        $upload_dir = wp_upload_dir();
        $file_name = 'survey_' . $survey_data['response_id'] . '_' . time() . '.pdf';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        // TODO: Implement PDF generation
        // ตอนนี้สร้างไฟล์ dummy
        file_put_contents($file_path, 'PDF Content');
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * Export to Excel
     */
    private function export_to_excel($survey_data) {
        // ใช้ library เช่น PHPSpreadsheet
        $upload_dir = wp_upload_dir();
        $file_name = 'survey_' . $survey_data['response_id'] . '_' . time() . '.xlsx';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        // TODO: Implement Excel generation
        // ตอนนี้สร้างไฟล์ dummy
        file_put_contents($file_path, 'Excel Content');
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * Export to JSON
     */
    private function export_to_json($survey_data) {
        $upload_dir = wp_upload_dir();
        $file_name = 'survey_' . $survey_data['response_id'] . '_' . time() . '.json';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        // บันทึกไฟล์ JSON
        file_put_contents($file_path, json_encode($survey_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * Log activity
     */
    private function log_activity($response_id, $action, $data = array()) {
        global $wpdb;
        $current_user = wp_get_current_user();
        
        $wpdb->insert(
            $wpdb->prefix . 'tpak_survey_audit',
            array(
                'response_id' => $response_id,
                'action' => $action,
                'action_data' => json_encode($data),
                'user_id' => $current_user->ID,
                'user_name' => $current_user->display_name,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Ensure forward table exists
     */
    private function ensure_forward_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'tpak_survey_forwards';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            response_id varchar(100) NOT NULL,
            forwarded_to bigint(20) NOT NULL,
            forwarded_by bigint(20) NOT NULL,
            forwarded_at datetime DEFAULT CURRENT_TIMESTAMP,
            viewed_at datetime,
            status varchar(20) DEFAULT 'pending',
            access_token varchar(100),
            notes text,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY forwarded_to (forwarded_to),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Send forward notification
     */
    private function send_forward_notification($response_id, $user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'แบบสอบถามถูกส่งต่อให้คุณตรวจสอบ';
        $message = sprintf(
            'สวัสดีคุณ %s,\n\nมีแบบสอบถาม (ID: %s) ถูกส่งต่อให้คุณตรวจสอบ\n\nกรุณาเข้าระบบเพื่อดูรายละเอียด: %s',
            $user->display_name,
            $response_id,
            admin_url('post.php?action=view_survey&response_id=' . $response_id)
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send review notification
     */
    public function send_review_notification($response_id, $status) {
        // ส่ง email แจ้งเตือนผู้ที่เกี่ยวข้อง
        $subject = 'แบบสอบถามได้รับการตรวจสอบแล้ว';
        $message = sprintf('แบบสอบถาม ID: %s สถานะ: %s', $response_id, $status);
        
        // TODO: กำหนดผู้รับ email
    }
    
    /**
     * Send approval notification
     */
    public function send_approval_notification($response_id, $forward_to) {
        // ส่ง email แจ้งเตือนการอนุมัติ
        $subject = 'แบบสอบถามได้รับการอนุมัติ';
        $message = sprintf('แบบสอบถาม ID: %s ได้รับการอนุมัติเรียบร้อย', $response_id);
        
        // TODO: กำหนดผู้รับ email
    }
    
    /**
     * Send rejection notification  
     */
    public function send_rejection_notification($response_id, $return_to) {
        $user = get_userdata($return_to);
        if (!$user) return;
        
        $subject = 'แบบสอบถามถูกส่งกลับแก้ไข';
        $message = sprintf(
            'สวัสดีคุณ %s,\n\nแบบสอบถาม ID: %s ถูกส่งกลับให้แก้ไข\n\nกรุณาเข้าระบบเพื่อดูรายละเอียด',
            $user->display_name,
            $response_id
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Get action label
     */
    private function get_action_label($action) {
        $labels = array(
            'save_draft' => 'บันทึกฉบับร่าง',
            'submit' => 'ส่งแบบสอบถาม',
            'reviewed' => 'ตรวจสอบ',
            'approved' => 'อนุมัติ',
            'rejected' => 'ส่งกลับแก้ไข',
            'forwarded' => 'ส่งต่อ',
            'exported' => 'Export ข้อมูล',
            'edited' => 'แก้ไข'
        );
        
        return isset($labels[$action]) ? $labels[$action] : $action;
    }
    
    /**
     * Format action details
     */
    private function format_action_details($action, $data) {
        $html = '';
        
        switch ($action) {
            case 'reviewed':
                if (isset($data['notes'])) {
                    $html .= '<p>หมายเหตุ: ' . esc_html($data['notes']) . '</p>';
                }
                break;
                
            case 'forwarded':
                if (isset($data['to_users'])) {
                    $html .= '<p>ส่งต่อไปยัง: ';
                    $users = array();
                    foreach ($data['to_users'] as $user_id) {
                        $user = get_userdata($user_id);
                        if ($user) {
                            $users[] = $user->display_name;
                        }
                    }
                    $html .= implode(', ', $users) . '</p>';
                }
                break;
                
            case 'exported':
                if (isset($data['format'])) {
                    $html .= '<p>รูปแบบ: ' . strtoupper($data['format']) . '</p>';
                }
                break;
        }
        
        return $html;
    }
}