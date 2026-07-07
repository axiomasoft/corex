# COREX-CORE — Ядро CoreX: примитивы, модульная система, tenancy & auth

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | COREX-CORE |
| Title | Ядро CoreX — B-10 (примитивы + модули) и B-11 (tenancy + auth) |
| Version | 0.1.0 |
| Status | 🟡 In progress |
| Document Type | Executable Master Plan |
| Authoring Model | opus |
| Last Updated | 2026-07-06 |
| Repository | corex (packages/corex) |
| Related Packages | corex/core · flex/modules (new) · corex/tenancy (new) · corex/auth |
| Execution Mode | phase-first |
| Target Operator Models | sonnet (exec) · haiku (LOW) · opus (design/audit) |
| Approval Owner | Dmitry Vostrikov |

## 1. Context

Реализация ядра экосистемы Flex по завершённому дизайну (vault `plans/flex/`, Этапы 0–8 ☑).
Этот план — исполняемая часть **roadmap-фаз P0 и P1** из `Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md`.
Правило разделения (roadmap §3.1): **только код, приземляющийся в `packages/corex/**`** — приложение
FlexCRM и OSS-выносы (workflow/attributes/import-export) идут отдельными планами позже.

- **Phase P1** = roadmap-фаза **P0** / блупринт **B-10** — примитивы ядра (BaseModel/ID/Settings/Audit/
  Money/departments/feature-flags/pubsub/events) + полноценная **модульная система** (manifest,
  discovery, RegistryCompiler + 8 extenders, ModuleRegistry hot-path, Lifecycle-FSM).
- **Phase P2** = roadmap-фаза **P1** / блупринт **B-11** — двухуровневая tenancy (физическая
  DB-per-account на stancl/tenancy v4 + логический workspace) и auth (AzGuard-надстройка,
  entity-scoped RBAC, OIDC/boxed-Fortify).

Часть примитивов уже реализована в `corex/core` (BaseModel, MoneyCast, ID-seam, простые
EntityRegistry/Settings/Audit, собственный tenancy-каркас) — **не переписываем, считаем дельту к
спеке** (см. Decision Log). Собственный tenancy-каркас (`TenantService`/`TenantScope`, row-level)
**замещается** физической моделью B-11 (DB-per-account) — ADR-004 переписан (D1).

Source истины спеки — vault (не этот репо): блупринты `plans/flex/30_blueprints/B-10_*`, `B-11_*`,
ресёрч `R-11_stancl-readiness.md`, решения `00_MASTER_PLAN.md §3`. Каждый item указывает точные
Required Reads. **Vault не перемещать/переименовывать** — Required Reads ссылаются на него по пути.

**DB-контракт (обязательный Required Read всех migration-items):**
`~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md` — канонический каталог таблиц/полей/связей по
префиксам. Миграции `root_/sys_/mod_/wsp_/aut_` (P1.6/P1.7/P1.8/P1.10, P2.2/P2.6/P2.8…) сверяются с
ним поколоночно. При `Status: DRAFT` — контракт не финален (см. его §Конфликты C1–C6); заморозка
(`FROZEN`) — предусловие финального закрытия migration-items.

**Связь с потребителем (зачем этот пакет):** traceability «capability → продуктовая фича» —
roadmap §3.3 (`Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md`); поле **Why** каждого
item называет потребителя. **План живой (loop-back):** когда разработка FlexCRM (roadmap-фаза P3)
упрётся в нехватку ядра — сюда добавляется НОВЫЙ item (core-first, тег, app потребляет через симлинк),
план не заморожен после закрытия P1/P2. Пакет и приложение со-эволюционируют (roadmap §3.2).

## 2. Execution Rules

- Один item за раз; статус обновляется В ПЛАНЕ до начала работы (🟡) и по завершении (🟢/🟠).
- Статусы — 6 канонических строк байт-в-байт (plan-protocol §5).
- Фазы закрываются последовательно; items не прыгают между фазами.
- **Гейт пакета (roadmap §5):** `composer lint && composer analyse && composer test` зелёные —
  условие закрытия любого code-item. Внешние факты (версии/API stancl, laravel-workflow) —
  верифицировать через RAG на исполнении, не по памяти.
