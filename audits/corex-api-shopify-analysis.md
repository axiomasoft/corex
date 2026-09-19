# API крупных платформ: Shopify

Ниже — самостоятельный раздел, который можно наложить поверх текущего аудита CoreX. В этой итерации первичными источниками удалось полноценно перепроверить Shopify; рекомендации по Stripe, GitHub, Salesforce, Slack, Atlassian, Microsoft Graph, Google и AWS приведены как программа следующего сравнительного прохода, а не как неподтверждённые выводы.

## Основной вывод

Главный урок Shopify для CoreX — API следует проектировать как **самостоятельный продукт со своим жизненным циклом**, а не как автоматическую HTTP-проекцию Eloquent-моделей.

CoreX нужен единый transport-neutral слой операций, поверх которого независимо строятся:

- REST API;
- GraphQL API;
- OpenAPI;
- MCP/AI tools;
- CLI-команды;
- внутренние application calls;
- asynchronous bulk operations;
- webhooks и integration events.

Поэтому текущее имя пакета `api`, вероятно, слишком широкое и одновременно недостаточно точное. Если центральная abstraction пакета — `Operation`, наиболее последовательное название:

```text
corex/operations
CoreX\Operations
```

А REST, GraphQL, OpenAPI и tools должны стать адаптерами этого слоя.

# Что делает Shopify

## Versioned endpoints

Shopify включает версию API непосредственно в endpoint:

```text
/admin/api/2026-04/graphql.json
```

