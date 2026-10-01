# P05 — примеры и эталоны

Скелеты написаны по коду commit `39ae489a` и автором не исполнялись (`vendor/` отсутствует). Имена классов,
сигнатуры и форматы вывода — норма; тела методов исполнитель доводит до зелёных проверок.

## 1. Options нового пакета (образец — wakeup)

Вход: `packages/wakeup/config/corex-wakeup.php` = `lease_seconds: 60`, `max_attempts: 5`, `handlers: []`.

```php
<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Internal;

use CoreX\Config\Laravel\ConfigurationValidator;
use CoreX\Wakeup\Data\WakeupHandlerDefinition;

/** @internal Package composition type: read only by the provider and registrars. */
final readonly class WakeupOptions
{
    /** @param  list<WakeupHandlerDefinition>  $handlers */
    public function __construct(
        public int $leaseSeconds,
        public int $maxAttempts,
        public array $handlers,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $leaseSeconds = $config['lease_seconds'] ?? 60;
        if (! is_int($leaseSeconds) || $leaseSeconds < 1) {
            $violations->add('lease_seconds', 'must be a positive integer');
            $leaseSeconds = 60;
        }

        $maxAttempts = $config['max_attempts'] ?? 5;
        if (! is_int($maxAttempts) || $maxAttempts < 1) {
            $violations->add('max_attempts', 'must be a positive integer');
            $maxAttempts = 5;
        }

        $handlers = [];
        foreach (is_array($config['handlers'] ?? null) ? $config['handlers'] : [] as $kind => $definition) {
            if (! is_string($kind)
                || ! is_array($definition)
                || ! is_string($definition['handler'] ?? null)
                || ! is_string($definition['version'] ?? null)
                || ! is_bool($definition['accept_legacy_unstamped'] ?? false)) {
                $violations->add('handlers.'.$kind, 'requires handler, version and an optional boolean accept_legacy_unstamped');

                continue;
            }

            $handlers[] = new WakeupHandlerDefinition(
                kind: $kind,
                version: $definition['version'],
                handler: $definition['handler'],
                acceptLegacyUnstamped: $definition['accept_legacy_unstamped'] ?? false,
            );
        }

        $violations->failIfAny();

        return new self($leaseSeconds, $maxAttempts, $handlers);
    }

    /** @return list<string> config paths of enabled dangerous modes (D69 п.7) */
    public function dangerousModes(): array
    {
        $paths = [];
        foreach ($this->handlers as $handler) {
            if ($handler->acceptLegacyUnstamped) {
                $paths[] = 'corex-wakeup.handlers.'.$handler->kind.'.accept_legacy_unstamped';
            }
        }

        return $paths;
    }
}
```

Вход → выход:

| Вход `fromArray` | Результат |
|:--|:--|
| `[]` | `leaseSeconds=60`, `maxAttempts=5`, `handlers=[]` |
| `['lease_seconds' => 0, 'max_attempts' => 'x']` | `InvalidConfiguration` с двумя строками: `lease_seconds: …`, `max_attempts: …` |
| `['handlers' => ['a' => 'App\\H']]` (scalar-форма) | `InvalidConfiguration`: `handlers.a: requires handler, version …` (deprecated-ветка удалена, D01) |

Тест пакета (`packages/wakeup/tests/Unit/WakeupOptionsTest.php`) покрывает три строки таблицы. Такой же
трёхстрочный тест — у каждого нового Options: дефолты без окружения, накопление нарушений, одна специфичная форма.
Метод `dangerousModes()` есть только у Options пакетов из D69 п.7.

Потребление: сервис не принимает Options.

```php
// было: new SqlWorkflowBinding(enforceDeclarations: (bool) $app['config']->get('corex-workflow.enforce_declarations'))
// стало (registrar):
new SqlWorkflowBinding(enforceDeclarations: $app->make(WorkflowOptions::class)->enforceDeclarations)
// неверно: new SqlWorkflowBinding(options: $app->make(WorkflowOptions::class))  — R5 и связность с формой config
```

## 2. Публичный provider и registrar

```php
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WakeupServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [WakeupBindingsRegistrar::class];

    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);
        $this->mergeConfigFrom(__DIR__.'/../config/corex-wakeup.php', 'corex-wakeup');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }
    }

    public function boot(): void
    {
        $this->app->make(WakeupOptions::class); // форма проверяется при загрузке: CFG-004

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }

        $this->publishes([__DIR__.'/../config/corex-wakeup.php' => config_path('corex-wakeup.php')], 'corex-wakeup-config');
    }
}
```

