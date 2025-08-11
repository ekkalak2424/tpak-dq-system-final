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
    public function manual_import($survey_id = null, $start_date = null, $end_date = null) {
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
        
        error_log('TPAK DQ System: Manual import called with survey_id: ' . $survey_id . ', start_date: ' . ($start_date ?: 'null') . ', end_date: ' . ($end_date ?: 'null'));
        
        $result = $api_handler->import_survey_data($survey_id, $start_date, $end_date);
        
        if ($result && isset($result['imported'])) {
            if ($result['imported'] > 0) {
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
                // No data imported, but no error either
                $error_message = __('ไม่พบข้อมูลที่ตรงกับเงื่อนไขที่ระบุ', 'tpak-dq-system');
                if (!empty($start_date) || !empty($end_date)) {
                    $error_message .= ' (ช่วงวันที่: ' . ($start_date ?: 'ไม่ระบุ') . ' ถึง ' . ($end_date ?: 'ไม่ระบุ') . ')';
                }
                if (!empty($result['errors'])) {
                    $error_message .= ' - ' . implode(', ', $result['errors']);
                }
                return array(
                    'success' => false,
                    'message' => $error_message
                );
            }
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
     * Validate cron settings
     */
    private function validate_cron_settings($settings) {
        $errors = array();
        
        // Validate cron interval
        $valid_intervals = array('hourly', 'twicedaily', 'daily', 'weekly');
        if (!isset($settings['cron_interval']) || !in_array($settings['cron_interval'], $valid_intervals)) {
            $errors[] = __('Invalid cron interval. Must be one of: hourly, twicedaily, daily, weekly', 'tpak-dq-system');
        }
        
        // Validate survey ID
        if (isset($settings['survey_id']) && !empty($settings['survey_id'])) {
            if (!is_numeric($settings['survey_id'])) {
                $errors[] = __('Survey ID must be numeric', 'tpak-dq-system');
            } elseif (intval($settings['survey_id']) <= 0) {
                $errors[] = __('Survey ID must be positive', 'tpak-dq-system');
            } elseif (intval($settings['survey_id']) > 999999999) {
                $errors[] = __('Survey ID is too large', 'tpak-dq-system');
            }
        }
        
        // Validate sampling percentage if provided
        if (isset($settings['sampling_percentage'])) {
            $sampling = intval($settings['sampling_percentage']);
            if ($sampling < 1 || $sampling > 100) {
                $errors[] = __('Sampling percentage must be between 1 and 100', 'tpak-dq-system');
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Update cron settings
     */
    public function update_cron_settings($settings) {
        // Validate settings first
        $validation = $this->validate_cron_settings($settings);
        if (!$validation['valid']) {
            error_log('TPAK DQ System: Cron settings validation failed: ' . implode(', ', $validation['errors']));
            return false;
        }
        
        $options = get_option('tpak_dq_system_options', array());
        
        $options['cron_interval'] = sanitize_text_field($settings['cron_interval']);
        $options['survey_id'] = sanitize_text_field($settings['survey_id']);
        
        // Update sampling percentage if provided
        if (isset($settings['sampling_percentage'])) {
            $options['sampling_percentage'] = intval($settings['sampling_percentage']);
        }
        
        $result = update_option('tpak_dq_system_options', $options);
        
        if ($result) {
            // Reschedule cron job only if update was successful
            wp_clear_scheduled_hook('tpak_dq_cron_import_data');
            wp_schedule_event(time(), $options['cron_interval'], 'tpak_dq_cron_import_data');
            error_log('TPAK DQ System: Cron settings updated successfully');
        } else {
            error_log('TPAK DQ System: Failed to update cron settings');
        }
        
        return $result;
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