# Implementation Notes

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Session Summary

Session 07 closes Phase 02. Key outputs:
- Option migration framework (CFI-MIG-003 resolved): idempotent, versioned, rollback-safe
- Migration strategies for all 3 source plugins (AXI: ~30 keys; MXC: blob decomposition ~20 keys; BAA: ~15 key renames)
- Hook and filter architecture: 40+ named hooks with signatures
- Extension point registry: tools/integrations/WooCommerce stub hooks for Phase 07+
- Carry-forward reconciliation: 28/33 RESOLVED, 5/33 RE-DEFERRED (Phase 07/08)
- Phase 02 exit validation: all 21 exit criteria PASS
- Phase 03 handoff bundle: complete implementation order and deliverable index

**Phase 02 is complete.** Phase 03 can begin.

---

## 2. CFI Resolution Table

| CFI ID | Status | Session | Deliverable |
|--------|--------|---------|-------------|
| CFI-MIG-003 | RESOLVED HERE | S07 | option_migration_framework.md |
| CFI-TOL-001/002/003/004 | RE-DEFERRED | Phase 07 | extension_point_registry.md |
| CFI-INT-001/002/003 | RE-DEFERRED | Phase 07 | extension_point_registry.md |
| CFI-WOO-001/002/003/004 | RE-DEFERRED | Phase 08 | extension_point_registry.md |

All other 25 CFI items: RESOLVED in sessions 02-06 (see carry_forward_reconciliation.md).

---

## 3. Key Architectural Decisions

### Decision 1: AXI encryption is compatible with WPAIC
AxiaChat uses the same AES-256-GCM wire format (base64 JSON envelope with `{v, m, iv, ct, tag}`)
and the same `wp_salt('auth')` key derivation fallback. WPAIC's Encryption::is_encrypted()
detector correctly identifies AXI-encrypted values. Migration can decrypt with WPAIC key and
re-encrypt -- works on sites where AXI used the salt-based key (most sites).

### Decision 2: MxChat migration complexity is manageable via define_from_blob()
The monolithic `mxchat_options` blob contains ~20 settings. The `define_from_blob()` method
handles all sub-key extraction. No custom parser needed. The blob is a standard PHP array
serialized by update_option().

### Decision 3: Tools extension hooks are pre-defined but no-op in MVP
The message pipeline's Stage 5 (tool execution) fires `wpaic_tool_execute_{slug}` regardless.
If no extension registers a handler, `apply_filters()` returns null and the pipeline skips.
No code changes needed in Phase 07 to activate tools -- just register handlers.

### Decision 4: WooCommerce extension point is the RAG context filter
WooCommerce product context injection uses `wpaic_rag_context` filter (priority 20) to append
product context to the assembled context string. This avoids coupling WooCommerce to the RAG
module internals. Phase 08 implementation requires no changes to `class-context-assembler.php`.

---

## 4. Migration Sequencing Recommendation for Phase 10

When implementing actual migration code (Phase 10), run in this order:
1. axiachat_migration (most complex, decrypt-re-encrypt pass)
2. mxchat_migration (blob decomposition)
3. baachal_migration (simple renames)
4. encryption_pass (encrypt all plaintext keys from any source)
5. Source option cleanup (delete_source=true on second run, after validation)

Each migration uses a unique migration_id sentinel to prevent re-running:
- `axiachat_v1`, `mxchat_v1`, `baachal_v1`, `encryption_pass_v1`

---

## 5. Phase 03 Priority Guidance

The critical path for Phase 03 (fastest to a working plugin):
1. Bootstrap + constants + autoloader (no deps)
2. DB schema creation (migration runner creates tables on activation)
3. Security: encryption + sanitizer (required by all layers)
4. Provider: OpenAI adapter only (enables first chat test)
5. Runtime: message pipeline + session manager (enables end-to-end test)
6. Widget shortcode + admin settings page (enables user-facing test)

The RAG pipeline, advanced providers, and admin diagnostic panels can follow in parallel
tracks or later sessions.
