<?php
/**
 * Simple AJAX test
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

// Handle AJAX request
if (isset($_POST['action']) && $_POST['action'] === 'test_ajax') {
    wp_send_json_success(array('message' => 'AJAX is working!'));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>AJAX Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>AJAX Test</h1>
    
    <button id="test-ajax">Test AJAX</button>
    <div id="result"></div>
    
    <script>
    jQuery(document).ready(function($) {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $('#test-ajax').on('click', function() {
            console.log('Button clicked');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'test_ajax',
                    nonce: '<?php echo wp_create_nonce('test_nonce'); ?>'
                },
                success: function(response) {
                    console.log('Success:', response);
                    $('#result').html('<p style="color: green;">Success: ' + JSON.stringify(response) + '</p>');
                },
                error: function(xhr, status, error) {
                    console.log('Error:', xhr, status, error);
                    $('#result').html('<p style="color: red;">Error: ' + error + '</p>');
                }
            });
        });
    });
    </script>
</body>
</html>