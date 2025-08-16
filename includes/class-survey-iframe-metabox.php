<?php
/**
 * TPAK DQ System - Survey iFrame Meta Box
 * 
 * แสดงแบบสอบถาม LimeSurvey แบบ iframe ใน WordPress meta box
 * วิธีนี้ง่ายที่สุดและแสดงผล 100% เหมือนต้นฉบับ
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Survey_Iframe_MetaBox {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_survey_meta_boxes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_get_survey_iframe', array($this, 'get_survey_iframe_ajax'));
        add_action('wp_ajax_nopriv_get_survey_iframe', array($this, 'get_survey_iframe_ajax'));
    }
    
    /**
     * เพิ่ม meta boxes
     */
    public function add_survey_meta_boxes() {
        // Meta box สำหรับ verification_batch
        add_meta_box(
            'tpak_survey_iframe',
            '📋 แบบสอบถาม LimeSurvey',
            array($this, 'render_survey_iframe_metabox'),
            'verification_batch',
            'normal',
            'high'
        );
        
        // Meta box สำหรับ post types อื่นๆ (ถ้าต้องการ)
        $post_types = array('post', 'page');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'tpak_survey_embed',
                '🎯 ฝังแบบสอบถาม',
                array($this, 'render_survey_embed_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render meta box แสดงแบบสอบถามแบบ iframe
     */
    public function render_survey_iframe_metabox($post) {
        // ดึง Survey ID จาก post meta
        $survey_id = get_post_meta($post->ID, '_lime_survey_id', true);
        $response_id = get_post_meta($post->ID, '_lime_response_id', true);
        
        if (!$survey_id) {
            echo '<p>ไม่พบ Survey ID สำหรับรายการนี้</p>';
            return;
        }
        
        $options = get_option('tpak_dq_system_options', array());
        $limesurvey_url = isset($options['limesurvey_url']) ? $options['limesurvey_url'] : '';
        
        if (!$limesurvey_url) {
            echo '<p>กรุณาตั้งค่า LimeSurvey URL ในหน้า Settings</p>';
            return;
        }
        
        ?>
        <div class="tpak-survey-iframe-container">
            <div class="survey-controls">
                <div class="control-row">
                    <label>Survey ID: <strong><?php echo esc_html($survey_id); ?></strong></label>
                    <?php if ($response_id): ?>
                        <label>Response ID: <strong><?php echo esc_html($response_id); ?></strong></label>
                    <?php endif; ?>
                </div>
                
                <div class="control-row">
                    <label for="survey_view_mode">รูปแบบการแสดงผล:</label>
                    <select id="survey_view_mode" class="survey-view-mode">
                        <option value="full">แบบสอบถามเต็ม</option>
                        <option value="preview">พรีวิว (อ่านอย่างเดียว)</option>
                        <option value="statistics">สถิติแบบสอบถาม</option>
                        <option value="responses" <?php echo $response_id ? 'selected' : ''; ?>>ดูคำตอบเฉพาะ</option>
                    </select>
                    
                    <button type="button" class="button refresh-iframe">🔄 รีเฟรช</button>
                    <button type="button" class="button open-external">🔗 เปิดในหน้าใหม่</button>
                </div>
                
                <div class="control-row">
                    <label>
                        <input type="checkbox" id="auto_resize" checked> ปรับขนาดอัตโนมัติ
                    </label>
                    <label>
                        <input type="checkbox" id="hide_header"> ซ่อน Header LimeSurvey
                    </label>
                </div>
            </div>
            
            <div class="iframe-wrapper">
                <iframe 
                    id="survey_iframe" 
                    src="" 
                    width="100%" 
                    height="600" 
                    frameborder="0"
                    sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-top-navigation"
                    loading="lazy">
                </iframe>
                
                <div class="iframe-overlay" style="display: none;">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>กำลังโหลดแบบสอบถาม...</p>
                    </div>
                </div>
            </div>
            
            <div class="survey-info">
                <details>
                    <summary>ข้อมูลเพิ่มเติม</summary>
                    <div class="info-grid">
                        <div><strong>Survey URL:</strong> <span id="current_survey_url">-</span></div>
                        <div><strong>Last Updated:</strong> <span id="last_updated"><?php echo current_time('Y-m-d H:i:s'); ?></span></div>
                        <div><strong>Mode:</strong> <span id="current_mode">-</span></div>
                    </div>
                </details>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var surveyId = '<?php echo esc_js($survey_id); ?>';
            var responseId = '<?php echo esc_js($response_id); ?>';
            var baseUrl = '<?php echo esc_js(rtrim($limesurvey_url, '/')); ?>';
            
            // สร้าง URL สำหรับ iframe
            function buildSurveyUrl(mode) {
                var url = baseUrl + '/index.php';
                var params = new URLSearchParams();
                
                switch(mode) {
                    case 'full':
                        params.append('r', 'survey/index');
                        params.append('sid', surveyId);
                        break;
                        
                    case 'preview':
                        params.append('r', 'survey/index');
                        params.append('sid', surveyId);
                        params.append('newtest', 'Y');
                        break;
                        
                    case 'statistics':
                        params.append('r', 'admin/statistics');
                        params.append('sid', surveyId);
                        break;
                        
                    case 'responses':
                        if (responseId) {
                            params.append('r', 'admin/responses/view');
                            params.append('surveyid', surveyId);
                            params.append('id', responseId);
                        } else {
                            params.append('r', 'admin/responses');
                            params.append('surveyid', surveyId);
                        }
                        break;
                }
                
                // เพิ่มพารามิเตอร์สำหรับ iframe
                params.append('iframe', '1');
                if ($('#hide_header').is(':checked')) {
                    params.append('hide_header', '1');
                }
                
                return url + '?' + params.toString();
            }
            
            // โหลด iframe
            function loadSurvey(mode) {
                var url = buildSurveyUrl(mode);
                
                $('.iframe-overlay').show();
                $('#survey_iframe').attr('src', url);
                $('#current_survey_url').text(url);
                $('#current_mode').text(mode);
                $('#last_updated').text(new Date().toLocaleString());
                
                // ซ่อน overlay เมื่อโหลดเสร็จ
                $('#survey_iframe').on('load', function() {
                    $('.iframe-overlay').hide();
                    
                    // Auto resize
                    if ($('#auto_resize').is(':checked')) {
                        autoResizeIframe();
                    }
                });
            }
            
            // Auto resize iframe
            function autoResizeIframe() {
                try {
                    var iframe = document.getElementById('survey_iframe');
                    var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    var height = iframeDoc.body.scrollHeight;
                    
                    if (height > 300) {
                        iframe.style.height = height + 'px';
                    }
                } catch(e) {
                    console.log('Cannot access iframe content for auto-resize');
                }
            }
            
            // Event handlers
            $('#survey_view_mode').on('change', function() {
                loadSurvey($(this).val());
            });
            
            $('.refresh-iframe').on('click', function() {
                loadSurvey($('#survey_view_mode').val());
            });
            
            $('.open-external').on('click', function() {
                var url = buildSurveyUrl($('#survey_view_mode').val());
                window.open(url, '_blank');
            });
            
            $('#hide_header, #auto_resize').on('change', function() {
                loadSurvey($('#survey_view_mode').val());
            });
            
            // โหลดครั้งแรก
            loadSurvey($('#survey_view_mode').val());
        });
        </script>
        
        <style>
        .tpak-survey-iframe-container {
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .survey-controls {
            background: #f9f9f9;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .control-row {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .control-row:last-child {
            margin-bottom: 0;
        }
        
        .survey-view-mode {
            min-width: 200px;
        }
        
        .iframe-wrapper {
            position: relative;
            background: white;
        }
        
        .iframe-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .loading-spinner {
            text-align: center;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .survey-info {
            background: #f9f9f9;
            padding: 10px 15px;
            border-top: 1px solid #ddd;
            font-size: 12px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        #survey_iframe {
            display: block;
            border: none;
            width: 100%;
            min-height: 400px;
        }
        </style>
        <?php
    }
    
    /**
     * Render meta box สำหรับฝังแบบสอบถาม
     */
    public function render_survey_embed_metabox($post) {
        $embedded_survey = get_post_meta($post->ID, '_embedded_survey_id', true);
        
        ?>
        <div class="survey-embed-controls">
            <label for="embed_survey_id">Survey ID:</label>
            <input type="number" 
                   id="embed_survey_id" 
                   name="embed_survey_id" 
                   value="<?php echo esc_attr($embedded_survey); ?>" 
                   placeholder="เช่น 836511" 
                   style="width: 100%; margin-bottom: 10px;">
            
            <button type="button" class="button button-primary embed-survey" style="width: 100%;">
                📋 ฝังแบบสอบถาม
            </button>
            
            <div class="embed-preview" style="margin-top: 15px; display: none;">
                <h4>พรีวิว:</h4>
                <div class="preview-content"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.embed-survey').on('click', function() {
                var surveyId = $('#embed_survey_id').val();
                if (!surveyId) {
                    alert('กรุณาระบุ Survey ID');
                    return;
                }
                
                // สร้าง shortcode preview
                var shortcode = '[tpak_survey id="' + surveyId + '"]';
                $('.preview-content').html(
                    '<p><strong>Shortcode:</strong></p>' +
                    '<input type="text" value="' + shortcode + '" readonly style="width: 100%;">' +
                    '<p><small>คัดลอก shortcode นี้ไปใส่ในเนื้อหาที่ต้องการ</small></p>'
                );
                $('.embed-preview').show();
                
                // บันทึก Survey ID
                $.post(ajaxurl, {
                    action: 'save_embedded_survey',
                    post_id: <?php echo $post->ID; ?>,
                    survey_id: surveyId,
                    nonce: '<?php echo wp_create_nonce('save_embedded_survey'); ?>'
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Enqueue scripts สำหรับ frontend
     */
    public function enqueue_scripts() {
        if (is_singular()) {
            wp_enqueue_script('tpak-survey-iframe', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/survey-iframe.js', array('jquery'), TPAK_DQ_SYSTEM_VERSION, true);
            wp_localize_script('tpak-survey-iframe', 'tpak_survey_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tpak_survey_nonce')
            ));
        }
    }
    
    /**
     * Enqueue scripts สำหรับ admin
     */
    public function enqueue_admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script('tpak-admin-survey', TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/admin-survey.js', array('jquery'), TPAK_DQ_SYSTEM_VERSION, true);
        }
    }
    
    /**
     * AJAX handler สำหรับดึง iframe URL
     */
    public function get_survey_iframe_ajax() {
        check_ajax_referer('tpak_survey_nonce', 'nonce');
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $mode = sanitize_text_field($_POST['mode']);
        $response_id = sanitize_text_field($_POST['response_id']);
        
        $options = get_option('tpak_dq_system_options', array());
        $limesurvey_url = rtrim($options['limesurvey_url'], '/');
        
        $url = $this->build_survey_url($limesurvey_url, $survey_id, $mode, $response_id);
        
        wp_send_json_success(array('url' => $url));
    }
    
    /**
     * สร้าง URL สำหรับแบบสอบถาม
     */
    private function build_survey_url($base_url, $survey_id, $mode = 'full', $response_id = null) {
        $url = $base_url . '/index.php';
        $params = array();
        
        switch ($mode) {
            case 'preview':
                $params = array(
                    'r' => 'survey/index',
                    'sid' => $survey_id,
                    'newtest' => 'Y'
                );
                break;
                
            case 'statistics':
                $params = array(
                    'r' => 'admin/statistics',
                    'sid' => $survey_id
                );
                break;
                
            case 'responses':
                if ($response_id) {
                    $params = array(
                        'r' => 'admin/responses/view',
                        'surveyid' => $survey_id,
                        'id' => $response_id
                    );
                } else {
                    $params = array(
                        'r' => 'admin/responses',
                        'surveyid' => $survey_id
                    );
                }
                break;
                
            default: // full
                $params = array(
                    'r' => 'survey/index',
                    'sid' => $survey_id
                );
        }
        
        return $url . '?' . http_build_query($params);
    }
}