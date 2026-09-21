# Дизайн фазы P7 — типизированные Options вместо runtime `config()`

**Статус:** нормативный вход items P7.1–P7.12. Это **источник готового кода
Options, policy, SecretRef, команды и гейта**, а не эскиз. Каждый item
называет здесь свой параграф; спека без указателя — дефект дизайна.

**Что этот документ НЕ делает.** Он не переименовывает ключи (BC). Он не
переносит runtime-настройки тенанта в Laravel config. Он не дробит
провайдеры (P8). Он не меняет дефолты. Он не исполняется до конца
стадии 2 (D14).

**Входы:** `01-shapes-runtime.md` §4; `P1-design.md` §2.8 и §9; `P2-design.md`
§6; `P3-design.md` §8.2; `P5-design.md` блок `corex.messaging`;
`P7-design-rag.md`; `00-baseline-metrics.md` §2–§3; A75, A76, N12;
CFG-001…CFG-006; D38–D41; листинг `packages/*/src` (152 `config(`).

---

## 0. Инварианты, обязательные для всех items фазы

1. **Язык — русский**, идентификаторы классов и ключей — латиницей.
2. **Ширина строки ≤ 100 символов.** Таблицы могут её превышать, проза — нет.
3. **Ключи конфигурации не переименовываются.** Меняется только путь чтения.
4. **`fromArray()` не вызывает `config()`** (`RAG:✅` P7-Q1).
5. **Нарушения накапливаются** и выбрасываются одним
   `InvalidConfiguration` со всем списком.
6. **Негативная проверка обязательна** у гейта и у команды redaction
   (`RAG:✅` P7-Q5, P7-Q2).
7. **Один пакет на item**, кроме корневого гейта в `scripts/` + `tests/Arch`.
8. **Маркеры:** факт о репозитории — `RAG:—`; внешняя практика — `RAG:✅`
   со ссылкой на `P7-design-rag.md`. Из `audits/*` не копируется ни код,
   ни формулировка.
9. **CHANGELOG.** Item, меняющий продукт, дописывает одну строку в корневой
   `CHANGELOG.md` под `[Unreleased]` → `### Added`.
10. **Валидная конфигурация не меняет поведение.** Доказывается пакетным
    сьютом затронутого пакета.
11. **Новых runtime-зависимостей нет.** OptionsResolver как пакет не
    вводится (`RAG:✅` P7-Q1: резолвер ≠ DTO).
12. **Секрет в сообщении об ошибке и в выводе команды отсутствует.**

---

## 1. Несущие решения детализации

| ID | Решение | Где в коде |
|:--|:--|:--|
| D38 | Options только у пакетов с `config()` в `src`; CRM — allowlist | §2, §10, §13 |
| D39 | `Config` — holder над `CoreOptions`, не читатель `config()` | §5 |
| D40 | `SecretRef` + boot-отказ по форме; команда не заменяет boot | §4, §12 |
| D41 | Impersonation/lifecycle/status — policy, не bag | §6, §7 |

Эскиз `01-shapes-runtime.md` §4 **принят**. Расширения: `SensitiveParameter`
(D40); E-CONFIG-HOLDER (D39); отказ от Options на пакеты без чтений (D38).

---

## 2. Карта ключей и классов (CFG-001)

`RAG:—` 18 файлов `packages/*/config/*.php`; 152 `config(` в `src`.
Класс ключа — правило P2.4 §6.2, применённое вручную к измеренным чтениям.
`runtime-settings` в Laravel config **нет и не появится**: слой Settings
уже существует.

### 2.1 Пакеты с чтениями — в скоупе P7

| Пакет | `config(` в src | Файл(ы) | Options |
|:--|--:|:--|:--|
| tenancy | 75 | `tenancy.php`, `corex-tenancy.php` | `TenancyOptions` + 3 policy |
| core | 41 | `corex.php` | `CoreOptions`; `Config::bind` |
| auth | 18 | `corex-auth.php` | `AuthOptions` |
| modules | 11 | `corex-modules.php` | `ModulesOptions` |
| media | 6 | `corex-media.php` | `MediaOptions` |
| crm | 1 | нет `corex-*` | нет; E-FLEX-CRM-SEARCH-LANGUAGE |

### 2.2 Ядро `corex.*`

| Ключ | Класс | Отказ |
|:--|:--|:--|
| `ids.strategy` | product-policy | boot: не значение `IdStrategy` |
| `tables.*` | deployment | boot: пустая / не `[a-z0-9_]+` |
| `tenant_context.*` | product-policy | boot: класс не существует |
| `bindings.*` | product-policy | boot: класс не существует |
| `storage.*` | deployment | boot: пустой путь |
| `views.connection` | deployment | boot: нет (null = default) |
| `audit.enabled` | product-policy | boot: не bool |
| `settings.cache_*` | deployment | boot: ttl < 0 |
| `pubsub.driver` | product-policy | boot: не `PubSubDriver` |
| `pubsub.poll_interval_microseconds` | product-policy | boot: ≤ 0 |
| `pubsub.octane_worker_opt_in` | product-policy | boot: не bool |
| `pubsub.retention` | product-policy | boot: не `DateInterval` |
| `messaging.*` (P5.4) | product-policy | boot: те же правила; отсутствие блока = дефолты P5 |

Реальных `config()` ядра вне провайдера и `Config.php`: три в
`SqlSavedViewRepository` плюс совпадения в комментариях/строках
(`SubscribeGuard`, `CoreXTestCase`, `IdColumns`, `HasConfiguredId`).
Гейт считает токены, не текст.

