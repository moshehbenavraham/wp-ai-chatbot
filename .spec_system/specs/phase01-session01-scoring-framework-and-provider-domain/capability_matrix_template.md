# Canonical Capability Matrix Template

**Version**: `1.0`
**Session ID**: `phase01-session01-scoring-framework-and-provider-domain`
**Created**: 2026-03-01
**Source Schemas**: `scoring_rubric.md`, `feature_unit_schema.md`, `domain_taxonomy.md`

---

## Purpose

Reusable structure for scoring and comparing capabilities across all 9 canonical domains. Each domain occupies a section with standardized feature entries, scored comparisons, and decision records. Sessions 02-05 populate their respective domains using this template.

---

## Usage Instructions

1. Copy the **Domain Scoring Section** below for each domain being scored in a session.
2. Fill one **Feature Entry Row** per extracted capability using `feature_unit_schema.md` fields.
3. Fill one **Scored Comparison Row** per capability using `scoring_rubric.md` dimensions.
4. Fill one **Decision Record** per capability using `decision_log_template.md` outcome fields.
5. Complete the **Domain Summary** after all capabilities in that domain are scored.
6. Domains with fewer than 3 sources use `N/A (no matching implementation)` per the N/A policy.

---

## Matrix Structure Overview

```
Matrix
  +-- Domain Section (repeat per domain)
  |     +-- Domain Metadata
  |     +-- Feature Entry Table (from feature_unit_schema.md)
  |     +-- Scored Comparison Table (from scoring_rubric.md)
  |     +-- Decision Record Table (from decision_log_template.md)
  |     +-- Domain Summary
  +-- Cross-Domain Summary (end of matrix)
```

---

## Domain Coverage Index

| Domain ID | Canonical Name | SRC-AXI | SRC-MXC | SRC-BAA | Scored In Session |
|-----------|----------------|---------|---------|---------|-------------------|
| `DOM-PROVIDER` | provider | `ANC-PRO-AXI-001` | `ANC-PRO-MXC-001` | `ANC-PRO-BAA-001` | `phase01-session01` |
| `DOM-RUNTIME` | runtime | `ANC-RUN-AXI-001` | `ANC-RUN-MXC-001` | `ANC-RUN-BAA-001` | `phase01-session02` |
| `DOM-RAG` | rag | `ANC-RAG-AXI-001` | `ANC-RAG-MXC-001` | `ANC-RAG-BAA-001` | `phase01-session03` |
| `DOM-TOOLS` | tools | `ANC-TOL-AXI-001` | `ANC-TOL-MXC-001` | `ANC-TOL-BAA-NA` | `phase01-session04` |
| `DOM-ADMIN` | admin | `ANC-ADM-AXI-001` | `ANC-ADM-MXC-001` | `ANC-ADM-BAA-001` | `phase01-session03` |
| `DOM-SECURITY` | security | `ANC-SEC-AXI-001` | `ANC-SEC-MXC-001` | `ANC-SEC-BAA-001` | `phase01-session04` |
| `DOM-WOOCOMMERCE` | woocommerce | `ANC-WOO-AXI-001` | `ANC-WOO-MXC-001` | `ANC-WOO-BAA-001` | `phase01-session05` |
| `DOM-INTEGRATIONS` | integrations | `ANC-INT-AXI-001` | `ANC-INT-MXC-001` | `ANC-INT-BAA-NA` | `phase01-session04` |
| `DOM-MIGRATION` | migration | `ANC-MIG-AXI-001` | `ANC-MIG-MXC-001` | `ANC-MIG-BAA-001` | `phase01-session02` |

---

## Domain Scoring Section (Repeatable Template)

### Domain: `<DOM-*>` - `<canonical_name>`

**Anchor Coverage**:

| Source | Anchor ID | Source Path | Coverage Status |
|--------|-----------|-------------|-----------------|
| `SRC-AXI` | `<anchor_id>` | `<path>` | `implemented` or `N/A (no matching implementation)` |
| `SRC-MXC` | `<anchor_id>` | `<path>` | `implemented` or `N/A (no matching implementation)` |
| `SRC-BAA` | `<anchor_id>` | `<path>` | `implemented` or `N/A (no matching implementation)` |

**Context Pack Reference**: `<pack_id>`

---

#### Feature Entry Table

One row per extracted capability. Fields from `feature_unit_schema.md`.

