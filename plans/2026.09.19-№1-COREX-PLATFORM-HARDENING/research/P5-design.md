# P5 — дизайн фазы: messaging-основа (envelope, реестр контрактов, outbox, inbox)

Нормативный носитель фазы. Исполнитель items — модель класса implementation
(Composer 2.5), поэтому каждый параграф либо даёт готовый код, либо даёт готовый
нормативный текст. Домысливать нечего: если параграф чего-то не сказал, это
предмет receipt'а и эскалации, а не импровизации.

Внешние подтверждения — `P5-design-rag.md` (семь запросов, `artifacts/P5-design-rag/`).
`RAG:✅` — подтверждена общая практика; `RAG:—` — факт о дереве.

---

## 0. Инварианты, обязательные для всех items фазы

1. **Конвенции репозитория.** `declare(strict_types=1)`; `final` кроме абстрактных
   точек расширения; constructor property promotion; явные типы; `#[Override]`;
   контракты — только интерфейсы; именованные аргументы при нескольких однотипных
   параметрах. Pint preset `laravel`, PHPStan уровня корневого `phpstan.neon`.
2. **Раскладка зоны.** Новый код фазы живёт в `packages/core/src/Messaging/` и
   повторяет раскладку, уже существующую в дереве у `packages/wakeup/src`:
   `Contracts/` — интерфейсы, `Data/` — неизменяемые значения, `Internal/` —
   реализации, `Commands/` — консольные команды. `RAG:—` внутри самого `core` такая
   раскладка тоже есть: `core/src/Tenancy/Contracts/`.
3. **Ни один существующий файл `packages/core/src/PubSub/**` в этой фазе не
   переносится и не переименовывается.** Зона объявляется логически и защищается
   arch-гейтом (§11, D31); физический перенос неймспейсов ядра — P6 под owner-gate
   G-API.
4. **Время.** Везде, где время влияет на durable-решение (наступление срока, lease,
   backoff, retention, `occurred_at`), используется инъектированный
   `Psr\Clock\ClockInterface` из P4.9. Глобальный `now()` в коде фазы запрещён.
   Признанный предел D23 (серверное время PostgreSQL надёжнее часов хоста) наследуется
   без изменения — см. D30 и §5.9.
5. **Скоуп.** Любой durable-носитель фазы несёт скаляры скоупа — результат
   `DataScopeCodec::encode()` из P4.8, а не объект контекста. Восстановление контекста
   у потребителя — `TenantContextRestorer::restore()` из P4.2 (D22: сверяет, но не
   инициализирует).
6. **Существующие прецеденты переиспользуются, а не изобретаются заново.** Канонизация
   payload'а — форма `SqlWakeupScheduler::canonical()`; захват по lease со
   `SKIP LOCKED` и ветка по драйверу — форма `SweepRunner::claimDue()`; fence-предикат
   на обновлении — форма `DatabaseTransferRunner` (`claim_epoch` + `checkpoint_seq`);
   prune-команда — форма `PrunePubSubMessages`. Вторая реализация того же механизма в
   одном репозитории — дефект дизайна, а не выбор стиля.
7. **Оба лейна.** Закрытие кодового item'а — `composer lint:check && composer analyse &&
   composer test`. Для всякого item'а, трогающего SQL-пути, дополнительно
   `composer test:pg` пакета `core`. Только sqlite-лейн = ложно-зелёное (D34 плана):
   ни `for update skip locked`, ни `on conflict do nothing` на SQLite не доказываются.
8. **Никаких новых runtime-зависимостей.** Вся фаза строится на том, что уже есть:
   `illuminate/database`, `psr/clock` (объявлен в P4.9), существующий контракт
   `CoreX\Contracts\PubSub`.

---

## 1. Состав фазы и обязательный порядок

| Item | Что производит | Параграф |
|:--|:--|:--|
| P5.1 | `docs/architecture/message-policy.md` до исполнимого вида | §10 |
| P5.2 | `MessageEnvelope`, `MessagePayload`, `MessageContract`, два enum'а, исключение | §2 |
| P5.3 | Реестр объявленных контрактов и отказ на необъявленном | §3 |
| P5.4 | DDL `sys_outbox_messages` и `sys_inbox_messages`, блок конфигурации, владение таблицами | §4 |
| P5.5 | `Outbox::append()` внутри бизнес-транзакции | §5.1–§5.3 |
| P5.6 | `OutboxRelay`: lease, fence-отметка, backoff, dead-letter | §5.4–§5.10 |
| P5.7 | Порт публикации и команда relay с кооперативной остановкой | §6 |
| P5.8 | `Inbox::claim()` | §7 |
| P5.9 | Retention outbox; inbox по умолчанию не чистится | §8 |
| P5.10 | Наблюдаемость доставки: четыре числа из таблиц плюс названный пятый счётчик | §9 |
| P5.11 | Зона `Messaging`: границы и arch-гейт | §11 |
| P5.12 | Доказательства гарантий: падение, конкуренция, откат, дубль | §12 |

**Порядок по данным жёсткий:**

```text
P5.1 → P5.2 → P5.3 → P5.5 → P5.6 → P5.7 → {P5.9, P5.10} → P5.11 → P5.12
P5.4 → {P5.5, P5.8}
P5.8 → P5.12
```

`P5.4` (DDL) независим от `P5.3` и может идти сразу после `P5.2`; он же — единственный
носитель owner-gate `G-DDL` фазы.

---

## 2. P5.2 — конверт, payload и словарь контракта

### 2.1 Состав конверта и почему он такой

`RAG:—` Обязательный состав уже зафиксирован статьёй `MSG-003` и таблицей
`message-policy.md` (P1.6): `id`, `source`, `type`, `envelope-version`, `occurred-at`,
`scope`; необязательные — `subject`, `correlation-id`, `causation-id`, класс
персональных данных. P5 не переписывает этот перечень, а даёт ему типы и правила.

`RAG:✅` P5-Q3: CloudEvents 1.0 требует ровно четырёх атрибутов — `id`, `source`,
`specversion`, `type` — и пара `source` + `id` уникально идентифицирует событие, что и
является основанием дедупликации на стороне потребителя.

**Соответствие CloudEvents не заявляется.** Таблица ниже — справочная, для читателя,
который знает спецификацию; имена полей CoreX следуют политике P1, а не CloudEvents.
Заявлять соответствие, не реализовав сериализацию по спецификации и не проверив её
тестом, — ровно тот класс обещания, который потребитель принимает за контракт.

| Поле CoreX | Аналог CloudEvents | Отличие |
|:--|:--|:--|
| `messageId` | `id` | нет |
| `source` | `source` | CoreX не требует URI-reference; требуется непустой непрозрачный идентификатор |
| `name` + `contractVersion` | `type` | у CoreX хранится разложенной парой, публикуется как одна строка `name.vN` (D24) |
| `envelopeVersion` | `specversion` | версия формата **конверта CoreX**, не версия спецификации CloudEvents |
| `occurredAt` | `time` | у CoreX обязателен |
| `subject` | `subject` | нет |
| `scope` | — | нет аналога: это скалярный `DataScope` (RUN-002) |
| `piiClass` | — | нет аналога: расширение CoreX |
| `correlationId`, `causationId` | extension-атрибуты | у CloudEvents не ядровые (`RAG:✅` P5-Q3) |

### 2.2 Две версии, которые нельзя путать

`RAG:✅` P5-Q3: `specversion` — версия конверта, а не схемы содержимого; стандартного
поля «версия схемы события» нет вовсе, это политика продюсера.

В CoreX это две независимые величины:

- `envelopeVersion` — версия **формата конверта**. Меняется, когда меняется набор полей
  самого конверта. Сегодня `1`.
- `contractVersion` — мажорная версия **контракта содержимого**. Меняется, когда
  меняется схема payload'а ломающим образом.

`message-policy.md` (P1) уже требует их различать. P5 делает это машинным: это два
разных свойства и две разные колонки, а не одно число, которое читатель трактует по
контексту.

### 2.3 Идентичность контракта — одна каноническая строка (D24)

`RAG:✅` P5-Q3: `type` — единственная граница совместимости; совместимое изменение
сохраняет `type`, ломающее — меняет; отдельный атрибут версии это частное расширение,
которое не должно противоречить `type`. Одновременно источник разрешает продюсеру
вкладывать мажорную версию прямо в `type`.

Эскиз `01-shapes-runtime.md` §2 предлагал `public string $name` и `public int $version`
как два равноправных **публичных** поля. Эскиз **принят с изменением** (D24):

- пара хранится разложенной — это деталь хранения: по `name` строится индекс, а
  чтение отказывает на неизвестной версии, не разбирая строку;
- **опубликованная идентичность контракта — одна строка** `"{$name}.v{$version}"`,
  которую возвращает `MessageEnvelope::contract()`. Именно она объявляется в реестре,
  попадает в канал публикации, в маршрутизацию потребителя и в `message-policy.md`.

Имя без версии наружу не публикуется никогда: потребитель, подписавшийся на
`membership.expired` без версии, обязательно однажды получит `v2`.

### 2.4 Имя контракта не равно имени PHP-класса

`RAG:✅` P5-Q3: имя класса описывает артефакт реализации; оно меняется при реорганизации
неймспейсов, переносе пакета контрактов, смене сериализатора и генерации кода — и
публикация имени класса превращает внутренний рефакторинг во внешне ломающее изменение.

Правило (`MSG-004`) становится машинным в §3.6: `MessageContract::__construct()`
отвергает имя, содержащее `\`, и имя, начинающееся с `CoreX`. Это дешёвая проверка,
закрывающая ровно тот способ ошибиться, который встречается на практике —
`static::class` в качестве имени события.

Форма имени: сегменты `[a-z][a-z0-9]*` через точку, от двух до четырёх сегментов,
например `membership.expired`, `search.document.indexed`.

### 2.5 correlation и causation

`RAG:✅` P5-Q3: correlation идентифицирует весь бизнес-процесс (многие события делят
один ID, включая параллельные ветви); causation указывает на **непосредственного**
предшественника. При простой пересылке конверт сохраняется целиком; при семантическом
преобразовании создаётся новое событие с новым `id` и записанной причинностью.

`causationId` в CoreX хранит `messageId` непосредственного предшественника, а не пару
`(source, id)`. Причина названа явно: `messageId` в CoreX — UUIDv7, генерируемый одним
процессом на одну запись, и глобально уникален сам по себе; хранить пару значило бы
завести второе поле ради инварианта, который уже выполнен. Это **названный предел**: он
перестанет выполняться в день, когда в один outbox начнут писать два независимых
продюсера с собственными генераторами ID. Условие возврата записывается в receipt.

### 2.6 Payload: канонизация, лимит и класс персональных данных

`RAG:—` В дереве уже есть ровно та канонизация, которая здесь нужна:
`SqlWakeupScheduler::canonical()` (`packages/wakeup/src/Internal/SqlWakeupScheduler.php:112–174`)
требует ограниченную карту объектов, рекурсивно сортирует ключи, ограничивает глубину
восемью уровнями, число ключей — 256, а сериализованный размер — 64 KiB.

**Лимит размера берётся из дерева, а не извне** (`RAG:—`). Внешней нормы на размер
payload'а нет; Standard Webhooks (Q12 верхнего слоя) называет «обычно <20 KB» для
webhook'ов, но это про HTTP-доставку, а не про строку в таблице. Значение 64 KiB выбрано
потому, что в репозитории уже есть это правило и вторая цифра означала бы два разных
ответа на один вопрос. Условие пересмотра — первый контракт, которому 64 KiB мало;
тогда меняются оба правила сразу, а не одно.

`RAG:✅` P5-Q6: непрозрачный идентификатор остаётся персональными данными
(псевдонимизация ≠ анонимизация); дефолт — идентификаторы плюс обращение к системе
записи, а не снимок PII в событии. Crypto-shredding защитим только при надёжном
уничтожении всех пригодных ключей, включая резервные копии; tombstone — событие
плоскости управления, а не само удаление.

P5 поэтому делает **только объявление**: `PiiClass` — обязательное поле конверта, и
реестр контрактов объявляет максимально допустимый класс для каждого контракта
(§3.6). Шифрование полей, crypto-shredding и tombstone-события в фазу **не входят** и
названы в Scope Excluded: обещать стирание, не имея жизненного цикла ключей и инвентаря
потоков данных, — хуже, чем не обещать.

**Почему payload — объект, а не типизированный массив.** `MessageEnvelope` помечается
маркером `DurablePayload` (P3.5, ужесточён в P4.7). Ужесточённое правило отвергает
свойство типа `array` с типом значения `mixed`. Payload события — по своей природе
рекурсивная ограниченная карта, и выразить «рекурсивно ограниченная карта скаляров»
типом PHPStan нельзя. Поэтому инвариант переносится туда, где он выразим: в конструктор
`MessagePayload`, который проверяет его фактически. Это строже, чем статический тип, а
не слабее — и предел правила P3.5 на конверте просто не задействуется.

### 2.7 Готовый код

`packages/core/src/Enums/PiiClass.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Класс персональных данных в payload'е сообщения (MSG-007).
 *
 * Порядок значений — возрастание чувствительности: контракт объявляет максимально
 * допустимый класс, и сообщение с более высоким классом в него не принимается.
 */
