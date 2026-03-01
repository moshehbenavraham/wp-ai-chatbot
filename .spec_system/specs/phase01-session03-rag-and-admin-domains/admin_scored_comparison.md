# Admin Scored Comparison

**Session ID**: `phase01-session03-rag-and-admin-domains`
**Domain**: `DOM-ADMIN`
**Context Pack**: `CP-P01-S03-002`
**Scoring Rubric**: `specs/phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
**Created**: 2026-03-01

---

## Scoring Scale Reference

| Score | Meaning |
|---|---|
| 0 | Not present or unusable |
| 1 | Bare-minimum with major limits |
| 2 | Partial with clear constraints |
| 3 | Solid for common use cases |
| 4 | Strong with low friction |
| 5 | Best-in-class among reference plugins |

**Weighted Total**: `(coverage * 0.30) + (reliability * 0.20) + (security * 0.20) + (operability * 0.15) + (extensibility * 0.15)`

---

## Capability 1: Settings Architecture

Comparison of settings page organization, option storage, and navigation patterns.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-ADM-001` | `SRC-AXI` | 4 | 4 | 4 | 4 | 3 | 3.85 | `adopt` |
| `FEAT-MXC-ADM-001` | `SRC-MXC` | 5 | 4 | 4 | 4 | 3 | 4.15 | `adopt` |
| `FEAT-BAA-ADM-001` | `SRC-BAA` | 3 | 3 | 3 | 3 | 4 | 3.15 | `investigate` |

### Evidence Notes

#### FEAT-AXI-ADM-001 - SRC-AXI

- Coverage: 4 - Single page with 5 tabs. Full WP Settings API. ~35 options with explicit type/sanitize/default. Individual wp_options rows. Clean separation via tab partials.
- Reliability: 4 - Standard WP Settings API handles option registration, save, and validation lifecycle. Nonce via settings_fields().
- Security: 4 - WP Settings API automatic nonce handling. capability check in page render. Standard options.php form action.
- Operability: 4 - Tab-based navigation. Clear organization. Partials per tab. Standard WP admin pattern.
- Extensibility: 3 - 2 pre-update filters. Tab partials modular. But no hook for adding custom tabs or settings sections externally.

- Weighted Summary: 3.85 (adopt)
- Recommendation: adopt
- Residual Risks: Limited extensibility for third-party additions compared to SRC-BAA's hook-based approach.

#### FEAT-MXC-ADM-001 - SRC-MXC

- Coverage: 5 - 6 admin pages. 80+ options in main array plus 3 separate groups. Full Settings API with sections and fields. Template-based page rendering. Sidebar navigation. Most comprehensive settings surface.
- Reliability: 4 - WP Settings API lifecycle. Template-based rendering prevents rendering errors. Page-specific asset loading.
- Security: 4 - Settings API nonce handling. capability checks on all pages. check_admin_referer on forms.
- Operability: 4 - Multi-page organization. Sidebar navigation for quick access. Dedicated pages per concern (Knowledge, Transcripts, Actions). Template includes.
- Extensibility: 3 - Template-based pages. But monolithic class (9036 lines) limits structural extension. Minimal custom hooks. Adding admin surfaces requires editing main class.

- Weighted Summary: 4.15 (adopt - broadest settings surface)
- Recommendation: adopt
- Residual Risks: Monolithic admin class is maintenance burden. 80+ options may overwhelm users.

#### FEAT-BAA-ADM-001 - SRC-BAA

- Coverage: 3 - Single page with 6 tabs. ~30 options. Manual update_option() (no WP Settings API). Tab partials from admin/tabs/. Individual wp_options rows.
- Reliability: 3 - Manual form handling is simpler but misses WP Settings API validation lifecycle. Form POST to self works but less standard.
- Security: 3 - Nonce via check_admin_referer. wp_nonce_field in form. But missing current_user_can() in settings page itself. Relies on menu registration for access control.
- Operability: 3 - Tab navigation. Clean layout. But fewer features means less to manage. localStorage tab persistence.
- Extensibility: 4 - Best extensibility via 10+ explicit hooks: baachal_settings_tabs (add custom tabs), baachal_settings_tab_content (render custom content), baachal_before/after hooks. Per-setting filters. Most extensible of the three despite simplest architecture.

- Weighted Summary: 3.15 (investigate)
- Recommendation: investigate
- Residual Risks: No WP Settings API means missing standard validation/sanitization lifecycle. Missing capability check in page file.

---

## Capability 2: Option Management

