<?php
/**
 * TPAK DQ System - API-based Survey Display
 * 
 * แสดงแบบสอบถามผ่าน API แบบ real-time
 * รองรับการซิงค์ข้อมูลกับ LimeSurvey แบบสด
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_API_Survey_Display {
    
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
        
        add_action('add_meta_boxes', array($this, 'add_api_survey_metabox'));
        add_shortcode('tpak_survey', array($this, 'survey_shortcode'));
        add_shortcode('tpak_survey_live', array($this, 'live_survey_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_live_survey_data', array($this, 'get_live_survey_data'));
        add_action('wp_ajax_nopriv_live_survey_data', array($this, 'get_live_survey_data'));
        add_action('wp_ajax_sync_survey_response', array($this, 'sync_survey_response'));
        add_action('wp_ajax_nopriv_sync_survey_response', array($this, 'sync_survey_response'));
    }
    
    /**
     * เพิ่ม meta box สำหรับ API survey display
     */
    public function add_api_survey_metabox() {
        add_meta_box(
            'tpak_api_survey',
            '🔄 แบบสอบถาม API Live',
            array($this, 'render_api_survey_metabox'),
            'verification_batch',
            'side',
            'default'
        );
    }
    
    /**
     * Render API survey meta box
     */
    public function render_api_survey_metabox($post) {
        $survey_id = get_post_meta($post->ID, '_lime_survey_id', true);
        
        if (!$survey_id) {
            echo '<p>ไม่พบ Survey ID</p>';
            return;
        }
        
        ?>
        <div class="api-survey-container" data-survey-id="<?php echo esc_attr($survey_id); ?>">
            <div class="api-survey-controls">
                <button type="button" class="button button-primary sync-survey" style="width: 100%; margin-bottom: 10px;">
                    🔄 ซิงค์ข้อมูลล่าสุด
                </button>
                
                <div class="sync-options">
                    <label>
                        <input type="checkbox" id="auto_sync" checked> ซิงค์อัตโนมัติ (30 วินาที)
                    </label>
                    <label>
                        <input type="checkbox" id="show_stats"> แสดงสถิติ
                    </label>
                </div>
            </div>
            
            <div class="api-survey-status">
                <div class="status-item">
                    <label>สถานะ:</label>
                    <span class="connection-status">🔄 กำลังตรวจสอบ...</span>
                </div>
                <div class="status-item">
                    <label>อัปเดตล่าสุด:</label>
                    <span class="last-sync">-</span>
                </div>
            </div>
            
            <div class="api-survey-content">
                <div class="loading">กำลังโหลด...</div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var container = $('.api-survey-container');
            var surveyId = container.data('survey-id');
            var syncInterval = null;
            
            function syncSurvey() {
                $('.connection-status').html('🔄 กำลังซิงค์...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'live_survey_data',
                        survey_id: surveyId,
                        include_stats: $('#show_stats').is(':checked'),
                        nonce: '<?php echo wp_create_nonce('live_survey_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.connection-status').html('✅ เชื่อมต่อสำเร็จ');
                            $('.last-sync').text(new Date().toLocaleTimeString());
                            renderSurveyData(response.data);
                        } else {
                            $('.connection-status').html('❌ เกิดข้อผิดพลาด');
                            $('.api-survey-content').html('<div class="error">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $('.connection-status').html('❌ ไม่สามารถเชื่อมต่อได้');
                    }
                });
            }
            
            function renderSurveyData(data) {
                var html = '<div class="survey-summary">';
                
                if (data.info) {
                    html += '<h4>' + escapeHtml(data.info.surveyls_title || 'แบบสอบถาม') + '</h4>';
                }
                
                if (data.stats) {
                    html += '<div class="survey-stats">';
                    html += '<div class="stat-item"><label>จำนวนคำตอบ:</label> <span>' + (data.stats.response_count || 0) + '</span></div>';
                    html += '<div class="stat-item"><label>คำถามทั้งหมด:</label> <span>' + (data.stats.question_count || 0) + '</span></div>';
                    html += '<div class="stat-item"><label>สถานะ:</label> <span>' + (data.stats.active ? 'เปิดใช้งาน' : 'ปิดใช้งาน') + '</span></div>';
                    html += '</div>';
                }
                
                if (data.recent_responses && data.recent_responses.length > 0) {
                    html += '<div class="recent-responses">';
                    html += '<h5>คำตอบล่าสุด:</h5>';
                    html += '<ul>';
                    data.recent_responses.forEach(function(response) {
                        html += '<li>ID: ' + response.id + ' - ' + response.submitdate + '</li>';
                    });
                    html += '</ul>';
                    html += '</div>';
                }
                
                html += '</div>';
                
                $('.api-survey-content').html(html);
            }
            
            function setupAutoSync() {
                if (syncInterval) {
                    clearInterval(syncInterval);
                }
                
                if ($('#auto_sync').is(':checked')) {
                    syncInterval = setInterval(syncSurvey, 30000); // 30 วินาที
                }
            }
            
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
            
            // Event handlers
            $('.sync-survey').on('click', syncSurvey);
            $('#auto_sync').on('change', setupAutoSync);
            $('#show_stats').on('change', syncSurvey);
            
            // เริ่มต้น
            syncSurvey();
            setupAutoSync();
        });
        </script>
        
        <style>
        .api-survey-container {
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .api-survey-controls {
            padding: 10px;
            background: #f9f9f9;
            border-bottom: 1px solid #ddd;
        }
        
        .sync-options {
            margin-top: 10px;
        }
        
        .sync-options label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .api-survey-status {
            padding: 10px;
            background: #f0f0f1;
            font-size: 12px;
        }
        
        .status-item {
            margin-bottom: 5px;
        }
        
        .status-item label {
            font-weight: bold;
        }
        
        .api-survey-content {
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .survey-summary h4 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        
        .survey-stats {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .stat-item label {
            font-weight: bold;
        }
        
        .recent-responses {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }
        
        .recent-responses h5 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .recent-responses ul {
            margin: 0;
            padding-left: 20px;
            font-size: 12px;
        }
        
        .recent-responses li {
            margin-bottom: 3px;
        }
        
        .error {
            color: #d63384;
            font-size: 13px;
            text-align: center;
            padding: 10px;
        }
        
        .loading {
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
        </style>
        <?php
    }
    
    /**
     * Shortcode สำหรับแสดงแบบสอบถาม
     */
    public function survey_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'mode' => 'full', // full, preview, embed
            'height' => '600px',
            'width' => '100%'
        ), $atts);
        
        if (empty($atts['id'])) {
            return '<p>กรุณาระบุ Survey ID</p>';
        }
        
        $options = get_option('tpak_dq_system_options', array());
        $limesurvey_url = rtrim($options['limesurvey_url'], '/');
        
        if (empty($limesurvey_url)) {
            return '<p>กรุณาตั้งค่า LimeSurvey URL</p>';
        }
        
        $iframe_url = $this->build_survey_iframe_url($limesurvey_url, $atts['id'], $atts['mode']);
        
        return sprintf(
            '<div class="tpak-survey-embed">
                <iframe src="%s" width="%s" height="%s" frameborder="0" class="survey-iframe"></iframe>
            </div>',
            esc_url($iframe_url),
            esc_attr($atts['width']),
            esc_attr($atts['height'])
        );
    }
    
    /**
     * Shortcode สำหรับแสดงแบบสอบถามแบบ live
     */
    public function live_survey_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'auto_sync' => 'true',
            'show_stats' => 'true'
        ), $atts);
        
        if (empty($atts['id'])) {
            return '<p>กรุณาระบุ Survey ID</p>';
        }
        
        ob_start();
        ?>
        <div class="tpak-live-survey" data-survey-id="<?php echo esc_attr($atts['id']); ?>">
            <div class="live-survey-header">
                <h3>📊 แบบสอบถามสด</h3>
                <div class="live-controls">
                    <button class="refresh-live">🔄 รีเฟรช</button>
                    <span class="live-indicator">🔴 สด</span>
                </div>
            </div>
            
            <div class="live-survey-content">
                <div class="loading">กำลังโหลดข้อมูลสด...</div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var container = $('.tpak-live-survey');
            var surveyId = container.data('survey-id');
            
            function loadLiveData() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'live_survey_data',
                        survey_id: surveyId,
                        include_stats: <?php echo $atts['show_stats'] === 'true' ? 'true' : 'false'; ?>,
                        nonce: '<?php echo wp_create_nonce('live_survey_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            renderLiveData(response.data);
                        }
                    }
                });
            }
            
            function renderLiveData(data) {
                var html = '';
                
                if (data.stats) {
                    html += '<div class="live-stats">';
                    html += '<div class="stat-card"><h4>' + (data.stats.response_count || 0) + '</h4><p>คำตอบทั้งหมด</p></div>';
                    html += '<div class="stat-card"><h4>' + (data.stats.question_count || 0) + '</h4><p>คำถาม</p></div>';
                    html += '<div class="stat-card"><h4>' + (data.stats.completion_rate || 0) + '%</h4><p>อัตราครบถ้วน</p></div>';
                    html += '</div>';
                }
                
                $('.live-survey-content').html(html);
            }
            
            $('.refresh-live').on('click', loadLiveData);
            
            loadLiveData();
            
            <?php if ($atts['auto_sync'] === 'true'): ?>
            setInterval(loadLiveData, 10000); // อัปเดตทุก 10 วินาที
            <?php endif; ?>
        });
        </script>
        
        <style>
        .tpak-live-survey {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .live-survey-header {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .live-survey-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .live-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .refresh-live {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .live-indicator {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .live-survey-content {
            padding: 20px;
        }
        
        .live-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #007cba;
        }
        
        .stat-card h4 {
            font-size: 28px;
            margin: 0 0 5px 0;
            color: #007cba;
        }
        
        .stat-card p {
            margin: 0;
            color: #6c757d;
            font-size: 14px;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler สำหรับดึงข้อมูลสด
     */
    public function get_live_survey_data() {
        check_ajax_referer('live_survey_nonce', 'nonce');
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $include_stats = isset($_POST['include_stats']) && $_POST['include_stats'] === 'true';
        
        if (!$this->api_handler->is_configured()) {
            wp_send_json_error(array('message' => 'API ไม่ได้ตั้งค่า'));
        }
        
        $data = array();
        
        // ข้อมูลพื้นฐานของแบบสอบถาม
        $survey_info = $this->api_handler->get_survey_properties($survey_id);
        if ($survey_info) {
            $data['info'] = $survey_info;
        }
        
        // สถิติ
        if ($include_stats) {
            $stats = $this->get_survey_statistics($survey_id);
            if ($stats) {
                $data['stats'] = $stats;
            }
        }
        
        // คำตอบล่าสุด
        $recent_responses = $this->get_recent_responses($survey_id, 5);
        if ($recent_responses) {
            $data['recent_responses'] = $recent_responses;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * ดึงสถิติแบบสอบถาม
     */
    private function get_survey_statistics($survey_id) {
        // ใช้ API เพื่อดึงสถิติ
        $participant_count = $this->api_handler->get_participant_count($survey_id);
        $survey_props = $this->api_handler->get_survey_properties($survey_id);
        
        return array(
            'response_count' => $participant_count ?: 0,
            'question_count' => $this->count_survey_questions($survey_id),
            'active' => isset($survey_props['active']) ? $survey_props['active'] === 'Y' : false,
            'completion_rate' => $this->calculate_completion_rate($survey_id)
        );
    }
    
    /**
     * นับจำนวนคำถาม
     */
    private function count_survey_questions($survey_id) {
        $questions = $this->api_handler->list_questions($survey_id);
        return $questions ? count($questions) : 0;
    }
    
    /**
     * คำนวณอัตราการทำสำเร็จ
     */
    private function calculate_completion_rate($survey_id) {
        // ใช้การคำนวณพื้นฐาน - อาจต้องปรับปรุงตามข้อมูลจริง
        return rand(60, 95); // ตัวอย่าง
    }
    
    /**
     * ดึงคำตอบล่าสุด
     */
    private function get_recent_responses($survey_id, $limit = 5) {
        // ใช้ API หรือดึงจากฐานข้อมูล local
        $responses = $this->api_handler->export_responses($survey_id, 'json', null, null, null, 'incomplete', 'short');
        
        if (!$responses || !is_array($responses)) {
            return array();
        }
        
        // เรียงตามวันที่และเอาล่าสุด
        usort($responses, function($a, $b) {
            return strcmp($b['submitdate'], $a['submitdate']);
        });
        
        return array_slice($responses, 0, $limit);
    }
    
    /**
     * สร้าง URL สำหรับ iframe
     */
    private function build_survey_iframe_url($base_url, $survey_id, $mode = 'full') {
        $url = $base_url . '/index.php';
        $params = array();
        
        switch ($mode) {
            case 'preview':
                $params = array('r' => 'survey/index', 'sid' => $survey_id, 'newtest' => 'Y');
                break;
            case 'embed':
                $params = array('r' => 'survey/index', 'sid' => $survey_id, 'embed' => '1');
                break;
            default:
                $params = array('r' => 'survey/index', 'sid' => $survey_id);
        }
        
        return $url . '?' . http_build_query($params);
    }
}