```php
namespace CoreX\Wakeup\Laravel;

final class WakeupBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(WakeupOptions::class, static fn (Application $app): WakeupOptions => WakeupOptions::fromArray(
            (array) $app->make('config')->get('corex-wakeup', []),
        ));
        $app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);
        // остальные биндинги пакета — в прежнем порядке
    }

    #[Override]
    public function boot(Application $app): void {}
}
```

Запрещённые формы (каждая ловится гейтом или тестом): `$app->bind(XOptions::class, …)`; `$app->instance(XOptions::class, …)`
в `register()`; блочное замыкание с несколькими операторами в provider или registrar (S3); `new class` (S2/G5).

## 3. Runtime-проверки и опасные режимы

```php
namespace CoreX\Config\Contracts;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RuntimeConfigCheck
{
    /** @return list<string> "<config path>: <message>"; empty when the process may accept work */
    public function violations(bool $production): array;
}
```

```php
namespace CoreX\Wakeup\Internal;

/** @internal */
final readonly class DangerousModesCheck implements RuntimeConfigCheck
{
    public function __construct(private WakeupOptions $options, private CoreOptions $core) {}

    #[Override]
    public function violations(bool $production): array
    {
        if (! $production) {
            return [];
        }

        return array_values(array_map(
            static fn (string $path): string => $path.': dangerous mode is not acknowledged for production',
            array_diff($this->options->dangerousModes(), $this->core->acknowledgedDangerousModes),
        ));
    }
}
```

Реализация `RuntimeConfigCheck` — тип слоя композиции пакета: ей, как provider и registrars, разрешено читать
`<Pkg>Options` (D76); runtime-сервисы по-прежнему получают значения.

`CoreX\Config\Laravel\RuntimeConfigGate` (регистрирует registrar модуля `Config`):

| Процесс | Хук | Когда проверки выполняются |
|:--|:--|:--|
| HTTP (не console) | `$app->booted(...)` | всегда |
| console | `Illuminate\Console\Events\CommandStarting` | имя команды не входит в `BUILD_COMMANDS` |
| `corex:config:validate` | сама команда | всегда; печатает нарушения, подтверждённые режимы, уведомления; exit 1 при нарушении |

`BUILD_COMMANDS` — константа: `package:discover`, `config:cache`, `config:clear`, `optimize`, `optimize:clear`,
`route:cache`, `route:clear`, `view:cache`, `view:clear`, `event:cache`, `event:clear`, `vendor:publish`,
`key:generate`, `storage:link`, `list`, `help`, `about`, `corex:config:validate`. Нарушение → `InvalidConfiguration`
со всеми строками всех проверок.

| Пакет | Опасный режим | Путь |
|:--|:--|:--|
| workflow | `enforce_declarations = false` | `corex-workflow.enforce_declarations` |
| notifications | `digest.accept_legacy_unstamped = true` | `corex-notifications.digest.accept_legacy_unstamped` |
| tasks-workflow | `wakeup.accept_legacy_unstamped = true` | `corex-tasks-workflow.wakeup.accept_legacy_unstamped` |
| wakeup | `handlers.<kind>.accept_legacy_unstamped = true` | `corex-wakeup.handlers.<kind>.accept_legacy_unstamped` |

Таблица поведения (тесты `tests/Feature/BuildStageBootTest.php` + по одному на пакет):

| Окружение | Режим | `acknowledged_dangerous_modes` | Команда/процесс | Результат |
|:--|:--|:--|:--|:--|
| любое | — | — | boot со всеми providers, окружение пустое | проходит |
| production | включён | `[]` | `package:discover` | проходит (build) |
| production | включён | `[]` | `queue:work` / HTTP | `InvalidConfiguration` с путём |
| production | включён | `[<точный путь>]` | `queue:work` / HTTP | проходит; `corex:config:validate` показывает предупреждение |
| production | включён | `['corex-wakeup.*']` | boot | `InvalidConfiguration`: шаблон в списке — нарушение формы `CoreOptions` |
| testing | включён | `[]` | любой | проходит |
| production | функция включена, секрет отсутствует | — | `queue:work` / HTTP | `InvalidConfiguration` с путём секрета, без значения |

## 4. Замена static Config