Все GraphQL Admin API-запросы выполняются через `POST`, а токен передаётся в `X-Shopify-Access-Token`. [shopify](https://shopify.dev/docs/api/admin-graphql/2026-04)

Публичные версии имеют date-based identifiers вида `YYYY-MM`; отдельно существуют supported, release candidate, unstable и end-of-life версии. Shopify также предоставляет API-запрос `publicApiVersions`, позволяющий программно получить версии и их статус. [shopify](https://shopify.dev/docs/api/admin-graphql/2026-10/queries/publicApiVersions)

На сентябрь 2026 года последней стабильной версией является `2026-07`, заявленный период production-поддержки которой продолжается как минимум до 1 июля 2027 года. [shopify](https://shopify.dev/release-notes/2026-07)

### Урок для CoreX

CoreX не должен версионировать API только через версию Composer-пакета:

```text
corex/operations 1.4.0
```

Версия PHP-пакета и версия внешнего API — разные контракты.

Предлагаемая модель:

```php
final readonly class ApiVersion
{
    public function __construct(
        public string $value,
        public ApiVersionStatus $status,
        public DateTimeImmutable $introducedAt,
        public ?DateTimeImmutable $supportedUntil,
    ) {}
}
```

```php
enum ApiVersionStatus: string
{
    case Stable = 'stable';
    case ReleaseCandidate = 'release_candidate';
    case Unstable = 'unstable';
    case Deprecated = 'deprecated';
    case EndOfLife = 'end_of_life';
}
```

Маршруты:

```text
/api/2026-10/graphql
/api/2026-10/rest/contacts
/api/unstable/graphql
```

Для self-hosted CoreX необязательно выпускать API каждые три месяца. Однако схема `YYYY-MM` удобна тем, что:

- версия имеет понятный возраст;
- documentation и schema snapshots естественно привязываются к дате;
- несколько совместимых версий могут сосуществовать;
- версия API не привязана к версии Laravel application;
- deprecation schedule становится измеримым.

Альтернатива для раннего CoreX:

```text
2027-01
2027-07
2028-01
```

То есть два release train в год.

## Версия как профиль

Версия не должна быть набором условных операторов в controllers:

```php
if ($request->version() === '2027-01') {
    // ...
}
```

Нужен immutable profile:

```php
final readonly class ApiProfile
{
    /**
     * @param array<string, OperationDefinition> $operations
     */
    public function __construct(
        public ApiVersion $version,
        public array $operations,
        public DeprecationCatalogue $deprecations,
        public CapabilityCatalogue $capabilities,
    ) {}
}
```

Compiler должен собрать профиль при bootstrap:

```php
interface ApiProfileCompiler
{
    public function compile(ApiVersion $version): ApiProfile;
}
```

Runtime dispatcher получает уже готовый профиль:

```php
final class OperationDispatcher
{
    public function dispatch(
        ApiProfile $profile,
        OperationRequest $request,
        InvocationContext $context,
    ): OperationResponse {}
}
```

Это позволит избежать version logic, размазанной по:

- controllers;
- resolvers;
- resources;
- policies;
- OpenAPI generators;
- MCP tool providers.

# Эволюция без обрыва

Shopify показывает incremental migration approach: при переходе Collection API к новой модели старые deprecated members остаются доступными, чтобы клиенты могли перейти постепенно. [shopify](https://shopify.dev/changelog/new-collection-model-and-apis-now-available)

Для CoreX это важнее, чем формальное следование SemVer. API-клиенты обновляются независимо от сервера и не могут синхронно мигрировать при каждом release.

## Правило additive-first

Внутри поддерживаемой API-версии разрешены:

- новые optional fields;
- новые operations;
- новые enum values, если клиент обязан принимать unknown value;
- новые error details;
- новые webhook topics;
- новые capabilities.

Не должны бесконтрольно появляться:

- новые required request fields;
- изменение смысла существующего поля;
- изменение scalar type;
- удаление enum value;
- изменение pagination ordering;
- замена `null` на error;
- изменение authorization semantics;
- изменение idempotency semantics;
- изменение default filters.

## Deprecation descriptor

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
final readonly class ApiDeprecated
{
    public function __construct(
        public string $since,
        public string $replacement,
        public string $removalVersion,
        public string $reason,
    ) {}
}
```

Пример:

```php
final readonly class ContactRepresentation
{
    public function __construct(
        public string $id,

        #[ApiDeprecated(
            since: '2027-07',
            replacement: 'displayName',
            removalVersion: '2028-07',
            reason: 'The old field cannot represent localized names.',
        )]
        public ?string $name,

        public string $displayName,
    ) {}
}
```

Compiler использует metadata одновременно для:

- OpenAPI;
- GraphQL deprecation directives;
- MCP tool descriptions;
- documentation;
- compatibility checks;
- response headers;
- migration guides.

# Cost-based throttling

Shopify ограничивает GraphQL Admin API не простым числом HTTP-запросов, а стоимостью выбранных полей. Bucket определяется для пары «приложение + магазин», использует leaky-bucket replenishment и возвращает requested cost, actual cost, доступный остаток и restore rate. [shopify](https://shopify.dev/docs/apps/build/apis/graphql-admin/rate-limits)

Запрос сначала проверяется по предполагаемой стоимости. После выполнения Shopify возвращает разницу между requested и actual cost, если фактический результат оказался дешевле; один обычный GraphQL-запрос не может превышать 1 000 cost points. [shopify](https://shopify.dev/docs/apps/build/apis/graphql-admin/rate-limits)

Shopify также предоставляет debug header для подробной разбивки стоимости полей. [shopify](https://shopify.dev/docs/apps/build/apis/graphql-admin/rate-limits)

## Почему request-per-minute недостаточно

Для CoreX два запроса не обязательно эквивалентны:

```graphql
query {
  contact(id: "...") {
    id
    name
  }
}
```

и:

```graphql
query {
  contacts(first: 500) {
    attributes
    activities
    files
    workflows
    permissions
  }
}
```

Одинаковое списание одного request token создаёт возможность перегрузки через:

- deep nested queries;
- большие connections;
- expensive computed fields;
- permission-sensitive filters;
- dynamic attributes;
- full-text search;
- cross-module aggregation;
- files и media metadata.

## CoreX Query Budget

```php
final readonly class QueryCost
{
    public function __construct(
        public int $requested,
        public int $actual,
    ) {}
}
```

```php
interface OperationCostEstimator
{
    public function estimate(
        OperationDefinition $operation,
        OperationInput $input,
        SelectionSet $selection,
    ): EstimatedOperationCost;
}
```

```php
interface OperationBudget
{
    public function reserve(
        ClientIdentity $client,
        ScopeRef $scope,
        EstimatedOperationCost $cost,
    ): BudgetReservation;
}
```

```php
interface BudgetReservation
{
    public function settle(ActualOperationCost $cost): void;

    public function reject(): void;
}
```

Throttle context:

```php
final readonly class ThrottleStatus
{
    public function __construct(
        public int $capacity,
        public int $available,
        public int $restoreRate,
        public DateTimeImmutable $observedAt,
    ) {}
}
```

Response metadata:

```json
{
  "extensions": {
    "cost": {
      "requested": 120,
      "actual": 43,
      "throttle": {
        "capacity": 1000,
        "available": 957,
        "restoreRate": 50
      }
    }
  }
}
```

## Dimension бюджета

Shopify изолирует лимиты по паре app/store: нагрузка одного приложения или магазина не должна потреблять весь бюджет остальных. [shopify](https://shopify.dev/docs/apps/build/apis/graphql-admin/rate-limits)

Для CoreX возможная dimension:

```text
tenant
workspace
client application
actor
operation category
API version
```

Однако создавать bucket для каждой возможной комбинации опасно. Рекомендуемая иерархия:

```text
Global safety limit
  -> Tenant limit
      -> Client application limit
          -> Actor abuse limit
```

В storage key не следует включать operation ID для каждого запроса, иначе cardinality станет неуправляемой.

## Operation weights

```php
#[ApiOperation(
    name: 'crm.contact.get',
    baseCost: 1,
)]
final class GetContactOperation {}
```

```php
#[ApiOperation(
    name: 'crm.contacts.search',
    baseCost: 5,
    costPolicy: SearchOperationCost::class,
)]
final class SearchContactsOperation {}
```

Cost policy должна учитывать:

- page size;
- requested fields;
- nested relations;
- expensive computed fields;
- attribute filters;
- search highlights;
- export size;
- synchronous side effects.

Cost не должен выполнять запрос к базе только для оценки стоимости.

# Bulk operations

Shopify рекомендует bulk operations для больших объёмов данных; такие операции отделены от ограничений обычного запроса и предназначены для обработки больших наборов. [shopify](https://shopify.dev/docs/apps/build/apis/graphql-admin/rate-limits)

Это принципиально для будущей CRM/CMS на CoreX.

Плохой API:

```text
GET /contacts?limit=100000
POST /contacts/import
```

Хорошая модель:

```text
POST /bulk-operations
GET /bulk-operations/{id}
POST /bulk-operations/{id}/cancel
GET /bulk-operations/{id}/result
GET /bulk-operations/{id}/failures
```

## Универсальный контракт

```php
final readonly class BulkOperationDefinition
{
    public function __construct(
        public OperationName $operation,
        public OperationInput $input,
        public OutputFormat $outputFormat,
        public FailurePolicy $failurePolicy,
    ) {}
}
```

```php
enum BulkOperationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
```

```php
interface BulkOperationStore
{
    public function create(
        BulkOperationDefinition $definition,
        InvocationContext $context,
    ): BulkOperation;

