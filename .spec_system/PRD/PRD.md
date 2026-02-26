# Ultimate WP AI Chatbot Plugin - Product Requirements Document

## Overview

Ultimate WP AI Chatbot Plugin unifies the strongest implemented capabilities from `axiachat-ai/`, `mxchat-basic/`, and `baachal/` into one maintainable WordPress plugin. The product must deliver reliable AI chat experiences for business websites while improving architecture quality, migration safety, performance, and security posture.

The product direction follows a phase-driven delivery model that controls scope, preserves context quality, and reduces implementation drift. Early phases focus on extraction and architecture decisions, then move through implementation tracks for runtime, retrieval, integrations, hardening, migration, and GA readiness.

Primary ownership is with business owners and operators who need practical automation for customer-facing conversations without adopting a standalone SaaS platform.

## Reference Source Code Working Set

### Canonical reference projects

- `axiachat-ai/` (broadest feature surface: provider abstraction, RAG depth, tools/add-ons, diagnostics)
- `mxchat-basic/` (broad provider coverage, intent/action patterns, live-agent channels, migration and cache compatibility depth)
- `baachal/` (lean runtime architecture, WooCommerce-first context patterns, clean data lifecycle controls)

### Reference-only policy (non-negotiable)

- `axiachat-ai/`, `mxchat-basic/`, and `baachal/` are read-only reference repositories.
- These folders are used only for code reading, pattern extraction, and implementation inspiration.
- No implementation session may modify, delete, rename, or reformat files inside these three folders.
- New implementation work must be created in the unified target plugin codebase, with behavior adapted from reference anchors.

### Mandatory reference rule for all implementation sessions

1. Every session spec must include a `Reference Anchors` section with concrete file paths from these source projects.
2. Every task in `tasks.md` must cite at least one source anchor path for the behavior being implemented.
3. If a capability has no equivalent in one source project, mark that project as `N/A (no matching implementation)` explicitly.
4. `/plansession` should not finalize a session until the session has enough anchors to implement without re-reading full repositories.
5. Any task that proposes edits inside `axiachat-ai/`, `mxchat-basic/`, or `baachal/` must be rejected and rewritten to target the unified plugin code instead.

## Goals

1. Deliver one production-grade WordPress chatbot plugin that combines best-of capabilities from all three source plugins.
   - Reference anchors: `axiachat-ai/axiachat-ai.php`, `mxchat-basic/mxchat-basic.php`, `baachal/baachal.php`.
2. Improve maintainability through clear module boundaries, explicit contracts, and phased implementation gates.
   - Reference anchors: `axiachat-ai/includes/interfaces/interface-aichat-provider.php`, `mxchat-basic/includes/class-mxchat-integrator.php`, `baachal/includes/content-indexer.php`.
3. Provide secure, privacy-aware chat operations suitable for customer-facing business sites.
   - Reference anchors: `axiachat-ai/includes/class-aichat-ajax.php`, `mxchat-basic/admin/class-ajax-handler.php`, `baachal/baachal.php`.
4. Enable safe migration paths from legacy plugin settings and data with rollback-aware release practices.
   - Reference anchors: `axiachat-ai/axiachat-ai.php`, `mxchat-basic/mxchat-basic.php`, `baachal/uninstall.php`.
5. Build an operable system with diagnostics and controls that support ongoing tuning and adoption growth.
   - Reference anchors: `axiachat-ai/includes/usage.php`, `mxchat-basic/includes/admin-transcripts-page.php`, `baachal/admin/settings-page.php`.

## Non-Goals

- Full feature parity with all three source plugins on day 1.
- Enterprise SLA and high-availability guarantees in the initial release.
- Converting this plugin effort into a standalone multi-tenant SaaS product.
- Bypassing phased scope controls to deliver all integrations immediately.

## Users and Use Cases

### Primary users

- **Business owners and site operators**: teams responsible for customer engagement, support automation, and conversion workflows on WordPress properties.

### Key use cases

1. Configure and deploy a branded AI chatbot on a WordPress site via widget or shortcode modes.
   - Reference anchors: `axiachat-ai/includes/shortcode.php`, `mxchat-basic/includes/class-mxchat-public.php`, `baachal/templates/chatbot-widget.php`.
