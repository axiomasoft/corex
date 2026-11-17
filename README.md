# CoreX

Модульное ядро на Laravel (MIT), на котором собираются продукты Flex-экосистемы (FlexCRM, FlexCMS,
FlexStore) без переписывания фундамента. Монорепо: разработка здесь, публикация — read-only зеркалами
по пакету (см. [[ADR-002 Monorepo]]).

## Пакеты

Назначения, границы и правила именования: [карта пакетов](docs/architecture/package-map.md).

| Пакет | Назначение | Документация |
|---|---|---|
| `axiomasoft/corex-access-azguard` | CoreX context adaptation for AzGuard authorization and query visibility. | [access-azguard](packages/access-azguard/README.md) |
| `axiomasoft/corex-access` | Native tenant authorization, role matrices, direct grants and record scopes. | [access](packages/access/README.md) |
| `axiomasoft/corex` | CoreX application foundation — tenant context, settings, audit and messaging on Packman modules and schema. | [core](packages/core/README.md) |
| `axiomasoft/corex-custom-fields` | Typed attribute ports and safe schema-operation bridge for CoreX. | [custom-fields](packages/custom-fields/README.md) |
| `axiomasoft/corex-intl` | Typed locale, money/rate snapshots, and offline geography reference utilities for CoreX. | [intl](packages/intl/README.md) |
| `axiomasoft/corex-media` | Tenant-scoped private media lifecycle primitives for CoreX. | [media](packages/media/README.md) |
| `axiomasoft/corex-notifications` | Durable tenant-scoped notification primitives for CoreX. | [notifications](packages/notifications/README.md) |
| `axiomasoft/corex-operations` | Opt-in module operation dispatch, trusted context contracts and OpenAPI projection for Laravel. | [operations](packages/operations/README.md) |
| `axiomasoft/corex-panels` | Trusted CoreX context integration for Packman panels and saved views. | [panels](packages/panels/README.md) |
| `axiomasoft/corex-recurrence` | Bounded RFC 5545 recurrence expansion for CoreX consumers. | [recurrence](packages/recurrence/README.md) |
| `axiomasoft/corex-search` | Typed, policy-hydrated search boundary for CoreX. | [search](packages/search/README.md) |
| `axiomasoft/corex-search-intl` | Optional localized projection glue between axiomasoft/corex-intl and axiomasoft/corex-search. | [search-intl](packages/search-intl/README.md) |
| `axiomasoft/corex-search-meilisearch` | Optional Meilisearch candidate adapter for axiomasoft/corex-search. | [search-meilisearch](packages/search-meilisearch/README.md) |
| `axiomasoft/corex-tasks` | CoreX Tasks module — workspace projects, stages and tasks for DB-per-account applications. | [tasks](packages/tasks/README.md) |
| `axiomasoft/corex-recurring-tasks` | Recurrence-to-task materialization for CoreX with durable wakeup and optional workflow capture. | [recurring-tasks](packages/recurring-tasks/README.md) |
| `axiomasoft/corex-tenancy` | CoreX account and workspace lifecycle with external Tenant runtime and host-owned physical resources. | [tenancy](packages/tenancy/README.md) |
| `axiomasoft/corex-tools` | Guarded tool runtime for CoreX operations: schemas, contract hash and MCP projection. | [tools](packages/tools/README.md) |
| `axiomasoft/corex-import-export` | Bounded import and export primitives for CoreX. | [import-export](packages/import-export/README.md) |
| `axiomasoft/corex-scheduler` | Durable, tenant-local scheduling of one-shot work. | [scheduler](packages/scheduler/README.md) |
| `axiomasoft/corex-webhooks` | Webhook subscriptions, signing, delivery and replay for CoreX operations. | [webhooks](packages/webhooks/README.md) |
| `axiomasoft/corex-workflow` | Optional durable workflow engine binding for CoreX modules. | [workflow](packages/workflow/README.md) |
| `flex/crm` | Продукт FlexCRM поверх CoreX | [flex-crm](products/flex-crm/README.md) |

Перед установкой настройте [канал зависимостей экосистемы](docs/architecture/ecosystem-dependencies.md).
Текущий development-снимок устанавливается как `composer require 'axiomasoft/corex:^1.0@dev'`. Конфигурация публикуется командой
`php artisan vendor:publish --tag=corex-config`; параметры модульного runtime Packman находятся в `config/modules.php`. Для пакетов используются собственные config-ключи и publish-теги из их README.

Схема пакетов устанавливается из зарегистрированных наборов:

```bash
php artisan packman:schema:migrate --scope=tenant
php artisan packman:schema:status --scope=tenant
```

В production для миграции нужен `--force`. Для tenancy центральная база устанавливается
через `packman:schema:migrate --scope=central --database=<central>`, отдельное соединение
бюджетов — через `--scope=dedicated`; модули создают свою схему при активации через
`ModuleLifecycle`. Наборы, драйверы и правила формы — в
[политике схемы](docs/architecture/schema-policy.md).

## Стек

- PHP 8.4–8.5, Laravel 13 (см. [[ADR-001 Stack]]).
- ULID-идентификаторы ([[ADR-003 ULID]]); морфы — через `EntityRegistry`.
- Деньги — `brick/money`, minor units ([[ADR-005 Money]]).
- Мульти-тенантность — опциональная; `axiomasoft/corex-tenancy` адаптирует account/workspace к Tenant. Физическими ресурсами владеет host-selected Tenant PostgreSQL adapter.
- Авторизация — собственная матрица ролей `axiomasoft/corex-access`; интеграции подключаются отдельно.
- Контракты под строгим SemVer — не ломаются без major ([[ADR-008 Versioning]]).

## Разработка

Точные внешние refs, требования доступа и локальный Vaulter patch описаны в
[инструкции установки](docs/architecture/ecosystem-dependencies.md); свежему CI нужны доступные
источники всех этих commits.

```bash
scripts/php84 --check
scripts/php84 composer install
scripts/php84 composer test        # Pest + Orchestra Testbench (sqlite :memory:)
scripts/php84 composer analyse     # PHPStan (larastan)
scripts/php84 composer lint        # Pint
```

Обёртка `scripts/php84` выбирает PHP 8.4 для Composer и дочерних команд. Для локального PostgreSQL используйте `PGSQL_PORT=5434 scripts/php84 composer test:pg`.

Пакеты — чистые библиотеки; тест-харнес и инструменты качества живут в корне монорепо.

Локальное окружение AI-агентов устанавливается через swissknifeman с профилем
`php-package` и каналом `connect`. Настройки провайдеров, хуки, роли, планы и
временные отчёты исключены из Git; сторонние skills в проект не копируются.
Контрактные снимки, тестовые фикстуры и инструменты качества остаются частью исходников.
