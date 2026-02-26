// Simple debounce function implementation
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Helper function to open edit modal for intents/actions
function mxchatOpenEditModal(intentId, phrases) {
    const modal = document.getElementById('mxchat-edit-modal');
    if (!modal) return;

    // Get form fields
    const intentIdField = document.getElementById('edit_intent_id');
    const phrasesField = document.getElementById('edit_phrases');

    // Set values
    intentIdField.value = intentId;
    phrasesField.value = phrases;

    // Show modal with animation
    modal.style.display = 'flex';
    requestAnimationFrame(() => {
        modal.classList.add('active');
    });

    // Set up close handlers
    const closeModal = () => {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Match the CSS transition time
    };

    // Close button handler
    const closeBtn = modal.querySelector('.mxchat-modal-close');
    if (closeBtn) {
        closeBtn.onclick = closeModal;
    }

    // Cancel button handler
    const cancelBtn = modal.querySelector('.mxchat-modal-cancel');
    if (cancelBtn) {
        cancelBtn.onclick = closeModal;
    }

    // Click outside modal to close
    modal.onclick = (e) => {
        if (e.target === modal) {
            closeModal();
        }
    };

    // Focus the textarea
    phrasesField.focus();
}
// Live Agent Notice Dismissal Function
function dismissLiveAgentNotice() {
    if (typeof jQuery !== 'undefined' && typeof mxchatLiveAgent !== 'undefined') {
        jQuery.post(mxchatLiveAgent.ajaxurl, {
            action: 'dismiss_live_agent_notice',
            nonce: mxchatLiveAgent.nonce
        }, function(response) {
            if (response.success) {
                jQuery('#mxchat-disabled-notice').fadeOut(300);
            }
        }).fail(function() {
            // Fallback: just hide the notice if AJAX fails
            jQuery('#mxchat-disabled-notice').fadeOut(300);
        });
    } else {
        // Fallback for cases where jQuery or localized data isn't available
        var notice = document.getElementById('mxchat-disabled-notice');
        if (notice) {
            notice.style.display = 'none';
        }
    }
}

// Theme Migration Notice Dismissal Function
function dismissThemeMigrationNotice() {
    if (typeof jQuery !== 'undefined' && typeof mxchatThemeMigration !== 'undefined') {
        jQuery.post(mxchatThemeMigration.ajaxurl, {
            action: 'dismiss_theme_migration_notice',
            nonce: mxchatThemeMigration.nonce
        }, function(response) {
            if (response.success) {
                jQuery('#mxchat-theme-migration-notice').fadeOut(300);
            }
        }).fail(function() {
            // Fallback: just hide the notice if AJAX fails
            jQuery('#mxchat-theme-migration-notice').fadeOut(300);
        });
    } else {
        // Fallback for cases where jQuery or localized data isn't available
        var notice = document.getElementById('mxchat-theme-migration-notice');
        if (notice) {
            notice.style.display = 'none';
        }
    }
}

// Updated mxchatOpenActionModal function to integrate with the new selector
function mxchatOpenActionModal(isEdit = false, actionId = '', label = '', phrases = '', threshold = 85, callbackFunction = '') {
    const modal = document.getElementById('mxchat-action-modal');
    if (!modal) return;

    // Get form fields
    const actionIdField = document.getElementById('edit_action_id');
    const labelField = document.getElementById('intent_label');
    const phrasesField = document.getElementById('action_phrases');
    const formActionType = document.getElementById('form_action_type');
    const callbackGroup = document.getElementById('callback_selection_group');
    const callbackSelect = document.getElementById('callback_function');
    const saveButton = document.getElementById('mxchat-save-action-btn');
    const nonceContainer = document.getElementById('action-nonce-container');
    const thresholdSlider = document.getElementById('similarity_threshold');
    const thresholdDisplay = document.querySelector('.mxchat-threshold-value-display');

    // Set up modal for edit or create
    if (isEdit) {
        saveButton.textContent = 'Update Action';
        formActionType.value = 'mxchat_edit_intent';
        actionIdField.value = actionId;
        labelField.value = label;
        phrasesField.value = phrases;
        callbackGroup.style.display = 'none'; // Hide callback selection when editing
        thresholdSlider.value = threshold; // Set the current threshold value
        thresholdDisplay.textContent = threshold + '%'; // Update display
        
        // Remove the required attribute when editing
        callbackSelect.removeAttribute('required');
        
        // Update the nonce field for editing
        nonceContainer.innerHTML = '';  // Clear existing nonce
        if (typeof mxchatAdmin !== 'undefined' && mxchatAdmin.edit_intent_nonce) {
            nonceContainer.innerHTML = `<input type="hidden" name="_wpnonce" value="${mxchatAdmin.edit_intent_nonce}">`;
        }
    } else {
        saveButton.textContent = 'Save Action';
        formActionType.value = 'mxchat_add_intent';
        actionIdField.value = '';
        labelField.value = '';
        phrasesField.value = '';
        callbackGroup.style.display = 'block'; // Show callback selection when creating
        thresholdSlider.value = 85; // Default value for new actions
        thresholdDisplay.textContent = '85%'; // Default display
        
        // Ensure the required attribute is present when adding
        callbackSelect.setAttribute('required', 'required');
        
        // Update the nonce field for adding
        nonceContainer.innerHTML = '';  // Clear existing nonce
        if (typeof mxchatAdmin !== 'undefined' && mxchatAdmin.add_intent_nonce) {
            nonceContainer.innerHTML = `<input type="hidden" name="_wpnonce" value="${mxchatAdmin.add_intent_nonce}">`;
        }
    }

    // Show modal with animation
    modal.style.display = 'flex';
    requestAnimationFrame(() => {
        modal.classList.add('active');
    });

    // Set up close handlers
    const closeModal = () => {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Match the CSS transition time
    };

    // Close button handler
    const closeBtn = modal.querySelector('.mxchat-modal-close');
    if (closeBtn) {
        closeBtn.onclick = closeModal;
    }

    // Cancel button handler
    const cancelBtn = modal.querySelector('.mxchat-modal-cancel');
    if (cancelBtn) {
        cancelBtn.onclick = closeModal;
    }

    // Click outside modal to close
    modal.onclick = (e) => {
        if (e.target === modal) {
            closeModal();
        }
    };

    // Escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    }, { once: true });

    // Focus the first field
    labelField.focus();
    
    // Dispatch an event for the action type selector to catch
    const event = new CustomEvent('mxchatModalOpened', {
        detail: {
            isEdit: isEdit,
            callbackFunction: callbackFunction || (isEdit ? callbackSelect.value : '')
        }
    });
    document.dispatchEvent(event);
    
    return closeModal; // Return close function for external use
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', () => {
    // Set up edit button handlers for intents
    document.querySelectorAll('.mxchat-edit-button').forEach(button => {
        button.onclick = () => {
            const intentId = button.dataset.intentId;
            const phrases = button.dataset.phrases;
            mxchatOpenEditModal(intentId, phrases);
        };
    });
    
    document.querySelectorAll('.mxchat-action-card .mxchat-edit-button').forEach(button => {
        button.onclick = () => {
            const actionId = button.dataset.actionId;
            const phrases = button.dataset.phrases;
            const label = button.dataset.label;
            const threshold = button.dataset.threshold || 85;
            const callbackFunction = button.dataset.callbackFunction;
            const enabledBots = button.dataset.enabledBots; // ADD THIS LINE
            
            mxchatOpenActionModal(true, actionId, label, phrases, threshold, callbackFunction, enabledBots);
        };
    });
        
    // Set up add new action buttons (new functionality)
    const addActionBtn = document.getElementById('mxchat-add-action-btn');
    if (addActionBtn) {
        addActionBtn.onclick = () => mxchatOpenActionModal();
    }
    
    const createFirstAction = document.getElementById('mxchat-create-first-action');
    if (createFirstAction) {
        createFirstAction.onclick = () => mxchatOpenActionModal();
    }
    
    // Setup category-specific new action buttons (new functionality)
    document.querySelectorAll('.mxchat-new-action-button').forEach(button => {
        button.onclick = () => {
            const category = button.closest('.mxchat-new-action-card').dataset.category;
            const closeModal = mxchatOpenActionModal();
            
            // Pre-select the appropriate callback based on category
            if (category) {
                const callbackSelect = document.getElementById('callback_function');
                if (callbackSelect) {
                    setTimeout(() => {
                        // Map categories to default callbacks
                        const categoryToCallback = {
                            'data_collection': 'mxchat_handle_form_collection',
                            'integrations': 'mxchat_handle_slack_message',
                            'custom_actions': 'mxchat_handle_custom_action',
                            'recommendations': 'mxchat_handle_product_recommendations'
                            // Add more mappings as needed
                        };
                        
                        if (categoryToCallback[category]) {
                            callbackSelect.value = categoryToCallback[category];
                        }
                    }, 100);
                }
            }
        };
    });
    
    // Handle action toggle switches (new functionality)
    document.querySelectorAll('.mxchat-action-toggle').forEach(toggle => {
        toggle.onchange = function() {
            const actionId = this.dataset.actionId;
            const isEnabled = this.checked;
            
            // Show loading indicator
            const loadingEl = document.getElementById('mxchat-action-loading');
            if (loadingEl) loadingEl.style.display = 'flex';
            
            // Send AJAX request to update status
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'mxchat_toggle_action',
                    intent_id: actionId,
                    enabled: isEnabled ? 1 : 0,
                    nonce: mxchatAdmin.toggle_action_nonce // Use the correct nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Failed to update action status: ' + (data.data?.message || 'Unknown error'));
                    this.checked = !isEnabled; // Revert the toggle
                }
            })
            .catch(error => {
                //console.error('Error:', error);
                alert('Server error. Please try again.');
                this.checked = !isEnabled; // Revert the toggle
            })
            .finally(() => {
                if (loadingEl) loadingEl.style.display = 'none';
            });
        };
    });
    
    // Handle threshold sliders in action cards (new functionality)
    document.querySelectorAll('.mxchat-threshold-slider').forEach(slider => {
        slider.oninput = function() {
            const actionId = this.id.replace('intent_threshold_', '');
            document.getElementById('threshold_output_' + actionId).textContent = this.value + '%';
        };
    });

    // Handle threshold save buttons in action cards (new functionality)
    document.querySelectorAll('.mxchat-threshold-save').forEach(button => {
        button.onclick = function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const intentId = form.querySelector('input[name="intent_id"]').value;
            const threshold = form.querySelector('input[name="intent_threshold"]').value;
            const nonce = form.querySelector('input[name="_wpnonce"]').value;
            
            // Show loading indicator
            const loadingEl = document.getElementById('mxchat-action-loading');
            if (loadingEl) loadingEl.style.display = 'flex';
            
            // Send AJAX request
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'mxchat_update_intent_threshold',
                    intent_id: intentId,
                    intent_threshold: threshold,
                    _wpnonce: nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Visual feedback of success
                    const card = this.closest('.mxchat-action-card');
                    card.style.background = 'rgba(120, 115, 245, 0.1)';
                    setTimeout(() => {
                        card.style.background = 'white';
                    }, 300);
                } else {
                    alert('Failed to update threshold: ' + (data.data?.message || 'Unknown error'));
                }
            })
            .catch(error => {
                //console.error('Error:', error);
                alert('Server error. Please try again.');
            })
            .finally(() => {
                if (loadingEl) loadingEl.style.display = 'none';
            });
        };
    });
});

