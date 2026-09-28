---
id: D28
date: 2026-09-28
status: accepted
item: P00.1
items: [P00.1, P02]
supersedes: []
superseded_by: null
---
# D28 — savedviews-to-panels (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6); frontier-вердикт P00.1 в пределах `research/P00-design.md` §2.
**Evidence:** RAG:— `artifacts/P00-design/facts/{import-matrix.txt,zone-consumers.tsv}`, `packages/*/composer.json`, `artifacts/P00/*.tsv`; `check-maps.py` (default/`--dependencies`) exit 0 на `b624936`+P00.1.

## Решение

`core/src/SavedViews/**` → `CoreX\Panels\SavedViews\` (`packages/panels/src/SavedViews/`); контракты `SavedViewRepository`, `ViewAccessPolicy`, `ViewSchemaProvider` → `CoreX\Panels\SavedViews\Contracts\`; миграции `sys_saved_view*` → `packages/panels/database/migrations/pg/`; тесты `core/tests/Views` → `packages/panels/tests/SavedViews/`. Bindings из `CoreServiceProvider` переходят в `PanelsServiceProvider` (7 рёбер `remove`).

## Почему

- Единственный потребитель — panels (7 импортов); SavedViews — UI-состояние, не механизм платформы (F4).
- Иначе ядро → panels (kernel-dependency).

## Consequences

P02 перенос + provider wiring; таблицы — владение panels (сверка `table-ownership.md` в P08).
