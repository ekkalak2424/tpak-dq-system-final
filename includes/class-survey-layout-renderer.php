<?php
/**
 * TPAK DQ System - Survey Layout Renderer
 * 
 * ระบบจัดรูปแบบการแสดงผลแบบสอบถามให้เหมือนต้นฉบับมากที่สุด
 * วิเคราะห์โครงสร้างและสร้าง layout ที่เหมาะสม
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Survey_Layout_Renderer {
    
    private static $instance = null;
    private $survey_structure = null;
    private $display_config = null;
    private $response_data = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * เตรียมข้อมูลสำหรับการแสดงผล
     */
    public function prepare_survey_display($survey_id, $response_data, $display_config = null) {
        $this->response_data = $response_data;
        $this->display_config = $display_config ?: $this->get_default_config();
        
        // ดึงโครงสร้างจาก LSS หรือ API
        $this->survey_structure = $this->load_survey_structure($survey_id);
        
        if (!$this->survey_structure) {
            return false;
        }
        
        return true;
    }
    
    /**
     * โหลดโครงสร้างแบบสอบถาม
     */
    private function load_survey_structure($survey_id) {
        // ลองดึงจาก LSS ก่อน
        $lss_structure = get_option('tpak_lss_structure_' . $survey_id, false);
        if ($lss_structure) {
            return $this->process_lss_structure($lss_structure);
        }
        
        // ถ้าไม่มี LSS ให้ลองดึงจาก API
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-api-handler.php';
        $api_handler = new TPAK_DQ_API_Handler();
        $api_structure = $api_handler->get_survey_structure($survey_id);
        
        if ($api_structure) {
            return $this->process_api_structure($api_structure);
        }
        
        return null;
    }
    
    /**
     * ประมวลผลโครงสร้าง LSS
     */
    private function process_lss_structure($lss_data) {
        $processed = array(
            'source' => 'lss',
            'survey_info' => $lss_data['survey_info'],
            'groups' => array(),
            'questions' => array(),
            'question_texts' => $lss_data['question_texts'],
            'answers' => $lss_data['answers']
        );
        
        // จัดเรียงกลุ่มตาม order
        $groups = $lss_data['groups'];
        uasort($groups, function($a, $b) {
            return intval($a['group_order']) - intval($b['group_order']);
        });
        
        foreach ($groups as $gid => $group) {
            $processed['groups'][$gid] = array(
                'gid' => $gid,
                'name' => $group['group_name'],
                'description' => $group['description'],
                'order' => intval($group['group_order']),
                'questions' => array()
            );
        }
        
        // จัดเรียงคำถามตาม order และจัดกลุ่ม
        $questions = $lss_data['questions'];
        uasort($questions, function($a, $b) {
            return intval($a['sort_order']) - intval($b['sort_order']);
        });
        
        foreach ($questions as $qid => $question) {
            $gid = $question['gid'];
            $title = $question['title'];
            
            $question_data = array(
                'qid' => $qid,
                'title' => $title,
                'type' => $question['type'],
                'question_text' => isset($lss_data['question_texts'][$qid]) ? 
                    $lss_data['question_texts'][$qid]['question'] : $title,
                'help_text' => isset($lss_data['question_texts'][$qid]) ? 
                    $lss_data['question_texts'][$qid]['help'] : '',
                'mandatory' => $question['mandatory'] === 'Y',
                'parent_qid' => $question['parent_qid'],
                'sub_questions' => array(),
                'answers' => isset($lss_data['answers'][$qid]) ? $lss_data['answers'][$qid] : array(),
                'response_value' => null,
                'formatted_value' => null
            );
            
            // หาค่าคำตอบจาก response data
            if ($this->response_data && isset($this->response_data[$title])) {
                $question_data['response_value'] = $this->response_data[$title];
                $question_data['formatted_value'] = $this->format_answer_value(
                    $this->response_data[$title], 
                    $question_data
                );
            }
            
            $processed['questions'][$qid] = $question_data;
            
            // เพิ่มคำถามเข้ากลุ่ม
            if (isset($processed['groups'][$gid])) {
                $processed['groups'][$gid]['questions'][] = $qid;
            }
        }
        
        // จัดการ sub-questions
        $this->organize_sub_questions($processed);
        
        return $processed;
    }
    
    /**
     * ประมวลผลโครงสร้าง API
     */
    private function process_api_structure($api_data) {
        // สำหรับข้อมูลจาก API ที่อาจไม่สมบูรณ์เท่า LSS
        $processed = array(
            'source' => 'api',
            'survey_info' => array(
                'title' => 'Survey ' . ($api_data['survey_id'] ?? ''),
                'description' => ''
            ),
            'groups' => array(),
            'questions' => array()
        );
        
        // สร้างกลุ่มเริ่มต้น
        $processed['groups']['default'] = array(
            'gid' => 'default',
            'name' => 'คำถามทั้งหมด',
            'description' => '',
            'order' => 1,
            'questions' => array()
        );
        
        // ประมวลผลคำถาม
        if (isset($api_data['questions'])) {
            foreach ($api_data['questions'] as $title => $question) {
                $qid = 'q_' . count($processed['questions']);
                
                $question_data = array(
                    'qid' => $qid,
                    'title' => $title,
                    'type' => $question['type'] ?? 'T',
                    'question_text' => $question['question'] ?? $title,
                    'help_text' => $question['help'] ?? '',
                    'mandatory' => false,
                    'parent_qid' => '0',
                    'answers' => array(),
                    'response_value' => null,
                    'formatted_value' => null
                );
                
                // หาค่าคำตอบ
                if ($this->response_data && isset($this->response_data[$title])) {
                    $question_data['response_value'] = $this->response_data[$title];
                    $question_data['formatted_value'] = $this->format_answer_value(
                        $this->response_data[$title], 
                        $question_data
                    );
                }
                
                $processed['questions'][$qid] = $question_data;
                $processed['groups']['default']['questions'][] = $qid;
            }
        }
        
        return $processed;
    }
    
    /**
     * จัดการ sub-questions
     */
    private function organize_sub_questions(&$processed) {
        foreach ($processed['questions'] as $qid => $question) {
            if ($question['parent_qid'] !== '0') {
                $parent_qid = $question['parent_qid'];
                if (isset($processed['questions'][$parent_qid])) {
                    $processed['questions'][$parent_qid]['sub_questions'][$qid] = $question;
                    // ลบจากรายการหลัก
                    unset($processed['questions'][$qid]);
                }
            }
        }
    }
    
    /**
     * Format ค่าคำตอบ
     */
    private function format_answer_value($raw_value, $question_data) {
        // ถ้ามี answer options ให้ใช้
        if (!empty($question_data['answers'])) {
            foreach ($question_data['answers'] as $code => $answer) {
                if ($code === $raw_value && isset($answer['text'])) {
                    return $answer['text'];
                }
            }
        }
        
        // ใช้ Question Dictionary
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-question-dictionary.php';
        $dictionary = TPAK_Question_Dictionary::getInstance();
        
        $formatted = $dictionary->getAnswerText($raw_value);
        return $formatted !== $raw_value ? $formatted : $raw_value;
    }
    
    /**
     * สร้าง HTML สำหรับแสดงผล
     */
    public function render_survey_layout() {
        if (!$this->survey_structure) {
            return '<div class="tpak-error">ไม่พบโครงสร้างแบบสอบถาม</div>';
        }
        
        $html = '<div class="tpak-survey-layout ' . $this->display_config['layout_mode'] . '">';
        
        // Header
        $html .= $this->render_survey_header();
        
        // Navigation
        if (isset($this->display_config['show_navigation']) && $this->display_config['show_navigation']) {
            $html .= $this->render_navigation();
        }
        
        // Groups
        foreach ($this->survey_structure['groups'] as $gid => $group) {
            if (!empty($group['questions'])) {
                $html .= $this->render_group($group);
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * แสดง Header ของแบบสอบถาม
     */
    private function render_survey_header() {
        $info = $this->survey_structure['survey_info'];
        
        $html = '<div class="survey-header">';
        $html .= '<h1 class="survey-title">' . esc_html($info['title']) . '</h1>';
        
        if (!empty($info['description'])) {
            $html .= '<div class="survey-description">' . nl2br(esc_html($info['description'])) . '</div>';
        }
        
        // Survey stats
        $total_questions = count($this->survey_structure['questions']);
        $answered_questions = 0;
        
        foreach ($this->survey_structure['questions'] as $question) {
            if ($question['response_value'] !== null && $question['response_value'] !== '') {
                $answered_questions++;
            }
        }
        
        $completion_rate = $total_questions > 0 ? round(($answered_questions / $total_questions) * 100) : 0;
        
        $html .= '<div class="survey-stats">';
        $html .= '<div class="stat-item">';
        $html .= '<span class="stat-number">' . $answered_questions . '/' . $total_questions . '</span>';
        $html .= '<span class="stat-label">คำถามที่ตอบแล้ว</span>';
        $html .= '</div>';
        $html .= '<div class="stat-item">';
        $html .= '<span class="stat-number">' . $completion_rate . '%</span>';
        $html .= '<span class="stat-label">ความสมบูรณ์</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * แสดง Navigation
     */
    private function render_navigation() {
        $html = '<div class="survey-navigation">';
        $html .= '<h3>รายการคำถาม</h3>';
        $html .= '<ul class="question-nav">';
        
        foreach ($this->survey_structure['groups'] as $gid => $group) {
            if (!empty($group['questions'])) {
                $html .= '<li class="nav-group">';
                $html .= '<a href="#group-' . $gid . '" class="nav-group-link">';
                $html .= esc_html($group['name']);
                $html .= '<span class="question-count">(' . count($group['questions']) . ')</span>';
                $html .= '</a>';
                $html .= '</li>';
            }
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * แสดงกลุ่มคำถาม
     */
    private function render_group($group) {
        $html = '<div class="survey-group" id="group-' . $group['gid'] . '">';
        
        // Group header
        $html .= '<div class="group-header">';
        $html .= '<h2 class="group-title">' . esc_html($group['name']) . '</h2>';
        
        if (!empty($group['description'])) {
            $html .= '<div class="group-description">' . nl2br(esc_html($group['description'])) . '</div>';
        }
        $html .= '</div>';
        
        // Questions
        $html .= '<div class="group-questions">';
        foreach ($group['questions'] as $qid) {
            if (isset($this->survey_structure['questions'][$qid])) {
                $question = $this->survey_structure['questions'][$qid];
                $html .= $this->render_question($question);
            }
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * แสดงคำถาม
     */
    private function render_question($question) {
        $html = '<div class="survey-question" data-qid="' . $question['qid'] . '">';
        
        // Question header
        $html .= '<div class="question-header">';
        if (isset($this->display_config['show_question_numbers']) && $this->display_config['show_question_numbers']) {
            $html .= '<div class="question-number">' . $question['title'] . '</div>';
        }
        $html .= '<div class="question-text">' . nl2br(esc_html($question['question_text'])) . '</div>';
        
        if (isset($question['mandatory']) && $question['mandatory']) {
            $html .= '<span class="required-indicator">*</span>';
        }
        $html .= '</div>';
        
        // Help text
        if (isset($this->display_config['show_help_text']) && $this->display_config['show_help_text'] && !empty($question['help_text'])) {
            $html .= '<div class="question-help">' . nl2br(esc_html($question['help_text'])) . '</div>';
        }
        
        // Answer
        $html .= '<div class="question-answer">';
        if ($question['response_value'] !== null) {
            $html .= '<div class="answer-value">';
            $html .= '<strong>คำตอบ:</strong> ';
            $html .= esc_html($question['formatted_value'] ?: $question['response_value']);
            $html .= '</div>';
        } else {
            $html .= '<div class="no-answer">ไม่ได้ตอบ</div>';
        }
        $html .= '</div>';
        
        // Sub-questions
        if (!empty($question['sub_questions'])) {
            $html .= '<div class="sub-questions">';
            $html .= '<h4>คำถามย่อย:</h4>';
            foreach ($question['sub_questions'] as $sub_question) {
                $html .= $this->render_question($sub_question);
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * ค่าเริ่มต้นสำหรับ display config
     */
    private function get_default_config() {
        return array(
            'layout_mode' => 'original',
            'show_navigation' => false,
            'group_display' => true,
            'show_question_numbers' => true,
            'show_help_text' => true,
            'answer_display_mode' => 'formatted',
            'use_original_labels' => true,
            'responsive_layout' => true,
            'collapsible_sections' => false
        );
    }
}