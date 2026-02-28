# Reference Inventory Baseline

**Session ID**: `phase00-session01-reference-inventory-baseline`  
**Purpose**: Canonical source inventory and key entry-point baseline for Phase 00 downstream sessions.

---

## Scope

This inventory tracks the three canonical reference plugins and their mapped feature documents.  
Source repositories are read-only and are referenced only for traceable extraction.

---

## Canonical Source Set

| Source ID | Plugin | Root Path | Main Entry | Readme | Feature Map |
|---|---|---|---|---|---|
| `SRC-AXI` | AxiaChat AI | `axiachat-ai/` | `axiachat-ai/axiachat-ai.php` | `axiachat-ai/readme.txt` | `docs/axiachat-ai-feature-map.md` |
| `SRC-MXC` | MxChat Basic | `mxchat-basic/` | `mxchat-basic/mxchat-basic.php` | `mxchat-basic/readme.txt` | `docs/mxchat-basic-feature-map.md` |
| `SRC-BAA` | Baachal | `baachal/` | `baachal/baachal.php` | `baachal/readme.txt` | `docs/baachal-feature-map.md` |

---

## Inventory Template

### Source `SRC-AXI` - AxiaChat AI

#### Root Structure Snapshot

- Bootstrap and distribution files:
  - `axiachat-ai/axiachat-ai.php`
  - `axiachat-ai/readme.txt`
- Core modules under `axiachat-ai/includes/`:
  - provider abstractions and model registry
  - runtime AJAX orchestration
  - training/context and RAG pipeline
  - settings, bots, usage, logs, cron
  - add-ons (`ai-tools`, `mcp`, `livechat`, `leads`, `appointments`, `custom-webhooks`, `web-scraper`)
- Frontend/admin assets under `axiachat-ai/assets/`:
  - widget, embed loader, settings/training/admin scripts
  - companion style bundles
- Third-party dependencies under `axiachat-ai/vendor/` (reference-only, not anchor extraction targets).

#### Key Entry Points

