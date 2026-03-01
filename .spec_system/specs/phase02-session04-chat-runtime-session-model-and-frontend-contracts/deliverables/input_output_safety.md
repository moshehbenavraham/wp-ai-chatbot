# Input/Output Safety Contract

**Task**: T013 [S0204]
**Session**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Version**: 1.0
**Created**: 2026-03-01

---

## 1. Overview

This document specifies input sanitization, output rendering, and XSS prevention
rules for the chat runtime pipeline. It applies a defense-in-depth strategy with
three ordered principles:

1. **Sanitize on input** -- strip or reject dangerous content at the earliest
   possible point (PHP `$_POST` / `$_GET` receipt).
2. **Validate by rules** -- enforce type, format, length, and domain constraints
   before any business logic executes.
3. **Escape on output** -- apply allowlist filtering and contextual escaping
   immediately before content reaches the browser (PHP `wp_kses()` on the server,
   DOMParser allowlist on the client).

No single layer is assumed to be sufficient. Every user-supplied value passes
through at least two independent sanitization boundaries before it is rendered
in the DOM.

The design combines AxiaChat's server-side sanitization pipeline (per-field
sanitizers, `sanitize_answer_html()`, `maybe_render_markdown_answer()`) with
Baachal's client-side DOMParser allowlist approach (`sanitizeBotHtml()`) and
MxChat's `sanitizeUserInput()` / `escapeHtml()` fallback pattern.

### Reference Anchors

| Source Plugin | File | Lines | Relevance |
|---------------|------|-------|-----------|
| AxiaChat (AXI) | `axiachat-ai/includes/class-aichat-ajax.php` | 50-128 | Server-side input sanitization (nonce, session, message, bot_slug, tool_calls) |
| AxiaChat (AXI) | `axiachat-ai/includes/class-aichat-ajax.php` | 131-200 | Captcha, honeypot, rate limiting, length check, spam, moderation pipeline |
| AxiaChat (AXI) | `axiachat-ai/includes/sanitize-helpers.php` | 16-31 | `aichat_sanitize_session_id()` -- regex + length bound |
| AxiaChat (AXI) | `axiachat-ai/includes/class-aichat-ajax.php` | 2403-2446 | `maybe_render_markdown_answer()` -- conditional markdown-to-HTML |
| AxiaChat (AXI) | `axiachat-ai/includes/class-aichat-ajax.php` | 2553-2650 | `markdown_to_html_basic()` -- headings, links, images, lists, code |
| AxiaChat (AXI) | `axiachat-ai/includes/class-aichat-ajax.php` | 2682-2698 | `sanitize_answer_html()` -- `wp_kses()` allowlist + forced `rel="noopener"` |
| MxChat (MXC) | `mxchat-basic/js/chat-script.js` | 1820-1824 | `sanitizeUserInput()` via `textContent`/`innerHTML` trick |
| MxChat (MXC) | `mxchat-basic/js/chat-script.js` | 1826-1837 | `escapeHtml()` entity replacement |
| MxChat (MXC) | `mxchat-basic/js/chat-script.js` | 1268-1320 | `appendMessage()` -- user vs bot rendering branch |
| MxChat (MXC) | `mxchat-basic/js/chat-script.js` | 1593-1662 | `linkify()` -- markdown link conversion with `safeEncodeUrl()` |
| MxChat (MXC) | `mxchat-basic/includes/class-mxchat-integrator.php` | 152, 231, 420-421 | Server-side `sanitize_text_field()`, `wp_kses()` for transcripts |
| Baachal (BAA) | `baachal/assets/chatbot.js` | 284-400 | `sanitizeBotHtml()` -- DOMParser with tag/attribute allowlists, safe href pattern |
| Baachal (BAA) | `baachal/assets/chatbot.js` | 402-414 | `convertMarkdownLinks()`, `escapeHtml()` entity map |
| Baachal (BAA) | `baachal/baachal.php` | 486-512 | Server-side nonce, `sanitize_text_field()` on message and session_id |
| Baachal (BAA) | `baachal/baachal.php` | 2483-2550 | Settings `sanitize_callback` registry (`sanitize_text_field`, `sanitize_textarea_field`, `absint`) |

---

## 2. Input Sanitization (Server-Side PHP)

### 2.1 Per-Field Sanitizer Table

Every field from `$_POST` / `$_GET` is sanitized immediately on receipt, before
any business logic executes. No raw superglobal value ever reaches the runtime
pipeline.

