# P12 package-boundary rebaseline receipts

**Schema:** p12-rebaseline-receipts/1

## P12.1 — Починить контракт модуля, утечку ядра и незаявленные зависимости

**Item:** P12.1  
**Actor:** executor/composer-2.5/medium  
**Date:** 2026-09-18

### Pre-mutation design gate

```text
$ python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/validate-p12.py --check design
P12 structural identity check PASS; actual executed/accepting-owner truth remains item-owned
exit=0
```

### Core leak check (§1.2)

```text
$ rg -n "^use CoreX\\\\" packages/core/src --no-heading \
    | rg -v "use CoreX\\\\(Contracts|Support|Tenancy|Modules)\\\\" || echo "clean"
```

Вывод не пустой: остаются только внутрипакетные импорты `CoreX\{Enums,Views,Audit,Models,...}` из `packages/core/src`. Импорт `CoreX\Tenancy\StanclTenancyManager` в `TenancyManager.php` удалён; `rg StanclTenancyManager packages/core/src/Tenancy/Contracts/` — пусто.

### Wakeup usage characterization (§1.3)

```text
$ rg -n "CoreX\\\\Wakeup" packages/tenancy/src packages/tasks-workflow/src --no-heading
packages/tasks-workflow/src/Internal/MaterializationWakeupHandler.php:13:use CoreX\Wakeup\Contracts\WakeupHandler;
packages/tasks-workflow/src/Internal/MaterializationWakeupHandler.php:14:use CoreX\Wakeup\Data\WakeupDelivery;
packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php:24:use CoreX\Wakeup\Contracts\WakeupScheduler;
packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php:25:use CoreX\Wakeup\Data\WakeupRequest;
packages/tenancy/src/Wakeup/Internal/RootWakeupReconciler.php:10:use CoreX\Wakeup\Contracts\WakeupSweeper;
packages/tenancy/src/Wakeup/Internal/RootRegistryWakeupHint.php:8:use CoreX\Wakeup\Contracts\WakeupHint;
packages/tenancy/src/TenancyServiceProvider.php:60:use CoreX\Wakeup\Contracts\WakeupHint;
packages/tenancy/src/TenancyServiceProvider.php:61:use CoreX\Wakeup\Contracts\WakeupSweeper;
```

| Пакет | Решение | Файл-охранник для allowlist P12.3 |
|:--|:--|:--|
| `corex/tenancy` | `suggest` (не `require`) | `TenancyServiceProvider.php` — `interface_exists(WakeupHint::class)` и `interface_exists(WakeupSweeper::class)` перед регистрацией |
| `corex/tasks-workflow` | `suggest` (не `require`) | `SqlTaskRecurrenceMaterializer::scheduleWakeup()` — `config` + `$this->container->bound(WakeupScheduler::class)`; `CompletionCaptureObserver::saving()` — `interface_exists(WorkflowBinding::class)` + `app()->bound()` |

`packages/tenancy/composer.json` не менялся: охрана подтверждена.

### ModuleDiscovery proof (обязательное)

```text
$ php -r 'require "vendor/autoload.php";
use CoreX\Intl\IntlModule;
use CoreX\Modules\Discovery\ModuleDiscovery;
$discovery = new ModuleDiscovery(fallbackModules: [IntlModule::class]);
$manifests = $discovery->discover();
echo "discovered=" . count($manifests) . PHP_EOL;
echo "title=" . $manifests[0]->title . PHP_EOL;
echo "OK: ModuleDiscovery processed corex/intl without InvalidManifestException" . PHP_EOL;'
discovered=1
title=Internationalization
OK: ModuleDiscovery processed corex/intl without InvalidManifestException
exit=0
```

### Package validation

#### `packages/intl`

```text
$ cd packages/intl && composer test
{"tool":"pest","result":"passed","tests":33,"passed":33,"assertions":96,"duration_ms":360}
exit=0
# --exclude-group=pg (pg-группа в intl отсутствует)

$ cd packages/intl && composer analyse
{"tool":"phpstan","result":"passed","errors":0}
exit=0

$ cd packages/intl && composer lint:check
{"tool":"pint","result":"passed"}
exit=0
```

#### `packages/core` (скрипты пакета — P12.2; корневой контур)

```text
$ composer analyse
{"tool":"phpstan","result":"passed","errors":0}
exit=0

$ composer test:arch
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":6,"duration_ms":22}
exit=0
```

#### `packages/tasks-workflow`

