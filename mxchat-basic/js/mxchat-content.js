/**
 * MxChat Content Generator
 *
 * Handles modal generation flow, full-width preview with iframe scaling,
 * floating chat panel, sidebar navigation, and settings auto-save.
 *
 * @package MxChat
 * @since 3.1.0
 */
(function($) {
    'use strict';

    var state = {
        postId: null,
        previewUrl: null,
        editUrl: null,
        permalink: null,
        progressKey: null,
        progressPoll: null,
        isGenerating: false,
        isEditing: false,
        chatMessages: [],
        chatOpen: false,
        iframeScale: 1,
        historyLoaded: false,
        historyPage: 1,
        historyLoading: false,
        phraseTimer: null,
        phraseIndex: 0,
        pollStartTime: null,
        lastProgressTime: null,
        postStatus: null
    };

    var loadingPhrases = [
        'Consulting our AI overlords...',
        'Teaching pixels to paint...',
        'Brewing a fresh pot of creativity...',
        'Convincing the robots to cooperate...',
        'Warming up the content engines...',
        'Negotiating with the algorithm...',
        'Sprinkling some digital magic...',
        'Asking ChatGPT to hold our beer...',
        'Running it through the vibe check...',
        'Assembling the word wizards...',
        'Translating brain waves to HTML...',
        'Polishing every last pixel...',
        'Taking a quick coffee break...',
        'Man, this is going to be good...',
        'Almost there... probably...',
        'Generating something awesome...',
        'Feeding the hamsters that power our servers...',
        'Doing that thing where we look busy...',
        'Hold tight, genius at work...',
        'Making the internet a little bit cooler...',
        'Crafting content so good it should be illegal...',
        'Our AI designer just said "trust the process"...'
    ];

    // ─── Sidebar Navigation ────────────────────────────────────────────

    function initNavigation() {
        $(document).on('click', '.mxch-nav-link[data-target], .mxch-nav-sub-link[data-target]', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            switchSection(target);
            $('.mxch-nav-link, .mxch-nav-sub-link').removeClass('active');
            $(this).addClass('active');
        });

        $(document).on('click', '.mxch-mobile-nav-link[data-target]', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            switchSection(target);
            $('.mxch-mobile-nav-link').removeClass('active');
            $(this).addClass('active');
            closeMobileMenu();
        });

        $(document).on('click', '.mxch-mobile-menu-btn', function() {
            $('.mxch-mobile-menu, .mxch-mobile-overlay').addClass('open');
        });
        $(document).on('click', '.mxch-mobile-menu-close, .mxch-mobile-overlay', function() {
            closeMobileMenu();
        });
    }

    function switchSection(target) {
        $('.mxch-section').removeClass('active');
        $('#' + target).addClass('active');
    }

    function closeMobileMenu() {
        $('.mxch-mobile-menu, .mxch-mobile-overlay').removeClass('open');
    }

    // ─── Inline Form ────────────────────────────────────────────────────

    function initInlineForm() {
        // "Create New" button in toolbar — resets to inline form
        $('#mxch-cg-new-btn').on('click', function() {
            resetToForm();
        });

        // On initial load: form is already visible, hide toolbar and preview-wrap chrome
        $('#mxch-cg-new-btn').hide();
        $('.mxch-cg-toolbar').addClass('mxch-cg-toolbar-minimal');
        $('.mxch-cg-preview-wrap').addClass('mxch-cg-preview-wrap-form');
    }

    function showInlineForm() {
        var $form = $('#mxch-cg-inline-form');
        $form.removeClass('mxch-cg-form-collapsing').show();

        // Hide preview and loading
        $('#mxch-cg-preview-iframe').hide();
        $('#mxch-cg-loading-indicator').hide();
        $('.mxch-cg-preview-wrap').css('height', '');

        // Toolbar: hidden; preview-wrap: transparent
        $('.mxch-cg-toolbar').addClass('mxch-cg-toolbar-minimal');
        $('.mxch-cg-preview-wrap').addClass('mxch-cg-preview-wrap-form');
        $('#mxch-cg-new-btn').hide();
        $('.mxch-cg-toolbar-right').hide();
        $('#mxch-cg-status-dropdown').hide();
        $('#mxch-cg-preview-title').text('Content Generator');

        setTimeout(function() { $('#mxch-cg-prompt').focus(); }, 100);
    }

    function hideInlineForm() {
        var $form = $('#mxch-cg-inline-form');
        $form.addClass('mxch-cg-form-collapsing');
        setTimeout(function() {
            $form.hide().removeClass('mxch-cg-form-collapsing');
        }, 300);

        $('.mxch-cg-toolbar').removeClass('mxch-cg-toolbar-minimal');
        $('.mxch-cg-preview-wrap').removeClass('mxch-cg-preview-wrap-form');
    }

    function resetToForm() {
        // Reset state
        state.postId = null;
        state.previewUrl = null;
        state.editUrl = null;
        state.permalink = null;
        state.postStatus = null;
        state.chatMessages = [];

        closeChatPanel();
        closeStatusDropdown();
        $('#mxch-cg-prompt').val('');
        showInlineForm();
    }

    // ─── Generation Flow ───────────────────────────────────────────────

    function initGeneration() {
        // Show/hide schedule date picker
        $('#mxch-cg-status').on('change', function() {
            if ($(this).val() === 'future') {
                $('.mxch-cg-schedule-wrap').show();
                if (!$('#mxch-cg-schedule').val()) {
                    var tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    tomorrow.setHours(9, 0, 0, 0);
                    $('#mxch-cg-schedule').val(tomorrow.toISOString().slice(0, 16));
                }
            } else {
                $('.mxch-cg-schedule-wrap').hide();
            }
        });

        // Generate button
        $('#mxch-cg-generate-btn').on('click', function() {
            if (state.isGenerating) return;
            startGeneration();
        });
    }

    function startGeneration() {
        var prompt = $('#mxch-cg-prompt').val().trim();
        if (!prompt) {
            showNotice('Please enter a prompt describing the content you want to generate.', 'error');
            return;
        }

        state.isGenerating = true;

        // Immediately hide form and show loading indicator
        $('#mxch-cg-inline-form').hide().removeClass('mxch-cg-form-collapsing');
        $('.mxch-cg-toolbar').removeClass('mxch-cg-toolbar-minimal');
        $('.mxch-cg-preview-wrap').removeClass('mxch-cg-preview-wrap-form');
        $('#mxch-cg-preview-title').text('Generating...');
        $('#mxch-cg-new-btn').hide();
        $('.mxch-cg-toolbar-right').hide();
        $('#mxch-cg-status-dropdown').hide();
        closeStatusDropdown();
        closeChatPanel();
        showLoadingIndicator();

        var data = {
            action: 'mxchat_generate_content',
            nonce: mxchatContent.nonce,
            prompt: prompt,
            content_type: $('#mxch-cg-type').val(),
            post_status: $('#mxch-cg-status').val(),
            schedule_date: $('#mxch-cg-schedule').val() || '',
            layout: $('#mxch-cg-layout').val() || 'fullwidth',
            title_display: $('#mxch-cg-title-display').val() || 'hide'
        };

        $.ajax({
            url: mxchatContent.ajaxUrl,
            type: 'POST',
            data: data,
            timeout: 60000,
            success: function(response) {
                if (response.success && response.data.progress_key) {
                    // Async mode — loading indicator already showing
                    state.progressKey = response.data.progress_key;
                    startProgressPoll();
                } else if (response.success) {
                    // Sync fallback — full result returned directly
                    onGenerationSuccess(response.data);
                } else {
                    onGenerationError(response.data && response.data.message ? response.data.message : 'Generation failed.');
                }
            },
            error: function(xhr, status, error) {
                onGenerationError('Request failed: ' + (error || status));
            }
        });
    }

    function onGenerationSuccess(data) {
        state.isGenerating = false;
        state.postId = data.post_id;
        state.previewUrl = data.preview_url;
        state.editUrl = data.edit_url;
        state.permalink = data.permalink;
        state.postStatus = data.status;
        state.chatMessages = [];
        state.historyLoaded = false;

        // Ensure form and its chrome are hidden
        hideInlineForm();

        // Show success state on loading indicator briefly before showing preview
        var $loadingIndicator = $('#mxch-cg-loading-indicator');
        if ($loadingIndicator.is(':visible')) {
            stopPhraseRotation();
            $('#mxch-cg-loading-phrase').text('Your content is ready!');
            updateLoadingProgress(100, 'Complete!');
            $loadingIndicator.addClass('mxch-cg-loading-success');

            setTimeout(function() {
                hideLoadingIndicator();
                finishPreviewLoad(data);
            }, 1200);
        } else {
            // Direct/sync flow — no loading indicator was shown
            finishPreviewLoad(data);
        }
    }

    function finishPreviewLoad(data) {
        // Update toolbar title
        $('#mxch-cg-preview-title').text(data.title || 'Preview');

        // Show status dropdown
        var statusLabels = { draft: 'Draft', publish: 'Published', future: 'Scheduled' };
        var $dropdown = $('#mxch-cg-status-dropdown');
        var $badge = $('#mxch-cg-status-badge');
        $badge.find('.mxch-cg-status-badge-text').text(statusLabels[data.status] || data.status);
        $badge.removeClass('mxch-cg-badge-draft mxch-cg-badge-publish mxch-cg-badge-future')
              .addClass('mxch-cg-badge-' + data.status);
        $dropdown.show();
        closeStatusDropdown();
        $('.mxch-cg-status-option').removeClass('mxch-cg-status-active');
        $('.mxch-cg-status-option[data-status="' + data.status + '"]').addClass('mxch-cg-status-active');
        state.postStatus = data.status;

        // Show toolbar actions
        $('.mxch-cg-toolbar-right').show();
        $('#mxch-cg-view-post').attr('href', data.permalink);

        // Show "Create New" button in toolbar
        var $newBtn = $('#mxch-cg-new-btn');
        $newBtn.find('span').text('Create New');
        $newBtn.show();

        // Load preview
        loadPreview(data.preview_url);

        // Store post ID on the chat panel for add-on access
        $('#mxch-cg-chat').attr('data-post-id', data.post_id);

        // Populate image panel with generated images
        populateImagePanel(data.images || []);

        // Populate meta panel with SEO data
        populateMetaPanel(data);

        // Pre-populate chat
        $('#mxch-cg-chat-messages').empty();
        addChatMessage('assistant', 'Content generated! Request edits like "change the heading to..." or "make the background blue".');
    }

    function onGenerationError(message) {
        state.isGenerating = false;

        var $btn = $('#mxch-cg-generate-btn');
        $btn.prop('disabled', false).removeClass('mxch-cg-loading');

        var $loadingIndicator = $('#mxch-cg-loading-indicator');

        if ($loadingIndicator.is(':visible')) {
            // Error while loading indicator is showing (async flow)
            stopPhraseRotation();
            $loadingIndicator.addClass('mxch-cg-loading-error');
            $('#mxch-cg-loading-phrase').text('Oops! Something went wrong.');
            updateLoadingProgress(0, message);
            $('#mxch-cg-loading-progress-fill').css('background', '#ef4444');

            // Add retry and dismiss buttons
            if (!$loadingIndicator.find('.mxch-cg-loading-error-actions').length) {
                var $actions = $(
                    '<div class="mxch-cg-loading-error-actions">' +
                        '<button type="button" class="mxch-cg-generate-btn" id="mxch-cg-loading-retry">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>' +
                            ' Try Again' +
                        '</button>' +
                        '<button type="button" class="mxch-cg-action-btn" id="mxch-cg-loading-dismiss">Dismiss</button>' +
                    '</div>'
                );
                $loadingIndicator.append($actions);

                $actions.find('#mxch-cg-loading-retry').on('click', function() {
                    hideLoadingIndicator();
                    showInlineForm();
                });
                $actions.find('#mxch-cg-loading-dismiss').on('click', function() {
                    hideLoadingIndicator();
                    showInlineForm();
                });
            }
        } else {
            // Error while modal is still open (pre-async or sync flow)
            updateProgress(0, 'Error: ' + message);
            $('#mxch-cg-progress .mxch-cg-progress-fill').css('background', '#ef4444');

            setTimeout(function() {
                $('#mxch-cg-progress').fadeOut(300);
                $('#mxch-cg-progress .mxch-cg-progress-fill').css('background', '');
            }, 4000);
        }
    }

    function updateProgress(percent, message) {
        $('#mxch-cg-progress .mxch-cg-progress-fill').css('width', percent + '%');
        $('#mxch-cg-progress .mxch-cg-progress-text').text(message);
    }

    function startProgressPoll() {
        // Clear any existing poll interval (but preserve progressKey — it was just set)
        if (state.progressPoll) {
            clearInterval(state.progressPoll);
            state.progressPoll = null;
        }
        state.pollStartTime = Date.now();
        state.lastProgressTime = Date.now();
        state.progressPoll = setInterval(pollProgress, 2500);
    }

    function pollProgress() {
        if (!state.progressKey) return;

        // Activity-based timeout: if no progress update received for 3 minutes, stop.
        // This allows long generations (many images + long content) to run as long as
        // the backend is still making progress, while still catching truly stalled jobs.
        var inactiveMs = Date.now() - (state.lastProgressTime || state.pollStartTime);
        if (inactiveMs > 180000) {
            stopProgressPoll();
            onGenerationError('Generation is taking longer than expected. Check your History tab — the post may have been created.');
            return;
        }

        $.ajax({
            url: mxchatContent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_content_progress',
                nonce: mxchatContent.nonce,
                progress_key: state.progressKey
            },
            timeout: 10000,
            success: function(response) {
                if (!response.success) return;

                var d = response.data;

                // Any non-waiting response means the backend is alive — reset inactivity timer
                if (d.step && d.step !== 'waiting') {
                    state.lastProgressTime = Date.now();
                }

                updateProgress(d.percent || 0, d.message || 'Processing...');
                updateLoadingProgress(d.percent || 0, d.message || 'Processing...');

                if (d.step === 'done' && d.result) {
                    stopProgressPoll();
                    onGenerationSuccess(d.result);
                } else if (d.step === 'error') {
                    stopProgressPoll();
                    onGenerationError(d.message || 'Generation failed.');
                }
            },
            error: function() {
                // Silently retry on poll failure — don't stop polling
            }
        });
    }

    function stopProgressPoll() {
        if (state.progressPoll) {
            clearInterval(state.progressPoll);
            state.progressPoll = null;
        }
        state.progressKey = null;
        state.pollStartTime = null;
        state.lastProgressTime = null;
    }

    // ─── Loading Indicator ────────────────────────────────────────────

    function showLoadingIndicator() {
        $('#mxch-cg-inline-form').hide().removeClass('mxch-cg-form-collapsing');
        $('#mxch-cg-preview-iframe').hide();
        $('.mxch-cg-preview-wrap').css('height', '');

        var $loading = $('#mxch-cg-loading-indicator');
        $loading
            .removeClass('mxch-cg-loading-error mxch-cg-loading-success')
            .show();

        // Reset mini progress
        $('#mxch-cg-loading-progress-fill').css({ 'width': '0%', 'background': '' });
        $('#mxch-cg-loading-progress-text').text('Starting...');

        // Remove any leftover error actions
        $loading.find('.mxch-cg-loading-error-actions').remove();

        startPhraseRotation();
    }

    function hideLoadingIndicator() {
        stopPhraseRotation();
        $('#mxch-cg-loading-indicator').hide();
    }

    function startPhraseRotation() {
        stopPhraseRotation();

        // Fisher-Yates shuffle for variety
        var shuffled = loadingPhrases.slice();
        for (var i = shuffled.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var temp = shuffled[i];
            shuffled[i] = shuffled[j];
            shuffled[j] = temp;
        }

        state.phraseIndex = 0;
        var $phrase = $('#mxch-cg-loading-phrase');

        // Show first phrase immediately
        $phrase.text(shuffled[0]).removeClass('mxch-cg-phrase-exit mxch-cg-phrase-enter');

        state.phraseTimer = setInterval(function() {
            state.phraseIndex = (state.phraseIndex + 1) % shuffled.length;
            var nextText = shuffled[state.phraseIndex];

            // Fade out (slide up)
            $phrase.addClass('mxch-cg-phrase-exit');

            setTimeout(function() {
                // Swap text and prepare enter state (below)
                $phrase
                    .text(nextText)
                    .removeClass('mxch-cg-phrase-exit')
                    .addClass('mxch-cg-phrase-enter');

                // Force reflow then remove enter class to trigger transition
                $phrase[0].offsetHeight;
                $phrase.removeClass('mxch-cg-phrase-enter');
            }, 400); // matches CSS transition duration

        }, 4500);
    }

    function stopPhraseRotation() {
        if (state.phraseTimer) {
            clearInterval(state.phraseTimer);
            state.phraseTimer = null;
        }
    }

    function updateLoadingProgress(percent, message) {
        $('#mxch-cg-loading-progress-fill').css('width', percent + '%');
        if (message) {
            $('#mxch-cg-loading-progress-text').text(message);
        }
    }

    // ─── Preview ───────────────────────────────────────────────────────

    function initPreview() {
        // Viewport toggle
        $(document).on('click', '.mxch-cg-viewport-btn', function() {
            var viewport = $(this).data('viewport');
            $('.mxch-cg-viewport-btn').removeClass('active');
            $(this).addClass('active');

            var $container = $('#mxch-cg-preview-container');
            if (viewport === 'mobile') {
                $container.addClass('mxch-cg-viewport-mobile');
                // Reset iframe to natural size for mobile
                $('#mxch-cg-preview-iframe').css({
                    width: '375px',
                    transform: 'none'
                });
            } else {
                $container.removeClass('mxch-cg-viewport-mobile');
                scaleIframe();
            }
        });

        // Recalculate scale on window resize
        $(window).on('resize', function() {
            if (!$('#mxch-cg-preview-container').hasClass('mxch-cg-viewport-mobile')) {
                scaleIframe();
            }
        });
    }

    function scaleIframe() {
        var $iframe = $('#mxch-cg-preview-iframe');
        if (!$iframe.is(':visible')) return;

        var $wrap = $('.mxch-cg-preview-wrap');
        var containerWidth = $wrap.innerWidth();
        var iframeNativeWidth = 1400;

        if (containerWidth < iframeNativeWidth) {
            var scale = containerWidth / iframeNativeWidth;
            state.iframeScale = scale;
            $iframe.css({
                width: iframeNativeWidth + 'px',
                transform: 'scale(' + scale + ')',
                height: (Math.max(700, $(window).height() - 220) / scale) + 'px'
            });
            // Set container height to match scaled iframe
            $wrap.css('height', ($iframe.outerHeight() * scale) + 'px');
        } else {
            state.iframeScale = 1;
            $iframe.css({
                width: '100%',
                transform: 'none',
                height: Math.max(700, $(window).height() - 220) + 'px'
            });
            $wrap.css('height', '');
        }
    }

    function loadPreview(url) {
        var $iframe = $('#mxch-cg-preview-iframe');
        var $empty = $('.mxch-cg-preview-empty');

        $empty.hide();
        $iframe.show();

        // Attach load handler BEFORE setting src to avoid race condition
        $iframe.off('load.scale').on('load.scale', function() {
            scaleIframe();
        });

        // Add mxchat_preview param so PHP hides admin bar in <head> before render
        var separator = url.indexOf('?') !== -1 ? '&' : '?';
        $iframe.attr('src', url + separator + 'mxchat_preview=1&_t=' + Date.now());

        // Also scale immediately for initial sizing
        setTimeout(scaleIframe, 100);
    }

    function refreshPreview() {
        if (state.previewUrl) {
            loadPreview(state.previewUrl);
        }
    }

    function showPreviewEmpty() {
        showInlineForm();
    }

    // ─── Chat Panel ────────────────────────────────────────────────────

    function initChat() {
        // Toggle chat panel
        $('#mxch-cg-chat-toggle').on('click', function() {
            if (state.chatOpen) {
                closeChatPanel();
            } else {
                openChatPanel();
            }
        });

        // Close chat panel
        $('#mxch-cg-chat-close').on('click', function() {
            closeChatPanel();
        });

        // Enable/disable send button + auto-resize textarea
        $('#mxch-cg-chat-input').on('input', function() {
            var hasText = $(this).val().trim().length > 0;
            $('#mxch-cg-chat-send').prop('disabled', !hasText || state.isEditing);
            // Auto-resize
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        // Send on Enter, Shift+Enter for newline
        $('#mxch-cg-chat-input').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!$(this).val().trim() || state.isEditing) return;
                sendEdit();
            }
        });

        // Send button click
        $('#mxch-cg-chat-send').on('click', function() {
            if (state.isEditing) return;
            sendEdit();
        });
    }

    function openChatPanel() {
        state.chatOpen = true;
        // Use flex display for two-column layout
        $('#mxch-cg-chat').css('display', 'flex');
        $('#mxch-cg-chat-input').focus();
        scrollChatToBottom();
    }

    function closeChatPanel() {
        state.chatOpen = false;
        $('#mxch-cg-chat').hide();
    }

    function sendEdit() {
        var input = $('#mxch-cg-chat-input').val().trim();
        if (!input || !state.postId) return;

        state.isEditing = true;
        $('#mxch-cg-chat-input').val('').css('height', 'auto');
        $('#mxch-cg-chat-send').prop('disabled', true);

        addChatMessage('user', input);

        var $loading = $('<div class="mxch-cg-chat-msg mxch-cg-chat-assistant"><div class="mxch-cg-chat-bubble mxch-cg-chat-loading"><span></span><span></span><span></span></div></div>');
        $('#mxch-cg-chat-messages').append($loading);
        scrollChatToBottom();

        $.ajax({
            url: mxchatContent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_content_edit',
                nonce: mxchatContent.nonce,
                post_id: state.postId,
                edit_instruction: input
            },
            timeout: 120000,
            success: function(response) {
                $loading.remove();
                state.isEditing = false;

                if (response.success) {
                    addChatMessage('assistant', response.data.message || 'Content updated.');
                    if (response.data.preview_url) {
                        state.previewUrl = response.data.preview_url;
                    }
                    refreshPreview();
                    if (response.data.title) {
                        $('#mxch-cg-preview-title').text(response.data.title);
                    }
                    if (response.data.meta) {
                        populateMetaPanel(response.data);
                    }
                    if (response.data.images) {
                        populateImagePanel(response.data.images);
                    }
                } else {
                    addChatMessage('assistant', 'Error: ' + (response.data && response.data.message ? response.data.message : 'Edit failed.'));
                }
            },
            error: function() {
                $loading.remove();
                state.isEditing = false;
                addChatMessage('assistant', 'Error: Request failed. Please try again.');
            }
        });
    }

    function addChatMessage(role, content) {
        state.chatMessages.push({ role: role, content: content });
        var roleClass = role === 'user' ? 'mxch-cg-chat-user' : 'mxch-cg-chat-assistant';
        var $msg = $('<div class="mxch-cg-chat-msg ' + roleClass + '">' +
                     '<div class="mxch-cg-chat-bubble">' + escapeHtml(content) + '</div>' +
                     '</div>');
        $('#mxch-cg-chat-messages').append($msg);
        scrollChatToBottom();
    }

    function scrollChatToBottom() {
        var el = document.getElementById('mxch-cg-chat-messages');
        if (el) el.scrollTop = el.scrollHeight;
    }

    // ─── Settings Auto-Save ────────────────────────────────────────────

    function initSettingsAutoSave() {
        // Use event delegation so dynamically-enabled fields (e.g. pro toggles
        // unlocked by add-ons after page load) still trigger saves.
        $('#content-settings').on('change', '[data-field]', function() {
            var $field = $(this);
            var field = $field.data('field');
            var value;

            if ($field.is(':checkbox')) {
                value = $field.is(':checked') ? 'on' : 'off';
            } else {
                value = $field.val();
            }

            saveContentSetting(field, value, $field);
        });
    }

    function saveContentSetting(field, value, $field) {
        var $label = $field.closest('.mxch-field').find('.mxch-field-label');
        if (!$label.length) {
            $label = $field.closest('.mxch-field').find('.mxch-toggle-label');
        }

        // Show saving spinner
        if ($label.length) {
            $label.removeClass('mxch-saved').addClass('mxch-saving');
        }

        $.ajax({
            url: mxchatContent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_save_content_setting',
                nonce: mxchatContent.nonce,
                field: field,
                value: value
            },
            success: function(response) {
                if ($label.length) {
                    $label.removeClass('mxch-saving');
                    if (response.success) {
                        $label.addClass('mxch-saved');
                        setTimeout(function() {
                            $label.removeClass('mxch-saved');
                        }, 1500);
                    }
                }
            },
            error: function(xhr, status, error) {
                if ($label.length) {
                    $label.removeClass('mxch-saving');
                }
                if (window.console) {
                    console.warn('MxChat content setting save failed:', field, status, error);
                }
            }
        });
    }

    // ─── Image Panel ────────────────────────────────────────────────────

    function populateImagePanel(images) {
        var $grid = $('#mxch-cg-images-grid');
        var $empty = $('#mxch-cg-images-empty');
        var isLocked = $('.mxch-cg-images-col').hasClass('mxch-cg-pro-locked');

        // Clear any previous images (keep the empty state element)
        $grid.find('.mxch-cg-image-thumb').remove();

        if (!images || images.length === 0) {
            $empty.show();
            return;
        }

        $empty.hide();

        $.each(images, function(i, img) {
            var $thumb = $(
                '<div class="mxch-cg-image-thumb">' +
                    '<img src="' + escapeAttr(img.thumbnail) + '" alt="Image ' + (i + 1) + '">' +
                    '<div class="mxch-cg-image-actions' + (isLocked ? ' mxch-cg-image-actions-locked' : '') + '">' +
                        '<button type="button" class="mxch-cg-image-action-btn mxch-cg-image-upload-btn"' + (isLocked ? ' disabled' : '') + ' data-attachment-id="' + (img.attachment_id || '') + '">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>' +
                            ' Upload' +
                        '</button>' +
                        '<button type="button" class="mxch-cg-image-action-btn mxch-cg-image-regen-btn"' + (isLocked ? ' disabled' : '') + ' data-attachment-id="' + (img.attachment_id || '') + '">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>' +
                            ' Regenerate' +
                        '</button>' +
                    '</div>' +
                    (isLocked ? '<div class="mxch-cg-image-lock-badge"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> PRO</div>' : '') +
                '</div>'
            );
            $grid.append($thumb);
        });
    }

    function escapeAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ─── Left Column Tabs ──────────────────────────────────────────────

    function initLeftTabs() {
        $(document).on('click', '.mxch-cg-left-tab', function() {
            var tab = $(this).data('tab');
            $('.mxch-cg-left-tab').removeClass('active');
            $(this).addClass('active');
            $('.mxch-cg-left-panel').removeClass('active');
            $('#mxch-cg-panel-' + tab).addClass('active');
        });

        // Character counter for meta description
        $(document).on('input', '#mxch-cg-meta-description', updateCharCount);
    }

    function populateMetaPanel(data) {
        $('#mxch-cg-meta-title').val(data.title || '');
        if (data.meta) {
            $('#mxch-cg-meta-description').val(data.meta.description || '');
            $('#mxch-cg-meta-keyword').val(data.meta.keyword || '');
            $('#mxch-cg-meta-excerpt').val(data.meta.excerpt || '');
        }
        updateCharCount();
    }

    function updateCharCount() {
        var len = ($('#mxch-cg-meta-description').val() || '').length;
        var $counter = $('.mxch-cg-meta-charcount');
        $counter.text(len + ' / 160');
        if (len > 160) {
            $counter.addClass('mxch-cg-meta-charcount-over');
        } else {
            $counter.removeClass('mxch-cg-meta-charcount-over');
        }
    }

    // ─── History Tab ──────────────────────────────────────────────────

    function initHistory() {
        $(document).on('click', '[data-target="content-history"]', function() {
            if (!state.historyLoaded) {
                loadHistory(1);
            }
        });

        $(document).on('click', '.mxch-cg-history-page-btn[data-page]', function() {
            var page = $(this).data('page');
            if (page && !state.historyLoading) {
                loadHistory(page);
            }
        });

        $(document).on('click', '.mxch-cg-history-edit-btn', function() {
            var postId = $(this).data('post-id');
            if (postId) {
                loadPostForEdit(postId, $(this));
            }
        });

        $(document).on('click', '.mxch-cg-history-delete-btn', function() {
            var $btn = $(this);
            var postId = $btn.data('post-id');
            var $item = $btn.closest('.mxch-cg-history-item');
            var title = $item.find('.mxch-cg-history-title').text();

            if (!confirm('Move "' + title + '" to trash?')) return;

            $btn.prop('disabled', true);
            $.ajax({
                url: mxchatContent.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mxchat_delete_content',
                    nonce: mxchatContent.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        $item.slideUp(200, function() { $(this).remove(); });
                    } else {
                        alert(response.data.message || 'Failed to delete.');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    function loadHistory(page) {
        state.historyLoading = true;
        state.historyPage = page;

        var $loading = $('#mxch-cg-history-loading');
        var $empty   = $('#mxch-cg-history-empty');
        var $list    = $('#mxch-cg-history-list');
        var $pag     = $('#mxch-cg-history-pagination');

        $loading.show();
        $empty.hide();
        $list.hide();
        $pag.hide();

        $.ajax({
            url: mxchatContent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_content_history',
                nonce: mxchatContent.nonce,
                page: page
            },
            success: function(response) {
                state.historyLoading = false;
                state.historyLoaded = true;
                $loading.hide();

                if (!response.success || !response.data.items.length) {
                    $empty.show();
                    return;
                }

                renderHistoryList(response.data.items);
                renderHistoryPagination(response.data.current_page, response.data.total_pages);
                $list.show();

                if (response.data.total_pages > 1) {
                    $pag.show();
                }
            },
            error: function() {
                state.historyLoading = false;
                $loading.hide();
                $empty.show();
            }
        });
    }

    function renderHistoryList(items) {
        var $list = $('#mxch-cg-history-list');
        $list.empty();

        var statusLabels = {
            draft: 'Draft',
            publish: 'Published',
            future: 'Scheduled',
            pending: 'Pending',
            'private': 'Private'
        };

        $.each(items, function(i, item) {
            var thumbHtml = item.thumbnail
                ? '<img src="' + escapeAttr(item.thumbnail) + '" alt="" class="mxch-cg-history-thumb-img">'
                : '<div class="mxch-cg-history-thumb-placeholder">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>' +
                  '</div>';

            var statusClass = 'mxch-cg-badge-' + item.status;
            var statusText = statusLabels[item.status] || item.status;
            var typeLabel = item.post_type === 'page' ? 'Page' : 'Post';

            var $row = $(
                '<div class="mxch-cg-history-item">' +
                    '<div class="mxch-cg-history-thumb">' + thumbHtml + '</div>' +
                    '<div class="mxch-cg-history-info">' +
                        '<div class="mxch-cg-history-title">' + escapeHtml(item.title) + '</div>' +
                        '<div class="mxch-cg-history-meta">' +
                            '<span class="mxch-cg-status-badge ' + statusClass + '">' + escapeHtml(statusText) + '</span>' +
                            '<span class="mxch-cg-history-type">' + escapeHtml(typeLabel) + '</span>' +
                            '<span class="mxch-cg-history-date">' + escapeHtml(item.date) + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="mxch-cg-history-actions">' +
                        '<a href="' + escapeAttr(item.permalink) + '" target="_blank" class="mxch-cg-history-action-btn mxch-cg-history-view-btn" title="View">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>' +
                        '</a>' +
                        '<button type="button" class="mxch-cg-history-action-btn mxch-cg-history-edit-btn" data-post-id="' + item.post_id + '" title="Edit">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg>' +
                        '</button>' +
                        '<button type="button" class="mxch-cg-history-action-btn mxch-cg-history-delete-btn" data-post-id="' + item.post_id + '" title="Delete">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
                        '</button>' +
                    '</div>' +
                '</div>'
            );

            $list.append($row);
        });
    }

    function renderHistoryPagination(current, total) {
        var $pag = $('#mxch-cg-history-pagination');
        $pag.empty();

        if (total <= 1) return;

        var html = '';

        if (current > 1) {
            html += '<button type="button" class="mxch-cg-history-page-btn mxch-cg-history-page-prev" data-page="' + (current - 1) + '">&laquo; Prev</button>';
        }

        for (var p = 1; p <= total; p++) {
            if (p === current) {
                html += '<span class="mxch-cg-history-page-btn mxch-cg-history-page-current">' + p + '</span>';
            } else if (p === 1 || p === total || (p >= current - 1 && p <= current + 1)) {
                html += '<button type="button" class="mxch-cg-history-page-btn" data-page="' + p + '">' + p + '</button>';
            } else if (p === current - 2 || p === current + 2) {
                html += '<span class="mxch-cg-history-page-ellipsis">&hellip;</span>';
            }
        }

        if (current < total) {
            html += '<button type="button" class="mxch-cg-history-page-btn mxch-cg-history-page-next" data-page="' + (current + 1) + '">Next &raquo;</button>';
        }

        $pag.html(html);
    }

    function loadPostForEdit(postId, $btn) {
        var editBtnHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg> Edit';

        $btn.prop('disabled', true).text('Loading...');

        $.ajax({
            url: mxchatContent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_load_post_for_edit',
                nonce: mxchatContent.nonce,
                post_id: postId
            },
            success: function(response) {
                $btn.prop('disabled', false).html(editBtnHtml);

                if (response.success) {
                    // Switch to Generate tab
                    switchSection('content-generate');
                    $('.mxch-nav-link, .mxch-nav-sub-link').removeClass('active');
                    $('[data-target="content-generate"]').addClass('active');
                    $('.mxch-mobile-nav-link').removeClass('active');
                    $('.mxch-mobile-nav-link[data-target="content-generate"]').addClass('active');

                    // Load post into the same editor state as fresh generation
                    onGenerationSuccess(response.data);
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Failed to load post.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(editBtnHtml);
                alert('Request failed. Please try again.');
            }
        });
    }

    // ─── Status Dropdown ──────────────────────────────────────────────

    function initStatusDropdown() {
        // Toggle dropdown on badge click
        $(document).on('click', '#mxch-cg-status-badge', function(e) {
            e.stopPropagation();
            var $dropdown = $('#mxch-cg-status-dropdown');
            if ($dropdown.hasClass('mxch-cg-dropdown-open')) {
                closeStatusDropdown();
            } else {
                openStatusDropdown();
            }
        });

        // Close on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#mxch-cg-status-dropdown').length) {
                closeStatusDropdown();
            }
        });

        // Close on Escape
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStatusDropdown();
            }
        });

        // Draft / Publish — immediate status change
        $(document).on('click', '.mxch-cg-status-option[data-status="draft"], .mxch-cg-status-option[data-status="publish"]', function() {
            var newStatus = $(this).data('status');
            if (newStatus === state.postStatus) {
                closeStatusDropdown();
                return;
            }
            updatePostStatus(newStatus, '');
        });

        // Scheduled — show datetime picker
        $(document).on('click', '.mxch-cg-status-option[data-status="future"]', function() {
            var $scheduleRow = $('.mxch-cg-status-schedule-row');
            if ($scheduleRow.is(':visible')) {
                $scheduleRow.hide();
                return;
            }
            // Pre-fill with tomorrow at 9am if empty
            var $input = $('#mxch-cg-status-schedule-input');
            if (!$input.val()) {
                var tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(9, 0, 0, 0);
                $input.val(tomorrow.toISOString().slice(0, 16));
            }
            $scheduleRow.show();
            $input.focus();
            // Highlight scheduled option
            $('.mxch-cg-status-option').removeClass('mxch-cg-status-active');
            $(this).addClass('mxch-cg-status-active');
        });

        // Confirm schedule
        $(document).on('click', '#mxch-cg-status-schedule-confirm', function() {
            var scheduleDate = $('#mxch-cg-status-schedule-input').val();
            if (!scheduleDate) {
                $('#mxch-cg-status-schedule-input').focus();
                return;
            }
            // Convert datetime-local value to WordPress format (Y-m-d H:i:s)
            var wpDate = scheduleDate.replace('T', ' ') + ':00';
            updatePostStatus('future', wpDate);
        });

        // Enter key on datetime input confirms
        $(document).on('keydown', '#mxch-cg-status-schedule-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#mxch-cg-status-schedule-confirm').trigger('click');
            }
        });
    }

    function openStatusDropdown() {
        var $dropdown = $('#mxch-cg-status-dropdown');
        $dropdown.addClass('mxch-cg-dropdown-open');
        $('.mxch-cg-status-menu').show();
        // Highlight current status
        $('.mxch-cg-status-option').removeClass('mxch-cg-status-active');
        $('.mxch-cg-status-option[data-status="' + state.postStatus + '"]').addClass('mxch-cg-status-active');
        // Hide schedule row unless current status is future
        if (state.postStatus !== 'future') {
            $('.mxch-cg-status-schedule-row').hide();
        }
    }

    function closeStatusDropdown() {
        $('#mxch-cg-status-dropdown').removeClass('mxch-cg-dropdown-open');
        $('.mxch-cg-status-menu').hide();
        $('.mxch-cg-status-schedule-row').hide();
    }

    function updatePostStatus(newStatus, scheduleDate) {
        var $badge = $('#mxch-cg-status-badge');
        $badge.addClass('mxch-cg-status-updating');
        closeStatusDropdown();

        $.ajax({
            url: mxchatContent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mxchat_update_post_status',
                nonce: mxchatContent.nonce,
                post_id: state.postId,
                new_status: newStatus,
                schedule_date: scheduleDate || ''
            },
            success: function(response) {
                $badge.removeClass('mxch-cg-status-updating');

                if (response.success) {
                    var confirmedStatus = response.data.status;
                    state.postStatus = confirmedStatus;

                    // Update badge appearance
                    var statusLabels = { draft: 'Draft', publish: 'Published', future: 'Scheduled' };
                    $badge.find('.mxch-cg-status-badge-text').text(statusLabels[confirmedStatus] || confirmedStatus);
                    $badge.removeClass('mxch-cg-badge-draft mxch-cg-badge-publish mxch-cg-badge-future')
                          .addClass('mxch-cg-badge-' + confirmedStatus);

                    // Mark history as stale so it reloads on next visit
                    state.historyLoaded = false;

                    // Refresh preview (URL may differ between draft/published)
                    refreshPreview();
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Failed to update status.');
                }
            },
            error: function() {
                $badge.removeClass('mxch-cg-status-updating');
                alert('Request failed. Please try again.');
            }
        });
    }

    // ─── Utilities ─────────────────────────────────────────────────────

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function showNotice(message, type) {
        $('.mxch-cg-notice').remove();
        var typeClass = type === 'error' ? 'mxch-cg-notice-error' : 'mxch-cg-notice-success';
        var $notice = $('<div class="mxch-cg-notice ' + typeClass + '">' + escapeHtml(message) + '</div>');
        $('#mxch-cg-inline-form .mxch-cg-form').prepend($notice);
        setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 4000);
    }

    // ─── Initialize ────────────────────────────────────────────────────

    $(document).ready(function() {
        initNavigation();
        initInlineForm();
        initGeneration();
        initPreview();
        initChat();
        initSettingsAutoSave();
        initLeftTabs();
        initHistory();
        initStatusDropdown();

        // Prevent interaction with locked pro feature toggles
        $('.mxch-cg-pro-locked .mxch-toggle-input').on('click', function(e) {
            e.preventDefault();
            return false;
        });
    });

})(jQuery);