### 2.3 Tenancy `tenancy.*` и `corex-tenancy.wakeup.*`

| Ключ | Класс | Отказ |
|:--|:--|:--|
| `central_connection` | deployment | boot: пустая строка |
| `oidc.client_secret` | secret | boot: если `oidc.enabled` — пустой секрет |
| `oidc.impersonation.*` | product-policy | boot: не bool |
| `trial_days`, `grace_days` | product-policy | boot: < 0 |
| `bootstrapper_order` | product-policy | boot: пустой список / не class-string |
| `wakeup.driver` | deployment | boot: не `local_sweep`/`root_registry` |
| `wakeup.root_connection` | deployment | boot: пусто при `root_registry` |

Полный перечень полей — §6.1. `stancl.pin` — справочный, Options его
не валидирует (это pin Composer, не runtime).

### 2.4 Auth / modules / media

Auth: `panel`, `cache.store`, `bind_permission_layer`,
`workspace_scope_class`, `identity.*`, `azguard_pins.*` — смесь
deployment (`env`) и product-policy. `identity.columns.password` — **имя
колонки**, не секрет (класс product-policy).

Modules: `connection`, `cache.store/ttl/prefix` — deployment.

Media: лимиты и MIME — product-policy; boot: `max_upload_bytes` ≤ 0,
пустой `allowed_mime_types`.

### 2.5 Секреты вне скоупа Options (D38)

| Ключ | Почему не Options |
|:--|:--|
| `corex-intl.reference.cursor_secret` | 0 чтений в `src`; дефолт в дереве — warning команды |
| `corex-search.cursor_secret` | 0 чтений |
| `corex-search-meilisearch.key` | 0 чтений; `secret=yes` по эвристике P2.4 |
| `flex-crm.search_language` | чужой ключ в `crm` |

---

## 3. Шаблон фабрики (все пакеты)

`RAG:✅` P7-Q1. Готовый код — переносится дословно.

```php
<?php

declare(strict_types=1);

namespace CoreX\Support;

use InvalidArgumentException;

final class ViolationList
{
    /** @var list<string> */
    private array $items = [];

    public function add(string $path, string $message): void
    {
        $this->items[] = $path.': '.$message;
    }

    public function failIfAny(): void
    {
        if ($this->items === []) {
            return;
        }

        throw new InvalidConfiguration($this->items);
    }
}

final class InvalidConfiguration extends InvalidArgumentException
{
    /** @param list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(
            'Invalid configuration ('.count($violations).'): '.implode('; ', $violations),
        );
    }
}
```

Правила фабрики:

- принимает `array<string, mixed>`, не контейнер и не `config()`;
- каждое поле нормализуется и проверяется независимо;
- секрет в `$message` не интерполируется (только путь ключа);
- после `failIfAny()` — `new self` с named arguments.

Тест шаблона (живёт в `packages/core/tests`):

1. две ошибки сразу — сообщение содержит обе;
2. валидный массив проходит без контейнера;
3. секретное значение, переданное в `fromArray`, в `getMessage()`
   отсутствует.

---

## 4. `SecretRef` (D40)

```php
<?php

declare(strict_types=1);

namespace CoreX\Support;

use LogicException;
use SensitiveParameter;
use Stringable;

final class SecretRef implements Stringable
{
    public function __construct(
        #[SensitiveParameter]
        private readonly string $value,
    ) {}

    public function reveal(): string
    {
        return $this->value;
    }

    public function isPresent(): bool
    {
        return $this->value !== '';
    }

    public function __toString(): string
    {
        return '[redacted]';
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /** @return array<string, string> */
    public function __serialize(): array
    {
        throw new LogicException('SecretRef must not be serialized.');
    }
}
```

`reveal()` не несёт атрибут на возвращаемом значении — атрибут стоит на
входе конструктора. Тесты P7.12: интерполяция, `(string)`, `var_dump`
через `ob_start`, `serialize` бросает, `json_encode` не содержит
plaintext (объект без `JsonSerializable` кодируется как `{}` или
ошибка — assert plaintext отсутствует).

---

## 5. `CoreOptions` и `Config::bind` (P7.1, D39)

`RAG:—` по D101 exact-путь `packages/core/src/Support/CoreOptions.php`
входит в закрытый `COMPOSITION`, а не в Foundation. Это необходимо:
`fromArray()` ниже задаёт defaults из Audit, Settings, Departments, Features и
TenancyContracts. Классификация точечная: `ALLOWED['Foundation']` остаётся
пустым, а неизвестный `Support/*` по-прежнему падает по R-Z0.
Будущий combined P6+P7+P8 witness принимает эти объявленные defaults,
но по-прежнему отклоняет Foundation → SavedViews/Internal и
`Support/Probe.php`.

### 5.1 Класс

