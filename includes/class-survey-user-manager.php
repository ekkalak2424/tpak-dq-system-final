<?php
/**
 * TPAK DQ System - Survey User Manager
 * 
 * ระบบจัดการผู้ใช้สำหรับแบบสอบถาม
 * รองรับการสร้างผู้ใช้ การมอบหมายสิทธิ์ และการควบคุมการเข้าถึง
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Survey_User_Manager {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Admin menu hooks
        add_action('admin_menu', array($this, 'add_user_management_pages'), 20);
        
        // AJAX handlers
        add_action('wp_ajax_create_survey_user', array($this, 'create_survey_user'));
        add_action('wp_ajax_update_user_permissions', array($this, 'update_user_permissions'));
        add_action('wp_ajax_get_user_activity', array($this, 'get_user_activity'));
        add_action('wp_ajax_generate_access_link', array($this, 'generate_access_link'));
        add_action('wp_ajax_revoke_user_access', array($this, 'revoke_user_access'));
        
        // Capability filtering
        add_filter('user_has_cap', array($this, 'dynamic_capability_check'), 10, 4);
        
        // Access control
        add_action('init', array($this, 'check_survey_access'));
        
        // Notifications
        add_action('tpak_user_assigned', array($this, 'send_assignment_notification'), 10, 3);
        add_action('tpak_user_permissions_changed', array($this, 'send_permission_notification'), 10, 2);
    }
    
    /**
     * สร้าง custom roles สำหรับระบบ
     */
    public function register_custom_roles() {
        // Survey Editor - แก้ไขแบบสอบถามได้
        if (!get_role('survey_editor')) {
            add_role('survey_editor', 'Survey Editor', array(
                'read' => true,
                'edit_posts' => true,
                'edit_survey_responses' => true,
                'view_survey_responses' => true,
                'create_survey_drafts' => true
            ));
        }
        
        // Survey Reviewer - ตรวจสอบแบบสอบถาม
        if (!get_role('survey_reviewer')) {
            add_role('survey_reviewer', 'Survey Reviewer', array(
                'read' => true,
                'view_survey_responses' => true,
                'review_survey_responses' => true,
                'add_review_notes' => true,
                'view_audit_logs' => true
            ));
        }
        
        // Survey Approver - อนุมัติแบบสอบถาม
        if (!get_role('survey_approver')) {
            add_role('survey_approver', 'Survey Approver', array(
                'read' => true,
                'view_survey_responses' => true,
                'review_survey_responses' => true,
                'approve_survey_responses' => true,
                'reject_survey_responses' => true,
                'forward_survey_responses' => true,
                'view_audit_logs' => true
            ));
        }
        
        // Survey Manager - จัดการระบบ
        if (!get_role('survey_manager')) {
            add_role('survey_manager', 'Survey Manager', array(
                'read' => true,
                'edit_posts' => true,
                'view_survey_responses' => true,
                'edit_survey_responses' => true,
                'review_survey_responses' => true,
                'approve_survey_responses' => true,
                'export_survey_responses' => true,
                'forward_survey_responses' => true,
                'manage_survey_users' => true,
                'view_audit_logs' => true,
                'export_audit_logs' => true,
                'configure_survey_settings' => true
            ));
        }
        
        // Survey Viewer - ดูแบบสอบถามอย่างเดียว
        if (!get_role('survey_viewer')) {
            add_role('survey_viewer', 'Survey Viewer', array(
                'read' => true,
                'view_survey_responses' => true
            ));
        }
    }
    
    /**
     * ลงทะเบียน custom capabilities
     */
    public function register_custom_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $capabilities = array(
                'view_survey_responses',
                'edit_survey_responses',
                'review_survey_responses',
                'approve_survey_responses',
                'reject_survey_responses',
                'export_survey_responses',
                'forward_survey_responses',
                'manage_survey_users',
                'view_audit_logs',
                'export_audit_logs',
                'configure_survey_settings',
                'create_survey_drafts',
                'add_review_notes',
                'restore_survey_versions',
                'delete_survey_responses',
                'bulk_process_surveys',
                'schedule_survey_exports',
                'manage_survey_templates'
            );
            
            foreach ($capabilities as $cap) {
                $admin->add_cap($cap);
            }
        }
    }
    
    /**
     * เพิ่มหน้าจัดการผู้ใช้
     */
    public function add_user_management_pages() {
        if (current_user_can('manage_survey_users')) {
            add_submenu_page(
                'tpak-dq-system',
                'จัดการผู้ใช้แบบสอบถาม',
                'จัดการผู้ใช้',
                'manage_survey_users',
                'tpak-survey-users',
                array($this, 'render_user_management_page')
            );
            
            add_submenu_page(
                'tpak-dq-system',
                'สิทธิ์และการเข้าถึง',
                'สิทธิ์และการเข้าถึง',
                'manage_survey_users',
                'tpak-survey-permissions',
                array($this, 'render_permissions_page')
            );
        }
    }
    
    /**
     * Render หน้าจัดการผู้ใช้
     */
    public function render_user_management_page() {
        $users = $this->get_survey_users();
        $roles = $this->get_survey_roles();
        
        ?>
        <div class="wrap">
            <h1>จัดการผู้ใช้แบบสอบถาม</h1>
            
            <!-- สร้างผู้ใช้ใหม่ -->
            <div class="card">
                <h2>เพิ่มผู้ใช้ใหม่</h2>
                <form id="create-user-form">
                    <?php wp_nonce_field('create_survey_user', 'create_user_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="username">ชื่อผู้ใช้</label></th>
                            <td><input type="text" id="username" name="username" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="email">อีเมล</label></th>
                            <td><input type="email" id="email" name="email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="first_name">ชื่อ</label></th>
                            <td><input type="text" id="first_name" name="first_name" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="last_name">นามสกุล</label></th>
                            <td><input type="text" id="last_name" name="last_name" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="role">บทบาท</label></th>
                            <td>
                                <select id="role" name="role" required>
                                    <option value="">-- เลือกบทบาท --</option>
                                    <?php foreach ($roles as $role_key => $role_name): ?>
                                        <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="send_credentials">ส่งข้อมูลการเข้าสู่ระบบ</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="send_credentials" name="send_credentials" checked>
                                    ส่งชื่อผู้ใช้และรหัสผ่านทาง Email
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="สร้างผู้ใช้">
                    </p>
                </form>
            </div>
            
            <!-- รายการผู้ใช้ -->
            <div class="card">
                <h2>รายการผู้ใช้ในระบบ</h2>
                
                <div class="user-filters">
                    <select id="filter-role">
                        <option value="">ทุกบทบาท</option>
                        <?php foreach ($roles as $role_key => $role_name): ?>
                            <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="filter-status">
                        <option value="">ทุกสถานะ</option>
                        <option value="active">ใช้งานอยู่</option>
                        <option value="inactive">ไม่ได้ใช้งาน</option>
                    </select>
                    
                    <button type="button" id="apply-filters" class="button">กรอง</button>
                </div>
                
                <table class="widefat fixed striped" id="users-table">
                    <thead>
                        <tr>
                            <th width="5%"><input type="checkbox" id="select-all-users"></th>
                            <th width="15%">ชื่อผู้ใช้</th>
                            <th width="20%">ชื่อ-นามสกุล</th>
                            <th width="25%">อีเมล</th>
                            <th width="15%">บทบาท</th>
                            <th width="10%">สถานะ</th>
                            <th width="10%">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?php echo esc_attr($user->ID); ?>">
                                <td><input type="checkbox" class="user-checkbox" value="<?php echo esc_attr($user->ID); ?>"></td>
                                <td>
                                    <strong><?php echo esc_html($user->user_login); ?></strong>
                                    <div class="user-meta">
                                        <small>ลงทะเบียน: <?php echo date('Y-m-d', strtotime($user->user_registered)); ?></small>
                                    </div>
                                </td>
                                <td><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <?php 
                                    $user_roles = $user->roles;
                                    foreach ($user_roles as $role) {
                                        $role_obj = get_role($role);
                                        if ($role_obj && isset($roles[$role])) {
                                            echo '<span class="role-badge">' . esc_html($roles[$role]) . '</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $last_login = get_user_meta($user->ID, 'last_login', true);
                                    $is_active = $last_login && (time() - strtotime($last_login)) < (30 * 24 * 60 * 60); // 30 วัน
                                    ?>
                                    <span class="status-badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                        <?php echo $is_active ? 'ใช้งานอยู่' : 'ไม่ได้ใช้งาน'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="user-actions">
                                        <button class="button button-small edit-user" data-user-id="<?php echo esc_attr($user->ID); ?>">แก้ไข</button>
                                        <button class="button button-small view-activity" data-user-id="<?php echo esc_attr($user->ID); ?>">กิจกรรม</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions">
                    <select id="bulk-action">
                        <option value="">-- การดำเนินการแบบกลุ่ม --</option>
                        <option value="change_role">เปลี่ยนบทบาท</option>
                        <option value="send_notification">ส่งการแจ้งเตือน</option>
                        <option value="generate_access_links">สร้างลิงค์เข้าถึง</option>
                        <option value="revoke_access">ยกเลิกการเข้าถึง</option>
                    </select>
                    
                    <div id="bulk-action-options" style="display: none;">
                        <select id="new-role" style="display: none;">
                            <?php foreach ($roles as $role_key => $role_name): ?>
                                <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <textarea id="notification-message" style="display: none;" rows="3" placeholder="ข้อความแจ้งเตือน..."></textarea>
                    </div>
                    
                    <button type="button" id="apply-bulk-action" class="button">ดำเนินการ</button>
                </div>
            </div>
        </div>
        
        <style>
        .role-badge {
            background: #e1f5fe;
            color: #0277bd;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-right: 5px;
        }
        
        .status-badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-badge.active {
            background: #c8e6c9;
            color: #2e7d32;
        }
        
        .status-badge.inactive {
            background: #ffcdd2;
            color: #c62828;
        }
        
        .user-filters {
            margin-bottom: 15px;
        }
        
        .user-filters select {
            margin-right: 10px;
        }
        
        .user-actions button {
            margin-right: 5px;
        }
        
        .bulk-actions {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // สร้างผู้ใช้ใหม่
            $('#create-user-form').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: $(this).serialize() + '&action=create_survey_user',
                    success: function(response) {
                        if (response.success) {
                            alert('สร้างผู้ใช้เรียบร้อย');
                            location.reload();
                        } else {
                            alert('เกิดข้อผิดพลาด: ' + response.data);
                        }
                    }
                });
            });
            
            // แก้ไขผู้ใช้
            $('.edit-user').on('click', function() {
                var userId = $(this).data('user-id');
                // TODO: โหลดข้อมูลผู้ใช้และแสดงใน modal
                alert('แก้ไขผู้ใช้ ID: ' + userId);
            });
            
            // ดูกิจกรรมผู้ใช้
            $('.view-activity').on('click', function() {
                var userId = $(this).data('user-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_user_activity',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce('user_activity_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('กิจกรรมผู้ใช้: ' + JSON.stringify(response.data));
                        }
                    }
                });
            });
            
            // Bulk actions
            $('#bulk-action').on('change', function() {
                var action = $(this).val();
                $('#bulk-action-options').toggle(action !== '');
                
                $('#new-role, #notification-message').hide();
                
                if (action === 'change_role') {
                    $('#new-role').show();
                } else if (action === 'send_notification') {
                    $('#notification-message').show();
                }
            });
            
            $('#apply-bulk-action').on('click', function() {
                var selectedUsers = $('.user-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selectedUsers.length === 0) {
                    alert('กรุณาเลือกผู้ใช้');
                    return;
                }
                
                var action = $('#bulk-action').val();
                if (!action) {
                    alert('กรุณาเลือกการดำเนินการ');
                    return;
                }
                
                console.log('Bulk action:', action, 'Users:', selectedUsers);
            });
            
            // Select all checkbox
            $('#select-all-users').on('change', function() {
                $('.user-checkbox').prop('checked', $(this).is(':checked'));
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render หน้าจัดการสิทธิ์
     */
    public function render_permissions_page() {
        $roles = $this->get_survey_roles();
        $capabilities = $this->get_survey_capabilities();
        
        ?>
        <div class="wrap">
            <h1>สิทธิ์และการเข้าถึง</h1>
            
            <div class="card">
                <h2>จัดการสิทธิ์ตามบทบาท</h2>
                
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="20%">บทบาท</th>
                            <th width="80%">สิทธิ์</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role_key => $role_name): ?>
                            <?php $role = get_role($role_key); ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($role_name); ?></strong>
                                    <br><small><?php echo esc_html($role_key); ?></small>
                                </td>
                                <td>
                                    <div class="capabilities-grid">
                                        <?php foreach ($capabilities as $cap_key => $cap_name): ?>
                                            <label class="capability-item">
                                                <input type="checkbox" 
                                                       name="capabilities[<?php echo esc_attr($role_key); ?>][<?php echo esc_attr($cap_key); ?>]"
                                                       value="1"
                                                       <?php checked($role && $role->has_cap($cap_key)); ?>
                                                       data-role="<?php echo esc_attr($role_key); ?>"
                                                       data-capability="<?php echo esc_attr($cap_key); ?>">
                                                <?php echo esc_html($cap_name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="button" id="save-permissions" class="button-primary">บันทึกการเปลี่ยนแปลง</button>
                    <button type="button" id="reset-permissions" class="button">รีเซ็ตเป็นค่าเริ่มต้น</button>
                </p>
            </div>
        </div>
        
        <style>
        .capabilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .capability-item {
            display: flex;
            align-items: center;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        
        .capability-item input {
            margin-right: 8px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // บันทึกสิทธิ์
            $('#save-permissions').on('click', function() {
                var permissions = {};
                
                $('input[name^="capabilities"]:checked').each(function() {
                    var role = $(this).data('role');
                    var capability = $(this).data('capability');
                    
                    if (!permissions[role]) {
                        permissions[role] = [];
                    }
                    permissions[role].push(capability);
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'update_user_permissions',
                        permissions: permissions,
                        nonce: '<?php echo wp_create_nonce('update_permissions_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('บันทึกสิทธิ์เรียบร้อย');
                        } else {
                            alert('เกิดข้อผิดพลาด: ' + response.data);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * สร้างผู้ใช้ใหม่
     */
    public function create_survey_user() {
        check_ajax_referer('create_survey_user', 'create_user_nonce');
        
        if (!current_user_can('manage_survey_users')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการสร้างผู้ใช้');
        }
        
        $username = sanitize_text_field($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $role = sanitize_text_field($_POST['role']);
        $send_credentials = isset($_POST['send_credentials']);
        
        // ตรวจสอบข้อมูล
        if (username_exists($username) || email_exists($email)) {
            wp_send_json_error('ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว');
        }
        
        // สร้างรหัสผ่านสุ่ม
        $password = wp_generate_password(12, false);
        
        // สร้างผู้ใช้
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        // อัปเดตข้อมูลเพิ่มเติม
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role
        ));
        
        // บันทึก metadata
        update_user_meta($user_id, 'created_by', get_current_user_id());
        update_user_meta($user_id, 'created_for_surveys', true);
        
        // ส่งข้อมูลการเข้าสู่ระบบ
        if ($send_credentials) {
            $this->send_user_credentials($user_id, $username, $password);
        }
        
        // บันทึก audit log
        $this->log_user_action('user_created', array(
            'user_id' => $user_id,
            'username' => $username,
            'role' => $role
        ));
        
        wp_send_json_success(array(
            'message' => 'สร้างผู้ใช้เรียบร้อย',
            'user_id' => $user_id
        ));
    }
    
    /**
     * อัปเดตสิทธิ์ผู้ใช้
     */
    public function update_user_permissions() {
        check_ajax_referer('update_permissions_nonce', 'nonce');
        
        if (!current_user_can('manage_survey_users')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการจัดการสิทธิ์');
        }
        
        $permissions = $_POST['permissions'];
        
        foreach ($permissions as $role_key => $capabilities) {
            $role = get_role($role_key);
            if (!$role) continue;
            
            // ลบสิทธิ์เดิมทั้งหมด
            $survey_caps = $this->get_survey_capabilities();
            foreach (array_keys($survey_caps) as $cap) {
                $role->remove_cap($cap);
            }
            
            // เพิ่มสิทธิ์ใหม่
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
        
        // บันทึก audit log
        $this->log_user_action('permissions_updated', array(
            'updated_roles' => array_keys($permissions)
        ));
        
        wp_send_json_success('อัปเดตสิทธิ์เรียบร้อย');
    }
    
    /**
     * ดึงกิจกรรมผู้ใช้
     */
    public function get_user_activity() {
        check_ajax_referer('user_activity_nonce', 'nonce');
        
        if (!current_user_can('view_audit_logs')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการดูกิจกรรม');
        }
        
        $user_id = intval($_POST['user_id']);
        
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $audit_table 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 50",
            $user_id
        ), ARRAY_A);
        
        wp_send_json_success(array('activities' => $activities));
    }
    
    /**
     * สร้างลิงค์เข้าถึงพิเศษ
     */
    public function generate_access_link() {
        check_ajax_referer('generate_access_link', 'access_link_nonce');
        
        if (!current_user_can('forward_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการสร้างลิงค์เข้าถึง');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $access_level = sanitize_text_field($_POST['access_level']);
        $expires_in = intval($_POST['expires_in']);
        $max_uses = intval($_POST['max_uses']);
        
        // สร้าง token
        $token = wp_generate_password(32, false);
        
        // คำนวณวันหมดอายุ
        $expires_at = null;
        if ($expires_in > 0) {
            $expires_at = date('Y-m-d H:i:s', time() + ($expires_in * 24 * 60 * 60));
        }
        
        // บันทึกลงฐานข้อมูล
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_access_tokens';
        
        // สร้างตารางถ้ายังไม่มี
        $this->ensure_access_tokens_table();
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'token' => $token,
                'response_id' => $response_id,
                'access_level' => $access_level,
                'created_by' => get_current_user_id(),
                'expires_at' => $expires_at,
                'max_uses' => $max_uses ?: null,
                'uses_count' => 0,
                'is_active' => 1,
                'created_at' => current_time('mysql')
            )
        );
        
        if ($result) {
            $url = home_url('/survey-access/' . $token);
            
            wp_send_json_success(array(
                'url' => $url,
                'token' => $token,
                'expires_at' => $expires_at
            ));
        } else {
            wp_send_json_error('ไม่สามารถสร้างลิงค์ได้');
        }
    }
    
    /**
     * ยกเลิกการเข้าถึงผู้ใช้
     */
    public function revoke_user_access() {
        check_ajax_referer('revoke_access_nonce', 'nonce');
        
        if (!current_user_can('manage_survey_users')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการยกเลิกการเข้าถึง');
        }
        
        $user_id = intval($_POST['user_id']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        // ปิดการใช้งานบัญชี
        $user = new WP_User($user_id);
        $user->set_role(''); // ลบ role
        
        wp_send_json_success('ยกเลิกการเข้าถึงเรียบร้อย');
    }
    
    /**
     * ตรวจสอบการเข้าถึงแบบสอบถาม
     */
    public function check_survey_access() {
        // TODO: Implement token-based access checking
    }
    
    /**
     * Dynamic capability check
     */
    public function dynamic_capability_check($allcaps, $caps, $args, $user) {
        // TODO: Implement dynamic capability checking
        return $allcaps;
    }
    
    /**
     * ส่งการแจ้งเตือนการมอบหมาย
     */
    public function send_assignment_notification($user_id, $response_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'คุณได้รับมอบหมายให้ดูแลแบบสอบถาม';
        $message = sprintf(
            "สวัสดี %s,\n\nคุณได้รับมอบหมายให้ดูแลแบบสอบถาม ID: %s\nบทบาท: %s",
            $user->display_name,
            $response_id,
            $role
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * ส่งการแจ้งเตือนการเปลี่ยนสิทธิ์
     */
    public function send_permission_notification($user_id, $changes) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'สิทธิ์การเข้าถึงของคุณมีการเปลี่ยนแปลง';
        $message = sprintf(
            "สวัสดี %s,\n\nสิทธิ์การเข้าถึงระบบของคุณมีการเปลี่ยนแปลง",
            $user->display_name
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * ส่งข้อมูลการเข้าสู่ระบบ
     */
    private function send_user_credentials($user_id, $username, $password) {
        $user = get_userdata($user_id);
        
        $subject = 'ข้อมูลการเข้าสู่ระบบแบบสอบถาม';
        $message = sprintf(
            "สวัสดี %s,\n\nบัญชีของคุณถูกสร้างเรียบร้อยแล้ว:\n\nชื่อผู้ใช้: %s\nรหัสผ่าน: %s\n\nเข้าสู่ระบบได้ที่: %s",
            $user->display_name ?: $user->user_login,
            $username,
            $password,
            wp_login_url()
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * ดึงรายการผู้ใช้ในระบบ survey
     */
    private function get_survey_users() {
        $users = get_users(array(
            'meta_key' => 'created_for_surveys',
            'meta_value' => true,
            'orderby' => 'registered',
            'order' => 'DESC'
        ));
        
        // รวมผู้ใช้ที่มี survey roles
        $survey_roles = array_keys($this->get_survey_roles());
        $role_users = get_users(array(
            'role__in' => $survey_roles
        ));
        
        // รวมและลบ duplicate
        $all_users = array_merge($users, $role_users);
        $unique_users = array();
        $seen_ids = array();
        
        foreach ($all_users as $user) {
            if (!in_array($user->ID, $seen_ids)) {
                $unique_users[] = $user;
                $seen_ids[] = $user->ID;
            }
        }
        
        return $unique_users;
    }
    
    /**
     * ดึง survey roles
     */
    private function get_survey_roles() {
        return array(
            'survey_editor' => 'Survey Editor',
            'survey_reviewer' => 'Survey Reviewer', 
            'survey_approver' => 'Survey Approver',
            'survey_manager' => 'Survey Manager',
            'survey_viewer' => 'Survey Viewer'
        );
    }
    
    /**
     * ดึง survey capabilities
     */
    private function get_survey_capabilities() {
        return array(
            'view_survey_responses' => 'ดูแบบสอบถาม',
            'edit_survey_responses' => 'แก้ไขแบบสอบถาม',
            'review_survey_responses' => 'ตรวจสอบแบบสอบถาม',
            'approve_survey_responses' => 'อนุมัติแบบสอบถาม',
            'reject_survey_responses' => 'ส่งกลับแก้ไข',
            'export_survey_responses' => 'ส่งออกข้อมูล',
            'forward_survey_responses' => 'ส่งต่อแบบสอบถาม',
            'manage_survey_users' => 'จัดการผู้ใช้',
            'view_audit_logs' => 'ดู Audit Log',
            'export_audit_logs' => 'ส่งออก Audit Log',
            'configure_survey_settings' => 'ตั้งค่าระบบ',
            'create_survey_drafts' => 'สร้างฉบับร่าง',
            'add_review_notes' => 'เพิ่มหมายเหตุ',
            'restore_survey_versions' => 'คืนค่าเวอร์ชัน',
            'delete_survey_responses' => 'ลบแบบสอบถาม',
            'bulk_process_surveys' => 'ประมวลผลแบบกลุ่ม',
            'schedule_survey_exports' => 'กำหนดเวลาส่งออก',
            'manage_survey_templates' => 'จัดการเทมเพลต'
        );
    }
    
    /**
     * สร้างตาราง access tokens
     */
    private function ensure_access_tokens_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'tpak_access_tokens';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token varchar(100) NOT NULL,
            response_id varchar(100) NOT NULL,
            access_level enum('view', 'comment', 'edit') DEFAULT 'view',
            created_by bigint(20) NOT NULL,
            expires_at datetime,
            max_uses int,
            uses_count int DEFAULT 0,
            last_used_at datetime,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY response_id (response_id),
            KEY created_by (created_by),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * บันทึก user action log
     */
    private function log_user_action($action, $data = array()) {
        // ใช้ Survey Audit Manager
        if (class_exists('TPAK_Survey_Audit_Manager')) {
            $audit_manager = TPAK_Survey_Audit_Manager::getInstance();
            $audit_manager->log_audit('user_management', $action, $data);
        }
    }
}