# Эскизы верхнего слоя — ошибки, наблюдаемость, внешний API (P12–P14)

**Статус: несущие эскизы, не финальный код.** Правила использования — см. заголовок
`01-shapes-runtime.md`.

Главное ограничение фазы P13, которое легко нарушить: контракт достраивается **поверх
существующей декларативной модели модулей** (`CoreX\Modules\Extend\Api` → compiled manifest →
`OperationRegistry`), а не переводится на статический метод `Operation::definition()`. Переход на
статику сломал бы discovery и compile, которые уже работают.

---

## 1. P12 — Таксономия ошибок и наблюдаемость

```php
enum ErrorCategory: string
{
    case Authentication = 'authentication';
    case Authorization = 'authorization';
    case Validation = 'validation';
    case Conflict = 'conflict';
    case NotFound = 'not_found';
    case RateLimit = 'rate_limit';
    case Unsupported = 'unsupported';
    case Unavailable = 'unavailable';
    case Internal = 'internal';
}
```

Стабильный контракт — `code`, `category`, `path`, `retryable` и документированные `details`.
`message` предназначен человеку и **не является контрактом**: клиент его не парсит, и его смена
не breaking. Это различие нужно записать в `api-policy.md`, иначе первый же рефакторинг текста
сломает чей-то `if (str_contains($message, ...))`.

Пять «недоступностей», которые сейчас сливаются в один ответ, — разные причины и разные действия
клиента:

```php
enum UnavailabilityReason: string
{
    case CapabilityUnavailable = 'capability_unavailable';  // пакет/адаптер не установлен
    case CapabilityDisabled = 'capability_disabled';        // установлен, выключен конфигурацией
    case OperationUnsupported = 'operation_unsupported';    // драйвер не умеет эту операцию
    case AccessDenied = 'access_denied';                    // права актора
    case FeatureNotEntitled = 'feature_not_entitled';       // лицензия/тариф
}
```

Правило result vs exception (записать как статью, применять точечно):

```text
ожидаемый бизнес-отказ        → типизированный результат/решение
ошибка программиста/конфига   → исключение
инфраструктурный сбой         → исключение с безопасным контекстом
частичный отказ батча         → отчёт с ошибками по записям
конфликт параллелизма         → доменное исключение либо типизированный конфликт
«не найдено» в запросе        → nullable/optional, если это нормальный случай
```

Наблюдатель длительной операции:

```php
interface OperationObserver
{
    public function started(OperationStarted $event): void;

    public function completed(OperationCompleted $event): void;

    public function failed(OperationFailed $event): void;
}
```

Обязательный контекст каждого события: идентификатор операции, correlation, ссылка на аккаунт и
воркспейс, актор, имя пакета и операции, длительность, исход, номер повтора, отредактированные
детали отказа. Собственная tracing-платформа не строится — только bridge к PSR-3 и OpenTelemetry.

**Ограничение cardinality — исполняемое правило, а не пожелание.** Идентификатор аккаунта не
может быть label'ом метрики: он неограничен. Допустимый набор label'ов объявляется списком
(`package`, `operation`, `outcome`, `category`, `retry_bucket`), и всё, что вне списка, уходит в
структурированный лог и трассу, но не в метрику. Проверяется тестом: попытка передать
неразрешённый label отклоняется.

**Сверить с деревом:** `packages/api/src/{ApiError,ErrorBoundary}.php`,
`Internal/{ErrorMapper,EnvelopeSerializer}.php` (конверт `{data,error,meta}` и `X-Request-ID`
уже есть — расширяем, не переписываем), `packages/core/src/Exceptions/**`,
`packages/tenancy/src/Impersonation/**`.

---

## 2. P13 — Ядро внешнего API

### 2.1 Режимы операции

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

Это **не** существующий `mode` декларации (`entity|extendResource|operation|tool`) — там другое
понятие. Новое поле добавляется в декларацию `Api` и валидируется компилятором модулей.