enum PiiClass: string
{
    /** Данных, относящихся к человеку, нет вовсе. */
    case None = 'none';

    /** Только идентификаторы: связать с человеком можно лишь отдельно хранимыми данными. */
    case Pseudonymous = 'pseudonymous';

    /** Прямо идентифицирующие значения: имя, адрес, контакт, документ. */
    case Personal = 'personal';

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Pseudonymous => 1,
            self::Personal => 2,
        };
    }

    public function permits(self $other): bool
    {
        return $other->rank() <= $this->rank();
    }
}
```

`packages/core/src/Enums/MessageClass.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Три класса сообщений (MSG-001). Класс — объявление, а не тип PHP: событие
 * фреймворка не становится интеграционным контрактом от того, что на него
 * подписались (MSG-002).
 */
enum MessageClass: string
{
    /** Свершившийся факт внутри одного контекста; наружу не публикуется. */
    case DomainEvent = 'domain-event';

    /** Опубликованный факт и контракт совместимости. */
    case IntegrationEvent = 'integration-event';

    /** Намерение изменить состояние; ровно один владелец-обработчик. */
    case Command = 'command';
}
```

`packages/core/src/Exceptions/InvalidMessage.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use InvalidArgumentException;

final class InvalidMessage extends InvalidArgumentException
{
    public static function identity(string $name): self
    {
        return new self("Invalid message {$name}.");
    }

    public static function contractName(string $value): self
    {
        return new self("Message contract name [{$value}] must be a dotted lowercase name, not a PHP class name.");
    }

    public static function payloadShape(string $reason): self
    {
        return new self("Message payload {$reason}.");
    }
}
```

`packages/core/src/Messaging/Data/MessagePayload.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Exceptions\InvalidMessage;

/**
 * Канонизованный payload сообщения: ограниченная карта объектов из null, bool, int,
 * string и вложенных таких же карт и списков.
 *
 * Форма и пределы повторяют существующий прецедент дерева
 * SqlWakeupScheduler::canonical() — восемь уровней вложенности, 256 ключей на уровень,
 * 64 KiB в сериализованном виде, рекурсивная сортировка ключей. Второе правило на тот
 * же вопрос в одном репозитории было бы дефектом, а не гибкостью.
 *
 * Инвариант проверяется конструктором, а не типом: «рекурсивно ограниченная карта
 * скаляров» статическим типом PHP/PHPStan не выражается, поэтому MessageEnvelope
 * хранит этот объект, а не array<string, mixed>.
 */
final readonly class MessagePayload
{
    public const int MAX_DEPTH = 8;

    public const int MAX_KEYS = 256;

    public const int MAX_BYTES = 65_536;

    /** @var array<string, mixed> */
    public array $values;

    /** @param array<array-key, mixed> $values */
    public function __construct(array $values)
    {
        $this->values = self::canonical($values);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    public function encode(): string
    {
        return json_encode($this->values, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private static function canonical(array $values): array
    {
        if ($values !== [] && array_is_list($values)) {
            throw InvalidMessage::payloadShape('must be a bounded object map, not a list');
        }

        if (count($values) > self::MAX_KEYS) {
            throw InvalidMessage::payloadShape('contains too many values');
        }

        $canonical = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw InvalidMessage::payloadShape('contains an unsupported key');
            }

            $canonical[$key] = self::canonicalValue(value: $value, depth: 1);
        }

        ksort($canonical);

        if (strlen(json_encode($canonical, JSON_THROW_ON_ERROR)) > self::MAX_BYTES) {
            throw InvalidMessage::payloadShape('exceeds 64KiB');
        }

        return $canonical;
    }

    private static function canonicalValue(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidMessage::payloadShape('nesting exceeds eight levels');
        }

        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw InvalidMessage::payloadShape('contains an unsupported value');
        }

        if (count($value) > self::MAX_KEYS) {
            throw InvalidMessage::payloadShape('contains too many values');
        }

        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::canonicalValue(value: $item, depth: $depth + 1),
                $value,
            );
        }

        $canonical = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || $key === '') {
                throw InvalidMessage::payloadShape('contains an unsupported key');
            }

            $canonical[$key] = self::canonicalValue(value: $item, depth: $depth + 1);
        }

        ksort($canonical);

        return $canonical;
    }
}
```

`packages/core/src/Messaging/Data/MessageContract.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Enums\MessageClass;
use CoreX\Enums\PiiClass;
use CoreX\Exceptions\InvalidMessage;

/**
 * Объявление опубликованного контракта сообщения.
 *
 * Существование этой записи — и есть повышение факта до публичного контракта
 * (MSG-002). Ни рефлексия, ни подписка, ни имя класса такого повышения не дают.
 */
final readonly class MessageContract
{
    public function __construct(
        public string $name,
        public int $version,
        public MessageClass $class,
        public string $owner,
        public PiiClass $maxPiiClass,
        public string $channel,
    ) {
        self::assertContractName($name);

        if ($version < 1) {
            throw InvalidMessage::identity("contract version [{$version}]");
        }

        if ($class === MessageClass::DomainEvent) {
            throw InvalidMessage::identity(
                "contract [{$name}]: a domain event is not a published contract (MSG-002)",
            );
        }

        if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $owner) !== 1) {
            throw InvalidMessage::identity("contract owner [{$owner}]");
        }

        if ($channel === '' || strlen($channel) > 120 || preg_match('/[\x00-\x1f\x7f]/', $channel) === 1) {
            throw InvalidMessage::identity("contract channel [{$channel}]");
        }
    }

    /** Опубликованная идентичность контракта: единственная строка, видимая потребителю. */
    public function id(): string
    {
        return "{$this->name}.v{$this->version}";
    }

    private static function assertContractName(string $value): void
    {
        if (str_contains($value, '\\') || str_starts_with($value, 'CoreX')) {
            throw InvalidMessage::contractName($value);
        }

        if (preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*){1,3}\z/D', $value) !== 1) {
            throw InvalidMessage::contractName($value);
        }
    }
}
```

`packages/core/src/Messaging/Data/MessageEnvelope.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Contracts\DurablePayload;
use CoreX\Enums\PiiClass;
use CoreX\Exceptions\InvalidMessage;
use DateTimeImmutable;

/**
 * Конверт опубликованного сообщения (MSG-003).
 *
 * `envelopeVersion` — версия формата ЭТОГО конверта; `contractVersion` — мажорная
 * версия схемы содержимого. Их смешение — типовая и дорогая ошибка, поэтому это два
 * независимых свойства (см. research/P5-design.md §2.2).
 *
 * Наружу публикуется одна идентичность контракта — contract(), то есть `name.vN`:
 * подписка на имя без версии однажды получит следующую мажорную версию (D24).
 */
