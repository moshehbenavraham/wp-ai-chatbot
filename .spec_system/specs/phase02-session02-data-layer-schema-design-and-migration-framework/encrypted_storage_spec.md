# Encrypted Storage Specification

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Status**: Complete
**Created**: 2026-03-01
**Resolves**: CFI-SEC-002

---

## 1. Overview

All API keys and sensitive configuration values are encrypted at rest using AES-256-GCM. The encryption module lives in `includes/security/class-encryption.php` (DOM-SECURITY, leaf module). The settings manager in `includes/admin/class-settings-manager.php` wraps `get_option()`/`update_option()` with transparent encrypt/decrypt for designated sensitive fields.

This specification adopts the AxiaChat encryption pattern (`axiachat-ai/includes/settings/encrypt-decrypt.php`) as the winner, with improvements:
- Explicit envelope versioning for forward compatibility
- Documented key rotation procedure
- Plaintext-to-encrypted migration path
- Fallback behavior when OpenSSL is unavailable

**Owner module**: `includes/security/class-encryption.php`

**Cross-domain pattern**: Policy-vs-Execution (RES-XD-001) -- Security owns the cryptographic implementation; Admin designates which fields are sensitive and calls encrypt/decrypt through the settings manager interface.

---

## 2. Cryptographic Parameters

| Parameter | Value | Rationale |
|-----------|-------|-----------|
| Cipher | `aes-256-gcm` | Authenticated encryption; prevents tampering and padding oracle attacks |
| Key size | 256 bits (32 bytes) | Standard for AES-256 |
| IV size | 12 bytes (96 bits) | GCM recommended IV length |
| Tag size | 16 bytes (128 bits) | Full GCM authentication tag |
| IV generation | `random_bytes(12)` | Unique random IV per encryption operation |
| Key derivation | SHA-256 hash of master key material | One-way derivation from site-specific salt |

### 2.1 Key Derivation

The encryption key is derived from site-specific key material using SHA-256:

```
Priority 1: WPAIC_MASTER_KEY constant (defined in wp-config.php by site admin)
  -> key = hash('sha256', WPAIC_MASTER_KEY, binary: true)

Priority 2: WordPress AUTH_KEY salt (fallback)
  -> key = hash('sha256', wp_salt('auth'), binary: true)
```

**Design choice**: Using `wp_salt('auth')` as fallback ensures encryption works out-of-the-box without manual configuration. The `WPAIC_MASTER_KEY` constant allows site administrators to use a dedicated encryption key that survives `wp-config.php` salt rotation.

### 2.2 OpenSSL Requirement

The encryption module requires PHP's OpenSSL extension. If OpenSSL is unavailable:
- `Encryption::encrypt()` returns the plaintext value unchanged
- `Encryption::decrypt()` returns the stored value unchanged
- A persistent admin notice warns that API keys are stored in plaintext
- The health endpoint reports `encryption: degraded`

---

## 3. Wire Format

### 3.1 Envelope Structure

Encrypted values are stored as a base64-encoded JSON envelope:

```
base64_encode(json_encode({
    "v":   1,                        // Envelope version (integer)
    "m":   "aes-256-gcm",           // Cipher method (string)
    "iv":  base64_encode(random_iv), // 12-byte IV (base64 string)
    "ct":  base64_encode(ciphertext),// Encrypted data (base64 string)
    "tag": base64_encode(auth_tag)   // 16-byte GCM tag (base64 string)
}))
```

### 3.2 Envelope Detection

A stored value is recognized as encrypted if:
1. It is a non-empty string
2. It base64-decodes to valid JSON
3. The JSON contains a `"v"` field with integer value >= 1
4. The JSON contains `"m"`, `"iv"`, `"ct"`, and `"tag"` fields

This detection is used by `Encryption::is_encrypted()` to avoid double-encryption and to enable transparent decryption of already-encrypted values while passing through plaintext values unchanged.

### 3.3 Version Field

The `"v"` field enables future envelope format changes without breaking existing encrypted values:

| Version | Format | Status |
|---------|--------|--------|
| 1 | AES-256-GCM with base64 JSON envelope | Current |

When decrypting, the code checks `"v"` and dispatches to the appropriate decryption path. This allows migrating to a different cipher or envelope format in the future while still reading old values.

---

## 4. Sensitive Field Inventory

