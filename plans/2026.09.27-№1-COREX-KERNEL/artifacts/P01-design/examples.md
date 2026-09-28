# P01 — примеры: скелеты, before/after, worked examples

Нормативный порядок и команды — `research/P01-design.md` §4. `$P` = `plans/2026.09.27-№1-COREX-KERNEL`.
Комментарии в bash-блоках начинаются с пробела (` # …`), чтобы селекторы секций Task не принимали их за заголовки; для bash это эквивалентно.
Код ниже — готовая основа; исполнитель адаптирует только помеченное `ADAPT:`.

## 1 `scripts/php84` (проверено 2026-09-28 на хосте)

```bash
#!/usr/bin/env bash
 # Runs a command with PHP 8.4 as `php` on PATH, so Composer scripts and
 # `#!/usr/bin/env php` binaries use 8.4 even when the host default is older.
 # Usage: scripts/php84 --check | scripts/php84 <command> [args...]
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
bin="${PHP84_BIN:-$(command -v php8.4 || true)}"

if [[ -z "$bin" || ! -x "$bin" ]]; then
    echo "php84: php8.4 binary not found (set PHP84_BIN)" >&2
    exit 1
fi

shim="$root/build/php84/bin"
mkdir -p "$shim"
ln -sf "$bin" "$shim/php"
export PATH="$shim:$PATH"

if [[ "${1:-}" == "--check" ]]; then
    version="$(php -r 'echo PHP_VERSION;')"
    if [[ "$version" != 8.4.* ]]; then
        echo "php84: expected PHP 8.4.x, got $version" >&2
        exit 1
    fi
    required=(ctype curl dom fileinfo gd gmp intl bcmath mbstring openssl pcntl pcov pdo_pgsql pdo_sqlite sqlite3 tokenizer xml zip)
    missing=()
    for ext in "${required[@]}"; do
        php -r "exit(extension_loaded('$ext') ? 0 : 1);" || missing+=("$ext")
    done
    if (( ${#missing[@]} )); then
        echo "php84: MISSING: ${missing[*]}" >&2
        # Several extensions ship in one apt package (dom → xml, pdo_sqlite → sqlite3).
        declare -A packages=()
        for ext in "${missing[@]}"; do
            case "$ext" in
                dom) packages[xml]=1 ;;
                pdo_pgsql) packages[pgsql]=1 ;;
                pdo_sqlite) packages[sqlite3]=1 ;;
                *) packages[$ext]=1 ;;
            esac
        done
        echo "php84: owner action: sudo apt install $(printf 'php8.4-%s ' $(printf '%s\n' "${!packages[@]}" | sort))" >&2
        exit 1
    fi
    echo "php84 OK: $version, extensions complete"
    exit 0
fi

