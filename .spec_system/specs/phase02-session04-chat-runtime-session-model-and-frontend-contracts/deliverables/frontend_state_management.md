# Frontend State Management Specification

**Session**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Module**: `assets/js/chatbot.js` (frontend), `admin/js/admin.js` (admin)
**Resolves**: CFI-RUN-004 (frontend JS architecture -- vanilla JS, no jQuery), CFI-ADM-005 (admin JS architecture -- vanilla JS, no jQuery)
**Tasks**: T005 (architecture decision), T011 (frontend state management spec)

---

## 1. Architecture Decision: Vanilla JS, No jQuery, No Build Tools

### 1.1 Decision Statement

The WPAIC plugin frontend uses **vanilla JavaScript** exclusively, delivered as browser-ready IIFE scripts with no transpilation, no bundling, and no jQuery dependency. This decision applies to both the chat widget runtime (`assets/js/chatbot.js`) and the admin interface (`admin/js/admin.js`), resolving two carry-forward items identified during Phase 1:

- **CFI-RUN-004**: jQuery coupling in chat runtime is unnecessary and harmful.
- **CFI-ADM-005**: Admin JS should follow the same architecture as the frontend to prevent divergent patterns.

### 1.2 Source Plugin Analysis

All three source plugins wrap their entire frontend JavaScript in jQuery:

| Plugin | File | Entry Pattern | jQuery Usage |
|--------|------|---------------|-------------|
| AxiaChat | `axiachat-ai/assets/js/aichat-frontend.js` line 11 | `(($) => { ... })(jQuery)` | Selector queries, `.data()` state, `.ajax()`, `.each()`, `.on()` events, `$.Event()`, `.prop()`, `.find()`, `.html()`, `.append()` |
| MxChat | `mxchat-basic/js/chat-script.js` line 1 | `jQuery(document).ready(($) => { ... })` | Same as AXC plus `getElement()` returning jQuery objects (line 98), cookie helpers |
| Baachal | `baachal/assets/chatbot.js` line 1 | `jQuery(document).ready(($) => { ... })` | `$('#id')` selectors, `$.ajax()`, `$(document).trigger()`, `.fadeIn()/.fadeOut()`, `.prop()` |

Every jQuery API used by the source plugins has a native equivalent in ES2017+, which is the baseline for all browsers supported by WordPress 6.0+:

| jQuery API | Native Equivalent | Used By |
|-----------|-------------------|---------|
| `$('#id')` | `document.getElementById()` / `document.querySelector()` | AXC, MXC, BAA |
| `$('.class')` | `document.querySelectorAll()` | AXC, MXC, BAA |
| `.each()` | `NodeList.forEach()` / `for` loop | AXC (line 104), MXC (line 187) |
| `.on('click', fn)` | `addEventListener('click', fn)` | AXC, MXC, BAA |
| `.data('key', val)` | `el.dataset.key` / private Map | AXC (lines 108, 1032) |
| `$.ajax()` | `fetch()` / `XMLHttpRequest` | AXC, MXC, BAA |
| `.append(html)` | `el.appendChild()` / `el.insertAdjacentHTML()` | AXC, MXC, BAA |
| `.prop('disabled', true)` | `el.disabled = true` | AXC (line 346), BAA (line 266) |
| `$(document).trigger()` | `el.dispatchEvent(new CustomEvent())` | BAA (lines 60, 105, 141) |
| `.fadeIn()/.fadeOut()` | CSS transitions + class toggle | BAA (lines 62, 82) |

Ref: AXC `axiachat-ai/includes/class-aichat-core.php` line 70 (jQuery registered as dependency: `['jquery', 'wp-i18n']`), MXC `mxchat-basic/includes/class-mxchat-integrator.php` line 9357 (jQuery implicitly required), BAA `baachal/baachal.php` line 350 (jQuery dependency on enqueue).

### 1.3 Decision Rationale

| Criterion | jQuery (all 3 source plugins) | Vanilla JS (WPAIC) | Winner |
|-----------|-------------------------------|---------------------|--------|
| Page weight | +87KB minified (jQuery 3.7 shipped by WP) | 0KB additional library load | Vanilla |
| Browser compat | Normalizes IE11; irrelevant since WP 6.x dropped IE | All required APIs in ES2017+ (WP 6.x baseline) | Vanilla |
| Theme conflicts | Themes that deregister or defer jQuery break plugin initialization | No external dependency to deregister | Vanilla |
| Streaming support | No native ReadableStream integration; MXC works around this with `fetch` inside jQuery wrapper | Native `fetch` + `ReadableStream` + `EventSource` | Vanilla |
| WordPress direction | WP core is moving away from jQuery (Gutenberg uses React, admin modernization underway) | Aligns with WP's direction | Vanilla |
| Testing | Requires jsdom + jQuery mock in test environment | Standard DOM APIs; easy to stub in Vitest | Vanilla |
| Contributor onboarding | jQuery API well-known but increasingly niche | `querySelector`/`fetch`/`addEventListener` are universal web skills | Vanilla |

### 1.4 No-Build-Tools Constraint

The plugin ships browser-ready assets with no transpilation, bundling, or compilation step. Rationale:

1. **WordPress ecosystem norm**: Most WP plugins ship vanilla JS; build pipelines add contributor friction and CI complexity. None of the three source plugins use build tools.
2. **Hosting diversity**: WordPress sites run on shared hosts where Node.js is unavailable for asset builds. The plugin must be installable via ZIP upload with zero post-install steps.
3. **Debug transparency**: Site administrators can read the shipped JS directly in their editor without source maps. This is critical for WP's open-source culture.
4. **Cache busting**: WordPress provides `wp_enqueue_script` with version-stamped URLs. No Webpack content hashing needed.

### 1.5 Module Pattern: IIFE with Revealing Module

The IIFE (Immediately Invoked Function Expression) pattern provides module-level encapsulation without requiring ES module syntax (`import`/`export`), `<script type="module">` tags (which have CORS requirements that conflict with some WP CDN setups), or bundlers.

```javascript
/**
 * WP AI Chatbot -- Frontend Chat Widget
 * @file assets/js/chatbot.js
 * @version 1.0.0
 *
 * Vanilla JS, no jQuery, no build tools.
 * IIFE + revealing module on window.WPAICChat.
 */
(function (window, document) {
    'use strict';

    // --- Private state (inaccessible from outside) ---
    var registry = {};
    var config = null;

    // --- Private functions ---
    function privateHelper() { /* ... */ }

    // --- Public API (revealing module) ---
    var WPAICChat = {
        version: '1.0.0',
        getInstance: function (botSlug) { return registry[botSlug] || null; },
        getInstances: function () { return Object.keys(registry); },
        getSessionToken: function (botSlug) { return getSessionToken(botSlug); },
    };

    // Freeze to prevent accidental mutation by themes/plugins
    if (typeof Object.freeze === 'function') {
        Object.freeze(WPAICChat);
    }

    // Single global export
    window.WPAICChat = WPAICChat;

})(window, document);
```

This mirrors the MXC `MxChatInstances` object pattern (`mxchat-basic/js/chat-script.js` lines 7-79) but without the jQuery wrapper. The revealing module exposes only the intended public API while keeping the instance registry, configuration, and all helper functions private within the IIFE closure.

### 1.6 CFI Resolution Scope

| CFI Item | Status | Resolution |
|----------|--------|-----------|
| CFI-RUN-004 | **Resolved** | Frontend chat widget JS uses vanilla IIFE in `assets/js/chatbot.js`. The `wp_register_script` call specifies an empty dependency array `array()` -- no jQuery. |
| CFI-ADM-005 | **Resolved** | Admin JS uses the same vanilla IIFE pattern in `admin/js/admin.js`. Separate IIFE closure, separate global namespace (`window.WPAICAdmin`), same conventions. Loaded only on plugin admin pages via `admin_enqueue_scripts` hook with page-slug gating. |

Phase 05 implementers can proceed with parallel frontend and admin JS development using the patterns defined in this document.

### 1.7 Reference Anchors

| Source Plugin | File | Lines | Pattern Observed | Adopted/Adapted |
|---------------|------|-------|------------------|-----------------|
| MxChat | `mxchat-basic/js/chat-script.js` | 7-79 | `MxChatInstances` registry as plain object in jQuery closure | Adopted: registry pattern, adapted to vanilla IIFE |
| MxChat | `mxchat-basic/js/chat-script.js` | 1 | `jQuery(document).ready()` entry point | Replaced: native `DOMContentLoaded` |
| Baachal | `baachal/assets/chatbot.js` | 1 | `jQuery(document).ready()` entry point | Replaced: native `DOMContentLoaded` |
| Baachal | `baachal/assets/chatbot.js` | 94-211 | jQuery custom events on `$(document)` | Adapted: native `CustomEvent` on widget element |
| AxiaChat | `axiachat-ai/assets/js/aichat-frontend.js` | 11 | jQuery IIFE `(($) => { ... })(jQuery)` | Replaced: vanilla `(function(window, document) { ... })()` |
| AxiaChat | `axiachat-ai/assets/js/aichat-frontend.js` | 104-112 | Double-init guard via `$root.data('aichatReady')` | Adapted: `data-wpaic-initialized` attribute |
| AxiaChat | `axiachat-ai/includes/class-aichat-core.php` | 70 | `['jquery', 'wp-i18n']` dependency array | Replaced: `array()` (empty, no deps) |

---

## 2. Global Namespace: `window.WPAICChat`

### 2.1 Namespace Definition

The plugin exposes exactly two globals on `window`:

