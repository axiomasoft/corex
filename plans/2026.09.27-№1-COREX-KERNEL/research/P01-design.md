# P01 — досье дизайна: окружение, платформа, страховочная сетка

Норма для исполнителей P01.1–P01.4. Факты — `artifacts/P01-design/facts/README.md` (F1–F6, RAG:—); внешние факты —
`research/P01-design-rag.md` (E1–E7, RAG:✅); готовый код и эталонные выходы — `artifacts/P01-design/examples.md`.
Дополнение D41 — inline audit corrections; доказательства `findings/design-audit-autofix-2026-09-28-evidence.json`.

Решения — D36 (манифесты), D37 (стек и ratchet), D40 (golden, заменяет D38), D39 (ownership и CI). `$P` = `plans/2026.09.27-№1-COREX-KERNEL`.

## 1 Карта кода

| Файл / каталог | Сейчас | Item | Действие |
|:--|:--|:--|:--|
| `composer.json` (root) | `php ^8.3`, `minimum-stability: dev`, 16×`@dev`, pest `^4.0` | P01.1 | D36/D37: php, stability, `^1.0@dev`, + `corex/recurrence`, `corex/search`, stancl-пины; стек |
| `packages/*/composer.json` (20) | `php ^8.3` (tenancy `^8.4`), `@dev`, нет branch-alias | P01.1 | D36: branch-alias, `^1.0@dev`, closure, path-repos; access → `^12.0\|^13.0`, testbench `^10.12\|^11.3` |
| `composer.lock` | platform `^8.3`, pest 4.7.5, phpunit 12.5.30 | P01.1 | перегенерировать под PHP 8.4 (`scripts/php84 composer update`) |
| `scripts/php84` | нет | P01.1 | новый: PATH-шим `php → php8.4`, `--check` (§4.1, examples §1) |
| `phpunit.xml` | PG env `PGSQL_PORT=5434` | P01.1 | не меняется; локально `PGSQL_PORT=5432` |
| `docs/` (установка/разработка) | PHP 8.3 | P01.1 | упоминания требований → PHP 8.4, `scripts/php84` (grep `8\.3` в `docs/`, `README.md`, `packages/*/README.md`) |
| `tests/Golden/**` | нет | P01.2 | новый сьют `Golden` (§4.2, examples §3) |
| `tests/Golden/rename-map.json` | нет | P01.2 | генерирует plan-owned `$P/artifacts/P01-design/scripts/golden-rename-map.py` (готов, проверен: 666 class / 16 package / 3 ambiguous) |
| `phpunit.xml` testsuite `Golden` | нет | P01.2 | добавить; `composer golden` |
| `scripts/ownership-gate.php`, `scripts/ownership-map.json`, `scripts/ownership-gate-baseline.json` | нет | P01.3 | новый гейт (§4.3, examples §4) |
| `packages/*/phpstan.neon` (18 есть, core/modules нет) | level 6, `includes: vendor/larastan…` | P01.3 | единый шаблон level max (examples §5) + `phpstan-baseline.neon` |
| `phpstan.neon` (root) | агрегат level 6 | P01.3 | агрегат `paths` пакетов удаляется; root-конфиг анализирует только `tests/Support`, `tests/Golden`, `scripts/*.php` на level max с baseline (ключ `phpstan.root` в ceilings) |
| `scripts/manifest-check.py` | нет | P01.1 | копия `$P/artifacts/P01-design/scripts/manifest-check.py` (продуктовый гейт, без ссылок на план); `composer manifests:check` |
| `scripts/phpstan-contour.php`, `composer analyse:packages` | PLAN2-инструмент | P01.3 | удалить |
| `scripts/phpstan-packages.sh` | нет | P01.3 | новый цикл по пакетам + guard |
| `scripts/quality-ceilings.json`, `scripts/quality-ceiling-guard.php` | нет | P01.3 | новый ratchet (examples §6) |
| `packages/*/composer-dependency-analyser.php`, `scripts/dependency-analyser.sh` | нет | P01.3 | новый (examples §7) |
| `scripts/anti-weakening-gate.sh:71–76` | запрет baseline | P01.3/P01.2 | заменить на: рост `quality-ceilings.json`, правка `ownership-gate-baseline.json` с добавлением записей, правка `tests/Golden/baseline/**` без строки `REBASELINE.md` → fail |
| `.github/workflows/{ci,distribution-proof,remaining-cohort-proof}.yml` | теги `@v4/@v2`, нет permissions/audit | P01.4 | D39 §3, examples §8 |
| `.github/{dependabot.yml,CODEOWNERS,zizmor.yml}` | нет | P01.4 | новые (examples §8) |
| `composer.json` scripts | `analyse`, `analyse:packages`, … | P01.2–P01.4 | + `golden`, `ownership-gate`, `quality:ceilings`, `deps:analyse`, `manifests:validate`; `analyse` → `bash scripts/phpstan-packages.sh` |

