/**
 * Live Chat Admin Dashboard JavaScript
 * 
 * @package AIChat
 * @subpackage LiveChat
 */

(function($) {
    'use strict';

    const LiveChat = {
        pollInterval: null,
        currentSession: null,
        lastMessageId: 0,

        init: function() {
            const $chat = $('.aichat-livechat-chat');
            this.currentSession = $chat.data('session');
            this.lastMessageId = parseInt($chat.data('last-message-id')) || 0;
            this.bindEvents();
            this.startPolling();
        },

        bindEvents: function() {
            // Take session
            $(document).on('click', '#aichat-take-session', this.takeSession.bind(this));
            
            // Send message
            $(document).on('submit', '#aichat-livechat-reply-form', this.sendMessage.bind(this));
            
            // Return to bot
            $(document).on('click', '#aichat-return-to-bot', this.returnToBot.bind(this));
            
            // Enter to send
            $(document).on('keydown', '#aichat-reply-message', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $('#aichat-livechat-reply-form').submit();
                }
            });
        },

        startPolling: function() {
            const self = this;
            
            // Poll for new sessions every N seconds
            this.pollInterval = setInterval(function() {
                self.pollSessions();
                
                if (self.currentSession) {
                    self.pollMessages();
                }
            }, aichatLiveChat.pollInterval || 3000);
        },

        pollSessions: function() {
            const self = this;
            
            $.ajax({
                url: aichatLiveChat.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aichat_livechat_get_sessions',
                    nonce: aichatLiveChat.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update pending count badge
                        const pendingCount = response.data.pending_count || 0;
                        $('#aichat-livechat-count').text(pendingCount);
                        
                        // Update admin bar badge if exists
                        $('.aichat-livechat-badge').text(pendingCount);
                        
                        // Update sessions list
                        self.updateSessionsList(response.data.sessions || []);
                    }
                }
            });
        },
        
        updateSessionsList: function(sessions) {
            const $list = $('.aichat-sessions-list');
            if (!$list.length) return;
            
            // Remember current scroll position
            const scrollTop = $list.scrollTop();
            
            // Group sessions by status
            const pending = sessions.filter(s => s.status === 'pending_agent');
            const active = sessions.filter(s => s.status === 'agent_connected');
            
            let html = '';
            
            // Pending sessions
            if (pending.length > 0) {
                html += '<div class="sessions-group"><h4><span class="dashicons dashicons-clock"></span> ' + aichatLiveChat.strings.pending + ' (' + pending.length + ')</h4>';
                pending.forEach(s => {
                    html += this.renderSessionItem(s);
                });
                html += '</div>';
            }
            
            // Active sessions
            if (active.length > 0) {
                html += '<div class="sessions-group"><h4><span class="dashicons dashicons-format-chat"></span> ' + aichatLiveChat.strings.active + ' (' + active.length + ')</h4>';
                active.forEach(s => {
                    html += this.renderSessionItem(s);
                });
                html += '</div>';
            }
            
            // No sessions
            if (sessions.length === 0) {
                html = '<div class="no-sessions"><span class="dashicons dashicons-testimonial"></span><p>' + aichatLiveChat.strings.no_sessions + '</p></div>';
            }
            
            $list.html(html);
            $list.scrollTop(scrollTop);
            
            // Highlight current session
            if (this.currentSession) {
                $list.find('[data-session="' + this.currentSession + '"]').addClass('active');
            }
            
            // Play sound for new pending
            if (pending.length > (this.lastPendingCount || 0) && aichatLiveChat.notificationSound) {
                this.playNotificationSound();
            }
            this.lastPendingCount = pending.length;
        },
        
        renderSessionItem: function(s) {
            const priorityClass = s.priority >= 3 ? 'priority-urgent' : (s.priority >= 2 ? 'priority-high' : '');
            const statusClass = s.status === 'pending_agent' ? 'status-pending' : 'status-active';
            
            return `
                <a href="?page=aichat-livechat&session=${s.session_id}" 
                   class="session-item ${statusClass} ${priorityClass}" 
                   data-session="${s.session_id}">
                    <div class="session-avatar">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="session-info">
                        <div class="session-name">${this.escapeHtml(s.visitor_name)}</div>
                        <div class="session-reason">${this.escapeHtml(s.handoff_reason || '')}</div>
                        <div class="session-meta">${s.time_ago} ago</div>
                    </div>
                    ${s.status === 'agent_connected' ? '<div class="session-agent"><span class="dashicons dashicons-businessman"></span> ' + this.escapeHtml(s.agent_name || '') + '</div>' : ''}
                </a>
            `;
        },

        pollMessages: function() {
            const self = this;
            
            console.log('[LiveChat Admin] pollMessages session=', this.currentSession, 'since_id=', this.lastMessageId);
            
            $.ajax({
                url: aichatLiveChat.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aichat_livechat_get_messages',
                    nonce: aichatLiveChat.nonce,
                    session_id: this.currentSession,
                    since_id: this.lastMessageId
                },
                success: function(response) {
                    console.log('[LiveChat Admin] pollMessages response=', response);
                    if (response.success && response.data.messages) {
                        console.log('[LiveChat Admin] pollMessages got', response.data.messages.length, 'messages');
                        response.data.messages.forEach(function(msg) {
                            console.log('[LiveChat Admin] appending msg id=', msg.id, 'type=', msg.sender_type, 'text=', msg.message.substring(0, 50));
                            self.appendMessage(msg);
                            self.lastMessageId = Math.max(self.lastMessageId, msg.id);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[LiveChat Admin] pollMessages ERROR=', status, error);
                }
            });
        },

        appendMessage: function(msg) {
            const html = `
                <div class="aichat-livechat-message message-${msg.sender_type}">
                    <div class="message-avatar">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="message-content">
                        <div class="message-header">
                            <span class="message-sender">${this.escapeHtml(msg.sender_type === 'visitor' ? 'Visitor' : msg.sender_name || 'Agent')}</span>
                            <span class="message-time">${msg.time || ''}</span>
                        </div>
                        <div class="message-text">${this.escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
                    </div>
                </div>
            `;
            
            $('#aichat-livechat-messages').append(html);
            this.scrollToBottom();
            
            // Play sound
            if (aichatLiveChat.notificationSound && msg.sender_type === 'visitor') {
                this.playNotificationSound();
            }
        },

        takeSession: function(e) {
            e.preventDefault();
            
            const sessionId = $(e.currentTarget).data('session');
            
            if (!confirm(aichatLiveChat.strings.confirm_take)) {
                return;
            }
            
            $.ajax({
                url: aichatLiveChat.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aichat_livechat_take_session',
                    nonce: aichatLiveChat.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show updated UI
                        location.reload();
                    } else {
                        alert(response.data || 'Failed to take session');
                    }
                }
            });
        },

        sendMessage: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $textarea = $('#aichat-reply-message');
            const $button = $form.find('button[type="submit"]');
            const message = $textarea.val().trim();
            const sessionId = $form.find('[name="session_id"]').val();
            
            console.log('[LiveChat Admin] sendMessage session=', sessionId, 'msg=', message.substring(0, 50));
            
            if (!message) return;
            
            $button.prop('disabled', true).text(aichatLiveChat.strings.sending);
            
            $.ajax({
                url: aichatLiveChat.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aichat_livechat_send_message',
                    nonce: aichatLiveChat.nonce,
                    session_id: sessionId,
                    message: message
                },
                success: (response) => {
                    console.log('[LiveChat Admin] sendMessage response=', response);
                    if (response.success) {
                        // Add message to UI
                        this.appendMessage({
                            sender_type: 'agent',
                            sender_name: response.data.sender_name || 'You',
                            message: message,
                            time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                        });
                        
                        // Update lastMessageId to prevent polling from fetching this message again
                        if (response.data.message_id) {
                            this.lastMessageId = Math.max(this.lastMessageId, response.data.message_id);
                        }
                        
                        $textarea.val('');
                    } else {
                        console.error('[LiveChat Admin] sendMessage FAILED=', response.data);
                        alert(response.data || 'Failed to send message');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[LiveChat Admin] sendMessage AJAX ERROR=', status, error);
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-arrow-right-alt"></span> Send');
                }
            });
        },

        returnToBot: function(e) {
            e.preventDefault();
            
            if (!confirm(aichatLiveChat.strings.confirm_return)) {
                return;
            }
            
            const sessionId = $(e.currentTarget).data('session');
            
            $.ajax({
                url: aichatLiveChat.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aichat_livechat_return_to_bot',
                    nonce: aichatLiveChat.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },

        scrollToBottom: function() {
            const $messages = $('#aichat-livechat-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        },

        playNotificationSound: function() {
            // Simple beep using Web Audio API
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                gainNode.gain.value = 0.1;
                
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.1);
            } catch (e) {
                // Audio not supported
            }
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Settings form handler
     */
    const SettingsForm = {
        init: function() {
            const $form = $('#aichat-livechat-settings-form');
            if ($form.length) {
                $form.on('submit', this.saveSettings.bind(this));
            }
        },

        saveSettings: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $button = $form.find('input[type="submit"], button[type="submit"]');
            const originalText = $button.val() || $button.text();
            
            $button.prop('disabled', true).val('Saving...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        // Show success notice
                        const $notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        $form.before($notice);
                        
                        // Auto-dismiss after 3 seconds
                        setTimeout(function() {
                            $notice.fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    } else {
                        alert(response.data.message || 'Error saving settings');
                    }
                },
                error: function() {
                    alert('Error saving settings. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).val(originalText);
                }
            });
        }
    };

    $(document).ready(function() {
        if ($('.aichat-livechat-wrap').length) {
            LiveChat.init();
        }
        
        // Init settings form handler
        SettingsForm.init();
    });

})(jQuery);
