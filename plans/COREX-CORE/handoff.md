# HANDOFF — 2026-07-13 — after P1.1

**Next:**

| Параметр | Значение |
|:--|:--|
| Model | Sonnet |
| Thinking | medium |
| Context | continue (/clear) — ручной item |
| Суть | Исполнить P1.12 (контракты TenantContext/AccountRef/WorkspaceRef + TenantContextResolver + SingleAccountResolver-заглушка) — предпосылка DomainEvent.tenant (P1.9) и Settings/Audit scope; исполняется сразу после P1.1, до P1.7 (порядок фазы — Phase Context P1.md). |

````
/task:plan-exec COREX-CORE P1.12
````

Cold-start reads: `plans/COREX-CORE/plan.md` → `plans/COREX-CORE/handoff.md` (этот файл) →
`plans/COREX-CORE/phases/P1.md` (Phase Context + item P1.12) → Required Reads P1.12 (B-10 §3.1,
B-11 §3.1, B-10 §6.1, plan.md D10/D11, `packages/core/src/Tenancy/` текущий код).

**Done:** План создан 2026-07-06; обе фазы детализированы до 16-польных ТЗ 2026-07-08 (26/26);
2026-07-12 двойной design-пасс (DoR-финализация + max-design, детали в Update Log plan.md).
**2026-07-13 — P1.1 закрыт:** UUIDv7 — дефолт ID-strategy (ревизия ADR-003). `IdStrategy::Uuid7`
(enum-ветка); `HasConfiguredId::newUniqueId()` — `Str::uuid7()` для Uuid7; `IdColumns::primary/
reference/foreign` — нативный `uuid`-тип PG для Uuid7 (как для Uuid v4); `config('corex.ids.
strategy')` дефолт → `IdStrategy::Uuid7`. Тесты: `ConfigurableIdTest.php` +4 (uuid7-датасет,
генерация валидного id, дефолт-стратегия, монотонность/time-sortability, тип колонки);
`UlidGenerationTest.php` — явный `beforeEach(config ulid)` (раньше полагался на дефолт=ulid).
Гейт зелёный: `composer test` 41/41, `composer lint:check` passed, `composer analyse` 0 ошибок.
Known Deviation: тронут `tests/Crm/DealFlowTest.php` (вне Files item'а) — `Str::isUlid`→
`Str::isUuid` на assertion `deal->id` (crm-тест ловил старый дефолт, ID crm НЕ менялся —
Scope Excluded соблюдён). Коммит `c6b4ce0`.

**Remaining:** Исполнять phase-first, **гейт «Запускай P6» снят владельцем 2026-07-13**. Порядок P1
(sequential-coupled), P1.1 ✅: **P1.12** (контракты TenantContext — следующий) → P1.2 → P1.3 → P1.4 →
P1.5 → **P1.6** (вводит PG-лейн) → P1.7 → P1.8 → P1.9 → P1.10 → P1.11 → P1.13. Затем P2 —
**предусловие P2.1 = решение OQ-5 (php ^8.4 vs ^8.3)**; P2.1 GO → P2.2 → P2.3 → P2.4 → P2.5 →
P2.7 (users, до P2.6/P2.8) → P2.6 → P2.8 → P2.9 → re-GO checkpoint → P2.10 (DoR: ADR-017 сверен,
OQ-7) → P2.11 → P2.12 → P2.13.

**Per-item routing P1** (несущие opus/high — pre-mortem; механика sonnet/medium):

| Item | Model/effort | Почему |
|:--|:--|:--|
| P1.4 | opus/high | RegistryCompiler + 8 extenders + фейл-деплоя (несущий инвариант, pre-mortem A) |
| P1.6 | opus/high | Lifecycle-FSM + purge (data-loss) + вводит PG16-лейн (pre-mortem B/C) |
| P1.13 | opus/high | кастомное PHPStan-правило (AST-уровень) |
| P2.1 | opus/high | PoC-гейт stancl: GO/NO-GO + F2-спайка + НФТ (Routing §3) |
| P2.13 | opus/high | PHPStan stancl-изоляция + arch-тесты (Routing §3) |
| P1.2 P1.3 P1.5 P1.7 P1.8 P1.9 P1.10 P1.11 | sonnet/medium | механика по готовому ТЗ (DDL/контракты/DTO) |
| P1.12 | sonnet/medium | чистые DTO-контракты (sonnet/low допустимо) |

**Sources of truth:** plans/COREX-CORE/plan.md (v0.2.0, D1–D26) · phases/P1.md · phases/P2.md ·
open-questions.md (OQ-1..9) · findings/P1-P2-rag-verification-2026-07-12.md (внешние факты,
дата-штамп) · findings/P2-premortem.md · findings/P1-P2-audit-2026-07-12.md · findings/P1-premortem.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT — сверяться; FROZEN = предусловие
финального закрытия migration-items) · ~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md ·
B-11_auth-tenancy.md · ~/Vaults/Brain/plans/flex/20_research/R-11_stancl-readiness.md · R-15_cell-infra.md ·
~/Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md

**Open risks:** **OQ-5 php ^8.4** (stancl master vs corex ^8.3 — блокер P2.1, решение владельца);
DB_SCHEMA DRAFT (OQ-1/OQ-6 — владелец схемы); stancl v4 без релиза (D8/D25 supply-chain-якорь;
PoC-гейт держит); churn контрактов до 1.0 (D17 — не тегать 1.0 рано); jobpipeline rc (точный пин);
broadcasting PR #1448 открыт (OQ-8, вне scope). Полная карта — findings/P2-premortem.md.

**Workarounds/Deferred/Open questions:**
- workarounds: SettingChanged (P1.7) и lifecycle-события (P1.6) до DomainEvent — interim-база,
  ретрофит P1.9 (OQ-4); `Manifest::featureFlags()` — interim `list<mixed>` до P1.9 (OQ-9/D20).
- deferred: corex/components (D6); §4.5 DX-генераторы + §4.6 MCP dev-сервер + AC-33/34 + AC-12 N−1
  (D26); контекстные PHPStan-правила 6/8/9 (P1.13 → Pending Work при неразрешимости); партиционирование
  sys_audit_log (§9.7); pest --parallel per-worker DB isolation (findings §6 — при переходе CI).
- open_questions: **open-questions.md OQ-1..9** — блокеры: OQ-5 (php-констрейнт, предусловие P2.1),
  OQ-7 (actor-type support + ADR-017, DoR P2.10/P2.11), OQ-1 (updated_at, финальное закрытие P1.8);
  OQ-6 (DB_SCHEMA счёт/sys_pubsub_messages — владелец схемы); OQ-2 (DomainEvent↔CoreXEvent, решается
  на P1.9); OQ-8 (наблюдение PR #1448).
