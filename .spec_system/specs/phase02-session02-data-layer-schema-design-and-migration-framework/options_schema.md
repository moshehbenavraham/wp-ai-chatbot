# Options Schema

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

All plugin configuration is stored as individual WordPress options using the `wpaic_{domain}_{setting}` naming convention. This follows the AxiaChat individual-option pattern rather than MxChat's monolithic `mxchat_options[]` blob, which is an anti-pattern for:
- Atomic updates (blob requires read-modify-write cycle)
- Autoloading control (individual options can be selectively autoloaded)
- Settings API integration (each option maps to one `register_setting()` call)

**Prefix**: `wpaic_` (defined as `WPAIC_PREFIX` constant)

**Domain identifiers**: `provider`, `runtime`, `rag`, `admin`, `security`, `migration`

**Sensitive fields**: Marked with `[E]` -- automatically encrypted via `encrypted_storage_spec.md`

---

## 2. Provider Domain Options (`wpaic_provider_*`)

Settings for AI provider configuration, API keys, and model selection.

| Option Key | Type | Default | Validation | Autoload | Description |
|------------|------|---------|-----------|----------|-------------|
| `wpaic_provider_default` | `string` | `'openai'` | One of: `openai`, `claude`, `gemini`, `deepseek`, `grok` | yes | Default AI provider |
| `wpaic_provider_openai_key` [E] | `string` | `''` | `sanitize_text_field` + encrypt | yes | OpenAI API key |
| `wpaic_provider_claude_key` [E] | `string` | `''` | `sanitize_text_field` + encrypt | yes | Anthropic Claude API key |
| `wpaic_provider_gemini_key` [E] | `string` | `''` | `sanitize_text_field` + encrypt | yes | Google Gemini API key |
| `wpaic_provider_deepseek_key` [E] | `string` | `''` | `sanitize_text_field` + encrypt | yes | DeepSeek API key |
| `wpaic_provider_grok_key` [E] | `string` | `''` | `sanitize_text_field` + encrypt | yes | xAI Grok API key |
| `wpaic_provider_voyage_key` [E] | `string` | `''` | `sanitize_text_field` + encrypt | yes | Voyage AI embedding API key |
| `wpaic_provider_default_model` | `string` | `'gpt-4o'` | `sanitize_text_field` | yes | Default model slug |
| `wpaic_provider_timeout` | `int` | `30` | `absint`, min 5, max 120 | yes | Provider HTTP request timeout (seconds) |
| `wpaic_provider_max_retries` | `int` | `3` | `absint`, min 0, max 5 | yes | Max retry attempts on provider error |

**Source anchors**:
- `aichat_openai_api_key`, `aichat_claude_api_key`, `aichat_gemini_api_key` (AxiaChat individual options)
- `mxchat_options['api_key']`, `mxchat_options['xai_api_key']` (MxChat blob -- decomposed)
- `baachal_ai_provider`, `baachal_ai_model` (Baachal individual options)

---

## 3. Runtime Domain Options (`wpaic_runtime_*`)

Settings for chat behavior, streaming, and session management.

| Option Key | Type | Default | Validation | Autoload | Description |
|------------|------|---------|-----------|----------|-------------|
| `wpaic_runtime_stream_enabled` | `bool` | `true` | Boolean cast | yes | Enable SSE streaming responses |
| `wpaic_runtime_max_tokens` | `int` | `2048` | `absint`, min 1, max 128000 | yes | Default max response tokens |
| `wpaic_runtime_temperature` | `float` | `0.7` | Float, min 0.0, max 2.0 | yes | Default sampling temperature |
| `wpaic_runtime_input_max_length` | `int` | `512` | `absint`, min 1, max 10000 | yes | Max user input characters |
| `wpaic_runtime_max_messages` | `int` | `20` | `absint`, min 1, max 100 | yes | Max conversation history messages |
| `wpaic_runtime_history_persistence` | `bool` | `true` | Boolean cast | yes | Store conversation history in DB |
| `wpaic_runtime_context_max_length` | `int` | `6000` | `absint`, min 100, max 50000 | yes | Max context injection characters |
| `wpaic_runtime_context_limit` | `int` | `5` | `absint`, min 1, max 20 | yes | Max RAG context chunks |
| `wpaic_runtime_datetime_injection` | `bool` | `true` | Boolean cast | yes | Inject current datetime into context |
| `wpaic_runtime_user_context_injection` | `bool` | `false` | Boolean cast | yes | Inject user context (role, name) |

