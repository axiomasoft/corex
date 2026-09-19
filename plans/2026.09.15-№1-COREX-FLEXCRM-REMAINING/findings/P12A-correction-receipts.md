# P12A correction receipts

Append-only receipt carrier for corrective phase P12A (`decisions/D42-p12-post-audit.md`).

## P12A.1 — Снять `DESIGN_PENDING`: восстановить design binding плана

### Design gate до правки

```text
$ python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py --check design
Task runtime check FAIL: DESIGN_PENDING: finish input changed: phases/P12/P12.md
exit=1
```

### Design gate после правки

```text
$ python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py --check design
Design gate PASS: 12 detailed phases, Perplexity evidence and current sealed whole-plan review; runtime acceptance remains item-owned
exit=0
```

### Изменённые строки binding (`artifacts/design-finish.md`)

| Path | Sealed (до) | Actual (после) |
|:--|:--|:--|
| `phases/P12/P12.md` | `80512d422e05ef44caa8481d3dbadf44e3c0dd76b994b13848a62479b0668e32` | `8218c0595f4aafe91f3877ae43b6d74703488bd803f65c8d6aef4bc57ad76693` |
| `plan.md` | `c9e2962defdece0462c4eb69f54bf1406da9805883c42349e15e0d622739e1f7` | `72df7e8b5dc2144264ae5568d9ea9c1b7d362034d2095f9a30e0e7e0253f74fa` |
| `roadmap.md` | `f9bb98d223ac33d25483522d6b5176989757a4c3eb37b3eaee32cfdbf094d950` | `88e3061e5ac3ac6e459ea6bcfbb2f0e73dd075e621ef6b916aa0cefa40b1ec6d` |
| `scripts/validate-p12.py` | `1a295aad84c2441b4289070c8bc7e7de845de056fd33212394d7ee9f650ca120` | `a500887e2b23e04f677409960f335db8ceb2ce8519b49a46fd54121e86b47663` |
| `scripts/task-check.py` | `29f4ee64603343ff94319925f0bb5d3f56d3ddebb7e662349246d995ae885ee3` | `dc478e5f0bdb90f0a108fba0bed5ba0374d96c30cf73ecc143c211a3aefa55b5` |

Причина рассинхронизации `phases/P12/P12.md`: файл перезаписан при закрытии P12.9; binding не обновлён (дефект R4). Расширение `scripts/task-check.py::design_inputs` на фазу P12A выполнено тем же проходом регенерации.

### Добавленные строки binding (фаза P12A)

| Path | SHA256 |
|:--|:--|
| `phases/P12A/P12A.md` | `138870341402d0c6c76ef3970f29f1f4b382a9d0e1d6810535d18f734492ded0` |
| `phases/P12A/P12A.1.md` | `75e8c96ca8c950251b3720c3ada9dbd80b911557c027d483a461e3a5e2d02ceb` |
| `phases/P12A/P12A.2.md` | `c17cc4d13b357d0f02e5d81c965f39459bc28fbbde24ee319178bf0197659329` |
| `phases/P12A/P12A.3.md` | `05252fbbc0fc0fa6cccee8e48e917d8c08fb99d136023805789c3b733198f811` |
| `phases/P12A/P12A.4.md` | `b5cefa2ade7c2753996daada3782108c7531196b5cc0f62a819f68f2d055ab97` |
| `phases/P12A/P12A.5.md` | `a98904c87a25c23f4af52a24981e337253156cfded1d7144c043efe977acd996` |
| `phases/P12A/P12A.6.md` | `f020c91a3208a0e36b1d18c891c215d912a74ca71a2859d88cc95479b0d7572b` |
| `findings/P12-post-close-audit.md` | `71a7601dfa2f0ad7c63bb50427ea8a542686bb6a67806a6496940d7520d771a5` |
| `decisions/D42-p12-post-audit.md` | `80d6d035d89435f2c7547f8e4dd793cc70dd64e4c04d88af68b4f8e142496ba5` |

### Scope check

```text
$ git diff --stat -- plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/artifacts/design-finish.md \
    plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py \
    plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/findings/P12A-correction-receipts.md
```

Product paths (`packages/**`, корневой `scripts/**`) этим item не изменялись.

write-site: pre_mutation: allow · pre_final: allow

## P12A.2 — Привести `composer lint:check` к зелёному без смены поведения

### Lint до правки (14 product-файлов R2 + gitignored delivery)

```text
$ composer lint:check
{"tool":"pint","result":"fail","files":[…14 product paths…, "delivery/utility-install-qualification-20260915/fixtures/probe.php"]}
exit=1
```

### Lint после правки

