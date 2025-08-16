/**
 * TPAK Clean Tab System - Conflict-free jQuery implementation
 */

// Wait for document ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== TPAK CLEAN TABS LOADED ===');
    
    // Initialize tab system
    initCleanTabs();
});

function initCleanTabs() {
    console.log('Initializing clean tab system...');
    
    var tabs = document.querySelectorAll('.nav-tab');
    var contents = document.querySelectorAll('.tab-content');
    
    console.log('Found tabs:', tabs.length);
    console.log('Found contents:', contents.length);
    
    // Debug each tab
    tabs.forEach(function(tab, index) {
        var tabId = tab.getAttribute('data-tab');
        var href = tab.getAttribute('href');
        console.log('Tab ' + index + ': data-tab="' + tabId + '" href="' + href + '"');
    });
    
    // Add click handlers to tabs
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('=== TAB CLICKED (VANILLA JS) ===');
            
            var tabId = tab.getAttribute('data-tab');
            console.log('Clicked tab ID:', tabId);
            
            if (!tabId) {
                var href = tab.getAttribute('href');
                if (href && href.indexOf('#tab-') === 0) {
                    tabId = href.replace('#tab-', '');
                    console.log('Tab ID from href:', tabId);
                }
            }
            
            if (tabId) {
                // Remove active from all tabs
                tabs.forEach(function(t) {
                    t.classList.remove('nav-tab-active');
                });
                
                // Add active to clicked tab
                tab.classList.add('nav-tab-active');
                
                // Hide all content
                contents.forEach(function(content) {
                    content.style.display = 'none';
                    content.classList.remove('active');
                });
                
                // Show target content
                var targetContent = document.getElementById('tab-' + tabId);
                if (targetContent) {
                    targetContent.style.display = 'block';
                    targetContent.classList.add('active');
                    console.log('Switched to tab:', tabId);
                } else {
                    console.log('ERROR: Target content not found for tab:', tabId);
                }
            } else {
                console.log('ERROR: No tab ID found');
            }
            
            return false;
        });
    });
    
    console.log('Clean tab system initialized');
    
    // Test click on Native tab after 2 seconds
    setTimeout(function() {
        console.log('=== Testing Native tab click ===');
        var nativeTab = document.querySelector('a[data-tab="native"]');
        if (nativeTab) {
            console.log('Native tab found, clicking...');
            nativeTab.click();
        } else {
            console.log('Native tab not found');
        }
    }, 2000);
}

// Native survey functionality (using vanilla JS)
function initNativeSurvey() {
    console.log('Initializing native survey...');
    
    var activateBtn = document.getElementById('activate-native');
    if (activateBtn) {
        activateBtn.addEventListener('click', function() {
            console.log('Activate native button clicked');
            
            activateBtn.disabled = true;
            activateBtn.textContent = '🔄 กำลังโหลด...';
            
            // Use XMLHttpRequest for AJAX without jQuery
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.ajaxurl || '/wp-admin/admin-ajax.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                var container = document.getElementById('native-survey-container');
                                if (container) {
                                    container.innerHTML = response.data.html;
                                    container.style.display = 'block';
                                }
                                
                                var statusEl = document.querySelector('.native-status');
                                if (statusEl) {
                                    statusEl.innerHTML = '✅ โหลดแล้ว';
                                }
                                
                                activateBtn.textContent = '✅ Native Mode เปิดใช้งานแล้ว';
                                activateBtn.classList.add('button-secondary');
                                activateBtn.classList.remove('button-primary');
                            } else {
                                alert('เกิดข้อผิดพลาด: ' + (response.data || 'Unknown error'));
                                activateBtn.disabled = false;
                                activateBtn.textContent = '🚀 เปิดใช้งาน Native Mode';
                            }
                        } catch (e) {
                            console.log('JSON parse error:', e);
                            alert('เกิดข้อผิดพลาดในการประมวลผล');
                            activateBtn.disabled = false;
                            activateBtn.textContent = '🚀 เปิดใช้งาน Native Mode';
                        }
                    } else {
                        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                        activateBtn.disabled = false;
                        activateBtn.textContent = '🚀 เปิดใช้งาน Native Mode';
                    }
                }
            };
            
            // Get survey data from page
            var surveyId = window.tpakSurveyId || '';
            var responseId = window.tpakResponseId || '';
            
            var data = 'action=load_native_view&survey_id=' + encodeURIComponent(surveyId) + 
                      '&response_id=' + encodeURIComponent(responseId) + 
                      '&post_id=' + encodeURIComponent(responseId) +
                      '&nonce=' + encodeURIComponent(window.tpakNonce || '');
            
            xhr.send(data);
        });
    }
}

// Initialize native survey when document is ready
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        initNativeSurvey();
    }, 1000);
});