jQuery(document).ready(function($) {

    // ====================================
    // MULTI-INSTANCE MANAGEMENT SYSTEM
    // ====================================

    // Instance registry - tracks all chatbot instances on the page
    const MxChatInstances = {
        instances: {},

        // Initialize an instance for a bot
        init: function(botId) {
            if (!this.instances[botId]) {
                // When persistence is OFF, track when this session started
                // so the AI only sees messages from this page load
                var chatPersistenceEnabled = typeof mxchatChat !== 'undefined' && mxchatChat.chat_persistence_toggle === 'on';

                this.instances[botId] = {
                    botId: botId,
                    sessionId: this.getChatSession(botId),
                    lastSeenMessageId: '',
                    notificationCheckInterval: null,
                    pollingInterval: null,
                    processedMessageIds: new Set(),
                    activePdfFile: null,
                    activeWordFile: null,
                    chatHistoryLoaded: false,
                    isStreaming: false,
                    // Fresh context timestamp - only used when persistence is OFF
                    sessionStartTimestamp: chatPersistenceEnabled ? 0 : Date.now()
                };
            }
            return this.instances[botId];
        },

        // Get instance by botId
        get: function(botId) {
            return this.instances[botId] || this.init(botId);
        },

        // Get all active bot IDs
        getAllBotIds: function() {
            return Object.keys(this.instances);
        },

        // Session management per bot
        getChatSession: function(botId) {
            var cookieName = 'mxchat_session_id_' + botId;
            var sessionId = getCookie(cookieName);

            if (!sessionId) {
                sessionId = generateSessionId();
                this.setChatSession(botId, sessionId);
            }

            return sessionId;
        },

        setChatSession: function(botId, sessionId) {
            var cookieName = 'mxchat_session_id_' + botId;
            document.cookie = cookieName + "=" + sessionId + "; path=/; max-age=86400; SameSite=Lax";
            if (this.instances[botId]) {
                this.instances[botId].sessionId = sessionId;
            }
        },

        resetChatSession: function(botId) {
            var newSessionId = generateSessionId();
            this.setChatSession(botId, newSessionId);
            var $chatBox = getElement(botId, 'chat-box');
            if ($chatBox.length) {
                $chatBox.find('.user-message, .bot-message:not(:first), .agent-message').remove();
            }
            if (this.instances[botId]) {
                this.instances[botId].chatHistoryLoaded = false;
                this.instances[botId].processedMessageIds = new Set();
            }
        }
    };

    // ====================================
    // ELEMENT SELECTOR HELPERS
    // ====================================

    // Check if a specific bot has an AI theme assigned (skip inline colors)
    function shouldSkipInlineColors(botId) {
        // If global AI theme is active, skip inline colors for all bots
        if (mxchatChat.skip_inline_colors) {
            return true;
        }
        // Check if this specific bot has a theme assignment
        var botAssignments = mxchatChat.bot_theme_assignments || {};
        return botAssignments.hasOwnProperty(botId);
    }

    // Get element by ID with bot suffix - returns jQuery object
    function getElement(botId, elementName) {
        return $('#' + elementName + '-' + botId);
    }

    // Get element by ID with bot suffix - returns DOM element
    function getElementDOM(botId, elementName) {
        return document.getElementById(elementName + '-' + botId);
    }

    // Get bot ID from any element within a chatbot instance
    function getBotIdFromElement(element) {
        var $wrapper = $(element).closest('.mxchat-chatbot-wrapper');
        if ($wrapper.length) {
            return $wrapper.data('bot-id') || 'default';
        }
        // Fallback: try to find from floating container
        var $floating = $(element).closest('.floating-chatbot');
        if ($floating.length) {
            var id = $floating.attr('id') || '';
            var match = id.match(/floating-chatbot-(.+)/);
            if (match) return match[1];
        }
        // Fallback: check if element itself has an ID with bot suffix (e.g., floating-chatbot-button-{bot_id})
        var elementId = $(element).attr('id') || '';
        if (elementId) {
            // Match patterns like: floating-chatbot-button-{bot_id}, pre-chat-message-{bot_id}
            var idMatch = elementId.match(/^(?:floating-chatbot-button|pre-chat-message|chat-notification-badge)-(.+)$/);
            if (idMatch) return idMatch[1];
        }
        return 'default';
    }

    // Get wrapper element for a bot
    function getWrapper(botId) {
        return getElement(botId, 'mxchat-chatbot-wrapper');
    }

    // ====================================
    // GLOBAL VARIABLES & CONFIGURATION
    // ====================================
    const toolbarIconColor = mxchatChat.toolbar_icon_color || '#212121';

    // Initialize color settings (these are global as they come from PHP)
    var userMessageBgColor = mxchatChat.user_message_bg_color;
    var userMessageFontColor = mxchatChat.user_message_font_color;
    var botMessageBgColor = mxchatChat.bot_message_bg_color;
    var botMessageFontColor = mxchatChat.bot_message_font_color;
    var liveAgentMessageBgColor = mxchatChat.live_agent_message_bg_color;
    var liveAgentMessageFontColor = mxchatChat.live_agent_message_font_color;

    var linkTarget = mxchatChat.link_target_toggle === 'on' ? '_blank' : '_self';

    // ====================================
    // SESSION MANAGEMENT (Legacy compatibility)
    // ====================================

    function getCookie(name) {
        let value = "; " + document.cookie;
        let parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
    }

    function generateSessionId() {
        return 'mxchat_chat_' + Math.random().toString(36).substr(2, 9);
    }

    // Legacy function - now delegates to instance manager
    function getChatSession(botId) {
        botId = botId || 'default';
        return MxChatInstances.getChatSession(botId);
    }

    function setChatSession(sessionId, botId) {
        botId = botId || 'default';
        MxChatInstances.setChatSession(botId, sessionId);
    }

    function resetChatSession(botId) {
        botId = botId || 'default';
        MxChatInstances.resetChatSession(botId);
    }

    // ====================================
    // INITIALIZE ALL CHATBOT INSTANCES
    // ====================================

    function initializeAllInstances() {
        // Find all chatbot wrappers on the page
        $('.mxchat-chatbot-wrapper').each(function() {
            var botId = $(this).data('bot-id') || 'default';
            MxChatInstances.init(botId);
            initializeBotInstance(botId);
        });
    }

    function initializeBotInstance(botId) {
        var instance = MxChatInstances.get(botId);

        // Initialize quick questions state for this bot
        checkQuickQuestionsState(botId);

        // Note: Event handlers use event delegation with class selectors,
        // so they work automatically for all instances without per-bot setup
    }

// ====================================
// CONTEXTUAL AWARENESS FUNCTIONALITY
// ====================================

function getPageContext() {
    // Check if contextual awareness is enabled
    if (mxchatChat.contextual_awareness_toggle !== 'on') {
        return null;
    }
    
    // Get page URL
    const pageUrl = window.location.href;
    
    // Get page title
    const pageTitle = document.title || '';
    
    // Get main content from the page
    let pageContent = '';
    
    // Try to get content from common content areas
    const contentSelectors = [
        'main',
        '[role="main"]',
        '.content',
        '.main-content',
        '.post-content',
        '.entry-content',
        '.page-content',
        'article',
        '#content',
        '#main'
    ];
    
    let contentElement = null;
    for (const selector of contentSelectors) {
        contentElement = document.querySelector(selector);
        if (contentElement) {
            break;
        }
    }
    
    // If no specific content area found, use body but exclude header, footer, nav, sidebar
    if (!contentElement) {
        contentElement = document.body;
    }
    
    if (contentElement) {
        // Clone the element to avoid modifying the original
        const clone = contentElement.cloneNode(true);
        
        // Remove unwanted elements
        const unwantedSelectors = [
            'header',
            'footer',
            'nav',
            '.navigation',
            '.sidebar',
            '.widget',
            '.menu',
            'script',
            'style',
            '.comments',
            '#comments',
            '.breadcrumb',
            '.breadcrumbs',
            '#floating-chatbot',
            '#floating-chatbot-button',
            '.mxchat',
            '[class*="chat"]',
            '[id*="chat"]'
        ];
        
        unwantedSelectors.forEach(selector => {
            const elements = clone.querySelectorAll(selector);
            elements.forEach(el => el.remove());
        });
        
        //   Extract MxChat context data attributes before getting text content
        const contextData = [];
        clone.querySelectorAll('[data-mxchat-context]').forEach(el => {
            const contextValue = el.dataset.mxchatContext;
            if (contextValue && contextValue.trim()) {
                contextData.push(contextValue);
            }
        });
        
        // Get text content and clean it up
        pageContent = clone.textContent || clone.innerText || '';
        
        // Add context data to page content if any were found
        if (contextData.length > 0) {
            pageContent += '\n\nAdditional Context:\n' + contextData.join('\n');
        }
        
        // Clean up whitespace and limit length
        pageContent = pageContent
            .replace(/\s+/g, ' ')
            .trim()
            .substring(0, 3000); // Limit to 3000 characters to avoid token limits
    }
    
    // Only return context if we have meaningful content
    if (!pageContent || pageContent.length < 50) {
        return null;
    }
    
    return {
        url: pageUrl,
        title: pageTitle,
        content: pageContent
    };
}

//   Track originating page when chat starts
function trackOriginatingPage() {
    const sessionId = getChatSession();
    const pageUrl = window.location.href;
    const pageTitle = document.title || 'Untitled Page';
    
    // Only track once per session
    const trackingKey = 'mxchat_originating_tracked_' + sessionId;
    if (sessionStorage.getItem(trackingKey)) {
        return;
    }
    
    $.ajax({
        url: mxchatChat.ajax_url,
        type: 'POST',
        data: {
            action: 'mxchat_track_originating_page',
            session_id: sessionId,
            page_url: pageUrl,
            page_title: pageTitle,
            nonce: mxchatChat.nonce
        },
        success: function(response) {
            if (response.success) {
                sessionStorage.setItem(trackingKey, 'true');
            }
        }
    });
}

// ====================================
// CORE CHAT FUNCTIONALITY
// ====================================

// Helper functions to disable/enable chat input while waiting for response
function disableChatInput(botId) {
    botId = botId || 'default';
    var chatInput = getElementDOM(botId, 'chat-input');
    var sendButton = getElementDOM(botId, 'send-button');
    if (chatInput) {
        chatInput.disabled = true;
        chatInput.style.opacity = '0.6';
    }
    if (sendButton) {
        sendButton.disabled = true;
        sendButton.style.opacity = '0.5';
        sendButton.style.pointerEvents = 'none';
    }
}

function enableChatInput(botId) {
    botId = botId || 'default';
    var chatInput = getElementDOM(botId, 'chat-input');
    var sendButton = getElementDOM(botId, 'send-button');
    if (chatInput) {
        chatInput.disabled = false;
        chatInput.style.opacity = '1';
        chatInput.focus();
    }
    if (sendButton) {
        sendButton.disabled = false;
        sendButton.style.opacity = '1';
        sendButton.style.pointerEvents = 'auto';
    }
}

// Update your existing sendMessage function
function sendMessage(botId) {
    botId = botId || 'default';
    var $chatInput = getElement(botId, 'chat-input');
    var message = $chatInput.val();

    // ADD PROMPT HOOK HERE
    if (typeof customMxChatFilter === 'function') {
        message = customMxChatFilter(message, "prompt");
    }

    if (message) {
        // Disable input while waiting for response
        disableChatInput(botId);

        appendMessage("user", message, '', [], false, botId);
        $chatInput.val('');
        $chatInput.css('height', 'auto');

        if (hasQuickQuestions(botId)) {
            collapseQuickQuestions(botId);
        }
        appendThinkingMessage(botId);
        scrollToBottom(botId);

        const currentModel = mxchatChat.model || 'gpt-5.1-chat-latest';

        // Check if streaming is enabled AND supported for this model
        if (shouldUseStreaming(currentModel)) {
            callMxChatStream(message, function(response) {
                getElement(botId, 'chat-box').find('.bot-message.temporary-message').removeClass('temporary-message');
            }, botId);
        } else {
            callMxChat(message, function(response) {
                replaceLastMessage("bot", response, '', [], botId);
            }, botId);
        }
    }
}

// Update your existing sendMessageToChatbot function
function sendMessageToChatbot(message, botId) {
    botId = botId || 'default';

    // ADD PROMPT HOOK HERE
    if (typeof customMxChatFilter === 'function') {
        message = customMxChatFilter(message, "prompt");
    }

    // Disable input while waiting for response
    disableChatInput(botId);

    var sessionId = getChatSession(botId);

    if (hasQuickQuestions(botId)) {
        collapseQuickQuestions(botId);
    }
    appendThinkingMessage(botId);
    scrollToBottom(botId);

    const currentModel = mxchatChat.model || 'gpt-5.1-chat-latest';

    // Check if streaming is enabled AND supported for this model
    if (shouldUseStreaming(currentModel)) {
        callMxChatStream(message, function(response) {
            getElement(botId, 'chat-box').find('.bot-message.temporary-message').removeClass('temporary-message');
        }, botId);
    } else {
        callMxChat(message, function(response) {
            getElement(botId, 'chat-box').find('.temporary-message').remove();
            replaceLastMessage("bot", response, '', [], botId);
        }, botId);
    }
}

// Updated shouldUseStreaming function with debugging
function shouldUseStreaming(model) {
    // Check if streaming is enabled in settings (using your toggle naming pattern)
    const streamingEnabled = mxchatChat.enable_streaming_toggle === 'on';
    
    // Check if model supports streaming
    const streamingSupported = isStreamingSupported(model);
    
    
    // Only use streaming if both enabled and supported
    return streamingEnabled && streamingSupported;
}

// Helper function to handle chat mode updates
function handleChatModeUpdates(response, responseText) {
    // Check for explicit chat mode in response (THIS IS THE KEY FIX)
    if (response.chat_mode) {
        updateChatModeIndicator(response.chat_mode);
        return; // Return early since we found explicit mode
    }
    // Check for fallback response chat mode
    else if (response.fallbackResponse && response.fallbackResponse.chat_mode) {
        updateChatModeIndicator(response.fallbackResponse.chat_mode);
        return; // Return early since we found explicit mode
    }
    
    // Only do text-based detection if no explicit mode was provided
    // Check for specific AI chatbot response text
    if (responseText === 'You are now chatting with the AI chatbot.' || 
        responseText.includes('now chatting with the AI') ||
        responseText.includes('switched to AI mode') ||
        responseText.includes('AI chatbot is now')) {
        updateChatModeIndicator('ai');
    }
    // Check for agent transfer messages
    else if (responseText.includes('agent') && 
             (responseText.includes('transfer') || responseText.includes('connected'))) {
        updateChatModeIndicator('agent');
    }
}

// Function to get bot ID from any element or wrapper
// If element is provided, finds the bot ID from its wrapper
// If no element, returns 'default' (for backward compatibility)
function getMxChatBotId(element) {
    if (element) {
        return getBotIdFromElement(element);
    }
    // Fallback: find first chatbot wrapper on page
    const chatbotWrapper = document.querySelector('.mxchat-chatbot-wrapper');
    return chatbotWrapper ? chatbotWrapper.getAttribute('data-bot-id') || 'default' : 'default';
}

function callMxChat(message, callback, botId) {
    botId = botId || getMxChatBotId();

    // Store the message in case we need to retry after session reset
    getElement(botId, 'mxchat-chatbot-wrapper').find('.mxchat-input-holder textarea').data('pending-message', message);

    // Get page context if contextual awareness is enabled
    const pageContext = getPageContext();

    // Get instance for session start timestamp (used when persistence is OFF)
    var instance = MxChatInstances.get(botId);

    // Prepare AJAX data
    const ajaxData = {
        action: 'mxchat_handle_chat_request',
        message: message,
        session_id: getChatSession(botId),
        nonce: mxchatChat.nonce,
        current_page_url: window.location.href,
        current_page_title: document.title,
        bot_id: botId,
        // Pass session start timestamp so AI context matches what user sees
        session_start_timestamp: instance.sessionStartTimestamp || 0
    };
    
    // Add page context if available
    if (pageContext) {
        ajaxData.page_context = JSON.stringify(pageContext);
    }
    
    // CHECK FOR VISION FLAGS AND ADD THEM
    if (window.mxchatVisionProcessed) {
        ajaxData.vision_processed = true;
        ajaxData.original_user_message = window.mxchatOriginalMessage || message;
        ajaxData.vision_images_count = window.mxchatVisionImagesCount || 0;
        // Clear the flags after use
        window.mxchatVisionProcessed = false;
        window.mxchatOriginalMessage = null;
        window.mxchatVisionImagesCount = 0;
    }
    
    $.ajax({
        url: mxchatChat.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: ajaxData,
        success: function(response) {
            // IMMEDIATE CHAT MODE UPDATE - This should be FIRST
            if (response.chat_mode) {
                updateChatModeIndicator(response.chat_mode, botId);
            }

            // Also check in data property if response is wrapped
            if (response.data && response.data.chat_mode) {
                updateChatModeIndicator(response.data.chat_mode, botId);
            }

            // SECURITY FIX: Check for errors FIRST before checking for success
            // This ensures API errors (quota exceeded, invalid key, rate limit) are properly displayed
            if (response.success === false || (response.data && response.data.error_message)) {
                let errorMessage = "";
                let errorCode = "";

                // Check various possible error locations in the response
                if (response.data && response.data.error_message) {
                    errorMessage = response.data.error_message;
                    errorCode = response.data.error_code || "";
                } else if (response.error_message) {
                    errorMessage = response.error_message;
                    errorCode = response.error_code || "";
                } else if (response.message) {
                    errorMessage = response.message;
                } else if (typeof response.data === 'string') {
                    errorMessage = response.data;
                } else {
                    // Fallback for any other unexpected response format
                    errorMessage = "An error occurred. Please try again or contact support.";
                }

                // Handle session reset action (IP changed, session expired, etc.)
                if (response.data && response.data.action === 'reset_session') {
                    // Clear the old session and generate a new one
                    resetChatSession(botId);
                    // Remove the temporary loading message
                    getElement(botId, 'chat-box').find('.bot-message.temporary-message').remove();
                    // Re-send the original message with the new session
                    var originalMessage = getElement(botId, 'mxchat-chatbot-wrapper').find('.mxchat-input-holder textarea').data('pending-message');
                    if (originalMessage) {
                        getElement(botId, 'mxchat-chatbot-wrapper').find('.mxchat-input-holder textarea').data('pending-message', null);
                        // Re-add the user message and thinking indicator
                        appendMessage("user", originalMessage, '', [], false, botId);
                        appendThinkingMessage(botId);
                        scrollToBottom(botId);
                        // Determine whether to use streaming
                        const currentModel = mxchatChat.model || 'gpt-5.1-chat-latest';
                        if (shouldUseStreaming(currentModel)) {
                            callMxChatStream(originalMessage, function(response) {
                                getElement(botId, 'chat-box').find('.bot-message.temporary-message').removeClass('temporary-message');
                            }, botId);
                        } else {
                            callMxChat(originalMessage, function(response) {
                                replaceLastMessage("bot", response, '', [], botId);
                            }, botId);
                        }
                    }
                    return;
                }

                // Format user-friendly error message
                let displayMessage = errorMessage;

                // Customize message for admin users
                if (mxchatChat.is_admin) {
                    // For admin users, show more technical details including error code
                    displayMessage = errorMessage + (errorCode ? " (Error code: " + errorCode + ")" : "");
                }

                replaceLastMessage("bot", displayMessage, '', [], botId);
                return; // Exit early for errors
            }

            // NOW check if this is a successful response by looking for text, html, or message fields
            // This preserves compatibility with your server response format
            if (response.text !== undefined || response.html !== undefined || response.message !== undefined ||
                (response.success === true && response.data && response.data.status === 'waiting_for_agent')) {

                // Handle successful response - this is your original success handling code

                // Handle other responses
                let responseText = response.text || '';
                let responseHtml = response.html || '';
                let responseMessage = response.message || '';

                // Add PDF filename handling
                if (response.data && response.data.filename) {
                    showActivePdf(response.data.filename, botId);
                    var instance = MxChatInstances.get(botId);
                    instance.activePdfFile = response.data.filename;
                }

                // Add redirect check here
                if (response.redirect_url) {
                    if (responseText) {
                        replaceLastMessage("bot", responseText, '', [], botId);
                    }
                    setTimeout(() => {
                        window.location.href = response.redirect_url;
                    }, 1500);
                    return;
                }

                // Check for live agent response
                if (response.success && response.data && response.data.status === 'waiting_for_agent') {
                    updateChatModeIndicator('agent', botId);
                    return;
                }

                // Handle the message and show notification if chat is hidden
                if (responseText || responseHtml || responseMessage) {

                    // ADD RESPONSE HOOKS HERE - BEFORE DISPLAYING
                    if (responseText && typeof customMxChatFilter === 'function') {
                        responseText = customMxChatFilter(responseText, "response");
                    }
                    if (responseMessage && typeof customMxChatFilter === 'function') {
                        responseMessage = customMxChatFilter(responseMessage, "response");
                    }

                    // Update the messages as before
                    if (responseText && responseHtml) {
                        replaceLastMessage("bot", responseText, responseHtml, [], botId);
                    } else if (responseText) {
                        replaceLastMessage("bot", responseText, '', [], botId);
                    } else if (responseHtml) {
                        replaceLastMessage("bot", "", responseHtml, [], botId);
                    } else if (responseMessage) {
                        replaceLastMessage("bot", responseMessage, '', [], botId);
                    }

                    // Check if chat is hidden and show notification
                    var $floatingChatbot = getElement(botId, 'floating-chatbot');
                    if ($floatingChatbot.hasClass('hidden')) {
                        var $badge = getElement(botId, 'chat-notification-badge');
                        if ($badge.length) {
                            $badge.show();
                        }
                    }
                } else {
                    replaceLastMessage("bot", "I received an empty response. Please try again or contact support if this persists.", '', [], botId);
                }

                if (response.message_id) {
                    var instance = MxChatInstances.get(botId);
                    instance.lastSeenMessageId = response.message_id;
                }

                return;
            }

            // Fallback for truly unexpected response formats
            replaceLastMessage("bot", "Unexpected response format. Please try again or contact support.", '', [], botId);
        },
        error: function(xhr, status, error) {
            let errorMessage = "An unexpected error occurred.";

            // Try to parse the response if it's JSON
            try {
                const responseJson = JSON.parse(xhr.responseText);

                if (responseJson.data && responseJson.data.error_message) {
                    errorMessage = responseJson.data.error_message;
                } else if (responseJson.message) {
                    errorMessage = responseJson.message;
                }
            } catch (e) {
                // Not JSON or parsing failed, use HTTP status based messages
                if (xhr.status === 0) {
                    errorMessage = "Network error: Please check your internet connection.";
                } else if (xhr.status === 403) {
                    errorMessage = "Access denied: Your session may have expired. Please refresh the page.";
                } else if (xhr.status === 404) {
                    errorMessage = "API endpoint not found. Please contact support.";
                } else if (xhr.status === 429) {
                    errorMessage = "Too many requests. Please try again in a moment.";
                } else if (xhr.status >= 500) {
                    errorMessage = "Server error: The server encountered an issue. Please try again later.";
                }
            }

            replaceLastMessage("bot", errorMessage, '', [], botId);
        }
    });
}

function callMxChatStream(message, callback, botId) {
    botId = botId || getMxChatBotId();

    // Store the message in case we need to retry after session reset
    getElement(botId, 'mxchat-chatbot-wrapper').find('.mxchat-input-holder textarea').data('pending-message', message);

    const currentModel = mxchatChat.model || 'gpt-5.1-chat-latest';
    if (!isStreamingSupported(currentModel)) {
        callMxChat(message, callback, botId);
        return;
    }

    // Get page context if contextual awareness is enabled
    const pageContext = getPageContext();

    // Get instance for session start timestamp (used when persistence is OFF)
    var instance = MxChatInstances.get(botId);

    const formData = new FormData();
    formData.append('action', 'mxchat_stream_chat');
    formData.append('message', message);
    formData.append('session_id', getChatSession(botId));
    formData.append('nonce', mxchatChat.nonce);
    formData.append('current_page_url', window.location.href);
    formData.append('current_page_title', document.title);
    formData.append('bot_id', botId);
    // Pass session start timestamp so AI context matches what user sees
    formData.append('session_start_timestamp', instance.sessionStartTimestamp || 0);

    // Add page context if available
    if (pageContext) {
        formData.append('page_context', JSON.stringify(pageContext));
    }

    // CHECK FOR VISION FLAGS AND ADD THEM
    if (window.mxchatVisionProcessed) {
        formData.append('vision_processed', 'true');
        formData.append('original_user_message', window.mxchatOriginalMessage || message);
        formData.append('vision_images_count', window.mxchatVisionImagesCount || '0');
        // Clear the flags after use
        window.mxchatVisionProcessed = false;
        window.mxchatOriginalMessage = null;
        window.mxchatVisionImagesCount = 0;
    }

    let accumulatedContent = '';
    let testingDataReceived = false;
    let streamingStarted = false;

    fetch(mxchatChat.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        // Store the response for potential fallback handling
        const responseClone = response.clone();

        if (!response.ok) {
            // Try to get error details from response
            return responseClone.json().then(errorData => {
                throw { isServerError: true, data: errorData };
            }).catch(() => {
                throw new Error('Network response was not ok');
            });
        }

    // Check if response is JSON instead of streaming
    const contentType = response.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
        return responseClone.json().then(data => {
            // IMMEDIATE CHAT MODE UPDATE for JSON response
            if (data.chat_mode) {
                updateChatModeIndicator(data.chat_mode, botId);
            }

            // Check for testing panel
            if (window.mxchatTestPanelInstance && data.testing_data) {
                window.mxchatTestPanelInstance.handleTestingData(data.testing_data);
            }

            // Handle the JSON response directly
            handleNonStreamResponse(data, callback, botId);
            return Promise.resolve(); // Prevent further processing
        });
    }

        // Continue with streaming processing
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        function processStream() {
            reader.read().then(({ done, value }) => {
                if (done) {
                    // If streaming completed but no content was received, try to get response as fallback
                    if (!streamingStarted || !accumulatedContent) {
                        // Try to read the response as JSON
                        responseClone.text().then(text => {
                            try {
                                const data = JSON.parse(text);
                                if (data.text || data.message || data.html) {
                                    handleNonStreamResponse(data, callback, botId);
                                } else {
                                    // No valid data, fall back to regular call
                                    getElement(botId, 'chat-box').find('.bot-message.temporary-message').remove();
                                    callMxChat(message, callback, botId);
                                }
                            } catch (e) {
                                // Could not parse, fall back to regular call
                                getElement(botId, 'chat-box').find('.bot-message.temporary-message').remove();
                                callMxChat(message, callback, botId);
                            }
                        }).catch(() => {
                            getElement(botId, 'chat-box').find('.bot-message.temporary-message').remove();
                            callMxChat(message, callback, botId);
                        });
                        return;
                    }

                    // Re-enable chat input when stream ends with content
                    enableChatInput(botId);

                    if (callback) {
                        callback(accumulatedContent);
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
                            if (!accumulatedContent) {
                                getElement(botId, 'chat-box').find('.bot-message.temporary-message').remove();
                                callMxChat(message, callback, botId);
                                return;
                            }

                            // Re-enable chat input after streaming completes
                            enableChatInput(botId);

                            if (callback) {
                                callback(accumulatedContent);
                            }
                            return;
                        }

                        try {
                            const json = JSON.parse(data);

                            // IMMEDIATE CHAT MODE UPDATE FOR STREAMING
                            if (json.chat_mode) {
                                updateChatModeIndicator(json.chat_mode, botId);
                            }

                            // Handle testing data
                            if (json.testing_data && !testingDataReceived) {
                                if (window.mxchatTestPanelInstance) {
                                    window.mxchatTestPanelInstance.handleTestingData(json.testing_data);
                                    testingDataReceived = true;
                                }
                            }
                            // Handle content streaming
                            else if (json.content) {
                                streamingStarted = true;
                                accumulatedContent += json.content;
                                updateStreamingMessage(accumulatedContent, botId);
                            }
                            // Handle complete response in stream (fallback response)
                            else if (json.text || json.message || json.html) {
                                handleNonStreamResponse(json, callback, botId);
                                return;
                            }
                            // Handle errors
                            else if (json.error) {

                                // Get error message from various possible fields
                                let errorMessage = json.error_message || json.message || json.text ||
                                    (typeof json.error === 'string' ? json.error : 'An error occurred. Please try again.');

                                // Re-enable chat input on error
                                enableChatInput(botId);

                                // Display the error directly in the chat
                                replaceLastMessage("bot", errorMessage, '', [], botId);

                                if (callback) {
                                    callback(errorMessage);
                                }
                                return;
                            }
                        } catch (e) {
                            // SSE data parsing error - silently continue
                        }
                    }
                }

                processStream();
            }).catch(streamError => {
                getElement(botId, 'chat-box').find('.bot-message.temporary-message').remove();
                callMxChat(message, callback, botId);
            });
        }

        processStream();
    })
        .catch(error => {
            // Check if we have server error data with chat mode
            if (error && error.isServerError && error.data) {
                // Check for chat mode in error data
                if (error.data.chat_mode) {
                    updateChatModeIndicator(error.data.chat_mode, botId);
                }

                handleNonStreamResponse(error.data, callback, botId);
            } else {
                // Only fall back to regular call if we don't have any response data
                getElement(botId, 'chat-box').find('.bot-message.temporary-message').remove();
                callMxChat(message, callback, botId);
            }
        });
}

