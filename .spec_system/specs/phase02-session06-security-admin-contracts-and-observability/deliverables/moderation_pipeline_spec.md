# Moderation Pipeline Specification

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

The moderation pipeline (`includes/security/class-moderator.php`) implements a 5-layer sequential
filter for incoming user messages. The design adopts AxiaChat's 5-layer pattern as-is (Phase 01
DOM-SECURITY winner, DEC-SEC-003). Each layer is independently configurable (enabled/disabled).
Layers execute in the fixed order below: if any layer blocks the message, the pipeline stops and
returns a `Moderation_Result` with `blocked = true` and the blocking layer's reason code.

The moderation pipeline is called in the pre-processing stage of the message pipeline
(`class-message-pipeline.php`, Stage 2 of 7) after nonce/session auth and before provider dispatch.

**Owner module**: `includes/security/class-moderator.php`
**Namespace**: `WPAIC\Security\Moderator`

---

## 2. Pipeline Layers

### Layer Order (fixed, cannot be reordered)

| Order | Layer | Config Key | Default | Block on |
|-------|-------|-----------|---------|---------|
| 1 | Honeypot | `wpaic_moderation_honeypot_enabled` | `true` | Hidden field filled |
| 2 | Rate Limit | `wpaic_moderation_rate_limit_enabled` | `true` | Exceeds per-IP limit |
| 3 | Spam Signature | `wpaic_moderation_spam_enabled` | `true` | Hash matches spam DB |
| 4 | Banned Words | `wpaic_moderation_banned_words_enabled` | `true` | Word in banned list |
| 5 | AI Moderation | `wpaic_moderation_ai_enabled` | `false` | AI flags content |

---

## 3. Moderation Result Contract

```php
namespace WPAIC\Security;

class Moderation_Result {
    public bool   $blocked;          // True if message was blocked
    public string $layer;            // Name of blocking layer (empty if not blocked)
    public string $reason_code;      // Machine-readable reason (e.g., 'honeypot_filled')
    public string $user_message;     // User-facing error message (localized)
    public array  $metadata;         // Layer-specific debug data (only in WPAIC_DEBUG mode)

    public static function pass(): self;
    public static function block( string $layer, string $reason_code, string $user_message, array $metadata = [] ): self;
}
```

---

## 4. Layer Specifications

### Layer 1: Honeypot

**Purpose**: Bot detection. A hidden HTML form field (`name="wpaic_hp"`) is injected into the chat
widget template. Real users never fill it. Bots that auto-fill forms will fill it.

**Implementation**:
```php
// In class-moderator.php:
if ( ! empty( $request_data['wpaic_hp'] ) ) {
    return Moderation_Result::block( 'honeypot', 'honeypot_filled', __( 'Submission rejected.', 'wp-ai-chatbot' ) );
}
```

**Template integration**: `templates/chatbot-widget.php` renders `<input type="text" name="wpaic_hp"
class="wpaic-hp" tabindex="-1" autocomplete="off" aria-hidden="true">`. CSS hides it.

**Configuration**: `wpaic_moderation_honeypot_enabled` (bool, default true).

**Source anchor**: `axiachat-ai/includes/class-aichat-ajax.php` lines 131-140 honeypot check.

---

### Layer 2: Rate Limit

**Purpose**: Prevent message flooding from a single IP address.

**Implementation**: Calls `lib/rate-limiter.php:check_rate_limit('wpaic_chat', $client_ip)`.
The rate limiter uses WP transients with configurable window/limit via options:
- `wpaic_security_rate_limit_requests`: requests per window (default 20)
- `wpaic_security_rate_limit_window`: window in seconds (default 60)

**Block behavior**: Returns HTTP 429 body with `wpaic_retry_after` seconds in metadata.
User-facing message: "Too many requests. Please wait and try again."

**Bypass rule**: Users with `manage_options` capability bypass rate limiting. Per-IP overrides
can be set via `wpaic_rate_limit_bypass_ips` option (comma-separated IP list).

**Configuration**: `wpaic_moderation_rate_limit_enabled` (bool, default true).

**Source anchor**: `axiachat-ai/includes/class-aichat-ajax.php` lines 141-160 rate limit check.

---

### Layer 3: Spam Signature

**Purpose**: Block known spam patterns via content fingerprinting.

**Implementation**: The message is hashed with `md5(strtolower(trim($message)))`. The hash is
checked against a transient-cached list of known spam hashes stored in option
`wpaic_security_spam_hashes` (JSON array, admin-editable). Optionally, Akismet API is consulted
if active (via `wpaic_moderation_akismet_enabled` option).

**Block behavior**: Returns reason code `spam_signature_match`. Does not reveal which signature
matched. Metadata contains hash (debug mode only).

**Configuration**: `wpaic_moderation_spam_enabled` (bool, default true).
Extension hook: `wpaic_moderation_spam_check` filter receives `$message` and returns bool.

**Source anchor**: `axiachat-ai/includes/class-aichat-ajax.php` lines 161-185 spam signature check.

---

### Layer 4: Banned Words

**Purpose**: Block messages containing site-administrator-defined prohibited terms.

**Implementation**: The banned word list is stored in `wpaic_security_banned_words` (newline-
separated text, admin-editable). Matching is case-insensitive word-boundary match:
`preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $message)`.

**Block behavior**: Returns reason code `banned_word_match`. User-facing message is configurable
via `wpaic_security_banned_word_message` option (default: "Your message contains prohibited content.").

