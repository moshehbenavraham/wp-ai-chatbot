# Autoloading Design

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Design Decision

**Chosen approach**: Custom PSR-4-compatible autoloader without Composer autoload in production.

**Rationale**:
- WordPress plugins are distributed as self-contained ZIP files -- Composer autoload is not
  available in production unless `vendor/autoload.php` is shipped (adds ~500 files).
- PHP 7.4+ supports namespaces and `spl_autoload_register()` natively.
- AxiaChat uses manual `require_once` includes (38+ files in bootstrap) -- error-prone and verbose.
- MxChat uses `class-mxchat-*.php` naming without autoloading -- requires manual includes.
- A lightweight custom autoloader (~40 LOC) provides namespace-to-directory resolution without
  external dependencies.

**Alternatives considered**:
1. **Ship Composer autoload** -- rejected: adds vendor bloat, complicates ZIP build, conflicts with
   other plugins that may include different versions of the same library.
2. **Manual require_once** (AxiaChat pattern) -- rejected: does not scale to 37+ classes,
   error-prone when adding new files.
3. **WordPress classmap** -- rejected: requires regeneration on every new class, no namespace support.

---

## 2. Namespace-to-Directory Mapping

Root namespace: `WPAIC`
Root directory: `includes/`

| Namespace | Directory | Example Class | File Path |
|-----------|----------|--------------|-----------|
| `WPAIC` | `includes/` | `WPAIC\Plugin` | `includes/class-plugin.php` |
| `WPAIC\Provider` | `includes/provider/` | `WPAIC\Provider\Provider_Registry` | `includes/provider/class-provider-registry.php` |
| `WPAIC\Provider\Adapters` | `includes/provider/adapters/` | `WPAIC\Provider\Adapters\OpenAI_Adapter` | `includes/provider/adapters/class-openai-adapter.php` |
| `WPAIC\Runtime` | `includes/runtime/` | `WPAIC\Runtime\Message_Pipeline` | `includes/runtime/class-message-pipeline.php` |
| `WPAIC\Migration` | `includes/migration/` | `WPAIC\Migration\Lifecycle_Manager` | `includes/migration/class-lifecycle-manager.php` |
| `WPAIC\Rag` | `includes/rag/` | `WPAIC\Rag\Retriever` | `includes/rag/class-retriever.php` |
| `WPAIC\Admin` | `includes/admin/` | `WPAIC\Admin\Admin_Router` | `includes/admin/class-admin-router.php` |
| `WPAIC\Admin\Pages` | `includes/admin/pages/` | `WPAIC\Admin\Pages\General_Page` | `includes/admin/pages/class-general-page.php` |
| `WPAIC\Security` | `includes/security/` | `WPAIC\Security\Sanitizer` | `includes/security/class-sanitizer.php` |
| `WPAIC\Tools` | `includes/tools/` | `WPAIC\Tools\Tool_Registry` | `includes/tools/class-tool-registry.php` |
| `WPAIC\Integrations` | `includes/integrations/` | `WPAIC\Integrations\Mcp_Client` | `includes/integrations/class-mcp-client.php` |
| `WPAIC\WooCommerce` | `includes/woocommerce/` | `WPAIC\WooCommerce\Product_Context` | `includes/woocommerce/class-product-context.php` |

---

## 3. Class-to-File Resolution Rules

The autoloader converts a fully-qualified class name to a file path using these rules:

1. **Strip root namespace**: `WPAIC\Provider\Provider_Registry` -> `Provider\Provider_Registry`
2. **Convert namespace separators to directory separators**: `Provider\Provider_Registry` -> `Provider/Provider_Registry`
3. **Convert class name to file name**:
   - Replace `_` in class name with `-` (WordPress convention)
   - Convert to lowercase
   - Determine file type prefix from class name characteristics
4. **Apply type prefix**:
   - Classes: `class-{name}.php`
   - Interfaces (class name is a single noun or ends with capability descriptor): `interface-{name}.php`
   - Traits (class name starts with `Has_` or ends with `_Trait`): `trait-{name}.php`
5. **Convert namespace path to lowercase**: `Provider` -> `provider`
6. **Prepend base directory**: `includes/provider/class-provider-registry.php`

### Resolution Examples

| Fully Qualified Name | Resolution Steps | File Path |
|---------------------|-----------------|-----------|
| `WPAIC\Plugin` | Strip WPAIC -> `Plugin` -> `class-plugin.php` | `includes/class-plugin.php` |
| `WPAIC\Provider\Provider_Registry` | Strip -> `Provider\Provider_Registry` -> `provider/class-provider-registry.php` | `includes/provider/class-provider-registry.php` |
| `WPAIC\Provider\Adapters\OpenAI_Adapter` | Strip -> `Provider\Adapters\OpenAI_Adapter` -> `provider/adapters/class-openai-adapter.php` | `includes/provider/adapters/class-openai-adapter.php` |
| `WPAIC\Security\Encryption` | Strip -> `Security\Encryption` -> `security/class-encryption.php` | `includes/security/class-encryption.php` |
| `WPAIC\Admin\Pages\General_Page` | Strip -> `Admin\Pages\General_Page` -> `admin/pages/class-general-page.php` | `includes/admin/pages/class-general-page.php` |