// Helper function to handle non-streaming responses
function handleNonStreamResponse(data, callback, botId) {
    botId = botId || 'default';

    // IMMEDIATE CHAT MODE UPDATE FOR NON-STREAMING RESPONSES
    if (data.chat_mode) {
        updateChatModeIndicator(data.chat_mode, botId);
    }

    // Also check in data property if response is wrapped
    if (data.data && data.data.chat_mode) {
        updateChatModeIndicator(data.data.chat_mode, botId);
    }

    // NOTE: Don't remove temporary message here - let replaceLastMessage handle it
    // This prevents a visual gap between thinking dots disappearing and content appearing

    // SECURITY FIX: Check for errors FIRST
    if (data.success === false || (data.data && data.data.error_message)) {
        let errorMessage = "";
        let errorCode = "";

        // Check various possible error locations
        if (data.data && data.data.error_message) {
            errorMessage = data.data.error_message;
            errorCode = data.data.error_code || "";
        } else if (data.error_message) {
            errorMessage = data.error_message;
            errorCode = data.error_code || "";
        } else if (data.message) {
            errorMessage = data.message;
        } else if (typeof data.data === 'string') {
            errorMessage = data.data;
        } else {
            errorMessage = "An error occurred. Please try again or contact support.";
        }

        // Handle session reset action (IP changed, session expired, etc.)
        if (data.data && data.data.action === 'reset_session') {
            // Clear the old session and generate a new one
            resetChatSession(botId);
            // Re-send the original message with the new session
            var originalMessage = getElement(botId, 'mxchat-chatbot-wrapper').find('.mxchat-input-holder textarea').data('pending-message');
            if (originalMessage) {
                getElement(botId, 'mxchat-chatbot-wrapper').find('.mxchat-input-holder textarea').data('pending-message', null);
                // Re-add the user message and thinking indicator
                appendMessage("user", originalMessage, '', [], false, botId);
                appendThinkingMessage(botId);
                scrollToBottom(botId);
                // Determine whether to use streaming
                const currentModel = mxchatChat.model || 'gpt-5.1-chat-latest';
                if (shouldUseStreaming(currentModel)) {
                    callMxChatStream(originalMessage, callback, botId);
                } else {
                    callMxChat(originalMessage, callback, botId);
                }
            }
            return;
        }

        // Format user-friendly error message
        let displayMessage = errorMessage;
        if (mxchatChat.is_admin) {
            displayMessage = errorMessage + (errorCode ? " (Error code: " + errorCode + ")" : "");
        }

        replaceLastMessage("bot", displayMessage, '', [], botId);

        if (callback) {
            callback('');
        }
        return; // Exit early for errors
    }

    // Handle different response formats
    if (data.text || data.html || data.message) {

        // Apply response hooks
        if (data.text && typeof customMxChatFilter === 'function') {
            data.text = customMxChatFilter(data.text, "response");
        }
        if (data.message && typeof customMxChatFilter === 'function') {
            data.message = customMxChatFilter(data.message, "response");
        }

        // Display the response
        if (data.text && data.html) {
            replaceLastMessage("bot", data.text, data.html, [], botId);
        } else if (data.text) {
            replaceLastMessage("bot", data.text, '', [], botId);
        } else if (data.html) {
            replaceLastMessage("bot", "", data.html, [], botId);
        } else if (data.message) {
            replaceLastMessage("bot", data.message, '', [], botId);
        }
    }

    // Handle other response properties
    if (data.data && data.data.filename) {
        showActivePdf(data.data.filename, botId);
        var instance = MxChatInstances.get(botId);
        instance.activePdfFile = data.data.filename;
    }

    if (data.redirect_url) {
        setTimeout(() => {
            window.location.href = data.redirect_url;
        }, 1500);
    }

    // Ensure chat input is re-enabled (safety net for edge cases)
    enableChatInput(botId);

    if (callback) {
        callback(data.text || data.message || '');
    }
}