**Configuration**: `wpaic_moderation_banned_words_enabled` (bool, default true).

**Source anchor**: `axiachat-ai/includes/class-aichat-ajax.php` lines 186-210 banned word check.

---

### Layer 5: AI Moderation

**Purpose**: Use the active AI provider's moderation API (if available) to classify content.

**Implementation**: Calls the provider's moderation endpoint if the provider supports it.
For OpenAI: `POST https://api.openai.com/v1/moderations`. Other providers: skipped if no
moderation API available (layer treated as pass).

**Block behavior**: If moderation API returns `flagged = true`, block with reason code
`ai_moderation_flagged`. Categories from the API response are included in metadata (debug mode).

**Failure behavior**: If the moderation API call fails (network error, timeout), the layer PASSES
(fail-open). This prevents AI API outages from blocking all user messages. The failure is logged
as a warning in the structured logger.

**Configuration**: `wpaic_moderation_ai_enabled` (bool, default false -- opt-in due to API cost).
Extension hook: `wpaic_moderation_ai_result` filter allows custom moderation logic injection.

**Source anchor**: `axiachat-ai/includes/moderation.php` OpenAI moderation API integration.

---

## 5. Moderator Class Interface

```php
namespace WPAIC\Security;

class Moderator {

    /**
     * Run the full moderation pipeline against a message.
     *
     * @param string $message       Raw user message (pre-sanitized).
     * @param array  $context       Request context: ['ip' => string, 'request_data' => array].
     * @param string $provider_slug Active provider (for Layer 5 moderation API selection).
     * @return Moderation_Result    Pass or Block result.
     */
    public static function moderate(
        string $message,
        array $context,
        string $provider_slug = ''
    ): Moderation_Result;

    /**
     * Check if a specific layer is enabled.
     *
     * @param string $layer  Layer name: 'honeypot'|'rate_limit'|'spam'|'banned_words'|'ai'.
     * @return bool
     */
    public static function is_layer_enabled( string $layer ): bool;
}
```

---

## 6. Pipeline Execution Flow

```
Moderator::moderate($message, $context, $provider_slug):

  Layer 1: Honeypot
    -> if is_layer_enabled('honeypot') and $context['request_data']['wpaic_hp'] != '':
         return Moderation_Result::block('honeypot', 'honeypot_filled', ...)

  Layer 2: Rate Limit
    -> if is_layer_enabled('rate_limit'):
         $result = rate_limiter->check('wpaic_chat', $context['ip'])
         if $result->exceeded: return Moderation_Result::block('rate_limit', 'rate_limit_exceeded', ...)

  Layer 3: Spam Signature
    -> if is_layer_enabled('spam'):
         $hash = md5(strtolower(trim($message)))
         if in_array($hash, $spam_hashes) or apply_filters('wpaic_moderation_spam_check', false, $message):
           return Moderation_Result::block('spam', 'spam_signature_match', ...)

  Layer 4: Banned Words
    -> if is_layer_enabled('banned_words'):
         foreach $banned_words as $word:
           if preg_match('/\b' . preg_quote($word) . '\b/iu', $message):
             return Moderation_Result::block('banned_words', 'banned_word_match', ...)

  Layer 5: AI Moderation
    -> if is_layer_enabled('ai') and provider_has_moderation($provider_slug):
         $flagged = call_moderation_api($message, $provider_slug)  // fail-open on error
         if $flagged: return Moderation_Result::block('ai', 'ai_moderation_flagged', ...)

  return Moderation_Result::pass()
```

---

## 7. Admin Configuration Surface

The moderation pipeline configuration lives on the Security admin page (`admin.php?page=uwpaic-security`):

| Setting | Admin Field | Type | Default |
|---------|-------------|------|---------|
| `wpaic_moderation_honeypot_enabled` | Honeypot toggle | bool | true |
| `wpaic_moderation_rate_limit_enabled` | Rate limiting toggle | bool | true |
| `wpaic_security_rate_limit_requests` | Requests per window | int (1-200) | 20 |
| `wpaic_security_rate_limit_window` | Window (seconds) | int (10-3600) | 60 |
| `wpaic_moderation_spam_enabled` | Spam signature toggle | bool | true |
| `wpaic_moderation_akismet_enabled` | Akismet integration | bool | false |
| `wpaic_security_spam_hashes` | Spam hash list | JSON | [] |
| `wpaic_moderation_banned_words_enabled` | Banned words toggle | bool | true |
| `wpaic_security_banned_words` | Banned word list | textarea | '' |
| `wpaic_security_banned_word_message` | Block message shown to user | text | default |
| `wpaic_moderation_ai_enabled` | AI moderation toggle | bool | false |
| `wpaic_security_rate_limit_bypass_ips` | Bypass IP list | text | '' |

---

## 8. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| 5-layer moderation sequence | `axiachat-ai/includes/class-aichat-ajax.php` lines 131-210 | Adopted verbatim; extracted to class |
| Honeypot HTML field in template | `axiachat-ai/includes/shortcode.php` honeypot field | Adopted |
| OpenAI moderation API call | `axiachat-ai/includes/moderation.php` | Adopted; fail-open behavior added |
| Banned word regex word-boundary | `axiachat-ai/includes/moderation.php` | Adopted |
| Rate limit via transients | `lib/rate-limiter.php` | Integrated (existing infrastructure) |
| Akismet integration hook | `mxchat-basic/includes/class-mxchat-public.php` | Reference only (deferred to Phase 09) |