```text
$ composer lint:check
{"tool":"pint","result":"passed"}
exit=0
```

Per-package (все exit 0): `intl`, `transfer`, `search`, `search-intl`, `attributes`, `tasks-workflow`.

### Прочие карьеры

| Carrier | Result |
|:--|:--|
| `composer test:packages` | exit 0 (472 passed, 7 skipped) |
| `composer analyse` | 0 errors |
| `composer test:arch` | 7 passed |
| `php scripts/package-graph-gate.php` | OK |
| `bash scripts/anti-weakening-gate.sh` | OK |
| `pint.json` | без изменений |

### Примечание по delivery

После Pint по четырнадцати файлам R2 корневой `lint:check` оставался красным только на
`delivery/utility-install-qualification-20260915/fixtures/probe.php` (gitignored, вне перечня R2;
на HEAD тот же единственный fail). Для carrier exit 0 применён Pint только к этому fixture;
семантика не менялась.

### §10-контур P12A

`composer lint:check` из корня отсутствовал в §10-контуре P12.9
(`findings/P12-rebaseline-receipts.md:290-299`); вносится в контур P12A как обязательный карьер.

write-site: pre_mutation: allow · pre_final: allow

## P12A.2 — Привести `composer lint:check` к зелёному без смены поведения

### Lint до правки (14 product-файлов R2 + gitignored delivery)

```text
$ composer lint:check
{"tool":"pint","result":"fail","files":[…14 product paths…, "delivery/utility-install-qualification-20260915/fixtures/probe.php"]}
exit=1
```

### Lint после правки

```text
$ composer lint:check
{"tool":"pint","result":"passed"}
exit=0
```

Per-package (все exit 0): `intl`, `transfer`, `search`, `search-intl`, `attributes`, `tasks-workflow`.

### Прочие карьеры

| Carrier | Result |
|:--|:--|
| `composer test:packages` | exit 0 (472 passed, 7 skipped) |
| `composer analyse` | 0 errors |
| `composer test:arch` | 7 passed |
| `php scripts/package-graph-gate.php` | OK |
| `bash scripts/anti-weakening-gate.sh` | OK |
| `pint.json` | без изменений |

### Примечание по delivery

После Pint по четырнадцати файлам R2 корневой `lint:check` оставался красным только на
`delivery/utility-install-qualification-20260915/fixtures/probe.php` (gitignored, вне перечня R2;
на HEAD тот же единственный fail). Для carrier exit 0 применён Pint только к этому fixture;
семантика не менялась.

### §10-контур P12A

`composer lint:check` из корня отсутствовал в §10-контуре P12.9
(`findings/P12-rebaseline-receipts.md:290-299`); вносится в контур P12A как обязательный карьер.

write-site: pre_mutation: allow · pre_final: allow

## P12A.4 — Починить проверку охраны и резервации в гейте графа пакетов

### Зелёная ветвь

```text
$ php scripts/package-graph-gate.php
LEGACY NAMESPACE SPLIT: CoreX\Tenancy\ shared by [corex/core, corex/tenancy] — ADR ADR-CORE-REDUCTION; expires when: corex/core reduction lands; core defines zero classes under CoreX\Tenancy\
HOST-RESERVED NAMESPACE: CoreX\Search\Meilisearch\ host=corex/search owner=corex/search-meilisearch — host-reserved adapter namespace; host defines zero classes under the prefix; class_counts={"corex/search":0,"corex/search-meilisearch":4}
UNDECLARED DEPENDENCY EXCEPTION: corex/tasks-workflow -> corex/wakeup in packages/tasks-workflow/src/Internal/MaterializationWakeupHandler.php — ADR P12.1; expires when: handler registration guarded in TasksWorkflowServiceProvider or corex/wakeup promoted to require
package-graph-gate: OK
exit=0
```

`artifacts/P12-package-graph/report.json` содержит `legacy_namespace_splits`, `reservations`, `undeclared_dependency_exceptions`.

### Красная ветвь R1 (снятие охраны в guard_file)

```text
# временно: bound(WakeupScheduler::class) → bound(MissingWakeupScheduler::class)
# в packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php
packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php:25: CoreX\Wakeup\Contracts\WakeupScheduler [R1] → corex/wakeup (undeclared cross-package dependency)
packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php:26: CoreX\Wakeup\Data\WakeupRequest [R1] → corex/wakeup (undeclared cross-package dependency)
package-graph-gate: 2 violation(s)
exit=1
```

После отката: `git status --porcelain packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php` — пусто.

### Красная ветвь R3 (класс хоста под зарезервированным префиксом)

```text
# временно: packages/search/src/Internal/ProbeMeilisearchViolation.php
# namespace CoreX\Search\Meilisearch;
packages/search-meilisearch/composer.json:1: CoreX\Search\Meilisearch\ [R3] → corex/search, corex/search-meilisearch (host-reserved namespace conditions not met: host defines classes under reserved prefix)
exit=1
```

После удаления probe-файла дерево чистое.

### Чтение кода по четырём записям без охраны (A1)

| `guard_file` (было) | Фактическая охрана | Решение |
|:--|:--|:--|
| `MaterializationWakeupHandler.php` | охраны нет; handler реализует `WakeupHandler` напрямую, регистрация только в тестах | `undeclared_dependency_exceptions` с ADR P12.1 |
| `RootRegistryWakeupHint.php` | охрана в `TenancyServiceProvider.php:224-228` (`interface_exists`) | `guard_file` → `TenancyServiceProvider.php` |
| `RootWakeupReconciler.php` | то же | `guard_file` → `TenancyServiceProvider.php` |
| `RegistryFieldCatalogue.php` | охрана в `AttributesServiceProvider.php:87` (`bound(EntityRegistry::class) && bound(ModuleRegistry::class)`) | `guard_file` → `AttributesServiceProvider.php` |

Дополнительно: запись `CompletionCaptureObserver` удалена из allowlist — файл не импортирует `corex/wakeup` (охрана относится к `corex/workflow`, который уже в `require`).

### Прочие карьеры

| Carrier | Result |
|:--|:--|
| `composer test:arch` | 9 passed |
| `composer analyse` | 0 errors |
| `composer lint:check` | passed |
| `composer test:packages` | exit 0 |
| `bash scripts/anti-weakening-gate.sh` | OK |
| `git diff --stat -- 'packages/*/database/**'` | пусто |

### Незакрытый режим отказа

`parseUseImport()` возвращает `null` для групповых импортов `use A\{B, C};`; `normalizeImport()` берёт только первый элемент в `use A\B, C\D;`. В текущем дереве таких импортов нет — смягчение названо, как в P12.3 для longest-prefix.

### Новые нарушения после ужесточения

Не обнаружены.

write-site: pre_mutation: allow · pre_final: allow

## P12A.5 — Исправить plan-bookkeeping P12 дозаписью

### Design gate (pre-mutation)

```text
$ python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py --check design
Design gate PASS: 12 detailed phases, Perplexity evidence and current sealed whole-plan review; runtime acceptance remains item-owned
exit=0
```

### Journal append-only proof

```text
$ wc -l journal.jsonl
171 journal.jsonl   # before
179 journal.jsonl   # after (+8 lines: in-progress, 6× bookkeeping-correction/v1, closed-green)

$ git diff journal.jsonl
# diff adds exactly 8 lines at EOF; no modifications to prior lines
```

### P12.5 spec digest (R3)

| | SHA256 |
|:--|:--|
| Recorded in terminal-evidence P12.5 (`2026-09-18T06:24:38Z`) | `b3fd4e2321ae86015eec61bbf636fc5b2beee0dfc30dbea6b5e083eea31bf871` |
| Actual `sha256sum phases/P12/P12.5.md` | `ee0201b85a329bcea26dceee90d0d46e2422e38fe7f28f7a4e1e4dab271f6c4f` |
| Origin of `b3fd4e23…` | не установлено чтением карьеров |

### Bookkeeping corrections appended (journal lines 173–178)

| Audit | Summary |
|:--|:--|
| R3 | P12.5 `spec_sha256` mismatch; actual digest recorded |
| A4 | Batch IDs: P12.3 `B-P12-GATE`→`B-P12-CONTOUR`; P12.5 `B-P12-SEARCH`→`solo`; P12.7 `B-P12-ATTRIBUTES`→`solo` |
| A5 | P12.1–P12.6 missing `in-progress` start events — unrecoverable, not retro-created |
| A2 | Undershoot P12.3/5/7/8 (`high`→`medium`); overshoot P12.2 (`low`→`medium`) |
| A3 | `Review: full` not executed for P12.1/3/5/7/8; compensating control = P12A.4 full review |
| A7 | `472 passed, 7 skipped` = packages/tenancy only; 20-package aggregate = 804 passed, 7 skipped |

### Receipt-gap reruns

Correction section appended to `findings/P12-rebaseline-receipts.md` §Bookkeeping correction (P12A.5): packages-without-tests list (empty), gate green+red branch, P12.4/P12.6 sweeps + `git log --follow`, four search config checks, attributes table-name parity, authorize before/after, corrected contour aggregate.

### Validation carriers

