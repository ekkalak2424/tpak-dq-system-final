<?php
/**
 * TPAK DQ System - Single Response Detail View
 * แสดงรายละเอียดของ Response เดี่ยวแบบคำถาม + คำถามย่อย + คำตอบ
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enqueue necessary scripts and styles
wp_enqueue_script('jquery');

// Enqueue main admin script
wp_enqueue_script(
    'tpak-dq-admin-script',
    TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/admin-script.js',
    array('jquery'),
    TPAK_DQ_SYSTEM_VERSION,
    true
);

// Enqueue response detail specific script
wp_enqueue_script(
    'tpak-dq-response-detail',
    TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/response-detail.js',
    array('jquery', 'tpak-dq-admin-script'),
    TPAK_DQ_SYSTEM_VERSION,
    true
);

wp_enqueue_style('tpak-dq-admin', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/css/admin-style.css', array(), TPAK_DQ_SYSTEM_VERSION);

// Localize script with AJAX data
wp_localize_script('tpak-dq-response-detail', 'tpak_dq_ajax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('tpak_workflow_nonce')
));

// Also add as global variables for backward compatibility
wp_add_inline_script('tpak-dq-response-detail', '
    window.ajaxurl = "' . admin_url('admin-ajax.php') . '";
', 'before');

// Check user permissions first
if (!current_user_can('edit_posts')) {
    wp_die(__('Sorry, you are not allowed to access this page.', 'tpak-dq-system'));
}

// Get response ID from URL
$response_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

if (!$response_id) {
    wp_die(__('ไม่พบข้อมูลที่ร้องขอ', 'tpak-dq-system'));
}

// Get post data
$post = get_post($response_id);
if (!$post || $post->post_type !== 'verification_batch') {
    wp_die(__('ไม่พบข้อมูลแบบสอบถาม', 'tpak-dq-system'));
}

// Get response data and metadata
$survey_data_json = get_post_meta($response_id, '_survey_data', true);
$response_data = json_decode($survey_data_json, true);
$lime_response_id = get_post_meta($response_id, '_lime_response_id', true);
$lime_survey_id = get_post_meta($response_id, '_lime_survey_id', true);
$import_date = get_post_meta($response_id, '_import_date', true);

// Get workflow data
$workflow = new TPAK_DQ_Workflow();
$status = $workflow->get_batch_status($response_id);
$audit_trail = $workflow->get_audit_trail($response_id);

// Advanced Question Mapping System
class TPAK_Question_Mapper {
    
    private static $common_patterns = [
        // Personal Information
        '/^(name|firstname|first_name)$/i' => 'ชื่อจริง',
        '/^(lastname|last_name|surname)$/i' => 'นามสกุล',
        '/^(fullname|full_name)$/i' => 'ชื่อ-นามสกุล',
        '/^(nickname|nick_name)$/i' => 'ชื่อเล่น',
        '/^(age|อายุ)$/i' => 'อายุ',
        '/^(birth|birthday|birthdate|birth_date|วันเกิด)$/i' => 'วันเกิด',
        '/^(gender|sex|เพศ)$/i' => 'เพศ',
        '/^(id|id_card|citizen_id|บัตรประชาชน)$/i' => 'เลขบัตรประชาชน',
        '/^(nationality|สัญชาติ)$/i' => 'สัญชาติ',
        '/^(religion|ศาสนา)$/i' => 'ศาสนา',
        '/^(marital|marital_status|สถานภาพ)$/i' => 'สถานภาพสมรส',
        
        // Contact Information  
        '/^(phone|tel|telephone|mobile|โทรศัพท์)$/i' => 'เบอร์โทรศัพท์',
        '/^(email|e_mail|อีเมล)$/i' => 'อีเมล',
        '/^(address|ที่อยู่)$/i' => 'ที่อยู่',
        '/^(province|จังหวัด)$/i' => 'จังหวัด',
        '/^(district|อำเภอ)$/i' => 'อำเภอ/เขต',
        '/^(subdistrict|tambon|ตำบล)$/i' => 'ตำบล/แขวง',
        '/^(postal|postcode|zip|รหัสไปรษณีย์)$/i' => 'รหัสไปรษณีย์',
        
        // Education
        '/^(education|การศึกษา)$/i' => 'ระดับการศึกษา',
        '/^(school|โรงเรียน)$/i' => 'โรงเรียน',
        '/^(university|มหาวิทยาลัย)$/i' => 'มหาวิทยาลัย',
        '/^(degree|ปริญญา)$/i' => 'ระดับปริญญา',
        '/^(major|สาขา)$/i' => 'สาขาวิชา',
        '/^(gpa|เกรด)$/i' => 'เกรดเฉลี่ย',
        
        // Work
        '/^(job|work|occupation|อาชีพ)$/i' => 'อาชีพ',
        '/^(company|บริษัท)$/i' => 'บริษัท/หน่วยงาน',
        '/^(position|ตำแหน่ง)$/i' => 'ตำแหน่งงาน',
        '/^(income|salary|เงินเดือน|รายได้)$/i' => 'รายได้',
        '/^(experience|ประสบการณ์)$/i' => 'ประสบการณ์การทำงาน',
        
        // Survey specific patterns
        '/^Q(\d+)$/i' => 'คำถามที่ $1',
        '/^Q(\d+)([A-Z])(\d*)$/i' => 'คำถามที่ $1 ข้อย่อย $2$3',
        '/^(\d+)([a-z]+)?$/i' => 'คำถามที่ $1$2',
    ];
    
    private static $value_mappings = [
        // Gender mappings
        'gender' => [
            'M' => 'ชาย', 'Male' => 'ชาย', '1' => 'ชาย', 'male' => 'ชาย',
            'F' => 'หญิง', 'Female' => 'หญิง', '2' => 'หญิง', 'female' => 'หญิง',
            'O' => 'อื่นๆ', 'Other' => 'อื่นๆ', '3' => 'อื่นๆ', 'other' => 'อื่นๆ'
        ],
        
        // Yes/No mappings
        'yesno' => [
            'Y' => 'ใช่', 'Yes' => 'ใช่', '1' => 'ใช่', 'yes' => 'ใช่', 'true' => 'ใช่',
            'N' => 'ไม่ใช่', 'No' => 'ไม่ใช่', '0' => 'ไม่ใช่', 'no' => 'ไม่ใช่', 'false' => 'ไม่ใช่'
        ],
        
        // Education level mappings
        'education' => [
            '1' => 'ประถมศึกษา',
            '2' => 'มัธยมศึกษาตอนต้น', 
            '3' => 'มัธยมศึกษาตอนปลาย',
            '4' => 'ปวช./ปวส.',
            '5' => 'ปริญญาตรี',
            '6' => 'ปริญญาโท',
            '7' => 'ปริญญาเอก'
        ],
        
        // Marital status mappings
        'marital' => [
            '1' => 'โสด', 'single' => 'โสด',
            '2' => 'สมรส', 'married' => 'สมรส', 
            '3' => 'หย่าร้าง', 'divorced' => 'หย่าร้าง',
            '4' => 'หม้าย', 'widowed' => 'หม้าย'
        ]
    ];
    
    public static function getQuestionLabel($field_key, $survey_id = null) {
        // Try to get from database first (if we have survey structure)
        if ($survey_id) {
            $cached_label = self::getCachedQuestionLabel($field_key, $survey_id);
            if ($cached_label) {
                return $cached_label;
            }
        }
        
        // Use pattern matching
        foreach (self::$common_patterns as $pattern => $replacement) {
            if (preg_match($pattern, $field_key)) {
                return preg_replace($pattern, $replacement, $field_key);
            }
        }
        
        // Fallback: clean up field key
        return self::cleanFieldKey($field_key);
    }
    
    public static function getAnswerValue($field_key, $raw_value, $context = null) {
        if (empty($raw_value) && $raw_value !== '0') {
            return $raw_value;
        }
        
        // Detect answer type from field key
        $answer_type = self::detectAnswerType($field_key);
        
        if (isset(self::$value_mappings[$answer_type][$raw_value])) {
            return self::$value_mappings[$answer_type][$raw_value];
        }
        
        // Try to format common value types
        return self::formatValue($raw_value, $answer_type);
    }
    
    private static function detectAnswerType($field_key) {
        $field_lower = strtolower($field_key);
        
        if (preg_match('/(gender|sex|เพศ)/', $field_lower)) return 'gender';
        if (preg_match('/(yes|no|agree|disagree)/', $field_lower)) return 'yesno';
        if (preg_match('/(education|การศึกษา)/', $field_lower)) return 'education';
        if (preg_match('/(marital|สถานภาพ)/', $field_lower)) return 'marital';
        
        return 'text';
    }
    
    private static function formatValue($value, $type) {
        switch ($type) {
            case 'text':
                // Format long text
                if (strlen($value) > 100) {
                    return nl2br(esc_html($value));
                }
                return esc_html($value);
                
            case 'number':
                if (is_numeric($value)) {
                    return number_format($value);
                }
                return $value;
                
            case 'date':
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                    return date('j F Y', strtotime($value));
                }
                return $value;
                
            default:
                return esc_html($value);
        }
    }
    
    private static function cleanFieldKey($field_key) {
        // Remove common prefixes/suffixes
        $clean = preg_replace('/^(Q|question|ans|answer)_?/i', '', $field_key);
        $clean = str_replace(['_', '-'], ' ', $clean);
        $clean = ucwords(strtolower($clean));
        
        return $clean ?: $field_key;
    }
    
    private static function getCachedQuestionLabel($field_key, $survey_id) {
        // This would query a cache table or API
        // For now, return null to use pattern matching
        return null;
    }
    
    public static function getQuestionCategory($field_key) {
        $field_lower = strtolower($field_key);
        
        $categories = [
            'personal' => ['name', 'age', 'birth', 'gender', 'id', 'nationality', 'religion', 'marital'],
            'contact' => ['phone', 'email', 'address', 'province', 'district', 'postal', 'tel'],
            'education' => ['school', 'university', 'degree', 'grade', 'education', 'major', 'gpa'],
            'work' => ['job', 'work', 'occupation', 'company', 'position', 'income', 'salary', 'experience'],
            'survey' => ['Q', 'question', 'answer', 'opinion', 'rating', 'score']
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
}

// Include the advanced question mapper and survey adapter
require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-question-mapper.php';
require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-survey-adapter.php';
require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-auto-structure-detector.php';
require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-survey-layout-renderer.php';

// Initialize variables with error handling
$question_mapper = null;
$survey_adapter = null;
$adapter_result = null;
$response_mapping = array(
    'questions' => array(),
    'structure' => array('type' => 'unknown'),
    'categories' => array(),
    'statistics' => array()
);

try {
    // Initialize the advanced mapper and survey adapter
    $question_mapper = TPAK_Advanced_Question_Mapper::getInstance();
    $survey_adapter = TPAK_Survey_Adapter::getInstance();
    
    // ตรวจจับโครงสร้างอัตโนมัติถ้ายังไม่มี
    $detector = TPAK_Auto_Structure_Detector::getInstance();
    $detection_result = $detector->get_cached_result($lime_survey_id);
    
    if (!$detection_result) {
        error_log('TPAK DQ System: Running auto-detection for Survey ID: ' . $lime_survey_id);
        $detection_result = $detector->auto_detect_structure($lime_survey_id);
    }
    
    // เตรียม Survey Layout Renderer
    $layout_renderer = TPAK_Survey_Layout_Renderer::getInstance();
    $layout_prepared = $layout_renderer->prepare_survey_display(
        $lime_survey_id, 
        $response_data, 
        $detection_result['display_config'] ?? null
    );
    
    error_log('TPAK DQ System: Processing response for Survey ID: ' . $lime_survey_id . ' with ' . count($response_data) . ' fields');
    
    // Validate survey ID and response data
    if (empty($lime_survey_id)) {
        throw new Exception('Survey ID is empty');
    }
    
    if (empty($response_data) || !is_array($response_data)) {
        throw new Exception('Response data is empty or invalid');
    }
    
    // Process response with appropriate adapter
    $adapter_result = $survey_adapter->processResponse($lime_survey_id, $response_data);
    error_log('TPAK DQ System: Adapter processing completed. Type: ' . ($adapter_result['structure_type'] ?? 'unknown'));
    
    // Get response mapping
    $response_mapping = $question_mapper->getResponseMapping($response_data, $lime_survey_id);
    error_log('TPAK DQ System: Question mapping completed. Questions found: ' . count($response_mapping['questions']));
    
    // Merge adapter results with mapper results for better accuracy
    if ($adapter_result && isset($adapter_result['questions'])) {
        foreach ($adapter_result['questions'] as $key => $adapter_data) {
            if (isset($response_mapping['questions'][$key])) {
                // Use adapter's display name if confidence is higher
                if (isset($adapter_data['confidence']) && isset($response_mapping['questions'][$key]['confidence']) &&
                    $adapter_data['confidence'] > $response_mapping['questions'][$key]['confidence']) {
                    $response_mapping['questions'][$key]['display_name'] = $adapter_data['display_name'];
                    $response_mapping['questions'][$key]['category'] = $adapter_data['category'];
                    $response_mapping['questions'][$key]['confidence'] = $adapter_data['confidence'];
                }
            } else {
                // Add new question from adapter
                $response_mapping['questions'][$key] = $adapter_data;
            }
        }
        
        // Update structure info
        if (!isset($response_mapping['structure'])) {
            $response_mapping['structure'] = array();
        }
        $response_mapping['structure']['adapter_type'] = $adapter_result['structure_type'] ?? 'unknown';
        $response_mapping['structure']['adapter_confidence'] = $adapter_result['confidence'] ?? 0.5;
    }
    
} catch (Exception $e) {
    // Use error handler for better error management
    $error_handler = TPAK_Error_Handler::getInstance();
    $error_handler->log_error('Response processing failed: ' . $e->getMessage(), array(
        'survey_id' => $lime_survey_id,
        'response_fields' => array_keys($response_data),
        'trace' => $e->getTraceAsString()
    ), 'error');
    
    // Set default fallback values
    $response_mapping = array(
        'questions' => array(),
        'structure' => array('type' => 'error', 'message' => $e->getMessage()),
        'categories' => array(),
        'statistics' => array()
    );
    
    // Try basic fallback processing
    if (!empty($response_data) && is_array($response_data)) {
        foreach ($response_data as $key => $value) {
            if (!empty($value) && $value !== ' ') {
                $response_mapping['questions'][$key] = array(
                    'original_key' => $key,
                    'display_name' => $key, // Use key as fallback
                    'category' => 'unknown',
                    'type' => 'text',
                    'original_value' => $value,
                    'formatted_value' => htmlspecialchars($value),
                    'confidence' => 0.1
                );
            }
        }
    }
}

// Enhanced Question Organization with Smart Display Names
function generateDisplayName($field_key) {
    global $response_mapping, $lime_survey_id;
    
    // First check from response mapping
    if (isset($response_mapping['questions'][$field_key])) {
        return $response_mapping['questions'][$field_key]['display_name'];
    }
    
    // Fallback: Try to get from LSS structure directly
    $lss_structure = get_option('tpak_lss_structure_' . $lime_survey_id, false);
    if ($lss_structure) {
        // Try exact match first
        if (isset($lss_structure['questions'][$field_key])) {
            $qid = $lss_structure['questions'][$field_key]['qid'];
            if (isset($lss_structure['question_texts'][$qid]['question'])) {
                return strip_tags($lss_structure['question_texts'][$qid]['question']);
            }
        }
        
        // Try pattern matching for complex keys like PA1TT2[1]
        foreach ($lss_structure['questions'] as $title => $question_data) {
            if ($title === $field_key || strpos($field_key, $title) !== false || strpos($title, $field_key) !== false) {
                $qid = $question_data['qid'];
                if (isset($lss_structure['question_texts'][$qid]['question'])) {
                    $question_text = strip_tags($lss_structure['question_texts'][$qid]['question']);
                    
                    // Add sub-key info if it's a complex field
                    if (preg_match('/\[(\d+)\]$/', $field_key, $matches)) {
                        $question_text .= ' [' . $matches[1] . ']';
                    }
                    
                    return $question_text;
                }
            }
        }
    }
    
    // Final fallback: Try Question Dictionary
    require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-question-dictionary.php';
    $dictionary = TPAK_Question_Dictionary::getInstance();
    $dictionary->loadCustomMappings($lime_survey_id);
    $dict_result = $dictionary->getQuestionText($field_key);
    
    if ($dict_result !== $field_key) {
        return $dict_result;
    }
    
    return $field_key;
}

function guessCategory($field_key) {
    global $response_mapping;
    return isset($response_mapping['questions'][$field_key]) ? 
        $response_mapping['questions'][$field_key]['category'] : 'other';
}

function formatAnswerValue($field_key, $raw_value, $survey_id = null) {
    global $response_mapping;
    
    // First check from response mapping
    if (isset($response_mapping['questions'][$field_key])) {
        return $response_mapping['questions'][$field_key]['formatted_value'];
    }
    
    // Fallback: Use Question Dictionary to format
    require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-question-dictionary.php';
    $dictionary = TPAK_Question_Dictionary::getInstance();
    if ($survey_id) {
        $dictionary->loadCustomMappings($survey_id);
    }
    
    // Format common answer patterns
    if (strtoupper($raw_value) === 'Y') {
        return 'ใช่';
    } elseif (strtoupper($raw_value) === 'N') {
        return 'ไม่ใช่';
    } elseif (is_numeric($raw_value)) {
        $num = intval($raw_value);
        // 1-5 scale
        if ($num >= 1 && $num <= 5) {
            $scale = array(
                1 => 'น้อยที่สุด',
                2 => 'น้อย', 
                3 => 'ปานกลาง',
                4 => 'มาก',
                5 => 'มากที่สุด'
            );
            return isset($scale[$num]) ? $scale[$num] . ' (' . $num . ')' : $raw_value;
        }
    }
    
    // Use dictionary
    $formatted = $dictionary->getAnswerText($raw_value);
    return $formatted !== $raw_value ? $formatted : nl2br(esc_html($raw_value));
}

// Organize questions by groups/sections with enhanced logic
$organized_data = array();
$other_data = array();
$question_stats = array('total' => 0, 'answered' => 0, 'categories' => array());

if ($response_data && is_array($response_data)) {
    foreach ($response_data as $field_key => $field_value) {
        // Skip empty values but count them
        $question_stats['total']++;
        $is_answered = !($field_value === null || $field_value === '' || $field_value === ' ');
        
        if ($is_answered) {
            $question_stats['answered']++;
        }
        
        if (!$is_answered) {
            continue; // Skip empty for display but counted above
        }
        
        // Identify metadata fields
        if (in_array($field_key, ['id', 'submitdate', 'lastpage', 'startlanguage', 'seed', 'startdate', 'datestamp', 'ipaddr', 'refurl'])) {
            $other_data[$field_key] = $field_value;
            continue;
        }
        
        // Enhanced pattern matching with better grouping
        if (preg_match('/^(Q?\d+)([A-Z]*\d*)(.*)/', $field_key, $matches)) {
            $question_group = $matches[1];
            $sub_part = $matches[2] . $matches[3];
            
            if (!isset($organized_data[$question_group])) {
                $organized_data[$question_group] = array(
                    'main' => null,
                    'sub_questions' => array(),
                    'display_name' => generateDisplayName($question_group),
                    'category' => guessCategory($field_key),
                    'field_count' => 0,
                    'answered_count' => 0
                );
            }
            
            $organized_data[$question_group]['field_count']++;
            if ($is_answered) {
                $organized_data[$question_group]['answered_count']++;
            }
            
            if (empty($sub_part) || $sub_part === '') {
                $organized_data[$question_group]['main'] = $field_value;
            } else {
                $organized_data[$question_group]['sub_questions'][$field_key] = array(
                    'value' => $field_value,
                    'display_name' => generateDisplayName($field_key),
                    'category' => guessCategory($field_key)
                );
            }
        } else {
            // Handle other patterns
            if (!in_array($field_key, ['token', 'lastpage', 'startlanguage', 'seed'])) {
                $category = guessCategory($field_key);
                $organized_data[$field_key] = array(
                    'main' => $field_value,
                    'sub_questions' => array(),
                    'display_name' => generateDisplayName($field_key),
                    'category' => $category,
                    'field_count' => 1,
                    'answered_count' => $is_answered ? 1 : 0
                );
                
                // Count categories
                if (!isset($question_stats['categories'][$category])) {
                    $question_stats['categories'][$category] = 0;
                }
                $question_stats['categories'][$category]++;
            } else {
                $other_data[$field_key] = $field_value;
            }
        }
    }
}

// Sort organized data by question number for better display
uksort($organized_data, function($a, $b) {
    // Extract numbers for proper sorting
    preg_match('/\d+/', $a, $matches_a);
    preg_match('/\d+/', $b, $matches_b);
    
    $num_a = isset($matches_a[0]) ? intval($matches_a[0]) : 0;
    $num_b = isset($matches_b[0]) ? intval($matches_b[0]) : 0;
    
    return $num_a - $num_b;
});

$question_labels = array(); // Keep for backward compatibility
?>

<div class="wrap tpak-response-detail">
    <!-- Header -->
    <div class="tpak-detail-header">
        <div class="header-left">
            <h1>
                <?php _e('รายละเอียดแบบสอบถาม', 'tpak-dq-system'); ?>
                <span class="response-id">#<?php echo esc_html($lime_response_id ?: $response_id); ?></span>
            </h1>
            
            <?php if ($status): ?>
                <?php
                $status_term = get_term_by('slug', $status, 'verification_status');
                $status_name = $status_term ? $status_term->name : $status;
                ?>
                <span class="status-badge large <?php echo esc_attr($status); ?>">
                    <?php echo esc_html($status_name); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <div class="header-actions">
            <a href="<?php echo admin_url('admin.php?page=tpak-dq-responses'); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php _e('กลับ', 'tpak-dq-system'); ?>
            </a>
            
            <a href="<?php echo get_edit_post_link($response_id); ?>" class="button">
                <span class="dashicons dashicons-edit"></span>
                <?php _e('แก้ไข', 'tpak-dq-system'); ?>
            </a>
            
            <button class="button" onclick="window.print()">
                <span class="dashicons dashicons-printer"></span>
                <?php _e('พิมพ์', 'tpak-dq-system'); ?>
            </button>
            
            <button class="button export-excel" data-id="<?php echo $response_id; ?>">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <?php _e('ส่งออก Excel', 'tpak-dq-system'); ?>
            </button>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <div class="nav-tab-wrapper">
        <a href="#tab-original" class="nav-tab nav-tab-active" data-tab="original">
            📊 แบบเดิม
        </a>
        <a href="#tab-native" class="nav-tab" data-tab="native">
            🎯 Native 100%
        </a>
        <?php do_action('tpak_response_detail_tabs'); ?>
    </div>
    
    <!-- Main Content Area -->
    <div class="tpak-detail-content">
        
        <!-- Original Tab Content -->
        <div id="tab-original" class="tab-content active">
            <div class="content-main">
                
                <!-- Response Info Card -->
                <div class="info-card">
                    <h2><?php _e('ข้อมูลทั่วไป', 'tpak-dq-system'); ?></h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label><?php _e('ชื่อชุดข้อมูล:', 'tpak-dq-system'); ?></label>
                        <span><?php echo esc_html($post->post_title); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label><?php _e('วันที่ตอบ:', 'tpak-dq-system'); ?></label>
                        <span><?php echo get_the_date('j F Y H:i', $response_id); ?></span>
                    </div>
                    
                    <?php if (isset($other_data['submitdate'])): ?>
                        <div class="info-item">
                            <label><?php _e('วันที่ส่ง:', 'tpak-dq-system'); ?></label>
                            <span><?php echo esc_html($other_data['submitdate']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($other_data['startdate'])): ?>
                        <div class="info-item">
                            <label><?php _e('เริ่มตอบ:', 'tpak-dq-system'); ?></label>
                            <span><?php echo esc_html($other_data['startdate']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($lime_survey_id): ?>
                        <div class="info-item">
                            <label><?php _e('Survey ID:', 'tpak-dq-system'); ?></label>
                            <span><?php echo esc_html($lime_survey_id); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($import_date): ?>
                        <div class="info-item">
                            <label><?php _e('วันที่ Import:', 'tpak-dq-system'); ?></label>
                            <span><?php echo esc_html($import_date); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Enhanced Search/Filter Bar with Display Modes -->
            <div class="question-filter enhanced">
                <div class="filter-row-1">
                    <input type="text" id="question-search" 
                           placeholder="<?php _e('ค้นหาคำถามหรือคำตอบ...', 'tpak-dq-system'); ?>">
                    
                    <div class="display-mode-selector">
                        <label><?php _e('รูปแบบการแสดงผล:', 'tpak-dq-system'); ?></label>
                        <select id="display-mode" class="display-mode-select">
                            <option value="original" <?php echo $layout_prepared ? 'selected' : ''; ?>><?php _e('🎯 แบบต้นฉบับ (ใหม่!)', 'tpak-dq-system'); ?></option>
                            <option value="enhanced" <?php echo !$layout_prepared ? 'selected' : ''; ?>><?php _e('แบบปรับปรุง', 'tpak-dq-system'); ?></option>
                            <option value="grouped"><?php _e('จัดกลุ่มตามหมวด', 'tpak-dq-system'); ?></option>
                            <option value="flat"><?php _e('แสดงแบบเรียบ', 'tpak-dq-system'); ?></option>
                            <option value="table"><?php _e('แสดงแบบตาราง', 'tpak-dq-system'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row-2">
                    <div class="filter-stats">
                        <span class="stat-item">
                            <strong><?php echo $question_stats['answered']; ?></strong> / <?php echo $question_stats['total']; ?> 
                            <?php _e('ตอบแล้ว', 'tpak-dq-system'); ?>
                        </span>
                        <span class="stat-item completion-rate">
                            <?php 
                            $completion_rate = $question_stats['total'] > 0 ? 
                                round(($question_stats['answered'] / $question_stats['total']) * 100) : 0;
                            echo $completion_rate . '%';
                            ?>
                        </span>
                        <?php if (!empty($question_stats['categories'])): ?>
                            <span class="stat-item">
                                <?php echo count($question_stats['categories']); ?> <?php _e('หมวดหมู่', 'tpak-dq-system'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="filter-actions">
                        <div class="category-filter">
                            <select id="category-filter">
                                <option value=""><?php _e('ทุกหมวดหมู่', 'tpak-dq-system'); ?></option>
                                <?php foreach ($question_stats['categories'] as $category => $count): ?>
                                    <option value="<?php echo esc_attr($category); ?>">
                                        <?php 
                                        $category_names = [
                                            'personal' => 'ข้อมูลส่วนตัว',
                                            'contact' => 'ข้อมูลติดต่อ', 
                                            'education' => 'การศึกษา',
                                            'work' => 'การทำงาน',
                                            'survey' => 'คำถามสำรวจ',
                                            'other' => 'อื่นๆ'
                                        ];
                                        echo isset($category_names[$category]) ? $category_names[$category] : ucfirst($category);
                                        echo ' (' . $count . ')';
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button class="button expand-all">
                            <span class="dashicons dashicons-editor-expand"></span>
                            <?php _e('ขยายทั้งหมด', 'tpak-dq-system'); ?>
                        </button>
                        <button class="button collapse-all">
                            <span class="dashicons dashicons-editor-contract"></span>
                            <?php _e('ย่อทั้งหมด', 'tpak-dq-system'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Questions and Answers -->
            <div class="questions-container">
                <h2>
                    <?php _e('คำถามและคำตอบ', 'tpak-dq-system'); ?>
                    <span class="question-count">
                        (<?php echo count($organized_data); ?> <?php _e('คำถามหลัก', 'tpak-dq-system'); ?>)
                    </span>
                </h2>
                
                <?php if (current_user_can('manage_options')): ?>
                    <!-- Survey Analysis Dashboard -->
                    <div class="survey-analysis-dashboard" style="margin-bottom: 20px;">
                        <div class="analysis-cards">
                            <div class="analysis-card structure-card">
                                <div class="card-header">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <h4>โครงสร้างแบบสอบถาม</h4>
                                </div>
                                <div class="card-content">
                                    <div class="structure-info">
                                        <span class="structure-type <?php echo $response_mapping['structure']['type']; ?>">
                                            <?php 
                                            $structure_names = [
                                                'limesurvey' => 'LimeSurvey Standard',
                                                'numeric' => 'ตัวเลขเรียงลำดับ',
                                                'descriptive' => 'ชื่อบรรยาย',
                                                'mixed' => 'รูปแบบผสม'
                                            ];
                                            echo $structure_names[$response_mapping['structure']['type']] ?? 'ไม่ทราบ';
                                            ?>
                                        </span>
                                        <span class="complexity-badge <?php echo $response_mapping['structure']['complexity']; ?>">
                                            <?php 
                                            $complexity_names = [
                                                'simple' => 'เรียบง่าย',
                                                'moderate' => 'ปานกลาง', 
                                                'complex' => 'ซับซ้อน'
                                            ];
                                            echo $complexity_names[$response_mapping['structure']['complexity']] ?? 'ไม่ทราบ';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="analysis-card mapping-card">
                                <div class="card-header">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <h4>คุณภาพการแปลง</h4>
                                </div>
                                <div class="card-content">
                                    <div class="confidence-meter">
                                        <div class="confidence-bar">
                                            <div class="confidence-fill" style="width: <?php echo ($response_mapping['statistics']['confidence_average'] * 100); ?>%"></div>
                                        </div>
                                        <span class="confidence-text">
                                            <?php echo round($response_mapping['statistics']['confidence_average'] * 100); ?>% ความแม่นยำ
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="analysis-card completion-card">
                                <div class="card-header">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <h4>ความสมบูรณ์</h4>
                                </div>
                                <div class="card-content">
                                    <div class="completion-stats">
                                        <div class="completion-number"><?php echo $response_mapping['statistics']['completion_rate']; ?>%</div>
                                        <div class="completion-detail">
                                            <?php echo $response_mapping['statistics']['answered_questions']; ?> / 
                                            <?php echo $response_mapping['statistics']['total_questions']; ?> คำถาม
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (current_user_can('manage_options')): ?>
                        <!-- Debug Info for Admins -->
                        <details style="margin-bottom: 20px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                            <summary style="cursor: pointer; font-weight: bold;">🔧 Debug Information (Admin Only)</summary>
                            <div style="margin-top: 10px; font-size: 12px;">
                                <p><strong>Survey Structure:</strong> <?php echo json_encode($response_mapping['structure'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></p>
                                <p><strong>Statistics:</strong> <?php echo json_encode($response_mapping['statistics'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></p>
                                <p><strong>Categories:</strong> <?php echo implode(', ', array_keys($response_mapping['statistics']['categories'])); ?></p>
                                <p><strong>Raw Response Data Keys:</strong> <?php echo $response_data ? implode(', ', array_keys($response_data)) : 'None'; ?></p>
                            </div>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Original Survey Layout (ใหม่!) -->
                <?php if ($layout_prepared): ?>
                    <div id="original-layout" class="survey-layout-container" style="display: block;">
                        <div class="layout-header">
                            <h3>🎯 รูปแบบแบบสอบถามต้นฉบับ</h3>
                            <p class="layout-description">แสดงผลตามโครงสร้างดั้งเดิมของแบบสอบถาม พร้อมกลุ่มคำถามและลำดับที่ถูกต้อง</p>
                        </div>
                        
                        <?php echo $layout_renderer->render_survey_layout(); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Enhanced Layout (เดิม) -->
                <div id="enhanced-layout" class="enhanced-layout-container" style="display: <?php echo $layout_prepared ? 'none' : 'block'; ?>;">
                
                <?php if (!empty($organized_data)): ?>
                    <?php 
                    $section_num = 1;
                    foreach ($organized_data as $question_key => $question_data): 
                        $category = isset($question_data['category']) ? $question_data['category'] : 'other';
                        $display_name = isset($question_data['display_name']) ? $question_data['display_name'] : $question_key;
                        $completion_rate = 0;
                        if (isset($question_data['field_count']) && $question_data['field_count'] > 0) {
                            $completion_rate = round(($question_data['answered_count'] / $question_data['field_count']) * 100);
                        }
                    ?>
                        <div class="question-section enhanced" 
                             data-question="<?php echo esc_attr($question_key); ?>"
                             data-category="<?php echo esc_attr($category); ?>"
                             data-completion="<?php echo $completion_rate; ?>">
                            
                            <div class="question-header">
                                <button class="toggle-section" aria-expanded="true">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                
                                <div class="question-meta">
                                    <div class="category-badge <?php echo esc_attr($category); ?>">
                                        <?php 
                                        $category_icons = [
                                            'personal' => 'dashicons-admin-users',
                                            'contact' => 'dashicons-phone', 
                                            'education' => 'dashicons-welcome-learn-more',
                                            'work' => 'dashicons-businessman',
                                            'survey' => 'dashicons-clipboard',
                                            'other' => 'dashicons-admin-generic'
                                        ];
                                        $icon = isset($category_icons[$category]) ? $category_icons[$category] : 'dashicons-admin-generic';
                                        ?>
                                        <span class="dashicons <?php echo $icon; ?>"></span>
                                    </div>
                                    
                                    <div class="question-title">
                                        <span class="question-text">
                                            <?php echo esc_html($display_name); ?>
                                        </span>
                                        <small class="question-number"><?php echo esc_html($question_key); ?></small>
                                    </div>
                                </div>
                                
                                <div class="question-stats">
                                    <?php if (isset($question_data['field_count']) && $question_data['field_count'] > 1): ?>
                                        <span class="completion-indicator">
                                            <span class="completion-bar">
                                                <span class="completion-fill" style="width: <?php echo $completion_rate; ?>%"></span>
                                            </span>
                                            <span class="completion-text"><?php echo $completion_rate; ?>%</span>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="field-count">
                                        <?php 
                                        $total_fields = (isset($question_data['field_count']) ? $question_data['field_count'] : 1);
                                        if ($total_fields > 1) {
                                            echo $total_fields . ' ' . __('ฟิลด์', 'tpak-dq-system');
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="question-content">
                                <!-- Main Answer -->
                                <?php if ($question_data['main'] !== null): ?>
                                    <div class="main-answer">
                                        <div class="answer-label">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php _e('คำตอบหลัก:', 'tpak-dq-system'); ?>
                                        </div>
                                        <div class="answer-value">
                                            <?php echo formatAnswerValue($question_key, $question_data['main'], $lime_survey_id); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Sub-questions -->
                                <?php if (!empty($question_data['sub_questions'])): ?>
                                    <div class="sub-questions">
                                        <div class="sub-questions-header">
                                            <span class="dashicons dashicons-list-view"></span>
                                            <?php _e('คำถามย่อย:', 'tpak-dq-system'); ?>
                                            <span class="sub-count">(<?php echo count($question_data['sub_questions']); ?> รายการ)</span>
                                        </div>
                                        
                                        <div class="sub-questions-grid">
                                            <?php foreach ($question_data['sub_questions'] as $sub_key => $sub_data): 
                                                $sub_value = is_array($sub_data) ? $sub_data['value'] : $sub_data;
                                                $sub_display_name = is_array($sub_data) && isset($sub_data['display_name']) ? 
                                                    $sub_data['display_name'] : generateDisplayName($sub_key);
                                            ?>
                                                <div class="sub-question-item">
                                                    <div class="sub-question-header">
                                                        <span class="sub-question-label">
                                                            <?php echo esc_html($sub_display_name); ?>
                                                        </span>
                                                        <small class="sub-question-key"><?php echo esc_html($sub_key); ?></small>
                                                    </div>
                                                    <div class="sub-question-value">
                                                        <?php echo formatAnswerValue($sub_key, $sub_value, $lime_survey_id);
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                    $section_num++;
                    endforeach; 
                    ?>
                <?php else: ?>
                    <div class="no-questions">
                        <p><?php _e('ไม่พบข้อมูลคำถามและคำตอบ', 'tpak-dq-system'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Other Data (if any) -->
            <?php if (!empty($other_data)): ?>
                <div class="other-data-container">
                    <h2><?php _e('ข้อมูลเพิ่มเติม', 'tpak-dq-system'); ?></h2>
                    <div class="other-data-grid">
                        <?php foreach ($other_data as $key => $value): ?>
                            <?php if (!in_array($key, ['submitdate', 'startdate', 'id'])): ?>
                                <div class="other-data-item">
                                    <label><?php echo esc_html($key); ?>:</label>
                                    <span><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="content-sidebar">
            <!-- Quick Navigation -->
            <div class="sidebar-card">
                <h3><?php _e('นำทางด่วน', 'tpak-dq-system'); ?></h3>
                <div class="quick-nav">
                    <?php foreach ($organized_data as $question_key => $question_data): ?>
                        <a href="#" class="nav-item" data-target="<?php echo esc_attr($question_key); ?>">
                            <?php echo esc_html($question_key); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Verification Status -->
            <div class="sidebar-card">
                <h3><?php _e('สถานะการตรวจสอบ', 'tpak-dq-system'); ?></h3>
                <div class="status-info">
                    <?php if ($status): ?>
                        <?php
                        $status_term = get_term_by('slug', $status, 'verification_status');
                        ?>
                        <div class="current-status">
                            <span class="status-badge large <?php echo esc_attr($status); ?>">
                                <?php echo esc_html($status_term->name); ?>
                            </span>
                        </div>
                        
                        <?php if ($status_term->description): ?>
                            <p class="status-description">
                                <?php echo esc_html($status_term->description); ?>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?php _e('ยังไม่มีสถานะ', 'tpak-dq-system'); ?></p>
                    <?php endif; ?>
                    
                    <!-- Status Change Section -->
                    <?php
                    $current_user_id = get_current_user_id();
                    $user_role = '';
                    $user = wp_get_current_user();
                    
                    // Determine user role
                    if (in_array('administrator', $user->roles)) {
                        $user_role = 'administrator';
                    } elseif (in_array('interviewer', $user->roles)) {
                        $user_role = 'interviewer';
                    } elseif (in_array('supervisor', $user->roles)) {
                        $user_role = 'supervisor';
                    } elseif (in_array('examiner', $user->roles)) {
                        $user_role = 'examiner';
                    }
                    
                    // Get available actions
                    $available_actions = $workflow->get_available_actions($response_id, $current_user_id);
                    ?>
                    
                    <?php if (!empty($available_actions) || current_user_can('manage_options')): ?>
                        <div class="status-change-section">
                            <h4><?php _e('เปลี่ยนสถานะ', 'tpak-dq-system'); ?></h4>
                            
                            <?php if (current_user_can('manage_options')): ?>
                                <!-- Admin can change to any status -->
                                <div class="admin-status-change">
                                    <label for="status-select"><?php _e('เลือกสถานะใหม่:', 'tpak-dq-system'); ?></label>
                                    <select id="status-select" class="status-select">
                                        <option value=""><?php _e('-- เลือกสถานะ --', 'tpak-dq-system'); ?></option>
                                        <?php
                                        $all_statuses = get_terms(array(
                                            'taxonomy' => 'verification_status',
                                            'hide_empty' => false
                                        ));
                                        
                                        foreach ($all_statuses as $status_option):
                                            $selected = ($status_option->slug === $status) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo esc_attr($status_option->slug); ?>" <?php echo $selected; ?>>
                                                <?php echo esc_html($status_option->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <div class="admin-comment-section" style="margin-top: 10px;">
                                        <label for="admin-comment"><?php _e('หมายเหตุ (ไม่บังคับ):', 'tpak-dq-system'); ?></label>
                                        <textarea id="admin-comment" class="admin-comment" rows="3" 
                                                  placeholder="<?php _e('เพิ่มหมายเหตุสำหรับการเปลี่ยนสถานะ...', 'tpak-dq-system'); ?>"></textarea>
                                    </div>
                                    
                                    <button class="button button-primary admin-change-status" 
                                            data-id="<?php echo $response_id; ?>" 
                                            style="margin-top: 10px; width: 100%;">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php _e('เปลี่ยนสถานะ', 'tpak-dq-system'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($available_actions)): ?>
                                <!-- Role-based actions -->
                                <div class="role-based-actions" <?php echo current_user_can('manage_options') ? 'style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;"' : ''; ?>>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <h5><?php _e('หรือใช้การดำเนินการตามบทบาท:', 'tpak-dq-system'); ?></h5>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($available_actions as $action): ?>
                                        <?php
                                        $action_name = $workflow->get_action_display_name($action);
                                        $button_class = 'button';
                                        $icon = 'dashicons-yes';
                                        
                                        if (strpos($action, 'reject') !== false) {
                                            $button_class .= ' button-secondary';
                                            $icon = 'dashicons-no';
                                        } else {
                                            $button_class .= ' button-primary';
                                        }
                                        ?>
                                        
                                        <button class="<?php echo $button_class; ?> workflow-action-btn" 
                                                data-id="<?php echo $response_id; ?>" 
                                                data-action="<?php echo esc_attr($action); ?>"
                                                style="width: 100%; margin-bottom: 10px;">
                                            <span class="dashicons <?php echo $icon; ?>"></span>
                                            <?php echo esc_html($action_name); ?>
                                        </button>
                                    <?php endforeach; ?>
                                    
                                    <!-- Comment section for reject actions -->
                                    <div class="comment-section" style="display: none; margin-top: 10px;">
                                        <label for="action-comment"><?php _e('ความคิดเห็น (บังคับสำหรับการส่งกลับ):', 'tpak-dq-system'); ?></label>
                                        <textarea id="action-comment" class="action-comment" rows="3" 
                                                  placeholder="<?php _e('กรุณาระบุเหตุผลในการส่งกลับ...', 'tpak-dq-system'); ?>"></textarea>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Audit Trail -->
            <?php if (!empty($audit_trail)): ?>
                <div class="sidebar-card">
                    <h3><?php _e('ประวัติการตรวจสอบ', 'tpak-dq-system'); ?></h3>
                    <div class="audit-trail">
                        <?php foreach (array_reverse($audit_trail) as $entry): ?>
                            <div class="audit-entry">
                                <div class="audit-date">
                                    <?php echo esc_html($entry['timestamp']); ?>
                                </div>
                                <div class="audit-action">
                                    <strong><?php echo esc_html($entry['user_name']); ?></strong>
                                    <?php echo esc_html($entry['action']); ?>
                                </div>
                                <?php if (!empty($entry['note'])): ?>
                                    <div class="audit-note">
                                        <?php echo esc_html($entry['note']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="sidebar-card">
                <h3><?php _e('สถิติ', 'tpak-dq-system'); ?></h3>
                <div class="response-stats">
                    <div class="stat-item">
                        <label><?php _e('คำถามทั้งหมด:', 'tpak-dq-system'); ?></label>
                        <span><?php echo count($organized_data); ?></span>
                    </div>
                    
                    <div class="stat-item">
                        <label><?php _e('ตอบแล้ว:', 'tpak-dq-system'); ?></label>
                        <span>
                            <?php 
                            $answered = 0;
                            foreach ($organized_data as $q) {
                                if ($q['main'] !== null && $q['main'] !== '') {
                                    $answered++;
                                }
                            }
                            echo $answered;
                            ?>
                        </span>
                    </div>
                    
                    <div class="stat-item">
                        <label><?php _e('เปอร์เซ็นต์:', 'tpak-dq-system'); ?></label>
                        <span>
                            <?php 
                            $percentage = count($organized_data) > 0 ? 
                                round(($answered / count($organized_data)) * 100) : 0;
                            echo $percentage . '%';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            </div> <!-- End content-main -->
        </div> <!-- End tab-original -->
        
        <!-- Native Tab Content -->
        <div id="tab-native" class="tab-content">
            <div class="native-integration-header">
                <h3>🎯 ระบบแก้ไขแบบสอบถาม Native 100%</h3>
                <p>ดึงแบบสอบถามมาแบบสมบูรณ์และแก้ไขได้เต็มรูปแบบ</p>
                
                <div class="integration-controls">
                    <button type="button" class="button button-primary" id="activate-native">
                        🚀 เปิดใช้งาน Native Mode
                    </button>
                    <button type="button" class="button" id="compare-views">
                        🔍 เปรียบเทียบกับแบบเดิม
                    </button>
                </div>
                
                <div class="integration-status">
                    <div class="status-item">
                        <label>แบบเดิม:</label>
                        <span class="status-indicator original-status">✅ โหลดแล้ว</span>
                    </div>
                    <div class="status-item">
                        <label>แบบ Native:</label>
                        <span class="status-indicator native-status">⏳ รอโหลด</span>
                    </div>
                </div>
            </div>
            
            <div id="native-survey-container" style="display: none;">
                <!-- Native Survey Renderer จะโหลดที่นี่ -->
            </div>
        </div>
        
        <?php do_action('tpak_response_detail_content'); ?>
        
    </div> <!-- End tpak-detail-content -->
</div> <!-- End wrap -->

<style>
/* Tab Styles */
.nav-tab-wrapper {
    margin-bottom: 20px;
}

