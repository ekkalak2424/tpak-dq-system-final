<?php
/**
 * Simple status change test page
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

// Get a test post
$posts = get_posts(array(
    'post_type' => 'verification_batch',
    'posts_per_page' => 1,
    'post_status' => 'publish'
));

if (empty($posts)) {
    echo "<p style='color: red;'>No verification batch posts found. Please import some data first.</p>\n";
    exit;
}

$test_post = $posts[0];
$workflow = new TPAK_DQ_Workflow();
$current_status = $workflow->get_batch_status($test_post->ID);

// Get all status terms
$status_terms = get_terms(array(
    'taxonomy' => 'verification_status',
    'hide_empty' => false
));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple Status Change Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, textarea, button { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #0073aa; color: white; cursor: pointer; }
        button:hover { background: #005a87; }
        button:disabled { background: #ccc; cursor: not-allowed; }
        .result { margin-top: 20px; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Status Change Test</h1>
        
        <div style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3>Test Post: <?php echo esc_html($test_post->post_title); ?></h3>
            <p><strong>Post ID:</strong> <?php echo $test_post->ID; ?></p>
            <p><strong>Current Status:</strong> <?php echo $current_status ? esc_html($current_status) : 'None'; ?></p>
        </div>
        
        <form id="status-change-form">
            <div class="form-group">
                <label for="new-status">New Status:</label>
                <select id="new-status" required>
                    <option value="">-- Select Status --</option>
                    <?php foreach ($status_terms as $term): ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($term->slug, $current_status); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="comment">Comment (Optional):</label>
                <textarea id="comment" rows="3" placeholder="Add a comment for this status change..."></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" id="change-status-btn">
                    <span id="btn-text">Change Status</span>
                    <span id="btn-spinner" style="display: none;">⟳</span>
                </button>
            </div>
        </form>
        
        <div id="result"></div>
        
        <div id="debug-log" style="background: #f0f0f0; padding: 10px; margin-top: 20px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">
            <h4>Debug Log:</h4>
            <div id="debug-content"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var postId = <?php echo $test_post->ID; ?>;
        var nonce = '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>';
        
        function debugLog(message) {
            console.log(message);
            $('#debug-content').append('<div>' + new Date().toLocaleTimeString() + ': ' + message + '</div>');
            $('#debug-log').scrollTop($('#debug-log')[0].scrollHeight);
        }
        
        debugLog('Page loaded successfully');
        debugLog('AJAX URL: ' + ajaxurl);
        debugLog('Post ID: ' + postId);
        debugLog('Nonce: ' + nonce);
        
        $('#status-change-form').on('submit', function(e) {
            e.preventDefault();
            
            var newStatus = $('#new-status').val();
            var comment = $('#comment').val();
            
            debugLog('Form submitted');
            debugLog('New Status: ' + newStatus);
            debugLog('Comment: ' + comment);
            
            if (!newStatus) {
                alert('Please select a new status');
                return;
            }
            
            if (confirm('Are you sure you want to change the status?')) {
                changeStatus(newStatus, comment);
            }
        });
        
        function changeStatus(newStatus, comment) {
            debugLog('changeStatus called');
            
            var btn = $('#change-status-btn');
            var btnText = $('#btn-text');
            var btnSpinner = $('#btn-spinner');
            
            // Show loading state
            btn.prop('disabled', true);
            btnText.hide();
            btnSpinner.show().addClass('spin');
            
            var requestData = {
                action: 'tpak_admin_change_status',
                post_id: postId,
                new_status: newStatus,
                comment: comment,
                nonce: nonce
            };
            
            debugLog('Sending AJAX request with data: ' + JSON.stringify(requestData));
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: requestData,
                success: function(response) {
                    debugLog('AJAX Success Response: ' + JSON.stringify(response));
                    
                    if (response.success) {
                        $('#result').html('<div class="result success">Success: ' + response.data.message + '</div>');
                        
                        // Reload page after 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#result').html('<div class="result error">Error: ' + (response.data.message || 'Unknown error') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    debugLog('AJAX Error: ' + status + ' - ' + error);
                    debugLog('Response Text: ' + xhr.responseText);
                    
                    $('#result').html('<div class="result error">Connection Error: ' + error + '</div>');
                },
                complete: function() {
                    // Reset button state
                    btn.prop('disabled', false);
                    btnText.show();
                    btnSpinner.hide().removeClass('spin');
                }
            });
        }
    });
    </script>
</body>
</html>