**Source anchors**:
- `aichat_bots` table columns: `temperature`, `max_tokens`, `input_max_length`, `max_messages`, `context_max_length`, `context_limit`, `history_persistence` (AxiaChat per-bot)
- `mxchat_options['enable_streaming_toggle']` (MxChat global toggle)
- `aichat_datetime_injection_enabled`, `aichat_inject_user_context_enabled` (AxiaChat)

**Note**: Per-bot overrides for these settings are stored in the `wp_wpaic_bots` table columns. The options here serve as global defaults that new bots inherit.

---

## 4. RAG Domain Options (`wpaic_rag_*`)

Settings for knowledge base, ingestion, and retrieval configuration.

| Option Key | Type | Default | Validation | Autoload | Description |
|------------|------|---------|-----------|----------|-------------|
| `wpaic_rag_chunk_size` | `int` | `1000` | `absint`, min 100, max 10000 | yes | Chunk size in characters |
| `wpaic_rag_chunk_overlap` | `int` | `200` | `absint`, min 0, max chunk_size/2 | yes | Overlap between chunks |
| `wpaic_rag_retrieval_mode` | `string` | `'local'` | One of: `local`, `pinecone`, `page`, `auto` | yes | Default retrieval mode |
| `wpaic_rag_embedding_model` | `string` | `'text-embedding-ada-002'` | `sanitize_text_field` | yes | Default embedding model |
| `wpaic_rag_max_results` | `int` | `5` | `absint`, min 1, max 20 | yes | Max retrieval results |
| `wpaic_rag_similarity_threshold` | `int` | `80` | `absint`, min 20, max 95 | yes | Similarity threshold (percentage) |
| `wpaic_rag_auto_index` | `bool` | `true` | Boolean cast | yes | Auto-index on post save |
| `wpaic_rag_indexable_post_types` | `array` | `['post', 'page']` | Array of `sanitize_key` values | yes | Post types eligible for indexing |
| `wpaic_rag_pinecone_key` [E] | `string` | `''` | `sanitize_text_field` + encrypt | no | Pinecone API key |
| `wpaic_rag_pinecone_endpoint` [E] | `string` | `''` | `esc_url_raw` + encrypt | no | Pinecone endpoint URL |
| `wpaic_rag_content_indexing_enabled` | `bool` | `true` | Boolean cast | yes | Enable content indexing subsystem |

**Source anchors**:
- `mxchat_options['embedding_model']`, `mxchat_similarity_threshold` (MxChat)
- `baachal_content_max_results`, `baachal_indexable_post_types`, `baachal_auto_index`, `baachal_content_indexing_enabled` (Baachal)
- AxiaChat context settings via `aichat_bots` table columns

---

## 5. Admin Domain Options (`wpaic_admin_*`)

Settings for admin UI behavior, diagnostics, and operational controls.

