<?php
/**
 * Quick Fix for API URL Issue
 */

// Include WordPress
require_once '../../../wp-config.php';

echo "🔧 TPAK DQ System - Quick Fix\n\n";

// Get current options
$options = get_option('tpak_dq_system_options', array());

echo "Current URL: " . ($options['limesurvey_url'] ?? 'Not set') . "\n";

// Fix the URL
$correct_url = 'https://limesurvey.tpak.or.th/index.php/admin/remotecontrol';
$options['limesurvey_url'] = $correct_url;

// Update the options
$updated = update_option('tpak_dq_system_options', $options);

if ($updated) {
    echo "✅ URL fixed successfully!\n";
    echo "New URL: $correct_url\n";
} else {
    echo "⚠️ URL was already correct or update failed\n";
}

// Verify the fix
$new_options = get_option('tpak_dq_system_options', array());
echo "Verified URL: " . ($new_options['limesurvey_url'] ?? 'Not set') . "\n";

echo "\n📝 Next steps:\n";
echo "1. Go back to the Import Data page\n";
echo "2. Try importing again\n";
echo "3. If it still fails, run test_api_direct.php to diagnose further\n";
?>