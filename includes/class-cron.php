<?php
/**
 * TPAK DQ System - Cron Jobs Management
 * 
 * Handles scheduled tasks for automatic data import from LimeSurvey
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Cron {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'schedule_cron_jobs'));
        add_action('tpak_dq_cron_import_data', array($this, 'import_data_cron'));
        add_action('deactivate', array($this, 'clear_cron_jobs'));
    }
    
    /**
     * Schedule cron jobs
     */
    public function schedule_cron_jobs() {
        if (!wp_next_scheduled('tpak_dq_cron_import_data')) {
            $options = get_option('tpak_dq_system_options', array());
            $interval = isset($options['cron_interval']) ? $options['cron_interval'] : 'hourly';
            
            wp_schedule_event(time(), $interval, 'tpak_dq_cron_import_data');
        }
    }
    
    /**
     * Clear cron jobs on deactivation
     */
    public function clear_cron_jobs() {
        wp_clear_scheduled_hook('tpak_dq_cron_import_data');
    }
    
    /**
     * Import data cron job
     */
    public function import_data_cron() {
        // Check if API is configured
        $api_handler = new TPAK_DQ_API_Handler();
        if (!$api_handler->is_configured()) {
            error_log('TPAK DQ System: API not configured for cron import');
            return;
        }
        
        // Get configured survey ID
        $options = get_option('tpak_dq_system_options', array());
        $survey_id = isset($options['survey_id']) ? $options['survey_id'] : '';
        
        if (empty($survey_id)) {
            error_log('TPAK DQ System: No survey ID configured for cron import');
            return;
        }
        
        // Import data
        $result = $api_handler->import_survey_data($survey_id);
        
        if ($result) {
            $log_message = sprintf(
                'TPAK DQ System: Cron import completed. Imported: %d, Errors: %d',
                $result['imported'],
                count($result['errors'])
            );
            error_log($log_message);
            
            // Log errors if any
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    error_log('TPAK DQ System Error: ' . $error);
                }
            }
        } else {
            error_log('TPAK DQ System: Cron import failed');
        }
    }
    
    /**
     * Manual import trigger
     */
    public function manual_import($survey_id = null) {
        $api_handler = new TPAK_DQ_API_Handler();
        
        if (!$api_handler->is_configured()) {
            return array(
                'success' => false,
                'message' => __('API ไม่ได้ตั้งค่า', 'tpak-dq-system')
            );
        }
        
        if (!$survey_id) {
            $options = get_option('tpak_dq_system_options', array());
            $survey_id = isset($options['survey_id']) ? $options['survey_id'] : '';
        }
        
        if (empty($survey_id)) {
            return array(
                'success' => false,
                'message' => __('ไม่พบ Survey ID', 'tpak-dq-system')
            );
        }
        
        $result = $api_handler->import_survey_data($survey_id);
        
        if ($result) {
            return array(
                'success' => true,
                'imported' => $result['imported'],
                'errors' => $result['errors'],
                'message' => sprintf(
                    __('นำเข้าข้อมูลสำเร็จ %d รายการ', 'tpak-dq-system'),
                    $result['imported']
                )
            );
        } else {
            return array(
                'success' => false,
                'message' => __('ไม่สามารถนำเข้าข้อมูลได้', 'tpak-dq-system')
            );
        }
    }
    
    /**
     * Get cron schedule options
     */
    public function get_cron_schedule_options() {
        return array(
            'hourly' => __('ทุกชั่วโมง', 'tpak-dq-system'),
            'twicedaily' => __('วันละ 2 ครั้ง', 'tpak-dq-system'),
            'daily' => __('วันละครั้ง', 'tpak-dq-system'),
            'weekly' => __('สัปดาห์ละครั้ง', 'tpak-dq-system')
        );
    }
    
    /**
     * Update cron settings
     */
    public function update_cron_settings($settings) {
        $options = get_option('tpak_dq_system_options', array());
        
        $options['cron_interval'] = sanitize_text_field($settings['cron_interval']);
        $options['survey_id'] = sanitize_text_field($settings['survey_id']);
        
        update_option('tpak_dq_system_options', $options);
        
        // Reschedule cron job
        wp_clear_scheduled_hook('tpak_dq_cron_import_data');
        wp_schedule_event(time(), $options['cron_interval'], 'tpak_dq_cron_import_data');
    }
    
    /**
     * Get next scheduled run time
     */
    public function get_next_scheduled_run() {
        $next_run = wp_next_scheduled('tpak_dq_cron_import_data');
        
        if ($next_run) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run);
        }
        
        return __('ไม่มีการตั้งเวลา', 'tpak-dq-system');
    }
    
    /**
     * Get last run time
     */
    public function get_last_run_time() {
        $last_run = get_option('tpak_dq_cron_last_run', '');
        
        if ($last_run) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_run));
        }
        
        return __('ยังไม่เคยรัน', 'tpak-dq-system');
    }
    
    /**
     * Update last run time
     */
    public function update_last_run_time() {
        update_option('tpak_dq_cron_last_run', current_time('mysql'));
    }
} 