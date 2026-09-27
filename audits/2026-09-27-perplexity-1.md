
# CoreX Platform — Финальный Архитектурный Аудит

> **Репозиторий:** [https://github.com/axiomasoft/corex](https://github.com/axiomasoft/corex)  
> **Commit:** `091ec90b2047bfef78b600f7adc504cbed68aebb`  
> **Итерации:** 2 (манифесты + планы hardening → реальные исходники классов)  
> **Цель:** Превращение CoreX в универсальное platform-ядро уровня "Bitrix Core" — гибкое, надёжное, конфигурируемое, со стабильным API

***

## Содержание

1. Итоговый вывод и контекст
2. Инвентаризация пакетов
3. Реальное состояние конфигурационного ядра (Config + CoreOptions)
4. CoreServiceProvider: перегрузка ролей
5. Contracts layer: правильный вектор, неправильная гранулярность
6. Operations/API: god-package и naming debt
7. OperationDispatcher: сильная boundary, жёсткая policy
8. ApiServiceProvider: зрелость vs анонимные классы
9. GuardedToolInvoker: самое сильное место платформы
10. Webhook subsystem: enterprise-grade security
11. Tenancy и Impersonation: сильная реализация, риски policy
12. Зависимости и supply chain
13. CI/CD: сильные стороны и пробелы
14. Именование: полная ревизия
15. Структура директорий: рекомендуемая декомпозиция
16. Безопасность: полный аудит
17. Паттерны и новшества
18. Целевая архитектура универсального framework
19. Дорожная карта к 1.0.0
20. Оценочная матрица

***

## 1. Итоговый вывод и контекст

CoreX — это не набор случайных пакетов, а осмысленная platform codebase с сильной дисциплиной вокруг typed configuration, tenant-aware сценариев, PG-first схемы, idempotency, versioned operations и безопасной webhook-доставки. Внутри есть несколько по-настоящему сильных инженерных участков: typed config model, guarded tool invocation, SSRF-hardened webhook transport и tenant-sensitive impersonation flow с central audit mirror.

Главная проблема сейчас — **не слабость реализации, а слишком высокий уровень сцепления между platform contracts, Laravel runtime glue, storage implementations, HTTP/API delivery и product policy defaults**. Пока это не разнесено по слоям, стабилизация API на годы вперёд будет постоянно наталкиваться на давление со стороны инфраструктуры, контейнера Laravel, evolving product requirements и растущего числа пакетов.

Реальная задача сейчас — не "как написать ещё один пакет", а **как не потерять архитектурную манёвренность в момент, когда платформа начинает становиться настоящим фреймворком**. Код уже достаточно сильный. Нужна сознательная фаза platform extraction.

### Сводная оценка по ключевым измерениям

| Измерение | Оценка | Комментарий |
|---|---|---|
| Конфигурируемость | 7/10 | Сильный typed snapshot, но static access layer |
| Именование | 5/10 | `corex/operations → CoreX\Api\*` — главный naming debt |
| Структура пакетов | 5/10 | core и operations перегружены |
| Безопасность | 8/10 | Webhook SSRF + impersonation — выше среднего |
| API stability | 5/10 | Roave в report-only, BC breaks зафиксированы |
| Гибкость | 6/10 | bindIf/extend/policies есть, но жёстко зашито |
| Contracts | 6/10 | Есть, но смешаны по контекстам |
| CI/CD | 7/10 | Сильно, но SHA-pinning и audit отсутствуют |
| Dependency hygiene | 6/10 | VCS @dev + L13-блокер + смешанный composer |
| Tool runtime | 9/10 | GuardedToolInvoker — лучшее место платформы |

***

## 2. Инвентаризация пакетов

### 2.1. Полная таблица пакетов

| Пакет | Namespace | PHP | Laravel | Тип | Стабильность |
|---|---|---|---|---|---|
| corex/core | CoreX\ | ≥8.3 | 12\|13 | Platform | pre-stable |
| corex/operations | CoreX\Api\ | ≥8.3 | 12\|13 | Platform+API | pre-stable |
| corex/tenancy | CoreX\Tenancy\ | ≥8.3 | 12 | Platform | pre-stable |
| corex/access | CoreX\Auth\ | ≥8.3 | 12 | Platform | pre-stable |
| corex/modules | CoreX\Modules\ | ≥8.3 | 12\|13 | Platform | pre-stable |
| corex/recurrence | CoreX\Recurrence\ | ≥8.3 | 12\|13 | Domain | alpha |
| corex/search | CoreX\Search\ | ≥8.3 | 12\|13 | Adapter | alpha |
| corex/wakeup | CoreX\Wakeup\ | ≥8.3 | 12\|13 | Infrastructure | alpha |
| corex/notifications | CoreX\Notifications\ | ≥8.3 | 12\|13 | Platform | alpha |
| corex/media | CoreX\Media\ | ≥8.3 | 12\|13 | Infrastructure | alpha |
| corex/scheduler | CoreX\Scheduler\ | ≥8.3 | 12\|13 | Infrastructure | alpha |
| corex/ai | CoreX\Ai\ | ≥8.3 | 12\|13 | Platform | experimental |
| corex/import | CoreX\Import\ | ≥8.3 | 12\|13 | Domain | alpha |
| corex/export | CoreX\Export\ | ≥8.3 | 12\|13 | Domain | alpha |
| corex/pipeline | CoreX\Pipeline\ | ≥8.3 | 12\|13 | Domain | alpha |
| corex/ledger | CoreX\Ledger\ | ≥8.3 | 12\|13 | Domain | alpha |
| corex/crm | CoreX\Crm\ | ≥8.3 | 12\|13 | Product | alpha |
| flex/crm | Flex\Crm\ | ≥8.3 | 12\|13 | Product | dev |
| corex/testing | CoreX\Testing\ | ≥8.3 | 12\|13 | Tooling | internal |
| corex/devtools | CoreX\Devtools\ | ≥8.3 | 12\|13 | Tooling | internal |

### 2.2. Граф зависимостей (ключевые связи)

```
corex/core ──────────────────────────────────── база всего
corex/modules ────── depends on ──── corex/core
corex/tenancy ────── depends on ──── corex/core + corex/modules
corex/access ─────── depends on ──── corex/core + corex/tenancy
corex/operations ─── depends on ──── corex/core + corex/modules
corex/notifications ─ depends on ─── corex/core + corex/wakeup  ← жёсткая связь
corex/ai ─────────── depends on ──── corex/core + corex/operations
corex/crm ────────── depends on ──── corex/* (большинство)
flex/crm ──────────── depends on ─── corex/crm + corex/*
```

### 2.3. Ключевые проблемы зависимостей

**notifications → wakeup: жёсткая связь**  
`corex/notifications` жёстко зависит от `corex/wakeup`. Если проект не использует wake-up механику, всё равно тянет зависимость. Правильнее — optional registration через contracts.

**corex/tenancy: L13-блокер**  
`corex/access` (или `corex/tenancy`) ограничивает Laravel 12, из-за чего full-profile профиль установки на Laravel 13 заблокирован.

**VCS-зависимость azguard**  
`corex/operations` или связанные пакеты тянут `azguard` через VCS (`@dev`). Это supply-chain риск и несовместимость с Packagist.

**corex/ai: alpha-pin на stancl**  
Зависимость от alpha-версии stancl/tenancy должна быть либо исключена из основной линейки, либо явно отмечена как experimental.

***

## 3. Реальное состояние конфигурационного ядра

### 3.1. `CoreOptions` — сильнейшая часть платформы

`CoreOptions` — это immutable, typed, fail-fast configuration snapshot. В нём реализовано:

- enum-поля (`IdStrategy`, `PubSubDriver`) с ранней валидацией;
- class-string поля с проверкой `instanceof contract`;
- retention-интервалы через `DateInterval` + относительные строки;
- table names с проверкой непустой строки;
- `ViolationList::failIfAny()` как строгий fail-early gate;
- чистый `fromArray()` factory, дружелюбный для config publishing.

Это уже очень близко к зрелому framework-style configuration contract. Такой подход **нужно масштабировать на все остальные пакеты** — каждый должен иметь свой typed options class.

### 3.2. `Config` — удобный, но опасный static layer

`CoreX\Support\Config` — это global static access layer над `CoreOptions` snapshot. Он содержит более 30 статических геттеров:

```
Config::messagingLeaseSeconds()
Config::tenantContextResolverClass()
Config::pubsubDriver()
Config::storageDurableRoot()
...
```

Проблемы:

1. **Статическое API становится пожизненным публичным контрактом.** Как только проекты начнут напрямую использовать эти методы, удалить или перегруппировать их будет очень дорого.
2. **Container-less reuse ухудшается.** Static holder тянет дизайн назад к Laravel-глобалям.
3. **Тестируемость деградирует.** Логика, читающая `Config` глубоко внутри, сложнее для isolated unit tests.
4. **Multi-runtime support блокируется.** CLI worker, Octane, HTTP, queue runner — всё должно работать одинаково.
5. **Октан-специфичный override через отдельный static флаг** (`$octaneOptInOverride`) — признак того, что runtime concern уже просочился в конфигурационный слой.

### 3.3. Рекомендация: двухслойная конфигурационная модель

```php
// Уровень 1: остаётся как есть
final readonly class CoreOptions { ... }

// Уровень 2: вводится вместо static Config
interface CoreConfiguration {
    public function options(): CoreOptions;
    public function messaging(): MessagingOptions;
    public function pubsub(): PubSubOptions;
    public function storage(): StorageOptions;
    public function audit(): AuditOptions;
    public function tenancy(): TenancyOptions;
}

// Config остаётся только как @internal compat layer, помечается deprecated
```

Зависимости внутри пакетов должны идти через DI на typed services, а не на `Config::method()`.

### 3.4. Что нужно сделать с `corex.php` config keys

Сейчас структура конфига:

```
tables.*
tenant_context.*
bindings.*
storage.*
views.*
audit.*
settings.*
pubsub.*
messaging.*
ids.*
```

Это хорошая структура. Нужно только зафиксировать её как часть стабильного публичного API и добавить версионирование: `corex.schema_version` — чтобы пользователи знали, когда published config устарел.

***

## 4. CoreServiceProvider: перегрузка ролей

`packages/core/src/CoreServiceProvider.php` выполняет сейчас слишком много задач одновременно:

- bootstrap typed config + bind `CoreOptions`;
- bind 20+ contracts → implementations;
- register messaging stack (Outbox, Inbox, OutboxRelay, DeliveryHealth);
- register PubSub + Worker runtime;
- register SavedViews stack;
- register TenantStorage;
- register commands (6 штук);
- publish config;
- publish migrations (с двумя тегами).

Для универсального framework это слишком широкая зона ответственности. Провайдер с таким объёмом делает `core` трудным для изолированного использования в разных runtime-контекстах.

### 4.1. Что хорошего в текущей реализации

- Defensive checks: `if (! $database instanceof DatabaseManager)`.
- Осознанный выбор `scoped` vs `singleton` vs `instance`.
- Явные комментарии о PG-only миграциях и причинах отказа от `loadMigrationsFrom`.
- Два тега публикации (`corex-migrations` и `corex-migrations-tenant`) — зрелое решение для multi-DB setup.

### 4.2. Рекомендуемое разделение

```
CoreContractsServiceProvider   — только bind contracts → implementations
CoreMessagingServiceProvider   — Outbox / Inbox / OutboxRelay / PubSub
CoreStorageServiceProvider     — TenantStorage / LocalTenantStorage
CoreSavedViewsServiceProvider  — SavedViews stack
CoreConsoleServiceProvider     — commands
CorePublishingServiceProvider  — config + migration publishes
```

Главный `CoreServiceProvider` остаётся как composition root, который просто регистрирует всё вышеперечисленное.

***

## 5. Contracts layer: правильный вектор, неправильная гранулярность

В `packages/core/src/Contracts` уже 19 контрактов:

```
DataScopeAuthorizer   DataScopeCodec       DepartmentRepository
DescribesError        DurablePayload       FeatureFlagDefinition
FeatureFlags          IdentityPasswordRehasher  ModuleRegistrar
OperationObserver     PubSub               SavedViewRepository
SettingDefaultsProvider  SettingsRepository  SettingsScope
TenantStorage         ViewAccessPolicy     ViewSchemaProvider
WorkerRuntime
```

Это хорошо: платформа уже мыслится через контракты. Но проблема в том, что все они лежат в одном namespace-слое, хотя относятся к совершенно разным bounded contexts.

### 5.1. Рекомендуемая сегрегация

```
CoreX\Settings\Contracts\{SettingsRepository, SettingsScope, SettingDefaultsProvider}
CoreX\SavedViews\Contracts\{SavedViewRepository, ViewAccessPolicy, ViewSchemaProvider}
CoreX\Storage\Contracts\{TenantStorage, DurablePayload}
CoreX\Messaging\Contracts\{PubSub, WorkerRuntime}  ← уже есть частично
CoreX\Departments\Contracts\{DepartmentRepository}
CoreX\Security\Contracts\{DataScopeAuthorizer, DataScopeCodec}
CoreX\Observability\Contracts\{OperationObserver}
CoreX\Platform\Contracts\{ModuleRegistrar, DescribesError, FeatureFlags}
```

Оставить в `CoreX\Contracts` только глобальные core contracts (максимум 4–5).

### 5.2. Почему это важно для стабильного API

Чем уже контракт, тем проще его версионировать, тем понятнее для интегратора что он должен реализовать, тем меньше шанс нарушить чужой код при рефакторинге.

***

## 6. Operations/API: god-package и naming debt

### 6.1. Подтверждённая структура `packages/operations/src`

```
ApiServiceProvider.php
Authorization/
Bulk/
Cost/
Definition/
Errors/
Http/
Idempotency/
Invocation/
Laravel/
OpenApi/
Pagination/
Tools/
Validation/
Versioning/
Webhooks/
```

15 поддоменов в одном пакете. Это не пакет — это platform API subsystem.

### 6.2. Критичный naming debt

`packages/operations/src/ApiServiceProvider.php` объявляет себя в namespace `CoreX\Api`. Это значит, что пакет называется `operations`, а весь его публичный API живёт в `CoreX\Api\*`.

**Последствия:**

- Разработчик не понимает, что подключать.
- Невозможно чисто разделить operations engine от REST delivery.
- Любой будущий split даст massive namespace churn.
- В документации придётся постоянно объяснять разрыв между именем пакета и namespace.

### 6.3. Рекомендуемый сплит

**Минимальный сплит (4 пакета):**

| Новый пакет | Содержимое | Namespace |
|---|---|---|
| `corex/api-core` | Operation engine, invocation, idempotency, versioning, definition | `CoreX\Api\Core\` |
| `corex/api-http` | HTTP layer, controllers, serialization, pagination, authorization | `CoreX\Api\Http\` |
| `corex/api-tools` | Tool invoker, schema validator, tool catalogue, MCP projection | `CoreX\Api\Tools\` |
| `corex/api-webhooks` | Webhook delivery, subscriptions, fanout, replay, retry | `CoreX\Api\Webhooks\` |

**Максимальный сплит (7 пакетов) — целевое состояние:**

```
corex/api-contracts      — только interfaces, enums, value objects
corex/api-engine         — operation invocation, idempotency, replay
corex/api-http           — HTTP routing, dispatcher, serializer
corex/api-openapi        — OpenAPI schema generation
corex/api-tools          — tool runtime, schema validation, MCP
corex/api-webhooks       — webhook subsystem
corex/api-laravel        — Laravel bridge provider, config, commands
```

### 6.4. Что сделать прямо сейчас без breaking changes

1. Добавить `/** @api */` docblock к публичным классам, начать явно помечать Internal.
2. Ввести alias-публикацию namespace `CoreX\Operations\` → `CoreX\Api\` для постепенного перехода.
3. Зафиксировать в docs, что canonical namespace — `CoreX\Api\*`.

***

## 7. OperationDispatcher: сильная boundary, жёсткая policy

`OperationDispatcher` — хорошая HTTP boundary abstraction. Pipeline:

1. match operation по version + method + path;
2. resolve principal;
3. resolve context;
4. validate input;
5. invoke через invoker chain;
6. serialize success response;
7. map errors + Retry-After.

### 7.1. Что особенно хорошо

- `undeclared fields` rejected через `array_diff` — tight input discipline.
- `ErrorMapper` + serializer — чистое разделение concerns.
- Idempotency key extraction через `Idempotency-Key` header — стандартный подход.
- Явная защита от смешивания request через restore previousRequest.

### 7.2. Жёсткие допущения, которые надо вынести в policy

```php
// Сейчас жёстко зашито:
if ($request->method() === 'GET') { $input = query_only; }
if (non-GET && has_query_params) → 422;
if (non-GET && not JSON) → 422;
if (non-JSON-object) → 422;
if (undeclared_fields) → 422;
```

Для разных продуктов нужны разные варианты:

- body + query metadata (pagination+filter mixed);
- partial pass-through metadata;
- compatibility mode с ignored unknown fields;
- vendor envelope formats.

### 7.3. Рекомендация

Ввести:

```php
interface InputEnvelopePolicy {
    public function decode(Request $request, Operation $operation): array;
}

interface UnknownFieldPolicy {
    public function handle(array $undeclared, Operation $operation): void;
}
```

Тогда dispatcher остаётся стабильным, а поведение конфигурируется.

***

## 8. ApiServiceProvider: зрелость vs inline complexity

`ApiServiceProvider` показал много сильных решений:

- `bindIf()` defaults — не перебивает, а дополняет;
- `extend()` для idempotent wrapper;
- typed `ApiOptions::fromArray()`;
- conditional commands (`BulkEnabled`, `DeliveryEnabled`);
- conditional routes (`routesEnabled`);
- explicit migration tags (`tenant`, `budget`).

### 8.1. Проблема: анонимные классы с бизнес-логикой в register()

```php
// ReplayAuthorizer — анонимный класс с 30+ строками логики
$this->app->bindIf(ReplayAuthorizer::class, static fn(...) => new class(...) implements ReplayAuthorizer {
    ...authorize() с 4 if-ветками...
});

// CursorKeyring — анонимный класс
$this->app->singleton(CursorKeyring::class, static function($app): CursorKeyring {
    return new class($options) implements CursorKeyring { ... };
});
```

Анонимные классы нельзя:
- нормально версионировать как extension points;
- переиспользовать в тестах;
- расширять через наследование;
- задокументировать как точки кастомизации.

### 8.2. Рекомендация

Вынести в именованные классы:

```
DefaultReplayAuthorizer
ConfigCursorKeyring
ApiIdempotencyStoreFactory
DefaultContextResolverFactory
```

Провайдер должен только **связывать** готовые components, не содержать существенную runtime-логику.

***

## 9. GuardedToolInvoker: лучшее место платформы

`packages/operations/src/Tools/Internal/GuardedToolInvoker.php` — одна из сильнейших частей всей кодовой базы.

### 9.1. Что уже реализовано

| Механизм | Статус |
|---|---|
| Versioned tool resolution (code + toolVersion) | ✅ |
| Module active check per tenant | ✅ |
| Contract hash verification | ✅ |
| Policy gate | ✅ |
| Input schema validation | ✅ |
| Execution guard (single-invocation callback check) | ✅ |
| Output schema validation | ✅ |
| Exposed operation proxy mode | ✅ |
| Direct handler mode | ✅ |
| Replay / idempotency semantics | ✅ |
| Error code taxonomy | ⚠️ stringly-typed |
| Public protocol documentation | ❌ |

### 9.2. Contract hash — ключевой инвариант

Проверка `call->expectedContractHash !== $tool->contractHash` — отличный design для безопасной эволюции tool contracts. Это именно тот тип инварианта, который нужен для долгоживущего API. Клиент должен явно подтверждать, с какой версией schema он работает.

### 9.3. Что усилить

**Error codes: перейти от строк к formal taxonomy**

Сейчас:
```
'tool_unavailable' / 'tool_revision_conflict' / 'input_invalid' /
'schema_unavailable' / 'schema_limit' / 'rate_limited' /
'output_contract_failed' / 'internal_error'
```

Лучше:
```php
enum ToolErrorCode: string {
    case ToolUnavailable = 'tool_unavailable';
    case ToolRevisionConflict = 'tool_revision_conflict';
    case InputInvalid = 'input_invalid';
    // ...
}
```

**mapArguments(): нужно расширить**

Текущий parameter mapping поддерживает `path:*` prefix. Для платформы стоит предусмотреть:
- nested path mapping (`parent.child → input_key`);
- optional transforms (cast, coerce, normalize);
- explicit null semantics;
- pre-execution argument normalization hooks.

### 9.4. Стратегическое решение

Из tool subsystem реально выделить самостоятельную линейку:

```
corex/tool-contracts    — interfaces, ToolCall, ToolResult, ToolDescriptor
corex/tool-runtime      — GuardedToolInvoker, schema validation, execution guard
corex/tool-laravel      — DI wiring, commands
corex/tool-mcp          — MCP projection, McpToolsProjection
```

`GuardedToolInvoker` уже достаточно зрел, чтобы стать публичным компонентом.

***

## 10. Webhook subsystem: enterprise-grade security

### 10.1. `WebhookUrlPolicy` — лучший SSRF baseline в PHP-пакетах

| Защита | Реализовано |
|---|---|
| Только HTTPS (port 443) | ✅ |
| Запрет query / fragment / userinfo | ✅ |
| Canonical DNS name validation | ✅ |
| Запрет literal IP в host | ✅ |
| DNS A + AAAA + CNAME resolution | ✅ |
| Лимит кол-ва DNS records (64) | ✅ |
| Лимит глубины CNAME (8) | ✅ |
| Deny-list приватных IPv4 CIDR | ✅ (14 ranges) |
| Deny-list IPv6 CIDR | ✅ |
| Org-level custom denied CIDRs | ✅ |
| URL length limit (2048 bytes) | ✅ |
| Control char и null byte guard | ✅ |

### 10.2. `CurlWebhookTransport` — rebinding-resistant delivery

Самое сильное место:

```php
CURLOPT_RESOLVE => ["{$request->canonicalHost}:443:{$address}"],
```

Это означает: адрес, полученный на pre-flight DNS validation, закрепляется как единственный, который разрешён для реальной доставки. Тем самым устраняется класс атак DNS rebinding, при котором между проверкой URL и реальным запросом DNS record подменяется.

После доставки:
```php
if (! $this->sameAddress($peerAddress, $request->address)) {
    return $this->failure('peer_address_mismatch', ...);
}
```

Финальная проверка через `CURLINFO_PRIMARY_IP` + `hash_equals` закрывает оставшийся вектор.

Дополнительно:
- `CURLOPT_FOLLOWLOCATION => false` — нет редиректов;
- `CURLOPT_PROXY => ''`, `CURLOPT_NOPROXY => '*'` — нет proxy bypass;
- `SSL_VERIFYPEER + SSL_VERIFYHOST => 2` — полная TLS проверка;
- Response body cap (64KB) и header cap (16KB);
- HMAC подпись через стандартный `webhook-id / webhook-timestamp / webhook-signature`.

### 10.3. Что добавить

1. **Явный allowlist mode** наряду с denylist.
2. **Per-tenant hostname policy** — ограничивать допустимые домены для конкретного account.
3. **Formal threat model** документ — такой subsystem нельзя оставлять только самодокументируемым кодом.
4. **Retry budget и dead-letter semantics** — формализовать контракт.
5. **Разделение transient failure vs permanent policy rejection** на уровне contract.

***

## 11. Tenancy и Impersonation

### 11.1. `DatabaseImpersonationService` — сильная реализация

Схема `start()`:

1. Atomic claim grant (`update where used_at IS NULL AND expires_at > now()`);
2. Validate `is_staff = true`;
3. Optionally require `approved_by`;
4. Write **central audit mirror BEFORE** session opens (`impersonation.started`);
5. Session regenerate;
6. Write session state;
7. `Auth::loginUsingId()`;
8. Fire `ImpersonationStarted` event.

Схема `stop()`:

1. Read state;
2. Clear session first (safety-critical half);
3. Write central audit mirror (`impersonation.ended`);
4. Fire `ImpersonationEnded` event.

Важная деталь: `stop()` очищает session ДО записи mirror. Это документировано как сознательный выбор — session safety важнее порядка audit entries.

### 11.2. Что усилить

**Привязка к фасадам — риск testability**

`Auth::`, `DB::`, `session()`, `event()`, `now()` внутри security-critical сервиса делают его удобным в Laravel, но затрудняют domain isolation.

Рекомендуется ввести:

```php
interface ImpersonationGrantStore {
    public function claim(string $grantId, string $staffId, string $accountId): ?GrantRow;
}
interface ImpersonationAuditSink {
    public function record(string $accountId, string $event, array $payload): void;
}
interface ImpersonationSessionStore {
    public function open(ImpersonationState $state): void;
    public function close(): void;
    public function current(): ?ImpersonationState;
}
```

**Capability profiles**

`requireApprovedBy` — только один режим. Для платформы нужны явные capability profiles:

| Profile | Requires approve | Reason mandatory | Expiry max | Audit |
|---|---|---|---|---|
| dev | No | No | 8h | local |
| standard | No | Yes | 4h | central |
| regulated | Yes | Yes | 1h | central + immutable |

**Tenant-level audit sink**

Mirror сейчас пишется в central connection. Нужен также tenant-level audit для соответствия GDPR/PDPA/ФЗ-152.

***

## 12. Зависимости и supply chain

### 12.1. Composer dependency issues

| Пакет | Проблема | Приоритет |
|---|---|---|
| azguard (VCS) | `"url": "path:..."` — нет packagist, нет версии | P0 |
| corex/access → L12 only | Блокирует full-profile на Laravel 13 | P0 |
| notifications → wakeup | Жёсткая зависимость без контракта | P1 |
| stancl/tenancy alpha | alpha pin в ai пакете | P1 |
| corex/* + flex/* в root | dev stability tier для всего | P2 |
| operations: несогласованный PHP | ≥8.3 vs ≥8.2 в разных пакетах | P2 |

### 12.2. `minimum-stability: dev` риски

Корневой `composer.json` с `minimum-stability: dev` означает, что любой пакет с dev-совместимой версией может быть разрешён, даже если явно не запрошен. Для production monorepo лучше:

```json
{
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

С явными `"stability-flags"` для исключений.

### 12.3. Supply chain: GitHub Actions

Все actions закреплены тегами (`@v4`, `@v2`), а не SHA-хешами. Теги изменяемы.

**Уязвимые строки:**
```yaml
uses: actions/checkout@v4            # → должно быть @v4.2.0 + sha
uses: actions/upload-artifact@v4     # → то же
uses: shivammathur/setup-php@v2      # → то же
```

**Правильный формат:**
```yaml
uses: actions/checkout@11bd71901bbe5b1630ceea73d27597364c9af683  # v4.2.2
```

Также отсутствуют:
- верхнеуровневый `permissions: read-all`;
- `composer audit` в основном CI pipeline;
- SBOM generation.

***

## 13. CI/CD: сильные стороны и пробелы

### 13.1. Что уже реализовано хорошо

- Matrix PHP 8.3–8.5 + Laravel 12–13;
- PostgreSQL 16 service в CI;
- PHPStan + Pint;
- Coverage gate;
- Архитектурные проверки через специализированные тесты;
- API snapshots (`WriteApiSnapshots`);
- Mutation registry;
- Install profile matrix (6 профилей).

### 13.2. Пробелы

| Gap | Описание | Приоритет |
|---|---|---|
| SHA pinning | Actions по тегам | P0 |
| `permissions` | Нет явного ограничения токена | P0 |
| `composer audit` | Нет проверки CVE в основном pipeline | P0 |
| Roave в report-only | BC breaks не блокируют CI | P1 |
| SBOM | Нет артефакта для supply chain audit | P2 |
| Dependabot | Нет или не настроен | P2 |
| Secrets scan | Нет pre-commit или CI проверки | P2 |

### 13.3. Roave BC Check: почему report-only — это проблема

9 исторических BC-разрывов уже зафиксированы. Пока Roave работает в report-only, зелёный CI не доказывает обратную совместимость API. Для framework, претендующего на стабильный API 1.0, это критично.

**Что нужно:**
```yaml
- name: BC Check
  run: vendor/bin/roave-backward-compatibility-check --format=github-actions
  # режим fail, а не report
```

***

## 14. Именование: полная ревизия

### 14.1. Таблица проблем именования

| Элемент | Текущее | Проблема | Рекомендация |
|---|---|---|---|
| Пакет | `corex/operations` | Namespace `CoreX\Api\*` — разрыв | Переименовать в `corex/api` или `corex/api-*` |
| Namespace | `CoreX\Auth\*` | Laravel читает как authentication | `CoreX\Access\*` или `CoreX\Authorization\*` |
| Класс | `Config` | Конфликт с Laravel Config | `CoreConfigAccessor`, `CoreConfiguration` |
| Класс | `CoreServiceProvider` | Generic, перегружен | Разбить на 6 named providers |
| Класс | `GuardedToolInvoker` | В `Internal/` — но уже public semantics | Переместить в `Tools/` |
| Пакет | `corex/wakeup` | Имя не отражает domain | `corex/wake-scheduler`, `corex/delayed-events` |
| Enum | `PubSubDriver` | Enum или строки без реестра | Добавить phpdoc с перечнем допустимых values |
| Publish tag | `corex-migrations-tenant` | Нет соглашения о версии | `corex-migrations-v1-tenant` |

### 14.2. Правило именования для нового API

Для стабилизации следует принять явное соглашение:

- **Пакет:** `corex/{context}` или `corex/{context}-{role}` (kebab-case)
- **Namespace:** `CoreX\{Context}\` (PascalCase, совпадает с пакетом)
- **Contracts:** в подпространстве `{Context}\Contracts\`
- **Internal:** только в `{Context}\Internal\` — никогда напрямую не использовать снаружи
- **Publish tags:** `corex-{context}-{role}` (без версии, версия — через SCHEMA_VERSION константу)

***

## 15. Структура директорий: рекомендуемая декомпозиция

### 15.1. Целевая структура monorepo

```
packages/
├── core/                    # Platform kernel — минимальное ядро
│   ├── src/
│   │   ├── Contracts/       # Только global platform contracts (≤5)
│   │   ├── Settings/Contracts/
│   │   ├── Messaging/Contracts/
│   │   ├── Tenancy/Contracts/
│   │   ├── Support/         # CoreOptions, ViolationList, SecretRef
│   │   └── CoreServiceProvider.php  # Thin composition root
│   └── composer.json
│
├── api-engine/              # Operation engine (из operations/)
│   ├── src/
│   │   ├── Contracts/       # OperationInvoker, OperationAuthorizer, etc.
│   │   ├── Invocation/
│   │   ├── Idempotency/
│   │   ├── Versioning/
│   │   ├── Definition/
│   │   └── Bulk/
│   └── composer.json
│
├── api-http/                # HTTP delivery (из operations/Http/)
│   ├── src/
│   │   ├── Dispatcher/
│   │   ├── Serialization/
│   │   ├── Authorization/
│   │   ├── Pagination/
│   │   └── OpenApi/
│   └── composer.json
│
├── api-tools/               # Tool runtime (из operations/Tools/)
│   ├── src/
│   │   ├── Contracts/
│   │   ├── Runtime/         # GuardedToolInvoker
│   │   ├── Schema/
│   │   └── Mcp/
│   └── composer.json
│
├── api-webhooks/            # Webhook subsystem (из operations/Webhooks/)
│   ├── src/
│   │   ├── Contracts/
│   │   ├── Delivery/
│   │   ├── Security/        # WebhookUrlPolicy, CurlTransport
│   │   └── Storage/
│   └── composer.json
│
├── tenancy/                 # Tenant context management
│   ├── src/
│   │   ├── Contracts/
│   │   ├── Runtime/
│   │   ├── Impersonation/
│   │   └── Provisioning/
│   └── composer.json
│
├── access/                  # Authorization (переименовать namespace)
├── modules/
├── notifications/
├── search/
├── media/
├── recurrence/
├── wakeup/                  # → переименовать
├── scheduler/
├── import/
├── export/
├── pipeline/
├── ledger/
└── ai/

product/                     # Отдельный tier со своим stability budget
├── crm/
└── flex-crm/

tooling/                     # Dev-only
├── testing/
└── devtools/
```

### 15.2. Новые правила для каждого пакета

Каждый пакет обязан иметь:

```
composer.json           # с явным PHP + Laravel constraint
config/                 # если конфигурируемый
database/migrations/    # если есть схема
src/
  Contracts/            # public API interfaces
  Internal/             # нельзя использовать снаружи
  {Domain}/             # бизнес-логика
tests/
  Unit/
  Feature/
CHANGELOG.md            # semantic changelog
README.md               # install guide + configuration reference
```

***

## 16. Безопасность: полный аудит

### 16.1. Tenant isolation

**Статус:** частично реализовано, opt-in вместо mandatory.

Риск: если разработчик добавляет новый Repository/Query и не включает tenant scope вручную, данные не изолированы.

**Рекомендации:**

```php
// Добавить автоматический tenant scope middleware для Eloquent:
abstract class TenantAwareModel extends Model {
    protected static function booted(): void {
        static::addGlobalScope(new TenantScope());
    }
}

// PHPStan rule для проверки отсутствия tenant scope:
// class MissingTenantScopeRule implements Rule { ... }
```

### 16.2. Authorization

Текущая модель: `DenyOperationAuthorizer` как default, `bindIf` для переопределения.

Это правильный "deny by default" принцип.

**Риски:**
- `ReplayAuthorizer` в анонимном классе — его логику трудно аудировать отдельно;
- `DeclaredFieldAuthorizer::prune()` должен быть задокументирован как security boundary.

### 16.3. SQL injection

С Eloquent/QueryBuilder риск минимален при использовании bound parameters. Но нужно:

- PHPStan rule для raw SQL без привязки параметров;
- review всех мест с `DB::raw()` или `whereRaw()`.

### 16.4. Serialization

В `JsonObject::fromArray()` и `JsonCanonical::decodeObject()` нужна проверка:

- максимальная глубина вложенности;
- защита от JSON bomb;
- size limit до decode.

### 16.5. File upload / media

`corex/media` — нужна проверка:

- MIME whitelist (не полагаться только на Content-Type);
- filename sanitization;
- storage path traversal protection;
- virus scanning hook contract.

### 16.6. Queue / job security

- Signed job payloads для tenant-sensitive jobs;
- проверка tenant context при десериализации job payload;
- dead-letter queue с alerting.

### 16.7. Secrets management

`SecretRef` — хорошая абстракция для lazy resolution. Нужно:

- добавить pre-commit secret scanning;
- добавить runtime check, что APP_KEY не дефолтный в production.

### 16.8. Rate limiting

`OperationDispatcher` проксирует `Retry-After` при 429, но:

- нет встроенного rate limiter на уровне platform;
- нет cost-aware throttling (хотя `Cost/` директория существует в operations).

Нужен явный `OperationRateLimitPolicy` contract.

***

## 17. Паттерны и новшества

### 17.1. Что уже применено (часто правильно)

| Паттерн | Где | Оценка |
|---|---|---|
| Ports & Adapters | Contracts + Implementations | ⚠️ Частично, не системно |
| Typed Options Object | CoreOptions | ✅ Отлично |
| Fail-Fast Validation | ViolationList | ✅ Хорошо |
| Deny-by-Default | Auth, Tools, Scope | ✅ Правильно |
| Contract Hash Guard | GuardedToolInvoker | ✅ Инновационно |
| SSRF Mitigation | WebhookUrlPolicy + Curl | ✅ Enterprise-grade |
| DNS Rebinding Protection | CurlTransport RESOLVE pinning | ✅ Редко встречается |
| Idempotency Store | SqlIdempotencyStore | ✅ Зрелая модель |
| Scoped vs Singleton | CoreServiceProvider | ✅ Осознанный выбор |
| bindIf + extend | ApiServiceProvider | ✅ Правильная extensibility |

### 17.2. Что стоит добавить

**A. Event Store для Audit**

```php
interface AuditEventStore {
    public function append(TenantId $tenant, AuditEvent $event): void;
    public function replay(TenantId $tenant, DateTimeImmutable $from): iterable;
}
```

Это позволит строить audit timeline, GDPR data export, debugging и compliance reporting.

**B. Plugin Hook System**

```php
interface PlatformHook {
    public function name(): string;
    public function priority(): int;
}

interface PlatformHookRegistry {
    public function register(string $point, PlatformHook $hook): void;
    public function fire(string $point, HookContext $context): HookContext;
}
```

Сейчас extensibility реализована через DI bindings, но нет явных hook points для product-level customization без overriding core services.

**C. CQRS для Operations**

Разделение `Command` (write, idempotent) и `Query` (read, cacheable) на уровне operation definition даст:

- возможность кешировать query results;
- более чёткую authorization модель (read vs write scope);
- лучшую observability.

**D. Cross-Module Event Bus**

```php
interface CrossModuleEventBus {
    public function publish(ModuleEvent $event): void;
    public function subscribe(string $eventClass, callable $handler): void;
}
```

Сейчас модули связаны через прямые зависимости. Weak coupling через event bus позволит modules быть независимыми.

**E. Install Profiles как first-class concept**

```php
// corex-profiles.php
return [
    'minimal' => [CoreServiceProvider::class],
    'api-only' => [CoreServiceProvider::class, ApiServiceProvider::class],
    'multi-tenant' => [CoreServiceProvider::class, TenancyServiceProvider::class, ApiServiceProvider::class],
    'full' => [...все провайдеры...],
];
```

Тогда пользователь просто выбирает профиль, а не вручную подбирает 15 провайдеров.

***

## 18. Целевая архитектура универсального framework

### 18.1. Пятислойная модель

```
┌─────────────────────────────────────────────┐
│  LAYER 5: Product / Composition             │
│  flex/crm, corex/crm, app-specific modules  │
├─────────────────────────────────────────────┤
│  LAYER 4: Bridge / Laravel Integration      │
│  *ServiceProvider, config publish,          │
│  route wiring, console commands             │
├─────────────────────────────────────────────┤
│  LAYER 3: Infrastructure Adapters           │
│  SQL implementations, filesystem,           │
│  HTTP transports, cache, queue              │
├─────────────────────────────────────────────┤
│  LAYER 2: Domain / Engine                   │
│  Pure PHP: invocation engine, idempotency,  │
│  tool runtime, webhook policies             │
├─────────────────────────────────────────────┤
│  LAYER 1: Contracts / Platform Kernel       │
│  Interfaces, value objects, enums,          │
│  error taxonomy, typed options              │
└─────────────────────────────────────────────┘
```

### 18.2. Правило dependency direction

```
Product → Bridge → Infrastructure → Domain → Contracts
```

Ни один нижний слой не должен знать о верхних. Сейчас это нарушается в нескольких местах (фасады в Domain layer, inline factory logic в Bridge layer).

### 18.3. Stability budget по слоям

| Слой | BC Policy | Deprecation | Versioning |
|---|---|---|---|
| Contracts | No breaks без major version | 2 минора min | semver strict |
| Domain | Breaks only с minor bumps | 1 минор min | semver minor |
| Infrastructure | Breaks c minor bumps | 0 — internal | semver minor |
| Bridge | Breaks с minor bumps | 1 минор | semver minor |
| Product | Breaks допустимы | — | semver patch |

### 18.4. Три продуктовых инсталляционных профиля

```
[Minimal]           [Edu Platform]          [CRM/SaaS]
core                core                    core
modules             modules                 modules
─────────           tenancy                 tenancy
                    access                  access
                    settings                settings
                    notifications           notifications
                    recurrence              operations/api
                    scheduler               webhooks
                                            import/export
                                            ledger
                                            crm
```

***

## 19. Дорожная карта к 1.0.0

### P0 — Блокеры (без этого нельзя объявлять stable API)

| Задача | Описание | Трудоёмкость |
|---|---|---|
| P0-1 | Зафиксировать canonical namespace для operations (CoreX\Api\*) | S |
| P0-2 | Убрать azguard VCS dependency | M |
| P0-3 | SHA-pin всех GitHub Actions | S |
| P0-4 | Добавить `composer audit` в CI | S |
| P0-5 | Включить Roave в fail-режим | S |
| P0-6 | Определить и задокументировать public API list | M |
| P0-7 | Устранить L13-блокер в corex/access | L |
| P0-8 | Формализовать error code taxonomy (enum/registry) | M |

### P1 — Архитектурные улучшения (повысят долгосрочную гибкость)

| Задача | Описание | Трудоёмкость |
|---|---|---|
| P1-1 | Разбить CoreServiceProvider на 4–6 узких провайдеров | M |
| P1-2 | Начать migration с static Config → DI typed services | L |
| P1-3 | Разнести Contracts по bounded contexts | M |
| P1-4 | Вынести анонимные классы из ApiServiceProvider | M |
| P1-5 | Разделить operations на api-engine + api-http + api-tools + api-webhooks | XL |
| P1-6 | Ввести InputEnvelopePolicy + UnknownFieldPolicy | M |
| P1-7 | Добавить capability profiles для impersonation | M |
| P1-8 | Добавить верхнеуровневый `permissions` в CI workflow | S |

### P2 — Platform extraction (сделают CoreX реально reusable)

| Задача | Описание | Трудоёмкость |
|---|---|---|
| P2-1 | Выделить corex/tool-contracts + corex/tool-runtime | L |
| P2-2 | Выделить corex/api-webhooks как самостоятельный reusable пакет | L |
| P2-3 | Ввести install profiles как first-class concept | M |
| P2-4 | Разделить product-tier (flex/*) от platform-tier (corex/*) | M |
| P2-5 | Ввести package boundary linter (PHPStan rule) | M |
| P2-6 | Добавить ImpersonationGrantStore + AuditSink abstractions | M |
| P2-7 | Настроить Dependabot или аналог | S |
| P2-8 | Добавить SBOM generation в CI | S |

### P3 — Polish (сделают платформу продуктом)

| Задача | Описание | Трудоёмкость |
|---|---|---|
| P3-1 | Docs portal для public contracts | XL |
| P3-2 | Package maturity labels (experimental / alpha / beta / stable) | S |
| P3-3 | Examples repo с разными composition profiles | L |
| P3-4 | CrossModuleEventBus | L |
| P3-5 | Plugin Hook System | L |
| P3-6 | AuditEventStore с replay | L |
| P3-7 | CQRS разделение для Operations | XL |

***

## 20. Оценочная матрица

### 20.1. По пакетам

| Пакет | API Clarity | Security | Stability | Reusability | Split Ready |
|---|---|---|---|---|---|
| core | 6/10 | 8/10 | 6/10 | 6/10 | Нужен |
| operations | 4/10 | 8/10 | 5/10 | 4/10 | Критично |
| tenancy | 7/10 | 8/10 | 6/10 | 6/10 | Желательно |
| access | 5/10 | 7/10 | 5/10 | 5/10 | Переименовать |
| modules | 7/10 | 7/10 | 7/10 | 8/10 | Хорошее |
| recurrence | 8/10 | 7/10 | 7/10 | 9/10 | Эталон |
| notifications | 6/10 | 7/10 | 6/10 | 5/10 | Разъединить wakeup |
| ai | 5/10 | 6/10 | 4/10 | 5/10 | Experimental |
| webhooks (внутри ops) | 9/10 | 9/10 | 7/10 | 9/10 | Выделить |
| tool runtime (внутри ops) | 9/10 | 9/10 | 7/10 | 9/10 | Выделить |

### 20.2. Приоритетные 5 решений прямо сейчас

Если принять эти пять решений осознанно, дальнейшая архитектурная эволюция станет заметно дешевле:

1. **Считать ли `CoreX\Api` официальным namespace для API-подсистемы** и переименовывать пакет `operations` в `api` или `api-engine`.
2. **Готовы ли выделить `tool-runtime` как отдельную платформенную линейку** — это лучший кандидат.
3. **Согласны заморозить static `Config` API** и постепенно переводить на typed DI config services.
4. **Будет ли `core` разделён** на contracts/domain/bridge, или останется "ядром обо всём".
5. **Какие namespaces объявляются стабильными до `1.0`**, а какие явно помечаются `@internal`.

***

*Аудит выполнен на основании прямого чтения исходников commit `091ec90b2047bfef78b600f7adc504cbed68aebb`. Все выводы привязаны к реальному состоянию кода, а не к предположениям.*
