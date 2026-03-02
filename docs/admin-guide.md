# WP AI Chatbot — Administrator Guide

> Version 1.0.2 · Last updated 2026-03-02

---

## Quick Start

1. **Install & activate** the plugin from WordPress Admin > Plugins > Add New.
2. Navigate to **WP AI Chatbot > General Settings** and enter your AI provider API key.
3. Add `[wpaic_chatbot]` to any page or post. The floating widget is also injected in the site footer by default.

---

## Admin Menu Overview

| Menu Item | Description |
|-----------|-------------|
| General Settings | Provider selection, API keys, default model, AI disclosure |
| Appearance | Color palettes, custom tokens, widget position, fonts, branding |
| Knowledge Base | RAG content sources, ingestion status, embedding configuration |
| Tools | Tool/function-calling configuration (webhooks, MCP servers, catalog) |
| Transcripts & Usage | Chat session history, usage stats, CSV export |
| Security & Privacy | Rate limiting, data retention, keyword moderation |
| Diagnostics | Audit logs, health checks, system info |
| Migration | Import settings from axiachat-ai, mxchat-basic, or baachal |
| Chat | Bot configuration and management |

---

## General Settings

### Provider Configuration

| Option | Description | Default |
|--------|-------------|---------|
| Default AI Provider | Active AI provider | OpenAI |
| API Keys | Per-provider credentials (AES-256-GCM encrypted at rest) | — |
| Default Model | Model used when not overridden by shortcode | `gpt-4o-mini` |
| Request Timeout | Seconds before provider request times out | 30 |

### Legal & Compliance

| Option | Description | Default |
|--------|-------------|---------|
| AI Disclosure Notice | Display a non-dismissible AI notice in the widget header | Disabled |
| Disclosure Text | Text of the AI disclosure message | See below |

**Default disclosure text:**
> "You are chatting with an AI assistant. Your messages are processed to generate responses."

**Compliance notes:**
- Required for EU deployments under **EU AI Act Article 50** (effective 1 August 2026).
- Required for bots that interact with California consumers under **CA SB 1001** (effective 1 July 2024).
- The notice is displayed non-dismissibly in the widget header above the chat transcript.

---

## Widget Shortcode

```
[wpaic_chatbot bot_slug="default" mode="floating"]
```

| Attribute | Values | Default |
|-----------|--------|---------|
| `bot_slug` | Any configured bot slug | `default` |
| `mode` | `floating`, `inline` | `floating` |
| `title` | Widget header title | From options |
| `placeholder` | Input placeholder text | From options |
| `gdpr` | `0`, `1` | From options |

---

## Security & Privacy

### Rate Limiting

Rate limiting is IP-based using WordPress transients. Limits apply per `bot_slug`.

| Option | Description | Default |
|--------|-------------|---------|
| Enable Rate Limiting | Global on/off switch | Enabled |
| Max Requests | Maximum messages allowed per window | 20 |
| Window (seconds) | Rolling time window | 60 |
| Rate Limit Message | Message shown to user when limited | See Security page |
| Fallback Email | Contact email shown on rate limit | — |
| Fallback Phone | Contact phone shown on rate limit | — |
| Fallback URL | Link to contact page or live chat | — |

When rate limited, the chat input is disabled and the configured message (with any fallback contact options) is displayed in the transcript. The widget remains visible.

### Data Retention

Chat transcripts are retained for 90 days and automatically deleted. Anonymized usage statistics may be retained indefinitely.

| Option | Description | Default |
|--------|-------------|---------|
| Retain Chat Sessions For | Days before sessions are purged | 90 |
| Anonymise IP in Audit Logs | Truncate IPs to first three octets | Disabled |

**Important:** Setting retention to `0` retains sessions indefinitely.

### GDPR Tools

WP AI Chatbot integrates with WordPress's built-in Personal Data tools:
- **Export**: exports authenticated user sessions + lead data
- **Erasure**: permanently deletes sessions + lead records

Access via **Tools > Export Personal Data / Erase Personal Data**.

---

## Knowledge Base (RAG)

1. Navigate to **Knowledge Base**.
2. Add a content source (WordPress Posts/Pages is enabled by default).
3. Click **Ingest** to process content into vector chunks.
4. The chatbot will automatically retrieve relevant context from the knowledge base.

**Settings:**
- **Embedding Provider**: OpenAI (`text-embedding-3-small` by default, requires OpenAI API key)
- **Chunk Size**: Maximum characters per chunk (default: 4,000)
- **Overlap**: Character overlap between adjacent chunks (default: 200)

---

## Tools & Function Calling

Tools must be globally enabled via **Security & Privacy > Security** (disabled by default).

### Webhook Tools
Configure custom webhook endpoints that the AI can call. Each webhook has:
- Name, description, HTTP method, URL
- Static parameters merged with AI-provided inputs
- SSRF protection: RFC-1918 / localhost URLs are blocked

### MCP Servers
Connect to Model Context Protocol (MCP) servers for richer integrations.

---

## Appearance

Navigate to **WP AI Chatbot > Appearance** to customize the widget's visual design.

### Palette Tab

Choose from 8 preset color palettes or define custom hex values.

| Palette | Description |
|---------|-------------|
| Dark Teal (default) | Deep navy with cyan accent |
| Dark Violet | Deep purple with violet accent |
| Dark Emerald | Dark green with emerald accent |
| Dark Rose | Dark red with rose accent |
| Light Cloud | Light gray with blue accent |
| Light Sand | Warm light with amber accent |
| Light Mint | Light green with teal accent |
| Light Lavender | Light purple with violet accent |

**Custom overrides**: Set individual hex values for `bg_deep`, `bg_card`, `bg_input`, `accent`, and `accent_hover` to override the active palette.

### Display Tab

| Option | Description | Default |
|--------|-------------|---------|
| Widget Position | `bottom-right`, `bottom-left` | `bottom-right` |
| Show Powered By | Display "Powered by WP AI Chatbot" footer | Enabled |
| Load Google Fonts | Load Inter + JetBrains Mono from CDN | Disabled |

---

## Transcripts & Usage

Navigate to **WP AI Chatbot > Transcripts & Usage** to review chat history and usage data.

### Chat Transcripts Tab

- Paginated list of all chat sessions (search by session token or user)
- Click any session to open a drawer with the full message transcript
- **Inspect** button shows RAG source context and tool actions used per message
- Delete individual sessions

### Usage & Cost Tab

- Daily aggregated statistics from `wpaic_usage_daily`
- Breakdown by provider and model
- 7-day and 30-day total summaries (tokens, cost in microcents)

### Export Tab

- Date-range CSV export of chat transcripts
- Downloaded via `admin-post.php` (admin authentication required)

---

## Diagnostics

### Audit Logs
Shows security events (auth failures, rate limits, key changes) from the last 7 days of structured log files.

### Health Check
Live verification of: PHP version, WordPress version, database connectivity, required PHP extensions, memory limits.

### System Info
Copyable system information block for support tickets.

---

## Structured Logging

Log files are written to `wp-content/plugins/wp-ai-chatbot/logs/` as daily JSON files (`app-YYYY-MM-DD.log`). Files older than **30 days** are automatically pruned by the daily cron job.

---

## Cron Jobs

| Event | Frequency | Purpose |
|-------|-----------|---------|
| `wpaic_daily_cleanup` | Daily | Prune expired sessions, old leads, log files |
| `wpaic_process_ingestion` | Every 5 minutes | Process pending RAG ingestion batches |
