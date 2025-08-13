<?php
/**
 * Simple URL Validation Test
 */

// Mock WordPress functions for testing
if (!function_exists('__')) {
    function __($text, $domain = '') { return $text; }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) { return $url; }
}

// Include the validator class
require_once 'includes/class-validator.php';

echo "=== TPAK DQ System - URL Validation Test ===\n\n";

// Test the specific URL that was causing issues
$test_url = 'https://limesurvey.tpak.or.th/index.php/admin/remotecontrol';
$endpoints = array('/admin/remotecontrol', '/index.php/admin/remotecontrol', 'remotecontrol');

echo "Testing URL: $test_url\n";
echo "Required endpoints: " . implode(', ', $endpoints) . "\n\n";

$result = TPAK_DQ_Validator::validate_url($test_url, $endpoints);

echo "Result:\n";
echo "- Valid: " . ($result['valid'] ? 'YES' : 'NO') . "\n";
echo "- Message: " . ($result['message'] ?? 'OK') . "\n";

if ($result['valid']) {
    echo "- Sanitized URL: " . $result['url'] . "\n";
}

echo "\n=== Additional Test Cases ===\n";

$additional_tests = [
    'https://limesurvey.tpak.or.th/admin/remotecontrol',
    'https://demo.limesurvey.org/index.php/admin/remotecontrol',
    'https://survey.example.com/remotecontrol',
    'https://invalid-url.com',
    'not-a-url'
];

foreach ($additional_tests as $url) {
    $test_result = TPAK_DQ_Validator::validate_url($url, $endpoints);
    $status = $test_result['valid'] ? '✓' : '✗';
    echo "$status $url\n";
    if (!$test_result['valid']) {
        echo "  Error: " . $test_result['message'] . "\n";
    }
}

echo "\n=== Test Complete ===\n";
?>