<?php
/**
 * Check Taxonomy and Terms
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

echo "<h1>TPAK DQ System - Taxonomy Check</h1>";

// Check if post type exists
echo "<h2>Post Type Check</h2>";
if (post_type_exists('verification_batch')) {
    echo "<p style='color: green;'>✓ Post type 'verification_batch' exists</p>";
} else {
    echo "<p style='color: red;'>✗ Post type 'verification_batch' does not exist</p>";
}

// Check if taxonomy exists
echo "<h2>Taxonomy Check</h2>";
if (taxonomy_exists('verification_status')) {
    echo "<p style='color: green;'>✓ Taxonomy 'verification_status' exists</p>";
} else {
    echo "<p style='color: red;'>✗ Taxonomy 'verification_status' does not exist</p>";
    
    // Try to register it
    echo "<p>Attempting to register taxonomy...</p>";
    $post_types = new TPAK_DQ_Post_Types();
    $post_types->register_taxonomies();
    
    if (taxonomy_exists('verification_status')) {
        echo "<p style='color: green;'>✓ Taxonomy registered successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to register taxonomy</p>";
    }
}

// Check terms
echo "<h2>Terms Check</h2>";
$terms = get_terms(array(
    'taxonomy' => 'verification_status',
    'hide_empty' => false
));

if (is_wp_error($terms)) {
    echo "<p style='color: red;'>✗ Error getting terms: " . $terms->get_error_message() . "</p>";
} elseif (empty($terms)) {
    echo "<p style='color: orange;'>⚠ No terms found in verification_status taxonomy</p>";
    
    // Create default terms
    echo "<p>Creating default terms...</p>";
    $default_terms = array(
        'pending_a' => 'รอการตรวจสอบ A',
        'pending_b' => 'รอการตรวจสอบ B', 
        'pending_c' => 'รอการตรวจสอบ C',
        'finalized' => 'เสร็จสมบูรณ์',
        'finalized_by_sampling' => 'เสร็จสมบูรณ์โดยการสุ่ม',
        'rejected_by_b' => 'ถูกส่งกลับโดย B',
        'rejected_by_c' => 'ถูกส่งกลับโดย C'
    );
    
    foreach ($default_terms as $slug => $name) {
        $result = wp_insert_term($name, 'verification_status', array('slug' => $slug));
        if (is_wp_error($result)) {
            echo "<p style='color: red;'>✗ Failed to create term '$slug': " . $result->get_error_message() . "</p>";
        } else {
            echo "<p style='color: green;'>✓ Created term '$slug': $name</p>";
        }
    }
    
    // Re-check terms
    $terms = get_terms(array(
        'taxonomy' => 'verification_status',
        'hide_empty' => false
    ));
} else {
    echo "<p style='color: green;'>✓ Found " . count($terms) . " terms in verification_status taxonomy:</p>";
    echo "<ul>";
    foreach ($terms as $term) {
        echo "<li><strong>" . $term->slug . "</strong>: " . $term->name . " (ID: " . $term->term_id . ")</li>";
    }
    echo "</ul>";
}

// Check posts
echo "<h2>Posts Check</h2>";
$posts = get_posts(array(
    'post_type' => 'verification_batch',
    'posts_per_page' => 5,
    'post_status' => 'publish'
));

if (empty($posts)) {
    echo "<p style='color: orange;'>⚠ No verification_batch posts found</p>";
} else {
    echo "<p style='color: green;'>✓ Found " . count($posts) . " verification_batch posts:</p>";
    echo "<ul>";
    foreach ($posts as $post) {
        $workflow = new TPAK_DQ_Workflow();
        $status = $workflow->get_batch_status($post->ID);
        echo "<li><strong>ID " . $post->ID . "</strong>: " . $post->post_title . " (Status: " . ($status ? $status : 'No status') . ")</li>";
    }
    echo "</ul>";
}

// Test workflow class
echo "<h2>Workflow Class Check</h2>";
if (class_exists('TPAK_DQ_Workflow')) {
    echo "<p style='color: green;'>✓ TPAK_DQ_Workflow class exists</p>";
    
    $workflow = new TPAK_DQ_Workflow();
    
    if (!empty($posts)) {
        $test_post = $posts[0];
        echo "<p>Testing with post ID: " . $test_post->ID . "</p>";
        
        $available_actions = $workflow->get_available_actions($test_post->ID);
        echo "<p>Available actions: " . (!empty($available_actions) ? implode(', ', $available_actions) : 'None') . "</p>";
        
        // Try to set status
        $result = $workflow->update_batch_status($test_post->ID, 'pending_a');
        if ($result) {
            echo "<p style='color: green;'>✓ Successfully set status to pending_a</p>";
            
            $new_status = $workflow->get_batch_status($test_post->ID);
            echo "<p>New status: " . ($new_status ? $new_status : 'Still no status') . "</p>";
            
            $new_actions = $workflow->get_available_actions($test_post->ID);
            echo "<p>New available actions: " . (!empty($new_actions) ? implode(', ', $new_actions) : 'None') . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to set status</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ TPAK_DQ_Workflow class does not exist</p>";
}

echo "<h2>Fix Actions</h2>";
echo "<p><a href='?fix=1' style='background: #0073aa; color: white; padding: 10px; text-decoration: none;'>Fix All Issues</a></p>";

if (isset($_GET['fix']) && $_GET['fix'] == '1') {
    echo "<h3>Fixing Issues...</h3>";
    
    // Register post types and taxonomies
    $post_types = new TPAK_DQ_Post_Types();
    $post_types->register_post_types();
    $post_types->register_taxonomies();
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    echo "<p style='color: green;'>✓ Post types and taxonomies registered</p>";
    echo "<p style='color: green;'>✓ Rewrite rules flushed</p>";
    echo "<p><a href='?' style='background: #0073aa; color: white; padding: 10px; text-decoration: none;'>Refresh Page</a></p>";
}
?>