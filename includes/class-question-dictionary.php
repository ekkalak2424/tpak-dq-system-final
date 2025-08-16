<?php
/**
 * TPAK DQ System - Question Dictionary
 * 
 * Centralized mapping of question codes to Thai text
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Question_Dictionary {
    
    private static $instance = null;
    
    // Common question patterns and their Thai translations
    private $question_patterns = array(
        // Consent patterns
        'CONSENT' => 'การยินยอมเข้าร่วมการวิจัย',
        'CONSENT_FORM' => 'แบบฟอร์มการยินยอม',
        
        // Survey sections
        'PA1' => 'ส่วนที่ 1: ข้อมูลทั่วไป',
        'PA2' => 'ส่วนที่ 2: ข้อมูลการศึกษา',
        'PA3' => 'ส่วนที่ 3: ข้อมูลการทำงาน',
        'PA4' => 'ส่วนที่ 4: ความคิดเห็น',
        'PA5' => 'ส่วนที่ 5: ข้อเสนอแนะ',
        
        // Personal information
        'NAME' => 'ชื่อ-นามสกุล',
        'FIRSTNAME' => 'ชื่อ',
        'LASTNAME' => 'นามสกุล',
        'AGE' => 'อายุ',
        'GENDER' => 'เพศ',
        'BIRTHDATE' => 'วันเกิด',
        'ID_CARD' => 'เลขบัตรประชาชน',
        'NATIONALITY' => 'สัญชาติ',
        'RELIGION' => 'ศาสนา',
        'MARITAL' => 'สถานภาพสมรส',
        
        // Contact information
        'ADDRESS' => 'ที่อยู่',
        'PROVINCE' => 'จังหวัด',
        'DISTRICT' => 'อำเภอ/เขต',
        'SUBDISTRICT' => 'ตำบล/แขวง',
        'POSTAL' => 'รหัสไปรษณีย์',
        'PHONE' => 'เบอร์โทรศัพท์',
        'EMAIL' => 'อีเมล',
        
        // Education
        'EDUCATION' => 'ระดับการศึกษา',
        'SCHOOL' => 'สถาบันการศึกษา',
        'DEGREE' => 'วุฒิการศึกษา',
        'MAJOR' => 'สาขาวิชา',
        'GPA' => 'เกรดเฉลี่ย',
        
        // Work
        'OCCUPATION' => 'อาชีพ',
        'POSITION' => 'ตำแหน่ง',
        'COMPANY' => 'บริษัท/หน่วยงาน',
        'INCOME' => 'รายได้',
        'EXPERIENCE' => 'ประสบการณ์ทำงาน',
        
        // Complex patterns
        'PA1TT2' => 'ส่วนที่ 1: ข้อมูลพื้นฐาน',
        'PA2TT1' => 'ส่วนที่ 2: ข้อมูลการประเมิน',
        'PA3TT1' => 'ส่วนที่ 3: ข้อมูลผลลัพธ์',
    );
    
    // Answer options mapping
    private $answer_options = array(
        // Yes/No
        'Y' => 'ใช่',
        'N' => 'ไม่ใช่',
        'YES' => 'ใช่',
        'NO' => 'ไม่',
        '1' => array(
            'default' => 'ใช่',
            'scale' => 'น้อยที่สุด',
            'order' => 'ลำดับที่ 1'
        ),
        '0' => 'ไม่',
        
        // Gender
        'M' => 'ชาย',
        'F' => 'หญิง',
        'MALE' => 'ชาย',
        'FEMALE' => 'หญิง',
        'OTHER' => 'อื่นๆ',
        
        // Satisfaction scale
        'VERY_SATISFIED' => 'พอใจมากที่สุด',
        'SATISFIED' => 'พอใจ',
        'NEUTRAL' => 'ปานกลาง',
        'DISSATISFIED' => 'ไม่พอใจ',
        'VERY_DISSATISFIED' => 'ไม่พอใจมากที่สุด',
        
        // Agreement scale
        'STRONGLY_AGREE' => 'เห็นด้วยอย่างยิ่ง',
        'AGREE' => 'เห็นด้วย',
        'DISAGREE' => 'ไม่เห็นด้วย',
        'STRONGLY_DISAGREE' => 'ไม่เห็นด้วยอย่างยิ่ง',
        
        // Frequency
        'ALWAYS' => 'เสมอ',
        'OFTEN' => 'บ่อยครั้ง',
        'SOMETIMES' => 'บางครั้ง',
        'RARELY' => 'นานๆ ครั้ง',
        'NEVER' => 'ไม่เคย',
        
        // Education levels
        'PRIMARY' => 'ประถมศึกษา',
        'SECONDARY' => 'มัธยมศึกษา',
        'VOCATIONAL' => 'ปวช./ปวส.',
        'BACHELOR' => 'ปริญญาตรี',
        'MASTER' => 'ปริญญาโท',
        'DOCTORAL' => 'ปริญญาเอก',
        
        // Marital status
        'SINGLE' => 'โสด',
        'MARRIED' => 'สมรส',
        'DIVORCED' => 'หย่าร้าง',
        'WIDOWED' => 'หม้าย',
        'SEPARATED' => 'แยกกันอยู่'
    );
    
    // Sub-question patterns
    private $sub_question_patterns = array(
        '[1]' => 'ข้อที่ 1',
        '[2]' => 'ข้อที่ 2',
        '[3]' => 'ข้อที่ 3',
        '[4]' => 'ข้อที่ 4',
        '[5]' => 'ข้อที่ 5',
        '_SQ001' => 'ข้อย่อยที่ 1',
        '_SQ002' => 'ข้อย่อยที่ 2',
        '_SQ003' => 'ข้อย่อยที่ 3',
        'A1' => 'ตอนที่ 1',
        'A2' => 'ตอนที่ 2',
        'B1' => 'ส่วนที่ 1',
        'B2' => 'ส่วนที่ 2'
    );
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get question text from code
     */
    public function getQuestionText($code) {
        // Direct match
        if (isset($this->question_patterns[$code])) {
            return $this->question_patterns[$code];
        }
        
        // Try uppercase
        $upper_code = strtoupper($code);
        if (isset($this->question_patterns[$upper_code])) {
            return $this->question_patterns[$upper_code];
        }
        
        // Pattern matching
        foreach ($this->question_patterns as $pattern => $text) {
            if (strpos($code, $pattern) !== false) {
                return $text;
            }
        }
        
        // Parse complex codes
        return $this->parseComplexCode($code);
    }
    
    /**
     * Get answer text from value
     */
    public function getAnswerText($value, $context = null) {
        // Direct match
        $upper_value = strtoupper($value);
        
        if (isset($this->answer_options[$upper_value])) {
            $option = $this->answer_options[$upper_value];
            
            if (is_array($option)) {
                // Context-based answer
                if ($context && isset($option[$context])) {
                    return $option[$context];
                }
                return $option['default'] ?? $value;
            }
            
            return $option;
        }
        
        // Numeric scales
        if (is_numeric($value)) {
            $num = intval($value);
            
            // 1-5 scale
            if ($num >= 1 && $num <= 5) {
                $scale = array(
                    1 => 'น้อยที่สุด',
                    2 => 'น้อย',
                    3 => 'ปานกลาง',
                    4 => 'มาก',
                    5 => 'มากที่สุด'
                );
                
                if (isset($scale[$num])) {
                    return $scale[$num] . ' (' . $num . ')';
                }
            }
            
            // 1-10 scale
            if ($num >= 1 && $num <= 10) {
                return 'ระดับ ' . $num . ' จาก 10';
            }
        }
        
        return $value;
    }
    
    /**
     * Parse complex question codes
     */
    private function parseComplexCode($code) {
        // Handle Q1, Q2, etc.
        if (preg_match('/^Q(\d+)(.*)/', $code, $matches)) {
            $text = 'คำถามที่ ' . $matches[1];
            
            if (!empty($matches[2])) {
                // Check for sub-question patterns
                foreach ($this->sub_question_patterns as $pattern => $label) {
                    if (strpos($matches[2], $pattern) !== false) {
                        $text .= ' ' . $label;
                        break;
                    }
                }
            }
            
            return $text;
        }
        
        // Handle PA1TT2[1] format
        if (preg_match('/^([A-Z]+)(\d+)([A-Z]+)(\d+)(.*)/', $code, $matches)) {
            $section = $matches[1] . $matches[2];
            $subsection = $matches[3] . $matches[4];
            
            $text = 'ส่วน ' . $section . ' หมวด ' . $subsection;
            
            if (!empty($matches[5])) {
                // Extract index
                if (preg_match('/\[(\d+)\]/', $matches[5], $index_match)) {
                    $text .= ' ข้อที่ ' . $index_match[1];
                }
            }
            
            return $text;
        }
        
        // Default: clean up the code
        $clean = str_replace(['_', '-', '[', ']'], ' ', $code);
        return trim($clean);
    }
    
    /**
     * Get all mappings for debugging
     */
    public function getAllMappings() {
        return array(
            'questions' => $this->question_patterns,
            'answers' => $this->answer_options,
            'sub_questions' => $this->sub_question_patterns
        );
    }
    
    /**
     * Add custom mapping
     */
    public function addMapping($type, $code, $text) {
        switch ($type) {
            case 'question':
                $this->question_patterns[$code] = $text;
                break;
            case 'answer':
                $this->answer_options[$code] = $text;
                break;
            case 'sub_question':
                $this->sub_question_patterns[$code] = $text;
                break;
        }
    }
    
    /**
     * Load custom mappings from database
     */
    public function loadCustomMappings($survey_id = null) {
        // This could load survey-specific mappings from database
        $custom_mappings = get_option('tpak_question_mappings_' . $survey_id, array());
        
        if (!empty($custom_mappings) && is_array($custom_mappings)) {
            foreach ($custom_mappings as $type => $mappings) {
                // Ensure mappings is an array before iterating
                if (is_array($mappings)) {
                    foreach ($mappings as $code => $text) {
                        $this->addMapping($type, $code, $text);
                    }
                }
            }
        }
    }
}