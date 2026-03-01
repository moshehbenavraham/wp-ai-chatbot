# Widget & Shortcode Rendering Contract

**Session**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Version**: 2.0
**Created**: 2026-03-01
**Updated**: 2026-03-01
**Module**: `includes/frontend/class-widget-renderer.php`
**Resolves**: T010, CFI-FE-001 (unified widget rendering -- shortcode, embed modes, multi-instance)

---

## 1. Overview

This document defines how the chatbot widget is rendered on the frontend via the `[wpaic_chat]` shortcode. It specifies shortcode attributes, bot resolution logic, data attribute injection, HTML output structure, embed modes (floating and inline), multi-instance support, template hook points, honeypot spam protection, nonce and configuration injection via `wp_localize_script`, asset enqueuing, and accessibility requirements.

The design unifies three reference patterns into a single rendering pipeline:
- **AxiaChat**: Data-attribute-driven widget with 30+ `data-*` attrs on `.aichat-widget`, shortcode-level overrides (`bot`/`id`, `title`, `placeholder`, `layout`, `position`), and a honeypot hidden input for bot-trap spam protection (ref: `axiachat-ai/includes/shortcode.php` lines 34-311).
- **Baachal**: Hook-rich template with 18 `do_action` / `apply_filters` points at every structural boundary (`baachal_before_chatbot_widget`, `baachal_before_toggle_button`, `baachal_chatbot_title`, `baachal_input_placeholder`, `baachal_send_button_content`, etc.), option-driven styling via CSS classes (`chatbot-position-{pos}`, `chatbot-size-{size}`, `chatbot-animated`), and filter-based content customization (ref: `baachal/templates/chatbot-widget.php` lines 7-182).
- **MxChat**: Multi-bot support with `data-bot-id` attribute scoping all HTML IDs to the bot instance, per-bot theme assignments that conditionally skip inline color styles when an AI theme is active, and `floating` / `has_consent` / `bot_id` shortcode attributes (ref: `mxchat-basic/includes/class-mxchat-public.php` lines 164-249).

### Reference Anchors

| Source Plugin | File | Lines | Relevance |
|---------------|------|-------|-----------|
| AxiaChat | `axiachat-ai/includes/shortcode.php` | 36-58 | Shortcode registration, `shortcode_atts` call with `bot`/`id`/`title`/`placeholder`/`layout`/`position` |
| AxiaChat | `axiachat-ai/includes/shortcode.php` | 60-108 | Bot resolution fallback chain (shortcode slug > global bot > first bot in DB) |
| AxiaChat | `axiachat-ai/includes/shortcode.php` | 110-177 | UI field mapping from bot DB row to template variables |
| AxiaChat | `axiachat-ai/includes/shortcode.php` | 179-211 | Shortcode overrides, CSS class assembly, inline style for floating width |
| AxiaChat | `axiachat-ai/includes/shortcode.php` | 213-245 | Asset enqueuing and GDPR localization via `wp_localize_script('aichat-frontend', 'AIChatGDPR', ...)` |
| AxiaChat | `axiachat-ai/includes/shortcode.php` | 262-311 | Data attribute injection via `sprintf`, honeypot hidden input |
| Baachal | `baachal/templates/chatbot-widget.php` | 7-18 | Option-driven config: `baachal_welcome_message`, `baachal_title`, `baachal_position`, `baachal_size`, `baachal_animation_enabled` |
| Baachal | `baachal/templates/chatbot-widget.php` | 20-34 | CSS class assembly from options, `do_action('baachal_before_chatbot_widget')` |
| Baachal | `baachal/templates/chatbot-widget.php` | 36-68 | Toggle button with `baachal_before_toggle_button`/`baachal_after_toggle_button` hooks, filterable icon content |
| Baachal | `baachal/templates/chatbot-widget.php` | 70-110 | Header section with `baachal_before_chatbot_header`, `baachal_chatbot_header_buttons_before/after`, `baachal_after_chatbot_header` |
| Baachal | `baachal/templates/chatbot-widget.php` | 112-151 | Messages area with welcome message hooks, input area with `baachal_before_input_area`/`baachal_after_input_area`, filterable send button |
| Baachal | `baachal/templates/chatbot-widget.php` | 153-182 | Loading dots, `baachal_before_container_end`, `baachal_after_chatbot_container`, `baachal_after_chatbot_widget` |
| MxChat | `mxchat-basic/includes/class-mxchat-public.php` | 161-169 | Shortcode `[mxchat_chatbot]` with `floating`, `has_consent`, `bot_id` attrs |
| MxChat | `mxchat-basic/includes/class-mxchat-public.php` | 194-283 | Per-bot options resolution, `data-bot-id` on wrapper, AI theme detection skipping inline colors |
| MxChat | `mxchat-basic/includes/class-mxchat-public.php` | 389-405 | Input area with `aria-label` on send button, per-bot ID scoping on all elements |

---

## 2. Shortcode Definition

### 2.1 Registration

```php
add_action( 'init', function () {
    add_shortcode( 'wpaic_chat', 'wpaic_render_chat_shortcode' );
} );
```

The shortcode tag is `[wpaic_chat]`. No aliases are registered at launch. Add-on plugins may register aliases via their own `add_shortcode` calls.

When the shortcode is present on a page, the renderer sets `$GLOBALS['wpaic_has_shortcode'] = true` to signal to the global floating widget injector (fired on `wp_footer`) that a shortcode-based widget already exists on the page.

Ref: AXC `shortcode.php` line 45 (`$GLOBALS['aichat_has_shortcode'] = true`), MXC `class-mxchat-public.php` lines 111-151 (auto-append logic in `wp_footer` that checks page-level override).

### 2.2 Attribute Definitions