| Feature ID | Source | Capability Name | Capability Summary | Status | Maturity | Dependency | Risk | Secondary Domains |
|------------|--------|-----------------|-------------------|--------|----------|------------|------|-------------------|
| `FEAT-<SRC>-<DOM>-<NNN>` | `SRC-*` | `<3-80 chars>` | `<1-3 sentences>` | `implemented` / `na_no_matching_implementation` | `<maturity_enum>` | `<dependency_enum>` | `<risk_enum>` | `<optional>` |

**N/A Entry Convention**: When `implementation_status` is `na_no_matching_implementation`, include `na_reason` in the Capability Summary field as: `"[N/A] <na_reason>"`.

---

#### Scored Comparison Table

One row per source per capability. Dimensions from `scoring_rubric.md`.

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|------------|--------|---------------------|------------------------|---------------------|------------------------|--------------------------|----------------|------|
| `FEAT-*` | `SRC-*` | `<0-5>` | `<0-5>` | `<0-5>` | `<0-5>` | `<0-5>` | `<computed>` | `adopt` / `investigate` / `reject` |

**Weighted Total Formula**: `(coverage * 0.30) + (reliability * 0.20) + (security * 0.20) + (operability * 0.15) + (extensibility * 0.15)`

**Band Thresholds**:
- `adopt`: >= 3.8, no dimension below 3, DIM-SECURITY >= 3
- `investigate`: 2.8-3.7, or any single dimension below 3 with mitigations possible
- `reject`: < 2.8, or DIM-SECURITY <= 2, or critical parity gaps without mitigation

---

#### Evidence Notes

One block per scored capability per source. Provides dimension-level justification.

```
### <Feature ID> - <Source>

- Coverage: <0-5> - <one-line evidence>
- Reliability: <0-5> - <one-line evidence>
- Security: <0-5> - <one-line evidence>
- Operability: <0-5> - <one-line evidence>
- Extensibility: <0-5> - <one-line evidence>

- Weighted Summary: <numeric> (<interpretation>)
- Recommendation: <adopt | investigate | reject>
- Residual Risks: <known gaps or N/A constraints>
```

---

#### Decision Record Table

One row per capability decision. Fields from `decision_log_template.md`.

| Decision ID | Capability Name | Winner | Outcome | Band | Rationale Summary | Affected Domains | Carry-Forward |
|-------------|-----------------|--------|---------|------|-------------------|------------------|---------------|
| `DEC-<DOM>-<NNN>` | `<name>` | `SRC-*` or `N/A` | `adopt_as_is` / `adopt_with_modifications` / `reject_defer` | `adopt` / `investigate` / `reject` | `<2-3 sentences>` | `<DOM-* list>` | `<CFI-* list or none>` |

**Tie-Break Documentation** (when scores within 0.2):

| Decision ID | Tie-Break Applied | Rule Used | Rationale |
|-------------|-------------------|-----------|-----------|
| `DEC-*` | `yes` / `no` | `security_preference` / `dependency_burden` / `migration_compatibility` | `<explanation>` |

---

#### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | `<N>` |
| Adopt-As-Is | `<N>` |
| Adopt-With-Modifications | `<N>` |
| Reject/Defer | `<N>` |
| Domain Winner | `<SRC-* or mixed>` |
| Dominant Maturity | `<maturity_enum>` |
| Key Risks | `<summary>` |
| Cross-Domain Dependencies | `<DOM-* list>` |

---

## Cross-Domain Summary (End of Matrix)

Complete after all domains have been scored across sessions 01-05.

### Winner Summary

| Domain ID | Winner | Outcome | Weighted Total | Key Modification |
|-----------|--------|---------|----------------|------------------|
| `DOM-PROVIDER` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |
| `DOM-RUNTIME` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |
| `DOM-RAG` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |
| `DOM-TOOLS` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |
| `DOM-ADMIN` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |
| `DOM-SECURITY` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |
| `DOM-WOOCOMMERCE` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |
| `DOM-INTEGRATIONS` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |
| `DOM-MIGRATION` | `<SRC-*>` | `<outcome>` | `<score>` | `<brief note or N/A>` |

### Carry-Forward Register

| Item ID | Domain | Type | Priority | Target Session |
|---------|--------|------|----------|----------------|
| `CFI-*` | `DOM-*` | `<type>` | `<priority>` | `<session_id>` |

### Matrix Validation

- [ ] All 9 domains represented (scored or deferred with rationale)
- [ ] All feature entries validate against `feature_unit_schema.md`
- [ ] All scores use `scoring_rubric.md` dimensions and weights
- [ ] All decisions follow `decision_log_template.md` contract
- [ ] Three-source coverage or explicit N/A for every domain
- [ ] Cross-domain dependencies documented
- [ ] Carry-forward items linked to target sessions
