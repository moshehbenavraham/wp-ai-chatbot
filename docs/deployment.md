# Deployment

## Local Dev

### Start Everything

```bash
npm install
docker run --rm -v "$(pwd):/app" -w /app composer:2 install
npm test
```

### Verify

```bash
# Run all checks
npm run check
npm run check:lint:php
npm test
```

## CI/CD Pipeline

```
Push/PR --> Quality (Biome, PHP-CS-Fixer, PHPStan) --> Test (PHPUnit + Vitest)
Tag v* --> Build (rsync) --> ZIP --> GitHub Release
```

### Quality Workflow

File: `.github/workflows/quality.yml`

3 parallel jobs:

1. **JS**: Biome format + lint check
2. **PHP Format**: PHP-CS-Fixer dry-run
3. **PHP Static Analysis**: PHPStan

### Test Workflow

File: `.github/workflows/test.yml`

- PHPUnit with coverage
- Vitest with coverage

### Release Workflow

File: `.github/workflows/release.yml`

Triggered by tags matching `v*`:

1. rsync build (excludes dev files per `.distignore`)
2. ZIP artifact created
3. Published as GitHub Release via `softprops/action-gh-release@v2`

## Release Process

1. Ensure all tests pass on `main`
2. Tag the release: `git tag v1.0.0`
3. Push the tag: `git push origin v1.0.0`
4. GitHub Actions builds and publishes the ZIP
5. Download from GitHub Releases and install on WordPress

## Build Exclusions

The `.distignore` file lists files/directories excluded from the release ZIP:

- Dev tools (node_modules, vendor dev deps, config files)
- Reference plugins (axiachat-ai, mxchat-basic, baachal)
- Tests, spec system, CI configs
- Logs and coverage output

## Rollback

To roll back a release:

1. Install the previous version ZIP from GitHub Releases
2. Or revert the tag and create a new release