    public function find(BulkOperationId $id): ?BulkOperation;

    public function claimNext(WorkerIdentity $worker): ?ClaimedBulkOperation;
}
```

## Связь с Transfer

Не следует дублировать execution engine в `operations` и `transfer`.

Предлагаемая граница:

```text
Operations
  - внешний контракт запуска;
  - авторизация;
  - API version;
  - idempotency;
  - job status;
  - response representation.

Transfer
  - чтение batch;
  - mapping;
  - validation;
  - checkpoint;
  - запись;
  - per-record failures;
  - resume.
```

Например:

```php
final class StartContactExportOperation
{
    public function __construct(
        private TransferCoordinator $transfers,
    ) {}

    public function execute(
        StartContactExportInput $input,
        InvocationContext $context,
    ): BulkOperationAccepted {
        // Build a transfer definition and schedule execution.
    }
}
```

# Каноническая модель операций

## Не строить API из controllers

Controller должен быть transport adapter:

```php
final class OperationController
{
    public function __construct(
        private OperationDispatcher $operations,
        private HttpOperationRequestMapper $requests,
        private HttpOperationResponseMapper $responses,
    ) {}

    public function __invoke(Request $request): Response
    {
        $operationRequest = $this->requests->map($request);

        $result = $this->operations->dispatch(
            profile: $request->attributes->get(ApiProfile::class),
            request: $operationRequest,
            context: $request->attributes->get(InvocationContext::class),
        );

        return $this->responses->map($result);
    }
}
```

Controller не должен:

- содержать domain validation;
- читать Eloquent models;
- вычислять tenant;
- вручную проверять permissions;
- создавать OpenAPI schemas;
- определять idempotency;
- ловить все исключения;
- определять API version compatibility.

## Operation definition

```php
interface Operation
{
    public static function definition(): OperationDefinition;

