# P10 — примеры и эталоны

Скелеты написаны по коду commit `39ae489a` и автором не исполнялись. Имена, сигнатуры и форматы — норма.

## 1. Arch-тест печатает вывод гейта

```php
// было (tests/Arch/PackageGraphTest.php)
exec($command, $output, $exitCode);
expect($exitCode)->toBe(0);
// стало
exec($command, $output, $exitCode);
expect($exitCode)->toBe(0, "package-graph-gate exit {$exitCode}:\n".implode("\n", $output));
```

Эталон отказа в логе CI после правки: строки `[R4b] … (API-STALE generated surface)` видны в сообщении теста.

## 2. `scripts/ci-local.sh`

```bash
#!/usr/bin/env bash
# Reproduces the hosted CI recipe in a throwaway worktree. Never run the recipe in the main tree:
# `composer update` rewrites composer.lock and provider staging rewrites three composer.json files.
set -euo pipefail
root="$(git rev-parse --show-toplevel)"
copy="$(mktemp -d)/corex-ci"
git -C "$root" worktree add --detach "$copy" HEAD >/dev/null
trap 'git -C "$root" worktree remove --force "$copy"' EXIT
cd "$copy"
if [[ "${LARAVEL_VERSION:-}" == 12.* ]]; then
    # D42: Pest ^5.2 does not resolve with Laravel 12; the hosted Laravel 12 lane applies the same override.
    scripts/php84 composer require --dev 'pestphp/pest:^4.7' --no-update --no-interaction
fi
python3 scripts/distribution/stage-stancl-providers.py >/dev/null
scripts/php84 composer update --prefer-dist --no-interaction --no-progress ${LARAVEL_VERSION:+--with "illuminate/contracts:$LARAVEL_VERSION"}
scripts/php84 composer test:hermetic
scripts/php84 composer test:arch
scripts/php84 composer php-api
scripts/php84 composer deps:analyse
scripts/php84 composer analyse:tests-lint
```

Вызов: `bash scripts/ci-local.sh` (Laravel 13) и `LARAVEL_VERSION='12.*' bash scripts/ci-local.sh`. До P10 — exit ≠ 0
на первом же гейте; после P10 — exit 0. `composer.json` (оба скрипта создаёт P10.1): `"ci:local": "bash scripts/ci-local.sh"`,
`"test:hermetic": "PGSQL_PORT=1 bash scripts/run-root-tests.sh @additional_args"`. До P10.4 `test:hermetic` ожидаемо красный
(H1, H2, H5); зелёным его делает P10.4. Переопределение Pest меняет `composer.json` только в копии.
Проверяемое копией состояние — commit `HEAD`: незакоммиченные правки в неё не попадают.

Заглушка `rg` для проверки `bc-check` без ripgrep (раннер CI его не имеет; код 127 = «команда не найдена»):

```bash
mkdir -p build/no-rg
printf '#!/bin/sh\nexit 127\n' > build/no-rg/rg
chmod +x build/no-rg/rg
PATH="build/no-rg:$PATH" scripts/php84 composer bc-check   # из корня репозитория; ожидание после P10.1: exit 0
```

## 3. Правило R6 гейта раскладки

`BRIDGES = ('Internal', 'Laravel', 'Testing', 'PHPStan', 'Tooling')`; `DEV_BRIDGES = ('Tooling', 'PHPStan', 'Testing')`.

R6: файл `packages/<pkg>/src/**`, путь которого не содержит сегмента из `DEV_BRIDGES`, не содержит ссылки (`use` или FQCN)
на namespace с сегментом из `DEV_BRIDGES` — своего или чужого пакета.

| Файл | Ссылка | Итог |
|:--|:--|:--|
| `core/src/Messaging/Internal/SqlOutbox.php` | `CoreX\Attributes\Tooling\SurfaceScanner` | `R6 packages/core/src/Messaging/Internal/SqlOutbox.php CoreX\Attributes\Tooling\SurfaceScanner` |
| `core/src/Attributes/Tooling/SurfaceScanner.php` | `CoreX\Attributes\Tooling\SurfaceReport` | допустимо |
| `core/src/Modules/PHPStan/Rules/X.php` | `CoreX\Attributes\Tooling\SourceType` | допустимо (dev → dev) |
| `tools/src/Internal/X.php` | `CoreX\Time\Testing\FrozenClock` | `R6 …` |

Сводная строка гейта дополняется: `R0 0, R1 0, R2 0, R3 0, R4 0, R5 0, R6 0`.

## 4. Манифест без чужих членов

Фикстура теста сканера: внешний родитель в двух редакциях и собственный класс.

```php
// fixtures/external-v1/Base.php            // fixtures/external-v2/Base.php
namespace Vendor\Lib;                        namespace Vendor\Lib;
class Base {                                 class Base {
    /** @return string */                        /** @return string
    public function morph(string $c) {}           *  @throws \RuntimeException */
}                                                public function morph(string $c) {}
                                             }
// fixtures/package/src/Data/Own.php
namespace Fixture\Pkg\Data;
#[Api(since: '1.0')]
final class Own extends \Vendor\Lib\Base { public function own(): int { return 1; } }
```

Эталон записи символа (схема остаётся `php-api/1`):

```json
{
    "name": "Fixture\\Pkg\\Data\\Own",
    "kind": "class",
    "extends": "Vendor\\Lib\\Base",
    "implements": [],
    "uses": [],
    "methods": { "own": { "returnType": "int", "parameters": [] } }
}
```

Утверждения теста: (a) в `methods` нет `morph`; (b) манифест при `external-v1` и `external-v2` байт-идентичен;
(c) метод, переопределённый в `Own`, присутствует с собственной сигнатурой; (d) у символа записаны `extends`,
`implements`, `uses` как FQCN.

## 5. Протокол готовности worker схем

```text
supervisor                                   worker (ToolSchemaWorker.php)
proc_open(php -d memory_limit=64M worker)    require autoload; готовит валидатор
ждёт строку "ready\n" ≤ WORKER_STARTUP_MS  ← echo "ready\n"; fflush
  нет строки → terminate, schema_unavailable
fwrite(request JSON); fclose(stdin)        → читает stdin, валидирует
ждёт ответ ≤ WORKER_DEADLINE_MS (100 мс)   ← echo response JSON
  нет ответа → terminate, schema_limit
```

```php
// packages/tools/src/Internal/ToolSchemaSupervisor.php
private const int WORKER_STARTUP_MS = 5_000;   // запуск процесса и автозагрузка; не дедлайн валидации
```

| Сценарий теста | Ожидание |
|:--|:--|
| старт 300 мс, валидация быстрая | успех, результат валидации |
| worker не печатает `ready`, бюджет старта 50 мс | `RuntimeException('schema_unavailable')`, процесс завершён |
| готов, валидация дольше 100 мс | `RuntimeException('schema_limit')`, процесс завершён |
| готов, схема превышает память worker | `schema_limit`, процесс завершён |

Бюджет старта для теста задаётся аргументом конструктора supervisor (`?int $startupMs = null`), а не правкой константы.