Вне P01: имена/namespace/таблицы (P02/P03), Rector-сеты PHP 8.4 (P09), SBOM/release (P09), stancl/azguard (P07).

## 2 Предвычисленные факты и скрипты

1. Окружение: F1 — PHP 8.4.26 есть без 10 расширений; PG 16 на 5432 без роли `corex`; Docker недоступен.
2. Манифесты: F2; полный перечень нарушений контракта D36/D37 — `python3 $P/artifacts/P01-design/scripts/manifest-check.py`
   (сейчас exit 1, **155** строк; исторический F2 = 154, новый golden-closure = 1: branch-alias 20, closure 4, illuminate 2, mono-constraint 57, path-repo 2, php 20,
   prerelease-root 2, stability 21, stack 26). Это рабочий список P01.1; цель — `manifests OK: 21 roots`.
3. Замыкание и прототип Composer: F2/F3 (root без `corex/recurrence`, `corex/search`; `modules`/`search-intl` без
   path-repo; `search-intl`/`tenancy` без `corex/modules`).
4. PHPUnit 13 removals: 0 использований (F4) — апгрейд Pest 4 → 5 механический.
5. CI: F5 — 60 `uses:` двух actions; 20 jobs; PHP 8.3 в матрице; PG 16.
6. Метрики тестов: `python3 $P/artifacts/P01-design/scripts/junit-summary.py <lane>=<xml> [--compare <json>]`.

## 3 Развилки (все решены)

| # | Развилка | Решение | Где |
|:--|:--|:--|:--|
| F1 | PHP 8.4: docker или хост | хост `php8.4` через PATH-шим `scripts/php84`; Docker недоступен (F1) | D37, §4.1 |
| F2 | Как вызывать 8.4 из Composer-скриптов (`#!/usr/bin/env php`) | шим-каталог `build/php84/bin/php → $(command -v php8.4)` первым в `PATH`, затем `exec "$@"` | examples §1 |
| F3 | PG локально | системный PG 16, `PGSQL_PORT=5432`, роль/БД создаёт владелец (O-2) | D37 п.5 |
| F4 | Форма межпакетных ограничений | `branch-alias 1.0.x-dev` + `^1.0@dev`, stable везде, замыкание в корне | D36 |
| F5 | Стек | Pest 5.2 / PHPUnit 13 / Testbench 10.12\|11.3 / PHPStan 2.2 / Larastan 3.12 / Pint 1.32 | D37 п.1 |
| F6 | Golden vs переименования P02 | замороженный спроецированный baseline + симметричный `rename-map.json` | D40 (заменяет D38) |
| F7 | Ownership v2: гейт сразу блокирующий? | ratchet по baseline; `--strict` к закрытию P02 | D39 |
| F8 | PHPStan level max сразу | да, per package, baseline + потолок только вниз | D37 п.2–3 |
| F9 | Где запускать analyser | standalone-установка пакета, бинарь из root | D37 п.4 |
| F10 | Root `phpstan.neon` | агрегат пакетов удаляется; root анализирует `tests/Support`, `tests/Golden`, `scripts/*.php` (level max + baseline) | §4.3 |
| F11 | Roave | report-only до rc | D17, D37 п.6 |
| F12 | SBOM | P09 (release-job) | D39 п.4 |
| F13 | CODEOWNERS | `@academici` | D39 |
| F14 | Live CI | owner push после P01.4 — внешняя граница фазы, не item | Phase |

## 4 Runbooks

