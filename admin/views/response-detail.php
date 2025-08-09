<?php
/**
 * TPAK DQ System - Single Response Detail View
 * แสดงรายละเอียดของ Response เดี่ยวแบบคำถาม + คำถามย่อย + คำตอบ
 */

if (!defined('ABSPATH')) {
    exit;
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
$response_data = get_post_meta($response_id, '_response_data', true);
$response_meta_id = get_post_meta($response_id, '_response_id', true);
$survey_info = get_post_meta($response_id, '_survey_info', true);
$survey_structure = get_post_meta($response_id, '_survey_structure', true);

// Get workflow data
$workflow = new TPAK_DQ_Workflow();
$status = $workflow->get_batch_status($response_id);
$audit_trail = $workflow->get_audit_trail($response_id);

// Organize questions by groups/sections
$organized_data = array();
$other_data = array();

if ($response_data && is_array($response_data)) {
    foreach ($response_data as $field_key => $field_value) {
        // Group questions by prefix (e.g., Q1, Q2, etc.)
        if (preg_match('/^(Q\d+)(.*)/', $field_key, $matches)) {
            $question_group = $matches[1];
            $sub_question = $matches[2];
            
            if (!isset($organized_data[$question_group])) {
                $organized_data[$question_group] = array(
                    'main' => null,
                    'sub_questions' => array()
                );
            }
            
            if (empty($sub_question)) {
                $organized_data[$question_group]['main'] = $field_value;
            } else {
                $organized_data[$question_group]['sub_questions'][$field_key] = $field_value;
            }
        } else {
            // Non-question data (metadata, timestamps, etc.)
            $other_data[$field_key] = $field_value;
        }
    }
}

// Get question labels from survey structure if available
$question_labels = array();
if ($survey_structure && isset($survey_structure['questions'])) {
    foreach ($survey_structure['questions'] as $q) {
        if (isset($q['title']) && isset($q['question'])) {
            $question_labels[$q['title']] = $q['question'];
        }
    }
}
?>

<div class="wrap tpak-response-detail">
    <!-- Header -->
    <div class="tpak-detail-header">
        <div class="header-left">
            <h1>
                <?php _e('รายละเอียดแบบสอบถาม', 'tpak-dq-system'); ?>
                <span class="response-id">#<?php echo esc_html($response_meta_id ?: $response_id); ?></span>
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
    
    <!-- Main Content Area -->
    <div class="tpak-detail-content">
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
                    
                    <?php if ($survey_info && isset($survey_info['surveyls_title'])): ?>
                        <div class="info-item full-width">
                            <label><?php _e('ชื่อแบบสอบถาม:', 'tpak-dq-system'); ?></label>
                            <span><?php echo esc_html($survey_info['surveyls_title']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Search/Filter Bar -->
            <div class="question-filter">
                <input type="text" id="question-search" 
                       placeholder="<?php _e('ค้นหาคำถามหรือคำตอบ...', 'tpak-dq-system'); ?>">
                
                <div class="filter-actions">
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
            
            <!-- Questions and Answers -->
            <div class="questions-container">
                <h2>
                    <?php _e('คำถามและคำตอบ', 'tpak-dq-system'); ?>
                    <span class="question-count">
                        (<?php echo count($organized_data); ?> <?php _e('คำถามหลัก', 'tpak-dq-system'); ?>)
                    </span>
                </h2>
                
                <?php if (!empty($organized_data)): ?>
                    <?php 
                    $section_num = 1;
                    foreach ($organized_data as $question_key => $question_data): 
                    ?>
                        <div class="question-section" data-question="<?php echo esc_attr($question_key); ?>">
                            <div class="question-header">
                                <button class="toggle-section" aria-expanded="true">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                
                                <div class="question-title">
                                    <span class="question-number"><?php echo esc_html($question_key); ?></span>
                                    <?php if (isset($question_labels[$question_key])): ?>
                                        <span class="question-text">
                                            <?php echo esc_html($question_labels[$question_key]); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="question-content">
                                <!-- Main Answer -->
                                <?php if ($question_data['main'] !== null): ?>
                                    <div class="main-answer">
                                        <div class="answer-label"><?php _e('คำตอบหลัก:', 'tpak-dq-system'); ?></div>
                                        <div class="answer-value">
                                            <?php echo nl2br(esc_html($question_data['main'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Sub-questions -->
                                <?php if (!empty($question_data['sub_questions'])): ?>
                                    <div class="sub-questions">
                                        <div class="sub-questions-header">
                                            <?php _e('คำถามย่อย:', 'tpak-dq-system'); ?>
                                        </div>
                                        
                                        <?php foreach ($question_data['sub_questions'] as $sub_key => $sub_value): ?>
                                            <div class="sub-question-item">
                                                <div class="sub-question-key">
                                                    <?php echo esc_html($sub_key); ?>
                                                    <?php if (isset($question_labels[$sub_key])): ?>
                                                        <span class="sub-question-label">
                                                            - <?php echo esc_html($question_labels[$sub_key]); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="sub-question-value">
                                                    <?php echo nl2br(esc_html($sub_value)); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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
                    
                    <!-- Action Buttons based on user role and status -->
                    <?php
                    $current_user = wp_get_current_user();
                    $can_verify = false;
                    $next_action = '';
                    
                    if ($status == 'pending_a' && current_user_can('verify_level_a')) {
                        $can_verify = true;
                        $next_action = 'approve_a';
                    } elseif ($status == 'pending_b' && current_user_can('verify_level_b')) {
                        $can_verify = true;
                        $next_action = 'approve_b';
                    } elseif ($status == 'pending_c' && current_user_can('verify_level_c')) {
                        $can_verify = true;
                        $next_action = 'approve_c';
                    }
                    ?>
                    
                    <?php if ($can_verify): ?>
                        <div class="verification-actions">
                            <button class="button button-primary approve-btn" 
                                    data-id="<?php echo $response_id; ?>" 
                                    data-action="<?php echo $next_action; ?>">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('อนุมัติ', 'tpak-dq-system'); ?>
                            </button>
                            
                            <button class="button button-secondary reject-btn" 
                                    data-id="<?php echo $response_id; ?>" 
                                    data-action="reject">
                                <span class="dashicons dashicons-no"></span>
                                <?php _e('ส่งกลับ', 'tpak-dq-system'); ?>
                            </button>
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
        </div>
    </div>
</div>

<style>
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
</style>

<script>
jQuery(document).ready(function($) {
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