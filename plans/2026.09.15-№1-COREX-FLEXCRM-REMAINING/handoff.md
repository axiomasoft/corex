# HANDOFF — 2026-09-18 — after P12.1

**Next:** exec-items: task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P12.2

| Параметр | Значение |
|---|---|
| Batch | B-P12-CONTOUR |
| Model class | implementation |
| Effort | low |
| Capabilities | — |
| Context | continue-root |
| Суть | Расширить `test:packages` с 8 до 20 пакетов; выдать `corex/core` и `corex/modules` скрипты test/analyse/lint. |

```
task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P12.2
```

**Done:** P12.1 закрыт: `IntlModule` наследует `CorexModule` (discovery больше не падает); из `TenancyManager` удалён импорт `StanclTenancyManager`; в `require` добавлены `corex/modules` (intl) и `corex/workflow` (tasks-workflow). Receipt — `findings/P12-rebaseline-receipts.md`.

**Ready:** P12.2 — dependency satisfied (P12.1 🟢). Bundle: `plan-views.py bundle --item P12.2`.

**Remaining:** P12.2–P12.9, затем P8.1–P8.7 и далее P9/P10/P11.

**Open risks:** Расщеплённый неймспейс `CoreX\Tenancy\` остаётся legacy-исключением для гейта P12.3. `corex/wakeup` у `tenancy` и `tasks-workflow` остаётся в `suggest` с файлами-охранниками (см. receipt P12.1). 14 унаследованных ошибок `task-check --check lint` — вне scope P12.

**Sources of truth:** `journal.jsonl`; `findings/P12-rebaseline-receipts.md`; `decisions/D41-package-boundary-rebaseline.md`; `research/P12-rebaseline-design.md`; `phases/P12/P12.md`.

**Workarounds/Deferred/Open questions:**
- workarounds: без изменений относительно design-finish.
- deferred: без изменений — ledger в P12.9.
- open_questions: —