// Enhanced updateChatModeIndicator function for immediate DOM updates
function updateChatModeIndicator(mode, botId) {
    console.log('[MxChat] updateChatModeIndicator called with mode:', mode, 'botId:', botId);
    botId = botId || 'default';
    const indicator = getElementDOM(botId, 'chat-mode-indicator');
    console.log('[MxChat] chat-mode-indicator element found:', !!indicator);
    if (indicator) {
        const oldText = indicator.textContent;
        console.log('[MxChat] Current indicator text:', oldText, '-> changing to mode:', mode);

        if (mode === 'agent') {
            indicator.textContent = 'Live Agent';
            console.log('[MxChat] Mode is agent, calling startPolling...');
            startPolling(botId);
        } else {
            // Everything else is AI mode
            const customAiText = indicator.getAttribute('data-ai-text') || 'AI Agent';
            indicator.textContent = customAiText;
            stopPolling(botId);
        }

        // Force immediate DOM update and reflow
        if (oldText !== indicator.textContent) {
            // Force a reflow to ensure the change is visible immediately
            indicator.style.display = 'none';
            indicator.offsetHeight; // Trigger reflow
            indicator.style.display = '';

            // Double-check after a brief moment to ensure the change stuck
            setTimeout(() => {
                if (mode === 'agent' && indicator.textContent !== 'Live Agent') {
                    indicator.textContent = 'Live Agent';
                } else if (mode !== 'agent' && indicator.textContent === 'Live Agent') {
                    const customAiText = indicator.getAttribute('data-ai-text') || 'AI Agent';
                    indicator.textContent = customAiText;
                }
            }, 50);
        }
    }
}

// Function to update message during streaming
function updateStreamingMessage(content, botId) {
    botId = botId || 'default';

    // ADD RESPONSE HOOK FOR REAL-TIME STREAMING
    if (typeof customMxChatFilter === 'function') {
        content = customMxChatFilter(content, "response");
    }

    const formattedContent = linkify(content);

    // Find the temporary message in this bot's chat box
    var $chatBox = getElement(botId, 'chat-box');
    const tempMessage = $chatBox.find('.bot-message.temporary-message').last();

    if (tempMessage.length) {
        // Update existing message
        tempMessage.html(formattedContent);
    } else {
        // Create new temporary message if it doesn't exist
        appendMessage("bot", content, '', [], true, botId);
    }
}

function isStreamingSupported(model) {
    if (!model) return false;

    const modelPrefix = model.split('-')[0].toLowerCase();

    // Support streaming for OpenAI, Claude, Grok, DeepSeek, and OpenRouter models
    const isSupported = modelPrefix === 'gpt' || 
                        modelPrefix === 'o1' || 
                        modelPrefix === 'claude' || 
                        modelPrefix === 'grok' || 
                        modelPrefix === 'deepseek' || 
                        model === 'openrouter';  // Add this line - check full model name for OpenRouter
    
    return isSupported;
}

// Update the event handlers to use the correct function names (using event delegation)
// Use class-based selectors for multi-instance support
$(document).on('click', '.send-button', function() {
    var botId = getBotIdFromElement(this);
    disableChatInput(botId);
    sendMessage(botId);
});

