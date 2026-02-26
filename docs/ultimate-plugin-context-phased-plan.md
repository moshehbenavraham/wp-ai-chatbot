# Ultimate WP AI Chatbot Plugin - Context-Bounded Master Plan

## Mission

Build one "ultimate" WordPress AI chatbot plugin by combining the strongest implemented features from:

- `axiachat-ai/`
- `mxchat-basic/`
- `baachal/`

...while improving architecture quality, security posture, performance, migration safety, and maintainability.

This plan is designed to stay under an approximately 100k context window per working phase.

## Source Inputs

- `docs/axiachat-ai-feature-map.md` (broadest feature surface; tools/macros/add-ons depth) | Corresponding source code `axiachat-ai/`
- `docs/mxchat-basic-feature-map.md` (provider breadth, intents/actions, live-agent channels, cache compatibility) | Corresponding source code `mxchat-basic/`
- `docs/baachal-feature-map.md` (lean chat flow, WooCommerce context pipeline, cleaner data lifecycle options) | Corresponding source code `baachal/`

## Phase Plan

## Phase 00 - Program Setup and Extraction Schema

**Objective:** Establish the working framework that prevents context bloat and extraction drift.

**Focus:** Process and structure, not implementation.

**Inputs (bounded):**
- Top-level summaries from the three feature maps
- Plugin root structures and key file indexes

**Outputs:**
- Domain taxonomy (providers, runtime, RAG, tools, admin, security, WooCommerce, integrations)
- "Feature unit" schema (ID, source plugin, capability, maturity, dependencies, risk)
- Context pack template and carry-forward template

**Context budget target:** 45k-65k

**Exit criteria:**
- Shared vocabulary and scoring model are stable
- Future phases can run without re-reading full source maps

## Phase 01 - Canonical Capability Matrix and Scoring

**Objective:** Build a canonical matrix of all feature units and select winners per domain.

**Focus:** Extraction and decision quality.

**Inputs (bounded):**
- Feature-map sections by domain (sliced, not full docs)

**Outputs:**
- Capability matrix with source attribution:
  - "Adopt as-is"
  - "Adopt with modifications"
  - "Reject/defer"
- Rationale per selection (technical and product rationale)
- Initial "Ultimate v1 scope" boundary

**Context budget target:** 65k-85k

**Exit criteria:**
- Every major domain has a selected baseline
- No unresolved "which plugin behavior wins?" conflicts

## Phase 02 - Target Architecture and Module Contracts

**Objective:** Define final plugin architecture and subsystem contracts before coding.

**Focus:** Maintainable module boundaries and future extensibility.

**Inputs (bounded):**
- Winners from Phase 01 only
- Key implementation references for chosen patterns

**Outputs:**
- Module map:
  - bootstrap/lifecycle
  - provider layer
  - model registry
  - chat runtime
  - RAG/knowledge pipeline
  - tool/macro/intent execution
  - WooCommerce enrichment
  - admin/ops surface
- Contract docs (interfaces, payload schemas, storage contracts)
- Migration strategy outline from each source plugin

**Context budget target:** 70k-90k

**Exit criteria:**
- Interfaces are fixed enough to begin implementation in parallel tracks
- High-risk decisions are documented with fallback options

## Phase 03 - Core Plugin Skeleton, Lifecycle, and Data Foundations

**Objective:** Implement a clean foundation that all advanced features depend on.

**Focus:** Bootstrap reliability and data model correctness.

**Outputs:**
- Plugin bootstrap, constants, autoloading, hook registration
- Activation/deactivation/uninstall policy
- Core DB schema and migration runner
- Versioned upgrade framework and repair checks

**Budget strategy:**
- Split by concern: lifecycle session, schema session, migration session

**Context budget target:** 60k-85k

**Exit criteria:**
- Fresh install, upgrade, deactivate/reactivate, uninstall paths are deterministic

## Phase 04 - Provider Orchestration and Model Registry

**Objective:** Implement robust multi-provider AI orchestration and model governance.

**Best-of intent:**
- AxiaChat-style provider abstraction and tool continuation handling
- MxChat-style broad provider/model support and migration hygiene
- Baachal-style straightforward provider key and model validation UX

