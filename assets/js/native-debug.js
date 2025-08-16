/**
 * Debug script for Native Survey System
 */

console.log('=== NATIVE SURVEY DEBUG LOADED ===');

// Check jQuery availability
if (typeof jQuery !== 'undefined') {
    console.log('jQuery version:', jQuery.fn.jquery);
    
    jQuery(document).ready(function($) {
        console.log('=== Starting Native Survey Debug ===');
        
        // Check for survey container
        var container = $('.tpak-native-survey-container');
        console.log('Native survey container found:', container.length);
        
        if (container.length > 0) {
            var surveyId = container.data('survey-id');
            var responseId = container.data('response-id');
            console.log('Survey ID:', surveyId);
            console.log('Response ID:', responseId);
            
            // Check for buttons
            console.log('Load survey button:', $('.load-survey').length);
            console.log('Refresh button:', $('.refresh-survey').length);
            
            // Try to manually trigger load
            console.log('Attempting to trigger load survey...');
            
            // Check if ajaxurl is defined
            if (typeof ajaxurl !== 'undefined') {
                console.log('AJAX URL:', ajaxurl);
                
                // Make a test AJAX call
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'render_native_survey',
                        survey_id: surveyId || '836511',
                        response_id: responseId || '',
                        readonly: false,
                        nonce: window.tpakNonce || ''
                    },
                    success: function(response) {
                        console.log('AJAX Success:', response);
                        if (response.success) {
                            console.log('Survey data received:', response.data);
                        } else {
                            console.log('Error from server:', response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX Error:', status, error);
                        console.log('Response:', xhr.responseText);
                    }
                });
            } else {
                console.log('ERROR: ajaxurl not defined');
            }
        } else {
            console.log('No native survey container found');
        }
        
        // Monitor button clicks
        $(document).on('click', '.load-survey', function() {
            console.log('Load survey button clicked!');
        });
        
        $(document).on('click', '#activate-native', function() {
            console.log('Activate native button clicked!');
        });
    });
} else {
    console.log('ERROR: jQuery not found');
}