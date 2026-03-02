# Ultimate WP AI Chatbot Plugin - UX Requirements Document

**Companion to**: [PRD.md](PRD.md)
**Created**: 2026-02-28

---

## 1. Design Brief

This UX PRD maps directly to the functional scope in `PRD.md`, especially the five core
use cases in `Users and Use Cases`.

### Emotional Targets

- Calm control
- Trustworthy competence
- Approachable intelligence

### Aesthetic Identity

- **Reference domain**: Mission control dashboard plus premium support concierge
- **Era / movement**: Swiss International grid discipline with modern product UI restraint
- **Material metaphor**: Frosted glass panels over matte graphite surfaces with crisp signal
  accents

The product should feel operationally serious without appearing cold or intimidating.

### Signature Moment

When a website visitor opens the widget and submits a question, the first grounded response
appears in a staged reveal: answer text fades in first, then source chips and suggested
follow-ups animate in sequence within 300-450ms. This moment should feel precise, fast,
and credible enough to screenshot.

### Micro-Narrative

Arrival -> Visitor sees a compact, calm launcher with clear intent text.
Orientation -> Opening the widget immediately shows conversation context and one suggested prompt.
Engagement -> User asks a question and receives grounded output with visible confidence cues.
Action -> User follows a CTA (product page, lead form, appointment, or live handoff when enabled).
Resolution -> Session ends with a clear close/reset path and no uncertainty about next steps.

On the operator side: install -> configure -> deploy -> monitor -> improve, with each step
visibly reducing uncertainty.

---

## Scope and Phase Gating

- **MVP baseline**: setup and deployment, provider/model configuration, secure chat runtime,
  retrieval operations, diagnostics/transcript review, and migration assistant.
- **Deferred (phase-gated)**: advanced tool and macro ecosystem, extended WooCommerce workflow
  modules, and broad optional integration catalog.
- **Optional module (default off for MVP)**: transcript translation workflow.
- Any deferred or optional capability must be explicitly marked in phase/session specs before
  implementation work starts.

---

## 2. User Flows

### Flow 1: Operator First-Time Setup and Launch
**Trigger**: Plugin activation on a WordPress site
**Goal**: Publish a working chatbot in widget and shortcode modes

```text
[Activate plugin] --> [Open setup wizard] --> [Connect provider and model]
      |                                              |
      v                                              v
[Import previous settings?]                     [Validate key/model]
      |                                              |
      v                                              v
[Configure widget basics] --> [Save and test] --> [Enable on site]
```

**Happy path**: Operator completes setup in one session, validates a test prompt, and enables
frontend widget.
**Error states**: invalid API key, unsupported model, failed nonce on save, missing capability
role; each error returns inline guidance and retry action.

### Flow 2: Visitor Chat Question to Conversion Action
**Trigger**: Visitor opens chat launcher on frontend page
**Goal**: Get a grounded answer and complete a meaningful action

```text
[Open widget] --> [Submit question] --> [Runtime safety checks]
                                      |
                                      v
                             [Retrieve context + generate answer]
                                      |
                                      v
                    [Show answer + citations + suggested next actions]
                                      |
                                      v
                     [Click CTA / continue chat / clear and restart]
```

**Happy path**: Response arrives quickly, references business context, and offers clear
follow-up.
**Error states**: timeout, provider unavailable, blocked unsafe input, low-confidence retrieval;
each has fallback copy and recovery option.

### Flow 3: Operator Knowledge Ingestion and Reindex
**Trigger**: New business content or stale answers detected
**Goal**: Refresh retrieval quality without plugin downtime

```text
[Open Knowledge tab] --> [Select sources] --> [Start ingest job]
                                              |
                                              v
                                   [Track queue progress]
                                              |
                                              v
                           [Inspect failures] --> [Retry failed items]
                                              |
                                              v
                                  [Publish updated index]
```

**Happy path**: Content ingests, queue completes, retrieval quality improves in next chat tests.
**Error states**: source fetch fail, chunking error, embedding provider quota, partial index
publish; operator gets per-item remediation.

### Flow 4: Operator Diagnostics and Runtime Tuning
**Trigger**: Drop in answer quality, latency spike, or abuse event
**Goal**: Identify root cause and tune behavior safely

```text
[Open Ops dashboard] --> [Review transcript/usage slices]
                                  |
                                  v
                [Open problematic conversation details]
                                  |
                                  v
                   [Adjust guardrails or model settings]
                                  |
                                  v
                       [Run validation prompt pack]
                                  |
                                  v
                         [Publish settings update]
```

