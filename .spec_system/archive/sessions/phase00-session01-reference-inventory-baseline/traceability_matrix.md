# Traceability Matrix

**Session ID**: `phase00-session01-reference-inventory-baseline`  
**Purpose**: Crosswalk between inventory entries and anchor IDs for downstream reuse.

---

## Matrix Columns

| Anchor ID | Source ID | Inventory IDs | Domain | Primary Path | Rationale Tags | Promotion Status |
|---|---|---|---|---|---|---|

---

## Matrix Body

| Anchor ID | Source ID | Inventory IDs | Domain | Primary Path | Rationale Tags | Promotion Status |
|---|---|---|---|---|---|---|
| `ANC-PRO-AXI-001` | `SRC-AXI` | `INV-AXI-002` | `provider` | `axiachat-ai/includes/class-aichat-provider-registry.php` | `provider-abstraction`, `integration-boundary` | `candidate` |
| `ANC-PRO-MXC-001` | `SRC-MXC` | `INV-MXC-002` | `provider` | `mxchat-basic/includes/class-mxchat-integrator.php` | `provider-abstraction`, `integration-boundary` | `candidate` |
| `ANC-PRO-BAA-001` | `SRC-BAA` | `INV-BAA-001` | `provider` | `baachal/baachal.php` | `provider-abstraction` | `candidate` |
| `ANC-RUN-AXI-001` | `SRC-AXI` | `INV-AXI-003` | `runtime` | `axiachat-ai/includes/class-aichat-ajax.php` | `runtime-orchestration` | `candidate` |
| `ANC-RUN-MXC-001` | `SRC-MXC` | `INV-MXC-004` | `runtime` | `mxchat-basic/includes/class-mxchat-public.php` | `runtime-orchestration` | `candidate` |
| `ANC-RUN-BAA-001` | `SRC-BAA` | `INV-BAA-005`, `INV-BAA-006` | `runtime` | `baachal/assets/chatbot.js` | `runtime-orchestration` | `candidate` |
| `ANC-RAG-AXI-001` | `SRC-AXI` | `INV-AXI-004` | `rag` | `axiachat-ai/includes/contexto-functions.php` | `rag-retrieval-core` | `candidate` |
| `ANC-RAG-MXC-001` | `SRC-MXC` | `INV-MXC-005` | `rag` | `mxchat-basic/admin/class-knowledge-manager.php` | `rag-retrieval-core` | `candidate` |
| `ANC-RAG-BAA-001` | `SRC-BAA` | `INV-BAA-002` | `rag` | `baachal/includes/content-indexer.php` | `rag-retrieval-core` | `candidate` |
| `ANC-TOL-AXI-001` | `SRC-AXI` | `INV-AXI-007` | `tools` | `axiachat-ai/includes/add-ons/ai-tools/api.php` | `tool-execution` | `candidate` |
| `ANC-TOL-MXC-001` | `SRC-MXC` | `INV-MXC-002` | `tools` | `mxchat-basic/includes/class-mxchat-integrator.php` | `tool-execution` | `candidate` |
| `ANC-TOL-BAA-NA` | `SRC-BAA` | `INV-BAA-001` | `tools` | `baachal/baachal.php` | `tool-execution` | `na` |
| `ANC-ADM-AXI-001` | `SRC-AXI` | `INV-AXI-005` | `admin` | `axiachat-ai/includes/settings.php` | `admin-configuration-surface` | `candidate` |
| `ANC-ADM-MXC-001` | `SRC-MXC` | `INV-MXC-003` | `admin` | `mxchat-basic/includes/class-mxchat-admin.php` | `admin-configuration-surface` | `candidate` |
| `ANC-ADM-BAA-001` | `SRC-BAA` | `INV-BAA-003` | `admin` | `baachal/admin/settings-page.php` | `admin-configuration-surface` | `candidate` |
| `ANC-SEC-AXI-001` | `SRC-AXI` | `INV-AXI-003` | `security` | `axiachat-ai/includes/moderation.php` | `security-enforcement` | `candidate` |
| `ANC-SEC-MXC-001` | `SRC-MXC` | `INV-MXC-002` | `security` | `mxchat-basic/includes/class-mxchat-integrator.php` | `security-enforcement` | `candidate` |
| `ANC-SEC-BAA-001` | `SRC-BAA` | `INV-BAA-001` | `security` | `baachal/baachal.php` | `security-enforcement` | `candidate` |
| `ANC-WOO-AXI-001` | `SRC-AXI` | `INV-AXI-004` | `woocommerce` | `axiachat-ai/includes/contexto-functions.php` | `woocommerce-domain-logic` | `candidate` |
| `ANC-WOO-MXC-001` | `SRC-MXC` | `INV-MXC-006` | `woocommerce` | `mxchat-basic/includes/class-mxchat-woocommerce.php` | `woocommerce-domain-logic` | `candidate` |
| `ANC-WOO-BAA-001` | `SRC-BAA` | `INV-BAA-004` | `woocommerce` | `baachal/admin/tabs/woocommerce.php` | `woocommerce-domain-logic` | `candidate` |
| `ANC-INT-AXI-001` | `SRC-AXI` | `INV-AXI-008` | `integrations` | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` | `integration-boundary` | `candidate` |
| `ANC-INT-MXC-001` | `SRC-MXC` | `INV-MXC-002` | `integrations` | `mxchat-basic/includes/class-mxchat-integrator.php` | `integration-boundary` | `candidate` |
| `ANC-INT-BAA-NA` | `SRC-BAA` | `INV-BAA-001` | `integrations` | `baachal/baachal.php` | `integration-boundary` | `na` |
| `ANC-MIG-AXI-001` | `SRC-AXI` | `INV-AXI-001` | `migration` | `axiachat-ai/axiachat-ai.php` | `migration-upgrade-safety` | `candidate` |
| `ANC-MIG-MXC-001` | `SRC-MXC` | `INV-MXC-001` | `migration` | `mxchat-basic/mxchat-basic.php` | `migration-upgrade-safety` | `candidate` |
| `ANC-MIG-BAA-001` | `SRC-BAA` | `INV-BAA-001` | `migration` | `baachal/baachal.php` | `migration-upgrade-safety` | `candidate` |

---

## Rationale Tag Set

- `bootstrap-control-point`
- `runtime-orchestration`
- `provider-abstraction`
- `rag-retrieval-core`
- `tool-execution`
- `admin-configuration-surface`
- `security-enforcement`
- `woocommerce-domain-logic`
- `integration-boundary`
- `migration-upgrade-safety`