| Option Key | Type | Default | Validation | Autoload | Description |
|------------|------|---------|-----------|----------|-------------|
| `wpaic_admin_global_bot_enabled` | `bool` | `true` | Boolean cast | yes | Show chatbot globally (vs shortcode-only) |
| `wpaic_admin_global_bot_slug` | `string` | `''` | `sanitize_title` | yes | Default bot slug for global display |
| `wpaic_admin_visibility_mode` | `string` | `'all'` | One of: `all`, `include`, `exclude` | yes | Page visibility mode |
| `wpaic_admin_visibility_pages` | `array` | `[]` | Array of `absint` page IDs | no | Pages to include/exclude |
| `wpaic_admin_branding_enabled` | `bool` | `true` | Boolean cast | yes | Show branding in widget |
| `wpaic_admin_branding_text` | `string` | `'WP AI Chatbot'` | `sanitize_text_field` | yes | Branding display text |
| `wpaic_admin_branding_url` | `string` | `''` | `esc_url_raw` | yes | Branding link URL |
| `wpaic_admin_logging_enabled` | `bool` | `true` | Boolean cast | yes | Enable conversation logging |
| `wpaic_admin_debug_enabled` | `bool` | `false` | Boolean cast | yes | Enable debug mode |
| `wpaic_admin_preserve_data` | `bool` | `true` | Boolean cast | yes | Preserve data on uninstall |

**Source anchors**:
- `aichat_global_bot_enabled`, `aichat_global_bot_slug` (AxiaChat)
- `mxchat_options['post_type_visibility_mode']`, `mxchat_options['append_to_body']` (MxChat)
- `aichat_branding_enabled`, `aichat_branding_text`, `aichat_branding_url` (AxiaChat)
- `aichat_logging_enabled`, `aichat_debug_enabled` (AxiaChat)
- `baachal_preserve_data_on_uninstall` (Baachal)

---

## 6. Security Domain Options (`wpaic_security_*`)

Settings for rate limiting, moderation, privacy, and GDPR compliance.

| Option Key | Type | Default | Validation | Autoload | Description |
|------------|------|---------|-----------|----------|-------------|
| `wpaic_security_rate_limit` | `int` | `60` | `absint`, min 1, max 10000 | yes | Requests per window |
| `wpaic_security_rate_window` | `int` | `60` | `absint`, min 10, max 3600 | yes | Rate limit window (seconds) |
| `wpaic_security_moderation_enabled` | `bool` | `false` | Boolean cast | yes | Enable content moderation |
| `wpaic_security_moderation_external` | `bool` | `false` | Boolean cast | yes | Use external moderation API |
| `wpaic_security_moderation_default_words` | `bool` | `true` | Boolean cast | yes | Use default banned word list |
| `wpaic_security_banned_ips` | `string` | `''` | `wp_kses_post` | no | Newline-separated banned IPs |
| `wpaic_security_banned_words` | `string` | `''` | `wp_kses_post` | no | Newline-separated banned words |
| `wpaic_security_rejection_message` | `string` | `'Your message could not be processed.'` | `sanitize_text_field` | yes | Moderation rejection message |
| `wpaic_security_policy` | `string` | (see note) | `wp_kses_post` | no | System security policy prompt |
| `wpaic_security_embed_origins` | `string` | `''` | URL validation per line | no | Allowed embed origin URLs |
| `wpaic_security_gdpr_enabled` | `bool` | `false` | Boolean cast | yes | Show GDPR consent overlay |
| `wpaic_security_gdpr_text` | `string` | (default consent text) | `wp_kses_post` | no | GDPR consent message |
| `wpaic_security_gdpr_button` | `string` | `'I understand'` | `sanitize_text_field` | yes | GDPR consent button text |
| `wpaic_security_usage_limits_enabled` | `bool` | `true` | Boolean cast | yes | Enable daily usage limits |
| `wpaic_security_max_daily_total` | `int` | `1000` | `absint` | yes | Max total daily requests |
| `wpaic_security_max_daily_per_user` | `int` | `30` | `absint` | yes | Max daily requests per user |
| `wpaic_security_per_user_message` | `string` | (default limit text) | `sanitize_text_field` | yes | Message when user limit reached |
| `wpaic_security_daily_total_behavior` | `string` | `'disabled'` | One of: `hide`, `disabled` | yes | Behavior when daily total reached |
| `wpaic_security_daily_total_message` | `string` | (default limit text) | `sanitize_text_field` | yes | Message when total limit reached |
| `wpaic_security_ip_privacy` | `bool` | `false` | Boolean cast | yes | Anonymize stored IP addresses |