    public function execute(
        OperationInput $input,
        InvocationContext $context,
    ): OperationResult;
}
```

```php
final readonly class OperationDefinition
{
    /**
     * @param list<string> $requiredScopes
     */
    public function __construct(
        public OperationName $name,
        public OperationVersion $version,
        public string $inputType,
        public string $resultType,
        public OperationMode $mode,
        public array $requiredScopes,
        public IdempotencyPolicy $idempotency,
        public CostPolicy $cost,
        public DeprecationStatus $deprecation,
    ) {}
}
```

```php
enum OperationMode: string
{
    case Query = 'query';
    case Command = 'command';
    case AsyncCommand = 'async_command';
    case BulkQuery = 'bulk_query';
    case BulkCommand = 'bulk_command';
}
```

## Transport adapters

```text
CoreX\Operations\Http
CoreX\Operations\GraphQL
CoreX\Operations\OpenApi
CoreX\Operations\Tools
CoreX\Operations\Console
```

Каждый transport отвечает только за:

- decoding;
- transport authentication extraction;
- input mapping;
- content negotiation;
- output mapping;
- transport-specific errors.

Operation layer отвечает за:

- semantic authorization;
- idempotency;
- validation orchestration;
- business dispatch;
- cost;
- audit;
- observability.

# Query и mutation

Shopify GraphQL разделяет query root и mutations, что соответствует разнице между чтением и изменением состояния. [shopify](https://shopify.dev/docs/api/admin-graphql/2026-04)

CoreX должен формально различать operation modes даже для REST и MCP tools.

## Query

Query:

- не изменяет source of truth;
- может использовать read model;
- допускает caching;
- поддерживает field selection;
- может вернуть stale-but-declared result;
- имеет cursor pagination;
- не требует idempotency key.

## Command

Command:

- изменяет состояние;
- проходит semantic authorization;
- поддерживает idempotency;
- возвращает operation result, а не произвольную model;
- публикует события через transactional boundary;
- поддерживает optimistic concurrency;
- записывает audit.

## Async command

Async command возвращает не результат бизнес-операции, а acceptance:

```php
final readonly class OperationAccepted
{
    public function __construct(
        public OperationRunId $runId,
        public OperationStatusUrl $statusUrl,
        public DateTimeImmutable $acceptedAt,
    ) {}
}
```

Не следует возвращать `200 OK` с полем:

```json
{"status": "queued"}
```

Лучше использовать transport semantics `202 Accepted`.

# Error model

Transport error, application rejection и domain validation — разные категории.

## Envelope

```json
{
  "errors": [
    {
      "code": "ATTRIBUTE_VALUE_INVALID",
      "message": "One or more attribute values are invalid.",
      "path": ["input", "attributes", "employee_count"],
      "category": "validation",
      "retryable": false,
      "details": {
        "constraint": "minimum",
        "minimum": 0
      }
    }
  ],
  "meta": {
    "requestId": "req_...",
    "correlationId": "corr_...",
    "apiVersion": "2027-01"
  }
}
```

## Категории

```text
authentication
authorization
validation
conflict
not_found
rate_limit
unsupported
unavailable
internal
```

## Стабильность

Стабильным контрактом должны быть:

- `code`;
- `category`;
- `path`;
- `retryable`;
- documented `details`.

`message` предназначен человеку и не должен парситься клиентом.

## GraphQL

GraphQL transport может отображать:

- transport/schema errors — в top-level `errors`;
- ожидаемые business failures — в typed operation payload;
- correlation metadata — в `extensions`.

Не следует превращать любую domain validation в необработанное GraphQL exception.

# Idempotency

Shopify-source этой итерации не подтверждает конкретный универсальный idempotency contract, поэтому приведённое ниже — рекомендация для CoreX, а не копирование Shopify.

Для CRM/CMS idempotency обязательна в:

- создании сущностей;
- transfer/import batches;
- provisioning;
- external webhook consumption;
- payment-adjacent operations;
- workflow signals;
- async commands.

```php
final readonly class IdempotencyKey
{
    public function __construct(public string $value) {}
}
```

```php
interface IdempotencyStore
{
    public function begin(
        IdempotencyScope $scope,
        IdempotencyKey $key,
        RequestFingerprint $fingerprint,
    ): IdempotencyAttempt;
}
```

Состояния:

```text
started
completed
failed_retryable
failed_final
expired
```

Повторный key с другим fingerprint должен возвращать conflict, а не старый результат другого запроса.

# Pagination

Для всех list operations следует выбрать cursor-based pagination как основной public contract.

```php
final readonly class PageRequest
{
    public function __construct(
        public int $first,
        public ?Cursor $after,
    ) {}
}
```

```php
final readonly class PageInfo
{
    public function __construct(
        public bool $hasNextPage,
        public ?Cursor $endCursor,
    ) {}
}
```

Cursor должен кодировать:

- ordering version;
- last sort values;
- unique tie-breaker;
- query fingerprint или selection identity;
- optional expiry;
- signature.

Cursor не должен раскрывать raw SQL или небезопасно сериализованный PHP object.

# Resource identifiers

Внешний API не должен раскрывать auto-increment IDs как единственный стабильный identifier.

```php
final readonly class ResourceId
{
    public function __construct(
        public ResourceType $type,
        public string $value,
    ) {}
}
```

Пример:

```text
crm_contact_01K5...
cms_page_01K5...
transfer_run_01K5...
```

Преимущества:

- меньше случайных cross-resource lookups;
- проще логирование;
- легче отличать ID разных сущностей;
- внутреннюю storage model можно менять;
- ID безопаснее переносить между transports.

Prefix не является authorization boundary, но помогает обнаруживать ошибки клиента.

# Scopes и permissions

OAuth scope или API scope не заменяет domain authorization.

Нужно разделить:

```text
Client scope:
crm.contacts.read