| Attribute | Type | Default | Validation | Description |
|-----------|------|---------|------------|-------------|
| `bot` | string | `''` (empty) | `sanitize_title()` | Bot slug to render. Primary identifier. |
| `id` | string | `''` (empty) | `sanitize_title()` | Alias for `bot`. If both provided, `bot` takes precedence. |
| `layout` | string | `'floating'` | Whitelist: `floating`, `inline` | Embed mode. See section 5. |
| `position` | string | `''` (from bot config) | Whitelist: `bottom-right`, `bottom-left`, `top-right`, `top-left` | Screen corner for floating mode. Ignored in inline mode. |
| `title` | string | `''` (empty) | `sanitize_text_field()` | Override the bot display name in the header. |
| `placeholder` | string | `''` (empty) | `sanitize_text_field()` | Override the input field placeholder text. |
| `class` | string | `''` (empty) | `preg_replace('/[^a-z0-9\-\_\s]/i', '', ...)` | Additional CSS classes appended to the root element. |
| `width` | int | `0` (from bot config) | `absint()`, range 200-800 | Widget width in pixels. Applies to floating mode only. 0 means use bot config default. |
| `height` | int | `0` (from bot config) | `absint()`, range 200-800 | Widget height in pixels (messages area). 0 means use bot config default. |

### 2.3 Attribute Resolution

```php
$atts = shortcode_atts( array(
    'bot'         => '',
    'id'          => '',
    'layout'      => 'floating',
    'position'    => '',
    'title'       => '',
    'placeholder' => '',
    'class'       => '',
    'width'       => 0,
    'height'      => 0,
), $atts, 'wpaic_chat' );
```

Ref: AXC `shortcode.php` lines 48-58 (attribute defaults and `shortcode_atts` call with `bot`, `id`, `title`, `placeholder`, `layout`, `position`). MXC `class-mxchat-public.php` lines 164-168 (`floating`, `has_consent`, `bot_id` attributes).

### 2.4 Layout Normalization

The `layout` attribute accepts common synonyms and normalizes them to canonical values:

```php
function wpaic_normalize_layout( $value ) {
    $value = strtolower( trim( (string) $value ) );
    $floating_aliases = array( 'floating', 'float', 'global', 'popup' );
    $inline_aliases   = array( 'inline', 'embed', 'embedded' );

    if ( in_array( $value, $floating_aliases, true ) ) {
        return 'floating';
    }
    if ( in_array( $value, $inline_aliases, true ) ) {
        return 'inline';
    }
    return 'floating'; // Default
}
```

### 2.5 Position Normalization

The `position` attribute accepts shorthand aliases and normalizes to canonical values:

```php
function wpaic_normalize_position( $value ) {
    $value = strtolower( trim( (string) $value ) );
    $map   = array(
        'br' => 'bottom-right',
        'bl' => 'bottom-left',
        'tr' => 'top-right',
        'tl' => 'top-left',
    );
    if ( isset( $map[ $value ] ) ) {
        return $map[ $value ];
    }
    $valid = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );
    if ( in_array( $value, $valid, true ) ) {
        return $value;
    }
    return 'bottom-right'; // Default
}
```

Ref: AXC `shortcode.php` lines 339-379 (`aichat_norm_layout` accepting `floating`/`float`/`global`/`popup`/`inline`/`embed` synonyms; `aichat_norm_pos` accepting `br`/`bl`/`tr`/`tl` shorthands and full names). BAA `chatbot-widget.php` line 12 (position options: `bottom-right`, `bottom-left`).

---

## 3. Bot Resolution

### 3.1 Priority Chain

The bot slug is resolved using the following priority chain (first non-empty wins):

```
1. Shortcode attribute:  $atts['bot'] or $atts['id']
2. Page meta:            get_post_meta( get_the_ID(), '_wpaic_bot_slug', true )
3. Global option:        get_option( 'wpaic_admin_global_bot_slug', '' )
4. First active bot:     SELECT slug FROM {$bots_table} WHERE is_active = 1
                          ORDER BY id ASC LIMIT 1
```

### 3.2 Resolution Logic

```php
// Step 1: Shortcode attribute (bot takes precedence over id)
$slug = sanitize_title( $atts['bot'] ?: $atts['id'] );

// Step 2: Page meta
if ( empty( $slug ) ) {
    $slug = sanitize_title(
        (string) get_post_meta( get_the_ID(), '_wpaic_bot_slug', true )
    );
}

// Step 3: Global default bot
if ( empty( $slug ) ) {
    $global_slug = get_option( 'wpaic_admin_global_bot_slug', '' );
    if ( $global_slug ) {
        $slug = sanitize_title( $global_slug );
    }
}

// Step 4: First active bot in database
if ( empty( $slug ) ) {
    $slug = $wpdb->get_var(
        "SELECT slug FROM {$bots_table} WHERE is_active = 1 ORDER BY id ASC LIMIT 1"
    );
    $slug = $slug ? sanitize_title( $slug ) : '';
}
```

Ref: AXC `shortcode.php` lines 60-81 (slug resolution chain: shortcode attr > global option `aichat_global_bot_slug` > first bot in DB via `SELECT slug FROM {$bots_table} ORDER BY id ASC LIMIT 1`). MXC `class-mxchat-public.php` lines 741-756 (`determine_bot_for_shortcode` checking shortcode `bot_id` > page meta `_mxchat_selected_bot` > `'default'`). BAA `chatbot-widget.php` lines 7-14 (single bot via options, no resolution chain -- N/A for multi-bot).

### 3.3 Error Handling

| Condition | Admin User (`manage_options`) | Public User |
|-----------|-------------------------------|-------------|
| No bot slug resolved | Render error notice: `[WPAIC] No bots configured.` | Render empty container (silent fail) |
| Bot slug not found in DB | Render error notice: `[WPAIC] Bot not found: {slug}` | Render empty container (silent fail) |
| Bot exists but `is_active = 0` | Render error notice: `[WPAIC] Bot is inactive: {slug}` | Render empty container (silent fail) |

Error notices are wrapped in `<div class="wpaic-chatbot"><em style="color:#b00">...</em></div>` and only visible to administrators. This prevents leaking configuration details to public users.

```php
if ( empty( $slug ) ) {
    if ( current_user_can( 'manage_options' ) ) {
        return '<div class="wpaic-chatbot"><em style="color:#b00">'
            . esc_html__( '[WPAIC] No bots configured.', 'wp-ai-chatbot' )
            . '</em></div>';
    }
    return '<div class="wpaic-chatbot"></div>';
}
```

