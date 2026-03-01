# Contributing

## Branch Conventions

- `main` - Production-ready code
- `feature/*` - New features
- `fix/*` - Bug fixes
- `docs/*` - Documentation changes

## Commit Style

Use conventional commits:

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `refactor:` Code refactoring
- `test:` Adding tests
- `chore:` Tooling and maintenance

## Pre-Commit Hooks

This project uses husky + lint-staged. On commit:

1. JS files are checked with Biome
2. PHP files are formatted with PHP-CS-Fixer (via Docker)

Do not bypass hooks with `--no-verify` unless explicitly approved.

## Pull Request Process

1. Create feature branch from `main`
2. Make changes with clear commits
3. Write/update tests
4. Update documentation
5. Open PR with description
6. Address review feedback
7. Squash and merge

## Code Style

### PHP

- PSR-12 via PHP-CS-Fixer
- WordPress coding practices for hooks, options, and capabilities
- `snake_case` for functions/hooks/options
- `PascalCase` for class names

### JavaScript

- Biome for formatting and linting
- `camelCase` for variables and functions
- No build tools -- vanilla JS only

## Code Review Norms

- Review within 24 hours
- Be constructive and specific
- Approve when ready, request changes when not

## Reference Plugins

The `axiachat-ai/`, `mxchat-basic/`, and `baachal/` directories are **read-only references**. Never modify files in these directories.
