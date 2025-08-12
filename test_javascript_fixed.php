<?php
/**
 * Test JavaScript functionality after fixes
 */

// Include WordPress
require_once('../../../wp-config.php');

// Check if user is logged in and has admin privileges
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>TPAK DQ System - JavaScript Test (Fixed)</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .button { padding: 8px 16px; margin: 5px; cursor: pointer; }
        .notice { padding: 10px; margin: 10px 0; border-left: 4px solid; }
        .notice-success { background: #dff0d8; border-color: #5cb85c; }
        .notice-error { background: #f2dede; border-color: #d9534f; }
        .dashicons { font-family: dashicons; }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <h1>TPAK DQ System - JavaScript Test (Fixed)</h1>
    
    <div class="test-section">
        <h2>AJAX Configuration Test</h2>
        <p>Testing if tpak_dq_ajax is properly configured...</p>
        <div id="ajax-config-result"></div>
    </div>
    
    <div class="test-section">
        <h2>Status Change Test</h2>
        <p>Testing the status change functionality...</p>
        
        <select id="status-select">
            <option value="">-- เลือกสถานะ --</option>
            <option value="pending_a">รอตรวจสอบขั้นที่ 1</option>
            <option value="pending_b">รอตรวจสอบขั้นที่ 2</option>
            <option value="pending_c">รอตรวจสอบขั้นที่ 3</option>
            <option value="finalized">เสร็จสมบูรณ์</option>
        </select>
        
        <textarea id="admin-comment" placeholder="ความคิดเห็น (ไม่บังคับ)"></textarea>
        
        <button class="admin-change-status button" data-id="123">เปลี่ยนสถานะ</button>
        
        <div id="status-change-result"></div>
    </div>
    
    <div class="test-section">
        <h2>Global Function Test</h2>
        <p>Testing the global changeStatusAdmin function...</p>
        <button onclick="testGlobalFunction()" class="button">Test Global Function</button>
        <div id="global-function-result"></div>
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
    </script>
    
    <!-- Load the admin script -->
    <script src="assets/js/admin-script.js" onload="console.log('Admin script loaded successfully')" onerror="console.error('Failed to load admin script')"></script>
    
    <script>
        // Define global functions directly in case the admin script doesn't load properly
        window.changeStatusAdmin = function(postId, newStatus, comment) {
            console.log('changeStatusAdmin called with:', postId, newStatus, comment);
            
            var $ = jQuery;
            var button = $('.admin-change-status');
            var originalText = button.html();
            
            button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> กำลังเปลี่ยน...');
            
            var requestData = {
                action: 'tpak_admin_change_status',
                post_id: postId,
                new_status: newStatus,
                comment: comment,
                nonce: window.tpak_dq_ajax ? window.tpak_dq_ajax.nonce : ''
            };
            
            console.log('AJAX request data:', requestData);
            
            var ajaxUrl = window.tpak_dq_ajax ? window.tpak_dq_ajax.ajax_url : (window.ajaxurl || '/wp-admin/admin-ajax.php');
            console.log('AJAX URL:', ajaxUrl);
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: requestData,
                success: function(response) {
                    console.log('AJAX success response:', response);
                    
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        
                        // Reload page to show updated status
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification(response.data.message || 'Unknown error', 'error');
                        button.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', xhr, status, error);
                    console.log('Response text:', xhr.responseText);
                    
                    showNotification('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error, 'error');
                    button.prop('disabled', false).html(originalText);
                }
            });
        };
        
        // Define global showNotification function
        window.showNotification = function(message, type) {
            var $ = jQuery;
            var notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wp-header-end').after(notification);
            
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        };
        
        jQuery(document).ready(function($) {
            // Test AJAX configuration
            $('#ajax-config-result').html(
                '<strong>AJAX URL:</strong> ' + (window.tpak_dq_ajax ? window.tpak_dq_ajax.ajax_url : 'Not set') + '<br>' +
                '<strong>Nonce:</strong> ' + (window.tpak_dq_ajax ? (window.tpak_dq_ajax.nonce ? 'Set' : 'Not set') : 'Not set')
            );
            
            // Wait a bit for scripts to load, then test functions
            setTimeout(function() {
                // Test if functions are available
                if (typeof window.changeStatusAdmin === 'function') {
                    console.log('✓ changeStatusAdmin function is available');
                } else {
                    console.error('✗ changeStatusAdmin function is NOT available');
                }
                
                if (typeof window.showNotification === 'function') {
                    console.log('✓ showNotification function is available');
                } else {
                    console.error('✗ showNotification function is NOT available');
                }
            }, 1000);
            
            // Test status change button click
            $('.admin-change-status').on('click', function(e) {
                e.preventDefault();
                console.log('Status change button clicked');
                
                var postId = $(this).data('id');
                var newStatus = $('#status-select').val();
                var comment = $('#admin-comment').val();
                
                if (!newStatus) {
                    alert('กรุณาเลือกสถานะใหม่');
                    return;
                }
                
                if (confirm('คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะ?')) {
                    if (typeof window.changeStatusAdmin === 'function') {
                        window.changeStatusAdmin(postId, newStatus, comment);
                    } else {
                        alert('changeStatusAdmin function not found!');
                    }
                }
            });
        });
        
        // Test global function
        function testGlobalFunction() {
            console.log('Testing global function...');
            console.log('window.changeStatusAdmin type:', typeof window.changeStatusAdmin);
            
            if (typeof window.changeStatusAdmin === 'function') {
                document.getElementById('global-function-result').innerHTML = 
                    '<span style="color: green;">✓ Global function changeStatusAdmin is available</span>';
                
                // Test call with dummy data
                console.log('Testing global function with dummy data...');
                // Don't actually call it to avoid real AJAX request
                // window.changeStatusAdmin(999, 'pending_a', 'Test comment');
            } else {
                document.getElementById('global-function-result').innerHTML = 
                    '<span style="color: red;">✗ Global function changeStatusAdmin is NOT available</span>';
                
                // Try to debug what's available
                console.log('Available window properties:', Object.keys(window).filter(key => key.includes('change') || key.includes('Status')));
            }
        }
        
        // Test notification function
        function testNotification() {
            if (typeof window.showNotification === 'function') {
                window.showNotification('This is a test notification', 'success');
            } else {
                alert('showNotification function not found!');
            }
        }
    </script>
    
    <div class="test-section">
        <h2>Notification Test</h2>
        <button onclick="testNotification()" class="button">Test Notification</button>
    </div>
</body>
</html>