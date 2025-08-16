<?php
/**
 * TPAK DQ System - Survey Adapter
 * 
 * Handles different survey structures and formats from various Survey IDs
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Survey_Adapter {
    
    private static $instance = null;
    private $adapters = array();
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_adapters();
    }
    
    /**
     * Register different survey adapters
     */
    private function register_adapters() {
        // LimeSurvey standard format
        $this->adapters['limesurvey'] = new TPAK_LimeSurvey_Adapter();
        
        // Numeric question format (1, 2, 3...)
        $this->adapters['numeric'] = new TPAK_Numeric_Adapter();
        
        // Descriptive format (name, age, address...)
        $this->adapters['descriptive'] = new TPAK_Descriptive_Adapter();
        
        // Mixed format
        $this->adapters['mixed'] = new TPAK_Mixed_Adapter();
    }
    
    /**
     * Get appropriate adapter for survey
     */
    public function getAdapter($survey_id, $response_data = null) {
        // Get survey structure to determine adapter type
        $api_handler = new TPAK_DQ_API_Handler();
        $survey_structure = $api_handler->get_survey_structure($survey_id);
        
        if ($survey_structure && isset($survey_structure['structure_type'])) {
            $type = $survey_structure['structure_type'];
        } else {
            // Analyze response data to determine type
            $type = $this->analyze_response_structure($response_data);
        }
        
        return isset($this->adapters[$type]) ? $this->adapters[$type] : $this->adapters['mixed'];
    }
    
    /**
     * Analyze response data structure to determine adapter type
     */
    private function analyze_response_structure($response_data) {
        if (!is_array($response_data)) {
            return 'mixed';
        }
        
        $keys = array_keys($response_data);
        $limesurvey_count = 0;
        $numeric_count = 0;
        $descriptive_count = 0;
        
        foreach ($keys as $key) {
            if (preg_match('/^Q\d+/', $key)) {
                $limesurvey_count++;
            } elseif (preg_match('/^\d+[a-z]*$/', $key)) {
                $numeric_count++;
            } elseif (preg_match('/^[a-zA-Z_]+/', $key)) {
                $descriptive_count++;
            }
        }
        
        $total = count($keys);
        if ($limesurvey_count > $total * 0.6) {
            return 'limesurvey';
        } elseif ($numeric_count > $total * 0.6) {
            return 'numeric';
        } elseif ($descriptive_count > $total * 0.6) {
            return 'descriptive';
        } else {
            return 'mixed';
        }
    }
    
    /**
     * Process response with appropriate adapter
     */
    public function processResponse($survey_id, $response_data) {
        $adapter = $this->getAdapter($survey_id, $response_data);
        return $adapter->process($response_data, $survey_id);
    }
}

/**
 * Base adapter class
 */
abstract class TPAK_Survey_Adapter_Base {
    
    abstract public function process($response_data, $survey_id = null);
    
    protected function clean_field_key($key) {
        $clean = str_replace(['_', '-'], ' ', $key);
        return ucwords(strtolower($clean));
    }
    
    protected function format_value($value, $type = 'text') {
        if (empty($value) && $value !== '0') {
            return '<em class="empty-value">ไม่ได้ระบุ</em>';
        }
        
        switch ($type) {
            case 'date':
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                    return date('j F Y', strtotime($value));
                }
                break;
                
            case 'number':
                if (is_numeric($value)) {
                    return number_format($value);
                }
                break;
                
            case 'phone':
                if (preg_match('/^0\d{9}$/', $value)) {
                    return preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1-$2-$3', $value);
                }
                break;
        }
        
        return nl2br(esc_html($value));
    }
}

/**
 * LimeSurvey format adapter (Q1, Q1A1, etc.)
 */
class TPAK_LimeSurvey_Adapter extends TPAK_Survey_Adapter_Base {
    
    private $dictionary;
    