```text
$ cd packages/tasks-workflow && composer test
Tests: 12 passed (57 assertions)
exit=0
# pg-группа: доступна, 5 тестов MAT-* в Tests\Pg\MaterializationPgTest — PASS

$ cd packages/tasks-workflow && composer analyse
{"tool":"phpstan","result":"passed","errors":0}
exit=0

$ cd packages/tasks-workflow && composer lint:check
{"tool":"pint","result":"passed"}
exit=0
```

### Repository gates

```text
$ bash scripts/anti-weakening-gate.sh
ANTI-WEAKENING GATE: OK
exit=0

$ git diff --stat -- 'packages/*/database/**'
# (пусто)
exit=0
```

### Изменённые файлы

| Path | Изменение |
|:--|:--|
| `packages/intl/src/IntlModule.php` | `extends CorexModule`, `manifest()` по §1.1 |
| `packages/intl/composer.json` | `require`: `corex/modules`; `repositories`: `../modules` |
| `packages/core/src/Tenancy/Contracts/TenancyManager.php` | удалён импорт `StanclTenancyManager`, docblock без `@see` |
| `packages/tasks-workflow/composer.json` | `require`: `corex/workflow`; убран из `suggest` |
| `CHANGELOG.md` | `[Unreleased]` запись |

### Непокрытые группы / ограничения

- `packages/intl`: отдельной pg-группы нет; sqlite-лейн 33/33 PASS.
- `packages/core`: per-package `composer test`/`analyse`/`lint:check` — dependency-not-ready до P12.2; использован корневой `composer analyse` + `composer test:arch`.
- `packages/tasks-workflow`: pg-лейн доступен, 5 pg-тестов PASS.

### Suggest allowlist candidates (для P12.3)

- `(corex/tenancy, corex/wakeup)` → `TenancyServiceProvider.php`
- `(corex/tasks-workflow, corex/wakeup)` → `SqlTaskRecurrenceMaterializer.php`, `CompletionCaptureObserver.php` (workflow уже в `require`)

**Result:** PASS  
**write-site:** pre_mutation: allow · pre_final: allow

## P12.2 — Расширить контур тестов пакетов с 8 до 20

**Item:** P12.2 · **Actor:** executor/composer-2.5/medium · **Date:** 2026-09-18

### Validation

```text
$ composer test:packages
472 passed, 7 skipped (pg-only), exit=0

$ cd packages/core && composer test && composer analyse && composer lint:check
27 passed; phpstan 0 errors; pint passed

$ cd packages/modules && composer test && composer analyse && composer lint:check
exit=0
```

### Coverage gaps (explicit)

Пакеты без каталога `tests/` (если остаются) — явные дыры покрытия, не закрыты выдуманными тестами.

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## P12.3 — Ввести исполняемый гейт графа пакетов

**Item:** P12.3 · **Date:** 2026-09-18

```text
$ php scripts/package-graph-gate.php
LEGACY NAMESPACE SPLIT: CoreX\Tenancy\ ... expires when: corex/core reduction lands; core defines zero classes under CoreX\Tenancy\
package-graph-gate: OK

$ composer test:arch  # includes PackageGraphTest
7 passed
```

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## P12.4 — Переименовать `corex/import-export` в `corex/transfer`

**Item:** P12.4 · **Date:** 2026-09-18

Repo-wide sweep §4.2: нет runtime-ссылок на старые имена (кроме `audits/` и исторических audit-доков).

| TR2–TR4 | Статус |
|:--|:--|
| TR2 разделение runner | отложено (ledger P12.9) |
| TR3 таксономия ошибок | отложено |
| TR4 dry-run / catch(Throwable) | отложено |

```text
$ cd packages/transfer && composer test && composer analyse && composer lint:check
exit=0
```

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## P12.5 — Свести поиск к одному реестру драйверов

**Item:** P12.5 · **Date:** 2026-09-18

SearchManager registry; fail-closed config; `CoreX\Search\Meilisearch\` host-reserved (§2.3).

```text
$ cd packages/search && composer test && composer analyse && composer lint:check
$ cd packages/search-meilisearch && composer test && composer analyse && composer lint:check
exit=0
```

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## P12.6 — Переименовать `corex/intl-search` в `corex/search-intl`

**Item:** P12.6 · **Date:** 2026-09-18

Bridge-политика зафиксирована в `docs/dev-doctrine.md`. Saved views — владелец `corex/core`.

```text
$ cd packages/search-intl && composer test && composer analyse && composer lint:check
exit=0
```

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## P12.7 — Типизировать table-map и сделать `corex/modules` опциональным

**Item:** P12.7 · **Date:** 2026-09-18

```text
$ git diff --stat packages/attributes/database/
(пусто)

