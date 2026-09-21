# P14 — нормативный дизайн стоимости, bulk и webhook-доставки

## 0. Статус, входы и доказанные границы

Authoring Model: GPT-6 Astra/high, provider-thread-state, 2026-09-20.
Это дизайн, не выполненные изменения. D14 запрещает исполнение до детализации
P15–P17 и общего аудита. P13 operation-contract/1, P5 message-envelope/1,
P12 operation-observer/1 и P4 scalar scope/Clock потребляются без изменения.

RAG:— В дереве нет webhook/store/budget. `DatabaseTransferRunner::step()` уже
исполняет один батч в tenant-транзакции с checkpoint/claim fence; `TransferReceipt`
уже допускает cancelled. Лимиты runtime/memory не применены. `DatabaseExportRunner`
имеет seal/emit, coherent manifest, проверку part hash и запрет resume незапечатанного
export attempt. Публичного export-runner порта и фабрики transfer jobs нет.
Следовательно нельзя обещать одинаковый resume импорта и экспорта или импортировать
`CoreX\Transfer\Internal` из api. Это исправления эскиза §3, не смена D9.

Новые интерфейсы — только `src/Contracts`, DTO — `src/Data`, enums — `src/Enums`,
реализации — `src/Internal/{Cost,Bulk,Webhook}`. PHP strict/final/promotion/Override,
именованные однотипные аргументы. Новых runtime dependencies нет. API optional
adapter к transfer активируется только при установленном пакете и явной DI-фабрике;
core/transfer не зависят от api. GraphQL отсутствует. Product docs и release-visible
изменения идут в docs и один корневой CHANGELOG, плановые evidence — только здесь.

## 1. Объявленная стоимость (D74)

P14.1 достраивает `Modules\Extend\Api` методом `cost(array $recipe): self` и
serialized `cost` metadata. Defaults для legacy: base=1, остальные веса=0,
max_units=10000. Это additive metadata в operation-contract/1, не новая authority.
PHP attribute из S11 отклонён: второй источник расходился бы с compiled declarations.
Compiler принимает только ключи `base`, `row`, `field`, `relation`, `attribute_filter`,
`highlight`, `side_effect`, `max_units`, целые 0..10000; base/max_units >=1.
Legacy descriptor hash сохраняет старые bytes при отсутствии cost; наличие cost
аддитивно включает canonical recipe в hash. Bulk mode требует явной recipe.

P14.2 API projection получает trailing `?array $cost = null`, сохраняет совместимость.
`CostInput` — readonly DTO: pageRows:int, selectedFields:int, relationNodes:int,
attributeFilters:int, highlight:bool, exportRows:int, sideEffects:int. Все counts
неотрицательные и уже schema-validated; отсутствующая page size берётся из declared
page maximum, не из DB. Для export без известного количества требуется bounded
max_rows; unbounded bulk admission запрещён. Поля/relations берутся только из
разрешённой схемы, не из произвольных строк запроса. Structural input caps
действуют и в observe mode; overflow и превышение max_units дают validation error.

```php
interface OperationCostEstimator
{
    public function estimate(Operation $operation, CostInput $input): EstimatedCost;
}
// EstimatedCost: units:int, recipeHash:string; ActualCost: units:int.
// Финальные readonly DTO; units >= 0, estimate >= 1.
```

Формула: base + row*max(pageRows,exportRows) + field*selectedFields +
relation*relationNodes + attribute_filter*attributeFilters + highlight*(int)highlight
+ side_effect*sideEffects. Каждый multiply/add проверяется до integer overflow.
Estimator pure: никаких database/cache/network вызовов или reflection handler.
ActualCost считается той же recipe по server-observed counters, не client data и
не wall time. Если handler не сообщает счётчики, actual=estimate, а не 0.

## 2. Бюджет: authority, атомарность, возврат (D75)

### 2.1 Контракты и default Q4

```php
interface OperationBudget
{
    public function reserve(BudgetScope $scope, EstimatedCost $cost): BudgetReservation;
}
interface BudgetReservation
{
    public function settle(ActualCost $cost): void;
    public function reject(): void;
}
```

`BudgetScope` содержит installationId/accountId/clientId/actorId из trusted context,
не request body; отсутствие account запрещено, anonymous client/actor нормализуются
в фиксированный sentinel. `ThrottleStatus` — capacity:int, available:int,
restoreRate:int, observedAt:DateTimeImmutable. Operation id не входит в bucket key.
HMAC-SHA256 от canonical tuple с namespace/version даёт фиксированный key без PII.
Четыре keys: global(installation), account(installation,account), client(+client),
actor(+actor). Уникальных scopes может быть много: auth до reserve, TTL idle buckets,
bounded request rate и retention ограничивают рост; «нет operation id» не доказывает
конечную cardinality. Scope IDs никогда не становятся metric labels (P12).