2. Connect AI providers and tune model behavior per business use case.
   - Reference anchors: `axiachat-ai/includes/model-registry.php`, `mxchat-basic/includes/class-mxchat-admin.php`, `baachal/admin/tabs/general.php`.
3. Build and maintain a business-specific knowledge base for grounded responses.
   - Reference anchors: `axiachat-ai/includes/contexto-functions.php`, `mxchat-basic/admin/class-knowledge-manager.php`, `baachal/includes/content-indexer.php`.
4. Review usage, quality signals, and operational diagnostics to improve outcomes over time.
   - Reference anchors: `axiachat-ai/includes/logs.php`, `mxchat-basic/js/mxchat_transcripts.js`, `baachal/admin/tabs/advanced.php`.
5. Migrate from existing chatbot plugin configurations to the unified plugin with controlled risk.
   - Reference anchors: `axiachat-ai/axiachat-ai.php`, `mxchat-basic/mxchat-basic.php`, `baachal/uninstall.php`.

## Requirements

### MVP requirements

1. Deterministic plugin lifecycle behavior (install, upgrade, deactivate/reactivate, uninstall) with data safety checks.
   - `axiachat-ai/axiachat-ai.php`
   - `mxchat-basic/mxchat-basic.php`
   - `baachal/baachal.php`
   - `baachal/uninstall.php`
2. Provider orchestration and model registry patterns supporting multiple AI providers under one request contract.
   - `axiachat-ai/includes/class-aichat-provider-registry.php`
   - `axiachat-ai/includes/providers/class-openai-provider.php`
   - `axiachat-ai/includes/providers/class-claude-provider.php`
   - `axiachat-ai/includes/providers/class-gemini-provider.php`
   - `mxchat-basic/includes/class-mxchat-integrator.php`
   - `mxchat-basic/includes/class-mxchat-admin.php`
   - `baachal/baachal.php`
   - `baachal/admin/tabs/general.php`
3. Secure chat runtime behavior with session handling, persistence options, and abuse controls.
   - `axiachat-ai/includes/class-aichat-ajax.php`
   - `axiachat-ai/assets/js/aichat-frontend.js`
   - `mxchat-basic/includes/class-mxchat-public.php`
   - `mxchat-basic/js/chat-script.js`
   - `baachal/baachal.php`
   - `baachal/assets/chatbot.js`
4. Retrieval pipeline foundations for ingestion, chunking/indexing, and context assembly.
   - `axiachat-ai/includes/contexto-functions.php`
   - `axiachat-ai/includes/contexto-create.php`
   - `axiachat-ai/includes/add-ons/web-scraper/scraper-core.php`
   - `mxchat-basic/admin/class-knowledge-manager.php`
   - `mxchat-basic/includes/class-mxchat-chunker.php`
   - `mxchat-basic/js/knowledge-processing.js`
   - `baachal/includes/content-indexer.php`
   - `baachal/admin/tabs/content.php`
5. Admin controls for configuration, operations, and quality tuning needed for business deployment.
   - `axiachat-ai/includes/settings.php`
   - `axiachat-ai/assets/js/settings.js`
   - `mxchat-basic/includes/class-mxchat-admin.php`
   - `mxchat-basic/js/mxchat-admin.js`
   - `baachal/admin/settings-page.php`
   - `baachal/admin/tabs/advanced.php`
   - `baachal/assets/admin.js`

### Deferred requirements

1. Advanced tool, macro, and intent ecosystems beyond prioritized high-value integrations.
   - `axiachat-ai/includes/add-ons/ai-tools/api.php`
   - `axiachat-ai/includes/add-ons/ai-tools/macro-api.php`
   - `mxchat-basic/js/mxchat_actions.js`
   - `mxchat-basic/includes/admin-actions-page.php`
2. Extended WooCommerce and business workflow modules beyond initial validated scope.
   - `axiachat-ai/includes/add-ons/appointments/class-appointments-manager.php`
   - `axiachat-ai/includes/add-ons/leads/class-leads-manager.php`
   - `mxchat-basic/includes/class-mxchat-woocommerce.php`
   - `baachal/admin/tabs/woocommerce.php`
