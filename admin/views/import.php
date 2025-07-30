<?php
/**
 * TPAK DQ System - Import View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('TPAK DQ System - Import Data', 'tpak-dq-system'); ?></h1>
    
    <div class="tpak-import-page">
        <!-- Manual Import Section -->
        <div class="tpak-import-section">
            <h2><?php _e('นำเข้าข้อมูลด้วยตนเอง', 'tpak-dq-system'); ?></h2>
            
            <?php if (isset($result)): ?>
                <?php if ($result['success']): ?>
                    <div class="tpak-import-status success">
                        <h3><?php _e('นำเข้าข้อมูลสำเร็จ', 'tpak-dq-system'); ?></h3>
                        <p><?php echo esc_html($result['message']); ?></p>
                        <?php if (!empty($result['errors'])): ?>
                            <h4><?php _e('ข้อผิดพลาด:', 'tpak-dq-system'); ?></h4>
                            <ul>
                                <?php foreach ($result['errors'] as $error): ?>
                                    <li><?php echo esc_html($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="tpak-import-status error">
                        <h3><?php _e('นำเข้าข้อมูลล้มเหลว', 'tpak-dq-system'); ?></h3>
                        <p><?php echo esc_html($result['message']); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('tpak_manual_import'); ?>
                
                <div class="tpak-form-row">
                    <label for="survey_id_manual"><?php _e('Survey ID', 'tpak-dq-system'); ?></label>
                    <input type="text" id="survey_id_manual" name="survey_id_manual" 
                           value="<?php echo esc_attr($options['survey_id'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('ID ของแบบสอบถามที่ต้องการนำเข้า', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <label for="start_date"><?php _e('วันที่เริ่มต้น (ไม่บังคับ)', 'tpak-dq-system'); ?></label>
                    <input type="date" id="start_date" name="start_date" class="regular-text" />
                    <p class="description">
                        <?php _e('นำเข้าข้อมูลตั้งแต่วันที่นี้ (รูปแบบ: YYYY-MM-DD)', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <label for="end_date"><?php _e('วันที่สิ้นสุด (ไม่บังคับ)', 'tpak-dq-system'); ?></label>
                    <input type="date" id="end_date" name="end_date" class="regular-text" />
                    <p class="description">
                        <?php _e('นำเข้าข้อมูลจนถึงวันที่นี้ (รูปแบบ: YYYY-MM-DD)', 'tpak-dq-system'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-row">
                    <button type="submit" name="manual_import" class="button button-primary" id="tpak-manual-import">
                        <?php _e('นำเข้าข้อมูล', 'tpak-dq-system'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- API Status Section -->
        <div class="tpak-import-section">
            <h2><?php _e('สถานะการเชื่อมต่อ API', 'tpak-dq-system'); ?></h2>
            
            <?php if ($api_handler->is_configured()): ?>
                <?php if ($api_handler->test_connection()): ?>
                    <div class="tpak-import-status success">
                        <h3><?php _e('เชื่อมต่อสำเร็จ', 'tpak-dq-system'); ?></h3>
                        <p><?php _e('สามารถเชื่อมต่อกับ LimeSurvey API ได้', 'tpak-dq-system'); ?></p>
                    </div>
                    
                    <!-- Available Surveys -->
                    <div class="tpak-surveys-list">
                        <h3><?php _e('แบบสอบถามที่มีอยู่', 'tpak-dq-system'); ?></h3>
                        <?php
                        $surveys = $api_handler->get_surveys();
                        if ($surveys && !empty($surveys)):
                        ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Survey ID', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('ชื่อแบบสอบถาม', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('สถานะ', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('การดำเนินการ', 'tpak-dq-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($surveys as $survey): ?>
                                    <tr>
                                        <td><?php echo esc_html($survey['sid']); ?></td>
                                        <td><?php echo esc_html($survey['surveyls_title']); ?></td>
                                        <td>
                                            <?php 
                                            $status = $survey['active'] ? __('เปิดใช้งาน', 'tpak-dq-system') : __('ปิดใช้งาน', 'tpak-dq-system');
                                            $status_class = $survey['active'] ? 'success' : 'warning';
                                            ?>
                                            <span class="tpak-status-<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small tpak-import-survey" 
                                                    data-survey-id="<?php echo esc_attr($survey['sid']); ?>">
                                                <?php _e('นำเข้า', 'tpak-dq-system'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <p><?php _e('ไม่พบแบบสอบถามหรือไม่สามารถดึงข้อมูลได้', 'tpak-dq-system'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="tpak-import-status error">
                        <h3><?php _e('เชื่อมต่อล้มเหลว', 'tpak-dq-system'); ?></h3>
                        <p><?php _e('ไม่สามารถเชื่อมต่อกับ LimeSurvey API ได้ กรุณาตรวจสอบการตั้งค่า', 'tpak-dq-system'); ?></p>
                        <p><a href="<?php echo admin_url('admin.php?page=tpak-dq-settings'); ?>" class="button">
                            <?php _e('ไปที่การตั้งค่า', 'tpak-dq-system'); ?>
                        </a></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="tpak-import-status warning">
                    <h3><?php _e('ยังไม่ได้ตั้งค่า API', 'tpak-dq-system'); ?></h3>
                    <p><?php _e('กรุณาตั้งค่า LimeSurvey API ก่อนนำเข้าข้อมูล', 'tpak-dq-system'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=tpak-dq-settings'); ?>" class="button button-primary">
                        <?php _e('ตั้งค่า API', 'tpak-dq-system'); ?>
                    </a></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Import History -->
        <div class="tpak-import-section">
            <h2><?php _e('ประวัติการนำเข้า', 'tpak-dq-system'); ?></h2>
            
            <?php
            $recent_imports = get_posts(array(
                'post_type' => 'verification_batch',
                'posts_per_page' => 20,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => '_import_date',
                        'compare' => 'EXISTS'
                    )
                )
            ));
            
            if (!empty($recent_imports)):
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ชุดข้อมูล', 'tpak-dq-system'); ?></th>
                        <th><?php _e('LimeSurvey ID', 'tpak-dq-system'); ?></th>
                        <th><?php _e('วันที่นำเข้า', 'tpak-dq-system'); ?></th>
                        <th><?php _e('สถานะปัจจุบัน', 'tpak-dq-system'); ?></th>
                        <th><?php _e('การดำเนินการ', 'tpak-dq-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_imports as $post): ?>
                        <?php
                        $lime_survey_id = get_post_meta($post->ID, '_lime_survey_id', true);
                        $import_date = get_post_meta($post->ID, '_import_date', true);
                        $workflow = new TPAK_DQ_Workflow();
                        $status = $workflow->get_batch_status($post->ID);
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($lime_survey_id); ?></td>
                            <td>
                                <?php 
                                if ($import_date) {
                                    echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import_date));
                                } else {
                                    echo __('ไม่ระบุ', 'tpak-dq-system');
                                }
                                ?>
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
                                <a href="<?php echo get_edit_post_link($post->ID); ?>" class="button button-small">
                                    <?php _e('ดูรายละเอียด', 'tpak-dq-system'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p><?php _e('ยังไม่มีประวัติการนำเข้าข้อมูล', 'tpak-dq-system'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Import Statistics -->
        <div class="tpak-import-section">
            <h2><?php _e('สถิติการนำเข้า', 'tpak-dq-system'); ?></h2>
            
            <div class="tpak-stats-grid">
                <?php
                // Get total imported count
                $total_imported = wp_count_posts('verification_batch');
                $total_count = $total_imported->publish + $total_imported->private + $total_imported->draft;
                
                // Get today's imported count
                $today_imported = get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'date_query' => array(
                        array(
                            'after' => '1 day ago'
                        )
                    )
                ));
                $today_count = count($today_imported);
                
                // Get status counts using taxonomy
                $pending_a_count = count(get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => 'pending_a'
                        )
                    )
                )));
                
                $pending_b_count = count(get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => 'pending_b'
                        )
                    )
                )));
                
                $pending_c_count = count(get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => 'pending_c'
                        )
                    )
                )));
                
                $finalized_count = count(get_posts(array(
                    'post_type' => 'verification_batch',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'verification_status',
                            'field' => 'slug',
                            'terms' => array('finalized', 'finalized_by_sampling')
                        )
                    )
                )));
                ?>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('นำเข้าทั้งหมด', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo $total_count; ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('นำเข้าวันนี้', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo $today_count; ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('รอการตรวจสอบ', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo $pending_a_count + $pending_b_count + $pending_c_count; ?></div>
                </div>
                
                <div class="tpak-stat-card">
                    <h3><?php _e('เสร็จสมบูรณ์', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-stat-number"><?php echo $finalized_count; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Import survey from list
    $('.tpak-import-survey').on('click', function() {
        var button = $(this);
        var surveyId = button.data('survey-id');
        
        if (confirm('<?php _e('คุณต้องการนำเข้าแบบสอบถาม ID: ', 'tpak-dq-system'); ?>' + surveyId + '?')) {
            button.prop('disabled', true).text('<?php _e('กำลังนำเข้า...', 'tpak-dq-system'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tpak_import_survey',
                    nonce: '<?php echo wp_create_nonce('tpak_import_survey'); ?>',
                    survey_id: surveyId
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('นำเข้าข้อมูลสำเร็จ', 'tpak-dq-system'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('นำเข้าข้อมูลล้มเหลว: ', 'tpak-dq-system'); ?>' + response.data.message);
                    }
                },
                error: function() {
                    alert('<?php _e('นำเข้าข้อมูลล้มเหลว กรุณาลองใหม่อีกครั้ง', 'tpak-dq-system'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('นำเข้า', 'tpak-dq-system'); ?>');
                }
            });
        }
    });
    
    // Date validation
    $('#start_date, #end_date').on('change', function() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (startDate && endDate && startDate > endDate) {
            alert('<?php _e('วันที่เริ่มต้นต้องไม่เกินวันที่สิ้นสุด', 'tpak-dq-system'); ?>');
            $(this).val('');
        }
    });
});
</script>

<style>
.tpak-surveys-list {
    margin-top: 20px;
}

.tpak-status-success {
    color: #28a745;
    font-weight: 600;
}

.tpak-status-warning {
    color: #ffc107;
    font-weight: 600;
}

.tpak-status-error {
    color: #dc3545;
    font-weight: 600;
}
</style> 