# corex/attributes

Typed PostgreSQL attribute-store, filter, and schema-operation ports for CoreX.

`corex/attributes` is a neutral owned engine. It reads active scalar declarations from the existing
`EntityRegistry`/`ModuleRegistry`; it never creates a second field registry or product dependency.
Its only tables are additive `sys_attribute_*` tables.

The application selects either `jsonb` or `eav` storage. Both drivers enforce a trusted `DataScope`,
including the physical `pgsql:<database>` identity, authorization callback, record version and write
token. When a registry is bound, reads and writes also validate the active field snapshot and all
fifteen supported scalar types.

Schema requests persist an idempotent operation only. They do not execute SQL in the request path.
A trusted asynchronous worker invokes `SchemaOperationWorker` with a fresh `DataScope`; it claims
the operation transactionally, executes PostgreSQL concurrent index DDL outside that transaction,
checks the catalog, preserves a 30-day retirement grace period before a managed-index drop, and
denies stale restore epochs. The application is responsible for dispatching that worker from its
queue or console boundary; it must never be called from HTTP handling.
