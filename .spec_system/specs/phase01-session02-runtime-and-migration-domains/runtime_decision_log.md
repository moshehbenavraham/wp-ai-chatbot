# Runtime Decision Log

## Log Metadata

| Field | Value |
|---|---|
| `log_id` | `DL-P01-S02-RUN` |
| `session_id` | `phase01-session02-runtime-and-migration-domains` |
| `phase` | `1` |
| `domain_scope` | `["DOM-RUNTIME"]` |
| `created_date` | `2026-03-01` |
| `last_updated` | `2026-03-01` |
| `context_pack_id` | `CP-P01-S02-001` |

---

## Entry: `DEC-RUN-001`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RUN-001` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Message lifecycle and request pipeline` |
| `capability_summary` | End-to-end message handling from user input through pre-processing, provider dispatch, response formatting, and JSON return with error handling and extensibility hooks. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RUN-AXI-001` | `axiachat-ai/includes/class-aichat-ajax.php` | `FEAT-AXI-RUN-001` | `implemented` |
| `SRC-MXC` | `ANC-RUN-MXC-001` | `mxchat-basic/includes/class-mxchat-integrator.php` | `FEAT-MXC-RUN-001` | `implemented` |
| `SRC-BAA` | `ANC-RUN-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-RUN-001` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 4 | 4 | 5 | 4.45 |
| `SRC-MXC` | 4 | 3 | 4 | 3 | 3 | 3.50 |
| `SRC-BAA` | 3 | 3 | 3 | 3 | 4 | 3.20 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's message pipeline is the most complete: pre-processing filter chain (captcha, honeypot, rate limiting, moderation), provider dispatch via registry, tool-pending handshake for advanced AI models, response processing with markdown and HTML sanitization, and the most extensive hook system. SRC-MXC adds contextual awareness (page content injection) and dual-mode handling (standard + streaming) which should be integrated. SRC-BAA's `baachal_custom_message_handler` pattern (complete handler replacement) is a valuable extensibility concept to adopt. |
| `modification_notes` | Adopt SRC-AXI pipeline architecture. Integrate SRC-MXC contextual awareness (page content/title injection) as an optional pre-processing step. Add streaming entry point alongside standard AJAX handler (from SRC-MXC). Adopt SRC-BAA's complete-handler-replacement hook pattern as an additional extensibility mechanism. |
| `carry_forward_ids` | `["CFI-RUN-001"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-PROVIDER", "DOM-SECURITY", "DOM-ADMIN"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | SRC-MXC's monolithic integrator must be decomposed to extract runtime pipeline logic. SRC-BAA's simple handler will be replaced by the registry-dispatched pipeline. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-RUN-002`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RUN-002` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Session handling and conversation state` |
| `capability_summary` | Management of chat sessions including storage, retrieval, ownership validation, persistence modes, and multi-bot isolation. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RUN-AXI-001` | `axiachat-ai/includes/class-aichat-ajax.php` | `FEAT-AXI-RUN-002` | `implemented` |
| `SRC-MXC` | `ANC-RUN-MXC-001` | `mxchat-basic/includes/class-mxchat-integrator.php` | `FEAT-MXC-RUN-002` | `implemented` |
| `SRC-BAA` | `ANC-RUN-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-RUN-002` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 3 | 4 | 3 | 3.65 |
| `SRC-MXC` | 5 | 4 | 5 | 3 | 3 | 4.20 |
| `SRC-BAA` | 4 | 3 | 3 | 3 | 4 | 3.45 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat's session management leads on security (session ownership validation with IP/user binding and automatic reset) and coverage (per-bot isolation, persistence modes, chat mode tracking). This is the only source with anti-session-hijacking controls. SRC-AXI's conversation logging (tokens/costs) and configurable history limits should be integrated. SRC-BAA's CPT-based storage pattern is interesting but not scalable; however, its hook system for session events should be adopted. |
| `modification_notes` | Adopt SRC-MXC session ownership validation as the security baseline. Replace WP options storage with a dedicated session table (combining SRC-AXI's conversation table with SRC-MXC's session concept). Integrate SRC-AXI's token/cost tracking per session. Add session lifecycle hooks from SRC-BAA pattern (before_save, should_save, conversation_history filter). Consider session expiration/cleanup mechanism (none of the sources implement this). |
| `carry_forward_ids` | `["CFI-RUN-002"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-SECURITY", "DOM-ADMIN", "DOM-MIGRATION"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Session storage change (options to table) requires migration planning. Existing SRC-MXC sessions in options would need migration to new table format. SRC-BAA CPT conversations would need import path. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-RUN-003`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RUN-003` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Streaming and real-time delivery` |
| `capability_summary` | Server-Sent Events streaming from backend to frontend with provider-specific chunk parsing, incremental DOM updates, and non-streaming fallback. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RUN-AXI-001` | `axiachat-ai/includes/class-aichat-ajax.php` | `FEAT-AXI-RUN-003` | `na_no_matching_implementation` |
| `SRC-MXC` | `ANC-RUN-MXC-001` | `mxchat-basic/includes/class-mxchat-integrator.php` | `FEAT-MXC-RUN-003` | `implemented` |
| `SRC-BAA` | `ANC-RUN-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-RUN-003` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 0 | 0 | 0 | 0 | 0 | 0.00 |
| `SRC-MXC` | 5 | 4 | 3 | 4 | 3 | 3.95 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat is the only source with streaming implementation. The full SSE pipeline (cURL WRITEFUNCTION backend, Fetch API frontend, provider-specific parsing for 5 providers, non-streaming fallback) is production-proven. This capability is critical for user experience -- streaming reduces perceived response time from seconds to sub-second for first token. The main modification needed is extraction from the monolithic integrator class into a modular streaming transport layer. |
| `modification_notes` | Extract SRC-MXC streaming logic into a standalone transport module. Integrate with SRC-AXI provider registry (each adapter exposes streaming endpoint). Add response sanitization during streaming transit (currently only sanitized after accumulation). Ensure streaming transport works with SRC-AXI's tool-pending handshake (non-streaming fallback for tool rounds). |
| `carry_forward_ids` | `["CFI-RUN-003"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-PROVIDER"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `migration_notes` | Streaming is a new capability for the combined plugin's runtime pipeline. Links to CFI-PRO-001 (SSE integration into per-adapter architecture from Session 01). |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-RUN-004`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RUN-004` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Frontend state management and UX runtime` |
| `capability_summary` | Client-side chat widget management including session handling, DOM manipulation, streaming UI, input handling, and extensibility hooks. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RUN-AXI-001` | `axiachat-ai/assets/js/aichat-frontend.js` | `FEAT-AXI-RUN-004` | `implemented` |
| `SRC-MXC` | `ANC-RUN-MXC-001` | `mxchat-basic/js/chat-script.js` | `FEAT-MXC-RUN-004` | `implemented` |
| `SRC-BAA` | `ANC-RUN-BAA-001` | `baachal/assets/chatbot.js` | `FEAT-BAA-RUN-004` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 3 | 4 | 3 | 3.65 |
| `SRC-MXC` | 5 | 4 | 4 | 4 | 3 | 4.15 |
| `SRC-BAA` | 3 | 3 | 4 | 3 | 5 | 3.50 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat's frontend is the most feature-complete: multi-bot instance management, real-time streaming DOM updates, file upload, popular question chips, robust error handling with status code mapping. SRC-AXI adds useful UX patterns (progressive thinking stages, suggestion chips, draggable widget). SRC-BAA's extensive JS hook system is the most extensible and should be adopted as the hook architecture for the combined frontend. |
| `modification_notes` | Adopt SRC-MXC frontend architecture for streaming and multi-bot support. Integrate SRC-AXI's progressive thinking stages as a UX enhancement. Adopt SRC-BAA's JS hook system as the extensibility layer (before_send, response_received, filter_bot_response, widget lifecycle hooks). Replace jQuery dependency with vanilla JS or maintain jQuery for WP compatibility. Add SRC-BAA's DOMParser sanitization with safe protocol whitelist. |
| `carry_forward_ids` | `["CFI-RUN-004"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `migration_notes` | Frontend rewrite combining 3 sources. Session storage changes (cookie vs localStorage) need unified approach. Admin-configured widget options must drive frontend rendering. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Carry-Forward Items

### CFI-RUN-001

| Field | Value |
|---|---|
| `item_id` | `CFI-RUN-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session02-runtime-and-migration-domains` |
| `domain_id` | `DOM-RUNTIME` |
| `description` | Integrate SRC-MXC contextual awareness (page content/title injection) and SRC-BAA complete-handler-replacement hook into SRC-AXI message pipeline architecture. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-RUN-001` |
| `related_anchor_ids` | `["ANC-RUN-AXI-001", "ANC-RUN-MXC-001", "ANC-RUN-BAA-001"]` |
| `resolution_criteria` | Message pipeline supports optional page context injection and provides a complete-handler-replacement hook alongside granular filter hooks. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-RUN-002

| Field | Value |
|---|---|
| `item_id` | `CFI-RUN-002` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session02-runtime-and-migration-domains` |
| `domain_id` | `DOM-RUNTIME` |
| `description` | Design unified session storage (dedicated table vs options vs CPT) with SRC-MXC ownership validation, SRC-AXI token/cost tracking, SRC-BAA lifecycle hooks, and session expiration/cleanup mechanism. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-RUN-002` |
| `related_anchor_ids` | `["ANC-RUN-AXI-001", "ANC-RUN-MXC-001", "ANC-RUN-BAA-001"]` |
| `resolution_criteria` | Session system uses dedicated storage with ownership validation, token tracking, configurable retention, and lifecycle hooks. Migration paths from all 3 source formats documented. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-RUN-003

| Field | Value |
|---|---|
| `item_id` | `CFI-RUN-003` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session02-runtime-and-migration-domains` |
| `domain_id` | `DOM-RUNTIME` |
| `description` | Extract SRC-MXC streaming transport into modular layer integrated with SRC-AXI provider registry. Add transit-time response sanitization and tool-pending handshake compatibility. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-RUN-003` |
| `related_anchor_ids` | `["ANC-RUN-MXC-001", "ANC-PRO-AXI-001"]` |
| `resolution_criteria` | Streaming works as modular transport layer called by provider adapters. Non-streaming fallback for tool rounds. Response chunks sanitized during transit. Links to CFI-PRO-001. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-RUN-004

| Field | Value |
|---|---|
| `item_id` | `CFI-RUN-004` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session02-runtime-and-migration-domains` |
| `domain_id` | `DOM-RUNTIME` |
| `description` | Design combined frontend architecture with SRC-MXC streaming/multi-bot, SRC-AXI thinking stages/suggestions, and SRC-BAA JS hook system. Evaluate jQuery dependency vs vanilla JS. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-RUN-004` |
| `related_anchor_ids` | `["ANC-RUN-AXI-001", "ANC-RUN-MXC-001", "ANC-RUN-BAA-001"]` |
| `resolution_criteria` | Frontend supports streaming, multi-bot, progressive feedback, and exposes hook API for third-party customization. HTML sanitization uses DOMParser with safe protocol whitelist. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

---

## Cross-Domain Interaction Notes

### DOM-RUNTIME x DOM-PROVIDER

- Runtime pipeline dispatches to provider adapters via registry (DEC-PRO-001 from Session 01). The adapter interface contract defines the boundary.
- Streaming delivery (DEC-RUN-003) requires coordination with per-adapter streaming endpoints (CFI-PRO-001 from Session 01). Runtime owns SSE transport; provider owns chunk format parsing.
- Tool-pending handshake (SRC-AXI) requires runtime pipeline to detect tool state and manage continuation flow with the provider adapter.

### DOM-RUNTIME x DOM-SECURITY

- Session ownership validation (DEC-RUN-002) is a security control scored under runtime but governed by security policy.
- Nonce verification, input sanitization, output sanitization are runtime-embedded security controls. Session 04 (DOM-SECURITY) should evaluate these as part of trust boundary scoring.
- Rate limiting hooks in the message pipeline connect to infrastructure rate limiter (`lib/rate-limiter.php`).

### DOM-RUNTIME x DOM-ADMIN

- Widget configuration (colors, position, labels, feature toggles) flows from admin settings to frontend runtime via `wp_localize_script()`.
- Conversation logging and token/cost tracking (DEC-RUN-002) produces data consumed by admin dashboards.
- Bot-specific options (multi-bot) are admin-configured but runtime-consumed.

### DOM-RUNTIME x DOM-MIGRATION

- Session storage format changes (DEC-RUN-002) create migration requirements. Moving from WP options to dedicated table requires data migration.
- Streaming capability addition changes frontend asset requirements -- migration must handle JS asset version changes.
- See DOM-MIGRATION decision log for bidirectional dependency analysis.

---

## Decision Summary

| Decision ID | Capability | Winner | Outcome | Band | Score |
|---|---|---|---|---|---|
| `DEC-RUN-001` | Message lifecycle and request pipeline | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.45 |
| `DEC-RUN-002` | Session handling and conversation state | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 4.20 |
| `DEC-RUN-003` | Streaming and real-time delivery | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 3.95 |
| `DEC-RUN-004` | Frontend state management and UX runtime | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 4.15 |

### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | 4 |
| Adopt-As-Is | 0 |
| Adopt-With-Modifications | 4 |
| Reject/Defer | 0 |
| Domain Winner | `mixed (SRC-AXI: pipeline; SRC-MXC: session, streaming, frontend)` |
| Dominant Maturity | `production_stable` |
| Key Risks | Monolithic SRC-MXC decomposition; streaming extraction; session storage migration; frontend rewrite combining 3 sources |
| Cross-Domain Dependencies | `DOM-PROVIDER`, `DOM-SECURITY`, `DOM-ADMIN`, `DOM-MIGRATION` |
| Carry-Forward Items | `CFI-RUN-001`, `CFI-RUN-002`, `CFI-RUN-003`, `CFI-RUN-004` |
