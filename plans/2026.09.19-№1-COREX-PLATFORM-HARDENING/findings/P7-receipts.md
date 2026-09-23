# P7 receipts

## P7.11 — блокирующий гейт `config()`

`scripts/composition-boundary.json` перенесён из §13: `blocking: true`,
globs composition, `allow_paths` только для
`packages/crm/src/Support/RecordRules.php` (`E-FLEX-CRM-SEARCH-LANGUAGE`).
`Support/Config.php` в allowlist нет. Сканер прежний: `token_get_all`,
`T_STRING config` и `(`. `**` в glob — ноль или больше сегментов, поэтому
`*/src/**/Commands/*.php` покрывает `src/Commands`. Класс §12
`CoreX\Support\Console\ValidateConfigCommand` сопоставляется с glob
`*/src/Console/*.php` через путь `src/Support/Console`.

Чистое дерево, exit 0:

```
config-composition-gate: blocking=true
config-composition-gate: C1 count=0
config-composition-gate: OK
```

Посадка `packages/core/src/Support/ConfigCompositionProbe.php`, exit 1
(файл снят в `finally`):

```
C1 packages/core/src/Support/ConfigCompositionProbe.php:11 config()
config-composition-gate: blocking=true
config-composition-gate: C1 count=1
```

`vendor/bin/pest --testsuite=Arch --filter=ConfigComposition|NamingAndConfig`:
6 passed, 19 assertions. `composer test:arch` целиком: 46 passed, 3 failed.
Провалы не из файлов P7.11: `CoreZoneTest` R-Z0 на
`ValidateConfigCommand.php`; `PackageGraphTest` — 16 R4/R4b на Options,
добавленных P7.1–P7.9.

Inputs-проза P7.1–P7.9 свёрнута на `findings/P7-receipts.md`, иначе bundle
не резолвится. Свидетель P3.6 в `NamingAndConfigGateTest` ждёт exit 1 на
той же посадке.

## P7.10 — `corex:config:validate` и redaction

`ValidateConfigCommand` регистрируется в `CoreServiceProvider`. Для связанных
Options (Core, Tenancy, Auth, Modules, Media — `class_exists` и `bound`) команда
вызывает `fromArray` и печатает все нарушения за один проход. Эффективные
драйверы и доступность адаптеров идут в stdout; ошибки — в stderr. Секреты
известных путей печатаются как `present`/`absent`. Дефолтный hex
`corex-intl.reference.cursor_secret` даёт `WARNING [secret-default]`;
`packages/intl/config/corex-intl.php` не менялся.

Негатив сажает `super-secret-value-test` в `SecretRef` (`tenancy.oidc.client_secret`)
и в строковый `corex-search.cursor_secret`. Строки нет в stdout, stderr и message
exception. Два нарушения `settings.cache_store` и `settings.cache_ttl` печатаются
вместе.

### Вывод команды

Дефолтная конфигурация тестов, exit 0:

```
driver pubsub.driver=null
driver tenancy.wakeup.driver=absent
driver tenancy.oidc.enabled=absent
adapter pubsub.redis=not-selected
adapter wakeup.root_registry=not-selected
secret tenancy.oidc.client_secret=absent
secret corex-intl.reference.cursor_secret=absent
secret corex-search.cursor_secret=absent
secret corex-search-meilisearch.key=absent
```

Тот же прогон с секретом и дефолтным intl hex (значения заменены на `present`), exit 0:

```
driver pubsub.driver=null
driver tenancy.wakeup.driver=absent
driver tenancy.oidc.enabled=absent
adapter pubsub.redis=not-selected
adapter wakeup.root_registry=not-selected
secret tenancy.oidc.client_secret=present
secret corex-intl.reference.cursor_secret=present
WARNING [secret-default] corex-intl.reference.cursor_secret
secret corex-search.cursor_secret=absent
secret corex-search-meilisearch.key=absent
```

### Validation

```
composer --working-dir=packages/core lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/core analyse
exit 0
{"tool":"phpstan","result":"passed","errors":0}
```