exec "$@"
```

Эталонные выходы на хосте 2026-09-28 (до O-1):

```text
$ scripts/php84 --check
php84: MISSING: dom gd gmp intl bcmath pcov pdo_sqlite sqlite3 xml zip
php84: owner action: sudo apt install php8.4-bcmath php8.4-gd php8.4-gmp php8.4-intl php8.4-pcov php8.4-sqlite3 php8.4-xml php8.4-zip
exit 1
$ scripts/php84 bash -c 'env php -v | head -1'
PHP 8.4.26 (cli) (built: Sep 24 2026 17:15:37) (NTS)
```
`build/` игнорируется (`.gitignore:7 /build/`, проверено `git check-ignore`).

## 2 Манифесты (D36/D37)

### 2.1 `packages/tasks/composer.json` — before → after (фрагменты)

```diff
   "require": {
-    "php": "^8.3",
-    "corex/core": "@dev",
-    "corex/modules": "@dev",
+    "php": "^8.4",
+    "corex/core": "^1.0@dev",
+    "corex/modules": "^1.0@dev",
     "illuminate/contracts": "^12.0|^13.0",
@@
   "require-dev": {
     "larastan/larastan": "^3.0",
-    "laravel/pint": "^1.13",
-    "orchestra/testbench": "^10.0|^11.0",
-    "pestphp/pest": "^4.0"
+    "laravel/pint": "^1.32",
+    "orchestra/testbench": "^10.12|^11.3",
+    "pestphp/pest": "^5.2"
   },
-  "minimum-stability": "dev",
+  "minimum-stability": "stable",
   "prefer-stable": true,
   "extra": {
+    "branch-alias": { "dev-main": "1.0.x-dev" },
     "laravel": { ... }
```
`larastan/larastan` → `^3.12` там, где объявлен (P01.3 заменяет per-package запуск на root-бинарь; объявление остаётся для
standalone-анализа).

### 2.2 Root — добавления

```json
"require": {
    "php": "^8.4",
    "axiomasoft/stancl-jobpipeline": "2.0.0-alpha1",
    "axiomasoft/stancl-tenancy": "4.0.0-alpha1",
    "corex/recurrence": "^1.0@dev",
    "corex/search": "^1.0@dev",
    "…все прежние corex/* и flex/crm": "^1.0@dev"
},
"minimum-stability": "stable",
"prefer-stable": true
```

### 2.3 `manifest-check.py` — эталон

```text
до правок:   manifests FAIL: 154 violations   (exit 1; по кодам — research/P01-design.md §2 п.2)
после P01.1: manifests OK: 21 roots          (exit 0)
```
Негатив: вернуть в любой пакет `"corex/core": "@dev"` → строка `<dir>: mono-constraint: corex/core='@dev', expected '^1.0@dev'`, exit 1.

## 3 Golden (D38)

### 3.1 `tests/Golden/rename-map.json` — форма и применение

```json
{
  "ambiguous_packages": {
    "corex/core": ["axiomasoft/corex", "axiomasoft/corex-intl", "axiomasoft/corex-panels"],
    "corex/modules": ["axiomasoft/corex", "axiomasoft/corex-operations", "axiomasoft/corex-tools"],
    "corex/operations": ["axiomasoft/corex-operations", "axiomasoft/corex-tools", "axiomasoft/corex-webhooks"]
  },
  "generated": [
    {"from": "CoreX\\Api\\ApiController", "kind": "class", "to": "CoreX\\Operations\\Http\\ApiController"},
    {"from": "corex/access", "kind": "package", "to": "axiomasoft/corex-access"}
  ],
  "manual": [],
  "schema": "corex-golden-rename-map/1"
}
```

Применение (PHP, `tests/Golden/Support/RenameMap.php`):

```php
<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

/**
 * Applies planned renames to the frozen golden baseline as exact tokens.
 * Prefix replacement would be wrong: psr-4 folding maps one old prefix to many new ones.
 */
final readonly class RenameMap
{
    private const FQCN = '/\\\\?[A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)+/';

    /** @param array<string, string> $classes @param array<string, string> $packages @param list<array<string, mixed>> $manual */
    private function __construct(private array $classes, private array $packages, private array $manual) {}

    public static function load(string $path): self
    {
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $classes = $packages = [];
        foreach ($data['generated'] as $rule) {
            match ($rule['kind']) {
                'class' => $classes[$rule['from']] = $rule['to'],
                'package' => $packages[$rule['from']] = $rule['to'],
            };
        }

        return new self($classes, $packages, $data['manual']);
    }

    public function apply(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[is_string($key) ? $this->string($key) : $key] = $this->apply($item);
            }

            return $out;
        }

        return is_string($value) ? $this->string($value) : $value;
    }

    private function string(string $value): string
    {
        if (isset($this->packages[$value])) {
            return $this->packages[$value];
        }

        $value = preg_replace_callback(self::FQCN, function (array $m): string {
            $leading = str_starts_with($m[0], '\\') ? '\\' : '';
            $name = ltrim($m[0], '\\');

            return $leading.($this->classes[$name] ?? $name);
        }, $value);

        foreach ($this->manual as $rule) {
            // ADAPT (P02): kinds `identifier` (exact string) and `field` (layer/match/field/to).
            if (($rule['kind'] ?? null) === 'identifier' && $value === $rule['from']) {
                return $rule['to'];
            }
        }

        return $value;
    }
}
```

Worked example: baseline `"type": "?CoreX\\Api\\ApiError"` + правило `CoreX\Api\ApiError → CoreX\Operations\Errors\ApiError`
→ `"?CoreX\\Operations\\Errors\\ApiError"`; строка `"corex/access"` → `"axiomasoft/corex-access"`; `"corex/operations"`
остаётся как есть (ambiguous) — P02 обязан добавить `manual`-правило, иначе golden wire упадёт на `owner`.

### 3.2 Коллектор (скелет)

```php
<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

