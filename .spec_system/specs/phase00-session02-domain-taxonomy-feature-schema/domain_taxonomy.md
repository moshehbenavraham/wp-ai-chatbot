# Canonical Domain Taxonomy

**Session ID**: `phase00-session02-domain-taxonomy-feature-schema`
**Status**: Ready for Phase 01 consumption

---

## Purpose

Define stable domain names and boundaries for feature extraction across:
- `axiachat-ai/`
- `mxchat-basic/`
- `baachal/`

This taxonomy is the canonical vocabulary for Session 03 templates and Phase 01 scoring matrices.

---

## Canonical Domain Set

| Domain ID | Canonical Name | Allowed Aliases | Definition | Include When | Exclude When |
|---|---|---|---|---|---|
| `DOM-PROVIDER` | provider | ai_provider, model_provider, llm_provider | Provider abstraction, model routing, provider-specific API adapters, and model catalog behavior. | The capability decides which provider/model is used or how provider requests are built. | The capability is only about request lifecycle, UI, or retrieval context and does not own provider/model decisions. |
| `DOM-RUNTIME` | runtime | chat_runtime, request_pipeline, session_runtime | Message lifecycle from user request intake to response output, including session/state handling and streaming behavior. | The capability processes live chat requests, stores conversational state, or emits responses to frontend/admin clients. | The behavior is strictly settings-only, migration-only, or ingestion-only. |
| `DOM-RAG` | rag | retrieval, knowledge_base, context_pipeline, indexing | Ingestion, chunking, indexing, retrieval, and context assembly used to ground responses. | The capability builds, stores, searches, or injects contextual knowledge into prompts/responses. | The behavior is purely provider API transport, non-context chat UI, or generic admin rendering. |
| `DOM-TOOLS` | tools | actions, function_calling, macros, tool_registry | Structured callable tools/macros that extend chatbot behavior beyond direct answer generation. | The capability registers tools, validates callable schemas, or executes tool callbacks. | The behavior is generic integrations without callable tool contract, or normal chat runtime logic. |
| `DOM-ADMIN` | admin | settings, admin_ui, control_surface, dashboard | Admin pages, option registration/sanitization, operational controls, and diagnostics interfaces. | The behavior is only available in admin context and controls configuration/operations. | The capability is runtime message handling, provider transport, or retrieval core internals. |
| `DOM-SECURITY` | security | trust_boundary, authz, nonce_guard, moderation | Security controls such as nonce checks, capability checks, sanitization, abuse controls, and privacy enforcement. | The capability protects state-changing actions or enforces safety/privacy guarantees. | The code only performs business logic with no trust-boundary enforcement responsibility. |
| `DOM-WOOCOMMERCE` | woocommerce | commerce, product_assist, cart_assist | WooCommerce-specific context, catalog operations, product retrieval, and commerce action handling. | The capability requires WooCommerce entities, hooks, or product/cart/order context. | The behavior is generic content retrieval or chat runtime unrelated to WooCommerce entities. |
| `DOM-INTEGRATIONS` | integrations | external_integrations, handoff_channels, connectors | External system integration boundaries (MCP, Slack, Telegram, custom webhooks, external channels). | The capability connects to external services/channels beyond base provider APIs. | The behavior is first-party provider transport (`DOM-PROVIDER`) or internal tool execution only (`DOM-TOOLS`). |
| `DOM-MIGRATION` | migration | upgrade_path, schema_migration, compatibility_migration | Upgrade and compatibility routines that change persisted state, schema, defaults, or deprecated mappings. | The behavior is explicitly tied to version transitions or compatibility repair. | The behavior is standard runtime/admin logic not tied to versioned upgrade flow. |

---

## Domain Boundary Rules

### Primary-domain rule

Assign exactly one primary domain per feature unit using this precedence order when overlap exists:

`provider -> runtime -> rag -> tools -> admin -> security -> woocommerce -> integrations -> migration`

Use `secondary_domains` only when cross-domain behavior is material and non-incidental.

### Overlap handling

| Overlap Scenario | Primary Domain | Secondary Domain | Rule |
|---|---|---|---|
| Provider adapter validates nonce/capability | `DOM-PROVIDER` | `DOM-SECURITY` | If provider transport is the core behavior, keep provider primary and tag security secondary. |
| Runtime handler injects RAG context | `DOM-RUNTIME` | `DOM-RAG` | Request pipeline remains primary; retrieval is a subordinate step in that feature entry. |
| WooCommerce context built through RAG index/search | `DOM-WOOCOMMERCE` | `DOM-RAG` | If commerce entities are the intent, classify as WooCommerce and tag RAG as secondary. |
| Tool callback invokes external webhook | `DOM-TOOLS` | `DOM-INTEGRATIONS` | Callable tool framework remains primary unless the feature is channel/transport infrastructure itself. |
| Admin page performs migration trigger | `DOM-MIGRATION` | `DOM-ADMIN` | If versioned state change is the main objective, migration is primary even when initiated from admin UI. |

### N/A policy

When a source has no comparable implementation for a canonical domain, use exact status text:

`N/A (no matching implementation)`

Required N/A metadata:
1. `source_plugin` and canonical domain id.
2. Best-available reference anchor or file scope used to confirm the absence.
3. One sentence rationale stating why behavior is not equivalent.

N/A entries stay in extraction and scoring artifacts. They are not omitted.

---

## Naming Rules

- Canonical names are lowercase and singular.
- Domain IDs use `DOM-*` format and remain stable across phases.
- Aliases exist only for traceability; all outputs must emit canonical names/IDs.
- No new domain label is allowed without updating this file and `taxonomy_schema_crosswalk.md`.

---

## Coverage Traceability

| Domain | AxiaChat Anchor | MxChat Anchor | Baachal Anchor | Notes |
|---|---|---|---|---|
| provider | `ANC-PRO-AXI-001` | `ANC-PRO-MXC-001` | `ANC-PRO-BAA-001` | Full three-source coverage. |
| runtime | `ANC-RUN-AXI-001` | `ANC-RUN-MXC-001` | `ANC-RUN-BAA-001` | Full three-source coverage. |
| rag | `ANC-RAG-AXI-001` | `ANC-RAG-MXC-001` | `ANC-RAG-BAA-001` | Full three-source coverage. |
| tools | `ANC-TOL-AXI-001` | `ANC-TOL-MXC-001` | `ANC-TOL-BAA-NA` | Baachal has explicit N/A for dedicated tools/macro subsystem parity. |
| admin | `ANC-ADM-AXI-001` | `ANC-ADM-MXC-001` | `ANC-ADM-BAA-001` | Full three-source coverage. |
| security | `ANC-SEC-AXI-001` | `ANC-SEC-MXC-001` | `ANC-SEC-BAA-001` | Full three-source coverage. |
| woocommerce | `ANC-WOO-AXI-001` | `ANC-WOO-MXC-001` | `ANC-WOO-BAA-001` | Full three-source coverage with conditional activation patterns. |
| integrations | `ANC-INT-AXI-001` | `ANC-INT-MXC-001` | `ANC-INT-BAA-NA` | Baachal has explicit N/A for non-provider integration subsystem parity. |
| migration | `ANC-MIG-AXI-001` | `ANC-MIG-MXC-001` | `ANC-MIG-BAA-001` | Retained for upgrade compatibility analysis continuity. |
