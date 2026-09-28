---
id: D44
date: 2026-09-28
status: accepted
item: P02
items: [P02, P02.1, P02.2, P02.3, P02.4]
supersedes: []
superseded_by: null
---
# D44 — P02 transfer mechanics (accepted)

**Actor:** author-choice (claude-opus-5-5/high, `task:plan-design … P02`) по мандату владельца B6 («есть идеальное решение — утверждай»).
**Evidence:** RAG:— `research/P02-design.md` §2 (F1–F9), `artifacts/P02-design/facts/README.md`; дизайн-прогон `p02-transfer.py plan` (0 problems) и `apply --no-rector --allow-seams` в scratch-worktree; RAG:✅ `research/P02-design-rag.md` E1 (Rector RenameClassRector).

## Решение

1. **Порядок.** Швы до переноса: P02.1 (ядро: D27, D28, D32, тестовые харнессы D35 вне operations) → P02.2 (D31 швы 1–3, 5 и тестовая граница operations) → P02.3 механический перенос → P02.4 идентификаторы и гейты. Шов 4 D31 (Validation/Pagination) — только строки карты, выполняет перенос.
2. **Инструмент P02.3** — `artifacts/P02-design/scripts/p02-transfer.py` + `p02-literal-pass.php`: preflight (чистое дерево, карты OK, из 136 строк-швов остаются только 18 строк `CoreX\Api\Tests\TestCase`, их снимает scaffold собственных `TestCase` tools/webhooks) → literal pass (строковые литералы, комментарии, не-PHP текст: FQCN, namespace-правила без неоднозначности, пути, composer-имена) → Rector `RenameClassRector` с полной картой классов (только ссылки в коде) → переписывание объявлений `namespace` → `git mv` по карте, `extra-moves.tsv` и переименованиям каталогов → манифесты (один PSR-4 корень, требования по проецированным рёбрам, опциональные интеграции остаются в `suggest`/`require-dev`) → удаление tombstone. Каждый токен переписывается ровно один раз; цепочек `old→new→…` нет (0 в плане).
3. **Сухой прогон обязателен:** полный `apply` (с Rector) в scratch `git worktree` + полный стек проверок до применения в рабочем дереве; итог сравнивается пофайлово (`git diff --stat` обоих прогонов идентичен).
4. **Новые файлы швов** создаются в текущей раскладке (старые namespace) и получают строку `namespace-map.tsv` из `facts/new-map-rows.tsv`, если их каталог не покрыт; `check-maps.py` (default, `--dependencies`) exit 0 после каждого item; `--write-edges` регенерирует рёбра после шва.
5. **Golden в швах:** routes/events/wire не меняются; `php-surface` меняется только в типах, объявленных в файлах диффа item (`golden-delta.py`), с одной строкой `REBASELINE.md` на item. Перенос P02.3 и идентификаторы P02.4 проходят golden **без** rebaseline (rename-map регенерируется до переноса).
6. **Ratchet-проекция** (anti-weakening, ceilings guard): при split/merge/rename пакетов потолок сравнивается по группе преемников — сумма после ≤ сумма до для `{core, modules}→{core}`, `{operations}→{operations, tools, webhooks}`, `attributes→custom-fields`, `crm→flex-crm`; остальные ключи — поштучно, общий итог не растёт. Нарушения ownership-baseline сравниваются после проекции composer-имён. Таблица преемников — `scripts/ratchet-successors.json`. Это не ослабление: ни один долг не переносится без равного или большего сокращения.
7. `artifacts/P00/namespace-map.tsv`: строка `StubToolHandler.php` получает фактический PSR-4 префикс `CoreX\Modules\Tests\` (P01 добавил per-package `autoload-dev`); ключ и цель не меняются.

## Почему

- Plan §2: массовые переносы — инструментом с картой, ручные правки — остаток; без алиасов (D01).
- Rector переименовывает ссылки и docblock-типы, но не объявления, строки и файлы (E1) — их берёт скрипт; разделение литерал/код исключает двойную замену.
- Швы до переноса: карта и `check-maps.py` работают только по исходной раскладке; после переноса граф проверяют ownership, package-graph и dependency-analyser.
- Отклонено: чистый regex-перенос кода (не видит неявных same-namespace ссылок); перенос без сухого прогона (≥500 файлов); rebaseline golden в P02.3 (стёр бы доказательство «перенесено, а не изменено»).

## Consequences

P02.1–P02.4 Validation ссылаются на эти шаги; P03 расширяет карту новыми строками (D34) и переиспользует `p02-transfer.py`.
