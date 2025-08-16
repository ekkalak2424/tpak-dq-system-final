<?php
/**
 * TPAK DQ System - Enhanced Response Viewer Integration
 * 
 * ระบบรวม Native Survey Renderer เข้ากับระบบเดิม
 * เพื่อให้ใช้งานได้แบบ seamless integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Enhanced_Response_Viewer {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // เพิ่มใน response detail page
        add_action('tpak_response_detail_tabs', array($this, 'add_native_tab'));
        add_action('tpak_response_detail_content', array($this, 'add_native_content'));
        
        // AJAX handlers
        add_action('wp_ajax_load_native_view', array($this, 'load_native_view'));
        add_action('wp_ajax_switch_to_native', array($this, 'switch_to_native'));
        
        // เพิ่ม shortcode สำหรับใช้ในที่อื่น
        add_shortcode('tpak_enhanced_survey', array($this, 'enhanced_survey_shortcode'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_integration_scripts'));
    }
    
    /**
     * เพิ่ม Native tab ใน response detail page
     */
    public function add_native_tab() {
        ?>
        <button class="nav-tab" id="native-tab" data-target="native-content">
            📋 แบบ Native 100%
        </button>
        <?php
    }
    
    /**
     * เพิ่ม Native content ใน response detail page
     */
    public function add_native_content() {
        global $post;
        
        if (!$post) return;
        
        $survey_id = get_post_meta($post->ID, '_lime_survey_id', true);
        $response_id = get_post_meta($post->ID, '_lime_response_id', true);
        
        ?>
        <div id="native-content" class="tab-content" style="display: none;">
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
                    <button type="button" class="button" id="sync-changes">
                        🔄 ซิงค์การเปลี่ยนแปลง
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
            
            <div id="comparison-view" style="display: none;">
                <div class="comparison-header">
                    <h4>เปรียบเทียบการแสดงผล</h4>
                </div>
                <div class="comparison-content">
                    <div class="comparison-side">
                        <h5>แบบเดิม</h5>
                        <div id="original-preview"></div>
                    </div>
                    <div class="comparison-side">
                        <h5>แบบ Native</h5>
                        <div id="native-preview"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var surveyId = '<?php echo esc_js($survey_id); ?>';
            var responseId = '<?php echo esc_js($response_id); ?>';
            var postId = '<?php echo esc_js($post->ID); ?>';
            
            // เปิดใช้งาน Native Mode
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
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('native_view_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#native-survey-container').html(response.data.html).show();
                            $('.native-status').html('✅ โหลดแล้ว').removeClass('loading').addClass('loaded');
                            button.text('✅ Native Mode เปิดใช้งานแล้ว').addClass('button-secondary').removeClass('button-primary');
                        } else {
                            alert('เกิดข้อผิดพลาด: ' + response.data);
                            button.prop('disabled', false).text('🚀 เปิดใช้งาน Native Mode');
                        }
                    },
                    error: function() {
                        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                        button.prop('disabled', false).text('🚀 เปิดใช้งาน Native Mode');
                    }
                });
            });
            
            // เปรียบเทียบ views
            $('#compare-views').on('click', function() {
                if ($('#comparison-view').is(':visible')) {
                    $('#comparison-view').hide();
                    $(this).text('🔍 เปรียบเทียบกับแบบเดิม');
                } else {
                    loadComparisonView();
                    $('#comparison-view').show();
                    $(this).text('❌ ปิดการเปรียบเทียบ');
                }
            });
            
            // ซิงค์การเปลี่ยนแปลง
            $('#sync-changes').on('click', function() {
                if (confirm('คุณต้องการซิงค์การเปลี่ยนแปลงจาก Native กลับไปยังระบบเดิมหรือไม่?')) {
                    syncChangesToOriginal();
                }
            });
            
            function loadComparisonView() {
                // โหลดข้อมูลสำหรับเปรียบเทียบ
                $('#original-preview').html($('#original-content').clone());
                $('#native-preview').html($('#native-survey-container').clone());
            }
            
            function syncChangesToOriginal() {
                // ดึงข้อมูลจาก Native form
                var nativeData = $('#native-survey-form').serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sync_changes',
                        native_data: nativeData,
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('sync_changes_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('ซิงค์การเปลี่ยนแปลงเรียบร้อย');
                            location.reload(); // รีโหลดหน้า
                        } else {
                            alert('เกิดข้อผิดพลาด: ' + response.data);
                        }
                    }
                });
            }
        });
        </script>
        
        <style>
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
        
        .comparison-view {
            margin-top: 20px;
        }
        
        .comparison-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        
        .comparison-side {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            background: #f9f9f9;
        }
        
        .comparison-side h5 {
            margin: 0 0 15px 0;
            text-align: center;
            background: #fff;
            padding: 10px;
            border-radius: 4px;
        }
        
        #native-survey-container {
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            background: #f8f9ff;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler สำหรับโหลด Native view
     */
    public function load_native_view() {
        check_ajax_referer('native_view_nonce', 'nonce');
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        $response_id = sanitize_text_field($_POST['response_id']);
        $post_id = intval($_POST['post_id']);
        
        try {
            // โหลด Native Survey Renderer
            require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-native-survey-renderer.php';
            $renderer = TPAK_Native_Survey_Renderer::getInstance();
            
            // สร้าง mock post object
            $post = (object) array(
                'ID' => $post_id
            );
            
            // Capture output
            ob_start();
            $renderer->render_native_survey_metabox($post);
            $html = ob_get_clean();
            
            wp_send_json_success(array(
                'html' => $html,
                'message' => 'โหลด Native view เรียบร้อย'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * Enhanced survey shortcode
     */
    public function enhanced_survey_shortcode($atts) {
        $atts = shortcode_atts(array(
            'survey_id' => '',
            'response_id' => '',
            'mode' => 'integrated', // integrated, native-only, comparison
            'height' => '600px'
        ), $atts);
        
        if (empty($atts['survey_id'])) {
            return '<p>กรุณาระบุ survey_id</p>';
        }
        
        ob_start();
        
        switch ($atts['mode']) {
            case 'native-only':
                $this->render_native_only($atts);
                break;
                
            case 'comparison':
                $this->render_comparison_mode($atts);
                break;
                
            default:
                $this->render_integrated_mode($atts);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render Native only mode
     */
    private function render_native_only($atts) {
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-native-survey-renderer.php';
        $renderer = TPAK_Native_Survey_Renderer::getInstance();
        
        // สร้าง mock post
        $post = (object) array(
            'ID' => 0
        );
        
        // Set survey ID และ response ID ใน $_POST สำหรับ renderer
        $_POST['survey_id'] = $atts['survey_id'];
        $_POST['response_id'] = $atts['response_id'];
        
        echo '<div class="shortcode-native-survey">';
        $renderer->render_native_survey_metabox($post);
        echo '</div>';
    }
    
    /**
     * Render Comparison mode
     */
    private function render_comparison_mode($atts) {
        ?>
        <div class="enhanced-survey-comparison">
            <div class="comparison-tabs">
                <button class="tab-btn active" data-target="original-tab">แบบเดิม</button>
                <button class="tab-btn" data-target="native-tab">แบบ Native 100%</button>
                <button class="tab-btn" data-target="side-by-side-tab">เปรียบเทียบ</button>
            </div>
            
            <div id="original-tab" class="tab-content active">
                <?php echo do_shortcode('[tpak_survey id="' . $atts['survey_id'] . '"]'); ?>
            </div>
            
            <div id="native-tab" class="tab-content">
                <?php $this->render_native_only($atts); ?>
            </div>
            
            <div id="side-by-side-tab" class="tab-content">
                <div class="side-by-side">
                    <div class="side">
                        <h4>แบบเดิม</h4>
                        <?php echo do_shortcode('[tpak_survey id="' . $atts['survey_id'] . '"]'); ?>
                    </div>
                    <div class="side">
                        <h4>แบบ Native 100%</h4>
                        <?php $this->render_native_only($atts); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.tab-btn').on('click', function() {
                var target = $(this).data('target');
                
                $('.tab-btn').removeClass('active');
                $('.tab-content').removeClass('active');
                
                $(this).addClass('active');
                $('#' + target).addClass('active');
            });
        });
        </script>
        
        <style>
        .enhanced-survey-comparison {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .comparison-tabs {
            display: flex;
            background: #f1f1f1;
            border-bottom: 1px solid #ddd;
        }
        
        .tab-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 500;
        }
        
        .tab-btn.active {
            background: white;
            border-bottom: 2px solid #667eea;
        }
        
        .tab-content {
            display: none;
            padding: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .side-by-side {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .side {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            background: #f9f9f9;
        }
        </style>
        <?php
    }
    
    /**
     * Render Integrated mode
     */
    private function render_integrated_mode($atts) {
        ?>
        <div class="enhanced-survey-integrated">
            <div class="integration-header">
                <h3>📋 แบบสอบถามแบบ Enhanced</h3>
                <div class="view-switcher">
                    <label>
                        <input type="radio" name="view_mode" value="original" checked>
                        แบบเดิม
                    </label>
                    <label>
                        <input type="radio" name="view_mode" value="native">
                        แบบ Native 100%
                    </label>
                </div>
            </div>
            
            <div id="original-view" class="view-content active">
                <?php echo do_shortcode('[tpak_survey id="' . $atts['survey_id'] . '"]'); ?>
            </div>
            
            <div id="native-view" class="view-content">
                <?php $this->render_native_only($atts); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('input[name="view_mode"]').on('change', function() {
                var mode = $(this).val();
                
                $('.view-content').removeClass('active');
                
                if (mode === 'native') {
                    $('#native-view').addClass('active');
                } else {
                    $('#original-view').addClass('active');
                }
            });
        });
        </script>
        
        <style>
        .enhanced-survey-integrated {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .integration-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .integration-header h3 {
            margin: 0;
            color: white;
        }
        
        .view-switcher label {
            margin-left: 15px;
            color: white;
        }
        
        .view-content {
            display: none;
            padding: 20px;
        }
        
        .view-content.active {
            display: block;
        }
        </style>
        <?php
    }
    
    /**
     * Enqueue integration scripts
     */
    public function enqueue_integration_scripts($hook) {
        if (strpos($hook, 'tpak-dq') !== false) {
            wp_enqueue_script(
                'tpak-enhanced-integration',
                TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/enhanced-integration.js',
                array('jquery'),
                TPAK_DQ_SYSTEM_VERSION,
                true
            );
            
            wp_localize_script('tpak-enhanced-integration', 'tpakIntegration', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonces' => array(
                    'native_view' => wp_create_nonce('native_view_nonce'),
                    'sync_changes' => wp_create_nonce('sync_changes_nonce')
                )
            ));
        }
    }
}