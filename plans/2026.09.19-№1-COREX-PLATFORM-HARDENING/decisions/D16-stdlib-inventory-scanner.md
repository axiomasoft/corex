---
id: D16
date: 2026-09-19
status: accepted
item: P2.1
items: [P2, P2.1, P2.2, P3]
supersedes: []
superseded_by: null
---
# D16 — Плановый сканер на stdlib `token_get_all`, без `nikic/php-parser`

**Actor:** plan-designer/cursor-grok-4.6/high

**Evidence:** `RAG:✅` 2026-09-19 — P2-Q1: tokenizer не даёт дерева и резолва имён;
PHP-Parser даёт NameResolver; строковые FQCN остаются convention. Источник требует
резолва, не конкретной библиотеки (захват `artifacts/P2-design-rag/q01-q02.md`).
`RAG:—` `scripts/package-graph-gate.php:364-366` пропускает group-use;
`plan.md` §2 запрещает новые runtime-зависимости и допускает `require-dev` только
названием в решении; сканер по правилу плана живёт в `plans/<ID>/scripts/`.

## Решение

Сканер фазы — плановый PHP на `token_get_all(..., TOKEN_PARSE)` плюс узкий разбор
грамматики `use` (обычный, `as`, group, `function`/`const`). `nikic/php-parser` в
корневой `require-dev` не вводится. Закрытый набор восстанавливаемых конструкций и
колонка `certainty` (`static` / `convention` / `unresolved`) заданы в
`research/P2-design.md` §3.

Существующий гейт графа не меняется. P2.2 считает рёбра, которые `parseUseImport`
гейт не видит (`G-GATE-BLIND`), и отдаёт число в P3.

## Почему

Эскиз предлагал php-parser «уровня dev-зависимости либо token_get_all для узкого
набора». Второй вариант выбран, потому что сканер — обоснование плана, а не
инструмент репозитория: зависимость в корневом Composer переживает план и меняет
продукт без owner-gate на состав поставки. Альтернатива «вендорнуть php-parser в
папку плана» отклонена как скрытая третья копия парсера. Альтернатива «оставить
regex гейта» отклонена: именно его слепое пятно фаза обязана измерить.

## Consequences

P2.1–P2.6 не трогают корневой `composer.json`. P3 вправе отдельно решить, чинить
ли `parseUseImport` через тот же узкий разбор или через `require-dev` анализатор —
это уже продуктовый гейт, не плановый сканер.