final readonly class MessageEnvelope implements DurablePayload
{
    public const int ENVELOPE_VERSION = 1;

    /**
     * @param  array<string, scalar|null>  $scope  результат DataScopeCodec::encode()
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $source,
        public string $name,
        public int $contractVersion,
        public DateTimeImmutable $occurredAt,
        public array $scope,
        public MessagePayload $payload,
        public PiiClass $piiClass,
        public ?string $subject = null,
        public ?string $correlationId = null,
        public ?string $causationId = null,
        public array $metadata = [],
        public int $envelopeVersion = self::ENVELOPE_VERSION,
    ) {
        self::assertOpaque(value: $messageId, name: 'message identity');
        self::assertOpaque(value: $source, name: 'message source');

        if ($contractVersion < 1) {
            throw InvalidMessage::identity("contract version [{$contractVersion}]");
        }

        if ($envelopeVersion !== self::ENVELOPE_VERSION) {
            throw InvalidMessage::identity("envelope version [{$envelopeVersion}]");
        }

        if ($scope === []) {
            throw InvalidMessage::identity('scope: a published message carries a data scope (RUN-002)');
        }

        if ($subject !== null) {
            self::assertOpaque(value: $subject, name: 'message subject');
        }

        if ($correlationId !== null) {
            self::assertOpaque(value: $correlationId, name: 'correlation identity');
        }

        if ($causationId !== null) {
            self::assertOpaque(value: $causationId, name: 'causation identity');
        }
    }

    /** Опубликованная идентичность контракта: `membership.expired.v1`. */
    public function contract(): string
    {
        return "{$this->name}.v{$this->contractVersion}";
    }

    private static function assertOpaque(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw InvalidMessage::identity($name);
        }
    }
}
```

Проверка формы имени в `MessageEnvelope` намеренно **не** дублируется: имя проверяет
`MessageContract`, а конверт без объявленного контракта в outbox не попадает (§3.4).
Дублирование одной проверки в двух местах даёт два разных сообщения об ошибке на одну
причину.

### 2.8 Тесты P5.2

`packages/core/tests/Unit/MessagingEnvelopeTest.php`:

1. Конверт со всеми полями строится; `contract()` возвращает `membership.expired.v1`.
2. Пустой `messageId`, `source` длиной 129, управляющий символ в `subject` — три
   различимых отказа.
3. `envelopeVersion: 2` → отказ (чужая версия конверта — задача декодера, не конструктора).
4. `scope: []` → отказ с упоминанием `RUN-002`.
5. `MessagePayload` с вложенностью девять уровней → отказ `nesting exceeds eight levels`.
6. `MessagePayload` со списком верхнего уровня → отказ `must be a bounded object map`.
7. `MessagePayload` с 257 ключами → отказ `contains too many values`.
8. `MessagePayload` с объектом (`new stdClass`) в значении → отказ `unsupported value`.
9. `MessagePayload` на 70 KiB → отказ `exceeds 64KiB`.
10. Канонизация: `(new MessagePayload(['b' => 1, 'a' => ['d' => 1, 'c' => 2]]))->encode()`
    равно `{"a":{"c":2,"d":1},"b":1}` — рекурсивная сортировка, а не только верхний уровень.
11. `MessageContract` с именем `CoreX\Events\Foo` → отказ `must be a dotted lowercase name`.
12. `MessageContract` с `MessageClass::DomainEvent` → отказ с упоминанием `MSG-002`.
13. `PiiClass::Pseudonymous->permits(PiiClass::Personal)` = `false`;
    `PiiClass::Personal->permits(PiiClass::None)` = `true`.

---

## 3. P5.3 — реестр объявленных контрактов

### 3.1 Почему перевод на границе, а не подписка на доменное событие

`RAG:✅` P5-Q4: внутреннее доменное событие — плохой опубликованный контракт. Оно
раскрывает внутренние понятия и жизненный цикл (рефакторинг одного действия в два
события становится инцидентом совместимости); его payload несёт ORM-формы, value
objects и локальные ID; у него другая временнáя семантика (может подниматься внутри
транзакции, тогда как интеграционное обязано описывать закоммиченный исход); и один
доменный факт не обязательно релевантен снаружи. Published Language — перевод на
границе в терминах, понятных без импорта внутренностей публикатора.

`RAG:—` В дереве есть `CoreX\Events\DomainEvent` — абстрактная база с `eventId`,
`occurredAt`, `tenant` и `name()`. Она остаётся ровно тем, чем является: базой
**доменных** событий внутри процесса. P5 её не трогает и не «повышает»: превращение
`DomainEvent::name()` в имя публичного контракта было бы автоматическим повышением,
запрещённым `MSG-002`.

### 3.2 Реестр как машинное правило `MSG-002`

`RAG:✅` P5-Q4: повышение обязано быть объявленной записью реестра, а не автоматикой;
автоповышение делает «внутреннее событие» молчаливым синонимом публичного API. Реестр
даёт намеренность, обозримость, избирательное раскрытие, семантическое преобразование,
версионирование и тестируемость. Одно внутреннее изменение может дать ноль, одно или
синтезированное из нескольких доменных событий публичное событие.

`RAG:—` Статья `MSG-002` конституции (P1) уже несёт запрет, но её `Enforcement` —
«нет — P5». Реестр и есть это принуждение: запись в реестре — единственный способ
сделать факт публикуемым, а `Outbox::append()` отказывает на паре, которой в реестре
нет (§5.2).

### 3.3 Версии и правила совместимости

`RAG:✅` P5-Q3: `type` — граница совместимости. Совместимое изменение сохраняет его,
ломающее — меняет; во время миграции публикуются обе версии. Перечень ломающих
изменений: удаление или переименование поля, смена типа, превращение необязательного в
обязательное, смена единиц/семантики/точности/nullability, изменение перечисления так,
что старые потребители его отвергают, переиспользование поля под другой смысл.
Аддитивное добавление поля безопасно только если контракт потребителя его терпит.

В CoreX это означает: ломающее изменение payload'а — **новая запись реестра** с
`version: N+1`, живущая рядом со старой в течение объявленного окна. Реестр допускает
обе; `Outbox` принимает обе; потребитель мигрирует осознанно. Удаление записи из
реестра — ломающее изменение публичной поверхности и подчиняется `api-policy.md` P1.5.

Апкастеры версий P5 **не** вводит: сериализованных сообщений ни одной версии в дереве
нет (`RAG:—` outbox отсутствует полностью, N05), и писать преобразование формата,
которого нет ни в одной строке БД, — код без входных данных. Это то же решение и та же
причина, что в P4.8 §6.3, и оно фиксируется в receipt'е P5.3, а не подразумевается.

### 3.4 Принуждение — рантаймовое, CI-гейт не заявляется

`RAG:—` Хвост запроса P5-Q4 про машинное принуждение каталога в CI выпал при сокращении
формулировки после таймаута и повторно не задавался (`manifest.json` → `unresolved`).
Поэтому форма принуждения здесь — локальное решение, а не внешне подтверждённая практика.

Принуждение рантаймовое и точечное: `Outbox::append()` — единственная дверь в durable-
поверхность, и она отказывает на необъявленной паре. Статический гейт «ни одно
неописанное событие не пересекло границу» **не заявляется**: чтобы он не был
ложно-зелёным, он обязан уметь отличать вызов `append()` с вычисляемым именем от вызова
с литералом, а это анализ потока данных, которого в плановом сканере (P2.1, stdlib,
D16) нет. Названный предел записывается в receipt P5.3.

### 3.5 Форма payload'а интеграционного события

`RAG:✅` P5-Q4: дефолт — ориентированный на потребителя снимок **контрактного**
состояния, а не сериализованный агрегат и не одинокий ID. «Только ID» создаёт
рантайм-связность (потребитель не может обработать факт при недоступном продюсере) и
ломает смысл повтора: воспроизведение старого события с выборкой текущего состояния не
говорит, что было истинным в момент события. Дельта требует у потребителя корректной
базы, порядка, идемпотентности и восстановления.

CoreX выбирает снимок контрактного состояния как рекомендацию политики (§10), а не как
машинное правило: отличить снимок от «только ID» автоматически нельзя, и правило,
которое нечем проверить, в гейт не превращается. Правило живёт в `message-policy.md` и
в ревью объявления контракта.

### 3.6 Готовый код

`packages/core/src/Messaging/Contracts/MessageContractRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Exceptions\UnknownMessageContract;
use CoreX\Messaging\Data\MessageContract;

/**
 * Реестр объявленных контрактов сообщений (MSG-002).
 *
 * Запись в этом реестре — единственный способ сделать факт публикуемым. Ни подписка,
 * ни базовый класс события, ни рефлексия публикации не дают.
 */
interface MessageContractRegistry
{
    public function has(string $name, int $version): bool;

    /** @throws UnknownMessageContract */
    public function get(string $name, int $version): MessageContract;

    /** @return list<MessageContract> отсортирован по id() */
    public function all(): array;
}
```

`packages/core/src/Exceptions/UnknownMessageContract.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

final class UnknownMessageContract extends RuntimeException
{
    public static function notDeclared(string $name, int $version): self
    {
        return new self(
            "Message contract [{$name}.v{$version}] is not declared: a message is published only by an "
            .'explicit registry declaration (MSG-002).',
        );
    }

    public static function duplicate(string $id): self
    {
        return new self("Message contract [{$id}] is declared more than once.");
    }
}
```

`packages/core/src/Messaging/Internal/ArrayMessageContractRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Exceptions\UnknownMessageContract;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use Override;

/**
 * Неизменяемый реестр контрактов, собираемый в composition root.
 *
 * Дубликат идентичности — ошибка построения, а не молчаливое переопределение: это тот
 * же инвариант, что EXT-006 требует от любого реестра. Полная модель реестров
 * (collecting → compiled → frozen, отпечаток) вводится в P11; здесь достаточно
 * неизменяемости после сборки.
 */
final readonly class ArrayMessageContractRegistry implements MessageContractRegistry
{
    /** @var array<string, MessageContract> */
    private array $contracts;

    /** @param list<MessageContract> $contracts */
    public function __construct(array $contracts)
    {
        $indexed = [];

        foreach ($contracts as $contract) {
            if (isset($indexed[$contract->id()])) {
                throw UnknownMessageContract::duplicate($contract->id());
            }

            $indexed[$contract->id()] = $contract;
        }

        ksort($indexed);

        $this->contracts = $indexed;
    }

    #[Override]
    public function has(string $name, int $version): bool
    {
        return isset($this->contracts["{$name}.v{$version}"]);
    }

    #[Override]
    public function get(string $name, int $version): MessageContract
    {
        return $this->contracts["{$name}.v{$version}"]
            ?? throw UnknownMessageContract::notDeclared(name: $name, version: $version);
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->contracts);
    }
}
```

Привязка в `CoreServiceProvider::register()`:

```php
$this->app->singleton(
    MessageContractRegistry::class,
    static fn (): ArrayMessageContractRegistry => new ArrayMessageContractRegistry(contracts: []),
);
```

Ядро **не объявляет ни одного контракта**: у `corex/core` своих интеграционных событий
нет, а объявлять чужие за владельца — ровно то размывание владения, против которого
написана `data-ownership-policy.md`. Пакет-владелец переопределяет привязку в своём
провайдере; процедура — в §10. Пустой реестр по умолчанию означает, что попытка
опубликовать что-либо в чистой установке отказывает с внятным сообщением, а не молча
проходит.

### 3.7 Тесты P5.3

`packages/core/tests/Unit/MessageContractRegistryTest.php`:

1. `has()`/`get()` находят объявленный контракт по имени и версии.
2. `get()` на необъявленном → `UnknownMessageContract::notDeclared`, и текст содержит
   `MSG-002`.
3. Две записи с одной парой `(name, version)` → `duplicate` при построении, а не при
   чтении.
4. `v1` и `v2` одного имени сосуществуют и возвращаются раздельно.
5. `all()` отсортирован по `id()` детерминированно.
6. Реестр по умолчанию (из контейнера) пуст.

---

## 4. P5.4 — DDL, конфигурация, владение таблицами

### 4.1 `afterCommit()` не является реализацией `MSG-008`

`RAG:✅` P5-Q5: `dispatch()->afterCommit()` и `after_commit => true` гарантируют только
порядок видимости — не отправлять до коммита и отбросить при откате. Это **не** атомарная
координация с Redis/SQS. Остаётся окно: коммит изменения прошёл, процесс умер, деплой
его убил или публикация провалилась — и долговременной локальной записи, из которой
можно сверить и повторить, нет. Обратная неоднозначность тоже есть: при таймауте брокер
мог задачу принять, тогда как продюсер считает отправку неудавшейся.

Это правило попадает в политику (§10) дословно, потому что «у нас же есть
`afterCommit()`» — самый вероятный способ не построить outbox вовсе.

### 4.2 Где живёт таблица (D27)

`RAG:✅` P5-Q7: при физической базе на тенанта outbox живёт в тенантной базе. Центральный
outbox возвращает окно двойной записи — PostgreSQL не сделает два обычных соединения
одной локальной ACID-транзакцией, и падение между коммитами даёт либо навсегда
пропавшее событие, либо событие о несуществующем состоянии.

`RAG:—` Существующая конвенция дерева совпадает: `CoreServiceProvider::boot()` прямо
объявляет весь набор `sys_*` как **tenant-schema DDL** и публикует его тегом
`corex-migrations-tenant`. Обе новые таблицы получают префикс `sys_` и ложатся в
`packages/core/database/migrations/pg/` — тот же каталог, тот же тег публикации, то же
правило «под P2 консьюмер выполняет `migrate --database=<tenant>`».

### 4.3 DDL — готовый код

`packages/core/database/migrations/pg/2026_09_20_100000_create_sys_outbox_messages_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->unsignedSmallInteger('contract_version');
            $table->unsignedSmallInteger('envelope_version');
            $table->string('source', 128);
            $table->string('subject', 128)->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->string('causation_id', 128)->nullable();
            $table->string('pii_class', 16);
            $table->json('scope');
            $table->json('payload');
            $table->json('metadata');
            $table->timestampTz('occurred_at');
            $table->timestampTz('available_at');
            $table->string('status', 16);
            $table->uuid('lease_owner')->nullable();
            $table->timestampTz('lease_until')->nullable();
            $table->unsignedInteger('attempts');
            $table->string('failure_class', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('dead_at')->nullable();
            $table->timestampsTz();
        });

        // Горячий путь захвата: только строки, которые relay может взять.
        Schema::getConnection()->statement(
            'CREATE INDEX sys_outbox_messages_claim_idx ON sys_outbox_messages '
            ."(available_at, occurred_at, id) WHERE status = 'pending'",
        );

        // Перезахват просроченной аренды.
        Schema::getConnection()->statement(
            'CREATE INDEX sys_outbox_messages_reclaim_idx ON sys_outbox_messages '
            ."(lease_until, id) WHERE status = 'publishing'",
        );

        // Retention опубликованных и мёртвых — два разных окна, два разных индекса.
        Schema::getConnection()->statement(
            'CREATE INDEX sys_outbox_messages_published_idx ON sys_outbox_messages '
            ."(published_at, id) WHERE status = 'published'",
        );
        Schema::getConnection()->statement(
            'CREATE INDEX sys_outbox_messages_dead_idx ON sys_outbox_messages '
            ."(dead_at, id) WHERE status = 'dead'",
        );
    }
};
```

`packages/core/database/migrations/pg/2026_09_20_100001_create_sys_inbox_messages_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_inbox_messages', function (Blueprint $table): void {
            $table->string('consumer', 120);
            $table->uuid('message_id');
            $table->string('name', 160);
            $table->timestampTz('processed_at');
            $table->primary(['consumer', 'message_id']);
            $table->index(['processed_at'], 'sys_inbox_messages_processed_idx');
        });
    }
};
```

Пять решений DDL, которые легко потерять при переписывании:

1. **Частичные индексы создаются сырым SQL, а не `Blueprint`.** `RAG:—` Laravel Schema
   Builder не выражает `WHERE` у индекса. `RAG:✅` P5-Q1: индекс горячего пути обязан
   покрывать только захватываемые строки, иначе он растёт вместе с удержанной историей.
   Этот файл и так живёт в `migrations/pg/` и PG-only по объявлению провайдера.
2. **`payload` в ключевой индекс не кладётся** (`RAG:✅` P5-Q1): крупный, повышает write
   amplification, ухудшает кеш-дружелюбность горячего индекса.
3. **Статус — строка с четырьмя значениями** (`pending`, `publishing`, `published`,
   `dead`), как у `sys_wakeup_requests`. Enum PostgreSQL не вводится: в дереве его нет
   ни разу, а миграция значений enum дороже, чем проверка в коде.
4. **`ordering_key`/`sequence_no` из ответа P5-Q1 НЕ вводятся.** Они нужны только для
   per-key порядка, которого фаза не обещает (D28, §5.9). Колонка, которую никто не
   заполняет осмысленно, — обещание, выданное схемой, но не кодом.
5. **`down()` не пишется.** Ни одна существующая миграция ядра его не имеет; откат
   tenant-schema DDL — операционная процедура, а не метод класса.

### 4.4 Блок конфигурации — готовый код

Весь блок объявляется **одним item'ом** (P5.4). Остальные items эту конфигурацию только
читают и файлов `config/corex.php` и `Support/Config.php` не трогают: один факт — один
владелец.

В `packages/core/config/corex.php`, в секцию `tables`:

```php
        'outbox_messages' => 'sys_outbox_messages',
        'inbox_messages' => 'sys_inbox_messages',