jQuery(document).ready(function($) {
    // Ensure we have a debounce function (use lodash if available, otherwise use our implementation)
    const useDebounce = (window._ && window._.debounce) ? window._.debounce : debounce;

    // --- AJAX Auto-Save ---
    let $autosaveSections = $('.mxchat-autosave-section');
    
    // *** ADD THIS: Extend auto-save sections to include Pinecone settings ***
    const $pineconeAutosaveSection = $('#mxchat-kb-tab-pinecone');
    if ($pineconeAutosaveSection.length) {
        $autosaveSections = $autosaveSections.add($pineconeAutosaveSection);
        //console.log('Added Pinecone section to auto-save monitoring');
    }
    
    // Track whether fields have been modified by user
    const userModifiedFields = new Set();

    if ($autosaveSections.length) {
        // Track user interactions with input fields to determine if changes are user-initiated
        $autosaveSections.find('input, textarea, select').on('focus keydown paste', function() {
            const fieldName = $(this).attr('name');
            if (fieldName) {
                userModifiedFields.add(fieldName);
            }
        });
    
        // Handle real-time range slider value updates
        $autosaveSections.find('input[type="range"]').on('input', function() {
            const value = $(this).val();
            const $slider = $(this);
            const sliderId = $slider.attr('id');
            // Find the corresponding value display span (convention: id_value)
            const $valueSpan = $('#' + sliderId + '_value');
            if ($valueSpan.length) {
                $valueSpan.text(value);
            } else {
                // Fallback for similarity_threshold which uses threshold_value
                $('#threshold_value').text(value);
            }
        });

        // Handle all input changes (including range slider)
        // Store pending AJAX requests and debounce timers per field to prevent freezing
        const pendingRequests = {};
        const fieldDebounceTimers = {};

        // Helper function to save rate limit fields with request tracking
        function triggerFieldSave($field, name, pendingRequests) {
            const value = $field.val();

            // Remove any existing feedback containers for this field
            $field.siblings('.feedback-container').remove();
            $field.parent().find('.feedback-container').remove();

            // Create feedback container
            const feedbackContainer = $('<div class="feedback-container"></div>');
            const spinner = $('<div class="saving-spinner"></div>');
            const successIcon = $('<div class="success-icon">✔</div>');
            $field.after(feedbackContainer);
            feedbackContainer.append(spinner);

            // Rate limits use the main settings action
            const ajaxAction = 'mxchat_save_setting';
            const nonce = mxchatAdmin.setting_nonce;

            // Store the AJAX request so it can be aborted if needed
            pendingRequests[name] = $.ajax({
                url: mxchatAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    name: name,
                    value: value,
                    _ajax_nonce: nonce
                },
                success: function(response) {
                    delete pendingRequests[name];
                    if (response.success) {
                        spinner.fadeOut(200, function() {
                            feedbackContainer.append(successIcon);
                            successIcon.fadeIn(200).delay(800).fadeOut(200, function() {
                                feedbackContainer.remove();
                            });
                        });
                    } else {
                        feedbackContainer.remove();
                    }
                },
                error: function(xhr, textStatus, error) {
                    delete pendingRequests[name];
                    // Don't show error for aborted requests
                    if (textStatus !== 'abort') {
                        feedbackContainer.remove();
                    }
                }
            });
        }

        $autosaveSections.find('input, textarea, select').not('#model, #openrouter_selected_model').on('change', function() {
                    const $field = $(this);
                    const name = $field.attr('name');

                    // Debounce rate limit fields to prevent UI freezing from rapid changes
                    if (name && name.indexOf('rate_limits') !== -1) {
                        // Clear any pending timer for this field
                        if (fieldDebounceTimers[name]) {
                            clearTimeout(fieldDebounceTimers[name]);
                        }
                        // Abort any pending AJAX request for this field
                        if (pendingRequests[name]) {
                            pendingRequests[name].abort();
                            // Remove any existing feedback containers for this field
                            $field.siblings('.feedback-container').remove();
                            $field.parent().find('.feedback-container').remove();
                        }
                        // Debounce the save operation
                        const fieldRef = $field;
                        fieldDebounceTimers[name] = setTimeout(function() {
                            triggerFieldSave(fieldRef, name, pendingRequests);
                        }, 300);
                        return;
                    }
                    
                    // Skip saving for API key fields that haven't been interacted with and are empty
                    const isApiKeyField = name && (
                        name === 'loops_api_key' || 
                        name === 'api_key' || 
                        name === 'xai_api_key' || 
                        name === 'claude_api_key' ||
                        name === 'voyage_api_key' ||
                        name === 'gemini_api_key' ||
                        name === 'deepseek_api_key' ||
                        name.indexOf('_api_key') !== -1
                    );
                    
                    // Skip processing if:
                    // 1. It's an API key field
                    // 2. The user hasn't interacted with it
                    // 3. The field is empty
                    if (isApiKeyField && !userModifiedFields.has(name) && (!$field.val() || $field.val().trim() === '')) {
                        //console.log('Skipping auto-save for untouched API key field:', name);
                        return;
                    }
                    
                    let value;
        
                    // Handle different input types
                    if ($field.attr('type') === 'checkbox') {
                        // *** UPDATED: Handle Pinecone checkboxes differently ***
                        if (name && name.indexOf('mxchat_pinecone_addon_options') !== -1) {
                            value = $field.is(':checked') ? '1' : '0';
                        } else {
                            value = $field.is(':checked') ? 'on' : 'off';
                        }
                    } else {
                        value = $field.val();
                    }
        
                    // Create feedback container
                    const feedbackContainer = $('<div class="feedback-container"></div>');
                    const spinner = $('<div class="saving-spinner"></div>');
                    const successIcon = $('<div class="success-icon">✔</div>');
        
                    // Position feedback container based on input type
                    if ($field.closest('.toggle-switch').length) {
                        // Try td first (old layout), then mxc-field-control (new card layout), then fallback to after toggle
                        var $container = $field.closest('td');
                        if (!$container.length) {
                            $container = $field.closest('.mxc-field-control');
                        }
                        if ($container.length) {
                            $container.append(feedbackContainer);
                        } else {
                            $field.closest('.toggle-switch').after(feedbackContainer);
                        }
                    } else if ($field.closest('.mxchat-toggle-switch').length) {
                        // Try mxchat-toggle-container first, then parent div, then fallback to after toggle
                        var $toggleContainer = $field.closest('.mxchat-toggle-container');
                        if ($toggleContainer.length) {
                            $toggleContainer.append(feedbackContainer);
                        } else {
                            $field.closest('.mxchat-toggle-switch').after(feedbackContainer);
                        }
                    } else if ($field.closest('.slider-container').length) {
                        $field.closest('.slider-container').after(feedbackContainer);
                    } else {
                        $field.after(feedbackContainer);
                    }
                    feedbackContainer.append(spinner);
        
                    // Determine which AJAX action and nonce to use:
                    var ajaxAction, nonce;
                    // *** UPDATED: Add Pinecone fields, chunking fields, ACF fields, and custom meta to prompts action ***
                    if (name.indexOf('mxchat_prompts_options') !== -1 ||
                        name === 'mxchat_auto_sync_posts' ||
                        name === 'mxchat_auto_sync_pages' ||
                        name.indexOf('mxchat_auto_sync_') === 0 ||
                        name.indexOf('mxchat_pinecone_addon_options') !== -1 ||
                        name.indexOf('mxchat_chunk') === 0 ||
                        name.indexOf('mxchat_acf_field_') === 0 ||
                        name === 'mxchat_custom_meta_whitelist') { // Chunking, ACF field settings, and custom meta
                        ajaxAction = 'mxchat_save_prompts_setting';
                        nonce = mxchatPromptsAdmin.prompts_setting_nonce;
                    } else {
                        // Otherwise, use the existing AJAX action.
                        ajaxAction = 'mxchat_save_setting';
                        nonce = mxchatAdmin.setting_nonce;
                    }
        
                    // *** ADD THIS: Debug logging for Pinecone fields ***
                    if (name && name.indexOf('mxchat_pinecone_addon_options') !== -1) {
                        //console.log('Saving Pinecone field:', name, '=', value);
                    }
        
                    // AJAX save request
                    $.ajax({
                        url: (ajaxAction === 'mxchat_save_prompts_setting') ? mxchatPromptsAdmin.ajax_url : mxchatAdmin.ajax_url,
                        type: 'POST',
                        data: {
                            action: ajaxAction,
                            name: name,
                            value: value,
                            _ajax_nonce: nonce
                        },
                        success: function(response) {
            if (response.success) {
                spinner.fadeOut(200, function() {
                    feedbackContainer.append(successIcon);
                    successIcon.fadeIn(200).delay(1000).fadeOut(200, function() {
                        feedbackContainer.remove();
                    });
                });

                // *** ADD THIS: Refresh API key status after saving an API key ***
                const isApiKeyField = name && (
                    name === 'api_key' ||
                    name === 'xai_api_key' ||
                    name === 'claude_api_key' ||
                    name === 'voyage_api_key' ||
                    name === 'gemini_api_key' ||
                    name === 'deepseek_api_key' ||
                    name === 'openrouter_api_key' ||
                    name.indexOf('_api_key') !== -1
                );

                if (isApiKeyField && typeof window.mxchatRefreshAPIKeyStatus === 'function') {
                    window.mxchatRefreshAPIKeyStatus();
                }

                // *** ADD THIS: Update Pinecone checkbox state after successful save ***
                if (name && name.indexOf('mxchat_pinecone_addon_options[mxchat_use_pinecone]') !== -1) {
                    //console.log('Pinecone toggle saved successfully, value:', value);
                    
                    // The checkbox state is already updated by the user interaction
                    // But let's make sure the UI state matches the saved value
                    var $checkbox = $('input[name="mxchat_pinecone_addon_options[mxchat_use_pinecone]"]');
                    var settingsDiv = $('.mxchat-pinecone-settings');
                    
                    // Double-check the UI state matches what was saved
                    if (value === '1' && !$checkbox.is(':checked')) {
                        $checkbox.prop('checked', true);
                        settingsDiv.slideDown(300);
                    } else if (value === '0' && $checkbox.is(':checked')) {
                        $checkbox.prop('checked', false);
                        settingsDiv.slideUp(300);
                    }
                    
                    //console.log('Pinecone UI state synchronized');
                    
                    // Check if Knowledge Import tab is currently active
                    if ($('.mxchat-kb-tab-button[data-tab="import"]').hasClass('active')) {
                        // Show a notice that we need to refresh
                        var $knowledgeCard = $('#mxchat-kb-tab-import .mxchat-card').eq(1);
                        if ($knowledgeCard.length > 0) {
                            // Add a refresh notice at the top of the knowledge base card
                            var refreshNotice = $('<div class="notice notice-warning" style="margin: 15px 0; padding: 10px 15px;">' +
                                '<p style="margin: 0;">' +
                                '<span class="dashicons dashicons-info" style="color: #f0ad4e; margin-right: 5px;"></span>' +
                                'Database settings have changed. ' +
                                '<a href="#" onclick="location.reload(); return false;" style="font-weight: bold;">Click here to refresh</a> to see the updated knowledge base.' +
                                '</p></div>');
                            
                            $knowledgeCard.prepend(refreshNotice);
                        }
                    } else {
                        // If not on import tab, set a flag to refresh when they go there
                        sessionStorage.setItem('mxchat_pinecone_changed', 'true');
                    }
                }
                
                // *** ADD THIS: Debug logging for successful saves ***
                if (name && name.indexOf('mxchat_pinecone_addon_options') !== -1) {
                    //console.log('Pinecone field saved successfully:', name, '=', value);
                }
                
                // Check if the response contains a "no changes" message and log it
                if (response.data && response.data.message === 'No changes detected') {
                    //console.log('No changes detected for field:', name);
                }
            } else {
                // Only show alert for actual errors, not for "no changes"
                let errorMessage = response.data?.message || 'Unknown error';
                
                // Don't display an alert for "no changes" message
                if (errorMessage !== 'No changes detected' && errorMessage !== 'Update failed or no changes') {
                    alert('Error saving: ' + errorMessage);
                } else {
                    // Still provide visual feedback that no changes were needed
                    spinner.fadeOut(200, function() {
                        feedbackContainer.append(successIcon);
                        successIcon.fadeIn(200).delay(1000).fadeOut(200, function() {
                            feedbackContainer.remove();
                        });
                    });
                    //console.log('No changes detected for field:', name);
                    return;
                }
                
                // Only revert checkbox state if it was an actual error
                if (errorMessage !== 'No changes detected' && errorMessage !== 'Update failed or no changes') {
                    if ($field.attr('type') === 'checkbox') {
                        $field.prop('checked', !$field.is(':checked'));
                    }
                }
                
                // Always clean up the feedback container
                feedbackContainer.remove();
            }
        },
                        error: function(xhr, textStatus, error) {
                            //console.error('AJAX Error:', textStatus, error);
                            alert('An error occurred while saving. Please try again.');
                            
                            // Revert checkbox state on error
                            if ($field.attr('type') === 'checkbox') {
                                $field.prop('checked', !$field.is(':checked'));
                            }
                            
                            feedbackContainer.remove();
                        }
                    });
                });

        // Initialize color pickers with debouncing
        $autosaveSections.find('.my-color-field').each(function() {
            const $colorField = $(this);
            
            $(this).wpColorPicker({
                change: useDebounce(function(event, ui) {
                    // Safety check - ensure we have a valid field and value
                    if (!$colorField || !$colorField.val()) {
                        //console.warn('Color picker not ready');
                        return;
                    }

                    const name = $colorField.attr('name');
                    const value = $colorField.val();

                    if (!name || !value) {
                        //console.warn('Missing required color picker values');
                        return;
                    }

                    // Create feedback container
                    const feedbackContainer = $('<div class="feedback-container"></div>');
                    const spinner = $('<div class="saving-spinner"></div>');
                    const successIcon = $('<div class="success-icon">✔</div>');

                    // Position feedback container
                    $colorField.closest('.wp-picker-container').after(feedbackContainer);
                    feedbackContainer.append(spinner);

                    // Determine which AJAX action and nonce to use:
                    var ajaxAction, nonce;
                    // Use the new AJAX action for submenu fields:
                    if (name.indexOf('mxchat_prompts_options') !== -1 ||
                        name === 'mxchat_auto_sync_posts' || 
                        name === 'mxchat_auto_sync_pages' ||
                        name.indexOf('mxchat_auto_sync_') === 0) { // Modified to catch all auto-sync fields
                        ajaxAction = 'mxchat_save_prompts_setting';
                        nonce = mxchatPromptsAdmin.prompts_setting_nonce;
                    } else {
                        // Otherwise, use the existing AJAX action.
                        ajaxAction = 'mxchat_save_setting';
                        nonce = mxchatAdmin.setting_nonce;
                    }
                    // AJAX save request
                    $.ajax({
                        url: (ajaxAction === 'mxchat_save_prompts_setting') ? mxchatPromptsAdmin.ajax_url : mxchatAdmin.ajax_url,
                        type: 'POST',
                        data: {
                            action: ajaxAction,
                            name: name,
                            value: value,
                            _ajax_nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                spinner.fadeOut(200, function() {
                                    feedbackContainer.append(successIcon);
                                    successIcon.fadeIn(200).delay(1000).fadeOut(200, function() {
                                        feedbackContainer.remove();
                                    });
                                });
                            } else {
                                alert('Error saving: ' + (response.data?.message || 'Unknown error'));
                                feedbackContainer.remove();
                            }
                        },
                        error: function() {
                            alert('An error occurred while saving.');
                            feedbackContainer.remove();
                        }
                    });
                }, 500)
            });
        });

    }

// ========================================
// POST TYPE VISIBILITY SETTINGS
// ========================================
(function() {
    const $appendToBody = $('#append_to_body');
    const $visibilityOptions = $('#post-type-visibility-options');
    const $modeRadios = $('input[name="post_type_visibility_mode"]');
    const $postTypeList = $('#post-type-list');
    const $postTypeCheckboxes = $('input[name="post_type_visibility_list[]"]');

    // Toggle visibility options based on auto-display toggle
    $appendToBody.on('change', function() {
        if ($(this).is(':checked')) {
            $visibilityOptions.slideDown(200);
        } else {
            $visibilityOptions.slideUp(200);
        }
    });

    // Toggle post type list based on mode selection
    $modeRadios.on('change', function() {
        const mode = $(this).val();
        if (mode === 'all') {
            $postTypeList.slideUp(200);
        } else {
            $postTypeList.slideDown(200);
        }

        // Save mode via AJAX
        savePostTypeVisibility('post_type_visibility_mode', mode);
    });

    // Save post type list when checkboxes change
    $postTypeCheckboxes.on('change', function() {
        // Collect all checked post types
        const selectedPostTypes = [];
        $postTypeCheckboxes.filter(':checked').each(function() {
            selectedPostTypes.push($(this).val());
        });

        // Save as JSON array
        savePostTypeVisibility('post_type_visibility_list', JSON.stringify(selectedPostTypes));
    });

    // Helper function to save post type visibility settings
    function savePostTypeVisibility(name, value) {
        if (typeof mxchatAdmin === 'undefined') return;

        // Find the container element for feedback
        const $container = name === 'post_type_visibility_mode'
            ? $('.mxchat-visibility-mode')
            : $postTypeList;

        // Remove any existing feedback
        $container.find('.mxchat-save-feedback').remove();

        // Create feedback element
        const $feedback = $('<span class="mxchat-save-feedback" style="margin-left: 10px; font-size: 12px;"></span>');
        $feedback.text('Saving...').css('color', '#666');

        if (name === 'post_type_visibility_mode') {
            $container.append($feedback);
        } else {
            $container.before($feedback);
        }

        $.ajax({
            url: mxchatAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_save_setting',
                name: name,
                value: value,
                _ajax_nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success) {
                    $feedback.text('✓ Saved').css('color', '#46b450');
                    setTimeout(function() {
                        $feedback.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 1500);
                } else {
                    $feedback.text('Error saving').css('color', '#dc3232');
                }
            },
            error: function() {
                $feedback.text('Error saving').css('color', '#dc3232');
            }
        });
    }
})();

// Toggle visibility handlers
function toggleVisibility(selector) {
    $(selector).on('click', function() {
        var inputField = $(this).prev('input');
        // Check if this is a CSS-masked field (type="text" with mxchat-api-key-field class)
        if (inputField.hasClass('mxchat-api-key-field')) {
            // Use CSS class toggle for CSS-masked fields
            if (inputField.hasClass('mxchat-show-key')) {
                inputField.removeClass('mxchat-show-key');
                $(this).text('Show');
            } else {
                inputField.addClass('mxchat-show-key');
                $(this).text('Hide');
            }
        } else {
            // Legacy type toggle for password fields
            if (inputField.attr('type') === 'password') {
                inputField.attr('type', 'text');
                $(this).text('Hide');
            } else {
                inputField.attr('type', 'password');
                $(this).text('Show');
            }
        }
    });
}

// Initialize all toggle visibility buttons
[
    '#toggleApiKeyVisibility',
    '#toggleWooCommerceSecretVisibility',
    '#toggleVoyageAPIKeyVisibility',
    '#toggleLoopsApiKeyVisibility',
    '#toggleXaiApiKeyVisibility',
    '#toggleClaudeApiKeyVisibility',
    '#toggleBraveApiKeyVisibility',
    '#toggleWebhookUrlVisibility',
    '#toggleSecretKeyVisibility',
    '#toggleBotTokenVisibility',
    '#toggleDeepSeekApiKeyVisibility',
    '#toggleGeminiApiKeyVisibility',
    '#toggleOpenRouterApiKeyVisibility'
].forEach(toggleVisibility);

