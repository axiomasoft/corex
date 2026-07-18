# corex/auth

Auth-мост между [`corex/core`] и [AzGuard] (ADR-009). AzGuard — движок прав; этот пакет добавляет
интеграционный слой экосистемы, **не дублируя** то, что уже есть в AzGuard.

## Что добавляет

- **`TenantContextGuard`** — реализация AzGuard-контракта `ContextGuard` (тот самый seam, который AzGuard
  ждёт от внешнего пакета). Делает рабочим `$user->hasPermissionIn($type, $id, $permission)`: контекстная
  сущность резолвится через `EntityRegistry` ядра (единый источник морфов, ADR-003), затем проверяется
  scoped- или глобальное право. Изоляция тенантов — физическая (DB-per-account, B-11): сущность,
  загруженная здесь, уже пришла из БД своего аккаунта.
- **Blade `@scope` / `@entity`** — entity-scoped директивы поверх scoped-ролей AzGuard.

## Вне объёма (позже)

- RoleGroup-иерархия (наследование ролей). У AzGuard есть только информационный `role.level`.

## Зависимости

- `corex/core`, `axioma-studio/azguard-core`. При локальной разработке подключены через composer
  path-repo (`../core`, `../../../azguard/packages/core`).

> **CI/дистрибуция:** `azguard-core` приватный, поэтому пакет пока тестируется **standalone локально**
> (`composer install && composer test`) и не подключён в корневой monorepo-CI — это включится после
> решения по дистрибуции AzGuard (приватный packagist/VCS + токен).

## Разработка

```bash
composer install
composer test       # Pest + Orchestra Testbench (sqlite :memory:)
composer analyse    # PHPStan (larastan)
composer lint       # Pint
```