Comparison of option validation, sanitization, and security.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-ADM-002` | `SRC-AXI` | 4 | 4 | 5 | 4 | 4 | 4.20 | `adopt` |
| `FEAT-MXC-ADM-002` | `SRC-MXC` | 5 | 4 | 3 | 4 | 3 | 3.95 | `adopt` |
| `FEAT-BAA-ADM-002` | `SRC-BAA` | 3 | 3 | 3 | 3 | 2 | 2.85 | `investigate` |

### Evidence Notes

#### FEAT-AXI-ADM-002 - SRC-AXI

- Coverage: 4 - Per-field sanitize callbacks. Custom setting getter with registered defaults and auto-decryption. Whitelist validation for behaviors. Range validation for limits.
- Reliability: 4 - Custom aichat_get_setting() fallback: stored value -> registered default -> empty string. Auto-assignment logic for global bot on save.
- Security: 5 - API key encryption at rest via AES-256-GCM. Per-value random IVs and authentication tags. Key from AICHAT_MASTER_KEY or WP salts. Graceful degradation without OpenSSL. Best-in-class API key security.
- Operability: 4 - Transparent encryption/decryption. Setting getter abstracts storage details. Standard WP Settings API patterns.
- Extensibility: 4 - Custom setting getter extensible. Pre-update filters for validation hooks. Encryption infrastructure reusable.

- Weighted Summary: 4.20 (adopt - best security)
- Recommendation: adopt
- Residual Risks: OpenSSL required for encryption (graceful degradation to plaintext). Encryption key derived from WP salts if no constant defined.

#### FEAT-MXC-ADM-002 - SRC-MXC

- Coverage: 5 - Comprehensive field-by-field sanitizer for 80+ fields. Range enforcement. Callback function whitelist. Multiple sanitization methods per field type. Most thorough field coverage.
- Reliability: 4 - WP Settings API validation lifecycle. Transient-based error notices. Embedding generation failure tracking.
- Security: 3 - Thorough field sanitization. Nonce and capability checks on AJAX. But API keys stored as plaintext in wp_options (no encryption). direct wp_verify_nonce without wp_unslash in some places. DIM-SECURITY below adopt threshold.
- Operability: 4 - 70+ configurable options. Range sliders. Dropdown selectors. Real-time feedback on AJAX save.
- Extensibility: 3 - Options-based configuration. But monolithic sanitizer. No per-option filter hooks.

- Weighted Summary: 3.95 (adopt - but security concern)
- Recommendation: adopt
- Residual Risks: Plaintext API key storage is significant security gap. Monolithic sanitizer hard to extend.

#### FEAT-BAA-ADM-002 - SRC-BAA

- Coverage: 3 - Inline per-field sanitization. API key format validation via regex. Provider whitelist. Numeric range clamping. String length limits.
- Reliability: 3 - Manual validation works for current field set. Error notices inline via echo. No transient-based notices.
- Security: 3 - Regex API key validation. sanitize_text_field throughout. Nonce check. But no API key encryption. Missing current_user_can in page file. Inline error notices without proper escaping.
- Operability: 3 - Simple form with clear validation feedback. Success/error notices.
- Extensibility: 2 - Per-setting filters exist. But manual validation means adding fields requires editing the form handler.

- Weighted Summary: 2.85 (investigate)
- Recommendation: investigate
- Residual Risks: No encryption. Missing capability checks. Inline validation harder to maintain than declarative.

---

## Capability 3: Diagnostics and Health

Comparison of debugging, connectivity testing, and operational monitoring.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-ADM-003` | `SRC-AXI` | 3 | 4 | 4 | 4 | 3 | 3.55 | `investigate` |
| `FEAT-MXC-ADM-003` | `SRC-MXC` | 5 | 4 | 3 | 5 | 3 | 4.10 | `adopt` |
| `FEAT-BAA-ADM-003` | `SRC-BAA` | 2 | 3 | 3 | 2 | 2 | 2.40 | `reject` |

### Evidence Notes

#### FEAT-AXI-ADM-003 - SRC-AXI

- Coverage: 3 - Debug toggle, log viewer via AJAX (tail), log clearing. Supports general and AI-specific log streams. No provider connectivity testing, no health dashboard, no analytics.
- Reliability: 4 - Log viewer with spinner feedback. Clear log with confirmation. AJAX error handling with catch.
- Security: 4 - Capability check on log access. Debug gated behind constant. No sensitive data in displayed logs.
- Operability: 4 - In-page log viewer with refresh. Clear, simple UX for debugging. But limited scope.
- Extensibility: 3 - Debug toggle extensible. But no hook for adding diagnostic panels or custom health checks.

- Weighted Summary: 3.55 (investigate - narrow scope)
- Recommendation: investigate
- Residual Risks: Limited diagnostic breadth. No provider connectivity testing. No analytics.

#### FEAT-MXC-ADM-003 - SRC-MXC

