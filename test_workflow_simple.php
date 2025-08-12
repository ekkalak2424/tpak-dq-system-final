<?php
/**
 * Simple Workflow Test
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
    <title>TPAK DQ System - Simple Workflow Test</title>
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
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .dashicons { font-family: dashicons; }
        textarea { width: 100%; height: 60px; margin: 10px 0; padding: 5px; }
        .comment-section { background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #e9ecef; margin-top: 10px; display: none; }
    </style>
</head>
<body>
    <h1>TPAK DQ System - Simple Workflow Test</h1>
    
    <div class="test-section">
        <h2>Direct AJAX Test</h2>
        <p>ทดสอบการเรียก AJAX โดยตรงโดยไม่ผ่าน workflow validation</p>
        
        <button class="button button-primary" onclick="testApproveAction()">
            <span class="dashicons dashicons-yes"></span>
            Test Approve Action
        </button>
        
        <button class="button button-secondary" onclick="testRejectAction()">
            <span class="dashicons dashicons-no"></span>
            Test Reject Action
        </button>
        
        <div class="comment-section" id="comment-section">
            <label for="test-comment">ความคิดเห็น:</label>
            <textarea id="test-comment" placeholder="กรุณาระบุเหตุผล..."></textarea>
            <button class="button" onclick="submitRejectAction()">ยืนยันการส่งกลับ</button>
        </div>
        
        <div id="test-result"></div>
    </div>
    
    <div class="test-section">
        <h2>Manual Status Change Test</h2>
        <p>ทดสอบการเปลี่ยนสถานะโดยตรง</p>
        
        <button class="button" onclick="testStatusChange()">
            Test Manual Status Change
        </button>
        
        <div id="status-result"></div>
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
        
        function showNotification(type, message) {
            var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notification = jQuery('<div class="notice ' + notificationClass + '"><p>' + message + '</p></div>');
            
            jQuery('body').prepend(notification);
            
            setTimeout(function() {
                notification.fadeOut(function() {
                    jQuery(this).remove();
                });
            }, 5000);
        }
        
        function testApproveAction() {
            console.log('Testing approve action...');
            
            jQuery.ajax({
                url: window.tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_approve_batch',
                    post_id: 999, // Dummy ID
                    comment: 'Test approve comment',
                    nonce: window.tpak_dq_ajax.nonce
                },
                success: function(response) {
                    console.log('Approve response:', response);
                    
                    if (response.success) {
                        showNotification('success', 'Approve action successful: ' + response.data.message);
                    } else {
                        showNotification('error', 'Approve action failed: ' + response.data.message);
                    }
                    
                    jQuery('#test-result').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                },
                error: function(xhr, status, error) {
                    console.log('Approve error:', xhr, status, error);
                    showNotification('error', 'AJAX error: ' + error);
                    jQuery('#test-result').html('<div style="color: red;">AJAX Error: ' + error + '<br>Response: ' + xhr.responseText + '</div>');
                }
            });
        }
        
        function testRejectAction() {
            console.log('Testing reject action...');
            jQuery('#comment-section').show();
        }
        
        function submitRejectAction() {
            var comment = jQuery('#test-comment').val().trim();
            if (!comment) {
                alert('กรุณากรอกความคิดเห็น');
                return;
            }
            
            console.log('Submitting reject action with comment:', comment);
            
            jQuery.ajax({
                url: window.tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_reject_batch',
                    post_id: 999, // Dummy ID
                    comment: comment,
                    nonce: window.tpak_dq_ajax.nonce
                },
                success: function(response) {
                    console.log('Reject response:', response);
                    
                    if (response.success) {
                        showNotification('success', 'Reject action successful: ' + response.data.message);
                    } else {
                        showNotification('error', 'Reject action failed: ' + response.data.message);
                    }
                    
                    jQuery('#test-result').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                    jQuery('#comment-section').hide();
                },
                error: function(xhr, status, error) {
                    console.log('Reject error:', xhr, status, error);
                    showNotification('error', 'AJAX error: ' + error);
                    jQuery('#test-result').html('<div style="color: red;">AJAX Error: ' + error + '<br>Response: ' + xhr.responseText + '</div>');
                }
            });
        }
        
        function testStatusChange() {
            console.log('Testing status change...');
            
            jQuery.ajax({
                url: window.tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_admin_change_status',
                    post_id: 999, // Dummy ID
                    new_status: 'pending_a',
                    comment: 'Test status change',
                    nonce: window.tpak_dq_ajax.nonce
                },
                success: function(response) {
                    console.log('Status change response:', response);
                    
                    if (response.success) {
                        showNotification('success', 'Status change successful: ' + response.data.message);
                    } else {
                        showNotification('error', 'Status change failed: ' + response.data.message);
                    }
                    
                    jQuery('#status-result').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                },
                error: function(xhr, status, error) {
                    console.log('Status change error:', xhr, status, error);
                    showNotification('error', 'AJAX error: ' + error);
                    jQuery('#status-result').html('<div style="color: red;">AJAX Error: ' + error + '<br>Response: ' + xhr.responseText + '</div>');
                }
            });
        }
    </script>
</body>
</html>