```

и новая верхнеуровневая секция:

```php
    /*
    |--------------------------------------------------------------------------
    | Messaging (MSG-001 … MSG-008)
    |--------------------------------------------------------------------------
    | Transactional outbox и inbox идемпотентного потребителя.
    |
    | `lease_seconds` обязан превышать максимальную ожидаемую длительность
    | публикации: истёкшая аренда означает перезахват, то есть дубль. `batch_size`
    | ограничивает один ход relay — это же ограничение не даёт одному аккаунту
    | занять воркер целиком.
    |
    | `max_attempts` и `backoff_cap_seconds` задают ограниченный экспоненциальный
    | backoff min(cap, 2 ** attempt) секунд — та же формула, что у
    | packages/wakeup (SweepRunner). Jitter не добавляется: детерминизм важнее,
    | пока на один аккаунт приходится один relay (research/P5-design.md §5.8).
    |
    | `outbox_retention` — окно хранения ОПУБЛИКОВАННЫХ строк. Мёртвые строки
    | хранятся отдельным, более длинным окном `dead_retention`: они нужны
    | расследованию, а не доставке.
    |
    | `inbox_retention` по умолчанию null — inbox НЕ чистится. Удаление отметки
    | заново открывает окно дублей, поэтому включать чистку можно только там, где
    | объявлен конечный горизонт повтора (research/P5-design.md §7.3, D29).
    */
    'messaging' => [
        'lease_seconds' => 120,
        'batch_size' => 100,
        'max_attempts' => 8,
        'backoff_cap_seconds' => 300,
        'outbox_retention' => '14 days',
        'dead_retention' => '90 days',
        'inbox_retention' => null,
    ],
```

В `packages/core/src/Support/Config.php` — девять аксессоров по образцу существующих
`pubsub*`:

```php
    public static function outboxMessagesTable(): string
    {
        return (string) config('corex.tables.outbox_messages', 'sys_outbox_messages');
    }

    public static function inboxMessagesTable(): string
    {
        return (string) config('corex.tables.inbox_messages', 'sys_inbox_messages');
    }

    public static function messagingLeaseSeconds(): int
    {
        return (int) config('corex.messaging.lease_seconds', 120);
    }

    public static function messagingBatchSize(): int
    {
        return (int) config('corex.messaging.batch_size', 100);
    }

    public static function messagingMaxAttempts(): int
    {
        return (int) config('corex.messaging.max_attempts', 8);
    }

    public static function messagingBackoffCapSeconds(): int
    {
        return (int) config('corex.messaging.backoff_cap_seconds', 300);
    }

    public static function messagingOutboxRetention(): string
    {
        return (string) config('corex.messaging.outbox_retention', '14 days');
    }

    public static function messagingDeadRetention(): string
    {
        return (string) config('corex.messaging.dead_retention', '90 days');
    }

    public static function messagingInboxRetention(): ?string
    {
        $value = config('corex.messaging.inbox_retention');

        return is_string($value) && $value !== '' ? $value : null;
    }
```

`RAG:—` Статический аксессор — существующая форма ядра (31 ключ), а не выбор этой фазы.
Её сужение до boot-адаптера и типизированные Options — работа P7; вводить здесь второй
способ читать конфигурацию значило бы дать P7 на один паттерн больше.

### 4.5 Записи владения таблицами

В `docs/architecture/table-ownership.md` (реестр создаётся P2.3) добавляются две строки
по форме реестра: владелец `corex/core`, зона `Messaging`, схема — тенантная,
классификация объекта — «внутренний носитель доставки», не доменная сущность. Явно
указывается, что обе таблицы **не** входят в `DB_SCHEMA.md` `FROZEN v1.0`: это новые
таблицы вне контракта, введённые под owner-gate `G-DDL`, а не изменение существующих.

### 4.6 Тест схемы (P5.4)

`packages/core/tests/Pg/MessagingSchemaTest.php`, группа `pg`, по образцу
`packages/core/tests/Views/SavedViewPgSchemaTest.php`:

1. Обе таблицы существуют; перечень колонок совпадает с DDL поимённо.
2. `sys_inbox_messages` имеет составной первичный ключ `(consumer, message_id)` —
   читается из `information_schema.table_constraints` + `key_column_usage`.
3. Четыре частичных индекса `sys_outbox_messages` существуют и **имеют предикат**:
   `pg_indexes.indexdef` каждого содержит `WHERE`. Проверка именно предиката
   обязательна: индекс без `WHERE` создастся молча и будет выглядеть рабочим.
4. Вставка двух строк с одной парой `(consumer, message_id)` → нарушение ограничения.

---

## 5. P5.5–P5.6 — outbox: запись и relay

### 5.1 Три правила транзакционности

`RAG:✅` P5-Q1 и P5-Q7:

1. **Вставка в outbox — в той же транзакции, что бизнес-изменение.** Это и есть инвариант
   паттерна: атомарно сохраняются состояние и долговременное намерение опубликовать в той
   единственной системе, которая участвует в транзакции.
2. **Публикация — вне транзакции.** Короткая транзакция берёт lease и **коммитит его**,
   затем выполняется I/O, затем отдельным запросом ставится `published_at`. Сетевой вызов
   внутри транзакции ставит её длительность в зависимость от брокера.
3. **Окно дублей неустранимо.** Ответ — стабильный `messageId` и идемпотентный
   потребитель, а не попытка exactly-once.

### 5.2 Контракт записи и почему он требует открытой транзакции

`packages/core/src/Messaging/Contracts/Outbox.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\MessageEnvelope;

/**
 * Запись намерения опубликовать сообщение.
 *
 * Вызывается ВНУТРИ уже открытой транзакции бизнес-операции: атомарность бизнес-
 * изменения и записи outbox — единственная причина, по которой этот механизм
 * существует. dispatch()->afterCommit() его не заменяет (MSG-008,
 * research/P5-design.md §4.1).
 *
 * Порядок доставки НЕ гарантируется (D28, research/P5-design.md §5.9).
 */
interface Outbox
{
    public function append(MessageEnvelope $message): void;
}
```

`packages/core/src/Messaging/Internal/SqlOutbox.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Exceptions\OutboxRefused;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Support\Config;
use Illuminate\Database\ConnectionInterface;
use Override;
use Psr\Clock\ClockInterface;

