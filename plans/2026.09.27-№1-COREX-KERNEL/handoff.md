# HANDOFF — 2026-09-28 — after P02

**Next:** audit-design: task:plan-audit 2026.09.27-№1-COREX-KERNEL design

| Параметр | Значение |
|:--|:--|
| Batch | n/a |
| Model class | frontier |
| Effort | high |
| Capabilities | — |
| Context | cold-start-root: whole-plan-independent-audit |
| Суть | Независимый аудит дизайна P00–P02 (текущий fingerprint) моделью, не автором дизайна P02. GREEN → `run-items` строки 5 execution-sheet (sol), если hosted CI GREEN; иначе `waiting` (D46). Порядок дальше — `roadmap.md` §Эстафета. |

**Provider-native command:**

```bash
codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-astra -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-audit 2026.09.27-№1-COREX-KERNEL design'
```

**Done:** `backlog/P02.md` → `phases/P02/P02.md` + `P02.1.md`…`P02.4.md`. Decisions D44 (порядок швы → перенос → идентификаторы; инструмент `p02-transfer.py` + Rector; сухой прогон; golden-delta; ratchet-проекция групп преемников; ремонт строки карты `StubToolHandler`) и D45 (идентификаторы по `identifier-map.tsv`, `corex.modules`, `tenancy` до P07, порядок разделов `corex:config:validate`, SPI компилятора, гейт полноты, удаление P15-гейта). Проверено на `d2e84a1`: карты OK (1756 рёбер), швы 136 (P02.1 47, P02.2 71, 18 — scaffold P02.3), план переноса 795 классов / 510 файлов / 0 problems, сухой прогон без Rector `verify: 0 problems`, гейт полноты «до» 8509 вхождений, golden-delta негатив FAIL. Внешний факт E1 (Rector) — capture perplexity; context7 без совпадений (записано).

**Remaining:** `task:plan-audit … design` — codex gpt-6-astra (D46: не автор дизайна и не executor строк P02). Finish выполнен: execution-sheet строки 5–7 (P02). Владелец: восстановить billing GitHub Actions, перезапустить `CI` на `d2e84a1`, приложить GREEN run к Phase Handoff P01 (O-3) — без этого P02.1 не стартует. Push не выполнялся.

**Sources of truth:** phases/P02/P02.md; phases/P02/P02.1.md–P02.4.md; research/P02-design.md; research/P02-design-rag.md; artifacts/P02-design/{examples.md,facts/README.md,scripts/}; artifacts/P02-design-rag/README.md; decisions/D44-p02-transfer-mechanics.md; decisions/D45-p02-identifiers-and-gates.md.

**Open risks:** Rector-шаг переноса не прогнан при дизайне (нет vendor в рабочем дереве) — VERIFY-AT-EXEC в обязательном сухом прогоне P02.3. Hosted CI (PHP 8.5 / PG 18) не проверен с P01. Разбиение 7 смешанных тестов operations — по правилу, не по готовому списку кейсов.

**Workarounds/Deferred/Open questions:** `tenancy` config-ключ остаётся до P07 (`upstream_config`). Безопасные коды `webhook_*` в `errors.json` остаются в engine до P06. `corex-tools` без config до P05.
