# intra-refs

Ссылки между разными верхними записями `src/` внутри пакета (count = уникальные пары файл→символ; учтены `use`, FQCN в коде и строках). Ячейка матрицы — from (строка) → to (столбец); `n/m` = всего / из них в чужой `Internal`.


## axiomasoft/corex

| from \ to | `<root>` | `Audit` | `Config` | `Context` | `Data` | `Database` | `Departments` | `Errors` | `Features` | `Messaging` | `Modules` | `Observability` | `Settings` | `Storage` | `Time` |
|:--|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 3/0 | 5/0 | 5/0 | 3/0 |  | 1/0 |  | 1/0 | 19/6 | 2/1 | 2/0 | 2/0 | 2/0 | 1/0 |
| `Audit` |  |  | 3/0 | 5/0 |  | 1/0 |  |  |  |  |  |  |  |  |  |
| `Config` |  | 5/0 |  | 9/0 | 3/0 | 2/0 | 3/0 |  | 3/0 | 4/0 |  |  | 6/0 |  |  |
| `Context` |  | 1/1 |  |  | 2/0 |  |  |  |  |  |  |  |  |  |  |
| `Data` |  |  |  | 1/0 |  |  |  |  |  | 1/0 |  |  |  |  |  |
| `Database` |  | 1/0 | 2/0 |  |  |  |  |  |  |  |  |  |  |  |  |
| `Departments` |  |  | 2/0 |  |  | 1/0 |  |  |  | 1/0 |  |  |  |  |  |
| `Errors` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `Features` |  |  | 2/0 |  |  | 2/0 |  |  |  | 1/0 |  |  | 4/0 |  |  |
| `Messaging` |  |  | 10/0 | 2/0 |  |  |  |  |  |  |  | 4/0 |  |  |  |
| `Modules` |  | 3/0 | 3/0 | 19/0 |  |  |  |  | 4/0 | 5/0 |  |  | 3/0 |  |  |
| `Observability` |  | 1/0 |  |  |  |  |  | 1/0 |  |  |  |  |  |  |  |
| `Settings` |  |  | 1/0 |  |  | 1/0 |  |  |  | 1/0 |  |  |  |  |  |
| `Storage` |  |  |  | 2/0 |  |  |  |  |  |  |  |  |  |  |  |
| `Time` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

## axiomasoft/corex-access

| from \ to | `<root>` | `Access` | `Contracts` | `Enums` | `Exceptions` | `Extend` | `Identity` | `Matrix` | `Models` | `Roles` | `Support` | `Tenancy` |
|:--|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 6/0 | 3/0 |  |  | 1/0 | 1/0 | 2/0 |  |  | 2/0 | 1/0 |
| `Access` | 3/0 |  | 9/0 | 8/0 | 2/0 |  | 1/0 | 2/0 |  |  | 4/0 |  |
| `Contracts` | 2/0 |  |  | 2/0 |  |  |  |  |  |  |  |  |
| `Enums` |  |  |  |  |  |  |  |  |  |  |  |  |
| `Exceptions` |  |  |  |  |  |  |  |  |  |  |  |  |
| `Extend` |  |  | 1/0 |  |  |  |  | 2/0 |  |  |  |  |
| `Identity` |  | 1/0 |  | 1/0 |  |  |  |  |  |  | 1/0 |  |
| `Matrix` |  | 1/0 | 1/0 | 4/0 |  |  |  |  | 5/0 |  | 3/0 |  |
| `Models` |  |  |  | 2/0 |  |  |  |  |  | 2/0 |  |  |
| `Roles` |  |  |  |  |  |  |  |  | 2/0 |  |  |  |
| `Support` |  |  |  |  |  |  |  |  |  |  |  |  |
| `Tenancy` |  |  |  |  |  |  |  |  |  |  |  |  |

## axiomasoft/corex-custom-fields

| from \ to | `<root>` | `Contracts` | `Data` | `Exceptions` | `Internal` | `Support` |
|:--|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 4/0 | 2/0 |  | 13/13 |  |
| `Contracts` |  |  |  |  |  | 15/0 |
| `Data` |  |  |  | 1/0 |  |  |
| `Exceptions` |  |  |  |  |  |  |
| `Internal` |  | 23/0 | 25/0 |  |  | 55/0 |
| `Support` |  |  |  |  |  |  |

## axiomasoft/corex-intl

| from \ to | `<root>` | `Currency` | `Formatting` | `Locale` | `Money` | `Territory` | `Translation` |
|:--|--:|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 8/4 | 2/1 | 6/4 | 1/0 | 6/4 | 2/1 |
| `Currency` |  |  |  |  |  | 2/0 |  |
| `Formatting` |  |  |  | 2/0 |  |  |  |
| `Locale` |  |  |  |  |  |  |  |
| `Money` |  |  |  |  |  |  |  |
| `Territory` |  | 2/0 |  | 2/1 |  |  |  |
| `Translation` |  |  |  | 2/0 |  | 1/0 |  |

