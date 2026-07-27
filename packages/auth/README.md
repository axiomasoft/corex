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
- **`Contracts\ScopeResolver`** (`Access\DefaultScopeResolver`) — Builder-аналог `canOnRecord()`:
  накладывает WHERE по эффективному scope на листинг, а не на одну запись. Семантика 6 скоупов
  (`none`/`own`/`dept`/`dept_tree`/`workspace`/`account`) живёт в ОДНОМ месте — `Access\ScopePredicate`
  (`applyTo()` для Builder, `matches()` для записи) — и используется обоими путями, так что
  листинг и детальная проверка одной и той же записи не могут разойтись. `workspace` несёт явный
  предикат с модификатором `cascade_view` (иерархический просмотр родитель→потомки по
  `wsp_workspaces.path`, флаг в `settings` читается строго `=== 'true'` у контекстного workspace).
- **`Access\ScopeAvailability`** — сущность без объявленной capability (`ownable`/`workspace_scoped`),
  но с физической колонкой (`owner_id`/`workspace_id`) на таблице, не превращает scope в `account`
  молча: это declaration drift, `Exceptions\ScopeCapabilityMismatch` (fail-closed, чинится
  декларацией capability на `EntityDefinition`, не кодом). Нет ни capability, ни колонки — сужать
  нечем, тогда `account` легален.
- **`Access\WorkspaceScopeWiring`** — регистрирует `WorkspaceScope` (глобальный scope, приходит из
  `corex/tenancy` по конфиг-пину `corex-auth.workspace_scope_class`) на моделях с capability
  `workspace_scoped` автоматически при `boot()`; boxed-профиль без пина — тихий пропуск.
- **Известное ограничение профиля:** в дереве корневого проекта пока 0 сущностей с capability
  `ownable`/`workspace_scoped` — их раздача реальным продуктовым сущностям (CRM/Tasks) вне
  этого пакета.

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
