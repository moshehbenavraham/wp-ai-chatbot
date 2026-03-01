/**
 * Leads Admin JavaScript
 *
 * Uses Bootstrap 5 for modals and UI components.
 *
 * @package AIChat
 * @subpackage Leads
 */

const { __, sprintf } = wp.i18n;

(($) => {
	const LeadsAdmin = {
		modal: null,

		init: function () {
			// Initialize Bootstrap modal
			const modalEl = document.getElementById('lead-detail-modal');
			if (modalEl && typeof bootstrap !== 'undefined') {
				this.modal = new bootstrap.Modal(modalEl);
			}

			this.bindEvents();
		},

		bindEvents: function () {
			// Settings form submit
			$('#aichat-leads-settings-form').on('submit', this.saveSettings.bind(this));

			// Destination radio toggle config sections
			$('input[name="destination"]').on('change', this.toggleDestinationConfig.bind(this));

			// Status change
			$(document).on('change', '.lead-status-select', this.updateStatus.bind(this));

			// View lead detail
			$(document).on('click', '.view-lead-btn', this.viewLead.bind(this));

			// Delete lead
			$(document).on('click', '.delete-lead-btn', this.deleteLead.bind(this));

			// Close modal via custom button
			$(document).on('click', '.close-modal', this.closeModal.bind(this));

			// Select all checkbox
			$('#cb-select-all').on('change', this.toggleSelectAll.bind(this));

			// Export button
			$('#export-leads-btn').on('click', this.exportLeads.bind(this));

			// Save email template button
			$('#save-lead-email-template').on('click', this.saveEmailTemplate.bind(this));

			// Google Sheets disconnect button
			$('#gsheets-disconnect').on('click', this.gsheetsDisconnect.bind(this));

			// Google Sheets test connection button
			$('#gsheets-test-connection').on('click', this.gsheetsTestConnection.bind(this));
		},

		gsheetsDisconnect: (e) => {
			e.preventDefault();

			if (!confirm(__('Are you sure you want to disconnect Google Sheets?', 'axiachat-ai'))) {
				return;
			}

			const $button = $(e.target).closest('button');
			const originalHtml = $button.html();

			$button.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i>');

			$.ajax({
				url: aichatLeadsAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'aichat_leads_gsheets_disconnect',
					nonce: aichatLeadsAdmin.nonce,
				},
				success: (response) => {
					if (response.success) {
						// Reload page to show updated connection state
						window.location.reload();
					} else {
						LeadsAdmin.showNotice('danger', response.data.message || __('Error', 'axiachat-ai'));
						$button.prop('disabled', false).html(originalHtml);
					}
				},
				error: () => {
					LeadsAdmin.showNotice('danger', __('Error', 'axiachat-ai'));
					$button.prop('disabled', false).html(originalHtml);
				},
			});
		},

		gsheetsTestConnection: (e) => {
			e.preventDefault();

			const $button = $(e.target).closest('button');
			const $result = $('#gsheets-test-result');
			const originalHtml = $button.html();
			const spreadsheetId = $('#gsheets_spreadsheet_id').val();
			const sheetName = $('#gsheets_sheet_name').val();

			if (!spreadsheetId) {
				$result.html(
					'<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>' +
						__('Please enter a Google Sheet URL', 'axiachat-ai') +
						'</span>',
				);
				return;
			}

			$button
				.prop('disabled', true)
				.html(`<i class="bi bi-hourglass-split me-1"></i>${__('Testing...', 'axiachat-ai')}`);
			$result.html('');

			$.ajax({
				url: aichatLeadsAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'aichat_leads_gsheets_test',
					nonce: aichatLeadsAdmin.nonce,
					spreadsheet_id: spreadsheetId,
					sheet_name: sheetName,
				},
				success: (response) => {
					if (response.success) {
						$result.html(
							'<span class="text-success"><i class="bi bi-check-circle me-1"></i>' +
								(response.data.message || __('Connection successful', 'axiachat-ai')) +
								'</span>',
						);
					} else {
						$result.html(
							'<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' +
								(response.data.message || __('Error', 'axiachat-ai')) +
								'</span>',
						);
					}
				},
				error: () => {
					$result.html(
						'<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' +
							__('Error', 'axiachat-ai') +
							'</span>',
					);
				},
				complete: () => {
					$button.prop('disabled', false).html(originalHtml);
				},
			});
		},

		saveEmailTemplate: (e) => {
			e.preventDefault();

			const $button = $(e.target);
			const originalHtml = $button.html();

			$button
				.prop('disabled', true)
				.html(`<i class="bi bi-hourglass-split me-1"></i>${__('Saving...', 'axiachat-ai')}`);

			$.ajax({
				url: aichatLeadsAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'aichat_leads_save_email_template',
					nonce: aichatLeadsAdmin.nonce,
					email_subject: $('#lead_email_subject').val(),
					email_body: $('#lead_email_body').val(),
				},
				success: (response) => {
					if (response.success) {
						LeadsAdmin.showNotice('success', response.data.message || __('Template saved', 'axiachat-ai'));
						// Close modal
						const modal = bootstrap.Modal.getInstance(document.getElementById('leadEmailTemplateModal'));
						if (modal) modal.hide();
					} else {
						LeadsAdmin.showNotice('danger', response.data.message || __('Error', 'axiachat-ai'));
					}
				},
				error: () => {
					LeadsAdmin.showNotice('danger', __('Error', 'axiachat-ai'));
				},
				complete: () => {
					$button.prop('disabled', false).html(originalHtml);
				},
			});
		},

		saveSettings: (e) => {
			e.preventDefault();

			const $form = $(e.target);
			const $button = $form.find('button[type="submit"]');
			const originalHtml = $button.html();

			$button
				.prop('disabled', true)
				.html(`<i class="bi bi-hourglass-split me-1"></i>${__('Saving...', 'axiachat-ai')}`);

			$.ajax({
				url: aichatLeadsAdmin.ajax_url,
				type: 'POST',
				data: `${$form.serialize()}&action=aichat_leads_save_settings&nonce=${aichatLeadsAdmin.nonce}`,
				success: (response) => {
					if (response.success) {
						LeadsAdmin.showNotice(
							'success',
							response.data.message || __('Settings saved successfully', 'axiachat-ai'),
						);
					} else {
						LeadsAdmin.showNotice('danger', response.data.message || __('Error', 'axiachat-ai'));
					}
				},
				error: () => {
					LeadsAdmin.showNotice('danger', __('Error', 'axiachat-ai'));
				},
				complete: () => {
					$button.prop('disabled', false).html(originalHtml);
				},
			});
		},

		toggleDestinationConfig: () => {
			const selected = $('input[name="destination"]:checked').val();

			// Update visual selection on cards
			$('.destination-card').removeClass('border-primary');
			$('input[name="destination"]:checked').closest('.destination-card').addClass('border-primary');

			// Toggle CF7 config section
			if (selected === 'cf7') {
				$('.cf7-settings').slideDown();
			} else {
				$('.cf7-settings').slideUp();
			}

			// Toggle WPForms config section
			if (selected === 'wpforms') {
				$('.wpforms-settings').slideDown();
			} else {
				$('.wpforms-settings').slideUp();
			}

			// Toggle Google Sheets config section
			if (selected === 'google_sheets') {
				$('#gsheets-settings-card').slideDown();
			} else {
				$('#gsheets-settings-card').slideUp();
			}
		},

		updateStatus: (e) => {
			const $select = $(e.target);
			const id = $select.data('id');
			const status = $select.val();

			$.ajax({
				url: aichatLeadsAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'aichat_leads_update_status',
					nonce: aichatLeadsAdmin.nonce,
					id: id,
					status: status,
				},
				success: (response) => {
					if (response.success) {
						$select.addClass('updated');
						setTimeout(() => {
							$select.removeClass('updated');
						}, 1500);
					}
				},
			});
		},

		viewLead: (e) => {
			const id = $(e.currentTarget).data('id');

			$.ajax({
				url: aichatLeadsAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'aichat_leads_get_lead',
					nonce: aichatLeadsAdmin.nonce,
					id: id,
				},
				success: (response) => {
					if (response.success) {
						LeadsAdmin.renderLeadDetail(response.data.lead);
						if (LeadsAdmin.modal) {
							LeadsAdmin.modal.show();
						} else {
							// Fallback for older Bootstrap or missing instance
							$('#lead-detail-modal').addClass('show').css('display', 'block');
							$('body').addClass('modal-open');
						}
					}
				},
			});
		},

		renderLeadDetail: (lead) => {
			let html = '<div class="lead-detail-grid">';

			const fields = [
				{ key: 'nombre', label: __('Name', 'axiachat-ai'), icon: 'bi-person' },
				{ key: 'email', label: __('Email', 'axiachat-ai'), icon: 'bi-envelope' },
				{ key: 'telefono', label: __('Phone', 'axiachat-ai'), icon: 'bi-telephone' },
				{ key: 'empresa', label: __('Company', 'axiachat-ai'), icon: 'bi-building' },
				{ key: 'interes', label: __('Interest', 'axiachat-ai'), icon: 'bi-star', fullWidth: true },
				{ key: 'notas', label: __('Notes', 'axiachat-ai'), icon: 'bi-journal-text', fullWidth: true },
				{ key: 'bot_slug', label: __('Bot', 'axiachat-ai'), icon: 'bi-robot' },
				{ key: 'estado', label: __('Status', 'axiachat-ai'), icon: 'bi-flag' },
				{ key: 'created_at', label: __('Date', 'axiachat-ai'), icon: 'bi-calendar' },
				{ key: 'session_id', label: __('Session ID', 'axiachat-ai'), icon: 'bi-hash' },
			];

			fields.forEach((field) => {
				const value = lead[field.key] || '—';
				const fullWidthClass = field.fullWidth ? ' full-width' : '';

				html += `<div class="detail-item${fullWidthClass}">`;
				html += `<div class="detail-label"><i class="bi ${field.icon} me-1"></i>${field.label}</div>`;
				html += `<div class="detail-value">${LeadsAdmin.escapeHtml(value)}</div>`;
				html += '</div>';
			});

			// Custom fields
			if (lead.campos_extra && Object.keys(lead.campos_extra).length > 0) {
				html += '<div class="detail-item full-width">';
				html +=
					'<div class="detail-label"><i class="bi bi-braces me-1"></i>' +
					__('Custom Fields', 'axiachat-ai') +
					'</div>';
				html += `<div class="detail-value"><pre>${JSON.stringify(lead.campos_extra, null, 2)}</pre></div>`;
				html += '</div>';
			}

			html += '</div>';

			$('#lead-detail-content').html(html);
		},

		deleteLead: (e) => {
			if (!confirm(__('Are you sure you want to delete this lead?', 'axiachat-ai'))) {
				return;
			}

			const $btn = $(e.currentTarget);
			const id = $btn.data('id');
			const $row = $btn.closest('tr');

			$btn.prop('disabled', true);

			$.ajax({
				url: aichatLeadsAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'aichat_leads_delete',
					nonce: aichatLeadsAdmin.nonce,
					id: id,
				},
				success: (response) => {
					if (response.success) {
						$row.fadeOut(function () {
							$(this).remove();
						});
					} else {
						LeadsAdmin.showNotice('danger', response.data.message);
						$btn.prop('disabled', false);
					}
				},
				error: () => {
					$btn.prop('disabled', false);
				},
			});
		},

		closeModal: function (_e) {
			if (this.modal) {
				this.modal.hide();
			} else {
				$('#lead-detail-modal').removeClass('show').css('display', 'none');
				$('body').removeClass('modal-open');
			}
		},

		toggleSelectAll: (e) => {
			const checked = $(e.target).is(':checked');
			$('input[name="leads[]"]').prop('checked', checked);
		},

		exportLeads: () => {
			const params = new URLSearchParams(window.location.search);
			let url = `${aichatLeadsAdmin.ajax_url}?action=aichat_leads_export&nonce=${aichatLeadsAdmin.nonce}`;

			if (params.get('estado')) {
				url += `&estado=${params.get('estado')}`;
			}
			if (params.get('s')) {
				url += `&s=${encodeURIComponent(params.get('s'))}`;
			}

			window.location.href = url;
		},

		showNotice: (type, message) => {
			// Remove existing notices
			$('.aichat-leads-wrap .alert.auto-dismiss').remove();

			const iconMap = {
				success: 'bi-check-circle-fill',
				danger: 'bi-exclamation-triangle-fill',
				warning: 'bi-exclamation-circle-fill',
				info: 'bi-info-circle-fill',
			};
			const icon = iconMap[type] || 'bi-info-circle-fill';

			const $notice = $(
				'<div class="alert alert-' +
					type +
					' alert-dismissible fade show auto-dismiss" role="alert">' +
					'<i class="bi ' +
					icon +
					' me-2"></i>' +
					message +
					'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
					'</div>',
			);

			$('.aichat-leads-wrap h1').after($notice);

			// Auto dismiss after 5s
			setTimeout(() => {
				$notice.fadeOut(function () {
					$(this).remove();
				});
			}, 5000);
		},

		escapeHtml: (text) => {
			if (!text) return '';
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},
	};

	$(document).ready(() => {
		LeadsAdmin.init();
	});
})(jQuery);