.nav-tab {
    font-size: 14px !important;
    padding: 10px 15px !important;
}

.nav-tab-active {
    background: #fff !important;
    border-bottom: 1px solid #fff !important;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block !important;
}

.nav-tab {
    cursor: pointer !important;
    text-decoration: none;
    border: 1px solid #c3c4c7;
    border-bottom: none;
    margin-left: 0.5em;
    padding: 5px 14px;
    background: #f6f7f7;
    color: #50575e;
    font-size: 12px;
    line-height: 16px;
    display: inline-block;
}
.nav-tab:hover {
    background-color: #fff;
    color: #2271b1;
}
/* Native Integration Styles */
.native-integration-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.native-integration-header h3 {
    margin: 0 0 10px 0;
    color: white;
}

.integration-controls {
    margin: 15px 0;
}

.integration-controls button {
    margin-right: 10px;
}

.integration-status {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    font-size: 14px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-indicator {
    font-weight: bold;
}

#native-survey-container {
    border: 2px solid #667eea;
    border-radius: 8px;
    padding: 20px;
    background: #f8f9ff;
}

/* Print Styles */
@media print {
    .tpak-detail-header .header-actions,
    .content-sidebar,
    .question-filter,
    .toggle-section {
        display: none !important;
    }
    
    .question-content {
        display: block !important;
    }
}

