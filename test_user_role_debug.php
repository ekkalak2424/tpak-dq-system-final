<?php
/**
 * Debug User Role and Permissions
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

echo "<h1>TPAK DQ System - User Role Debug</h1>";

// Get current user info
$current_user = wp_get_current_user();
$user_id = get_current_user_id();

echo "<h2>Current User Information</h2>";
echo "<p><strong>User ID:</strong> " . $user_id . "</p>";
echo "<p><strong>Username:</strong> " . $current_user->user_login . "</p>";
echo "<p><strong>Display Name:</strong> " . $current_user->display_name . "</p>";
echo "<p><strong>Email:</strong> " . $current_user->user_email . "</p>";

echo "<h3>User Roles</h3>";
echo "<ul>";
foreach ($current_user->roles as $role) {
    echo "<li>" . $role . "</li>";
}
echo "</ul>";

echo "<h3>User Capabilities</h3>";
$important_caps = array('manage_options', 'edit_posts', 'publish_posts', 'delete_posts');
echo "<ul>";
foreach ($important_caps as $cap) {
    $has_cap = current_user_can($cap) ? 'Yes' : 'No';
    $color = current_user_can($cap) ? 'green' : 'red';
    echo "<li style='color: $color;'><strong>$cap:</strong> $has_cap</li>";
}
echo "</ul>";

// Test workflow class
echo "<h2>Workflow Class Test</h2>";
if (class_exists('TPAK_DQ_Workflow')) {
    echo "<p style='color: green;'>✓ TPAK_DQ_Workflow class exists</p>";
    
    $workflow = new TPAK_DQ_Workflow();
    
    // Test get_user_verification_role
    $user_role = $workflow->get_user_verification_role($user_id);
    echo "<p><strong>User Verification Role:</strong> " . ($user_role ? $user_role : 'None') . "</p>";
    
    // Test with a post
    $posts = get_posts(array(
        'post_type' => 'verification_batch',
        'posts_per_page' => 1,
        'post_status' => 'publish'
    ));
    
    if (!empty($posts)) {
        $test_post = $posts[0];
        echo "<h3>Testing with Post ID: " . $test_post->ID . "</h3>";
        
        $current_status = $workflow->get_batch_status($test_post->ID);
        echo "<p><strong>Current Status:</strong> " . ($current_status ? $current_status : 'No status') . "</p>";
        
        $available_actions = $workflow->get_available_actions($test_post->ID, $user_id);
        echo "<p><strong>Available Actions:</strong> " . (!empty($available_actions) ? implode(', ', $available_actions) : 'None') . "</p>";
        
        // Test specific actions
        $test_actions = array('approve_a', 'approve_batch_supervisor', 'reject_b', 'finalize', 'reject_c');
        echo "<h4>Action Permissions Test</h4>";
        echo "<ul>";
        foreach ($test_actions as $action) {
            $can_perform = $workflow->can_perform_action($test_post->ID, $action, $user_id);
            $color = $can_perform ? 'green' : 'red';
            $status = $can_perform ? 'Allowed' : 'Not Allowed';
            echo "<li style='color: $color;'><strong>$action:</strong> $status</li>";
        }
        echo "</ul>";
        
        // Test AJAX call simulation
        echo "<h3>AJAX Call Simulation</h3>";
        echo "<p>Simulating approve_a action...</p>";
        
        // Check nonce
        $nonce = wp_create_nonce('tpak_workflow_nonce');
        echo "<p><strong>Generated Nonce:</strong> " . $nonce . "</p>";
        
        // Simulate the validation that happens in approve_batch
        $validation_result = $workflow->validate_user_action($test_post->ID, 'approve_a', $user_id);
        if (method_exists($workflow, 'validate_user_action')) {
            echo "<p><strong>Validation Result:</strong> " . ($validation_result['valid'] ? 'Valid' : 'Invalid') . "</p>";
            if (!$validation_result['valid']) {
                echo "<p><strong>Validation Error:</strong> " . $validation_result['message'] . "</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ validate_user_action method not found</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ No verification_batch posts found</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ TPAK_DQ_Workflow class does not exist</p>";
}

// Test button click simulation
echo "<h2>Button Click Test</h2>";
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .button { padding: 8px 16px; margin: 5px; cursor: pointer; background: #0073aa; color: white; border: none; }
        .notice { padding: 10px; margin: 10px 0; border-left: 4px solid; }
        .notice-success { background: #dff0d8; border-color: #5cb85c; }
        .notice-error { background: #f2dede; border-color: #d9534f; }
    </style>
</head>
<body>
    <?php if (!empty($posts)): ?>
    <button class="button workflow-action-btn" 
            data-id="<?php echo $posts[0]->ID; ?>" 
            data-action="approve_a"
            onclick="testWorkflowAction()">
        Test Approve A Action
    </button>
    
    <div id="test-result"></div>
    <?php endif; ?>
    
    <script>
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.tpak_dq_ajax = {
            ajax_url: window.ajaxurl,
            nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
        };
        
        function testWorkflowAction() {
            console.log('Testing workflow action...');
            
            jQuery.ajax({
                url: window.tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_approve_batch',
                    post_id: <?php echo !empty($posts) ? $posts[0]->ID : 999; ?>,
                    comment: 'Test comment from debug page',
                    nonce: window.tpak_dq_ajax.nonce
                },
                success: function(response) {
                    console.log('Response:', response);
                    
                    var resultDiv = jQuery('#test-result');
                    if (response.success) {
                        resultDiv.html('<div class="notice notice-success"><p>✓ Success: ' + response.data.message + '</p></div>');
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p>✗ Error: ' + response.data.message + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', xhr, status, error);
                    jQuery('#test-result').html('<div class="notice notice-error"><p>✗ AJAX Error: ' + error + '</p></div>');
                }
            });
        }
    </script>
</body>
</html>