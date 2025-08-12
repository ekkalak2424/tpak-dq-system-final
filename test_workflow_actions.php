<?php
/**
 * Test Workflow Actions
 */

// Include WordPress
$wp_config_paths = array(
    '../../../wp-config.php',
    '../../../../wp-config.php',
    '../../../../../wp-config.php',
    dirname(__FILE__) . '/../../../wp-config.php',
    dirname(__FILE__) . '/../../../../wp-config.php'
);

$wp_loaded = false;
foreach ($wp_config_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Cannot find wp-config.php. Please check the file path.');
}

// Check if user is logged in and has admin privileges
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Get a real verification_batch post for testing
$test_posts = get_posts(array(
    'post_type' => 'verification_batch',
    'posts_per_page' => 1,
    'post_status' => 'publish'
));

$test_post_id = null;
$current_status = null;
$available_actions = array();

if (!empty($test_posts)) {
    $test_post_id = $test_posts[0]->ID;
    
    // Get current status and available actions
    $workflow = new TPAK_DQ_Workflow();
    $current_status = $workflow->get_batch_status($test_post_id);
    $available_actions = $workflow->get_available_actions($test_post_id);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>TPAK DQ System - Workflow Actions Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .button { padding: 8px 16px; margin: 5px; cursor: pointer; background: #0073aa; color: white; border: none; }
        .button-secondary { background: #666; }
        .button:disabled { background: #ccc; cursor: not-allowed; }
        .notice { padding: 10px; margin: 10px 0; border-left: 4px solid; }
        .notice-success { background: #dff0d8; border-color: #5cb85c; }
        .notice-error { background: #f2dede; border-color: #d9534f; }
        .notice-warning { background: #fcf8e3; border-color: #faebcc; }
        select, textarea { width: 200px; margin: 5px; padding: 5px; }
        textarea { height: 60px; }
        .post-info { background: #f9f9f9; padding: 10px; margin: 10px 0; border: 1px solid #ddd; }
        .debug-info { background: #e8f4fd; padding: 10px; margin: 10px 0; border: 1px solid #bee5eb; }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .dashicons { font-family: dashicons; }
        .comment-section { background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #e9ecef; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>TPAK DQ System - Workflow Actions Test</h1>
    
    <div class="debug-info">
        <h3>Debug Information</h3>
        <p><strong>WordPress AJAX URL:</strong> <?php echo admin_url('admin-ajax.php'); ?></p>
        <p><strong>Nonce:</strong> <?php echo wp_create_nonce('tpak_workflow_nonce'); ?></p>
        <p><strong>Current User ID:</strong> <?php echo get_current_user_id(); ?></p>
        <p><strong>User Can Manage Options:</strong> <?php echo current_user_can('manage_options') ? 'Yes' : 'No'; ?></p>
    </div>
    
    <?php if ($test_post_id): ?>
    <div class="post-info">
        <h3>Test Post Information</h3>
        <p><strong>Post ID:</strong> <?php echo $test_post_id; ?></p>
        <p><strong>Post Title:</strong> <?php echo get_the_title($test_post_id); ?></p>
        <p><strong>Current Status:</strong> <?php echo $current_status ? $current_status : 'No status'; ?></p>
        <p><strong>Available Actions:</strong> <?php echo !empty($available_actions) ? implode(', ', $available_actions) : 'None'; ?></p>
    </div>
    
    <div class="test-section">
        <h2>Workflow Actions Test</h2>
        <p>Testing workflow action buttons like in the response detail page</p>
        
        <?php if (!empty($available_actions)): ?>
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
                        data-id="<?php echo $test_post_id; ?>" 
                        data-action="<?php echo esc_attr($action); ?>"
                        style="width: 100%; margin-bottom: 10px;">
                    <span class="dashicons <?php echo $icon; ?>"></span>
                    <?php echo esc_html($action_name); ?>
                </button>
            <?php endforeach; ?>
            
            <!-- Comment section for reject actions -->
            <div class="comment-section" style="display: none;">
                <label for="action-comment">ความคิดเห็น (บังคับสำหรับการส่งกลับ):</label>
                <textarea id="action-comment" class="action-comment" rows="3" 
                          placeholder="กรุณาระบุเหตุผลในการส่งกลับ..."></textarea>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p>ไม่มี workflow actions ที่สามารถทำได้สำหรับสถานะปัจจุบัน</p>
                <p>สถานะปัจจุบัน: <strong><?php echo $current_status; ?></strong></p>
                <p>ลองเปลี่ยนสถานะเป็น "pending_a" หรือ "pending_b" เพื่อดู workflow actions</p>
            </div>
        <?php endif; ?>
        
        <div id="test-result"></div>
    </div>
    
    <?php else: ?>
    <div class="notice notice-warning">
        <p><strong>Warning:</strong> ไม่พบ verification_batch posts ในระบบ</p>
        <p>กรุณาสร้างข้อมูลทดสอบก่อนโดยไปที่หน้า Import</p>
    </div>
    <?php endif; ?>
    
    <div class="test-section">
        <h2>Manual Test Buttons</h2>
        <p>ทดสอบ workflow actions ด้วยข้อมูลจำลอง</p>
        
        <button class="button button-primary workflow-action-btn" 
                data-id="<?php echo $test_post_id ? $test_post_id : 999; ?>" 
                data-action="approve_a"
                style="width: 100%; margin-bottom: 10px;">
            <span class="dashicons dashicons-yes"></span>
            ยืนยันและส่งต่อให้ Supervisor (Test)
        </button>
        
        <button class="button button-secondary workflow-action-btn" 
                data-id="<?php echo $test_post_id ? $test_post_id : 999; ?>" 
                data-action="reject_b"
                style="width: 100%; margin-bottom: 10px;">
            <span class="dashicons dashicons-no"></span>
            ส่งกลับเพื่อแก้ไข (Test)
        </button>
        
        <!-- Comment section for reject actions -->
        <div class="comment-section" style="display: none;">
            <label for="action-comment">ความคิดเห็น (บังคับสำหรับการส่งกลับ):</label>
            <textarea id="action-comment" class="action-comment" rows="3" 
                      placeholder="กรุณาระบุเหตุผลในการส่งกลับ..."></textarea>
        </div>
        
        <div id="manual-test-result"></div>
    </div>
    
    <div class="wp-header-end"></div>

    <!-- Load the response detail script -->
    <script src="assets/js/response-detail.js"></script>
    
    <script>
        // Set up WordPress AJAX variables
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.tpak_dq_ajax = {
            ajax_url: window.ajaxurl,
            nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
        };
        
        console.log('WordPress AJAX URL:', window.ajaxurl);
        console.log('TPAK DQ AJAX Config:', window.tpak_dq_ajax);
        
        jQuery(document).ready(function($) {
            console.log('Workflow Actions Test loaded');
            
            // Test button detection
            var workflowButtons = $('.workflow-action-btn');
            console.log('Found workflow buttons:', workflowButtons.length);
            
            workflowButtons.each(function(index) {
                var button = $(this);
                console.log('Button ' + index + ':', {
                    id: button.data('id'),
                    action: button.data('action'),
                    text: button.text().trim(),
                    classes: button.attr('class')
                });
            });
            
            // Add click event for debugging
            $(document).on('click', '.workflow-action-btn', function() {
                console.log('Direct click event fired on workflow button');
            });
            
            // Test notification after 2 seconds
            setTimeout(function() {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('success', 'Workflow Actions Test page loaded successfully!');
                }
            }, 2000);
        });
    </script>
</body>
</html>