- Coverage: 5 - Streaming test (actual SSE against provider APIs). Vector Store connection test. Slack connection test with scope verification. Frontend debugger toggle. Debug mode. Transcript analytics with 7-day charts (Chart.js). Transient-based admin notices. Most comprehensive diagnostics.
- Reliability: 4 - Real API connectivity testing catches configuration issues before runtime. SSE parsing validation. Detailed error events per failure mode.
- Security: 3 - Diagnostics AJAX handlers have nonce/capability checks. But streaming test sends API key to provider (necessary but security-relevant). Error responses may include provider error details.
- Operability: 5 - One-click provider connectivity testing. Visual analytics dashboard. Frontend debugger for client-side issues. Transient notices for async feedback. Best operational visibility.
- Extensibility: 3 - Analytics data via SQL queries. But no hook for custom diagnostic panels or health checks.

- Weighted Summary: 4.10 (adopt - best diagnostics)
- Recommendation: adopt
- Residual Risks: Streaming test requires valid API key and credits. Analytics limited to transcripts.

#### FEAT-BAA-ADM-003 - SRC-BAA

- Coverage: 2 - Debug mode checkbox (console-level only). No connectivity testing. No health checks. No analytics. No log viewer.
- Reliability: 3 - Debug toggle works. Simple and predictable.
- Security: 3 - No security concerns with minimal diagnostics. Debug mode is console-only.
- Operability: 2 - Debug toggle is useful but insufficient. No way to verify configuration is correct before use.
- Extensibility: 2 - No hooks for extending diagnostics.

- Weighted Summary: 2.40 (reject)
- Recommendation: reject
- Residual Risks: No operational visibility. Configuration errors only discovered at runtime.

---

## Capability 4: Operational Controls

Comparison of usage limits, moderation, compliance, and administrative features.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-ADM-004` | `SRC-AXI` | 4 | 4 | 5 | 4 | 4 | 4.20 | `adopt` |
| `FEAT-MXC-ADM-004` | `SRC-MXC` | 5 | 4 | 3 | 4 | 3 | 3.95 | `adopt` |
| `FEAT-BAA-ADM-003` | `SRC-BAA` | 2 | 3 | 3 | 2 | 2 | 2.40 | `reject` |

### Evidence Notes

#### FEAT-AXI-ADM-004 - SRC-AXI

- Coverage: 4 - Global bot enable/disable. Usage limits (daily total + per-user). Content moderation (banned IPs/words, rejection message, external moderation). GDPR consent overlay. Add-on toggles. Security policy with reset. Embed origins whitelist. Comprehensive for single-bot use case.
- Reliability: 4 - Auto-slug-assignment on bot enable. Pre-update filters validate state. Whitelist behaviors for consistent state.
- Security: 5 - Embed origins whitelist prevents unauthorized embedding. Security policy customizable with reset-to-defaults. GDPR consent overlay. Content moderation prevents abuse. Best security controls.
- Operability: 4 - All controls in Settings tabs. Clear organization. Toggle-based activation.
- Extensibility: 4 - Pre-update filters. Add-on toggles for modular activation. Security policy filter.

- Weighted Summary: 4.20 (adopt - best security controls)
- Recommendation: adopt
- Residual Risks: Single-bot limitation (no multi-bot). Add-on toggles require actual add-on code.

#### FEAT-MXC-ADM-004 - SRC-MXC

- Coverage: 5 - License system. Multi-bot support. Per-role rate limits. Post type visibility. Email gating. Chat persistence. WooCommerce integration. Privacy/Complianz. Auto-delete transcripts. Auto-email transcripts. Transcript translation. Content generation. CSV export. 70+ options. Most comprehensive operational controls.
- Reliability: 4 - Cron-based auto-delete. License domain verification. Multi-bot configuration isolation.
- Security: 3 - Per-role rate limits. Privacy integration. But license verification sends domain to external API. Plaintext API keys.
- Operability: 4 - Dedicated admin pages per concern. Rich configuration. But 70+ options can overwhelm.
- Extensibility: 3 - License-gated features. Multi-bot via options. But no hook for adding custom operational controls.

- Weighted Summary: 3.95 (adopt)
- Recommendation: adopt
- Residual Risks: License dependency on external API. Option count may overwhelm. Plaintext keys.

#### FEAT-BAA-ADM-003 - SRC-BAA (reused for operational controls)

- Coverage: 2 - Chatbot enable/disable. Conversation memory with configurable limit. Preserve data on uninstall. Provider switching. Content indexing/auto-index toggle. No usage limits, no moderation, no GDPR, no transcripts, no multi-bot.
- Reliability: 3 - Simple toggles work reliably. Conversation memory configurable.
- Security: 3 - Basic controls. No moderation or abuse prevention.
- Operability: 2 - Minimal controls distributed across tabs. No dedicated operational page.
- Extensibility: 2 - Per-setting filters. baachal_save_additional_settings action. But limited scope.

- Weighted Summary: 2.40 (reject)
- Recommendation: reject
- Residual Risks: No usage limits or moderation. No compliance features. Insufficient for production deployment.

---

## Capability 5: Admin UX

Comparison of JavaScript interaction quality and user experience.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-ADM-005` | `SRC-AXI` | 3 | 4 | 4 | 4 | 4 | 3.70 | `investigate` |
| `FEAT-MXC-ADM-005` | `SRC-MXC` | 5 | 4 | 3 | 5 | 3 | 4.10 | `adopt` |
| `FEAT-BAA-ADM-004` | `SRC-BAA` | 3 | 3 | 3 | 3 | 3 | 3.00 | `investigate` |

