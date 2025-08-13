<?php
/**
 * Test URL Validation for LimeSurvey API
 */

// Include WordPress
require_once '../../../wp-config.php';

// Include the validator class
require_once 'includes/class-validator.php';

echo "<h2>🧪 TPAK DQ System - URL Validation Test</h2>";

// Test URLs
$test_urls = [
    // Valid URLs
    'https://limesurvey.tpak.or.th/index.php/admin/remotecontrol' => true,
    'https://limesurvey.tpak.or.th/admin/remotecontrol' => true,
    'http://localhost/limesurvey/index.php/admin/remotecontrol' => true,
    'https://survey.example.com/index.php/admin/remotecontrol' => true,
    'https://demo.limesurvey.org/index.php/admin/remotecontrol' => true,
    
    // Invalid URLs
    'https://limesurvey.tpak.or.th/' => false,
    'https://limesurvey.tpak.or.th/admin/' => false,
    'not-a-url' => false,
    'https://google.com' => false,
    '' => false,
];

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th style='padding: 10px; text-align: left;'>URL</th>";
echo "<th style='padding: 10px; text-align: center;'>Expected</th>";
echo "<th style='padding: 10px; text-align: center;'>Result</th>";
echo "<th style='padding: 10px; text-align: center;'>Status</th>";
echo "<th style='padding: 10px; text-align: left;'>Message</th>";
echo "</tr>";

$total_tests = 0;
$passed_tests = 0;

foreach ($test_urls as $url => $expected) {
    $total_tests++;
    
    $result = TPAK_DQ_Validator::validate_url(
        $url, 
        array('/admin/remotecontrol', '/index.php/admin/remotecontrol', 'remotecontrol')
    );
    
    $passed = ($result['valid'] === $expected);
    if ($passed) $passed_tests++;
    
    $status_color = $passed ? 'green' : 'red';
    $status_icon = $passed ? '✅' : '❌';
    $expected_text = $expected ? 'Valid' : 'Invalid';
    $result_text = $result['valid'] ? 'Valid' : 'Invalid';
    
    echo "<tr>";
    echo "<td style='padding: 8px; font-family: monospace; font-size: 12px;'>" . esc_html($url) . "</td>";
    echo "<td style='padding: 8px; text-align: center;'>" . $expected_text . "</td>";
    echo "<td style='padding: 8px; text-align: center;'>" . $result_text . "</td>";
    echo "<td style='padding: 8px; text-align: center; color: $status_color;'>" . $status_icon . "</td>";
    echo "<td style='padding: 8px; font-size: 12px;'>" . esc_html($result['message'] ?? 'OK') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<div style='margin: 20px 0; padding: 15px; background: " . ($passed_tests === $total_tests ? '#d4edda' : '#f8d7da') . "; border-radius: 5px;'>";
echo "<h3>📊 Test Summary</h3>";
echo "<p><strong>Total Tests:</strong> $total_tests</p>";
echo "<p><strong>Passed:</strong> $passed_tests</p>";
echo "<p><strong>Failed:</strong> " . ($total_tests - $passed_tests) . "</p>";
echo "<p><strong>Success Rate:</strong> " . round(($passed_tests / $total_tests) * 100, 1) . "%</p>";
echo "</div>";

// Test the specific URL from the user
echo "<h3>🎯 Your Specific URL Test</h3>";
$your_url = 'https://limesurvey.tpak.or.th/index.php/admin/remotecontrol';
$your_result = TPAK_DQ_Validator::validate_url(
    $your_url, 
    array('/admin/remotecontrol', '/index.php/admin/remotecontrol', 'remotecontrol')
);

echo "<div style='padding: 15px; background: " . ($your_result['valid'] ? '#d4edda' : '#f8d7da') . "; border-radius: 5px; margin: 10px 0;'>";
echo "<p><strong>URL:</strong> " . esc_html($your_url) . "</p>";
echo "<p><strong>Status:</strong> " . ($your_result['valid'] ? '✅ Valid' : '❌ Invalid') . "</p>";
if (!$your_result['valid']) {
    echo "<p><strong>Error:</strong> " . esc_html($your_result['message']) . "</p>";
} else {
    echo "<p><strong>Message:</strong> URL is valid and ready to use!</p>";
}
echo "</div>";

echo "<h3>💡 Tips</h3>";
echo "<ul>";
echo "<li>Make sure your URL ends with <code>/admin/remotecontrol</code> or <code>/index.php/admin/remotecontrol</code></li>";
echo "<li>The URL should be accessible from your WordPress server</li>";
echo "<li>Make sure LimeSurvey RemoteControl 2 API is enabled</li>";
echo "<li>Test the connection after saving settings</li>";
echo "</ul>";
?>