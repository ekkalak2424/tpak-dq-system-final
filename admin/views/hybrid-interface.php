<?php
/**
 * Hybrid System Interface View
 * หน้าจอสำหรับใช้งานระบบ Hybrid
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get survey and response IDs from URL or settings
$survey_id = isset($_GET['survey_id']) ? sanitize_text_field($_GET['survey_id']) : '836511';
$response_id = isset($_GET['response_id']) ? sanitize_text_field($_GET['response_id']) : '';
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

// Get hybrid system instance
$hybrid = TPAK_LimeSurvey_Hybrid_System::getInstance();

// Get existing responses if any
$existing_responses = $hybrid->get_survey_responses($survey_id);

?>

<div class="wrap tpak-hybrid-system">
    <h1>🎯 LimeSurvey Hybrid System</h1>
    <p class="description">ระบบผสมระหว่าง iframe display และ API data management</p>
    
    <!-- Notifications -->
    <div id="hybrid-notifications"></div>
    <div id="hybrid-loading" style="display: none;"></div>
    
    <!-- Step 1: Configuration -->
    <div class="hybrid-section">
        <h2>📋 ขั้นตอนที่ 1: ตั้งค่า</h2>
        <div class="hybrid-config-panel">
            <table class="form-table">
                <tr>
                    <th>Survey ID:</th>
                    <td>
                        <input type="text" id="hybrid-survey-id" value="<?php echo esc_attr($survey_id); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th>Response ID (ถ้ามี):</th>
                    <td>
                        <input type="text" id="hybrid-response-id" value="<?php echo esc_attr($response_id); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th>Token (ถ้ามี):</th>
                    <td>
                        <input type="text" id="hybrid-token" value="<?php echo esc_attr($token); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Step 2: Display Survey -->
    <div class="hybrid-section">
        <h2>📱 ขั้นตอนที่ 2: แสดงแบบสอบถาม</h2>
        
        <div class="hybrid-controls">
            <button class="button button-primary button-hero hybrid-load-survey" 
                    data-survey-id="<?php echo esc_attr($survey_id); ?>"
                    data-token="<?php echo esc_attr($token); ?>">
                🚀 โหลดแบบสอบถามใน Iframe
            </button>
            
            <button class="button button-secondary" onclick="window.open('https://survey.tpak.or.th/index.php/<?php echo $survey_id; ?>', '_blank')">
                🔗 เปิดในหน้าต่างใหม่
            </button>
        </div>
        
        <!-- Iframe container -->
        <div id="hybrid-iframe-container" class="hybrid-iframe-section"></div>
        
        <!-- Completion message -->
        <div id="hybrid-completion-message"></div>
    </div>
    
    <!-- Step 3: Fetch Data -->
    <div class="hybrid-section hybrid-fetch-controls" style="display: none;">
        <h2>📥 ขั้นตอนที่ 3: ดึงข้อมูลจาก LimeSurvey</h2>
        
        <div class="hybrid-controls">
            <button class="button button-primary hybrid-fetch-response">
                📥 ดึงข้อมูลผ่าน API
            </button>
            
            <span class="description">
                คลิกเพื่อดึงข้อมูลที่กรอกใน LimeSurvey มาเก็บใน WordPress
            </span>
        </div>
    </div>
    
    <!-- Step 4: Display & Edit Data -->
    <div class="hybrid-section">
        <h2>✏️ ขั้นตอนที่ 4: แสดงและแก้ไขข้อมูล</h2>
        
        <!-- Response display container -->
        <div id="hybrid-response-container"></div>
        
        <!-- Edit controls -->
        <div class="hybrid-edit-controls" style="display: none;">
            <div class="hybrid-save-status"></div>
            
            <button class="button button-primary hybrid-save-response">
                💾 บันทึกการแก้ไข
            </button>
            
            <button class="button hybrid-toggle-edit">
                ✏️ โหมดแก้ไขแบบ Inline
            </button>
            
            <button class="button button-secondary hybrid-sync-back" 
                    data-response-id="<?php echo esc_attr($response_id); ?>">
                🔄 Sync กลับไป LimeSurvey
            </button>
        </div>
    </div>
    
    <!-- Step 5: Existing Responses -->
    <?php if (!empty($existing_responses)): ?>
    <div class="hybrid-section">
        <h2>📊 ข้อมูลที่บันทึกไว้แล้ว</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Survey ID</th>
                    <th>Response ID</th>
                    <th>Token</th>
                    <th>สถานะ</th>
                    <th>แก้ไขล่าสุด</th>
                    <th>การกระทำ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($existing_responses as $response): ?>
                <tr>
                    <td><?php echo $response->id; ?></td>
                    <td><?php echo $response->survey_id; ?></td>
                    <td><?php echo $response->response_id ?: '-'; ?></td>
                    <td><?php echo $response->token ?: '-'; ?></td>
                    <td>
                        <?php
                        $status_class = '';
                        $status_text = '';
                        switch ($response->status) {
                            case 'draft':
                                $status_class = 'status-draft';
                                $status_text = '📝 แก้ไขแล้ว';
                                break;
                            case 'completed':
                                $status_class = 'status-completed';
                                $status_text = '✅ สมบูรณ์';
                                break;
                            case 'synced':
                                $status_class = 'status-synced';
                                $status_text = '🔄 Synced';
                                break;
                        }
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td><?php echo $response->modified_at; ?></td>
                    <td>
                        <button class="button button-small hybrid-load-saved" 
                                data-response-id="<?php echo $response->id; ?>">
                            👁️ ดู
                        </button>
                        <button class="button button-small hybrid-edit-saved" 
                                data-response-id="<?php echo $response->id; ?>">
                            ✏️ แก้ไข
                        </button>
                        <?php if ($response->status === 'draft'): ?>
                        <button class="button button-small hybrid-sync-back" 
                                data-response-id="<?php echo $response->id; ?>">
                            🔄 Sync
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Instructions -->
    <div class="hybrid-section">
        <h2>📖 วิธีใช้งาน</h2>
        <ol>
            <li><strong>โหลดแบบสอบถาม:</strong> คลิก "โหลดแบบสอบถามใน Iframe" เพื่อแสดงฟอร์ม</li>
            <li><strong>กรอกข้อมูล:</strong> กรอกแบบสอบถามใน iframe หรือเปิดในหน้าต่างใหม่</li>
            <li><strong>ดึงข้อมูล:</strong> หลังกรอกเสร็จ คลิก "ดึงข้อมูลผ่าน API"</li>
            <li><strong>แก้ไข:</strong> แก้ไขข้อมูลใน WordPress ได้ทันที</li>
            <li><strong>Sync กลับ:</strong> ส่งข้อมูลที่แก้ไขกลับไป LimeSurvey</li>
        </ol>
        
        <div class="hybrid-info-box">
            <h3>💡 ข้อดีของระบบ Hybrid:</h3>
            <ul>
                <li>✅ ไม่มีปัญหา CORS - ใช้ PHP API</li>
                <li>✅ แสดง UI ต้นฉบับของ LimeSurvey</li>
                <li>✅ แก้ไขข้อมูลใน WordPress ได้</li>
                <li>✅ Sync 2 ทาง (WordPress ↔️ LimeSurvey)</li>
                <li>✅ Backup อัตโนมัติใน WordPress</li>
            </ul>
        </div>
    </div>
</div>

<style>
.tpak-hybrid-system {
    max-width: 1200px;
}

.hybrid-section {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.hybrid-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9e9e9;
}

.hybrid-controls {
    margin: 20px 0;
}

.hybrid-controls button {
    margin-right: 10px;
}

.hybrid-iframe-section {
    margin: 20px 0;
}

.hybrid-iframe-wrapper {
    border: 2px solid #e9e9e9;
    border-radius: 8px;
    overflow: hidden;
}

.iframe-header {
    background: #f9f9f9;
    padding: 10px 15px;
    border-bottom: 1px solid #e9e9e9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.iframe-status {
    font-weight: bold;
}

.hybrid-response-display {
    margin: 20px 0;
}

.hybrid-response-display table {
    margin-top: 15px;
}

.hybrid-response-display.edit-mode {
    background: #fff9e6;
    padding: 15px;
    border: 2px solid #ffc107;
    border-radius: 8px;
}

.hybrid-response-display.unsaved {
    border-color: #dc3545;
}

.hybrid-inline-edit {
    width: 100%;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.status-draft {
    background: #fff3cd;
    color: #856404;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-synced {
    background: #d1ecf1;
    color: #0c5460;
}

.hybrid-info-box {
    background: #e7f3ff;
    border: 1px solid #0073aa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
}

.hybrid-info-box h3 {
    margin-top: 0;
    color: #0073aa;
}

.hybrid-config-panel {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

.hybrid-save-status {
    display: inline-block;
    margin-right: 15px;
    font-weight: bold;
}

#hybrid-notifications {
    margin: 20px 0;
}

.button-hero {
    font-size: 16px !important;
    line-height: 1.4 !important;
    padding: 12px 30px !important;
}
</style>

<script>
// Pass PHP variables to JavaScript
window.tpakSurveyId = '<?php echo esc_js($survey_id); ?>';
window.tpakResponseId = '<?php echo esc_js($response_id); ?>';
window.tpakToken = '<?php echo esc_js($token); ?>';
</script>