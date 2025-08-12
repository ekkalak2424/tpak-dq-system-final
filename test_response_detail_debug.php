<?php
/**
 * Debug Response Detail Page JavaScript Loading
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

if (!empty($test_posts)) {
    $test_post_id = $test_posts[0]->ID;
    
    // Get current status
    $workflow = new TPAK_DQ_Workflow();
    $current_status = $workflow->get_batch_status($test_post_id);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>TPAK DQ System - Response Detail Debug</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .button { padding: 8px 16px; margin: 5px; cursor: pointer; background: #0073aa; color: white; border: none; }
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
    </style>
</head>
<body>
    <h1>TPAK DQ System - Response Detail Debug</h1>
    
    <div class="debug-info">
        <h3>Debug Information</h3>
        <p><strong>WordPress AJAX URL:</strong> <?php echo admin_url('admin-ajax.php'); ?></p>
        <p><strong>Plugin URL:</strong> <?php echo TPAK_DQ_SYSTEM_PLUGIN_URL; ?></p>
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
        <p><strong>Post Date:</strong> <?php echo get_the_date('Y-m-d H:i:s', $test_post_id); ?></p>
    </div>
    
    <div class="test-section">
        <h2>Status Change Test (Like Response Detail Page)</h2>
        <p>This simulates the exact same functionality as the response detail page</p>
        
        <label for="status-select">เลือกสถานะใหม่:</label>
        <select id="status-select">
            <option value="">-- เลือกสถานะ --</option>
            <option value="pending_a" <?php echo ($current_status === 'pending_a') ? 'selected' : ''; ?>>รอตรวจสอบขั้นที่ 1</option>
            <option value="pending_b" <?php echo ($current_status === 'pending_b') ? 'selected' : ''; ?>>รอตรวจสอบขั้นที่ 2</option>
            <option value="pending_c" <?php echo ($current_status === 'pending_c') ? 'selected' : ''; ?>>รอตรวจสอบขั้นที่ 3</option>
            <option value="finalized" <?php echo ($current_status === 'finalized') ? 'selected' : ''; ?>>เสร็จสมบูรณ์</option>
            <option value="finalized_by_sampling" <?php echo ($current_status === 'finalized_by_sampling') ? 'selected' : ''; ?>>เสร็จสมบูรณ์โดยการสุ่ม</option>
            <option value="rejected_by_b" <?php echo ($current_status === 'rejected_by_b') ? 'selected' : ''; ?>>ถูกส่งกลับโดย Supervisor</option>
            <option value="rejected_by_c" <?php echo ($current_status === 'rejected_by_c') ? 'selected' : ''; ?>>ถูกส่งกลับโดย Examiner</option>
        </select>
        
        <label for="admin-comment">หมายเหตุ (ไม่บังคับ):</label>
        <textarea id="admin-comment" placeholder="เพิ่มหมายเหตุสำหรับการเปลี่ยนสถานะ..."></textarea>
        
        <button class="button admin-change-status" data-id="<?php echo $test_post_id; ?>">
            <span class="dashicons dashicons-update"></span>
            เปลี่ยนสถานะ
        </button>
        
        <div id="test-result"></div>
    </div>
    
    <?php else: ?>
    <div class="notice notice-warning">
        <p><strong>Warning:</strong> ไม่พบ verification_batch posts ในระบบ</p>
        <p>กรุณาสร้างข้อมูลทดสอบก่อนโดยไปที่หน้า Import</p>
    </div>
    <?php endif; ?>
    
    <div class="test-section">
        <h2>JavaScript Loading Test</h2>
        <div id="js-test-results">
            <p>กำลังตรวจสอบ JavaScript...</p>
        </div>
    </div>
    
    <div class="wp-header-end"></div>

    <!-- Load the response detail script -->
    <script src="assets/js/response-detail.js"></script>
    
    <script>
        // Set up WordPress AJAX variables (same as response detail page)
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.tpak_dq_ajax = {
            ajax_url: window.ajaxurl,
            nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
        };
        
        console.log('WordPress AJAX URL:', window.ajaxurl);
        console.log('TPAK DQ AJAX Config:', window.tpak_dq_ajax);
        
        jQuery(document).ready(function($) {
            // Test JavaScript loading
            var results = [];
            
            // Test 1: Check if jQuery is loaded
            if (typeof jQuery !== 'undefined') {
                results.push('<p style="color: green;">✓ jQuery is loaded</p>');
            } else {
                results.push('<p style="color: red;">✗ jQuery is NOT loaded</p>');
            }
            
            // Test 2: Check if AJAX variables are set
            if (typeof window.ajaxurl !== 'undefined' && window.ajaxurl) {
                results.push('<p style="color: green;">✓ ajaxurl is set: ' + window.ajaxurl + '</p>');
            } else {
                results.push('<p style="color: red;">✗ ajaxurl is NOT set</p>');
            }
            
            if (typeof window.tpak_dq_ajax !== 'undefined' && window.tpak_dq_ajax.nonce) {
                results.push('<p style="color: green;">✓ tpak_dq_ajax is set with nonce</p>');
            } else {
                results.push('<p style="color: red;">✗ tpak_dq_ajax is NOT properly set</p>');
            }
            
            // Test 3: Check if global functions are available
            if (typeof window.changeStatusAdmin === 'function') {
                results.push('<p style="color: green;">✓ changeStatusAdmin function is available</p>');
            } else {
                results.push('<p style="color: red;">✗ changeStatusAdmin function is NOT available</p>');
            }
            
            if (typeof window.showNotification === 'function') {
                results.push('<p style="color: green;">✓ showNotification function is available</p>');
            } else {
                results.push('<p style="color: red;">✗ showNotification function is NOT available</p>');
            }
            
            // Test 4: Check if event handlers are working
            var buttonExists = $('.admin-change-status').length > 0;
            if (buttonExists) {
                results.push('<p style="color: green;">✓ Admin change status button found</p>');
            } else {
                results.push('<p style="color: red;">✗ Admin change status button NOT found</p>');
            }
            
            // Display results
            $('#js-test-results').html(results.join(''));
            
            // Test notification
            setTimeout(function() {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('success', 'JavaScript loading test completed successfully!');
                }
            }, 2000);
        });
    </script>
</body>
</html>