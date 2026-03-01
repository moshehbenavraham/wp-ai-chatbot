# Page Context Injection

**Session**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Task**: T014
**Resolves**: CFI-RUN-001 (MXC contextual awareness + BAA handler-replacement hook in AXI message pipeline)
**Module**: `includes/runtime/class-page-context.php` (PHP), `assets/js/wpaic-chat.js` (JS extraction)

---

## 1. Overview

This document specifies how the chatbot extracts page context from the current browsing page and injects it into the AI conversation, enabling context-aware responses. It resolves CFI-RUN-001 by combining two complementary patterns from the reference plugins:

- **MxChat client-side extraction**: `getPageContext()` (`mxchat-basic/js/chat-script.js` lines 208-311) extracts page URL, title, and main content text via CSS selector priority, clones the DOM to avoid mutation, strips unwanted elements, collects `data-mxchat-context` attributes, enforces a 3000-character limit with a 50-character minimum, and sends the result as `page_context` JSON in the AJAX request (lines 558-561 for sync, lines 827-828 for streaming FormData).
- **AxiaChat server-side context modes**: `class-aichat-ajax.php` lines 287-307 passes `page_id` from `AIChatVars.page_id` (set via `wp_localize_script` from `get_the_ID()`, see `aichat-embed-loader.js` line 208) and uses `context_mode` ('auto', 'none', 'page') to determine whether to call `aichat_get_context_for_question()` for server-side post content retrieval. The frontend sends `page_id` as an integer in the AJAX payload (`aichat-frontend.js` line 1060).
- **Baachal**: No page context injection. N/A.

The unified design uses MxChat's client-side extraction as the primary mechanism (works without server round-trips, captures dynamically rendered content) and extends it with AXI's `page_id` for server-side post lookup when the client-side extraction is insufficient or disabled. The `context_mode` per-bot setting controls which source is used.

### Reference Anchors

| Source Plugin | File | Lines | Relevance |
|---------------|------|-------|-----------|
| MxChat | `mxchat-basic/js/chat-script.js` | 208-311 | Primary: `getPageContext()` client-side extraction with CSS selector priority, clone-and-clean, data attributes, char limits |
| MxChat | `mxchat-basic/js/chat-script.js` | 540-561, 810-828 | `page_context` JSON serialized and sent in both AJAX and streaming FormData |
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 287-307 | Server-side `context_mode` ('auto', 'none', 'page'), `page_id` from frontend, `aichat_get_context_for_question()` |
| AxiaChat | `axiachat-ai/assets/js/aichat-frontend.js` | 1055-1060 | `page_id: AIChatVars.page_id` sent in AJAX payload |
| AxiaChat | `axiachat-ai/assets/js/aichat-embed-loader.js` | 205-208 | `AIChatVars.page_id` initialized via `wp_localize_script` |
| Baachal | N/A | N/A | No page context injection |

### Cross-References

| Document | Section | Relationship |
|----------|---------|-------------|
| `message_lifecycle_pipeline.md` | Section 4.2 (Step 2.2) | Pipeline injection point and `wpaic_inject_page_context` hook |
| `message_lifecycle_pipeline.md` | Section 4.4 (Step 2.4) | System prompt assembly consumes injected page context |
| `input_output_safety.md` | Section 2.1 | `page_context` field sanitization rules (`wp_kses` + JSON decode, 4096 max) |
| `streaming_delivery_contract.md` | Section 4.2 | `page_context` included in streaming FormData |
| `widget_shortcode_contract.md` | Section 4 | `data-wpaic-*` attribute namespace (context uses same namespace) |
| Session 02 `database_table_schemas.md` | `wp_wpaic_bots` table | `context_mode` ENUM column ('embeddings', 'page', 'none') |
| Session 02 `options_schema.md` | Runtime options | `wpaic_runtime_context_max_length` (default 6000), `wpaic_runtime_context_limit` |

---

## 2. Context Types

Page context is composed of five data fields, each with different collection mechanisms and inclusion rules.

### 2.1 Context Fields

| Field | Type | Source | Always Included | Size Contribution |
|-------|------|--------|-----------------|-------------------|
| `url` | string | `window.location.href` | Yes | Excluded from content char limit |
| `title` | string | `document.title` | Yes | Excluded from content char limit |
| `content` | string | CSS selector extraction (see Section 3) | Conditional (min 50 chars) | Subject to char limit |
| `custom` | string[] | `data-wpaic-context` attributes | If present | Appended to content, subject to char limit |
| `page_id` | int | `wpaicChatData.pageId` (from `get_the_ID()`) | Yes (if non-zero) | Excluded from content char limit |

### 2.2 Field Details

**url**: The full URL of the page where the chatbot widget is rendered. Always sent, even when content extraction fails. Useful for the AI to reference the page the user is on. Not subject to character limits.

**title**: The `document.title` value (typically the `<title>` tag content). Always sent. Not subject to character limits.

**content**: The main textual content of the page, extracted via CSS selector priority (Section 3). Subject to the configurable character limit (default 3000). If the extracted content is fewer than 50 characters after cleaning, the `content` field is set to an empty string (the context object is still sent with url/title/page_id, but content is omitted).

