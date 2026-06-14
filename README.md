# CoreX

Модульное ядро на Laravel (MIT), на котором собираются продукты Flex-экосистемы (FlexCRM, FlexCMS,
FlexStore) без переписывания фундамента. Монорепо: разработка здесь, публикация — read-only зеркалами
по пакету (см. [[ADR-002 Monorepo]]).

## Пакеты

| Пакет | Назначение | Статус |
|---|---|---|
| `corex/core` | Ядро: Contracts, BaseModel (ULID), EntityRegistry, мульти-тенантность, Settings, Audit, Money, Module Loader | в разработке (0.x) |

Остальные модули (`corex/auth`, `corex/crm`, `corex/tasks`, …) добавляются по принципу product-first.

## Стек

- PHP 8.3–8.5, Laravel 12–13 (см. [[ADR-001 Stack]]).
- ULID-идентификаторы ([[ADR-003 ULID]]); морфы — через `EntityRegistry`.
- Деньги — `brick/money`, minor units ([[ADR-005 Money]]).
- Мульти-тенантность — опциональная, row-level, включается биндингом `TenantService` ([[ADR-004 Multi-Tenancy]]).
- Auth — поверх AzGuard ([[ADR-009 Auth via AzGuard]]), в пакете `corex/auth`.
- Контракты под строгим SemVer — не ломаются без major ([[ADR-008 Versioning]]).

## Разработка

```bash
composer install
composer test        # Pest + Orchestra Testbench (sqlite :memory:)
composer analyse     # PHPStan (larastan)
composer lint        # Pint
```

Пакеты — чистые библиотеки; тест-харнес и инструменты качества живут в корне монорепо.