```
composer --working-dir=packages/core test
exit 0
{"tool":"pest","result":"passed","tests":97,"passed":97,"assertions":243}
```

```
bash scripts/anti-weakening-gate.sh
ANTI-WEAKENING GATE: OK
```

## P7.9 — MediaOptions, SqlMediaLibrary, ConversionCoordinator

`MediaOptions::fromArray` валидирует `corex-media`; `MediaServiceProvider` делает один
`get('corex-media')` и `instance(MediaOptions::class)`. `SqlMediaLibrary` и
`ConversionCoordinator` читают снимок Options. `rg -n 'config\('` по
`packages/media/src` пуст (composition — `$app['config']->get` только в провайдере).

`packages/media/tests/Unit/MediaOptionsTest.php` — `max_upload_bytes <= 0`, пустой
`allowed_mime_types`.

### Validation

```
composer --working-dir=packages/media lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/media analyse
exit 0
{"tool":"phpstan","result":"passed","errors":0}
```

```
composer --working-dir=packages/media test
exit 0
{"tool":"pest","result":"passed","tests":19,"passed":19,"assertions":54}
```

```
composer --working-dir=packages/media test:pg
exit 0
{"tool":"pest","result":"passed","tests":1,"passed":1,"assertions":1}
```

## P7.8 — ModulesOptions, провайдер, registry cache

`ModulesOptions::fromArray` валидирует `corex-modules` (ttl > 0, prefix непустой,
`connection` nullable). `ModulesServiceProvider` — один `get('corex-modules')` и
`instance(ModulesOptions::class)`; hot-path connection/cache через Options.
`RepositoryModuleRegistryCache` получает `ModulesOptions`, без `config()`.

`packages/modules/tests/Unit/ModulesOptionsTest.php` — ttl ≤ 0.

### Validation

```
composer --working-dir=packages/modules lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/modules analyse
exit 0
{"tool":"phpstan","result":"passed","errors":0}
```

```
composer --working-dir=packages/modules test
exit 0
{"tool":"pest","result":"passed","tests":28,"passed":28,"assertions":109}
```

```
rg 'config\(' packages/modules/src/Registry/RepositoryModuleRegistryCache.php
exit 1
(no matches)
```

## P7.7 — access/matrix на AuthOptions

Семь потребителей `corex-auth.*` в access/matrix читают `AuthOptions`;
`rg -n 'config\('` по этим файлам пуст. В `packages/auth/src` вызовов
`config(` нет (только docblock в провайдере).

### Validation

```
composer --working-dir=packages/auth lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/auth analyse
exit 0
{"tool":"phpstan","result":"passed","errors":0}
```

```
composer --working-dir=packages/auth test
exit 0
{"tool":"pest","result":"passed","tests":38,"passed":38,"assertions":65}
```

```
composer --working-dir=packages/auth test:pg
exit 0
{"tool":"pest","result":"passed","tests":112,"passed":112,"assertions":293}
```

## P7.6 — AuthOptions, провайдер, identity

`AuthOptions::fromArray` валидирует `corex-auth`; `CoreAuthServiceProvider`
делает один `get('corex-auth')` и `instance(AuthOptions::class)`;
`PrincipalUserProvider` и проекция `az-guard.*` берут снимок Options.

`packages/auth/tests/Unit/AuthOptionsTest.php` — пустой panel, неполные
`identity.columns`, нормализация `workspace_scope_class`.

### Validation

Те же команды, что в блоке P7.7 (пакетный прогон покрывает P7.6+P7.7).

## P7.5 — workspace/OIDC; остаток `config(` tenancy

`ResolveWorkspace`, `OidcLoginController` и `LogoutController` больше не
вызывают `config(`. После P7.3–P7.5 в `packages/tenancy/src` остаются
только composition-чтения:

```
$ rg -n 'config\(' packages/tenancy/src
packages/tenancy/src/Commands/PendingCreateCommand.php:25:        $centralConnection = (string) config('tenancy.central_connection');
packages/tenancy/src/Commands/IdentitiesReconcileCommand.php:25:        $centralConnection = (string) config('tenancy.central_connection');
packages/tenancy/src/TenancyServiceProvider.php:483:        config([
packages/tenancy/src/TenancyServiceProvider.php:553:            config([
packages/tenancy/src/TenancyServiceProvider.php:562:                    ...config('tenancy.identification.middleware', []),
```

