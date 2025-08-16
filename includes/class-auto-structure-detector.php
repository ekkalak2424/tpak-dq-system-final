<?php
/**
 * TPAK DQ System - Auto Structure Detector
 * 
 * ระบบตรวจจับและวิเคราะห์โครงสร้างของแบบสอบถามอัตโนมัติ
 * เมื่อมีการใส่ Survey ID จะพยายามหา LSS structure และวิเคราะห์โครงสร้าง
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Auto_Structure_Detector {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * ตรวจจับโครงสร้างอัตโนมัติจาก Survey ID
     */
    public function auto_detect_structure($survey_id) {
        $result = array(
            'success' => false,
            'structure_found' => false,
            'lss_imported' => false,
            'api_structure' => false,
            'analysis' => array(),
            'recommendations' => array(),
            'display_config' => array()
        );
        
        error_log("TPAK Auto Detector: Starting detection for Survey ID: {$survey_id}");
        
        // 1. ตรวจสอบ LSS structure ที่มีอยู่แล้ว
        $existing_lss = $this->check_existing_lss($survey_id);
        if ($existing_lss) {
            $result['structure_found'] = true;
            $result['lss_imported'] = true;
            $result['analysis'] = $this->analyze_lss_structure($existing_lss);
            error_log("TPAK Auto Detector: Found existing LSS structure");
        }
        
        // 2. หาไฟล์ .lss ที่อาจตรงกับ Survey ID
        if (!$result['lss_imported']) {
            $lss_file = $this->find_matching_lss_file($survey_id);
            if ($lss_file) {
                $import_result = $this->auto_import_lss($survey_id, $lss_file);
                if ($import_result['success']) {
                    $result['lss_imported'] = true;
                    $result['structure_found'] = true;
                    $result['analysis'] = $import_result['analysis'];
                    error_log("TPAK Auto Detector: Auto-imported LSS file: {$lss_file}");
                }
            }
        }
        
        // 3. ลองดึงจาก API หากไม่มี LSS
        if (!$result['structure_found']) {
            $api_result = $this->get_api_structure($survey_id);
            if ($api_result['success']) {
                $result['api_structure'] = true;
                $result['structure_found'] = true;
                $result['analysis'] = $api_result['analysis'];
                error_log("TPAK Auto Detector: Retrieved structure from API");
            }
        }
        
        // 4. วิเคราะห์และสร้าง display configuration
        if ($result['structure_found']) {
            $result['display_config'] = $this->create_display_config($result['analysis']);
            $result['recommendations'] = $this->generate_recommendations($result['analysis']);
            $result['success'] = true;
        }
        
        // 5. บันทึกผลลัพธ์
        $this->cache_detection_result($survey_id, $result);
        
        return $result;
    }
    
    /**
     * ตรวจสอบ LSS structure ที่มีอยู่แล้ว
     */
    private function check_existing_lss($survey_id) {
        $lss_structure = get_option('tpak_lss_structure_' . $survey_id, false);
        return $lss_structure;
    }
    
    /**
     * ค้นหาไฟล์ .lss ที่ตรงกับ Survey ID
     */
    private function find_matching_lss_file($survey_id) {
        $upload_dir = wp_upload_dir();
        $lss_search_paths = array(
            $upload_dir['basedir'] . '/tpak-lss/',
            TPAK_DQ_SYSTEM_PLUGIN_DIR . 'lss-files/',
            ABSPATH . 'lss-files/',
            dirname(ABSPATH) . '/lss-files/'
        );
        
        $possible_names = array(
            "limesurvey_survey_{$survey_id}.lss",
            "survey_{$survey_id}.lss",
            "{$survey_id}.lss",
            "structure_{$survey_id}.lss"
        );
        
        foreach ($lss_search_paths as $path) {
            if (!is_dir($path)) continue;
            
            foreach ($possible_names as $filename) {
                $full_path = $path . $filename;
                if (file_exists($full_path)) {
                    error_log("TPAK Auto Detector: Found LSS file: {$full_path}");
                    return $full_path;
                }
            }
            
            // ค้นหาไฟล์ที่มี survey_id ในชื่อ
            $files = glob($path . "*{$survey_id}*.lss");
            if (!empty($files)) {
                error_log("TPAK Auto Detector: Found LSS file by pattern: {$files[0]}");
                return $files[0];
            }
        }
        
        return false;
    }
    
    /**
     * Import LSS อัตโนมัติ
     */
    private function auto_import_lss($survey_id, $lss_file) {
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-lss-parser.php';
        
        $parser = TPAK_LSS_Parser::getInstance();
        $parse_result = $parser->parse_lss_file($lss_file);
        
        if ($parse_result['success']) {
            // บันทึกข้อมูล
            $saved = $parser->save_to_database($parse_result, $survey_id);
            
            if ($saved) {
                $analysis = $this->analyze_lss_structure($parse_result);
                return array(
                    'success' => true,
                    'analysis' => $analysis,
                    'file_path' => $lss_file
                );
            }
        }
        
        return array('success' => false, 'message' => 'Failed to import LSS file');
    }
    
    /**
     * ดึงโครงสร้างจาก API
     */
    private function get_api_structure($survey_id) {
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-api-handler.php';
        
        $api_handler = new TPAK_DQ_API_Handler();
        
        if (!$api_handler->is_configured()) {
            return array('success' => false, 'message' => 'API not configured');
        }
        
        $structure = $api_handler->get_survey_structure($survey_id);
        
        if ($structure) {
            $analysis = $this->analyze_api_structure($structure);
            return array(
                'success' => true,
                'analysis' => $analysis,
                'structure' => $structure
            );
        }
        
        return array('success' => false, 'message' => 'Failed to get API structure');
    }
    
    /**
     * วิเคราะห์โครงสร้าง LSS
     */
    private function analyze_lss_structure($lss_data) {
        $analysis = array(
            'survey_info' => $lss_data['survey_info'],
            'total_questions' => count($lss_data['questions']),
            'total_groups' => count($lss_data['groups']),
            'question_types' => array(),
            'structure_complexity' => 'simple',
            'has_sub_questions' => false,
            'has_matrix_questions' => false,
            'language' => $lss_data['survey_info']['language'] ?? 'th',
            'groups_structure' => array(),
            'display_recommendations' => array()
        );
        
        // วิเคราะห์ประเภทคำถาม
        foreach ($lss_data['questions'] as $qid => $question) {
            $type = $question['type'];
            if (!isset($analysis['question_types'][$type])) {
                $analysis['question_types'][$type] = 0;
            }
            $analysis['question_types'][$type]++;
            
            // ตรวจสอบ sub-questions
            if ($question['parent_qid'] != '0') {
                $analysis['has_sub_questions'] = true;
            }
            
            // ตรวจสอบ matrix questions
            if (in_array($type, ['F', 'H', '1', ':', ';'])) {
                $analysis['has_matrix_questions'] = true;
            }
        }
        
        // วิเคราะห์กลุ่มคำถาม
        foreach ($lss_data['groups'] as $gid => $group) {
            $analysis['groups_structure'][$gid] = array(
                'name' => $group['group_name'],
                'description' => $group['description'],
                'order' => $group['group_order'],
                'questions_count' => 0
            );
        }
        
        // นับคำถามในแต่ละกลุ่ม
        foreach ($lss_data['questions'] as $qid => $question) {
            $gid = $question['gid'];
            if (isset($analysis['groups_structure'][$gid])) {
                $analysis['groups_structure'][$gid]['questions_count']++;
            }
        }
        
        // กำหนดระดับความซับซ้อน
        if ($analysis['total_questions'] > 50 || $analysis['has_matrix_questions']) {
            $analysis['structure_complexity'] = 'complex';
        } elseif ($analysis['total_questions'] > 20 || $analysis['has_sub_questions']) {
            $analysis['structure_complexity'] = 'medium';
        }
        
        return $analysis;
    }
    
    /**
     * วิเคราะห์โครงสร้าง API
     */
    private function analyze_api_structure($api_structure) {
        // คล้ายกับ analyze_lss_structure แต่สำหรับข้อมูลจาก API
        $analysis = array(
            'source' => 'api',
            'total_questions' => count($api_structure['questions'] ?? array()),
            'question_types' => array(),
            'structure_complexity' => 'simple',
            'language' => 'th'
        );
        
        // วิเคราะห์ประเภทคำถามจาก API
        if (isset($api_structure['questions'])) {
            foreach ($api_structure['questions'] as $question) {
                $type = $question['type'] ?? 'text';
                if (!isset($analysis['question_types'][$type])) {
                    $analysis['question_types'][$type] = 0;
                }
                $analysis['question_types'][$type]++;
            }
        }
        
        return $analysis;
    }
    
    /**
     * สร้าง display configuration
     */
    private function create_display_config($analysis) {
        $config = array(
            'layout_mode' => 'enhanced',
            'group_display' => true,
            'show_question_numbers' => true,
            'show_help_text' => true,
            'answer_display_mode' => 'formatted',
            'use_original_labels' => true,
            'responsive_layout' => true
        );
        
        // ปรับ config ตามความซับซ้อน
        switch ($analysis['structure_complexity']) {
            case 'complex':
                $config['layout_mode'] = 'grouped';
                $config['group_display'] = true;
                $config['collapsible_sections'] = true;
                break;
                
            case 'medium':
                $config['layout_mode'] = 'enhanced';
                $config['group_display'] = true;
                break;
                
            default:
                $config['layout_mode'] = 'simple';
                $config['group_display'] = false;
        }
        
        return $config;
    }
    
    /**
     * สร้างคำแนะนำ
     */
    private function generate_recommendations($analysis) {
        $recommendations = array();
        
        if ($analysis['structure_complexity'] === 'complex') {
            $recommendations[] = 'แนะนำให้ใช้การแสดงผลแบบจัดกลุ่มเพื่อความง่ายในการอ่าน';
            $recommendations[] = 'ควรเปิดใช้งานการย่อ/ขยายส่วนต่างๆ';
        }
        
        if ($analysis['has_matrix_questions']) {
            $recommendations[] = 'พบคำถามแบบ Matrix ควรแสดงในรูปแบบตารางเพื่อความชัดเจน';
        }
        
        if ($analysis['total_questions'] > 30) {
            $recommendations[] = 'มีคำถามจำนวนมาก แนะนำให้ใช้ระบบค้นหาและกรอง';
        }
        
        return $recommendations;
    }
    
    /**
     * Cache ผลลัพธ์การตรวจจับ
     */
    private function cache_detection_result($survey_id, $result) {
        $cache_key = 'tpak_auto_detection_' . $survey_id;
        set_transient($cache_key, $result, 3600); // Cache 1 ชั่วโมง
        
        // บันทึก log
        error_log('TPAK Auto Detector: Cached result for Survey ID: ' . $survey_id);
    }
    
    /**
     * ดึงผลลัพธ์ที่ cache ไว้
     */
    public function get_cached_result($survey_id) {
        $cache_key = 'tpak_auto_detection_' . $survey_id;
        return get_transient($cache_key);
    }
    
    /**
     * ล้าง cache
     */
    public function clear_cache($survey_id = null) {
        if ($survey_id) {
            $cache_key = 'tpak_auto_detection_' . $survey_id;
            delete_transient($cache_key);
        } else {
            // ล้าง cache ทั้งหมด
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tpak_auto_detection_%'");
        }
    }
}