# Дизайн фазы P8 — registrar-модель composition roots

**Статус:** нормативный вход items P8.1–P8.6. Это **источник готового кода
интерфейса, registrar'ов, доказательства биндингов и гейта**, а не эскиз.
Каждый item называет здесь свой параграф; спека без указателя — дефект
дизайна.

**Что этот документ НЕ делает.** Он не меняет состав и классы реализации
биндингов. Он не меняет порядок bootstrapper'ов. Он не создаёт registrar
«на каждые два биндинга». Он не переносит код между пакетами и не
переименовывает типы (P15). Он не раскладывает tenancy по bounded
context'ам (P9). Он не дробит `CoreServiceProvider` (E-CORE-PROVIDER).
Он не исполняется до конца стадии 2 (D14).

**Входы:** `01-shapes-runtime.md` §5; `P7-design.md` §6 и §9 (Options);
`P8-design-rag.md`; `00-baseline-metrics.md` §3; A40, A41, A74; D42–D45;
`packages/tenancy/src/TenancyServiceProvider.php`;
`packages/modules/src/ModulesServiceProvider.php`.

---

## 0. Инварианты, обязательные для всех items фазы

1. **Язык — русский**, идентификаторы классов — латиницей.
2. **Ширина строки ≤ 100 символов.** Таблицы могут её превышать, проза — нет.
3. **Ключ, класс реализации и lifetime биндинга не меняются.**
   `bindIf` остаётся `bindIf`, не `bind`.
4. **Порядок `bootstrapper_order` совпадает байтово** с
   `packages/tenancy/config/tenancy.php`.
5. **Registrar не наследует ServiceProvider (D42).**
6. **Негативная проверка обязательна у гейта** (`RAG:✅` P8-Q5).
7. **Один пакет на item**, кроме корневого гейта в `scripts/` + `tests/Arch`.
8. **Маркеры:** факт о репозитории — `RAG:—`; внешняя практика — `RAG:✅`
   со ссылкой на `P8-design-rag.md`. Из `audits/*` не копируется ни код,
   ни формулировка.
9. **CHANGELOG.** Item, меняющий продукт, дописывает одну строку в корневой
   `CHANGELOG.md` под `[Unreleased]` → `### Added`.
10. **Пакетный сьют затронутого пакета зелёный.** Поведение валидной
    конфигурации не меняется.
11. **Новых runtime-зависимостей нет.** Symfony DI compiler не вводится
    (`RAG:✅` P8-Q1: механика не эквивалентна).
12. **После P7 registrar читает Options из контейнера**, не `config()`.

---

## 1. Несущие решения детализации

| ID | Решение | Где в коде |
|:--|:--|:--|
| D101 (supersedes D42) | `PackageRegistrar`/trait — exact public composition API; boot с default | §3 |
| D43 | Модули по измеренным биндингам; MembershipRegistrar нет | §2, §4 |
| D44 | Каталог `Laravel/` в tenancy и modules; P9 не рассыпает | §5, §7 |
| D45 | Гейт считает только провайдер; порог 20 + ratchet | §10 |

Эскиз `01-shapes-runtime.md` §5 **принят**. Расширения: `boot()` на
интерфейсе (D42); отказ от пустого Membership (D43); место на диске
(D44); измеримый гейт (D45).

---

## 2. Каталог биндингов «до» (P8.1)

`RAG:—` снято с `TenancyServiceProvider` и `ModulesServiceProvider`
2026-09-19. После P7 ключи те же; чтение идёт из Options. Item P8.1
переносит таблицу в `findings/P8-binding-catalog.md` и сверяет её с
деревом **после исполнения P7** командой ниже. Расхождение с этой
таблицей, если P7 добавил только `TenancyOptions` / policy `instance`,
дописывается строками Options, не переписывает остальное.

`instance()` Options и трёх policy — не call site `singleton|scoped|bind`
и в гейт D45 не входит. В каталог доказательств фазы они входят:
после декомпозиции `$app->make(TenancyOptions::class)` обязан
разрешаться.

### 2.1 Tenancy — register()

