# Domain Anchor Index

**Session ID**: `phase00-session01-reference-inventory-baseline`  
**Ordering Rule**: provider -> runtime -> rag -> tools -> admin -> security -> woocommerce -> integrations -> migration

---

## Anchor Table Columns

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|

---

## Provider

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-PRO-AXI-001` | `SRC-AXI` | `axiachat-ai/includes/class-aichat-provider-registry.php` | `AIChat_Provider_Registry` | `integration` | Central provider dispatch contract for OpenAI, Claude, and Gemini paths | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-PRO-MXC-001` | `SRC-MXC` | `mxchat-basic/includes/class-mxchat-integrator.php` | provider routing methods | `integration` | Consolidates multi-provider chat calls and model handling in one runtime boundary | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-PRO-BAA-001` | `SRC-BAA` | `baachal/baachal.php` | `call_gemini_api`, `call_openai_api`, `call_claude_api`, `call_grok_api` | `integration` | Compact provider API implementations useful for baseline contract comparison | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |

## Runtime

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-RUN-AXI-001` | `SRC-AXI` | `axiachat-ai/includes/class-aichat-ajax.php` | `AIChat_AJAX` request pipeline | `runtime` | Primary request lifecycle with validation, moderation, provider dispatch, and tool continuation | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-RUN-MXC-001` | `SRC-MXC` | `mxchat-basic/includes/class-mxchat-public.php` | widget rendering and frontend runtime bridge | `runtime` | Captures shortcode/widget runtime shape and session behavior exposed to users | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-RUN-BAA-001` | `SRC-BAA` | `baachal/assets/chatbot.js` | frontend send/receive message loop | `runtime` | Defines client runtime behavior including session token, message lifecycle, and UX controls | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |

## RAG

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-RAG-AXI-001` | `SRC-AXI` | `axiachat-ai/includes/contexto-functions.php` | context retrieval, chunking, embedding helpers | `rag` | Highest-value RAG control point for chunk retrieval and prompt context assembly | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-RAG-MXC-001` | `SRC-MXC` | `mxchat-basic/admin/class-knowledge-manager.php` | knowledge ingestion and queue orchestration | `rag` | Central ingestion and retrieval manager for local and external vector workflows | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-RAG-BAA-001` | `SRC-BAA` | `baachal/includes/content-indexer.php` | index table lifecycle and search routines | `rag` | Defines lightweight content indexing/search substrate used as context for provider calls | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |

## Tools

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-TOL-AXI-001` | `SRC-AXI` | `axiachat-ai/includes/add-ons/ai-tools/api.php` | tool registration and capability controls | `integration` | Canonical tool and macro registration boundary in AxiaChat runtime | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-TOL-MXC-001` | `SRC-MXC` | `mxchat-basic/includes/class-mxchat-integrator.php` | intents/actions callback execution paths | `integration` | Captures MxChat action framework behavior used as tool-like execution layer | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-TOL-BAA-NA` | `SRC-BAA` | `baachal/baachal.php` | no dedicated tools/macro subsystem | `integration` | N/A: Baachal exposes provider chat flow but no standalone tools registry comparable to AxiaChat/MxChat | `na` | `phase00-session02-domain-taxonomy-feature-schema` |

## Admin

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-ADM-AXI-001` | `SRC-AXI` | `axiachat-ai/includes/settings.php` | settings registration and tab orchestration | `admin` | Defines admin configuration contract and validation boundaries | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-ADM-MXC-001` | `SRC-MXC` | `mxchat-basic/includes/class-mxchat-admin.php` | admin page routing and option management | `admin` | Primary MxChat admin orchestration surface and model/settings controls | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-ADM-BAA-001` | `SRC-BAA` | `baachal/admin/settings-page.php` | tabbed settings save and sanitization flow | `admin` | Compact admin boundary covering provider settings and operational toggles | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |

## Security

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-SEC-AXI-001` | `SRC-AXI` | `axiachat-ai/includes/moderation.php` | moderation and abuse controls | `security` | Centralized moderation and anti-abuse controls tied to runtime message flow | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-SEC-MXC-001` | `SRC-MXC` | `mxchat-basic/includes/class-mxchat-integrator.php` | nonce/session ownership and request guards | `security` | Security-sensitive checks and request protection in high-traffic runtime entry points | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-SEC-BAA-001` | `SRC-BAA` | `baachal/baachal.php` | nonce verification, capability checks, input limits | `security` | Primary trust-boundary enforcement and sanitization for Baachal chat/admin actions | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |

## WooCommerce

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-WOO-AXI-001` | `SRC-AXI` | `axiachat-ai/includes/contexto-functions.php` | WooCommerce product enrichment during retrieval | `integration` | Captures query-time commerce enrichment used in RAG responses | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-WOO-MXC-001` | `SRC-MXC` | `mxchat-basic/includes/class-mxchat-woocommerce.php` | WooCommerce intent helpers and cart/order actions | `integration` | Dedicated commerce behavior module with product-aware action flows | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-WOO-BAA-001` | `SRC-BAA` | `baachal/admin/tabs/woocommerce.php` | WooCommerce settings and product-search controls | `integration` | Defines Baachal commerce configuration and term extraction controls | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |

## Integrations

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-INT-AXI-001` | `SRC-AXI` | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` | MCP server lifecycle and tool execution bridge | `integration` | Strongest external systems integration boundary in AxiaChat architecture | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-INT-MXC-001` | `SRC-MXC` | `mxchat-basic/includes/class-mxchat-integrator.php` | live-agent handoff and external channel dispatch | `integration` | Central integration behavior for Slack/Telegram and action-triggered external handoff | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-INT-BAA-NA` | `SRC-BAA` | `baachal/baachal.php` | no dedicated non-provider integration subsystem | `integration` | N/A: Baachal focuses on core provider calls and WooCommerce/content context without separate integration add-ons | `na` | `phase00-session02-domain-taxonomy-feature-schema` |

## Migration

| Anchor ID | Source ID | Path | Symbol or Scope | Anchor Type | Rationale | Promotion Status | Session Targets |
|---|---|---|---|---|---|---|---|
| `ANC-MIG-AXI-001` | `SRC-AXI` | `axiachat-ai/axiachat-ai.php` | update checks, table/column repairs, capability migration | `migration` | Canonical upgrade and repair flows for long-lived installs | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-MIG-MXC-001` | `SRC-MXC` | `mxchat-basic/mxchat-basic.php` | schema migration routines and deprecated model remapping | `migration` | Consolidated migration safety logic across table and model evolution | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
| `ANC-MIG-BAA-001` | `SRC-BAA` | `baachal/baachal.php` | `migrate_provider_settings` and default-option upgrade path | `migration` | Compact migration path for provider/model transition and upgrade compatibility | `candidate` | `phase00-session02-domain-taxonomy-feature-schema`, `phase00-session03-context-pack-decision-log-templates` |
