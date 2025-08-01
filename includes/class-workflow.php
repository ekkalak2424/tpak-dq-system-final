<?php
/**
 * TPAK DQ System - Workflow Management
 * 
 * Handles the 3-step verification workflow and sampling gate logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Workflow {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_tpak_approve_batch', array($this, 'approve_batch'));
        add_action('wp_ajax_tpak_approve_batch_supervisor', array($this, 'approve_batch_supervisor'));
        add_action('wp_ajax_tpak_reject_batch', array($this, 'reject_batch'));
        add_action('wp_ajax_tpak_finalize_batch', array($this, 'finalize_batch'));
    }
    
    /**
     * Get current status of a verification batch
     */
    public function get_batch_status($post_id) {
        $terms = wp_get_object_terms($post_id, 'verification_status');
        if (!empty($terms) && !is_wp_error($terms)) {
            return $terms[0]->slug;
        }
        return false;
    }
    
    /**
     * Update batch status
     */
    public function update_batch_status($post_id, $status) {
        $result = wp_set_object_terms($post_id, $status, 'verification_status');
        return !is_wp_error($result);
    }
    
    /**
     * Add audit trail entry
     */
    public function add_audit_trail_entry($post_id, $entry) {
        $audit_trail = get_post_meta($post_id, '_audit_trail', true);
        if (!is_array($audit_trail)) {
            $audit_trail = array();
        }
        
        $entry['timestamp'] = current_time('mysql');
        $audit_trail[] = $entry;
        
        update_post_meta($post_id, '_audit_trail', $audit_trail);
    }
    
    /**
     * Get audit trail for a batch
     */
    public function get_audit_trail($post_id) {
        $audit_trail = get_post_meta($post_id, '_audit_trail', true);
        return is_array($audit_trail) ? $audit_trail : array();
    }
    
    /**
     * Check if user can perform action on batch
     */
    public function can_perform_action($post_id, $action, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $current_status = $this->get_batch_status($post_id);
        $user_role = $this->get_user_verification_role($user_id);
        
        switch ($action) {
            case 'approve_a':
                return $user_role === 'interviewer' && $current_status === 'pending_a';
                
            case 'reject_b':
                return $user_role === 'supervisor' && $current_status === 'pending_b';
                
            case 'approve_b':
                return $user_role === 'supervisor' && $current_status === 'pending_b';
                
            case 'reject_c':
                return $user_role === 'examiner' && $current_status === 'pending_c';
                
            case 'finalize':
                return $user_role === 'examiner' && $current_status === 'pending_c';
                
            default:
                return false;
        }
    }
    
    /**
     * Get user's verification role
     */
    private function get_user_verification_role($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $roles = array('interviewer', 'supervisor', 'examiner', 'administrator');
        
        foreach ($roles as $role) {
            if (in_array($role, $user->roles)) {
                return $role;
            }
        }
        
        return false;
    }
    
    /**
     * Approve batch (Interviewer action)
     */
    public function approve_batch() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_workflow_nonce')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $post_id = intval($_POST['post_id']);
        $user_id = get_current_user_id();
        
        if (!$this->can_perform_action($post_id, 'approve_a', $user_id)) {
            wp_die(__('You do not have permission to perform this action', 'tpak-dq-system'));
        }
        
        // Update status to pending_b
        $this->update_batch_status($post_id, 'pending_b');
        
        // Add audit trail entry
        $user = get_user_by('id', $user_id);
        $this->add_audit_trail_entry($post_id, array(
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'action' => 'approved_a',
            'comment' => __('ยืนยันและส่งต่อให้ Supervisor', 'tpak-dq-system')
        ));
        
        // Send notification to supervisors
        $this->send_notification_to_role('supervisor', $post_id, 'pending_b');
        
        wp_send_json_success(array(
            'message' => __('ส่งต่อข้อมูลสำเร็จ', 'tpak-dq-system'),
            'new_status' => 'pending_b'
        ));
    }
    
    /**
     * Reject batch (Supervisor action)
     */
    public function reject_batch() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_workflow_nonce')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $post_id = intval($_POST['post_id']);
        $comment = sanitize_textarea_field($_POST['comment']);
        $user_id = get_current_user_id();
        
        if (empty($comment)) {
            wp_send_json_error(array(
                'message' => __('กรุณากรอกความคิดเห็น', 'tpak-dq-system')
            ));
        }
        
        $current_status = $this->get_batch_status($post_id);
        $action = $current_status === 'pending_b' ? 'reject_b' : 'reject_c';
        
        if (!$this->can_perform_action($post_id, $action, $user_id)) {
            wp_die(__('You do not have permission to perform this action', 'tpak-dq-system'));
        }
        
        // Determine new status based on current status
        $new_status = $current_status === 'pending_b' ? 'rejected_by_b' : 'rejected_by_c';
        
        // Update status
        $this->update_batch_status($post_id, $new_status);
        
        // Add audit trail entry
        $user = get_user_by('id', $user_id);
        $this->add_audit_trail_entry($post_id, array(
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'action' => 'rejected',
            'comment' => $comment
        ));
        
        // Send notification to appropriate role
        $notification_role = $current_status === 'pending_b' ? 'interviewer' : 'supervisor';
        $this->send_notification_to_role($notification_role, $post_id, $new_status);
        
        wp_send_json_success(array(
            'message' => __('ส่งกลับข้อมูลสำเร็จ', 'tpak-dq-system'),
            'new_status' => $new_status
        ));
    }
    
    /**
     * Approve batch (Supervisor action with sampling)
     */
    public function approve_batch_supervisor() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_workflow_nonce')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $post_id = intval($_POST['post_id']);
        $user_id = get_current_user_id();
        
        if (!$this->can_perform_action($post_id, 'approve_b', $user_id)) {
            wp_die(__('You do not have permission to perform this action', 'tpak-dq-system'));
        }
        
        // Run sampling gate logic
        $sampling_result = $this->run_sampling_gate();
        
        if ($sampling_result === 'finalized_by_sampling') {
            // 70% - Finalize by sampling
            $this->update_batch_status($post_id, 'finalized_by_sampling');
            $new_status = 'finalized_by_sampling';
            $comment = __('ยืนยันข้อมูล (เสร็จสมบูรณ์โดยการสุ่ม)', 'tpak-dq-system');
        } else {
            // 30% - Send to examiner
            $this->update_batch_status($post_id, 'pending_c');
            $new_status = 'pending_c';
            $comment = __('ยืนยันข้อมูลและส่งต่อให้ Examiner', 'tpak-dq-system');
            
            // Send notification to examiners
            $this->send_notification_to_role('examiner', $post_id, 'pending_c');
        }
        
        // Add audit trail entry
        $user = get_user_by('id', $user_id);
        $this->add_audit_trail_entry($post_id, array(
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'action' => 'approved_b',
            'comment' => $comment
        ));
        
        wp_send_json_success(array(
            'message' => __('ดำเนินการสำเร็จ', 'tpak-dq-system'),
            'new_status' => $new_status,
            'sampling_result' => $sampling_result
        ));
    }
    
    /**
     * Finalize batch (Examiner action)
     */
    public function finalize_batch() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_workflow_nonce')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $post_id = intval($_POST['post_id']);
        $user_id = get_current_user_id();
        
        if (!$this->can_perform_action($post_id, 'finalize', $user_id)) {
            wp_die(__('You do not have permission to perform this action', 'tpak-dq-system'));
        }
        
        // Update status to finalized
        $this->update_batch_status($post_id, 'finalized');
        
        // Add audit trail entry
        $user = get_user_by('id', $user_id);
        $this->add_audit_trail_entry($post_id, array(
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'action' => 'finalized',
            'comment' => __('อนุมัติขั้นสุดท้าย', 'tpak-dq-system')
        ));
        
        wp_send_json_success(array(
            'message' => __('อนุมัติขั้นสุดท้ายสำเร็จ', 'tpak-dq-system'),
            'new_status' => 'finalized'
        ));
    }
    
    /**
     * Run sampling gate logic
     */
    private function run_sampling_gate() {
        $options = get_option('tpak_dq_system_options', array());
        $sampling_percentage = isset($options['sampling_percentage']) ? $options['sampling_percentage'] : 70;
        
        // Generate random number 1-100
        $random_number = rand(1, 100);
        
        if ($random_number <= $sampling_percentage) {
            return 'finalized_by_sampling';
        } else {
            return 'pending_c';
        }
    }
    
    /**
     * Send notification to users with specific role
     */
    private function send_notification_to_role($role, $post_id, $status) {
        $users = get_users(array(
            'role' => $role,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        if (empty($users)) {
            return;
        }
        
        $post = get_post($post_id);
        $status_term = get_term_by('slug', $status, 'verification_status');
        $status_name = $status_term ? $status_term->name : $status;
        
        $subject = sprintf(__('งานใหม่: %s', 'tpak-dq-system'), $post->post_title);
        $message = sprintf(
            __('มีชุดข้อมูลตรวจสอบใหม่ (ID: %d) รอการตรวจสอบในสถานะ: %s', 'tpak-dq-system'),
            $post_id,
            $status_name
        );
        
        foreach ($users as $user) {
            wp_mail($user->user_email, $subject, $message);
        }
    }
    
    /**
     * Get available actions for current user and batch status
     */
    public function get_available_actions($post_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $current_status = $this->get_batch_status($post_id);
        $user_role = $this->get_user_verification_role($user_id);
        $actions = array();
        
        switch ($user_role) {
            case 'interviewer':
                if ($current_status === 'pending_a' || $current_status === 'rejected_by_b') {
                    $actions[] = 'approve_a';
                }
                break;
                
            case 'supervisor':
                if ($current_status === 'pending_b') {
                    $actions[] = 'approve_batch_supervisor';
                    $actions[] = 'reject_b';
                }
                break;
                
            case 'examiner':
                if ($current_status === 'pending_c') {
                    $actions[] = 'finalize';
                    $actions[] = 'reject_c';
                }
                break;
                
            case 'administrator':
                // Administrator can perform all actions
                switch ($current_status) {
                    case 'pending_a':
                    case 'rejected_by_b':
                        $actions[] = 'approve_a';
                        break;
                    case 'pending_b':
                        $actions[] = 'approve_batch_supervisor';
                        $actions[] = 'reject_b';
                        break;
                    case 'pending_c':
                        $actions[] = 'finalize';
                        $actions[] = 'reject_c';
                        break;
                }
                break;
        }
        
        return $actions;
    }
    
    /**
     * Get action display name
     */
    public function get_action_display_name($action) {
        $action_names = array(
            'approve_a' => __('ยืนยันและส่งต่อให้ Supervisor', 'tpak-dq-system'),
            'approve_b' => __('ยืนยันข้อมูล', 'tpak-dq-system'),
            'approve_batch_supervisor' => __('ยืนยันข้อมูล', 'tpak-dq-system'),
            'reject_b' => __('ส่งกลับเพื่อแก้ไข', 'tpak-dq-system'),
            'finalize' => __('อนุมัติขั้นสุดท้าย', 'tpak-dq-system'),
            'reject_c' => __('ส่งกลับเพื่อตรวจสอบอีกครั้ง', 'tpak-dq-system')
        );
        
        return isset($action_names[$action]) ? $action_names[$action] : $action;
    }
} 