**Happy path**: Operator pinpoints issue, applies scoped fix, and confirms improvement with
validation prompts.
**Error states**: insufficient logs, conflicting settings, role restrictions, stale cache;
UI offers guided next checks.

### Flow 5: Legacy Migration to Unified Plugin
**Trigger**: Existing site uses `axiachat-ai`, `mxchat-basic`, or `baachal`
**Goal**: Migrate settings and critical data with rollback confidence

```text
[Open Migration assistant] --> [Detect source plugin]
                                     |
                                     v
                           [Preview import mapping]
                                     |
                                     v
                    [Run dry run] --> [Resolve conflicts]
                                     |
                                     v
                            [Execute migration]
                                     |
                                     v
                  [Post-migration validation + rollback checkpoint]
```

**Happy path**: Dry run passes, migration applies cleanly, chat parity checks succeed.
**Error states**: missing source data, schema mismatch, unsupported addon settings; assistant
blocks final apply until resolved.

### Flow 6: Lifecycle Change and Recovery
**Trigger**: Upgrade available or lifecycle action requested (deactivate/reactivate/uninstall)
**Goal**: Complete lifecycle operation safely with deterministic outcomes

```text
[Detect lifecycle action] --> [Run preflight checks]
                                  |
                                  v
                  [Show risk summary + backup reminder]
                                  |
                                  v
        [Apply action: upgrade/deactivate/reactivate/uninstall]
                                  |
                                  v
                     [Run post-action validation]
                                  |
                                  v
                  [Show status report + rollback guidance]
```

**Happy path**: Action completes, post-action checks pass, and operator sees clear next steps.
**Error states**: blocked preflight, partial migration, or validation failure; system surfaces
recovery guidance and rollback checkpoint details.

---

## 3. Screen Inventory

| Screen | Route/Path | Purpose | Key Components | Scope |
|--------|------------|---------|----------------|-------|
| Setup Wizard | `admin.php?page=uwpaic-setup` | Guided onboarding from activation to first deployment | Stepper, provider form, model selector, test prompt console | MVP |
| Dashboard | `admin.php?page=uwpaic-dashboard` | Operational summary and health at a glance | KPI cards, alert feed, quick actions, lifecycle status card | MVP |
| Provider and Models | `admin.php?page=uwpaic-providers` | Configure providers, keys, model defaults, fallback policy | Provider cards, key vault form, model table, priority list | MVP |
| Runtime and Safety | `admin.php?page=uwpaic-runtime` | Session, moderation, limits, and response behavior controls | Toggle groups, threshold sliders, policy preview | MVP |
| Knowledge Sources | `admin.php?page=uwpaic-knowledge` | Configure ingestion sources and indexing strategy | Source list, source config drawers, ingest action bar, inline test search | MVP |
| Queue and Index Jobs | `admin.php?page=uwpaic-jobs` | Track ingestion status and retry failures | Job table, progress bars, error detail panel | MVP |
| Widget and Shortcode | `admin.php?page=uwpaic-widget` | Brand, layout, launcher, and embed behavior | Live preview, style controls, shortcode generator | MVP |
| Actions and Integrations | `admin.php?page=uwpaic-actions` | Configure tool actions, macros, and external hooks | Action catalog, permission matrix, test runner | Deferred (phase-gated) |
| Transcripts and Usage | `admin.php?page=uwpaic-transcripts` | Review conversation quality and usage metrics | Filter bar, transcript list, split detail panel, context modal, translation controls (optional) | MVP core + optional module |
| Migration Assistant | `admin.php?page=uwpaic-migration` | Import and map legacy plugin settings/data | Source detector, mapping table, dry-run report, rollback checkpoint | MVP (late phase) |
| Security and Privacy | `admin.php?page=uwpaic-security` | Retention, export, redaction, and audit controls | Retention policy cards, export panel, access log table | MVP |
| Frontend Launcher | Site footer fixed position | Entry point for visitor conversations | Launcher button, unread badge, status indicator | MVP |
| Frontend Chat Panel | Widget overlay or inline container | Visitor conversation interface | Header, transcript body, input composer, citations, CTA chips | MVP |

---

## 4. Navigation Structure

```text
[WordPress Admin]
|-- [Ultimate AI Chatbot]
|   |-- [Setup]
|   |-- [Dashboard]
|   |-- [Provider and Models]
|   |-- [Runtime and Safety]
|   |-- [Knowledge Sources]
|   |-- [Queue and Index Jobs]
|   |-- [Widget and Shortcode]
|   |-- [Actions and Integrations]
|   |-- [Transcripts and Usage]
|   |-- [Migration Assistant]
|   \-- [Security and Privacy]
|
\-- [Frontend Surface]
    |-- [Launcher]
    \-- [Chat Panel]
```

