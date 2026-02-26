/**
 * MxChat Test Panel JavaScript
 * Handles the testing interface for admins - always active when panel is open
 */

class MxChatTestPanel {
    constructor() {
        this.panel = null;
        this.tab = null;
        this.isOpen = false;
        this.lastQueryData = null;
        
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        this.createElements();
        this.bindEvents();
        this.setupChatInterception();
    }

    createElements() {
        // Create the test tab
        this.tab = document.createElement('div');
        this.tab.className = 'mxchat-test-tab';
        this.tab.innerHTML = 'MXCHAT DEBUGGING';
        this.tab.title = 'Open MxChat Debug Panel';
        document.body.appendChild(this.tab);

        // Create the test panel
        this.panel = document.createElement('div');
        this.panel.className = 'mxchat-test-panel';
        this.panel.innerHTML = this.getPanelHTML();
        document.body.appendChild(this.panel);
    }

    getPanelHTML() {
        return `
            <div class="mxchat-test-header">
                <h3>MxChat Debug Panel</h3>
                <p>Always-on debugging for administrators</p>
                <button class="mxchat-test-close" title="Close panel">&times;</button>
            </div>
            
            <div class="mxchat-test-content">
                <!-- Session Management -->
                <div class="mxchat-test-section">
                    <h4>Quick Actions</h4>
                    <button class="mxchat-test-btn danger" id="clear-chat-session">
                        Clear Chat Session
                    </button>
                </div>
    
                <!-- Query Analysis -->
                <div class="mxchat-test-section">
                    <h4>Last Query Analysis</h4>
                    <div class="mxchat-test-info">
                        <strong>Similarity Threshold:</strong>
                        <span id="similarity-threshold">Loading...</span>
                    </div>
                    <div class="mxchat-test-info">
                        <strong>User Query:</strong>
                        <div id="last-query" class="query-display">Waiting for next query...</div>
                    </div>
                    <div class="mxchat-test-info">
                        <strong>Approved URLs for Citations:</strong>
                        <div class="mxchat-test-results approved-urls-container" id="approved-urls">
                            <div class="no-data-message">No URL data yet</div>
                        </div>
                    </div>
                    <div class="mxchat-test-info">
                        <strong>Document Matches:</strong>
                        <div class="mxchat-test-results similarity-container" id="similarity-scores">
                            <div class="no-data-message">No query data yet</div>
                        </div>
                    </div>
                    <div class="mxchat-test-info">
                        <strong>Actions Triggered:</strong>
                        <div class="mxchat-test-results actions-container" id="action-scores">
                            <div class="no-data-message">No action data yet</div>
                        </div>
                    </div>
                </div>
    
                <!-- System Information -->
                <div class="mxchat-test-section">
                    <h4>System Information</h4>
                    <div class="mxchat-test-info">
                        <strong>System Prompt:</strong>
                        <div class="mxchat-test-results system-prompt-container" id="system-prompt">Loading...</div>
                    </div>
                    <div class="mxchat-test-info">
                        <strong>Knowledge Base:</strong>
                        <span id="kb-status">Checking...</span>
                    </div>
                </div>
    
                <!-- Debug Console -->
                <div class="mxchat-test-section">
                    <h4>Debug Log</h4>
                    <div class="mxchat-test-results debug-console-container" id="debug-console">
                        <div class="debug-entry">Debug panel ready - monitoring chat activity...</div>
                    </div>
                    <button class="mxchat-test-btn secondary" id="clear-debug">
                        Clear Log
                    </button>
                </div>
            </div>
        `;
    }