Формат: `команда` → ожидаемо → при расхождении. Все PHP-команды — через `scripts/php84` (после P01.1 шаг 2).
Локально перед PG-командами: `export PGSQL_PORT=5432`.

### 4.1 P01.1 — окружение, baseline, платформа

1. `bash -c 'php8.4 -v'` → `PHP 8.4.x`. Иначе → стоп, эскалация O-1.
2. Создать `scripts/php84` (examples §1), `chmod +x`. `scripts/php84 --check` → `php84 OK: 8.4.x, extensions complete`.
   Иначе печатает `MISSING: …` и строку `sudo apt install …` → стоп, эскалация O-1 (установка системного ПО — owner-gate).
3. `pg_isready -h 127.0.0.1 -p 5432` → accepting; `PGPASSWORD=corex psql -h 127.0.0.1 -U corex -d corex_test -tAc 'select 1'`
   → `1`. Иначе → стоп, эскалация O-2 (команды — §7).
4. **Baseline на исходных манифестах** (до правок): `python3 scripts/distribution/stage-stancl-providers.py` (регистрирует
   `repositories.stancl-providers` в `composer.json` root и tenancy — после каждого install/update снимать ключ:
   `composer config --unset repositories.stancl-providers` и то же с `--working-dir=packages/tenancy`; ключ никогда не
   коммитится), `scripts/php84 composer install --no-interaction` → exit 0.
   Затем:
   ```bash
   mkdir -p build/p01
   scripts/php84 vendor/bin/pest --exclude-group=pg --exclude-group=meilisearch --exclude-testsuite=SearchMeilisearchPackage,TasksWorkflowPackage --log-junit build/p01/sqlite.xml
   scripts/php84 vendor/bin/pest --testsuite=TasksWorkflowPackage --exclude-group=pg --log-junit build/p01/tasks-workflow.xml
   PGSQL_PORT=5432 scripts/php84 vendor/bin/pest --group=pg --log-junit build/p01/pg.xml
   python3 $P/artifacts/P01-design/scripts/junit-summary.py sqlite=build/p01/sqlite.xml tasks-workflow=build/p01/tasks-workflow.xml pg=build/p01/pg.xml
   scripts/php84 vendor/bin/phpstan analyse --memory-limit=1G --error-format=json > build/p01/phpstan-l6.json || true
   ```
   Записать `$P/artifacts/P01/baseline-metrics.json`: `{commit, php, lanes:{…из junit-summary…}, phpstan_level6_errors,
   failing_tests:[имена]}`. Падения на 8.4 **до** апгрейда — не блокер: они перечисляются и исправляются в шаге 7.
5. Правки манифестов по D36/D37 до `python3 $P/artifacts/P01-design/scripts/manifest-check.py` → `manifests OK: 21 roots`.
   Порядок: (a) все пакеты — `php ^8.4`, `minimum-stability: stable`, `prefer-stable: true`, `extra.branch-alias`;
   (b) межпакетные `@dev` → `^1.0@dev`, добавить недостающее замыкание и path-repos (§2 п.3); (c) access `illuminate/*`
   → `^12.0|^13.0`, `orchestra/testbench` → `^10.12|^11.3`; (d) стек D37 п.1 во всех `require-dev`, где пакет уже есть
   (не добавлять инструменты туда, где их нет, кроме root); (e) root: `corex/recurrence`, `corex/search`,
   `axiomasoft/stancl-tenancy: 4.0.0-alpha1`, `axiomasoft/stancl-jobpipeline: 2.0.0-alpha1`; root require-dev также
   `corex/access: ^1.0@dev`, `axioma-studio/azguard-core: ^0.2@dev` + VCS из access (golden всех пакетов требует
   upstream интерфейсы access; D36 уже разрешает это исключение в installable-корне). tenancy — те же пины уже
   есть. Каждый `composer.json` — `composer validate --strict --no-check-lock --working-dir=<dir>` → `is valid`.
   Скопировать скрипт в `scripts/manifest-check.py` (docstring без путей плана) и добавить composer-скрипт
   `"manifests:check": "python3 scripts/manifest-check.py"`.
