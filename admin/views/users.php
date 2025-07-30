<?php
/**
 * TPAK DQ System - Users Management View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('จัดการผู้ใช้งาน TPAK DQ System', 'tpak-dq-system'); ?></h1>
    
    <?php settings_errors(); ?>
    
    <div class="tpak-users-page">
        <!-- User Statistics -->
        <div class="tpak-user-stats">
            <h2><?php _e('สถิติผู้ใช้งาน', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-stats-grid">
                <?php
                $interviewers = get_users(array('role' => 'interviewer'));
                $supervisors = get_users(array('role' => 'supervisor'));
                $examiners = get_users(array('role' => 'examiner'));
                $administrators = get_users(array('role' => 'administrator'));
                ?>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('ผู้ตรวจสอบขั้นที่ 1', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo count($interviewers); ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('ผู้ตรวจสอบขั้นที่ 2', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo count($supervisors); ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('ผู้ตรวจสอบขั้นที่ 3', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo count($examiners); ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('ผู้ดูแลระบบ', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo count($administrators); ?></div>
                </div>
            </div>
        </div>
        
        <!-- User Lists -->
        <div class="tpak-user-lists">
            <h2><?php _e('รายการผู้ใช้งาน', 'tpak-dq-system'); ?></h2>
            
            <!-- Interviewers -->
            <div class="tpak-user-section">
                <h3><?php _e('ผู้ตรวจสอบขั้นที่ 1 (Interviewer)', 'tpak-dq-system'); ?></h3>
                <?php if (!empty($interviewers)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('ชื่อ', 'tpak-dq-system'); ?></th>
                                <th><?php _e('อีเมล', 'tpak-dq-system'); ?></th>
                                <th><?php _e('วันที่สมัคร', 'tpak-dq-system'); ?></th>
                                <th><?php _e('สถานะ', 'tpak-dq-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($interviewers as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user->display_name); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))); ?></td>
                                    <td>
                                        <span class="tpak-status-active"><?php _e('ใช้งาน', 'tpak-dq-system'); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('ยังไม่มีผู้ตรวจสอบขั้นที่ 1', 'tpak-dq-system'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Supervisors -->
            <div class="tpak-user-section">
                <h3><?php _e('ผู้ตรวจสอบขั้นที่ 2 (Supervisor)', 'tpak-dq-system'); ?></h3>
                <?php if (!empty($supervisors)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('ชื่อ', 'tpak-dq-system'); ?></th>
                                <th><?php _e('อีเมล', 'tpak-dq-system'); ?></th>
                                <th><?php _e('วันที่สมัคร', 'tpak-dq-system'); ?></th>
                                <th><?php _e('สถานะ', 'tpak-dq-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supervisors as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user->display_name); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))); ?></td>
                                    <td>
                                        <span class="tpak-status-active"><?php _e('ใช้งาน', 'tpak-dq-system'); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('ยังไม่มีผู้ตรวจสอบขั้นที่ 2', 'tpak-dq-system'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Examiners -->
            <div class="tpak-user-section">
                <h3><?php _e('ผู้ตรวจสอบขั้นที่ 3 (Examiner)', 'tpak-dq-system'); ?></h3>
                <?php if (!empty($examiners)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('ชื่อ', 'tpak-dq-system'); ?></th>
                                <th><?php _e('อีเมล', 'tpak-dq-system'); ?></th>
                                <th><?php _e('วันที่สมัคร', 'tpak-dq-system'); ?></th>
                                <th><?php _e('สถานะ', 'tpak-dq-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($examiners as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user->display_name); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))); ?></td>
                                    <td>
                                        <span class="tpak-status-active"><?php _e('ใช้งาน', 'tpak-dq-system'); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('ยังไม่มีผู้ตรวจสอบขั้นที่ 3', 'tpak-dq-system'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="tpak-quick-actions">
            <h2><?php _e('การดำเนินการด่วน', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-action-grid">
                <a href="<?php echo admin_url('user-new.php'); ?>" class="tpak-action-card">
                    <h3><?php _e('เพิ่มผู้ใช้งานใหม่', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('สร้างผู้ใช้งานใหม่และกำหนดสิทธิ์', 'tpak-dq-system'); ?></p>
                </a>
                
                <a href="<?php echo admin_url('users.php'); ?>" class="tpak-action-card">
                    <h3><?php _e('จัดการผู้ใช้งาน', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('แก้ไขข้อมูลและสิทธิ์ผู้ใช้งาน', 'tpak-dq-system'); ?></p>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=tpak-dq-settings'); ?>" class="tpak-action-card">
                    <h3><?php _e('ตั้งค่าระบบ', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('ตั้งค่า API และการแจ้งเตือน', 'tpak-dq-system'); ?></p>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.tpak-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.tpak-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tpak-stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
}

.tpak-user-section {
    margin: 30px 0;
}

.tpak-user-section h3 {
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.tpak-status-active {
    color: #28a745;
    font-weight: 600;
}

.tpak-action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.tpak-action-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tpak-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    text-decoration: none;
    color: inherit;
}

.tpak-action-card h3 {
    margin-top: 0;
    color: #0073aa;
}

.tpak-quick-actions h2 {
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
</style> 