# WP AI Chatbot

Build one ultimate WordPress AI chatbot plugin by combining the strongest implemented features from three reference plugins (axiachat-ai, mxchat-basic, baachal) while improving architecture, security, performance, migration safety, and maintainability.

## Quick Start

```bash
# Install dependencies
npm install
docker run --rm -v "$(pwd):/app" -w /app composer:2 install

# Run all checks
npm test
```

## Repository Structure

```
.
|-- wp-ai-chatbot.php      # Main plugin bootstrap (constants, lifecycle hooks)
|-- uninstall.php          # Clean uninstall handler
|-- includes/              # Plugin PHP classes (autoloaded via WPAIC\ namespace)
|   |-- admin/             # Admin pages, router, settings manager
|   |-- migration/         # Lifecycle manager, migration registry, migrators
|   |-- provider/          # Provider interface, registry, adapters (OpenAI/Claude/Gemini)
|   |-- rag/               # Content sources, embeddings, chunker, ingestion, retrieval
|   |-- runtime/           # Session manager, message pipeline, output formatter
|   |-- security/          # Encryption, sanitizer, auth enforcer, audit logger, moderator
|   |-- tools/             # Tool interface, registry, executor, continuation loop
|   |-- mcp/               # MCP client, registry, tool adapter
|   |-- woocommerce/       # WooCommerce/HPOS integration
|   \-- integrations/      # Third-party integrations
|-- admin/                 # Admin CSS/JS assets
|-- assets/                # Frontend CSS/JS/images
|-- templates/             # PHP templates (chatbot-widget.php)
|-- lib/                   # Shared infrastructure (health, rate limiter, backup, logger)
|-- tests/                 # PHP and JS test suites (215 PHP + 26 JS)
|-- docs/                  # Project documentation
|-- languages/             # i18n (POT file)
|-- .spec_system/          # Specification-driven delivery artifacts
|-- .github/workflows/     # CI/CD pipelines (6 workflows)
\-- logs/                  # Runtime log output (gitignored)
```

## Documentation

- [Getting Started](docs/onboarding.md)
- [Development Guide](docs/development.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Admin Guide](docs/admin-guide.md)
- [Migration Guide](docs/migration-guide.md)
- [REST API Reference](docs/api/rest-endpoints.md)
- [Deployment](docs/deployment.md)
- [Contributing](CONTRIBUTING.md)
- [Known Limitations](docs/known-limitations.md)
- [Changelog](docs/CHANGELOG.md)

## Tech Stack

| Technology | Purpose |
|------------|---------|
| PHP >=7.4 | WordPress plugin backend |
| JavaScript (vanilla) | Admin and frontend assets, no build tools |
| Docker (php:8.3-cli) | Local PHP tooling (PHPUnit, PHP-CS-Fixer, PHPStan) |
| Node.js v22 | JS tooling (Biome, Vitest, husky) |
| GitHub Actions | CI/CD (quality, testing, releases) |

## Dev Scripts

| Command | Purpose |
|---------|---------|
| `npm test` | Run all tests (JS + PHP) |
| `npm run check` | Run all Biome checks (format + lint) |
| `npm run lint:php` | Run PHPStan static analysis |
| `npm run format` | Auto-format JS + PHP |

## Project Status

All 21 phases are complete. The plugin is GA-ready. See [GA Checklist](docs/ga-checklist.md) for full readiness status and [PRD](.spec_system/PRD/PRD.md) for the original phase plan.

### Phase Summary

| Phases | Scope | Status |
|--------|-------|--------|
| 00-02 | Planning, capability scoring, architecture contracts | Complete |
| 03-07 | Core skeleton, providers, runtime, RAG, tools | Complete |
| 08-11 | WooCommerce, security hardening, migration, GA docs | Complete |
| 12-14 | Design system, admin UX, WCAG 2.2 AA accessibility | Complete |
| 15-16 | PDF/web sources, transcripts, usage dashboard | Complete |
| 17-21 | Advanced integrations, test suite, logging, final QA | Complete |
