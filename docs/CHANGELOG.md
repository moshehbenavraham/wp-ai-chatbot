# Changelog

All notable changes to the Ultimate WP AI Chatbot plugin are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Usage:** When completing work, append entries under the `[Unreleased]` section.
> Move entries to a versioned heading at release time.

---

## [Unreleased]

### Added
- Project scaffolding: Biome, PHP-CS-Fixer, PHPStan, PHPUnit, Vitest
- Structured logger (PHP + JS) in `lib/`
- Health endpoint (`lib/health-endpoint.php`)
- Rate limiter (`lib/rate-limiter.php`)
- Backup manager (`lib/backup-manager.php`)
- CI/CD: quality, test, security, integration, release workflows
- Git hooks via husky + lint-staged
- Phase 02 architecture contracts (module map, DB schema, provider/chat/RAG/security contracts)

### Changed

### Fixed

### Removed

---

<!--
## [0.1.0] - YYYY-MM-DD

### Added
- First tagged release items go here

[Unreleased]: https://github.com/OWNER/wp-ai-chatbot/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/OWNER/wp-ai-chatbot/releases/tag/v0.1.0
-->
