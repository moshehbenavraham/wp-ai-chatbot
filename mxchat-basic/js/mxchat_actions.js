/**
 * MxChat Actions Page JavaScript - v2.0
 * Split-panel layout with action list and editor
 */
jQuery(document).ready(function($) {
    // ==========================================================================
    // Sidebar Navigation
    // ==========================================================================

    // Desktop sidebar navigation
    $('.mxch-nav-link').on('click', function(e) {
        e.preventDefault();
        const target = $(this).data('target');

        // Update active states
        $('.mxch-nav-link').removeClass('active');
        $(this).addClass('active');

        // Show target section
        $('.mxch-section').removeClass('active');
        $('#' + target).addClass('active');

        // Also update mobile nav if open
        $('.mxch-mobile-nav-link').removeClass('active');
        $('.mxch-mobile-nav-link[data-target="' + target + '"]').addClass('active');

        // Load actions when switching to all-actions
        if (target === 'all-actions' && !actionsLoaded) {
            loadActionList(1, '', '');
        }
    });

    // Mobile menu toggle
    $('.mxch-mobile-menu-btn').on('click', function() {
        $('.mxch-mobile-menu').addClass('open');
        $('.mxch-mobile-overlay').addClass('open');
    });

    // Close mobile menu
    $('.mxch-mobile-menu-close, .mxch-mobile-overlay').on('click', function() {
        $('.mxch-mobile-menu').removeClass('open');
        $('.mxch-mobile-overlay').removeClass('open');
    });

    // Mobile navigation
    $('.mxch-mobile-nav-link').on('click', function(e) {
        e.preventDefault();
        const target = $(this).data('target');

        $('.mxch-mobile-nav-link').removeClass('active');
        $(this).addClass('active');

        $('.mxch-section').removeClass('active');
        $('#' + target).addClass('active');

        $('.mxch-nav-link').removeClass('active');
        $('.mxch-nav-link[data-target="' + target + '"]').addClass('active');

        $('.mxch-mobile-menu').removeClass('open');
        $('.mxch-mobile-overlay').removeClass('open');
    });

    // Quick action buttons
    $('.mxch-quick-action-btn[data-action="view-actions"]').on('click', function() {
        $('.mxch-nav-link[data-target="all-actions"]').trigger('click');
    });

    $('#mxch-add-action-dashboard-btn').on('click', function() {
        $('.mxch-nav-link[data-target="all-actions"]').trigger('click');
        setTimeout(function() {
            openActionEditor();
        }, 100);
    });

    // ==========================================================================
    // Mobile Detection & Panel Management
    // ==========================================================================

    function isMobile() {
        return window.innerWidth <= 782;
    }

    function showMobileDetailPanel() {
        if (isMobile()) {
            $('.mxch-action-detail-panel').addClass('mobile-active');
            $('body').addClass('mxch-mobile-panel-open');
        }
    }

    function hideMobileDetailPanel() {
        $('.mxch-action-detail-panel').removeClass('mobile-active');
        $('body').removeClass('mxch-mobile-panel-open');
    }

    // Mobile back button handler
    $(document).on('click', '.mxch-mobile-back-btn', function(e) {
        e.preventDefault();
        hideMobileDetailPanel();
        resetEditorPanel();
        $('.mxch-action-item').removeClass('active');
        currentActionId = null;
    });

    // ==========================================================================
    // Action List - Split Panel
    // ==========================================================================

    let currentPage = 1;
    const perPage = 50;
    let totalPages = 1;
    let currentActionId = null;
    let actionsLoaded = false;
    let selectedActions = new Set();
    let currentSortOrder = 'desc';
    let currentFilter = '';

    // Load action list on page load
    loadActionList(1, '', '');

    // Search functionality with debounce
    let searchTimeout;
    $('#mxch-search-actions').on('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val().toLowerCase();

        searchTimeout = setTimeout(function() {
            currentPage = 1;
            loadActionList(currentPage, searchTerm, currentFilter);
        }, 300);
    });

    // Filter by type
    $('#mxch-filter-actions').on('change', function() {
        currentFilter = $(this).val();
        currentPage = 1;
        loadActionList(currentPage, $('#mxch-search-actions').val(), currentFilter);
    });

    // Refresh button
    $('#mxch-refresh-actions').on('click', function() {
        const $btn = $(this);
        $btn.addClass('spinning');
        loadActionList(currentPage, $('#mxch-search-actions').val(), currentFilter);
        setTimeout(() => $btn.removeClass('spinning'), 500);
    });

    // ==========================================================================
    // Bulk Selection & Actions
    // ==========================================================================

    // Select all checkbox
    $('#mxch-select-all-actions').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.mxch-action-checkbox').prop('checked', isChecked);

        if (isChecked) {
            $('.mxch-action-item').each(function() {
                selectedActions.add($(this).data('action-id'));
                $(this).addClass('selected');
            });
            $('#mxch-action-list').addClass('selection-mode');
        } else {
            selectedActions.clear();
            $('.mxch-action-item').removeClass('selected');
            $('#mxch-action-list').removeClass('selection-mode');
        }

        updateSelectionUI();
    });

    // Update selection UI
    function updateSelectionUI() {
        const count = selectedActions.size;
        const $countEl = $('#mxch-selected-action-count');
        const $deleteBtn = $('#mxch-delete-selected-actions');

        if (count > 0) {
            $countEl.text(count + ' selected').addClass('has-selection');
            $deleteBtn.prop('disabled', false);
            $('#mxch-action-list').addClass('selection-mode');
        } else {
            $countEl.removeClass('has-selection');
            $deleteBtn.prop('disabled', true);
            $('#mxch-action-list').removeClass('selection-mode');
        }

        // Update select all checkbox state
        const totalItems = $('.mxch-action-checkbox').length;
        const checkedItems = $('.mxch-action-checkbox:checked').length;
        $('#mxch-select-all-actions').prop('checked', totalItems > 0 && checkedItems === totalItems);
        $('#mxch-select-all-actions').prop('indeterminate', checkedItems > 0 && checkedItems < totalItems);
    }

    // Delete selected button
    $('#mxch-delete-selected-actions').on('click', function() {
        const count = selectedActions.size;
        if (count === 0) return;

        if (!confirm(mxchActionsData.i18n.confirmBulkDelete)) {
            return;
        }

        deleteMultipleActions(Array.from(selectedActions));
    });

    // Delete multiple actions
    function deleteMultipleActions(actionIds) {
        showLoading();

        $.ajax({
            url: mxchActionsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_bulk_delete_actions',
                action_ids: actionIds,
                security: mxchActionsData.deleteNonce
            },
            success: function(response) {
                hideLoading();

                if (response.success) {
                    // Clear selection
                    selectedActions.clear();
                    $('#mxch-select-all-actions').prop('checked', false);
                    updateSelectionUI();

                    // If current action was deleted, reset panel
                    if (actionIds.includes(currentActionId)) {
                        currentActionId = null;
                        resetEditorPanel();
                    }

                    // Reload list
                    loadActionList(currentPage, $('#mxch-search-actions').val(), currentFilter);
                } else {
                    alert(response.data || mxchActionsData.i18n.error);
                }
            },
            error: function() {
                hideLoading();
                alert(mxchActionsData.i18n.error);
            }
        });
    }

    // Load action list function
    function loadActionList(page, searchTerm, filter) {
        const $container = $('#mxch-action-list');
        $container.html('<div class="mxch-list-loading"><span class="spinner is-active"></span></div>');

        $.ajax({
            url: mxchActionsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_fetch_actions_list',
                page: page,
                per_page: perPage,
                search: searchTerm,
                callback_filter: filter,
                sort_order: currentSortOrder,
                security: mxchActionsData.nonce
            },
            success: function(response) {
                actionsLoaded = true;

                if (response.success && response.data.actions && response.data.actions.length > 0) {
                    renderActionList(response.data.actions);
                    currentPage = response.data.page;
                    totalPages = response.data.total_pages;
                    updateActionCount(response.data.showing_start, response.data.showing_end, response.data.total_actions);
                    renderPagination(response.data.page, response.data.total_pages, searchTerm, filter);
                } else {
                    $container.html('<div class="mxch-list-empty"><p>No actions found</p></div>');
                    updateActionCount(0, 0, 0);
                    $('#mxch-actions-pagination').html('');
                }
            },
            error: function() {
                $container.html('<div class="mxch-list-empty"><p>Error loading actions</p></div>');
            }
        });
    }

    // Render action list items
    function renderActionList(actions) {
        const $container = $('#mxch-action-list');
        let html = '';

        actions.forEach(function(action) {
            const isActive = action.id == currentActionId ? ' active' : '';
            const isSelected = selectedActions.has(action.id) ? ' selected' : '';
            const isChecked = selectedActions.has(action.id) ? ' checked' : '';
            const isDisabled = !action.enabled ? ' disabled' : '';
            const statusClass = action.enabled ? 'enabled' : 'disabled';
            const statusText = action.enabled ? 'Enabled' : 'Disabled';

            html += `
                <div class="mxch-action-item${isActive}${isSelected}${isDisabled}"
                     data-action-id="${action.id}"
                     data-label="${escapeHtml(action.label)}"
                     data-phrases="${escapeHtml(action.phrases)}"
                     data-threshold="${action.threshold}"
                     data-callback="${escapeHtml(action.callback_function)}"
                     data-enabled="${action.enabled ? '1' : '0'}"
                     data-enabled-bots='${escapeHtml(JSON.stringify(action.enabled_bots))}'>
                    <input type="checkbox" class="mxch-action-checkbox"${isChecked}>
                    <div class="mxch-action-icon">
                        <span class="dashicons dashicons-${escapeHtml(action.icon || 'admin-generic')}"></span>
                    </div>
                    <div class="mxch-action-info">
                        <div class="mxch-action-name">${escapeHtml(action.label)}</div>
                        <div class="mxch-action-type-label">${escapeHtml(action.callback_label)}</div>
                    </div>
                    <div class="mxch-action-meta">
                        <span class="mxch-action-status ${statusClass}">${statusText}</span>
                    </div>
                </div>
            `;
        });

        $container.html(html);

        // Attach checkbox handlers
        $('.mxch-action-checkbox').on('click', function(e) {
            e.stopPropagation();
            const $item = $(this).closest('.mxch-action-item');
            const actionId = $item.data('action-id');

            if ($(this).is(':checked')) {
                selectedActions.add(actionId);
                $item.addClass('selected');
            } else {
                selectedActions.delete(actionId);
                $item.removeClass('selected');
            }

            updateSelectionUI();
        });

        // Attach click handlers for selecting action
        $('.mxch-action-item').on('click', function(e) {
            if ($(e.target).is('.mxch-action-checkbox')) return;

            const actionId = $(this).data('action-id');
            selectAction(actionId, $(this));

            // Update active state
            $('.mxch-action-item').removeClass('active');
            $(this).addClass('active');
        });

        // Update selection UI after render
        updateSelectionUI();
    }

    // Update action count display
    function updateActionCount(start, end, total) {
        if (total === 0) {
            $('#mxch-action-count').text('0 actions');
        } else {
            $('#mxch-action-count').text(`${start}-${end} / ${total} actions`);
        }
    }

    // Render pagination
    function renderPagination(currentPage, totalPages, searchTerm, filter) {
        const $container = $('#mxch-actions-pagination');

        if (totalPages <= 1) {
            $container.html('');
            return;
        }

        let html = '<div class="mxch-pagination-btns">';

        if (currentPage > 1) {
            html += `<button class="mxch-page-btn" data-page="${currentPage - 1}">&laquo;</button>`;
        }

        html += `<span class="mxch-page-info">${currentPage} / ${totalPages}</span>`;

        if (currentPage < totalPages) {
            html += `<button class="mxch-page-btn" data-page="${currentPage + 1}">&raquo;</button>`;
        }

        html += '</div>';
        $container.html(html);

        // Pagination click handlers
        $('.mxch-page-btn').on('click', function() {
            const pageNum = $(this).data('page');
            loadActionList(pageNum, searchTerm, filter);
        });
    }

    // ==========================================================================
    // Action Editor Panel
    // ==========================================================================

    // Select and view an action
    function selectAction(actionId, $item) {
        currentActionId = actionId;

        // Get data from item attributes
        const data = {
            id: actionId,
            label: $item.data('label'),
            phrases: $item.data('phrases'),
            threshold: $item.data('threshold'),
            callback_function: $item.data('callback'),
            enabled: $item.data('enabled') == '1',
            enabled_bots: $item.data('enabled-bots') || ['default']
        };

        showActionView(data);
    }

    // Show action view (details)
    function showActionView(action) {
        $('#mxch-action-empty').hide();
        $('#mxch-action-editor').show();

        // Hide editor steps, show view
        $('.mxch-editor-step').removeClass('active');
        $('#mxch-action-view').addClass('active');

        // Show detail panel on mobile
        showMobileDetailPanel();

        // Populate view
        $('#mxch-view-title').text(action.label);
        $('#mxch-view-label').text(action.label);

        // Get callback label from filter dropdown
        const callbackLabel = $('#mxch-filter-actions option[value="' + action.callback_function + '"]').text() || action.callback_function;
        $('#mxch-view-type').text(callbackLabel);

        // Get icon from type grid
        const $typeCard = $('.mxch-type-card[data-value="' + action.callback_function + '"]');
        const iconClass = $typeCard.find('.dashicons').attr('class') || 'dashicons dashicons-admin-generic';
        $('#mxch-view-icon').html('<span class="' + iconClass + '"></span>');
        $('#mxch-detail-icon').html('<span class="' + iconClass + '"></span>');

        // Phrases
        const phrases = action.phrases.split(',').map(p => p.trim()).filter(p => p);
        let phrasesHtml = '';
        phrases.forEach(function(phrase) {
            phrasesHtml += '<span class="mxch-phrase-tag">' + escapeHtml(phrase) + '</span>';
        });
        $('#mxch-view-phrases').html(phrasesHtml);

        // Settings
        $('#mxch-view-threshold').text(action.threshold + '%');

        const statusText = action.enabled ? 'Enabled' : 'Disabled';
        const statusClass = action.enabled ? 'mxch-status-enabled' : 'mxch-status-disabled';
        $('#mxch-view-status').text(statusText).removeClass('mxch-status-enabled mxch-status-disabled').addClass(statusClass);

        // Toggle switch
        $('#mxch-action-enabled-toggle').prop('checked', action.enabled);

        // Bots
        let botsHtml = '';
        const enabledBots = Array.isArray(action.enabled_bots) ? action.enabled_bots : ['default'];
        enabledBots.forEach(function(bot) {
            const botName = bot === 'default' ? 'Default Bot' : bot;
            botsHtml += '<span class="mxch-bot-tag">' + escapeHtml(botName) + '</span>';
        });
        $('#mxch-view-bots').html(botsHtml);

        // Store current action data for editing
        $('#mxch-action-view').data('current-action', action);
    }

    // Open action editor (for creating new)
    function openActionEditor() {
        currentActionId = null;

        $('#mxch-action-empty').hide();
        $('#mxch-action-editor').show();

        // Show step 1
        $('.mxch-editor-step').removeClass('active');
        $('#mxch-editor-step-1').addClass('active');

        // Reset form
        resetForm();

        // Show detail panel on mobile
        showMobileDetailPanel();
    }

    // Open action editor for editing
    function openActionEditorForEdit(action) {
        currentActionId = action.id;

        $('#mxch-action-empty').hide();
        $('#mxch-action-editor').show();

        // Go directly to step 2
        $('.mxch-editor-step').removeClass('active');
        $('#mxch-editor-step-2').addClass('active');

        // Populate form
        $('#mxch-action-id').val(action.id);
        $('#mxch-callback-function').val(action.callback_function);
        $('#mxch-form-mode').val('edit');
        $('#mxch-action-label').val(action.label);
        $('#mxch-action-phrases').val(action.phrases);
        $('#mxch-action-threshold').val(action.threshold);
        $('#mxch-threshold-value').text(action.threshold + '%');

        // Set selected type display
        const $typeCard = $('.mxch-type-card[data-value="' + action.callback_function + '"]');
        const iconHtml = $typeCard.find('.mxch-type-icon').html();
        const typeLabel = $typeCard.data('label');
        const typeDesc = $typeCard.find('p').text();

        $('#mxch-selected-type-icon').html(iconHtml);
        $('#mxch-selected-type-label').text(typeLabel);
        $('#mxch-selected-type-desc').text(typeDesc);
        $('#mxch-config-title').text('Edit Action');

        // Set enabled bots
        const enabledBots = Array.isArray(action.enabled_bots) ? action.enabled_bots : ['default'];
        $('#mxch-bot-selector input[type="checkbox"]').each(function() {
            $(this).prop('checked', enabledBots.includes($(this).val()));
        });

        // Update save button text
        $('#mxch-save-action').text('Update Action');
    }

    // Reset form
    function resetForm() {
        $('#mxch-action-id').val('');
        $('#mxch-callback-function').val('');
        $('#mxch-form-mode').val('add');
        $('#mxch-action-label').val('');
        $('#mxch-action-phrases').val('');
        $('#mxch-action-threshold').val(85);
        $('#mxch-threshold-value').text('85%');
        $('#mxch-config-title').text('Configure Action');
        $('#mxch-save-action').text('Save Action');

        // Reset bot selection
        $('#mxch-bot-selector input[type="checkbox"]').prop('checked', false);
        $('#mxch-bot-selector input[value="default"]').prop('checked', true);

        // Reset type selection
        $('.mxch-type-card').removeClass('selected');
    }

    // Reset editor panel to empty state
    function resetEditorPanel() {
        $('#mxch-action-editor').hide();
        $('#mxch-action-empty').show();
        $('.mxch-editor-step').removeClass('active');

        // Hide mobile panel
        hideMobileDetailPanel();
    }

    // ==========================================================================
    // Editor Event Handlers
    // ==========================================================================

    // Add new action buttons
    $('#mxch-add-action-btn, #mxch-create-first-action-btn').on('click', function() {
        openActionEditor();
    });

    // Close editor buttons
    $('#mxch-close-editor, #mxch-close-editor-2').on('click', function() {
        resetEditorPanel();
        $('.mxch-action-item').removeClass('active');
        currentActionId = null;
    });

    // Cancel button
    $('#mxch-cancel-action').on('click', function() {
        if (currentActionId && $('#mxch-form-mode').val() === 'edit') {
            // Go back to view mode
            const action = $('#mxch-action-view').data('current-action');
            if (action) {
                showActionView(action);
            }
        } else {
            resetEditorPanel();
        }
    });

    // Back to step 1
    $('#mxch-back-to-step-1').on('click', function() {
        if ($('#mxch-form-mode').val() === 'edit') {
            // If editing, go back to view
            const action = $('#mxch-action-view').data('current-action');
            if (action) {
                showActionView(action);
            }
        } else {
            // If creating, go back to step 1
            $('.mxch-editor-step').removeClass('active');
            $('#mxch-editor-step-1').addClass('active');
        }
    });

    // Edit action button
    $('#mxch-edit-action-btn').on('click', function() {
        const action = $('#mxch-action-view').data('current-action');
        if (action) {
            openActionEditorForEdit(action);
        }
    });

    // Toggle enabled status
    $('#mxch-action-enabled-toggle').on('change', function() {
        if (!currentActionId) return;

        const isEnabled = $(this).is(':checked');
        toggleActionStatus(currentActionId, isEnabled);
    });

    // Delete action button
    $('#mxch-delete-action-btn').on('click', function() {
        if (!currentActionId) return;
        showDeleteModal(currentActionId);
    });

    // ==========================================================================
    // Action Type Selection (Step 1)
    // ==========================================================================

    // Type search
    $('#mxch-type-search-input').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterTypeCards(searchTerm, 'all');
    });

    // Category buttons
    $('.mxch-category-btn').on('click', function() {
        $('.mxch-category-btn').removeClass('active');
        $(this).addClass('active');

        const category = $(this).data('category');
        const searchTerm = $('#mxch-type-search-input').val().toLowerCase();
        filterTypeCards(searchTerm, category);
    });

    // Filter type cards
    function filterTypeCards(searchTerm, category) {
        $('.mxch-type-card').each(function() {
            const $card = $(this);
            const cardCategory = $card.data('category');
            const cardLabel = $card.data('label').toLowerCase();
            const cardDesc = $card.find('p').text().toLowerCase();

            const matchesCategory = category === 'all' || cardCategory === category;
            const matchesSearch = !searchTerm || cardLabel.includes(searchTerm) || cardDesc.includes(searchTerm);

            if (matchesCategory && matchesSearch) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    }

    // Type card click
    $('.mxch-type-card').on('click', function() {
        const $card = $(this);

        // Check if pro required
        if ($card.hasClass('mxch-type-pro') && !mxchActionsData.isActivated) {
            alert(mxchActionsData.i18n.proRequired);
            return;
        }

        // Check if addon required
        if ($card.hasClass('mxch-type-addon') && $card.data('installed') !== true) {
            alert(mxchActionsData.i18n.addonRequired);
            return;
        }

        // Select this card
        $('.mxch-type-card').removeClass('selected');
        $card.addClass('selected');

        // Get card data
        const callback = $card.data('value');
        const label = $card.data('label');
        const iconHtml = $card.find('.mxch-type-icon').html();
        const desc = $card.find('p').text();

        // Populate step 2
        $('#mxch-callback-function').val(callback);
        $('#mxch-selected-type-icon').html(iconHtml);
        $('#mxch-selected-type-label').text(label);
        $('#mxch-selected-type-desc').text(desc);

        // Go to step 2
        $('.mxch-editor-step').removeClass('active');
        $('#mxch-editor-step-2').addClass('active');
    });

    // ==========================================================================
    // Form Handling
    // ==========================================================================

    // Threshold slider
    $('#mxch-action-threshold').on('input', function() {
        $('#mxch-threshold-value').text($(this).val() + '%');
    });

    // Form submission
    $('#mxch-action-form').on('submit', function(e) {
        e.preventDefault();

        const formMode = $('#mxch-form-mode').val();
        const actionId = $('#mxch-action-id').val();
        const callbackFunction = $('#mxch-callback-function').val();
        const label = $('#mxch-action-label').val().trim();
        const phrases = $('#mxch-action-phrases').val().trim();
        const threshold = $('#mxch-action-threshold').val();

        // Validation
        if (!callbackFunction) {
            alert('Please select an action type.');
            return;
        }

        if (!label) {
            alert('Please enter an action label.');
            $('#mxch-action-label').focus();
            return;
        }

        if (!phrases) {
            alert('Please enter at least one trigger phrase.');
            $('#mxch-action-phrases').focus();
            return;
        }

        // Get enabled bots
        const enabledBots = [];
        $('#mxch-bot-selector input[type="checkbox"]:checked').each(function() {
            enabledBots.push($(this).val());
        });

        if (enabledBots.length === 0) {
            enabledBots.push('default');
        }

        showLoading();

        const data = {
            action: formMode === 'edit' ? 'mxchat_edit_intent_ajax' : 'mxchat_add_intent_ajax',
            intent_label: label,
            phrases: phrases,
            similarity_threshold: threshold,
            callback_function: callbackFunction,
            enabled_bots: enabledBots,
            security: formMode === 'edit' ? mxchActionsData.editNonce : mxchActionsData.addNonce
        };

        if (formMode === 'edit') {
            data.intent_id = actionId;
        }

        $.ajax({
            url: mxchActionsData.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                hideLoading();

                if (response.success) {
                    // Reload list
                    loadActionList(currentPage, $('#mxch-search-actions').val(), currentFilter);

                    // Reset and close editor
                    resetEditorPanel();
                    currentActionId = null;

                    // Show success message (could use a toast notification)
                    // For now, just reload
                } else {
                    alert(response.data || mxchActionsData.i18n.error);
                }
            },
            error: function() {
                hideLoading();
                alert(mxchActionsData.i18n.error);
            }
        });
    });

    // ==========================================================================
    // Toggle Action Status
    // ==========================================================================

    function toggleActionStatus(actionId, isEnabled) {
        // Optimistic UI update - update immediately before AJAX completes
        const $item = $('.mxch-action-item[data-action-id="' + actionId + '"]');
        const $status = $item.find('.mxch-action-status');
        const $toggle = $('#mxch-action-enabled-toggle');

        // Immediately update UI
        $item.data('enabled', isEnabled ? '1' : '0');
        if (isEnabled) {
            $status.removeClass('disabled').addClass('enabled').text('Enabled');
            $item.removeClass('disabled');
        } else {
            $status.removeClass('enabled').addClass('disabled').text('Disabled');
            $item.addClass('disabled');
        }

        // Update view status immediately
        const statusText = isEnabled ? 'Enabled' : 'Disabled';
        const statusClass = isEnabled ? 'mxch-status-enabled' : 'mxch-status-disabled';
        $('#mxch-view-status').text(statusText).removeClass('mxch-status-enabled mxch-status-disabled').addClass(statusClass);

        // Update stored data immediately
        const currentData = $('#mxch-action-view').data('current-action');
        if (currentData) {
            currentData.enabled = isEnabled;
            $('#mxch-action-view').data('current-action', currentData);
        }

        $.ajax({
            url: mxchActionsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_toggle_action_status',
                action_id: actionId,
                enabled: isEnabled ? 1 : 0,
                security: mxchActionsData.toggleNonce
            },
            success: function(response) {
                if (!response.success) {
                    // Revert optimistic update on failure
                    revertToggleUI(!isEnabled, $item, $status, $toggle);
                    alert(response.data || mxchActionsData.i18n.error);
                }
            },
            error: function() {
                // Revert optimistic update on error
                revertToggleUI(!isEnabled, $item, $status, $toggle);
                alert(mxchActionsData.i18n.error);
            }
        });
    }

    // Helper to revert toggle UI on error
    function revertToggleUI(revertEnabled, $item, $status, $toggle) {
        $toggle.prop('checked', revertEnabled);
        $item.data('enabled', revertEnabled ? '1' : '0');
        if (revertEnabled) {
            $status.removeClass('disabled').addClass('enabled').text('Enabled');
            $item.removeClass('disabled');
        } else {
            $status.removeClass('enabled').addClass('disabled').text('Disabled');
            $item.addClass('disabled');
        }
        const statusText = revertEnabled ? 'Enabled' : 'Disabled';
        const statusClass = revertEnabled ? 'mxch-status-enabled' : 'mxch-status-disabled';
        $('#mxch-view-status').text(statusText).removeClass('mxch-status-enabled mxch-status-disabled').addClass(statusClass);
    }

    // ==========================================================================
    // Delete Modal
    // ==========================================================================

    function showDeleteModal(actionId) {
        $('#mxch-delete-modal').show();
        $('#mxch-confirm-delete').data('action-id', actionId);
    }

    $('#mxch-delete-modal .mxch-modal-close, #mxch-cancel-delete').on('click', function() {
        $('#mxch-delete-modal').hide();
    });

    $('#mxch-confirm-delete').on('click', function() {
        const actionId = $(this).data('action-id');
        $('#mxch-delete-modal').hide();
        deleteAction(actionId);
    });

    function deleteAction(actionId) {
        showLoading();

        $.ajax({
            url: mxchActionsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_delete_intent_ajax',
                intent_id: actionId,
                security: mxchActionsData.deleteNonce
            },
            success: function(response) {
                hideLoading();

                if (response.success) {
                    currentActionId = null;
                    resetEditorPanel();
                    loadActionList(currentPage, $('#mxch-search-actions').val(), currentFilter);
                } else {
                    alert(response.data || mxchActionsData.i18n.error);
                }
            },
            error: function() {
                hideLoading();
                alert(mxchActionsData.i18n.error);
            }
        });
    }

    // ==========================================================================
    // Utility Functions
    // ==========================================================================

    function showLoading() {
        $('#mxch-action-loading').show();
    }

    function hideLoading() {
        $('#mxch-action-loading').hide();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
});