/* Layout */
.tpak-response-detail {
    max-width: 1400px;
    margin: 20px auto;
}

.tpak-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-left h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.response-id {
    color: #666;
    font-weight: normal;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.header-actions .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Content Layout */
.tpak-detail-content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
}

@media (max-width: 1200px) {
    .tpak-detail-content {
        grid-template-columns: 1fr;
    }
}

/* Info Card */
.info-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.info-card h2 {
    margin: 0 0 15px 0;
    font-size: 18px;
    color: #23282d;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-item.full-width {
    grid-column: span 2;
}

.info-item label {
    font-weight: 600;
    color: #666;
    font-size: 13px;
}

.info-item span {
    color: #23282d;
    font-size: 14px;
}

/* Question Filter */
.question-filter {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
}

#question-search {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

/* Questions Container */
.questions-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.questions-container h2 {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #23282d;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.question-count {
    font-size: 14px;
    color: #666;
    font-weight: normal;
}

/* Question Section */
.question-section {
    border: 1px solid #e9ecef;
    border-radius: 4px;
    margin-bottom: 15px;
    overflow: hidden;
}

.question-section.filtered-out {
    display: none;
}

.question-header {
    background: #f8f9fa;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
}

.toggle-section {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: #666;
    transition: transform 0.3s;
}