| Abstract | Lifetime | Concrete | Модуль |
|:--|:--|:--|:--|
| `StanclTenantDatabaseProvisioner` | singleton | self | Provisioning |
| `SchemaCatalogueSerializer` | singleton | self | Database |
| `TemplateIntegrityLock` | singleton | self | Database |
| `DatabaseTemplateIntegrityVerifier` | singleton | self | Database |
| `TemplateIntegrityVerifier` | singleton | `DatabaseTemplateIntegrityVerifier` | Database |
| `DomainTrustStateWriter` | scoped | self | DomainTrust |
| `DomainTrustCoordinator` | scoped | self | DomainTrust |
| `PendingPool` | singleton | self | Provisioning |
| `StanclTenantDatabaseLifecycle` | singleton | self | Provisioning |
| `TenantDatabaseLifecycle` | singleton | `StanclTenantDatabaseLifecycle` | Provisioning |
| `AccountLifecycle` | scoped | self | Provisioning |
| `MigrationWaveOrchestrator` | singleton | self | Provisioning |
| `StanclTenancyManager` | singleton | self | Runtime |
| `TenancyManager` | singleton | `StanclTenancyManager` | Runtime |
| `TenantContextResolver` | singleton | `TenancyManagerContextResolver` | Runtime |
| `TenantStorage` | scoped | `ContextTenantStorage` | Runtime |
| `DatabaseIdentitySyncer` | singleton | self | Identity |
| `IdentitySyncer` | singleton | `DatabaseIdentitySyncer` | Identity |
| `WorkspaceResolver` | singleton | `DatabaseWorkspaceResolver` | Workspace |
| `PrincipalWorkspaceResolver` | bindIf | `DatabaseWorkspaceResolver` | Workspace |
| `RootRegistryWakeupHint` | singleton | self | Wakeup, optional |
| `RootWakeupReconciler` | singleton | self | Wakeup, optional |
| `OidcDiscovery` | singleton | self | Authentication, optional |
| `JwksKeySet` | singleton | self | Authentication, optional |
| `IdTokenVerifier` | singleton | self | Authentication, optional |
| `OidcClient` | singleton | self | Authentication, optional |
| `LogoutController` | singleton | self | Authentication, optional |
| `ImpersonationService` | scoped | `DatabaseImpersonationService` | Authentication, optional |
| каждый класс из `bootstrapperOrder` | singleton | class-string | Runtime |

Дополнительно, не lifetime-строка: `tag(RootRegistryWakeupHint, 'corex.wakeup.hints')`;
команда `SweepRootWakeups` при wakeup; `WorkspaceContextBootstrapper::__constructStatic`
ровно один раз.

### 2.2 Tenancy — boot()

| Abstract | Lifetime | Concrete | Модуль |
|:--|:--|:--|:--|
| `UniqueIdentifierGenerator` | bind | `$options->idGenerator` | Runtime |

Команды: `PendingCreateCommand`, `PendingClearCommand`,
`IdentitiesReconcileCommand`, `TenantsMigrateCommand`.
События: `TenantCreated` → JobPipeline; `TenancyInitialized` →
`BootstrapTenancy`; `TenancyEnded` → `RevertToCentralContext`;
`JobExceptionOccurred` → `RevertTenancyOnJobException`; три wildcard
`eloquent.*` при impersonation.
Middleware-алиасы: `tenancy.workspace` всегда; `tenancy.host`,
`tenancy.status` при host identification; `tenancy.oidc.session_lifetime`
при OIDC; `tenancy.impersonation.allow` при impersonation.
Группа `tenant` мержится, не перезаписывается.
Маршруты: `routes/oidc.php` при OIDC, с охраной route cache.

### 2.3 Modules — register()

