# Session 04: Security, Tools, and Integrations Domains

**Session ID**: `phase01-session04-security-tools-and-integrations-domains`
**Status**: Complete (2026-03-01)
**Tasks**: 22
**Duration**: ~2 hours

---

## Objective

Extract, score, and select winners for the Security (DOM-SECURITY), Tools (DOM-TOOLS), and Integrations (DOM-INTEGRATIONS) domains. These three domains share cross-cutting trust-boundary concerns and include known N/A parity gaps for Baachal.

---

## Scope

### In Scope (MVP)
- Assemble security domain context packs from all 3 sources
- Extract security feature units (nonce checks, capability checks, sanitization, moderation, privacy controls)
- Score security capabilities across 5 dimensions
- Record security domain decisions
- Assemble tools domain context packs from AxiaChat and MxChat (Baachal is N/A)
- Extract tools feature units (callable tools, macro registry, schema validation, callbacks)
- Score tools capabilities across 5 dimensions (Baachal scored as explicit N/A)
- Record tools domain decisions
- Assemble integrations domain context packs from AxiaChat and MxChat (Baachal is N/A)
- Extract integrations feature units (MCP, webhooks, external channels, Slack/Telegram)
- Score integrations capabilities across 5 dimensions (Baachal scored as explicit N/A)
- Record integrations domain decisions

### Out of Scope
- Security architecture or hardening implementation (Phase 09)
- Tool execution engine design (Phase 02+)
- Integration adapter implementation (Phase 07)
- Domains other than DOM-SECURITY, DOM-TOOLS, DOM-INTEGRATIONS

---

## Prerequisites

- [ ] Sessions 01-03 completed (6 domains scored, pattern established)
- [ ] N/A scoring policy from domain taxonomy reviewed

---

## Reference Anchors

### Security
- `ANC-SEC-AXI-001`: `axiachat-ai/includes/moderation.php`, `axiachat-ai/includes/sanitize-helpers.php`, `axiachat-ai/includes/class-aichat-ajax.php`
- `ANC-SEC-MXC-001`: `mxchat-basic/admin/class-ajax-handler.php`, `mxchat-basic/mxchat-basic.php`
- `ANC-SEC-BAA-001`: `baachal/baachal.php`, `baachal/admin/settings-page.php`

### Tools
- `ANC-TOL-AXI-001`: `axiachat-ai/includes/add-ons/ai-tools/api.php`, `axiachat-ai/includes/add-ons/ai-tools/macro-api.php`
- `ANC-TOL-MXC-001`: `mxchat-basic/js/mxchat_actions.js`, `mxchat-basic/includes/admin-actions-page.php`
- `ANC-TOL-BAA-NA`: N/A (no matching implementation) -- Baachal has no standalone tools/macro registry

### Integrations
- `ANC-INT-AXI-001`: `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php`, `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php`
- `ANC-INT-MXC-001`: `mxchat-basic/includes/class-mxchat-addons.php`
- `ANC-INT-BAA-NA`: N/A (no matching implementation) -- Baachal has no non-provider integration subsystem

---

## Deliverables

1. Security domain context pack and feature extraction entries
2. Security domain scored comparison and decision log
3. Tools domain context pack and feature extraction entries (2-source + 1 N/A)
4. Tools domain scored comparison and decision log
5. Integrations domain context pack and feature extraction entries (2-source + 1 N/A)
6. Integrations domain scored comparison and decision log

---

## Success Criteria

- [ ] Security domain has scored entries for all 3 sources across 5 dimensions
- [ ] Security winner selected with documented rationale
- [ ] Tools domain has scored entries for AxiaChat and MxChat, explicit N/A for Baachal
- [ ] Tools winner selected with rationale (2-source comparison)
- [ ] Integrations domain has scored entries for AxiaChat and MxChat, explicit N/A for Baachal
- [ ] Integrations winner selected with rationale (2-source comparison)
- [ ] Security cross-cutting concerns noted for all other domains