| Field | Sanitizer | Validation | Default Max Length | Absolute Max |
|-------|-----------|------------|-------------------|--------------|
| `nonce` | `sanitize_text_field( wp_unslash() )` | Non-empty, `wp_verify_nonce()` | 64 | 64 |
| `message` | `sanitize_textarea_field( wp_unslash() )` | Non-empty after trim | 512 (configurable) | 4000 |
| `bot_slug` | `sanitize_title( wp_unslash() )` | Matches registered bot | 100 | 100 |
| `session_token` | Custom UUID regex (see 2.2) | UUID v4 format | 36 | 72 |
| `page_context` | `wp_kses( $val, array() )` then JSON decode | Valid JSON object | 4096 | 4096 |
| `page_id` | `absint()` | Positive integer | - | PHP_INT_MAX |
| `continue_tool` | `filter_var( ..., FILTER_VALIDATE_BOOLEAN )` | Boolean | - | - |
| `response_id` | `sanitize_text_field( wp_unslash() )` | Non-empty string | 64 | 64 |
| `tool_calls` | JSON decode then per-element normalization | Valid JSON array | 65535 | 65535 |
| `tool_calls[].call_id` | `sanitize_text_field()` | Non-empty string | 64 | 64 |
| `tool_calls[].name` | `sanitize_text_field()` | Non-empty string | 64 | 64 |
| `tool_calls[].args` | Cast to string, validate JSON | Valid JSON string | 65535 | 65535 |

Ref: AXI `class-aichat-ajax.php` lines 54-117 (nonce, message, bot_slug, session,
tool_calls normalization). BAA `baachal.php` lines 488-512 (nonce, message,
session_id via `sanitize_text_field()`). MXC `class-mxchat-integrator.php` line 152
(`sanitize_text_field()` on session_id).

### 2.2 Session Token UUID Validation

Session tokens use UUID v4 format. The validator accepts only hexadecimal
characters and hyphens, enforces the UUID v4 structure, and applies a length
bound to prevent abuse.

```php
function wpaic_validate_session_token( $token ) {
    if ( empty( $token ) ) {
        return ''; // Empty triggers new session creation
    }

    // Step 1: Strip non-alphanumeric-hyphen characters
    $token = preg_replace( '/[^a-z0-9\-]/i', '', (string) $token );

    // Step 2: Length bound (UUID v4 = 36 chars; accept up to 72 for legacy compat)
    if ( strlen( $token ) > 72 ) {
        return '';
    }

    // Step 3: Strict UUID v4 format validation
    if ( ! preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $token
    ) ) {
        return ''; // Invalid format triggers new session creation
    }

    return strtolower( $token );
}
```

Ref: AXI `sanitize-helpers.php` lines 16-31 (`aichat_sanitize_session_id()` --
regex strip + 72-char bound). BAA `baachal.php` line 607
(`sanitize_text_field()` on provided session_id). MXC
`class-mxchat-integrator.php` line 152 (`sanitize_text_field()` on session_id).

### 2.3 Message Length Limits

Message length is validated in two tiers:

| Tier | Source | Default | Range | Override |
|------|--------|---------|-------|----------|
| Configurable limit | Per-bot `input_max_length` option | 512 | 1-4000 | Admin UI or `wpaic_input_max_length` filter |
| Absolute hard cap | Server-side constant | 4000 | Fixed | Not overridable |

```php
// Step 1: WordPress sanitization
$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

// Step 2: Trim whitespace
$message = trim( $message );

// Step 3: Reject empty
if ( $message === '' ) {
    wp_send_json_error( array( 'message' => __( 'Message is empty.', 'wp-ai-chatbot' ) ), 400 );
}

// Step 4: Configurable length check (default 512, absolute cap 4000)
$configurable_max = (int) ( $bot['input_max_length'] ?? 512 );
$max_length       = min( max( $configurable_max, 1 ), 4000 );

if ( mb_strlen( $message, 'UTF-8' ) > $max_length ) {
    wp_send_json_error( array( 'message' => __( 'Message too long.', 'wp-ai-chatbot' ) ), 400 );
}
```

**What `sanitize_textarea_field()` does**:
- Strips NUL bytes
- Removes carriage returns (`\r`)
- Strips HTML tags via `wp_strip_all_tags()`
- Converts special characters to HTML entities
- Preserves newlines (unlike `sanitize_text_field()`)

Ref: AXI `class-aichat-ajax.php` lines 69, 159-164 (message sanitize +
4000 hard cap). BAA `baachal.php` line 496 (`sanitize_text_field()` on
message). MXC `js/chat-script.js` line 299 (client-side 3000-char context
trim).

### 2.4 Page Context JSON Validation

Page context arrives as a JSON string with URL, title, and content excerpt.
All HTML is stripped first, then the structure is validated and each field is
sanitized individually.

```php
$raw_context = wp_unslash( $_POST['page_context'] ?? '' );

// Step 1: Strip ALL HTML
$raw_context = wp_kses( $raw_context, array() );

// Step 2: Length cap
if ( mb_strlen( $raw_context, 'UTF-8' ) > 4096 ) {
    $raw_context = mb_substr( $raw_context, 0, 4096, 'UTF-8' );
}

// Step 3: JSON decode and validate structure
$context = json_decode( $raw_context, true );
if ( ! is_array( $context ) ) {
    $context = null;
}

// Step 4: Whitelist expected fields only
if ( $context ) {
    $context = array(
        'url'     => esc_url_raw( $context['url'] ?? '' ),
        'title'   => sanitize_text_field( mb_substr( $context['title'] ?? '', 0, 200, 'UTF-8' ) ),
        'content' => sanitize_textarea_field( mb_substr( $context['content'] ?? '', 0, 3000, 'UTF-8' ) ),
    );
}
```

Ref: MXC `js/chat-script.js` lines 280-311 (`getPageContext()` -- extracts URL,
title, content with 3000-char limit). AXI `class-aichat-ajax.php` lines 297-305
(`page_id` context mode using `absint()`).

---

## 3. Output Sanitization (Server-Side PHP)

### 3.1 AI Response Sanitization Pipeline

Before storing in the database and returning to the frontend, the AI response
passes through a multi-step sanitization pipeline:

```php
function wpaic_sanitize_response( $text ) {
    // Step 1: Extract suggestion markers (they use delimited syntax)
    $suggestions = wpaic_extract_suggestions( $text );
    $text        = wpaic_strip_suggestion_markers( $text );

    // Step 2: Optional markdown-to-HTML conversion
    if ( get_option( 'wpaic_runtime_markdown_enabled', true ) ) {
        $text = wpaic_markdown_to_html( $text );
    }

    // Step 3: HTML allowlist filtering via wp_kses()
    $text = wp_kses( $text, wpaic_get_allowed_html() );

    // Step 4: Link safety enforcement
    $text = wpaic_sanitize_links( $text );

    return $text;
}
```

Ref: AXI `class-aichat-ajax.php` lines 2403-2446
(`maybe_render_markdown_answer()` -- conditional conversion with Parsedown safe
mode or basic regex fallback). AXI `class-aichat-ajax.php` lines 2682-2698
(`sanitize_answer_html()` -- `wp_kses()` with allowlist). MXC
`class-mxchat-integrator.php` line 231 (`wp_kses()` on transcript content).

### 3.2 `wp_kses()` HTML Allowlist

The complete tag and attribute allowlist for AI response output:

```php
function wpaic_get_allowed_html() {
    return array(
        // Links
        'a'          => array(
            'href'   => true,
            'title'  => true,
            'target' => true,
            'rel'    => true,
            'class'  => true,
        ),
        // Inline formatting
        'strong'     => array(),
        'em'         => array(),
        'b'          => array(),
        'i'          => array(),
        'br'         => array(),
        'sup'        => array(),
        'sub'        => array(),
        'del'        => array(),
        // Block structure
        'p'          => array(),
        'blockquote' => array(),
        'hr'         => array(),
        // Lists
        'ul'         => array(),
        'ol'         => array(),
        'li'         => array(),
        // Code
        'pre'        => array(),
        'code'       => array( 'class' => true ),
        // Headings (H3-H6 only; H1/H2 reserved for page structure)
        'h3'         => array(),
        'h4'         => array(),
        'h5'         => array(),
        'h6'         => array(),
        // Tables
        'table'      => array(),
        'thead'      => array(),
        'tbody'      => array(),
        'tr'         => array(),
        'th'         => array(),
        'td'         => array(),
    );
}
```

**Explicitly disallowed** (never in allowlist):

| Category | Tags / Attributes | Reason |
|----------|------------------|--------|
| Script execution | `<script>`, `<noscript>` | Direct code execution |
| Style injection | `<style>`, `style` attribute | CSS-based exfiltration / UI redress |
| Embedded content | `<iframe>`, `<object>`, `<embed>`, `<applet>` | Third-party content injection |
| Forms | `<form>`, `<input>`, `<textarea>`, `<select>`, `<button>` | Phishing / data exfiltration |
| Media (from AI) | `<img>`, `<video>`, `<audio>`, `<source>`, `<picture>` | Image injection, tracking pixels |
| Event handlers | `onclick`, `onload`, `onerror`, `onmouseover`, etc. | DOM event-based XSS |
| Layout control | `<div>`, `<span>` | Complex DOM injection, UI spoofing |
| SVG / MathML | `<svg>`, `<math>` and children | Script execution via foreign content |

Ref: AXI `class-aichat-ajax.php` lines 2684-2693 (allowlist: a, strong, em, b,
i, br, p, ul, ol, li, code, pre, h1-h4, blockquote, span, img). BAA
`chatbot.js` lines 296-325 (client-side allowlist: DIV, P, SPAN, A, IMG, UL,
OL, LI, STRONG, EM, B, I, BR, H3-H6, SMALL, ARTICLE, SECTION, BUTTON, FIGURE,
FIGCAPTION, SUP, SUB, DEL, INS, BDI). Our unified allowlist is more restrictive
than either source, excluding `<img>`, `<div>`, `<span>`, and `<button>` from
server-side output.

### 3.3 Markdown-to-HTML Conversion Rules

When `wpaic_runtime_markdown_enabled` is true, the runtime converts common
markdown patterns. Conversion runs BEFORE `wp_kses()`, so any tag produced by
the converter that is not in the allowlist will be stripped.

| Markdown Pattern | HTML Output | Notes |
|------------------|-------------|-------|
| `**bold**` or `__bold__` | `<strong>bold</strong>` | |
| `*italic*` or `_italic_` | `<em>italic</em>` | |
| `` `inline code` `` | `<code>inline code</code>` | |
| ` ```lang\ncode\n``` ` | `<pre><code class="language-lang">code</code></pre>` | Language class sanitized via `sanitize_html_class()` |
| `# Heading` | Not converted | H1/H2 reserved for page structure |
| `### Heading` | `<h3>Heading</h3>` | H3-H6 only |
| `- item` or `* item` | `<ul><li>item</li></ul>` | Unordered lists |
| `1. item` | `<ol><li>item</li></ol>` | Ordered lists |
| `> quote` | `<blockquote>quote</blockquote>` | |
| `[text](url)` | `<a href="url" target="_blank" rel="noopener nofollow">text</a>` | URL validated via `esc_url()` |
| `![alt](url)` | `<img src="url" alt="alt" loading="lazy" />` | Stripped by `wp_kses()` since `<img>` not in allowlist |
| `---` | `<hr>` | Horizontal rule |
| `\| col \| col \|` | `<table>` structure | Basic pipe tables |

**Conversion boundary**: Markdown conversion happens ONCE on the server (pipeline
Stage 4.2), before `wp_kses()`. The client does NOT re-process markdown -- it
receives pre-rendered, allowlisted HTML.

Ref: AXI `class-aichat-ajax.php` lines 2553-2650 (`markdown_to_html_basic()` --
headings, images, links via `esc_url()`, inline code, bold, italic, lists). AXI
`class-aichat-ajax.php` lines 2403-2446 (`maybe_render_markdown_answer()` --
checks for existing HTML, tries Parsedown with safe mode, falls back to basic).
MXC `js/chat-script.js` lines 1593-1662 (`linkify()` -- client-side markdown
link conversion). BAA `chatbot.js` lines 402-403 (`convertMarkdownLinks()` --
simple `[text](url)` regex).

### 3.4 Link Safety (Server-Side)

All `<a>` tags in the sanitized response are post-processed to enforce safe URL
schemes and forced security attributes:

```php
function wpaic_sanitize_links( $html ) {
    $html = preg_replace_callback(
        '/<a\s[^>]*>/i',
        function( $matches ) {
            $tag = $matches[0];

            // Validate href scheme
            if ( preg_match( '/href=["\']([^"\']*)["\']/', $tag, $href_match ) ) {
                $url = $href_match[1];
                if ( ! preg_match( '/^(https?:|mailto:|tel:|\/|#)/i', $url ) ) {
                    return '<a href="#">'; // Neutralize unsafe href
                }
            }

            // Strip existing target and rel to prevent bypass
            $tag = preg_replace( '/target=["\'][^"\']*["\']/', '', $tag );
            $tag = preg_replace( '/rel=["\'][^"\']*["\']/', '', $tag );

            // Force security attributes
            $tag = str_replace( '>', ' target="_blank" rel="noopener noreferrer">', $tag );

            return $tag;
        },
        $html
    );
    return $html;
}
```

**Safe URL protocols**: `https:`, `http:`, `mailto:`, `tel:`, `/` (relative
paths), `#` (fragment anchors).

**Blocked protocols**: `javascript:`, `data:`, `vbscript:`, `blob:`, `file:`,
and any other scheme not in the safe list.

**Forced attributes**: Every `<a>` tag receives `target="_blank"` and
`rel="noopener noreferrer"` regardless of its original attributes.

Ref: AXI `class-aichat-ajax.php` lines 2694-2698 (`preg_replace` to inject
`rel="noopener"` on `target="_blank"` links, then `wp_kses()`). BAA
`chatbot.js` line 334 (`safeHrefPattern = /^(https?:|mailto:|tel:|\/|#)/i`).
MXC `js/chat-script.js` lines 1630-1653 (`safeEncodeUrl()` on href, forced
`target` attribute via `linkTarget` variable).

---

