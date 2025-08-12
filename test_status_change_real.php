<?php
/**
 * Real Status Change Test with actual post ID
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
    <title>TPAK DQ System - Real Status Change Test</title>
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
    </style>
</head>
<body>
    <h1>TPAK DQ System - Real Status Change Test</h1>
    
    <?php if ($test_post_id): ?>
    <div class="post-info">
        <h3>Test Post Information</h3>
        <p><strong>Post ID:</strong> <?php echo $test_post_id; ?></p>
        <p><strong>Post Title:</strong> <?php echo get_the_title($test_post_id); ?></p>
        <p><strong>Current Status:</strong> <?php echo $current_status ? $current_status : 'No status'; ?></p>
        <p><strong>Post Date:</strong> <?php echo get_the_date('Y-m-d H:i:s', $test_post_id); ?></p>
    </div>
    
    <div class="test-section">
        <h2>Status Change Test</h2>
        <p>Testing with real post ID: <strong><?php echo $test_post_id; ?></strong></p>
        
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
        
        <textarea id="admin-comment" placeholder="ความคิดเห็น (ไม่บังคับ)"></textarea>
        
        <button id="test-status-change" class="button">เปลี่ยนสถานะ</button>
        
        <div id="test-result"></div>
    </div>
    
    <?php else: ?>
    <div class="notice notice-warning">
        <p><strong>Warning:</strong> ไม่พบ verification_batch posts ในระบบ กรุณาสร้างข้อมูลทดสอบก่อน</p>
        <p>คุณสามารถ:</p>
        <ul>
            <li>ไปที่หน้า Import เพื่อนำเข้าข้อมูลจาก LimeSurvey</li>
            <li>หรือสร้าง verification_batch post ด้วยตนเอง</li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>Test with Dummy Data</h2>
        <p>ทดสอบด้วยข้อมูลจำลอง (จะได้ "Invalid post ID" error ซึ่งเป็นเรื่องปกติ)</p>
        
        <select id="dummy-status-select">
            <option value="">-- เลือกสถานะ --</option>
            <option value="pending_a">รอตรวจสอบขั้นที่ 1</option>
            <option value="pending_b">รอตรวจสอบขั้นที่ 2</option>
            <option value="pending_c">รอตรวจสอบขั้นที่ 3</option>
            <option value="finalized">เสร็จสมบูรณ์</option>
        </select>
        
        <textarea id="dummy-comment" placeholder="ความคิดเห็น (ไม่บังคับ)"></textarea>
        
        <button id="test-dummy-change" class="button">ทดสอบด้วยข้อมูลจำลอง</button>
        
        <div id="dummy-result"></div>
    </div>
    <?php endif; ?>
    
    <div class="wp-header-end"></div>

    <script>
        // Set up WordPress AJAX variables
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.tpak_dq_ajax = {
            ajax_url: window.ajaxurl,
            nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
        };
        
        console.log('WordPress AJAX URL:', window.ajaxurl);
        console.log('TPAK DQ AJAX Config:', window.tpak_dq_ajax);
        
        // Define the status change function
        function changeStatusAdmin(postId, newStatus, comment, resultDiv, button) {
            console.log('changeStatusAdmin called with:', postId, newStatus, comment);
            
            var $ = jQuery;
            var originalText = button.html();
            
            button.prop('disabled', true).html('กำลังเปลี่ยน...');
            
            var requestData = {
                action: 'tpak_admin_change_status',
                post_id: postId,
                new_status: newStatus,
                comment: comment,
                nonce: window.tpak_dq_ajax.nonce
            };
            
            console.log('AJAX request data:', requestData);
            
            $.ajax({
                url: window.tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: requestData,
                success: function(response) {
                    console.log('AJAX success response:', response);
                    
                    if (response.success) {
                        resultDiv.html('<div class="notice notice-success"><p>✓ ' + response.data.message + '</p></div>');
                        
                        // If this is a real post, offer to reload
                        if (postId !== 999) {
                            setTimeout(function() {
                                if (confirm('สถานะเปลี่ยนแล้ว ต้องการรีโหลดหน้าเพื่อดูการเปลี่ยนแปลงหรือไม่?')) {
                                    location.reload();
                                }
                            }, 1000);
                        }
                    } else {
                        var errorClass = (response.data.message && response.data.message.includes('Invalid post ID')) ? 'notice-warning' : 'notice-error';
                        resultDiv.html('<div class="notice ' + errorClass + '"><p>⚠ ' + (response.data.message || 'Unknown error') + '</p></div>');
                    }
                    
                    button.prop('disabled', false).html(originalText);
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', xhr, status, error);
                    console.log('Response text:', xhr.responseText);
                    
                    var errorMsg = 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error;
                    if (xhr.responseText) {
                        errorMsg += '<br><small>Response: ' + xhr.responseText.substring(0, 200) + '</small>';
                    }
                    
                    resultDiv.html('<div class="notice notice-error"><p>✗ ' + errorMsg + '</p></div>');
                    button.prop('disabled', false).html(originalText);
                }
            });
        }
        
        jQuery(document).ready(function($) {
            // Real post test
            $('#test-status-change').on('click', function(e) {
                e.preventDefault();
                console.log('Real status change button clicked');
                
                var newStatus = $('#status-select').val();
                var comment = $('#admin-comment').val();
                
                if (!newStatus) {
                    alert('กรุณาเลือกสถานะใหม่');
                    return;
                }
                
                if (confirm('คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะของ Post ID <?php echo $test_post_id; ?>?')) {
                    changeStatusAdmin(<?php echo $test_post_id; ?>, newStatus, comment, $('#test-result'), $(this));
                }
            });
            
            // Dummy test
            $('#test-dummy-change').on('click', function(e) {
                e.preventDefault();
                console.log('Dummy status change button clicked');
                
                var newStatus = $('#dummy-status-select').val();
                var comment = $('#dummy-comment').val();
                
                if (!newStatus) {
                    alert('กรุณาเลือกสถานะใหม่');
                    return;
                }
                
                if (confirm('ทดสอบการเปลี่ยนสถานะด้วยข้อมูลจำลอง (Post ID 999)?')) {
                    changeStatusAdmin(999, newStatus, comment, $('#dummy-result'), $(this));
                }
            });
        });
    </script>
</body>
</html>