# COREX-CORE — Ядро CoreX: примитивы, модульная система, tenancy & auth

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | COREX-CORE |
| Title | Ядро CoreX — B-10 (примитивы + модули) и B-11 (tenancy + auth) |
| Version | 0.2.0 |
| Status | 🟡 In progress |
| Document Type | Executable Master Plan |
| Authoring Model | opus |
| Last Updated | 2026-07-12 |
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
- **Гейт пакета (D18, уточнение roadmap §5):** `composer lint:check && composer analyse && composer test`
  зелёные — условие закрытия любого code-item (`composer lint` = Pint-FIX, мутирует и всегда exit 0 —
  НЕ гейт; расхождение с roadmap §5 зафиксировано в D18). Внешние факты (версии/API stancl,
  laravel-workflow) — верифицированы 2026-07-12 (`findings/P1-P2-rag-verification-2026-07-12.md`);
  на исполнении освежать только версии/sha (verify-claims), не переделывать базовую верификацию.
- **Тест-файлы items — в корневом `tests/`** (D19): packages/*/tests НЕ подключены к phpunit.xml;
  новый домен тестов = новый testsuite в phpunit.xml (вводит первый item пакета).
- **P2.1 (stancl PoC-гейт) — блокирующий:** ни один tenancy-item P2.2+ не стартует до GO по P2.1.
- План разошёлся с кодом/спекой → не импровизировать, эскалировать (Escalation Needed + handoff).
- После закрытия item/фазы — перезаписать `plans/COREX-CORE/handoff.md`.
- На закрытии фазы — semver-тег затронутых пакетов + запись в CHANGELOG (release-discipline, **политика D17**: pre-1.0, breaking-в-minor легально; рекоменд. P1-теги `corex/core v0.1.0`, `flex/modules v0.1.0`).
- **Спорное/незакрытое — в `open-questions.md`** (не висит в теле item'а): OQ-1..OQ-9
  (блокеры гейтов: OQ-5 — предусловие P2.1; OQ-7 — DoR P2.10/P2.11; OQ-1 — финальное закрытие P1.8).

## 3. Routing

Отклонения от SSOT-матрицы plan-protocol §7 (per-item routing P1 по риску):
- **P1.4** (RegistryCompiler + 8 extenders + валидатор компиляции) — opus/high: детерминизм пайплайна + закрытый набор + фейл-деплоя, несущий инвариант модуль-системы (pre-mortem A).
- **P1.6** (ModuleLifecycle-FSM + purge + PG тест-лейн) — opus/high: data-loss-риск на purge + вводит PG16-инфраструктуру (pre-mortem B/C).
- **P1.13 / P2.13** (кастомные PHPStan-правила) — opus/high: статик-анализ уровня AST, не тривиальный код.
- **P2.1** (PoC-гейт stancl) — opus/high: инфра-дизайн + GO/NO-GO-решение, не механический exec.
- **Механика (sonnet/medium):** P1.1, P1.2, P1.3, P1.5, P1.7, P1.8, P1.9, P1.10, P1.11, P1.12 (DDL-миграции — sonnet/medium; чистые DTO P1.12 — sonnet/low допустимо).

## 4. Phase Index & Status Board

| Phase | Title | Items 🟢/всего | Status |
|:--|:--|:--|:--|
| P1 | Ядро CoreX — примитивы + модульная система (roadmap P0 / B-10) | 12/13 | 🟠 Done with deviations |
| P2 | Tenancy & Auth (roadmap P1 / B-11) | 0/13 | ⬜ Not started |

## 5. Decision Log

| D# | Дата | Решение | Почему |
|:--|:--|:--|:--|
| D1 | 2026-07-06 | План покрывает только код в `packages/corex/**` (roadmap §3.1). FlexCRM-приложение и OSS-выносы — отдельные планы. | Правило разделения «item = один слой/репа»; разный тулинг пакет vs приложение. RAG:✅ vault roadmap §3.1 2026-07-06 |
| D2 | 2026-07-06 | Модульная система (Module Loader + EntityRegistry v2) живёт в НОВОМ пакете `flex/modules` (namespace `Flex\Modules\`) внутри монорепо corex. Существующие `corex/core` EntityRegistry/ModuleRegistrar **мигрируют** туда, не переписываются с нуля. | B-10 задаёт namespace `Flex\Modules\`; D17 классифицирует модуль-систему как OSS-вынос (`flex/*`). RAG:✅ vault B-10 §namespace/§9.2 2026-07-06 |
| D3 | 2026-07-06 | ID-strategy: **UUIDv7 — дефолт** (ревизия ADR-003). Швы ulid/int сохраняются для boxed/legacy. | ADR-003 (UUIDv7, time-sortable, курсорная пагинация); seam уже есть в коде (PR #10). RAG:✅ vault ADR-003 + code PR#10 2026-07-06 |
| D4 | 2026-07-06 | `users` — **полная денормализация** identity (email/name/phone в БД тенанта) per B-11 canon. Тонкая проекция отклонена. | B-11 §5; тонкая проекция ломает офлайн-режим и миграторы Б24. [B-11 §9 п.1 помечал неутверждённым — принимаю canon.] [UNVERIFIED] денорм users — рабочая гипотеза, не заморожена (B-11 §5 canon vs §9 п.1) |
| D5 | 2026-07-06 | `ScopeResolver` (entity-scoped RBAC applier) реализуется в **corex/auth** (consumer-side), НЕ апстримом в AzGuard. | Держит план в границах corex-репо (§3.1). Апстрим `ScopeResolver`/`is_system`/principal `agent` в AzGuard — отдельный кандидат позже. RAG:✅ vault B-11 §границы + D1 corex-репо 2026-07-06 |
| D6 | 2026-07-06 | `corex/components` (B-10 §3.5) **отложен** из P1. | Потребитель — page-builder FlexCMS (roadmap-фаза P5); не строим ядро впрок (D6-принцип roadmap «фича по факту потребителя»). RAG:✅ vault roadmap-фаза P5 + B-10 §3.5 2026-07-06 |
| D7 | 2026-07-06 | OIDC/IdP `id.flex.ru` — внешнее проприетарное приложение (ADR-017). В границах corex v1: boxed-Fortify + OIDC-client-шов; сам IdP-сервер вне плана. | IdP не является кодом `packages/corex/**` (§3.1); проприетарен (faithful-first не применим). [UNVERIFIED] ADR-017 IdP `id.flex.ru` — сверить контракт при P2.10 |
| D8 | 2026-07-06 | stancl/tenancy пин `dev-master#<sha> as 4.0.0` + jobpipeline `2.0.0-rc7`. PoC-гейт (7 сценариев) — ПЕРВЫЙ и блокирующий item P2. | R-11: v4 не зарелижен (0 тегов); B-11 Приложение A требует PoC до кода. RAG:✅ R-11 (external: stancl v4 0 тегов, не зарелижен) 2026-07-06 |
| D9 | 2026-07-08 | `tablePrefix` модуля — `[a-z]{2,3}` (≤3 симв.), уникален в экосистеме; ядро-префиксы `sys_` (corex/core) и `mod_` (flex/modules) внесены в реестр префиксов. Валидируется на компиляции (P1.3/P1.4), фейл = ошибка деплоя. | B-10 §2/§9.1, DB_SCHEMA §2. Референсится items'ами P1.3/P1.4/P1.6. RAG:✅ vault B-10 §2/§9.1 + DB_SCHEMA §2 2026-07-08 |
| D10 | 2026-07-08 | Boxed-инверсия: ни один модуль не ветвится `if(cloud)`. Облачные решения (биллинг-гейт, root_, тарифы) — только через инжектируемые контракты (`ModuleActivationGate` и т.п.) + конфиг-биндинги; `TenantContext::isBoxed()` — единственный легальный ветвитель, и только в инфра-коде ядра. | B-10 §6.1/§7.4 (инвариант D10 блупринта). Референсится P1.5/P1.12, P2.5. RAG:✅ vault B-10 §6.1/§7.4 (инвариант D10) 2026-07-08 |
| D11 | 2026-07-08 | Namespace canon: corex/core остаётся в корне `CoreX\` (существующий код); блупринтовый `Corex\Core\` — иллюстративен (vendor-имя открыто, B-10 §9.2). Маппинг блупринт→код: `Corex\Core\X → CoreX\X` (напр. `Corex\Core\Events\DomainEvent → CoreX\Events\DomainEvent`). Новый пакет модуль-системы — `flex/modules`, PSR-4 `Flex\Modules\` (D2). | Расширяем существующий код, не переименовываем (roadmap «дельта, не переписывание»); D2 фиксирует `Flex\Modules\`. Артизан-префикс `flex:` — условный (B-10 §9.2 п.2/п.10). RAG:✅ vault B-10 §9.2 + D2 2026-07-08 |
| D12 | 2026-07-08 | PG-first: миграции `sys_*`/`mod_*` таргетят PostgreSQL 16 (ltree, BRIN, jsonb, `UNIQUE NULLS NOT DISTINCT`, partial-unique, `pg_advisory_xact_lock`). Дефолтный тест-коннекшн — SQLite `:memory:` (phpunit.xml) остаётся для pure-PHP items; PG-зависимые Feature-тесты идут на отдельном `pgsql`-коннекшене (docker PG), помечаются Pest `->group('pg')`. Тест-лейн вводит P1.6, последующие PG-items переиспользуют. | Схема B-10 PG-нативна; SQLite не поддерживает ltree/BRIN/advisory-locks/`NULLS NOT DISTINCT`. Skills: devops:docker-postgres, devops:db-test-preflight. RAG:✅ vault B-10 (схема PG-нативна: ltree/BRIN/advisory-lock) 2026-07-08 |
| D13 | 2026-07-08 | **corex/tenancy** — новый пакет (`packages/tenancy`, PSR-4 `Corex\Tenancy\`, type library, require corex/core + stancl/tenancy). Импорт `Stancl\*` разрешён ТОЛЬКО здесь (инвариант B-11 §7.3 п.1, ловит P2.13). НО tenancy-КОНТРАКТЫ (`TenancyManager`, `AccountConnectionResolver`, `TenantDatabaseProvisioner`, `DatabaseConnectionConfig`, интерфейсы lifecycle/provisioning) живут в **corex/core** (`CoreX\Tenancy\Contracts\*`) — чтобы boxed (FlexCMS) не тянул corex/tenancy; их cloud-реализации — в corex/tenancy. | B-11 §1.1 L11–22: contracts in core, impl in tenancy; boxed FlexCMS не должен зависеть от stancl. Дополняет контракты P1.12 (TenantContext/AccountRef/WorkspaceRef уже в core). RAG:✅ vault B-11 §1.1 L11–22 (contracts in core, impl in tenancy) 2026-07-08 |
| D14 | 2026-07-08 | **root_-схема — split владения.** P2.2 создаёт root_ таблицы, которыми владеет ЭТОТ план (tenancy/identity/provisioning/migration-control): clusters, templates, identities, identity_accounts, accounts, account_products, domains, oauth_clients, idp_sessions, oauth_refresh_tokens, impersonation_grants, feature_flags, usage_snapshots, migration_batches, account_schema_state, account_events, account_exports (17 таблиц). **Отложены** в свои планы: `root_plans`/`root_subscriptions` (биллинг-модуль, вне corex-core), `root_wfl_wakeups` (B-12 workflow OSS-вынос). Расхождение «18 префикс-карта vs 17 блоков» DB_SCHEMA §2 (L44) — не блокер (билинг-таблицы добавит свой план). Root_ миграции идут на ЦЕНТРАЛЬНЫЙ (root) коннекшн, не в тенант. Cross-DB/полиморфные колонки — БЕЗ FK (DB_SCHEMA §1). Cross-package FK (`users.department_id`→sys_departments, `sys_departments.workspace_id`→wsp_workspaces, конфликт C4) — отдельной финальной integration-миграцией (P2.6), не inline. | DB_SCHEMA §Конфликты C4/C6; §3.1 разделение слоёв; roadmap §3.1 «только код packages/corex/**». RAG:✅ vault DB_SCHEMA §Конфликты C4/C6 + §3.1 2026-07-08 |
| D15 | 2026-07-08 | **AzGuard заморожен апстримом (усиление D5).** corex/auth добавляет `aut_*` + `RoleMatrixCompiler` (компилирует матрицу в AzGuard `az_guard_role_permissions` через модели/менеджер AzGuard — публичный API, НЕ правку пакета) + `ScopeResolver`, который НАПОЛНЯЕТ пустой shipped-шов AzGuard `ScopeInterface` (в AzGuard интерфейс есть, реализаций 0 — builder-applier net-new в corex/auth) + `AccessChecker` (фаза-1 = AzGuard `HasPermissions` ≤1мс, фаза-2 = ScopeResolver). Правок пакета `azguard` НЕТ. Существующий мост `corex/auth` (`TenantContextGuard`/`TenantBoundary`) переиспользуется, не переписывается. | Инвентарь: AzGuard `ScopeInterface` без реализаций, `az_guard_role_permissions` как sink компилятора; D5 держит границы corex-репо. RAG:✅ vault AzGuard-инвентарь (ScopeInterface без реализаций) 2026-07-08 |
| D16 | 2026-07-08 | Все P2 migration-items переиспользуют PG тест-лейн из P1.6 (docker PG16 + `pgsql` + Pest `->group('pg')`). root_ миграции — на центральном коннекшене; `wsp_*`/`aut_*`/`users` — внутри тенант-БД (golden-шаблон). stancl v4 пин `dev-master#<sha> as 4.0.0` + jobpipeline `2.0.0-rc7` (D8), гейт P2.1. Рантайм v1 — PHP-FPM (Octane не поддержан stancl v4, R-11 #1405); broadcasting — путь BroadcastChannelPrefix, не config-bootstrapper (R-11 #1448). | R-11 §2.1/§2.6; B-11 §7.1 рантайм; единый тест-лейн исключает дубли инфраструктуры. RAG:✅ R-11 §2.1/§2.6 + B-11 §7.1 2026-07-08 |
| D17 | 2026-07-12 | **Semver-политика тегов `corex/core` и `flex/modules`.** Оба пакета pre-1.0 (0.y.z, MIT): по SemVer §4 breaking-изменения легальны в MINOR до 1.0 — публичный API НЕ заморожен, потребители (app/спутники) обкатывают его через симлинк/path-repo (D1). Тег ставится ТОЛЬКО на закрытии фазы по GREEN-аудиту (release-discipline, `oss-dev/release-engineering`) + запись в per-package `CHANGELOG.md`. Рекомендованные теги на GREEN-закрытии P1: `corex/core v0.1.0` (первый релиз, вкл. Settings/Audit/Departments/PubSub/Events v2; удаление старого EntityRegistry — breaking, легально до 1.0), `flex/modules v0.1.0` (первый релиз нового пакета). Форма git-тега (per-package split vs repo-level) — по механизму релиза репо; дефолт при отсутствии сплиттера — repo-level `vX.Y.Z` + CHANGELOG на каждый пакет. НЕ тегать 1.0, пока EntityRegistry/TenantContext не обкатаны потребителями (pre-mortem кластер D). | Нет тегов в репе (git tag пусто), нет `version` в composer (git-tag-driven); pre-1.0 даёт легальный churn контрактов ядра. RAG:✅ SemVer §4 (0.y.z semantics) 2026-07-12 |
| D18 | 2026-07-12 | Канон гейта закрытия = `composer lint:check && composer analyse && composer test`. `composer lint` (Pint FIX) — не гейт: мутирует файлы, exit 0 при нарушениях. То же расхождение в roadmap §5 — помечено, канон здесь. | Аудит F14 (workflow wf_cc73752d): гейт с фиксером даёт ложно-зелёное закрытие. RAG:✅ composer.json scripts 2026-07-12 |
| D19 | 2026-07-12 | Тест-файлы плана живут в корневом `tests/` (testsuites phpunit.xml); пакетные `packages/*/tests` к раннеру НЕ подключены. Новый домен (Modules/Tenancy/Auth/PHPStan) = новый testsuite, вводит первый item пакета. | Аудит F15: phpunit.xml перечисляет только tests/{Unit,Feature,Crm,Tasks} — тесты в packages/*/tests молча не выполнялись бы («зелёное» без прогона). |
| D20 | 2026-07-12 | Владение DTO модульной системы: `SettingDefault` — flex/modules (P1.3; core потребляет через инъектируемый `SettingDefaultsProvider`-контракт, direction core←modules); `FeatureFlagDefinition` — corex/core (P1.9; P1.3 до него живёт на interim-типе `list<mixed>` — OQ-9, паттерн OQ-4). | Аудит F6/F7: двойное создание SettingDefault (P1.3+P1.7) и forward-ref P1.3→P1.9 ломали порядок исполнения. |
| D21 | 2026-07-12 | `wsp_invitations.role_id` — SOFT-REF БЕЗ FK на aut_roles (cross-package tenancy→auth, класс C4; консистентно с `azguard_role_id`/`principal_id`). Семантика ON DELETE SET NULL — прикладной очисткой при акцепте. | Аудит F16 (blocker): P2.6 создаёт таблицу до P2.8 (aut_roles) — inline/integration FK в объявленном порядке неисполним. |
| D22 | 2026-07-12 | Биндинг `EntityRegistry`→`ArrayEntityRegistry` и построение `Relation::morphMap` живут в `ModulesServiceProvider` (flex/modules), НЕ в CoreServiceProvider. | Аудит F8 (blocker): core не может биндить класс пакета, который сам require'ит core — цикл зависимостей. |
| D23 | 2026-07-12 | Генерация UUIDv7 — штатный Laravel-путь (`HasUuids`-default v7 / `Str::uuid7()`); прямой импорт `Ramsey\Uuid`/`Symfony\Component\Uid` в пакетах запрещён. | RAG:✅ 2026-07-12 findings §2: Laravel 13 HasUuids дефолтит UUIDv7; обе uid-либы в lock транзитивны. |
| D24 | 2026-07-12 | Template-parity gate: `tpl_flex_vNN` получает status=ready ТОЛЬКО при diff(pg_dump-schema шаблона, migration-set на пустой БД)=0; schema-hash в root_templates; провижининг отказывает клонировать не-ready/hash-mismatch шаблон. | Pre-mortem A3 (findings/P2-premortem.md): дрейф golden-шаблона = новые аккаунты без security-констрейнтов, тихо. |
| D25 | 2026-07-12 | Supply-chain-якорь stancl: пин-коммит зеркалируется в собственный форк (composer тянет из форка, не из archtechx-ветки); jobpipeline пин ТОЧНО `2.0.0-rc7` в корневом composer.json. | Pre-mortem B1/B4: force-push/rebase master делает sha недостижимым — чистый CI падает mid-phase; RAG:✅ 2026-07-12 GitHub API (v4 = только master, rc7 — последний тег). |
| D26 | 2026-07-12 | B-10 §4.5 DX-генераторы (make:module/make:entity, flex:modules:why), §4.6 MCP dev-сервер, AC-33/AC-34, AC-12 N−1 CI-прогон — отложены из P1 (DX-волна после ядра; component-tied части уже покрыты D6). Loop-back: новый item по факту потребителя. | Аудит F13: секции не были ни замаплены, ни помечены отложенными — теперь явный deferred. |
| D27 | 2026-07-13 | `DomainEvent` (B-10 §3.1: eventId/occurredAt/tenant/`name()`) — каноническая база доменных событий ядра; заменяет пустой маркер `CoreEvent` (удалён, легально pre-1.0 — D17). `Cancellable` (Architecture_CoreX §7.3) — ОТДЕЛЬНЫЙ опциональный интерфейс для отменяемых pre-action событий (`cancel()`/`isCancelled()`), НЕ примешивается в базу: большинство доменных событий информационные. `FeatureFlags`/`DatabaseFeatureFlags`/`FeatureFlagDefinition` (persisted, `sys_feature_flags`/`sys_feature_overrides`) заменяют интерим `FeatureResolver`/`ConfigFeatureResolver` (config-driven, удалены) — единый источник истины. | B-10 §3.1 задаёт форму DomainEvent явно; Architecture_CoreX §7.3 (`CoreXEvent`) — иллюстративный документ (открытое vendor-имя, D11), не владелец контракта; `CoreEvent` был пустым маркером без применяемой формы. RAG:✅ vault B-10 §3.1 (302–336) + Architecture_CoreX §7.3 2026-07-13 |

## 6. Update Log

| Дата | Кто (role/model) | Что |
|:--|:--|:--|
| 2026-07-06 | plan-design/opus | План создан из EXECUTION_ROADMAP P0+P1; дельта посчитана против текущего кода corex (fan-out 3 агента: B-10, B-11, инвентарь). Скелеты P1/P2 (по 13 items), P1.1 и P2.1 детализированы как образцы. |
| 2026-07-08 | plan-design/opus | Фаза P1 спроектирована: P1.2–P1.13 доведены до исполнимого ТЗ по коду и B-10. Добавлены D9–D12 — детали см. phases/P1.md. |
| 2026-07-08 | plan-design/opus | Фаза P2 спроектирована: P2.2–P2.13 доведены до ТЗ (fan-out 4 агента). Добавлены D13–D16; 26/26 items детализированы — детали см. phases/P2.md. |
| 2026-07-12 | plan-design/opus | P1 DoR-финализация (детализация уже была готова 2026-07-08): +open-questions.md (OQ-1..4), +findings/P1-premortem.md, RAG-маркеры D-log, +D17 semver-политика, per-item routing §3, handoff→launch-block. Items не переписаны. |
| 2026-07-12 | plan-design/fable (ultracode) | Max-design обеих фаз (52 агента): RAG 8 тем + аудит (21 находка внесена) + pre-mortem P2. +D18–D26, +OQ-5..9, v0.2.0 — детали см. findings/P1-P2-audit-2026-07-12.md. |
| 2026-07-13 | plan-exec/sonnet | P1.1 закрыт: UUIDv7 — дефолт ID-strategy (enum-ветка + IdColumns uuid-тип + config) — детали см. phases/P1.md P1.1 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.12 закрыт: контракты TenantContext/AccountRef/WorkspaceRef + TenantContextResolver + SingleAccountResolver-заглушка (boxed) — детали см. phases/P1.md P1.12 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.2 закрыт: EntityRegistry v2 (get/has/all/byCapability/forModel) + новый пакет flex/modules, миграция crm/tasks на точечные handles — детали см. phases/P1.md P1.2 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.3 закрыт: CorexModule/Manifest/Dep/SettingDefault + ModuleDiscovery (сканер+fallback) + `flex:modules:compile` (discovery-часть) — детали см. phases/P1.md P1.3 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.4 закрыт: `Extend\*` (маркер+8 extenders) + `RegistryCompiler`/`CompilationValidator`/`CompilationException` (пайплайн Entity→…→Workflow, sleeping cross-module extenders) + `Manifest::extends()` — детали см. phases/P1.md P1.4 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.5 закрыт: `ModuleRegistry` compiled hot-path (atomic write, per-account tagged cache) + `ModuleActivationGate`/`AllowAllActivationGate` (D10 boxed-дефолт) — детали см. phases/P1.md P1.5 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.6 закрыт: `ModuleLifecycle`-FSM + `RecordsRegistrar` + 3 миграции `mod_*`, вводит PG16 тест-лейн — детали см. phases/P1.md P1.6 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.7 закрыт: Settings v2 (namespace/scope, каскад user→workspace→account→manifest-default, `sys_settings` PG-only, шифрование+маска sensitive, `SettingChanged`) — детали см. phases/P1.md P1.7 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.8 закрыт: Audit v2 (queued AuditLogger/AuditEntry, `sys_audit_log` PG-only с BRIN, единый AuditObserver для HasAuditLog+byCapability(Auditable)) — детали см. phases/P1.md P1.8 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.9 закрыт: DomainEvent-база (D27, заменяет CoreEvent) + FeatureFlags v2 (sys_feature_flags/overrides PG-only, DatabaseFeatureFlags заменяет ConfigFeatureResolver) + SettingChanged/FeatureFlagToggled на DomainEvent — детали см. phases/P1.md P1.9 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.10 закрыт: sys_departments (ltree/GIST) + sys_department_user + DepartmentRepository (find/subtree/primaryOf/move — один UPDATE по path <@, pg_advisory_xact_lock, DepartmentCycleException) + DepartmentMoved на DomainEvent — детали см. phases/P1.md P1.10 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.11 закрыт: PubSub-контракт + 3 драйвера (Null in-memory sync, Redis, DatabasePoll на sys_pubsub_messages cursor-poll) + PubSubManager (config-driven выбор, D10) + request-cycle guard на Redis/DatabasePoll — детали см. phases/P1.md P1.11 Completion Notes. |
| 2026-07-13 | plan-exec/sonnet | P1.13 закрыт (🟠): PHPStan-расширение `flex/modules-lint` — 9 правил transaction-pooling дисциплины (6 простых + 3 контекстных на neon-карте владения); фаза P1 терминальна — детали см. phases/P1.md P1.13 Completion Notes. |
