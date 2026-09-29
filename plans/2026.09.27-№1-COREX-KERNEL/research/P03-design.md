# P03 — досье дизайна: feature modules и `Internal`

Решения — D57 (заменяет D05) и D58 (исправления аудита A1–A5), D59 (полный replay и исправления readiness). Внешние факты — `research/P03-design-rag.md` (E1–E3; captures сохранены).
Факты кода — облачный скан `artifacts/P03-design/cloud/` (README, `scan.py`), примеры — `artifacts/P03-design/examples.md`,
скрипты — `artifacts/P03-design/scripts/`, карта — `artifacts/P03/`. Черновик прошлой сессии
(`artifacts/P03-design/design-notes.md`) проверен; поправки — §3 (Ф2, Ф4) и D57 п.3–4.

Все команды — из корня репозитория; PHP — `scripts/php84` (как P01/P02); PG — `PGSQL_PORT=5432`.

## 1 Карта кода

| Что | Где | Роль в P03 |
|:--|:--|:--|
| Облачный скан | `plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud/scan.py` → `inventory.tsv`, `consumers.tsv`, `package-shapes.md` | вход карты; детерминирован |
| Карта модулей | `artifacts/P03-design/scripts/p03-module-map.py` → `artifacts/P03/module-map.tsv`, `public-by-usage.tsv` | `--check` — свежесть карты на текущем дереве |
| Перенос | `artifacts/P03-design/scripts/p03-transfer.py` (`plan/apply/manifest/golden/lock/verify/adapters`) | поверх `artifacts/P02-design/scripts/p02-transfer.py` + `p02-literal-pass.php` + `p02-references.php` |
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

- **F11 (D58, после аудита, HEAD `556c547`):** `artifacts/P03-design-remediation/check.py` воспроизводит исправленный перенос без Rector: `manifest → golden (376) → lock → verify (0)`; rename-completeness exit 0. Lock: 233 пакета, 0 installs/updates/removals, изменения ограничены FQCN metadata и path references; старый lock возвращает 13 ошибок. Восемь тестов гейта проходят; обе baseline регенерированы и совпали байт в байт (436/159). Исходные audit artifacts сохранены. F5 — исторический неполный прогон; актуальный порядок и доказательства — F11.

- **F12 (D59, полный replay):** `audits/2026-09-29-p03-design-recheck/` от корня репозитория сохраняет реальные выводы Rector, golden/root/PG/package suites, PHPStan и mutation cohort. Найдены и исправлены: 12 implicit imports, восемь package-relative NEON paths, короткие fixture paths и пять прежних cross-zone edges, пять AST-equivalent adapter hashes. Карта и baseline не меняются. Итоговые статусы и hash привязка инструментов — `final-replay.json` и `script-sha256.json`; не выводить GREEN из промежуточных `full-initial`/`full-fixed*` логов.

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

1. `git status` чистый после фиксации текущих plan carriers; `python3 plans/…/artifacts/P03-design/scripts/p03-module-map.py --check` → `module-map OK`.
2. Скопировать `module-layout-gate.py`, `module-layout.json` → `scripts/`; `test_module_layout_gate.py` → `scripts/tests/`; `module-layout-baseline.current.json` → `scripts/module-layout-baseline.json` (или `--write-baseline`, файл должен совпасть байт в байт).
3. `composer.json` scripts: `"module-layout-gate": "python3 scripts/module-layout-gate.py"`; удалить `tenancy-structure-gate`.
4. CI (`.github/workflows/ci.yml`): шаг «Tenancy structure and compatibility» → «Feature-module layout (D57)» с `composer module-layout-gate` и `python3 -m unittest discover -s scripts/tests -p 'test_module_layout_gate.py'`.
5. `git rm scripts/tenancy-structure-gate.py tests/fixtures/tenancy-structure` (-r).
6. Доктрина: STR-001 удалить; STR-002…STR-006 — тексты examples §3; `structure-profiles.md` §2 — процедуры в терминах модулей; удалить `exceptions/STR-003/`, `exceptions/STR-005/` и их ссылки (`README.md`, constitution, structure-profiles); `package-docs-gate`/`naming-gate` зелёные.
7. Validation item → review.

### 4.2 P03.2 — перенос по карте (D58)

