# Structured Logging Integration Specification

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

The existing `lib/structured-logger.php` (PHP) is the single logging backend for all plugin
subsystems. No subsystem implements its own file logging. This document specifies how each
subsystem integrates with the logger: subsystem tags, log levels per event type, and rotation
hooks.

`lib/structured-logger.js` is used only in Node.js tooling (test runner, build scripts) and
is NOT available in WordPress runtime.

---

## 2. Logger Integration API

```php
// lib/structured-logger.php public interface:
$logger = new Structured_Logger( 'wpaic', WPAIC_PLUGIN_DIR . 'logs/' );
$logger->info( 'message', [ 'context' => 'array' ] );
$logger->warn( 'message', [ 'context' => 'array' ] );
$logger->error( 'message', [ 'context' => 'array' ] );
$logger->debug( 'message', [ 'context' => 'array' ] );  // Only writes if WPAIC_DEBUG = true
```

The logger instance is initialized in `includes/class-plugin.php` and stored as a singleton
accessible via `WPAIC\Plugin::get_logger()`. No subsystem creates its own logger instance.

---

## 3. Subsystem Tag Map

Each log entry includes a `subsystem` field for filtering in the log viewer.

| Subsystem Tag | Module | Types of Events |
|--------------|--------|----------------|
| `bootstrap` | `class-plugin.php` | Plugin init, module load order, version checks |
| `provider` | `includes/provider/` | Provider dispatch, model resolution, retry, cost |
| `runtime` | `includes/runtime/` | Pipeline stage entry/exit, session create/resume, stream events |
| `session` | `class-session-manager.php` | Session create, expire, close, cleanup |
| `stream` | `class-stream-handler.php` | SSE connect, chunk count, disconnect, errors |
| `rag` | `includes/rag/` | Ingestion queue, chunk generation, retrieval, context assembly |
| `embedding` | `class-embedding-client.php` | Embed API call, batch size, dimension |
| `migration` | `includes/migration/` | Schema migrations, option migrations, lifecycle hooks |
| `security` | `includes/security/` | Sanitization failures, moderation blocks, auth failures |
| `audit` | `class-audit-logger.php` | All EVENT_* constants (see privacy_audit_trail_spec.md) |
| `admin` | `includes/admin/` | Settings saves, page loads, AJAX admin calls |
| `queue` | `class-ingestion-pipeline.php` | Queue enqueue, process, complete, fail, stall |
| `health` | `lib/health-endpoint.php` | Health check results, degraded states |

---

## 4. Log Level Guidelines Per Subsystem

### 4.1 `error` level -- Always write regardless of WPAIC_DEBUG

- Provider API call fails after all retries exhausted
- OpenSSL unavailable (encryption degraded)
- DB write fails for session or message
- Migration runner throws exception
- Stream handler cannot open SSE connection

### 4.2 `warn` level -- Write always (not debug-gated)

- Provider fallback triggered (primary failed, fallback used)
- Rate limit exceeded for a visitor
- Nonce verification failed
- Capability check failed
- Queue item stalled (exceeded max_attempts threshold)
- AI moderation API call failed (fail-open triggered)
- Encryption key hash mismatch detected

### 4.3 `info` level -- Write always in production

- Session created or resumed
- Message pipeline completed successfully (with token count + cost)
- Migration callback executed successfully
- Data export or erasure completed
- Settings change by admin
- Provider API key set or cleared
- Queue batch processed (N items, N succeeded, N failed)

### 4.4 `debug` level -- Write only when `WPAIC_DEBUG = true`

- Each pipeline stage entry with payload size
- RAG retrieval query and result count
- Context assembly token budget calculation
- Embedding batch composition
- Sanitizer type invocations

---

## 5. Concrete Log Calls Per Subsystem

### 5.1 Runtime

```php
// Message pipeline completion (info):
$logger->info( 'message_pipeline_complete', [
    'subsystem'    => 'runtime',
    'session_id'   => $session_id,
    'provider'     => $provider_slug,
    'model'        => $model_slug,
    'prompt_tokens'=> $usage['prompt_tokens'],
    'compl_tokens' => $usage['completion_tokens'],
    'cost_usd'     => $cost,
    'latency_ms'   => $latency,
] );

// Pipeline stage (debug):
$logger->debug( 'pipeline_stage_enter', [
    'subsystem' => 'runtime',
    'stage'     => 'pre_process',
    'msg_len'   => strlen( $message ),
] );
```

### 5.2 Provider

```php
// Provider retry (warn):
$logger->warn( 'provider_retry', [
    'subsystem' => 'provider',
    'provider'  => $slug,
    'attempt'   => $attempt,
    'error'     => $error_code,
] );

// Fallback triggered (warn):
$logger->warn( 'provider_fallback', [
    'subsystem' => 'provider',
    'primary'   => $primary_slug,
    'fallback'  => $fallback_slug,
    'reason'    => $reason,
] );
```

### 5.3 RAG / Queue

```php
// Queue batch complete (info):
$logger->info( 'queue_batch_complete', [
    'subsystem' => 'queue',
    'processed' => $count,
    'succeeded' => $success_count,
    'failed'    => $fail_count,
    'duration_ms' => $duration,
] );

// Queue stall (warn):
$logger->warn( 'queue_item_stalled', [
    'subsystem'    => 'queue',
    'item_id'      => $item_id,
    'knowledge_id' => $knowledge_id,
    'attempts'     => $attempts,
] );
```

### 5.4 Security (via Audit Logger)

All `Audit_Logger::log()` calls are routed to `lib/structured-logger.php` with
`subsystem = 'audit'`. See `privacy_audit_trail_spec.md` for the full event taxonomy and schema.

---

## 6. Log Rotation

`lib/structured-logger.php` creates a new log file per day: `logs/app-YYYY-MM-DD.log`.
No additional rotation configuration is needed.

Log cleanup is handled by `Privacy_Manager::run_retention_cleanup()` on the
`wpaic_retention_cleanup` daily cron event. Files older than `wpaic_privacy_log_retention_days`
are deleted.

---

## 7. Error Capture Files

`lib/structured-logger.php` writes `logs/last_error_<timestamp>.json` for fatal errors.
These files have the AI-friendly JSON schema:
```json
{
  "timestamp": "...",
  "subsystem": "...",
  "message": "...",
  "trace": "...",
  "context": {}
}
```
Error capture files are NOT subject to log retention cleanup (kept until manually cleared).
They are accessible via the Diagnostics > Logs panel.

---

## 8. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Structured JSON logging per subsystem | `axiachat-ai/includes/logs.php` | Adopted; routed to existing lib/ |
| Log level guidance (error/warn/info) | `axiachat-ai/includes/logs.php` | Adopted; extended with subsystem tags |
| Daily log file rotation | `lib/structured-logger.php` (existing) | Integrated as-is |
| Error capture files | `lib/structured-logger.php` (existing) | Integrated as-is |
| Subsystem tagging in log entries | (new architecture pattern) | Net-new; no source equivalent |
