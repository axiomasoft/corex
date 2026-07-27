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
- **`aut_*`-схема + `RoleMatrixCompiler`** — UI-матрица роль×entity×action×scope, компилируемая в
  AzGuard-ключи `{panel}.{entity_key}.{action}` (D100) одной транзакцией (P2.8).
- **`Contracts\AccessChecker`** (`Access\DefaultAccessChecker`) — двухфазная проверка (B-11 §5.5):
  `allowed()` спрашивает НАСТОЯЩИЙ AzGuard через `Access\PrincipalIdentity` (капабилити-кеш, ≤1мс);
  `scopeFor()`/`canOnRecord()` компилируют эффективный scope из `aut_role_matrix` напрямую.
  `Access\AutMatrixGrantSource` (priority 85) + `Access\WorkspacePermissionLayer` (дискриминатор
  кеша по workspace, конфиг-ключ `corex-auth.bind_permission_layer`) — мост к капабилити-слою
  AzGuard (P2.20, D101).
- **Известное ограничение профиля:** `canOnRecord()` для scope `dept`/`dept_tree` читает
  `users.department_id`/`sys_departments.path` — таблиц другого пакета (`corex/tenancy`,
  `corex/core`'s department feature), которых в boxed-профиле может не быть. Отсутствующая таблица
  — fail-closed `false` (никогда не трактуется как «весь скоуп»), не исключение и не access grant.

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
