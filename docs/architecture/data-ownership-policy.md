# Data ownership policy

Articles `DATA-001` through `DATA-007` of the constitution define the invariants. This policy explains how to apply them.

## Declaring a table owner

Assign an owner to each table. A prefix is a naming convention and can span packages. For example, `axiomasoft/corex`, `axiomasoft/corex-custom-fields`, `axiomasoft/corex-workflow` and `axiomasoft/corex-scheduler` all own tables with the `sys_` prefix.

Record the table name, owner, migration directory, classification, permitted consumers, foreign key exceptions, retention policy and personal data classification in the [table ownership catalogue](table-ownership.md).

| Classification | Meaning |
|:--|:--|
| `private` | The owner's implementation detail. Other packages cannot read the table. |
| `projection` | A projection that the owner explicitly exposes for external reads. |
| `outbox` | The owner's outgoing message queue. |
| `reporting` | An object used only for reporting, outside the transaction path. |

Shared data still requires an owner with a named capability. There is no category for ownerless tables or for tables assigned to a generic “common” package.

## Working with data owned by another package

1. Call the owner's contract when the operation needs immediate consistency.
2. Publish or consume an event when eventual consistency is sufficient.
3. Maintain a local projection from events for frequent reads.

An ORM relationship across a package boundary is subject to the same direct-access restriction as a SQL query.

## Cross-package foreign keys

By default, reference an external record through a typed identifier without a database constraint. The owning contract, event processing or reconciliation maintains integrity. A cross-package foreign key requires **all** of the following:

- The packages are always installed together in practice.
- The relationship is fundamental and both sides share a lifecycle.
- Omitting the constraint causes a concrete integrity or security defect.
- Both owners accept the coordinated schema change.
- A decision records the owner and the condition for removing the exception.

A permanent exception requires a named decision that takes responsibility for it.

## Changing an existing schema

The schema policy defines sets, scopes, commands and the baseline format (`DATA-007`). `composer schema-gate` checks that the set matches the ownership record's `tenant_scope` (S9). `composer table-ownership-gate` checks cross-package foreign keys (T4). `composer table-write-ownership-gate` checks writes to tables owned by another package. See the [schema policy](schema-policy.md).

Released migration files are immutable starting with `1.0.0-rc.1` (`DATA-005`). Ship a schema change in a new migration file and increment the set version in the same commit. Introduce a table only when the relevant plan phase explicitly calls for it, and register ownership in the same work item (`DATA-006`).
