<?php
/**
 * TPAK DQ System - Survey Responses View
 * แสดงรายการ Response ทั้งหมดที่ import มาแล้ว
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

// Query arguments
$args = array(
    'post_type' => 'verification_batch',
    'posts_per_page' => 20,
    'paged' => $paged,
    'orderby' => 'date',
    'order' => 'DESC'
);

// Add status filter
if (!empty($current_status)) {
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'verification_status',
            'field' => 'slug',
            'terms' => $current_status
        )
    );
}

// Add search
if (!empty($search_query)) {
    $args['s'] = $search_query;
}

$query = new WP_Query($args);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('ข้อมูลแบบสอบถาม', 'tpak-dq-system'); ?>
        <span class="count">(<?php echo $query->found_posts; ?>)</span>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=tpak-dq-import'); ?>" class="page-title-action">
        <?php _e('นำเข้าข้อมูลใหม่', 'tpak-dq-system'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Filters -->
    <div class="tpak-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="tpak-dq-responses">
            
            <!-- Status Filter -->
            <select name="status" id="filter-status">
                <option value=""><?php _e('ทุกสถานะ', 'tpak-dq-system'); ?></option>
                <?php
                $statuses = get_terms(array(
                    'taxonomy' => 'verification_status',
                    'hide_empty' => false
                ));
                foreach ($statuses as $status) {
                    $selected = ($current_status == $status->slug) ? 'selected' : '';
                    echo '<option value="' . esc_attr($status->slug) . '" ' . $selected . '>' . esc_html($status->name) . '</option>';
                }
                ?>
            </select>
            
            <!-- Search Box -->
            <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" 
                   placeholder="<?php _e('ค้นหา Response ID...', 'tpak-dq-system'); ?>">
            
            <input type="submit" class="button" value="<?php _e('กรองข้อมูล', 'tpak-dq-system'); ?>">
            
            <?php if (!empty($current_status) || !empty($search_query)): ?>
                <a href="<?php echo admin_url('admin.php?page=tpak-dq-responses'); ?>" class="button">
                    <?php _e('ล้างตัวกรอง', 'tpak-dq-system'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Responses Grid/Table -->
    <?php if ($query->have_posts()): ?>
        
        <!-- View Toggle -->
        <div class="tpak-view-toggle">
            <button class="button view-grid active" data-view="grid">
                <span class="dashicons dashicons-grid-view"></span> <?php _e('มุมมองการ์ด', 'tpak-dq-system'); ?>
            </button>
            <button class="button view-table" data-view="table">
                <span class="dashicons dashicons-list-view"></span> <?php _e('มุมมองตาราง', 'tpak-dq-system'); ?>
            </button>
        </div>
        
        <!-- Grid View -->
        <div class="tpak-responses-grid view-content" id="grid-view">
            <?php while ($query->have_posts()): $query->the_post(); ?>
                <?php
                // Get the actual survey data that was imported
                $survey_data_json = get_post_meta(get_the_ID(), '_survey_data', true);
                $response_data = json_decode($survey_data_json, true);
                $lime_response_id = get_post_meta(get_the_ID(), '_lime_response_id', true);
                $lime_survey_id = get_post_meta(get_the_ID(), '_lime_survey_id', true);
                $workflow = new TPAK_DQ_Workflow();
                $status = $workflow->get_batch_status(get_the_ID());
                ?>
                
                <div class="response-card status-<?php echo esc_attr($status); ?>">
                    <div class="card-header">
                        <span class="response-id">#<?php echo esc_html($lime_response_id ?: get_the_ID()); ?></span>
                        <?php if ($status): ?>
                            <?php
                            $status_term = get_term_by('slug', $status, 'verification_status');
                            $status_name = $status_term ? $status_term->name : $status;
                            ?>
                            <span class="status-badge <?php echo esc_attr($status); ?>">
                                <?php echo esc_html($status_name); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <h3><?php the_title(); ?></h3>
                        
                        <div class="card-meta">
                            <div class="meta-item">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php echo get_the_date(); ?>
                            </div>
                            
                            <?php if ($response_data && is_array($response_data)): ?>
                                <div class="meta-item">
                                    <span class="dashicons dashicons-editor-ul"></span>
                                    <?php 
                                    $question_count = count($response_data);
                                    printf(_n('%d คำถาม', '%d คำถาม', $question_count, 'tpak-dq-system'), $question_count);
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Preview of first few questions -->
                        <?php if ($response_data && is_array($response_data)): ?>
                            <div class="response-preview">
                                <?php
                                $preview_count = 0;
                                foreach ($response_data as $field_key => $field_value) {
                                    if ($preview_count >= 3) break;
                                    // Skip metadata fields and show only actual question responses
                                    if (in_array($field_key, ['id', 'submitdate', 'lastpage', 'startlanguage', 'seed', 'startdate', 'datestamp'])) {
                                        continue;
                                    }
                                    if (!empty($field_value) && $field_value !== '' && $field_value !== null) {
                                        echo '<div class="preview-item">';
                                        echo '<strong>' . esc_html($field_key) . ':</strong> ';
                                        echo '<span>' . esc_html(mb_substr(strip_tags($field_value), 0, 50)) . (strlen($field_value) > 50 ? '...' : '') . '</span>';
                                        echo '</div>';
                                        $preview_count++;
                                    }
                                }
                                
                                // Debug info for admins when no preview items found
                                if ($preview_count === 0 && current_user_can('manage_options')) {
                                    echo '<div class="preview-item" style="color: #999; font-style: italic;">';
                                    echo '<small>Debug: ';
                                    if ($response_data && is_array($response_data)) {
                                        echo 'Fields: ' . implode(', ', array_keys($response_data));
                                    } else {
                                        echo 'No response data found';
                                    }
                                    echo '</small>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer">
                        <a href="<?php echo admin_url('admin.php?page=tpak-dq-response-view&id=' . get_the_ID()); ?>" 
                           class="button button-primary">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('ดูรายละเอียด', 'tpak-dq-system'); ?>
                        </a>
                        <a href="<?php echo get_edit_post_link(); ?>" class="button">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('แก้ไข', 'tpak-dq-system'); ?>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Table View -->
        <div class="tpak-responses-table view-content" id="table-view" style="display: none;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-id"><?php _e('Response ID', 'tpak-dq-system'); ?></th>
                        <th class="column-title"><?php _e('ชื่อชุดข้อมูล', 'tpak-dq-system'); ?></th>
                        <th class="column-status"><?php _e('สถานะ', 'tpak-dq-system'); ?></th>
                        <th class="column-date"><?php _e('วันที่', 'tpak-dq-system'); ?></th>
                        <th class="column-questions"><?php _e('จำนวนคำถาม', 'tpak-dq-system'); ?></th>
                        <th class="column-actions"><?php _e('การดำเนินการ', 'tpak-dq-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $query->rewind_posts();
                    while ($query->have_posts()): $query->the_post(); 
                    ?>
                        <?php
                        // Get the actual survey data that was imported
                        $survey_data_json = get_post_meta(get_the_ID(), '_survey_data', true);
                        $response_data = json_decode($survey_data_json, true);
                        $lime_response_id = get_post_meta(get_the_ID(), '_lime_response_id', true);
                        $workflow = new TPAK_DQ_Workflow();
                        $status = $workflow->get_batch_status(get_the_ID());
                        ?>
                        <tr>
                            <td>#<?php echo esc_html($lime_response_id ?: get_the_ID()); ?></td>
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=tpak-dq-response-view&id=' . get_the_ID()); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <?php if ($status): ?>
                                    <?php
                                    $status_term = get_term_by('slug', $status, 'verification_status');
                                    $status_name = $status_term ? $status_term->name : $status;
                                    ?>
                                    <span class="status-badge <?php echo esc_attr($status); ?>">
                                        <?php echo esc_html($status_name); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo get_the_date(); ?></td>
                            <td>
                                <?php 
                                if ($response_data && is_array($response_data)) {
                                    echo count($response_data);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=tpak-dq-response-view&id=' . get_the_ID()); ?>" 
                                   class="button button-small">
                                    <?php _e('ดู', 'tpak-dq-system'); ?>
                                </a>
                                <a href="<?php echo get_edit_post_link(); ?>" class="button button-small">
                                    <?php _e('แก้ไข', 'tpak-dq-system'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="tpak-pagination">
            <?php
            $big = 999999999;
            echo paginate_links(array(
                'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format' => '?paged=%#%',
                'current' => max(1, $paged),
                'total' => $query->max_num_pages,
                'prev_text' => __('« ก่อนหน้า', 'tpak-dq-system'),
                'next_text' => __('ถัดไป »', 'tpak-dq-system')
            ));
            ?>
        </div>
        
    <?php else: ?>
        <div class="tpak-no-items">
            <p><?php _e('ไม่พบข้อมูลแบบสอบถาม', 'tpak-dq-system'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=tpak-dq-import'); ?>" class="button button-primary">
                <?php _e('นำเข้าข้อมูลจาก LimeSurvey', 'tpak-dq-system'); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <?php wp_reset_postdata(); ?>
</div>

<style>
/* Filter Styles */
.tpak-filters {
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.tpak-filters form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.tpak-filters select,
.tpak-filters input[type="search"] {
    min-width: 200px;
}

/* View Toggle */
.tpak-view-toggle {
    margin: 20px 0;
    display: flex;
    gap: 5px;
}

.tpak-view-toggle .button.active {
    background: #0073aa;
    color: #fff;
    border-color: #0073aa;
}

/* Grid View */
.tpak-responses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.response-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.response-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.response-card.status-pending_a { border-left: 4px solid #f0ad4e; }
.response-card.status-pending_b { border-left: 4px solid #5bc0de; }
.response-card.status-pending_c { border-left: 4px solid #d9534f; }
.response-card.status-finalized { border-left: 4px solid #5cb85c; }

.card-header {
    padding: 12px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.response-id {
    font-weight: 600;
    color: #666;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.pending_a { background: #fff3cd; color: #856404; }
.status-badge.pending_b { background: #d1ecf1; color: #0c5460; }
.status-badge.pending_c { background: #f8d7da; color: #721c24; }
.status-badge.finalized { background: #d4edda; color: #155724; }

.card-body {
    padding: 15px;
}

.card-body h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #23282d;
}

.card-meta {
    display: flex;
    gap: 15px;
    margin: 10px 0;
    color: #666;
    font-size: 13px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.meta-item .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.response-preview {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.preview-item {
    margin-bottom: 8px;
    font-size: 13px;
    color: #555;
}

.preview-item strong {
    color: #23282d;
    margin-right: 5px;
}

.card-footer {
    padding: 12px 15px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
}

.card-footer .button {
    flex: 1;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.card-footer .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Table View */
.tpak-responses-table {
    margin: 20px 0;
}

.tpak-responses-table .column-id { width: 100px; }
.tpak-responses-table .column-status { width: 150px; }
.tpak-responses-table .column-date { width: 120px; }
.tpak-responses-table .column-questions { width: 100px; }
.tpak-responses-table .column-actions { width: 150px; }

/* No Items */
.tpak-no-items {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

.tpak-no-items p {
    font-size: 16px;
    color: #666;
    margin-bottom: 20px;
}

/* Pagination */
.tpak-pagination {
    margin: 30px 0;
    text-align: center;
}

.tpak-pagination .page-numbers {
    padding: 8px 12px;
    margin: 0 2px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
    text-decoration: none;
    color: #0073aa;
}

.tpak-pagination .page-numbers.current {
    background: #0073aa;
    color: #fff;
    border-color: #0073aa;
}

.tpak-pagination .page-numbers:hover:not(.current) {
    background: #f3f4f5;
    border-color: #999;
}
</style>

<script>
jQuery(document).ready(function($) {
    // View toggle functionality
    $('.tpak-view-toggle .button').on('click', function() {
        var view = $(this).data('view');
        
        // Update button states
        $('.tpak-view-toggle .button').removeClass('active');
        $(this).addClass('active');
        
        // Show/hide views
        $('.view-content').hide();
        $('#' + view + '-view').show();
        
        // Save preference
        localStorage.setItem('tpak_response_view', view);
    });
    
    // Load saved view preference
    var savedView = localStorage.getItem('tpak_response_view') || 'grid';
    $('.view-' + savedView).trigger('click');
});
</script>