    public function process($response_data, $survey_id = null) {
        // Initialize dictionary
        $this->dictionary = TPAK_Question_Dictionary::getInstance();
        $this->dictionary->loadCustomMappings($survey_id);
        
        $processed = array(
            'questions' => array(),
            'structure_type' => 'limesurvey',
            'confidence' => 0.9
        );
        
        // Get survey structure from multiple sources (LSS first, then API)
        $question_map = array();
        $groups = array();
        $structure_source = 'none';
        
        // First try LSS structure (highest priority)
        $lss_structure = get_option('tpak_lss_structure_' . $survey_id, false);
        if ($lss_structure) {
            $question_map = $this->process_lss_questions($lss_structure);
            $groups = isset($lss_structure['groups']) ? $lss_structure['groups'] : array();
            $structure_source = 'lss';
            error_log('TPAK DQ System: Using LSS structure for Survey ID: ' . $survey_id . ' with ' . count($question_map) . ' questions');
        } else {
            // Fallback to API
            $api_handler = new TPAK_DQ_API_Handler();
            $survey_structure = $api_handler->get_survey_structure($survey_id);
            
            if ($survey_structure && isset($survey_structure['questions'])) {
                $question_map = $survey_structure['questions'];
                $structure_source = 'api';
            }
            
            if ($survey_structure && isset($survey_structure['groups'])) {
                $groups = $survey_structure['groups'];
            }
        }
        
        foreach ($response_data as $key => $value) {
            if ($this->should_skip_field($key, $value)) {
                continue;
            }
            
            // Parse complex field keys (e.g., PA1TT2[1])
            $parsed = $this->parse_field_key($key);
            $main_key = $parsed['main'];
            $sub_key = $parsed['sub'];
            
            // Get question text from multiple sources
            $question_text = '';
            $type = 'text';
            $confidence = 0.5;
            
            // 1. Try from survey structure (LSS or API)
            if (isset($question_map[$main_key])) {
                $question_data = $question_map[$main_key];
                $question_text = strip_tags($question_data['question']);
                $type = $question_data['type'] ?? 'text';
                $confidence = ($structure_source === 'lss') ? 0.98 : 0.95;
                
                // Add sub-question information
                if ($sub_key && isset($question_data['sub_questions'][$sub_key])) {
                    $question_text .= ' - ' . $question_data['sub_questions'][$sub_key];
                } elseif ($sub_key) {
                    $question_text .= ' [' . $sub_key . ']';
                }
            }
            // 2. Try exact field matching in LSS (for complex keys)
            elseif ($structure_source === 'lss' && $lss_structure) {
                $lss_match = $this->find_lss_question_by_field($key, $lss_structure);
                if ($lss_match) {
                    $question_text = $lss_match['question'];
                    $type = $lss_match['type'];
                    $confidence = 0.95;
                } else {
                    $question_text = $this->dictionary->getQuestionText($main_key);
                    if ($sub_key) {
                        $sub_text = $this->dictionary->getQuestionText($sub_key);
                        if ($sub_text !== $sub_key) {
                            $question_text .= ' - ' . $sub_text;
                        } else {
                            $question_text .= ' [' . $sub_key . ']';
                        }
                    }
                    $confidence = 0.8;
                }
            }
            // 3. Try question dictionary
            else {
                $question_text = $this->dictionary->getQuestionText($main_key);
                if ($sub_key) {
                    $sub_text = $this->dictionary->getQuestionText($sub_key);
                    if ($sub_text !== $sub_key) {
                        $question_text .= ' - ' . $sub_text;
                    } else {
                        $question_text .= ' [' . $sub_key . ']';
                    }
                }
                $confidence = 0.8;
            }
            
            // Format the answer value
            $formatted_value = $this->format_answer($value, $type, $key);
            
            $processed['questions'][$key] = array(
                'original_key' => $key,
                'display_name' => $question_text,
                'category' => $this->guess_category($key, $question_text),
                'type' => $type,
                'original_value' => $value,
                'formatted_value' => $formatted_value,
                'confidence' => $confidence,
                'main_key' => $main_key,
                'sub_key' => $sub_key
            );
        }
        
        return $processed;
    }
    
