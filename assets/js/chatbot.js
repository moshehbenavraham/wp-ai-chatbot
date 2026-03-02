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

((global) => {
	// =========================================================================
	// Module constants
	// =========================================================================

	const VERSION = '1.0.0';
	const INSTANCE_ATTR = 'data-wpaic-instance';
	const SESSION_KEY_PREFIX = 'wpaic_session_';
	const COOKIE_KEY_PREFIX = 'wpaic_sid_';
	const COOKIE_DAYS = 30;
	const TYPING_DELAY_MS = 300;
	const MAX_SUGGESTIONS = 5;

	// =========================================================================
	// Instance registry (supports multiple widgets per page)
	// =========================================================================

	const instances = {};

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
		const el = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach((k) => {
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
			children.forEach((c) => {
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
		const key = SESSION_KEY_PREFIX + botSlug;
		try {
			return localStorage.getItem(key) || '';
		} catch (_e) {
			return getCookie(COOKIE_KEY_PREFIX + botSlug);
		}
	}

	function setSessionToken(botSlug, token) {
		const key = SESSION_KEY_PREFIX + botSlug;
		try {
			localStorage.setItem(key, token);
		} catch (_e) {
			// Fall back to cookie.
		}
		// Always write cookie as backup.
		setCookie(COOKIE_KEY_PREFIX + botSlug, token, COOKIE_DAYS);
	}

	function clearSessionToken(botSlug) {
		try {
			localStorage.removeItem(SESSION_KEY_PREFIX + botSlug);
		} catch (_e) {
			// ignore
		}
		setCookie(COOKIE_KEY_PREFIX + botSlug, '', -1);
	}

	function getCookie(name) {
		const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function setCookie(name, value, days) {
		let expires = '';
		if (days) {
			const d = new Date();
			d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
			expires = `; expires=${d.toUTCString()}`;
		}
		// biome-ignore lint/suspicious/noDocumentCookie: required for cookie fallback session storage
		document.cookie = `${name}=${encodeURIComponent(value)}${expires}; path=/; SameSite=Lax`;
	}

	// =========================================================================
	// XSS-safe HTML sanitization (DOMParser allowlist)
	// =========================================================================

	const ALLOWED_TAGS = [
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

	const ALLOWED_ATTRS = {
		A: ['href', 'title', 'target', 'rel'],
		TD: ['colspan', 'rowspan'],
		TH: ['scope', 'colspan', 'rowspan'],
		OL: ['start'],
		'*': [],
	};

	const ALLOWED_HREF_SCHEMES = /^(https?:\/\/|mailto:|tel:|\/|#)/i;

	function sanitizeBotHtml(html) {
		const parser = new DOMParser();
		const doc = parser.parseFromString(`<body>${html}</body>`, 'text/html');
		sanitizeNode(doc.body);
		return doc.body.innerHTML;
	}

	function sanitizeNode(node) {
		const children = Array.prototype.slice.call(node.childNodes);
		children.forEach((child) => {
			if (child.nodeType === 1) {
				// Element node.
				const tag = child.tagName.toUpperCase();
				if (ALLOWED_TAGS.indexOf(tag) === -1) {
					// Replace disallowed element with its text content.
					const text = document.createTextNode(child.textContent || '');
					node.replaceChild(text, child);
					return;
				}

				// Strip disallowed attributes.
				const attrsToRemove = [];
				const allowedForTag = ALLOWED_ATTRS[tag] || ALLOWED_ATTRS['*'];
				const globalAllowed = ['class']; // class is always allowed for styling
				Array.prototype.slice.call(child.attributes).forEach((attr) => {
					const name = attr.name.toLowerCase();
					if (allowedForTag.indexOf(name) === -1 && globalAllowed.indexOf(name) === -1) {
						attrsToRemove.push(name);
					}
				});
				attrsToRemove.forEach((a) => {
					child.removeAttribute(a);
				});

				// Enforce safe link attributes.
				if (tag === 'A') {
					const href = child.getAttribute('href') || '';
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
		const data = new FormData();
		data.append('action', 'wpaic_chat');
		data.append('nonce', wpaicChatData.nonce);
		data.append('message', message);
		data.append('bot_slug', instance.botSlug);
		data.append('session_token', instance.sessionToken || '');

		const ctx = extractPageContext();
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
		const url = wpaicChatData.ajaxUrl;

		if (wpaicChatData.streamEnabled) {
			// SSE streaming via fetch + ReadableStream.
			sendStream(instance, message, url, callbacks);
		} else {
			// Standard AJAX JSON response.
			sendAjax(instance, message, url, callbacks);
		}
	}

	function sendAjax(instance, message, url, callbacks) {
		const xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.onload = () => {
			// Parse WP JSON body for both success (2xx) and error (4xx/5xx) responses.
			try {
				const data = JSON.parse(xhr.responseText);
				if (data.success) {
					if (data.data.session_token) {
						instance.sessionToken = data.data.session_token;
						setSessionToken(instance.botSlug, instance.sessionToken);
					}
					callbacks.onDone(data.data.html || '', data.data.suggestions || []);
				} else {
					const errPayload = data.data && typeof data.data === 'object' ? data.data : {};
					callbacks.onError(errPayload.message || wpaicChatData.i18n.error_generic, errPayload);
				}
			} catch (_e) {
				callbacks.onError(wpaicChatData.i18n.error_generic, {});
			}
		};
		xhr.onerror = () => {
			callbacks.onError(wpaicChatData.i18n.error_network, {});
		};
		xhr.send(buildFormData(instance, message));
	}

	function sendStream(instance, message, url, callbacks) {
		const body = buildFormData(instance, message);
		let buffer = '';

		fetch(url, { method: 'POST', body: body })
			.then((response) => {
				if (!response.ok) {
					throw new Error(`HTTP ${response.status}`);
				}
				const reader = response.body.getReader();
				const decoder = new TextDecoder();

				function readChunk() {
					return reader.read().then((result) => {
						if (result.done) {
							return;
						}
						buffer += decoder.decode(result.value, { stream: true });

						// Process complete SSE lines.
						const lines = buffer.split('\n');
						buffer = lines.pop(); // keep incomplete last line

						lines.forEach((line) => {
							if (line.indexOf('data: ') === 0) {
								const payload = line.slice(6).trim();
								if (payload === '[DONE]') {
									return;
								}
								try {
									const chunk = JSON.parse(payload);
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
								} catch (_e) {
									// Malformed chunk — skip.
								}
							}
						});

						return readChunk();
					});
				}

				return readChunk();
			})
			.catch(() => {
				// Fallback: try sync AJAX.
				sendAjax(instance, message, url, callbacks);
			});
	}

	// =========================================================================
	// Focus trap helpers
	// =========================================================================

	const FOCUSABLE_SELECTOR =
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
			const focusable = Array.prototype.slice.call(container.querySelectorAll(FOCUSABLE_SELECTOR));
			if (focusable.length === 0) return;

			const first = focusable[0];
			const last = focusable[focusable.length - 1];

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
		const transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return;

		const row = ce('div', { className: `wpaic-message wpaic-message--${role}` });
		const bubble = ce('div', { className: 'wpaic-bubble' });

		if (role === 'user') {
			// User messages: always plain text to prevent DOM XSS.
			bubble.textContent = htmlOrText;
		} else {
			// Bot messages: sanitized HTML.
			const safeHtml = isHtml ? sanitizeBotHtml(htmlOrText) : sanitizeBotHtml(`<p>${escapeHtml(htmlOrText)}</p>`);
			bubble.innerHTML = safeHtml;
		}

		row.appendChild(bubble);
		transcript.appendChild(row);
		transcript.scrollTop = transcript.scrollHeight;

		return row;
	}

	function addTypingIndicator(instance) {
		const transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return null;

		const row = ce('div', { className: 'wpaic-message wpaic-message--bot wpaic-message--typing' });
		const bubble = ce('div', { className: 'wpaic-bubble', 'aria-label': wpaicChatData.i18n.typing || 'Typing…' });
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
		if (typingEl?.parentNode) {
			typingEl.parentNode.removeChild(typingEl);
		}
	}

	function showSuggestions(instance, suggestions) {
		const container = qs('.wpaic-suggestions', instance.panel);
		if (!container) return;

		container.innerHTML = '';

		if (!suggestions || suggestions.length === 0) {
			container.style.display = 'none';
			return;
		}

		container.style.display = 'flex';
		suggestions.slice(0, MAX_SUGGESTIONS).forEach((text) => {
			const chip = ce('button', {
				className: 'wpaic-suggestion-chip',
				type: 'button',
				textContent: text,
			});
			chip.addEventListener('click', () => {
				if (instance.state !== 'idle' && instance.state !== 'open') return;
				container.style.display = 'none';
				submitMessage(instance, text);
			});
			container.appendChild(chip);
		});
	}

	function setInputDisabled(instance, disabled) {
		const input = qs('.wpaic-input', instance.panel);
		const btn = qs('.wpaic-send-btn', instance.panel);
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
		const transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return;

		const row = ce('div', { className: 'wpaic-message wpaic-message--error' });
		const bubble = ce('div', { className: 'wpaic-bubble' });
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
		const email = errData.fallback_email || '';
		const phone = errData.fallback_phone || '';
		const url = errData.fallback_url || '';

		if (!email && !phone && !url) {
			return; // No fallback configured — nothing to show.
		}

		const transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return;

		const row = ce('div', { className: 'wpaic-message wpaic-message--fallback' });
		const bubble = ce('div', { className: 'wpaic-bubble' });
		const links = [];

		if (email) {
			links.push(
				ce('a', {
					href: `mailto:${email}`,
					target: '_blank',
					rel: 'noopener noreferrer',
					textContent: email,
				}),
			);
		}
		if (phone) {
			links.push(
				ce('a', {
					href: `tel:${phone.replace(/\s/g, '')}`,
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

		links.forEach((link, idx) => {
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
		const d = document.createElement('div');
		d.textContent = str;
		return d.innerHTML;
	}

	// =========================================================================
	// Streaming bubble
	// =========================================================================

	function createStreamBubble(instance) {
		const transcript = qs('.wpaic-transcript', instance.panel);
		if (!transcript) return null;

		const row = ce('div', { className: 'wpaic-message wpaic-message--bot wpaic-message--streaming' });
		const bubble = ce('div', { className: 'wpaic-bubble' });
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
		const input = qs('.wpaic-input', instance.panel);
		if (input) input.value = '';

		// Show typing indicator.
		let typingEl = addTypingIndicator(instance);

		let streamBubble = null;

		const callbacks = {
			onChunk: (text) => {
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
					const transcript = qs('.wpaic-transcript', instance.panel);
					if (transcript) transcript.scrollTop = transcript.scrollHeight;
				}
			},
			onDone: (html, suggestions) => {
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
				const inp = qs('.wpaic-input', instance.panel);
				if (inp) inp.focus();
			},
			onError: (msg, errData) => {
				removeTypingIndicator(typingEl);
				typingEl = null;
				if (streamBubble?.row.parentNode) {
					streamBubble.row.parentNode.removeChild(streamBubble.row);
					streamBubble = null;
				}

				const isRateLimit = errData && errData.code === 'wpaic_rate_limit';

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
		const consentBanner = qs('.wpaic-consent', instance.panel);
		if (!consentBanner) {
			instance.gdprAccepted = true;
			return;
		}

		// Check if already accepted (stored in localStorage).
		const storageKey = `wpaic_gdpr_${instance.botSlug}`;
		try {
			if (localStorage.getItem(storageKey) === '1') {
				instance.gdprAccepted = true;
				consentBanner.style.display = 'none';
				return;
			}
		} catch (_e) {
			// ignore
		}

		// Disable input until consent.
		setInputDisabled(instance, true);
		instance.gdprAccepted = false;

		const acceptBtn = qs('.wpaic-consent__accept', consentBanner);
		if (acceptBtn) {
			acceptBtn.addEventListener('click', () => {
				instance.gdprAccepted = true;
				try {
					localStorage.setItem(storageKey, '1');
				} catch (_e) {}
				consentBanner.style.display = 'none';
				setInputDisabled(instance, false);
				const inp = qs('.wpaic-input', instance.panel);
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
		setTimeout(() => {
			const inp = qs('.wpaic-input', instance.panel);
			if (!instance.gdprAccepted) {
				const btn = qs('.wpaic-consent__accept', instance.panel);
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
		const instanceId = widgetEl.getAttribute(INSTANCE_ATTR);
		if (!instanceId || instances[instanceId]) return; // already initted

		const launcher = qs('.wpaic-launcher', widgetEl);
		const panel = qs('.wpaic-panel', widgetEl);
		const form = qs('.wpaic-form', widgetEl);
		const input = qs('.wpaic-input', widgetEl);

		if (!launcher || !panel || !form || !input) {
			return; // malformed widget HTML
		}

		const botSlug = widgetEl.getAttribute('data-bot-slug') || 'default';

		const instance = {
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
		launcher.addEventListener('click', () => {
			if (instance.state === 'idle') {
				openWidget(instance);
			} else {
				closeWidget(instance);
			}
		});

		// Close button inside panel.
		const closeBtn = qs('.wpaic-close-btn', panel);
		if (closeBtn) {
			closeBtn.addEventListener('click', () => {
				closeWidget(instance);
			});
		}

		// Form submission.
		form.addEventListener('submit', (e) => {
			e.preventDefault();
			if (!instance.gdprAccepted) return;
			const msg = input.value;
			submitMessage(instance, msg);
		});

		// Enter key (no shift) submits.
		input.addEventListener('keydown', (e) => {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				if (!instance.gdprAccepted) return;
				submitMessage(instance, input.value);
			}
		});

		// Keyboard trap: Escape closes the panel.
		panel.addEventListener('keydown', (e) => {
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
		qsa(`[${INSTANCE_ATTR}]`).forEach((el) => {
			initWidget(el);
		});
	}

	// =========================================================================
	// Public API (window.WPAICChat)
	// =========================================================================

	const WPAICChat = {
		version: VERSION,
		instances: instances,

		/**
		 * Manually initialise a widget by DOM element or instance ID.
		 *
		 * @param {Element|string} target Widget element or INSTANCE_ATTR value.
		 */
		init: (target) => {
			if (typeof target === 'string') {
				const el = qs(`[${INSTANCE_ATTR}="${target}"]`);
				if (el) initWidget(el);
			} else if (target?.nodeType) {
				initWidget(target);
			}
		},

		/**
		 * Open a widget by instance ID.
		 *
		 * @param {string} instanceId
		 */
		open: (instanceId) => {
			const inst = instances[instanceId];
			if (inst) openWidget(inst);
		},

		/**
		 * Close a widget by instance ID.
		 *
		 * @param {string} instanceId
		 */
		close: (instanceId) => {
			const inst = instances[instanceId];
			if (inst) closeWidget(inst);
		},

		/**
		 * Clear session for a bot slug (forces new session on next message).
		 *
		 * @param {string} botSlug
		 */
		clearSession: (botSlug) => {
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
