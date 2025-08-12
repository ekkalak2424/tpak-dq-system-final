<?php
/**
 * Test admin_change_status AJAX endpoint directly
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>Test Admin Change Status</h1>\n";

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

echo "<h2>Test Post Information</h2>\n";
echo "<p><strong>Post ID:</strong> " . $test_post->ID . "</p>\n";
echo "<p><strong>Post Title:</strong> " . esc_html($test_post->post_title) . "</p>\n";
echo "<p><strong>Current Status:</strong> " . ($current_status ? esc_html($current_status) : 'None') . "</p>\n";

// Test the AJAX endpoint directly
if (isset($_POST['test_change_status'])) {
    echo "<h2>Testing AJAX Endpoint</h2>\n";
    
    // Simulate AJAX request
    $_POST['action'] = 'tpak_admin_change_status';
    $_POST['post_id'] = $test_post->ID;
    $_POST['new_status'] = sanitize_text_field($_POST['new_status']);
    $_POST['comment'] = sanitize_textarea_field($_POST['comment']);
    $_POST['nonce'] = wp_create_nonce('tpak_workflow_nonce');
    
    echo "<p><strong>Simulating AJAX request with data:</strong></p>\n";
    echo "<pre>" . print_r($_POST, true) . "</pre>\n";
    
    // Call the method directly
    try {
        ob_start();
        $workflow->admin_change_status();
        $output = ob_get_clean();
        
        echo "<p><strong>Method output:</strong></p>\n";
        echo "<pre>" . esc_html($output) . "</pre>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Exception:</strong> " . esc_html($e->getMessage()) . "</p>\n";
    }
}

// Get all status terms
$status_terms = get_terms(array(
    'taxonomy' => 'verification_status',
    'hide_empty' => false
));
?>

<h2>Test Form</h2>
<form method="post" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd;">
    <p>
        <label for="new_status">New Status:</label>
        <select name="new_status" id="new_status" required>
            <option value="">-- Select Status --</option>
            <?php foreach ($status_terms as $term): ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($term->slug, $current_status); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    
    <p>
        <label for="comment">Comment:</label><br>
        <textarea name="comment" id="comment" rows="3" cols="50" placeholder="Optional comment..."></textarea>
    </p>
    
    <p>
        <input type="submit" name="test_change_status" value="Test Change Status" class="button button-primary">
    </p>
</form>

<h2>Check Error Log</h2>
<p>Check your WordPress error log for detailed information about the AJAX request processing.</p>

<h2>AJAX Actions Check</h2>
<?php
global $wp_filter;
$ajax_actions = array();

if (isset($wp_filter['wp_ajax_tpak_admin_change_status'])) {
    echo "<p style='color: green;'>✓ wp_ajax_tpak_admin_change_status is registered</p>\n";
    foreach ($wp_filter['wp_ajax_tpak_admin_change_status']->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function'])) {
                $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                $method = $callback['function'][1];
                echo "<p>Callback: {$class}::{$method} (priority: {$priority})</p>\n";
            }
        }
    }
} else {
    echo "<p style='color: red;'>✗ wp_ajax_tpak_admin_change_status is NOT registered</p>\n";
}
?>