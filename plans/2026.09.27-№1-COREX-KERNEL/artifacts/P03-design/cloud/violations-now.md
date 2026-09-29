# violations-now

Гейт D05 на текущем дереве (считает, не чинит). Правила независимы, файл может попасть в несколько.

Трактовка: L1 — файл в корне `src/`, не provider и не `*Module.php`, либо файл в каталоге-корзине уровня пакета (любая из `Contracts|Data|Enums|Events|Exceptions|Internal|Support|Models|Commands|Jobs|Concerns|Http`); L2 — сегмент публичной категории не на индексе 1 пути (индекс 0 = уровень пакета, ≥2 = глубже модуля); L3 — по ролям инвентаря; `Laravel`/`Testing` в L1/L2/L4 исключены, в L3 исключены только `Internal`/`Laravel`; L4 — `Internal` на индексе ≥2 (на индексе 0 это L1); L5 — пара (файл, символ) в чужой верхней записи того же пакета, цель под `Internal`, включая ссылки из корня/`Laravel` (provider-проводка).

## Пакет × правило

| пакет | L1 | L2 | L3 | L3 в public-каталоге | L4 | L5 |
|:--|--:|--:|--:|--:|--:|--:|
| axiomasoft/corex | 0 | 0 | 130 | 25 | 0 | 8 |
| axiomasoft/corex-access | 13 | 7 | 25 | 0 | 0 | 0 |
| axiomasoft/corex-custom-fields | 62 | 12 | 4 | 3 | 0 | 13 |
| axiomasoft/corex-intl | 1 | 0 | 3 | 0 | 0 | 15 |
| axiomasoft/corex-media | 35 | 20 | 7 | 1 | 0 | 11 |
| axiomasoft/corex-notifications | 0 | 0 | 4 | 0 | 0 | 19 |
| axiomasoft/corex-operations | 4 | 0 | 49 | 17 | 0 | 31 |
| axiomasoft/corex-panels | 10 | 7 | 3 | 2 | 0 | 7 |
| axiomasoft/corex-recurrence | 16 | 13 | 2 | 1 | 0 | 4 |
| axiomasoft/corex-search | 29 | 6 | 3 | 0 | 0 | 4 |
| axiomasoft/corex-search-intl | 7 | 2 | 0 | 0 | 0 | 2 |
| axiomasoft/corex-search-meilisearch | 4 | 1 | 1 | 0 | 0 | 1 |
| axiomasoft/corex-tasks | 0 | 0 | 23 | 10 | 0 | 12 |
| axiomasoft/corex-tasks-workflow | 15 | 7 | 1 | 0 | 0 | 2 |
| axiomasoft/corex-tenancy | 8 | 8 | 86 | 9 | 0 | 0 |
| axiomasoft/corex-tools | 23 | 10 | 2 | 1 | 0 | 8 |
| axiomasoft/corex-transfer | 38 | 27 | 1 | 1 | 0 | 0 |
| axiomasoft/corex-wakeup | 18 | 12 | 4 | 0 | 0 | 7 |
| axiomasoft/corex-webhooks | 29 | 12 | 5 | 3 | 0 | 16 |
| axiomasoft/corex-workflow | 13 | 9 | 1 | 0 | 0 | 2 |
| flex/crm | 1 | 0 | 46 | 9 | 0 | 0 |
| **всего** | 326 | 153 | 400 | 82 | 0 | 162 |

Продукт (`flex/crm`) — отдельной строкой (N8); правила D05 к нему приложены для справки.


## L1 — примеры (до 10, по одному кругу от пакета)

- `packages/access/src/Contracts/AccessChecker.php` (axiomasoft/corex-access) — package-level bucket `Contracts`
- `packages/custom-fields/src/Contracts/AttributeFilter.php` (axiomasoft/corex-custom-fields) — package-level bucket `Contracts`
- `packages/intl/src/IntlConfigSection.php` (axiomasoft/corex-intl) — root file other
- `packages/media/src/Commands/SweepMediaOperations.php` (axiomasoft/corex-media) — package-level bucket `Commands`
- `packages/operations/src/Http/ApiController.php` (axiomasoft/corex-operations) — package-level bucket `Http`
- `packages/panels/src/Contracts/PanelCatalogue.php` (axiomasoft/corex-panels) — package-level bucket `Contracts`
- `packages/recurrence/src/Contracts/RecurrenceExpander.php` (axiomasoft/corex-recurrence) — package-level bucket `Contracts`
- `packages/search/src/Contracts/IndexDriver.php` (axiomasoft/corex-search) — package-level bucket `Contracts`
- `packages/search-intl/src/Contracts/SavedViewLabelReader.php` (axiomasoft/corex-search-intl) — package-level bucket `Contracts`
- `packages/search-meilisearch/src/Exceptions/InvalidMeilisearchConfiguration.php` (axiomasoft/corex-search-meilisearch) — package-level bucket `Exceptions`

