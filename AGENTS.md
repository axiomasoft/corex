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
- Мульти-тенантность опциональна: `BelongsToTenant` + `TenantScope` активны только при наличии биндинга `TenantService` (контракт). On-prem — без него.
- `EntityRegistry` — реестр сущностей; источник морф-карты (`Relation::enforceMorphMap`).
- `Settings` (System→Tenant→User), `Audit` (append-only), `MoneyCast` (`brick/money`, minor units).
- ID/морфы/деньги — по ADR-003/004/005; стек — ADR-001 (Laravel 12–13, PHP 8.3–8.5).

## Где что

- Библиотечный код пакета: `packages/<name>/src`, `config/`, `database/migrations/`.
- Тесты и инструменты качества: корень монорепо (`tests/`, `phpunit.xml`, `pint.json`, `phpstan.neon`).
- Спека и ADR — в Brain-vault (CoreX), не в репозитории кода.
