# CoreX

Модульное ядро на Laravel (MIT), на котором собираются продукты Flex-экосистемы (FlexCRM, FlexCMS,
FlexStore) без переписывания фундамента. Монорепо: разработка здесь, публикация — read-only зеркалами
по пакету (см. [[ADR-002 Monorepo]]).

## Пакеты

| Пакет | Назначение | Документация |
|---|---|---|
| `axiomasoft/corex-access` | CoreX auth bridge — tenant-boundary authorization, entity-context checks and scoped Blade directives on top of AzGuard (ADR-009). | [access](packages/access/README.md) |
| `axiomasoft/corex` | CoreX kernel — base model (ULID), optional multi-tenancy, entity registry, settings, audit log, and money for any Laravel application. | [core](packages/core/README.md) |
| `axiomasoft/corex-custom-fields` | Typed attribute ports and safe schema-operation bridge for CoreX. | [custom-fields](packages/custom-fields/README.md) |
| `axiomasoft/corex-intl` | Typed locale, money/rate snapshots, and offline geography reference utilities for CoreX. | [intl](packages/intl/README.md) |
| `axiomasoft/corex-media` | Tenant-scoped private media lifecycle primitives for CoreX. | [media](packages/media/README.md) |
| `axiomasoft/corex-notifications` | Durable tenant-scoped notification primitives for CoreX. | [notifications](packages/notifications/README.md) |
| `axiomasoft/corex-operations` | Opt-in module operation dispatch, trusted context contracts and OpenAPI projection for Laravel. | [operations](packages/operations/README.md) |
| `axiomasoft/corex-panels` | Headless panel descriptors and current-context access catalogue for CoreX modules. | [panels](packages/panels/README.md) |
| `axiomasoft/corex-recurrence` | Bounded RFC 5545 recurrence expansion for CoreX consumers. | [recurrence](packages/recurrence/README.md) |
| `axiomasoft/corex-search` | Typed, policy-hydrated search boundary for CoreX. | [search](packages/search/README.md) |
| `axiomasoft/corex-search-intl` | Optional localized projection glue between axiomasoft/corex-intl and axiomasoft/corex-search. | [search-intl](packages/search-intl/README.md) |
| `axiomasoft/corex-search-meilisearch` | Optional Meilisearch candidate adapter for axiomasoft/corex-search. | [search-meilisearch](packages/search-meilisearch/README.md) |
| `axiomasoft/corex-tasks` | CoreX Tasks module — workspace projects, stages and tasks for DB-per-account applications. | [tasks](packages/tasks/README.md) |
| `axiomasoft/corex-tasks-workflow` | Recurrence-to-task materialization for CoreX with durable wakeup and optional workflow capture. | [tasks-workflow](packages/tasks-workflow/README.md) |
| `axiomasoft/corex-tenancy` | CoreX physical DB-per-account tenancy — root-schema control plane on stancl/tenancy v4 (B-11, D13). | [tenancy](packages/tenancy/README.md) |
| `axiomasoft/corex-tools` | Guarded tool runtime for CoreX operations: schemas, contract hash and MCP projection. | [tools](packages/tools/README.md) |
| `axiomasoft/corex-transfer` | Bounded import and export primitives for CoreX. | [transfer](packages/transfer/README.md) |
| `axiomasoft/corex-wakeup` | Durable local wakeup requests for CoreX modules. | [wakeup](packages/wakeup/README.md) |
| `axiomasoft/corex-webhooks` | Webhook subscriptions, signing, delivery and replay for CoreX operations. | [webhooks](packages/webhooks/README.md) |
| `axiomasoft/corex-workflow` | Optional durable workflow engine binding for CoreX modules. | [workflow](packages/workflow/README.md) |
| `flex/crm` | Продукт FlexCRM поверх CoreX | [flex-crm](products/flex-crm/README.md) |

Установка ядра: `composer require axiomasoft/corex`. Конфигурация публикуется командой
`php artisan vendor:publish --tag=corex-config`; параметры модулей находятся в `config/corex.php`
в разделе `modules`. Для пакетов используются собственные config-ключи и publish-теги из их README.

## Стек

- PHP 8.4–8.5, Laravel 12–13 (см. [[ADR-001 Stack]]).
- ULID-идентификаторы ([[ADR-003 ULID]]); морфы — через `EntityRegistry`.
- Деньги — `brick/money`, minor units ([[ADR-005 Money]]).
- Мульти-тенантность — опциональная, row-level, включается биндингом `TenantService` ([[ADR-004 Multi-Tenancy]]).
- Auth — поверх AzGuard ([[ADR-009 Auth via AzGuard]]), в пакете `axiomasoft/corex-access`.
- Контракты под строгим SemVer — не ломаются без major ([[ADR-008 Versioning]]).

## Разработка

```bash
scripts/php84 --check
scripts/php84 python3 scripts/distribution/stage-stancl-providers.py
scripts/php84 composer install
scripts/php84 composer config --unset repositories.stancl-providers
scripts/php84 composer --working-dir=packages/tenancy config --unset repositories.stancl-providers
scripts/php84 composer --working-dir=packages/access config --unset repositories.stancl-providers
scripts/php84 python3 scripts/distribution/stage-stancl-providers.py --portable-locks
scripts/php84 composer test        # Pest + Orchestra Testbench (sqlite :memory:)
scripts/php84 composer analyse     # PHPStan (larastan)
scripts/php84 composer lint        # Pint
```

Обёртка `scripts/php84` выбирает PHP 8.4 для Composer и дочерних команд. Для локального PostgreSQL используйте `PGSQL_PORT=5432 scripts/php84 composer test:pg`; staging извлекает AzGuard из закреплённого upstream-коммита, потому что VCS-репозиторий содержит монорепо. Временный `repositories.stancl-providers` снимайте после каждого install/update, включая неуспешный. После update выполняйте `--portable-locks`, чтобы ZIP-пути в lock оставались относительными к checkout.

Пакеты — чистые библиотеки; тест-харнес и инструменты качества живут в корне монорепо.
