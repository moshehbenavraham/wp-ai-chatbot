/**
 * LiveChat Frontend - Polling y comunicación con agente
 *
 * Se integra con aichat-frontend.js para manejar estados de LiveChat
 *
 * @package AIChat
 * @subpackage LiveChat
 * @since 2.5.0
 */

(() => {
	// =========================================================================
	// CONFIGURACIÓN
	// =========================================================================

	const POLL_INTERVAL = 3000; // 3 segundos
	const STORAGE_KEY = 'aichat_livechat_last_id';

	// Estados - mapeados a los valores de livechat_mode del backend
	const MODE = {
		BOT: 'bot',
		WAITING: 'waiting_agent',
		AGENT: 'agent',
	};

	// Estados internos (legacy, para compatibilidad)
	const STATUS = {
		BOT: 'bot',
		PENDING: 'pending_agent',
		CONNECTED: 'agent_connected',
		CLOSED: 'closed',
	};

	// =========================================================================
	// ESTADO
	// =========================================================================

	const state = {
		sessionId: null,
		mode: MODE.BOT, // Modo actual: bot, waiting_agent, agent
		status: STATUS.BOT, // Legacy
		agentName: null,
		lastMessageId: 0,
		pollTimer: null,
		isPolling: false,
		lastAgentMessage: null, // Timestamp del último mensaje del agente
		timeoutTimer: null, // Timer para detectar inactividad
	};

	// Timeout por defecto: 5 minutos (se sobreescribe con el setting)
	const DEFAULT_TIMEOUT = 5 * 60 * 1000;

	// =========================================================================
	// INICIALIZACIÓN
	// =========================================================================

	function init() {
		// Verificar que LiveChat está habilitado
		if (!window.AIChatLiveChat?.enabled) {
			console.log('[LiveChat] Not enabled');
			return;
		}

		console.log('[LiveChat] Initializing...');

		// Obtener session_id del widget principal
		state.sessionId = getSessionId();
		if (!state.sessionId) {
			console.log('[LiveChat] No session ID found, will retry...');
			// Reintentar en 1 segundo
			setTimeout(init, 1000);
			return;
		}

		console.log('[LiveChat] Session ID:', state.sessionId);

		// Recuperar lastMessageId de localStorage
		const stored = localStorage.getItem(`${STORAGE_KEY}_${state.sessionId}`);
		if (stored) {
			state.lastMessageId = parseInt(stored, 10) || 0;
		}

		// Escuchar evento de modo livechat desde aichat-frontend.js
		// Este evento se emite en CADA respuesta del chat
		document.addEventListener('aichat_livechat_mode', handleModeChange);

		// Hacer un poll inicial para verificar estado
		checkInitialStatus();
	}

	/**
	 * Handler para cambio de modo (viene de aichat-frontend.js)
	 * Se llama en cada respuesta del chat con el modo actual
	 */
	function handleModeChange(event) {
		const newMode = event.detail?.mode;
		console.log('[LiveChat] Mode event received:', newMode, '(current:', `${state.mode})`);

		if (!newMode) return;

		const previousMode = state.mode;
		state.mode = newMode;

		// Si cambió de modo, reaccionar
		if (previousMode !== newMode) {
			console.log('[LiveChat] Mode changed:', previousMode, '->', newMode);

			// Si pasamos a waiting_agent o agent, iniciar polling
			if (newMode === MODE.WAITING || newMode === MODE.AGENT) {
				startPolling();
				updateUIForMode(newMode);
			}

			// Si volvemos a bot, detener polling
			if (newMode === MODE.BOT) {
				stopPolling();
				updateUIForMode(newMode);
			}
		}
	}

	/**
	 * Actualizar UI según el modo
	 */
	function updateUIForMode(mode) {
		const widget = document.querySelector('.aichat-widget');
		if (!widget) return;

		// Actualizar atributo de datos
		widget.dataset.livechatMode = mode;

		// Mostrar/ocultar indicador debajo del header (no dentro)
		const header = widget.querySelector('.aichat-header');
		if (header) {
			// Buscar o crear el indicador como elemento hermano del header
			let indicator = widget.querySelector('.livechat-mode-indicator');

			if (mode === MODE.AGENT || mode === MODE.WAITING) {
				if (!indicator) {
					indicator = document.createElement('div');
					indicator.className = 'livechat-mode-indicator';
					indicator.style.cssText =
						'background:#fff3cd; color:#856404; padding:6px 12px; font-size:12px; text-align:center; border-bottom:1px solid #ffc107;';
					// Insertar después del header
					header.insertAdjacentElement('afterend', indicator);
				}
				if (mode === MODE.AGENT) {
					indicator.textContent = '🟢 Connected with agent';
					indicator.style.background = '#d4edda';
					indicator.style.color = '#155724';
					indicator.style.borderColor = '#28a745';
				} else {
					indicator.textContent = '🟡 Connecting to agent...';
					indicator.style.background = '#fff3cd';
					indicator.style.color = '#856404';
					indicator.style.borderColor = '#ffc107';
				}
				indicator.style.display = 'block';
			} else {
				if (indicator) {
					indicator.style.display = 'none';
				}
			}
		}
	}

	function getSessionId() {
		// 1. Intentar obtener de la cookie aichat_sid (igual que el widget principal)
		const cookieMatch = document.cookie.match(/(?:^|;)\s*aichat_sid=([^;]+)/);
		if (cookieMatch?.[1]) {
			return decodeURIComponent(cookieMatch[1]);
		}

		// 2. Intentar obtener del widget como data attribute
		const widget = document.querySelector('.aichat-widget');
		if (widget?.dataset.sessionId) {
			return widget.dataset.sessionId;
		}

		// 3. Intentar de localStorage (legacy)
		const stored = localStorage.getItem('aichat_session_id');
		if (stored) {
			return stored;
		}

		// Intentar de AIChatVars
		if (window.AIChatVars?.session_id) {
			return window.AIChatVars.session_id;
		}

		return null;
	}

	// =========================================================================
	// POLLING
	// =========================================================================

	function checkInitialStatus() {
		poll().then(() => {
			// Si está en pending o connected, iniciar polling continuo
			if (state.status === STATUS.PENDING || state.status === STATUS.CONNECTED) {
				startPolling();
			}
		});
	}

	function startPolling() {
		if (state.pollTimer) {
			return; // Ya está corriendo
		}

		console.log('[LiveChat] Starting polling');
		state.pollTimer = setInterval(() => {
			poll();
		}, POLL_INTERVAL);

		// Iniciar timeout timer
		startTimeoutTimer();
	}

	function stopPolling() {
		if (state.pollTimer) {
			console.log('[LiveChat] Stopping polling');
			clearInterval(state.pollTimer);
			state.pollTimer = null;
		}
		stopTimeoutTimer();
	}

	/**
	 * Iniciar timer de timeout de agente
	 */
	function startTimeoutTimer() {
		stopTimeoutTimer(); // Limpiar cualquier timer anterior

		const timeout = window.AIChatLiveChat?.agent_timeout || DEFAULT_TIMEOUT;
		state.lastAgentMessage = Date.now();

		console.log('[LiveChat] Starting timeout timer:', timeout / 1000, 'seconds');

		state.timeoutTimer = setInterval(() => {
			checkAgentTimeout();
		}, 10000); // Verificar cada 10 segundos
	}

	function stopTimeoutTimer() {
		if (state.timeoutTimer) {
			clearInterval(state.timeoutTimer);
			state.timeoutTimer = null;
		}
	}

	/**
	 * Verificar si ha pasado el timeout sin respuesta del agente
	 */
	function checkAgentTimeout() {
		if (state.mode === MODE.BOT) {
			stopTimeoutTimer();
			return;
		}

		const timeout = window.AIChatLiveChat?.agent_timeout || DEFAULT_TIMEOUT;
		const elapsed = Date.now() - state.lastAgentMessage;

		if (elapsed >= timeout) {
			console.log('[LiveChat] Agent timeout reached, returning to bot');
			returnToBot();
		}
	}

	/**
	 * Volver al modo bot por timeout
	 */
	async function returnToBot() {
		stopPolling();
		state.mode = MODE.BOT;
		state.status = STATUS.BOT;
		updateUIForMode(MODE.BOT);

		// Mostrar mensaje de timeout
		const messagesContainer = document.querySelector('.aichat-messages');
		if (messagesContainer) {
			const systemMsg = document.createElement('div');
			systemMsg.className = 'aichat-message aichat-message-system';
			systemMsg.innerHTML =
				'<div class="aichat-message-content"><div class="aichat-message-text">' +
				(window.AIChatLiveChat?.strings?.timeout || 'No response from agent. Returning to AI assistant.') +
				'</div></div>';
			messagesContainer.appendChild(systemMsg);
			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		}

		// Notificar al backend (opcional, el backend también puede tener su propio cron de timeout)
		try {
			const formData = new FormData();
			formData.append('action', 'aichat_livechat_return_to_bot');
			formData.append('nonce', window.AIChatLiveChat.nonce);
			formData.append('session_id', state.sessionId);
			formData.append('reason', 'timeout');

			await fetch(window.AIChatLiveChat.ajax_url, {
				method: 'POST',
				body: formData,
			});
		} catch (e) {
			console.error('[LiveChat] Failed to notify backend of timeout:', e);
		}
	}

	async function poll() {
		if (state.isPolling || !state.sessionId) {
			return;
		}

		state.isPolling = true;

		try {
			const formData = new FormData();
			formData.append('action', 'aichat_livechat_poll');
			formData.append('nonce', window.AIChatLiveChat.nonce);
			formData.append('session_id', state.sessionId);
			formData.append('since_id', state.lastMessageId);

			const response = await fetch(window.AIChatLiveChat.ajax_url, {
				method: 'POST',
				body: formData,
			});

			const data = await response.json();

			if (data.success) {
				handlePollResponse(data.data);
			}
		} catch (error) {
			console.error('[LiveChat] Poll error:', error);
		} finally {
			state.isPolling = false;
		}
	}

	function handlePollResponse(data) {
		const previousStatus = state.status;
		state.status = data.status;
		state.agentName = data.agent_name;

		// Actualizar lastMessageId
		if (data.last_id > state.lastMessageId) {
			state.lastMessageId = data.last_id;
			localStorage.setItem(`${STORAGE_KEY}_${state.sessionId}`, state.lastMessageId);
		}

		// Detectar cambios de estado
		if (previousStatus !== state.status) {
			console.log('[LiveChat] Status changed:', previousStatus, '->', state.status);
			handleStateTransition(previousStatus, state.status);
		}

		// Mostrar mensajes nuevos y reiniciar timeout si hay mensaje del agente
		if (data.messages && data.messages.length > 0) {
			let hasAgentMessage = false;
			data.messages.forEach((msg) => {
				displayMessage(msg);
				if (msg.sender_type === 'agent') {
					hasAgentMessage = true;
				}
			});

			// Reiniciar timeout si recibimos mensaje del agente
			if (hasAgentMessage) {
				state.lastAgentMessage = Date.now();
				console.log('[LiveChat] Agent message received, timeout reset');
			}
		}

		// Controlar polling según estado
		if (state.status === STATUS.BOT || state.status === STATUS.CLOSED) {
			stopPolling();
		} else if (!state.pollTimer) {
			startPolling();
		}
	}

	// =========================================================================
	// TRANSICIONES DE ESTADO
	// =========================================================================

	function handleStateTransition(from, to) {
		// Emitir evento para que el widget principal pueda reaccionar
		document.dispatchEvent(
			new CustomEvent('aichat_livechat_state', {
				detail: {
					from: from,
					to: to,
					agentName: state.agentName,
				},
			}),
		);

		// Mapear status interno a mode para UI
		let mode = MODE.BOT;
		if (to === STATUS.PENDING) mode = MODE.WAITING;
		else if (to === STATUS.CONNECTED) mode = MODE.AGENT;

		updateUIForMode(mode);
	}

	// =========================================================================
	// MENSAJES
	// =========================================================================

	function displayMessage(msg) {
		const messagesContainer = document.querySelector('.aichat-messages');
		if (!messagesContainer) {
			console.error('[LiveChat] Messages container not found');
			return;
		}

		// Verificar si el mensaje ya existe
		if (messagesContainer.querySelector(`[data-message-id="${msg.id}"]`)) {
			return;
		}

		const messageDiv = document.createElement('div');
		messageDiv.className = 'aichat-message';
		messageDiv.dataset.messageId = msg.id;

		if (msg.sender_type === 'agent') {
			messageDiv.classList.add('aichat-message-bot', 'livechat-agent-message');
		} else if (msg.sender_type === 'system') {
			messageDiv.classList.add('aichat-message-system');
		} else {
			messageDiv.classList.add('aichat-message-user');
		}

		// Contenido
		const content = document.createElement('div');
		content.className = 'aichat-message-content';

		// No mostrar nombre del agente en cada mensaje (ya está en el indicador)

		const text = document.createElement('div');
		text.className = 'aichat-message-text';
		text.textContent = msg.message;
		content.appendChild(text);

		messageDiv.appendChild(content);
		messagesContainer.appendChild(messageDiv);

		// Scroll to bottom
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	}

	/**
	 * Enviar mensaje del visitante cuando está con agente
	 */
	async function sendVisitorMessage(message) {
		if (state.status !== STATUS.CONNECTED) {
			return false;
		}

		try {
			const formData = new FormData();
			formData.append('action', 'aichat_livechat_visitor_message');
			formData.append('nonce', window.AIChatLiveChat.nonce);
			formData.append('session_id', state.sessionId);
			formData.append('message', message);

			const response = await fetch(window.AIChatLiveChat.ajax_url, {
				method: 'POST',
				body: formData,
			});

			const data = await response.json();
			return data.success;
		} catch (error) {
			console.error('[LiveChat] Send message error:', error);
			return false;
		}
	}

	// =========================================================================
	// API PÚBLICA (extender, no sobrescribir)
	// =========================================================================

	// Preservar datos originales de wp_localize_script
	const originalConfig = window.AIChatLiveChat || {};

	window.AIChatLiveChat = Object.assign({}, originalConfig, {
		// API pública
		getMode: () => state.mode,
		getStatus: () => state.status, // Legacy
		getAgentName: () => state.agentName,
		isConnected: () => state.mode === MODE.AGENT,
		isWaiting: () => state.mode === MODE.WAITING,
		isPending: () => state.status === STATUS.PENDING, // Legacy
		sendMessage: sendVisitorMessage,
		startPolling: startPolling,
		stopPolling: stopPolling,
		poll: poll,
	});

	// =========================================================================
	// ARRANQUE
	// =========================================================================

	// Esperar a que el widget principal esté listo
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => {
			setTimeout(init, 500); // Pequeño delay para asegurar que el widget principal está listo
		});
	} else {
		setTimeout(init, 500);
	}
})();