---

## 4. Autoloader Implementation Specification

The autoloader is a single procedural function registered via `spl_autoload_register()` in
`includes/autoloader.php`. It is loaded by `wp-ai-chatbot.php` before any class is instantiated.

### 4.1 Pseudocode

```
function wpaic_autoloader(fully_qualified_class):
    // Only handle our namespace
    if not starts_with(fully_qualified_class, 'WPAIC\\'):
        return  // Let other autoloaders handle it

    // Strip root namespace
    relative_class = remove_prefix(fully_qualified_class, 'WPAIC\\')

    // Split into namespace parts and class name
    parts = split(relative_class, '\\')
    class_name = array_pop(parts)

    // Convert namespace parts to directory path (lowercase)
    directory = lowercase(join('/', parts))

    // Convert class name to file name
    file_name = determine_prefix(class_name) + lowercase(replace('_', '-', class_name)) + '.php'

    // Build full path
    file_path = WPAIC_INCLUDES_DIR + directory + '/' + file_name

    // Load if exists
    if file_exists(file_path):
        require_once file_path
```

### 4.2 Type Prefix Detection

The autoloader determines file type prefix by examining the class name or using an explicit map:

| Detection Rule | Prefix | Example |
|---------------|--------|---------|
| Class name matches interface list | `interface-` | `Provider`, `Embedding` |
| Class name starts with `Has_` or ends with `_Trait` | `trait-` | `Has_Hooks` |
| Default | `class-` | `Provider_Registry`, `Sanitizer` |

The interface list is a static array maintained in the autoloader:

```php
$interfaces = ['Provider', 'Embedding'];
```

This is a small, explicit list. As interfaces are added in future sessions, this list is updated.

### 4.3 Special Cases

| Case | Handling |
|------|---------|
| Root namespace class (e.g., `WPAIC\Plugin`) | `directory` is empty; file resolves to `includes/class-plugin.php` |
| Class not found | Autoloader returns silently; PHP throws standard "class not found" fatal |
| Non-WPAIC namespace | Autoloader returns immediately; does not interfere with other autoloaders |
| `lib/` files | NOT autoloaded; loaded explicitly in bootstrap (procedural, no namespace) |
| Deferred module classes | Autoloaded on-demand when hooks fire; directories are empty until Phase 03+ |

---

## 5. Loading Strategy for Non-Autoloaded Files

Some files are procedural and do not use namespaces. These are loaded explicitly in the bootstrap:

| File | Loaded By | When |
|------|----------|------|
| `includes/autoloader.php` | `wp-ai-chatbot.php` | Immediately (before any class use) |
| `lib/structured-logger.php` | `wp-ai-chatbot.php` | After constants defined |
| `lib/health-endpoint.php` | `wp-ai-chatbot.php` | Via `rest_api_init` hook |
| `lib/rate-limiter.php` | `wp-ai-chatbot.php` | Via `rest_api_init` hook |
| `lib/backup-manager.php` | `wp-ai-chatbot.php` | Via `rest_api_init` hook |
| `admin/views/*.php` | `Admin_Router` | On admin page render (conditional) |
| `templates/chatbot-widget.php` | Shortcode handler | On frontend shortcode render (conditional) |

---

## 6. Development vs Production

| Aspect | Development | Production |
|--------|-----------|------------|
| Autoloader | `includes/autoloader.php` (custom) | Same |
| Composer autoload | Available for dev tools (PHPStan, PHP-CS-Fixer, PHPUnit) | Not shipped |
| `vendor/` directory | Present locally for dev deps | Excluded via `.distignore` |
| Class loading | On-demand via autoloader | Same |
| Performance | Acceptable (file_exists per class) | Same (~37 classes, negligible overhead) |

### 6.1 Performance Considerations

- The autoloader performs one `file_exists()` check per unique class load. With ~37 MVP classes,
  this is negligible.
- WordPress already loads 1000+ files per request. Adding 10-15 autoloaded classes per request
  is inconsequential.
- If performance becomes a concern at scale, a generated classmap can replace the dynamic
  resolution without changing the namespace structure.

---

## 7. Compatibility Notes

### 7.1 PHP 7.4 Compatibility

- Namespaces: Supported since PHP 5.3.
- `spl_autoload_register()`: Supported since PHP 5.1.2.
- No typed properties required (PHP 7.4 supports them but we don't require them for autoloading).
- No union types or named arguments needed.

### 7.2 WordPress Compatibility

- The autoloader does not conflict with WordPress's own class loading.
- `spl_autoload_register()` appends to the autoloader stack; it does not replace existing loaders.
- Other plugins' autoloaders are unaffected (our autoloader only handles the `WPAIC\` prefix).
- Composer's autoloader (if present for dev) coexists without conflict.
