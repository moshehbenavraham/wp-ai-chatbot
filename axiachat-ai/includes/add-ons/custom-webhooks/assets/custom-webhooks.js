/**
 * Custom Webhooks Admin JS
 *
 * Handles CRUD operations for custom webhook actions.
 */
(($) => {
	const nonce = typeof aichatToolsConfig !== 'undefined' ? aichatToolsConfig.nonce : '';
	let webhooksData = [];
	let modal = null;

	// Initialize when tab is shown
	function init() {
		if (!$('#aichat-custom-webhooks-container').length) return;

		// Get Bootstrap modal instance
		const modalEl = document.getElementById('aichat-webhook-modal');
		if (modalEl) {
			modal = new bootstrap.Modal(modalEl);
		}

		// Load webhooks
		loadWebhooks();

		// Event bindings
		$('#aichat-webhook-add-btn, #aichat-webhook-add-first').on('click', openNewModal);
		$('#aichat-webhook-save').on('click', saveWebhook);
		$('#wh-add-param').on('click', addParameter);
		$('#wh-include-history').on('change', toggleHistoryOptions);

		// Delegate events for dynamic elements
		$(document).on('click', '.wh-edit-btn', function () {
			const slug = $(this).data('slug');
			openEditModal(slug);
		});

		$(document).on('click', '.wh-delete-btn', function () {
			const slug = $(this).data('slug');
			deleteWebhook(slug);
		});

		$(document).on('click', '.wh-test-btn', function () {
			const slug = $(this).data('slug');
			testWebhook(slug, $(this));
		});

		$(document).on('click', '.wh-toggle-btn', function () {
			const slug = $(this).data('slug');
			const active = $(this).data('active') ? 0 : 1;
			toggleWebhook(slug, active, $(this));
		});

		$(document).on('change', '.wh-param-type', function () {
			const row = $(this).closest('.wh-param-row');
			row.find('.wh-param-enum-col').toggle($(this).val() === 'enum');
		});

		$(document).on('click', '.wh-param-remove', function () {
			$(this).closest('.wh-param-row').remove();
			updateParamsEmpty();
		});
	}

	// Load webhooks list
	function loadWebhooks() {
		$.post(
			ajaxurl,
			{
				action: 'aichat_custom_webhooks_list',
				nonce: nonce,
			},
			(res) => {
				if (res.success) {
					webhooksData = res.data.webhooks || [];
					renderWebhooks(res.data);
				} else {
					console.error('Failed to load webhooks:', res);
				}
			},
		);
	}

	// Render webhooks list
	function renderWebhooks(data) {
		const $list = $('#aichat-webhooks-list');
		const $empty = $('#aichat-webhooks-empty');
		const $count = $('#aichat-webhooks-count');
		const $addBtn = $('#aichat-webhook-add-btn');

		$count.text(data.count || 0);

		if (!data.webhooks || data.webhooks.length === 0) {
			$list.hide();
			$empty.show();
			return;
		}

		$empty.hide();
		$list.show().empty();

		// Disable add button if limit reached
		if (!data.can_create) {
			$addBtn.prop('disabled', true).attr('title', 'Upgrade to PRO for more webhooks');
		} else {
			$addBtn.prop('disabled', false).removeAttr('title');
		}

		data.webhooks.forEach((wh) => {
			const statusClass = wh.is_active === 1 ? 'success' : 'secondary';
			const statusText = wh.is_active === 1 ? 'Active' : 'Paused';
			const statusIcon = wh.is_active === 1 ? 'bi-check-circle-fill' : 'bi-pause-circle';

			const paramsCount = wh.params_schema?.length || 0;
			const historyBadge =
				wh.include_history === 1
					? '<span class="badge bg-info ms-1" title="Includes conversation history"><i class="bi bi-chat-left-text"></i></span>'
					: '';

			const card = `
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 ${wh.is_active !== 1 ? 'opacity-75' : ''}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-truncate" title="${escapeHtml(wh.label)}">
                                <i class="bi bi-broadcast me-1"></i>${escapeHtml(wh.label)}
                            </span>
                            <span class="badge bg-${statusClass}">
                                <i class="bi ${statusIcon} me-1"></i>${statusText}
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-2">
                                <code>webhook_${escapeHtml(wh.slug)}</code>
                            </p>
                            <p class="small mb-2">${escapeHtml(wh.description_ai.substring(0, 100))}${wh.description_ai.length > 100 ? '...' : ''}</p>
                            <p class="small mb-0">
                                <span class="badge bg-light text-dark">${wh.http_method}</span>
                                <span class="badge bg-light text-dark" title="${escapeHtml(wh.webhook_url)}">
                                    <i class="bi bi-link-45deg"></i> ${truncateUrl(wh.webhook_url)}
                                </span>
                                ${paramsCount > 0 ? `<span class="badge bg-light text-dark">${paramsCount} params</span>` : ''}
                                ${historyBadge}
                            </p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="btn-group btn-group-sm w-100">
                                <button type="button" class="btn btn-outline-primary wh-edit-btn" data-slug="${escapeHtml(wh.slug)}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-success wh-test-btn" data-slug="${escapeHtml(wh.slug)}" title="Test">
                                    <i class="bi bi-play"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary wh-toggle-btn" data-slug="${escapeHtml(wh.slug)}" data-active="${wh.is_active}" title="${wh.is_active === 1 ? 'Pause' : 'Activate'}">
                                    <i class="bi ${wh.is_active === 1 ? 'bi-pause' : 'bi-play-fill'}"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger wh-delete-btn" data-slug="${escapeHtml(wh.slug)}" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

			$list.append(card);
		});
	}

	// Open modal for new webhook
	function openNewModal() {
		resetForm();
		$('#aichat-webhook-modal-title').html('<i class="bi bi-broadcast me-2"></i>New Webhook Action');
		$('#wh-slug').prop('readonly', false);
		modal.show();
	}

	// Open modal for editing
	function openEditModal(slug) {
		resetForm();

		const wh = webhooksData.find((w) => w.slug === slug);
		if (!wh) return;

		$('#aichat-webhook-modal-title').html(`<i class="bi bi-pencil me-2"></i>Edit Webhook: ${escapeHtml(wh.label)}`);

		// Populate form
		$('#wh-id').val(wh.id);
		$('#wh-slug').val(wh.slug).prop('readonly', true);
		$('#wh-label').val(wh.label);
		$('#wh-description-ai').val(wh.description_ai);
		$('#wh-activity-label').val(wh.activity_label);
		$('#wh-url').val(wh.webhook_url);
		$('#wh-method').val(wh.http_method);
		$('#wh-headers').val(wh.headers_json && wh.headers_json !== '{}' ? wh.headers_json : '');
		$('#wh-static-params').val(
			wh.static_params && Object.keys(wh.static_params).length ? JSON.stringify(wh.static_params, null, 2) : '',
		);
		$('#wh-include-history')
			.prop('checked', wh.include_history === 1)
			.trigger('change');
		$('#wh-history-limit').val(wh.history_limit || 10);
		$('#wh-success-msg').val(wh.success_message);
		$('#wh-error-msg').val(wh.error_message);
		$('#wh-active').prop('checked', wh.is_active === 1);

		// Populate parameters
		if (wh.params_schema?.length) {
			wh.params_schema.forEach((param) => {
				addParameter(null, param);
			});
		}

		updateParamsEmpty();
		modal.show();
	}

	// Reset form
	function resetForm() {
		$('#aichat-webhook-form')[0].reset();
		$('#wh-id').val(0);
		$('#wh-slug').prop('readonly', false);
		$('#wh-params-list').empty();
		$('#wh-history-options').hide();
		updateParamsEmpty();
	}

	// Add parameter row
	function addParameter(_e, data) {
		const template = document.getElementById('wh-param-template');
		const clone = template.content.cloneNode(true);

		if (data) {
			$(clone)
				.find('.wh-param-name')
				.val(data.name || '');
			$(clone)
				.find('.wh-param-type')
				.val(data.type || 'string');
			$(clone)
				.find('.wh-param-desc')
				.val(data.description || '');
			$(clone)
				.find('.wh-param-required')
				.prop('checked', data.required !== false);
			if (data.type === 'enum') {
				$(clone).find('.wh-param-enum-col').show();
				$(clone)
					.find('.wh-param-values')
					.val(data.values || '');
			}
		}

		$('#wh-params-list').append(clone);
		updateParamsEmpty();
	}

	// Update params empty message
	function updateParamsEmpty() {
		const hasParams = $('#wh-params-list .wh-param-row').length > 0;
		$('#wh-params-empty').toggle(!hasParams);
	}

	// Toggle history options
	function toggleHistoryOptions() {
		$('#wh-history-options').toggle($(this).is(':checked'));
	}

	// Collect parameters from form
	function collectParams() {
		const params = [];
		$('#wh-params-list .wh-param-row').each(function () {
			const name = $(this).find('.wh-param-name').val().trim();
			if (!name) return;

			const param = {
				name: name,
				type: $(this).find('.wh-param-type').val(),
				description: $(this).find('.wh-param-desc').val(),
				required: $(this).find('.wh-param-required').is(':checked'),
			};

			if (param.type === 'enum') {
				param.values = $(this).find('.wh-param-values').val();
			}

			params.push(param);
		});
		return params;
	}

	// Save webhook
	function saveWebhook() {
		const $btn = $('#aichat-webhook-save');
		const $form = $('#aichat-webhook-form');

		// Basic validation
		if (!$form[0].checkValidity()) {
			$form[0].reportValidity();
			return;
		}

		$btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

		const data = {
			action: 'aichat_custom_webhooks_save',
			nonce: nonce,
			id: $('#wh-id').val(),
			slug: $('#wh-slug').val(),
			label: $('#wh-label').val(),
			description_ai: $('#wh-description-ai').val(),
			activity_label: $('#wh-activity-label').val(),
			webhook_url: $('#wh-url').val(),
			http_method: $('#wh-method').val(),
			headers_json: $('#wh-headers').val() || '{}',
			params_schema: JSON.stringify(collectParams()),
			static_params: $('#wh-static-params').val() || '{}',
			include_history: $('#wh-include-history').is(':checked') ? 1 : 0,
			history_limit: $('#wh-history-limit').val(),
			success_message: $('#wh-success-msg').val(),
			error_message: $('#wh-error-msg').val(),
			is_active: $('#wh-active').is(':checked') ? 1 : 0,
		};

		$.post(ajaxurl, data, (res) => {
			$btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Save Webhook');

			if (res.success) {
				modal.hide();
				loadWebhooks();
				showToast('success', res.data.message || 'Webhook saved!');
			} else {
				showToast('error', res.data.message || 'Error saving webhook');
			}
		}).fail(() => {
			$btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Save Webhook');
			showToast('error', 'Network error');
		});
	}

	// Delete webhook
	function deleteWebhook(slug) {
		if (!confirm('Are you sure you want to delete this webhook? This cannot be undone.')) {
			return;
		}

		$.post(
			ajaxurl,
			{
				action: 'aichat_custom_webhooks_delete',
				nonce: nonce,
				slug: slug,
			},
			(res) => {
				if (res.success) {
					loadWebhooks();
					showToast('success', res.data.message || 'Webhook deleted');
				} else {
					showToast('error', res.data.message || 'Error deleting webhook');
				}
			},
		);
	}

	// Test webhook
	function testWebhook(slug, $btn) {
		const originalHtml = $btn.html();
		$btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

		$.post(
			ajaxurl,
			{
				action: 'aichat_custom_webhooks_test',
				nonce: nonce,
				slug: slug,
			},
			(res) => {
				$btn.prop('disabled', false).html(originalHtml);

				if (res.success) {
					showToast('success', res.data.message || 'Test successful!');
					console.log('Webhook test result:', res.data.result);
				} else {
					showToast('error', res.data.message || 'Test failed');
					console.error('Webhook test error:', res.data.result);
				}
			},
		).fail(() => {
			$btn.prop('disabled', false).html(originalHtml);
			showToast('error', 'Network error');
		});
	}

	// Toggle webhook active status
	function toggleWebhook(slug, active, _$btn) {
		$.post(
			ajaxurl,
			{
				action: 'aichat_custom_webhooks_toggle',
				nonce: nonce,
				slug: slug,
				is_active: active,
			},
			(res) => {
				if (res.success) {
					loadWebhooks();
					showToast('success', res.data.message);
				} else {
					showToast('error', res.data.message || 'Error toggling webhook');
				}
			},
		);
	}

	// Helper: escape HTML
	function escapeHtml(str) {
		if (!str) return '';
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	// Helper: truncate URL for display
	function truncateUrl(url) {
		if (!url) return '';
		try {
			const parsed = new URL(url);
			let display = parsed.hostname;
			if (parsed.pathname && parsed.pathname !== '/') {
				display += parsed.pathname.substring(0, 15) + (parsed.pathname.length > 15 ? '...' : '');
			}
			return display;
		} catch (_e) {
			return `${url.substring(0, 25)}...`;
		}
	}

	// Helper: show toast notification
	function showToast(type, message) {
		// Use existing toast system if available, otherwise console
		if (typeof aichatShowToast === 'function') {
			aichatShowToast(type, message);
		} else {
			console.log(`[${type}] ${message}`);
			// Fallback: simple alert for errors
			if (type === 'error') {
				alert(message);
			}
		}
	}

	// Initialize when DOM ready
	$(document).ready(() => {
		// Initialize immediately if tab is visible
		if ($('#pane-webhooks').hasClass('active')) {
			init();
		}

		// Also initialize when tab is shown (Bootstrap tab event)
		$('button[data-bs-target="#pane-webhooks"]').on('shown.bs.tab', () => {
			if (!webhooksData.length) {
				init();
			}
		});

		// Fallback: initialize on any click of the tab button
		$(document).on('click', '#tab-webhooks', () => {
			setTimeout(init, 100);
		});
	});
})(jQuery);