6. `python3 scripts/distribution/stage-stancl-providers.py && scripts/php84 composer update --no-interaction` → exit 0;
   затем снять `repositories.stancl-providers` (шаг 4). `composer.lock` коммитится (content-hash может не совпасть из-за
   снятого ключа — это ожидаемо, D36 п.4). Затем `stage-stancl-providers.py --portable-locks` сохраняет
   локальные ZIP-пути относительно checkout. При конфликте разрешения → §5 K2.
7. Прогоны: `scripts/php84 composer test` → exit 0; `PGSQL_PORT=5432 scripts/php84 composer test:pg` → exit 0;
   `scripts/php84 composer test:packages` → exit 0 (standalone-пакеты ставят свой vendor; staging stancl нужен для
   tenancy — повторить `stage-stancl-providers.py` и затем убрать ключ); `scripts/php84 composer lint:check` → exit 0;
   `scripts/php84 composer analyse` → ошибок не больше, чем `phpstan_level6_errors` baseline. Сверка:
   `junit-summary.py … --compare $P/artifacts/P01/baseline-metrics.json` → exit 0.
8. Laravel 12-лейн в scratch-worktree: `git worktree add ../corex-p01-l12 HEAD` (после коммита шагов 5–7),
   в нём `scripts/php84 composer require "illuminate/contracts:12.*" --no-update`, затем
   `scripts/php84 composer require --dev "pestphp/pest:^4.7" --no-update` (D42), staging и
   `scripts/php84 composer update --with-all-dependencies`; снять временные repositories, выполнить
   `stage-stancl-providers.py --portable-locks`; `scripts/php84 composer test` → exit 0; затем удалить worktree.
9. Docs: `grep -rn "8\.3" README.md docs docs-public packages/*/README.md packages/*/PACKAGE.md` → каждое требование
   PHP 8.3 → 8.4; команда разработки — `scripts/php84`. `composer package-docs-gate` → exit 0.

### 4.2 P01.2 — golden

1. `python3 $P/artifacts/P01-design/scripts/golden-rename-map.py` → `rename-map written: 666 class, 16 package,
   3 ambiguous, 0 manual`; `… --check` → `rename-map OK: …`. Правила — точные токены (examples §3.1): применяются к
   каждой строке и строковому ключу JSON baseline **и current** токенизацией по `[A-Za-z_]\w*(?:\\[A-Za-z_]\w*)+` (FQCN) и точным сравнением
   composer-имён в полях `owner`/`module`/`package`; префиксная замена запрещена (psr-4 folding делает её неверной).
   P02/P03 добавляют правила в `manual` (генератор их сохраняет).
2. Коллектор `tests/Golden/Support/GoldenSnapshot.php` (examples §3.2): четыре слоя, каждый — список записей с ключом
   сортировки; JSON — `JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR`, ключи отсортированы рекурсивно.
   - `php-surface`: все объявленные классы/интерфейсы/трейты/enum из `packages/*/src/**/*.php`: FQCN извлекается
     через `token_get_all(TOKEN_PARSE)`, включая classmap и несколько namespace в файле; из пути имя не выводится; процессный classmap коллектор строит по файлам деклараций, включая `Internal` с флагом `internal: true`; для каждого: `kind`, `final`, `abstract`,
     `readonly`, `parent`, `interfaces` (sorted), `constants` (public, declared in class: name→{value, type}; type =
     `get_debug_type(value)`, значение обязательно), `properties` (public, declared in class:
     name→{name, type, static, readonly}), `methods` (public, declared in class: name, static, params [name, type, default?,
     variadic, byRef], return type). Типы — `ReflectionType::__toString()` с FQCN.
     `default` записывается при `isDefaultValueAvailable()`, включая `null`; иначе ключ отсутствует (`optional` не замена
     default). Значения constants/default — scalar/array рекурсивно; enum → `{enum: FQCN, case: name}` + `value` для backed enum; object-default →
     `{class: FQCN, properties: public-values}` без object-id. Все перечисленные поля обязательны, не `ADAPT`.
   - `routes`: `Route::getRoutes()` → `{methods, uri, name, action (Class@method|Closure)}` без middleware-замыканий.
   - `events`: классы `extends CoreX\Events\DomainEvent` (из php-surface) → `{class, name: Class::name()}`; плюс
     `MessageContractRegistry` из контейнера, если привязан → все контракты (`type`, `version`, поля схемы).
   - `wire`: `$this->artisan('corex:api-snapshots', ['directory' => $tmp, '--fixture' => $root.'/tests/fixtures/public-error-contract.php'])` → содержимое `errors.json`,
     `operations.json`, `openapi.json`, `webhooks.json`; поля `contract_fingerprint`, `registry_fingerprint`,
     `files.*` (хэши) остаются в `manifest.json`, который вообще не читается. Производный `operations[*].contract_hash`
     исключён только у записей операций: hash зависит от module/registry identities, его semantic inputs остаются в записи.
     Command exit проверяется; отсутствие любого из четырёх файлов — ошибка; tmp удаляется в finally.
   Normalizer сортирует interface names после rename и вычисляет `internal` по projected class key (производные от имени).
   Порядок params/default arrays сохраняется; поля API/schema с именами files/generated_at/contract_hash не исключаются.
