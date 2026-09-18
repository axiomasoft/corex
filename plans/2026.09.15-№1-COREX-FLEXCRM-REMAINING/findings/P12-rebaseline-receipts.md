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