function setupMxChatModelSelector() {
    const $modelSelect = $('#model');
    const $modelSelectorButton = $('<button>', {
        type: 'button',
        id: 'mxchat_model_selector_btn',
        class: 'button-primary mxchat-model-selector-btn',
        text: 'Select AI Model'
    });
    
    // Replace the select dropdown with a button
    $modelSelect.hide().after($modelSelectorButton);
    
    // Update button text to show currently selected model
    function updateButtonText() {
        const selectedModel = $modelSelect.val();
        
        // Check if OpenRouter is selected
        if (selectedModel === 'openrouter') {
            const openrouterModelId = $('#openrouter_selected_model').val();
            const openrouterModelName = $('#openrouter_selected_model_name').val();
            
            if (openrouterModelName && openrouterModelName.trim() !== '') {
                $modelSelectorButton.text('OpenRouter: ' + openrouterModelName);
            } else if (openrouterModelId && openrouterModelId.trim() !== '') {
                $modelSelectorButton.text('OpenRouter: ' + openrouterModelId);
            } else {
                $modelSelectorButton.text('OpenRouter - Select Model');
            }
        } else {
            const selectedModelText = $modelSelect.find('option:selected').text();
            $modelSelectorButton.text(selectedModelText);
        }
    }

    // Initialize button text
    updateButtonText();
    
    // Create and append modal HTML
    const modelSelectorModal = `
        <div id="mxchat_model_selector_modal" class="mxchat-model-selector-modal">
            <div class="mxchat-model-selector-modal-content">
                <div class="mxchat-model-selector-modal-header">
                    <h3>Select AI Model</h3>
                    <span class="mxchat-model-selector-modal-close">&times;</span>
                </div>
                <div class="mxchat-model-selector-modal-body">
                    <div class="mxchat-model-selector-search-container">
                        <input type="text" id="mxchat_model_search_input" class="mxchat-model-search-input" placeholder="Search models...">
                    </div>
                        <div class="mxchat-model-selector-categories">
                            <button class="mxchat-model-category-btn active" data-category="all">All</button>
                            <button class="mxchat-model-category-btn" data-category="openrouter">OpenRouter</button>
                            <button class="mxchat-model-category-btn" data-category="gemini">Google Gemini</button>
                            <button class="mxchat-model-category-btn" data-category="openai">OpenAI</button>
                            <button class="mxchat-model-category-btn" data-category="claude">Claude</button>
                            <button class="mxchat-model-category-btn" data-category="xai">X.AI</button>
                            <button class="mxchat-model-category-btn" data-category="deepseek">DeepSeek</button>
                        </div>
                    <div class="mxchat-model-selector-grid" id="mxchat_models_grid"></div>
                </div>
                <div class="mxchat-model-selector-modal-footer">
                    <button id="mxchat_cancel_model_selection" class="button mxchat-model-cancel-btn">Cancel</button>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modelSelectorModal);
    
    // MOVE THIS OUTSIDE - Make it a property of the window object so it's accessible globally
    window.populateModelsGrid = function(filter = '', category = 'all') {
        const $grid = $('#mxchat_models_grid');
        $grid.empty();
        
        const models = {
            openrouter: [
                { value: 'openrouter', label: 'OpenRouter', description: 'Access 100+ models from multiple providers (add API key to browse)' }
            ],
            gemini: [
                { value: 'gemini-3-pro-preview', label: 'Gemini 3 Pro', description: 'Most intelligent model - multimodal understanding & agentic' },
                { value: 'gemini-3-flash-preview', label: 'Gemini 3 Flash', description: 'Most balanced - speed, scale & frontier intelligence' },
                { value: 'gemini-2.5-pro', label: 'Gemini 2.5 Pro', description: 'Advanced thinking - code, math, STEM & long context' },
                { value: 'gemini-2.5-flash', label: 'Gemini 2.5 Flash', description: 'Best price-performance with thinking capabilities' },
                { value: 'gemini-2.5-flash-lite', label: 'Gemini 2.5 Flash-Lite', description: 'Ultra fast - optimized for cost & high throughput' },
                { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash', description: 'Deprecated March 2026' },
                { value: 'gemini-2.0-flash-lite', label: 'Gemini 2.0 Flash-Lite', description: 'Deprecated March 2026' },
                { value: 'gemini-1.5-pro', label: 'Gemini 1.5 Pro', description: 'Deprecated September 2025' },
                { value: 'gemini-1.5-flash', label: 'Gemini 1.5 Flash', description: 'Deprecated September 2025' },
            ],
            openai: [
                { value: 'gpt-5.2', label: 'GPT-5.2', description: 'Best general-purpose & agentic model with fast responses' },
                { value: 'gpt-5.1-chat-latest', label: 'GPT-5.1 Chat Latest', description: 'Recommended for most use cases' },
                { value: 'gpt-5.1-2025-11-13', label: 'GPT-5.1', description: 'Flagship for coding & agentic tasks with low reasoning (400K context)' },
                { value: 'gpt-5', label: 'GPT-5', description: 'Flagship for coding, reasoning, and agentic tasks across domains' },
                { value: 'gpt-5-mini', label: 'GPT-5 Mini', description: 'Fast and lightweight' },
                { value: 'gpt-5-nano', label: 'GPT-5 Nano', description: 'Fastest and cheapest; ideal for summarization and classification' },
            ],
            claude: [
                { value: 'claude-opus-4-6', label: 'Claude Opus 4.6', description: 'Most capable Claude model - recommended' },
                { value: 'claude-sonnet-4-6', label: 'Claude Sonnet 4.6', description: 'Latest Sonnet - excellent balance of speed and capability' },
                { value: 'claude-opus-4-5', label: 'Claude Opus 4.5', description: 'Highly capable for complex tasks' },
                { value: 'claude-sonnet-4-5-20250929', label: 'Claude Sonnet 4.5', description: 'Best for complex agents and coding' },
                { value: 'claude-opus-4-1-20250805', label: 'Claude Opus 4.1', description: 'Exceptional for specialized complex tasks' },
                { value: 'claude-haiku-4-5-20251001', label: 'Claude Haiku 4.5', description: 'Fastest and most intelligent Haiku' },
                { value: 'claude-opus-4-20250514', label: 'Claude 4 Opus', description: 'Complex tasks' },
                { value: 'claude-sonnet-4-20250514', label: 'Claude 4 Sonnet', description: 'High performance' },
            ],
            xai: [
                { value: 'grok-4-1-fast-reasoning', label: 'Grok 4.1 Fast (Reasoning)', description: '2M context window and reasoning' },
                { value: 'grok-4-1-fast-non-reasoning', label: 'Grok 4.1 Fast (Non-Reasoning)', description: '2M context window and faster responses' },
                { value: 'grok-4-0709', label: 'Grok 4', description: 'Latest flagship model - unparalleled performance in natural language, math and reasoning' },
                { value: 'grok-3-beta', label: 'Grok-3', description: 'Powerful model with 131K context' },
                { value: 'grok-3-fast-beta', label: 'Grok-3 Fast', description: 'High performance with faster responses' },
                { value: 'grok-3-mini-beta', label: 'Grok-3 Mini', description: 'Affordable model with good performance' },
                { value: 'grok-3-mini-fast-beta', label: 'Grok-3 Mini Fast', description: 'Quick and cost-effective' },
                { value: 'grok-2', label: 'Grok 2', description: 'Latest X.AI model' },
            ],
            deepseek: [
                { value: 'deepseek-chat', label: 'DeepSeek-V3', description: 'Advanced AI assistant' },
            ],
        };
        
        let allModels = [];
        Object.keys(models).forEach(key => {
            if (category === 'all' || category === key) {
                allModels = allModels.concat(models[key]);
            }
        });
        
        // Filter by search term if present
        if (filter) {
            const lowerFilter = filter.toLowerCase();
            allModels = allModels.filter(model => 
                model.label.toLowerCase().includes(lowerFilter) || 
                model.description.toLowerCase().includes(lowerFilter)
            );
        }
        
        // Create model cards
        allModels.forEach(model => {
            const isSelected = $modelSelect.val() === model.value;
            const $modelCard = $(`
                <div class="mxchat-model-selector-card ${isSelected ? 'mxchat-model-selected' : ''}" data-value="${model.value}">
                    <div class="mxchat-model-selector-icon">${getModelIcon(model.value)}</div>
                    <div class="mxchat-model-selector-info">
                        <h4 class="mxchat-model-selector-title">${model.label}</h4>
                        <p class="mxchat-model-selector-description">${model.description}</p>
                    </div>
                    ${isSelected ? '<div class="mxchat-model-selector-checkmark">✓</div>' : ''}
                </div>
            `);
            $grid.append($modelCard);
        });
    };

    // Helper function to get icon for each model
    function getModelIcon(modelValue) {
        if (modelValue === 'openrouter') return '<span class="dashicons dashicons-networking" style="font-size: 24px; color: #6750A4;"></span>';
        if (modelValue.startsWith('gemini-')) return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 48 48" class="mxchat-model-icon-gemini"><defs><path id="a" d="M44.5 20H24v8.5h11.8C34.7 33.9 30.1 37 24 37c-7.2 0-13-5.8-13-13s5.8-13 13-13c3.1 0 5.9 1.1 8.1 2.9l6.4-6.4C34.6 4.1 29.6 2 24 2 11.8 2 2 11.8 2 24s9.8 22 22 22c11 0 21-8 21-22 0-1.3-.2-2.7-.5-4z"></path></defs><clipPath id="b"><use xlink:href="#a" overflow="visible"></use></clipPath><path clip-path="url(#b)" fill="#FBBC05" d="M0 37V11l17 13z"></path><path clip-path="url(#b)" fill="#EA4335" d="M0 11l17 13 7-6.1L48 14V0H0z"></path><path clip-path="url(#b)" fill="#34A853" d="M0 37l30-23 7.9 1L48 0v48H0z"></path><path clip-path="url(#b)" fill="#4285F4" d="M48 48L17 24l-4-3 35-10z"></path></svg>';
        if (modelValue.startsWith('gpt-')) return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 320" class="mxchat-model-icon-openai"><path fill="currentColor" d="M297 131a80.6 80.6 0 0 0-93.7-104.2 80.6 80.6 0 0 0-137 29A80.6 80.6 0 0 0 23 189a80.6 80.6 0 0 0 93.7 104.2 80.6 80.6 0 0 0 137-29A80.7 80.7 0 0 0 297.1 131zM176.9 299c-14 .1-27.6-4.8-38.4-13.8l1.9-1 63.7-36.9c3.3-1.8 5.3-5.3 5.2-9v-89.9l27 15.6c.3.1.4.4.5.7v74.4a60 60 0 0 1-60 60zM47.9 244a59.7 59.7 0 0 1-7.1-40.1l1.9 1.1 63.7 36.8c3.2 1.9 7.2 1.9 10.5 0l77.8-45V228c0 .3-.2.6-.4.8L129.9 266a60 60 0 0 1-82-22zM31.2 105c7-12.2 18-21.5 31.2-26.3v75.8c0 3.7 2 7.2 5.2 9l77.8 45-27 15.5a1 1 0 0 1-.9 0L53.1 187a60 60 0 0 1-22-82zm221.2 51.5-77.8-45 27-15.5a1 1 0 0 1 .9 0l64.4 37.1a60 60 0 0 1-9.3 108.2v-75.8c0-3.7-2-7.2-5.2-9zm26.8-40.4-1.9-1.1-63.7-36.8a10.4 10.4 0 0 0-10.5 0L125.4 123V92c0-.3 0-.6.3-.8L190.1 54a60 60 0 0 1 89.1 62.1zm-168.5 55.4-27-15.5a1 1 0 0 1-.4-.7V80.9a60 60 0 0 1 98.3-46.1l-1.9 1L116 72.8a10.3 10.3 0 0 0-5.2 9v89.8zm14.6-31.5 34.7-20 34.6 20v40L160 200l-34.7-20z"></path></svg>';
        if (modelValue.startsWith('claude-')) return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 176" fill="none" class="mxchat-model-icon-claude"><path fill="currentColor" d="m147.487 0l70.081 175.78H256L185.919 0zM66.183 106.221l23.98-61.774l23.98 61.774zM70.07 0L0 175.78h39.18l14.33-36.914h73.308l14.328 36.914h39.179L110.255 0z"></path></svg>';
        if (modelValue.startsWith('grok-')) return '<svg fill="currentColor" fill-rule="evenodd" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="mxchat-model-icon-xai"><path d="M6.469 8.776L16.512 23h-4.464L2.005 8.776H6.47zm-.004 7.9l2.233 3.164L6.467 23H2l4.465-6.324zM22 2.582V23h-3.659V7.764L22 2.582zM22 1l-9.952 14.095-2.233-3.163L17.533 1H22z"></path></svg>';
        if (modelValue.startsWith('deepseek-')) return '<svg height="1em" viewBox="0 0 24 24" width="1em" xmlns="http://www.w3.org/2000/svg" class="mxchat-model-icon-deepseek"><path d="M23.748 4.482c-.254-.124-.364.113-.512.234-.051.039-.094.09-.137.136-.372.397-.806.657-1.373.626-.829-.046-1.537.214-2.163.848-.133-.782-.575-1.248-1.247-1.548-.352-.156-.708-.311-.955-.65-.172-.241-.219-.51-.305-.774-.055-.16-.11-.323-.293-.35-.2-.031-.278.136-.356.276-.313.572-.434 1.202-.422 1.84.027 1.436.633 2.58 1.838 3.393.137.093.172.187.129.323-.082.28-.18.552-.266.833-.055.179-.137.217-.329.14a5.526 5.526 0 01-1.736-1.18c-.857-.828-1.631-1.742-2.597-2.458a11.365 11.365 0 00-.689-.471c-.985-.957.13-1.743.388-1.836.27-.098.093-.432-.779-.428-.872.004-1.67.295-2.687.684a3.055 3.055 0 01-.465.137 9.597 9.597 0 00-2.883-.102c-1.885.21-3.39 1.102-4.497 2.623C.082 8.606-.231 10.684.152 12.85c.403 2.284 1.569 4.175 3.36 5.653 1.858 1.533 3.997 2.284 6.438 2.14 1.482-.085 3.133-.284 4.994-1.86.47.234.962.327 1.78.397.63.059 1.236-.03 1.705-.128.735-.156.684-.837.419-.961-2.155-1.004-1.682-.595-2.113-.926 1.096-1.296 2.746-2.642 3.392-7.003.05-.347.007-.565 0-.845-.004-.17.035-.237.23-.256a4.173 4.173 0 001.545-.475c1.396-.763 1.96-2.015 2.093-3.517.02-.23-.004-.467-.247-.588zM11.581 18c-2.089-1.642-3.102-2.183-3.52-2.16-.392.024-.321.471-.235.763.09.288.207.486.371.739.114.167.192.416-.113.603-.673.416-1.842-.14-1.897-.167-1.361-.802-2.5-1.86-3.301-3.307-.774-1.393-1.224-2.887-1.298-4.482-.02-.386.093-.522.477-.592a4.696 4.696 0 011.529-.039c2.132.312 3.946 1.265 5.468 2.774.868.86 1.525 1.887 2.202 2.891.72 1.066 1.494 2.082 2.48 2.914.348.292.625.514.891.677-.802.09-2.14.11-3.054-.614zm1-6.44a.306.306 0 01.415-.287.302.302 0 01.2.288.306.306 0 01-.31.307.303.303 0 01-.304-.308zm3.11 1.596c-.2.081-.399.151-.59.16a1.245 1.245 0 01-.798-.254c-.274-.23-.47-.358-.552-.758a1.73 1.73 0 01.016-.588c.07-.327-.008-.537-.239-.727-.187-.156-.426-.199-.688-.199a.559.559 0 01-.254-.078c-.11-.054-.2-.19-.114-.358.028-.054.16-.186.192-.21.356-.202.767-.136 1.146.016.352.144.618.408 1.001.782.391.451.462.576.685.914.176.265.336.537.445.848.067.195-.019.354-.25.452z" fill="currentColor"></path></svg>';
        return '<span class="dashicons dashicons-admin-generic mxchat-model-icon-generic"></span>';
    }
    
    // Event handlers
    $modelSelectorButton.on('click', function() {
        $('#mxchat_model_selector_modal').show();
        window.populateModelsGrid('', 'all');
    });
    
    $('.mxchat-model-selector-modal-close, #mxchat_cancel_model_selection').on('click', function() {
        $('#mxchat_model_selector_modal').hide();
    });
    
    $('.mxchat-model-category-btn').on('click', function() {
        $('.mxchat-model-category-btn').removeClass('active');
        $(this).addClass('active');
        const category = $(this).data('category');
        const searchTerm = $('#mxchat_model_search_input').val();
        window.populateModelsGrid(searchTerm, category);
    });
    
    $('#mxchat_model_search_input').on('input', function() {
        const searchTerm = $(this).val();
        const activeCategory = $('.mxchat-model-category-btn.active').data('category');
        window.populateModelsGrid(searchTerm, activeCategory);
    });
    
$(document).on('click', '.mxchat-model-selector-card', function() {
    const modelValue = $(this).data('value');
    const $modelSelect = $('#model');
    const $clickedCard = $(this);

    // Check if OpenRouter was selected
    if (modelValue === 'openrouter') {
        // Load OpenRouter models instead of closing
        loadOpenRouterModels();
    } else {
        // Remove selection from all other cards
        $('.mxchat-model-selector-card').removeClass('mxchat-model-selected').find('.mxchat-model-selector-checkmark').remove();

        // Add selection to clicked card immediately for instant feedback
        $clickedCard.addClass('mxchat-model-selected');
        if ($clickedCard.find('.mxchat-model-selector-checkmark').length === 0) {
            $clickedCard.append('<div class="mxchat-model-selector-checkmark">✓</div>');
        }

        // Brief delay to show the selection, then start saving
        setTimeout(function() {
            // Normal model selection
            $modelSelect.val(modelValue).trigger('change');

            // Show loading state on the card
            $clickedCard.css('pointer-events', 'none');
            const originalContent = $clickedCard.find('.mxchat-model-selector-title').html();
            $clickedCard.find('.mxchat-model-selector-title').html(
                '<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Saving...'
            );

            // Manually save the model via AJAX
            jQuery.ajax({
                url: mxchatAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mxchat_save_setting',
                    name: 'model',
                    value: modelValue,
                    _ajax_nonce: mxchatAdmin.setting_nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success state
                        $clickedCard.find('.mxchat-model-selector-title').html(
                            '<span class="dashicons dashicons-yes" style="color: #46b450; margin-top: 3px;"></span> Saved!'
                        );

                        // Update button text after successful save
                        const selectedModelText = $modelSelect.find('option:selected').text();
                        $('#mxchat_model_selector_btn').text(selectedModelText);

                        // Close modal after a short delay to show the success message
                        setTimeout(function() {
                            $('#mxchat_model_selector_modal').hide();
                            // Restore original content and remove selection for next time
                            $clickedCard.find('.mxchat-model-selector-title').html(originalContent);
                            $clickedCard.removeClass('mxchat-model-selected').find('.mxchat-model-selector-checkmark').remove();
                            $clickedCard.css('pointer-events', 'auto');
                        }, 600);
                    } else {
                        // Show error state
                        $clickedCard.find('.mxchat-model-selector-title').html(
                            '<span class="dashicons dashicons-no" style="color: #dc3232;"></span> Error!'
                        );

                        setTimeout(function() {
                            $clickedCard.find('.mxchat-model-selector-title').html(originalContent);
                            $clickedCard.removeClass('mxchat-model-selected').find('.mxchat-model-selector-checkmark').remove();
                            $clickedCard.css('pointer-events', 'auto');
                        }, 1500);
                    }
                },
                error: function() {
                    // Show error state
                    $clickedCard.find('.mxchat-model-selector-title').html(
                        '<span class="dashicons dashicons-no" style="color: #dc3232;"></span> Error!'
                    );

                    setTimeout(function() {
                        $clickedCard.find('.mxchat-model-selector-title').html(originalContent);
                        $clickedCard.removeClass('mxchat-model-selected').find('.mxchat-model-selector-checkmark').remove();
                        $clickedCard.css('pointer-events', 'auto');
                    }, 1500);
                }
            });
        }, 300); // 300ms delay to show the selection before starting to save
    }
});
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).is('#mxchat_model_selector_modal')) {
            $('#mxchat_model_selector_modal').hide();
        }
    });
}

function loadOpenRouterModels() {
    const apiKey = $('#openrouter_api_key').val(); // This line already re-checks the field
    const $modal = $('#mxchat_model_selector_modal');
    const $modalBody = $modal.find('.mxchat-model-selector-modal-body');
    
    if (!apiKey || apiKey.trim() === '') {
        // Show error message in modal
        $modalBody.html(`
            <div style="text-align: center; padding: 40px;">
                <span class="dashicons dashicons-warning" style="font-size: 48px; color: #d63638; margin-bottom: 20px;"></span>
                <h3>OpenRouter API Key Required</h3>
                <p>Please enter your OpenRouter API key in the settings before selecting a model. If you're seeing this message and recently entered API key, try refreshing.</p>
                <button class="button button-primary" id="mxchat_back_to_models">Back to Models</button>
            </div>
        `);
        
        $('#mxchat_back_to_models').on('click', function(e) {
            e.preventDefault();
            // CHANGE THIS: Instead of reloading, restore the original modal content
            $modalBody.html(`
                <div class="mxchat-model-selector-search-container">
                    <input type="text" id="mxchat_model_search_input" class="mxchat-model-search-input" placeholder="Search models...">
                </div>
                <div class="mxchat-model-selector-categories">
                    <button class="mxchat-model-category-btn active" data-category="all">All</button>
                    <button class="mxchat-model-category-btn" data-category="openrouter">OpenRouter</button>
                    <button class="mxchat-model-category-btn" data-category="gemini">Google Gemini</button>
                    <button class="mxchat-model-category-btn" data-category="openai">OpenAI</button>
                    <button class="mxchat-model-category-btn" data-category="claude">Claude</button>
                    <button class="mxchat-model-category-btn" data-category="xai">X.AI</button>
                    <button class="mxchat-model-category-btn" data-category="deepseek">DeepSeek</button>
                </div>
                <div class="mxchat-model-selector-grid" id="mxchat_models_grid"></div>
            `);
            
            // Re-populate the grid
            populateModelsGrid('', 'all');
            
            // Re-bind event handlers
            rebindModalEventHandlers();
        });
        return;
    }
    
    // Show loading state
    $modalBody.html(`
        <div style="text-align: center; padding: 60px 20px;">
            <div class="spinner is-active" style="float: none; margin: 0 auto 20px;"></div>
            <h3>Loading OpenRouter Models...</h3>
            <p>Fetching available models from OpenRouter</p>
        </div>
    `);
    
    // Fetch models from OpenRouter
    jQuery.ajax({
        url: mxchatAdmin.ajax_url,
        type: 'POST',
        data: {
            action: 'mxchat_fetch_openrouter_models',
            api_key: apiKey,
            nonce: mxchatAdmin.fetch_openrouter_models_nonce
        },
        success: function(response) {
            if (response.success && response.data.models) {
                displayOpenRouterModels(response.data.models);
            } else {
                $modalBody.html(`
                    <div style="text-align: center; padding: 40px;">
                        <span class="dashicons dashicons-warning" style="font-size: 48px; color: #d63638; margin-bottom: 20px;"></span>
                        <h3>Error Loading Models</h3>
                        <p>${response.data.message || 'Failed to load models from OpenRouter'}</p>
                        <button class="button button-primary" id="mxchat_back_to_models">Back to Models</button>
                    </div>
                `);
                
                $('#mxchat_back_to_models').on('click', function(e) {
                    e.preventDefault();
                    // CHANGE THIS: Restore original content instead of reloading
                    restoreOriginalModalContent();
                });
            }
        },
        error: function() {
            $modalBody.html(`
                <div style="text-align: center; padding: 40px;">
                    <span class="dashicons dashicons-warning" style="font-size: 48px; color: #d63638; margin-bottom: 20px;"></span>
                    <h3>Connection Error</h3>
                    <p>Failed to connect to OpenRouter. Please check your API key and try again.</p>
                    <button class="button button-primary" id="mxchat_back_to_models">Back to Models</button>
                </div>
            `);
            
            $('#mxchat_back_to_models').on('click', function(e) {
                e.preventDefault();
                // CHANGE THIS: Restore original content instead of reloading
                restoreOriginalModalContent();
            });
        }
    });
}
function restoreOriginalModalContent() {
    const $modalBody = $('#mxchat_model_selector_modal').find('.mxchat-model-selector-modal-body');
    
    $modalBody.html(`
        <div class="mxchat-model-selector-search-container">
            <input type="text" id="mxchat_model_search_input" class="mxchat-model-search-input" placeholder="Search models...">
        </div>
        <div class="mxchat-model-selector-categories">
            <button class="mxchat-model-category-btn active" data-category="all">All</button>
            <button class="mxchat-model-category-btn" data-category="openrouter">OpenRouter</button>
            <button class="mxchat-model-category-btn" data-category="gemini">Google Gemini</button>
            <button class="mxchat-model-category-btn" data-category="openai">OpenAI</button>
            <button class="mxchat-model-category-btn" data-category="claude">Claude</button>
            <button class="mxchat-model-category-btn" data-category="xai">X.AI</button>
            <button class="mxchat-model-category-btn" data-category="deepseek">DeepSeek</button>
        </div>
        <div class="mxchat-model-selector-grid" id="mxchat_models_grid"></div>
    `);
    
    // Re-populate the grid
    window.populateModelsGrid('', 'all');
    
    // Re-bind event handlers
    rebindModalEventHandlers();
}
function rebindModalEventHandlers() {
    const $modal = $('#mxchat_model_selector_modal');

    // Re-bind category button clicks
    $('.mxchat-model-category-btn').off('click').on('click', function() {
        $('.mxchat-model-category-btn').removeClass('active');
        $(this).addClass('active');
        const category = $(this).data('category');
        const searchTerm = $('#mxchat_model_search_input').val();
        window.populateModelsGrid(searchTerm, category);
    });

    // Re-bind search input
    $('#mxchat_model_search_input').off('input').on('input', function() {
        const searchTerm = $(this).val();
        const activeCategory = $('.mxchat-model-category-btn.active').data('category');
        populateModelsGrid(searchTerm, activeCategory);
    });
}

function restoreDefaultModalFooter() {
    const $modalFooter = $('#mxchat_model_selector_modal').find('.mxchat-model-selector-modal-footer');

    // Restore default footer buttons
    $modalFooter.html(`
        <button id="mxchat_cancel_model_selection" class="button mxchat-model-cancel-btn">Cancel</button>
    `);

    // Re-bind cancel button
    $('#mxchat_cancel_model_selection').on('click', function() {
        $('#mxchat_model_selector_modal').hide();
    });
}

function displayOpenRouterModels(models) {
    const $modal = $('#mxchat_model_selector_modal');
    const $modalBody = $modal.find('.mxchat-model-selector-modal-body');
    const $modalFooter = $modal.find('.mxchat-model-selector-modal-footer');
    const currentSelected = $('#openrouter_selected_model').val();

    // Variable to store the currently selected model (in the UI, not yet saved)
    let pendingSelection = {
        modelId: currentSelected || null,
        modelName: $('#openrouter_selected_model_name').val() || null
    };

    // Build new modal content with search and models
    const newContent = `
        <div class="mxchat-model-selector-search-container">
            <input type="text" id="mxchat_openrouter_search" class="mxchat-model-search-input" placeholder="Search OpenRouter models...">
            <p style="margin: 10px 0; color: #666; font-size: 13px;">
                <strong>${models.length} models available</strong> ·
                <a href="#" id="mxchat_back_to_provider_select" style="color: #2271b1;">← Back to providers</a>
            </p>
        </div>
        <div class="mxchat-model-selector-grid" id="mxchat_openrouter_models_grid"></div>
    `;

    // Update footer with Save button for OpenRouter
    const footerContent = `
        <button id="mxchat_back_to_models_footer" class="button mxchat-model-cancel-btn">Back to Providers</button>
        <button id="mxchat_save_openrouter_model" class="button button-primary" disabled>
            <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span> Save Selected Model
        </button>
    `;

    $modalBody.html(newContent);
    $modalFooter.html(footerContent);
    
    // Function to render models
    function renderOpenRouterModels(filterText = '') {
        const $grid = $('#mxchat_openrouter_models_grid');
        $grid.empty();

        let filteredModels = models;
        if (filterText) {
            const lowerFilter = filterText.toLowerCase();
            filteredModels = models.filter(m =>
                m.id.toLowerCase().includes(lowerFilter) ||
                m.name.toLowerCase().includes(lowerFilter) ||
                (m.description && m.description.toLowerCase().includes(lowerFilter))
            );
        }

        filteredModels.forEach(model => {
            const isSelected = pendingSelection.modelId === model.id;
            const contextLength = model.context_length ? `${(model.context_length / 1000).toFixed(0)}K` : '';
            const promptPrice = model.pricing.prompt ? `$${(model.pricing.prompt * 1000000).toFixed(2)}/1M` : '';

            const $card = jQuery(`
                <div class="mxchat-openrouter-card ${isSelected ? 'mxchat-model-selected' : ''}" data-model-id="${model.id}" data-model-name="${model.name}">
                    <div class="mxchat-model-selector-icon">
                        ${getOpenRouterIcon(model.id)}
                    </div>
                    <div class="mxchat-model-selector-info">
                        <h4 class="mxchat-model-selector-title">${model.name}</h4>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            ${contextLength ? '<span style="margin-right: 12px;">📄 ' + contextLength + '</span>' : ''}
                            ${promptPrice ? '<span>💰 ' + promptPrice + '</span>' : ''}
                        </div>
                    </div>
                    ${isSelected ? '<div class="mxchat-model-selector-checkmark">✓</div>' : ''}
                </div>
            `);

            $grid.append($card);
        });
    }
    
    // Helper to get icon
    function getOpenRouterIcon(modelId) {
        if (modelId.includes('gpt') || modelId.includes('openai')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 320" class="mxchat-model-icon-openai"><path fill="currentColor" d="M297 131a80.6 80.6 0 0 0-93.7-104.2 80.6 80.6 0 0 0-137 29A80.6 80.6 0 0 0 23 189a80.6 80.6 0 0 0 93.7 104.2 80.6 80.6 0 0 0 137-29A80.7 80.7 0 0 0 297.1 131zM176.9 299c-14 .1-27.6-4.8-38.4-13.8l1.9-1 63.7-36.9c3.3-1.8 5.3-5.3 5.2-9v-89.9l27 15.6c.3.1.4.4.5.7v74.4a60 60 0 0 1-60 60zM47.9 244a59.7 59.7 0 0 1-7.1-40.1l1.9 1.1 63.7 36.8c3.2 1.9 7.2 1.9 10.5 0l77.8-45V228c0 .3-.2.6-.4.8L129.9 266a60 60 0 0 1-82-22zM31.2 105c7-12.2 18-21.5 31.2-26.3v75.8c0 3.7 2 7.2 5.2 9l77.8 45-27 15.5a1 1 0 0 1-.9 0L53.1 187a60 60 0 0 1-22-82zm221.2 51.5-77.8-45 27-15.5a1 1 0 0 1 .9 0l64.4 37.1a60 60 0 0 1-9.3 108.2v-75.8c0-3.7-2-7.2-5.2-9zm26.8-40.4-1.9-1.1-63.7-36.8a10.4 10.4 0 0 0-10.5 0L125.4 123V92c0-.3 0-.6.3-.8L190.1 54a60 60 0 0 1 89.1 62.1zm-168.5 55.4-27-15.5a1 1 0 0 1-.4-.7V80.9a60 60 0 0 1 98.3-46.1l-1.9 1L116 72.8a10.3 10.3 0 0 0-5.2 9v89.8zm14.6-31.5 34.7-20 34.6 20v40L160 200l-34.7-20z"></path></svg>';
        } else if (modelId.includes('claude') || modelId.includes('anthropic')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 176" fill="none" class="mxchat-model-icon-claude"><path fill="currentColor" d="m147.487 0l70.081 175.78H256L185.919 0zM66.183 106.221l23.98-61.774l23.98 61.774zM70.07 0L0 175.78h39.18l14.33-36.914h73.308l14.328 36.914h39.179L110.255 0z"></path></svg>';
        } else if (modelId.includes('gemini') || modelId.includes('google')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 48 48" class="mxchat-model-icon-gemini"><defs><path id="a" d="M44.5 20H24v8.5h11.8C34.7 33.9 30.1 37 24 37c-7.2 0-13-5.8-13-13s5.8-13 13-13c3.1 0 5.9 1.1 8.1 2.9l6.4-6.4C34.6 4.1 29.6 2 24 2 11.8 2 2 11.8 2 24s9.8 22 22 22c11 0 21-8 21-22 0-1.3-.2-2.7-.5-4z"></path></defs><clipPath id="b"><use xlink:href="#a" overflow="visible"></use></clipPath><path clip-path="url(#b)" fill="#FBBC05" d="M0 37V11l17 13z"></path><clip-path="url(#b)" fill="#EA4335" d="M0 11l17 13 7-6.1L48 14V0H0z"></path><path clip-path="url(#b)" fill="#34A853" d="M0 37l30-23 7.9 1L48 0v48H0z"></path><path clip-path="url(#b)" fill="#4285F4" d="M48 48L17 24l-4-3 35-10z"></path></svg>';
        }
        return '<span class="dashicons dashicons-cloud" style="font-size: 24px; color: #6750A4;"></span>';
    }
    
    // Initial render
    renderOpenRouterModels();
    
    // Search handler
    $('#mxchat_openrouter_search').on('input', function() {
        renderOpenRouterModels($(this).val());
    });
    
    $('#mxchat_back_to_provider_select').on('click', function(e) {
        e.preventDefault();
        // Restore footer to default state
        restoreDefaultModalFooter();
        // Instead of location.reload(), restore original content
        restoreOriginalModalContent();
    });

    // Back to providers footer button
    $('#mxchat_back_to_models_footer').on('click', function(e) {
        e.preventDefault();
        // Restore footer to default state
        restoreDefaultModalFooter();
        // Restore original content
        restoreOriginalModalContent();
    });

    // Model selection - just highlight, don't save yet
    $(document).on('click', '.mxchat-openrouter-card', function(e) {
        e.stopPropagation(); // Prevent triggering the regular model card handler

        const modelId = $(this).data('model-id');
        const modelName = $(this).data('model-name');

        // Remove selection from all cards
        $('.mxchat-openrouter-card').removeClass('mxchat-model-selected').find('.mxchat-model-selector-checkmark').remove();

        // Add selection to clicked card
        $(this).addClass('mxchat-model-selected');
        if ($(this).find('.mxchat-model-selector-checkmark').length === 0) {
            $(this).append('<div class="mxchat-model-selector-checkmark">✓</div>');
        }

        // Update pending selection
        pendingSelection.modelId = modelId;
        pendingSelection.modelName = modelName;

        // Enable the save button
        $('#mxchat_save_openrouter_model').prop('disabled', false);
    });

    // Save button handler
    $('#mxchat_save_openrouter_model').on('click', function() {
        const $saveButton = $(this);

        if (!pendingSelection.modelId) {
            return;
        }

        // Disable button and show loading state
        $saveButton.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Saving...');

        // First, save that we're using OpenRouter
        jQuery.ajax({
            url: mxchatAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_save_setting',
                name: 'model',
                value: 'openrouter',
                _ajax_nonce: mxchatAdmin.setting_nonce
            },
            success: function() {
                // After model is set, save the model ID
                jQuery.ajax({
                    url: mxchatAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mxchat_save_setting',
                        name: 'openrouter_selected_model',
                        value: pendingSelection.modelId,
                        _ajax_nonce: mxchatAdmin.setting_nonce
                    },
                    success: function() {
                        // Update DOM immediately
                        $('#openrouter_selected_model').val(pendingSelection.modelId);

                        // After model ID is saved, save the display name
                        jQuery.ajax({
                            url: mxchatAdmin.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'mxchat_save_setting',
                                name: 'openrouter_selected_model_name',
                                value: pendingSelection.modelName,
                                _ajax_nonce: mxchatAdmin.setting_nonce
                            },
                            success: function() {
                                // Update DOM immediately
                                $('#openrouter_selected_model_name').val(pendingSelection.modelName);

                                // Update button text
                                $('#mxchat_model_selector_btn').text('OpenRouter: ' + pendingSelection.modelName);

                                // Update the "Currently using" message
                                const $currentSelection = $('#openrouter-current-selection');
                                if ($currentSelection.length) {
                                    $currentSelection.html(
                                        '<span class="dashicons dashicons-yes" style="font-size: 16px; vertical-align: middle;"></span> ' +
                                        'Currently using: <strong>' + pendingSelection.modelName + '</strong>'
                                    ).show();
                                }

                                // Show success state briefly
                                $saveButton.html('<span class="dashicons dashicons-yes" style="color: #46b450; margin-top: 3px;"></span> Saved!');

                                // Close modal after short delay
                                setTimeout(function() {
                                    // Restore footer to default state
                                    restoreDefaultModalFooter();
                                    $modal.hide();
                                }, 800);
                            },
                            error: function() {
                                $saveButton.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top: 3px;"></span> Save Selected Model');
                                alert('Failed to save model name. Please try again.');
                            }
                        });
                    },
                    error: function() {
                        $saveButton.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top: 3px;"></span> Save Selected Model');
                        alert('Failed to save model ID. Please try again.');
                    }
                });
            },
            error: function() {
                $saveButton.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top: 3px;"></span> Save Selected Model');
                alert('Failed to save OpenRouter selection. Please try again.');
            }
        });
    });

}

// Embedding model selector - completely separate from chat model selector
function setupMxChatEmbeddingModelSelector() {
    const $embeddingModelSelect = $('#embedding_model');
    
    // Skip if the element doesn't exist on the page
    if ($embeddingModelSelect.length === 0) {
        return;
    }
    
    const $embeddingModelSelectorButton = $('<button>', {
        type: 'button',
        id: 'mxchat_embedding_model_selector_btn',
        class: 'button-primary mxchat-embedding-model-selector-btn', // Changed class name to be more specific
        text: 'Select Embedding Model'
    });
    
    // Replace the select dropdown with a button
    $embeddingModelSelect.hide().after($embeddingModelSelectorButton);
    
    // Update button text to show currently selected model
    function updateButtonText() {
        const selectedModel = $embeddingModelSelect.val();
        const selectedModelText = $embeddingModelSelect.find('option:selected').text();
        $embeddingModelSelectorButton.text(selectedModelText);
    }
    
    // Initialize button text
    updateButtonText();
    
    // Create a unique ID for the modal to avoid conflicts
    const embeddingModalId = 'mxchat_embedding_model_selector_modal';
    
    // Create and append modal HTML with unique IDs
    const embeddingModelSelectorModal = `
        <div id="${embeddingModalId}" class="mxchat-embedding-model-selector-modal">
            <div class="mxchat-embedding-model-selector-modal-content">
                <div class="mxchat-embedding-model-selector-modal-header">
                    <h3>Select Embedding Model</h3>
                    <span class="mxchat-embedding-model-selector-modal-close">&times;</span>
                </div>
                <div class="mxchat-embedding-model-selector-modal-body">
                    <div class="mxchat-embedding-model-selector-search-container">
                        <input type="text" id="mxchat_embedding_model_search_input" class="mxchat-embedding-model-search-input" placeholder="Search models...">
                    </div>
                    <div class="mxchat-embedding-model-selector-categories">
                        <button class="mxchat-embedding-model-category-btn active" data-category="all">All</button>
                        <button class="mxchat-embedding-model-category-btn" data-category="openai">OpenAI</button>
                        <button class="mxchat-embedding-model-category-btn" data-category="voyage">Voyage AI</button>
                        <button class="mxchat-embedding-model-category-btn" data-category="gemini">Google Gemini</button>
                    </div>
                    <div class="mxchat-embedding-model-selector-grid" id="mxchat_embedding_models_grid"></div>
                </div>
                <div class="mxchat-embedding-model-selector-modal-footer">
                    <button id="mxchat_cancel_embedding_model_selection" class="button mxchat-embedding-model-cancel-btn">Cancel</button>
                </div>
            </div>
        </div>
    `;
    
    // Use jQuery's append to ensure it doesn't clash with existing modals
    $('body').append(embeddingModelSelectorModal);
    
    // Populate models grid
    function populateEmbeddingModelsGrid(filter = '', category = 'all') {
        const $grid = $('#mxchat_embedding_models_grid');
        $grid.empty();
        
        // Define embedding models with descriptions and context lengths
        const models = {
            openai: [
                { 
                    value: 'text-embedding-3-small', 
                    label: 'TE3 Small', 
                    description: 'Fast and cost-effective embeddings (1536 dimensions, 8K context)'
                },
                { 
                    value: 'text-embedding-ada-002', 
                    label: 'Ada 2', 
                    description: 'Balanced performance embeddings (1536 dimensions, 8K context)'
                },
                { 
                    value: 'text-embedding-3-large', 
                    label: 'TE3 Large', 
                    description: 'High-performance embeddings (3072 dimensions, 8K context)'
                }
            ],
            voyage: [
                { 
                    value: 'voyage-3-large', 
                    label: 'Voyage-3 Large', 
                    description: 'Advanced semantic search embeddings (2048 dimensions, 32K context)'
                }
            ],
            gemini: [
                { 
                    value: 'gemini-embedding-001',
                    label: 'Gemini Embedding',
                    description: 'Stable SOTA embeddings (1536 dimensions, 8K context)'
                }
            ]
        };
        
        let allModels = [];
        Object.keys(models).forEach(key => {
            if (category === 'all' || category === key) {
                allModels = allModels.concat(models[key]);
            }
        });
        
        // Filter by search term if present
        if (filter) {
            const lowerFilter = filter.toLowerCase();
            allModels = allModels.filter(model => 
                model.label.toLowerCase().includes(lowerFilter) || 
                model.description.toLowerCase().includes(lowerFilter)
            );
        }
        
        // Create model cards
        allModels.forEach(model => {
            const isSelected = $embeddingModelSelect.val() === model.value;
            let providerClass = 'mxchat-embedding-model-provider-openai';
            
            if (model.value.startsWith('voyage-')) {
                providerClass = 'mxchat-embedding-model-provider-voyage';
            } else if (model.value.startsWith('gemini-embedding-')) {
                providerClass = 'mxchat-embedding-model-provider-gemini';
            }
            
            let iconHTML = '';
            if (model.value.startsWith('voyage-')) {
                iconHTML = '<span class="dashicons dashicons-chart-line mxchat-embedding-model-icon-voyage"></span>';
            } else if (model.value.startsWith('gemini-embedding-')) {
                iconHTML = '<span class="dashicons dashicons-google mxchat-embedding-model-icon-gemini"></span>';
            } else {
                iconHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 320" class="mxchat-embedding-model-icon-openai"><path fill="currentColor" d="M297 131a80.6 80.6 0 0 0-93.7-104.2 80.6 80.6 0 0 0-137 29A80.6 80.6 0 0 0 23 189a80.6 80.6 0 0 0 93.7 104.2 80.6 80.6 0 0 0 137-29A80.7 80.7 0 0 0 297.1 131zM176.9 299c-14 .1-27.6-4.8-38.4-13.8l1.9-1 63.7-36.9c3.3-1.8 5.3-5.3 5.2-9v-89.9l27 15.6c.3.1.4.4.5.7v74.4a60 60 0 0 1-60 60zM47.9 244a59.7 59.7 0 0 1-7.1-40.1l1.9 1.1 63.7 36.8c3.2 1.9 7.2 1.9 10.5 0l77.8-45V228c0 .3-.2.6-.4.8L129.9 266a60 60 0 0 1-82-22zM31.2 105c7-12.2 18-21.5 31.2-26.3v75.8c0 3.7 2 7.2 5.2 9l77.8 45-27 15.5a1 1 0 0 1-.9 0L53.1 187a60 60 0 0 1-22-82zm221.2 51.5-77.8-45 27-15.5a1 1 0 0 1 .9 0l64.4 37.1a60 60 0 0 1-9.3 108.2v-75.8c0-3.7-2-7.2-5.2-9zm26.8-40.4-1.9-1.1-63.7-36.8a10.4 10.4 0 0 0-10.5 0L125.4 123V92c0-.3 0-.6.3-.8L190.1 54a60 60 0 0 1 89.1 62.1zm-168.5 55.4-27-15.5a1 1 0 0 1-.4-.7V80.9a60 60 0 0 1 98.3-46.1l-1.9 1L116 72.8a10.3 10.3 0 0 0-5.2 9v89.8zm14.6-31.5 34.7-20 34.6 20v40L160 200l-34.7-20z"></path></svg>';
            }
            
            const $modelCard = $(`
                <div class="mxchat-embedding-model-selector-card ${isSelected ? 'mxchat-embedding-model-selected' : ''} ${providerClass}" data-value="${model.value}">
                    <div class="mxchat-embedding-model-selector-icon">
                        ${iconHTML}
                    </div>
                    <div class="mxchat-embedding-model-selector-info">
                        <h4 class="mxchat-embedding-model-selector-title">${model.label}</h4>
                        <p class="mxchat-embedding-model-selector-description">${model.description}</p>
                    </div>
                    ${isSelected ? '<div class="mxchat-embedding-model-selector-checkmark">✓</div>' : ''}
                </div>
            `);
            
            $grid.append($modelCard);
        });
    }
    
    // Event handlers - use namespaced events to avoid conflicts
    $embeddingModelSelectorButton.on('click.embeddingModelSelector', function(e) {
        e.stopPropagation(); // Prevent event bubbling
        $('#' + embeddingModalId).show();
        populateEmbeddingModelsGrid('', 'all');
    });
    
    $('.mxchat-embedding-model-selector-modal-close, #mxchat_cancel_embedding_model_selection').on('click.embeddingModelSelector', function(e) {
        e.stopPropagation(); // Prevent event bubbling
        $('#' + embeddingModalId).hide();
    });
    
    $('.mxchat-embedding-model-selector-categories .mxchat-embedding-model-category-btn').on('click.embeddingModelSelector', function(e) {
        e.stopPropagation(); // Prevent event bubbling
        $('.mxchat-embedding-model-selector-categories .mxchat-embedding-model-category-btn').removeClass('active');
        $(this).addClass('active');
        const category = $(this).data('category');
        const searchTerm = $('#mxchat_embedding_model_search_input').val();
        populateEmbeddingModelsGrid(searchTerm, category);
    });
    
    $('#mxchat_embedding_model_search_input').on('input.embeddingModelSelector', function() {
        const searchTerm = $(this).val();
        const activeCategory = $('.mxchat-embedding-model-selector-categories .mxchat-embedding-model-category-btn.active').data('category');
        populateEmbeddingModelsGrid(searchTerm, activeCategory);
    });
    
    // Use a direct selector to avoid conflicts with other card elements
    $(document).on('click.embeddingModelSelector', '.mxchat-embedding-model-selector-grid .mxchat-embedding-model-selector-card', function(e) {
        e.stopPropagation(); // Prevent event bubbling
        const modelValue = $(this).data('value');
        
        // Important: Only update this specific select element
        $embeddingModelSelect.val(modelValue);
        
        // Manually trigger change only on this element
        const changeEvent = new Event('change', { bubbles: true });
        $embeddingModelSelect[0].dispatchEvent(changeEvent);
        
        // Update button text
        updateButtonText();
        
        // Hide modal
        $('#' + embeddingModalId).hide();
    });
    
    // Close modal when clicking outside - use namespaced events
    $(window).on('click.embeddingModelSelector', function(event) {
        if ($(event.target).is('#' + embeddingModalId)) {
            $('#' + embeddingModalId).hide();
        }
    });
}

// Call this function after the DOM is fully loaded
$(document).ready(function() {
    setupMxChatModelSelector();
    setupMxChatEmbeddingModelSelector();
});

    // Add Intent Form Submission
    $('#mxchat-add-intent-form').on('submit', function(event) {
        $('#mxchat-intent-loading').show();
        $('#mxchat-intent-loading-text').show();
        $(this).find('button[type="submit"]').hide();
    });
    
    // Inline Edit Functionality
    $('.edit-button').on('click', function() {
        var row = $(this).closest('tr');

        // Clear URL field if it's a manual content URL (mxchat:// protocol)
        var urlEdit = row.find('.url-edit');
        if (urlEdit.length && urlEdit.val().indexOf('mxchat://') === 0) {
            urlEdit.val('');
        }

        // Expand the accordion to show the edit textarea (fixes short content editing)
        var contentFull = row.find('.mxchat-content-full');
        if (contentFull.length && contentFull.is(':hidden')) {
            contentFull.show();
            row.find('.mxchat-content-preview').hide();
        }

        row.find('.content-view, .url-view').hide();
        row.find('.content-edit, .url-edit').show();
        row.find('.edit-button').hide();
        row.find('.save-button').show();
    });
    
    // Save button handler
// Save button handler
$('.save-button').on('click', function() {
    var button = $(this);
    var row = button.closest('tr');
    var id = button.data('id');
    var nonce = button.data('nonce'); // Get nonce from button data attribute
    var newContent = row.find('.content-edit').val();
    var newUrl = row.find('.url-edit').val();

    //console.log('Nonce from button:', nonce); // Debug

    button.prop('disabled', true);
    button.text('Saving...');

    $.ajax({
        url: mxchatAdmin.ajax_url,
        type: 'POST',
        data: {
            action: 'mxchat_save_inline_prompt',
            id: id,
            article_content: newContent,
            article_url: newUrl,
            _ajax_nonce: nonce  // Use nonce from button
        },
        success: function(response) {
            button.prop('disabled', false);
            button.text('Save');

            if (response.success) {
                row.find('.content-view').html(newContent.replace(/\n/g, "<br>"));
                if (newUrl) {
                    row.find('.url-view').html('<a href="' + newUrl + '" target="_blank"><span class="dashicons dashicons-external"></span> View Source</a>');
                } else {
                    row.find('.url-view').html('<span class="mxchat-na">Manual Content</span>');
                }

                row.find('.content-edit, .url-edit').hide();
                row.find('.content-view, .url-view').show();
                row.find('.save-button').hide();
                row.find('.edit-button').show();

                // Restore accordion state - show preview, hide full content
                row.find('.mxchat-content-preview').show();
                row.find('.mxchat-content-full').hide();
            } else {
                alert('Error saving content: ' + (response.data?.message || 'Unknown error'));
            }
        },
        error: function() {
            button.prop('disabled', false);
            button.text('Save');
            alert('An error occurred while saving.');
        }
    });
});
    
    
    // Questions handling
    $('.mxchat-add-question').on('click', function () {
        const container = $('#mxchat-additional-questions-container');
        const questionCount = container.find('.mxchat-question-row').length + 4;
        const questionIndex = container.find('.mxchat-question-row').length;
    
        const newQuestion = `
            <div class="mxchat-question-row">
                <input type="text"
                       name="additional_popular_questions[]"
                       placeholder="Enter Additional Popular Question ${questionCount}"
                       class="regular-text mxchat-question-input"
                       data-question-index="${questionIndex}" />
                <button type="button" class="button mxchat-remove-question"
                        aria-label="Remove question">Remove</button>
            </div>
        `;
        container.append(newQuestion);
    });
    
    $(document).on('click', '.mxchat-remove-question', function () {
        $(this).closest('.mxchat-question-row').remove();
        saveQuestions();
    });
    
    $(document).on('change', '.mxchat-question-input', function() {
        saveQuestions();
    });
    
    function saveQuestions() {
        const questions = [];
        $('.mxchat-question-input').each(function() {
            const value = $(this).val().trim();
            if (value) {
                questions.push(value);
            }
        });
    
        const feedbackContainer = $('<div class="feedback-container"></div>');
        const spinner = $('<div class="saving-spinner"></div>');
        const successIcon = $('<div class="success-icon">✔</div>');
    
        // Append feedback after the add button
        $('.mxchat-add-question').after(feedbackContainer);
        feedbackContainer.append(spinner);
    
        // Save via AJAX
        $.ajax({
            url: mxchatAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_save_setting',
                name: 'additional_popular_questions',
                value: JSON.stringify(questions),
                _ajax_nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success) {
                    spinner.fadeOut(200, function() {
                        feedbackContainer.append(successIcon);
                        successIcon.fadeIn(200).delay(1000).fadeOut(200, function() {
                            feedbackContainer.remove();
                        });
                    });
                } else {
                    alert('Error saving questions: ' + (response.data?.message || 'Unknown error'));
                    feedbackContainer.remove();
                }
            },
            error: function() {
                alert('An error occurred while saving questions.');
                feedbackContainer.remove();
            }
        });
    }
    
    // Live agent status handler
    const statusToggle = document.getElementById('live_agent_status');
    const statusText = statusToggle?.parentElement.nextElementSibling?.querySelector('.status-text');
    if (statusToggle && statusText) {
        statusToggle.addEventListener('change', function() {
            // Update display text
            statusText.textContent = this.checked ? 'Online' : 'Offline';
            
            // Send the correct on/off value to the server
            if (window.mxchatSaveSetting) {
                window.mxchatSaveSetting('live_agent_status', this.checked ? 'on' : 'off');
            }
        });
    }
    
    // Function to adjust the textarea height to content
    function adjustTextareaHeight() {
        this.style.height = 'auto'; // Reset to auto to calculate scrollHeight
        this.style.height = this.scrollHeight + 'px'; // Expand to content height
    }

    // Function to reset the textarea height to initial
    function resetTextareaHeight() {
        this.style.height = ''; // Remove inline height, reverting to CSS default
    }

    // Target the specific textarea by ID
    var $textarea = $('#system_prompt_instructions');

    // Bind events
    $textarea.on('focus input', adjustTextareaHeight) // Expand on focus and input
             .on('blur', resetTextareaHeight); // Reset on blur
});



document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the correct page before initializing
    const modal = document.getElementById('mxchat-action-modal');
    
    // Only initialize if the modal exists on this page
    if (modal) {
        //console.log('MXChat Action Modal JS Loaded');
        
        // Initialize the action modal functionality
        initStepBasedActionModal();
    }
    
    // Function to initialize the step-based action modal
    function initStepBasedActionModal() {
        // We already checked for modal existence above, so no need to check again
        
        const actionStep1 = document.getElementById('mxchat-action-step-1');
        const actionStep2 = document.getElementById('mxchat-action-step-2');
        const backToStep1Btn = document.getElementById('mxchat-back-to-step-1');
        const searchInput = document.getElementById('action-type-search');
        const categoryButtons = modal.querySelectorAll('.mxchat-category-button');
        const actionCards = modal.querySelectorAll('.mxchat-action-type-card');
        const actionForm = document.getElementById('mxchat-action-form');
        const callbackInput = document.getElementById('callback_function');
        const actionIdField = document.getElementById('edit_action_id');
        const labelField = document.getElementById('intent_label');
        const phrasesField = document.getElementById('action_phrases');
        const formActionType = document.getElementById('form_action_type');
        const nonceContainer = document.getElementById('action-nonce-container');
        const thresholdSlider = document.getElementById('similarity_threshold');
        const thresholdDisplay = document.querySelector('.mxchat-threshold-value-display');
        
        // Rest of your initialization code remains the same...
        
        // Log the structure of one action card for debugging
        if (actionCards.length > 0) {
            //console.log('First action card data attributes:', actionCards[0].dataset);
            //console.log('First action card HTML:', actionCards[0].outerHTML);
        }
        
        // Add click event listeners to category buttons
        categoryButtons.forEach(button => {
            button.addEventListener('click', function() {
                //console.log('Category button clicked:', this.dataset.category);
                
                // Remove active class from all buttons
                categoryButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Get selected category
                const category = this.dataset.category;
                
                // Filter action cards
                filterActionCards(category, searchInput.value);
            });
        });
        
        // Add search functionality
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                // Get active category
                const activeCategory = modal.querySelector('.mxchat-category-button.active')?.dataset.category || 'all';
                //console.log('Search input changed, active category:', activeCategory);
                
                // Filter action cards
                filterActionCards(activeCategory, this.value);
            });
        }
        
        // Add click event listeners to action cards
        actionCards.forEach(card => {
            card.addEventListener('click', function() {
                // Get the action data
                const isPro = this.dataset.pro === 'true';
                const isInstalled = this.dataset.installed === 'true';
                const addonName = this.dataset.addon || '';
                const actionValue = this.dataset.value;
                const actionLabel = this.dataset.label;
                const actionIcon = this.querySelector('.dashicons').getAttribute('class').replace('dashicons dashicons-', '');
                const actionDescription = this.querySelector('p').textContent;
                
                // Pro check using the proper detection method
                const proIsActivated = typeof mxchatAdmin !== 'undefined' && 
                                     (mxchatAdmin.is_activated === '1' || 
                                      mxchatAdmin.is_activated === 'true' || 
                                      mxchatAdmin.is_activated === true);
                
                // Handle different states
                if (isPro && !proIsActivated) {
                    // Pro feature but no Pro license
                    showProFeatureNotice();
                    return;
                }
                
                if (addonName && !isInstalled) {
                    // Add-on required but not installed
                    const addonDisplayName = this.querySelector('.mxchat-addon-info')?.textContent?.replace('Requires ', '') || addonName + ' Add-on';
                    showAddonRequiredNotice(addonDisplayName);
                    return;
                }
                
                // If we get here, the action is available - proceed as normal
                callbackInput.value = actionValue;
                
                // Update the selected action display in step 2
                document.getElementById('selected-action-title').textContent = actionLabel;
                document.getElementById('selected-action-description').textContent = actionDescription;
                document.getElementById('selected-action-icon').innerHTML = 
                    `<span class="dashicons dashicons-${actionIcon}"></span>`;
                
                // Set a default label based on the action type (user can change it)
                if (!labelField.value) {
                    labelField.value = actionLabel;
                }
                
                // Move to step 2
                actionStep1.classList.remove('active');
                actionStep2.classList.add('active');
                
                // Update modal title
            });
        });
        
        // Back button functionality
        if (backToStep1Btn) {
            backToStep1Btn.addEventListener('click', function() {
                //console.log('Back button clicked');
                actionStep2.classList.remove('active');
                actionStep1.classList.add('active');
            });
        }
        
        // Function to filter action cards by category and search term
        function filterActionCards(category, searchTerm) {
            searchTerm = searchTerm.toLowerCase().trim();
            //console.log(`Filtering cards by category: "${category}", search: "${searchTerm}"`);
            
            let visibleCount = 0;
            
            // Show all cards initially with animation
            actionCards.forEach((card, index) => {
                // Reset animation
                card.style.animation = 'none';
                // Trigger reflow
                void card.offsetWidth;
                
                // Determine if card should be visible based on category and search term
                const cardCategory = card.dataset.category || '';
                const matchesCategory = category === 'all' || cardCategory === category;
                
                const cardTitle = card.querySelector('h4')?.textContent?.toLowerCase() || '';
                const cardDesc = card.querySelector('p')?.textContent?.toLowerCase() || '';
                const matchesSearch = searchTerm === '' || 
                                    cardTitle.includes(searchTerm) || 
                                    cardDesc.includes(searchTerm);
                
                // Show/hide card with animation
                if (matchesCategory && matchesSearch) {
                    card.style.display = 'flex';
                    // Staggered animation for cards
                    card.style.animation = `fadeIn 0.2s ease forwards ${index * 0.03}s`;
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            //console.log(`Filter results: ${visibleCount} cards visible out of ${actionCards.length}`);
        }
        
        // Function to show notice for Pro features
        function showProFeatureNotice() {
            //console.log('Showing Pro feature notice');
            // Check if we already have a notification container
            let noticeContainer = document.querySelector('.mxchat-pro-notice');
            
            if (!noticeContainer) {
                // Create the notice container
                noticeContainer = document.createElement('div');
                noticeContainer.className = 'mxchat-pro-notice';
                
                // Create content
                noticeContainer.innerHTML = `
                    <div class="mxchat-pro-notice-content">
                        <h3>MxChat Pro Feature</h3>
                        <p>This action is available in the Pro version only.</p>
                        <div class="mxchat-pro-notice-buttons">
                            <button class="mxchat-button-secondary mxchat-pro-notice-close">Close</button>
                            <a href="https://mxchat.ai/" class="mxchat-button-primary">Upgrade to Pro</a>
                        </div>
                    </div>
                `;
                
                // Append to body
                document.body.appendChild(noticeContainer);
                
                // Add close functionality
                const closeButton = noticeContainer.querySelector('.mxchat-pro-notice-close');
                closeButton.addEventListener('click', function() {
                    noticeContainer.classList.remove('active');
                    setTimeout(() => {
                        noticeContainer.remove();
                    }, 300);
                });
                
                // Click outside to close
                noticeContainer.addEventListener('click', function(e) {
                    if (e.target === noticeContainer) {
                        closeButton.click();
                    }
                });
                
                // Show with animation
                setTimeout(() => {
                    noticeContainer.classList.add('active');
                }, 10);
            } else {
                // If it already exists, just make it visible again
                noticeContainer.classList.add('active');
            }
        }

        // Function to show notice for add-on requirements
        function showAddonRequiredNotice(addonName) {
            //console.log(`Showing add-on notice for: ${addonName}`);
            // Check if we already have a notification container
            let noticeContainer = document.querySelector('.mxchat-addon-notice');
            
            if (!noticeContainer) {
                // Create the notice container
                noticeContainer = document.createElement('div');
                noticeContainer.className = 'mxchat-addon-notice';
                
                // Create content
                noticeContainer.innerHTML = `
                    <div class="mxchat-addon-notice-content">
                        <span class="mxchat-addon-notice-icon">🧩</span>
                        <h3>Add-on Required</h3>
                        <p>This action requires the <strong>${addonName}</strong> add-on to be installed.</p>
                        <div class="mxchat-addon-notice-buttons">
                            <button class="mxchat-button-secondary mxchat-addon-notice-close">Close</button>
                            <a href="admin.php?page=mxchat-addons" class="mxchat-button-primary">Get Add-ons</a>
                        </div>
                    </div>
                `;
                
                // Append to body
                document.body.appendChild(noticeContainer);
                
                // Add close functionality
                const closeButton = noticeContainer.querySelector('.mxchat-addon-notice-close');
                closeButton.addEventListener('click', function() {
                    noticeContainer.classList.remove('active');
                    setTimeout(() => {
                        noticeContainer.remove();
                    }, 300);
                });
                
                // Click outside to close
                noticeContainer.addEventListener('click', function(e) {
                    if (e.target === noticeContainer) {
                        closeButton.click();
                    }
                });
                
                // Show with animation
                setTimeout(() => {
                    noticeContainer.classList.add('active');
                }, 10);
            } else {
                // If it already exists, update the content
                const addonNameElement = noticeContainer.querySelector('p strong');
                if (addonNameElement) {
                    addonNameElement.textContent = addonName;
                }
                
                // Make it visible again
                noticeContainer.classList.add('active');
            }
        }
        
        // Form submission handling
        if (actionForm) {
            actionForm.addEventListener('submit', function() {
                //console.log('Form submitted');
                document.getElementById('mxchat-action-loading').style.display = 'flex';
                this.querySelector('button[type="submit"]').disabled = true;
            });
        }
    }
    
    // Setup add action buttons (only if we're on the correct page)
    if (modal) {
        // Update the modal open function to support the step-based flow
        window.mxchatOpenActionModal = function(isEdit = false, actionId = '', label = '', phrases = '', threshold = 85, callbackFunction = '', enabledBots = null) {
            //console.log('Modal opening, edit mode:', isEdit);
            
            // Get form fields
            const actionIdField = document.getElementById('edit_action_id');
            const labelField = document.getElementById('intent_label');
            const phrasesField = document.getElementById('action_phrases');
            const formActionType = document.getElementById('form_action_type');
            const callbackInput = document.getElementById('callback_function'); 
            const saveButton = document.getElementById('mxchat-save-action-btn');
            const nonceContainer = document.getElementById('action-nonce-container');
            const thresholdSlider = document.getElementById('similarity_threshold');
            const thresholdDisplay = document.querySelector('.mxchat-threshold-value-display');
            const actionStep1 = document.getElementById('mxchat-action-step-1');
            const actionStep2 = document.getElementById('mxchat-action-step-2');
            const searchInput = document.getElementById('action-type-search');
            
            // Set up modal for edit or create
            if (isEdit) {
                saveButton.textContent = 'Update Action';
                formActionType.value = 'mxchat_edit_intent';
                actionIdField.value = actionId;
                labelField.value = label;
                phrasesField.value = phrases;
                callbackInput.value = callbackFunction;
                thresholdSlider.value = threshold; // Set the current threshold value
                thresholdDisplay.textContent = threshold + '%'; // Update display
                
                // NEW: Handle bot selection checkboxes for editing
                // First uncheck all bot checkboxes
                document.querySelectorAll('input[name="enabled_bots[]"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Then check the ones that should be enabled
                if (enabledBots) {
                    let botsArray;
                    if (typeof enabledBots === 'string') {
                        try {
                            botsArray = JSON.parse(enabledBots);
                        } catch (e) {
                            console.warn('Failed to parse enabledBots:', enabledBots);
                            botsArray = ['default']; // fallback
                        }
                    } else if (Array.isArray(enabledBots)) {
                        botsArray = enabledBots;
                    } else {
                        botsArray = ['default']; // fallback
                    }
                    
                    botsArray.forEach(botId => {
                        const checkbox = document.querySelector(`input[name="enabled_bots[]"][value="${botId}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                } else {
                    // Fallback to default if no bot data
                    const defaultCheckbox = document.querySelector('input[name="enabled_bots[]"][value="default"]');
                    if (defaultCheckbox) {
                        defaultCheckbox.checked = true;
                    }
                }
                
                // Update the nonce field for editing
                nonceContainer.innerHTML = '';  // Clear existing nonce
                if (typeof mxchatAdmin !== 'undefined' && mxchatAdmin.edit_intent_nonce) {
                    nonceContainer.innerHTML = `<input type="hidden" name="_wpnonce" value="${mxchatAdmin.edit_intent_nonce}">`;
                }
                
                // For editing, go directly to step 2 and update the selected action display
                actionStep1.classList.remove('active');
                actionStep2.classList.add('active');
                
                // Find the matching action card to get its details
                const actionCards = document.querySelectorAll('.mxchat-action-type-card');
                let foundCard = null;
                
                actionCards.forEach(card => {
                    if (card.dataset.value === callbackFunction) {
                        foundCard = card;
                    }
                });
                
                if (foundCard) {
                    //console.log('Found matching action card for:', callbackFunction);
                    const actionLabel = foundCard.dataset.label || foundCard.querySelector('h4')?.textContent || '';
                    const actionIconElement = foundCard.querySelector('.dashicons');
                    const actionIcon = actionIconElement 
                        ? actionIconElement.getAttribute('class').replace('dashicons dashicons-', '') 
                        : 'admin-generic';
                    const actionDescription = foundCard.querySelector('p')?.textContent || '';
                    
                    document.getElementById('selected-action-title').textContent = actionLabel;
                    document.getElementById('selected-action-description').textContent = actionDescription;
                    document.getElementById('selected-action-icon').innerHTML = 
                        `<span class="dashicons dashicons-${actionIcon}"></span>`;
                } else {
                    //console.log('No matching action card found for:', callbackFunction);
                    // Fallback if we can't find the card
                    document.getElementById('selected-action-title').textContent = label;
                    document.getElementById('selected-action-description').textContent = 'Configure this action for your chatbot';
                    document.getElementById('selected-action-icon').innerHTML = 
                        `<span class="dashicons dashicons-admin-generic"></span>`;
                }
            } else {
                //console.log('Setting up create mode');
                saveButton.textContent = 'Save Action';
                formActionType.value = 'mxchat_add_intent';
                actionIdField.value = '';
                labelField.value = '';
                phrasesField.value = '';
                callbackInput.value = '';
                thresholdSlider.value = 85; // Default value for new actions
                thresholdDisplay.textContent = '85%'; // Default display
                
                // For new actions, ensure default is checked and others are unchecked
                document.querySelectorAll('input[name="enabled_bots[]"]').forEach(checkbox => {
                    checkbox.checked = (checkbox.value === 'default');
                });
                
                // Update the nonce field for adding
                nonceContainer.innerHTML = '';  // Clear existing nonce
                if (typeof mxchatAdmin !== 'undefined' && mxchatAdmin.add_intent_nonce) {
                    nonceContainer.innerHTML = `<input type="hidden" name="_wpnonce" value="${mxchatAdmin.add_intent_nonce}">`;
                }
                
                // For creating new, start at step 1
                actionStep1.classList.add('active');
                actionStep2.classList.remove('active');
            }
            
            // Show modal with animation
            modal.style.display = 'flex';
            requestAnimationFrame(() => {
                modal.classList.add('active');
            });
            
            // Set up close handlers
            const closeModal = () => {
                //console.log('Closing modal');
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300); // Match the CSS transition time
            };
            
            // Close button handler
            const closeBtn = modal.querySelector('.mxchat-modal-close');
            if (closeBtn) {
                closeBtn.onclick = closeModal;
            }
            
            // Cancel button handler
            const cancelBtns = modal.querySelectorAll('.mxchat-modal-cancel');
            if (cancelBtns) {
                cancelBtns.forEach(btn => {
                    btn.onclick = closeModal;
                });
            }
            
            // Click outside modal to close
            modal.onclick = (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            };
            
            // Escape key to close modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeModal();
                }
            }, { once: true });
            
            // Focus appropriate field based on current step
            if (isEdit || actionStep2.classList.contains('active')) {
                if (labelField) labelField.focus();
            } else {
                if (searchInput) searchInput.focus();
            }
            
            return closeModal; // Return close function for external use
        };
        // Setup add action buttons
        const addActionBtn = document.getElementById('mxchat-add-action-btn');
        if (addActionBtn) {
            //console.log('Add action button found');
            addActionBtn.onclick = () => window.mxchatOpenActionModal();
        }
        
        const createFirstAction = document.getElementById('mxchat-create-first-action');
        if (createFirstAction) {
            //console.log('Create first action button found');
            createFirstAction.onclick = () => window.mxchatOpenActionModal();
        }
        
        // Setup edit buttons
        const editButtons = document.querySelectorAll('.mxchat-action-card .mxchat-edit-button');
        //console.log('Edit buttons found:', editButtons.length);
            editButtons.forEach(button => {
                button.onclick = () => {
                    const actionId = button.dataset.actionId;
                    const phrases = button.dataset.phrases;
                    const label = button.dataset.label;
                    const threshold = button.dataset.threshold || 85;
                    const callbackFunction = button.dataset.callbackFunction;
                    const enabledBots = button.dataset.enabledBots; // ADD THIS LINE
            
                    window.mxchatOpenActionModal(true, actionId, label, phrases, threshold, callbackFunction, enabledBots);
                };
            });
    }
});