**Navigation pattern**: Admin left menu plus tabbed sub-navigation within each major module.
Frontend uses single launcher to panel transition.
**Deep linking**: Supported for all admin screens and major sub-tabs via URL params;
unsupported for transient modals.

---

## 5. Interaction Patterns

### Forms
- Validation: both inline and on-submit
- Error display: field-level messages plus top summary banner for multi-error saves
- Success feedback: sticky success toast with "View change impact" quick link where applicable

### Modals/Dialogs
- Use modals for destructive actions, migration apply, credential replacement, and focused read-only diagnostics
- Use inline panels/drawers for routine edits
- Confirmation required for index reset, migration apply, transcript purge, and action deletion

### Loading States
- Skeletons for list and dashboard views
- Inline spinners for small state transitions (toggle save, retry job)
- Progressive loading for transcript detail and large job reports

### Notifications
- Toasts for transient success and non-blocking warnings
- Persistent banners for system-level issues (provider outage, failed migrations, permission problems)
- Inline notices for row-level errors in tables and queues

### Real-Time and Polling
- Job progress updates every 3-5 seconds with manual refresh fallback
- Transcript stream loads on demand; no background auto-refresh while operator is reading details

### Consent and Quota States
- Render consent notice inline inside the chat transcript area, not as a blocking modal
- Disable composer input, send button, and voice button until consent is accepted
- Support two quota outcomes: disable interactions with explanatory copy, or hide launcher/widget when global limit policy requires it
- Show quota status with low/near-limit/reached messaging using banner plus progress indicator

### Async Save and Long-Running Operations
- Autosave-enabled controls show inline spinner feedback, then short success state (about 800ms)
- Long operations (reindex, migration, cache clear, test runs) disable trigger buttons and swap labels to in-progress text
- Use inline progress bars for operations expected to exceed 3 seconds, including percentage and current phase text
- Polling cadence: 3-5 seconds for job queues, up to 10 seconds for deep indexing workflows

### Lifecycle and Recovery Operations
- Lifecycle actions (upgrade, deactivate, reactivate, uninstall) require preflight checks and explicit risk summary
- Uninstall flow must include data retention choice and irreversible action confirmation
- Post-action validation report is mandatory for upgrade and migration-related lifecycle paths
- Recovery guidance and rollback checkpoints must be visible immediately on failure

### Streaming and Fallback Behavior
- Use streaming response rendering when enabled and supported by selected model
- If streaming yields no content or fails, auto-fallback to non-stream response path without losing user message
- Always clear temporary typing placeholders and re-enable input controls on completion or failure

### Master-Detail and Mobile Overlay Behavior
- Transcript and action-heavy screens use desktop split-panel layout (list left, detail right)
- On mobile/tablet narrow widths, detail opens as overlay panel with explicit back action
- Preserve selected row and filter state across panel transitions

### Provider and Integration Aware Forms
- Provider selection reveals only relevant API key fields and model groups
- Keep non-selected provider inputs hidden to reduce noise and accidental edits
- Show integration availability notices (for example, WooCommerce detected or missing) with actionable next-step copy
- Disable integration toggles when required dependency is unavailable

### Validation and Debug Surfaces
- Include inline test consoles for search and retrieval verification in admin
- Expose context inspection modal with separate Sources and Actions views for transcript review
- Show cache status, cached item count preview, and guarded manual clear action

### Notification and Message Feedback
- Use toast notifications for save/update outcomes with severity icon and auto-dismiss
- Distinguish chat error messages visually from normal assistant responses
- Keep typing indicator and suggestion chips explicit, including disabled state styling

---

## 6. Motion and Animation Strategy

### Philosophy
Motion supports trust and wayfinding; it should clarify state changes, not decorate them.

### Entrance Choreography
- Page load: primary cards fade and rise in 120-180ms stagger groups
- Scroll reveals: section blocks enter with subtle y-axis translation and opacity ramp

### Interaction Feedback
- Hover states: 1-2px lift plus border contrast increase on interactive cards
- Click/tap responses: 90-120ms press-in scale to 0.98 then return
- Focus rings: 2px high-contrast ring using accent token with 2px offset

### Scroll-Driven Moments
- Frontend chat panel header condenses on scroll to preserve transcript viewport
- Citations and follow-up chips reveal in ordered sequence after answer render
- Tool execution labels reveal in sequence during long-running tool chains, then transition to result processing copy

