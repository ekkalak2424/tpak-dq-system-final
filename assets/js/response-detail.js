/**
 * TPAK DQ System - Response Detail JavaScript
 */

jQuery(document).ready(function($) {
    console.log('Response Detail Script loaded');
    
    // Ensure AJAX variables are available
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '/wp-admin/admin-ajax.php';
    }
    
    if (typeof window.tpak_dq_ajax === 'undefined') {
        window.tpak_dq_ajax = {
            ajax_url: window.ajaxurl,
            nonce: ''
        };
    }
    
    console.log('AJAX URL:', window.ajaxurl);
    console.log('TPAK DQ AJAX:', window.tpak_dq_ajax);
    
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
    
    // Workflow action buttons (support multiple class names)
    $(document).on('click', '.workflow-action-btn, .tpak-action-btn', function(e) {
        e.preventDefault();
        console.log('Workflow action button clicked');
        
        var button = $(this);
        var postId = button.data('id');
        var action = button.data('action');
        
        console.log('Action:', action, 'Post ID:', postId);
        console.log('Button classes:', button.attr('class'));
        
        if (!action) {
            console.error('No action data found on button');
            alert('ไม่พบข้อมูล action บนปุ่ม');
            return;
        }
        
        if (!postId) {
            console.error('No post ID data found on button');
            alert('ไม่พบข้อมูล post ID บนปุ่ม');
            return;
        }
        
        // Show comment section for reject actions
        if (action.indexOf('reject') !== -1) {
            $('.comment-section').show();
            $('#action-comment').focus();
            
            // Change button text to confirm
            var originalHtml = button.html();
            button.html('<span class="dashicons dashicons-no"></span> ยืนยันการส่งกลับ');
            
            // Remove previous click handlers and add new one
            button.off('click.reject').on('click.reject', function(e) {
                e.preventDefault();
                var comment = $('#action-comment').val().trim();
                if (!comment) {
                    alert('กรุณากรอกความคิดเห็นสำหรับการส่งกลับ');
                    return;
                }
                
                if (confirm('คุณแน่ใจหรือไม่ที่จะส่งกลับ?')) {
                    performWorkflowAction(postId, action, comment);
                }
            });
        } else {
            // For approve actions
            if (confirm('คุณแน่ใจหรือไม่ที่จะดำเนินการนี้?')) {
                performWorkflowAction(postId, action, '');
            }
        }
    });
    
    // Global function to change status (admin)
    window.changeStatusAdmin = function(postId, newStatus, comment) {
        console.log('changeStatusAdmin called with:', postId, newStatus, comment);
        
        var button = $('.admin-change-status');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> กำลังเปลี่ยน...');
        
        // Get nonce and AJAX URL with fallbacks
        var nonce = (window.tpak_dq_ajax && window.tpak_dq_ajax.nonce) ? 
                    window.tpak_dq_ajax.nonce : '';
        
        var ajaxUrl = (window.tpak_dq_ajax && window.tpak_dq_ajax.ajax_url) ? 
                      window.tpak_dq_ajax.ajax_url : 
                      (window.ajaxurl || '/wp-admin/admin-ajax.php');
        
        var requestData = {
            action: 'tpak_admin_change_status',
            post_id: postId,
            new_status: newStatus,
            comment: comment,
            nonce: nonce
        };
        
        console.log('AJAX request data:', requestData);
        console.log('AJAX URL:', ajaxUrl);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                console.log('AJAX success response:', response);
                
                if (response.success) {
                    showNotification('success', response.data.message);
                    
                    // Reload page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', response.data.message || 'Unknown error');
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                
                showNotification('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                button.prop('disabled', false).html(originalText);
            }
        });
    };
    
    // Function to perform workflow action
    function performWorkflowAction(postId, action, comment) {
        console.log('performWorkflowAction called with:', postId, action, comment);
        
        // Find button by multiple possible selectors
        var button = $('.workflow-action-btn[data-action="' + action + '"], .tpak-action-btn[data-action="' + action + '"]');
        if (button.length === 0) {
            console.error('Button not found for action:', action);
            button = $('.workflow-action-btn, .tpak-action-btn').first(); // Fallback to first button
        }
        
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> กำลังดำเนินการ...');
        
        var ajaxAction = '';
        switch(action) {
            case 'approve_a':
                ajaxAction = 'tpak_approve_batch';
                break;
            case 'approve_batch_supervisor':
                ajaxAction = 'tpak_approve_batch_supervisor';
                break;
            case 'reject_b':
            case 'reject_c':
                ajaxAction = 'tpak_reject_batch';
                break;
            case 'finalize':
                ajaxAction = 'tpak_finalize_batch';
                break;
            default:
                console.error('Unknown action:', action);
                showNotification('error', 'ไม่รู้จัก action: ' + action);
                button.prop('disabled', false).html(originalText);
                return;
        }
        
        console.log('Mapped AJAX action:', ajaxAction);
        
        // Get nonce and AJAX URL with fallbacks
        var nonce = (window.tpak_dq_ajax && window.tpak_dq_ajax.nonce) ? 
                    window.tpak_dq_ajax.nonce : '';
        
        var ajaxUrl = (window.tpak_dq_ajax && window.tpak_dq_ajax.ajax_url) ? 
                      window.tpak_dq_ajax.ajax_url : 
                      (window.ajaxurl || '/wp-admin/admin-ajax.php');
        
        var requestData = {
            action: ajaxAction,
            post_id: postId,
            comment: comment,
            nonce: nonce
        };
        
        console.log('Workflow AJAX request:', requestData);
        console.log('AJAX URL:', ajaxUrl);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                console.log('Workflow action response:', response);
                
                if (response.success) {
                    showNotification('success', response.data.message);
                    
                    // Reload page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', response.data.message || 'Unknown error');
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.log('Workflow action error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                showNotification('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    // Function to show notifications
    function showNotification(type, message) {
        var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Try to find a good place to insert the notification
        var target = $('.wrap').length ? $('.wrap') : $('body');
        target.prepend(notification);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Global showNotification function
    window.showNotification = showNotification;
    
    // Toggle question sections
    $(document).on('click', '.toggle-section, .question-header', function(e) {
        e.preventDefault();
        var section = $(this).closest('.question-section');
        section.toggleClass('collapsed');
        
        var expanded = !section.hasClass('collapsed');
        section.find('.toggle-section').attr('aria-expanded', expanded);
    });
    
    // Expand/Collapse all
    $(document).on('click', '.expand-all', function() {
        $('.question-section').removeClass('collapsed');
        $('.toggle-section').attr('aria-expanded', 'true');
    });
    
    $(document).on('click', '.collapse-all', function() {
        $('.question-section').addClass('collapsed');
        $('.toggle-section').attr('aria-expanded', 'false');
    });
    
    // Search functionality
    $(document).on('keyup', '#question-search', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.question-section').each(function() {
            var section = $(this);
            var questionText = section.find('.question-title').text().toLowerCase();
            var answerText = section.find('.question-content').text().toLowerCase();
            
            if (questionText.indexOf(searchTerm) !== -1 || answerText.indexOf(searchTerm) !== -1) {
                section.removeClass('filtered-out');
            } else {
                section.addClass('filtered-out');
            }
        });
    });
    
    // Quick navigation
    $(document).on('click', '.nav-item', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        var targetSection = $('.question-section[data-question="' + target + '"]');
        
        if (targetSection.length) {
            // Remove active class from all nav items
            $('.nav-item').removeClass('active');
            $(this).addClass('active');
            
            // Scroll to target section
            $('html, body').animate({
                scrollTop: targetSection.offset().top - 100
            }, 500);
            
            // Expand the target section if collapsed
            targetSection.removeClass('collapsed');
            targetSection.find('.toggle-section').attr('aria-expanded', 'true');
        }
    });
});