**Source anchors**:
- `aichat_moderation_enabled`, `aichat_moderation_banned_ips`, `aichat_moderation_banned_words` (AxiaChat)
- `aichat_gdpr_consent_enabled`, `aichat_gdpr_text`, `aichat_gdpr_button` (AxiaChat)
- `aichat_usage_limits_enabled`, `aichat_usage_max_daily_total`, `aichat_usage_max_daily_per_user` (AxiaChat)
- `aichat_security_policy`, `aichat_embed_allowed_origins` (AxiaChat)
- `mxchat_options['rate_limit_logged_out']`, `mxchat_options['role_rate_limits']` (MxChat)

---

## 7. Migration Domain Options (`wpaic_migration_*`)

Internal options for migration state tracking. Not exposed in admin UI.

| Option Key | Type | Default | Validation | Autoload | Description |
|------------|------|---------|-----------|----------|-------------|
| `wpaic_migration_version` | `string` | `'0.0.0'` | Semver string | yes | Last completed migration version |
| `wpaic_migration_source_plugin` | `string` | `''` | One of: `''`, `axiachat`, `mxchat`, `baachal` | yes | Source plugin detected for settings migration |
| `wpaic_migration_source_version` | `string` | `''` | `sanitize_text_field` | yes | Source plugin version at migration time |

**Source anchors**:
- `mxchat_plugin_version` (MxChat version tracking)
- `mxchat_pinecone_roles_migration_version` (MxChat per-table migration version)

---

## 8. Sensitive Field Registry

Fields marked with `[E]` in sections above are automatically encrypted. Complete list:

| # | Option Key | Domain | Description |
|---|------------|--------|-------------|
| 1 | `wpaic_provider_openai_key` | provider | OpenAI API key |
| 2 | `wpaic_provider_claude_key` | provider | Claude API key |
| 3 | `wpaic_provider_gemini_key` | provider | Gemini API key |
| 4 | `wpaic_provider_deepseek_key` | provider | DeepSeek API key |
| 5 | `wpaic_provider_grok_key` | provider | Grok API key |
| 6 | `wpaic_provider_voyage_key` | provider | Voyage AI key |
| 7 | `wpaic_rag_pinecone_key` | rag | Pinecone API key |
| 8 | `wpaic_rag_pinecone_endpoint` | rag | Pinecone endpoint URL |

Cross-reference: `encrypted_storage_spec.md` Section 4 for encryption details.

---

## 9. Option Summary

### 9.1 Count by Domain

| Domain | Options | Sensitive | Autoloaded |
|--------|---------|-----------|------------|
| provider | 10 | 7 | 10 |
| runtime | 10 | 0 | 10 |
| rag | 11 | 2 | 9 |
| admin | 10 | 0 | 8 |
| security | 20 | 0 | 14 |
| migration | 3 | 0 | 3 |
| **Total** | **64** | **9** | **54** |

### 9.2 Collision Safety

All option keys are prefixed with `wpaic_` which does not collide with:
- WordPress core options (`siteurl`, `blogname`, `active_plugins`, etc.)
- Source plugin options (`aichat_*`, `mxchat_*`, `baachal_*`)
- Common third-party plugin prefixes

### 9.3 Autoload Strategy

Options accessed on every page load are autoloaded (`autoload=yes`). Options only needed in admin or during specific operations are not autoloaded to reduce memory usage:
- **Not autoloaded**: Banned IP/word lists, security policy text, GDPR text, page visibility lists, Pinecone credentials
- **Autoloaded**: All frequently-read settings (provider defaults, runtime behavior, feature toggles)

### 9.4 Default Value Seeding

On activation, `Lifecycle_Manager::activate()` calls `add_option()` for each option with its default value. `add_option()` is a no-op if the option already exists, making this idempotent across re-activations.

Options are NOT set via `update_option()` on activation to avoid overwriting user-customized values during plugin re-activation.