jQuery(document).ready(function($) {
    // Auto-expand custom post types container if any are checked
    // Note: Click handler is in admin-knowledge-page.php inline script (initCustomPostTypesToggle)
    function autoExpandIfNeeded() {
        const $container = $('#mxchat-custom-post-types-container');
        const $toggleBtn = $('#mxchat-custom-post-types-toggle');

        if ($container.length === 0) return;

        const hasCheckedItems = $container.find('input[type="checkbox"]:checked').length > 0;

        if (hasCheckedItems) {
            $container.show();
            const $icon = $toggleBtn.find('span:last-child');
            if ($icon.length) {
                $icon.text('▲');
            }
        }
    }

    // Run on page load
    autoExpandIfNeeded();
});

document.addEventListener('DOMContentLoaded', function() {
    var viewSampleBtn = document.getElementById('mxchatViewSampleBtn');
    var modal = document.getElementById('mxchatSampleModal');
    var modalClose = document.getElementById('mxchatModalClose');
    var closeBtn = document.getElementById('mxchatCloseBtn');
    var copyBtn = document.getElementById('mxchatCopyBtn');
    var instructionsContent = document.querySelector('.mxchat-instructions-content');
    var modalContent = document.querySelector('.mxchat-instructions-modal-content');

    if (!viewSampleBtn || !modal) {
        return;
    }

    // Open modal
    viewSampleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        modal.classList.add('mxchat-instructions-show');
    });

    // Close modal function
    function closeModal(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        modal.classList.remove('mxchat-instructions-show');
    }

    // Close modal events
    if (modalClose) {
        modalClose.addEventListener('click', function(e) {
            closeModal(e);
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            closeModal(e);
        });
    }

    // Close on backdrop click ONLY (not on hover)
    modal.addEventListener('click', function(e) {
        // Only close if clicking directly on the overlay, not on child elements
        if (e.target === modal) {
            closeModal(e);
        }
    });

    // Prevent modal content clicks from closing the modal
    if (modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('mxchat-instructions-show')) {
            closeModal();
        }
    });

    // Copy functionality
    if (copyBtn && instructionsContent) {
        copyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var text = instructionsContent.textContent;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess();
                }).catch(function() {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        });
    }

    function fallbackCopy(text) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showCopySuccess();
        } catch (err) {
            console.error('Copy failed');
        }
        document.body.removeChild(textArea);
    }

    function showCopySuccess() {
        var originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20,6 9,17 4,12"/></svg>Copied!';
        
        setTimeout(function() {
            copyBtn.innerHTML = originalText;
        }, 2000);
    }
});