## axiomasoft/corex-media

| from \ to | `<root>` | `Commands` | `Contracts` | `Data` | `Internal` | `Jobs` | `Support` |
|:--|--:|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 1/0 | 3/0 | 1/0 | 7/7 |  | 2/0 |
| `Commands` |  |  |  |  | 1/1 | 2/0 |  |
| `Contracts` |  |  |  | 15/0 |  |  |  |
| `Data` |  |  |  |  |  |  |  |
| `Internal` |  |  | 7/0 | 28/0 |  |  | 2/0 |
| `Jobs` |  |  |  |  | 3/3 |  |  |
| `Support` |  |  |  |  |  |  |  |

## axiomasoft/corex-notifications

| from \ to | `<root>` | `Delivery` | `Digest` | `Inbox` | `Preferences` |
|:--|--:|--:|--:|--:|--:|
| `<root>` |  | 7/5 | 6/5 | 4/2 | 2/1 |
| `Delivery` |  |  |  | 9/2 |  |
| `Digest` |  | 4/1 |  | 5/1 | 1/0 |
| `Inbox` |  | 4/0 |  |  |  |
| `Preferences` |  |  |  | 4/2 |  |

## axiomasoft/corex-operations

| from \ to | `<root>` | `Authorization` | `Bulk` | `Cost` | `Definition` | `Errors` | `Http` | `Idempotency` | `Invocation` | `Laravel` | `OpenApi` | `Pagination` | `Testing` | `Validation` | `Versioning` |
|:--|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 9/4 | 3/0 | 1/0 | 4/1 | 4/2 |  | 4/2 | 7/3 | 3/0 | 1/0 | 3/0 |  |  |  |
| `Authorization` |  |  |  |  | 6/0 |  |  | 1/0 | 7/0 |  |  |  |  |  |  |
| `Bulk` |  |  |  |  |  |  |  |  |  | 1/0 |  |  |  | 2/0 |  |
| `Cost` |  |  |  |  | 4/0 | 2/0 |  |  | 2/0 | 1/0 |  |  |  | 3/0 |  |
| `Definition` |  |  |  |  |  | 1/0 |  | 2/0 | 2/0 |  |  |  |  | 2/0 |  |
| `Errors` |  |  |  |  |  |  | 1/1 |  | 6/0 | 1/0 |  |  |  |  |  |
| `Http` |  |  |  |  | 3/0 | 2/0 |  |  | 2/1 | 1/0 | 1/0 |  |  |  |  |
| `Idempotency` |  | 2/0 |  | 3/3 | 1/1 | 3/1 |  |  | 4/0 |  |  |  |  | 2/0 |  |
| `Invocation` |  | 11/1 |  | 1/1 | 4/0 | 7/1 |  |  |  | 1/0 |  |  |  |  | 3/1 |
| `Laravel` |  |  | 1/1 | 1/1 |  |  |  |  |  |  |  |  |  |  | 3/0 |
| `OpenApi` |  |  |  |  | 5/0 |  |  |  |  | 1/0 |  |  |  | 2/0 | 2/1 |
| `Pagination` |  |  |  |  | 1/0 | 1/0 |  |  |  |  |  |  |  | 2/0 |  |
| `Testing` |  | 6/0 |  | 6/3 | 9/0 | 1/0 |  |  | 16/2 |  |  |  |  |  |  |
| `Validation` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `Versioning` |  |  |  |  | 2/0 |  |  |  |  | 1/0 |  |  |  | 2/0 |  |

## axiomasoft/corex-panels

| from \ to | `<root>` | `Contracts` | `Data` | `Internal` | `SavedViews` |
|:--|--:|--:|--:|--:|--:|
| `<root>` |  | 1/0 |  | 3/3 | 1/0 |
| `Contracts` |  |  | 9/0 |  |  |
| `Data` |  |  |  |  |  |
| `Internal` |  | 2/0 | 5/0 |  | 11/4 |
| `SavedViews` |  |  |  |  |  |

## axiomasoft/corex-recurrence

| from \ to | `<root>` | `Contracts` | `Data` | `Exceptions` | `Internal` |
|:--|--:|--:|--:|--:|--:|
| `<root>` |  | 1/0 | 7/0 | 1/0 | 2/2 |
| `Contracts` |  |  | 5/0 |  |  |
| `Data` |  |  |  |  | 2/2 |
| `Exceptions` |  |  |  |  |  |
| `Internal` |  |  | 4/0 |  |  |

