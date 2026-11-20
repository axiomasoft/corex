# axiomasoft/corex-search

Applications resolve `CoreX\Search\Query\Contracts\Search` and call `query($scope, $query)`. Each hit contains a record reference and a typed projection from the current owner. The caller selects an entity, text and its search fields, equality filters, sort keys, result fields and optional statistics. The page limit is 1–100.

Bind a `SearchSource` for current owner records. Tag each `SearchProjectionContributor` with its interface name. Each contributor declares stable field IDs, types, allowed uses and a SHA-256 revision covering its mapping and any external dataset it uses. A projected field has one declaring contributor. Bind the Core record authorizer and field-use policy with current revision witnesses. They must agree on the exact corpus for each principal and use.

Set `corex-search.driver`, `corex-search.cluster` and a private `corex-search.cursor_secret` of at least 32 bytes. PostgreSQL profiles bind an owner-scoped `PostgresCorpus` with allowlisted SQL columns. Indexed drivers require a published generation for the current binding and restore epoch. Register candidate and index factories with `SearchDriverRegistry`. Candidate factories resolve inside verified queries. The index worker authorizes submissions, and receipt recovery authorizes lookup of its exact durable batch.

The query intersects record and operation grants before candidate lookup and checks document identity before owner load. The owner supplies current values for equality checks and result fields. A text match also requires matching field-content digests from the index and current projection for every authorized search field used by that query. The final check repeats policy, mapping, generation and returned-record witnesses. A first page may restart once within its remaining scan budget.

A continuation may lead to an empty page. It is authenticated and encrypted for the query, principal, physical and logical scope, grants, mapping and generation. A changed generation requires a new search. Concurrent owner updates may move a matching record across page boundaries. The default call budget is 1,000 candidates, including a restart. An exact count or requested facet needs a verified PostgreSQL snapshot shared by the complete owner corpus, source, projections and ACL. A candidate profile returns typed unavailable statistics.

The index lane journals dirty records and tracks each generation's contiguous confirmed sequence. Rebuilds scan into a new immutable UID, catch it up, and publish its SQL pointer under the cutover barrier. Ambiguous submissions require reconciliation against the original target. The retention window for superseded generations defaults to 604,800 seconds and is configurable with `corex-search.retention_seconds`.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
