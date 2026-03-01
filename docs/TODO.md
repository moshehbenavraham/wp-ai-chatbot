# Ultimate WP AI Chatbot — Remaining Work

> **Tracking file for outstanding work only.** Completed work is recorded in
> `docs/CHANGELOG.md`. Each phase is scoped to fit within a single ~100K context
> window session.

---

## Phase 11 — Validation, Documentation, and GA Readiness

**Goal**: Ship-ready documentation, legal compliance, and sign-off.

- [ ] AI disclosure statement in chat widget (EU AI Act Article 50 — effective August 2, 2026)
  - Display in widget header or as first system message before user input
  - Non-dismissible; stored as `wpaic_ai_disclosure_text`
  - Default text: *"You are chatting with an AI assistant. Your messages are processed to generate responses."*
  - Must satisfy EU AI Act Art. 50 + CA SB 1001
- [ ] End-to-end validation report
  - Verify 30-day operational log rotation (Audit_Logger currently reads 7 days; confirm rotate/prune at 30)
  - Verify 5-year consent record retention (separate from transcript 90-day policy)
  - Verify retention notice default text appears in Security settings description:
    *"Chat transcripts are retained for 90 days and automatically deleted. Anonymized usage statistics may be retained indefinitely."*
  - Verify `wpaic_rate_limit_message` option exists and is editable (default: *"I've reached my message limit. Please try again shortly or contact us at [email]."*)
  - Verify widget is never hidden when rate limit is hit (input disabled + message shown, widget stays visible)
  - Verify rate limit fallback options (email, phone, live agent escalation) are available when configured
- [ ] Admin/user documentation
- [ ] Migration guides (per source plugin: axiachat-ai, mxchat-basic, baachal)
- [ ] Post-migration validation report
- [ ] Known limitations and deferred backlog document
- [ ] GA checklist sign-off

**Reference files**:
- `axiachat-ai/includes/logs.php`, `axiachat-ai/includes/usage-functions.php`
- `axiachat-ai/assets/js/usage.js`
- `mxchat-basic/includes/admin-transcripts-page.php`, `mxchat-basic/js/mxchat_transcripts.js`
- `mxchat-basic/readme.txt`
- `baachal/readme.txt`, `baachal/languages/baachal.pot`

---

## Phase 12 — Design System Foundation

**Goal**: Establish the visual design system — tokens, typography, spacing, elevation,
and preset color palettes with a picker UI.

### Design Tokens
- [ ] Color tokens: deep slate `#0F172A` / `#111827` / `#1F2937`, accent `#22D3EE`, signals `#22C55E` / `#F59E0B` / `#EF4444`
  - Ship with current dark/teal scheme (`--wpaic-accent #22D3EE`, `--wpaic-bg-deep #0F172A`)
  - CSS custom properties for all token values
- [ ] Typography: Space Grotesk (display), Inter (body), JetBrains Mono (code), scale 1.25
  - Font loading strategy (self-hosted or CDN with fallbacks)
  - CSS custom properties for font families + size scale
- [ ] Spacing scale: 4/8/12/16/24/32/48/64/96px
  - CSS custom properties (`--wpaic-space-1` through `--wpaic-space-9`)
- [ ] Three-level elevation model (flat, card, modal)
  - Box-shadow tokens for each level
  - Usage guidelines in CSS comments

### Color Palette System
- [ ] 6–10 preset color palettes (Baachal pattern) + full hex customization
  - Neutral defaults + preset palettes (Decision #6)
  - Optional removable "Powered by" footer link
- [ ] Preset color palette picker UI in admin Appearance/Widget settings
  - Palette preview swatches
  - Full hex override inputs per token

**Reference files**:
- `baachal/includes/class-settings.php` — preset color palettes UI baseline
- `assets/css/chatbot.css` — existing CSS custom properties to extend

---

## Phase 13 — Interaction Patterns & Animation

**Goal**: Implement the standardized interaction patterns for forms, feedback,
loading states, and animation constraints across admin and frontend.

### Form Patterns
- [ ] Forms: inline + on-submit validation, field-level errors, sticky success toast
  - Inline validation on blur; full validation on submit
  - Field-level error messages positioned below inputs
  - Success toast: sticky 3s then auto-dismiss
- [ ] Autosave: inline spinner then 800ms success state (MxChat pattern)
  - Debounce input changes (500ms)
  - Inline spinner replaces save button during save
  - 800ms green checkmark on success

### Feedback & Navigation
- [ ] Modals for destructive actions only; drawers for routine edits
  - Confirmation modal with explicit action labels (not "OK"/"Cancel")
  - Slide-in drawer for settings panels and edit forms
- [ ] Toasts for transient success; persistent banners for system issues
  - Toast: bottom-right, auto-dismiss 3–5s, dismissible
  - Banner: top of content area, requires manual dismiss or resolution
- [ ] Long operations: disable trigger, swap label, inline progress bar if > 3s
  - Button disabled + label changes to "Processing..."
  - Progress bar appears after 3s delay

### Loading States
- [ ] Loading: skeletons for lists, inline spinners for toggles, progressive for large views
  - Skeleton placeholders matching final layout shape
  - Toggle switches show inline spinner during async state change
  - Large views load progressively (header → content → sidebar)

### Animation Constraints
- [ ] Max 3 simultaneous animations per viewport region
- [ ] Min 0.6s for scroll-triggered reveals
- [ ] Cubic-bezier easing only (no linear)
  - Standard easing: `cubic-bezier(0.4, 0, 0.2, 1)`
  - Enter: `cubic-bezier(0, 0, 0.2, 1)`
  - Exit: `cubic-bezier(0.4, 0, 1, 1)`
- [ ] 60fps target under 6x CPU throttle for chat interactions
  - Use `transform` and `opacity` only for animations
  - Avoid layout-triggering properties in animation frames

---

## Phase 14 — Accessibility (WCAG 2.2 AA) & Responsive Layout

**Goal**: Full WCAG 2.2 AA compliance across admin and frontend, plus responsive
breakpoint implementation.

### Keyboard Navigation
- [ ] Full keyboard navigation, no traps, skip links in admin
  - Tab order follows visual order
  - Skip-to-content link as first focusable element in admin pages
  - Escape closes modals/drawers and returns focus to trigger
- [ ] Focus management: return to trigger on modal close
  - Store trigger element reference on open
  - Restore focus on close (modal, drawer, dropdown)

### Screen Reader Support
- [ ] Screen reader: semantic landmarks, explicit labels, polite live regions
  - `<nav>`, `<main>`, `<aside>` landmarks in admin layout
  - All form inputs have associated `<label>` elements
  - `aria-live="polite"` for chat messages, toast notifications, status updates

### Visual Compliance
- [ ] Color contrast AA on all states (default, hover, focus, disabled)
  - Minimum 4.5:1 for normal text, 3:1 for large text
  - Test all interactive states: default, hover, focus, active, disabled
- [ ] `prefers-reduced-motion`: replace animations with opacity transitions
  - `@media (prefers-reduced-motion: reduce)` block in main stylesheet
  - All `transform`/`translate` animations → `opacity` fade

### ARIA Patterns
- [ ] `aria-disabled` for gated controls, `aria-pressed` for binary toggles
  - Use `aria-disabled="true"` instead of `disabled` for controls that need explanation
  - Toggle buttons use `aria-pressed="true|false"`

### Responsive Breakpoints
- [ ] `< 640px` mobile: full-height chat sheet, single-column admin forms
  - Chat widget: full viewport height, no floating launcher
  - Admin forms: single column, stacked labels
  - Touch targets: minimum 44x44px
- [ ] `640-1024px` tablet: two-column admin, wider chat composer
  - Admin: sidebar + content two-column layout
  - Chat composer: wider input area
- [ ] `> 1024px` desktop: full data density, 380-420px chat panel
  - Admin: full data tables, multi-column forms
  - Chat panel: fixed 380-420px width

---

## Phase 15 — RAG Extensions

**Goal**: Extend the knowledge base pipeline with PDF ingestion, web scraping,
and a search testing console.

### PDF Content Source
- [ ] PDF content source adapter
  - Implement `Content_Source` interface
  - PDF text extraction (consider `smalot/pdfparser` or similar PHP library)
  - Handle multi-page documents with page-level chunking
  - Admin UI: file upload in Knowledge page
  - Support for scanned PDFs out of scope (text-only extraction)

**Baseline**: `axiachat-ai/includes/add-ons/pdf/`

### Web Scraper Content Source
- [ ] Web scraper content source adapter
  - Implement `Content_Source` interface
  - URL input with crawl depth option (1-3 levels)
  - HTML-to-text extraction (strip navigation, footers, scripts)
  - Respect `robots.txt` and rate limiting
  - Store source URL as metadata for citation

### Search Console
- [ ] Inline test search console in admin Knowledge page
  - Query input with "Test Search" button
  - Display: matched chunks, similarity scores, source metadata
  - Useful for debugging retrieval quality before deploying

**Reference files**:
- `includes/rag/interface-content-source.php` — Content_Source interface to implement
- `includes/rag/class-content-source-registry.php` — registry for new sources
- `includes/rag/class-knowledge-index.php` — search() for cosine similarity
- `includes/admin/pages/class-knowledge-page.php` — admin page to extend

---

## Phase 16 — Admin Screens & Dashboards

**Goal**: Build the remaining admin screens — transcripts/usage dashboard,
context inspection modal, and action catalog.

### Transcripts & Usage Screen
- [ ] Screen: Transcripts and Usage (`admin.php?page=uwpaic-transcripts`)
  - Session list table with filters (date range, user, status)
  - Individual transcript viewer with message thread
  - Usage statistics: messages/day, tokens consumed, cost estimates
  - Export to CSV
  - Retention policy indicator (shows days remaining per transcript)

**Baseline**: `mxchat-basic/includes/admin-transcripts-page.php`, `mxchat-basic/js/mxchat_transcripts.js`
**Reference**: `axiachat-ai/includes/logs.php`, `axiachat-ai/includes/usage-functions.php`, `axiachat-ai/assets/js/usage.js`

### Context Inspection Modal
- [ ] Context Inspection Modal (Sources + Actions tabs)
  - Sources tab: RAG chunks used, similarity scores, source document links
  - Actions tab: tool calls executed, inputs/outputs, timing
  - Accessible from transcript viewer (per-message "Inspect" link)

### Action Catalog
- [ ] Action catalog, permission matrix, test runner (Actions and Integrations screen)
  - Catalog: list all registered tools (native + webhook + MCP) with descriptions
  - Permission matrix: grid of tools × permission levels (none/user/admin)
  - Test runner: execute any tool with sample inputs, view output inline
  - Integrates with existing Tools page or as a sub-tab

---

## Phase 17 — Advanced Integrations

**Goal**: Build remaining integration features — appointment scheduling, bot
management, macro routing, MCP auth, and translation.

### Appointment Scheduling
- [ ] Appointment scheduling integration
  - Tool implementing `Tool_Interface`
  - Integration with Bookly and/or Amelia plugin APIs
  - Slot availability check, booking creation, cancellation
  - Confirmation message with booking details
  - Estimated scope: 8–10 new files

**Baseline**: `axiachat-ai/includes/add-ons/appointments/class-appointments-manager.php`

### Bot Management
- [ ] Per-bot tool assignment UI
  - Admin UI to assign/restrict tools per bot instance
  - Tool toggle grid per bot slug
  - Respects global `wpaic_tools_enabled` setting as master switch

**Baseline**: `axiachat-ai/includes/class-bot-manager.php`

### Routing & Authorization
- [ ] Macro/intent router UI
  - Pattern-based routing rules (keyword → action mapping)
  - Priority ordering for overlapping patterns
  - Admin UI for rule CRUD
- [ ] Full MCP authorization headers UI
  - Per-server auth configuration (API key, Bearer token, custom headers)
  - Secure storage via Settings_Manager (AES-256-GCM encrypted)
  - Test connection button with auth headers included

### Translation
- [ ] Translation Toolbar (optional module, default off)
  - Language selector in chat widget
  - Auto-detect incoming language
  - Translate bot responses via provider API or dedicated translation service

---

## Phase 18 — End-to-End Test Suite

**Goal**: Comprehensive E2E test coverage across all implemented features.

- [ ] End-to-end test matrix
  - Provider tests: OpenAI, Claude, Gemini, DeepSeek, Grok (chat + streaming + tool calls)
  - RAG pipeline: ingest → chunk → embed → search → context injection
  - Session lifecycle: create → message → history → cleanup → retention
  - Tool framework: webhook execution, MCP handshake, tool continuation loops
  - Migration: detect → dry-run → live run → rollback (per source plugin)
  - Security: rate limiting, content moderation, GDPR export/erasure, encryption round-trip
  - WooCommerce: product search, cart operations, order lookup
  - Lead capture + live chat handoff flows
  - Widget: floating/inline modes, mobile responsive, GDPR consent, SSE streaming
  - Admin: all pages render, settings save/load, REST endpoints respond correctly

---

## Technical Notes for Next Session

- WPAIC_VERSION is `1.0.0`
- PHPStan runs at level 0 with `--memory-limit=2G` on `includes/` (reference plugins cause OOM)
- PHP not installed locally — use Docker `php:8.3-cli` for all PHP tooling
- `Provider_Registry` uses `::instance()` not `::get_instance()`
- `wpaic_tools_enabled` defaults to 0 (opt-in globally)
- Tool_Interface file is `interface-tool-interface.php` — do NOT rename
- MCP/webhook tools stored in WP options (not DB tables)
- Tool continuation: streaming disabled when tools present (sync-only)
- RAG search scales to ~50K chunks in PHP; for larger indexes hook `wpaic_rag_search_results`
- `wpaic_data_retention_days` = 0 means retain indefinitely
- Migration_Runner::SOURCES = `['axiachat', 'mxchat', 'baachal']`
- API keys written via Settings_Manager::set() for transparent AES-256-GCM encryption
- OpenAI_Compatible_Adapter is the base for OpenAI, DeepSeek, and Grok adapters
- DeepSeek/Grok embedding providers not planned — neither offers production embedding API
