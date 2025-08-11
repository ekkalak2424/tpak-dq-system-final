<?php
/**
 * Test status change functionality
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>TPAK DQ System - Status Change Test</h1>\n";

try {
    // Test loading workflow class
    require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-workflow.php');
    $workflow = new TPAK_DQ_Workflow();
    echo "<p style='color: green;'>✓ Workflow class loaded successfully</p>\n";
    
    // Get all verification batch posts
    $posts = get_posts(array(
        'post_type' => 'verification_batch',
        'posts_per_page' => 5,
        'post_status' => 'publish'
    ));
    
    if (empty($posts)) {
        echo "<p style='color: orange;'>⚠ No verification batch posts found. Please import some data first.</p>\n";
    } else {
        echo "<h2>Available Verification Batches:</h2>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>ID</th><th>Title</th><th>Current Status</th><th>Available Actions</th></tr>\n";
        
        foreach ($posts as $post) {
            $current_status = $workflow->get_batch_status($post->ID);
            $available_actions = $workflow->get_available_actions($post->ID, get_current_user_id());
            
            echo "<tr>";
            echo "<td>" . $post->ID . "</td>";
            echo "<td>" . esc_html($post->post_title) . "</td>";
            echo "<td>";
            
            if ($current_status) {
                $status_term = get_term_by('slug', $current_status, 'verification_status');
                echo esc_html($status_term ? $status_term->name : $current_status);
            } else {
                echo "No status";
            }
            
            echo "</td>";
            echo "<td>";
            
            if (!empty($available_actions)) {
                foreach ($available_actions as $action) {
                    $action_name = $workflow->get_action_display_name($action);
                    echo "<span style='background: #0073aa; color: white; padding: 2px 6px; margin: 2px; border-radius: 3px; font-size: 11px;'>" . esc_html($action_name) . "</span> ";
                }
            } else {
                echo "No actions available";
            }
            
            echo "</td>";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
    }
    
    // Test status terms
    echo "<h2>Available Status Terms:</h2>\n";
    $status_terms = get_terms(array(
        'taxonomy' => 'verification_status',
        'hide_empty' => false
    ));
    
    if (!empty($status_terms)) {
        echo "<ul>\n";
        foreach ($status_terms as $term) {
            echo "<li><strong>" . esc_html($term->name) . "</strong> (slug: " . esc_html($term->slug) . ") - " . esc_html($term->description) . "</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p style='color: red;'>✗ No status terms found. The plugin may not be properly activated.</p>\n";
    }
    
    // Test AJAX endpoints
    echo "<h2>AJAX Endpoints Test:</h2>\n";
    echo "<p>The following AJAX actions should be registered:</p>\n";
    echo "<ul>\n";
    echo "<li>tpak_approve_batch</li>\n";
    echo "<li>tpak_approve_batch_supervisor</li>\n";
    echo "<li>tpak_reject_batch</li>\n";
    echo "<li>tpak_finalize_batch</li>\n";
    echo "<li>tpak_admin_change_status</li>\n";
    echo "</ul>\n";
    
    echo "<h2>Test Status Change Form:</h2>\n";
    if (!empty($posts)) {
        $test_post = $posts[0];
        $current_status = $workflow->get_batch_status($test_post->ID);
        
        echo "<form method='post' style='background: #f9f9f9; padding: 20px; border: 1px solid #ddd;'>\n";
        echo "<h3>Test Post: " . esc_html($test_post->post_title) . " (ID: " . $test_post->ID . ")</h3>\n";
        echo "<p><strong>Current Status:</strong> " . ($current_status ? esc_html($current_status) : 'None') . "</p>\n";
        
        echo "<label for='new_status'>Change to:</label>\n";
        echo "<select name='new_status' id='new_status'>\n";
        echo "<option value=''>-- Select Status --</option>\n";
        
        foreach ($status_terms as $term) {
            $selected = ($term->slug === $current_status) ? 'selected' : '';
            echo "<option value='" . esc_attr($term->slug) . "' $selected>" . esc_html($term->name) . "</option>\n";
        }
        
        echo "</select>\n";
        echo "<br><br>\n";
        echo "<label for='comment'>Comment:</label><br>\n";
        echo "<textarea name='comment' id='comment' rows='3' cols='50' placeholder='Optional comment...'></textarea>\n";
        echo "<br><br>\n";
        echo "<input type='hidden' name='test_post_id' value='" . $test_post->ID . "'>\n";
        echo "<input type='hidden' name='test_status_change' value='1'>\n";
        echo "<input type='submit' value='Test Status Change' class='button button-primary'>\n";
        echo "</form>\n";
        
        // Handle form submission
        if (isset($_POST['test_status_change']) && $_POST['test_status_change'] == '1') {
            $test_post_id = intval($_POST['test_post_id']);
            $new_status = sanitize_text_field($_POST['new_status']);
            $comment = sanitize_textarea_field($_POST['comment']);
            
            if ($test_post_id && $new_status) {
                echo "<h3>Test Result:</h3>\n";
                
                // Update status
                $result = $workflow->update_batch_status($test_post_id, $new_status);
                
                if ($result) {
                    // Add audit trail
                    $user = wp_get_current_user();
                    $workflow->add_audit_trail_entry($test_post_id, array(
                        'user_id' => get_current_user_id(),
                        'user_name' => $user->display_name,
                        'action' => 'test_status_change',
                        'comment' => 'Test status change: ' . $new_status . ($comment ? ' - ' . $comment : '')
                    ));
                    
                    echo "<p style='color: green;'>✓ Status changed successfully to: " . esc_html($new_status) . "</p>\n";
                } else {
                    echo "<p style='color: red;'>✗ Failed to change status</p>\n";
                }
            } else {
                echo "<p style='color: red;'>✗ Invalid post ID or status</p>\n";
            }
        }
    }
    
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
}

echo "<hr>\n";
echo "<p><em>Status change test completed.</em></p>\n";
?>