<?php
/**
 * Simple test script to validate survey ID
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>TPAK DQ System - Survey ID Test</h1>\n";

// Load the API handler
require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-api-handler.php');
$api_handler = new TPAK_DQ_API_Handler();

// Get current settings
$options = get_option('tpak_dq_system_options', array());
$survey_id = isset($options['survey_id']) ? $options['survey_id'] : '';

echo "<h2>Current Settings</h2>\n";
echo "<p><strong>Survey ID:</strong> " . ($survey_id ? $survey_id : 'Not set') . "</p>\n";
echo "<p><strong>API URL:</strong> " . (isset($options['limesurvey_url']) ? $options['limesurvey_url'] : 'Not set') . "</p>\n";
echo "<p><strong>Username:</strong> " . (isset($options['limesurvey_username']) ? $options['limesurvey_username'] : 'Not set') . "</p>\n";

if (empty($survey_id)) {
    echo "<p style='color: red;'>No survey ID configured. Please set it in the Settings page.</p>\n";
    exit;
}

echo "<h2>Testing Survey ID: " . esc_html($survey_id) . "</h2>\n";

// Test 1: Validate survey ID
echo "<h3>1. Survey Validation</h3>\n";
$validation = $api_handler->validate_survey_id($survey_id);
if ($validation['valid']) {
    echo "<p style='color: green;'>✓ " . esc_html($validation['message']) . "</p>\n";
    if (isset($validation['data'])) {
        echo "<p><strong>Survey Title:</strong> " . esc_html($validation['data']['title'] ?? 'Unknown') . "</p>\n";
        echo "<p><strong>Survey Active:</strong> " . (isset($validation['data']['active']) && $validation['data']['active'] ? 'Yes' : 'No') . "</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ " . esc_html($validation['message']) . "</p>\n";
    exit;
}

// Test 2: Get survey structure
echo "<h3>2. Survey Structure</h3>\n";
$structure = $api_handler->get_survey_structure($survey_id);
if ($structure && is_array($structure)) {
    echo "<p style='color: green;'>✓ Survey structure retrieved successfully</p>\n";
    echo "<p><strong>Number of questions:</strong> " . count($structure) . "</p>\n";
} else {
    echo "<p style='color: red;'>✗ Failed to get survey structure</p>\n";
    exit;
}

// Test 3: Get survey responses (no date filter)
echo "<h3>3. Survey Responses (No Date Filter)</h3>\n";
$responses = $api_handler->get_survey_responses($survey_id);
if ($responses && is_array($responses)) {
    echo "<p style='color: green;'>✓ Survey responses retrieved successfully</p>\n";
    echo "<p><strong>Total responses:</strong> " . count($responses) . "</p>\n";
    
    if (count($responses) > 0) {
        echo "<p><strong>Sample response dates:</strong></p>\n";
        echo "<ul>\n";
        $sample_count = 0;
        foreach ($responses as $response) {
            if ($sample_count >= 5) break;
            if (isset($response['submitdate'])) {
                echo "<li>" . esc_html($response['submitdate']) . "</li>\n";
            }
            $sample_count++;
        }
        echo "</ul>\n";
    }
} else {
    echo "<p style='color: red;'>✗ Failed to get survey responses</p>\n";
}

// Test 3.5: Get response date range
echo "<h3>3.5. Response Date Range</h3>\n";
$date_range = $api_handler->get_response_date_range($survey_id);
if ($date_range['success']) {
    echo "<p style='color: green;'>✓ Date range retrieved successfully</p>\n";
    echo "<p><strong>Total responses:</strong> " . $date_range['total_responses'] . "</p>\n";
    echo "<p><strong>Date range:</strong> " . esc_html($date_range['date_range']) . "</p>\n";
    echo "<p><strong>Earliest date:</strong> " . esc_html($date_range['earliest_date']) . "</p>\n";
    echo "<p><strong>Latest date:</strong> " . esc_html($date_range['latest_date']) . "</p>\n";
} else {
    echo "<p style='color: red;'>✗ " . esc_html($date_range['message']) . "</p>\n";
}

// Test 4: Get survey responses with date filter
echo "<h3>4. Survey Responses (With Date Filter: 2025-07-09 to 2025-08-09)</h3>\n";
$responses_with_date = $api_handler->get_survey_responses($survey_id, '2025-07-09', '2025-08-09');
if ($responses_with_date && is_array($responses_with_date)) {
    echo "<p style='color: green;'>✓ Survey responses with date filter retrieved successfully</p>\n";
    echo "<p><strong>Responses in date range:</strong> " . count($responses_with_date) . "</p>\n";
} else {
    echo "<p style='color: red;'>✗ Failed to get survey responses with date filter</p>\n";
    echo "<p><em>This might be normal if there are no responses in the specified date range.</em></p>\n";
}

echo "<hr>\n";
echo "<p><em>Test completed. Check the results above to understand the issue.</em></p>\n";
?>
