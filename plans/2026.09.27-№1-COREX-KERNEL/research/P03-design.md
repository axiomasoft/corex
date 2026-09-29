# P03 — досье дизайна: feature modules и `Internal`

Решение — D57 (заменяет D05). Внешние факты — `research/P03-design-rag.md` (E1, E2; повторный поиск не нужен).
Факты кода — облачный скан `artifacts/P03-design/cloud/` (README, `scan.py`), примеры — `artifacts/P03-design/examples.md`,
скрипты — `artifacts/P03-design/scripts/`, карта — `artifacts/P03/`. Черновик прошлой сессии
(`artifacts/P03-design/design-notes.md`) проверен; поправки — §3 (Ф2, Ф4) и D57 п.3–4.

Все команды — из корня репозитория; PHP — `scripts/php84` (как P01/P02); PG — `PGSQL_PORT=5432`.

## 1 Карта кода

| Что | Где | Роль в P03 |
|:--|:--|:--|
| Облачный скан | `plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud/scan.py` → `inventory.tsv`, `consumers.tsv`, `package-shapes.md` | вход карты; детерминирован |
| Карта модулей | `artifacts/P03-design/scripts/p03-module-map.py` → `artifacts/P03/module-map.tsv`, `public-by-usage.tsv` | `--check` — свежесть карты на текущем дереве |
| Перенос | `artifacts/P03-design/scripts/p03-transfer.py` (`plan/apply/verify/manifest/golden`) | поверх `artifacts/P02-design/scripts/p02-transfer.py` + `p02-literal-pass.php` + `p02-references.php` |
| Гейт раскладки | `artifacts/P03-design/scripts/{module-layout-gate.py,module-layout.json,test_module_layout_gate.py}` | копируются в `scripts/` и `scripts/tests/` (P03.1) |
| Baseline гейта | `artifacts/P03/module-layout-baseline.current.json` (436), `…predicted.json` (159) | эталоны P03.1 и P03.2 |
| Golden | `tests/Golden/rename-map.json` (`manual`), `tests/Golden/Support/{RenameMap,Normalizer}.php` | `manual` `identifier` с `\` = правило класса (RenameMap:31–35); `internal` выводится из спроецированного имени (Normalizer:15–17) |
| Полнота переименований | `scripts/rename-completeness.json` + `scripts/rename-completeness-gate.php` | секции `classes` (`fqcn`), `namespaces` (`prefix`) |
| Граница пакетов | `scripts/package-graph-gate.php` R2 (только `packages/*/src`) | не меняется; остающиеся на месте классы не создают R2 |
| Ретайр | `scripts/tenancy-structure-gate.py`, `tests/fixtures/tenancy-structure/**`, `composer.json` `tenancy-structure-gate`, `.github/workflows/ci.yml` шаг «Tenancy structure and compatibility» | удаляются (D57 п.9) |
| Доктрина | `docs/architecture/{constitution.md (STR-001…006), structure-profiles.md, README.md, exceptions/STR-003/**, exceptions/STR-005/**}` | переписываются по `artifacts/P00/doctrine-delta.md` строки STR-* с поправкой D57 |
| Morph map | `packages/core/src/Modules/Laravel/ModulesBootRegistrar.php:93–107` | `Relation::morphMap` из алиасов сущностей (не enforce) |

## 2 Предвычисленные факты (на `52446de`)

- **F1** Скан воспроизводится байт в байт (`scan.py` → нет диффа); 1168 PHP-файлов, 21 корень (20 пакетов + продукт); namespace ≠ путь — 0.
- **F2** Карта: 1168 строк, **278 переносов** (Contracts 6, Data 64, Enums 7, Exceptions 19, Internal 182), **68 остаются** (`public-by-usage.tsv`: 46 — потребитель другой пакет/продукт, 22 — `config`/glossary), продукт 56 — `product-n8`, коллизий 0. По пакетам: core 68, tenancy 118, access 26, search 16, custom-fields 15, operations 10, tasks 9, media 5, notifications 3, wakeup 2, webhooks 2, intl/meilisearch/tasks-workflow/tools по 1.
- **F3** 23 переноса меняют FQCN, которые называет другой пакет/продукт (все — в публичную категорию: `CoreX\Config\InvalidConfiguration` → `CoreX\Config\Exceptions\…`, `CoreX\Data\RecordRef` → `CoreX\Data\Data\RecordRef`, 12 `CoreX\Search\Support\*` → `CoreX\Search\Data\*`, …); ещё 32 — только в тестах монорепо. Остальные 223 — внутри своего пакета.
- **F4** Гейт на текущем дереве: 1112 файлов пакетов, R1 49, R3 344, R5 43 (436, `module-layout-baseline.current.json`); проекция по карте (`--project`): R3 68 (ровно `public-by-usage`), R5 91 (159, `…predicted.json`), R0/R1/R2/R4 = 0.
- **F5** Дизайн-прогон в scratch-worktree: `p03-transfer.py plan` → `278 classes, 278 php moves, 278 namespace rewrites, 53 namespace tokens, 53 vanished namespaces, 0 problems`; `apply --no-rector` → relative 1, literal 42 text + 17 php, namespace 278, move 278 (≈8 с); `golden` → 376 manual rules; `verify` → `0 problems`; гейт на результате = F4 (R3 68, R5 91). Изменено 278 RM + 386 M файлов. Rector и тесты в дизайне не запускались (нет `vendor` в worktree) — это сухой прогон P03.2.
- **F6** Golden: baseline хранит имена до P02; `generated` проецирует их на P02-имена, одно правило на имя (без цепочек). Для P03 `manual` `identifier` правило «имя baseline → имя P03» перекрывает `generated` (RenameMap:31–35); `internal` у перенесённого в `Internal` класса выводится из проекции → baseline не меняется.
- **F7** `python3 scripts/tenancy-structure-gate.py check` → exit 1 (`D49 is not the only permitted behavior delta`) уже на `main`; гейт читает `plans/2026.09.19-…/findings/P9-*` и сверяет FQCN tenancy с baseline прежнего плана — противоречит D01 и D57.
- **F8** Morph map — из алиасов сущностей модулей, не `enforceMorphMap`; `BaseModel` → `HasAuditLog` → `AuditObserver` пишет `getMorphClass()` (FQCN для модели без алиаса). Переносятся 20 моделей пакетов, 6 jobs и 17 команд → их FQCN в существующих данных/очередях перестанут резолвиться (D01: без миграции, запись в CHANGELOG).
- **F9** `CoreX\Contracts` после P02 не существует (зарезервированный сегмент); фраза скелета «сегрегирует 19 контрактов `CoreX\Contracts`» устарела — выполнено P02.
- **F10** `p02-transfer.py rector` берёт файлы только из `packages/ tests/ scripts/ fixtures/`; продукт потребляет перенесённые классы (`DatabaseConnectionConfig` и др.) → `p03-transfer.py` использует свою копию с `products/`.

## 3 Развилки (решены D57)

| # | Развилка | Решение | Отклонено |
|:--|:--|:--|:--|
| Ф1 | Корзины single-feature пакетов (S1/S2) | корень пакета = корневой модуль | S1 (корзины остаются, 420), S2 (`X\X\…`, 664) |
| Ф2 | Реализация, которую уже используют снаружи | остаётся на месте (68), P04 классифицирует | перенос в `Internal` (R2 + опубликованный конфиг); поправка: FQCN публичных типов, уходящих в категорию, меняются (F3) |
| Ф3 | Команды/jobs/модели/контроллеры без внешних потребителей | `<Module>\Internal\<Bucket>\…` | `Laravel\Commands` (D05 допускал «либо Internal»; мост — composition root) |
| Ф4 | Продукт `flex/crm` | не переносится (N8) | перенос 36 файлов по той же норме |
| Ф5 | L3 для `Events`/`Data` с логикой | категория законна по каталогу; гейт ролей не угадывает | роли `event`/`value-object` |
| Ф6 | Крупные модули | не делятся в P03; подкаталоги вне категорий → `Internal/<Sub>` | деление на модули (решение P04+ по контрактам) |
| Ф7 | Межмодульные ссылки в `Internal` (R5) | ratchet-baseline 91, P04 сокращает | блок P03 (потребовал бы контрактов модулей = P04) |
| Ф8 | Гейт `tenancy-structure` | ретайр, заменён гейтом раскладки | чинить baseline прежнего плана (D01) |
| Ф9 | Строки в БД/очередях | без миграции, CHANGELOG (D01) | алиасы/`class_alias` (D01 запрещает) |

## 4 Runbooks

### 4.1 P03.1 — гейт раскладки и ретайр гейта tenancy

1. `git status` чистый; `python3 plans/…/artifacts/P03-design/scripts/p03-module-map.py --check` → `module-map OK`.
2. Скопировать `module-layout-gate.py`, `module-layout.json` → `scripts/`; `test_module_layout_gate.py` → `scripts/tests/`; `module-layout-baseline.current.json` → `scripts/module-layout-baseline.json` (или `--write-baseline`, файл должен совпасть байт в байт).
3. `composer.json` scripts: `"module-layout-gate": "python3 scripts/module-layout-gate.py"`; удалить `tenancy-structure-gate`.
4. CI (`.github/workflows/ci.yml`): шаг «Tenancy structure and compatibility» → «Feature-module layout (D57)» с `composer module-layout-gate` и `python3 -m unittest discover -s scripts/tests -p 'test_module_layout_gate.py'`.
5. `git rm scripts/tenancy-structure-gate.py tests/fixtures/tenancy-structure` (-r).
6. Доктрина: STR-001 удалить; STR-002…STR-006 — тексты examples §3; `structure-profiles.md` §2 — процедуры в терминах модулей; удалить `exceptions/STR-003/`, `exceptions/STR-005/` и их ссылки (`README.md`, constitution, structure-profiles); `package-docs-gate`/`naming-gate` зелёные.
7. Validation item → review.

### 4.2 P03.2 — перенос по карте

1. Предусловие: P03.1 GREEN; `git status` чистый; `module-map --check` OK; `python3 …/p03-transfer.py plan` → `0 problems`.
2. Сухой прогон: `git worktree add --detach build/p03-dry HEAD`; в нём `scripts/php84 composer install` (как P02.3) → `python3 …/p03-transfer.py apply` (с Rector) → `verify` → `manifest` → `golden` → `scripts/php84 vendor/bin/pint` по изменённым PHP → `python3 scripts/module-layout-gate.py --write-baseline` (должен совпасть с `artifacts/P03/module-layout-baseline.predicted.json`) → стек §4.3. Записать `git diff --stat HEAD` сухого прогона.
3. Основное дерево: те же шаги 2 (apply…baseline); `git diff --stat` = сухой прогон.
4. CHANGELOG каждого пакета с переносами: раздел «Changed» — «PLAN3.D57: классы реализации перенесены в `<Module>\Internal`, публичные типы — в категории модуля; перед обновлением очистить очереди; `*_type` со старым FQCN не резолвятся» (D01, без алиасов).
5. Документы пакетов (README/docs), упомянутые literal pass, проверить `package-docs-gate`.

### 4.3 Полный стек (после каждого item)

`scripts/php84 composer lint:check`; `scripts/php84 composer analyse` + `php scripts/quality-ceiling-guard.php` (`ceilings OK`);
`scripts/php84 composer test`; `PGSQL_PORT=5432 scripts/php84 composer test:pg`; `scripts/php84 composer test:packages`;
`scripts/php84 composer golden` (`Tests: 4 passed`); `scripts/php84 composer test:arch`; `php scripts/package-graph-gate.php`;
`php scripts/rename-completeness-gate.php`; `php scripts/ownership-gate.php --strict`; `bash scripts/anti-weakening-gate.sh`;
`composer module-layout-gate` (после P03.1); `python3 <task>/scripts/plan-lint.py plans/2026.09.27-№1-COREX-KERNEL`.

## 5 Failure playbook

| Симптом | Причина | Действие |
|:--|:--|:--|
| `module-map STALE` | дерево изменилось после скана | стоп: автор плана перезапускает `cloud/scan.py` и `p03-module-map.py`, выпускает дельту (D56) |
| `verify: old FQCN … in <file>` | токен вне PHP-кода/не покрыт проходом | ≤ 20 — руками, каждый в Completion Notes; больше — стоп, автор плана правит `p03-transfer.py` (D56), новый сухой прогон |
| Rector не добавил `use` для соседнего класса | короткое имя в том же старом namespace | PHPStan/тесты покажут; иначе — стоп, автор плана дополняет проход (D56), новый сухой прогон |
| `golden` падает | нет `manual`-правила для имени baseline | `p03-transfer.py golden` снова; baseline и `generated` не трогать |
| гейт: `new R5 …` сверх прогноза | ссылка появилась в Rector-проходе | сверить с `module-layout-baseline.predicted.json`; расхождение объяснить в Completion Notes, baseline не растёт без D# |
| `package-graph-gate` R2 | внешний потребитель класса, ушедшего в `Internal` | дефект карты (класс должен быть в `public-by-usage`) → стоп, автор плана правит `p03-module-map.py` (D56) |
| `anti-weakening` число тестов | удаление тестов гейта tenancy не касается Pest (Python-гейт без Pest-тестов) | если упало — найти удалённый тест, стоп |

## 6 Self-check (B10)

- Факты, которые исполнитель ищет сам: ни одного — карта, остающиеся классы, baseline, golden-правила, тексты STR и шаги посчитаны.
- Решения исполнителю: ни одного — кроме ≤ 20 ручных остатков переноса (фиксируются).
- Доказательство: команды Validation items (точные строки вывода).

## 7 Owner gates

Нет. Hosted CI отложен (D47); push — владелец.
