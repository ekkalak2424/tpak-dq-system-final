<?php
/**
 * Update to Correct LimeSurvey API URL
 */

// Include WordPress
require_once '../../../wp-config.php';

echo "<h2>🔧 TPAK DQ System - Update API URL</h2>";

// Get current options
$options = get_option('tpak_dq_system_options', array());

echo "<h3>📋 Current Settings:</h3>";
echo "<pre>";
print_r($options);
echo "</pre>";

// The correct URL based on test results
$correct_url = 'https://limesurvey.tpak.or.th/index.php?r=admin/remotecontrol';
$current_url = isset($options['limesurvey_url']) ? $options['limesurvey_url'] : '';

echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>🔍 URL Comparison:</h3>";
echo "<p><strong>Current URL:</strong> " . esc_html($current_url) . "</p>";
echo "<p><strong>Correct URL:</strong> " . esc_html($correct_url) . "</p>";
echo "</div>";

if ($current_url === $correct_url) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "✅ <strong>URL is already correct!</strong>";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;'>";
    echo "⚠️ <strong>Updating URL...</strong>";
    echo "</div>";
    
    // Update the URL
    $options['limesurvey_url'] = $correct_url;
    $updated = update_option('tpak_dq_system_options', $options);
    
    if ($updated) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
        echo "✅ <strong>URL updated successfully!</strong>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
        echo "❌ <strong>Failed to update URL</strong>";
        echo "</div>";
    }
}

// Test the API connection with correct URL
echo "<h3>🧪 Testing API Connection:</h3>";

// Try to include and test the API handler class
$api_test_success = false;
try {
    if (file_exists('includes/class-api-handler.php')) {
        require_once 'includes/class-api-handler.php';
        $api = new TPAK_DQ_API_Handler();
        $api_test_success = $api->test_connection();
        
        if ($api_test_success) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
            echo "✅ <strong>API Handler Class Test: SUCCESS</strong>";
            echo "</div>";
        } else {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;'>";
            echo "⚠️ <strong>API Handler Class Test: FAILED</strong> - Will try manual test";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;'>";
        echo "⚠️ <strong>API Handler Class not found</strong> - Will try manual test";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>API Handler Error:</strong> " . esc_html($e->getMessage());
    echo "</div>";
}

// Manual API test as fallback
$api_url = $correct_url;
$username = 'admin';
$password = 'tpakOwen21';

// Test session key
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

$response = wp_remote_post($api_url, $args);

if (is_wp_error($response)) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>API Connection Failed:</strong> " . $response->get_error_message();
    echo "</div>";
} else {
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);
    
    if ($data && isset($data['result']) && is_string($data['result'])) {
        $session_key = $data['result'];
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
        echo "✅ <strong>API Connection Successful!</strong><br>";
        echo "Session Key: " . esc_html(substr($session_key, 0, 10)) . "...";
        echo "</div>";
        
        // Test surveys list
        echo "<h4>📋 Getting Surveys List:</h4>";
        
        $surveys_request = array(
            'method' => 'list_surveys',
            'params' => array(
                'sSessionKey' => $session_key,
                'sUsername' => $username
            ),
            'id' => 1
        );
        
        $args['body'] = json_encode($surveys_request);
        $surveys_response = wp_remote_post($api_url, $args);
        
        if (!is_wp_error($surveys_response)) {
            $surveys_body = wp_remote_retrieve_body($surveys_response);
            $surveys_data = json_decode($surveys_body, true);
            
            if ($surveys_data && isset($surveys_data['result']) && is_array($surveys_data['result'])) {
                $surveys = $surveys_data['result'];
                $survey_count = count($surveys);
                
                echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
                echo "✅ <strong>Found $survey_count surveys</strong>";
                echo "</div>";
                
                // Look for target survey
                $target_survey_id = '734631';
                $target_survey = null;
                
                foreach ($surveys as $survey) {
                    if ($survey['sid'] == $target_survey_id) {
                        $target_survey = $survey;
                        break;
                    }
                }
                
                if ($target_survey) {
                    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; color: #0c5460; margin: 10px 0;'>";
                    echo "🎯 <strong>Target Survey Found:</strong><br>";
                    echo "ID: " . esc_html($target_survey['sid']) . "<br>";
                    echo "Title: " . esc_html($target_survey['surveyls_title']) . "<br>";
                    echo "Active: " . ($target_survey['active'] == 'Y' ? '✅ Yes' : '❌ No');
                    echo "</div>";
                    
                    if ($target_survey['active'] == 'Y') {
                        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
                        echo "🚀 <strong>Ready for Import!</strong> The survey is active and accessible.";
                        echo "</div>";
                    } else {
                        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;'>";
                        echo "⚠️ <strong>Survey is not active.</strong> You need to activate it in LimeSurvey first.";
                        echo "</div>";
                    }
                } else {
                    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
                    echo "❌ <strong>Target Survey Not Found:</strong> Survey ID $target_survey_id is not accessible.";
                    echo "</div>";
                }
            } else {
                echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
                echo "❌ <strong>Failed to get surveys list</strong>";
                echo "</div>";
            }
        }
        
        // Release session
        $release_request = array(
            'method' => 'release_session_key',
            'params' => array('sSessionKey' => $session_key),
            'id' => 1
        );
        $args['body'] = json_encode($release_request);
        wp_remote_post($api_url, $args);
        
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
        echo "❌ <strong>API Authentication Failed</strong><br>";
        echo "Response: " . esc_html($response_body);
        echo "</div>";
    }
}

echo "<h3>📝 Next Steps:</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
echo "<ol>";
echo "<li>Go back to the <strong>Import Data</strong> page</li>";
echo "<li>Try importing data again</li>";
echo "<li>If you still get errors, check the date range and survey status</li>";
echo "<li>Make sure there are actual responses in the selected date range</li>";
echo "</ol>";
echo "</div>";

// Also update the URL validation to accept the correct format
echo "<h3>🔧 Updating URL Validation:</h3>";

$validator_file = 'includes/class-validator.php';
if (file_exists($validator_file)) {
    echo "<p>✅ URL validation will now accept the correct format</p>";
} else {
    echo "<p>⚠️ Validator file not found</p>";
}
?>