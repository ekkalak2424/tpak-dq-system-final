/**
 * LimeSurvey Hybrid System JavaScript
 * จัดการ iframe และการ sync ข้อมูล
 */

(function($) {
    'use strict';
    
    var HybridSystem = {
        
        currentSurveyId: null,
        currentResponseId: null,
        currentToken: null,
        iframeLoaded: false,
        autoSaveInterval: null,
        
        /**
         * Initialize system
         */
        init: function() {
            console.log('🚀 Hybrid System Initializing...');
            
            this.bindEvents();
            this.setupAutoSave();
            
            // Check if we have survey data in page
            if (window.tpakSurveyId) {
                this.currentSurveyId = window.tpakSurveyId;
                this.currentResponseId = window.tpakResponseId;
                console.log('📊 Survey detected:', this.currentSurveyId);
            }
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Load survey button
            $(document).on('click', '.hybrid-load-survey', function() {
                var surveyId = $(this).data('survey-id');
                var token = $(this).data('token') || '';
                self.loadSurveyIframe(surveyId, token);
            });
            
            // Fetch response button
            $(document).on('click', '.hybrid-fetch-response', function() {
                self.fetchResponseFromAPI();
            });
            
            // Save to WordPress button
            $(document).on('click', '.hybrid-save-response', function() {
                self.saveResponseToWP();
            });
            
            // Edit field
            $(document).on('click', '.hybrid-edit-field', function() {
                var fieldName = $(this).data('field');
                var currentValue = $(this).data('value');
                self.editField(fieldName, currentValue);
            });
            
            // Sync to LimeSurvey
            $(document).on('click', '.hybrid-sync-back', function() {
                var responseId = $(this).data('response-id');
                self.syncToLimeSurvey(responseId);
            });
            
            // Toggle edit mode
            $(document).on('click', '.hybrid-toggle-edit', function() {
                self.toggleEditMode();
            });
            
            // Refresh iframe
            $(document).on('click', '.hybrid-refresh-iframe', function() {
                self.refreshIframe();
            });
        },
        
        /**
         * Load survey in iframe
         */
        loadSurveyIframe: function(surveyId, token) {
            console.log('📱 Loading survey iframe:', surveyId);
            
            var self = this;
            
            // Show loading
            this.showLoading('กำลังโหลดแบบสอบถาม...');
            
            $.ajax({
                url: tpakHybrid.ajaxurl,
                type: 'POST',
                data: {
                    action: 'hybrid_load_survey',
                    survey_id: surveyId,
                    token: token,
                    nonce: tpakHybrid.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayIframe(response.data.iframe_url);
                        self.currentSurveyId = response.data.survey_id;
                        self.hideLoading();
                        
                        // Show fetch button after iframe loads
                        setTimeout(function() {
                            $('.hybrid-fetch-controls').fadeIn();
                        }, 2000);
                    } else {
                        self.showError('ไม่สามารถโหลดแบบสอบถามได้');
                    }
                },
                error: function() {
                    self.showError('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                }
            });
        },
        
        /**
         * Display iframe
         */
        displayIframe: function(url) {
            var html = '<div class="hybrid-iframe-wrapper">';
            html += '<div class="iframe-header">';
            html += '<span class="iframe-status">🟢 กำลังแสดงแบบสอบถาม</span>';
            html += '<button class="button button-small hybrid-refresh-iframe">🔄 รีเฟรช</button>';
            html += '</div>';
            html += '<iframe id="hybrid-survey-iframe" src="' + url + '" ';
            html += 'style="width: 100%; height: 800px; border: 1px solid #ddd; border-radius: 8px;">';
            html += '</iframe>';
            html += '</div>';
            
            $('#hybrid-iframe-container').html(html);
            
            // Monitor iframe load
            $('#hybrid-survey-iframe').on('load', function() {
                console.log('✅ Iframe loaded successfully');
                HybridSystem.iframeLoaded = true;
                
                // Try to detect completion
                HybridSystem.detectSurveyCompletion();
            });
        },
        
        /**
         * Detect survey completion (check iframe URL change)
         */
        detectSurveyCompletion: function() {
            try {
                var iframe = document.getElementById('hybrid-survey-iframe');
                var iframeUrl = iframe.contentWindow.location.href;
                
                // Check if URL contains completion indicators
                if (iframeUrl.includes('completed') || iframeUrl.includes('thank')) {
                    console.log('🎉 Survey completed detected!');
                    this.showCompletionMessage();
                }
            } catch (e) {
                // CORS will block this, but we try anyway
                console.log('⚠️ Cannot detect iframe URL (CORS)');
            }
        },
        
        /**
         * Fetch response from API
         */
        fetchResponseFromAPI: function() {
            console.log('📥 Fetching response from API...');
            
            var self = this;
            this.showLoading('กำลังดึงข้อมูลจาก LimeSurvey...');
            
            $.ajax({
                url: tpakHybrid.ajaxurl,
                type: 'POST',
                data: {
                    action: 'hybrid_fetch_response',
                    survey_id: this.currentSurveyId,
                    response_id: this.currentResponseId,
                    token: this.currentToken,
                    nonce: tpakHybrid.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Response fetched:', response.data);
                        self.displayResponseData(response.data);
                        self.hideLoading();
                        
                        // Auto save to WordPress
                        self.saveResponseToWP(response.data);
                    } else {
                        self.showError('ไม่สามารถดึงข้อมูลได้: ' + response.data);
                    }
                },
                error: function() {
                    self.showError('เกิดข้อผิดพลาดในการดึงข้อมูล');
                }
            });
        },
        
        /**
         * Save response to WordPress
         */
        saveResponseToWP: function(responseData) {
            console.log('💾 Saving to WordPress...');
            
            var self = this;
            
            // If no data provided, get from display
            if (!responseData) {
                responseData = this.getDisplayedData();
            }
            
            $.ajax({
                url: tpakHybrid.ajaxurl,
                type: 'POST',
                data: {
                    action: 'hybrid_save_response',
                    survey_id: this.currentSurveyId,
                    response_id: this.currentResponseId,
                    token: this.currentToken,
                    response_data: JSON.stringify(responseData),
                    nonce: tpakHybrid.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Saved to WordPress:', response.data);
                        self.showSuccess('บันทึกข้อมูลสำเร็จ! ID: ' + response.data.id);
                        
                        // Enable edit mode
                        $('.hybrid-edit-controls').fadeIn();
                    } else {
                        self.showError('ไม่สามารถบันทึกได้: ' + response.data);
                    }
                },
                error: function() {
                    self.showError('เกิดข้อผิดพลาดในการบันทึก');
                }
            });
        },
        
        /**
         * Display response data
         */
        displayResponseData: function(data) {
            var html = '<div class="hybrid-response-display">';
            html += '<h3>📊 ข้อมูลคำตอบจาก LimeSurvey</h3>';
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>ฟิลด์</th><th>ค่า</th><th>การกระทำ</th></tr></thead>';
            html += '<tbody>';
            
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    html += '<tr class="hybrid-field-row" data-field="' + key + '">';
                    html += '<td><strong>' + key + '</strong></td>';
                    html += '<td class="field-value">' + (data[key] || '-') + '</td>';
                    html += '<td>';
                    html += '<button class="button button-small hybrid-edit-field" ';
                    html += 'data-field="' + key + '" data-value="' + (data[key] || '') + '">';
                    html += '✏️ แก้ไข</button>';
                    html += '</td>';
                    html += '</tr>';
                }
            }
            
            html += '</tbody></table>';
            html += '</div>';
            
            $('#hybrid-response-container').html(html);
        },
        
        /**
         * Edit field
         */
        editField: function(fieldName, currentValue) {
            var newValue = prompt('แก้ไขค่าของ ' + fieldName + ':', currentValue);
            
            if (newValue !== null && newValue !== currentValue) {
                this.updateField(fieldName, newValue);
            }
        },
        
        /**
         * Update field
         */
        updateField: function(fieldName, newValue) {
            console.log('📝 Updating field:', fieldName, '=', newValue);
            
            var self = this;
            
            $.ajax({
                url: tpakHybrid.ajaxurl,
                type: 'POST',
                data: {
                    action: 'hybrid_update_field',
                    response_id: this.currentResponseId,
                    field_name: fieldName,
                    field_value: newValue,
                    nonce: tpakHybrid.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update display
                        $('tr[data-field="' + fieldName + '"] .field-value').text(newValue);
                        self.showSuccess('อัพเดทฟิลด์สำเร็จ');
                        
                        // Mark as unsaved
                        self.markAsUnsaved();
                    } else {
                        self.showError('ไม่สามารถอัพเดทได้');
                    }
                },
                error: function() {
                    self.showError('เกิดข้อผิดพลาด');
                }
            });
        },
        
        /**
         * Sync to LimeSurvey
         */
        syncToLimeSurvey: function(responseId) {
            console.log('🔄 Syncing to LimeSurvey...');
            
            var self = this;
            this.showLoading('กำลัง sync กลับไป LimeSurvey...');
            
            $.ajax({
                url: tpakHybrid.ajaxurl,
                type: 'POST',
                data: {
                    action: 'hybrid_sync_to_limesurvey',
                    response_id: responseId,
                    nonce: tpakHybrid.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('✅ Sync สำเร็จ!');
                        self.markAsSaved();
                    } else {
                        self.showError('Sync ล้มเหลว: ' + response.data);
                    }
                    self.hideLoading();
                },
                error: function() {
                    self.showError('เกิดข้อผิดพลาดในการ sync');
                    self.hideLoading();
                }
            });
        },
        
        /**
         * Toggle edit mode
         */
        toggleEditMode: function() {
            $('.hybrid-response-display').toggleClass('edit-mode');
            
            if ($('.hybrid-response-display').hasClass('edit-mode')) {
                this.showInfo('📝 เข้าสู่โหมดแก้ไข - คลิกที่ค่าเพื่อแก้ไข');
                this.makeFieldsEditable();
            } else {
                this.showInfo('👁️ โหมดดูอย่างเดียว');
                this.makeFieldsReadonly();
            }
        },
        
        /**
         * Make fields editable
         */
        makeFieldsEditable: function() {
            $('.field-value').each(function() {
                var value = $(this).text();
                var field = $(this).closest('tr').data('field');
                
                $(this).html('<input type="text" class="hybrid-inline-edit" ' +
                           'data-field="' + field + '" ' +
                           'value="' + value + '" />');
            });
            
            // Bind inline edit events
            $('.hybrid-inline-edit').on('change', function() {
                var field = $(this).data('field');
                var value = $(this).val();
                HybridSystem.updateField(field, value);
            });
        },
        
        /**
         * Make fields readonly
         */
        makeFieldsReadonly: function() {
            $('.hybrid-inline-edit').each(function() {
                var value = $(this).val();
                $(this).parent().text(value);
            });
        },
        
        /**
         * Setup auto save
         */
        setupAutoSave: function() {
            var self = this;
            
            // Auto save every 5 minutes
            this.autoSaveInterval = setInterval(function() {
                if ($('.hybrid-response-display.unsaved').length > 0) {
                    console.log('⏰ Auto-saving...');
                    self.saveResponseToWP();
                }
            }, 300000); // 5 minutes
        },
        
        /**
         * Get displayed data
         */
        getDisplayedData: function() {
            var data = {};
            
            $('.hybrid-field-row').each(function() {
                var field = $(this).data('field');
                var value = $(this).find('.field-value').text();
                data[field] = value;
            });
            
            return data;
        },
        
        /**
         * Mark as unsaved
         */
        markAsUnsaved: function() {
            $('.hybrid-response-display').addClass('unsaved');
            $('.hybrid-save-status').html('⚠️ มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก');
        },
        
        /**
         * Mark as saved
         */
        markAsSaved: function() {
            $('.hybrid-response-display').removeClass('unsaved');
            $('.hybrid-save-status').html('✅ บันทึกแล้ว');
        },
        
        /**
         * Show completion message
         */
        showCompletionMessage: function() {
            var html = '<div class="notice notice-success">';
            html += '<p>🎉 <strong>กรอกแบบสอบถามเสร็จสิ้น!</strong></p>';
            html += '<p>คลิกปุ่ม "ดึงข้อมูล" เพื่อนำข้อมูลมาแก้ไขใน WordPress</p>';
            html += '<button class="button button-primary hybrid-fetch-response">';
            html += '📥 ดึงข้อมูลจาก LimeSurvey</button>';
            html += '</div>';
            
            $('#hybrid-completion-message').html(html);
        },
        
        /**
         * Refresh iframe
         */
        refreshIframe: function() {
            var iframe = document.getElementById('hybrid-survey-iframe');
            if (iframe) {
                iframe.src = iframe.src;
                this.showInfo('🔄 รีเฟรช iframe แล้ว');
            }
        },
        
        /**
         * UI Helper functions
         */
        showLoading: function(message) {
            $('#hybrid-loading').html('<div class="notice notice-info">' +
                '<p>⏳ ' + message + '</p></div>').show();
        },
        
        hideLoading: function() {
            $('#hybrid-loading').hide();
        },
        
        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },
        
        showError: function(message) {
            this.showNotification(message, 'error');
        },
        
        showInfo: function(message) {
            this.showNotification(message, 'info');
        },
        
        showNotification: function(message, type) {
            var html = '<div class="notice notice-' + type + ' is-dismissible">';
            html += '<p>' + message + '</p>';
            html += '</div>';
            
            $('#hybrid-notifications').html(html);
            
            // Auto hide after 5 seconds
            setTimeout(function() {
                $('#hybrid-notifications .notice').fadeOut();
            }, 5000);
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        HybridSystem.init();
    });
    
    // Make available globally
    window.HybridSystem = HybridSystem;
    
})(jQuery);