jQuery(document).ready(function($) {
    // Modal elements
    const $modal = $('#mxchat-kb-content-selector-modal');
    const $openButton = $('#mxchat-open-content-selector');
    const $closeButtons = $('.mxchat-kb-modal-close');
    const $contentList = $('.mxchat-kb-content-list');
    const $loading = $('.mxchat-kb-loading');
    const $pagination = $('.mxchat-kb-pagination');
    const $processButton = $('#mxchat-kb-process-selected');
    const $selectAll = $('#mxchat-kb-select-all');
    const $selectionCount = $('.mxchat-kb-selection-count');
    
    // Filter elements
    const $searchInput = $('#mxchat-kb-content-search');1
    const $typeFilter = $('#mxchat-kb-content-type-filter');
    const $statusFilter = $('#mxchat-kb-content-status-filter');
    const $processedFilter = $('#mxchat-kb-processed-filter');
    
    // Current state - using let for variables that change
    let currentPage = 1;
    let totalPages = 1;
    let selectedItems = new Set();
    let allItems = [];
    
    // Open modal when WordPress import button is clicked
    $openButton.on('click', function() {
        $modal.show();
        // Reset to first page when opening the modal
        currentPage = 1;
        loadContent();
    });
    
    // Close modal
    $closeButtons.on('click', function() {
        $modal.hide();
    });
// Handle import option box clicks (for non-WordPress options)
$('.mxchat-import-box').on('click', function() {
    const $box = $(this);
    const option = $box.data('option');

    // Skip if this is the WordPress option (it has its own handler)
    if (option === 'wordpress') {
        return;
    }

    // Update active state
    $('.mxchat-import-box').removeClass('active');
    $box.addClass('active');

    // Hide all input areas
    $('#mxchat-url-input-area, #mxchat-content-input-area').hide();

    // Hide sitemap-specific sections (but NOT for sitemap option - let detection logic handle it)
    if (option !== 'sitemap') {
        $('#mxchat-detected-sitemaps, #mxchat-no-sitemaps, #mxchat-sitemaps-loading').hide();
    }

    // Handle different import options
    switch (option) {
        case 'pdf':
        case 'sitemap':
        case 'url':
            // Show URL input area with appropriate placeholder
            $('#mxchat-url-input-area').show();
            $('#sitemap_url').attr('placeholder', $box.data('placeholder'));
            $('#import_type').val(option);

            // UPDATED: Add or update bot_id hidden field for URL forms
            updateBotIdInForm('#mxchat-url-form');

            // Update the description text based on the import type
            let descriptionText = '';
            if (option === 'pdf') {
                descriptionText = 'Import a PDF document by entering its URL above. PDFs are processed via cron job. If processing does not start, you can manually process batch 5 pages at a time.';
            } else if (option === 'sitemap') {
                descriptionText = 'Enter a content-specific sub-sitemap URL, not the sitemap index. Sitemaps are processed via cron job. If processing does not start, you can manually process batch 5 pages at a time.';
                // Re-show sitemap sections if they were previously loaded
                const $sitemapsList = $('#mxchat-sitemaps-list');
                if ($sitemapsList.children().length > 0) {
                    // Sitemaps were already loaded, just show the container
                    $('#mxchat-detected-sitemaps').show();
                } else if ($('#mxchat-no-sitemaps').data('was-shown')) {
                    // No sitemaps message was shown before
                    $('#mxchat-no-sitemaps').show();
                }
                // Note: If neither condition is true, initSitemapDetection will show loading state
            } else if (option === 'url') {
                descriptionText = 'Import content from any webpage by entering its URL.';
            }
            $('#url-description-text').text(descriptionText);
            break;

        case 'content':
            // Show content input area
            $('#mxchat-content-input-area').show();

            // UPDATED: Add or update bot_id hidden field for content forms
            updateBotIdInForm('#mxchat-content-form');
            break;
    }
});

// Helper function to add/update bot_id hidden field in forms
function updateBotIdInForm(formSelector) {
    const $form = $(formSelector);
    if ($form.length === 0) return;
    
    // Get current bot_id from the bot selector dropdown
    const currentBotId = $('#mxchat-bot-selector').val();
    
    // Only add bot_id field if multi-bot is active and bot is not 'default'
    if (currentBotId && currentBotId !== 'default') {
        // Remove existing bot_id field if it exists
        $form.find('input[name="bot_id"]').remove();
        
        // Add new bot_id field
        $form.append('<input type="hidden" name="bot_id" value="' + currentBotId + '">');
        
        console.log('Updated bot_id in form ' + formSelector + ' to: ' + currentBotId);
    } else {
        // Remove bot_id field if bot is default
        $form.find('input[name="bot_id"]').remove();
    }
}

// Load content via AJAX
function loadContent() {
    $loading.show();
    $contentList.find('.mxchat-kb-content-item').remove();
    
    const data = {
        action: 'mxchat_get_content_list',
        nonce: mxchatSelector.nonce,
        page: currentPage,
        per_page: 100,
        search: $searchInput.val(),
        post_type: $typeFilter.val(),
        post_status: $statusFilter.val(),
        processed_filter: $processedFilter.val()
    };
    
    //console.log('Loading content for page', currentPage, 'with filters:', data);
    
    $.ajax({
        url: mxchatSelector.ajaxurl,
        data: data,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            $loading.hide();
            
            if (response.success && response.data.items && response.data.items.length > 0) {
                // Store the items directly
                let items = response.data.items;
                
                if (items.length > 0) {
                    renderContentItems(items);
                    renderPagination(parseInt(response.data.current_page), parseInt(response.data.total_pages));
                    
                    // Update state
                    allItems = items;
                    totalPages = parseInt(response.data.total_pages);
                    currentPage = parseInt(response.data.current_page);
                    
                    // Update select all checkbox based on current selection
                    updateSelectAllState();
                } else {
                    displayNoResults($processedFilter.val());
                }
            } else {
                displayNoResults($processedFilter.val());
            }
        },
        error: function(xhr, status, error) {
            $loading.hide();
            console.error('AJAX Error:', status, error);
            $contentList.html('<div class="mxchat-kb-error">Error loading content. Please try again.</div>');
            // Clear pagination on error
            $pagination.empty();
        }
    });
}

// Helper function to display appropriate "no results" message
function displayNoResults(processedStatus) {
    let message = 'No content found matching your criteria.';
    
    if (processedStatus === 'processed') {
        message = 'No content found in knowledge base.';
    } else if (processedStatus === 'unprocessed') {
        message = 'All content is already in knowledge base.';
    }
    
    $contentList.html('<div class="mxchat-kb-no-results">' + message + '</div>');
    // Clear pagination when no results
    $pagination.empty();
}
    
    // Render content items
    function renderContentItems(items) {
        let html = '';

        items.forEach(function(item) {
            const isSelected = selectedItems.has(item.id);
            const isProcessed = item.already_processed;
            const chunkCount = item.chunk_count || 0;

            // Updated badge text - include chunk count if > 1
            let badgeText = 'Not In Knowledge Base';
            if (isProcessed) {
                badgeText = chunkCount > 1 ? `In Knowledge Base (${chunkCount} chunks)` : 'In Knowledge Base';
            }
            const badgeClass = isProcessed ? 'mxchat-kb-processed-badge' : 'mxchat-kb-unprocessed-badge';


            html += `
                <div class="mxchat-kb-content-item ${isProcessed ? 'processed' : ''}" data-id="${item.id}">
                    <div class="mxchat-kb-content-checkbox">
                        <input type="checkbox" id="content-${item.id}" ${isSelected ? 'checked' : ''}>
                    </div>
                    <div class="mxchat-kb-content-details">
                        <div class="mxchat-kb-content-title">
                            <a href="${item.permalink}" target="_blank">${item.title}</a>
                            <span class="${badgeClass}">${badgeText}</span>
                            ${isProcessed ? '<span class="mxchat-kb-last-updated">Last updated: ' + item.processed_date + '</span>' : ''}
                        </div>
                        <div class="mxchat-kb-content-meta">
                            <span class="mxchat-kb-content-type">${item.type}</span>
                            <span class="mxchat-kb-content-date">${item.date}</span>
                            <span class="mxchat-kb-content-words">${item.word_count} words</span>
                        </div>
                        <div class="mxchat-kb-content-excerpt">${item.excerpt}</div>
                    </div>
                </div>
            `;
        });

        $contentList.html(html);
        
        // Add event listeners for checkboxes using delegation for better performance
        $contentList.off('change', 'input[type="checkbox"]').on('change', 'input[type="checkbox"]', function() {
            const $checkbox = $(this);
            const itemId = parseInt($checkbox.closest('.mxchat-kb-content-item').data('id'));
            
            if ($checkbox.is(':checked')) {
                selectedItems.add(itemId);
            } else {
                selectedItems.delete(itemId);
            }
            
            updateSelection();
        });
    }
    
    // Render pagination - FIXED VERSION
    function renderPagination(currentPage, totalPages) {
        // Clear existing pagination first
        $pagination.empty();
        
        // Don't render pagination if only one page
        if (totalPages <= 1) {
            return;
        }
        
        let html = '<div class="mxchat-kb-pagination-links">';
        
        // Previous button
        if (currentPage > 1) {
            html += '<a href="#" class="mxchat-kb-page-link prev" data-page="' + (currentPage - 1) + '">&laquo; Previous</a>';
        }
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, startPage + 4);
        
        for (let i = startPage; i <= endPage; i++) {
            if (i === currentPage) {
                html += '<span class="mxchat-kb-page-current">' + i + '</span>';
            } else {
                html += '<a href="#" class="mxchat-kb-page-link" data-page="' + i + '">' + i + '</a>';
            }
        }
        
        // Next button
        if (currentPage < totalPages) {
            html += '<a href="#" class="mxchat-kb-page-link next" data-page="' + (currentPage + 1) + '">Next &raquo;</a>';
        }
        
        html += '</div>';
        
        $pagination.html(html);
    }
    
    // Handle pagination clicks directly on the document
    $(document).on('click', '.mxchat-kb-page-link', function(e) {
        e.preventDefault();
        const newPage = parseInt($(this).data('page'));
        //console.log('Pagination clicked: changing from page', currentPage, 'to', newPage);
        
        // Only reload if the page actually changed
        if (currentPage !== newPage) {
            currentPage = newPage;
            loadContent();
        }
    });
    
    // Update selection counts and button state
    function updateSelection() {
        const selectedCount = selectedItems.size;
        $selectionCount.text(selectedCount + ' ' + (selectedCount === 1 ? 'selected' : 'selected'));
        $('.mxchat-kb-selected-count').text('(' + selectedCount + ')');

        // Show/hide clear all selections link
        let $clearAllLink = $('.mxchat-kb-clear-all-selections');
        if (selectedCount > 0) {
            if ($clearAllLink.length === 0) {
                $clearAllLink = $('<a href="#" class="mxchat-kb-clear-all-selections" style="margin-left: 10px; font-size: 12px; color: var(--mxch-error, #dc2626);">Clear all</a>');
                $selectionCount.after($clearAllLink);
                $clearAllLink.on('click', function(e) {
                    e.preventDefault();
                    selectedItems.clear();
                    $('.mxchat-kb-content-item input[type="checkbox"]').prop('checked', false);
                    updateSelection();
                });
            }
            $clearAllLink.show();
        } else {
            $clearAllLink.hide();
        }

        // Determine if any selected items are already processed
        const hasProcessedItems = Array.from(selectedItems).some(id => {
            const item = allItems.find(item => item.id === id);
            return item && item.already_processed;
        });

        if (selectedCount > 0) {
            $processButton.prop('disabled', false);

            // Update button text based on selection
            if (hasProcessedItems && selectedCount === 1) {
                $processButton.text('Update Selected Content (1)').addClass('update-mode');
            } else if (hasProcessedItems && selectedCount > 1) {
                $processButton.text('Process/Update Selected (' + selectedCount + ')').addClass('mixed-mode');
            } else {
                $processButton.text('Process Selected Content (' + selectedCount + ')').removeClass('update-mode mixed-mode');
            }
        } else {
            $processButton.prop('disabled', true);
            $processButton.text('Process Selected Content').removeClass('update-mode mixed-mode');
            $('.mxchat-kb-selected-count').text('(0)');
        }

        updateSelectAllState();
    }
    
    // Update "Select All" checkbox state
    function updateSelectAllState() {
        const availableItems = allItems.length;
        const selectedAvailableItems = allItems.filter(item => selectedItems.has(item.id)).length;
        
        if (availableItems === 0) {
            $selectAll.prop('checked', false);
            $selectAll.prop('disabled', true);
        } else if (selectedAvailableItems === availableItems) {
            $selectAll.prop('checked', true);
        } else {
            $selectAll.prop('checked', false);
        }
    }
    
    // Handle Select All checkbox
    $selectAll.on('change', function() {
        const isChecked = $(this).is(':checked');
        
        $contentList.find('.mxchat-kb-content-item input[type="checkbox"]').each(function() {
            const $checkbox = $(this);
            const $item = $checkbox.closest('.mxchat-kb-content-item');
            const itemId = parseInt($item.data('id'));
            
            $checkbox.prop('checked', isChecked);
            
            if (isChecked) {
                selectedItems.add(itemId);
            } else {
                selectedItems.delete(itemId);
            }
        });
        
        updateSelection();
    });
    
    // Handle search input
    let searchTimer;
    $searchInput.on('keyup', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            currentPage = 1; // Reset to first page on new search
            loadContent();
        }, 500);
    });
    
    // Handle filter changes
    $typeFilter.add($statusFilter).add($processedFilter).on('change', function() {
        currentPage = 1; // Reset to first page on filter change
        selectedItems.clear(); // Clear selection when filter changes
        loadContent();
    });
    