    /**
     * Parse complex field keys like PA1TT2[1] or Q1_SQ001
     */
    private function parse_field_key($key) {
        $result = array(
            'main' => $key,
            'sub' => null,
            'index' => null
        );
        
        // Pattern 1: Field[index] format
        if (preg_match('/^([^\[]+)\[(\d+)\]$/', $key, $matches)) {
            $result['main'] = $matches[1];
            $result['index'] = $matches[2];
            $result['sub'] = $matches[2];
        }
        // Pattern 2: Field_SubField format
        elseif (preg_match('/^([^_]+)_(.+)$/', $key, $matches)) {
            $result['main'] = $matches[1];
            $result['sub'] = $matches[2];
        }
        // Pattern 3: Q1A1 format
        elseif (preg_match('/^(Q\d+)([A-Z]\d*)/', $key, $matches)) {
            $result['main'] = $matches[1];
            $result['sub'] = $matches[2];
        }
        // Pattern 4: Complex patterns like PA1TT2
        elseif (preg_match('/^([A-Z]+\d+[A-Z]+\d+)(.*)/', $key, $matches)) {
            $result['main'] = $matches[1];
            if (!empty($matches[2])) {
                $result['sub'] = trim($matches[2], '[]');
            }
        }
        
        return $result;
    }
    
    /**
     * Generate Thai label from field key
     */
    private function generate_thai_label($key, $parsed) {
        $main = $parsed['main'];
        $sub = $parsed['sub'];
        
        // Handle specific patterns
        if (strpos($main, 'CONSENT') !== false) {
            return 'การยินยอมเข้าร่วมการวิจัย' . ($sub ? ' ข้อ ' . $sub : '');
        }
        
        if (preg_match('/^Q(\d+)/', $main, $matches)) {
            $label = 'คำถามที่ ' . $matches[1];
            if ($sub) {
                $label .= ' ข้อย่อย ' . $sub;
            }
            return $label;
        }
        
        if (preg_match('/^PA(\d+)/', $main, $matches)) {
            $label = 'ส่วนที่ ' . $matches[1];
            if ($sub) {
                $label .= ' ข้อ ' . $sub;
            }
            return $label;
        }
        
        // Default: clean the key
        return $this->clean_field_key($key);
    }
    
    /**
     * Format answer based on type and value
     */
    private function format_answer($value, $type, $key) {
        // Try dictionary first
        $context = null;
        if (stripos($key, 'CONSENT') !== false) {
            $context = 'consent';
        } elseif ($type === 'Y') {
            $context = 'yesno';
        } elseif (is_numeric($value) && strlen($value) <= 2) {
            $context = 'scale';
        }
        
        $formatted = $this->dictionary->getAnswerText($value, $context);
        
        if ($formatted !== $value) {
            return $formatted;
        }
        
        // Fallback to basic formatting
        return $this->format_value($value, $type);
    }
    
    /**
     * Process LSS questions into format compatible with Survey Adapter
     */
    private function process_lss_questions($lss_structure) {
        $processed_questions = array();
        
        if (!isset($lss_structure['questions']) || !is_array($lss_structure['questions'])) {
            return $processed_questions;
        }
        
        foreach ($lss_structure['questions'] as $qid => $question_data) {
            $title = isset($question_data['title']) ? $question_data['title'] : $qid;
            
            // Get question text from question_texts
            $question_text = $title;
            if (isset($lss_structure['question_texts'][$qid]['question'])) {
                $question_text = $lss_structure['question_texts'][$qid]['question'];
            }
            
            $processed_questions[$title] = array(
                'qid' => $qid,
                'title' => $title,
                'question' => $question_text,
                'type' => isset($question_data['type']) ? $question_data['type'] : 'text',
                'help' => '',
                'mandatory' => isset($question_data['mandatory']) ? $question_data['mandatory'] : 'N',
                'other' => isset($question_data['other']) ? $question_data['other'] : 'N',
                'group_id' => isset($question_data['gid']) ? $question_data['gid'] : null,
                'group_name' => '',
                'sub_questions' => array()
            );
        }
        
        return $processed_questions;
    }
    
    /**
     * Find LSS question by field key (advanced matching)
     */
    private function find_lss_question_by_field($field_key, $lss_structure) {
        if (!isset($lss_structure['questions']) || !isset($lss_structure['question_texts'])) {
            return null;
        }
        
        // Try exact title match first
        foreach ($lss_structure['questions'] as $qid => $question_data) {
            if (isset($question_data['title']) && $question_data['title'] === $field_key) {
                $question_text = isset($lss_structure['question_texts'][$qid]['question']) 
                    ? $lss_structure['question_texts'][$qid]['question'] 
                    : $question_data['title'];
                    
                return array(
                    'question' => $question_text,
                    'type' => $question_data['type'] ?? 'text'
                );
            }
        }
        
        // Try pattern matching for complex keys
        $parsed = $this->parse_field_key($field_key);
        $main_key = $parsed['main'];
        $sub_key = $parsed['sub'];
        
        foreach ($lss_structure['questions'] as $qid => $question_data) {
            if (isset($question_data['title'])) {
                $title = $question_data['title'];
                
                // Check if main key matches
                if ($title === $main_key || strpos($title, $main_key) !== false) {
                    $question_text = isset($lss_structure['question_texts'][$qid]['question']) 
                        ? $lss_structure['question_texts'][$qid]['question'] 
                        : $title;
                    
                    // Add sub-question info if exists
                    if ($sub_key) {
                        $question_text .= ' [' . $sub_key . ']';
                    }
                    
                    return array(
                        'question' => $question_text,
                        'type' => $question_data['type'] ?? 'text'
                    );
                }
            }
        }
        
        return null;
    }
    