Ref: AXC `shortcode.php` lines 84-108 (admin-only error notices with `current_user_can('manage_options')`, empty div fallback for public users). MXC `class-mxchat-public.php` lines 126-129 (returns early without output when bot determination is `false`).

---

## 4. Data Attribute Injection

All configuration values are injected as `data-wpaic-*` attributes on the root widget element. JavaScript reads these attributes at initialization time -- no inline `<script>` blocks are used for per-instance configuration.

### 4.1 Attribute Map

| Attribute | Type | Source | Default | Example |
|-----------|------|--------|---------|---------|
| `data-wpaic-bot` | string | Resolved bot slug | (required) | `"support-agent"` |
| `data-wpaic-instance` | string | Auto-generated instance ID | (generated) | `"wpaic-1"` |
| `data-wpaic-layout` | string | Shortcode attr > bot config `ui_layout` | `"floating"` | `"floating"` |
| `data-wpaic-position` | string | Shortcode attr > bot config `ui_position` | `"bottom-right"` | `"bottom-right"` |
| `data-wpaic-color` | string | Bot config `ui_color` (hex) | `"#0073aa"` | `"#0073aa"` |
| `data-wpaic-title` | string | Shortcode attr > bot config `name` | `"AI Chat"` | `"Support Bot"` |
| `data-wpaic-placeholder` | string | Shortcode attr > bot config `ui_placeholder` | `"Type your question..."` | `"Ask me anything..."` |
| `data-wpaic-width` | int | Shortcode attr > bot config `ui_width` | `380` | `380` |
| `data-wpaic-height` | int | Shortcode attr > bot config `ui_height` | `400` | `400` |
| `data-wpaic-avatar` | string | Bot config `ui_avatar` (URL) or empty | `""` | `"https://example.com/avatar.png"` |
| `data-wpaic-start-sentence` | string | Bot config `ui_start_sentence` | `""` | `"Hello! How can I help?"` |
| `data-wpaic-suggestions` | int | Bot config `ui_suggestions_enabled` (0/1) | `0` | `1` |
| `data-wpaic-suggestions-count` | int | Bot config `ui_suggestions_count` (1-6) | `3` | `3` |
| `data-wpaic-max-length` | int | Bot config `input_max_length` | `4000` | `4000` |
| `data-wpaic-history` | int | Bot config `history_persistence` (0/1) | `1` | `1` |
| `data-wpaic-closable` | int | Bot config `ui_closable` (0/1) | `1` | `1` |
| `data-wpaic-minimizable` | int | Bot config `ui_minimizable` (0/1) | `1` | `1` |

### 4.2 Injection Pattern

```php
$data_attrs = sprintf(
    'data-wpaic-bot="%s" data-wpaic-instance="%s" '
    . 'data-wpaic-layout="%s" data-wpaic-position="%s" '
    . 'data-wpaic-color="%s" data-wpaic-title="%s" '
    . 'data-wpaic-placeholder="%s" '
    . 'data-wpaic-width="%d" data-wpaic-height="%d" '
    . 'data-wpaic-avatar="%s" data-wpaic-start-sentence="%s" '
    . 'data-wpaic-suggestions="%d" data-wpaic-suggestions-count="%d" '
    . 'data-wpaic-max-length="%d" data-wpaic-history="%d" '
    . 'data-wpaic-closable="%d" data-wpaic-minimizable="%d"',
    esc_attr( $slug ),
    esc_attr( $instance_id ),
    esc_attr( $layout ),
    esc_attr( $position ),
    esc_attr( $color ),
    esc_attr( $title ),
    esc_attr( $placeholder ),
    $width,
    $height,
    esc_attr( $avatar_url ),
    esc_attr( $start_sentence ),
    $suggestions_enabled ? 1 : 0,
    $suggestions_count,
    $max_length,
    $history_persistence ? 1 : 0,
    $closable ? 1 : 0,
    $minimizable ? 1 : 0
);
```

All string values pass through `esc_attr()`. Integer flags use `%d` format specifier. This mirrors the AXC pattern of injecting all configuration as data attributes, while using the `data-wpaic-*` namespace prefix to avoid collisions.

Ref: AXC `shortcode.php` lines 262-303 (30+ data attributes on `.aichat-widget` via `sprintf`: `data-bot`, `data-type`, `data-title`, `data-placeholder`, `data-layout`, `data-position`, `data-color`, `data-width`, `data-height`, `data-avatar-enabled`, `data-avatar-url`, `data-role`, `data-button-send`, `data-start-sentence`, `data-closable`, `data-minimizable`, `data-draggable`, `data-suggestions-enabled`, `data-suggestions-count`, `data-history-persistence`, `data-branding-text`/`url`/`icon`). MXC `class-mxchat-public.php` line 283 (`data-bot-id` attribute on `mxchat-chatbot-wrapper` div for per-bot scoping). BAA `chatbot-widget.php` lines 20-31 (option-driven CSS classes instead of data attributes: `chatbot-position-{pos}`, `chatbot-size-{size}`, `chatbot-animated`).

---

## 5. Embed Modes

### 5.1 Floating Mode (`layout="floating"`)

- Widget is rendered with `position: fixed` CSS, anchored to the corner specified by `data-wpaic-position`.
- A circular toggle button is rendered outside the main container to open/close the chat panel.
- The chat panel supports minimized state (toggle button only visible) and open state (full panel).
- When `width` is specified, floating container receives an inline `style="width: {n}px"`.
- Only one floating widget is permitted per page (see section 7.3).

**Position mapping**:

| Value | CSS | Description |
|-------|-----|-------------|
| `bottom-right` | `bottom: 20px; right: 20px` | Bottom-right (default) |
| `bottom-left` | `bottom: 20px; left: 20px` | Bottom-left |
| `top-right` | `top: 20px; right: 20px` | Top-right |
| `top-left` | `top: 20px; left: 20px` | Top-left |