**custom**: An array of strings collected from `data-wpaic-context` attributes on any DOM element within the content area. These allow site authors to inject specific context strings that the AI should be aware of (e.g., product SKU, pricing tier, user segment). Custom context strings are concatenated and appended to the content field before the character limit is applied.

**page_id**: The WordPress post/page ID, injected server-side via `wp_localize_script` into `wpaicChatData.pageId`. Value is `0` for non-singular pages (archives, search, 404). Used by the backend for server-side post content lookup when client-side content is insufficient.

### 2.3 Context Modes

Controlled by `$bot['context_mode']` (from `wp_wpaic_bots.context_mode` column, Session 02 schema):

| Mode | DB Value | Client-Side Behavior | Server-Side Behavior |
|------|----------|---------------------|---------------------|
| Auto | `'page'` | Extract and send page context | Use client content; fall back to `page_id` post lookup if content is empty |
| Page Only | `'page'` | Extract and send page context | Use client content only (no server-side fallback) |
| Embeddings | `'embeddings'` | Extraction still runs (no round-trip to check mode) | Discard `page_context`; use RAG pipeline only (Step 2.3) |
| Disabled | `'none'` | Extraction still runs (no round-trip to check mode) | Discard `page_context`; no context injection of any kind |

**Note on 'auto' vs strict 'page'**: Both map to the DB value `'page'`. The initial implementation treats `'page'` as auto-mode: if client-side content is missing but `page_id` is non-zero, the server retrieves post content. This matches AXI's `aichat_get_context_for_question()` fallback behavior (lines 297-307).

Ref: AXI lines 287-307 (`context_mode` 'auto', 'none', 'page'); Session 02 `context_mode` ENUM.

---

## 3. Frontend Extraction (JavaScript)

### 3.1 Extraction Function

```javascript
/**
 * Extract page context for AI conversation awareness.
 *
 * @returns {Object|null} Context object or null if disabled/insufficient.
 */
WPAICChat.getPageContext = function() {
    // Check global disable via localized config
    if (!wpaicChatData.contextEnabled) {
        return null;
    }

    // Check per-page disable via data attribute
    if (document.querySelector('[data-wpaic-no-context="true"]')) {
        return null;
    }

    var url = window.location.href;
    var title = document.title || '';
    var pageId = wpaicChatData.pageId || 0;
    var maxLength = wpaicChatData.contextMaxLength || 3000;

    // Extract main content
    var content = extractMainContent(maxLength);

    // Build context object
    var context = {
        url: url,
        title: title,
        page_id: pageId,
    };

    // Only include content if it meets the minimum threshold
    if (content && content.length >= 50) {
        context.content = content;
    }

    return context;
};
```

### 3.2 CSS Selector Priority List

The content area is identified by trying each selector in order. The first match is used. If no selector matches, `document.body` is used as fallback.

```javascript
var CONTENT_SELECTORS = [
    'main',
    '[role="main"]',
    '.entry-content',
    '.post-content',
    '.page-content',
    '.main-content',
    '.content',
    'article',
    '#content',
    '#main',
];
```

**Rationale**: The order prioritizes semantic HTML5 elements (`main`, `[role="main"]`) over WordPress theme conventions (`.entry-content`, `.post-content`) over generic selectors (`.content`, `article`). This matches MXC's selector list (lines 224-235) with minor reordering to favor WordPress-specific classes higher in the priority chain.

**Configurability**: The selector list can be customized server-side via the `wpaic_page_context_selectors` filter and passed to the frontend via `wpaicChatData.contextSelectors`. If the localized config provides a custom array, it replaces the default list entirely.

Ref: MXC `getPageContext()` lines 224-235, selector priority list.

### 3.3 Unwanted Element Exclusion List

Elements matching these selectors are removed from the cloned content before text extraction:

```javascript
var UNWANTED_SELECTORS = [
    'header',
    'footer',
    'nav',
    '.navigation',
    '.sidebar',
    '.widget',
    '.menu',
    'script',
    'style',
    'noscript',
    'iframe',
    'svg',
    '.comments',
    '#comments',
    '.breadcrumb',
    '.breadcrumbs',
    '.wpaic-chatbot',
    '[class*="chat"]',
    '[id*="chat"]',
    '[data-wpaic-no-context]',
    '[aria-hidden="true"]',
    '.social-share',
    '.related-posts',
    'form',
];
```

**Additions over MXC**: `noscript`, `iframe`, `svg`, `[aria-hidden="true"]`, `.social-share`, `.related-posts`, `form`, and `[data-wpaic-no-context]` are added to prevent extraction of invisible content, non-content elements, form fields (potential PII), and explicitly excluded sections. The chatbot widget class is changed from `.mxchat`/`#floating-chatbot` to `.wpaic-chatbot` to match our namespace.

Ref: MXC `getPageContext()` lines 255-274, unwanted selectors.

### 3.4 Clone-and-Clean Approach

The extraction MUST NOT modify the live DOM. All operations are performed on a cloned subtree:

```javascript
function extractMainContent(maxLength) {
    // Find content element via selector priority
    var contentElement = null;
    for (var i = 0; i < CONTENT_SELECTORS.length; i++) {
        contentElement = document.querySelector(CONTENT_SELECTORS[i]);
        if (contentElement) {
            break;
        }
    }

    // Fallback to body
    if (!contentElement) {
        contentElement = document.body;
    }

    // Clone to avoid modifying live DOM
    var clone = contentElement.cloneNode(true);

    // Remove unwanted elements from clone
    for (var i = 0; i < UNWANTED_SELECTORS.length; i++) {
        var elements = clone.querySelectorAll(UNWANTED_SELECTORS[i]);
        for (var j = 0; j < elements.length; j++) {
            elements[j].remove();
        }
    }

    // Extract custom context data attributes
    var customContextParts = [];
    var contextElements = clone.querySelectorAll('[data-wpaic-context]');
    for (var i = 0; i < contextElements.length; i++) {
        var value = contextElements[i].getAttribute('data-wpaic-context');
        if (value && value.trim()) {
            customContextParts.push(value.trim());
        }
    }

    // Get text content
    var text = clone.textContent || '';

    // Append custom context after main text
    if (customContextParts.length > 0) {
        text += '\n\nAdditional Context:\n' + customContextParts.join('\n');
    }

    // Clean whitespace and enforce character limit
    text = text.replace(/\s+/g, ' ').trim();
    if (text.length > maxLength) {
        text = text.substring(0, maxLength);
    }

    return text;
}
```

**Key design decisions**:

- `cloneNode(true)` deep-clones the entire subtree. This is the same pattern MXC uses (line 252). The live DOM is never mutated by context extraction.
- `textContent` is used (not `innerText`) for consistency and performance. `textContent` includes text from hidden elements but avoids layout recalculation.
- Custom `data-wpaic-context` attributes are extracted from the cloned tree after unwanted element removal but before text extraction, matching MXC's `data-mxchat-context` pattern (lines 283-288).
- Whitespace normalization (`/\s+/g` to single space) ensures clean content regardless of source HTML formatting.
- The fallback to `document.body` (matching MXC line 247) ensures some content is always available, even on non-standard themes.

### 3.5 Character Limits

| Parameter | Default | Configurable Via | Min | Max |
|-----------|---------|-----------------|-----|-----|
| Content max length | 3000 | `wpaicChatData.contextMaxLength` | 100 | 10000 |
| Content min threshold | 50 | Not configurable | - | - |

The `contextMaxLength` value is injected via `wp_localize_script` from the bot's configuration or the global option. The default of 3000 matches MXC's hardcoded limit (line 299). The Session 02 options schema defines `wpaic_runtime_context_max_length` at 6000 for total context budget (including RAG); page context defaults to 3000, which is half the total budget.

**Minimum threshold**: If extracted content (after cleaning) is fewer than 50 characters, it is considered insufficient -- likely a page with minimal text content or a page where the content area selector matched a navigation-only element. In this case, the `content` field is omitted from the context object, but `url`, `title`, and `page_id` are still sent.

Ref: MXC lines 299 (3000 char limit) and 303-305 (50 char minimum check).

### 3.6 Custom Context via Data Attributes

Site administrators can annotate any HTML element with `data-wpaic-context` to inject specific context into the AI conversation:

```html
<!-- Product page example -->
<div class="product-info" data-wpaic-context="Product: Widget Pro, SKU: WP-001, Price: $49.99">
    ...
</div>

<!-- Pricing tier example -->
<section data-wpaic-context="User is viewing the Enterprise pricing plan">
    ...
</section>

<!-- Multiple attributes on different elements are all collected -->
<div data-wpaic-context="Free shipping on orders over $50">
    ...
</div>
```

**Rules**:
- Multiple `data-wpaic-context` attributes on different elements are collected and concatenated with newlines.
- Empty or whitespace-only values are skipped.
- Custom context is appended to the extracted text content BEFORE the character limit is applied, so it competes for space with page content. To ensure critical custom context is not truncated, keep individual attribute values concise.
- The attribute name `data-wpaic-context` uses our plugin prefix to avoid collisions with other plugins (MXC uses `data-mxchat-context`).

Ref: MXC `data-mxchat-context` pattern, lines 283-288.

### 3.7 DOM Event: `wpaic:context-extracted`

After context extraction completes, a custom DOM event is dispatched on the widget element:

```javascript
// After successful extraction, before AJAX/streaming request
instance.element.dispatchEvent(new CustomEvent('wpaic:context-extracted', {
    detail: {
        url: context.url,
        title: context.title,
        contentLength: (context.content || '').length,
        pageId: context.page_id,
        hasCustomContext: customContextParts.length > 0,
    },
    bubbles: true,
}));
```

**Event name**: `wpaic:context-extracted`

**Timing**: Dispatched once per message send, after extraction completes but before the AJAX/streaming request is sent.

**Bubbles**: Yes (propagates up from widget element to document).

**Purpose**: Allows third-party scripts to observe what context is being sent (for debugging, analytics, or UI indicators). The event detail intentionally includes only metadata (content length, not full content) to avoid leaking page content to unrelated event listeners.

---

## 4. Frontend-to-Backend Transport

### 4.1 Request Payload

The extracted context is serialized as JSON and included in the chat request.

**AJAX (sync)**:
```javascript
var ajaxData = {
    action: 'wpaic_chat',
    nonce: wpaicChatData.nonce,
    bot_slug: instance.bot.slug,
    message: message,
    session_token: instance.sessionToken,
};

var pageContext = WPAICChat.getPageContext();
if (pageContext) {
    ajaxData.page_context = JSON.stringify(pageContext);
}
```