jQuery(document).ready(function($) {
    var emailDependentFields = [
        '#email_blocker_header_content',
        '#email_blocker_button_text',
        '#enable_name_field', 
        '#name_field_placeholder'
    ];
    
    // Add visual indicators
    emailDependentFields.forEach(function(fieldId) {
        var $row = $(fieldId).closest('tr');
        $row.addClass('email-dependent-field');
        
        // Add an icon to show it's dependent
        var $label = $row.find('th label, th');
        $label.prepend('<span class="email-dependent-icon" style="color: #0073aa; margin-right: 5px;">↳</span>');
    });
    
    // Hide initially with opacity for smoother transition
    emailDependentFields.forEach(function(fieldId) {
        $(fieldId).closest('tr').hide().css('opacity', '0');
    });
    
    function toggleEmailFields() {
        var emailEnabled = $('#enable_email_block').is(':checked');
        
        emailDependentFields.forEach(function(fieldId) {
            var $row = $(fieldId).closest('tr');
            if (emailEnabled) {
                $row.slideDown(400).animate({opacity: 1}, 200);
            } else {
                $row.animate({opacity: 0}, 200).slideUp(400);
            }
        });
    }
    
    toggleEmailFields();
    $('#enable_email_block').on('change', toggleEmailFields);
});