3. `tests/Golden/GoldenTest.php`: TestCase — корневой `CoreX\Tests\TestCase` + все service providers пакетов корня
   (список — `extra.laravel.providers` каждого пакета, собрать при создании; tools-провайдер как в
   `tests/Feature/OperationContractTest.php:49–52`; `AzGuard\AzGuardServiceProvider` перед CoreAuth provider как в
   `packages/access/tests/Support/CoreAuthTestCase.php`). Тест на слой: `expect(normalize(apply(map, current)))->toBe(normalize(apply(map,
   baseline)))` (D40); при `GOLDEN_UPDATE=1` — пишет `normalize(apply(map, current))` в baseline и успешно завершает тест
   с сообщением «baseline written». Неизменная карта идемпотентна; оба снимка имеют конечные имена до и после переноса.
4. Создание baseline: `GOLDEN_UPDATE=1 scripts/php84 vendor/bin/pest --testsuite=Golden` → 4 файла
   `tests/Golden/baseline/{php-surface,routes,events,wire}.json`; повтор без флага → 4 passed; двойной прогон даёт
   идентичные байты (`sha256sum` до/после) — иначе недетерминизм → §5 K5.
5. `tests/Golden/REBASELINE.md` — таблица `| Дата | D# | Слои | Причина |` с первой строкой `2026-… | PLAN3.D40 | all |
   initial P01 baseline` (действующее решение — PLAN3.D40).
6. Anti-weakening (examples §6.3): дифф `tests/Golden/baseline/**` без новой строки в `REBASELINE.md` → fail.
7. Негативный контроль (все — в scratch, откатить `git checkout`): (a) поменять URI одного маршрута в
   `packages/operations/routes/*` → `Golden` routes fail; (b) переименовать публичный метод класса пакета → php-surface
   fail; (c) переименовать namespace одного класса **и** добавить правило в `manual` rename-map → pass; без правила →
   fail; (d) поменять код ошибки в descriptor → wire fail; (e) сменить тип публичного свойства, значение или тип
   публичной константы, default параметра (отдельные прогоны; также отсутствие default ↔ `null`) → php-surface fail.
   Позитивный контроль: старый и конечный FQCN из `generated` сравниваются зелёным с неизменным baseline и картой;
   `apply(apply(snapshot)) == apply(snapshot)` на всех слоях. Результат — Completion Notes.
8. `composer.json` script `"golden": "vendor/bin/pest --testsuite=Golden"`; корневой `test` исключает `Golden`
   (`--exclude-testsuite=…,Golden`), CI гоняет его отдельным job (P01.4).

### 4.3 P01.3 — ownership v2, PHPStan max, dependency analyser

**Ownership.**
1. `scripts/ownership-map.json` (examples §4.1): `packages` — для каждого текущего composer-name: `dir`, `target`
   (из `package-map.tsv` `new_name`), `root` (`new_root`), `config`, `tag_prefix`, `env_prefix`, `command_prefix`
   (из glossary §Identifiers per package: `corex-access` → tags `corex-access-`, env `COREX_ACCESS_`, commands
   `corex:access:`); `kernel` — `target: axiomasoft/corex`, `modules` (15 из §Kernel modules), `root_symbols`,
   `reserved`, `dev_roots`. Генерируется вручную один раз, сверяется с glossary в ревью.