**Streaming (FormData)**:
```javascript
var formData = new FormData();
formData.append('action', 'wpaic_chat_stream');
formData.append('nonce', wpaicChatData.nonce);
formData.append('bot_slug', instance.bot.slug);
formData.append('message', message);
formData.append('session_token', instance.sessionToken);

var pageContext = WPAICChat.getPageContext();
if (pageContext) {
    formData.append('page_context', JSON.stringify(pageContext));
}
```

Ref: MXC lines 558-561 (AJAX `ajaxData.page_context = JSON.stringify(pageContext)`) and lines 827-828 (streaming `formData.append('page_context', ...)`).

### 4.2 JSON Wire Format

```json
{
    "url": "https://example.com/products/widget-pro/",
    "title": "Widget Pro - Premium Widgets | Example Store",
    "content": "Widget Pro is our flagship product designed for enterprise teams. Features include real-time collaboration, advanced analytics, and custom integrations. Starting at $49.99/month. Additional Context: Product: Widget Pro, SKU: WP-001, Price: $49.99",
    "page_id": 42
}
```

### 4.3 Caching Strategy

Page context is extracted fresh on each message send (not cached across messages). This ensures dynamic content changes within a single page visit are captured.

MXC also re-extracts on every message (lines 540, 810: `getPageContext()` called inside each send function). The per-message extraction cost is minimal (DOM clone + text extraction < 5ms on typical pages).

---

## 5. Backend Injection

### 5.1 Injection Point

Page context injection occurs at **pipeline Stage 2, Step 2.2** -- after session resolution (Step 1.4) and history retrieval (Step 2.1), before RAG context retrieval (Step 2.3) and system prompt assembly (Step 2.4).

```
Stage 1: Pre-Processing (validation, session, security)
    |
    v
Stage 2: Context Assembly
    2.1 History retrieval
    2.2 PAGE CONTEXT INJECTION  <-- this step
    2.3 RAG context retrieval
    2.4 System prompt assembly
    2.5 Message array construction
    |
    v
Stage 3: Provider Dispatch
```

Ref: `message_lifecycle_pipeline.md` Section 4 (Stage 2).

### 5.2 Server-Side Deserialization and Validation

On the backend, the `page_context` field is sanitized and decoded:

```php
// Step 1: Sanitize raw input (strip all HTML tags)
$raw_context = wp_kses( wp_unslash( $_POST['page_context'] ?? '' ), array() );

// Step 2: Enforce size limit on raw string
if ( mb_strlen( $raw_context ) > 4096 ) {
    $raw_context = mb_substr( $raw_context, 0, 4096 );
}

// Step 3: JSON decode
$page_context = json_decode( $raw_context, true );
if ( ! is_array( $page_context ) ) {
    $page_context = null;
}

// Step 4: Validate and sanitize individual fields
if ( $page_context ) {
    $page_context = array(
        'url'     => esc_url_raw( $page_context['url'] ?? '' ),
        'title'   => sanitize_text_field( $page_context['title'] ?? '' ),
        'content' => sanitize_textarea_field( $page_context['content'] ?? '' ),
        'page_id' => absint( $page_context['page_id'] ?? 0 ),
    );

    // Re-enforce content length limit server-side
    $max_length = (int) ( $bot['context_max_length'] ?? 3000 );
    if ( mb_strlen( $page_context['content'] ) > $max_length ) {
        $page_context['content'] = mb_substr( $page_context['content'], 0, $max_length );
    }
}
```

Ref: `input_output_safety.md` Section 2.1 (`page_context` sanitization: `wp_kses` with empty allowlist, 4096 max).

### 5.3 Injection Logic

```php
/**
 * Build page context string for system prompt injection.
 *
 * Pipeline Stage 2, Step 2.2.
 *
 * @param array|null $page_context Sanitized page context from request.
 * @param array      $bot          Bot configuration row.
 * @param array      $session      Session row.
 * @return string|null Formatted context string for system prompt, or null.
 */
function wpaic_build_page_context( $page_context, $bot, $session ) {
    $context_mode = $bot['context_mode'] ?? 'page';

    // Mode: 'none' or 'embeddings' -- skip page context entirely
    if ( 'none' === $context_mode || 'embeddings' === $context_mode ) {
        return null;
    }

    if ( empty( $page_context ) || ! is_array( $page_context ) ) {
        return null;
    }

    $parts = array();

    // URL (always include if present)
    if ( ! empty( $page_context['url'] ) ) {
        $parts[] = 'Page URL: ' . $page_context['url'];
    }

    // Title (always include if present)
    if ( ! empty( $page_context['title'] ) ) {
        $parts[] = 'Page Title: ' . $page_context['title'];
    }

    // Content: client-side extraction or server-side fallback
    $content = $page_context['content'] ?? '';

    // Server-side fallback: if no client content but page_id is available
    if ( empty( $content ) && ! empty( $page_context['page_id'] ) ) {
        $content = wpaic_get_post_content_excerpt(
            $page_context['page_id'],
            (int) ( $bot['context_max_length'] ?? 3000 )
        );
    }

    if ( ! empty( $content ) ) {
        $parts[] = "Page Content:\n" . $content;
    }

    if ( empty( $parts ) ) {
        return null;
    }

    $formatted = implode( "\n", $parts );

    // Apply filter hook for extension
    $formatted = apply_filters( 'wpaic_inject_page_context', $formatted, $bot, $session );

    return $formatted;
}
```