// Override enter key handler (using event delegation)
$(document).on('keypress', '.chat-input', function(e) {
    if (e.which == 13 && !e.shiftKey) {
        e.preventDefault();
        var botId = getBotIdFromElement(this);
        disableChatInput(botId);
        sendMessage(botId);
    }
});

    
function appendMessage(sender, messageText = '', messageHtml = '', images = [], isTemporary = false, botId = 'default') {
    try {
        // Determine styles based on sender type
        let messageClass, bgColor, fontColor;

        if (sender === "user") {
            messageClass = "user-message";
            bgColor = userMessageBgColor;
            fontColor = userMessageFontColor;
            // Only sanitize user input
            messageText = sanitizeUserInput(messageText);
        } else if (sender === "agent") {
            messageClass = "agent-message";
            bgColor = liveAgentMessageBgColor;
            fontColor = liveAgentMessageFontColor;
        } else {
            messageClass = "bot-message";
            bgColor = botMessageBgColor;
            fontColor = botMessageFontColor;
        }

        const messageDiv = $('<div>')
            .addClass(messageClass)
            .attr('dir', 'auto');

        // Only apply inline colors if AI theme is not active (let CSS handle it)
        var skipColors = shouldSkipInlineColors(botId);
        if (skipColors) {
            messageDiv.css({
                'margin-bottom': '1em'
            });
        } else {
            messageDiv.css({
                'background': bgColor,
                'color': fontColor,
                'margin-bottom': '1em'
            });
        }

        // Process the message content based on sender
        let fullMessage;
        if (sender === "user") {
            // For user messages, apply linkify after sanitization
            fullMessage = linkify(messageText);
        } else {
            // For bot/agent messages, preserve HTML
            fullMessage = messageText;
        }

        // Add images if provided
        if (images && images.length > 0) {
            fullMessage += '<div class="image-gallery" dir="auto">';
            images.forEach(img => {
                const safeTitle = sanitizeUserInput(img.title);
                const safeUrl = encodeURI(img.image_url);
                const safeThumbnail = encodeURI(img.thumbnail_url);

                fullMessage += `
                    <div style="margin-bottom: 10px;">
                        <strong>${safeTitle}</strong><br>
                        <a href="${safeUrl}" target="_blank">
                            <img src="${safeThumbnail}" alt="${safeTitle}" style="max-width: 100px; height: auto; margin: 5px;" />
                        </a>
                    </div>`;
            });
            fullMessage += '</div>';
        }

        // Append HTML content if provided
        if (messageHtml && sender !== "user") {
            // Only add line breaks if there's actual text content before the HTML
            if (fullMessage && fullMessage.trim()) {
                fullMessage += '<br><br>' + messageHtml;
            } else {
                fullMessage = messageHtml;
            }
        }

        messageDiv.html(fullMessage);

        if (isTemporary) {
            messageDiv.addClass('temporary-message');
        }

        // Append to the correct chatbot instance's chat-box
        var $chatBox = getElement(botId, 'chat-box');
        messageDiv.hide().appendTo($chatBox).fadeIn(300, function() {
            // FIXED: Use event delegation for link tracking
            if (sender === "bot" || sender === "agent") {
                attachLinkTracking(messageDiv, messageText, botId);
            }

            if (sender === "bot") {
                const lastUserMessage = $chatBox.find('.user-message').last();
                if (lastUserMessage.length) {
                    scrollElementToTop(lastUserMessage, botId);
                }
            }
        });

        if (messageText.id) {
            var instance = MxChatInstances.get(botId);
            instance.lastSeenMessageId = messageText.id;
            hideNotification(botId);
        }
    } catch (error) {
        // Error rendering message - silently continue
    }
}

//   Helper function to attach link tracking with proper event handling
function attachLinkTracking(messageDiv, messageText, botId) {
    botId = botId || 'default';
    // Use a slight delay to ensure DOM is ready
    setTimeout(function() {
        const links = messageDiv.find('a[href]').not('[data-tracked]');

        links.each(function() {
            const $link = $(this);
            const originalHref = $link.attr('href');

            // Mark as tracked to avoid duplicate handlers
            $link.attr('data-tracked', 'true');

            // Only track external URLs
            if (originalHref && (originalHref.startsWith('http://') || originalHref.startsWith('https://'))) {
                // Remove any existing click handlers first
                $link.off('click.tracking');

                // Add new click handler with namespace
                $link.on('click.tracking', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const messageContext = typeof messageText === 'string'
                        ? messageText.substring(0, 200)
                        : '';

                    // Track the click
                    $.ajax({
                        url: mxchatChat.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'mxchat_track_url_click',
                            session_id: getChatSession(botId),
                            url: originalHref,
                            message_context: messageContext,
                            nonce: mxchatChat.nonce
                        },
                        complete: function() {
                            // Always redirect, even if tracking fails
                            if ($link.attr('target') === '_blank' || linkTarget === '_blank') {
                                window.open(originalHref, '_blank');
                            } else {
                                window.location.href = originalHref;
                            }
                        }
                    });

                    return false; // Extra insurance to prevent default
                });
            }
        });
    }, 100); // Small delay to ensure DOM is ready
}

function replaceLastMessage(sender, responseText, responseHtml = '', images = [], botId = 'default') {
    var messageClass = sender === "user" ? "user-message" : sender === "agent" ? "agent-message" : "bot-message";
    var $chatBox = getElement(botId, 'chat-box');
    var lastMessageDiv = $chatBox.find('.bot-message.temporary-message, .agent-message.temporary-message').last();

    // Determine styles
    let bgColor, fontColor;
    if (sender === "user") {
        bgColor = userMessageBgColor;
        fontColor = userMessageFontColor;
    } else if (sender === "agent") {
        bgColor = liveAgentMessageBgColor;
        fontColor = liveAgentMessageFontColor;
    } else {
        bgColor = botMessageBgColor;
        fontColor = botMessageFontColor;
    }

    // FIXED: Only linkify if response doesn't already contain HTML links or tags
    // This prevents double-processing of URLs that are already formatted as HTML
    var fullMessage;
    if (sender === "user") {
        // Always linkify user messages (they're plain text)
        fullMessage = linkify(responseText);
    } else {
        // For bot/agent messages, check if HTML already exists
        if (responseText.includes('<a href=') || responseText.includes('</a>') ||
            responseText.includes('<img') || responseText.includes('<div') ||
            responseText.includes('<p>') || responseText.includes('<br>')) {
            // Response already has HTML, don't process it
            fullMessage = responseText;
        } else {
            // Plain text response, apply linkify
            fullMessage = linkify(responseText);
        }
    }

    if (responseHtml) {
        // Only add line breaks if there's actual text content before the HTML
        if (fullMessage && fullMessage.trim()) {
            fullMessage += '<br><br>' + responseHtml;
        } else {
            fullMessage = responseHtml;
        }
    }

    if (images.length > 0) {
        fullMessage += '<div class="image-gallery" dir="auto">';
        images.forEach(img => {
            fullMessage += `
                <div style="margin-bottom: 10px;">
                    <strong>${img.title}</strong><br>
                    <a href="${img.image_url}" target="_blank">
                        <img src="${img.thumbnail_url}" alt="${img.title}" style="max-width: 100px; height: auto; margin: 5px;" />
                    </a>
                </div>`;
        });
        fullMessage += '</div>';
    }

    if (lastMessageDiv.length) {
        // Replace content immediately to prevent visual gap between thinking dots and response
        lastMessageDiv
            .html(fullMessage)
            .removeClass('bot-message user-message temporary-message')
            .addClass(messageClass)
            .attr('dir', 'auto');

        // Only apply inline colors if AI theme is not active (let CSS handle it)
        var skipColors = mxchatChat.skip_inline_colors || shouldSkipInlineColors(botId);
        if (!skipColors) {
            lastMessageDiv.css({
                'background-color': bgColor,
                'color': fontColor,
            });
        }

        // Handle link tracking and scroll
        if (sender === "bot" || sender === "agent") {
            attachLinkTracking(lastMessageDiv, responseText, botId);

            const lastUserMessage = $chatBox.find('.user-message').last();
            if (lastUserMessage.length) {
                scrollElementToTop(lastUserMessage, botId);
            }
            // Show notification if chat is hidden
            var $floatingChatbot = getElement(botId, 'floating-chatbot');
            if ($floatingChatbot.hasClass('hidden')) {
                showNotification(botId);
            }
        }

        // Re-enable chat input after response is displayed
        enableChatInput(botId);
    } else {
        appendMessage(sender, responseText, responseHtml, images, false, botId);
        // Re-enable chat input after response is displayed
        enableChatInput(botId);
    }
}


    function appendThinkingMessage(botId) {
        botId = botId || 'default';
        var $chatBox = getElement(botId, 'chat-box');

        // Remove any existing thinking dots in this bot's chat first
        $chatBox.find('.thinking-dots').remove();

        // Check if we should skip inline colors (AI theme is active)
        var skipColors = shouldSkipInlineColors(botId);

        // Retrieve the bot message font color and background color
        var botMessageFontColor = mxchatChat.bot_message_font_color;
        var botMessageBgColor = mxchatChat.bot_message_bg_color;

        // Build thinking dots HTML - skip inline colors if AI theme is active
        var dotStyle = skipColors ? '' : ' style="background-color: ' + botMessageFontColor + ';"';
        var thinkingHtml = '<div class="thinking-dots-container">' +
                           '<div class="thinking-dots">' +
                           '<span class="dot"' + dotStyle + '></span>' +
                           '<span class="dot"' + dotStyle + '></span>' +
                           '<span class="dot"' + dotStyle + '></span>' +
                           '</div>' +
                           '</div>';

        // Append the thinking dots to this bot's chat container - skip inline colors if AI theme is active
        var messageStyle = skipColors ? '' : ' style="background-color: ' + botMessageBgColor + ';"';
        $chatBox.append('<div class="bot-message temporary-message"' + messageStyle + '>' + thinkingHtml + '</div>');
        scrollToBottom(botId);
    }

    function removeThinkingDots(botId) {
        botId = botId || 'default';
        var $chatBox = getElement(botId, 'chat-box');
        $chatBox.find('.thinking-dots').closest('.temporary-message').remove();
    }

    // ====================================
    // TEXT FORMATTING & PROCESSING
    // ====================================
    
    function linkify(inputText) {
    if (!inputText) {
        return '';
    }
    
    // Helper function to check if URL is already encoded
    function isUrlEncoded(url) {
        // Check for % followed by exactly 2 hex digits
        return /%[0-9a-fA-F]{2}/.test(url);
    }
    
    // Helper function to safely encode URLs only if needed
    function safeEncodeUrl(url) {
        // If URL already contains encoded characters, return as-is
        if (isUrlEncoded(url)) {
            return url;
        }
        // Otherwise, encode it
        return encodeURI(url);
    }
    
    // Process markdown headers FIRST
    let processedText = formatMarkdownHeaders(inputText);
    
    // Process text styling (bold, italic, strikethrough)
    processedText = formatTextStyling(processedText);
    
    // Process code blocks BEFORE processing links
    processedText = formatCodeBlocks(processedText);
    
    // NOW convert to paragraphs
    processedText = convertNewlinesToBreaks(processedText);
    
    // IMPORTANT: Handle citation-style brackets FIRST [URL] 
    // This prevents them from being processed as markdown links
    // Match [URL] where URL is a complete URL in square brackets (common in AI citations)
    processedText = processedText.replace(/\[(https?:\/\/[^\]]+)\]/g, (match, url) => {
        // Clean the URL of any trailing punctuation
        let cleanUrl = url.replace(/[.,;!?]+$/, '');
        const safeUrl = safeEncodeUrl(cleanUrl);
        // Return as a proper link without the brackets
        return `<a href="${safeUrl}" target="${linkTarget}">${cleanUrl}</a>`;
    });
    
    // Process proper markdown links with text: [text](url)
    // This MUST have non-empty text in the first brackets
    const markdownLinkPattern = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g;
    processedText = processedText.replace(markdownLinkPattern, (match, text, url) => {
        // Make sure we have actual text (not just whitespace)
        if (!text || !text.trim()) {
            // If no text, treat the URL as the text
            let cleanUrl = url.replace(/[.,;!?]+$/, '');
            const safeUrl = safeEncodeUrl(cleanUrl);
            return `<a href="${safeUrl}" target="${linkTarget}">${cleanUrl}</a>`;
        }
        
        // Clean the URL
        let cleanUrl = url.replace(/[\].,;!?]+$/, '');
        const safeUrl = safeEncodeUrl(cleanUrl);
        const safeText = sanitizeUserInput(text);
        return `<a href="${safeUrl}" target="${linkTarget}">${safeText}</a>`;
    });
    
    // Handle empty markdown links: [](url)
    // This is a specific case where there's no text
    const emptyMarkdownPattern = /\[\]\((https?:\/\/[^\s)]+)\)/g;
    processedText = processedText.replace(emptyMarkdownPattern, (match, url) => {
        let cleanUrl = url.replace(/[.,;!?]+$/, '');
        const safeUrl = safeEncodeUrl(cleanUrl);
        // Use the URL itself as the link text
        return `<a href="${safeUrl}" target="${linkTarget}">${cleanUrl}</a>`;
    });
    
    // Process phone numbers: [text](tel:number)
    const phonePattern = /\[([^\]]+)\]\((tel:[\d+\-\s()]+)\)/g;
    processedText = processedText.replace(phonePattern, (match, text, phone) => {
        const safePhone = safeEncodeUrl(phone);
        const safeText = sanitizeUserInput(text);
        return `<a href="${safePhone}">${safeText}</a>`;
    });
    
    // Process mailto links: [text](mailto:email)
    const mailtoPattern = /\[([^\]]+)\]\((mailto:[^\)]+)\)/g;
    processedText = processedText.replace(mailtoPattern, (match, text, mailto) => {
        const safeMailto = safeEncodeUrl(mailto);
        const safeText = sanitizeUserInput(text);
        return `<a href="${safeMailto}">${safeText}</a>`;
    });
    
    // Process standalone URLs - but NOT if they're already in <a> tags or brackets
    // Updated pattern to be more careful about what it matches
    const urlPattern = /(^|[^">=\[\]])(https?:\/\/[^\s<"\[\]]+)(?![^<]*<\/a>)(?!\])/gim;
    processedText = processedText.replace(urlPattern, (match, prefix, url) => {
        // Extra check: make sure this isn't already linked
        if (match.includes('href=') || match.includes('</a>')) {
            return match;
        }
        
        // Clean trailing punctuation
        let cleanUrl = url.replace(/[.,;!?)]+$/, '');
        const safeUrl = safeEncodeUrl(cleanUrl);
        return `${prefix}<a href="${safeUrl}" target="${linkTarget}">${cleanUrl}</a>`;
    });
    
    // Process www. URLs - but NOT if they're already in <a> tags or brackets
    const wwwPattern = /(^|[^">/\[\]])(www\.[\S]+)(?![^<]*<\/a>)(?!\])/gim;
    processedText = processedText.replace(wwwPattern, (match, prefix, url) => {
        // Extra check: make sure this isn't already linked
        if (match.includes('href=') || match.includes('</a>')) {
            return match;
        }
        
        // Clean trailing punctuation
        let cleanUrl = url.replace(/[.,;!?)]+$/, '');
        const safeUrl = safeEncodeUrl(`http://${cleanUrl}`);
        return `${prefix}<a href="${safeUrl}" target="${linkTarget}">${cleanUrl}</a>`;
    });
    
    return processedText;
}
     
    function formatMarkdownHeaders(text) {
        // Handle h1 to h6 headers
        return text.replace(/^(#{1,6})\s+(.+)$/gm, function(match, hashes, content) {
            const level = hashes.length;
            return `<h${level} class="chat-heading chat-heading-${level}">${content.trim()}</h${level}>`;
        });
    }
    