| Abstract | Lifetime | Concrete | Модуль |
|:--|:--|:--|:--|
| `ArrayEntityRegistry` | singleton | self | Registry |
| `EntityRegistry` | singleton | `ArrayEntityRegistry` | Registry |
| `ModuleDiscovery` | singleton | self | Registry |
| `ModuleActivationGate` | singleton | `AllowAllActivationGate` | Registry |
| `ModuleStateReader` | singleton | `DatabaseModuleStateReader` | Lifecycle |
| `RecordsRegistrar` | singleton | `DatabaseRecordsRegistrar` | Lifecycle |
| `corex-modules.wiring` | singleton | array | Lifecycle |
| `ModuleLifecycle` | singleton | `DatabaseModuleLifecycle` | Lifecycle |
| `ModuleRegistryCache` | singleton | `RepositoryModuleRegistryCache` | Registry |
| `ModuleRegistry` | singleton | `CompiledModuleRegistry` | Registry |
| `RegistryIntrospection` | singleton | `ReadRegistryIntrospection` | Registry |
| `SettingDefaultsProvider` | singleton | `CompiledSettingDefaultsProvider` | Settings |

### 2.4 Modules — boot()

Команды `CompileModulesCommand`, `WhyCommand`, closure
`corex:modules:repair`. Publish tags без изменения. `JobProcessing` →
`CompiledModuleRegistry::flush`. `optimizes(corex:modules:compile)`.
`booted`: проекция compiled → EntityRegistry, `Relation::morphMap`,
`AuditCapabilityWiring::wire`. `registryVersion()` остаётся с
`Schema::hasTable` — ленивый epoch, не boot-time обход схемы всего
приложения.

### 2.5 Сверка каталога при исполнении P8.1

```bash
rg -n '->(singleton|scoped|bind|bindIf)\(' \
  packages/tenancy/src/TenancyServiceProvider.php \
  packages/modules/src/ModulesServiceProvider.php
```

Число совпадений tenancy register+boot и modules register должно
совпасть с таблицами §2 после добавления строк Options. Loop
bootstrappers — одна строка `->singleton($bootstrapper)`.

---

## 3. Контракт PackageRegistrar (P8.1, D42)

`RAG:✅` P8-Q1. Готовый код — переносится дословно.

D101 supersedes D42's private-surface consequence: both exact symbols are published
in `extra.corex.public-api` because tenancy/modules import them. P3 R4 permits only
these names, and P6 classifies them in the closed COMPOSITION list; `Support/**` is not
opened as a namespace wildcard.

```php
<?php

declare(strict_types=1);

namespace CoreX\Support;

use Illuminate\Contracts\Foundation\Application;

interface PackageRegistrar
{
    public function register(Application $app): void;

    public function boot(Application $app): void;
}
```

PHP 8.3 допускает default в интерфейсе. Пустой `boot` — в trait, чтобы
не тащить реализацию в контракт (интерфейс остаётся чистым, тест не
зависит от default method):

```php
<?php

declare(strict_types=1);

namespace CoreX\Support;

use Illuminate\Contracts\Foundation\Application;
use Override;

trait RegistersNothingOnBoot
{
    #[Override]
    public function boot(Application $app): void
    {
    }
}
```

Тест без Testbench: анонимный класс с trait, `boot()` — no-op;
`register()` записывает флаг. `ModuleRegistrar` не импортируется.

---

## 4. Когда composition-модуль оправдан (P8.1, D43)

`RAG:✅` P8-Q2. Правило записывается в каталог P8.1 и в Code Guidance
всех item'ов декомпозиции.

Модуль оправдан, если истинен **хотя бы один** пункт:

1. Его можно не включить, и пакет остаётся поддерживаемым (optional
   integration: Wakeup, OIDC).
2. Это связная возможность с одним владельцем и одним ответом на
   «что пропадёт, если убрать вызов» (Runtime, Database, Provisioning,
   Registry, Lifecycle).
3. Это отдельная фаза lifecycle фреймворка (Console, Http, Events).

Модуль **запрещён**, если:

- в провайдере нет ни одного биндинга или хука этого имени
  (Membership);
- единственный мотив — уложиться в порог гейта;
- два биндинга одного владельца разносятся «для красоты».

---

## 5. Tenancy register()-registrar'ы (P8.2, P8.3)

Пространство имён `CoreX\Tenancy\Laravel` (D44). Каждый класс `final`,
`implements PackageRegistrar`, для register-only — trait
`RegistersNothingOnBoot`. Options: `$app->make(TenancyOptions::class)`.

Главный провайдер после P7 уже делает `instance` Options и policy.
P8 это **не** переносит в registrar: это регистрация «что регистрировать»,
она остаётся в провайдере (контракт фазы).