3. Full breadth of optional integrations from all source plugins in early releases.
   - `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php`
   - `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php`
   - `mxchat-basic/includes/class-mxchat-addons.php`
   - `baachal/baachal.php` (hooks/filter extension points)

## Non-Functional Requirements

- **Performance**: Runtime and retrieval paths must meet agreed latency and throughput baselines defined during hardening phases.
  - Reference anchors: `axiachat-ai/includes/aichat-cron.php`, `mxchat-basic/admin/class-knowledge-manager.php`, `baachal/includes/content-indexer.php`.
- **Security**: Nonce, capability, sanitization, escaping, and audit-path controls must be enforced across admin and runtime surfaces.
  - Reference anchors: `axiachat-ai/includes/moderation.php`, `mxchat-basic/admin/class-ajax-handler.php`, `baachal/admin/settings-page.php`.
- **Reliability**: Lifecycle operations, indexing workflows, and chat request paths must be deterministic and recoverable.
  - Reference anchors: `axiachat-ai/axiachat-ai.php`, `mxchat-basic/mxchat-basic.php`, `baachal/baachal.php`.
- **Accessibility**: Frontend chat surfaces and admin controls must support keyboard-first interaction and readable semantics.
  - Reference anchors: `axiachat-ai/assets/js/aichat-frontend.js`, `mxchat-basic/js/chat-script.js`, `baachal/templates/chatbot-widget.php`.

## Constraints and Dependencies

- Product must remain a WordPress plugin deployment model.
- Delivery follows bounded phases and session scopes to manage context and implementation quality.
- External AI provider APIs are required for model inference capabilities.
- Migration compatibility with `axiachat-ai/`, `mxchat-basic/`, and `baachal/` is a core dependency for release confidence.
- Optional external storage/services may be used where clearly justified, but core operation should remain viable in plugin-centric environments.
- Core dependency for development speed: all phases must use source anchors from the three reference repositories rather than starting from blank designs.
- Hard boundary: the three reference repositories remain untouched throughout delivery; they are not development targets.

## Phases

This system delivers the product via phases. Each phase is implemented via multiple 2-4 hour sessions (12-25 tasks each).

| Phase | Name | Sessions | Status |
|-------|------|----------|--------|
| 00 | Program Setup and Extraction Schema | TBD | Not Started |
| 01 | Canonical Capability Matrix and Scoring | TBD | Planned |
| 02 | Target Architecture and Module Contracts | TBD | Planned |
| 03 | Core Plugin Skeleton, Lifecycle, and Data Foundations | TBD | Planned |
| 04 | Provider Orchestration and Model Registry | TBD | Planned |
| 05 | Chat Runtime, Sessioning, UX, and Safety Controls | TBD | Planned |
| 06 | Knowledge Base, RAG, and Content Ingestion | TBD | Planned |
| 07 | Actions, Tools, Macros, and Integrations | TBD | Planned |
| 08 | WooCommerce and Business Workflows | TBD | Planned |
| 09 | Security, Privacy, Performance, and Observability Hardening | TBD | Planned |
| 10 | Migration, Compatibility, and Release Candidate | TBD | Planned |
| 11 | Validation, Documentation, and GA Readiness | TBD | Planned |

## Phase Reference Anchors (Use During /plansession)

### Phase 00 - Program Setup and Extraction Schema

- `axiachat-ai/axiachat-ai.php`, `axiachat-ai/readme.txt`
- `baachal/baachal.php`, `baachal/readme.txt`
- `mxchat-basic/mxchat-basic.php`, `mxchat-basic/readme.txt`

### Phase 01 - Canonical Capability Matrix and Scoring

- `axiachat-ai/includes/model-registry.php`, `axiachat-ai/includes/class-aichat-ajax.php`
- `baachal/admin/tabs/general.php`, `baachal/admin/tabs/search.php`
- `mxchat-basic/includes/class-mxchat-integrator.php`, `mxchat-basic/includes/class-mxchat-admin.php`

### Phase 02 - Target Architecture and Module Contracts

- `axiachat-ai/includes/interfaces/interface-aichat-provider.php`, `axiachat-ai/includes/class-aichat-provider-registry.php`
- `baachal/includes/content-indexer.php`, `baachal/admin/settings-page.php`
- `mxchat-basic/includes/class-mxchat-public.php`, `mxchat-basic/admin/class-ajax-handler.php`