function formatTextStyling(text) {
    // IMPORTANT: Protect BOTH HTML href and Markdown URLs from formatting
    const protectedSegments = [];
    let protectedText = text;
    
    // Step 1a: Protect HTML href="..." attributes
    protectedText = protectedText.replace(/href\s*=\s*["']([^"']+)["']/gi, function(match) {
        const placeholder = `__PROTECTED_${protectedSegments.length}__`;
        protectedSegments.push(match);
        return placeholder;
    });
    
    // Step 1b: Protect Markdown links [text](url)
    // This is crucial - we need to protect the URLs in markdown format
    protectedText = protectedText.replace(/\[([^\]]*)\]\(([^)]+)\)/g, function(match) {
        const placeholder = `__PROTECTED_${protectedSegments.length}__`;
        protectedSegments.push(match);
        return placeholder;
    });
    
    // Step 1c: Also protect bare URLs that might exist
    protectedText = protectedText.replace(/(https?:\/\/[^\s<>"]+)/gi, function(match) {
        const placeholder = `__PROTECTED_${protectedSegments.length}__`;
        protectedSegments.push(match);
        return placeholder;
    });
    
    // Step 2: Now apply text styling to the protected text
    // Handle bold text (**text**)
    protectedText = protectedText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Handle italic text (*text* or _text_) - Safari-compatible (no lookbehind)
    // Match single asterisks that aren't part of bold (**) by checking they're not followed/preceded by another *
    protectedText = protectedText.replace(/(?!\*\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>');

    // Handle underscores for italic - Safari-compatible (no lookbehind)
    // Exclude __PROTECTED_N__ placeholders by checking the content doesn't contain PROTECTED
    protectedText = protectedText.replace(/(?!__)_((?!PROTECTED)[^_\n]+)_(?!_)/g, '<em>$1</em>');
    
    // Handle strikethrough (~~text~~)
    protectedText = protectedText.replace(/~~(.*?)~~/g, '<del>$1</del>');
    
    // Step 3: Restore all protected segments
    protectedSegments.forEach((original, index) => {
        const placeholder = `__PROTECTED_${index}__`;
        protectedText = protectedText.replace(placeholder, original);
    });
    
    return protectedText;
}
    function formatBoldText(text) {
        // This function is kept for compatibility but now uses formatTextStyling
        return formatTextStyling(text);
    }
    
function convertNewlinesToBreaks(text) {
    // Split the text into paragraphs (marked by double newlines or multiple <br> tags)
    const paragraphs = text.split(/(?:\n\n|\<br\>\s*\<br\>)/g);
    
    // Filter out empty paragraphs and wrap each paragraph in <p> tags
    return paragraphs
        .map(para => para.trim())
        .filter(para => para.length > 0) // Remove empty paragraphs
        .map(para => `<p>${para}</p>`)
        .join('');
}
    function formatCodeBlocks(text) {
        // Handle fenced code blocks with language specification (```language)
        text = text.replace(/```(\w+)?\n?([\s\S]*?)```/g, (match, language, code) => {
            const lang = language || 'text';
            const escapedCode = escapeHtml(code.trim());
            return `<div class="mxchat-code-block-container">
                <div class="mxchat-code-header">
                    <span class="mxchat-code-language">${lang}</span>
                    <button class="mxchat-copy-button" aria-label="Copy to clipboard">Copy</button>
                </div>
                <pre class="mxchat-code-block"><code class="language-${lang}">${escapedCode}</code></pre>
            </div>`;
        });

        // Handle inline code with single backticks
        text = text.replace(/`([^`\n]+)`/g, '<code class="mxchat-inline-code">$1</code>');

        // Handle raw PHP tags (legacy support)
        text = text.replace(/(<\?php[\s\S]*?\?>)/g, (match) => {
            const escapedCode = escapeHtml(match);
            return `<div class="mxchat-code-block-container">
                <div class="mxchat-code-header">
                    <span class="mxchat-code-language">php</span>
                    <button class="mxchat-copy-button" aria-label="Copy to clipboard">Copy</button>
                </div>
                <pre class="mxchat-code-block"><code class="language-php">${escapedCode}</code></pre>
            </div>`;
        });

        return text;
    }
    
    function sanitizeUserInput(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeHtml(unsafe) {
        // Skip escaping if it's already escaped or contains HTML code block markup
        if (unsafe.includes('&lt;') || unsafe.includes('&gt;') || 
            unsafe.includes('<pre><code') || unsafe.includes('</code></pre>')) {
            return unsafe;
        }
        
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    function decodeHTMLEntities(text) {
        var textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    }

    // ====================================
    // UI & SCROLLING CONTROLS
    // ====================================
    
    function scrollToBottom(botIdOrInstant, instant) {
        // Handle backward compatibility: scrollToBottom() or scrollToBottom(true/false)
        var botId = 'default';
        if (typeof botIdOrInstant === 'string') {
            botId = botIdOrInstant;
            instant = instant || false;
        } else if (typeof botIdOrInstant === 'boolean') {
            instant = botIdOrInstant;
        } else {
            instant = false;
        }

        var chatBox = getElement(botId, 'chat-box');
        if (instant) {
            // Instantly set the scroll position to the bottom
            chatBox.scrollTop(chatBox.prop("scrollHeight"));
        } else {
            // Use requestAnimationFrame for smoother scrolling if needed
            let start = null;
            const scrollHeight = chatBox.prop("scrollHeight");
            const initialScroll = chatBox.scrollTop();
            const distance = scrollHeight - initialScroll;
            const duration = 500; // Duration in ms

            function smoothScroll(timestamp) {
                if (!start) start = timestamp;
                const progress = timestamp - start;
                const currentScroll = initialScroll + (distance * (progress / duration));
                chatBox.scrollTop(currentScroll);

                if (progress < duration) {
                    requestAnimationFrame(smoothScroll);
                } else {
                    chatBox.scrollTop(scrollHeight); // Ensure it's exactly at the bottom
                }
            }

            requestAnimationFrame(smoothScroll);
        }
    }

    function scrollElementToTop(element, botId) {
        botId = botId || 'default';
        var chatBox = getElement(botId, 'chat-box');
        var elementTop = element.position().top + chatBox.scrollTop();
        chatBox.animate({ scrollTop: elementTop }, 500);
    }

    function showChatWidget(botId) {
        botId = botId || 'default';
        var $button = getElement(botId, 'floating-chatbot-button');
        // First ensure display is set
        $button.css('display', 'flex');
        // Then handle the fade
        $button.fadeTo(500, 1);
        // Force visibility
        $button.removeClass('hidden');
    }

    function hideChatWidget(botId) {
        botId = botId || 'default';
        var $button = getElement(botId, 'floating-chatbot-button');
        $button.css('display', 'none');
        $button.addClass('hidden');
    }
    
    function disableScroll() {
        if (isMobile()) {
            $('body').css('overflow', 'hidden');
        }
    }
    
    function enableScroll() {
        if (isMobile()) {
            $('body').css('overflow', '');
        }
    }
    
    function isMobile() {
        // This can be a simple check, or more sophisticated detection of mobile devices
        return window.innerWidth <= 768; // Example threshold for mobile devices
    }
    
    function setFullHeight() {
        var vh = $(window).innerHeight() * 0.01;
        $(':root').css('--vh', vh + 'px');
    }


    // ====================================
    // NOTIFICATION SYSTEM
    // ====================================
    
    function createNotificationBadge() {
        const chatButton = document.getElementById('floating-chatbot-button');

        if (!chatButton) return;

        // Remove any existing badge first
        const existingBadge = chatButton.querySelector('.chat-notification-badge');
        if (existingBadge) {
            existingBadge.remove();
        }
    
        notificationBadge = document.createElement('div');
        notificationBadge.className = 'chat-notification-badge';
        notificationBadge.style.cssText = `
            display: none;
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: bold;
            z-index: 10001;
        `;
        chatButton.style.position = 'relative';
        chatButton.appendChild(notificationBadge);
        
    }
    
    function showNotification(botId) {
        botId = botId || 'default';
        const badge = getElementDOM(botId, 'chat-notification-badge');
        var $floatingChatbot = getElement(botId, 'floating-chatbot');
        if (badge && $floatingChatbot.hasClass('hidden')) {
            badge.style.display = 'block';
            badge.textContent = '1';
        }
    }

    function hideNotification(botId) {
        botId = botId || 'default';
        const badge = getElementDOM(botId, 'chat-notification-badge');
        if (badge) {
            badge.style.display = 'none';
        }
    }

    function startNotificationChecking(botId) {
        botId = botId || 'default';
        const chatPersistenceEnabled = mxchatChat.chat_persistence_toggle === 'on';
        if (!chatPersistenceEnabled) return;

        createNotificationBadge(botId);
        var instance = MxChatInstances.get(botId);
        instance.notificationCheckInterval = setInterval(function() {
            checkForNewMessages(botId);
        }, 30000); // Check every 30 seconds
    }

    function stopNotificationChecking(botId) {
        botId = botId || 'default';
        var instance = MxChatInstances.get(botId);
        if (instance.notificationCheckInterval) {
            clearInterval(instance.notificationCheckInterval);
        }
    }
    
    function checkForNewMessages() {
        const sessionId = getChatSession();
        const chatPersistenceEnabled = mxchatChat.chat_persistence_toggle === 'on';
        
        if (!chatPersistenceEnabled) return;
    
        $.ajax({
            url: mxchatChat.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_check_new_messages',
                session_id: sessionId,
                last_seen_id: lastSeenMessageId,
                nonce: mxchatChat.nonce
            },
            success: function(response) {
                if (response.success && response.data.hasNewMessages) {
                    showNotification();
                }
            }
        });
    }


// ====================================
// LIVE AGENT FUNCTIONALITY
// ====================================

function startPolling(botId) {
    console.log('[MxChat] startPolling called for botId:', botId);
    botId = botId || 'default';
    var instance = MxChatInstances.get(botId);
    // Clear any existing interval first
    stopPolling(botId);
    // Start new polling interval
    console.log('[MxChat] Starting polling interval (5s) for botId:', botId);
    instance.pollingInterval = setInterval(function() {
        checkForAgentMessages(botId);
    }, 5000);
}

function stopPolling(botId) {
    console.log('[MxChat] stopPolling called for botId:', botId);
    botId = botId || 'default';
    var instance = MxChatInstances.get(botId);
    if (instance.pollingInterval) {
        clearInterval(instance.pollingInterval);
        instance.pollingInterval = null;
        console.log('[MxChat] Polling stopped for botId:', botId);
    }
}

function checkForAgentMessages(botId) {
    console.log('[MxChat] checkForAgentMessages called for botId:', botId);
    botId = botId || 'default';
    var instance = MxChatInstances.get(botId);
    const sessionId = getChatSession(botId);
    $.ajax({
        url: mxchatChat.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'mxchat_fetch_new_messages',
            session_id: sessionId,
            last_seen_id: instance.lastSeenMessageId,
            persistence_enabled: 'true',
            nonce: mxchatChat.nonce
        },
        success: function (response) {
            if (response.success && response.data?.new_messages) {
                let hasNewMessage = false;

                response.data.new_messages.forEach(function (message) {
                    if (message.role === "agent" && !instance.processedMessageIds.has(message.id)) {
                        hasNewMessage = true;
                        appendMessage("agent", message.content, '', [], false, botId);
                        instance.lastSeenMessageId = message.id;
                        instance.processedMessageIds.add(message.id);
                    }
                });

                var $floatingChatbot = getElement(botId, 'floating-chatbot');
                if (hasNewMessage && $floatingChatbot.hasClass('hidden')) {
                    showNotification(botId);
                }

                scrollToBottom(botId, true);
            }
        },
        error: function (xhr, status, error) {
            // Polling error - silently continue
        }
    });
}

    // ====================================
    // CHAT HISTORY & PERSISTENCE
    // ====================================

function loadChatHistory(botId) {
    botId = botId || 'default';
    var instance = MxChatInstances.get(botId);

    // Prevent duplicate loading
    if (instance.chatHistoryLoaded) {
        return;
    }

    var sessionId = getChatSession(botId);
    var chatPersistenceEnabled = mxchatChat.chat_persistence_toggle === 'on';

    if (chatPersistenceEnabled && sessionId) {
        $.ajax({
            url: mxchatChat.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mxchat_fetch_conversation_history',
                session_id: sessionId
            },
            success: function(response) {
                // Handle session reset (IP changed while user was away)
                if (response.success === false && response.data && response.data.action === 'reset_session') {
                    // Silently reset session - user will start fresh
                    resetChatSession(botId);
                    instance.chatHistoryLoaded = true; // Prevent retry loop
                    return;
                }

                // Check if the response indicates success
                if (response.success) {
                    // Handle case where conversation data exists and is an array
                    if (response.data && Array.isArray(response.data.conversation)) {
                        var $chatBox = getElement(botId, 'chat-box');
                        var $fragment = $(document.createDocumentFragment());
                        let highestMessageId = instance.lastSeenMessageId;

                        // Update chat mode if provided
                        if (response.data.chat_mode) {
                            updateChatModeIndicator(response.data.chat_mode, botId);
                        }

                        // Only process if there are actual messages
                        if (response.data.conversation.length > 0) {
                            // IMPORTANT: Clear existing messages before loading history
                            $chatBox.empty();

                            $.each(response.data.conversation, function(index, message) {
                                // Skip agent messages if persistence is off
                                if (!chatPersistenceEnabled && message.role === 'agent') {
                                    return;
                                }

                                var messageClass, messageBgColor, messageFontColor;

                                switch (message.role) {
                                    case 'user':
                                        messageClass = 'user-message';
                                        messageBgColor = userMessageBgColor;
                                        messageFontColor = userMessageFontColor;
                                        break;
                                    case 'agent':
                                        messageClass = 'agent-message';
                                        messageBgColor = liveAgentMessageBgColor;
                                        messageFontColor = liveAgentMessageFontColor;
                                        break;
                                    default:
                                        messageClass = 'bot-message';
                                        messageBgColor = botMessageBgColor;
                                        messageFontColor = botMessageFontColor;
                                        break;
                                }

                                var messageElement = $('<div>').addClass(messageClass)
                                    .css({
                                        'background': messageBgColor,
                                        'color': messageFontColor
                                    });

                                var content = message.content;
                                content = content.replace(/\\'/g, "'").replace(/\\"/g, '"');
                                content = decodeHTMLEntities(content);

                                if (content.includes("mxchat-product-card") || content.includes("mxchat-image-gallery")) {
                                    messageElement.html(content);
                                } else {
                                    var formattedContent = linkify(content);
                                    messageElement.html(formattedContent);
                                }

                                $fragment.append(messageElement);

                                // Track message IDs
                                if (message.id) {
                                    highestMessageId = Math.max(highestMessageId, message.id);
                                    instance.processedMessageIds.add(message.id);
                                }
                            });

                            // Only append messages and scroll if we have content
                            $chatBox.append($fragment);
                            scrollToBottom(botId, true);

                            // Collapse quick questions if we have conversation history
                            // BUT skip auto-collapse for embedded bots (they should stay expanded)
                            if (hasQuickQuestions(botId) && !isEmbeddedBot(botId)) {
                                collapseQuickQuestions(botId);
                            }

                            // Update lastSeenMessageId after history loads
                            instance.lastSeenMessageId = highestMessageId;

                            // Only update chat mode if persistence is enabled and we have messages
                            if (chatPersistenceEnabled) {
                                var lastMessage = response.data.conversation[response.data.conversation.length - 1];
                                if (lastMessage.role === 'agent') {
                                    updateChatModeIndicator('agent', botId);
                                }
                            }

                            // Mark as loaded ONLY after successful load
                            instance.chatHistoryLoaded = true;
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                // Error loading chat history - silently continue
            }
        });
    }
}


    // ====================================
    // FILE UPLOAD FUNCTIONALITY
    // ====================================
    
    function addSafeEventListener(elementId, eventType, handler) {
        const element = document.getElementById(elementId);
        if (element) {
            element.addEventListener(eventType, handler);
        }
    }
    
    function showActivePdf(filename, botId) {
        botId = botId || 'default';
        const container = getElementDOM(botId, 'active-pdf-container');
        const nameElement = getElementDOM(botId, 'active-pdf-name');

        if (!container || !nameElement) {
            return;
        }

        nameElement.textContent = filename;
        container.style.display = 'flex';
    }

    function showActiveWord(filename, botId) {
        botId = botId || 'default';
        const container = getElementDOM(botId, 'active-word-container');
        const nameElement = getElementDOM(botId, 'active-word-name');

        if (!container || !nameElement) {
            return;
        }

        nameElement.textContent = filename;
        container.style.display = 'flex';
    }

    function removeActivePdf(botId) {
        botId = botId || 'default';
        var instance = MxChatInstances.get(botId);
        const container = getElementDOM(botId, 'active-pdf-container');
        const nameElement = getElementDOM(botId, 'active-pdf-name');

        if (!container || !nameElement || !instance.activePdfFile) return;

        fetch(mxchatChat.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'action': 'mxchat_remove_pdf',
                'session_id': getChatSession(botId),
                'nonce': mxchatChat.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.style.display = 'none';
                nameElement.textContent = '';
                activePdfFile = null;
                appendMessage('bot', 'PDF removed.');
            }
        })
        .catch(error => {
            // Error removing PDF - silently continue
        });
    }

    function removeActiveWord() {
        const container = document.getElementById('active-word-container');
        const nameElement = document.getElementById('active-word-name');
        
        if (!container || !nameElement || !activeWordFile) return;
    
        fetch(mxchatChat.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'action': 'mxchat_remove_word',
                'session_id': sessionId,
                'nonce': mxchatChat.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.style.display = 'none';
                nameElement.textContent = '';
                activeWordFile = null;
                appendMessage('bot', 'Word document removed.');
            }
        })
        .catch(error => {
            // Error removing Word document - silently continue
        });
    }

    // ====================================
    // CONSENT & COMPLIANCE (GDPR)
    // ====================================

    function initializeChatVisibility(botId) {
        botId = botId || 'default';
        const complianzEnabled = mxchatChat.complianz_toggle === 'on' ||
                                mxchatChat.complianz_toggle === '1' ||
                                mxchatChat.complianz_toggle === 1;

        if (complianzEnabled && typeof cmplz_has_consent === "function" && typeof complianz !== 'undefined') {
            // Initial check
            checkConsentAndShowChat(botId);

            // Listen for consent changes
            $(document).on('cmplz_status_change', function(event) {
                checkConsentAndShowChat(botId);
            });
        } else {
            // If Complianz is not enabled, always show
            getElement(botId, 'floating-chatbot-button')
                .css('display', 'flex')
                .removeClass('hidden no-consent')
                .fadeTo(500, 1);

            // Also check pre-chat message when Complianz is not enabled
            checkPreChatDismissal(botId);
        }
    }


    function checkConsentAndShowChat(botId) {
        botId = botId || 'default';
        var consentStatus = cmplz_has_consent('marketing');
        var consentType = complianz.consenttype;

        let $widget = getElement(botId, 'floating-chatbot-button');
        let $chatbot = getElement(botId, 'floating-chatbot');
        let $preChat = getElement(botId, 'pre-chat-message');

        if (consentStatus === true) {
            $widget
                .removeClass('no-consent')
                .css('display', 'flex')
                .removeClass('hidden')
                .fadeTo(500, 1);
            $chatbot.removeClass('no-consent');

            // Show pre-chat message if not dismissed
            checkPreChatDismissal(botId);
        } else {
            $widget
                .addClass('no-consent')
                .fadeTo(500, 0, function() {
                    $(this)
                        .css('display', 'none')
                        .addClass('hidden');
                });
            $chatbot.addClass('no-consent');

            // Hide pre-chat message when no consent
            $preChat.hide();
        }
    }


    // ====================================
    // PRE-CHAT MESSAGE HANDLING
    // ====================================

    function checkPreChatDismissal(botId) {
        botId = botId || 'default';
        $.ajax({
            url: mxchatChat.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_check_pre_chat_message_status',
                _ajax_nonce: mxchatChat.nonce
            },
            success: function(response) {
                if (response.success && !response.data.dismissed) {
                    getElement(botId, 'pre-chat-message').fadeIn(250);
                } else {
                    getElement(botId, 'pre-chat-message').hide();
                }
            },
            error: function() {
                // Error checking pre-chat dismissal - silently continue
            }
        });
    }

    function handlePreChatDismissal(botId) {
        botId = botId || 'default';
        getElement(botId, 'pre-chat-message').fadeOut(200);
        $.ajax({
            url: mxchatChat.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_dismiss_pre_chat_message',
                _ajax_nonce: mxchatChat.nonce
            },
            success: function() {
                $('#pre-chat-message').hide();
            },
            error: function() {
                // Error dismissing pre-chat message - silently continue
            }
        });
    }


    // ====================================
    // UTILITY FUNCTIONS
    // ====================================
    
    function copyToClipboard(text) {
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();
        document.execCommand('copy');
        tempInput.remove();
    }

    
    function isImageHtml(str) {
        return str.startsWith('<img') && str.endsWith('>');
    }


    // ====================================
    // EVENT HANDLERS & INITIALIZATION
    // ====================================

$(document).on('click', '.mxchat-popular-question', function () {
    var question = $(this).text();
    var botId = getBotIdFromElement(this);

    // Append the question as if the user typed it
    appendMessage("user", question, '', [], false, botId);

    // Only collapse if there are questions
    if (hasQuickQuestions(botId)) {
        collapseQuickQuestions(botId);
    }

    // Send the question to the server
    sendMessageToChatbot(question, botId);
});

$(document).on('click', '.questions-toggle-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var botId = getBotIdFromElement(this);
    expandQuickQuestions(botId);
});

$(document).on('click', '.questions-collapse-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var botId = getBotIdFromElement(this);
    collapseQuickQuestions(botId);
});
    
    // Chatbot visibility toggle handlers - use class selector for multi-instance support
    $(document).on('click', '.floating-chatbot-button', function() {
        var botId = getBotIdFromElement(this);
        var $chatbot = getElement(botId, 'floating-chatbot');
        var $badge = getElement(botId, 'chat-notification-badge');
        var $preChat = getElement(botId, 'pre-chat-message');

        if ($chatbot.hasClass('hidden')) {
            $chatbot.removeClass('hidden').addClass('visible');
            $(this).addClass('hidden');
            $badge.hide(); // Hide notification when opening chat
            disableScroll();
            $preChat.fadeOut(250);
        } else {
            $chatbot.removeClass('visible').addClass('hidden');
            $(this).removeClass('hidden');
            enableScroll();
            checkPreChatDismissal(botId);
        }
    });

    // Allow clicking anywhere on the title bar to close the chatbot
    $(document).on('click', '.chatbot-top-bar', function() {
        var botId = getBotIdFromElement(this);
        getElement(botId, 'floating-chatbot').addClass('hidden').removeClass('visible');
        getElement(botId, 'floating-chatbot-button').removeClass('hidden');
        enableScroll();
    });

    $(document).on('click', '.close-pre-chat-message', function(e) {
        e.stopPropagation(); // Prevent triggering the parent .pre-chat-message click
        var botId = getBotIdFromElement(this);
        getElement(botId, 'pre-chat-message').fadeOut(200, function() {
            $(this).remove();
        });
    });


    // PDF upload button handlers - use class selector
    $(document).on('click', '.pdf-upload-btn', function() {
        var botId = getBotIdFromElement(this);
        var pdfInput = getElementDOM(botId, 'pdf-upload');
        if (pdfInput) pdfInput.click();
    });

    // Word upload button handlers - use class selector
    $(document).on('click', '.word-upload-btn', function() {
        var botId = getBotIdFromElement(this);
        var wordInput = getElementDOM(botId, 'word-upload');
        if (wordInput) wordInput.click();
    });
    
    // PDF file input change handler
    addSafeEventListener('pdf-upload', 'change', async function(e) {
        const file = e.target.files[0];
    
        if (!file || file.type !== 'application/pdf') {
            alert('Please select a valid PDF file.');
            return;
        }
    
        if (!sessionId) {
            alert('Error: No session ID found');
            return;
        }

        if (!mxchatChat || !mxchatChat.ajax_url || !mxchatChat.nonce) {
            alert('Error: Ajax configuration missing');
            return;
        }
    
        // Disable buttons and show loading state
        const uploadBtn = document.getElementById('pdf-upload-btn');
        const sendBtn = document.getElementById('send-button');
        const originalBtnContent = uploadBtn.innerHTML;
    
        try {
            const formData = new FormData();
            formData.append('action', 'mxchat_upload_pdf');
            formData.append('pdf_file', file);
            formData.append('session_id', sessionId);
            formData.append('nonce', mxchatChat.nonce);
    
            uploadBtn.disabled = true;
            sendBtn.disabled = true;
            uploadBtn.innerHTML = `<svg class="spinner" viewBox="0 0 50 50">
                <circle cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>`;
    
            const response = await fetch(mxchatChat.ajax_url, {
                method: 'POST',
                body: formData
            });
    
            const data = await response.json();
    
            if (data.success) {
                // Hide popular questions if they exist
                const popularQuestionsContainer = document.getElementById('mxchat-popular-questions');
                if (hasQuickQuestions()) {
                    collapseQuickQuestions();
                }
                
                // Show the active PDF name
                showActivePdf(data.data.filename);
                
                appendMessage('bot', data.data.message);
                scrollToBottom();
                activePdfFile = data.data.filename;
            } else {
                alert('Failed to upload PDF. Please try again.');
            }
        } catch (error) {
            alert('Error uploading file. Please try again.');
        } finally {
            uploadBtn.disabled = false;
            sendBtn.disabled = false;
            uploadBtn.innerHTML = originalBtnContent;
            this.value = ''; // Reset file input
        }
    });
    
    // Word file input change handler
    addSafeEventListener('word-upload', 'change', async function(e) {
        const file = e.target.files[0];
    
        if (!file || file.type !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            alert('Please select a valid Word document (.docx).');
            return;
        }
    
        if (!sessionId) {
            alert('Error: No session ID found');
            return;
        }

        // Disable buttons and show loading state
        const uploadBtn = document.getElementById('word-upload-btn');
        const sendBtn = document.getElementById('send-button');
        const originalBtnContent = uploadBtn.innerHTML;
    
        try {
            const formData = new FormData();
            formData.append('action', 'mxchat_upload_word');
            formData.append('word_file', file);
            formData.append('session_id', sessionId);
            formData.append('nonce', mxchatChat.nonce);
    
            uploadBtn.disabled = true;
            sendBtn.disabled = true;
            uploadBtn.innerHTML = `<svg class="spinner" viewBox="0 0 50 50">
                <circle cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>`;
    
            const response = await fetch(mxchatChat.ajax_url, {
                method: 'POST',
                body: formData
            });
    
            const data = await response.json();
    
            if (data.success) {
                // Hide popular questions if they exist
                const popularQuestionsContainer = document.getElementById('mxchat-popular-questions');
                if (hasQuickQuestions()) {
                    collapseQuickQuestions();
                }
    
                // Show the active Word document name
                showActiveWord(data.data.filename);
                
                appendMessage('bot', data.data.message);
                scrollToBottom();
                activeWordFile = data.data.filename;
            } else {
                alert('Failed to upload Word document. Please try again.');
            }
        } catch (error) {
            alert('Error uploading file. Please try again.');
        } finally {
            uploadBtn.disabled = false;
            sendBtn.disabled = false;
            uploadBtn.innerHTML = originalBtnContent;
            this.value = ''; // Reset file input
        }
    });
    
    // Remove button click handlers
    document.getElementById('remove-pdf-btn')?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        removeActivePdf();
    });
    
    document.getElementById('remove-word-btn')?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        removeActiveWord();
    });
    
    // Window resize handlers
    $(window).on('resize orientationchange', function() {
        setFullHeight();
    });


    // ====================================
    // TOOLBAR & STYLING SETUP
    // ====================================
    
    // Apply toolbar settings
    if (mxchatChat.chat_toolbar_toggle === 'on') {
        $('.chat-toolbar').show();
    } else {
        $('.chat-toolbar').hide();
    }
    
    // Apply toolbar icon colors
    const toolbarElements = [
        '#mxchat-chatbot .toolbar-btn svg',
        '#mxchat-chatbot .active-pdf-name',
        '#mxchat-chatbot .active-word-name',
        '#mxchat-chatbot .remove-pdf-btn svg',
        '#mxchat-chatbot .remove-word-btn svg',
        '#mxchat-chatbot .toolbar-perplexity svg'
    ];
    
    toolbarElements.forEach(selector => {
        $(selector).css({
            'fill': toolbarIconColor,
            'stroke': toolbarIconColor,
            'color': toolbarIconColor
        });
    });


// ====================================
//  EMAIL COLLECTION SETUP - MULTI-INSTANCE VERSION
// ====================================
// Only run email collection setup if it's enabled
if (mxchatChat && mxchatChat.email_collection_enabled === 'on') {

    // Track submitting state per bot
    const emailSubmittingState = {};

    // Add CSS animations for email form (once globally)
    if (!document.getElementById('email-error-styles')) {
        const style = document.createElement('style');
        style.id = 'email-error-styles';
        style.textContent = `
            @keyframes fadeInError {
                from { opacity: 0; transform: translateY(-5px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .email-input-shake {
                animation: shake 0.5s ease-in-out;
            }
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .email-spinner {
                display: inline-block;
                vertical-align: middle;
            }
        `;
        document.head.appendChild(style);
    }

    // Helper functions for email collection (multi-instance aware)
    function showEmailFormForBot(botId) {
        var emailBlocker = getElementDOM(botId, 'email-blocker');
        var chatContainer = getElementDOM(botId, 'chat-container');
        if (emailBlocker) emailBlocker.style.display = 'flex';
        if (chatContainer) chatContainer.style.display = 'none';
    }

    function showChatContainerForBot(botId) {
        var emailBlocker = getElementDOM(botId, 'email-blocker');
        var chatContainer = getElementDOM(botId, 'chat-container');
        if (emailBlocker) emailBlocker.style.display = 'none';
        if (chatContainer) chatContainer.style.display = 'flex';

        // Load chat history for this bot
        if (typeof loadChatHistory === 'function') {
            loadChatHistory(botId);
        }
    }

    function isValidEmailAddress(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email.trim()) && email.length <= 254;
    }

    function isValidNameInput(name) {
        return name && name.trim().length >= 2 && name.trim().length <= 100;
    }

    /**
     * Replace {visitor_name} placeholder in intro message with actual visitor name
     * @param {string} botId - The bot instance ID
     * @param {string} visitorName - The visitor's name to insert
     */
    function replaceVisitorNamePlaceholder(botId, visitorName) {
        var chatBox = getElementDOM(botId, 'chat-box');
        if (!chatBox) return;

        // Find the first bot message (intro message)
        var introMessage = chatBox.querySelector('.bot-message');
        if (!introMessage) return;

        var messageContent = introMessage.querySelector('div[dir="auto"]');
        if (!messageContent) return;

        var html = messageContent.innerHTML;

        // Replace {visitor_name} placeholder (case-insensitive)
        if (visitorName && visitorName.trim()) {
            // Escape HTML to prevent XSS
            var safeName = $('<div>').text(visitorName.trim()).html();
            html = html.replace(/\{visitor_name\}/gi, safeName);
        } else {
            // Remove placeholder and clean up spacing if no name provided
            html = html.replace(/\{visitor_name\}/gi, '');
            // Clean up any double spaces that might result
            html = html.replace(/\s{2,}/g, ' ').trim();
        }

        messageContent.innerHTML = html;
    }

    function setEmailSubmissionState(botId, loading) {
        var submitButton = getElementDOM(botId, 'email-submit-button');
        var emailInput = getElementDOM(botId, 'user-email');
        var nameInput = getElementDOM(botId, 'user-name');

        if (loading) {
            emailSubmittingState[botId] = true;
            if (submitButton) submitButton.disabled = true;
            if (emailInput) emailInput.disabled = true;
            if (nameInput) nameInput.disabled = true;

            if (submitButton && !submitButton.getAttribute('data-original-html')) {
                submitButton.setAttribute('data-original-html', submitButton.innerHTML);
                const originalText = submitButton.textContent;
                submitButton.innerHTML = `
                    <svg class="email-spinner" style="width: 16px; height: 16px; margin-right: 8px; animation: spin 1s linear infinite;" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" stroke-dasharray="31.416" stroke-dashoffset="31.416">
                            <animate attributeName="stroke-dasharray" dur="2s" values="0 31.416;15.708 15.708;0 31.416" repeatCount="indefinite"/>
                            <animate attributeName="stroke-dashoffset" dur="2s" values="0;-15.708;-31.416" repeatCount="indefinite"/>
                        </circle>
                    </svg>
                    ${originalText}
                `;
                submitButton.style.opacity = '0.8';
            }
        } else {
            emailSubmittingState[botId] = false;
            if (submitButton) submitButton.disabled = false;
            if (emailInput) emailInput.disabled = false;
            if (nameInput) nameInput.disabled = false;

            if (submitButton) {
                const originalHtml = submitButton.getAttribute('data-original-html');
                if (originalHtml) {
                    submitButton.innerHTML = originalHtml;
                }
                submitButton.style.opacity = '1';
            }
        }
    }

    function showEmailError(botId, message) {
        clearEmailError(botId);

        var emailForm = getElementDOM(botId, 'email-collection-form');
        if (!emailForm) return;

        const errorDiv = document.createElement('div');
        errorDiv.className = 'email-error';
        errorDiv.style.cssText = `
            color: #e74c3c;
            font-size: 12px;
            margin-top: 8px;
            padding: 4px 0;
            animation: fadeInError 0.3s ease;
        `;
        errorDiv.textContent = message;
        emailForm.appendChild(errorDiv);

        // Add shake animation to inputs
        var emailInput = getElementDOM(botId, 'user-email');
        var nameInput = getElementDOM(botId, 'user-name');

        if (emailInput) {
            emailInput.classList.add('email-input-shake');
            setTimeout(() => emailInput.classList.remove('email-input-shake'), 500);
        }
        if (nameInput) {
            nameInput.classList.add('email-input-shake');
            setTimeout(() => nameInput.classList.remove('email-input-shake'), 500);
        }
    }

    function clearEmailError(botId) {
        var emailForm = getElementDOM(botId, 'email-collection-form');
        if (emailForm) {
            const existingErrors = emailForm.querySelectorAll('.email-error');
            existingErrors.forEach(error => error.remove());
        }
    }

    function checkSessionAndEmailForBot(botId) {
        const sessionId = getChatSession(botId);

        fetch(mxchatChat.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'mxchat_check_email_provided',
                session_id: sessionId,
                nonce: mxchatChat.nonce,
            })
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then((data) => {
            if (data.success) {
                if (data.data.logged_in || data.data.email) {
                    showChatContainerForBot(botId);
                } else {
                    showEmailFormForBot(botId);
                }
            } else {
                showEmailFormForBot(botId);
            }
        })
        .catch((error) => {
            showEmailFormForBot(botId);
        });
    }

    // Event delegation for email form submission
    $(document).on('submit', '.email-collection-form', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var botId = getBotIdFromElement(this);

        // Prevent double submission
        if (emailSubmittingState[botId]) {
            return false;
        }

        var emailInput = getElementDOM(botId, 'user-email');
        var nameInput = getElementDOM(botId, 'user-name');
        var userEmail = emailInput ? emailInput.value.trim() : '';
        var userName = nameInput ? nameInput.value.trim() : '';
        var sessionId = getChatSession(botId);

        // Validate email
        if (!userEmail) {
            showEmailError(botId, 'Please enter your email address.');
            return false;
        }

        if (!isValidEmailAddress(userEmail)) {
            showEmailError(botId, 'Please enter a valid email address.');
            return false;
        }

        // Validate name if field exists and has content
        if (nameInput && userName && !isValidNameInput(userName)) {
            showEmailError(botId, 'Please enter a valid name (2-100 characters).');
            return false;
        }

        clearEmailError(botId);
        setEmailSubmissionState(botId, true);

        // Prepare form data
        const formData = new URLSearchParams({
            action: 'mxchat_handle_save_email_and_response',
            email: userEmail,
            session_id: sessionId,
            nonce: mxchatChat.nonce,
        });

        if (userName) {
            formData.append('name', userName);
        }

        fetch(mxchatChat.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then((data) => {
            setEmailSubmissionState(botId, false);

            if (data.success) {
                showChatContainerForBot(botId);

                // Replace {visitor_name} placeholder in intro message with actual name
                if (userName) {
                    replaceVisitorNamePlaceholder(botId, userName);
                } else {
                    // Remove placeholder if no name provided
                    replaceVisitorNamePlaceholder(botId, '');
                }

                if (data.message && typeof appendMessage === 'function') {
                    setTimeout(() => {
                        appendMessage('bot', data.message, '', [], false, botId);
                        if (typeof scrollToBottom === 'function') {
                            scrollToBottom(botId);
                        }
                    }, 100);
                }
            } else {
                showEmailError(botId, data.message || 'Failed to save email. Please try again.');
            }
        })
        .catch((error) => {
            setEmailSubmissionState(botId, false);
            showEmailError(botId, 'An error occurred. Please try again.');
        });

        return false;
    });

    // Real-time email validation using event delegation
    $(document).on('input', '.mxchat-email-input', function() {
        var botId = getBotIdFromElement(this);
        var $input = $(this);

        // Clear previous timeout
        clearTimeout($input.data('validationTimeout'));

        // Debounce validation
        var timeout = setTimeout(() => {
            var email = this.value.trim();
            clearEmailError(botId);

            if (email && !isValidEmailAddress(email)) {
                showEmailError(botId, 'Please enter a valid email address.');
            }
        }, 500);

        $input.data('validationTimeout', timeout);
    });

    // Handle Enter key in email input
    $(document).on('keypress', '.mxchat-email-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var botId = getBotIdFromElement(this);
            if (!emailSubmittingState[botId]) {
                $(this).closest('.email-collection-form').submit();
            }
        }
    });

    // Handle Enter key in name input
    $(document).on('keypress', '.mxchat-name-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var botId = getBotIdFromElement(this);
            if (!emailSubmittingState[botId]) {
                $(this).closest('.email-collection-form').submit();
            }
        }
    });

    // Initialize email check for all bot instances
    $('.mxchat-chatbot-wrapper').each(function() {
        var botId = $(this).data('bot-id') || 'default';
        var emailBlocker = getElementDOM(botId, 'email-blocker');

        // Only check if email blocker exists for this bot
        if (emailBlocker) {
            if (mxchatChat.skip_email_check && mxchatChat.initial_email_state) {
                if (mxchatChat.initial_email_state.show_email_form) {
                    showEmailFormForBot(botId);
                } else {
                    showChatContainerForBot(botId);
                }
            } else {
                setTimeout(function() {
                    checkSessionAndEmailForBot(botId);
                }, 100);
            }
        }
    });
}

    // Open chatbot when pre-chat message is clicked - use class selector for multi-instance
    $(document).on('click', '.pre-chat-message', function() {
        var botId = getBotIdFromElement(this);
        var $chatbot = getElement(botId, 'floating-chatbot');
        if ($chatbot.hasClass('hidden')) {
            $chatbot.removeClass('hidden').addClass('visible');
            getElement(botId, 'floating-chatbot-button').addClass('hidden');
            $(this).fadeOut(250); // Hide pre-chat message
            disableScroll(); // Disable scroll when chatbot opens
        }
    });

    // Dismiss pre-chat message via close button - handled by event delegation above at line ~2376
    // This is a fallback for legacy support
    $(document).on('click', '.close-pre-chat-message', function() {
        var botId = getBotIdFromElement(this);
        var $preChat = getElement(botId, 'pre-chat-message');
        $preChat.fadeOut(200); // Hide the message

        // Send an AJAX request to set the transient flag for 24 hours
        $.ajax({
            url: mxchatChat.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_dismiss_pre_chat_message',
                _ajax_nonce: mxchatChat.nonce
            },
            success: function() {
                // Ensure the message is hidden after dismissal
                $preChat.hide();
            },
            error: function() {
                // Error dismissing pre-chat message - silently continue
            }
        });
    });


function hasQuickQuestions(botId) {
    botId = botId || 'default';
    var questionsContainer = getElementDOM(botId, 'mxchat-popular-questions');
    if (!questionsContainer) return false;
    const questionButtons = questionsContainer.querySelectorAll('.mxchat-popular-question');
    return questionButtons.length > 0;
}

/**
 * Check if a bot is embedded (not floating)
 * Embedded bots don't have a .floating-chatbot wrapper
 */
function isEmbeddedBot(botId) {
    botId = botId || 'default';
    var floatingWrapper = document.getElementById('floating-chatbot-' + botId);
    return !floatingWrapper;
}

function collapseQuickQuestions(botId) {
    botId = botId || 'default';
    const questionsContainer = getElementDOM(botId, 'mxchat-popular-questions');
    if (questionsContainer && hasQuickQuestions(botId)) {
        questionsContainer.classList.add('collapsed');
        questionsContainer.classList.add('has-been-collapsed');
        try {
            sessionStorage.setItem('mxchat_questions_collapsed_' + botId, 'true');
            sessionStorage.setItem('mxchat_questions_has_been_collapsed_' + botId, 'true');
        } catch (e) {
            // Ignore if sessionStorage is not available
        }
    }
}

function expandQuickQuestions(botId) {
    botId = botId || 'default';
    const questionsContainer = getElementDOM(botId, 'mxchat-popular-questions');
    if (questionsContainer && hasQuickQuestions(botId)) {
        questionsContainer.classList.remove('collapsed');
        try {
            sessionStorage.setItem('mxchat_questions_collapsed_' + botId, 'false');
        } catch (e) {
            // Ignore if sessionStorage is not available
        }
    }
}

function checkQuickQuestionsState(botId) {
    botId = botId || 'default';
    if (!hasQuickQuestions(botId)) {
        return; // Don't do anything if no questions exist
    }

    // Skip restoring collapsed state for embedded bots - they should always start expanded
    if (isEmbeddedBot(botId)) {
        return;
    }

    try {
        const isCollapsed = sessionStorage.getItem('mxchat_questions_collapsed_' + botId);
        const hasBeenCollapsed = sessionStorage.getItem('mxchat_questions_has_been_collapsed_' + botId);

        const questionsContainer = getElementDOM(botId, 'mxchat-popular-questions');
        if (questionsContainer) {
            if (hasBeenCollapsed === 'true') {
                questionsContainer.classList.add('has-been-collapsed');
            }
            if (isCollapsed === 'true') {
                questionsContainer.classList.add('collapsed');
            }
        }
    } catch (e) {
        // Ignore if sessionStorage is not available
    }
}

//   Global delegation for dynamically added links as fallback
// Use class selector for multi-instance support
$(document).on('click', '.chat-box a[href]:not([data-tracked])', function(e) {
    const $link = $(this);
    const messageDiv = $link.closest('.bot-message, .agent-message');

    // Only process bot/agent message links
    if (messageDiv.length > 0) {
        const originalHref = $link.attr('href');

        if (originalHref && (originalHref.startsWith('http://') || originalHref.startsWith('https://'))) {
            e.preventDefault();
            e.stopPropagation();

            // Mark as tracked
            $link.attr('data-tracked', 'true');

            // Get bot ID from the chat box context
            var botId = getBotIdFromElement(this);

            // Get message context from the message div
            const messageText = messageDiv.text().substring(0, 200);

            $.ajax({
                url: mxchatChat.ajax_url,
                type: 'POST',
                data: {
                    action: 'mxchat_track_url_click',
                    session_id: getChatSession(botId),
                    url: originalHref,
                    message_context: messageText,
                    nonce: mxchatChat.nonce
                },
                complete: function() {
                    if ($link.attr('target') === '_blank' || linkTarget === '_blank') {
                        window.open(originalHref, '_blank');
                    } else {
                        window.location.href = originalHref;
                    }
                }
            });

            return false;
        }
    }
});

    // ====================================
    // MAIN INITIALIZATION
    // ====================================

    // Initialize all chatbot instances on the page
    initializeAllInstances();

    // Legacy initialization for single bot compatibility
    $('.floating-chatbot.hidden').each(function() {
        var botId = getBotIdFromElement(this);
        getElement(botId, 'floating-chatbot-button').removeClass('hidden');
    });

    // Initialize when document is ready
    setFullHeight();
    trackOriginatingPage();

    // Only load chat history if email collection is disabled
    if (mxchatChat.email_collection_enabled !== 'on') {
        // Load history for all instances
        $('.mxchat-chatbot-wrapper').each(function() {
            var botId = $(this).data('bot-id') || 'default';
            loadChatHistory(botId);
        });
    }

    // Initialize chat visibility for all instances
    $('.mxchat-chatbot-wrapper').each(function() {
        var botId = $(this).data('bot-id') || 'default';
        initializeChatVisibility(botId);
    });

    // Make functions globally available for add-ons
    window.hasQuickQuestions = hasQuickQuestions;
    window.collapseQuickQuestions = collapseQuickQuestions;
    window.appendMessage = appendMessage;
    window.appendThinkingMessage = appendThinkingMessage;
    window.scrollToBottom = scrollToBottom;
    window.scrollElementToTop = scrollElementToTop;
    window.replaceLastMessage = replaceLastMessage;
    window.callMxChat = callMxChat;
    window.callMxChatStream = callMxChatStream;
    window.shouldUseStreaming = shouldUseStreaming;
    window.getChatSession = getChatSession;
    window.getPageContext = getPageContext;
    window.updateStreamingMessage = updateStreamingMessage;
    window.MxChatInstances = MxChatInstances;
    window.getElement = getElement;
    window.getElementDOM = getElementDOM;
    window.getBotIdFromElement = getBotIdFromElement;

}); // End of jQuery ready


// ====================================
// GLOBAL EVENT LISTENERS (Outside jQuery)
// ====================================

// Event listener for copy button (code blocks)
document.addEventListener("click", (e) => {
    if (e.target.classList.contains("mxchat-copy-button")) {
        const copyButton = e.target;
        const codeBlock = copyButton
            .closest(".mxchat-code-block-container")
            .querySelector(".mxchat-code-block code");

        if (codeBlock) {
            // Preserve formatting using innerText
            navigator.clipboard.writeText(codeBlock.innerText).then(() => {
                copyButton.textContent = "Copied!";
                copyButton.setAttribute("aria-label", "Copied to clipboard");

                setTimeout(() => {
                    copyButton.textContent = "Copy";
                    copyButton.setAttribute("aria-label", "Copy to clipboard");
                }, 2000);
            });
        }
    }
});

