# axiomasoft/corex-search

`axiomasoft/corex-search` provides typed candidate search over a consumer-declared, allowlisted SQL schema. It never treats an index as authorization: consumers must hydrate candidates through their current policy boundary.

Install it with `composer require axiomasoft/corex-search`, bind `SearchSchema`, and set `corex-search.driver` to `postgres` only for an approved PostgreSQL profile. Without that binding the provider reports unsupported capabilities and does not perform network or database work.

Indexing is optional and tenant-local. `corex_search_dirty` retains a committed mutation receipt; the single durable lane fences each provider task before another batch may be submitted. An unknown provider or swap outcome quarantines the lane and requires reconciliation: it is never retried by lease expiry. Rebuilds use a new generation and only publish after the lane has drained.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
