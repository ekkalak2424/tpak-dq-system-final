<?php
/**
 * Test Different LimeSurvey API Endpoints
 */

// Include WordPress
require_once '../../../wp-config.php';

echo "<h2>🧪 TPAK DQ System - LimeSurvey Endpoint Test</h2>";

$base_url = 'https://limesurvey.tpak.or.th';
$username = 'admin';
$password = 'tpakOwen21';

// Different possible endpoints to test
$endpoints = [
    'index.php?r=admin/remotecontrol',
    'index.php/admin/remotecontrol',
    'admin/remotecontrol',
    'index.php/RemoteControl',
    'index.php?r=RemoteControl',
    'api/v1',
    'jsonrpc.php'
];

echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>🔧 Test Configuration:</h3>";
echo "<p><strong>Base URL:</strong> " . esc_html($base_url) . "</p>";
echo "<p><strong>Username:</strong> " . esc_html($username) . "</p>";
echo "<p><strong>Password:</strong> " . str_repeat('*', strlen($password)) . "</p>";
echo "</div>";

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

echo "<h3>📝 Testing Endpoints:</h3>";

foreach ($endpoints as $endpoint) {
    $full_url = $base_url . '/' . $endpoint;
    
    echo "<div style='border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px;'>";
    echo "<h4>🔗 Testing: " . esc_html($full_url) . "</h4>";
    
    $response = wp_remote_post($full_url, $args);
    
    if (is_wp_error($response)) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 3px; color: #721c24;'>";
        echo "❌ <strong>Request Failed:</strong> " . $response->get_error_message();
        echo "</div>";
        continue;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    
    echo "<p><strong>Response Code:</strong> " . $response_code . "</p>";
    echo "<p><strong>Content Type:</strong> " . esc_html($content_type) . "</p>";
    
    // Check if response is JSON
    $is_json = (strpos($content_type, 'application/json') !== false);
    $json_data = json_decode($response_body, true);
    
    if ($is_json && $json_data) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 3px; color: #155724;'>";
        echo "✅ <strong>Valid JSON Response</strong>";
        echo "</div>";
        
        echo "<p><strong>Response Data:</strong></p>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px; font-size: 12px;'>";
        echo esc_html(json_encode($json_data, JSON_PRETTY_PRINT));
        echo "</pre>";
        
        // Check if we got a session key
        if (isset($json_data['result']) && is_string($json_data['result']) && strlen($json_data['result']) > 10) {
            echo "<div style='background: #d1ecf1; padding: 10px; border-radius: 3px; color: #0c5460; margin: 10px 0;'>";
            echo "🎉 <strong>SUCCESS! Got Session Key:</strong> " . esc_html($json_data['result']);
            echo "<br><strong>This endpoint works!</strong>";
            echo "</div>";
        }
        
    } else {
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 3px; color: #856404;'>";
        echo "⚠️ <strong>Non-JSON Response (HTML/Text)</strong>";
        echo "</div>";
        
        // Show first 500 characters of response
        echo "<p><strong>Response Preview:</strong></p>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px; font-size: 11px; max-height: 200px; overflow-y: auto;'>";
        echo esc_html(substr($response_body, 0, 500));
        if (strlen($response_body) > 500) {
            echo "\n... (truncated)";
        }
        echo "</pre>";
    }
    
    echo "</div>";
}

echo "<h3>💡 Recommendations:</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
echo "<ol>";
echo "<li><strong>Check LimeSurvey Admin Panel:</strong> Go to Configuration → Global Settings → Interfaces → Enable JSON-RPC</li>";
echo "<li><strong>Check LimeSurvey Version:</strong> Different versions use different endpoints</li>";
echo "<li><strong>Check Server Configuration:</strong> Make sure mod_rewrite is enabled if using pretty URLs</li>";
echo "<li><strong>Check User Permissions:</strong> Make sure the admin user has API access rights</li>";
echo "<li><strong>Try Direct Access:</strong> Visit the API URL directly in browser to see what happens</li>";
echo "</ol>";
echo "</div>";

echo "<h3>🔧 Next Steps:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;'>";
echo "<p>If none of the endpoints work, you need to:</p>";
echo "<ol>";
echo "<li>Log into your LimeSurvey admin panel</li>";
echo "<li>Go to <strong>Configuration → Global Settings → Interfaces</strong></li>";
echo "<li>Enable <strong>JSON-RPC</strong> interface</li>";
echo "<li>Save settings and try again</li>";
echo "</ol>";
echo "</div>";
?>