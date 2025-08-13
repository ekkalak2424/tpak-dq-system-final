<?php
/**
 * Fix API URL in Database
 * This script will correct the LimeSurvey API URL in the database
 */

// Include WordPress
require_once '../../../wp-config.php';

echo "<h2>🔧 TPAK DQ System - Fix API URL</h2>";

// Get current options
$options = get_option('tpak_dq_system_options', array());

echo "<h3>📋 Current Settings:</h3>";
echo "<pre>";
print_r($options);
echo "</pre>";

// Check current URL
$current_url = isset($options['limesurvey_url']) ? $options['limesurvey_url'] : '';
echo "<p><strong>Current URL:</strong> " . esc_html($current_url) . "</p>";

// Correct URL
$correct_url = 'https://limesurvey.tpak.or.th/index.php/admin/remotecontrol';

if ($current_url === $correct_url) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "✅ <strong>URL is already correct!</strong>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24; margin: 10px 0;'>";
    echo "❌ <strong>URL needs to be fixed</strong><br>";
    echo "Current: <code>" . esc_html($current_url) . "</code><br>";
    echo "Should be: <code>" . esc_html($correct_url) . "</code>";
    echo "</div>";
    
    // Fix the URL
    $options['limesurvey_url'] = $correct_url;
    $updated = update_option('tpak_dq_system_options', $options);
    
    if ($updated) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
        echo "✅ <strong>URL has been fixed successfully!</strong><br>";
        echo "New URL: <code>" . esc_html($correct_url) . "</code>";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404; margin: 10px 0;'>";
        echo "⚠️ <strong>URL was already correct or update failed</strong>";
        echo "</div>";
    }
}

// Test the API connection
echo "<h3>🧪 Testing API Connection:</h3>";

// Include the API handler
require_once 'includes/class-api-handler.php';

$api = new TPAK_DQ_API_Handler();
$test_result = $api->test_connection();

if ($test_result['success']) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "✅ <strong>API Connection Successful!</strong><br>";
    echo "Message: " . esc_html($test_result['message']);
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>API Connection Failed</strong><br>";
    echo "Error: " . esc_html($test_result['message']);
    echo "</div>";
}

echo "<h3>📝 Next Steps:</h3>";
echo "<ol>";
echo "<li>Go back to the Import Data page</li>";
echo "<li>Try importing again</li>";
echo "<li>If you still get errors, check the LimeSurvey server logs</li>";
echo "<li>Make sure the survey ID (734631) exists and is active</li>";
echo "</ol>";

echo "<h3>🔍 Troubleshooting:</h3>";
echo "<ul>";
echo "<li><strong>Check Survey Status:</strong> Make sure survey 734631 is active in LimeSurvey</li>";
echo "<li><strong>Check Permissions:</strong> Make sure the admin user has access to the survey</li>";
echo "<li><strong>Check Date Range:</strong> Make sure there are responses in the selected date range</li>";
echo "<li><strong>Check Network:</strong> Make sure WordPress can reach the LimeSurvey server</li>";
echo "</ul>";
?>