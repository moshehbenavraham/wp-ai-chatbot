# WP AI Chatbot — Migration Guide

> Version 1.0.0 · Last updated 2026-03-01

Migrate from axiachat-ai, mxchat-basic, or baachal to WP AI Chatbot using the built-in Migration tool.

---

## Overview

The migration tool:
1. **Detects** any installed source plugin and inspects its stored options.
2. **Maps** compatible settings to their WPAIC equivalents.
3. **Runs a Dry Run** — shows what would be imported, flags conflicts, no data is changed.
4. **Runs Live** — imports settings; API keys are re-encrypted with AES-256-GCM.
5. **Creates a backup** of all affected WPAIC options before overwriting, enabling **rollback**.

Navigate to **WP AI Chatbot > Migration** to use the tool.

---

## Before You Begin

- Keep the source plugin **activated** during migration (options must exist in the DB).
- Run a **Dry Run** first and review the conflict report.
- Backup your database before running a Live migration.
- After migration, **deactivate** (do not uninstall) the source plugin until you have verified all features work correctly.

---

## Migrating from axiachat-ai

### Detected Options

| axiachat-ai Option | WPAIC Option | Notes |
|--------------------|--------------|-------|
| `aichat_openai_api_key` | `wpaic_provider_openai_key` | Re-encrypted |
| `aichat_widget_position` | `wpaic_widget_position` | |
| `aichat_bot_name` | `wpaic_widget_title` | |
| `aichat_show_powered_by` | `wpaic_powered_by_enabled` | |
| `aichat_global_widget` | `wpaic_widget_global` | |

### Migration Steps

1. Ensure axiachat-ai is installed and activated.
2. Go to **Migration** and look for the axiachat detection card — it should show **Detected**.
3. Click **Dry Run** to preview the mapping.
4. Review the **Conflict** badges — conflicts occur when a WPAIC option already has a non-default value.
5. Click **Run Live** (with or without "Overwrite conflicts" checked).
6. Verify settings in **General Settings** and the widget on the frontend.

### Known Differences

- axiachat-ai uses a single OpenAI API key; WPAIC supports 5 providers. The key is migrated to the OpenAI slot.
- axiachat-ai's per-post widget settings are not migrated (shortcode attributes are the WPAIC equivalent).

---

## Migrating from mxchat-basic

### Detected Options

mxchat-basic stores settings as a serialized array under `mxchat_options`.

| mxchat Key | WPAIC Option | Notes |
|------------|--------------|-------|
| `openai_api_key` | `wpaic_provider_openai_key` | Re-encrypted |
| `anthropic_api_key` | `wpaic_provider_claude_key` | Re-encrypted |
| `google_api_key` | `wpaic_provider_gemini_key` | Re-encrypted |
| `deepseek_api_key` | `wpaic_provider_deepseek_key` | Re-encrypted |
| `grok_api_key` | `wpaic_provider_grok_key` | Re-encrypted |
| `model` | `wpaic_provider_default_model` | |
| `system_prompt` | `wpaic_system_prompt_default` | |
| `widget_title` | `wpaic_widget_title` | |
| `welcome_message` | `wpaic_widget_welcome_message` | |
| `rate_limit` | `wpaic_security_rate_limit_max` | |

The active provider is inferred from which API key is populated and set as `wpaic_provider_default`.

### Migration Steps

1. Ensure mxchat-basic is active.
2. Go to **Migration** — you should see the mxchat detection card.
3. Run **Dry Run**, review the mapping table.
4. Note that if multiple API keys are present, the inferred provider may not match your intended default — adjust after migration.
5. Run **Live** when ready.

### Known Differences

- mxchat's transcript viewer is not migrated; use the **Diagnostics** page and future Transcripts screen (Phase 16).
- mxchat rate limits are per-session; WPAIC applies IP-based rate limiting.

---

## Migrating from baachal

### Detected Options

| baachal Option | WPAIC Option | Notes |
|----------------|--------------|-------|
| `baachal_openai_key` | `wpaic_provider_openai_key` | Re-encrypted |
| `baachal_anthropic_key` | `wpaic_provider_claude_key` | Re-encrypted |
| `baachal_google_key` | `wpaic_provider_gemini_key` | Re-encrypted |
| `baachal_grok_key` | `wpaic_provider_grok_key` | Re-encrypted |
| `baachal_provider` | `wpaic_provider_default` | Normalized: `anthropic`→`claude`, `google`→`gemini`, `grok`→`xai` |
| `baachal_model` | `wpaic_provider_default_model` | |
| `baachal_system_prompt` | `wpaic_system_prompt_default` | |
| `baachal_widget_title` | `wpaic_widget_title` | |
| `baachal_welcome_message` | `wpaic_widget_welcome_message` | |
| `baachal_enable_woocommerce` | `wpaic_woocommerce_enabled` | |
| `baachal_enable_rag` | `wpaic_rag_enabled` | |
| `baachal_rate_limit_enabled` | `wpaic_security_rate_limit_enabled` | |
| `baachal_rate_limit_max` | `wpaic_security_rate_limit_max` | |

### Migration Steps

1. Ensure baachal is active.
2. Go to **Migration** — look for the baachal detection card.
3. Run **Dry Run**, check provider normalization (baachal uses provider name aliases that WPAIC normalizes).
4. Run **Live**.
5. Verify WooCommerce integration in **General Settings** if applicable.

### Known Differences

- baachal's preset color palettes are not yet migrated; Phase 12 will add color palette support to WPAIC.
- baachal's language/translation module is not migrated (Phase 17 feature).

---

## Rollback

If migration causes issues, use the **Rollback** button on the Migration page. This restores all WPAIC options that were overwritten during the live run to their pre-migration values.

**Rollback limitations:**
- Only the most recent 5 backups per source are retained.
- Rollback does not restore the source plugin's own data.
- Rollback is not available after a Dry Run (no data was changed).

---

## Post-Migration Validation Checklist

After completing a live migration, verify:

- [ ] Correct AI provider is selected in **General Settings**
- [ ] API key is validated (use the **Test** button on General Settings)
- [ ] Widget appears on the frontend with the correct title
- [ ] Chat messages generate responses
- [ ] Rate limiting is active (confirm in Security > Security tab)
- [ ] All migrated API keys are masked (stored encrypted — only last 4 chars visible)
- [ ] Source plugin can be safely deactivated
- [ ] Run **Diagnostics > Health Check** — all checks should pass
