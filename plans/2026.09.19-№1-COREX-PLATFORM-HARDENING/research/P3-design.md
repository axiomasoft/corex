# Дизайн фазы P3 — исполняемые гейты

**Статус:** нормативный вход items P3.1–P3.7. Это **источник готовых правил
и готового кода гейтов**, а не эскиз. Каждый item называет здесь свой
параграф; спека без такого указателя — дефект дизайна.

**Что этот документ НЕ делает.** Он не чинит production-код, который гейт
покраснит: приведение — фаза-владелец. Он не исполняется до конца стадии 2
плана (D14).

**Входы:** `02-shapes-platform.md` §3; `P1-design.md` §2.5–2.8, §3, §5.3, §6,
§9, §12; `P2-design.md` §0, §3, §4.4; `P3-design-rag.md`;
`findings/audit-verdicts.md` A18/A57/A61/A83/A85/A99, N02/N03;
`scripts/package-graph-gate.php`; решения D4, D12, D14–D19.

---

## 0. Инварианты, обязательные для всех items фазы

1. **Язык — русский**, идентификаторы правил, команд, классов — латиницей.
2. **Ширина строки ≤ 100 символов.** Таблицы могут её превышать, проза — нет.
3. **Гейт фиксирует достигнутое.** Новое нарушение блокирует; текущий долг
   либо allowlist с причиной, либо observational-режим, названный явно.
   Чинить код пакетов, чтобы гейт зазеленел, запрещено.
4. **Негативная проверка обязательна (`RAG:✅` P3-Q4).** Для каждого
   блокирующего правила: контролируемое обратимое нарушение, ненулевой exit,
   в выводе — код правила и имя посаженного файла. Наблюдательный гейт
   печатает нарушение и выходит 0; негативная проверка всё равно требует
   имя файла в выводе.
5. **N02 снят.** `composer test:packages` покрывает 20 живых пакетов
   (`RAG:—`, P1-design.md §12). P3 не добавляет цикл тестов «для полноты».
6. **Одна новая dev-зависимость — Roave (D4, D18).** Deptrac, PHPat,
   class-leak, php-parser в корневой Composer не вводятся (D17).
7. **Парсер графа — stdlib `token_get_all` (D16→D17).** Line-regex
   `parseUseImport` заменяется, а не дополняется вторым инструментом.
8. **Ни один существующий порог не понижается.**
   `bash scripts/anti-weakening-gate.sh` — обязательный Validation.
9. **Маркеры:** факт о репозитории — `RAG:—`; внешняя практика — `RAG:✅`
   со ссылкой на `P3-design-rag.md`; неподтверждённое — `[UNVERIFIED]`.
10. **CHANGELOG.** Item, меняющий продукт (`scripts/`, `tests/Arch`,
    `phpstan.neon`, `composer.json`, `.github`, `packages/core`), дописывает
    одну строку в корневой `CHANGELOG.md` под `[Unreleased]` → `### Added`.
11. Из `audits/*` не копируется ни код, ни формулировка.

---

## 1. Состав гейтов после P3

| Гейт | Item | Режим | Правило |
|:--|:--|:--|:--|
| корневой PHPStan на currently-green пакеты | P3.1 | blocking | N03, только уже зелёные |
| tokenizer-граф (group-use, FQCN, extends) | P3.2 | blocking | закрывает `G-GATE-BLIND` |
| R4 чужой не-public-api неймспейс | P3.3 | blocking + allowlist текущего | API-003 |
| R4b незаявленная поверхность | P3.3 | blocking на *новых* типах | A18/A85, D17 |
| R5 направление `package-type` | P3.3 | blocking | D3 |
| Roave release-to-release | P3.4 | blocking только `stable` | D4, D18, API-005 |
| DurablePayload PHPStan | P3.5 | blocking на помеченных типах | RUN-002/003 |
| словарь имён | P3.6 | blocking на *новых* публичных именах | `naming.md` §5.3 |
| `config()` вне composition | P3.6 | observational | CFG-002 |
| T1/T2/T4 владение таблицами | P3.7 | blocking | DATA-001/002/004, D19 |
| A57/A61/A99 contract-тесты | P3.7 | blocking | уже достигнутые инварианты |