.toggle-section .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.question-section.collapsed .toggle-section .dashicons {
    transform: rotate(-90deg);
}

.question-title {
    flex: 1;
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.question-number {
    font-weight: 600;
    color: #0073aa;
    font-size: 16px;
}

.question-text {
    color: #23282d;
    font-size: 14px;
}

.question-content {
    padding: 20px;
    display: block;
}

.question-section.collapsed .question-content {
    display: none;
}

/* Answers */
.main-answer {
    margin-bottom: 20px;
}

.answer-label {
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
    font-size: 13px;
    text-transform: uppercase;
}

.answer-value {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 4px;
    border-left: 3px solid #0073aa;
    color: #23282d;
    font-size: 14px;
}

/* Sub-questions */
.sub-questions {
    margin-top: 20px;
}

.sub-questions-header {
    font-weight: 600;
    color: #666;
    margin-bottom: 15px;
    font-size: 13px;
    text-transform: uppercase;
}

.sub-question-item {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 15px;
    padding: 10px;
    border-bottom: 1px solid #e9ecef;
}

.sub-question-item:last-child {
    border-bottom: none;
}

.sub-question-key {
    font-weight: 600;
    color: #666;
    font-size: 13px;
}

.sub-question-label {
    font-weight: normal;
    color: #999;
    font-size: 12px;
}

.sub-question-value {
    color: #23282d;
    font-size: 14px;
}

/* Other Data */
.other-data-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
}