### 5.4 Server-Side Post Content Fallback

When client-side extraction is unavailable (content below the 50-char threshold or extraction disabled on the frontend) but `page_id` is non-zero, the server retrieves post content directly:

```php
/**
 * Get a text excerpt from a WordPress post for context injection.
 *
 * Only returns content from published posts (security: prevents
 * information leakage through crafted page_id values).
 *
 * @param int $post_id    The WordPress post ID.
 * @param int $max_length Maximum character length.
 * @return string Text excerpt or empty string.
 */
function wpaic_get_post_content_excerpt( $post_id, $max_length = 3000 ) {
    $post = get_post( $post_id );

    if ( ! $post || 'publish' !== $post->post_status ) {
        return '';
    }

    // Strip shortcodes and HTML, get plain text
    $content = $post->post_content;
    $content = strip_shortcodes( $content );
    $content = wp_strip_all_tags( $content );
    $content = preg_replace( '/\s+/', ' ', $content );
    $content = trim( $content );

    if ( mb_strlen( $content ) > $max_length ) {
        $content = mb_substr( $content, 0, $max_length );
    }

    return $content;
}
```

**Security**: Only published posts are returned. Draft, private, password-protected, and trashed posts return empty string. This prevents information leakage through crafted `page_id` values in the AJAX payload.

Ref: AXI `aichat_get_context_for_question()` with `mode => 'page'` and `page_id` parameter (lines 297-307).

### 5.5 System Prompt Injection Format

The formatted page context is injected into the system prompt during Step 2.4 (system prompt assembly):

```php
// In system prompt assembly (message_lifecycle_pipeline.md Section 4.4)
if ( ! empty( $page_context_string ) ) {
    $system_prompt .= "\n\n--- Page Context ---\n" . $page_context_string;
}
```

**Example system prompt section**:

```
--- Page Context ---
Page URL: https://example.com/products/widget-pro/
Page Title: Widget Pro - Premium Widgets | Example Store
Page Content:
Widget Pro is our flagship product designed for enterprise teams.
Features include real-time collaboration, advanced analytics, and
custom integrations. Starting at $49.99/month.

Additional Context:
Product: Widget Pro, SKU: WP-001, Price: $49.99
```

The `--- Page Context ---` delimiter is a fixed string that:
1. Visually separates context from bot instructions in the system prompt.
2. Enables the AI to distinguish between instructions and reference material.
3. Can be used as a marker by the `wpaic_system_prompt` filter for context manipulation.

---

## 6. Opt-In / Opt-Out Controls

### 6.1 Global Enable (Per-Bot Setting)

| Setting | Storage | Default | Description |
|---------|---------|---------|-------------|
| `context_mode` | `wp_wpaic_bots.context_mode` column | `'page'` | Per-bot context mode: 'page' (enabled with auto-fallback), 'embeddings' (RAG only), 'none' (disabled) |

When `context_mode` is `'none'` or `'embeddings'`, the backend ignores any `page_context` payload sent by the frontend. The frontend still extracts and sends context (to avoid needing a server round-trip to determine the mode), but the backend discards it.

### 6.2 Per-Page Disable via Data Attribute

A page author or theme developer can disable context extraction for a specific page by adding `data-wpaic-no-context="true"` to any element on the page:

```html
<!-- On the widget element itself -->
<div class="wpaic-chatbot" data-wpaic-no-context="true" ...>

<!-- Or on any element in the document -->
<body data-wpaic-no-context="true">
```

The frontend checks for this attribute before extraction (Section 3.1). If found on any element in the document, context extraction is skipped entirely and `getPageContext()` returns `null`.

Additionally, elements with `data-wpaic-no-context` (without `="true"`) within the content area are removed during the clone-and-clean phase (Section 3.3), excluding their content from extraction while still allowing extraction of the rest of the page.

### 6.3 Per-Page Disable via Post Meta

Administrators can exclude specific pages via WordPress post meta:

```php
// If page has meta _wpaic_no_context = '1', skip extraction
$exclude = get_post_meta( get_the_ID(), '_wpaic_no_context', true );
```

When set, the `wpaicChatData.contextEnabled` flag is set to `false` for that page via `wp_localize_script`, preventing frontend extraction.

### 6.4 Shortcode Attribute Override

The `[wpaic_chatbot]` shortcode accepts a `context` attribute:

```
[wpaic_chatbot bot="support" context="off"]
```

