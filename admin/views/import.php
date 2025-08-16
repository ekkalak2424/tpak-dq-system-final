<?php
/**
 * TPAK DQ System - Import View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('TPAK DQ System - Import Data', 'tpak-dq-system'); ?></h1>
    
    <div class="tpak-import-page">
        <!-- Manual Import Section -->
        <div class="tpak-import-section">
            <h2><?php _e('นำเข้าข้อมูลด้วยตนเอง', 'tpak-dq-system'); ?></h2>
            
            <!-- Configuration Status -->
            <div class="tpak-config-status" style="margin-bottom: 20px; padding: 15px; background: #e2e3e5; border-left: 4px solid #6c757d;">
                <h4><?php _e('สถานะการตั้งค่า', 'tpak-dq-system'); ?></h4>
                <ul>
                    <li><strong><?php _e('LimeSurvey URL:', 'tpak-dq-system'); ?></strong> 
                        <?php echo !empty($options['limesurvey_url']) ? '✓ ' . esc_html($options['limesurvey_url']) : '✗ ' . __('ไม่ระบุ', 'tpak-dq-system'); ?>
                    </li>
                    <li><strong><?php _e('Username:', 'tpak-dq-system'); ?></strong> 
                        <?php echo !empty($options['limesurvey_username']) ? '✓ ' . esc_html($options['limesurvey_username']) : '✗ ' . __('ไม่ระบุ', 'tpak-dq-system'); ?>
                    </li>
                    <li><strong><?php _e('Password:', 'tpak-dq-system'); ?></strong> 
                        <?php echo !empty($options['limesurvey_password']) ? '✓ ' . __('ตั้งค่าแล้ว', 'tpak-dq-system') : '✗ ' . __('ไม่ระบุ', 'tpak-dq-system'); ?>
                    </li>
                    <li><strong><?php _e('Survey ID:', 'tpak-dq-system'); ?></strong> 
                        <?php if (!empty($options['survey_id'])): ?>
                            ✓ <?php echo esc_html($options['survey_id']); ?>
                        <?php else: ?>
                            <span style="color: #dc3545;">✗ <?php _e('ไม่ระบุ - กรุณาตั้งค่าในหน้า Settings', 'tpak-dq-system'); ?></span>
                        <?php endif; ?>
                    </li>
                </ul>
                <?php if (empty($options['survey_id'])): ?>
                    <p style="margin-top: 10px; color: #dc3545;">
                        <strong><?php _e('หมายเหตุ:', 'tpak-dq-system'); ?></strong> 
                        <?php _e('กรุณาไปที่หน้า Settings เพื่อตั้งค่า Survey ID ก่อนการนำเข้าข้อมูล', 'tpak-dq-system'); ?>
                    </p>
                    <p style="margin-top: 10px;">
                        <a href="<?php echo admin_url('admin.php?page=tpak-dq-settings'); ?>" class="button button-secondary">
                            <?php _e('ไปที่หน้า Settings', 'tpak-dq-system'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Auto Structure Detection Section -->
            <div class="tpak-auto-detection" style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
                <h3>🔍 ระบบตรวจจับโครงสร้างอัตโนมัติ</h3>
                <p>ระบุ Survey ID แล้วระบบจะตรวจหาไฟล์ .lss และวิเคราะห์โครงสร้างอัตโนมัติ</p>
                
                <div class="auto-detection-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Survey ID', 'tpak-dq-system'); ?></th>
                            <td>
                                <input type="number" 
                                       id="auto_detect_survey_id" 
                                       name="auto_detect_survey_id" 
                                       value="<?php echo esc_attr($options['survey_id'] ?? ''); ?>" 
                                       class="regular-text" 
                                       placeholder="เช่น 836511" />
                                <button type="button" 
                                        id="btn_auto_detect" 
                                        class="button button-primary"
                                        style="margin-left: 10px;">
                                    🔍 ตรวจจับโครงสร้าง
                                </button>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Detection Results -->
                    <div id="detection_results" style="display: none; margin-top: 20px;">
                        <h4>ผลการตรวจจับ:</h4>
                        <div id="detection_content"></div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($result)): ?>
                <?php if ($result['success']): ?>
                    <div class="tpak-import-status success">
                        <h3><?php _e('นำเข้าข้อมูลสำเร็จ', 'tpak-dq-system'); ?></h3>
                        <p><?php echo esc_html($result['message']); ?></p>
                        <?php if (!empty($result['errors'])): ?>
                            <h4><?php _e('ข้อผิดพลาด:', 'tpak-dq-system'); ?></h4>
                            <ul>
                                <?php foreach ($result['errors'] as $error): ?>
                                    <li><?php echo esc_html($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="tpak-import-status error">
                        <h3><?php _e('นำเข้าข้อมูลล้มเหลว', 'tpak-dq-system'); ?></h3>
                        <p><?php echo esc_html($result['message']); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php
            // Debug: Log options in the view
            error_log('TPAK DQ System: Import view - Options: ' . print_r($options, true));
            error_log('TPAK DQ System: Import view - Survey ID: ' . (isset($options['survey_id']) ? $options['survey_id'] : 'NOT SET'));
            
            // Show survey information if survey ID is set
            if (!empty($options['survey_id'])) {
                $survey_id = $options['survey_id'];
                $date_range = $api_handler->get_response_date_range($survey_id);
                if ($date_range['success']) {
                    echo '<div class="tpak-import-info" style="margin-bottom: 20px; padding: 15px; background: #d4edda; border-left: 4px solid #28a745;">';
                    echo '<h4>' . __('ข้อมูลแบบสอบถาม', 'tpak-dq-system') . '</h4>';
                    echo '<p><strong>' . __('Survey ID:', 'tpak-dq-system') . '</strong> ' . esc_html($survey_id) . '</p>';
                    echo '<p><strong>' . __('จำนวนการตอบกลับทั้งหมด:', 'tpak-dq-system') . '</strong> ' . esc_html($date_range['total_responses']) . '</p>';
                    echo '<p><strong>' . __('ช่วงวันที่ที่มีข้อมูล:', 'tpak-dq-system') . '</strong> ' . esc_html($date_range['date_range']) . '</p>';
                    echo '<p><strong>' . __('วันที่เริ่มต้น:', 'tpak-dq-system') . '</strong> ' . esc_html($date_range['earliest_date']) . '</p>';
                    echo '<p><strong>' . __('วันที่สิ้นสุด:', 'tpak-dq-system') . '</strong> ' . esc_html($date_range['latest_date']) . '</p>';
                    echo '</div>';
                } else {
                    echo '<div class="tpak-import-info" style="margin-bottom: 20px; padding: 15px; background: #f8d7da; border-left: 4px solid #dc3545;">';
                    echo '<h4>' . __('ข้อมูลแบบสอบถาม', 'tpak-dq-system') . '</h4>';
                    echo '<p><strong>' . __('Survey ID:', 'tpak-dq-system') . '</strong> ' . esc_html($survey_id) . '</p>';
                    echo '<p style="color: #721c24;">' . esc_html($date_range['message']) . '</p>';
                    echo '</div>';
                }
            }
            ?>
            <form method="post" action="">
                <?php wp_nonce_field('tpak_manual_import'); ?>
                
                <div class="tpak-form-row">
                    <label for="survey_id_manual"><?php _e('Survey ID', 'tpak-dq-system'); ?> <span style="color: #dc3545;">*</span></label>
                    <input type="text" id="survey_id_manual" name="survey_id_manual" 
                           value="<?php echo esc_attr(isset($options['survey_id']) ? $options['survey_id'] : ''); ?>" 
                           class="regular-text" required />
                    <p class="description">
                        <?php _e('ID ของแบบสอบถามที่ต้องการนำเข้า (จำเป็น)', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <div style="margin-bottom: 10px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2196F3;">
                        <strong><?php _e('หมายเหตุ:', 'tpak-dq-system'); ?></strong> 
                        <?php _e('หากไม่ระบุช่วงวันที่ ระบบจะนำเข้าข้อมูลทั้งหมด หากระบุช่วงวันที่ ระบบจะนำเข้าข้อมูลเฉพาะในช่วงวันที่ที่ระบุเท่านั้น', 'tpak-dq-system'); ?>
                    </div>
                </div>
                
                <div class="tpak-form-row">
                    <label for="start_date"><?php _e('วันที่เริ่มต้น (ไม่บังคับ)', 'tpak-dq-system'); ?></label>
                    <input type="date" id="start_date" name="start_date" class="regular-text" value="2025-07-09" />
                    <p class="description">
                        <?php _e('นำเข้าข้อมูลตั้งแต่วันที่นี้ (รูปแบบ: YYYY-MM-DD)', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <label for="end_date"><?php _e('วันที่สิ้นสุด (ไม่บังคับ)', 'tpak-dq-system'); ?></label>
                    <input type="date" id="end_date" name="end_date" class="regular-text" value="2025-08-09" />
                    <p class="description">
                        <?php _e('นำเข้าข้อมูลจนถึงวันที่นี้ (รูปแบบ: YYYY-MM-DD)', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <button type="submit" name="manual_import" class="button button-primary" id="tpak-manual-import">
                        <?php _e('นำเข้าข้อมูล', 'tpak-dq-system'); ?>
                    </button>
                    <button type="submit" name="manual_import_no_date" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('นำเข้าข้อมูลทั้งหมด (ไม่ใช้ช่วงวันที่)', 'tpak-dq-system'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Data Structure Fix Section -->
        <div class="tpak-import-section">
            <h2><?php _e('แก้ไขโครงสร้างข้อมูล', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-import-info" style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h4><?php _e('คำอธิบาย', 'tpak-dq-system'); ?></h4>
                <p><?php _e('หากข้อมูล LimeSurvey ID แสดงผลไม่ถูกต้อง (แสดง Response ID แทน Survey ID) ให้ใช้เครื่องมือนี้เพื่อแก้ไขโครงสร้างข้อมูลที่มีอยู่', 'tpak-dq-system'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('tpak_fix_data_structure'); ?>
                
                <div class="tpak-form-row">
                    <label for="survey_id_fix"><?php _e('Survey ID สำหรับแก้ไข', 'tpak-dq-system'); ?></label>
                    <input type="text" id="survey_id_fix" name="survey_id_fix" 
                           value="<?php echo esc_attr($options['survey_id'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('ระบุ Survey ID ที่ถูกต้องเพื่อแก้ไขข้อมูลที่มีอยู่', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <button type="submit" name="fix_data_structure" class="button button-secondary" id="tpak-fix-data-structure">
                        <?php _e('แก้ไขโครงสร้างข้อมูล', 'tpak-dq-system'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Data Clear Section -->
        <div class="tpak-import-section">
            <h2><?php _e('เคลียร์ข้อมูล', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-import-info" style="margin-bottom: 20px; padding: 15px; background: #f8d7da; border-left: 4px solid #dc3545;">
                <h4><?php _e('คำเตือน', 'tpak-dq-system'); ?></h4>
                <p><strong><?php _e('การดำเนินการนี้จะลบข้อมูลทั้งหมดอย่างถาวรและไม่สามารถกู้คืนได้', 'tpak-dq-system'); ?></strong></p>
                <p><?php _e('กรุณาตรวจสอบและยืนยันการดำเนินการอย่างรอบคอบ', 'tpak-dq-system'); ?></p>
            </div>
            
            <?php
            // Get current data statistics
            $total_posts = wp_count_posts('verification_batch');
            $total_published = $total_posts->publish;
            $total_draft = $total_posts->draft;
            $total_trash = $total_posts->trash;
            $total_all = $total_published + $total_draft + $total_trash;
            ?>
            
            <div class="tpak-data-stats" style="margin-bottom: 20px; padding: 15px; background: #e2e3e5; border-left: 4px solid #6c757d;">
                <h4><?php _e('สถิติข้อมูลปัจจุบัน', 'tpak-dq-system'); ?></h4>
                <ul>
                    <li><strong><?php _e('ชุดข้อมูลทั้งหมด:', 'tpak-dq-system'); ?></strong> <?php echo number_format($total_all); ?> <?php _e('รายการ', 'tpak-dq-system'); ?></li>
                    <li><strong><?php _e('ชุดข้อมูลที่เผยแพร่:', 'tpak-dq-system'); ?></strong> <?php echo number_format($total_published); ?> <?php _e('รายการ', 'tpak-dq-system'); ?></li>
                    <li><strong><?php _e('ชุดข้อมูลแบบร่าง:', 'tpak-dq-system'); ?></strong> <?php echo number_format($total_draft); ?> <?php _e('รายการ', 'tpak-dq-system'); ?></li>
                    <li><strong><?php _e('ชุดข้อมูลในถังขยะ:', 'tpak-dq-system'); ?></strong> <?php echo number_format($total_trash); ?> <?php _e('รายการ', 'tpak-dq-system'); ?></li>
                </ul>
            </div>
            
            <form method="post" action="" id="tpak-clear-data-form">
                <?php wp_nonce_field('tpak_clear_data'); ?>
                
                <div class="tpak-form-row">
                    <label for="clear_type"><?php _e('ประเภทการเคลียร์', 'tpak-dq-system'); ?></label>
                    <select id="clear_type" name="clear_type" class="regular-text">
                        <option value="all"><?php _e('เคลียร์ข้อมูลทั้งหมด', 'tpak-dq-system'); ?></option>
                        <option value="by_survey"><?php _e('เคลียร์ข้อมูลตาม Survey ID', 'tpak-dq-system'); ?></option>
                        <option value="by_status"><?php _e('เคลียร์ข้อมูลตามสถานะ', 'tpak-dq-system'); ?></option>
                        <option value="by_date"><?php _e('เคลียร์ข้อมูลตามวันที่', 'tpak-dq-system'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('เลือกประเภทการเคลียร์ข้อมูลที่ต้องการ', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <!-- Survey ID Option -->
                <div class="tpak-form-row clear-option" id="clear-by-survey" style="display: none;">
                    <label for="clear_survey_id"><?php _e('Survey ID', 'tpak-dq-system'); ?></label>
                    <input type="text" id="clear_survey_id" name="clear_survey_id" 
                           value="<?php echo esc_attr($options['survey_id'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('ระบุ Survey ID ที่ต้องการเคลียร์ข้อมูล', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <!-- Status Option -->
                <div class="tpak-form-row clear-option" id="clear-by-status" style="display: none;">
                    <label for="clear_status"><?php _e('สถานะ', 'tpak-dq-system'); ?></label>
                    <select id="clear_status" name="clear_status" class="regular-text">
                        <option value="pending_a"><?php _e('รอตรวจสอบขั้นที่ 1', 'tpak-dq-system'); ?></option>
                        <option value="pending_b"><?php _e('รอตรวจสอบขั้นที่ 2', 'tpak-dq-system'); ?></option>
                        <option value="pending_c"><?php _e('รอตรวจสอบขั้นที่ 3', 'tpak-dq-system'); ?></option>
                        <option value="rejected_by_b"><?php _e('ถูกปฏิเสธโดยขั้นที่ 2', 'tpak-dq-system'); ?></option>
                        <option value="rejected_by_c"><?php _e('ถูกปฏิเสธโดยขั้นที่ 3', 'tpak-dq-system'); ?></option>
                        <option value="finalized"><?php _e('เสร็จสมบูรณ์', 'tpak-dq-system'); ?></option>
                        <option value="finalized_by_sampling"><?php _e('เสร็จสมบูรณ์โดยการสุ่มตัวอย่าง', 'tpak-dq-system'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('เลือกสถานะที่ต้องการเคลียร์ข้อมูล', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <!-- Date Range Option -->
                <div class="tpak-form-row clear-option" id="clear-by-date" style="display: none;">
                    <label for="clear_start_date"><?php _e('วันที่เริ่มต้น', 'tpak-dq-system'); ?></label>
                    <input type="date" id="clear_start_date" name="clear_start_date" class="regular-text" />
                    <p class="description">
                        <?php _e('เคลียร์ข้อมูลตั้งแต่วันที่นี้', 'tpak-dq-system'); ?>
                    </p>
                    
                    <label for="clear_end_date" style="margin-top: 10px; display: block;"><?php _e('วันที่สิ้นสุด', 'tpak-dq-system'); ?></label>
                    <input type="date" id="clear_end_date" name="clear_end_date" class="regular-text" />
                    <p class="description">
                        <?php _e('เคลียร์ข้อมูลจนถึงวันที่นี้', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <label for="clear_confirmation">
                        <input type="checkbox" id="clear_confirmation" name="clear_confirmation" value="1" />
                        <?php _e('ฉันเข้าใจว่าการดำเนินการนี้จะลบข้อมูลอย่างถาวรและไม่สามารถกู้คืนได้', 'tpak-dq-system'); ?>
                    </label>
                </div>
                
                <div class="tpak-form-row">
                    <button type="submit" name="clear_data" class="button button-danger" id="tpak-clear-data" disabled>
                        <?php _e('เคลียร์ข้อมูล', 'tpak-dq-system'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- API Status Section -->
        <div class="tpak-import-section">
            <h2><?php _e('สถานะการเชื่อมต่อ API', 'tpak-dq-system'); ?></h2>
            
            <?php if ($api_handler->is_configured()): ?>
                <?php if ($api_handler->test_connection()): ?>
                    <div class="tpak-import-status success">
                        <h3><?php _e('เชื่อมต่อสำเร็จ', 'tpak-dq-system'); ?></h3>
                        <p><?php _e('สามารถเชื่อมต่อกับ LimeSurvey API ได้', 'tpak-dq-system'); ?></p>
                    </div>
                    
                    <!-- Available Surveys -->
                    <div class="tpak-surveys-list">
                        <h3><?php _e('แบบสอบถามที่มีอยู่', 'tpak-dq-system'); ?></h3>
                        
                        <div class="tpak-import-info" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                            <h4><?php _e('คำอธิบายการนำเข้า', 'tpak-dq-system'); ?></h4>
                            <ul style="margin: 10px 0;">
                                <li><strong><?php _e('นำเข้าแบบเต็ม:', 'tpak-dq-system'); ?></strong> <?php _e('นำเข้าข้อมูลทั้งหมดจาก LimeSurvey พร้อมข้อมูลครบถ้วน เหมาะสำหรับข้อมูลขนาดเล็ก', 'tpak-dq-system'); ?></li>
                                <li><strong><?php _e('นำเข้าข้อมูลดิบ:', 'tpak-dq-system'); ?></strong> <?php _e('นำเข้าข้อมูลดิบพร้อม mapping ระบบจะแสดงเฉพาะคำถาม คำถามย่อย และคำตอบ เหมาะสำหรับข้อมูลขนาดใหญ่', 'tpak-dq-system'); ?></li>
                            </ul>
                        </div>
                        <?php
                        $surveys = $api_handler->get_surveys();
                        if ($surveys && !empty($surveys)):
                        ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Survey ID', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('ชื่อแบบสอบถาม', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('สถานะ', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('การดำเนินการ', 'tpak-dq-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (is_array($surveys)): ?>
                                    <?php foreach ($surveys as $survey): ?>
                                        <tr>
                                            <td><?php echo esc_html($survey['sid']); ?></td>
                                            <td><?php echo esc_html($survey['surveyls_title']); ?></td>
                                        <td>
                                            <?php 
                                            $status = $survey['active'] ? __('เปิดใช้งาน', 'tpak-dq-system') : __('ปิดใช้งาน', 'tpak-dq-system');
                                            $status_class = $survey['active'] ? 'success' : 'warning';
                                            ?>
                                            <span class="tpak-status-<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                        </td>
                                        <td>
                                            <div class="tpak-import-actions">
                                                <button type="button" class="button button-small tpak-import-survey" 
                                                        data-survey-id="<?php echo esc_attr($survey['sid']); ?>" 
                                                        data-import-type="full">
                                                    <?php _e('นำเข้าแบบเต็ม', 'tpak-dq-system'); ?>
                                                </button>
                                                <button type="button" class="button button-small tpak-import-survey" 
                                                        data-survey-id="<?php echo esc_attr($survey['sid']); ?>" 
                                                        data-import-type="raw" style="margin-left: 5px;">
                                                    <?php _e('นำเข้าข้อมูลดิบ', 'tpak-dq-system'); ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4"><?php _e('ไม่สามารถดึงข้อมูลแบบสอบถามได้', 'tpak-dq-system'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <p><?php _e('ไม่พบแบบสอบถามหรือไม่สามารถดึงข้อมูลได้', 'tpak-dq-system'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="tpak-import-status error">
                        <h3><?php _e('เชื่อมต่อล้มเหลว', 'tpak-dq-system'); ?></h3>
                        <p><?php _e('ไม่สามารถเชื่อมต่อกับ LimeSurvey API ได้ กรุณาตรวจสอบการตั้งค่า', 'tpak-dq-system'); ?></p>
                        <p><a href="<?php echo admin_url('admin.php?page=tpak-dq-settings'); ?>" class="button">
                            <?php _e('ไปที่การตั้งค่า', 'tpak-dq-system'); ?>
                        </a></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="tpak-import-status warning">
                    <h3><?php _e('ยังไม่ได้ตั้งค่า API', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('กรุณาตั้งค่า LimeSurvey API ก่อนนำเข้าข้อมูล', 'tpak-dq-system'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=tpak-dq-settings'); ?>" class="button button-primary">
                        <?php _e('ตั้งค่า API', 'tpak-dq-system'); ?>
                    </a></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Import History -->
        <div class="tpak-import-section">
            <h2><?php _e('ประวัติการนำเข้า', 'tpak-dq-system'); ?></h2>
            
            <?php
            $recent_imports = get_posts(array(
                'post_type' => 'verification_batch',
                'posts_per_page' => 20,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => '_import_date',
                        'compare' => 'EXISTS'
                    )
                )
            ));
            
            if (!empty($recent_imports)):
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ชุดข้อมูล', 'tpak-dq-system'); ?></th>
                        <th><?php _e('LimeSurvey ID', 'tpak-dq-system'); ?></th>
                        <th><?php _e('วันที่นำเข้า', 'tpak-dq-system'); ?></th>
                        <th><?php _e('สถานะปัจจุบัน', 'tpak-dq-system'); ?></th>
                        <th><?php _e('การดำเนินการ', 'tpak-dq-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_imports as $post): ?>
                        <?php
                        $lime_survey_id = get_post_meta($post->ID, '_lime_survey_id', true);
                        $lime_response_id = get_post_meta($post->ID, '_lime_response_id', true);
                        $import_date = get_post_meta($post->ID, '_import_date', true);
                        $workflow = new TPAK_DQ_Workflow();
                        $status = $workflow->get_batch_status($post->ID);
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo esc_html($lime_survey_id); ?>
                                <?php if ($lime_response_id): ?>
                                    <br><small><?php echo __('Response ID: ', 'tpak-dq-system') . esc_html($lime_response_id); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($import_date) {
                                    echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import_date));
                                } else {
                                    echo __('ไม่ระบุ', 'tpak-dq-system');
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($status): ?>
                                    <span class="tpak-status-indicator <?php echo esc_attr($status); ?>"></span>
                                    <?php 
                                    $status_term = get_term_by('slug', $status, 'verification_status');
                                    echo esc_html($status_term ? $status_term->name : $status);
                                    ?>
                                <?php else: ?>
                                    <span class="tpak-status-text"><?php _e('ไม่ระบุ', 'tpak-dq-system'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_post_link($post->ID); ?>" class="button button-small">
                                    <?php _e('ดูรายละเอียด', 'tpak-dq-system'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p><?php _e('ยังไม่มีประวัติการนำเข้าข้อมูล', 'tpak-dq-system'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Import Statistics -->
        <div class="tpak-import-section">
            <h2><?php _e('สถิติการนำเข้า', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-stats-grid">
                <?php
                // Get total imported count
                $total_imported = wp_count_posts('verification_batch');
                $total_count = 0;
                if (is_object($total_imported)) {
                    $total_count = (isset($total_imported->publish) ? $total_imported->publish : 0) + 
                                  (isset($total_imported->private) ? $total_imported->private : 0) + 
                                  (isset($total_imported->draft) ? $total_imported->draft : 0);
                }
                
                // Get today's imported count
                $today_imported = get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'date_query' => array(
                        array(
                            'after' => '1 day ago'
                        )
                    )
                ));
                $today_count = count($today_imported);
                
                // Get status counts using taxonomy
                $pending_a_count = count(get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => 'pending_a'
                        )
                    )
                )));
                
                $pending_b_count = count(get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => 'pending_b'
                        )
                    )
                )));
                
                $pending_c_count = count(get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => 'pending_c'
                        )
                    )
                )));
                
                $finalized_count = count(get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => array('finalized', 'finalized_by_sampling')
                        )
                    )
                )));
                ?>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('นำเข้าทั้งหมด', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo $total_count; ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('นำเข้าวันนี้', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo $today_count; ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('รอการตรวจสอบ', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo $pending_a_count + $pending_b_count + $pending_c_count; ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('เสร็จสมบูรณ์', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo $finalized_count; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Import survey from list with batch processing support
    $('.tpak-import-survey').on('click', function() {
        var button = $(this);
        var surveyId = button.data('survey-id');
        var importType = button.data('import-type') || 'full';
        
        var confirmMessage = '<?php _e('คุณต้องการนำเข้าแบบสอบถาม ID: ', 'tpak-dq-system'); ?>' + surveyId + '?';
        if (importType === 'raw') {
            confirmMessage += '\n\n<?php _e('ประเภท: นำเข้าข้อมูลดิบพร้อม mapping', 'tpak-dq-system'); ?>';
        } else {
            confirmMessage += '\n\n<?php _e('ประเภท: นำเข้าข้อมูลแบบเต็ม', 'tpak-dq-system'); ?>';
        }
        confirmMessage += '\n\n<?php _e('หมายเหตุ: การนำเข้าข้อมูลขนาดใหญ่อาจใช้เวลานาน กรุณารอจนกว่าการดำเนินการจะเสร็จสิ้น', 'tpak-dq-system'); ?>';
        
        if (confirm(confirmMessage)) {
            button.prop('disabled', true).text('<?php _e('กำลังนำเข้า...', 'tpak-dq-system'); ?>');
            
            // Show progress message
            var progressDiv = $('<div class="import-progress" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 4px; border-left: 4px solid #0073aa;"><?php _e('กำลังดึงข้อมูลจาก LimeSurvey...', 'tpak-dq-system'); ?></div>');
            button.after(progressDiv);
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'tpak_import_survey',
                    nonce: '<?php echo wp_create_nonce('tpak_import_survey'); ?>',
                    survey_id: surveyId,
                    import_type: importType
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var message = '<?php _e('นำเข้าข้อมูลสำเร็จ', 'tpak-dq-system'); ?>';
                        if (response.data.imported > 0) {
                            message += '\n\n<?php _e('รายละเอียด:', 'tpak-dq-system'); ?>\n- <?php _e('นำเข้าสำเร็จ:', 'tpak-dq-system'); ?> ' + response.data.imported + ' <?php _e('รายการ', 'tpak-dq-system'); ?>';
                            if (response.data.errors > 0) {
                                message += '\n- <?php _e('ข้อผิดพลาด:', 'tpak-dq-system'); ?> ' + response.data.errors + ' <?php _e('รายการ', 'tpak-dq-system'); ?>';
                            }
                        }
                        alert(message);
                        location.reload();
                    } else {
                        alert('<?php _e('นำเข้าข้อมูลล้มเหลว: ', 'tpak-dq-system'); ?>' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Import error:', error);
                    alert('<?php _e('นำเข้าข้อมูลล้มเหลว กรุณาลองใหม่อีกครั้ง', 'tpak-dq-system'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false);
                    if (importType === 'raw') {
                        button.text('<?php _e('นำเข้าข้อมูลดิบ', 'tpak-dq-system'); ?>');
                    } else {
                        button.text('<?php _e('นำเข้าแบบเต็ม', 'tpak-dq-system'); ?>');
                    }
                    progressDiv.remove();
                }
            });
        }
    });
    
    // Date validation
    $('#start_date, #end_date').on('change', function() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (startDate && endDate && startDate > endDate) {
            alert('<?php _e('วันที่เริ่มต้นต้องไม่เกินวันที่สิ้นสุด', 'tpak-dq-system'); ?>');
            $(this).val('');
        }
    });
    
    // Clear data form handling
    $('#clear_type').on('change', function() {
        var clearType = $(this).val();
        
        // Hide all clear options
        $('.clear-option').hide();
        
        // Show relevant option
        if (clearType === 'by_survey') {
            $('#clear-by-survey').show();
        } else if (clearType === 'by_status') {
            $('#clear-by-status').show();
        } else if (clearType === 'by_date') {
            $('#clear-by-date').show();
        }
    });
    
    // Clear confirmation checkbox
    $('#clear_confirmation').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('#tpak-clear-data').prop('disabled', !isChecked);
    });
    
    // Clear data form submission
    $('#tpak-clear-data-form').on('submit', function(e) {
        var clearType = $('#clear_type').val();
        var confirmation = $('#clear_confirmation').is(':checked');
        
        if (!confirmation) {
            e.preventDefault();
            alert('<?php _e('กรุณายืนยันการดำเนินการก่อน', 'tpak-dq-system'); ?>');
            return false;
        }
        
        // Validate specific options
        if (clearType === 'by_survey') {
            var surveyId = $('#clear_survey_id').val().trim();
            if (!surveyId) {
                e.preventDefault();
                alert('<?php _e('กรุณาระบุ Survey ID', 'tpak-dq-system'); ?>');
                return false;
            }
        } else if (clearType === 'by_date') {
            var startDate = $('#clear_start_date').val();
            var endDate = $('#clear_end_date').val();
            if (!startDate || !endDate) {
                e.preventDefault();
                alert('<?php _e('กรุณาระบุช่วงวันที่ให้ครบถ้วน', 'tpak-dq-system'); ?>');
                return false;
            }
            if (startDate > endDate) {
                e.preventDefault();
                alert('<?php _e('วันที่เริ่มต้นต้องไม่เกินวันที่สิ้นสุด', 'tpak-dq-system'); ?>');
                return false;
            }
        }
        
        // Final confirmation
        var confirmMessage = '<?php _e('คุณแน่ใจหรือไม่ที่จะเคลียร์ข้อมูล?', 'tpak-dq-system'); ?>\n\n';
        
        if (clearType === 'all') {
            confirmMessage += '<?php _e('ประเภท: เคลียร์ข้อมูลทั้งหมด', 'tpak-dq-system'); ?>';
        } else if (clearType === 'by_survey') {
            confirmMessage += '<?php _e('ประเภท: เคลียร์ข้อมูลตาม Survey ID', 'tpak-dq-system'); ?>: ' + $('#clear_survey_id').val();
        } else if (clearType === 'by_status') {
            confirmMessage += '<?php _e('ประเภท: เคลียร์ข้อมูลตามสถานะ', 'tpak-dq-system'); ?>: ' + $('#clear_status option:selected').text();
        } else if (clearType === 'by_date') {
            confirmMessage += '<?php _e('ประเภท: เคลียร์ข้อมูลตามวันที่', 'tpak-dq-system'); ?>: ' + $('#clear_start_date').val() + ' ถึง ' + $('#clear_end_date').val();
        }
        
        confirmMessage += '\n\n<?php _e('การดำเนินการนี้ไม่สามารถยกเลิกได้', 'tpak-dq-system'); ?>';
        
        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Date validation for clear form
    $('#clear_start_date, #clear_end_date').on('change', function() {
        var startDate = $('#clear_start_date').val();
        var endDate = $('#clear_end_date').val();
        
        if (startDate && endDate && startDate > endDate) {
            alert('<?php _e('วันที่เริ่มต้นต้องไม่เกินวันที่สิ้นสุด', 'tpak-dq-system'); ?>');
            $(this).val('');
        }
    });
});
</script>

<style>
.tpak-surveys-list {
    margin-top: 20px;
}

.tpak-status-success {
    color: #28a745;
    font-weight: 600;
}

.tpak-status-warning {
    color: #ffc107;
    font-weight: 600;
}

.tpak-status-error {
    color: #dc3545;
    font-weight: 600;
}

.button-danger {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: #fff !important;
}

.button-danger:hover {
    background-color: #c82333 !important;
    border-color: #bd2130 !important;
}

.button-danger:disabled {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    cursor: not-allowed !important;
}
</style> 