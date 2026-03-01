const { __, sprintf } = wp.i18n;

(($) => {
	$(document).ready(() => {
		var modal = $('#aichat-mcp-modal');
		var testModal = $('#aichat-mcp-test-modal');
		var form = $('#aichat-mcp-server-form');

		// === TAB NAVIGATION ===
		$('.aichat-mcp-wrap .nav-tab').on('click', function (e) {
			e.preventDefault();
			var target = $(this).attr('href');

			$('.aichat-mcp-wrap .nav-tab').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');

			$('.aichat-mcp-tab-content').hide();
			$(target).show();
		});

		// === TEST TOOLS TAB ===
		var mcpTools = {}; // {server_id: [{name, description, schema}, ...]}

		function loadMCPServers() {
			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_list_servers_for_test',
					nonce: window.aichatMcpData?.nonce ? window.aichatMcpData.nonce : '',
				},
				(response) => {
					if (response?.success && response.data && response.data.servers) {
						var select = $('#aichat-mcp-test-server-select');
						select.html(`<option value="">${__('— Select a server —', 'axiachat-ai')}</option>`);

						$.each(response.data.servers, (serverId, serverName) => {
							select.append(
								$('<option>', {
									value: serverId,
									text: serverName,
								}),
							);
						});
					}
				},
			);
		}

		$('#aichat-mcp-test-server-select').on('change', function () {
			var serverId = $(this).val();
			var toolSelect = $('#aichat-mcp-test-tool-select');
			var runButton = $('#aichat-mcp-test-tool-run');

			toolSelect.prop('disabled', true).html(`<option>${__('Loading tools...', 'axiachat-ai')}</option>`);
			runButton.prop('disabled', true);
			$('#aichat-mcp-test-tool-description').hide();
			$('#aichat-mcp-test-tool-form').empty();
			$('#aichat-mcp-test-tool-result').text('');

			if (!serverId) {
				toolSelect.html(`<option>${__('— Select a server first —', 'axiachat-ai')}</option>`);
				return;
			}

			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_list_tools',
					nonce: window.aichatMcpData?.nonce ? window.aichatMcpData.nonce : '',
					server_id: serverId,
				},
				(response) => {
					if (response?.success && response.data && response.data.tools) {
						mcpTools[serverId] = response.data.tools;
						toolSelect.html(`<option value="">${__('— Select a tool —', 'axiachat-ai')}</option>`);

						$.each(response.data.tools, (_i, tool) => {
							toolSelect.append(
								$('<option>', {
									value: tool.name,
									text: tool.name,
								}),
							);
						});

						toolSelect.prop('disabled', false);
					} else {
						toolSelect.html(`<option>${__('No tools available', 'axiachat-ai')}</option>`);
					}
				},
			);
		});

		$('#aichat-mcp-test-tool-select').on('change', function () {
			var serverId = $('#aichat-mcp-test-server-select').val();
			var toolName = $(this).val();
			var runButton = $('#aichat-mcp-test-tool-run');

			$('#aichat-mcp-test-tool-form').empty();
			$('#aichat-mcp-test-tool-description').hide();
			$('#aichat-mcp-test-tool-result').text('');
			runButton.prop('disabled', true);

			if (!toolName || !mcpTools[serverId]) return;

			var tool = null;
			$.each(mcpTools[serverId], (_, t) => {
				if (!tool && t && t.name === toolName) tool = t;
			});
			if (!tool) return;

			if (tool.description) {
				$('#aichat-mcp-test-tool-desc-text').text(tool.description);
				$('#aichat-mcp-test-tool-description').show();
			}

			var schema = tool.inputSchema || tool.schema || {};
			var properties = schema.properties || {};
			var required = schema.required || [];

			if (!Object.keys(properties).length) {
				$('#aichat-mcp-test-tool-form').html(
					'<p style="color:#666;font-style:italic;">' +
						__('This tool takes no parameters.', 'axiachat-ai') +
						'</p>',
				);
				runButton.prop('disabled', false);
				return;
			}

			var formHTML = '<div style="background:#f9f9f9;padding:15px;border-radius:4px;">';
			formHTML += `<strong>${__('Parameters:', 'axiachat-ai')}</strong><br><br>`;

			$.each(properties, (propName, propDef) => {
				var isRequired = required.indexOf(propName) !== -1;
				var propType = propDef.type || 'string';
				var propDesc = propDef.description || '';

				formHTML += '<div style="margin-bottom:15px;">';
				formHTML += '<label style="display:block;margin-bottom:5px;font-weight:500;">';
				formHTML += propName;
				if (isRequired) formHTML += ' <span style="color:#d63638;">*</span>';
				formHTML += '</label>';

				if (propDesc) {
					formHTML += `<p style="margin:0 0 5px 0;font-size:12px;color:#666;">${propDesc}</p>`;
				}

				if (propType === 'boolean') {
					formHTML +=
						'<select class="mcp-test-param" data-param="' +
						propName +
						'" style="width:100%;max-width:300px;">';
					formHTML += '<option value="true">true</option>';
					formHTML += '<option value="false">false</option>';
					formHTML += '</select>';
				} else if (propType === 'number' || propType === 'integer') {
					formHTML +=
						'<input type="number" class="mcp-test-param" data-param="' +
						propName +
						'" style="width:100%;max-width:300px;" />';
				} else {
					formHTML +=
						'<input type="text" class="mcp-test-param" data-param="' +
						propName +
						'" style="width:100%;max-width:600px;" />';
				}

				formHTML += '</div>';
			});

			formHTML += '</div>';
			$('#aichat-mcp-test-tool-form').html(formHTML);
			runButton.prop('disabled', false);
		});

		$('#aichat-mcp-test-tool-run').on('click', function () {
			var serverId = $('#aichat-mcp-test-server-select').val();
			var toolName = $('#aichat-mcp-test-tool-select').val();
			if (!serverId || !toolName) return;

			var params = {};
			$('.mcp-test-param').each(function () {
				var paramName = $(this).data('param');
				var val = $(this).val();
				if ($(this).is('select') && val === 'true') val = true;
				if ($(this).is('select') && val === 'false') val = false;
				if ($(this).attr('type') === 'number') val = parseFloat(val);
				if (val !== '') params[paramName] = val;
			});

			var statusEl = $('#aichat-mcp-test-tool-status');
			var resultEl = $('#aichat-mcp-test-tool-result');
			var runButton = $(this);

			runButton.prop('disabled', true);
			statusEl.html(
				'<span class="dashicons dashicons-update spin" style="color:#2271b1;"></span> ' +
					__('Executing...', 'axiachat-ai'),
			);
			resultEl.text('');

			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_run_tool',
					nonce: window.aichatMcpData?.nonce ? window.aichatMcpData.nonce : '',
					server_id: serverId,
					tool_name: toolName,
					arguments: JSON.stringify(params),
				},
				(response) => {
					runButton.prop('disabled', false);

					if (response?.success) {
						statusEl.html(
							'<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ' +
								__('Success', 'axiachat-ai'),
						);
						resultEl.text(JSON.stringify(response.data, null, 2));
					} else {
						statusEl.html(
							'<span class="dashicons dashicons-dismiss" style="color:#d63638;"></span> ' +
								__('Error', 'axiachat-ai'),
						);
						resultEl.text(JSON.stringify(response?.data || response || {}, null, 2));
					}
				},
			).fail((xhr) => {
				runButton.prop('disabled', false);
				statusEl.html(
					'<span class="dashicons dashicons-dismiss" style="color:#d63638;"></span> ' +
						__('Request failed', 'axiachat-ai'),
				);
				resultEl.text(xhr.responseText || __('Network error', 'axiachat-ai'));
			});
		});

		$('#tab-test-tools-link').on('click', () => {
			if ($('#aichat-mcp-test-server-select option').length === 1) {
				loadMCPServers();
			}
		});

		$('.aichat-mcp-toggle-enabled').on('change', function () {
			var checkbox = $(this);
			var serverId = checkbox.data('server-id');
			var enabled = checkbox.is(':checked');

			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_toggle_server',
					server_id: serverId,
					enabled: enabled ? 1 : 0,
					_wpnonce: window.aichatMcpData?.nonceAjax ? window.aichatMcpData.nonceAjax : '',
				},
				(response) => {
					var statusEl = $(`.aichat-mcp-status[data-server-id="${serverId}"]`);
					if (response?.success) {
						if (enabled) {
							statusEl.html(
								'<span class="dashicons dashicons-update spin"></span> ' +
									((window.aichatMcpData && aichatMcpData.i18n_connecting) || 'Connecting...'),
							);
							setTimeout(() => {
								$(`.aichat-mcp-test[data-server-id="${serverId}"]`).trigger('click');
							}, 500);
						} else {
							statusEl.html(
								'<span class="dashicons dashicons-minus" style="color:#999;"></span> ' +
									((window.aichatMcpData && aichatMcpData.i18n_disabled) || 'Disabled'),
							);
						}
					} else {
						alert(
							response?.data ||
								(window.aichatMcpData && aichatMcpData.i18n_error_updating) ||
								'Error updating server.',
						);
						checkbox.prop('checked', !enabled);
					}
				},
			);
		});

		$('#mcp-auth-type').on('change', function () {
			var authType = $(this).val();
			$('#mcp-auth-token-row, #mcp-auth-header-row, #mcp-custom-headers-row').hide();
			if (authType === 'bearer') {
				$('#mcp-auth-token-row').show();
			} else if (authType === 'api_key') {
				$('#mcp-auth-token-row, #mcp-auth-header-row').show();
			} else if (authType === 'custom') {
				$('#mcp-custom-headers-row').show();
			}
		});

		$('#aichat-mcp-add-server').on('click', (e) => {
			e.preventDefault();
			form[0].reset();
			$('#mcp-server-id').val('');
			$('#aichat-mcp-modal-title').text(
				(window.aichatMcpData && aichatMcpData.i18n_add_server) || 'Add MCP Server',
			);
			$('#mcp-auth-type').trigger('change');
			$('#mcp-unsupported-transport').hide();
			modal.fadeIn(200);
		});

		$('.aichat-mcp-edit').on('click', function () {
			var serverId = $(this).data('server-id');
			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_get_server',
					server_id: serverId,
					_wpnonce: window.aichatMcpData?.nonceAjax ? window.aichatMcpData.nonceAjax : '',
				},
				(response) => {
					if (response?.success) {
						var server = response.data || {};
						$('#mcp-server-id').val(serverId);
						$('#mcp-name').val(server.name || '');
						$('#mcp-url').val(server.url || '');
						$('#mcp-auth-type')
							.val(server.auth_type || 'none')
							.trigger('change');
						$('#mcp-auth-token').val(server.auth_token || '');
						$('#mcp-auth-header').val(server.auth_header || '');
						$('#mcp-custom-headers').val(server.custom_headers || '');
						if (server.transport && server.transport !== 'http') {
							$('#mcp-unsupported-transport').show();
						} else {
							$('#mcp-unsupported-transport').hide();
						}
						$('#aichat-mcp-modal-title').text(
							(window.aichatMcpData && aichatMcpData.i18n_edit_server) || 'Edit MCP Server',
						);
						modal.fadeIn(200);
					}
				},
			);
		});

		form.on('submit', function (e) {
			e.preventDefault();
			var data = $(this).serializeArray();
			var isNewServer = !$('#mcp-server-id').val();
			data.push({ name: 'action', value: 'aichat_mcp_save_server' });
			data.push({
				name: '_wpnonce',
				value: window.aichatMcpData?.nonceAjax ? window.aichatMcpData.nonceAjax : '',
			});

			$.post(window.ajaxurl, data, (response) => {
				if (response?.success) {
					if (isNewServer && response.data && response.data.server_id) {
						window.location.href = `${window.location.href}&test=${encodeURIComponent(response.data.server_id)}`;
					} else {
						window.location.reload();
					}
				} else {
					alert(
						response?.data ||
							(window.aichatMcpData && aichatMcpData.i18n_error_saving) ||
							'Error saving server.',
					);
				}
			});
		});

		$('.aichat-mcp-delete').on('click', function () {
			if (
				!window.confirm(
					(window.aichatMcpData && aichatMcpData.i18n_confirm_delete) ||
						'Are you sure you want to delete this server?',
				)
			) {
				return;
			}
			var serverId = $(this).data('server-id');
			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_delete_server',
					server_id: serverId,
					_wpnonce: window.aichatMcpData?.nonceAjax ? window.aichatMcpData.nonceAjax : '',
				},
				(response) => {
					if (response?.success) {
						window.location.reload();
					}
				},
			);
		});

		$('.aichat-mcp-test').on('click', function () {
			var serverId = $(this).data('server-id');
			var statusEl = $(`.aichat-mcp-status[data-server-id="${serverId}"]`);
			statusEl.html(
				'<span class="dashicons dashicons-update spin"></span> ' +
					((window.aichatMcpData && aichatMcpData.i18n_testing) || 'Testing...'),
			);

			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_test_server',
					server_id: serverId,
					_wpnonce: window.aichatMcpData?.nonceAjax ? window.aichatMcpData.nonceAjax : '',
				},
				(response) => {
					if (response?.success) {
						statusEl.html(
							'<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ' +
								((window.aichatMcpData && aichatMcpData.i18n_connected) || 'Connected'),
						);
						var data = response.data || {};
						var html =
							'<div class="notice notice-success inline"><p><strong>' +
							((window.aichatMcpData && aichatMcpData.i18n_connection_success) ||
								'Connection successful!') +
							'</strong></p></div>';
						html +=
							'<h3>' +
							((window.aichatMcpData && aichatMcpData.i18n_server_info) || 'Server Information') +
							'</h3>';
						html += '<table class="widefat"><tbody>';
						html +=
							'<tr><th style="width:30%;">' +
							((window.aichatMcpData && aichatMcpData.i18n_protocol_version) || 'Protocol Version') +
							'</th><td>' +
							(data.protocol_version || '—') +
							'</td></tr>';
						html +=
							'<tr><th>' +
							((window.aichatMcpData && aichatMcpData.i18n_server_name) || 'Server Name') +
							'</th><td>' +
							(data.server_name || '—') +
							'</td></tr>';
						html +=
							'<tr><th>' +
							((window.aichatMcpData && aichatMcpData.i18n_server_version) || 'Server Version') +
							'</th><td>' +
							(data.server_version || '—') +
							'</td></tr>';
						html +=
							'<tr><th>' +
							((window.aichatMcpData && aichatMcpData.i18n_capabilities) || 'Capabilities') +
							'</th><td>' +
							(data.capabilities || '—') +
							'</td></tr>';
						html += '</tbody></table>';

						if (data.tools?.length) {
							html +=
								'<h3>' +
								((window.aichatMcpData && aichatMcpData.i18n_available_tools) || 'Available Tools') +
								' (' +
								data.tools.length +
								')</h3>';
							html += '<ul style="column-count:2;">';
							data.tools.forEach((tool) => {
								html += `<li><strong>${tool.name}</strong>`;
								if (tool.description) {
									html += `<br><span style="color:#666;font-size:12px;">${tool.description}</span>`;
								}
								html += '</li>';
							});
							html += '</ul>';
						}

						$('#aichat-mcp-test-results').html(html);
						testModal.fadeIn(200);
					} else {
						statusEl.html(
							'<span class="dashicons dashicons-dismiss" style="color:#dc3232;"></span> ' +
								((window.aichatMcpData && aichatMcpData.i18n_failed) || 'Failed'),
						);
						var msg =
							response?.data ||
							(window.aichatMcpData && aichatMcpData.i18n_unknown_error) ||
							'Unknown error';
						var html =
							'<div class="notice notice-error inline"><p><strong>' +
							((window.aichatMcpData && aichatMcpData.i18n_connection_failed) || 'Connection failed') +
							'</strong></p>';
						html += `<p>${msg}</p></div>`;
						$('#aichat-mcp-test-results').html(html);
						testModal.fadeIn(200);
					}
				},
			);
		});

		$('.aichat-mcp-modal-close, .aichat-mcp-modal-backdrop').on('click', () => {
			modal.fadeOut(200);
			testModal.fadeOut(200);
		});

		if (!$('head style[data-aichat-mcp-spinner]').length) {
			$(
				'<style data-aichat-mcp-spinner>.dashicons.spin { animation: spin 1s linear infinite; } @keyframes spin { 100% { transform: rotate(360deg); } }</style>',
			).appendTo('head');
		}

		$('#aichat-mcp-manage-server-select').on('change', function () {
			var serverId = $(this).val();
			if (!serverId) {
				$('#aichat-mcp-manage-tools-container').hide();
				return;
			}
			$('#aichat-mcp-manage-loading').show();
			$('#aichat-mcp-manage-tools-container').hide();

			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_get_server_tools_status',
					nonce: window.aichatMcpData?.nonce ? window.aichatMcpData.nonce : '',
					server_id: serverId,
				},
				(response) => {
					$('#aichat-mcp-manage-loading').hide();
					if (response?.success && response.data && response.data.tools) {
						renderToolsCheckboxes(response.data.tools);
						$('#aichat-mcp-manage-tools-container').show();
					} else {
						alert(
							(window.aichatMcpData && aichatMcpData.i18n_failed_load_tools) || 'Failed to load tools.',
						);
					}
				},
			);
		});

		function renderToolsCheckboxes(tools) {
			var html = '';
			if (!tools.length) {
				html =
					'<p>' +
					((window.aichatMcpData && aichatMcpData.i18n_no_tools_server) ||
						'No tools found for this server.') +
					'</p>';
			} else {
				tools.forEach((tool) => {
					var checked = tool.enabled ? 'checked' : '';
					html += '<div style="padding:10px;border-bottom:1px solid #f0f0f0;">';
					html += '<label style="display:flex;align-items:center;cursor:pointer;">';
					html +=
						'<input type="checkbox" class="mcp-tool-checkbox" data-tool="' +
						tool.local_name +
						'" ' +
						checked +
						' style="margin:0 10px 0 0;" />';
					html += '<div>';
					html += `<strong style="font-size:14px;">${tool.local_name}</strong>`;
					if (tool.description) {
						html +=
							'<br><span class="description" style="font-size:12px;color:#666;">' +
							tool.description +
							'</span>';
					}
					html += '</div>';
					html += '</label>';
					html += '</div>';
				});
			}
			$('#aichat-mcp-manage-tools-list').html(html);
		}

		$('#aichat-mcp-enable-all').on('click', () => {
			$('.mcp-tool-checkbox').prop('checked', true);
		});

		$('#aichat-mcp-disable-all').on('click', () => {
			$('.mcp-tool-checkbox').prop('checked', false);
		});

		$('#aichat-mcp-save-tools-status').on('click', () => {
			var serverId = $('#aichat-mcp-manage-server-select').val();
			if (!serverId) {
				alert(
					(window.aichatMcpData && aichatMcpData.i18n_select_server_first) || 'Please select a server first.',
				);
				return;
			}

			var toolsStatus = {};
			$('.mcp-tool-checkbox').each(function () {
				var toolName = $(this).data('tool');
				toolsStatus[toolName] = $(this).is(':checked') ? 1 : 0;
			});

			var statusEl = $('#aichat-mcp-save-status');
			statusEl.html('<span class="spinner is-active" style="float:none;"></span>');

			$.post(
				window.ajaxurl,
				{
					action: 'aichat_mcp_save_tools_status',
					nonce: window.aichatMcpData?.nonce ? window.aichatMcpData.nonce : '',
					server_id: serverId,
					tools_status: JSON.stringify(toolsStatus),
				},
				(response) => {
					if (response?.success && response.data && response.data.message) {
						statusEl.html(
							'<span class="dashicons dashicons-yes" style="color:#46b450;"></span> ' +
								response.data.message,
						);
						setTimeout(() => {
							statusEl.html('');
						}, 3000);
					} else {
						statusEl.html(
							'<span class="dashicons dashicons-dismiss" style="color:#dc3232;"></span> ' +
								((window.aichatMcpData && aichatMcpData.i18n_error_saving) || 'Error saving.'),
						);
					}
				},
			);
		});

		var urlParams = new URLSearchParams(window.location.search);
		var testServerId = urlParams.get('test');
		if (testServerId) {
			setTimeout(() => {
				$(`.aichat-mcp-test[data-server-id="${testServerId}"]`).trigger('click');
				var cleanUrl = `${window.location.pathname}?page=aichat-mcp-servers`;
				window.history.replaceState({}, document.title, cleanUrl);
			}, 500);
		}
	});
})(jQuery);
