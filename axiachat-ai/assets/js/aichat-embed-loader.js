/* AI Chat Embed Loader
 * Minimal external embed script.
 * Usage (external site):
 * <div id="aichat-embed" data-bot="default"></div>
 * <script async src="https://TU-DOMINIO/wp-content/plugins/axiachat-ai/assets/js/aichat-embed-loader.js"></script>
 */
(function(){
  'use strict';

  var ORIGIN = (function(){
    // Detect script src to infer plugin base automatically
    var current = document.currentScript || (function(){
      var scripts = document.getElementsByTagName('script');
      return scripts[scripts.length-1];
    })();
    if (!current || !current.src) return '';
    // Expected: https://domain/.../assets/js/aichat-embed-loader.js
    try {
      var u = new URL(current.src);
      return u.origin;
    } catch(e){ return ''; }
  })();

  if (!ORIGIN) return;

  // Derive plugin base dynamically from script src path (removing trailing /assets/js/aichat-embed-loader.js)
  var PLUGIN_BASE = (function(){
    var current = document.currentScript || (function(){
      var scripts = document.getElementsByTagName('script');
      return scripts[scripts.length-1];
    })();
    if (!current || !current.src) return ORIGIN; // fallback
    try {
      var full = current.src.split('?')[0].split('#')[0];
      // Remove last 3 segments if they are assets/js/aichat-embed-loader.js
      var parts = full.replace(/^[a-z]+:\/\//i,'').split('/');
      // Find index inside parts of 'assets'
      var idxAssets = parts.lastIndexOf('assets');
      if (idxAssets > 0) {
        // plugin base is protocol + everything before 'assets'
        var proto = full.match(/^[a-z]+:\/\//i);
        var pre = parts.slice(0, idxAssets).join('/');
        return (proto ? proto[0] : 'https://') + pre; // protocol preserved
      }
      // Fallback: strip /assets/js/aichat-embed-loader.js by regex
      return full.replace(/\/assets\/js\/aichat-embed-loader\.js$/,'');
    } catch(e){ return ORIGIN; }
  })();
  // Determine AJAX and nonce endpoints without hardcoding wp-admin path.
  var currentScript = document.currentScript || (function(){ var s=document.getElementsByTagName('script'); return s[s.length-1]; })();
  var ds = currentScript ? currentScript.dataset : {};
  // Priority: data attributes -> query params -> fallback guess
  var qp = (function(){
    if (!currentScript || !currentScript.src) return {};
    try { var url = new URL(currentScript.src); var out={}; url.searchParams.forEach(function(v,k){out[k]=v;}); return out; } catch(e){ return {}; }
  })();
  function firstNonEmpty(){ for (var i=0;i<arguments.length;i++){ if (arguments[i]) return arguments[i]; } return ''; }
  var AJAX_URL = firstNonEmpty(ds.ajaxUrl, ds.ajax_url, qp.ajax_url);
  if (!AJAX_URL) { // last resort (may fail on custom setups but keeps backward compatibility)
    AJAX_URL = ORIGIN + '/wp-admin/admin-ajax.php';
  }
  var NONCE_ENDPOINT = firstNonEmpty(ds.nonceEndpoint, ds.nonce_endpoint, qp.nonce_endpoint, ORIGIN + '/?aichat_embed_nonce=1');

  // Select targets
  var targets = [].slice.call(document.querySelectorAll('#aichat-embed, [data-aichat-embed]'));
  if (!targets.length) return;

  // Spinner / pending placeholders
  targets.forEach(function(t){
    if (!t.getAttribute('data-aichat-embed')) t.setAttribute('data-aichat-embed','1');
    if (!t.innerHTML.trim()) {
      t.innerHTML = '<div style="font:14px system-ui,Arial;opacity:.7;">Loading AI Chat…</div>';
    }
  });

  // Fetch nonce + optional UI map once (pass bot slugs)
  function fetchNonce(slugs){
    var url = NONCE_ENDPOINT;
    if (slugs && slugs.length){
      url += (url.indexOf('?')===-1 ? '?' : '&') + 'bots=' + encodeURIComponent(slugs.join(','));
    }
    return fetch(url, { credentials: 'omit' })
      .then(function(r){ return r.json(); })
      .catch(function(){ return {}; });
  }

  // Ensure jQuery is available (load from CDN if missing)
  function ensureJQuery(onReady){
    if (window.jQuery) { onReady(); return; }
    var s = document.createElement('script');
    s.src = 'https://code.jquery.com/jquery-3.7.1.min.js';
    s.async = true;
    s.onload = function(){ onReady(); };
    s.onerror = function(){
      // Fallback to Google CDN
      var s2 = document.createElement('script');
      s2.src = 'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js';
      s2.async = true;
      s2.onload = onReady;
      s2.onerror = function(){
        console.error('[AIChat] Failed to load jQuery from CDN');
        onReady(); // Continue anyway, will fail gracefully
      };
      document.head.appendChild(s2);
    };
    document.head.appendChild(s);
  }

  // Ensure wp.i18n shim exists for external sites without WordPress
  function ensureWpI18nShim(){
    if (!window.wp) window.wp = {};
    if (!window.wp.i18n) {
      window.wp.i18n = {
        __: function(text) { return text; },
        _x: function(text) { return text; },
        _n: function(single, plural, number) { return number === 1 ? single : plural; },
        _nx: function(single, plural, number) { return number === 1 ? single : plural; },
        sprintf: function(fmt) {
          var args = Array.prototype.slice.call(arguments, 1);
          var idx = 0;
          return fmt.replace(/%(\d+\$)?s/g, function(match, pos) {
            if (pos) {
              var i = parseInt(pos, 10) - 1;
              return args[i] !== undefined ? args[i] : '';
            }
            return args[idx++] !== undefined ? args[idx - 1] : '';
          });
        }
      };
    }
  }

  function ensureCore(onReady){
    if (window.__AIChatCoreLoaded) { onReady(); return; }
    // First ensure jQuery, then wp.i18n shim, then load core
    ensureJQuery(function(){
      ensureWpI18nShim();
      var s = document.createElement('script');
      s.src = PLUGIN_BASE + '/assets/js/aichat-frontend.js';
      s.async = true;
      s.onload = function(){ window.__AIChatCoreLoaded = true; onReady(); };
      s.onerror = function(){ console.error('[AIChat] Failed to load frontend core'); };
      document.head.appendChild(s);
    });
  }

  // Shadow DOM + roots queue
  window.AIChatEmbedRoots = window.AIChatEmbedRoots || [];

  // Collect unique bot slugs
  var botSlugs = Array.from(new Set(targets.map(function(t){ return t.getAttribute('data-bot')||'default'; })));
  fetchNonce(botSlugs).then(function(data){
    if (!data || !data.nonce){
      targets.forEach(function(t){ t.innerHTML = '<div style="color:#b00;font:14px system-ui;">Embed error (nonce)</div>'; });
      return;
    }

    // Prepare global vars once
    window.AIChatVars = window.AIChatVars || {};
    window.AIChatVars.ajax_url = AJAX_URL;
    window.AIChatVars.nonce    = data.nonce;
    if (typeof window.AIChatVars.page_id === 'undefined') window.AIChatVars.page_id = 0;

    var uiMap = (data && data.ui) ? data.ui : {};
    targets.forEach(function(t){
        var bot = t.getAttribute('data-bot') || 'default';
        var cfg = uiMap[bot] || null;
        try {
          var shadow = t.attachShadow ? t.attachShadow({mode:'open'}) : null;
          var host = shadow || t; // fallback without shadow
          var link = document.createElement('link');
          link.rel = 'stylesheet';
          link.href = PLUGIN_BASE + '/assets/css/aichat-frontend.css';
          host.appendChild(link);
          var wrap = document.createElement('div');
          wrap.className = 'aichat-widget';
          wrap.setAttribute('data-bot', bot);
          if (cfg){
            // Basic config
            if (cfg.type) wrap.setAttribute('data-type', cfg.type);
            if (cfg.title) wrap.setAttribute('data-title', cfg.title);
            if (cfg.role) wrap.setAttribute('data-role', cfg.role);
            if (cfg.color) wrap.setAttribute('data-color', cfg.color);
            if (cfg.position) wrap.setAttribute('data-position', cfg.position);
            wrap.setAttribute('data-layout', 'floating'); // Embeds are always floating
            if (cfg.placeholder) wrap.setAttribute('data-placeholder', cfg.placeholder);
            if (cfg.start_sentence) wrap.setAttribute('data-start-sentence', cfg.start_sentence);
            if (cfg.button_send) wrap.setAttribute('data-button-send', cfg.button_send);
            // Dimensions
            if (cfg.width) wrap.setAttribute('data-width', cfg.width);
            if (cfg.height) wrap.setAttribute('data-height', cfg.height);
            // Avatar
            if (cfg.avatar_enabled) wrap.setAttribute('data-avatar-enabled', '1');
            if (cfg.avatar_url) wrap.setAttribute('data-avatar-url', cfg.avatar_url);
            // Window controls
            if (cfg.closable) wrap.setAttribute('data-closable', '1');
            if (cfg.minimizable) wrap.setAttribute('data-minimizable', '1');
            if (cfg.draggable) wrap.setAttribute('data-draggable', '1');
            if (cfg.minimized_default) wrap.setAttribute('data-minimized-default', '1');
            if (cfg.superminimized_default) wrap.setAttribute('data-superminimized-default', '1');
            if (cfg.avatar_bubble) wrap.setAttribute('data-avatar-bubble', '1');
            // Suggestions / Next Options
            if (cfg.suggestions_enabled) wrap.setAttribute('data-suggestions-enabled', '1');
            if (cfg.suggestions_count) wrap.setAttribute('data-suggestions-count', cfg.suggestions_count);
            if (cfg.suggestions_bg) wrap.setAttribute('data-suggestions-bg', cfg.suggestions_bg);
            if (cfg.suggestions_text) wrap.setAttribute('data-suggestions-text', cfg.suggestions_text);
          }
          // Branding "Powered by" (global, not per-bot)
          var br = (data && data.branding) ? data.branding : null;
          if (br && br.text) {
            wrap.setAttribute('data-branding-text', br.text);
            if (br.url) wrap.setAttribute('data-branding-url', br.url);
            if (br.icon) wrap.setAttribute('data-branding-icon', br.icon);
          }
          host.appendChild(wrap);
          window.AIChatEmbedRoots.push(wrap);
          t.__aichatEmbedReady = true;
          t.innerHTML = '';
        } catch(e){
          t.innerHTML = '<div style="color:#b00;font:14px system-ui;">Shadow DOM unsupported</div>';
        }
    });
    ensureCore(function(){ /* Core will auto-init including our pushed roots with UI data attributes */ });
  });
})();
