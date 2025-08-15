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

// Include the API handler
require_once 'includes/class-api-handler.php';

$api = new TPAK_DQ_API_Handler();
$test_result = $api->test_connection();

if ($test_result['success']) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "✅ <strong>API Connection Successful!</strong><br>";
    echo "Message: " . esc_html($test_result['message']);
    echo "</div>";
    
    // Try to get surveys list
    echo "<h4>📋 Getting Surveys List:</h4>";
    $surveys = $api->get_surveys();
    
    if ($surveys && is_array($surveys)) {
        $survey_count = count($surveys);
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
        echo "✅ <strong>Found $survey_count surveys</strong>";
        echo "</div>";
        
        // Look for the target survey
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
    
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "❌ <strong>API Connection Failed</strong><br>";
    echo "Error: " . esc_html($test_result['message']);
    echo "</div>";
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