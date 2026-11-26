# CoreX rename register

**Version** 1.0.0 · **Status** completed (P15.13) · **Level** 0 (`corex/*`)

This register records the original and final names agreed in P1 (D1). All P15 entries were completed (D85). The historical record informs the current [rename checks](../../scripts/rename-completeness.json) and the [root changelog](../../CHANGELOG.md). Conditions describe the prerequisites at the time of the change. A dash indicates that there were none.

| Former name | New name | Package | Phase | Completed in | Condition | Source |
|:--|:--|:--|:--|:--|:--|:--|
| `Dep` | `ModuleDependency` | modules | P15 | P15.1 | — | A42 |
| `Dep::$type` | `$kind` | modules | P15 | P15.1 | — | A42 |
| `Dep::$target` | `$package` | modules | P15 | P15.1 | — | A42 |
| `CorexModule` | `ModuleDefinition` | modules | P15 | P15.1 | Migrate callers in the same change, update the CHANGELOG and omit a pre-1.0 deprecated alias | A43 |
| `Manifest` | `ModuleManifest` | modules | P15 | P15.1 | — | A44 |
| `GateDecision` | `ModuleActivationDecision` | modules | P15 | P15.1 | — | A44 |
| `SettingDefault` | `DefaultSettingDefinition` | modules | P15 | P15.1 | — | A44 |
| `ModuleContext` | `ModuleRuntimeContext` | modules | P15 | P15.1 | — | A44 |
| `WhyCommand` | `ExplainModuleCommand` | modules | P15 | P15.1 | Preserve the `modules:why` Artisan signature | A46 |
| `RegistryFieldCatalogue` | `ModuleFieldCatalogue` | attributes | P10/P15 | P15.2 | After P10, use as a source for the composite catalogue | A50 |
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
| `Dispatcher` | `OperationDispatcher` | api | P15 | P15.8 | After separating responsibilities in P13 | A68 |
| `ErrorBoundary` | `OperationErrorBoundary` | api | P15 | P15.8 | — | A68 |
| `RequestMetadata` | `InvocationMetadata` | api | P15 | P15.8 | — | A68 |
| `OpenApi` | `OpenApiDocumentGenerator` | api | P15 | P15.8 | — | A68 |
| `AccountsResult` | `AvailableAccounts` | tenancy | P15 | P15.9 | — | A79 |
| `RecurringTasksModule` | `TaskWorkflowIntegration` | tasks-workflow | P15 | P15.6 | Preserve discovery and the module ID (Composer name) | A80 |
| `corex/api` | `axiomasoft/corex-operations` | api | P15 | P15.11 | Approved in D134, directory `packages/operations` | A67 |
| `corex/auth` | `axiomasoft/corex-access` | auth | P15 | P15.12 | Approved in D135, directory `packages/access` | A72 |

The original proposal A71 assigned `RecordRules` to `axiomasoft/corex-access` in error. The class lives in `products/flex-crm/src/Context/Internal/` and required no rename.

The gate manifest also covers two outcomes outside these rows (P15.6/P15.10). `CompletionCaptureObserver` became `CaptureTaskCompletion`, and D88 changed the fully qualified names in the kernel zones. See the [kernel zones](core-zones.md).
