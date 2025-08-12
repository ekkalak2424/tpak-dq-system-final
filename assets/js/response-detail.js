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
    
    // Enhanced search functionality
    $(document).on('keyup', '#question-search', function() {
        var searchTerm = $(this).val().toLowerCase();
        var visibleCount = 0;
        
        $('.question-section').each(function() {
            var section = $(this);
            var questionText = section.find('.question-title').text().toLowerCase();
            var answerText = section.find('.question-content').text().toLowerCase();
            var questionKey = section.data('question').toString().toLowerCase();
            
            var isMatch = questionText.indexOf(searchTerm) !== -1 || 
                         answerText.indexOf(searchTerm) !== -1 ||
                         questionKey.indexOf(searchTerm) !== -1;
            
            if (isMatch || searchTerm === '') {
                section.removeClass('filtered-out').show();
                visibleCount++;
            } else {
                section.addClass('filtered-out').hide();
            }
        });
        
        // Update search results count
        updateSearchResults(visibleCount, searchTerm);
    });
    
    // Category filter
    $(document).on('change', '#category-filter', function() {
        var selectedCategory = $(this).val();
        var visibleCount = 0;
        
        $('.question-section').each(function() {
            var section = $(this);
            var category = section.data('category');
            
            if (selectedCategory === '' || category === selectedCategory) {
                section.removeClass('category-filtered').show();
                visibleCount++;
            } else {
                section.addClass('category-filtered').hide();
            }
        });
        
        updateCategoryResults(visibleCount, selectedCategory);
    });
    
    // Display mode selector
    $(document).on('change', '#display-mode', function() {
        var mode = $(this).val();
        var container = $('.questions-container');
        
        // Remove all mode classes
        container.removeClass('mode-enhanced mode-grouped mode-flat mode-table');
        
        // Add selected mode class
        container.addClass('mode-' + mode);
        container.attr('data-display-mode', mode);
        
        // Apply mode-specific changes
        applyDisplayMode(mode);
    });
    
    function updateSearchResults(count, term) {
        var totalQuestions = $('.question-section').length;
        var message = '';
        
        if (term) {
            message = 'พบ ' + count + ' จาก ' + totalQuestions + ' คำถาม';
        } else {
            message = 'แสดง ' + totalQuestions + ' คำถามทั้งหมด';
        }
        
        // Update or create search results indicator
        var indicator = $('.search-results-indicator');
        if (indicator.length === 0) {
            $('.filter-stats').append('<span class="stat-item search-results-indicator"></span>');
            indicator = $('.search-results-indicator');
        }
        indicator.text(message);
    }
    
    function updateCategoryResults(count, category) {
        var categoryNames = {
            'personal': 'ข้อมูลส่วนตัว',
            'contact': 'ข้อมูลติดต่อ', 
            'education': 'การศึกษา',
            'work': 'การทำงาน',
            'survey': 'คำถามสำรวจ',
            'other': 'อื่นๆ'
        };
        
        var message = category ? 
            'แสดง ' + count + ' คำถามในหมวด ' + (categoryNames[category] || category) :
            'แสดงทุกหมวดหมู่';
            
        console.log('Category filter:', message);
    }
    
    function applyDisplayMode(mode) {
        var sections = $('.question-section');
        
        switch(mode) {
            case 'flat':
                sections.removeClass('enhanced').addClass('flat-mode');
                $('.question-content').show();
                $('.toggle-section').hide();
                break;
                
            case 'grouped':
                sections.removeClass('flat-mode').addClass('enhanced');
                groupByCategory();
                $('.toggle-section').show();
                break;
                
            case 'table':
                sections.removeClass('enhanced flat-mode').addClass('table-mode');
                convertToTable();
                break;
                
            case 'enhanced':
            default:
                sections.removeClass('flat-mode table-mode').addClass('enhanced');
                $('.toggle-section').show();
                break;
        }
    }
    
    function groupByCategory() {
        var categories = {};
        var container = $('.questions-container');
        
        // Group sections by category
        $('.question-section').each(function() {
            var section = $(this);
            var category = section.data('category') || 'other';
            
            if (!categories[category]) {
                categories[category] = [];
            }
            categories[category].push(section.detach());
        });
        
        // Create category headers and append sections
        var categoryNames = {
            'personal': 'ข้อมูลส่วนตัว',
            'contact': 'ข้อมูลติดต่อ', 
            'education': 'การศึกษา',
            'work': 'การทำงาน',
            'survey': 'คำถามสำรวจ',
            'other': 'อื่นๆ'
        };
        
        Object.keys(categories).forEach(function(category) {
            if (categories[category].length > 0) {
                var categoryHeader = $('<div class="category-header"><h3>' + 
                    (categoryNames[category] || category) + 
                    ' (' + categories[category].length + ' คำถาม)</h3></div>');
                
                container.append(categoryHeader);
                categories[category].forEach(function(section) {
                    container.append(section);
                });
            }
        });
    }
    
    function convertToTable() {
        // This would convert the display to a table format
        // Implementation depends on specific requirements
        console.log('Table mode activated');
    }
    
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