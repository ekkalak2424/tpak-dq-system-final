<?php
/**
 * Test JavaScript functionality
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>TPAK DQ System - JavaScript Test</h1>\n";

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

<div style="max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border: 1px solid #ddd;">
    <h2>JavaScript Status Change Test</h2>
    
    <div style="background: #f9f9f9; padding: 15px; margin-bottom: 20px;">
        <h3>Test Post: <?php echo esc_html($test_post->post_title); ?> (ID: <?php echo $test_post->ID; ?>)</h3>
        <p><strong>Current Status:</strong> <?php echo $current_status ? esc_html($current_status) : 'None'; ?></p>
    </div>
    
    <div class="status-change-section">
        <h4>เปลี่ยนสถานะ</h4>
        
        <div class="admin-status-change">
            <label for="status-select">เลือกสถานะใหม่:</label>
            <select id="status-select" class="status-select">
                <option value="">-- เลือกสถานะ --</option>
                <?php foreach ($status_terms as $status_option): ?>
                    <option value="<?php echo esc_attr($status_option->slug); ?>" <?php selected($status_option->slug, $current_status); ?>>
                        <?php echo esc_html($status_option->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div class="admin-comment-section" style="margin-top: 10px;">
                <label for="admin-comment">หมายเหตุ (ไม่บังคับ):</label>
                <textarea id="admin-comment" class="admin-comment" rows="3" 
                          placeholder="เพิ่มหมายเหตุสำหรับการเปลี่ยนสถานะ..."></textarea>
            </div>
            
            <button class="button button-primary admin-change-status" 
                    data-id="<?php echo $test_post->ID; ?>" 
                    style="margin-top: 10px;">
                <span class="dashicons dashicons-update"></span>
                เปลี่ยนสถานะ
            </button>
        </div>
    </div>
    
    <div id="debug-info" style="background: #f0f0f0; padding: 10px; margin-top: 20px; font-family: monospace; font-size: 12px;">
        <h4>Debug Information:</h4>
        <div id="debug-output"></div>
    </div>
</div>

<style>
.status-select, .admin-comment {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

.admin-comment {
    resize: vertical;
    min-height: 60px;
}

.admin-change-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
jQuery(document).ready(function($) {
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    function debugLog(message) {
        console.log(message);
        $('#debug-output').append('<div>' + new Date().toLocaleTimeString() + ': ' + message + '</div>');
    }
    
    debugLog('JavaScript loaded successfully');
    debugLog('AJAX URL: ' + ajaxurl);
    
    // Test button click
    $(document).on('click', '.admin-change-status', function() {
        debugLog('Button clicked!');
        
        var button = $(this);
        var postId = button.data('id');
        var newStatus = $('#status-select').val();
        var comment = $('#admin-comment').val();
        
        debugLog('Post ID: ' + postId);
        debugLog('New Status: ' + newStatus);
        debugLog('Comment: ' + comment);
        
        if (!newStatus) {
            alert('กรุณาเลือกสถานะใหม่');
            return;
        }
        
        if (confirm('คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะ?')) {
            changeStatus(postId, newStatus, comment);
        }
    });
    
    function changeStatus(postId, newStatus, comment) {
        debugLog('changeStatus called');
        
        var button = $('.admin-change-status');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> กำลังเปลี่ยน...');
        
        var requestData = {
            action: 'tpak_admin_change_status',
            post_id: postId,
            new_status: newStatus,
            comment: comment,
            nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
        };
        
        debugLog('Request data: ' + JSON.stringify(requestData));
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                debugLog('AJAX success: ' + JSON.stringify(response));
                
                if (response.success) {
                    alert('สำเร็จ: ' + response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    alert('ข้อผิดพลาด: ' + (response.data.message || 'Unknown error'));
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                debugLog('AJAX error: ' + status + ' - ' + error);
                debugLog('Response text: ' + xhr.responseText);
                
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                button.prop('disabled', false).html(originalText);
            }
        });
    }
});
</script>