    bindEvents() {
        // Tab click to toggle panel
        this.tab.addEventListener('click', () => this.togglePanel());

        // Close button
        const closeBtn = this.panel.querySelector('.mxchat-test-close');
        closeBtn.addEventListener('click', () => this.closePanel());

        // Action buttons
        this.bindActionButtons();

        // Keep escape key to close (useful shortcut)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.closePanel();
            }
        });
    }

    bindActionButtons() {
        // Clear chat session
        this.panel.querySelector('#clear-chat-session').addEventListener('click', () => {
            this.clearChatSession();
        });

        // Clear debug console
        this.panel.querySelector('#clear-debug').addEventListener('click', () => {
            this.clearDebugConsole();
        });
    }

    togglePanel() {
        if (this.isOpen) {
            this.closePanel();
        } else {
            this.openPanel();
        }
    }

    openPanel() {
        this.panel.classList.add('open');
        this.isOpen = true;
        this.tab.style.display = 'none';
        this.loadSystemInfo();
        this.log('Debug panel opened - capturing chat data automatically');
    }

    closePanel() {
        this.panel.classList.remove('open');
        this.isOpen = false;
        this.tab.style.display = 'block';
    }

    setupChatInterception() {
        // Set up interception for chat responses to capture testing data
        this.interceptChatResponses();
        this.log('Chat monitoring initialized');
    }

    interceptChatResponses() {
        // Store reference to the test panel instance
        window.mxchatTestPanelInstance = this;
        
        // Intercept jQuery AJAX calls (for regular chat)
        if (window.jQuery) {
            const originalAjax = jQuery.ajax;
            
            jQuery.ajax = function(options) {
                const originalSuccess = options.success;
                
                options.success = function(data, textStatus, jqXHR) {
                    
                    // Check if this is a chat request
                    if (options.data && 
                        (options.data.action === 'mxchat_handle_chat_request' || 
                         options.data.action === 'mxchat_stream_chat')) {
                        
                        const testPanel = window.mxchatTestPanelInstance;
                        
                        // Always try to handle testing data if it exists (no toggle check)
                        if (testPanel && data && data.testing_data) {
                            testPanel.handleTestingData(data.testing_data);
                        } else if (testPanel && data && data.data && data.data.testing_data) {
                            // Check if data is nested
                            testPanel.handleTestingData(data.data.testing_data);
                        }
                    }
                    
                    // Call original success handler
                    if (originalSuccess) {
                        originalSuccess.call(this, data, textStatus, jqXHR);
                    }
                };
                
                return originalAjax.call(this, options);
            };
        }
        
        // Also intercept fetch API calls (for streaming)
        const originalFetch = window.fetch;
        
        window.fetch = (...args) => {
            return originalFetch(...args).then(response => {
                // Check if this is a chat request
                if (args[0].includes('admin-ajax.php') || args[0].includes('mxchat')) {
                    // For streaming responses that return JSON instead of streams
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        response.clone().json().then(data => {
                            const testPanel = window.mxchatTestPanelInstance;
                            
                            // Always try to handle testing data if it exists (no toggle check)
                            if (testPanel && data && data.testing_data) {
                                testPanel.handleTestingData(data.testing_data);
                            } else if (testPanel && data && data.data && data.data.testing_data) {
                                // Check nested data
                                testPanel.handleTestingData(data.data.testing_data);
                            }
                        }).catch(() => {
                            // Ignore JSON parsing errors
                        });
                    }
                }
                return response;
            });
        };
        
        this.log('Chat interception active for jQuery and fetch requests');
    }

handleTestingData(testingData) {
    this.log('📊 Chat data captured from response');
    
    // Update query analysis section
    this.updateLastQuery(testingData.query || 'No query', testingData.top_matches || []);
    
    // NEW: Update approved URLs
    this.updateApprovedUrls(testingData.approved_urls || []);
    
    this.updateTopMatches(testingData.top_matches || [], testingData.similarity_threshold || 0.75);
    
    // NEW: Update action matches
    this.updateActionMatches(testingData.action_matches || []);
    
    // Log additional info
    if (testingData.knowledge_base_type) {
        this.log(`📚 Knowledge Base: ${testingData.knowledge_base_type}`);
    }
    if (testingData.similarity_threshold) {
        this.log(`🎯 Similarity Threshold: ${(testingData.similarity_threshold * 100)}%`);
    }
    
    // NEW: Log approved URLs count
    if (testingData.approved_urls && testingData.approved_urls.length > 0) {
        this.log(`🔗 Approved URLs for citations: ${testingData.approved_urls.length}`);
    }
    
    // Show summary in debug console
    if (testingData.top_matches && testingData.top_matches.length > 0) {
        const aboveThreshold = testingData.top_matches.filter(match => match.above_threshold).length;
        const belowThreshold = testingData.top_matches.length - aboveThreshold;
        const highestScore = testingData.top_matches[0].similarity_percentage;
        
        this.log(`✅ Analysis: ${aboveThreshold} above threshold, ${belowThreshold} below threshold`);
        this.log(`🏆 Highest similarity: ${highestScore}%`);
    } else {
        this.log('⚠️ No document matches found');
    }
    
    // NEW: Log action summary
    if (testingData.action_matches && testingData.action_matches.length > 0) {
        const triggeredAction = testingData.action_matches.find(action => action.triggered);
        if (triggeredAction) {
            this.log(`🎯 Action Triggered: ${triggeredAction.intent_label} (${triggeredAction.similarity_percentage}%)`);
        } else {
            const highestAction = testingData.action_matches[0];
            this.log(`🚫 No actions triggered - Highest: ${highestAction.intent_label} (${highestAction.similarity_percentage}%)`);
        }
    } else {
        this.log('📝 No actions checked');
    }
}

