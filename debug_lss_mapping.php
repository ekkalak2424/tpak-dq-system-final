<?php
/**
 * Debug LSS Mapping
 * 
 * ตรวจสอบการทำงานของระบบ mapping หลัง import LSS
 */

// WordPress bootstrap
require_once(dirname(__FILE__) . '/../../../wp-config.php');

$survey_id = '836511'; // Survey ID ที่ import LSS แล้ว

echo "<h1>🔍 Debug LSS Mapping สำหรับ Survey ID: {$survey_id}</h1>";

// 1. ตรวจสอบว่า LSS structure บันทึกแล้วหรือไม่
echo "<h2>1. ตรวจสอบ LSS Structure</h2>";
$lss_structure = get_option('tpak_lss_structure_' . $survey_id, false);
if ($lss_structure) {
    echo "✅ LSS Structure พบแล้ว<br>";
    echo "📊 จำนวนคำถาม: " . count($lss_structure['questions']) . "<br>";
    echo "📊 จำนวน Question Texts: " . count($lss_structure['question_texts']) . "<br>";
    echo "📊 จำนวนกลุ่ม: " . count($lss_structure['groups']) . "<br>";
    
    // แสดงตัวอย่างคำถาม 5 ข้อแรก
    echo "<h3>ตัวอย่างคำถาม:</h3>";
    $count = 0;
    foreach ($lss_structure['questions'] as $qid => $question) {
        if ($count >= 5) break;
        $question_text = isset($lss_structure['question_texts'][$qid]['question']) 
            ? $lss_structure['question_texts'][$qid]['question'] 
            : $question['title'];
        echo "• QID: {$qid}, Title: {$question['title']}, Text: " . substr($question_text, 0, 100) . "...<br>";
        $count++;
    }
} else {
    echo "❌ LSS Structure ไม่พบ - กรุณา import ไฟล์ .lss ก่อน<br>";
}

// 2. ตรวจสอบ Question Mappings
echo "<h2>2. ตรวจสอบ Question Mappings</h2>";
$mappings = get_option('tpak_question_mappings_' . $survey_id, false);
if ($mappings) {
    echo "✅ Question Mappings พบแล้ว<br>";
    echo "📊 จำนวน Question Mappings: " . count($mappings['questions']) . "<br>";
    echo "📊 จำนวน Answer Mappings: " . count($mappings['answers']) . "<br>";
    
    // แสดงตัวอย่าง mappings
    echo "<h3>ตัวอย่าง Question Mappings:</h3>";
    $count = 0;
    foreach ($mappings['questions'] as $key => $text) {
        if ($count >= 5) break;
        echo "• {$key} → " . substr($text, 0, 100) . "...<br>";
        $count++;
    }
} else {
    echo "❌ Question Mappings ไม่พบ<br>";
}

// 3. ทดสอบ Question Mapper
echo "<h2>3. ทดสอบ Question Mapper</h2>";
require_once(dirname(__FILE__) . '/includes/class-question-mapper.php');

$question_mapper = TPAK_Advanced_Question_Mapper::getInstance();

// ทดสอบกับ field keys ตัวอย่าง
$test_fields = array(
    'CONSENT' => 'Y',
    'Q1' => '25',
    'PA1TT2[1]' => '1',
    'AGE' => '30'
);

echo "<h3>ทดสอบการ mapping:</h3>";
foreach ($test_fields as $field_key => $value) {
    echo "<strong>Field: {$field_key}</strong><br>";
    
    $response_mapping = $question_mapper->getResponseMapping(array($field_key => $value), $survey_id);
    
    if (isset($response_mapping['questions'][$field_key])) {
        $question = $response_mapping['questions'][$field_key];
        echo "✅ Display Name: {$question['display_name']}<br>";
        echo "📊 Confidence: {$question['confidence']}<br>";
        echo "🏷️ Category: {$question['category']}<br>";
        echo "💬 Formatted Value: {$question['formatted_value']}<br>";
    } else {
        echo "❌ ไม่พบการ mapping<br>";
    }
    echo "<br>";
}

// 4. ทดสอบ Survey Adapter
echo "<h2>4. ทดสอบ Survey Adapter</h2>";
require_once(dirname(__FILE__) . '/includes/class-survey-adapter.php');

$survey_adapter = TPAK_Survey_Adapter::getInstance();
$adapter_result = $survey_adapter->processResponse($survey_id, $test_fields);

if ($adapter_result) {
    echo "✅ Survey Adapter ทำงานสำเร็จ<br>";
    echo "📊 Structure Type: {$adapter_result['structure_type']}<br>";
    echo "📊 Confidence: {$adapter_result['confidence']}<br>";
    echo "📊 จำนวนคำถาม: " . count($adapter_result['questions']) . "<br>";
    
    echo "<h3>ผลลัพธ์จาก Survey Adapter:</h3>";
    foreach ($adapter_result['questions'] as $key => $question) {
        echo "• {$key} → {$question['display_name']} (Confidence: {$question['confidence']})<br>";
    }
} else {
    echo "❌ Survey Adapter ไม่สามารถประมวลผลได้<br>";
}

// 5. ตรวจสอบ Cache
echo "<h2>5. ตรวจสอบ Cache</h2>";
$cache_key = 'survey_structure_' . $survey_id;
$cached = get_transient($cache_key);
if ($cached) {
    echo "✅ พบ Cache สำหรับ Survey Structure<br>";
    echo "📊 Structure Type: {$cached['structure_type']}<br>";
    echo "📊 จำนวนคำถาม: {$cached['question_count']}<br>";
} else {
    echo "ℹ️ ไม่พบ Cache (จะถูกสร้างเมื่อเรียกใช้งาน)<br>";
}

// 6. แนะนำการแก้ไข
echo "<h2>6. การแก้ไขปัญหา</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa;'>";
echo "<h3>หากยังไม่แสดงข้อมูลภาษาไทย:</h3>";
echo "1. ลบ Cache: <code>delete_transient('survey_structure_{$survey_id}');</code><br>";
echo "2. ตรวจสอบว่า import LSS เสร็จสมบูรณ์<br>";
echo "3. Refresh หน้ารายละเอียดแบบสอบถาม<br>";
echo "4. ตรวจสอบ Error Logs ใน WordPress<br>";
echo "</div>";

echo "<h2>7. ลิงก์ที่เป็นประโยชน์</h2>";
echo "<a href='/wp-admin/admin.php?page=tpak-dq-system-import-lss'>📂 หน้านำเข้าโครงสร้าง</a><br>";
echo "<a href='/wp-admin/admin.php?page=tpak-dq-responses'>📋 ข้อมูลแบบสอบถาม</a><br>";

// 7. ปุ่มลบ Cache
if (isset($_GET['clear_cache'])) {
    delete_transient($cache_key);
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; color: #155724; margin: 10px 0;'>";
    echo "✅ ลบ Cache เรียบร้อยแล้ว";
    echo "</div>";
}

echo "<br><a href='?clear_cache=1' onclick='return confirm(\"คุณต้องการลบ Cache หรือไม่?\")' style='background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>🗑️ ลบ Cache</a>";
?>