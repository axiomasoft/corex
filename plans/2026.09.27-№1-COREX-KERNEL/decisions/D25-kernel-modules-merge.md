---
id: D25
date: 2026-09-28
status: accepted
item: P00.1
items: [P00.1, P02]
supersedes: []
superseded_by: null
---
# D25 — kernel-modules-merge (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6); frontier-вердикт P00.1 в пределах `research/P00-design.md` §2.
**Evidence:** RAG:— `artifacts/P00-design/facts/{import-matrix.txt,zone-consumers.tsv}`, `packages/*/composer.json`, `artifacts/P00/*.tsv`; `check-maps.py` (default/`--dependencies`) exit 0 на `b624936`+P00.1.

## Решение

`corex/modules` сливается в ядро: `packages/modules/src/*` → `packages/core/src/Modules/*`, namespace `CoreX\Modules\<путь>` (N4), module id и composer-имя — `axiomasoft/corex`; config `corex-modules` → раздел `corex.modules`, tags `corex-modules-*` → `corex-*`; тесты → `packages/core/tests/Modules/{Feature,Unit}`, тестовая поддержка → `CoreX\Testing\Modules\`; миграции `mod_*` → `packages/core/database/migrations/`. Отдельного пакета modules нет.

## Почему

- 12 из 19 живых пакетов требуют пару `corex/core`+`corex/modules`; modules требует только core+illuminate (`packages/modules/composer.json`) — независимого потребителя нет (pplx-02, F1).
- Система модулей — определяющая ответственность L0 (`dev-doctrine.md` §1); ядро без неё не платформа.
- Отклонено: отдельный `corex-modules` — lockstep-пара без самостоятельной ценности, лишний release lifecycle.

## Consequences

P02 merge по `namespace-map.tsv` (строки `packages/modules/**`); валидаторы секций api/tools уходят из ядра (D32).
