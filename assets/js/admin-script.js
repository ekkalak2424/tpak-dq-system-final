/**
 * TPAK DQ System - Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Ensure tpak_dq_ajax is available
    if (typeof tpak_dq_ajax === 'undefined') {
        console.warn('tpak_dq_ajax is not defined, creating fallback');
        window.tpak_dq_ajax = {
            ajax_url: (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php',
            nonce: ''
        };
    }
    
    console.log('TPAK DQ Admin Script loaded');
    console.log('tpak_dq_ajax:', tpak_dq_ajax);
    
    // Display Mode Switcher
    $(document).on('change', '#display-mode', function() {
        var mode = $(this).val();
        
        // ซ่อนทุก layout
        $('#original-layout, #enhanced-layout').hide();
        
        // แสดง layout ที่เลือก
        if (mode === 'original') {
            $('#original-layout').show();
        } else {
            $('#enhanced-layout').show();
        }
        
        console.log('Display mode changed to:', mode);
    });
    
    // Auto Structure Detection
    $(document).on('click', '#btn_auto_detect', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var surveyId = $('#auto_detect_survey_id').val();
        
        if (!surveyId) {
            alert('กรุณาระบุ Survey ID');
            return;
        }
        
        // แสดง loading
        button.prop('disabled', true).html('🔍 กำลังตรวจจับ...');
        $('#detection_results').hide();
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_auto_detect_structure',
                survey_id: surveyId,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                console.log('Auto detection response:', response);
                
                if (response.success) {
                    $('#detection_content').html(response.data.html);
                    $('#detection_results').show();
                    
                    // แสดงข้อความสำเร็จ
                    showNotification('success', 'ตรวจจับโครงสร้างสำเร็จ!');
                } else {
                    alert('ข้อผิดพลาด: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Auto detection error:', error);
                alert('เกิดข้อผิดพลาดในการตรวจจับโครงสร้าง');
            },
            complete: function() {
                // คืนสถานะปุ่ม
                button.prop('disabled', false).html('🔍 ตรวจจับโครงสร้าง');
            }
        });
    });
    
    // Workflow action handlers
    $(document).on('click', '.tpak-action-btn', function(e) {
        e.preventDefault();
        
        var action = $(this).data('action');
        var postId = $(this).data('post-id');
        var button = $(this);
        
        console.log('Workflow action clicked:', action, 'Post ID:', postId);
        
        if (action === 'reject') {
            showRejectDialog(postId, button);
        } else {
            performWorkflowAction(action, postId, button);
        }
    });
    
    // Admin status change handler
    $(document).on('click', '.admin-change-status', function(e) {
        e.preventDefault();
        
        console.log('Admin change status button clicked');
        
        var button = $(this);
        var postId = button.data('id');
        var newStatus = $('#status-select').val();
        var comment = $('#admin-comment').val();
        
        console.log('Post ID:', postId, 'New Status:', newStatus, 'Comment:', comment);
        
        if (!newStatus) {
            alert('กรุณาเลือกสถานะใหม่');
            return;
        }
        
        if (confirm('คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะ?')) {
            changeStatusAdmin(postId, newStatus, comment);
        }
    });
    
    // Manual import handler - Use AJAX for better user experience
    $('#tpak-manual-import').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        var surveyId = $('#survey_id_manual').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        // Validate survey ID
        if (!surveyId || surveyId.trim() === '') {
            showNotification('กรุณาระบุ Survey ID', 'error');
            return;
        }
        
        // Show loading state
        button.text('กำลังนำเข้า...').prop('disabled', true);
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_manual_import',
                survey_id: surveyId,
                start_date: startDate,
                end_date: endDate,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                console.log('Manual import response:', response);
                if (response.success) {
                    showNotification('นำเข้าข้อมูลสำเร็จ: ' + response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('นำเข้าข้อมูลล้มเหลว: ' + response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('Manual import error:', {xhr: xhr, status: status, error: error});
                showNotification('นำเข้าข้อมูลล้มเหลว กรุณาลองใหม่อีกครั้ง', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Test API connection
    $('#tpak-test-api').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        var resultSpan = $('#tpak-api-test-result');
        
        button.text('กำลังทดสอบ...').prop('disabled', true);
        resultSpan.html('');
        
        // Debug: Log the request data
        var requestData = {
            action: 'tpak_test_api',
            nonce: tpak_dq_ajax.nonce
        };
        console.log('API Test Request Data:', requestData);
        console.log('AJAX URL:', tpak_dq_ajax.ajax_url);
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: requestData,
            dataType: 'json',
            success: function(response) {
                console.log('API Test Response:', response);
                if (response.success) {
                    resultSpan.html('<span style="color: green; font-weight: bold;">✓ ' + response.data.message + '</span>');
                } else {
                    resultSpan.html('<span style="color: red; font-weight: bold;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.log('API Test Error Details:', {
                    xhr: xhr,
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusText: xhr.statusText
                });
                resultSpan.html('<span style="color: red; font-weight: bold;">✗ การทดสอบล้มเหลว กรุณาลองใหม่อีกครั้ง</span>');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Perform workflow action
    function performWorkflowAction(action, postId, button) {
        var originalText = button.text();
        
        button.text('Processing...').prop('disabled', true);
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_' + action + '_batch',
                post_id: postId,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    location.reload();
                } else {
                    showNotification('Action failed: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('Action failed. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    // Show reject dialog
    function showRejectDialog(postId, button) {
        var comment = prompt('Please enter a reason for rejection:');
        
        if (comment !== null && comment.trim() !== '') {
            var originalText = button.text();
            
            button.text('Processing...').prop('disabled', true);
            
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_reject_batch',
                    post_id: postId,
                    comment: comment,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        location.reload();
                    } else {
                        showNotification('Action failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Action failed. Please try again.', 'error');
                },
                complete: function() {
                    button.text(originalText).prop('disabled', false);
                }
            });
        }
    }
    
    // Show notification (local function)
    function showNotification(message, type) {
        var notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wp-header-end').after(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Status filter
    $('.tpak-status-filter').on('change', function() {
        var status = $(this).val();
        var url = new URL(window.location);
        
        if (status) {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status');
        }
        
        window.location.href = url.toString();
    });
    
    // Date range filter
    $('.tpak-date-filter').on('change', function() {
        var startDate = $('#tpak-start-date').val();
        var endDate = $('#tpak-end-date').val();
        var url = new URL(window.location);
        
        if (startDate) {
            url.searchParams.set('start_date', startDate);
        } else {
            url.searchParams.delete('start_date');
        }
        
        if (endDate) {
            url.searchParams.set('end_date', endDate);
        } else {
            url.searchParams.delete('end_date');
        }
        
        window.location.href = url.toString();
    });
    
    // Bulk actions
    $('.tpak-bulk-action').on('click', function(e) {
        e.preventDefault();
        
        var action = $(this).data('action');
        var selectedPosts = $('.tpak-post-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedPosts.length === 0) {
            showNotification('Please select at least one item.', 'error');
            return;
        }
        
        if (confirm('Are you sure you want to perform this action on ' + selectedPosts.length + ' items?')) {
            performBulkAction(action, selectedPosts);
        }
    });
    
    // Perform bulk action
    function performBulkAction(action, postIds) {
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_bulk_' + action,
                post_ids: postIds,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    location.reload();
                } else {
                    showNotification('Bulk action failed: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('Bulk action failed. Please try again.', 'error');
            }
        });
    }
    
    // Select all checkbox
    $('#tpak-select-all').on('change', function() {
        $('.tpak-post-checkbox').prop('checked', $(this).is(':checked'));
    });
    
    // Auto-refresh dashboard
    if ($('.tpak-dashboard').length > 0) {
        setInterval(function() {
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_get_dashboard_stats',
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateDashboardStats(response.data);
                    }
                }
            });
        }, 30000); // Refresh every 30 seconds
    }
    
    // Update dashboard statistics
    function updateDashboardStats(stats) {
        $('.tpak-stat-number.pending-a').text(stats.pending_a);
        $('.tpak-stat-number.pending-b').text(stats.pending_b);
        $('.tpak-stat-number.pending-c').text(stats.pending_c);
        $('.tpak-stat-number.finalized').text(stats.finalized);
    }
    
    // Export functionality
    $('.tpak-export-btn').on('click', function(e) {
        e.preventDefault();
        
        var format = $(this).data('format');
        var filters = getCurrentFilters();
        
        var url = tpak_dq_ajax.ajax_url + '?action=tpak_export_data&format=' + format + '&' + $.param(filters);
        
        window.open(url, '_blank');
    });
    
    // Get current filters
    function getCurrentFilters() {
        var filters = {};
        
        $('.tpak-status-filter').each(function() {
            var value = $(this).val();
            if (value) {
                filters[$(this).attr('name')] = value;
            }
        });
        
        $('.tpak-date-filter').each(function() {
            var value = $(this).val();
            if (value) {
                filters[$(this).attr('name')] = value;
            }
        });
        
        return filters;
    }
    
    // Initialize tooltips (if jQuery UI is available)
    if (typeof $.fn.tooltip !== 'undefined') {
        try {
            $('[data-tooltip]').tooltip({
                position: { my: 'left+5 center', at: 'right center' }
            });
        } catch (e) {
            console.log('Tooltip initialization failed:', e);
        }
    }
    
    // Initialize datepickers
    $('.tpak-date-input').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
    });
    
    // Initialize sortable tables
    $('.tpak-sortable-table').tablesorter();
    
    // Initialize search functionality
    $('.tpak-search-input').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.tpak-searchable-row').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(searchTerm) > -1);
        });
    });
    
    // Global function to change status (admin) - accessible from outside jQuery ready
    window.changeStatusAdmin = function(postId, newStatus, comment) {
        console.log('changeStatusAdmin called with:', postId, newStatus, comment);
        
        var $ = jQuery;
        var button = $('.admin-change-status');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> กำลังเปลี่ยน...');
        
        var requestData = {
            action: 'tpak_admin_change_status',
            post_id: postId,
            new_status: newStatus,
            comment: comment,
            nonce: window.tpak_dq_ajax ? window.tpak_dq_ajax.nonce : ''
        };
        
        console.log('AJAX request data:', requestData);
        
        var ajaxUrl = window.tpak_dq_ajax ? window.tpak_dq_ajax.ajax_url : (window.ajaxurl || '/wp-admin/admin-ajax.php');
        console.log('AJAX URL:', ajaxUrl);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                console.log('AJAX success response:', response);
                
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    
                    // Reload page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(response.data.message || 'Unknown error', 'error');
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                
                showNotification('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error, 'error');
                button.prop('disabled', false).html(originalText);
            }
        });
    };
    
    // Global showNotification function
    window.showNotification = function(message, type) {
        var $ = jQuery;
        var notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wp-header-end').after(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    };
});