## axiomasoft/corex-search

| from \ to | `<root>` | `Contracts` | `Exceptions` | `Internal` | `Support` | `Testing` |
|:--|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 3/0 | 1/0 | 3/3 | 1/0 |  |
| `Contracts` |  |  |  |  | 13/0 |  |
| `Exceptions` |  |  |  |  |  |  |
| `Internal` |  | 4/0 |  |  | 17/0 |  |
| `Support` |  |  |  |  |  |  |
| `Testing` |  | 1/0 |  | 1/1 | 2/0 |  |

## axiomasoft/corex-search-intl

| from \ to | `<root>` | `Contracts` | `Data` | `Internal` |
|:--|--:|--:|--:|--:|
| `<root>` |  | 1/0 |  | 2/2 |
| `Contracts` |  |  |  |  |
| `Data` |  |  |  |  |
| `Internal` |  | 1/0 | 2/0 |  |

## axiomasoft/corex-search-meilisearch

| from \ to | `<root>` | `Exceptions` | `Internal` |
|:--|--:|--:|--:|
| `<root>` |  | 1/0 | 1/1 |
| `Exceptions` |  |  |  |
| `Internal` |  |  |  |

## axiomasoft/corex-tasks

| from \ to | `<root>` | `Access` | `Planning` | `Project` | `Sprint` | `Task` | `Time` |
|:--|--:|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 6/3 | 3/2 | 1/0 |  | 9/0 |  |
| `Access` | 1/0 |  | 4/0 |  |  | 19/0 |  |
| `Planning` |  | 2/1 |  | 7/0 | 7/0 | 2/0 | 6/0 |
| `Project` |  |  |  |  | 1/0 | 3/0 |  |
| `Sprint` |  |  |  | 1/0 |  | 2/0 |  |
| `Task` |  | 10/6 |  | 5/0 | 2/0 |  | 1/0 |
| `Time` |  |  |  |  |  | 3/0 |  |

## axiomasoft/corex-tasks-workflow

| from \ to | `<root>` | `Contracts` | `Data` | `Exceptions` | `Internal` |
|:--|--:|--:|--:|--:|--:|
| `<root>` |  | 1/0 |  |  | 2/2 |
| `Contracts` |  |  | 3/0 |  |  |
| `Data` |  |  |  |  |  |
| `Exceptions` |  |  |  |  |  |
| `Internal` |  | 2/0 | 6/0 | 7/0 |  |

## axiomasoft/corex-tenancy

| from \ to | `<root>` | `Account` | `Authentication` | `Contracts` | `DomainTrust` | `Identity` | `Impersonation` | `Integration` | `Laravel` | `Membership` | `Provisioning` | `Runtime` | `Workspace` |
|:--|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| `<root>` |  |  |  |  |  |  |  |  | 12/0 |  |  |  |  |
| `Account` |  |  |  |  | 1/0 |  |  |  | 2/0 |  | 7/0 |  |  |
| `Authentication` |  | 1/0 |  |  |  | 1/0 |  |  | 3/0 |  |  |  |  |
| `Contracts` |  |  |  |  | 12/0 |  |  |  |  | 4/0 | 4/0 |  |  |
| `DomainTrust` |  | 1/0 |  | 5/0 |  |  |  |  |  |  |  |  |  |
| `Identity` |  | 1/0 |  |  |  |  |  |  |  |  |  |  |  |
| `Impersonation` |  | 1/0 | 1/0 |  |  |  |  |  | 3/0 |  |  |  |  |
| `Integration` |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `Laravel` | 1/0 | 4/0 | 7/0 | 6/0 | 5/0 | 5/0 | 3/0 | 3/0 |  |  | 13/0 | 6/0 | 6/0 |
| `Membership` |  | 1/0 |  | 2/0 |  | 2/0 |  |  |  |  |  |  |  |
| `Provisioning` |  | 8/0 |  | 3/0 |  |  |  |  | 3/0 |  |  |  |  |
| `Runtime` |  | 1/0 |  |  |  |  |  |  | 1/0 |  |  |  |  |
| `Workspace` |  |  |  | 1/0 |  | 2/0 |  |  |  |  |  |  |  |

## axiomasoft/corex-tools

| from \ to | `<root>` | `Contracts` | `Data` | `Internal` |
|:--|--:|--:|--:|--:|
| `<root>` |  | 5/0 |  | 6/6 |
| `Contracts` |  |  | 7/0 |  |
| `Data` |  |  |  | 2/2 |
| `Internal` |  | 12/0 | 12/0 |  |

## axiomasoft/corex-transfer