All fields that store secrets or credentials. These fields are automatically encrypted on write and decrypted on read by the settings manager.

| Option Key | Domain | Description | Source Anchor |
|------------|--------|-------------|--------------|
| `wpaic_provider_openai_key` | provider | OpenAI API key | `aichat_openai_api_key` |
| `wpaic_provider_claude_key` | provider | Anthropic Claude API key | `aichat_claude_api_key` |
| `wpaic_provider_gemini_key` | provider | Google Gemini API key | `aichat_gemini_api_key` |
| `wpaic_provider_deepseek_key` | provider | DeepSeek API key | (new, no source) |
| `wpaic_provider_grok_key` | provider | xAI Grok API key | `mxchat_options['xai_api_key']` |
| `wpaic_provider_voyage_key` | provider | Voyage AI embedding key | `mxchat_options['voyage_api_key']` |
| `wpaic_rag_pinecone_key` | rag | Pinecone vector DB API key | `mxchat_pinecone_addon_options` |
| `wpaic_rag_pinecone_endpoint` | rag | Pinecone endpoint URL (contains project ID) | `mxchat_pinecone_addon_options` |

### 4.1 Field Registry Constant

The list of sensitive fields is maintained as a constant in the security module:

```php
const SENSITIVE_FIELDS = [
    'wpaic_provider_openai_key',
    'wpaic_provider_claude_key',
    'wpaic_provider_gemini_key',
    'wpaic_provider_deepseek_key',
    'wpaic_provider_grok_key',
    'wpaic_provider_voyage_key',
    'wpaic_rag_pinecone_key',
    'wpaic_rag_pinecone_endpoint',
];
```

Extension domains can register additional sensitive fields via the `wpaic_sensitive_fields` filter:

```php
add_filter('wpaic_sensitive_fields', function (array $fields): array {
    $fields[] = 'wpaic_integrations_webhook_secret';
    return $fields;
});
```

---

## 5. Transparent Interface

### 5.1 Settings Manager Integration

The settings manager (`includes/admin/class-settings-manager.php`) provides the transparent encrypt/decrypt interface:

```php
// Writing a setting (auto-encrypts if field is sensitive)
Settings_Manager::set($key, $value);
  -> if in_array($key, Encryption::get_sensitive_fields()):
       $value = Encryption::encrypt($value)
  -> update_option($key, $value)

// Reading a setting (auto-decrypts if value is encrypted)
Settings_Manager::get($key, $default);
  -> $value = get_option($key, $default)
  -> if Encryption::is_encrypted($value):
       $value = Encryption::decrypt($value)
  -> return $value
```

### 5.2 Sanitization Callback

Sensitive fields use a dedicated sanitization callback during `register_setting()`:

```php
register_setting('wpaic_settings', 'wpaic_provider_openai_key', [
    'sanitize_callback' => function ($value) {
        $value = sanitize_text_field($value);
        if (empty($value)) {
            return '';
        }
        if (Encryption::is_encrypted($value)) {
            return $value; // Already encrypted (e.g., re-save without change)
        }
        return Encryption::encrypt($value);
    },
]);
```

### 5.3 Display Masking

When displaying sensitive fields in admin UI, the settings manager returns a masked version:

```php
Settings_Manager::get_masked($key);
  -> $value = Settings_Manager::get($key)
  -> if empty($value): return ''
  -> return str_repeat('*', 8) . substr($value, -4) // Show last 4 chars
```

---

## 6. Key Rotation Strategy

### 6.1 When Key Rotation is Needed

- Site administrator changes `WPAIC_MASTER_KEY` in `wp-config.php`
- WordPress `AUTH_KEY` salt changes (e.g., security incident response, site migration)
- Periodic rotation per security policy

### 6.2 Re-Encryption Procedure

Key rotation requires re-encrypting all sensitive fields with the new key:

```
1. Store old key material (before changing wp-config.php constants)
2. Change WPAIC_MASTER_KEY or AUTH_KEY
3. Navigate to plugin admin settings page
4. Plugin detects key change:
   a. Attempt to decrypt each sensitive field with new key
   b. If decryption fails (returns garbled data):
      - Field was encrypted with old key
      - Prompt admin to enter old key for re-encryption
5. Admin-initiated re-encryption:
   a. For each sensitive field:
      - Decrypt with old key
      - Re-encrypt with new key
      - Store updated value
   b. Log re-encryption event to audit logger
```

