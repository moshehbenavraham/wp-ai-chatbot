# Feature Entry Template

**Session ID**: `phase00-session02-domain-taxonomy-feature-schema`
**Status**: Ready for Session 03 template reuse

---

## Usage

Use one template block per extracted capability. Keep IDs stable and always include source attribution.

---

## Canonical Template

```markdown
### Feature: <feature_id> - <capability_name>

- source_plugin: <SRC-AXI|SRC-MXC|SRC-BAA>
- source_anchor_id: <ANC-*>
- source_path: `<path/to/file>`
- domain_id: <DOM-* canonical domain id>
- secondary_domains: <[] or [DOM-*]>

#### Capability Summary
<1-3 sentence summary of behavior and scope>

#### Implementation Status
- implementation_status: <implemented | na_no_matching_implementation>
- status_label: <Implemented | N/A (no matching implementation)>
- na_reason: <required when status is na_no_matching_implementation>

#### Maturity and Operations
- maturity_level: <production_stable | production_limited | beta_or_addon | experimental | na_no_matching_implementation>
- dependency_profile: <wordpress_core_only | plugin_internal_modules | external_ai_provider_required | optional_external_service | ecommerce_stack_required | paid_addon_or_license>
- risk_level: <low | medium | high | critical>

#### Evidence
- feature_map_reference: `<docs/*-feature-map.md>`
- feature_map_section: `<section label>`
- anchor_rationale: <why this anchor represents the capability>

#### Notes
- normalization_notes: <alias mapping or collision handling notes>
```

---

## Source Attribution Slots

| Plugin | Feature Entry Slot | Expected Anchor Format |
|---|---|---|
| AxiaChat | `FEAT-AXI-*` | `ANC-*-AXI-*` |
| MxChat | `FEAT-MXC-*` | `ANC-*-MXC-*` |
| Baachal | `FEAT-BAA-*` | `ANC-*-BAA-*` or explicit `ANC-*-BAA-NA` |

---

## Source-Attributed Example Entries

### Example A - AxiaChat tools capability (implemented)

### Feature: FEAT-AXI-TOL-001 - Tool registry and execution boundary

- source_plugin: `SRC-AXI`
- source_anchor_id: `ANC-TOL-AXI-001`
- source_path: `axiachat-ai/includes/add-ons/ai-tools/api.php`
- domain_id: `DOM-TOOLS`
- secondary_domains: `[DOM-INTEGRATIONS]`

#### Capability Summary
Registers callable tools and capability controls used by runtime tool execution paths.

#### Implementation Status
- implementation_status: `implemented`
- status_label: `Implemented`
- na_reason: `n/a`

#### Maturity and Operations
- maturity_level: `production_limited`
- dependency_profile: `plugin_internal_modules`
- risk_level: `medium`

#### Evidence
- feature_map_reference: `docs/axiachat-ai-feature-map.md`
- feature_map_section: `5.1 Tool registry and execution`
- anchor_rationale: Core tool registration boundary used by runtime continuation flows.

#### Notes
- normalization_notes: "ai tools" and "macro tools" labels map to canonical `DOM-TOOLS`.

---

### Example B - MxChat integration capability (implemented)

### Feature: FEAT-MXC-INT-001 - Live agent handoff channels

- source_plugin: `SRC-MXC`
- source_anchor_id: `ANC-INT-MXC-001`
- source_path: `mxchat-basic/includes/class-mxchat-integrator.php`
- domain_id: `DOM-INTEGRATIONS`
- secondary_domains: `[DOM-RUNTIME]`

#### Capability Summary
Provides external handoff channels for Slack and Telegram through dedicated integration handlers.

#### Implementation Status
- implementation_status: `implemented`
- status_label: `Implemented`
- na_reason: `n/a`

#### Maturity and Operations
- maturity_level: `production_limited`
- dependency_profile: `optional_external_service`
- risk_level: `medium`

#### Evidence
- feature_map_reference: `docs/mxchat-basic-feature-map.md`
- feature_map_section: `6) Live Agent Integration (Slack + Telegram)`
- anchor_rationale: Integrator class hosts channel verification and dispatch callbacks.

#### Notes
- normalization_notes: "live agent" and "handoff channel" labels map to `DOM-INTEGRATIONS`.

---

### Example C - Baachal tools parity (explicit N/A)

### Feature: FEAT-BAA-TOL-001 - Dedicated tools registry parity

- source_plugin: `SRC-BAA`
- source_anchor_id: `ANC-TOL-BAA-NA`
- source_path: `baachal/baachal.php`
- domain_id: `DOM-TOOLS`
- secondary_domains: `[]`

#### Capability Summary
Baachal provides provider chat APIs but no dedicated tool registry or macro execution subsystem equivalent to AxiaChat or MxChat.

#### Implementation Status
- implementation_status: `na_no_matching_implementation`
- status_label: `N/A (no matching implementation)`
- na_reason: `No standalone tools subsystem with callable schema registration.`

#### Maturity and Operations
- maturity_level: `na_no_matching_implementation`
- dependency_profile: `plugin_internal_modules`
- risk_level: `low`

#### Evidence
- feature_map_reference: `docs/baachal-feature-map.md`
- feature_map_section: `9) Extensibility Surface`
- anchor_rationale: Anchor scope confirms extensibility hooks exist but not a dedicated tools/macro framework.

#### Notes
- normalization_notes: Record as explicit parity gap; do not infer tool capability from general hooks alone.

---

## Completeness Checks

- [ ] Source plugin and anchor IDs are present.
- [ ] Domain assignment uses canonical taxonomy terms.
- [ ] Maturity, dependency, and risk fields use schema enums.
- [ ] N/A entries use exact phrase `N/A (no matching implementation)` in human-readable reports.
- [ ] Machine-readable status uses `na_no_matching_implementation`.
