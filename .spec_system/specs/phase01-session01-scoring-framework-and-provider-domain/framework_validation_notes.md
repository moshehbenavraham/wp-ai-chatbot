# Framework Validation Notes

**Session ID**: `phase01-session01-scoring-framework-and-provider-domain`
**Created**: 2026-03-01

---

## 1. Rubric Usability

### What worked well

- **5-point scale with evidence requirement**: Forcing one-line evidence per dimension per score prevented subjective inflation. Scores grounded in observable behavior from source code.
- **Dimension weights**: The 30/20/20/15/15 split (coverage > reliability = security > operability = extensibility) produced intuitive rankings. Coverage driving 30% correctly elevated sources with broader behavior.
- **Decision thresholds**: The adopt (>= 3.8), investigate (2.8-3.7), reject (< 2.8) bands worked cleanly. No ambiguous boundary cases in this session. Security floor (DIM-SECURITY >= 3 for adopt) did not trigger but is a valid safety valve.
- **Tie-break rules**: Not needed in this session (SRC-AXI had clear leads), but the documented rules (security preference > dependency burden > migration compatibility) are well-ordered for future sessions.

### Observations for subsequent sessions

- **Cross-capability aggregation**: The rubric does not prescribe how to aggregate scores across multiple capabilities within a domain. This session used simple averaging across 4 capabilities. Sessions 02-05 should use the same method for consistency, or the rubric should specify the aggregation rule.
- **N/A scoring**: No N/A entries occurred in DOM-PROVIDER (all sources implement provider logic). Sessions 04 (DOM-TOOLS, DOM-INTEGRATIONS) will have Baachal N/A entries and will need to validate that the N/A policy works in the scoring context (score as 0 vs exclude from averages).
- **Decimal precision**: Weighted totals computed to 2 decimal places. This is sufficient for band assignment but creates a false sense of precision. Sessions should continue treating scores as directional, not mathematically rigid, per rubric guidance.

---

## 2. Matrix Reusability

### Structure validation

- **3-source domain**: DOM-PROVIDER exercised the full matrix structure successfully. All sections filled: feature entry table, scored comparison table, evidence notes, decision record table, domain summary.
- **9-domain coverage index**: Populated with anchor IDs and session assignments for all 9 domains. Ready for sessions 02-05 to fill their respective sections.
- **N/A domain compatibility**: The matrix template includes N/A entry conventions and handles 2-source + N/A cases. Not yet exercised with real data; sessions 04 (DOM-TOOLS with Baachal N/A) and 04 (DOM-INTEGRATIONS with Baachal N/A) will be the first tests.

### Recommended adjustments for sessions 02-05

- **Feature granularity guidance**: This session extracted 5 features from SRC-AXI (registry, 3 adapters, model catalog), 3 from SRC-MXC, and 3 from SRC-BAA. Future sessions should aim for 3-6 features per source to maintain comparable granularity. Extracting too many fine-grained features creates scoring overhead; too few loses capability-level decision precision.
- **Cross-domain tagging**: `secondary_domains` was useful for flagging security, admin, and runtime crossovers. Sessions should consistently tag cross-domain behavior to enable session 06 (consolidation) to identify integration patterns.

---

## 3. Evidence Logging Clarity

### What worked well

- **One-line evidence per dimension**: Forced conciseness while maintaining traceability. Each score can be challenged by re-reading the evidence line and cross-referencing the cited source code.
- **Residual risks section**: Captured known gaps (e.g., retry pattern only for Claude, not generalized) that feed directly into modification notes and carry-forward items.

### Improvement opportunities

- **Source code line references**: Evidence notes reference files and symbols but not specific line numbers. Adding optional line ranges (e.g., "class-claude-provider.php L145-L210") would improve auditability for subsequent sessions.
- **Comparative evidence**: The current template scores each source independently. A brief comparative note ("SRC-AXI exceeds SRC-MXC because...") would strengthen decision rationale. Currently this is captured in the decision log rationale but not in evidence notes.

---

## 4. Schema and Template Compliance

### Feature unit schema

- All 11 feature entries validated against schema contract with 0 violations.
- Schema was sufficient for all DOM-PROVIDER capabilities. No missing fields or ambiguous enum values encountered.
- The `na_reason` conditional requirement will be exercised in sessions 04-05.

### Context pack template

- 8 slices within 48000 token budget.
- Three-source coverage achieved (SRC-AXI: 5 slices, SRC-MXC: 2 slices, SRC-BAA: 1 slice). Slice count asymmetry reflects real codebase architecture differences.
- Anchor citations present on all slices.

### Decision log template

- 4 decision entries following the full template contract.
- 2 adopt_as_is, 2 adopt_with_modifications. Outcome distribution is realistic and demonstrates both code paths.
- 2 carry-forward items created and linked.
- Cross-domain interaction notes appended as specified.

---

## 5. Workflow Observations

- **Anchor-first extraction**: Starting from `ANC-PRO-*` entries in the anchor index worked well for scoping source reads. Prevented unbounded file browsing.
- **Canonical vocabulary**: All outputs use `DOM-PROVIDER`, `SRC-AXI/MXC/BAA`, `FEAT-*`, `ANC-*`, `DEC-*`, `CFI-*` consistently. No legacy labels or aliases in final artifacts.
- **Scoring is behavioral**: Following the spec guidance to "score based on behavior outcomes, not code organization" was critical. SRC-BAA's simple switch-case dispatch delivers the same basic behavior as SRC-AXI's registry, but lacks lifecycle management, extensibility, and resilience -- which the 5-dimension scoring correctly captures.

---

## 6. Recommended Adjustments

| Recommendation | Priority | Target |
|----------------|----------|--------|
| Define cross-capability aggregation method in rubric | medium | Sessions 02-05 |
| Validate N/A scoring approach in session 04 (DOM-TOOLS) | high | Session 04 |
| Add optional line ranges to evidence notes | low | Sessions 02-05 |
| Maintain 3-6 features per source per domain for consistency | medium | Sessions 02-05 |