use CoreX\Events\DomainEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class GoldenSnapshot
{
    /** @return list<class-string> */
    public static function sourceClasses(string $repoRoot): array
    {
        $classes = [];
        foreach (glob($repoRoot.'/packages/*/composer.json') ?: [] as $manifest) {
            $data = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
            foreach ($data['autoload']['psr-4'] ?? [] as $prefix => $dirs) {
                foreach ((array) $dirs as $dir) {
                    $base = dirname($manifest).'/'.rtrim($dir, '/');
                    if (! str_ends_with(rtrim($dir, '/'), 'src') && ! str_contains($dir, 'src/')) {
                        continue; // database/, tests/ are not surface
                    }
                    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
                    foreach ($iterator as $file) {
                        if ($file->getExtension() !== 'php') {
                            continue;
                        }
                        $relative = substr($file->getPathname(), strlen($base) + 1, -4);
                        $classes[] = $prefix.str_replace('/', '\\', $relative);
                    }
                }
            }
        }
        $classes = array_values(array_unique(array_filter($classes, static fn (string $c): bool => class_exists($c) || interface_exists($c) || trait_exists($c) || enum_exists($c))));
        sort($classes);

        return $classes;
    }

    /** @return array<string, mixed> */
    public static function phpSurface(array $classes): array
    {
        $out = [];
        foreach ($classes as $class) {
            $r = new ReflectionClass($class);
            $methods = [];
            foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $methods[$m->getName()] = [
                    'static' => $m->isStatic(),
                    'return' => (string) $m->getReturnType(),
                    'params' => array_map(static fn (\ReflectionParameter $p): array => [
                        'name' => $p->getName(),
                        'type' => (string) $p->getType(),
                        'optional' => $p->isOptional(),
                        'variadic' => $p->isVariadic(),
                        'byRef' => $p->isPassedByReference(),
                    ], $m->getParameters()),
                ];
            }
            ksort($methods);
            $out[$class] = [
                'kind' => $r->isEnum() ? 'enum' : ($r->isInterface() ? 'interface' : ($r->isTrait() ? 'trait' : 'class')),
                'final' => $r->isFinal(),
                'abstract' => $r->isAbstract() && ! $r->isInterface(),
                'readonly' => $r->isReadOnly(),
                'internal' => str_contains($class, '\\Internal\\'),
                'parent' => $r->getParentClass() ? $r->getParentClass()->getName() : null,
                'interfaces' => self::sorted($r->getInterfaceNames()),
                'constants' => self::sorted(array_keys(array_filter($r->getReflectionConstants(), static fn ($c) => $c->isPublic() && $c->getDeclaringClass()->getName() === $class))),
                'methods' => $methods,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function routes(): array
    {
        $rows = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            $rows[] = [
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $action === 'Closure' ? 'Closure' : $action,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$a['uri'], $a['methods']] <=> [$b['uri'], $b['methods']]);

        return $rows;
    }

    /** @param list<class-string> $classes @return array<string, string> */
    public static function events(array $classes): array
    {
        $names = [];
        foreach ($classes as $class) {
            if (is_subclass_of($class, DomainEvent::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $names[$class] = $class::name();
            }
        }
        ksort($names);

        return $names; // ADAPT: + MessageContractRegistry contracts if bound (dossier §4.2 p.2)
    }

    /** @return array<string, mixed> */
    public static function wire(string $tmp): array
    {
        Artisan::call('corex:api-snapshots', ['directory' => $tmp]);
        $out = [];
        foreach (['errors', 'operations', 'openapi', 'webhooks'] as $file) {
            $path = $tmp.'/'.$file.'.json';
            $out[$file] = is_file($path) ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : null;
        }

        return $out;
    }

    /** @param list<string> $values @return list<string> */
    private static function sorted(array $values): array
    {
        sort($values);

        return array_values($values);
    }
}
```

`Normalizer::normalize(mixed)`: рекурсивный `ksort` ассоциативных массивов; удаление ключей `contract_fingerprint`,
`registry_fingerprint`, `files` (в wire manifest), `generated_at`; остальное — как есть. Каждое новое исключение — с
комментарием «почему» (K5).

### 3.3 Тест

```php
<?php

declare(strict_types=1);

use CoreX\Tests\Golden\GoldenTestCase;
use CoreX\Tests\Golden\Support\{GoldenSnapshot, Normalizer, RenameMap};

uses(GoldenTestCase::class);

dataset('layers', ['php-surface', 'routes', 'events', 'wire']);

it('matches the frozen golden baseline modulo planned renames', function (string $layer): void {
    $root = dirname(__DIR__, 2);
    $classes = GoldenSnapshot::sourceClasses($root);
    $current = match ($layer) {
        'php-surface' => GoldenSnapshot::phpSurface($classes),
        'routes' => GoldenSnapshot::routes(),
        'events' => GoldenSnapshot::events($classes),
        'wire' => GoldenSnapshot::wire(sys_get_temp_dir().'/corex-golden-'.getmypid()),
    };
    $path = __DIR__."/baseline/{$layer}.json";

    if (getenv('GOLDEN_UPDATE') === '1') {
        file_put_contents($path, json_encode(Normalizer::normalize($current), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->markTestSkipped("baseline written: {$layer}");
    }

    $baseline = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $expected = Normalizer::normalize(RenameMap::load(__DIR__.'/rename-map.json')->apply($baseline));

    expect(Normalizer::normalize($current))->toBe($expected);
})->with('layers');
```
`GoldenTestCase extends CoreX\Tests\TestCase`, `getPackageProviders()` = провайдеры `extra.laravel.providers` всех пакетов
корня + `ToolServiceProvider` (как `tests/Feature/OperationContractTest.php:49–52`). ADAPT: точный список собрать
механически (`jq -r '.extra.laravel.providers[]?' packages/*/composer.json`).

Эталон: первый `GOLDEN_UPDATE=1` → `4 skipped`; затем `Tests: 4 passed`. Негативы — досье §4.2 п.7.

### 3.4 `tests/Golden/REBASELINE.md`

```markdown
(заголовок файла: «Golden rebaseline log», уровень 1)

Baseline под `tests/Golden/baseline/` меняется только намеренным изменением поверхности (PLAN3.D38).

| Дата | Решение | Слои | Причина |
|:--|:--|:--|:--|
| 2026-MM-DD | PLAN3.D38 | all | initial baseline P01.2 |
```

## 4 Ownership gate v2 (D39)

### 4.1 `scripts/ownership-map.json` (фрагмент)

```json
{
  "schema": "corex-ownership-map/1",
  "packages": {
    "corex/access": {"dir": "access", "target": "axiomasoft/corex-access", "root": "CoreX\\Access",
      "config": "corex-access", "tag_prefix": "corex-access-", "env_prefix": "COREX_ACCESS_", "command_prefix": "corex:access:"},
    "corex/operations": {"dir": "operations", "target": "axiomasoft/corex-operations", "root": "CoreX\\Operations",
      "config": "corex-operations", "tag_prefix": "corex-operations-", "env_prefix": "COREX_OPERATIONS_", "command_prefix": "corex:operations:"},
    "corex/recurrence": {"dir": "recurrence", "target": "axiomasoft/corex-recurrence", "root": "CoreX\\Recurrence",
      "config": null, "tag_prefix": null, "env_prefix": null, "command_prefix": null},
    "flex/crm": {"dir": "crm", "target": "flex/crm", "root": "Flex\\Crm",
      "config": "flex-crm", "tag_prefix": "flex-crm-", "env_prefix": "FLEX_CRM_", "command_prefix": "flex:crm:"}
  },
  "kernel": {
    "packages": ["corex/core", "corex/modules"],
    "target": "axiomasoft/corex",
    "config": "corex", "tag_prefix": "corex-", "env_prefix": "COREX_", "command_prefix": "corex:",
    "modules": ["Attributes", "Audit", "Config", "Context", "Data", "Database", "Departments", "Errors", "Features",
                "Messaging", "Modules", "Observability", "Settings", "Storage", "Time"],
    "root_symbols": ["CoreX\\CoreServiceProvider"],
    "reserved": ["CoreX\\Testing"]
  }
}
```
Источник значений — glossary §Packages (root), §Identifiers per package (config/tags/env/commands), §Kernel modules,
§Root symbols, §Reserved segments, §Dev roots. `flex/crm` root — ADAPT по glossary §Packages.

### 4.2 Экстракция (PHP-регулярки по тексту файла)

```php
const RX_CONFIG  = "/mergeConfigFrom\\(\\s*[^,]+,\\s*'([^']+)'/";
const RX_TAG     = "/->publishes(?:Migrations)?\\(\\s*\\[.*?\\]\\s*,\\s*'([^']+)'/s";
const RX_COMMAND = "/(?:protected\\s+\\\$signature\\s*=\\s*'|#\\[AsCommand\\(\\s*(?:name:\\s*)?')([a-z0-9:_-]+)/i";
const RX_ENV     = "/\\benv\\(\\s*'([A-Z0-9_]+)'/";
const RX_TABLE   = "/Schema::(?:connection\\([^)]*\\)->)?create\\(\\s*'([a-z0-9_]+)'/";
const RX_NS      = '/^namespace\\s+([\\w\\\\]+)\\s*;/m';
```
Области: config/tag/command — `packages/*/src/**/*.php`; env — `packages/*/config/**/*.php`; table —
`packages/*/database/migrations/**/*.php`; namespace — `packages/*/src/**/*.php` (первый сегмент после `CoreX\` для ядра).

### 4.3 Формат вывода и baseline

```text
$ php scripts/ownership-gate.php --report | jq -r '.violations[]' | head -5
O2 command corex/operations corex:api:prune expected-prefix corex:operations:
O2 config corex/access corex-auth expected corex-access
O2 config corex/modules corex-modules expected corex
O2 namespace corex/access CoreX\Auth expected CoreX\Access
O3 namespace corex/core CoreX\Contracts not-a-kernel-module
```
`scripts/ownership-gate-baseline.json`: `{"schema": "corex-ownership-baseline/1", "violations": ["O2 command …", …]}`
(отсортировано). Выходы: `ownership OK: <N> identifiers, <B> baselined` (exit 0); `ownership FAIL: new: <строки>`,
`ownership FAIL: stale: <строки>` (exit 1); `--strict` → каждый baseline как new.

## 5 PHPStan per package

### 5.1 `packages/<p>/phpstan.neon`

```neon
includes:
    - phar://phpstan.phar/conf/bleedingEdge.neon
    - phpstan-baseline.neon

parameters:
    level: max
    tmpDir: ../../build/phpstan/<p>   # ADAPT: имя каталога пакета
    paths:
        - src
        - database/migrations          # ADAPT: только если каталог есть
    checkModelProperties: true
    checkPhpDocMissingReturn: true
```
Strict-rules и larastan подключает `phpstan/extension-installer` (root `require-dev`, `allow-plugins`).
`packages/{core,modules,tenancy}/composer.json`: `"extra": {"phpstan": {"includes": ["extension.neon"]}}`.

### 5.2 `scripts/phpstan-packages.sh`

```bash
#!/usr/bin/env bash
 # Analyses each package with its own config and baseline, then enforces the ratchet.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

status=0
for config in packages/*/phpstan.neon phpstan.neon; do
    echo "==> ${config}"
    vendor/bin/phpstan analyse -c "$config" --memory-limit=2G --no-progress || status=1
done
php scripts/quality-ceiling-guard.php || status=1
exit "$status"
```

## 6 Ratchet

### 6.1 `scripts/quality-ceilings.json`

```json
{
  "schema": "corex-quality-ceilings/1",
  "phpstan": {"access": 0, "core": 0, "root": 0},
  "dependencies": {"notifications": 1}
}
```
(значения — факт после `--tighten`; ключи — каталоги пакетов + `root`.)

### 6.2 `scripts/quality-ceiling-guard.php`

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Quality ratchet: PHPStan baseline entries and dependency-analyser `ratchet:` ignores
 * per package may only shrink. `--tighten` lowers ceilings to the measured values.
 */
$root = dirname(__DIR__);
$ceilingsPath = $root.'/scripts/quality-ceilings.json';
$ceilings = json_decode((string) file_get_contents($ceilingsPath), true, flags: JSON_THROW_ON_ERROR);

$measure = static function (string $file, string $pattern): int {
    if (! is_file($file)) {
        return 0;
    }
    preg_match_all($pattern, (string) file_get_contents($file), $m);

    return array_sum(array_map('intval', $m[1] ?? [])) ?: count($m[0] ?? []);
};

$actual = ['phpstan' => [], 'dependencies' => []];
$actual['phpstan']['root'] = $measure($root.'/phpstan-baseline.neon', '/^\s+count: (\d+)$/m');
foreach (glob($root.'/packages/*/composer.json') ?: [] as $manifest) {
    $dir = basename(dirname($manifest));
    $actual['phpstan'][$dir] = $measure(dirname($manifest).'/phpstan-baseline.neon', '/^\s+count: (\d+)$/m');
    $actual['dependencies'][$dir] = substr_count((string) @file_get_contents(dirname($manifest).'/composer-dependency-analyser.php'), '// ratchet:');
}

if (in_array('--tighten', $argv, true)) {
    foreach ($actual as $kind => $values) {
        foreach ($values as $key => $value) {
            $ceilings[$kind][$key] = min($value, $ceilings[$kind][$key] ?? $value);
        }
        ksort($ceilings[$kind]);
    }
    file_put_contents($ceilingsPath, json_encode($ceilings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

$violations = [];
foreach ($actual as $kind => $values) {
    foreach ($values as $key => $value) {
        $ceiling = $ceilings[$kind][$key] ?? 0;
        if ($value > $ceiling) {
            $violations[] = "{$kind}.{$key}: {$value} > ceiling {$ceiling}";
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "ceilings FAIL:\n  ".implode("\n  ", $violations)."\n");
    exit(1);
}
echo "ceilings OK\n";
```
Замечание: в PHPStan baseline каждая запись имеет `count: N` — сумма `count` и есть число подавленных ошибок.

### 6.3 `scripts/anti-weakening-gate.sh` — замена строк 71–76

```bash
 # Quality ratchet (PLAN3.D37/D38/D39): ceilings may only fall, ownership baseline may only
 # lose entries, golden baseline changes need a REBASELINE.md entry.
if git diff "$DIFF_RANGE" -- scripts/quality-ceilings.json | command grep -qE '^\+\s+"[a-z-]+": [0-9]+'; then
    python3 - "$DIFF_RANGE" <<'PY' || log_fail "quality ceiling raised (scripts/quality-ceilings.json)"
import json, subprocess, sys
rng = sys.argv[1].split('...')[0].split('..')[0]
old = json.loads(subprocess.run(['git', 'show', f'{rng}:scripts/quality-ceilings.json'], capture_output=True, text=True).stdout or '{}')
new = json.load(open('scripts/quality-ceilings.json'))
raised = [f'{k}.{p}' for k in new if isinstance(new[k], dict) for p, v in new[k].items() if v > old.get(k, {}).get(p, v)]
sys.exit(1 if raised else 0)
PY
fi
if git diff "$DIFF_RANGE" -- scripts/ownership-gate-baseline.json | command grep -qE '^\+\s+"O[0-9]'; then
    log_fail "ownership baseline gained entries (only removals allowed)"
fi
if git diff --name-only "$DIFF_RANGE" -- tests/Golden/baseline | command grep -q . \
   && ! git diff "$DIFF_RANGE" -- tests/Golden/REBASELINE.md | command grep -qE '^\+\| [0-9]{4}-'; then
    log_fail "golden baseline changed without a REBASELINE.md entry"
fi
```
Первичное создание (P01): ceilings и ownership baseline — новые файлы, в `old` отсутствуют → не рост; golden baseline
создаётся вместе с первой строкой `REBASELINE.md`. ADAPT: `DIFF_RANGE` и `log_fail` — существующие в скрипте.

## 7 Dependency analyser

`packages/<p>/composer-dependency-analyser.php`:

```php
<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    // ratchet: P07 notifications→wakeup must go through a kernel contract (PLAN3.D16)
    ->ignoreErrorsOnPackage('corex/wakeup', [ErrorType::SHADOW_DEPENDENCY]); // ADAPT: только при факте
```

`scripts/dependency-analyser.sh`:

```bash
#!/usr/bin/env bash
 # Runs composer-dependency-analyser for a package inside its own standalone install,
 # which is what a split repository will resolve.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

run() {
    local dir="$1"
    [[ "$dir" == tenancy ]] && python3 scripts/distribution/stage-stancl-providers.py >/dev/null
    composer --working-dir="packages/$dir" update --no-interaction --no-progress --quiet
    [[ "$dir" == tenancy ]] && composer --working-dir="packages/$dir" config --unset repositories.stancl-providers
    vendor/bin/composer-dependency-analyser \
        --composer-json="packages/$dir/composer.json" \
        --config="packages/$dir/composer-dependency-analyser.php"
}

if [[ "${1:-}" == "--all" ]]; then
    status=0
    for manifest in packages/*/composer.json; do
        dir="$(basename "$(dirname "$manifest")")"
        echo "==> $dir"
        run "$dir" || status=1
    done
    exit "$status"
fi
run "${1:?usage: dependency-analyser.sh <package-dir>|--all}"
```
Эталон: `No composer issues found` на пакет; итог exit 0.

## 8 CI (D39)

### 8.1 Job before → after (`tests`)

```diff
+permissions: {}
+
 jobs:
   tests:
+    permissions:
+      contents: read
     strategy:
       matrix:
-        php: ['8.3', '8.4', '8.5']
+        php: ['8.4', '8.5']
         laravel: ['12.*', '13.*']
     steps:
-      - uses: actions/checkout@v4
+      - uses: actions/checkout@<40-hex> # v7.0.1
+        with:
+          persist-credentials: false
-      - uses: shivammathur/setup-php@v2
+      - uses: shivammathur/setup-php@<40-hex> # 2.37.2
@@
-      - name: Boxed profile (drop 8.4-only tenancy)
-        if: matrix.php == '8.3'
-        run: composer remove corex/tenancy --no-update --no-interaction
@@
       - name: Install dependencies
         run: composer update --prefer-dist --no-interaction --no-progress
+      - name: Composer audit
+        run: composer audit --locked
```
SHA проставляет `pinact run`, не вручную.

### 8.2 Новые jobs (фрагмент)

```yaml
  manifests:
    name: Manifests (validate --strict, D36)
    runs-on: ubuntu-latest
    permissions:
      contents: read
    steps:
      - uses: actions/checkout@<sha> # v7.0.1
        with:
          persist-credentials: false
      - uses: shivammathur/setup-php@<sha> # 2.37.2
        with:
          php-version: '8.4'
          coverage: none
      - run: python3 scripts/manifest-check.py
      - run: for d in . packages/*/; do composer validate --strict --no-check-lock --working-dir="$d" || exit 1; done

  zizmor:
    name: Workflow security (zizmor)
    runs-on: ubuntu-latest
    permissions:
      contents: read
    steps:
      - uses: actions/checkout@<sha> # v7.0.1
        with:
          persist-credentials: false
      - uses: zizmorcore/zizmor-action@<sha> # v0.6.4
        with:
          advanced-security: false
```
Аналогично `golden` (`composer golden`), `ownership` (`php scripts/ownership-gate.php`), `dependencies`
(`bash scripts/dependency-analyser.sh --all` + `composer audit --locked --working-dir=packages/<p>` в цикле); шаг PHPStan
в `quality` → `composer analyse`. PG: `tests-pg` — `matrix.postgres: ['16', '18']`, `image: postgres:${{ matrix.postgres }}`.

### 8.3 Конфиги

`.github/dependabot.yml`:
```yaml
version: 2
updates:
  - package-ecosystem: github-actions
    directory: /
    schedule: { interval: weekly }
    groups:
      actions: { patterns: ['*'] }
  - package-ecosystem: composer
    directories: ['/', '/packages/*']
    schedule: { interval: weekly }
    groups:
      composer: { patterns: ['*'] }
```
`.github/CODEOWNERS`:
```text
/.github/   @academici
/scripts/   @academici
```
`.github/zizmor.yml` (только при необходимости, каждое правило с причиной):
```yaml
rules:
  unpinned-images:
    ignore:
      - ci.yml   # postgres service images are pinned by major tag; digest pinning is P09 release hardening
```

## 9 Эталонная Completion Notes строка (формат)

```text
P01.2 GREEN: golden 4 layers (php-surface N classes, routes N, events N, wire 4 files), byte-stable x2;
negatives a–d red/green as expected; review: cursor/grok-4.6-high GREEN
```