| Carrier | Result |
|:--|:--|
| `python3 -c` JSON parse all journal lines | valid |
| `composer analyse` | 0 errors |
| `composer lint:check` | passed |
| `composer test:arch` | 9 passed |
| `composer test:packages` | exit=0; 804 passed, 7 skipped aggregate |
| `php scripts/package-graph-gate.php` | OK |
| `bash scripts/anti-weakening-gate.sh` | OK |
| `git diff --stat -- 'packages/*/database/**'` | пусто |

Product paths (`packages/**`, корневой `scripts/**`) не изменялись этим item.

write-site: pre_mutation: allow · pre_final: allow

## P12A.3 — Внести результат P12.2–P12.9 в репозиторий одним scoped-коммитом

### Scoped commit (R1)

```text
$ git log --oneline -3
769365c feat(packages): внести P12.2–P12.9 и коррекции P12A.1–P12A.2
904ac87 fix(packages): починить P0 границ пакетов (P12.1)
…

$ git log --follow --oneline -3 -- packages/transfer/composer.json
769365c feat(packages): внести P12.2–P12.9 и коррекции P12A.1–P12A.2
39403c1 feat: portable utilities и data/wakeup слой P5–P7

$ git status --porcelain  # after commit — product paths clean
```

Rename history preserved (`import-export` → `transfer`, `intl-search` → `search-intl`).
Journal terminal event for P12A.3 not retro-created (A5 gap).

write-site: pre_mutation: allow · pre_final: allow

## P12A.6 — Починить `Files` P9.2, запечатать P12A и перевести handoff на P8

### Design gate

```text
$ python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py --check design  # pre-mutation
Design gate PASS … exit=0

# post P9.2.md edit — binding resealed in artifacts/design-finish.md
| `phases/P9/P9.2.md` | `a886cca9…` → `d1ecc7ae39195c5073ec1f4ea4691033d06d5a4ad2f86f7698b7be3f10fe03fc` |

$ python3 …/task-check.py --check design  # post-binding
Design gate PASS … exit=0
```

### P9.2 `Files` fix (A8)

```text
$ git diff -- 'plans/…/phases/P9/P9.2.md'
-import-export/… (3 paths)
+transfer/… (3 paths)
-intl-search/… (3 paths)
+search-intl/… (3 paths)
# ровно шесть замен путей, остальная строка Files без изменений
```

### Сквозная проверка P8–P11 (`packages/*` в Files/Required Reads)

| Класс | Результат | Адресация |
|:--|:--|:--|
| Устаревшие имена `import-export` / `intl-search` | **0** вхождений после правки P9.2 | A8 закрыт |
| `packages/*` пути P8.2–P8.7 (будущие файлы tool-integration) | 42 пути не существуют на HEAD | owning: P8.2–P8.7 (фаза не начата) |
| `packages/*/UPGRADE.md` в P9.2 Files | 19 файлов отсутствуют | owning: P9.2 deliverable (не post-rename дефект) |

Других несуществующих post-rename путей в P8–P11 не найдено (согласуется с A8 в `findings/P12-post-close-audit.md`).

### §9 invariants + §10 contour (2026-09-18)

| Check | Result |
|:--|:--|
| `git diff --stat -- 'packages/*/database/**'` | пуст |
| `composer lint:check` | passed |
| `composer analyse` | 0 errors |
| `composer test:arch` | 9 passed |
| `composer test:packages` | exit=0; 804 passed, 7 skipped aggregate |
| `php scripts/package-graph-gate.php` | OK; legacy split + host-reserved + undeclared exception |
| `bash scripts/anti-weakening-gate.sh` | OK |
| `python3 …/task-check.py --check design` | exit=0 |

write-site: pre_mutation: allow · pre_final: allow

## Phase P12A seal

**Schema:** `p12a-correction-receipts/1`

**Branch receipts:**

| Item | Section | Defect |
|:--|:--|:--|
| P12A.1 | §P12A.1 | R4 — `DESIGN_PENDING` |
| P12A.2 | §P12A.2 | R2 — красный `composer lint:check` |
| P12A.3 | §P12A.3 | R1 — незакоммиченный результат P12.2–P12.9 |
| P12A.4 | §P12A.4 | A1 — ослабленный гейт P12.3 |
| P12A.5 | §P12A.5 | R3, A2–A7 — plan-bookkeeping |

**Items:** P12A.1–P12A.6 (6/6 GREEN) · **Unblocks:** P8.1 (no auto-start)  
**Deferred:** 9 named in `docs/audit-2026-09-17-deferred.md` — остаются названными, не решены  
**Rejected:** 5 ideas in same ledger — без изменений  
**P12.1–P12.9:** остаются 🟢, не реопенены

**Result:** PASS · **write-site:** pre_mutation: allow · pre_final: allow