Actor permission:
contact.view

Record policy:
actor can view this contact

Field policy:
actor can view contact.salary

Tenant boundary:
contact belongs to the resolved tenant
```

Operation definition объявляет coarse scopes:

```php
#[RequiresScope('crm.contacts.write')]
final class UpdateContactOperation {}
```

Но application authorizer отдельно проверяет:

- tenant;
- workspace;
- record;
- fields;
- impersonation policy;
- feature entitlement.

# Webhooks

Webhook API должен использовать operation/integration contracts, но не Laravel domain events напрямую.

```php
final readonly class WebhookSubscription
{
    public function __construct(
        public WebhookSubscriptionId $id,
        public WebhookTopic $topic,
        public ApiVersion $version,
        public Uri $endpoint,
        public WebhookSecretRef $secret,
        public WebhookFilter $filter,
    ) {}
}
```

Обязательные свойства:

- подписка фиксирует API version;
- delivery имеет stable ID;
- payload подписан;
- consumer должен быть idempotent;
- retries имеют предел;
- delivery history доступна;
- payload schema versioned;
- secrets ротируются;
- PII-classification известна;
- replay выполняется явно;
- internal event не публикуется наружу автоматически.

# API capabilities

По аналогии с программно доступным списком публичных версий Shopify CoreX должен позволять клиенту узнать supported surface. [shopify](https://shopify.dev/docs/api/admin-graphql/2026-10/queries/publicApiVersions)

Предлагаемые endpoints:

```text
GET /api
GET /api/versions
GET /api/2027-01/capabilities
GET /api/2027-01/openapi.json
POST /api/2027-01/graphql
```

Capability response:

```json
{
  "apiVersion": "2027-01",
  "capabilities": {
    "crm.contacts": {
      "version": "1",
      "operations": [
        "crm.contacts.get",
        "crm.contacts.search",
        "crm.contacts.create"
      ]
    },
    "search": {
      "available": true,
      "features": [
        "facets",
        "highlights"
      ]
    }
  }
}
```

Не нужно раскрывать конкретный backend:

```json
{"driver": "meilisearch"}
```

если это не требуется клиенту. Публичный API сообщает capability, а не инфраструктуру.

# Предлагаемая структура пакета

```text
packages/operations/
  src/
    Contract/
      Operation.php
      OperationRegistry.php
      OperationAuthorizer.php
      OperationCostEstimator.php

    Definition/
      OperationDefinition.php
      OperationName.php
      OperationMode.php
      OperationVersion.php

    Invocation/
      OperationDispatcher.php
      InvocationContext.php
      InvocationMetadata.php
      OperationRequest.php
      OperationResult.php

    Versioning/
      ApiVersion.php
      ApiProfile.php
      ApiProfileCompiler.php
      DeprecationCatalogue.php

    Authorization/
      Scope.php
      ScopeSet.php
      OperationAccessPolicy.php

    Validation/
      OperationInputValidator.php
      Violation.php
      ViolationList.php

    Idempotency/
      IdempotencyKey.php
      IdempotencyStore.php
      RequestFingerprint.php

    Throttling/
      OperationBudget.php
      OperationCost.php
      ThrottleStatus.php

    Bulk/
      BulkOperation.php
      BulkOperationStore.php
      BulkOperationWorker.php

    Error/
      OperationError.php
      OperationErrorCode.php
      ErrorCatalogue.php

    Observability/
      OperationObserver.php
      OperationTelemetry.php

    Laravel/
      OperationsServiceProvider.php

    Http/
      OperationController.php
      HttpOperationRequestMapper.php
      HttpOperationResponseMapper.php

    GraphQL/
      GraphQlSchemaCompiler.php
      GraphQlOperationAdapter.php

    OpenApi/
      OpenApiDocumentGenerator.php

    Tools/
      ToolDefinitionGenerator.php
      ToolInvocationAdapter.php
