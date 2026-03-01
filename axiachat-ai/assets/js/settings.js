(() => {
	// WordPress i18n functions
	var __ = wp.i18n.__;

	function forEachNode(list, callback) {
		if (!list || typeof callback !== 'function') {
			return;
		}
		Array.prototype.forEach.call(list, callback);
	}

	function initSecretToggles(root) {
		forEachNode(root.querySelectorAll('.aichat-toggle-secret'), (btn) => {
			btn.addEventListener('click', () => {
				var targetId = btn.getAttribute('data-target');
				var input = document.getElementById(targetId);
				if (!input) {
					return;
				}
				var icon = btn.querySelector('i');
				if (input.type === 'password') {
					input.type = 'text';
					if (icon) {
						icon.classList.remove('bi-eye');
						icon.classList.add('bi-eye-slash');
					}
				} else {
					input.type = 'password';
					if (icon) {
						icon.classList.remove('bi-eye-slash');
						icon.classList.add('bi-eye');
					}
				}
			});
		});
	}

	function initTabs(root) {
		var container = root.querySelector('.aichat-settings-tabs');
		if (!container) {
			return;
		}
		var tabLinks = container.querySelectorAll('.nav-link');
		var tabPanes = container.querySelectorAll('.tab-pane');
		if (!tabLinks.length || !tabPanes.length) {
			return;
		}

		function activateTab(targetId) {
			forEachNode(tabLinks, (link) => {
				if (link.getAttribute('data-tab-target') === targetId) {
					link.classList.add('active');
					link.setAttribute('aria-selected', 'true');
				} else {
					link.classList.remove('active');
					link.setAttribute('aria-selected', 'false');
				}
			});
			forEachNode(tabPanes, (pane) => {
				if (pane.id === targetId) {
					pane.classList.add('active');
					pane.setAttribute('aria-hidden', 'false');
				} else {
					pane.classList.remove('active');
					pane.setAttribute('aria-hidden', 'true');
				}
			});
		}

		forEachNode(tabLinks, (link) => {
			link.addEventListener('click', (event) => {
				event.preventDefault();
				var targetId = link.getAttribute('data-tab-target');
				if (targetId) {
					activateTab(targetId);
				}
			});
		});

		var initialLink = container.querySelector('.nav-link.active');
		if (initialLink) {
			activateTab(initialLink.getAttribute('data-tab-target'));
		} else if (tabPanes[0]) {
			activateTab(tabPanes[0].id);
		}
	}

	function initPolicyReset() {
		var resetBtn = document.getElementById('aichat-reset-security-policy');
		var textarea = document.getElementById('aichat_security_policy');
		if (!resetBtn || !textarea) {
			return;
		}
		var data = window.aichatSettingsData || {};
		var defaultPolicy = data.defaultPolicy || '';
		var confirmMessage = data.resetConfirm || '';
		resetBtn.addEventListener('click', () => {
			if (defaultPolicy === '') {
				return;
			}
			if (!confirmMessage || window.confirm(confirmMessage)) {
				textarea.value = defaultPolicy;
			}
		});
	}

	function initConnectToggle() {
		var connectToggle = document.getElementById('aichat_addon_connect_enabled');
		if (!connectToggle) {
			return;
		}
		connectToggle.addEventListener('change', () => {
			if (!connectToggle.checked) {
				return;
			}
			var shouldShowGuide = connectToggle.getAttribute('data-guide-required') === '1';
			if (!shouldShowGuide) {
				return;
			}
			var guideUrl = connectToggle.getAttribute('data-guide-url');
			if (!guideUrl) {
				connectToggle.checked = false;
				return;
			}
			var message = connectToggle.getAttribute('data-guide-message');
			if (window.confirm(message || __('Visit Andromeda Connect installation guide?', 'axiachat-ai'))) {
				connectToggle.checked = false;
				window.location.href = guideUrl;
			} else {
				connectToggle.checked = false;
			}
		});
	}

	function initLogViewers() {
		var phpBtn = document.getElementById('aichat-debug-log-refresh');
		var aiBtn = document.getElementById('aichat-debug-ai-log-refresh');

		function bind(button, type, textareaId) {
			if (!button) return;
			var textarea = document.getElementById(textareaId);
			if (!textarea) return;

			button.addEventListener('click', () => {
				if (!window.aichatSettingsData || !window.aichatSettingsData.ajaxUrl) {
					return;
				}
				button.disabled = true;
				button.classList.add('disabled');
				var originalHtml = button.innerHTML;
				button.innerHTML =
					'<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
					(button.getAttribute('data-loading-label') || originalHtml);

				var formData = new FormData();
				formData.append('action', 'aichat_get_log_tail');
				formData.append('nonce', window.aichatSettingsData?.nonce || '');
				formData.append('log_type', type);

				fetch(window.aichatSettingsData.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData,
				})
					.then((res) => res.json())
					.then((data) => {
						if (data?.success) {
							textarea.value = data.data || '';
							textarea.classList.remove('d-none');
						} else {
							var msg = data?.data ? data.data : __('Error loading log.', 'axiachat-ai');
							textarea.value = msg;
							textarea.classList.remove('d-none');
						}
					})
					.catch(() => {
						textarea.value = __('Error loading log.', 'axiachat-ai');
						textarea.classList.remove('d-none');
					})
					.finally(() => {
						button.disabled = false;
						button.classList.remove('disabled');
						button.innerHTML = originalHtml;
					});
			});
		}

		bind(phpBtn, 'general', 'aichat_debug_log_preview');
		bind(aiBtn, 'ai', 'aichat_debug_ai_log_preview');
	}

	function initClearLogButtons() {
		var buttons = document.querySelectorAll('.aichat-clear-log-btn');
		if (!buttons.length) return;

		buttons.forEach((btn) => {
			btn.addEventListener('click', () => {
				if (!window.aichatSettingsData || !window.aichatSettingsData.ajaxUrl) return;

				var type = btn.getAttribute('data-log-type');
				var confirmMsg = window.aichatSettingsData?.clearLogConfirm
					? window.aichatSettingsData.clearLogConfirm
					: __('Are you sure you want to clear this log file?', 'axiachat-ai');

				if (!window.confirm(confirmMsg)) return;

				btn.disabled = true;
				var originalHtml = btn.innerHTML;
				btn.innerHTML =
					'<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
					__('Clearing…', 'axiachat-ai');

				var formData = new FormData();
				formData.append('action', 'aichat_clear_log');
				formData.append('nonce', window.aichatSettingsData?.nonce || '');
				formData.append('log_type', type);

				fetch(window.aichatSettingsData.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData,
				})
					.then((res) => res.json())
					.then((_data) => {
						// Also clear the corresponding textarea if visible.
						var textareaId = type === 'ai' ? 'aichat_debug_ai_log_preview' : 'aichat_debug_log_preview';
						var textarea = document.getElementById(textareaId);
						if (textarea) {
							textarea.value = '';
						}
					})
					.catch(() => {
						// Silent fail — nothing to show.
					})
					.finally(() => {
						btn.disabled = false;
						btn.innerHTML = originalHtml;
					});
			});
		});
	}

	document.addEventListener('DOMContentLoaded', () => {
		var settingsWrap = document.querySelector('.aichat-settings-wrap');
		if (!settingsWrap) {
			return;
		}
		initSecretToggles(settingsWrap);
		initTabs(settingsWrap);
		initPolicyReset();
		initConnectToggle();
		initLogViewers();
		initClearLogButtons();
	});
})();
