<?php
/**
 * TPAK DQ System - Notifications Management
 * 
 * Handles email notifications for workflow events
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Notifications {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('tpak_workflow_status_changed', array($this, 'send_status_change_notification'), 10, 3);
        add_action('tpak_new_batch_imported', array($this, 'send_new_batch_notification'), 10, 2);
    }
    
    /**
     * Send notification when workflow status changes
     */
    public function send_status_change_notification($post_id, $old_status, $new_status) {
        $options = get_option('tpak_dq_system_options', array());
        $email_notifications = isset($options['email_notifications']) ? $options['email_notifications'] : true;
        
        if (!$email_notifications) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $status_term = get_term_by('slug', $new_status, 'verification_status');
        $status_name = $status_term ? $status_term->name : $new_status;
        
        // Determine which role should be notified
        $notify_role = $this->get_notification_role($new_status);
        
        if ($notify_role) {
            $users = get_users(array(
                'role' => $notify_role,
                'orderby' => 'display_name',
                'order' => 'ASC'
            ));
            
            foreach ($users as $user) {
                $this->send_status_notification_email($user, $post, $new_status, $status_name);
            }
        }
    }
    
    /**
     * Send notification for new batch import
     */
    public function send_new_batch_notification($post_id, $batch_data) {
        $options = get_option('tpak_dq_system_options', array());
        $email_notifications = isset($options['email_notifications']) ? $options['email_notifications'] : true;
        
        if (!$email_notifications) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Notify interviewers about new batch
        $users = get_users(array(
            'role' => 'interviewer',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        foreach ($users as $user) {
            $this->send_new_batch_email($user, $post, $batch_data);
        }
    }
    
    /**
     * Get notification role based on status
     */
    private function get_notification_role($status) {
        $status_roles = array(
            'pending_a' => 'interviewer',
            'pending_b' => 'supervisor',
            'pending_c' => 'examiner',
            'rejected_by_b' => 'interviewer',
            'rejected_by_c' => 'supervisor'
        );
        
        return isset($status_roles[$status]) ? $status_roles[$status] : false;
    }
    
    /**
     * Send status change notification email
     */
    private function send_status_notification_email($user, $post, $status, $status_name) {
        $subject = sprintf(__('งานใหม่: %s', 'tpak-dq-system'), $post->post_title);
        
        $message = $this->get_status_notification_message($user, $post, $status, $status_name);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Send new batch notification email
     */
    private function send_new_batch_email($user, $post, $batch_data) {
        $subject = sprintf(__('ชุดข้อมูลใหม่: %s', 'tpak-dq-system'), $post->post_title);
        
        $message = $this->get_new_batch_message($user, $post, $batch_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Get status notification message
     */
    private function get_status_notification_message($user, $post, $status, $status_name) {
        $admin_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
        
        $message = '<html><body>';
        $message .= '<h2>' . __('แจ้งเตือนงานใหม่', 'tpak-dq-system') . '</h2>';
        $message .= '<p>' . sprintf(__('สวัสดี %s,', 'tpak-dq-system'), $user->display_name) . '</p>';
        $message .= '<p>' . sprintf(__('มีชุดข้อมูลตรวจสอบใหม่ที่รอการดำเนินการจากคุณ:', 'tpak-dq-system')) . '</p>';
        $message .= '<ul>';
        $message .= '<li><strong>' . __('ชื่อชุดข้อมูล:', 'tpak-dq-system') . '</strong> ' . $post->post_title . '</li>';
        $message .= '<li><strong>' . __('สถานะปัจจุบัน:', 'tpak-dq-system') . '</strong> ' . $status_name . '</li>';
        $message .= '<li><strong>' . __('วันที่สร้าง:', 'tpak-dq-system') . '</strong> ' . get_the_date('', $post->ID) . '</li>';
        $message .= '</ul>';
        $message .= '<p>' . sprintf(__('กรุณาเข้าไปตรวจสอบที่: <a href="%s">%s</a>', 'tpak-dq-system'), $admin_url, $admin_url) . '</p>';
        $message .= '<p>' . __('ขอบคุณ', 'tpak-dq-system') . '</p>';
        $message .= '</body></html>';
        
        return $message;
    }
    
    /**
     * Get new batch message
     */
    private function get_new_batch_message($user, $post, $batch_data) {
        $admin_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
        
        $message = '<html><body>';
        $message .= '<h2>' . __('ชุดข้อมูลใหม่ถูกนำเข้า', 'tpak-dq-system') . '</h2>';
        $message .= '<p>' . sprintf(__('สวัสดี %s,', 'tpak-dq-system'), $user->display_name) . '</p>';
        $message .= '<p>' . __('มีชุดข้อมูลใหม่ถูกนำเข้าจาก LimeSurvey และรอการตรวจสอบจากคุณ:', 'tpak-dq-system') . '</p>';
        $message .= '<ul>';
        $message .= '<li><strong>' . __('ชื่อชุดข้อมูล:', 'tpak-dq-system') . '</strong> ' . $post->post_title . '</li>';
        $message .= '<li><strong>' . __('LimeSurvey ID:', 'tpak-dq-system') . '</strong> ' . (isset($batch_data['id']) ? $batch_data['id'] : 'N/A') . '</li>';
        $message .= '<li><strong>' . __('วันที่นำเข้า:', 'tpak-dq-system') . '</strong> ' . get_the_date('', $post->ID) . '</li>';
        $message .= '</ul>';
        $message .= '<p>' . sprintf(__('กรุณาเข้าไปตรวจสอบที่: <a href="%s">%s</a>', 'tpak-dq-system'), $admin_url, $admin_url) . '</p>';
        $message .= '<p>' . __('ขอบคุณ', 'tpak-dq-system') . '</p>';
        $message .= '</body></html>';
        
        return $message;
    }
    
    /**
     * Send custom notification to specific users
     */
    public function send_custom_notification($user_ids, $subject, $message, $post_id = null) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $custom_message = $this->customize_message_for_user($message, $user, $post_id);
                wp_mail($user->user_email, $subject, $custom_message, $headers);
            }
        }
    }
    
    /**
     * Customize message for specific user
     */
    private function customize_message_for_user($message, $user, $post_id = null) {
        $custom_message = str_replace(
            array('{user_name}', '{user_email}'),
            array($user->display_name, $user->user_email),
            $message
        );
        
        if ($post_id) {
            $admin_url = admin_url('post.php?post=' . $post_id . '&action=edit');
            $custom_message = str_replace('{admin_url}', $admin_url, $custom_message);
        }
        
        return $custom_message;
    }
    
    /**
     * Send notification to all users with verification roles
     */
    public function send_notification_to_all_verification_users($subject, $message, $post_id = null) {
        $roles = array('interviewer', 'supervisor', 'examiner', 'administrator');
        $user_ids = array();
        
        foreach ($roles as $role) {
            $users = get_users(array(
                'role' => $role,
                'fields' => 'ID'
            ));
            $user_ids = array_merge($user_ids, $users);
        }
        
        $this->send_custom_notification($user_ids, $subject, $message, $post_id);
    }
    
    /**
     * Test email functionality
     */
    public function test_email($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $subject = __('ทดสอบระบบแจ้งเตือน TPAK DQ System', 'tpak-dq-system');
        $message = '<html><body>';
        $message .= '<h2>' . __('ทดสอบระบบแจ้งเตือน', 'tpak-dq-system') . '</h2>';
        $message .= '<p>' . sprintf(__('สวัสดี %s,', 'tpak-dq-system'), $user->display_name) . '</p>';
        $message .= '<p>' . __('นี่คือการทดสอบระบบแจ้งเตือนของ TPAK DQ System', 'tpak-dq-system') . '</p>';
        $message .= '<p>' . __('หากคุณได้รับอีเมลนี้ แสดงว่าระบบแจ้งเตือนทำงานปกติ', 'tpak-dq-system') . '</p>';
        $message .= '<p>' . __('ขอบคุณ', 'tpak-dq-system') . '</p>';
        $message .= '</body></html>';
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Get notification settings
     */
    public function get_notification_settings() {
        $options = get_option('tpak_dq_system_options', array());
        
        return array(
            'email_notifications' => isset($options['email_notifications']) ? $options['email_notifications'] : true,
            'admin_email' => get_option('admin_email'),
            'site_name' => get_bloginfo('name')
        );
    }
    
    /**
     * Update notification settings
     */
    public function update_notification_settings($settings) {
        $options = get_option('tpak_dq_system_options', array());
        
        $options['email_notifications'] = isset($settings['email_notifications']) ? true : false;
        
        update_option('tpak_dq_system_options', $options);
    }
} 