`contract-guard.sh`, `LayerTest`, `anti-weakening-gate.sh`, `mutation-check.sh`
не меняют поведения. R1/R2/R3 гейта графа сохраняются; меняется только сбор
рёбер и добавляются R4/R4b/R5.

---

## 2. Q5 закрыт (D17)

Критерий фазы: «правило, которое существующий гейт выразить не может».
`RAG:✅` P3-Q1: такого правила среди задач P3 нет, если парсер видит не только
`use`-строку. `RAG:—` LayerTest уже отверг Deptrac как лишнюю зависимость.
`RAG:✅` P3-Q5: class-leak — про unused, не про публичность.

Эскиз §3.1 («R4 внутри существующего сканера») **принимается**. Предложение
аудита «поставить анализатор границ» **отклоняется** с записью D17.

---

## 3. Полнота PHPStan (item P3.1)

`RAG:—` корневой `phpstan.neon` `paths` — 7 пакетов: api, core, crm, tasks,
modules, tenancy, workflow (плюс их migrations). У 18 пакетов есть
`packages/*/phpstan.neon` или скрипт `analyse`; `core` и `modules` ходят в
корневой neon. CI `analyse` есть у api, auth, media, panels, tenancy,
notifications+wakeup (extra paths), плюс корневой job. Нет отдельного
analyse-job у attributes, transfer, intl, search-intl, recurrence, search,
search-meilisearch, tasks-workflow.

N02 опровергнут: отдельные CI-джобы «чтобы тесты recurrence/meilisearch
вообще бежали» не нужны — они уже в `composer test:packages`.

### 3.1 Алгоритм (готовое поведение)

CLI: `php scripts/phpstan-contour.php` пишет
`artifacts/architecture/phpstan-contour.json` (плановый путь внутри плана
**не** используется: это продуктовый контур, файл — evidence в
`plans/<ID>/artifacts/architecture/phpstan-contour.json`).

Для каждого живого пакета из цикла `test:packages`:

1. Запустить его `composer --working-dir=packages/<dir> analyse`.
2. Записать `package`, `exit`, `in_root_neon` (path есть в корневом neon).
3. `pass = (exit === 0)`.

Затем корневой `phpstan.neon`:

- каждый `pass === true` пакет **обязан** появиться в `parameters.paths`
  как `packages/<dir>/src` (migrations — только если уже были или пакетный
  `analyse` их включает);
- `pass === false` в `paths` **не** добавляется; строка попадает в
  `debt` JSON с целым `exit`. Это долг фазы-владельца, не baseline.neon
  (анти-ослабление запрещает baseline).

`composer.json` получает скрипт
`"analyse:packages": "php scripts/phpstan-contour.php --also-analyse-root"`.
Корневой `composer analyse` остаётся главным blocking-контуром и после
item'а включает ровно currently-green множество.

CI `quality` job не меняет PHP 8.4. Новых изолированных test-job для
recurrence/search-meilisearch нет.

### 3.2 Негативная проверка

Посадить в `packages/core/src/Support/PhpstanContourProbe.php` файл с
явной ошибкой типов (`string $x = 1;`). `composer analyse` выходит ≠ 0 и
печатает `PhpstanContourProbe.php`. Файл удаляется в `finally`. Нельзя
оставлять его в дереве.

### 3.3 Готовая форма JSON

```json
{
  "schema": "phpstan-contour/1",
  "root_level": 6,
  "packages": [
    {"name": "corex/core", "dir": "core", "pass": true, "in_root_neon": true, "exit": 0}
  ],
  "debt": []
}
```

Сортировка `packages` по `dir`. Без `generated_at`. Повторный прогон на
неизменном дереве байтово идентичен по ключам `name/dir/pass/in_root_neon`
(поле `exit` тоже стабильно).

