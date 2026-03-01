# Session 05: WooCommerce and Cross-Domain Resolution

**Session ID**: `phase01-session05-woocommerce-and-cross-domain-resolution`
**Status**: Not Started
**Estimated Tasks**: ~18
**Estimated Duration**: 2-4 hours

---

## Objective

Complete the final domain scoring (DOM-WOOCOMMERCE) and resolve all cross-domain conflicts and interaction points identified during sessions 01-04, producing a conflict-free domain winner map.

---

## Scope

### In Scope (MVP)
- Assemble WooCommerce domain context packs from all 3 sources
- Extract WooCommerce feature units (product context, catalog ops, cart/order handling, recommendations)
- Score WooCommerce capabilities across 5 dimensions
- Record WooCommerce domain decisions
- Review all cross-domain interaction notes from sessions 01-04
- Identify and resolve "which plugin behavior wins?" conflicts across domain boundaries
- Produce cross-domain conflict resolution register with rationale
- Update carry-forward register with any deferred cross-domain items

### Out of Scope
- WooCommerce integration implementation (Phase 08)
- Final v1 scope boundary definition (Session 06)
- Architecture contract design (Phase 02)

---

## Prerequisites

- [ ] Sessions 01-04 completed (8 of 9 domains scored)
- [ ] Cross-domain interaction notes collected from all prior sessions
- [ ] Carry-forward template available for deferred items

---

## Reference Anchors

### WooCommerce
- `ANC-WOO-AXI-001`: `axiachat-ai/includes/add-ons/appointments/class-appointments-manager.php`, `axiachat-ai/includes/add-ons/leads/class-leads-manager.php`
- `ANC-WOO-MXC-001`: `mxchat-basic/includes/class-mxchat-woocommerce.php`, `mxchat-basic/includes/class-mxchat-integrator.php`
- `ANC-WOO-BAA-001`: `baachal/admin/tabs/woocommerce.php`, `baachal/baachal.php`

---

## Deliverables

1. WooCommerce domain context pack (bounded excerpts from 3 sources)
2. WooCommerce domain feature extraction entries
3. WooCommerce domain scored comparison and decision log
4. Cross-domain conflict resolution register (all 9 domains)
5. Updated carry-forward register with deferred cross-domain items
6. Conflict-free domain winner map (all 9 domains, ready for Session 06 consolidation)

---

## Success Criteria

- [ ] WooCommerce domain has scored entries for all 3 sources across 5 dimensions
- [ ] WooCommerce winner selected with documented rationale
- [ ] All cross-domain conflicts from sessions 01-04 are resolved or explicitly deferred
- [ ] Domain winner map covers all 9 domains without unresolved "which wins?" conflicts
- [ ] Carry-forward register captures any items deferred to Phase 02+
