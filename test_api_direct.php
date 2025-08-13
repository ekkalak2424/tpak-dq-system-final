<?php
/**
 * Direct API Test
 * Test LimeSurvey API connection directly
 */

// Include WordPress
require_once '../../../wp-config.php';

echo "<h2>🧪 TPAK DQ System - Direct API Test</h2>";

// API settings
$api_url = 'https://limesurvey.tpak.or.th/index.php/admin/remotecontrol';
$username = 'admin';
$password = 'tpakOwen21'; // From your error log
$survey_id = '734631';

echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>🔧 Test Configuration:</h3>";
echo "<p><strong>API URL:</strong> " . esc_html($api_url) . "</p>";
echo "<p><strong>Username:</strong> " . esc_html($username) . "</p>";
echo "<p><strong>Password:</strong> " . str_repeat('*', strlen($password)) . "</p>";
echo "<p><strong>Survey ID:</strong> " . esc_html($survey_id) . "</p>";
echo "</div>";

// Test 1: Get session key
echo "<h3>📝 Test 1: Getting Session Key</h3>";

$request_data = array(
    'method' => 'get_session_key',
    'params' => array(
        'username' => $username,
        'password' => $password
    ),
    'id' => 1
);

$args = array(
    'body' => json_encode($request_data),
    'headers' => array(
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ),
    'timeout' => 30
);

echo "<p><strong>Request URL:</strong> " . esc_html($api_url) . "</p>";
echo "<p><strong>Request Body:</strong></p>";
echo "<pre>" . esc_html(json_encode($request_data, JSON_PRETTY_PRINT)) . "</pre>";

$response = wp_remote_post($api_url, $args);

if (is_wp_error($response)) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>Request Failed:</strong> " . $response->get_error_message();
    echo "</div>";
    exit;
}

$response_code = wp_remote_retrieve_response_code($response);
$response_body = wp_remote_retrieve_body($response);

echo "<p><strong>Response Code:</strong> " . $response_code . "</p>";
echo "<p><strong>Response Body:</strong></p>";
echo "<pre>" . esc_html($response_body) . "</pre>";

$data = json_decode($response_body, true);

if (!$data || !isset($data['result'])) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>Invalid API Response</strong>";
    echo "</div>";
    exit;
}

$session_key = $data['result'];

if (is_array($session_key) && isset($session_key['status'])) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>Authentication Failed:</strong> " . esc_html($session_key['status']);
    echo "</div>";
    exit;
}

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
echo "✅ <strong>Session Key Obtained:</strong> " . esc_html($session_key);
echo "</div>";

// Test 2: List surveys
echo "<h3>📋 Test 2: Listing Surveys</h3>";

$request_data = array(
    'method' => 'list_surveys',
    'params' => array(
        'sSessionKey' => $session_key,
        'sUsername' => $username
    ),
    'id' => 1
);

$args['body'] = json_encode($request_data);

echo "<p><strong>Request Body:</strong></p>";
echo "<pre>" . esc_html(json_encode($request_data, JSON_PRETTY_PRINT)) . "</pre>";

$response = wp_remote_post($api_url, $args);
$response_body = wp_remote_retrieve_body($response);
$data = json_decode($response_body, true);

echo "<p><strong>Response:</strong></p>";
echo "<pre>" . esc_html(substr($response_body, 0, 1000)) . (strlen($response_body) > 1000 ? '...' : '') . "</pre>";

if ($data && isset($data['result']) && is_array($data['result'])) {
    $survey_count = count($data['result']);
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "✅ <strong>Found $survey_count surveys</strong>";
    echo "</div>";
    
    // Check if our target survey exists
    $target_survey = null;
    foreach ($data['result'] as $survey) {
        if ($survey['sid'] == $survey_id) {
            $target_survey = $survey;
            break;
        }
    }
    
    if ($target_survey) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
        echo "✅ <strong>Target Survey Found:</strong><br>";
        echo "ID: " . esc_html($target_survey['sid']) . "<br>";
        echo "Title: " . esc_html($target_survey['surveyls_title']) . "<br>";
        echo "Active: " . ($target_survey['active'] == 'Y' ? 'Yes' : 'No');
        echo "</div>";
        
        if ($target_survey['active'] != 'Y') {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;'>";
            echo "⚠️ <strong>Warning:</strong> Survey is not active. You need to activate it in LimeSurvey to get responses.";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
        echo "❌ <strong>Target Survey Not Found:</strong> Survey ID " . esc_html($survey_id) . " does not exist or you don't have access to it.";
        echo "</div>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>Failed to get surveys list</strong>";
    echo "</div>";
}

// Test 3: Get survey responses (if survey is active)
if (isset($target_survey) && $target_survey['active'] == 'Y') {
    echo "<h3>📊 Test 3: Getting Survey Responses</h3>";
    
    $request_data = array(
        'method' => 'export_responses',
        'params' => array(
            'sSessionKey' => $session_key,
            'iSurveyID' => intval($survey_id),
            'sDocumentType' => 'json',
            'sLanguageCode' => null,
            'sCompletionStatus' => 'complete',
            'sHeadingType' => 'code',
            'sResponseType' => 'short'
        ),
        'id' => 1
    );
    
    $args['body'] = json_encode($request_data);
    
    echo "<p><strong>Request Body:</strong></p>";
    echo "<pre>" . esc_html(json_encode($request_data, JSON_PRETTY_PRINT)) . "</pre>";
    
    $response = wp_remote_post($api_url, $args);
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);
    
    if ($data && isset($data['result'])) {
        if (is_string($data['result'])) {
            // Result is base64 encoded data
            $decoded_data = base64_decode($data['result']);
            $responses = json_decode($decoded_data, true);
            
            if ($responses && isset($responses['responses'])) {
                $response_count = count($responses['responses']);
                echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
                echo "✅ <strong>Found $response_count responses</strong>";
                echo "</div>";
                
                if ($response_count > 0) {
                    echo "<p><strong>Sample Response Keys:</strong></p>";
                    $sample_keys = array_keys($responses['responses'][0]);
                    echo "<pre>" . esc_html(implode(', ', array_slice($sample_keys, 0, 10))) . (count($sample_keys) > 10 ? '...' : '') . "</pre>";
                }
            } else {
                echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;'>";
                echo "⚠️ <strong>No responses found or invalid response format</strong>";
                echo "</div>";
            }
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
            echo "❌ <strong>Unexpected response format:</strong> " . esc_html(print_r($data['result'], true));
            echo "</div>";
        }
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
        echo "❌ <strong>Failed to get responses</strong>";
        if (isset($data['error'])) {
            echo "<br>Error: " . esc_html($data['error']);
        }
        echo "</div>";
    }
}

// Release session
echo "<h3>🔚 Releasing Session</h3>";
$request_data = array(
    'method' => 'release_session_key',
    'params' => array('sSessionKey' => $session_key),
    'id' => 1
);

$args['body'] = json_encode($request_data);
$response = wp_remote_post($api_url, $args);

echo "<p>✅ Session released</p>";

echo "<h3>📋 Summary</h3>";
echo "<p>If all tests passed, the API connection is working correctly. The import issue might be related to:</p>";
echo "<ul>";
echo "<li>Date range filters</li>";
echo "<li>Response completion status</li>";
echo "<li>Survey activation status</li>";
echo "<li>Data processing logic in the import function</li>";
echo "</ul>";
?>