# Навигация по PLAN1

## План Astra и детализация P1–P7 Sol

- [План](plan.md) — authority, границы, Routing.
- [Порядок фаз](roadmap.md) — DAG и условия app-only финала.
- [Статус](status.md) — generated, все фазы Not started.
- [Передача Sol](handoff.md) — continuation дизайна; execution P1.1 отозвана D4.

- [P1 design validation](artifacts/P1-design/validation.md) — source preservation, contracts и structural gates.

- [Единая картина всех 11 фаз](research/05-integrated-design.md) — contracts, общий порядок и finish checks.
- [D4 — обязательный RAG и полный дизайн](decisions/D4.md) — owner policy.

## Evidence и решения

- [Исходные указания](brief/00-owner-request.md) — Astra → Sol → выбранные исполнители.
- [Источники](research/00-sources.md) — source identity для всех фаз.
- [Матрица 52 требований](research/01-traceability.md) — владельцы/границы/receipts, P1 freeze.
- [365 исходных критериев](research/01a-acceptance-index.md) — AC trace inputs для Sol.
- [Keep catalogue](research/02-baseline.md) — accepted foundations A01–A09, P1/P2.
- [Gate ledger](research/03-gates.md) — exact obligations P2/P3/P9/P10/P11.
- [Архитектура](research/04-architecture.md) — инварианты и primary sources для всех фаз.
- [D3 — P1 detail](decisions/D3.md), [D1](decisions/D1.md), [D2](decisions/D2.md) — authoring choice и scope/closure policy.
- [Вопросы](open-questions.md) — owners и conditions, без нового опроса владельца.

## Фазы (P1–P7 detailed; P8–P11 для детализации Sol)

- [P1: Freeze требований, provenance и владельцев](phases/P1/P1.md)
- [P2: Минимальные reusable contracts и границы адаптеров](phases/P2/P2.md)
- [P3: Довести generic tenancy, storage и identity adapters](phases/P3/P3.md)
- [P4: Headless extensions: panels, media, notifications, saved views](phases/P4/P4.md)
- [P5: Workflow binding и единый durable wakeup seam](phases/P5/P5.md)
- [P6: Data extension, import/export и search](phases/P6/P6.md)
- [P7: Task primitives, recurrence и internationalization](phases/P7/P7.md)
- [P8: Introspection и transport-neutral tool integration](phases/P8/P8.md)
- [P9: Integrated cohort, compatibility, pristine install и release](phases/P9/P9.md)
- [P10: Реальная external/central/cloud acceptance](phases/P10/P10.md)
- [P11: Reconciliation: закрыть CoreX-зависимости и передать работу FlexCRM](phases/P11/P11.md)

- [P3 adapter design](research/P3-adapter-design.md) — точные candidate signatures/DDL/42 cases.
- [P3 RAG](research/P3-design-rag.md) — neutral queries, response hashes, primary verification.
- [P3 design validation](artifacts/P3-design/validation.md) — проверки и границы evidence.
- [D8 — P3 adapters](decisions/D8.md) — protected physical adoption и local/effective разделение.

- [P4 extension design](research/P4-extension-design.md) — exact ports/SQL/defaults/51 local case contracts.
- [P4 RAG](research/P4-design-rag.md) — neutral supplementary queries and independent primary verification.
- [P4 design validation](artifacts/P4-design/validation.md) — structural checks and evidence limits.
- [D9 — P4 headless boundaries](decisions/D9-headless-extensions.md) — frozen candidate, no protected adoption grants.

- [P6 frozen data design](research/P6-data-design.md) — eight specs/57 future cases, exact ports/schema/fault/AC owners.
- [P6 RAG](research/P6-design-rag.md) — actual neutral responses/hashes/direct independent origins.
- [P6 design validation](artifacts/P6-design/validation.md) — structural checks and evidence limits.
- [D11](decisions/D11-data-import-search.md) — thin engine binding, resumable transfer and authoritative search.

- [P7 utility design](research/P7-utility-design.md) — nine specs/70 future cases/45mixed parents, exact ports/DTOs/atomicity.
- [P7 RAG](research/P7-design-rag.md) — successful neutral raw responses/digests/direct primary origins.
- [P7 validation](artifacts/P7-design/validation.md) — structural/input/negative checks, no executed product proof.
- [PLAN1.D12](decisions/D12-portable-utilities.md) — pure recurrence/request locale/exact FX/offline reference and optional glue.