//Handle role restriction changes
jQuery(document).ready(function($) {
    //Handle role restriction changes for both data sources
    $(document).on('change', '.mxchat-role-select', function() {
        const $select = $(this);
        const entryId = $select.data('entry-id');
        const dataSource = $select.data('data-source');
        const roleRestriction = $select.val();
        const nonce = $select.data('nonce');
        
        // Visual feedback
        $select.prop('disabled', true).addClass('updating');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_update_role_restriction',
                nonce: nonce,
                entry_id: entryId,
                data_source: dataSource, // NEW: Include data source
                role_restriction: roleRestriction
            },
            success: function(response) {
                if (response.success) {
                    // Show success feedback
                    $select.removeClass('updating').addClass('updated');
                    setTimeout(() => {
                        $select.removeClass('updated');
                    }, 2000);
                } else {
                    alert('Failed to update role restriction: ' + response.data);
                    // Revert selection
                    $select.val($select.data('original-value'));
                }
            },
            error: function() {
                alert('Error updating role restriction');
                // Revert selection
                $select.val($select.data('original-value'));
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
        
        // Store original value for potential revert
        $select.data('original-value', roleRestriction);
    });
    
    // Store initial values
    $('.mxchat-role-select').each(function() {
        $(this).data('original-value', $(this).val());
    });
});

