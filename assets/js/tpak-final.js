/**
 * TPAK Final - Complete Tab and Native Survey System
 */

console.log('=== TPAK FINAL SYSTEM LOADING ===');

// Force clean jQuery environment
(function() {
    'use strict';
    
    // Remove all jQuery UI plugins to prevent conflicts
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery !== 'undefined') {
            var $ = jQuery;
            
            // Remove problematic jQuery plugins
            if ($.fn.datepicker) {
                delete $.fn.datepicker;
            }
            if ($.datepicker) {
                delete $.datepicker;
            }
            
            console.log('TPAK: jQuery UI conflicts removed');
            
            // Initialize everything
            initTabSystem();
            initNativeSurvey();
        }
    });
    
    // Tab system
    function initTabSystem() {
        console.log('=== INITIALIZING TAB SYSTEM ===');
        
        var tabs = document.querySelectorAll('.nav-tab');
        var contents = document.querySelectorAll('.tab-content');
        
        console.log('Found tabs:', tabs.length, 'Found contents:', contents.length);
        
        // Add click handlers to tabs
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                var tabId = tab.getAttribute('data-tab');
                console.log('Tab clicked:', tabId);
                
                if (tabId) {
                    // Update tab appearance
                    tabs.forEach(function(t) {
                        t.classList.remove('nav-tab-active');
                    });
                    tab.classList.add('nav-tab-active');
                    
                    // Update content
                    contents.forEach(function(content) {
                        content.style.display = 'none';
                        content.classList.remove('active');
                    });
                    
                    var targetContent = document.getElementById('tab-' + tabId);
                    if (targetContent) {
                        targetContent.style.display = 'block';
                        targetContent.classList.add('active');
                        console.log('Content switched to:', tabId);
                        console.log('Target content height:', targetContent.offsetHeight);
                        console.log('Target content HTML length:', targetContent.innerHTML.length);
                        
                        // Force visible if it's the native tab
                        if (tabId === 'native') {
                            console.log('📍 Native tab activated - checking content...');
                            var nativeContainer = targetContent.querySelector('#native-survey-container');
                            if (nativeContainer) {
                                nativeContainer.style.display = 'block';
                                console.log('Native container made visible');
                            }
                        }
                    } else {
                        console.log('ERROR: Target content not found for tab:', tabId);
                    }
                }
            });
        });
        
        // Make sure first tab is visible
        var firstContent = document.querySelector('.tab-content.active');
        if (firstContent) {
            firstContent.style.display = 'block';
        }
        
        console.log('Tab system initialized');
    }
    
    // Native Survey System using jQuery for AJAX
    function initNativeSurvey() {
        console.log('=== INITIALIZING NATIVE SURVEY ===');
        
        if (typeof jQuery === 'undefined') {
            console.log('ERROR: jQuery not available');
            return;
        }
        
        var $ = jQuery;
        
        // Handle Activate Native button
        $(document).on('click', '#activate-native', function() {
            console.log('🚀 Activate Native clicked');
            console.log('Button element:', this);
            console.log('Button text:', $(this).text());
            
            var button = $(this);
            var originalText = button.text();
            button.prop('disabled', true).text('🔄 กำลังโหลด...');
            
            var surveyId = window.tpakSurveyId || '';
            var responseId = window.tpakResponseId || '';
            
            console.log('Loading for Survey:', surveyId, 'Response:', responseId);
            
            // Force show the container first
            $('#native-survey-container').html('<div style="padding: 20px; text-align: center;">กำลังโหลด Native Survey...</div>').show();
            
            $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                data: {
                    action: 'load_native_view',
                    survey_id: surveyId,
                    response_id: responseId,
                    post_id: responseId,
                    nonce: window.tpakNonce
                },
                success: function(response) {
                    console.log('✅ Native view loaded:', response);
                    
                    if (response.success) {
                        $('#native-survey-container').html(response.data.html);
                        $('.native-status').html('✅ โหลดแล้ว');
                        button.text('✅ Native Mode เปิดใช้งานแล้ว')
                              .removeClass('button-primary')
                              .addClass('button-secondary');
                        
                        // Auto-load survey data
                        setTimeout(function() {
                            autoLoadSurveyData();
                        }, 1000);
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX Error:', status, error);
                    console.log('Response:', xhr.responseText);
                    alert('Connection error');
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Auto-load survey data
        function autoLoadSurveyData() {
            console.log('🔄 Auto-loading survey data...');
            
            var loadBtn = $('.load-survey');
            if (loadBtn.length > 0) {
                console.log('Found load button, clicking...');
                loadBtn.trigger('click');
            } else {
                // Manual load if button not found
                var container = $('.tpak-native-survey-container');
                if (container.length > 0) {
                    var surveyId = container.data('survey-id');
                    var responseId = container.data('response-id');
                    
                    loadSurveyData(surveyId, responseId);
                }
            }
        }
        
        // Load survey data function
        function loadSurveyData(surveyId, responseId) {
            console.log('📥 Loading survey data:', surveyId, responseId);
            
            $('.survey-loading').show();
            
            $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                data: {
                    action: 'render_native_survey',
                    survey_id: surveyId,
                    response_id: responseId,
                    readonly: false,
                    nonce: window.tpakSurveyNonce || window.tpakNonce
                },
                success: function(response) {
                    console.log('📊 Survey data response:', response);
                    
                    $('.survey-loading').hide();
                    
                    if (response.success) {
                        displaySurveyData(response.data);
                    } else {
                        var errorMsg = 'Error: ' + (response.data ? response.data.message : 'Unknown error');
                        $('.native-survey-content').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Survey load error:', status, error);
                    $('.survey-loading').hide();
                    $('.native-survey-content').html('<div class="notice notice-error"><p>Connection error</p></div>');
                }
            });
        }
        
        // Display survey data
        function displaySurveyData(data) {
            console.log('📋 Displaying survey data:', data);
            
            var html = '<div class="survey-form-wrapper">';
            html += '<h3>✅ แบบสอบถาม Native 100% โหลดสำเร็จ</h3>';
            
            if (data && Object.keys(data).length > 0) {
                html += '<div class="survey-data-display">';
                
                if (data.info) {
                    html += '<h4>ข้อมูลแบบสอบถาม</h4>';
                    html += '<div class="info-box">' + JSON.stringify(data.info, null, 2) + '</div>';
                }
                
                if (data.groups) {
                    html += '<h4>กลุ่มคำถาม (' + Object.keys(data.groups).length + ' กลุ่ม)</h4>';
                    html += '<div class="groups-box">' + JSON.stringify(data.groups, null, 2) + '</div>';
                }
                
                if (data.questions) {
                    html += '<h4>คำถาม (' + Object.keys(data.questions).length + ' คำถาม)</h4>';
                    html += '<div class="questions-box">' + JSON.stringify(data.questions, null, 2) + '</div>';
                }
                
                html += '</div>';
            } else {
                html += '<div class="notice notice-warning"><p>ไม่พบข้อมูลแบบสอบถาม</p></div>';
            }
            
            html += '</div>';
            
            $('.native-survey-content').html(html);
            $('.survey-actions').show();
        }
        
        // Handle load survey button clicks
        $(document).on('click', '.load-survey', function() {
            var container = $('.tpak-native-survey-container');
            var surveyId = container.data('survey-id');
            var responseId = container.data('response-id');
            
            loadSurveyData(surveyId, responseId);
        });
        
        console.log('Native survey system initialized');
        
        // Test if buttons are visible
        setTimeout(function() {
            var activateBtn = $('#activate-native');
            console.log('🔍 Checking activate button:', activateBtn.length);
            if (activateBtn.length > 0) {
                console.log('Button visible:', activateBtn.is(':visible'));
                console.log('Button parent visible:', activateBtn.parent().is(':visible'));
            }
            
            var nativeContainer = $('#native-survey-container');
            console.log('🔍 Checking native container:', nativeContainer.length);
            if (nativeContainer.length > 0) {
                console.log('Container visible:', nativeContainer.is(':visible'));
                console.log('Container HTML:', nativeContainer.html().substring(0, 200));
            }
        }, 2000);
    }
    
})();

console.log('=== TPAK FINAL SYSTEM READY ===');