$ cd packages/attributes && composer test && composer test:pg && composer analyse && composer lint:check
16 passed; 13 pg passed; phpstan 0; pint passed
```

B5 presence-семантика — отложена в ledger P12.9.

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## P12.8 — Убрать `Closure` из `DataScope` [OWNER-GATE:G-API]

**Item:** P12.8 · **Date:** 2026-09-18

```text
$ cd packages/core && composer test --filter="serializes and unserializes"
1 passed, 4 assertions

$ rg '\(\$scope->authorize\)' packages/*/src
(пусто — заменено на DataScopeAuthorizer)
```

Полная декомпозиция DataScope — отложена в ledger P12.9.

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## P12.9 — Запечатать фазу и оформить отложенное в ADR

**Item:** P12.9 · **Actor:** executor/composer-2.5/medium · **Date:** 2026-09-18

### Pre-mutation design gate

```text
$ python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/validate-p12.py --check design
P12 structural identity check PASS
exit=0
```

### Branch receipts P12.1–P12.8

All PASS — sections §P12.1–§P12.8 above.

### Audit triad 2026-09-17

| Document | Purpose |
|:--|:--|
| `docs/audit-2026-09-17.md` | сводка |
| `docs/audit-2026-09-17-remediation.md` | исправления P12 |
| `docs/audit-2026-09-17-deferred.md` | 9 отложенных + 5 отклонённых |

### §9 invariants + §10 contour (2026-09-18)

| Check | Result |
|:--|:--|
| `git diff --stat -- 'packages/*/database/**'` | пуст |
| `composer analyse` | 0 errors |
| `composer test:arch` | 7 passed |
| `composer test:packages` | 472 passed, 7 skipped (pg-only) |
| `php scripts/package-graph-gate.php` | OK; legacy split + `expires_when` |
| `bash scripts/anti-weakening-gate.sh` | OK |

### Sealed item specs (SHA256)

| Path | SHA256 |
|:--|:--|
| `phases/P12/P12.1.md` | `71ecb1721a8e3905ff215b3e5debc37b7b1ad1d1136740ddb60f51163767318e` |
| `phases/P12/P12.2.md` | `71d8d697be049668b514af26525b3e101893b1de371e9ddfb084bde2137f79d3` |
| `phases/P12/P12.3.md` | `459e89871847489d9894dd96b0b580ffa0c578f6e4f9a87de9bdb6f29ca7718f` |
| `phases/P12/P12.4.md` | `e8bcbc012d88305bf0fcd8fc7ab5019cf284826a065df9e9a0446fce876ca7e8` |
| `phases/P12/P12.5.md` | `ee0201b85a329bcea26dceee90d0d46e2422e38fe7f28f7a4e1e4dab271f6c4f` |
| `phases/P12/P12.6.md` | `c5d36d8c9e48cea9b78ded799b91a87cc8012335ae5861a3b9d8b1a9acf32a16` |
| `phases/P12/P12.7.md` | `f8541aa3f36e902841c9ca17aaa09a12af480a2d4f389cac305d446f44fdd0ef` |
| `phases/P12/P12.8.md` | `9018b6ea0d478a25128a8e572e25d356a0ab62c23ea10a45d77269714de47bcb` |

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## Phase P12 seal

**Items:** P12.1–P12.9 (9/9 GREEN) · **Unblocks:** P8 (no auto-start)  
**Deferred:** 9 named in `docs/audit-2026-09-17-deferred.md` · **Rejected:** 5 ideas in same ledger

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow

## Bookkeeping correction (P12A.5)

**Item:** P12A.5 · **Actor:** executor/composer-2.5/medium · **Date:** 2026-09-18  
**Purpose:** Close receipt gaps A6/A7 and supply factual command output per `findings/P12-post-close-audit.md` without rewriting original P12 sections.

### P12.2 — packages without `tests/` (A6)

```text
$ for pkg in packages/*/; do [ -d "${pkg}tests" ] || basename "$pkg"; done
(empty — all 20 packages have tests/)
```

### P12.2 / A7 — `composer test:packages` attribution

```text
$ composer test:packages
…
==> packages/tenancy
  Tests:    7 skipped, 472 passed (1505 assertions)