### Animation Constraints
- Maximum 3 elements animating simultaneously per viewport region
- Minimum 0.6s duration for scroll-triggered reveals
- No linear easing; use cubic-bezier curves tuned for responsive feel
- Respect `prefers-reduced-motion` with low-motion opacity and position alternatives
- Target 60fps under 6x CPU throttling in critical chat interactions

---

## 7. Layout Philosophy

### Composition Approach
Use structured, high-legibility layouts with selective asymmetry: stable grids in admin,
conversation-forward vertical rhythm in frontend chat.

### Visual Hierarchy
- Scale contrast: medium-high, with clear dominance of primary answer content and primary admin actions
- Negative space: moderate in admin, generous in frontend conversation area
- Section rhythm: alternate dense data modules with breathing-room sections to avoid cognitive overload

### Section Transitions
Use subtle surface tone shifts and border rhythm rather than heavy visual separators;
avoid abrupt hard cuts except for alert states.

---

## 8. Responsive Strategy

| Breakpoint | Target | Layout Approach |
|------------|--------|-----------------|
| `< 640px` | Mobile | Frontend chat becomes full-height sheet; admin converts multi-column forms to single-column with sticky save bar |
| `640-1024px` | Tablet | Two-column admin where possible; chat panel uses wider composer and compact header mode |
| `> 1024px` | Desktop | Full admin data density and side-by-side panes; chat panel default width 380-420px anchored lower right |

**Approach**: Mobile-aware adaptive design (desktop-optimized admin, mobile-first chat runtime behavior).
**Touch targets**: Minimum 44x44px with 8px minimum separation for adjacent controls.

---

## 9. Accessibility

**Target**: WCAG 2.2 AA minimum

- Keyboard navigation: full tab/shift-tab traversal, no keyboard traps, visible skip links in admin modules with long tables
- Screen reader: semantic landmarks, explicit labels for all controls, polite live regions for async status updates
- Color contrast: all text and UI controls meet WCAG AA, including disabled/hover/focus states
- Focus management: focus returns to triggering control after modal close; focus sent to first actionable item on modal open
- Reduced motion: all non-essential transitions replaced with minimal opacity/state transitions when reduced motion is enabled
- State semantics: use `aria-disabled` for gated controls and `aria-pressed` for toggle/record controls where state is binary

---

## 10. Design System

### Color Architecture
- **Dominant surface** (60%): `#0F172A` deep slate canvas for structure and contrast
- **Secondary surfaces** (25%): `#111827` and `#1F2937` layered panels for hierarchy
- **Accent** (10%): `#22D3EE` cyan signal for primary interaction and focus
- **Signal colors** (5%): success `#22C55E`, warning `#F59E0B`, error `#EF4444`

Palette character: cool, synthetic, quiet baseline with sharp high-clarity accents.

### Typography
- **Display font**: `Space Grotesk` (headlines, module titles, hero prompts)
- **Body font**: `Inter` (forms, tables, chat content, help copy)
- **Monospace**: `JetBrains Mono` (model IDs, tokens, logs, diagnostics)
- **Scale ratio**: 1.25 (minor third)
- **Minimum body size**: 18px on frontend chat surfaces, 16px allowed in dense admin tables with minimum 1.5 line-height

### Spacing Scale
4px, 8px, 12px, 16px, 24px, 32px, 48px, 64px, 96px.

### Elevation and Depth
Three-level model:
- Level 0: flat base surfaces with strong border separation
- Level 1: elevated cards with soft shadow and subtle backdrop tint
- Level 2: modal/dialog layers with stronger shadow and background scrim

### Texture and Atmosphere
Mostly clean surfaces with subtle gradient noise in frontend header areas only;
admin remains crisp and low-ornament for clarity.

---

## 11. Component Patterns