### 5.1 Форма провайдера (наращивается P8.2→P8.4)

```php
#[Override]
public function register(): void
{
    $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');
    $this->mergeConfigFrom(__DIR__.'/../config/corex-tenancy.php', 'corex-tenancy');

    $tenancy = $this->app['config']->get('tenancy', []);
    $wakeup = $this->app['config']->get('corex-tenancy', []);
    $options = TenancyOptions::fromArray(
        tenancy: is_array($tenancy) ? $tenancy : [],
        corexTenancy: is_array($wakeup) ? $wakeup : [],
    );
    $this->app->instance(TenancyOptions::class, $options);
    $this->app->instance(ImpersonationPolicy::class, $options->impersonation);
    $this->app->instance(AccountLifecyclePolicy::class, $options->lifecycle);
    $this->app->instance(StatusGatePolicy::class, $options->statusGate);

    foreach ($this->registrars() as $registrar) {
        $registrar->register($this->app);
    }
}

#[Override]
public function boot(): void
{
    $this->publishes([
        __DIR__.'/../database/migrations/root' => database_path('migrations/root'),
    ], 'tenancy-migrations');
    $this->publishes([
        __DIR__.'/../database/migrations/tenant' => database_path('migrations/tenant'),
    ], 'tenancy-migrations-tenant');
    $this->publishes([
        __DIR__.'/../config/tenancy.php' => config_path('tenancy.php'),
    ], 'tenancy-config');

    foreach ($this->registrars() as $registrar) {
        $registrar->boot($this->app);
    }
}

/** @return list<PackageRegistrar> */
private function registrars(): array
{
    $options = $this->app->make(TenancyOptions::class);
    $registrars = [
        new RuntimeRegistrar(),
        new DatabaseRegistrar(),
        new ProvisioningRegistrar(),
        new IdentityRegistrar(),
        new WorkspaceRegistrar(),
        new DomainTrustRegistrar(),
        new ConsoleRegistrar(),
        new HttpRegistrar(),
        new EventsRegistrar(),
    ];

    if ($options->oidcEnabled) {
        $registrars[] = new AuthenticationRegistrar();
    }

    if (
        $options->wakeupDriver === 'root_registry'
        && interface_exists(WakeupHint::class)
        && interface_exists(WakeupSweeper::class)
    ) {
        $registrars[] = new WakeupRegistrar();
    }

    return $registrars;
}
```

P8.2 добавляет первые три; P8.3 — Identity/Workspace/DomainTrust и
условные Authentication/Wakeup; P8.4 — Console/Http/Events. Пока
item не сдвинул группу, её код остаётся в провайдере.

### 5.2 RuntimeRegistrar (P8.2)

Перенос дословно замыканий manager/context/storage плюс цикл
bootstrappers и `__constructStatic` для `WorkspaceContextBootstrapper`.
`UniqueIdentifierGenerator` и проекция `config([...])` — **boot**, P8.4
(тот же класс, метод `boot`). В P8.2 `boot` пустой через trait; P8.4
снимает trait и пишет `boot`.

```php
final class RuntimeRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $options = $app->make(TenancyOptions::class);

        $app->singleton(
            StanclTenancyManager::class,
            static fn (Application $app): StanclTenancyManager => new StanclTenancyManager(
                $app->make(Stancl::class),
            ),
        );
        $app->singleton(
            TenancyManager::class,
            static fn (Application $app): StanclTenancyManager => $app->make(
                StanclTenancyManager::class,
            ),
        );
        $app->singleton(TenantContextResolver::class, TenancyManagerContextResolver::class);

        $app->scoped(
            TenantStorage::class,
            static fn (Application $app): ContextTenantStorage => new ContextTenantStorage(
                contexts: $app->make(TenantContextResolver::class),
                filesystems: new FilesystemManager($app),
                durableDriver: $options->storageDurableDriver,
                durableRoot: $options->storageDurableRoot,
                ephemeralRoot: $options->storageEphemeralRoot,
            ),
        );

        foreach ($options->bootstrapperOrder as $bootstrapper) {
            if ($bootstrapper === WorkspaceContextBootstrapper::class) {
                WorkspaceContextBootstrapper::__constructStatic($app);
            }

            $app->singleton($bootstrapper);
        }
    }
}
```