2. Инвентарь (регулярные выражения — examples §4.2):
   - `namespace`: `autoload{,-dev}.psr-4` ключи пакета + фактический `namespace` каждого `*.php` в `src/`;
   - `table`: `Schema::create('<t>'` / `Schema::connection(...)->create('<t>'` в `packages/*/database/migrations/**`;
   - `config`: `mergeConfigFrom(<path>, '<key>')` в `src/**`;
   - `publish-tag`: второй аргумент `$this->publishes([...], '<tag>')` и `publishesMigrations([...], '<tag>')`;
   - `command`: `protected $signature = '<name>` (первое слово) и `#[AsCommand(name: '<name>')]`;
   - `env`: `env('<NAME>'` в `packages/*/config/**`.
3. Правила: O1 — идентификатор в ≥2 пакетах; O2 — `config` ≠ `config` карты, `publish-tag` не начинается с
   `tag_prefix` и не равен `<config>-config`/`<config>-migrations…`, `env` без `env_prefix`, `command` без
   `command_prefix`, namespace-корень ≠ `root` (для ядра — первый сегмент ∉ modules ∪ root_symbols ∪ reserved ∪
   dev_roots → O3); O4 — таблица: владелец в `docs/architecture/table-ownership.md` ≠ пакет миграции.
4. Вывод на нарушение: `O2 config corex/access corex-auth expected corex-access` (одна строка, стабильная сортировка).
   Baseline — JSON-список этих строк. `php scripts/ownership-gate.php --report > build/ownership.json`,
   `php scripts/ownership-gate.php --write-baseline` (только в P01 и при сокращении; добавление строк в диффе ловит
   anti-weakening), `php scripts/ownership-gate.php` → `ownership OK: <N> identifiers, <B> baselined`.
5. Негативный контроль: (a) добавить в `packages/tasks/src/...Provider` `mergeConfigFrom(..., 'corex-media')` → O1 и O2
   exit 1; (b) удалить одну строку baseline, соответствующую живому нарушению → exit 1; (c) добавить в baseline
   несуществующую строку → exit 1 (`stale`); (d) `--strict` на текущем коде → exit 1 с числом = размер baseline.

**PHPStan.**
6. Root dev: `phpstan/extension-installer`, `phpstan/phpstan-strict-rules`; `config.allow-plugins.phpstan/extension-installer: true`.
   `packages/{core,modules,tenancy}/composer.json`: `extra.phpstan.includes: ["extension.neon"]`; удалить из всех
   `phpstan.neon` строки `includes: - vendor/larastan/...` и `packages/*/extension.neon`.
7. Шаблон `packages/<p>/phpstan.neon` (examples §5) для всех 20 пакетов (core/modules — создать; paths: `src`,
   `database/migrations` если есть; `tmpDir: ../../build/phpstan/<p>`). Уникальные `ignoreErrors` текущих
   конфигов (intl/search-intl — F6) не переносить: baseline их поглощает.
8. Для каждого пакета: `scripts/php84 vendor/bin/phpstan analyse -c packages/<p>/phpstan.neon --generate-baseline
   packages/<p>/phpstan-baseline.neon --memory-limit=2G` → exit 0; повтор без флага → `[OK] No errors`.
   Root `phpstan.neon` → шаблон с paths `tests/Support`, `tests/Golden`, `scripts` (только `*.php`), baseline
   `phpstan-baseline.neon`.
9. Создать `scripts/quality-ceilings.json` из examples §6.1 (пустые словари `phpstan`/`dependencies` при инициализации).
   `scripts/quality-ceiling-guard.php --tighten` пишет `scripts/quality-ceilings.json` (факт); повтор без флага →
   `ceilings OK`. Негатив: удалить одну запись из baseline любого пакета (ошибка вернётся) → phpstan exit 1; вручную
   +1 к `count` baseline → guard exit 1 (`phpstan.<p>: 124 > ceiling 123`).
10. `scripts/phpstan-packages.sh` (examples §5.2): цикл по `packages/*/phpstan.neon` + root, затем guard;
    `composer analyse` → этот скрипт. Удалить `scripts/phpstan-contour.php` и `analyse:packages`; поправить ссылки
    (`grep -rn "phpstan-contour\|analyse:packages" scripts tests docs .github composer.json`).

