# P4 binding audit — singleton / scoped (P4.12)

**Measured:** 2026-09-21 on working tree after P4.11. **Gate:** `tests/Arch/SingletonInjectionTest.php` + `SingletonInjectionGate`.

## Composition roots (`00-baseline-metrics.md` §3)

| Provider | singleton | scoped | `config(` (baseline) |
|:--|--:|--:|--:|
| `tenancy/.../TenancyServiceProvider.php` | 23 | 5 | 47 |
| `modules/.../ModulesServiceProvider.php` | 12 | 0 | 7 |

## Package rollup (20 live packages + tombstone)

| Package | singleton | scoped | gate-checked (static `::class`) | allowlist hits | non-static rows |
|:--|--:|--:|--:|--:|--:|
| api | 0 | 0 | 0 | 0 | no ServiceProvider in `src` |
| attributes | 1 | 5 | 0 | 0 | 4 |
| auth | 1 | 3 | 1 | 0 | 0 |
| core | 19 | 2 | 8 | 2 | 13 |
| crm | 0 | 0 | 0 | 0 | no ServiceProvider in `src` |
| import-export (tombstone) | — | — | — | — | no `src` provider |
| intl | 0 | 14 | 0 | 0 | 7 |
| media | 2 | 5 | 1 | 0 | 6 |
| modules | 12 | 0 | 2 | 0 | 10 |
| notifications | 5 | 7 | 3 | 0 | 9 |
| panels | 2 | 0 | 0 | 0 | 2 |
| recurrence | 0 | 0 | 0 | 0 | no ServiceProvider in `src` |
| search | 1 | 1 | 0 | 0 | 2 |
| search-intl | 0 | 2 | 0 | 0 | 0 |
| search-meilisearch | 0 | 0 | 0 | 0 | no ServiceProvider in `src` |
| tasks | 0 | 0 | 0 | 0 | no ServiceProvider in `src` |
| tasks-workflow | 1 | 1 | 0 | 0 | 2 |
| tenancy | 23 | 5 | 4 | 0 | 23 |
| transfer | 0 | 0 | 0 | 0 | no ServiceProvider in `src` |
| wakeup | 0 | 2 | 0 | 0 | 2 |
| workflow | 0 | 1 | 0 | 0 | 1 |

## Allowlisted constructor captures (owner phase required)

| Package | Class | Parameter | Owner |
|:--|:--|:--|:--|
| core | `CoreX\Support\ServiceProviderModuleRegistrar` | `app` (`Application`) | P6 |
| core | `CoreX\PubSub\PubSubManager` | `app` (`Application`) | P6 |

## Named gate limit

Bindings whose concrete is a closure, `config()`-resolved class name, or other non-`::class` literal are listed as **не разбирается статически** (`static-limit`). The gate does not infer their constructors; remediation owners must review manually (same principle as P3 D17 — extend scanner, not new tool).

## Full inventory

