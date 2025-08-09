<?php
/**
 * Debug script for TPAK DQ System API
 * This script helps debug the LimeSurvey API connection and survey data issues
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in and has admin privileges
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>TPAK DQ System - API Debug</h1>\n";

// Load the API handler
require_once(TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-api-handler.php');
$api_handler = new TPAK_DQ_API_Handler();

echo "<h2>1. Configuration Check</h2>\n";
$options = get_option('tpak_dq_system_options', array());
echo "<p><strong>Current Settings:</strong></p>\n";
echo "<ul>\n";
echo "<li>API URL: " . (isset($options['limesurvey_url']) ? $options['limesurvey_url'] : 'Not set') . "</li>\n";
echo "<li>Username: " . (isset($options['limesurvey_username']) ? $options['limesurvey_username'] : 'Not set') . "</li>\n";
echo "<li>Password: " . (isset($options['limesurvey_password']) ? 'Set' : 'Not set') . "</li>\n";
echo "<li>Survey ID: " . (isset($options['survey_id']) ? $options['survey_id'] : 'Not set') . "</li>\n";
echo "</ul>\n";

echo "<h2>2. API Configuration Test</h2>\n";
if ($api_handler->is_configured()) {
    echo "<p style='color: green;'>✓ API is configured</p>\n";
} else {
    echo "<p style='color: red;'>✗ API is not configured</p>\n";
    die();
}

echo "<h2>3. API Connection Test</h2>\n";
if ($api_handler->test_connection()) {
    echo "<p style='color: green;'>✓ API connection successful</p>\n";
} else {
    echo "<p style='color: red;'>✗ API connection failed</p>\n";
    die();
}

echo "<h2>4. Available Surveys</h2>\n";
$surveys = $api_handler->get_surveys();
if ($surveys && is_array($surveys)) {
    echo "<p>Found " . count($surveys) . " surveys:</p>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Survey ID</th><th>Title</th><th>Status</th><th>Language</th></tr>\n";
    foreach ($surveys as $survey) {
        $status = isset($survey['active']) && $survey['active'] ? 'Active' : 'Inactive';
        $language = isset($survey['language']) ? $survey['language'] : 'Unknown';
        echo "<tr>";
        echo "<td>" . esc_html($survey['sid']) . "</td>";
        echo "<td>" . esc_html($survey['surveyls_title']) . "</td>";
        echo "<td>" . esc_html($status) . "</td>";
        echo "<td>" . esc_html($language) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p style='color: red;'>✗ Failed to get surveys list</p>\n";
}

echo "<h2>5. Test Specific Survey ID</h2>\n";
$survey_id = isset($options['survey_id']) ? $options['survey_id'] : '';
if (!empty($survey_id)) {
    echo "<p>Testing survey ID: <strong>" . esc_html($survey_id) . "</strong></p>\n";
    
    // Test survey validation first
    echo "<h3>5.1 Survey Validation Test</h3>\n";
    $validation = $api_handler->validate_survey_id($survey_id);
    if ($validation['valid']) {
        echo "<p style='color: green;'>✓ " . esc_html($validation['message']) . "</p>\n";
        if (isset($validation['data'])) {
            echo "<p>Survey Title: " . esc_html($validation['data']['title'] ?? 'Unknown') . "</p>\n";
            echo "<p>Survey Active: " . (isset($validation['data']['active']) && $validation['data']['active'] ? 'Yes' : 'No') . "</p>\n";
        }
    } else {
        echo "<p style='color: red;'>✗ " . esc_html($validation['message']) . "</p>\n";
    }
    
    // Test survey structure
    echo "<h3>5.2 Survey Structure Test</h3>\n";
    $structure = $api_handler->get_survey_structure($survey_id);
    if ($structure && is_array($structure)) {
        echo "<p style='color: green;'>✓ Survey structure retrieved successfully</p>\n";
        echo "<p>Found " . count($structure) . " questions</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to get survey structure</p>\n";
    }
    
    // Test survey responses without date filter
    echo "<h3>5.3 Survey Responses Test (No Date Filter)</h3>\n";
    $responses = $api_handler->get_survey_responses($survey_id);
    if ($responses && is_array($responses)) {
        echo "<p style='color: green;'>✓ Survey responses retrieved successfully</p>\n";
        echo "<p>Found " . count($responses) . " responses</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to get survey responses</p>\n";
    }
    
    // Test survey responses with date filter
    echo "<h3>5.4 Survey Responses Test (With Date Filter: 2025-07-09 to 2025-08-09)</h3>\n";
    $responses_with_date = $api_handler->get_survey_responses($survey_id, '2025-07-09', '2025-08-09');
    if ($responses_with_date && is_array($responses_with_date)) {
        echo "<p style='color: green;'>✓ Survey responses with date filter retrieved successfully</p>\n";
        echo "<p>Found " . count($responses_with_date) . " responses in date range</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to get survey responses with date filter</p>\n";
    }
    
    // Test pagination
    echo "<h3>5.5 Pagination Test</h3>\n";
    $pagination_result = $api_handler->test_pagination($survey_id, 10);
    if ($pagination_result) {
        echo "<p style='color: green;'>✓ Pagination test successful</p>\n";
        echo "<p>Pagination result: " . esc_html(print_r($pagination_result, true)) . "</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Pagination test failed</p>\n";
    }
    
} else {
    echo "<p style='color: orange;'>⚠ No survey ID configured in settings</p>\n";
}

echo "<h2>6. Manual Survey ID Test</h2>\n";
echo "<form method='post'>\n";
echo "<p>Enter a survey ID to test: <input type='text' name='test_survey_id' value='' /> <input type='submit' value='Test' /></p>\n";
echo "</form>\n";

if (isset($_POST['test_survey_id']) && !empty($_POST['test_survey_id'])) {
    $test_survey_id = sanitize_text_field($_POST['test_survey_id']);
    echo "<h3>Testing Manual Survey ID: " . esc_html($test_survey_id) . "</h3>\n";
    
    // Test survey structure
    $test_structure = $api_handler->get_survey_structure($test_survey_id);
    if ($test_structure && is_array($test_structure)) {
        echo "<p style='color: green;'>✓ Survey structure retrieved successfully</p>\n";
        echo "<p>Found " . count($test_structure) . " questions</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to get survey structure</p>\n";
    }
    
    // Test survey responses
    $test_responses = $api_handler->get_survey_responses($test_survey_id);
    if ($test_responses && is_array($test_responses)) {
        echo "<p style='color: green;'>✓ Survey responses retrieved successfully</p>\n";
        echo "<p>Found " . count($test_responses) . " responses</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to get survey responses</p>\n";
    }
}

echo "<h2>7. Error Log Check</h2>\n";
echo "<p>Check the WordPress error log for detailed API request/response information.</p>\n";
echo "<p>Recent error log entries related to TPAK DQ System:</p>\n";

// Try to read the error log (this might not work on all systems)
$error_log_path = ini_get('error_log');
if ($error_log_path && file_exists($error_log_path)) {
    $log_lines = file($error_log_path);
    $tpak_lines = array();
    foreach (array_reverse($log_lines) as $line) {
        if (strpos($line, 'TPAK DQ System') !== false) {
            $tpak_lines[] = $line;
            if (count($tpak_lines) >= 20) break; // Show last 20 TPAK-related lines
        }
    }
    
    if (!empty($tpak_lines)) {
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 400px; overflow-y: auto;'>\n";
        foreach (array_reverse($tpak_lines) as $line) {
            echo esc_html($line);
        }
        echo "</pre>\n";
    } else {
        echo "<p>No recent TPAK DQ System entries found in error log.</p>\n";
    }
} else {
    echo "<p>Could not access error log file.</p>\n";
}

echo "<hr>\n";
echo "<p><em>Debug script completed. Check the results above to identify the issue.</em></p>\n";
?>
