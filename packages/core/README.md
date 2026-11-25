# axiomasoft/corex

Ядро CoreX для любого Laravel-приложения: `BaseModel` (ключи UUIDv7), `Settings` (каскад
System→Tenant→User), append-only `Audit`, tenant-контекст, департаменты (ltree),
фичефлаги, PubSub-контракт, адаптация реестра сущностей Packman и контракты отложенного пробуждения
`CoreX\Scheduling` (исполнение — пакет `corex-scheduler`). MIT.

Пакет ставится как зависимость приложения-потребителя; публичные контракты —
в `<Module>/Contracts/`; DTO помечены `#[Api]`, адаптеры интеграции — `#[Spi]`.
Классы под `Internal` принадлежат своему модулю и не являются контрактом приложения.
Атрибуты импортируются из `Packman\Attributes`; `#[Internal]` явно обозначает
реализацию. Общие config/error contracts также импортируются из Packman напрямую.
`php artisan packman:config:validate` объединяет отчёты установленных пакетов,
проверяет runtime configuration и скрывает значения секретов.

## Требование к СУБД

Ядро **требует PostgreSQL 16** для тенантской БД: `sys_*`-миграции используют
`ltree`/BRIN/`jsonb`/`UNIQUE NULLS NOT DISTINCT`/`pg_advisory_xact_lock` —
PG-нативные конструкции без SQLite-эквивалента. SQLite-лейн (`composer test`)
покрывает только pure-PHP код пакета для удобства unit-тестирования — это
НЕ заявленная поддержка SQLite в продакшене (OQ-12). Все гарантии
целостности/конкурентности проверяются исключительно на PG-лейне
(`composer test:pg`).

## Установка схемы

Настройки публикуются через `php artisan vendor:publish --tag=corex-config`.
Набор `sys_*` принадлежит CoreX; `mod_*` — Packman Modules. Оба исполняет Packman на PostgreSQL tenant-соединении:

```bash
php artisan packman:schema:migrate --scope=tenant --database=<tenant>
php artisan packman:schema:status --scope=tenant --database=<tenant>
```

В single-DB установке можно опустить `--database`: используется default connection.
В DB-per-account tenant-схема создаётся только на базе аккаунта; central-схема принадлежит tenancy.
Модульные наборы создаёт `ModuleLifecycle::enable()`; `--modules` явно включает их при подготовке шаблона.
Для production команда требует подтверждения или `--force`. Обычный `artisan migrate` создаёт только схему приложения.

### Версия схемы и апгрейды

Поколение каждого набора доступно через `Packman\Schema\Data\Migration\SchemaSet::version` в реестре
`Packman\Schema\Contracts\Migration\SchemaSets`. Файлы исполняются из пакета; копии в приложении не нужны.
После фиксации baseline изменение схемы поставляется новым файлом с повышением версии набора.
Повторный `packman:schema:migrate` применяет только отсутствующие в журнале Laravel `migrations` файлы.

## Доверенный scope для worker

Задайте `corex.scope.deployment_id` и подключение `corex.scope.control_connection`.
Control store должен находиться за пределами backup и restore тенантской БД, в том числе
в boxed-установке. Установите его схему через `ScopeControlSchema::install()` и
зарегистрируйте активную привязку каждого аккаунта у владельца `ScopeAuthority`.
Хост реализует `ControlStoreIsolation` и проверку владельца физического соединения.
В cloud-профиле реализацию проверки связывает Tenancy через `TenantBindingAuthority`.

`DataScopeFactory` выдаёт scope от аутентифицированного principal текущего account/workspace.
Хост проверяет живое членство и именованные service grants через `WorkPrincipalAuthority`.
Для операций из консоли или центрального обработчика хост явно устанавливает именованный
grant через `NamedWorkPrincipal`. Декларируйте tenant и central job handlers в
`corex.scope.tenant_jobs` и `corex.scope.central_jobs` или в `WorkQueueHandlers`.
Локальная durable работа сохраняет proof от `StoredWorkIssuer` вместе со своей записью.

