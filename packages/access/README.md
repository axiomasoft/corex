# axiomasoft/corex-access

Auth-мост между [`axiomasoft/corex`] и [AzGuard] (ADR-009). AzGuard — движок прав; этот пакет добавляет
интеграционный слой экосистемы, **не дублируя** то, что уже есть в AzGuard.

## Граница ответственности

`axiomasoft/corex-access` — необязательный адаптер к независимому движку ролей, а не его владелец и не
обязательная часть `axiomasoft/corex`/`axiomasoft/corex`. Приложение-потребитель самостоятельно решает,
подключать ли адаптер, и владеет собственной композицией, настройкой и политиками. Наличие
адаптера не передаёт ему владение API, назначением ролей, проверкой полномочий вызывающего,
обходами или аудитом независимого движка.

Описанные ниже `aut_*`-модели, матрица и компилятор — фактические поверхности этого пакета; они
не доказывают отсутствия дублирования с внешним движком. Возможное пересечение следует
рассматривать отдельным совместимостным анализом, а не удалять или переклассифицировать в рамках
документации.

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
  кеша по workspace, конфиг-ключ `corex-access.bind_permission_layer`) — мост к капабилити-слою
  AzGuard (P2.20, D101).
- **Известное ограничение профиля:** `canOnRecord()` для scope `dept`/`dept_tree` читает
  `users.department_id`/`sys_departments.path` — таблиц другого пакета (`axiomasoft/corex-tenancy`,
  `axiomasoft/corex`'s department feature), которых в boxed-профиле может не быть. Отсутствующая таблица
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
  `axiomasoft/corex-tenancy` по конфиг-пину `corex-access.workspace_scope_class`) на моделях с capability
  `workspace_scoped` автоматически при `boot()`; boxed-профиль без пина — тихий пропуск.
- **Известное ограничение профиля:** в дереве корневого проекта пока 0 сущностей с capability
  `ownable`/`workspace_scoped` — их раздача реальным продуктовым сущностям (CRM/Tasks) вне
  этого пакета.

- **`Identity\PrincipalUserProvider` — локальный лейн входа (D125)**: собственный
  `Illuminate\Contracts\Auth\UserProvider`, читающий пользовательскую таблицу через
  query-builder ЦЕЛИКОМ по конфигу `corex-access.identity` (`connection`/`table`/карта колонок
  `id`/`email`/`password`/`active`/`deleted_at`) и отдающий `Access\PrincipalIdentity(user,
  <id>)`. Пакет не импортирует ни одну модель `axiomasoft/corex-tenancy` — имя таблицы приходит строкой,
  тот же приём, что и `workspace_scope_class`. Это ЕДИНСТВЕННЫЙ шов, которым конструируется
  принципал-пользователь: облачный OIDC-лейн (`axiomasoft/corex-tenancy`, под `corex-tenancy.oidc.enabled`)
  заканчивается штатным `Auth::loginUsingId($sub)`, который резолвится через ЭТОТ ЖЕ
  провайдер — так оба входа (локальный `Auth::attempt()` и облачный OIDC) собирают ровно один
  класс `Principal` (premortem C4). Чтобы включить лейн, приложение указывает драйвер в
  СВОЁМ `config/auth.php`:

  ```php
  'providers' => [
      'users' => [
          'driver' => 'corex-principal',
      ],
  ],
  ```

  Пакет никогда не пишет в `config('auth.*')` сам — драйвер регистрируется
  (`Auth::provider('corex-principal', …)`), проводка на гвард — дело приложения.
  `retrieveByToken()`/`updateRememberToken()` — постоянный no-op: колонки `remember_token` в
  замороженной схеме `users` нет (P2.7 закрыт), добавлять её запрещено.
  При необходимом rehash провайдер вызывает `CoreX\Context\Contracts\IdentityPasswordRehasher`,
  передавая старый и новый хэш; он сам не пишет в чужую таблицу. `axiomasoft/corex-tenancy`
  связывает этот порт со своим writer для `users.password_hash` и выполняет
  compare-and-swap только для активной записи. Если приложение использует другую
  таблицу или колонку, её владелец должен предоставить свою реализацию порта;
  неподдерживаемое отображение отклоняется до записи.

## Вне объёма (позже)

- RoleGroup-иерархия (наследование ролей). У AzGuard есть только информационный `role.level`.

## Зависимости

`axioma-studio/azguard-core` ^0.3 — публичный пакет из монорепозитория
`https://github.com/axiomasoft/azguard-private` (снимок `main` `253cbf4`, каталог `packages/core`).
Composer ставит его через `type: package` в `repositories`, без токена и без локального
checkout. Репозитории Composer не транзитивны: потребитель `axiomasoft/corex-access` добавляет
тот же публичный источник у себя. Path-репозитории sibling-пакетов ядра остаются
только в monorepo-манифесте.

## Разработка

```bash
composer install
composer test       # Pest + Orchestra Testbench (sqlite :memory:)
composer analyse    # PHPStan (larastan)
composer lint       # Pint
```

## Installation

Configure the approved Composer channels for CoreX and AzGuard, then run `composer require axiomasoft/corex-access`. Composer repositories are application-owned and are not inherited from dependencies. Select the `corex-principal` user-provider driver in your own auth configuration and install the package-owned schema on the intended tenant connection before using the role matrix.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.