| Value | Behavior |
|-------|----------|
| (omitted) | Context extraction enabled (default, subject to bot's `context_mode`) |
| `"on"` | Context extraction explicitly enabled |
| `"off"` | Context extraction disabled for this widget instance |

**Implementation**: When `context="off"`, the widget renderer adds `data-wpaic-no-context="true"` to the root widget element, which the frontend JavaScript detects:

```php
if ( 'off' === $atts['context'] ) {
    $data_attrs .= ' data-wpaic-no-context="true"';
}
```

### 6.5 JavaScript Runtime Disable

Third-party scripts can disable context extraction at runtime:

```javascript
// Disable for all instances globally
wpaicChatData.contextEnabled = false;
```

### 6.6 Control Priority

Controls are evaluated in this order (first match wins):

```
1. wpaicChatData.contextEnabled = false     --> frontend skips extraction (includes post meta disable)
2. data-wpaic-no-context="true" on page     --> frontend skips extraction
3. Shortcode context="off"                  --> renders data-wpaic-no-context, same as #2
4. Content < 50 chars after extraction      --> content field omitted (url/title/page_id still sent)
5. Bot context_mode = 'none'                --> backend discards page_context payload
6. Bot context_mode = 'embeddings'          --> backend discards page_context payload
7. wpaic_inject_page_context filter -> null --> backend skips injection for this request
8. Bot context_mode = 'page'                --> full extraction and injection proceeds
```

---

## 7. Privacy Considerations

### 7.1 Client-Side Extraction (No Server Crawling)

Page content is extracted entirely in the browser by the JavaScript running on the page the user is already viewing. The server does NOT crawl, fetch, or scrape any pages. This means:

- Only content the user can already see is captured.
- Content behind authentication gates is only captured if the user is authenticated on that page.
- JavaScript-rendered content (SPAs, AJAX-loaded sections) is captured as the user sees it at the moment of message send.
- The extraction happens at the moment the user sends a message, capturing the current page state.

### 7.2 Content NOT Stored in Database

Page context is used exclusively for the current AI request and is NOT persisted:

- The `page_context` string is injected into the system prompt for the provider API call.
- It is NOT stored in `wp_wpaic_messages.message` or `wp_wpaic_messages.response` or any other database column.
- It is NOT included in session history retrieval for subsequent messages (Step 2.1 retrieves only stored message/response pairs).
- It is NOT included in conversation exports via `lib/backup-manager.php`.
- Each message send extracts fresh context, so the AI always sees the current page state. Previous messages in the same session do NOT carry forward their page context.

### 7.3 Sensitive Content Exclusion Patterns

Elements matching the following patterns are excluded from extraction (via the unwanted selectors in Section 3.3):

| Selector | Purpose |
|----------|---------|
| `[data-wpaic-no-context]` | Explicit opt-out for any element |
| `.wpaic-chatbot` | The chatbot widget itself |
| `[class*="chat"]` | Other chat widgets |
| `form` | Form contents (may contain PII, passwords, payment fields) |
| `[aria-hidden="true"]` | Hidden accessibility content |
| `.comments`, `#comments` | User-generated comment content |
| `script`, `style`, `noscript` | Non-content elements |

Site administrators can mark any content as sensitive by adding `data-wpaic-no-context` to the element. The element and all its descendants are excluded from text extraction.

### 7.4 Content Sent to Third-Party AI Providers

The extracted page content IS sent to the configured AI provider (OpenAI, Anthropic, Google, etc.) as part of the system prompt. Site administrators should be aware that:

- Page content is transmitted to the provider's API endpoint over HTTPS.
- Provider data retention policies vary (consult each provider's terms of service).
- Sensitive pages should use the opt-out controls (Section 6) to prevent content from being sent.
- The `wpaic_inject_page_context` filter (Section 8.1) can be used to redact or transform content before it reaches the provider.

### 7.5 GDPR Considerations

If `wpaic_security_gdpr_enabled` option is true, the GDPR consent modal must be accepted before ANY data is sent, including page context. Page context extraction only runs after consent is granted (the chat widget is non-functional until consent).

---

## 8. Handler Hooks

### 8.1 PHP Filter: `wpaic_inject_page_context`

**Location**: Pipeline Stage 2, Step 2.2
**Priority**: Default (10)

```php
$page_context_string = apply_filters(
    'wpaic_inject_page_context',
    $page_context_string,   // string|null: formatted context or null
    $bot,                   // array: bot configuration row
    $session                // array: session row
);
```

**Arguments**:

| # | Name | Type | Description |
|---|------|------|-------------|
| 1 | `$page_context_string` | `string\|null` | The formatted page context string ready for system prompt injection, or null if context is disabled/unavailable |
| 2 | `$bot` | `array` | Bot configuration row from `wp_wpaic_bots` |
| 3 | `$session` | `array` | Session row from `wp_wpaic_sessions` |

**Return**: `string|null` -- Return the modified context string, or `null` to suppress injection for this request.

**Use cases**:
- Append additional server-side context (e.g., logged-in user's purchase history, WooCommerce cart)
- Redact sensitive patterns (e.g., strip email addresses, phone numbers)
- Replace client-side content with a server-side alternative
- Enforce per-bot context templates
- Conditionally suppress context for certain sessions or users

**Example: Redact email addresses**:
```php
add_filter( 'wpaic_inject_page_context', function( $context, $bot, $session ) {
    if ( null === $context ) {
        return $context;
    }
    // Remove email addresses from page context
    return preg_replace(
        '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
        '[email redacted]',
        $context
    );
}, 10, 3 );
```

**Example: Add WooCommerce cart context**:
```php
add_filter( 'wpaic_inject_page_context', function( $context, $bot, $session ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return $context;
    }
    $cart_total = WC()->cart->get_cart_contents_count();
    $cart_value = WC()->cart->get_cart_total();
    $context .= "\nUser's cart: {$cart_total} items, total {$cart_value}";
    return $context;
}, 10, 3 );
```

### 8.2 PHP Filters: Selector Customization

**Content selectors**: `wpaic_page_context_selectors`

```php
$selectors = apply_filters( 'wpaic_page_context_selectors', $default_selectors );
// Passed to frontend via wpaicChatData.contextSelectors
```

**Unwanted selectors**: `wpaic_page_context_unwanted`

```php
$unwanted = apply_filters( 'wpaic_page_context_unwanted', $default_unwanted );
// Passed to frontend via wpaicChatData.contextUnwanted
```

These filters run during `wp_localize_script` and their results are passed to the frontend as arrays. The JavaScript extraction uses the localized arrays if present, falling back to the hardcoded defaults.

### 8.3 JavaScript DOM Event: `wpaic:context-extracted`

**Dispatched on**: The widget root element (`instance.element`)

**Bubbles**: Yes

**Detail properties**:

| Property | Type | Description |
|----------|------|-------------|
| `url` | string | The page URL |
| `title` | string | The page title |
| `contentLength` | number | Length of extracted content (0 if below threshold) |
| `pageId` | number | WordPress post ID (0 for non-singular pages) |
| `hasCustomContext` | boolean | Whether `data-wpaic-context` attributes were found |

**Timing**: After context extraction, before the AJAX/streaming request is dispatched.

**Example: Debug logging**:
```javascript
document.addEventListener('wpaic:context-extracted', function(e) {
    console.log('[WPAIC] Page context:', e.detail.contentLength, 'chars from', e.detail.title);
});
```

**Example: Context indicator UI**:
```javascript
document.addEventListener('wpaic:context-extracted', function(e) {
    var indicator = document.querySelector('.my-context-indicator');
    if (indicator) {
        indicator.textContent = e.detail.contentLength > 0
            ? 'Context: ' + e.detail.contentLength + ' chars'
            : 'No page context';
    }
});
```

---

## 9. Size Limits and Validation

### 9.1 Limits Summary

| Boundary | Value | Enforced By | Configurable |
|----------|-------|-------------|-------------|
| Content extraction max | 3000 chars | Frontend JS | Yes: `wpaicChatData.contextMaxLength` |
| Content min threshold | 50 chars | Frontend JS | No |
| Raw `page_context` field max | 4096 bytes | Backend PHP | No (fixed safety limit) |
| Server-side content re-limit | `$bot['context_max_length']` or 3000 | Backend PHP | Yes: per-bot config |
| URL max length | 2083 chars | `esc_url_raw()` | No (URL standard) |
| Title max length | 512 chars | `sanitize_text_field()` | No |
| Custom context per-attribute | No explicit limit | Included in content char limit | N/A |
| `page_id` range | 0 to PHP_INT_MAX | `absint()` | No |

### 9.2 Validation Flow

```
Frontend JS                              Backend PHP
-----------                              -----------
1. Check contextEnabled flag
2. Check data-wpaic-no-context
3. Find content element via selectors
4. Clone element (deep clone)
5. Remove unwanted elements from clone
6. Collect data-wpaic-context attrs
7. Extract textContent
8. Append custom context strings
9. Normalize whitespace
10. Apply contextMaxLength limit
11. Check 50-char minimum
12. Build context object (url, title,
    content, page_id)
13. JSON.stringify() context object
14. Dispatch wpaic:context-extracted event
15. Append to AJAX/FormData payload
                                         16. wp_kses() strip HTML from raw string
                                         17. Enforce 4096-byte raw limit
                                         18. json_decode() to array
                                         19. Validate array structure
                                         20. Sanitize each field individually
                                         21. Re-enforce content length limit
                                         22. Check context_mode (discard if
                                             none/embeddings)
                                         23. Server-side fallback via page_id
                                             if content is empty
                                         24. Build formatted string
                                         25. Apply wpaic_inject_page_context
                                             filter
                                         26. Inject into system prompt
```

### 9.3 Error Handling

| Error Condition | Frontend Behavior | Backend Behavior |
|----------------|-------------------|------------------|
| No content element found | Uses `document.body` as fallback | N/A |
| Content below 50 chars | Omits `content` field; sends url/title/page_id | Uses server-side fallback if page_id > 0 |
| JSON parse failure on backend | N/A (JS produces valid JSON) | Sets `$page_context = null`; continues without context |
| Invalid field types | N/A | Type-cast and sanitize; invalid fields become empty |
| `page_id` references non-existent post | N/A | Server-side fallback returns empty string |
| `page_id` references non-published post | N/A | Server-side fallback returns empty string (security) |
| Content exceeds server-side limit | N/A | Truncated to `$bot['context_max_length']` |
| `wpaic_inject_page_context` returns null | N/A | Context injection skipped for this request |
| `wpaicChatData` not available | `getPageContext()` returns null | N/A |

No errors in page context extraction are surfaced to the user. Context is strictly best-effort: if extraction fails at any point, the conversation proceeds without page context. No error messages, no retry logic.

---

## 10. Configuration Injection via wp_localize_script

The frontend extraction parameters are injected server-side via `wp_localize_script` as part of the `wpaicChatData` object:

```php
$context_selectors = apply_filters( 'wpaic_page_context_selectors', array(
    'main', '[role="main"]', '.entry-content', '.post-content',
    '.page-content', '.main-content', '.content', 'article',
    '#content', '#main',
) );

$no_context = get_post_meta( get_the_ID(), '_wpaic_no_context', true );

wp_localize_script( 'wpaic-chat', 'wpaicChatData', array(
    // ... other fields ...
    'contextEnabled'   => (bool) ( 'none' !== $bot['context_mode'] ) && '1' !== $no_context,
    'contextMaxLength' => (int) ( $bot['context_max_length'] ?? 3000 ),
    'pageId'           => (int) get_the_ID(),
    'contextSelectors' => $context_selectors,
) );
```

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `contextEnabled` | boolean | `true` | Whether context extraction is enabled for this bot/page |
| `contextMaxLength` | integer | `3000` | Maximum characters of page content to extract |
| `pageId` | integer | `0` | WordPress post/page ID (0 for non-singular) |
| `contextSelectors` | string[] | (10-item default list) | CSS selector priority for content area detection |

Ref: AXI `AIChatVars.page_id` via `wp_localize_script` (`aichat-embed-loader.js` lines 205-208).

---

## 11. Source Comparison

| Aspect | MxChat | AxiaChat | Baachal | WPAIC (this spec) |
|--------|--------|----------|---------|-------------------|
| Extraction location | Client-side JS | Server-side (`page_id` lookup) | N/A | Client-side JS (MXC) + server-side fallback (AXI) |
| Selector priority | 10-item list | N/A (uses `page_id`) | N/A | 10-item configurable list |
| Content stripping | Unwanted elements removed | N/A | N/A | Unwanted + sensitive + custom exclusions |
| Custom markers | `data-mxchat-context` | N/A | N/A | `data-wpaic-context` |
| Exclusion markers | N/A | N/A | N/A | `data-wpaic-no-context` (new) |
| Character limit | 3000 (hardcoded) | N/A | N/A | 3000 (configurable via localized config + filter) |
| Minimum threshold | 50 chars | N/A | N/A | 50 chars |
| Context modes | Toggle on/off | 'auto', 'none', 'page' | N/A | 'page', 'embeddings', 'none' (per-bot) |
| `page_id` support | No | Yes (`AIChatVars.page_id`) | No | Yes (`wpaicChatData.pageId`) |
| Server-side fallback | No | Yes (`aichat_get_context_for_question`) | No | Yes (`wpaic_get_post_content_excerpt`) |
| Clone-and-clean | Yes (line 252) | N/A | N/A | Yes (same pattern) |
| Caching | Re-extracted per message | N/A | N/A | Re-extracted per message |
| Privacy controls | Toggle only | Mode toggle | N/A | Toggle + page meta + shortcode + element exclusion + filter |
| Extension hooks | None | None | None | `wpaic_inject_page_context` filter + `wpaic:context-extracted` event |
| Transport | JSON in AJAX/FormData | `page_id` int in AJAX | N/A | JSON in AJAX/FormData (MXC pattern) |

---

## 12. Testability Notes

### Unit Testable (JS)

- **Selector priority**: Given a mock DOM with various content containers, verify the correct element is selected in priority order.
- **Unwanted element removal**: Given a clone with header/footer/nav/form/chat widgets, verify they are stripped.
- **Character limit enforcement**: Given content exceeding the limit, verify truncation at exactly `maxLength`.
- **Minimum threshold**: Given content below 50 chars, verify `content` field is omitted from the returned object.
- **Custom data attributes**: Given elements with `data-wpaic-context`, verify collection and concatenation with newline separators.
- **Whitespace normalization**: Given multi-line content with irregular spacing, verify single-space output.
- **Disable controls**: Given `contextEnabled=false` or `data-wpaic-no-context="true"`, verify null return.
- **Clone integrity**: Verify original DOM is not modified after extraction.

### Unit Testable (PHP)

- **Context mode routing**: Given each `context_mode` value ('page', 'embeddings', 'none'), verify correct behavior (inject, discard, skip).
- **Server-side validation**: Given malformed JSON, oversized content, invalid `page_id`, verify sanitization and graceful handling.
- **System prompt formatting**: Given context fields, verify correct `--- Page Context ---` delimited format.
- **Post content fallback**: Given a published post ID, verify content extraction; given draft/private/trashed, verify empty return.
- **Filter hook**: Given a filter that modifies/nullifies context, verify pipeline respects the return value.
- **Field sanitization**: Given XSS payloads in url/title/content fields, verify all are sanitized.

### Integration Testable

- **Full flow**: JS extraction -> JSON serialize -> AJAX transport -> PHP deserialize -> system prompt injection -> provider receives context in system message.
- **Opt-out controls**: Shortcode `context="off"` -> widget renders `data-wpaic-no-context="true"` -> JS skips extraction -> backend receives no `page_context` field.
- **Server-side fallback**: Frontend sends empty content with valid `page_id` -> backend retrieves post content -> injects into system prompt.
- **Mode switching**: Change bot `context_mode` from 'page' to 'embeddings' -> verify page context is discarded and RAG context is used instead.

### Not Testable in Isolation

- Real-world CSS selector matching across diverse WordPress themes (requires live theme testing).
- Dynamic content extraction timing (SPA-like content loaded after initial page load).
- Provider-side utilization of injected context (depends on AI model behavior and prompt quality).
- Cross-browser `cloneNode(true)` fidelity for edge-case DOM structures.

---