Default: mode=observe для account/client/actor, global=enforce. Локальные self-hosted
настройки: global capacity=10000 units, refill=1000 units/sec; нижние capacity=10000,
refill=1000 только наблюдаются, явный enforce включает их. Это начальные защитные
значения CoreX, не SaaS тариф/SLO и не число из Perplexity. Нельзя молча отключить
global при отсутствии store: включённый API без настроенного budget store даёт
capability_unavailable до handler. Existing installations получают migration/config
инструкцию, не незаметный fail-open. Единицы не деньги/биллинг.

### 2.2 SQL algorithm

Один явно сконфигурированный shared budget connection на installation; это **не**
active tenant connection. Все узлы API указывают на одну SQL authority. Тесты доказывают
две tenant DB + общий store. При boxed это отдельная explicit binding на ту же DB.
Физическая изоляция tenant payload сохраняется: budget хранит только HMAC keys/units.

Reserve в одной короткой transaction: upsert четыре bucket rows; lock их в
лексикографическом key order; refill по injected Clock (отрицательная дельта=0,
монотонное last_refill); вычислить все enforce deficits; если любой отказ — rollback
без debit; иначе debit всех четырёх и insert reservation. In observe нижний bucket
может уйти в отрицательное значение; ограничить arithmetic и display available>=0.
Никаких четырёх независимых cache increments. PG retry только на deadlock/serialization
до внешнего эффекта; handler выполняется после commit reserve, вне этой transaction.

Reservation: случайный id, scope bucket keys, estimated units, charged units,
state=held|settled|rejected|abandoned, created_at/expires_at (max 1h), recipe_hash.
settle/reject lock reservation и те же buckets; первая terminal transition выигрывает,
повтор с тем же outcome no-op, конфликт terminal outcome не делает второй refund.
settle actual <=estimate возвращает разницу; actual>estimate списывает excess в debt,
не отменяет совершённую операцию; метрика estimate_underrun, следующий reserve видит
недостаток. reject допустим только до handler admission; возвращает всю estimate.
Crash/неизвестный исход переводится в abandoned **без refund**. Refill естественно
восстанавливает пропускную способность; expiry не является доказательством nonexecution.
Missing reservation после retention никогда не даёт refund.

### 2.3 Invocation ordering и async

`Internal\Cost\BudgetAdmission` вызывается из `DefaultOperationInvoker` и живёт внутри P13 shared invoker, после auth/schema/field admission.
Idempotency replay не списывает стоимость исходного handler второй раз: берёт
base=1 из global для обслуживания replay. На первом вызове reserve следует за
idempotency ownership, перед handler; отказ reserve освобождает не начатое владение
idempotency без сохранения отказа как выполненного результата. Auth/validation failures
защищаются host request limiter; SQL budgets не заменяют edge/body/concurrency caps.
Все pre-handler exits reject; после admission unknown failure settle estimate.
Observer failure не меняет business outcome и не раскрывает секреты.

Bucket capacity/available и reservation estimate/charged хранятся в micro-units;
refill_rate — whole units/sec, public DTO — whole units. Refills/refunds переводятся
между ними в одном месте. Refills не увеличивают available выше capacity.
Refill использует целые micro-units (units*1_000_000), elapsed microseconds*rate;
checked arithmetic и saturation at capacity. Не терять дробный refill при частых вызовах.

P13 `OperationResult` получает trailing metadata с пустым default; HTTP meta.cost
и tool result metadata идентичны: requested, actual, mode, global throttle snapshot.
Не отдавать внутренние keys/actor/account или все ancestor остатки. 429 использует
существующий rate_limited; Retry-After = ceil(max enforce deficit/refill), не меньше 1.
Store failure — unavailable, не фиктивный 429. Timeout не означает refund.

Bulk submit оплачивает bounded admission cost, каждый шаг worker резервирует стоимость
батча отдельно. Не держать reservation на миллионы строк/всю жизнь job. При 429 worker
планирует next_attempt_at, не spin-loop. Poll/cancel/result тоже имеют bounded cost.

## 3. Новые схемы и scope DDL (D76)

P14.3 — единственный G-DDL item; три новых migration-файла, **никаких edits старых**.
Timestamps UTC microseconds; ids UUID/ULID varchar(64), hashes char(64), counts bigint
nonnegative, state string с validation; JSON переносим между SQLite и PG. Enum DB не нужен.