| Global | Type | Context | Set By |
|--------|------|---------|--------|
| `wpaicConfig` | Object | Frontend pages with chatbot shortcode | PHP via `wp_localize_script` |
| `WPAICChat` | Object (frozen) | Frontend pages with chatbot shortcode | JavaScript IIFE at script load |

Admin pages have their own pair:

| Global | Type | Context | Set By |
|--------|------|---------|--------|
| `wpaicAdminData` | Object | Plugin admin pages only | PHP via `wp_localize_script` |
| `WPAICAdmin` | Object (frozen) | Plugin admin pages only | JavaScript IIFE at script load |

No other globals are created. The frontend and admin namespaces never coexist on the same page (admin pages do not render the chatbot shortcode; public pages do not load admin scripts).

### 2.2 `WPAICChat` Public API

```javascript
window.WPAICChat = Object.freeze({
    // Identity
    version: '1.0.0',

    // Instance access
    getInstance:     function (botSlug) { /* ... */ },  // Returns instance API or null
    getInstances:    function () { /* ... */ },          // Returns array of active bot slugs

    // Session access (read-only)
    getSessionToken: function (botSlug) { /* ... */ },  // Returns token string or null

    // Event helpers (convenience wrappers for addEventListener/removeEventListener)
    on:  function (eventName, callback) { /* ... */ },  // Listen on document (catches all widgets via bubbling)
    off: function (eventName, callback) { /* ... */ },  // Remove listener from document

    // Debug (only populated when wpaicConfig.debug === true)
    debug: {
        getRegistry: function () { /* ... */ },         // Returns shallow copy of registry
        getState:    function (botSlug) { /* ... */ },  // Returns instance internal state snapshot
    },
});
```

### 2.3 Namespace Protection

The namespace object is frozen after construction via `Object.freeze()`. This prevents third-party themes or plugins from accidentally overwriting API methods. The `debug` sub-object is only attached when `wpaicConfig.debug === true`, keeping the production API surface minimal.

### 2.4 Reference Comparison

| Plugin | Global Names | Count | Protection |
|--------|-------------|-------|------------|
| AxiaChat | `AIChatVars`, `AIChatGlobal`, `AIChatGDPR` | 3 globals | None |
| MxChat | `mxchatChat` (config); `MxChatInstances` is closure-scoped, not global | 1 global | None |
| Baachal | `baachal_ajax` (config) | 1 global | None |
| WPAIC | `wpaicConfig` (config), `WPAICChat` (API) | 2 globals | `Object.freeze` on API |

Ref: AXC uses 3 separate `wp_localize_script` objects (`class-aichat-core.php` line 80 for `AIChatVars`, separate calls for `AIChatGlobal` and `AIChatGDPR`); MXC keeps `MxChatInstances` private inside jQuery closure (`chat-script.js` line 7); BAA exposes only the `wp_localize_script` data (`baachal.php` line 350).

---

## 3. Instance Registry

### 3.1 Purpose

The instance registry is a private plain object inside the IIFE closure that tracks per-widget state. It enables multiple chatbot widgets on the same page, each keyed by the bot slug read from the `data-wpaic-bot` attribute on the widget root element.

### 3.2 Registry Data Structure

```javascript
// Private, inside IIFE closure -- not accessible from outside
var registry = {};

// Each entry is a state object:
registry['support-agent'] = {
    // --- Identity ---
    botSlug:       'support-agent',            // Bot identifier (registry key)
    instanceId:    'wpaic-1',                  // From root element ID (wpaic-chatbot-wpaic-1)
    rootEl:        HTMLElement,                // .wpaic-chatbot root element reference

    // --- Session ---
    sessionToken:  'xxxxxxxx-xxxx-4xxx-...',   // UUID v4 or null (no session yet)
    sessionActive: true,                       // False after explicit close or expiry

    // --- UI State (see section 5) ---
    uiState:       'idle',                     // Current state machine value

    // --- Streaming ---
    streamController: null,                    // AbortController for active stream
    streamBuffer:     '',                      // Accumulated raw text during streaming
    currentStreamEl:  null,                    // DOM element being progressively filled

    // --- Message Metadata (messages live in DOM, see section 6) ---
    messageCount:         0,                   // Total messages rendered (user + bot)
    lastMessageTimestamp: 0,                   // Unix ms of most recent message

    // --- Error Recovery ---
    retryCount:   0,                           // Consecutive error count (reset on success)
    lastMessage:  '',                          // Last user message text (for retry)

    // --- Consent ---
    gdprConsented: true,                       // False when GDPR consent gate is active

    // --- Configuration (read from data-wpaic-* attributes at init) ---
    config: {
        layout:              'floating',       // 'floating' | 'inline'
        position:            'br',             // 'br' | 'bl' | 'tr' | 'tl'
        color:               '#0073aa',
        avatar:              '',
        title:               'Support Bot',
        placeholder:         'Type your question...',
        startSentence:       'Hello! How can I help?',
        suggestionsEnabled:  false,
        suggestionsCount:    3,
        maxLength:           500,
        historyPersistence:  true,
    },
};
```

### 3.3 Registry Operations

```javascript
function createInstance(botSlug, rootEl, widgetConfig) {
    // Same bot slug on multiple widgets: first instance is primary (owns session),
    // subsequent instances get a suffixed key for registry isolation
    var key = botSlug;
    if (registry[botSlug]) {
        key = botSlug + '__' + widgetConfig.instanceId;
    }

    registry[key] = {
        botSlug:              botSlug,
        instanceId:           widgetConfig.instanceId,
        rootEl:               rootEl,
        sessionToken:         null,
        sessionActive:        false,
        uiState:              'idle',
        streamController:     null,
        streamBuffer:         '',
        currentStreamEl:      null,
        messageCount:         0,
        lastMessageTimestamp: 0,
        retryCount:           0,
        lastMessage:          '',
        gdprConsented:        true,
        config:               widgetConfig,
    };

    // Copy session token from primary instance if same bot
    if (key !== botSlug && registry[botSlug]) {
        registry[key].sessionToken = registry[botSlug].sessionToken;
        registry[key].sessionActive = registry[botSlug].sessionActive;
    }

    return registry[key];
}

function getInstance(botSlug) {
    return registry[botSlug] || null;
}

function getAllInstances() {
    return Object.keys(registry);
}

function destroyInstance(key) {
    var instance = registry[key];
    if (!instance) return;

    // Abort in-flight requests
    if (instance.streamController) {
        instance.streamController.abort();
    }
    // Remove initialized marker
    if (instance.rootEl) {
        instance.rootEl.removeAttribute('data-wpaic-initialized');
    }
    delete registry[key];
}
```

### 3.4 Multi-Instance Behavior

When the same bot slug appears in two shortcodes on the same page:
- Both widgets share the same session token (same `localStorage` key `wpaic_session_{bot_slug}`).
- Each widget maintains separate DOM state (separate message containers).
- Messages sent from one widget appear only in that widget's DOM.
- The conversation continues seamlessly regardless of which widget the user interacts with.

This follows the design specified in `widget_shortcode_contract.md` section 7.2.

### 3.5 Reference Comparison

| Aspect | AxiaChat | MxChat | Baachal | WPAIC |
|--------|----------|--------|---------|-------|
| Registry type | No central registry; `$root.data()` per DOM element | `MxChatInstances.instances` plain object | No registry (single instance) | `registry` plain object in IIFE closure |
| Registry key | jQuery index (`idx`) from `.each()` | `botId` string | N/A | `botSlug` string |
| Multi-instance | Yes via `$('.aichat-widget').each()` (AXC line 104) | Yes via `$('.mxchat-chatbot-wrapper').each()` (MXC line 187) | No (single `$('#ai-chatbot-widget')`) | Yes via `querySelectorAll('.wpaic-chatbot')` |
| Double-init guard | `$root.data('aichatReady')` (AXC line 108) | `instances[botId]` existence check (MXC line 12) | None | `data-wpaic-initialized` attribute + `registry[key]` check |
| Instance shape | Ad-hoc jQuery `.data()` properties | Defined at lines 18-31 (8 properties) | Closure variables `isOpen`, `isLoading` | Formal 16-property object (section 3.2) |
| Cleanup | None | None | None | `destroyInstance()` aborts streams, removes marker, deletes entry |

Ref: MXC `chat-script.js` lines 7-79 (full `MxChatInstances` with `init`, `get`, `getAllBotIds`, `getChatSession`, `setChatSession`, `resetChatSession`), AXC `aichat-frontend.js` lines 104-112 (per-element init guard), BAA `chatbot.js` lines 2-3 (closure-scoped `isOpen`/`isLoading`).

---

## 4. Session Token Management

### 4.1 Storage Strategy

Session tokens are stored client-side with localStorage as primary and a cookie as fallback. Each bot gets its own token, enabling multi-bot pages.

| Storage | Key Pattern | TTL | Scope | When Used |
|---------|------------|-----|-------|-----------|
| Primary: `localStorage` | `wpaic_session_{bot_slug}` | Until explicitly cleared | Per-origin, per-bot | Default; survives page navigation |
| Fallback: `cookie` | `wpaic_sid_{bot_slug}` | 24 hours (`max-age=86400`) | Per-domain, per-bot, `SameSite=Lax; Path=/` | When localStorage is unavailable (incognito mode, quota exceeded, disabled by browser) |

### 4.2 Per-Bot Scoping

```
localStorage key: wpaic_session_support-agent
localStorage key: wpaic_session_sales-bot
cookie name:      wpaic_sid_support-agent
cookie name:      wpaic_sid_sales-bot
```