## 4. Frontend Output Rendering (Client-Side JavaScript)

### 4.1 User Message Escaping

User messages are ALWAYS rendered as plain text. No HTML parsing is applied to
user-authored content at any point in the rendering pipeline.

**Primary method** -- `textContent` assignment (MXC pattern):

```javascript
// MxChat pattern: sanitizeUserInput()
function sanitizeUserInput(text) {
    const div = document.createElement('div');
    div.textContent = text;         // Browser encodes all entities
    return div.innerHTML;           // Returns safe HTML-escaped string
}
```

**Fallback method** -- entity replacement (BAA/MXC pattern):

```javascript
// Baachal pattern: escapeHtml()
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    };
    return text.replace(/[&<>"']/g, (m) => map[m]);
}
```

**Rendering rule**: User messages use `textContent` assignment on the DOM
element. The `innerHTML` property is never set with user-authored content.

```javascript
// When displaying user messages:
messageEl.textContent = userMessage;  // Safe: browser treats as text, not HTML
```

Ref: MXC `js/chat-script.js` lines 1820-1824 (`sanitizeUserInput()` via
`textContent`/`innerHTML`). BAA `chatbot.js` lines 406-414 (`escapeHtml()` entity
map). MXC `js/chat-script.js` lines 1280-1285 (`appendMessage()` -- user branch
calls `sanitizeUserInput()`).

### 4.2 Bot Message Rendering Pipeline

Bot responses arrive as pre-sanitized HTML from the server. The client applies a
second independent sanitization layer before DOM insertion:

```
Server HTML response
    |
    v
[1] DOMParser.parseFromString()   -- parse into document
    |
    v
[2] sanitizeNode() recursion      -- allowlist tags + attributes
    |
    v
[3] Force link safety attributes  -- target="_blank" rel="noopener noreferrer"
    |
    v
[4] container.innerHTML           -- extract sanitized HTML string
    |
    v
[5] messageEl.innerHTML = result  -- insert into chat DOM
```

If DOMParser is unavailable (legacy environments), the entire response is
escaped via `escapeHtml()` and rendered as plain text.

```javascript
function sanitizeBotHtml(rawContent) {
    if (typeof DOMParser === 'undefined') {
        return escapeHtml(rawContent);  // Fallback: escape everything
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(
        '<div>' + rawContent + '</div>',
        'text/html'
    );

    if (doc.querySelector('parsererror')) {
        return escapeHtml(rawContent);  // Parse error: escape everything
    }

    const container = doc.body.firstElementChild;
    if (!container) {
        return '';
    }

    sanitizeNode(container);
    return container.innerHTML;
}
```

Ref: BAA `chatbot.js` lines 284-400 (`sanitizeBotHtml()` -- DOMParser with
fallback to `escapeHtml()`). MXC `js/chat-script.js` lines 1312-1320
(`appendMessage()` -- bot branch preserves HTML, user branch sanitizes).

### 4.3 DOMParser Tag and Attribute Allowlist

The client-side allowlist mirrors the server-side `wp_kses()` allowlist to
provide redundant defense:

```javascript
const ALLOWED_TAGS = new Set([
    // Links
    'A',
    // Inline formatting
    'STRONG', 'EM', 'B', 'I', 'BR', 'SUP', 'SUB', 'DEL',
    // Block structure
    'P', 'BLOCKQUOTE', 'HR',
    // Lists
    'UL', 'OL', 'LI',
    // Code
    'PRE', 'CODE',
    // Headings
    'H3', 'H4', 'H5', 'H6',
    // Tables
    'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD',
]);

const ALLOWED_ATTRS = {
    '*':    ['class'],
    'A':    ['href', 'target', 'rel', 'title'],
    'CODE': ['class'],
};

const SAFE_HREF = /^(https?:|mailto:|tel:|\/|#)/i;
```

### 4.4 Recursive Node Sanitization

Disallowed tags are unwrapped (their text content is preserved but the element
is removed). Disallowed attributes are stripped. Href values are validated
against the safe protocol pattern.

```javascript
function sanitizeNode(node) {
    const children = Array.from(node.children);
    for (const child of children) {
        // Remove disallowed tags (unwrap to preserve text)
        if (!ALLOWED_TAGS.has(child.tagName)) {
            const fragment = document.createDocumentFragment();
            while (child.firstChild) {
                fragment.appendChild(child.firstChild);
            }
            child.replaceWith(fragment);
            continue;
        }

        // Strip disallowed attributes
        const allowed = [
            ...(ALLOWED_ATTRS['*'] || []),
            ...(ALLOWED_ATTRS[child.tagName] || []),
        ];
        for (const attr of Array.from(child.attributes)) {
            if (!allowed.includes(attr.name.toLowerCase())) {
                child.removeAttribute(attr.name);
                continue;
            }
            // Validate href against safe protocol pattern
            if (attr.name.toLowerCase() === 'href') {
                if (!SAFE_HREF.test(attr.value.trim())) {
                    child.setAttribute('href', '#');
                }
            }
        }

        // Force link safety attributes
        if (child.tagName === 'A') {
            child.setAttribute('target', '_blank');
            child.setAttribute('rel', 'noopener noreferrer');
        }

        // Recurse into children
        sanitizeNode(child);
    }
}
```

