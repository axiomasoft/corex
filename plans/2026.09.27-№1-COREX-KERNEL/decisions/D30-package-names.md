---
id: D30
date: 2026-09-28
status: accepted
item: P00.1
items: [P00.1, P02, P04, P07, P09]
supersedes: []
superseded_by: null
---
# D30 — package-names (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6); frontier-вердикт P00.1 в пределах `research/P00-design.md` §2.
**Evidence:** RAG:— `artifacts/P00-design/facts/{import-matrix.txt,zone-consumers.tsv}`, `packages/*/composer.json`, `artifacts/P00/*.tsv`; `check-maps.py` (default/`--dependencies`) exit 0 на `b624936`+P00.1.

## Решение

Набор и имена — `research/03-glossary.md` §Packages: `corex/attributes` → `axiomasoft/corex-custom-fields` (`CoreX\CustomFields`, classmap-каталоги сохраняют `Internal\<X>` и переезжают в `src/Internal/<X>`; F7); `corex/wakeup` → `corex-wakeup` без переименования (F8); `corex/access` → `CoreX\Access` (F10); `corex-search-meilisearch` → `CoreX\SearchMeilisearch` (F11); bridges — `search-intl`, `search-meilisearch`, `tasks-workflow`; `CoreX\Attributes\{Api,Spi}` (F12). Без исходного кода: `corex-access-azguard` (bridge, P07), `corex-full` (metapackage, P09). Не вводятся: `corex-testkit` (фейки — `CoreX\<Pkg>\Testing\`), `corex-timers`. Реализация по умолчанию `RRuleRecurrenceExpander` публична (`CoreX\Recurrence\`): у recurrence нет provider, её связывает потребитель.

## Почему

- PKG-001: имя = назначение; `attributes` конфликтует с PHP-атрибутами (F00 X4), `Auth` читается как authentication (A14.1).
- «wakeup» точно называет durable wakeup requests; `research/02` §2 предлагал `timers` с пометкой ≈ — отклонено (F8).
- testkit: нет независимого потребителя (pplx-02); существующий прецедент `CoreX\Modules\Testing`.

## Consequences

P02 переименования composer/namespace/каталогов; P04 атрибуты; P07 мост AzGuard; P09 metapackage.