Гарантии, привязанные к режиму (иначе enum декоративен): `Query` не меняет источник истины,
допускает кеш и курсорную пагинацию, не требует ключа идемпотентности; `Command` проходит
семантическую авторизацию, поддерживает идемпотентность и оптимистичный параллелизм, публикует
события через транзакционную границу, пишет audit; `AsyncCommand` возвращает приём, а не результат.

### 2.2 Версия и профиль

По D2: мажор в пути (`/api/v1`), никаких датовых версий. Профиль компилируется один раз и
замораживается — та же механика, что в P11.

```php
final readonly class ApiVersion
{
    public function __construct(
        public string $major,                 // 'v1'
        public ApiVersionStatus $status,      // stable | experimental | deprecated
        public ?string $supportedThrough,     // релизная линия продукта, НЕ календарная дата
    ) {}
}

final readonly class ApiProfile
{
    /** @param array<string, OperationDescriptor> $operations */
    public function __construct(
        public ApiVersion $version,
        public array $operations,
        public DeprecationCatalogue $deprecations,
        public CapabilityCatalogue $capabilities,
        public string $fingerprint,
    ) {}
}
```

Диспетчер получает **готовый** профиль, а не вычисляет его на запросе. Ветвлений `if
($version === ...)` в контроллерах, резолверах, генераторе OpenAPI и tool-проекции быть не должно —
именно это условие делает версионирование поддерживаемым.

### 2.3 Capability/discovery — несущая часть D2, а не косметика

```text
GET /api                          → мажоры, дефолтный мажор, ссылка на схему
GET /api/v1/capabilities          → эффективные возможности для текущего токена и аккаунта
GET /api/v1/openapi.json          → схема развёрнутого сервера
```

```json
{
  "product": { "version": "1.4.0", "edition": "self-hosted", "build": "..." },
  "api": { "stableVersions": ["v1"], "defaultVersion": "v1" },
  "capabilities": {
    "attributes.schema.v1": { "enabled": true },
    "search.facets.v1": { "enabled": false, "reason": "capability_disabled" },
    "transfer.bulk.v1": { "enabled": true, "limits": { "maxRowsPerRun": 500000 } }
  }
}
```

Два инварианта с гейтами: capability описывает **поведенческий контракт**, а не инфраструктуру
(`{"driver":"meilisearch"}` наружу не отдаётся никогда); доступность capability не означает права
актора — авторизация остаётся в доменном слое.

Отказ по отсутствующей возможности — структурированный и машинно-обрабатываемый:

```json
{
  "errors": [{
    "code": "CAPABILITY_UNAVAILABLE",
    "category": "unsupported",
    "retryable": false,
    "details": { "capability": "search.facets.v1", "reason": "capability_disabled" }
  }],
  "meta": { "requestId": "req_...", "correlationId": "corr_...", "apiVersion": "v1" }
}
```

Плюс контент-негоциация `application/problem+json` для транспортного слоя (RFC 9457) — два слоя,
а не выбор одного: стабильные коды нужны клиентскому коду, problem+json — стандартным
инструментам.

### 2.4 Идемпотентность

```php
enum IdempotencyState: string
{
    case Started = 'started';
    case Completed = 'completed';
    case FailedRetryable = 'failed_retryable';
    case FailedFinal = 'failed_final';
    case Expired = 'expired';
}

interface IdempotencyStore
{
    public function begin(
        IdempotencyScope $scope,
        string $key,
        string $requestFingerprint,
    ): IdempotencyAttempt;
}
```

Четыре правила из первичного источника (Stripe), каждое — отдельный тест:

1. тот же ключ + тот же отпечаток → возвращается **сохранённый ответ первого исполнения**,
   включая сохранённый 5xx;
2. тот же ключ + другой отпечаток → конфликт, а не старый результат;
3. валидация не прошла до начала исполнения → результат **не сохраняется**, ключ можно
   переиспользовать;
4. retention конечен (порядка суток и настраивается) — идемпотентность не является долговременной
   дедупликацией.

`requestFingerprint` считается по канонизированному входу операции плюс имя операции плюс мажор
API. Канонизация — детерминированная сериализация (в дереве уже есть `Internal/JsonCanonical.php`,
переиспользовать её).