---

## 4. Tokenizer-граф (item P3.2)

`RAG:—` `parseUseImport` (`scripts/package-graph-gate.php:352-369`)
возвращает `null`, если в строке есть `{`. `RAG:✅` P3-Q1: FQCN без `use`
и `extends`/`implements` — отдельный класс пропущенных рёбер.

P3.2 **не** добавляет R4. Только сбор рёбер. R1/R2/R3 начинают видеть
group-use и FQCN — это починка ложно-зелёного, не новая политика.
Allowlist, который покраснеет из-за вновь увиденных легальных рёбер,
дополняется именованной записью с причиной (как существующие wakeup-мосты),
а не молча.

### 4.1 Готовая замена сборщика

Цикл `foreach ($lines as $lineNumber => $line)` заменяется вызовом
`extractReferences($file)` по целому файлу. Функция ниже — канон. Имена
сохраняют стиль гейта (без namespace, `final` не для функций файла).

```php
/**
 * @return list<array{import: string, line: int, via: 'use'|'fqcn'|'extends'}>
 */
function extractReferences(string $file): array
{
    $code = file_get_contents($file);
    if ($code === false) {
        return [];
    }

    $tokens = token_get_all($code, TOKEN_PARSE);
    $useTable = [];
    $namespace = '';
    $refs = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE) {
            $namespace = readName($tokens, $i);
            continue;
        }

        if ($token[0] === T_USE) {
            foreach (readUseClause($tokens, $i, $token[2]) as $clause) {
                $useTable[$clause['alias']] = $clause['fqcn'];
                $refs[] = [
                    'import' => $clause['fqcn'],
                    'line' => $clause['line'],
                    'via' => 'use',
                ];
            }
            continue;
        }

        if (in_array($token[0], [T_EXTENDS, T_IMPLEMENTS], true)) {
            $name = readName($tokens, $i);
            if ($name !== '') {
                $refs[] = [
                    'import' => resolveName($name, $namespace, $useTable),
                    'line' => $token[2],
                    'via' => 'extends',
                ];
            }
            continue;
        }

        if ($token[0] === T_NAME_FULLY_QUALIFIED) {
            $refs[] = [
                'import' => ltrim($token[1], '\\'),
                'line' => $token[2],
                'via' => 'fqcn',
            ];
        }
    }

    return uniqueRefs($refs);
}
```

`readUseClause` обязан понимать `use A\B;`, `use A\B as C;`,
`use A\{B, C as D};`, `use function`, `use const`. Алгоритм — тот же, что
таблица референс-`use` в `P2-design.md` §3.4; копировать оттуда разбор
group-use, не выдумывать третий.

