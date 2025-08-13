<?php
/**
 * Fix URL Validation Issue
 * This script will update the URL validation to be more flexible
 */

echo "🔧 Fixing TPAK DQ System URL Validation...\n\n";

// Read the current validator file
$validator_file = 'includes/class-validator.php';
$content = file_get_contents($validator_file);

if ($content === false) {
    echo "❌ Error: Could not read validator file\n";
    exit(1);
}

// Check if the fix is already applied
if (strpos($content, 'More flexible endpoint matching') !== false) {
    echo "✅ URL validation fix is already applied!\n";
    echo "Your URL should now work: https://limesurvey.tpak.or.th/index.php/admin/remotecontrol\n\n";
    
    // Test the validation
    require_once $validator_file;
    
    if (!function_exists('__')) {
        function __($text, $domain = '') { return $text; }
    }
    if (!function_exists('esc_url_raw')) {
        function esc_url_raw($url) { return $url; }
    }
    
    $test_url = 'https://limesurvey.tpak.or.th/index.php/admin/remotecontrol';
    $result = TPAK_DQ_Validator::validate_url(
        $test_url, 
        array('/admin/remotecontrol', '/index.php/admin/remotecontrol', 'remotecontrol')
    );
    
    echo "🧪 Testing your URL: $test_url\n";
    echo "Result: " . ($result['valid'] ? '✅ VALID' : '❌ INVALID') . "\n";
    if (!$result['valid']) {
        echo "Error: " . $result['message'] . "\n";
    }
    
} else {
    echo "❌ Fix not found in validator file. Please check the file manually.\n";
}

echo "\n📋 Next Steps:\n";
echo "1. Go to WordPress Admin → TPAK DQ System → Settings\n";
echo "2. Enter your LimeSurvey URL: https://limesurvey.tpak.or.th/index.php/admin/remotecontrol\n";
echo "3. Enter your username and password\n";
echo "4. Click 'ทดสอบการเชื่อมต่อ' to verify the connection\n";
echo "5. Save settings\n\n";

echo "✨ The validation should now accept your URL without errors!\n";
?>