1. Предусловие: P03.1 GREEN; `git status` чистый после фиксации текущих plan carriers; `module-map --check` OK; `python3 …/p03-transfer.py plan` → `0 problems`.
2. Сухой прогон: `git worktree add --detach build/p03-dry HEAD`; в нём setup ниже (root install + собственные package vendors) → `python3 …/p03-transfer.py apply` (с Rector) → `manifest` → `golden` → `lock` → `verify` → `scripts/php84 composer install --no-scripts --no-interaction` → `scripts/php84 vendor/bin/pint` по изменённым PHP → `python3 …/p03-transfer.py adapters` (5 AST-equivalent sources) → `python3 scripts/module-layout-gate.py --write-baseline` (должен совпасть с `artifacts/P03/module-layout-baseline.predicted.json`) → стек §4.3. Записать `git diff --stat HEAD` сухого прогона.
3. Основное дерево: те же шаги 2 (apply…baseline); `git diff --stat` = сухой прогон.
4. CHANGELOG каждого пакета с переносами: раздел «Changed» — «PLAN3.D57: классы реализации перенесены в `<Module>\Internal`, публичные типы — в категории модуля; перед обновлением очистить очереди; `*_type` со старым FQCN не резолвятся» (D01, без алиасов).
5. Документы пакетов (README/docs), упомянутые literal pass, проверить `package-docs-gate`.

`lock` — обязательный шаг D58: `apply` сохраняет preimage root manifest/lock в `build/p03/`;
`lock` регистрирует sealed Stancl providers, восстанавливает package manifests до Composer resolution,
запускает `scripts/php84 composer update --lock --no-install --no-scripts --no-plugins --no-interaction`,
восстанавливает root manifest байт в байт и portable URLs. Content-hash берётся из preimage только при
неизменном root manifest. Допускаются только `extra.corex.public-api`, `extra.corex.module` и `dist.reference`
локальных path packages; все остальные поля lock (включая версии, dependency edges, external source/dist)
сравниваются с preimage. Metadata должна совпасть с текущими манифестами. При drift — stop, lock возвращается
к состоянию до команды, candidate остаётся `build/p03/composer-lock.candidate.json` для диагностики.
Не заменять это общим `composer update`. После успеха — dev-autoload и rename-completeness, который сканирует lock.

В setup scratch-worktree `composer install` требует локальных sealed zip: сначала
`python3 scripts/distribution/stage-stancl-providers.py`, затем восстановить три composer.json из чистого HEAD
(`git restore -- composer.json packages/tenancy/composer.json packages/access/composer.json`) и выполнить
`scripts/php84 composer install --no-scripts --no-interaction`. Staging остаётся игнорируемым; перед `apply`
проверить чистоту tracked/untracked worktree. После lock повторный `composer install --no-scripts --no-interaction` синхронизирует installed metadata и classmap с lock.


### 4.2.1 Дополнения полного replay (D59)

`apply` теперь сам выполняет `p02-imports.php` после перемещения, точную проекцию `path:` в package PHPStan NEON
и `p03-fixtures.py`. Последний проецирует CORE_ZONE_DUMP/FOUNDATION_SUPPORT по карте, сохраняет пять существующих
cross-zone edges точными source/FQCN парами, исправляет `packagePath()` и удаляет ссылку на пустой Views в phpunit.
R-Z*, сообщения/count baseline, тесты и negative controls сохраняются. Это не ручные остатки лимита 20.

После Pint команда `adapters` проверяет исходные SHA и AST-equivalence с картой и лишь затем обновляет пять hashes.
`build/p03/{fixture-projection,adapter-projection}.json` и `build/p02/implicit-references.json` — receipts.
Порядок: `apply → manifest → golden → lock → verify → install → Pint → adapters → baseline → gates`.

**Package harness setup.** Root install не создаёт `packages/*/vendor` для phpunit bootstrap. Перед `test:packages`
у каждого из 21 roots из Composer script должна быть собственная установленная vendor-папка. Для обычной свежей
установки: после sealed-provider staging выполнить `scripts/php84 composer --working-dir=<root> install --no-scripts
--no-interaction` для roots списка `test:packages`, сохранить и восстановить три manifests staging, portable-locks.
Для локального scratch replay допустимо `cp -a --reflink=auto <source-root>/<package>/vendor <scratch>/<package>/vendor`
(не symlink/hardlink всего vendor), проверить все vendor symlinks: `resolve()` внутри scratch, затем
`scripts/php84 composer --working-dir=<package> dump-autoload --no-scripts --no-interaction` для каждого root.
Зафиксировать dependency-set/source и результаты autoload в artifact. Это не fresh-install/hosted-CI proof.

После свежего переноса `tests/Arch/AchievedInvariantTest.php` обязан сканировать `custom-fields`, а не оставшийся
локально каталог `attributes`; root suite должен стартовать из чистого Git checkout без пустого `tests/Views`.
Конкретный локальный replay runner: `audits/2026-09-29-p03-design-recheck/replay.py` (диагностика, только свой scratch).

### 4.3 Полный стек (после каждого item)