### Phase 03 - Core Plugin Skeleton, Lifecycle, and Data Foundations

- `axiachat-ai/axiachat-ai.php`, `axiachat-ai/includes/aichat-cron.php`
- `baachal/baachal.php`, `baachal/uninstall.php`
- `mxchat-basic/mxchat-basic.php`, `mxchat-basic/includes/class-mxchat-utils.php`

### Phase 04 - Provider Orchestration and Model Registry

- `axiachat-ai/includes/class-aichat-provider-registry.php`, `axiachat-ai/includes/providers/class-openai-provider.php`, `axiachat-ai/includes/providers/class-claude-provider.php`, `axiachat-ai/includes/providers/class-gemini-provider.php`
- `baachal/baachal.php`, `baachal/admin/tabs/general.php`
- `mxchat-basic/includes/class-mxchat-integrator.php`, `mxchat-basic/includes/class-mxchat-admin.php`

### Phase 05 - Chat Runtime, Sessioning, UX, and Safety Controls

- `axiachat-ai/includes/class-aichat-ajax.php`, `axiachat-ai/includes/shortcode.php`, `axiachat-ai/assets/js/aichat-frontend.js`
- `baachal/templates/chatbot-widget.php`, `baachal/assets/chatbot.js`, `baachal/baachal.php`
- `mxchat-basic/includes/class-mxchat-public.php`, `mxchat-basic/js/chat-script.js`, `mxchat-basic/includes/class-mxchat-integrator.php`

### Phase 06 - Knowledge Base, RAG, and Content Ingestion

- `axiachat-ai/includes/contexto-functions.php`, `axiachat-ai/includes/contexto-create.php`, `axiachat-ai/includes/contexto-modify.php`, `axiachat-ai/includes/add-ons/web-scraper/scraper-core.php`
- `baachal/includes/content-indexer.php`, `baachal/admin/tabs/content.php`, `baachal/admin/tabs/search.php`
- `mxchat-basic/admin/class-knowledge-manager.php`, `mxchat-basic/includes/class-mxchat-chunker.php`, `mxchat-basic/js/knowledge-processing.js`

### Phase 07 - Actions, Tools, Macros, and Integrations

- `axiachat-ai/includes/add-ons/ai-tools/api.php`, `axiachat-ai/includes/add-ons/ai-tools/macro-api.php`, `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php`, `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php`
- `baachal/baachal.php`, `baachal/admin/tabs/advanced.php`
- `mxchat-basic/js/mxchat_actions.js`, `mxchat-basic/includes/admin-actions-page.php`, `mxchat-basic/includes/class-mxchat-addons.php`

### Phase 08 - WooCommerce and Business Workflows

- `axiachat-ai/includes/add-ons/appointments/class-appointments-manager.php`, `axiachat-ai/includes/add-ons/leads/class-leads-manager.php`, `axiachat-ai/includes/add-ons/livechat/class-livechat-manager.php`
- `baachal/admin/tabs/woocommerce.php`, `baachal/baachal.php`
- `mxchat-basic/includes/class-mxchat-woocommerce.php`, `mxchat-basic/includes/class-mxchat-integrator.php`

### Phase 09 - Security, Privacy, Performance, and Observability Hardening

- `axiachat-ai/includes/moderation.php`, `axiachat-ai/includes/sanitize-helpers.php`, `axiachat-ai/includes/logs.php`, `axiachat-ai/includes/usage.php`
- `baachal/baachal.php`, `baachal/admin/settings-page.php`, `baachal/includes/content-indexer.php`
- `mxchat-basic/admin/class-ajax-handler.php`, `mxchat-basic/mxchat-basic.php`, `mxchat-basic/includes/admin-transcripts-page.php`

### Phase 10 - Migration, Compatibility, and Release Candidate

- `axiachat-ai/axiachat-ai.php`, `axiachat-ai/includes/model-registry.php`
- `baachal/baachal.php`, `baachal/uninstall.php`
- `mxchat-basic/mxchat-basic.php`, `mxchat-basic/includes/class-mxchat-admin.php`

### Phase 11 - Validation, Documentation, and GA Readiness

- `axiachat-ai/includes/logs.php`, `axiachat-ai/includes/usage-functions.php`, `axiachat-ai/assets/js/usage.js`
- `baachal/readme.txt`, `baachal/languages/baachal.pot`
- `mxchat-basic/includes/admin-transcripts-page.php`, `mxchat-basic/js/mxchat_transcripts.js`, `mxchat-basic/readme.txt`

## Phase 00: Program Setup and Extraction Schema

### Objectives

1. Establish a working framework that prevents context bloat and extraction drift.
2. Define stable taxonomy and feature-unit schemas so later phases can proceed without re-reading full source maps.
3. Produce a reusable anchor index for `axiachat-ai/`, `baachal/`, and `mxchat-basic/` at phase start.

### Sessions (To Be Defined)

Sessions are defined via `/phasebuild` as `session_NN_name.md` stubs under `.spec_system/PRD/phase_00/`.

Each `phase_00` session must include:

- Reference anchors per source project
- Decision logs for "adopt as-is" vs "adopt with modifications"
- Explicit carry-forward notes for unresolved conflicts

**Note**: This command does NOT create phase directories or session stubs. Run `/phasebuild` after creating the PRD.

## Technical Stack

- WordPress plugin architecture (PHP backend) aligned to `axiachat-ai/axiachat-ai.php`, `mxchat-basic/mxchat-basic.php`, and `baachal/baachal.php`.
- JavaScript admin and frontend assets aligned to `axiachat-ai/assets/js/*.js`, `mxchat-basic/js/*.js`, and `baachal/assets/*.js`.
- WordPress AJAX/REST request patterns aligned to `axiachat-ai/includes/class-aichat-ajax.php`, `mxchat-basic/admin/class-ajax-handler.php`, and `baachal/baachal.php`.
- WordPress data layer (`options`, metadata, and custom tables) aligned to `axiachat-ai/includes/contexto-functions.php`, `mxchat-basic/admin/class-knowledge-manager.php`, and `baachal/includes/content-indexer.php`.
- External AI provider APIs (OpenAI, Claude, Gemini and optional provider extensions) aligned to `axiachat-ai/includes/providers/*.php`, `mxchat-basic/includes/class-mxchat-integrator.php`, and `baachal/baachal.php`.
- Retrieval infrastructure (local embeddings plus optional external vectors) aligned to `axiachat-ai/includes/contexto-functions.php`, `mxchat-basic/admin/class-pinecone-manager.php`, and `baachal/includes/content-indexer.php`.

## Success Criteria

- [ ] Unified plugin architecture is defined and implemented across phases without unresolved source-conflict decisions.
- [ ] Every implementation session includes source anchors that reference the three canonical projects.
- [ ] Grounded response quality is validated against representative business content from indexed sources.
- [ ] Security and privacy controls pass hardening and validation gates with checks informed by reference implementations.
- [ ] Performance baselines are established and met for chat runtime and retrieval flows.
- [ ] Admin operators can configure, diagnose, and maintain the system without code changes.
- [ ] Migration paths from all three source plugins are validated before GA release.

## Risks

- **Integration scope creep**: enforce phased gating, explicit MVP boundaries, and session-level scope limits.
- **Security regressions while merging patterns**: enforce centralized validation checklists and dedicated hardening phases.
- **Migration complexity across source data models**: define compatibility contracts early and validate with a migration test matrix.
- **Provider/API volatility**: maintain adapter boundaries and fallback/deprecation handling in the model registry.
- **Reference drift across phases**: keep phase-level anchor lists updated whenever source-derived decisions change.

## Assumptions

- Source plugin codebases remain available as reference inputs during build phases.
- Required AI provider APIs remain accessible under acceptable operational and cost constraints.
- WordPress plugin deployment remains the required operating model for target users.
- Manual phase reviews continue to be used before progressing to later implementation tracks.
- Session authors will follow the mandatory source-anchor rule to keep implementation speed high and rework low.

## Open Questions

1. What is the minimum feature set required for first GA beyond phases 03-06?
2. Which migration scenarios are mandatory for GA: settings-only, conversation history, knowledge indexes, or all?
3. What default retention policy should apply to chat transcripts and related operational logs?
4. Which external integrations (if any) must be included before GA versus deferred to post-GA?
5. For each deferred capability, which source project should be treated as the primary baseline and why?
