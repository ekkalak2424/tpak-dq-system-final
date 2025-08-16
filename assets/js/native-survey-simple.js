/**
 * Simple Native Survey Handler
 */

console.log('=== NATIVE SURVEY SIMPLE LOADED ===');

// Wait for jQuery
jQuery(document).ready(function($) {
    console.log('Native Survey Ready');
    
    // Remove datepicker if exists to prevent errors
    if ($.fn.datepicker) {
        delete $.fn.datepicker;
    }
    
    // Handle Activate Native button click
    $(document).on('click', '#activate-native', function() {
        console.log('Activate Native clicked');
        
        var button = $(this);
        var originalText = button.text();
        button.prop('disabled', true).text('🔄 กำลังโหลด...');
        
        // Get IDs from page
        var surveyId = window.tpakSurveyId || '';
        var responseId = window.tpakResponseId || '';
        
        console.log('Loading Native view for Survey:', surveyId, 'Response:', responseId);
        
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
                console.log('Load Native View Response:', response);
                
                if (response.success) {
                    // Show the content
                    $('#native-survey-container').html(response.data.html).show();
                    
                    // Update status
                    $('.native-status').html('✅ โหลดแล้ว');
                    
                    // Update button
                    button.text('✅ Native Mode เปิดใช้งานแล้ว')
                          .removeClass('button-primary')
                          .addClass('button-secondary');
                    
                    console.log('Native content loaded successfully');
                    
                    // Initialize survey controls after content is loaded
                    initializeSurveyControls();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Connection error');
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Initialize survey controls when content is loaded
    function initializeSurveyControls() {
        console.log('Initializing survey controls...');
        
        // Check for load survey button
        var loadBtn = $('.load-survey');
        if (loadBtn.length > 0) {
            console.log('Found load survey button, auto-clicking...');
            
            // Auto-click load survey button
            setTimeout(function() {
                loadBtn.trigger('click');
            }, 500);
        }
        
        // Handle load survey button click
        $(document).on('click', '.load-survey', function() {
            console.log('Load survey clicked');
            
            var surveyId = $('.tpak-native-survey-container').data('survey-id');
            var responseId = $('.tpak-native-survey-container').data('response-id');
            
            console.log('Loading survey data:', surveyId, responseId);
            
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
                    console.log('Survey data response:', response);
                    
                    $('.survey-loading').hide();
                    
                    if (response.success) {
                        // Display survey data
                        displaySurveyData(response.data);
                    } else {
                        $('.native-survey-content').html(
                            '<div class="notice notice-error"><p>Error: ' + 
                            (response.data ? response.data.message : 'Unknown error') + 
                            '</p></div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Load survey error:', status, error);
                    $('.survey-loading').hide();
                    $('.native-survey-content').html(
                        '<div class="notice notice-error"><p>Connection error</p></div>'
                    );
                }
            });
        });
    }
    
    // Display survey data
    function displaySurveyData(data) {
        console.log('Displaying survey data:', data);
        
        var html = '<div class="survey-form">';
        html += '<h3>Survey Data Loaded</h3>';
        
        if (data.info) {
            html += '<div class="survey-info">';
            html += '<h4>Survey Information</h4>';
            html += '<pre>' + JSON.stringify(data.info, null, 2) + '</pre>';
            html += '</div>';
        }
        
        if (data.groups) {
            html += '<div class="survey-groups">';
            html += '<h4>Question Groups</h4>';
            html += '<pre>' + JSON.stringify(data.groups, null, 2) + '</pre>';
            html += '</div>';
        }
        
        if (data.questions) {
            html += '<div class="survey-questions">';
            html += '<h4>Questions</h4>';
            html += '<pre>' + JSON.stringify(data.questions, null, 2) + '</pre>';
            html += '</div>';
        }
        
        html += '</div>';
        
        $('.native-survey-content').html(html);
        $('.survey-actions').show();
    }
});