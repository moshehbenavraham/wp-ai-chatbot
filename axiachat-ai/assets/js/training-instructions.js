/**
 * AxiaChat AI – Training Instructions JS
 *
 * Handles manual mode (templates + textarea) and guided mode (type/tone/length wizard).
 *
 * @since 3.0.1
 */
(($) => {
	const __ = wp?.i18n?.__ ? wp.i18n.__ : (t) => t;

	function escapeHtml(s) {
		if (!s) return '';
		const d = document.createElement('div');
		d.appendChild(document.createTextNode(s));
		return d.innerHTML;
	}

	// ---- Data ----
	const $data = $('#aichat-instr-data');
	if (!$data.length) return;

	const botId = $data.data('bot-id');
	const _botSlug = $data.data('bot-slug');
	const nonce = $data.data('nonce');
	const chatbotTypes = JSON.parse($data.attr('data-chatbot-types') || '{}');
	const voiceTones = JSON.parse($data.attr('data-voice-tones') || '{}');
	const responseLens = JSON.parse($data.attr('data-response-lengths') || '{}');
	const templates = JSON.parse($data.attr('data-templates') || '{}');
	const ajaxUrl = window.aichat_training_ajax?.ajax_url || ajaxurl;

	// ---- Guided state ----
	const guided = {
		type: '',
		tone: '',
		length: 3,
		guidelines: [],
	};

	// ---- Mode toggle ----
	$('.aichat-instr-mode-btn').on('click', function () {
		const mode = $(this).data('mode');
		$('.aichat-instr-mode-btn').removeClass('active');
		$(this).addClass('active');
		if (mode === 'manual') {
			$('#aichat-instr-manual').show();
			$('#aichat-instr-guided').hide();
		} else {
			$('#aichat-instr-manual').hide();
			$('#aichat-instr-guided').show();
		}
	});

	// ---- Bot selector navigation ----
	$('#aichat-instr-bot-select').on('change', function () {
		const slug = $(this).val();
		window.location.href =
			window.aichat_training_ajax.admin_url +
			'?page=aichat-training-instructions&bot=' +
			encodeURIComponent(slug);
	});

	// ============================
	// MANUAL MODE
	// ============================

	// Template picker
	let selectedTplId = null;

	$(document).on('click', '#aichat-instr-tpl-list .aichat-tpl-item', function () {
		$('#aichat-instr-tpl-list .aichat-tpl-item').removeClass('selected');
		$(this).addClass('selected');
		selectedTplId = $(this).data('id');

		// Show description
		const tpl = templates[selectedTplId];
		if (tpl) {
			$('#aichat-instr-tpl-desc').text(tpl.description || '');
			$('#aichat-instr-tpl-load').prop('disabled', false);
		}
	});

	// Scroll arrows
	$(document).on('click', '#aichat-instr-tpl-panel .aichat-tpl-arrow.up', () => {
		const $list = $('#aichat-instr-tpl-list');
		$list.scrollTop($list.scrollTop() - 60);
	});
	$(document).on('click', '#aichat-instr-tpl-panel .aichat-tpl-arrow.down', () => {
		const $list = $('#aichat-instr-tpl-list');
		$list.scrollTop($list.scrollTop() + 60);
	});

	// Load template
	$('#aichat-instr-tpl-load').on('click', () => {
		if (!selectedTplId) return;
		const tpl = templates[selectedTplId];
		if (tpl?.template) {
			$('#aichat-instr-textarea').val(tpl.template);
		}
	});

	// Save manual
	$('#aichat-instr-save-manual').on('click', function () {
		const $btn = $(this);
		const instructions = $('#aichat-instr-textarea').val();

		$btn.prop('disabled', true).text(__('Saving...', 'axiachat-ai'));

		$.post(
			ajaxUrl,
			{
				action: 'aichat_training_save_instructions',
				nonce: nonce,
				bot_id: botId,
				instructions: instructions,
			},
			(res) => {
				$btn.prop('disabled', false).html(
					`<i class="bi bi-check-lg me-1"></i>${__('Save Instructions', 'axiachat-ai')}`,
				);
				if (res.success) {
					$('#aichat-instr-manual-saved').fadeIn().delay(2000).fadeOut();
				} else {
					alert(res.data?.message ? res.data.message : __('Error saving.', 'axiachat-ai'));
				}
			},
		).fail(() => {
			$btn.prop('disabled', false).html(
				`<i class="bi bi-check-lg me-1"></i>${__('Save Instructions', 'axiachat-ai')}`,
			);
			alert(__('Network error.', 'axiachat-ai'));
		});
	});

	// ============================
	// GUIDED MODE
	// ============================

	function renderGuidedTypeGrid() {
		let html = '';
		for (const [key, type] of Object.entries(chatbotTypes)) {
			html += `<div class="aichat-ec-type-card ${guided.type === key ? 'selected' : ''}" data-type="${key}">
        <div class="aichat-ec-type-icon">${type.icon || '🤖'}</div>
        <div class="aichat-ec-type-name">${escapeHtml(type.name)}</div>
        <div class="aichat-ec-type-desc">${escapeHtml(type.description)}</div>
      </div>`;
		}
		$('#aichat-guided-type-grid').html(html);
	}

	function renderGuidedToneGrid() {
		let html = '';
		for (const [key, tone] of Object.entries(voiceTones)) {
			html += `<div class="aichat-instr-tone-pill ${guided.tone === key ? 'selected' : ''}" data-tone="${key}">
        <span>${tone.icon || ''}</span> ${escapeHtml(tone.name)}
      </div>`;
		}
		$('#aichat-guided-tone-grid').html(html);
	}

	function buildGuidedPrompt() {
		const lines = [];

		// Type
		if (guided.type && chatbotTypes[guided.type]) {
			const t = chatbotTypes[guided.type];
			lines.push(`${__('Act as a', 'axiachat-ai')} ${t.name}:`);
			// Use editable guidelines instead of static ones
			if (guided.guidelines.length) {
				guided.guidelines.forEach((g) => {
					if (g.trim()) lines.push(`- ${g.trim()}`);
				});
			}
		}

		// Tone
		if (guided.tone && voiceTones[guided.tone]) {
			lines.push('');
			lines.push(voiceTones[guided.tone].instruction);
		}

		// Length
		const lenKeys = Object.keys(responseLens);
		const lenIdx = Math.max(0, Math.min(guided.length - 1, lenKeys.length - 1));
		const lenKey = lenKeys[lenIdx];
		if (lenKey && responseLens[lenKey]) {
			lines.push('');
			lines.push(responseLens[lenKey].instruction);
		}

		return lines.join('\n');
	}

	function updateGuidedPreview() {
		const prompt = buildGuidedPrompt();
		$('#aichat-guided-preview').text(
			prompt || __('Select a chatbot type above to see the preview.', 'axiachat-ai'),
		);
	}

	// ---- Guidelines rendering ----
	function renderGuidelines() {
		let html = '';
		guided.guidelines.forEach((g, idx) => {
			html +=
				'<div class="aichat-guideline-item">' +
				'<span class="aichat-guideline-bullet">•</span>' +
				'<input type="text" class="aichat-guideline-input" data-idx="' +
				idx +
				'" value="' +
				escapeHtml(g) +
				'">' +
				'<button type="button" class="aichat-guideline-delete" data-idx="' +
				idx +
				'"><i class="bi bi-x-lg"></i></button>' +
				'</div>';
		});
		$('#aichat-guided-guidelines-list').html(html);
	}

	// Guideline inline edit
	$(document).on('input', '.aichat-guideline-input', function () {
		const idx = parseInt($(this).data('idx'), 10);
		guided.guidelines[idx] = $(this).val();
		updateGuidedPreview();
	});

	// Guideline delete
	$(document).on('click', '.aichat-guideline-delete', function () {
		const idx = parseInt($(this).data('idx'), 10);
		guided.guidelines.splice(idx, 1);
		renderGuidelines();
		updateGuidedPreview();
	});

	// Add guideline — open modal with predefined options
	$('#aichat-guided-add-guideline').on('click', () => {
		// ---- Universal guidelines (always shown) ----
		const universalGuidelines = [
			{
				icon: '📖',
				text: __(
					"Use the provided CONTEXT to answer accurately. If the information is not available in the context, say so clearly and ask for the user's email so human support can follow up. Save the email using the save_lead tool.",
					'axiachat-ai',
				),
			},
			{
				icon: '🛡️',
				text: __(
					'If you are not sure about the answer, do not make it up. State that you cannot confirm it and suggest escalating to human support.',
					'axiachat-ai',
				),
			},
			{
				icon: '📋',
				text: __(
					'Provide a clear step-by-step solution based strictly on the available CONTEXT or conversation.',
					'axiachat-ai',
				),
			},
			{
				icon: '📧',
				text: __(
					'If the user wants a quote, ask for their email and let them know they will be contacted as soon as possible. Save the email using the save_lead tool.',
					'axiachat-ai',
				),
			},
			{
				icon: '🖼️',
				text: __(
					'If the user is interested in a product and you have the image URL, display the product image using markdown.',
					'axiachat-ai',
				),
			},
			{
				icon: '🔗',
				text: __(
					'When referencing a product or article, always include the direct link if available in the CONTEXT.',
					'axiachat-ai',
				),
			},
			{
				icon: '🚫',
				text: __(
					'Never invent information, prices, or availability. Only use what is confirmed in the CONTEXT.',
					'axiachat-ai',
				),
			},
			{
				icon: '🌐',
				text: __(
					'Always reply in the same language the user writes in, regardless of the CONTEXT language.',
					'axiachat-ai',
				),
			},
			{
				icon: '💬',
				text: __(
					'Keep responses concise and focused. Do not repeat information the user already provided.',
					'axiachat-ai',
				),
			},
			{
				icon: '📞',
				text: __(
					'If the user needs urgent help or the question is outside your scope, provide the company contact details.',
					'axiachat-ai',
				),
			},
			{
				icon: '🔄',
				text: __(
					'If the user seems confused, ask a clarifying question before giving a long answer.',
					'axiachat-ai',
				),
			},
			{
				icon: '😊',
				text: __(
					"Always be polite, empathetic, and professional. Mirror the user's emotional tone.",
					'axiachat-ai',
				),
			},
			{
				icon: '📦',
				text: __(
					'When listing multiple products or options, format them clearly using bullet points or numbered lists.',
					'axiachat-ai',
				),
			},
			{
				icon: '⏰',
				text: __(
					'If a question relates to schedules, hours, or deadlines, remind the user to verify with official channels.',
					'axiachat-ai',
				),
			},
			{
				icon: '🔒',
				text: __(
					'Never ask for or store sensitive data like passwords, credit card numbers, or personal IDs.',
					'axiachat-ai',
				),
			},
			{
				icon: '🎯',
				text: __(
					'At the end of your answer, suggest a related question or next step the user might find useful.',
					'axiachat-ai',
				),
			},
			{
				icon: '📩',
				text: __(
					'If the user needs personalized support, ask for the subject, send an email to the admin using the aichat_send_email_admin tool, and let the user know they will be contacted soon.',
					'axiachat-ai',
				),
			},
			{
				icon: '🛒',
				text: __(
					'If the user reports a problem with an order or purchase, gather the details and notify the admin via the aichat_send_email_admin tool so the team can follow up.',
					'axiachat-ai',
				),
			},
			{
				icon: '💡',
				text: __(
					'If the user submits a suggestion or feature request, summarize it and send it to the admin using the aichat_send_email_admin tool, then thank the user for their feedback.',
					'axiachat-ai',
				),
			},
		];

		// ---- Type-specific guidelines (only unused ones) ----
		let typeSpecific = [];
		if (guided.type && chatbotTypes[guided.type] && chatbotTypes[guided.type].guidelines) {
			typeSpecific = chatbotTypes[guided.type].guidelines.filter((g) => guided.guidelines.indexOf(g) === -1);
		}

		// Filter out already-added universals
		const available = universalGuidelines.filter((u) => guided.guidelines.indexOf(u.text) === -1);

		// Build modal HTML
		let itemsHtml =
			'<div class="aichat-gl-modal-item blank" data-value="">' +
			'<span class="aichat-gl-icon">✏️</span>' +
			'<span>' +
			__('Write your own custom guideline...', 'axiachat-ai') +
			'</span>' +
			'</div>';

		// Type-specific section
		if (typeSpecific.length > 0) {
			itemsHtml +=
				'<div class="aichat-gl-section-label"><i class="bi bi-star me-1"></i>' +
				__('Suggested for this bot type', 'axiachat-ai') +
				'</div>';
			typeSpecific.forEach((g) => {
				itemsHtml +=
					'<div class="aichat-gl-modal-item" data-value="' +
					escapeHtml(g) +
					'">' +
					'<span class="aichat-gl-icon">⭐</span>' +
					'<span>' +
					escapeHtml(g) +
					'</span>' +
					'</div>';
			});
		}

		// Universal section
		itemsHtml +=
			'<div class="aichat-gl-section-label"><i class="bi bi-lightbulb me-1"></i>' +
			__('Universal guidelines', 'axiachat-ai') +
			'</div>';
		available.forEach((u) => {
			itemsHtml +=
				'<div class="aichat-gl-modal-item" data-value="' +
				escapeHtml(u.text) +
				'">' +
				'<span class="aichat-gl-icon">' +
				u.icon +
				'</span>' +
				'<span>' +
				escapeHtml(u.text) +
				'</span>' +
				'</div>';
		});

		const modalHtml =
			'<div class="aichat-gl-modal-overlay" id="aichat-gl-modal">' +
			'<div class="aichat-gl-modal">' +
			'<div class="aichat-gl-modal-header">' +
			'<h3><i class="bi bi-plus-circle me-2"></i>' +
			__('Add Guideline', 'axiachat-ai') +
			'</h3>' +
			'<p class="text-muted small mb-0">' +
			__('Click any guideline below to add it. You can edit it afterwards.', 'axiachat-ai') +
			'</p>' +
			'</div>' +
			'<div class="aichat-gl-modal-body">' +
			itemsHtml +
			'</div>' +
			'<div class="aichat-gl-modal-footer"><button type="button" class="btn-cancel" id="aichat-gl-modal-close">' +
			__('Cancel', 'axiachat-ai') +
			'</button></div>' +
			'</div>' +
			'</div>';

		$('body').append(modalHtml);
	});

	// Modal: pick predefined guideline
	$(document).on('click', '.aichat-gl-modal-item', function () {
		const val = $(this).data('value');
		if (val === '') {
			// Blank — add empty guideline for user to type
			guided.guidelines.push('');
		} else {
			guided.guidelines.push(val);
		}
		renderGuidelines();
		updateGuidedPreview();
		$('#aichat-gl-modal').remove();

		// Focus the new input if blank
		if (val === '') {
			const $inputs = $('.aichat-guideline-input');
			$inputs.last().focus();
		}
	});

	// Modal: cancel
	$(document).on('click', '#aichat-gl-modal-close, .aichat-gl-modal-overlay', function (e) {
		if (e.target === this) {
			$('#aichat-gl-modal').remove();
		}
	});

	// Type selection
	$(document).on('click', '#aichat-guided-type-grid .aichat-ec-type-card', function () {
		guided.type = $(this).data('type');
		// Populate guidelines from type defaults
		if (chatbotTypes[guided.type] && chatbotTypes[guided.type].guidelines) {
			guided.guidelines = chatbotTypes[guided.type].guidelines.slice(); // clone
		} else {
			guided.guidelines = [];
		}
		renderGuidedTypeGrid();
		renderGuidelines();
		updateGuidedPreview();
	});

	// Tone selection
	$(document).on('click', '#aichat-guided-tone-grid .aichat-instr-tone-pill', function () {
		guided.tone = $(this).data('tone');
		renderGuidedToneGrid();
		updateGuidedPreview();
	});

	// Length slider
	$('#aichat-guided-length').on('input change', function () {
		guided.length = parseInt($(this).val(), 10);
		updateGuidedPreview();
	});

	// Save guided
	$('#aichat-instr-save-guided').on('click', function () {
		if (!guided.type) {
			alert(__('Please select a chatbot type first.', 'axiachat-ai'));
			return;
		}
		const prompt = buildGuidedPrompt();
		const $btn = $(this);

		$btn.prop('disabled', true).text(__('Saving...', 'axiachat-ai'));

		$.post(
			ajaxUrl,
			{
				action: 'aichat_training_save_instructions',
				nonce: nonce,
				bot_id: botId,
				instructions: prompt,
			},
			(res) => {
				$btn.prop('disabled', false).html(
					`<i class="bi bi-check-lg me-1"></i>${__('Apply & Save Instructions', 'axiachat-ai')}`,
				);
				if (res.success) {
					// Also update the manual textarea
					$('#aichat-instr-textarea').val(prompt);
					$('#aichat-instr-guided-saved').fadeIn().delay(2000).fadeOut();
				} else {
					alert(res.data?.message ? res.data.message : __('Error saving.', 'axiachat-ai'));
				}
			},
		).fail(() => {
			$btn.prop('disabled', false).html(
				`<i class="bi bi-check-lg me-1"></i>${__('Apply & Save Instructions', 'axiachat-ai')}`,
			);
			alert(__('Network error.', 'axiachat-ai'));
		});
	});

	// ---- Init ----
	renderGuidedTypeGrid();
	renderGuidedToneGrid();
	updateGuidedPreview();
})(jQuery);