| Inventory ID | Path | Scope |
|---|---|---|
| `INV-AXI-001` | `axiachat-ai/axiachat-ai.php` | Bootstrap, lifecycle hooks, module loading |
| `INV-AXI-002` | `axiachat-ai/includes/class-aichat-provider-registry.php` | Provider registration and dispatch |
| `INV-AXI-003` | `axiachat-ai/includes/class-aichat-ajax.php` | Chat runtime pipeline and tool continuation |
| `INV-AXI-004` | `axiachat-ai/includes/contexto-functions.php` | RAG retrieval, chunk assembly, embedding flow |
| `INV-AXI-005` | `axiachat-ai/includes/settings.php` | Option registration, admin settings contract |
| `INV-AXI-006` | `axiachat-ai/includes/aichat-cron.php` | Background indexing/autosync maintenance |
| `INV-AXI-007` | `axiachat-ai/includes/add-ons/ai-tools/api.php` | Tool registry and provider-native tool injection |
| `INV-AXI-008` | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` | MCP transport lifecycle and execution |
| `INV-AXI-009` | `axiachat-ai/includes/add-ons/livechat/class-livechat-manager.php` | Human handoff session orchestration |
| `INV-AXI-010` | `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` | External webhook tool execution boundary |

#### Notes

- Domain coverage is broad; downstream sessions should prioritize contract and boundary files over UI-only files.
- `vendor/` paths are intentionally excluded from anchor promotion unless a runtime dependency edge case is documented.
- AxiaChat feature-map revisions contain repeated sections; treat canonical file paths as source of truth for anchor IDs.

---

### Source `SRC-MXC` - MxChat Basic

#### Root Structure Snapshot

- Bootstrap and distribution files:
  - `mxchat-basic/mxchat-basic.php`
  - `mxchat-basic/readme.txt`
- Core runtime modules under `mxchat-basic/includes/`:
  - integrator, admin/public controllers, WooCommerce layer
  - content generator, chunking, user/session utilities
  - admin page templates and add-on catalog integration
- Admin manager layer under `mxchat-basic/admin/`:
  - AJAX handler
  - knowledge manager
  - Pinecone manager
- Frontend/admin assets:
  - `mxchat-basic/js/`
  - `mxchat-basic/css/`
  - `mxchat-basic/images/`
  - `mxchat-basic/languages/`

#### Key Entry Points

| Inventory ID | Path | Scope |
|---|---|---|
| `INV-MXC-001` | `mxchat-basic/mxchat-basic.php` | Bootstrap, lifecycle, compatibility filters |
| `INV-MXC-002` | `mxchat-basic/includes/class-mxchat-integrator.php` | Provider routing, chat orchestration, action execution |
| `INV-MXC-003` | `mxchat-basic/includes/class-mxchat-admin.php` | Settings sanitization and admin control surface |
| `INV-MXC-004` | `mxchat-basic/includes/class-mxchat-public.php` | Frontend widget/shortcode rendering path |
| `INV-MXC-005` | `mxchat-basic/admin/class-knowledge-manager.php` | RAG ingestion and queue processing controls |
| `INV-MXC-006` | `mxchat-basic/includes/class-mxchat-woocommerce.php` | WooCommerce domain operations and action helpers |
| `INV-MXC-007` | `mxchat-basic/includes/class-mxchat-content-generator.php` | Content generation subsystem and background flow |
| `INV-MXC-008` | `mxchat-basic/admin/class-ajax-handler.php` | Admin AJAX boundary and operations dispatch |

#### Notes

- MxChat merges runtime and admin concerns across multiple classes; downstream extraction should anchor by behavior boundary, not file size.
- The plugin includes broad provider/model support and many AJAX actions, so endpoint inventory anchors are high-value for taxonomy work.
- `includes/pdf-parser/` is third-party parser code and should remain reference-only for behavior mapping.

---

### Source `SRC-BAA` - Baachal

#### Root Structure Snapshot

- Bootstrap and distribution files:
  - `baachal/baachal.php`
  - `baachal/readme.txt`
  - `baachal/uninstall.php`
- Core modules:
  - `baachal/includes/content-indexer.php`
  - `baachal/admin/settings-page.php`
  - tab-specific admin configuration under `baachal/admin/tabs/`
- Frontend/admin assets:
  - `baachal/templates/chatbot-widget.php`
  - `baachal/assets/chatbot.js`
  - `baachal/assets/chatbot.css`
  - `baachal/assets/admin.js`
  - `baachal/assets/admin.css`
- Localization artifact:
  - `baachal/languages/baachal.pot`

#### Key Entry Points

| Inventory ID | Path | Scope |
|---|---|---|
| `INV-BAA-001` | `baachal/baachal.php` | Bootstrap, hooks, provider APIs, WooCommerce integration |
| `INV-BAA-002` | `baachal/includes/content-indexer.php` | Content indexing schema and search logic |
| `INV-BAA-003` | `baachal/admin/settings-page.php` | Settings save flow and validation rules |
| `INV-BAA-004` | `baachal/admin/tabs/woocommerce.php` | WooCommerce options and product-search controls |
| `INV-BAA-005` | `baachal/templates/chatbot-widget.php` | Frontend widget markup and render hooks |
| `INV-BAA-006` | `baachal/assets/chatbot.js` | Client runtime, message send/receive, session state |
| `INV-BAA-007` | `baachal/uninstall.php` | Uninstall and cleanup behavior boundary |

#### Notes

- Baachal is compact compared with AxiaChat and MxChat; key anchors are concentrated in a small set of files.
- Product search and content indexing are central capability paths for taxonomy and context-pack reuse.
- WooCommerce behavior can be absent on non-WooCommerce sites, so downstream sessions should mark this as conditional coverage when needed.

---

## ID Rules

- Source IDs use `SRC-<3 letter code>` format.
- Inventory row IDs use `INV-<source>-<3 digit sequence>`.
- Anchor references use anchor IDs from `anchor_index.md`.