Ref: AXC `shortcode.php` lines 196-211 (floating layout adds `is-global` class, position class `pos-{position}`, inline width style for floating). BAA `chatbot-widget.php` lines 12, 21-24 (position options `bottom-right`/`bottom-left` mapped to CSS class `chatbot-position-{position}`). MXC `class-mxchat-public.php` lines 278-279 (floating wrapper div `floating-chatbot-{bot_id}`).

### 5.2 Inline Mode (`layout="inline"`)

- Widget flows with page content at the shortcode position in the DOM.
- No toggle button is rendered; the chat panel is always visible.
- No fixed/absolute positioning is applied.
- Width defaults to 100% of the parent container unless `width` attribute is set.
- No minimize or close buttons in the header.
- No position modifier class is applied.

Ref: AXC `shortcode.php` line 112 (layout default `'inline'` for bots, normalized by `aichat_norm_layout`). MXC `class-mxchat-public.php` lines 174-186 (non-floating shortcodes `floating="no"` are never blocked by hide setting, allowing embedded chatbots).

---

## 6. HTML Output Structure

### 6.1 Floating Mode

```html
<!-- Hook: wpaic_before_widget ($bot, $instance_id, 'floating') -->

<!-- Toggle button (floating only) -->
<button class="wpaic-chatbot__toggle wpaic-chatbot__toggle--bottom-right"
        id="wpaic-chatbot-toggle-{instance_id}"
        aria-label="Open chat"
        aria-expanded="false"
        aria-controls="wpaic-chatbot-panel-{instance_id}"
        type="button">
  <!-- Content controlled by 'wpaic_toggle_icon_html' filter -->
</button>

<!-- Chat panel -->
<div id="wpaic-chatbot-{instance_id}"
     class="wpaic-chatbot wpaic-chatbot--floating wpaic-chatbot--bottom-right {custom_classes}"
     data-wpaic-bot="..." data-wpaic-instance="..." data-wpaic-layout="floating" ...
     role="complementary"
     aria-label="Chat widget"
     style="width: 380px">

  <!-- Hook: wpaic_before_header ($bot, $instance_id) -->

  <!-- Header -->
  <div class="wpaic-chatbot__header">
    <div class="wpaic-chatbot__header-info">
      <img class="wpaic-chatbot__avatar" src="..." alt="" />
      <span class="wpaic-chatbot__title">Bot Name</span>
    </div>
    <div class="wpaic-chatbot__header-buttons">
      <!-- Hook: wpaic_header_buttons ($bot, $instance_id) -->
      <button class="wpaic-chatbot__btn-minimize"
              aria-label="Minimize chat"
              type="button">
        <!-- minimize icon -->
      </button>
      <button class="wpaic-chatbot__btn-close"
              aria-label="Close chat"
              type="button">
        <!-- close icon -->
      </button>
    </div>
  </div>

  <!-- Hook: wpaic_after_header ($bot, $instance_id) -->

  <!-- Hook: wpaic_before_messages ($bot, $instance_id) -->

  <!-- Messages -->
  <div class="wpaic-chatbot__messages"
       id="wpaic-chatbot-messages-{instance_id}"
       role="log"
       aria-live="polite"
       aria-label="Chat messages">
    <!-- Welcome message and conversation injected by JS -->
  </div>

  <!-- Hook: wpaic_after_messages ($bot, $instance_id) -->

  <!-- Hook: wpaic_before_input ($bot, $instance_id) -->

  <!-- Input area -->
  <div class="wpaic-chatbot__input-area">
    <textarea class="wpaic-chatbot__input"
              id="wpaic-chatbot-input-{instance_id}"
              placeholder="Type your question..."
              aria-label="Chat input"
              maxlength="4000"
              rows="1"></textarea>
    <button class="wpaic-chatbot__btn-send"
            id="wpaic-chatbot-send-{instance_id}"
            aria-label="Send message"
            type="button"
            disabled>
      <!-- Content controlled by 'wpaic_send_button_html' filter -->
    </button>
  </div>

  <!-- Hook: wpaic_after_input ($bot, $instance_id) -->

  <!-- Honeypot (spam protection) -->
  <input type="text"
         name="wpaic_hp"
         value=""
         tabindex="-1"
         autocomplete="off"
         aria-hidden="true"
         class="wpaic-chatbot__honeypot" />

</div>

<!-- Hook: wpaic_after_widget ($bot, $instance_id, 'floating') -->
```

### 6.2 Inline Mode

Identical to floating mode except:
- No `<button class="wpaic-chatbot__toggle">` element is rendered.
- Root `<div>` uses `wpaic-chatbot--inline` modifier instead of `wpaic-chatbot--floating`.
- No position modifier class (`--bottom-right`, `--bottom-left`, etc.).
- No minimize or close buttons in the header.
- No inline `style="width: ..."` unless the shortcode `width` attribute is explicitly set.

### 6.3 CSS Class Naming Convention

All classes follow BEM methodology with the `wpaic-` prefix:

| Pattern | Example | Usage |
|---------|---------|-------|
| Block | `wpaic-chatbot` | Root widget container |
| Element | `wpaic-chatbot__header` | Structural child of block |
| Modifier | `wpaic-chatbot--floating` | Variant of block or element |
| State | `wpaic-chatbot--open` | Interactive state (toggled by JS) |
| Position | `wpaic-chatbot--bottom-right` | Position modifier (floating only) |

Ref: AXC `shortcode.php` lines 195-205 (flat classes: `aichat-widget`, `is-global`, `pos-bottom-right`, custom class from attr). BAA `chatbot-widget.php` lines 20-31 (option-driven classes: `chatbot-position-bottom-right`, `chatbot-size-medium`, `chatbot-animated`). MXC `class-mxchat-public.php` lines 279-283 (ID-based scoping: `id="mxchat-chatbot-wrapper-{bot_id}"`, class `mxchat-chatbot-wrapper`).

**WPAIC rationale**: BEM naming avoids the specificity wars caused by ID-based selectors (MXC pattern) while being more structured than flat class names (AXC pattern). The `wpaic-` prefix prevents collisions with theme and other plugin styles.

---

## 7. Multi-Instance Support

### 7.1 Instance ID Generation

Each shortcode invocation receives a unique instance ID to prevent DOM collisions when multiple chatbot widgets appear on the same page:

```php
static $wpaic_instance_counter = 0;
$wpaic_instance_counter++;
$instance_id = 'wpaic-' . $wpaic_instance_counter;
```

The instance ID scopes:
- Root container `id`: `wpaic-chatbot-{instance_id}`
- Toggle button `id`: `wpaic-chatbot-toggle-{instance_id}`
- Toggle button `aria-controls`: references the panel ID
- Messages container `id`: `wpaic-chatbot-messages-{instance_id}`
- Input field `id`: `wpaic-chatbot-input-{instance_id}`
- Send button `id`: `wpaic-chatbot-send-{instance_id}`
- The `data-wpaic-instance` attribute on the root element

Ref: MXC `class-mxchat-public.php` lines 279-405 (per-bot ID scoping: every element ID is suffixed with `-{bot_id}`, e.g., `mxchat-chatbot-wrapper-{bot_id}`, `chat-container-{bot_id}`, `chat-box-{bot_id}`, `chat-input-{bot_id}`, `send-button-{bot_id}`). AXC `shortcode.php` lines 262-304 (single instance assumed, no ID scoping -- N/A for multi-instance).

### 7.2 State Isolation

Each widget instance maintains independent state:
- Separate session token per bot (stored in `localStorage` as `wpaic_session_{bot_slug}`)
- Separate message history in the DOM (scoped by instance container)
- Separate input field state
- Separate UI state (idle, sending, streaming, error)

If the same bot slug appears in two shortcodes on the same page, they share the same session token (same `localStorage` key) but maintain separate DOM states. This is intentional: the conversation continues seamlessly regardless of which instance the user interacts with.

Ref: MXC `class-mxchat-public.php` lines 195-198 (per-bot options resolution: `$bot_options = $this->get_bot_options($bot_id)` with fallback to global options). AXC `shortcode.php` lines 45, 196 (`$GLOBALS['aichat_has_shortcode'] = true` prevents duplicate global widget, single instance assumed).

### 7.3 Floating Instance Limit

Only one floating widget is permitted per page. The renderer enforces this with a static flag:

```php
static $wpaic_has_floating = false;

if ( $layout === 'floating' ) {
    if ( $wpaic_has_floating ) {
        // Downgrade to inline, log warning
        $layout = 'inline';
        wpaic_log( 'warning', 'Multiple floating widgets on page; downgrading to inline.', array(
            'instance_id' => $instance_id,
            'bot_slug'    => $slug,
        ) );
    } else {
        $wpaic_has_floating = true;
    }
}
```

This prevents overlapping fixed-position widgets. The first floating shortcode wins; subsequent floating shortcodes on the same page are silently downgraded to inline with a logged warning.

Ref: AXC `shortcode.php` line 45 (`$GLOBALS['aichat_has_shortcode'] = true` prevents duplicate global widget injection). MXC `class-mxchat-public.php` lines 174-183 (floating shortcodes check `should_hide_chatbot` to prevent duplicate floating chatbots).

---

## 8. Honeypot Field

A hidden text input acts as a bot trap for automated spam submissions. Human users never see or interact with this field. Bots that auto-complete form fields will fill it, and the backend rejects any request where the honeypot field contains a value.

### 8.1 HTML

```html
<input type="text"
       name="wpaic_hp"
       value=""
       tabindex="-1"
       autocomplete="off"
       aria-hidden="true"
       class="wpaic-chatbot__honeypot" />
```

### 8.2 CSS

The honeypot is visually hidden using off-screen positioning. It is NOT hidden via `display: none` or `visibility: hidden` because some bots detect those and skip the field:

```css
.wpaic-chatbot__honeypot {
    position: absolute !important;
    left: -9999px !important;
    top: auto !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
}
```

### 8.3 Backend Validation

The message pipeline (Stage 1.3 in `message_lifecycle_pipeline.md`) checks the honeypot value:

```php
if ( ! empty( $_POST['wpaic_hp'] ) ) {
    // Silently reject -- do not reveal the honeypot mechanism
    wp_send_json_error( array(
        'code'    => 'spam_detected',
        'message' => __( 'Message could not be sent.', 'wp-ai-chatbot' ),
    ), 400 );
}
```

### 8.4 Accessibility

- `aria-hidden="true"` hides the field from screen readers
- `tabindex="-1"` removes it from the tab order
- Human users never encounter this field via any input modality

Ref: AXC `shortcode.php` lines 306-309 (honeypot `<input type="text" name="aichat_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute!important;left:-9999px!important;...">`, backend rejects non-empty `$_POST['aichat_hp']`). MXC and BAA do not implement honeypot fields (N/A).

---

## 9. Template Hook Points

Hooks follow the Baachal pattern of `do_action` at every structural boundary, enabling add-ons and themes to inject custom content without modifying the core template.

### 9.1 Action Hooks

| Hook | Location | Arguments | Description |
|------|----------|-----------|-------------|
| `wpaic_before_widget` | Before root `<div>` opens | `$bot, $instance_id, $layout` | Inject content before the widget container |
| `wpaic_after_widget` | After root `<div>` closes | `$bot, $instance_id, $layout` | Inject content after the widget container |
| `wpaic_before_header` | Inside container, before header `<div>` | `$bot, $instance_id` | Inject content before the header section |
| `wpaic_after_header` | Inside container, after header `<div>` | `$bot, $instance_id` | Inject content after the header section |
| `wpaic_header_buttons` | Inside header buttons `<div>`, before built-in buttons | `$bot, $instance_id` | Add custom header buttons (e.g., clear history, export) |
| `wpaic_before_messages` | Inside container, before messages `<div>` | `$bot, $instance_id` | Inject content before the messages area |
| `wpaic_after_messages` | Inside container, after messages `<div>` | `$bot, $instance_id` | Inject content after the messages area |
| `wpaic_before_input` | Inside container, before input area `<div>` | `$bot, $instance_id` | Inject content before the input area |
| `wpaic_after_input` | Inside container, after input area `<div>` | `$bot, $instance_id` | Inject content after the input area |
| `wpaic_before_toggle` | Before toggle button (floating only) | `$bot, $instance_id` | Inject content before the toggle button |
| `wpaic_after_toggle` | After toggle button (floating only) | `$bot, $instance_id` | Inject content after the toggle button |