`resolveName`: если имя начинается с `\`, снять слэш; иначе подставить
alias из `$useTable` или склеить с `$namespace`.

R1/R2 применяютcя к каждому `import` как сейчас. Дубликаты
`(import, file)` схлопываются: одно нарушение на ребро.

### 4.2 Негативные проверки (добавить в PackageGraphTest)

1. **Group-use.** Посадить в
   `packages/core/src/Support/GroupUseProbe.php`:
   `use CoreX\Tenancy\Internal\{Foo as TFoo};` — если класса нет, достаточно
   `use CoreX\Wakeup\Contracts\{WakeupHint};` из пакета без require.
   Гейт печатает `[R1]` или `[R2]` и `GroupUseProbe.php`. Удалить файл.
2. **FQCN без use.** Посадить
   `new \CoreX\Wakeup\Contracts\WakeupHint();` без `use` — гейт видит ребро,
   имя файла в выводе.
3. Регрессия существующих тестов PackageGraphTest (R1 guard, R3 probe)
   остаётся зелёной.

Существующий `parseUseImport` удаляется, чтобы его нельзя было вызвать
из забытого места. Поиск по файлу: 0 вхождений `parseUseImport`.

---

## 5. R4, R4b, R5 (item P3.3)

Зависит от терминального P3.2: правила бегут по `extractReferences`.

### 5.1 R4 — чужой неймспейс вне `public-api`

Эскиз §3.1 **принимается**; сообщения R2 и R4 **различаются** (P1-design.md
§6.5, D12).

```php
function violatesPublicApi(
    string $import,
    string $fromPackage,
    string $ownerPackage,
    array $packages,
): ?string {
    if ($fromPackage === $ownerPackage) {
        return null;
    }
    if (violatesForeignInternal($import, $fromPackage, $ownerPackage)) {
        return null; // R2 already reports this edge
    }
    $public = $packages[$ownerPackage]['public_api'] ?? null;
    if ($public === null) {
        return 'R4 missing extra.corex.public-api on '.$ownerPackage;
    }
    foreach ($public as $prefix) {
        if ($import === $prefix || str_starts_with($import, $prefix.'\\')) {
            return null;
        }
    }

    return 'R4 non-public namespace';
}
```

`public_api` читается из `packages/<dir>/composer.json` →
`extra.corex.public-api` (P1.2 к исполнению уже заполнил). Prefix-match,
не substring (`RAG:✅` P3-Q5).

Сообщение R4: `R4 {file}: импорт непубличного {import} из пакета {owner}`.
Сообщение R2 без изменений: `foreign Internal import`.

Текущие нарушения на момент прогона собираются в
`scripts/package-graph-gate.json` ключ `public_api_exceptions` той же формы,
что `undeclared_dependency_exceptions` (`from`, `to`, `import_file`, `adr`,
`expires_when`, `reason`). Пустой массив, если дерево чистое. Новое ребро
мимо списка — blocking.

### 5.2 R4b — незаявленная поверхность

`RAG:✅` P3-Q5. Для каждого top-level типа в `packages/*/src`:

```text
нарушение, если ВСЕ сразу:
- неймспейс типа не покрыт extra.corex.public-api владельца
- сегмент неймспейса не содержит \Internal\
- docblock типа не содержит @internal
```

Текущие нарушители — снимок
`scripts/package-graph-undeclared-surface.json` (сортированный список
`package|fqcn`). Гейт блокирует только тип, **отсутствующий** в снимке.
Удаление из снимка без пометки `@internal` / переноса в Internal /
добавления в `public-api` — красное (ratchet, не baseline-долг).
Добавление в снимок диффом без причины в `reason` запрещено Implementation
Rules: снимок растёт только вместе с именованной записью в receipt.

Нативная PHPStan-проверка `@internal` **не** включается (D12, D17).

### 5.3 R5 — направление package-type

Ранги: `foundation=0`, `capability=1`, `integration=2`, `product=3`.
`tooling` и `fixture` в R5 не участвуют (не runtime-цепочка).
Ребро `from → to` легально при `rank(from) >= rank(to)`. Иначе:

`R5 {file}: {from} ({type}) не вправе зависеть от {to} ({type})`.

Отсутствие `extra.corex.package-type` у живого пакета — violation R5
`missing package-type` (после P1.2 ключ есть у всех 20).

### 5.4 Негативные проверки

| Правило | Посадка | Ожидаемый фрагмент вывода |
|:--|:--|:--|
| R4 | `use CoreX\Tenancy\Support\DoesNotExist;` из `packages/core` | `R4` + имя файла + `непубличного` |
| R2 vs R4 | `use CoreX\Tenancy\Internal\X;` | содержит `foreign Internal`, **не** содержит `непубличного` |
| R4b | новый `final class AccidentalPublic {}` в `packages/core/src/Support/` без `@internal` | `R4b` + имя файла |
| R5 | `use Flex\Crm\Contracts\X;` из `packages/core` | `R5` + `foundation` + `product` + имя файла |

Посадки удаляются после прогона. Регрессия: `package-graph-gate: OK` на
чистом дереве (с учётом нового allowlist, если он не пуст).

`discoverPackages` дополняется чтением `extra.corex` (`package-type`,
`public-api`, `stability`). Невалидный блок — EXIT_CONFIG, не молчание
(D11: отсутствие и невалидность различны).

---

## 6. Roave BC-дифф (item P3.4)

`RAG:✅` P3-Q2, P3-Q6. D18.

### 6.1 Зависимость

Корневой `composer.json` `require-dev`:
`"roave/backward-compatibility-check": "^8.22"`.

### 6.2 Wrapper `scripts/bc-check.sh`

`RAG:✅` version-pinned source Roave 8.22.0: the command has only
`--from`, required `--to` and `--format`; it resolves the git repository and
Composer configuration from CWD and returns exit 3 only for detected BC changes.

The wrapper therefore does **not** pass a package positional argument and does not
compare the ambient `HEAD`. It creates an isolated temporary git repository per live
package with two explicit commits:

1. `base`: package bytes from `BC_CHECK_BASE` and a generated root comparison
   `composer.json` whose production autoload maps only that package;
2. `candidate`: the current worktree snapshot of the same package, including tracked
   modifications, deletions and non-ignored untracked files, with the corresponding
   generated comparison Composer root.

The snapshot is built with an alternate temporary git index/`write-tree` and never
touches the source index, worktree or refs. The comparison root retains the dependency
closure needed by Composer path repositories but exposes only the selected package as
the source autoload. Roave runs from the temporary repository root:

```bash
vendor/bin/roave-backward-compatibility-check \
  --from="${base_commit}" \
  --to="${candidate_commit}" \
  --format=github-actions
```

Exit classification is closed: 0 = compatible; 3 = semantic BC break; every other
exit, signal, missing report or Composer/reflection/config failure = infrastructure
RED. Only exit 3 may be report-only for an `experimental` package. A missing base
package is the explicit `new-package` outcome; a missing/unresolvable base revision is
infrastructure RED, not first-history GREEN. All temporary state is removed in `trap`.

### 6.3 CI

Новый job `backward-compatibility` в `.github/workflows/ci.yml`:
PHP 8.4, `fetch-depth: 0`, `composer update`, `bash scripts/bc-check.sh`.
Не матрица 8.3. `contract-guard.sh` не удаляется.

### 6.4 Негативная проверка

В текущем worktree временно удалить публичный метод у интерфейса в
`packages/core/src/Contracts/` и запустить wrapper **до restore**. Candidate commit
обязан содержать это незакоммиченное удаление. Для `experimental` классифицированный
exit 3 печатает report-only и даёт общий exit 0; с
`BC_CHECK_FORCE_STABLE=corex/core` тот же candidate даёт общий exit ≠ 0 и имя файла.
Отдельная посадка ломает generated comparison Composer config и обязана дать
infrastructure RED даже для experimental. Restore в `try/finally`; после обоих
посадок исходные index/worktree/refs байт-в-байт не изменены.

До 1.0 все пакеты experimental — джоб зелёный при наличии отчёта. Это не
ложно-зелёное: blocking-условие названо и проверяется force-stable посадкой.

---

## 7. DurablePayload (item P3.5)

Эскиз §3.2 **принимается** с уточнениями P3-Q3: проверяются объявленные
свойства (включая promoted), не runtime-граф значений. `array<mixed>`
deny-list'ом не ловится и честно назван пределом в receipt; allow-list
скаляров — P4, когда появятся реальные payload-типы.

Ни один существующий класс **не** помечается маркером в P3 (DataScope —
P4).

### 7.1 Маркер — готовый файл

`packages/core/src/Contracts/DurablePayload.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Тип пересекает durable-границу: очередь, outbox, чекпойнт, wakeup, bulk.
 *
 * @see RUN-002 RUN-003
 */