### 2.5 Курсорная пагинация

```php
final readonly class CursorPayload implements DurablePayload
{
    /** @param array<string, scalar|null> $keys */
    public function __construct(
        public int $version,          // версия формата
        public string $ordering,      // версия порядка: 'contacts.created.v1'
        public string $direction,
        public array $keys,           // значения сортировки + обязательный tie-breaker
        public string $scopeHash,     // аккаунт + отпечаток фильтров + мажор API
        public int $issuedAt,
        public int $expiresAt,
        public string $keyId,
    ) {}
}
```

Пять требований, которые чаще всего теряют:

- курсор несёт **значения**, а не имена полей и направления; SQL строится только из серверного
  реестра порядков;
- обязателен уникальный tie-breaker, иначе равные значения сортировки дают пропуски и дубли;
- подпись HMAC обязательна — base64 это кодирование, а не защита;
- привязка к аккаунту и отпечатку фильтров обязательна, иначе курсор становится примитивом
  межаккаунтного раскрытия;
- версия формата и версия порядка — **разные** поля; смена `ORDER BY` не переинтерпретирует старые
  курсоры молча, а даёт машинно-читаемый отказ `ordering_version_unsupported`.

В дереве уже есть `packages/search/src/Internal/Query/SearchCursorCodec.php` — форму брать оттуда,
а не изобретать вторую.

### 2.6 Внешний идентификатор ресурса

```php
final readonly class ResourceId
{
    public function __construct(
        public string $type,     // 'crm_contact'
        public string $value,    // ULID
    ) {}

    public function __toString(): string
    {
        return $this->type.'_'.$this->value;   // crm_contact_01K5...
    }
}
```

Префикс — не граница авторизации, а средство раннего обнаружения ошибки клиента и читаемости логов.

### 2.7 Пять слоёв авторизации

```text
scope клиента        crm.contacts.write        объявлено на операции
право актора         contact.update            проверяет authorizer
политика записи      этот контакт этому актору  доменная политика
политика поля        contact.salary            фильтрация ответа и проверка записи
граница аккаунта     контакт принадлежит       инвариант доступа к данным
```

Ни один слой не заменяет другой. `onlyFields` из декларации обязан фактически применяться в
диспетчере на **всех** путях — и в ответе, и при записи (N08).

### 2.8 Маршруты и приём асинхронной команды

Пакет регистрирует свои маршруты сам, с возможностью отключить (N07: сейчас их монтирует
приложение, что для «API как продукта» — дырка в поставке). Async command отвечает `202 Accepted`
с идентификатором запуска и адресом статуса, а не `200 {"status":"queued"}`.

### 2.9 Snapshot'ы схемы и CI-гейт

```text
api/v1/openapi.json
api/v1/operations.json
api/v1/errors.json
api/v1/webhooks.json
```

CI отклоняет семь изменений: удаление операции, удаление поля ответа, добавление обязательного
входа, смену типа, смену nullability, удаление значения перечисления, смену стабильного кода
ошибки (плюс удаление webhook-топика и смену контракта пагинации в P14). Для каждого — отдельная
негативная проверка: гейт должен краснеть на контролируемом нарушении, иначе он не проверен.

**Сверить с деревом:** `packages/api/src/{Dispatcher,Operation,OperationRegistry,OpenApi}.php`,
`packages/modules/src/Extend/Api.php`, `packages/modules/src/Compiler/ApiOperationValidator.php`
(там же регулярка `^v[1-9][0-9]*$` — она остаётся и получает нормативное определение),
`packages/api/src/Internal/JsonCanonical.php`.

---

## 3. P14 — Бюджеты, bulk, webhooks

### 3.1 Стоимость и бюджет