### 9.2 Filter Hooks

| Filter | Default Value | Expected Return | Description |
|--------|---------------|-----------------|-------------|
| `wpaic_widget_title` | Bot `name` or shortcode `title` | string | Modify the displayed widget title |
| `wpaic_input_placeholder` | Bot `ui_placeholder` or shortcode `placeholder` | string | Modify the input placeholder text |
| `wpaic_welcome_message` | Bot `ui_start_sentence` | string | Modify the welcome message content |
| `wpaic_send_button_html` | SVG send icon markup | string (HTML) | Replace the send button inner content |
| `wpaic_toggle_icon_html` | SVG chat icon markup | string (HTML) | Replace the toggle button inner content |
| `wpaic_close_icon_html` | SVG close icon markup | string (HTML) | Replace the close button inner content |
| `wpaic_widget_classes` | Array of CSS classes | array | Add/remove CSS classes on the root element |
| `wpaic_show_clear_history` | `true` | bool | Show or hide the clear history button |

### 9.3 Filter Usage Example

```php
// Plugin or theme customization:
add_filter( 'wpaic_widget_title', function ( $title, $bot, $instance_id ) {
    return 'Ask ' . $title;
}, 10, 3 );

add_filter( 'wpaic_send_button_html', function ( $html ) {
    return '<span class="dashicons dashicons-arrow-right-alt"></span>';
}, 10, 1 );

add_filter( 'wpaic_welcome_message', function ( $message, $bot ) {
    return sprintf( 'Welcome! I am %s. How can I help?', $bot['name'] );
}, 10, 2 );
```

Ref: BAA `chatbot-widget.php` lines 32-176 (18 hook points: `baachal_before_chatbot_widget`, `baachal_before_toggle_button`, `baachal_after_toggle_button`, `baachal_before_chatbot_header`, `baachal_chatbot_header_buttons_before`, `baachal_chatbot_header_buttons_after`, `baachal_after_chatbot_header`, `baachal_before_welcome_message`, `baachal_after_welcome_message`, `baachal_before_input_area`, `baachal_after_input_area`, `baachal_before_container_end`, `baachal_after_chatbot_container`, `baachal_after_chatbot_widget`; filters: `baachal_welcome_message`, `baachal_show_clear_history`, `baachal_chatbot_title`, `baachal_input_placeholder`, `baachal_send_button_content`, `baachal_chat_icon_content`, `baachal_close_icon_content`, `baachal_loading_content`). AXC `shortcode.php` lines 249 (`apply_filters('aichat_widget_branding', [], $slug)` for branding injection).

---

## 10. Nonce and Configuration Injection

### 10.1 Localized Data via wp_localize_script

The `wpaicChatConfig` object is passed to JavaScript via `wp_localize_script`. It contains global configuration that applies to all widget instances on the page. This is emitted once per page load, not per widget instance.

```php
static $wpaic_config_localized = false;

if ( ! $wpaic_config_localized ) {
    wp_localize_script( 'wpaic-chatbot', 'wpaicChatConfig', array(
        'restUrl'       => esc_url_raw( rest_url( 'wp-ai-chatbot/v1/' ) ),
        'ajaxUrl'       => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
        'nonce'         => wp_create_nonce( 'wp_rest' ),
        'chatNonce'     => wp_create_nonce( 'wpaic_chat' ),
        'pluginUrl'     => esc_url_raw( plugins_url( '', WPAIC_PLUGIN_FILE ) ),
        'streamEnabled' => (bool) get_option( 'wpaic_runtime_stream_enabled', true ),
        'i18n'          => array(
            'sendLabel'      => __( 'Send', 'wp-ai-chatbot' ),
            'typingLabel'    => __( 'Typing...', 'wp-ai-chatbot' ),
            'openChat'       => __( 'Open chat', 'wp-ai-chatbot' ),
            'closeChat'      => __( 'Close chat', 'wp-ai-chatbot' ),
            'minimizeChat'   => __( 'Minimize chat', 'wp-ai-chatbot' ),
            'clearHistory'   => __( 'Clear chat history', 'wp-ai-chatbot' ),
            'errorGeneric'   => __( 'Something went wrong. Please try again.', 'wp-ai-chatbot' ),
            'errorNetwork'   => __( 'Network error. Check your connection.', 'wp-ai-chatbot' ),
            'errorRateLimit' => __( 'Too many messages. Please wait a moment.', 'wp-ai-chatbot' ),
        ),
    ) );
    $wpaic_config_localized = true;
}
```

### 10.2 Localized Data Shape

| Key | Type | Description |
|-----|------|-------------|
| `restUrl` | string | Base URL for the plugin REST API (e.g., `/wp-json/wp-ai-chatbot/v1/`) |
| `ajaxUrl` | string | WordPress AJAX handler URL (`admin-ajax.php`) |
| `nonce` | string | WordPress REST API nonce for session management endpoints |
| `chatNonce` | string | Action-specific nonce for chat message submission (AJAX endpoints) |
| `pluginUrl` | string | Plugin base URL for resolving asset paths (icons, images) |
| `streamEnabled` | bool | Whether SSE streaming is globally enabled |
| `i18n` | object | Translatable UI strings for frontend rendering |

### 10.3 Nonce Strategy

Two separate nonces serve different endpoint types:

| Nonce | Action Name | Used By | Verification |
|-------|-------------|---------|--------------|
| `nonce` | `wp_rest` | REST API endpoints (session create/resume/close/history) | Automatic via `permission_callback` and `X-WP-Nonce` header |
| `chatNonce` | `wpaic_chat` | AJAX endpoints (message send, streaming) | Manual via `check_ajax_referer( 'wpaic_chat', 'nonce' )` |

The AJAX chat endpoint uses a dedicated nonce action name (`wpaic_chat`) rather than the generic `wp_rest` to provide endpoint-specific CSRF protection. Both nonces are injected into the same `wpaicChatConfig` object so the frontend does not need to manage separate script localizations.

