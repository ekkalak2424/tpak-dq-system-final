<?php
/**
 * TPAK DQ System - Admin Columns Management
 * 
 * Handles custom columns for verification batch post list
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Admin_Columns {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_filter('manage_verification_batch_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_verification_batch_posts_custom_column', array($this, 'display_custom_columns'), 10, 2);
        add_filter('manage_edit-verification_batch_sortable_columns', array($this, 'make_columns_sortable'));
        add_action('pre_get_posts', array($this, 'filter_posts_by_user_role'));
        add_action('restrict_manage_posts', array($this, 'add_filter_dropdowns'));
    }
    
    /**
     * Add custom columns
     */
    public function add_custom_columns($columns) {
        $new_columns = array();
        
        // Add custom columns in specific order
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['status'] = __('สถานะ', 'tpak-dq-system');
        $new_columns['lime_survey_id'] = __('LimeSurvey ID', 'tpak-dq-system');
        $new_columns['import_date'] = __('วันที่นำเข้า', 'tpak-dq-system');
        $new_columns['assigned_to'] = __('ผู้รับผิดชอบ', 'tpak-dq-system');
        $new_columns['last_action'] = __('การดำเนินการล่าสุด', 'tpak-dq-system');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Display custom columns
     */
    public function display_custom_columns($column, $post_id) {
        switch ($column) {
            case 'status':
                $this->display_status_column($post_id);
                break;
                
            case 'lime_survey_id':
                $this->display_lime_survey_id_column($post_id);
                break;
                
            case 'import_date':
                $this->display_import_date_column($post_id);
                break;
                
            case 'assigned_to':
                $this->display_assigned_to_column($post_id);
                break;
                
            case 'last_action':
                $this->display_last_action_column($post_id);
                break;
        }
    }
    
    /**
     * Display status column
     */
    private function display_status_column($post_id) {
        $workflow = new TPAK_DQ_Workflow();
        $status = $workflow->get_batch_status($post_id);
        
        if ($status) {
            $status_term = get_term_by('slug', $status, 'verification_status');
            $status_name = $status_term ? $status_term->name : $status;
            
            echo '<span class="tpak-status-indicator ' . esc_attr($status) . '"></span>';
            echo '<span class="tpak-status-text">' . esc_html($status_name) . '</span>';
        } else {
            echo '<span class="tpak-status-text">' . __('ไม่ระบุ', 'tpak-dq-system') . '</span>';
        }
    }
    
    /**
     * Display LimeSurvey ID column
     */
    private function display_lime_survey_id_column($post_id) {
        $lime_survey_id = get_post_meta($post_id, '_lime_survey_id', true);
        $lime_response_id = get_post_meta($post_id, '_lime_response_id', true);
        
        if ($lime_survey_id) {
            echo '<span class="tpak-lime-survey-id">' . esc_html($lime_survey_id) . '</span>';
            if ($lime_response_id) {
                echo '<br><small class="tpak-response-id">' . __('Response ID: ', 'tpak-dq-system') . esc_html($lime_response_id) . '</small>';
            }
        } else {
            echo '<span class="tpak-lime-survey-id">' . __('ไม่ระบุ', 'tpak-dq-system') . '</span>';
        }
    }
    
    /**
     * Display import date column
     */
    private function display_import_date_column($post_id) {
        $import_date = get_post_meta($post_id, '_import_date', true);
        
        if ($import_date) {
            echo '<span class="tpak-import-date">' . esc_html(date_i18n(get_option('date_format'), strtotime($import_date))) . '</span>';
        } else {
            echo '<span class="tpak-import-date">' . __('ไม่ระบุ', 'tpak-dq-system') . '</span>';
        }
    }
    
    /**
     * Display assigned to column
     */
    private function display_assigned_to_column($post_id) {
        $workflow = new TPAK_DQ_Workflow();
        $status = $workflow->get_batch_status($post_id);
        
        $assigned_role = $this->get_assigned_role($status);
        
        if ($assigned_role) {
            $roles = new TPAK_DQ_Roles();
            $role_name = $roles->get_role_display_name($assigned_role);
            
            echo '<span class="tpak-assigned-role">' . esc_html($role_name) . '</span>';
        } else {
            echo '<span class="tpak-assigned-role">' . __('เสร็จสมบูรณ์', 'tpak-dq-system') . '</span>';
        }
    }
    
    /**
     * Display last action column
     */
    private function display_last_action_column($post_id) {
        $workflow = new TPAK_DQ_Workflow();
        $audit_trail = $workflow->get_audit_trail($post_id);
        
        if (!empty($audit_trail)) {
            $last_entry = end($audit_trail);
            
            echo '<div class="tpak-last-action">';
            echo '<span class="tpak-action-user">' . esc_html($last_entry['user_name']) . '</span>';
            echo '<br>';
            echo '<span class="tpak-action-time">' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_entry['timestamp']))) . '</span>';
            echo '</div>';
        } else {
            echo '<span class="tpak-last-action">' . __('ไม่มี', 'tpak-dq-system') . '</span>';
        }
    }
    
    /**
     * Get assigned role based on status
     */
    private function get_assigned_role($status) {
        $status_roles = array(
            'pending_a' => 'interviewer',
            'rejected_by_b' => 'interviewer',
            'pending_b' => 'supervisor',
            'rejected_by_c' => 'supervisor',
            'pending_c' => 'examiner',
            'finalized' => 'completed',
            'finalized_by_sampling' => 'completed'
        );
        
        return isset($status_roles[$status]) ? $status_roles[$status] : false;
    }
    
    /**
     * Make columns sortable
     */
    public function make_columns_sortable($columns) {
        $columns['status'] = 'status';
        $columns['lime_survey_id'] = 'lime_survey_id';
        $columns['import_date'] = 'import_date';
        $columns['assigned_to'] = 'assigned_to';
        
        return $columns;
    }
    
    /**
     * Filter posts by user role
     */
    public function filter_posts_by_user_role($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'verification_batch') {
            return;
        }
        
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        // Administrator can see all posts
        if (in_array('administrator', $user->roles)) {
            return;
        }
        
        // Filter posts based on user role
        $user_role = $this->get_user_verification_role($user_id);
        $allowed_statuses = $this->get_allowed_statuses_for_role($user_role);
        
        if (!empty($allowed_statuses)) {
            $tax_query = array(
                array(
                    'taxonomy' => 'verification_status',
                    'field' => 'slug',
                    'terms' => $allowed_statuses
                )
            );
            
            $query->set('tax_query', $tax_query);
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
     * Get allowed statuses for role
     */
    private function get_allowed_statuses_for_role($role) {
        $role_statuses = array(
            'interviewer' => array('pending_a', 'rejected_by_b'),
            'supervisor' => array('pending_b', 'rejected_by_c'),
            'examiner' => array('pending_c')
        );
        
        return isset($role_statuses[$role]) ? $role_statuses[$role] : array();
    }
    
    /**
     * Add filter dropdowns
     */
    public function add_filter_dropdowns() {
        global $typenow;
        
        if ($typenow !== 'verification_batch') {
            return;
        }
        
        // Status filter
        $current_status = isset($_GET['verification_status']) ? $_GET['verification_status'] : '';
        $status_terms = get_terms(array(
            'taxonomy' => 'verification_status',
            'hide_empty' => false
        ));
        
        if (!empty($status_terms) && !is_wp_error($status_terms)) {
            echo '<select name="verification_status">';
            echo '<option value="">' . __('สถานะทั้งหมด', 'tpak-dq-system') . '</option>';
            
            foreach ($status_terms as $term) {
                $selected = ($current_status === $term->slug) ? 'selected' : '';
                echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
            }
            
            echo '</select>';
        }
        
        // Date range filter
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        
        echo '<input type="date" name="start_date" value="' . esc_attr($start_date) . '" placeholder="' . __('วันที่เริ่มต้น', 'tpak-dq-system') . '">';
        echo '<input type="date" name="end_date" value="' . esc_attr($end_date) . '" placeholder="' . __('วันที่สิ้นสุด', 'tpak-dq-system') . '">';
        
        // Submit button
        echo '<input type="submit" class="button" value="' . __('กรอง', 'tpak-dq-system') . '">';
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions() {
        global $typenow;
        
        if ($typenow !== 'verification_batch') {
            return;
        }
        
        // Add bulk actions to the dropdown
        add_filter('bulk_actions-edit-verification_batch', array($this, 'register_bulk_actions'));
        add_filter('handle_bulk_actions-edit-verification_batch', array($this, 'handle_bulk_actions'), 10, 3);
    }
    
    /**
     * Register bulk actions
     */
    public function register_bulk_actions($bulk_actions) {
        $bulk_actions['tpak_export_selected'] = __('ส่งออกข้อมูลที่เลือก', 'tpak-dq-system');
        $bulk_actions['tpak_change_status'] = __('เปลี่ยนสถานะ', 'tpak-dq-system');
        
        return $bulk_actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'tpak_export_selected' && $doaction !== 'tpak_change_status') {
            return $redirect_to;
        }
        
        if (empty($post_ids)) {
            return $redirect_to;
        }
        
        switch ($doaction) {
            case 'tpak_export_selected':
                $this->export_selected_posts($post_ids);
                break;
                
            case 'tpak_change_status':
                $this->change_selected_status($post_ids);
                break;
        }
        
        return $redirect_to;
    }
    
    /**
     * Export selected posts
     */
    private function export_selected_posts($post_ids) {
        // Implementation for exporting selected posts
        // This would typically generate a CSV or Excel file
    }
    
    /**
     * Change selected status
     */
    private function change_selected_status($post_ids) {
        // Implementation for changing status of selected posts
        // This would typically show a form to select new status
    }
} 