| Table | Connection / keys / обязательные columns |
|:--|:--|
| sys_api_budget_buckets | shared budget; PK key char(64); capacity bigint, available bigint signed, refill_rate bigint, last_refill_at, last_used_at |
| sys_api_budget_reservations | shared budget; PK id; keys JSON(ровно 4), estimate bigint, charged bigint, recipe_hash, state, created_at, expires_at; index(state,expires_at) |
| sys_api_bulk_operations | tenant; PK id; unique(major,operation_id,submission_id); scope JSON, actor_id, client_id, contract_hash, extension_stamp JSON, input_ref JSON, transfer_ref nullable, status, terminal_status nullable, revision bigint, lease_owner nullable, lease_until nullable, cancel_requested_at nullable, checkpoint JSON, progress JSON, result_ref JSON nullable, error JSON nullable, attempt int, next_attempt_at, created_at, finished_at nullable, expires_at nullable; index(status,next_attempt_at,lease_until) |
| sys_api_webhook_subscriptions | tenant; PK id; revision bigint; major, topic, topic_version int, endpoint_url, status(active/disabled), max_pii_class, key_refs JSON, created_at, updated_at; index(status,topic,major) |
| sys_api_webhook_deliveries | tenant; PK id (webhook-id); unique(subscription_id,event_id); subscription_revision bigint, event_id, topic, topic_version, major, endpoint_snapshot, scope JSON, body text, body_hash, status(pending/sending/retry/succeeded/dead), attempt int, replay_count int, lease_owner nullable, lease_until nullable, next_attempt_at, created_at, finished_at nullable, expires_at, last_error nullable; index(status,next_attempt_at,lease_until) |
| sys_api_webhook_attempts | tenant; PK id; unique(delivery_id,attempt); lease_owner, started_at, finished_at nullable, outcome, http_status nullable, duration_ms nullable, error_code nullable; index(delivery_id,started_at) |

Budget migrations публикуются отдельным `corex-api-budget-migrations`, остальные
`corex-api-tenant-migrations`; никогда автоматически не запускаются. Новый registrar
получает explicit connections, проверяет driver/database identity. Без настроек нет
DDL на случайном default connection. Foreign keys только внутри owner tables где
совместимы с retention; delivery не cascade-delete при удалении subscription.
Table ownership записывает оба scope, PII classes и retention. Rollback удаляет только
эти шесть таблиц, тест на disposable DB; production rollback с данными не авторизован.

