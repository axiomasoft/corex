# Changelog

Все заметные изменения `corex/tenancy` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).
Пакет ещё не тегирован: в `v0.1.0` монорепо его нет (`git ls-tree -r v0.1.0 -- packages/tenancy` пусто).

## [Unreleased]

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
