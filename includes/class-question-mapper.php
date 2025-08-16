<?php
/**
 * TPAK DQ System - Advanced Question Mapping System
 * 
 * This class provides intelligent mapping of survey field keys to human-readable
 * questions and answers, with support for multiple survey structures.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Advanced_Question_Mapper {
    
    private static $instance = null;
    private $survey_cache = [];
    private $mapping_cache = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get comprehensive question mapping for a survey response
     */
    public function getResponseMapping($response_data, $survey_id = null) {
        $mapping = [
            'questions' => [],
            'structure' => $this->analyzeSurveyStructure($response_data),
            'categories' => [],
            'statistics' => []
        ];
        
        foreach ($response_data as $field_key => $value) {
            if ($this->shouldSkipField($field_key, $value)) {
                continue;
            }
            
            $question_info = $this->getQuestionInfo($field_key, $survey_id);
            $answer_info = $this->getAnswerInfo($field_key, $value);
            
            $mapping['questions'][$field_key] = [
                'original_key' => $field_key,
                'display_name' => $question_info['display_name'],
                'category' => $question_info['category'],
                'type' => $question_info['type'],
                'original_value' => $value,
                'formatted_value' => $answer_info['formatted_value'],
                'display_type' => $answer_info['display_type'],
                'confidence' => $question_info['confidence']
            ];
            
            // Count categories
            $category = $question_info['category'];
            if (!isset($mapping['categories'][$category])) {
                $mapping['categories'][$category] = 0;
            }
            $mapping['categories'][$category]++;
        }
        
        $mapping['statistics'] = $this->calculateStatistics($mapping['questions']);
        
        return $mapping;
    }
    
    /**
     * Analyze survey structure to determine the best mapping approach
     */
    private function analyzeSurveyStructure($response_data) {
        $structure = [
            'type' => 'unknown',
            'patterns' => [],
            'complexity' => 'simple',
            'field_count' => count($response_data),
            'naming_convention' => 'mixed'
        ];
        
        $patterns = [
            'limesurvey' => 0,
            'numeric' => 0,
            'descriptive' => 0,
            'mixed' => 0
        ];
        
        foreach (array_keys($response_data) as $key) {
            // LimeSurvey pattern (Q1, Q1A1, etc.)
            if (preg_match('/^Q\d+([A-Z]\d*)?/', $key)) {
                $patterns['limesurvey']++;
            }
            // Pure numeric (1, 2, 3, etc.)
            elseif (preg_match('/^\d+[a-z]*$/', $key)) {
                $patterns['numeric']++;
            }
            // Descriptive (name, age, address, etc.)
            elseif (preg_match('/^[a-zA-Z_]+\d*$/', $key)) {
                $patterns['descriptive']++;
            }
            else {
                $patterns['mixed']++;
            }
        }
        
        // Determine dominant pattern
        $max_pattern = array_keys($patterns, max($patterns))[0];
        $structure['type'] = $max_pattern;
        $structure['patterns'] = $patterns;
        
        // Determine complexity
        $pattern_count = count(array_filter($patterns, function($count) { return $count > 0; }));
        if ($pattern_count > 2 || $structure['field_count'] > 50) {
            $structure['complexity'] = 'complex';
        } elseif ($pattern_count > 1 || $structure['field_count'] > 20) {
            $structure['complexity'] = 'moderate';
        }
        
        return $structure;
    }
    
    /**
     * Get question information including display name, category, and type
     */
    private function getQuestionInfo($field_key, $survey_id = null) {
        // Check cache first
        $cache_key = $survey_id . '_' . $field_key;
        if (isset($this->mapping_cache[$cache_key])) {
            return $this->mapping_cache[$cache_key];
        }
        
        $info = [
            'display_name' => $field_key,
            'category' => 'other',
            'type' => 'text',
            'confidence' => 0.5 // How confident we are in this mapping (0-1)
        ];
        
        // Try database lookup first (if available)
        if ($survey_id) {
            $db_info = $this->getQuestionFromDatabase($field_key, $survey_id);
            if ($db_info) {
                $info = array_merge($info, $db_info);
                $info['confidence'] = 0.9;
                $this->mapping_cache[$cache_key] = $info;
                return $info;
            }
        }
        
        // Use pattern matching
        $pattern_info = $this->getQuestionFromPatterns($field_key);
        $info = array_merge($info, $pattern_info);
        
        $this->mapping_cache[$cache_key] = $info;
        return $info;
    }
    
    /**
     * Get answer information including formatted value and display type
     */
    private function getAnswerInfo($field_key, $value) {
        $info = [
            'formatted_value' => $value,
            'display_type' => 'text'
        ];
        
        if (empty($value) && $value !== '0') {
            $info['formatted_value'] = '<em class="empty-value">ไม่ได้ระบุ</em>';
            $info['display_type'] = 'empty';
            return $info;
        }
        
        // Detect answer type from field key
        $answer_type = $this->detectAnswerType($field_key);
        
        // Apply value mappings
        $mapped_value = $this->applyValueMapping($value, $answer_type);
        if ($mapped_value !== $value) {
            $info['formatted_value'] = $mapped_value;
            $info['display_type'] = $answer_type;
        } else {
            // Apply formatting
            $info['formatted_value'] = $this->formatValue($value, $answer_type);
            $info['display_type'] = $answer_type;
        }
        
        return $info;
    }
    
    /**
     * Pattern-based question mapping
     */
    private function getQuestionFromPatterns($field_key) {
        $patterns = [
            // Personal Information
            '/^(name|firstname|first_name)$/i' => ['display_name' => 'ชื่อจริง', 'category' => 'personal', 'type' => 'text'],
            '/^(lastname|last_name|surname)$/i' => ['display_name' => 'นามสกุล', 'category' => 'personal', 'type' => 'text'],
            '/^(fullname|full_name)$/i' => ['display_name' => 'ชื่อ-นามสกุล', 'category' => 'personal', 'type' => 'text'],
            '/^(age|อายุ)$/i' => ['display_name' => 'อายุ', 'category' => 'personal', 'type' => 'number'],
            '/^(birth|birthday|birthdate|วันเกิด)$/i' => ['display_name' => 'วันเกิด', 'category' => 'personal', 'type' => 'date'],
            '/^(gender|sex|เพศ)$/i' => ['display_name' => 'เพศ', 'category' => 'personal', 'type' => 'gender'],
            '/^(id|id_card|citizen_id)$/i' => ['display_name' => 'เลขบัตรประชาชน', 'category' => 'personal', 'type' => 'text'],
            
            // Contact Information
            '/^(phone|tel|telephone|mobile)$/i' => ['display_name' => 'เบอร์โทรศัพท์', 'category' => 'contact', 'type' => 'phone'],
            '/^(email|e_mail)$/i' => ['display_name' => 'อีเมล', 'category' => 'contact', 'type' => 'email'],
            '/^(address|ที่อยู่)$/i' => ['display_name' => 'ที่อยู่', 'category' => 'contact', 'type' => 'textarea'],
            '/^(province|จังหวัด)$/i' => ['display_name' => 'จังหวัด', 'category' => 'contact', 'type' => 'text'],
            '/^(district|อำเภอ)$/i' => ['display_name' => 'อำเภอ/เขต', 'category' => 'contact', 'type' => 'text'],
            
            // Education
            '/^(education|การศึกษา)$/i' => ['display_name' => 'ระดับการศึกษา', 'category' => 'education', 'type' => 'education'],
            '/^(school|โรงเรียน)$/i' => ['display_name' => 'โรงเรียน', 'category' => 'education', 'type' => 'text'],
            '/^(university|มหาวิทยาลัย)$/i' => ['display_name' => 'มหาวิทยาลัย', 'category' => 'education', 'type' => 'text'],
            
            // Work
            '/^(job|work|occupation|อาชีพ)$/i' => ['display_name' => 'อาชีพ', 'category' => 'work', 'type' => 'text'],
            '/^(income|salary|เงินเดือน)$/i' => ['display_name' => 'รายได้', 'category' => 'work', 'type' => 'number'],
            
            // Survey patterns
            '/^Q(\d+)$/i' => ['display_name' => 'คำถามที่ $1', 'category' => 'survey', 'type' => 'text'],
            '/^Q(\d+)([A-Z])(\d*)$/i' => ['display_name' => 'คำถามที่ $1 ข้อย่อย $2$3', 'category' => 'survey', 'type' => 'text'],
        ];
        
        foreach ($patterns as $pattern => $mapping) {
            if (preg_match($pattern, $field_key)) {
                $mapping['display_name'] = preg_replace($pattern, $mapping['display_name'], $field_key);
                $mapping['confidence'] = 0.8;
                return $mapping;
            }
        }
        
        // Fallback: clean up field key
        return [
            'display_name' => $this->cleanFieldKey($field_key),
            'category' => $this->guessCategory($field_key),
            'type' => 'text',
            'confidence' => 0.3
        ];
    }
    
    /**
     * Detect answer type from field key
     */
    private function detectAnswerType($field_key) {
        $field_lower = strtolower($field_key);
        
        $type_patterns = [
            'gender' => ['gender', 'sex', 'เพศ'],
            'yesno' => ['yes', 'no', 'agree', 'disagree', 'ใช่', 'ไม่'],
            'education' => ['education', 'การศึกษา', 'degree'],
            'marital' => ['marital', 'สถานภาพ', 'married'],
            'rating' => ['rating', 'score', 'คะแนน', 'rate'],
            'date' => ['date', 'birth', 'วันที่', 'วันเกิด'],
            'number' => ['age', 'income', 'salary', 'อายุ', 'เงิน', 'จำนวน'],
            'phone' => ['phone', 'tel', 'mobile', 'โทร'],
            'email' => ['email', 'mail', 'อีเมล']
        ];
        
        foreach ($type_patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($field_lower, $keyword) !== false) {
                    return $type;
                }
            }
        }
        
        return 'text';
    }
    
    /**
     * Apply value mappings for common answer types
     */
    private function applyValueMapping($value, $type) {
        $mappings = [
            'gender' => [
                'M' => 'ชาย', 'Male' => 'ชาย', '1' => 'ชาย', 'male' => 'ชาย',
                'F' => 'หญิง', 'Female' => 'หญิง', '2' => 'หญิง', 'female' => 'หญิง',
                'O' => 'อื่นๆ', 'Other' => 'อื่นๆ', '3' => 'อื่นๆ'
            ],
            'yesno' => [
                'Y' => 'ใช่', 'Yes' => 'ใช่', '1' => 'ใช่', 'yes' => 'ใช่', 'true' => 'ใช่',
                'N' => 'ไม่ใช่', 'No' => 'ไม่ใช่', '0' => 'ไม่ใช่', 'no' => 'ไม่ใช่', 'false' => 'ไม่ใช่'
            ],
            'education' => [
                '1' => 'ประถมศึกษา',
                '2' => 'มัธยมศึกษาตอนต้น',
                '3' => 'มัธยมศึกษาตอนปลาย',
                '4' => 'ปวช./ปวส.',
                '5' => 'ปริญญาตรี',
                '6' => 'ปริญญาโท',
                '7' => 'ปริญญาเอก'
            ],
            'marital' => [
                '1' => 'โสด', 'single' => 'โสด',
                '2' => 'สมรส', 'married' => 'สมรส',
                '3' => 'หย่าร้าง', 'divorced' => 'หย่าร้าง',
                '4' => 'หม้าย', 'widowed' => 'หม้าย'
            ],
            'rating' => [
                '1' => '1 - น้อยที่สุด',
                '2' => '2 - น้อย',
                '3' => '3 - ปานกลาง',
                '4' => '4 - มาก',
                '5' => '5 - มากที่สุด'
            ]
        ];
        
        if (isset($mappings[$type][$value])) {
            return $mappings[$type][$value];
        }
        
        return $value;
    }
    
    /**
     * Format values based on type
     */
    private function formatValue($value, $type) {
        switch ($type) {
            case 'date':
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                    return date('j F Y', strtotime($value));
                }
                return $value;
                
            case 'number':
                if (is_numeric($value)) {
                    return number_format($value);
                }
                return $value;
                
            case 'phone':
                // Format Thai phone numbers
                if (preg_match('/^0\d{9}$/', $value)) {
                    return preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1-$2-$3', $value);
                }
                return $value;
                
            case 'email':
                return '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                
            case 'textarea':
                return nl2br(esc_html($value));
                
            default:
                if (strlen($value) > 100) {
                    return nl2br(esc_html($value));
                }
                return esc_html($value);
        }
    }
    
    /**
     * Clean field key for display
     */
    private function cleanFieldKey($field_key) {
        $clean = preg_replace('/^(Q|question|ans|answer)_?/i', '', $field_key);
        $clean = str_replace(['_', '-'], ' ', $clean);
        return ucwords(strtolower($clean)) ?: $field_key;
    }
    
    /**
     * Guess category from field key
     */
    private function guessCategory($field_key) {
        $field_lower = strtolower($field_key);
        
        $categories = [
            'personal' => ['name', 'age', 'birth', 'gender', 'id', 'nationality', 'religion'],
            'contact' => ['phone', 'email', 'address', 'province', 'district', 'postal'],
            'education' => ['school', 'university', 'degree', 'grade', 'education'],
            'work' => ['job', 'work', 'occupation', 'company', 'income', 'salary'],
            'survey' => ['Q', 'question', 'answer', 'opinion', 'rating']
        ];
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($field_lower, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'other';
    }
    
    /**
     * Check if field should be skipped
     */
    private function shouldSkipField($field_key, $value) {
        // Skip empty values
        if ($value === null || $value === '' || $value === ' ') {
            return true;
        }
        
        // Skip metadata fields
        $metadata_fields = [
            'id', 'submitdate', 'lastpage', 'startlanguage', 'seed', 
            'startdate', 'datestamp', 'ipaddr', 'refurl', 'token'
        ];
        
        return in_array($field_key, $metadata_fields);
    }
    
    /**
     * Calculate statistics for the response
     */
    private function calculateStatistics($questions) {
        $stats = [
            'total_questions' => count($questions),
            'answered_questions' => 0,
            'completion_rate' => 0,
            'categories' => [],
            'confidence_average' => 0
        ];
        
        $total_confidence = 0;
        
        foreach ($questions as $question) {
            if (!empty($question['original_value'])) {
                $stats['answered_questions']++;
            }
            
            $category = $question['category'];
            if (!isset($stats['categories'][$category])) {
                $stats['categories'][$category] = 0;
            }
            $stats['categories'][$category]++;
            
            $total_confidence += $question['confidence'];
        }
        
        if ($stats['total_questions'] > 0) {
            $stats['completion_rate'] = round(($stats['answered_questions'] / $stats['total_questions']) * 100);
            $stats['confidence_average'] = round($total_confidence / $stats['total_questions'], 2);
        }
        
        return $stats;
    }
    
    /**
     * Get question from database, LSS structure, or API
     */
    private function getQuestionFromDatabase($field_key, $survey_id) {
        if (!$survey_id) {
            return null;
        }
        
        // First, try to get from LSS structure (highest priority)
        $lss_data = $this->getQuestionFromLSS($field_key, $survey_id);
        if ($lss_data) {
            return $lss_data;
        }
        
        // Second, try Question Dictionary mappings
        $dict_data = $this->getQuestionFromDictionary($field_key, $survey_id);
        if ($dict_data) {
            return $dict_data;
        }
        
        // Finally, try to get survey structure from API handler
        $api_handler = new TPAK_DQ_API_Handler();
        $survey_structure = $api_handler->get_survey_structure($survey_id);
        
        if ($survey_structure && isset($survey_structure['questions'][$field_key])) {
            $question_data = $survey_structure['questions'][$field_key];
            
            return [
                'display_name' => $question_data['question'],
                'category' => $this->guessCategory($question_data['question']),
                'type' => $this->mapLimesurveyType($question_data['type']),
                'help' => $question_data['help'] ?? '',
                'mandatory' => ($question_data['mandatory'] === 'Y')
            ];
        }
        
        return null;
    }
    
    /**
     * Get question from LSS imported data
     */
    private function getQuestionFromLSS($field_key, $survey_id) {
        // Check if LSS structure exists for this survey
        $lss_structure = get_option('tpak_lss_structure_' . $survey_id, false);
        if (!$lss_structure) {
            return null;
        }
        
        // Try to find exact match by field key/title
        if (isset($lss_structure['questions'][$field_key])) {
            $question_data = $lss_structure['questions'][$field_key];
            
            // Get question text from question_texts
            $question_text = $field_key;
            if (isset($lss_structure['question_texts'][$question_data['qid']]['question'])) {
                $question_text = $lss_structure['question_texts'][$question_data['qid']]['question'];
            } elseif (isset($question_data['question'])) {
                $question_text = $question_data['question'];
            }
            
            return [
                'display_name' => $question_text,
                'category' => $this->guessCategory($question_text),
                'type' => $this->mapLimesurveyType($question_data['type'] ?? 'text'),
                'help' => '',
                'mandatory' => ($question_data['mandatory'] ?? 'N') === 'Y'
            ];
        }
        
        // Try to find by QID if field_key looks like a question ID
        foreach ($lss_structure['questions'] as $title => $question_data) {
            if ($question_data['qid'] == $field_key || $title == $field_key) {
                // Get question text
                $question_text = $title;
                if (isset($lss_structure['question_texts'][$question_data['qid']]['question'])) {
                    $question_text = $lss_structure['question_texts'][$question_data['qid']]['question'];
                }
                
                return [
                    'display_name' => $question_text,
                    'category' => $this->guessCategory($question_text),
                    'type' => $this->mapLimesurveyType($question_data['type'] ?? 'text'),
                    'help' => '',
                    'mandatory' => ($question_data['mandatory'] ?? 'N') === 'Y'
                ];
            }
        }
        
        // Try pattern matching for complex field keys like PA1TT2[1]
        return $this->getQuestionFromLSSPattern($field_key, $lss_structure);
    }
    
    /**
     * Get question from LSS using pattern matching
     */
    private function getQuestionFromLSSPattern($field_key, $lss_structure) {
        // Parse complex field keys like PA1TT2[1]
        $main_key = $field_key;
        $sub_key = null;
        
        // Pattern 1: Field[index] format
        if (preg_match('/^([^\[]+)\[(\d+)\]$/', $field_key, $matches)) {
            $main_key = $matches[1];
            $sub_key = $matches[2];
        }
        // Pattern 2: Field_SubField format
        elseif (preg_match('/^([^_]+)_(.+)$/', $field_key, $matches)) {
            $main_key = $matches[1];
            $sub_key = $matches[2];
        }
        
        // Search for main key in LSS structure
        foreach ($lss_structure['questions'] as $title => $question_data) {
            if ($title == $main_key || strpos($title, $main_key) !== false) {
                // Get question text
                $question_text = $title;
                if (isset($lss_structure['question_texts'][$question_data['qid']]['question'])) {
                    $question_text = $lss_structure['question_texts'][$question_data['qid']]['question'];
                }
                
                // Add sub-question information if available
                if ($sub_key) {
                    $question_text .= ' [' . $sub_key . ']';
                }
                
                return [
                    'display_name' => $question_text,
                    'category' => $this->guessCategory($question_text),
                    'type' => $this->mapLimesurveyType($question_data['type'] ?? 'text'),
                    'help' => '',
                    'mandatory' => ($question_data['mandatory'] ?? 'N') === 'Y'
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Get question from Question Dictionary mappings
     */
    private function getQuestionFromDictionary($field_key, $survey_id) {
        // Check if dictionary mappings exist for this survey
        $mappings = get_option('tpak_question_mappings_' . $survey_id, false);
        if (!$mappings || !isset($mappings['questions'])) {
            return null;
        }
        
        // Try exact match
        if (isset($mappings['questions'][$field_key])) {
            return [
                'display_name' => $mappings['questions'][$field_key],
                'category' => $this->guessCategory($mappings['questions'][$field_key]),
                'type' => 'text',
                'help' => '',
                'mandatory' => false
            ];
        }
        
        // Try pattern matching
        foreach ($mappings['questions'] as $pattern => $text) {
            if (strpos($field_key, $pattern) !== false) {
                return [
                    'display_name' => $text,
                    'category' => $this->guessCategory($text),
                    'type' => 'text',
                    'help' => '',
                    'mandatory' => false
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Map LimeSurvey question types to our internal types
     */
    private function mapLimesurveyType($lime_type) {
        $type_mapping = [
            'T' => 'textarea',      // Long free text
            'S' => 'text',          // Short free text
            'N' => 'number',        // Numerical input
            'D' => 'date',          // Date
            'G' => 'gender',        // Gender
            'Y' => 'yesno',         // Yes/No
            'L' => 'text',          // List (Radio)
            'O' => 'text',          // List with comment
            'M' => 'text',          // Multiple choice
            'P' => 'text',          // Multiple choice with comments
            'A' => 'text',          // Array
            'B' => 'text',          // Array (10 point choice)
            'C' => 'text',          // Array (Yes/No/Uncertain)
            'E' => 'text',          // Array (Increase/Same/Decrease)
            'F' => 'text',          // Array (Flexible Labels)
            'H' => 'text',          // Array (Flexible Labels) by Column
            'Q' => 'text',          // Multiple Short Text
            'K' => 'text',          // Multiple Numerical Input
            'R' => 'rating',        // Ranking
            'I' => 'text',          // Language Switch
            'X' => 'text',          // Boilerplate question
            '1' => 'text',          // Array dual scale
            ':' => 'text',          // Array (Numbers)
            ';' => 'text',          // Array (Texts)
            '|' => 'text',          // File upload
            '*' => 'text'           // Equation
        ];
        
        return $type_mapping[$lime_type] ?? 'text';
    }
}