### 5.3 DatabaseRegistrar и ProvisioningRegistrar (P8.2)

Database: serializer, lock, `DatabaseTemplateIntegrityVerifier`,
контракт `TemplateIntegrityVerifier`. Provisioning: provisioner,
`PendingPool`, оба lifecycle, orchestrator, `AccountLifecycle` (D43).
Замыкания — как в текущем провайдере, `centralConnection` из Options.
`AccountLifecycle` остаётся `scoped`.

### 5.4 Identity, Workspace, DomainTrust (P8.3)

Identity: `DatabaseIdentitySyncer` + alias `IdentitySyncer`.
Workspace: `WorkspaceResolver` + `bindIf(PrincipalWorkspaceResolver)`.
DomainTrust: оба `scoped` writer и coordinator; coordinator бросает
`DomainTrustDisabled`, если `!$options->domainTrustEnabled` — поведение
не менять.

### 5.5 AuthenticationRegistrar и WakeupRegistrar (P8.3)

Провайдер **не создаёт** эти объекты, если предикат ложен (D43).
Внутри registrar'а повторный `if` не нужен. Authentication переносит
пять OIDC singleton'ов и `scoped ImpersonationService`. Wakeup —
hint, tag, reconciler; команду `SweepRootWakeups` регистрирует в
`boot()` (P8.3 пишет и `boot` этого класса, без trait).

---

## 6. Tenancy boot()-registrar'ы (P8.4)

`RAG:✅` P8-Q3. Review `full`: порядок bootstrapper'ов и проекция на
stancl.

### 6.1 RuntimeRegistrar::boot (дописывается в P8.4)

```php
#[Override]
public function boot(Application $app): void
{
    $options = $app->make(TenancyOptions::class);

    $app['config']->set([
        'tenancy.models.tenant' => $options->tenantModel,
        'tenancy.database.central_connection' => $options->centralConnection,
        'tenancy.database.managers.pgsql' => $options->tenantDatabaseManager,
        'tenancy.bootstrappers' => $options->bootstrapperOrder,
        'tenancy.cache.prefix' => $options->cachePrefix,
    ]);

    $app->bind(UniqueIdentifierGenerator::class, $options->idGenerator);
}
```

Массив `bootstrapperOrder` — тот же объект, что в конфиге. Тест
сравнивает `config('tenancy.bootstrapper_order')` с
`config('tenancy.bootstrappers')` поэлементно и с литералом §6.4.

### 6.2 ConsoleRegistrar

Не вызывать `$this->commands()`. Эквивалент провайдера:

```php
use Illuminate\Console\Application as Artisan;

#[Override]
public function boot(Application $app): void
{
    if (! $app->runningInConsole()) {
        return;
    }

    Artisan::starting(static function (Artisan $artisan): void {
        $artisan->resolveCommands([
            PendingCreateCommand::class,
            PendingClearCommand::class,
            IdentitiesReconcileCommand::class,
            TenantsMigrateCommand::class,
        ]);
    });
}
```

### 6.3 HttpRegistrar

`aliasMiddleware('tenancy.workspace', ResolveWorkspace::class)` —
всегда. Блоки host identification и OIDC — по полям Options, с тем же
merge группы `tenant` (`...$existing`). Перед `group` маршрутов:

```php
if ($app->routesAreCached()) {
    return;
}
$app->make(Router::class)->group(
    $app->make(TenancyServiceProvider::class)->oidcRouteFile(),
);
```

Путь к `routes/oidc.php` провайдер отдаёт узким методом
`oidcRouteFile(): string` (один литерал `__DIR__`), чтобы registrar не
считал `__DIR__` своего файла. Альтернатива — абсолютный путь
`dirname(__DIR__, 2).'/routes/oidc.php'` от `Laravel/`. Второй вариант
проще и не расширяет публичную поверхность провайдера: использовать его.

Имена middleware, порядок в группе `tenant` и
`addToMiddlewarePriorityBefore` копируются без перестановки.

### 6.4 EventsRegistrar

