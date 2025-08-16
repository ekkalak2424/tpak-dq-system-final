<?php
/**
 * TPAK DQ System - Native WordPress Survey Renderer
 * 
 * แสดงแบบสอบถาม LimeSurvey แบบ Native ใน WordPress
 * ใช้ API ดึงข้อมูลและ render ด้วย WordPress เองเพื่อควบคุมได้ 100%
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Native_Survey_Renderer {
    
    private static $instance = null;
    private $api_handler = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-api-handler.php';
        $this->api_handler = new TPAK_DQ_API_Handler();
        
        add_action('add_meta_boxes', array($this, 'add_native_survey_metabox'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_survey_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_survey_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_render_native_survey', array($this, 'render_native_survey_ajax'));
        add_action('wp_ajax_nopriv_render_native_survey', array($this, 'render_native_survey_ajax'));
        add_action('wp_ajax_submit_native_survey', array($this, 'submit_native_survey_ajax'));
        add_action('wp_ajax_nopriv_submit_native_survey', array($this, 'submit_native_survey_ajax'));
    }
    
    /**
     * เพิ่ม meta box สำหรับแสดงแบบสอบถามแบบ native
     */
    public function add_native_survey_metabox() {
        add_meta_box(
            'tpak_native_survey',
            '🎯 แบบสอบถาม Native WordPress',
            array($this, 'render_native_survey_metabox'),
            'verification_batch',
            'normal',
            'high'
        );
    }
    
    /**
     * Render meta box แสดงแบบสอบถามแบบ native พร้อมระบบ Review
     */
    public function render_native_survey_metabox($post) {
        $survey_id = get_post_meta($post->ID, '_lime_survey_id', true);
        $response_id = get_post_meta($post->ID, '_lime_response_id', true);
        
        // ดึงข้อมูล review status
        $review_data = $this->get_review_data($response_id);
        $can_edit = current_user_can('edit_survey_responses');
        $can_review = current_user_can('review_survey_responses');
        $can_approve = current_user_can('approve_survey_responses');
        
        if (!$survey_id) {
            echo '<p>ไม่พบ Survey ID สำหรับรายการนี้</p>';
            return;
        }
        
        ?>
        <div class="tpak-native-survey-container" data-survey-id="<?php echo esc_attr($survey_id); ?>" data-response-id="<?php echo esc_attr($response_id); ?>">
            
            <!-- Survey Controls -->
            <div class="survey-controls-native">
                <div class="control-row">
                    <div class="survey-info">
                        <strong>Survey ID:</strong> <?php echo esc_html($survey_id); ?>
                        <?php if ($response_id): ?>
                            | <strong>Response ID:</strong> <?php echo esc_html($response_id); ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="control-buttons">
                        <button type="button" class="button load-survey">📥 โหลดแบบสอบถาม</button>
                        <button type="button" class="button refresh-survey">🔄 รีเฟรช</button>
                        <button type="button" class="button toggle-edit-mode">✏️ โหมดแก้ไข</button>
                    </div>
                </div>
                
                <div class="control-row">
                    <label>
                        <input type="checkbox" id="show_progress" checked> แสดงความก้าวหน้า
                    </label>
                    <label>
                        <input type="checkbox" id="enable_validation" checked> ตรวจสอบข้อมูล
                    </label>
                    <label>
                        <input type="checkbox" id="auto_save"> บันทึกอัตโนมัติ
                    </label>
                    <label>
                        <input type="checkbox" id="readonly_mode" <?php echo ($response_id && !$can_edit) ? 'checked' : ''; ?>> โหมดอ่านอย่างเดียว
                    </label>
                    <label>
                        <input type="checkbox" id="show_modifications"> แสดงการแก้ไข
                    </label>
                    <label>
                        <input type="checkbox" id="compare_mode"> เปรียบเทียบกับต้นฉบับ
                    </label>
                </div>
                
                <!-- Review Status -->
                <?php if ($review_data): ?>
                <div class="review-status-bar">
                    <span class="status-label">สถานะ:</span>
                    <span class="status-badge status-<?php echo esc_attr($review_data['review_status']); ?>">
                        <?php echo $this->get_status_label($review_data['review_status']); ?>
                    </span>
                    <?php if ($review_data['reviewed_by']): ?>
                        <span class="reviewer-info">
                            ตรวจสอบโดย: <?php echo esc_html(get_userdata($review_data['reviewed_by'])->display_name); ?>
                            เมื่อ <?php echo esc_html($review_data['reviewed_at']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Survey Content -->
            <div class="native-survey-content">
                <div class="survey-loading" style="text-align: center; padding: 40px;">
                    <div class="loading-spinner"></div>
                    <p>กำลังโหลดแบบสอบถาม...</p>
                </div>
                
                <!-- Auto-load survey data if available -->
                <?php if ($survey_id && $response_id): ?>
                <script>
                jQuery(document).ready(function($) {
                    console.log('Auto-loading survey data...');
                    // Trigger load survey button click
                    setTimeout(function() {
                        $('.load-survey').trigger('click');
                    }, 500);
                });
                </script>
                <?php endif; ?>
            </div>
            
            <!-- Survey Progress -->
            <div class="survey-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">
                    <span class="current-question">0</span> / <span class="total-questions">0</span> คำถาม
                    (<span class="completion-percent">0</span>%)
                </div>
            </div>
            
            <!-- Survey Actions -->
            <div class="survey-actions" style="display: none;">
                <?php if ($can_edit): ?>
                    <button type="button" class="button button-primary save-survey">💾 บันทึกคำตอบ</button>
                    <button type="button" class="button save-and-review">📝 บันทึกและส่งตรวจสอบ</button>
                <?php endif; ?>
                
                <?php if ($can_review): ?>
                    <button type="button" class="button review-survey">🔍 ตรวจสอบ</button>
                    <button type="button" class="button add-review-note">📌 เพิ่มหมายเหตุ</button>
                <?php endif; ?>
                
                <?php if ($can_approve): ?>
                    <button type="button" class="button button-primary approve-survey">✅ อนุมัติ</button>
                    <button type="button" class="button button-warning reject-survey">❌ ส่งกลับแก้ไข</button>
                <?php endif; ?>
                
                <button type="button" class="button submit-survey">📤 ส่งไป LimeSurvey</button>
                <button type="button" class="button export-survey">📥 Export</button>
                <button type="button" class="button view-history">📜 ประวัติ</button>
                <button type="button" class="button button-secondary reset-survey">🔄 รีเซ็ต</button>
            </div>
            
            <!-- Review Notes Section -->
            <div class="review-notes-section" style="display: none;">
                <h3>หมายเหตุการตรวจสอบ</h3>
                <textarea id="review_notes" rows="4" style="width: 100%;" placeholder="ใส่หมายเหตุหรือข้อเสนอแนะ..."></textarea>
                <div class="review-actions">
                    <button type="button" class="button save-review-notes">บันทึกหมายเหตุ</button>
                    <button type="button" class="button cancel-review">ยกเลิก</button>
                </div>
            </div>
            
            <!-- History Modal -->
            <div id="history-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>ประวัติการแก้ไข</h2>
                    <div id="history-content"></div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var container = $('.tpak-native-survey-container');
            var surveyId = container.data('survey-id');
            var responseId = container.data('response-id');
            var currentSurveyData = null;
            
            // โหลดแบบสอบถาม
            function loadSurvey() {
                $('.survey-loading').show();
                $('.native-survey-content').show();
                $('.survey-actions').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'render_native_survey',
                        survey_id: surveyId,
                        response_id: responseId,
                        readonly: $('#readonly_mode').is(':checked'),
                        nonce: '<?php echo wp_create_nonce('native_survey_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            renderSurvey(response.data);
                            currentSurveyData = response.data;
                        } else {
                            $('.native-survey-content').html('<div class="error">ข้อผิดพลาด: ' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $('.native-survey-content').html('<div class="error">เกิดข้อผิดพลาดในการโหลดแบบสอบถาม</div>');
                    },
                    complete: function() {
                        $('.survey-loading').hide();
                    }
                });
            }
            
            // Render แบบสอบถาม
            function renderSurvey(surveyData) {
                var html = '<form id="native-survey-form" class="native-survey-form">';
                
                // Survey Header
                if (surveyData.info) {
                    html += '<div class="survey-header">';
                    html += '<h3>' + escapeHtml(surveyData.info.title || 'แบบสอบถาม') + '</h3>';
                    if (surveyData.info.description) {
                        html += '<div class="survey-description">' + escapeHtml(surveyData.info.description) + '</div>';
                    }
                    html += '</div>';
                }
                
                // Survey Groups
                if (surveyData.groups) {
                    $.each(surveyData.groups, function(gid, group) {
                        html += renderGroup(group, surveyData.questions, surveyData.responses);
                    });
                }
                
                html += '</form>';
                
                $('.native-survey-content').html(html);
                
                // แสดง progress และ actions
                if ($('#show_progress').is(':checked')) {
                    updateProgress();
                    $('.survey-progress').show();
                }
                
                if (!$('#readonly_mode').is(':checked')) {
                    $('.survey-actions').show();
                }
                
                // Bind events
                bindSurveyEvents();
            }
            
            // Render กลุ่มคำถาม
            function renderGroup(group, questions, responses) {
                var html = '<div class="survey-group" data-gid="' + group.gid + '">';
                html += '<h4 class="group-title">' + escapeHtml(group.name) + '</h4>';
                
                if (group.description) {
                    html += '<div class="group-description">' + escapeHtml(group.description) + '</div>';
                }
                
                // Render คำถามในกลุ่ม
                if (group.questions) {
                    html += '<div class="group-questions">';
                    $.each(group.questions, function(index, qid) {
                        if (questions[qid]) {
                            html += renderQuestion(questions[qid], responses);
                        }
                    });
                    html += '</div>';
                }
                
                html += '</div>';
                return html;
            }
            
            // Render คำถาม
            function renderQuestion(question, responses) {
                var response = responses && responses[question.title] ? responses[question.title] : '';
                var readonly = $('#readonly_mode').is(':checked');
                
                var html = '<div class="survey-question" data-qid="' + question.qid + '" data-type="' + question.type + '">';
                
                // Question header
                html += '<div class="question-header">';
                html += '<span class="question-number">' + question.title + '</span>';
                html += '<span class="question-text">' + question.question + '</span>';
                if (question.mandatory === 'Y') {
                    html += '<span class="required">*</span>';
                }
                html += '</div>';
                
                // Help text
                if (question.help) {
                    html += '<div class="question-help">' + question.help + '</div>';
                }
                
                // Question input
                html += '<div class="question-input">';
                html += renderQuestionInput(question, response, readonly);
                html += '</div>';
                
                html += '</div>';
                return html;
            }
            
            // Render input สำหรับคำถาม
            function renderQuestionInput(question, response, readonly) {
                var name = 'question_' + question.qid;
                var id = 'q_' + question.qid;
                var readonlyAttr = readonly ? 'readonly disabled' : '';
                
                switch (question.type) {
                    case 'T': // Text
                    case 'S': // Short text
                        return '<input type="text" name="' + name + '" id="' + id + '" value="' + escapeHtml(response) + '" ' + readonlyAttr + ' class="question-input-text">';
                        
                    case 'L': // List (dropdown)
                        var html = '<select name="' + name + '" id="' + id + '" ' + readonlyAttr + ' class="question-input-select">';
                        html += '<option value="">-- เลือก --</option>';
                        if (question.answers) {
                            $.each(question.answers, function(code, answer) {
                                var selected = (response == code) ? 'selected' : '';
                                html += '<option value="' + code + '" ' + selected + '>' + escapeHtml(answer.text) + '</option>';
                            });
                        }
                        html += '</select>';
                        return html;
                        
                    case 'O': // List with comment
                        var html = renderQuestionInput({...question, type: 'L'}, response, readonly);
                        html += '<textarea name="' + name + '_comment" placeholder="ความคิดเห็นเพิ่มเติม..." ' + readonlyAttr + ' class="question-input-textarea"></textarea>';
                        return html;
                        
                    case 'Y': // Yes/No
                        var html = '<div class="question-input-radio">';
                        html += '<label><input type="radio" name="' + name + '" value="Y" ' + (response == 'Y' ? 'checked' : '') + ' ' + readonlyAttr + '> ใช่</label>';
                        html += '<label><input type="radio" name="' + name + '" value="N" ' + (response == 'N' ? 'checked' : '') + ' ' + readonlyAttr + '> ไม่ใช่</label>';
                        html += '</div>';
                        return html;
                        
                    case 'M': // Multiple choice
                        var html = '<div class="question-input-checkbox">';
                        if (question.answers) {
                            $.each(question.answers, function(code, answer) {
                                var checked = response && response.indexOf(code) !== -1 ? 'checked' : '';
                                html += '<label><input type="checkbox" name="' + name + '[]" value="' + code + '" ' + checked + ' ' + readonlyAttr + '> ' + escapeHtml(answer.text) + '</label>';
                            });
                        }
                        html += '</div>';
                        return html;
                        
                    case 'U': // Long text
                        return '<textarea name="' + name + '" id="' + id + '" ' + readonlyAttr + ' class="question-input-textarea" rows="4">' + escapeHtml(response) + '</textarea>';
                        
                    case 'N': // Number
                        return '<input type="number" name="' + name + '" id="' + id + '" value="' + escapeHtml(response) + '" ' + readonlyAttr + ' class="question-input-number">';
                        
                    case 'D': // Date
                        return '<input type="date" name="' + name + '" id="' + id + '" value="' + escapeHtml(response) + '" ' + readonlyAttr + ' class="question-input-date">';
                        
                    default:
                        return '<input type="text" name="' + name + '" id="' + id + '" value="' + escapeHtml(response) + '" ' + readonlyAttr + ' class="question-input-text">';
                }
            }
            
            // Bind events
            function bindSurveyEvents() {
                // Auto save
                if ($('#auto_save').is(':checked')) {
                    $('#native-survey-form input, #native-survey-form select, #native-survey-form textarea').on('change', function() {
                        setTimeout(saveSurvey, 1000);
                    });
                }
                
                // Validation
                if ($('#enable_validation').is(':checked')) {
                    $('#native-survey-form input, #native-survey-form select, #native-survey-form textarea').on('blur', validateField);
                }
                
                // Progress update
                $('#native-survey-form input, #native-survey-form select, #native-survey-form textarea').on('change', updateProgress);
            }
            
            // Update progress
            function updateProgress() {
                var totalQuestions = $('.survey-question').length;
                var answeredQuestions = 0;
                
                $('.survey-question').each(function() {
                    var hasAnswer = false;
                    $(this).find('input, select, textarea').each(function() {
                        if ($(this).val() && $(this).val().trim() !== '') {
                            hasAnswer = true;
                            return false;
                        }
                    });
                    if (hasAnswer) answeredQuestions++;
                });
                
                var percentage = totalQuestions > 0 ? Math.round((answeredQuestions / totalQuestions) * 100) : 0;
                
                $('.current-question').text(answeredQuestions);
                $('.total-questions').text(totalQuestions);
                $('.completion-percent').text(percentage);
                $('.progress-fill').css('width', percentage + '%');
            }
            
            // Validate field
            function validateField() {
                var field = $(this);
                var question = field.closest('.survey-question');
                var isRequired = question.find('.required').length > 0;
                
                field.removeClass('error');
                question.find('.field-error').remove();
                
                if (isRequired && (!field.val() || field.val().trim() === '')) {
                    field.addClass('error');
                    field.after('<div class="field-error">กรุณากรอกข้อมูลในช่องนี้</div>');
                }
            }
            
            // Save survey
            function saveSurvey() {
                var formData = $('#native-survey-form').serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'submit_native_survey',
                        survey_id: surveyId,
                        response_id: responseId,
                        form_data: formData,
                        save_only: true,
                        nonce: '<?php echo wp_create_nonce('submit_survey_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('บันทึกเรียบร้อย', 'success');
                        }
                    }
                });
            }
            
            // Utility functions
            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
            }
            
            function showNotification(message, type) {
                var notification = $('<div class="survey-notification ' + type + '">' + message + '</div>');
                container.prepend(notification);
                setTimeout(function() {
                    notification.fadeOut();
                }, 3000);
            }
            
            // Event handlers
            $('.load-survey').on('click', loadSurvey);
            $('.refresh-survey').on('click', loadSurvey);
            
            $('.save-survey').on('click', function() {
                saveSurvey();
                showNotification('กำลังบันทึก...', 'info');
            });
            
            $('.submit-survey').on('click', function() {
                if (confirm('คุณต้องการส่งแบบสอบถามหรือไม่? การดำเนินการนี้ไม่สามารถยกเลิกได้')) {
                    var formData = $('#native-survey-form').serialize();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'submit_native_survey',
                            survey_id: surveyId,
                            form_data: formData,
                            save_only: false,
                            nonce: '<?php echo wp_create_nonce('submit_survey_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotification('ส่งแบบสอบถามเรียบร้อย', 'success');
                                $('#readonly_mode').prop('checked', true);
                                loadSurvey();
                            } else {
                                showNotification('เกิดข้อผิดพลาด: ' + response.data.message, 'error');
                            }
                        }
                    });
                }
            });
            
            $('.reset-survey').on('click', function() {
                if (confirm('คุณต้องการรีเซ็ตแบบสอบถามหรือไม่?')) {
                    $('#native-survey-form')[0].reset();
                    updateProgress();
                }
            });
            
            $('.toggle-edit-mode').on('click', function() {
                $('#readonly_mode').prop('checked', !$('#readonly_mode').is(':checked'));
                loadSurvey();
            });
            
            // Control change handlers
            $('#show_progress, #enable_validation, #auto_save, #readonly_mode').on('change', function() {
                if (currentSurveyData) {
                    renderSurvey(currentSurveyData);
                }
            });
            
            // โหลดครั้งแรก
            loadSurvey();
        });
        </script>
        
        <style>
        .tpak-native-survey-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }
        
        .survey-controls-native {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .control-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .control-row:last-child {
            margin-bottom: 0;
        }
        
        .control-buttons {
            display: flex;
            gap: 10px;
        }
        
        .native-survey-content {
            max-height: 600px;
            overflow-y: auto;
            padding: 20px;
        }
        
        .survey-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .survey-header h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .survey-description {
            color: #6c757d;
            line-height: 1.5;
        }
        
        .survey-group {
            margin-bottom: 30px;
        }
        
        .group-title {
            background: #e3f2fd;
            padding: 12px 15px;
            margin: 0 0 15px 0;
            border-left: 4px solid #2196f3;
            font-weight: 600;
        }
        
        .group-description {
            margin-bottom: 15px;
            font-style: italic;
            color: #6c757d;
        }
        
        .survey-question {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: #fafafa;
        }
        
        .question-header {
            margin-bottom: 10px;
        }
        
        .question-number {
            display: inline-block;
            background: #007cba;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .question-text {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .required {
            color: #dc3545;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .question-help {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 10px;
            font-style: italic;
        }
        
        .question-input {
            margin-top: 10px;
        }
        
        .question-input-text,
        .question-input-number,
        .question-input-date,
        .question-input-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .question-input-textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
        }
        
        .question-input-radio label,
        .question-input-checkbox label {
            display: block;
            margin-bottom: 8px;
            font-weight: normal;
        }
        
        .question-input-radio input,
        .question-input-checkbox input {
            margin-right: 8px;
        }
        
        .field-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .error {
            border-color: #dc3545 !important;
        }
        
        .survey-progress {
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        
        .survey-actions {
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
            text-align: center;
        }
        
        .survey-actions .button {
            margin: 0 5px;
        }
        
        .survey-notification {
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .survey-notification.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .survey-notification.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .survey-notification.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007cba;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler สำหรับ render แบบสอบถาม
     */
    public function render_native_survey_ajax() {
        check_ajax_referer('native_survey_nonce', 'nonce');
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $response_id = sanitize_text_field($_POST['response_id']);
        $readonly = isset($_POST['readonly']) && $_POST['readonly'] === 'true';
        
        // ดึงข้อมูลแบบสอบถาม
        $survey_data = $this->get_survey_data($survey_id);
        
        if (!$survey_data) {
            wp_send_json_error(array('message' => 'ไม่สามารถดึงข้อมูลแบบสอบถามได้'));
        }
        
        // ดึงคำตอบถ้ามี response_id
        if ($response_id) {
            $survey_data['responses'] = $this->get_response_data($survey_id, $response_id);
        }
        
        wp_send_json_success($survey_data);
    }
    
    /**
     * AJAX handler สำหรับบันทึกแบบสอบถาม
     */
    public function submit_native_survey_ajax() {
        check_ajax_referer('submit_survey_nonce', 'nonce');
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $response_id = sanitize_text_field($_POST['response_id']);
        $form_data = $_POST['form_data'];
        $save_only = isset($_POST['save_only']) && $_POST['save_only'] === 'true';
        
        // Parse form data
        parse_str($form_data, $responses);
        
        // บันทึกข้อมูล
        $result = $this->save_survey_responses($survey_id, $responses, $save_only, $response_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * ดึงข้อมูลแบบสอบถามจาก API
     */
    private function get_survey_data($survey_id) {
        if (!$this->api_handler->is_configured()) {
            return false;
        }
        
        // ลองใช้ LSS structure ก่อน
        $lss_structure = get_option('tpak_lss_structure_' . $survey_id, false);
        if ($lss_structure) {
            return $this->format_lss_data($lss_structure);
        }
        
        // ดึงจาก API
        $survey_properties = $this->api_handler->get_survey_properties($survey_id);
        $groups = $this->api_handler->list_groups($survey_id);
        $questions = $this->api_handler->list_questions($survey_id);
        
        if (!$survey_properties || !$groups || !$questions) {
            return false;
        }
        
        return array(
            'info' => $survey_properties,
            'groups' => $groups,
            'questions' => $questions
        );
    }
    
    /**
     * Format ข้อมูล LSS structure
     */
    private function format_lss_data($lss_structure) {
        $formatted = array(
            'info' => $lss_structure['survey_info'],
            'groups' => array(),
            'questions' => array()
        );
        
        // Format groups
        foreach ($lss_structure['groups'] as $gid => $group) {
            $formatted['groups'][$gid] = array(
                'gid' => $gid,
                'name' => $group['group_name'],
                'description' => $group['description'],
                'questions' => array()
            );
        }
        
        // Format questions และจัดเข้ากลุ่ม
        foreach ($lss_structure['questions'] as $qid => $question) {
            $gid = $question['gid'];
            $question_text = isset($lss_structure['question_texts'][$qid]) ? 
                $lss_structure['question_texts'][$qid]['question'] : $question['title'];
            
            $formatted_question = array(
                'qid' => $qid,
                'title' => $question['title'],
                'question' => $question_text,
                'help' => isset($lss_structure['question_texts'][$qid]) ? 
                    $lss_structure['question_texts'][$qid]['help'] : '',
                'type' => $question['type'],
                'mandatory' => $question['mandatory']
            );
            
            // เพิ่ม answer options
            if (isset($lss_structure['answers'][$qid])) {
                $formatted_question['answers'] = $lss_structure['answers'][$qid];
            }
            
            $formatted['questions'][$qid] = $formatted_question;
            
            // เพิ่มเข้ากลุ่ม
            if (isset($formatted['groups'][$gid])) {
                $formatted['groups'][$gid]['questions'][] = $qid;
            }
        }
        
        return $formatted;
    }
    
    /**
     * ดึงข้อมูลคำตอบ
     */
    private function get_response_data($survey_id, $response_id) {
        return $this->api_handler->export_responses($survey_id, 'json', null, null, array($response_id));
    }
    
    /**
     * บันทึกคำตอบแบบสอบถาม พร้อมระบบ Review & Approval
     */
    private function save_survey_responses($survey_id, $responses, $save_only = true, $response_id = null) {
        global $wpdb;
        
        // เตรียมข้อมูลสำหรับบันทึก
        $current_user = wp_get_current_user();
        $timestamp = current_time('mysql');
        
        // สร้าง response ID ใหม่ถ้าไม่มี
        if (!$response_id) {
            $response_id = 'resp_' . $survey_id . '_' . time() . '_' . wp_rand(1000, 9999);
        }
        
        // เตรียมข้อมูลสำหรับบันทึก
        $survey_data = array(
            'response_id' => $response_id,
            'survey_id' => $survey_id,
            'responses' => $responses,
            'original_responses' => $this->get_original_responses($survey_id, $response_id),
            'modifications' => $this->track_modifications($responses, $response_id),
            'status' => $save_only ? 'draft' : 'submitted',
            'created_by' => $current_user->ID,
            'created_date' => $timestamp,
            'modified_by' => $current_user->ID,
            'modified_date' => $timestamp,
            'review_status' => 'pending',
            'review_notes' => '',
            'version' => $this->get_next_version($response_id)
        );
        
        // บันทึกลงฐานข้อมูล
        $table_name = $wpdb->prefix . 'tpak_survey_responses';
        
        // ตรวจสอบและสร้างตารางถ้ายังไม่มี
        $this->ensure_database_tables();
        
        if ($save_only) {
            // บันทึกเป็น draft พร้อม versioning
            $result = $wpdb->insert(
                $table_name,
                array(
                    'response_id' => $response_id,
                    'survey_id' => $survey_id,
                    'response_data' => json_encode($survey_data),
                    'status' => 'draft',
                    'created_by' => $current_user->ID,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                )
            );
            
            // บันทึก audit log
            $this->log_audit_trail($response_id, 'save_draft', $survey_data);
            
            // บันทึกลง option สำหรับ backup
            update_option('tpak_draft_response_' . $response_id, $survey_data);
            
            return array(
                'success' => true,
                'message' => 'บันทึก draft เรียบร้อย',
                'response_id' => $response_id,
                'version' => $survey_data['version']
            );
            
        } else {
            // ส่งไป LimeSurvey ผ่าน API
            $api_result = $this->submit_to_limesurvey($survey_id, $responses, $response_id);
            
            if ($api_result['success']) {
                // อัปเดต status
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'submitted',
                        'lime_response_id' => $api_result['lime_response_id'],
                        'submitted_at' => $timestamp,
                        'updated_at' => $timestamp
                    ),
                    array('response_id' => $response_id)
                );
                
                // บันทึก audit log
                $this->log_audit_trail($response_id, 'submit', array(
                    'lime_response_id' => $api_result['lime_response_id'],
                    'submitted_by' => $current_user->ID
                ));
                
                return array(
                    'success' => true,
                    'message' => 'ส่งแบบสอบถามเรียบร้อย',
                    'response_id' => $response_id,
                    'lime_response_id' => $api_result['lime_response_id']
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'ไม่สามารถส่งไป LimeSurvey: ' . $api_result['message']
                );
            }
        }
    }
    
    /**
     * ดึงข้อมูลคำตอบต้นฉบับ
     */
    private function get_original_responses($survey_id, $response_id) {
        // ดึงจาก LimeSurvey ผ่าน API
        if ($response_id && strpos($response_id, 'resp_') !== 0) {
            $original = $this->api_handler->export_responses($survey_id, 'json', null, null, array($response_id));
            if ($original) {
                return $original;
            }
        }
        return array();
    }
    
    /**
     * ติดตามการแก้ไข
     */
    private function track_modifications($new_responses, $response_id) {
        $modifications = array();
        $original = $this->get_original_responses(null, $response_id);
        
        if (!empty($original)) {
            foreach ($new_responses as $field => $value) {
                $field_key = str_replace('question_', '', $field);
                if (isset($original[$field_key]) && $original[$field_key] != $value) {
                    $modifications[] = array(
                        'field' => $field_key,
                        'old_value' => $original[$field_key],
                        'new_value' => $value,
                        'modified_at' => current_time('mysql'),
                        'modified_by' => get_current_user_id()
                    );
                }
            }
        }
        
        return $modifications;
    }
    
    /**
     * ดึง version ถัดไป
     */
    private function get_next_version($response_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_survey_responses';
        
        $max_version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(JSON_EXTRACT(response_data, '$.version') AS UNSIGNED)) 
             FROM $table_name 
             WHERE response_id = %s",
            $response_id
        ));
        
        return $max_version ? $max_version + 1 : 1;
    }
    
    /**
     * สร้างตารางในฐานข้อมูล
     */
    private function ensure_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // ตาราง survey responses
        $table_responses = $wpdb->prefix . 'tpak_survey_responses';
        $sql_responses = "CREATE TABLE IF NOT EXISTS $table_responses (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            response_id varchar(100) NOT NULL,
            survey_id varchar(50) NOT NULL,
            lime_response_id varchar(50),
            response_data longtext NOT NULL,
            status varchar(20) DEFAULT 'draft',
            review_status varchar(20) DEFAULT 'pending',
            created_by bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            submitted_at datetime,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY survey_id (survey_id),
            KEY status (status)
        ) $charset_collate;";
        
        // ตาราง audit log
        $table_audit = $wpdb->prefix . 'tpak_survey_audit';
        $sql_audit = "CREATE TABLE IF NOT EXISTS $table_audit (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            response_id varchar(100) NOT NULL,
            action varchar(50) NOT NULL,
            action_data longtext,
            user_id bigint(20),
            user_name varchar(100),
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_responses);
        dbDelta($sql_audit);
    }
    
    /**
     * บันทึก audit trail
     */
    private function log_audit_trail($response_id, $action, $data = array()) {
        global $wpdb;
        $current_user = wp_get_current_user();
        
        $wpdb->insert(
            $wpdb->prefix . 'tpak_survey_audit',
            array(
                'response_id' => $response_id,
                'action' => $action,
                'action_data' => json_encode($data),
                'user_id' => $current_user->ID,
                'user_name' => $current_user->display_name,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * ส่งข้อมูลไป LimeSurvey ผ่าน API
     */
    private function submit_to_limesurvey($survey_id, $responses, $response_id) {
        if (!$this->api_handler->is_configured()) {
            return array('success' => false, 'message' => 'API ไม่ได้ตั้งค่า');
        }
        
        try {
            // เตรียมข้อมูลสำหรับ API
            $formatted_responses = $this->format_responses_for_api($responses);
            
            // เรียก API เพื่อเพิ่มหรืออัปเดต response
            if (strpos($response_id, 'resp_') === 0) {
                // สร้าง response ใหม่
                $result = $this->api_handler->add_response($survey_id, $formatted_responses);
            } else {
                // อัปเดต response ที่มีอยู่
                $result = $this->api_handler->update_response($survey_id, $response_id, $formatted_responses);
            }
            
            if ($result) {
                return array(
                    'success' => true,
                    'lime_response_id' => $result
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'ไม่สามารถส่งข้อมูลได้'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * จัดรูปแบบคำตอบสำหรับ API
     */
    private function format_responses_for_api($responses) {
        $formatted = array();
        
        foreach ($responses as $key => $value) {
            // แปลง question_123 เป็น field name ที่ LimeSurvey ต้องการ
            if (strpos($key, 'question_') === 0) {
                $qid = str_replace('question_', '', $key);
                // ดึง field name จากโครงสร้าง
                $field_name = $this->get_field_name_for_question($qid);
                if ($field_name) {
                    $formatted[$field_name] = $value;
                }
            }
        }
        
        return $formatted;
    }
    
    /**
     * ดึง field name สำหรับคำถาม
     */
    private function get_field_name_for_question($qid) {
        // จะต้องดึงจาก mapping หรือโครงสร้างที่เก็บไว้
        // ตัวอย่างเบื้องต้น
        return 'Q' . $qid;
    }
    
    /**
     * ดึงข้อมูล review และ workflow status
     */
    private function get_review_data($response_id) {
        global $wpdb;
        
        // ข้อมูลเริ่มต้น
        $review_data = array(
            'status' => 'draft',
            'reviews' => array(),
            'approvals' => array(),
            'can_edit' => true,
            'can_review' => false,
            'can_approve' => false,
            'notes' => array()
        );
        
        if (!$response_id) {
            return $review_data;
        }
        
        // ดึงข้อมูลจาก audit table
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        if ($wpdb->get_var("SHOW TABLES LIKE '$audit_table'")) {
            $audit_logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $audit_table WHERE response_id = %s ORDER BY created_at DESC",
                $response_id
            ), ARRAY_A);
            
            if ($audit_logs) {
                foreach ($audit_logs as $log) {
                    if (in_array($log['action'], array('reviewed', 'approved', 'rejected'))) {
                        $review_data['reviews'][] = $log;
                    }
                }
                
                // กำหนดสถานะล่าสุด
                $latest_log = $audit_logs[0];
                $review_data['status'] = $latest_log['action'];
            }
        }
        
        // ตรวจสอบสิทธิ์ของผู้ใช้ปัจจุบัน
        $review_data['can_edit'] = current_user_can('edit_survey_responses');
        $review_data['can_review'] = current_user_can('review_survey_responses');
        $review_data['can_approve'] = current_user_can('approve_survey_responses');
        
        return $review_data;
    }
    
    /**
     * Enqueue assets สำหรับ frontend
     */
    public function enqueue_survey_assets() {
        wp_enqueue_style('tpak-native-survey', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/css/native-survey.css', array(), TPAK_DQ_SYSTEM_VERSION);
        wp_enqueue_script('tpak-native-survey', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/native-survey.js', array('jquery'), TPAK_DQ_SYSTEM_VERSION, true);
    }
    
    /**
     * Enqueue assets สำหรับ admin
     */
    public function enqueue_admin_survey_assets($hook) {
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_style('tpak-admin-native-survey', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/css/admin-native-survey.css', array(), TPAK_DQ_SYSTEM_VERSION);
        }
    }
}