```php
<?php

declare(strict_types=1);

namespace CoreX\Support;

use CoreX\Audit\NullAuditRetention;
use CoreX\Audit\QueuedAuditLogger;
use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\AuditRetention;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Contracts\DepartmentRepository;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Departments\DatabaseDepartmentRepository;
use CoreX\Enums\IdStrategy;
use CoreX\Enums\PubSubDriver;
use CoreX\Features\DatabaseFeatureFlags;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Settings\NullSettingDefaultsProvider;
use CoreX\Support\Data\DenyDataScopeAuthorizer;
use CoreX\Tenancy\Contracts\ImpersonationService;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\NullImpersonationService;
use CoreX\Tenancy\NullTenancyManager;
use CoreX\Tenancy\SingleAccountResolver;
use DateInterval;
use Throwable;

final readonly class CoreOptions
{
    /**
     * @param class-string<TenantContextResolver> $tenantContextResolver
     * @param class-string<TenancyManager> $tenancyManager
     * @param class-string<ImpersonationService> $impersonationService
     * @param class-string<SettingsRepository> $settingsRepository
     * @param class-string<AuditLogger> $auditLogger
     * @param class-string<AuditRetention> $auditRetention
     * @param class-string<SettingDefaultsProvider> $settingDefaultsProvider
     * @param class-string<FeatureFlags> $featureFlags
     * @param class-string<DepartmentRepository> $departmentRepository
     * @param class-string<DataScopeAuthorizer> $dataScopeAuthorizer
     */
    public function __construct(
        public IdStrategy $idStrategy,
        public string $settingsTable,
        public string $auditLogsTable,
        public string $featureFlagsTable,
        public string $featureOverridesTable,
        public string $departmentsTable,
        public string $departmentUserTable,
        public string $pubsubMessagesTable,
        public string $pubsubCursorsTable,
        public string $savedViewsTable,
        public string $savedViewScopesTable,
        public string $outboxMessagesTable,
        public string $inboxMessagesTable,
        public string $tenantContextResolver,
        public string $tenancyManager,
        public string $impersonationService,
        public string $settingsRepository,
        public string $auditLogger,
        public string $auditRetention,
        public string $settingDefaultsProvider,
        public string $featureFlags,
        public string $departmentRepository,
        public string $dataScopeAuthorizer,
        public string $storageDurableRoot,
        public string $storageEphemeralRoot,
        public ?string $viewsConnection,
        public bool $auditEnabled,
        public string $settingsCacheStore,
        public ?int $settingsCacheTtl,
        public string $settingsCachePrefix,
        public PubSubDriver $pubsubDriver,
        public int $pubsubPollIntervalMicroseconds,
        public bool $pubsubOctaneWorkerOptIn,
        public string $pubsubRetention,
        public int $messagingLeaseSeconds,
        public int $messagingBatchSize,
        public int $messagingMaxAttempts,
        public int $messagingBackoffCapSeconds,
        public string $messagingOutboxRetention,
        public string $messagingDeadRetention,
        public ?string $messagingInboxRetention,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $v = new ViolationList();
        $tables = is_array($config['tables'] ?? null) ? $config['tables'] : [];
        $tenant = is_array($config['tenant_context'] ?? null) ? $config['tenant_context'] : [];
        $bindings = is_array($config['bindings'] ?? null) ? $config['bindings'] : [];
        $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
        $views = is_array($config['views'] ?? null) ? $config['views'] : [];
        $audit = is_array($config['audit'] ?? null) ? $config['audit'] : [];
        $settings = is_array($config['settings'] ?? null) ? $config['settings'] : [];
        $pubsub = is_array($config['pubsub'] ?? null) ? $config['pubsub'] : [];
        $messaging = is_array($config['messaging'] ?? null) ? $config['messaging'] : [];
        $ids = is_array($config['ids'] ?? null) ? $config['ids'] : [];

        $idStrategy = self::enum($v, 'ids.strategy', $ids['strategy'] ?? IdStrategy::Uuid7, IdStrategy::class);
        $settingsTable = self::table($v, 'tables.settings', $tables['settings'] ?? 'sys_settings');
        $auditLogsTable = self::table($v, 'tables.audit_logs', $tables['audit_logs'] ?? 'sys_audit_log');
        $featureFlagsTable = self::table($v, 'tables.feature_flags', $tables['feature_flags'] ?? 'sys_feature_flags');
        $featureOverridesTable = self::table($v, 'tables.feature_overrides', $tables['feature_overrides'] ?? 'sys_feature_overrides');
        $departmentsTable = self::table($v, 'tables.departments', $tables['departments'] ?? 'sys_departments');
        $departmentUserTable = self::table($v, 'tables.department_user', $tables['department_user'] ?? 'sys_department_user');
        $pubsubMessagesTable = self::table($v, 'tables.pubsub_messages', $tables['pubsub_messages'] ?? 'sys_pubsub_messages');
        $pubsubCursorsTable = self::table($v, 'tables.pubsub_cursors', $tables['pubsub_cursors'] ?? 'sys_pubsub_cursors');
        $savedViewsTable = self::table($v, 'tables.saved_views', $tables['saved_views'] ?? 'sys_saved_views');
        $savedViewScopesTable = self::table($v, 'tables.saved_view_scopes', $tables['saved_view_scopes'] ?? 'sys_saved_view_scopes');
        $outboxMessagesTable = self::table($v, 'tables.outbox_messages', $tables['outbox_messages'] ?? 'sys_outbox_messages');
        $inboxMessagesTable = self::table($v, 'tables.inbox_messages', $tables['inbox_messages'] ?? 'sys_inbox_messages');

        $tenantContextResolver = self::classString($v, 'tenant_context.resolver', $tenant['resolver'] ?? SingleAccountResolver::class, TenantContextResolver::class);
        $tenancyManager = self::classString($v, 'tenant_context.manager', $tenant['manager'] ?? NullTenancyManager::class, TenancyManager::class);
        $impersonationService = self::classString($v, 'tenant_context.impersonation', $tenant['impersonation'] ?? NullImpersonationService::class, ImpersonationService::class);
        $settingsRepository = self::classString($v, 'bindings.settings', $bindings['settings'] ?? DatabaseSettingsRepository::class, SettingsRepository::class);
        $auditLogger = self::classString($v, 'bindings.audit_logger', $bindings['audit_logger'] ?? QueuedAuditLogger::class, AuditLogger::class);
        $auditRetention = self::classString($v, 'bindings.audit_retention', $bindings['audit_retention'] ?? NullAuditRetention::class, AuditRetention::class);
        $settingDefaultsProvider = self::classString($v, 'bindings.setting_defaults', $bindings['setting_defaults'] ?? NullSettingDefaultsProvider::class, SettingDefaultsProvider::class);
        $featureFlags = self::classString($v, 'bindings.feature_flags', $bindings['feature_flags'] ?? DatabaseFeatureFlags::class, FeatureFlags::class);
        $departmentRepository = self::classString($v, 'bindings.departments', $bindings['departments'] ?? DatabaseDepartmentRepository::class, DepartmentRepository::class);
        $dataScopeAuthorizer = self::classString($v, 'bindings.data_scope_authorizer', $bindings['data_scope_authorizer'] ?? DenyDataScopeAuthorizer::class, DataScopeAuthorizer::class);

        $durable = self::nonEmpty($v, 'storage.durable_root', $storage['durable_root'] ?? 'storage/app/corex/durable');
        $ephemeral = self::nonEmpty($v, 'storage.ephemeral_root', $storage['ephemeral_root'] ?? 'storage/app/corex/ephemeral');
        $viewsConnection = isset($views['connection']) && $views['connection'] !== null && $views['connection'] !== ''
            ? (string) $views['connection']
            : null;
        $auditEnabled = self::bool($v, 'audit.enabled', $audit['enabled'] ?? true);
        $cacheStore = self::nonEmpty($v, 'settings.cache_store', $settings['cache_store'] ?? 'array');
        $cacheTtl = self::nullableNonNegativeInt(
            $v,
            'settings.cache_ttl',
            array_key_exists('cache_ttl', $settings) ? $settings['cache_ttl'] : 3600,
        );
        $cachePrefix = self::nonEmpty($v, 'settings.cache_prefix', $settings['cache_prefix'] ?? 'corex.settings');
        $pubsubDriver = self::enum($v, 'pubsub.driver', $pubsub['driver'] ?? PubSubDriver::Null, PubSubDriver::class);
        $poll = self::positiveInt($v, 'pubsub.poll_interval_microseconds', $pubsub['poll_interval_microseconds'] ?? 50_000);
        $optIn = self::bool($v, 'pubsub.octane_worker_opt_in', $pubsub['octane_worker_opt_in'] ?? false);
        $retention = self::interval($v, 'pubsub.retention', $pubsub['retention'] ?? '30 days');
        $lease = self::positiveInt($v, 'messaging.lease_seconds', $messaging['lease_seconds'] ?? 120);
        $batch = self::positiveInt($v, 'messaging.batch_size', $messaging['batch_size'] ?? 100);
        $attempts = self::positiveInt($v, 'messaging.max_attempts', $messaging['max_attempts'] ?? 8);
        $backoff = self::positiveInt($v, 'messaging.backoff_cap_seconds', $messaging['backoff_cap_seconds'] ?? 300);
        $outboxRetention = self::interval($v, 'messaging.outbox_retention', $messaging['outbox_retention'] ?? '14 days');
        $deadRetention = self::interval($v, 'messaging.dead_retention', $messaging['dead_retention'] ?? '90 days');
        $inboxRetention = array_key_exists('inbox_retention', $messaging) && $messaging['inbox_retention'] !== null
            ? self::interval($v, 'messaging.inbox_retention', $messaging['inbox_retention'])
            : null;

        $v->failIfAny();

        return new self(
            idStrategy: $idStrategy,
            settingsTable: $settingsTable,
            auditLogsTable: $auditLogsTable,
            featureFlagsTable: $featureFlagsTable,
            featureOverridesTable: $featureOverridesTable,
            departmentsTable: $departmentsTable,
            departmentUserTable: $departmentUserTable,
            pubsubMessagesTable: $pubsubMessagesTable,
            pubsubCursorsTable: $pubsubCursorsTable,
            savedViewsTable: $savedViewsTable,
            savedViewScopesTable: $savedViewScopesTable,
            outboxMessagesTable: $outboxMessagesTable,
            inboxMessagesTable: $inboxMessagesTable,
            tenantContextResolver: $tenantContextResolver,
            tenancyManager: $tenancyManager,
            impersonationService: $impersonationService,
            settingsRepository: $settingsRepository,
            auditLogger: $auditLogger,
            auditRetention: $auditRetention,
            settingDefaultsProvider: $settingDefaultsProvider,
            featureFlags: $featureFlags,
            departmentRepository: $departmentRepository,
            dataScopeAuthorizer: $dataScopeAuthorizer,
            storageDurableRoot: $durable,
            storageEphemeralRoot: $ephemeral,
            viewsConnection: $viewsConnection,
            auditEnabled: $auditEnabled,
            settingsCacheStore: $cacheStore,
            settingsCacheTtl: $cacheTtl,
            settingsCachePrefix: $cachePrefix,
            pubsubDriver: $pubsubDriver,
            pubsubPollIntervalMicroseconds: $poll,
            pubsubOctaneWorkerOptIn: $optIn,
            pubsubRetention: $retention,
            messagingLeaseSeconds: $lease,
            messagingBatchSize: $batch,
            messagingMaxAttempts: $attempts,
            messagingBackoffCapSeconds: $backoff,
            messagingOutboxRetention: $outboxRetention,
            messagingDeadRetention: $deadRetention,
            messagingInboxRetention: $inboxRetention,
        );
    }

    // Хелперы table/enum/classString/bool/positiveInt/interval/nonEmpty/
    // nullableNonNegativeInt — private static в том же файле. interval:
    // try { new DateInterval('P'.…) } catch — add violation. Для
    // DateInterval-parseable relative strings ('30 days') — тот же разбор,
    // что сегодня у prune-команд: `DateInterval::createFromDateString`.
    // classString: class_exists + is_a($class, $contract, true).
    // enum: instanceof BackedEnum или TryFrom. При ошибке возвращают
    // значение по умолчанию только чтобы конструктор мог быть вызван
    // после failIfAny; до failIfAny объект не создаётся.
}
```

Хелперы исполнитель **дописывает** в том же файле по контракту выше;
сигнатуры фиксированы. `fromArray` при отсутствии блока `messaging`
подставляет дефолты P5 — P7 не зависит от терминальности P5.4.

### 5.2 Holder

```php
final class Config
{
    private static ?CoreOptions $bound = null;

    public static function bind(CoreOptions $options): void
    {
        self::$bound = $options;
    }

    public static function unbind(): void
    {
        self::$bound = null;
    }

    public static function options(): CoreOptions
    {
        if (self::$bound instanceof CoreOptions) {
            return self::$bound;
        }

        throw new \LogicException('CoreOptions is not bound; call Config::bind at boot.');
    }

    public static function idStrategy(): IdStrategy
    {
        return self::options()->idStrategy;
    }

    public static function settingsTable(): string
    {
        return self::options()->settingsTable;
    }

    // … каждый существующий аксессор читает одноимённое поле Options.
    // impersonationServiceClass() → options()->impersonationService
    // pubsubOctaneWorkerOptIn() → options()->pubsubOctaneWorkerOptIn
    // Новых config() в этом файле нет.
}
```

Существующие 25 публичных методов сохраняют сигнатуры. Добавляются
`bind`/`unbind`/`options` и аксессоры messaging/outbox, которые P5.4
планировал повесить сюда же.