Ref: AXC `shortcode.php` lines 218-244 (separate `wp_localize_script` calls: `aichat-frontend` handle for main script, `AIChatGDPR` object for GDPR consent with `enabled`, `text`, `button`, `cookie` keys; uses static flag `$aichat_gdpr_localized` to emit once). MXC `class-mxchat-public.php` lines 58-63 (`wp_localize_script('mxchat-test-panel', 'mxchatTestData', array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('mxchat_test_nonce'), ...))` for test panel data).

---

## 11. Asset Enqueuing

### 11.1 Conditional Loading

Assets are enqueued only when the `[wpaic_chat]` shortcode is present on the current page. This prevents loading unnecessary CSS/JS on pages that do not use the chatbot.

```php
function wpaic_render_chat_shortcode( $atts ) {
    // ... bot resolution, HTML generation ...

    // Enqueue assets (runs once per page load via wp_enqueue)
    wp_enqueue_style( 'wpaic-chatbot' );
    wp_enqueue_script( 'wpaic-chatbot' );

    return $html;
}
```

### 11.2 Asset Registration

Assets are registered early (on `wp_enqueue_scripts`) but not enqueued until the shortcode callback executes:

```php
add_action( 'wp_enqueue_scripts', function () {
    wp_register_style(
        'wpaic-chatbot',
        plugins_url( 'assets/css/chatbot.css', WPAIC_PLUGIN_FILE ),
        array(),
        WPAIC_VERSION
    );

    wp_register_script(
        'wpaic-chatbot',
        plugins_url( 'assets/js/chatbot.js', WPAIC_PLUGIN_FILE ),
        array(),   // No jQuery dependency -- vanilla JS
        WPAIC_VERSION,
        true       // Load in footer
    );
} );
```

### 11.3 No jQuery Dependency

The frontend script uses vanilla JavaScript with no jQuery dependency. This reduces page weight and avoids conflicts with themes that deregister jQuery. The empty dependency array `array()` is intentional.

Ref: AXC `shortcode.php` lines 214-216 (`wp_enqueue_style('aichat-frontend')`, `wp_enqueue_script('jquery')`, `wp_enqueue_script('aichat-frontend')` -- note jQuery dependency). MXC `class-mxchat-public.php` lines 49-55 (`wp_enqueue_script('mxchat-test-panel', ..., ['jquery'], ...)` -- jQuery dependency). BAA `chatbot-widget.php` does not directly enqueue assets (handled in main plugin class -- N/A for enqueue pattern, but `baachal/assets/chatbot.js` is vanilla JS).

**WPAIC rationale**: AXC and MXC depend on jQuery. WPAIC removes this dependency to match BAA's vanilla JS approach, reducing payload and eliminating the jQuery version conflict risk across WP themes.

---

## 12. Accessibility

### 12.1 ARIA Attributes

| Element | ARIA Attribute | Value | Purpose |
|---------|----------------|-------|---------|
| Root container | `role="complementary"` | -- | Identifies the widget as a supplementary page region |
| Root container | `aria-label` | `"Chat widget"` | Descriptive label for screen readers |
| Toggle button | `aria-label` | `"Open chat"` / `"Close chat"` | Toggled by JS on state change |
| Toggle button | `aria-expanded` | `"false"` / `"true"` | Reflects chat panel visibility |
| Toggle button | `aria-controls` | Panel element ID | Associates button with controlled panel |
| Minimize button | `aria-label` | `"Minimize chat"` | Descriptive label for icon-only button |
| Close button | `aria-label` | `"Close chat"` | Descriptive label for icon-only button |
| Messages area | `role="log"` | -- | Announces new messages automatically |
| Messages area | `aria-live="polite"` | -- | Screen reader announces new content without interrupting |
| Messages area | `aria-label` | `"Chat messages"` | Descriptive label |
| Input textarea | `aria-label` | `"Chat input"` | Label for the input field |
| Send button | `aria-label` | `"Send message"` | Descriptive label for the icon-only button |
| Send button | `disabled` | present when empty | Prevents submission of empty messages |
| Honeypot field | `aria-hidden="true"` | -- | Hidden from assistive technology |
| Honeypot field | `tabindex="-1"` | -- | Excluded from tab order |

Ref: MXC `class-mxchat-public.php` lines 298, 355-356, 362-363, 391 (`aria-label` on minimize button, show/hide quick questions buttons, send button). BAA `chatbot-widget.php` does not use ARIA attributes on the widget structure (N/A -- accessibility gap addressed in WPAIC). AXC `shortcode.php` line 308 (`aria-hidden="true"` on honeypot only).

### 12.2 Keyboard Navigation

| Key | Context | Behavior |
|-----|---------|----------|
| `Enter` | Input textarea (without Shift) | Sends the message |
| `Shift+Enter` | Input textarea | Inserts a newline |
| `Escape` | Chat panel open (floating mode) | Minimizes/closes the chat panel, returns focus to toggle button |
| `Tab` | Within widget | Cycles through interactive elements in DOM order |
| `Space` / `Enter` | Toggle button focused | Toggles the chat panel open/closed |

### 12.3 Focus Management

- When the chat panel opens (floating mode), focus moves to the input textarea.
- When the chat panel closes (via close button or Escape), focus returns to the toggle button.
- New messages do not steal focus from the input field.
- The toggle button receives focus when the page loads if it is the only interactive chatbot element visible (floating, minimized state).

### 12.4 Color Contrast

The `data-wpaic-color` theme color is used for the header background and send button. The renderer does not enforce contrast ratios at the PHP level, but the default stylesheet ensures WCAG 2.1 AA compliance (4.5:1 minimum contrast ratio) for the default color scheme (`#0073aa` on white). Custom theme colors set via bot configuration are the site administrator's responsibility for contrast compliance.

Ref: MXC `class-mxchat-public.php` lines 219-221 (AI theme detection: `$skip_inline_colors` flag skips inline color styles when an AI-generated theme is active, delegating to CSS classes). BAA `chatbot-widget.php` lines 26-28 (animation class `chatbot-animated` is purely presentational -- does not affect accessibility).

---

## 13. Global Floating Widget Injection