## L2 — примеры (до 10, по одному кругу от пакета)

- `packages/access/src/Contracts/AccessChecker.php` (axiomasoft/corex-access) — category `Contracts` at depth 0
- `packages/custom-fields/src/Contracts/AttributeFilter.php` (axiomasoft/corex-custom-fields) — category `Contracts` at depth 0
- `packages/media/src/Contracts/ConversionDriver.php` (axiomasoft/corex-media) — category `Contracts` at depth 0
- `packages/panels/src/Contracts/PanelCatalogue.php` (axiomasoft/corex-panels) — category `Contracts` at depth 0
- `packages/recurrence/src/Contracts/RecurrenceExpander.php` (axiomasoft/corex-recurrence) — category `Contracts` at depth 0
- `packages/search/src/Contracts/IndexDriver.php` (axiomasoft/corex-search) — category `Contracts` at depth 0
- `packages/search-intl/src/Contracts/SavedViewLabelReader.php` (axiomasoft/corex-search-intl) — category `Contracts` at depth 0
- `packages/search-meilisearch/src/Exceptions/InvalidMeilisearchConfiguration.php` (axiomasoft/corex-search-meilisearch) — category `Exceptions` at depth 0
- `packages/tasks-workflow/src/Contracts/TaskRecurrenceCommand.php` (axiomasoft/corex-tasks-workflow) — category `Contracts` at depth 0
- `packages/tenancy/src/Contracts/CentralApiClient.php` (axiomasoft/corex-tenancy) — category `Contracts` at depth 0

## L3 — примеры (до 10, по одному кругу от пакета)

- `packages/core/src/Audit/AuditObserver.php` (axiomasoft/corex) — role other outside Internal/Laravel
- `packages/access/src/Access/AutMatrixGrantSource.php` (axiomasoft/corex-access) — role other outside Internal/Laravel
- `packages/custom-fields/src/Data/AttributeDefinition.php` (axiomasoft/corex-custom-fields) — role other outside Internal/Laravel [in public category dir]
- `packages/intl/src/IntlConfigSection.php` (axiomasoft/corex-intl) — role other outside Internal/Laravel
- `packages/media/src/Commands/SweepMediaOperations.php` (axiomasoft/corex-media) — role command outside Internal/Laravel
- `packages/notifications/src/Delivery/Commands/SweepNotificationDeliveries.php` (axiomasoft/corex-notifications) — role command outside Internal/Laravel
- `packages/operations/src/Bulk/Commands/WorkBulkOperations.php` (axiomasoft/corex-operations) — role command outside Internal/Laravel
- `packages/panels/src/Data/PanelAccess.php` (axiomasoft/corex-panels) — role other outside Internal/Laravel [in public category dir]
- `packages/recurrence/src/Data/RecurrenceSet.php` (axiomasoft/corex-recurrence) — role other outside Internal/Laravel [in public category dir]
- `packages/search/src/SearchManager.php` (axiomasoft/corex-search) — role other outside Internal/Laravel

## L4 — примеры (до 10, по одному кругу от пакета)

- нет

## L5 — примеры (до 10, по одному кругу от пакета)

- `packages/core/src/Context/ImpersonationGuard.php` (axiomasoft/corex) — -> CoreX\Audit\Internal\AuditLog
- `packages/custom-fields/src/AttributesServiceProvider.php` (axiomasoft/corex-custom-fields) — -> CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator
- `packages/intl/src/IntlServiceProvider.php` (axiomasoft/corex-intl) — -> CoreX\Intl\Currency\Internal\BrickCurrencyConverter
- `packages/media/src/Commands/SweepMediaOperations.php` (axiomasoft/corex-media) — -> CoreX\Media\Internal\MediaOperationStore
- `packages/notifications/src/Delivery/Internal/ChannelDrivers.php` (axiomasoft/corex-notifications) — -> CoreX\Notifications\Inbox\Internal\NotificationOperationConflict
- `packages/operations/src/ApiServiceProvider.php` (axiomasoft/corex-operations) — -> CoreX\Operations\Authorization\Internal\DeclaredFieldAuthorizer
- `packages/panels/src/Internal/SavedViewsRegistrar.php` (axiomasoft/corex-panels) — -> CoreX\Panels\SavedViews\Internal\DenyViewAccessPolicy
- `packages/recurrence/src/Data/RecurrenceSet.php` (axiomasoft/corex-recurrence) — -> CoreX\Recurrence\Internal\CanonicalHasher
- `packages/search/src/SearchManager.php` (axiomasoft/corex-search) — -> CoreX\Search\Internal\Postgres\PostgresSearchProvider
- `packages/search-intl/src/SearchIntlServiceProvider.php` (axiomasoft/corex-search-intl) — -> CoreX\SearchIntl\Internal\LocalizedFieldProjector
