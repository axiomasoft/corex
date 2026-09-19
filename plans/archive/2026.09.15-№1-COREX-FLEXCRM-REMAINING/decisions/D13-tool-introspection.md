---
id: D13
date: 2026-09-15
status: accepted
item: P8
items: [P8, P8.1, P8.2, P8.3, P8.4, P8.5, P8.6, P8.7]
supersedes: []
superseded_by: null
---
# D13 — Явные tool contributions, проверка текущих полномочий и интроспекция

**Date:** 2026-09-15
**Status:** accepted
**Actor:** plan-designer/gpt-5.6-sol/high; provider-attested explicit P8 design
**Evidence:** RAG:— artifacts/P8-design/route-evidence.json; artifacts/P8-design/source-observations.json; research/P8-tool-design.md; B10/B32/B33/B34 snapshots. RAG:✅ research/P8-design-rag.md; successful neutral Perplexity retrieval and independent primary verification.
**Supersedes:** —
**Superseded By:** —

## Решение

Детализировать P8 в семь пунктов с 56 наблюдаемыми проверками для R08/R36/R50. Сохранить владение 49 исходными критериями B32/B10 и 19 тулзами control plane из B33/B34. Последние остаются внешней картой ответственности и не регистрируются как tenant endpoints.

Декларации проходят через существующий Api extender и compiled API slice. Опциональная поверхность corex/api проецирует типизированный каталог и вызывает явные OperationHandler → owning Action adapters. corex/modules предоставляет только чтение реестра и CLI why. Существующие интерфейсы, восемь extenders, HTTP envelope и legitimate domain Actions сохраняются. Новый реестр, Manifest::agentic, девятый extender, AI/MCP сервер, движки прав, событий или схем не создаются.

Дизайн фиксирует ограниченный offline JSON Schema2020-12 профиль и квалификацию существующей Opis2.x библиотеки. Её точные immutable source/ref/license, совместимость, evaluator budgets и conformance suite должны быть приняты в P8.1/P2/G-DEPENDENCY до реализации. Наличие документации или косвенной ссылки в lockfile не доказывает установленный валидатор.

Каталог проверяет текущие activation/discover/invoke политики; его содержимое не даёт полномочий. ToolExecutionGuard по умолчанию допускает только авторизованные reads. Consumer adapter владеет подтверждением, атомарным claim, operation key, current resource checks, локальной транзакцией и видимостью повторного результата. Новый ToolRequestContext передаёт метаданные ключа без изменения принятых интерфейсов. Generic fixture подтверждает контракт, а реальный approval ledger остаётся у владельца приложения. Исходы committed/unknown/output_contract_failed сохраняются явно; автоматический повтор неизвестного внешнего эффекта запрещён.

Имена используют namespace без underscores и проверяемое отображение canonical/version ↔ wire. Это исправляет неоднозначность кандидата из B32 без изменения исходного канона. additionalProperties сохраняет семантику того же subschema; unevaluatedProperties требует квалифицированного evaluator. Математически целое1.0 соответствует integer; format/default/annotations не становятся серверной политикой. Внешний MCP integration profile фиксирован на2025-11-25; наблюдаемая2026-07-28 ревизия не принимается автоматически.

## Почему

Новый agentic stack дублировал бы существующие registry/domain/policy owners. Автоматическая публикация операций создала бы неограниченный CRUD. Cached allow, клиентское подтверждение и annotations не закрывают текущую авторизацию; раздельные admission/effect дают TOCTOU. Потеря JSON типов при хешировании, неподдержанные keywords/refs и неоднозначный wire parser нарушают контракт. Ошибка результата после внешнего эффекта не доказывает откат.

Альтернативы, независимые первоисточники и межфазовые риски сохранены в candidate/RAG/integrated design. Неполный первый ответ Perplexity был сразу сообщён владельцу; дополнительные guard/schema запросы закрыли gap. Неверное утверждение о additionalProperties исправлено по первоисточнику.

**Consequences:** P8 получает семь specs, Routing, Phase Assurance, точные Files/Validation и будущий installed seal для трёх R. Стабильный candidate добавлен в полный design-finish hash set. P1–P8 детализированы; P9–P11 и общая проверка дизайна остаются pending. Все execution items Not started. Решение не выдаёт API/DDL/dependency/private/foreign/Brain/release/live/audit grants, не запускает преемника и не делает commit/push. Следующая рекомендация — отдельный дизайн P9 на Sol/high после DESIGN-ROOT-TERMINAL.