// Process selected content
$processButton.on('click', function() {
    if (selectedItems.size === 0) {
        return;
    }
    
    const $button = $(this);
    $button.prop('disabled', true);
    
    // Update button text based on mode
    if ($button.hasClass('update-mode')) {
        $button.text('Updating...');
    } else if ($button.hasClass('mixed-mode')) {
        $button.text('Processing/Updating...');
    } else {
        $button.text('Processing...');
    }
    
    // Convert selected items to array
    const selectedPostIds = Array.from(selectedItems);
    const totalToProcess = selectedPostIds.length;
    let processed = 0;
    let updated = 0;
    let failed = 0;
    const results = {
        success: [],
        updated: [],
        failed: []
    };
    
    // UPDATED: Get current bot_id for WordPress content processing
    const currentBotId = $('#mxchat-bot-selector').val();
    
    // Flag to track if processing should be aborted
    let abortProcessing = false;
    let currentXHR = null;

    // Create a modal to show progress with stop button
    const $progressModal = $('<div class="mxchat-kb-processing-overlay">' +
        '<div class="mxchat-kb-processing-content">' +
        '<h3>Processing Content</h3>' +
        '<p class="mxchat-kb-processing-status">Processing 1 of ' + totalToProcess + '...</p>' +
        '<div class="mxchat-kb-progress-bar"><div class="mxchat-kb-progress-fill" style="width: 0%"></div></div>' +
        '<p class="mxchat-kb-current-item"></p>' +
        '<button type="button" class="mxchat-kb-stop-processing mxch-btn mxch-btn-secondary" style="margin-top: 15px;">' +
        '<span class="dashicons dashicons-controls-pause" style="margin-right: 5px;"></span>Stop Processing</button>' +
        '</div>' +
        '</div>');

    $('body').append($progressModal);

    // Handle stop button click
    $progressModal.find('.mxchat-kb-stop-processing').on('click', function() {
        abortProcessing = true;
        $(this).prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-right: 5px;"></span>Stopping...');
        if (currentXHR) {
            currentXHR.abort();
        }
    });
    
    // Process posts one by one
    function processNext(index) {
        // Check if processing was aborted
        if (abortProcessing) {
            finishProcessing(true); // Pass true to indicate abort
            return;
        }

        if (index >= selectedPostIds.length) {
            // All done
            finishProcessing();
            return;
        }

        const postId = selectedPostIds[index];
        const percent = Math.round((index / totalToProcess) * 100);
        const item = allItems.find(item => item.id === postId);
        const isUpdate = item && item.already_processed;

        // Update progress UI
        $progressModal.find('.mxchat-kb-processing-status')
            .text((isUpdate ? 'Updating' : 'Processing') + ' ' + (index + 1) + ' of ' + totalToProcess + '...');
        $progressModal.find('.mxchat-kb-progress-fill').css('width', percent + '%');

        // UPDATED: Prepare AJAX data with bot_id
        const ajaxData = {
            action: 'mxchat_process_selected_content',
            nonce: mxchatSelector.nonce,
            post_ids: [postId],
            is_update: isUpdate
        };

        // Add bot_id if multi-bot is active and not default
        if (currentBotId && currentBotId !== 'default') {
            ajaxData.bot_id = currentBotId;
        }

        // Make AJAX request for this post (store reference for potential abort)
        currentXHR = $.ajax({
            url: mxchatSelector.ajaxurl,
            method: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (isUpdate) {
                        updated++;
                        results.updated.push({
                            id: postId,
                            title: response.data.title || ('ID: ' + postId)
                        });
                    } else {
                        processed++;
                        results.success.push({
                            id: postId,
                            title: response.data.title || ('ID: ' + postId)
                        });
                    }
                    
                    $progressModal.find('.mxchat-kb-current-item')
                        .text('Successfully ' + (isUpdate ? 'updated' : 'processed') + ': ' + response.data.title);
                } else {
                    failed++;
                    results.failed.push({
                        id: postId,
                        error: response.data || 'Unknown error'
                    });
                    
                    $progressModal.find('.mxchat-kb-current-item')
                        .text('Failed to ' + (isUpdate ? 'update' : 'process') + ' ID: ' + postId);
                }
                
                // Process next post
                setTimeout(function() {
                    processNext(index + 1);
                }, 500); // Small delay between requests
            },
            error: function(xhr, status, error) {
                failed++;
                results.failed.push({
                    id: postId,
                    error: error || 'Server error'
                });
                
                $progressModal.find('.mxchat-kb-current-item')
                    .text('Error ' + (isUpdate ? 'updating' : 'processing') + ' ID: ' + postId);
                
                // Process next post
                setTimeout(function() {
                    processNext(index + 1);
                }, 500);
            }
        });
    }
    
    // Function to finish processing and show results
    function finishProcessing(wasAborted) {
        // Remove progress modal
        $progressModal.remove();

        // Determine notification type based on results
        let notificationClass = 'success';
        if (wasAborted) {
            notificationClass = processed > 0 || updated > 0 ? 'warning' : 'info';
        } else if (failed > 0) {
            notificationClass = processed > 0 || updated > 0 ? 'warning' : 'error';
        }

        // Create summary message
        let resultHTML = '<div class="mxchat-kb-notification ' + notificationClass + '">' +
                       '<h4>';

        if (wasAborted) {
            resultHTML += 'Processing stopped. ';
            if (processed > 0 || updated > 0) {
                resultHTML += 'Completed ' + (processed + updated) + ' of ' + totalToProcess + ' items before stopping';
            } else {
                resultHTML += 'No items were processed before stopping';
            }
        } else if (processed > 0 && updated > 0) {
            resultHTML += 'Processed ' + processed + ' new items and updated ' + updated + ' existing items';
        } else if (processed > 0) {
            resultHTML += 'Processed ' + processed + ' items successfully';
        } else if (updated > 0) {
            resultHTML += 'Updated ' + updated + ' items successfully';
        } else {
            resultHTML += 'No items were processed successfully';
        }

        if (failed > 0 && !wasAborted) {
            resultHTML += ' with ' + failed + ' failures';
        }
        
        resultHTML += '</h4>';
        
        // Add details if there were failures
        if (failed > 0) {
            resultHTML += '<div class="mxchat-kb-results-details">';
            resultHTML += '<h5>Failed Items:</h5><ul>';
            
            results.failed.forEach(function(item) {
                resultHTML += '<li><strong>ID: ' + item.id + '</strong>: ' + item.error + '</li>';
            });
            
            resultHTML += '</ul></div>';
        }
        
        resultHTML += '</div>';
        
        // Show results in modal
        $modal.find('.mxchat-kb-modal-content').prepend($(resultHTML));
        
        // Clear selection
        selectedItems.clear();
        updateSelection();
        
        // Enable button
        $button.prop('disabled', false)
               .text('Process Selected Content')
               .removeClass('update-mode mixed-mode');
        $('.mxchat-kb-selected-count').text('(0)');

        // Only reload if there were successful operations
        if (processed > 0 || updated > 0) {
            // Refresh the knowledge base table with properly grouped entries
            if (typeof window.refreshKnowledgeBaseTable === 'function') {
                window.refreshKnowledgeBaseTable();
            }

            // Reload content list to update "already processed" status
            setTimeout(function() {
                loadContent();
            }, 1000);
        }
    }
    
    // Start processing the first post
    processNext(0);
});

    // Initialize - Set WordPress as the active option by default
    $('.mxchat-import-box[data-option="wordpress"]').addClass('active');
});

