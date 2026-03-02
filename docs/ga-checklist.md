# WP AI Chatbot — GA Readiness Checklist

> Version 1.0.2 · Last updated 2026-03-02

All items must be checked before publishing to the WordPress.org plugin directory.

---

## Core Functionality

- [x] Plugin activates cleanly on WordPress 6.0+ with PHP 7.4+
- [x] Plugin deactivates without errors
- [x] Plugin uninstalls cleanly (removes all DB tables and options via `uninstall.php`)
- [x] Autoloader resolves all `WPAIC\*` classes from `includes/`
- [x] DB tables created via `dbDelta()` on activation (7 MVP tables)
- [x] Migration registry runs semver-based DB migrations on version change
- [x] Cron events scheduled on activation, cleared on deactivation

## AI Provider Integration

- [x] OpenAI Chat Completions — sync + SSE streaming
- [x] Anthropic Claude Messages API — sync + SSE streaming
- [x] Google Gemini — sync + SSE streaming
- [x] DeepSeek — sync (OpenAI-compatible)
- [x] xAI Grok — sync (OpenAI-compatible)
- [x] Provider validation REST endpoint (`/providers/validate`)
- [x] API keys encrypted at rest (AES-256-GCM via `Settings_Manager`)
- [x] Exponential backoff on provider errors
- [x] Cost calculation in microcents per model

## Chat Widget

- [x] Floating mode (launcher button + expandable panel)
- [x] Inline mode (shortcode embed)
- [x] Multiple widgets per page (instance counter)
- [x] SSE streaming with ReadableStream (AJAX fallback)
- [x] Session persistence via localStorage + cookie fallback
- [x] GDPR consent banner (optional, configurable)
- [x] **AI disclosure notice (EU AI Act Art. 50 / CA SB 1001)** — non-dismissible
- [x] Honeypot spam protection
- [x] XSS-safe HTML sanitization (DOMParser allowlist)
- [x] Mobile full-height sheet (`< 640px`)
- [x] `prefers-reduced-motion` CSS rule
- [x] Rate limit: widget stays visible, input disabled, message shown
- [x] Rate limit fallback contacts (email / phone / URL) shown in transcript

## Security

- [x] Nonce verification on all AJAX + REST endpoints
- [x] IP-based rate limiting (transient-based, proxy-aware)
- [x] **`wpaic_rate_limit_message` option** — configurable, not hardcoded
- [x] Keyword moderation (blocklist filter chain `wpaic_moderate_message`)
- [x] SSRF protection on webhook tool URLs (RFC-1918 blocked)
- [x] AES-256-GCM encryption for sensitive fields (API keys)
- [x] No raw IPs, no message content, no keys in audit logs
- [x] Audit log EVENT_* constants (15 events)

## GDPR / Privacy

- [x] WP personal data exporters registered (sessions + leads)
- [x] WP personal data erasers registered (sessions + leads)
- [x] Chat session retention: configurable (default 90 days), auto-purged daily
- [x] `wpaic_data_retention_days = 0` retains indefinitely
- [x] Retention notice displayed in Security > Privacy tab
- [ ] 5-year consent record retention (server-side) — **DEFERRED** (see known-limitations.md)

## Legal Compliance

- [x] AI disclosure: `wpaic_ai_disclosure_enabled` option
- [x] AI disclosure: `wpaic_ai_disclosure_text` option (configurable, non-dismissible)
- [x] EU AI Act Article 50 compliance path documented
- [x] CA SB 1001 compliance path documented

## Knowledge Base (RAG)

- [x] Content_Source interface + registry
- [x] Embedding_Provider interface + registry
- [x] Posts/Pages content source
- [x] OpenAI embedding adapter
- [x] Chunker (paragraph → sentence → word fallback, configurable overlap)
- [x] Knowledge index (upsert, delete, cosine similarity search)
- [x] Ingestion pipeline (batch processing, atomic claim, stall recovery)
- [x] Retrieval engine wired to `wpaic_rag_context` hook

## Tools & Function Calling

