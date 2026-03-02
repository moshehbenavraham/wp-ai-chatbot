# Onboarding

Zero-to-hero checklist for new developers.

## Prerequisites

- [ ] Node.js v22+ installed (via nvm recommended)
- [ ] Docker installed and running (no sudo required)
- [ ] Git configured
- [ ] Access to GitHub repository

Note: PHP is **not** required locally. All PHP tooling runs via Docker.

## Setup Steps

### 1. Clone Repository

```bash
git clone https://github.com/moshehbenavraham/wp-ai-chatbot.git
cd wp-ai-chatbot
```

### 2. Install Node Dependencies

```bash
# Source nvm if needed
export NVM_DIR="$HOME/.nvm" && [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

npm install
```

### 3. Install PHP Dependencies

```bash
docker run --rm -v "$(pwd):/app" -w /app composer:2 install
```

### 4. Verify Setup

- [ ] JS tests pass: `npm run test:js`
- [ ] PHP tests pass: `npm run test:php`
- [ ] Biome checks pass: `npm run check`
- [ ] PHPStan passes: `npm run check:lint:php`

## Key Directories

| Directory | Purpose |
|-----------|---------|
| `includes/` | Plugin PHP classes (autoloaded via `WPAIC\` namespace) |
| `admin/` | Admin CSS/JS assets |
| `assets/` | Frontend CSS/JS/images |
| `templates/` | PHP templates (chatbot widget) |
| `lib/` | Shared infrastructure (health, rate limiter, backup, logger) |
| `tests/php/`, `tests/js/` | Test suites (215 PHP + 26 JS) |
| `docs/` | Project documentation |
| `languages/` | i18n POT file |
| `axiachat-ai/`, `mxchat-basic/`, `baachal/` | Read-only reference plugins |
| `.spec_system/` | Specification-driven delivery artifacts |

## Common Issues

### Docker not available

Ensure Docker daemon is running. All PHP commands (PHPUnit, PHP-CS-Fixer, PHPStan) require Docker.

### nvm not found

Source nvm before running npm commands:

```bash
export NVM_DIR="$HOME/.nvm" && [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
```

### PHPStan out of memory

PHPStan needs 4G+ RAM. The config uses `parallel.maximumNumberOfProcesses: 1` to reduce memory pressure.

### Composer dependency conflicts

Use the `-W` flag when adding dev dependencies:

```bash
docker run --rm -v "$(pwd):/app" -w /app composer:2 require --dev <package> -W
```
