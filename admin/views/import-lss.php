<?php
/**
 * TPAK DQ System - Import LSS Structure Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle file upload
$upload_message = '';
$upload_success = false;

if (isset($_POST['upload_lss']) && isset($_FILES['lss_file'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'upload_lss_nonce')) {
        $upload_message = 'Security check failed.';
    } else {
        $file = $_FILES['lss_file'];
        
        // Check file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_message = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
        } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'lss') {
            $upload_message = 'กรุณาเลือกไฟล์ .lss เท่านั้น';
        } else {
            // Process the file
            require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-lss-parser.php';
            
            $parser = TPAK_LSS_Parser::getInstance();
            $result = $parser->parse_lss_file($file['tmp_name']);
            
            if ($result['success']) {
                // Save to database
                $saved = $parser->save_to_database($result);
                
                if ($saved) {
                    $upload_success = true;
                    $upload_message = 'อัพโหลดและนำเข้าข้อมูลเรียบร้อยแล้ว';
                    $survey_info = $result['survey_info'];
                    $statistics = $result['statistics'];
                } else {
                    $upload_message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
                }
            } else {
                $upload_message = 'เกิดข้อผิดพลาด: ' . $result['message'];
            }
        }
    }
}

// Get existing structures
require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-lss-parser.php';
$parser = TPAK_LSS_Parser::getInstance();
$existing_structures = $parser->get_available_structures();
?>

<div class="wrap">
    <h1>📤 นำเข้าโครงสร้างแบบสอบถาม (.lss)</h1>
    
    <?php if (!empty($upload_message)): ?>
        <div class="notice <?php echo $upload_success ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><?php echo esc_html($upload_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($upload_success && isset($survey_info)): ?>
        <div class="notice notice-info">
            <h3>ข้อมูลที่นำเข้า:</h3>
            <ul>
                <li><strong>Survey ID:</strong> <?php echo esc_html($survey_info['survey_id']); ?></li>
                <li><strong>ชื่อแบบสอบถาม:</strong> <?php echo esc_html($survey_info['title']); ?></li>
                <li><strong>ภาษา:</strong> <?php echo esc_html($survey_info['language']); ?></li>
                <li><strong>จำนวนคำถาม:</strong> <?php echo esc_html($statistics['total_questions']); ?> คำถาม</li>
                <li><strong>จำนวนกลุ่ม:</strong> <?php echo esc_html($statistics['total_groups']); ?> กลุ่ม</li>
                <li><strong>ความสมบูรณ์:</strong> <?php echo esc_html($statistics['completion_rate']); ?>%</li>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>📂 อัพโหลดไฟล์ LSS</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('upload_lss_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="lss_file">ไฟล์ .lss</label>
                    </th>
                    <td>
                        <input type="file" name="lss_file" id="lss_file" accept=".lss" required>
                        <p class="description">
                            เลือกไฟล์ .lss ที่ Export จาก LimeSurvey (ต้องเป็นไฟล์ Survey structure เท่านั้น ไม่ใช่ Response data)
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="upload_lss" class="button-primary" value="อัพโหลดและนำเข้า">
            </p>
        </form>
    </div>

    <div class="card">
        <h2>📋 โครงสร้างที่นำเข้าแล้ว</h2>
        
        <?php if (empty($existing_structures)): ?>
            <p>ยังไม่มีโครงสร้างที่นำเข้า</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Survey ID</th>
                        <th>ชื่อแบบสอบถาม</th>
                        <th>คำอธิบาย</th>
                        <th>คำถาม</th>
                        <th>กลุ่ม</th>
                        <th>ภาษา</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existing_structures as $survey_id => $structure): ?>
                        <tr>
                            <td><strong><?php echo esc_html($survey_id); ?></strong></td>
                            <td><?php echo esc_html($structure['title']); ?></td>
                            <td><?php echo esc_html($structure['description']); ?></td>
                            <td><?php echo esc_html($structure['questions_count']); ?></td>
                            <td><?php echo esc_html($structure['groups_count']); ?></td>
                            <td><?php echo esc_html($structure['language']); ?></td>
                            <td>
                                <a href="?page=tpak-dq-system-responses&survey_id=<?php echo esc_attr($survey_id); ?>" 
                                   class="button button-small">
                                    ดูข้อมูล
                                </a>
                                <a href="?page=tpak-dq-system-import-lss&action=delete&survey_id=<?php echo esc_attr($survey_id); ?>" 
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('คุณต้องการลบโครงสร้างนี้หรือไม่?')">
                                    ลบ
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>ℹ️ คำแนะนำ</h2>
        <h3>วิธีการ Export ไฟล์ .lss จาก LimeSurvey:</h3>
        <ol>
            <li>เข้าสู่ LimeSurvey Admin Panel</li>
            <li>เลือกแบบสอบถามที่ต้องการ</li>
            <li>ไปที่ <strong>Settings → Export → Survey structure (.lss)</strong></li>
            <li>เลือก <strong>"Survey structure only"</strong> (ไม่เลือก Response data)</li>
            <li>กด <strong>Export survey</strong></li>
            <li>ดาวน์โหลดไฟล์ .lss</li>
        </ol>

        <h3>ประโยชน์ของการนำเข้าโครงสร้าง:</h3>
        <ul>
            <li>✅ แสดงคำถามภาษาไทยแทน field codes</li>
            <li>✅ แสดงคำตอบที่ถูกต้องแทนรหัส</li>
            <li>✅ จัดกลุ่มคำถามตามโครงสร้างเดิม</li>
            <li>✅ เพิ่มความเร็วในการประมวลผล (ไม่ต้องเรียก API ทุกครั้ง)</li>
            <li>✅ รองรับ Survey ที่มีโครงสร้างซับซ้อน</li>
        </ul>

        <div class="notice notice-warning inline">
            <p><strong>หมายเหตุ:</strong> การนำเข้าโครงสร้างจะไม่ส่งผลต่อข้อมูล Response ที่มีอยู่แล้ว 
            และสามารถนำเข้าโครงสร้างใหม่ทับเดิมได้</p>
        </div>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.card h2 {
    margin-top: 0;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.notice.inline {
    margin: 15px 0;
    padding: 10px 15px;
}
</style>