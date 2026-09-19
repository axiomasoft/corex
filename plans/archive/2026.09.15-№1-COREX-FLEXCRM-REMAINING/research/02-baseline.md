# Основания, которые сохраняем

RAG:— Под «достаточно принято» здесь понимается достаточность **для сохранения архитектуры и
отказа от перепроектирования**, в пределах явно подтверждённого поведения. Это не blanket
production acceptance. P1 проверяет source/test/release/consumer цепочку отдельно для каждого R.

## Receipt classes и актуальность

| Evidence | Источник | Что доказано | Граница |
|:--|:--|:--|:--|
| E1 | CoreX archive 2026.09.13-№1-COREX-TENANCY-IDENTITY P2/P3/P4/P5/P7; findings P7.1–P7.5 | Локальные storage/claim/transport/session references и fail-closed adoption; подготовлены DI/AP dossiers | Effective DI 0/34 и AP 0/13 на последней полной записи; подготовительный GREEN не публичная/production приёмка |
| E2 | delivery/crm-install-qualification-20260915/receipt.md + seal.json + candidate.json | G1/G2/G3; пять ZIP из cb79f18095589124759fdd1107144764bb31d094, 12 enabled CRM tables, 7 API operations, isolated physical/boxed consumers L12/L13 | Локальная immutable distribution; не publication/production/populated upgrade; точные counts относятся к source/config/environment receipt |
| E3 | brief/sources/flex-installed.md + flex-installed-inputs.md | P17.1 использовал CoreX 6d23d6e3bf9cbe8f609ebf8b8eefb56216a5eb41, cohort 0.3.2-alpha.20260915, host resolver и base+2 solution installed HTTP reuse | Последующие changed bytes не наследуют E2 full-suite без delta proof; не full B13/B27/B34, не production, не P6.5 closure |
| E4 | delivery/module-install-qualification-20260915/corex-tasks-candidate.json; FLEX-2 P12/P14 evidence | Immutable candidate Tasks и минимальная app-интеграция | Не вся Tasks-collab и не release полного конечного cohort |
| E5 | archive 2026.09.12-№2-COREX-APPROVED-SPECS findings/P5.3-remediation-receipt.md, P5.3-public-consumers.json, D17, handoff | Committed историческая запись шести public results и cold install старого четырёх-пакетного CoreX set с двумя providers | Не current hosted CI/Packagist и не publication нового api/tasks/identity cohort; не повторять уже выполненную публикацию |

Противоречие разрешается по времени и scope: старый P7.5 говорит «distribution отсутствует»;
новый APPROVED-SPECS содержит публикацию. Старый API report содержит 4 PG failures; E2 позднее
содержит их scoped remedy. Ни старый NO-GO, ни поздний GREEN нельзя переносить на чужой cohort.

## Keep catalogue

| ID | Сохраняем | Source/test evidence для квалификации Sol | Что НЕ перепроектировать |
|:--|:--|:--|:--|
| A01 | TenantStorage + StorageCapabilities/ExportArchiveRef; boxed и tenant driver separation | packages/core/src/Contracts/TenantStorage.php; packages/core/src/Storage; packages/tenancy/tests; E1 | Не создавать второй filesystem API; реальные S3/export/usage ограничения квалифицировать отдельно |
| A02 | TenantContext, TenancyManager, AccountConnectionResolver, provisioning/lifecycle и ProvisioningClaim | packages/core/src/Tenancy; packages/tenancy/src/Contracts; packages/tenancy/tests; E1/E2/E3 | DB-per-account и Stancl adapter; не row-level tenant engine, не boxed multi-workspace без consumer trigger |
| A03 | PrincipalWorkspaceResolver + physical host/context lifecycle; ID safety | packages/tenancy/src/Contracts/PrincipalWorkspaceResolver.php; principal/host/identity tests; E2/E3 | Не merge по email/phone; не заменить UUIDv7/ULID/int support глобальным новым ID каноном |
| A04 | EntityRegistry/ModuleRegistry/ModuleLifecycle/ModuleActivationGate, 8 extenders, after-commit events | packages/modules/src/Contracts; packages/modules/src/Extend; tests/Arch/LayerTest.php; E2 | Не девятый extender, второй registry/lifecycle/event bus; новый механизм только RFC |
| A05 | corex/api Principal/ContextResolver/OperationHandler/OperationAuthorizer/ResponseSerializer/SchemaContributor SPI | packages/api/src/Contracts; packages/api/tests; E2/E3; API report | Не новый REST/router/auth framework; профиль transport и concrete policies принадлежат потребителю |
| A06 | Settings/FeatureFlags/Audit/PubSub/MoneyCast | packages/core/src/Contracts; packages/core/src/Casts; root/package tests; COREX-CORE + E2 | Не новый config/flags/audit/money engine; subscriber и policy опциональны, внешний сервис через порт |
| A07 | Task commands/access + ActorData/EntityReference и explicit context | packages/tasks/src/Contracts; packages/tasks/src/Data; packages/tasks/tests; E4 | Не переносить flex/tasks acceptance роли/KPI/status policy в примитивы |
| A08 | flex/crm CrmExecutionContext и approved bounded CRM API/storage | packages/crm; E2/E3 | Это Flex-слой даже внутри monorepo; не переименовывать в corex/crm и не расширять в рамках CoreX-дельт |
| A09 | Optional corex/auth adapter / independently owned AzGuard | packages/auth; SUCCESSOR P2; APPROVED-SPECS private receipt | Не владеть role writer/engine; публичный reusable граф не должен требовать private credential |

## Реальные gaps, которые нельзя скрыть

SavedView/search/headless notifications/media/panels/recurrence/intl и extension bindings требуют
P1 symbol/test inventory и P2 минимального контракта. Отсутствие точного имени не доказывает
отсутствие эквивалентного поведения. Для уже достаточного результата Sol создаёт qualification
item с конкретным receipt, а не новый runtime. Контракт не считается production принят лишь
потому, что он лежит в Contracts или присутствует Unreleased CHANGELOG.
