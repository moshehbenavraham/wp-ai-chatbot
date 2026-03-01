# Module Dependency Graph

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Visual Dependency Graph (ASCII)

Arrows indicate "depends on" (source depends on target). Deferred modules are in brackets.

```
                        +---------------------+
                        |   Presentation      |
                        | (admin/, assets/,   |
                        |  templates/)        |
                        +---------------------+
                               |
                               v
+----------------------------------------------------------------------+
|                        Domain Modules                                 |
|                                                                      |
|                                                                      |
|              +-------+          +---------+                          |
|              | admin |--------->| provider|<-----------+             |
|              +-------+    +--->+---------+             |             |
|              | | | | |    |         ^                  |             |
|              | | | | |    |         |                  |             |
|              v v v v v    |    +----+---+         +----+----+        |
|    +---------+ | | |     |    | runtime |         |   rag   |        |
|    |migration| | | |     |    +---------+         +---------+        |
|    +---------+ | | |     |    | | |               | |                |
|                | | |     |    | | +------+--------+ |                |
|                | v |     |    | v        v          v                |
|                | +-+-----+--->+----------+-----------+               |
|                |              | security              |               |
|                |              +----------+------------+               |
|                |                         ^                           |
|                |                         |                           |
|    +-----------+-----------+   +---------+----------+                |
|    |  [tools]  |           |   | [woocommerce]      |                |
|    +-----------+           |   +--------------------+                |
|         ^                  |             ^                           |
|         |                  |             |                           |
|    +----+-----------+      +-------------+                           |
|    | [integrations] |                                                |
|    +----------------+                                                |
|                                                                      |
+----------------------------------------------------------------------+
                               |
                               v
                   +------------------------+
                   |   Infrastructure       |
                   | (lib/, WordPress API)  |
                   +------------------------+
```

---

## 2. Simplified Directed Graph

### 2.1 MVP Module Dependencies

```
admin      -> provider, runtime, rag, security, migration
runtime    -> provider, security, rag
rag        -> provider, security
provider   -> security
migration  -> (none)
security   -> (none)
```

### 2.2 Deferred Module Dependencies

```
tools         -> provider, security
integrations  -> tools, security
woocommerce   -> rag, tools, security
```

### 2.3 Infrastructure Dependencies

```
lib/       -> WordPress API only (no domain modules)
All domain modules -> lib/ (optional), WordPress API
```

---

## 3. Topological Sort (Cycle-Free Verification)

A valid topological ordering proves no circular dependencies exist. Starting from leaf modules
(no outgoing domain dependencies) and working up:

**MVP modules topological order**:

```
Level 0 (leaf):     security, migration
Level 1:            provider  (depends on: security)
Level 2:            rag       (depends on: provider, security)
Level 3:            runtime   (depends on: provider, security, rag)
Level 4:            admin     (depends on: provider, runtime, rag, security, migration)
```

**Deferred modules topological order** (extends MVP):

```
Level 1.5:          tools         (depends on: provider, security)
Level 2.5:          integrations  (depends on: tools, security)
Level 3.5:          woocommerce   (depends on: rag, tools, security)
```

**Result**: Topological sort succeeds. **No circular dependencies exist.**

---

## 4. Dependency Matrix

Read as "row depends on column". `D` = direct dependency, `.` = no dependency.

```
                 security  migration  provider  rag  runtime  admin  tools  integr  woocom
security            .         .         .       .      .       .      .       .       .
migration           .         .         .       .      .       .      .       .       .
provider            D         .         .       .      .       .      .       .       .
rag                 D         .         D       .      .       .      .       .       .
runtime             D         .         D       D      .       .      .       .       .
admin               D         D         D       D      D       .      .       .       .
[tools]             D         .         D       .      .       .      .       .       .
[integrations]      D         .         .       .      .       .      D       .       .
[woocommerce]       D         .         .       D      .       .      D       .       .
```

### Verification checks:
- **No `D` on the diagonal**: No module depends on itself.
- **Upper triangle sparse**: Most dependencies flow from higher-level to lower-level modules.
- **Security column is densest**: Security is a foundational policy module used by most modules.
- **Migration column has one entry**: Only admin depends on migration (for version display/controls).
- **Deferred columns are empty**: No MVP module depends on deferred modules.

---

## 5. Cross-Domain Integration Points Mapped

All 12 integration points from the Phase 01 handoff bundle are represented in this graph:

| # | Integration Point | Graph Edge | Verified |
|---|------------------|-----------|----------|
| 1 | Provider dispatch | runtime -> provider | Yes |
| 2 | Tool injection | [tools] -> provider | Yes |
| 3 | Moderation gate | runtime -> security | Yes |
| 4 | Session validation | runtime -> security | Yes |
| 5 | Context policies | rag -> security | Yes |
| 6 | Key encryption | admin -> security | Yes (via provider -> security for reads) |
| 7 | Embedding lookup | rag -> provider | Yes |
| 8 | Product enrichment | [woocommerce] -> rag | Yes |
| 9 | Cart as tool | [woocommerce] -> [tools] | Yes |
| 10 | Tool source tracking | [integrations] -> [tools] | Yes |
| 11 | Diagnostics panels | security -> admin (via hook) | Yes (hook-based, no import dependency) |
| 12 | Extension lifecycle | [integrations] -> [tools] | Yes |

**Note on integration point 11**: Security registers diagnostic panels via the
`wpaic_admin_register_diagnostic_panel` action hook. This is hook-based communication, NOT an
import dependency. Security does not import admin module code. Admin fires the hook; security
listens. This preserves security's leaf module status.

---

## 6. Circular Dependency Prevention

Two potential circular dependency pairs were identified in Phase 01:

### 6.1 CFI-PRO-001 <-> CFI-RUN-003 (Streaming)

**Risk**: Provider adapters need streaming transport; streaming layer needs provider-specific parsing.

**Resolution**: The `Stream_Handler` class lives in `runtime/` and receives a provider-specific
stream parser as a callback parameter. Provider adapters return a stream configuration object
that `runtime/` consumes. Provider does NOT depend on runtime -- runtime depends on provider.

```
runtime/Stream_Handler <-- calls --> provider/adapters/{Adapter}::get_stream_config()
Direction: runtime -> provider (one-way, no cycle)
```

### 6.2 CFI-RUN-004 <-> CFI-ADM-005 (Frontend + Admin JS)

**Risk**: Frontend chat and admin UX both need the jQuery-vs-vanilla decision.

**Resolution**: Both are **presentation layer** assets (`assets/js/chatbot.js` and `admin/js/admin.js`).
They share no PHP module dependency. The decision affects file contents, not module architecture.
The spec establishes vanilla JS (no jQuery) per CFI-ADM-005 in CONVENTIONS.md. Both JS files
are independent and load in separate contexts (frontend vs admin).

```
assets/js/chatbot.js   (frontend, no admin dependency)
admin/js/admin.js      (admin, no frontend dependency)
No circular dependency exists at module level.
```