```

Если GraphQL и REST optional и имеют тяжёлые dependencies, их можно впоследствии выделить:

```text
corex/operations
corex/operations-http
corex/operations-graphql
corex/operations-tools
```

До появления реальной dependency pressure создавать эти packages преждевременно.

# Проверки API

## Schema snapshots

Для каждой поддерживаемой версии хранить:

```text
api/
  2027-01/
    openapi.json
    graphql-schema.graphql
    operations.json
    errors.json
    webhooks.json
```

CI должен отклонять:

- удаление operation;
- удаление response field;
- добавление required input;
- изменение type;
- изменение nullability;
- удаление enum case;
- изменение stable error code;
- удаление webhook topic;
- изменение pagination contract.

## Consumer fixtures

Создать внешние consumers:

```text
fixtures/api/rest-client
fixtures/api/graphql-client
fixtures/api/mcp-client
fixtures/api/webhook-consumer
fixtures/api/bulk-export-client
```

Fixtures должны использовать только опубликованные schemas и public SDK.

## Contract tests

```php
it('replays an idempotent command without duplicate effects');
it('rejects the same idempotency key with another fingerprint');
it('preserves ordering while traversing cursor pages');
it('rejects a query exceeding its cost budget');
it('refunds unused requested cost');
it('does not expose inaccessible fields');
it('keeps deprecated fields available until removal version');
it('serializes errors using stable codes');
it('isolates tenant budgets');
it('resumes interrupted bulk operations');
it('validates old API snapshots against the current implementation');
```

# Что брать у Shopify

| Практика | Для CoreX |
|---|---|
| Date-based API versions | Ввести независимую версию внешнего API |
| Stable/RC/unstable status | Использовать для generated profiles и documentation |
| Version в endpoint | Сделать версию явной, не скрывать в Composer release |
| Incremental deprecation | Сохранять deprecated members на migration window |
| GraphQL query cost | Ввести operation/query cost budgets |
| Bucket per app/store | Изолировать tenant и client application |
| Requested/actual cost | Резервировать budget и возвращать неиспользованную часть |
| Debug cost breakdown | Добавить development-only cost diagnostics |
| Bulk operations | Не выполнять большие exports/imports синхронно |
| Programmatic versions query | Публиковать versions/capabilities endpoints |
| GraphQL selection control | Не возвращать фиксированные гигантские representations |

# Что не копировать буквально

- Не делать GraphQL единственным canonical API.
- Не вводить quarterly version cadence, если команда не сможет поддерживать несколько версий.
- Не копировать Shopify resource naming без CoreX ubiquitous language.
- Не привязывать operation layer к commerce concepts.
- Не делать every field cost manually configurable.
- Не смешивать SaaS rate limits и self-hosted authorization.
- Не заставлять локальную установку использовать remote-style throttling.
- Не держать десять старых версий API без реального потребителя.
- Не генерировать domain model из GraphQL schema.
- Не возвращать Eloquent models непосредственно из resolvers.

# Следующий сравнительный проход

Для полноценного анализа «Shopify и других крупных игроков» следующий отдельный отчёт должен проверить первичные документации по единой матрице.

| Платформа | Что исследовать |
|---|---|
| Stripe | Idempotency, request IDs, expandable fields, errors, API version pinning, webhook delivery |
| GitHub | REST/GraphQL coexistence, conditional requests, pagination, previews, webhook events, fine-grained permissions |
| Salesforce | Composite API, Bulk API, governor limits, field-level security, describe/metadata API |
| Microsoft Graph | OData query model, delta queries, batching, throttling, long-running operations |
| Google APIs | Resource-oriented design, long-running operations, field masks, standard methods and errors |
| AWS | Request tokens, retries, pagination tokens, error metadata, SDK generation |
| Slack | Cursor pagination, granular scopes, retry headers, event envelopes |
| Atlassian | Cloud API versioning, asynchronous tasks, app scopes, extensibility |
| Twilio | Stable resource IDs, status callbacks, subaccounts, request validation |
| HubSpot | CRM schemas, associations, batch endpoints, custom properties |
| Notion | Block/object model, pagination, API version headers, capability-based integrations |
| Contentful | Content models, environment aliases, migrations, locale handling |
| Shopify | Versions, cost throttling, bulk jobs, GraphQL Admin API |

Итоговая сравнительная таблица должна отвечать не на вопрос «как у них называется endpoint», а на вопросы:

- Что является canonical API abstraction?
- Как версия привязана к клиенту?
- Как выполняются additive changes?
- Как устроены idempotency и retries?
- Как моделируются bulk/long-running operations?
- Как API сообщает rate-limit state?
- Как обеспечивается tenant isolation?
- Как выглядят stable error codes?
- Как организованы webhooks?
- Как клиент обнаруживает capabilities?
- Как платформа проверяет backwards compatibility?
- Какие практики применимы к self-hosted CoreX, а какие имеют смысл только для SaaS?

Этот раздел можно сохранить отдельным дополнением к аудиту под именем `corex-api-shopify-analysis.md`; скачиваемый файл в текущем проходе не создавался.