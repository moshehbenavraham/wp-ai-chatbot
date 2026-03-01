# Session Model Contract

**Session**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Module**: `includes/runtime/class-session-manager.php`
**Resolves**: CFI-RUN-002 (unified session storage -- consumed from session 02)

---

## 1. Overview

The session model defines how chat sessions are created, resumed, validated, stored, and cleaned up. It consumes the `wp_wpaic_sessions` and `wp_wpaic_messages` table schemas from session 02 and exposes a PHP interface for the runtime pipeline.

The design unifies three divergent source patterns:
- AxiaChat: Implicit sessions via per-message `session_id` column, UUID-based tokens
- MxChat: Cookie-based per-bot sessions with transient history storage
- Baachal: localStorage sessions with Custom Post Type conversations

### Reference Anchors

| Source Plugin | File | Lines | Relevance |
|---------------|------|-------|-----------|
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 55-80 | Session ID validation, UUID v4 generation |
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 340-342 | History assembly for provider context |
| MxChat | `mxchat-basic/js/chat-script.js` | 47-56 | Cookie-based per-bot session management |
| Baachal | `baachal/assets/chatbot.js` | 6-20 | localStorage session persistence |
| Baachal | `baachal/assets/chatbot.js` | 441-468 | History loading from server |

---

## 2. Session Lifecycle States

### 2.1 State Diagram

```
                  create()
    [none] ----------------------> [active]
                                    |    |
                          close()   |    | expire (cron)
                                    v    v
                               [closed] [expired]
```

### 2.2 State Definitions

| State | ENUM Value | Description | Allowed Transitions |
|-------|-----------|-------------|---------------------|
| Active | `'active'` | Session accepting messages | -> closed, -> expired |
| Closed | `'closed'` | Explicitly ended by user or admin | Terminal (no transitions) |
| Expired | `'expired'` | Exceeded TTL without activity | Terminal (no transitions) |

### 2.3 Transition Rules

| Transition | Trigger | Actor | Side Effects |
|-----------|---------|-------|-------------|
| none -> active | New chat initiated | Pipeline (step 1.4) | Insert row, fire `wpaic_session_created` |
| active -> closed | User clicks "New Conversation" or admin deletes | Frontend / Admin API | Update status, fire `wpaic_session_closed` |
| active -> expired | `expires_at < NOW()` | WP-Cron cleanup job | Update status, fire `wpaic_session_expired` |

### 2.4 Expiry Policy

- **Default TTL**: 24 hours from last activity (`updated_at + 24h`)
- **Configurable**: Via `wpaic_runtime_session_ttl` option (seconds, default 86400)
- **Renewal**: Every message updates `updated_at` and extends `expires_at`
- **Cleanup frequency**: WP-Cron event `wpaic_cleanup_expired_sessions` runs hourly

```php
// On session creation:
$expires_at = gmdate( 'Y-m-d H:i:s', time() + (int) get_option( 'wpaic_runtime_session_ttl', 86400 ) );

// On each message (Stage 4.6):
$expires_at = gmdate( 'Y-m-d H:i:s', time() + (int) get_option( 'wpaic_runtime_session_ttl', 86400 ) );
// UPDATE wp_wpaic_sessions SET updated_at = NOW(), expires_at = $expires_at WHERE id = $id
```

---

## 3. Session Token Format

### 3.1 Token Specification

| Property | Value |
|----------|-------|
| Format | UUID v4 (RFC 4122) |
| Length | 36 characters (with dashes) |
| Character set | `[0-9a-f-]` |
| Generation | `wp_generate_uuid4()` (WordPress built-in) |
| Validation regex | `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i` |

### 3.2 Token Storage (Frontend)

The frontend stores the session token in `localStorage` with a cookie fallback:

| Storage | Key | TTL | Scope |
|---------|-----|-----|-------|
| Primary: localStorage | `wpaic_session_{bot_slug}` | Until cleared | Per-origin, per-bot |
| Fallback: cookie | `wpaic_sid_{bot_slug}` | 24 hours | Per-domain, per-bot, `SameSite=Lax` |

**Per-bot scoping**: Each bot gets its own session token, enabling multi-bot on the same page (MxChat pattern).