interface DurablePayload {}
```

### 7.2 Правило — готовая форма

Класс `CoreX\PHPStan\Rules\DurablePayloadRule` в
`packages/core/src/PHPStan/Rules/DurablePayloadRule.php`.
`getNodeType()` = `PhpParser\Node\Stmt\Property`.
Identifier: `corex.durablePayload.nonSerializableProperty`.

Запрещённые FQCN (закрытый список):

- `Illuminate\Database\Eloquent\Model`
- `Closure`
- `Illuminate\Container\Container`
- `Illuminate\Contracts\Container\Container`
- `Psr\Container\ContainerInterface`
- `Illuminate\Http\Request`
- `Symfony\Component\HttpFoundation\Request`
- `CoreX\Tenancy\Contracts\TenantContext`

Сообщение:
`Durable payload {class}::${prop} must not contain {forbidden}`.

Класс, не реализующий маркер, молчит даже с Model-свойством.

`packages/core/extension.neon`:

```neon
services:
    -
        class: CoreX\PHPStan\Rules\DurablePayloadRule
        tags:
            - phpstan.rules.rule
```

Корневой `phpstan.neon` `includes` получает `- packages/core/extension.neon`
рядом с modules/tenancy.

### 7.3 Негативная проверка

`tests/PHPStan/DurablePayloadRuleTest.php` по образцу
`tests/PHPStan/ModuleBoundaryRulesTest.php` (`RuleTestCase`).
Фикстура `tests/PHPStan/data/durable-payload.php`:

- `ValidPayload implements DurablePayload` со `string`/`int` — 0 ошибок;
- четыре invalid: Model, Closure, Container, TenantContext — ошибка на
  строке свойства, identifier в объекте ошибки;
- `NotAPayload` с Model — 0 ошибок;
- promoted `public TenantContext $ctx` — ошибка (не пропустить promoted).

`composer analyse` на чистом дереве остаётся зелёным: маркер ещё никто не
реализует.

---

## 8. Словарь имён и `config()` (item P3.6)

### 8.1 Naming gate

Источник — `P1-design.md` §5.3: гейт ловит **появление нового** имени, не
существующий долг. Скоуп — top-level типы в неймспейсе из `public-api`
пакета (публичные сигнатуры, A82).

Запрещённые шаблоны короткого имени (новое):

- точное `Dep`, `Helper`, `Util`, `Common`, `Misc`, `Processor`,
  `BaseService`, `AbstractManager`, `DataManager`;
- суффиксы `Info`, `Manager`, `Service`, `Helper`.

Снимок текущих совпадений: `scripts/naming-gate-grandfather.json`
(сортированный список FQCN). Новое имя, которого нет в снимке — blocking.
Легальные названные случаи P1 (`CurrencyInfo`, `SearchManager`) входят в
снимок на момент прогона.

CLI: `php scripts/naming-gate.php`. Composer-скрипт `"naming-gate"`.

Негативная проверка: посадить
`packages/core/src/Contracts/ProbeHelper.php` `final class ProbeHelper {}`
(Contracts ∈ public-api ядра). Exit ≠ 0, вывод содержит `ProbeHelper.php`
и `N1`. Удалить файл. Снимок не пополняется этой посадкой.

### 8.2 Observational `config()` (эскиз §3.3 принимается)

Allowlist путей в `scripts/composition-boundary.json`:

```json
{
  "allow_globs": [
    "*/src/*ServiceProvider.php",
    "*/src/**/Commands/*.php",
    "*/src/Console/*.php",
    "packages/core/src/Support/Config.php"
  ],
  "reasons": {
    "packages/core/src/Support/Config.php": "N12: boot-адаптер, чистит P7"
  }
}
```

`packages/core/src/Support/Config.php` — именованное исключение (N12), не
молчаливый пропуск. Остальные 152 вызова печатаются.

CLI: `php scripts/config-composition-gate.php`. Находит `config(` с
литералом в `packages/*/src`. Совпадение allow-glob — skip. Иначе строка
`C1 {file}:{line} config()`. Exit всегда 0 в P3. P7 сменит exit на 1 тем
же скриптом (один бит: `--strict` или ключ JSON `blocking: false`).

Негативная проверка: посадить `config('probe-key')` в
`packages/core/src/Support/ConfigCompositionProbe.php`. Вывод содержит
имя файла; exit 0. Удалить файл.

Pest-тест не должен требовать exit 1 от observational-гейта.

---

## 9. Достигнутые инварианты и каталог таблиц (item P3.7)

### 9.1 A57, A61, A99

Arch-тест `tests/Arch/AchievedInvariantTest.php`, сканер —
существующий `CoreX\Tests\Support\SourceScanner`.

- **A57:** в `packages/attributes/src` нет `use CoreX\Search\` и нет
  `Meilisearch`. Негатив: посадить `use CoreX\Search\Contracts\SearchProvider;`
  в probe-файл attributes — тест красный с путём файла; удалить.
- **A61:** реализации `SearchProvider` в `packages/search/src` и
  `packages/search-meilisearch/src` не импортируют
  `Illuminate\Database\Eloquent`. Контракты
  `SearchProvider` / `SearchSchema` / `SearchDocuments` / `IndexDriver` /
  `SearchHydrator` существуют как раздельные файлы в
  `packages/search/src/Contracts/`. Негатив: probe-импорт Eloquent в
  класс, реализующий `SearchProvider`.
- **A99:** `packages/api/src/Internal/TrustedContextResolver.php`
  не содержит `$request->input`, `$request->get(`, `$request->query(`
  и литералов `tenant_id` / `account_id` в чтении request. Резолв идёт
  через `TenantContextResolver::current()` (`RAG:—`, чтение файла).
  Негатив: посадить `$request->input('tenant_id')` в копию логики
  нельзя в production-файл насовсем — только временная строка с restore,
  как PackageGraphTest.

### 9.2 T1/T2/T4 (D19)

CLI: `php scripts/table-ownership-gate.php`.
Каталог-вход: `docs/architecture/table-ownership.md` (продукт P2.3).
До исполнения P2 файла нет — Validation item'а требует, чтобы P2.3 был
терминален (D14 это гарантирует к стадии 3).

- **T1:** каждая `Schema::create('name'` в
  `packages/*/database/migrations/**` имеет ровно одну строку владельца
  в каталоге.
- **T2:** пакет миграции = владелец таблицы.
- **T4:** `foreign(` / `constrained(` в миграции, ссылающиеся на таблицу
  другого владельца, кроме
  `aut_role_assignments.workspace_id → wsp_workspaces.id` и
  `aut_role_assignments.granted_by → users.id`.

DATA-003 новым гейтом не покрывается.

Негатив T1: probe-миграция с `Schema::create('zzz_probe')` без строки
каталога — вывод `T1` + имя файла, exit 1; файл удалить.
Негатив T4: probe `foreign('x')->references('id')->on('wsp_workspaces')`
в миграции `core` — `T4` + имя файла.

---

## 10. Карта items → параграфы

| Item | Параграфы этого документа |
|:--|:--|
| P3.1 | §0, §3 |
| P3.2 | §0, §4 |
| P3.3 | §0, §5 |
| P3.4 | §0, §6 |
| P3.5 | §0, §7 |
| P3.6 | §0, §8 |
| P3.7 | §0, §9 |

---

## 11. Границы фазы — что исполнитель делать не вправе

1. Не чинит production-код пакетов, чтобы гейт зазеленел, кроме посадок,
   которые сам же удаляет в негативной проверке.
2. Не вводит Deptrac, PHPat, class-leak, php-parser (D17).
3. Не включает нативный PHPStan `@internal` как межпакетный firewall (D12).
4. Не помечает существующие классы `DurablePayload` (это P4).
5. Не переводит config-гейт в `--strict` (это P7).
6. Не понижает PHPStan level, не добавляет `baseline.neon`.
7. Не меняет существующие миграции (DATA-005).
8. Не исполняет items до конца стадии 2 (D14).
9. Не копирует текст и код из `audits/*`.
10. Не обещает полный DATA-003 (D19).