`scripts/php84 composer lint:check`; `scripts/php84 composer analyse` + `php scripts/quality-ceiling-guard.php` (`ceilings OK`);
`scripts/php84 composer test`; `PGSQL_PORT=5432 scripts/php84 composer test:pg`; `scripts/php84 composer test:packages`;
`scripts/php84 composer golden` (`Tests: 4 passed`); `scripts/php84 composer test:arch`; `php scripts/package-graph-gate.php`;
`php scripts/rename-completeness-gate.php`; `php scripts/ownership-gate.php --strict`; `bash scripts/anti-weakening-gate.sh`;
`PGSQL_PORT=5432 scripts/php84 bash scripts/mutation-check.sh --all` (34/34);
`composer module-layout-gate` (после P03.1); `python3 <task>/scripts/plan-lint.py plans/2026.09.27-№1-COREX-KERNEL`.

## 5 Failure playbook

| Симптом | Причина | Действие |
|:--|:--|:--|
| `module-map STALE` | дерево изменилось после скана | стоп: автор плана перезапускает `cloud/scan.py` и `p03-module-map.py`, выпускает дельту (D56) |
| `verify: old FQCN … in <file>` | токен вне PHP-кода/не покрыт проходом | ≤ 20 — руками, каждый в Completion Notes; больше — стоп, автор плана правит `p03-transfer.py` (D56), новый сухой прогон |
| Fatal class/тип после смены namespace | короткое имя прежнего соседнего класса | `apply` обязан выполнить P02 implicit-imports (D59); исправить карту/проход и повторить полный dry, не дописывать imports вручную |
| `lock` сообщает drift | Composer изменил поле вне FQCN metadata/ref локальных пакетов | stop; сверить candidate/preimage в `build/p03/`, не расширять allowlist без D# |
| `golden` падает | нет `manual`-правила для имени baseline | `p03-transfer.py golden` снова; baseline и `generated` не трогать |
| гейт: `new R5 …` сверх прогноза | ссылка появилась в Rector-проходе | сверить с `module-layout-baseline.predicted.json`; расхождение объяснить в Completion Notes, baseline не растёт без D# |
| `package-graph-gate` R2 | внешний потребитель класса, ушедшего в `Internal` | дефект карты (класс должен быть в `public-by-usage`) → стоп, автор плана правит `p03-module-map.py` (D56) |
| `anti-weakening` число тестов | удаление тестов гейта tenancy не касается Pest (Python-гейт без Pest-тестов) | если упало — найти удалённый тест, стоп |

## 6 Self-check (B10)

- Данные для исполнения: карта, scope metadata и обязательные проверки заданы; полный Rector-прогон воспроизведён в design audit (F12); исполнитель повторяет его на своём input-tree. Факты, которые исполнитель ищет сам: ни одного — карта, остающиеся классы, baseline, golden-правила, тексты STR и шаги посчитаны.
- Решения исполнителю: ни одного — кроме ≤ 20 ручных остатков переноса (фиксируются).
- Доказательство: команды Validation items (точные строки вывода).

## 7 Owner gates

Нет. Hosted CI отложен (D47); push — владелец.

### F12 — digest инструментов финального design audit

Эти значения связывают нормативное досье (и design fingerprint) с исполняемыми инструментами D59.

| Файл | SHA-256 |
|:--|:--|
| `artifacts/P03-design/scripts/module-layout-gate.py` | `a1ddd0d8ef059860299703d656650dbc4d0d1bf5b98443cb19644e8807e3a72b` |
| `artifacts/P03-design/scripts/module-layout.json` | `3db084e42fc399249948ddcf9e758e5cf2727b63dac87e4f84563af866cec195` |
| `artifacts/P03-design/scripts/p03-adapters.php` | `ed96c4ae5b42bf285076b8f44903eed77eea1889394b1457a19342a10d0f13b3` |
| `artifacts/P03-design/scripts/p03-fixtures.py` | `434679fa9b5390a0cac6f240e7b32b39326ba116e15ca49080d90f0fed75a340` |
| `artifacts/P03-design/scripts/p03-module-map.py` | `bac7885b2858fe794ec6207342f90c520e4fd4992bd18868e1aad3e70bae5c5f` |
| `artifacts/P03-design/scripts/p03-transfer.py` | `1c2956664529fe75bf54791130b280221131f1e9f6bcb97d511eada4a807a0cc` |
| `artifacts/P03-design/scripts/test_module_layout_gate.py` | `8577d12ac273af9d5ae45e679558d594fbc768ca01fead103ec9eacc9b657a8a` |
| `artifacts/P03-design/scripts/test_p03_transfer.py` | `d878f810e54f0f1b6f2fdbcbd76972477a58d640b467b747f15a827d59d47f21` |
| `artifacts/P02-design/scripts/p02-imports.php` | `6607f41b418b94fff03fae37c71f00e8eb077d395acbd068aebce1f952c12fc6` |