Четыре `Dispatcher::listen` плюс три wildcard при
`$options->impersonation->enabled`. Listener wildcard по-прежнему
`[ImpersonatedModelWriteGuard::class, 'handle']` — resolve per event,
не captured instance (`RAG:—` комментарий текущего провайдера).

Порядок `bootstrapper_order` (литерал приёмки):

1. `DatabaseTenancyBootstrapper`
2. `WorkspaceContextBootstrapper`
3. `CacheTenancyBootstrapper`
4. `DatabaseSessionBootstrapper`
5. `QueueTenancyBootstrapper`
6. `FilesystemTenancyBootstrapper`
7. `ScoutPrefixBootstrapper`

---

## 7. Modules (P8.5)

`CoreX\Modules\Laravel`. Провайдер оставляет `mergeConfigFrom`,
`publishes`, `optimizes`, SCHEMA_VERSION.

```php
foreach ($this->registrars() as $registrar) {
    $registrar->register($this->app);
}
// boot:
foreach ($this->registrars() as $registrar) {
    $registrar->boot($this->app);
}
```

`RegistryRegistrar`: ArrayEntityRegistry, EntityRegistry, Discovery,
ActivationGate, cache, CompiledModuleRegistry, Introspection.
`LifecycleRegistrar`: StateReader, RecordsRegistrar, wiring,
ModuleLifecycle; методы `moduleWiring()` и `registryVersion()`
переезжают сюда как private.
`SettingsRegistrar`: rebind `SettingDefaultsProvider`.
`ModulesBootRegistrar`: команды, `JobProcessing` flush, `booted` morph
map и `AuditCapabilityWiring`. `isCompilingModules()` переезжает с ним.

`ModulesOptions` из контейнера: `$app->make(ModulesOptions::class)`
вместо `config('corex-modules.*')`.

---

## 8. Что остаётся на главном провайдере

Tenancy: mergeConfigFrom ×2, instance Options/policy, publishes ×3,
список registrar'ов, условное включение Authentication/Wakeup.
Modules: SCHEMA_VERSION, mergeConfigFrom, publishes, optimizes,
список registrar'ов.

После P8.4/P8.5 call site `singleton|scoped|bind|bindIf` в каждом
провайдере — **ноль** (Options — `instance`). Это и есть цель гейта.

---

## 9. Доказательство неизменности (P8.2, P8.3, P8.5)

`RAG:✅` P8-Q4. Хелперы — в `packages/tenancy/tests/Concerns/` и
аналог modules. `class_exists` как единственная проверка запрещена.

```php
private function assertSingletonBinding(
    Application $app,
    string $abstract,
    string $expectedConcrete,
): void {
    expect($app->getBindings())->toHaveKey($abstract);
    expect($app->isShared($abstract))->toBeTrue();
    $first = $app->make($abstract);
    $second = $app->make($abstract);
    expect($first)->toBeInstanceOf($expectedConcrete);
    expect($first)->toBe($second);
    $app->forgetScopedInstances();
    expect($app->make($abstract))->toBe($first);
}

private function assertScopedBinding(
    Application $app,
    string $abstract,
    string $expectedConcrete,
): void {
    expect($app->getBindings())->toHaveKey($abstract);
    expect($app->isShared($abstract))->toBeTrue();
    $first = $app->make($abstract);
    expect($first)->toBeInstanceOf($expectedConcrete);
    expect($app->make($abstract))->toBe($first);
    $app->forgetScopedInstances();
    $next = $app->make($abstract);
    expect($next)->toBeInstanceOf($expectedConcrete);
    expect($next)->not->toBe($first);
}

private function assertTransientBinding(
    Application $app,
    string $abstract,
    string $expectedConcrete,
): void {
    expect($app->getBindings())->toHaveKey($abstract);
    expect($app->isShared($abstract))->toBeFalse();
    $first = $app->make($abstract);
    $second = $app->make($abstract);
    expect($first)->toBeInstanceOf($expectedConcrete);
    expect($first)->not->toBe($second);
}
```

Optional-строки каталога гоняются дважды: предикат истинен — abstract
bound; предикат ложен — `getBindings()` ключа нет.
`bindIf` для `PrincipalWorkspaceResolver`: если потребитель уже bound,
тест не перезаписывает; в чистом Testbench пакета — bound на
`DatabaseWorkspaceResolver`.