Ref: BAA `chatbot.js` lines 337-399 (recursive traverse with tag check,
attribute check, href validation, forced target/rel on `<a>` tags). MXC
`js/chat-script.js` lines 1593-1662 (`linkify()` -- link conversion with
sanitized user input for link text via `sanitizeUserInput(text)`).

### 4.5 Safe Link Handling

All links rendered in the chat widget follow these rules regardless of source:

| Rule | Server (PHP) | Client (JS) |
|------|-------------|-------------|
| Protocol allowlist | `https:`, `http:`, `mailto:`, `tel:`, `/`, `#` | Same via `SAFE_HREF` regex |
| Blocked protocols | `javascript:`, `data:`, `vbscript:`, `blob:`, `file:` | Same (any non-matching protocol) |
| Unsafe href action | Replace with `#` | Replace with `#` |
| `target` attribute | Force `_blank` | Force `_blank` |
| `rel` attribute | Force `noopener noreferrer` | Force `noopener noreferrer` |
| Link text | Sanitized via `esc_html()` / `wp_kses()` | Preserved from DOMParser (tags stripped) |

Ref: AXI `class-aichat-ajax.php` lines 2594-2598 (link generation with
`esc_url()`, `target="_blank"`, `rel="noopener nofollow"`). BAA `chatbot.js`
lines 334, 357-387 (`safeHrefPattern` test, force target/rel). MXC
`js/chat-script.js` lines 1630-1653 (`safeEncodeUrl()`, `target="${linkTarget}"`).

---

## 5. XSS Prevention Matrix

### 5.1 Stored XSS

| Attack Vector | Protection Layer 1 | Protection Layer 2 |
|--------------|-------------------|-------------------|
| Malicious user message saved to DB | `sanitize_textarea_field()` strips all HTML tags before storage | Output re-sanitized via `wp_kses()` on retrieval |
| Malicious AI response saved to DB | `wp_kses()` allowlist strips disallowed tags/attributes before storage | Client DOMParser allowlist re-sanitizes on render |
| Script in session metadata | `sanitize_text_field()` on all metadata fields | No HTML rendering of metadata |

Ref: AXI `class-aichat-ajax.php` line 69 (`sanitize_textarea_field()` on message
input). AXI lines 2682-2698 (`wp_kses()` on AI response). BAA `chatbot.js` lines
284-400 (client-side DOMParser re-sanitization).

### 5.2 Reflected XSS

| Attack Vector | Protection Layer 1 | Protection Layer 2 |
|--------------|-------------------|-------------------|
| Crafted URL with payload in query params | WordPress nonce verification rejects cross-site requests | No query param values rendered in DOM |
| Manipulated AJAX response | AJAX endpoints use `wp_send_json_success/error()` (JSON-safe) | Client parses JSON, never injects raw response as HTML |

Ref: AXI `class-aichat-ajax.php` lines 54-57 (nonce verify + 403 rejection).
BAA `baachal.php` lines 488-496 (nonce verify + `sanitize_text_field()`). MXC
`class-mxchat-integrator.php` lines 891-894 (nonce verify).

### 5.3 DOM-based XSS

| Attack Vector | Protection Layer 1 | Protection Layer 2 |
|--------------|-------------------|-------------------|
| `innerHTML` with unsanitized content | DOMParser allowlist strips disallowed elements before `innerHTML` assignment | `textContent` used for all user-authored and error content |
| `javascript:` in href | Server-side protocol check blocks unsafe schemes | Client-side `SAFE_HREF` regex blocks unsafe schemes |
| Event handler attributes | Server `wp_kses()` strips all event attributes | Client `sanitizeNode()` strips all non-allowlisted attributes |
| Template literal injection | No template literals with user data in HTML context | All user data rendered via `textContent` |

Ref: BAA `chatbot.js` lines 284-400 (DOMParser sanitization with allowlist, no
raw `innerHTML` with untrusted content). MXC `js/chat-script.js` lines 1820-1824
(`sanitizeUserInput()` via `textContent`). AXI `class-aichat-ajax.php` lines
2682-2698 (`wp_kses()` strips all event handlers).

---

## 6. Content Security Policy Recommendations