### 13.1 Auto-Append via wp_footer

In addition to explicit `[wpaic_chat]` shortcodes, the plugin can auto-inject a floating widget on every page via the `wp_footer` hook. This is controlled by the `wpaic_admin_global_bot_enabled` option.

```php
add_action( 'wp_footer', function () {
    // Skip if a shortcode already rendered a widget on this page
    if ( ! empty( $GLOBALS['wpaic_has_shortcode'] ) ) {
        return;
    }

    // Skip if global floating widget is disabled
    if ( ! get_option( 'wpaic_admin_global_bot_enabled', false ) ) {
        return;
    }

    // Skip on admin pages
    if ( is_admin() ) {
        return;
    }

    $global_slug = get_option( 'wpaic_admin_global_bot_slug', '' );
    if ( empty( $global_slug ) ) {
        return;
    }

    echo do_shortcode( '[wpaic_chat bot="' . esc_attr( $global_slug ) . '" layout="floating"]' );
} );
```

### 13.2 Page-Level Override

Individual pages can suppress the global floating widget via post meta:

```php
$hide_on_page = get_post_meta( get_the_ID(), '_wpaic_hide_chatbot', true );
if ( $hide_on_page === '1' ) {
    return; // Do not inject global widget on this page
}
```

Ref: MXC `class-mxchat-public.php` lines 111-151 (`append_chatbot_to_body` on `wp_footer`: checks `should_hide_chatbot('auto')`, `get_display_bot()`, page meta `_mxchat_hide_chatbot`, global `append_to_body` option; falls back to `do_shortcode('[mxchat_chatbot floating="yes" ...]')`). AXC `shortcode.php` line 45 (`$GLOBALS['aichat_has_shortcode'] = true` prevents duplicate global widget). BAA `chatbot-widget.php` is loaded as a template directly (N/A for conditional injection -- always renders).

---

## 14. Source Comparison Summary

| Aspect | AxiaChat | MxChat | Baachal | WPAIC |
|--------|----------|--------|---------|-------|
| Shortcode tag | `[aichat]` | `[mxchat_chatbot]` | None (template include) | `[wpaic_chat]` |
| Bot identifier | `bot` / `id` attr | `bot_id` attr | Single bot (options) | `bot` / `id` attr |
| Embed modes | `floating` / `inline` (normalized) | `floating` attr (`yes`/`no`) | Floating only | `floating` / `inline` |
| Position options | `br`/`bl`/`tr`/`tl` (normalized) | N/A (CSS only) | `bottom-right`/`bottom-left` | `bottom-right`/`bottom-left`/`top-right`/`top-left` |
| Width/height | Shortcode via bot config (px) | Theme options only | Options (`small`/`medium`/`large`) | Shortcode attr (px) with bot config fallback |
| Data attributes | `data-bot`, `data-type`, 30+ flat names | `data-bot-id` only | None (PHP template) | `data-wpaic-*` namespaced |
| Multi-instance | Single instance (static flag) | Per-bot ID scoping on all HTML IDs | Single instance | Counter-based instance IDs + per-bot scoping |
| Template hooks | 1 filter (`aichat_widget_branding`) | 1 filter (`mxchat_get_bot_options`) | 18 actions + 8 filters | 11 actions + 8 filters |
| Honeypot | Yes (hidden input, `aichat_hp`) | No | No | Yes (hidden input, `wpaic_hp`) |
| CSS methodology | Flat classes (`aichat-widget`, `pos-*`) | ID-based (`#mxchat-chatbot-*`) | ID-based + class-based (`#ai-chatbot-widget`, `chatbot-position-*`) | BEM (`wpaic-chatbot__element--modifier`) |
| JS dependency | jQuery required | jQuery required | Vanilla JS | Vanilla JS |
| Asset loading | Shortcode callback (conditional) | Always (all pages via constructor) | Always (template include) | Shortcode callback (conditional) |
| Config injection | `wp_localize_script` (GDPR separate) | `wp_localize_script` (test panel) | None (PHP template variables) | `wp_localize_script` (unified `wpaicChatConfig`) |
| Nonce strategy | Inline in localized data | Per-feature nonce (`mxchat_test_nonce`) | None visible in template | Dual nonce: `wp_rest` + `wpaic_chat` |
| Global widget | `$GLOBALS['aichat_has_shortcode']` flag | `wp_footer` auto-append with page override | Always rendered | `wp_footer` auto-append with shortcode flag + page meta override |
| Accessibility | `aria-hidden` on honeypot only | `aria-label` on 4 buttons | No ARIA attributes | Full ARIA: `role`, `aria-live`, `aria-expanded`, `aria-controls`, labels on all interactive elements |

---

## 15. Implementation Checklist

The following items must be implemented to satisfy this contract:

1. [ ] Register `[wpaic_chat]` shortcode on `init` hook
2. [ ] Implement `shortcode_atts` with all 9 attributes (bot, id, layout, position, title, placeholder, class, width, height)
3. [ ] Implement layout and position normalization functions
4. [ ] Implement 4-step bot resolution chain (shortcode > page meta > global option > first active)
5. [ ] Implement admin-only error messages with empty container fallback
6. [ ] Implement data attribute injection with `esc_attr()` on all string values
7. [ ] Implement instance ID counter for multi-instance support
8. [ ] Implement floating instance limit with inline downgrade
9. [ ] Implement honeypot hidden input field
10. [ ] Implement 11 action hooks and 8 filter hooks at template boundaries
11. [ ] Implement `wp_localize_script` with `wpaicChatConfig` object (once per page)
12. [ ] Implement dual nonce strategy (`wp_rest` + `wpaic_chat`)
13. [ ] Implement conditional asset enqueuing (register early, enqueue in shortcode)
14. [ ] Implement global floating widget injection via `wp_footer`
15. [ ] Implement page-level override via `_wpaic_hide_chatbot` post meta
16. [ ] Apply all ARIA attributes per section 12.1
17. [ ] Implement keyboard navigation per section 12.2
18. [ ] Implement focus management per section 12.3
19. [ ] Use BEM CSS class naming throughout
20. [ ] No jQuery dependency in frontend script

---
