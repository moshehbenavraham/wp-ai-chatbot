jQuery(document).ready(function($) {
    let isOpen = false;
    let isLoading = false;
    
    // Generate or retrieve session ID from localStorage
    let sessionId = localStorage.getItem('baachal_session_id');
    if (!sessionId) {
        sessionId = 'chat_' + generateUUID();
        localStorage.setItem('baachal_session_id', sessionId);
    }
    
    // Debug: log session ID (can be removed in production)
    if (baachal_ajax.debug_mode === '1') {
        console.log('Baachal Session ID:', sessionId);
    }
    
    // Load saved messages on page load (if persistence is enabled)
    if (baachal_ajax.message_persistence === '1') {
        loadSavedMessages();
    }
    
    // Toggle chatbot
    $('#chatbot-toggle').on('click', function() {
        if (isOpen) {
            closeChatbot();
        } else {
            openChatbot();
        }
    });
    
    // Close chatbot
    $('#chatbot-close').on('click', function() {
        closeChatbot();
    });
    
    // Clear chat history (only if button exists)
    if ($('#chatbot-clear').length) {
        $('#chatbot-clear').on('click', function() {
            if (confirm('Are you sure you want to clear the chat history?')) {
                clearChatHistory();
            }
        });
    }
    
    // Send message on button click
    $('#chatbot-send').on('click', function() {
        sendMessage();
    });
    
    // Send message on Enter key
    $('#chatbot-input').on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    function openChatbot() {
        // Trigger custom event before opening
        $(document).trigger('baachal_before_open', {widget: $('#ai-chatbot-widget')});
        
        $('#chatbot-container').removeClass('chatbot-hidden').fadeIn(300);
        isOpen = true;
        $('#chatbot-input').focus();
        
        // Add class to show close icon
        $('#ai-chatbot-widget').addClass('chatbot-open');
        
        // Scroll to bottom when opening (in case there are messages)
        setTimeout(function() {
            scrollToBottom();
        }, 350); // After fade-in animation completes
        
        // Trigger custom event after opening
        $(document).trigger('baachal_after_open', {widget: $('#ai-chatbot-widget')});
    }

    function closeChatbot() {
        // Trigger custom event before closing
        $(document).trigger('baachal_before_close', {widget: $('#ai-chatbot-widget')});
        
        $('#chatbot-container').fadeOut(300, function() {
            $(this).addClass('chatbot-hidden');
        });
        isOpen = false;
        
        // Remove class to show chat icon
        $('#ai-chatbot-widget').removeClass('chatbot-open');
        
        // Trigger custom event after closing
        $(document).trigger('baachal_after_close', {widget: $('#ai-chatbot-widget')});
    }
    
    function sendMessage() {
        if (isLoading) return;
        
        const message = $('#chatbot-input').val().trim();
        if (!message) return;

        // Trigger custom event before sending message - allow modification or cancellation
        const beforeSendEvent = $.Event('baachal_before_send_message', {
            message: message,
            cancelled: false
        });
        $(document).trigger(beforeSendEvent);
        
        // Allow other scripts to cancel the message sending
        if (beforeSendEvent.cancelled) {
            return;
        }
        
        // Use potentially modified message
        const finalMessage = beforeSendEvent.message;
        
        // Add user message to chat
        addMessage(finalMessage, 'user');
        
        // Clear input
        $('#chatbot-input').val('');
        
        // Show loading
        showLoading();
        
        // Trigger custom event when sending starts
        $(document).trigger('baachal_sending_started', {message: finalMessage});
        
        // Send AJAX request
        $.ajax({
            url: baachal_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'baachal_message',
                message: finalMessage,
                nonce: baachal_ajax.nonce,
                session_id: sessionId
            },
            success: function(response) {
                hideLoading();
                
                // Trigger custom event for response received
                $(document).trigger('baachal_response_received', {
                    response: response,
                    originalMessage: finalMessage
                });
                
                if (response.success) {
                    // Allow filtering of bot response before display
                    const filteredResponse = $(document).triggerHandler('baachal_filter_bot_response', [response.data]) || response.data;
                    addMessage(filteredResponse, 'bot');
                    
                    // Trigger custom event after successful response
                    $(document).trigger('baachal_response_success', {
                        botMessage: filteredResponse,
                        userMessage: finalMessage
                    });
                } else {
                    let errorMessage = 'Sorry, I encountered an error. Please try again.';
                    
                    // Show detailed error in debug mode
                    if (baachal_ajax.debug_mode === '1' && response.data) {
                        errorMessage = 'Error: ' + response.data;
                    }
                    
                    // Allow filtering of error message
                    errorMessage = $(document).triggerHandler('baachal_filter_error_message', [errorMessage, response]) || errorMessage;
                    
                    addMessage(errorMessage, 'bot');
                    
                    // Trigger custom event for error
                    $(document).trigger('baachal_response_error', {
                        error: errorMessage,
                        response: response,
                        userMessage: finalMessage
                    });
                    
                    // Always log to console for debugging
                    console.error('Chatbot error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                let errorMessage = 'Sorry, I\'m having trouble connecting. Please try again later.';
                
                // Show detailed error in debug mode
                if (baachal_ajax.debug_mode === '1') {
                    errorMessage = 'Connection error: ' + error + ' (Status: ' + status + ')';
                }
                
                // Allow filtering of connection error message
                errorMessage = $(document).triggerHandler('baachal_filter_connection_error', [errorMessage, xhr, status, error]) || errorMessage;
                
                addMessage(errorMessage, 'bot');
                
                // Trigger custom event for connection error
                $(document).trigger('baachal_connection_error', {
                    error: errorMessage,
                    xhr: xhr,
                    status: status,
                    originalError: error,
                    userMessage: finalMessage
                });
                
                console.error('AJAX error:', error, xhr);
            }
        });
    }
    
    function addMessage(content, type, save = true) {
        // Trigger custom event before adding message - allow modification
        const beforeAddEvent = $.Event('baachal_before_add_message', {
            content: content,
            type: type,
            save: save,
            cancelled: false
        });
        $(document).trigger(beforeAddEvent);
        
        // Allow cancellation
        if (beforeAddEvent.cancelled) {
            return;
        }
        
        // Use potentially modified values
        content = beforeAddEvent.content;
        type = beforeAddEvent.type;
        save = beforeAddEvent.save;
        
        // Process content for bot messages to handle links and safe HTML blocks
        let processedContent = content;
        if (type === 'bot') {
            processedContent = convertMarkdownLinks(processedContent);
            processedContent = sanitizeBotHtml(processedContent);
        } else {
            processedContent = escapeHtml(content);
        }
        
        const messageHtml = `
            <div class="message ${type}-message" data-type="${type}" data-timestamp="${Date.now()}">
                <div class="message-content">${processedContent}</div>
            </div>
        `;
        
        $('#chatbot-messages').append(messageHtml);
        scrollToBottom();
        
        // Trigger custom event after adding message
        $(document).trigger('baachal_after_add_message', {
            content: content,
            processedContent: processedContent,
            type: type,
            messageElement: $('#chatbot-messages .message').last()
        });
        
        // Messages are now automatically saved on the server side
        // No need to save here as it's handled in the AJAX response
    }
    
    function showLoading() {
        isLoading = true;
        $('#chatbot-loading').removeClass('chatbot-hidden');
        $('#chatbot-send').prop('disabled', true);
        scrollToBottom();
    }
    
    function hideLoading() {
        isLoading = false;
        $('#chatbot-loading').addClass('chatbot-hidden');
        $('#chatbot-send').prop('disabled', false);
    }
    
    function scrollToBottom() {
        const messagesContainer = $('#chatbot-messages')[0];
        messagesContainer.scrollTo({
            top: messagesContainer.scrollHeight,
            behavior: 'smooth'
        });
    }
    
    function sanitizeBotHtml(rawContent) {
        if (typeof DOMParser === 'undefined') {
            return escapeHtml(rawContent);
        }
        
        const parser = new DOMParser();
        const parsed = parser.parseFromString(`<div>${rawContent}</div>`, 'text/html');
        
        if (parsed.querySelector('parsererror')) {
            return escapeHtml(rawContent);
        }
        
        const allowedTags = new Set([
            'DIV', 'P', 'SPAN', 'A', 'IMG', 'UL', 'OL', 'LI', 'STRONG', 'EM', 'B', 'I', 'BR',
            'H3', 'H4', 'H5', 'H6', 'SMALL', 'ARTICLE', 'SECTION', 'BUTTON', 'FIGURE', 'FIGCAPTION',
            'SUP', 'SUB', 'DEL', 'INS', 'BDI'
        ]);
        
        const allowedAttributes = {
            '*': ['class'],
            A: ['href', 'target', 'rel', 'title'],
            IMG: ['src', 'alt', 'loading', 'width', 'height'],
            BUTTON: ['type']
        };
        
        const safeHrefPattern = /^(https?:|mailto:|tel:|\/|#)/i;
        const safeLoadingValues = ['lazy', 'eager', 'auto'];
        
        const traverse = (node) => {
            Array.from(node.children).forEach((child) => {
                if (!allowedTags.has(child.tagName)) {
                    const fragment = parsed.createDocumentFragment();
                    while (child.firstChild) {
                        fragment.appendChild(child.firstChild);
                    }
                    child.replaceWith(fragment);
                    return;
                }
                
                Array.from(child.attributes).forEach((attr) => {
                    const attrName = attr.name.toLowerCase();
                    const allowedForTag = (allowedAttributes[child.tagName] || []).concat(allowedAttributes['*'] || []);
                    
                    if (!allowedForTag.includes(attrName)) {
                        child.removeAttribute(attr.name);
                        return;
                    }
                    
                    if (attrName === 'href') {
                        const value = attr.value.trim();
                        if (!safeHrefPattern.test(value)) {
                            child.setAttribute('href', '#');
                        }
                    }
                    
                    if (attrName === 'target') {
                        child.setAttribute('target', '_blank');
                    }
                    
                    if (attrName === 'rel') {
                        child.setAttribute('rel', 'noopener noreferrer');
                    }
                    
                    if (attrName === 'loading') {
                        const value = attr.value.toLowerCase();
                        if (!safeLoadingValues.includes(value)) {
                            child.setAttribute('loading', 'lazy');
                        }
                    }
                });
                
                if (child.tagName === 'A') {
                    if (!child.hasAttribute('target')) {
                        child.setAttribute('target', '_blank');
                    }
                    if (!child.hasAttribute('rel')) {
                        child.setAttribute('rel', 'noopener noreferrer');
                    }
                }
                
                traverse(child);
            });
        };
        
        const container = parsed.body.firstElementChild;
        if (!container) {
            return '';
        }
        
        traverse(container);
        return container.innerHTML;
    }
    
    function convertMarkdownLinks(text) {
        return text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Close chatbot when clicking outside - DISABLED
    // $(document).on('click', function(e) {
    //     if (isOpen && !$(e.target).closest('#ai-chatbot-widget').length) {
    //         closeChatbot();
    //     }
    // });
    
    // Prevent closing when clicking inside the chatbot - NO LONGER NEEDED
    // $('#ai-chatbot-widget').on('click', function(e) {
    //     e.stopPropagation();
    // });
    
    // Auto-resize input area
    $('#chatbot-input').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
    
    // Message persistence functions (now using database)
    function loadSavedMessages() {
        if (baachal_ajax.message_persistence !== '1') {
            return;
        }
        
        $.ajax({
            url: baachal_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'baachal_get_chat_history',
                nonce: baachal_ajax.nonce,
                session_id: sessionId
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // Clear existing messages except welcome message
                    $('#chatbot-messages .message:not(:first)').remove();
                    
                    // Restore saved messages
                    response.data.forEach(function(message) {
                        addMessage(message.message, message.type, false); // false = don't save again
                    });
                    
                    // Scroll to bottom after loading messages
                    setTimeout(function() {
                        scrollToBottom();
                    }, 100); // Small delay to ensure DOM is updated
                }
            },
            error: function(xhr, status, error) {
                console.warn('Could not load chat history:', error);
            }
        });
    }
    
    function clearChatHistory() {
        $.ajax({
            url: baachal_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'baachal_clear_chat_history',
                nonce: baachal_ajax.nonce,
                session_id: sessionId
            },
            success: function(response) {
                if (response.success) {
                    $('#chatbot-messages .message:not(:first)').remove();
                    
                    // Generate new session ID for fresh start
                    sessionId = 'chat_' + generateUUID();
                    localStorage.setItem('baachal_session_id', sessionId);
                    
                    console.log('Chat history cleared and new session started');
                }
            },
            error: function(xhr, status, error) {
                console.warn('Could not clear chat history:', error);
            }
        });
    }
    
    // Generate a simple UUID for session ID
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
});