Перед переносом или восстановлением закройте admission в control store, дождитесь
завершения tickets и разрешите все неизвестные результаты. После проверки новой
физической привязки откройте admission с новым поколением или restore epoch.
Worker проверяет эту границу до получения доступа к обработчику.


## Mandatory support audit

Mandatory support-access events use `CoreX\Audit\Contracts\SynchronousAuditWriter`.
The stock writer commits directly to the configured audit table, requires an event-time
snapshot and rejects ambient transactions. It bypasses the optional audit logger and queue.
Custom implementations must provide the same committed, fail-closed behavior.

## Точность времени

Все `timestamptz`-колонки объявлены с явной микросекундной точностью
(`precision: 6`): PostgreSQL при `timestamp(0)` **округляет** значение, что
исказило бы хронологический порядок в юридически значимом append-only аудите
(`sys_audit_log`). `sys_audit_log.seq bigserial` — монотонный курсор аудита:
keyset-пагинация и tie-break сортировки идут **только по `seq`**, а не по
`id`/`occurred_at` (часы принявшего узла монотонности не дают).

## Provider composition

Public providers are thin composition roots: they merge configuration, publish assets and register commands, and
delegate every binding to `PackageRegistrar` services listed in a `REGISTRARS` constant. A registrar keeps the
original binding order and lifetimes; a factory closure in a provider or registrar is a single `return`, and
anything with branching moves to a named `<Thing>Factory`. `CoreServiceProvider` registers `ModulesServiceProvider`
itself, so applications list only `CoreX\CoreServiceProvider`.

## Configuration report contributions

`packman:config:validate` collects services tagged with `Packman\Config\Contracts\ConfigReportSectionInterface`.
Each package owns its options types, secret keys and defaults, drivers and adapters. Sections
supply an integer order; the command retains its stable report format and redacts secret values.
Kernel installations require no optional package to build the report.

Each package validates the shape of its configuration once, when its provider boots, through an
internal `<Pkg>Options` type; the shape check passes on an empty environment, so an image build
(`package:discover`, `config:cache`) needs no secrets. Requirements of the running process are
separate `Packman\Config\Contracts\RuntimeConfigCheck` services tagged by the owning package: they
run when the HTTP application finishes booting or a console command starts (build commands such as
`package:discover`, `config:cache`, `optimize` and `packman:config:validate` are exempt). In
`production`, setting `corex-workflow.enforce_declarations=false` requires its exact config path
in `corex.acknowledged_dangerous_modes`. Patterns are rejected. `packman:config:validate`
runs the same checks (`--production` evaluates them as
production), prints acknowledged modes as warnings and the `corex-operations.require_physical_tenancy=false`
opt-out as a notice, and exits non-zero on a violation.

Consumer tests can create messaging adapters through
`CoreX\Messaging\Testing\MessagingFixtures`; the factory returns the public messaging contracts.

The module runtime belongs to `axiomasoft/packman-modules`; CoreX contributes context, audit and feature adapters. See the [package map](../../docs/architecture/package-map.md).

## JSON в публичных DTO

Произвольные JSON-данные передаются как неизменяемый `CoreX\Data\Data\JsonDocument`.
Интеграционный адаптер `CoreX\Data\Laravel\JsonDocuments` проверяет входное дерево
и сохраняет различие целых и дробных чисел. Объекты, ресурсы и бесконечные числа
не принимаются. Сохранённый JSON и HTTP-представление продолжают содержать обычные
JSON-значения.

```php
use CoreX\Data\Laravel\JsonDocuments;

$document = JsonDocuments::capture(['enabled' => true, 'weight' => 1.5]);
$value = JsonDocuments::map($document);
```

`SettingsRepository::get()`/`FeatureFlags::value()` возвращают JSON-документ;
`SettingsRepository::set()` принимает его. `CoreOptions` — неизменяемый снимок
текущей конфигурации: новое разрешение из контейнера видит её актуальное состояние.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Схема

Набор `tenant`, baseline v1 (PostgreSQL). Он содержит таблицы `sys_*`; `mod_*` предоставляет `axiomasoft/packman-modules:tenant`; исполнение — `packman:schema:migrate --scope=tenant`. Миграции не публикуются.
