<?php
/**
 * TPAK DQ System - Survey Audit Manager
 * 
 * ระบบจัดการ Audit Log สำหรับการแก้ไขแบบสอบถาม
 * ติดตามการเปลี่ยนแปลงทุกครั้งพร้อมรายละเอียดสมบูรณ์
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Survey_Audit_Manager {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // สร้างตาราง audit เมื่อ activate plugin
        register_activation_hook(TPAK_DQ_SYSTEM_PLUGIN_FILE, array($this, 'create_audit_tables'));
        
        // AJAX handlers
        add_action('wp_ajax_get_audit_details', array($this, 'get_audit_details'));
        add_action('wp_ajax_get_audit_summary', array($this, 'get_audit_summary'));
        add_action('wp_ajax_export_audit_log', array($this, 'export_audit_log'));
        add_action('wp_ajax_compare_versions', array($this, 'compare_versions'));
        add_action('wp_ajax_restore_version', array($this, 'restore_version'));
        
        // Auto-cleanup old logs
        add_action('tpak_cleanup_audit_logs', array($this, 'cleanup_old_logs'));
        
        // Schedule cleanup
        if (!wp_next_scheduled('tpak_cleanup_audit_logs')) {
            wp_schedule_event(time(), 'daily', 'tpak_cleanup_audit_logs');
        }
    }
    
    /**
     * สร้างตาราง audit logs
     */
    public function create_audit_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // ตาราง audit หลัก
        $table_audit = $wpdb->prefix . 'tpak_survey_audit';
        $sql_audit = "CREATE TABLE IF NOT EXISTS $table_audit (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            response_id varchar(100) NOT NULL,
            action varchar(50) NOT NULL,
            action_data longtext,
            field_changes longtext,
            before_data longtext,
            after_data longtext,
            user_id bigint(20),
            user_name varchar(100),
            user_role varchar(50),
            ip_address varchar(45),
            user_agent text,
            session_id varchar(100),
            request_uri varchar(500),
            severity enum('low', 'medium', 'high', 'critical') DEFAULT 'low',
            status enum('success', 'failed', 'pending') DEFAULT 'success',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY action (action),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY severity (severity),
            KEY status (status)
        ) $charset_collate;";
        
        // ตาราง field changes รายละเอียด
        $table_field_changes = $wpdb->prefix . 'tpak_survey_field_changes';
        $sql_field_changes = "CREATE TABLE IF NOT EXISTS $table_field_changes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            audit_id bigint(20) NOT NULL,
            response_id varchar(100) NOT NULL,
            field_name varchar(100) NOT NULL,
            field_type varchar(50),
            old_value text,
            new_value text,
            change_type enum('created', 'updated', 'deleted') DEFAULT 'updated',
            validation_status enum('valid', 'invalid', 'warning') DEFAULT 'valid',
            validation_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY audit_id (audit_id),
            KEY response_id (response_id),
            KEY field_name (field_name),
            KEY change_type (change_type),
            FOREIGN KEY (audit_id) REFERENCES $table_audit(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // ตาราง user sessions
        $table_sessions = $wpdb->prefix . 'tpak_user_sessions';
        $sql_sessions = "CREATE TABLE IF NOT EXISTS $table_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_id bigint(20) NOT NULL,
            login_time datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            is_active tinyint(1) DEFAULT 1,
            logout_time datetime,
            session_data longtext,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_audit);
        dbDelta($sql_field_changes);
        dbDelta($sql_sessions);
    }
    
    /**
     * บันทึก audit log รายละเอียดสมบูรณ์
     */
    public function log_audit($response_id, $action, $data = array()) {
        global $wpdb;
        $current_user = wp_get_current_user();
        $session_id = $this->get_or_create_session();
        
        // วิเคราะห์ความสำคัญของ action
        $severity = $this->get_action_severity($action);
        
        // เตรียมข้อมูล audit
        $audit_data = array(
            'response_id' => $response_id,
            'action' => $action,
            'action_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'user_id' => $current_user->ID,
            'user_name' => $current_user->display_name,
            'user_role' => implode(', ', $current_user->roles),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => $session_id,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'severity' => $severity,
            'status' => 'success',
            'created_at' => current_time('mysql')
        );
        
        // บันทึกข้อมูล before/after ถ้ามี
        if (isset($data['before_data'])) {
            $audit_data['before_data'] = json_encode($data['before_data'], JSON_UNESCAPED_UNICODE);
        }
        
        if (isset($data['after_data'])) {
            $audit_data['after_data'] = json_encode($data['after_data'], JSON_UNESCAPED_UNICODE);
        }
        
        // บันทึก field changes
        if (isset($data['field_changes'])) {
            $audit_data['field_changes'] = json_encode($data['field_changes'], JSON_UNESCAPED_UNICODE);
        }
        
        // บันทึกลงฐานข้อมูล
        $result = $wpdb->insert(
            $wpdb->prefix . 'tpak_survey_audit',
            $audit_data
        );
        
        if ($result) {
            $audit_id = $wpdb->insert_id;
            
            // บันทึก field changes รายละเอียด
            if (isset($data['field_changes']) && is_array($data['field_changes'])) {
                $this->log_field_changes($audit_id, $response_id, $data['field_changes']);
            }
            
            // อัปเดต session activity
            $this->update_session_activity($session_id);
            
            return $audit_id;
        }
        
        return false;
    }
    
    /**
     * บันทึก field changes รายละเอียด
     */
    private function log_field_changes($audit_id, $response_id, $field_changes) {
        global $wpdb;
        
        foreach ($field_changes as $change) {
            $change_data = array(
                'audit_id' => $audit_id,
                'response_id' => $response_id,
                'field_name' => $change['field'],
                'field_type' => $change['type'] ?? 'text',
                'old_value' => $change['old_value'],
                'new_value' => $change['new_value'],
                'change_type' => $this->determine_change_type($change['old_value'], $change['new_value']),
                'validation_status' => $change['validation_status'] ?? 'valid',
                'validation_message' => $change['validation_message'] ?? '',
                'created_at' => current_time('mysql')
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'tpak_survey_field_changes',
                $change_data
            );
        }
    }
    
    /**
     * ดึงรายละเอียด audit
     */
    public function get_audit_details() {
        check_ajax_referer('audit_details_nonce', 'nonce');
        
        if (!current_user_can('review_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการดู Audit Log');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $limit = intval($_POST['limit'] ?? 50);
        $offset = intval($_POST['offset'] ?? 0);
        
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        $field_changes_table = $wpdb->prefix . 'tpak_survey_field_changes';
        
        // ดึงข้อมูล audit หลัก
        $audits = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $audit_table 
             WHERE response_id = %s 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $response_id, $limit, $offset
        ), ARRAY_A);
        
        // เพิ่มข้อมูล field changes
        foreach ($audits as &$audit) {
            $field_changes = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $field_changes_table 
                 WHERE audit_id = %d 
                 ORDER BY field_name",
                $audit['id']
            ), ARRAY_A);
            
            $audit['field_changes_detail'] = $field_changes;
            $audit['action_data'] = json_decode($audit['action_data'], true);
            $audit['before_data'] = json_decode($audit['before_data'], true);
            $audit['after_data'] = json_decode($audit['after_data'], true);
        }
        
        // นับจำนวนทั้งหมด
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $audit_table WHERE response_id = %s",
            $response_id
        ));
        
        wp_send_json_success(array(
            'audits' => $audits,
            'total' => $total,
            'has_more' => ($offset + $limit) < $total
        ));
    }
    
    /**
     * ดึงสรุป audit
     */
    public function get_audit_summary() {
        check_ajax_referer('audit_summary_nonce', 'nonce');
        
        if (!current_user_can('review_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการดู Audit Summary');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $days = intval($_POST['days'] ?? 30);
        
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        
        // สถิติการแก้ไข
        $edit_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                action,
                COUNT(*) as count,
                DATE(created_at) as date
             FROM $audit_table 
             WHERE response_id = %s 
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY action, DATE(created_at)
             ORDER BY created_at DESC",
            $response_id, $days
        ), ARRAY_A);
        
        // ผู้ใช้ที่แก้ไข
        $user_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                user_name,
                user_role,
                COUNT(*) as edit_count,
                MAX(created_at) as last_edit
             FROM $audit_table 
             WHERE response_id = %s 
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY user_id, user_name, user_role
             ORDER BY edit_count DESC",
            $response_id, $days
        ), ARRAY_A);
        
        // สถิติ severity
        $severity_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                severity,
                COUNT(*) as count
             FROM $audit_table 
             WHERE response_id = %s 
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY severity",
            $response_id, $days
        ), ARRAY_A);
        
        // Field ที่แก้ไขบ่อยที่สุด
        $field_changes_table = $wpdb->prefix . 'tpak_survey_field_changes';
        $field_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                fc.field_name,
                fc.field_type,
                COUNT(*) as change_count
             FROM $field_changes_table fc
             JOIN $audit_table a ON fc.audit_id = a.id
             WHERE fc.response_id = %s 
               AND a.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY fc.field_name, fc.field_type
             ORDER BY change_count DESC
             LIMIT 10",
            $response_id, $days
        ), ARRAY_A);
        
        wp_send_json_success(array(
            'edit_stats' => $edit_stats,
            'user_stats' => $user_stats,
            'severity_stats' => $severity_stats,
            'field_stats' => $field_stats,
            'period_days' => $days
        ));
    }
    
    /**
     * Export audit log
     */
    public function export_audit_log() {
        check_ajax_referer('export_audit_nonce', 'nonce');
        
        if (!current_user_can('export_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการ Export Audit Log');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $format = sanitize_text_field($_POST['format'] ?? 'excel');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');
        
        try {
            $audit_data = $this->get_audit_export_data($response_id, $date_from, $date_to);
            
            switch ($format) {
                case 'excel':
                    $file_url = $this->export_audit_to_excel($audit_data, $response_id);
                    break;
                case 'json':
                    $file_url = $this->export_audit_to_json($audit_data, $response_id);
                    break;
                case 'csv':
                    $file_url = $this->export_audit_to_csv($audit_data, $response_id);
                    break;
                default:
                    throw new Exception('รูปแบบไฟล์ไม่ถูกต้อง');
            }
            
            wp_send_json_success(array(
                'file_url' => $file_url,
                'message' => 'Export Audit Log เรียบร้อย'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * เปรียบเทียบ versions
     */
    public function compare_versions() {
        check_ajax_referer('compare_versions_nonce', 'nonce');
        
        if (!current_user_can('review_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการเปรียบเทียบ');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $version1_id = intval($_POST['version1_id']);
        $version2_id = intval($_POST['version2_id']);
        
        try {
            $comparison = $this->generate_version_comparison($response_id, $version1_id, $version2_id);
            
            wp_send_json_success(array(
                'comparison' => $comparison,
                'html' => $this->render_comparison_html($comparison)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * Restore version
     */
    public function restore_version() {
        check_ajax_referer('restore_version_nonce', 'nonce');
        
        if (!current_user_can('edit_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการ Restore');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $audit_id = intval($_POST['audit_id']);
        $restore_reason = sanitize_textarea_field($_POST['restore_reason'] ?? '');
        
        try {
            $result = $this->perform_version_restore($response_id, $audit_id, $restore_reason);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Restore version เรียบร้อย',
                    'new_audit_id' => $result
                ));
            } else {
                wp_send_json_error('ไม่สามารถ Restore ได้');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * ทำความสะอาด audit logs เก่า
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = apply_filters('tpak_audit_retention_days', 365); // เก็บ 1 ปี
        
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        $field_changes_table = $wpdb->prefix . 'tpak_survey_field_changes';
        
        // ลบ audit logs เก่า
        $deleted_audits = $wpdb->query($wpdb->prepare(
            "DELETE FROM $audit_table 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
               AND severity NOT IN ('high', 'critical')",
            $retention_days
        ));
        
        // ลบ field changes ที่ไม่มี audit แล้ว
        $deleted_changes = $wpdb->query(
            "DELETE fc FROM $field_changes_table fc
             LEFT JOIN $audit_table a ON fc.audit_id = a.id
             WHERE a.id IS NULL"
        );
        
        // บันทึก log การทำความสะอาด
        error_log("TPAK Audit Cleanup: Deleted $deleted_audits audit logs and $deleted_changes field changes");
    }
    
    /**
     * ดึงข้อมูลสำหรับ export audit
     */
    private function get_audit_export_data($response_id, $date_from = '', $date_to = '') {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        $field_changes_table = $wpdb->prefix . 'tpak_survey_field_changes';
        
        $where_clause = "WHERE a.response_id = %s";
        $params = array($response_id);
        
        if ($date_from) {
            $where_clause .= " AND a.created_at >= %s";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $where_clause .= " AND a.created_at <= %s";
            $params[] = $date_to . ' 23:59:59';
        }
        
        $sql = "SELECT 
                    a.*,
                    GROUP_CONCAT(
                        CONCAT(fc.field_name, ':', fc.old_value, '->', fc.new_value)
                        SEPARATOR '; '
                    ) as field_changes_summary
                FROM $audit_table a
                LEFT JOIN $field_changes_table fc ON a.id = fc.audit_id
                $where_clause
                GROUP BY a.id
                ORDER BY a.created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }
    
    /**
     * Export audit เป็น Excel
     */
    private function export_audit_to_excel($audit_data, $response_id) {
        // ใช้ PHPSpreadsheet (ต้องติดตั้งแยก)
        $upload_dir = wp_upload_dir();
        $file_name = 'audit_log_' . $response_id . '_' . time() . '.xlsx';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        // TODO: Implement Excel generation with PHPSpreadsheet
        // ตอนนี้สร้างไฟล์ CSV แทน
        $csv_content = $this->generate_audit_csv_content($audit_data);
        file_put_contents($file_path, $csv_content);
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * Export audit เป็น JSON
     */
    private function export_audit_to_json($audit_data, $response_id) {
        $upload_dir = wp_upload_dir();
        $file_name = 'audit_log_' . $response_id . '_' . time() . '.json';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        file_put_contents($file_path, json_encode($audit_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * Export audit เป็น CSV
     */
    private function export_audit_to_csv($audit_data, $response_id) {
        $upload_dir = wp_upload_dir();
        $file_name = 'audit_log_' . $response_id . '_' . time() . '.csv';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        $csv_content = $this->generate_audit_csv_content($audit_data);
        file_put_contents($file_path, $csv_content);
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * สร้างเนื้อหา CSV
     */
    private function generate_audit_csv_content($audit_data) {
        $csv = "วันที่/เวลา,การกระทำ,ผู้ใช้,บทบาท,IP Address,รายละเอียด,ความสำคัญ,สถานะ\n";
        
        foreach ($audit_data as $row) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,\"%s\",%s,%s\n",
                $row['created_at'],
                $row['action'],
                $row['user_name'],
                $row['user_role'],
                $row['ip_address'],
                str_replace('"', '""', $row['field_changes_summary'] ?? ''),
                $row['severity'],
                $row['status']
            );
        }
        
        return "\xEF\xBB\xBF" . $csv; // เพิ่ม BOM สำหรับ UTF-8
    }
    
    /**
     * สร้างการเปรียบเทียบ versions
     */
    private function generate_version_comparison($response_id, $version1_id, $version2_id) {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        
        $version1 = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $audit_table WHERE id = %d AND response_id = %s",
            $version1_id, $response_id
        ), ARRAY_A);
        
        $version2 = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $audit_table WHERE id = %d AND response_id = %s",
            $version2_id, $response_id
        ), ARRAY_A);
        
        if (!$version1 || !$version2) {
            throw new Exception('ไม่พบ version ที่ระบุ');
        }
        
        $data1 = json_decode($version1['after_data'], true) ?? array();
        $data2 = json_decode($version2['after_data'], true) ?? array();
        
        $differences = array();
        $all_fields = array_unique(array_merge(array_keys($data1), array_keys($data2)));
        
        foreach ($all_fields as $field) {
            $value1 = $data1[$field] ?? null;
            $value2 = $data2[$field] ?? null;
            
            if ($value1 !== $value2) {
                $differences[$field] = array(
                    'field' => $field,
                    'version1_value' => $value1,
                    'version2_value' => $value2,
                    'change_type' => $this->determine_change_type($value1, $value2)
                );
            }
        }
        
        return array(
            'version1' => $version1,
            'version2' => $version2,
            'differences' => $differences
        );
    }
    
    /**
     * Render HTML การเปรียบเทียบ
     */
    private function render_comparison_html($comparison) {
        $html = '<div class="version-comparison">';
        
        $html .= '<div class="comparison-header">';
        $html .= '<div class="version-info">';
        $html .= '<h4>Version 1</h4>';
        $html .= '<p>วันที่: ' . esc_html($comparison['version1']['created_at']) . '</p>';
        $html .= '<p>ผู้แก้ไข: ' . esc_html($comparison['version1']['user_name']) . '</p>';
        $html .= '</div>';
        
        $html .= '<div class="version-info">';
        $html .= '<h4>Version 2</h4>';
        $html .= '<p>วันที่: ' . esc_html($comparison['version2']['created_at']) . '</p>';
        $html .= '<p>ผู้แก้ไข: ' . esc_html($comparison['version2']['user_name']) . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        
        if (empty($comparison['differences'])) {
            $html .= '<p>ไม่มีความแตกต่าง</p>';
        } else {
            $html .= '<div class="differences">';
            $html .= '<h4>ความแตกต่าง (' . count($comparison['differences']) . ' รายการ)</h4>';
            
            foreach ($comparison['differences'] as $diff) {
                $html .= '<div class="diff-item">';
                $html .= '<strong>' . esc_html($diff['field']) . '</strong>';
                $html .= '<div class="diff-values">';
                $html .= '<div class="old-value">เก่า: ' . esc_html($diff['version1_value']) . '</div>';
                $html .= '<div class="new-value">ใหม่: ' . esc_html($diff['version2_value']) . '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * ทำการ restore version
     */
    private function perform_version_restore($response_id, $audit_id, $restore_reason) {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        $responses_table = $wpdb->prefix . 'tpak_survey_responses';
        
        // ดึงข้อมูลจาก audit ที่จะ restore
        $restore_audit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $audit_table WHERE id = %d AND response_id = %s",
            $audit_id, $response_id
        ), ARRAY_A);
        
        if (!$restore_audit) {
            throw new Exception('ไม่พบข้อมูล audit ที่จะ restore');
        }
        
        $restore_data = json_decode($restore_audit['after_data'], true);
        
        if (!$restore_data) {
            throw new Exception('ไม่มีข้อมูลสำหรับ restore');
        }
        
        // ดึงข้อมูลปัจจุบัน
        $current_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $responses_table WHERE response_id = %s",
            $response_id
        ), ARRAY_A);
        
        if (!$current_data) {
            throw new Exception('ไม่พบข้อมูลปัจจุบัน');
        }
        
        $current_response_data = json_decode($current_data['response_data'], true);
        
        // อัปเดตข้อมูล
        $new_response_data = $current_response_data;
        $new_response_data['responses'] = $restore_data;
        
        $result = $wpdb->update(
            $responses_table,
            array(
                'response_data' => json_encode($new_response_data),
                'updated_at' => current_time('mysql')
            ),
            array('response_id' => $response_id)
        );
        
        if ($result !== false) {
            // บันทึก audit log การ restore
            return $this->log_audit($response_id, 'restored', array(
                'restored_from_audit_id' => $audit_id,
                'restore_reason' => $restore_reason,
                'before_data' => $current_response_data['responses'],
                'after_data' => $restore_data
            ));
        }
        
        return false;
    }
    
    /**
     * ดึงหรือสร้าง session ID
     */
    private function get_or_create_session() {
        $session_id = session_id();
        
        if (empty($session_id)) {
            if (!session_start()) {
                session_start();
            }
            $session_id = session_id();
        }
        
        // บันทึก session ลงฐานข้อมูล
        $this->track_user_session($session_id);
        
        return $session_id;
    }
    
    /**
     * ติดตาม user session
     */
    private function track_user_session($session_id) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'tpak_user_sessions';
        $current_user = wp_get_current_user();
        
        // ตรวจสอบว่ามี session อยู่แล้วหรือไม่
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE session_id = %s",
            $session_id
        ));
        
        if (!$existing) {
            // สร้าง session ใหม่
            $wpdb->insert(
                $sessions_table,
                array(
                    'session_id' => $session_id,
                    'user_id' => $current_user->ID,
                    'login_time' => current_time('mysql'),
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'is_active' => 1
                )
            );
        } else {
            // อัปเดต last activity
            $wpdb->update(
                $sessions_table,
                array(
                    'last_activity' => current_time('mysql'),
                    'is_active' => 1
                ),
                array('session_id' => $session_id)
            );
        }
    }
    
    /**
     * อัปเดต session activity
     */
    private function update_session_activity($session_id) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'tpak_user_sessions';
        
        $wpdb->update(
            $sessions_table,
            array('last_activity' => current_time('mysql')),
            array('session_id' => $session_id)
        );
    }
    
    /**
     * กำหนดความสำคัญของ action
     */
    private function get_action_severity($action) {
        $severity_map = array(
            'save_draft' => 'low',
            'submit' => 'medium',
            'reviewed' => 'medium',
            'approved' => 'high',
            'rejected' => 'high',
            'restored' => 'critical',
            'deleted' => 'critical',
            'exported' => 'low',
            'forwarded' => 'medium'
        );
        
        return $severity_map[$action] ?? 'low';
    }
    
    /**
     * กำหนดประเภทการเปลี่ยนแปลง
     */
    private function determine_change_type($old_value, $new_value) {
        if ($old_value === null || $old_value === '') {
            return 'created';
        } elseif ($new_value === null || $new_value === '') {
            return 'deleted';
        } else {
            return 'updated';
        }
    }
    
    /**
     * ดึง IP address ของ client
     */
    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP']) && !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // ถ้ามีหลาย IP (proxy chain) เอาตัวแรก
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        
        return $ip;
    }
}