| Package | Abstract | Lifetime | Static parse? | Captures context? | Verdict |
|:--|:--|:--|:--|:--|:--|
| attributes | `CoreX\Attributes\Contracts\AttributeFilter` | scoped | yes | no | ok (scoped) |
| attributes | `CoreX\Attributes\Contracts\AttributeStore` | scoped | no (static-limit) | no | static-limit |
| attributes | `CoreX\Attributes\Contracts\FieldCatalogue` | scoped | no (static-limit) | no | static-limit |
| attributes | `CoreX\Attributes\Contracts\SchemaOperations` | scoped | no (static-limit) | no | static-limit |
| attributes | `CoreX\Attributes\Data\AttributeTables` | singleton | no (static-limit) | no | static-limit |
| attributes | `CoreX\Attributes\Internal\UnavailableAttributes` | scoped | yes | no | ok (scoped) |
| auth | `AzGuard\Contracts\ContextGuard` | singleton | yes | no | ok |
| auth | `CoreX\Auth\Access\ScopeAvailability` | scoped | yes | no | ok (scoped) |
| auth | `CoreX\Auth\Contracts\AccessChecker` | scoped | yes | no | ok (scoped) |
| auth | `CoreX\Auth\Contracts\ScopeResolver` | scoped | yes | no | ok (scoped) |
| core | `CoreX\Contracts\AuditLogger` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Contracts\AuditRetention` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Contracts\DataScopeAuthorizer` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Contracts\DataScopeCodec` | singleton | yes | no | stateless-ok |
| core | `CoreX\Contracts\DepartmentRepository` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Contracts\FeatureFlags` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Contracts\ModuleRegistrar` | singleton | yes | yes | allowlist (P6) |
| core | `CoreX\Contracts\PubSub` | singleton | yes | yes | allowlist (P6) |
| core | `CoreX\Contracts\SavedViewRepository` | singleton | yes | no | stateless-ok |
| core | `CoreX\Contracts\SettingDefaultsProvider` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Contracts\SettingsRepository` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Contracts\TenantStorage` | scoped | no (static-limit) | no | static-limit |
| core | `CoreX\Contracts\ViewAccessPolicy` | singleton | yes | no | stateless-ok |
| core | `CoreX\Contracts\ViewSchemaProvider` | singleton | yes | no | stateless-ok |
| core | `CoreX\Contracts\WorkerRuntime` | singleton | yes | no | stateless-ok |
| core | `CoreX\Tenancy\Contracts\ImpersonationService` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Tenancy\Contracts\TenancyManager` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Tenancy\Contracts\TenantContextResolver` | singleton | no (static-limit) | no | static-limit |
| core | `CoreX\Tenancy\Contracts\TenantContextRestorer` | scoped | no (static-limit) | no | static-limit |
| core | `CoreX\Views\Internal\ViewConfigValidator` | singleton | yes | no | stateless-ok |
| core | `Psr\Clock\ClockInterface` | singleton | no (static-limit) | no | static-limit |
| intl | `CoreX\Intl\Contracts\CurrencyConverter` | scoped | yes | no | ok (scoped) |
| intl | `CoreX\Intl\Contracts\CurrencyMetadata` | scoped | no (static-limit) | no | static-limit |
| intl | `CoreX\Intl\Contracts\DatasetImporter` | scoped | yes | no | ok (scoped) |
| intl | `CoreX\Intl\Contracts\GeographyProvider` | scoped | yes | no | ok (scoped) |
| intl | `CoreX\Intl\Contracts\LocaleResolver` | scoped | yes | no | ok (scoped) |
| intl | `CoreX\Intl\Contracts\MoneyFormatter` | scoped | no (static-limit) | no | static-limit |
| intl | `CoreX\Intl\Contracts\RateImporter` | scoped | no (static-limit) | no | static-limit |
| intl | `CoreX\Intl\Contracts\RateProvider` | scoped | no (static-limit) | no | static-limit |
| intl | `CoreX\Intl\Contracts\TranslationReader` | scoped | yes | no | ok (scoped) |
| intl | `CoreX\Intl\Data\LocalePolicy` | scoped | no (static-limit) | no | static-limit |
| intl | `CoreX\Intl\Internal\Locale\Bcp47Validator` | scoped | no (static-limit) | no | static-limit |
| intl | `CoreX\Intl\Internal\Locale\RequestLocaleScope` | scoped | yes | no | ok (scoped) |
| intl | `CoreX\Intl\Internal\Reference\FixtureReferenceNormalizer` | scoped | yes | no | ok (scoped) |
| intl | `CoreX\Intl\Internal\Reference\ReferenceCursorCodec` | scoped | no (static-limit) | no | static-limit |
| media | `CoreX\Media\Contracts\MediaLibrary` | scoped | no (static-limit) | no | static-limit |
| media | `CoreX\Media\Contracts\MediaPolicy` | singleton | yes | no | stateless-ok |
| media | `CoreX\Media\Contracts\RelationLookup` | scoped | no (static-limit) | no | static-limit |
| media | `CoreX\Media\Internal\ConversionCoordinator` | scoped | no (static-limit) | no | static-limit |
| media | `CoreX\Media\Internal\ConversionProfiles` | scoped | no (static-limit) | no | static-limit |
| media | `CoreX\Media\Internal\GdThumbnailDriver` | singleton | no (static-limit) | no | static-limit |
| media | `CoreX\Media\Internal\MediaOperationStore` | scoped | no (static-limit) | no | static-limit |
| modules | `'corex-modules.wiring'` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Contracts\SettingDefaultsProvider` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Modules\Contracts\EntityRegistry` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Modules\Contracts\ModuleActivationGate` | singleton | yes | no | stateless-ok |
| modules | `CoreX\Modules\Contracts\ModuleLifecycle` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Modules\Contracts\ModuleRegistry` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Modules\Contracts\ModuleRegistryCache` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Modules\Contracts\ModuleStateReader` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Modules\Contracts\RecordsRegistrar` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Modules\Contracts\RegistryIntrospection` | singleton | no (static-limit) | no | static-limit |
| modules | `CoreX\Modules\Discovery\ModuleDiscovery` | singleton | yes | no | stateless-ok |
| modules | `CoreX\Modules\Registry\ArrayEntityRegistry` | singleton | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Contracts\DigestBuilder` | scoped | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Contracts\NotificationCenter` | scoped | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Contracts\NotificationPolicy` | singleton | yes | no | stateless-ok |
| notifications | `CoreX\Notifications\Contracts\PreferenceRepository` | scoped | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Contracts\RecipientResolver` | singleton | yes | no | stateless-ok |
| notifications | `CoreX\Notifications\Internal\ChannelDrivers` | scoped | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Internal\DeliveryCoordinator` | scoped | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Internal\DeliveryStore` | scoped | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Internal\Digest\DigestWakeupAdapter` | singleton | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Internal\Digest\DigestWakeupHandler` | singleton | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Internal\Digest\NotificationDigestDueSource` | scoped | no (static-limit) | no | static-limit |
| notifications | `CoreX\Notifications\Internal\InAppChannelDriver` | singleton | yes | no | stateless-ok |
| panels | `CoreX\Contracts\ViewSchemaProvider` | singleton | no (static-limit) | no | static-limit |
| panels | `CoreX\Panels\Contracts\PanelCatalogue` | singleton | no (static-limit) | no | static-limit |
| search | `CoreX\Search\Contracts\SearchProvider` | scoped | no (static-limit) | no | static-limit |
| search | `CoreX\Search\SearchManager` | singleton | no (static-limit) | no | static-limit |
| search-intl | `CoreX\SearchIntl\Contracts\SavedViewLabelReader` | scoped | yes | no | ok (scoped) |
| search-intl | `CoreX\SearchIntl\Internal\LocalizedFieldProjector` | scoped | yes | no | ok (scoped) |
| tasks-workflow | `CoreX\Recurrence\Contracts\RecurrenceExpander` | singleton | no (static-limit) | no | static-limit |
| tasks-workflow | `CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand` | scoped | no (static-limit) | no | static-limit |
| tenancy | `$bootstrapper` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Contracts\TenantStorage` | scoped | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Contracts\ImpersonationService` | scoped | yes | no | ok (scoped) |
| tenancy | `CoreX\Tenancy\Contracts\TemplateIntegrityVerifier` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Contracts\TenancyManager` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Contracts\TenantContextResolver` | singleton | yes | no | stateless-ok |
| tenancy | `CoreX\Tenancy\Contracts\TenantDatabaseLifecycle` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Database\SchemaCatalogueSerializer` | singleton | yes | no | stateless-ok |
| tenancy | `CoreX\Tenancy\Database\TemplateIntegrityLock` | singleton | yes | no | stateless-ok |
| tenancy | `CoreX\Tenancy\Database\TemplateIntegrityVerifier` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\DomainTrust\DomainTrustCoordinator` | scoped | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\DomainTrust\DomainTrustStateWriter` | scoped | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Http\Controllers\LogoutController` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Identity\DatabaseIdentitySyncer` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Identity\IdentitySyncer` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Lifecycle\AccountLifecycle` | scoped | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Migrations\MigrationWaveOrchestrator` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Oidc\IdTokenVerifier` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Oidc\JwksKeySet` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Oidc\OidcClient` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Oidc\OidcDiscovery` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Provisioning\PendingPool` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Provisioning\StanclTenantDatabaseLifecycle` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Provisioning\StanclTenantDatabaseProvisioner` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\StanclTenancyManager` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Wakeup\Internal\RootRegistryWakeupHint` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Wakeup\Internal\RootWakeupReconciler` | singleton | no (static-limit) | no | static-limit |
| tenancy | `CoreX\Tenancy\Workspaces\WorkspaceResolver` | singleton | yes | no | stateless-ok |
| wakeup | `CoreX\Wakeup\Contracts\WakeupScheduler` | scoped | no (static-limit) | no | static-limit |
| wakeup | `CoreX\Wakeup\Contracts\WakeupSweeper` | scoped | no (static-limit) | no | static-limit |
| workflow | `CoreX\Workflow\Contracts\WorkflowBinding` | scoped | no (static-limit) | no | static-limit |
