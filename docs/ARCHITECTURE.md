# Architecture

## System Overview

WP AI Chatbot is a WordPress plugin that provides AI-powered chatbot capabilities. It combines patterns from three reference implementations (axiachat-ai, mxchat-basic, baachal) into a unified, production-grade plugin.

The architecture is being defined through a specification-driven phased delivery model. Phase 02 will formalize module contracts and interfaces.

## Planned Component Map

```
[WordPress Core]
     |
     v
[Plugin Bootstrap / Lifecycle]
     |
     +-- [Provider Orchestration] --> [AI Provider APIs]
     |
     +-- [Chat Runtime / Sessions]
     |
     +-- [RAG / Knowledge Pipeline] --> [Content Sources]
     |
     +-- [Tools / Actions / Macros]
     |
     +-- [WooCommerce Enrichment]
     |
     +-- [Admin / Settings / Ops]
     |
     +-- [Security / Rate Limiting]
```

## Current Infrastructure

### Health Endpoint

- **Purpose**: Runtime health checks for monitoring
- **Tech**: WP REST API
- **Location**: `lib/health-endpoint.php`
- **Endpoint**: `GET /wp-json/wp-ai-chatbot/v1/health`

### Rate Limiter

- **Purpose**: IP-based abuse prevention
- **Tech**: WP Transients
- **Location**: `lib/rate-limiter.php`

### Backup Manager

- **Purpose**: Plugin data export/import
- **Tech**: WP REST API + JSON
- **Location**: `lib/backup-manager.php`

### Structured Logger

- **Purpose**: JSON-structured logging with error capture
- **Tech**: PHP + JS (CJS)
- **Location**: `lib/structured-logger.php`, `lib/structured-logger.js`

## Tech Stack Rationale

| Technology | Purpose | Why Chosen |
|------------|---------|------------|
| PHP >=7.4 | Plugin runtime | WordPress standard; broad host compatibility |
| Vanilla JS | Frontend/admin assets | No build step required; simpler deployment |
| WP REST API | Health, backup endpoints | Native WordPress; consistent auth model |
| WP Transients | Rate limiting, caching | Built-in; works across hosting environments |
| Docker php:8.3-cli | Local PHP tooling | PHP not installed locally; consistent environment |

## Data Layer

### Storage Strategy

- **Options API**: Plugin settings and configuration
- **Transients API**: Rate limiting, temporary cache
- **Custom Tables**: Planned for chat sessions, knowledge index (Phase 03+)

### Migration Strategy

- Tool: Versioned upgrade framework (Phase 03)
- Migration paths from all three reference plugins planned (Phase 10)

## Key Decisions

Architecture Decision Records will be maintained in [docs/adr/](adr/) as the project progresses through implementation phases.

## Reference Source Analysis

Detailed feature maps for each reference plugin:

- [axiachat-ai Feature Map](axiachat-ai-feature-map.md)
- [mxchat-basic Feature Map](mxchat-basic-feature-map.md)
- [baachal Feature Map](baachal-feature-map.md)
