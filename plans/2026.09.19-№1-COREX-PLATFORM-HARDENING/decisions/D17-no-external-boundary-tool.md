---
id: D17
date: 2026-09-19
status: accepted
item: P3.2
items: [P3, P3.2, P3.3]
supersedes: []
superseded_by: null
---
# D17 — Внешний инструмент границ не вводится; гейт графа получает tokenizer

**Actor:** plan-designer/cursor-grok-4.6/high

**Evidence:** `RAG:✅` 2026-09-19 — P3-Q1: Deptrac/PHPat не выражают класс правил,
который PHPStan Rule и полный статический граф не могут выразить; их ценность —
декларативный DSL поверх уже построенного графа, не новая семантика. Источник
прямо называет FQCN без `use`, `extends`/`implements` и vendor-containment как
то, чего **не видит** сканер одних `use`-строк (захват
`artifacts/P3-design-rag/q01-q03.md`). `RAG:✅` P3-Q5: `tomasvotruba/class-leak`
ищет неиспользуемые классы, а не «случайно публичные»; тот же вопрос закрывается
предикатом «не в `public-api`, не в `Internal`, нет `@internal`».
`RAG:—` `tests/Arch/LayerTest.php` уже отверг `pest-plugin-arch` за вакуумное
прохождение и записал, что Deptrac «adds a dependency the require-graph +
source scan already make unnecessary». `RAG:—` D12 оставил Q5 на критерий
«правило, которое существующий гейт выразить не может». `RAG:—` D4 разрешает
ровно одну новую dev-зависимость — BC-чекер.

## Решение

Deptrac, PHPat, PHPArkitect и `tomasvotruba/class-leak` в `require-dev` не
вводятся. Q5 закрывается отрицательно: критерий не выполнен.

Слепое пятно существующего гейта — не отсутствие DSL, а узкий разбор `use` по
строке (`parseUseImport` возвращает `null` на `{`, не видит FQCN без `use`).
P3.2 заменяет этот разбор тем же stdlib-`token_get_all`, что и плановый сканер
(D16), и добавляет рёбра `extends`/`implements`/`use Trait` и FQCN. P3.3 строит
R4/R4b/R5 на уже полном графе.

«Случайно публичный» тип — R4b того же гейта (предикат P3-Q5), со снимком
текущего дерева как allowlist. Нативная проверка `@internal` PHPStan в
межпакетный enforcement не включается (D12).

## Почему

Альтернатива «поставить Deptrac, потому что гейт не видит group-use» чинит не
тот слой: group-use и FQCN закрываются парсером, который план уже написал для
P2. Третья копия графа в YAML расходилась бы с `package-graph-gate.json`.
Альтернатива «class-leak как обнаружение случайно публичных» отклонена
источником: инструмент отвечает на другой вопрос и был бы второй
dev-зависимостью сверх D4.

## Consequences

P3.2 правит `scripts/package-graph-gate.php` и обязан иметь негативную проверку
на group-use и на FQCN без `use`. P3.3 не имеет права вводить второй анализатор
границ. Корневой `composer.json` не получает Deptrac/PHPat/class-leak.
`docs/rejected-optimizations.md` в исполнении P1.7 уже отвергает
`pest-plugin-arch`; строка про Deptrac/PHPat добавляется исполнением P3.3 в
корневой `CHANGELOG.md` как «гейт графа расширен без нового инструмента».
