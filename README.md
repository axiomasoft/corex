# CoreX

Модульное ядро на Laravel (MIT), на котором собираются продукты Flex-экосистемы (FlexCRM, FlexCMS,
FlexStore) без переписывания фундамента. Монорепо: разработка здесь, публикация — read-only зеркалами
по пакету (см. [[ADR-002 Monorepo]]).

## Пакеты

| Пакет | Назначение | Статус |
|---|---|---|
| `corex/core` | Ядро: Contracts, BaseModel (ULID), EntityRegistry, мульти-тенантность, Settings, Audit, Money, Module Loader | в разработке (0.x) |

Остальные модули (`corex/access`, `corex/crm`, `corex/tasks`, …) добавляются по принципу product-first.

## Стек

- PHP 8.4–8.5, Laravel 12–13 (см. [[ADR-001 Stack]]).
- ULID-идентификаторы ([[ADR-003 ULID]]); морфы — через `EntityRegistry`.
- Деньги — `brick/money`, minor units ([[ADR-005 Money]]).
- Мульти-тенантность — опциональная, row-level, включается биндингом `TenantService` ([[ADR-004 Multi-Tenancy]]).
- Auth — поверх AzGuard ([[ADR-009 Auth via AzGuard]]), в пакете `corex/access`.
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