**Why localStorage primary**: Survives page navigation without server round-trips, larger storage budget than cookies, not sent with every HTTP request (privacy). Cookie fallback handles localStorage-disabled browsers.

Ref: MXC cookie-based sessions (lines 47-56), BAA localStorage sessions (lines 6-20).

---

## 4. Ownership Validation

### 4.1 Dual Strategy

Session ownership uses two validation paths based on authentication state:

| User Type | Validation Fields | Rationale |
|-----------|------------------|-----------|
| Authenticated (`user_id > 0`) | `session_token` + `user_id` | IP may change on mobile; user_id is stable |
| Anonymous (`user_id = 0`) | `session_token` + `ip_address` + `user_agent_hash` | No user_id; fingerprint provides ownership signal |

### 4.2 Validation Logic

```php
public function validate_ownership( $session, $request_context ) {
    if ( $session['user_id'] > 0 ) {
        // Authenticated: token + user_id
        return $session['user_id'] === $request_context['user_id'];
    }
    // Anonymous: token + IP + user-agent hash
    $ip_match = $session['ip_address'] === $request_context['ip_address'];
    $ua_match = $session['user_agent_hash'] === $request_context['user_agent_hash'];
    return $ip_match && $ua_match;
}
```

### 4.3 IP Address Handling

```php
public function get_client_ip() {
    // Proxy-aware, matching lib/rate-limiter.php
    $headers = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
    foreach ( $headers as $header ) {
        $ip = $_SERVER[ $header ] ?? '';
        if ( ! empty( $ip ) ) {
            // X-Forwarded-For may contain multiple IPs; take the first (client IP)
            $ip = explode( ',', $ip )[0];
            $ip = trim( $ip );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}
```

**Storage**: IP stored as `VARBINARY(16)` via `inet_pton()` (supports IPv4 and IPv6).

### 4.4 User-Agent Hashing

```php
$user_agent_hash = hash( 'sha256', $_SERVER['HTTP_USER_AGENT'] ?? '' );
```

Stored as `VARCHAR(64)` in `wp_wpaic_sessions.user_agent_hash`.

### 4.5 Failure Behavior

On ownership validation failure:
- Return 403 error: `"Session access denied."`
- Do NOT reveal which field mismatched (prevents enumeration)
- Log the failure via structured logger if debug mode is enabled

Ref: Session 02 `wp_wpaic_sessions` ownership columns (2.3).

---

## 5. Session CRUD Operations

### 5.1 Interface Contract

```php
interface Session_Manager_Interface {

    /**
     * Create a new session.
     *
     * @param string $bot_slug   Bot identifier.
     * @param array  $context    Request context (user_id, ip, user_agent, page_id).
     * @return array Session data array with id, session_token, status.
     */
    public function create( $bot_slug, $context );

    /**
     * Resume an existing active session.
     *
     * @param string $session_token UUID session token.
     * @param array  $context       Request context for ownership validation.
     * @return array|null Session data array or null if not found/expired/invalid.
     */
    public function resume( $session_token, $context );

    /**
     * Close a session (mark as closed).
     *
     * @param int $session_id Internal session ID.
     * @return bool True on success.
     */
    public function close( $session_id );

    /**
     * Get conversation history for a session.
     *
     * @param int $session_id Internal session ID.
     * @param int $limit      Maximum messages to retrieve.
     * @return array Array of message rows ordered chronologically.
     */
    public function get_history( $session_id, $limit = 20 );

    /**
     * Delete a session and its messages (admin only).
     *
     * @param int $session_id Internal session ID.
     * @return bool True on success.
     */
    public function delete( $session_id );
}
```

### 5.2 Create

```php
public function create( $bot_slug, $context ) {
    $bot = wpaic_get_bot_by_slug( $bot_slug );
    if ( ! $bot ) {
        return null;
    }

    $session_token = wp_generate_uuid4();
    $ttl           = (int) get_option( 'wpaic_runtime_session_ttl', 86400 );
    $now           = current_time( 'mysql', true );

    $data = array(
        'session_token'   => $session_token,
        'user_id'         => $context['user_id'] ?? 0,
        'bot_id'          => $bot['id'],
        'ip_address'      => inet_pton( $context['ip_address'] ),
        'user_agent_hash' => $context['user_agent_hash'],
        'status'          => 'active',
        'provider'        => $bot['provider'],
        'model'           => $bot['model'],
        'page_id'         => $context['page_id'] ?? null,
        'created_at'      => $now,
        'updated_at'      => $now,
        'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
    );

    $this->wpdb->insert( $this->sessions_table, $data );
    $session_id = $this->wpdb->insert_id;

    do_action( 'wpaic_session_created', $session_id, $session_token, $bot['id'] );

    return array_merge( $data, array( 'id' => $session_id ) );
}
```