The plugin cannot set CSP headers directly (other plugins and themes may
conflict), but admin documentation should recommend the following CSP
directives:

### 6.1 Recommended CSP Header

```
Content-Security-Policy:
    default-src 'self';
    script-src 'self';
    style-src 'self' 'unsafe-inline';
    connect-src 'self';
    img-src 'self' data: https:;
    font-src 'self';
    frame-src 'none';
    object-src 'none';
    base-uri 'self';
    form-action 'self';
```

### 6.2 Directive Rationale

| Directive | Value | Reason |
|-----------|-------|--------|
| `script-src` | `'self'` | Prevents inline scripts and external script injection |
| `style-src` | `'self' 'unsafe-inline'` | WordPress admin and many themes require inline styles |
| `connect-src` | `'self'` | Chat AJAX/SSE only connects to same origin |
| `img-src` | `'self' data: https:` | Allow site images and HTTPS external images |
| `frame-src` | `'none'` | No iframes needed in chat widget |
| `object-src` | `'none'` | No plugin/embed content needed |
| `base-uri` | `'self'` | Prevents base tag hijacking |
| `form-action` | `'self'` | Prevents form submission to external targets |

### 6.3 Implementation Approach

The plugin provides a filterable CSP header function that site administrators
can enable:

```php
// Off by default; opt-in via filter
add_filter( 'wpaic_csp_enabled', '__return_true' );

// Customizable directives via filter
add_filter( 'wpaic_csp_directives', function( $directives ) {
    $directives['connect-src'] = "'self' https://api.example.com";
    return $directives;
} );
```

The plugin should also add a `<meta>` CSP tag for environments where HTTP
headers cannot be modified, but only when explicitly enabled by the site admin.

Ref: AXI uses nonce-based AJAX (same-origin policy enforcement). BAA uses nonce
AJAX with `sanitize_text_field()`. MXC uses nonce AJAX with session management.
All three plugins operate within same-origin constraints.

---

## 7. OWASP Coverage Checklist

Assessment against OWASP Top 10 Web Application Security Risks (2021) and
OWASP XSS Prevention Cheat Sheet:

### 7.1 OWASP Top 10 Coverage

| # | Risk | Status | Protection |
|---|------|--------|-----------|
| A01 | Broken Access Control | COVERED | Nonce verification on all endpoints; admin-only routes require `manage_options` capability; session tokens are per-client, non-guessable UUID v4 |
| A02 | Cryptographic Failures | COVERED | API keys stored in encrypted WP options, never in session data; session tokens use `wp_generate_uuid4()`; no sensitive data in client-side storage |
| A03 | Injection (XSS/SQLi) | COVERED | Input sanitization (section 2), output allowlisting (section 3), client DOMParser (section 4), prepared statements for all DB queries |
| A04 | Insecure Design | COVERED | Defense-in-depth with redundant sanitization layers; principle of least privilege in allowlists; fail-secure defaults (escape on parse error) |
| A05 | Security Misconfiguration | PARTIAL | CSP recommendations provided (section 6); debug mode gated on capability check; error messages stripped of internals (section 8) |
| A06 | Vulnerable Components | OUT OF SCOPE | Dependency scanning handled by CI security workflow (`.github/workflows/security.yml`) |
| A07 | Auth Failures | COVERED | WordPress nonce system for CSRF; rate limiting per session/IP; honeypot and captcha hooks |
| A08 | Data Integrity Failures | COVERED | All input validated before processing; JSON payloads decoded and structure-validated; tool_calls normalized per-element |
| A09 | Logging Failures | OUT OF SCOPE | Handled by structured logger (`lib/structured-logger.php`); moderation blocks logged |
| A10 | SSRF | COVERED | AI provider URLs configured admin-side only; user input never used to construct outbound request URLs |

### 7.2 OWASP XSS Prevention Rules

| Rule | Requirement | Implementation |
|------|------------|----------------|
| Rule 0 | Never insert untrusted data except in allowed locations | All user data passes through sanitization before any use |
| Rule 1 | HTML-encode before inserting into HTML element content | `wp_kses()` server-side; `textContent`/`escapeHtml()` client-side |
| Rule 2 | Attribute-encode before inserting into HTML attributes | `esc_attr()` server-side; attribute allowlist client-side |
| Rule 3 | JavaScript-encode before inserting into JS data values | `wp_json_encode()` for all data passed to frontend; `wp_localize_script()` for config |
| Rule 4 | CSS-encode before inserting into CSS property values | `style` attribute not in allowlist; no dynamic CSS from user data |
| Rule 5 | URL-encode before inserting into URL parameter values | `esc_url()` / `esc_url_raw()` server-side; `SAFE_HREF` regex client-side |
| Rule 6 | Sanitize HTML markup with a well-designed allowlist | `wp_kses()` with explicit allowlist (section 3.2); DOMParser allowlist (section 4.3) |
| Rule 7 | Prevent DOM-based XSS | No `eval()`, no `innerHTML` with unsanitized content; `textContent` for user data; DOMParser for bot HTML |

