/**
 * assets/js/bots.js
 * Chatbots Admin UI (tabs + full form + autosave)
 *
 * Requiere: window.aichat_bots_ajax = { ajax_url, nonce, embedding_options }
 */
const { __, sprintf } = wp.i18n;

(function($){   
  "use strict";
   console.log('[AIChat Bots] READY — panel?', $('#aichat-panel').length, 
              'tabs?', $('#aichat-tab-strip').length, 
              'ajax obj?', !!window.aichat_bots_ajax);
  if(window.aichat_bots_ajax){
    console.log('[AIChat Bots] templates localized?', !!window.aichat_bots_ajax.instruction_templates, 'count=', window.aichat_bots_ajax.instruction_templates ? Object.keys(window.aichat_bots_ajax.instruction_templates).length : 0);
  }

  // ---------------- State ----------------
  let bots = [];          // lista de bots desde el servidor
  let activeId = null;    // id activo (db)
  const saveTimers = {};  // debounce por bot

  // -------------- Utils / Logs ----------
  // Enable debug logs only when explicitly requested via ?aichat_debug=1
  const DBG = /(?:^|[?&])aichat_debug=1(?:&|$)/.test(location.search);
  const DEBOUNCE_MS = 250; // antes 500: preview más ágil

  // ---- Preview helpers ----
  let previewTimer = null;
  // const PREVIEW_FIELDS = [...]; // ya no filtramos por campos
  function refreshPreview(bot){
    const base = (window.aichat_bots_ajax && aichat_bots_ajax.preview_url)
      ? aichat_bots_ajax.preview_url
      : (location.origin + '/?aichat_preview=1&bot=');

    const slug = (bot && bot.slug) ? bot.slug : 'default';
    const url  = base + encodeURIComponent(slug) + '&t=' + Date.now(); // bust cache

    const $if = $('#aichat-preview');
    if (!$if.length) return;

    // opcional: spinner si tienes overlay en el HTML/CSS
    // $('#aichat-preview').addClass('is-loading');

    $if.off('load.aichat').on('load.aichat', function(){
      // $('#aichat-preview').removeClass('is-loading');
    }).attr('src', url);
  }

  function schedulePreview(botId){
    clearTimeout(previewTimer);
    previewTimer = setTimeout(()=>{
      const bot = findBot(botId);
      if (bot) refreshPreview(bot);
    }, DEBOUNCE_MS);
  }

  const log = (...a)=>{ if(DBG) console.log('[AIChat Bots]', ...a); };
  
  /* ================== MODELOS POR PROVIDER (from registry) ================== */
  // Pull model data from the centralized PHP registry via wp_localize_script.
  const _REG = (window.aichat_bots_ajax && window.aichat_bots_ajax.model_registry) || {};
  const _REG_MODELS  = _REG.models  || {};
  const _REG_TOKENS  = _REG.tokens  || {};
  const _REG_DEFAULTS = _REG.defaults || {};

  function providerModels(prov){
    return _REG_MODELS[prov] || _REG_MODELS['openai'] || [];
  }

  /* ================== REBUILD MODELOS ================== */
  function rebuildModelSelect(botId){
    const b = findBot(botId);
    if(!b) return;
    // Panel actual
    const $panel = $('#aichat-panel');
    const $sel = $panel.find(`#model-${botId}`);
    if(!$sel.length) return;
    const list = providerModels(b.provider || 'openai');
    const prev = b.model;
    $sel.empty();
    list.forEach(m=>{
      if(m && m.val && m.label){
        $sel.append('<option value="'+m.val+'">'+m.label+'</option>');
      }
    });
    if(!list.some(m=>m.val===prev)){
      b.model = list[0].val;
      $sel.val(b.model);
    } else {
      $sel.val(prev);
    }
    updateModelTokenInfo(b.id);
  }

  /* ================== TOKEN INFO (from registry) ================== */
  // _REG_TOKENS is already populated from the centralized registry above.
  const MODEL_TOKEN_INFO = _REG_TOKENS;

  const DEFAULT_MAX_TOKENS_FALLBACK = 4096;
  const LEGACY_MAX_TOKENS_DEFAULT = 2048;

  function recommendedMaxTokens(model){
    const info = MODEL_TOKEN_INFO[model];
    if (!info) return DEFAULT_MAX_TOKENS_FALLBACK;
    return info.rec || info.comp || info.ctx || DEFAULT_MAX_TOKENS_FALLBACK;
  }

  function syncMaxTokensFlag(bot){
    if (!bot) return;
    const recommended = recommendedMaxTokens(bot.model);
    let max = Number(bot.max_tokens);
    if (!isFinite(max) || max <= 0) {
      bot.max_tokens = recommended;
      bot.__maxTokensCustom = false;
      return;
    }
    bot.max_tokens = max;
    const rounded = Math.round(max);
    if (rounded === LEGACY_MAX_TOKENS_DEFAULT) {
      bot.__maxTokensCustom = false;
      return;
    }
    bot.__maxTokensCustom = recommended ? rounded !== Math.round(recommended) : false;
  }

  function maybeApplyRecommendedMaxTokens(bot, opts){
    if (!bot) return null;
    const recommended = recommendedMaxTokens(bot.model);
    if (!recommended) return null;
    const force = !!(opts && opts.force);
    if (!force && bot.__maxTokensCustom) return null;

    const current = Number(bot.max_tokens);
    if (!force && Math.round(current) === Math.round(recommended)) {
      bot.__maxTokensCustom = false;
      return null;
    }

    bot.max_tokens = recommended;
    bot.__maxTokensCustom = false;
    const $panel = $('#aichat-panel');
    const $input = $panel.find(`#mx-${bot.id}`);
    if ($input.length) $input.val(recommended);
    return recommended;
  }

  function maybeUpgradeLegacyMaxTokens(bot){
    if (!bot || !bot.id) return;
    if (bot.__maxTokensCustom) return;
    const recommended = recommendedMaxTokens(bot.model);
    const rounded = Math.round(Number(bot.max_tokens));
    if (!recommended || rounded !== LEGACY_MAX_TOKENS_DEFAULT) return;
    if (Math.round(recommended) === LEGACY_MAX_TOKENS_DEFAULT) return;
    bot.max_tokens = recommended;
    bot.__maxTokensCustom = false;
    updateBot(bot.id, { max_tokens: recommended });
  }

  function updateModelTokenInfo(botId){
    const b = findBot(botId);
    if(!b) return;
    const info = MODEL_TOKEN_INFO[b.model];
    const $panel = $('#aichat-panel');
    const $inp = $panel.find(`#mx-${botId}`);
    if(!$inp.length) return;
    // Usar un contenedor único por bot
    let $box = $panel.find(`#token-info-${botId}`);
    if(!info){
      if($box.length) $box.remove();
      return;
    }
    if(!$box.length){
      $box = $('<div class="aichat-model-token-info" id="token-info-'+botId+'"></div>')
        .insertAfter($inp);
    }
    $box.html(
      __('Contextual:', 'axiachat-ai')+' '+info.ctx.toLocaleString()+
      ' - '+__('Completion:', 'axiachat-ai')+' '+info.comp.toLocaleString()+
      ' <span class="recommended">'+__('Recommended:', 'axiachat-ai')+' '+info.rec.toLocaleString()+'</span>'
    );
  }

  // Inyectar estilos una vez
  if(!document.getElementById('aichat-model-token-info-style')){
    const st=document.createElement('style');
    st.id='aichat-model-token-info-style';
    st.textContent=`.aichat-model-token-info{font-size:11px;margin-top:4px;color:#555}
    .aichat-model-token-info .recommended{color:#1a73e8;font-weight:600}`;
    document.head.appendChild(st);
  }

  // Derivar plantillas desde PHP (si existen)
  function getInstructionTemplates(){
    const raw = (window.aichat_bots_ajax && window.aichat_bots_ajax.instruction_templates) || {};
    // Normalizar a array [{key,id,name,description,template}]
    return Object.keys(raw).map(k=>({
      key: k,
      id: k,
      name: raw[k].name || k,
      description: raw[k].description || '',
      template: raw[k].template || ''
    })).filter(t=>t.template);
  }

  /* ================== HOOK RENDER FILA ================== */
  const _afterRenderBotRow_orig = window.afterRenderBotRow || null;
  window.afterRenderBotRow = function(botId){
    if(_afterRenderBotRow_orig) _afterRenderBotRow_orig(botId);
    rebuildModelSelect(botId);
  };

  const defaults = ()=>{
    const defModel = _REG_DEFAULTS['openai'] || 'gpt-5.2-chat-latest';
    return {
    id: null,
    name: 'Default',
    slug: 'default',
    type: 'text',
    instructions: 'Respond like a website support agent—friendly and creative. Use the page the customer is currently browsing as context.',

    provider: 'openai',
    model: defModel,
    temperature: 0.7,
    max_tokens: recommendedMaxTokens(defModel),
    __maxTokensCustom: false,
    reasoning: 'off',     // off|fast|accurate
    verbosity: 'medium',  // low|medium|high

  context_mode: 'page', // embeddings|page|none (changed default to 'page')
    context_id: 0,

    input_max_length: 512,
    max_messages: 20,
    context_max_length: 4096,

    ui_color: '#1a73e8',
    ui_position: 'br',         // br|bl|tr|tl
    ui_avatar_enabled: 0,
    ui_avatar_key: null,
    ui_icon_url: '',
    ui_start_sentence: 'Hi! How can I help you?',
    /* new UI defaults */
    ui_placeholder: 'Type your question...',
    ui_button_send: 'Send',
    ui_role: 'AI Agent Specialist',
  // Restored window control flags
  ui_closable: 1,
  ui_minimizable: 1,
  ui_draggable: 1,
  ui_minimized_default: 0,
  ui_superminimized_default: 0,
  ui_avatar_bubble: 1,
  ui_css_force: 0,
  // Suggestions enabled by default
  ui_suggestions_enabled: 1,
  ui_suggestions_count: 3,
  ui_suggestions_bg: '#f1f3f4',
  ui_suggestions_text: '#1a73e8',

    is_default: 0
  };};

  function makeBot(row){
    const bot = Object.assign(defaults(), row || {});
    syncMaxTokensFlag(bot);
    maybeUpgradeLegacyMaxTokens(bot);
    return bot;
  }

  const embeddingOptions = ()=>{
    const raw = (window.aichat_bots_ajax && Array.isArray(window.aichat_bots_ajax.embedding_options))
      ? window.aichat_bots_ajax.embedding_options : [{id:0,text:'— None —'}];
    return raw;
  };

  const shortcodeForBot = (bot)=> `[aichat id="${(bot.slug||'default')}"]`;

  // Global bot state — kept in sync with WP options (aichat_global_bot_enabled / aichat_global_bot_slug)
  let _globalBotEnabled = Number(window.aichat_bots_ajax && window.aichat_bots_ajax.global_bot_enabled) || 0;
  let _globalBotSlug    = (window.aichat_bots_ajax && window.aichat_bots_ajax.global_bot_slug) || '';

  function isGlobalBot(bot){
    return _globalBotEnabled && _globalBotSlug === (bot.slug || '');
  }

  function debouncePerBot(botId, fn){
    clearTimeout(saveTimers[botId]);
    saveTimers[botId] = setTimeout(fn, DEBOUNCE_MS);
  }

  function findBot(id){ return bots.find(b => String(b.id) === String(id)); }

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
  }

  /**
   * Show alert when bot limit is reached (free plan)
   */
  function showBotLimitAlert(maxBots, upgradeUrl) {
    const { __, sprintf } = wp.i18n;
    const message = sprintf(
      /* translators: %d: maximum number of bots allowed */
      __( 'You have reached the limit of %d chatbot(s) on your current plan.', 'axiachat-ai' ),
      maxBots
    );
    const upgradeText = __( 'Upgrade your plan for more chatbots.', 'axiachat-ai' );
    
    // Remove existing alert if any
    $('.aichat-limit-alert').remove();
    
    // Create and show alert
    const alertHtml = `
      <div class="aichat-limit-alert notice notice-warning" style="position:fixed;top:50px;left:50%;transform:translateX(-50%);z-index:100000;padding:15px 20px;max-width:500px;box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:4px;">
        <p style="margin:0 0 10px 0;font-size:14px;">
          <strong>⚠️ ${escapeHtml(message)}</strong>
        </p>
        <p style="margin:0;">
          <a href="${escapeHtml(upgradeUrl)}" target="_blank" class="button button-primary" style="margin-right:10px;">
            🚀 ${escapeHtml(upgradeText)}
          </a>
          <button type="button" class="button aichat-limit-alert-close">${__( 'Close', 'axiachat-ai' )}</button>
        </p>
      </div>
    `;
    
    $('body').append(alertHtml);
    
    // Auto-close after 8 seconds
    setTimeout(() => $('.aichat-limit-alert').fadeOut(300, function() { $(this).remove(); }), 8000);
    
    // Close button handler
    $('.aichat-limit-alert-close').on('click', function() {
      $('.aichat-limit-alert').fadeOut(300, function() { $(this).remove(); });
    });
  }

  // -------------- AJAX ------------------
  function ajaxPost(action, data){
    data = data || {};
    data.action = action;
    data.nonce  = (window.aichat_bots_ajax && aichat_bots_ajax.nonce) ? aichat_bots_ajax.nonce : '';
    log('AJAX →', action, data);
    return $.post(aichat_bots_ajax.ajax_url, data);
  }

  function loadBots(){
    return ajaxPost('aichat_bots_list', {})
      .done(res=>{
        log('LIST ←', res);
        // Support both old format (res.data = array) and new format (res.data.bots = array)
        const botsData = res && res.success && res.data ? (Array.isArray(res.data) ? res.data : res.data.bots) : null;
        if (botsData && Array.isArray(botsData)) {
          bots = botsData.map(row => makeBot(row));
          if (bots.length) activeId = bots[0].id;
          // Store limits info if available
          if (res.data.limits) {
            window.aichat_bots_limits = res.data.limits;
          }
          // Sync global bot state from server
          if (typeof res.data.global_bot_enabled !== 'undefined') {
            _globalBotEnabled = Number(res.data.global_bot_enabled) || 0;
            _globalBotSlug    = res.data.global_bot_slug || '';
          }
        } else {
          bots = [makeBot({id:0})];
          activeId = 0;
        }
        renderAll();
      })
      .fail(err=>{
        console.error('LIST error', err);
        bots = [makeBot({id:0})];
        activeId = 0;
        renderAll();
      });
  }

  function createBot(){
    return ajaxPost('aichat_bot_create', {})
      .done(res=>{
        log('CREATE ←', res);
        if (res && res.success && res.data) {
          const bot = makeBot(res.data);
          bots.push(bot);
          activeId = bot.id;
          renderAll();
        }
      });
  }

  function updateBot(botId, patch){
    const bot = findBot(botId);
    if (bot) Object.assign(bot, patch);

    debouncePerBot(botId, ()=>{
      const payload = { id: botId, patch: JSON.stringify(patch) };
      ajaxPost('aichat_bot_update', payload)
        .done(res=> log('UPDATE ←', res))
        .fail(err=> console.error('UPDATE error', err));
    });
  }

  function duplicateBot(botId){
    return ajaxPost('aichat_bot_duplicate', { id: botId })
      .done(res=>{
        log('DUP ←', res);
        if (res && res.success && res.data) {
          const copy = makeBot(res.data);
          bots.push(copy);
          activeId = copy.id;
          renderAll();
        }
      });
  }

  function resetBot(botId){
    return ajaxPost('aichat_bot_reset', { id: botId })
      .done(res=>{
        log('RESET ←', res);
        if (res && res.success && res.data) {
          const idx = bots.findIndex(b => String(b.id)===String(botId));
          if (idx>=0) {
            bots[idx] = makeBot(res.data);
            activeId = bots[idx].id;
            renderAll();
          }
        }
      });
  }

  function deleteBot(botId){
    return ajaxPost('aichat_bot_delete', { id: botId })
      .done(res=>{
        log('DEL ←', res);
        if (res && res.success) {
          bots = bots.filter(b => String(b.id)!==String(botId));
          if (!bots.length) {
            bots = [makeBot({id:0, is_default:1})];
          }
          if (!findBot(activeId)) activeId = bots[0].id;
          renderAll();
        }
      });
  }

  // -------------- Render ----------------
  function renderAll(){
    renderTabs();
    highlightActiveTab();
    renderPanel(activeId);
    updateArrows();
  }

  function renderTabs(){
    const $rail = $('#aichat-tab-strip');
    $rail.empty();

    bots.forEach(b=>{
      const $btn = $('<button/>', {
        type:'button',
        class:'aichat-tab' + (String(b.id)===String(activeId) ? ' active' : ''),
        'data-id': b.id,
        'aria-label': `Bot ${b.name || 'Bot'}`
      }).append('<i class="bi bi-robot"></i>')
        .append($('<span class="aichat-tab-title"/>').text(b.name || 'Bot'));
      $rail.append($btn);
    });
  }

  function highlightActiveTab(){
    const $rail = $('#aichat-tab-strip');
    $rail.find('.aichat-tab').removeClass('active');
    $rail.find(`.aichat-tab[data-id="${activeId}"]`).addClass('active');
  }

  function radio(field, value, bot, label){
    const checked = (String(bot[field])===String(value)) ? 'checked' : '';
    return `
      <label class="me-3">
        <input type="radio" class="form-check-input aichat-field" name="${field}-${bot.id}"
               data-field="${field}" data-id="${bot.id}" value="${value}" ${checked}> ${label}
      </label>
    `;
  }

  function nicePos(code){
    const map = { br:__('Bottom-right','axiachat-ai'), bl:__('Bottom-left','axiachat-ai'), tr:__('Top-right','axiachat-ai'), tl:__('Top-left','axiachat-ai') };
    return map[code] || code;
  }

  function renderPanel(botId){
    const bot = findBot(botId);
    const $panel = $('#aichat-panel');
    if (!$panel.length || !bot){ $panel.html(''); return; }

    const models = providerModels(bot.provider);
    const modelsHTML = models.map(m=> `<option value="${m.val}" ${bot.model===m.val?'selected':''}>${m.label}</option>`).join('');

    // Avatares desde assets/images/1.png ... 22.png (compat: aceptar 'avatarN' legacy)
    const scriptEl = document.querySelector('script[src*="assets/js/bots.js"]');
    const pluginBase = scriptEl ? scriptEl.src.replace(/assets\/js\/bots\.js.*$/, '') : '';
    const imgBase = `${pluginBase}assets/images/`;

    // Normaliza la clave actual: puede venir 'avatar7' (legacy) o '7' (nuevo)
    const currentKeyRaw = String(bot.ui_avatar_key || '');
    const matchLegacy = currentKeyRaw.match(/^(?:avatar)?(\d{1,2})$/i);
    const currentNum = matchLegacy ? matchLegacy[1] : '';

    const totalAvatars = 22;
  const avatars = Array.from({ length: totalAvatars }, (_, idx) => {
      const num = String(idx + 1);
      const key = num; // almacenamos el valor numérico como nueva clave
      const url = `${imgBase}${num}.png`;
      const isActive = (String(currentNum) === num);
      const checked = isActive ? 'checked' : '';
      const activeCls = isActive ? ' active' : '';
      return `
        <label class="aichat-avatar${activeCls}" title="${key}">
          <input type="radio" class="aichat-field d-none"
                 data-field="ui_avatar_key" data-id="${bot.id}"
                 name="ui_avatar_key-${bot.id}" value="${key}" ${checked}>
          <img src="${url}" alt="${key}">
        </label>
      `;
    }).join('');

    const html = `
      <form class="aichat-bot-form" data-id="${bot.id}">
        <div class="accordion aichat-accordion" id="acc-${bot.id}">

          <!-- General -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="g-h-${bot.id}">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#g-b-${bot.id}">
                <i class="bi bi-gear me-2"></i> ${__('General', 'axiachat-ai')}
              </button>
            </h2>
            <div id="g-b-${bot.id}" class="accordion-collapse collapse show" data-bs-parent="#acc-${bot.id}">
              <div class="accordion-body">
                <div class="row g-3">
                  <div style="flex:0 0 35%;max-width:35%">
                    <label class="form-label" for="name-${bot.id}">${__('Chatbot Name', 'axiachat-ai')}</label>
                    <input type="text" class="form-control aichat-field" data-field="name" data-id="${bot.id}" id="name-${bot.id}" value="${escapeHtml(bot.name||'')}">
                  </div>
                  <div style="flex:0 0 20%;max-width:20%">
                    <label class="form-label" for="type-${bot.id}">${__('Chatbot Type', 'axiachat-ai')}</label>
                    <select class="form-select aichat-field" data-field="type" data-id="${bot.id}" id="type-${bot.id}">
                      <option value="text" ${bot.type==='text'?'selected':''}>${__('Text', 'axiachat-ai')}</option>
                      <option value="voice_text" ${bot.type==='voice_text'?'selected':''}>${__('Voice & Text', 'axiachat-ai')}</option>
                    </select>
                  </div>
                  <div style="flex:0 0 45%;max-width:45%">
                    <label class="form-label" for="role-${bot.id}">${__('Role / subtitle', 'axiachat-ai')}</label>
                    <input type="text" class="form-control aichat-field" data-field="ui_role" data-id="${bot.id}" id="role-${bot.id}" value="${escapeHtml(bot.ui_role||'AI Agent Specialist')}">
                  </div>
                </div>

                <div class="mt-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="globalbot-${bot.id}"
                           ${isGlobalBot(bot)?'checked':''}
                           data-id="${bot.id}">
                    <label class="form-check-label" for="globalbot-${bot.id}">${__('Show on all pages as a floating widget', 'axiachat-ai')}</label>
                  </div>
                </div>

                ${(window.aichat_bots_ajax && window.aichat_bots_ajax.test_page_url) ? `
                <div class="mt-3 d-flex align-items-center gap-2">
                  <span class="text-muted small">${__('Test the bot before making it public:', 'axiachat-ai')}</span>
                  <a href="${escapeHtml(window.aichat_bots_ajax.test_page_url)}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-eye me-1"></i>${__('Open external test page', 'axiachat-ai')}
                  </a>
                </div>
                ` : ''}

                <div class="mt-3 row g-3 align-items-center">
                  <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">${__('ID (slug)', 'axiachat-ai')}</label>
                    <input type="text" class="form-control form-control-sm aichat-field" data-field="slug" data-id="${bot.id}" id="slug-${bot.id}" value="${escapeHtml(bot.slug||'')}">
                  </div>
                  <div class="col-md-8">
                    <label class="form-label small text-muted mb-1">${__('Shortcode', 'axiachat-ai')}</label>
                    <div class="d-flex align-items-center gap-2">
                      <span class="aichat-shortcode flex-grow-1">
                        <code id="sc-${bot.id}">${shortcodeForBot(bot)}</code>
                        <button type="button" class="copy-btn" data-copy="#sc-${bot.id}"><i class="bi bi-clipboard"></i></button>
                      </span>
                      <span class="form-text-muted small">${__('You can use it in posts/pages.', 'axiachat-ai')}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Appearance -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="a-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#a-b-${bot.id}">
                <i class="bi bi-palette me-2"></i> ${__('Appearance', 'axiachat-ai')}
              </button>
            </h2>
            <div id="a-b-${bot.id}" class="accordion-collapse collapse" data-bs-parent="#acc-${bot.id}">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label" for="color-${bot.id}">${__('Color', 'axiachat-ai')}</label>
                    <input id="color-${bot.id}" type="color" class="form-control form-control-color aichat-field"
                           data-field="ui_color" data-id="${bot.id}" value="${bot.ui_color||'#1a73e8'}">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="pos-${bot.id}">${__('Position', 'axiachat-ai')}</label>
                    <select id="pos-${bot.id}" class="form-select aichat-field" data-field="ui_position" data-id="${bot.id}">
                      ${['br','bl','tr','tl'].map(p => `<option value="${p}" ${bot.ui_position===p?'selected':''}>${nicePos(p)}</option>`).join('')}
                    </select>
                  </div>
                  <div class="col-md-4">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="avaon-${bot.id}"
                             data-field="ui_avatar_enabled" data-id="${bot.id}" ${bot.ui_avatar_enabled? 'checked':''}>
                      <label class="form-check-label" for="avaon-${bot.id}">${__('Avatar enabled', 'axiachat-ai')}</label>
                    </div>
                  </div>
                </div>

                <!-- Window control flags -->
                <div class="row g-3 mt-1">
                  <div class="col-md-3">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="clos-${bot.id}" data-field="ui_closable" data-id="${bot.id}" ${bot.ui_closable? 'checked':''}>
                      <label class="form-check-label" for="clos-${bot.id}">${__('Closable', 'axiachat-ai')}</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="mini-${bot.id}" data-field="ui_minimizable" data-id="${bot.id}" ${bot.ui_minimizable? 'checked':''}>
                      <label class="form-check-label" for="mini-${bot.id}">${__('Minimizable', 'axiachat-ai')}</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="drag-${bot.id}" data-field="ui_draggable" data-id="${bot.id}" ${bot.ui_draggable? 'checked':''}>
                      <label class="form-check-label" for="drag-${bot.id}">${__('Draggable', 'axiachat-ai')}</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-check mt-4">
                      <input class="form-check-input aichat-field" type="checkbox" id="mindef-${bot.id}" data-field="ui_minimized_default" data-id="${bot.id}" ${bot.ui_minimized_default? 'checked':''}>
                      <label class="form-check-label" for="mindef-${bot.id}">${__('Minimized (default)', 'axiachat-ai')}</label>
                    </div>
                  </div>
                </div>
                <!-- NUEVA FILA: Super minimized -->
                <div class="row g-3 mt-1">
                  <div class="col-md-3">
                    <div class="form-check mt-2">
                      <input class="form-check-input aichat-field" type="checkbox" id="supmindef-${bot.id}" data-field="ui_superminimized_default" data-id="${bot.id}" ${bot.ui_superminimized_default? 'checked':''}>
                      <label class="form-check-label" for="supmindef-${bot.id}">${__('Super minimized (avatar)', 'axiachat-ai')}</label>
                    </div>
                  </div>
                  <div class="col-md-9">
                    <div class="form-text-muted" style="margin-top:14px;">${__('If enabled, the widget starts as an avatar bubble. Opening it will show the full chat window.', 'axiachat-ai')}</div>
                  </div>
                </div>
                <!-- Avatar bubble: show start sentence on avatar -->
                <div class="row g-3 mt-1" id="avatarbubble-wrap-${bot.id}" style="${bot.ui_superminimized_default ? '' : 'display:none'}">
                  <div class="col-md-3">
                    <div class="form-check mt-2">
                      <input class="form-check-input aichat-field" type="checkbox" id="avatarbubble-${bot.id}" data-field="ui_avatar_bubble" data-id="${bot.id}" ${bot.ui_avatar_bubble ? 'checked':''}>
                      <label class="form-check-label" for="avatarbubble-${bot.id}">${__('Show start sentence on avatar', 'axiachat-ai')}</label>
                    </div>
                  </div>
                  <div class="col-md-9">
                    <div class="form-text-muted" style="margin-top:14px;">${__('Displays the start sentence in a speech bubble next to the avatar.', 'axiachat-ai')}</div>
                  </div>
                </div>

                <!-- Force CSS isolation -->
                <div class="row g-3 mt-1">
                  <div class="col-md-3">
                    <div class="form-check mt-2">
                      <input class="form-check-input aichat-field" type="checkbox" id="cssforce-${bot.id}" data-field="ui_css_force" data-id="${bot.id}" ${bot.ui_css_force? 'checked':''}>
                      <label class="form-check-label" for="cssforce-${bot.id}">${__('Force CSS isolation', 'axiachat-ai')}</label>
                    </div>
                  </div>
                  <div class="col-md-9">
                    <div class="form-text-muted" style="margin-top:14px;">${__('Locks fonts, sizes and line-heights with !important. Fixes display issues on themes that override widget typography.', 'axiachat-ai')}</div>
                  </div>
                </div>

                <!-- NUEVOS CAMPOS -->
                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label" for="ph-${bot.id}">${__('Placeholder', 'axiachat-ai')}</label>
                    <input id="ph-${bot.id}" type="text" class="form-control aichat-field"
                           data-field="ui_placeholder" data-id="${bot.id}" value="${escapeHtml(bot.ui_placeholder||'')}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="sendlbl-${bot.id}">${__('Send button label', 'axiachat-ai')}</label>
                    <input id="sendlbl-${bot.id}" type="text" class="form-control aichat-field"
                           data-field="ui_button_send" data-id="${bot.id}" value="${escapeHtml(bot.ui_button_send||'')}">
                  </div>
                </div>

                <!-- Size: width/height -->
                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label" for="w-${bot.id}">${__('Widget width (px)', 'axiachat-ai')}</label>
          <input id="w-${bot.id}" type="number" min="300" max="1200" class="form-control aichat-field"
            data-field="ui_width" data-id="${bot.id}" value="${parseInt(bot.ui_width||380,10)}">
                    <div class="form-text">${__('Minimum 300px; auto-clamped to viewport on small screens.', 'axiachat-ai')}</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="h-${bot.id}">${__('Messages area height (px)', 'axiachat-ai')}</label>
          <input id="h-${bot.id}" type="number" min="300" max="1200" class="form-control aichat-field"
            data-field="ui_height" data-id="${bot.id}" value="${parseInt(bot.ui_height||380,10)}">
                    <div class="form-text">${__('Minimum 300px; auto-clamped to visible space under header/input.', 'axiachat-ai')}</div>
                  </div>
                </div>

                <div class="mt-3" id="avatar-wrap-${bot.id}" style="${bot.ui_avatar_enabled?'':'display:none;'}">
                  <div class="mb-2 fw-semibold">${__('Pick an avatar', 'axiachat-ai')}</div>
                  <div class="aichat-ava-picker">
                    <button type="button" class="aichat-ava-arrow left" data-bot="${bot.id}" aria-label="${__('Scroll left', 'axiachat-ai')}">‹</button>
                    <div class="aichat-ava-strip" id="ava-strip-${bot.id}" data-bot="${bot.id}" role="listbox">${avatars}</div>
                    <button type="button" class="aichat-ava-arrow right" data-bot="${bot.id}" aria-label="${__('Scroll right', 'axiachat-ai')}">›</button>
                  </div>
                  <div class="mt-2">
                    <label class="form-label" for="icon-${bot.id}">${__('Custom Icon URL', 'axiachat-ai')}</label>
                    <input id="icon-${bot.id}" type="url" class="form-control aichat-field" data-field="ui_icon_url" data-id="${bot.id}" value="${escapeHtml(bot.ui_icon_url||'')}">
                  </div>
                </div>

                <div class="mt-3">
                  <label class="form-label" for="start-${bot.id}">${__('Start Sentence', 'axiachat-ai')}</label>
                  <input id="start-${bot.id}" type="text" class="form-control aichat-field"
                         data-field="ui_start_sentence" data-id="${bot.id}" value="${escapeHtml(bot.ui_start_sentence||'')}">
                </div>
              </div>
            </div>
          </div>

          <!-- Suggestions / Next actions -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="sug-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sug-b-${bot.id}">
                <i class="bi bi-ui-checks-grid me-2"></i> ${__('Suggestions / Next actions', 'axiachat-ai')}
              </button>
            </h2>
            <div id="sug-b-${bot.id}" class="accordion-collapse collapse" data-bs-parent="#acc-${bot.id}">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="form-check mt-2">
                      <input class="form-check-input aichat-field" type="checkbox" id="sugon-${bot.id}"
                             data-field="ui_suggestions_enabled" data-id="${bot.id}" ${bot.ui_suggestions_enabled ? 'checked' : ''}>
                      <label class="form-check-label" for="sugon-${bot.id}">${__('Enable suggested replies (chips)', 'axiachat-ai')}</label>
                    </div>
                    <div class="form-text">${__('If enabled, the system prompt asks the model to return suggestions in a strict format. The widget renders them as clickable chips.', 'axiachat-ai')}</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="sugcount-${bot.id}">${__('How many suggestions', 'axiachat-ai')}</label>
                    <select id="sugcount-${bot.id}" class="form-select aichat-field" data-field="ui_suggestions_count" data-id="${bot.id}">
                      ${[1,2,3,4,5,6].map(n=>`<option value="${n}" ${(parseInt(bot.ui_suggestions_count||3,10)===n)?'selected':''}>${n}</option>`).join('')}
                    </select>
                    <div class="form-text">${__('Recommended: 3–5. We clamp the model output to this number server-side.', 'axiachat-ai')}</div>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label" for="sugbg-${bot.id}">${__('Chip background', 'axiachat-ai')}</label>
                    <input id="sugbg-${bot.id}" type="color" class="form-control form-control-color aichat-field"
                           data-field="ui_suggestions_bg" data-id="${bot.id}" value="${bot.ui_suggestions_bg || '#f1f3f4'}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="sugtxt-${bot.id}">${__('Chip text color', 'axiachat-ai')}</label>
                    <input id="sugtxt-${bot.id}" type="color" class="form-control form-control-color aichat-field"
                           data-field="ui_suggestions_text" data-id="${bot.id}" value="${bot.ui_suggestions_text || '#1a73e8'}">
                  </div>
                </div>

                <div class="mt-3">
                  <div class="form-text-muted">${__('Pro tip: keep suggestions short (2–6 words). Chips send the suggestion as the next user message when clicked.', 'axiachat-ai')}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Thresholds (simplified in v3.0.1 – context thresholds moved to Training) -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="t-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t-b-${bot.id}">
                <i class="bi bi-sliders me-2"></i> ${__('Conversation Limits', 'axiachat-ai')} <span class="badge bg-secondary ms-2" style="font-size:.65em">${__('Advanced', 'axiachat-ai')}</span>
              </button>
            </h2>
            <div id="t-b-${bot.id}" class="accordion-collapse collapse" data-bs-parent="#acc-${bot.id}">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label" for="inmax-${bot.id}">${__('Input Max Length', 'axiachat-ai')}</label>
                    <input id="inmax-${bot.id}" type="number" class="form-control aichat-field" data-field="input_max_length" data-id="${bot.id}" value="${parseInt(bot.input_max_length||512,10)}">
                    <div class="form-text">${__('Maximum characters per user input.', 'axiachat-ai')}</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="mxmsg-${bot.id}">${__('Max Messages', 'axiachat-ai')}</label>
                    <input id="mxmsg-${bot.id}" type="number" class="form-control aichat-field" data-field="max_messages" data-id="${bot.id}" value="${parseInt(bot.max_messages||20,10)}">
                    <div class="form-text">${__('Historical messages sent to the model.', 'axiachat-ai')}</div>
                  </div>
                  <div class="col-md-4 d-flex align-items-center">
                    <div class="form-check">
                      <input class="form-check-input aichat-field" type="checkbox" id="histpersist-${bot.id}" data-field="history_persistence" data-id="${bot.id}" ${parseInt(bot.history_persistence??1,10)?'checked':''}>
                      <label class="form-check-label" for="histpersist-${bot.id}">${__('History Persistence', 'axiachat-ai')}</label>
                      <div class="form-text">${__('When OFF the chat history is cleared on page refresh.', 'axiachat-ai')}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Model -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="m-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m-b-${bot.id}">
                <i class="bi bi-cpu me-2"></i> ${__('Model', 'axiachat-ai')} <span class="badge bg-secondary ms-2" style="font-size:.65em">${__('Advanced', 'axiachat-ai')}</span>
              </button>
            </h2>
            <div id="m-b-${bot.id}" class="accordion-collapse collapse" data-bs-parent="#acc-${bot.id}">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="prov-${bot.id}">${__('Provider', 'axiachat-ai')}</label>
                    <select id="prov-${bot.id}" class="form-select aichat-field" data-field="provider" data-id="${bot.id}">
                      <option value="openai" ${bot.provider==='openai'?'selected':''}>OpenAI</option>
                      <option value="anthropic" ${bot.provider==='anthropic'?'selected':''}>Claude</option>
                      <option value="gemini" ${bot.provider==='gemini'?'selected':''}>${__('Google Gemini', 'axiachat-ai')}</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="model-${bot.id}">${__('Model', 'axiachat-ai')}</label>
                    <select id="model-${bot.id}" class="form-select aichat-field" data-field="model" data-id="${bot.id}">
                      ${modelsHTML}
                    </select>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label" for="temp-${bot.id}">${__('Temperature', 'axiachat-ai')}</label>
                    <input id="temp-${bot.id}" type="number" step="0.01" min="0" max="2"
                           class="form-control aichat-field" data-field="temperature" data-id="${bot.id}" value="${Number(bot.temperature||0.7)}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="mx-${bot.id}">${__('Max Tokens', 'axiachat-ai')}</label>
                    <input id="mx-${bot.id}" type="number" min="1"
                           class="form-control aichat-field" data-field="max_tokens" data-id="${bot.id}" value="${parseInt(bot.max_tokens||2048,10)}">
                  </div>
                </div>

                <div class="row g-3 mt-2">
                  <div class="col-md-6">
                    <label class="form-label d-block">${__('Reasoning', 'axiachat-ai')}</label>
                    ${radio('reasoning','off',bot,__('Off', 'axiachat-ai'))}
                    ${radio('reasoning','fast',bot,__('Fast', 'axiachat-ai'))}
                    ${radio('reasoning','accurate',bot,__('Accurate', 'axiachat-ai'))}
                  </div>
                  <div class="col-md-6">
                    <label class="form-label d-block">${__('Verbosity', 'axiachat-ai')}</label>
                    ${radio('verbosity','low',bot,__('Low', 'axiachat-ai'))}
                    ${radio('verbosity','medium',bot,__('Medium', 'axiachat-ai'))}
                    ${radio('verbosity','high',bot,__('High', 'axiachat-ai'))}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="x-h-${bot.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#x-b-${bot.id}">
                <i class="bi bi-tools me-2"></i> ${__('Actions', 'axiachat-ai')}
              </button>
            </h2>
            <div id="x-b-${bot.id}" class="accordion-collapse collapse" data-bs-parent="#acc-${bot.id}">
              <div class="accordion-body d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                  <button type="button" class="button button-secondary aichat-action" data-action="duplicate" data-id="${bot.id}">
                    <i class="bi bi-files"></i> ${__('Duplicate', 'axiachat-ai')}
                  </button>
                  <button type="button" class="button button-secondary aichat-action" data-action="reset" data-id="${bot.id}">
                    <i class="bi bi-arrow-counterclockwise"></i> ${__('Reset', 'axiachat-ai')}
                  </button>
                </div>
                <div>
                  <button type="button" class="button button-danger aichat-action" data-action="delete" data-id="${bot.id}" ${(bots.length<=1 || bot.slug==='default')?'disabled':''}>
                    <i class="bi bi-trash"></i> ${__('Delete', 'axiachat-ai')}
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </form>
    `;

    $panel.html(html);
    updateModelTokenInfo(bot.id);
    refreshPreview(bot);
    // acoordeones: inicializar listeners de accesibilidad
    try {
  const accSel = `#acc-${bot.id}`;
  const $acc = $panel.find(accSel);
      const accEl = document.getElementById(`acc-${bot.id}`);
      const BOT_ID = bot.id;
      if (accEl && window.bootstrap && window.bootstrap.Collapse) {
        // Dejar que Bootstrap gestione el toggle con data-bs-parent (máximo 1 abierto por defecto)
        // Accesibilidad: permitir teclado (Enter/Espacio) sobre el botón
        accEl.addEventListener('keydown', function(ev){
          const btn = ev.target && ev.target.closest && ev.target.closest('.accordion-button');
          if (!btn) return;
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            btn.click(); // deja a Bootstrap hacer el toggle
          }
        });
      }
    } catch(_){ /* noop */ }

    // Initialize avatar arrows state
    setTimeout(()=>{ try{ updateAvaScroll(bot.id); }catch(e){} }, 50);
    
  }

  // -------------- Events ----------------
  function bindEvents(){
    $(document)
      .off('click.aichat', '#aichat-add-bot')
      .off('click.aichat', '#aichat-tab-strip .aichat-tab')
      .off('click.aichat', '#aichat-tabs-prev')
      .off('click.aichat', '#aichat-tabs-next')
      .off('click.aichat', '.copy-btn')
      .off('input.aichat change.aichat', '#aichat-panel .aichat-field')
      .off('click.aichat', '.aichat-tpl-arrow')
      .off('scroll.aichat', '.aichat-tpl-list');

    // Añadir bot
    $(document).on('click.aichat', '#aichat-add-bot', function(e){
      e.preventDefault();
      // Check bot limits before creating
      const limits = window.aichat_bots_limits;
      if (limits && !limits.can_create) {
        const maxBots = limits.max_bots || 2;
        const upgradeUrl = limits.upgrade_url || 'https://axiachat.com/pricing/';
        // Show upgrade modal/alert
        showBotLimitAlert(maxBots, upgradeUrl);
        return;
      }
      createBot();
    });

    // Activar pestaña
    $(document).on('click.aichat', '#aichat-tab-strip .aichat-tab', function(){
      activeId = $(this).data('id');
      highlightActiveTab();
      renderPanel(activeId);
    });

    // Scroll pestañas
    $(document).on('click.aichat', '#aichat-tabs-prev', function(){
      const rail = document.getElementById('aichat-tab-strip');
      rail && rail.scrollBy({ left: -150, behavior: 'smooth' });
    });
    $(document).on('click.aichat', '#aichat-tabs-next', function(){
      const rail = document.getElementById('aichat-tab-strip');
      rail && rail.scrollBy({ left:  150, behavior: 'smooth' });
    });

    // Copiar shortcode
    $(document).on('click.aichat', '.copy-btn', function(){
      const sel = $(this).data('copy');
      const el  = document.querySelector(sel);
      if (!el) return;
      const txt = el.textContent.trim();
      navigator.clipboard?.writeText(txt);
    });

    // (Acordeones: la lógica principal se monta tras renderPanel, con listeners nativos y captura)

    // Cambios de campo (autosave)
    $(document).on('input.aichat change.aichat', '#aichat-panel .aichat-field', function(){
      const $el   = $(this);
      const id    = $el.data('id');
      const field = $el.data('field');
      let val     = $el.val();

      if ($el.is(':checkbox')) {
        val = $el.is(':checked') ? 1 : 0;
      } else if ($el.is(':radio')) {
        if (!$el.is(':checked')) return;
        val = $el.val();
      } else {
        val = $el.val();
      }

      if (['temperature','max_tokens','input_max_length','max_messages','context_max_length','context_limit','ui_width','ui_height'].includes(field)) {
        const num = Number(val);
        val = isFinite(num) ? num : val;
      }

      // Live update for range slider label
      if (field === 'context_limit') {
        $(`#ctxlimit-val-${id}`).text(val);
      }

      if (field === 'max_tokens') {
        const bot = findBot(id);
        if (bot) {
          const numeric = Number(val);
          bot.max_tokens = isFinite(numeric) ? numeric : 0;
          const rec = recommendedMaxTokens(bot.model);
          bot.__maxTokensCustom = rec ? Math.round(bot.max_tokens) !== Math.round(rec) : true;
        }
      }

      if (field === 'ui_avatar_enabled') {
        $(`#avatar-wrap-${id}`).toggle(!!val);
        try { updateAvaScroll(id); } catch(e){}
      }

      if (field === 'ui_superminimized_default') {
        $(`#avatarbubble-wrap-${id}`).toggle(!!val);
      }

      if (field === 'slug') {
        const code = `[aichat id="${(val||'default')}"]`;
        $(`#sc-${id}`).text(code);
      }

      // Cambio de provider: reconstruir modelos y enviar patch combinado
      if (field === 'provider') {
        const bot = findBot(id);
        if (bot) {
          bot.provider = val;
          rebuildModelSelect(id);
          const patch = { provider: bot.provider, model: bot.model };
          const newMax = maybeApplyRecommendedMaxTokens(bot);
          if (typeof newMax === 'number') {
            patch.max_tokens = newMax;
          }
          updateBot(id, patch);
          schedulePreview(id);
          return;
        }
      }
      if (field === 'model') {
        const bot = findBot(id);
        if (bot) {
          bot.model = val;
          updateModelTokenInfo(id);
          const patch = { model: bot.model };
          const newMax = maybeApplyRecommendedMaxTokens(bot);
          if (typeof newMax === 'number') {
            patch.max_tokens = newMax;
          }
          updateBot(id, patch);
          schedulePreview(id);
          return;
        }
      }

      const patch = {}; patch[field] = val;
      updateBot(id, patch);

      // Marcar visualmente el avatar activo
      if (field === 'ui_avatar_key') {
        $(`#avatar-wrap-${id} .aichat-avatar`).removeClass('active');
        $el.closest('.aichat-avatar').addClass('active');
      }

      // Preview inmediato para cualquier campo EXCEPTO slug (para evitar 404 mientras guarda)
      if (field !== 'slug') {
        schedulePreview(id);
      }
    });

    // Tras guardar en AJAX (incluye cambios de slug), refresca con los datos devueltos por el servidor
    $(document).ajaxSuccess(function(evt, xhr, settings){
      try {
        if (!settings || !settings.data || settings.data.indexOf('action=aichat_bot_update') === -1) return;
        const res = JSON.parse(xhr.responseText);
        if (res && res.success && res.data && res.data.bot) {
          refreshPreview(res.data.bot); // usa el slug ya consolidado en BD
        }
      } catch(e){}
    });

    // Mantener visibilidad de flechas
    const rail = document.getElementById('aichat-tab-strip');
    if (rail) {
      rail.addEventListener('scroll', updateArrows, {passive:true});
      window.addEventListener('resize', updateArrows, {passive:true});
      // Also update avatar arrows on resize for current bot
      window.addEventListener('resize', function(){ try{ if(activeId!=null) updateAvaScroll(activeId); }catch(e){} }, {passive:true});
    }

    // Avatar arrows: scroll strip (robust, smooth scrolling)
    $(document).on('click.aichat', '.aichat-ava-arrow', function(e){
      e.preventDefault(); e.stopPropagation();
      const botId = $(this).data('bot');
      const el = document.getElementById(`ava-strip-${botId}`);
      if(!el) return;
      const dir = this.classList.contains('left') ? -1 : 1;
      const firstItem = el.querySelector('.aichat-avatar');
      const itemW = firstItem ? (firstItem.getBoundingClientRect().width || 0) : 0;
      const gap = 8; // CSS gap
      // Prefer ~3 items per step, min 60% of visible width
      const step = Math.max((itemW + gap) * 3, el.clientWidth * 0.6, 240);
      if (typeof el.scrollBy === 'function') {
        el.scrollBy({ left: dir * step, behavior: 'smooth' });
      } else {
        // Fallback jQuery animation
        const $list = $(el);
        $list.animate({ scrollLeft: $list.scrollLeft() + dir*step }, 200);
      }
      setTimeout(()=> updateAvaScroll(botId), 260);
    });
    $(document).on('scroll.aichat', '.aichat-ava-strip', function(){
      const m = this.id.match(/ava-strip-(\d+)/); if(m) updateAvaScroll(m[1]);
    });

    // Acciones
    $(document).on('click.aichat', '#aichat-panel .aichat-action', function(){
      const id = $(this).data('id');
      const act = $(this).data('action');
      if (!id || !act) return;

      if (act === 'duplicate') {
        duplicateBot(id);
      } else if (act === 'reset') {
        if (confirm(__('Reset this bot to default settings?', 'axiachat-ai'))) resetBot(id);
      } else if (act === 'delete') {
        const bot = findBot(id);
        if (!bot) return;
        if (bots.length<=1 || bot.slug==='default') return;
        if (confirm(__('Delete this bot? This action cannot be undone.', 'axiachat-ai'))) deleteBot(id);
      }
    });

    // Plantillas: seleccionar
    $(document).on('click.aichat', '#aichat-panel .aichat-tpl-item', function(){
      const $it=$(this); const botId=$it.data('bot');
      $(`#tpl-list-${botId} .aichat-tpl-item`).removeClass('active');
      $it.addClass('active');
      const tplId=$it.data('id');
      const list=getInstructionTemplates();
      const tpl=list.find(t=>t.id===tplId);
      const $desc=$(`#tpl-desc-${botId}`);
      const $btn=$(`#tpl-panel-${botId} .aichat-tpl-load[data-id="${botId}"]`);
      if(tpl){
        $desc.text(tpl.description);
        $btn.prop('disabled', false).data('tplId', tpl.id);
      } else {
        $desc.text('');
        $btn.prop('disabled', true).removeData('tplId');
      }
    });
    // Plantillas: cargar
    $(document).on('click.aichat', '#aichat-panel .aichat-tpl-load', function(){
      const botId=$(this).data('id');
      const tplId=$(this).data('tplId');
      if(!tplId) return; const list=getInstructionTemplates();
      const tpl=list.find(t=>t.id===tplId); if(!tpl) return;
      const $ta=$(`#inst-${botId}`); if(!$ta.length) return;
      const current=($ta.val()||'').trim();
      if(current && current!==tpl.template){
        if(!confirm(__('This will replace the current instructions. Continue?', 'axiachat-ai'))) return;
      }
      $ta.val(tpl.template).trigger('input');
    });

    // Flechas de navegación en pestañas
    $(document).on('mouseenter.aichat', '#aichat-tab-strip', function(){
      updateArrows();
    });

    // Global bot checkbox toggle
    $(document).on('change.aichat', '[id^="globalbot-"]', function(){
      const $cb  = $(this);
      const id   = $cb.data('id');
      const bot  = findBot(id);
      if (!bot) return;
      const enabled = $cb.is(':checked') ? 1 : 0;

      // Update local state
      _globalBotEnabled = enabled;
      _globalBotSlug    = enabled ? (bot.slug || '') : '';

      // If enabling this bot, uncheck any other bot's global checkbox visually
      if (enabled) {
        $('[id^="globalbot-"]').not($cb).prop('checked', false);
      }

      // Send to server (persists to WP options)
      updateBot(id, { _global_bot_enabled: enabled });
    });

    // Flechas plantillas
    $(document).on('click.aichat', '.aichat-tpl-arrow', function(){
      const botId = $(this).data('bot');
      if($(this).hasClass('up')) scrollTpl(botId,'up'); else scrollTpl(botId,'down');
    });
    // Scroll manual lista plantillas
    $(document).on('scroll.aichat', '.aichat-tpl-list', function(){
      const m = this.id.match(/tpl-list-(\d+)/); if(m) updateTplScroll(m[1]);
    });
  }

  function updateArrows(){
    const rail = document.getElementById('aichat-tab-strip');
    const $prev = $('#aichat-tabs-prev');
    const $next = $('#aichat-tabs-next');
    if (!rail) { $prev.hide(); $next.hide(); return; }
    const canL = rail.scrollLeft > 5;
    const canR = (rail.scrollWidth - rail.clientWidth - rail.scrollLeft) > 5;
    $prev.toggle(canL);
    $next.toggle(canR);
  }

  // ===== Avatar strip arrows helpers =====
  function updateAvaScroll(botId){
    const list = document.getElementById(`ava-strip-${botId}`);
    if(!list) return;
    const leftBtn = document.querySelector(`.aichat-ava-arrow.left[data-bot="${botId}"]`);
    const rightBtn = document.querySelector(`.aichat-ava-arrow.right[data-bot="${botId}"]`);
    if(!leftBtn || !rightBtn) return;
    const maxScroll = list.scrollWidth - list.clientWidth;
    const pos = list.scrollLeft;
    leftBtn.disabled = pos <= 0;
    rightBtn.disabled = pos >= (maxScroll - 2);
  }

  // ===== Template list scroll helpers (no inline styles) =====
  function updateTplScroll(botId){
    const list = document.getElementById(`tpl-list-${botId}`);
    if(!list) return;
    const up = document.querySelector(`.aichat-tpl-arrow.up[data-bot="${botId}"]`);
    const down = document.querySelector(`.aichat-tpl-arrow.down[data-bot="${botId}"]`);
    if(!up||!down) return;
    const maxScroll = list.scrollHeight - list.clientHeight;
    const pos = list.scrollTop;
    up.disabled = pos <= 0;
    down.disabled = pos >= (maxScroll - 2);
    if(maxScroll <= 0){ up.classList.add('aichat-hidden'); down.classList.add('aichat-hidden'); }
    else { up.classList.remove('aichat-hidden'); down.classList.remove('aichat-hidden'); }
  }

  function scrollTpl(botId, dir){
    const list = document.getElementById(`tpl-list-${botId}`);
    if(!list) return;
    const item = list.querySelector('.aichat-tpl-item');
    const step = item ? (item.getBoundingClientRect().height + 4) * 2 : 100;
    list.scrollBy({ top: (dir==='up'?-step:step), behavior:'smooth'});
    setTimeout(()=> updateTplScroll(botId), 240);
  }

  // ------------- Init -------------------
  $(function(){
    bindEvents();
    loadBots();
  });

})(jQuery);