```php
interface OperationCostEstimator
{
    public function estimate(OperationDescriptor $operation, OperationInput $input): EstimatedCost;
}

interface OperationBudget
{
    public function reserve(ClientIdentity $client, string $accountId, EstimatedCost $cost): BudgetReservation;
}

interface BudgetReservation
{
    public function settle(ActualCost $cost): void;   // возврат неиспользованного
    public function reject(): void;
}

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

Три ограничения, которые делают модель пригодной для self-hosted:

- оценка **не делает запрос в базу** ради оценки — только объявленный вес плюс параметры вызова;
- иерархия ключей: глобальный предохранитель → аккаунт → клиентское приложение → актор;
  идентификатор операции в ключ хранилища **не входит** (иначе cardinality неуправляема);
- режим по умолчанию — наблюдение с отказом только на глобальном предохранителе (Q4): SaaS-лимиты
  в self-hosted установке неуместны, но защита от самоперегрузки нужна.

Оценщик принимает набор запрошенных полей и вложенность как входные данные — это и есть весь
задел под другой транспорт по D7, отдельной работы под GraphQL нет.

### 3.2 Bulk / long-running

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

```text
POST /api/v1/bulk-operations            → 202 + id + statusUrl
GET  /api/v1/bulk-operations/{id}       → статус, прогресс, счётчики
POST /api/v1/bulk-operations/{id}/cancel
GET  /api/v1/bulk-operations/{id}/result
GET  /api/v1/bulk-operations/{id}/failures
```

Граница по D9: слой операций владеет внешним контрактом (приём, авторизация, мажор API,
идемпотентность, статус, представление, отмена, срок жизни); чтение батчей, маппинг, валидация,
checkpoint, запись, ошибки по записям и возобновление остаются в `corex/transfer`. Второй движок
исполнения не создаётся.

Два достраиваемых свойства `corex/transfer` (иначе контракт не выполним):

```php
// Кооперативная отмена: исполнитель проверяет флаг на границе батча, не посреди записи.
interface CancellationSignal
{
    public function isCancelled(): bool;
}
```

И фактическое применение объявленных лимитов: `TransferLimits.maxMemoryMiB` и
`maxRuntimeSeconds` сейчас объявлены, но внутри исполнителя не проверяются. Проверка — на
границе батча, с корректным чекпойнтом перед остановкой, чтобы возобновление было возможно.

### 3.3 Webhooks

Форма подписи и заголовков берётся из Standard Webhooks дословно, потому что у получателей уже
есть готовые библиотеки под неё:

```text
webhook-id: msg_01K5...
webhook-timestamp: 1789805563
webhook-signature: v1,<base64> v1,<base64>     ← несколько подписей при ротации
```

```php
$signedContent = $webhookId.'.'.$timestamp.'.'.$rawBody;
$signature = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, binary: true));
```

Требования, каждое из которых — отдельный тест:

- подпись считается над **точными байтами** тела до любого декодирования;
- окно проверки времени порядка пяти минут, timestamp входит в подписываемую строку;
- `webhook-id` — ключ идемпотентности получателя; при повторе бизнес-эффект не повторяется;
- при ротации отправляются **обе** подписи в одном заголовке, получатель принимает любую;
- успех — только 2xx; 410 отключает endpoint; уважается `Retry-After`;
- backoff экспоненциальный с jitter и конечным числом попыток, затем dead-letter;
- история попыток хранится с редакцией секретов и поддерживает явный replay;
- payload небольшой (ориентир — до 20 KB), для крупных сущностей отдаётся идентификатор и ссылка;
- SSRF-политика: запрет приватных, loopback, link-local и metadata-диапазонов, проверка DNS
  rebinding;
- подписка фиксирует мажор API, а топики версионированы как интеграционные события.

Доставка идёт из outbox P5, а не напрямую из обработчика запроса. В тестах доставки — только на
локальный приёмник; обращений к внешним хостам быть не должно.

**Сверить с деревом:** `packages/transfer/src/Internal/Transfer/DatabaseTransferRunner.php`
(шаг, fence-токен, статусы receipt'а), `packages/transfer/src/Data/TransferLimits.php`
(объявленные, но не применяемые лимиты), outbox из P5, `packages/api/src/Internal/ErrorMapper.php:46`
(уже маппит 429 → `rate_limited` — код переиспользуется, не заводится второй).
