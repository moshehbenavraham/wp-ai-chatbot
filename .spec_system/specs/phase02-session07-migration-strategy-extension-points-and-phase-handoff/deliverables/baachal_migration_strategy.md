# Baachal Migration Strategy

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

Baachal is the simplest migration scenario:
- Individual option keys (no blob decomposition needed)
- All API keys are plaintext (no decryption step, only re-encryption to WPAIC format)
- No custom database tables (no data migration)
- Limited feature set: provider selection, model, basic runtime settings, WooCommerce toggles
- Clean uninstall pattern (`baachal/uninstall.php`) provides the reference for rollback

**Migration ID**: `baachal_v1`
**Complexity**: Low -- key renames + encryption pass

---

## 2. Option Key Mapping

### 2.1 Provider Settings

| Source Option | Target Option | Transform | Encrypt |
|--------------|--------------|-----------|---------|
| `baachal_ai_provider` | `wpaic_provider_default` | `map_value(['openai'=>'openai','gemini'=>'gemini','anthropic'=>'claude'])` | no |
| `baachal_ai_model` | `wpaic_provider_default_model` | `to_string()` | no |
| `baachal_gemini_api_key` | `wpaic_provider_gemini_key` | `to_string()` | YES |
| `baachal_openai_api_key` | `wpaic_provider_openai_key` | `to_string()` | YES |
| `baachal_anthropic_api_key` | `wpaic_provider_claude_key` | `to_string()` | YES |

### 2.2 Runtime Settings

| Source Option | Target Option | Transform |
|--------------|--------------|-----------|
| `baachal_chatbot_name` | `wpaic_widget_title` | `to_string()` |
| `baachal_welcome_message` | `wpaic_widget_welcome_message` | `to_string()` |
| `baachal_system_prompt` | `wpaic_runtime_default_system_prompt` | `to_string()` |
| `baachal_max_tokens` | `wpaic_runtime_max_tokens` | `to_int(1, 128000)` |
| `baachal_temperature` | `wpaic_runtime_temperature` | `to_float(0.0, 2.0)` |

### 2.3 Content / RAG Settings

| Source Option | Target Option | Transform | Notes |
|--------------|--------------|-----------|-------|
| `baachal_context_mode` | `wpaic_rag_context_mode` | `map_value(['site_content'=>'page','disabled'=>'none'])` | BAA has no vector RAG |
| `baachal_exclude_pages` | `wpaic_rag_excluded_post_ids` | custom: parse newline-sep list to array | |
| `baachal_cache_enabled` | `wpaic_rag_cache_enabled` | `to_bool()` | |

### 2.4 Security Settings

| Source Option | Target Option | Transform |
|--------------|--------------|-----------|
| `baachal_rate_limit_enabled` | `wpaic_moderation_rate_limit_enabled` | `to_bool()` |
| `baachal_rate_limit_requests` | `wpaic_security_rate_limit_requests` | `to_int(1, 200)` |

### 2.5 WooCommerce Settings

| Source Option | Target Option | Transform |
|--------------|--------------|-----------|
| `baachal_woocommerce_enabled` | `wpaic_woocommerce_enabled` | `to_bool()` |
| `baachal_product_context_limit` | `wpaic_woocommerce_product_limit` | `to_int(1, 20)` |

---

## 3. Data Migration

Baachal has no custom database tables. No data migration needed.

Baachal's keyword search index is stored in-memory (transients/cache). No persistent index
data to migrate. WPAIC's RAG pipeline starts fresh for Baachal migrations.

---

## 4. Uninstall Reference

Baachal's `uninstall.php` provides the canonical reference for what to clean up when rolling
back a WPAIC migration from Baachal:

```php
// From baachal/uninstall.php (reference for rollback inverse):
delete_option('baachal_ai_provider');
delete_option('baachal_gemini_api_key');
// ... (all option keys listed above)
```

The WPAIC migration stores a backup at `wpaic_migration_backup_baachal_v1` before any
source option deletion, enabling rollback restoration.

---

## 5. Source Anchors

- `baachal/baachal.php` -- full option key inventory, activation hooks
- `baachal/admin/tabs/general.php` -- provider + model selection option keys
- `baachal/admin/tabs/woocommerce.php` -- WooCommerce option keys
- `baachal/uninstall.php` -- complete list of options and tables to clean up (rollback reference)
