Сейчас не могу приложить физический `.md`-файл, поэтому ниже даю полностью готовое содержимое расширенного отчёта в GitHub-Flavored Markdown. Оно сохраняет все рекомендации предыдущего аудита, детализирует архитектуру, миграции, структуру каталогов, контракты, примеры кода, тесты и изменения `plans/`.

# CoreX: детальный архитектурный аудит и план развития

> **Репозиторий:** [axiomasoft/corex](https://github.com/axiomasoft/corex)  
> **Назначение:** универсальное Laravel-ядро для CRM, CMS и других долгоживущих продуктов  
> **Статус:** углублённый архитектурный аудит текущего кода и незавершённого плана  
> **Целевая аудитория:** maintainers CoreX, разработчики CRM/CMS-модулей, архитекторы интеграций  
> **Главный приоритет:** стабилизация границ пакетов и публичного API до дальнейшего расширения функциональности

***

## Краткий вердикт

CoreX уже заметно сильнее обычного набора Laravel-пакетов: в проекте осознанно прорабатываются tenant isolation, fencing tokens, optimistic concurrency, идемпотентность, schema snapshots, policy-aware search, восстановление после сбоев и конкурентные сценарии.

Однако главная угроза проекту теперь находится не в недостатке технической строгости, а в противоположной крайности:

- слишком широком `corex/core`;
- растущем количестве Composer-пакетов;
- преждевременном закреплении сложных distributed protocols;
- смешении публичного API, persistence protocol и release evidence;
- появлении integration/glue-пакетов;
- неявном fail-open поведении;
- использовании абстракций до подтверждения вариативности реальными CRM- и CMS-потребителями;
- постепенном создании собственного framework поверх Laravel.

Корневой `composer.json` уже соединяет `core`, CRM, tasks, modules, panels, media, notifications, wakeup, tenancy, API, workflow-интеграцию, intl и intl-search. Это показывает, что монорепозиторий постепенно становится не только ядром, но и development distribution, из-за чего важно формально отделить platform foundation от capabilities и продуктов .

Ключевое решение:

> CoreX должен быть не «ядром, знающим обо всём», а минимальной платформой с подключаемыми capabilities и product modules.

***

## Сохранённые рекомендации

Ни одна рекомендация предыдущего аудита не удаляется. В документе подробно рассматриваются следующие решения:

| Область | Решение |
|---|---|
| Архитектура | Разделить Foundation, Platform, Capabilities и Products |
| `corex/core` | Постепенно уменьшить до действительно горизонтальных примитивов |
| Attributes | Убрать неявные storage assumptions и nullable validation |
| Attributes | Явно моделировать missing, null, set и remove |
| Attributes | Разделить sidecar JSONB, EAV и возможный inline JSON |
| Transfer | Переименовать `import-export` в `transfer` |
| Transfer | Разбить большой runner на claim/read/apply/checkpoint |
| Transfer | Не выполнять external side effects внутри retryable DB transaction |
| Search | Заменить конкурирующие container bindings на `SearchManager` |
| Meilisearch | Включить как optional driver в `corex/search` |
| Intl Search | Удалить самостоятельный `intl-search` package |
| Пакеты | Не создавать Composer package для каждой комбинации capabilities |
| Публичный API | Провести полный API census до stable release |
| Каталоги | Ограничить `Contracts`, `Support`, `Internal`, `Services`, `Managers` |
| Laravel | Сохранить стандартные service provider, container, queue и transaction semantics |
| Symfony | Использовать component-like package boundaries и constructor injection |
| Spatie | Перенять configurable implementations и contract-driven replacement |
| Bitrix | Сохранить продуктовые идеи, не переносить global state и magic integration |
| `plans/` | Разделить ADR, invariants, delivery plan и generated evidence |
| Тестирование | Добавить contract suites, architecture tests и package matrix |
| План релизов | Выполнять работу вертикальными срезами CRM и CMS |

***

# Целевая архитектура

## Четыре уровня

Предлагаемая модель:

```text
┌─────────────────────────────────────────────────────────┐
│ Products                                                │
│ CRM, CMS, Tasks, Education, Sales, Documents            │
├─────────────────────────────────────────────────────────┤
│ Capabilities                                            │
│ Auth, Tenancy, Attributes, Search, Transfer, Media, Intl│
├─────────────────────────────────────────────────────────┤
│ Platform                                                │
│ Modules, Entity Registry, Capability Registry           │
├─────────────────────────────────────────────────────────┤
│ Foundation                                              │
│ IDs, Context, Clock, Operation keys, primitive contracts│
└─────────────────────────────────────────────────────────┘
```

Зависимости направлены только вниз:

```text
Products
   ↓
Capabilities
   ↓
Platform
   ↓
Foundation
```

Допускаются зависимости между capabilities только в исключительных случаях. Предпочтительный способ интеграции двух capabilities — composition root в product/distribution package.

## Foundation

В Foundation должны находиться только классы, которые:

- используются несколькими независимыми пакетами;
- не содержат продуктовой политики;
- не требуют CRM, CMS, Departments, Views или Meilisearch;
- имеют стабильную семантику;
- не являются просто удобным местом для общего кода.

Возможный состав:

```text
packages/core/
├── composer.json
├── src/
│   ├── Context/
│   │   ├── ExecutionContext.php
│   │   └── TenantContext.php
│   ├── Identity/
│   │   ├── EntityHandle.php
│   │   ├── FieldHandle.php
│   │   ├── RecordId.php
│   │   └── RecordRef.php
│   ├── Operation/
│   │   ├── OperationKey.php
│   │   ├── PayloadHash.php
│   │   └── WriteToken.php
│   ├── Time/
│   │   └── Clock.php
│   ├── Exceptions/
│   │   ├── ConfigurationException.php
│   │   └── ConcurrencyException.php
│   └── CoreServiceProvider.php
└── tests/
```

При этом даже `TenantContext` должен быть достаточно нейтральным: Foundation может переносить идентификатор scope, но не должен создавать базы tenant, управлять impersonation или определять lifecycle аккаунта.

## Текущая перегрузка Core

Сейчас `packages/core/src` содержит Audit, Departments, Features, PubSub, Settings, Storage, Tenancy и Views. `CoreServiceProvider` регистрирует repositories, audit retention, tenant resolution, impersonation, modules, feature flags, departments, PubSub, saved views и tenant storage .

В `Contracts` одновременно находятся interfaces и data objects: например, `AuditEntry`, `FeatureFlagDefinition` и `SettingsScope` расположены рядом с `AuditLogger`, `FeatureFlags` и `SettingsRepository` .

Это делает `corex/core` semantic dependency magnet: capability зависит от Core ради `DataScope`, но транзитивно получает представление о Departments, Views, PubSub и Settings.

## Предлагаемое извлечение

| Текущая область | Новое место | Обоснование |
|---|---|---|
| `Audit` | `corex/audit` или platform capability | Самостоятельное хранение, retention и команды |
| `Departments` | `corex/organization` либо CRM/product | Departments не универсальны для CMS |
| `Features` | `corex/features` или `modules` | Feature flags имеют отдельную политику и storage |
| `PubSub` | `corex/messaging` | Это инфраструктурная capability |
| `Settings` | `corex/settings` | Самостоятельный repository и schema lifecycle |
| `Storage` | `corex/storage` или tenancy | Tenant storage не нужен каждому consumer |
| `Tenancy` contracts | `corex/tenancy` | Контракт должен принадлежать capability |
| `Views` | `corex/panels` или `corex/views` | Saved views — UI/query capability |
| `ModuleRegistrar` | `corex/modules` | Владельцем interface должен быть modules package |
| `DepartmentRepository` | Organization/CRM | Это domain contract |
| `WorkerRuntime` | Messaging/Runtime | Не является фундаментальным типом |

Не нужно перемещать всё одним большим commit. Безопасная последовательность:

1. Создать новый package.
2. Перенести реализацию.
3. Оставить временный compatibility alias.
4. Переключить внутренние зависимости.
5. Добавить deprecation.
6. Удалить alias до `1.0.0`.

Если публичных внешних consumers ещё нет, лучше выполнить hard move сразу, не создавая долгосрочный compatibility burden.

***

# Границы пакетов

## Когда нужен пакет

Новый Composer package оправдан, если выполняются хотя бы четыре условия:

- Возможность можно установить независимо.
- У неё есть самостоятельный публичный API.
- У неё есть собственный lifecycle версий.
- Её использует больше одного продукта.
- У неё есть собственный persistence или external integration boundary.
- Пакет можно описать одним предложением без союза «и».
- Пакет можно протестировать независимо.
- Отключение пакета не ломает загрузку foundation.
- Пакет не является только wiring двух других пакетов.

Symfony рекомендует не использовать bundles только для группировки application logic: самостоятельная единица нужна тогда, когда функциональность действительно пригодна для повторного использования. [symfony](https://symfony.com/doc/current/best_practices.html)

## Когда достаточно namespace

Отдельный namespace предпочтительнее package, если:

- код всегда выпускается вместе с родительской capability;
- он не используется отдельно;
- это один из drivers;
- он содержит несколько integration classes;
- ему не нужен самостоятельный changelog;
- разные версии компонентов не имеют смысла;
- его удаление не меняет dependency graph продукта.

Пример:

```text
packages/search/src/Drivers/Meilisearch
```

лучше, чем:

```text
packages/search-meilisearch
```

если CoreX не планирует независимо выпускать десятки search adapters.

## Integration packages

Пакеты наподобие:

```text
intl-search
tasks-workflow
attributes-search
media-transfer
crm-search
cms-search
```

нужно рассматривать как архитектурный запах.

Это не абсолютный запрет. Integration package оправдан, если интеграция:

- велика;
- имеет самостоятельный runtime;
- устанавливается опционально многими приложениями;
- не может принадлежать ни одной стороне;
- имеет независимый release lifecycle.

В остальных случаях wiring должен находиться:

- в product package;
- в distribution package;
- в service provider приложения;
- в небольшом adapter namespace одной из capabilities.

## Предлагаемый graph

```text
corex/core
└── no CoreX dependencies

corex/modules
└── corex/core

corex/tenancy
└── corex/core

corex/auth
├── corex/core
└── optional corex/tenancy

corex/attributes
├── corex/core
└── corex/modules

corex/search
├── corex/core
└── optional corex/modules

corex/transfer
└── corex/core

corex/intl
└── corex/core

corex/media
├── corex/core
└── optional corex/tenancy

corex/workflow
└── corex/core

flex/crm
├── corex/modules
├── corex/tenancy
├── corex/auth
├── corex/attributes
├── corex/search
├── corex/transfer
└── corex/intl

flex/cms
├── corex/modules
├── corex/auth
├── corex/attributes
├── corex/search
├── corex/transfer
├── corex/media
└── corex/intl
```

Composer optional dependency нельзя превращать в скрытую обязательную runtime dependency. Если код требует пакет для выбранного driver, CoreX должен выбрасывать понятную configuration exception.

***

# Attributes

## Текущее устройство

`JsonbAttributeStore` работает с package-owned таблицами:

```text
sys_attribute_documents
sys_attribute_write_tokens
```

Он принимает `ConnectionInterface` и nullable `FieldCatalogue`. При `null` schema validation полностью пропускается .

Текущий `ValuePatch` является просто непустым ассоциативным массивом, а `ValueBag` — ассоциативным массивом значений . Это не позволяет однозначно выразить различие между:

- поле не передано;
- поле передано со значением `null`;
- поле требуется удалить;
- поле отсутствует в storage;
- поле существует, но больше не активно в schema;
- поле было очищено;
- поле имеет пустую строку;
- поле имеет пустой массив.

`RegistryFieldCatalogue` строит snapshot на основе `EntityRegistry`, `ModuleRegistry` и `EntityField`, а mapping типов содержит закрытый `match` со списком известных типов .

## Реальная проблема связности

Важно точно сформулировать проблему.

Текущий JSONB store не привязан непосредственно к таблице Eloquent-модели. Он использует sidecar storage CoreX. Жёсткая связность находится в других местах:

1. Имена package-owned таблиц hardcoded в store.
2. Connection передаётся как уже выбранный объект без отдельного storage descriptor.
3. Schema validation можно неявно отключить через `null`.
4. Schema package связан с module registry.
5. Список типов закрыт внутри `match`.
6. `DataScope` переносит callback авторизации вместо явной policy.
7. `RecordRef` не выражает тип ID и lifecycle record.
8. Название `JsonbAttributeStore` не говорит, что это sidecar-document storage.

## Три storage strategy

Не следует создавать один сверхгибкий `JsonbAttributeStore`, который умеет работать в любом режиме.

Нужны три явно разные реализации.

### Sidecar document

```text
sys_attribute_documents
┌───────────────┬───────────┬────────┬────────────────┬───────────────┐
│ tenant scope  │ entity    │ record │ values jsonb   │ version       │
└───────────────┴───────────┴────────┴────────────────┴───────────────┘
```

Преимущества:

- CoreX владеет схемой.
- Не нужно менять таблицу host model.
- Удобно добавлять динамические поля.
- Можно централизовать concurrency и idempotency.
- Подходит CRM и CMS.

Недостатки:

- Нужны joins или отдельное bulk loading.
- Referential integrity к произвольным entity сложна.
- Удаление host record требует cleanup protocol.
- Фильтрация по многим динамическим полям требует индексов.

Предлагаемое имя:

```php
final class DocumentAttributeStore implements AttributeStore
```

### EAV

```text
sys_attribute_records
sys_attribute_values
```

Преимущества:

- Можно индексировать отдельные поля.
- Удобнее хранить typed values.
- Можно выполнять field-level queries.
- Лучше для отчётов по часто используемым custom fields.

Недостатки:

- Больше строк.
- Сложнее bulk loading.
- Сложнее обновлять record atomically.
- Нужно внимательно управлять типами и уникальными ограничениями.

Предлагаемое имя:

```php
final class EavAttributeStore implements AttributeStore
```

### Inline JSON

Значения находятся в таблице host entity:

```text
crm_contacts.custom_attributes
cms_entries.custom_attributes
```

Это отдельный driver:

```php
final class InlineJsonAttributeStore implements AttributeStore
```

Именно ему нужен trusted resolver table/column metadata. Он не должен получать имена таблиц из request или entity payload.

## Storage schema

Для package-owned таблиц:

```php
namespace CoreX\Attributes\Data;

final readonly class AttributeStorageSchema
{
    public function __construct(
        public string $documentsTable,
        public string $writeTokensTable,
        public string $recordsTable,
        public string $valuesTable,
        public string $operationsTable,
    ) {}
}
```

Configuration:

```php
return [
    'driver' => env('COREX_ATTRIBUTES_DRIVER', 'document'),

    'connection' => env('COREX_ATTRIBUTES_CONNECTION'),

    'tables' => [
        'documents' => 'corex_attribute_documents',
        'write_tokens' => 'corex_attribute_write_tokens',
        'records' => 'corex_attribute_records',
        'values' => 'corex_attribute_values',
        'operations' => 'corex_attribute_operations',
    ],
];
```

Provider:

```php
$this->app->singleton(
    AttributeStorageSchema::class,
    static fn (Application $app): AttributeStorageSchema =>
        new AttributeStorageSchema(
            documentsTable: $app['config']->string(
                'corex-attributes.tables.documents'
            ),
            writeTokensTable: $app['config']->string(
                'corex-attributes.tables.write_tokens'
            ),
            recordsTable: $app['config']->string(
                'corex-attributes.tables.records'
            ),
            valuesTable: $app['config']->string(
                'corex-attributes.tables.values'
            ),
            operationsTable: $app['config']->string(
                'corex-attributes.tables.operations'
            ),
        ),
);
```

Store:

```php
private function documents(): Builder
{
    return $this->connection->table(
        $this->schema->documentsTable
    );
}
```

Spatie Settings допускает независимую конфигурацию repository type, model, table и connection. Это хороший пример разделения storage strategy и physical layout. [packagist](https://packagist.org/packages/spatie/laravel-settings)

## Inline resolver

```php
interface EntityStorageResolver
{
    public function resolve(
        ExecutionContext $context,
        EntityHandle $entity,
    ): EntityStorage;
}
```

```php
final readonly class EntityStorage
{
    public function __construct(
        public string $connection,
        public string $table,
        public string $primaryKey,
        public KeyType $keyType,
        public ?string $attributesColumn,
        public ?string $versionColumn,
        public StorageCapabilities $capabilities,
    ) {}
}
```

```php
enum KeyType: string
{
    case Integer = 'integer';
    case Uuid = 'uuid';
    case Ulid = 'ulid';
    case String = 'string';
}
```

```php
final readonly class StorageCapabilities
{
    public function __construct(
        public bool $supportsInlineAttributes,
        public bool $supportsAtomicCompareAndSwap,
        public bool $supportsJsonFiltering,
        public bool $supportsTransactions,
    ) {}
}
```

Resolver получает metadata только из trusted registry:

```php
final readonly class RegistryEntityStorageResolver
    implements EntityStorageResolver
{
    public function __construct(
        private EntityRegistry $entities,
    ) {}

    public function resolve(
        ExecutionContext $context,
        EntityHandle $entity,
    ): EntityStorage {
        $definition = $this->entities->get(
            context: $context,
            handle: $entity,
        );

        if ($definition->storage === null) {
            throw EntityStorageUnavailable::for($entity);
        }

        return $definition->storage;
    }
}
```

Запрещено:

```php
$table = $request->input('table');
$connection = $request->input('connection');
```

Допустимо:

```php
$storage = $resolver->resolve($context, $entity);
```

## Обязательная schema validation

Текущий конструктор допускает:

```php
private ?FieldCatalogue $catalogue = null
```

Это создаёт два режима одного production-класса и позволяет случайно отключить validation .

Предлагается:

```php
final readonly class DocumentAttributeStore implements AttributeStore
{
    public function __construct(
        private ConnectionInterface $connection,
        private AttributeSchemaRepository $schemas,
        private AttributeValueValidator $validator,
        private AttributeAccessPolicy $policy,
        private AttributeStorageSchema $storage,
    ) {}
}
```

Если нужен raw store для migrations или recovery:

```php
/**
 * @internal
 */
final readonly class RawDocumentAttributeStore
{
    // Explicitly bypasses domain schema.
}
```

Нельзя выражать опасный режим через nullable dependency.

Symfony рекомендует делать зависимости явными и передавать их через constructor injection, поскольку это повышает повторное использование, тестируемость и слабую связанность. [symfony](https://symfony.com/doc/current/service_container/injection_types.html)

## Schema contract

Переименование:

```php
interface AttributeSchemaRepository
{
    public function get(
        ExecutionContext $context,
        EntityHandle $entity,
    ): AttributeSchema;
}
```

```php
final readonly class AttributeSchema
{
    /**
     * @param array<string, AttributeDefinition> $attributes
     */
    public function __construct(
        public EntityHandle $entity,
        public SchemaVersion $version,
        public array $attributes,
        public AttributeCapabilities $capabilities,
    ) {}
}
```

```php
final readonly class AttributeDefinition
{
    public function __construct(
        public FieldHandle $handle,
        public AttributeType $type,
        public bool $required,
        public bool $searchable,
        public bool $multiple,
        public mixed $defaultValue,
        public array $metadata,
    ) {}
}
```

Почему `Repository`, а не `Catalogue`:

- Интерфейс возвращает версионированный schema aggregate.
- Он не просто перечисляет поля.
- Реализация может использовать registry, database или compiled cache.
- Название лучше описывает lookup responsibility.

## Расширяемые типы

Вместо закрытого `match`:

```php
interface AttributeTypeHandler
{
    public function type(): AttributeType;

    public function validate(mixed $value): void;

    public function normalize(mixed $value): mixed;

    public function encode(mixed $value): mixed;

    public function decode(mixed $value): mixed;
}
```

```php
final class AttributeTypeRegistry
{
    /** @var array<string, AttributeTypeHandler> */
    private array $handlers = [];

    public function register(AttributeTypeHandler $handler): void
    {
        $name = $handler->type()->value;

        if (isset($this->handlers[$name])) {
            throw DuplicateAttributeType::named($name);
        }

        $this->handlers[$name] = $handler;
    }

    public function handler(AttributeType $type): AttributeTypeHandler
    {
        return $this->handlers[$type->value]
            ?? throw UnsupportedAttributeType::for($type);
    }
}
```

Built-in handlers:

```text
TextType
IntegerType
DecimalType
BooleanType
DateType
DateTimeType
SelectType
MultiSelectType
ReferenceType
MoneyType
UrlType
EmailType
PhoneType
FileType
JsonType
```

Это реальная extension point: CMS сможет добавить `rich_text`, CRM — `organization_reference`, education product — `student_reference`.

## Patch semantics

Вместо массива:

```php
new ValuePatch([
    'phone' => null,
]);
```

где непонятно, означает ли `null` удаление или explicit null, следует моделировать операции.

```php
interface AttributeMutation
{
    public function field(): FieldHandle;
}
```

```php
final readonly class SetAttribute implements AttributeMutation
{
    public function __construct(
        private FieldHandle $field,
        public mixed $value,
    ) {}

    public function field(): FieldHandle
    {
        return $this->field;
    }
}
```

```php
final readonly class RemoveAttribute implements AttributeMutation
{
    public function __construct(
        private FieldHandle $field,
    ) {}

    public function field(): FieldHandle
    {
        return $this->field;
    }
}
```

```php
final readonly class AttributePatch
{
    /**
     * @param non-empty-list<AttributeMutation> $mutations
     */
    public function __construct(
        public array $mutations,
    ) {
        // Validate duplicate fields.
    }
}
```

Использование:

```php
$patch = new AttributePatch([
    new SetAttribute(
        field: new FieldHandle('phone'),
        value: null,
    ),
    new RemoveAttribute(
        field: new FieldHandle('legacy_code'),
    ),
]);
```

Теперь:

- `SetAttribute(null)` — explicit null.
- `RemoveAttribute` — удалить значение.
- Отсутствие mutation — не менять поле.
- Отсутствие stored field — missing.

## Read semantics

```php
enum AttributePresence: string
{
    case Missing = 'missing';
    case Present = 'present';
}
```

```php
final readonly class AttributeValue
{
    private function __construct(
        public AttributePresence $presence,
        public mixed $value,
    ) {}

    public static function missing(): self
    {
        return new self(AttributePresence::Missing, null);
    }

    public static function present(mixed $value): self
    {
        return new self(AttributePresence::Present, $value);
    }
}
```

`present(null)` отличается от `missing()`.

```php
final readonly class AttributeValues
{
    /**
     * @param array<string, AttributeValue> $values
     */
    public function __construct(
        public array $values,
        public RecordVersion $version,
    ) {}
}
```

## Политика доступа

Не следует помещать executable closure авторизации внутрь serialized context.

Предлагается:

```php
interface AttributeAccessPolicy
{
    public function authorize(
        ExecutionContext $context,
        AttributeAction $action,
        RecordRef $record,
        FieldSelection $fields,
    ): void;
}
```

```php
enum AttributeAction: string
{
    case Read = 'read';
    case Write = 'write';
    case Filter = 'filter';
    case Define = 'define';
    case Delete = 'delete';
}
```

Fail-closed implementation:

```php
final class DenyAttributeAccess implements AttributeAccessPolicy
{
    public function authorize(
        ExecutionContext $context,
        AttributeAction $action,
        RecordRef $record,
        FieldSelection $fields,
    ): void {
        throw AttributeAccessDenied::for(
            action: $action,
            record: $record,
        );
    }
}
```

## Выбор driver

```php
final class AttributeStoreManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->string(
            'corex-attributes.driver',
            'document',
        );
    }

    protected function createDocumentDriver(): AttributeStore
    {
        return new DocumentAttributeStore(
            connection: $this->connection(),
            schemas: $this->container->make(
                AttributeSchemaRepository::class
            ),
            validator: $this->container->make(
                AttributeValueValidator::class
            ),
            policy: $this->container->make(
                AttributeAccessPolicy::class
            ),
            storage: $this->container->make(
                AttributeStorageSchema::class
            ),
        );
    }

    protected function createEavDriver(): AttributeStore
    {
        // ...
    }

    protected function createInlineDriver(): AttributeStore
    {
        // ...
    }
}
```

## Contract suite

Все stores должны проходить одни тесты:

```php
abstract class AttributeStoreContract
{
    abstract protected function store(): AttributeStore;

    public function test_it_distinguishes_missing_and_null(): void
    {
        // ...
    }

    public function test_replay_with_same_key_is_idempotent(): void
    {
        // ...
    }

    public function test_replay_with_different_payload_is_rejected(): void
    {
        // ...
    }

    public function test_stale_record_version_is_rejected(): void
    {
        // ...
    }

    public function test_unknown_field_is_rejected(): void
    {
        // ...
    }

    public function test_inactive_field_is_rejected(): void
    {
        // ...
    }

    public function test_denied_read_returns_no_values(): void
    {
        // Prefer explicit exception or a single documented behavior.
    }
}
```

Drivers:

```php
final class DocumentAttributeStoreContractTest
    extends AttributeStoreContract
{
    protected function store(): AttributeStore
    {
        return $this->documentStore();
    }
}
```

```php
final class EavAttributeStoreContractTest
    extends AttributeStoreContract
{
    protected function store(): AttributeStore
    {
        return $this->eavStore();
    }
}
```

***

# Transfer

## Новое название

`corex/import-export` следует переименовать в:

```text
corex/transfer
```

Namespace:

```text
CoreX\Transfer
```

Provider:

```text
TransferServiceProvider
```

Причины:

- Import и export — направления transfer.
- Название уже соответствует текущим `TransferRunner`, `TransferSource`, `TransferRef`, `TransferLimits` и `TransferReceipt`.
- Оно подходит для файлов, streams, API и external systems.
- Не содержит дефис в namespace.
- Не привязывает API к CSV или Excel.
- Подходит CMS migrations, CRM data onboarding и tenant offboarding.

Текущий пакет называется `corex/import-export` и использует namespace `CoreX\ImportExport` .

## Переименование до 1.0

Если внешних consumers ещё нет:

```bash
mv packages/import-export packages/transfer
```

В `composer.json`:

```json
{
    "name": "corex/transfer",
    "description": "Bounded data transfer primitives for CoreX.",
    "autoload": {
        "psr-4": {
            "CoreX\\Transfer\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "CoreX\\Transfer\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "CoreX\\Transfer\\TransferServiceProvider"
            ]
        }
    }
}
```

Если compatibility всё-таки нужна:

```json
{
    "replace": {
        "corex/import-export": "self.version"
    }
}
```

Но `replace` решает Composer dependency, а не совместимость PHP namespace. Для временного namespace bridge потребуется отдельный deprecated package или `class_alias`. До stable release это лишняя сложность, поэтому предпочтителен hard rename.

## Проблемы runner

`DatabaseTransferRunner` сейчас:

- захватывает transfer job через `lockForUpdate`;
- проверяет fencing token;
- описывает и читает source;
- выполняет mapping;
- вызывает target preview/apply/lookup;
- пишет row results;
- обновляет references;
- меняет cursor, stats и state;
- возвращает receipt;
- выполняет всё внутри внешней DB transaction с тремя попытками .

Класс размером более 11 KB выполняет слишком много обязанностей .

Более опасная проблема: target invocation находится внутри retryable transaction. Laravel повторяет transaction closure при deadlock, поэтому внешний HTTP/API side effect потенциально может быть вызван повторно. [laravel](https://laravel.com/framework/docs/12.x/database)

Также код ловит любой `Throwable` вокруг обработки строки и преобразует его в `rowFailed`. Это может скрыть:

- потерю соединения;
- programming error;
- `TypeError`;
- cancellation;
- повреждение schema;
- неверную конфигурацию;
- исчерпание памяти;
- ошибку самого persistence protocol.

Validation failure и infrastructure failure нельзя сводить к одному результату.

## Новый pipeline

```text
Create
  ↓
Claim
  ↓
Read
  ↓
Map
  ↓
Validate
  ↓
Apply
  ↓
Record result
  ↓
Checkpoint
  ↓
Complete / Await reconciliation / Fail
```

## Разделение классов

```text
packages/transfer/src/
├── Contracts/
│   ├── TransferSource.php
│   ├── RecordMapper.php
│   ├── ImportTarget.php
│   ├── LocalImportTarget.php
│   ├── RemoteImportTarget.php
│   ├── ExportSource.php
│   └── ExportSink.php
├── Data/
│   ├── TransferId.php
│   ├── TransferRequest.php
│   ├── TransferManifest.php
│   ├── TransferCursor.php
│   ├── TransferClaim.php
│   ├── TransferBatch.php
│   ├── MappedRecord.php
│   ├── TransferProgress.php
│   └── TransferResult.php
├── Domain/
│   ├── TransferState.php
│   ├── TransferMode.php
│   ├── ConflictStrategy.php
│   └── TransferFailure.php
├── Application/
│   ├── CreateTransfer.php
│   ├── ClaimTransferStep.php
│   ├── RunTransferStep.php
│   ├── CheckpointTransfer.php
│   ├── CompleteTransfer.php
│   └── ReconcileTransferRow.php
├── Persistence/
│   ├── TransferJobRepository.php
│   ├── SqlTransferJobRepository.php
│   ├── TransferRowRepository.php
│   └── TransferReferenceRepository.php
├── Sources/
│   ├── CsvSource.php
│   └── NdjsonSource.php
├── Exports/
│   ├── CsvSink.php
│   └── NdjsonSink.php
└── TransferServiceProvider.php
```

## Claim transaction

```php
final readonly class ClaimTransferStep
{
    public function __construct(
        private ConnectionInterface $connection,
        private TransferJobRepository $jobs,
        private Clock $clock,
    ) {}

    public function handle(
        ExecutionContext $context,
        TransferId $transfer,
        WorkerId $worker,
    ): TransferClaim {
        return $this->connection->transaction(
            callback: fn (): TransferClaim =>
                $this->jobs->claim(
                    context: $context,
                    transfer: $transfer,
                    worker: $worker,
                    now: $this->clock->now(),
                ),
            attempts: 3,
        );
    }
}
```

Transaction должна быть короткой:

- lock row;
- проверить state;
- увеличить claim epoch;
- записать lease;
- вернуть immutable claim;
- commit.

Нельзя читать файл или обращаться к Meilisearch/HTTP под этим lock.

## Step orchestration

```php
final readonly class RunTransferStep
{
    public function __construct(
        private ClaimTransferStep $claims,
        private TransferSourceResolver $sources,
        private RecordMapperResolver $mappers,
        private ImportTargetResolver $targets,
        private ApplyTransferBatch $apply,
        private CheckpointTransfer $checkpoints,
    ) {}

    public function handle(
        ExecutionContext $context,
        TransferId $transfer,
        WorkerId $worker,
    ): TransferStepResult {
        $claim = $this->claims->handle(
            context: $context,
            transfer: $transfer,
            worker: $worker,
        );

        $source = $this->sources->resolve($claim->source);
        $manifest = $source->describe($claim->source);

        $claim->assertSourceIsUnchanged($manifest);

        $batch = $source->read(
            manifest: $manifest,
            after: $claim->cursor,
            limits: $claim->limits,
        );

        $outcome = $this->apply->handle(
            context: $context,
            claim: $claim,
            batch: $batch,
        );

        return $this->checkpoints->handle(
            claim: $claim,
            outcome: $outcome,
        );
    }
}
```

## Error taxonomy

```php
interface TransferException extends Throwable
{
}
```

```php
final class InvalidSourceRow extends RuntimeException
    implements TransferException
{
}
```

```php
final class MappingRejected extends RuntimeException
    implements TransferException
{
}
```

```php
final class TargetConflict extends RuntimeException
    implements TransferException
{
}
```

```php
final class UnknownTargetOutcome extends RuntimeException
    implements TransferException
{
    public function __construct(
        public readonly ?string $providerTaskId,
        Throwable $previous,
    ) {
        parent::__construct(
            'Target outcome is unknown and requires reconciliation.',
            previous: $previous,
        );
    }
}
```

```php
final class TransferInfrastructureFailure extends RuntimeException
    implements TransferException
{
}
```

Catching:

```php
try {
    $result = $target->apply($command);
} catch (MappingRejected|InvalidSourceRow $exception) {
    return RowOutcome::invalid(
        code: $exception->getCode(),
        message: $exception->getMessage(),
    );
} catch (TargetConflict $exception) {
    return RowOutcome::conflict(
        code: $exception->getCode(),
    );
} catch (UnknownTargetOutcome $exception) {
    return RowOutcome::unknown(
        providerTaskId: $exception->providerTaskId,
    );
}
```

Все остальные исключения должны подниматься выше и переводить step/job в infrastructure failure либо инициировать queue retry.

## Local и remote target

Один `ImportTarget` не должен скрывать фундаментальную разницу между local transaction и remote API.

```php
interface ImportTarget
{
    public function capabilities(): TargetCapabilities;
}
```

```php
interface LocalImportTarget extends ImportTarget
{
    public function apply(
        ExecutionContext $context,
        MappedRecord $record,
        ConflictStrategy $strategy,
        OperationKey $operation,
    ): TargetResult;
}
```

```php
interface RemoteImportTarget extends ImportTarget
{
    public function submit(
        ExecutionContext $context,
        MappedRecord $record,
        OperationKey $operation,
    ): RemoteSubmission;

    public function reconcile(
        ExecutionContext $context,
        RemoteTaskId $task,
    ): RemoteTargetStatus;
}
```

```php
final readonly class TargetCapabilities
{
    public function __construct(
        public bool $transactional,
        public bool $supportsIdempotencyKeys,
        public bool $supportsLookup,
        public bool $returnsOperationId,
        public bool $supportsReconciliation,
    ) {}
}
```

## Idempotency

Operation key должен строиться в одном специальном компоненте:

```php
interface TransferOperationKeyFactory
{
    public function forRecord(
        TransferId $transfer,
        SourceIdentity $source,
        MappedRecord $record,
    ): OperationKey;
}
```

```php
final class Sha256TransferOperationKeyFactory
    implements TransferOperationKeyFactory
{
    public function forRecord(
        TransferId $transfer,
        SourceIdentity $source,
        MappedRecord $record,
    ): OperationKey {
        return new OperationKey(hash(
            'sha256',
            implode("\n", [
                $transfer->value,
                $source->system,
                $source->recordId,
                $source->revision,
                $record->payloadHash->value,
            ]),
        ));
    }
}
```

Нельзя разбрасывать различные формулы `hash()` между runner, repository и target.

## Preview

Preview должен быть отдельной operation mode, а не частично отключённым normal run.

```php
interface TransferPreviewer
{
    public function preview(
        ExecutionContext $context,
        TransferPreviewRequest $request,
    ): TransferPreview;
}
```

Если preview большой, он может иметь persisted job, но обязан использовать отдельный state machine.

В текущем runner dry run не обновляет persisted checkpoint, хотя возвращает увеличенный checkpoint sequence. Это создаёт неоднозначность для многошагового preview и требует отдельного теста/решения .

## Transfer v1

В первую стабильную версию включить:

- CSV.
- NDJSON.
- Immutable source manifest.
- Mapping snapshot.
- Preview.
- Local target.
- Idempotency.
- Resume.
- Bounded errors.
- Explicit conflict strategy.
- Streaming export.
- Contract tests.

Отложить в v2:

- arbitrary external targets;
- provider reconciliation;
- distributed gap ledger;
- generic outbox;
- exactly-once-like delivery;
- tenant offboarding export protocol;
- XLSX;
- distributed index synchronization.

***

# Search

## Проблема bindings

`SearchServiceProvider` сейчас регистрирует `SearchProvider`. Если driver не `postgres`, отсутствует cursor secret, schema binding или database binding, он возвращает `UnsupportedSearchProvider` .

`MeilisearchServiceProvider` при включённой и валидной конфигурации повторно регистрирует тот же `SearchProvider`. Если конфигурация неполна, provider просто ничего не регистрирует .

Итог зависит от:

- порядка загрузки service providers;
- того, какой provider последним перезаписал binding;
- того, был ли Meilisearch config признан валидным;
- наличия более раннего fallback binding.

Это неявная и хрупкая модель.

## SearchManager

Нужен единственный manager:

```php
interface SearchEngine
{
    public function capabilities(): SearchCapabilities;

    public function search(
        ExecutionContext $context,
        SearchQuery $query,
    ): SearchCandidatePage;
}
```

```php
final class SearchManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->string(
            'corex-search.driver',
            'database',
        );
    }

    protected function createDatabaseDriver(): SearchEngine
    {
        return $this->container->make(
            DatabaseSearchEngine::class
        );
    }

    protected function createMeilisearchDriver(): SearchEngine
    {
        if (! class_exists(\Meilisearch\Client::class)) {
            throw MissingSearchDependency::for(
                driver: 'meilisearch',
                package: 'meilisearch/meilisearch-php',
            );
        }

        return $this->container->make(
            MeilisearchSearchEngine::class
        );
    }
}
```

Provider:

```php
final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/corex-search.php',
            'corex-search',
        );

        $this->app->singleton(
            SearchManager::class,
            fn (Application $app): SearchManager =>
                new SearchManager($app),
        );

        $this->app->scoped(
            SearchEngine::class,
            fn (Application $app): SearchEngine =>
                $app->make(SearchManager::class)->driver(),
        );
    }
}
```

Laravel Scout использует engine manager и позволяет добавлять drivers через `extend()`. Это проверенный Laravel-подход для одной capability с несколькими engines. [laravel](https://laravel.com/docs/9.x/scout)

## Структура search

```text
packages/search/
├── composer.json
├── config/
│   └── corex-search.php
├── src/
│   ├── Contracts/
│   │   ├── SearchEngine.php
│   │   ├── SearchDocumentSource.php
│   │   ├── SearchResultHydrator.php
│   │   ├── SearchAccessPolicy.php
│   │   └── SearchSchemaRepository.php
│   ├── Data/
│   │   ├── SearchQuery.php
│   │   ├── SearchFilter.php
│   │   ├── SearchSort.php
│   │   ├── SearchCursor.php
│   │   ├── SearchCandidate.php
│   │   ├── SearchCandidatePage.php
│   │   └── SearchResultPage.php
│   ├── Drivers/
│   │   ├── Database/
│   │   │   ├── DatabaseSearchEngine.php
│   │   │   └── DatabaseFilterCompiler.php
│   │   └── Meilisearch/
│   │       ├── MeilisearchSearchEngine.php
│   │       ├── MeilisearchFilterCompiler.php
│   │       └── MeilisearchClientFactory.php
│   ├── Indexing/
│   │   ├── SearchIndexer.php
│   │   ├── IndexDocument.php
│   │   ├── RemoveDocument.php
│   │   └── RebuildIndex.php
│   ├── Application/
│   │   └── SearchRecords.php
│   ├── SearchManager.php
│   └── SearchServiceProvider.php
└── tests/
    ├── Contract/
    ├── Drivers/Database/
    └── Drivers/Meilisearch/
```

## Optional Meilisearch dependency

`composer.json` объединённого search package:

```json
{
    "require": {
        "php": "^8.3",
        "corex/core": "@dev",
        "illuminate/contracts": "^12.0|^13.0",
        "illuminate/database": "^12.0|^13.0",
        "illuminate/support": "^12.0|^13.0"
    },
    "require-dev": {
        "meilisearch/meilisearch-php": "^1.16",
        "orchestra/testbench": "^10.0|^11.0",
        "pestphp/pest": "^4.0",
        "larastan/larastan": "^3.0"
    },
    "suggest": {
        "meilisearch/meilisearch-php": "Required for the Meilisearch search driver."
    }
}
```

Consumer:

```bash
composer require corex/search
```

Для Meilisearch:

```bash
composer require corex/search meilisearch/meilisearch-php
```

Meilisearch SDK сейчас является обязательной зависимостью отдельного `corex/search-meilisearch` . После объединения он становится optional runtime dependency и dev dependency самого search package.

## Configuration failure

Нельзя молча возвращать unsupported implementation.

Неправильно:

```php
return new UnsupportedSearchProvider();
```

Предпочтительно:

```php
throw new InvalidSearchConfiguration(
    'Search driver [meilisearch] requires a valid host, key and index prefix.'
);
```

Для явно отключённого search допустим отдельный driver:

```php
'driver' => 'null',
```

```php
final class NullSearchEngine implements SearchEngine
{
    public function search(
        ExecutionContext $context,
        SearchQuery $query,
    ): SearchCandidatePage {
        return SearchCandidatePage::empty();
    }
}
```

Это отличается от сломанной конфигурации:

- `null` driver выбран намеренно;
- invalid driver — ошибка;
- отсутствующая SDK — ошибка;
- недоступный remote server — runtime infrastructure error.

## Candidate и hydration

Внешний search engine не должен быть authority.

Pipeline:

```text
Query
  ↓
SearchEngine returns candidate IDs
  ↓
Host repositories load current records
  ↓
Current policy filters records
  ↓
Results are presented
```

```php
final readonly class SearchRecords
{
    public function __construct(
        private SearchEngine $engine,
        private SearchResultHydrator $hydrator,
        private SearchAccessPolicy $policy,
    ) {}

    public function handle(
        ExecutionContext $context,
        SearchQuery $query,
    ): SearchResultPage {
        $candidates = $this->engine->search(
            context: $context,
            query: $query,
        );

        $records = $this->hydrator->hydrate(
            context: $context,
            candidates: $candidates->items,
        );

        $authorized = $this->policy->filter(
            context: $context,
            records: $records,
        );

        return SearchResultPage::from(
            candidates: $candidates,
            records: $authorized,
        );
    }
}
```

Нельзя возвращать весь документ из Meilisearch напрямую как final domain record, особенно если ACL изменился после индексации.

## Filter AST

```php
interface SearchFilter
{
}
```

```php
final readonly class EqualsFilter implements SearchFilter
{
    public function __construct(
        public string $field,
        public scalar|null $value,
    ) {}
}
```

```php
final readonly class AndFilter implements SearchFilter
{
    /**
     * @param non-empty-list<SearchFilter> $filters
     */
    public function __construct(
        public array $filters,
    ) {}
}
```

```php
final readonly class InFilter implements SearchFilter
{
    /**
     * @param non-empty-list<scalar|null> $values
     */
    public function __construct(
        public string $field,
        public array $values,
    ) {}
}
```

Каждый driver имеет compiler:

```php
interface SearchFilterCompiler
{
    public function compile(SearchFilter $filter): mixed;
}
```

Не следует принимать raw SQL, raw Meilisearch expressions или пользовательские callbacks в публичном query DTO.

Meilisearch требует заранее определить filterable и sortable attributes; это часть schema/index deployment, а не ad hoc query behavior. [meilisearch](https://www.meilisearch.com/docs/getting_started/frameworks/laravel)

## Naming

| Сейчас | Предлагается |
|---|---|
| `SearchProvider` | `SearchEngine` |
| `PostgresSearchProvider` | `DatabaseSearchEngine` или `PostgresSearchEngine` |
| `UnsupportedSearchProvider` | Удалить либо заменить `NullSearchEngine` |
| `CandidatePage` | `SearchCandidatePage` |
| `SearchHydrator` | `SearchResultHydrator` |
| `SearchDocuments` | `SearchDocumentSource` |
| `SearchSchema` | `SearchSchemaRepository` |
| `IndexDriver` | `SearchIndexer` |
| `IndexSubmission` | `IndexTask` |
| `IndexOutcome` | `IndexTaskStatus` |

***

# Intl Search

## Текущее назначение

`corex/intl-search` прямо описан как optional localized projection glue между `corex/intl` и `corex/search` и требует оба пакета .

Service provider регистрирует `LocalizedFieldProjector` и `SavedViewLabelReader`, только если integration включена .

Это небольшая integration layer, а не самостоятельная capability.

## Почему пакет нужно удалить

Проблемы отдельного package:

- отдельная версия;
- отдельный changelog;
- отдельный provider;
- отдельная конфигурация;
- отдельные tests;
- дополнительная строка в root dependencies;
- потребитель должен понимать внутреннюю package topology;
- появляется шаблон для будущих `x-y` packages.

Root `composer.json` уже требует `corex/intl-search`, то есть optional glue фактически стал частью основной development distribution .

## Куда перенести ответственность

Нужно разделить две разные задачи.

### Локализация search documents

Контракт принадлежит search:

```php
interface SearchDocumentContributor
{
    /**
     * @return array<string, scalar|array|null>
     */
    public function contribute(
        ExecutionContext $context,
        SearchableRecord $record,
    ): array;
}
```

Intl implementation может находиться в:

```text
packages/intl/src/Search/LocalizedDocumentContributor.php
```

или product package:

```text
packages/crm/src/Search/ContactLocalizationContributor.php
packages/cms/src/Search/ContentLocalizationContributor.php
```

Для максимальной независимости предпочтительнее product implementations, потому что только CRM/CMS знает, какие локализованные поля принадлежат документу.

### Локализация saved views

`SavedViewLabelReader` не относится к full-text search. Его нужно переместить в:

```text
corex/panels
```

или:

```text
corex/views
```

Если labels используют Intl:

```text
packages/panels/src/Localization/LocalizedViewLabelReader.php
```

Это integration `panels + intl`, а не `search + intl`.

## Composition root

CRM provider:

```php
final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag(
            [
                ContactSearchDocumentContributor::class,
                CompanySearchDocumentContributor::class,
                LocalizedCrmFieldContributor::class,
            ],
            'corex.search.document-contributors',
        );
    }
}
```

Search package не знает о CRM или Intl.

***

# Публичный API

## API census

До stable release необходимо создать machine-readable список публичных symbols.

```yaml
packages:
  corex/attributes:
    public:
      - CoreX\Attributes\Contracts\AttributeStore
      - CoreX\Attributes\Contracts\AttributeSchemaRepository
      - CoreX\Attributes\Data\AttributePatch
      - CoreX\Attributes\Data\AttributeValues
    internal:
      - CoreX\Attributes\Persistence\SqlAttributeSchemaRepository
      - CoreX\Attributes\Drivers\Document\DocumentAttributeStore
```

Для каждого symbol:

| Поле | Смысл |
|---|---|
| Package | Владелец |
| Symbol | Полное имя |
| Kind | Interface, DTO, enum, exception |
| Public | Гарантируется ли BC |
| Since | Версия появления |
| Consumers | Какие packages используют |
| Replacement | Как заменить implementation |
| Stability | Experimental, provisional, stable |
| Removal | План удаления/deprecation |

## Public namespaces

Предлагаемое правило:

```text
CoreX\Package\Contracts\*     public
CoreX\Package\Data\*          public
CoreX\Package\Enums\*         public
CoreX\Package\Exceptions\*    public when consumer must catch it

CoreX\Package\Drivers\*       implementation API
CoreX\Package\Persistence\*   internal by default
CoreX\Package\Internal\*      no BC promise
```

Однако публичность нельзя определять только директорией. Она должна быть записана в API policy и проверяться тестами.

## Contracts

В `Contracts` помещать только interfaces.

Не помещать:

- DTO;
- value objects;
- enums;
- exceptions;
- traits;
- abstract base classes;
- implementations.

Пример:

```text
Contracts/AuditEntry.php
```

следует перенести в:

```text
Data/AuditEntry.php
```

если это DTO.

`FeatureFlagDefinition`:

```text
Data/FeatureFlagDefinition.php
```

`SettingsScope`:

```text
Data/SettingsScope.php
```

В текущем core эти типы находятся рядом с interfaces .

## Internal

`Internal` полезен как сигнал, но PHP технически не запрещает импортировать класс из этого namespace.

Поэтому нужны:

- documentation policy;
- architecture tests;
- PHPStan custom rule;
- semver statement;
- public API snapshot;
- запрет cross-package imports.

Пример Pest architecture test:

```php
arch('packages do not depend on foreign internal namespaces')
    ->expect([
        'CoreX\\Attributes',
        'CoreX\\Search',
        'CoreX\\Transfer',
        'CoreX\\Intl',
    ])
    ->not->toUse([
        'CoreX\\Modules\\Internal',
        'CoreX\\Tenancy\\Internal',
        'CoreX\\Search\\Internal',
        'CoreX\\Attributes\\Internal',
    ]);
```

Более надёжно добавить Deptrac:

```yaml
deptrac:
  paths:
    - ./packages

  layers:
    - name: Core
      collectors:
        - type: classLike
          value: ^CoreX\\(?!Attributes|Search|Transfer|Intl|Modules)

    - name: Modules
      collectors:
        - type: classLike
          value: ^CoreX\\Modules\\

    - name: Attributes
      collectors:
        - type: classLike
          value: ^CoreX\\Attributes\\

  ruleset:
    Core: []
    Modules: [Core]
    Attributes: [Core, Modules]
```

## Support

`Support` должен быть исключением, а не стандартной свалкой.

Текущие Transfer DTO (`CanonicalRecord`, `MappingSnapshot`, `SourceCursor`, `StepToken`, `TransferLimits`, `TransferReceipt`, `TransferRef`) находятся в `Support`, хотя это центральные domain/application data objects .

Их следует перенести:

```text
Support/CanonicalRecord   -> Data/CanonicalRecord
Support/MappingSnapshot   -> Data/MappingSnapshot
Support/SourceCursor      -> Data/TransferCursor
Support/StepToken         -> Data/TransferClaim
Support/TransferLimits    -> Data/TransferLimits
Support/TransferReceipt   -> Data/TransferStepResult
Support/TransferRef       -> Data/TransferId
Support/ConflictRule      -> Domain/ConflictStrategy
Support/TargetResult      -> Data/TargetResult
```

## Services и Managers

Правила:

- `Manager` используется только для driver resolution/registry.
- `Service` не используется как универсальный suffix.
- Use-case получает глагольное имя.
- Repository описывает persistence aggregate.
- Resolver выбирает implementation/metadata.
- Compiler переводит AST.
- Factory создаёт object.
- Policy принимает решение.
- Validator проверяет данные.
- Projector строит representation.
- Hydrator восстанавливает domain records.

Примеры:

| Плохое имя | Хорошее имя |
|---|---|
| `AttributeService` | `WriteAttributes` |
| `SearchService` | `SearchRecords` |
| `TransferManager` | `TransferManager`, только если выбирает driver |
| `TransferService` | `RunTransferStep` |
| `SchemaHelper` | `AttributeSchemaHasher` |
| `DataProcessor` | `CsvRowDecoder` |
| `CommonUtils` | Разделить по responsibility |
| `EntityManager` | `EntityRegistry` или `RegisterEntity` |

## Final classes

Implementation classes — `final` по умолчанию:

```php
final readonly class SearchRecords
{
}
```

Расширяемость предоставляется через composition:

```php
interface SearchEngine
{
}
```

Нельзя ожидать наследование от каждого implementation.

***

# Service Providers

## Ответственность provider

Laravel определяет service providers как connection point между package и application container, а также место регистрации package resources. [laravel](https://laravel.com/framework/docs/packages)

Provider может:

- merge config;
- register bindings;
- register managers;
- register tagged services;
- register commands;
- publish config;
- publish migrations;
- load routes при необходимости.

Provider не должен:

- выполнять DDL;
- обращаться по HTTP;
- проверять доступность Meilisearch;
- создавать tenant databases;
- запускать reconciliation;
- изменять domain data;
- строить registry с дорогим I/O;
- зависеть от порядка перезаписи одного binding несколькими providers.

## Provider template

```php
final class AttributesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/corex-attributes.php',
            'corex-attributes',
        );

        $this->app->singleton(AttributeStoreManager::class);

        $this->app->scoped(
            AttributeStore::class,
            fn (Application $app): AttributeStore =>
                $app->make(AttributeStoreManager::class)->driver(),
        );

        $this->app->singleton(
            AttributeStorageSchema::class,
            fn (Application $app): AttributeStorageSchema =>
                AttributeStorageSchema::fromConfig(
                    $app['config']->array('corex-attributes.tables')
                ),
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/corex-attributes.php'
                => config_path('corex-attributes.php'),
        ], 'corex-attributes-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations'
                => database_path('migrations'),
        ], 'corex-attributes-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ValidateAttributeSchema::class,
            ]);
        }
    }
}
```

## Scoped bindings

Tenant-aware services и services с request/job context должны быть `scoped`, а не singleton. Laravel очищает scoped bindings при переходе к следующему request или queue job в long-lived worker. [laravel](https://laravel.com/docs/master/container.md)

Кандидаты на `scoped`:

- current tenant context;
- current actor context;
- selected tenant connection;
- attribute store;
- search engine, если он зависит от tenant;
- access policy context;
- transfer execution context.

Кандидаты на `singleton`:

- immutable config descriptors;
- stateless registries после compilation;
- clock;
- codec registry;
- manager без tenant-local mutable state.

***

# Configuration

## Категории config

### Runtime config

Можно менять без migration:

```php
'driver'
'queue'
'batch_size'
'timeout'
'cache_store'
'logging'
'feature toggles'
```

### Installation config

Меняется только вместе с migration:

```php
'table names'
'table prefix'
'connection'
'id type'
'morph map'
'inline JSON column'
'schema strategy'
```

### Domain policy

Не должна быть обычным package config:

```php
'trial duration'
'CRM stages'
'CMS publication workflow'
'allowed fields by tariff'
'department ownership'
'export permissions'
'which records can be deleted'
```

Domain policy предоставляется через contracts:

```php
interface AttributeQuotaPolicy
{
    public function assertCanCreate(
        ExecutionContext $context,
        EntityHandle $entity,
        AttributeDefinition $definition,
    ): void;
}
```

## Typed config

Не следует распределять вызовы:

```php
config('corex.foo.bar')
```

по всему пакету.

Нужен typed descriptor:

```php
final readonly class SearchConfiguration
{
    public function __construct(
        public string $driver,
        public string $cursorSecret,
        public DatabaseSearchConfiguration $database,
        public MeilisearchConfiguration $meilisearch,
    ) {}

    public static function fromRepository(
        Repository $config,
    ): self {
        // Validate once.
    }
}
```

Конфигурация валидируется при разрешении capability, а не произвольным набором условий в service provider.

***

# Transactions and events

## Короткие транзакции

В transaction closure можно выполнять:

- SQL reads/writes;
- pessimistic locks;
- optimistic compare-and-swap;
- local aggregate validation;
- insertion into local outbox.

Нельзя выполнять:

- HTTP;
- отправку email;
- upload в remote storage;
- вызов Meilisearch;
- external webhook;
- длительный file parsing;
- ожидание provider task;
- большой CPU transformation.

Laravel может повторить transaction closure при deadlock. Следовательно, внешний side effect внутри такой closure может выполниться повторно. [laravel](https://laravel.com/framework/docs/12.x/database)

## After commit

Событие, job или listener, которому нужны committed records, должно запускаться после commit. Laravel отдельно предупреждает, что queued listener может выполниться до завершения transaction, и поддерживает `ShouldQueueAfterCommit` и `ShouldDispatchAfterCommit`. [laravel](https://laravel.com/framework/docs/12.x/events)

Пример:

```php
final readonly class AttributeValuesChanged
    implements ShouldDispatchAfterCommit
{
    public function __construct(
        public RecordRef $record,
        public RecordVersion $version,
    ) {}
}
```

Но after-commit event не заменяет durable outbox, если требуется гарантированная интеграционная доставка.

## Domain event и integration event

```text
Domain event
- локальный факт внутри bounded context;
- может содержать domain types;
- используется внутри одного процесса.

Integration event
- стабильный serialized contract;
- публикуется после commit;
- может читаться другим package/service;
- имеет version;
- не содержит Eloquent model.
```

Пример integration envelope:

```php
final readonly class IntegrationEvent
{
    public function __construct(
        public EventId $id,
        public string $type,
        public int $version,
        public DateTimeImmutable $occurredAt,
        public ExecutionScope $scope,
        public array $payload,
    ) {}
}
```

## Events не заменяют commands

Неправильно:

```php
event(new CreateSearchIndex($entity));
```

если создание индекса является обязательной операцией.

Предпочтительно:

```php
$rebuildIndex->handle($command);
```

После успешного изменения можно отправить уведомление:

```php
event(new SearchIndexRebuilt(...));
```

***

# Bitrix inspiration

## Что сохранить

CoreX может перенять продуктовые идеи Bitrix:

- module catalogue;
- install/activate/deactivate lifecycle;
- entity registry;
- dynamic fields;
- unified permissions;
- admin panels;
- saved views;
- search;
- CRM и CMS на общем platform foundation;
- import/export;
- background agents;
- audit;
- extension points.

## Что не переносить

Нельзя переносить:

- глобальный application state;
- статические registries;
- прямое обращение модуля к таблицам другого модуля;
- строковые event names без contracts;
- обработчики с неочевидным порядком;
- magic service location;
- runtime schema mutation из request;
- универсальные массивы вместо DTO;
- один общий god-kernel;
- скрытые cross-module assumptions;
- install scripts, выполняющие необратимые операции без журнала;
- вечную совместимость со случайно опубликованным API.

## CoreX alternative

| Bitrix-подобный подход | CoreX |
|---|---|
| Global module registry | Immutable compiled module registry |
| Static service access | Constructor injection |
| Magic events | Typed commands и events |
| User fields | Versioned attribute schema |
| Agents | Laravel queues + durable operation state |
| Search index | Search engines + policy hydration |
| Import/export scripts | Transfer pipeline |
| Module DB access | Package-owned schema + explicit ports |
| Runtime include files | Composer + PSR-4 |
| Global configuration | Typed package configuration |

PSR-4 требует соответствия namespace и directory structure, поэтому namespaces должны отражать архитектуру и ответственность, а не исторические категории. [php-fig](https://www.php-fig.org/psr/psr-4/)

***

# Названия каталогов

## Стандарт пакета

```text
packages/{package}/
├── composer.json
├── config/
├── database/
│   ├── factories/
│   └── migrations/
├── src/
│   ├── Application/
│   ├── Contracts/
│   ├── Data/
│   ├── Domain/
│   ├── Drivers/
│   ├── Events/
│   ├── Exceptions/
│   ├── Persistence/
│   ├── Policies/
│   └── {Package}ServiceProvider.php
└── tests/
    ├── Architecture/
    ├── Contract/
    ├── Feature/
    ├── Integration/
    ├── Unit/
    └── TestCase.php
```

Не каждый package обязан содержать все папки.

## Domain vs Application

`Domain`:

- enums;
- value objects;
- invariants;
- domain decisions;
- domain services без Laravel/container/database.

`Application`:

- commands;
- queries;
- orchestration;
- use cases;
- transaction boundary coordination.

`Persistence`:

- SQL repositories;
- Eloquent models;
- schema records;
- query builders.

`Drivers`:

- external providers;
- alternative storage engines;
- Meilisearch;
- filesystem adapters.

## Models

`Models` использовать только для настоящих Eloquent models:

```text
Persistence/Models/TransferJob.php
```

Не называть DTO словом Model.

## Actions

Если используется `Actions`, правило должно быть строгим:

```php
final readonly class RebuildSearchIndex
{
    public function handle(RebuildSearchIndexCommand $command): void
    {
    }
}
```

Если classes содержат orchestration, лучше `Application`, а не большой общий `Actions`.

## Concerns

Traits можно помещать в `Concerns`, но их использование должно быть редким.

Traits не должны:

- скрывать dependencies;
- обращаться к container;
- выполнять database writes;
- регистрировать observers неявно;
- определять бизнес-политику.

***

# Лучшие практики Laravel

## Не дублировать framework

CoreX не должен создавать собственные аналоги:

- service container;
- events dispatcher;
- queue abstraction без дополнительной семантики;
- filesystem abstraction;
- HTTP client;
- cache repository;
- transaction API;
- validation framework;
- authorization Gate целиком.

Создавать собственный port нужно только там, где CoreX добавляет domain semantics:

```text
AttributeStore
SearchEngine
TransferSource
ModuleRegistry
TenantProvisioner
```

## Package resources

Laravel поддерживает package config, migrations, translations, views и automatic provider discovery. CoreX должен использовать эти conventions, а не вводить собственную универсальную installer систему без доказанной необходимости. [laravel](https://laravel.com/framework/docs/packages)

## Spatie patterns

Полезные практики Spatie:

- простая установка;
- publishable config;
- replaceable implementation;
- contract-driven custom model;
- package Testbench;
- Pest architecture tests;
- PHPStan;
- небольшой верхнеуровневый API.

Spatie Permission позволяет заменить Role и Permission models при соблюдении contracts. Media Library позволяет заменить path generator через interface и container. Settings отделяет repository type, model, table и connection. [spatie](https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/using-a-custom-directory-structure)

CoreX следует перенять сам принцип:

> Каждый реально вариативный механизм имеет один понятный contract и один рабочий default.

Не нужно копировать API конкретного Spatie package буквально.

***

# Лучшие практики Symfony

## Component mentality

Каждый package должен:

- выполнять одну законченную функцию;
- иметь небольшой public surface;
- не зависеть от application;
- использовать constructor injection;
- не читать global container;
- иметь deterministic configuration;
- тестироваться отдельно.

Symfony рекомендует стандартную, плоскую и самообъясняющуюся структуру, а reusable component создавать только при настоящей повторной используемости. [symfony](https://symfony.com/doc/current/best_practices.html)

## Constructor injection

Основной подход:

```php
final readonly class SearchRecords
{
    public function __construct(
        private SearchEngine $engine,
        private SearchResultHydrator $hydrator,
        private SearchAccessPolicy $policy,
    ) {}
}
```

Неправильно:

```php
public function search(): array
{
    $engine = app(SearchEngine::class);
}
```

## Optional dependency

Optional dependency не должна выражаться nullable constructor dependency в критичном пути.

Плохо:

```php
public function __construct(
    private ?AttributeSchemaRepository $schemas = null,
) {}
```

Допустимо для настоящего optional collaborator:

```php
public function __construct(
    private MetricsCollector $metrics = new NullMetricsCollector(),
) {}
```

Ключевое отличие: отсутствие metrics не меняет correctness, а отсутствие schema validation меняет.

***

# Изменение plans

## Проблема текущего подхода

Большой frozen plan полезен для фиксации invariants, но опасен, если одновременно замораживает:

- имена DTO;
- список таблиц;
- package boundaries;
- implementation details;
- state machine;
- test case IDs;
- receipts;
- hashes документов;
- release evidence.

План должен помогать менять код безопасно, а не делать изменение самого плана дороже изменения implementation.

## Новая структура

```text
plans/
├── README.md
├── charter/
│   ├── platform-principles.md
│   ├── package-boundaries.md
│   ├── public-api-policy.md
│   └── compatibility-policy.md
├── adr/
│   ├── 0001-package-layers.md
│   ├── 0002-attributes-storage.md
│   ├── 0003-transfer-naming.md
│   ├── 0004-search-driver-model.md
│   ├── 0005-intl-search-integration.md
│   └── 0006-events-and-outbox.md
├── capabilities/
│   ├── attributes/
│   │   ├── README.md
│   │   ├── invariants.md
│   │   ├── api.md
│   │   ├── persistence.md
│   │   ├── failure-modes.md
│   │   ├── security.md
│   │   ├── migration.md
│   │   └── acceptance.md
│   ├── transfer/
│   └── search/
├── products/
│   ├── crm-vertical-slice.md
│   └── cms-vertical-slice.md
├── releases/
│   ├── 0.2.md
│   ├── 0.3.md
│   └── 1.0.md
└── evidence/
    ├── README.md
    └── generated/
```

## ADR template

```markdown
# ADR-0003: Rename ImportExport to Transfer

## Status

Accepted

## Context

The package covers import, export, preview, resume and source/target transfer.

## Decision

Rename:
- corex/import-export -> corex/transfer
- CoreX\ImportExport -> CoreX\Transfer

## Alternatives

- Exchange
- Transit
- Porter
- Pipeline

## Consequences

Positive:
- One-word package name.
- Symmetric import/export vocabulary.
- Suitable for remote and file transfers.

Negative:
- Breaking namespace change before 1.0.

## Migration

Describe exact file, namespace and Composer changes.

## Validation

- Composer install.
- Package discovery.
- PHPStan.
- Test suite.
```

## Capability plan template

```markdown
# Attributes v1

## Status

Draft

## Problem

What user/product problem is being solved?

## Consumers

- CRM
- CMS
- Neutral fixture application

## Goals

## Non-goals

## Invariants

Only statements that must remain true regardless of implementation.

## Public API

Provisional code examples.

## Persistence

Tables and ownership.

## Failure model

## Concurrency

## Security

## Observability

## Migration

## Contract tests

## Acceptance criteria

## Deferred decisions

## Open questions
```

## Что замораживать

Frozen invariants:

- tenant connection не выбирается из request payload;
- stale token не применяется;
- external search index не является authority;
- invalid configuration не работает silently;
- remote unknown outcome не повторяется вслепую;
- runtime HTTP request не выполняет DDL;
- package не пишет в чужую таблицу без explicit adapter;
- restore epoch инвалидирует старые jobs;
- operation key не переиспользуется для другого payload.

## Что не замораживать рано

Provisional:

- names DTO;
- exact directory names;
- exact table split;
- number of state classes;
- private implementation;
- internal SQL;
- package split для adapters;
- remote transfer protocol;
- generic outbox;
- schema operation runner.

## Evidence

Generated evidence не должен редактироваться вручную:

```text
plans/evidence/generated/
├── test-results.xml
├── coverage-summary.json
├── phpstan.txt
├── dependency-graph.json
└── public-api.json
```

В Git можно сохранять компактные summaries, но не обязательно хранить огромные transient artifacts.

***

# Поэтапный план

## Этап 0: Freeze features

На время архитектурной стабилизации:

- не добавлять новые capabilities;
- не создавать новые integration packages;
- не стабилизировать public API;
- разрешать только bug fixes, tests и refactoring boundaries.

Результат: архитектура перестаёт двигаться одновременно со всем функциональным backlog.

## Этап 1: Package graph

Работы:

1. Составить список packages.
2. Описать ответственность каждого одним предложением.
3. Построить dependency graph.
4. Найти cycles.
5. Найти glue packages.
6. Найти зависимости product → platform и platform → product.
7. Определить package-owned tables.
8. Создать ADR.

Acceptance:

```text
core-only app boots
modules-only app boots
attributes app boots without CRM
search app boots without Meilisearch SDK when database/null driver selected
intl app boots without search
CRM composes capabilities explicitly
```

## Этап 2: API census

Работы:

- экспортировать все symbols;
- классифицировать public/internal;
- перенести DTO из `Contracts`;
- установить naming policy;
- добавить API snapshot;
- запретить foreign `Internal`.

Пример snapshot generation:

```bash
vendor/bin/roave-backward-compatibility-check
```

либо собственный PHP reflection script для monorepo до появления tagged releases.

## Этап 3: Core reduction

Первыми вывести:

1. Departments.
2. Views.
3. Settings.
4. PubSub.
5. Tenant storage.
6. Audit retention commands.

Не обязательно сразу создавать шесть новых public packages. Некоторые области могут принадлежать уже существующим `panels`, `tenancy` или `modules`.

## Этап 4: Attributes v1

Порядок:

1. Переименовать `FieldCatalogue`.
2. Сделать schema mandatory.
3. Ввести typed patch operations.
4. Разделить missing и explicit null.
5. Вынести storage table descriptor.
6. Переименовать JSONB driver в document driver.
7. Добавить common driver contract suite.
8. Ввести type registry.
9. Добавить CRM consumer.
10. Добавить CMS consumer.

Не реализовывать inline driver, пока он не нужен реальному продукту. Но public contracts не должны мешать его добавлению.

## Этап 5: Transfer rename

Порядок:

1. Rename package.
2. Rename namespace.
3. Перенести DTO из `Support`.
4. Зафиксировать Transfer terminology.
5. Обновить plans.
6. Обновить root Composer dependency.
7. Проверить package discovery.
8. Добавить migration note.

## Этап 6: Transfer simplification

Порядок:

1. Разделить claim и processing.
2. Убрать source read из locked transaction.
3. Разделить local и remote target.
4. Ввести error taxonomy.
5. Удалить `catch (Throwable)` на row level.
6. Исправить preview lifecycle.
7. Централизовать operation key.
8. Добавить crash-point tests.
9. Оставить remote reconciliation на v2.

## Этап 7: Search consolidation

Порядок:

1. Создать `SearchManager`.
2. Переименовать `SearchProvider` в `SearchEngine`.
3. Перенести PostgreSQL driver.
4. Перенести Meilisearch driver.
5. Добавить optional dependency check.
6. Удалить competing bindings.
7. Унифицировать contract tests.
8. Переместить config.
9. Удалить старый package.
10. Проверить install без Meilisearch SDK.

## Этап 8: Intl integration

Порядок:

1. Классифицировать classes `intl-search`.
2. Saved view labels перенести в panels/views.
3. Search projection contract оставить в search.
4. Product-specific localization перенести в CRM/CMS.
5. Удалить provider/config/package.
6. Удалить root dependency.
7. Добавить integration tests в product packages.

## Этап 9: CMS vertical slice

Минимальный slice:

```text
ContentType
ContentItem
ContentVersion
PublicationState
Slug
Attributes
Media
Search
Transfer
Authorization
Audit
Localization
```

Проверки:

- ContentItem не импортирует CRM namespaces.
- Search не требует Eloquent trait.
- Attributes не знает таблицу content items.
- Transfer работает через CMS target adapter.
- Localized projection не требует `intl-search`.
- Media path заменяется через contract/config.

## Этап 10: Release qualification

Matrix:

```text
PHP: 8.3, 8.4, 8.5
Laravel: 12, 13
Dependencies: lowest, locked, latest
Database: PostgreSQL primary
Runtime: CLI, queue worker, Octane-like reused container
Install: source, dist, no-dev
Cache: config cache, route cache
```

Spatie package skeleton использует Testbench, Pest, PHPStan и Pint как стандартную основу package quality. [github](https://github.com/spatie/package-skeleton-laravel/blob/main/composer.json)

***

# Тестовая стратегия

## Уровни тестов

### Unit

- value objects;
- state transitions;
- schema hashing;
- filter compilers;
- operation keys;
- validation.

### Contract

- все AttributeStore drivers;
- все SearchEngine drivers;
- все TransferSource implementations;
- все local targets;
- storage repositories.

### Integration

- PostgreSQL transactions;
- Meilisearch;
- queue after commit;
- migration installation;
- config cache;
- package discovery.

### Architecture

- dependency direction;
- no foreign Internal;
- no Facades in domain;
- no Eloquent models in contracts;
- no product dependency from platform;
- no mutable static registries.

### Product slices

- CRM contact custom field → search → export.
- CMS content localization → search → import.
- Tenant restore → old jobs rejected.
- Deactivated module → fields/search documents unavailable.

## Crash-point tests

Transfer:

```text
before claim commit
after claim commit
after source read
before local target commit
after target commit but before row result
after row result but before checkpoint
after checkpoint
during completion
```

Attributes:

```text
before document lock
after value update
before write-token insert
after write-token insert
deadlock retry
same key/same payload
same key/different payload
```

Search:

```text
record changed after indexing
permission revoked after indexing
document deleted before hydration
Meilisearch timeout
index schema mismatch
cursor tampering
```

***

# Observability

## Structured context

Каждая operation должна логировать:

```text
operation_id
tenant_id
actor_id
package
capability
entity
record_id
job_id
claim_epoch
schema_version
driver
attempt
outcome
duration_ms
```

Не логировать:

- секреты;
- raw API keys;
- полные импортируемые строки;
- персональные данные без необходимости;
- произвольные attribute values.

## Metrics

Attributes:

```text
corex_attribute_reads_total
corex_attribute_writes_total
corex_attribute_conflicts_total
corex_attribute_replays_total
corex_attribute_validation_failures_total
```

Transfer:

```text
corex_transfer_rows_total
corex_transfer_invalid_rows_total
corex_transfer_unknown_outcomes_total
corex_transfer_checkpoint_lag
corex_transfer_step_duration_seconds
```

Search:

```text
corex_search_requests_total
corex_search_candidates_total
corex_search_hydration_misses_total
corex_search_policy_filtered_total
corex_search_driver_failures_total
```

***

# Security checklist

## Attributes

- Table/connection names только из trusted configuration.
- Field handles валидируются.
- Attribute type handler не выполняет arbitrary class.
- JSON depth и payload size ограничены.
- Query filters компилируются из AST.
- Archived/inactive fields не записываются.
- Schema version проверяется при write.
- Authorization выполняется для текущего actor/context.

## Transfer

- Source size ограничен.
- Decompression ratio ограничен.
- CSV formula injection учитывается при export.
- Encoding определяется явно.
- MIME type не является единственным доказательством формата.
- Remote URLs используют allowlist.
- SSRF запрещён.
- Temporary files tenant-isolated.
- Errors не раскрывают secrets.
- Resume разрешён только для неизменившегося source.
- Unknown remote outcome требует reconciliation.

## Search

- External engine получает минимально необходимые данные.
- ACL не считается окончательно применённым в индексе.
- Tenant filter добавляется server-side.
- Raw filter expressions запрещены.
- Cursor подписывается.
- Cursor содержит driver/schema/version binding.
- Index aliases нельзя выбирать из request.
- Search highlights санитизируются перед HTML.

***

# Финальные архитектурные решения

## Принять сейчас

1. Переименовать `corex/import-export` в `corex/transfer`.
2. Ввести `SearchManager`.
3. Перенести Meilisearch в `corex/search` как optional driver.
4. Удалить `corex/intl-search`.
5. Переместить saved-view localization в views/panels.
6. Сделать Attributes schema validation обязательной.
7. Ввести typed `SetAttribute` и `RemoveAttribute`.
8. Разделить sidecar document, EAV и inline JSON drivers.
9. Вынести package table names в immutable descriptor.
10. Прекратить создание новых glue packages.
11. Провести public API census.
12. Начать уменьшение `corex/core`.
13. Разделить plans на ADR, invariants, delivery и evidence.
14. Проверить contracts двумя consumers: CRM и CMS.

## Отложить

- Generic remote target framework.
- Exactly-once-like external transfer.
- Generic outbox для всех packages.
- DDL attribute driver.
- Универсальный workflow для любой domain entity.
- Отдельные packages для каждого search/storage provider.
- Frozen API до CRM+CMS validation.
- Сложный index swap state machine без production consumer.
- Самостоятельный integration package на каждую комбинацию.

## Запретить

- Payload-selected connection/table.
- External calls внутри retryable DB transaction.
- `catch (Throwable)` с превращением любой ошибки в invalid row.
- Nullable dependency, отключающую correctness.
- Domain policy в generic Core config.
- Прямые imports чужого `Internal`.
- Product dependencies из Foundation/Platform.
- Несколько service providers, конкурирующих за один binding.
- Events как скрытую замену обязательному command.
- Silent fallback при некорректной конфигурации.
- Новые публичные contracts без двух реальных consumers.

***

# Целевая структура репозитория

```text
corex/
├── composer.json
├── packages/
│   ├── core/
│   ├── modules/
│   ├── tenancy/
│   ├── auth/
│   ├── attributes/
│   ├── search/
│   ├── transfer/
│   ├── intl/
│   ├── media/
│   ├── workflow/
│   ├── tasks/
│   ├── panels/
│   ├── notifications/
│   ├── api/
│   └── cms/
├── products/
│   └── crm/
├── plans/
│   ├── charter/
│   ├── adr/
│   ├── capabilities/
│   ├── products/
│   ├── releases/
│   └── evidence/
├── tests/
│   ├── Architecture/
│   ├── Distribution/
│   ├── Fixtures/
│   └── Products/
└── scripts/
    ├── check-package-graph.php
    ├── dump-public-api.php
    ├── verify-composer-boundaries.php
    └── verify-migrations.php
```

CRM уже использует vendor name `flex/crm`, тогда как platform packages используют `corex/*` . Это полезное концептуальное разделение: `CoreX` может оставаться платформой, а `Flex` — продуктовой линейкой.

***

# Порядок ближайших PR

## PR 1: Architecture charter

Только документация и tests:

- platform layers;
- dependency direction;
- package criteria;
- public API policy;
- ADR templates;
- architecture tests.

## PR 2: Transfer rename

- directory;
- Composer package;
- namespaces;
- provider;
- tests;
- root dependency;
- plans links.

## PR 3: Search manager

- новый contract;
- manager;
- database driver;
- config validation;
- compatibility adapter для старого interface при необходимости.

## PR 4: Meilisearch merge

- перенос implementation;
- optional SDK;
- contract tests;
- удаление provider collision;
- удаление package.

## PR 5: Intl-search removal

- классификация classes;
- перенос saved-view labels;
- перенос localization contributors;
- удаление package/config/provider.

## PR 6: Attributes semantics

- schema repository;
- обязательная validation;
- patch operations;
- presence type;
- contract tests.

## PR 7: Attributes storage

- table descriptor;
- rename JSONB → document;
- EAV contract parity;
- trusted inline resolver interface без реализации либо с реальным consumer.

## PR 8: Transfer runner split

- claim;
- apply;
- checkpoint;
- error taxonomy;
- transaction reduction;
- crash tests.

## PR 9: Core reduction

- Departments;
- Views;
- Settings;
- PubSub;
- tenancy contracts;
- migration compatibility.

## PR 10: CMS vertical slice

Проверка универсальности готовых contracts на втором продукте.

***

# Definition of Done

CoreX можно считать готовым к `1.0.0`, когда:

- `corex/core` не содержит CRM/CMS/UI-specific concepts;
- package graph ацикличен;
- каждый package загружается независимо;
- public API зафиксирован;
- чужой `Internal` нельзя импортировать;
- Attributes проходят общий contract suite на двух drivers;
- missing/null/remove однозначны;
- table и connection metadata не приходят из payload;
- Transfer не выполняет external side effects внутри retryable transaction;
- Search использует один manager;
- Meilisearch не требует отдельного CoreX package;
- Intl не требует Search;
- Search не требует Intl;
- CRM и CMS используют одни и те же platform contracts;
- все заявленные PHP/Laravel versions проходят CI;
- `prefer-lowest` проходит;
- config/route cache проходят;
- queue serialization проходит;
- scoped state не протекает между jobs/requests;
- upgrade migrations проверены с предыдущего release candidate;
- plans описывают invariants и решения, а не замораживают случайные implementation details.

Главная проверка универсальности должна быть практической:

> Контракт принадлежит CoreX только тогда, когда CRM и CMS используют его без product-specific условий, скрытых соглашений и доступа к внутренним классам.