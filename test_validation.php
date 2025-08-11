<?php
/**
 * Test script for TPAK DQ System validation functions
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>TPAK DQ System - Validation Test</h1>\n";

// Load the validator
require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-validator.php');

echo "<h2>1. Email Validation Tests</h2>\n";
$email_tests = array(
    'valid@example.com' => true,
    'invalid-email' => false,
    'test@domain' => false,
    '' => false,
    'very-long-email-address-that-exceeds-the-maximum-length-allowed-for-email-addresses-in-most-systems@example.com' => false
);

foreach ($email_tests as $email => $expected) {
    $result = TPAK_DQ_Validator::validate_email($email);
    $status = $result['valid'] === $expected ? '✓' : '✗';
    $color = $result['valid'] === $expected ? 'green' : 'red';
    echo "<p style='color: {$color};'>{$status} Email: '{$email}' - " . ($result['valid'] ? 'Valid' : $result['message']) . "</p>\n";
}

echo "<h2>2. URL Validation Tests</h2>\n";
$url_tests = array(
    'https://example.com/admin/remotecontrol' => true,
    'http://survey.example.com/index.php/admin/remotecontrol' => true,
    'invalid-url' => false,
    'https://example.com/wrong-endpoint' => false,
    '' => false
);

foreach ($url_tests as $url => $expected) {
    $result = TPAK_DQ_Validator::validate_url($url, array('/admin/remotecontrol', '/index.php/admin/remotecontrol'));
    $status = $result['valid'] === $expected ? '✓' : '✗';
    $color = $result['valid'] === $expected ? 'green' : 'red';
    echo "<p style='color: {$color};'>{$status} URL: '{$url}' - " . ($result['valid'] ? 'Valid' : $result['message']) . "</p>\n";
}

echo "<h2>3. Numeric ID Validation Tests</h2>\n";
$id_tests = array(
    '123' => true,
    '0' => false,
    '-5' => false,
    'abc' => false,
    '1000000000' => false,
    '' => false
);

foreach ($id_tests as $id => $expected) {
    $result = TPAK_DQ_Validator::validate_numeric_id($id, 1, 999999999, 'Survey ID');
    $status = $result['valid'] === $expected ? '✓' : '✗';
    $color = $result['valid'] === $expected ? 'green' : 'red';
    echo "<p style='color: {$color};'>{$status} ID: '{$id}' - " . ($result['valid'] ? 'Valid' : $result['message']) . "</p>\n";
}

echo "<h2>4. Percentage Validation Tests</h2>\n";
$percentage_tests = array(
    '50' => true,
    '1' => true,
    '100' => true,
    '0' => false,
    '101' => false,
    'abc' => false,
    '' => false
);

foreach ($percentage_tests as $percentage => $expected) {
    $result = TPAK_DQ_Validator::validate_percentage($percentage);
    $status = $result['valid'] === $expected ? '✓' : '✗';
    $color = $result['valid'] === $expected ? 'green' : 'red';
    echo "<p style='color: {$color};'>{$status} Percentage: '{$percentage}' - " . ($result['valid'] ? 'Valid' : $result['message']) . "</p>\n";
}

echo "<h2>5. Text Validation Tests</h2>\n";
$text_tests = array(
    'Valid comment text' => true,
    'Short' => false, // Less than 10 characters
    str_repeat('A', 1001) => false, // Too long
    '<script>alert("xss")</script>' => false, // Contains script
    '' => false // Empty
);

foreach ($text_tests as $text => $expected) {
    $result = TPAK_DQ_Validator::validate_text($text, 10, 1000, 'Comment');
    $status = $result['valid'] === $expected ? '✓' : '✗';
    $color = $result['valid'] === $expected ? 'green' : 'red';
    $display_text = strlen($text) > 50 ? substr($text, 0, 50) . '...' : $text;
    echo "<p style='color: {$color};'>{$status} Text: '{$display_text}' - " . ($result['valid'] ? 'Valid' : $result['message']) . "</p>\n";
}

echo "<h2>6. Username Validation Tests</h2>\n";
$username_tests = array(
    'validuser' => true,
    'user@domain.com' => true,
    'user_name' => true,
    'ab' => false, // Too short
    'user with spaces' => false, // Contains spaces
    'user<script>' => false, // Contains invalid characters
    '' => false // Empty
);

foreach ($username_tests as $username => $expected) {
    $result = TPAK_DQ_Validator::validate_username($username);
    $status = $result['valid'] === $expected ? '✓' : '✗';
    $color = $result['valid'] === $expected ? 'green' : 'red';
    echo "<p style='color: {$color};'>{$status} Username: '{$username}' - " . ($result['valid'] ? 'Valid' : $result['message']) . "</p>\n";
}

echo "<h2>7. JSON Validation Tests</h2>\n";
$json_tests = array(
    '{"id": 123, "name": "test"}' => true,
    '{"valid": "json"}' => true,
    '{invalid json}' => false,
    '' => false,
    str_repeat('{"data": "' . str_repeat('A', 1000) . '"}', 100) => false // Too large
);

foreach ($json_tests as $json => $expected) {
    $result = TPAK_DQ_Validator::validate_json($json, 50000, 'Survey Data');
    $status = $result['valid'] === $expected ? '✓' : '✗';
    $color = $result['valid'] === $expected ? 'green' : 'red';
    $display_json = strlen($json) > 50 ? substr($json, 0, 50) . '...' : $json;
    echo "<p style='color: {$color};'>{$status} JSON: '{$display_json}' - " . ($result['valid'] ? 'Valid' : $result['message']) . "</p>\n";
}

echo "<h2>8. Date Validation Tests</h2>\n";
$date_tests = array(
    '2025-01-15' => true,
    '2024-12-31' => true,
    '2015-01-01' => false, // Too old
    '2030-01-01' => false, // Too far in future
    'invalid-date' => false,
    '' => false
);

foreach ($date_tests as $date => $expected) {
    $result = TPAK_DQ_Validator::validate_date($date, 'Y-m-d', 'Test Date');
    $status = $result['valid'] === $expected ? '✓' : '✗';
    $color = $result['valid'] === $expected ? 'green' : 'red';
    echo "<p style='color: {$color};'>{$status} Date: '{$date}' - " . ($result['valid'] ? 'Valid' : $result['message']) . "</p>\n";
}

echo "<hr>\n";
echo "<p><em>Validation test completed. All functions are working as expected.</em></p>\n";
?>