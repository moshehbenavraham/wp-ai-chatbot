jQuery(document).ready(function($) {
    // ========================================
    // UNIQUE URL GENERATOR FOR DIRECT CONTENT
    // ========================================

    // Show/hide generate button based on checkbox
    $('#mxchat-unique-url-toggle').on('change', function() {
        const $button = $('#mxchat-generate-unique-url');
        const $urlInput = $('#article_url');

        if ($(this).is(':checked')) {
            $button.show();
            // If URL field is empty and checkbox is enabled, generate immediately
            if (!$urlInput.val()) {
                generateUniqueUrl();
            }
        } else {
            $button.hide();
        }
    });

    // Generate unique URL when button is clicked
    $('#mxchat-generate-unique-url').on('click', function() {
        generateUniqueUrl();
    });

    // Function to generate unique URL
    function generateUniqueUrl() {
        const $urlInput = $('#article_url');
        let baseUrl = $urlInput.val().trim();

        // If no URL provided, use a default
        if (!baseUrl) {
            baseUrl = window.location.origin;
        }

        // Split URL into base and hash fragment
        let hashFragment = '';
        if (baseUrl.includes('#')) {
            const parts = baseUrl.split('#');
            baseUrl = parts[0];
            hashFragment = '#' + parts[1];
        }

        // Remove any existing ref parameter (matches ref=anything up to & or end of string)
        baseUrl = baseUrl.replace(/[?&]ref=[^&]+(&|$)/, function(match, ending) {
            // If it ends with &, keep it; otherwise remove the whole thing
            return ending === '&' ? '&' : '';
        });

        // Clean up any trailing ? or & from URL
        baseUrl = baseUrl.replace(/[?&]$/, '');

        // Generate unique reference (timestamp only for cleaner URLs)
        const timestamp = Date.now();
        const uniqueRef = timestamp;

        // Add the unique reference as a query parameter (before hash)
        const separator = baseUrl.includes('?') ? '&' : '?';
        const uniqueUrl = baseUrl + separator + 'ref=' + uniqueRef + hashFragment;

        // Update the input field
        $urlInput.val(uniqueUrl);

        // Visual feedback
        $urlInput.css('background-color', '#e7f7e7');
        setTimeout(function() {
            $urlInput.css('background-color', '');
        }, 1000);
    }

    // ========================================
    // QUEUE PROCESSING SYSTEM
    // ========================================

    let isProcessingQueue = false;
    let currentQueueId = null;
    let currentQueueType = null;

    // Process 5 items at a time
    const BATCH_SIZE = 5;

    // Check if we should start queue processing on page load
    checkForActiveQueues();
    
    // Form submission handler - triggers queue processing
    $('#mxchat-url-form').on('submit', function(e) {
        //console.log('MxChat: Form submitted, queue will be created');

        // Don't prevent default - let form submit normally
        // But schedule a check after redirect
        localStorage.setItem('mxchat_check_queue_after_submit', Date.now().toString());
    });

    // Check if we just submitted a form and need to start processing
    const justSubmitted = localStorage.getItem('mxchat_check_queue_after_submit');
    if (justSubmitted) {
        const submitTime = parseInt(justSubmitted);
        const now = Date.now();
        
        // If submitted within last 10 seconds, wait for queue to be created
        if (now - submitTime < 10000) {
            //console.log('MxChat: Form was just submitted, waiting for queue creation...');
            localStorage.removeItem('mxchat_check_queue_after_submit');
            
            // Show processing message
            if ($('.mxchat-processing-message').length === 0) {
                const message = $('<div class="mxchat-processing-message" style="text-align: center; padding: 15px; background: #f0f7ff; border-radius: 8px; margin-top: 15px;">⏳ Queue created! Processing will start in a moment...</div>');
                $('.mxchat-import-section').after(message);
            }
            
            // Check for queue multiple times with increasing delays
            setTimeout(function() { checkForActiveQueues(); }, 1000);
            setTimeout(function() { checkForActiveQueues(); }, 2000);
            setTimeout(function() { checkForActiveQueues(); }, 3000);
            setTimeout(function() { checkForActiveQueues(); }, 5000);
        }
    }
    
function checkForActiveQueues() {
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'mxchat_get_status_updates',
            nonce: mxchatAdmin.status_nonce
        },
        success: function(response) {
            //console.log('MxChat: Checking for active queues...', response);
            
            if (response.sitemap_queue_id && response.sitemap_status) {
                if (response.sitemap_status.status === 'processing') {
                    //console.log('MxChat: Found active sitemap queue:', response.sitemap_queue_id);
                    startQueueProcessing(response.sitemap_queue_id, 'sitemap');
                } else if (response.sitemap_status.status === 'complete') {
                    //console.log('MxChat: Sitemap queue already complete');
                    // ADD THIS LINE:
                    $('.mxchat-processing-message').remove();
                    // Show completed status card (no auto-refresh)
                    updateSitemapStatus(response.sitemap_status);
                }
            }
            
            if (response.pdf_queue_id && response.pdf_status) {
                if (response.pdf_status.status === 'processing') {
                    //console.log('MxChat: Found active PDF queue:', response.pdf_queue_id);
                    startQueueProcessing(response.pdf_queue_id, 'pdf');
                } else if (response.pdf_status.status === 'complete') {
                    //console.log('MxChat: PDF queue already complete');
                    // ADD THIS LINE:
                    $('.mxchat-processing-message').remove();
                    // Show completed status card (no auto-refresh)
                    updatePdfStatus(response.pdf_status);
                }
            }
            
            // ADD THIS: If no queues found at all, remove the message
            if (!response.sitemap_queue_id && !response.pdf_queue_id) {
                $('.mxchat-processing-message').remove();
            }
        },
        error: function(xhr, status, error) {
            console.error('MxChat: Error checking for active queues:', error);
            // ADD THIS: Remove message on error too
            $('.mxchat-processing-message').remove();
        }
    });
}
    
    /**
     * Start processing a queue
     */
    function startQueueProcessing(queueId, queueType) {
        if (isProcessingQueue) {
            //console.log('MxChat: Already processing a queue, skipping');
            return;
        }

        isProcessingQueue = true;
        currentQueueId = queueId;
        currentQueueType = queueType;

        //console.log('MxChat: Starting queue processing:', queueId, queueType);

        // Remove any "waiting" messages
        $('.mxchat-processing-message').remove();

        // Create or update status card
        createOrUpdateStatusCard(queueType);

        // Start real-time entries polling if function exists
        if (typeof startEntriesPolling === 'function') {
            startEntriesPolling();
        }

        // Start the batch processing loop
        processNextBatch();
    }

    /**
     * Process the next batch of items (5 at a time)
     */
    function processNextBatch() {
        if (!isProcessingQueue) {
            //console.log('MxChat: Processing stopped');
            return;
        }

        // Fetch the next batch of items
        const fetchPromises = [];

        for (let i = 0; i < BATCH_SIZE; i++) {
            const promise = $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mxchat_get_next_queue_item',
                    nonce: mxchatAdmin.queue_nonce,
                    queue_id: currentQueueId
                }
            });
            fetchPromises.push(promise);
        }

        // Wait for all fetch requests to complete
        Promise.all(fetchPromises).then(function(responses) {
            // Filter out completed/error responses and extract items
            const items = [];
            let queueComplete = false;

            for (let response of responses) {
                if (response.success && response.data && !response.data.complete) {
                    items.push(response.data.item);
                } else if (response.data && response.data.complete) {
                    queueComplete = true;
                }
            }

            // If no items to process, queue is done
            if (items.length === 0) {
                if (queueComplete) {
                    handleQueueComplete();
                } else {
                    verifyQueueCompletion();
                }
                return;
            }

            // Process all items in this batch simultaneously
            const processPromises = items.map(item => processQueueItem(item));

            // Wait for all items to finish processing
            Promise.all(processPromises).then(function() {
                // Update progress after batch completes
                updateQueueProgress();

                // If we got fewer items than batch size, queue might be done
                if (items.length < BATCH_SIZE || queueComplete) {
                    verifyQueueCompletion();
                } else {
                    // Process next batch immediately
                    processNextBatch();
                }
            }).catch(function(error) {
                console.error('MxChat: Error processing batch:', error);
                // Continue anyway
                updateQueueProgress();
                setTimeout(function() {
                    processNextBatch();
                }, 1000);
            });

        }).catch(function(error) {
            console.error('MxChat: Error fetching batch:', error);
            // Verify queue status before retrying
            setTimeout(function() {
                verifyQueueCompletion();
            }, 2000);
        });
    }
    
    /**
     *   Verify if queue is actually complete
     * Prevents infinite loops when last item fails
     */
    function verifyQueueCompletion() {
        //console.log('MxChat: Verifying queue completion status...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_queue_status',
                nonce: mxchatAdmin.queue_nonce,
                queue_id: currentQueueId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data;
                    
                    // If no pending or processing items, queue is done
                    if (status.pending === 0 && status.processing === 0) {
                        //console.log('MxChat: Queue verified as complete');
                        handleQueueComplete();
                    } else {
                        // Still has items, try to continue
                        //console.log('MxChat: Queue still has pending items, continuing...');
                        processNextBatch();
                    }
                } else {
                    // Can't verify, assume complete to prevent infinite loop
                    //console.log('MxChat: Could not verify queue status, assuming complete');
                    handleQueueComplete();
                }
            },
            error: function() {
                // Can't verify, assume complete to prevent infinite loop
                //console.log('MxChat: Network error verifying queue, assuming complete');
                handleQueueComplete();
            }
        });
    }
    
    /**
     * Process a single queue item
     * Returns a Promise that resolves when processing is complete
     */
    function processQueueItem(item) {
        return $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_process_queue_item',
                nonce: mxchatAdmin.queue_nonce,
                item_id: item.id,
                item_type: item.type,
                item_data: item.data,
                bot_id: item.bot_id
            }
        }).then(function(response) {
            if (response.success) {
                // Item processed successfully
                //console.log('MxChat: Item processed successfully:', item.id);
                return true;
            } else {
                // Item failed but we KEEP GOING
                console.warn('MxChat: Item processing failed (will continue):', item.type, item.id);
                console.warn('MxChat: Error details:', response.data);
                return false;
            }
        }).catch(function(xhr, status, error) {
            // Network error - log it but KEEP GOING
            console.error('MxChat: AJAX/Network error processing item:', item.id, error);
            return false;
        });
    }
    
    /**
     * Update queue progress (fetches latest stats)
     */
    function updateQueueProgress() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_queue_status',
                nonce: mxchatAdmin.queue_nonce,
                queue_id: currentQueueId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data;
                    
                    // Update the appropriate status card
                    if (currentQueueType === 'pdf') {
                        updatePdfStatusFromQueue(status);
                    } else {
                        updateSitemapStatusFromQueue(status);
                    }
                }
            }
        });
    }
    
    /**
     * Handle queue completion
     *  NO AUTO-REFRESH - Show completed card with errors until dismissed
     */
    function handleQueueComplete() {
        isProcessingQueue = false;

        // Stop real-time entries polling if function exists
        if (typeof stopEntriesPolling === 'function') {
            stopEntriesPolling();
        }

        // Refresh the knowledge base table with properly grouped entries
        refreshKnowledgeBaseTable();

        // Get final status with error details
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_queue_status',
                nonce: mxchatAdmin.queue_nonce,
                queue_id: currentQueueId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data;
                    
                    // Show completed status card (NO REFRESH)
                    if (currentQueueType === 'pdf') {
                        showCompletedPdfCard(status);
                    } else {
                        showCompletedSitemapCard(status);
                    }
                    
                    // Mark the queue as complete on server
                    markQueueAsComplete(currentQueueId);
                    
                    // Show notification based on results
                    if (status.failed > 0) {
                        showNotification('warning', 
                            `Processing completed: ${status.completed} succeeded, ${status.failed} failed. ` +
                            `Review errors below and dismiss when ready.`
                        );
                    } else {
                        showNotification('success', 
                            `Processing completed successfully! All ${status.completed} items processed. ` +
                            `Dismiss the status card when ready.`
                        );
                    }
                    
                    // NO AUTO-REFRESH - User must manually dismiss
                } else {
                    // Couldn't get final status, just show generic completion
                    showNotification('success', 'Processing completed! Refresh page to see final results.');
                }
            },
            error: function() {
                // Error getting final status
                showNotification('success', 'Processing completed! Refresh page to see final results.');
            }
        });
    }
    
    /**
     *   Show completed PDF card with full error details (NO RETRY BUTTON)
     */
    function showCompletedPdfCard(status) {
        let $card = $('.mxchat-status-card:contains("PDF Processing")');
        
        if ($card.length === 0) {
            return;
        }
        
        // Remove processing UI elements
        $card.find('.mxchat-stop-form').remove();
        $card.find('.mxchat-status-warning').remove();
        
        // Update header with completion badge
        $card.find('.mxchat-status-badge').remove();
        if (status.failed > 0) {
            $card.find('.mxchat-status-header h4').after(
                '<span class="mxchat-status-badge mxchat-status-warning" style="margin-left: 10px; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: normal;">⚠️ Completed with ' + 
                status.failed + ' failures - Refresh to view entries</span>'
            );
        } else {
            $card.find('.mxchat-status-header h4').after(
                '<span class="mxchat-status-badge mxchat-status-success" style="margin-left: 10px; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: normal;">✓ Complete - Refresh to view entries</span>'
            );
        }
                
        // Add dismiss button
        addDismissButton($card);
        
        // Update progress bar to 100%
        $card.find('.mxchat-progress-fill').css('width', '100%');
        
        // Update details with final stats
        let detailsHtml = '<div style="background: #f0f7ff; padding: 15px; border-radius: 4px; margin-bottom: 15px;">';
        detailsHtml += '<h4 style="margin: 0 0 10px 0;">📊 Final Results</h4>';
        detailsHtml += '<p style="margin: 5px 0;"><strong>Total Pages:</strong> ' + status.total + '</p>';
        detailsHtml += '<p style="margin: 5px 0; color: #2ea44f;"><strong>✓ Successfully Processed:</strong> ' + status.completed + '</p>';
        
        if (status.failed > 0) {
            detailsHtml += '<p style="margin: 5px 0; color: #cf222e;"><strong>✗ Failed:</strong> ' + status.failed + '</p>';
        }
        
        detailsHtml += '</div>';
        
        // Add error details if there are failures (NO RETRY BUTTON)
        if (status.failed > 0 && status.failed_items && status.failed_items.length > 0) {
            detailsHtml += '<div class="mxchat-error-summary" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 15px;">';
            detailsHtml += '<h4 style="margin: 0 0 10px 0; color: #856404;">⚠️ Failed Pages</h4>';
            
            detailsHtml += '<details style="cursor: pointer;">';
            detailsHtml += '<summary style="font-weight: bold; color: #856404; padding: 5px 0;">Click to view ' + status.failed_items.length + ' failed pages</summary>';
            
            detailsHtml += '<div style="margin-top: 10px; max-height: 400px; overflow-y: auto;">';
            detailsHtml += '<table style="width: 100%; border-collapse: collapse;">';
            detailsHtml += '<thead><tr style="background: #f5f5f5;"><th style="padding: 8px; text-align: left;">Page</th><th style="padding: 8px; text-align: left;">Error</th><th style="padding: 8px; text-align: left;">Attempts</th></tr></thead>';
            detailsHtml += '<tbody>';
            
            status.failed_items.forEach(function(item) {
                const data = JSON.parse(item.item_data);
                const pageNum = data.page_number || 'Unknown';
                
                detailsHtml += '<tr style="border-bottom: 1px solid #ddd;">';
                detailsHtml += '<td style="padding: 8px;">Page ' + pageNum + '</td>';
                detailsHtml += '<td style="padding: 8px; word-break: break-word;">' + (item.error_message || 'Unknown error') + '</td>';
                detailsHtml += '<td style="padding: 8px;">' + item.attempts + '</td>';
                detailsHtml += '</tr>';
            });
            
            detailsHtml += '</tbody></table>';
            detailsHtml += '</div>';
            detailsHtml += '</details>';
            
            detailsHtml += '</div>';
        }
        
        $card.find('.mxchat-status-details').html(detailsHtml);
    }
    
    /**
     *   Show completed sitemap card with full error details (NO RETRY BUTTON)
     */
    function showCompletedSitemapCard(status) {
        let $card = $('.mxchat-status-card:contains("Sitemap Processing")');
        
        if ($card.length === 0) {
            return;
        }
        
        // Remove processing UI elements
        $card.find('.mxchat-stop-form').remove();
        $card.find('.mxchat-status-warning').remove();
        
        // Update header with completion badge
        $card.find('.mxchat-status-badge').remove();
        if (status.failed > 0) {
            $card.find('.mxchat-status-header h4').after(
                '<span class="mxchat-status-badge mxchat-status-warning" style="margin-left: 10px; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: normal;">⚠️ Completed with ' + 
                status.failed + ' failures - Refresh to view entries</span>'
            );
        } else {
            $card.find('.mxchat-status-header h4').after(
                '<span class="mxchat-status-badge mxchat-status-success" style="margin-left: 10px; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: normal;">✓ Complete - Refresh to view entries</span>'
            );
        }
        
        // Add dismiss button
        addDismissButton($card);
        
        // Update progress bar to 100%
        $card.find('.mxchat-progress-fill').css('width', '100%');
        
        // Update details with final stats
        let detailsHtml = '<div style="background: #f0f7ff; padding: 15px; border-radius: 4px; margin-bottom: 15px;">';
        detailsHtml += '<h4 style="margin: 0 0 10px 0;">📊 Final Results</h4>';
        detailsHtml += '<p style="margin: 5px 0;"><strong>Total URLs:</strong> ' + status.total + '</p>';
        detailsHtml += '<p style="margin: 5px 0; color: #2ea44f;"><strong>✓ Successfully Processed:</strong> ' + status.completed + '</p>';
        
        if (status.failed > 0) {
            detailsHtml += '<p style="margin: 5px 0; color: #cf222e;"><strong>✗ Failed:</strong> ' + status.failed + '</p>';
        }
        
        detailsHtml += '</div>';
        
        // Add error details if there are failures (NO RETRY BUTTON)
        if (status.failed > 0 && status.failed_items && status.failed_items.length > 0) {
            detailsHtml += '<div class="mxchat-error-summary" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 15px;">';
            detailsHtml += '<h4 style="margin: 0 0 10px 0; color: #856404;">⚠️ Failed URLs</h4>';
            
            detailsHtml += '<details style="cursor: pointer;">';
            detailsHtml += '<summary style="font-weight: bold; color: #856404; padding: 5px 0;">Click to view ' + status.failed_items.length + ' failed URLs</summary>';
            
            detailsHtml += '<div style="margin-top: 10px; max-height: 400px; overflow-y: auto;">';
            detailsHtml += '<table style="width: 100%; border-collapse: collapse;">';
            detailsHtml += '<thead><tr style="background: #f5f5f5;"><th style="padding: 8px; text-align: left;">URL</th><th style="padding: 8px; text-align: left;">Error</th><th style="padding: 8px; text-align: left;">Attempts</th></tr></thead>';
            detailsHtml += '<tbody>';
            
            status.failed_items.forEach(function(item) {
                const data = JSON.parse(item.item_data);
                const url = data.url || 'Unknown URL';
                const displayUrl = url.length > 60 ? url.substring(0, 57) + '...' : url;
                
                detailsHtml += '<tr style="border-bottom: 1px solid #ddd;">';
                detailsHtml += '<td style="padding: 8px;"><a href="' + url + '" target="_blank" style="color: #0073aa; text-decoration: none;">' + displayUrl + '</a></td>';
                detailsHtml += '<td style="padding: 8px; word-break: break-word;">' + (item.error_message || 'Unknown error') + '</td>';
                detailsHtml += '<td style="padding: 8px;">' + item.attempts + '</td>';
                detailsHtml += '</tr>';
            });
            
            detailsHtml += '</tbody></table>';
            detailsHtml += '</div>';
            detailsHtml += '</details>';
            
            detailsHtml += '</div>';
        }
        
        $card.find('.mxchat-status-details').html(detailsHtml);
    }
    
    /**
     *   Mark queue as complete on server side
     * This prevents it from auto-starting on page refresh
     */
    function markQueueAsComplete(queueId) {
        // This is a fire-and-forget call to update queue status
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_mark_queue_complete',
                nonce: mxchatAdmin.queue_nonce,
                queue_id: queueId
            },
            success: function(response) {
                //console.log('MxChat: Queue marked as complete on server');
            },
            error: function() {
                //console.log('MxChat: Could not mark queue as complete, but continuing');
            }
        });
    }
    
    /**
     * Stop processing button handler
     */
    $(document).on('submit', '.mxchat-stop-form', function() {
        //console.log('MxChat: Stop processing requested');
        isProcessingQueue = false;
        currentQueueId = null;
        currentQueueType = null;
    });
    
    /**
     * Create or update status card
     */
    function createOrUpdateStatusCard(queueType) {
        const cardTitle = queueType === 'pdf' ? 'PDF Processing Status' : 'Sitemap Processing Status';
        let $card = $('.mxchat-status-card:contains("' + cardTitle + '")');
        
        if ($card.length === 0) {
            // Create new card
            let html = '<div class="mxchat-status-card">';
            html += '<div class="mxchat-status-header">';
            html += '<h4>' + cardTitle + '</h4>';
            html += '<div class="mxchat-status-warning" style="background: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin: 10px 0;">';
            html += '⚠️ <strong>Keep this tab open</strong> - Processing runs in your browser';
            html += '</div>';
            html += '<form method="post" class="mxchat-stop-form" action="' + 
                   mxchatAdmin.admin_url + 'admin-post.php?action=mxchat_stop_processing">';
            html += '<input type="hidden" name="mxchat_stop_processing_nonce" value="' + 
                   mxchatAdmin.stop_nonce + '">';
            html += '<button type="submit" name="stop_processing" class="mxchat-button-secondary">';
            html += 'Stop Processing</button></form>';
            html += '</div>';
            html += '<div class="mxchat-progress-bar">';
            html += '<div class="mxchat-progress-fill" style="width: 0%"></div>';
            html += '</div>';
            html += '<div class="mxchat-status-details">';
            html += '<p>Initializing...</p>';
            html += '</div>';
            html += '</div>';
            
            // Insert card
            let $importSection = $('.mxchat-import-section');
            if ($importSection.length > 0) {
                $importSection.after($(html));
            }
        }
    }
    
    /**
     * Update PDF status from queue data (DURING PROCESSING)
     */
    function updatePdfStatusFromQueue(status) {
        let $card = $('.mxchat-status-card:contains("PDF Processing")');
        
        if ($card.length === 0) {
            return;
        }
        
        // Update progress bar
        $card.find('.mxchat-progress-fill').css('width', status.percentage + '%');
        
        // Update details
        let detailsHtml = '<p>Progress: ' + (status.completed + status.failed) + ' of ' + 
                         status.total + ' pages (' + status.percentage + '%)</p>';
        
        if (status.completed > 0) {
            detailsHtml += '<p><strong>✓ Processed successfully:</strong> ' + status.completed + '</p>';
        }
        
        if (status.failed > 0) {
            detailsHtml += '<p class="error-count"><strong>✗ Failed pages:</strong> ' + status.failed + '</p>';
        }
        
        detailsHtml += '<p><strong>Status:</strong> Processing</p>';
        
        $card.find('.mxchat-status-details').html(detailsHtml);
    }
    
    /**
     * Update sitemap status from queue data (DURING PROCESSING)
     */
    function updateSitemapStatusFromQueue(status) {
        let $card = $('.mxchat-status-card:contains("Sitemap Processing")');
        
        if ($card.length === 0) {
            return;
        }
        
        // Update progress bar
        $card.find('.mxchat-progress-fill').css('width', status.percentage + '%');
        
        // Update details
        let detailsHtml = '<p>Progress: ' + (status.completed + status.failed) + ' of ' + 
                         status.total + ' URLs (' + status.percentage + '%)</p>';
        
        if (status.completed > 0) {
            detailsHtml += '<p><strong>✓ Processed successfully:</strong> ' + status.completed + '</p>';
        }
        
        if (status.failed > 0) {
            detailsHtml += '<p class="error-count"><strong>✗ Failed URLs:</strong> ' + status.failed + '</p>';
        }
        
        detailsHtml += '<p><strong>Status:</strong> Processing</p>';
        
        $card.find('.mxchat-status-details').html(detailsHtml);
    }
    
    // ========================================
    // STATUS UPDATES FOR COMPLETED QUEUES (FROM SERVER)
    // ========================================
    
    /**
     * Dismiss completed status button handler
     */
    $(document).on('click', '.mxchat-dismiss-button', function() {
        const $button = $(this);
        const $card = $button.closest('.mxchat-status-card');
        
        let cardType = $card.data('card-type');
        if (!cardType) {
            cardType = $card.find('h4').text().includes('PDF') ? 'pdf' : 'sitemap';
        }
        
        $card.fadeOut(300, function() {
            $(this).remove();
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_clear_queue',
                nonce: mxchatAdmin.queue_nonce,
                queue_id: $card.data('queue-id') || ''
            },
            success: function(response) {
                //console.log('MxChat: Queue cleared');
            }
        });
    });
    
    /**
     * Update PDF status card (for already completed queues on page load)
     */
    function updatePdfStatus(status) {
        let $pdfCard = $('.mxchat-status-card:contains("PDF Processing")');
        
        if ($pdfCard.length === 0 && status) {
            createPdfStatusCard(status);
            $pdfCard = $('.mxchat-status-card:contains("PDF Processing")');
        }
        
        if ($pdfCard.length > 0 && status.status === 'complete') {
            // Show as completed (same as showCompletedPdfCard but from server data)
            showCompletedPdfCard(status);
        }
    }
    
    /**
     * Update sitemap status card (for already completed queues on page load)
     */
    function updateSitemapStatus(status) {
        let $sitemapCard = $('.mxchat-status-card:contains("Sitemap Processing")');
        
        if ($sitemapCard.length === 0 && status) {
            createSitemapStatusCard(status);
            $sitemapCard = $('.mxchat-status-card:contains("Sitemap Processing")');
        }
        
        if ($sitemapCard.length > 0 && status.status === 'complete') {
            // Show as completed (same as showCompletedSitemapCard but from server data)
            showCompletedSitemapCard(status);
        }
    }
    
    /**
     * Create PDF status card
     */
    function createPdfStatusCard(status) {
        let html = '<div class="mxchat-status-card" data-queue-id="' + (status.queue_id || '') + '">';
        html += '<div class="mxchat-status-header">';
        html += '<h4>PDF Processing Status</h4>';
        html += '</div>';
        html += '<div class="mxchat-progress-bar">';
        html += '<div class="mxchat-progress-fill" style="width: ' + status.percentage + '%"></div>';
        html += '</div>';
        html += '<div class="mxchat-status-details">';
        html += '<p>Progress: ' + status.processed_pages + ' of ' + status.total_pages + ' pages</p>';
        html += '</div>';
        html += '</div>';
        
        $('.mxchat-import-section').after($(html));
    }
    
    /**
     * Create sitemap status card
     */
    function createSitemapStatusCard(status) {
        let html = '<div class="mxchat-status-card" data-queue-id="' + (status.queue_id || '') + '">';
        html += '<div class="mxchat-status-header">';
        html += '<h4>Sitemap Processing Status</h4>';
        html += '</div>';
        html += '<div class="mxchat-progress-bar">';
        html += '<div class="mxchat-progress-fill" style="width: ' + status.percentage + '%"></div>';
        html += '</div>';
        html += '<div class="mxchat-status-details">';
        html += '<p>Progress: ' + status.processed_urls + ' of ' + status.total_urls + ' URLs</p>';
        html += '</div>';
        html += '</div>';
        
        $('.mxchat-import-section').after($(html));
    }
    
    /**
     * Add dismiss button to completed cards
     */
    function addDismissButton($card) {
        if ($card.find('.mxchat-dismiss-button').length === 0) {
            const dismissButton = $('<button type="button" class="mxchat-dismiss-button" style="padding: 6px 12px; background: #666; color: white; border: none; border-radius: 3px; cursor: pointer; margin-left: 10px;">Dismiss</button>');
            $card.find('.mxchat-status-header').append(dismissButton);
        }
    }
    
    /**
     * Show notification helper
     */
    function showNotification(type, message) {
        const $notification = $('<div class="mxchat-kb-notification ' + type + '">' + message + '</div>');
        $('.mxchat-content, body').first().prepend($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // ========================================
    // ROLE-BASED CONTENT RESTRICTIONS (Keep existing code)
    // ========================================
    
    if ($('#mxchat-mappings-container').length > 0) {
        loadTagRoleMappings();
    }
    
    $('#mxchat-add-tag-role').on('click', function() {
        const tagSlug = $('#mxchat-tag-input').val().trim();
        const roleRestriction = $('#mxchat-role-select').val();
        
        if (!tagSlug) {
            alert('Please enter a tag name');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Adding...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_add_tag_role_mapping',
                nonce: mxchatAdmin.settings_nonce,
                tag_slug: tagSlug,
                role_restriction: roleRestriction
            },
            success: function(response) {
                if (response.success) {
                    $('#mxchat-tag-input').val('');
                    $('#mxchat-role-select').val('public');
                    loadTagRoleMappings();
                    showNotification('success', 'Tag-role mapping added successfully!');
                } else {
                    alert('Error: ' + response.data);
                }
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Add Mapping');
            },
            error: function() {
                alert('Network error occurred');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Add Mapping');
            }
        });
    });
    
    $(document).on('click', '.mxchat-delete-mapping', function() {
        if (!confirm('Are you sure you want to delete this mapping?')) {
            return;
        }
        
        const $btn = $(this);
        const $row = $btn.closest('tr');
        const tagSlug = $btn.data('tag-slug');
        
        $btn.html('<span class="dashicons dashicons-update-alt"></span> Deleting...');
        $row.addClass('mxchat-row-deleting');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_delete_tag_role_mapping',
                nonce: mxchatAdmin.settings_nonce,
                tag_slug: tagSlug
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        if ($('.mxchat-mappings-table tbody tr').length === 0) {
                            $('.mxchat-mappings-table').hide();
                            $('#mxchat-no-mappings').show();
                        }
                    });
                    showNotification('success', 'Mapping deleted successfully!');
                } else {
                    alert('Error: ' + response.data);
                    $btn.html('<span class="dashicons dashicons-trash"></span> Delete');
                    $row.removeClass('mxchat-row-deleting');
                }
            },
            error: function() {
                alert('Network error occurred');
                $btn.html('<span class="dashicons dashicons-trash"></span> Delete');
                $row.removeClass('mxchat-row-deleting');
            }
        });
    });
    
    $('#mxchat-bulk-update-roles').on('click', function() {
        if (!confirm('This will update role restrictions for all existing content with mapped tags. Continue?')) {
            return;
        }
        
        const $btn = $(this);
        const $progress = $('#mxchat-bulk-update-progress');
        const $result = $('#mxchat-bulk-update-result');
        
        $progress.show();
        $result.hide();
        $btn.prop('disabled', true);
        
        $progress.find('.mxchat-progress-text').text('Starting bulk update...');
        $progress.find('.mxchat-progress-fill').css('width', '0%');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_bulk_update_tag_roles',
                nonce: mxchatAdmin.settings_nonce
            },
            success: function(response) {
                $progress.hide();
                $btn.prop('disabled', false);
                
                if (response.success) {
                    $result.removeClass('error').addClass('success');
                    
                    let resultHtml = '<h5>Bulk Update Complete</h5>';
                    resultHtml += '<p><strong>Total Updated:</strong> ' + response.data.updated_count + '</p>';
                    resultHtml += '<p><strong>Tags Processed:</strong> ' + response.data.tags_processed + '</p>';
                    
                    if (response.data.details && response.data.details.length > 0) {
                        resultHtml += '<ul>';
                        response.data.details.forEach(function(detail) {
                            resultHtml += '<li>' + detail + '</li>';
                        });
                        resultHtml += '</ul>';
                    }
                    
                    $result.html(resultHtml).show();
                    showNotification('success', 'Bulk update completed successfully!');
                } else {
                    $result.removeClass('success').addClass('error');
                    $result.html('<h5>Update Failed</h5><p>' + response.data + '</p>').show();
                }
            },
            error: function() {
                $progress.hide();
                $btn.prop('disabled', false);
                $result.removeClass('success').addClass('error');
                $result.html('<h5>Network Error</h5><p>Please try again.</p>').show();
            }
        });
    });
    
    function loadTagRoleMappings() {
        const $container = $('#mxchat-mappings-container');
        $container.html('<div class="mxchat-loading-mappings"><span class="mxchat-role-spinner is-active"></span> Loading mappings...</div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_tag_role_mappings',
                nonce: mxchatAdmin.settings_nonce
            },
            success: function(response) {
                if (response.success && response.data.mappings.length > 0) {
                    $('#mxchat-no-mappings').hide();
                    
                    let html = '<table class="mxchat-mappings-table">';
                    html += '<thead><tr><th>Tag</th><th>Role Restriction</th><th>Posts with Tag</th><th>Actions</th></tr></thead><tbody>';
                    
                    response.data.mappings.forEach(function(mapping) {
                        html += '<tr>';
                        html += '<td><span class="mxchat-tag-badge"><span class="dashicons dashicons-tag"></span>' + mapping.tag_slug + '</span></td>';
                        html += '<td><span class="mxchat-role-badge ' + mapping.role_restriction + '">' + mapping.role_label + '</span></td>';
                        html += '<td><span class="mxchat-post-count"><span class="dashicons dashicons-admin-post"></span>' + mapping.post_count + '</span></td>';
                        html += '<td><div class="mxchat-mapping-actions"><button class="mxchat-delete-mapping" data-tag-slug="' + mapping.tag_slug + '"><span class="dashicons dashicons-trash"></span> Delete</button></div></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    $container.html(html);
                } else {
                    $container.html('');
                    $('#mxchat-no-mappings').show();
                }
            },
            error: function() {
                $container.html('<div class="mxchat-error">Failed to load mappings. Please refresh the page.</div>');
            }
        });
    }
    
    // ========================================
    // PINECONE DELETE HANDLER (Keep existing code)
    // ========================================
    
    $(document).on('click', '.delete-button-ajax', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this entry?')) {
            return;
        }
        
        var $button = $(this);
        var $row = $button.closest('tr');
        var vectorId = $button.data('vector-id');
        var botId = $button.data('bot-id') || 'default';
        var nonce = $button.data('nonce');
        
        $button.prop('disabled', true);
        $button.find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-update-alt');
        $row.addClass('mxchat-row-deleting');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_delete_pinecone_prompt',
                nonce: nonce,
                vector_id: vectorId,
                bot_id: botId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(500, function() {
                        $(this).remove();
                        // Update entry count displays (header and sidebar)
                        var $countSpan = $('#mxchat-entry-count');
                        if ($countSpan.length) {
                            var currentText = $countSpan.text();
                            var match = currentText.match(/\((\d+)\)/);
                            if (match) {
                                var newCount = Math.max(0, parseInt(match[1]) - 1);
                                updateEntryCount(newCount);
                            }
                        }
                    });

                    $('<div class="notice notice-success is-dismissible"><p>Entry deleted successfully from Pinecone.</p></div>')
                        .insertAfter('.mxchat-hero')
                        .delay(3000)
                        .fadeOut();
                } else {
                    $button.prop('disabled', false);
                    $button.find('.dashicons').removeClass('dashicons-update-alt').addClass('dashicons-trash');
                    $row.removeClass('mxchat-row-deleting');
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('dashicons-update-alt').addClass('dashicons-trash');
                $row.removeClass('mxchat-row-deleting');
                alert('Network error occurred');
            }
        });
    });

    // ========================================
    // WORDPRESS DATABASE DELETE HANDLER (AJAX)
    // ========================================

    $(document).on('click', '.delete-button-wordpress', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this entry?')) {
            return;
        }

        var $button = $(this);
        var $row = $button.closest('tr');
        var entryId = $button.data('entry-id');
        var nonce = $button.data('nonce');

        $button.prop('disabled', true);
        $button.find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-update-alt spin');
        $row.addClass('mxchat-row-deleting');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_delete_wordpress_prompt',
                nonce: nonce,
                entry_id: entryId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(500, function() {
                        $(this).remove();
                        // Update entry count displays (header and sidebar)
                        var $countSpan = $('#mxchat-entry-count');
                        if ($countSpan.length) {
                            var currentText = $countSpan.text();
                            var match = currentText.match(/\((\d+)\)/);
                            if (match) {
                                var newCount = Math.max(0, parseInt(match[1]) - 1);
                                updateEntryCount(newCount);
                            }
                        }
                    });

                    $('<div class="notice notice-success is-dismissible"><p>Entry deleted successfully.</p></div>')
                        .insertAfter('.mxchat-hero')
                        .delay(3000)
                        .fadeOut();
                } else {
                    $button.prop('disabled', false);
                    $button.find('.dashicons').removeClass('dashicons-update-alt spin').addClass('dashicons-trash');
                    $row.removeClass('mxchat-row-deleting');
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('dashicons-update-alt spin').addClass('dashicons-trash');
                $row.removeClass('mxchat-row-deleting');
                alert('Network error occurred');
            }
        });
    });

    // ========================================
    // BULK SELECTION FOR KNOWLEDGE ENTRIES
    // ========================================

    var selectedKnowledgeEntries = new Set();

    // Update selection UI
    function updateKnowledgeSelectionUI() {
        var count = selectedKnowledgeEntries.size;
        var $countEl = $('#mxchat-selected-entry-count');
        var $deleteBtn = $('#mxchat-delete-selected-entries');
        var $deleteAllForm = $('#mxchat-delete-all-form');

        if (count > 0) {
            // Show Delete Selected button, hide Delete All form
            $deleteAllForm.hide();
            $deleteBtn.show();
            $countEl.text('(' + count + ')');
        } else {
            // Show Delete All form, hide Delete Selected button
            $deleteAllForm.show();
            $deleteBtn.hide();
            $countEl.text('');
        }

        // Update select all checkbox state
        var totalItems = $('.mxchat-entry-checkbox').length;
        var checkedItems = $('.mxchat-entry-checkbox:checked').length;
        $('.mxchat-entry-checkbox-all').prop('checked', totalItems > 0 && checkedItems === totalItems);
        $('.mxchat-entry-checkbox-all').prop('indeterminate', checkedItems > 0 && checkedItems < totalItems);
    }

    // Select all checkbox handler
    $(document).on('change', '.mxchat-entry-checkbox-all', function() {
        var isChecked = $(this).is(':checked');

        // Sync all select-all checkboxes
        $('.mxchat-entry-checkbox-all').prop('checked', isChecked);

        $('.mxchat-entry-checkbox').prop('checked', isChecked);

        if (isChecked) {
            $('.mxchat-entry-checkbox').each(function() {
                var entryData = {
                    id: $(this).data('entry-id'),
                    source: $(this).data('source'),
                    sourceUrl: $(this).data('source-url'),
                    isGroup: $(this).data('is-group'),
                    chunkCount: $(this).data('chunk-count') || 1
                };
                selectedKnowledgeEntries.add(JSON.stringify(entryData));
                $(this).closest('tr').addClass('selected');
            });
        } else {
            selectedKnowledgeEntries.clear();
            $('tr.selected').removeClass('selected');
        }

        updateKnowledgeSelectionUI();
    });

    // Individual checkbox handler
    $(document).on('change', '.mxchat-entry-checkbox', function() {
        var $checkbox = $(this);
        var $row = $checkbox.closest('tr');
        var entryData = {
            id: $checkbox.data('entry-id'),
            source: $checkbox.data('source'),
            sourceUrl: $checkbox.data('source-url'),
            isGroup: $checkbox.data('is-group'),
            chunkCount: $checkbox.data('chunk-count') || 1
        };
        var entryKey = JSON.stringify(entryData);

        if ($checkbox.is(':checked')) {
            selectedKnowledgeEntries.add(entryKey);
            $row.addClass('selected');
        } else {
            selectedKnowledgeEntries.delete(entryKey);
            $row.removeClass('selected');
        }

        updateKnowledgeSelectionUI();
    });

    // Bulk delete button handler
    $(document).on('click', '#mxchat-delete-selected-entries', function() {
        var count = selectedKnowledgeEntries.size;
        if (count === 0) return;

        if (!confirm('Are you sure you want to delete ' + count + ' selected entries? This action cannot be undone.')) {
            return;
        }

        var $button = $(this);
        var nonce = $button.data('nonce');
        var botId = $button.data('bot-id');

        // Parse selected entries
        var entries = Array.from(selectedKnowledgeEntries).map(function(entryStr) {
            return JSON.parse(entryStr);
        });

        // Show loading state
        $button.prop('disabled', true);
        $button.find('.mxchat-bulk-delete-text').text('Deleting...');
        $button.find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-update spin');

        // Mark rows as deleting
        entries.forEach(function(entry) {
            $('#prompt-' + entry.id).addClass('mxchat-row-deleting');
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_bulk_delete_knowledge',
                entries: entries,
                bot_id: botId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;

                    // Remove successful rows
                    if (data.success_ids && data.success_ids.length > 0) {
                        data.success_ids.forEach(function(id) {
                            var $row = $('#prompt-' + id);
                            // Also remove child chunk rows if it's a group
                            var groupId = $row.data('group-id');
                            if (groupId) {
                                $('.mxchat-chunk-row.' + groupId).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }
                            $row.fadeOut(300, function() {
                                $(this).remove();
                            });
                        });
                    }

                    // Handle failed entries
                    if (data.failed_ids && data.failed_ids.length > 0) {
                        data.failed_ids.forEach(function(id) {
                            $('#prompt-' + id).removeClass('mxchat-row-deleting').addClass('mxchat-row-error');
                        });
                    }

                    // Show result message
                    var successCount = data.success_ids ? data.success_ids.length : 0;
                    var failedCount = data.failed_ids ? data.failed_ids.length : 0;
                    var message = 'Deleted ' + successCount + ' entries.';
                    if (failedCount > 0) {
                        message += ' ' + failedCount + ' entries failed to delete.';
                    }

                    $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>')
                        .insertAfter('.mxchat-hero')
                        .delay(5000)
                        .fadeOut();

                    // Clear selection
                    selectedKnowledgeEntries.clear();
                    updateKnowledgeSelectionUI();

                    // Update entry count displays (header and sidebar)
                    if (successCount > 0) {
                        var $countSpan = $('#mxchat-entry-count');
                        if ($countSpan.length) {
                            var currentText = $countSpan.text();
                            var match = currentText.match(/\((\d+)\)/);
                            if (match) {
                                var newCount = Math.max(0, parseInt(match[1]) - successCount);
                                updateEntryCount(newCount);
                            }
                        }
                    }

                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    $('tr.mxchat-row-deleting').removeClass('mxchat-row-deleting');
                }
            },
            error: function() {
                alert('Network error occurred');
                $('tr.mxchat-row-deleting').removeClass('mxchat-row-deleting');
            },
            complete: function() {
                $button.prop('disabled', selectedKnowledgeEntries.size === 0);
                $button.find('.mxchat-bulk-delete-text').text('Delete Selected');
                $button.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
            }
        });
    });

    // Clear selection when page changes
    $(document).on('click', '.mxchat-page-link', function() {
        selectedKnowledgeEntries.clear();
        updateKnowledgeSelectionUI();
    });

    // ========================================
    // AJAX PAGINATION FOR KNOWLEDGE BASE ENTRIES
    // ========================================

    // Handle pagination link clicks
    $(document).on('click', '.mxchat-page-link', function(e) {
        e.preventDefault();

        var $link = $(this);
        var page = $link.data('page');
        var $paginationWrapper = $('#mxchat-kb-pagination');
        var $tbody = $('#mxchat-entries-tbody');

        if (!page || $link.hasClass('loading')) {
            return;
        }

        // Show loading state
        $link.addClass('loading');
        $tbody.css('opacity', '0.5');

        // Add loading indicator to pagination
        var $loadingIndicator = $('<span class="mxchat-pagination-loading"><span class="dashicons dashicons-update spin"></span></span>');
        $paginationWrapper.find('.mxchat-ajax-pagination').append($loadingIndicator);

        // Get search and filter values from pagination wrapper
        var searchQuery = $paginationWrapper.data('search') || '';
        var contentType = $paginationWrapper.data('content-type') || '';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_paginate_entries',
                nonce: mxchatAdmin.entries_nonce,
                bot_id: mxchatAdmin.bot_id || 'default',
                page: page,
                search: searchQuery,
                content_type: contentType
            },
            success: function(response) {
                $link.removeClass('loading');
                $tbody.css('opacity', '1');
                $loadingIndicator.remove();

                if (response.success && response.data) {
                    // Update the table body with new HTML
                    $tbody.html(response.data.html);

                    // Update the pagination - find the inner container
                    if (response.data.pagination_html) {
                        // Replace the inner pagination div content
                        $paginationWrapper.html(response.data.pagination_html);
                    }

                    // Update data attributes on wrapper (preserve search/filter for next pagination)
                    $paginationWrapper.attr('data-current-page', response.data.page);
                    if (response.data.total_pages) {
                        $paginationWrapper.attr('data-total-pages', response.data.total_pages);
                    }
                    // Preserve search and content_type on the wrapper from the inner pagination div
                    var $innerPagination = $paginationWrapper.find('.mxchat-ajax-pagination');
                    if ($innerPagination.length) {
                        $paginationWrapper.attr('data-search', $innerPagination.data('search') || '');
                        $paginationWrapper.attr('data-content-type', $innerPagination.data('content-type') || '');
                    }

                    // Scroll to top of the table smoothly
                    $('html, body').animate({
                        scrollTop: $('#knowledge-base').offset().top - 50
                    }, 300);

                    // Update URL hash to stay on knowledge-base tab
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', window.location.pathname + window.location.search + '#knowledge-base');
                    }

                    // Show success feedback
                    showNotification('success', 'Page ' + response.data.page + ' loaded');
                } else {
                    showNotification('error', 'Failed to load page: ' + (response.data?.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                $link.removeClass('loading');
                $tbody.css('opacity', '1');
                $loadingIndicator.remove();
                showNotification('error', 'Network error while loading page');
            }
        });
    });

    // ========================================
    // PINECONE REFRESH ENTRIES BUTTON
    // ========================================

    $('#mxchat-refresh-pinecone-entries').on('click', function() {
        var $button = $(this);
        var $icon = $button.find('.dashicons');
        var $tbody = $('#mxchat-entries-tbody');
        var $paginationWrapper = $('#mxchat-kb-pagination');

        // Show loading state
        $button.prop('disabled', true);
        $icon.addClass('spin');
        $tbody.css('opacity', '0.5');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_refresh_pinecone_entries',
                nonce: mxchatAdmin.entries_nonce,
                bot_id: mxchatAdmin.bot_id || 'default',
                page: 1
            },
            success: function(response) {
                $button.prop('disabled', false);
                $icon.removeClass('spin');
                $tbody.css('opacity', '1');

                if (response.success && response.data) {
                    // Update the table body with new HTML
                    $tbody.html(response.data.html);

                    // Update the pagination (same pattern as refreshKnowledgeBaseTable)
                    if ($paginationWrapper.length) {
                        if (response.data.pagination_html) {
                            $paginationWrapper.html(response.data.pagination_html);
                            $paginationWrapper.attr('style', 'padding: 16px; border-top: 1px solid var(--mxch-card-border); text-align: center;');
                        } else {
                            $paginationWrapper.html('');
                            $paginationWrapper.attr('style', '');
                        }
                    }

                    // Update the count display
                    if (response.data.total_count !== undefined) {
                        updateEntryCount(response.data.total_count);
                    }

                    // Show success feedback
                    showNotification('success', 'Entries refreshed successfully!');
                } else {
                    showNotification('error', 'Failed to refresh entries: ' + (response.data?.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false);
                $icon.removeClass('spin');
                $tbody.css('opacity', '1');
                showNotification('error', 'Network error while refreshing entries');
            }
        });
    });

    // ========================================
    // ACCORDION FUNCTIONALITY
    // ========================================

    // Handle expand/collapse toggle
    $(document).on('click', '.mxchat-expand-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $button = $(this);
        const $wrapper = $button.closest('.mxchat-accordion-wrapper');
        const $preview = $wrapper.find('.mxchat-content-preview');
        const $fullContent = $wrapper.find('.mxchat-content-full');

        // Toggle expanded state
        if ($fullContent.is(':visible')) {
            // Collapse
            $fullContent.slideUp(300);
            $button.removeClass('expanded');
        } else {
            // Expand
            $fullContent.slideDown(300);
            $button.addClass('expanded');
        }
    });

    // Click anywhere on preview to toggle (expand or collapse)
    $(document).on('click', '.mxchat-content-preview', function(e) {
        // Only trigger if not clicking the button directly
        if (!$(e.target).closest('.mxchat-expand-toggle').length) {
            const $preview = $(this);
            const $wrapper = $preview.closest('.mxchat-accordion-wrapper');
            const $button = $preview.find('.mxchat-expand-toggle');

            // Only trigger if there's a button (meaning content is long enough to expand)
            if ($button.length) {
                $button.trigger('click');
            }
        }
    });

    // ========================================
    // CHUNK GROUP TOGGLE FUNCTIONALITY
    // ========================================

    // Handle chunk group expand/collapse toggle
    $(document).on('click', '.mxchat-chunk-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $button = $(this);
        const groupId = $button.data('group-id');
        const $chunkRows = $('.mxchat-chunk-row.' + groupId);

        // Toggle expanded state
        if ($button.hasClass('expanded')) {
            // Collapse
            $chunkRows.slideUp(300);
            $button.removeClass('expanded');
        } else {
            // Expand
            $chunkRows.slideDown(300);
            $button.addClass('expanded');
        }
    });

    // Handle delete button for chunk groups
    $(document).on('click', '.delete-button-group', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $button = $(this);
        const sourceUrl = $button.data('source-url');
        const chunkCount = $button.data('chunk-count');
        const dataSource = $button.data('data-source');
        const botId = $button.data('bot-id');
        const nonce = $button.data('nonce');

        // Confirm deletion
        if (!confirm('Are you sure you want to delete all ' + chunkCount + ' chunks for this URL? This action cannot be undone.')) {
            return;
        }

        // Show loading state
        $button.prop('disabled', true);
        $button.find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-update spin');

        // Make AJAX request to delete all chunks for this URL
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_delete_chunks_by_url',
                source_url: sourceUrl,
                data_source: dataSource,
                bot_id: botId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove the group header row and all chunk rows
                    const $headerRow = $button.closest('tr');
                    const groupId = $headerRow.data('group-id');
                    $('.mxchat-chunk-row.' + groupId).fadeOut(300, function() {
                        $(this).remove();
                    });
                    $headerRow.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    var errorMsg = response.data || 'Unknown error';
                    if (typeof response.data === 'object' && response.data.message) {
                        errorMsg = response.data.message;
                    }
                    alert('Error deleting chunks: ' + errorMsg);
                    $button.prop('disabled', false);
                    $button.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr.responseText);
                alert('Error deleting chunks: ' + (error || 'Server error'));
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
            }
        });
    });

    // ========================================
    // REAL-TIME KNOWLEDGE ENTRIES UPDATE
    // ========================================

    let lastEntryId = 0;
    let entriesPollingInterval = null;
    let isEntriesPolling = false;

    // Initialize: get the highest ID from the current table
    function initializeLastEntryId() {
        const $tbody = $('#mxchat-entries-tbody');
        if ($tbody.length === 0) return;

        // Get the highest ID from the table
        $tbody.find('tr').each(function() {
            const idText = $(this).find('td:first').text().trim();
            const id = parseInt(idText);
            if (!isNaN(id) && id > lastEntryId) {
                lastEntryId = id;
            }
        });
    }

    // Poll for new entries during processing
    function startEntriesPolling() {
        if (entriesPollingInterval) return; // Already polling

        isEntriesPolling = true;

        // Fetch immediately, then start interval
        fetchNewEntries();

        entriesPollingInterval = setInterval(function() {
            fetchNewEntries();
        }, 3000); // Poll every 3 seconds
    }

    function stopEntriesPolling() {
        if (entriesPollingInterval) {
            clearInterval(entriesPollingInterval);
            entriesPollingInterval = null;
        }
        isEntriesPolling = false;
    }

    // Track Pinecone count for change detection
    var lastPineconeCount = 0;
    var pineconeRefreshPending = false;

    // Fetch new entries from server
    function fetchNewEntries() {
        if (!mxchatAdmin.entries_nonce) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_recent_entries',
                nonce: mxchatAdmin.entries_nonce,
                last_id: lastEntryId,
                bot_id: mxchatAdmin.bot_id || 'default',
                limit: 20
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Update count
                    if (response.data.total_count !== undefined) {
                        updateEntryCount(response.data.total_count);
                    }

                    // Handle Pinecone data source differently
                    if (response.data.data_source === 'pinecone') {
                        var newCount = response.data.total_count || 0;

                        // If count changed and we haven't scheduled a refresh yet
                        if (newCount !== lastPineconeCount && !pineconeRefreshPending) {
                            lastPineconeCount = newCount;

                            // Schedule a table refresh after processing completes
                            // Show a "refresh to see entries" message
                            var $tbody = $('#mxchat-entries-tbody');
                            var $refreshNotice = $tbody.find('.mxchat-pinecone-refresh-notice');

                            if ($refreshNotice.length === 0 && newCount > 0) {
                                var noticeHtml = '<tr class="mxchat-pinecone-refresh-notice">' +
                                    '<td colspan="4" style="padding: 20px; text-align: center; background: #f0f7ff; border-bottom: 1px solid var(--mxch-card-border);">' +
                                    '<span class="dashicons dashicons-update" style="color: #7873f5; margin-right: 8px;"></span>' +
                                    '<strong>' + newCount + ' entries in Pinecone.</strong> ' +
                                    '<a href="#" class="mxchat-refresh-table-link" style="color: #7873f5; text-decoration: underline;">Refresh to see new entries</a>' +
                                    '</td></tr>';
                                $tbody.prepend(noticeHtml);

                                // Handle refresh link click
                                $tbody.find('.mxchat-refresh-table-link').on('click', function(e) {
                                    e.preventDefault();
                                    location.reload();
                                });
                            } else if ($refreshNotice.length > 0) {
                                // Update the count in existing notice
                                $refreshNotice.find('strong').text(newCount + ' entries in Pinecone.');
                            }
                        }
                    } else {
                        // WordPress DB - Track new entries and show refresh notice
                        // Don't add rows individually during processing as they need to be grouped by source_url
                        if (response.data.entries && response.data.entries.length > 0) {
                            // Update last ID to track progress
                            if (response.data.max_id > lastEntryId) {
                                lastEntryId = response.data.max_id;
                            }

                            // Show/update refresh notice (similar to Pinecone handling)
                            var $tbody = $('#mxchat-entries-tbody');
                            var $refreshNotice = $tbody.find('.mxchat-wordpress-refresh-notice');
                            var newCount = response.data.total_count || 0;

                            if ($refreshNotice.length === 0 && newCount > 0) {
                                var noticeHtml = '<tr class="mxchat-wordpress-refresh-notice mxchat-new-entry">' +
                                    '<td colspan="4" style="padding: 16px 20px; text-align: center; background: linear-gradient(135deg, rgba(120, 115, 245, 0.08) 0%, rgba(167, 139, 250, 0.05) 100%); border-bottom: 1px solid var(--mxch-card-border);">' +
                                    '<span class="dashicons dashicons-update spin" style="color: #7873f5; margin-right: 8px;"></span>' +
                                    '<strong style="color: var(--mxch-text-primary);">Processing... <span class="mxchat-processing-count">' + newCount + '</span> entries</strong>' +
                                    '</td></tr>';
                                $tbody.prepend(noticeHtml);
                            } else if ($refreshNotice.length > 0) {
                                // Update the count in existing notice
                                $refreshNotice.find('.mxchat-processing-count').text(newCount);
                            }
                        } else {
                            // Update last ID even if no new entries
                            if (response.data.max_id > lastEntryId) {
                                lastEntryId = response.data.max_id;
                            }
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('MxChat: Error fetching new entries:', error, xhr.responseText);
            }
        });
    }

    // Update the entry count display
    function updateEntryCount(count) {
        // Update main table count
        const $countSpan = $('#mxchat-entry-count');
        if ($countSpan.length) {
            $countSpan.text('(' + count + ')');

            // Flash animation to indicate update
            $countSpan.addClass('mxchat-count-updated');
            setTimeout(function() {
                $countSpan.removeClass('mxchat-count-updated');
            }, 1000);
        }

        // Update sidebar badge count
        const $sidebarCount = $('#mxchat-sidebar-count');
        if ($sidebarCount.length) {
            $sidebarCount.text(count);

            // Flash animation for sidebar too
            $sidebarCount.addClass('mxchat-count-updated');
            setTimeout(function() {
                $sidebarCount.removeClass('mxchat-count-updated');
            }, 1000);
        }
    }

    // Refresh the knowledge base table via AJAX pagination
    // This ensures entries are properly grouped by source_url
    function refreshKnowledgeBaseTable() {
        var $paginationWrapper = $('#mxchat-kb-pagination');
        var $tbody = $('#mxchat-entries-tbody');

        if ($tbody.length === 0) {
            return;
        }

        // Remove any processing notice
        $tbody.find('.mxchat-wordpress-refresh-notice, .mxchat-pinecone-refresh-notice').remove();

        // Show loading state
        $tbody.css('opacity', '0.5');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_paginate_entries',
                nonce: mxchatAdmin.entries_nonce,
                bot_id: mxchatAdmin.bot_id || 'default',
                page: 1 // Always go to first page to see newest entries
            },
            success: function(response) {
                $tbody.css('opacity', '1');

                if (response.success && response.data) {
                    // Update the table body with properly grouped HTML
                    $tbody.html(response.data.html);

                    // Update the pagination
                    if ($paginationWrapper.length) {
                        if (response.data.pagination_html) {
                            // Add pagination content and styling
                            $paginationWrapper.html(response.data.pagination_html);
                            $paginationWrapper.attr('style', 'padding: 16px; border-top: 1px solid var(--mxch-card-border); text-align: center;');
                        } else {
                            // No pagination needed - clear and hide
                            $paginationWrapper.html('');
                            $paginationWrapper.attr('style', '');
                        }
                    }

                    // Update count display
                    if (response.data.total_count !== undefined) {
                        updateEntryCount(response.data.total_count);
                    }
                }
            },
            error: function(xhr, status, error) {
                $tbody.css('opacity', '1');
                console.error('MxChat: Error refreshing table:', error);
            }
        });
    }

    // Expose refreshKnowledgeBaseTable for external access (content-selector.js)
    window.refreshKnowledgeBaseTable = refreshKnowledgeBaseTable;

    // Add new entries to the table (kept for backwards compatibility but not used during processing)
    function addNewEntriesToTable(entries) {
        const $tbody = $('#mxchat-entries-tbody');
        if ($tbody.length === 0) return;

        // Remove "no entries" message if present
        const $noEntries = $tbody.find('td[colspan="4"]').closest('tr');
        if ($noEntries.length) {
            $noEntries.remove();
        }

        // Add entries in reverse order (oldest first, so newest ends up at top)
        entries.reverse().forEach(function(entry) {
            // Check if entry already exists
            if ($tbody.find('tr[data-entry-id="' + entry.id + '"]').length > 0) {
                return;
            }

            const sourceHtml = entry.has_link
                ? '<a href="' + entry.source_url + '" target="_blank" style="color: var(--mxch-primary); text-decoration: none;"><span class="dashicons dashicons-external" style="font-size: 14px;"></span> View</a>'
                : '<span style="color: var(--mxch-text-muted);">Manual</span>';

            const deleteUrl = mxchatAdmin.admin_url + 'admin-post.php?action=mxchat_delete_prompt&id=' + entry.id + '&_wpnonce=' + entry.delete_nonce;

            // Check if content needs expand button (content longer than preview)
            const needsExpand = entry.content_length > entry.preview_length;

            // Build accordion-style content cell (matching initial page load structure)
            let contentHtml = '<div class="mxchat-accordion-wrapper">' +
                '<div class="mxchat-content-preview">' +
                    '<span class="preview-text">' + entry.preview + '</span>';

            if (needsExpand) {
                contentHtml += '<button class="mxchat-expand-toggle" type="button">' +
                    '<span class="dashicons dashicons-arrow-down-alt2"></span>' +
                '</button>';
            }

            contentHtml += '</div>' +
                '<div class="mxchat-content-full" style="display: none;">' +
                    '<div class="content-view">' + entry.full_content + '</div>' +
                '</div>' +
            '</div>';

            const $row = $('<tr id="prompt-' + entry.id + '" data-entry-id="' + entry.id + '" data-source="wordpress" style="border-bottom: 1px solid var(--mxch-card-border); display: none;">' +
                '<td style="padding: 12px 16px; font-size: 13px;">' + entry.id + '</td>' +
                '<td class="mxchat-content-cell" style="padding: 12px 16px; font-size: 13px;">' + contentHtml + '</td>' +
                '<td class="mxchat-url-cell" style="padding: 12px 16px; font-size: 13px;">' + sourceHtml + '</td>' +
                '<td style="padding: 12px 16px;">' +
                    '<a href="' + deleteUrl + '" class="mxch-btn mxch-btn-ghost mxch-btn-sm" style="color: var(--mxch-error);" onclick="return confirm(\'Delete this entry?\');">' +
                        '<span class="dashicons dashicons-trash" style="font-size: 14px;"></span>' +
                    '</a>' +
                '</td>' +
            '</tr>');

            // Add highlight class and prepend to tbody
            $row.addClass('mxchat-new-entry');
            $tbody.prepend($row);
            $row.slideDown(300);

            // Remove highlight after animation
            setTimeout(function() {
                $row.removeClass('mxchat-new-entry');
            }, 2000);
        });
    }

    // Initialize entry ID tracking
    initializeLastEntryId();

    // Check on page load if there's already active processing (e.g., page was refreshed during processing)
    if ($('.mxchat-status-card').length > 0) {
        // Check if processing is active via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_status_updates',
                nonce: mxchatAdmin.status_nonce
            },
            success: function(response) {
                if (response.is_processing) {
                    startEntriesPolling();
                }
            }
        });
    }

    // Expose functions for external access
    window.mxchatEntriesPolling = {
        start: startEntriesPolling,
        stop: stopEntriesPolling,
        fetch: fetchNewEntries
    };

    // ========================================
    // SITEMAP DETECTION FUNCTIONALITY
    // ========================================

    let sitemapDetectionInitialized = false;

    /**
     * Initialize sitemap detection when Sitemap Import is clicked
     */
    function initSitemapDetection() {
        if (sitemapDetectionInitialized) return;

        const loadingEl = document.getElementById('mxchat-sitemaps-loading');
        const detectedEl = document.getElementById('mxchat-detected-sitemaps');
        const noSitemapsEl = document.getElementById('mxchat-no-sitemaps');
        const listEl = document.getElementById('mxchat-sitemaps-list');
        const refreshBtn = document.getElementById('mxchat-refresh-sitemaps');
        const nonceEl = document.getElementById('mxchat-detect-sitemaps-nonce');

        if (!loadingEl || !nonceEl) return;

        sitemapDetectionInitialized = true;

        function detectSitemaps() {
            // Show loading
            loadingEl.style.display = 'block';
            if (detectedEl) detectedEl.style.display = 'none';
            if (noSitemapsEl) noSitemapsEl.style.display = 'none';

            // Disable refresh button
            if (refreshBtn) {
                refreshBtn.disabled = true;
                var refreshIcon = refreshBtn.querySelector('.dashicons');
                if (refreshIcon) refreshIcon.classList.add('spin');
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mxchat_detect_sitemaps',
                    nonce: nonceEl.value
                },
                timeout: 60000, // 60 second timeout for slow servers
                success: function(data) {
                    loadingEl.style.display = 'none';

                    // Re-enable refresh button
                    if (refreshBtn) {
                        refreshBtn.disabled = false;
                        var refreshIcon = refreshBtn.querySelector('.dashicons');
                        if (refreshIcon) refreshIcon.classList.remove('spin');
                    }

                    if (data.success && data.data && data.data.sitemaps && data.data.sitemaps.length > 0) {
                        renderSitemaps(data.data.sitemaps);
                        if (detectedEl) detectedEl.style.display = 'block';
                    } else {
                        if (noSitemapsEl) {
                            noSitemapsEl.style.display = 'block';
                            $(noSitemapsEl).data('was-shown', true);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    loadingEl.style.display = 'none';
                    if (noSitemapsEl) {
                        noSitemapsEl.style.display = 'block';
                        $(noSitemapsEl).data('was-shown', true);
                    }
                    if (refreshBtn) {
                        refreshBtn.disabled = false;
                        var refreshIcon = refreshBtn.querySelector('.dashicons');
                        if (refreshIcon) refreshIcon.classList.remove('spin');
                    }
                }
            });
        }

        function renderSitemaps(sitemaps) {
            if (!listEl) return;

            var html = '';
            var botIdEl = document.getElementById('mxchat-sitemap-bot-id');
            var botId = botIdEl ? botIdEl.value : '';

            sitemaps.forEach(function(sitemap) {
                if (sitemap.type === 'index' && sitemap.sub_sitemaps && sitemap.sub_sitemaps.length > 0) {
                    // Render sitemap index with sub-sitemaps
                    html += '<div class="mxchat-sitemap-group">';
                    html += '<div class="mxchat-sitemap-group-header">';
                    html += '<div style="display: flex; align-items: center; gap: 10px;">';
                    html += '<span class="dashicons dashicons-arrow-right-alt2" style="transition: transform 0.2s;"></span>';
                    html += '<span class="dashicons dashicons-list-view" style="color: #7873f5;"></span>';
                    html += '<div>';
                    html += '<strong style="font-size: 13px;">Sitemap Index</strong>';
                    html += '<span style="color: #666; font-size: 12px; margin-left: 8px;">' + sitemap.source + '</span>';
                    html += '</div>';
                    html += '</div>';
                    html += '<span style="background: rgba(120, 115, 245, 0.1); color: #7873f5; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">';
                    html += sitemap.sub_sitemaps.length + ' sitemaps';
                    html += '</span>';
                    html += '</div>';
                    html += '<div class="mxchat-sitemap-sub-list">';
                    sitemap.sub_sitemaps.forEach(function(sub) {
                        html += renderSitemapRow(sub, botId, true);
                    });
                    html += '</div>';
                    html += '</div>';
                } else if (sitemap.type !== 'index') {
                    // Render standalone sitemap
                    html += renderSitemapRow(sitemap, botId, false);
                }
            });

            listEl.innerHTML = html;

            // Add click handlers for group toggles
            $(listEl).find('.mxchat-sitemap-group-header').on('click', function() {
                var $group = $(this).parent();
                var $subList = $group.find('.mxchat-sitemap-sub-list');
                var $arrow = $(this).find('.dashicons-arrow-right-alt2');

                $group.toggleClass('expanded');

                if ($group.hasClass('expanded')) {
                    $subList.slideDown(200);
                    $arrow.css('transform', 'rotate(90deg)');
                } else {
                    $subList.slideUp(200);
                    $arrow.css('transform', 'rotate(0deg)');
                }
            });

            // Add click handlers for process buttons
            $(listEl).find('.mxchat-process-sitemap-btn').on('click', function() {
                var url = $(this).data('url');
                var type = $(this).data('sitemap-type');
                processSitemap(url, type, this);
            });
        }

        function renderSitemapRow(sitemap, botId, isSubItem) {
            var typeLabels = {
                'content': 'Content',
                'taxonomy': 'Taxonomy',
                'author': 'Authors'
            };
            var typeLabel = typeLabels[sitemap.type] || sitemap.type;
            var displayName = sitemap.name || sitemap.url.split('/').pop();
            var urlCount = sitemap.url_count || 0;
            var paddingLeft = isSubItem ? '40px' : '16px';

            var html = '<div class="mxchat-sitemap-row" style="padding-left: ' + paddingLeft + ';">';
            html += '<div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">';
            html += '<span class="dashicons dashicons-media-text" style="color: #666; flex-shrink: 0;"></span>';
            html += '<div style="min-width: 0; flex: 1;">';
            html += '<div style="font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' + sitemap.url + '">';
            html += displayName;
            html += '</div>';
            html += '<div style="font-size: 11px; color: #666;">';
            html += '<span style="background: #f0f0f0; padding: 1px 6px; border-radius: 3px; margin-right: 8px;">' + typeLabel + '</span>';
            if (urlCount > 0) {
                html += urlCount + ' URLs';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '<button type="button" class="mxchat-process-sitemap-btn mxch-btn mxch-btn-primary mxch-btn-sm" data-url="' + sitemap.url + '" data-sitemap-type="' + sitemap.type + '">';
            html += '<span class="dashicons dashicons-download" style="font-size: 14px; margin-top: 3px;"></span> Process';
            html += '</button>';
            html += '</div>';

            return html;
        }

        function processSitemap(url, type, buttonEl) {
            var $button = $(buttonEl);
            var originalHtml = $button.html();

            // Update button to show loading
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spin" style="font-size: 14px; margin-top: 3px;"></span> Processing...');

            // Fill in the sitemap URL form and submit
            var $form = $('#mxchat-url-form');
            var $urlInput = $('#sitemap_url');
            var $importType = $('#import_type');

            if ($urlInput.length) {
                $urlInput.val(url);
            }

            if ($importType.length) {
                $importType.val('sitemap');
            }

            // Add a hidden submit field if not present (required by the PHP handler)
            if ($form.find('input[name="submit_sitemap"]').length === 0) {
                $form.append('<input type="hidden" name="submit_sitemap" value="1">');
            }

            // Submit the form
            $form.submit();
        }


        // Refresh button handler
        if (refreshBtn) {
            $(refreshBtn).on('click', detectSitemaps);
        }

        // Start detection
        detectSitemaps();
    }

    // Expose initSitemapDetection globally so it can be called from the import options handler
    window.mxchatInitSitemapDetection = initSitemapDetection;

    // ========================================
    // ADMIN NOTICE DISMISS FUNCTIONALITY
    // ========================================

    // Initialize dismissible notices - add dismiss button if missing
    function initDismissibleNotices() {
        $('.notice.is-dismissible').each(function() {
            var $notice = $(this);

            // Skip if already has a dismiss button
            if ($notice.find('.notice-dismiss').length > 0) {
                return;
            }

            // Add dismiss button
            var $dismissButton = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            $notice.append($dismissButton);
        });
    }

    // Initialize on page load
    initDismissibleNotices();

    // Use event delegation for dismiss button clicks - works for existing and dynamically added notices
    $(document).on('click', '.notice.is-dismissible .notice-dismiss', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var $notice = $(this).closest('.notice');
        $notice.fadeTo(100, 0, function() {
            $notice.slideUp(100, function() {
                $notice.remove();
            });
        });
    });

    // Re-initialize when new notices are added dynamically (e.g., via AJAX)
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).hasClass('notice') && $(e.target).hasClass('is-dismissible')) {
            setTimeout(initDismissibleNotices, 10);
        }
    });
});