This combines MXC's per-bot scoping (cookie `mxchat_session_id_{botId}`, `chat-script.js` lines 48-61) with BAA's localStorage preference (`localStorage.getItem('baachal_session_id')`, `chatbot.js` lines 6-9).

### 4.3 Token Operations

```javascript
var STORAGE_PREFIX = 'wpaic_session_';
var COOKIE_PREFIX  = 'wpaic_sid_';
var COOKIE_MAX_AGE = 86400; // 24 hours

function getSessionToken(botSlug) {
    var key = STORAGE_PREFIX + botSlug;
    var token = null;

    // Try localStorage first
    try {
        token = localStorage.getItem(key);
        if (token && isValidUUID(token)) return token;
    } catch (e) {
        // localStorage unavailable -- private browsing, quota, etc.
    }

    // Fallback to cookie
    token = getCookie(COOKIE_PREFIX + botSlug);
    if (token && isValidUUID(token)) return token;

    return null; // No stored token; server will create session on first message
}

function setSessionToken(botSlug, token) {
    var key = STORAGE_PREFIX + botSlug;

    // Write localStorage
    try { localStorage.setItem(key, token); }
    catch (e) { /* localStorage unavailable */ }

    // Always write cookie as fallback
    setCookie(COOKIE_PREFIX + botSlug, token, COOKIE_MAX_AGE);
}

function clearSessionToken(botSlug) {
    try { localStorage.removeItem(STORAGE_PREFIX + botSlug); }
    catch (e) { /* ignore */ }

    // Expire cookie
    setCookie(COOKIE_PREFIX + botSlug, '', 0);
}

function isValidUUID(str) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(str);
}

// Cookie helpers (same technique as MXC chat-script.js lines 155-158)
function getCookie(name) {
    var value = '; ' + document.cookie;
    var parts = value.split('; ' + name + '=');
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function setCookie(name, value, maxAge) {
    document.cookie = name + '=' + encodeURIComponent(value)
        + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
}
```

### 4.4 Token Lifecycle

```
Page Load
    |
    v
getSessionToken(botSlug)
    |
    +-- token found in localStorage? ---> Use it; send with first message
    |       |
    |       +-- server validates ownership
    |       |       |
    |       |       +-- valid? -----> Resume conversation, load history
    |       |       +-- invalid? ---> Server returns new token in response
    |       |                           |
    |       |                           v
    |       |                       setSessionToken(botSlug, newToken)
    |       |                       dispatch 'wpaic:session-created'
    |       |
    |       +-- token found in cookie fallback? ---> Same flow as above
    |
    +-- NO token found? -----> Widget renders normally (idle state)
                                    |
                                    v
                                User sends first message
                                    |
                                    v
                                Server creates session, returns token
                                    |
                                    v
                                setSessionToken(botSlug, token)
                                dispatch 'wpaic:session-created'
```

Key design decision: **Token generation is server-side** (UUID v4 via `wp_generate_uuid4()`). The frontend never generates session tokens. This differs from all three source plugins (AXC: `crypto.randomUUID()` client-side at line 1797; MXC: `Math.random().toString(36)` client-side at line 162; BAA: `generateUUID()` client-side at line 8) and eliminates the risk of client-side token collisions.

### 4.5 Session Reset ("New Conversation")

```javascript
function resetSession(instance) {
    // 1. Clear stored token
    clearSessionToken(instance.botSlug);

    // 2. Reset instance state
    instance.sessionToken = null;
    instance.sessionActive = false;
    instance.messageCount = 0;
    instance.lastMessageTimestamp = 0;
    instance.retryCount = 0;

    // 3. Abort any in-flight request
    if (instance.streamController) {
        instance.streamController.abort();
        instance.streamController = null;
    }

    // 4. Clear DOM messages
    var messagesEl = instance.rootEl.querySelector('.wpaic-chatbot__messages');
    if (messagesEl) {
        messagesEl.innerHTML = '';
    }

    // 5. Dispatch events
    dispatchWidgetEvent(instance.rootEl, 'wpaic:session-created', {
        botSlug: instance.botSlug,
        sessionToken: null, // Will be set on next message send
    });

    // 6. Restore idle state
    setState(instance, 'idle');
}
```

Ref: MXC `chat-script.js` lines 67-78 (`resetChatSession` clears cookie, empties DOM children, resets flags), AXC `aichat-frontend.js` lines 442-461 (cookie deletion via `Max-Age=0`, `$messages.empty()`, new session ID generation).

### 4.6 Reference Comparison

| Aspect | AxiaChat | MxChat | Baachal | WPAIC |
|--------|----------|--------|---------|-------|
| Primary storage | Cookie | Cookie | localStorage | localStorage |
| Fallback storage | None | None | None | Cookie |
| Key format | `aichat_sid` (global, single cookie) | `mxchat_session_id_{botId}` (per-bot) | `baachal_session_id` (global) | `wpaic_session_{bot_slug}` (per-bot) |
| Cookie TTL | 30 days (AXC line 1799) | 24 hours (MXC line 61) | N/A (no cookie) | 24 hours (fallback only) |
| Per-bot scoping | No (single session for all bots) | Yes | No (single bot) | Yes |
| Token generation | Client-side (AXC line 1797) | Client-side (MXC line 162) | Client-side (BAA line 8) | Server-side (`wp_generate_uuid4()`) |
| Token format | UUID or random string | `mxchat_chat_` + random 9-char | `chat_` + UUID | UUID v4 (RFC 4122) |
| SameSite | Lax (AXC line 1800) | Lax (MXC line 61) | N/A | Lax |

---

## 5. UI State Machine

### 5.1 States

Each widget instance maintains a `uiState` string property that drives visual feedback, input availability, and allowed operations.

| State | Value | Description | Input Enabled | Visual Indicator |
|-------|-------|-------------|---------------|------------------|
| Idle | `'idle'` | Ready for user input; default state | Yes | Default appearance |
| Sending | `'sending'` | Message submitted, waiting for response (non-streaming path) | No | Typing indicator in messages area, send button disabled |
| Streaming | `'streaming'` | SSE/fetch stream active, chunks arriving | No | Progressive text rendering, typing cursor animation |
| Error | `'error'` | Last operation failed | Yes | Error message with retry affordance |
| Offline | `'offline'` | Browser reports no network connectivity | No | Offline banner, input disabled |
| Disabled | `'disabled'` | Widget blocked by external condition (GDPR consent pending, rate limited, admin-disabled) | No | Explanatory overlay or message, all inputs disabled |

### 5.2 State Transition Diagram

```
                                        send (non-streaming)
            +--------- [idle] --------------------------------> [sending]
            |             ^   \                                    |
            |             |    \  send (streaming)                 | response received
            |             |     \                                  v
            |             |      +---------------------------->[streaming]
            |             |                                       |
            |             |    success                            | stream done
            |             +<--------------------------------------+
            |             |
            |             |    error (from sending or streaming)
            |             +<----------- [error]
            |             |                ^
            |             |  retry/dismiss |
            |             +--------------->+
            |
            |    network lost            network restored
            +<----------- [offline] ---------->+
            |                                  |
            |                                  v
            |                               [idle]
            |
            |    consent given / rate limit cleared / admin enable
            +<----------- [disabled] --------->+
                                               |
                                               v
                                            [idle]
```

### 5.3 Allowed Transitions

| From | To | Trigger | Guard Condition | Side Effects |
|------|----|---------|-----------------|-------------|
| `idle` | `sending` | User submits message; bot has streaming disabled | Input non-empty; GDPR consented; not rate-limited | Disable input, show typing indicator, dispatch `wpaic:message-sent` |
| `idle` | `streaming` | User submits message; bot has streaming enabled | Same as above | Disable input, create streaming message shell |
| `idle` | `offline` | `window` `offline` event fires | `navigator.onLine === false` | Show offline banner, disable input |
| `idle` | `disabled` | GDPR consent revoked, rate limit hit, or admin disable action | External condition detected | Show explanatory message, disable all interaction |
| `sending` | `idle` | Full JSON response received successfully | HTTP 200, `data.success === true` | Render bot message, re-enable input, dispatch `wpaic:response-received` |
| `sending` | `error` | Network failure, timeout, HTTP 4xx/5xx, or `data.success === false` | -- | Show error message, dispatch `wpaic:error` |
| `streaming` | `idle` | Stream `[DONE]` signal received or ReadableStream closed | -- | Finalize rendered content, sanitize HTML, re-enable input, dispatch `wpaic:response-received` |
| `streaming` | `error` | Stream connection dropped, server error chunk, or timeout | -- | Keep partial content visible, show error, dispatch `wpaic:error` |
| `error` | `idle` | User dismisses error or types new message | -- | Reset `retryCount`, clear error display |
| `error` | `sending` | User clicks retry button | `retryCount < 3` | Increment `retryCount`, re-send `lastMessage` |
| `offline` | `idle` | `window` `online` event fires | `navigator.onLine === true` | Hide offline banner, re-enable input |
| `disabled` | `idle` | Blocking condition cleared (consent granted, rate limit expired, admin re-enabled) | Condition check passes | Remove overlay, re-enable input |

### 5.4 State Transition Implementation

