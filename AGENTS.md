# CoreX — контекст для ИИ-ассистентов

Монорепо ядра CoreX (Laravel, MIT). Здесь разрабатываются пакеты `corex/*`, поверх которых собираются
продукты Flex (FlexCRM и др.). Публикация — split-зеркалами по пакету.

## Конвенции кода

- `declare(strict_types=1);` в каждом PHP-файле; все классы `final`, кроме абстрактных точек расширения.
- Constructor property promotion, явные типы и возвраты, `#[Override]` где уместно.
- Именованные аргументы при нескольких однотипных параметрах.
- Контракты — только интерфейсы в `src/Contracts/`; реализации скрыты. Контракты не меняем без major ([[ADR-008]]).
- Расширение — только через события/Observer и `EntityRegistry`; monkey-patching запрещён.
- Pint preset `laravel` (`composer lint`), PHPStan (`composer analyse`), Pest (`composer test`).

## Архитектура ядра (`packages/core`)

- `BaseModel` (ULID + SoftDeletes) + трейты `Concerns/*` (для моделей, что не могут наследовать BaseModel, напр. `User`).
- Мульти-тенантность — **физическая DB-per-account** (stancl/tenancy v4; ADR-004 переписан D1):
  контракты (`TenantContext`, `TenancyManager`, `AccountConnectionResolver`) живут в `corex/core`,
  реализация — пакет `corex/tenancy` (boxed-профиль ставится без него). Legacy row-level каркас
  удалён (P2.14) — в дереве его нет, новые фичи на нём не строятся.
- `EntityRegistry` — реестр сущностей; источник морф-карты (`Relation::enforceMorphMap`).
- `Settings` (System→Tenant→User), `Audit` (append-only), `MoneyCast` (`brick/money`, minor units).
- ID/морфы/деньги — по ADR-003/004/005; стек — ADR-001 (Laravel 12–13, PHP 8.3–8.5).

## Где что

- Библиотечный код пакета: `packages/<name>/src`, `config/`, `database/migrations/`.
- Тесты — **per-package** (канон D81): новые тесты пакета кладутся в `packages/<pkg>/tests/**`.
  Корневой `tests/` — харнесс core/modules (Arch/Feature/Unit/PHPStan) + инструменты качества
  (`phpunit.xml`, `pint.json`, `phpstan.neon`).
- Спека и ADR — в Brain-vault (CoreX), не в репозитории кода.

## Доктрина разработки

- Слои: `corex/*` = агностик-платформа (L0) + домен-основы (L1); `flex/*` = тематические модули
  (L2); продукты = композиция (L3); cross-ecosystem — standalone (D70).
- Тест агностичности: «`corex/*` полезен проекту БЕЗ CRM/CMS/Store»; зависимость `corex/*` →
  `flex/*` запрещена (гейт — `tests/Arch/LayerTest.php`).
- Полные правила, гейты и их честное состояние — **`docs/dev-doctrine.md`** (входной документ).
