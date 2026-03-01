# Admin Decision Log

## Log Metadata

| Field | Value |
|---|---|
| `log_id` | `DL-P01-S03-ADM` |
| `session_id` | `phase01-session03-rag-and-admin-domains` |
| `phase` | `1` |
| `domain_scope` | `["DOM-ADMIN"]` |
| `created_date` | `2026-03-01` |
| `last_updated` | `2026-03-01` |
| `context_pack_id` | `CP-P01-S03-002` |

---

## Entry: `DEC-ADM-001`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-ADM-001` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Settings architecture` |
| `capability_summary` | Settings page organization, option storage patterns, navigation structure, and WP Settings API usage for admin configuration surfaces. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-ADM-AXI-001` | `axiachat-ai/includes/settings.php` | `FEAT-AXI-ADM-001` | `implemented` |
| `SRC-MXC` | `ANC-ADM-MXC-001` | `mxchat-basic/includes/class-mxchat-admin.php` | `FEAT-MXC-ADM-001` | `implemented` |
| `SRC-BAA` | `ANC-ADM-BAA-001` | `baachal/admin/settings-page.php` | `FEAT-BAA-ADM-001` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 4 | 4 | 3 | 3.85 |
| `SRC-MXC` | 5 | 4 | 4 | 4 | 3 | 4.15 |
| `SRC-BAA` | 3 | 3 | 3 | 3 | 4 | 3.15 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat has the broadest settings architecture with 6 admin pages, full WP Settings API, template-based page rendering, and sidebar navigation -- the most comprehensive admin surface. However, the monolithic 9036-line class is a structural liability. AxiaChat's single-page tabbed approach with WP Settings API is cleaner but less scalable. Baachal's hook-based extensibility (10+ hooks) is the best extensibility pattern despite bypassing WP Settings API. Modification: adopt MxChat's multi-page WP Settings API pattern with dedicated pages per concern, integrate Baachal's hook-based extensibility for third-party tab/section registration, and decompose the monolithic class into per-page controllers following AxiaChat's tab partial pattern. |
| `modification_notes` | Decompose monolithic admin class into per-page controller classes. Adopt SRC-BAA extensibility hooks (`baachal_settings_tabs`, `baachal_settings_tab_content`, per-setting filters). Use SRC-AXI's tab partial pattern within each page. Maintain SRC-MXC's sidebar navigation and multi-page organization. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-RAG", "DOM-PROVIDER", "DOM-RUNTIME"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `migration_notes` | Multi-page architecture defines navigation structure for all domain-specific settings. Pages per concern (Knowledge, Transcripts, Actions) map to DOM-RAG and DOM-RUNTIME surfaces. Decomposition of monolithic class is an architecture-phase task. |
| `carry_forward_ids` | `["CFI-ADM-001"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-ADM-002`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-ADM-002` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Option management` |
| `capability_summary` | Option validation, sanitization, secure storage (including API key encryption), and retrieval patterns for plugin configuration values. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-ADM-AXI-001` | `axiachat-ai/includes/settings.php` | `FEAT-AXI-ADM-002` | `implemented` |
| `SRC-MXC` | `ANC-ADM-MXC-001` | `mxchat-basic/includes/class-mxchat-admin.php` | `FEAT-MXC-ADM-002` | `implemented` |
| `SRC-BAA` | `ANC-ADM-BAA-001` | `baachal/admin/settings-page.php` | `FEAT-BAA-ADM-002` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 5 | 4 | 4 | 4.20 |
| `SRC-MXC` | 5 | 4 | 3 | 4 | 3 | 3.95 |
| `SRC-BAA` | 3 | 3 | 3 | 3 | 2 | 2.85 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat has the strongest option management with AES-256-GCM API key encryption at rest (per-value random IVs, authentication tags), custom `aichat_get_setting()` with auto-decryption and registered defaults fallback, and per-field sanitize callbacks. The security score (5) is best-in-class -- no other source encrypts API keys. MxChat has the broadest field coverage (80+ fields) with comprehensive field-by-field sanitization but stores API keys as plaintext. Modification: adopt SRC-AXI's encryption infrastructure and setting getter pattern, integrate SRC-MXC's breadth of field-by-field sanitization for the full option set, and add SRC-BAA's regex API key format validation as an additional pre-encryption check. |
| `modification_notes` | Apply SRC-AXI encryption to all API key fields from all domains. Port SRC-MXC's comprehensive sanitizer coverage for non-key fields. Add SRC-BAA's regex format validation for API key fields (Gemini, OpenAI sk-*, Claude sk-ant-*) before encryption. Ensure graceful degradation without OpenSSL (SRC-AXI pattern). |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-SECURITY", "DOM-PROVIDER"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `migration_notes` | Encryption infrastructure must handle migration from plaintext storage for existing installs. AICHAT_MASTER_KEY constant or WP salts derivation needs documentation. Provider domain API key fields are the primary consumers. |
| `carry_forward_ids` | `["CFI-ADM-002"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-ADM-003`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-ADM-003` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Diagnostics and health` |
| `capability_summary` | Debugging, connectivity testing, operational monitoring, analytics, and health check capabilities for admin operators. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-ADM-AXI-001` | `axiachat-ai/includes/settings.php` | `FEAT-AXI-ADM-003` | `implemented` |
| `SRC-MXC` | `ANC-ADM-MXC-001` | `mxchat-basic/includes/class-mxchat-admin.php` | `FEAT-MXC-ADM-003` | `implemented` |
| `SRC-BAA` | `ANC-ADM-BAA-001` | `baachal/admin/tabs/advanced.php` | `FEAT-BAA-ADM-003` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 3 | 4 | 4 | 4 | 3 | 3.55 |
| `SRC-MXC` | 5 | 4 | 3 | 5 | 3 | 4.10 |
| `SRC-BAA` | 2 | 3 | 3 | 2 | 2 | 2.40 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat has the most comprehensive diagnostics: actual SSE streaming test against provider APIs, Vector Store connection test, Slack connection test with scope verification, frontend debugger toggle, transcript analytics with 7-day Chart.js charts, and transient-based admin notices. The operability score (5) reflects one-click provider connectivity testing and visual analytics. AxiaChat adds a useful log viewer (AJAX tail with clear) and security-gated debug mode. Modification: adopt SRC-MXC's streaming test and analytics dashboard, integrate SRC-AXI's log viewer with capability-checked access, and add extensibility hooks for custom diagnostic panels (missing from all sources). |
| `modification_notes` | Port SRC-MXC streaming test and analytics charts. Integrate SRC-AXI log viewer with debug toggle and log clearing. Add diagnostic panel registration hook (new capability absent from all sources). Ensure connectivity tests do not expose API keys in error responses. Extend analytics beyond transcripts to include RAG pipeline metrics. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-PROVIDER", "DOM-RAG"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `migration_notes` | Streaming test requires valid provider API keys. Analytics data model (transcript tables) must be available. Log viewer requires log directory configuration. |
| `carry_forward_ids` | `["CFI-ADM-003"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-ADM-004`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-ADM-004` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Operational controls` |
| `capability_summary` | Usage limits, content moderation, compliance features (GDPR), licensing, multi-bot support, and administrative management capabilities. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-ADM-AXI-001` | `axiachat-ai/includes/settings.php` | `FEAT-AXI-ADM-004` | `implemented` |
| `SRC-MXC` | `ANC-ADM-MXC-001` | `mxchat-basic/includes/class-mxchat-admin.php` | `FEAT-MXC-ADM-004` | `implemented` |
| `SRC-BAA` | `ANC-ADM-BAA-001` | `baachal/admin/tabs/advanced.php` | `FEAT-BAA-ADM-003` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 5 | 4 | 4 | 4.20 |
| `SRC-MXC` | 5 | 4 | 3 | 4 | 3 | 3.95 |
| `SRC-BAA` | 2 | 3 | 3 | 2 | 2 | 2.40 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat has the strongest security controls: embed origins whitelist (prevents unauthorized embedding), customizable security policy with reset-to-defaults, GDPR consent overlay, content moderation (banned IPs/words, rejection message, external moderation toggle), and usage limits (daily total + per-user). The security score (5) is decisive. MxChat has the broadest operational scope (multi-bot, license system, per-role rate limits, auto-delete transcripts, WooCommerce integration, email gating) but stores API keys as plaintext and sends domain to external API for license verification. Modification: adopt SRC-AXI's security controls as the foundation, integrate SRC-MXC's multi-bot support, per-role rate limits, transcript management, and WooCommerce toggles without the external license dependency, and ensure all operational controls pass through security-audited code paths. |
| `modification_notes` | Port SRC-AXI embed origins whitelist, GDPR overlay, and content moderation to unified plugin. Integrate SRC-MXC multi-bot support and per-role rate limits without external license API. Port SRC-MXC transcript management (auto-delete, export) and WooCommerce integration toggles. Evaluate license system replacement in Phase 02 (remove external domain verification). |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-SECURITY", "DOM-RUNTIME", "DOM-RAG"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Multi-bot support from MxChat requires per-bot option isolation pattern. External license API dependency should be removed or internalized. Transcript auto-delete requires cron scheduling. GDPR overlay must comply with consent storage regulations. |
| `carry_forward_ids` | `["CFI-ADM-004"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-ADM-005`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-ADM-005` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Admin UX` |
| `capability_summary` | JavaScript interaction quality, user experience patterns, auto-save behavior, and admin interface responsiveness. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-ADM-AXI-001` | `axiachat-ai/assets/js/settings.js` | `FEAT-AXI-ADM-005` | `implemented` |
| `SRC-MXC` | `ANC-ADM-MXC-001` | `mxchat-basic/js/mxchat-admin.js` | `FEAT-MXC-ADM-005` | `implemented` |
| `SRC-BAA` | `ANC-ADM-BAA-001` | `baachal/assets/admin.js` | `FEAT-BAA-ADM-004` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 3 | 4 | 4 | 4 | 4 | 3.70 |
| `SRC-MXC` | 5 | 4 | 3 | 5 | 3 | 4.10 |
| `SRC-BAA` | 3 | 3 | 3 | 3 | 3 | 3.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat has the richest admin UX: AJAX auto-save with per-field debouncing and visual feedback (spinners, checkmarks), AI model selector modal with search, OpenRouter browser, embedding model selector, and extensive option controls. The operability score (5) reflects the best admin interaction quality. However, it uses jQuery (3824 lines) which increases attack surface and conflicts with WP modernization. AxiaChat's vanilla JS approach (255 lines, no jQuery dependency, uses fetch() and wp.i18n) is architecturally superior but has narrower coverage. Modification: adopt SRC-MXC's UX patterns (auto-save, model selectors, visual feedback) but rewrite in vanilla JS following SRC-AXI's modular IIFE pattern, eliminating jQuery dependency. Port SRC-BAA's localStorage tab persistence for cross-page state retention. |
| `modification_notes` | Rewrite SRC-MXC admin UX patterns in vanilla JS (SRC-AXI's approach). Port auto-save with per-field debouncing using native fetch(). Reimplement model selector modals without jQuery. Port SRC-BAA localStorage tab persistence. Use wp.i18n for all user-facing strings. Keep modular init pattern for page-specific JS loading. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-RAG", "DOM-PROVIDER"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `migration_notes` | jQuery to vanilla JS rewrite is a significant effort. Model selector and OpenRouter browser are complex UI components. Auto-save pattern needs careful debounce tuning. Page-specific JS loading must be maintained. |
| `carry_forward_ids` | `["CFI-ADM-005"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Carry-Forward Items

### CFI-ADM-001

| Field | Value |
|---|---|
| `item_id` | `CFI-ADM-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-ADMIN` |
| `description` | Decompose MxChat's monolithic admin class (9036 lines) into per-page controller classes following AxiaChat's tab partial pattern. Integrate Baachal's hook-based extensibility (`baachal_settings_tabs`, `baachal_settings_tab_content`, per-setting filters) for third-party extension points. Design multi-page navigation with sidebar. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-ADM-001` |
| `related_anchor_ids` | `["ANC-ADM-AXI-001", "ANC-ADM-MXC-001", "ANC-ADM-BAA-001"]` |
| `resolution_criteria` | Admin architecture has per-page controller classes, hook-based tab/section registration for extensions, and multi-page sidebar navigation. No single class exceeds 600 LOC. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-ADM-002

| Field | Value |
|---|---|
| `item_id` | `CFI-ADM-002` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-ADMIN` |
| `description` | Apply AES-256-GCM API key encryption to all provider key fields across domains. Port MxChat's comprehensive field-by-field sanitization for non-key fields. Add Baachal's regex API key format validation as pre-encryption check. Design migration path from plaintext to encrypted storage for existing installs. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-ADM-002` |
| `related_anchor_ids` | `["ANC-ADM-AXI-001", "ANC-ADM-MXC-001", "ANC-ADM-BAA-001"]` |
| `resolution_criteria` | All API keys encrypted at rest with AES-256-GCM. Custom setting getter auto-decrypts. Graceful degradation without OpenSSL. Migration handles plaintext-to-encrypted upgrade. Format validation catches malformed keys before storage. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-ADM-003

| Field | Value |
|---|---|
| `item_id` | `CFI-ADM-003` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-ADMIN` |
| `description` | Design unified diagnostics dashboard combining MxChat's streaming test and analytics with AxiaChat's log viewer. Add diagnostic panel registration hook (absent from all sources). Extend analytics beyond transcripts to RAG pipeline metrics. Ensure connectivity tests do not expose API keys in error responses. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-ADM-003` |
| `related_anchor_ids` | `["ANC-ADM-AXI-001", "ANC-ADM-MXC-001"]` |
| `resolution_criteria` | Diagnostics page includes provider connectivity testing, log viewer, analytics dashboard, and extensible panel registration. Error responses sanitized of API keys. RAG pipeline metrics (index status, queue health) visible. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-ADM-004

| Field | Value |
|---|---|
| `item_id` | `CFI-ADM-004` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-ADMIN` |
| `description` | Unify operational controls: port AxiaChat's embed origins whitelist, GDPR overlay, and content moderation. Integrate MxChat's multi-bot support and per-role rate limits without external license dependency. Port transcript management (auto-delete, export) and WooCommerce integration toggles. Evaluate license system replacement. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-ADM-004` |
| `related_anchor_ids` | `["ANC-ADM-AXI-001", "ANC-ADM-MXC-001"]` |
| `resolution_criteria` | Unified plugin has embed origins whitelist, GDPR consent, content moderation, multi-bot support, per-role rate limits, transcript management, and WooCommerce toggles. No external license API dependency. All controls pass security audit. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-ADM-005

| Field | Value |
|---|---|
| `item_id` | `CFI-ADM-005` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-ADMIN` |
| `description` | Rewrite MxChat's admin UX patterns (auto-save, model selectors, visual feedback) in vanilla JS following AxiaChat's modular IIFE pattern. Eliminate jQuery dependency. Port localStorage tab persistence from Baachal. Use wp.i18n for all user-facing strings. Maintain page-specific JS loading. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-ADM-005` |
| `related_anchor_ids` | `["ANC-ADM-AXI-001", "ANC-ADM-MXC-001", "ANC-ADM-BAA-001"]` |
| `resolution_criteria` | Admin JS uses vanilla JS (no jQuery). Auto-save with per-field debouncing via native fetch(). Model selector modals reimplemented without jQuery. Tab persistence via localStorage. wp.i18n for translations. Page-specific loading maintained. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

---

## Cross-Domain Interaction Notes

### DOM-ADMIN x DOM-RAG

**Interaction Points (T015)**:

1. **Ingestion triggers**: Admin UI initiates content ingestion -- SRC-AXI's context create/modify pages trigger per-post indexing; SRC-MXC's Knowledge page provides queue processing start/stop/retry controls; SRC-BAA's Content tab has a reindex button. The unified plugin needs an admin surface to trigger, pause, and monitor ingestion across all source types.

2. **Index status and monitoring**: Admin must display current index state -- SRC-MXC shows entry counts with 3-second polling, queue progress with per-item status, and error summary tables; SRC-AXI shows per-context document counts; SRC-BAA shows post count only. RAG pipeline health (indexed count, queue depth, last error) should appear in admin diagnostics (CFI-ADM-003).

3. **Chunking configuration**: Chunk size and overlap are RAG parameters exposed via admin UI -- SRC-MXC has admin-settable chunk size (1000-10000 chars); SRC-AXI uses fixed 1000-word/180-word overlap. Per DEC-RAG-002, the unified chunker needs admin-configurable chunk size and overlap fields in the RAG settings page.

4. **Retrieval tuning**: Admin controls RAG retrieval behavior -- SRC-AXI has per-context retrieval mode selection (local/Pinecone/page/auto) and similarity threshold; SRC-MXC has similarity threshold (10-95%) and RAG source count (3-10). These RAG tuning parameters need admin UI fields with appropriate range validation (DEC-ADM-002 sanitization).

5. **Embedding provider selection**: Embedding provider is selected via admin UI -- SRC-AXI stores per-context embedding provider preference; SRC-MXC has bot-specific embedding model selection. This overlaps with DOM-PROVIDER API key management (DEC-ADM-002) and requires the admin to show only providers with valid API keys.

6. **Knowledge entry management**: Admin provides CRUD for knowledge base entries -- SRC-MXC has grouped chunk display, pagination, search/filter, bulk delete, and role-based access restrictions; SRC-AXI has per-context document management with add/remove. Entry management is an admin page that operates directly on RAG storage.

7. **Auto-sync controls**: Admin toggles automatic RAG synchronization -- SRC-MXC has auto-sync on post update/delete with ACF `save_post` integration; SRC-BAA has auto-index toggle. Admin operational controls (DEC-ADM-004) must include RAG auto-sync configuration.

8. **Security policy injection**: Admin-configurable security policy text (SRC-AXI) is injected into RAG context assembly (DEC-RAG-004). The admin settings page for security policy directly affects RAG prompt construction. Customizable text with reset-to-defaults is an admin operational control that produces a RAG behavioral output.

### DOM-ADMIN x DOM-PROVIDER

- API key management is a core admin function: storage (DEC-ADM-002 encryption), validation (regex format checking), and connectivity testing (DEC-ADM-003 streaming test).
- Provider model selection UIs (AI model selector, OpenRouter browser, embedding model selector) are admin-surface controls from SRC-MXC.
- Provider switching (show/hide relevant API key fields per selected provider) is an admin UX concern from SRC-BAA.
- Agency proxy mode configuration is an admin setting (SRC-AXI).

### DOM-ADMIN x DOM-RUNTIME

- Chat configuration (bot enable/disable, chat persistence, conversation memory limits) are admin operational controls that affect runtime behavior.
- Usage limits (daily total, per-user) from SRC-AXI admin settings are enforced at runtime.
- Per-role rate limits from SRC-MXC admin settings gate runtime message flow.
- Post type visibility controls from SRC-MXC determine where the chatbot widget renders at runtime.
- Email gating from SRC-MXC determines whether runtime requires email before chat.

### DOM-ADMIN x DOM-SECURITY

- API key encryption (DEC-ADM-002) is the primary DOM-ADMIN x DOM-SECURITY intersection.
- Embed origins whitelist (SRC-AXI) prevents unauthorized embedding of the chat widget.
- Content moderation settings (banned IPs, banned words) are admin-managed security controls.
- GDPR consent overlay is an admin-configurable compliance control.
- Security policy customization (SRC-AXI) is an admin surface for DOM-SECURITY behavior.
- Capability checks (`manage_options`, `current_user_can`) enforce access control on admin surfaces.

---

## Decision Summary

| Decision ID | Capability | Winner | Outcome | Band | Score |
|---|---|---|---|---|---|
| `DEC-ADM-001` | Settings architecture | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 4.15 |
| `DEC-ADM-002` | Option management | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.20 |
| `DEC-ADM-003` | Diagnostics and health | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 4.10 |
| `DEC-ADM-004` | Operational controls | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.20 |
| `DEC-ADM-005` | Admin UX | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 4.10 |

### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | 5 |
| Adopt-As-Is | 0 |
| Adopt-With-Modifications | 5 |
| Reject/Defer | 0 |
| Domain Winner | Mixed: `SRC-MXC` (3 of 5 capabilities), `SRC-AXI` (2 of 5 capabilities) |
| Dominant Maturity | `production_stable` |
| Key Risks | Monolithic admin class decomposition; jQuery to vanilla JS rewrite; API key encryption migration; external license API removal; multi-bot option isolation |
| Cross-Domain Dependencies | `DOM-RAG`, `DOM-PROVIDER`, `DOM-RUNTIME`, `DOM-SECURITY` |
| Carry-Forward Items | `CFI-ADM-001`, `CFI-ADM-002`, `CFI-ADM-003`, `CFI-ADM-004`, `CFI-ADM-005` |