```javascript
var VALID_STATES = ['idle', 'sending', 'streaming', 'error', 'offline', 'disabled'];

function setState(instance, newState) {
    if (VALID_STATES.indexOf(newState) === -1) {
        if (DEBUG) console.warn('[WPAIC] Invalid state:', newState);
        return;
    }

    var oldState = instance.uiState;
    if (oldState === newState) return;

    instance.uiState = newState;

    // Update CSS classes on root element
    var root = instance.rootEl;
    for (var i = 0; i < VALID_STATES.length; i++) {
        root.classList.remove('wpaic-chatbot--state-' + VALID_STATES[i]);
    }
    root.classList.add('wpaic-chatbot--state-' + newState);

    // Enable/disable input based on state
    var inputEnabled = (newState === 'idle' || newState === 'error');
    var inputEl = root.querySelector('.wpaic-chatbot__input');
    var sendBtn = root.querySelector('.wpaic-chatbot__btn-send');
    if (inputEl) {
        inputEl.disabled = !inputEnabled;
        inputEl.setAttribute('aria-disabled', String(!inputEnabled));
    }
    if (sendBtn) {
        sendBtn.disabled = !inputEnabled;
    }

    // Announce to screen readers
    announceState(instance);

    // Dispatch state change event
    dispatchWidgetEvent(root, 'wpaic:state-changed', {
        botSlug: instance.botSlug,
        oldState: oldState,
        newState: newState,
    });
}
```

### 5.5 Reference Comparison

| Concept | AxiaChat | MxChat | Baachal | WPAIC |
|---------|----------|--------|---------|-------|
| State tracking | `$root.data('aichatInFlight')` boolean (AXC line 1032) | `instance.isStreaming` boolean (MXC line 28) | `isLoading` boolean (BAA line 3) | `uiState` 6-value enum |
| Input disable method | jQuery `.prop('disabled', true)` (AXC line 346) | jQuery `.prop('disabled', true)` | jQuery `.prop('disabled', true)` (BAA line 266) | Native `el.disabled = true` |
| CSS state classes | None (AXC uses jQuery class adds) | None | None | `wpaic-chatbot--state-{state}` on root |
| Error state | Inline error message, no dedicated state | Inline error, no state | Inline error, no state (BAA line 198) | Dedicated `error` state with CSS class + retry |
| Offline detection | None | None | None | `navigator.onLine` + `online`/`offline` events |
| Consent blocking | GDPR overlay disables inputs (AXC lines 346-348) | None | None | Dedicated `disabled` state |
| In-flight guard | `$root.data('aichatInFlight')` check before send (AXC line 1032) | `isStreaming` check | `isLoading` check (BAA line 95) | `uiState !== 'idle'` (state machine prevents invalid transitions) |

Ref: AXC `aichat-frontend.js` lines 1032-1053 (`aichatInFlight` set, checked, and cleared in `.always()`), MXC `chat-script.js` lines 18-31 (instance properties including `isStreaming`), BAA `chatbot.js` lines 263-274 (`showLoading`/`hideLoading` toggle `isLoading` and jQuery `.prop('disabled')`).

---

## 6. Message State: DOM-Canonical Model

### 6.1 Design Decision

**Messages live in the DOM.** The DOM is the single source of truth for rendered message content. The instance registry stores lightweight metadata (message count, timestamps, streaming buffer) but does NOT maintain a parallel in-memory message array.

### 6.2 Rationale

| Approach | Pros | Cons |
|----------|------|------|
| In-memory array + DOM sync | Easier to serialize; testable state | Sync logic required; double memory usage; risk of DOM/state drift |
| DOM-canonical (selected) | No sync needed; lower memory; fewer bugs; simpler code | DOM queries needed for state inspection; harder to unit test message list |

All three source plugins use the DOM-canonical pattern:
- AXC: Builds HTML strings, appends via jQuery `.html()` / `.append()` (no message array)
- MXC: Appends to DOM via `getElement(botId, 'chat-box').append()` (no message array; `processedMessageIds` Set tracks IDs but not content)
- BAA: Appends via `$('#chatbot-messages').append(messageHtml)` (no message array)

WPAIC follows this established pattern. Messages are rendered as DOM elements with `data-wpaic-*` attributes carrying metadata:

```html
<div class="wpaic-chatbot__message wpaic-chatbot__message--user"
     data-wpaic-role="user"
     data-wpaic-timestamp="1709308800000">
  <div class="wpaic-chatbot__message-content">
    How do I reset my password?
  </div>
</div>

<div class="wpaic-chatbot__message wpaic-chatbot__message--assistant"
     data-wpaic-role="assistant"
     data-wpaic-timestamp="1709308802000">
  <div class="wpaic-chatbot__message-content">
    <!-- Sanitized HTML response -->
  </div>
</div>
```

### 6.3 Message Creation

Messages are created using `document.createElement` to prevent XSS. User content uses `textContent` (no HTML interpretation); bot responses use `innerHTML` after sanitization (see `input_output_safety.md`).

```javascript
function createMessageElement(role, content, timestamp) {
    var msg = document.createElement('div');
    msg.className = 'wpaic-chatbot__message wpaic-chatbot__message--' + role;
    msg.setAttribute('data-wpaic-role', role);
    msg.setAttribute('data-wpaic-timestamp', String(timestamp || Date.now()));

    var contentEl = document.createElement('div');
    contentEl.className = 'wpaic-chatbot__message-content';

    if (role === 'user') {
        contentEl.textContent = content; // Safe: no HTML interpretation
    } else {
        contentEl.innerHTML = sanitizeHtml(content); // See input_output_safety.md
    }

    msg.appendChild(contentEl);
    return msg;
}

function appendMessage(instance, role, content) {
    var messagesEl = instance.rootEl.querySelector('.wpaic-chatbot__messages');
    var msgEl = createMessageElement(role, content, Date.now());
    messagesEl.appendChild(msgEl);

    instance.messageCount++;
    instance.lastMessageTimestamp = Date.now();

    scrollToBottom(messagesEl);
    return msgEl;
}
```

### 6.4 Streaming Message Updates

During streaming, a temporary "shell" message element is created and progressively filled with text nodes. The final sanitized HTML is set once when the stream completes.

```javascript
function createStreamingMessage(instance) {
    var messagesEl = instance.rootEl.querySelector('.wpaic-chatbot__messages');
    var msg = document.createElement('div');
    msg.className = 'wpaic-chatbot__message wpaic-chatbot__message--assistant wpaic-chatbot__message--streaming';
    msg.setAttribute('data-wpaic-role', 'assistant');
    msg.setAttribute('data-wpaic-timestamp', String(Date.now()));

    var contentEl = document.createElement('div');
    contentEl.className = 'wpaic-chatbot__message-content';

    msg.appendChild(contentEl);
    messagesEl.appendChild(msg);
    scrollToBottom(messagesEl);

    instance.currentStreamEl = contentEl;
    instance.streamBuffer = '';
    return msg;
}

function appendStreamChunk(instance, chunk) {
    instance.streamBuffer += chunk;
    if (instance.currentStreamEl) {
        // Append text node -- avoids innerHTML replacement on each chunk
        instance.currentStreamEl.appendChild(document.createTextNode(chunk));
    }
}

function finalizeStreamingMessage(instance) {
    var msgEl = instance.currentStreamEl
        ? instance.currentStreamEl.parentElement
        : null;

    if (msgEl) {
        msgEl.classList.remove('wpaic-chatbot__message--streaming');
    }

    if (instance.currentStreamEl && instance.streamBuffer) {
        // Replace accumulated text nodes with sanitized final HTML
        // (enables proper markdown rendering + link sanitization)
        instance.currentStreamEl.innerHTML = sanitizeHtml(
            markdownToHtml(instance.streamBuffer)
        );
    }

    instance.messageCount++;
    instance.lastMessageTimestamp = Date.now();
    instance.streamBuffer = '';
    instance.currentStreamEl = null;
}
```

The progressive text-node append during streaming avoids `innerHTML` replacement on every chunk, which would cause layout thrashing and cursor position loss. The final HTML is set once on stream completion, enabling proper markdown rendering and HTML sanitization.

### 6.5 Metadata in Instance Registry

The registry tracks lightweight metadata that complements the DOM-canonical messages:

| Property | Type | Purpose |
|----------|------|---------|
| `messageCount` | number | Total messages for display count; avoids `querySelectorAll('.wpaic-chatbot__message').length` on each message |
| `lastMessageTimestamp` | number | Duplicate detection and rate-limit feedback UX |
| `streamBuffer` | string | Accumulated raw text during streaming; needed for final sanitized render |
| `currentStreamEl` | Element or null | Reference to the content element being progressively filled |

Ref: MXC `chat-script.js` lines 18-31 (instance tracks `isStreaming`, `chatHistoryLoaded`, `processedMessageIds` as metadata alongside DOM), BAA `chatbot.js` lines 213-249 (`addMessage` creates DOM elements with `data-type` and `data-timestamp` attributes).

---

## 7. Custom DOM Events

### 7.1 Event Design Principles

1. **Dispatched on widget root element** (not `document`), enabling per-widget event listening.
2. **All events bubble**, so listeners on `document` also receive them.
3. **`wpaic:` prefix namespace** prevents collision with other plugins/themes.
4. **`CustomEvent` API** with structured `detail` payload.
5. **Cancellable events** use standard `event.preventDefault()` (not custom flags).

### 7.2 Event Catalog

| Event Name | Dispatched When | Cancellable | `detail` Payload |
|------------|----------------|-------------|------------------|
| `wpaic:widget-ready` | Widget instance initialized, DOM ready, listeners bound | No | `{ botSlug, instanceId, config }` |
| `wpaic:session-created` | New session token received from server | No | `{ botSlug, sessionToken }` |
| `wpaic:message-sent` | User message submitted (before server request fires) | **Yes** | `{ botSlug, message, sessionToken, timestamp }` |
| `wpaic:response-received` | Complete bot response received (non-streaming or stream finalized) | No | `{ botSlug, message, usage, model, duration }` |
| `wpaic:stream-chunk` | Single SSE chunk received during streaming | No | `{ botSlug, chunk, accumulated }` |
| `wpaic:error` | Any error in the message pipeline (network, server, timeout) | No | `{ botSlug, code, message, recoverable }` |
| `wpaic:state-changed` | Widget UI state transition occurred | No | `{ botSlug, oldState, newState }` |