### 7.3 Additional OWASP Considerations

| Consideration | Status | Notes |
|--------------|--------|-------|
| HTTP-only cookies | N/A | Plugin uses WP nonces, not custom cookies for auth |
| Same-site cookie attribute | DELEGATED | WordPress handles cookie policy |
| X-Content-Type-Options | RECOMMENDED | `nosniff` header recommended in CSP section |
| X-Frame-Options | RECOMMENDED | `DENY` or `SAMEORIGIN` recommended |
| Referrer-Policy | RECOMMENDED | `strict-origin-when-cross-origin` recommended |
| Trusted Types | FUTURE | Not yet widely supported; plugin architecture is compatible (no `innerHTML` with raw strings) |

---

## 8. Streaming Safety

During streaming, content arrives chunk-by-chunk. Special considerations apply
to prevent multi-chunk injection attacks.

### 8.1 Chunk Content Safety

- Each SSE chunk is a JSON-encoded string from the trusted provider pipeline
- Content chunks contain text deltas, NOT HTML
- The frontend accumulates text deltas and renders via `textContent` during
  streaming
- Full HTML sanitization applies only at stream finalization

### 8.2 Progressive Rendering Safety

```javascript
// During streaming: safe text rendering (no HTML parsing)
tempMessage.textContent = accumulatedContent;

// At stream finalization: apply full HTML sanitization pipeline
if (WPAICChat.config.markdownEnabled) {
    const sanitized = WPAICChat.sanitizeBotHtml(accumulatedContent);
    tempMessage.innerHTML = sanitized;
} else {
    tempMessage.textContent = accumulatedContent;
}
```

This ensures partial content is never rendered as HTML mid-stream, preventing
injection via carefully crafted multi-chunk payloads where `<scr` arrives in
one chunk and `ipt>` arrives in the next.

Ref: MXC `js/chat-script.js` lines 408-415 (streaming detection and temporary
message rendering). The unified plugin uses `textContent` during streaming
(safer than MXC's approach) and applies DOMParser sanitization at finalization
(BAA pattern).

---

## 9. Error Message Safety

### 9.1 Error Information Boundaries

Error messages exposed to users MUST NOT contain:
- Stack traces or exception messages
- File paths or line numbers
- Database queries, table names, or column names
- API keys, tokens, or credentials
- Internal error codes (use user-friendly codes like `wpaic_provider_error`)
- Provider-specific error details (model names, request IDs)

### 9.2 Error Escaping

```javascript
// Error messages displayed in chat are always escaped via textContent
errorEl.textContent = error.user_message || wpaicChatData.i18n.errorGeneric;
// NEVER: errorEl.innerHTML = error.message
```

### 9.3 Debug Mode

When `wpaic_admin_debug_enabled` is true AND the current user has
`manage_options` capability:
- Additional debug info may be included in the response `debug` field
- Debug info is rendered in a separate admin-only debug panel, not in the chat
- Debug panel uses `textContent` assignment (never `innerHTML`)

---

## 10. Data Storage Safety

### 10.1 What is Stored

| Data | Storage Column | Sanitized By |
|------|---------------|-------------|
| User message | `wp_wpaic_messages.message` | `sanitize_textarea_field()` |
| AI response | `wp_wpaic_messages.response` | `wp_kses()` with allowlist + link safety |
| RAG context | `wp_wpaic_messages.rag_context` | `wp_json_encode()` (JSON, not raw HTML) |
| Session token | `wp_wpaic_sessions.session_token` | UUID regex validation |
| IP address | `wp_wpaic_sessions.ip_address` | `inet_pton()` (binary, not string) |
| User-agent hash | `wp_wpaic_sessions.user_agent_hash` | `hash('sha256', ...)` (one-way hash) |
| Bot slug | `wp_wpaic_messages.bot_slug` | `sanitize_title()` |

### 10.2 What is NOT Stored

- Raw `$_POST` / `$_GET` / `$_SERVER` values
- API keys or provider credentials (stored in encrypted WP options, not per-session)
- Full page content (only extracted excerpt, max 3000 chars after sanitization)
- User passwords or authentication tokens

Ref: AXI `class-aichat-ajax.php` lines 2704-2751 (`build_history_messages()` --
retrieves stored messages with `wp_strip_all_tags()` on output). BAA
`baachal.php` lines 715-716 (stores IP and user-agent via `sanitize_text_field()`
in post meta). MXC `class-mxchat-integrator.php` line 231 (`wp_kses()` on stored
transcript content).

---