Команды — CFG-002 (composition). Провайдер пишет проекцию stancl и
мержит чужой `tenancy.identification.middleware`.

### Validation

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
{"tool":"phpstan","result":"passed","errors":0}
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":476,"passed":476,"assertions":1513}
```

```
./vendor/bin/pest --group=pg (packages/tenancy)
exit 0
{"tool":"pest","result":"passed","tests":228,"passed":228,"assertions":865}
```

## P7.2 — TenancyOptions, три policy, провайдер


`packages/tenancy/tests/Unit/TenancyOptionsTest.php`

- valid snapshot resolves `centralConnection`, `wakeupDriver`, `status_gate` methods
- `oidc.enabled` without `client_secret` → `oidc.client_secret` violation
- `wakeup.driver=root_registry` with empty `root_connection` → violation
- both cross-field failures accumulate in one `InvalidConfiguration`

`TenancyServiceProvider` reads `tenancy` / `corex-tenancy` only at the
composition boundary; service closures resolve `TenancyOptions` after
Testbench `defineEnvironment()` so OIDC test fixtures stay valid.

### Validation

```
composer --working-dir=packages/tenancy lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/tenancy analyse
exit 0
{"tool":"phpstan","result":"passed","errors":0}
```

```
composer --working-dir=packages/tenancy test
exit 0
{"tool":"pest","result":"passed","tests":476,"passed":476,"assertions":1513}
```

```
../../vendor/bin/pest --group=pg (packages/tenancy)
exit 0
```

## P7.12 — D100 behavior proof

Witnesses consume `CoreOptions::fromArray()` and one `SqlSavedViewRepository`
instance. Product code under `packages/core/src/` was not written by this item.

### TTL and SecretRef

`packages/core/tests/Unit/CoreOptionsTest.php`

- missing `settings` and missing `settings.cache_ttl` resolve to `3600`
- explicit `null` stays `null`
- `0` stays `0`
- `120` stays `120`
- `cache_store: ''` and `cache_ttl: -1` accumulate in one `InvalidConfiguration`
  with both violation strings
- `SecretRef` interpolation, `(string)`, and `var_dump` show `[redacted]`;
  `json_encode` does not contain the plaintext; `serialize` throws `LogicException`

### Dynamic default connection

`packages/core/tests/Views/SqlSavedViewRepositoryTest.php`

- one repository, injected recording `DatabaseManager`, no container rebind
  between `viewsFor` calls
- successive defaults `central` → `tenant` → `central` select those connection
  names
- explicit `views.connection = pinned` stays `pinned` on the same three defaults

### Validation

```
composer --working-dir=packages/core lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/core analyse
exit 0
{"tool":"phpstan","result":"passed","errors":0}
```

```
composer --working-dir=packages/core test
exit 0
{"tool":"pest","result":"passed","tests":93,"passed":93,"assertions":223,"duration_ms":964}
```

```
composer --working-dir=packages/core test:pg
exit 0
{"tool":"pest","result":"passed","tests":37,"passed":37,"assertions":180,"duration_ms":7652}
```

Postgres preflight before `test:pg`: `pgsql` healthy, `pg_isready` on
`127.0.0.1:5434` accepted connections, `corex_test` exists.

```
git diff --stat -- packages/core/src/
 packages/core/src/Support/Data/DataScope.php       | 44 +++++++++++++++++++++-
 .../src/Tenancy/Contracts/ImpersonationService.php |  6 +--
 2 files changed, 44 insertions(+), 6 deletions(-)
```

That stat is the foreign baseline already present before P7.12. This item's
writes are `packages/core/tests/Unit/CoreOptionsTest.php`,
`packages/core/tests/Views/SqlSavedViewRepositoryTest.php`, and this receipt.
`CoreOptions.php` and `SqlSavedViewRepository.php` are absent from the diff.
