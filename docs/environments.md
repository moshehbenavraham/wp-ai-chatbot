# Environments

## Environment Overview

| Environment | URL | Purpose |
|-------------|-----|---------|
| Development | localhost | Local development via Docker |
| CI | GitHub Actions | Automated quality, testing, releases |
| Production | WordPress sites | Self-hosted WP installations |

## Configuration Differences

| Config | Development | CI | Production |
|--------|------------|-----|------------|
| PHP version | 8.3 (Docker) | 8.4 | >=7.4 (host) |
| PHPStan level | 0 | 0 | N/A |
| Debug logging | Enabled | Disabled | Configurable |

## Development Environment

### PHP Tooling (Docker-based)

- PHPUnit, PHPStan: `php:8.3-cli`
- PHP-CS-Fixer: `php:8.4-cli` (matches CI)
- Composer: `composer:2`

### JS Tooling (Node.js-based)

- Biome: Formatter + linter
- Vitest: Test runner
- husky + lint-staged: Pre-commit hooks

## CI Environment

Three GitHub Actions workflows:

| Workflow | Trigger | File |
|----------|---------|------|
| Code Quality | Push/PR | `.github/workflows/quality.yml` |
| Build & Test | Push/PR | `.github/workflows/test.yml` |
| Release | Tag `v*` | `.github/workflows/release.yml` |

## Production Notes

The plugin targets WordPress installations with PHP >=7.4. No build step is required -- the release workflow packages a ZIP artifact via GitHub Releases.
