# Development Guide

## Local Environment

### Required Tools

- Node.js v22+ (via nvm)
- Docker 29+ (for PHP tooling)
- Git

### No Local PHP Required

All PHP tooling runs in Docker containers:

- `php:8.3-cli` for PHPUnit, PHPStan
- `php:8.4-cli` for PHP-CS-Fixer (matches CI)
- `composer:2` for dependency management

## Dev Scripts

| Command | Purpose |
|---------|---------|
| `npm test` | Run all tests (JS + PHP) |
| `npm run test:js` | Run Vitest JS tests |
| `npm run test:php` | Run PHPUnit PHP tests |
| `npm run format` | Auto-format JS + PHP |
| `npm run format:js` | Format JS with Biome |
| `npm run format:php` | Format PHP with PHP-CS-Fixer |
| `npm run check` | Run all Biome checks |
| `npm run check:format` | Check formatting (no write) |
| `npm run lint:js` | Lint JS with Biome |
| `npm run lint:php` | Run PHPStan static analysis |
| `npm run check:lint:js` | Lint JS (read-only) |
| `npm run check:lint:php` | PHPStan with table output |

## Testing

### PHP Tests

```bash
# Run all PHP tests
npm run test:php

# Run specific test file
docker run --rm -v "$(pwd):/app" -w /app php:8.3-cli vendor/bin/phpunit --filter BackupManagerTest
```

Test stubs for WordPress functions live in `tests/php/stubs/`. WP functions must be stubbed in separate non-namespaced files.

### JS Tests

```bash
# Run all JS tests
npm run test:js

# Run with coverage
npx vitest run --coverage
```

JS test files must use ESM `import` syntax (Vitest v4 does not support CJS imports in test files).

## Configuration Files

| File | Purpose |
|------|---------|
| `biome.json` | JS formatter/linter (scoped to plugin dirs + lib/ + tests/js/) |
| `.php-cs-fixer.dist.php` | PHP formatter (PSR-12 + WordPress-friendly) |
| `phpstan.neon` | PHPStan (level 0, WP+WC stubs) |
| `phpunit.xml.dist` | PHPUnit 10 config |
| `vitest.config.js` | Vitest 4 config |

## Git Hooks

Pre-commit hooks are managed by husky 9 + lint-staged 16:

- JS files: Biome check
- PHP files: PHP-CS-Fixer via Docker

The hook is configured in `.husky/pre-commit`.

## Development Workflow

1. Pull latest `main`
2. Create feature branch (`feature/description`)
3. Make changes
4. Run tests: `npm test`
5. Run checks: `npm run check`
6. Commit (pre-commit hooks run automatically)
7. Open PR

## Debugging

### PHP test failures

Run with verbose output:

```bash
docker run --rm -v "$(pwd):/app" -w /app php:8.3-cli vendor/bin/phpunit --configuration=phpunit.xml.dist --verbose
```

### Error capture logs

The structured logger writes error captures to `logs/last_error_<timestamp>.json` with AI-friendly JSON schema.