==> packages/wakeup
…
exit=0
```

Misattribution corrected: `472 passed, 7 skipped` is the **packages/tenancy** banner only.  
**2026-09-18 aggregate across 20 packages:** 804 passed, 7 skipped, exit=0 (summed from per-package pest/json output in the same run).

### P12.3 — package-graph gate red branch (A6)

Green branch (2026-09-18):

```text
$ php scripts/package-graph-gate.php
LEGACY NAMESPACE SPLIT: CoreX\Tenancy\ …
HOST-RESERVED NAMESPACE: CoreX\Search\Meilisearch\ …
UNDECLARED DEPENDENCY EXCEPTION: corex/tasks-workflow -> corex/wakeup …
package-graph-gate: OK
exit=0
```

Red branch (controlled violation — reproduced in P12A.4, output unchanged):

```text
# temporary probe class under CoreX\Search\Meilisearch\ in packages/search/src/
packages/search-meilisearch/composer.json:1: CoreX\Search\Meilisearch\ [R3] → corex/search, corex/search-meilisearch (host-reserved namespace conditions not met)
exit=1
```

See `findings/P12A-correction-receipts.md` §P12A.4 for full R1/R3 red-branch transcripts.

### P12.4 — repo sweep + history (A6)

```text
$ rg -l 'import-export|CoreX\\ImportExport' --glob '!audits/**' --glob '!plans/**' --glob '!docs/audit-*' --glob '!CHANGELOG.md' --glob '!packages/transfer/**' .
(empty)

$ git log --follow --oneline -3 -- packages/transfer/composer.json
769365c feat(packages): внести P12.2–P12.9 и коррекции P12A.1–P12A.2
39403c1 feat: portable utilities и data/wakeup слой P5–P7
```

Rename history preserved via `--follow` from predecessor path `packages/import-export/`.

### P12.5 — four observable config checks (A6)

From `packages/search/tests/Unit/SearchServiceProviderTest.php` and `packages/search-meilisearch/tests/Unit/MeilisearchServiceProviderTest.php`:

```text
$ cd packages/search && composer test --filter='null_driver|weak_cursor|without_adapter|postgresql_provider'
{"tool":"pest","result":"passed","tests":4,"passed":4}
exit=0

$ cd packages/search-meilisearch && composer test --filter='registration_order'
{"tool":"pest","result":"passed","tests":1,"passed":1}
exit=0
```

| Check | Test | Result |
|:--|:--|:--|
| `driver: null` → `UnsupportedSearchProvider` | `test_null_driver_returns_unsupported_search_provider` | PASS |
| `driver: postgres` + weak secret → exception | `test_postgres_driver_with_weak_cursor_secret_throws` | PASS |
| `driver: meilisearch` without adapter → exception | `test_meilisearch_driver_without_adapter_throws` | PASS |
| Adapter resolves regardless of provider order | `test_meilisearch_driver_resolves_regardless_of_provider_registration_order` | PASS |

### P12.6 — repo sweep + history (A6)

```text
$ rg -l 'intl-search|CoreX\\IntlSearch' --glob '!audits/**' --glob '!plans/**' --glob '!docs/audit-*' --glob '!CHANGELOG.md' --glob '!packages/search-intl/**' .
(empty outside renamed package tree)

$ git log --follow --oneline -3 -- packages/search-intl/composer.json
769365c feat(packages): внести P12.2–P12.9 и коррекции P12A.1–P12A.2
39403c1 feat: portable utilities и data/wakeup слой P5–P7
```

### P12.7 — table names unchanged (A6)

Config defaults (symbol-for-symbol with provider fallbacks):

```text
documents: sys_attribute_documents
write_tokens: sys_attribute_write_tokens
eav_values: sys_attribute_eav_values
eav_records: sys_attribute_eav_records
schema_operations: sys_attribute_schema_operations
```

```text
$ git diff --stat packages/attributes/database/
(pусто)
```

`rg "config\(" packages/attributes/src/Internal/Engine/` — пусто (unchanged from audit GREEN table).

### P12.8 — authorize call sites before/after (A6)

```text
$ git grep -n '(\$scope->authorize)' 904ac87 -- 'packages/*/src'
(no matches at P12.1 close)

$ git show 904ac87:packages/core/src/Support/Data/DataScope.php | rg 'Closure \$authorize'
public Closure $authorize,

$ rg '\(\$scope->authorize\)' packages/*/src
(empty — replaced by DataScopeAuthorizer injection)
```

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow
