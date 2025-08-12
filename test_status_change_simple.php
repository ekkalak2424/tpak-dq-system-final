<?php
/**
 * Simple Status Change Test
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>TPAK DQ System - Simple Status Change Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .button { padding: 8px 16px; margin: 5px; cursor: pointer; background: #0073aa; color: white; border: none; }
        .notice { padding: 10px; margin: 10px 0; border-left: 4px solid; }
        .notice-success { background: #dff0d8; border-color: #5cb85c; }
        .notice-error { background: #f2dede; border-color: #d9534f; }
        .dashicons { font-family: dashicons; }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        select, textarea { width: 200px; margin: 5px; padding: 5px; }
        textarea { height: 60px; }
    </style>
</head>
<body>
    <h1>TPAK DQ System - Simple Status Change Test</h1>
    
    <div class="test-section">
        <h2>Status Change Test</h2>
        <p>Testing the status change functionality with embedded JavaScript...</p>
        
        <select id="status-select">
            <option value="">-- เลือกสถานะ --</option>
            <option value="pending_a">รอตรวจสอบขั้นที่ 1</option>
            <option value="pending_b">รอตรวจสอบขั้นที่ 2</option>
            <option value="pending_c">รอตรวจสอบขั้นที่ 3</option>
            <option value="finalized">เสร็จสมบูรณ์</option>
        </select>
        
        <textarea id="admin-comment" placeholder="ความคิดเห็น (ไม่บังคับ)"></textarea>
        
        <button id="test-status-change" class="button">เปลี่ยนสถานะ (Test)</button>
        
        <div id="test-result"></div>
    </div>
    
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
        
        // Define the status change function directly
        function changeStatusAdmin(postId, newStatus, comment) {
            console.log('changeStatusAdmin called with:', postId, newStatus, comment);
            
            var $ = jQuery;
            var button = $('#test-status-change');
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
                    
                    var resultDiv = $('#test-result');
                    
                    if (response.success) {
                        resultDiv.html('<div class="notice notice-success"><p>✓ ' + response.data.message + '</p></div>');
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p>✗ ' + (response.data.message || 'Unknown error') + '</p></div>');
                    }
                    
                    button.prop('disabled', false).html(originalText);
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', xhr, status, error);
                    console.log('Response text:', xhr.responseText);
                    
                    var errorMsg = 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error;
                    if (xhr.responseText) {
                        errorMsg += '<br>Response: ' + xhr.responseText.substring(0, 200);
                    }
                    
                    $('#test-result').html('<div class="notice notice-error"><p>✗ ' + errorMsg + '</p></div>');
                    button.prop('disabled', false).html(originalText);
                }
            });
        }
        
        jQuery(document).ready(function($) {
            // Test button click
            $('#test-status-change').on('click', function(e) {
                e.preventDefault();
                console.log('Test status change button clicked');
                
                var newStatus = $('#status-select').val();
                var comment = $('#admin-comment').val();
                
                if (!newStatus) {
                    alert('กรุณาเลือกสถานะใหม่');
                    return;
                }
                
                if (confirm('คุณแน่ใจหรือไม่ที่จะทดสอบการเปลี่ยนสถานะ?')) {
                    // Use dummy post ID for testing
                    changeStatusAdmin(999, newStatus, comment);
                }
            });
            
            // Test AJAX configuration on load
            console.log('Testing AJAX configuration...');
            console.log('AJAX URL:', window.tpak_dq_ajax.ajax_url);
            console.log('Nonce:', window.tpak_dq_ajax.nonce ? 'Set' : 'Not set');
        });
    </script>
</body>
</html>