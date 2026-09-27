# P5 — measured Messaging zone membership (D31)

Измеренная принадлежность файлов логической зоны `Messaging` на момент P5.11. Каталог
`PubSub/` физически остаётся в `CoreX\PubSub\**` до P6 (G-API); перенос — P6.

Гейт: `tests/Arch/MessagingZoneTest.php` (R-M1 allow-list, R-M2 `Internal` surface).

## `packages/core/src/Messaging/**`

### `packages/core/src/Messaging/Commands/PruneOutboxMessages.php`

- `CoreX\Support\Config`
- `DateInterval`
- `Illuminate\Console\Command`
- `Illuminate\Support\Facades\DB`
- `Psr\Clock\ClockInterface`

### `packages/core/src/Messaging/Commands/RelayOutbox.php`

- `CoreX\Exceptions\OutboxLeaseLost`
- `CoreX\Messaging\Contracts\DurableMessagePublisher`
- `CoreX\Messaging\Contracts\OutboxRelay`
- `CoreX\Support\Config`
- `Illuminate\Console\Command`
- `Illuminate\Support\Str`
- `Throwable`

### `packages/core/src/Messaging/Commands/ShowDeliveryHealth.php`

- `CoreX\Messaging\Internal\SqlDeliveryHealth`
- `Illuminate\Console\Command`

### `packages/core/src/Messaging/Contracts/DurableMessagePublisher.php`

(no `use` imports)

### `packages/core/src/Messaging/Contracts/MessageContractRegistry.php`

- `CoreX\Exceptions\UnknownMessageContract`
- `CoreX\Messaging\Data\MessageContract`

### `packages/core/src/Messaging/Contracts/MessagePublisher.php`

- `CoreX\Messaging\Data\MessageEnvelope`

### `packages/core/src/Messaging/Contracts/Outbox.php`

- `CoreX\Messaging\Data\MessageEnvelope`

### `packages/core/src/Messaging/Contracts/OutboxRelay.php`

- `CoreX\Messaging\Data\LeasedMessage`
- `Throwable`

### `packages/core/src/Messaging/Data/DeliveryHealth.php`

(no `use` imports)

### `packages/core/src/Messaging/Data/LeasedMessage.php`

- `DateTimeImmutable`

### `packages/core/src/Messaging/Data/MessageContract.php`

- `CoreX\Enums\MessageClass`
- `CoreX\Enums\PiiClass`
- `CoreX\Exceptions\InvalidMessage`

### `packages/core/src/Messaging/Data/MessageEnvelope.php`

- `CoreX\Contracts\DurablePayload`
- `CoreX\Enums\PiiClass`
- `CoreX\Exceptions\InvalidMessage`
- `DateTimeImmutable`

### `packages/core/src/Messaging/Data/MessagePayload.php`

- `CoreX\Exceptions\InvalidMessage`

### `packages/core/src/Messaging/Internal/ArrayMessageContractRegistry.php`

- `CoreX\Exceptions\UnknownMessageContract`
- `CoreX\Messaging\Contracts\MessageContractRegistry`
- `CoreX\Messaging\Data\MessageContract`
- `Override`

### `packages/core/src/Messaging/Internal/SqlDeliveryHealth.php`

- `CoreX\Messaging\Data\DeliveryHealth`
- `CoreX\Support\Config`
- `DateTimeImmutable`
- `DateTimeInterface`
- `Illuminate\Database\ConnectionInterface`
- `Psr\Clock\ClockInterface`

### `packages/core/src/Messaging/Internal/SqlOutbox.php`

- `CoreX\Exceptions\OutboxRefused`
- `CoreX\Messaging\Contracts\MessageContractRegistry`
- `CoreX\Messaging\Contracts\Outbox`
- `CoreX\Messaging\Data\MessageEnvelope`
- `CoreX\Support\Config`
- `Illuminate\Database\ConnectionInterface`
- `Override`
- `Psr\Clock\ClockInterface`

### `packages/core/src/Messaging/Internal/SqlOutboxRelay.php`

- `CoreX\Enums\PiiClass`
- `CoreX\Exceptions\OutboxLeaseLost`
- `CoreX\Messaging\Contracts\OutboxRelay`
- `CoreX\Messaging\Data\LeasedMessage`
- `CoreX\Messaging\Data\MessageEnvelope`
- `CoreX\Messaging\Data\MessagePayload`
- `CoreX\Support\Config`
- `DateInterval`
- `DateTimeImmutable`
- `DateTimeInterface`
- `Illuminate\Database\Connection`
- `Override`
- `Psr\Clock\ClockInterface`
- `Throwable`

## `packages/core/src/PubSub/**` (логическая зона — перенос P6)

| File | Note |
|:--|:--|
| `packages/core/src/PubSub/DatabasePollPubSub.php` | перенос — P6 |
| `packages/core/src/PubSub/DefaultWorkerRuntime.php` | перенос — P6 |
| `packages/core/src/PubSub/NullPubSub.php` | перенос — P6 |
| `packages/core/src/PubSub/PrunePubSubMessages.php` | перенос — P6 |
| `packages/core/src/PubSub/PubSubManager.php` | перенос — P6 |
| `packages/core/src/PubSub/RedisPubSub.php` | перенос — P6 |
| `packages/core/src/PubSub/SubscribeGuard.php` | перенос — P6 |
