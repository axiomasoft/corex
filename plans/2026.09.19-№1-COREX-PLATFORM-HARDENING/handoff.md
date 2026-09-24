# HANDOFF — 2026-09-24 — after P10.5

**Next:** exec-items: task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P10.6

| Параметр | Значение |
|:--|:--|
| Batch | solo |
| Model | implementation |
| Thinking | high |
| Context | continue-root |
| Суть | P10.6 — восемь fail-closed write checks и schema-revision precondition. |

**Sources of truth:** `phases/P10/P10.5.md` ·
`decisions/D53-storage-is-per-entity-indexes-are-measured.md` · `journal.jsonl` ·
`packages/attributes/src/Internal/Storage/ResolvingAttributeStore.php` ·
`packages/attributes/src/Internal/Schema/AttributeIndexPlanner.php`.

**Done:** P10.5 GREEN. AttributeStore резолвится по entity, mixed loadMany сохраняет
порядок, неизвестный driver и per-field map красные. Expression B-tree создаётся
только после чистого cast и EXPLAIN, имя из id+revision, порядок включает record id.
Promoted остаётся seam без foreign DDL.

**Remaining:** P10.6–P10.8 и P11–P17.

**Open risks:** D48 multi-account relay ждёт starvation, SLO breach или manual
fan-out trigger. P10.6 не должен раскрывать существование поля во внешнем отказе.

**Pointers:** `phases/P10/P10.6.md` · `phases/P10/P10.md` ·
`research/P10-design.md` · `journal.jsonl`.
