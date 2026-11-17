# Реестр переименований CoreX

**Версия:** 1.0.0 · **Статус:** исполнен (P15.13) · **Уровень:** 0 (`corex/*`)

Реестр зафиксирован в P1 (D1) как карта «было → стало». Исполнение P15 завершено (D85);
таблица ниже — исторический источник для `scripts/p15-rename-completeness-manifest.json`
и корневого `CHANGELOG.md`. Незакрытых строк с фазой P15 не осталось.

Колонка «Условие» фиксирует предпосылки на момент исполнения; «—» — дополнительных
предпосылок не было.

| Сейчас | Целевое | Пакет | Фаза | Исполнено | Условие | Источник |
|:--|:--|:--|:--|:--|:--|:--|
| `Dep` | `ModuleDependency` | modules | P15 | P15.1 | — | A42 |
| `Dep::$type` | `$kind` | modules | P15 | P15.1 | — | A42 |
| `Dep::$target` | `$package` | modules | P15 | P15.1 | — | A42 |
| `CorexModule` | `ModuleDefinition` | modules | P15 | P15.1 | миграция потребителей в том же item'е, `CHANGELOG`, без deprecated-алиаса до 1.0 | A43 |
| `Manifest` | `ModuleManifest` | modules | P15 | P15.1 | — | A44 |
| `GateDecision` | `ModuleActivationDecision` | modules | P15 | P15.1 | — | A44 |
| `SettingDefault` | `DefaultSettingDefinition` | modules | P15 | P15.1 | — | A44 |
| `ModuleContext` | `ModuleRuntimeContext` | modules | P15 | P15.1 | — | A44 |
| `WhyCommand` | `ExplainModuleCommand` | modules | P15 | P15.1 | сигнатура artisan `modules:why` **сохраняется** | A46 |
| `RegistryFieldCatalogue` | `ModuleFieldCatalogue` | attributes | P10/P15 | P15.2 | после P10 как источник composite | A50 |
| `StructuralAttributeFilter` | `SchemaAttributeFilter` | attributes | P15 | P15.2 | — | A79 |
| `WriteResult` | `AttributeWriteResult` | attributes | P15 | P15.2 | — | A79 |
| `OperationKey` | `SchemaOperationId` | attributes | P15 | P15.2 | — | A79 |
| `ImportTarget` | `TransferSink` | transfer | P15 | P15.5 | — | A59 |
| `DatabaseTransferRunner` | `SqlTransferExecutor` | transfer | P15 | P15.5 | — | A59/A79 |
| `MeilisearchQueryAdapter` | `MeilisearchSearchProvider` | search-meilisearch | P15 | P15.4 | — | A62 |
| `UnsupportedSearchProvider` | `UnavailableSearchProvider` | search | P15 | P15.3 | — | A63 |
| `DueSource` | `DueWakeupSource` | wakeup | P15 | P15.7 | — | A70 |
| `WakeupHandler` | `DueWakeupHandler` | wakeup | P15 | P15.7 | — | A70 |
| `WakeupHint` | `WakeupScheduleHint` | wakeup | P15 | P15.7 | — | A70 |
| `WakeupSweeper` | `DueWakeupSweeper` | wakeup | P15 | P15.7 | — | A70 |
| `Dispatcher` | `OperationDispatcher` | api | P15 | P15.8 | после разделения ответственностей в P13 | A68 |
| `ErrorBoundary` | `OperationErrorBoundary` | api | P15 | P15.8 | — | A68 |
| `RequestMetadata` | `InvocationMetadata` | api | P15 | P15.8 | — | A68 |
| `OpenApi` | `OpenApiDocumentGenerator` | api | P15 | P15.8 | — | A68 |
| `AccountsResult` | `AvailableAccounts` | tenancy | P15 | P15.9 | — | A79 |
| `RecurringTasksModule` | `TaskWorkflowIntegration` | tasks-workflow | P15 | P15.6 | discovery; идентификатор модуля (composer-имя) **не меняется** | A80 |
| `corex/api` | `axiomasoft/corex-operations` | api | P15 | P15.11 | принято D134; каталог `packages/operations` | A67 |
| `corex/auth` | `axiomasoft/corex-access` | auth | P15 | P15.12 | принято D135; каталог `packages/access` | A72 |

`RecordRules` в реестр **не входит**: класс живёт в `products/flex-crm/src/Context/Internal/`, а не в
`axiomasoft/corex-access` (A71 опровергнуто).

Дополнительные исходы P15.6/P15.10 (не строки реестра) включены в manifest гейта:
`CompletionCaptureObserver` → `CaptureTaskCompletion`; зоновые FQCN D88 — см.
`docs/architecture/core-zones.md` §5.
