# P12 — post-close adversarial audit

**Schema:** p12-post-close-audit/1
**Дата аудита:** 2026-09-18
**Ревизия дерева:** HEAD `904ac87` + незакоммиченное рабочее дерево (см. R1)
**Режим:** read-only над продуктом; ни один product-файл не изменён
**Актор:** auditor/claude-opus-5/high

## Вердикт: RED

P12 закрыт как 🟢 Done (9/9), но четыре дефекта класса RED делают закрытие недействительным
по действующему контракту фазы и протоколу. Продуктовая часть фазы в основном реальна:
восемь из девяти item имеют подтверждаемый кодом результат. Недействительно именно
**закрытие**: результат не существует в репозитории, один объявленный Validation-карьер
красный, terminal-evidence одного item не привязан к его спеке, а обязательный design-гейт
плана красный — из-за чего записанный в `handoff.md` следующий шаг не допущен к исполнению.

| Класс | ID | Кратко |
|:--|:--|:--|
| RED | R1 | P12.2–P12.9 не закоммичены: результата фазы нет в репозитории |
| RED | R2 | `composer lint:check` красный — объявленный Validation-карьер; доказанная регрессия P12 |
| RED | R3 | P12.5: `spec_sha256` в terminal-evidence не совпадает с фактической спекой |
| RED | R4 | `task-check.py --check design` → `DESIGN_PENDING`; persisted Next (P8.1) не допущен |
| ATTENTION | A1 | Гейт P12.3 материально ослаблен: allowlist не проверяет охрану, `reservations` — мёртвый и инвертированный механизм |
| ATTENTION | A2 | Effort undershoot: четыре item пинятся `high`, исполнены `medium` |
| ATTENTION | A3 | `Review: full` (пять item) не исполнен — ни одного review-события |
| ATTENTION | A4 | Batch-ID в journal расходятся с Routing-таблицей плана |
| ATTENTION | A5 | Отсутствуют start-события journal для P12.1–P12.6 |
| ATTENTION | A6 | Receipt'ы P12.2–P12.8 не содержат фактического вывода, которого требуют их спеки |
| ATTENTION | A7 | Цифра «472 passed, 7 skipped» — результат одного пакета, выдан за агрегат 20-пакетного контура |
| ATTENTION | A8 | `phases/P9/P9.2.md` ссылается на пути пакетов, переименованных в P12.4/P12.6 |

## Что подтверждено кодом (GREEN-часть)

Проверено чтением кода и фактическим запуском, а не по receipt'ам.