Note: The `wpaic:stream-chunk` event fires on every chunk during streaming. The `wpaic:response-received` event fires once at the end, whether the response was streaming or non-streaming.

### 7.3 Event Dispatch Implementation

```javascript
function dispatchWidgetEvent(rootEl, eventName, detail, cancellable) {
    var event = new CustomEvent(eventName, {
        bubbles: true,
        cancelable: !!cancellable,
        detail: detail || {},
    });
    return rootEl.dispatchEvent(event);
    // Returns false if a listener called preventDefault()
}
```

### 7.4 Cancellable Event: `wpaic:message-sent`

Only `wpaic:message-sent` is cancellable. Calling `event.preventDefault()` aborts the message send, enabling third-party content filtering:

```javascript
// In the send pipeline:
var allowed = dispatchWidgetEvent(instance.rootEl, 'wpaic:message-sent', {
    botSlug: instance.botSlug,
    message: userMessage,
    sessionToken: instance.sessionToken,
    timestamp: Date.now(),
}, true); // cancellable = true

if (!allowed) {
    // A listener called preventDefault() -- abort send
    return;
}
// ... proceed with fetch/stream
```

This replaces BAA's custom cancellation pattern (`beforeSendEvent.cancelled = true`, `chatbot.js` lines 101-109) with the standard DOM `preventDefault()` mechanism that all web developers already know.

### 7.5 Listener Examples

```javascript
// === Per-widget listener ===
var widget = document.querySelector('.wpaic-chatbot[data-wpaic-bot="support-agent"]');
widget.addEventListener('wpaic:response-received', function (e) {
    console.log('Bot said:', e.detail.message);
    console.log('Tokens used:', e.detail.usage);
});

// === Global listener (all widgets, via bubbling) ===
document.addEventListener('wpaic:error', function (e) {
    analytics.track('chatbot_error', {
        bot: e.detail.botSlug,
        code: e.detail.code,
    });
});

// === Cancel a message (content filtering) ===
document.addEventListener('wpaic:message-sent', function (e) {
    if (containsBannedWord(e.detail.message)) {
        e.preventDefault(); // Standard DOM API
        alert('Please rephrase your message.');
    }
});

// === Convenience API ===
WPAICChat.on('wpaic:widget-ready', function (e) {
    console.log('Bot ready:', e.detail.botSlug);
});
```

### 7.6 Reference Comparison

| Aspect | AxiaChat | MxChat | Baachal | WPAIC |
|--------|----------|--------|---------|-------|
| Event system | None | None | jQuery `$(document).trigger()` | Native `CustomEvent` on widget element |
| Event prefix | N/A | N/A | `baachal_` | `wpaic:` |
| Event target | N/A | N/A | `$(document)` (global scope) | Widget root element (scoped; bubbles to document) |
| Event count | 0 | 0 | 8 events | 7 events |
| Cancellation mechanism | N/A | N/A | `event.cancelled = true` (custom property) | `event.preventDefault()` (standard DOM) |
| Filter events | N/A | N/A | `$(document).triggerHandler()` for synchronous filters (BAA line 149) | `preventDefault()` on `wpaic:message-sent` |
| Payload access | N/A | N/A | `event.originalEvent.message` (jQuery event properties) | `event.detail.message` (standard CustomEvent) |

Ref: BAA `chatbot.js` lines 60-76 (`baachal_before_open`, `baachal_after_open`, `baachal_before_close`, `baachal_after_close`), lines 100-210 (`baachal_before_send_message` with cancellation, `baachal_response_received`, `baachal_response_error`, `baachal_filter_bot_response`, `baachal_filter_error_message`, `baachal_connection_error`), AXC -- no custom events, MXC -- no custom events.

---

## 8. `wp_localize_script` Configuration Shape (`wpaicConfig`)

### 8.1 Overview

PHP injects a `wpaicConfig` global via `wp_localize_script`. This object carries page-global configuration shared by all widget instances. Per-widget configuration is read from `data-wpaic-*` attributes at initialization time (see `widget_shortcode_contract.md` section 4).

### 8.2 PHP Registration

```php
wp_localize_script( 'wpaic-chatbot', 'wpaicConfig', array(
    // Endpoints
    'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
    'restUrl'          => esc_url_raw( rest_url( 'wp-ai-chatbot/v1/' ) ),
    'nonce'            => wp_create_nonce( 'wpaic_chat_nonce' ),
    'restNonce'        => wp_create_nonce( 'wp_rest' ),

    // Plugin metadata
    'pluginUrl'        => esc_url_raw( plugins_url( '', WPAIC_PLUGIN_FILE ) ),
    'version'          => WPAIC_VERSION,

    // Feature flags
    'streamingEnabled' => (bool) get_option( 'wpaic_runtime_streaming_enabled', true ),
    'debug'            => (bool) get_option( 'wpaic_admin_debug_mode', false ),

    // Internationalization
    'i18n'             => array(
        'send'              => __( 'Send', 'wp-ai-chatbot' ),
        'typing'            => __( 'Typing...', 'wp-ai-chatbot' ),
        'thinking'          => __( 'Thinking...', 'wp-ai-chatbot' ),
        'errorGeneric'      => __( 'Something went wrong. Please try again.', 'wp-ai-chatbot' ),
        'errorNetwork'      => __( 'Network error. Check your connection.', 'wp-ai-chatbot' ),
        'errorRateLimit'    => __( 'Too many messages. Please wait a moment.', 'wp-ai-chatbot' ),
        'errorOffline'      => __( 'You appear to be offline.', 'wp-ai-chatbot' ),
        'newConversation'   => __( 'New conversation', 'wp-ai-chatbot' ),
        'retry'             => __( 'Retry', 'wp-ai-chatbot' ),
        'close'             => __( 'Close', 'wp-ai-chatbot' ),
        'minimize'          => __( 'Minimize', 'wp-ai-chatbot' ),
        'openChat'          => __( 'Open chat', 'wp-ai-chatbot' ),
    ),

    // GDPR consent configuration
    'gdpr'             => array(
        'enabled'    => (bool) get_option( 'wpaic_privacy_gdpr_enabled', false ),
        'text'       => esc_html( get_option( 'wpaic_privacy_gdpr_text', '' ) ),
        'buttonText' => esc_html( get_option( 'wpaic_privacy_gdpr_button', __( 'I understand', 'wp-ai-chatbot' ) ) ),
        'cookieName' => 'wpaic_gdpr_consent',
    ),

    // Rate limiting feedback
    'rateLimitMessage' => esc_html( get_option(
        'wpaic_runtime_rate_limit_message',
        __( 'Please wait before sending another message.', 'wp-ai-chatbot' )
    ) ),

    // Page context
    'pageId'           => get_queried_object_id(),
) );
```

### 8.3 Config Schema

```
wpaicConfig: {
    ajaxUrl:          String    -- WordPress admin-ajax.php URL
    restUrl:          String    -- WP REST base (e.g., /wp-json/wp-ai-chatbot/v1/)
    nonce:            String    -- Nonce for AJAX endpoints (action: wpaic_chat_nonce)
    restNonce:        String    -- Nonce for REST API (action: wp_rest)
    pluginUrl:        String    -- Plugin base URL for asset paths
    version:          String    -- Plugin version (WPAIC_VERSION constant)
    streamingEnabled: Boolean   -- Global streaming feature flag
    debug:            Boolean   -- Enable console debug logging
    i18n: {                     -- Translatable UI strings
        send:             String
        typing:           String
        thinking:         String
        errorGeneric:     String
        errorNetwork:     String
        errorRateLimit:   String
        errorOffline:     String
        newConversation:  String
        retry:            String
        close:            String
        minimize:         String
        openChat:         String
    }
    gdpr: {                     -- GDPR consent gate configuration
        enabled:    Boolean
        text:       String
        buttonText: String
        cookieName: String
    }
    rateLimitMessage: String    -- User-facing rate limit message
    pageId:           Number    -- Current WordPress page/post ID (for page context injection)
}
```

### 8.4 Dual Nonce Strategy

Two nonces are provided because the plugin uses two endpoint mechanisms:

| Nonce Key | WP Nonce Action | Used For | Sent As |
|-----------|----------------|----------|---------|
| `nonce` | `wpaic_chat_nonce` | AJAX endpoints (`admin-ajax.php`) for chat message send and stream | `FormData` field `nonce` |
| `restNonce` | `wp_rest` | REST API endpoints for session management (create, resume, close, history) | `X-WP-Nonce` HTTP header |

The chat message endpoint uses AJAX (not REST API) because SSE streaming requires direct output control (`header()`, `ob_end_flush()`) that is incompatible with the REST API response pipeline.

### 8.5 Config Validation on JS Side

```javascript
function validateConfig() {
    if (typeof window.wpaicConfig === 'undefined') {
        console.error('[WPAIC] wpaicConfig not defined. wp_localize_script may have failed.');
        return false;
    }
    if (!wpaicConfig.ajaxUrl) {
        console.error('[WPAIC] wpaicConfig.ajaxUrl is missing.');
        return false;
    }
    if (!wpaicConfig.nonce) {
        console.error('[WPAIC] wpaicConfig.nonce is missing.');
        return false;
    }
    return true;
}
```

This mirrors AXC's early validation pattern (`aichat-frontend.js` lines 56-59: `if (typeof AIChatVars === 'undefined' || !AIChatVars || !AIChatVars.ajax_url)`) but without jQuery dependency.