```php
// миграция — было
Schema::create(Config::settingsTable(), function (Blueprint $table): void {
// стало
Schema::create(app(CoreOptions::class)->settingsTable, function (Blueprint $table): void {

// тест — было
DB::table(Config::outboxMessagesTable())->count();
// стало
DB::table(app(CoreOptions::class)->outboxMessagesTable)->count();

// registrar — было
$this->app->singleton(SettingsRepository::class, $options->settingsRepository);
// стало
$app->singleton(SettingsRepository::class, static fn (Application $app): SettingsRepository => $app->make($app->make(CoreOptions::class)->settingsRepository));

// тест с записью после boot
config(['corex.audit.enabled' => false]);
$this->app->forgetInstance(CoreOptions::class);
```

Импорт `use CoreX\Config\Internal\Config;` заменяется на `use CoreX\Config\Data\CoreOptions;`.

## 5. Гейт config-composition

`scripts/composition-boundary.json` после P05.1:

```json
{
    "blocking": true,
    "allow_globs": [
        "*/src/*ServiceProvider.php",
        "*/src/**/Laravel/*Registrar.php",
        "*/src/**/Laravel/*Factory.php",
        "*/src/**/*ConfigSection.php"
    ],
    "allow_paths": {
        "packages/core/src/Config/Internal/Commands/ValidateConfigCommand.php": "Effective configuration report command"
    }
}
```

Формат находки: `<RULE> <file>:<line> <what>`; правила: `C1 config()`, `C2 config-repository`, `C3 static-Config`, `C4 env()`.
C2 срабатывает на: `app('config')`, `$app['config']`, `->make('config')`, тип `Illuminate\Contracts\Config\Repository`
или `Illuminate\Config\Repository` в `use`/сигнатуре, фасад `Config`.

Эталон на чистом дереве после P05.1:

```text
config-composition-gate: blocking=true
config-composition-gate: C1 count=0 C2 count=0 C3 count=0 C4 count=0
config-composition-gate: OK
```

Эталон негативного контроля C2 (проба `packages/core/src/Config/Internal/CompositionProbe.php` с
`use Illuminate\Contracts\Config\Repository;`): exit 1, вывод содержит
`C2 packages/core/src/Config/Internal/CompositionProbe.php:` и `C2 count=1`.

## 6. Гейты композиции: provider-size и время жизни

`scripts/provider-size.json` после P05.2: `"max_bindings": 8`, `"forbid_anonymous_classes": true`,
`"single_return_factories": true`, `"registrar_globs": ["*/src/**/Laravel/*Registrar.php"]`; `ratchet` — фактические
числа (разделённые providers — `0`); `note` удалён.

| Правило | Где | Находка |
|:--|:--|:--|
| S1 | provider | `S1 <file> singleton=<n> … total=<n> max_bindings=8` |
| S2 | provider, registrar | `S2 <file>:<line> anonymous class` |
| S3 | provider, registrar | `S3 <file>:<line> factory closure is not a single return` |

```php
// S3: допустимо
$app->scoped(Outbox::class, static fn (Application $app): SqlOutbox => new SqlOutbox(/* аргументы из контейнера */));
$app->singleton(X::class, static function (Application $app): X { return new X($app->make(Y::class)); });
// S3: нарушение → Laravel/IdempotencyStoreFactory::make()
$app->scoped(IdempotencyStore::class, static function (Application $app): SqlIdempotencyStore {
    $secret = $app['config']->get('app.key');
    if (! is_string($secret) || $secret === '') { throw new InvalidArgumentException('…'); }
    return new SqlIdempotencyStore(/* … */);
});
```

Негативные контроли в `tests/Arch/ProviderSizeTest.php`: проба на 9 биндингов (`total=9`, `max_bindings=8`); provider с
`new class` (`S2`); provider с двухоператорным замыканием (`S3`); registrar с двухоператорным замыканием (`S3`).
Проба «биндинги вне provider не считаются для S1» сохраняется.

`SingletonInjectionGate` (P05.2 — скан registrars; P05.3 — L2, L3):

| Правило | Нарушение | Находка |
|:--|:--|:--|
| L1 | конструктор singleton принимает `Application`, `Request`, config repository | существующий формат |
| L2 | конструктор singleton принимает тип, зарегистрированный как `scoped` | `L2 <class>::$<param> depends on scoped <type>` |
| L3 | конструктор singleton принимает `Illuminate\Database\Connection` или `ConnectionInterface` | `L3 <class>::$<param> captures a database connection` |

Эталон после P05.3: `violations === []` при `"entries": []` в `scripts/singleton-injection-allowlist.json`.
Негативные контроли в `tests/Arch/SingletonInjectionTest.php`: проба-registrar (L1), проба L2, проба L3.

## 7. Гейт runtime-globals

`scripts/runtime-globals.json`:

```json
{
    "bridge_globs": ["*/src/**/Laravel/**", "*/src/*ServiceProvider.php", "*/src/**/*ServiceProvider.php"],
    "testing_globs": ["*/src/**/Testing/**"],
    "phpstan_globs": ["*/src/**/PHPStan/**"],
    "helpers": ["app", "resolve", "now", "today", "event", "dispatch", "session", "request", "auth", "cache", "logger", "report", "abort", "url", "route", "base_path", "storage_path", "database_path", "config_path", "app_path"],
    "baseline_prefixes": ["packages/tenancy/src/Impersonation/"]
}
```

| Правило | Что ловит | Где разрешено |
|:--|:--|:--|
| G1 | `Illuminate\Support\Facades\*` (use или FQ) | bridge |
| G2 | вызов хелпера из `helpers` как функции (не метод, не `::`, не объявление) | bridge |
| G3 | `Carbon::now/today/parse`, `CarbonImmutable::…`, `new DateTimeImmutable()` без аргументов, `time()` | bridge |
| G4 | static-свойство класса, `static $x` в теле функции | testing |
| G5 | `new class` | нигде в `src` (phpstan — разрешено) |

Формат: `<RULE> <file>:<line> <token>`. Baseline `scripts/runtime-globals-baseline.json`:
`{"packages/tenancy/src/Impersonation/Internal/X.php": {"G2": 3, "G4": 1}}`.

Поведение: находка вне baseline → exit 1; число по файлу/правилу больше записанного → exit 1 (`baseline exceeded`);
меньше записанного → exit 1 (`baseline stale: lower it`); путь baseline вне `baseline_prefixes` → exit 2.

Эталон после P05.3:

```text
runtime-globals-gate: G1 0, G2 0, G3 0, G4 0, G5 0 outside baseline
runtime-globals-gate: baseline files=<n> findings=<m> (prefix packages/tenancy/src/Impersonation/)
runtime-globals-gate: OK
```

`<m>` — ориентир 24 по `facts/summary.txt`; точное число даёт токенный гейт.

## 8. Таблица замен (P05.3)

| Было | Стало (зависимость конструктора) |
|:--|:--|
| `DB::connection($n)->…` | `ConnectionResolverInterface $connections` → `$this->connections->connection($n)->…` в методе |
| `DB::table('t')`, `DB::select()`, `DB::transaction()`, `DB::afterCommit()` | `$this->connections->connection()->table('t')` и т.д. — `connection()` вызывается в момент операции |
| `DB::raw('…')` | `$this->connections->connection()->raw('…')` |
| `DB::getDefaultConnection()` | `$this->connections->getDefaultConnection()` |
| `Schema::hasTable()` и т.п. | `$this->connections->connection($n)->getSchemaBuilder()->hasTable()` |
| `now()`, `today()`, `Carbon::now()`, `new DateTimeImmutable()` | `ClockInterface $clock` → `$this->clock->now()`; Carbon API — `CarbonImmutable::instance($this->clock->now())` |
| `app(X::class)`, `resolve(X::class)` | `X $x`, если X живёт не меньше потребителя; иначе потребитель → `scoped` |
| `event(new E)` | `Illuminate\Contracts\Events\Dispatcher $events` → `$this->events->dispatch(new E)` |
| `dispatch($job)` | `Illuminate\Contracts\Bus\Dispatcher $bus` → `$this->bus->dispatch($job)` |
| `Cache::store($s)` | `Illuminate\Contracts\Cache\Factory $cache` → `$this->cache->store($s)` |
| `Http::…` | `Illuminate\Http\Client\Factory $http` |
| `Auth::guard()`, `auth()` | `Illuminate\Contracts\Auth\Factory $auth` |
| `session()`, `request()` | `Illuminate\Http\Request $request` аргументом метода HTTP-слоя; `$request->session()` |
| `Log::…`, `logger()` | `Psr\Log\LoggerInterface $logger` |
| `Crypt::…` | `Illuminate\Contracts\Encryption\Encrypter $encrypter` |
| `Redis::…` | `Illuminate\Contracts\Redis\Factory $redis` |
| `report($e)` | `Illuminate\Contracts\Debug\ExceptionHandler $exceptions` → `->report($e)` |
| `abort(404)` | `throw new Symfony\Component\HttpKernel\Exception\HttpException(404)` |
| `base_path('x')`, `storage_path()` | `string $path`, заданный в registrar |

Before/after:

