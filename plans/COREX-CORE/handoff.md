# HANDOFF — 2026-07-13 — after P1.2

**Next:**

| Параметр | Значение |
|:--|:--|
| Model | Sonnet |
| Thinking | medium |
| Context | continue (/clear) — ручной item |
| Суть | Исполнить P1.3 (Module manifest + discovery — `Flex\Modules\CorexModule`/`Manifest`/`Dep`/`SettingDefault` + `flex:modules:compile` discovery-часть) — следующий item фазы после P1.1/P1.12/P1.2. |

````
/task:plan-exec COREX-CORE P1.3
````

Cold-start reads: `plans/COREX-CORE/plan.md` → `plans/COREX-CORE/handoff.md` (этот файл) →
`plans/COREX-CORE/phases/P1.md` (Phase Context + item P1.3) → Required Reads P1.3 (B-10 §3.2
строки 364–431, §5.2 889–906, §4.1 анти-паттерны 700–718, plan.md D9/D11,
`packages/modules/src/Enums/Capability.php` из P1.2, `packages/core/src/Modules/ServiceProviderModuleRegistrar.php`).

**Done:** План создан 2026-07-06; обе фазы детализированы до 16-польных ТЗ 2026-07-08 (26/26);
2026-07-12 двойной design-пасс (DoR-финализация + max-design, детали в Update Log plan.md).
**2026-07-13 — P1.1 закрыт:** UUIDv7 — дефолт ID-strategy (ревизия ADR-003) — детали
phases/P1.md P1.1 Completion Notes, коммит `c6b4ce0`.
**2026-07-13 — P1.12 закрыт:** контракты `CoreX\Tenancy\AccountRef`/`WorkspaceRef`/`TenantContext`
+ `TenantContextResolver` + `SingleAccountResolver`-заглушка (boxed) — детали phases/P1.md P1.12
Completion Notes, коммит `1890e56`.
**2026-07-13 — P1.2 закрыт:** EntityRegistry v2 (`get`/`has`/`all(?ctx)`/`byCapability`/`forModel`,
`EntityDefinition`/`FieldDefinition`/`Capability`/`EntityNotFound`) в новом пакете `flex/modules`
(`Flex\Modules\`); `ArrayEntityRegistry` с инъектируемым module-active предикатом (шов P1.5);
`ModulesServiceProvider` биндит конкретный синглтон + алиасит контракт (D22), строит
`Relation::morphMap()` в `booted()`. Старый `CoreX\Registry\*`/`CoreX\Contracts\EntityRegistry`
удалён из `corex/core`; `CoreServiceProvider`/`Config` очищены. `CrmServiceProvider`/
`TasksServiceProvider` переведены на точечные handles (`crm.deal`, `tsk.task`, …). Известное
отклонение: правка задела `packages/auth` (не в Files item'а) — единственный внешний потребитель
удалённого контракта (`TenantContextGuard`), переведён + верифицирован отдельным прогоном (вне
корневого гейта). Детали phases/P1.md P1.2 Completion Notes/Known Deviations, коммит `c5fd939`.

**Remaining:** Исполнять phase-first, гейт «Запускай P6» снят владельцем 2026-07-13. Порядок P1
(sequential-coupled), P1.1 ✅ / P1.12 ✅ / P1.2 ✅: **P1.3** (Module manifest + discovery —
следующий) → P1.4 → P1.5 → **P1.6** (вводит PG-лейн) → P1.7 → P1.8 → P1.9 → P1.10 → P1.11 → P1.13.
Затем P2 — **предусловие P2.1 = решение OQ-5 (php ^8.4 vs ^8.3)**; P2.1 GO → P2.2 → P2.3 → P2.4 →
P2.5 → P2.7 (users, до P2.6/P2.8) → P2.6 → P2.8 → P2.9 → re-GO checkpoint → P2.10 (DoR: ADR-017
сверен, OQ-7) → P2.11 → P2.12 → P2.13.

**Per-item routing P1** (несущие opus/high — pre-mortem; механика sonnet/medium):

| Item | Model/effort | Почему |
|:--|:--|:--|
| P1.4 | opus/high | RegistryCompiler + 8 extenders + фейл-деплоя (несущий инвариант, pre-mortem A) |
| P1.6 | opus/high | Lifecycle-FSM + purge (data-loss) + вводит PG16-лейн (pre-mortem B/C) |
| P1.13 | opus/high | кастомное PHPStan-правило (AST-уровень) |
| P2.1 | opus/high | PoC-гейт stancl: GO/NO-GO + F2-спайка + НФТ (Routing §3) |
| P2.13 | opus/high | PHPStan stancl-изоляция + arch-тесты (Routing §3) |
| P1.3 P1.5 P1.7 P1.8 P1.9 P1.10 P1.11 | sonnet/medium | механика по готовому ТЗ (DDL/контракты/DTO) |

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
  ретрофит P1.9 (OQ-4); `Manifest::featureFlags()` — interim `list<mixed>` до P1.9 (OQ-9/D20);
  `ArrayEntityRegistry::moduleActive` — предикат-заглушка `fn()=>true` до реального ModuleRegistry
  (P1.5).
- deferred: corex/components (D6); §4.5 DX-генераторы + §4.6 MCP dev-сервер + AC-33/34 + AC-12 N−1
  (D26); контекстные PHPStan-правила 6/8/9 (P1.13 → Pending Work при неразрешимости); партиционирование
  sys_audit_log (§9.7); pest --parallel per-worker DB isolation (findings §6 — при переходе CI).
- open_questions: **open-questions.md OQ-1..9** — блокеры: OQ-5 (php-констрейнт, предусловие P2.1),
  OQ-7 (actor-type support + ADR-017, DoR P2.10/P2.11), OQ-1 (updated_at, финальное закрытие P1.8);
  OQ-6 (DB_SCHEMA счёт/sys_pubsub_messages — владелец схемы); OQ-2 (DomainEvent↔CoreXEvent, решается
  на P1.9); OQ-8 (наблюдение PR #1448).
