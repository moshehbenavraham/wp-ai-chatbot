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
|-- axiachat-ai/           # Reference plugin (read-only)
|-- baachal/               # Reference plugin (read-only)
|-- mxchat-basic/          # Reference plugin (read-only)
|-- docs/                  # Project documentation and feature maps
|-- lib/                   # Shared infrastructure libraries
|-- tests/                 # PHP and JS test suites
|-- .spec_system/          # Specification-driven delivery system
|-- .github/workflows/     # CI/CD pipelines
\-- logs/                  # Runtime log output (gitignored)
```

## Documentation

- [Getting Started](docs/onboarding.md)
- [Development Guide](docs/development.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Deployment](docs/deployment.md)
- [Contributing](CONTRIBUTING.md)

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

Phases 00-01 (planning and capability scoring) are complete. Phase 02 (Target Architecture and Module Contracts) is in progress (1/7 sessions). See [PRD](.spec_system/PRD/PRD.md) for the full phase plan and roadmap.

### Phase Progress

| Phase | Name | Status |
|-------|------|--------|
| 00 | Program Setup and Extraction Schema | Complete |
| 01 | Canonical Capability Matrix and Scoring | Complete |
| 02 | Target Architecture and Module Contracts | In Progress (1/7) |
| 03-11 | Implementation through GA Readiness | Planned |
