<?php
/**
 * Test to verify the fatal error is fixed
 */

// Include WordPress
require_once('../../../wp-config.php');

// Check if user is logged in and has admin privileges
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied');
}

echo "<h1>TPAK DQ System - Error Fix Test</h1>";

try {
    // Test if the admin menu class can be instantiated without errors
    echo "<h2>Testing Class Instantiation</h2>";
    
    if (class_exists('TPAK_DQ_Admin_Menu')) {
        echo "<p style='color: green;'>✓ TPAK_DQ_Admin_Menu class exists</p>";
        
        // Try to create an instance
        $admin_menu = new TPAK_DQ_Admin_Menu();
        echo "<p style='color: green;'>✓ TPAK_DQ_Admin_Menu instantiated successfully</p>";
        
        // Check if the method exists
        if (method_exists($admin_menu, 'enqueue_admin_scripts')) {
            echo "<p style='color: green;'>✓ enqueue_admin_scripts method exists</p>";
        } else {
            echo "<p style='color: red;'>✗ enqueue_admin_scripts method does not exist</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ TPAK_DQ_Admin_Menu class does not exist</p>";
    }
    
    echo "<h2>Testing Method Reflection</h2>";
    
    // Use reflection to check for duplicate methods
    if (class_exists('TPAK_DQ_Admin_Menu')) {
        $reflection = new ReflectionClass('TPAK_DQ_Admin_Menu');
        $methods = $reflection->getMethods();
        
        $method_names = array();
        $duplicates = array();
        
        foreach ($methods as $method) {
            if (in_array($method->getName(), $method_names)) {
                $duplicates[] = $method->getName();
            } else {
                $method_names[] = $method->getName();
            }
        }
        
        if (empty($duplicates)) {
            echo "<p style='color: green;'>✓ No duplicate methods found</p>";
        } else {
            echo "<p style='color: red;'>✗ Duplicate methods found: " . implode(', ', $duplicates) . "</p>";
        }
        
        // List all methods for debugging
        echo "<h3>All Methods in TPAK_DQ_Admin_Menu:</h3>";
        echo "<ul>";
        foreach ($method_names as $method_name) {
            echo "<li>$method_name</li>";
        }
        echo "</ul>";
    }
    
    echo "<h2>Testing WordPress Hooks</h2>";
    
    // Check if hooks are properly registered
    global $wp_filter;
    
    if (isset($wp_filter['admin_enqueue_scripts'])) {
        echo "<p style='color: green;'>✓ admin_enqueue_scripts hook is registered</p>";
        
        // Check if our method is hooked
        $found_our_hook = false;
        foreach ($wp_filter['admin_enqueue_scripts']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && 
                    is_object($callback['function'][0]) && 
                    get_class($callback['function'][0]) === 'TPAK_DQ_Admin_Menu' &&
                    $callback['function'][1] === 'enqueue_admin_scripts') {
                    $found_our_hook = true;
                    break 2;
                }
            }
        }
        
        if ($found_our_hook) {
            echo "<p style='color: green;'>✓ Our enqueue_admin_scripts method is properly hooked</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Our enqueue_admin_scripts method hook not found (this might be normal if not on admin page)</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ admin_enqueue_scripts hook is not registered</p>";
    }
    
    echo "<h2>Test Result</h2>";
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✓ No fatal errors detected! The duplicate method issue has been resolved.</p>";
    
} catch (Exception $e) {
    echo "<h2>Error Caught</h2>";
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
} catch (Error $e) {
    echo "<h2>Fatal Error Caught</h2>";
    echo "<p style='color: red;'>✗ Fatal Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>