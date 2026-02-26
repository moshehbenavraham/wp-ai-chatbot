const { __, sprintf } = wp.i18n;

(function($){
  'use strict';

  const WHEN_OPTIONS = [
    {value:'user_wants', label: __('User wants', 'axiachat-ai')},
    {value:'user_talks_about', label: __('User talks about', 'axiachat-ai')},
    {value:'user_asks_about', label: __('User asks about', 'axiachat-ai')},
    {value:'user_sentiment', label: __('User sentiment is', 'axiachat-ai')},
    {value:'phrase_contains', label: __('Phrase contains', 'axiachat-ai')},
    {value:'date_is', label: __('Date is', 'axiachat-ai')},
    {value:'is_holiday', label: __('Is holiday', 'axiachat-ai')},
    {value:'url_contains', label: __('Page URL contains', 'axiachat-ai')},
    {value:'custom', label: __('Other (custom)', 'axiachat-ai')}
  ];

  const ACTION_OPTIONS = [
    {value:'navigate', label: __('Navigate to', 'axiachat-ai')},
    {value:'say_exact', label: __('Say exact message', 'axiachat-ai')},
    {value:'always_include', label: __('Always include', 'axiachat-ai')},
    {value:'always_talk_about', label: __('Always talk about', 'axiachat-ai')},
    {value:'request_info', label: __('Request information', 'axiachat-ai')},
    {value:'send_email', label: __('Send email', 'axiachat-ai')},
    {value:'api_request', label: __('Send API request', 'axiachat-ai')},
    {value:'site_search', label: __('Site search', 'axiachat-ai')},
    {value:'list_articles', label: __('List articles', 'axiachat-ai')},
    {value:'book_appointment', label: __('Book an appointment', 'axiachat-ai')},
    {value:'knowledge_base', label: __('Answer from knowledge base', 'axiachat-ai')},
    {value:'enable_screen_share', label: __('Enable screen share', 'axiachat-ai')},
    {value:'push_notification', label: __('Send push notification', 'axiachat-ai')}
  ];

  const state = {
    rules: [],
    dirty: false,
    capabilities: {
      available: [], // array of {id,label,description,type:'macro'|'tool'}
      selected: [],
      loading: false,
      dirty: false,
      settings: {} // map capId -> { system_policy: string, ... }
    },
    testTools: {
      tools: {}, // id -> {name,description,schema}
      loaded: false,
      selected: '',
      args: {},
      running: false
    }
  };

  function render(){
    const $wrap = $('#aichat-tools-builder');
    if(!$wrap.length) return;
    $wrap.empty();

    if(state.rules.length === 0){
      const txtNo = __('No rules defined yet. Use the + button to create the first one.', 'axiachat-ai');
      $('<p class="description"/>').text(txtNo).appendTo($wrap);
    }

    state.rules.forEach((rule, idx)=>{
      const $card = $('<div class="aichat-rule card mb-4 border-0 shadow-sm"/>');
      const $body = $('<div class="card-body p-3"/>').appendTo($card);

      // WHEN block
      const $whenBlock = $('<div class="aichat-block when-block mb-3"/>').appendTo($body);
  $('<div class="aichat-block-title"/>').text(__('WHEN', 'axiachat-ai')).appendTo($whenBlock);
      const $conditionsContainer = $('<div class="conditions-container"/>').appendTo($whenBlock);

      rule.when.forEach((cond, cIdx)=>{
        $conditionsContainer.append(renderCondition(idx,cIdx,cond));
      });

      const $addCondBtn = $('<button type="button" class="button button-small aichat-add-condition"><span class="dashicons dashicons-plus"></span></button>');
      $addCondBtn.on('click',()=>{ addCondition(idx); });
      $whenBlock.append($addCondBtn);

      // ACTION block
      const $actionBlock = $('<div class="aichat-block action-block mb-3"/>').appendTo($body);
  $('<div class="aichat-block-title"/>').text(__('DO', 'axiachat-ai')).appendTo($actionBlock);
      const $actionsContainer = $('<div class="actions-container"/>').appendTo($actionBlock);
      rule.actions.forEach((act,aIdx)=>{
        $actionsContainer.append(renderAction(idx,aIdx,act));
      });
      const $addActBtn = $('<button type="button" class="button button-small aichat-add-action"><span class="dashicons dashicons-plus"></span></button>');
      $addActBtn.on('click',()=>{ addAction(idx); });
      $actionBlock.append($addActBtn);

      // Footer (delete rule)
      const $footer = $('<div class="text-end pt-2"/>');
  const $del = $('<button type="button" class="button button-secondary"/>').text(__('Delete rule', 'axiachat-ai')).on('click',()=>{ deleteRule(idx); });
      $footer.append($del);
      $body.append($footer);

      $wrap.append($card);
    });
  }

  function renderCondition(ruleIdx, condIdx, cond){
    const c = cond || { type:'user_wants', value:'' };
    const $row = $('<div class="row g-2 align-items-center mb-2 condition-row"/>');
    const $col1 = $('<div class="col-md-5"/>').appendTo($row);
    const $sel = $('<select class="form-select form-select-sm" />').appendTo($col1);
    WHEN_OPTIONS.forEach(o=>{ $('<option/>').val(o.value).text(o.label).appendTo($sel); });
    $sel.val(c.type);
    $sel.on('change',()=>{ state.rules[ruleIdx].when[condIdx].type = $sel.val(); markDirty(); });

    const $col2 = $('<div class="col-md-6"/>').appendTo($row);
  const $input = $('<input type="text" class="form-control form-control-sm"/>').attr('placeholder', __('value', 'axiachat-ai')).val(c.value).appendTo($col2);
    $input.on('input',()=>{ state.rules[ruleIdx].when[condIdx].value = $input.val(); markDirty(); });

    const $col3 = $('<div class="col-md-1 text-end"/>').appendTo($row);
    $('<button type="button" class="button button-small" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>')
      .on('click',()=>{ removeCondition(ruleIdx, condIdx); })
      .appendTo($col3);

    return $row;
  }

  function renderAction(ruleIdx, actIdx, act){
    const a = act || { type:'say_exact', params:{ text:'' } };
    const $row = $('<div class="row g-2 align-items-center mb-2 action-row"/>');
    const $col1 = $('<div class="col-md-5"/>').appendTo($row);
    const $sel = $('<select class="form-select form-select-sm" />').appendTo($col1);
    ACTION_OPTIONS.forEach(o=>{ $('<option/>').val(o.value).text(o.label).appendTo($sel); });
    $sel.val(a.type);
    $sel.on('change',()=>{ state.rules[ruleIdx].actions[actIdx].type = $sel.val(); ensureActionParams(ruleIdx,actIdx); markDirty(); render(); });

    const $col2 = $('<div class="col-md-6 action-extra"/>').appendTo($row);
    renderActionExtra($col2, ruleIdx, actIdx);

    const $col3 = $('<div class="col-md-1 text-end"/>').appendTo($row);
    $('<button type="button" class="button button-small" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>')
      .on('click',()=>{ removeAction(ruleIdx, actIdx); })
      .appendTo($col3);

    return $row;
  }

  function renderActionExtra($container, ruleIdx, actIdx){
    const act = state.rules[ruleIdx].actions[actIdx];
    $container.empty();
    switch(act.type){
      case 'say_exact':
        $('<input type="text" class="form-control form-control-sm"/>')
          .attr('placeholder', __('Text to say', 'axiachat-ai'))
          .val(act.params.text||'')
          .on('input', function(){ act.params.text = $(this).val(); markDirty(); })
          .appendTo($container);
        break;
      case 'navigate':
        $('<input type="url" class="form-control form-control-sm"/>')
          .attr('placeholder', __('https://...', 'axiachat-ai'))
          .val(act.params.url||'')
          .on('input', function(){ act.params.url = $(this).val(); markDirty(); })
          .appendTo($container);
        break;
      case 'send_email':
        const $email = $('<input type="email" class="form-control form-control-sm mb-1"/>')
          .attr('placeholder', __('recipient@domain.com', 'axiachat-ai'))
          .val(act.params.to||'')
          .on('input', function(){ act.params.to = $(this).val(); markDirty(); })
          .appendTo($container);
        const $msg = $('<textarea class="form-control form-control-sm" rows="2"/>')
          .attr('placeholder', __('Message', 'axiachat-ai'))
          .val(act.params.body||'')
          .on('input', function(){ act.params.body = $(this).val(); markDirty(); })
          .appendTo($container);
        break;
      case 'request_info':
        // Chips style: comma separated tokens => array
        $('<input type="text" class="form-control form-control-sm"/>')
          .attr('placeholder', __('Fields to request (e.g. phone,name)', 'axiachat-ai'))
          .val((act.params.fields||[]).join(','))
          .on('input', function(){ act.params.fields = $(this).val().split(',').map(s=>s.trim()).filter(Boolean); markDirty(); })
          .appendTo($container);
        break;
      default:
        $('<input type="text" class="form-control form-control-sm"/>')
          .attr('placeholder', __('Parameter', 'axiachat-ai'))
          .val(act.params.value||'')
          .on('input', function(){ act.params.value = $(this).val(); markDirty(); })
          .appendTo($container);
    }
  }

  function ensureActionParams(ruleIdx, actIdx){
    const act = state.rules[ruleIdx].actions[actIdx];
    if(!act.params) act.params = {};
    switch(act.type){
      case 'say_exact': if(!('text' in act.params)) act.params.text=''; break;
      case 'navigate': if(!('url' in act.params)) act.params.url=''; break;
      case 'send_email': if(!('to' in act.params)) act.params.to=''; if(!('body' in act.params)) act.params.body=''; break;
      case 'request_info': if(!('fields' in act.params)) act.params.fields=[]; break;
      default: if(!('value' in act.params)) act.params.value='';
    }
  }

  // Mutators
  function addRule(){ state.rules.push({ when:[{type:'user_wants',value:''}], actions:[{type:'say_exact', params:{text:''}}] }); markDirty(); render(); }
  function deleteRule(idx){ state.rules.splice(idx,1); markDirty(); render(); }
  function addCondition(ruleIdx){ state.rules[ruleIdx].when.push({type:'user_wants',value:''}); markDirty(); render(); }
  function removeCondition(ruleIdx, condIdx){ state.rules[ruleIdx].when.splice(condIdx,1); markDirty(); render(); }
  function addAction(ruleIdx){ state.rules[ruleIdx].actions.push({type:'say_exact',params:{text:''}}); markDirty(); render(); }
  function removeAction(ruleIdx, actIdx){ state.rules[ruleIdx].actions.splice(actIdx,1); markDirty(); render(); }

  function markDirty(){ state.dirty = true; $('#aichat-tools-save').prop('disabled', false); }

  function currentBot(){ return $('#aichat-tools-bot').val() || ''; }

  // ==== Capabilities (macros/tools) ====
  function renderCapabilities(){
    const $list = $('#aichat-capabilities-list');
    if(!$list.length) return;
    $list.empty();
    if(state.capabilities.loading){
      $('<div class="col-12"><em>' + __('Loading...', 'axiachat-ai') + '</em></div>').appendTo($list);
      return;
    }
    if(state.capabilities.available.length===0){
      $('<div class="col-12"><em>'+ __('No skills available. Register macros or tools.', 'axiachat-ai') +'</em></div>').appendTo($list);
      return;
    }
    state.capabilities.available.forEach(cap=>{
      const id = cap.id;
      const checked = state.capabilities.selected.includes(id);
      const $col = $('<div class="col-md-6 col-xl-4"/>' );
      const $card = $('<div class="aichat-cap border rounded p-2 h-100" style="background:#fff; border-color:#e2e8f0;"/>' ).appendTo($col);
      // Per-capability colored icons + friendly tooltips
      const capIconMap = {
        'appointment_booking':       { icon: 'bi-calendar-check-fill', color: '#0d6efd', tip: __('Lets your bot check availability and book appointments automatically','axiachat-ai') },
        'lead_capture':              { icon: 'bi-person-plus-fill',    color: '#198754', tip: __('Collects visitor contact details (name, email, phone) and saves them as leads','axiachat-ai') },
        'notifications_email_admin': { icon: 'bi-envelope-fill',       color: '#dc3545', tip: __('Sends an email notification to the site admin when triggered by the bot','axiachat-ai') },
        'notifications_email_client':{ icon: 'bi-envelope-paper-fill', color: '#6f42c1', tip: __('Sends an email to the visitor (e.g. confirmations or summaries)','axiachat-ai') },
        'web_search':                { icon: 'bi-globe2',              color: '#fd7e14', tip: __('Allows the bot to search the web for up-to-date information','axiachat-ai') },
        'human_handoff':             { icon: 'bi-headset',             color: '#0dcaf0', tip: __('Transfers the conversation to a human agent via live chat','axiachat-ai') }
      };
      const mapped = capIconMap[id];
      const icon  = mapped ? mapped.icon  : (cap.type==='macro' ? 'bi-layers' : 'bi-gear');
      const iconColor = mapped ? mapped.color : '';
      const tooltip = mapped && mapped.tip ? mapped.tip : (cap.description || '');
      const $top = $('<div class="d-flex align-items-start gap-2" />').appendTo($card);
      const $label = $('<label class="d-flex align-items-start gap-2 flex-grow-1" style="cursor:pointer;" />').attr('title', tooltip).appendTo($top);
      const $cb = $('<input type="checkbox" class="mt-1"/>' ).val(id).prop('checked',checked).appendTo($label);
      const $icon = $('<i class="bi '+icon+'" style="font-size:18px;'+(iconColor?' color:'+iconColor+';':'')+' " aria-hidden="true"></i>').appendTo($label);
      const $textBox = $('<div class="flex-grow-1"/>' ).appendTo($label);
      $('<div class="fw-semibold"/>' ).text(cap.label).appendTo($textBox);
      if(tooltip){ $('<div class="text-muted small"/>' ).text(tooltip).appendTo($textBox); }
      $cb.on('change', function(){
        const val = $(this).val();
        if($(this).is(':checked')){ if(!state.capabilities.selected.includes(val)) state.capabilities.selected.push(val); }
        else { state.capabilities.selected = state.capabilities.selected.filter(v=>v!==val); }
        state.capabilities.dirty = true;
        $('#aichat-capabilities-save').prop('disabled', false);
      });
      $list.append($col);
    });
  }

  function loadCapabilities(){
    state.capabilities.loading = true; renderCapabilities();
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_get_bot_tools', nonce:aichat_tools_ajax.nonce, bot: currentBot() },
      dataType:'json'
    }).done(res=>{
      state.capabilities.loading = false;
      if(res.success){
        const selected = res.data.selected || [];
        const list = [];
        if(res.data.macros && Object.keys(res.data.macros).length){
          Object.values(res.data.macros).forEach(m=>{
            list.push({ id:m.name, label:m.label||m.name, description:m.description||'', type:'macro' });
          });
        } else if (res.data.tools){
          Object.entries(res.data.tools).forEach(([id,def])=>{
            list.push({ id, label:(def.name||id), description:def.description||'', type:'tool' });
          });
        }
        state.capabilities.available = list;
        state.capabilities.selected = selected;
      } else {
        state.capabilities.available = [];
        state.capabilities.selected = [];
      }
      // Load per-capability settings then render UI
      loadCapabilitySettings().always(function(){
        renderCapabilities();
        $('#aichat-capabilities-save').prop('disabled', true);
        $('#aichat-capabilities-status').text('');
      });
    }).fail(()=>{
      state.capabilities.loading=false; state.capabilities.available=[]; renderCapabilities();
    });
  }

  // ==== Test Tools (Underlying tools) ====
  function loadTestTools(){
    const $sel = $('#aichat-testtool-select');
    if(!$sel.length) return;
    $sel.prop('disabled', true).empty().append($('<option/>').text('Loading tools...'));
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_list_all_tools', nonce:aichat_tools_ajax.nonce },
      dataType:'json'
    }).done(function(res){
      state.testTools.tools = res.success ? (res.data.tools||{}) : {};
      state.testTools.loaded = true;
      renderTestToolsSelect();
    }).fail(function(){ state.testTools.tools={}; state.testTools.loaded=true; renderTestToolsSelect(); });
  }

  function renderTestToolsSelect(){
    const $sel = $('#aichat-testtool-select'); if(!$sel.length) return;
    $sel.empty();
    const ids = Object.keys(state.testTools.tools);
    if(ids.length===0){ $sel.append($('<option/>').text('No tools available')); $sel.prop('disabled', true); return; }
    $sel.append($('<option/>').attr('value','').text('Select a tool...'));
    ids.sort().forEach(id=>{ const def = state.testTools.tools[id]; $sel.append($('<option/>').attr('value',id).text(def.name||id)); });
    $sel.prop('disabled', false);
  }

  function renderTestToolDetails(){
    const id = state.testTools.selected; const def = state.testTools.tools[id] || null;
    const $desc = $('#aichat-testtool-desc'); const $form = $('#aichat-testtool-form');
    const $run = $('#aichat-testtool-run');
    $desc.text(''); $form.empty(); $run.prop('disabled', true);
    if(!def) return;
    if(def.description) $desc.text(def.description);
    const schema = def.schema || {};
    if((schema.type||'') !== 'object'){ $('<div class="text-muted"/>').text('This tool has no parameter object.').appendTo($form); $run.prop('disabled', false); return; }
    const props = schema.properties || {};
    const required = Array.isArray(schema.required) ? schema.required : [];
    state.testTools.args = {};
    Object.keys(props).forEach(key=>{
      const spec = props[key] || {}; const t = (spec.type||'string');
      const label = spec.description || key;
      const $row = $('<div class="mb-2"/>').appendTo($form);
      $('<label class="form-label fw-semibold"/>').text(label + (required.includes(key)?' *':'' )).appendTo($row);
      let $input;
      if(t==='integer' || t==='number'){
        $input = $('<input type="number" class="form-control"/>');
      } else if (t==='boolean'){
        $input = $('<select class="form-select"/>').append('<option value="false">false</option><option value="true">true</option>');
      } else if (t==='object'){
        $input = $('<textarea class="form-control" rows="3"/>').attr('placeholder','{"key":"value"}');
      } else if (t==='array'){
        $input = $('<textarea class="form-control" rows="2"/>').attr('placeholder','[1,2,3]');
      } else {
        $input = $('<input type="text" class="form-control"/>' );
        // Friendly placeholders for common datetime fields
        const lowerKey = String(key).toLowerCase();
        if (lowerKey === 'from' || lowerKey === 'to' || lowerKey === 'start'){
          $input.attr('placeholder','YYYY-MM-DD HH:MM:SS');
          let hint = 'Format: Y-m-d H:i:s (site local time). You can also use Y-m-d.';
          if (lowerKey === 'from') hint += ' Default: now.';
          if (lowerKey === 'to') hint += ' Default: now + 7 days.';
          $('<div class="form-text text-muted small"/>')
            .text(hint)
            .appendTo($row);
        }
      }
      $input.on('input change', function(){ captureArgValue(key, t, $(this).val()); });
      $row.append($input);
    });
    $run.prop('disabled', false);
  }

  function captureArgValue(key, type, raw){
    let val = raw;
    try{
      if(type==='integer'){ val = parseInt(raw,10); if(isNaN(val)) val = 0; }
      else if(type==='number'){ val = parseFloat(raw); if(isNaN(val)) val = 0; }
      else if(type==='boolean'){ val = String(raw) === 'true'; }
      else if(type==='object' || type==='array'){ val = raw ? JSON.parse(raw) : (type==='object'? {} : []); }
      else { val = String(raw||''); }
    }catch(e){ /* ignore parse errors until submit */ }
    state.testTools.args[key] = val;
  }

  function runTestTool(){
    const id = state.testTools.selected; if(!id) return;
    const $status = $('#aichat-testtool-status'); const $result = $('#aichat-testtool-result');
    $status.text('Running...'); $result.text('');
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_run_tool', nonce:aichat_tools_ajax.nonce, tool: id, args: JSON.stringify(state.testTools.args||{}) },
      dataType:'json'
    }).done(function(res){
      $status.text('');
      if(res.success){
        const out = (res.data && typeof res.data === 'object') ? res.data : res;
        $result.text(JSON.stringify(out, null, 2));
      } else {
        $result.text(JSON.stringify({ok:false,error:'ajax_failed'}, null, 2));
      }
    }).fail(function(){ $status.text(''); $result.text(JSON.stringify({ok:false,error:'network'}, null, 2)); });
  }

  function loadCapabilitySettings(){
    const dfd = $.Deferred();
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_get_capability_settings', nonce:aichat_tools_ajax.nonce, bot: currentBot() },
      dataType:'json'
    }).done(function(res){
      if(res.success){ state.capabilities.settings = res.data.settings || {}; }
      else { state.capabilities.settings = {}; }
      dfd.resolve();
    }).fail(function(){ state.capabilities.settings = {}; dfd.resolve(); });
    return dfd.promise();
  }



  function saveCapabilities(){
    $('#aichat-capabilities-save').prop('disabled', true).text(window.aichat_tools_i18n?.caps_saving || 'Saving capabilities...');
    $('#aichat-capabilities-status').text('');
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_save_bot_tools', nonce:aichat_tools_ajax.nonce, bot: currentBot(), selected: JSON.stringify(state.capabilities.selected) },
      dataType:'json'
    }).done(res=>{
      if(res.success){
        state.capabilities.dirty=false;
        $('#aichat-capabilities-save').text(window.aichat_tools_i18n?.caps_saved || 'Capabilities saved');
        setTimeout(()=>{ $('#aichat-capabilities-save').text(window.aichat_tools_i18n?.caps_save || 'Save Capabilities'); }, 1400);
      } else {
        $('#aichat-capabilities-save').text(window.aichat_tools_i18n?.caps_error || 'Error saving capabilities');
      }
    }).fail(()=>{
      $('#aichat-capabilities-save').text(window.aichat_tools_i18n?.caps_error || 'Error saving capabilities');
    }).always(()=>{
      if(state.capabilities.dirty) $('#aichat-capabilities-save').prop('disabled', false);
    });
  }

  function loadRules(){
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_get_rules', nonce: aichat_tools_ajax.nonce, bot: currentBot() },
      dataType:'json'
    }).done(res=>{
      if(res.success){ state.rules = res.data.rules || []; } else { state.rules=[]; }
      render();
    }).fail(()=>{ console.warn('Failed to load rules'); });
  }

  function saveRules(){
  $('#aichat-tools-save').prop('disabled', true).text((window.aichat_tools_i18n||{}).saving || 'Saving...');
    $.ajax({
      url: aichat_tools_ajax.ajax_url,
      method: 'POST',
      data: { action:'aichat_tools_save_rules', nonce:aichat_tools_ajax.nonce, bot: currentBot(), rules: JSON.stringify(state.rules) },
      dataType:'json'
    }).done(res=>{
      if(res.success){ state.dirty=false; $('#aichat-tools-save').text((window.aichat_tools_i18n||{}).saved || 'Saved').delay(1200).queue(function(n){ $(this).text((window.aichat_tools_i18n||{}).save || 'Save'); n(); }); }
      else { $('#aichat-tools-save').text((window.aichat_tools_i18n||{}).error || 'Error'); }
    }).fail(()=>{ $('#aichat-tools-save').text((window.aichat_tools_i18n||{}).error || 'Error'); })
      .always(()=>{ if(state.dirty) $('#aichat-tools-save').prop('disabled', false); });
  }

  $(document).ready(function(){
    if($('#aichat-tools-builder').length){
      loadRules();
      loadCapabilities();
      loadTestTools();
      $('#aichat-tools-add-rule').on('click', addRule);
      $('#aichat-tools-save').on('click', saveRules);
      $('#aichat-tools-bot').on('change', function(){ state.rules=[]; render(); loadRules(); loadCapabilities(); });
      $('#aichat-capabilities-save').on('click', saveCapabilities);
      $(document).on('change', '#aichat-testtool-select', function(){ state.testTools.selected = $(this).val(); renderTestToolDetails(); });
      $(document).on('click', '#aichat-testtool-run', runTestTool);
    }
  });

})(jQuery);
