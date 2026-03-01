# WP AI Chatbot — Known Limitations & Deferred Backlog

> Version 1.0.1 · Last updated 2026-03-01

---

## Known Limitations

### AI Providers

| Limitation | Details | Workaround |
|------------|---------|------------|
| DeepSeek/Grok embedding | Neither provider offers a production embedding API | Use OpenAI `text-embedding-3-small` for RAG |
| Streaming + tools | Streaming (SSE) is automatically disabled when tool calling is active | Expected behaviour; sync responses are used |
| Tool continuation max loops | Capped at 5 (`wpaic_tools_max_loops`) to prevent infinite loops | Filter `wpaic_tools_max_loops` to raise the cap |
| Model registry staleness | Model lists are hardcoded; new provider models require a plugin update | Filter `wpaic_model_registry_models` to add models dynamically |

### RAG / Knowledge Base

| Limitation | Details | Workaround |
|------------|---------|------------|
| PHP cosine similarity scaling | PHP-based vector search scales to ~50K chunks; larger indexes slow | Hook `wpaic_rag_search_results` to offload to a vector DB |
| Scanned PDF OCR | Text-only PDF extraction; scanned documents not supported | Out of scope |
| Embedding re-ingestion | Changing the embedding model requires full re-ingestion | Delete chunks and re-ingest |
| smalot/pdfparser optional | PDF text extraction uses smalot/pdfparser when present; falls back to pure-PHP BT…ET extractor | Run `composer install` to enable full PDF extraction |

### Tools & Integrations

| Limitation | Details | Workaround |
|------------|---------|------------|
| SSRF protection | Webhook tools block all RFC-1918 / localhost URLs | Run webhooks on public endpoints |
| Appointment scheduling | Bookly/Amelia integration not implemented | Deferred — see backlog below |
| Translation toolbar | Language auto-detect and response translation not implemented | Deferred — see backlog below |

### Admin / UI

| Limitation | Details | Workaround |
|------------|---------|------------|
| Usage chart/graph | Transcripts screen shows stat cards + table only; no visual chart | Deferred — see backlog below |
| Full permission matrix | Tools × roles permission grid not implemented | Deferred — see backlog below |

### Security & Compliance

| Limitation | Details | Workaround |
|------------|---------|------------|
| 5-year consent record retention | Client-side GDPR consent is stored in localStorage only; no server-side consent audit log | Implement a `wpaic_consent_records` DB table (future phase) |
| Anonymous session export | WP GDPR Personal Data Export only covers authenticated user sessions | By design: anonymous sessions cannot be linked to a user identity |

### WooCommerce

| Limitation | Details | Workaround |
|------------|---------|------------|
| WooCommerce 3.x | HPOS (High-Performance Order Storage) compatibility targets WooCommerce 7.1+; WC 3.x not tested | Upgrade to WooCommerce 7.1+ |
| Product search scope | WC product search tool returns published products only | Filter `wpaic_wc_product_query_args` to adjust |

---

## Deferred Backlog (Future Work)

### Appointment Scheduling
- Bookly/Amelia integration — 8-10 new files; complex calendar/availability API surface
- Out of scope for current GA milestone

### Translation Toolbar
- Language auto-detect on user input + response translation
- Optional module; out of scope for current GA milestone

### Usage Chart / Dashboard
- Visual chart library integration (sessions/messages/tokens over time)
- Current dashboard shows stat cards + daily breakdown table only

### Full Permission Matrix
- Tools × roles grid (tool assignment per WP user role)
- Current model: per-bot tool assignment via `wpaic_bot_tools_{slug}` option

### Server-Side GDPR Consent Records
- 5-year consent audit log with `wpaic_consent_records` DB table
- Current implementation: localStorage consent flag per bot slug (client-side only)
