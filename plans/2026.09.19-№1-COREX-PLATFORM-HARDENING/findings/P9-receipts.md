# P9.7 receipt

3 файла карты P9.7 перенесены в `Integration/Wakeup` с namespace
context-first `CoreX\Tenancy\Integration\Wakeup`. `corex/wakeup` остаётся
`suggest` + dev-only. Optional predicate, tag `corex.wakeup.hints` и
сигнатура `corex:wakeup:root-sweep` не менялись. Registrar остаётся в
`Laravel/`. Adapter-классы помечены `@internal`.

Package-graph allowlist называет `Integration/Wakeup` adapters; негативная
посадка `tests/fixtures/package-graph/tenancy-wakeup-foreign-context.php`
из `Account/` даёт `[R1]`. Полный `package-graph-gate` остаётся красным
на 64 чужих R4/R4b после P9.2–P9.6 до P9.8. Пустой leftover `src/Wakeup`.
`packages/tenancy/database/**` без diff.
`scripts/tenancy-structure-gate.py check` остаётся красным до P9.8.

## Validation

```
composer --working-dir=packages/tenancy dump-autoload -o
exit 0
Generated optimized autoload files containing 10184 classes
```

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
phpstan 175/175, errors 0
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":498,"passed":491,"skipped":7,"assertions":1747}
```

```
composer test:pg
exit 0
{"tool":"pest","result":"passed","tests":181,"passed":181,"assertions":687}
```

```
vendor/bin/pest tests/Arch/PackageGraphTest.php --filter='wakeup import outside Integration/Wakeup'
exit 0
{"tool":"pest","result":"passed","tests":1,"passed":1,"assertions":5}
```

```
rg -n 'Wakeup\\\\Internal|Wakeup/Internal' packages/tenancy
exit 1 (no matches)
```

```
git diff --exit-code -- packages/tenancy/database
exit 0
```

# P9.6 receipt

12 файлов карты P9.6 перенесены. `Workspaces` стал `Workspace` с
namespace context-first `CoreX\Tenancy\Workspace`. Модели Domain,
Invitation, Workspace, WorkspaceMember, ImpersonationGrant и события
DomainTrustChanged, ImpersonationStarted, ImpersonationEnded сохраняют
FQCN через PSR-4 maps. Registrar'ы остаются в `Laravel/`; импорты
workspace обновлены.

Пустые `src/Workspaces` и `src/Models`. `src/Events` содержит только
пустой leftover `Identity/` от dual-map P9.3. Trusted host, grant clock
и listener patterns не менялись. `packages/tenancy/database/**` без diff.
`scripts/tenancy-structure-gate.py check` остаётся красным до P9.8.

## Validation

```
composer --working-dir=packages/tenancy dump-autoload -o
exit 0
Generated optimized autoload files containing 10184 classes
```

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
phpstan 175/175, errors 0
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":498,"passed":491,"skipped":7,"assertions":1747}
```

```
composer test:pg
exit 0
{"tool":"pest","result":"passed","tests":181,"passed":181,"assertions":687}
```

```
git diff --exit-code -- packages/tenancy/database
exit 0
```

# P9.5 receipt

10 файлов карты P9.5 перенесены. OIDC стал `Authentication/Oidc` с
namespace context-first `CoreX\Tenancy\Authentication\Oidc`. Контроллеры,
`OidcAuthenticationException`, `User`, `UserSynced` и
`SyncIdentityToAccount` сохраняют FQCN через PSR-4 maps.
`AuthenticationRegistrar` остаётся в `Laravel/`; импорты OIDC обновлены.
`routes/oidc.php` без изменения name/method/URI/action.

Пустые `src/Oidc`, `src/Http/Controllers`, `src/Exceptions`, `src/Jobs`.
`oidc.enabled=false` по-прежнему нулевой composition. Публичные контракты
не менялись. `packages/tenancy/database/**` без diff.
`scripts/tenancy-structure-gate.py check` остаётся красным до P9.8.

## Validation

```
composer --working-dir=packages/tenancy dump-autoload -o
exit 0
Generated optimized autoload files containing 10184 classes
```

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
phpstan 175/175, errors 0
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":498,"passed":491,"skipped":7,"assertions":1747}
```

```
composer test:pg
exit 0
{"tool":"pest","result":"passed","tests":181,"passed":181,"assertions":687}
```

```
git diff --exit-code -- packages/tenancy/database
exit 0
```

# P9.4 receipt

20 файлов карты P9.4 перенесены. Account models/events/lifecycle живут в
`Account/`. Provisioning принял action, database, jobs, migrations и
`MigrationBatch`. `TemplateClonePostgreSQLManager` сохраняет FQCN
`CoreX\Tenancy\Database`: Composer classmap, а не PSR-4 prefix `Database\`,
иначе PHPStan дважды грузит context-first siblings. События account,
`CreateDatabaseFromTemplate`, модели Account/AccountExport/AccountSchemaState
и MigrationBatch сохраняют FQCN через массивы каталогов PSR-4.

Пустые `src/Actions`, `src/Database`, `src/Lifecycle`, `src/Migrations`.
`Jobs/SyncIdentityToAccount` остаётся до P9.5. Публичные контракты и
registrar не менялись. `packages/tenancy/database/**` без diff.
`scripts/tenancy-structure-gate.py check` остаётся красным до P9.8.
Binding fingerprint не менялся: единственная разрешённая дельта — D49.

## Validation

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
phpstan 175/175, errors 0
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":498,"passed":491,"skipped":7,"assertions":1747}
```

```
composer test:pg
exit 0
{"tool":"pest","result":"passed","tests":181,"passed":181,"assertions":687}
```

```
git diff --exit-code -- packages/tenancy/database
exit 0
```

# P9.3 receipt

55 файлов P9.3 перенесены. `Membership/Internal` снят: десять подсистем
`Credentials`, `Expiration`, `Identity`, `Inbox`, `Persistence`,
`Projection`, `Refresh`, `Switcher`, `Timing`, `Transport`. Каталог
`Expiry` стал путём `Expiration`; имя класса `ExpiryScheduler` не менялось.
12 классов `Central` слиты в `Membership/Transport` вместе с прежним
transport (22 файла, без коллизии имён и без дубля protocol DTO).
`ApplyMembershipProjection` — `CoreX\Tenancy\Membership\Projection`.
`MembershipGranted`, `MembershipRevoked` и `RefreshAccountMembership`
сохраняют FQCN: PSR-4 `Events\Identity` и `Jobs` указывают на массив
каталогов. Публичные контракты и registrar не менялись.

Пустые `src/Membership/Internal` и `src/Central`. Namespace
`Membership\Internal` и `Tenancy\Central` в `packages/tenancy/src` отсутствуют.
`scripts/tenancy-structure-gate.py check` по-прежнему сверяет pre-move
baseline P9.1 и остаётся красным до P9.8. Binding fingerprint не менялся:
единственная разрешённая дельта по-прежнему D49.

## Validation

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
phpstan 175/175, errors 0
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":498,"passed":491,"skipped":7,"assertions":1747}
```

```
composer test:pg
exit 0
{"tool":"pest","result":"passed","tests":181,"passed":181,"assertions":687}
```

# P9.2 receipt

25 файлов P9.2 перенесены: Laravel seams (`Bootstrappers`, `Commands`,
`Http/Middleware`, `Listeners`, `Resolvers`, `Scopes`, `Support`) и Runtime
(`StanclTenancyManager`, `TenancyManagerContextResolver`,
`Exceptions/TenantCouldNotBeIdentifiedByHostException`, `Storage/**`).
Стабильные FQCN сохранены точечными PSR-4 maps в `packages/tenancy/composer.json`.
`Storage` — context-first `CoreX\Tenancy\Runtime\Storage`. Provider и
P8 registrar'ы не двигались. `RuntimeRegistrar` не перенесён в `Runtime`.

D49: scoped `TenantContextRestorer` →
`CoreX\Tenancy\Runtime\ConnectionGuardedTenantContextRestorer`.
Сначала semantic-сверка `GuardedTenantContextRestorer`, затем identity
активного соединения (`driver:database` или `boxed:<accountId>`).
Неизвестный account не читает соединение и не делает lookup.
`double_init_revert.measured=true`. Один цикл central→tenant→central
отдаёт long-lived consumer текущие default `pgsql`, `tenant`, `pgsql`.

D48 relay не построен.

`scripts/tenancy-structure-gate.py check` сверяет живое дерево с
pre-move baseline P9.1, поэтому после переноса он красный до P9.8.

## Validation

```
composer --working-dir=packages/tenancy lint:check
exit 0
```

```
composer --working-dir=packages/tenancy analyse
exit 0
phpstan errors 0
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":498,"passed":491,"skipped":7,"assertions":1747}
```

# P9.1 receipt

Post-P8 дерево `packages/tenancy/src`: 174 PHP-файла, coverage 100%,
duplicates 0, unique from/to. Карта: `findings/P9-tenancy-map.md`.
Fingerprint: `findings/P9-compatibility-baseline.json`
(`p9-compatibility-baseline/v1`).

Распределение по item: stationary 49, P9.2 25, P9.3 55, P9.4 20,
P9.5 10, P9.6 12, P9.7 3. Единственная разрешённая behavioral
binding-дельта — D49: scoped `CoreX\Tenancy\Contracts\TenantContextRestorer`
→ `CoreX\Tenancy\GuardedTenantContextRestorer`,
`only_permitted_behavior_delta=true`. Listeners 7, bootstrapper FQCN
разрешены через use-alias. `double_init_revert.measured=false`, owner P9.2.

D48: multi-account relay owner `Tenancy/Runtime`. Scheduler, lease,
DDL и concurrency policy не построены. Риск голодания D27 остаётся
открытым до измеренного триггера (starvation, SLO breach, manual fan-out).

Живое дерево ещё не 13 context-каталогов: `check` сверяет committed
baseline, а не требует текущий layout. Негативные fixtures красные
по своей причине.

## Validation

```
python3 scripts/tenancy-structure-gate.py check
exit 0
baseline accepted files=174 duplicates=0
```

```
python3 scripts/tenancy-structure-gate.py fixture fourteenth-root
exit 1
fixture fourteenth-root rejected: fourteenth-root
```

```
python3 scripts/tenancy-structure-gate.py fixture membership-internal
exit 1
fixture membership-internal rejected: membership-internal
```

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
phpstan 174/174, errors 0
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":495,"passed":488,"skipped":7,"assertions":1724}
```

```
composer test:pg
exit 0
{"tool":"pest","result":"passed","tests":181,"passed":181,"assertions":687}
```

`composer test:pg` на грязном дереве сначала падал в
`MigrationPublishTest` (12 sys_* миграций и timestamptz без precision 6
у незакоммиченных outbox/inbox). Это чужой messaging slice, не P9.1.
Чтобы заявленный carrier стал зелёным, у этих миграций выставлен
`precision: 6`, а ожидание publish-теста стало 12. Эти правки не входят
в commit P9.1.
