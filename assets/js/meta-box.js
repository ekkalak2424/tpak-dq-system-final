/**
 * TPAK DQ System - Meta Box JavaScript
 */

jQuery(document).ready(function($) {
    
    // Workflow action button handlers
    $('.tpak-action-btn').on('click', function(e) {
        e.preventDefault();
        
        var action = $(this).data('action');
        var postId = $(this).data('post-id');
        var button = $(this);
        
        if (action === 'reject_b' || action === 'reject_c') {
            showRejectDialog(action, postId, button);
        } else {
            performWorkflowAction(action, postId, button);
        }
    });
    
    // Perform workflow action
    function performWorkflowAction(action, postId, button) {
        var originalText = button.text();
        
        button.text('กำลังดำเนินการ...').prop('disabled', true);
        
        $.ajax({
            url: tpak_meta_box.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_' + action + '_batch',
                post_id: postId,
                nonce: tpak_meta_box.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('การดำเนินการล้มเหลว: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('การดำเนินการล้มเหลว กรุณาลองใหม่อีกครั้ง', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    // Show reject dialog
    function showRejectDialog(action, postId, button) {
        var dialog = $('<div class="tpak-reject-dialog">' +
            '<div class="tpak-dialog-content">' +
            '<h3>' + (action === 'reject_b' ? 'ส่งกลับจาก Supervisor' : 'ส่งกลับจาก Examiner') + '</h3>' +
            '<p>กรุณากรอกเหตุผลในการส่งกลับ:</p>' +
            '<textarea id="tpak-reject-comment" rows="4" cols="50" placeholder="กรอกเหตุผล..."></textarea>' +
            '<div class="tpak-dialog-buttons">' +
            '<button class="button button-primary" id="tpak-confirm-reject">ยืนยัน</button>' +
            '<button class="button" id="tpak-cancel-reject">ยกเลิก</button>' +
            '</div>' +
            '</div>' +
            '</div>');
        
        $('body').append(dialog);
        
        // Handle confirm button
        $('#tpak-confirm-reject').on('click', function() {
            var comment = $('#tpak-reject-comment').val().trim();
            
            if (comment === '') {
                alert('กรุณากรอกเหตุผลในการส่งกลับ');
                return;
            }
            
            performRejectAction(action, postId, comment, button);
            dialog.remove();
        });
        
        // Handle cancel button
        $('#tpak-cancel-reject').on('click', function() {
            dialog.remove();
        });
        
        // Handle escape key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // ESC key
                dialog.remove();
            }
        });
    }
    
    // Perform reject action
    function performRejectAction(action, postId, comment, button) {
        var originalText = button.text();
        
        button.text('กำลังดำเนินการ...').prop('disabled', true);
        
        $.ajax({
            url: tpak_meta_box.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_reject_batch',
                post_id: postId,
                comment: comment,
                nonce: tpak_meta_box.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('การดำเนินการล้มเหลว: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('การดำเนินการล้มเหลว กรุณาลองใหม่อีกครั้ง', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    // Show notification
    function showNotification(message, type) {
        var notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wp-header-end').after(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Survey data display enhancements
    $('.tpak-survey-data').each(function() {
        var $container = $(this);
        var $dataItems = $container.find('.tpak-data-item');
        
        // Add expand/collapse functionality for long data
        $dataItems.each(function() {
            var $item = $(this);
            var $value = $item.find('.tpak-data-value');
            var text = $value.text();
            
            if (text.length > 100) {
                var shortText = text.substring(0, 100) + '...';
                var fullText = text;
                
                $value.html('<span class="tpak-short-text">' + shortText + '</span>' +
                           '<span class="tpak-full-text" style="display:none;">' + fullText + '</span>' +
                           '<button class="tpak-toggle-text button button-small">แสดงทั้งหมด</button>');
                
                $value.find('.tpak-toggle-text').on('click', function() {
                    var $shortText = $value.find('.tpak-short-text');
                    var $fullText = $value.find('.tpak-full-text');
                    var $button = $(this);
                    
                    if ($fullText.is(':visible')) {
                        $shortText.show();
                        $fullText.hide();
                        $button.text('แสดงทั้งหมด');
                    } else {
                        $shortText.hide();
                        $fullText.show();
                        $button.text('ย่อ');
                    }
                });
            }
        });
    });
    
    // Audit trail enhancements
    $('.tpak-audit-trail').each(function() {
        var $container = $(this);
        var $auditItems = $container.find('.tpak-audit-item');
        
        // Add timestamp formatting
        $auditItems.each(function() {
            var $item = $(this);
            var $time = $item.find('.audit-time');
            var timestamp = $time.text();
            
            if (timestamp) {
                var date = new Date(timestamp);
                var formattedDate = date.toLocaleDateString('th-TH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                $time.attr('title', formattedDate);
            }
        });
        
        // Add action type indicators
        $auditItems.each(function() {
            var $item = $(this);
            var $action = $item.find('.audit-action');
            var actionText = $action.text();
            
            if (actionText.includes('ยืนยัน')) {
                $action.addClass('tpak-action-approved');
            } else if (actionText.includes('ส่งกลับ')) {
                $action.addClass('tpak-action-rejected');
            } else if (actionText.includes('อนุมัติ')) {
                $action.addClass('tpak-action-finalized');
            }
        });
    });
    
    // Status indicator enhancements
    $('.tpak-status-indicator').each(function() {
        var $indicator = $(this);
        var status = $indicator.attr('class').split(' ').pop();
        
        // Add tooltip with status description
        var statusDescriptions = {
            'pending_a': 'รอการตรวจสอบจาก Interviewer',
            'pending_b': 'รอการตรวจสอบจาก Supervisor',
            'pending_c': 'รอการตรวจสอบจาก Examiner',
            'rejected_by_b': 'ส่งกลับจาก Supervisor เพื่อแก้ไข',
            'rejected_by_c': 'ส่งกลับจาก Examiner เพื่อแก้ไข',
            'finalized': 'ตรวจสอบเสร็จสมบูรณ์',
            'finalized_by_sampling': 'เสร็จสมบูรณ์โดยการสุ่ม'
        };
        
        if (statusDescriptions[status]) {
            $indicator.attr('title', statusDescriptions[status]);
        }
    });
    
    // Form validation for survey data editing
    $('.tpak-survey-data input, .tpak-survey-data textarea').on('blur', function() {
        var $field = $(this);
        var value = $field.val();
        
        // Basic validation
        if ($field.hasClass('required') && !value.trim()) {
            $field.addClass('tpak-error');
            showFieldError($field, 'กรุณากรอกข้อมูลนี้');
        } else {
            $field.removeClass('tpak-error');
            $field.siblings('.tpak-field-error').remove();
        }
    });
    
    // Show field error
    function showFieldError($field, message) {
        if (!$field.siblings('.tpak-field-error').length) {
            $field.after('<span class="tpak-field-error">' + message + '</span>');
        }
    }
    
    // Auto-save functionality for survey data
    var autoSaveTimer;
    $('.tpak-survey-data input, .tpak-survey-data textarea').on('input', function() {
        clearTimeout(autoSaveTimer);
        
        autoSaveTimer = setTimeout(function() {
            autoSaveSurveyData();
        }, 2000); // Auto-save after 2 seconds of inactivity
    });
    
    // Auto-save survey data
    function autoSaveSurveyData() {
        var $form = $('.tpak-survey-data').closest('form');
        var formData = $form.serialize();
        
        $.ajax({
            url: tpak_meta_box.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_auto_save_survey_data',
                form_data: formData,
                nonce: tpak_meta_box.nonce
            },
            success: function(response) {
                if (response.success) {
                    showAutoSaveNotification('บันทึกอัตโนมัติสำเร็จ');
                }
            }
        });
    }
    
    // Show auto-save notification
    function showAutoSaveNotification(message) {
        var notification = $('<div class="tpak-auto-save-notification">' + message + '</div>');
        
        $('body').append(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 2000);
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+S to save
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            $('#publish').click();
        }
        
        // Ctrl+Enter to approve
        if (e.ctrlKey && e.keyCode === 13) {
            e.preventDefault();
            $('.tpak-action-btn[data-action*="approve"]').first().click();
        }
    });
    
    // Confirm before leaving with unsaved changes
    var hasUnsavedChanges = false;
    
    $('.tpak-survey-data input, .tpak-survey-data textarea').on('input', function() {
        hasUnsavedChanges = true;
    });
    
    $(window).on('beforeunload', function() {
        if (hasUnsavedChanges) {
            return 'คุณมีข้อมูลที่ยังไม่ได้บันทึก ต้องการออกจากหน้านี้หรือไม่?';
        }
    });
    
    // Reset unsaved changes flag when form is submitted
    $('form').on('submit', function() {
        hasUnsavedChanges = false;
    });
}); 