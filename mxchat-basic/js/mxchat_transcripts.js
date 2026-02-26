/**
 * MxChat Transcripts Page JavaScript - v3.0
 * Split-panel layout with chat list and conversation view
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

        // Reset scroll position of content area
        $('.mxch-content').scrollTop(0);

        // Also update mobile nav if open
        $('.mxch-mobile-nav-link').removeClass('active');
        $('.mxch-mobile-nav-link[data-target="' + target + '"]').addClass('active');

        // Load transcripts when switching to all-chats
        if (target === 'all-chats' && !transcriptsLoaded) {
            loadChatList(1, '');
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

        // Reset scroll position of content area
        $('.mxch-content').scrollTop(0);

        $('.mxch-nav-link').removeClass('active');
        $('.mxch-nav-link[data-target="' + target + '"]').addClass('active');

        $('.mxch-mobile-menu').removeClass('open');
        $('.mxch-mobile-overlay').removeClass('open');
    });

    // Quick action buttons
    $('.mxch-quick-action-btn[data-action="view-chats"]').on('click', function() {
        $('.mxch-nav-link[data-target="all-chats"]').trigger('click');
    });

    $('.mxch-quick-action-btn[data-action="settings"]').on('click', function() {
        $('.mxch-nav-link[data-target="notifications"]').trigger('click');
    });

    // ==========================================================================
    // Mobile Panel Management
    // ==========================================================================

    function isMobile() {
        return window.innerWidth <= 782;
    }

    function showMobileConversationPanel() {
        if (isMobile()) {
            $('.mxch-chat-list-panel').addClass('panel-hidden');
            $('#mxch-conversation-panel').addClass('panel-active');
            $('#mxch-transcript-back-btn').show();
        }
    }

    function hideMobileConversationPanel() {
        if (isMobile()) {
            $('#mxch-conversation-panel').removeClass('panel-active');
            $('.mxch-chat-list-panel').removeClass('panel-hidden');
            $('#mxch-transcript-back-btn').hide();
        }
    }

    // Mobile back button handler
    $('#mxch-transcript-back-btn').on('click', function(e) {
        e.preventDefault();
        hideMobileConversationPanel();
        $('.mxch-chat-item').removeClass('active');
        currentSessionId = null;
    });

    // Handle window resize
    $(window).on('resize', function() {
        if (!isMobile()) {
            // Reset panel states when switching to desktop
            $('.mxch-chat-list-panel').removeClass('panel-hidden');
            $('#mxch-conversation-panel').removeClass('panel-active');
            $('#mxch-transcript-back-btn').hide();
        }
        updateMobileViewportHeight();
    });

    // Fix for mobile browser address bar - sets CSS custom property for accurate viewport height
    function updateMobileViewportHeight() {
        if (isMobile()) {
            // Use visualViewport if available (most reliable for mobile)
            const vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            document.documentElement.style.setProperty('--mxch-mobile-vh', vh + 'px');
        }
    }

    // Update on load and viewport changes
    updateMobileViewportHeight();
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', updateMobileViewportHeight);
    }

    // ==========================================================================
    // Chat List - Split Panel
    // ==========================================================================

    let currentPage = 1;
    const perPage = 50;
    let totalPages = 1;
    let currentSessionId = null;
    let transcriptsLoaded = false;
    let selectedSessions = new Set();
    let currentSortOrder = 'desc'; // newest first

    // Load chat list on page load
    loadChatList(1, '');

    // Search functionality with debounce
    let searchTimeout;
    $('#mxch-search-transcripts').on('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val().toLowerCase();

        searchTimeout = setTimeout(function() {
            currentPage = 1;
            loadChatList(currentPage, searchTerm);
        }, 300);
    });

    // Refresh button
    $('#mxch-refresh-list').on('click', function() {
        const $btn = $(this);
        $btn.addClass('spinning');
        loadChatList(currentPage, $('#mxch-search-transcripts').val());
        setTimeout(() => $btn.removeClass('spinning'), 500);
    });

    // ==========================================================================
    // Bulk Selection & Actions
    // ==========================================================================

    // Select all checkbox
    $('#mxch-select-all').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.mxch-chat-checkbox').prop('checked', isChecked);

        if (isChecked) {
            $('.mxch-chat-item').each(function() {
                selectedSessions.add($(this).data('session-id'));
                $(this).addClass('selected');
            });
            $('#mxch-chat-list').addClass('selection-mode');
        } else {
            selectedSessions.clear();
            $('.mxch-chat-item').removeClass('selected');
            $('#mxch-chat-list').removeClass('selection-mode');
        }

        updateSelectionUI();
    });

    // Update selection UI
    function updateSelectionUI() {
        const count = selectedSessions.size;
        const $countEl = $('#mxch-selected-count');
        const $deleteBtn = $('#mxch-delete-selected');

        if (count > 0) {
            $countEl.text(count + ' selected').addClass('has-selection');
            $deleteBtn.prop('disabled', false);
            $('#mxch-chat-list').addClass('selection-mode');
        } else {
            $countEl.removeClass('has-selection');
            $deleteBtn.prop('disabled', true);
            $('#mxch-chat-list').removeClass('selection-mode');
        }

        // Update select all checkbox state
        const totalItems = $('.mxch-chat-checkbox').length;
        const checkedItems = $('.mxch-chat-checkbox:checked').length;
        $('#mxch-select-all').prop('checked', totalItems > 0 && checkedItems === totalItems);
        $('#mxch-select-all').prop('indeterminate', checkedItems > 0 && checkedItems < totalItems);
    }

    // Sort button
    $('#mxch-sort-btn').on('click', function() {
        currentSortOrder = currentSortOrder === 'desc' ? 'asc' : 'desc';
        $(this).find('svg').css('transform', currentSortOrder === 'asc' ? 'rotate(180deg)' : 'rotate(0deg)');
        loadChatList(currentPage, $('#mxch-search-transcripts').val());
    });

    // Delete selected button
    $('#mxch-delete-selected').on('click', function() {
        const count = selectedSessions.size;
        if (count === 0) return;

        if (!confirm('Are you sure you want to delete ' + count + ' conversation(s)? This action cannot be undone.')) {
            return;
        }

        deleteMultipleSessions(Array.from(selectedSessions));
    });

    // Delete multiple sessions
    function deleteMultipleSessions(sessionIds) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_delete_chat_history',
                delete_session_ids: sessionIds,
                security: $('#mxchat_delete_chat_nonce').val()
            },
            success: function(response) {
                try {
                    const jsonResponse = typeof response === 'object' ? response : JSON.parse(response);

                    if (jsonResponse.success) {
                        // Clear selection
                        selectedSessions.clear();
                        $('#mxch-select-all').prop('checked', false);
                        updateSelectionUI();

                        // If current conversation was deleted, reset panel
                        if (sessionIds.includes(currentSessionId)) {
                            currentSessionId = null;
                            $('#mxch-conversation-content').hide();
                            $('#mxch-conversation-empty').show();
                            $('#mxch-details-drawer').hide();
                        }

                        // Reload list
                        loadChatList(currentPage, $('#mxch-search-transcripts').val());
                    } else if (jsonResponse.error) {
                        alert('Error: ' + jsonResponse.error);
                    }
                } catch (e) {
                    alert('An error occurred while processing the response.');
                }
            },
            error: function() {
                alert('An error occurred while deleting conversations.');
            }
        });
    }

    // Load chat list function
    function loadChatList(page, searchTerm) {
        const $container = $('#mxch-chat-list');
        $container.html('<div class="mxch-list-loading"><span class="spinner is-active"></span></div>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_fetch_chat_history',
                page: page,
                per_page: perPage,
                search: searchTerm,
                sort_order: currentSortOrder
            },
            success: function(response) {
                transcriptsLoaded = true;

                if (response.success && response.sessions && response.sessions.length > 0) {
                    renderChatList(response.sessions);
                    currentPage = response.page;
                    totalPages = response.total_pages;
                    updateChatCount(response.showing_start, response.showing_end, response.total_sessions);
                    renderPagination(response.page, response.total_pages, searchTerm);
                } else {
                    $container.html('<div class="mxch-list-empty"><p>No chats found</p></div>');
                    updateChatCount(0, 0, 0);
                    $('#mxch-pagination').html('');
                }
            },
            error: function() {
                $container.html('<div class="mxch-list-empty"><p>Error loading chats</p></div>');
            }
        });
    }

    // Render chat list items
    function renderChatList(sessions) {
        const $container = $('#mxch-chat-list');
        let html = '';

        sessions.forEach(function(session) {
            const isActive = session.session_id === currentSessionId ? ' active' : '';
            const isSelected = selectedSessions.has(session.session_id) ? ' selected' : '';
            const isChecked = selectedSessions.has(session.session_id) ? ' checked' : '';
            html += `
                <div class="mxch-chat-item${isActive}${isSelected}" data-session-id="${escapeHtml(session.session_id)}">
                    <input type="checkbox" class="mxch-chat-checkbox"${isChecked}>
                    <div class="mxch-chat-avatar">
                        <span>${escapeHtml(session.initials)}</span>
                    </div>
                    <div class="mxch-chat-info">
                        <div class="mxch-chat-name">${escapeHtml(session.display_name)}</div>
                        <div class="mxch-chat-preview">${escapeHtml(session.preview)}</div>
                    </div>
                    <div class="mxch-chat-meta">
                        <span class="mxch-chat-time">${escapeHtml(session.time_display)}</span>
                        <span class="mxch-chat-count">${session.message_count}</span>
                    </div>
                </div>
            `;
        });

        $container.html(html);

        // Attach checkbox handlers
        $('.mxch-chat-checkbox').on('click', function(e) {
            e.stopPropagation(); // Prevent triggering chat item click
            const $item = $(this).closest('.mxch-chat-item');
            const sessionId = $item.data('session-id');

            if ($(this).is(':checked')) {
                selectedSessions.add(sessionId);
                $item.addClass('selected');
            } else {
                selectedSessions.delete(sessionId);
                $item.removeClass('selected');
            }

            updateSelectionUI();
        });

        // Attach click handlers for selecting chat
        $('.mxch-chat-item').on('click', function(e) {
            // Don't trigger if clicking on checkbox
            if ($(e.target).is('.mxch-chat-checkbox')) return;

            const sessionId = $(this).data('session-id');
            selectChat(sessionId);

            // Update active state
            $('.mxch-chat-item').removeClass('active');
            $(this).addClass('active');

            // Show conversation panel on mobile
            showMobileConversationPanel();
        });

        // Update selection UI after render
        updateSelectionUI();
    }

    // Update chat count display
    function updateChatCount(start, end, total) {
        if (total === 0) {
            $('#mxch-chat-count').text('0 chats');
        } else {
            $('#mxch-chat-count').text(`${start}-${end} / ${total} chats`);
        }
    }

    // Render pagination
    function renderPagination(currentPage, totalPages, searchTerm) {
        const $container = $('#mxch-pagination');

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
            loadChatList(pageNum, searchTerm);
        });
    }

    // ==========================================================================
    // Conversation Panel
    // ==========================================================================

    // Select and load a chat conversation
    function selectChat(sessionId) {
        currentSessionId = sessionId;

        // Reset translation state when selecting new chat
        if (typeof resetTranslationState === 'function') {
            resetTranslationState();
        }

        // Show loading in conversation panel
        $('#mxch-conversation-empty').hide();
        $('#mxch-conversation-content').show();
        $('#mxch-messages-area').html('<div class="mxch-messages-loading"><span class="spinner is-active"></span> Loading conversation...</div>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_fetch_conversation',
                session_id: sessionId
            },
            success: function(response) {
                if (response.success) {
                    renderConversation(response);
                    // Load saved translation after rendering
                    if (typeof loadSavedTranslation === 'function') {
                        setTimeout(function() {
                            loadSavedTranslation(sessionId);
                        }, 100);
                    }
                } else {
                    $('#mxch-messages-area').html('<div class="mxch-messages-error">Failed to load conversation</div>');
                }
            },
            error: function() {
                $('#mxch-messages-area').html('<div class="mxch-messages-error">Error loading conversation</div>');
            }
        });
    }

    // Render conversation content
    function renderConversation(data) {
        // Update header
        $('#mxch-user-avatar span').text(data.user.initials);
        $('#mxch-user-name').text(data.user.name);
        $('#mxch-user-meta').text(data.user.sub);

        // Update details drawer
        $('#mxch-detail-messages').text(data.message_count);
        $('#mxch-detail-started').text(data.started);

        if (data.page.url) {
            $('#mxch-detail-page').html(`<a href="${escapeHtml(data.page.url)}" target="_blank">${escapeHtml(data.page.title || data.page.url)}</a>`);
        } else {
            $('#mxch-detail-page').text('-');
        }

        if (data.user.email) {
            $('#mxch-detail-email').text(data.user.email);
            $('#mxch-detail-email-row').show();
        } else {
            $('#mxch-detail-email-row').hide();
        }

        // Clicked links
        if (data.clicked_urls && data.clicked_urls.length > 0) {
            let linksHtml = '';
            data.clicked_urls.forEach(function(url) {
                linksHtml += `<a href="${escapeHtml(url)}" target="_blank" class="mxch-clicked-link">${escapeHtml(url)}</a>`;
            });
            $('#mxch-clicked-links').html(linksHtml);
            $('#mxch-clicked-section').show();
        } else {
            $('#mxch-clicked-section').hide();
        }

        // Render messages
        let messagesHtml = '';
        data.messages.forEach(function(msg) {
            if (msg.is_user) {
                messagesHtml += `
                    <div class="mxch-message mxch-message-user" data-message-id="${msg.id}">
                        <div class="mxch-message-row">
                            <div class="mxch-message-bubble">
                                ${msg.content}
                            </div>
                        </div>
                        <div class="mxch-message-time">${escapeHtml(msg.timestamp)}</div>
                    </div>
                `;
            } else {
                const ragLink = msg.has_rag ? `<a href="#" class="mxch-rag-link" data-message-id="${msg.id}">Sources</a>` : '';
                messagesHtml += `
                    <div class="mxch-message mxch-message-bot" data-message-id="${msg.id}">
                        <div class="mxch-message-header">
                            <span class="mxch-bot-label">AI Assistant</span>
                            ${ragLink}
                        </div>
                        <div class="mxch-message-row">
                            <div class="mxch-message-bubble">
                                ${msg.content}
                            </div>
                        </div>
                        <div class="mxch-message-time">${escapeHtml(msg.timestamp)}</div>
                    </div>
                `;
            }
        });

        $('#mxch-messages-area').html(messagesHtml);

        // Scroll to bottom
        const $area = $('#mxch-messages-area');
        $area.scrollTop($area[0].scrollHeight);

        // Attach RAG link handlers
        $('.mxch-rag-link').on('click', function(e) {
            e.preventDefault();
            const messageId = $(this).data('message-id');
            if (messageId) {
                openRagContextModal(messageId);
            }
        });
    }

    // Toggle details drawer
    $('#mxch-toggle-details').on('click', function() {
        const $drawer = $('#mxch-details-drawer');
        const $btn = $(this);

        if ($drawer.is(':visible')) {
            $drawer.slideUp(200);
            $btn.removeClass('active');
        } else {
            $drawer.slideDown(200);
            $btn.addClass('active');
        }
    });

    // Delete current chat
    $('#mxch-delete-current').on('click', function() {
        if (!currentSessionId) return;

        if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
            return;
        }

        deleteSession(currentSessionId);
    });

    // Delete session function
    function deleteSession(sessionId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_delete_chat_history',
                delete_session_ids: [sessionId],
                security: $('#mxchat_delete_chat_nonce').val()
            },
            success: function(response) {
                try {
                    const jsonResponse = typeof response === 'object' ? response : JSON.parse(response);

                    if (jsonResponse.success) {
                        // Reset conversation panel
                        currentSessionId = null;
                        $('#mxch-conversation-content').hide();
                        $('#mxch-conversation-empty').show();
                        $('#mxch-details-drawer').hide();

                        // Reload list
                        loadChatList(currentPage, $('#mxch-search-transcripts').val());
                    } else if (jsonResponse.error) {
                        alert('Error: ' + jsonResponse.error);
                    }
                } catch (e) {
                    alert('An error occurred while processing the response.');
                }
            },
            error: function() {
                alert('An error occurred while deleting the conversation.');
            }
        });
    }

    // ==========================================================================
    // Export Functionality
    // ==========================================================================

    $('#mxch-export-btn, #mxch-export-current').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).addClass('loading');

        const $form = $('<form>', {
            method: 'post',
            action: ajaxurl
        });

        $form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'mxchat_export_transcripts'
        }));

        $form.append($('<input>', {
            type: 'hidden',
            name: 'security',
            value: mxchatAdmin.export_nonce
        }));

        $form.appendTo('body').submit();

        setTimeout(function() {
            $button.prop('disabled', false).removeClass('loading');
        }, 2000);
    });

    // ==========================================================================
    // Translation Functionality
    // ==========================================================================

    // Store original messages for reverting
    let originalMessages = null;
    let isTranslated = false;
    let currentTranslationLang = null;

    // Load saved language preference from localStorage
    const savedLang = localStorage.getItem('mxch_translate_lang');
    if (savedLang) {
        $('#mxch-translate-lang').val(savedLang);
    }

    // Save language preference when changed
    $('#mxch-translate-lang').on('change', function() {
        localStorage.setItem('mxch_translate_lang', $(this).val());
    });

    // Apply translations to messages
    function applyTranslations(translations) {
        // Store original messages if not already stored
        if (!originalMessages) {
            originalMessages = [];
            $('#mxch-messages-area .mxch-message-bubble').each(function() {
                originalMessages.push($(this).html());
            });
        }

        // Apply translations
        translations.forEach(function(item) {
            const $bubble = $('#mxch-messages-area .mxch-message-bubble').eq(item.index);
            if ($bubble.length) {
                $bubble.html(item.translated);
                $bubble.addClass('translated');
            }
        });

        isTranslated = true;
        $('#mxch-show-original-btn').show();
    }

    // Load saved translation for current session
    function loadSavedTranslation(sessionId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_transcript_translation',
                session_id: sessionId
            },
            success: function(response) {
                if (response.success && response.has_translation) {
                    currentTranslationLang = response.language;
                    applyTranslations(response.translations);
                    // Update language selector to show saved language
                    $('#mxch-translate-lang').val(response.language);
                }
            }
        });
    }

    // Translate button click handler
    $('#mxch-translate-btn').on('click', function() {
        if (!currentSessionId) return;

        const $btn = $(this);
        const targetLang = $('#mxch-translate-lang').val();

        // Disable button and show loading state
        $btn.prop('disabled', true);
        $btn.find('.mxch-translate-text').text('Translating...');
        $btn.find('svg').addClass('mxch-translate-spinner');

        // Store original messages before translation
        if (!originalMessages) {
            originalMessages = [];
            $('#mxch-messages-area .mxch-message-bubble').each(function() {
                originalMessages.push($(this).html());
            });
        }

        // If already translated, restore originals first before re-translating
        if (isTranslated) {
            $('#mxch-messages-area .mxch-message-bubble').each(function(index) {
                if (originalMessages[index]) {
                    $(this).html(originalMessages[index]);
                    $(this).removeClass('translated');
                }
            });
        }

        // Collect all message content (from originals)
        const messages = [];
        originalMessages.forEach(function(html, index) {
            // Create temp element to get text content
            const $temp = $('<div>').html(html);
            messages.push({
                index: index,
                content: $temp.text().trim()
            });
        });

        // Send translation request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_translate_messages',
                session_id: currentSessionId,
                target_lang: targetLang,
                messages: JSON.stringify(messages),
                security: mxchatAdmin.translate_nonce || ''
            },
            success: function(response) {
                if (response.success && response.translations) {
                    currentTranslationLang = response.language;
                    applyTranslations(response.translations);
                    $btn.find('.mxch-translate-text').text('Translate');
                } else {
                    alert(response.error || 'Translation failed. Please try again.');
                    $btn.find('.mxch-translate-text').text('Translate');
                }
            },
            error: function() {
                alert('Translation request failed. Please try again.');
                $btn.find('.mxch-translate-text').text('Translate');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('svg').removeClass('mxch-translate-spinner');
            }
        });
    });

    // Show original button click handler
    $('#mxch-show-original-btn').on('click', function() {
        if (!originalMessages) return;

        // Restore original messages
        $('#mxch-messages-area .mxch-message-bubble').each(function(index) {
            if (originalMessages[index]) {
                $(this).html(originalMessages[index]);
                $(this).removeClass('translated');
            }
        });

        isTranslated = false;
        $(this).hide();
    });

    // Reset translation state (called when selecting new chat)
    function resetTranslationState() {
        originalMessages = null;
        isTranslated = false;
        currentTranslationLang = null;
        $('#mxch-show-original-btn').hide();
    }

    // Make functions available to selectChat
    window.resetTranslationState = resetTranslationState;
    window.loadSavedTranslation = loadSavedTranslation;

    // ==========================================================================
    // RAG Context Modal (Sources & Actions Tabs)
    // ==========================================================================

    function openRagContextModal(messageId) {
        const $modal = $('#mxch-rag-modal');
        const $loading = $modal.find('.mxch-rag-loading');
        const $sourcesContent = $modal.find('.mxch-rag-content');
        const $actionsContent = $modal.find('.mxch-actions-content');

        // Reset to Sources tab
        $modal.find('.mxch-context-tab').removeClass('active');
        $modal.find('.mxch-context-tab[data-tab="sources"]').addClass('active');
        $('#mxch-tab-sources').show();
        $('#mxch-tab-actions').hide();

        // Reset badge counts
        $('#mxch-sources-count, #mxch-actions-count').hide().text('0');

        $modal.fadeIn(200);
        $loading.show();
        $sourcesContent.html('');
        $actionsContent.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_get_rag_context',
                message_id: messageId
            },
            success: function(response) {
                $loading.hide();

                if (response.success && response.data) {
                    // Render sources tab
                    renderRagContext(response.data, $sourcesContent);

                    // Render actions tab
                    renderActionsContext(response.data, $actionsContent);

                    // Update badge counts
                    const sourcesCount = response.data.top_matches ? response.data.top_matches.length : 0;
                    const actionsCount = response.data.action_analysis ? response.data.action_analysis.length : 0;

                    if (sourcesCount > 0) {
                        $('#mxch-sources-count').text(sourcesCount).show();
                    }
                    if (actionsCount > 0) {
                        $('#mxch-actions-count').text(actionsCount).show();
                    }
                } else {
                    $sourcesContent.html('<div class="mxch-rag-error">Unable to load document context.</div>');
                    $actionsContent.html('<div class="mxch-rag-error">No action data available.</div>');
                }
            },
            error: function() {
                $loading.hide();
                $sourcesContent.html('<div class="mxch-rag-error">Error loading context. Please try again.</div>');
                $actionsContent.html('<div class="mxch-rag-error">Error loading context. Please try again.</div>');
            }
        });
    }

    // Tab switching
    $(document).on('click', '.mxch-context-tab', function() {
        const $tab = $(this);
        const tabName = $tab.data('tab');

        // Update active tab
        $('.mxch-context-tab').removeClass('active');
        $tab.addClass('active');

        // Show/hide content
        $('.mxch-tab-content').hide();
        $('#mxch-tab-' + tabName).show();
    });

    function renderRagContext(data, $container) {
        let html = '';

        // Check if we have any source data
        if (!data.top_matches || data.top_matches.length === 0) {
            html += '<div class="mxch-no-results"><p>No document matches found for this response.</p></div>';
            $container.html(html);
            return;
        }

        html += '<div class="mxch-rag-summary">';
        html += '<div class="mxch-rag-summary-item"><span class="mxch-rag-label">Knowledge Base:</span> <span class="mxch-rag-value">' + escapeHtml(data.knowledge_base_type || 'WordPress Database') + '</span></div>';
        html += '<div class="mxch-rag-summary-item"><span class="mxch-rag-label">Similarity Threshold:</span> <span class="mxch-rag-value">' + Math.round((data.similarity_threshold || 0.35) * 100) + '%</span></div>';
        html += '<div class="mxch-rag-summary-item"><span class="mxch-rag-label">Documents Checked:</span> <span class="mxch-rag-value">' + (data.total_documents_checked || 0) + '</span></div>';
        html += '</div>';

        const groupedByUrl = {};

        data.top_matches.forEach(function(match) {
            const url = match.source_display || 'Unknown';
            if (!groupedByUrl[url]) {
                groupedByUrl[url] = {
                    url: url,
                    isUrl: url.startsWith('http'),
                    bestScore: 0,
                    usedForContext: false,
                    matchedChunks: []
                };
            }

            if (match.similarity_percentage > groupedByUrl[url].bestScore) {
                groupedByUrl[url].bestScore = match.similarity_percentage;
            }

            if (match.used_for_context) {
                groupedByUrl[url].usedForContext = true;
            }

            groupedByUrl[url].matchedChunks.push({
                chunkIndex: match.chunk_index,
                score: match.similarity_percentage,
                usedForContext: match.used_for_context
            });
        });

        const urlGroups = Object.values(groupedByUrl).sort((a, b) => b.bestScore - a.bestScore);
        const usedUrlCount = urlGroups.filter(g => g.usedForContext).length;

        html += '<div class="mxch-rag-matches">';
        html += '<h3>Retrieved Documents</h3>';
        html += '<p style="color: var(--mxch-text-secondary); font-size: 13px; margin-bottom: 16px;">' + usedUrlCount + ' entr' + (usedUrlCount === 1 ? 'y' : 'ies') + ' used for response</p>';

        urlGroups.forEach(function(group) {
            const cardClass = group.usedForContext ? 'mxch-rag-match-used' : 'mxch-rag-match-below';
            const statusIcon = group.usedForContext ? '&#10003;' : '&#10007;';
            const statusLabel = group.usedForContext ? 'Used' : 'Not Used';

            html += '<div class="mxch-rag-match-card ' + cardClass + '">';
            html += '<div class="mxch-rag-match-header">';
            html += '<span class="mxch-rag-match-score">' + group.bestScore + '%</span>';

            if (group.matchedChunks.length > 1) {
                html += '<span class="mxch-rag-chunk-badge">' + group.matchedChunks.length + ' chunks</span>';
            }

            html += '<span class="mxch-rag-match-status ' + (group.usedForContext ? 'status-used' : 'status-below') + '">' + statusIcon + ' ' + statusLabel + '</span>';
            html += '</div>';

            html += '<div class="mxch-rag-match-source">';
            if (group.isUrl) {
                html += '<a href="' + escapeHtml(group.url) + '" target="_blank">' + escapeHtml(group.url) + '</a>';
            } else {
                html += escapeHtml(group.url);
            }
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
        $container.html(html);
    }

    function renderActionsContext(data, $container) {
        let html = '';

        // Check if we have action analysis data
        if (!data.action_analysis || data.action_analysis.length === 0) {
            html += '<div class="mxch-no-results"><p>No action analysis available for this message.</p><p style="color: var(--mxch-text-secondary); font-size: 13px; margin-top: 8px;">Actions are only evaluated when enabled in your bot configuration.</p></div>';
            $container.html(html);
            return;
        }

        const actions = data.action_analysis;
        const triggeredAction = actions.find(a => a.triggered);
        const actionsAboveThreshold = actions.filter(a => a.above_threshold).length;

        // Summary section
        html += '<div class="mxch-rag-summary">';
        html += '<div class="mxch-rag-summary-item"><span class="mxch-rag-label">Actions Evaluated:</span> <span class="mxch-rag-value">' + actions.length + '</span></div>';
        html += '<div class="mxch-rag-summary-item"><span class="mxch-rag-label">Above Threshold:</span> <span class="mxch-rag-value">' + actionsAboveThreshold + '</span></div>';
        if (triggeredAction) {
            html += '<div class="mxch-rag-summary-item"><span class="mxch-rag-label">Triggered:</span> <span class="mxch-rag-value" style="color: #10b981; font-weight: 600;">' + escapeHtml(triggeredAction.intent_label) + '</span></div>';
        }
        html += '</div>';

        // Actions list
        html += '<div class="mxch-rag-matches">';
        html += '<h3>Action Scores</h3>';
        html += '<p style="color: var(--mxch-text-secondary); font-size: 13px; margin-bottom: 16px;">Showing all evaluated actions sorted by similarity score</p>';

        actions.forEach(function(action) {
            let cardClass = 'mxch-rag-match-below';
            let statusIcon = '&#10007;';
            let statusLabel = 'Below Threshold';

            if (action.triggered) {
                cardClass = 'mxch-action-triggered';
                statusIcon = '&#9889;';
                statusLabel = 'Triggered';
            } else if (action.above_threshold) {
                cardClass = 'mxch-rag-match-used';
                statusIcon = '&#10003;';
                statusLabel = 'Above Threshold';
            }

            html += '<div class="mxch-rag-match-card ' + cardClass + '">';
            html += '<div class="mxch-rag-match-header">';
            html += '<span class="mxch-rag-match-score">' + action.similarity_percentage + '%</span>';
            html += '<span class="mxch-action-threshold-badge">Threshold: ' + action.threshold_percentage + '%</span>';
            html += '<span class="mxch-rag-match-status ' + (action.triggered ? 'status-triggered' : (action.above_threshold ? 'status-used' : 'status-below')) + '">' + statusIcon + ' ' + statusLabel + '</span>';
            html += '</div>';

            html += '<div class="mxch-action-details">';
            html += '<div class="mxch-action-label">' + escapeHtml(action.intent_label) + '</div>';
            html += '<div class="mxch-action-callback"><span class="mxch-action-callback-label">Callback:</span> ' + escapeHtml(action.callback_function) + '</div>';
            html += '</div>';

            // Score bar visualization
            const scoreBarWidth = Math.min(action.similarity_percentage, 100);
            const thresholdPos = Math.min(action.threshold_percentage, 100);
            html += '<div class="mxch-action-score-bar">';
            html += '<div class="mxch-action-score-fill" style="width: ' + scoreBarWidth + '%;"></div>';
            html += '<div class="mxch-action-threshold-marker" style="left: ' + thresholdPos + '%;"></div>';
            html += '</div>';

            html += '</div>';
        });

        html += '</div>';
        $container.html(html);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Close RAG modal
    $('.mxch-modal-close').on('click', function() {
        $(this).closest('.mxch-modal-overlay').fadeOut(200);
    });

    $('.mxch-modal-overlay').on('click', function(e) {
        if ($(e.target).is('.mxch-modal-overlay')) {
            $(this).fadeOut(200);
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.mxch-modal-overlay').fadeOut(200);
        }
    });

    // ==========================================================================
    // Activity Chart
    // ==========================================================================

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
        }
    }

    // Initialize activity chart
    function initActivityChart() {
        console.log('[MxChat Chart] initActivityChart called');

        const canvas = document.getElementById('mxchat-activity-chart');
        console.log('[MxChat Chart] Canvas element:', canvas);

        if (!canvas) {
            console.log('[MxChat Chart] Canvas not found, aborting');
            return;
        }

        console.log('[MxChat Chart] mxchatChartData exists:', typeof mxchatChartData !== 'undefined');
        if (typeof mxchatChartData === 'undefined') {
            console.log('[MxChat Chart] mxchatChartData is undefined, aborting');
            return;
        }

        console.log('[MxChat Chart] Raw mxchatChartData:', mxchatChartData);

        // Check if chart already exists and destroy it
        if (canvas.chartInstance) {
            canvas.chartInstance.destroy();
        }

        const ctx = canvas.getContext('2d');
        console.log('[MxChat Chart] Canvas context:', ctx);
        console.log('[MxChat Chart] Canvas dimensions:', canvas.getBoundingClientRect());

        // Create gradient for chats line
        const chatsGradient = ctx.createLinearGradient(0, 0, 0, 300);
        chatsGradient.addColorStop(0, 'rgba(102, 126, 234, 0.3)');
        chatsGradient.addColorStop(1, 'rgba(102, 126, 234, 0.05)');

        // Create gradient for messages line
        const messagesGradient = ctx.createLinearGradient(0, 0, 0, 300);
        messagesGradient.addColorStop(0, 'rgba(118, 75, 162, 0.3)');
        messagesGradient.addColorStop(1, 'rgba(118, 75, 162, 0.05)');

        // Convert wp_localize_script objects to arrays (WordPress converts indexed arrays to objects)
        const labels = Object.values(mxchatChartData.labels);
        const chatsData = Object.values(mxchatChartData.chats).map(Number);
        const messagesData = Object.values(mxchatChartData.messages).map(Number);

        console.log('[MxChat Chart] Processed labels:', labels);
        console.log('[MxChat Chart] Processed chatsData:', chatsData);
        console.log('[MxChat Chart] Processed messagesData:', messagesData);

        // Create chart
        try {
            canvas.chartInstance = new SimpleChart(canvas, {
                labels: labels,
                datasets: [
                    {
                        label: 'Chats',
                        data: chatsData,
                        borderColor: '#667eea',
                        backgroundColor: chatsGradient,
                        fill: true
                    },
                    {
                        label: 'Messages',
                        data: messagesData,
                        borderColor: '#764ba2',
                        backgroundColor: messagesGradient,
                        fill: true
                    }
                ]
            });
            console.log('[MxChat Chart] Chart created successfully');
        } catch (error) {
            console.error('[MxChat Chart] Error creating chart:', error);
        }
    }

    // Initialize chart on page load (dashboard is shown by default)
    setTimeout(function() {
        initActivityChart();
    }, 100);

    // Reinitialize chart on window resize
    let resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            initActivityChart();
        }, 250);
    });
});