| Item | Утверждение | Подтверждение |
|:--|:--|:--|
| P12.1 | Контракт модуля `corex/intl` починен | `packages/intl/src/IntlModule.php` — `extends CorexModule`, `manifest()` |
| P12.1 | Утечка ядра устранена | `packages/core/src/Tenancy/Contracts/TenancyManager.php` — импорта `StanclTenancyManager` нет |
| P12.2 | Контур тестов — 20 пакетов | `composer.json:test:packages` перечисляет ровно 20; фактический прогон: 21 заголовок `==> packages/`, exit=0 |
| P12.2 | `core`/`modules` имеют скрипты | `packages/core/composer.json`, `packages/modules/composer.json` — `test`/`test:coverage`/`analyse`/`lint:check` |
| P12.2 | Пакетов без `tests/` не осталось | проверка всех 20 каталогов — пусто |
| P12.3 | Гейт исполним и зелёный | `php scripts/package-graph-gate.php` → `package-graph-gate: OK`, exit=0 |
| P12.3 | Legacy-исключение печатается каждый прогон | вывод `LEGACY NAMESPACE SPLIT: CoreX\Tenancy\ … expires when: …`; `artifacts/P12-package-graph/report.json` — `class_counts {core:16, tenancy:171}` |
| P12.3 | Гейт в `test:arch` | `tests/Arch/PackageGraphTest.php:9-29`; `composer test:arch` → 7 passed |
| P12.4 | Rename выполнен | `packages/transfer/**`, неймспейс `CoreX\Transfer\`, `src/Data/` вместо `src/Support/` |
| P12.4/P12.6 | Остаточных runtime-ссылок нет | repo-wide sweep вне `audits/`, `plans/`, `docs/audit-*`: только историчные записи `CHANGELOG.md` |
| P12.5 | Единственный реестр драйверов | `packages/search/src/SearchManager.php:16-58`; `SearchServiceProvider.php:20-22` резолвит через менеджер |
| P12.5 | Fail-closed конфиг | `SearchManager.php:22-24,37-40,42-44,46-48` — четыре громких исключения |
| P12.5 | Адаптер не перебиндивает `SearchProvider` | `MeilisearchServiceProvider.php:26` — `->extend('meilisearch', …)` в `boot()`, ключа `enabled` в конфиге нет |
| P12.5 | `null` как дефолт | `packages/search/config/corex-search.php:6` — `'driver' => 'null'` |
| P12.5 | Пин SDK не тронут | `packages/search-meilisearch/composer.json` — `1.16.1` |
| P12.6 | Rename выполнен | `packages/search-intl/**`, неймспейс `CoreX\SearchIntl\` |
| P12.7 | Типизированный table-map | `packages/attributes/src/Data/AttributeTables.php` |
| P12.7 | `config()` из движков убран | `rg "config\(" packages/attributes/src/Internal/Engine/` — пусто |
| P12.7 | Schema-валидация обязательна | `EavAttributeStore.php:32`, `JsonbAttributeStore.php:33` — `private FieldCatalogue $catalogue` без `?` |
| P12.7 | `modules` опционален | `packages/attributes/composer.json` — `corex/modules` в `suggest`, не в `require` |
| P12.7 | Миграции не тронуты | `git diff --stat -- 'packages/*/database/**'` — пусто |
| P12.8 | `DataScope` сериализуем | `packages/core/src/Support/Data/DataScope.php:10-32` — `final readonly class`, ни одного `Closure` |
| P12.8 | Контракт проверки доступа | `packages/core/src/Contracts/DataScopeAuthorizer.php:15` |
| P12.9 | Триада и ledger реальны | `docs/audit-2026-09-17.md`, `-remediation.md`, `-deferred.md`; ledger — 9 отложенных + 5 отклонённых, ни один не выдан за решённый |

Корневые гейты на момент аудита: `composer analyse` → 0 errors; `composer test:arch` → 7 passed;
`composer test:packages` → exit=0; `bash scripts/anti-weakening-gate.sh` → OK (sqlite 469→469, pg 507→507);
`git diff --stat -- 'packages/*/database/**'` → пусто.

## RED

### R1 — P12.2–P12.9 не закоммичены: результата фазы нет в репозитории

HEAD — `904ac87 fix(packages): починить P0 границ пакетов (P12.1)`. Это единственный коммит
исполнения фазы. Всё, что произвели P12.2–P12.9, лежит в рабочем дереве:

```text
$ git status --porcelain | awk '{print $1}' | sort | uniq -c
     22 ??      # untracked
     76 M       # modified
      7 R       # staged renames
     56 RM      # staged rename + unstaged modification
```

Среди untracked — сами носители результата фазы:

```text
?? scripts/package-graph-gate.php          # Files P12.3
?? scripts/package-graph-gate.json         # Files P12.3
?? tests/Arch/PackageGraphTest.php         # Files P12.3
?? artifacts/                              # artifacts/P12-package-graph/report.json, Files P12.3
?? packages/search/src/SearchManager.php   # Files P12.5
?? packages/attributes/src/Data/           # Files P12.7
?? packages/core/src/Contracts/DataScopeAuthorizer.php  # Files P12.8
?? docs/audit-2026-09-17.md                # Files P12.9
?? docs/audit-2026-09-17-remediation.md    # Files P12.9
?? docs/audit-2026-09-17-deferred.md       # Files P12.9
?? plans/.../phases/P12/P12.closed.md      # сама проекция закрытия фазы
```

Нарушены:

- §1 «Repository files are the source of truth» — восемь item закрыты по состоянию,
  которого в репозитории нет.
- §7 «Close with one scoped commit containing implementation and affected bookkeeping
  by default» — ни один terminal-event P12.2–P12.9 не несёт commit-ссылки, и коммита нет.
- Контракт фазы `P12.md:25-27` требует «актуального `p12-rebaseline-receipts/1` seal»;
  seal сам не закоммичен, поэтому не воспроизводим ни из одной ревизии.

Следствие: любой cold-start-root, читающий committed-карьеры, увидит P12 незавершённой,
а `handoff.md` — указывающим на P8.1 поверх несуществующего состояния. Одна `git clean -fd`
или свежий clone уничтожают восемь item.

### R2 — `composer lint:check` красный; доказано, что это регрессия P12

`P12.3.md:22` объявляет Validation: «`composer analyse` и `composer lint:check` из корня».
Фактический прогон:

```text
$ composer lint:check
{"tool":"pint","result":"fail","files":[ …14 product-файлов… ]}
exit=1
```

Красные файлы принадлежат change-set'у P12: `packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php`,
`packages/tasks-workflow/src/Internal/MaterializationWakeupHandler.php`,
`packages/tasks-workflow/src/TasksWorkflowServiceProvider.php`,
`packages/transfer/tests/Pg/DatabaseTransferRunnerTest.php`, `packages/transfer/tests/Pg/ExportPgTest.php`,
`packages/search-intl/tests/Unit/OptionalBridgesTest.php`,
`packages/search/src/Internal/Query/PolicySearchHydrator.php`, `packages/search/tests/Unit/PolicySearchHydratorTest.php`,
`packages/attributes/tests/Pg/QueuedSchemaOperationsTest.php`, `packages/attributes/tests/Unit/SupportTest.php`,
`packages/attributes/tests/Unit/AttributesServiceProviderTest.php`,
`packages/intl/src/IntlServiceProvider.php`, `packages/intl/src/Internal/Reference/SqlDatasetImporter.php`,
`packages/intl/src/Internal/Money/SqlRateImporter.php`.

Красной является и **per-package** команда — ровно та, чей `exit=0` записан в receipt'ах:

```text
$ composer --working-dir=packages/intl           lint:check   exit=1
$ composer --working-dir=packages/transfer       lint:check   exit=1
$ composer --working-dir=packages/search         lint:check   exit=1
$ composer --working-dir=packages/search-intl    lint:check   exit=1
$ composer --working-dir=packages/attributes     lint:check   exit=1
$ composer --working-dir=packages/tasks-workflow lint:check   exit=1
```

Ср. `findings/P12-rebaseline-receipts.md:108-111` (tasks-workflow → `{"tool":"pint","result":"passed"}`),
`:200-203` (transfer → `exit=0`), `:214-217` (search/search-meilisearch → `exit=0`),
`:227-230` (search-intl → `exit=0`), `:241-244` (attributes → «pint passed»).

Что это именно регрессия P12, а не унаследованный долг, проверено напрямую: HEAD-версии
тех же файлов проходят Pint.

```text
$ git show HEAD:<file> → изолированная копия + корневой pint.json
$ vendor/bin/pint --test
{"tool":"pint","result":"passed"}
exit=0
```

(файлы: `packages/intl/src/IntlServiceProvider.php`, `packages/intl/src/Internal/Money/SqlRateImporter.php`,
`packages/search/src/Internal/Query/PolicySearchHydrator.php`,
`packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php`.)

§7: «Any red carrier keeps the item in progress». §10-контур P12.9
(`findings/P12-rebaseline-receipts.md:290-299`) перечисляет шесть проверок и `lint:check`
среди них **нет** — карьер не был прогнан при печати фазы.

### R3 — P12.5: terminal-evidence не привязан к спеке

`journal.jsonl`, terminal-event P12.5: `terminal_evidence.spec_sha256 = b3fd4e2321ae86015eec61bbf636fc5b2beee0dfc30dbea6b5e083eea31bf871`.

Фактически:

```text
$ sha256sum phases/P12/P12.5.md
ee0201b85a329bcea26dceee90d0d46e2422e38fe7f28f7a4e1e4dab271f6c4f
```

Тот же `ee0201b8…` записан в seal-таблице `findings/P12-rebaseline-receipts.md:309`.
Остальные восемь item совпадают байт в байт. §7 требует terminal-evidence, «bound to the
current item-spec digest»; для P12.5 привязки нет, и происхождение `b3fd4e23…` не
восстанавливается ни из одного карьера в дереве.

### R4 — design-гейт плана красный; записанный Next не допущен

`plan.md` («Обязательная политика дизайна — D4 / D6») требует перед product mutation
**каждого** item: «`python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py
--check design`. Отсутствующая детализация/RAG/общий finish = DESIGN_PENDING, выполнение
не начинается».

```text
$ python3 scripts/task-check.py --check design
Task runtime check FAIL: DESIGN_PENDING: finish input changed: phases/P12/P12.md
```

Причина — рассогласование sealed-байтов:

```text
artifacts/design-finish.md:686  | `phases/P12/P12.md` | `80512d422e05ef44caa8481d3dbadf44e3c0dd76b994b13848a62479b0668e32` |
$ sha256sum phases/P12/P12.md
8218c0595f4aafe91f3877ae43b6d74703488bd803f65c8d6aef4bc57ad76693
```

`phases/P12/P12.md` перезаписан в 10:36 — в ту же минуту, что и `phases/P12/P12.closed.md`,
то есть при закрытии фазы в P12.9; `artifacts/design-finish.md` остался от 08:16 и
не перегенерирован. `plan.md` («Рациональное исполнение», «Закрытие и recovery») прямо
предписывает при фактическом изменении sealed normative inputs обновить current design
binding после регенерации, сохранив исходный baseline и неизменные hashes — этого не сделано.

Следствие: `handoff.md` записывает `Next: exec-items … P8.1` с `Context: continue-root`,
но P8.1 не может начать product mutation, пока design-гейт красный. Записанный следующий
шаг не исполним в момент записи.

## ATTENTION

### A1 — гейт P12.3 материально ослаблен

Гейт зелёный и находит реальное расщепление `CoreX\Tenancy\`, но два из трёх его
конфигурационных механизма не делают того, что заявлено.

**(a) allowlist проверяет строку пути, а не наличие охраны.**

```350:371:scripts/package-graph-gate.php
function violatesUndeclaredDependency(array $fromMeta, string $toPackage, string $relativeFile, array $allowlist): bool
{
    if (in_array($toPackage, $fromMeta['require'], true)) {
        return false;
    }

    foreach ($allowlist as $entry) {
        if (($entry['from'] ?? '') !== $fromMeta['name']) {
            continue;
        }

        if (($entry['to'] ?? '') !== $toPackage) {
            continue;
        }

        if (($entry['guard_file'] ?? '') === $relativeFile) {
            return false;
        }
    }

    return true;
}
```

`guard_file` сравнивается как строка; существование охраны внутри файла не проверяется.
Четыре из восьми записей называют файл, в котором охраны для объявленной зависимости нет:

| `scripts/package-graph-gate.json` | `guard_file` | Фактически в файле |
|:--|:--|:--|
| `:24` | `packages/tasks-workflow/src/Internal/MaterializationWakeupHandler.php` | единственный guard — `bound(DataScopeAuthorizer::class)` (`:45`), к `corex/wakeup` отношения не имеет |
| `:30` | `packages/tenancy/src/Wakeup/Internal/RootRegistryWakeupHint.php` | ни `interface_exists`, ни `class_exists`, ни `->bound(` |
| `:36` | `packages/tenancy/src/Wakeup/Internal/RootWakeupReconciler.php` | то же — охраны нет |
| `:48` | `packages/attributes/src/Internal/Registry/RegistryFieldCatalogue.php` | то же — охраны нет |

Решение P12.1 оставить `corex/wakeup` в `suggest` держится именно на охранах
(`findings/P12-rebaseline-receipts.md:42-45`), а гейт, который должен их удерживать,
не проверяет ничего.

**(b) `reservations` — мёртвый и инвертированный механизм.**

Дизайн §2.3 и `D41:62-68` требуют трёх условий, среди которых «хост определяет ноль классов
под префиксом». Проверка устроена так, что этого случая не достигает:

```441:464:scripts/package-graph-gate.php
            $owners = packagesWithClassesUnderPrefix($overlap, $classIndex);

            if (count($owners) < 2) {
                continue;
            }
            …
            if (findReservation($overlap, $owners, $config['reservations']) !== null) {
                continue;
            }
```

Для `CoreX\Search\Meilisearch\` хост `corex/search` определяет ноль классов
(`rg "namespace CoreX.Search.Meilisearch" packages/search/src/` — пусто), поэтому
`$owners` содержит один пакет, срабатывает `continue` на `:443-445`, и до
`findReservation()` управление не доходит. Резервация не проверяется никогда.

Обратный случай — тот, который §2.3 запрещает:

```570:583:scripts/package-graph-gate.php
    foreach ($reservations as $reservation) {
        if (($reservation['prefix'] ?? '') !== $prefix) {
            continue;
        }

        $host = (string) ($reservation['host'] ?? '');
        $owner = (string) ($reservation['owner'] ?? '');

        if (in_array($host, $packages, true) && in_array($owner, $packages, true) && count($packages) === 2) {
            return $reservation;
        }
    }
```

`in_array($host, $packages, true)` требует, чтобы хост **присутствовал среди владельцев
классов** под префиксом. Резервация признаётся действительной ровно тогда, когда хост
определяет там классы, — то есть гейт молча пропустил бы нарушение §2.3, ради ловли
которого и вводился.

**(c) резервация не печатается.** Отчёт (`scripts/package-graph-gate.php:110-131`) содержит
`legacy_namespace_splits`, `violations`, `summary` — поля `reservations` нет, и в
`artifacts/P12-package-graph/report.json` объявленная резервация отсутствует. Для legacy-splits
требование «печатать на каждом прогоне» выполнено; для резерваций аналога нет.

Отдельно: `parseUseImport()` (`:279-295`) возвращает `null` для групповых импортов
`use A\{B, C};`, а `normalizeImport()` (`:297-302`) берёт только первый элемент
списка `use A\B, C\D;`. В текущем дереве таких импортов нет, но заявленного смягчения
этого режима отказа в `P12.3.md:17` тоже нет — там объявлено только смягчение
longest-prefix-резолва.

### A2 — effort undershoot

Routing (`plan.md:196-204`, `P12.md:144-152`) пинит `implementation/high` для P12.3, P12.5,
P12.7, P12.8. Actor каждого terminal-event в `journal.jsonl` — `executor/composer-2.5/medium`.
§5: «undershoot, ambiguity, or unsupported capability blocks before mutation». Четыре item
исполнены ниже пина; ни одна запись undershoot не зафиксирована.

(P12.2 пинится `low`, исполнен `medium` — overshoot, допустим, но тоже не записан.)

### A3 — `Review: full` не исполнен

Routing требует `full` для P12.1, P12.3, P12.5, P12.7, P12.8 — с явным обоснованием
«ошибочный гейт даёт ложную зелёную» (`P12.md:147`). В `journal.jsonl` нет ни одного
review-события, в `findings/` нет reviewer-возврата в форме `isolated-child-return`,
`artifacts/` не содержит каталога P12-review. Дефект A1 — ровно тот класс, который full
review для P12.3 должен был поймать.

### A4 — batch-ID расходятся с Routing

| Item | Routing `Batch` | `journal.batch_receipt.batch_id` |
|:--|:--|:--|
| P12.3 | `B-P12-CONTOUR` | `B-P12-GATE` |
| P12.5 | `solo` | `B-P12-SEARCH` |
| P12.7 | `solo` | `B-P12-ATTRIBUTES` |

Batch-ID придуманы при исполнении. §5 требует фиксировать ambiguity/stale как
non-mutating (`BATCH_AMBIGUOUS`/`BATCH_STALE`), а `batch_id=solo` с membership из одного
item — назвать конкретную границу; вместо этого Routing-таблица и journal разошлись молча.

### A5 — отсутствуют start-события journal

`in-progress` события есть только у P12.7, P12.8, P12.9. У P12.1–P12.6 — только terminal.
§7 требует «journal start/terminal events». Шесть item не имеют записи начала работы,
и `attempt`/route-evidence для них не реконструируются.

### A6 — receipt'ы не содержат вывода, которого требуют спеки

Deliverables спек требуют «Receipt с фактическим выводом». P12.1 и P12.9 этому отвечают;
P12.2–P12.8 — нет.

| Item | Требование Deliverables | В `findings/P12-rebaseline-receipts.md` |
|:--|:--|:--|
| P12.2 | «перечнем пакетов без каталога `tests/` как явных дыр покрытия» | `:167-169` — условная фраза «если остаются» вместо перечня; фактический ответ — таких пакетов нет |
| P12.3 | «Receipt с выводом **обеих ветвей** — зелёной и красной» | `:176-183` — только зелёная ветка; красная ветка (exit 1 с адресами) не показана |
| P12.4 | «выводом sweep'а, доказательством сохранности истории» | `:192-197` — пересказ «нет runtime-ссылок»; вывода sweep'а и доказательства истории нет |
| P12.5 | «выводом четырёх наблюдаемых проверок конфигурации» | `:207-218` — ни одной из четырёх проверок |
| P12.6 | «выводом sweep'а, доказательством сохранности истории» | `:221-231` — ни того, ни другого |
| P12.7 | «совпадения имён таблиц до и после и отсутствия `config()` в движках» | `:234-247` — есть только пустой дифф миграций |
| P12.8 | «сверкой списка обращений до и после» | `:250-263` — «до» отсутствует, показан только результат `rg` после |

Все эти утверждения при проверке оказались истинными (см. GREEN-часть) — дефект в том,
что receipt их не доказывает, а фаза закрыта именно по receipt'ам.

### A7 — неверно атрибутированная цифра контура

`findings/P12-rebaseline-receipts.md:158-160` и §10-контур `:297` приводят
`composer test:packages` → «472 passed, 7 skipped (pg-only)». Скрипт — цикл `for` по 20
пакетам без агрегата; «7 skipped, 472 passed (1505 assertions)» — итог одного
`packages/tenancy` (заголовок `==> packages/tenancy` предшествует этой строке, следующий
заголовок — `==> packages/wakeup`). Контур действительно зелёный (exit=0), но цифра
характеризует один пакет из двадцати и в этом виде попала в печать фазы.

### A8 — `P9.2` ссылается на переименованные пути

```13:13:plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/phases/P9/P9.2.md
**Files:** … packages/import-export/composer.json; packages/import-export/CHANGELOG.md; packages/import-export/UPGRADE.md; … packages/intl-search/composer.json; packages/intl-search/CHANGELOG.md; packages/intl-search/UPGRADE.md; …
```

Этих каталогов нет: P12.4 переименовал `packages/import-export/` → `packages/transfer/`,
P12.6 — `packages/intl-search/` → `packages/search-intl/`. `Files` связывает записи item;
P9.2 упадёт fail-closed. `D41:194-197` прямо называет причиной блокировки P8–P11 то, что
«работа поверх старых имён породила бы переделку», — но обратная правка спек P8–P11 в P12
не выполнена. Других устаревших ссылок в P8–P11 нет: `SearchProvider`, `SearchManager`,
`DataScope`, `CoreX\Transfer`, `test:packages`, `package-graph` в спеках P8–P11 не
упоминаются, `scripts/distribution/**` старых имён не содержит.

## Проверенное и не подтвердившееся как дефект

- **Deferred ledger не «выдаёт отложенное за решённое».** `docs/audit-2026-09-17-deferred.md:9-19`
  перечисляет девять пунктов с матричными ID, требуемым гейтом и ADR-предложением;
  `:41-49` явно ограничивает выданные разрешения одним G-API. Прецедент формы
  (`docs/audit-2026-09-14*.md`) существует.
- **`expires_when` согласован между ledger и гейтом.** `docs/audit-2026-09-17-deferred.md:25`
  и `scripts/package-graph-gate.json:65` содержат одну и ту же строку.
- **`test:packages` не прячет пропуски.** 20 пакетов, все с каталогом `tests/`, exit=0.
- **Миграции не тронуты** — требование `P12.md:49-50` выполнено.
- **`anti-weakening-gate.sh` зелёный**, счётчики тестов не уменьшены (sqlite 469→469, pg 507→507).

## Отсутствие audit-admission

На момент запроса в `decisions/` нет принятого `audit-admission/v2` (последнее решение — D41).
§4 допускает admitted phase audit только по такому решению. Аудит выполнен по прямому
поручению владельца; провенанс owner-message зафиксирован в D42, который оформляет
admission ретроспективно вместе со scope/routing коррекции. Вердикт RED опирается на
воспроизведённые доказательства, а не на admission.

## Рекомендация

Корректирующая фаза **P12A** (шесть item, спеки — `phases/P12A/`), решение — `D42`.

| Item | Устраняет |
|:--|:--|
| P12A.1 | R4 — регенерация design binding, снятие `DESIGN_PENDING` |
| P12A.2 | R2 — зелёный `composer lint:check` без смены поведения |
| P12A.3 | R1 — единственный scoped-коммит с результатом P12.2–P12.9 |
| P12A.4 | A1 — фактическая проверка охраны и резервации в гейте |
| P12A.5 | R3, A2–A7 — коррекция plan-bookkeeping дозаписью |
| P12A.6 | A8 — `Files` в `P9.2.md`; печать P12A; handoff на P8 |

P8 остаётся заблокированным до закрытия P12A: `handoff.md` до этого аудита указывал на
P8.1 поверх незакоммиченного дерева с красным `lint:check` и красным design-гейтом.
