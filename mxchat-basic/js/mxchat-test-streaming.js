jQuery(document).ready(function($) {
    // Manual test button click
    $('#mxchat-test-streaming-btn').on('click', function() {
        const $button = $(this);
        const $result = $('#mxchat-test-streaming-result');

        $button.prop('disabled', true).text('Testing...');
        $result.text('Testing streaming environment...');

        // Test both backend capability AND frontend environment
        testCompleteStreamingEnvironment()
            .then(result => {
                if (result.success) {
                    $result.css('color', 'green').html(result.message);
                } else {
                    $result.css('color', 'red').html(result.message);
                }
            })
            .catch(error => {
                $result.css('color', 'red').html('❌ Test failed: ' + error.message);
            })
            .finally(() => {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="vertical-align: middle; margin-right: 5px;"></span>Test Streaming Compatibility');
            });
    });

    // Intercept the streaming toggle BEFORE autosave fires
    // We use a capturing event handler to run first
    const streamingToggle = document.getElementById('enable_streaming_toggle');
    if (streamingToggle) {
        streamingToggle.addEventListener('change', function(e) {
            if (this.checked) {
                // Prevent the default autosave from firing immediately
                e.stopImmediatePropagation();

                const $toggle = $(this);
                const $result = $('#mxchat-test-streaming-result');
                const $button = $('#mxchat-test-streaming-btn');

                // User is enabling streaming - run automatic compatibility test first
                $button.prop('disabled', true);
                $toggle.prop('disabled', true);
                $result.css('color', '#666').html('🔄 Testing streaming compatibility before enabling...');

                testCompleteStreamingEnvironment()
                    .then(result => {
                        if (result.success) {
                            // Test passed - now save the setting
                            saveStreamingSetting('on');
                            $result.css('color', 'green').html('✅ Streaming enabled - compatibility verified!');
                        } else {
                            // Streaming failed - turn it back off (don't save 'on')
                            $toggle.prop('checked', false);
                            $result.css('color', 'red').html(result.message);
                        }
                    })
                    .catch(error => {
                        // Error during test - turn it back off
                        $toggle.prop('checked', false);
                        $result.css('color', 'red').html('❌ Streaming test failed: ' + error.message + '<br>Streaming has been disabled.');
                    })
                    .finally(() => {
                        $button.prop('disabled', false);
                        $toggle.prop('disabled', false);
                    });
            }
            // If turning OFF, let the normal autosave handle it
        }, true); // 'true' for capturing phase - runs before jQuery handlers
    }

    // Helper function to save the streaming setting via AJAX
    function saveStreamingSetting(value) {
        $.ajax({
            url: mxchatTestStreamingAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_save_setting',
                name: 'enable_streaming_toggle',
                value: value,
                _ajax_nonce: mxchatTestStreamingAjax.settings_nonce
            }
        });
    }

    function testCompleteStreamingEnvironment() {
        return new Promise((resolve, reject) => {
            let results = {
                backendSupported: false,
                frontendWorking: false,
                chunks: 0,
                timing: null,
                issues: []
            };
            
            let startTime = Date.now();
            let firstChunkTime = null;
            let lastChunkTime = null;
            let timeout;
            
            // Set overall timeout
            timeout = setTimeout(() => {
                resolve({
                    success: false,
                    message: getFailureMessage(results)
                });
            }, 15000);
            
            // Test the actual chat streaming endpoint (not the test endpoint)
            const formData = new FormData();
            formData.append('action', 'mxchat_stream_chat');
            formData.append('message', 'Say "test 1", then "test 2", then "test 3" - each on a separate line.');
            formData.append('session_id', 'streaming_test_' + Date.now());
            formData.append('nonce', mxchatTestStreamingAjax.nonce);
            formData.append('force_streaming_test', '1'); // Force streaming mode for testing
            
            fetch(mxchatTestStreamingAjax.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                console.log('Content-Type:', contentType);
                
                // Check if we got JSON (streaming failed)
                if (contentType && contentType.includes('application/json')) {
                    response.json().then(data => {
                        clearTimeout(timeout);
                        results.backendSupported = true;
                        results.frontendWorking = false;
                        results.issues.push('Server fell back to JSON response');
                        resolve({
                            success: false,
                            message: getFailureMessage(results)
                        });
                    });
                    return;
                }
                
                // Check for proper SSE content type
                if (!contentType || !contentType.includes('text/event-stream')) {
                    clearTimeout(timeout);
                    results.issues.push(`Wrong content-type: ${contentType || 'none'}`);
                    resolve({
                        success: false,
                        message: getFailureMessage(results)
                    });
                    return;
                }
                
                results.backendSupported = true;
                
                // Process streaming response
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                function processStream() {
                    reader.read().then(({ done, value }) => {
                        if (done) {
                            clearTimeout(timeout);
                            results.timing = {
                                total: Date.now() - startTime,
                                firstChunk: firstChunkTime ? firstChunkTime - startTime : null,
                                lastChunk: lastChunkTime ? lastChunkTime - startTime : null
                            };
                            
                            if (results.chunks > 0) {
                                resolve({
                                    success: true,
                                    message: getSuccessMessage(results)
                                });
                            } else {
                                results.issues.push('No chunks received');
                                resolve({
                                    success: false,
                                    message: getFailureMessage(results)
                                });
                            }
                            return;
                        }
                        
                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop() || '';
                        
                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const data = line.substring(6);
                                
                                if (data === '[DONE]') {
                                    // Stream complete - will be handled in done section
                                    continue;
                                }
                                
                                try {
                                    const json = JSON.parse(data);
                                    if (json.content && json.content.trim().length > 0) {
                                        results.chunks++;
                                        results.frontendWorking = true;
                                        
                                        if (!firstChunkTime) {
                                            firstChunkTime = Date.now();
                                        }
                                        lastChunkTime = Date.now();
                                        
                                        $result.text(`✅ Streaming working... (${results.chunks} chunks received)`);
                                    }
                                } catch (e) {
                                    // Non-JSON data is fine for some SSE implementations
                                }
                            }
                        }
                        
                        processStream();
                    }).catch(error => {
                        clearTimeout(timeout);
                        results.issues.push('Stream read error: ' + error.message);
                        resolve({
                            success: false,
                            message: getFailureMessage(results)
                        });
                    });
                }
                
                processStream();
            })
            .catch(error => {
                clearTimeout(timeout);
                results.issues.push('Fetch error: ' + error.message);
                resolve({
                    success: false,
                    message: getFailureMessage(results)
                });
            });
        });
    }
    
    function getSuccessMessage(results) {
        return `✅ <strong>Streaming is working!</strong><br>
                📊 Received ${results.chunks} chunks in ${results.timing.total}ms<br>
                ⚡ First chunk: ${results.timing.firstChunk}ms<br>
                🎯 Your users will see real-time streaming responses.`;
    }
    
    function getFailureMessage(results) {
        let message = '❌ <strong>Streaming is not working in your environment. Please disable and use regular response.</strong><br><br>';
        
        if (results.backendSupported) {
            message += '<strong>Likely causes:</strong><br>';
            message += '• Caching plugin (WP Rocket, W3 Total Cache, etc.)<br>';
            message += '• CDN buffering (Cloudflare, etc.)<br>';
            message += '• Server-level buffering (nginx, Apache)<br>';
            message += '• Hosting provider optimizations<br><br>';
            
            message += '<strong>Solutions:</strong><br>';
            message += '• Disable caching for chat endpoints<br>';
            message += '• Add streaming exceptions in your CDN<br>';
            message += '• Contact your hosting provider<br>';
        } else {
            message += '❌ Backend streaming not supported<br>';
            if (results.issues.length > 0) {
                message += '<br><strong>Issues detected:</strong><br>';
                results.issues.forEach(issue => {
                    message += `• ${issue}<br>`;
                });
            }
        }
        
        return message;
    }
});