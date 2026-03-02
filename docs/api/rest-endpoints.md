# REST API Reference

All endpoints use the namespace `wp-ai-chatbot/v1`. Full URL pattern: `/wp-json/wp-ai-chatbot/v1/<path>`.

Authentication follows WordPress REST API conventions. Endpoints marked **Admin** require `manage_options` capability. Endpoints marked **Public** use nonce verification or are open.

---

## Infrastructure

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/health` | Public | Runtime health check |
| POST | `/backup/export` | Admin | Export plugin data as JSON |
| POST | `/backup/import` | Admin | Import plugin data from JSON |

## Providers

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/providers/validate` | Admin | Validate an API key against provider's model listing endpoint |

**Parameters**: `provider_id` (required, string), `api_key` (optional, string — falls back to saved key).

## Sessions

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/sessions` | Public (nonce) | Create a new chat session |
| GET | `/sessions/{token}` | Public (nonce) | Get session metadata |
| DELETE | `/sessions/{token}` | Public (nonce) | Close/delete a session |
| GET | `/sessions/{token}/history` | Public (nonce) | Get full message history for a session |

**Token format**: UUID v4 (`[0-9a-f\-]{36}`).

## Knowledge Base

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/knowledge/create` | Admin | Create a knowledge base context |
| POST | `/knowledge/ingest` | Admin | Trigger ingestion pipeline for a content source |
| GET | `/knowledge/status` | Admin | Get ingestion pipeline status |
| GET | `/knowledge/search` | Admin | Search the knowledge index (cosine similarity) |
| GET | `/knowledge/web-sources` | Admin | List all web content sources |
| POST | `/knowledge/web-sources` | Admin | Create or update a web content source |
| DELETE | `/knowledge/web-sources/{id}` | Admin | Delete a web content source |

## Tools

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/tools/webhooks` | Admin | List all webhook tools |
| POST | `/tools/webhooks` | Admin | Create/update a webhook tool |
| DELETE | `/tools/webhooks/{id}` | Admin | Delete a webhook tool |
| GET | `/tools/mcp` | Admin | List all MCP servers |
| POST | `/tools/mcp` | Admin | Create/update an MCP server |
| DELETE | `/tools/mcp/{id}` | Admin | Delete an MCP server |
| POST | `/tools/mcp/{id}/test` | Admin | Test an MCP server connection |
| POST | `/tools/mcp/{id}/auth` | Admin | Configure MCP server authentication |
| GET | `/tools/catalog` | Admin | List all registered tools (native + webhook + MCP) |
| POST | `/tools/catalog/{id}/test` | Admin | Execute a tool in test mode |
| GET | `/tools/routing` | Admin | List macro routing rules |
| POST | `/tools/routing` | Admin | Create/update a macro routing rule |
| DELETE | `/tools/routing/{id}` | Admin | Delete a macro routing rule |
| GET | `/tools/bots/{slug}/tools` | Admin | List tools enabled for a specific bot |

## Chat / Leads / Live Chat

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/chat/leads` | Admin | List captured leads |
| PATCH | `/chat/leads/{id}/status` | Admin | Update lead status |
| DELETE | `/chat/leads/{id}` | Admin | Delete a lead record |
| GET | `/chat/livechat/queue` | Admin | Get live chat escalation queue |
| PATCH | `/chat/livechat/{token}/status` | Admin | Update live chat session status |

## Transcripts

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/transcripts/sessions` | Admin | Paginated list of chat sessions |
| GET | `/transcripts/sessions/{id}` | Admin | Session detail with full message thread |
| DELETE | `/transcripts/sessions/{id}` | Admin | Delete a session and its messages |
| GET | `/transcripts/usage` | Admin | Aggregated usage statistics |

**CSV export**: Handled via `admin-post.php` action `wpaic_export_transcripts` (not REST).

## Migration

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/migration` | Admin | Detect installed source plugins |
| GET | `/migration/{source}/mapping` | Admin | Get mapping table for a source plugin |
| POST | `/migration/{source}/run` | Admin | Execute migration (supports `dry_run` flag) |
| POST | `/migration/{source}/rollback` | Admin | Rollback a previous migration |

**Source values**: `axiachat`, `mxchat`, `baachal`.

**Parameters for run**: `dry_run` (boolean, default true), `overwrite_conflicts` (boolean, default false).
