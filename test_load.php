<?php
/**
 * Test loading of all classes
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>TPAK DQ System - Class Loading Test</h1>\n";

try {
    // Test loading validator class
    require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-validator.php');
    echo "<p style='color: green;'>✓ TPAK_DQ_Validator class loaded successfully</p>\n";
    
    // Test validator function
    $email_test = TPAK_DQ_Validator::validate_email('test@example.com');
    if ($email_test['valid']) {
        echo "<p style='color: green;'>✓ Validator function works correctly</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Validator function failed</p>\n";
    }
    
    // Test loading API handler
    require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-api-handler.php');
    $api_handler = new TPAK_DQ_API_Handler();
    echo "<p style='color: green;'>✓ TPAK_DQ_API_Handler class loaded successfully</p>\n";
    
    // Test loading workflow
    require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-workflow.php');
    $workflow = new TPAK_DQ_Workflow();
    echo "<p style='color: green;'>✓ TPAK_DQ_Workflow class loaded successfully</p>\n";
    
    // Test loading cron
    require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-cron.php');
    $cron = new TPAK_DQ_Cron();
    echo "<p style='color: green;'>✓ TPAK_DQ_Cron class loaded successfully</p>\n";
    
    // Test loading admin menu
    require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/class-admin-menu.php');
    $admin_menu = new TPAK_DQ_Admin_Menu();
    echo "<p style='color: green;'>✓ TPAK_DQ_Admin_Menu class loaded successfully</p>\n";
    
    // Test loading meta boxes
    require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/class-meta-boxes.php');
    $meta_boxes = new TPAK_DQ_Meta_Boxes();
    echo "<p style='color: green;'>✓ TPAK_DQ_Meta_Boxes class loaded successfully</p>\n";
    
    echo "<hr>\n";
    echo "<p style='color: green; font-weight: bold;'>✅ All classes loaded successfully! The plugin should work now.</p>\n";
    
} catch (Error $e) {
    echo "<p style='color: red;'>✗ Error loading classes: " . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception loading classes: " . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
}
?>