### Evidence Notes

#### FEAT-AXI-ADM-005 - SRC-AXI

- Coverage: 3 - Tab switching, API key toggle, log viewer, policy reset, plugin install flow. Clean but focused scope. 255 lines.
- Reliability: 4 - Vanilla JS IIFE with modular init functions. fetch() with error handling. Spinner feedback.
- Security: 4 - No jQuery dependency reduces attack surface. Uses wp.i18n for translations. fetch() with proper headers.
- Operability: 4 - Clean, fast interactions. No unnecessary complexity. Good UX for what it covers.
- Extensibility: 4 - Modular init functions. Vanilla JS is easier to maintain and extend. No framework lock-in.

- Weighted Summary: 3.70 (investigate - narrow but high quality)
- Recommendation: investigate
- Residual Risks: Limited feature coverage. May need expansion for additional capabilities.

#### FEAT-MXC-ADM-005 - SRC-MXC

- Coverage: 5 - AJAX auto-save with per-field debouncing. AI model selector modal with search. OpenRouter browser. Embedding model selector. API key toggles. Post type controls. Color picker. Intent modals. Notice dismissal. Vector store management. 3824 lines. Most feature-rich.
- Reliability: 4 - Visual feedback (spinners, checkmarks) on every save. Debounced saves prevent rapid-fire requests.
- Security: 3 - jQuery dependency (larger attack surface). wp_localize_script for nonce passing. But complex modals with dynamic HTML generation.
- Operability: 5 - Auto-save eliminates manual save button workflow. Real-time feedback on every change. Model browsing. Best admin UX.
- Extensibility: 3 - Page-specific loading. wp_localize_script for data. But jQuery coupling and monolithic JS structure limit clean extension.

- Weighted Summary: 4.10 (adopt - best admin UX)
- Recommendation: adopt
- Residual Risks: 3824 lines is large. jQuery dependency may conflict with WP modernization. Complex modal logic.

#### FEAT-BAA-ADM-004 - SRC-BAA

- Coverage: 3 - Reindexing, test search, cache clearing, tab navigation, color picker, provider switching. 292 lines. Focused but adequate.
- Reliability: 3 - jQuery-based AJAX with nonce. Basic error handling. localStorage for tab persistence.
- Security: 3 - Nonce-protected AJAX. Standard jQuery patterns.
- Operability: 3 - Tab persistence across page loads (localStorage + hash). Provider switching auto-updates visible fields. Functional.
- Extensibility: 3 - Event-driven jQuery. Reasonable structure. No framework lock-in concerns.

- Weighted Summary: 3.00 (investigate)
- Recommendation: investigate
- Residual Risks: Basic UX may not meet growing feature needs. No auto-save.

---

## Aggregate Domain Scores

### Per-Source Weighted Averages (across all 5 scored capabilities)

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Aggregate Weighted | Band |
|---|---|---|---|---|---|---|---|
| `SRC-AXI` | 3.6 | 4.0 | 4.4 | 4.0 | 3.6 | 3.90 | `adopt` |
| `SRC-MXC` | 5.0 | 4.0 | 3.2 | 4.4 | 3.0 | 4.05 | `adopt` |
| `SRC-BAA` | 2.6 | 3.0 | 3.0 | 2.6 | 2.6 | 2.76 | `reject` |

**Notes on aggregation**: Averages computed across 5 capabilities. SRC-MXC leads in coverage (5.0) and operability (4.4) due to 6-page admin with 80+ options, auto-save, connectivity testing, and analytics. SRC-AXI leads in security (4.4) with AES-256-GCM API key encryption and comprehensive security controls. SRC-BAA is below threshold with minimal diagnostics, no WP Settings API, and missing capability checks. The mixed leadership (MXC for breadth, AXI for security) suggests an adopt_with_modifications outcome.