updateApprovedUrls(approvedUrls) {
    const urlsEl = this.panel.querySelector('#approved-urls');
    
    // Safety check: ensure approvedUrls is an array
    if (!approvedUrls || !Array.isArray(approvedUrls) || approvedUrls.length === 0) {
        urlsEl.innerHTML = '<div class="no-data-message">No approved URLs (AI cannot cite links)</div>';
        return;
    }
    
    let html = `<div class="urls-header">
        <strong>${approvedUrls.length} URL${approvedUrls.length !== 1 ? 's' : ''} approved for AI citations</strong>
    </div>`;
    
    approvedUrls.forEach((url, index) => {
        // Extract domain for display
        let displayUrl = url;
        try {
            const urlObj = new URL(url);
            displayUrl = urlObj.hostname + urlObj.pathname;
        } catch (e) {
            // Keep original if URL parsing fails
        }
        
        html += `
            <div class="url-card">
                <div class="url-line">
                    <span class="url-icon">🔗</span>
                    <a href="${url}" target="_blank" rel="noopener noreferrer" class="url-link" title="${url}">
                        ${displayUrl}
                    </a>
                </div>
            </div>
        `;
    });
    
    html += `<div class="urls-note">
        ℹ️ The AI can only cite these URLs. Any other URLs will be automatically removed from responses.
    </div>`;
    
    urlsEl.innerHTML = html;
}