| from \ to | `Contracts` | `Data` | `Internal` |
|:--|--:|--:|--:|
| `Contracts` |  | 21/0 |  |
| `Data` |  |  |  |
| `Internal` | 10/0 | 22/0 |  |

## axiomasoft/corex-wakeup

| from \ to | `<root>` | `Commands` | `Contracts` | `Data` | `Internal` | `Testing` |
|:--|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 2/0 | 4/0 | 1/0 | 4/4 |  |
| `Commands` |  |  | 1/0 |  | 1/1 |  |
| `Contracts` |  |  |  | 6/0 |  |  |
| `Data` |  |  | 1/0 |  |  |  |
| `Internal` |  |  | 5/0 | 8/0 |  |  |
| `Testing` |  |  |  |  | 2/2 |  |

## axiomasoft/corex-webhooks

| from \ to | `<root>` | `Commands` | `Contracts` | `Data` | `Internal` | `Laravel` |
|:--|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 2/0 |  | 1/0 | 1/1 | 1/0 |
| `Commands` |  |  |  |  | 2/2 |  |
| `Contracts` |  |  |  | 6/0 |  |  |
| `Data` |  |  |  |  |  |  |
| `Internal` |  |  | 9/0 | 15/0 |  |  |
| `Laravel` |  |  | 5/0 | 1/0 | 13/13 |  |

## axiomasoft/corex-workflow

| from \ to | `<root>` | `Contracts` | `Data` | `Internal` |
|:--|--:|--:|--:|--:|
| `<root>` |  | 2/0 |  | 2/2 |
| `Contracts` |  |  | 12/0 |  |
| `Data` |  |  |  |  |
| `Internal` |  | 3/0 | 13/0 |  |

## flex/crm

| from \ to | `<root>` | `Activity` | `ApiShared` | `Company` | `Contact` | `Context` | `Deal` | `Lead` | `Support` |
|:--|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| `<root>` |  | 2/0 | 1/0 | 5/0 | 6/0 |  | 6/0 | 2/0 | 1/0 |
| `Activity` |  |  |  | 1/0 | 1/0 | 2/0 | 1/0 | 1/0 |  |
| `ApiShared` |  |  |  |  |  |  |  |  |  |
| `Company` |  |  | 2/0 |  | 2/0 | 6/0 | 1/0 |  |  |
| `Contact` |  |  | 3/0 | 3/0 |  | 9/0 | 1/0 |  |  |
| `Context` |  | 2/0 |  | 1/0 | 1/0 |  | 1/0 | 1/0 | 1/0 |
| `Deal` |  |  |  | 1/0 | 1/0 | 4/0 |  |  |  |
| `Lead` |  |  |  | 2/0 | 5/0 | 4/0 | 2/0 |  |  |
| `Support` |  |  |  |  |  |  |  |  |  |

## Все ссылки в чужой `Internal` (другая верхняя запись, тот же пакет)

Всего: 162.

