/**
 * WP AI Chatbot — Admin UI Runtime
 * Phase 13: Interaction Patterns & Animation
 *
 * Architecture: Vanilla JS IIFE module — no jQuery, no build tools.
 * Namespace: window.WPAICAdmin
 *
 * Modules:
 *   toast    — transient notifications (bottom-right, auto-dismiss 3s)
 *   banner   — persistent banners (top of content, manual dismiss)
 *   modal    — confirmation dialogs for destructive actions (focus trap)
 *   drawer   — slide-in panels for routine settings edits (focus trap)
 *   validate — form field validation (blur + submit)
 *   validators — composable validator factory functions
 *   autosave — debounced auto-save with spinner + 800ms success state
 *   loading  — skeleton, inline spinner, and delayed progress bar
 *
 * Animation constraints (enforced across all modules):
 *   - Max 3 simultaneous animations per viewport quadrant.
 *   - Scroll reveals: minimum 600ms duration.
 *   - Easing: cubic-bezier only.
 *   - 60fps target: transform + opacity only in keyframes.
 *   - prefers-reduced-motion handled via CSS; JS skips animation-class toggles
 *     when the media query matches.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

((global) => {
	// =========================================================================
	// Module constants
	// =========================================================================

	const AUTOSAVE_DEBOUNCE_MS = 500;
	const AUTOSAVE_SUCCESS_MS = 800;
	const TOAST_DEFAULT_DURATION = 3000; // 3s default; 5s max recommended
	const PROGRESS_BAR_DELAY_MS = 3000; // Progress bar appears after this delay
	const MAX_CONCURRENT_ANIMS = 3; // Per viewport quadrant
	const SCROLL_REVEAL_DURATION = 600; // Must match --wpaic-dur-reveal in CSS
	const ANIM_EXIT_FALLBACK_MS = 400; // Safety timeout for exit animations

	// =========================================================================
	// Reduced-motion query (read once, stable for the session)
	// =========================================================================

	const prefersReducedMotion = global.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

	// =========================================================================
	// Animation concurrency controller
	// Partitions the viewport into a 2×2 grid (TL, TR, BL, BR) and tracks
	// how many animations are active in each region.
	// =========================================================================

	const animCounts = { TL: 0, TR: 0, BL: 0, BR: 0 };

	/**
	 * Return the viewport quadrant key for an element's current position.
	 *
	 * @param  {Element} el
	 * @return {string}  'TL' | 'TR' | 'BL' | 'BR'
	 */
	function quadrantOf(el) {
		const rect = el.getBoundingClientRect();
		const col = rect.left < global.innerWidth / 2 ? 'L' : 'R';
		const row = rect.top < global.innerHeight / 2 ? 'T' : 'B';
		return row + col;
	}

	/**
	 * Return true if the element's quadrant has capacity for another animation.
	 *
	 * @param  {Element} el
	 * @return {boolean}
	 */
	function canAnimate(el) {
		if (prefersReducedMotion) return true; // Reduced-motion = instant, no cap needed.
		return (animCounts[quadrantOf(el)] || 0) < MAX_CONCURRENT_ANIMS;
	}

	/**
	 * Register an animation of `durationMs` in the element's quadrant,
	 * auto-releasing after that duration.
	 *
	 * @param {Element} el
	 * @param {number}  durationMs
	 */
	function trackAnimation(el, durationMs) {
		const key = quadrantOf(el);
		animCounts[key] = (animCounts[key] || 0) + 1;
		setTimeout(() => {
			animCounts[key] = Math.max(0, (animCounts[key] || 1) - 1);
		}, durationMs);
	}

	// =========================================================================
	// DOM helpers
	// =========================================================================

	function qs(selector, context) {
		return (context || document).querySelector(selector);
	}

	function qsa(selector, context) {
		return Array.prototype.slice.call((context || document).querySelectorAll(selector));
	}

	/**
	 * Create a DOM element with optional attributes and child nodes.
	 *
	 * @param  {string}            tag
	 * @param  {object|null}       [attrs]
	 * @param  {Array|null}        [children]  Strings or Elements.
	 * @return {Element}
	 */
	function ce(tag, attrs, children) {
		const el = document.createElement(tag);

		if (attrs) {
			Object.keys(attrs).forEach((k) => {
				if (k === 'className') {
					el.className = attrs[k];
				} else if (k === 'textContent') {
					el.textContent = attrs[k];
				} else if (k === 'innerHTML') {
					el.innerHTML = attrs[k]; // Caller is responsible for safety.
				} else {
					el.setAttribute(k, attrs[k]);
				}
			});
		}

		if (children) {
			children.forEach((c) => {
				if (typeof c === 'string') {
					el.appendChild(document.createTextNode(c));
				} else if (c?.nodeType) {
					el.appendChild(c);
				}
			});
		}

		return el;
	}

	/**
	 * Return an inline SVG element for the given icon type.
	 * All paths are trusted static strings — no user input involved.
	 *
	 * @param  {string}       type  'success'|'error'|'warning'|'info'|'close'|'check'
	 * @return {Element|null}
	 */
	function svgIcon(type) {
		const paths = {
			success:
				'<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
			error: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>',
			warning:
				'<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
			info: '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>',
			close: '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>',
			check: '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>',
		};

		if (!paths[type]) return null;

		const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('viewBox', '0 0 20 20');
		svg.setAttribute('fill', 'currentColor');
		svg.setAttribute('width', '18');
		svg.setAttribute('height', '18');
		svg.setAttribute('aria-hidden', 'true');
		svg.innerHTML = paths[type];
		return svg;
	}

	/**
	 * Attach a one-time animationend listener plus a fallback timeout.
	 * The callback is called once — whichever fires first.
	 *
	 * @param {Element}  el
	 * @param {Function} callback
	 * @param {number}   fallbackMs
	 */
	function onAnimEnd(el, callback, fallbackMs) {
		let called = false;

		function done() {
			if (called) return;
			called = true;
			el.removeEventListener('animationend', done);
			callback();
		}

		el.addEventListener('animationend', done, { once: true });
		setTimeout(done, fallbackMs || ANIM_EXIT_FALLBACK_MS);
	}

	// =========================================================================
	// Toast module
	// Transient notifications — bottom-right, auto-dismiss (default 3s).
	// =========================================================================

	let _toastContainer = null;

	function getToastContainer() {
		if (!_toastContainer) {
			_toastContainer = ce('div', {
				id: 'wpaic-toast-container',
				role: 'log',
				'aria-live': 'polite',
				'aria-atomic': 'false',
				'aria-label': 'Notifications',
			});
			document.body.appendChild(_toastContainer);
		}
		return _toastContainer;
	}

	const toast = {
		/**
		 * Show a toast notification.
		 *
		 * @param {string} message  Text to display.
		 * @param {string} [type]   'success'|'error'|'warning'|'info'  Default: 'info'
		 * @param {object} [opts]
		 *   @param {string}  opts.title        Optional bold title line.
		 *   @param {number}  opts.duration     Auto-dismiss delay ms. 0 = sticky. Default: 3000
		 *   @param {boolean} opts.dismissible  Show × button. Default: true
		 * @return {Element}  The toast element.
		 */
		show: (message, type, opts) => {
			type = type || 'info';
			opts = opts || {};

			const duration = opts.duration !== undefined ? opts.duration : TOAST_DEFAULT_DURATION;
			const dismissible = opts.dismissible !== false;

			const el = ce('div', {
				className: `wpaic-toast wpaic-toast--${type}`,
				role: 'status',
				'aria-live': 'assertive',
				'aria-atomic': 'true',
			});

			// Icon.
			const iconEl = ce('span', { className: `wpaic-toast__icon wpaic-toast__icon--${type}` });
			const iconNode = svgIcon(type);
			if (iconNode) iconEl.appendChild(iconNode);
			el.appendChild(iconEl);

			// Body.
			const bodyEl = ce('div', { className: 'wpaic-toast__body' });
			if (opts.title) {
				bodyEl.appendChild(ce('div', { className: 'wpaic-toast__title', textContent: opts.title }));
			}
			bodyEl.appendChild(ce('div', { className: 'wpaic-toast__message', textContent: message }));
			el.appendChild(bodyEl);

			// Dismiss button.
			if (dismissible) {
				const dismissBtn = ce('button', {
					className: 'wpaic-toast__dismiss',
					type: 'button',
					'aria-label': 'Dismiss notification',
				});
				const closeIcon = svgIcon('close');
				if (closeIcon) dismissBtn.appendChild(closeIcon);
				dismissBtn.addEventListener('click', () => {
					toast._dismiss(el);
				});
				el.appendChild(dismissBtn);
			}

			getToastContainer().appendChild(el);

			// Auto-dismiss.
			if (duration > 0) {
				setTimeout(() => {
					toast._dismiss(el);
				}, duration);
			}

			return el;
		},

		/** @private */
		_dismiss: (el) => {
			if (!el.parentNode) return;
			el.classList.add('wpaic-toast--exiting');
			onAnimEnd(
				el,
				() => {
					if (el.parentNode) el.parentNode.removeChild(el);
				},
				350,
			);
		},
	};

	// =========================================================================
	// Banner module
	// Persistent in-page banners — top of content area, manual dismiss.
	// =========================================================================

	const banner = {
		/**
		 * Insert a dismissible banner at the top of a container element.
		 *
		 * @param {Element} container  Target element (e.g. `.wrap.wpaic-admin-page`).
		 * @param {string}  message    Banner text. HTML is accepted (admin context only).
		 * @param {string}  [type]     'success'|'error'|'warning'|'info'  Default: 'info'
		 * @param {object}  [opts]
		 *   @param {boolean} opts.dismissible  Default: true
		 * @return {Element}  The banner element.
		 */
		show: (container, message, type, opts) => {
			type = type || 'info';
			opts = opts || {};
			const dismissible = opts.dismissible !== false;

			const el = ce('div', {
				className: `wpaic-banner wpaic-banner--${type}`,
				role: 'alert',
			});

			const bodyEl = ce('div', { className: 'wpaic-banner__body' });
			bodyEl.innerHTML = message; // Admin context — caller controls content.
			el.appendChild(bodyEl);

			if (dismissible) {
				const dismissBtn = ce('button', {
					className: 'wpaic-banner__dismiss',
					type: 'button',
					'aria-label': 'Dismiss',
				});
				const closeIcon = svgIcon('close');
				if (closeIcon) dismissBtn.appendChild(closeIcon);
				dismissBtn.addEventListener('click', () => {
					banner.dismiss(el);
				});
				el.appendChild(dismissBtn);
			}

			// Prepend so it appears above page content.
			container.insertBefore(el, container.firstChild);

			return el;
		},

		/**
		 * Remove a banner element from the DOM.
		 *
		 * @param {Element} el  The banner element returned by banner.show().
		 */
		dismiss: (el) => {
			if (el?.parentNode) {
				el.parentNode.removeChild(el);
			}
		},
	};

	// =========================================================================
	// Modal module
	// Confirmation dialogs for destructive actions only. Returns a Promise.
	// =========================================================================

	const modal = {
		_triggerEl: null,

		/**
		 * Show a confirmation modal.
		 *
		 * @param {object} opts
		 *   @param {string}  opts.title         Heading text.
		 *   @param {string}  opts.message       Body text.
		 *   @param {string}  opts.confirmLabel  Confirm button label (explicit — not "OK").
		 *   @param {string}  opts.cancelLabel   Cancel button label. Default: 'Cancel'
		 *   @param {boolean} opts.destructive   true = red confirm button. Default: true
		 * @return {Promise<boolean>}  Resolves true on confirm, false on cancel/escape.
		 */
		confirm: (opts) => {
			opts = opts || {};

			return new Promise((resolve) => {
				// Store focus so we can restore it on close.
				modal._triggerEl = document.activeElement;

				// Backdrop overlay.
				const overlay = ce('div', {
					className: 'wpaic-modal-overlay',
					role: 'dialog',
					'aria-modal': 'true',
					'aria-labelledby': 'wpaic-modal-title',
					'aria-describedby': 'wpaic-modal-desc',
				});

				// Modal panel.
				const modalEl = ce('div', { className: 'wpaic-modal' });

				// Header.
				const header = ce('div', { className: 'wpaic-modal__header' });
				header.appendChild(
					ce('h2', {
						className: 'wpaic-modal__title',
						id: 'wpaic-modal-title',
						textContent: opts.title || 'Confirm action',
					}),
				);
				modalEl.appendChild(header);

				// Body.
				modalEl.appendChild(
					ce('p', {
						className: 'wpaic-modal__body',
						id: 'wpaic-modal-desc',
						textContent: opts.message || '',
					}),
				);

				// Footer.
				const footer = ce('div', { className: 'wpaic-modal__footer' });

				const cancelBtn = ce('button', {
					className: 'wpaic-modal__btn-cancel',
					type: 'button',
					textContent: opts.cancelLabel || 'Cancel',
				});

				let confirmClass = 'wpaic-modal__btn-confirm';
				if (opts.destructive === false) {
					confirmClass += ' wpaic-modal__btn-confirm--primary';
				}
				const confirmBtn = ce('button', {
					className: confirmClass,
					type: 'button',
					textContent: opts.confirmLabel || 'Confirm',
				});

				footer.appendChild(cancelBtn);
				footer.appendChild(confirmBtn);
				modalEl.appendChild(footer);
				overlay.appendChild(modalEl);
				document.body.appendChild(overlay);

				// Default focus: cancel button (safer for destructive modals).
				setTimeout(() => {
					cancelBtn.focus();
				}, 50);

				// Focus trap — cycle between cancel and confirm only.
				const focusable = [cancelBtn, confirmBtn];

				overlay.addEventListener('keydown', (e) => {
					if (e.key === 'Escape') {
						closeModal(false);
						return;
					}
					if (e.key === 'Tab') {
						let idx = focusable.indexOf(document.activeElement);
						idx = e.shiftKey
							? (idx - 1 + focusable.length) % focusable.length
							: (idx + 1) % focusable.length;
						focusable[idx].focus();
						e.preventDefault();
					}
				});

				// Backdrop click closes with cancel.
				overlay.addEventListener('click', (e) => {
					if (e.target === overlay) closeModal(false);
				});

				cancelBtn.addEventListener('click', () => {
					closeModal(false);
				});
				confirmBtn.addEventListener('click', () => {
					closeModal(true);
				});

				function closeModal(confirmed) {
					overlay.classList.add('wpaic-modal-overlay--exiting');
					onAnimEnd(overlay, () => {
						if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
					});
					// Restore focus to the triggering element.
					if (modal._triggerEl && typeof modal._triggerEl.focus === 'function') {
						modal._triggerEl.focus();
					}
					resolve(confirmed);
				}
			});
		},
	};

	// =========================================================================
	// Drawer module
	// Slide-in panel for routine settings edits. Supports focus trap + Escape.
	// =========================================================================

	const drawer = {
		_overlay: null,
		_triggerEl: null,
		_focusTrapCleanup: null,

		/**
		 * Open a slide-in drawer.
		 *
		 * @param {object} opts
		 *   @param {string}   opts.title    Panel heading.
		 *   @param {Element}  opts.content  DOM subtree to place in the drawer body.
		 *   @param {Function} [opts.onClose] Callback fired after drawer fully closes.
		 */
		open: (opts) => {
			opts = opts || {};

			// Close any already-open drawer first.
			if (drawer._overlay) {
				drawer.close();
			}

			drawer._triggerEl = document.activeElement;

			const overlay = ce('div', { className: 'wpaic-drawer-overlay' });
			const drawerEl = ce('div', {
				className: 'wpaic-drawer',
				role: 'dialog',
				'aria-modal': 'true',
				'aria-label': opts.title || 'Settings panel',
			});

			// Header.
			const header = ce('div', { className: 'wpaic-drawer__header' });
			header.appendChild(ce('h2', { className: 'wpaic-drawer__title', textContent: opts.title || '' }));

			const closeBtn = ce('button', {
				className: 'wpaic-drawer__close',
				type: 'button',
				'aria-label': 'Close panel',
			});
			const closeIcon = svgIcon('close');
			if (closeIcon) closeBtn.appendChild(closeIcon);
			header.appendChild(closeBtn);
			drawerEl.appendChild(header);

			// Body.
			const bodyEl = ce('div', { className: 'wpaic-drawer__body' });
			if (opts.content) bodyEl.appendChild(opts.content);
			drawerEl.appendChild(bodyEl);

			overlay.appendChild(drawerEl);
			document.body.appendChild(overlay);
			drawer._overlay = overlay;

			// Install full Tab focus trap within the drawer.
			// FOCUSABLE_SELECTOR is not yet defined at this point in code order,
			// but installFocusTrap() is defined below and closures work at call time.
			drawer._focusTrapCleanup = installFocusTrap(drawerEl);

			// Focus first interactive element inside the drawer.
			setTimeout(() => {
				const firstFocusable = qs(
					'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
					drawerEl,
				);
				if (firstFocusable && firstFocusable !== closeBtn) {
					firstFocusable.focus();
				} else {
					closeBtn.focus();
				}
			}, 50);

			// Escape closes the drawer.
			drawerEl.addEventListener('keydown', (e) => {
				if (e.key === 'Escape') drawer.close(opts.onClose);
			});

			// Backdrop click closes.
			overlay.addEventListener('click', (e) => {
				if (e.target === overlay) drawer.close(opts.onClose);
			});

			closeBtn.addEventListener('click', () => {
				drawer.close(opts.onClose);
			});
		},

		/**
		 * Close the currently open drawer (if any).
		 *
		 * @param {Function} [callback]  Called after the close animation finishes.
		 */
		close: (callback) => {
			if (!drawer._overlay) return;

			const overlay = drawer._overlay;
			drawer._overlay = null;

			// Release focus trap.
			if (drawer._focusTrapCleanup) {
				drawer._focusTrapCleanup();
				drawer._focusTrapCleanup = null;
			}

			overlay.classList.add('wpaic-drawer-overlay--exiting');
			onAnimEnd(
				overlay,
				() => {
					if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
					if (typeof callback === 'function') callback();
				},
				ANIM_EXIT_FALLBACK_MS,
			);

			// Restore focus to the element that opened the drawer.
			if (drawer._triggerEl && typeof drawer._triggerEl.focus === 'function') {
				drawer._triggerEl.focus();
			}
		},
	};

	// =========================================================================
	// Form validation module
	// Inline validation on blur; full validation on submit. Field-level errors.
	// =========================================================================

	const validate = {
		/**
		 * Attach validation to a form.
		 *
		 * @param {Element} formEl  The <form> element.
		 * @param {object}  rules   { fieldName: [validatorFn, ...] }
		 *                          validatorFn: (value) → null | 'error message'
		 * @param {object}  [opts]
		 *   @param {Function} opts.onSuccess  Called with (formEl) when submit passes.
		 */
		init: (formEl, rules, opts) => {
			opts = opts || {};

			// Blur: validate individual field.
			Object.keys(rules).forEach((name) => {
				const field = formEl.querySelector(`[name="${name}"]`);
				if (!field) return;

				field.addEventListener('blur', () => {
					validate._validateField(field, rules[name]);
				});

				// Re-validate on input once the error has been shown.
				field.addEventListener('input', () => {
					const wrapper = validate._getWrapper(field);
					if (wrapper?.classList.contains('wpaic-field--error')) {
						validate._validateField(field, rules[name]);
					}
				});
			});

			// Submit: validate all fields before allowing submission.
			formEl.addEventListener(
				'submit',
				(e) => {
					if (!validate._validateAll(formEl, rules)) {
						e.preventDefault();
						e.stopImmediatePropagation();
						return false;
					}
					if (typeof opts.onSuccess === 'function') {
						opts.onSuccess(formEl);
					}
				},
				true,
			); // Capture phase so we run before other submit listeners.
		},

		/**
		 * Validate a single field against its rule array.
		 * Returns true if valid, false otherwise.
		 *
		 * @param  {Element}   field
		 * @param  {Function[]} validators
		 * @return {boolean}
		 */
		_validateField: (field, validators) => {
			let error = null;
			for (let i = 0; i < validators.length; i++) {
				error = validators[i](field.value);
				if (error) break;
			}
			validate._setFieldState(validate._getWrapper(field), field, error);
			return !error;
		},

		/**
		 * Validate all fields; focus the first invalid one.
		 *
		 * @param  {Element} formEl
		 * @param  {object}  rules
		 * @return {boolean}
		 */
		_validateAll: (formEl, rules) => {
			let allValid = true;
			let firstInvalid = null;

			Object.keys(rules).forEach((name) => {
				const field = formEl.querySelector(`[name="${name}"]`);
				if (!field) return;
				if (!validate._validateField(field, rules[name])) {
					allValid = false;
					if (!firstInvalid) firstInvalid = field;
				}
			});

			if (firstInvalid) firstInvalid.focus();
			return allValid;
		},

		/**
		 * Walk up at most 3 levels to find a .wpaic-field wrapper element.
		 *
		 * @param  {Element}       field
		 * @return {Element|null}
		 */
		_getWrapper: (field) => {
			let node = field.parentNode;
			for (let i = 0; i < 3; i++) {
				if (!node) break;
				if (node.classList?.contains('wpaic-field')) return node;
				node = node.parentNode;
			}
			return null;
		},

		/**
		 * Apply or clear field error state.
		 *
		 * @param {Element|null} wrapper  .wpaic-field wrapper, or null.
		 * @param {Element}      field    The input/select/textarea.
		 * @param {string|null}  error    Error message, or null for valid.
		 */
		_setFieldState: (wrapper, field, error) => {
			// Remove any previous error message.
			if (wrapper) {
				wrapper.classList.remove('wpaic-field--error', 'wpaic-field--valid');
				const prev = wrapper.querySelector('.wpaic-field-error');
				if (prev) prev.parentNode.removeChild(prev);
			}

			if (error) {
				if (wrapper) wrapper.classList.add('wpaic-field--error');

				const errId = `wpaic-err-${field.name || field.id || Math.random().toString(36).slice(2)}`;
				field.setAttribute('aria-invalid', 'true');
				field.setAttribute('aria-describedby', errId);

				const errEl = ce('span', {
					className: 'wpaic-field-error',
					id: errId,
					role: 'alert',
					textContent: error,
				});

				const target = wrapper || field.parentNode;
				if (target) target.appendChild(errEl);
			} else {
				if (wrapper) wrapper.classList.add('wpaic-field--valid');
				field.removeAttribute('aria-invalid');
				field.removeAttribute('aria-describedby');
			}
		},
	};

	// =========================================================================
	// Validator factory functions (composable — pass to validate.init rules)
	// =========================================================================

	const validators = {
		/**
		 * Require a non-empty value.
		 * @param  {string}   [msg]
		 * @return {Function}
		 */
		required: (msg) => (val) =>
			val === null || val === undefined || String(val).trim() === '' ? msg || 'This field is required.' : null,

		/**
		 * Enforce a minimum character length.
		 * @param  {number}   n
		 * @param  {string}   [msg]
		 * @return {Function}
		 */
		minLength: (n, msg) => (val) => (val.length < n ? msg || `Minimum ${n} characters required.` : null),

		/**
		 * Enforce a maximum character length.
		 * @param  {number}   n
		 * @param  {string}   [msg]
		 * @return {Function}
		 */
		maxLength: (n, msg) => (val) => (val.length > n ? msg || `Maximum ${n} characters allowed.` : null),

		/**
		 * Validate a URL (requires protocol).
		 * @param  {string}   [msg]
		 * @return {Function}
		 */
		url: (msg) => (val) => {
			if (!val) return null;
			try {
				new URL(val);
				return null;
			} catch (_e) {
				return msg || 'Please enter a valid URL (including https://).';
			}
		},

		/**
		 * Validate an email address format.
		 * @param  {string}   [msg]
		 * @return {Function}
		 */
		email: (msg) => (val) => {
			if (!val) return null;
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) ? null : msg || 'Please enter a valid email address.';
		},

		/**
		 * Validate against a regular expression.
		 * @param  {RegExp}   pattern
		 * @param  {string}   msg
		 * @return {Function}
		 */
		pattern: (pattern, msg) => (val) => {
			if (!val) return null;
			return pattern.test(val) ? null : msg || 'Invalid format.';
		},
	};

	// =========================================================================
	// Autosave module
	// Debounced save (500ms) → inline spinner → 800ms green checkmark.
	// =========================================================================

	const autosave = {
		/**
		 * Attach autosave behaviour to a form or specific input element.
		 *
		 * @param {Element}  triggerEl  Form or single input to watch.
		 * @param {Function} saveFn     Called on debounced change. Must return a Promise.
		 * @param {Element}  [statusEl] Element to render the autosave indicator inside.
		 * @return {Element}  The autosave indicator element.
		 */
		init: (triggerEl, saveFn, statusEl) => {
			const indicator = autosave._createIndicator(statusEl);
			let debounceTimer = null;

			// Watch all inputs within a form, or the single element directly.
			const targets =
				triggerEl.tagName === 'FORM'
					? qsa(
							'input:not([type="submit"]):not([type="button"]):not([type="reset"]), select, textarea',
							triggerEl,
						)
					: [triggerEl];

			targets.forEach((input) => {
				['input', 'change'].forEach((eventName) => {
					input.addEventListener(eventName, () => {
						clearTimeout(debounceTimer);
						debounceTimer = setTimeout(() => {
							autosave._doSave(saveFn, indicator);
						}, AUTOSAVE_DEBOUNCE_MS);
					});
				});
			});

			return indicator;
		},

		/** @private Create the status indicator element. */
		_createIndicator: (container) => {
			const el = ce('span', {
				className: 'wpaic-autosave wpaic-autosave--hidden',
				'aria-live': 'polite',
				'aria-atomic': 'true',
			});
			if (container) container.appendChild(el);
			return el;
		},

		/** @private Execute the save and update the indicator. */
		_doSave: (saveFn, indicator) => {
			// Show saving state: spinner + label.
			indicator.className = 'wpaic-autosave';
			indicator.innerHTML = '';
			indicator.appendChild(ce('span', { className: 'wpaic-autosave__spinner', 'aria-hidden': 'true' }));
			indicator.appendChild(document.createTextNode('\u00a0Saving\u2026'));

			// Invoke the save function; normalise to Promise.
			let promise;
			try {
				promise = saveFn();
			} catch (e) {
				promise = Promise.reject(e);
			}
			if (!promise || typeof promise.then !== 'function') {
				promise = Promise.resolve();
			}

			promise
				.then(() => {
					// Show success state: checkmark + label.
					indicator.innerHTML = '';
					const checkEl = ce('span', { className: 'wpaic-autosave__check', 'aria-hidden': 'true' });
					const checkIcon = svgIcon('check');
					if (checkIcon) checkEl.appendChild(checkIcon);
					indicator.appendChild(checkEl);
					indicator.appendChild(document.createTextNode('\u00a0Saved'));
					indicator.className = 'wpaic-autosave';

					// Auto-hide after 800ms.
					setTimeout(() => {
						indicator.className = 'wpaic-autosave wpaic-autosave--hidden';
					}, AUTOSAVE_SUCCESS_MS);
				})
				.catch(() => {
					// Show error state.
					indicator.innerHTML = '';
					indicator.appendChild(document.createTextNode('Save failed'));
					indicator.className = 'wpaic-autosave';

					setTimeout(() => {
						indicator.className = 'wpaic-autosave wpaic-autosave--hidden';
					}, 4000);
				});
		},
	};

	// =========================================================================
	// Loading module
	// Skeleton placeholders, inline spinners, and delayed progress bars.
	// =========================================================================

	const loading = {
		/**
		 * Replace element content with skeleton placeholder lines.
		 *
		 * @param {Element} el    Target element.
		 * @param {object}  [opts]
		 *   @param {number}  opts.lines  Number of skeleton lines. Default: 3
		 *   @param {string}  opts.width  CSS width for full lines. Default: '100%'
		 * @return {Function}  restore() — removes skeleton and restores original content.
		 */
		skeleton: (el, opts) => {
			opts = opts || {};
			const lines = opts.lines || 3;
			const fullWidth = opts.width || '100%';
			const savedHtml = el.innerHTML;
			const savedClass = el.className;

			el.innerHTML = '';
			el.className = `${savedClass ? `${savedClass} ` : ''}wpaic-skeleton-wrapper`;

			for (let i = 0; i < lines; i++) {
				// Last line is shorter to suggest the end of a paragraph.
				const lineWidth = i === lines - 1 ? '60%' : fullWidth;
				const line = ce('div', { className: 'wpaic-skeleton' });
				line.style.height = '14px';
				line.style.width = lineWidth;
				line.style.marginBottom = i < lines - 1 ? '8px' : '0';
				el.appendChild(line);
			}

			return function restore() {
				el.innerHTML = savedHtml;
				el.className = savedClass;
			};
		},

		/**
		 * Put a button into a loading state (spinner + optional label swap).
		 * Disables the button and sets aria-busy="true".
		 *
		 * @param {Element} btn    The <button> element.
		 * @param {string}  [label]  Label to show during loading.
		 * @return {Function}  restore() — re-enables button and restores original label.
		 */
		spinner: (btn, label) => {
			const savedText = btn.textContent;
			const savedDisabled = btn.disabled;

			btn.disabled = true;
			btn.setAttribute('aria-busy', 'true');
			btn.innerHTML = '';

			const spinEl = ce('span', { className: 'wpaic-spinner wpaic-spinner--sm', 'aria-hidden': 'true' });
			btn.appendChild(spinEl);

			if (label) {
				btn.appendChild(document.createTextNode(`\u00a0${label}`));
			}

			return function restore() {
				btn.disabled = savedDisabled;
				btn.removeAttribute('aria-busy');
				btn.textContent = savedText;
			};
		},

		/**
		 * Show a progress bar in the given container after a 3-second delay.
		 * For long-running operations where completion time is unknown.
		 *
		 * @param {Element} container  Element to append the progress bar into.
		 * @return {Function}  finish() — clears the delay and removes the progress bar.
		 */
		progress: (container) => {
			let barEl = null;
			const delayTimer = setTimeout(() => {
				barEl = ce('div', { className: 'wpaic-progress' });
				const bar = ce('div', {
					className: 'wpaic-progress__bar',
					role: 'progressbar',
					'aria-label': 'Loading',
					'aria-valuemin': '0',
					'aria-valuemax': '100',
				});
				barEl.appendChild(bar);
				container.appendChild(barEl);
			}, PROGRESS_BAR_DELAY_MS);

			return function finish() {
				clearTimeout(delayTimer);
				if (barEl?.parentNode) {
					barEl.parentNode.removeChild(barEl);
				}
			};
		},

		/**
		 * Show a loading spinner on a toggle switch element.
		 * Adds .wpaic-toggle--loading; removes it via the returned restore function.
		 *
		 * @param {Element} toggleEl  The toggle switch element.
		 * @return {Function}  restore() — removes loading state.
		 */
		toggleSpinner: (toggleEl) => {
			toggleEl.classList.add('wpaic-toggle--loading');
			return function restore() {
				toggleEl.classList.remove('wpaic-toggle--loading');
			};
		},
	};

	// =========================================================================
	// Scroll reveal
	// Elements with class="wpaic-reveal" animate in when they enter viewport.
	// Respects max-3-concurrent-animations-per-quadrant constraint.
	// Minimum animation duration: 600ms (--wpaic-dur-reveal).
	// =========================================================================

	let _revealObserver = null;

	function initScrollReveal() {
		// Feature-detect IntersectionObserver.
		if (typeof global.IntersectionObserver !== 'function') return;

		_revealObserver = new global.IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (!entry.isIntersecting) return;

					const el = entry.target;

					// Respect concurrent animation cap.
					if (!canAnimate(el)) return;

					trackAnimation(el, SCROLL_REVEAL_DURATION);
					el.classList.add('wpaic-reveal--visible');
					_revealObserver.unobserve(el);
				});
			},
			{
				rootMargin: '0px 0px -40px 0px',
				threshold: 0.1,
			},
		);

		// Observe all .wpaic-reveal elements present at boot time.
		qsa('.wpaic-reveal').forEach((el) => {
			_revealObserver.observe(el);
		});
	}

	// =========================================================================
	// Skip link
	// Injects a "Skip to main content" link as the first body child on all
	// WPAIC admin pages (class .wpaic-admin-page). Targets #wpaic-main-content.
	// =========================================================================

	function initSkipLink() {
		// Only inject on WPAIC admin pages.
		if (!qs('.wpaic-admin-page')) return;

		// Don't duplicate if already present (e.g. SPAs with multiple boot cycles).
		if (qs('.wpaic-skip-link')) return;

		const link = ce('a', {
			className: 'wpaic-skip-link',
			href: '#wpaic-main-content',
			textContent: 'Skip to main content',
		});

		document.body.insertBefore(link, document.body.firstChild);
	}

	// =========================================================================
	// aria-pressed toggle helper
	// =========================================================================

	/**
	 * Set aria-pressed on a toggle button and optionally apply an active class.
	 *
	 * @param {Element} btn      The button element.
	 * @param {boolean} pressed  New pressed state.
	 * @param {string}  [cls]    Optional CSS class to add when pressed.
	 */
	function setAriaPressed(btn, pressed, cls) {
		btn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
		if (cls) {
			btn.classList.toggle(cls, pressed);
		}
	}

	// =========================================================================
	// Full focus trap helper for drawer
	// The existing drawer only sets initial focus + Escape; this upgrades it
	// to trap Tab/Shift+Tab within all focusable drawer elements.
	// =========================================================================

	const FOCUSABLE_SELECTOR =
		'a[href], button:not([disabled]):not([tabindex="-1"]), ' +
		'input:not([disabled]):not([tabindex="-1"]), ' +
		'select:not([disabled]):not([tabindex="-1"]), ' +
		'textarea:not([disabled]):not([tabindex="-1"]), ' +
		'[tabindex]:not([tabindex="-1"])';

	/**
	 * Install a Tab focus trap on container. Returns cleanup function.
	 *
	 * @param  {Element}  container
	 * @return {Function} cleanup
	 */
	function installFocusTrap(container) {
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
	// Public API — window.WPAICAdmin
	// =========================================================================

	const WPAICAdmin = {
		version: '1.0.0',
		toast: toast,
		banner: banner,
		modal: modal,
		drawer: drawer,
		validate: validate,
		validators: validators,
		autosave: autosave,
		loading: loading,

		/**
		 * Set aria-pressed on a toggle button.
		 *
		 * @param {Element} btn      Button element.
		 * @param {boolean} pressed  Pressed state.
		 * @param {string}  [cls]    Optional CSS class toggled with state.
		 */
		setAriaPressed: setAriaPressed,

		/**
		 * Install a Tab focus trap on a container. Returns cleanup function.
		 *
		 * @param  {Element}  container
		 * @return {Function} cleanup
		 */
		installFocusTrap: installFocusTrap,

		/**
		 * Manually observe new .wpaic-reveal elements added after page load.
		 *
		 * @param {Element} [root]  Scope element. Defaults to document.
		 */
		observeReveal: (root) => {
			if (!_revealObserver) return;
			qsa('.wpaic-reveal', root).forEach((el) => {
				_revealObserver.observe(el);
			});
		},
	};

	// =========================================================================
	// Boot on DOMContentLoaded
	// =========================================================================

	function boot() {
		initSkipLink();
		initScrollReveal();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	global.WPAICAdmin = WPAICAdmin;
})(window);
