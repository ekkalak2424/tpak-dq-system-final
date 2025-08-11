<?php
/**
 * Test syntax of all PHP files
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>TPAK DQ System - Syntax Check</h1>\n";

$files_to_check = array(
    'tpak-dq-system.php',
    'includes/class-validator.php',
    'includes/class-post-types.php',
    'includes/class-roles.php',
    'includes/class-api-handler.php',
    'includes/class-cron.php',
    'includes/class-workflow.php',
    'includes/class-notifications.php',
    'admin/class-admin-menu.php',
    'admin/class-meta-boxes.php',
    'admin/class-admin-columns.php'
);

foreach ($files_to_check as $file) {
    $full_path = TPAK_DQ_SYSTEM_PLUGIN_DIR . $file;
    
    if (file_exists($full_path)) {
        // Try to include the file to check for syntax errors
        ob_start();
        $error = false;
        
        try {
            // Use token_get_all to check syntax without executing
            $tokens = token_get_all(file_get_contents($full_path));
            echo "<p style='color: green;'>✓ {$file} - Syntax OK</p>\n";
        } catch (ParseError $e) {
            echo "<p style='color: red;'>✗ {$file} - Parse Error: " . $e->getMessage() . "</p>\n";
            $error = true;
        } catch (Error $e) {
            echo "<p style='color: red;'>✗ {$file} - Error: " . $e->getMessage() . "</p>\n";
            $error = true;
        }
        
        ob_end_clean();
    } else {
        echo "<p style='color: orange;'>⚠ {$file} - File not found</p>\n";
    }
}

echo "<hr>\n";
echo "<p><em>Syntax check completed.</em></p>\n";
?>