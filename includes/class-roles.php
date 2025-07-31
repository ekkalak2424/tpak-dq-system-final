<?php
/**
 * TPAK DQ System - User Roles Management
 * 
 * Handles the creation and management of custom user roles
 * for the verification workflow system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Roles {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'maybe_create_roles'));
    }
    
    /**
     * Create roles if they don't exist
     */
    public function maybe_create_roles() {
        // Check if roles need to be created
        $roles_created = get_option('tpak_dq_roles_created', false);
        if (!$roles_created) {
            $this->create_roles();
            update_option('tpak_dq_roles_created', true);
        }
    }
    
    /**
     * Create custom user roles
     */
    public function create_roles() {
        // Create Interviewer role
        add_role('interviewer', __('ผู้ตรวจสอบขั้นที่ 1', 'tpak-dq-system'), array(
            'read' => true,
            'edit_verification_batches' => true,
            'edit_verification_batch' => true,
            'read_verification_batch' => true,
            'edit_published_verification_batches' => true,
            'publish_verification_batches' => true,
            'delete_verification_batch' => false,
            'delete_verification_batches' => false,
            'edit_others_verification_batches' => false,
            'read_private_verification_batches' => false,
            'delete_private_verification_batches' => false,
            'delete_published_verification_batches' => false,
            'delete_others_verification_batches' => false,
            'edit_private_verification_batches' => false,
            'assign_verification_status' => true,
        ));
        
        // Create Supervisor role
        add_role('supervisor', __('ผู้ตรวจสอบขั้นที่ 2', 'tpak-dq-system'), array(
            'read' => true,
            'read_verification_batch' => true,
            'read_verification_batches' => true,
            'read_private_verification_batches' => true,
            'edit_verification_batches' => false,
            'edit_verification_batch' => false,
            'publish_verification_batches' => false,
            'delete_verification_batch' => false,
            'delete_verification_batches' => false,
            'edit_others_verification_batches' => false,
            'delete_private_verification_batches' => false,
            'delete_published_verification_batches' => false,
            'delete_others_verification_batches' => false,
            'edit_private_verification_batches' => false,
            'edit_published_verification_batches' => false,
            'assign_verification_status' => true,
        ));
        
        // Create Examiner role
        add_role('examiner', __('ผู้ตรวจสอบขั้นที่ 3', 'tpak-dq-system'), array(
            'read' => true,
            'read_verification_batch' => true,
            'read_verification_batches' => true,
            'read_private_verification_batches' => true,
            'edit_verification_batches' => false,
            'edit_verification_batch' => false,
            'publish_verification_batches' => false,
            'delete_verification_batch' => false,
            'delete_verification_batches' => false,
            'edit_others_verification_batches' => false,
            'delete_private_verification_batches' => false,
            'delete_published_verification_batches' => false,
            'delete_others_verification_batches' => false,
            'edit_private_verification_batches' => false,
            'edit_published_verification_batches' => false,
            'assign_verification_status' => true,
        ));
        
        // Add capabilities to administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_tpak_settings');
            $admin_role->add_cap('edit_others_verification_batches');
            $admin_role->add_cap('read_private_verification_batches');
            $admin_role->add_cap('edit_verification_batches');
            $admin_role->add_cap('edit_verification_batch');
            $admin_role->add_cap('read_verification_batch');
            $admin_role->add_cap('read_verification_batches');
            $admin_role->add_cap('publish_verification_batches');
            $admin_role->add_cap('delete_verification_batch');
            $admin_role->add_cap('delete_verification_batches');
            $admin_role->add_cap('delete_private_verification_batches');
            $admin_role->add_cap('delete_published_verification_batches');
            $admin_role->add_cap('delete_others_verification_batches');
            $admin_role->add_cap('edit_private_verification_batches');
            $admin_role->add_cap('edit_published_verification_batches');
            $admin_role->add_cap('assign_verification_status');
            $admin_role->add_cap('manage_verification_status');
            $admin_role->add_cap('edit_verification_status');
            $admin_role->add_cap('delete_verification_status');
        }
    }
    
    /**
     * Get users by role
     */
    public function get_users_by_role($role) {
        $users = get_users(array(
            'role' => $role,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        return $users;
    }
    
    /**
     * Get all users with verification roles
     */
    public function get_all_verification_users() {
        $roles = array('interviewer', 'supervisor', 'examiner', 'administrator');
        $users = array();
        
        foreach ($roles as $role) {
            $role_users = $this->get_users_by_role($role);
            $users = array_merge($users, $role_users);
        }
        
        return $users;
    }
    
    /**
     * Check if user can edit verification batch
     */
    public function can_edit_verification_batch($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        return user_can($user_id, 'edit_verification_batch');
    }
    
    /**
     * Check if user can read verification batch
     */
    public function can_read_verification_batch($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        return user_can($user_id, 'read_verification_batch');
    }
    
    /**
     * Get user's verification role
     */
    public function get_user_verification_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
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
     * Get role display name
     */
    public function get_role_display_name($role) {
        $role_names = array(
            'administrator' => __('ผู้ดูแลระบบ', 'tpak-dq-system'),
            'interviewer' => __('ผู้ตรวจสอบขั้นที่ 1', 'tpak-dq-system'),
            'supervisor' => __('ผู้ตรวจสอบขั้นที่ 2', 'tpak-dq-system'),
            'examiner' => __('ผู้ตรวจสอบขั้นที่ 3', 'tpak-dq-system'),
            'completed' => __('เสร็จสมบูรณ์', 'tpak-dq-system')
        );
        
        return isset($role_names[$role]) ? $role_names[$role] : $role;
    }
} 