| Component | Used In | Behavior |
|-----------|---------|----------|
| Stepper Wizard | Setup | Sequential onboarding with validation gates and save-and-resume |
| Provider Card | Provider and Models | Expandable card with credential status, model defaults, and test action |
| Policy Toggle Group | Runtime and Safety | Multi-toggle group with dependency warnings and inline rationale |
| Source Configuration Drawer | Knowledge Sources | Inline source edit without leaving list context |
| Job Row | Queue and Index Jobs | Progress plus retry/cancel actions with error expansion |
| Transcript Row and Detail Pane | Transcripts and Usage | Master-detail review with filter persistence |
| Translation Toolbar | Transcripts and Usage | Optional module (default off in MVP), language selector, translate action, and restore-original message state |
| Context Inspection Modal | Transcripts and Usage | Tabbed Sources and Actions analysis with loading state |
| Migration Mapping Table | Migration Assistant | Source-to-target field mapping with conflict badges |
| Lifecycle Status Card | Dashboard | Surface pending upgrade, lifecycle warnings, and validation checkpoints |
| Frontend Composer | Chat Panel | Textarea, quick prompts, submit/cancel states, token-safe counter |
| Citation Chip | Chat Panel | Expand source preview and open source detail modal |
| CTA Action Chip | Chat Panel | Route to conversion action with optional confirmation |
| Consent Bubble | Chat Panel | Inline policy message with explicit accept action before input enablement |
| Quota Banner | Chat Panel | Usage threshold warning with progress indicator and policy-specific outcome |
| Autosave Feedback Indicator | Admin forms | Field-level spinner followed by short success confirmation |

---

## 12. Anti-Patterns to Avoid

- Do not use playful gradients, rounded toy-like controls, or mascot-heavy visuals that reduce operational trust.
- Do not hide system state behind ambiguous icons; every critical state needs text plus color.
- Do not overload modals for routine edits; preserve context with inline or drawer editing.
- Do not animate large regions continuously; use event-driven motion only.
- Do not mix more than one accent color per viewport region at a time; this weakens hierarchy.

---

## 13. Open UX Questions

1. Should brand identity for GA lock to a predefined company palette and logo set,
   or remain plugin-default and neutral in v1?
2. What legal/compliance copy is required for transcript consent, retention notice,
   and AI disclosure across target markets?
3. Which quota policy should be default in GA: disable interactions with message, or hide widget entirely at site-wide cap?

---

## 14. Reference-Validated UX Requirements

The requirements below are derived from implemented behavior in the three reference plugins and should be treated as preferred baseline UX unless superseded by product decisions.

### AxiaChat-derived requirements
- Inline consent gating must disable chat controls until user acknowledgement; apply `aria-disabled` to gated controls.
  - Reference: `axiachat-ai/assets/js/aichat-frontend.js`
- Tool execution feedback must expose sequential activity labels and a processing-results fallback label.
  - Reference: `axiachat-ai/assets/js/aichat-frontend.js`
- Quota UX must support both disable-mode and hide-widget mode depending on policy scope.
  - Reference: `axiachat-ai/assets/js/aichat-frontend.js`, `axiachat-ai/includes/settings/tab-usage.php`
- Chat error response styling must be visually distinct from normal assistant responses.
  - Reference: `axiachat-ai/assets/css/aichat-frontend.css`
- Suggestion chips and typing states must include explicit disabled and in-progress visuals.
  - Reference: `axiachat-ai/assets/css/aichat-frontend.css`, `axiachat-ai/assets/js/aichat-frontend.js`

### MxChat-derived requirements
- Admin autosave interactions should provide inline spinner-to-success feedback near edited controls.
  - Reference: `mxchat-basic/js/mxchat-admin.js`
- Transcript review UX should use split-panel desktop layout with mobile overlay/back transition model.
  - Reference: `mxchat-basic/js/mxchat_transcripts.js`, `mxchat-basic/includes/admin-transcripts-page.php`
- Streaming chat mode must include robust no-content/error fallback to non-stream response flow.
  - Reference: `mxchat-basic/js/chat-script.js`
- Context inspection for transcripts should separate retrieval sources from action traces in tabbed modal UI.
  - Reference: `mxchat-basic/includes/admin-transcripts-page.php`, `mxchat-basic/js/mxchat_transcripts.js`
- Translation preferences should persist per operator and allow restoration of original transcript text.
  - Reference: `mxchat-basic/js/mxchat_transcripts.js`

### Baachal-derived requirements
- Provider selection should conditionally reveal matching credentials and model groups only.
  - Reference: `baachal/admin/tabs/general.php`, `baachal/assets/admin.js`
- Admin tab selection should persist across visits using local state with URL/hash compatibility.
  - Reference: `baachal/assets/admin.js`
- Knowledge management screens should include inline index stats and test-search console before publish.
  - Reference: `baachal/admin/tabs/content.php`
- WooCommerce integration UX should show detected/missing notices and disable unavailable toggles.
  - Reference: `baachal/admin/tabs/woocommerce.php`
- Cache tooling should expose current cache status and guarded clear action with in-progress button state.
  - Reference: `baachal/admin/tabs/search.php`, `baachal/assets/admin.js`

