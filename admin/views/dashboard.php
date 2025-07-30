<?php
/**
 * TPAK DQ System - Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('TPAK DQ System Dashboard', 'tpak-dq-system'); ?></h1>
    
    <div class="tpak-dashboard">
        <!-- Statistics Grid -->
        <div class="tpak-stats-grid">
            <div class="tpak-stat-card pending-a">
                <h3><?php _e('รอการตรวจสอบ A', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo $pending_a_count; ?></div>
            </div>
            
            <div class="tpak-stat-card pending-b">
                <h3><?php _e('รอการตรวจสอบ B', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo $pending_b_count; ?></div>
            </div>
            
            <div class="tpak-stat-card pending-c">
                <h3><?php _e('รอการตรวจสอบ C', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo $pending_c_count; ?></div>
            </div>
            
            <div class="tpak-stat-card finalized">
                <h3><?php _e('เสร็จสมบูรณ์', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo $finalized_count; ?></div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="tpak-system-status">
            <h2><?php _e('สถานะระบบ', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-status-grid">
                <div class="tpak-status-item">
                    <h4><?php _e('การเชื่อมต่อ API', 'tpak-dq-system'); ?></h4>
                    <?php if ($api_handler->is_configured()): ?>
                        <?php if ($api_handler->test_connection()): ?>
                            <span class="tpak-status-success">✓ <?php _e('เชื่อมต่อสำเร็จ', 'tpak-dq-system'); ?></span>
                        <?php else: ?>
                            <span class="tpak-status-error">✗ <?php _e('เชื่อมต่อล้มเหลว', 'tpak-dq-system'); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="tpak-status-warning">⚠ <?php _e('ยังไม่ได้ตั้งค่า', 'tpak-dq-system'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="tpak-status-item">
                    <h4><?php _e('Cron Job', 'tpak-dq-system'); ?></h4>
                    <?php 
                    $next_run = $cron_handler->get_next_scheduled_run();
                    $last_run = $cron_handler->get_last_run_time();
                    ?>
                    <p><strong><?php _e('รันครั้งถัดไป:', 'tpak-dq-system'); ?></strong> <?php echo $next_run; ?></p>
                    <p><strong><?php _e('รันครั้งล่าสุด:', 'tpak-dq-system'); ?></strong> <?php echo $last_run; ?></p>
                </div>
                
                <div class="tpak-status-item">
                    <h4><?php _e('การแจ้งเตือน', 'tpak-dq-system'); ?></h4>
                    <?php 
                    $notifications = new TPAK_DQ_Notifications();
                    $notification_settings = $notifications->get_notification_settings();
                    ?>
                    <?php if ($notification_settings['email_notifications']): ?>
                        <span class="tpak-status-success">✓ <?php _e('เปิดใช้งาน', 'tpak-dq-system'); ?></span>
                    <?php else: ?>
                        <span class="tpak-status-warning">⚠ <?php _e('ปิดใช้งาน', 'tpak-dq-system'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="tpak-quick-actions">
            <h2><?php _e('การดำเนินการด่วน', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-action-grid">
                <a href="<?php echo admin_url('admin.php?page=tpak-dq-import'); ?>" class="tpak-action-card">
                    <h3><?php _e('นำเข้าข้อมูล', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('นำเข้าข้อมูลจาก LimeSurvey ด้วยตนเอง', 'tpak-dq-system'); ?></p>
                </a>
                
                <a href="<?php echo admin_url('edit.php?post_type=verification_batch'); ?>" class="tpak-action-card">
                    <h3><?php _e('ดูชุดข้อมูลทั้งหมด', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('ดูรายการชุดข้อมูลตรวจสอบทั้งหมด', 'tpak-dq-system'); ?></p>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=tpak-dq-settings'); ?>" class="tpak-action-card">
                    <h3><?php _e('ตั้งค่า', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('ตั้งค่า API และการทำงานของระบบ', 'tpak-dq-system'); ?></p>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=tpak-dq-users'); ?>" class="tpak-action-card">
                    <h3><?php _e('จัดการผู้ใช้', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('จัดการผู้ใช้งานและสิทธิ์', 'tpak-dq-system'); ?></p>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="tpak-recent-activity">
            <h2><?php _e('กิจกรรมล่าสุด', 'tpak-dq-system'); ?></h2>
            
            <?php
            $recent_posts = get_posts(array(
                'post_type' => 'verification_batch',
                'posts_per_page' => 10,
                'orderby' => 'modified',
                'order' => 'DESC'
            ));
            
            if (!empty($recent_posts)):
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ชุดข้อมูล', 'tpak-dq-system'); ?></th>
                        <th><?php _e('สถานะ', 'tpak-dq-system'); ?></th>
                        <th><?php _e('แก้ไขล่าสุด', 'tpak-dq-system'); ?></th>
                        <th><?php _e('การดำเนินการ', 'tpak-dq-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_posts as $post): ?>
                        <?php
                        $workflow = new TPAK_DQ_Workflow();
                        $status = $workflow->get_batch_status($post->ID);
                        $audit_trail = $workflow->get_audit_trail($post->ID);
                        $last_action = !empty($audit_trail) ? end($audit_trail) : null;
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($status): ?>
                                    <span class="tpak-status-indicator <?php echo esc_attr($status); ?>"></span>
                                    <?php 
                                    $status_term = get_term_by('slug', $status, 'verification_status');
                                    echo esc_html($status_term ? $status_term->name : $status);
                                    ?>
                                <?php else: ?>
                                    <span class="tpak-status-text"><?php _e('ไม่ระบุ', 'tpak-dq-system'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo get_the_modified_date('', $post->ID); ?>
                            </td>
                            <td>
                                <?php if ($last_action): ?>
                                    <span class="tpak-last-action">
                                        <?php echo esc_html($last_action['user_name']); ?> - 
                                        <?php echo esc_html($last_action['action']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="tpak-last-action"><?php _e('ไม่มี', 'tpak-dq-system'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p><?php _e('ยังไม่มีชุดข้อมูลตรวจสอบ', 'tpak-dq-system'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- System Information -->
        <div class="tpak-system-info">
            <h2><?php _e('ข้อมูลระบบ', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-info-grid">
                <div class="tpak-info-item">
                    <h4><?php _e('เวอร์ชันปลั๊กอิน', 'tpak-dq-system'); ?></h4>
                    <p><?php echo TPAK_DQ_SYSTEM_VERSION; ?></p>
                </div>
                
                <div class="tpak-info-item">
                    <h4><?php _e('เวอร์ชัน WordPress', 'tpak-dq-system'); ?></h4>
                    <p><?php echo get_bloginfo('version'); ?></p>
                </div>
                
                <div class="tpak-info-item">
                    <h4><?php _e('เวอร์ชัน PHP', 'tpak-dq-system'); ?></h4>
                    <p><?php echo PHP_VERSION; ?></p>
                </div>
                
                <div class="tpak-info-item">
                    <h4><?php _e('ฐานข้อมูล', 'tpak-dq-system'); ?></h4>
                    <p><?php echo $wpdb->db_version(); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tpak-status-grid,
.tpak-action-grid,
.tpak-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.tpak-status-item,
.tpak-action-card,
.tpak-info-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tpak-action-card {
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
}

.tpak-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.tpak-status-success {
    color: #28a745;
    font-weight: 600;
}

.tpak-status-error {
    color: #dc3545;
    font-weight: 600;
}

.tpak-status-warning {
    color: #ffc107;
    font-weight: 600;
}

.tpak-system-status,
.tpak-quick-actions,
.tpak-recent-activity,
.tpak-system-info {
    margin: 30px 0;
}

.tpak-system-status h2,
.tpak-quick-actions h2,
.tpak-recent-activity h2,
.tpak-system-info h2 {
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
</style> 