Строки, требующие PG или полного tenancy-контекста для `make()`,
доказывают membership + `isShared` + forgetScoped без обязательного
`make()`, если `make()` краснит инфраструктурой. Такие строки
перечисляются в receipt с причиной. Lifetime всё равно проверяется
`isShared` + forgetScoped на тех, где `make()` зелёный.

---

## 10. Гейт порога (P8.6, D45)

`RAG:✅` P8-Q5. Сканер — `token_get_all(..., TOKEN_PARSE)`, не regex
по тексту (D16/D17). Считает в файле, чья декларация `extends
ServiceProvider` (или FQCN `Illuminate\Support\ServiceProvider`),
вызовы `singleton` / `scoped` / `bind` / `bindIf` как T_STRING после
`->`.

`scripts/provider-size.json`:

```json
{
  "max_bindings": 20,
  "methods": ["singleton", "scoped", "bind", "bindIf"],
  "ratchet": {
    "packages/core/src/CoreServiceProvider.php": 18,
    "packages/tenancy/src/TenancyServiceProvider.php": 0,
    "packages/modules/src/ModulesServiceProvider.php": 0
  },
  "note": "Числа ratchet — после P8.5. P8.6 пересчитывает и записывает факт. E-CORE-PROVIDER: core не дробится."
}
```

18 у core — оценка до исполнения P7 (`instance` Options может не
увеличить call site). P8.6 **сначала измеряет**, потом пишет JSON;
спека запрещает угадывать. Если измерение дало 19 — в JSON 19, не 18.

Сообщение нарушения называет **относительный путь файла**, метод и
счёт. Посадка: `packages/core/src/Support/ProviderSizeProbeProvider.php`
с 21 `singleton`, restore в `finally`. Pest вызывает CLI, не копирует
сканер.

Allowlist сверх ratchet нет. Новый `*ServiceProvider.php` без ключа в
JSON обязан быть ≤ 20; следующий прогон добавляет его в ratchet
измерением, не вручную «с запасом».

---

## 11. Карта файлов по items

| Item | Пишет |
|:--|:--|
| P8.1 | `packages/core/src/Support/PackageRegistrar.php`, `RegistersNothingOnBoot.php`, unit-тест, `findings/P8-binding-catalog.md`, CHANGELOG |
| P8.2 | `Laravel/RuntimeRegistrar.php`, `DatabaseRegistrar.php`, `ProvisioningRegistrar.php`, провайдер tenancy, `CompositionBindingsTest.php`, CHANGELOG |
| P8.3 | пять registrar'ов §5.4–§5.5, провайдер, дописка теста, CHANGELOG |
| P8.4 | Console/Http/Events, `RuntimeRegistrar::boot`, провайдер, тест порядка, CHANGELOG |
| P8.5 | четыре registrar'а modules, провайдер, тест, CHANGELOG |
| P8.6 | `scripts/provider-size-gate.php`, `scripts/provider-size.json`, `tests/Arch/ProviderSizeTest.php`, CHANGELOG, receipt |

---

## 12. Границы и вход P9

P9 читает `packages/tenancy/src/Laravel/` как слой composition root и
не переносит registrar'ы в DomainTrust/Identity/Membership (D44).
Карта bounded context'ов P9 может совпасть с именами registrar'ов
только случайно: Membership в P9 есть, registrar'а membership нет.

P4.12 по-прежнему владеет гейтом «singleton не принимает Application/
Request/Config». P8 не конвертирует lifetime.

`CoreAuthServiceProvider` и прочие провайдеры < 20 не дробятся.
Ratchet заморозит их измеренные числа в P8.6.

---

## 13. Соответствие items → параграфы

| Item | Параграфы |
|:--|:--|
| P8.1 | §0, §2, §3, §4 |
| P8.2 | §0, §5.1–§5.3, §9 |
| P8.3 | §0, §5.1, §5.4–§5.5, §9 |
| P8.4 | §0, §6, §8 |
| P8.5 | §0, §7, §8, §9 |
| P8.6 | §0, §10 |
