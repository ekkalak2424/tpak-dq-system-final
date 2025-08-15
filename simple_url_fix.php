<?php
/**
 * Simple URL Fix - No Dependencies
 */

// Include WordPress
require_once '../../../wp-config.php';

echo "🔧 TPAK DQ System - Simple URL Fix\n\n";

// Get current options
$options = get_option('tpak_dq_system_options', array());

echo "Current Settings:\n";
foreach ($options as $key => $value) {
    if ($key === 'limesurvey_password') {
        echo "  $key: " . str_repeat('*', strlen($value)) . "\n";
    } else {
        echo "  $key: $value\n";
    }
}

// The correct URL
$correct_url = 'https://limesurvey.tpak.or.th/index.php?r=admin/remotecontrol';
$current_url = isset($options['limesurvey_url']) ? $options['limesurvey_url'] : '';

echo "\nURL Comparison:\n";
echo "Current: $current_url\n";
echo "Correct: $correct_url\n";

if ($current_url === $correct_url) {
    echo "\n✅ URL is already correct!\n";
} else {
    echo "\n🔄 Updating URL...\n";
    
    $options['limesurvey_url'] = $correct_url;
    $updated = update_option('tpak_dq_system_options', $options);
    
    if ($updated) {
        echo "✅ URL updated successfully!\n";
    } else {
        echo "⚠️ URL was already correct or update failed\n";
    }
    
    // Verify the update
    $new_options = get_option('tpak_dq_system_options', array());
    echo "Verified URL: " . $new_options['limesurvey_url'] . "\n";
}

echo "\n📝 Next Steps:\n";
echo "1. Go to WordPress Admin → TPAK DQ System → Import Data\n";
echo "2. Try importing data again\n";
echo "3. Make sure survey 734631 is active in LimeSurvey\n";
echo "4. Check that there are responses in the selected date range\n";

echo "\n✨ Fix completed!\n";
?>