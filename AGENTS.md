# AGENTS.md

## Purpose

This is a provider-agnostic playbook for any AI coding agent working in this repository (Anthropic, Google, OpenAI, or others).

## Repository Reality

- This repo currently contains three WordPress plugin codebases used as references:
  - `axiachat-ai/`
  - `baachal/`
  - `mxchat-basic/`
- Planning and delivery artifacts live in:
  - `.spec_system/`
  - feature maps: `docs/axiachat-ai-feature-map.md`, `docs/baachal-feature-map.md`, `docs/mxchat-basic-feature-map.md`
  - master PRD: `.spec_system/PRD/PRD.md`

## Non-Negotiable Boundaries

1. Treat `axiachat-ai/`, `baachal/`, and `mxchat-basic/` as read-only reference sources.
2. Do not modify, rename, delete, or reformat files inside those three directories unless the user explicitly requests it.
3. Do not edit `vendor/` code directly.
4. Never commit secrets, API keys, or credentials.
5. Avoid destructive operations (for example hard resets, force deletes) unless explicitly approved.

## Standard Agent Workflow

1. Confirm the user goal and expected deliverable.
2. Read relevant specs first (`.spec_system/PRD/PRD.md` and related phase/session docs).
3. Build a concise reference-anchor list from the three reference projects before implementing.
4. Implement only in approved target files outside read-only reference folders.
5. Keep changes minimal and scoped to the request.
6. Validate changed code and report what was verified.

## Implementation Rules

- Follow WordPress coding practices and match local style where you edit.
- Keep admin and frontend concerns separated.
- Use clear naming:
  - `snake_case` for procedural functions/hooks/options
  - `PascalCase` for PHP classes
  - `camelCase` for JavaScript variables/functions
- Preserve backward compatibility unless the task explicitly allows breaking changes.
- Prefer small, reviewable edits over large rewrites.

## Security Baseline

- Enforce capability checks and nonces on state-changing actions.
- Sanitize input, validate types, and escape output.
- Use `$wpdb->prepare()` for dynamic SQL.
- Keep logs and debug output free of secrets and sensitive personal data.

## Validation Baseline

No root CI pipeline is guaranteed in this snapshot. Minimum validation for code changes:

1. Syntax-check changed PHP files.
2. Manually test activation/deactivation paths if lifecycle code changes.
3. Manually test settings save and chatbot request/response flows when runtime or admin code changes.
4. If WooCommerce behavior changes, test on a WooCommerce-enabled site.

Example PHP lint command:

`find <changed-paths> -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l`

## Output Expectations for Agents

For each completed task, provide:

- What changed
- Why it changed
- Files touched
- Validation steps run and results
- Any remaining risks, assumptions, or follow-up items
