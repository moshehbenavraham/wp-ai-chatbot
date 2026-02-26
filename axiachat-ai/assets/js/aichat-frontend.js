/**
 * AI Chat — Frontend (multi-instancia, siempre flotante)
 * - Lee config desde data-attrs: position, color, width, height, title, placeholder
 * - Siempre usa layout flotante (no depende de ui_layout en BD)
 * - Origen del bot: data-bot (shortcode/core) o AIChatGlobal.bot_slug (global)
 * - AJAX: usa AIChatVars.ajax_url y AIChatVars.nonce
 *
 * Requiere jQuery y que el PHP haya hecho wp_localize_script('AIChatVars', ...).
 */
'use strict';

(function($){
  const { __, sprintf } = wp.i18n;
  // Asegura que AIChatGDPR exista incluso si la localización PHP falló (caché, orden, minifier, etc.)
  if (typeof window !== 'undefined' && typeof window.AIChatGDPR === 'undefined') {
    window.AIChatGDPR = { enabled: 0, text: '', button: '', cookie: 'aichat_gdpr_ok', _auto: true };
  }
  // Fallback temprano / normalización: rellena valores vacíos y añade trazas debug si ?aichat_debug=1
  (function(){
    if (typeof window === 'undefined' || !window.AIChatGDPR) return;
    var dbg = /(?:\?|&)aichat_debug=1(?:&|$)/.test(window.location.search);
    try {
      var changed = false;
      if (!window.AIChatGDPR.text || String(window.AIChatGDPR.text).trim() === '') {
        window.AIChatGDPR.text = __('By using this chatbot, you agree to the recording and processing of your data for improving our services.', 'axiachat-ai');
        changed = true;
      }
      if (!window.AIChatGDPR.button || String(window.AIChatGDPR.button).trim() === '') {
        window.AIChatGDPR.button = __('I understand', 'axiachat-ai');
        changed = true;
      }
      if (dbg) {
        console.log('[AIChat][GDPR] init object', window.AIChatGDPR, 'changedDefaults=', changed);
      }
    } catch(e) { if (dbg) console.warn('[AIChat][GDPR] fallback error', e); }
  })();
  // Activa logs si añades ?aichat_debug=1 a la URL
  var DEBUG = /(?:\?|&)aichat_debug=1(?:&|$)/.test(window.location.search);
  if (DEBUG) {
    console.log('[AIChat] JS loaded. jQuery=', typeof $, 'AIChatVars=', typeof window.AIChatVars, 'AIChatGlobal=', typeof window.AIChatGlobal);
  }

  $(function(){
    // ---------- 1) Comprobaciones iniciales ----------
    if (typeof AIChatVars === 'undefined' || !AIChatVars || !AIChatVars.ajax_url) {
      console.error('[AIChat] AIChatVars no definido o sin ajax_url.');
      return;
    }

    // ---------- 2) Localiza instancias ----------
    // Soporte de inserciones externas vía AIChatEmbedRoots (array de nodos DOM)
    if (Array.isArray(window.AIChatEmbedRoots) && window.AIChatEmbedRoots.length) {
      try {
        window.AIChatEmbedRoots.forEach(function(node){
          if (node && node.classList && !node.classList.contains('aichat-widget')) {
            node.classList.add('aichat-widget');
          }
        });
      } catch(e){ if (DEBUG) console.warn('[AIChat] fallo al normalizar AIChatEmbedRoots', e); }
    }
    // Captura instancias visibles en el DOM principal
    var $instances = $('.aichat-widget');
    // Añade explícitamente instancias creadas en Shadow DOM (no aparecen en el selector global)
    if (Array.isArray(window.AIChatEmbedRoots) && window.AIChatEmbedRoots.length) {
      window.AIChatEmbedRoots.forEach(function(node){
        if (!node) return;
        // Ya añadimos la clase antes; ahora aseguramos que se incluya en la lista a inicializar
        if (!$instances.filter(function(){ return this === node; }).length) {
          // Empuja el nodo manualmente a la colección de trabajo creando un wrapper jQuery
          $instances = $instances.add(node);
        }
      });
    }
    if ($instances.length === 0) {
      var $legacy = $('#aichat-widget'); // compat muy antigua
      if ($legacy.length) {
        $legacy.addClass('aichat-widget');
        $instances = $('.aichat-widget');
      }
    }
    if (DEBUG) console.log('[AIChat] instancias encontradas:', $instances.length);
  if ($instances.length === 0) return;

    var uidCounter = 0;

    // ---------- 3) Inicializa cada instancia ----------
    $instances.each(function(idx){
      var $root = $(this);

      // Evita doble init si el script se ejecuta dos veces
      if ($root.data('aichatReady')) {
        if (DEBUG) console.log('[AIChat] instancia ya inicializada idx=', idx);
        return;
      }
      $root.data('aichatReady', 1);

      // Bot slug: data-bot → AIChatGlobal.bot_slug → null
      var botSlug = $root.data('bot') || (window.AIChatGlobal && AIChatGlobal.bot_slug) || null;

      // Datos de UI desde data-attrs (no hay ui_layout en BD; el bot es SIEMPRE flotante)
      var botType   = String($root.data('type') || 'text'); // 'text' | 'voice_text'
      var rawPos   = (String($root.data('position') || '')).toLowerCase();
      var position = normPos(rawPos || 'bottom-right');  // 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left'
      var color     = String($root.data('color') || '');
      var width     = parseInt($root.data('width'), 10)  || 0;
      var mHeight   = parseInt($root.data('height'), 10) || 0;
      var title       = $root.data('title') || __('AI Chat', 'axiachat-ai');
      var placeholder  = $root.data('placeholder') || __('Write your question...', 'axiachat-ai');
  var startSentence = $root.data('startSentence') || '';
  var role         = $root.data('role') || __('AI Agent Specialist', 'axiachat-ai');
      var sendLabel    = $root.data('buttonSend') || __('Send', 'axiachat-ai'); // nuevo
      // Ventana
      var closable      = !!parseInt($root.data('closable') || 0, 10);
      var minimizable   = !!parseInt($root.data('minimizable') || 0, 10);
      var draggable     = !!parseInt($root.data('draggable') || 0, 10);
      var minimizedDefault = !!parseInt($root.data('minimizedDefault') || 0, 10);
  var superMinimizedDefault = !!parseInt($root.data('superminimizedDefault') || 0, 10);
      var avatarBubble = !!parseInt($root.data('avatarBubble') || 0, 10);
      var cssForce = !!parseInt($root.data('cssForce') || 0, 10);
      if (cssForce) $root.addClass('css-forced');


      // Avatar dataset
      var avatarEnabled = !!parseInt($root.data('avatarEnabled') || 0, 10);
      var avatarUrl     = String($root.data('avatarUrl') || '');

      // Suggestions / Next actions (chips)
      var suggestionsEnabled = !!parseInt($root.data('suggestionsEnabled') || 0, 10);
      var suggestionsCount   = parseInt($root.data('suggestionsCount'), 10) || 3;
      if (suggestionsCount < 1) suggestionsCount = 1;
      if (suggestionsCount > 6) suggestionsCount = 6;
      var suggestionsBg       = String($root.data('suggestionsBg') || '');
      var suggestionsText     = String($root.data('suggestionsText') || '');

      // History persistence (default ON)
      var historyPersistence = parseInt($root.data('historyPersistence') ?? 1, 10);
      if (isNaN(historyPersistence)) historyPersistence = 1;

      $root.data('aichatSuggestionsCfg', {
        enabled: suggestionsEnabled,
        count: suggestionsCount,
        bg: suggestionsBg,
        text: suggestionsText
      });

      // Branding "Powered by X" (add-on Agency)
      var brandingText = String($root.data('brandingText') || '');
      var brandingUrl  = String($root.data('brandingUrl') || '');
      var brandingIcon = String($root.data('brandingIcon') || '');

      if (DEBUG) console.log('[AIChat] init idx=', idx, { botSlug, rawPos, position, color, width, mHeight, title, avatarEnabled, avatarUrl });

      // Si no hay bot, muestra aviso para no romper layout
      if (!botSlug) {
        $root.html(
          '<div class="aichat-inner">' +
            '<div class="aichat-header">'+ escapeHtml(title) +'</div>' +
            '<div class="aichat-messages"></div>' +
            '<div class="aichat-inputbar"><em>' + __('Bot not configured.', 'axiachat-ai') + '</em></div>' +
          '</div>'
        );
        console.warn('[AIChat] Bot not configured idx=', idx); 
        return;
      }

      // Construye UI si el contenedor está vacío (o no trae .aichat-inner)
      if ($root.children().length === 0 || !$root.find('.aichat-inner').length) {
        var uid = 'aichat-' + (++uidCounter);

        var headerCls = 'aichat-header' + (avatarEnabled && avatarUrl ? ' with-avatar' : '');
        var headerHtml;
        if (avatarEnabled && avatarUrl) {
          // Avatar + nombre (arriba) + rol (debajo)
          headerHtml =
            '<img class="aichat-avatar-badge" src="'+ escapeHtml(avatarUrl) +'" alt="'+ escapeHtml(title) +'">' +
            '<span class="aichat-header-text">'+
              '<span class="aichat-header-title">'+ escapeHtml(title) +'</span>'+
              '<span class="aichat-header-subtitle">'+ escapeHtml(role) +'</span>'+
            '</span>';
        } else {
          // Nombre + rol en dos líneas
          headerHtml = 
            '<span class="aichat-header-text">' +
              '<span class="aichat-header-title">'+ escapeHtml(title) +'</span>'+
              '<span class="aichat-header-subtitle">'+ escapeHtml(role) +'</span>'+
            '</span>';
        }
       // Controles (derecha)
       var controlsHtml = '<div class="aichat-header-controls">';
       if (minimizable) controlsHtml += '<button type="button" class="aichat-btn aichat-btn-minimize" aria-label="' + __('Minimize', 'axiachat-ai') + '">−</button>';
       // Maximize button (toggle full-screen-like inside viewport)
       controlsHtml += '<button type="button" class="aichat-btn aichat-btn-maximize" aria-label="' + __('Maximize', 'axiachat-ai') + '" aria-pressed="false">'+
         '<svg class="aichat-ico-max" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M3 5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5zm2-1a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5z"/></svg>'+
         '</button>';
       if (closable)    controlsHtml += '<button type="button" class="aichat-btn aichat-btn-close" aria-label="' + __('Close', 'axiachat-ai') + '">×</button>';
       controlsHtml += '</div>';
       headerHtml += controlsHtml;

        var html =
          '<div class="aichat-inner" data-uid="'+uid+'">' +
            '<div class="'+headerCls+'" id="'+uid+'-header" aria-label="'+ escapeHtml(title) +'">'+ headerHtml +'</div>' +
            '<div class="aichat-messages" id="'+uid+'-messages" aria-live="polite">' +
              '<button type="button" class="aichat-newconv-btn" style="display:none">+ ' + __('New', 'axiachat-ai') + '</button>' +
            '</div>' +
            '<div class="aichat-inputbar">' +
              '<input type="text" class="aichat-input" id="'+uid+'-input" placeholder="'+ escapeHtml(placeholder) +'" autocomplete="off" />' +
              (botType==='voice_text'
                ? '<button type="button" class="aichat-mic" id="'+uid+'-mic" aria-pressed="false" aria-label="' + __('Start voice input', 'axiachat-ai') + '">'+
                    '<svg class="icon-mic" viewBox="0 0 16 16" aria-hidden="true">'+
                      '<path fill="currentColor" d="M8 11a3 3 0 0 0 3-3V4a3 3 0 1 0-6 0v4a3 3 0 0 0 3 3z"/>'+
                      '<path fill="currentColor" d="M5 8a.5.5 0 0 1 1 0 2 2 0 1 0 4 0 .5.5 0 0 1 1 0 3 3 0 0 1-2.5 2.959V13h1.5a.5.5 0 0 1 0 1H6a.5.5 0 0 1 0-1h1.5v-2.041A3 3 0 0 1 5 8z"/>'+
                    '</svg>'+
                    '<svg class="icon-stop" viewBox="0 0 16 16" aria-hidden="true">'+
                      '<rect x="4" y="4" width="8" height="8" fill="currentColor"></rect>'+
                    '</svg>'+
                    '<span class="screen-reader-text" style="position:absolute;left:-9999px;">' + __('Mic', 'axiachat-ai') + '</span>'+
                  '</button>'
                : '') +
              '<button type="button" class="aichat-send" id="'+uid+'-send">'+ escapeHtml(sendLabel) +'</button>' +
            '</div>' +
            // Branding "Powered by X" si está configurado
            (brandingText
              ? '<div class="aichat-branding">' +
                  __('Powered by', 'axiachat-ai') + ' ' +
                  (brandingIcon ? '<span class="aichat-branding-icon">'+ escapeHtml(brandingIcon) +'</span> ' : '') +
                  (brandingUrl
                    ? '<a href="'+ escapeHtml(brandingUrl) +'" target="_blank" rel="noopener noreferrer">'+ escapeHtml(brandingText) +'</a>'
                    : '<span>'+ escapeHtml(brandingText) +'</span>') +
                '</div>'
              : '') +
          '</div>';

        $root.html(html);
        if (DEBUG) console.log('[AIChat] UI construida idx=', idx, 'uid=', uid);
      } else {
        if (DEBUG) console.log('[AIChat] usando UI existente idx=', idx);
      }

      // Referencias
      var $inner    = $root.find('.aichat-inner').first();
      var $messages = $inner.find('.aichat-messages').first();
      var $input    = $inner.find('.aichat-input').first();
      var $sendBtn  = $inner.find('.aichat-send').first();
  var $micBtn   = $inner.find('.aichat-mic').first();
  var $ttsStop  = $inner.find('.aichat-tts-stop').first();
  var $maxBtn   = $inner.find('.aichat-btn-maximize').first();

     // ===== GDPR CONSENT COMO MENSAJE =====
     // Nueva lógica: en lugar de overlay que tapa header, insertamos una "burbuja" dentro del área de mensajes
     // Mantiene la cabecera (arrastrar, minimizar, cerrar) operativa. Inputs bloqueados hasta aceptar.
     try {
       if (window.AIChatGDPR && parseInt(AIChatGDPR.enabled,10) === 1) {
         var consentCookie = AIChatGDPR.cookie || 'aichat_gdpr_ok';
         var hasConsent = document.cookie.indexOf(consentCookie+'=1') !== -1;
         if (!hasConsent) {
           // Bloquear inputs inmediatamente (aunque el widget esté minimizado)
           $input.prop('disabled', true).attr('aria-disabled','true');
           $sendBtn.prop('disabled', true).attr('aria-disabled','true');
           if ($micBtn.length) $micBtn.prop('disabled', true).attr('aria-disabled','true');

           // Función para inyectar el bloque GDPR si aún no existe
           function injectGDPRMessage(){
             if ($messages.find('.aichat-gdpr-consent').length) return; // ya insertado
             // Revalidar justo antes por si otro script ha modificado AIChatGDPR
             try {
               if (!AIChatGDPR.text || String(AIChatGDPR.text).trim()==='') {
                 AIChatGDPR.text = __('By using this chatbot, you agree to the recording and processing of your data for improving our services.', 'axiachat-ai');
               }
               if (!AIChatGDPR.button || String(AIChatGDPR.button).trim()==='') {
                 AIChatGDPR.button = __('I understand', 'axiachat-ai');
               }
             } catch(e){}
             var boxHtml = ''+
               '<div class="message aichat-gdpr-consent" role="group" aria-label="' + __('GDPR consent', 'axiachat-ai') + '">'+
                 '<div class="aichat-gdpr-text">'+ (AIChatGDPR.text || '') +'</div>'+
                 '<div class="aichat-gdpr-actions">'+
                   '<button type="button" class="aichat-gdpr-accept">'+ escapeHtml(AIChatGDPR.button || __('OK', 'axiachat-ai')) +'</button>'+
                 '</div>'+
               '</div>';
             $messages.append(boxHtml);
             // Scroll al final cuando se muestre
             $messages.scrollTop($messages[0].scrollHeight);
           }

           // Si no está minimizado ahora, lo insertamos ya; si está minimizado esperamos al primer maximize
           if (!$inner.hasClass('is-minimized')) {
             injectGDPRMessage();
           } else {
             // Escuchar cambio de minimizado
             $inner.on('click.aichatGDPR', '.aichat-btn-minimize', function(){
               // El toggle ocurre después del click; usamos setTimeout corto
               setTimeout(function(){
                 if (!$inner.hasClass('is-minimized')) {
                   injectGDPRMessage();
                   $inner.off('click.aichatGDPR', '.aichat-btn-minimize');
                 }
               }, 30);
             });
           }

           // Delegar aceptación (aunque el mensaje aún no se haya inyectado, se manejará tras inyección)
           $messages.on('click', '.aichat-gdpr-accept', function(){
             var expDays = 365;
             var maxAge = expDays*24*60*60;
             document.cookie = consentCookie + '=1; Max-Age='+maxAge+'; Path=/; SameSite=Lax';
             $messages.find('.aichat-gdpr-consent').remove();
             $input.prop('disabled', false).removeAttr('aria-disabled').focus();
             $sendBtn.prop('disabled', false).removeAttr('aria-disabled');
             if ($micBtn.length) $micBtn.prop('disabled', false).removeAttr('aria-disabled');
           });
         }
       }
     } catch(e){ if (DEBUG) console.warn('[AIChat][GDPR] error consent message', e); }

  // Sesión y carga de historial
     var sessionId = getOrCreateSessionId();
  // Carga historial y, si viene vacío, muestra mensaje de bienvenida (si definido)
  if (historyPersistence) {
    loadHistory($messages, botSlug, sessionId, startSentence);
  } else {
    // Sin persistencia: mostrar solo welcome si definido
    if (startSentence && String(startSentence).trim() !== '') {
      appendBot($messages, escapeHtml(startSentence));
      scrollToBottom($messages);
    }
  }

     // "+New" conversation button: show only when there are real user messages (not just welcome/bot greeting)
     var $newConvBtn = $messages.find('.aichat-newconv-btn');
     function toggleNewConvBtn(){
       var hasUserMessages = $messages.find('.user-message').length > 0;
       $newConvBtn.toggle(hasUserMessages);
     }
     // Observe message area for changes
     var convObserver = new MutationObserver(toggleNewConvBtn);
     convObserver.observe($messages[0], { childList: true });

     // New conversation button handler
     $inner.on('click', '.aichat-newconv-btn', function(e){
       e.preventDefault();
       // Clear messages from DOM
       $messages.empty();
       // Delete session cookie and create a new one
       document.cookie = 'aichat_sid=; Max-Age=0; Path=/; SameSite=Lax';
       sessionId = forceNewSessionId();
       // Remove limited state if any
       $root.removeData('aichatLimited').removeClass('aichat-limited');
       var $inputEl = $root.find('.aichat-input');
       var $sendEl  = $root.find('.aichat-send');
       var $micEl   = $root.find('.aichat-mic');
       $inputEl.prop('disabled', false).removeAttr('aria-disabled');
       $sendEl.prop('disabled', false).removeAttr('aria-disabled');
       if ($micEl.length) $micEl.prop('disabled', false).removeAttr('aria-disabled');
       // Show welcome message if configured
       if (startSentence && String(startSentence).trim()) {
         appendBot($messages, escapeHtml(startSentence));
       }
       // Hide the button (no messages now)
       $newConvBtn.hide();
       if (DEBUG) console.log('[AIChat] Conversation reset, new session:', sessionId);
     });

     // ----- Voz (STT/TTS) por instancia -----
     var recognition = null;
     var isMicActive = false;
     var supportsSTT = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
     var supportsTTS = !!window.speechSynthesis;
     var ttsActive = false;
     var ttsWasMicActive = false;
     var ttsCancelledByUser = false;

     // Crea el overlay TTS (una vez por widget)
     var $inner = $root.find('.aichat-inner');
     var $ttsOverlay = $inner.find('.aichat-tts-overlay');
     if (!$ttsOverlay.length) {
       $ttsOverlay = $(
         '<div class="aichat-tts-overlay" aria-hidden="true">'+
           '<button type="button" class="aichat-tts-overlay-btn" aria-label="' + __('Stop reading', 'axiachat-ai') + '">'+
             '<svg viewBox="0 0 16 16" aria-hidden="true"><rect x="4" y="4" width="8" height="8" fill="currentColor"/></svg>'+
           '</button>'+
         '</div>'
       );
       $inner.append($ttsOverlay);
     }
     // Click en overlay: solo cancela TTS, no toca el micro
     $ttsOverlay.off('click.aichat').on('click.aichat', function(e){
       e.preventDefault(); e.stopPropagation();
       ttsCancelledByUser = true;
       try { window.speechSynthesis.cancel(); } catch(e){}
       ttsActive = false;
       $ttsOverlay.removeClass('show').attr('aria-hidden','true');
     });

     // Pre-carga voces (algunos navegadores necesitan getVoices() para inicializar)
     if (supportsTTS) {
       try { window.speechSynthesis.getVoices(); } catch(e){}
     }

     if (botType === 'voice_text') {
       if (!supportsSTT) {
         // Oculta el botón si no hay STT
         if ($micBtn && $micBtn.length) $micBtn.hide();
       } else {
         var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
         recognition = new Rec();
         recognition.continuous = true;
         recognition.interimResults = false;
         recognition.lang = navigator.language || 'es-ES';
 
         recognition.onresult = function(event){
           var res = event.results[event.results.length - 1];
           var transcript = res[0].transcript || '';
           $input.val(transcript);
           if (res.isFinal) {
             $micBtn.trigger('click'); // detener grabación
             sendMessage($root, $messages, $input, $sendBtn, botSlug, voiceOpts, sessionId);
           }
         };
         recognition.onerror = function(){
           isMicActive = false;
           $micBtn.attr('aria-pressed','false').removeClass('is-recording').attr('aria-label', __('Start voice input', 'axiachat-ai'));
           if ($micBtn.length) { $micBtn.find('.icon-stop').hide(); $micBtn.find('.icon-mic').show(); }
         };
         recognition.onend = function(){
           isMicActive = false;
           $micBtn.attr('aria-pressed','false').removeClass('is-recording').attr('aria-label', __('Start voice input', 'axiachat-ai'));
           if ($micBtn.length) { $micBtn.find('.icon-stop').hide(); $micBtn.find('.icon-mic').show(); }
         };
 
         if ($micBtn && $micBtn.length) {
           $micBtn.on('click', function(){
             // aichatWarmupTTS();  // eliminado
             if (!isMicActive) {
               try {
                 recognition.start();
                 isMicActive = true;
                 $micBtn.attr('aria-pressed','true').addClass('is-recording').attr('aria-label', __('Stop voice input', 'axiachat-ai'));
                 $micBtn.find('.icon-mic').hide(); $micBtn.find('.icon-stop').show();
               } catch(e){}
             } else {
               try { recognition.stop(); } catch(e){}
               isMicActive = false;
               $micBtn.attr('aria-pressed','false').removeClass('is-recording').attr('aria-label', __('Start voice input', 'axiachat-ai'));
               $micBtn.find('.icon-stop').hide(); $micBtn.find('.icon-mic').show();
             }
           });
         }
       }
       // Si el navegador no soporta TTS oculta el botón de parar
       if (!supportsTTS && $ttsStop.length) $ttsStop.hide();
       if ($ttsStop.length) {
         $ttsStop.on('click', function(){
           if (!supportsTTS) return;
           try { window.speechSynthesis.cancel(); } catch(e){}
           ttsActive = false;
           $ttsStop.hide();
         });
       }
     }
 
     // Mic: alterna estado/ícono
     if ($micBtn && $micBtn.length) {
       $micBtn.on('click', function(){
         if (!isMicActive) {
           try { recognition.start(); } catch(e){}
           isMicActive = true;
           $micBtn.attr('aria-pressed','true').addClass('is-recording');
           // por si algún CSS externo interfiere
           $micBtn.find('.icon-mic').hide();
           $micBtn.find('.icon-stop').show();
         } else {
           try { recognition.stop(); } catch(e){}
           isMicActive = false;
           $micBtn.attr('aria-pressed','false').removeClass('is-recording');
           $micBtn.find('.icon-stop').hide();
           $micBtn.find('.icon-mic').show();
         }
       });
     }

     // Botón Stop (TTS): no reactivar micro al cancelar manualmente
     if ($ttsStop && $ttsStop.length) {
       $ttsStop.on('click', function(){
         ttsCancelledByUser = true;
         try { window.speechSynthesis.cancel(); } catch(e){}
         ttsActive = false;
         $ttsStop.hide();
       });
     }

     // TTS (no cambiar la lógica de voz que ya funciona)
     function speakResponse(text){
       if (!supportsTTS || !text) return;

       var plain = htmlToSpeechText(String(text));

       try {
         if (window.speechSynthesis.speaking || window.speechSynthesis.pending) {
           window.speechSynthesis.cancel();
         }
       } catch(e){}

       ttsWasMicActive = isMicActive;
       if (ttsWasMicActive && recognition) { try { recognition.stop(); } catch(e){} }

       var utter = new SpeechSynthesisUtterance(plain);
       utter.lang  = navigator.language || 'es-ES';
       utter.rate  = 1;
       utter.pitch = 1;

       // Usar overlay centrado (ocultar cualquier stop inline si existe)
       ttsActive = true;
       if ($ttsStop && $ttsStop.length) { $ttsStop.hide().off('click.aichat'); }
       if ($ttsOverlay && $ttsOverlay.length) {
         $ttsOverlay.addClass('show').attr('aria-hidden','false');
       }

       utter.onend = function(){
         ttsActive = false;
         if ($ttsOverlay && $ttsOverlay.length) {
           $ttsOverlay.removeClass('show').attr('aria-hidden','true');
         }
         // Solo reactivar micro si NO lo paró el usuario
         if (ttsWasMicActive && !ttsCancelledByUser && recognition) {
           try { recognition.start(); } catch(e){}
         }
         ttsWasMicActive = false;
         ttsCancelledByUser = false;
       };

       utter.onerror = function(){
         ttsActive = false;
         if ($ttsOverlay && $ttsOverlay.length) {
           $ttsOverlay.removeClass('show').attr('aria-hidden','true');
         }
         ttsWasMicActive = false;
         ttsCancelledByUser = false;
       };

       try { window.speechSynthesis.speak(utter); } catch(e){}
     }

     // Convierte HTML a texto para el TTS
     function htmlToSpeechText(html){
       var el = document.createElement('div');
       el.innerHTML = String(html);

       // Mejora pausas: <br> y cierre de <p> → punto y espacio
       el.querySelectorAll('br').forEach(function(br){
         br.replaceWith(document.createTextNode('. '));
       });
       el.querySelectorAll('p').forEach(function(p, idx, arr){
         if (p.lastChild && p.lastChild.nodeType === 3) {
           p.lastChild.textContent += (idx < arr.length - 1) ? '. ' : '';
         } else {
           p.appendChild(document.createTextNode(idx < arr.length - 1 ? '. ' : ''));
         }
       });

       var txt = el.textContent || el.innerText || '';
       return txt.replace(/\s+/g, ' ').trim();
     }
 
     var voiceOpts = (botType==='voice_text') ? {
       onBotResponse: function(text){ speakResponse(text); }
     } : null;

      // ---------- 4) Aplicar CONFIG de UI (siempre flotante) ----------
      $root.addClass('is-global'); // siempre flotante
      if (draggable) $root.addClass('is-draggable');
      // Posición → clases pos-*
      $root.removeClass('pos-bottom-right pos-bottom-left pos-top-right pos-top-left');
      $root.addClass('pos-' + position);

      // Size helpers: clamp width/height to min and viewport
      function applySizing(){
        try {
          var vw = window.innerWidth || document.documentElement.clientWidth || 1024;
          var vh = window.innerHeight || document.documentElement.clientHeight || 768;
          var pad = 20; // viewport padding used in CSS
          var minW = 300, minH = 300;
          var cfgW = width > 0 ? width : 0;
          var cfgH = mHeight > 0 ? mHeight : 0;

          // Width: clamp to [minW, vw - 2*pad]
          if (cfgW > 0) {
            var w = Math.max(minW, cfgW);
            w = Math.min(w, Math.max(minW, vw - pad*2));
            $root.css('width', w + 'px');
          }

          // Height: clamp to [minH, available]
          // Available message height = viewport - header - inputbar - margins
          if (!$root.hasClass('is-maximized') && cfgH > 0) {
            var headerH = $inner.find('.aichat-header').outerHeight() || 56;
            var inputH  = $inner.find('.aichat-inputbar').outerHeight() || 56;
            var chrome  = headerH + inputH + 24; // internal padding/margins
            var avail = Math.max(120, vh - chrome - pad);
            var h = Math.max(minH, cfgH);
            h = Math.min(h, avail);
            $messages.css('height', h + 'px');
            // keep original configured height in data for restore when exiting maximized
            $messages.data('origHeightPx', cfgH);
          }
        } catch(_){ /* noop */ }
      }

      // Initial sizing
      applySizing();

      // Color de tema
      if (color) {
        $inner.find('.aichat-header').css('background-color', color);
        $inner.find('.aichat-send').css('background-color', color);
      }

      // Estado inicial: usar estado guardado del usuario si existe, sino usar default del bot
      var _savedState = loadWidgetState(botSlug);
      if (_savedState) {
        // Aplicar estado guardado por el usuario (prevalece sobre config del bot)
        if (_savedState === 'minimized') {
          $inner.addClass('is-minimized');
        }
        // 'superminimized' se aplica más abajo tras construir el avatar
        // 'maximized' y 'open' no requieren clase extra aquí
        if (DEBUG) console.log('[AIChat] restored widget state:', _savedState, 'for bot:', botSlug);
      } else {
        // Sin estado guardado: usar defaults del bot
        if (minimizedDefault) $inner.addClass('is-minimized');
      }

      // Helper: build avatar + optional speech bubble HTML (bubble inside avatar for correct positioning)
      function buildSuperAvatarHtml(){
        var bubbleHtml = '';
        if (avatarBubble && startSentence) {
          bubbleHtml = '<div class="aichat-avatar-bubble"><span class="aichat-avatar-bubble-text">'+escapeHtml(startSentence)+'</span><button type="button" class="aichat-avatar-bubble-close" aria-label="Close">&times;</button></div>';
        }
        var html = '';
        if (avatarEnabled && avatarUrl){
          html = '<div class="aichat-super-avatar" title="'+escapeHtml(title)+'"><img src="'+escapeHtml(avatarUrl)+'" alt="'+escapeHtml(title)+'" />'+bubbleHtml+'</div>';
        } else {
          var initials = 'AI';
          if (title && title.trim().length>=2){ initials = title.trim().substring(0,2).toUpperCase(); }
          html = '<div class="aichat-super-avatar" title="'+escapeHtml(title)+'">'+escapeHtml(initials)+bubbleHtml+'</div>';
        }
        return html;
      }

  // Super-minimizado: estado guardado tiene prioridad sobre default
  if (_savedState === 'superminimized') {
        if (!$root.find('.aichat-super-avatar').length){
          $root.append(buildSuperAvatarHtml());
        }
        $root.addClass('is-superminimized');
        try { animateToCorner($root, position); } catch(_){}
  } else if (_savedState === 'maximized') {
        // Restaurar maximizado
        $root.addClass('is-maximized');
        $inner.removeClass('is-minimized');
        try { $messages.css('height',''); } catch(_){}
        if ($maxBtn && $maxBtn.length) {
          $maxBtn.attr('aria-pressed','true').attr('aria-label','Restore');
        }
  } else if (!_savedState && superMinimizedDefault) {
        // Sin estado guardado: usar default del bot
        if (!$root.find('.aichat-super-avatar').length){
          $root.append(buildSuperAvatarHtml());
        }
        $root.addClass('is-superminimized');
        try { animateToCorner($root, position); } catch(_){}
      }

      // ---------- 5) Eventos ----------
      $micBtn.on('click', function(e){
        e.preventDefault(); e.stopPropagation();
        if (!isMicActive) {
          try { recognition.start(); } catch(e){}
          isMicActive = true;
          $micBtn.attr('aria-pressed','true').addClass('is-recording');
          $micBtn.find('.icon-mic').hide();
          $micBtn.find('.icon-stop').show();
        } else {
          try { recognition.stop(); } catch(e){}
          isMicActive = false;
          $micBtn.attr('aria-pressed','false').removeClass('is-recording');
          $micBtn.find('.icon-stop').hide();
          $micBtn.find('.icon-mic').show();
        }
      });

      $sendBtn.on('click', function(e){
        e.preventDefault(); e.stopPropagation();
        sendMessage($root, $messages, $input, $sendBtn, botSlug, voiceOpts, sessionId);
      });

      $input.on('keydown', function(e){
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault(); e.stopPropagation();
          sendMessage($root, $messages, $input, $sendBtn, botSlug, voiceOpts, sessionId);
        }
      });

      // Eventos de ventana
      if (minimizable) {
        $inner.on('click', '.aichat-btn-minimize', function(e){
          e.preventDefault();
          // unmaximize if minimizing
          $root.removeClass('is-maximized');
          $inner.toggleClass('is-minimized');
          // Persistir estado visual
          var newState = $inner.hasClass('is-minimized') ? 'minimized' : 'open';
          saveWidgetState(botSlug, newState);
        });
      }
      // Maximize toggle (only for floating widgets)
      if ($maxBtn && $maxBtn.length) {
        $inner.on('click', '.aichat-btn-maximize', function(e){
          e.preventDefault();
          if (!$root.hasClass('is-global')) return;
          var active = !$root.hasClass('is-maximized');
          if (active) {
            // ensure visible
            $root.removeClass('is-superminimized');
            $inner.removeClass('is-minimized');
            $root.addClass('is-maximized');
            // Allow messages panel to flex: remove fixed height if was set
            try { $messages.css('height',''); } catch(_){}
          } else {
            $root.removeClass('is-maximized');
            // Restore fixed height if it was configured
            var oh = $messages.data('origHeightPx');
            if (oh) { try { $messages.css('height', oh + 'px'); } catch(_){} }
          }
          var pressed = $root.hasClass('is-maximized');
          $maxBtn.attr('aria-pressed', pressed ? 'true':'false')
                .attr('aria-label', pressed ? 'Restore' : 'Maximize');
          // Persistir estado visual
          saveWidgetState(botSlug, pressed ? 'maximized' : 'open');
          // keep scroll at bottom when opening
          if (pressed) {
            setTimeout(function(){ try{ var el=$inner.find('.aichat-messages')[0]; if(el) el.scrollTop=el.scrollHeight; }catch(e){} }, 60);
          }
          // Reapply sizing when leaving maximized
          if (!pressed) {
            setTimeout(applySizing, 30);
          }
        });
      }
      if (closable) {
        // Super-minimizado: alterna clase en el contenedor raíz
        $inner.on('click', '.aichat-btn-close', function(e){
          e.preventDefault();
          // Si estaba maximizado, salir del estado maximizado antes de super-minimizar
          if ($root.hasClass('is-maximized')) {
            $root.removeClass('is-maximized');
            // Restaurar altura fija de mensajes si existía
            try {
              var oh = $messages.data('origHeightPx');
              if (oh) { $messages.css('height', oh + 'px'); }
            } catch(_){ }
          }
          // Crear contenedor avatar si no existe
          if (!$root.find('.aichat-super-avatar').length){
            $root.append(buildSuperAvatarHtml());
          }
          // Si ya está super-minimizado (caso raro: header visible por estilos), simplemente alterna
          if ($root.hasClass('is-superminimized')) {
            $root.removeClass('is-superminimized');
            saveWidgetState(botSlug, 'open');
            return;
          }

          // Añade estado super-minimizado primero (para tener tamaño final 60x60 al calcular destino)
          $root.addClass('is-superminimized');
          // Persistir estado visual
          saveWidgetState(botSlug, 'superminimized');

          // Animar regreso a la esquina configurada si el usuario había arrastrado el widget (left/top inline)
          animateToCorner($root, position);
        });
        // Dismiss bubble on X click (without opening widget)
        $root.on('click', '.aichat-avatar-bubble-close', function(e){
          e.preventDefault();
          e.stopPropagation();
          $root.find('.aichat-avatar-bubble').fadeOut(200);
        });
        // Restaurar al hacer click en el avatar circle
        $root.on('click', '.aichat-super-avatar', function(e){
          e.preventDefault();
          $root.find('.aichat-avatar-bubble').remove();
          $root.removeClass('is-superminimized');
          // Limpiar estilos inline para que vuelvan a aplicar las clases pos-*
          try { $root.css({ left:'', top:'', right:'', bottom:'' }); } catch(_){ }
          // Persistir estado visual
          saveWidgetState(botSlug, 'open');
        });
      }

      // Arrastrable (header como “handle”), solo flotante
      if (draggable && $root.hasClass('is-global')) {
        makeDraggable($root, $inner.find('.aichat-header'));
      }

      if (DEBUG) console.log('[AIChat] instancia lista idx=', idx);

      // Re-clamp on window resize (debounced)
      (function(){
        var t=null; function onResize(){ if(t) clearTimeout(t); t=setTimeout(applySizing, 100); }
        window.addEventListener('resize', onResize, {passive:true});
      })();
    });

    // ---------- helpers de envío y UI ----------

    function sendMessage($root, $messages, $input, $sendBtn, botSlug, opts, sessionId) {
        // Si ya está limitado, no permitir más envíos
        var limitedInfo = $root.data('aichatLimited');
        if (limitedInfo) {
          // Opcional: volver a mostrar el mensaje de límite (no duplicar demasiadas veces)
          return;
        }
      // Evita doble-submit mientras una petición está en curso
      if ($root.data('aichatInFlight')) {
        if (DEBUG) console.log('[AIChat] send ignored (in-flight)');
        return;
      }

      // Al enviar un nuevo mensaje, limpiar chips anteriores
      removeSuggestions($messages);
      var message = ($input.val() || '').trim();
      if (!message) {
        if (/[^\s]/.test($input.val() || '')) $input.val(''); // limpia si eran espacios
        return;
      }

      var $userMsg = appendUser($messages, message);
      $input.val('');

      var $typing = appendTyping($messages);
  // Programar secuencia temporal (solo para PRIMERA espera de respuesta)
  scheduleThinkingStages($typing);
      lockInputs($input, $sendBtn, true);

      $root.data('aichatInFlight', 1);

      var payload = {
        action:   'aichat_process_message',
        nonce:    AIChatVars.nonce,
        bot_slug: botSlug,
        message:  message,
        page_id:  (AIChatVars && AIChatVars.page_id) ? parseInt(AIChatVars.page_id,10) : 0,
        session_id: sessionId,
        debug:    DEBUG ? 1 : 0
      };

      if (DEBUG) payload.debug = 1;

      $.ajax({
        url:    AIChatVars.ajax_url,
        method: 'POST',
        data:   payload
      })
      .done(function(response){
        if (DEBUG && response && response.data && response.data.debug) {
          console.info('[AIChat][debug]', response.data.debug);
        }
        clearThinkingStages($typing);
        $typing.remove();
        
        // DEBUG: Log completo de la respuesta para ver qué viene
        console.log('[AIChat] Full response:', response);
        if (response && response.data) {
          console.log('[AIChat] response.data:', response.data);
        }
        
        // Caso límite (success true con limited)
        if (response && response.success && response.data) {
          
          // LIVECHAT: Siempre notificar el modo actual (bot, waiting_agent, agent)
          if (response.data.livechat_mode) {
            console.log('[AIChat] LiveChat mode:', response.data.livechat_mode);
            // Emitir evento para que livechat-frontend.js lo capture
            document.dispatchEvent(new CustomEvent('aichat_livechat_mode', {
              detail: { mode: response.data.livechat_mode }
            }));
          }
          
          // Duplicado (doble-submit / retry): no mostrar error ni dejar burbuja huérfana.
          if (response.data.duplicate) {
            try { if ($userMsg && $userMsg.length) $userMsg.remove(); } catch(e) {}
            return;
          }
          // Handshake tool_pending (Responses gpt-5*)
          if (response.data.status === 'tool_pending' && Array.isArray(response.data.tool_calls)) {
            handleToolPending($root, $messages, $input, $sendBtn, botSlug, opts, sessionId, response.data);
            return; // no continuar flujo normal
          }
          var msg = typeof response.data.message !== 'undefined' ? String(response.data.message) : '';
          var isLimited = !!response.data.limited || (response.data.limit_type && /daily_total|per_user/.test(response.data.limit_type));
          if (msg) appendBot($messages, msg);

          // Chips: renderizar sugerencias si vienen en la respuesta
          if (response.data && Array.isArray(response.data.suggestions)) {
            appendSuggestions($root, $messages, $input, $sendBtn, botSlug, opts, sessionId, response.data.suggestions);
          }
          if (isLimited) {
            setLimited($root, msg, response.data.limit_type || 'unknown');
            return; // no continuar TTS
          }
          if (opts && typeof opts.onBotResponse === 'function' && msg) {
            try { opts.onBotResponse(msg); } catch(e){}
          }
        } else {
          // success = false (error) → puede ser daily_total_hidden o error normal
          var errMsg = (response && response.data && response.data.message) ? String(response.data.message) : __('Unknown error.', 'axiachat-ai');
          var lt = response && response.data && response.data.limit_type ? response.data.limit_type : '';
          if (lt === 'daily_total_hidden') {
            // Ocultar completamente el widget
            setLimited($root, errMsg, lt, true);
            return;
          }
          appendError($messages, errMsg);
        }
      })
      .fail(function(jqXHR, textStatus, errorThrown){
        console.error('[AIChat] AJAX FAIL:', textStatus, errorThrown, jqXHR && jqXHR.status, jqXHR && jqXHR.responseText);
        $typing.remove();
        appendError($messages, sprintf(
          /* translators: %s: Error message from communication failure */
          __('Communication error: %s', 'axiachat-ai'), errorThrown || textStatus || __('unknown', 'axiachat-ai')));
      })
      .always(function(){
        $root.removeData('aichatInFlight');
        lockInputs($input, $sendBtn, false);
        scrollToBottom($messages);
      });
    }

    // Maneja estado tool_pending: muestra activity labels y lanza segunda llamada (continuation)
    function handleToolPending($root, $messages, $input, $sendBtn, botSlug, opts, sessionId, pendingData){
      if (DEBUG) console.log('[AIChat] tool_pending recibido', pendingData);
      lockInputs($input, $sendBtn, true);
      var list = pendingData.tool_calls || [];
      if (!list.length) return;
      // Burbuja única para actividad de tools (sin la secuencia temporal de 0/3/6s que ya ocurrió antes)
      var finished = false;
      var afterToolsTimer = null;
      var $bubble = appendSingleActivity($messages);

      function setBubbleText(txt){
        if (!$bubble || !$bubble.length) return;
        var safe = escapeHtml(txt || '');
        $bubble.html('<span class="activity-text">'+ safe +' <span class="dots">•••</span></span>');
      }

      var toolIndex = 0;
      function showNextToolLabel(){
        if (finished) return;
        if (toolIndex >= list.length){
          afterToolsTimer = setTimeout(function(){ if (!finished) setBubbleText(__('Processing results...', 'axiachat-ai')); }, 800);
          return;
        }
        var tc = list[toolIndex++];
        var label = tc.activity_label || (sprintf(
          /* translators: %s: Tool name being executed */
          __('Running %s', 'axiachat-ai'), tc.name||__('tool', 'axiachat-ai')));
        setBubbleText(label);
        setTimeout(showNextToolLabel, 900);
      }
      setTimeout(showNextToolLabel, 400);

      var contPayload = {
        action: 'aichat_process_message',
        nonce: AIChatVars.nonce,
        bot_slug: botSlug,
        continue_tool: 1,
        response_id: pendingData.response_id,
        tool_calls: JSON.stringify(list),
        aichat_request_uuid: pendingData.request_uuid || '',
        session_id: sessionId
      };
      $.ajax({
        url: AIChatVars.ajax_url,
        method: 'POST',
        data: contPayload
      }).done(function(res){
        finished = true;
        clearTimeout(afterToolsTimer);
        
        // Log para debugging
        if (DEBUG) console.log('[AIChat] Continuation response:', res);
        
        var ok = res && res.success && res.data;
        if (!ok){
          if ($bubble) $bubble.remove();
          appendError($messages, __('Tool continuation failed.', 'axiachat-ai'));
          return;
        }
        
        // Detectar livechat_mode en la respuesta de continuation
        if (res.data.livechat_mode) {
          console.log('[AIChat] LiveChat mode in continuation:', res.data.livechat_mode);
          // Dispatch custom event for livechat add-on
          document.dispatchEvent(new CustomEvent('aichat_livechat_mode', {
            detail: { mode: res.data.livechat_mode }
          }));
        }
        
        // Mostrar check y desvanecer la burbuja
        if ($bubble){
          $bubble.removeClass('typing').addClass('done');
          $bubble.empty().append('<span class="activity-text">' + __('Done', 'axiachat-ai') + ' <span class="dots">✓</span></span>');
          setTimeout(function(){ $bubble.fadeOut(220, function(){ $(this).remove(); }); }, 600);
        }
        var finalMsg = String(res.data.message || '');
        if (finalMsg) appendBot($messages, finalMsg);

        // Chips: renderizar sugerencias si vienen en la respuesta
        if (res.data && Array.isArray(res.data.suggestions)) {
          appendSuggestions($root, $messages, $input, $sendBtn, botSlug, opts, sessionId, res.data.suggestions);
        }
        scrollToBottom($messages);
      }).fail(function(jqXHR, textStatus){
        finished = true;
        clearTimeout(afterToolsTimer);
        if ($bubble) $bubble.remove();
        appendError($messages, sprintf(
          /* translators: %s: Error message from continuation failure */
          __('Continuation error: %s', 'axiachat-ai'), textStatus||__('unknown', 'axiachat-ai')));
      }).always(function(){
        lockInputs($input, $sendBtn, false);
      });
    }

      // Marca el widget como limitado y desactiva inputs/mic
      function setLimited($root, message, limitType, hideAll){
        $root.data('aichatLimited', { message: message, type: limitType });
        $root.addClass('aichat-limited');
        var $input = $root.find('.aichat-input');
        var $send  = $root.find('.aichat-send');
        var $mic   = $root.find('.aichat-mic');
        $input.prop('disabled', true).attr('aria-disabled','true');
        $send.prop('disabled', true).attr('aria-disabled','true');
        if ($mic.length) $mic.prop('disabled', true).attr('aria-disabled','true');
        if (hideAll) {
          // Comportamiento hidden: ocultar widget completo
          $root.hide();
        }
      }

    function appendUser($messages, text) {
      var $el = $('<div class="message user-message">' + escapeHtml(text) + '</div>');
      $messages.append($el);
      scrollToBottom($messages);
      return $el;
    }

    function appendBot($messages, text) {
      // Confía en HTML sanitizado en servidor (wp_kses) → permite <a>, <strong>, etc.
      var $el = $('<div class="message bot-message"></div>');
      $el.html(String(text));
      // Auto-linkify plain URLs that survived without <a> wrapping
      linkifyPlainUrls($el);
      // Ensure every <a> opens in a new tab
      $el.find('a').each(function(){
        if (!this.getAttribute('target')) this.setAttribute('target', '_blank');
        if (!this.getAttribute('rel'))    this.setAttribute('rel', 'noopener');
      });
      // Deduplicate: if two adjacent <a> tags point to the same URL, remove the plain-URL one
      dedupAdjacentLinks($el);
      $messages.append($el);
      scrollToBottom($messages);
      return $el;
    }

    /**
     * Abbreviate a URL for display: keep scheme + host (+ first path segment if short).
     * Examples: https://casaydinero.es/some/long/path → https://casaydinero.es…
     */
    function abbreviateUrl(url) {
      var maxLen = 55;
      if (url.length <= maxLen) return url;
      try {
        var u = new URL(url);
        var base = u.protocol + '//' + u.host;
        if (base.length >= maxLen - 1) return base + '\u2026';
        // Try adding first path segment
        var segs = u.pathname.split('/').filter(Boolean);
        if (segs.length > 0) {
          var short = base + '/' + segs[0];
          if (short.length < maxLen - 1 && segs.length > 1) return short + '/\u2026';
          if (short.length < maxLen) return short + (u.pathname.length > ('/' + segs[0]).length ? '\u2026' : '');
        }
        return base + '\u2026';
      } catch(e) {
        return url.slice(0, maxLen) + '\u2026';
      }
    }

    /**
     * Deduplicate adjacent links: when the AI outputs a raw URL AND we also
     * generated a titled [LINK:N] <a> next to it pointing to the same href,
     * remove the plain-URL link to avoid double links.
     */
    function dedupAdjacentLinks($el) {
      $el.find('a').each(function() {
        var $a = $(this);
        var href = $a.prop('href');
        if (!href) return;
        // Normalise for comparison (strip trailing slash)
        var norm = href.replace(/\/+$/, '').toLowerCase();

        // Look at the immediately preceding and following sibling nodes
        var siblings = [];
        var prev = this.previousSibling;
        // Skip whitespace-only text nodes
        if (prev && prev.nodeType === 3 && !prev.nodeValue.trim()) prev = prev.previousSibling;
        if (prev && prev.nodeName === 'A') siblings.push(prev);

        var next = this.nextSibling;
        if (next && next.nodeType === 3 && !next.nodeValue.trim()) next = next.nextSibling;
        if (next && next.nodeName === 'A') siblings.push(next);

        for (var i = 0; i < siblings.length; i++) {
          var sib = siblings[i];
          var sibHref = (sib.href || '').replace(/\/+$/, '').toLowerCase();
          if (sibHref === norm) {
            // One of them is a "plain URL" link (textContent ≈ href), remove that one
            var aIsPlain   = isPlainUrlLink(this);
            var sibIsPlain = isPlainUrlLink(sib);
            if (aIsPlain && !sibIsPlain) {
              $(this).remove();
              return; // removed self, stop
            } else if (sibIsPlain && !aIsPlain) {
              $(sib).remove();
            }
            // If both are plain or both are titled, leave them.
          }
        }
      });
    }

    /** Returns true if the <a> element's visible text looks like a bare URL */
    function isPlainUrlLink(a) {
      var txt = (a.textContent || '').trim().replace(/\/+$/, '').toLowerCase();
      var href = (a.href || '').replace(/\/+$/, '').toLowerCase();
      // Direct match or the text starts with http
      if (txt === href) return true;
      // Abbreviated URL (ends with …)
      if (/^https?:\/\//.test(txt) && txt.replace(/\u2026$/, '') === href.slice(0, txt.replace(/\u2026$/, '').length)) return true;
      return false;
    }

    /**
     * Walk text nodes inside $el and wrap bare URLs in <a> tags.
     * Skips text already inside <a>, <code>, <pre>.
     */
    function linkifyPlainUrls($el){
      var urlRe = /\bhttps?:\/\/[^\s<>\)\]"'`]+/gi;
      var skip  = {A:1, CODE:1, PRE:1};
      $el.contents().each(function walkNode(){
        if (this.nodeType === 3) { // text node
          // Check if parent is a tag we should skip
          if (this.parentNode && skip[this.parentNode.nodeName]) return;
          var txt = this.nodeValue;
          if (!urlRe.test(txt)) return;
          urlRe.lastIndex = 0; // reset after .test()
          var frag = document.createDocumentFragment();
          var lastIdx = 0, match;
          while ((match = urlRe.exec(txt)) !== null) {
            // Text before the URL
            if (match.index > lastIdx) frag.appendChild(document.createTextNode(txt.slice(lastIdx, match.index)));
            // Strip trailing punctuation that likely isn't part of the URL
            var raw = match[0].replace(/[.,;:!?\)]+$/, '');
            var trailing = match[0].slice(raw.length);
            var a = document.createElement('a');
            a.href = raw; a.target = '_blank'; a.rel = 'noopener';
            // Abbreviate long URLs for display: show host + ellipsis
            a.textContent = abbreviateUrl(raw);
            frag.appendChild(a);
            if (trailing) frag.appendChild(document.createTextNode(trailing));
            lastIdx = match.index + match[0].length;
          }
          if (lastIdx < txt.length) frag.appendChild(document.createTextNode(txt.slice(lastIdx)));
          this.parentNode.replaceChild(frag, this);
        } else if (this.nodeType === 1 && !skip[this.nodeName]) {
          // Recurse into child elements (but not <a>, <code>, <pre>)
          $(this).contents().each(walkNode);
        }
      });
    }

    function removeSuggestions($messages){
      try { $messages.find('.aichat-suggestions').remove(); } catch(e) {}
    }

    function appendSuggestions($root, $messages, $input, $sendBtn, botSlug, opts, sessionId, suggestions){
      removeSuggestions($messages);
      if (!Array.isArray(suggestions) || !suggestions.length) return;

      var cfg = $root.data('aichatSuggestionsCfg') || {};
      if (!cfg.enabled) return;

      var max = parseInt(cfg.count, 10) || 3;
      if (max < 1) max = 1;
      if (max > 6) max = 6;

      var bg = String(cfg.bg || '').trim();
      var tx = String(cfg.text || '').trim();

      var style = '';
      if (/^#[0-9a-fA-F]{6}$/.test(bg)) style += '--aichat-sugg-bg:'+bg+';';
      if (/^#[0-9a-fA-F]{6}$/.test(tx)) style += '--aichat-sugg-text:'+tx+';';

      var $wrap = $('<div class="aichat-suggestions"'+(style?' style="'+style+'"':'')+'></div>');
      suggestions.slice(0, max).forEach(function(s){
        if (typeof s !== 'string') return;
        var label = String(s).trim();
        if (!label) return;
        var $btn = $('<button type="button" class="aichat-suggestion"></button>');
        $btn.text(label);
        $btn.on('click', function(){
          if ($root.data('aichatLimited')) return;
          if ($root.data('aichatInFlight')) return;
          $input.val(label);
          sendMessage($root, $messages, $input, $sendBtn, botSlug, opts, sessionId);
        });
        $wrap.append($btn);
      });
      if (!$wrap.children().length) return;
      $messages.append($wrap);
      scrollToBottom($messages);
    }

    function appendError($messages, text) {
      $messages.append('<div class="message bot-message error">' + escapeHtml(text) + '</div>');
      scrollToBottom($messages);
    }

    function appendTyping($messages) {
      var $el = $('<div class="message bot-message typing"><span class="dots">•••</span></div>');
      $messages.append($el);
      scrollToBottom($messages);
      return $el;
    }
    // Programa la secuencia de 0/3/6s para la PRIMERA espera (antes de tool_pending o respuesta final)
    function scheduleThinkingStages($bubble){
      if (!$bubble || !$bubble.length) return;
      // Helper to check if element is still in DOM (works with Shadow DOM too)
      function isAttached(el) {
        if (!el) return false;
        var node = el;
        while (node) {
          if (node === document || node === document.documentElement) return true;
          // Shadow DOM: getRootNode returns ShadowRoot, check its host
          var root = node.getRootNode ? node.getRootNode() : node.ownerDocument;
          if (root && root.host) {
            node = root.host; // traverse up from shadow root
          } else {
            node = node.parentNode;
          }
        }
        return false;
      }
      var t3 = setTimeout(function(){
        if (!isAttached($bubble[0])) return; // element removed
        $bubble.html('<span class="activity-text">' + __('Thinking', 'axiachat-ai') + ' <span class="dots">•••</span></span>');
      }, 3000);
      var t6 = setTimeout(function(){
        if (!isAttached($bubble[0])) return;
        $bubble.html('<span class="activity-text">' + __('Still working, almost there', 'axiachat-ai') + ' <span class="dots">•••</span></span>');
      }, 6000);
      $bubble.data('thinkingTimers', [t3,t6]);
    }
    function clearThinkingStages($bubble){
      if (!$bubble || !$bubble.length) return;
      var timers = $bubble.data('thinkingTimers') || [];
      timers.forEach(function(id){ clearTimeout(id); });
      $bubble.removeData('thinkingTimers');
    }
    // Nueva burbuja de actividad
    function appendActivity($messages, text, extraClass){
      var cls = 'message bot-message typing aichat-activity-bubble';
      if (extraClass) cls += ' '+extraClass;
      var $el = $('<div class="'+cls+'"><span class="dots">•••</span><span class="activity-text"> '+escapeHtml(text||'')+'</span></div>');
      $messages.append($el);
      scrollToBottom($messages);
      return $el;
    }
    // Burbuja única inicial (solo puntos) para nueva estrategia
    function appendSingleActivity($messages){
      var $el = $('<div class="message bot-message typing aichat-activity-bubble"><span class="dots">•••</span></div>');
      $messages.append($el);
      scrollToBottom($messages);
      return $el;
    }

    function scrollToBottom($messages) {
      var el = $messages.get(0);
      if (el) el.scrollTop = el.scrollHeight;
    }

    function lockInputs($input, $sendBtn, lock) {
      $input.prop('disabled', !!lock);
      $sendBtn.prop('disabled', !!lock);
    }

    function escapeHtml(str) {
      return $('<div>').text(String(str)).html();
    }

    // ---------- helpers de normalización ----------
    function normPos(v){
      if (!v) return 'bottom-right';
      // abreviaturas
      if (v === 'tr') return 'top-right';
      if (v === 'tl') return 'top-left';
      if (v === 'br') return 'bottom-right';
      if (v === 'bl') return 'bottom-left';
      // sinónimos
      var map = {
        'top-right'    : ['top-right','derecha-superior','superior-derecha'],
        'top-left'     : ['top-left','izquierda-superior','superior-izquierda'],
        'bottom-right' : ['bottom-right','derecha-inferior','inferior-derecha'],
        'bottom-left'  : ['bottom-left','izquierda-inferior','inferior-izquierda']
      };
      for (var k in map){ if (map[k].indexOf(v) >= 0) return k; }
      return 'bottom-right';
    }

    // Drag helper
    function makeDraggable($root, $handle){
      var dragging = false, sx=0, sy=0, sl=0, st=0;
      var $doc = $(document);
      $handle.css('cursor','move');
      $handle.on('mousedown.aichat touchstart.aichat', function(ev){
        // No iniciar arrastre si el toque/click es sobre controles interactivos
        var $t = $(ev.target);
        if ($t.closest('.aichat-header-controls, .aichat-btn, button, a, input, select, textarea, label').length){
          return; // deja pasar el evento a los handlers de los botones
        }
        var e = ev.type.startsWith('touch') ? ev.originalEvent.touches[0] : ev;
        dragging = true;
        $root.addClass('dragging');
        // fijar a top/left absolutos (position:fixed ya viene por CSS)
        var rect = $root.get(0).getBoundingClientRect();
        sl = rect.left; st = rect.top;
        sx = e.clientX; sy = e.clientY;
        ev.preventDefault();
      });
      $doc.on('mousemove.aichat touchmove.aichat', function(ev){
        if (!dragging) return;
        var e = ev.type.startsWith('touch') ? ev.originalEvent.touches[0] : ev;
        var dx = e.clientX - sx, dy = e.clientY - sy;
        var nl = sl + dx, nt = st + dy;
        // límites viewport
        var vw = window.innerWidth, vh = window.innerHeight;
        var w = $root.outerWidth(), h = $root.outerHeight();
        nl = Math.max(0, Math.min(vw - w, nl));
        nt = Math.max(0, Math.min(vh - h, nt));
        $root.css({ left: nl+'px', top: nt+'px', right: 'auto', bottom: 'auto' });
      });
      $doc.on('mouseup.aichat touchend.aichat touchcancel.aichat', function(){
        if (!dragging) return;
        dragging = false;
        $root.removeClass('dragging');
      });
    }

    // Anima el widget hacia la esquina definida por 'position', limpiando estilos inline al finalizar.
    function animateToCorner($root, position){
      try {
        var rectStart = $root.get(0).getBoundingClientRect();
        // Establece posición inicial explícita (por si estaba anclado con right/bottom)
        $root.stop(true, false).css({
          left: rectStart.left + 'px',
          top: rectStart.top + 'px',
          right: 'auto',
          bottom: 'auto'
        });

  // Dimensiones finales (ya en modo superminimizado 60x60)
        var w = $root.outerWidth();
        var h = $root.outerHeight();
  var pad = 20; // margen estándar usado en CSS
  var extraBottom = $root.hasClass('is-superminimized') ? 50 : 0; // elevar avatar en bottom
        var targetLeft, targetTop;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        switch(position){
          case 'top-left':
            targetLeft = pad; targetTop = pad; break;
          case 'top-right':
            targetLeft = vw - pad - w; targetTop = pad; break;
          case 'bottom-left':
            targetLeft = pad; targetTop = vh - pad - h - extraBottom; break;
          case 'bottom-right':
          default:
            targetLeft = vw - pad - w; targetTop = vh - pad - h - extraBottom; break;
        }

        // Si ya está prácticamente en destino, sólo limpiar estilos
        if (Math.abs(rectStart.left - targetLeft) < 2 && Math.abs(rectStart.top - targetTop) < 2){
          // Limpieza asincrónica para permitir reflow de la clase
          setTimeout(function(){
            $root.css({ left:'', top:'', right:'', bottom:'' });
          }, 0);
          return;
        }

        $root.animate({ left: targetLeft, top: targetTop }, 300, 'swing', function(){
          // Si terminamos en super-minimized, mantenemos left/top inline para evitar "rebote"
          if ($root.hasClass('is-superminimized')) {
            // Asegurar que right/bottom no interfieran con inline left/top
            $root.css({ right:'auto', bottom:'auto' });
          } else {
            // En estados normales, limpiar para que pos-* gobierne
            $root.css({ left:'', top:'', right:'', bottom:'' });
          }
        });
      } catch(err){
        // Fallback silencioso: si algo falla, limpiar estilos para no dejar estado roto
        $root.css({ left:'', top:'', right:'', bottom:'' });
      }
    }
    // Carga historial del servidor y lo pinta
    function loadHistory($messages, botSlug, sessionId, welcomeText){
      $.ajax({
        url: AIChatVars.ajax_url,
        method: 'POST',
        data: { action: 'aichat_get_history', nonce: AIChatVars.nonce, bot_slug: botSlug, session_id: sessionId, limit: 50 }
      }).done(function(res){
        if (!res || !res.success || !Array.isArray(res.data.items)) return;
        res.data.items.forEach(function(it){
          appendUser($messages, String(it.q||''));    // usuario → escapado
          appendBot($messages, String(it.a||''));     // bot → HTML sanitizado
        });
        scrollToBottom($messages);
        try {
          if (Array.isArray(res.data.items) && res.data.items.length === 0 && welcomeText && String(welcomeText).trim() !== ''){
            appendBot($messages, escapeHtml(welcomeText));
            scrollToBottom($messages);
          }
        } catch(_){ }
      });
    }

    // Cookie helpers
    function getOrCreateSessionId(){
      var key='aichat_sid', m=/(?:^|;)\s*aichat_sid=([^;]+)/.exec(document.cookie);
      if (m && m[1]) return decodeURIComponent(m[1]);
      return forceNewSessionId();
    }

    function forceNewSessionId(){
      var sid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('sid-' + Math.random().toString(36).slice(2) + Date.now());
      var exp = 60*60*24*30; // 30 días
      document.cookie = 'aichat_sid=' + encodeURIComponent(sid) + '; Max-Age=' + exp + '; Path=/; SameSite=Lax';
      return sid;
    }

    // ---------- Widget visual state persistence (sessionStorage) ----------
    // States: 'open' | 'minimized' | 'superminimized' | 'maximized'
    function _widgetStateKey(slug){
      return 'aichat_wstate_' + (slug || 'default');
    }
    function saveWidgetState(slug, state){
      try { sessionStorage.setItem(_widgetStateKey(slug), state); } catch(_){}
      if (DEBUG) console.log('[AIChat] widget state saved:', slug, state);
    }
    function loadWidgetState(slug){
      try { return sessionStorage.getItem(_widgetStateKey(slug)) || ''; } catch(_){ return ''; }
    }

  });

})(jQuery);