updateActionMatches(actionMatches) {
    const actionsEl = this.panel.querySelector('#action-scores');
    
    if (!actionMatches || actionMatches.length === 0) {
        actionsEl.innerHTML = '<div class="no-data-message">No actions checked</div>';
        return;
    }
    
    let html = `<div class="actions-header">
        <strong>Top ${actionMatches.length} actions checked</strong>
    </div>`;
    
    actionMatches.forEach((action, index) => {
        const isTriggered = action.triggered;
        const isAboveThreshold = action.above_threshold;
        const statusIcon = isTriggered ? '🎯' : (isAboveThreshold ? '⚠️' : '❌');
        
        // Determine the correct label based on status
        let statusLabel;
        if (isTriggered) {
            statusLabel = 'TRIGGERED';
        } else if (isAboveThreshold) {
            statusLabel = 'Above threshold';
        } else {
            statusLabel = 'Below threshold';
        }
        
        // Determine card styling
        const cardClass = isTriggered ? 'action-triggered' : (isAboveThreshold ? 'action-above-threshold' : 'action-below-threshold');
        
        html += `
            <div class="action-card ${cardClass}">
                <div class="action-line">
                    <span class="action-icon">${statusIcon}</span>
                    <span class="action-name">${action.intent_label}</span>
                    <span class="action-score">${action.similarity_percentage}%</span>
                </div>
                <div class="action-details">
                    <span class="action-status">${statusLabel}</span>
                    <span class="action-threshold">Threshold: ${action.threshold_percentage}%</span>
                </div>
            </div>
        `;
    });
    
    actionsEl.innerHTML = html;
}

    updateTopMatches(topMatches, threshold) {
        const scoresEl = this.panel.querySelector('#similarity-scores');

        if (!topMatches || topMatches.length === 0) {
            scoresEl.innerHTML = '<div class="no-data-message">No similarity data available</div>';
            return;
        }

        // Group matches by source URL
        const groupedByUrl = {};
        topMatches.forEach((match) => {
            const url = match.source_display || 'Unknown';
            if (!groupedByUrl[url]) {
                groupedByUrl[url] = {
                    url: url,
                    isUrl: url.startsWith('http'),
                    bestScore: 0,
                    usedForContext: false,
                    totalChunks: match.total_chunks || 1,
                    matchedChunks: [],
                    isChunked: match.is_chunk || false
                };
            }

            // Track best score
            if (match.similarity_percentage > groupedByUrl[url].bestScore) {
                groupedByUrl[url].bestScore = match.similarity_percentage;
            }

            // Track if any chunk was used for context
            if (match.used_for_context) {
                groupedByUrl[url].usedForContext = true;
            }

            // Add chunk info
            groupedByUrl[url].matchedChunks.push({
                chunkIndex: match.chunk_index,
                score: match.similarity_percentage,
                usedForContext: match.used_for_context,
                aboveThreshold: match.above_threshold
            });
        });

        // Convert to array and sort by best score
        const urlGroups = Object.values(groupedByUrl).sort((a, b) => b.bestScore - a.bestScore);

        // Count unique URLs used for context
        const usedUrlCount = urlGroups.filter(g => g.usedForContext).length;

        let html = `<div class="matches-header">
            <strong>${usedUrlCount} entr${usedUrlCount === 1 ? 'y' : 'ies'} used for AI context</strong>
            <span class="matches-subheader">(from ${topMatches.length} chunk matches)</span>
        </div>`;

        urlGroups.forEach((group, groupIndex) => {
            const cardClass = group.usedForContext ? 'above-threshold' : 'below-threshold';
            const statusIcon = group.usedForContext ? '✓' : '✗';
            const contextLabel = group.usedForContext ? 'Used for AI context' : 'Not used';

            // Build chunk summary
            let chunkSummary = '';
            if (group.isChunked && group.totalChunks > 1) {
                const usedChunkCount = group.matchedChunks.filter(c => c.usedForContext).length;
                chunkSummary = `<span class="chunk-summary">${usedChunkCount}/${group.totalChunks} chunks matched</span>`;
            }

            // Check if this entry has multiple matched chunks to show expand toggle
            const hasMultipleChunks = group.matchedChunks.length > 1;
            const expandToggle = hasMultipleChunks
                ? `<span class="chunk-expand-toggle" data-group="${groupIndex}">▶ Show chunks</span>`
                : '';

            html += `
                <div class="match-card ${cardClass}">
                    <div class="match-header">
                        <div class="match-title">
                            <span class="status-icon">${statusIcon}</span>
                            <span class="similarity-score">${group.bestScore}%</span>
                            ${chunkSummary}
                        </div>
                        <span class="context-label">${contextLabel}</span>
                    </div>
                    <div class="match-source">
                        ${group.isUrl ?
                            `<span class="source-icon link-icon">🔗</span> ${group.url}` :
                            `<span class="source-icon doc-icon">📄</span> ${group.url}`
                        }
                    </div>
                    ${expandToggle}
                    ${hasMultipleChunks ? this.renderChunkDetails(group.matchedChunks, groupIndex) : ''}
                </div>
            `;
        });

        scoresEl.innerHTML = html;

        // Add click handlers for expand toggles
        scoresEl.querySelectorAll('.chunk-expand-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                const groupId = e.target.dataset.group;
                const details = scoresEl.querySelector(`.chunk-details[data-group="${groupId}"]`);
                if (details) {
                    const isExpanded = details.classList.toggle('expanded');
                    e.target.textContent = isExpanded ? '▼ Hide chunks' : '▶ Show chunks';
                }
            });
        });
    }

    renderChunkDetails(chunks, groupIndex) {
        // Sort chunks by chunk index
        const sortedChunks = [...chunks].sort((a, b) => (a.chunkIndex || 0) - (b.chunkIndex || 0));

        let html = `<div class="chunk-details" data-group="${groupIndex}">`;

        sortedChunks.forEach(chunk => {
            const chunkNum = (chunk.chunkIndex !== null && chunk.chunkIndex !== undefined)
                ? chunk.chunkIndex + 1
                : '?';
            const statusClass = chunk.usedForContext ? 'chunk-used' : 'chunk-not-used';
            const statusIcon = chunk.usedForContext ? '✓' : '○';

            html += `
                <div class="chunk-detail-row ${statusClass}">
                    <span class="chunk-detail-icon">${statusIcon}</span>
                    <span class="chunk-detail-num">Chunk ${chunkNum}</span>
                    <span class="chunk-detail-score">${chunk.score}%</span>
                </div>
            `;
        });

        html += '</div>';
        return html;
    }

    updateLastQuery(query, topMatches) {
        const queryEl = this.panel.querySelector('#last-query');
        queryEl.textContent = query;
        
        this.lastQueryData = { 
            query, 
            topMatches, 
            timestamp: new Date() 
        };
    }

    clearChatSession() {
        // Get current session ID from cookie (most reliable)
        const sessionId = this.getCookie('mxchat_session_id') || this.getCurrentSessionId();
        
        if (!sessionId) {
            this.log('❌ No active session found');
            return;
        }
        
        this.log(`🔍 Current session ID: ${sessionId}`);
        this.log('🧹 Starting fresh session...');
        
        // Generate new session ID (using your existing format)
        const newSessionId = 'mxchat_chat_' + Math.random().toString(36).substr(2, 9);
        
        // Clear the old cookie and set new one immediately
        this.clearMxChatCookie();
        this.setChatSession(newSessionId);
        
        this.log(`🆕 New session ID: ${newSessionId}`);
        
        // Call backend to clear old session data
        fetch(mxchatTestData.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'mxchat_start_fresh_session',
                nonce: mxchatTestData.nonce,
                old_session_id: sessionId,
                new_session_id: newSessionId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.log('✅ Backend session cleared: ' + data.data.message);
                
                // Update the session ID everywhere in the DOM
                this.updateSessionIdEverywhere(newSessionId);
                
                // Clear the chat UI
                this.clearChatUI();
                
                // Show popular questions again
                const popularQuestions = document.querySelector('#mxchat-popular-questions');
                if (popularQuestions) {
                    popularQuestions.style.display = 'block';
                }
                
                // Clear testing data displays
                this.updateLastQuery('New session started', []);
                this.updateTopMatches([], 0);
                this.updateApprovedUrls([]);
                
                this.log('🎉 Fresh session started successfully');
                
            } else {
                this.log('❌ Error clearing session: ' + (data.data?.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error clearing chat session:', error);
            this.log('🔌 Connection error when clearing session');
        });
    }

    // Helper function to get cookie (same as your existing one)
    getCookie(name) {
        let value = "; " + document.cookie;
        let parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
    }

    // Helper function to set session cookie (same as your existing one)
    setChatSession(sessionId) {
        // Set the cookie with a 24-hour expiration (86400 seconds)
        document.cookie = "mxchat_session_id=" + sessionId + "; path=/; max-age=86400; SameSite=Lax";
    }

    // Helper function to clear the MxChat session cookie
    clearMxChatCookie() {
        // Clear the cookie by setting it to expire in the past
        document.cookie = "mxchat_session_id=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax";
        this.log('🍪 Session cookie cleared');
    }

    updateSessionIdEverywhere(newSessionId) {
        // Update global session ID variable if it exists
        if (window.mxchatSessionId) {
            window.mxchatSessionId = newSessionId;
        }
        
        // Update session ID in chat input data attribute
        const chatInput = document.querySelector('#chat-input');
        if (chatInput) {
            chatInput.dataset.sessionId = newSessionId;
        }
        
        // Update any hidden session ID fields
        const sessionInputs = document.querySelectorAll('input[name="session_id"]');
        sessionInputs.forEach(input => {
            input.value = newSessionId;
        });
        
        // Update any data attributes that store session ID
        const elementsWithSessionId = document.querySelectorAll('[data-session-id]');
        elementsWithSessionId.forEach(element => {
            element.dataset.sessionId = newSessionId;
        });
        
        // Update URL parameter if it exists
        if (window.location.search.includes('session_id=')) {
            const url = new URL(window.location);
            url.searchParams.set('session_id', newSessionId);
            window.history.replaceState({}, '', url);
        }
        
        this.log('🔄 Session ID updated everywhere in DOM');
    }

    clearChatUI() {
        const chatBox = document.querySelector('#chat-box');
        if (chatBox) {
            // Remove all messages (both user and bot)
            const allMessages = chatBox.querySelectorAll('.bot-message, .user-message');
            allMessages.forEach(msg => {
                // Keep the first bot message if it's a welcome message
                if (msg === chatBox.querySelector('.bot-message') && 
                    msg.textContent.toLowerCase().includes('welcome')) {
                    return; // Keep welcome message
                }
                msg.remove();
            });
            this.log('🧹 Chat UI cleared');
        }
        
        // Clear chat input
        const chatInput = document.querySelector('#chat-input');
        if (chatInput) {
            chatInput.value = '';
        }
    }

    getCurrentSessionId() {
        // First try to get from cookie (most reliable)
        const cookieSessionId = this.getCookie('mxchat_session_id');
        if (cookieSessionId) {
            return cookieSessionId;
        }
        
        // Try to get session ID from various DOM sources
        const chatInput = document.querySelector('#chat-input');
        if (chatInput && chatInput.dataset.sessionId) {
            return chatInput.dataset.sessionId;
        }
        
        // Try to get from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const sessionFromUrl = urlParams.get('session_id');
        if (sessionFromUrl) {
            return sessionFromUrl;
        }
        
        // Try to get from global variables
        if (window.chatSessionId) {
            return window.chatSessionId;
        }
        
        // Generate a temporary session ID if none found
        return 'mxchat_chat_' + Math.random().toString(36).substr(2, 9);
    }
    
    loadSystemInfo() {
        // Load system information from backend
        this.updateSimilarityThreshold();
        this.updateSystemPrompt();
        this.updateKnowledgeBaseStatus();
    }

    updateSimilarityThreshold() {
        fetch(mxchatTestData.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'mxchat_get_similarity_threshold',
                nonce: mxchatTestData.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            const thresholdEl = this.panel.querySelector('#similarity-threshold');
            if (data.success) {
                thresholdEl.innerHTML = `<code>${data.data.threshold_percentage}</code>`;
            } else {
                thresholdEl.innerHTML = '<span class="error-text">Error loading threshold</span>';
            }
        })
        .catch(error => {
            console.error('Error fetching similarity threshold:', error);
            const thresholdEl = this.panel.querySelector('#similarity-threshold');
            thresholdEl.innerHTML = '<span class="error-text">Connection error</span>';
        });
    }

    updateSystemPrompt() {
    const promptEl = this.panel.querySelector('#system-prompt');
    promptEl.textContent = 'Loading system prompt...';
    
    fetch(mxchatTestData.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'mxchat_get_system_info',
            nonce: mxchatTestData.nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            promptEl.textContent = data.data.system_prompt || 'No system prompt configured';
            
            // Enhanced model display with OpenRouter support
            if (data.data.is_openrouter) {
                this.log(`🤖 Model: OpenRouter`);
                this.log(`   └─ Using: ${data.data.openrouter_model}`);
            } else {
                this.log(`🤖 Model: ${data.data.selected_model}`);
            }
            
            // Enhanced API status with OpenRouter
            const apiStatus = data.data.api_status;
            const configuredApis = Object.keys(apiStatus).filter(key => apiStatus[key]);
            
            if (configuredApis.length > 0) {
                this.log(`🔑 Configured APIs: ${configuredApis.map(api => {
                    // Capitalize and format API names
                    if (api === 'openai') return 'OpenAI';
                    if (api === 'xai') return 'X.AI';
                    if (api === 'openrouter') return 'OpenRouter';
                    return api.charAt(0).toUpperCase() + api.slice(1);
                }).join(', ')}`);
            } else {
                this.log(`⚠️ No API keys configured`);
            }
            
            // Specific warning for OpenRouter if selected but no key
            if (data.data.is_openrouter && !apiStatus.openrouter) {
                this.log(`❌ WARNING: OpenRouter selected but no API key configured!`);
            }
        } else {
            promptEl.textContent = 'Error loading system prompt';
        }
    })
    .catch(error => {
        console.error('Error fetching system info:', error);
        promptEl.textContent = 'Connection error';
    });
}

    updateKnowledgeBaseStatus() {
        const statusEl = this.panel.querySelector('#kb-status');
        statusEl.innerHTML = 'Checking...';
        
        fetch(mxchatTestData.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'mxchat_get_kb_status',
                nonce: mxchatTestData.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const kbData = data.data;
                statusEl.innerHTML = `<span class="success-text">✓ ${kbData.status}</span> (${kbData.type} - ${kbData.documents})`;
            } else {
                statusEl.innerHTML = '<span class="error-text">Error loading KB status</span>';
            }
        })
        .catch(error => {
            console.error('Error fetching KB status:', error);
            statusEl.innerHTML = '<span class="error-text">Connection error</span>';
        });
    }

    clearDebugConsole() {
        const console = this.panel.querySelector('#debug-console');
        console.innerHTML = '<div class="debug-entry">Debug console cleared...</div>';
    }

    log(message) {
        const console = this.panel.querySelector('#debug-console');
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
        logEntry.className = 'debug-entry';
        logEntry.innerHTML = `<span class="debug-timestamp">[${timestamp}]</span> ${message}`;
        console.appendChild(logEntry);
        console.scrollTop = console.scrollHeight;
        
        // Keep only last 50 entries to prevent memory issues
        const entries = console.querySelectorAll('.debug-entry');
        if (entries.length > 50) {
            entries[0].remove();
        }
    }
}

// Initialize the test panel when the script loads
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if user is admin and testing is enabled
    if (window.mxchatTestingEnabled) {
        window.mxchatTestPanel = new MxChatTestPanel();
    }
});

// Global function to enable testing mode programmatically
window.enableMxChatTesting = function() {
    if (!window.mxchatTestPanel) {
        window.mxchatTestPanel = new MxChatTestPanel();
    }
};