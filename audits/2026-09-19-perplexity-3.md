# CoreX: архитектурный аудит, итерация 2

**Репозиторий:** [`axiomasoft/corex`](https://github.com/axiomasoft/corex)  
**Проверенная ревизия:** [`1696cb3ede6db7f27a5950383c981ad1ebe8b3dc`](https://github.com/axiomasoft/corex/commit/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc)  
**Базовый документ:** [`audits/2026-09-17-perplexity-2.md`](https://github.com/axiomasoft/corex/blob/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/audits/2026-09-17-perplexity-2.md)  
**Назначение:** дополнение к предыдущему аудиту, а не его замена.

> В этой итерации не следует удалять рекомендации предыдущего аудита. Нужно объединить оба документа, отметить уже исправленные пункты и добавить приведённые ниже гипотезы, архитектурные решения и задачи для Claude.

## Статус ревизии

После первого аудита в CoreX произведена большая серия исправлений. В частности, commit `769365c` сообщает о rebaseline границ пакетов, переименованиях `transfer` и `search-intl`, package-graph gate, расширении тестового контура и переработке `DataScope`; последующие commits закрывают CI и publication gates.[История изменений](https://github.com/axiomasoft/corex/commits/main)

Исправлены или существенно улучшены:

- undeclared package dependencies;
- `import-export` переименован в `transfer`;
- `intl-search` переименован в `search-intl`;
- `DataScope` больше не содержит authorization closure;
- root `test:packages` перечисляет все основные пакеты;
- добавлен package graph gate;
- Meilisearch остаётся отдельным optional adapter;
- расширены package-local и hosted CI-проверки;
- внешние аудиты интегрированы в фазу P12;
- выполнена значительная часть первоначального architecture rebaseline.
- в Attributes имена таблиц инкапсулированы в `AttributeTables` и задаются через конфигурацию, а не захардкожены в store;
- в Tenancy усилены защитные механизмы: template integrity, impersonation write guards, workspace resolution, lifecycle FSM, OIDC, queue cleanup, explicit tenant storage;
- в репозитории появились архитектурные PHPStan rules, contract tests, concurrency tests и upgrade-safety tests.

Параллельная ревизия того же периода не претендовала на построчный проход всего дерева (порядка 1 200+ production PHP-файлов); исчерпывающий per-file verdict здесь по-прежнему опирается на машинную инвентаризацию из раздела «Задания Claude», а не на ручной обход каждого файла.

Тем не менее исправление конкретных дефектов не завершает архитектурную работу. Следующая задача — перейти от «набор пакетов успешно работает» к **сознательно спроектированной платформе**, где понятны:

- bounded contexts;
- направления зависимостей;
- ownership данных;
- public API;
- модель расширений;
- устойчивость contracts;
- единый язык имён;
- правила группировки классов;
- механизм конфигурации;
- жизненный цикл integrations;
- distinction между framework adapter и domain code.

## Главный вывод

CoreX больше не выглядит архитектурно сломанным прототипом. Сейчас это масштабная платформенная кодовая база с хорошими механизмами безопасности, идемпотентности, package isolation и delivery validation.

Главный новый риск — **локальная сложность внутри уже изолированных пакетов**.

Наиболее показательны:

- [`packages/core/src`](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/core/src) содержит Audit, Departments, Features, Money, PubSub, Settings, Storage, Tenancy и Views;
- [`packages/tenancy/src`](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/tenancy/src) сочетает vertical domain folders с горизонтальными `Actions`, `Commands`, `Contracts`, `Events`, `Jobs`, `Listeners`, `Models`, `Resolvers` и `Scopes`;
- [`TenancyServiceProvider`](https://github.com/axiomasoft/corex/blob/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/tenancy/src/TenancyServiceProvider.php) имеет размер около 27 KB;
- [`ModulesServiceProvider`](https://github.com/axiomasoft/corex/blob/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/modules/src/ModulesServiceProvider.php) имеет размер около 17 KB;
- в Attributes, Search, Transfer, Panels и Wakeup всё ещё активно используется универсальный каталог `Internal`;
- поиск `config()` на текущей ревизии возвращает более 100 совпадений, включая repositories, middleware, lifecycle services, authorization code и storage implementations.[Поиск `config()`](https://github.com/search?q=repo%3Aaxiomasoft%2Fcorex+path%3Apackages+config%28&type=code)
- [`DataScope`](https://github.com/axiomasoft/corex/blob/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/core/src/Support/Data/DataScope.php) на проверенной ревизии всё ещё может нести объект `TenantContext`, что нежелательно для queue payload, inbox/outbox, workflow history и других durable boundaries (см. раздел ниже).

Следующий rebaseline должен концентрироваться не на переносе пакетов ради переноса, а на четырёх вопросах:

1. Где проходит настоящая доменная граница?
2. Что является публичным extension point?
3. Какие детали являются Laravel/infrastructure adapters?
4. Может ли разработчик найти нужный класс по его назначению, не используя глобальный поиск?

# Приоритеты второй итерации

| Приоритет | Задача | Причина |
|---|---|---|
| P0 | Зафиксировать архитектурную конституцию | Иначе последующие улучшения останутся локальными и противоречивыми |
| P0 | Выбрать единую стратегию группировки больших пакетов | Tenancy уже смешивает domain-first и layer-first |
| P0 | Зафиксировать public API каждого пакета | Namespace `Internal` сам по себе ничего не защищает |
| P0 | Разделить `core` на внутренние bounded contexts | Сейчас `core` остаётся центром притяжения несвязанных возможностей |
| P1 | Декомпозировать крупные service providers | Они стали самостоятельными composition applications |
| P1 | Заменить runtime `config()` typed options | Глобальная конфигурация остаётся скрытой зависимостью |
| P1 | Отвязать Attributes definitions от Modules | Storage стал гибким, definition source ещё недостаточно независим |
| P1 | Нормализовать naming vocabulary | Существуют `Dep`, `Data`, `Internal`, `Support`, `Manager`, `Service` с разной семантикой |
| P1 | Формализовать bridge/integration policy | Уже существуют `search-intl`, `search-meilisearch`, `tasks-workflow` |
| P1 | Разделить transport, application и domain API | Особенно в `api`, `tenancy`, `workflow` и `transfer` |
| P2 | Рассмотреть переименование некоторых пакетов | `api`, `panels`, `modules`, `wakeup`, возможно `auth` остаются неоднозначными |
| P2 | Ввести package architecture scorecard | Нужна измеримая оценка качества каждого пакета |
| P2 | Добавить reference CMS consumer | Универсальность всё ещё в основном доказывает CRM |
| P2 | Ввести extension compatibility tests | Недостаточно проверить встроенные adapters |
| P3 | Оптимизировать структуру после появления стабильных patterns | Физические перемещения не должны предшествовать semantic decisions |

# Архитектурная конституция

Перед дальнейшим масштабным рефакторингом следует создать `docs/architecture/constitution.md`.

Это должен быть короткий нормативный документ. Он важнее подробных ADR, потому что задаёт правила, по которым принимаются все дальнейшие решения.

## Предлагаемые статьи

### Границы пакета

- Каждый пакет имеет одну сформулированную ответственность.
- Пакет должен быть полезен без знания внутреннего устройства потребителя.
- Capability не зависит от product package.
- Kernel не зависит от capability, integration или product.
- Integration может зависеть только от публичных APIs соединяемых пакетов.
- Package не импортирует чужой `Internal`, `Infrastructure` или concrete repository.
- Composer manifest обязан объявлять каждую фактическую межпакетную зависимость.
- Отсутствие optional integration не должно нарушать bootstrap приложения.

### Владение данными

- Каждая таблица имеет одного owning package.
- Package не обновляет чужие таблицы напрямую.
- Cross-package write выполняется через command/contract.
- Cross-package read выполняется через contract, query service или replicated projection.
- Search index, cache и read model никогда не являются source of truth.
- Integration package не владеет предметными данными соединяемых capabilities.
- Integration state допускается только для checkpoint, cursor, delivery, mapping и idempotency.

### Public API

- Public API явно перечислен.
- Public contract не экспортирует internal DTO в сигнатуре.
- Concrete SQL repository не является public API по умолчанию.
- Laravel service provider может быть public framework entry point, но не domain contract.
- Каждый public symbol либо documented, либо отмечен как experimental.
- `@internal` контролируется статическим анализом.
- Breaking change определяется по API surface, а не по намерениям автора.

### Runtime

- Нет глобального mutable current tenant, current actor или current locale.
- Контекст либо передаётся явно, либо хранится в scoped/resettable service.
- Command, event и workflow payload сериализуемы.
- Closure, container instance, Eloquent model и request object не пересекают durable boundary.
- External I/O не выполняется внутри domain transaction без outbox.
- Queue handlers и workflow activities идемпотентны.
- Любое retryable действие имеет operation identifier.
- Long-running worker очищает contextual state после каждой unit of work.

### Конфигурация

- Laravel `config()` используется только в composition/bootstrap boundary.
- Внутренние сервисы получают typed immutable options.
- Invalid configuration обнаруживается при boot или явной validation command.
- Секреты отделены от обычной конфигурации.
- Настройка, изменяемая во время выполнения, не должна маскироваться под Laravel config.
- Domain policy не кодируется произвольным array config без schema.

### Расширение

- Extension point является явным contract/registry/driver manager.
- Extension не требует fork базового пакета.
- Приоритет и порядок extensions детерминированы.
- Duplicate extension identifier вызывает ошибку.
- Extension compatibility проверяется contract suite.
- Extension lifecycle определён: register, validate, activate, execute, deactivate.
- Удаление extension не оставляет систему в неопределённом состоянии.

# Таксономия пакетов

Рекомендуется закрепить не только каталоги, но и semantic kind каждого пакета.

| Вид | Назначение | Может зависеть от |
|---|---|---|
| Foundation | Минимальные технические primitives | PHP, узкий набор framework contracts |
| Capability | Независимая возможность платформы | Foundation, собственные required libraries |
| Integration | Связывает capabilities или external driver | Public API соединяемых пакетов |
| Product | CRM, CMS, LMS | Capabilities и integrations |
| Tooling | PHPStan, generators, dev utilities | Не участвует в production runtime |
| Fixture | Проверяет реальную композицию | Опубликованные packages |

## Возможная классификация

| Пакет | Тип |
|---|---|
| `core` | Foundation, но сейчас содержит несколько capabilities |
| `modules` | Foundation или platform capability |
| `attributes` | Capability |
| `auth` | Capability |
| `media` | Capability |
| `notifications` | Capability |
| `recurrence` | Capability |
| `search` | Capability |
| `tasks` | Capability |
| `tenancy` | Capability или platform subsystem |
| `transfer` | Capability |
| `wakeup` | Capability |
| `workflow` | Capability |
| `panels` | Capability, если semantics подтверждена |
| `api` | Transport/application gateway |
| `intl` | Несколько portable capabilities под одним package |
| `search-intl` | Integration |
| `search-meilisearch` | External adapter |
| `tasks-workflow` | Integration |
| `crm` | Product |

Физически переносить каталоги в `packages/capabilities` и `packages/integrations` необязательно. Composer path repository и CI станут сложнее, а практическая ценность невелика. Достаточно manifest metadata:

```json
{
    "extra": {
        "corex": {
            "package-type": "capability",
            "stability": "experimental",
            "public-api": "api/public.php"
        }
    }
}
```

Package graph gate может проверять разрешённые направления зависимостей по `package-type`.

# Структура кода

## Не выбирать один шаблон

Унификация не означает, что все пакеты должны иметь одинаковые каталоги.

Плохая унификация:

```text
Domain/
Application/
Infrastructure/
Presentation/
```

в пакете из восьми классов.

Ещё хуже:

```text
Contracts/
Data/
Enums/
Events/
Exceptions/
Internal/
Support/
```

в пакете из двухсот классов, где классы одного use case распределены по семи папкам.

Нужны три профиля.

## Профиль A: компактный пакет

Для пакета с одним контекстом и примерно до 30–40 production classes:

```text
src/
  Contracts/
  Data/
  Exceptions/
  Laravel/
```

Пример кандидатов:

- Recurrence;
- Search Meilisearch;
- Search Intl;
- Panels, если scope останется узким;
- Wakeup, если он действительно только scheduler abstraction.

## Профиль B: capability

Для пакета со стабильными подфункциями:

```text
src/
  Schema/
  Query/
  Execution/
  Persistence/
  Integration/
  Laravel/
```

Подходит:

- Attributes;
- Search;
- Transfer;
- Notifications;
- Workflow;
- Media.

Главная ось — предметные подсистемы capability, а не тип PHP-класса.

## Профиль C: bounded contexts

Для Tenancy, Modules, CRM и будущей CMS:

```text
src/
  Membership/
  Provisioning/
  Identity/
  DomainTrust/
  Impersonation/
  Workspace/
  Shared/
  Laravel/
```

Внутри крупного bounded context:

```text
Membership/
  Domain/
  Application/
  Infrastructure/
  Http/
```

Однако создавать полный набор слоёв следует только там, где они реально существуют.

## Предлагаемое правило

- До 40 классов: layer/role-first.
- От 40 до 100: feature-first с 2–5 крупными features.
- Более 100: bounded-context-first.
- Если изменение одного use case требует правки в четырёх верхнеуровневых папках, структура выбрана неправильно.
- Если верхний каталог содержит более 20 непосредственных детей, требуется дополнительная группировка.
- Если каталог содержит только один класс и не выражает важную границу, каталог, вероятно, лишний.
- Если имя каталога не позволяет предсказать классы внутри, каталог следует переименовать.

# Core

Текущий [`packages/core/src`](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/core/src) содержит:

```text
Audit
Concerns
Contracts
Data
Departments
Enums
Events
Exceptions
Features
Models
Money
PubSub
Settings
Storage
Support
Tenancy
Testing
Views
```

Это сильный сигнал, что `core` фактически является не foundation, а **platform-common monolith**.

## Что допустимо оставить

- identifier primitives;
- clock;
- execution context primitives;
- generic idempotency primitives;
- transaction boundary contracts;
- result/error primitives;
- tiny serialization primitives;
- narrow Laravel-independent contracts.

## Что следует рассмотреть отдельно

| Текущий namespace | Возможный владелец |
|---|---|
| `Audit` | `corex/audit` |
| `Departments` | CRM, organization или directory package |
| `Features` | `corex/features` или Modules |
| `Money` | `corex/money` либо `intl` |
| `PubSub` | `corex/messaging` |
| `Settings` | `corex/settings` |
| `Storage` | `corex/storage` |
| `Tenancy` | `corex/tenancy` contracts |
| `Views` | `corex/views`, `corex/saved-views` или Panels |
| `Testing` | отдельный dev package либо `tests/Support` |

Не нужно немедленно публиковать десять пакетов. Сначала следует создать внутренние dependency zones и architecture tests.

Пример:

```text
CoreX\Core\Foundation
CoreX\Core\Messaging
CoreX\Core\Settings
CoreX\Core\Audit
CoreX\Core\SavedViews
```

После стабилизации станет видно, какие зоны заслуживают отдельный release lifecycle.

## Core Litmus Test

Класс должен находиться в `core`, только если:

1. Он нужен минимум трём независимым capabilities.
2. Он не использует terminology одной capability.
3. Он не требует Eloquent, HTTP или Laravel application lifecycle.
4. Его изменение не должно происходить ради одного продукта.
5. У него малый и стабильный API.
6. Его нельзя естественно разместить у владельца предметного поведения.

Если хотя бы два пункта не выполняются, класс не относится к foundation.

## DataScope: скалярный контекст и codec

Удаление authorization closure из `DataScope` — необходимый, но недостаточный шаг. Пока в durable scope встроен `TenantContext`, сериализация остаётся неявной: граница пересекает container-shaped object, а не версионированный набор скаляров.

Целевая форма:

```php
final readonly class DataScope
{
    public function __construct(
        public string $tenantId,
        public ?string $workspaceId,
        public string $connectionId,
        public string $actorId,
        public string $restoreEpoch,
        public int $version = 1,
    ) {}
}
```

Явный codec на границе persistence/transport:

```php
interface DataScopeCodec
{
    /** @return array<string, scalar|null> */
    public function encode(DataScope $scope): array;

    /** @param array<string, mixed> $payload */
    public function decode(array $payload): DataScope;
}
```

Восстановление runtime-контекста — отдельная ответственность, не часть payload:

```php
interface TenantContextResolver
{
    public function resolve(DataScope $scope): TenantContext;
}
```

Это одно из четырёх исправлений с наибольшей отдачей наряду с отвязкой Attributes definitions от Modules, декомпозицией `TenancyServiceProvider` и единой domain-first namespace policy.

# Tenancy

Tenancy остаётся наиболее сложным и рискованным пакетом.

Сейчас верхний уровень одновременно содержит domain-first namespaces:

- `DomainTrust`;
- `Identity`;
- `Impersonation`;
- `Lifecycle`;
- `Membership`;
- `Oidc`;
- `Provisioning`;
- `Storage`;
- `Wakeup`;
- `Workspaces`;

и horizontal buckets:

- `Actions`;
- `Commands`;
- `Contracts`;
- `Events`;
- `Jobs`;
- `Listeners`;
- `Models`;
- `Resolvers`;
- `Scopes`.

[Текущая структура Tenancy](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/tenancy/src)

## Проблема смешанной оси

Предположим, разработчик исправляет membership expiration. Связанные классы могут находиться в:

```text
Membership/Internal/Expiry
Commands
Events
Jobs
Listeners
Models
Http/Middleware
```

Структура не образует локальный feature neighbourhood.

Внутри каждого bounded context каталог `Internal` обычно лишний: граница Composer package уже обозначает implementation detail; публичность фиксируют `@api` / `@internal`, namespace policy и BC-checker, а не дополнительный уровень папок с непрозрачным именем.

## Целевая структура

```text
src/
  Account/
    Domain/
    Application/
    Persistence/

  Membership/
    Domain/
    Application/
    Credentials/
    Expiration/
    Projection/
    Persistence/
    Transport/

  Provisioning/
    Application/
    Database/
    Templates/
    Jobs/
    Persistence/

  Identity/
    Domain/
    Synchronization/
    Persistence/

  Authentication/
    Oidc/
    Sessions/
    Http/

  Impersonation/
    Domain/
    Application/
    Persistence/
    Http/

  DomainTrust/
    Domain/
    Verification/
    Persistence/
    Http/

  Workspace/
    Domain/
    Resolution/
    Persistence/
    Http/

  Runtime/
    Bootstrappers/
    TenantContext/
    ConnectionSwitching/
    WorkerReset/

  Laravel/
    Providers/
    Middleware/
    Commands/
    Listeners/
    Migrations/
```

## Уточнение по OIDC

OIDC не является bounded context платформы. Это authentication protocol adapter.

Вместо:

```text
Tenancy/Oidc
```

лучше:

```text
Tenancy/Authentication/Oidc
```

или отдельный package:

```text
corex/tenancy-oidc
```

Выделять package следует, если OIDC тянет заметные dependencies, имеет самостоятельный release lifecycle или не нужен большинству installations.

## Wakeup внутри Tenancy

`Tenancy/Wakeup` указывает на интеграцию capability Tenancy с capability Wakeup. Такой код лучше трактовать как integration boundary:

```text
CoreX\Tenancy\Integration\Wakeup
```

Либо вынести в `corex/tenancy-wakeup`, если зависимость optional.

Основной вопрос:

- Wakeup инициирует tenancy operation?
- Tenancy предоставляет `DueSource`?
- Или Tenancy непосредственно управляет scheduler?

Владелец extension point должен стоять первым в package naming.

## Service provider

[`TenancyServiceProvider`](https://github.com/axiomasoft/corex/blob/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/tenancy/src/TenancyServiceProvider.php) следует декомпозировать не просто по размеру, а по composition modules.

```text
Laravel/
  TenancyServiceProvider.php
  Providers/
    RuntimeProvider.php
    DatabaseProvider.php
    ProvisioningProvider.php
    MembershipProvider.php
    IdentityProvider.php
    WorkspaceProvider.php
    DomainTrustProvider.php
    ImpersonationProvider.php
    AuthenticationProvider.php
    ConsoleProvider.php
    EventProvider.php
```

Главный provider должен только:

- регистрировать typed options;
- включать composition modules;
- публиковать package resources;
- контролировать optional integrations.

Пример:

```php
final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            TenancyOptions::class,
            fn (Application $app): TenancyOptions =>
                TenancyOptions::fromRepository($app['config']),
        );

        foreach ($this->providers() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    private function providers(): array
    {
        return [
            RuntimeProvider::class,
            DatabaseProvider::class,
            MembershipProvider::class,
            WorkspaceProvider::class,
            ProvisioningProvider::class,
        ];
    }
}
```

Не следует создавать provider на каждые два bindings. Граница оправдана, если composition unit можно включить, протестировать или заменить независимо.

# Modules

Текущий пакет содержит хорошие semantic namespaces:

```text
Compiler
Discovery
Extend
Gate
Lifecycle
Registry
Settings
```

Но public root остаётся непоследовательным:

```text
CorexModule.php
Dep.php
GateDecision.php
Manifest.php
ModuleContext.php
ModuleStatus.php
SettingDefault.php
```

[Структура Modules](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/modules/src)

## Рекомендованные переименования

| Сейчас | Предлагается |
|---|---|
| `Dep` | `ModuleDependency` |
| `CorexModule` | `ModuleDefinition` или `CorexModuleDefinition` |
| `Manifest` | `ModuleManifest` |
| `GateDecision` | `ModuleActivationDecision` |
| `ModuleStatus` | оставить, если это runtime status |
| `ModuleContext` | `ModuleRuntimeContext`, если содержит runtime state |
| `SettingDefault` | `DefaultSettingDefinition` |
| `Extend` | `Extension` или `Extensions` |
| `Gate` | `Activation` или `Eligibility`, если это смысл gate |
| `ModulesServiceProvider` | оставить как package provider |
| `WhyCommand` | `ExplainModuleCommand` (понятно вне CLI alias) |
| `Modules` (package semantic) | `ModuleKernel` / `Extensions` только при смене модели; иначе оставить `modules` |

Поля value object dependency declaration лучше не оставлять абстрактными:

| Сейчас | Предлагается | Причина |
|---|---|---|
| `$type` в `Dep` | `$kind` | Discriminator, а не PHP/domain type |
| `$target` | `$package` или `$name` | `target` слишком абстрактно |

`Dep` особенно нежелателен:

- плохо ищется;
- неочевиден вне локального контекста;
- не образует единый словарь;
- выглядит как техническое сокращение;
- неудобен для документации и IDE autocomplete.

## Modules или Extensions

Возможны три semantic модели.

### Modules

Подходит, если объект описывает:

- installable platform feature;
- dependencies;
- lifecycle;
- activation;
- manifests;
- settings;
- migrations;
- contributions.

### Extensions

Подходит, если главная задача — подключение third-party поведения к extension points.

### Plugins

Подходит для CMS-facing ecosystem с UI, lifecycle, vendor identity и marketplace.

Для CoreX рекомендуется:

- оставить package `modules`;
- назвать базовую декларацию `ModuleDefinition`;
- использовать `Extension` только для вкладов module в конкретные registries;
- будущие CMS plugins строить поверх Modules, но не делать `Plugin` синонимом `Module`.

То есть:

```text
Module = устанавливаемая функциональная единица.
Extension = конкретный вклад модуля в extension point.
Plugin = distribution/product-level форма для CMS ecosystem.
```

# Attributes

Attributes значительно улучшен, но структура всё ещё показывает незавершённый переход:

```text
Contracts
Data
Exceptions
Internal
Support
```

[Структура Attributes](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/attributes/src)

## Главный оставшийся вопрос

Attributes должен быть:

1. dynamic-field engine;
2. schema registry;
3. value storage;
4. filtering engine;
5. indexing integration;
6. UI metadata source;
7. migration engine.

Если package пытается владеть всем одновременно, он станет вторым `core`.

Нужно разделить внутренние подсистемы.

```text
src/
  Schema/
    Contracts/
    Definition/
    Type/
    Versioning/
    Migration/

  Values/
    Contracts/
    Data/
    Validation/
    Mutation/

  Query/
    Filter/
    Planning/
    Execution/

  Storage/
    Contracts/
    JsonDocument/
    Eav/
    Database/

  Integration/
    Modules/
    Search/
    Transfer/

  Laravel/
```

## Definition source

`AttributesServiceProvider` сегодня собирает `RegistryFieldCatalogue` только при наличии `EntityRegistry` и `ModuleRegistry`. Универсальность storage уже выше, чем универсальность definition source: без module system catalogue может не появиться, хотя EAV/JSONB drivers уже подменяемы.

Базовый Attributes не должен требовать ModuleRegistry.

```php
interface AttributeDefinitionSource
{
    public function definitionsFor(
        SubjectType $subject,
        SchemaRevision $revision,
    ): AttributeSchema;
}
```

Возможные реализации:

```text
StaticAttributeDefinitionSource
DatabaseAttributeDefinitionSource
ModuleAttributeDefinitionSource
CompositeAttributeDefinitionSource
CachedAttributeDefinitionSource
```

Адаптер modules (имя класса лучше привести к `ModuleFieldCatalogue` вместо `RegistryFieldCatalogue`, чтобы источник definitions читался из имени):

```php
final class ModuleAttributeDefinitionSource implements AttributeDefinitionSource
{
    public function __construct(
        private EntityRegistry $entities,
        private ModuleRegistry $modules,
    ) {}
}
```

Так `corex/attributes` может работать без module system, с definitions из PHP, из БД, из будущей CMS или из внешнего schema registry.

Composite source должен иметь формализованную conflict policy:

- duplicate key;
- duplicate stable field ID;
- type mismatch;
- conflicting required/default/index flags;
- precedence;
- removal/deprecation.

## Subject identity

Использовать string `entity` допустимо, но лучше отделить value objects:

```php
final readonly class SubjectType
{
    public function __construct(public string $value)
    {
        // canonical validation
    }
}

final readonly class SubjectId
{
    public function __construct(public string $value) {}
}

final readonly class SubjectRef
{
    public function __construct(
        public SubjectType $type,
        public SubjectId $id,
    ) {}
}
```

Нельзя автоматически делать subject type равным:

- model class;
- table name;
- morph map alias;
- module class;
- API resource name.

Между ними должны существовать adapters/maps.

## Schema evolution

Dynamic attributes требуют собственного migration protocol.

Рекомендуемые состояния:

```text
draft
active
deprecated
migrating
retired
```

Изменение поля:

```text
propose
validate compatibility
create migration
dual-read
backfill
verify
switch writes
retire old representation
cleanup
```

Для каждого field:

```php
final readonly class AttributeDefinition
{
    public function __construct(
        public AttributeId $id,
        public AttributeKey $key,
        public AttributeType $type,
        public SchemaRevision $introducedIn,
        public ?SchemaRevision $deprecatedIn,
        public AttributeConstraints $constraints,
        public IndexingPolicy $indexing,
        public VisibilityPolicy $visibility,
    ) {}
}
```

`id` стабилен, `key` может переименовываться.

## EAV vs JSONB

Не следует позволять application code знать selected storage engine.

```php
interface AttributeStoreResolver
{
    public function for(SubjectType $subject): AttributeValueStore;
}
```

Selection policy может учитывать:

- subject type;
- expected cardinality;
- filtering requirements;
- analytics;
- unique constraints;
- write frequency;
- tenant scale;
- storage backend.

Однако выбор engine на каждый отдельный field почти наверняка создаст чрезмерную сложность. Более безопасная гранулярность — subject type или attribute set.

## Поисковая интеграция

Attributes не должен напрямую обновлять Meilisearch.

Правильная цепочка:

```text
Attribute mutation
  -> domain/application event
  -> outbox
  -> search projection handler
  -> SearchDocument
  -> SearchProvider
```

Search adapter не должен читать внутренние таблицы Attributes.

# Transfer

Переименование выполнено правильно. `Transfer` — достаточно универсальный и нейтральный термин.

Текущая структура всё ещё минимальна:

```text
Contracts
Data
Internal
TransferServiceProvider
```

[Структура Transfer](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/transfer/src)

## Целевые подсистемы

```text
src/
  Definition/
  Source/
  Sink/
  Mapping/
  Validation/
  Execution/
  Checkpoint/
  Format/
  Reporting/
  Laravel/
```

Не обязательно создавать все папки сейчас. Это semantic map для роста.

## Терминология

| Концепция | Рекомендуемое имя |
|---|---|
| Источник записей | `TransferSource` |
| Получатель | `TransferSink` |
| Преобразование | `RecordTransformer` |
| Сопоставление колонок | `FieldMapping` |
| Исполнитель | `TransferExecutor` |
| Конфигурация | `TransferDefinition` |
| Запуск | `TransferRun` |
| Позиция восстановления | `TransferCheckpoint` |
| Результат batch | `BatchReceipt` |
| Dry-run результат | `TransferPreview` |
| Ошибка конкретной записи | `RecordFailure` |
| Итог | `TransferReport` |

`Runner` допустим, но `Executor` точнее, если объект исполняет definition. `Coordinator` точнее, если он связывает source, mapping, validation, sink и checkpoint.

## Pipeline

Не следует строить полностью generic middleware pipeline раньше времени.

Минимальный контракт:

```php
interface TransferExecutor
{
    public function execute(
        TransferDefinition $definition,
        TransferContext $context,
    ): TransferReport;
}
```

```php
interface TransferSource
{
    public function read(
        TransferCheckpoint $checkpoint,
        BatchSize $size,
    ): TransferBatch;
}
```

```php
interface TransferSink
{
    public function write(
        TransferBatch $batch,
        WriteMode $mode,
    ): BatchReceipt;
}
```

## Обязательные свойства

- resumability;
- deterministic mapping;
- idempotent sink;
- per-record failure policy;
- transaction boundaries;
- dry run;
- progress;
- cancellation;
- schema version;
- source snapshot/revision;
- PII-safe diagnostics;
- backpressure;
- memory limits;
- audit trail.

Не следует смешивать:

- format decoding;
- business mapping;
- validation;
- persistence;
- orchestration.

# Search

Текущий `SearchManager` соответствует Laravel driver model, а Meilisearch остаётся external adapter.[Search](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/search/src) [Meilisearch adapter](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/search-meilisearch/src)

Отдельный Composer package для Meilisearch оправдан: driver регистрируется через `SearchManager::extend()`, SDK и transitive dependencies не тянутся в установки без поискового backend.

### Физическая организация каталогов (альтернатива manifest-only)

В таксономии выше рекомендованы `extra.corex.package-type` без переноса каталогов. Альтернативный вариант — явно развести capability и integration на уровне filesystem:

```text
packages/
  capabilities/
    search/
  integrations/
    search-meilisearch/
```

Смысл тот же, что у отдельного adapter package: потребитель CoreX не обязан ставить Meilisearch SDK. Выбор между metadata-only и физическими `capabilities/` / `integrations/` — организационный; направление зависимостей должно совпадать в обоих случаях.

## Что улучшить

Разделить четыре понятия:

1. Index document.
2. Search query.
3. Search provider.
4. Result hydration.

```php
interface SearchProvider
{
    public function search(SearchQuery $query): SearchResultPage;

    public function upsert(SearchDocumentBatch $documents): IndexReceipt;

    public function delete(DocumentSelection $documents): IndexReceipt;
}
```

```php
interface SearchResultHydrator
{
    public function hydrate(
        SearchHitBatch $hits,
        HydrationContext $context,
    ): HydratedSearchResults;
}
```

Provider возвращает search hits, но не загружает Eloquent models.

## Названия

`MeilisearchQueryAdapter` следует использовать только если класс действительно преобразует CoreX query в Meilisearch query.

Если класс реализует весь provider, имя должно быть:

```text
MeilisearchSearchProvider
```

Если управляет index settings:

```text
MeilisearchIndexConfigurator
```

Если преобразует документы:

```text
MeilisearchDocumentMapper
```

Если выполняет запрос:

```text
MeilisearchQueryExecutor
```

Один класс не должен называться Adapter только потому, что он находится в integration package.

## Search Intl

Переименование `intl-search` в `search-intl` правильно отражает host-first naming.

`search-intl` должен содержать только:

- locale-aware field projection;
- locale expansion;
- fallback strategy adapter;
- translation revision mapping.

Он не должен владеть:

- SQL repository Saved Views;
- общей translation revision model;
- core search schema;
- UI labels;
- locale persistence.

## OpenSearch и database adapter

Для проверки качества abstraction рекомендуется реализовать второй небольшой provider:

- PostgreSQL full-text/trigram;
- in-memory deterministic provider для tests;
- OpenSearch/Elasticsearch prototype.

Не обязательно выпускать их как packages. Второй provider нужен как архитектурный тест: abstraction, созданная только под Meilisearch, почти всегда сохраняет его терминологию.

# API

Название `api` слишком широкое.

Текущий package содержит:

- `ApiController`;
- `Dispatcher`;
- `ErrorBoundary`;
- `OpenApi`;
- `Operation`;
- `OperationRegistry`;
- `RequestMetadata`;
- `ToolServiceProvider`.

[Структура API](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/api/src)

По составу это не просто HTTP API. Это **operation invocation platform**, которая может обслуживать HTTP/OpenAPI и AI tools.

## Возможные имена

| Имя | Когда подходит |
|---|---|
| `operations` | Главный abstraction — зарегистрированная операция |
| `gateway` | Главная роль — единая внешняя точка входа |
| `invocation` | Главная роль — безопасный вызов операций |
| `tools` | Пакет преимущественно для AI/MCP tools |
| `api` | Пакет действительно определяет весь transport API |

Наиболее точный кандидат — `operations`.

Тогда:

```text
corex/operations
CoreX\Operations\Operation
CoreX\Operations\OperationRegistry
CoreX\Operations\OperationDispatcher
CoreX\Operations\Http\OperationController
CoreX\Operations\OpenApi\OpenApiDocument
CoreX\Operations\Tools\ToolProvider
```

Переименование стоит делать только после формулировки package responsibility одной строкой.

## Dispatcher

`Dispatcher` слишком общий root class.

Рекомендуется:

```text
OperationDispatcher
```

То же относится к:

```text
ErrorBoundary -> OperationErrorBoundary
RequestMetadata -> InvocationMetadata
OpenApi -> OpenApiDocumentGenerator
```

# Panels

`Panels` может означать:

- dashboard widgets;
- admin UI panels;
- layout regions;
- contextual sidebars;
- schema-driven presentation;
- saved views.

Публичные contracts `PanelCatalogue` и `PanelProvider` подтверждают registry/provider model, но не раскрывают назначение panel.[Panels contracts](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/panels/src/Contracts)

Перед переименованием нужно написать пять реальных examples:

```text
CRM contact side panel
CMS page inspector panel
Dashboard statistics panel
Settings panel
Workflow task panel
```

Если все examples относятся к composable UI regions, возможны:

- `workspace`;
- `surfaces`;
- `ui-schema`;
- `presentation`;
- `panels`.

`Panels` лучше оставить, если термин является устойчивым Ubiquitous Language продукта. Не стоит выбирать абстрактное красивое имя `Surfaces`, если разработчики будут постоянно объяснять его значение.

Рекомендуемая структура:

```text
Panels/
  Definition/
  Contribution/
  Resolution/
  Visibility/
  Layout/
  Serialization/
  Laravel/
```

# Wakeup

Contracts:

- `DueSource`;
- `WakeupHandler`;
- `WakeupHint`;
- `WakeupScheduler`;
- `WakeupSweeper`.

[Wakeup contracts](https://github.com/axiomasoft/corex/tree/1696cb3ede6db7f27a5950383c981ad1ebe8b3dc/packages/wakeup/src/Contracts)

Название `Wakeup` необычно, но довольно точно выражает durable execution concept: работа должна быть возобновлена в будущем.

Варианты:

| Имя | Проблема |
|---|---|
| `scheduler` | Слишком широко, конфликтует с Laravel Scheduler |
| `timers` | Не покрывает sweep/due source полностью |
| `activation` | Теряет временную семантику |
| `wakeups` | Хорошая предметная семантика, но сейчас package singular |
| `wakeup` | Можно оставить |
| `signals` | Слишком широко |

Рекомендуется оставить `wakeup`, если в документации чётко определено:

> Wakeup — durable hint о том, что некоторую работу следует снова рассмотреть не раньше указанного момента.

Но переименовать слишком общие classes:

```text
DueSource -> DueWakeupSource
WakeupHandler -> DueWakeupHandler
WakeupHint -> WakeupRequest или WakeupScheduleHint
WakeupScheduler -> оставить
WakeupSweeper -> DueWakeupSweeper
```

`Hint` следует оставить только если это действительно best-effort optimization, а не обязательная запись.

# Auth

Нужно определить, охватывает ли package:

- authentication;
- authorization;
- role/permission matrix;
- direct grants;
- record-level access;
- identity;
- session management.

Если преимущественно authorization, имя `auth` слишком неоднозначно.

Варианты:

```text
corex/access
corex/authorization
corex/permissions
```

Предпочтение:

- `access`, если включены scopes, policies, grants и record access;
- `authorization`, если package отвечает только на вопрос «можно ли?»;
- `auth`, если authentication и authorization сознательно объединены.

`RecordRules` лучше заменить на:

```text
RecordAccessPolicy
```

или:

```text
RecordAuthorizationPolicy
```

`Rules` не показывает, возвращает ли класс query constraints, policy decision или validation rules.

# Intl

`intl` потенциально объединяет:

- locales;
- translations;
- country/reference data;
- currencies;
- money formatting;
- phone/address rules;
- date/time zones;
- transliteration.

Это слишком широкий umbrella package, если все подсистемы развиваются независимо.

Рекомендуется провести cohesion audit:

| Подсистема | Нужна Search? | Нужна CRM? | Нужна CMS? | Требует БД? |
|---|---:|---:|---:|---:|
| Locale values | Да | Да | Да | Нет |
| Translation | Да | Да | Да | Возможно |
| Country reference | Нет | Да | Возможно | Возможно |
| Money | Нет | Да | Возможно | Нет |
| Time zone | Возможно | Да | Да | Нет |
| Transliteration | Да | Возможно | Да | Нет |

Не следует автоматически дробить package. Но внутри нужны явные namespaces:

```text
Locale/
Translation/
Currency/
Territory/
TimeZone/
Formatting/
```

`Intl` как package name приемлем, если он остаётся portable utility capability без общего mutable state.

# Service providers

Крупный service provider — это симптом, а не первичная болезнь.

## Проблемные признаки

- более 15–20 bindings;
- несколько independent feature flags;
- чтение десятков config keys;
- conditional branches по installed packages;
- регистрация routes, commands, events и storage в одном методе;
- создание domain objects вручную в длинных closures;
- database/schema checks при boot;
- optional integration wiring;
- repeated string configuration keys.

## Целевая модель

```text
Main package provider
  -> typed options
  -> feature composition providers
  -> integration providers
```

Внутренний feature provider необязательно должен наследовать Laravel `ServiceProvider`. Можно использовать простой registrar:

```php
interface PackageRegistrar
{
    public function register(Application $app): void;
}
```

Это уменьшит Laravel inheritance и упростит unit tests.

```php
final class MembershipRegistrar implements PackageRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(MembershipRepository::class, ...);
        $app->singleton(MembershipAuthorizer::class, ...);
    }
}
```

Главный provider:

```php
foreach ($this->registrars as $registrar) {
    $registrar->register($this->app);
}
```

Laravel providers лучше оставлять там, где нужны package lifecycle hooks.

# Typed configuration

Поиск показывает, что `config()` остаётся в:

- auth access classes;
- role matrix compiler;
- tenancy middleware;
- tenancy lifecycle;
- database template manager;
- media repositories;
- media conversion coordinator;
- core Saved Views repository;
- CRM record rules;
- models и commands.

[Текущие совпадения](https://github.com/search?q=repo%3Aaxiomasoft%2Fcorex+path%3Apackages+config%28&type=code)

## Классификация конфигурации

Не вся конфигурация одинакова.

### Deployment configuration

- connections;
- disks;
- endpoints;
- queue names;
- table names;
- timeouts.

Подходит immutable options.

### Product policy

- maximum session lifetime;
- impersonation restrictions;
- account lifecycle transitions;
- direct grant semantics.

Лучше policy object, а не generic options.

### Runtime tenant settings

- tenant-specific locale;
- enabled CRM feature;
- retention period;
- workflow preferences.

Не должны читаться через Laravel config.

### Secret material

- OIDC secret;
- Meilisearch key;
- signing key.

Должно иметь отдельную abstraction и redaction policy.

## Пример

```php
final readonly class ImpersonationPolicy
{
    public function __construct(
        public Duration $maximumDuration,
        public bool $allowWrites,
        public bool $requireReason,
        public bool $requireApproval,
    ) {}
}
```

Это лучше:

```php
config('corex-tenancy.impersonation.allow_writes')
```

в каждом middleware/service.

## Config schema

Каждый package должен иметь:

```text
Options::fromArray()
OptionsValidator
config reference documentation
configuration dump command
secret redaction
```

Пример command:

```bash
php artisan corex:config:validate
```

Он должен:

- проверять required keys;
- проверять incompatible flags;
- не выводить secrets;
- показывать effective driver selection;
- проверять optional adapter availability.

# Naming guide

Следует создать `docs/architecture/naming.md` и PHPStan/Pest architecture rules.

## Общие запрещённые имена

В public API не использовать:

```text
Dep
Info
Util
Helper
Common
Misc
BaseService
AbstractManager
DataManager
Processor
Handler
Resolver
Service
```

Последние четыре не запрещаются полностью, но требуют конкретного объекта ответственности.

## Суффиксы

| Суффикс | Точная семантика |
|---|---|
| `Definition` | Декларативное описание поведения |
| `Descriptor` | Представление metadata существующего объекта |
| `Manifest` | Distribution/module metadata |
| `Registry` | Набор зарегистрированных definitions/providers |
| `Catalogue` | Read-oriented классифицированный набор |
| `Repository` | Domain collection и persistence boundary |
| `Store` | Низкоуровневое durable key/value или record storage |
| `Gateway` | Граница внешней системы |
| `Provider` | Реализация capability либо Laravel registration |
| `Manager` | Driver manager или lifecycle manager |
| `Coordinator` | Оркестрация нескольких collaborators |
| `Executor` | Исполнение definition/plan |
| `Runner` | Runtime loop или command-level execution |
| `Compiler` | Детерминированное преобразование representation |
| `Planner` | Создание execution/query plan |
| `Projector` | Создание read model/document |
| `Hydrator` | Восстановление объекта из representation |
| `Mapper` | Structural conversion без внешних side effects |
| `Transformer` | Semantic преобразование данных |
| `Resolver` | Выбор/восстановление одного значения |
| `Locator` | Поиск resource/provider без semantic choice |
| `Factory` | Создание объекта |
| `Builder` | Пошаговая сборка сложного объекта |
| `Policy` | Business decision/rules |
| `Guard` | Проверка условия с отказом |
| `Authorizer` | Access decision |
| `Validator` | Сбор violations, не orchestration |
| `Handler` | Обработка конкретного command/event/message |
| `Listener` | Framework/event subscription adapter |
| `Subscriber` | Регистрирует несколько связанных event listeners |
| `Adapter` | Реализует один contract через другой API |
| `Bridge` | Соединяет две модели, редко должен быть именем класса |

`Factory` создаёт объекты и не выполняет orchestration. `Handler` всегда привязан к конкретному message/command/event. `Coordinator` оркестрирует несколько независимых collaborators.

### Дополнительные переименования (сквозные пакеты)

| Сейчас | Предлагается | Причина |
|---|---|---|
| `StructuralAttributeFilter` | `SchemaAttributeFilter` | «Structural» расплывчато |
| `RegistryFieldCatalogue` | `ModuleFieldCatalogue` | Виден источник definitions |
| `UnsupportedSearchProvider` | `UnavailableSearchProvider` | Нет capability, а не «неподдерживаемый» протокол |
| `TasksWorkflowModule` | `TaskWorkflowIntegration` | Bridge, не самостоятельный module |
| `CompletionCaptureObserver` | `CaptureTaskCompletion` | Имя по поведению handler |
| `DatabaseTransferRunner` | `SqlTransferExecutor` | `Runner` не раскрывает persistence role |
| `WriteResult` | `AttributeWriteResult` | Меньше collisions при импортах |
| `OperationKey` | `SchemaOperationId` | Если идентифицирует schema operation |
| `AccountsResult` | `AvailableAccounts` | Убрать generic `Result` |
| `Data` (крупные пакеты) | `ValueObjects` или папки домена | `Data` не сообщает роль |

В compact-пакетах каталог `Data` ещё допустим; в крупных — value objects рядом с доменом.

## Переменные

Избегать в public methods:

```text
$data
$item
$obj
$entity
$type
$value
$key
$target
$source
$result
$config
$options
```

без domain qualifier.

Предпочитать:

```text
$attributeValues
$subjectType
$operationId
$moduleDependency
$transferSource
$searchResultPage
$tenancyOptions
$workspaceId
```

## IDs

- String scalar: `$tenantId`.
- Value object: `$tenantId` тоже допустимо, если type виден.
- Reference: `$tenantRef`.
- Database primary key нельзя называть `$id`, если рядом несколько identities.
- External identifier: `$externalAccountId`.
- Idempotency identifier: `$operationId`, а не `$key`.
- Cursor: `$checkpoint` или `$cursor`, не `$offset`, если offset semantics отсутствует.

## Булевы значения

```text
$isActive
$hasPermission
$canRetry
$shouldPublish
$allowsWrites
$requiresApproval
```

Не использовать:

```text
$active
$permission
$retry
$enabledFlag
```

## Коллекции

```text
$definitions
$moduleDependencies
$attributeValues
$searchHits
```

Не использовать singular name для iterable.

# Public API

PHP namespace не даёт package-private visibility. Поэтому `Internal` является только соглашением.

## Предлагаемая API map

В каждом package создать:

```php
// api/public.php
return [
    Contracts\SearchProvider::class,
    Data\SearchQuery::class,
    Data\SearchResultPage::class,
    Exceptions\SearchUnavailable::class,
    SearchServiceProvider::class,
];
```

Можно генерировать snapshot автоматически из:

- `@api`;
- public namespace allowlist;
- reflection;
- PHPStan symbols.

## Stability levels

```text
stable
experimental
internal
deprecated
```

Public experimental API допускает изменение до 1.0, но должен быть явно обозначен.

## Межпакетный import rule

Разрешить импорт только:

```text
Package\Contracts
Package\Data
Package\Exceptions
documented root entry points
```

Запретить:

```text
Package\Internal
Package\Infrastructure
Package\Laravel, кроме provider registration
Package\Tests
Package\Testing, кроме require-dev
```

Но механический запрет на `Infrastructure` не абсолютен: отдельный product composition root может сознательно выбирать concrete adapter. Такое исключение должно быть зафиксировано.

# Extension model

CoreX нужен единый extension protocol, чтобы будущая CMS не создала десятки несовместимых registries.

## Не делать единый God Registry

Нельзя создавать:

```php
$corex->register($anything);
```

Нужны typed registries:

```text
ModuleRegistry
OperationRegistry
PanelRegistry
SearchDriverRegistry
AttributeTypeRegistry
WorkflowActivityRegistry
TransferCodecRegistry
NotificationChannelRegistry
```

## Общие invariants

Каждый registry должен определить:

- identifier type;
- duplicate policy;
- registration freeze moment;
- lazy/eager resolution;
- priority semantics;
- deterministic ordering;
- cache invalidation;
- serialization requirements;
- tenant awareness;
- replacement/decorator policy.

## Freeze

Registries должны иметь фазу:

```text
collecting -> compiled -> frozen
```

Runtime modification после freeze должна быть запрещена или явно ограничена.

Это предотвращает request-order-dependent behaviour в Octane и workers.

## Compile step

Module contributions следует компилировать в immutable runtime catalogues.

```php
final readonly class CompiledPanelCatalogue
{
    /** @param array<PanelId, PanelDefinition> $panels */
    public function __construct(public array $panels) {}
}
```

Runtime не должен каждый раз обходить module manifests и container.

# Events и messaging

CoreX активно использует events, jobs, workflow и PubSub. Нужна общая политика типов сообщений.

## Три вида сообщений

### Domain event

Факт внутри bounded context:

```text
MembershipExpired
AttributeValuesChanged
TransferCompleted
```

### Integration event

Версионированный контракт между packages/processes:

```text
MembershipChangedV1
SearchProjectionRequestedV1
```

### Command

Намерение выполнить действие:

```text
ProvisionTenant
ReindexSubject
ExecuteTransfer
```

Laravel event class не должен автоматически становиться integration contract.

## Envelope

```php
final readonly class MessageEnvelope
{
    public function __construct(
        public MessageId $messageId,
        public MessageName $name,
        public MessageVersion $version,
        public OccurredAt $occurredAt,
        public CorrelationId $correlationId,
        public ?CausationId $causationId,
        public ScopeRef $scope,
        public array $payload,
        public array $metadata,
    ) {}
}
```

## Обязательные правила

- versioned names;
- no model serialization;
- no arbitrary object graph;
- PII classification;
- payload size limit;
- retry classification;
- dead-letter policy;
- idempotent consumer;
- outbox/inbox where delivery matters;
- correlation and causation IDs;
- schema compatibility test.

# Ошибки

Сейчас многие packages имеют generic exceptions.

Нужна единая taxonomy:

```text
DomainViolation
Conflict
NotFound
Unavailable
Unsupported
InvalidConfiguration
InfrastructureFailure
AuthorizationDenied
ConcurrencyConflict
IdempotencyConflict
```

Но не нужно создавать один огромный hierarchy в `core`.

Пакетные ошибки должны быть конкретными:

```text
AttributeRecordVersionConflict
TransferCheckpointConflict
SearchProviderUnavailable
ModuleDependencyCycle
TenantProvisioningFailed
```

## Result или exception

- Ожидаемый business denial: typed result/decision.
- Programmer/configuration error: exception.
- Infrastructure failure: exception с safe context.
- Batch partial failure: report с record failures.
- Concurrency conflict: domain-specific exception или typed conflict result.
- «Не найдено» в query: nullable/optional result, если это нормальный случай.

# Observability

Для ядра CRM/CMS observability — часть contracts, а не поздняя надстройка.

Каждая длительная операция должна поддерживать:

- operation ID;
- correlation ID;
- tenant/workspace reference;
- actor reference;
- package and operation name;
- duration;
- outcome;
- retry number;
- redacted failure details;
- metrics tags с ограниченной cardinality.

Не использовать tenant ID как unrestricted Prometheus label при большом количестве tenants.

## Предлагаемые abstractions

```php
interface OperationObserver
{
    public function started(OperationStarted $event): void;

    public function completed(OperationCompleted $event): void;

    public function failed(OperationFailed $event): void;
}
```

Не стоит создавать собственную tracing platform. Достаточно bridge к PSR-3/OpenTelemetry/Laravel events.

# Security

## Capability checks

Optional package availability и permission — разные вещи.

Нужно различать:

```text
CapabilityUnavailable
CapabilityDisabled
OperationUnsupported
AccessDenied
FeatureNotEntitled
```

Один generic `Unavailable` скрывает причину.

## Impersonation

Обязательные invariants:

- отдельный actor и effective actor;
- неизменяемый initiator;
- reason;
- expiry;
- write policy;
- audit correlation;
- невозможность nested impersonation либо явная policy;
- revocation;
- worker/session cleanup;
- sensitive operation denial;
- отображение impersonation в каждом audit record.

## Tenant context

Запрещено доверять tenant ID из client payload без server-side resolution/verification.

Cross-tenant references должны проверяться до repository operation.

## Mass assignment

Attributes, Transfer и operation dispatch особенно уязвимы к schema-confusion. Нельзя считать, что поле разрешено только потому, что оно зарегистрировано.

Требуются отдельно:

- field exists;
- actor may view;
- actor may write;
- current operation may mutate;
- type valid;
- constraints valid;
- tenant owns subject;
- schema revision compatible.

# Open-source референсы

Ни один open-source проект не следует копировать целиком. Нужно заимствовать конкретные механизмы.

## Laravel Framework

Референсы:

- [`Illuminate\Manager`](https://github.com/laravel/framework/blob/12.x/src/Illuminate/Support/Manager.php);
- [`QueueManager`](https://github.com/laravel/framework/blob/12.x/src/Illuminate/Queue/QueueManager.php);
- [`FilesystemManager`](https://github.com/laravel/framework/blob/12.x/src/Illuminate/Filesystem/FilesystemManager.php);
- [Package development](https://laravel.com/docs/12.x/packages);
- [Service container](https://laravel.com/docs/12.x/container);
- [Octane dependency injection caveats](https://laravel.com/docs/12.x/octane#dependency-injection-and-octane).

Применить:

- extendable driver manager;
- composition через container;
- scoped bindings;
- provider как framework boundary;
- explicit lifecycle.

Не копировать:

- повсеместные facades и helpers внутри domain code;
- service locator style;
- mutable global runtime assumptions.

## Spatie package skeleton

Референсы:

- [`spatie/package-skeleton-laravel`](https://github.com/spatie/package-skeleton-laravel);
- [`spatie/laravel-package-tools`](https://github.com/spatie/laravel-package-tools).

Применить:

- package-local test setup;
- CI matrix;
- consistent resources registration;
- README and changelog discipline;
- minimal package boilerplate.

Не копировать автоматически:

- один structural template для пакетов любого размера;
- dependency на package-tools для foundation без оценки пользы.

## Spatie Laravel Permission

Референс: [`spatie/laravel-permission`](https://github.com/spatie/laravel-permission).

Изучить:

- registrars;
- model customization;
- cache invalidation;
- teams/tenant-like scoping;
- Octane reset;
- permission checks.

Применить к CoreX Auth:

- explicit cache lifecycle;
- reset semantics;
- configurable models/tables только на adapter boundary;
- contract tests для scopes.

## Spatie Media Library

Референс: [`spatie/laravel-medialibrary`](https://github.com/spatie/laravel-medialibrary).

Изучить:

- model extension;
- conversion registration;
- responsive images;
- filesystem abstraction;
- queued conversions;
- events.

Применить:

- declarative conversions;
- clear media ownership;
- configurable model/storage adapters;
- idempotent conversion pipeline.

Не копировать:

- Eloquent trait как обязательный universal CoreX contract.

## Symfony DependencyInjection

Референсы:

- [`symfony/dependency-injection`](https://github.com/symfony/dependency-injection);
- [Bundle best practices](https://symfony.com/doc/current/bundles/best_practices.html);
- [Semantic configuration](https://symfony.com/doc/current/components/config/definition.html).

Особенно полезно:

- compile-time validation;
- configuration tree;
- service visibility;
- compiler passes;
- tagged services;
- immutable compiled container.

Идея для CoreX:

- registry contributions собирать и валидировать при boot/compile;
- runtime catalogues делать immutable;
- typed config validation приблизить к Symfony Config Definition.

## Sylius

Референс: [`Sylius/Sylius`](https://github.com/Sylius/Sylius).

Изучить:

- resource layer;
- component/bundle separation;
- state machines;
- domain events;
- grids;
- modular e-commerce architecture.

Применить:

- разделение reusable component и framework bundle;
- state-machine discipline;
- resource metadata только там, где она не заменяет доменную модель.

Риск:

- чрезмерная abstraction и большое число очень мелких packages.

## Shopware 6

Референс: [`shopware/shopware`](https://github.com/shopware/shopware).

Изучить:

- plugin lifecycle;
- data abstraction layer;
- entity definitions;
- migrations;
- event subscribers;
- extension ecosystem;
- administration extensions.

Полезно для CoreX CMS:

- metadata-driven entities;
- extension registration;
- plugin ownership;
- migrations per extension.

Не копировать:

- универсальную DAL как обязательную замену Eloquent/domain repositories;
- чрезмерную runtime metadata complexity.

## Pimcore

Референс: [`pimcore/pimcore`](https://github.com/pimcore/pimcore).

Изучить:

- dynamic object classes;
- field definitions;
- classification store;
- localized fields;
- schema evolution;
- asset management.

Это особенно важный reference для Attributes и CMS.

Не копировать:

- active-record-like rich global platform;
- tight coupling dynamic schema, admin UI и persistence.

## Akeneo PIM

Референс: [`akeneo/pim-community-dev`](https://github.com/akeneo/pim-community-dev).

Изучить:

- attribute families;
- value collections;
- locale/channel scope;
- completeness;
- product projections;
- import/export jobs.

Применить:

- stable attribute identity;
- scoped values;
- transfer jobs;
- validation by schema/family;
- separation write model and search projection.

## OroPlatform

Референс: [`oroinc/platform`](https://github.com/oroinc/platform).

Особенно близок по назначению CRM-platform.

Изучить:

- entities/configuration;
- data grids;
- workflows;
- activities;
- ACL;
- migrations;
- integrations;
- organization/tenant scopes.

Не копировать:

- annotation/configuration-heavy magic;
- deep framework coupling;
- runtime entity extension complexity без строгих boundaries.

## Drupal

Референс: [`drupal/drupal`](https://github.com/drupal/drupal).

Изучить:

- modules;
- plugin managers;
- typed data;
- entity/field API;
- configuration schema;
- service tagging;
- cache contexts/tags;
- update hooks.

Применить:

- typed extension definitions;
- plugin manager separation;
- cache invalidation metadata;
- config schema;
- field API lessons.

Не копировать:

- hooks как global string extension mechanism;
- procedural module-level coupling;
- massive shared runtime.

## WordPress Gutenberg

Референс: [`WordPress/gutenberg`](https://github.com/WordPress/gutenberg).

Изучить для CMS:

- block registration;
- block schema;
- serialization/versioning;
- deprecations/migrations;
- editor/runtime separation.

Полезно для будущего content/block subsystem, но не для CoreX kernel.

## October CMS

Референс: [`octobercms/october`](https://github.com/octobercms/october).

Изучить:

- plugin lifecycle;
- backend navigation;
- forms/lists metadata;
- extension model;
- CMS themes/components.

Применить:

- product-level plugin UX;
- declarative admin contributions;
- separation CMS from lower-level capabilities.

Не копировать:

- event-driven class extension magic без строгого API;
- plugin assumptions внутри foundation.

## Bagisto

Референс: [`bagisto/bagisto`](https://github.com/bagisto/bagisto).

Изучить:

- Laravel package organization;
- contracts/repositories;
- package providers;
- admin/shop separation;
- modular product shell.

Не копировать:

- repository/interface на каждый Eloquent model;
- central Core как universal dumping ground;
- cross-package model coupling.

## Magento 2

Референс: [`magento/magento2`](https://github.com/magento/magento2).

Изучить только конкретные идеи:

- module declarations;
- dependency sequencing;
- service contracts;
- extension attributes;
- declarative schema;
- plugins/interceptors.

Magento — важный антипример:

- чрезмерная DI-конфигурация;
- service-contract explosion;
- generated complexity;
- plugin interception как скрытый control flow;
- дорогой runtime mental model.

CoreX не должен повторить Magento ради формальной extensibility.

## Medusa

Референс: [`medusajs/medusa`](https://github.com/medusajs/medusa).

Изучить:

- modules;
- workflow orchestration;
- links between modules;
- service isolation;
- product composition.

Полезна идея module links вместо прямого доступа к foreign models.

## Temporal PHP SDK

Референс: [`temporalio/sdk-php`](https://github.com/temporalio/sdk-php).

Изучить:

- deterministic workflow code;
- activity boundaries;
- retries;
- signals;
- queries;
- durable timers;
- payload serialization.

Применить к Workflow/Wakeup:

- workflow state не зависит от request/container globals;
- activity выполняет I/O;
- timer/wakeup semantics;
- versioning durable definitions.

## EventSauce

Референс: [`EventSaucePHP/EventSauce`](https://github.com/EventSaucePHP/EventSauce).

Изучить:

- message envelopes;
- serialization;
- aggregate roots;
- repositories;
- consumers.

Использовать как reference для message discipline, но не переводить весь CoreX на event sourcing.

## Broadway

Референс: [`broadway/broadway`](https://github.com/broadway/broadway).

Изучить:

- commands;
- events;
- projections;
- event stores.

Важный урок: CQRS/event sourcing полезны локально, но не должны становиться обязательной архитектурой всех пакетов.

## League packages

Референсы:

- [`thephpleague/flysystem`](https://github.com/thephpleague/flysystem);
- [`thephpleague/pipeline`](https://github.com/thephpleague/pipeline);
- [`thephpleague/fractal`](https://github.com/thephpleague/fractal);
- [`thephpleague/csv`](https://github.com/thephpleague/csv).

Уроки:

- маленькие стабильные contracts;
- adapter boundaries;
- минимальная framework dependence;
- explicit immutable objects;
- narrowly scoped packages.

Flysystem особенно полезен как эталон того, как отделить generic capability от provider adapters.

## Doctrine

Референсы:

- [`doctrine/orm`](https://github.com/doctrine/orm);
- [`doctrine/dbal`](https://github.com/doctrine/dbal);
- [`doctrine/migrations`](https://github.com/doctrine/migrations).

Изучить:

- metadata compilation;
- unit of work;
- database abstraction boundaries;
- migration versioning.

Не копировать Doctrine ORM поверх Eloquent. Полезны отдельные patterns, а не второй ORM.

## Laravel Scout

Референс: [`laravel/scout`](https://github.com/laravel/scout).

Применить:

- engine manager;
- optional providers;
- index abstraction;
- queued syncing.

Улучшить относительно Scout:

- не привязывать core search documents к Eloquent searchable models;
- разделить provider и hydration;
- version search document schema.

## Meilisearch Laravel Scout

Референс: [`meilisearch/meilisearch-laravel-scout`](https://github.com/meilisearch/meilisearch-laravel-scout).

Использовать как исторический пример того, как external adapter впоследствии может быть поглощён host package. Это подтверждает необходимость ясной policy: отдельный adapter package оправдан dependency isolation, но не обязан существовать вечно.

# Architecture scorecard

Для каждого package Claude должен заполнить таблицу.

| Критерий | 0 | 1 | 2 |
|---|---|---|---|
| Responsibility | Неясна | Частично | Одна чёткая |
| Standalone install | Не работает | Требует fixture hacks | Работает |
| Public API | Не определён | Соглашение | Автоматически контролируется |
| Config | Глобальная | Смешанная | Typed boundary |
| Extension | Fork/branch | Частичный registry | Contract + tests |
| Data ownership | Нарушен | Не документирован | Формализован |
| Worker safety | Не проверена | Локальные меры | Общий lifecycle |
| Package tests | Только monorepo | Package tests | Matrix + pristine install |
| Naming | Generic | Смешанное | Ubiquitous language |
| Structure | Junk drawers | Частично feature-first | Cohesive neighbourhoods |
| Observability | Ad hoc | Logs/events | Standard context |
| BC policy | Нет | Manual | API snapshot/checker |

Итог не должен превращаться в KPI. Scorecard нужен для обнаружения неоднородности.

# Задания Claude

## Инвентаризация

Создать machine-readable inventory:

```text
artifacts/architecture/files.csv
```

Колонки:

```text
package
file
namespace
symbol
symbol_kind
visibility
public_api
bounded_context
layer
framework_dependency
cross_package_imports
config_reads
facades
database_tables
events_emitted
events_consumed
serializable_boundary
suggested_namespace
suggested_name
risk
notes
```

## Анализ каждого класса

Для каждого production class ответить:

1. Какова единственная ответственность?
2. Кто его владелец?
3. Public ли он?
4. Почему он расположен именно здесь?
5. Зависит ли он от Laravel?
6. Можно ли протестировать его без container?
7. Читает ли он global config/state?
8. Пересекает ли durable boundary?
9. Использует ли concrete class другого package?
10. Имя объясняет действие или только техническую роль?
11. Нужно ли его разделить?
12. Можно ли удалить его без потери domain behaviour?

Не нужно писать длинный текст для каждого тривиального DTO. Достаточна таблица с verdict:

```text
KEEP
MOVE
RENAME
SPLIT
INLINE
DELETE
PUBLIC
INTERNAL
```

## Automated searches

Запустить и сохранить результаты:

```bash
rg '\bconfig\(' packages
rg '\bapp\(' packages
rg '\bresolve\(' packages
rg '\\bDB::' packages
rg '\\bAuth::' packages
rg '\\bEvent::' packages
rg '\\bCache::' packages
rg '\\bnow\(' packages
rg 'static \\$' packages
rg 'Internal\\\\' packages
rg 'Support\\\\' packages
rg 'use CoreX\\\\' packages
rg 'table\\(' packages
rg 'protected \\$table' packages
rg 'dispatch\\(' packages
rg 'event\\(' packages
rg 'implements ShouldQueue' packages
```

Дополнительно написать AST-based scanner, потому что `rg` не найдёт aliases и dynamic calls.

## Package graph

Построить три графа:

1. Composer dependency graph.
2. PHP namespace import graph.
3. Runtime integration graph.

Они не совпадают, и именно расхождения особенно важны.

## Database ownership

Создать:

```text
docs/architecture/table-ownership.md
```

Для каждой таблицы:

- owning package;
- migration;
- writer;
- readers;
- tenant scope;
- restore policy;
- retention policy;
- encryption/PII class;
- allowed cross-package access;
- indexes;
- upgrade strategy.

## Config ownership

Создать:

```text
docs/architecture/configuration.md
```

Для каждого key:

- owner;
- type;
- default;
- secret;
- deployment/runtime;
- reloadable;
- validation;
- typed options field;
- deprecated aliases.

# План рефакторинга

## Фаза A: нормативные правила

- Architecture constitution.
- Package taxonomy.
- Naming guide.
- Public API policy.
- Extension policy.
- Data ownership policy.
- Message policy.
- Configuration policy.

Никаких массовых переносов до завершения этой фазы.

## Фаза B: inventory

- file inventory;
- symbol inventory;
- package graph;
- runtime global inventory;
- table ownership;
- configuration map;
- public API snapshot.

## Фаза C: P0 boundaries

- сделать `core` foundation-oriented;
- закрыть remaining undeclared/internal imports;
- отвязать Attributes definitions от Modules;
- разделить contexts и adapters;
- decomposition крупных providers;
- исключить runtime model/request/container из durable payloads.

## Фаза D: domain structure

Последовательно, package-by-package:

1. Tenancy.
2. Core.
3. Modules.
4. Attributes.
5. Transfer.
6. Search.
7. Workflow/Wakeup.
8. API.
9. Auth.
10. Media/Notifications/Panels.

Не перемещать все пакеты одним commit.

## Фаза E: naming

После стабилизации структуры:

- rename classes;
- rename variables/public parameters;
- добавить deprecated aliases только если уже существует consumer;
- обновить examples/documentation;
- запустить API diff.

До стабильного 1.0 лучше делать чистое переименование без многолетнего compatibility baggage.

## Фаза F: extension verification

Создать external fixture packages:

```text
fixtures/extensions/custom-search-provider
fixtures/extensions/custom-attribute-source
fixtures/extensions/custom-transfer-codec
fixtures/extensions/custom-panel-provider
fixtures/extensions/custom-workflow-activity
```

Они должны использовать только public API.

## Фаза G: consumers

- FlexCRM.
- Minimal CMS.
- Single-tenant installation.
- Multi-tenant installation.
- CLI/worker-only installation.
- Installation without Meilisearch.
- Installation without Modules where допустимо.

# Что не делать

- Не дробить каждый namespace в отдельный Composer package.
- Не вводить интерфейс для каждого класса.
- Не создавать repository abstraction поверх каждой Eloquent model.
- Не переносить весь CoreX на DDD/CQRS/Event Sourcing ради терминологии.
- Не создавать `CoreXManager` или универсальный service locator.
- Не делать Modules владельцем всех extension definitions.
- Не использовать EAV для системных инвариантов.
- Не читать чужие таблицы ради удобства.
- Не делать поиск source of truth.
- Не связывать CMS block model с CRM dynamic attributes без отдельной границы.
- Не создавать десятки bridge packages без optional dependency justification.
- Не использовать `Internal` как замену проектированию namespace.
- Не считать высокий coverage доказательством правильных boundaries.
- Не считать зелёный monorepo CI доказательством standalone package correctness.
- Не создавать backward compatibility для API, который ещё не был публично выпущен.
- Не оптимизировать структуру только под IDE tree; runtime и ownership важнее.
- Не внедрять паттерн только потому, что он используется Symfony, Spatie или Magento.

# Definition of Done 1.0

CoreX можно считать готовым к 1.0, если:

- назначение каждого package формулируется одним предложением;
- package taxonomy проверяется автоматически;
- `core` не является складом общих возможностей;
- каждый public symbol входит в API map;
- межпакетный импорт internal implementation запрещён;
- у каждой таблицы указан владелец;
- configuration валидируется и передаётся typed objects;
- contextual state безопасен для Octane и workers;
- durable messages сериализуемы и версионированы;
- Attributes работает без привязки к model/table identity;
- Attributes definition source заменяем;
- Transfer поддерживает resume, idempotency и partial failures;
- Search имеет минимум две проверенные provider implementations;
- Meilisearch optional;
- Search Intl removable;
- bridge packages не владеют чужими данными;
- large packages организованы bounded-context-first;
- service providers остаются composition roots;
- CMS и CRM используют одинаковые lower-level contracts;
- custom external extensions проходят contract suites;
- upgrade path проверяется от предыдущей поддерживаемой версии;
- API diff является частью release gate;
- architecture decisions и code не расходятся;
- examples используют только supported public API;
- README каждого package показывает minimal installation, configuration, extension и failure semantics.

## Финальный вердикт

**Статус разработки: GO.** Предыдущие P0-дефекты в значительной степени исправлены, package graph и CI стали заметно сильнее.

**Статус архитектурной завершённости: ещё не 1.0.** Основной оставшийся объём — внутренняя декомпозиция крупных пакетов, typed configuration, explicit public API, единый naming language, формализация extension model и доказательство универсальности через независимые CRM/CMS consumers.

Наибольшую отдачу сейчас дадут:

1. архитектурная конституция;
2. полный file/symbol inventory;
3. bounded-context-first реорганизация Tenancy;
4. уменьшение ответственности Core;
5. отвязка Attributes definition source от Modules;
6. typed options и policies вместо runtime `config()`;
7. API maps и automatic BC gate;
8. external extension fixtures;
9. минимальный CMS consumer;
10. пакетный scorecard и последовательный рефакторинг без big-bang.

В этой итерации файл не приложен: текущая среда не позволила создать и передать новый artifact. Текст выше подготовлен как additive overlay, который можно сохранить рядом с предыдущими документами, например как `audits/2026-09-19-perplexity-3.md`.