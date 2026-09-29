# HANDOFF — 2026-09-29 — after P03

**Next:** audit-design: task:plan-audit 2026.09.27-№1-COREX-KERNEL design

| Параметр | Значение |
|:--|:--|
| Batch | n/a |
| Model class | frontier |
| Effort | high |
| Capabilities | — |
| Context | cold-start-root: whole-plan-independent-audit |
| Суть | Независимый аудит дизайна P00–P03 (текущий fingerprint после finish) моделью, не автором дизайна (D56). После GREEN — исполнение строки 8 execution-sheet (`roadmap.md` §P03 — последовательность команд, шаг 3). |

**Provider-native command:**

```bash
codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-audit 2026.09.27-№1-COREX-KERNEL design'
```

**Done:** Finish дизайна P00–P03 (2026-09-29): `plan-lint` OK, execution-sheet строки 8–9 согласованы с Routing P03, fingerprint записан. `backlog/P03.md` → `phases/P03/P03.md` + `P03.1.md`, `P03.2.md`. D57 (заменяет D05): корень `src/` пакета = корневой модуль, feature modules — подкаталоги с тем же набором; карта `artifacts/P03/module-map.tsv` — 278 переносов, 68 классов остаются на месте (внешний потребитель, `config/*.php` пакета, glossary), продукт вне N5 (N8), 0 коллизий; гейт `module-layout-gate` R0–R5 с ratchet-baseline (сейчас 436 → после переноса 159); ретайр красного `tenancy-structure-gate`; golden без rebaseline через `manual`-правила; glossary N5. Черновые выводы проверены, поправки — в D57 п.3–4. Проверено на `52446de`: скан воспроизводится, `p03-transfer.py plan` 0 problems, сухой прогон без Rector + `verify` 0 problems, гейт на перенесённом дереве = прогноз, тест гейта 3/3 OK.

**Remaining:** `task:plan-audit … design` Codex gpt-6-sol/high (не автор) → P03.1, P03.2 по execution-sheet строки 8–9 → фазовый аудит → закрытие; все команды — `roadmap.md` §P03 — последовательность команд. Носители плана сводит только автор плана (D56).

**Sources of truth:** phases/P03/P03.md; phases/P03/P03.1.md; phases/P03/P03.2.md; decisions/D57-p03-feature-module-layout.md; research/P03-design.md; research/P03-design-rag.md; artifacts/P03-design/examples.md; artifacts/P03-design/scripts/; artifacts/P03/; artifacts/P03-design/cloud/README.md; artifacts/P03-design-rag/README.md.

**Open risks:** Rector-шаг и полный тестовый стек переноса не прогонялись в дизайне (нет `vendor` в scratch-worktree) — проверяются в обязательном сухом прогоне P03.2. 23 публичных FQCN, которые называют другие пакеты, меняются (D01). Hosted CI отложен (D47).

**Workarounds/Deferred/Open questions:** 68 классов в `public-by-usage.tsv` и 91 межмодульная ссылка в `Internal` (R5) остаются в baseline гейта до P04. Модели/jobs без morph-алиаса: сохранённые FQCN не мигрируются (D01, CHANGELOG).