| пакет | from | to (symbol) |
|:--|:--|:--|
| axiomasoft/corex | `packages/core/src/Context/ImpersonationGuard.php` | `CoreX\Audit\Internal\AuditLog` |
| axiomasoft/corex | `packages/core/src/CoreServiceProvider.php` | `CoreX\Messaging\Internal\ArrayMessageContractRegistry` |
| axiomasoft/corex | `packages/core/src/CoreServiceProvider.php` | `CoreX\Messaging\Internal\NullTraceContextBridge` |
| axiomasoft/corex | `packages/core/src/CoreServiceProvider.php` | `CoreX\Messaging\Internal\SqlDeliveryHealth` |
| axiomasoft/corex | `packages/core/src/CoreServiceProvider.php` | `CoreX\Messaging\Internal\SqlInbox` |
| axiomasoft/corex | `packages/core/src/CoreServiceProvider.php` | `CoreX\Messaging\Internal\SqlOutbox` |
| axiomasoft/corex | `packages/core/src/CoreServiceProvider.php` | `CoreX\Messaging\Internal\SqlOutboxRelay` |
| axiomasoft/corex | `packages/core/src/CoreServiceProvider.php` | `CoreX\Modules\Internal\ServiceProviderModuleRegistrar` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Laravel\AttributeComposition` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Laravel\AttributeCompositionRegistrar` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Laravel\AttributeMessagingRegistrar` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Query\SchemaAttributeFilter` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Schema\ModuleFieldCatalogue` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Schema\QueuedSchemaOperations` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Schema\StaticFieldCatalogue` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Storage\ConfiguredAttributeStoreResolver` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Storage\ResolvingAttributeStore` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\UnavailableAttributes` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Values\EavAttributeStore` |
| axiomasoft/corex-custom-fields | `packages/custom-fields/src/AttributesServiceProvider.php` | `CoreX\CustomFields\Internal\Values\JsonbAttributeStore` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Currency\Internal\BrickCurrencyConverter` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Currency\Internal\BrickCurrencyMetadata` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Currency\Internal\SqlRateImporter` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Currency\Internal\SqlRateProvider` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Formatting\Internal\PlainMoneyFormatter` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Locale\Internal\Bcp47Validator` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Locale\Internal\DefaultLocaleResolver` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Locale\Internal\LocalePolicyBuilder` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Locale\Internal\RequestLocaleScope` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Territory\Internal\Reference\FixtureReferenceNormalizer` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Territory\Internal\Reference\ReferenceCursorCodec` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Territory\Internal\Reference\SqlDatasetImporter` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Territory\Internal\Reference\SqlGeographyProvider` |
| axiomasoft/corex-intl | `packages/intl/src/IntlServiceProvider.php` | `CoreX\Intl\Translation\Internal\LaravelTranslationReader` |
| axiomasoft/corex-intl | `packages/intl/src/Territory/Internal/Address/AddressSuggestGuard.php` | `CoreX\Intl\Locale\Internal\Bcp47Validator` |
| axiomasoft/corex-media | `packages/media/src/Commands/SweepMediaOperations.php` | `CoreX\Media\Internal\MediaOperationStore` |
| axiomasoft/corex-media | `packages/media/src/Jobs/ConvertMedia.php` | `CoreX\Media\Internal\ConversionCoordinator` |
| axiomasoft/corex-media | `packages/media/src/Jobs/PurgeMedia.php` | `CoreX\Media\Internal\MediaOperationConflict` |
| axiomasoft/corex-media | `packages/media/src/Jobs/PurgeMedia.php` | `CoreX\Media\Internal\MediaOperationStore` |
| axiomasoft/corex-media | `packages/media/src/MediaServiceProvider.php` | `CoreX\Media\Internal\ConversionCoordinator` |
| axiomasoft/corex-media | `packages/media/src/MediaServiceProvider.php` | `CoreX\Media\Internal\ConversionProfiles` |
| axiomasoft/corex-media | `packages/media/src/MediaServiceProvider.php` | `CoreX\Media\Internal\DenyMediaPolicy` |
| axiomasoft/corex-media | `packages/media/src/MediaServiceProvider.php` | `CoreX\Media\Internal\GdThumbnailDriver` |
| axiomasoft/corex-media | `packages/media/src/MediaServiceProvider.php` | `CoreX\Media\Internal\MediaOperationStore` |
| axiomasoft/corex-media | `packages/media/src/MediaServiceProvider.php` | `CoreX\Media\Internal\SqlMediaLibrary` |
| axiomasoft/corex-media | `packages/media/src/MediaServiceProvider.php` | `CoreX\Media\Internal\SqlRelationLookup` |
| axiomasoft/corex-notifications | `packages/notifications/src/Delivery/Internal/ChannelDrivers.php` | `CoreX\Notifications\Inbox\Internal\NotificationOperationConflict` |
| axiomasoft/corex-notifications | `packages/notifications/src/Delivery/Internal/DeliveryStore.php` | `CoreX\Notifications\Inbox\Internal\NotificationOperationConflict` |
| axiomasoft/corex-notifications | `packages/notifications/src/Digest/Internal/SqlDigestBuilder.php` | `CoreX\Notifications\Inbox\Internal\NotificationAccessDenied` |
| axiomasoft/corex-notifications | `packages/notifications/src/Digest/Jobs/DeliverDigest.php` | `CoreX\Notifications\Delivery\Internal\DeliveryCoordinator` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Delivery\Internal\ChannelDrivers` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Delivery\Internal\DeliveryCoordinator` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Delivery\Internal\DeliveryStore` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Delivery\Internal\InAppChannelDriver` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Delivery\Internal\NullRecipientResolver` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Digest\Internal\DigestTick` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Digest\Internal\DigestWakeupAdapter` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Digest\Internal\DigestWakeupHandler` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Digest\Internal\NotificationDigestDueSource` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Digest\Internal\SqlDigestBuilder` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Inbox\Internal\DenyNotificationPolicy` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Inbox\Internal\SqlNotificationCenter` |
| axiomasoft/corex-notifications | `packages/notifications/src/NotificationsServiceProvider.php` | `CoreX\Notifications\Preferences\Internal\SettingsPreferenceRepository` |
| axiomasoft/corex-notifications | `packages/notifications/src/Preferences/Internal/SettingsPreferenceRepository.php` | `CoreX\Notifications\Inbox\Internal\NotificationAccessDenied` |
| axiomasoft/corex-notifications | `packages/notifications/src/Preferences/Internal/SettingsPreferenceRepository.php` | `CoreX\Notifications\Inbox\Internal\NotificationOperationConflict` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Authorization\Internal\DeclaredFieldAuthorizer` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Authorization\Internal\DenyClientScopeAuthorizer` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Authorization\Internal\DenyOperationAuthorizer` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Authorization\Internal\DenyPrincipalResolver` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Definition\Internal\DeclaredSchemaContributor` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Errors\Internal\ErrorMapper` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Errors\Internal\NegotiatingResponseSerializer` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Idempotency\Internal\IdempotentOperationInvoker` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Idempotency\Internal\SqlIdempotencyStore` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Invocation\Internal\CurrentRequestContext` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Invocation\Internal\DefaultOperationInvoker` |
| axiomasoft/corex-operations | `packages/operations/src/ApiServiceProvider.php` | `CoreX\Operations\Invocation\Internal\TrustedContextResolver` |
| axiomasoft/corex-operations | `packages/operations/src/Errors/Internal/NegotiatingResponseSerializer.php` | `CoreX\Operations\Http\Internal\AcceptNegotiation` |
| axiomasoft/corex-operations | `packages/operations/src/Http/DiscoveryController.php` | `CoreX\Operations\Invocation\Internal\CapabilityResolver` |
| axiomasoft/corex-operations | `packages/operations/src/Idempotency/Internal/IdempotentOperationInvoker.php` | `CoreX\Operations\Cost\Internal\BudgetAdmission` |
| axiomasoft/corex-operations | `packages/operations/src/Idempotency/Internal/IdempotentOperationInvoker.php` | `CoreX\Operations\Cost\Internal\BudgetRateLimited` |
| axiomasoft/corex-operations | `packages/operations/src/Idempotency/Internal/IdempotentOperationInvoker.php` | `CoreX\Operations\Cost\Internal\BudgetStoreUnavailable` |
| axiomasoft/corex-operations | `packages/operations/src/Idempotency/Internal/IdempotentOperationInvoker.php` | `CoreX\Operations\Definition\Internal\OperationFingerprint` |
| axiomasoft/corex-operations | `packages/operations/src/Idempotency/Internal/IdempotentOperationInvoker.php` | `CoreX\Operations\Errors\Internal\ErrorMapper` |
| axiomasoft/corex-operations | `packages/operations/src/Invocation/Internal/CapabilityResolver.php` | `CoreX\Operations\Versioning\Internal\ApiProfileFactory` |
| axiomasoft/corex-operations | `packages/operations/src/Invocation/Internal/DefaultOperationInvoker.php` | `CoreX\Operations\Authorization\Internal\DeclaredFieldAuthorizer` |
| axiomasoft/corex-operations | `packages/operations/src/Invocation/Internal/DefaultOperationInvoker.php` | `CoreX\Operations\Cost\Internal\BudgetAdmission` |
| axiomasoft/corex-operations | `packages/operations/src/Invocation/OperationDispatcher.php` | `CoreX\Operations\Errors\Internal\ErrorMapper` |
| axiomasoft/corex-operations | `packages/operations/src/Laravel/Commands/PruneApiOperations.php` | `CoreX\Operations\Bulk\Internal\BulkRetention` |
| axiomasoft/corex-operations | `packages/operations/src/Laravel/Commands/PruneApiOperations.php` | `CoreX\Operations\Cost\Internal\BudgetRetention` |
| axiomasoft/corex-operations | `packages/operations/src/OpenApi/Commands/WriteApiSnapshots.php` | `CoreX\Operations\Versioning\Internal\ApiProfileFactory` |
| axiomasoft/corex-operations | `packages/operations/src/Testing/InvocationFixtures.php` | `CoreX\Operations\Cost\Internal\BudgetAdmission` |
| axiomasoft/corex-operations | `packages/operations/src/Testing/InvocationFixtures.php` | `CoreX\Operations\Cost\Internal\DeclaredCostEstimator` |
| axiomasoft/corex-operations | `packages/operations/src/Testing/InvocationFixtures.php` | `CoreX\Operations\Cost\Internal\SqlOperationBudget` |
| axiomasoft/corex-operations | `packages/operations/src/Testing/InvocationFixtures.php` | `CoreX\Operations\Invocation\Internal\DefaultOperationInvoker` |
| axiomasoft/corex-operations | `packages/operations/src/Testing/ResolvedContexts.php` | `CoreX\Operations\Invocation\Internal\ResolvedContext` |
| axiomasoft/corex-panels | `packages/panels/src/Internal/SavedViewsRegistrar.php` | `CoreX\Panels\SavedViews\Internal\DenyViewAccessPolicy` |
| axiomasoft/corex-panels | `packages/panels/src/Internal/SavedViewsRegistrar.php` | `CoreX\Panels\SavedViews\Internal\SqlSavedViewRepository` |
| axiomasoft/corex-panels | `packages/panels/src/Internal/SavedViewsRegistrar.php` | `CoreX\Panels\SavedViews\Internal\UnavailableViewSchemaProvider` |
| axiomasoft/corex-panels | `packages/panels/src/Internal/SavedViewsRegistrar.php` | `CoreX\Panels\SavedViews\Internal\ViewConfigValidator` |
| axiomasoft/corex-panels | `packages/panels/src/PanelsServiceProvider.php` | `CoreX\Panels\Internal\EntityViewSchemaProvider` |
| axiomasoft/corex-panels | `packages/panels/src/PanelsServiceProvider.php` | `CoreX\Panels\Internal\ModulePanelCatalogue` |
| axiomasoft/corex-panels | `packages/panels/src/PanelsServiceProvider.php` | `CoreX\Panels\Internal\SavedViewsRegistrar` |
| axiomasoft/corex-recurrence | `packages/recurrence/src/Data/RecurrenceSet.php` | `CoreX\Recurrence\Internal\CanonicalHasher` |
| axiomasoft/corex-recurrence | `packages/recurrence/src/Data/RecurrenceSet.php` | `CoreX\Recurrence\Internal\RuntimeProfile` |
| axiomasoft/corex-recurrence | `packages/recurrence/src/RRuleRecurrenceExpander.php` | `CoreX\Recurrence\Internal\CanonicalHasher` |
| axiomasoft/corex-recurrence | `packages/recurrence/src/RRuleRecurrenceExpander.php` | `CoreX\Recurrence\Internal\RuntimeProfile` |
| axiomasoft/corex-search | `packages/search/src/SearchManager.php` | `CoreX\Search\Internal\Postgres\PostgresSearchProvider` |
| axiomasoft/corex-search | `packages/search/src/SearchManager.php` | `CoreX\Search\Internal\Query\SearchCursorCodec` |
| axiomasoft/corex-search | `packages/search/src/SearchManager.php` | `CoreX\Search\Internal\UnavailableSearchProvider` |
| axiomasoft/corex-search | `packages/search/src/Testing/SearchFixtures.php` | `CoreX\Search\Internal\Query\PolicySearchHydrator` |
| axiomasoft/corex-search-intl | `packages/search-intl/src/SearchIntlServiceProvider.php` | `CoreX\SearchIntl\Internal\LocalizedFieldProjector` |
| axiomasoft/corex-search-intl | `packages/search-intl/src/SearchIntlServiceProvider.php` | `CoreX\SearchIntl\Internal\SqlSavedViewLabelReader` |
| axiomasoft/corex-search-meilisearch | `packages/search-meilisearch/src/MeilisearchServiceProvider.php` | `CoreX\SearchMeilisearch\Internal\MeilisearchSearchProvider` |
| axiomasoft/corex-tasks | `packages/tasks/src/Planning/Internal/PlanningCommand.php` | `CoreX\Tasks\Access\Internal\TaskModuleGuard` |
| axiomasoft/corex-tasks | `packages/tasks/src/Task/Actions/CompleteTask.php` | `CoreX\Tasks\Access\Internal\TaskCompletion` |
| axiomasoft/corex-tasks | `packages/tasks/src/Task/Actions/CompleteTask.php` | `CoreX\Tasks\Access\Internal\TaskModuleGuard` |
| axiomasoft/corex-tasks | `packages/tasks/src/Task/Actions/CreateTask.php` | `CoreX\Tasks\Access\Internal\TaskModuleGuard` |
| axiomasoft/corex-tasks | `packages/tasks/src/Task/Actions/MoveTaskToStage.php` | `CoreX\Tasks\Access\Internal\TaskCompletion` |
| axiomasoft/corex-tasks | `packages/tasks/src/Task/Actions/MoveTaskToStage.php` | `CoreX\Tasks\Access\Internal\TaskModuleGuard` |
| axiomasoft/corex-tasks | `packages/tasks/src/Task/Actions/UpdateTask.php` | `CoreX\Tasks\Access\Internal\TaskModuleGuard` |
| axiomasoft/corex-tasks | `packages/tasks/src/TasksServiceProvider.php` | `CoreX\Tasks\Access\Internal\DenyTaskAccess` |
| axiomasoft/corex-tasks | `packages/tasks/src/TasksServiceProvider.php` | `CoreX\Tasks\Access\Internal\DenyTaskMutationAccess` |
| axiomasoft/corex-tasks | `packages/tasks/src/TasksServiceProvider.php` | `CoreX\Tasks\Access\Internal\DenyTaskPlanningAccess` |
| axiomasoft/corex-tasks | `packages/tasks/src/TasksServiceProvider.php` | `CoreX\Tasks\Planning\Internal\PlanningCommand` |
| axiomasoft/corex-tasks | `packages/tasks/src/TasksServiceProvider.php` | `CoreX\Tasks\Planning\Internal\PlanningOperationsServiceProvider` |
| axiomasoft/corex-tasks-workflow | `packages/tasks-workflow/src/TasksWorkflowServiceProvider.php` | `CoreX\TasksWorkflow\Internal\CaptureTaskCompletion` |
| axiomasoft/corex-tasks-workflow | `packages/tasks-workflow/src/TasksWorkflowServiceProvider.php` | `CoreX\TasksWorkflow\Internal\SqlTaskRecurrenceMaterializer` |
| axiomasoft/corex-tools | `packages/tools/src/Data/ToolDescriptor.php` | `CoreX\Tools\Internal\ToolContractHasher` |
| axiomasoft/corex-tools | `packages/tools/src/Data/ToolDescriptor.php` | `CoreX\Tools\Internal\ToolName` |
| axiomasoft/corex-tools | `packages/tools/src/ToolSectionCompiler.php` | `CoreX\Tools\Internal\ApiToolValidator` |
| axiomasoft/corex-tools | `packages/tools/src/ToolServiceProvider.php` | `CoreX\Tools\Internal\CompiledToolCatalogue` |
| axiomasoft/corex-tools | `packages/tools/src/ToolServiceProvider.php` | `CoreX\Tools\Internal\DenyToolPolicy` |
| axiomasoft/corex-tools | `packages/tools/src/ToolServiceProvider.php` | `CoreX\Tools\Internal\GuardedToolInvoker` |
| axiomasoft/corex-tools | `packages/tools/src/ToolServiceProvider.php` | `CoreX\Tools\Internal\ReadOnlyToolExecutionGuard` |
| axiomasoft/corex-tools | `packages/tools/src/ToolServiceProvider.php` | `CoreX\Tools\Internal\ToolSchemaSupervisor` |
| axiomasoft/corex-wakeup | `packages/wakeup/src/Commands/ValidateWakeupStamps.php` | `CoreX\Wakeup\Internal\WakeupHandlerRegistry` |
| axiomasoft/corex-wakeup | `packages/wakeup/src/Testing/WakeupFixtures.php` | `CoreX\Wakeup\Internal\SystemClock` |
| axiomasoft/corex-wakeup | `packages/wakeup/src/Testing/WakeupFixtures.php` | `CoreX\Wakeup\Internal\WakeupHandlerRegistry` |
| axiomasoft/corex-wakeup | `packages/wakeup/src/WakeupServiceProvider.php` | `CoreX\Wakeup\Internal\SqlWakeupScheduler` |
| axiomasoft/corex-wakeup | `packages/wakeup/src/WakeupServiceProvider.php` | `CoreX\Wakeup\Internal\SweepRunner` |
| axiomasoft/corex-wakeup | `packages/wakeup/src/WakeupServiceProvider.php` | `CoreX\Wakeup\Internal\SystemClock` |
| axiomasoft/corex-wakeup | `packages/wakeup/src/WakeupServiceProvider.php` | `CoreX\Wakeup\Internal\WakeupHandlerRegistry` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Commands/DeliverWebhooks.php` | `CoreX\Webhooks\Internal\WebhookDeliveryWorker` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Commands/PruneWebhooks.php` | `CoreX\Webhooks\Internal\WebhookRetention` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\CurlWebhookTransport` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\HmacWebhookSigner` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\SqlWebhookDeliveryStore` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\SqlWebhookSubscriptionStore` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookDeliveryWorker` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookFanout` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookKeyRing` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookManagement` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookReplay` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookRetention` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookRetryPolicy` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookTopicCatalogue` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/Laravel/WebhookRegistrar.php` | `CoreX\Webhooks\Internal\WebhookUrlPolicy` |
| axiomasoft/corex-webhooks | `packages/webhooks/src/WebhooksServiceProvider.php` | `CoreX\Webhooks\Internal\WebhookSnapshotSection` |
| axiomasoft/corex-workflow | `packages/workflow/src/WorkflowServiceProvider.php` | `CoreX\Workflow\Internal\SqlWorkflowBinding` |
| axiomasoft/corex-workflow | `packages/workflow/src/WorkflowServiceProvider.php` | `CoreX\Workflow\Internal\UnavailableEngineAdapter` |
