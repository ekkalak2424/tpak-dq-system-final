<?php
/**
 * TPAK DQ System - Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('TPAK DQ System Settings', 'tpak-dq-system'); ?></h1>
    
    <?php settings_errors(); ?>
    
    <div class="tpak-settings-page">
        <form method="post" action="">
            <?php wp_nonce_field('tpak_dq_settings', '_wpnonce'); ?>
            
            <!-- API Settings -->
            <div class="tpak-settings-section">
                <h3><?php _e('การตั้งค่า LimeSurvey API', 'tpak-dq-system'); ?></h3>
                
                <div class="tpak-form-row">
                    <label for="limesurvey_url"><?php _e('LimeSurvey URL', 'tpak-dq-system'); ?></label>
                    <input type="url" id="limesurvey_url" name="limesurvey_url" 
                           value="<?php echo esc_attr($options['limesurvey_url'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('กรอก URL ของ LimeSurvey installation (เช่น: https://survey.example.com)', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <label for="limesurvey_username"><?php _e('Username', 'tpak-dq-system'); ?></label>
                    <input type="text" id="limesurvey_username" name="limesurvey_username" 
                           value="<?php echo esc_attr($options['limesurvey_username'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('ชื่อผู้ใช้สำหรับ LimeSurvey RemoteControl 2 API', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <label for="limesurvey_password"><?php _e('Password', 'tpak-dq-system'); ?></label>
                    <input type="password" id="limesurvey_password" name="limesurvey_password" 
                           value="<?php echo esc_attr($options['limesurvey_password'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('รหัสผ่านสำหรับ LimeSurvey RemoteControl 2 API', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <button type="button" id="tpak-test-api" class="button button-secondary">
                        <?php _e('ทดสอบการเชื่อมต่อ', 'tpak-dq-system'); ?>
                    </button>
                    <span id="tpak-api-test-result"></span>
                </div>
            </div>
            
            <!-- Cron Settings -->
            <div class="tpak-settings-section">
                <h3><?php _e('การตั้งค่า Cron Job', 'tpak-dq-system'); ?></h3>
                
                <div class="tpak-form-row">
                    <label for="cron_interval"><?php _e('ความถี่ในการนำเข้า', 'tpak-dq-system'); ?></label>
                    <select id="cron_interval" name="cron_interval">
                        <option value="hourly" <?php selected($options['cron_interval'] ?? 'hourly', 'hourly'); ?>>
                            <?php _e('ทุกชั่วโมง', 'tpak-dq-system'); ?>
                        </option>
                        <option value="twicedaily" <?php selected($options['cron_interval'] ?? 'hourly', 'twicedaily'); ?>>
                            <?php _e('วันละ 2 ครั้ง', 'tpak-dq-system'); ?>
                        </option>
                        <option value="daily" <?php selected($options['cron_interval'] ?? 'hourly', 'daily'); ?>>
                            <?php _e('วันละครั้ง', 'tpak-dq-system'); ?>
                        </option>
                        <option value="weekly" <?php selected($options['cron_interval'] ?? 'hourly', 'weekly'); ?>>
                            <?php _e('สัปดาห์ละครั้ง', 'tpak-dq-system'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('ความถี่ในการดึงข้อมูลจาก LimeSurvey', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <label for="survey_id"><?php _e('Survey ID', 'tpak-dq-system'); ?></label>
                    <input type="text" id="survey_id" name="survey_id" 
                           value="<?php echo esc_attr($options['survey_id'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('ID ของแบบสอบถามที่ต้องการนำเข้า', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <h4><?php _e('ข้อมูล Cron Job', 'tpak-dq-system'); ?></h4>
                    <?php
                    $cron_handler = new TPAK_DQ_Cron();
                    $next_run = $cron_handler->get_next_scheduled_run();
                    $last_run = $cron_handler->get_last_run_time();
                    ?>
                    <p><strong><?php _e('รันครั้งถัดไป:', 'tpak-dq-system'); ?></strong> <?php echo $next_run; ?></p>
                    <p><strong><?php _e('รันครั้งล่าสุด:', 'tpak-dq-system'); ?></strong> <?php echo $last_run; ?></p>
                </div>
            </div>
            
            <!-- Notification Settings -->
            <div class="tpak-settings-section">
                <h3><?php _e('การตั้งค่าการแจ้งเตือน', 'tpak-dq-system'); ?></h3>
                
                <div class="tpak-form-row">
                    <label>
                        <input type="checkbox" name="email_notifications" value="1" 
                               <?php checked($options['email_notifications'] ?? true, true); ?> />
                        <?php _e('เปิดใช้งานการแจ้งเตือนอีเมล', 'tpak-dq-system'); ?>
                    </label>
                    <p class="description">
                        <?php _e('ส่งอีเมลแจ้งเตือนเมื่อมีการเปลี่ยนแปลงสถานะ', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <label for="sampling_percentage"><?php _e('เปอร์เซ็นต์การสุ่มตรวจสอบ', 'tpak-dq-system'); ?></label>
                    <input type="number" id="sampling_percentage" name="sampling_percentage" 
                           value="<?php echo esc_attr($options['sampling_percentage'] ?? 70); ?>" 
                           min="1" max="100" class="small-text" />
                    <p class="description">
                        <?php _e('เปอร์เซ็นต์ของชุดข้อมูลที่จะเสร็จสมบูรณ์โดยการสุ่ม (1-100)', 'tpak-dq-system'); ?>
                    </p>
                </div>
            </div>
            
            <!-- Test Email -->
            <div class="tpak-settings-section">
                <h3><?php _e('ทดสอบการส่งอีเมล', 'tpak-dq-system'); ?></h3>
                
                <div class="tpak-form-row">
                    <label for="test_email_user"><?php _e('ผู้ใช้ที่ต้องการทดสอบ', 'tpak-dq-system'); ?></label>
                    <select id="test_email_user" name="test_email_user">
                        <option value=""><?php _e('เลือกผู้ใช้', 'tpak-dq-system'); ?></option>
                        <?php
                        $users = get_users(array('role__in' => array('administrator', 'interviewer', 'supervisor', 'examiner')));
                        foreach ($users as $user) {
                            echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="tpak-form-row">
                    <button type="button" id="tpak-test-email" class="button button-secondary">
                        <?php _e('ส่งอีเมลทดสอบ', 'tpak-dq-system'); ?>
                    </button>
                    <span id="tpak-email-test-result"></span>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="tpak-form-row">
                <input type="submit" name="submit" class="button button-primary" 
                       value="<?php _e('บันทึกการตั้งค่า', 'tpak-dq-system'); ?>" />
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test API connection
    $('#tpak-test-api').on('click', function() {
        var button = $(this);
        var resultSpan = $('#tpak-api-test-result');
        
        button.prop('disabled', true).text('กำลังทดสอบ...');
        resultSpan.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tpak_test_api',
                nonce: '<?php echo wp_create_nonce('tpak_workflow_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">✗ การทดสอบล้มเหลว กรุณาลองใหม่อีกครั้ง</span>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('ทดสอบการเชื่อมต่อ', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Test email
    $('#tpak-test-email').on('click', function() {
        var button = $(this);
        var resultSpan = $('#tpak-email-test-result');
        var userId = $('#test_email_user').val();
        
        if (!userId) {
            alert('<?php _e('กรุณาเลือกผู้ใช้', 'tpak-dq-system'); ?>');
            return;
        }
        
        button.prop('disabled', true).text('กำลังส่ง...');
        resultSpan.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tpak_test_email',
                nonce: '<?php echo wp_create_nonce('tpak_test_email'); ?>',
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">✗ การส่งอีเมลล้มเหลว กรุณาลองใหม่อีกครั้ง</span>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('ส่งอีเมลทดสอบ', 'tpak-dq-system'); ?>');
            }
        });
    });
});
</script> 