    private function should_skip_field($key, $value) {
        if ($value === null || $value === '' || $value === ' ') {
            return true;
        }
        
        $metadata_fields = ['id', 'submitdate', 'lastpage', 'startlanguage', 'seed', 'startdate', 'datestamp'];
        return in_array($key, $metadata_fields);
    }
    
    private function guess_category($key, $text) {
        $categories = array(
            'personal' => ['name', 'age', 'birth', 'gender', 'ชื่อ', 'อายุ', 'เพศ'],
            'contact' => ['phone', 'email', 'address', 'โทร', 'อีเมล', 'ที่อยู่'],
            'education' => ['education', 'school', 'การศึกษา', 'โรงเรียน'],
            'work' => ['job', 'work', 'occupation', 'อาชีพ', 'งาน']
        );
        
        $combined_text = strtolower($key . ' ' . $text);
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($combined_text, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'survey';
    }
}

/**
 * Numeric format adapter (1, 2, 3, etc.)
 */
class TPAK_Numeric_Adapter extends TPAK_Survey_Adapter_Base {
    
    public function process($response_data, $survey_id = null) {
        $processed = array(
            'questions' => array(),
            'structure_type' => 'numeric',
            'confidence' => 0.6
        );
        
        foreach ($response_data as $key => $value) {
            if ($this->should_skip_field($key, $value)) {
                continue;
            }
            
            // Generate display name for numeric keys
            if (preg_match('/^(\d+)([a-z]*)$/', $key, $matches)) {
                $question_num = $matches[1];
                $sub_part = $matches[2];
                
                $display_name = 'คำถามที่ ' . $question_num;
                if (!empty($sub_part)) {
                    $display_name .= ' ส่วนที่ ' . strtoupper($sub_part);
                }
            } else {
                $display_name = $this->clean_field_key($key);
            }
            
            $processed['questions'][$key] = array(
                'original_key' => $key,
                'display_name' => $display_name,
                'category' => 'survey',
                'type' => 'text',
                'original_value' => $value,
                'formatted_value' => $this->format_value($value),
                'confidence' => 0.6
            );
        }
        
        return $processed;
    }
    
    private function should_skip_field($key, $value) {
        if ($value === null || $value === '' || $value === ' ') {
            return true;
        }
        
        return false;
    }
}

/**
 * Descriptive format adapter (name, age, address, etc.)
 */
class TPAK_Descriptive_Adapter extends TPAK_Survey_Adapter_Base {
    
    private $field_mappings = array(
        // Personal
        'name' => 'ชื่อ',
        'firstname' => 'ชื่อจริง', 
        'lastname' => 'นามสกุล',
        'age' => 'อายุ',
        'birth' => 'วันเกิด',
        'gender' => 'เพศ',
        'sex' => 'เพศ',
        
        // Contact
        'phone' => 'โทรศัพท์',
        'tel' => 'โทรศัพท์',
        'mobile' => 'มือถือ',
        'email' => 'อีเมล',
        'address' => 'ที่อยู่',
        'province' => 'จังหวัด',
        'district' => 'อำเภอ',
        
        // Education & Work
        'education' => 'การศึกษา',
        'school' => 'โรงเรียน',
        'job' => 'อาชีพ',
        'occupation' => 'อาชีพ',
        'income' => 'รายได้'
    );
    
