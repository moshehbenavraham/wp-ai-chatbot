# CONVENTIONS.md

## Guiding Principles

- Optimize for readability and predictable WordPress behavior over clever abstractions.
- Preserve migration safety and backward compatibility unless a phase explicitly allows breaking changes.
- Prefer incremental, reversible changes that align with phased delivery gates.
- Treat security and privacy as default requirements, not polish tasks.
- Make no assumptions: verify behavior in code paths, docs, and runtime checks.

## Naming

- Prefix globally scoped functions, hooks, options, and transients with project slug to avoid collisions.
- Use `snake_case` for procedural PHP functions and WordPress-style keys.
- Use `PascalCase` for PHP class names and `camelCase` for local variables/methods.
- Booleans read as questions: `is_enabled`, `has_access`, `should_retry`.
- Match domain terms from PRD and phase docs for providers, runtime, RAG, tools, and migration.

## Files and Structure

- Keep a clear split between plugin bootstrap, domain logic, admin UI logic, and frontend runtime assets.
- Group by domain capability where practical (provider, runtime, rag, tools, migration).
- Keep new modules cohesive: one primary responsibility per file/class.
- Avoid deep nesting when equivalent flat domain folders are possible.

## PHP Conventions

- Follow WordPress PHP standards and keep syntax compatible with the minimum supported PHP version.
- Prefer explicit return values and guard clauses for readability.
- Avoid hidden side effects in constructors; wire hooks in dedicated bootstrap methods.
- Pass dependencies explicitly to services where practical instead of relying on global state.
- Keep public method signatures stable once phase contracts are published.

## WordPress Integration

- Register hooks in one place per module so event flow is discoverable.
- Every privileged action must enforce capability checks before processing.
- Verify nonces for state-changing requests from admin or frontend surfaces.
- Sanitize on input, validate by type/rules, and escape on output.
- Use i18n wrappers for user-facing strings in admin and widget UI.

## Data and Storage

- Treat schema changes as versioned migrations with explicit upgrade paths.
- Use `$wpdb->prepare()` for dynamic SQL and avoid string-concatenated queries.
- Namespace options, transients, and cron events with plugin-specific prefixes.
- Define retention and cleanup behavior for sessions, logs, and temporary artifacts.
- Design migration routines to be resumable and safe to rerun.

## JavaScript Conventions

- Keep admin and frontend scripts separate to reduce cross-surface coupling.
- Use `camelCase` for variables/functions and descriptive event handler names.
- Isolate network and DOM update logic into small functions for testability.
- Avoid framework assumptions unless adopted explicitly by project decision.
- Keep browser-delivered assets production-ready without requiring hidden build steps.

## Endpoints and Request Handling

- Use consistent request contracts across AJAX and REST handlers.
- Validate auth, nonce, and capabilities before executing endpoint business logic.
- Return structured, actionable error payloads that support admin diagnostics.
- Keep provider calls behind service boundaries; endpoints should orchestrate, not embed provider logic.

## Security and Privacy

- Minimize stored personal data and document purpose for retained fields.
- Do not log secrets, API keys, or raw sensitive payloads.
- Record security-sensitive operations in auditable logs with context but without secret leakage.
- Default to least privilege for admin actions and integration credentials.

## Performance and Reliability

- Keep request-path work bounded; move expensive ingestion/indexing operations to queued/background flows.
- Cache predictable lookups where safe and invalidate deterministically.
- Add retry/backoff for external calls and queue processing with bounded attempts.
- Instrument key runtime paths so regressions are visible during hardening phases.

## Error Handling

- Fail fast in development and return safe, user-readable failures in production.
- Include enough context in errors for debugging without exposing secrets.
- Avoid silent catches; either handle, escalate, or record with rationale.
- Use a consistent internal error shape for provider, storage, and endpoint failures.

## Testing

- Test behavior at integration boundaries: lifecycle, endpoints, retrieval, and migration flows.
- Include regression tests for known migration and security edge cases.
- Keep manual smoke-test checklists for admin setup, widget runtime, and upgrade paths.
- If a feature is hard to test, simplify module boundaries before adding complexity.

## Dependencies

- Keep dependencies minimal and justified by clear product value.
- Prefer mature libraries already proven in WordPress plugin environments.
- Pin and review dependency updates intentionally; avoid opportunistic upgrades during feature sessions.
- Isolate third-party adapters behind interfaces to reduce lock-in.

## Documentation and Specs

- Keep PRD, phase docs, and session specs aligned with implementation reality.
- Update assumptions, risks, and open questions when decisions materially change.
- Record migration and compatibility decisions close to implementation notes.
- Document new config keys, hooks, and endpoints in the same session that introduces them.

## Git and Review Workflow

- One logical change per commit; keep commits safe to revert.
- Branch naming: `type/short-description` (for example `feat/provider-registry`).
- PR descriptions must explain what changed, why it changed, and migration/security implications.
- Review comments should be specific, actionable, and focused on code behavior.

## Stack

| Runtime | Version | Package Manager |
|---------|---------|-----------------|
| PHP | >=7.4 (via Docker php:8.3-cli) | Composer (via Docker) |
| JavaScript | Vanilla (no build) | npm (via nvm) |
| Node.js | v22.22.0 | npm 10.9.4 |
| Python | 3.12.3 | pip |

## Local Dev Tools

| Category | Tool | Config |
|----------|------|--------|
| Formatter | Biome (JS), PHP-CS-Fixer (PHP via Docker) | biome.json, .php-cs-fixer.dist.php |
| Linter | Biome (JS), PHPStan (PHP via Docker) | biome.json, phpstan.neon |
| Type Safety | PHPStan (PHP, shared with Linting); N/A (vanilla JS) | phpstan.neon |
| Testing | PHPUnit 10 (PHP via Docker), Vitest 4 (JS) | phpunit.xml.dist, vitest.config.js |
| Observability | Structured Logger (PHP + JS) | lib/structured-logger.php, lib/structured-logger.js, logs/ |
| Git Hooks | husky 9 + lint-staged 16 | .husky/pre-commit, package.json (lint-staged) |

## CI/CD

| Bundle | Status | Workflow |
|--------|--------|----------|
| Code Quality | configured | .github/workflows/quality.yml |
| Build & Test | configured | .github/workflows/test.yml |
| Security | not configured | - |
| Integration | not configured | - |
| Operations | not configured | - |

Platform: GitHub Actions

## Infrastructure

| Component | Provider | Details |
|-----------|----------|---------|
| Platform | WordPress Plugin | PHP >=7.4, self-hosted WP installations |
| Health | WP REST API | `GET /wp-json/wp-ai-chatbot/v1/health` — PHP, WP, DB, extensions, memory |
| Security | WP Transient Rate Limiter | `lib/rate-limiter.php` — IP-based, per-endpoint namespace, configurable limits, proxy-aware |
| Backup | WP REST API + JSON Export | `lib/backup-manager.php` — versioned export/import of plugin options via REST endpoints, admin-only |
| Deploy | GitHub Actions + GitHub Releases | `.github/workflows/release.yml` — tag `v*` triggers rsync build + ZIP publish via `softprops/action-gh-release@v2` |

## When In Doubt

- Ask before making irreversible architecture or migration choices.
- Choose clarity over abstraction and safety over speed.
- Leave the codebase easier to understand than you found it.