```php
// БД — было
return DB::connection($this->connection)->table($this->table)->where('key', $key)->first();
// стало: resolver хранится, соединение берётся в момент операции
public function __construct(private readonly ConnectionResolverInterface $connections, /* … */) {}
return $this->connections->connection($this->connection)->table($this->table)->where('key', $key)->first();
// неверно: __construct(private readonly ConnectionInterface $connection) — соединение захвачено при создании сервиса

// время — было
$expires = now(); $expires->addMinutes($ttl);          // мутация Carbon
// стало
$expires = $this->clock->now()->modify("+{$ttl} minutes");

// опции — было (packages/core/src/Messaging/Internal/DatabasePollPubSub.php:89)
SubscribeGuard::assert($this->runtime, app(CoreOptions::class)->pubsubOctaneWorkerOptIn, self::class);
// стало
public function __construct(/* … */ private readonly CoreOptions $options) {}
SubscribeGuard::assert($this->runtime, $this->options->pubsubOctaneWorkerOptIn, self::class);

// PubSub — было: singleton(PubSub::class, PubSubManager::class), менеджер держит Application
// стало
$app->singleton(PubSub::class, static fn (Application $app): PubSub => $app->make(match ($app->make(CoreOptions::class)->pubsubDriver) {
    PubSubDriver::Redis => RedisPubSub::class,
    PubSubDriver::Database => DatabasePollPubSub::class,
    PubSubDriver::Null => NullPubSub::class,
}));
```

## 9. Сценарии резидентного режима

### 9a. Экземпляры и контекст (root, sqlite)

```php
// tests/Feature/ResidentLifecycleTest.php — контекст tenant задаётся так же, как в tests/Feature/TenantContextTest.php
test('scoped services and tenant context do not survive a lifecycle boundary', function (): void {
    $options = $this->app->make(CoreOptions::class);

    enterTenant($this->app, 'tenant-a');            // helper файла: тот же приём, что в TenantContextTest
    $first = [
        Outbox::class => $this->app->make(Outbox::class),
        TenantStorage::class => $this->app->make(TenantStorage::class),
        TenantContextRestorer::class => $this->app->make(TenantContextRestorer::class),
    ];

    $this->app->forgetScopedInstances();            // то, что делает резидентный воркер между запросами

    enterTenant($this->app, 'tenant-b');
    foreach ($first as $abstract => $instance) {
        expect($this->app->make($abstract))->not->toBe($instance);
    }

    expect(currentTenantId($this->app))->toBe('tenant-b')
        ->and($this->app->make(CoreOptions::class))->toBe($options);
});
```

Mutation-кейс `scripts/mutations/P05-scoped-to-singleton.patch`: `scoped(TenantStorage::class` → `singleton(TenantStorage::class`;
реестр `mutation-check.sh`: `[P05-scoped-to-singleton]="vendor/bin/pest --exclude-group=pg tests/Feature/ResidentLifecycleTest.php"`.
Ожидание: `bash scripts/mutation-check.sh P05 scoped-to-singleton` — тест краснеет под патчем, дерево восстановлено.

### 9b. Фактическая база после смены tenant (tenancy, PG)

```php
// packages/tenancy/tests/Pg/ResidentTenantSwitchPgTest.php — группа pg; два account и их БД создаются харнессом
// существующих тестов этого каталога; sys_*-таблицы ядра есть в обеих tenant-базах (при отсутствии — загрузить
// packages/core/database/migrations/pg в обе).
test('a write after a lifecycle boundary lands in the database of the current tenant', function (): void {
    [$a, $b] = twoProvisionedAccounts();            // helper файла поверх существующего харнесса

    $this->enter($a);
    app(SettingsRepository::class)->set('resident.marker', 'A');      // singleton-путь
    app(Outbox::class)->publish(markerMessage('A'));                  // scoped-путь

    $this->app->forgetScopedInstances();

    $this->enter($b);
    app(SettingsRepository::class)->set('resident.marker', 'B');
    app(Outbox::class)->publish(markerMessage('B'));

    expect(markersIn($a))->toBe(['settings' => 'A', 'outbox' => ['A']])
        ->and(markersIn($b))->toBe(['settings' => 'B', 'outbox' => ['B']])
        ->and(markersInCentral())->toBe(['settings' => null, 'outbox' => []]);
});
```

`markersIn()` читает напрямую по явному имени соединения базы account, минуя проверяемые сервисы. Точные методы
`SettingsRepository`/`Outbox` и вход в tenant берутся из их контрактов и существующих PG-тестов tenancy.
Красный тест — дефект времени жизни (досье §5), а не повод менять утверждения.