### 8.6 Debug Mode

When `wpaicConfig.debug === true` or the URL contains `?wpaic_debug=1`, verbose console logging is enabled:

```javascript
var DEBUG = false;

function initDebug() {
    DEBUG = (config && config.debug) ||
        /[?&]wpaic_debug=1(&|$)/.test(window.location.search);
}

function log() {
    if (DEBUG) {
        console.log.apply(console,
            ['[WPAIC]'].concat(Array.prototype.slice.call(arguments)));
    }
}
```

Ref: AXC `aichat-frontend.js` line 42 (URL-based debug toggle: `/(?:\?|&)aichat_debug=1(?:&|$)/.test(window.location.search)`), BAA `baachal.php` line 344 (`debug_mode` option in localized data, checked at `chatbot.js` line 13).

### 8.7 Reference Comparison

| Config Key | AxiaChat (`AIChatVars`) | MxChat (`mxchatChat`) | Baachal (`baachal_ajax`) | WPAIC (`wpaicConfig`) |
|-----------|------------------------|----------------------|------------------------|-----------------------|
| AJAX URL | `ajax_url` | `ajax_url` | `ajax_url` | `ajaxUrl` (camelCase) |
| REST URL | N/A | N/A | N/A | `restUrl` |
| Nonce | `nonce` (single) | `nonce` (single) | `nonce` (single) | `nonce` + `restNonce` (dual) |
| Plugin URL | N/A | N/A | `plugin_url` | `pluginUrl` |
| Streaming flag | N/A (no streaming) | `enable_streaming_toggle` | N/A (no streaming) | `streamingEnabled` |
| Debug | URL param only | N/A | `debug_mode` | `debug` boolean + URL param |
| i18n | Separate `wp.i18n` | Flat keys mixed into config | N/A | `i18n` nested sub-object |
| GDPR | Separate global `AIChatGDPR` | N/A | N/A | `gdpr` nested sub-object |
| Page context | `page_id` | `contextual_awareness_toggle` | N/A | `pageId` |
| Config size | 3 keys | 40+ flat keys | 5 keys | 12 top-level keys (structured) |

Ref: AXC `class-aichat-core.php` lines 80-84 (`AIChatVars` with 3 keys), MXC `class-mxchat-integrator.php` lines 9297-9352 (`mxchatChat` with 40+ flat keys including colors, toggles, feature flags), BAA `baachal.php` lines 341-347 (`baachal_ajax` with 5 keys).

---

## 9. Initialization Lifecycle

### 9.1 Sequence Diagram

```
1. Browser loads <script src="assets/js/chatbot.js"> (in footer, deferred)
2. IIFE executes immediately:
   a. Defines private state (registry, config, helpers)
   b. Defines WPAICChat namespace on window
   c. Registers DOMContentLoaded listener (or calls init() if DOM already ready)
3. DOMContentLoaded fires --> init() called
4. validateConfig():
   - Check wpaicConfig exists and has ajaxUrl + nonce
   - If missing: log error, abort (widgets remain inert HTML)
5. initDebug():
   - Set DEBUG flag from wpaicConfig.debug or URL param
6. Find all widget roots:
   document.querySelectorAll('.wpaic-chatbot')
7. For each widget root element:
   a. Guard: skip if data-wpaic-initialized already set
   b. Set data-wpaic-initialized="1" on element
   c. Read data-wpaic-* attributes into config object (readDataAttributes)
   d. Resolve bot slug from data-wpaic-bot
   e. Guard: skip if no bot slug (log warning if DEBUG)
   f. createInstance(botSlug, rootEl, config)
   g. Read session token: getSessionToken(botSlug)
   h. Set initial uiState:
      - If GDPR enabled and no consent cookie: 'disabled'
      - Otherwise: 'idle'
   i. Bind DOM event listeners (bindWidgetEvents)
   j. If historyPersistence enabled AND sessionToken exists:
      - Fetch history from REST API: GET /sessions/{token}/history
      - Render history messages into messages container
   k. Else if startSentence configured:
      - Render welcome message as bot message
   l. Dispatch 'wpaic:widget-ready' on root element
8. Bind global listeners:
   - window 'offline' event --> set all instances to 'offline' state
   - window 'online' event --> restore all instances to 'idle' state
9. Initialization complete; WPAICChat API fully operational
```

### 9.2 Implementation

```javascript
function init() {
    if (!validateConfig()) return;

    config = wpaicConfig; // Store reference
    initDebug();

    var widgets = document.querySelectorAll('.wpaic-chatbot');
    if (!widgets.length) {
        if (DEBUG) log('No chat widgets found on page.');
        return;
    }

    for (var i = 0; i < widgets.length; i++) {
        initWidget(widgets[i]);
    }

    // Global connectivity monitoring
    window.addEventListener('offline', handleOffline);
    window.addEventListener('online', handleOnline);
}

function initWidget(rootEl) {
    // Double-init guard (AXC pattern: $root.data('aichatReady'))
    if (rootEl.hasAttribute('data-wpaic-initialized')) {
        if (DEBUG) log('Widget already initialized, skipping.');
        return;
    }
    rootEl.setAttribute('data-wpaic-initialized', '1');

    var widgetConfig = readDataAttributes(rootEl);
    var botSlug = widgetConfig.bot;

    if (!botSlug) {
        if (DEBUG) log('Widget missing data-wpaic-bot attribute.');
        return;
    }

    var instance = createInstance(botSlug, rootEl, widgetConfig);
    instance.sessionToken = getSessionToken(botSlug);

    // GDPR consent check
    if (config.gdpr && config.gdpr.enabled) {
        var consentCookie = getCookie(config.gdpr.cookieName);
        instance.gdprConsented = (consentCookie === '1');
        if (!instance.gdprConsented) {
            setState(instance, 'disabled');
            showGDPRBanner(instance);
        }
    }

    // Set idle if not already disabled
    if (instance.uiState !== 'disabled') {
        setState(instance, 'idle');
    }

    bindWidgetEvents(instance);

    // Load history or show welcome
    if (widgetConfig.historyPersistence && instance.sessionToken) {
        loadHistory(instance);
    } else if (widgetConfig.startSentence) {
        appendMessage(instance, 'assistant', widgetConfig.startSentence);
    }

    dispatchWidgetEvent(rootEl, 'wpaic:widget-ready', {
        botSlug: botSlug,
        instanceId: widgetConfig.instanceId,
        config: widgetConfig,
    });
}

// Entry point: wait for DOM or run immediately if already ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init(); // DOM already parsed (script loaded with defer or dynamically)
}
```

### 9.3 Data Attribute Reading

```javascript
function readDataAttributes(rootEl) {
    var ds = rootEl.dataset;
    return {
        bot:                ds.wpaicBot || '',
        instanceId:         rootEl.id || '',
        layout:             ds.wpaicLayout || 'floating',
        position:           ds.wpaicPosition || 'br',
        color:              ds.wpaicColor || '#0073aa',
        avatar:             ds.wpaicAvatar || '',
        title:              ds.wpaicTitle || '',
        placeholder:        ds.wpaicPlaceholder || '',
        startSentence:      ds.wpaicStartSentence || '',
        suggestionsEnabled: ds.wpaicSuggestions === '1',
        suggestionsCount:   parseInt(ds.wpaicSuggestionsCount, 10) || 3,
        maxLength:          parseInt(ds.wpaicMaxLength, 10) || 500,
        historyPersistence: ds.wpaicHistory !== '0',
    };
}
```

These data attributes are injected by PHP in the shortcode renderer (see `widget_shortcode_contract.md` section 4.1).

### 9.4 Event Binding

```javascript
function bindWidgetEvents(instance) {
    var root = instance.rootEl;
    var inputEl = root.querySelector('.wpaic-chatbot__input');
    var sendBtn = root.querySelector('.wpaic-chatbot__btn-send');

    // Toggle button is outside the widget container (floating mode)
    var toggleBtn = document.querySelector(
        '.wpaic-chatbot__toggle[aria-controls="' + root.id + '"]'
    );

    // Send on button click
    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            handleSend(instance);
        });
    }

    // Send on Enter; newline on Shift+Enter
    if (inputEl) {
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend(instance);
            }
        });

        // Auto-resize textarea
        inputEl.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Enable/disable send button based on input content
        inputEl.addEventListener('input', function () {
            if (sendBtn && instance.uiState === 'idle') {
                sendBtn.disabled = !this.value.trim();
            }
        });
    }

    // Toggle (floating mode)
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            handleToggle(instance, toggleBtn);
        });
    }

    // Escape to close (floating mode)
    if (instance.config.layout === 'floating') {
        root.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                handleClose(instance, toggleBtn);
            }
        });
    }
}
```

### 9.5 Reference Comparison

| Init Phase | AxiaChat | MxChat | Baachal | WPAIC |
|-----------|----------|--------|---------|-------|
| Entry point | `$(document).ready()` (jQuery) | `$(document).ready()` (jQuery) | `$(document).ready()` (jQuery) | `DOMContentLoaded` (native) |
| Config validation | Check `AIChatVars` exists (line 56) | Check `mxchatChat` exists (implicit) | Implicit (jQuery wraps all) | `validateConfig()` with error logging |
| Widget discovery | `$('.aichat-widget')` + legacy `$('#aichat-widget')` fallback (AXC lines 75-96) | `$('.mxchat-chatbot-wrapper').each()` (MXC lines 187-191) | `$('#ai-chatbot-widget')` (single, BAA line 23) | `document.querySelectorAll('.wpaic-chatbot')` |
| Double-init guard | `$root.data('aichatReady')` (AXC line 108) | `instances[botId]` existence check (MXC line 12) | None | `data-wpaic-initialized` attribute |
| Config reading | Data attributes on root element (AXC lines 117-136) | `mxchatChat` global + DOM data attributes (MXC lines 139-149) | `baachal_ajax` global (BAA line 13) | `data-wpaic-*` attributes + `wpaicConfig` global |
| Event binding | jQuery `.on()` inside `$instances.each()` (AXC lines 877-889) | jQuery event delegation (MXC line 200) | jQuery `$('#id').on()` (BAA lines 23-56) | Native `addEventListener` per-instance |
| History loading | AJAX `aichat_load_history` (AXC line 1754) | Per-bot AJAX (MXC implicit) | AJAX `baachal_get_chat_history` (BAA lines 436-468) | REST API `GET /sessions/{token}/history` |

