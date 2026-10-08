---
id: D39
date: 2026-09-16
status: accepted
item: P6.1
items: [P6.1, P6.2, P6.3]
supersedes: []
superseded_by: null
---

# D39 — CoreX-owned attributes engine

## Решение

P6 no longer requires discovery of a third-party engine that simultaneously owns PostgreSQL
DDL, EAV, JSONB and asynchronous schema operations. `corex/attributes` is the neutral engine
package. It remains generic Laravel infrastructure: no `flex/*` import or literal, CRM schema,
product quota, tenant selection, second entity registry, ID policy, or synchronous HTTP DDL.

The package consumes the existing `EntityRegistry` and `ModuleRegistry` compiled
`EntityField` declarations. It owns only its additive `sys_attribute_*` storage and operation
tables; metadata is never copied into a competing registry. Driver implementations remain
`Internal`, public ports remain `Contracts`, and schema changes are admitted as requests then
executed by a worker with a current scope/restore epoch/approval check.

This supersedes the P6.1–P6.3 restrictions that required an already-existing external engine
and prohibited an owned field/DDL engine. All original safety and PG16 evidence obligations
remain in force.

## Почему

The owner explicitly selected the clean owned-engine option after the external candidates were
shown not to provide the combined JSONB, EAV and safe asynchronous-schema capability. Keeping
the engine as a neutral CoreX package preserves the L0 doctrine while avoiding a product-owned
fork or a fabricated external qualification.