### 5.3 Провайдер

В `register()` сразу после `mergeConfigFrom`:

```php
$raw = $this->app['config']->get('corex', []);
$options = CoreOptions::fromArray(is_array($raw) ? $raw : []);
$this->app->instance(CoreOptions::class, $options);
Config::bind($options);

$this->app->singleton(SettingDefaultsProvider::class, $options->settingDefaultsProvider);
// … все бывшие Config::fooClass() берутся с $options
$this->app->singleton(AuditRetention::class, $options->auditRetention);
```

Единственный `config()` ядра после P7.1 — `$this->app['config']->get`
(это тот же репозиторий; глобальный хелпер в провайдере допустим,
предпочтителен репозиторий — `RAG:✅` P7-Q1). Гейт разрешает
`*ServiceProvider.php`.

`SqlSavedViewRepository` хранит `CoreOptions` и injected Laravel database
manager/resolver. Он фиксирует только явный `views.connection` override.
Если override = null, каждая DB-операция разрешает текущий default через
manager/resolver. Имя default не сохраняется в singleton: Stancl меняет его
при tenant enter/leave. Global `config()` в репозитории по-прежнему запрещён.

Уточнение: добавить в `CoreOptions` не надо — биндинг:

```php
$this->app->singleton(SavedViewRepository::class, function ($app): SqlSavedViewRepository {
    return new SqlSavedViewRepository(
        options: $app->make(CoreOptions::class),
        database: $app->make('db'),
    );
});
```

Репозиторий не вызывает `config()`. Приёмка использует один instance и
цикл central→tenant→central; явный override остаётся неизменным во всех трёх шагах.

---

## 6. TenancyOptions и policy (P7.2, D41)

### 6.1 Поля корневого DTO

```php
final readonly class TenancyOptions
{
    /**
     * @param class-string $tenantModel
     * @param class-string $idGenerator
     * @param class-string $tenantDatabaseManager
     * @param list<class-string> $bootstrapperOrder
     * @param list<class-string> $provisioningPipeline
     * @param list<string> $oidcAllowedAlgs
     */
    public function __construct(
        public string $centralConnection,
        public string $tenantModel,
        public string $idGenerator,
        public string $tenantDatabaseManager,
        public array $bootstrapperOrder,
        public array $provisioningPipeline,
        public string $cachePrefix,
        public string $storageDurableDriver,
        public string $storageDurableRoot,
        public string $storageEphemeralRoot,
        public bool $hostIdentificationEnabled,
        public bool $hostIdentificationCache,
        public int $hostIdentificationCacheTtl,
        public ?string $hostIdentificationCacheStore,
        public bool $domainTrustEnabled,
        public int $domainTrustChallengeTtlSeconds,
        public StatusGatePolicy $statusGate,
        public bool $oidcEnabled,
        public ?string $oidcIssuer,
        public ?string $oidcClientId,
        public ?SecretRef $oidcClientSecret,
        public ?string $oidcRedirectUri,
        public array $oidcAllowedAlgs,
        public int $oidcLeewaySeconds,
        public string $oidcGuard,
        public ?string $oidcDiscoveryCacheStore,
        public int $oidcDiscoveryCacheTtl,
        public ?string $oidcJwksCacheStore,
        public int $oidcJwksCacheTtl,
        public int $oidcJwksRefetchCooldownSeconds,
        public ImpersonationPolicy $impersonation,
        public int $oidcMaxSessionLifetimeMinutes,
        public ?string $workspacePathSegment,
        public AccountLifecyclePolicy $lifecycle,
        public string $wakeupDriver,
        public string $wakeupRootConnection,
        public int $wakeupLeaseSeconds,
        public array $templateIntegrityTrustedKeys,
        public array $templateIntegrityManifests,
        public string $membershipGuard,
    ) {}
}
```