// Bot Selector Handler
jQuery(document).ready(function($) {
    var saveTimer;

    // Function to update bot_id in forms
    function updateBotIdInForm(formSelector) {
        var botId = $('#mxchat-bot-selector').val();
        var form = $(formSelector);

        if (form.length > 0) {
            // Remove existing bot_id hidden input
            form.find('input[name="bot_id"]').remove();

            // Add new bot_id hidden input if not default
            if (botId && botId !== 'default') {
                form.append('<input type="hidden" name="bot_id" value="' + botId + '">');
            }
        }
    }

    $('#mxchat-bot-selector').on('change', function() {
        var botId = $(this).val();

        // Clear any existing timer
        clearTimeout(saveTimer);

        // Update all forms with new bot_id when bot selection changes
        updateBotIdInForm('#mxchat-url-form');
        updateBotIdInForm('#mxchat-content-form');

        // Save the selection via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_save_selected_bot',
                bot_id: botId,
                nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show saved indicator
                    $('#mxchat-bot-save-status').fadeIn().delay(2000).fadeOut();

                    // Reload the page after a short delay to refresh content
                    saveTimer = setTimeout(function() {
                        var currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('bot_id', botId);
                        currentUrl.searchParams.set('page', 'mxchat-prompts');
                        window.location.href = currentUrl.toString();
                    }, 500);
                }
            },
            error: function() {
                console.error('Failed to save bot selection');
            }
        });
    });

    // Initialize forms with current bot_id when the page loads
    setTimeout(function() {
        updateBotIdInForm('#mxchat-url-form');
        updateBotIdInForm('#mxchat-content-form');
    }, 100);
});

// API Key Status Indicator for Chat Models
jQuery(document).ready(function($) {
    function updateChatModelAPIStatus(apiKeyStatuses) {
        var selectedModel = $('#model').val();

        // Hide all status messages
        $('.mxchat-api-status').hide();

        // Return early if no model is selected
        if (!selectedModel) {
            return;
        }

        // Map models to providers
        var provider = null;

        if (selectedModel.startsWith('gpt-')) {
            provider = 'openai';
        } else if (selectedModel.startsWith('claude-')) {
            provider = 'claude';
        } else if (selectedModel.startsWith('grok-')) {
            provider = 'xai';
        } else if (selectedModel.startsWith('deepseek-')) {
            provider = 'deepseek';
        } else if (selectedModel.startsWith('gemini-')) {
            provider = 'gemini';
        } else if (selectedModel === 'openrouter') {
            provider = 'openrouter';
        }

        // If we have fresh API key data, update the messages
        if (apiKeyStatuses && provider && apiKeyStatuses[provider] !== undefined) {
            var $statusElement = $('.mxchat-api-status[data-provider="' + provider + '"]');
            var hasKey = apiKeyStatuses[provider];

            if (hasKey) {
                $statusElement.html('<span style="color: #00a32a;">✓ API key for ' + getProviderName(provider) + ' detected</span>');
            } else {
                $statusElement.html('<span style="color: #d63638;">⚠ No API key for ' + getProviderName(provider) + ' detected. Please enter API key in API Keys tab.</span>');
            }
        }

        // Show the appropriate status message
        if (provider) {
            $('.mxchat-api-status[data-provider="' + provider + '"]').show();
        }
    }

    function updateEmbeddingModelAPIStatus(apiKeyStatuses) {
        var selectedModel = $('#embedding_model').val();

        // Hide all status messages
        $('.mxchat-embedding-api-status').hide();

        // Return early if no model is selected
        if (!selectedModel) {
            return;
        }

        // Map models to providers
        var provider = null;

        if (selectedModel.startsWith('text-embedding-')) {
            provider = 'openai';
        } else if (selectedModel.startsWith('voyage-')) {
            provider = 'voyage';
        } else if (selectedModel.startsWith('gemini-embedding-')) {
            provider = 'gemini';
        }

        // If we have fresh API key data, update the messages
        if (apiKeyStatuses && provider && apiKeyStatuses[provider] !== undefined) {
            var $statusElement = $('.mxchat-embedding-api-status[data-provider="' + provider + '"]');
            var hasKey = apiKeyStatuses[provider];

            if (hasKey) {
                $statusElement.html('<span style="color: #00a32a;">✓ API key for ' + getProviderName(provider) + ' detected</span>');
            } else {
                $statusElement.html('<span style="color: #d63638;">⚠ No API key for ' + getProviderName(provider) + ' detected. Please enter API key in API Keys tab.</span>');
            }
        }

        // Show the appropriate status message
        if (provider) {
            $('.mxchat-embedding-api-status[data-provider="' + provider + '"]').show();
        }
    }

    function getProviderName(provider) {
        var names = {
            'openai': 'OpenAI',
            'claude': 'Anthropic (Claude)',
            'xai': 'X.AI (Grok)',
            'deepseek': 'DeepSeek',
            'gemini': 'Google Gemini',
            'openrouter': 'OpenRouter',
            'voyage': 'Voyage AI'
        };
        return names[provider] || provider;
    }

    function refreshAPIKeyStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_check_api_keys',
                nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateChatModelAPIStatus(response.data);
                    updateEmbeddingModelAPIStatus(response.data);
                }
            }
        });
    }

    // Expose refresh function globally so auto-save can call it
    window.mxchatRefreshAPIKeyStatus = refreshAPIKeyStatus;

    // Run on page load
    updateChatModelAPIStatus();
    updateEmbeddingModelAPIStatus();

    // Check if we just saved settings (WordPress redirects with ?settings-updated=true)
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('settings-updated') === 'true') {
        // Page was just reloaded after save, fetch fresh API key status
        refreshAPIKeyStatus();
    }

    // Run when model changes
    $('#model').on('change', function() {
        updateChatModelAPIStatus();
        updateWebSearchToggleVisibility();
    });
    $('#embedding_model').on('change', function() { updateEmbeddingModelAPIStatus(); });

    // Web Search toggle visibility based on model
    function updateWebSearchToggleVisibility() {
        var selectedModel = $('#model').val();
        var $wrapper = $('#web-search-toggle-wrapper');
        var $unavailableMessage = $('#web-search-unavailable-message');

        if (!$wrapper.length) return; // Element doesn't exist

        // Get the list of supported OpenAI models from data attribute
        var openaiModelsAttr = $wrapper.data('openai-models');
        var unsupportedModelsAttr = $wrapper.data('unsupported-models');

        var openaiModels = openaiModelsAttr ? openaiModelsAttr.split(',') : [];
        var unsupportedModels = unsupportedModelsAttr ? unsupportedModelsAttr.split(',') : [];

        // Check if selected model is an OpenAI model that supports web search
        var isOpenAI = openaiModels.includes(selectedModel);
        var isSupported = isOpenAI && !unsupportedModels.includes(selectedModel);

        if (isSupported) {
            $wrapper.show();
            $unavailableMessage.hide();
        } else {
            $wrapper.hide();
            $unavailableMessage.show();
        }
    }

    // Run on page load
    updateWebSearchToggleVisibility();
});

