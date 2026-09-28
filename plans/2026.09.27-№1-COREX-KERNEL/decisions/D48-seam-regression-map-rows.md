---
id: D48
date: 2026-09-28
status: accepted
item: P02.1
items: [P02.1, P02.2]
supersedes: []
superseded_by: null
---
# D48 — Карта дополнительных регрессионных тестов швов

**Actor:** executor/codex/gpt-6-sol/high, routine author-choice по plan.md §2.
**Evidence:** RAG:— `phases/P02/P02.1.md`, `artifacts/P02-design/examples.md` §1.2;
фактическое разделение RegistryCompilerDeterminismTest и RegistryIntrospectionTest.

## Решение

В карту P00 добавить точные строки `packages/operations/tests/Unit/OperationRegistryDeterminismTest.php`
→ operations и `packages/operations/tests/Unit/ToolRegistryIntrospectionTest.php` → tools.
Три целиком переносимых API/tools-теста получают текущие пути operations и namespace
его тестового каталога; конечные владельцы сохраняются. Общие introspection-тесты
ядра используют entity-декларацию, tools-проекция отдельно проверяется владельцем tools.
Новый `packages/crm/tests/Feature/RegistryFieldCastsTest.php` покрывается существующей
строкой тестов CRM. Фактические колонки zone-consumers регенерируются после швов,
вердикты сохраняются; появившаяся зона Contracts? с новым SPI имеет verdict keep.

## Почему

Выделение секций не должно оставлять тесты ядра зависимыми от tools. Эти строки
фиксируют владельцев дополнительных регрессий для последующего механического
переноса; тесты не удаляются и не пропускаются.

## Consequences

Массовый перенос остаётся P02.3. Спецификация, поведение и fingerprint дизайна
не меняются; обновляются карты фактического дерева и тестовые доказательства.

- Shared `StubOperationHandler` copy has an exact map row into operations Testing for the later scaffold. Tool declaration/introspection tests use this handler, which is autoloadable in the standalone operations suite; the original monorepo handler remains unchanged.

- CRM money regression fixture is autoloaded from its own `tests/Fixtures/` directory; add that exact product-preserving map prefix.

- Seven namespaced `ConfigReportSectionTest.php` files have precise rows so the existing namespace-free Pest directories retain their rules. The mapped test owners are their section packages; modules maps into the merged kernel test support.