    public function process($response_data, $survey_id = null) {
        $processed = array(
            'questions' => array(),
            'structure_type' => 'descriptive', 
            'confidence' => 0.8
        );
        
        foreach ($response_data as $key => $value) {
            if ($this->should_skip_field($key, $value)) {
                continue;
            }
            
            // Map to Thai display name
            $display_name = $this->get_display_name($key);
            $category = $this->guess_category($key);
            $type = $this->guess_type($key);
            
            $processed['questions'][$key] = array(
                'original_key' => $key,
                'display_name' => $display_name,
                'category' => $category,
                'type' => $type,
                'original_value' => $value,
                'formatted_value' => $this->format_value($value, $type),
                'confidence' => isset($this->field_mappings[strtolower($key)]) ? 0.9 : 0.5
            );
        }
        
        return $processed;
    }
    
    private function get_display_name($key) {
        $lower_key = strtolower($key);
        
        if (isset($this->field_mappings[$lower_key])) {
            return $this->field_mappings[$lower_key];
        }
        
        return $this->clean_field_key($key);
    }
    
    private function guess_category($key) {
        $categories = array(
            'personal' => ['name', 'age', 'birth', 'gender', 'sex'],
            'contact' => ['phone', 'tel', 'mobile', 'email', 'address', 'province', 'district'],
            'education' => ['education', 'school', 'university', 'degree'],
            'work' => ['job', 'occupation', 'work', 'income', 'salary']
        );
        
        $lower_key = strtolower($key);
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($lower_key, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'other';
    }
    
    private function guess_type($key) {
        $types = array(
            'gender' => ['gender', 'sex'],
            'date' => ['birth', 'date'],
            'number' => ['age', 'income', 'salary'],
            'phone' => ['phone', 'tel', 'mobile'],
            'email' => ['email']
        );
        
        $lower_key = strtolower($key);
        
        foreach ($types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($lower_key, $keyword) !== false) {
                    return $type;
                }
            }
        }
        
        return 'text';
    }
    
    private function should_skip_field($key, $value) {
        if ($value === null || $value === '' || $value === ' ') {
            return true;
        }
        
        $metadata_fields = ['id', 'submitdate', 'token'];
        return in_array($key, $metadata_fields);
    }
}

/**
 * Mixed format adapter (handles combination of different formats)
 */
class TPAK_Mixed_Adapter extends TPAK_Survey_Adapter_Base {
    
    public function process($response_data, $survey_id = null) {
        $processed = array(
            'questions' => array(),
            'structure_type' => 'mixed',
            'confidence' => 0.4
        );
        
        // Use other adapters for different parts
        $limesurvey_adapter = new TPAK_LimeSurvey_Adapter();
        $numeric_adapter = new TPAK_Numeric_Adapter();
        $descriptive_adapter = new TPAK_Descriptive_Adapter();
        
        foreach ($response_data as $key => $value) {
            if ($this->should_skip_field($key, $value)) {
                continue;
            }
            
            $question_data = null;
            
            // Try LimeSurvey format first
            if (preg_match('/^Q\d+/', $key)) {
                $result = $limesurvey_adapter->process(array($key => $value), $survey_id);
                if (isset($result['questions'][$key])) {
                    $question_data = $result['questions'][$key];
                }
            }
            // Try numeric format
            elseif (preg_match('/^\d+[a-z]*$/', $key)) {
                $result = $numeric_adapter->process(array($key => $value), $survey_id);
                if (isset($result['questions'][$key])) {
                    $question_data = $result['questions'][$key];
                }
            }
            // Try descriptive format
            elseif (preg_match('/^[a-zA-Z_]+/', $key)) {
                $result = $descriptive_adapter->process(array($key => $value), $survey_id);
                if (isset($result['questions'][$key])) {
                    $question_data = $result['questions'][$key];
                }
            }
            
            // Fallback
            if (!$question_data) {
                $question_data = array(
                    'original_key' => $key,
                    'display_name' => $this->clean_field_key($key),
                    'category' => 'other',
                    'type' => 'text',
                    'original_value' => $value,
                    'formatted_value' => $this->format_value($value),
                    'confidence' => 0.3
                );
            }
            
            $processed['questions'][$key] = $question_data;
        }
        
        return $processed;
    }
    
    private function should_skip_field($key, $value) {
        if ($value === null || $value === '' || $value === ' ') {
            return true;
        }
        
        $metadata_fields = ['id', 'submitdate', 'lastpage', 'startlanguage', 'seed', 'startdate', 'datestamp'];
        return in_array($key, $metadata_fields);
    }
}