`fromArray` принимает **два** массива: `$tenancy` (`config('tenancy')`) и
`$corexTenancy` (`config('corex-tenancy')`). Провайдер:

```php
$tenancy = $app['config']->get('tenancy', []);
$wakeup = $app['config']->get('corex-tenancy', []);
$options = TenancyOptions::fromArray(
    tenancy: is_array($tenancy) ? $tenancy : [],
    corexTenancy: is_array($wakeup) ? $wakeup : [],
);
$app->instance(TenancyOptions::class, $options);
$app->instance(ImpersonationPolicy::class, $options->impersonation);
$app->instance(AccountLifecyclePolicy::class, $options->lifecycle);
$app->instance(StatusGatePolicy::class, $options->statusGate);
```

Cross-field: `oidc.enabled=true` требует непустые issuer, client_id,
redirect_uri и `SecretRef::isPresent()`. `wakeup.driver=root_registry`
требует непустой `wakeup.root_connection`. `bootstrapper_order` не пуст,
каждый элемент — существующий класс. `oidc.allowed_algs` по умолчанию
`['RS256']`, пустой список при enabled — violation.

Проекция на ключи stancl (`tenancy.models.tenant` и т.д.) остаётся в
`boot()` провайдера и читает **поля Options**, не `config()`, кроме
чтения чужого `tenancy.identification.middleware` для merge — это
ключ stancl, не наш. Если merge нужен, `$app['config']->get` в
провайдере допустим.

### 6.2 Policy-классы

```php
final readonly class ImpersonationPolicy
{
    public function __construct(
        public bool $enabled,
        public bool $requireApprovedBy,
    ) {}
}

final readonly class AccountLifecyclePolicy
{
    public function __construct(
        public int $trialDays,
        public int $graceDays,
        public string $platformDomain,
    ) {}
}

final readonly class StatusGatePolicy
{
    /**
     * @param list<string> $readOnlyMethods
     */
    public function __construct(
        public ?string $paymentRoute,
        public ?string $exportRoute,
        public array $readOnlyMethods,
    ) {}

    public function isReadOnlyMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->readOnlyMethods, true);
    }
}
```

`fromArray` нормализует методы в верхний регистр и отвергает пустой
список (иначе write-guard пропускает всё).

47 вызовов в `TenancyServiceProvider` заменяются чтением `$options`.
Глобальный `config()` в этом файле после item'а — только
`get('tenancy')` / `get('corex-tenancy')` / чужие ключи stancl.

---

## 7. Инъекция tenancy (P7.3–P7.5)

Правило: конструктор получает `TenancyOptions` или нужный policy.
Существующие зависимости сохраняются, Options добавляется аргументом
(named). Провайдерные замыкания уже имеют `$app` — `$app->make(...)`.

### 7.1 P7.3 — impersonation и status HTTP

| Файл | Что читал | Что получает |
|:--|:--|:--|
| `DatabaseImpersonationService` | require_approved_by, guard, central | `ImpersonationPolicy` + `TenancyOptions` (guard, connection) |
| `ImpersonationGrant` | central_connection | `TenancyOptions` |
| `ImpersonationSessionGuard` | central_connection | `TenancyOptions` |
| `DenyImpersonatedWrites` | status_gate.read_only_methods | `StatusGatePolicy` |
| `AccountStatusGate` | payment/export/read_only | `StatusGatePolicy` |
| `EnforceSessionLifetime` | max_session, guard | `TenancyOptions` |

### 7.2 P7.4 — lifecycle, database, host

| Файл | Что получает |
|:--|:--|
| `AccountLifecycle` | `AccountLifecyclePolicy` |
| `HostTenantResolver` | `TenancyOptions` (central) |
| `TemplateIntegrityVerifier` | `TenancyOptions` (trusted_keys, manifests) |
| `TemplateClonePostgreSQLManager` | `TenancyOptions` (central) |
| `CreateDatabaseFromTemplate` | `TenancyOptions` (central) |
| `RequireActiveMembership` | `TenancyOptions` (membershipGuard) |

`now()->addDays` в lifecycle **не** заменяется часами: это P4.9/P6 долг,
не P7.

### 7.3 P7.5 — workspace и OIDC-контроллеры

| Файл | Что получает |
|:--|:--|
| `ResolveWorkspace` | `TenancyOptions` (path_segment) |
| `OidcLoginController` | `ImpersonationPolicy` + `TenancyOptions` |
| `LogoutController` | `TenancyOptions` (guard) |

Команды `IdentitiesReconcileCommand` и `PendingCreateCommand`
остаются на composition-границе и **могут** читать Options из
контейнера (предпочтительно) либо оставить `config()` — CFG-002
разрешает команду. Спека P7.5 их не трогает: Files не влезают.

---

## 8. AuthOptions (P7.6–P7.7)