**Dependency analyser.**
11. Root dev `shipmonk/composer-dependency-analyser ^1.8.4`. На пакет — `composer-dependency-analyser.php` (examples §7):
    `src` (prod), `tests`, `database` (dev, если есть). `scripts/dependency-analyser.sh <dir>|--all`: `composer
    --working-dir=packages/<dir> update --no-interaction --no-progress` (stage stancl для tenancy), затем снять временный repository в root **и** tenancy даже при провале update; при провале update не запускать analyser.
    `vendor/bin/composer-dependency-analyser --composer-json=packages/<dir>/composer.json
    --config=packages/<dir>/composer-dependency-analyser.php`.
12. Для каждого пакета: shadow-зависимость → объявить в `require`/`require-dev` (ограничение — текущая заблокированная
    мажорная версия `^X.Y`), повторить `manifest-check.py`; unused — удалить из манифеста, если нет не-PHP использования
    (config/provider discovery); иначе и для архитектурных нарушений — ignore с `// ratchet: <фаза> <причина>`
    (например `// ratchet: P07 notifications→wakeup, D16`). Цель — exit 0 на всех 20.
13. Guard считает `ratchet:` на пакет → `dependencies.<dir>` в ceilings.
14. Anti-weakening (examples §6.3) — заменить строки 71–76.

### 4.4 P01.4 — CI

1. Инструменты локально: `python3 -m venv build/tools/venv && build/tools/venv/bin/pip install zizmor==1.30.1`;
   pinact: скачать `pinact_linux_amd64.tar.gz` v5.0.0 из GitHub release + `pinact_*_checksums.txt`, `sha256sum -c` →
   OK, распаковать в `build/tools/bin/`.
2. `build/tools/bin/pinact run` → все `uses:` получают `@<40-hex> # vX.Y.Z`. Обновить до актуальных мажоров (E6):
   `actions/checkout` v7, `shivammathur/setup-php` 2.37.x. Проверка: `bash -c 'uses=$(grep -hE "^\s*-?\s*uses: " .github/workflows/*.yml) || exit 1; bad=$(printf "%s\n" "$uses" | grep -vcE "@[0-9a-f]{40} # v?[0-9]"); rc=$?; printf "%s\n" "$bad"; test "$rc" -le 1 && test "$bad" -eq 0'` → `0`, exit 0; неполный pin или ошибка чтения → exit 1.
3. `permissions: {}` в начале каждого workflow; каждой job — `permissions: { contents: read }`; `actions/checkout` —
   `with: { persist-credentials: false }`.
4. Матрица `tests`: php `['8.4','8.5']` × laravel `['12.*','13.*']`; удалить шаг «Boxed profile». `tests-pg`: матрица
   `postgres: ['16','18']` × laravel; image `postgres:${{ matrix.postgres }}`; остальные PG-службы `postgres:18`.
5. После каждого шага `composer update|install` — шаг `composer audit --locked` (тот же `--working-dir`).
6. Новые jobs (examples §8.2): `manifests` (`composer manifests:check` из P01.1 + цикл `composer validate --strict
   --no-check-lock` по 21 корню); `golden` (`composer golden`); `ownership` (`php scripts/ownership-gate.php`);
   `phpstan` (замена шага в `quality`: `composer analyse`); `dependencies` (`bash scripts/dependency-analyser.sh --all`
   + `composer audit --locked` каждого пакета); `zizmor` (zizmor-action v0.6.4 по SHA, `persona: regular`,
   `advanced-security: false`, `permissions: contents: read`).
7. `.github/dependabot.yml`, `.github/CODEOWNERS`, `.github/zizmor.yml` (examples §8.3).
8. Проверки: `build/tools/venv/bin/zizmor --offline .github/workflows` → exit 0 (findings 0 или только
   задокументированные ignore с причиной); `python3 -c "import yaml,sys,glob;[yaml.safe_load(open(f)) for f in
   glob.glob('.github/**/*.yml',recursive=True)]"` → exit 0 (PyYAML есть? иначе `build/tools/venv/bin/pip install
   pyyaml`); anti-weakening `bash scripts/anti-weakening-gate.sh` → exit 0.