**Outputs:**
- Provider interface + registry dispatcher
- Provider adapters (initial prioritized set)
- Unified model registry with deprecation/fallback metadata
- Cost accounting hooks and usage attribution model

**Context budget target:** 70k-95k

**Exit criteria:**
- Same chat request contract works across all supported providers
- Deprecated model auto-migration path is tested

## Phase 05 - Chat Runtime, Sessioning, UX, and Safety Controls

**Objective:** Deliver production-grade chat runtime behavior on frontend and backend.

**Best-of intent:**
- AxiaChat depth in runtime controls and moderation/rate-limit pipeline
- MxChat resilience and fallback patterns
- Baachal simplicity in session handling and message lifecycle

**Outputs:**
- Widget + shortcode rendering modes
- Session model, history persistence options, clear/reset flows
- AJAX/REST runtime endpoints and nonce/auth checks
- Input sanitization, output rendering safeguards, anti-spam controls

**Context budget target:** 70k-95k

**Exit criteria:**
- Stable multi-turn chat across embed modes
- Security and abuse controls validated under load and edge cases

## Phase 06 - Knowledge Base, RAG, and Content Ingestion

**Objective:** Build a high-quality retrieval pipeline that is accurate and operable.

**Best-of intent:**
- AxiaChat ingestion breadth and background indexing depth
- MxChat queue processing and knowledge admin workflows
- Baachal lightweight site index and exclusion controls

**Outputs:**
- Content source adapters (posts/pages/products/manual/web/PDF where in-scope)
- Chunking/embedding/indexing pipeline
- Queue workers, retries, and operational controls
- Retrieval ranking and context assembly policy

**Context budget target:** 75k-95k

**Exit criteria:**
- Index build and retrieval are reliable, observable, and tunable

## Phase 07 - Actions, Tools, Macros, and Integrations

**Objective:** Add controlled execution capabilities beyond plain Q&A.

**Best-of intent:**
- AxiaChat tool and macro orchestration
- MxChat intent/action framework and live-agent handoff patterns
- Hardened permission, logging, and rollback semantics

**Outputs:**
- Tool/action contract and execution engine
- Macro/intent routing layer
- External integration adapters (prioritized by value/risk)
- Full audit trail for tool executions

**Context budget target:** 75k-95k

**Exit criteria:**
- Tooling is useful but bounded by strict trust and validation gates

## Phase 08 - WooCommerce and Business Workflows

**Objective:** Implement commerce-aware behavior and business conversions cleanly.

**Best-of intent:**
- Baachal product-context and recommendation patterns
- MxChat WooCommerce and lead/agent operational depth
- AxiaChat add-on style modular business features

**Outputs:**
- Product-aware retrieval and recommendation responses
- Lead capture, appointment, and handoff flows (as scoped)
- Admin controls and reporting views for business workflows

**Context budget target:** 70k-90k

**Exit criteria:**
- Commerce and conversion flows are reliable, measurable, and optional by module

## Phase 09 - Security, Privacy, Performance, and Observability Hardening

**Objective:** Raise production readiness before release candidate cut.

**Outputs:**
- Security review checklist closure (nonce/capability/sanitization/escaping paths)
- Privacy and data-retention controls
- Cache compatibility and performance profiling fixes
- Structured logging, diagnostics export, and alerting hooks

**Context budget target:** 60k-85k

**Exit criteria:**
- No high-severity security gaps remain open
- Performance baseline meets agreed targets

## Phase 10 - Migration, Compatibility, and Release Candidate

**Objective:** Safely transition existing users and ship with confidence.

**Outputs:**
- Migration paths from `axiachat-ai`, `mxchat-basic`, and `baachal` settings/data
- Backward-compatibility shims where required
- Release candidate package, test matrix, and rollback plan

**Context budget target:** 65k-90k

**Exit criteria:**
- Upgrade scenarios pass test matrix
- Rollback and disaster recovery runbook is verified

## Phase 11 - Validation, Documentation, and GA Readiness

**Objective:** Complete final validation and operational documentation.

**Outputs:**
- End-to-end validation report
- Admin/user docs and migration guides
- Known limitations and deferred backlog list

**Context budget target:** 45k-75k

**Exit criteria:**
- GA checklist complete
- Team can operate and support the plugin post-release
