<?php
/**
 * Complete test for JavaScript fixes
 */

// Include WordPress
require_once('../../../wp-config.php');

// Check if user is logged in and has admin privileges
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Test if classes exist
$classes_to_test = array(
    'TPAK_DQ_Workflow',
    'TPAK_DQ_Admin_Menu'
);

echo "<h1>TPAK DQ System - Complete Fix Test</h1>";

echo "<h2>1. Class Availability Test</h2>";
foreach ($classes_to_test as $class) {
    if (class_exists($class)) {
        echo "<p style='color: green;'>✓ Class $class exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Class $class does not exist</p>";
    }
}

echo "<h2>2. AJAX Handler Test</h2>";
$workflow = new TPAK_DQ_Workflow();
if (method_exists($workflow, 'admin_change_status')) {
    echo "<p style='color: green;'>✓ admin_change_status method exists in TPAK_DQ_Workflow</p>";
} else {
    echo "<p style='color: red;'>✗ admin_change_status method does not exist</p>";
}

$admin_menu = new TPAK_DQ_Admin_Menu();
if (method_exists($admin_menu, 'enqueue_admin_scripts')) {
    echo "<p style='color: green;'>✓ enqueue_admin_scripts method exists in TPAK_DQ_Admin_Menu</p>";
} else {
    echo "<p style='color: red;'>✗ enqueue_admin_scripts method does not exist</p>";
}

echo "<h2>3. WordPress Hook Test</h2>";
global $wp_filter;

// Check if AJAX actions are registered
$ajax_actions = array(
    'wp_ajax_tpak_admin_change_status',
    'wp_ajax_tpak_test_api',
    'wp_ajax_tpak_manual_import'
);

foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "<p style='color: green;'>✓ AJAX action $action is registered</p>";
    } else {
        echo "<p style='color: red;'>✗ AJAX action $action is not registered</p>";
    }
}

echo "<h2>4. File Existence Test</h2>";
$files_to_check = array(
    'assets/js/admin-script.js',
    'assets/css/admin-style.css',
    'admin/views/response-detail.php'
);

foreach ($files_to_check as $file) {
    $full_path = TPAK_DQ_SYSTEM_PLUGIN_DIR . $file;
    if (file_exists($full_path)) {
        echo "<p style='color: green;'>✓ File $file exists</p>";
    } else {
        echo "<p style='color: red;'>✗ File $file does not exist</p>";
    }
}

echo "<h2>5. JavaScript Test</h2>";
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .test-result { margin: 10px 0; padding: 10px; border-left: 4px solid; }
        .success { background: #dff0d8; border-color: #5cb85c; }
        .error { background: #f2dede; border-color: #d9534f; }
        .notice { padding: 10px; margin: 10px 0; border-left: 4px solid; }
        .notice-success { background: #dff0d8; border-color: #5cb85c; }
        .notice-error { background: #f2dede; border-color: #d9534f; }
    </style>
</head>
<body>
    <div id="js-test-results"></div>
    <div class="wp-header-end"></div>
    
    <script>
        // Set up WordPress AJAX variables
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.tpak_dq_ajax = {
            ajax_url: window.ajaxurl,
            nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
        };
    </script>
    
    <!-- Load the admin script -->
    <script src="assets/js/admin-script.js"></script>
    
    <script>
        jQuery(document).ready(function($) {
            var results = [];
            
            // Test 1: Check if tpak_dq_ajax is available
            if (typeof window.tpak_dq_ajax !== 'undefined' && window.tpak_dq_ajax.nonce) {
                results.push('<div class="test-result success">✓ tpak_dq_ajax is properly configured</div>');
            } else {
                results.push('<div class="test-result error">✗ tpak_dq_ajax is not properly configured</div>');
            }
            
            // Test 2: Check if global functions are available
            if (typeof window.changeStatusAdmin === 'function') {
                results.push('<div class="test-result success">✓ changeStatusAdmin global function is available</div>');
            } else {
                results.push('<div class="test-result error">✗ changeStatusAdmin global function is not available</div>');
            }
            
            if (typeof window.showNotification === 'function') {
                results.push('<div class="test-result success">✓ showNotification global function is available</div>');
            } else {
                results.push('<div class="test-result error">✗ showNotification global function is not available</div>');
            }
            
            // Test 3: Test AJAX call (with dummy data)
            $.ajax({
                url: window.tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_admin_change_status',
                    post_id: 999, // Dummy ID
                    new_status: 'pending_a',
                    comment: 'Test comment',
                    nonce: window.tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success === false && response.data && response.data.message) {
                        results.push('<div class="test-result success">✓ AJAX endpoint is responding (expected error for dummy data)</div>');
                    } else {
                        results.push('<div class="test-result error">✗ Unexpected AJAX response: ' + JSON.stringify(response) + '</div>');
                    }
                    $('#js-test-results').html(results.join(''));
                },
                error: function(xhr, status, error) {
                    results.push('<div class="test-result error">✗ AJAX call failed: ' + error + '</div>');
                    $('#js-test-results').html(results.join(''));
                }
            });
        });
    </script>
</body>
</html>