// Transcripts Metrics Dashboard
jQuery(document).ready(function($) {
    // Tab switching functionality
    $('.mxchat-metrics-tab').on('click', function() {
        const tabName = $(this).data('tab');
        
        // Update tab buttons
        $('.mxchat-metrics-tab').removeClass('active');
        $(this).addClass('active');
        
        // Update panels
        $('.mxchat-metrics-panel').removeClass('active');
        $(`.mxchat-metrics-panel[data-panel="${tabName}"]`).addClass('active');
        
        // Initialize chart if activity tab is shown
        if (tabName === 'activity' && typeof mxchatChartData !== 'undefined') {
            // Small delay to ensure the canvas is visible
            setTimeout(function() {
                initActivityChart();
            }, 50);
        }
    });
    
    // Initialize chart function
    function initActivityChart() {
        const canvas = document.getElementById('mxchat-activity-chart');
        if (!canvas) return;
        
        // Check if chart already exists and destroy it
        if (canvas.chartInstance) {
            canvas.chartInstance.destroy();
        }
        
        const ctx = canvas.getContext('2d');
        
        // Create gradient for chats line
        const chatsGradient = ctx.createLinearGradient(0, 0, 0, 300);
        chatsGradient.addColorStop(0, 'rgba(102, 126, 234, 0.3)');
        chatsGradient.addColorStop(1, 'rgba(102, 126, 234, 0.05)');
        
        // Create gradient for messages line
        const messagesGradient = ctx.createLinearGradient(0, 0, 0, 300);
        messagesGradient.addColorStop(0, 'rgba(118, 75, 162, 0.3)');
        messagesGradient.addColorStop(1, 'rgba(118, 75, 162, 0.05)');
        
        // Simple chart without external library
        canvas.chartInstance = new SimpleChart(canvas, {
            labels: mxchatChartData.labels,
            datasets: [
                {
                    label: 'Chats',
                    data: mxchatChartData.chats,
                    borderColor: '#667eea',
                    backgroundColor: chatsGradient,
                    fill: true
                },
                {
                    label: 'Messages',
                    data: mxchatChartData.messages,
                    borderColor: '#764ba2',
                    backgroundColor: messagesGradient,
                    fill: true
                }
            ]
        });
    }
    
    // Simple chart implementation (no external dependencies)
    class SimpleChart {
        constructor(canvas, config) {
            this.canvas = canvas;
            this.ctx = canvas.getContext('2d');
            this.config = config;
            this.padding = { top: 20, right: 20, bottom: 40, left: 50 };
            this.render();
        }
        
        destroy() {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        }
        
        render() {
            const dpr = window.devicePixelRatio || 1;
            const rect = this.canvas.getBoundingClientRect();
            
            this.canvas.width = rect.width * dpr;
            this.canvas.height = rect.height * dpr;
            this.ctx.scale(dpr, dpr);
            
            this.canvas.style.width = rect.width + 'px';
            this.canvas.style.height = rect.height + 'px';
            
            const width = rect.width - this.padding.left - this.padding.right;
            const height = rect.height - this.padding.top - this.padding.bottom;
            
            // Find max value
            let maxValue = 0;
            this.config.datasets.forEach(dataset => {
                const max = Math.max(...dataset.data);
                if (max > maxValue) maxValue = max;
            });
            
            // Add some padding to max value
            maxValue = Math.ceil(maxValue * 1.1);
            if (maxValue === 0) maxValue = 10;
            
            // Draw grid lines
            this.ctx.strokeStyle = '#e5e7eb';
            this.ctx.lineWidth = 1;
            const gridLines = 5;
            
            for (let i = 0; i <= gridLines; i++) {
                const y = this.padding.top + (height / gridLines) * i;
                this.ctx.beginPath();
                this.ctx.moveTo(this.padding.left, y);
                this.ctx.lineTo(this.padding.left + width, y);
                this.ctx.stroke();
                
                // Draw y-axis labels
                const value = maxValue - (maxValue / gridLines) * i;
                this.ctx.fillStyle = '#6b7280';
                this.ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                this.ctx.textAlign = 'right';
                this.ctx.fillText(Math.round(value), this.padding.left - 10, y + 4);
            }
            
            // Draw datasets
            this.config.datasets.forEach(dataset => {
                const points = [];
                const xStep = width / (this.config.labels.length - 1 || 1);
                
                dataset.data.forEach((value, index) => {
                    const x = this.padding.left + (xStep * index);
                    const y = this.padding.top + height - (value / maxValue * height);
                    points.push({ x, y, value });
                });
                
                // Draw filled area
                if (dataset.fill && dataset.backgroundColor) {
                    this.ctx.fillStyle = dataset.backgroundColor;
                    this.ctx.beginPath();
                    this.ctx.moveTo(points[0].x, this.padding.top + height);
                    points.forEach(point => {
                        this.ctx.lineTo(point.x, point.y);
                    });
                    this.ctx.lineTo(points[points.length - 1].x, this.padding.top + height);
                    this.ctx.closePath();
                    this.ctx.fill();
                }
                
                // Draw line
                this.ctx.strokeStyle = dataset.borderColor;
                this.ctx.lineWidth = 3;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                
                this.ctx.beginPath();
                points.forEach((point, index) => {
                    if (index === 0) {
                        this.ctx.moveTo(point.x, point.y);
                    } else {
                        this.ctx.lineTo(point.x, point.y);
                    }
                });
                this.ctx.stroke();
                
                // Draw points
                points.forEach(point => {
                    this.ctx.fillStyle = '#ffffff';
                    this.ctx.beginPath();
                    this.ctx.arc(point.x, point.y, 5, 0, Math.PI * 2);
                    this.ctx.fill();
                    this.ctx.strokeStyle = dataset.borderColor;
                    this.ctx.lineWidth = 2;
                    this.ctx.stroke();
                });
            });
            
            // Draw x-axis labels
            const xStep = width / (this.config.labels.length - 1 || 1);
            this.ctx.fillStyle = '#6b7280';
            this.ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            this.ctx.textAlign = 'center';
            
            this.config.labels.forEach((label, index) => {
                const x = this.padding.left + (xStep * index);
                this.ctx.fillText(label, x, this.padding.top + height + 20);
            });
            
            // Draw legend
            let legendX = this.padding.left;
            const legendY = rect.height - 10;
            
            this.config.datasets.forEach((dataset, index) => {
                // Color box
                this.ctx.fillStyle = dataset.borderColor;
                this.ctx.fillRect(legendX, legendY - 8, 12, 12);
                
                // Label
                this.ctx.fillStyle = '#374151';
                this.ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                this.ctx.textAlign = 'left';
                this.ctx.fillText(dataset.label, legendX + 18, legendY);
                
                legendX += this.ctx.measureText(dataset.label).width + 40;
            });
        }
    }
    
    // Initialize chart on page load if we're on the activity tab
    if ($('.mxchat-metrics-tab.active').data('tab') === 'activity' && typeof mxchatChartData !== 'undefined') {
        setTimeout(function() {
            initActivityChart();
        }, 100);
    }

    // ========================================
    // SLACK TEST CONNECTION
    // ========================================
    $('#mxchat-test-slack-connection').on('click', function() {
        var $button = $(this);
        var $result = $('#mxchat-slack-test-result');
        var originalText = $button.html();

        // Show loading state
        $button.prop('disabled', true).html(
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="12"/></svg>' +
            'Testing...'
        );

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_test_slack_connection',
                nonce: mxchatAdmin.nonce
            },
            success: function(response) {
                $result.show();
                if (response.success) {
                    $result.html(
                        '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 16px; border-radius: 8px;">' +
                        '<strong style="display: block; margin-bottom: 8px;">✓ Connection Successful!</strong>' +
                        '<pre style="margin: 0; white-space: pre-wrap; font-size: 13px;">' + escapeHtml(response.data.message) + '</pre>' +
                        '</div>'
                    );
                } else {
                    var bgColor = response.data.partial ? '#fff3cd' : '#f8d7da';
                    var borderColor = response.data.partial ? '#ffeeba' : '#f5c6cb';
                    var textColor = response.data.partial ? '#856404' : '#721c24';
                    var icon = response.data.partial ? '⚠' : '✗';
                    var title = response.data.partial ? 'Partial Success - Missing Scopes' : 'Connection Failed';

                    $result.html(
                        '<div style="background: ' + bgColor + '; border: 1px solid ' + borderColor + '; color: ' + textColor + '; padding: 16px; border-radius: 8px;">' +
                        '<strong style="display: block; margin-bottom: 8px;">' + icon + ' ' + title + '</strong>' +
                        '<pre style="margin: 0; white-space: pre-wrap; font-size: 13px;">' + escapeHtml(response.data.message) + '</pre>' +
                        (response.data.missing_scopes ? '<p style="margin: 12px 0 0; font-size: 13px;">Add these scopes in your Slack app settings and reinstall the app.</p>' : '') +
                        '</div>'
                    );
                }
            },
            error: function() {
                $result.show().html(
                    '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 16px; border-radius: 8px;">' +
                    '<strong>✗ Request Failed</strong><br>Could not connect to the server. Please try again.' +
                    '</div>'
                );
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Helper function to escape HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ========================================
    // DEBUG & OPTIMIZATION TOOLS
    // ========================================

    // Debug Mode Toggle
    $('#mxchat_debug_mode').on('change', function() {
        var $toggle = $(this);
        var enabled = $toggle.is(':checked') ? 'on' : 'off';
        var $label = $toggle.closest('label');

        // Add loading indicator next to the toggle label
        var $indicator = $label.find('.mxchat-save-indicator');
        if ($indicator.length === 0) {
            $indicator = $('<span class="mxchat-save-indicator" style="margin-left: 10px;"></span>');
            $label.append($indicator);
        }
        $indicator.html('<span class="mxchat-saving-spinner"></span>').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_toggle_debug_mode',
                enabled: enabled,
                _ajax_nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success indicator
                    $indicator.html('<span class="mxchat-save-success">✓</span>');
                    setTimeout(function() {
                        $indicator.fadeOut(300);
                    }, 2000);

                    // Always refresh the log after toggling
                    refreshDebugLog();
                } else {
                    $indicator.html('<span class="mxchat-save-error">✗</span>');
                    alert(response.data.message || 'Error toggling debug mode');
                    $toggle.prop('checked', !$toggle.is(':checked'));
                }
            },
            error: function() {
                $indicator.html('<span class="mxchat-save-error">✗</span>');
                alert('Error toggling debug mode');
                $toggle.prop('checked', !$toggle.is(':checked'));
            }
        });
    });

    // Refresh Debug Log
    function refreshDebugLog() {
        var $container = $('#mxchat-debug-log');
        var $countBadge = $('#mxchat-log-count');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_debug_log',
                _ajax_nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success) {
                    var log = response.data.log;
                    var count = response.data.count;

                    if (count > 0) {
                        $countBadge.text(count + ' entries').show();
                        var html = '<div class="mxchat-debug-log-entries">';
                        log.forEach(function(entry) {
                            var typeClass = 'mxchat-log-type-' + entry.type;
                            var typeLabel = entry.type.replace(/_/g, ' ').toUpperCase();
                            html += '<div class="mxchat-debug-log-entry ' + typeClass + '">';
                            html += '<div class="mxchat-log-header">';
                            html += '<span class="mxchat-log-type">' + escapeHtml(typeLabel) + '</span>';
                            html += '<span class="mxchat-log-time">' + escapeHtml(entry.time) + '</span>';
                            html += '</div>';
                            html += '<div class="mxchat-log-message">' + escapeHtml(entry.message) + '</div>';
                            if (entry.data) {
                                html += '<div class="mxchat-log-data">' + escapeHtml(JSON.stringify(entry.data, null, 2)) + '</div>';
                            }
                            html += '</div>';
                        });
                        html += '</div>';
                        $container.html(html);
                    } else {
                        $countBadge.hide();
                        $container.html(
                            '<div class="mxchat-debug-log-empty">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.3;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
                            '<p>No log entries yet. Enable debug mode to start logging.</p>' +
                            '</div>'
                        );
                    }
                }
            }
        });
    }

    // Load debug log on page load
    if ($('#mxchat-debug-log').length) {
        refreshDebugLog();
    }

    // Refresh Log Button
    $('#mxchat-refresh-log').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        refreshDebugLog();
        setTimeout(function() {
            $btn.prop('disabled', false);
        }, 500);
    });

    // Clear Log Button
    $('#mxchat-clear-log').on('click', function() {
        if (!confirm('Are you sure you want to clear the debug log?')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_clear_debug_log',
                _ajax_nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success) {
                    refreshDebugLog();
                } else {
                    alert(response.data.message || 'Error clearing log');
                }
            },
            error: function() {
                alert('Error clearing log');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Export Settings Button
    $('#mxchat-export-settings').on('click', function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html(
            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="12"/></svg> Exporting...'
        );

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_export_settings',
                _ajax_nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and download the JSON file
                    var dataStr = JSON.stringify(response.data.settings, null, 2);
                    var blob = new Blob([dataStr], { type: 'application/json' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    alert(response.data.message || 'Error exporting settings');
                }
            },
            error: function() {
                alert('Error exporting settings');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Reset Settings Modal
    var $resetModal = $('#mxchat-reset-modal');
    var $resetConfirmInput = $('#mxchat-reset-confirmation');
    var $resetConfirmBtn = $('#mxchat-reset-confirm');

    $('#mxchat-reset-settings').on('click', function() {
        $resetModal.fadeIn(200);
        $resetConfirmInput.val('').focus();
        $resetConfirmBtn.prop('disabled', true);
    });

    $('#mxchat-reset-modal-close, #mxchat-reset-cancel, .mxch-modal-backdrop').on('click', function() {
        $resetModal.fadeOut(200);
    });

    $resetConfirmInput.on('input', function() {
        var value = $(this).val().toUpperCase();
        $resetConfirmBtn.prop('disabled', value !== 'RESET');
    });

    $resetConfirmBtn.on('click', function() {
        var $btn = $(this);
        var confirmation = $resetConfirmInput.val();

        $btn.prop('disabled', true).text('Resetting...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_reset_all_settings',
                confirmation: confirmation,
                _ajax_nonce: mxchatAdmin.setting_nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Error resetting settings');
                    $btn.prop('disabled', false).text('Reset All Settings');
                }
            },
            error: function() {
                alert('Error resetting settings');
                $btn.prop('disabled', false).text('Reset All Settings');
            }
        });
    });

    // Load debug log on page load if we're on the optimization section
    if ($('#optimization').hasClass('active') || window.location.hash === '#optimization') {
        refreshDebugLog();
    }

    // Also refresh when switching to optimization tab
    $(document).on('click', '[data-section="optimization"]', function() {
        setTimeout(refreshDebugLog, 100);
    });
});