// Navigation functionality for Knowledge Base page
jQuery(document).ready(function($) {

    // Hook into the new navigation system using .mxch-nav-link
    $(document).on('click', '.mxch-nav-link[data-target], .mxch-mobile-nav-link[data-target]', function() {
        var target = $(this).data('target');

        // Initialize Pinecone functionality when Pinecone section is activated
        if (target === 'pinecone') {
            setTimeout(function() {
                if (typeof initPineconeFeatures === 'function') {
                    initPineconeFeatures();
                }
            }, 100);
        }

        // Initialize OpenAI Vector Store functionality when Vector Store section is activated
        if (target === 'openai-vectorstore') {
            setTimeout(function() {
                if (typeof initVectorStoreFeatures === 'function') {
                    initVectorStoreFeatures();
                }
            }, 100);
        }

        // Check if Pinecone was changed and we're going to import section
        if (target === 'import' && sessionStorage.getItem('mxchat_pinecone_changed') === 'true') {
            sessionStorage.removeItem('mxchat_pinecone_changed');

            // Show refresh notice
            var $knowledgeCard = $('#import .mxch-card').eq(1);
            if ($knowledgeCard.length > 0 && $knowledgeCard.find('.notice-warning').length === 0) {
                var refreshNotice = $('<div class="notice notice-warning" style="margin: 15px 0; padding: 10px 15px;">' +
                    '<p style="margin: 0;">' +
                    '<span class="dashicons dashicons-info" style="color: #f0ad4e; margin-right: 5px;"></span>' +
                    'Database settings have changed. ' +
                    '<a href="#" onclick="location.reload(); return false;" style="font-weight: bold;">Click here to refresh</a> to see the updated knowledge base.' +
                    '</p></div>');

                $knowledgeCard.prepend(refreshNotice);
            }
        }
    });

    // Also check on page load if we're already on one of these sections
    setTimeout(function() {
        if ($('#pinecone').is(':visible') || $('#pinecone.active').length > 0) {
            if (typeof initPineconeFeatures === 'function') {
                initPineconeFeatures();
            }
        }

        if ($('#openai-vectorstore').is(':visible') || $('#openai-vectorstore.active').length > 0) {
            if (typeof initVectorStoreFeatures === 'function') {
                initVectorStoreFeatures();
            }
        }
    }, 200);
});

