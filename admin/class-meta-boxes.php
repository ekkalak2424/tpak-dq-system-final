<?php
/**
 * TPAK DQ System - Meta Boxes Management
 * 
 * Handles custom meta boxes for verification batch posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Meta_Boxes {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_meta_box_scripts'));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'tpak_survey_data',
            __('ข้อมูลแบบสอบถาม', 'tpak-dq-system'),
            array($this, 'survey_data_meta_box'),
            'verification_batch',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tpak_audit_trail',
            __('ประวัติการตรวจสอบ', 'tpak-dq-system'),
            array($this, 'audit_trail_meta_box'),
            'verification_batch',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tpak_workflow_actions',
            __('ดำเนินการ', 'tpak-dq-system'),
            array($this, 'workflow_actions_meta_box'),
            'verification_batch',
            'side',
            'high'
        );
        
        add_meta_box(
            'tpak_batch_info',
            __('ข้อมูลชุดตรวจสอบ', 'tpak-dq-system'),
            array($this, 'batch_info_meta_box'),
            'verification_batch',
            'side',
            'default'
        );
    }
    
    /**
     * Survey data meta box
     */
    public function survey_data_meta_box($post) {
        $survey_data = get_post_meta($post->ID, '_survey_data', true);
        $survey_data = json_decode($survey_data, true);
        
        if (!$survey_data) {
            echo '<p>' . __('ไม่พบข้อมูลแบบสอบถาม', 'tpak-dq-system') . '</p>';
            return;
        }
        
        echo '<div class="tpak-survey-data">';
        echo '<h4>' . __('ข้อมูลคำตอบ', 'tpak-dq-system') . '</h4>';
        
        foreach ($survey_data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            
            echo '<div class="tpak-data-item">';
            echo '<span class="tpak-data-label">' . esc_html($key) . '</span>';
            echo '<span class="tpak-data-value">' . esc_html($value) . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Audit trail meta box
     */
    public function audit_trail_meta_box($post) {
        $workflow = new TPAK_DQ_Workflow();
        $audit_trail = $workflow->get_audit_trail($post->ID);
        
        if (empty($audit_trail)) {
            echo '<p>' . __('ไม่มีประวัติการตรวจสอบ', 'tpak-dq-system') . '</p>';
            return;
        }
        
        echo '<div class="tpak-audit-trail">';
        
        foreach ($audit_trail as $entry) {
            echo '<div class="tpak-audit-item">';
            echo '<div class="audit-header">';
            echo '<span class="audit-user">' . esc_html($entry['user_name']) . '</span>';
            echo '<span class="audit-time">' . esc_html($entry['timestamp']) . '</span>';
            echo '</div>';
            echo '<div class="audit-action">' . esc_html($this->get_action_display_name($entry['action'])) . '</div>';
            if (!empty($entry['comment'])) {
                echo '<div class="audit-comment">' . esc_html($entry['comment']) . '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Workflow actions meta box
     */
    public function workflow_actions_meta_box($post) {
        $workflow = new TPAK_DQ_Workflow();
        $current_status = $workflow->get_batch_status($post->ID);
        $available_actions = $workflow->get_available_actions($post->ID);
        
        if (empty($available_actions)) {
            echo '<p>' . __('ไม่มีงานที่ต้องดำเนินการ', 'tpak-dq-system') . '</p>';
            return;
        }
        
        echo '<div class="tpak-workflow-actions">';
        echo '<h4>' . __('ดำเนินการ', 'tpak-dq-system') . '</h4>';
        echo '<div class="tpak-action-buttons">';
        
        foreach ($available_actions as $action) {
            $display_name = $workflow->get_action_display_name($action);
            $button_class = $this->get_button_class($action);
            
            echo '<button class="tpak-action-btn ' . $button_class . '" data-action="' . esc_attr($action) . '" data-post-id="' . esc_attr($post->ID) . '">';
            echo esc_html($display_name);
            echo '</button>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Add status information
        echo '<div class="tpak-status-info">';
        echo '<h4>' . __('สถานะปัจจุบัน', 'tpak-dq-system') . '</h4>';
        echo '<p><span class="tpak-status-indicator ' . esc_attr($current_status) . '"></span>';
        echo esc_html($this->get_status_display_name($current_status)) . '</p>';
        echo '</div>';
    }
    
    /**
     * Batch info meta box
     */
    public function batch_info_meta_box($post) {
        $lime_survey_id = get_post_meta($post->ID, '_lime_survey_id', true);
        $import_date = get_post_meta($post->ID, '_import_date', true);
        $workflow = new TPAK_DQ_Workflow();
        $current_status = $workflow->get_batch_status($post->ID);
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th>' . __('LimeSurvey ID', 'tpak-dq-system') . '</th>';
        echo '<td>' . esc_html($lime_survey_id) . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('วันที่นำเข้า', 'tpak-dq-system') . '</th>';
        echo '<td>' . esc_html($import_date) . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('สถานะ', 'tpak-dq-system') . '</th>';
        echo '<td><span class="tpak-status-indicator ' . esc_attr($current_status) . '"></span>';
        echo esc_html($this->get_status_display_name($current_status)) . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('ผู้สร้าง', 'tpak-dq-system') . '</th>';
        echo '<td>' . esc_html(get_the_author_meta('display_name', $post->post_author)) . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('วันที่แก้ไขล่าสุด', 'tpak-dq-system') . '</th>';
        echo '<td>' . esc_html(get_the_modified_date('', $post->ID)) . '</td>';
        echo '</tr>';
        
        echo '</table>';
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check nonce
        if (!isset($_POST['tpak_meta_box_nonce']) || !wp_verify_nonce($_POST['tpak_meta_box_nonce'], 'tpak_meta_box')) {
            return;
        }
        
        // Save custom fields
        if (isset($_POST['_survey_data'])) {
            update_post_meta($post_id, '_survey_data', sanitize_textarea_field($_POST['_survey_data']));
        }
        
        if (isset($_POST['_lime_survey_id'])) {
            update_post_meta($post_id, '_lime_survey_id', sanitize_text_field($_POST['_lime_survey_id']));
        }
    }
    
    /**
     * Enqueue meta box scripts
     */
    public function enqueue_meta_box_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'verification_batch') {
            wp_enqueue_script('tpak-meta-box', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/meta-box.js', array('jquery'), TPAK_DQ_SYSTEM_VERSION, true);
            wp_localize_script('tpak-meta-box', 'tpak_meta_box', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tpak_meta_box_nonce')
            ));
        }
    }
    
    /**
     * Get action display name
     */
    private function get_action_display_name($action) {
        $action_names = array(
            'imported' => __('นำเข้าข้อมูล', 'tpak-dq-system'),
            'approved_a' => __('ยืนยันและส่งต่อให้ Supervisor', 'tpak-dq-system'),
            'approved_b' => __('ยืนยันข้อมูล', 'tpak-dq-system'),
            'rejected' => __('ส่งกลับเพื่อแก้ไข', 'tpak-dq-system'),
            'finalized' => __('อนุมัติขั้นสุดท้าย', 'tpak-dq-system')
        );
        
        return isset($action_names[$action]) ? $action_names[$action] : $action;
    }
    
    /**
     * Get status display name
     */
    private function get_status_display_name($status) {
        $status_names = array(
            'pending_a' => __('รอการตรวจสอบ A', 'tpak-dq-system'),
            'pending_b' => __('รอการตรวจสอบ B', 'tpak-dq-system'),
            'pending_c' => __('รอการตรวจสอบ C', 'tpak-dq-system'),
            'rejected_by_b' => __('ส่งกลับจาก B', 'tpak-dq-system'),
            'rejected_by_c' => __('ส่งกลับจาก C', 'tpak-dq-system'),
            'finalized' => __('ตรวจสอบเสร็จสมบูรณ์', 'tpak-dq-system'),
            'finalized_by_sampling' => __('เสร็จสมบูรณ์โดยการสุ่ม', 'tpak-dq-system')
        );
        
        return isset($status_names[$status]) ? $status_names[$status] : $status;
    }
    
    /**
     * Get button class
     */
    private function get_button_class($action) {
        $button_classes = array(
            'approve_a' => 'primary',
            'approve_b' => 'success',
            'reject_b' => 'danger',
            'reject_c' => 'danger',
            'finalize' => 'success'
        );
        
        return isset($button_classes[$action]) ? $button_classes[$action] : 'secondary';
    }
} 