final readonly class SqlOutbox implements Outbox
{
    public function __construct(
        private ConnectionInterface $connection,
        private MessageContractRegistry $contracts,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function append(MessageEnvelope $message): void
    {
        if ($this->connection->transactionLevel() < 1) {
            throw OutboxRefused::outsideTransaction($message->contract());
        }

        $contract = $this->contracts->get(name: $message->name, version: $message->contractVersion);

        if (! $contract->maxPiiClass->permits($message->piiClass)) {
            throw OutboxRefused::piiClassExceeded(
                contract: $contract->id(),
                declared: $contract->maxPiiClass->value,
                actual: $message->piiClass->value,
            );
        }

        $now = $this->clock->now();

        $this->connection->table(Config::outboxMessagesTable())->insert([
            'id' => $message->messageId,
            'name' => $message->name,
            'contract_version' => $message->contractVersion,
            'envelope_version' => $message->envelopeVersion,
            'source' => $message->source,
            'subject' => $message->subject,
            'correlation_id' => $message->correlationId,
            'causation_id' => $message->causationId,
            'pii_class' => $message->piiClass->value,
            'scope' => json_encode($message->scope, JSON_THROW_ON_ERROR),
            'payload' => $message->payload->encode(),
            'metadata' => json_encode($message->metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => $message->occurredAt,
            'available_at' => $now,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
```

`packages/core/src/Exceptions/OutboxRefused.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

final class OutboxRefused extends RuntimeException
{
    public static function outsideTransaction(string $contract): self
    {
        return new self(
            "Outbox append for [{$contract}] requires an open transaction: the business change and the "
            .'outbox row must commit together (MSG-008).',
        );
    }

    public static function piiClassExceeded(string $contract, string $declared, string $actual): self
    {
        return new self(
            "Message [{$contract}] declares a maximum personal-data class of [{$declared}] but carries "
            ."[{$actual}] (MSG-007).",
        );
    }
}
```

**Почему отказ на отсутствующей транзакции, а не предупреждение.** `RAG:—` Прецедент
чтения `transactionLevel()` в дереве есть: `SqlWakeupScheduler::notifyHints()`. Проверка
дешёвая, а нарушение — ровно тот дефект, ради которого строится фаза: запись без
бизнес-изменения в одной транзакции превращает outbox в обычную очередь с лишней
таблицей. Цена названа честно: продюсер, которому нужно записать сообщение без иного
изменения состояния, обязан открыть транзакцию явно. Это одна строка, и она делает
инвариант видимым в коде вызывающего.

### 5.3 Тесты P5.5

`packages/core/tests/Feature/OutboxAppendTest.php` (sqlite-лейн):

1. `append()` вне транзакции → `OutboxRefused::outsideTransaction`, текст содержит `MSG-008`.
2. `append()` с необъявленным контрактом внутри транзакции → `UnknownMessageContract`.
3. `append()` с `PiiClass::Personal` при контракте `maxPiiClass: Pseudonymous` →
   `piiClassExceeded`, текст содержит `MSG-007`.
4. Успешный `append()`: строка `pending`, `attempts = 0`, `available_at` равен часам,
   `payload` в БД равен `MessagePayload::encode()` байт-в-байт.
5. Время берётся из `FrozenClock`: `created_at`, `updated_at` и `available_at` равны —
   одна точка времени на операцию.

`packages/core/tests/Pg/OutboxAppendPgTest.php` (группа `pg`):

6. **Тест отката.** Внутри `DB::transaction()` выполняется `append()`, затем бросается
   исключение; после отката в таблице ноль строк. Это единственная проверка, доказывающая
   правило 1 §5.1, и она обязана жить в pg-лейне.

### 5.4 Relay: две транзакции и fence-предикат

`RAG:✅` P5-Q1: захват — короткая транзакция, которая коммитит lease; отметка успеха —
отдельный запрос с предикатом `id = ? AND status = 'publishing' AND lease_owner = ?`,
не дающим опоздавшему прежнему владельцу затереть результат нового.

`RAG:—` Ровно этот fence-предикат в дереве уже есть: `DatabaseTransferRunner` обновляет
`corex_transfer_jobs` с условиями `checkpoint_seq = ? AND claim_epoch = ?` и бросает
`TransferUnavailable('Transfer checkpoint lease was superseded.')` при нулевом числе
затронутых строк. Форма переиспользуется, а не изобретается.

`packages/core/src/Messaging/Data/LeasedMessage.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use DateTimeImmutable;

final readonly class LeasedMessage
{
    public function __construct(
        public MessageEnvelope $envelope,
        public string $leaseOwner,
        public int $attempt,
        public DateTimeImmutable $leaseUntil,
    ) {}
}
```

`packages/core/src/Messaging/Contracts/OutboxRelay.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\LeasedMessage;
use Throwable;

/**
 * Операторская сторона outbox: захват аренды, отметка результата.
 *
 * lease() — короткая транзакция без единого сетевого вызова внутри. Публикация
 * выполняется ПОСЛЕ её коммита; markPublished() и releaseWithBackoff() выполняются
 * отдельными запросами с fence-предикатом по владельцу аренды.
 */
interface OutboxRelay
{
    /** @return list<LeasedMessage> */
    public function lease(string $leaseOwner, int $batchSize): array;

    public function markPublished(LeasedMessage $message): void;

    public function releaseWithBackoff(LeasedMessage $message, Throwable $error): void;
}
```

### 5.5 Готовый код relay

`packages/core/src/Messaging/Internal/SqlOutboxRelay.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Enums\PiiClass;
use CoreX\Exceptions\OutboxLeaseLost;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Data\LeasedMessage;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Support\Config;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Override;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class SqlOutboxRelay implements OutboxRelay
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private int $leaseSeconds,
        private int $maxAttempts,
        private int $backoffCapSeconds,
    ) {}

    /** @return list<LeasedMessage> */
    #[Override]
    public function lease(string $leaseOwner, int $batchSize): array
    {
        $now = $this->clock->now();
        $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));
        $table = Config::outboxMessagesTable();

        /** @var list<object> $rows */
        $rows = $this->connection->transaction(function () use ($table, $leaseOwner, $batchSize, $now, $leaseUntil): array {
            // D99: expired rows already consumed an attempt. Exhausted rows become
            // dead before selection; only the remainder returns to pending.
            $this->connection->table($table)
                ->where('status', 'publishing')
                ->where('lease_until', '<=', $now)
                ->where('attempts', '>=', $this->maxAttempts)
                ->update([
                    'status' => 'dead',
                    'dead_at' => $now,
                    'failure_class' => 'attempts-exhausted-after-lease-expiry',
                    'lease_owner' => null,
                    'lease_until' => null,
                    'updated_at' => $now,
                ]);

            $this->connection->table($table)
                ->where('status', 'publishing')
                ->where('lease_until', '<=', $now)
                ->where('attempts', '<', $this->maxAttempts)
                ->update([
                    'status' => 'pending',
                    'lease_owner' => null,
                    'lease_until' => null,
                    'updated_at' => $now,
                ]);

            $query = $this->connection->table($table)
                ->where('status', 'pending')
                ->where('available_at', '<=', $now)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->limit($batchSize);

            if ($this->connection->getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $claimed = $query->get()->all();

            foreach ($claimed as $row) {
                $this->connection->table($table)->where('id', $row->id)->update([
                    'status' => 'publishing',
                    'lease_owner' => $leaseOwner,
                    'lease_until' => $leaseUntil,
                    'attempts' => (int) $row->attempts + 1,
                    'updated_at' => $now,
                ]);
            }

            return array_values($claimed);
        });

        return array_map(
            fn (object $row): LeasedMessage => new LeasedMessage(
                envelope: $this->envelope($row),
                leaseOwner: $leaseOwner,
                attempt: (int) $row->attempts + 1,
                leaseUntil: $leaseUntil,
            ),
            $rows,
        );
    }

    #[Override]
    public function markPublished(LeasedMessage $message): void
    {
        $now = $this->clock->now();

        $changed = $this->connection->table(Config::outboxMessagesTable())
            ->where('id', $message->envelope->messageId)
            ->where('status', 'publishing')
            ->where('lease_owner', $message->leaseOwner)
            ->update([
                'status' => 'published',
                'published_at' => $now,
                'lease_owner' => null,
                'lease_until' => null,
                'failure_class' => null,
                'last_error' => null,
                'updated_at' => $now,
            ]);

        if ($changed !== 1) {
            throw OutboxLeaseLost::superseded($message->envelope->messageId);
        }
    }

    #[Override]
    public function releaseWithBackoff(LeasedMessage $message, Throwable $error): void
    {
        $now = $this->clock->now();
        $dead = $message->attempt >= $this->maxAttempts;
        $delay = min($this->backoffCapSeconds, 2 ** $message->attempt);

        $changed = $this->connection->table(Config::outboxMessagesTable())
            ->where('id', $message->envelope->messageId)
            ->where('status', 'publishing')
            ->where('lease_owner', $message->leaseOwner)
            ->update([
                'status' => $dead ? 'dead' : 'pending',
                'available_at' => $dead ? $now : $now->add(new DateInterval("PT{$delay}S")),
                'dead_at' => $dead ? $now : null,
                'lease_owner' => null,
                'lease_until' => null,
                'failure_class' => $dead ? 'attempts-exhausted' : 'publish-failed',
                'last_error' => mb_substr($error->getMessage(), 0, 2_000),
                'updated_at' => $now,
            ]);

        if ($changed !== 1) {
            throw OutboxLeaseLost::superseded($message->envelope->messageId);
        }
    }

    private function envelope(object $row): MessageEnvelope
    {
        /** @var array<string, scalar|null> $scope */
        $scope = json_decode((string) $row->scope, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, scalar|null> $metadata */
        $metadata = json_decode((string) $row->metadata, true, flags: JSON_THROW_ON_ERROR);

        return new MessageEnvelope(
            messageId: (string) $row->id,
            source: (string) $row->source,
            name: (string) $row->name,
            contractVersion: (int) $row->contract_version,
            occurredAt: $this->date($row->occurred_at),
            scope: $scope,
            payload: new MessagePayload($payload),
            piiClass: PiiClass::from((string) $row->pii_class),
            subject: $row->subject === null ? null : (string) $row->subject,
            correlationId: $row->correlation_id === null ? null : (string) $row->correlation_id,
            causationId: $row->causation_id === null ? null : (string) $row->causation_id,
            metadata: $metadata,
            envelopeVersion: (int) $row->envelope_version,
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable((string) $value);
    }
}
```

`packages/core/src/Exceptions/OutboxLeaseLost.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

final class OutboxLeaseLost extends RuntimeException
{
    public static function superseded(string $messageId): self
    {
        return new self(
            "Outbox lease for message [{$messageId}] was superseded: the row was reclaimed by another relay. "
            .'The publish may already have reached its destination; do not finalize it here.',
        );
    }
}
```

Четыре требования, которые легко потерять:

1. **`envelopeVersion` из строки передаётся в конструктор.** Конструктор отвергает
   чужую версию, и это правильно: relay старой версии, встретивший конверт новой,
   обязан упасть видимо, а не доставить неполный объект.
2. **`attempts` увеличивается при захвате, а не при отказе.** Иначе строка, брошенная
   умершим воркером, вечно возвращается с тем же номером попытки и никогда не станет
   мёртвой.
3. **Возврат просроченной аренды выполняется до выборки, в той же транзакции.** Это
   форма `SweepRunner::claimDue()`.
4. **`OutboxLeaseLost` — не «ошибка публикации».** Сообщение исключения обязано сказать
   ровно то, что сказал источник: публикация могла дойти, и завершать её здесь нельзя
   (`RAG:✅` P5-Q1).

### 5.6 Многотенантность: что фаза делает и чего не делает

`RAG:✅` P5-Q7: честный relay по многим базам — это планировщик с каталогом тенантов,
ограничением визита, round-robin, приоритетом по возрасту старейшего сообщения, квотой,
backoff по тенанту, центральным lease и ограниченной **глобальной** конкурентностью;
постоянно тёплый пул на каждую тенантную базу — ошибка, потому что PostgreSQL использует
процесс на соединение.

**P5 этой плоскости управления не строит.** `SqlOutboxRelay` работает с одним
соединением — тем, которое ему дал контейнер, то есть текущим тенантным. Команда relay
(§6.2) работает на текущем доверенном контексте — ровно как существующая
`corex:wakeup:sweep`, которая берёт `$contexts->current()`.

Причина названа прямо: каталог аккаунтов, их локаторы и статусы — это `corex/tenancy`
и фаза P9, а не ядро. Ядро, знающее список аккаунтов, нарушило бы и доктрину уровней, и
boxed-профиль (который ставится без `corex/tenancy` вовсе). Долг записывается в
`findings/P5-receipts.md` с точной формулировкой: «честное планирование relay по многим
аккаунтам (round-robin, приоритет по возрасту, backoff по аккаунту, ограниченная
глобальная конкурентность) — вход P9; до него оператор запускает relay по аккаунту сам,
и малый аккаунт может ждать за большим».

### 5.7 Восстановление контекста у потребителя

`RAG:✅` P5-Q7: потребитель обязан разрешать тенанта через доверенный реестр и **не
принимать** строку соединения, имя схемы или имя базы из события как вход маршрутизации;
заголовок сообщения — метаданные маршрутизации, а не доказательство права.

Это буквально D22 фазы P4: `TenantContextRestorer::restore()` сверяет скоуп с активным
контекстом и отказывает на расхождении, не открывая произвольную БД. P5 ничего к этому
не добавляет и **обязан не добавлять**: второй способ восстановить контекст — это второй
способ ошибиться. Правило попадает в политику (§10) как ссылка на `RUN-002`/`RUN-003`.

### 5.8 Backoff: почему без jitter

`RAG:✅` P5-Q1 рекомендует ограниченный экспоненциальный backoff **с jitter**.

`RAG:—` В дереве уже есть формула без jitter: `SweepRunner` (строка 156) использует
`min(300, 2 ** $attempt)`. P4.10 делает её детерминизм проверяемым `FrozenClock`.

P5 берёт ту же формулу **без jitter** и называет причину: jitter нужен против
одновременного пробуждения многих воркеров, а сегодня на один аккаунт приходится один
relay (§5.6), поэтому сходящегося стада нет, а детерминизм повторов проверяется тестом.
Это **названный предел**: условие возврата — первый деплой с несколькими relay-воркерами
на один аккаунт. Записывается в receipt P5.6 вместе с долгом §5.6, потому что это одно и
то же условие.

### 5.9 Порядок доставки не обещается (D28)

`RAG:✅` P5-Q1: `SKIP LOCKED` даёт параллелизм, а не порядок. `ORDER BY` задаёт порядок
кандидатов внутри одного оператора, но пропуск строки, заблокированной другой
транзакцией, разрешён. Удержание блокировки на время публикации надёжной внешней
гарантии тоже не даёт. Настоящий per-key порядок требует отдельной машинерии: одно
сообщение «в полёте» на ключ, keyed shard либо таблица голов ключей.

P5 этой машинерии не строит. Поэтому фаза **обязана сказать вслух**, что порядок не
гарантирован, в трёх местах: в докблоке контракта `Outbox`, в `message-policy.md` и в
приёмке фазы. Молчание здесь читается как обещание — а потребитель, построивший
проекцию на предположении о порядке, обнаружит ошибку только под нагрузкой.

Условие возврата: первый контракт, для которого переупорядочивание меняет бизнес-исход.
Тогда вводится `ordering_key` и одна из трёх названных форм — но как отдельная работа с
собственными pg-тестами на конкуренцию, а не как колонка «на будущее».

### 5.10 Часы: наследование предела D23 (D30)

Открытый риск, переданный P4: если P5 возьмёт для своих таблиц серверное время
PostgreSQL, а `wakeup` останется на часах приложения, в одной установке окажется два
источника времени — это хуже любого из вариантов по отдельности.

P5 берёт **те же часы приложения** (`Psr\Clock\ClockInterface`, дефолт `LaravelClock`),
что и `wakeup` после P4.10. Один часовой домен на установку. Признанный предел D23
наследуется дословно и не ослабляется: для lease и retention серверное время БД строго
надёжнее часов хоста, и перевод предикатов на `now()`/`clock_timestamp()` остаётся
отдельной работой. Условие возврата тоже общее с D23 — первый мультихостовый
воркер-деплой или первый зафиксированный ранний перехват lease; в этот момент переводятся
**обе** подсистемы сразу, `wakeup` и `messaging`, а не одна.

### 5.11 Тесты P5.6

`packages/core/tests/Pg/OutboxRelayTest.php` (группа `pg`, `FrozenClock`):

1. `lease()` переводит строку в `publishing`, ставит владельца, `lease_until = now + 120s`
   и `attempts = 1`.
2. `lease()` не берёт строку с `available_at` в будущем.
3. Строка в `publishing` с истёкшим `lease_until` и `attempts < maxAttempts`
   возвращается в `pending` и захватывается; при `attempts >= maxAttempts` становится
   `dead` без нового publish. Envelope reconstruction входит в fenced failure
   path: повторно corrupt payload так же исчерпывает attempts.
4. `markPublished()` с чужим `leaseOwner` → `OutboxLeaseLost::superseded`, и статус
   строки не изменился.
5. `releaseWithBackoff()` на попытках 1, 2, 3, 4 даёт `available_at` = `now + 2s`,
   `+4s`, `+8s`, `+16s`; на попытке 8 (`maxAttempts = 8`) — статус `dead`, `dead_at`
   заполнен, `failure_class = 'attempts-exhausted'`.
6. `last_error` обрезается до 2000 символов.
7. **Конкуренция.** Два независимых PDO-соединения (форма
   `packages/wakeup/tests/Pg/WakeupClaimTest.php`): первое держит захват в открытой
   транзакции, второе выполняет `SELECT … FOR UPDATE SKIP LOCKED` и **не получает ни
   одной строки** — то есть пропускает, а не ждёт. Отдельным утверждением проверяется,
   что тот же запрос без `SKIP LOCKED` блокируется по `lock_timeout`.

---

## 6. P5.7 — порт публикации и команда relay

### 6.1 Почему отдельный порт, а не вызов `PubSub` из relay

`RAG:—` Имеющиеся `PubSub` drivers не равны durable handoff: `null`
ничего не передаёт, Redis PUBLISH теряет offline consumer, а DB driver
предназначен для другого PubSub-контракта. D99 поэтому вводит узкий
host-provided `MessagePublisher`, где успешный return означает только
durable broker/store acceptance, переживающий отсутствие consumer. Core не
привязывает lossy PubSub к этому порту и не маркирует `published`, если
порт не связан или не дал durable acknowledgement. P10/P14 — потребители
конверта, не неявные замены этого acknowledgement.

`packages/core/src/Messaging/Contracts/MessagePublisher.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\MessageEnvelope;

/**
 * Назначение публикации арендованного сообщения.
 *
 * Выполняется ВНЕ транзакции БД. Успешный return подтверждает durable
 * handoff, переживающий offline consumer. Реализация идемпотентна по
 * messageId или терпит повтор; окно дублей остаётся at-least-once.
 */
interface MessagePublisher
{
    public function publish(MessageEnvelope $message): void;
}
```

No `PubSubMessagePublisher` is supplied or bound. The provider validates a host binding
before relay execution and reports a stable configuration error when it is absent. Tests
use a durable fixture whose accepted queue survives construction of a new consumer
instance; a `null`/Redis-PUBLISH fixture is explicitly rejected and cannot call
`markPublished()`.

### 6.2 Команда relay — готовый код

`RAG:✅` P5-Q5: форма кооперативной остановки — `pcntl_async_signals(true)`, обработчик
сигнала только выставляет флаг, проверка флага **между** сообщениями, выход по безопасной
границе; сигнал не доказывает, что публикация не дошла. Непрерываемое блокирующее
ожидание запрещено.

`packages/core/src/Messaging/Commands/RelayOutbox.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Commands;

use CoreX\Exceptions\OutboxLeaseLost;
use CoreX\Messaging\Contracts\MessagePublisher;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Публикует ожидающие сообщения outbox текущего доверенного соединения.
 *
 * Работает на ОДНОМ соединении — том, которое дал контейнер. Планирование по многим
 * аккаунтам (round-robin, приоритет по возрасту, backoff по аккаунту) — вход P9
 * (research/P5-design.md §5.6).
 *
 * `--once` выполняет один проход и выходит: это режим планировщика. Без него команда
 * работает циклом и завершается по SIGTERM/SIGINT после текущего сообщения.
 */
final class RelayOutbox extends Command
{
    protected $signature = 'corex:messaging:relay
        {--batch= : Maximum messages per pass (default: corex.messaging.batch_size)}
        {--once : Run a single pass and exit}
        {--sleep=1 : Seconds to wait between empty passes}';

    protected $description = 'Publish pending CoreX outbox messages on the current trusted connection.';

    private bool $stopping = false;

    public function handle(OutboxRelay $relay, MessagePublisher $publisher): int
    {
        /** @var string|null $batchOption */
        $batchOption = $this->option('batch');
        $batch = $batchOption === null ? Config::messagingBatchSize() : (int) $batchOption;

        if ($batch < 1 || $batch > 1_000) {
            $this->error('The --batch option must be between 1 and 1000.');

            return self::INVALID;
        }

        $this->listenForShutdown();
        $leaseOwner = (string) Str::uuid7();
        $published = $deferred = 0;

        do {
            $leased = $relay->lease(leaseOwner: $leaseOwner, batchSize: $batch);

            foreach ($leased as $message) {
                if ($this->stopping) {
                    break;
                }

                try {
                    $publisher->publish($message->envelope);
                    $relay->markPublished($message);
                    $published++;
                } catch (OutboxLeaseLost $lost) {
                    // Аренда перехвачена: завершать сообщение здесь небезопасно.
                    $this->warn($lost->getMessage());
                } catch (Throwable $error) {
                    $relay->releaseWithBackoff(message: $message, error: $error);
                    $deferred++;
                }
            }

            if (! $this->stopping && ! $this->option('once') && $leased === []) {
                sleep(max(1, (int) $this->option('sleep')));
            }
        } while (! $this->stopping && ! $this->option('once'));

        $this->info("Outbox relay: published {$published}; deferred {$deferred}.");

        return self::SUCCESS;
    }

    private function listenForShutdown(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function (): void {
                $this->stopping = true;
            });
        }
    }
}
```

Три требования, которые легко потерять:

1. **Арендованное, но не опубликованное сообщение при остановке НЕ освобождается явно.**
   Его подберёт следующий проход по истёкшей аренде. Явный «возврат при остановке» ввёл
   бы четвёртый путь смены статуса ради случая, который уже покрыт механикой lease.
2. **`OutboxLeaseLost` не считается отказом публикации** и не вызывает
   `releaseWithBackoff()`: у процесса больше нет права писать в эту строку.
3. **`sleep()` вызывается только между пустыми проходами** и только в цикле; при
   `--once` его нет вовсе. `pcntl_async_signals(true)` прерывает `sleep()`, поэтому
   `SIGTERM` не ждёт полной секунды.

### 6.3 Тесты P5.7

`packages/core/tests/Feature/MessagePublisherTest.php`:

1. `PubSubMessagePublisher::publish()` зовёт `PubSub::publish()` с каналом **из
   объявления контракта**, а не из конверта.
2. Опубликованный массив содержит ровно двенадцать ключей в объявленном порядке;
   `contract` равен `membership.expired.v1`.
3. Публикация конверта с необъявленным контрактом → `UnknownMessageContract`.
4. `occurredAt` сериализуется в RFC 3339 с долями секунды.

`packages/core/tests/Pg/OutboxRelayCommandTest.php` (группа `pg`):

5. `corex:messaging:relay --once` с двумя ожидающими сообщениями публикует оба и
   печатает `published 2`.
6. Публикатор, бросающий исключение на втором сообщении: первое `published`, второе
   `pending` с `available_at = now + 2s`, вывод содержит `deferred 1`.
7. Публикатор, бросающий `OutboxLeaseLost`: строка остаётся в `publishing`, счётчик
   `deferred` не увеличивается, вывод содержит предупреждение.

---

## 7. P5.8 — inbox идемпотентного потребителя

### 7.1 Единственная форма, которая действительно защищает

`RAG:✅` P5-Q2: отметка inbox и бизнес-эффект обязаны быть одной транзакцией. Разделение
даёт либо **потерянный эффект** (отметка первой: повтор видит её и отбрасывает работу,
которая никогда не выполнялась), либо **дублирующий эффект** (эффект первым: повтор не
видит отметки и применяет снова). Предварительный `SELECT` вместо уникального
ограничения — гонка time-of-check/time-of-use: при Read Committed оба воркера законно не
видят закоммиченной строки. Ключ — пара «имя потребителя + message id»: один message id
независимо потребляется несколькими логическими потребителями.

`packages/core/src/Messaging/Contracts/Inbox.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

/**
 * Дедупликация идемпотентного потребителя (MSG-006).
 *
 * claim() вызывается ВНУТРИ той же транзакции, что бизнес-изменение, и возвращает
 * false на дубликате. Разделение отметки и эффекта на две транзакции ломает
 * дедупликацию в обе стороны: либо эффект теряется, либо применяется дважды
 * (research/P5-design.md §7.1).
 *
 * Отметка НЕ удаляется по умолчанию: её удаление заново открывает окно дублей (D29).
 */
interface Inbox
{
    /** @return bool true — заявка получена, эффект применять; false — дубликат */
    public function claim(string $consumer, string $messageId, string $name): bool;
}
```

`packages/core/src/Messaging/Internal/SqlInbox.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Exceptions\InboxRefused;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Support\Config;
use Illuminate\Database\ConnectionInterface;
use Override;
use Psr\Clock\ClockInterface;

final readonly class SqlInbox implements Inbox
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function claim(string $consumer, string $messageId, string $name): bool
    {
        if ($this->connection->transactionLevel() < 1) {
            throw InboxRefused::outsideTransaction($consumer);
        }

        // insertOrIgnore компилируется в INSERT ... ON CONFLICT DO NOTHING на pgsql.
        // Предварительный SELECT здесь был бы гонкой, а не защитой (P5-Q2).
        $inserted = $this->connection->table(Config::inboxMessagesTable())->insertOrIgnore([
            'consumer' => $consumer,
            'message_id' => $messageId,
            'name' => $name,
            'processed_at' => $this->clock->now(),
        ]);

        return $inserted === 1;
    }
}
```

`packages/core/src/Exceptions/InboxRefused.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

final class InboxRefused extends RuntimeException
{
    public static function outsideTransaction(string $consumer): self
    {
        return new self(
            "Inbox claim for consumer [{$consumer}] requires an open transaction: the deduplication row and "
            .'the business effect must commit together (MSG-006).',
        );
    }
}
```

### 7.2 Поведение при дубле

`RAG:✅` P5-Q2: при конфликте — подтвердить доставку, не применяя бизнес-логику снова;
не подтверждать брокеру до коммита локальной транзакции; не переиздавать побочные эффекты
из-за повторной доставки.

Форма потребителя, которую политика (§10) объявляет обязательной:

```php
$this->connection->transaction(function () use ($envelope): void {
    if (! $this->inbox->claim(
        consumer: 'search.projection.v1',
        messageId: $envelope->messageId,
        name: $envelope->name,
    )) {
        return; // дубликат: доставка подтверждается, бизнес-эффект не повторяется
    }

    $this->apply($envelope);
});
```

### 7.3 Retention: почему по умолчанию не чистим (D29)

`RAG:✅` P5-Q2: retention inbox — горизонт корректности, а не уборка. Отметку хранят не
меньше максимального времени, в течение которого тот же `message_id` может появиться
снова: retention брокера, окна повторов, длительность повтора у продюсера, ручной replay
и backfill, восстановление после аварии, дрейф часов. Если правдоподобной конечной
верхней границы назвать нельзя — удалять единственное свидетельство дедупликации нельзя.

Поэтому `inbox_retention` по умолчанию `null`, и prune-команда (§8) inbox **не трогает**.
Это осознанно отличается от дефолта `sys_pubsub_messages` (30 дней), и отличие обязано
быть объяснено в политике, иначе оно выглядит непоследовательностью: у pubsub-сообщений
удаление теряет **недоставленное**, а у inbox-отметок — **защиту от повтора уже
доставленного**; вторая потеря тише и обнаруживается позже.

Ключ существует, чтобы установка с объявленным конечным горизонтом повтора могла его
включить осознанно. Его включение требует записи в `message-policy.md` конкретной
установки, а не правки конфигурации.

### 7.4 Тесты P5.8

`packages/core/tests/Feature/InboxClaimTest.php`:

1. `claim()` вне транзакции → `InboxRefused::outsideTransaction`, текст содержит `MSG-006`.
2. Первый `claim()` возвращает `true`, второй с той же парой — `false`.
3. Тот же `messageId` для другого `consumer` возвращает `true`: потребители независимы.
4. `processed_at` берётся из `FrozenClock`.

`packages/core/tests/Pg/InboxConcurrencyTest.php` (группа `pg`):

5. **Гонка.** Два независимых PDO-соединения (форма `WakeupClaimTest`) одновременно
   выполняют `INSERT … ON CONFLICT DO NOTHING` с одной парой; ровно одно получает
   `1` вставленную строку, второе — `0`. Ни одно не падает.

---

## 8. P5.9 — retention outbox

`RAG:✅` P5-Q1: retention наступает только после терминального исхода и после срока,
достаточного для аудита, повтора, отставания потребителя и расследования; удалять только
на основании «брокер подтвердил» нельзя; мёртвые записи хранят дольше или архивируют.

`packages/core/src/Messaging/Commands/PruneOutboxMessages.php` — форма повторяет
существующую `PrunePubSubMessages` (та же структура опций, тот же расчёт порога, тот же
формат вывода):

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Commands;

use CoreX\Support\Config;
use DateInterval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

/**
 * Удаляет терминальные строки sys_outbox_messages старше порога.
 *
 * Два окна, а не одно: опубликованные записи нужны сверке, мёртвые — расследованию,
 * и второе обычно длиннее. Строки в pending и publishing не удаляются никогда — это
 * недоставленная работа, а не история.
 *
 * sys_inbox_messages эта команда НЕ трогает: удаление отметки дедупликации заново
 * открывает окно дублей (research/P5-design.md §7.3, D29).
 */
final class PruneOutboxMessages extends Command
{
    protected $signature = 'corex:messaging:prune
        {--published-older-than= : DateInterval-parseable age for published rows (default: corex.messaging.outbox_retention)}
        {--dead-older-than= : DateInterval-parseable age for dead rows (default: corex.messaging.dead_retention)}
        {--database= : Connection to prune (default: the app default)}';

    protected $description = 'Delete terminal sys_outbox_messages rows older than their retention window.';

    public function handle(ClockInterface $clock): int
    {
        /** @var string|null $publishedOption */
        $publishedOption = $this->option('published-older-than');
        /** @var string|null $deadOption */
        $deadOption = $this->option('dead-older-than');
        /** @var string|null $connection */
        $connection = $this->option('database');

        $published = $publishedOption ?? Config::messagingOutboxRetention();
        $dead = $deadOption ?? Config::messagingDeadRetention();
        $now = $clock->now();
        $table = Config::outboxMessagesTable();

        $prunedPublished = DB::connection($connection)->table($table)
            ->where('status', 'published')
            ->where('published_at', '<', $now->sub(DateInterval::createFromDateString($published)))
            ->delete();

        $prunedDead = DB::connection($connection)->table($table)
            ->where('status', 'dead')
            ->where('dead_at', '<', $now->sub(DateInterval::createFromDateString($dead)))
            ->delete();

        $this->info(sprintf(
            'Outbox retention: pruned %d published (older than %s) and %d dead (older than %s).',
            $prunedPublished,
            $published,
            $prunedDead,
            $dead,
        ));

        return self::SUCCESS;
    }
}
```

Тесты `packages/core/tests/Feature/PruneOutboxTest.php`:

1. Опубликованная строка старше окна удаляется; моложе — остаётся.
2. Мёртвая строка удаляется по **своему** окну, а не по окну опубликованных.
3. Строки в `pending` и `publishing` не удаляются ни при каком пороге.
4. Ни одна строка `sys_inbox_messages` не удаляется: команда её не читает.
5. Порог считается от `FrozenClock`, а не от `new DateTimeImmutable` — иначе тест
   границы недетерминирован. Это отличие от существующей `PrunePubSubMessages`, которая
   создаёт `new DateTimeImmutable` напрямую; расхождение фиксируется в receipt как долг,
   принадлежащий владельцу pubsub (P6), а не этой фазе.

---

## 9. P5.10 — наблюдаемость доставки

### 9.1 Четыре числа из таблиц и один названный счётчик

`RAG:✅` P5-Q6: возраст старейшего pending — главный сигнал пейджинга, глубина — сигнал
ёмкости. Глубина 100 000 при возрасте в секунды здорова; глубина 1 при возрасте 45 минут
— нет. Общая метрика возраста может скрыть застрявший малообъёмный маршрут за здоровым
высокообъёмным, поэтому возраст отслеживается и по классу событий.

Поэтому возраст идёт **первым** — и в объекте, и в выводе команды, и по каждому контракту
отдельно.

**Частоту дублей из таблиц прочитать нельзя.** `INSERT … ON CONFLICT DO NOTHING` не
оставляет следа: подавленный дубликат не отличим от несостоявшейся попытки. Это
называется прямо, а не заменяется похожим числом: `Inbox::claim()` возвращает `false`, и
счётчик дублей обязан вести **потребитель** в момент заявки. P5 даёт возврат `false` как
точку измерения и фиксирует в receipt'е, что пятое число фазой не производится.

### 9.2 Низкая кардинальность

`RAG:✅` P5-Q6: метрику нельзя размечать UUID события, ID агрегата, email или текстом
исключения — только низкокардинальными измерениями.

Поэтому разрез — по `contract` (`name.vN`), число которых равно числу объявленных
записей реестра и ограничено по построению, и по `failure_class` (закрытый набор из двух
значений, §5.5). Ни `messageId`, ни `subject`, ни `last_error` в разрез не попадают.

### 9.3 Готовый код

`packages/core/src/Messaging/Data/DeliveryHealth.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

/**
 * Здоровье доставки: вход для таксономии наблюдаемости P12.
 *
 * Возраст старейшего ожидающего идёт первым намеренно: глубина измеряет накопленную
 * работу, возраст — нарушенное обещание доставки (research/P5-design.md §9.1).
 *
 * Частота дублей здесь отсутствует: её нельзя прочитать из таблиц, она считается
 * потребителем по возврату Inbox::claim() === false.
 */
final readonly class DeliveryHealth
{
    /** @param array<string, int> $oldestPendingSecondsByContract */
    public function __construct(
        public ?int $oldestPendingSeconds,
        public array $oldestPendingSecondsByContract,
        public int $pendingCount,
        public int $publishingCount,
        public int $deadCount,
        public int $maxAttempts,
    ) {}
}
```

`packages/core/src/Messaging/Internal/SqlDeliveryHealth.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Messaging\Data\DeliveryHealth;
use CoreX\Support\Config;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;

final readonly class SqlDeliveryHealth
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
    ) {}

    public function read(): DeliveryHealth
    {
        $table = Config::outboxMessagesTable();
        $now = $this->clock->now();
        $counts = [];

        $grouped = $this->connection->table($table)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->get();

        foreach ($grouped as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }

        $byContract = [];
        $oldest = null;

        $pending = $this->connection->table($table)
            ->select('name', 'contract_version')
            ->selectRaw('min(occurred_at) as oldest')
            ->where('status', 'pending')
            ->where('available_at', '<=', $now)
            ->groupBy('name', 'contract_version')
            ->get();

        foreach ($pending as $row) {
            $age = $now->getTimestamp() - $this->date($row->oldest)->getTimestamp();
            $byContract["{$row->name}.v{$row->contract_version}"] = $age;
            $oldest = $oldest === null ? $age : max($oldest, $age);
        }

        ksort($byContract);

        $attempts = $this->connection->table($table)
            ->whereIn('status', ['pending', 'publishing'])
            ->max('attempts');

        return new DeliveryHealth(
            oldestPendingSeconds: $oldest,
            oldestPendingSecondsByContract: $byContract,
            pendingCount: $counts['pending'] ?? 0,
            publishingCount: $counts['publishing'] ?? 0,
            deadCount: $counts['dead'] ?? 0,
            maxAttempts: (int) ($attempts ?? 0),
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable((string) $value);
    }
}
```

`packages/core/src/Messaging/Commands/ShowDeliveryHealth.php` (`corex:messaging:health`)
печатает возраст первым, затем разрез по контрактам, затем счётчики. Опция
`--max-age=<секунды>` превращает команду в probe: при превышении возвращается
`self::FAILURE`, чтобы её можно было поставить в мониторинг как проверку, а не как отчёт.

### 9.4 Граница: OpenTelemetry — это P12

`RAG:✅` P5-Q6: соглашения OTel для messaging описывают транспорт и обработку и **не
заменяют** `backlog_depth`/`oldest_pending_age`; они всё ещё в статусе Development.

P5 производит четыре числа и команду. Экспорт в OTel, имена спанов, `span links`
продюсер→потребитель и `messaging.*` метрики — вход для P12 и её обязательство, а не
P5. Это записывается в Scope Excluded, а не подразумевается.

Тесты `packages/core/tests/Pg/DeliveryHealthTest.php` (группа `pg`, `FrozenClock`):

1. Пустая таблица → `oldestPendingSeconds` равен `null`, все счётчики нули.
2. Две ожидающие строки разных контрактов с разным `occurred_at`: разрез по контрактам
   содержит два ключа с точными возрастами, `oldestPendingSeconds` равен большему.
3. Строка с `available_at` в будущем в возраст **не** входит.
4. Строка `published` ни в один счётчик, кроме своего, не входит.
5. `maxAttempts` считается только по `pending` и `publishing`.

---

## 10. P5.1 — `message-policy.md` до исполнимого вида

Документ создан P1.6 по §8 `P1-design.md`. P5.1 его **не переписывает**: он добавляет
пять разделов, которых в нём нет, потому что на момент P1 не было ни форм, ни решений.
Ни одна существующая нормативная фраза не меняется; ни одна статья `MSG-*` не
переписывается — документ по-прежнему ссылается на идентификаторы.

Добавляемые разделы (готовый текст переносится исполнителем дословно):

**(1) Типы полей конверта.** Таблица шести обязательных полей P1 дополняется колонкой
«тип и предел»: `id` — непрозрачная строка ≤128; `source` — то же; `type` —
каноническая строка `name.vN`, где `name` — от двух до четырёх сегментов
`[a-z][a-z0-9]*`; `envelope-version` — целое, версия формата конверта; `occurred-at` —
момент с долями секунды; `scope` — карта скаляров, результат кодека `DataScope`.
Необязательные: `subject` ≤128, `correlation-id` ≤128, `causation-id` ≤128, класс
персональных данных из закрытого набора `none | pseudonymous | personal`. Отдельной
фразой: payload — ограниченная карта объектов, ≤8 уровней, ≤256 ключей на уровень,
≤64 KiB в сериализованном виде.

**(2) Как объявляется новый интеграционный контракт** — процедура из пяти шагов:
выбрать имя по правилу §2.4; определить класс (`integration-event` или `command`;
`domain-event` объявить нельзя); назвать владельца composer-именем пакета; объявить
максимальный класс персональных данных; добавить запись в реестр своего пакета,
переопределив привязку `MessageContractRegistry` в собственном провайдере. Ломающее
изменение — **новая** запись с версией `N+1` рядом со старой, а не правка существующей;
перечень ломающих изменений схемы переносится из §3.3.

**(3) Чего механизм не обещает** — три пункта, каждый со своей причиной:
порядок доставки не гарантирован (`SKIP LOCKED` даёт пропускную способность ценой
порядка); exactly-once недостижим, окно дублей неустранимо, ответ — идемпотентный
потребитель; `dispatch()->afterCommit()` **не является** реализацией `MSG-008` — он
гарантирует порядок видимости, но не долговременное намерение опубликовать.

**(4) Обязательная форма потребителя** — фрагмент §7.2 целиком: `claim()` внутри той же
транзакции, ранний возврат при `false`, запрет подтверждать доставку до коммита, запрет
переиздавать побочные эффекты при повторе. Плюс ссылка на `RUN-002`/`RUN-003`:
восстановление контекста — только `TenantContextRestorer::restore()`, и строка соединения
из сообщения входом маршрутизации не является.

**(5) Retention** — два окна outbox и отсутствие чистки inbox по умолчанию с объяснением
§7.3: у pubsub-сообщений удаление теряет недоставленное, у inbox-отметок — защиту от
повтора уже доставленного.

Приёмка P5.1: в документе присутствуют все пять разделов; `rg -c 'MSG-00'
docs/architecture/message-policy.md` не уменьшилось относительно состояния после P1.6;
`git diff --stat -- 'packages/'` пуст; `composer lint:check` из корня зелёный.

---

## 11. P5.11 — зона `Messaging` (D31)

### 11.1 Что объявляется и что не переносится

Эскиз `01-shapes-runtime.md` §3 и фаза P6 предполагают карту зон ядра целиком. P5 её
**не** создаёт: карта всех зон — предмет P6, и делать её здесь значило бы принять решения
за фазу, которая будет читать все 17 каталогов `core/src`, а не один.

P5 объявляет **одну** зону и защищает её двумя правилами:

- **R-M1.** Файл в `packages/core/src/Messaging/**` не импортирует ничего из
  `CoreX\*`, кроме закрытого allow-list'а: `CoreX\Contracts\*`, `CoreX\Enums\*`,
  `CoreX\Exceptions\*`, `CoreX\Support\Config`, `CoreX\Support\Data\*`,
  `CoreX\Support\Time\*`, `CoreX\Tenancy\Contracts\*`, `CoreX\Messaging\*`.
- **R-M2.** Файл вне `packages/core/src/Messaging/**` не импортирует
  `CoreX\Messaging\Internal\*`. Внешняя поверхность зоны — только `Contracts/`, `Data/`
  и `Commands/`.

`CoreX\PubSub\**` в этой фазе **не переносится и не переименовывается** (D31): перенос
публичных неймспейсов ядра — ломающее изменение под owner-gate G-API, и оно принадлежит
P6, где переносится весь набор сразу. Логическая принадлежность PubSub зоне `Messaging`
записывается в `findings/P5-zone-map.md` как вход P6, а не как правка кода.

### 11.2 Форма гейта

`tests/Arch/MessagingZoneTest.php` строится на существующем
`tests/Support/SourceScanner.php` (его собирает P2.1, расширяет P3.2) и на образце
`tests/Arch/LayerTest.php`. Сканер не модифицируется: нужны только импорты по файлу,
которые он уже даёт.

Обязательная негативная проверка — обе посадки:

1. импорт `CoreX\Views\Internal\SqlSavedViewRepository` в файл зоны → R-M1 краснеет
   **с именем файла и именем запрещённого символа**;
2. импорт `CoreX\Messaging\Internal\SqlOutbox` в произвольный файл вне зоны → R-M2
   краснеет с теми же данными.

Без обеих посадок гейт считается непроверенным: гейт по определению может быть
всегда-зелёным — ровно дефект G-GATE-BLIND, уже случавшийся в репозитории.

Исключение R-M2, названное заранее: `CoreServiceProvider` — composition root, и он обязан
упоминать реализации, чтобы их связать. Он вносится в allowlist **поимённо**, одним
путём, а не шаблоном.

`findings/P5-zone-map.md` — измеренная принадлежность: перечень файлов
`packages/core/src/Messaging/**` и `packages/core/src/PubSub/**` с отметкой «перенос —
P6», плюс фактический список импортов каждого файла зоны на момент item'а.

---

## 12. P5.12 — доказательства гарантий доставки

Item существует отдельно по той же причине, что `P4.11`: это тест, который **обязан
уметь покраснеть**. Присоединение его к продуктовому item'у создало бы соблазн
«подправить тест» вместо фиксации находки.

Шесть доказательств в `packages/core/tests/Pg/DeliveryGuaranteesTest.php` (группа `pg`,
`FrozenClock`):

**(1) Падение между публикацией и отметкой.** Публикатор-фикстура записывает вызов и
бросает исключение **после** записи — имитируя «брокер принял, процесс умер».
Проверяется: строка осталась в `publishing`; после истечения аренды следующий `lease()`
её перезахватывает; публикатор вызван **дважды** с одним `messageId`. Это доказательство
того, что дубль происходит — а не того, что его нет. Тест утверждает ровно
`at-least-once`, и его название обязано это говорить.

**(2) Дубль не даёт второго бизнес-эффекта.** Тот же `messageId` обрабатывается
потребителем дважды по форме §7.2; бизнес-таблица фикстуры содержит одну строку,
`Inbox::claim()` вернул `true`, затем `false`.

**(3) Конкуренция двух relay.** Два `leaseOwner` на двух независимых PDO-соединениях
захватывают из одной таблицы; объединение захваченных множеств не содержит пересечений,
и ни один из них не ждал (форма `WakeupClaimTest`).

**(4) Откат бизнес-транзакции.** Внутри `DB::transaction()` выполняются бизнес-вставка и
`Outbox::append()`, затем бросается исключение. После отката нет ни бизнес-строки, ни
строки outbox. Обратный случай тоже проверяется: при успешном коммите есть обе.

**(5) Durable handoff при offline consumer.** Consumer остановлен, publisher
подтверждает durable acceptance, outbox отмечается published; новый
consumer instance после restart получает тот же `messageId`. Lossy/null publisher
отклоняется до `markPublished()`.

**(6) Bounded reclaim.** Повторный crash→expiry→reclaim без
`releaseWithBackoff()` достигает `maxAttempts` и переводит строку в `dead`
без ещё одной публикации. Тот же исход доказан для corrupt envelope,
ошибка которого попадает в fenced failure path.

Фикстура `packages/core/tests/Fixtures/RecordingPublisher.php` — публикатор, который
записывает каждый вызов и настраивается бросать после записи или до неё.

Если любой из шести тестов краснеет на существующем дереве — это находка, которая
фиксируется в receipt'е и адресуется фазе-владельцу, а не повод ослабить утверждение.

---

## 13. Что фаза не обещает

| Не обещано | Почему | Где записано |
|:--|:--|:--|
| Порядок доставки, в том числе по ключу | `SKIP LOCKED` даёт пропускную способность ценой порядка; per-key порядок — отдельная машинерия | D28, §5.9, докблок `Outbox`, политика |
| Exactly-once | Окно между публикацией и отметкой неустранимо | §5.1, §12(1), политика |
| Честное планирование relay по многим аккаунтам | Каталог аккаунтов принадлежит `corex/tenancy` и P9; ядро его знать не должно | §5.6, receipt P5.6 |
| Jitter в backoff | На аккаунт приходится один relay; детерминизм важнее | §5.8, receipt P5.6 |
| Серверное время PostgreSQL для lease | Наследуется предел D23; два часовых домена хуже одного несовершенного | D30, §5.10 |
| Апкастеры версий конверта и контракта | Сериализованных сообщений ни одной версии в дереве нет | §3.3, receipt P5.3 |
| Статический гейт «необъявленное событие не пересекло границу» | Требует анализа потока данных, которого в плановом сканере нет | §3.4, receipt P5.3 |
| Шифрование полей, crypto-shredding, tombstone-события | Стирание без жизненного цикла ключей и инвентаря потоков — обещание сильнее факта | §2.6, Scope Excluded |
| Экспорт в OpenTelemetry и частота дублей как метрика фазы | Соглашения в статусе Development; дубли считает потребитель, а не таблица | §9.1, §9.4, Scope Excluded |
| Соответствие CloudEvents | Не реализовано и не проверено тестом | §2.1 |
| Webhook-доставка и подписи | P14; шов оставлен портом `MessagePublisher` | §6.1, Scope Excluded |
| Перенос `CoreX\PubSub\**` в зону | Ломающее изменение публичных неймспейсов — P6 под G-API | D31, §11.1 |
| `causationId` как пара `(source, id)` | Сегодня `messageId` глобально уникален сам по себе | §2.5, receipt P5.2 |