- [x] Tool_Interface + registry + executor
- [x] Tool continuation loop (MAX_LOOPS=5)
- [x] Webhook tool (SSRF-protected, static params, 30s timeout max)
- [x] MCP client + registry + tool adapter (MCP 2025-06-18)
- [x] Tools disabled by default (`wpaic_tools_enabled = 0`)
- [x] Streaming auto-disabled when tools are active

## Admin Pages

- [x] General Settings (provider, API keys, model, compliance)
- [x] Knowledge Base
- [x] Tools (webhooks, MCP servers)
- [x] Security & Privacy (rate limiting, retention, moderation)
- [x] Diagnostics (audit logs, health checks, system info)
- [x] Migration (axiachat-ai, mxchat-basic, baachal)

## Migration

- [x] axiachat-ai migrator (6 mappings)
- [x] mxchat-basic migrator (10 mappings)
- [x] baachal migrator (13 mappings, provider normalization)
- [x] Dry-run mode (no data written)
- [x] Backup before live run (max 5 backups per source)
- [x] Rollback capability

## Code Quality

- [x] PHP-CS-Fixer (PSR-12) — no errors
- [x] PHPStan level 0 — baseline tracked, new code clean
- [x] Biome JS linter — no errors
- [x] Pre-commit hooks (Husky + lint-staged)

## CI/CD Pipeline

- [x] Code quality workflow (Biome, PHP-CS-Fixer, PHPStan)
- [x] Test workflow (PHPUnit + Vitest with coverage)
- [x] Security workflow (Gitleaks, CodeQL JS, dependency-review, npm/composer audit)
- [x] Integration workflow (WP + MySQL, plugin activation, REST smoke tests)
- [x] Release workflow (tag `v*` → ZIP → GitHub Release)

## Observability

- [x] Structured logger (PHP + JS, daily log files)
- [x] 30-day log rotation (daily cron prunes `logs/app-*.log`)
- [x] Health endpoint (`/wp-json/wp-ai-chatbot/v1/health`)
- [x] Rate limiter (`lib/rate-limiter.php`)
- [x] Backup manager (`lib/backup-manager.php`)

## Documentation

- [x] Admin Guide (`docs/admin-guide.md`)
- [x] Migration Guide (`docs/migration-guide.md`)
- [x] Known Limitations (`docs/known-limitations.md`)
- [x] GA Checklist (this file)
- [x] CHANGELOG.md

## Completed Post-GA Phases

- [x] Design system — tokens, palettes, typography (Phase 12)
- [x] Interaction patterns — toasts, modals, drawers, validation (Phase 13)
- [x] WCAG 2.2 AA — keyboard nav, focus traps, ARIA, responsive (Phase 14)
- [x] PDF + web scraper content sources (Phase 15)
- [x] Transcripts + usage dashboard + action catalog (Phase 16)
- [x] Advanced integrations — macro router, MCP auth, bot tools (Phase 17)
- [x] Unit test suite — 168 PHP tests, 279 assertions (Phase 18)
- [x] Integration tests + JS tests — 215 PHP / 26 JS total (Phase 19)
- [x] Logging foundation + tool call history (Phase 20)
- [x] Final quality pass + operations CI (Phase 21)

## Deferred (Not Blocking GA)

- [ ] 5-year server-side GDPR consent records (see known-limitations.md)
- [ ] Appointment scheduling (Bookly/Amelia integration)
- [ ] Translation toolbar
- [ ] Usage chart/graph visualization
- [ ] Full tools x roles permission matrix

---

## Sign-Off

| Area | Status | Notes |
|------|--------|-------|
| Core / Bootstrap | ✅ Ready | |
| Provider Integration | ✅ Ready | |
| Chat Widget | ✅ Ready | |
| Security / Privacy | ✅ Ready | 5-year consent records deferred |
| Legal / Compliance | ✅ Ready | EU AI Act Art. 50 & CA SB 1001 addressed |
| RAG Pipeline | ✅ Ready | PDF + web scraper included |
| Tools / MCP | ✅ Ready | Macro router + MCP auth included |
| Admin UI | ✅ Ready | All 9 admin pages complete |
| Migration | ✅ Ready | |
| CI/CD | ✅ Ready | 6 workflows + Dependabot |
| Testing | ✅ Ready | 215 PHP + 26 JS tests |
| Documentation | ✅ Ready | |