---

## 10. Module Structure

### 10.1 File Layout

Per session 01 `directory_structure.md`, the frontend JavaScript lives in a single file:

```
assets/
  js/
    chatbot.js          -- Chat widget vanilla JS (streaming, multi-bot)
  css/
    chatbot.css         -- Chat widget stylesheet (BEM methodology)

admin/
  js/
    admin.js            -- Admin-only vanilla JS (settings, bot management)
  css/
    admin.css           -- Admin-only stylesheet
```

### 10.2 Why One File Per Context

The single-file approach is used because:

1. **No build tools**: There is no bundler to merge multiple source files. Multiple `<script>` tags would require careful load ordering and increase HTTP requests.
2. **Expected size**: The chat widget JS is estimated at 15-25KB minified, well within a single-file budget. All three source plugins use a single JS file for their frontend (AXC: `aichat-frontend.js` ~1800 lines; MXC: `chat-script.js` ~2500 lines; BAA: `chatbot.js` ~470 lines).
3. **Conditional loading**: The script is only enqueued when the `[wpaic_chatbot]` shortcode is present on the page (via `wp_enqueue_script` inside the shortcode callback). One file means one HTTP request per page that uses the chatbot.

### 10.3 Internal Organization of `chatbot.js`

The IIFE is organized into logical sections separated by comment headers:

```javascript
(function (window, document) {
    'use strict';

    // ====================================
    // SECTION 1: CONSTANTS & FEATURE DETECTION
    // ====================================
    // - STORAGE_PREFIX, COOKIE_PREFIX, COOKIE_MAX_AGE
    // - VALID_STATES array
    // - Feature checks: localStorage, CustomEvent, EventSource, AbortController

    // ====================================
    // SECTION 2: CONFIGURATION & VALIDATION
    // ====================================
    // - validateConfig(), initDebug()
    // - readDataAttributes()
    // - DEBUG flag, log() helper

    // ====================================
    // SECTION 3: INSTANCE REGISTRY
    // ====================================
    // - registry object
    // - createInstance(), getInstance(), getAllInstances(), destroyInstance()

    // ====================================
    // SECTION 4: SESSION TOKEN MANAGEMENT
    // ====================================
    // - getSessionToken(), setSessionToken(), clearSessionToken()
    // - getCookie(), setCookie()
    // - isValidUUID()
    // - resetSession()

    // ====================================
    // SECTION 5: UI STATE MACHINE
    // ====================================
    // - setState()
    // - State transition guards
    // - CSS class management
    // - announceState() (screen reader)

    // ====================================
    // SECTION 6: CUSTOM DOM EVENTS
    // ====================================
    // - dispatchWidgetEvent()

    // ====================================
    // SECTION 7: MESSAGE RENDERING
    // ====================================
    // - createMessageElement(), appendMessage()
    // - renderWelcomeMessage()
    // - scrollToBottom(), shouldAutoScroll()
    // - HTML sanitization: sanitizeHtml(), escapeHtml()
    // - Markdown conversion: markdownToHtml()

    // ====================================
    // SECTION 8: STREAMING
    // ====================================
    // - createStreamingMessage()
    // - appendStreamChunk()
    // - finalizeStreamingMessage()
    // - processStream() -- ReadableStream consumer
    // - parseSSEChunks() -- SSE line parser

    // ====================================
    // SECTION 9: MESSAGE SEND PIPELINE
    // ====================================
    // - handleSend() -- orchestrates the full send flow
    // - sendNonStreaming() -- fetch POST to AJAX endpoint, JSON response
    // - sendStreaming() -- fetch POST to AJAX endpoint, SSE response
    // - handleResponse() -- process successful response
    // - handleError() -- display error, manage retry state
    // - retryLastMessage()

    // ====================================
    // SECTION 10: HISTORY LOADING
    // ====================================
    // - loadHistory() -- REST API GET with X-WP-Nonce
    // - renderHistoryMessages()

    // ====================================
    // SECTION 11: WIDGET TOGGLE & LAYOUT
    // ====================================
    // - handleToggle(), handleClose(), handleOpen()
    // - Toggle button aria-expanded management
    // - Widget visual state persistence (sessionStorage per bot slug)

    // ====================================
    // SECTION 12: CONNECTIVITY MONITORING
    // ====================================
    // - handleOffline() -- set all instances to 'offline'
    // - handleOnline() -- restore all instances to 'idle'

    // ====================================
    // SECTION 13: GDPR CONSENT
    // ====================================
    // - checkGDPRConsent()
    // - showGDPRBanner()
    // - acceptGDPRConsent()

    // ====================================
    // SECTION 14: ACCESSIBILITY
    // ====================================
    // - setupFocusTrap()
    // - announceState() -- screen reader live region
    // - getScrollBehavior() -- respects prefers-reduced-motion

    // ====================================
    // SECTION 15: EVENT BINDING & INITIALIZATION
    // ====================================
    // - bindWidgetEvents()
    // - initWidget()
    // - init()

    // ====================================
    // SECTION 16: PUBLIC API EXPORT
    // ====================================
    // - window.WPAICChat definition
    // - Object.freeze()

    // DOMContentLoaded entry point
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
```

### 10.4 Admin JS Structure (`admin/js/admin.js`)

Follows the same IIFE pattern. Loaded only on plugin admin pages via `admin_enqueue_scripts` with page-slug gating. Separate namespace `window.WPAICAdmin`. No code shared with `chatbot.js` at runtime.

```
admin/js/admin.js internal sections:
  1. Configuration & validation (wpaicAdminData)
  2. Settings form management (save, validate, reset)
  3. Bot CRUD operations (create, update, delete, reorder)
  4. Provider testing (API key validation, connectivity)
  5. Usage dashboard (charts, export)
  6. Session management (list, delete, export)
  7. Public API export (window.WPAICAdmin)
```

### 10.5 File Size Budget

| File | Target (unminified) | Target (minified) | Target (gzipped) | Justification |
|------|--------------------|--------------------|-------------------|---------------|
| `assets/js/chatbot.js` | < 40KB | < 25KB | < 8KB | Comparable to AXC ~1800 lines + streaming support |
| `assets/css/chatbot.css` | < 15KB | < 10KB | < 3KB | BEM classes, no utility framework |
| `admin/js/admin.js` | < 25KB | < 15KB | < 5KB | Settings forms, CRUD, no heavy UI |
| `admin/css/admin.css` | < 12KB | < 8KB | < 3KB | Admin panel only |

### 10.6 Asset Registration Summary

| Handle | File | Dependencies | Context | Loaded When |
|--------|------|-------------|---------|-------------|
| `wpaic-chatbot` (JS) | `assets/js/chatbot.js` | `array()` (none) | Frontend | Shortcode present on page |
| `wpaic-chatbot` (CSS) | `assets/css/chatbot.css` | `array()` (none) | Frontend | Shortcode present on page |
| `wpaic-admin` (JS) | `admin/js/admin.js` | `array()` (none) | Admin | Plugin admin pages only |
| `wpaic-admin` (CSS) | `admin/css/admin.css` | `array()` (none) | Admin | Plugin admin pages only |

---

## 11. Source Comparison Table

### 11.1 Full Architecture Comparison

