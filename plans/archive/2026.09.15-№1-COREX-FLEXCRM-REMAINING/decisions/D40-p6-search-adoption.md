---
id: D40
date: 2026-09-17
status: accepted
item: P6.6
items: [P6.6, P6.7]
supersedes: []
superseded_by: null
---

# D40 — P6 search and index-lifecycle local adoption

## Решение

Владелец поручил Codex самостоятельно выбрать технические решения по
Perplexity и первичным Web-источникам и выполнить необходимое для START P6.6.
Это matching owner receipt для `Q-P2-034`–`Q-P2-039` только для frozen
`search/1` (`C-P2-019`) и `index-lifecycle/1` (`C-P2-020`) кандидатов.

`corex/search` получает только additive нейтральные Contracts и PostgreSQL
fallback: `SearchSchema`, `SearchProvider`, `SearchHydrator`,
`SearchDocuments`, `IndexDriver` и их declared DTO. На public boundary нет
Eloquent, SQL, Laravel Scout, Meilisearch SDK, endpoint, credential или
product literal. Results переносят только candidate ID/order/opaque cursor;
текущая authoritative SQL hydration повторно применяет scope, policy, schema
и field checks. PostgreSQL использует только allowlisted text-search config,
bound values, `websearch_to_tsquery` и GIN `tsvector`; не обещаются portable
rank, typo, facets или snippets.

`corex/search-meilisearch` — отдельный optional adapter package. Разрешён
ровно `meilisearch/meilisearch-php` `v1.16.1`, source ref
`f9f63e0e7d12ffaae54f7317fa8f4f4dfa8ae7b6`, GitHub archive SHA256
`24cbcdc9fc98a4c956f952a4ebb2d984a1afd1d79645b2378a1b4b3e7cd5b9d0`
и `composer.json` SHA256
`d514b4ca500bbd2e5cec7faeecbb66022a45fafbcd987c7fd82701ea54a20168`.
Не разрешены `latest`, `v2.0.0-beta.*` или иной source/ref/closure без нового
receipt. SDK и consumer-owned PSR-18 implementation требуются только этому
adapter package; base package не требует HTTP client/SDK. SDK/config/provider
absence возвращает typed `unsupported`/`not-ready`; service provider не делает
network call и не переключает silently выбранную Meilisearch operation на PG.
Local integration tests используют только isolated loopback Docker
`getmeili/meilisearch:v1.53.2@sha256:8d9bce7a169fd575290cee1ec2803315afbbb8dfa50122fb10d807ad393005aa`
(linux/amd64; upstream ref `ec006cecec6f684b1dcaee093a45ab4c05707127`).
Это не production endpoint, credential, cloud или release grant.

Schema/storage adoption ограничен package-owned tables
`corex_search_dirty`, `corex_search_lanes`, `corex_search_rebuilds` в current
trusted tenant/boxed connection. Разрешены ровно declared migration profile
variants `pg16.uuid`/`pg16.ulid`/`pg16.int` и
`sqlite.uuid`/`sqlite.ulid`/`sqlite.int` с SHA из `C-P2-020`; migration before
enable выбирает один current dialect/`IdColumns` variant и проверяет emitted
fingerprint. Existing schemas, root/central DB, consumer FK and tenancy truth
не меняются. Populated down-migration или unknown/alive external sender
отказываются; tombstone/task state retention нельзя сокращать без proven worker
and task horizon.

Meilisearch остаётся derived index: one durable writer lane per scoped
generation, submission/202 не считается completion, unknown task/swap
quarantines lane, no blind retry or inverse swap. Rebuild/swap use candidate
generation, drain/fence/current reconciliation; external index never becomes
an authority or durable source of truth.

## Почему

Это сохраняет CoreX как полезный boxed Laravel package без CRM/CMS, SaaS или
внешнего SDK. PostgreSQL documents identify GIN as the preferred text-search
index and define `websearch_to_tsquery` for user text. Meilisearch documents
that writes and swaps are asynchronous tasks; index swap is atomic but leaves
enqueued tasks unchanged. The official PHP SDK declares compatibility with
Meilisearch v1.x and a PSR-18 client requirement. Sources checked:

- https://www.postgresql.org/docs/current/textsearch-indexes.html
- https://www.postgresql.org/docs/current/functions-textsearch.html
- https://github.com/meilisearch/meilisearch-php/tree/f9f63e0e7d12ffaae54f7317fa8f4f4dfa8ae7b6
- https://www.meilisearch.com/docs/reference/api/async-task-management/list-tasks
- https://www.meilisearch.com/docs/reference/api/indexes/swap-indexes

## Consequences

Это разрешение только P6.6/P6.7 local source, schema, dependency and adapter
work declared in their Files. Оно не разрешает изменение existing Contracts,
new registry/engine/tenancy policy, secrets, external production endpoint,
cloud/IAM, release, consumer acceptance or hosted/live claims. Any different
server image/architecture, SDK closure, SQL profile/fingerprint or provider
capability needs a matching owner receipt.