9. Live-проверка — после item, владельцем: push → все jobs зелёные (Phase Validation, O-3).

## 5 Failure playbook

| Класс | Симптом | Действие |
|:--|:--|:--|
| K1 окружение | `scripts/php84 --check` MISSING / PG auth fail | стоп → эскалация O-1/O-2 с точной командой §7; не запускать на 8.3 |
| K2 разрешение Composer | `does not match your minimum-stability` | корень не содержит пакет замыкания → добавить `^1.0@dev` (manifest-check `closure`) |
| K2 | конфликт `phpunit/phpunit` / Pest | проверить, что везде Pest `^5.2`, testbench `^10.12\|^11.3`; `composer why-not pestphp/pest 5.2.1` |
| K2 | stancl не найден | не выполнен `stage-stancl-providers.py` перед update |
| K3 падения тестов на 8.4/Pest 5 | deprecation → failure (`failOnWarning`) | исправить код/тест в P01.1 (не подавлять, не skip — anti-weakening); >20 разнородных падений → эскалация |
| K4 PHPStan | OOM | `--memory-limit=4G`; при повторе — эскалация |
| K4 | baseline не воспроизводится (ошибки после generate) | удалить `tmpDir` пакета, повторить; нестабильность → эскалация |
| K5 golden недетерминирован | байты различаются между прогонами | найти поле (diff), добавить в нормализатор как исключаемое/сортируемое с комментарием «почему»; не удалять слой |
| K5 | коллектор падает на классе (autoload side effects) | исключить файл списком `tests/Golden/Support/skip.php` с причиной; >5 исключений → эскалация |
| K6 ownership | ложное O1 (одинаковое имя таблицы в тестовых фикстурах) | инвентарь только `src/`, `config/`, `database/migrations/` — не `tests/` |
| K7 analyser | unused для пакета, используемого через discovery/config (не PHP-импорт) | не долг, а ложное срабатывание → ignore `UNUSED_DEPENDENCY` с комментарием `// discovery: <файл, где используется>`; в ceiling не считается |
| K8 zizmor | `template-injection`/`artipacked` | исправить workflow (env-переменные вместо `${{ }}` в `run`, `persist-credentials: false`); ignore только `unpinned-images` для `postgres:<n>` с причиной |
| K9 внешнее | нужен push/merge, секреты, настройки репозитория | стоп → владелец (O-3) |

## 6 Self-check (B10)

| Item | Факты, которые исполнитель ищет сам | Решения, оставленные исполнителю | Доказательство готовности |
|:--|:--|:--|:--|
| P01.1 | нет (F1–F5, manifest-check даёт полный список правок) | нет; исправление падений K3 — по факту прогона | `manifest-check.py` OK, validate ×21, test/test:pg/test:packages/lint, L12-worktree, `junit-summary --compare` |
| P01.2 | список провайдеров пакетов (`extra.laravel.providers` — механический сбор) | нет; поля нормализатора — по K5 | 4 passed до/после class-rename, идемпотентность, байтовая воспроизводимость, негативы a–e |
| P01.3 | нет (регэкспы examples §4.2, шаблоны §5–§7) | классификация analyser-ошибок по правилу §4.3 п.12 | ownership OK + 4 негатива; phpstan ×21 OK; ceilings OK + 2 негатива; analyser ×20 OK |
| P01.4 | SHA текущих версий actions — делает pinact | нет | pinact-grep `0`, zizmor exit 0, YAML OK, anti-weakening OK; live CI — O-3 |

## 7 Owner gates

- **O-1 (system software):** `sudo apt install php8.4-xml php8.4-intl php8.4-sqlite3 php8.4-bcmath php8.4-zip php8.4-gmp php8.4-gd php8.4-pcov`
- **O-2 (локальная БД):**
  ```bash
  sudo -u postgres psql -c "CREATE ROLE corex LOGIN PASSWORD 'corex' CREATEDB"
  for db in corex_test corex_search_a_test corex_search_b_test; do sudo -u postgres createdb -O corex "$db"; done
  ```
- **O-3 (внешнее):** push ветки/коммитов P01 в `axiomasoft/corex` и просмотр CI-прогона; включение Dependabot в настройках
  репозитория, если выключен.
