// @vitest-environment happy-dom
/**
 * Tests for assets/js/chatbot.js — browser IIFE module.
 *
 * Uses the happy-dom environment so that `window`, `document`, `localStorage`,
 * and `document.cookie` globals are available when the IIFE executes.
 *
 * Strategy:
 *   1. Set up required globals (wpaicChatData, DOM fixture) before loading.
 *   2. Execute the IIFE via indirect eval so it runs in global (not module) scope.
 *   3. Assert on window.WPAICChat public API and internal behaviour.
 *
 * Pure helper tests (session token, cookies) are exercised via the public
 * clearSession() API and by inspecting localStorage/cookie state directly.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const CHATBOT_PATH = path.resolve(__dirname, '../../assets/js/chatbot.js');

// ---------------------------------------------------------------------------
// Load and execute the chatbot IIFE once for the whole suite.
// The indirect-eval form `(0, eval)(code)` runs the code in global scope,
// so `window` resolves to globalThis (provided by happy-dom).
// ---------------------------------------------------------------------------

beforeAll(() => {
	// Wire up the wpaicChatData global that boot() guards against.
	globalThis.wpaicChatData = {
		ajax_url: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce-abc',
		bot_slug: 'default',
		i18n: {},
	};

	const code = fs.readFileSync(CHATBOT_PATH, 'utf-8');
	// eslint-disable-next-line no-eval
	(0, eval)(code);
});

afterEach(() => {
	// Clear localStorage between tests.
	localStorage.clear();
	// Expire any test cookies.
	const cookies = document.cookie.split(';');
	for (const c of cookies) {
		const name = c.split('=')[0].trim();
		if (name) {
			document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
		}
	}
});

// ---------------------------------------------------------------------------
// Public API surface
// ---------------------------------------------------------------------------

describe('WPAICChat public API', () => {
	it('is exposed on window after loading', () => {
		expect(window.WPAICChat).toBeDefined();
		expect(typeof window.WPAICChat).toBe('object');
	});

	it('exposes the correct version string', () => {
		expect(window.WPAICChat.version).toBe('1.0.0');
	});

	it('exposes an instances registry object', () => {
		expect(typeof window.WPAICChat.instances).toBe('object');
	});

	it('exposes init as a function', () => {
		expect(typeof window.WPAICChat.init).toBe('function');
	});

	it('exposes open as a function', () => {
		expect(typeof window.WPAICChat.open).toBe('function');
	});

	it('exposes close as a function', () => {
		expect(typeof window.WPAICChat.close).toBe('function');
	});

	it('exposes clearSession as a function', () => {
		expect(typeof window.WPAICChat.clearSession).toBe('function');
	});
});

// ---------------------------------------------------------------------------
// Session token storage (via clearSession + direct localStorage inspection)
// ---------------------------------------------------------------------------

describe('session token storage', () => {
	const BOT_SLUG = 'test-bot';
	const LS_KEY = `wpaic_session_${BOT_SLUG}`;
	const COOKIE_KEY = `wpaic_sid_${BOT_SLUG}`;

	beforeEach(() => {
		localStorage.clear();
	});

	it('clearSession removes token from localStorage', () => {
		localStorage.setItem(LS_KEY, 'my-session-token');
		window.WPAICChat.clearSession(BOT_SLUG);
		expect(localStorage.getItem(LS_KEY)).toBeNull();
	});

	it('clearSession expires the session cookie', () => {
		// Seed a cookie first.
		document.cookie = `${COOKIE_KEY}=some-token; path=/`;

		window.WPAICChat.clearSession(BOT_SLUG);

		// Cookie value should now be empty or absent (set with expires in the past).
		const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${COOKIE_KEY}=([^;]*)`));
		const val = match ? decodeURIComponent(match[1]) : '';
		expect(val).toBe('');
	});

	it('clearSession for unknown bot slug does not throw', () => {
		expect(() => window.WPAICChat.clearSession('nonexistent-slug-xyz')).not.toThrow();
	});
});

// ---------------------------------------------------------------------------
// init() — does not throw on bad targets
// ---------------------------------------------------------------------------

describe('WPAICChat.init()', () => {
	it('does not throw when given a nonexistent instance ID', () => {
		expect(() => window.WPAICChat.init('nonexistent-widget-id-12345')).not.toThrow();
	});

	it('does not throw when given a null element', () => {
		expect(() => window.WPAICChat.init(null)).not.toThrow();
	});

	it('does not throw when given an empty string', () => {
		expect(() => window.WPAICChat.init('')).not.toThrow();
	});
});

// ---------------------------------------------------------------------------
// open() / close() — gracefully handle unknown instance IDs
// ---------------------------------------------------------------------------

describe('WPAICChat.open() / close()', () => {
	it('open() does not throw for unknown instanceId', () => {
		expect(() => window.WPAICChat.open('no-such-instance')).not.toThrow();
	});

	it('close() does not throw for unknown instanceId', () => {
		expect(() => window.WPAICChat.close('no-such-instance')).not.toThrow();
	});
});

// ---------------------------------------------------------------------------
// Widget initialisation from DOM element
// ---------------------------------------------------------------------------

describe('widget initialisation from DOM', () => {
	let widgetEl;

	beforeEach(() => {
		// Build a minimal widget DOM structure matching templates/chatbot-widget.php.
		widgetEl = document.createElement('div');
		widgetEl.setAttribute('data-wpaic-instance', 'widget-001');
		widgetEl.setAttribute('data-bot-slug', 'test-bot');
		widgetEl.setAttribute('data-mode', 'floating');

		// Launcher button.
		const launcher = document.createElement('button');
		launcher.className = 'wpaic-launcher';
		widgetEl.appendChild(launcher);

		// Panel.
		const panel = document.createElement('div');
		panel.className = 'wpaic-panel';
		panel.setAttribute('role', 'dialog');
		widgetEl.appendChild(panel);

		// Header.
		const header = document.createElement('div');
		header.className = 'wpaic-header';
		panel.appendChild(header);

		// Header title.
		const title = document.createElement('div');
		title.className = 'wpaic-header__title';
		title.id = 'wpaic-title-widget-001';
		header.appendChild(title);

		// Close button.
		const closeBtn = document.createElement('button');
		closeBtn.className = 'wpaic-close';
		header.appendChild(closeBtn);

		// Transcript.
		const transcript = document.createElement('div');
		transcript.className = 'wpaic-transcript';
		transcript.setAttribute('role', 'log');
		panel.appendChild(transcript);

		// Input form.
		const form = document.createElement('form');
		form.className = 'wpaic-form';
		const input = document.createElement('textarea');
		input.className = 'wpaic-input';
		form.appendChild(input);
		const submit = document.createElement('button');
		submit.type = 'submit';
		submit.className = 'wpaic-send';
		form.appendChild(submit);
		panel.appendChild(form);

		document.body.appendChild(widgetEl);
	});

	afterEach(() => {
		if (widgetEl?.parentNode) {
			widgetEl.parentNode.removeChild(widgetEl);
		}
	});

	it('init() with element registers the instance', () => {
		window.WPAICChat.init(widgetEl);
		expect(window.WPAICChat.instances['widget-001']).toBeDefined();
	});

	it('init() with string instance ID registers the instance', () => {
		window.WPAICChat.init('widget-001');
		expect(window.WPAICChat.instances['widget-001']).toBeDefined();
	});

	it('registered instance has expected shape', () => {
		window.WPAICChat.init(widgetEl);
		const inst = window.WPAICChat.instances['widget-001'];
		expect(inst).toBeDefined();
		expect(typeof inst).toBe('object');
	});
});

// ---------------------------------------------------------------------------
// i18n defaults applied at boot
// ---------------------------------------------------------------------------

describe('i18n defaults', () => {
	it('wpaicChatData.i18n.open_chat has a fallback value', () => {
		expect(typeof wpaicChatData.i18n.open_chat).toBe('string');
		expect(wpaicChatData.i18n.open_chat.length).toBeGreaterThan(0);
	});

	it('wpaicChatData.i18n.close_chat has a fallback value', () => {
		expect(typeof wpaicChatData.i18n.close_chat).toBe('string');
		expect(wpaicChatData.i18n.close_chat.length).toBeGreaterThan(0);
	});

	it('wpaicChatData.i18n.error_generic has a fallback value', () => {
		expect(typeof wpaicChatData.i18n.error_generic).toBe('string');
		expect(wpaicChatData.i18n.error_generic.length).toBeGreaterThan(0);
	});

	it('wpaicChatData.i18n.typing has a fallback value', () => {
		expect(typeof wpaicChatData.i18n.typing).toBe('string');
		expect(wpaicChatData.i18n.typing.length).toBeGreaterThan(0);
	});
});
