# Session 02: Domain Taxonomy and Feature Schema

**Session ID**: `phase00-session02-domain-taxonomy-feature-schema`
**Status**: Not Started
**Estimated Tasks**: ~14-20
**Estimated Duration**: 2-4 hours

---

## Objective

Define a canonical domain taxonomy and feature-unit schema that downstream phases can use for consistent extraction, scoring, and dependency reasoning.

---

## Scope

### In Scope (MVP)
- Define canonical domains (provider, runtime, RAG, tools, admin, security, WooCommerce, integrations).
- Specify feature-unit schema fields (ID, source plugin, capability, maturity, dependencies, risk).
- Draft lightweight scoring dimensions for later adopt/reject decisions.

### Out of Scope
- Final capability winner selections by domain.
- Full implementation contract design for runtime modules.

---

## Prerequisites

- [ ] Session 01 inventory and anchor baseline completed.

---

## Deliverables

1. Canonical domain taxonomy definition with naming conventions.
2. Feature-unit schema template with field definitions and validation guidance.
3. Initial scoring rubric draft aligned to phase planning needs.

---

## Success Criteria

- [ ] Taxonomy terms are stable enough to prevent duplicate or conflicting domain labels.
- [ ] Feature schema supports consistent extraction entries across all three source plugins.
