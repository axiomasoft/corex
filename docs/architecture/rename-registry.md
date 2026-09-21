# Реестр переименований CoreX

**Версия:** 1.0.0 · **Статус:** нормативный · **Уровень:** 0 (`corex/*`)

Реестр фиксирует целевые имена **до** исполнения (D1). **В фазе P1 реестр не исполняется:**
ни класс, ни файл, ни пакет не переименовываются (NON-001). Исполнение — в названных фазах.

Колонка «Условие» называет, что должно произойти до переименования; «—» — условий нет.

| Сейчас | Целевое | Пакет | Фаза | Условие | Источник |
|:--|:--|:--|:--|:--|:--|
| `Dep` | `ModuleDependency` | modules | P15 | — | A42 |
| `Dep::$type` | `$kind` | modules | P15 | — | A42 |
| `Dep::$target` | `$package` | modules | P15 | — | A42 |
| `CorexModule` | `ModuleDefinition` | modules | P15 | миграция потребителей в том же item'е, `CHANGELOG`, без deprecated-алиаса до 1.0 | A43 |
| `Manifest` | `ModuleManifest` | modules | P15 | — | A44 |
| `GateDecision` | `ModuleActivationDecision` | modules | P15 | — | A44 |
| `SettingDefault` | `DefaultSettingDefinition` | modules | P15 | — | A44 |
| `ModuleContext` | `ModuleRuntimeContext` | modules | P15 | — | A44 |
| `WhyCommand` | `ExplainModuleCommand` | modules | P15 | сигнатура artisan `corex:modules:why` **сохраняется** | A46 |
| `RegistryFieldCatalogue` | `ModuleFieldCatalogue` | attributes | P10/P15 | после P10 как источник composite | A50 |
| `StructuralAttributeFilter` | `SchemaAttributeFilter` | attributes | P15 | — | A79 |
| `WriteResult` | `AttributeWriteResult` | attributes | P15 | — | A79 |
| `OperationKey` | `SchemaOperationId` | attributes | P15 | — | A79 |
| `ImportTarget` | `TransferSink` | transfer | P15 | — | A59 |
| `DatabaseTransferRunner` | `SqlTransferExecutor` | transfer | P15 | — | A59/A79 |
| `MeilisearchQueryAdapter` | `MeilisearchSearchProvider` | search-meilisearch | P15 | — | A62 |
| `UnsupportedSearchProvider` | `UnavailableSearchProvider` | search | P15 | — | A63 |
| `DueSource` | `DueWakeupSource` | wakeup | P15 | — | A70 |
| `WakeupHandler` | `DueWakeupHandler` | wakeup | P15 | — | A70 |
| `WakeupHint` | `WakeupScheduleHint` | wakeup | P15 | — | A70 |
| `WakeupSweeper` | `DueWakeupSweeper` | wakeup | P15 | — | A70 |
| `Dispatcher` | `OperationDispatcher` | api | P15 | после разделения ответственностей в P13 | A68 |
| `ErrorBoundary` | `OperationErrorBoundary` | api | P15 | — | A68 |
| `RequestMetadata` | `InvocationMetadata` | api | P15 | — | A68 |
| `OpenApi` | `OpenApiDocumentGenerator` | api | P15 | — | A68 |
| `AccountsResult` | `AvailableAccounts` | tenancy | P15 | — | A79 |
| `TasksWorkflowModule` | `TaskWorkflowIntegration` | tasks-workflow | P15 | discovery; идентификатор модуля (composer-имя) **не меняется** | A80 |
| `corex/api` | `corex/operations` | api | P15 | **решение владельца Q1** после P13 | A67 |
| `corex/auth` | `corex/access` | auth | P15 | **решение владельца Q2** | A72 |

`RecordRules` в реестр **не входит**: класс живёт в `packages/crm/src/Support/`, а не в
`corex/auth` (A71 опровергнуто).