| Aspect | AxiaChat | MxChat | Baachal | WPAIC |
|--------|----------|--------|---------|-------|
| **JS entry pattern** | jQuery IIFE `(($) => {...})(jQuery)` (line 11) | jQuery `$(document).ready()` (line 1) | jQuery `$(document).ready()` (line 1) | Vanilla IIFE `(function(window, document){...})()` |
| **jQuery dependency** | Required (`['jquery', 'wp-i18n']`, core.php line 70) | Required (integrator.php line 9357) | Required (baachal.php line 350) | **None** (empty deps array) |
| **Build tools** | None | None | None | None |
| **Global namespace(s)** | `AIChatVars` + `AIChatGlobal` + `AIChatGDPR` (3) | `mxchatChat` (1 config) | `baachal_ajax` (1 config) | `wpaicConfig` (config) + `WPAICChat` (API) |
| **Namespace protection** | None | None | None | `Object.freeze` on API |
| **Instance registry** | No central registry; `$root.data()` per element | `MxChatInstances.instances` object (lines 7-79) | None (single instance, closure vars) | `registry` object in IIFE closure |
| **Registry key** | jQuery index from `.each()` | `botId` string | N/A | `botSlug` string |
| **Multi-instance** | Yes (`$('.aichat-widget').each()`, line 104) | Yes (`$('.mxchat-chatbot-wrapper').each()`, line 187) | No (single `$('#ai-chatbot-widget')`) | Yes (`querySelectorAll('.wpaic-chatbot')`) |
| **Double-init guard** | `$root.data('aichatReady')` (line 108) | `instances[botId]` check (line 12) | None | `data-wpaic-initialized` attribute |
| **Session primary storage** | Cookie `aichat_sid` (line 1788) | Cookie `mxchat_session_id_{botId}` (line 48) | localStorage `baachal_session_id` (line 6) | localStorage `wpaic_session_{bot_slug}` |
| **Session fallback** | None | None | None | Cookie `wpaic_sid_{bot_slug}` |
| **Session scoping** | Global (single cookie) | Per-bot | Global (single bot) | Per-bot |
| **Session TTL** | 30 days (line 1799) | 24 hours (line 61) | Until cleared (localStorage) | Until cleared (localStorage), 24h (cookie fallback) |
| **Token generation** | Client-side UUID/random (line 1797) | Client-side random (line 162) | Client-side UUID (line 8) | **Server-side** UUID v4 |
| **UI state model** | Boolean `aichatInFlight` (line 1032) | Boolean `isStreaming` (line 28) | Boolean `isLoading` (line 3) | 6-state enum with CSS classes |
| **Custom events** | None | None | 8 jQuery events on `$(document)` (lines 60-210) | 7 native CustomEvents on widget element |
| **Event cancellation** | N/A | N/A | `event.cancelled = true` (custom flag, line 108) | `event.preventDefault()` (standard DOM) |
| **Config injection** | 3 `wp_localize_script` objects | 1 object, 40+ flat keys (integrator line 9297) | 1 object, 5 keys (baachal.php line 341) | 1 structured object with nested sub-objects |
| **Streaming** | No (AJAX polling) | Yes (fetch + ReadableStream, lines 795-962) | No (AJAX only) | Yes (fetch + ReadableStream, consuming session 03 SSE) |
| **Message state** | DOM-canonical (jQuery `.append()`) | DOM-canonical (jQuery `.append()`) | DOM-canonical (jQuery `.append()`, line 248) | DOM-canonical (`document.createElement`) |
| **HTML sanitization** | `escapeHtml()` only | DOMParser + allowlist (lines 284-399) | DOMParser + allowlist (lines 284-400) | DOMParser + allowlist (see `input_output_safety.md`) |
| **GDPR consent** | Cookie-based GDPR overlay (lines 340-398) | None | None | `disabled` state + cookie consent |
| **Offline detection** | None | None | None | `navigator.onLine` + `online`/`offline` events |
| **In-flight guard** | `$root.data('aichatInFlight')` (line 1032) | `instance.isStreaming` (line 28) | `isLoading` (line 95) | State machine (`uiState !== 'idle'`) |
| **Widget toggle persistence** | `sessionStorage` per slug (line 1804) | Cookie/class-based | `isOpen` boolean (line 2) | `sessionStorage` per slug |
| **Keyboard handling** | jQuery `keypress` (lines 877-889) | jQuery `keypress` | jQuery `keypress` (line 51) | Native `keydown` (Enter, Shift+Enter, Escape) |
| **Auto-resize input** | jQuery `.on('input')` | N/A | jQuery `.on('input')` (line 430) | Native `addEventListener('input')` |
| **Focus management** | jQuery `.focus()` | N/A | jQuery `.focus()` (line 64) | Native `.focus()` + focus trap |
| **Accessibility** | `aria-live="polite"`, `aria-label` on some elements | N/A | N/A | Full ARIA: roles, live regions, focus trap, reduced motion |
| **Frontend JS file** | `aichat-frontend.js` (~1800 lines) | `chat-script.js` (~2500 lines) | `chatbot.js` (~470 lines) | `chatbot.js` (target: ~800-1000 lines) |

### 11.2 Pattern Adoption Summary

| WPAIC Feature | Primary Source | Secondary Source | Notes |
|---------------|---------------|------------------|-------|
| Instance registry | MXC `chat-script.js` lines 7-79 | AXC `aichat-frontend.js` lines 104-112 | MXC's explicit registry object adopted; AXC's per-element guard adapted |
| Per-bot session scoping | MXC `chat-script.js` lines 47-65 | BAA `chatbot.js` lines 6-9 | MXC's per-bot key pattern; BAA's localStorage preference |
| Custom DOM events | BAA `chatbot.js` lines 94-211 | AXC N/A, MXC N/A | BAA's event catalog adapted from jQuery to native CustomEvent |
| Config injection | AXC `class-aichat-core.php` lines 80-84 | MXC `class-mxchat-integrator.php` lines 9297-9357 | Structured object (not flat); dual nonce for AJAX + REST |
| Data attribute config | AXC `shortcode.php` lines 262-309 | MXC `class-mxchat-public.php` lines 164-249 | AXC's data-attr pattern with `wpaic-` namespace prefix |
| Streaming consumption | MXC `chat-script.js` lines 795-962 | Session 03 `streaming_transport_layer.md` | MXC's fetch+ReadableStream; normalized to session 03 SSE format |
| GDPR consent | AXC `aichat-frontend.js` lines 340-398 | BAA N/A, MXC N/A | AXC's cookie-based consent; integrated as `disabled` state |
| HTML sanitization | BAA `chatbot.js` lines 284-400 | MXC `chat-script.js` lines 284-399 | Both use DOMParser; unified in `input_output_safety.md` |
| Init lifecycle | AXC `aichat-frontend.js` lines 54-99 | MXC `chat-script.js` lines 185-202 | AXC's validation-first + MXC's per-bot init loop |
| History loading | BAA `chatbot.js` lines 436-468 | AXC `aichat-frontend.js` lines 1752-1780 | REST API instead of AJAX for history (alignment with session model) |
| Widget toggle state | AXC `aichat-frontend.js` lines 1804-1818 | BAA `chatbot.js` line 2 (`isOpen`) | AXC's `sessionStorage` per-slug pattern |

---

## 12. Cross-References

### 12.1 Session Dependencies

| Document | Relationship |
|----------|-------------|
| `session_model_contract.md` section 3.2 | Token format (UUID v4), `localStorage`/cookie key names, per-bot scoping strategy |
| `widget_shortcode_contract.md` section 4 | `data-wpaic-*` attribute definitions consumed by `readDataAttributes()` |
| `widget_shortcode_contract.md` section 9 | Asset registration, `wp_localize_script` call (note: object name reconciled to `wpaicConfig`) |
| `streaming_delivery_contract.md` section 5 | Frontend EventSource/fetch consumption, chunk format, connection lifecycle |
| `input_output_safety.md` | `sanitizeHtml()` and `escapeHtml()` implementation specifications |
| `message_lifecycle_pipeline.md` | Server-side pipeline that frontend communicates with via AJAX/REST |
| `endpoint_specifications.md` | Route definitions, request/response schemas for all frontend calls |
| Session 01 `directory_structure.md` | File locations (`assets/js/chatbot.js`, `admin/js/admin.js`) |
| Session 02 `wp_wpaic_sessions` schema | Session token format (UUID v4), session status enum, expiry policy |
| Session 03 `streaming_transport_layer.md` | SSE chunk format (`{type, content}` / `{type, done}`) consumed by streaming sections |

### 12.2 Localized Object Name Reconciliation

The `widget_shortcode_contract.md` (section 9.3) uses the name `wpaicChatData` for the localized script object. This document uses `wpaicConfig`. The recommended name for the final implementation is `wpaicConfig` because:
1. Follows the `wpaic` prefix convention from session 01 naming conventions.
2. Shorter and more ergonomic in JavaScript (`wpaicConfig.ajaxUrl` vs `wpaicChatData.ajaxUrl`).
3. The `Config` suffix is a standard pattern for localized data objects in WP plugins.

Phase 05 implementers should use `wpaicConfig` consistently and update `widget_shortcode_contract.md` section 9.3 accordingly.

---

## Appendix A: Admin JS Architecture (CFI-ADM-005)

The admin script follows the same IIFE/revealing module pattern, resolving CFI-ADM-005.

| Property | Value |
|----------|-------|
| File | `admin/js/admin.js` |
| Script handle | `wpaic-admin` |
| Dependencies | `array()` (none; no jQuery) |
| Loading | `admin_enqueue_scripts` hook, gated to `wpaic-*` admin page slugs |
| Config global | `wpaicAdminData` (via `wp_localize_script`) |
| API namespace | `window.WPAICAdmin` (frozen) |

Admin and frontend scripts never share state. They are loaded on mutually exclusive page sets (admin pages vs public pages). If shared utilities are needed during Phase 05 implementation, they will be extracted into `assets/js/shared.js` registered as a dependency of both scripts.

---

## Appendix B: Migration Notes from Reference Plugins

| Source Plugin | Key Migration Steps |
|--------------|---------------------|
| MxChat | `MxChatInstances` object becomes `registry` in IIFE closure. Cookie-only sessions gain localStorage primary. `jQuery(document).ready()` becomes `DOMContentLoaded`. `$('#id')` becomes `document.getElementById()`. `mxchatChat` global becomes `wpaicConfig`. |
| Baachal | `$(document).trigger('baachal_*')` events become `el.dispatchEvent(new CustomEvent('wpaic:*'))`. `$.Event().cancelled` flag becomes `event.preventDefault()`. `$.ajax()` becomes `fetch()`. `localStorage.getItem('baachal_session_id')` becomes per-bot `wpaic_session_{slug}`. |
| AxiaChat | jQuery IIFE `(($) => {...})(jQuery)` becomes vanilla `(function(window, document){...})()`. `$root.data('aichatReady')` becomes `data-wpaic-initialized` attribute. `AIChatVars`/`AIChatGlobal`/`AIChatGDPR` (3 globals) becomes single `wpaicConfig`. Per-instance data attributes map to `readDataAttributes()`. Client-side token generation replaced with server-side. |

---
