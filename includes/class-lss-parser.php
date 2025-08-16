<?php
/**
 * TPAK DQ System - LimeSurvey Structure (.lss) Parser
 * 
 * Parse LimeSurvey export files to extract survey structure and question mappings
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_LSS_Parser {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Parse LSS file and extract survey structure
     */
    public function parse_lss_file($file_path) {
        if (!file_exists($file_path)) {
            return array(
                'success' => false,
                'message' => 'ไฟล์ไม่พบ'
            );
        }
        
        // Read and parse XML
        $xml_content = file_get_contents($file_path);
        if ($xml_content === false) {
            return array(
                'success' => false,
                'message' => 'ไม่สามารถอ่านไฟล์ได้'
            );
        }
        
        // Parse XML
        $xml = simplexml_load_string($xml_content);
        if ($xml === false) {
            return array(
                'success' => false,
                'message' => 'ไฟล์ไม่ใช่ format XML ที่ถูกต้อง'
            );
        }
        
        // Extract data
        $result = array(
            'success' => true,
            'survey_info' => $this->extract_survey_info($xml),
            'questions' => $this->extract_questions($xml),
            'question_texts' => $this->extract_question_texts($xml),
            'answers' => $this->extract_answers($xml),
            'groups' => $this->extract_groups($xml),
            'statistics' => array()
        );
        
        // Calculate statistics
        $result['statistics'] = $this->calculate_statistics($result);
        
        return $result;
    }
    
    /**
     * Extract survey basic information
     */
    private function extract_survey_info($xml) {
        $info = array(
            'survey_id' => null,
            'title' => '',
            'description' => '',
            'language' => 'th',
            'db_version' => '',
            'welcome_text' => '',
            'end_text' => ''
        );
        
        // Get DB Version
        if (isset($xml->DBVersion)) {
            $info['db_version'] = (string)$xml->DBVersion;
        }
        
        // Get Language
        if (isset($xml->languages->language)) {
            $info['language'] = (string)$xml->languages->language;
        }
        
        // Get Survey ID from surveys section
        if (isset($xml->surveys->rows->row->sid)) {
            $info['survey_id'] = (string)$xml->surveys->rows->row->sid;
        }
        
        // Get survey texts
        if (isset($xml->surveys_languagesettings->rows->row)) {
            $lang_settings = $xml->surveys_languagesettings->rows->row;
            
            if (isset($lang_settings->surveyls_title)) {
                $info['title'] = $this->clean_html((string)$lang_settings->surveyls_title);
            }
            
            if (isset($lang_settings->surveyls_description)) {
                $info['description'] = $this->clean_html((string)$lang_settings->surveyls_description);
            }
            
            if (isset($lang_settings->surveyls_welcometext)) {
                $info['welcome_text'] = $this->clean_html((string)$lang_settings->surveyls_welcometext);
            }
            
            if (isset($lang_settings->surveyls_endtext)) {
                $info['end_text'] = $this->clean_html((string)$lang_settings->surveyls_endtext);
            }
        }
        
        return $info;
    }
    
    /**
     * Extract questions structure
     */
    private function extract_questions($xml) {
        $questions = array();
        
        if (!isset($xml->questions->rows->row)) {
            return $questions;
        }
        
        foreach ($xml->questions->rows->row as $question) {
            $qid = (string)$question->qid;
            $title = (string)$question->title;
            
            $questions[$qid] = array(
                'qid' => $qid,
                'title' => $title,
                'parent_qid' => (string)$question->parent_qid,
                'gid' => (string)$question->gid,
                'type' => (string)$question->type,
                'mandatory' => (string)$question->mandatory,
                'other' => (string)$question->other,
                'sort_order' => (string)$question->question_order,
                'relevance' => (string)$question->relevance,
                'scale_id' => (string)$question->scale_id,
                'same_default' => (string)$question->same_default
            );
        }
        
        return $questions;
    }
    
    /**
     * Extract question texts (Thai language)
     */
    private function extract_question_texts($xml) {
        $texts = array();
        
        if (!isset($xml->question_l10ns->rows->row)) {
            return $texts;
        }
        
        foreach ($xml->question_l10ns->rows->row as $text) {
            $qid = (string)$text->qid;
            $language = (string)$text->language;
            
            // Focus on Thai language
            if ($language === 'th' || empty($language)) {
                $texts[$qid] = array(
                    'qid' => $qid,
                    'question' => $this->clean_html((string)$text->question),
                    'help' => $this->clean_html((string)$text->help),
                    'language' => $language
                );
            }
        }
        
        return $texts;
    }
    
    /**
     * Extract answer options
     */
    private function extract_answers($xml) {
        $answers = array();
        
        if (!isset($xml->answers->rows->row)) {
            return $answers;
        }
        
        foreach ($xml->answers->rows->row as $answer) {
            $qid = (string)$answer->qid;
            $code = (string)$answer->code;
            
            if (!isset($answers[$qid])) {
                $answers[$qid] = array();
            }
            
            $answers[$qid][$code] = array(
                'aid' => (string)$answer->aid,
                'code' => $code,
                'sortorder' => (string)$answer->sortorder,
                'assessment_value' => (string)$answer->assessment_value,
                'scale_id' => (string)$answer->scale_id
            );
        }
        
        // Get answer texts
        if (isset($xml->answer_l10ns->rows->row)) {
            foreach ($xml->answer_l10ns->rows->row as $answer_text) {
                $aid = (string)$answer_text->aid;
                $text = $this->clean_html((string)$answer_text->answer);
                
                // Find corresponding question and code
                foreach ($answers as $qid => $qid_answers) {
                    foreach ($qid_answers as $code => $answer_data) {
                        if ($answer_data['aid'] === $aid) {
                            $answers[$qid][$code]['text'] = $text;
                        }
                    }
                }
            }
        }
        
        return $answers;
    }
    
    /**
     * Extract question groups
     */
    private function extract_groups($xml) {
        $groups = array();
        
        if (!isset($xml->groups->rows->row)) {
            return $groups;
        }
        
        foreach ($xml->groups->rows->row as $group) {
            $gid = (string)$group->gid;
            
            $groups[$gid] = array(
                'gid' => $gid,
                'group_name' => (string)$group->group_name,
                'description' => $this->clean_html((string)$group->description),
                'group_order' => (string)$group->group_order,
                'randomization_group' => (string)$group->randomization_group,
                'grelevance' => (string)$group->grelevance
            );
        }
        
        // Get group texts
        if (isset($xml->group_l10ns->rows->row)) {
            foreach ($xml->group_l10ns->rows->row as $group_text) {
                $gid = (string)$group_text->gid;
                $language = (string)$group_text->language;
                
                if (($language === 'th' || empty($language)) && isset($groups[$gid])) {
                    $groups[$gid]['group_name'] = (string)$group_text->group_name;
                    $groups[$gid]['description'] = $this->clean_html((string)$group_text->description);
                }
            }
        }
        
        return $groups;
    }
    
    /**
     * Calculate statistics
     */
    private function calculate_statistics($data) {
        return array(
            'total_questions' => count($data['questions']),
            'total_groups' => count($data['groups']),
            'questions_with_text' => count($data['question_texts']),
            'questions_with_answers' => count($data['answers']),
            'survey_id' => $data['survey_info']['survey_id'],
            'language' => $data['survey_info']['language'],
            'completion_rate' => count($data['questions']) > 0 ? 
                round((count($data['question_texts']) / count($data['questions'])) * 100) : 0
        );
    }
    
    /**
     * Clean HTML tags and decode entities
     */
    private function clean_html($html) {
        if (empty($html)) {
            return '';
        }
        
        // Remove CDATA
        $html = str_replace(array('<![CDATA[', ']]>'), '', $html);
        
        // Strip HTML tags but preserve line breaks
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        return $text;
    }
    
    /**
     * Convert parsed data to Question Dictionary format
     */
    public function convert_to_question_dictionary($parsed_data) {
        $dictionary_data = array(
            'questions' => array(),
            'answers' => array(),
            'groups' => array(),
            'survey_id' => $parsed_data['survey_info']['survey_id']
        );
        
        // Process questions
        foreach ($parsed_data['questions'] as $qid => $question) {
            $title = $question['title'];
            $question_text = '';
            
            // Get Thai text if available
            if (isset($parsed_data['question_texts'][$qid])) {
                $question_text = $parsed_data['question_texts'][$qid]['question'];
            }
            
            if (!empty($question_text)) {
                $dictionary_data['questions'][$title] = $question_text;
            }
        }
        
        // Process answers
        foreach ($parsed_data['answers'] as $qid => $qid_answers) {
            foreach ($qid_answers as $code => $answer) {
                if (isset($answer['text']) && !empty($answer['text'])) {
                    $dictionary_data['answers'][$code] = $answer['text'];
                }
            }
        }
        
        // Process groups
        foreach ($parsed_data['groups'] as $gid => $group) {
            if (!empty($group['group_name'])) {
                $dictionary_data['groups'][$gid] = $group['group_name'];
            }
        }
        
        return $dictionary_data;
    }
    
    /**
     * Save parsed data to database as custom mappings
     */
    public function save_to_database($parsed_data, $survey_id = null) {
        if (!$survey_id) {
            $survey_id = $parsed_data['survey_info']['survey_id'];
        }
        
        if (!$survey_id) {
            return false;
        }
        
        // Convert to dictionary format
        $dictionary_data = $this->convert_to_question_dictionary($parsed_data);
        
        // Save as WordPress option
        $option_name = 'tpak_question_mappings_' . $survey_id;
        $saved = update_option($option_name, $dictionary_data);
        
        // Also save full parsed data for reference
        $full_option_name = 'tpak_lss_structure_' . $survey_id;
        update_option($full_option_name, $parsed_data);
        
        return $saved;
    }
    
    /**
     * Get all available LSS structures
     */
    public function get_available_structures() {
        global $wpdb;
        
        $options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE 'tpak_lss_structure_%'",
            ARRAY_A
        );
        
        $structures = array();
        
        foreach ($options as $option) {
            $survey_id = str_replace('tpak_lss_structure_', '', $option['option_name']);
            $data = maybe_unserialize($option['option_value']);
            
            if ($data && isset($data['survey_info'])) {
                $structures[$survey_id] = array(
                    'survey_id' => $survey_id,
                    'title' => $data['survey_info']['title'],
                    'description' => substr($data['survey_info']['description'], 0, 200) . '...',
                    'questions_count' => count($data['questions']),
                    'groups_count' => count($data['groups']),
                    'language' => $data['survey_info']['language']
                );
            }
        }
        
        return $structures;
    }
}