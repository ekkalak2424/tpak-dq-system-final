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
    
    public function process($response_data, $survey_id = null) {
        $processed = array(
            'questions' => array(),
            'structure_type' => 'limesurvey',
            'confidence' => 0.9
        );
        
        // Get survey structure for better mapping
        $api_handler = new TPAK_DQ_API_Handler();
        $survey_structure = $api_handler->get_survey_structure($survey_id);
        $question_map = array();
        
        if ($survey_structure && isset($survey_structure['questions'])) {
            $question_map = $survey_structure['questions'];
        }
        
        foreach ($response_data as $key => $value) {
            if ($this->should_skip_field($key, $value)) {
                continue;
            }
            
            // Use survey structure if available
            if (isset($question_map[$key])) {
                $question_text = strip_tags($question_map[$key]['question']);
                $type = $question_map[$key]['type'] ?? 'text';
            } else {
                // Fallback pattern matching
                if (preg_match('/^Q(\d+)([A-Z]*\d*)(.*)/', $key, $matches)) {
                    $question_text = 'คำถามที่ ' . $matches[1];
                    if (!empty($matches[2])) {
                        $question_text .= ' ข้อย่อย ' . $matches[2];
                    }
                    if (!empty($matches[3])) {
                        $question_text .= ' ' . $this->clean_field_key($matches[3]);
                    }
                } else {
                    $question_text = $this->clean_field_key($key);
                }
                $type = 'text';
            }
            
            $processed['questions'][$key] = array(
                'original_key' => $key,
                'display_name' => $question_text,
                'category' => $this->guess_category($key, $question_text),
                'type' => $type,
                'original_value' => $value,
                'formatted_value' => $this->format_value($value, $type),
                'confidence' => isset($question_map[$key]) ? 0.95 : 0.7
            );
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