/**
 * Easy Config Wizard - Multi-step setup wizard
 * 
 * Step 1: Welcome - Chatbot type, voice tone, response length, guidelines
 * Step 2: Context - Web scan, manual info, file upload
 * Step 3: Provider - AI provider selection and API key
 * Step 4: Indexing - Progress indicator
 * Step 5: Finish - Congratulations
 */
const { __, sprintf } = wp.i18n;

(function($) {
    'use strict';

    const ROOT_SEL = '#aichat-easy-config-root';

    // =========================================================================
    // State Management
    // =========================================================================
    const initialState = {
        step: 0,
        // Step 1 data
        chatbotType: 'customer_service',
        voiceTone: 'friendly',
        responseLength: 'short',
        guidelines: [],
        // Bot mode
        botMode: 'new', // Will be set based on whether bots exist
        selectedBotId: null,
        hasBots: false, // Will be set from data attribute
        // Bot limits
        maxBots: 2,
        botCount: 0,
        canCreate: true,
        upgradeUrl: '',
        // Step 2 data
        discover: null,
        selected: {},
        wooSelected: {},   // WooCommerce product selections (separate from pages/posts)
        manualText: '',
        uploadedFiles: [],
        useAiVision: true, // AI Vision fallback for PDFs
        // Step 3 data
        provider: 'openai',
        providerStatus: {},
        agencyEnabled: false,
        agencyConfigured: false,
        // Step 4 data
        contextId: null,
        indexedCount: 0,
        indexTotal: 0,
        indexDone: false,
        // Step 5 data
        botId: null,
        botSlug: null
    };

    let state = { ...initialState };
    let data = {
        chatbotTypes: {},
        voiceTones: {},
        responseLengths: {},
        providers: {},
        existingBots: []
    };

    // =========================================================================
    // Initialization
    // =========================================================================
    function init() {
        const $root = $(ROOT_SEL);
        if (!$root.length) return;

        // Load data from data attributes
        try {
            data.chatbotTypes = JSON.parse($root.attr('data-chatbot-types') || '{}');
            data.voiceTones = JSON.parse($root.attr('data-voice-tones') || '{}');
            data.responseLengths = JSON.parse($root.attr('data-response-lengths') || '{}');
            data.providers = JSON.parse($root.attr('data-providers') || '{}');
            state.providerStatus = JSON.parse($root.attr('data-provider-status') || '{}');
            state.agencyEnabled = $root.attr('data-agency-enabled') === '1';
            state.agencyConfigured = $root.attr('data-agency-configured') === '1';
            data.existingBots = JSON.parse($root.attr('data-existing-bots') || '[]');
            state.hasBots = $root.attr('data-has-bots') === '1';
            // Load bot limits
            const maxBotsAttr = $root.attr('data-max-bots');
            state.maxBots = maxBotsAttr === 'unlimited' ? Infinity : parseInt(maxBotsAttr, 10) || 2;
            state.botCount = parseInt($root.attr('data-bot-count'), 10) || 0;
            state.canCreate = $root.attr('data-can-create') === '1';
            state.upgradeUrl = $root.attr('data-upgrade-url') || 'https://axiachat.com/pricing/';
            state.reusableContextId = parseInt($root.attr('data-reusable-context-id'), 10) || 0;
        } catch (e) {
            console.error('[EasyConfig] Error parsing data attributes:', e);
        }

        // If bots exist, default to updating the first one; otherwise create new
        if (state.hasBots && data.existingBots.length > 0) {
            state.botMode = 'overwrite';
            state.selectedBotId = data.existingBots[0].id;
        } else {
            state.botMode = 'new';
            state.selectedBotId = null;
        }

        // Set default guidelines from selected chatbot type
        if (data.chatbotTypes[state.chatbotType]) {
            state.guidelines = [...(data.chatbotTypes[state.chatbotType].guidelines || [])];
        }

        render();
    }

    // =========================================================================
    // Rendering
    // =========================================================================
    function render() {
        const $root = $(ROOT_SEL);
        if (!$root.length) return;

        console.log('[EasyConfig] render() called for step:', state.step);

        let html = '<div class="aichat-ec-wizard">';
        html += renderStepIndicators();
        html += '<div class="aichat-ec-panel">';
        html += renderCurrentStep();
        html += '</div>';
        html += '</div>';

        $root.html(html);
        bindEvents();
    }

    function renderStepIndicators() {
        const steps = [
            { icon: '🎯', label: __('Configure', 'axiachat-ai') },
            { icon: '📚', label: __('Training', 'axiachat-ai') },
            { icon: '🔑', label: __('Provider', 'axiachat-ai') },
            { icon: '⚙️', label: __('Indexing', 'axiachat-ai') },
            { icon: '🎉', label: __('Finish', 'axiachat-ai') }
        ];

        let html = '<div class="aichat-ec-steps">';
        steps.forEach((s, i) => {
            const cls = i < state.step ? 'done' : (i === state.step ? 'active' : '');
            html += `<div class="aichat-ec-step ${cls}">
                <div class="aichat-ec-step-icon">${s.icon}</div>
                <div class="aichat-ec-step-label">${s.label}</div>
                ${i < steps.length - 1 ? '<div class="aichat-ec-step-line"></div>' : ''}
            </div>`;
        });
        html += '</div>';
        return html;
    }

    function renderCurrentStep() {
        switch (state.step) {
            case 0: return renderStep1();
            case 1: return renderStep2();
            case 2: return renderStep3();
            case 3: return renderStep4();
            case 4: return renderStep5();
            default: return '';
        }
    }

    // =========================================================================
    // Step 1: Welcome & Configuration
    // =========================================================================
    function renderStep1() {
        const hasBots = data.existingBots && data.existingBots.length > 0;
        // When free plan with exactly 1 bot and max 1, skip the entire bot-mode section:
        // auto-set overwrite mode silently (no upgrade warning, no cards, no selector).
        const singleBotFree = hasBots && data.existingBots.length === 1 && state.maxBots === 1;
        const showBotModeSelector = hasBots && !singleBotFree;
        // Check if user can create new bots (free plan limit)
        const canCreateNew = state.canCreate;

        // Auto-configure overwrite for single-bot free plan
        if (singleBotFree) {
            state.botMode = 'overwrite';
            state.selectedBotId = data.existingBots[0].id;
        }

        let html = `
            <div class="aichat-ec-step-content">
                <div class="aichat-ec-header-section">
                    <h2>👋 ${__('Welcome! Let\'s configure your AI Chatbot', 'axiachat-ai')}</h2>
                    <p class="aichat-ec-desc">${__('Choose the type of assistant you want to create and customize its behavior.', 'axiachat-ai')}</p>
                </div>`;

        // Bot mode selector (only if existing bots)
        if (showBotModeSelector) {
            // If can't create new, force overwrite mode
            if (!canCreateNew && state.botMode === 'new') {
                state.botMode = 'overwrite';
                if (data.existingBots.length > 0 && !state.selectedBotId) {
                    state.selectedBotId = data.existingBots[0].id;
                }
            }

            const limitWarning = !canCreateNew ? `
                <div class="aichat-ec-limit-warning" style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:10px 15px;margin-bottom:15px;">
                    <p style="margin:0 0 8px 0;color:#856404;">
                        <strong>⚠️ ${sprintf(__('You have reached the limit of %d chatbot(s) on your current plan.', 'axiachat-ai'), state.maxBots)}</strong>
                    </p>
                    <a href="${esc(state.upgradeUrl)}" target="_blank" class="button button-primary" style="font-size:12px;">
                        🚀 ${__('Upgrade for more chatbots', 'axiachat-ai')}
                    </a>
                </div>` : '';

            html += `
                <div class="aichat-ec-section aichat-ec-bot-mode">
                    <div class="aichat-ec-section-header">
                        <span class="aichat-ec-section-icon">📝</span>
                        <h3>${__('Bot Configuration', 'axiachat-ai')}</h3>
                    </div>
                    ${limitWarning}
                    <div class="aichat-ec-mode-cards">
                        <div class="aichat-ec-mode-card ${state.botMode === 'new' ? 'selected' : ''} ${!canCreateNew ? 'disabled' : ''}" data-mode="new" ${!canCreateNew ? 'title="' + __('Upgrade to create more bots', 'axiachat-ai') + '"' : ''}>
                            <div class="aichat-ec-mode-icon">✨</div>
                            <div class="aichat-ec-mode-title">${__('Create New Bot', 'axiachat-ai')}</div>
                            <div class="aichat-ec-mode-desc">${!canCreateNew ? __('Limit reached', 'axiachat-ai') : __('Start fresh with a new chatbot', 'axiachat-ai')}</div>
                        </div>
                        <div class="aichat-ec-mode-card ${state.botMode === 'overwrite' ? 'selected' : ''}" data-mode="overwrite">
                            <div class="aichat-ec-mode-icon">🔄</div>
                            <div class="aichat-ec-mode-title">${__('Update Existing', 'axiachat-ai')}</div>
                            <div class="aichat-ec-mode-desc">${__('Modify an existing chatbot', 'axiachat-ai')}</div>
                        </div>
                    </div>`;

            if (state.botMode === 'overwrite') {
                html += `
                    <div class="aichat-ec-bot-select">
                        <label>${__('Select bot to update:', 'axiachat-ai')}</label>
                        <select id="ec-bot-select" class="aichat-ec-select">
                            ${data.existingBots.map(b => 
                                `<option value="${b.id}" ${state.selectedBotId == b.id ? 'selected' : ''}>${esc(b.name)} (${esc(b.slug)})</option>`
                            ).join('')}
                        </select>
                    </div>`;
            }
            html += '</div>';
        }

        // Chatbot Type
        html += `
            <div class="aichat-ec-section">
                <div class="aichat-ec-section-header">
                    <span class="aichat-ec-section-icon">🤖</span>
                    <h3>${__('Chatbot Type', 'axiachat-ai')}</h3>
                    <span class="aichat-ec-help-text">${__('Select the personality and purpose of your assistant', 'axiachat-ai')}</span>
                </div>
                <div class="aichat-ec-type-grid">`;

        for (const [key, type] of Object.entries(data.chatbotTypes)) {
            html += `
                <div class="aichat-ec-type-card ${state.chatbotType === key ? 'selected' : ''}" data-type="${key}">
                    <div class="aichat-ec-type-icon">${type.icon || '🤖'}</div>
                    <div class="aichat-ec-type-name">${esc(type.name)}</div>
                    <div class="aichat-ec-type-desc">${esc(type.description)}</div>
                </div>`;
        }

        html += `</div>
            </div>`;

        // Voice Tone
        html += `
            <div class="aichat-ec-section">
                <div class="aichat-ec-section-header">
                    <span class="aichat-ec-section-icon">🎭</span>
                    <h3>${__('Voice Tone', 'axiachat-ai')}</h3>
                    <span class="aichat-ec-help-text">${__('How should your chatbot communicate?', 'axiachat-ai')}</span>
                </div>
                <div class="aichat-ec-tone-options">`;

        for (const [key, tone] of Object.entries(data.voiceTones)) {
            html += `
                <label class="aichat-ec-tone-option ${state.voiceTone === key ? 'selected' : ''}">
                    <input type="radio" name="voice_tone" value="${key}" ${state.voiceTone === key ? 'checked' : ''}>
                    <span class="aichat-ec-tone-icon">${tone.icon || '💬'}</span>
                    <span class="aichat-ec-tone-name">${esc(tone.name)}</span>
                </label>`;
        }

        html += `</div>
            </div>`;

        // Response Length
        html += `
            <div class="aichat-ec-section">
                <div class="aichat-ec-section-header">
                    <span class="aichat-ec-section-icon">📏</span>
                    <h3>${__('Response Length', 'axiachat-ai')}</h3>
                    <span class="aichat-ec-help-text">${__('Control how detailed your chatbot\'s answers should be', 'axiachat-ai')}</span>
                </div>
                <div class="aichat-ec-length-slider">
                    <input type="range" id="ec-length-range" min="1" max="4" value="${getLengthValue(state.responseLength)}" class="aichat-ec-range">
                    <div class="aichat-ec-length-labels">`;

        for (const [key, len] of Object.entries(data.responseLengths)) {
            html += `<span class="aichat-ec-length-label ${state.responseLength === key ? 'active' : ''}" data-key="${key}">${len.name}</span>`;
        }

        html += `</div>
                    <div class="aichat-ec-length-desc" id="ec-length-desc">${getLengthDescription(state.responseLength)}</div>
                </div>
            </div>`;

        // Guidelines
        html += `
            <div class="aichat-ec-section">
                <div class="aichat-ec-section-header">
                    <span class="aichat-ec-section-icon">📋</span>
                    <h3>${__('Chat Guidelines', 'axiachat-ai')}</h3>
                    <span class="aichat-ec-help-text">${__('Define specific rules and behaviors for your chatbot', 'axiachat-ai')}</span>
                </div>
                <div class="aichat-ec-guidelines-list" id="ec-guidelines-list">`;

        state.guidelines.forEach((g, i) => {
            html += `
                <div class="aichat-ec-guideline-item" data-index="${i}">
                    <span class="aichat-ec-guideline-bullet">•</span>
                    <input type="text" class="aichat-ec-guideline-input" value="${esc(g)}" data-index="${i}">
                    <button type="button" class="aichat-ec-guideline-delete" data-index="${i}" title="${__('Delete', 'axiachat-ai')}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;
        });

        html += `</div>
                <button type="button" class="button aichat-ec-add-guideline" id="ec-add-guideline">
                    <i class="bi bi-plus-circle"></i> ${__('Add Guideline', 'axiachat-ai')}
                </button>
            </div>`;

        // Navigation
        html += `
            <div class="aichat-ec-nav">
                <div></div>
                <button type="button" class="button button-primary button-hero" data-action="next">
                    ${__('Continue', 'axiachat-ai')} <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>`;

        return html;
    }

    function getLengthValue(key) {
        const lengths = Object.keys(data.responseLengths);
        const idx = lengths.indexOf(key);
        return idx >= 0 ? idx + 1 : 2;
    }

    function getLengthKeyByValue(val) {
        const lengths = Object.keys(data.responseLengths);
        return lengths[val - 1] || 'short';
    }

    function getLengthDescription(key) {
        if (data.responseLengths[key]) {
            return data.responseLengths[key].description || '';
        }
        return '';
    }

    // =========================================================================
    // Step 2: Context / Training
    // =========================================================================
    function renderStep2() {
        let html = `
            <div class="aichat-ec-step-content">
                <div class="aichat-ec-header-section">
                    <h2>📚 ${__('Train Your Chatbot', 'axiachat-ai')}</h2>
                    <p class="aichat-ec-desc">${__('Add knowledge sources so your chatbot can provide accurate, relevant answers.', 'axiachat-ai')}</p>
                </div>`;

        // Web Scan Section
        html += `
            <div class="aichat-ec-section">
                <div class="aichat-ec-section-header">
                    <span class="aichat-ec-section-icon">🌐</span>
                    <h3>${__('Website Content', 'axiachat-ai')}</h3>
                    <span class="aichat-ec-help-text">${__('Automatically scan your website pages and posts', 'axiachat-ai')}</span>
                </div>
                <div class="aichat-ec-webscan">`;

        if (!state.discover) {
            html += `
                    <div class="aichat-ec-webscan-loading">
                        <div class="aichat-ec-spinner"></div>
                        <p>${__('Scanning your website for content...', 'axiachat-ai')}</p>
                    </div>`;
        } else {
            const items = state.discover.items || [];
            const selectedCount = countSelected();

            html += `
                    <div class="aichat-ec-webscan-results">
                        <div class="aichat-ec-webscan-summary">
                            <span class="aichat-ec-webscan-count">
                                <strong>${state.discover.total}</strong> ${__('items found', 'axiachat-ai')}
                            </span>
                            <span class="aichat-ec-webscan-selected">
                                <strong>${selectedCount}</strong> ${__('selected', 'axiachat-ai')}
                            </span>
                            <button type="button" class="button button-small" data-action="toggle-all">
                                ${allSelected() ? __('Unselect All', 'axiachat-ai') : __('Select All', 'axiachat-ai')}
                            </button>
                        </div>
                        <div class="aichat-ec-webscan-list">`;

            items.forEach(item => {
                const checked = state.selected[item.id] ? 'checked' : '';
                html += `
                            <label class="aichat-ec-webscan-item">
                                <input type="checkbox" data-item-id="${item.id}" ${checked}>
                                <span class="aichat-ec-item-title">${esc(item.title || __('(No title)', 'axiachat-ai'))}</span>
                                <span class="aichat-ec-item-type">${esc(item.type)}</span>
                            </label>`;
            });

            html += `</div>
                    </div>`;
        }

        html += `</div>
            </div>`;

        // =====================================================================
        // WooCommerce Products Section (only if WooCommerce items present)
        // =====================================================================
        const wooItems = (state.discover && state.discover.woo_items) ? state.discover.woo_items : [];
        if (state.discover && state.discover.has_woo && wooItems.length > 0) {
            const wooSelectedCount = countWooSelected();

            html += `
            <div class="aichat-ec-section">
                <div class="aichat-ec-accordion aichat-ec-accordion--woo">
                    <button type="button" class="aichat-ec-accordion-header aichat-ec-accordion-header--woo" data-accordion="woo">
                        <span class="aichat-ec-section-icon">🛒</span>
                        <span class="aichat-ec-accordion-title">${__('WooCommerce Products', 'axiachat-ai')}</span>
                        <span class="aichat-ec-accordion-desc">
                            <strong>${wooSelectedCount}</strong> / ${wooItems.length} ${__('selected', 'axiachat-ai')}
                        </span>
                        <i class="bi bi-chevron-down aichat-ec-accordion-arrow open"></i>
                    </button>
                    <div class="aichat-ec-accordion-content" id="accordion-woo" style="display:block;">
                        <div class="aichat-ec-webscan">
                            <div class="aichat-ec-webscan-results">
                                <div class="aichat-ec-webscan-summary">
                                    <span class="aichat-ec-webscan-count">
                                        <strong>${wooItems.length}</strong> ${__('products found', 'axiachat-ai')}
                                    </span>
                                    <span class="aichat-ec-webscan-selected aichat-ec-woo-selected-count">
                                        <strong>${wooSelectedCount}</strong> ${__('selected', 'axiachat-ai')}
                                    </span>
                                    <button type="button" class="button button-small" data-action="toggle-all-woo">
                                        ${allWooSelected() ? __('Unselect All', 'axiachat-ai') : __('Select All', 'axiachat-ai')}
                                    </button>
                                </div>
                                <div class="aichat-ec-webscan-list">`;

            wooItems.forEach(item => {
                const checked = state.wooSelected[item.id] ? 'checked' : '';
                html += `
                                    <label class="aichat-ec-webscan-item">
                                        <input type="checkbox" data-woo-id="${item.id}" ${checked}>
                                        <span class="aichat-ec-item-title">${esc(item.title || __('(No title)', 'axiachat-ai'))}</span>
                                        <span class="aichat-ec-item-type aichat-ec-item-type--product">${esc(item.type)}</span>
                                    </label>`;
            });

            html += `</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        }

        // Manual Information Accordion
        html += `
            <div class="aichat-ec-section">
                <div class="aichat-ec-accordion">
                    <button type="button" class="aichat-ec-accordion-header" data-accordion="info">
                        <span class="aichat-ec-section-icon">📝</span>
                        <span class="aichat-ec-accordion-title">${__('Additional Information', 'axiachat-ai')}</span>
                        <span class="aichat-ec-accordion-desc">${__('Add text content directly', 'axiachat-ai')}</span>
                        <i class="bi bi-chevron-down aichat-ec-accordion-arrow"></i>
                    </button>
                    <div class="aichat-ec-accordion-content" id="accordion-info">
                        <p class="aichat-ec-field-help">${__('Enter information your AI can use as knowledge base: company details, product features, FAQs, service guidelines...', 'axiachat-ai')}</p>
                        <textarea id="ec-manual-text" class="aichat-ec-textarea" rows="6" placeholder="${__('Company summary, product features, frequently asked questions, service guidelines...', 'axiachat-ai')}">${esc(state.manualText)}</textarea>
                    </div>
                </div>
            </div>`;

        // File Upload Accordion
        html += `
            <div class="aichat-ec-section">
                <div class="aichat-ec-accordion">
                    <button type="button" class="aichat-ec-accordion-header" data-accordion="files">
                        <span class="aichat-ec-section-icon">📁</span>
                        <span class="aichat-ec-accordion-title">${__('Upload Files', 'axiachat-ai')}</span>
                        <span class="aichat-ec-accordion-desc">${__('Upload TXT or PDF documents', 'axiachat-ai')}</span>
                        <i class="bi bi-chevron-down aichat-ec-accordion-arrow"></i>
                    </button>
                    <div class="aichat-ec-accordion-content" id="accordion-files">
                        <div class="aichat-ec-upload-zone" id="ec-upload-zone">
                            <i class="bi bi-cloud-upload aichat-ec-upload-icon"></i>
                            <p>${__('Drag & drop files here or', 'axiachat-ai')} <label class="aichat-ec-upload-link"><input type="file" id="ec-file-input" accept=".txt,.pdf" multiple hidden>${__('click to browse', 'axiachat-ai')}</label></p>
                            <p class="aichat-ec-upload-hint">${__('Supported formats: TXT, PDF', 'axiachat-ai')}</p>
                        </div>
                        <label class="aichat-ec-ai-vision-option">
                            <input type="checkbox" id="ec-use-ai-vision" ${state.useAiVision ? 'checked' : ''}>
                            <span>${__('Use AI Vision for complex PDFs', 'axiachat-ai')}</span>
                            <span class="aichat-ec-tooltip" title="${__('When standard text extraction fails (scanned PDFs, complex layouts), use AI vision to analyze pages. Cost: ~$0.01-0.03 per page. Only used as fallback.', 'axiachat-ai')}"><i class="bi bi-info-circle"></i></span>
                        </label>
                        <div class="aichat-ec-uploaded-files" id="ec-uploaded-files">`;

        state.uploadedFiles.forEach((f, i) => {
            html += `
                            <div class="aichat-ec-uploaded-file" data-index="${i}">
                                <i class="bi bi-file-earmark-text"></i>
                                <span class="aichat-ec-file-name">${esc(f.name)}</span>
                                <span class="aichat-ec-file-status ${f.indexed ? 'success' : 'pending'}">${f.indexed ? __('✓ Indexed', 'axiachat-ai') : __('Pending', 'axiachat-ai')}</span>
                                <button type="button" class="aichat-ec-file-remove" data-index="${i}"><i class="bi bi-x"></i></button>
                            </div>`;
        });

        html += `</div>
                    </div>
                </div>
            </div>`;

        // Navigation
        html += `
            <div class="aichat-ec-nav">
                <button type="button" class="button" data-action="back">
                    <i class="bi bi-arrow-left"></i> ${__('Back', 'axiachat-ai')}
                </button>
                <button type="button" class="button button-primary button-hero" data-action="next" ${countSelected() === 0 && countWooSelected() === 0 && !state.manualText && state.uploadedFiles.length === 0 ? 'disabled' : ''}>
                    ${__('Continue', 'axiachat-ai')} <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>`;

        return html;
    }

    // =========================================================================
    // Step 3: Provider Selection
    // =========================================================================
    function renderStep3() {
        const agencyActive = state.agencyEnabled || state.agencyConfigured;
        let html = `
            <div class="aichat-ec-step-content">
                <div class="aichat-ec-header-section">
                    <h2>🔑 ${__('AI Provider Configuration', 'axiachat-ai')}</h2>
                    <p class="aichat-ec-desc">${agencyActive ? __('Agency proxy is active. API keys are managed centrally.', 'axiachat-ai') : __('Select your AI provider and enter your API key to power your chatbot.', 'axiachat-ai')}</p>
                </div>

                ${agencyActive ? `
                <div class="aichat-ec-section">
                    <div class="aichat-ec-api-configured">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <strong>${__('PROXY DE AGENCIA activo', 'axiachat-ai')}</strong>
                            <p>${__('Your requests will be routed through the agency proxy. No API key is required for this step.', 'axiachat-ai')}</p>
                        </div>
                    </div>
                </div>` : ''}

                `;

        if (!agencyActive) {
            html += `

                <div class="aichat-ec-section">
                    <div class="aichat-ec-section-header">
                        <span class="aichat-ec-section-icon">🤖</span>
                        <h3>${__('Select Provider', 'axiachat-ai')}</h3>
                    </div>
                    <div class="aichat-ec-provider-grid">`;

            for (const [key, prov] of Object.entries(data.providers)) {
                const hasKey = state.providerStatus[key];
                html += `
                        <div class="aichat-ec-provider-card ${state.provider === key ? 'selected' : ''}" data-provider="${key}">
                            <div class="aichat-ec-provider-icon">${prov.icon || '🤖'}</div>
                            <div class="aichat-ec-provider-name">${esc(prov.name)}</div>
                            <div class="aichat-ec-provider-desc">${esc(prov.description)}</div>
                            ${hasKey ? '<div class="aichat-ec-provider-status"><i class="bi bi-check-circle-fill"></i> ' + __('Configured', 'axiachat-ai') + '</div>' : ''}
                        </div>`;
            }

            html += `</div>
                </div>`;
        }

        // Claude warning: requires OpenAI for embeddings
        if (!agencyActive && state.provider === 'claude') {
            const hasOpenAIKey = state.providerStatus['openai'];
            html += `
                <div class="aichat-ec-section">
                    <div class="aichat-ec-claude-warning ${hasOpenAIKey ? 'info' : 'error'}">
                        <i class="bi bi-exclamation-triangle"></i>
                        <div>
                            <strong>${__('Claude Embeddings Notice', 'axiachat-ai')}</strong>
                            <p>${__('Claude/Anthropic does not provide an embeddings API.', 'axiachat-ai')} ${hasOpenAIKey ? __('Your context will be created using <strong>OpenAI embeddings</strong> (already configured).', 'axiachat-ai') : __('<strong>OpenAI API key is required</strong> to create embeddings for the context. Please configure it in Settings before continuing.', 'axiachat-ai')}</p>
                        </div>
                    </div>
                </div>`;
        }

        // API Key Section
        const selectedProvider = data.providers[state.provider];
        const hasCurrentKey = state.providerStatus[state.provider];

        if (!agencyActive) {
            html += `
                <div class="aichat-ec-section">
                    <div class="aichat-ec-section-header">
                        <span class="aichat-ec-section-icon">🔐</span>
                        <h3>${__('API Key', 'axiachat-ai')}</h3>
                    </div>`;

            if (hasCurrentKey) {
                html += `
                    <div class="aichat-ec-api-configured">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <strong>${__('API Key Configured', 'axiachat-ai')}</strong>
                            <p>${sprintf(
                            /* translators: %s: Provider name (e.g., OpenAI, Claude) */
                            __('Your %s API key is already set up and encrypted.', 'axiachat-ai'), esc(selectedProvider.name))}</p>
                        </div>
                    </div>
                    <p class="aichat-ec-api-change">
                        <a href="#" id="ec-change-key">${__('Change API key', 'axiachat-ai')}</a>
                    </p>
                    <div class="aichat-ec-api-input-wrapper" id="ec-api-input-wrapper" style="display:none;">`;
            } else {
                html += `
                    <div class="aichat-ec-api-input-wrapper" id="ec-api-input-wrapper">`;
            }

            html += `
                        <div class="aichat-ec-api-notice">
                            <i class="bi bi-info-circle"></i>
                            <span>${__('Your API key is encrypted and stored securely.', 'axiachat-ai')} <a href="${esc(selectedProvider.help_url || '#')}" target="_blank">${__('Get your API key →', 'axiachat-ai')}</a></span>
                        </div>
                        <div class="aichat-ec-api-field">
                            <input type="password" id="ec-api-key" class="aichat-ec-input" placeholder="${__('Enter your API key...', 'axiachat-ai')}">
                            <button type="button" class="button" id="ec-save-key">${__('Save Key', 'axiachat-ai')}</button>
                        </div>
                    </div>
                </div>`;
        }

        // Navigation
        // Check if provider can continue: for Claude, also need OpenAI key for embeddings
        let canContinue = Object.values(state.providerStatus).some(v => v);
        if (state.provider === 'claude' && !state.providerStatus['openai']) {
            canContinue = false;
        }
        if (agencyActive) {
            canContinue = true;
        }

        html += `
                <div class="aichat-ec-nav">
                    <button type="button" class="button" data-action="back">
                        <i class="bi bi-arrow-left"></i> ${__('Back', 'axiachat-ai')}
                    </button>
                    <button type="button" class="button button-primary button-hero" data-action="next" ${!canContinue ? 'disabled' : ''}>
                        ${__('Start Indexing', 'axiachat-ai')} <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>`;

        return html;
    }

    // =========================================================================
    // Step 4: Indexing
    // =========================================================================
    function renderStep4() {
        const percent = state.indexTotal > 0 ? Math.round((state.indexedCount / state.indexTotal) * 100) : 0;

        let html = `
            <div class="aichat-ec-step-content aichat-ec-center">
                <div class="aichat-ec-indexing-container">
                    <div class="aichat-ec-indexing-icon">
                        ${state.indexDone ? '✅' : '⚙️'}
                    </div>
                    <h2>${state.indexDone ? __('Indexing Complete!', 'axiachat-ai') : __('Indexing Your Content...', 'axiachat-ai')}</h2>
                    <p class="aichat-ec-desc">${state.indexDone ? __('All content has been processed and is ready for your chatbot.', 'axiachat-ai') : __('Please wait while we process your content. This may take a few minutes.', 'axiachat-ai')}</p>
                    
                    <div class="aichat-ec-progress-container">
                        <div class="aichat-ec-progress-bar">
                            <div class="aichat-ec-progress-fill" style="width: ${percent}%"></div>
                        </div>
                        <div class="aichat-ec-progress-text">
                            ${sprintf(
                            /* translators: 1: Items indexed, 2: Total items, 3: Percentage */
                            __('%1$d / %2$d items (%3$d%%)', 'axiachat-ai'), state.indexedCount, state.indexTotal, percent)}
                        </div>
                    </div>
                </div>
            </div>`;

        return html;
    }

    // =========================================================================
    // Step 5: Finish
    // =========================================================================
    function renderStep5() {
        let html = `
            <div class="aichat-ec-step-content aichat-ec-center">
                <div class="aichat-ec-finish-container">
                    <div class="aichat-ec-finish-icon">🎉</div>
                    <h2>${__('Congratulations!', 'axiachat-ai')}</h2>
                    <p class="aichat-ec-desc">${__('Your AI Chatbot is now set up and ready to help your visitors!', 'axiachat-ai')}</p>
                    
                    <div class="aichat-ec-finish-summary">
                        <div class="aichat-ec-summary-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>${__('Chatbot configured with your chosen personality', 'axiachat-ai')}</span>
                        </div>
                        <div class="aichat-ec-summary-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>${sprintf(
                            /* translators: %d: Number of content items indexed */
                            __('%d content items indexed', 'axiachat-ai'), state.indexedCount)}</span>
                        </div>
                        <div class="aichat-ec-summary-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>${__('AI provider connected', 'axiachat-ai')}</span>
                        </div>
                    </div>

                    <div class="aichat-ec-next-steps">
                        <h3>${__('What\'s Next?', 'axiachat-ai')}</h3>
                        <p>${__('You can fine-tune your chatbot settings, add more content, or customize the appearance.', 'axiachat-ai')}</p>
                    </div>

                    <div class="aichat-ec-finish-actions">
                        <a href="admin.php?page=aichat-bots-settings" class="button button-primary button-hero">
                            <i class="bi bi-robot"></i> ${__('Go to Bots', 'axiachat-ai')}
                        </a>
                        <a href="admin.php?page=aichat-settings" class="button button-hero">
                            <i class="bi bi-gear"></i> ${__('Settings', 'axiachat-ai')}
                        </a>
                        <a href="admin.php?page=aichat-training" class="button button-hero">
                            <i class="bi bi-database"></i> ${__('Training', 'axiachat-ai')}
                        </a>
                    </div>
                </div>
            </div>`;

        return html;
    }

    // =========================================================================
    // Event Binding
    // =========================================================================
    function bindEvents() {
        const $root = $(ROOT_SEL);

        // Unbind previous events to prevent multiple bindings
        $root.off('click.ecwizard change.ecwizard input.ecwizard');

        // Navigation
        $root.on('click.ecwizard', '[data-action="next"]', handleNext);
        $root.on('click.ecwizard', '[data-action="back"]', handleBack);

        // Step 1 events
        $root.on('click.ecwizard', '.aichat-ec-mode-card', handleBotModeChange);
        $root.on('change.ecwizard', '#ec-bot-select', handleBotSelect);
        $root.on('click.ecwizard', '.aichat-ec-type-card', handleTypeChange);
        $root.on('change.ecwizard', 'input[name="voice_tone"]', handleToneChange);
        $root.on('input.ecwizard', '#ec-length-range', handleLengthChange);
        $root.on('input.ecwizard', '.aichat-ec-guideline-input', handleGuidelineEdit);
        $root.on('click.ecwizard', '.aichat-ec-guideline-delete', handleGuidelineDelete);
        $root.on('click.ecwizard', '#ec-add-guideline', handleGuidelineAdd);

        // Step 2 events
        $root.on('change.ecwizard', '.aichat-ec-webscan-item input[data-item-id]', handleItemToggle);
        $root.on('change.ecwizard', '.aichat-ec-webscan-item input[data-woo-id]', handleWooItemToggle);
        $root.on('click.ecwizard', '[data-action="toggle-all"]', handleToggleAll);
        $root.on('click.ecwizard', '[data-action="toggle-all-woo"]', handleToggleAllWoo);
        $root.on('click.ecwizard', '.aichat-ec-accordion-header', handleAccordion);
        $root.on('input.ecwizard', '#ec-manual-text', handleManualTextChange);
        
        // File upload
        const $uploadZone = $('#ec-upload-zone');
        const $fileInput = $('#ec-file-input');
        
        $uploadZone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });
        
        $uploadZone.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });
        
        $uploadZone.on('drop', function(e) {
            const files = e.originalEvent.dataTransfer.files;
            handleFiles(files);
        });
        
        $fileInput.on('change', function() {
            handleFiles(this.files);
            this.value = '';
        });
        
        $root.on('click.ecwizard', '.aichat-ec-file-remove', handleFileRemove);
        $root.on('change.ecwizard', '#ec-use-ai-vision', function() {
            state.useAiVision = this.checked;
        });

        // Step 3 events
        $root.on('click.ecwizard', '.aichat-ec-provider-card', handleProviderChange);
        $root.on('click.ecwizard', '#ec-change-key', function(e) {
            e.preventDefault();
            $('#ec-api-input-wrapper').slideDown();
        });
        $root.on('click.ecwizard', '#ec-save-key', handleSaveApiKey);
    }

    // =========================================================================
    // Event Handlers
    // =========================================================================
    function handleNext() {
        console.log('[EasyConfig] handleNext called, current step:', state.step);
        
        if (state.step === 0) {
            // Move to context step
            console.log('[EasyConfig] Moving from step 0 to step 1 (Training)');
            state.step = 1;
            render();
            startDiscovery();
            return;
        }

        if (state.step === 1) {
            // Save manual text
            state.manualText = $('#ec-manual-text').val() || '';
            
            // Move to provider step
            console.log('[EasyConfig] Moving from step 1 to step 2 (Provider)');
            state.step = 2;
            render();
            return;
        }

        if (state.step === 2) {
            // Move to indexing
            console.log('[EasyConfig] Moving from step 2 to step 3 (Indexing)');
            state.step = 3;
            render();
            startIndexing();
            return;
        }

        if (state.step === 3 && state.indexDone) {
            // Move to finish
            console.log('[EasyConfig] Moving from step 3 to step 4 (Finish)');
            state.step = 4;
            render();
            return;
        }
        
        console.log('[EasyConfig] handleNext - no action taken for step:', state.step);
    }

    function handleBack() {
        if (state.step > 0) {
            state.step--;
            render();
        }
    }

    function handleBotModeChange() {
        const newMode = $(this).data('mode');
        // Prevent selecting 'new' if limit reached
        if (newMode === 'new' && !state.canCreate) {
            return; // Don't change mode
        }
        state.botMode = newMode;
        if (state.botMode === 'overwrite' && data.existingBots.length > 0) {
            state.selectedBotId = data.existingBots[0].id;
        }
        render();
    }

    function handleBotSelect() {
        state.selectedBotId = parseInt($(this).val(), 10);
    }

    function handleTypeChange() {
        const type = $(this).data('type');
        state.chatbotType = type;
        
        // Update guidelines with default for this type
        if (data.chatbotTypes[type] && data.chatbotTypes[type].guidelines) {
            state.guidelines = [...data.chatbotTypes[type].guidelines];
        }
        
        render();
    }

    function handleToneChange() {
        state.voiceTone = $(this).val();
        $('.aichat-ec-tone-option').removeClass('selected');
        $(this).closest('.aichat-ec-tone-option').addClass('selected');
    }

    function handleLengthChange() {
        const val = parseInt($(this).val(), 10);
        state.responseLength = getLengthKeyByValue(val);
        
        // Update labels
        $('.aichat-ec-length-label').removeClass('active');
        $(`.aichat-ec-length-label[data-key="${state.responseLength}"]`).addClass('active');
        $('#ec-length-desc').text(getLengthDescription(state.responseLength));
    }

    function handleGuidelineEdit() {
        const idx = parseInt($(this).data('index'), 10);
        state.guidelines[idx] = $(this).val();
    }

    function handleGuidelineDelete() {
        const idx = parseInt($(this).data('index'), 10);
        state.guidelines.splice(idx, 1);
        render();
    }

    function handleGuidelineAdd() {
        state.guidelines.push('');
        render();
        // Focus the new input
        $('#ec-guidelines-list .aichat-ec-guideline-input').last().focus();
    }

    function handleItemToggle() {
        const id = parseInt($(this).data('item-id'), 10);
        if (!id) return; // safety check — skip woo inputs
        state.selected[id] = this.checked;
        updateStep2Counter();
        updateStep2Nav();
    }

    function updateStep2Counter() {
        const selectedCount = countSelected();
        $('.aichat-ec-webscan-selected:not(.aichat-ec-woo-selected-count) strong').text(selectedCount);
        
        // Update toggle all button text
        const btnText = allSelected() ? __('Unselect All', 'axiachat-ai') : __('Select All', 'axiachat-ai');
        $('[data-action="toggle-all"]').text(btnText);
    }

    function handleToggleAll() {
        const items = (state.discover && state.discover.items) ? state.discover.items : [];
        const selectAll = !allSelected();
        
        state.selected = {};
        items.forEach(item => {
            state.selected[item.id] = selectAll;
        });
        
        render();
    }

    // ----- WooCommerce handlers -----

    function handleWooItemToggle() {
        const id = parseInt($(this).data('woo-id'), 10);
        if (!id) return;
        state.wooSelected[id] = this.checked;
        updateWooCounter();
        updateStep2Nav();
    }

    function handleToggleAllWoo() {
        const items = (state.discover && state.discover.woo_items) ? state.discover.woo_items : [];
        const selectAll = !allWooSelected();

        state.wooSelected = {};
        items.forEach(item => {
            state.wooSelected[item.id] = selectAll;
        });

        render();
    }

    function updateWooCounter() {
        const count = countWooSelected();
        $('.aichat-ec-woo-selected-count strong').text(count);
        const btnText = allWooSelected() ? __('Unselect All', 'axiachat-ai') : __('Select All', 'axiachat-ai');
        $('[data-action="toggle-all-woo"]').text(btnText);
    }

    function handleAccordion() {
        const $content = $(this).next('.aichat-ec-accordion-content');
        const $arrow = $(this).find('.aichat-ec-accordion-arrow');
        
        $content.slideToggle(200);
        $arrow.toggleClass('open');
    }

    function handleManualTextChange() {
        state.manualText = $(this).val();
        updateStep2Nav();
    }

    function handleFiles(files) {
        Array.from(files).forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            if (['txt', 'pdf'].includes(ext)) {
                state.uploadedFiles.push({
                    file: file,
                    name: file.name,
                    indexed: false
                });
            }
        });
        render();
    }

    function handleFileRemove() {
        const idx = parseInt($(this).data('index'), 10);
        state.uploadedFiles.splice(idx, 1);
        render();
    }

    function handleProviderChange() {
        state.provider = $(this).data('provider');
        render();
    }

    function handleSaveApiKey() {
        const key = $('#ec-api-key').val().trim();
        if (!key) return;

        const $btn = $('#ec-save-key');
        $btn.prop('disabled', true).text('Saving...');

        $.post(ajaxurl, {
            action: 'aichat_easycfg_save_api_key',
            nonce: getNonce(),
            provider: state.provider,
            api_key: key
        }, function(response) {
            if (response && response.success) {
                state.providerStatus[state.provider] = true;
                render();
            } else {
                alert(__('Failed to save API key. Please try again.', 'axiachat-ai'));
                $btn.prop('disabled', false).text(__('Save Key', 'axiachat-ai'));
            }
        }).fail(function() {
            alert(__('Error saving API key. Please try again.', 'axiachat-ai'));
            $btn.prop('disabled', false).text(__('Save Key', 'axiachat-ai'));
        });
    }

    function updateStep2Nav() {
        const hasContent = countSelected() > 0 || countWooSelected() > 0 || state.manualText.trim() !== '' || state.uploadedFiles.length > 0;
        $('[data-action="next"]').prop('disabled', !hasContent);
    }

    // =========================================================================
    // Discovery
    // =========================================================================
    function startDiscovery() {
        console.log('[EasyConfig] startDiscovery() called, current step:', state.step);
        $.post(ajaxurl, {
            action: 'aichat_easycfg_discover',
            nonce: getNonce(),
            mode: 'smart'
        }, function(response) {
            console.log('[EasyConfig] Discovery response received, step is still:', state.step);
            if (response && response.success) {
                state.discover = response.data;
                // Select all web-content items by default
                (state.discover.items || []).forEach(item => {
                    state.selected[item.id] = true;
                });
                // Select all WooCommerce products by default
                (state.discover.woo_items || []).forEach(item => {
                    state.wooSelected[item.id] = true;
                });
                console.log('[EasyConfig] Discovered', (state.discover.items || []).length, 'web items,', (state.discover.woo_items || []).length, 'woo products');
            } else {
                state.discover = { total: 0, ids: [], items: [], woo_items: [], has_woo: false };
                console.log('[EasyConfig] Discovery returned no items');
            }
            render();
        }).fail(function() {
            console.log('[EasyConfig] Discovery AJAX failed');
            state.discover = { total: 0, ids: [], items: [], woo_items: [], has_woo: false };
            render();
        });
    }

    // =========================================================================
    // Indexing
    // =========================================================================
    function startIndexing() {
        console.log('[EasyConfig] startIndexing() called, current step:', state.step);
        // Calculate total items (web-content + woo products + manual + files)
        const selectedIds    = Object.keys(state.selected).filter(k => state.selected[k]).map(k => parseInt(k, 10));
        const wooSelectedIds = Object.keys(state.wooSelected).filter(k => state.wooSelected[k]).map(k => parseInt(k, 10));
        const allSelectedIds = selectedIds.concat(wooSelectedIds);
        const hasManualText  = state.manualText.trim() !== '';
        const fileCount      = state.uploadedFiles.length;

        state.indexTotal  = allSelectedIds.length + (hasManualText ? 1 : 0) + fileCount;
        state.indexedCount = 0;
        state.indexDone    = false;

        // Create context first (with provider for embedding)
        // If a reusable empty context exists, pass its ID so the server reuses it.
        $.post(ajaxurl, {
            action: 'aichat_easycfg_create_context',
            nonce: getNonce(),
            provider: state.provider,
            reusable_context_id: state.reusableContextId || 0
        }, function(response) {
            if (response && response.success) {
                state.contextId = response.data.context_id;
                
                // Start indexing chain
                indexNextBatch(allSelectedIds, 0, function() {
                    // After posts, index manual text
                    if (hasManualText) {
                        indexManualText(function() {
                            // After text, index files
                            indexFiles(0, function() {
                                // All done, save bot
                                saveBot();
                            });
                        });
                    } else {
                        // No manual text, index files
                        indexFiles(0, function() {
                            saveBot();
                        });
                    }
                });
            }
        });
    }

    function indexNextBatch(ids, offset, callback) {
        const batchSize = 10;
        const batch = ids.slice(offset, offset + batchSize);

        if (batch.length === 0) {
            callback();
            return;
        }

        $.post(ajaxurl, {
            action: 'aichat_easycfg_index_batch',
            nonce: getNonce(),
            context_id: state.contextId,
            ids: batch
        }, function(response) {
            state.indexedCount += batch.length;
            render();
            indexNextBatch(ids, offset + batchSize, callback);
        }).fail(function() {
            // Continue even on error
            state.indexedCount += batch.length;
            render();
            indexNextBatch(ids, offset + batchSize, callback);
        });
    }

    function indexManualText(callback) {
        $.post(ajaxurl, {
            action: 'aichat_easycfg_index_text',
            nonce: getNonce(),
            context_id: state.contextId,
            text: state.manualText,
            title: 'Manual Information'
        }, function(response) {
            state.indexedCount++;
            render();
            callback();
        }).fail(function() {
            state.indexedCount++;
            render();
            callback();
        });
    }

    function indexFiles(idx, callback) {
        if (idx >= state.uploadedFiles.length) {
            callback();
            return;
        }

        const fileData = state.uploadedFiles[idx];
        const formData = new FormData();
        formData.append('action', 'aichat_easycfg_upload_file');
        formData.append('nonce', getNonce());
        formData.append('context_id', state.contextId);
        formData.append('file', fileData.file);
        formData.append('use_ai_vision', state.useAiVision ? '1' : '0');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                state.uploadedFiles[idx].indexed = response && response.success;
                state.indexedCount++;
                render();
                indexFiles(idx + 1, callback);
            },
            error: function() {
                state.indexedCount++;
                render();
                indexFiles(idx + 1, callback);
            }
        });
    }

    function saveBot() {
        const postData = {
            action: 'aichat_easycfg_save_bot',
            nonce: getNonce(),
            mode: state.botMode,
            bot_id: state.selectedBotId || 0,
            context_id: state.contextId,
            chatbot_type: state.chatbotType,
            voice_tone: state.voiceTone,
            response_length: state.responseLength,
            provider: state.provider,
            guidelines: state.guidelines.filter(g => g.trim() !== '')
        };

        $.post(ajaxurl, postData, function(response) {
            if (response && response.success) {
                state.botId = response.data.bot_id;
                state.botSlug = response.data.bot_slug;
                state.indexDone = true;
                render();

                // Auto-advance to finish after short delay
                setTimeout(function() {
                    state.step = 4;
                    render();

                    // Save global bot setting with the actual slug
                    $.post(ajaxurl, {
                        action: 'aichat_easycfg_save_global_bot',
                        nonce: getNonce(),
                        bot_slug: state.botSlug
                    });
                }, 1500);
            }
        });
    }

    // =========================================================================
    // Utilities
    // =========================================================================
    function getNonce() {
        return $(ROOT_SEL).data('nonce');
    }

    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function countSelected() {
        return Object.values(state.selected).filter(v => v).length;
    }

    function allSelected() {
        const items = (state.discover && state.discover.items) ? state.discover.items : [];
        if (!items.length) return false;
        return items.every(item => state.selected[item.id]);
    }

    function countWooSelected() {
        return Object.values(state.wooSelected).filter(v => v).length;
    }

    function allWooSelected() {
        const items = (state.discover && state.discover.woo_items) ? state.discover.woo_items : [];
        if (!items.length) return false;
        return items.every(item => state.wooSelected[item.id]);
    }

    // =========================================================================
    // Initialize on DOM ready
    // =========================================================================
    $(document).ready(init);

})(jQuery);
