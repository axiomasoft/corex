# Changelog

Все заметные изменения `corex/tenancy` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).
Пакет ещё не тегирован: в `v0.1.0` монорепо его нет (`git ls-tree -r v0.1.0 -- packages/tenancy` пусто).

## [Unreleased]

### Added

- **Request resolution: host → account → status gate → tenant session (P2.10).**
  `Resolvers\HostTenantResolver` (extends stancl's `CachedTenantResolver`, D119 — no bespoke
  cache class) resolves `Request::getHost()` through `root_domains` → `root_accounts`, with a
  fail-closed double guard on `slug IS NULL`/`verified_at IS NULL`/`deleted_at` (§7.3 п.9);
  `Http\Middleware\ResolveTenantFromHost` initializes tenancy with the already-hydrated model
  (D120 — no per-request root-DB `find()`); `Http\Middleware\AccountStatusGate` enforces B-11
  §5.1 access rules purely from the hydrated tenant, no extra query. `Models\Domain` (new,
  `root_domains`) + `InvalidatesResolverCache`/`InvalidatesTenantsResolverCache` on
  `Account`/`Domain` bust the resolver cache on save/delete — no TTL wait needed after a status
  or domain change. Tenant-DB `sessions` migration (Laravel-canonical form, `user_id` as uuid) +
  `ScopeSessions` complete the middleware group `'tenant'` (merged into stancl's own pre-existing
  empty group of the same name, not overwritten — see `TenancyServiceProvider::boot()`).
  Config: new `host_identification`/`status_gate` top-level keys, projected onto stancl's
  `identification.resolvers.*`/`middleware` keys under the `host_identification.enabled` flag
  (boxed profile leaves this unregistered — AC-28). `sessions` is a Laravel-framework table, not
  a `corex/*`-owned domain table — deliberately absent from `DB_SCHEMA.md` (D122).

### Changed

- **Breaking (DDL, до первого тега):** `root_account_products.product` — продуктовый
  `CHECK (product IN ('flexcrm','flexstore'))` заменён product-agnostic ограничением формы
  `CHECK (product ~ '^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$')`; допустимый НАБОР продуктов задаётся
  прикладным уровнем, схема ядра его не перечисляет. Инвариант не ослаблен: NOT NULL +
  `varchar(32)` + непустота + формат слага; таблица получила нейтральный `COMMENT`. Правка внесена
  В СУЩЕСТВУЮЩУЮ миграцию (прод-инсталляций нет) — локальные БД чинит `migrate:fresh`/пересоздание.
  Замороженный контракт `DB_SCHEMA` синхронно бампнут до `FROZEN v1.1` под владельческим
  approval-gate (D94, P2.17).
- Комментарии таблиц `root_domains` и `root_templates` продукт-нейтральны: `slug.flexcrm.ru` →
  `slug.<base-domain>`, `tpl_flex_vNN` → «версионированная шаблонная БД (имя приходит данными —
  `db_name`)» (P2.17).
- Тест-фикстура шаблонной БД переименована `tpl_flex_v1` → `tpl_tenant_v1` (`tests/Stubs/`,
  константы `TEMPLATE_DB`/`PENDING_TEMPLATE_DB`) — идентификаторы уровня 0 без Flex-привязки (P2.17).