// Pinecone and Vector Store functionality - global functions
var initPineconeFeatures, initVectorStoreFeatures;

(function($) {

    // Helper function to update sidebar badge when integration is toggled
    function updateSidebarBadge(section, isActive) {
        // Find the nav item for this section (both desktop and mobile)
        var $desktopNavItem = $('.mxch-nav-item[data-section="' + section + '"] .mxch-nav-link');
        var $mobileNavItem = $('.mxch-mobile-nav-link[data-target="' + section + '"]');

        // Remove existing badge if any
        $desktopNavItem.find('.mxch-active-badge').remove();
        $mobileNavItem.find('.mxch-active-badge').remove();

        // Add badge if active
        if (isActive) {
            var badgeHtml = '<span class="mxch-nav-link-badge mxch-active-badge">Active</span>';
            $desktopNavItem.append(badgeHtml);
            $mobileNavItem.append(badgeHtml);
        }
    }

    // Pinecone functionality
    initPineconeFeatures = function() {
        // Check for either old or new section ID
        if ($('#pinecone').length === 0 && $('#mxchat-kb-tab-pinecone').length === 0) {
            return;
        }

        initPineconeToggle();
        initPineconeConnectionTest();
        checkPineconeCompatibility();
    };

    function initPineconeToggle() {
        // Remove any existing handlers to prevent duplicates
        var $toggleInput = $('input[name="mxchat_pinecone_addon_options[mxchat_use_pinecone]"]');
        $toggleInput.off('change.pineconeToggle');

        // Ensure the success notice exists in the settings div (add if not present)
        // Check for both the JS-added class and any existing PHP-rendered success notice
        var settingsDiv = $('.mxchat-pinecone-settings');
        if (settingsDiv.length > 0 && settingsDiv.find('.mxch-notice-success').length === 0) {
            var successNotice = $('<div class="mxch-notice mxch-notice-success mxchat-pinecone-enabled-notice" style="margin-bottom: 20px; display: none;">' +
                '<svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                '<span>Pinecone is enabled. All new knowledge base content will be stored in Pinecone.</span>' +
                '</div>');
            settingsDiv.prepend(successNotice);
        }

        // Add the toggle handler for UI only (auto-save will handle the actual saving)
        $toggleInput.on('change.pineconeToggle', function() {
            var $checkbox = $(this);
            var isChecked = $checkbox.is(':checked');
            var settingsDiv = $('.mxchat-pinecone-settings');
            var enabledNotice = settingsDiv.find('.mxchat-pinecone-enabled-notice, .mxch-notice-success');

            // Update the UI immediately
            if (isChecked) {
                settingsDiv.slideDown(300);
                enabledNotice.slideDown(300);
            } else {
                enabledNotice.slideUp(300);
                settingsDiv.slideUp(300);
            }

            // Update sidebar badge for Pinecone
            updateSidebarBadge('pinecone', isChecked);
        });

        // Set initial state based on current checkbox value
        var currentToggle = $('input[name="mxchat_pinecone_addon_options[mxchat_use_pinecone]"]');
        if (currentToggle.length > 0) {
            var settingsDiv = $('.mxchat-pinecone-settings');
            var enabledNotice = settingsDiv.find('.mxchat-pinecone-enabled-notice, .mxch-notice-success');
            if (currentToggle.is(':checked')) {
                settingsDiv.show();
                enabledNotice.show();
            } else {
                settingsDiv.hide();
                enabledNotice.hide();
            }
        }
    }
    
    function initPineconeConnectionTest() {
        $('#test-pinecone-connection').off('click.pinecone');
        
        $('#test-pinecone-connection').on('click.pinecone', function() {
            var button = $(this);
            var resultDiv = $('#connection-test-result');
            
            var apiKey = $('#mxchat_pinecone_api_key').val();
            var host = $('#mxchat_pinecone_host').val();
            var index = $('#mxchat_pinecone_index').val();
            
            if (!apiKey || !host || !index) {
                resultDiv.html('<div class="notice notice-error"><p>Please fill in all required fields first.</p></div>').show();
                return;
            }
            
            button.prop('disabled', true).text('Testing...');
            resultDiv.hide();
            
            var ajaxUrl = (typeof mxchatPromptsAdmin !== 'undefined') ? mxchatPromptsAdmin.ajax_url : ajaxurl;
            var nonce = (typeof mxchatPromptsAdmin !== 'undefined') ? mxchatPromptsAdmin.prompts_setting_nonce : 
                       (typeof mxchatAdmin !== 'undefined') ? mxchatAdmin.setting_nonce : '';
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mxchat_test_pinecone_connection',
                    _ajax_nonce: nonce,
                    api_key: apiKey,
                    host: host,
                    index_name: index
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.html('<div class="notice notice-success"><p><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</p></div>');
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</p></div>');
                    }
                    resultDiv.show();
                },
                error: function() {
                    resultDiv.html('<div class="notice notice-error"><p>Connection test failed. Please check your settings.</p></div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Connection');
                }
            });
        });
    }
    
    function checkPineconeCompatibility() {
        if ($('.mxchat-pinecone-compatibility-notice').length > 0) {
            return;
        }
        
        var hasOldAddon = $('body').hasClass('mxchat-pinecone-addon-active') || 
                         $('.pcm-card').length > 0;
        
        if (hasOldAddon) {
            var compatibilityNotice = $(`
                <div class="notice notice-info mxchat-pinecone-compatibility-notice">
                    <p><strong>Pinecone Integration Notice:</strong> We've detected you have the Pinecone add-on installed. 
                    Pinecone functionality is now built into the core plugin. You can safely deactivate the separate 
                    Pinecone add-on after confirming your settings are migrated below.</p>
                </div>
            `);
            
            $('#mxchat-kb-tab-pinecone .mxchat-card').prepend(compatibilityNotice);
            
            migratePineconeSettings();
        }
    }
    
    function migratePineconeSettings() {
        if (typeof ajaxurl !== 'undefined') {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mxchat_migrate_pinecone_settings',
                    _ajax_nonce: (typeof mxchatAdmin !== 'undefined') ? mxchatAdmin.setting_nonce : ''
                },
                success: function(response) {
                    if (response.success && response.data.migrated) {
                        location.reload();
                    }
                },
                error: function() {
                    //console.log('Pinecone settings migration not available');
                }
            });
        }
    }

    // ============================================
    // OpenAI Vector Store functionality
    // ============================================
    initVectorStoreFeatures = function() {
        if ($('#openai-vectorstore').length === 0) {
            return;
        }

        initVectorStoreToggle();
    };

    function initVectorStoreToggle() {
        // Remove any existing handlers to prevent duplicates
        var $toggleInput = $('input[name="mxchat_openai_vectorstore_options[mxchat_use_openai_vectorstore]"]');
        $toggleInput.off('change.vectorstoreToggle');

        // Ensure the success notice exists in the settings div (add if not present)
        // Check for both the JS-added class and any existing PHP-rendered success notice
        var settingsDiv = $('.mxchat-vectorstore-settings');
        if (settingsDiv.length > 0 && settingsDiv.find('.mxch-notice-success').length === 0) {
            var successNotice = $('<div class="mxch-notice mxch-notice-success mxchat-vectorstore-enabled-notice" style="margin-bottom: 20px; display: none;">' +
                '<svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                '<span>OpenAI Vector Store is enabled. Queries will search your Vector Store for relevant content.</span>' +
                '</div>');
            settingsDiv.prepend(successNotice);
        }

        // Add the toggle handler for UI only (form submit will handle the actual saving)
        $toggleInput.on('change.vectorstoreToggle', function() {
            var $checkbox = $(this);
            var isChecked = $checkbox.is(':checked');
            var settingsDiv = $('.mxchat-vectorstore-settings');
            var enabledNotice = settingsDiv.find('.mxchat-vectorstore-enabled-notice, .mxch-notice-success');

            // Update the UI immediately
            if (isChecked) {
                settingsDiv.slideDown(300);
                enabledNotice.slideDown(300);
            } else {
                enabledNotice.slideUp(300);
                settingsDiv.slideUp(300);
            }

            // Update sidebar badge for OpenAI Vector Store
            updateSidebarBadge('openai-vectorstore', isChecked);
        });

        // Set initial state based on current checkbox value
        var currentToggle = $('input[name="mxchat_openai_vectorstore_options[mxchat_use_openai_vectorstore]"]');
        if (currentToggle.length > 0) {
            var settingsDiv = $('.mxchat-vectorstore-settings');
            var enabledNotice = settingsDiv.find('.mxchat-vectorstore-enabled-notice, .mxch-notice-success');
            if (currentToggle.is(':checked')) {
                settingsDiv.show();
                enabledNotice.show();
            } else {
                settingsDiv.hide();
                enabledNotice.hide();
            }
        }
    }

})(jQuery);