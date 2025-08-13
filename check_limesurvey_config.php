<?php
/**
 * Check LimeSurvey Configuration
 */

// Include WordPress
require_once '../../../wp-config.php';

echo "<h2>🔍 TPAK DQ System - LimeSurvey Configuration Check</h2>";

$base_url = 'https://limesurvey.tpak.or.th';

echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>🌐 Basic Connectivity Test</h3>";
echo "<p>Testing basic connection to LimeSurvey server...</p>";
echo "</div>";

// Test 1: Basic connectivity
echo "<h4>📡 Test 1: Server Connectivity</h4>";
$response = wp_remote_get($base_url, array('timeout' => 10));

if (is_wp_error($response)) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 3px; color: #721c24;'>";
    echo "❌ <strong>Cannot reach server:</strong> " . $response->get_error_message();
    echo "</div>";
    exit;
} else {
    $response_code = wp_remote_retrieve_response_code($response);
    echo "<div style='background: #d4edda; padding: 10px; border-radius: 3px; color: #155724;'>";
    echo "✅ <strong>Server is reachable</strong> (Response: $response_code)";
    echo "</div>";
}

// Test 2: Check if it's actually LimeSurvey
echo "<h4>🔍 Test 2: LimeSurvey Detection</h4>";
$body = wp_remote_retrieve_body($response);
$is_limesurvey = (strpos($body, 'LimeSurvey') !== false || strpos($body, 'limesurvey') !== false);

if ($is_limesurvey) {
    echo "<div style='background: #d4edda; padding: 10px; border-radius: 3px; color: #155724;'>";
    echo "✅ <strong>Confirmed LimeSurvey installation</strong>";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; padding: 10px; border-radius: 3px; color: #856404;'>";
    echo "⚠️ <strong>Cannot confirm LimeSurvey installation</strong>";
    echo "</div>";
}

// Test 3: Check admin panel access
echo "<h4>🔐 Test 3: Admin Panel Access</h4>";
$admin_url = $base_url . '/index.php/admin';
$admin_response = wp_remote_get($admin_url, array('timeout' => 10));

if (!is_wp_error($admin_response)) {
    $admin_code = wp_remote_retrieve_response_code($admin_response);
    $admin_body = wp_remote_retrieve_body($admin_response);
    
    if ($admin_code == 200) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 3px; color: #155724;'>";
        echo "✅ <strong>Admin panel is accessible</strong>";
        echo "</div>";
        
        // Check for login form
        if (strpos($admin_body, 'login') !== false || strpos($admin_body, 'password') !== false) {
            echo "<p>📝 Login form detected - this is normal</p>";
        }
    } else {
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 3px; color: #856404;'>";
        echo "⚠️ <strong>Admin panel returned code:</strong> $admin_code";
        echo "</div>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 3px; color: #721c24;'>";
    echo "❌ <strong>Cannot access admin panel:</strong> " . $admin_response->get_error_message();
    echo "</div>";
}

// Test 4: Try different API endpoints
echo "<h4>🔌 Test 4: API Endpoint Detection</h4>";

$api_endpoints = [
    'index.php?r=admin/remotecontrol' => 'Yii Framework Style',
    'index.php/admin/remotecontrol' => 'Standard RemoteControl',
    'admin/remotecontrol' => 'Direct RemoteControl',
    'jsonrpc.php' => 'Legacy JSON-RPC'
];

foreach ($api_endpoints as $endpoint => $description) {
    $api_url = $base_url . '/' . $endpoint;
    echo "<p><strong>Testing:</strong> $endpoint ($description)</p>";
    
    $api_response = wp_remote_get($api_url, array('timeout' => 5));
    
    if (!is_wp_error($api_response)) {
        $api_code = wp_remote_retrieve_response_code($api_response);
        $api_body = wp_remote_retrieve_body($api_response);
        $content_type = wp_remote_retrieve_header($api_response, 'content-type');
        
        echo "<div style='margin-left: 20px; padding: 8px; background: #f8f9fa; border-radius: 3px; margin-bottom: 10px;'>";
        echo "<strong>Response:</strong> $api_code | <strong>Type:</strong> $content_type<br>";
        
        if (strpos($content_type, 'json') !== false) {
            echo "<span style='color: #28a745;'>✅ JSON endpoint detected!</span>";
        } elseif ($api_code == 200) {
            echo "<span style='color: #ffc107;'>⚠️ Returns HTML (may need POST request)</span>";
        } else {
            echo "<span style='color: #dc3545;'>❌ Not accessible</span>";
        }
        echo "</div>";
    } else {
        echo "<div style='margin-left: 20px; padding: 8px; background: #f8d7da; border-radius: 3px; margin-bottom: 10px; color: #721c24;'>";
        echo "❌ Error: " . $api_response->get_error_message();
        echo "</div>";
    }
}

echo "<h3>📋 Configuration Checklist</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;'>";
echo "<p><strong>To enable LimeSurvey RemoteControl API, you need to:</strong></p>";
echo "<ol>";
echo "<li>Log into LimeSurvey admin panel at: <a href='$base_url/index.php/admin' target='_blank'>$base_url/index.php/admin</a></li>";
echo "<li>Go to <strong>Configuration → Global Settings</strong></li>";
echo "<li>Click on <strong>Interfaces</strong> tab</li>";
echo "<li>Enable <strong>JSON-RPC</strong> interface</li>";
echo "<li>Set <strong>Publish API</strong> to <strong>Available</strong></li>";
echo "<li>Save the settings</li>";
echo "<li>Make sure your user account has <strong>RemoteControl</strong> permissions</li>";
echo "</ol>";
echo "</div>";

echo "<h3>🔧 Alternative Solutions</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
echo "<p>If RemoteControl API is not available, you can:</p>";
echo "<ul>";
echo "<li><strong>Export CSV manually</strong> from LimeSurvey and import via file upload</li>";
echo "<li><strong>Use database connection</strong> directly to LimeSurvey database</li>";
echo "<li><strong>Set up a custom API endpoint</strong> on the LimeSurvey server</li>";
echo "</ul>";
echo "</div>";
?>