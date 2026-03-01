/**
 * WP AI Chatbot — Frontend Runtime
 *
 * Architecture: Vanilla JS IIFE module, no jQuery, no build tools required.
 * Namespace: window.WPAICChat
 * Instance registry: one entry per widget (supports multiple widgets per page).
 * Session storage: localStorage primary, cookie fallback, per-bot scoped.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

/* global wpaicChatData */

(function (global) {
	'use strict';

	// =========================================================================
	// Module constants
	// =========================================================================

	var VERSION = '1.0.0';
	var INSTANCE_ATTR = 'data-wpaic-instance';
	var SESSION_KEY_PREFIX = 'wpaic_session_';
	var COOKIE_KEY_PREFIX = 'wpaic_sid_';
	var COOKIE_DAYS = 30;
	var TYPING_DELAY_MS = 300;
	var MAX_SUGGESTIONS = 5;

	// =========================================================================
	// Instance registry (supports multiple widgets per page)
	// =========================================================================

	var instances = {};

	// =========================================================================
	// DOM helpers
	// =========================================================================

	function qs(selector, context) {
		return (context || document).querySelector(selector);
	}

	function qsa(selector, context) {
		return Array.prototype.slice.call((context || document).querySelectorAll(selector));
	}

	function ce(tag, attrs, children) {
		var el = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'className') {
					el.className = attrs[k];
				} else if (k === 'textContent') {
					el.textContent = attrs[k];
				} else {
					el.setAttribute(k, attrs[k]);
				}
			});
		}
		if (children) {
			children.forEach(function (c) {
				if (typeof c === 'string') {
					el.appendChild(document.createTextNode(c));
				} else if (c) {
					el.appendChild(c);
				}
			});
		}
		return el;
	}

	// =========================================================================
	// Session storage (localStorage + cookie fallback)
	// =========================================================================

	function getSessionToken(botSlug) {
		var key = SESSION_KEY_PREFIX + botSlug;
		try {
			return localStorage.getItem(key) || '';
		} catch (e) {
			return getCookie(COOKIE_KEY_PREFIX + botSlug);
		}
	}

	function setSessionToken(botSlug, token) {
		var key = SESSION_KEY_PREFIX + botSlug;
		try {
			localStorage.setItem(key, token);
		} catch (e) {
			// Fall back to cookie.
		}
		// Always write cookie as backup.
		setCookie(COOKIE_KEY_PREFIX + botSlug, token, COOKIE_DAYS);
	}

	function clearSessionToken(botSlug) {
		try {
			localStorage.removeItem(SESSION_KEY_PREFIX + botSlug);
		} catch (e) {
			// ignore
		}
		setCookie(COOKIE_KEY_PREFIX + botSlug, '', -1);
	}

	function getCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function setCookie(name, value, days) {
		var expires = '';
		if (days) {
			var d = new Date();
			d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
			expires = '; expires=' + d.toUTCString();
		}
		document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
	}

	// =========================================================================
	// XSS-safe HTML sanitization (DOMParser allowlist)
	// =========================================================================

	var ALLOWED_TAGS = [
		'A',
		'STRONG',
		'EM',
		'B',
		'I',
		'BR',
		'SUP',
		'SUB',
		'DEL',
		'P',
		'BLOCKQUOTE',
		'HR',
		'UL',
		'OL',
		'LI',
		'PRE',
		'CODE',
		'H3',
		'H4',
		'H5',
		'H6',
		'TABLE',
		'THEAD',
		'TBODY',
		'TR',
		'TH',
		'TD',
	];

	var ALLOWED_ATTRS = {
		A: ['href', 'title', 'target', 'rel'],
		TD: ['colspan', 'rowspan'],
		TH: ['scope', 'colspan', 'rowspan'],
		OL: ['start'],
		'*': [],
	};

	var ALLOWED_HREF_SCHEMES = /^(https?:\/\/|mailto:|tel:|\/|#)/i;

	function sanitizeBotHtml(html) {
		var parser = new DOMParser();
		var doc = parser.parseFromString('<body>' + html + '</body>', 'text/html');
		sanitizeNode(doc.body);
		return doc.body.innerHTML;
	}

	function sanitizeNode(node) {
		var children = Array.prototype.slice.call(node.childNodes);
		children.forEach(function (child) {
			if (child.nodeType === 1) {
				// Element node.
				var tag = child.tagName.toUpperCase();
				if (ALLOWED_TAGS.indexOf(tag) === -1) {
					// Replace disallowed element with its text content.
					var text = document.createTextNode(child.textContent || '');
					node.replaceChild(text, child);
					return;
				}

				// Strip disallowed attributes.
				var attrsToRemove = [];
				var allowedForTag = ALLOWED_ATTRS[tag] || ALLOWED_ATTRS['*'];
				var globalAllowed = ['class']; // class is always allowed for styling
				Array.prototype.slice.call(child.attributes).forEach(function (attr) {
					var name = attr.name.toLowerCase();
					if (allowedForTag.indexOf(name) === -1 && globalAllowed.indexOf(name) === -1) {
						attrsToRemove.push(name);
					}
				});
				attrsToRemove.forEach(function (a) {
					child.removeAttribute(a);
				});

				// Enforce safe link attributes.
				if (tag === 'A') {
					var href = child.getAttribute('href') || '';
					if (href && !ALLOWED_HREF_SCHEMES.test(href)) {
						child.setAttribute('href', '#');
					}
					child.setAttribute('target', '_blank');
					child.setAttribute('rel', 'noopener noreferrer');
				}

				// Recurse.
				sanitizeNode(child);
			}
		});
	}

	// =========================================================================
	// Page context extraction
	// =========================================================================

	function extractPageContext() {
		return {
			url: window.location.href,
			title: document.title || '',
		};
	}

	// =========================================================================
	// AJAX / SSE communication
	// =========================================================================

	function buildFormData(instance, message) {
		var data = new FormData();
		data.append('action', 'wpaic_chat');
		data.append('nonce', wpaicChatData.nonce);
		data.append('message', message);
		data.append('bot_slug', instance.botSlug);
		data.append('session_token', instance.sessionToken || '');

		var ctx = extractPageContext();
		data.append('page_url', ctx.url);
		data.append('page_title', ctx.title);

		// Honeypot field (always blank; bots tend to fill it).
		data.append('wpaic_hp', '');

		if (wpaicChatData.streamEnabled) {
			data.append('stream', '1');
		}

		return data;
	}

	function sendMessage(instance, message, callbacks) {
		var url = wpaicChatData.ajaxUrl;

		if (wpaicChatData.streamEnabled) {
			// SSE streaming via fetch + ReadableStream.
			sendStream(instance, message, url, callbacks);
		} else {
			// Standard AJAX JSON response.
			sendAjax(instance, message, url, callbacks);
		}
	}

	function sendAjax(instance, message, url, callbacks) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.onload = function () {
			// Parse WP JSON body for both success (2xx) and error (4xx/5xx) responses.
			try {
				var data = JSON.parse(xhr.responseText);
				if (data.success) {
					if (data.data.session_token) {
						instance.sessionToken = data.data.session_token;
						setSessionToken(instance.botSlug, instance.sessionToken);
					}
					callbacks.onDone(data.data.html || '', data.data.suggestions || []);
				} else {
					var errPayload = data.data && typeof data.data === 'object' ? data.data : {};
					callbacks.onError(errPayload.message || wpaicChatData.i18n.error_generic, errPayload);
				}
			} catch (e) {
				callbacks.onError(wpaicChatData.i18n.error_generic, {});
			}
		};
		xhr.onerror = function () {
			callbacks.onError(wpaicChatData.i18n.error_network, {});
		};
		xhr.send(buildFormData(instance, message));
	}

	function sendStream(instance, message, url, callbacks) {
		var body = buildFormData(instance, message);
		var buffer = '';

		fetch(url, { method: 'POST', body: body })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				var reader = response.body.getReader();
				var decoder = new TextDecoder();

				function readChunk() {
					return reader.read().then(function (result) {
						if (result.done) {
							return;
						}
						buffer += decoder.decode(result.value, { stream: true });

						// Process complete SSE lines.
						var lines = buffer.split('\n');
						buffer = lines.pop(); // keep incomplete last line

						lines.forEach(function (line) {
							if (line.indexOf('data: ') === 0) {
								var payload = line.slice(6).trim();
								if (payload === '[DONE]') {
									return;
								}
								try {
									var chunk = JSON.parse(payload);
									if (chunk.type === 'delta' && chunk.text) {
										callbacks.onChunk(chunk.text);
									} else if (chunk.type === 'done') {
										if (chunk.session_token) {
											instance.sessionToken = chunk.session_token;
											setSessionToken(instance.botSlug, instance.sessionToken);
										}
										callbacks.onDone(chunk.html || '', chunk.suggestions || []);
									} else if (chunk.error) {
										callbacks.onError(chunk.error);
									}
								} catch (e) {
									// Malformed chunk — skip.
								}
							}
						});

						return readChunk();
					});
				}

				return readChunk();
			})
			.catch(function () {
				// Fallback: try sync AJAX.
				sendAjax(instance, message, url, callbacks);
			});
	}

	// =========================================================================
	// Focus trap helpers
	// =========================================================================

	var FOCUSABLE_SELECTOR =
		'a[href], button:not([disabled]):not([tabindex="-1"]), ' +
		'input:not([disabled]):not([tabindex="-1"]), ' +
		'select:not([disabled]):not([tabindex="-1"]), ' +
		'textarea:not([disabled]):not([tabindex="-1"]), ' +
		'[tabindex]:not([tabindex="-1"])';

	/**
	 * Trap Tab focus within a container element.
	 * Returns a cleanup function that removes the listener.
	 *
	 * @param  {Element}  container
	 * @return {Function} cleanup
	 */
	function trapFocus(container) {
		function handler(e) {
			if (e.key !== 'Tab') return;
			var focusable = Array.prototype.slice.call(container.querySelectorAll(FOCUSABLE_SELECTOR));
			if (focusable.length === 0) return;

			var first = focusable[0];
			var last = focusable[focusable.length - 1];

			if (e.shiftKey) {
				if (document.activeElement === first) {
					e.preventDefault();
					last.focus();
				}
			} else {
				if (document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			}
		}

		container.addEventListener('keydown', handler);
		return function cleanup() {
			container.removeEventListener('keydown', handler);
		};
	}

	// =========================================================================
	// Widget DOM state machine
	// =========================================================================

	// States: 'idle' | 'open' | 'typing' | 'streaming'

	function addMessage(instance, role, htmlOrText, isHtml) {
		var transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return;

		var row = ce('div', { className: 'wpaic-message wpaic-message--' + role });
		var bubble = ce('div', { className: 'wpaic-bubble' });

		if (role === 'user') {
			// User messages: always plain text to prevent DOM XSS.
			bubble.textContent = htmlOrText;
		} else {
			// Bot messages: sanitized HTML.
			var safeHtml = isHtml
				? sanitizeBotHtml(htmlOrText)
				: sanitizeBotHtml('<p>' + escapeHtml(htmlOrText) + '</p>');
			bubble.innerHTML = safeHtml;
		}

		row.appendChild(bubble);
		transcript.appendChild(row);
		transcript.scrollTop = transcript.scrollHeight;

		return row;
	}

	function addTypingIndicator(instance) {
		var transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return null;

		var row = ce('div', { className: 'wpaic-message wpaic-message--bot wpaic-message--typing' });
		var bubble = ce('div', { className: 'wpaic-bubble', 'aria-label': wpaicChatData.i18n.typing || 'Typing…' });
		// Animated dots: aria-hidden so AT reads the bubble label instead.
		bubble.innerHTML =
			'<span class="wpaic-dot" aria-hidden="true"></span>' +
			'<span class="wpaic-dot" aria-hidden="true"></span>' +
			'<span class="wpaic-dot" aria-hidden="true"></span>';
		row.appendChild(bubble);
		transcript.appendChild(row);
		transcript.scrollTop = transcript.scrollHeight;

		return row;
	}

	function removeTypingIndicator(typingEl) {
		if (typingEl && typingEl.parentNode) {
			typingEl.parentNode.removeChild(typingEl);
		}
	}

	function showSuggestions(instance, suggestions) {
		var container = qs('.wpaic-suggestions', instance.panel);
		if (!container) return;

		container.innerHTML = '';

		if (!suggestions || suggestions.length === 0) {
			container.style.display = 'none';
			return;
		}

		container.style.display = 'flex';
		suggestions.slice(0, MAX_SUGGESTIONS).forEach(function (text) {
			var chip = ce('button', {
				className: 'wpaic-suggestion-chip',
				type: 'button',
				textContent: text,
			});
			chip.addEventListener('click', function () {
				if (instance.state !== 'idle' && instance.state !== 'open') return;
				container.style.display = 'none';
				submitMessage(instance, text);
			});
			container.appendChild(chip);
		});
	}

	function setInputDisabled(instance, disabled) {
		var input = qs('.wpaic-input', instance.panel);
		var btn = qs('.wpaic-send-btn', instance.panel);
		if (input) {
			input.disabled = disabled;
			// aria-disabled mirrors the disabled state for AT that may ignore native disabled.
			if (disabled) {
				input.setAttribute('aria-disabled', 'true');
			} else {
				input.removeAttribute('aria-disabled');
			}
		}
		if (btn) {
			btn.disabled = disabled;
			if (disabled) {
				btn.setAttribute('aria-disabled', 'true');
			} else {
				btn.removeAttribute('aria-disabled');
			}
		}
	}

	function showError(instance, message) {
		var transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return;

		var row = ce('div', { className: 'wpaic-message wpaic-message--error' });
		var bubble = ce('div', { className: 'wpaic-bubble' });
		bubble.textContent = message;
		row.appendChild(bubble);
		transcript.appendChild(row);
		transcript.scrollTop = transcript.scrollHeight;
	}

	/**
	 * Show rate limit fallback contact links (email, phone, URL) in the transcript.
	 * Input remains disabled — server-side limit is still enforced.
	 *
	 * @param {object} instance  Widget instance.
	 * @param {object} errData   Error payload {fallback_email, fallback_phone, fallback_url}.
	 */
	function showRateLimitFallback(instance, errData) {
		var email = errData.fallback_email || '';
		var phone = errData.fallback_phone || '';
		var url = errData.fallback_url || '';

		if (!email && !phone && !url) {
			return; // No fallback configured — nothing to show.
		}

		var transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return;

		var row = ce('div', { className: 'wpaic-message wpaic-message--fallback' });
		var bubble = ce('div', { className: 'wpaic-bubble' });
		var links = [];

		if (email) {
			links.push(
				ce('a', {
					href: 'mailto:' + email,
					target: '_blank',
					rel: 'noopener noreferrer',
					textContent: email,
				}),
			);
		}
		if (phone) {
			links.push(
				ce('a', {
					href: 'tel:' + phone.replace(/\s/g, ''),
					textContent: phone,
				}),
			);
		}
		if (url) {
			links.push(
				ce('a', {
					href: url,
					target: '_blank',
					rel: 'noopener noreferrer',
					textContent: wpaicChatData.i18n.contact_us || 'Contact us',
				}),
			);
		}

		links.forEach(function (link, idx) {
			bubble.appendChild(link);
			if (idx < links.length - 1) {
				bubble.appendChild(document.createTextNode(' · '));
			}
		});

		row.appendChild(bubble);
		transcript.appendChild(row);
		transcript.scrollTop = transcript.scrollHeight;
	}

	function escapeHtml(str) {
		var d = document.createElement('div');
		d.textContent = str;
		return d.innerHTML;
	}

	// =========================================================================
	// Streaming bubble
	// =========================================================================

	function createStreamBubble(instance) {
		var transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return null;

		var row = ce('div', { className: 'wpaic-message wpaic-message--bot wpaic-message--streaming' });
		var bubble = ce('div', { className: 'wpaic-bubble' });
		row.appendChild(bubble);
		transcript.appendChild(row);
		transcript.scrollTop = transcript.scrollHeight;

		return { row: row, bubble: bubble, text: '' };
	}

	// =========================================================================
	// Message submission flow
	// =========================================================================

	function submitMessage(instance, message) {
		message = message.trim();
		if (!message || instance.state === 'typing' || instance.state === 'streaming') {
			return;
		}

		instance.state = 'typing';
		setInputDisabled(instance, true);

		// Add user message bubble.
		addMessage(instance, 'user', message, false);

		// Clear input field.
		var input = qs('.wpaic-input', instance.panel);
		if (input) input.value = '';

		// Show typing indicator.
		var typingEl = addTypingIndicator(instance);

		var streamBubble = null;

		var callbacks = {
			onChunk: function (text) {
				if (!streamBubble) {
					// First chunk: remove typing indicator, create stream bubble.
					removeTypingIndicator(typingEl);
					typingEl = null;
					streamBubble = createStreamBubble(instance);
					instance.state = 'streaming';
				}
				if (streamBubble) {
					streamBubble.text += text;
					// During streaming, render as plain text (safety).
					streamBubble.bubble.textContent = streamBubble.text;
					var transcript = qs('.wpaic-transcript', instance.panel);
					if (transcript) transcript.scrollTop = transcript.scrollHeight;
				}
			},
			onDone: function (html, suggestions) {
				removeTypingIndicator(typingEl);
				typingEl = null;

				if (streamBubble) {
					// Replace streaming plain-text with sanitized HTML.
					streamBubble.bubble.innerHTML = sanitizeBotHtml(html || streamBubble.text);
					streamBubble.row.classList.remove('wpaic-message--streaming');
					streamBubble = null;
				} else {
					// Sync response: add bot message.
					addMessage(instance, 'bot', html, true);
				}

				showSuggestions(instance, suggestions);
				instance.state = 'open';
				setInputDisabled(instance, false);

				// Focus input.
				var inp = qs('.wpaic-input', instance.panel);
				if (inp) inp.focus();
			},
			onError: function (msg, errData) {
				removeTypingIndicator(typingEl);
				typingEl = null;
				if (streamBubble && streamBubble.row.parentNode) {
					streamBubble.row.parentNode.removeChild(streamBubble.row);
					streamBubble = null;
				}

				var isRateLimit = errData && errData.code === 'wpaic_rate_limit';

				showError(instance, msg || wpaicChatData.i18n.error_generic);

				// Show rate limit fallback contact options if provided.
				if (isRateLimit && errData) {
					showRateLimitFallback(instance, errData);
					// Keep input disabled — server will reject further requests within the window.
					instance.state = 'open';
				} else {
					instance.state = 'open';
					setInputDisabled(instance, false);
				}
			},
		};

		sendMessage(instance, message, callbacks);
	}

	// =========================================================================
	// GDPR consent gating
	// =========================================================================

	function initGdprConsent(instance) {
		var consentBanner = qs('.wpaic-consent', instance.panel);
		if (!consentBanner) {
			instance.gdprAccepted = true;
			return;
		}

		// Check if already accepted (stored in localStorage).
		var storageKey = 'wpaic_gdpr_' + instance.botSlug;
		try {
			if (localStorage.getItem(storageKey) === '1') {
				instance.gdprAccepted = true;
				consentBanner.style.display = 'none';
				return;
			}
		} catch (e) {
			// ignore
		}

		// Disable input until consent.
		setInputDisabled(instance, true);
		instance.gdprAccepted = false;

		var acceptBtn = qs('.wpaic-consent__accept', consentBanner);
		if (acceptBtn) {
			acceptBtn.addEventListener('click', function () {
				instance.gdprAccepted = true;
				try {
					localStorage.setItem(storageKey, '1');
				} catch (e) {}
				consentBanner.style.display = 'none';
				setInputDisabled(instance, false);
				var inp = qs('.wpaic-input', instance.panel);
				if (inp) inp.focus();
			});
		}
	}

	// =========================================================================
	// Widget open/close toggle
	// =========================================================================

	function openWidget(instance) {
		if (instance.state !== 'idle') return;
		instance.state = 'open';
		instance.panel.setAttribute('aria-hidden', 'false');
		instance.panel.style.display = 'flex';
		instance.launcher.setAttribute('aria-expanded', 'true');
		instance.launcher.setAttribute('aria-label', wpaicChatData.i18n.close_chat);

		// Activate focus trap within the panel.
		if (instance.focusTrapCleanup) instance.focusTrapCleanup();
		instance.focusTrapCleanup = trapFocus(instance.panel);

		// Focus first interactive element.
		setTimeout(function () {
			var inp = qs('.wpaic-input', instance.panel);
			if (!instance.gdprAccepted) {
				var btn = qs('.wpaic-consent__accept', instance.panel);
				if (btn) btn.focus();
			} else if (inp) {
				inp.focus();
			}
		}, TYPING_DELAY_MS);

		/**
		 * Custom event: wpaic:open — fired on the panel element.
		 */
		instance.panel.dispatchEvent(new CustomEvent('wpaic:open', { bubbles: true }));
	}

	function closeWidget(instance) {
		if (instance.state === 'idle') return;
		instance.state = 'idle';
		instance.panel.setAttribute('aria-hidden', 'true');
		instance.panel.style.display = 'none';
		instance.launcher.setAttribute('aria-expanded', 'false');
		instance.launcher.setAttribute('aria-label', wpaicChatData.i18n.open_chat);

		// Release focus trap.
		if (instance.focusTrapCleanup) {
			instance.focusTrapCleanup();
			instance.focusTrapCleanup = null;
		}

		instance.launcher.focus();

		instance.panel.dispatchEvent(new CustomEvent('wpaic:close', { bubbles: true }));
	}

	// =========================================================================
	// Widget initialisation
	// =========================================================================

	function initWidget(widgetEl) {
		var instanceId = widgetEl.getAttribute(INSTANCE_ATTR);
		if (!instanceId || instances[instanceId]) return; // already initted

		var launcher = qs('.wpaic-launcher', widgetEl);
		var panel = qs('.wpaic-panel', widgetEl);
		var form = qs('.wpaic-form', widgetEl);
		var input = qs('.wpaic-input', widgetEl);

		if (!launcher || !panel || !form || !input) {
			return; // malformed widget HTML
		}

		var botSlug = widgetEl.getAttribute('data-bot-slug') || 'default';

		var instance = {
			id: instanceId,
			widgetEl: widgetEl,
			launcher: launcher,
			panel: panel,
			botSlug: botSlug,
			sessionToken: getSessionToken(botSlug),
			state: 'idle', // 'idle' | 'open' | 'typing' | 'streaming'
			gdprAccepted: true,
			focusTrapCleanup: null,
		};

		instances[instanceId] = instance;

		// Init GDPR consent gate.
		initGdprConsent(instance);

		// Launcher toggle.
		launcher.addEventListener('click', function () {
			if (instance.state === 'idle') {
				openWidget(instance);
			} else {
				closeWidget(instance);
			}
		});

		// Close button inside panel.
		var closeBtn = qs('.wpaic-close-btn', panel);
		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				closeWidget(instance);
			});
		}

		// Form submission.
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!instance.gdprAccepted) return;
			var msg = input.value;
			submitMessage(instance, msg);
		});

		// Enter key (no shift) submits.
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				if (!instance.gdprAccepted) return;
				submitMessage(instance, input.value);
			}
		});

		// Keyboard trap: Escape closes the panel.
		panel.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closeWidget(instance);
			}
		});

		// Inline mode: auto-open (no launcher).
		if (widgetEl.getAttribute('data-mode') === 'inline') {
			openWidget(instance);
		}
	}

	// =========================================================================
	// Inline shortcode widgets (embedded in page content)
	// =========================================================================

	function initInlineWidgets() {
		qsa('[' + INSTANCE_ATTR + ']').forEach(function (el) {
			initWidget(el);
		});
	}

	// =========================================================================
	// Public API (window.WPAICChat)
	// =========================================================================

	var WPAICChat = {
		version: VERSION,
		instances: instances,

		/**
		 * Manually initialise a widget by DOM element or instance ID.
		 *
		 * @param {Element|string} target Widget element or INSTANCE_ATTR value.
		 */
		init: function (target) {
			if (typeof target === 'string') {
				var el = qs('[' + INSTANCE_ATTR + '="' + target + '"]');
				if (el) initWidget(el);
			} else if (target && target.nodeType) {
				initWidget(target);
			}
		},

		/**
		 * Open a widget by instance ID.
		 *
		 * @param {string} instanceId
		 */
		open: function (instanceId) {
			var inst = instances[instanceId];
			if (inst) openWidget(inst);
		},

		/**
		 * Close a widget by instance ID.
		 *
		 * @param {string} instanceId
		 */
		close: function (instanceId) {
			var inst = instances[instanceId];
			if (inst) closeWidget(inst);
		},

		/**
		 * Clear session for a bot slug (forces new session on next message).
		 *
		 * @param {string} botSlug
		 */
		clearSession: function (botSlug) {
			clearSessionToken(botSlug);
		},
	};

	// =========================================================================
	// Boot on DOMContentLoaded
	// =========================================================================

	function boot() {
		// Ensure wpaicChatData is available (wp_localize_script guard).
		if (typeof wpaicChatData === 'undefined') {
			return;
		}

		// Default i18n strings if not localised.
		wpaicChatData.i18n = wpaicChatData.i18n || {};
		wpaicChatData.i18n.open_chat = wpaicChatData.i18n.open_chat || 'Open chat';
		wpaicChatData.i18n.close_chat = wpaicChatData.i18n.close_chat || 'Close chat';
		wpaicChatData.i18n.error_generic = wpaicChatData.i18n.error_generic || 'An error occurred. Please try again.';
		wpaicChatData.i18n.error_network =
			wpaicChatData.i18n.error_network || 'Network error. Please check your connection.';
		wpaicChatData.i18n.typing = wpaicChatData.i18n.typing || 'Typing\u2026';

		initInlineWidgets();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	// Expose public API.
	global.WPAICChat = WPAICChat;
})(window);
