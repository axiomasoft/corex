# P7 receipts

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