- **P2.1 (stancl PoC-гейт) — блокирующий:** ни один tenancy-item P2.2+ не стартует до GO по P2.1.
- План разошёлся с кодом/спекой → не импровизировать, эскалировать (Escalation Needed + handoff).
- После закрытия item/фазы — перезаписать `plans/COREX-CORE/handoff.md`.
- На закрытии фазы — semver-тег затронутых пакетов + запись в CHANGELOG (release-discipline).

## 3. Routing

Отклонения от SSOT-матрицы plan-protocol §7:
- **P2.1** (PoC-гейт stancl) — opus/high: инфра-дизайн + GO/NO-GO-решение, не механический exec.
- **P1.13 / P2.13** (кастомные PHPStan-правила) — opus/high: статик-анализ уровня AST, не тривиальный код.
- Остальное — дефолты (sonnet/exec; DDL-миграции — sonnet/medium).

## 4. Phase Index & Status Board

| Phase | Title | Items 🟢/всего | Status |
|:--|:--|:--|:--|
| P1 | Ядро CoreX — примитивы + модульная система (roadmap P0 / B-10) | 0/13 | ⬜ Not started |
| P2 | Tenancy & Auth (roadmap P1 / B-11) | 0/13 | ⬜ Not started |

## 5. Decision Log

| D# | Дата | Решение | Почему |
|:--|:--|:--|:--|
| D1 | 2026-07-06 | План покрывает только код в `packages/corex/**` (roadmap §3.1). FlexCRM-приложение и OSS-выносы — отдельные планы. | Правило разделения «item = один слой/репа»; разный тулинг пакет vs приложение. |
| D2 | 2026-07-06 | Модульная система (Module Loader + EntityRegistry v2) живёт в НОВОМ пакете `flex/modules` (namespace `Flex\Modules\`) внутри монорепо corex. Существующие `corex/core` EntityRegistry/ModuleRegistrar **мигрируют** туда, не переписываются с нуля. | B-10 задаёт namespace `Flex\Modules\`; D17 классифицирует модуль-систему как OSS-вынос (`flex/*`). |
| D3 | 2026-07-06 | ID-strategy: **UUIDv7 — дефолт** (ревизия ADR-003). Швы ulid/int сохраняются для boxed/legacy. | ADR-003 (UUIDv7, time-sortable, курсорная пагинация); seam уже есть в коде (PR #10). |
| D4 | 2026-07-06 | `users` — **полная денормализация** identity (email/name/phone в БД тенанта) per B-11 canon. Тонкая проекция отклонена. | B-11 §5; тонкая проекция ломает офлайн-режим и миграторы Б24. [B-11 §9 п.1 помечал неутверждённым — принимаю canon.] |
| D5 | 2026-07-06 | `ScopeResolver` (entity-scoped RBAC applier) реализуется в **corex/auth** (consumer-side), НЕ апстримом в AzGuard. | Держит план в границах corex-репо (§3.1). Апстрим `ScopeResolver`/`is_system`/principal `agent` в AzGuard — отдельный кандидат позже. |
| D6 | 2026-07-06 | `corex/components` (B-10 §3.5) **отложен** из P1. | Потребитель — page-builder FlexCMS (roadmap-фаза P5); не строим ядро впрок (D6-принцип roadmap «фича по факту потребителя»). |
| D7 | 2026-07-06 | OIDC/IdP `id.flex.ru` — внешнее проприетарное приложение (ADR-017). В границах corex v1: boxed-Fortify + OIDC-client-шов; сам IdP-сервер вне плана. | IdP не является кодом `packages/corex/**` (§3.1); проприетарен (faithful-first не применим). |
| D8 | 2026-07-06 | stancl/tenancy пин `dev-master#<sha> as 4.0.0` + jobpipeline `2.0.0-rc7`. PoC-гейт (7 сценариев) — ПЕРВЫЙ и блокирующий item P2. | R-11: v4 не зарелижен (0 тегов); B-11 Приложение A требует PoC до кода. |

## 6. Update Log

| Дата | Кто (role/model) | Что |
|:--|:--|:--|
| 2026-07-06 | plan-design/opus | План создан из EXECUTION_ROADMAP P0+P1; дельта посчитана против текущего кода corex (fan-out 3 агента: B-10, B-11, инвентарь). Скелеты P1/P2 (по 13 items), P1.1 и P2.1 детализированы как образцы. |