### 5.3 Resume

```php
public function resume( $session_token, $context ) {
    $session = $this->wpdb->get_row(
        $this->wpdb->prepare(
            "SELECT * FROM {$this->sessions_table} WHERE session_token = %s AND status = 'active'",
            $session_token
        ),
        ARRAY_A
    );

    if ( ! $session ) {
        return null;
    }

    // Check expiry
    if ( $session['expires_at'] && strtotime( $session['expires_at'] ) < time() ) {
        $this->expire( $session['id'] );
        return null;
    }

    // Validate ownership
    if ( ! $this->validate_ownership( $session, $context ) ) {
        return null;
    }

    return $session;
}
```

### 5.4 Close

```php
public function close( $session_id ) {
    $result = $this->wpdb->update(
        $this->sessions_table,
        array( 'status' => 'closed', 'updated_at' => current_time( 'mysql', true ) ),
        array( 'id' => $session_id )
    );

    if ( $result ) {
        do_action( 'wpaic_session_closed', $session_id );
    }

    return (bool) $result;
}
```

### 5.5 Get History

```php
public function get_history( $session_id, $limit = 20 ) {
    return $this->wpdb->get_results(
        $this->wpdb->prepare(
            "SELECT role, message, response, created_at
             FROM {$this->messages_table}
             WHERE session_id = %d
             ORDER BY id ASC
             LIMIT %d",
            $session_id,
            $limit
        ),
        ARRAY_A
    );
}
```

**Note**: Uses covering index `idx_session_id (session_id, id)` for efficient retrieval.

### 5.6 Delete (Admin Only)

```php
public function delete( $session_id ) {
    // Delete messages first (referential integrity)
    $this->wpdb->delete( $this->messages_table, array( 'session_id' => $session_id ) );

    // Delete session
    $result = $this->wpdb->delete( $this->sessions_table, array( 'id' => $session_id ) );

    return (bool) $result;
}
```

---

## 6. Session Expiry and Cleanup

### 6.1 WP-Cron Event

```php
// Registered during plugin activation:
if ( ! wp_next_scheduled( 'wpaic_cleanup_expired_sessions' ) ) {
    wp_schedule_event( time(), 'hourly', 'wpaic_cleanup_expired_sessions' );
}

add_action( 'wpaic_cleanup_expired_sessions', 'wpaic_run_session_cleanup' );
```

### 6.2 Cleanup Logic

```php
function wpaic_run_session_cleanup() {
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'wpaic_sessions';
    $messages_table = $wpdb->prefix . 'wpaic_messages';
    $now = current_time( 'mysql', true );

    // Find expired sessions (batch of 100)
    $expired = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$sessions_table}
             WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < %s
             LIMIT 100",
            $now
        )
    );

    if ( empty( $expired ) ) {
        return;
    }

    // Mark as expired
    $ids = implode( ',', array_map( 'intval', $expired ) );
    $wpdb->query(
        "UPDATE {$sessions_table} SET status = 'expired', updated_at = '{$now}' WHERE id IN ({$ids})"
    );

    foreach ( $expired as $session_id ) {
        do_action( 'wpaic_session_expired', (int) $session_id );
    }
}
```

### 6.3 Data Retention

The cleanup cron marks sessions as expired but does NOT delete data. A separate retention policy (configurable via `wpaic_admin_data_retention_days` option, default 90 days) handles permanent deletion:

```php
// Separate cron: wpaic_purge_old_sessions (daily)
function wpaic_run_session_purge() {
    global $wpdb;
    $retention_days = (int) get_option( 'wpaic_admin_data_retention_days', 90 );
    $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

    // Delete old messages
    $wpdb->query( $wpdb->prepare(
        "DELETE m FROM {$messages_table} m
         INNER JOIN {$sessions_table} s ON m.session_id = s.id
         WHERE s.status IN ('closed','expired') AND s.updated_at < %s
         LIMIT 1000",
        $cutoff
    ) );

    // Delete old sessions (only after messages are gone)
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$sessions_table}
         WHERE status IN ('closed','expired') AND updated_at < %s
         AND id NOT IN (SELECT DISTINCT session_id FROM {$messages_table})
         LIMIT 1000",
        $cutoff
    ) );
}
```

Ref: Session 02 `data_retention_policy.md`.

---

## 7. History Retrieval for Provider Context

### 7.1 Configurable Depth

```php
$max_messages = (int) $bot['max_messages']; // Default 20, from wp_wpaic_bots
$history = $session_manager->get_history( $session['id'], $max_messages );
```

### 7.2 Message Format for Provider

History rows are transformed to the provider message format:

```php
$provider_history = array();
foreach ( $history as $row ) {
    $provider_history[] = array( 'role' => 'user', 'content' => $row['message'] );
    if ( ! empty( $row['response'] ) ) {
        $provider_history[] = array( 'role' => 'assistant', 'content' => $row['response'] );
    }
}
```

### 7.3 Token Budget Awareness

The runtime does NOT truncate history based on token count (that is provider-specific). Instead, `max_messages` is the configurable limit. Providers handle token overflow via their own max_tokens logic (session 03).

---

## 8. Token and Cost Accumulation

### 8.1 Per-Message Tracking

On each provider response, token usage and cost are stored in `wp_wpaic_messages`:

| Column | Source |
|--------|--------|
| `prompt_tokens` | `$result['usage']['prompt_tokens']` |
| `completion_tokens` | `$result['usage']['completion_tokens']` |
| `cost_micros` | `$provider->calculate_cost( $usage, $model )` |

### 8.2 Session Aggregate Updates

Session totals are updated atomically via `UPDATE ... SET col = col + ?` (no race conditions):

```sql
UPDATE wp_wpaic_sessions SET
    total_prompt_tokens = total_prompt_tokens + ?,
    total_completion_tokens = total_completion_tokens + ?,
    total_cost_micros = total_cost_micros + ?,
    message_count = message_count + 1,
    updated_at = ?,
    expires_at = ?
WHERE id = ?
```

### 8.3 Daily Aggregation

The `wpaic_after_provider_request` action fires cost tracking to `wp_wpaic_usage_daily`:

```php
wpaic_track_usage( array(
    'provider'          => $result['provider_id'],
    'model'             => $result['model_id'],
    'prompt_tokens'     => $result['usage']['prompt_tokens'] ?? 0,
    'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
    'cost_micros'       => $cost_micros ?? 0,
) );
```

Ref: Session 03 `cost_accounting_interface.md` UPSERT pattern.

---

## 9. Hooks

| Hook | Type | Arguments | Purpose |
|------|------|-----------|---------|
| `wpaic_session_created` | action | `$session_id, $token, $bot_id` | After session insert |
| `wpaic_session_closed` | action | `$session_id` | After session close |
| `wpaic_session_expired` | action | `$session_id` | After expiry marking |
| `wpaic_resolve_session` | filter | `$session, $token, $bot, $ctx` | Override session resolution |
| `wpaic_history_messages` | filter | `$history, $session, $bot` | Modify history before provider |

---

## 10. Source Comparison Summary

| Aspect | AxiaChat | MxChat | Baachal | WPAIC |
|--------|----------|--------|---------|-------|
| Storage | DB table (implicit session) | wp_options transients | Custom Post Type | Dedicated `wp_wpaic_sessions` table |
| Token format | UUID v4 | Random 32-char hex | `chat_` + UUID | UUID v4 |
| Frontend storage | Cookie (30-day) | Cookie per-bot (24h) | localStorage | localStorage + cookie fallback |
| Multi-bot | Per-bot slug | Per-bot ID | Single bot | Per-bot slug |
| Ownership | IP in conversations table | IP/user binding | None | IP + user_agent_hash + user_id |
| Token tracking | Per-message columns | None | None | Per-message + session aggregates |
| Expiration | None | Transient TTL | None | `expires_at` column + WP-Cron |
| History depth | Configurable `max_messages` | Full session | Full session | Configurable `max_messages` (bot setting) |

---