## 4. Bulk contract и store (D77)

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
interface BulkOperationStore
{
    public function submit(BulkOperationDefinition $definition): BulkOperation;
    public function find(DataScope $scope, string $id): ?BulkOperation;
    public function claim(DataScope $scope, string $worker): ?BulkOperation;
    public function save(BulkOperation $operation, string $leaseOwner, int $revision): bool;
    public function cancel(DataScope $scope, string $id): BulkOperation;
}
```

Definition readonly: id, submissionId (P13 idempotency attempt id), operationId,
major, contractHash, encodedScope, actorId, clientId, extensionStamp, inputRef.
InputRef — authorized tenant-storage object id + sha256 + length + format, либо bounded
canonical parameters <=20KiB. Не URL, путь диска, serialized context/model или credentials.
BulkOperation — definition + columns §3, readonly snapshot; save uses CAS.
`pending→running→completed|failed`; `pending→cancelled`; `running→cancelling→cancelled`.
Completed/failed выигрывают если закоммичены до cancel lock; cancel terminal idempotent.
Нельзя сообщить cancelled пока старый worker ещё может commit: lease ownership fenced
в одной tenant transaction с transfer checkpoint. Effect transaction не имеет
автоматического retry (`attempts: 1`); recovery проходит receipt/reconciliation. Expired только из terminal, исходное
состояние хранится в terminal_status. Cancelling никогда автоматически не completed.

claim использует P5 короткий lease/SKIP LOCKED pattern, attempts и revision fence.
Перед работой trusted host инициализирует tenant; restorer только сверяет active
connection (D49), права/restore epoch/extension version revalidate на каждом шаге.
Lease expiry разрешает восстановление только через checkpoint/receipt; потерянный
ответ не доказывает отсутствие эффекта. Unknown target — failed с safe
bulk_reconciliation_required, без слепого retry. Durable dispatch: pending-row polling
остаётся authority; queue notification необязательна и может быть потеряна.

P13 идемпотентность + bulk submit уникальность исключают second job при crash между
insert и idempotency completion. Replay response содержит тот же id/status_url.
Schema-invalid/denied submit не создаёт row. Права resource/field применяются к каждой
записи domain sink/source, не только к start. Возможности операций объявляет модуль;
универсальный arbitrary class executor или generic CRUD не появляется.

## 5. Transfer: cancellation, limits, export port (D78)

### 5.1 Импорт

Новый `CoreX\Transfer\Contracts\CancellationSignal`:
`isCancelled(): bool`. Существующий `TransferRunner::step` не меняется. Runner
получает trailing optional CancellationSignal и внутренний `ExecutionGuard`; default
NeverCancelled сохраняет старую DI, guard всегда применяет существующие limits.
`ExecutionGuard` принимает closures monotonicNanoseconds/memoryBytes для test seams;
production — hrtime(true), memory_get_usage(true), Clock не используется для duration.

Guard перед read, после bounded read и перед каждой записью проверяет время/память;
source contract обязан вернуть <=batchRows, <=maxRecordBytes/row, <=maxFileBytes/source,
maxFields/maxDepth enforced codec'ом. BatchRows проверяется runner'ом до target calls.
Если time/memory исчерпаны в середине batch: бросить internal `TransferLimitReached`
**вне** row catch(Throwable), rollback всего batch, вернуть из outer boundary receipt
running с прежними cursor/checkpoint и errorCode runtimeLimitReached|memoryLimitReached.
Target contract допускает здесь только transactional/idempotent writes, external side
 effects требуют собственный WriteToken reconciliation; rollback не отменяет сеть.
После successful batch checkpoint уже committed, лимит перед следующим step не теряет его.
Один record/read call не preemptible: worker/process timeout и memory_limit выше
qualified profile остаются host backstop; гарантии hard wall-clock cap нет.

Cancellation проверяется до read и после commit на следующем step, не посреди write.
Pending cancel возвращает cancelled без следующего target call. Bulk worker использует
ту же DB transaction для lock bulk row, step, receipt и status update; cancel writer
ждёт не дольше одного bounded batch. Already committed rows не откатываются. Dry-run
не меняет checkpoint/source/target ни при cancel, ни при limit.

### 5.2 Экспорт

Добавить `Contracts\ExportRunner` с текущими public `seal(...)` и `emit(...)`
сигнатурами, `DatabaseExportRunner implements ExportRunner`. Это additive public port,
а не импорт чужого Internal. Guard/cancellation применять до каждой page и stagePart;
прерывание seal откатывает capture DB и помечает/собирает orphan immutable blobs
через существующий storage cleanup. Старый coherent manifest не продолжается с нового
snapshot: новый attempt/id, полное повторное capture. Emit только sealed part,
recheck hash/tenant/restore/field access. Ограничение part count не использовать как
замену batchRows: rows/page и maxFileBytes проверяются отдельно.

### 5.3 API adapter и отсутствие фабрики в дереве

API владеет `Contracts\BulkTransferFactory::create(BulkOperation $operation): BulkTransferSession`;
фабрика предоставляется приложением через DI, не runtime closure из сообщения.
`BulkTransferSession` содержит public TransferRunner или ExportRunner, TransferRef,
StepToken, CancellationSignal wiring, и export manifest/cursor при bulk_query.
Session также содержит public `TransferResultReader` (P14.8):
`failures(DataScope $scope, TransferRef $ref, ?string $afterOrdinal, int $limit): array`
возвращает `{records:list<{ordinal,code,path}>,next:?string}`; `result(DataScope $scope,
TransferRef $ref): array` возвращает safe typed manifest. Реализация читает только
owner transfer tables и reauthorizes scope; API не читает чужие tables напрямую.

Создание tenant transfer job с id=bulk operation id и initial checkpoint делает
фабрика атомарно/idempotently; повтор create возвращает текущий token, не старый.
Обязательная fake consumer fixture доказывает это. Factory не установлена →
capability_unavailable до submit; отсутствие corex/transfer тоже fail-closed.

API `TransferBulkExecutor` получает фабрику и делегирует batch reads/mapping/writes
**только** transfer. Не пишет corex_transfer_* напрямую. Он преобразует public receipt
в status/progress/refs, экспорт делегирует public ExportRunner. `BulkWorker` восстанавливает
trusted context, берёт lease и budget, делает один bounded step, persists progress,
releases lease; retries по delay. ContractHash/extensionStamp drift — fail closed,
не silent rebind. P12 started/completed/failed получают correlation + bounded metrics.

## 6. Bulk endpoints, results и failures

P13 routes дополняются фиксированными literal routes **перед** wildcard. Все действия
через shared invoker и те же guards; HTTP не обходит operation semantics.

| Method/path under /api/v1 | Успех / ограничения |
|:--|:--|
| POST /bulk-operations | 202 + operation_id/status_url, Location; required idempotency; declared bulk operation + authorized input_ref |
| GET /bulk-operations/{id} | 200 status/progress/counters, error отдельно от admission; Retry-After=2 while active |
| POST /bulk-operations/{id}/cancel | 202 cancelling или 200 terminal; идемпотентное действие, не rollback |
| GET /bulk-operations/{id}/result | 200 typed manifest/NDJSON stream только sealed/complete; 409 not_ready, 410 expired |
| GET /bulk-operations/{id}/failures | cursor page <=100 safe ordinal/code/path records, без raw input/secret |

Для незнакомого/чужого id одинаковый not_found. Чтение/cancel требует tenant + owning
client/actor или explicit delegated ability; resource possession не даёт права.
Result_ref — tenant storage locator, size/hash/media_type, expires_at; filesystem path,
credential/signed external URL не публикуется. Export защищён current policy при скачивании;
unsafe stale capture не отдаётся после revocation. Неполный импорт имеет counters
succeeded/invalid/unknown и failures link; completed с invalid допустим как
частично выполненный batch с `has_failures=true`, unknown никогда success.

Result TTL=24h after terminal, metadata/tombstone TTL=30d; configurable в пределах
result<=metadata и input/idempotency retention safety. Expiry очищает blobs и PII refs,
сохраняет original terminal outcome до metadata TTL; затем 404. Active jobs не prune.
Resume импортов — internal recovery текущей nonterminal job, не endpoint resurrect
cancelled job. New submission после cancelled — новый id с новым idempotency key.

## 7. Topics, subscription policy и доверие (D79)

`WebhookTopic` readonly: name (P5 canonical versioned contract name), version:int,
major, schema:array, schemaHash, piiClass, projectorService:string.
`WebhookTopicCatalogue` — immutable projection explicit typed ApiOptions mappings к
P5 declared MessageContract; проверяет existence/version/schema/PII. Не второй mutable
message registry, не runtime subscription на произвольный Laravel event. Никакого
девятого Modules extender. `Contracts\WebhookPayloadProjector::project(MessageEnvelope
$message, WebhookTopic $topic): array` — explicit public export allow-list;
opaque service id резолвится только из installed code map, не из JSON/body.

Подписка tenant-local фиксирует major/topic/version, https endpoint и максимум PII.
Topic personal/restricted по умолчанию запрещён для подписки; нужен явный actor ability
webhooks.subscribe_sensitive и policy разрешение. Secret class запрещён всегда.
Resource field policy участвует в projection. Payload содержит event_id/type/version/
occurred_at/data/correlation_id, без actor credentials и internal connection ids.
Лимит canonical UTF-8 body=20*1024 bytes после projection; excess — dead с safe
payload_too_large, без обрезания/загрузки произвольного URL. Крупный ресурс — id +
относительная API ссылка, защищённая обычным auth. Payload encrypt-at-rest наследует
storage deployment, эта фаза не обещает crypto-shredding.

`WebhookSubscriptionStore` create/update/find/disable принимает DataScope и trusted
policy admission; optimistic expectedRevision обязателен для update. Keys в row —
SecretRef identifiers, не секреты. Endpoint revision immutable для уже созданной delivery.
Изменение URL не перенаправляет старую delivery; она идёт на snapshot только если
subscription revision всё ещё current, иначе dead endpoint_changed. Новый endpoint
потребует новую подписку/новые события; replay не меняет destination тайно.

## 8. Outbox fan-out (D80)

P5 relay остаётся доставщиком в существующий MessagePublisher. API добавляет explicit
publisher decorator/listener на declared integration contracts; внутренний Laravel
DomainEvent напрямую наружу не выходит. Tenant context должен быть уже active.
В одной tenant transaction: Inbox::claim consumer=`api.webhooks.v1` по envelope.id;
выбрать active matching subscriptions; проверить их revision/PII/policy; serialize
body один раз на subscription/profile; insert unique(subscription_id,event_id).
Inbox и inserts коммитятся вместе; crash/retry не теряет и не удваивает fan-out.
Сеть вне transaction и вне P5 publish. Отказ body projection сохраняет dead delivery
с safe code, не делает бесконечно poison весь outbox. Другие P5 consumers не поглощаются
decorator'ом; forwarding существующему publisher сохраняется и защищается их inbox.

Fan-out snapshot — active subscriptions на **первой успешной materialization**, не
на occurred_at. Подписка после materialization не получает старые события автоматически.
При ошибке transaction snapshot не считается принятым. Fan-out bounded: <=100 active
subscriptions/account и <=100 matching/event, hard config cap; превышение subscription
create отклоняется. Так атомарный fan-out не маскирует неограниченный batch.

webhook-id = delivery.id, event_id = исходный envelope.id. Оба стабильны;
разные subscriptions имеют разные delivery ids, event_id общий. Body bytes/hash и
major/version остаются неизменными на retries/replay. Ни ordering, ни exactly-once
sender не обещает (D28). Consumer fixture делает transactional insert inbox + effect,
показывает один эффект при двух deliveries одного id; это требование receiver,
а не магическая гарантия sender.

## 9. Подписи, secrets и rotation (D81)

Standard Webhooks HMAC-SHA256; принимается secret `whsec_` + strict base64 ровно 32
random bytes (локальный профиль внутри допустимого стандартом диапазона 24–64 bytes). SecretRef resolver P7 даёт secret только в памяти signer'а;
subscription API не возвращает secret после provisioning. No secret logs/errors/DB JSON. Никогда не использовать один key для разных subscriptions.

```php
$bytes = base64_decode(substr($secret, strlen('whsec_')), strict: true);
// Reject missing prefix, invalid base64, strlen($bytes) !== 32 before signing.
$content = $deliveryId.'.'.$timestamp.'.'.$rawBody;
$signature = 'v1,'.base64_encode(hash_hmac('sha256', $content, $bytes, binary: true));
```

ID server-generated без '.', timestamp decimal Unix seconds from injected Clock.
Headers ровно webhook-id/webhook-timestamp/webhook-signature. Подписываются сохранённые
raw bytes, не decode/re-encode. Новый timestamp/подпись на каждой попытке, не occurred_at.
During overlap максимум 2 active SecretRefs; signatures space-delimited; порядок key id
детерминирован, key ids не добавляются в wire. Receiver accepts any matching signature
через hash_equals и timestamp +/-300sec; это окно предотвращает stale request replay,
но **не** заменяет durable dedup сроком всего retry/manual replay horizon.

Ротация: provision next key и доставить по trusted host channel; atomically добавить
second key с not_before/not_after; подтверждение receiver readiness; overlap не меньше
max in-flight request timeout+clock skew; затем revoke previous. Не считать любой 2xx
доказательством new-key readiness. Компрометация разрешает немедленный revoke, потеря
zero-downtime явно audit. Нет live rotation во время тестов; G-SECRET у wiring item
утверждает activation policy, реальные secrets/remote receiver требуют отдельной
конкретной authority. Unit rotation использует только синтетические keys.

## 10. SSRF и HTTP transport (D82)

Asset boundary: tenant-admin controls URL, remote receiver controls DNS/status/headers/body;
нельзя доверять даже admin URL. Entry points create/update, each attempt, replay.
Production policy: только https, порт 443, ASCII canonical DNS name (IDN только заранее
punycode), без userinfo/query/fragment и без IP literal. Reject CRLF/NUL, ambiguous
numeric hosts и excessive length. Endpoints с credentials в query не поддерживаются.

Resolver получает **все** A/AAAA конечные записи с bounded CNAME chain; любые private,
loopback, link-local, multicast, unspecified, reserved, IPv4-mapped IPv6, metadata,
transition ranges => отказ всего набора. Allow только global-unicast адреса по
committed deny-ranges policy, не один FILTER_FLAG_NO_PRIV_RANGE. CIDR minimum deny set IPv4: 0/8,10/8,100.64/10,127/8,169.254/16,172.16/12,
192.0.0/24,192.0.2/24,192.168/16,198.18/15,198.51.100/24,203.0.113/24,224/4,240/4;
IPv6: reject outside 2000::/3 and reject 2001::/23,2001:db8::/32,2002::/16,
3fff::/20 inside it; organization-owned internal ranges additionally denied.
CIDR сравнение через inet_pton + prefix mask, не строковый startsWith; special-use
исключения не разрешаются автоматически. Обновление deny set — reviewed policy change.
Пустой/timeout DNS —
retryable unavailable, policy violation — terminal. Revalidate every attempt.

`Contracts\WebhookHttpTransport::send(ValidatedWebhookRequest $request): WebhookHttpResult`.
Request содержит canonical host/url, ровно один vetted address из validated set,
body bytes, три signature headers, timeout; не arbitrary headers/proxy options.
Production `CurlWebhookTransport` использует ext-curl, если он есть у host; без него
capability_unavailable (никакой новой Composer dependency). CURLOPT_RESOLVE pins
host:443:address при сохранении TLS hostname/SNI; TLS verify peer/host обязательны.
Redirects disabled, protocol HTTPS only, proxy disabled, per-attempt fresh handle,
connect timeout 3sec, total 10sec; request <=20KiB, response body discarded with 64KiB
hard cap, headers <=16KiB. Никогда второй unvalidated DNS lookup/fallback IP.
Peer address сравнивается с pinned адресом; всё равно главным барьером служит pin **до** send.

No generic localhost exception в production. Тесты адресной policy — pure injected DNS;
local receiver integration использует отдельный test-only transport/resolver, explicit
fixture TLS и pinned 127.0.0.1. Production bindings нельзя переключить тестовым env flag.
Ни одного внешнего send во время qualification; network source retrieval дизайна не
является webhook send. Integration доказывает wire bytes, не заявляет live egress audit.

## 11. Worker, retry, dead-letter (D83)

SQL claim — короткая transaction, row lease/state/attempt increment; attempt row
pending до сети; HTTP после commit. Tenant scope/connection, active subscription,
revision, topic availability и key readiness проверяются снова непосредственно перед send.
Finish predicates id+status=sending+lease_owner+attempt; stale worker не меняет outcome.
Lease=30sec > total HTTP timeout 10sec + margin; monotonic timeout и Clock lease отдельно.
Crash after receiver success before ACK даёт повтор: одна delivery id/body, свежая подпись.
Lease fencing защищает БД, не способен отозвать уже отправленный HTTP request.

Response policy (локальная надстройка Standard Webhooks): 2xx success; 410 disables
subscription и dead остальных pending; 3xx terminal redirect_forbidden; 408/425/429,
5xx, network timeout/reset retry; остальные 4xx dead. Retry-After delta-seconds или
HTTP-date учитывается после bounded parsing; past=0, maximum 24h. Next delay =
max(valid Retry-After, full-jitter[0,min(3600,5*2^(attempt-1))]) с минимумом 1sec.
Random source injectable для теста, secure default random_int. Maximum 12 attempts
или 72h от created_at, что раньше; если следующий attempt выходит за horizon — dead.
Это defaults CoreX, не обещание vendor. Retry не обходит SSRF/authorization.

Attempt history: sequence/time/duration/status/safe error code only. Не хранить raw
response, Authorization, signature, secret, raw payload в log, URL query или stack trace.
Unknown send при crash остаётся unknown attempt; новый claim помечает его unknown,
не success. P12 events+PSR-3 дают correlation, metric labels только allow-list.
Single-account worker command принимает trusted active context; multi-tenant scheduler
не строится (D48). One loop supports graceful stop/bounded max deliveries.

## 12. Subscription/admin API, replay и retention (D84)

Authenticated literal routes `/webhook-subscriptions` create/list/update/disable,
`/webhook-deliveries` list/detail/attempts, POST `/{id}/replay` и POST `/replay` range.
Все mutations через shared invoker с declared scopes webhooks.manage/webhooks.replay
и idempotency=required, пятислойной авторизацией. Listing cursor<=100 по (created_at,id)
из P13, scoped tenant/client/filter; attempts не возвращают body или key_refs.
No endpoint accepts arbitrary secret bytes; provisioning host-owned SecretRef only.

Replay **той же delivery** сохраняет id/body/destination; CAS только dead/succeeded→pending,
active lease → conflict. succeeded replay explicit=true и audit reason обязателен;
receiver может игнорировать известный id — это ожидаемо. Нужен новый business effect →
новое domain event, не replay flag. attempts monotonically increase, replay_count<=3;
каждый replay получает новый bounded retry window, absolute replay horizon=7d.
Attempt limit 12 считается внутри cycle; total sequence не обнуляется.

Range replay: required subscription + from/to UTC (<=24h) + cursor + max=100;
server фиксирует upper bound при admission, возвращает selected/requeued/skipped/conflicts
и continuation, без unbounded all-history operation. Parent idempotency replay возвращает
тот же selection/outcome, не выбирает новые deliveries. Disabled/changed endpoint,
revoked permission, expired body или removed topic отказ, не auto-enable.

Webhook body retained 7d, metadata/attempts 30d; dead не теряет историю до TTL;
active deliveries не prune, PII policy может уменьшать body TTL и replay horizon вместе.
Retained dedup tombstone unique(subscription,event_id) живёт пока событие может повторно
приходить из P5. P5 inbox default indefinite (D29): webhook consumer inbox не чистить
раньше delivery tombstones; raw outbox replay старых event ids не создаёт новых sends.
Prune batch<=100, tenant-scoped, Clock-based, expired claims не удаляет; sealed bulk
result delete проверяет ref ownership/hash, shared budget prune не трогает held rows.

## 13. Wiring, snapshots, release evidence

Рядом с ApiOptions вводятся отдельные CostOptions/BulkOptions/WebhookOptions, не untyped bag;
только provider/config registrar читает Laravel config. Provider вызывает именованные
Cost/Bulk/Webhook registrar'ы и остаётся под P8 size ratchet. Optional adapters
объявляются suggest, не require; нет auto-provider discovery и auto-DDL. Один
schema registrar публикует две группы migrations из §3. Включение remote delivery
по умолчанию false; flag требует production URL policy, key resolver и worker readiness.
G-SECRET approval привязан к этому policy, не blanket permission внешних sends.

P14.18 расширяет существующие `Internal/ContractSnapshotBuilder`,
`Commands/WriteApiSnapshots` и `scripts/check-api-contract.php`, не создаёт второй generator: `api/v1/webhooks.json` (topic/version/major/schema/
PII, payload limit, at-least-once/no-ordering, retry/replay/rotation wire contract),
manifest добавляет его hash. operations/openapi/errors отражают новые endpoints,
cost metadata, bulk states и safe codes. Compatibility gate краснеет на topic removal,
required payload input addition/response field removal/type/nullability change,
PII widening, signature/id semantics change, major rebind; cost default enforcement
change также несовместим без explicit version/policy migration. Planted negative
fixtures обязательны; additive optional topics/fields проходят.

P14.21 publishes normative policies/table-ownership references, README и root changelog.
Receipt содержит schema/profile hashes, tenant/shared-connection proof, refund/crash
matrix, import resume/export restart matrix, cancellation latency in batches,
local wire vectors, SSRF negative matrix, replay/retention semantics и skips.
Проверки: root lint/analyse/test + targeted modules/api/transfer suites; isolated PG
на **все SQL paths**; architecture/BC/package-graph/manifest/provider ratchets.
Никакой skipped lane не считается green. Security-review для signing/SSRF/rotation
обязателен при исполнении, но не создаёт owner-consent на phase audit автоматически.

## 14. Acceptance / threat matrix

| Риск/сценарий | Обязательное наблюдаемое доказательство |
|:--|:--|
| Fake low cost / overflow | pure estimator clamps inputs by schema; planted overflow denied before handler |
| Hierarchy race / two API nodes | PG two connections share global cap; denied ancestor leaves all unchanged |
| Double refund / stale settlement | terminal CAS gives one refund; expired/unknown gives none |
| HTTP/tool bypass | one invoker/budget, idempotent replay charges admission only |
| Duplicate bulk submit / lost queue | unique submission -> same id; polling runs durable pending row |
| Crash/cancel/revocation | import resumes committed checkpoint; cancel <=one batch; no next effects after denial |
| Export crash | unsealed attempt never resumes against new snapshot; sealed bytes/hash verified |
| Cross-tenant result/URL/ref | 404 + no storage access; no arbitrary URL/path resolution |
| Outbox duplicate / fan-out crash | inbox+deliveries atomic; one row per subscription/event |
| Tampered wire / stale timestamp | known HMAC vector, any-byte change denied, +/-300s verifier |
| Rotation readiness | old/new each valid in overlap, revoked rejected; readiness explicit |
| SSRF redirect/rebinding/IPv6 | reject private/mixed sets, pinned socket, redirects never followed |
| ACK loss / stale worker | retry same delivery id, receiver inbox one effect; late ACK fenced |
| Replay / retention | same bytes/id, finite range/cycles; body expiry forbids replay |
| PII/secret leakage | captured log/API/attempts exclude secret/signature/raw body; sensitive policy negative |
| Compatibility regression | each new planted schema/semantics violation causes gate exit 1 |

RAG sources подтверждают patterns, не CoreX defaults или PHP имена. См.
`P14-design-rag.md`; unresolved external source не подменяется уверенным утверждением.

## 15. Safe error additions и concrete port shapes

Все ошибки проходят P12 ErrorDescriptor/P13 mapper. Existing `rate_limited` сохраняется;
новые public codes: `bulk_not_ready` conflict/retryable; `bulk_result_expired` not_found/
nonretryable (HTTP410); `bulk_reconciliation_required` conflict/nonretryable;
`webhook_endpoint_changed` conflict/nonretryable; `webhook_payload_too_large`
validation/nonretryable; `webhook_replay_expired` conflict/nonretryable.
SSRF/DNS/HTTP/key error details внутренние; наружу безопасный reason без URL/IP/secret.
В snapshot добавляются только перечисленные stable codes, не внутренние transport errors.

`WebhookSigner::sign(string $deliveryId, int $timestamp, string $rawBody,
array $secretRefs): WebhookSignedHeaders` возвращает readonly id/timestamp/signatures,
secretRefs list length1..2; production signer сам разрешает refs через P7 resolver.
`WebhookHttpResult` readonly: status:?int, retryAfter:?string, errorCode:?string,
durationMs:int; transport exception превращается в safe errorCode без raw exception.
`WebhookDeliveryStore::claim(DataScope $scope, string $worker): ?WebhookDelivery`,
`finish(WebhookDelivery $delivery, WebhookHttpResult $result): bool`,
`replay(DataScope $scope, string $id, int $expectedRevision): bool` — атомарные
operations §11–12; failed CAS возвращает false и никогда не делает второй send.
Delivery DTO несёт поля §3 плюс текущий lease token; replay CAS использует отдельный
revision bigint (добавить в delivery DDL с default0, повышать на transition).
Subscription DTO несёт columns §3 без resolved secret bytes; Store create/update/find/
disable используют DataScope, DTO и expectedRevision, возврат DTO|null для find,
DTO для mutations. Mutation conflict бросает безопасный descriptor conflict.

Gate delta: S11 attribute заменён compiled recipe (D74); S38 resume ограничен
импортом (D78); Q4 закрыт D75. Existing predecessor contracts не редактируются.
No migration, test suite, network delivery or live secret rotation выполнены дизайном.