.other-data-container h2 {
    margin: 0 0 15px 0;
    font-size: 18px;
    color: #23282d;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 10px;
}

.other-data-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.other-data-item {
    display: flex;
    gap: 10px;
}

.other-data-item label {
    font-weight: 600;
    color: #666;
    font-size: 13px;
}

.other-data-item span {
    color: #23282d;
    font-size: 13px;
}

/* Sidebar */
.sidebar-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.sidebar-card h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #23282d;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 10px;
}

/* Quick Navigation */
.quick-nav {
    display: flex;
    flex-direction: column;
    gap: 5px;
    max-height: 400px;
    overflow-y: auto;
}

.nav-item {
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 4px;
    text-decoration: none;
    color: #23282d;
    font-size: 13px;
    transition: all 0.2s;
}

.nav-item:hover,
.nav-item.active {
    background: #0073aa;
    color: #fff;
}

/* Status Info */
.current-status {
    margin-bottom: 15px;
}

.status-badge.large {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-description {
    color: #666;
    font-size: 13px;
    margin: 10px 0;
}

.verification-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.verification-actions .button {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

/* Audit Trail */
.audit-trail {
    max-height: 300px;
    overflow-y: auto;
}

.audit-entry {
    padding: 10px;
    border-bottom: 1px solid #e9ecef;
    font-size: 13px;
}

.audit-entry:last-child {
    border-bottom: none;
}

.audit-date {
    color: #999;
    font-size: 11px;
    margin-bottom: 5px;
}

.audit-action {
    color: #23282d;
}

.audit-note {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}

/* Response Stats */
.response-stats {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.stat-item label {
    color: #666;
    font-size: 13px;
}

.stat-item span {
    font-weight: 600;
    color: #23282d;
    font-size: 14px;
}

/* No Questions */
.no-questions {
    text-align: center;
    padding: 40px;
    color: #666;
}

/* Highlight search results */
.highlight {
    background-color: #ffeb3b;
    padding: 2px 4px;
    border-radius: 2px;
}

/* Status Change Section */
.status-change-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.status-change-section h4,
.status-change-section h5 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #23282d;
    font-weight: 600;
}

.status-change-section h5 {
    font-size: 13px;
    color: #666;
    margin-top: 15px;
}

.admin-status-change {
    margin-bottom: 15px;
}

.admin-status-change label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #666;
    font-size: 13px;
}

.status-select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

.admin-comment-section label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #666;
    font-size: 13px;
}