### 6.3 Key Change Detection

The plugin stores a hash of the current encryption key (not the key itself):

```php
$key_hash = hash('sha256', Encryption::get_derived_key());
$stored_hash = get_option('wpaic_security_key_hash', '');
if ($stored_hash !== '' && $stored_hash !== $key_hash) {
    // Key has changed -- trigger re-encryption workflow
}
update_option('wpaic_security_key_hash', $key_hash);
```

### 6.4 Limitations

- If the old key material is lost and no backup exists, encrypted values cannot be recovered
- Admin must re-enter API keys manually in this scenario
- The plugin logs this as a security event with severity `warning`

---

## 7. Plaintext Migration

### 7.1 Upgrading from Plaintext Storage

When migrating from source plugins or upgrading from a pre-encryption version:

```
1. Migration callback checks each sensitive field:
   a. Read raw value from wp_options
   b. If Encryption::is_encrypted($value): skip (already encrypted)
   c. If non-empty plaintext:
      - Encrypt the value
      - Store encrypted version
      - Log migration event
2. Migration is idempotent (is_encrypted check prevents double-encryption)
```

### 7.2 Source Plugin Key Migration

Session 07 (migration strategy) will define the key mapping from source plugin options to WPAIC options. The encryption migration runs after option key migration:

```
Source: aichat_openai_api_key (plaintext in AXI, encrypted in newer AXI versions)
  -> Target: wpaic_provider_openai_key
  -> Step 1: Copy/transform value (session 07)
  -> Step 2: Encrypt if plaintext (this spec)

Source: mxchat_options['api_key'] (always plaintext)
  -> Target: wpaic_provider_openai_key
  -> Step 1: Extract from blob (session 07)
  -> Step 2: Encrypt (this spec)

Source: baachal_gemini_api_key (always plaintext)
  -> Target: wpaic_provider_gemini_key
  -> Step 1: Copy (session 07)
  -> Step 2: Encrypt (this spec)
```

### 7.3 Backwards Compatibility

The `Settings_Manager::get()` function handles both encrypted and plaintext values transparently:
- If the value matches the encrypted envelope format: decrypt and return
- If the value is plaintext: return as-is
- This allows gradual migration without breaking functionality during the transition

---

## 8. CFI-SEC-002 Resolution

CFI-SEC-002 required: "API key encryption at rest (AES-256-GCM), migration from plaintext, key rotation."

| Requirement | Resolution | Section |
|-------------|-----------|---------|
| AES-256-GCM encryption | Cipher, IV, tag parameters fully specified | Section 2 |
| Wire format | Versioned base64 JSON envelope with IV, ciphertext, tag | Section 3 |
| Key derivation | SHA-256 from `WPAIC_MASTER_KEY` constant or `wp_salt('auth')` fallback | Section 2.1 |
| Sensitive field inventory | 8 fields across provider and rag domains | Section 4 |
| Transparent interface | Settings manager auto-encrypt/decrypt via Encryption module | Section 5 |
| Key rotation | Re-encryption procedure with key change detection | Section 6 |
| Migration from plaintext | Idempotent encrypt-if-plaintext migration callback | Section 7 |
| OpenSSL fallback | Graceful degradation with admin notice when OpenSSL unavailable | Section 2.2 |

**Status**: CFI-SEC-002 fully resolved.

---

## 9. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| AES-256-GCM with random IV per value | `axiachat-ai/includes/settings/encrypt-decrypt.php` | Adopted: cipher, IV generation, GCM tag |
| JSON envelope with version field | `axiachat-ai/includes/settings/encrypt-decrypt.php` | Adopted: `{v, m, ct, iv, tag}` structure |
| Master key from `AUTH_KEY` salt | `axiachat-ai/includes/settings/encrypt-decrypt.php` | Modified: added `WPAIC_MASTER_KEY` priority |
| Auto-decrypt in `aichat_get_setting()` | `axiachat-ai/includes/settings.php` | Adopted: transparent decrypt on read |
| Sanitize callback with encrypt | `axiachat-ai/includes/settings.php` | Adopted: encrypt during `register_setting` sanitize |
| Plaintext API keys (anti-pattern) | `mxchat-basic/includes/class-mxchat-admin.php` | Replaced: all keys encrypted |
| Plaintext API keys (anti-pattern) | `baachal/admin/tabs/general.php` | Replaced: all keys encrypted |