```php
final readonly class AuthOptions
{
    /**
     * @param array<string, string> $identityColumns
     * @param array<string, mixed> $azguardPins
     */
    public function __construct(
        public string $panel,
        public ?string $cacheStore,
        public bool $bindPermissionLayer,
        public ?string $workspaceScopeClass,
        public bool $identityEnabled,
        public ?string $identityConnection,
        public string $identityTable,
        public array $identityColumns,
        public array $azguardPins,
    ) {}
}
```

`fromArray` с ключа `corex-auth`. `panel` — непустая строка.
`identity.columns` — обязательные ключи `id`, `email`, `password`,
`active`, `deleted_at` (имена колонок). Пустой `workspace_scope_class`
нормализуется в `null`.

P7.6: класс + `CoreAuthServiceProvider` + `PrincipalUserProvider`
(кластер identity). Проекция `az-guard.*` остаётся в провайдере и
берёт `$options->azguardPins`.

P7.7: `DefaultAccessChecker`, `DirectGrantLookup`, `DefaultScopeResolver`,
`ScopePredicate`, `ScopeMapVersion`, `MatrixPermissionCatalogBuilder`,
`DatabaseRoleMatrixCompiler` — все читают `$this->options->panel` /
`cacheStore` / `workspaceScopeClass`.

Отдельный `PanelPolicy` не вводится (D41).

---

## 9. ModulesOptions и MediaOptions (P7.8, P7.9)

```php
final readonly class ModulesOptions
{
    public function __construct(
        public ?string $connection,
        public ?string $cacheStore,
        public int $cacheTtl,
        public string $cachePrefix,
    ) {}
}

final readonly class MediaOptions
{
    /**
     * @param list<string> $purposes
     * @param list<string> $allowedMimeTypes
     * @param array<string, mixed> $conversion
     */
    public function __construct(
        public int $maxUploadBytes,
        public int $maxImagePixels,
        public array $purposes,
        public array $allowedMimeTypes,
        public string $conversionDriverTag,
        public array $conversion,
    ) {}
}
```

Modules: ttl > 0, prefix непустой. Провайдер (10 вызовов) и
`RepositoryModuleRegistryCache` получают Options.

Media: `max_upload_bytes` > 0, `allowed_mime_types` не пуст.
`SqlMediaLibrary` и `ConversionCoordinator` получают Options.

---

## 10. Пакеты без чтений и CRM (D38)

Команда P7.10 читает их `config/*.php` через репозиторий (ей можно)
и применяет эвристику secret P2.4. Options не создаёт.

Allowlist гейта:

```json
"packages/crm/src/Support/RecordRules.php": "E-FLEX-CRM-SEARCH-LANGUAGE: flex-ключ, владелец P16"
```

Условие снятия: пакет `crm` перестаёт читать `flex-crm.*` или уходит
из `corex/*`.

---

## 11. Справочник `docs/architecture/configuration.md`

P2.4 создаёт каталог с пустым `typed_options_field`. P7 заполняет
колонку **только для ключей пяти пакетов** значениями вроде
`CoreOptions::$idStrategy`. Форма строки — путь свойства Options.
Item, вводящий Options, дописывает свои ключи в тот же документ
(после P2.4 он существует; до исполнения P2 — item пишет в тот же
путь, создание документа принадлежит P2.4, P7 только заполняет
колонку). Если файла ещё нет (стадия 3 не начата для P2), item
создаёт фрагмент `findings/P7-typed-options-map.md` и в Completion
Notes указывает, что перенос в `configuration.md` — первая работа
после терминальности P2.4. Предпочтительный путь: файл уже есть,
потому что исполнение идёт P2 → P3 → P7.

---

## 12. Команда `corex:config:validate` (P7.10)

Класс: `CoreX\Support\Console\ValidateConfigCommand`.
Сигнатура: `corex:config:validate {--format=text}`.

Алгоритм:

1. Для каждого зарегистрированного `*Options` в контейнере (явный
   список: Core, Tenancy, Auth, Modules, Media — `class_exists` +
   `bound`) вызвать `fromArray` в try и собрать нарушения.
2. Напечатать эффективный драйвер: `pubsub.driver`,
   `tenancy.wakeup.driver`, `tenancy.oidc.enabled`.
3. Доступность адаптера: выбранный `PubSubDriver::Redis` → класс
   Redis-драйвера существует; `wakeup.root_registry` → пакет
   `corex/wakeup` обнаружен по `class_exists`. Невыбранный
   отсутствующий адаптер — не ошибка.
4. Секреты: обойти известные пути
   (`tenancy.oidc.client_secret`, `corex-intl.reference.cursor_secret`,
   `corex-search.cursor_secret`, `corex-search-meilisearch.key`) и
   печать `present`/`absent`. Значение не выводится. Дефолтный
   hex intl — `WARNING [secret-default]`.
5. Exit 0 при нуле ошибок; 1 при любой ошибке `fromArray` или
   выбранном отсутствующем адаптере. Warning не валит без
   `--strict` (флаг не вводится в 1.0 — warnings в stdout, exit 0).

Тест: Options с `client_secret` = `super-secret-value-test`; вывод
команды и exception message не содержат этой строки.

Команда **может** вызывать `config()` — composition.

---

## 13. Блокирующий гейт (P7.11)

Тот же `scripts/config-composition-gate.php` из P3.6. JSON:

```json
{
  "blocking": true,
  "allow_globs": [
    "*/src/*ServiceProvider.php",
    "*/src/**/Commands/*.php",
    "*/src/Console/*.php"
  ],
  "allow_paths": {
    "packages/crm/src/Support/RecordRules.php": "E-FLEX-CRM-SEARCH-LANGUAGE"
  }
}
```

`Support/Config.php` из allowlist **удалён**. Сканер —
`token_get_all`, T_STRING `config` + `(`; комментарии и строки
пропускаются. Сообщение `C1 {file}:{line} config()`. Exit 1 при
любом C1.

Посадка: `packages/core/src/Support/ConfigCompositionProbe.php` с
`config('probe-key')`; вывод содержит имя файла; exit 1; удалить
в `finally`. Pest-тест гейта требует exit 1 на посадке и exit 0
на чистом дереве.

Команды tenancy, оставленные с `config()`, покрыты glob
`**/Commands/*.php`.

---

## 14. Карта items → параграфы

| Item | Параграф | Review |
|:--|:--|:--|
| P7.1 | §3, §4, §5 | light |
| P7.2 | §6 | light |
| P7.3 | §7.1 | light |
| P7.4 | §7.2 | light |
| P7.5 | §7.3 | light |
| P7.6 | §8 | light |
| P7.7 | §8 | light |
| P7.8 | §9 | light |
| P7.9 | §9 | light |
| P7.10 | §12 | full |
| P7.11 | §13 | full |
| P7.12 | §5.3 | full |

Порядок данных: `P7.1 → P7.2 → {P7.3, P7.4, P7.5}`; `{P7.6, P7.8, P7.9}`
после P7.1; `P7.7` после P7.6; `P7.10` после P7.2 (секрет tenancy);
`P7.12` после P7.1; `P7.11` последний и ждёт P7.12.

---

## 15. Ловушки

- Не считать совпадение `config(` в комментарии вызовом. Гейт — токены.
- Не подставлять `config('database.default')` в репозиторий «на минутку».
- Не печатать DSN: в нём бывают credentials (`RAG:✅` P7-Q3).
- Не менять дефолт intl cursor_secret — только warning.
- Не вызывать `Config::bind` из теста пакета, который не грузит
  провайдер: TestCase ядра уже загрузит. Чужой пакетный тест,
  конструирующий модель ядра, должен `Config::bind(CoreOptions::fromArray([]))`
  либо грузить провайдер — это уже так для `Config::idStrategy()`.
- `octane_worker_opt_in` остаётся в Options. Его по-прежнему выставляет
  worker команда через `config([...])`? Сегодня это ключ, который
  команда **пишет**. Запись `config([...])` — мутация репозитория,
  Q10 запрещает как изоляцию. P7 **не** вводит новый setter: worker
  по-прежнему делает `config(['corex.pubsub.octane_worker_opt_in' => true])`
  в команде (composition), но bound `CoreOptions` **не** следит за
  этим ключом в runtime. `SubscribeGuard` сегодня читает
  `Config::pubsubOctaneWorkerOptIn()`, которое после bind станет
  значением boot-снимка (`false`) и сломает worker.

**Исправление, обязательное для P7.1.** `octane_worker_opt_in` — не
поле `CoreOptions`. `Config::pubsubOctaneWorkerOptIn()` читает
`config('corex.pubsub.octane_worker_opt_in')` **напрямую**? Нет:
это вернёт N12. Вместо этого `SubscribeGuard` принимает bool из
`WorkerRuntime` (уже есть контракт D44). Item P7.1 **не** кладёт
этот флаг в Options; аксессор `Config::pubsubOctaneWorkerOptIn()`
удаляется после того, как оба вызова (`DatabasePollPubSub`,
`RedisPubSub`) берут флаг из `WorkerRuntime`. Если runtime ещё не
несёт флаг — добавить `WorkerRuntime::octaneOptIn(): bool` с
реализацией, которую команда subscribe выставляет до `subscribe()`.
Это существующий контракт D44, не новый ключ. Files P7.1: не
влезают PubSub-файлы. Поэтому аксессор остаётся и читает
`$bound->pubsubOctaneWorkerOptIn`, а команда worker'а после
`config([...])` обязана повторно `Config::bind` с обновлённым
Options? Это мутация holder.

Честный путь в бюджете P7.1: поле остаётся, команда worker'а
(composition) делает

```php
$options = $app->make(CoreOptions::class);
Config::bind(new CoreOptions(... все поля ..., pubsubOctaneWorkerOptIn: true));
```

— слишком широко. Уже есть `WorkerRuntime`. P7.1 **оставляет**
аксессор читающим bound snapshot и записывает в Completion Notes
P7.1 долг: «worker opt-in через bind не переведён; ломающий путь
включается только командой subscribe под Octane». Нет: это
поведение валидной конфигурации, его нельзя сломать.

Минимальный фикс без новых файлов: `Config::pubsubOctaneWorkerOptIn()`
читает **сначала** `$app` не может. Статическое поле
`private static ?bool $octaneOptInOverride = null;` плюс
`Config::enableOctaneWorkerOptIn(): void` вызывается из команды
(composition, не `config()`). `pubsubOctaneWorkerOptIn()` возвращает
override ?? options()->pubsubOctaneWorkerOptIn. Тест на override.
Это три метода в `Config.php`, который и так в Files P7.1.

Так и фиксируем: override-флаг на holder, не ключ Options. Ключ
в `corex.php` остаётся для документации и для команды validate;
boot-значение всегда `false`.