.admin-comment,
.action-comment {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    resize: vertical;
    min-height: 60px;
}

.admin-change-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.role-based-actions {
    margin-top: 15px;
}

.workflow-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin-bottom: 8px;
}

.comment-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}

.comment-section label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #666;
    font-size: 13px;
}

/* Status badges */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: #fff;
}

.status-badge.large {
    padding: 8px 16px;
    font-size: 12px;
    border-radius: 16px;
}

.status-badge.pending_a {
    background-color: #f39c12;
}

.status-badge.pending_b {
    background-color: #3498db;
}

.status-badge.pending_c {
    background-color: #9b59b6;
}

.status-badge.rejected_by_b,
.status-badge.rejected_by_c {
    background-color: #e74c3c;
}

.status-badge.finalized,
.status-badge.finalized_by_sampling {
    background-color: #27ae60;
}

/* Loading animation */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Notifications */
.notice {
    position: relative;
    margin: 5px 0 15px;
    padding: 1px 12px;
    border-left: 4px solid #fff;
    background: #fff;
    box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
}

.notice-success {
    border-left-color: #46b450;
}

.notice-error {
    border-left-color: #dc3232;
}

.notice p {
    margin: 0.5em 0;
    padding: 2px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .verification-actions,
    .role-based-actions {
        flex-direction: column;
    }
    
    .verification-actions .button,
    .workflow-action-btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .sub-question-item {
        grid-template-columns: 1fr;
        gap: 5px;
    }
    
    .other-data-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        console.log('Tab clicked!');
        
        var tabId = $(this).data('tab');
        console.log('Tab ID:', tabId);
        
        // Remove active class from all tabs and content
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        
        // Add active class to clicked tab and corresponding content
        $(this).addClass('nav-tab-active');
        $('#tab-' + tabId).addClass('active');
        
        console.log('Tab switching completed to:', tabId);
    });
    
    // Fallback: Handle tab clicking with alternative method
    $(document).on('click', '.nav-tab', function(e) {
        console.log('Fallback tab handler triggered');
        e.preventDefault();
        
        var tabId = $(this).data('tab');
        if (!tabId) {
            console.log('No tab ID found');
            return;
        }
        
        // Manual tab switching
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide().removeClass('active');
        
        $(this).addClass('nav-tab-active');
        $('#tab-' + tabId).show().addClass('active');
        
        console.log('Fallback tab switching completed to:', tabId);
    });
    
    // Native Survey Integration
    var surveyId = '<?php echo esc_js($lime_survey_id); ?>';
    var responseId = '<?php echo esc_js($response_id); ?>';
    
    // Debug log
    console.log('Tab switching initialized');
    console.log('jQuery version:', $.fn.jquery);
    console.log('Number of tabs found:', $('.nav-tab').length);
    console.log('Survey ID:', surveyId);
    console.log('Response ID:', responseId);
    
    $('#activate-native').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('🔄 กำลังโหลด...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'load_native_view',
                survey_id: surveyId,
                response_id: responseId,
                post_id: responseId,
                nonce: '<?php echo wp_create_nonce('native_view_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#native-survey-container').html(response.data.html).show();
                    $('.native-status').html('✅ โหลดแล้ว').removeClass('loading').addClass('loaded');
                    button.text('✅ Native Mode เปิดใช้งานแล้ว').addClass('button-secondary').removeClass('button-primary');
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (response.data || 'ไม่สามารถโหลด Native view ได้'));
                    button.prop('disabled', false).text('🚀 เปิดใช้งาน Native Mode');
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                button.prop('disabled', false).text('🚀 เปิดใช้งาน Native Mode');
            }
        });
    });
    
    // Toggle question sections
    $('.toggle-section, .question-header').on('click', function(e) {
        e.preventDefault();
        var section = $(this).closest('.question-section');
        section.toggleClass('collapsed');
        
        var expanded = !section.hasClass('collapsed');
        section.find('.toggle-section').attr('aria-expanded', expanded);
    });
    
    // Expand all
    $('.expand-all').on('click', function() {
        $('.question-section').removeClass('collapsed');
        $('.toggle-section').attr('aria-expanded', 'true');
    });
    
    // Collapse all
    $('.collapse-all').on('click', function() {
        $('.question-section').addClass('collapsed');
        $('.toggle-section').attr('aria-expanded', 'false');
    });
    
    // Search functionality
    $('#question-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.question-section').each(function() {
            var section = $(this);
            var questionText = section.find('.question-title').text().toLowerCase();
            var answerText = section.find('.question-content').text().toLowerCase();
            
            if (questionText.indexOf(searchTerm) !== -1 || answerText.indexOf(searchTerm) !== -1) {
                section.removeClass('filtered-out');
                // Highlight search terms
                if (searchTerm.length > 0) {
                    highlightText(section, searchTerm);
                } else {
                    removeHighlight(section);
                }
            } else {
                section.addClass('filtered-out');
            }
        });
    });
    
    // Quick navigation
    $('.nav-item').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        var targetSection = $('.question-section[data-question="' + target + '"]');
        
        if (targetSection.length) {
            // Remove active class from all nav items
            $('.nav-item').removeClass('active');
            $(this).addClass('active');
            
            // Scroll to target section
            $('html, body').animate({
                scrollTop: targetSection.offset().top - 100
            }, 500);
            
            // Expand the target section if collapsed
            targetSection.removeClass('collapsed');
            targetSection.find('.toggle-section').attr('aria-expanded', 'true');
        }
    });
    
    // Admin status change
    $(document).on('click', '.admin-change-status', function() {
        console.log('Admin change status button clicked');
        
        var button = $(this);
        var postId = button.data('id');
        var newStatus = $('#status-select').val();
        var comment = $('#admin-comment').val();
        
        console.log('Post ID:', postId, 'New Status:', newStatus, 'Comment:', comment);
        
        if (!newStatus) {
            alert('<?php _e('กรุณาเลือกสถานะใหม่', 'tpak-dq-system'); ?>');
            return;
        }
        
        if (confirm('<?php _e('คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะ?', 'tpak-dq-system'); ?>')) {
            changeStatus(postId, newStatus, comment, 'admin_change');
        }
    });
    
    // Workflow action buttons
    $('.workflow-action-btn').on('click', function() {
        var button = $(this);
        var postId = button.data('id');
        var action = button.data('action');
        
        // Show comment section for reject actions
        if (action.indexOf('reject') !== -1) {
            $('.comment-section').show();
            $('#action-comment').focus();
            
            // Change button text to confirm
            button.text('<?php _e('ยืนยันการส่งกลับ', 'tpak-dq-system'); ?>');
            button.off('click').on('click', function() {
                var comment = $('#action-comment').val().trim();
                if (!comment) {
                    alert('<?php _e('กรุณากรอกความคิดเห็นสำหรับการส่งกลับ', 'tpak-dq-system'); ?>');
                    return;
                }
                
                if (confirm('<?php _e('คุณแน่ใจหรือไม่ที่จะส่งกลับ?', 'tpak-dq-system'); ?>')) {
                    performWorkflowAction(postId, action, comment);
                }
            });
        } else {
            // For approve actions
            if (confirm('<?php _e('คุณแน่ใจหรือไม่ที่จะดำเนินการนี้?', 'tpak-dq-system'); ?>')) {
                performWorkflowAction(postId, action, '');
            }
        }
    });
    
    // Function to change status (admin)
    function changeStatus(postId, newStatus, comment, actionType) {
        console.log('changeStatus called with:', postId, newStatus, comment, actionType);
        
        var button = $('.admin-change-status');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('กำลังเปลี่ยน...', 'tpak-dq-system'); ?>');
        
        var requestData = {
            action: 'tpak_admin_change_status',
            post_id: postId,
            new_status: newStatus,
            comment: comment,
            nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
        };
        
        console.log('AJAX request data:', requestData);
        console.log('AJAX URL:', ajaxurl);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                console.log('AJAX success response:', response);
                
                if (response.success) {
                    // Show success message
                    showNotification('success', response.data.message);
                    
                    // Reload page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', response.data.message || 'Unknown error');
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', xhr, status, error);
                showNotification('error', '<?php _e('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'tpak-dq-system'); ?>: ' + error);
                button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    // Function to perform workflow action
    function performWorkflowAction(postId, action, comment) {
        var button = $('.workflow-action-btn[data-action="' + action + '"]');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('กำลังดำเนินการ...', 'tpak-dq-system'); ?>');
        
        var ajaxAction = '';
        switch(action) {
            case 'approve_a':
                ajaxAction = 'tpak_approve_batch';
                break;
            case 'approve_batch_supervisor':
                ajaxAction = 'tpak_approve_batch_supervisor';
                break;
            case 'reject_b':
            case 'reject_c':
                ajaxAction = 'tpak_reject_batch';
                break;
            case 'finalize':
                ajaxAction = 'tpak_finalize_batch';
                break;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: ajaxAction,
                post_id: postId,
                comment: comment,
                nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    
                    // Reload page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', response.data.message);
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showNotification('error', '<?php _e('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'tpak-dq-system'); ?>');
                button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    // Function to show notifications
    function showNotification(type, message) {
        var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap').prepend(notification);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    }
    
    // Helper functions for search highlighting
    function highlightText(container, searchTerm) {
        removeHighlight(container);
        
        container.find('*').contents().filter(function() {
            return this.nodeType === 3; // Text nodes only
        }).each(function() {
            var text = $(this).text();
            var regex = new RegExp('(' + escapeRegExp(searchTerm) + ')', 'gi');
            if (regex.test(text)) {
                var highlightedText = text.replace(regex, '<span class="highlight">$1</span>');
                $(this).replaceWith(highlightedText);
            }
        });
    }
    
    function removeHighlight(container) {
        container.find('.highlight').each(function() {
            $(this).replaceWith($(this).text());
        });
    }
    
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // Initialize the page after all variables are ready
    initializeStatusChange();
});

// Status change functionality
function initializeStatusChange() {
    jQuery(document).ready(function($) {
        console.log('Initializing status change functionality');
        
        // Admin status change
        $(document).on('click', '.admin-change-status', function() {
            console.log('Admin change status button clicked');
            
            var button = $(this);
            var postId = button.data('id');
            var newStatus = $('#status-select').val();
            var comment = $('#admin-comment').val();
            
            console.log('Post ID:', postId, 'New Status:', newStatus, 'Comment:', comment);
            
            if (!newStatus) {
                alert('<?php _e('กรุณาเลือกสถานะใหม่', 'tpak-dq-system'); ?>');
                return;
            }
            
            if (confirm('<?php _e('คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะ?', 'tpak-dq-system'); ?>')) {
                changeStatus(postId, newStatus, comment, 'admin_change');
            }
        });
        
        // Workflow action buttons
        $(document).on('click', '.workflow-action-btn', function() {
            var button = $(this);
            var postId = button.data('id');
            var action = button.data('action');
            
            console.log('Workflow action clicked:', action, 'for post:', postId);
            
            // Show comment section for reject actions
            if (action.indexOf('reject') !== -1) {
                $('.comment-section').show();
                $('#action-comment').focus();
                
                // Change button text to confirm
                button.text('<?php _e('ยืนยันการส่งกลับ', 'tpak-dq-system'); ?>');
                button.off('click').on('click', function() {
                    var comment = $('#action-comment').val().trim();
                    if (!comment) {
                        alert('<?php _e('กรุณากรอกความคิดเห็นสำหรับการส่งกลับ', 'tpak-dq-system'); ?>');
                        return;
                    }
                    
                    if (confirm('<?php _e('คุณแน่ใจหรือไม่ที่จะส่งกลับ?', 'tpak-dq-system'); ?>')) {
                        performWorkflowAction(postId, action, comment);
                    }
                });
            } else {
                // For approve actions
                if (confirm('<?php _e('คุณแน่ใจหรือไม่ที่จะดำเนินการนี้?', 'tpak-dq-system'); ?>')) {
                    performWorkflowAction(postId, action, '');
                }
            }
        });
    });
}

// Function to change status (admin)
function changeStatus(postId, newStatus, comment, actionType) {
    console.log('changeStatus called with:', postId, newStatus, comment, actionType);
    
    var $ = jQuery;
    var button = $('.admin-change-status');
    var originalText = button.html();
    
    button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('กำลังเปลี่ยน...', 'tpak-dq-system'); ?>');
    
    var requestData = {
        action: 'tpak_admin_change_status',
        post_id: postId,
        new_status: newStatus,
        comment: comment,
        nonce: window.tpak_dq_ajax.nonce
    };
    
    console.log('AJAX request data:', requestData);
    console.log('AJAX URL:', window.ajaxurl);
    
    $.ajax({
        url: window.ajaxurl,
        type: 'POST',
        data: requestData,
        success: function(response) {
            console.log('AJAX success response:', response);
            
            if (response.success) {
                // Show success message
                showNotification('success', response.data.message);
                
                // Reload page to show updated status
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', response.data.message || 'Unknown error');
                button.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX error:', xhr, status, error);
            showNotification('error', '<?php _e('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'tpak-dq-system'); ?>: ' + error);
            button.prop('disabled', false).html(originalText);
        }
    });
}

// Function to perform workflow action
function performWorkflowAction(postId, action, comment) {
    var $ = jQuery;
    var button = $('.workflow-action-btn[data-action="' + action + '"]');
    var originalText = button.html();
    
    button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('กำลังดำเนินการ...', 'tpak-dq-system'); ?>');
    
    var ajaxAction = '';
    switch(action) {
        case 'approve_a':
            ajaxAction = 'tpak_approve_batch';
            break;
        case 'approve_batch_supervisor':
            ajaxAction = 'tpak_approve_batch_supervisor';
            break;
        case 'reject_b':
        case 'reject_c':
            ajaxAction = 'tpak_reject_batch';
            break;
        case 'finalize':
            ajaxAction = 'tpak_finalize_batch';
            break;
    }
    
    $.ajax({
        url: window.ajaxurl,
        type: 'POST',
        data: {
            action: ajaxAction,
            post_id: postId,
            comment: comment,
            nonce: window.tpak_dq_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                showNotification('success', response.data.message);
                
                // Reload page to show updated status
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', response.data.message);
                button.prop('disabled', false).html(originalText);
            }
        },
        error: function() {
            showNotification('error', '<?php _e('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'tpak-dq-system'); ?>');
            button.prop('disabled', false).html(originalText);
        }
    });
}

// Function to show notifications
function showNotification(type, message) {
    var $ = jQuery;
    var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
    var notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
    
    $('.wrap').prepend(notification);
    
    // Auto dismiss after 5 seconds
    setTimeout(function() {
        notification.fadeOut();
    }, 5000);
}

// Wait for variables to be available
jQuery(document).ready(function($) {
    // Ensure ajaxurl is available
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    
    // Ensure tpak_dq_ajax is available
    if (typeof window.tpak_dq_ajax === 'undefined') {
        window.tpak_dq_ajax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
        };
    }
    
    console.log('TPAK DQ: Variables initialized');
    console.log('ajaxurl:', window.ajaxurl);
    console.log('tpak_dq_ajax:', window.tpak_dq_ajax);(this).val().toLowerCase();
        
        if (searchTerm === '') {
            $('.question-section').removeClass('filtered-out');
            $('.highlight').contents().unwrap();
            return;
        }
        
        $('.question-section').each(function() {
            var section = $(this);
            var text = section.text().toLowerCase();
            
            if (text.indexOf(searchTerm) > -1) {
                section.removeClass('filtered-out');
                // Highlight matching text
                highlightText(section, searchTerm);
            } else {
                section.addClass('filtered-out');
            }
        });
    });
    
    // Quick navigation
    $('.nav-item').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        var section = $('.question-section[data-question="' + target + '"]');
        
        if (section.length) {
            // Scroll to section
            $('html, body').animate({
                scrollTop: section.offset().top - 100
            }, 500);
            
            // Expand section if collapsed
            section.removeClass('collapsed');
            section.find('.toggle-section').attr('aria-expanded', 'true');
            
            // Highlight navigation
            $('.nav-item').removeClass('active');
            $(this).addClass('active');
        }
    });
    
    // Verification actions
    $('.approve-btn, .reject-btn').on('click', function() {
        var button = $(this);
        var id = button.data('id');
        var action = button.data('action');
        var confirmMsg = action.includes('approve') ? 
            'คุณต้องการอนุมัติข้อมูลนี้หรือไม่?' : 
            'คุณต้องการส่งกลับข้อมูลนี้หรือไม่?';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Add note if rejecting
        var note = '';
        if (action === 'reject') {
            note = prompt('กรุณาระบุเหตุผล:');
            if (!note) {
                alert('กรุณาระบุเหตุผลในการส่งกลับ');
                return;
            }
        }
        
        // Disable button and show loading
        button.prop('disabled', true).text('กำลังดำเนินการ...');
        
        // Make AJAX call to update status
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tpak_update_verification_status',
                nonce: '<?php echo wp_create_nonce("tpak_verification"); ?>',
                batch_id: id,
                verification_action: action,
                note: note
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + response.data.message);
                    button.prop('disabled', false).text(button.text());
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                button.prop('disabled', false).text(button.text());
            }
        });
    });
    
    // Export to Excel
    $('.export-excel').on('click', function() {
        var id = $(this).data('id');
        window.location.href = ajaxurl + '?action=tpak_export_response&id=' + id + '&nonce=<?php echo wp_create_nonce("tpak_export"); ?>';
    });
    
    // Helper function to highlight text
    function highlightText(element, searchTerm) {
        // Remove existing highlights
        element.find('.highlight').contents().unwrap();
        
        // Add new highlights
        var regex = new RegExp('(' + searchTerm + ')', 'gi');
        element.find('.answer-value, .sub-question-value, .question-text').each(function() {
            var text = $(this).text();
            var highlighted = text.replace(regex, '<span class="highlight">$1</span>');
            if (highlighted !== text) {
                $(this).html(highlighted);
            }
        });
    }
});
</script>