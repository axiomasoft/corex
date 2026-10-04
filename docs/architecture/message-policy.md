# Политика сообщений

Инварианты — статьи `MSG-001` … `MSG-008` конституции. Здесь — форма и процедуры.

## Три класса сообщений

| Класс | Смысл | Получатель | Обработчиков | Доставка | Имя |
|:--|:--|:--|:--|:--|:--|
| Команда | Намерение изменить состояние | Один владелец | Ровно один | Синхронно или через очередь; успех не подразумевается | Повелительный глагол: `EnableModule` |
| Доменное событие | Свершившийся факт внутри одного контекста | Внутренняя подписка | Ноль или больше | Обычно в процессе, в той же единице работы | Прошедшее время: `ModuleEnabled` |
| Интеграционное событие | Опубликованный факт и контракт совместимости | Независимые потребители за границей | Ноль или больше | Асинхронно, «не менее одного раза» | Прошедшее время плюс стабильное версионированное имя |

Событие фреймворка не становится интеграционным контрактом от того, что кто-то на него
подписался. Интеграционным событие делает явное объявление, и с этого момента его имя и
схема связывают нас обещанием совместимости.

## Конверт опубликованного сообщения

Wire — **CloudEvents 1.0 structured JSON**, media type `application/cloudevents+json`.
Публичный `CloudEventCodec` кодирует внутренний профиль, внешний профиль и строго декодирует
внутренний. Порядок ключей фиксирован; отсутствующие необязательные поля не выводятся.

| Атрибут | Источник / формат |
|:--|:--|
| `specversion` | `"1.0"` |
| `id` | `messageId`, непустая строка ≤128 байт |
| `source` | непустой RFC 3986 URI-reference ≤128 байт |
| `type` | `name.vN`, объявленный контракт |
| `subject` | необязательный субъект ≤128 байт |
| `time` | `occurredAt`, UTC RFC 3339 с шестью знаками микросекунд |
| `datacontenttype` | `application/json` |
| `dataschema` | необязательный `MessageContract::$dataSchema`, абсолютный URI без fragment |
| `corexscope` | каноническая JSON-строка объекта scope с рекурсивной сортировкой ключей |
| `corexpii` | `none`, `pseudonymous` или `personal` |
| `corexenvelope` | целое `envelopeVersion`, сейчас 1 |
| `corexcorrelation`, `corexcausation` | необязательные идентичности ≤128 байт |
| `traceparent`, `tracestate` | только эти ключи metadata; tracestate требует traceparent |
| `data` | ограниченный объект payload: ≤8 уровней, ≤256 ключей на уровень, ≤64 KiB |

Extensions — скаляры. Неизвестный атрибут, `data_base64`, неверная версия, тип без `.vN`,
время без зоны и scope не в форме JSON-объекта отвергаются как `InvalidMessage`.
Внешний профиль содержит ровно `specversion,id,source,type,time,datacontenttype,data`.
`data` — объект проектора. В нём нет внутренних scope, PII, subject, correlation/causation или
trace; `dataschema` также отсутствует, поскольку redacting projector может удалить поля,
обязательные во внутренней схеме. Подпись считается по сохранённым байтам, без пересериализации.

Имя `type` — стабильное публичное имя, не имя PHP-класса. Ломающее изменение payload требует
новой версии `name.vN` и новой ссылки `dataschema`; версия схемы независима от версии конверта.

## Обязательные правила

1. Потребитель идемпотентен: повтор доставки не создаёт второго эффекта. Основание
   идемпотентности — пара «источник + id», а не содержимое.
2. Модель Eloquent в payload не сериализуется; передаются неизменяемые версионированные
   значения.
3. Сообщение несёт объявленный класс персональных данных и не превышает объявленный лимит
   размера.
4. Интеграционное событие публикуется только после фиксации изменения, которое оно
   описывает.
5. Политика повторов и «мёртвых» сообщений объявляется для каждого класса сообщений, а не
   наследуется молча от настроек очереди.
6. Изменение схемы опубликованного сообщения проверяется тестом совместимости со старой
   версией; wire и round-trip закреплены `packages/core/tests/Unit/CloudEventCodecTest.php`.

## Как объявляется новый интеграционный контракт

Запись в реестре объявленных контрактов — единственный способ сделать факт публикуемым (D24,
`MSG-002`). Процедура:

1. Выбрать имя по правилу сегментов `[a-z][a-z0-9]*` через точку, от двух до четырёх
   сегментов; опубликованная идентичность — одна строка `name.vN`.
2. Определить класс: `integration-event` или `command`. Доменное событие (`domain-event`)
   через эту процедуру объявить нельзя.
3. Назвать владельца composer-именем пакета, который публикует контракт.
4. Объявить максимально допустимый класс персональных данных для payload'а.
5. Добавить запись в реестр своего пакета, переопределив привязку `MessageContractRegistry` в
   собственном service provider.

`type` — единственная граница совместимости: совместимое изменение сохраняет его, ломающее
меняет. Ломающее изменение схемы payload'а — **новая** запись реестра с версией `N+1`, живущая
рядом со старой в течение объявленного окна миграции, а не правка существующей записи.

Ломающими считаются: удаление или переименование поля; смена типа; превращение необязательного
поля в обязательное; смена единиц, семантики, точности или nullability; изменение перечисления
так, что старые потребители его отвергают; переиспользование поля под другой смысл. Аддитивное
добавление поля безопасно только если контракт потребителя его терпит.

## Чего механизм не обещает

1. **Порядок доставки.** Гарантия порядка не даётся: захват строк outbox через `SKIP LOCKED`
   повышает пропускную способность ценой того, что сообщения могут быть обработаны не в порядке
   записи. Per-key порядок требует отдельной машинерии и в базовый механизм не входит (D28).
2. **Exactly-once.** Доставка «не менее одного раза» (`at-least-once`): окно между успешной
   публикацией и отметкой потребителя неустранимо, дубли неизбежны. Ответ — стабильный
   идентификатор сообщения и идемпотентный потребитель, а не попытка exactly-once.
3. **`dispatch()->afterCommit()` и outbox.** `dispatch()->afterCommit()` (и `after_commit =>
   true` у очереди) **не является** реализацией `MSG-008`, потому что гарантирует только порядок
   видимости — не отправлять до коммита и отбросить при откате, — а не долговременное намерение
   опубликовать. После коммита бизнес-изменения остаётся окно: процесс умер, деплой прервал
   работу или публикация провалилась, и локальной записи, из которой можно сверить и повторить,
   нет (D25).

## Обязательная форма потребителя

При повторной доставке потребитель подтверждает доставку, не применяя бизнес-логику снова; не
подтверждает брокеру успех до коммита локальной транзакции; не переиздаёт побочные эффекты из-за
повтора.

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

Восстановление контекста выполнения — только через `TenantContextRestorer::restore()` (`RUN-002`,
`RUN-003`). Строка соединения или иной параметр подключения из сообщения не является входом
маршрутизации.

## Retention

Outbox несёт **два** окна хранения терминальных строк: опубликованные строки — после срока
`outbox_retention` (дефолт установки — 14 суток); мёртвые строки (`dead`) — отдельное, более
длинное окно `dead_retention` (дефолт — 90 суток), потому что они нужны расследованию и аудиту.

Inbox по умолчанию **не чистится**: `inbox_retention` по умолчанию `null`, и команда prune inbox
не трогает. Retention отметки inbox — горизонт корректности дедупликации, а не уборка: отметку
хранят не меньше максимального времени, в течение которого тот же `message_id` может появиться
снова (D29). Это сознательно отличается от дефолта `sys_pubsub_messages` (30 суток): у
pubsub-сообщений удаление теряет **недоставленное**, а у inbox-отметок — **защиту от повтора уже
доставленного**; вторая потеря обнаруживается позже и тише. Включить чистку inbox можно только
осознанно — с записью в политике конкретной установки, а не правкой конфигурации в одиночку.

## Webhook projection и внешняя доставка

Webhook topics — immutable projection объявленных P5 contracts через explicit installed
projectors, а не подписки на произвольный Laravel event или второй message registry.
Projection применяет field allow-list и текущую PII policy: personal/restricted требуют
явной sensitive ability; secret запрещён всегда. Canonical UTF-8 body ≤ 20 KiB,
active subscriptions/account ≤ 100 и matching subscriptions/event ≤ 100.
Переполнение сохраняется как dead с safe code, без truncation или произвольного URL.
Шифрование хранения принадлежит deployment; crypto-shredding не обещается.

Inbox claim `api.webhooks.v1` и deliveries коммитятся в одной tenant transaction,
unique(subscription,event_id) исключает второй fan-out. Snapshot включает подписки
на первой successful materialization. Сеть вне transaction. `event_id` — исходный
message id; `webhook-id` — отдельная delivery id. Retries/replay сохраняют raw body,
hash, destination и оба id. Доставка at-least-once, без ordering; ACK loss допускает
повтор после receiver success. Receiver фиксирует inbox и effect атомарно.
Lease fence защищает sender DB, но не отзывает отправленный HTTP request.

Standard Webhooks HMAC-SHA256 подписывает `delivery-id.timestamp.raw-body`;
`whsec_` содержит strict base64 ровно 32 bytes. Timestamp и подпись свежие на каждой
попытке, headers — webhook-id/webhook-timestamp/webhook-signature. Receiver проверяет
constant-time signature, ±300s и durable dedup. До 2 keys в overlap дают space-delimited
signatures. Перед revoke нужна explicit readiness нового key, не произвольный 2xx;
скомпрометированный key допускает immediate revoke с явной потерей zero-downtime.
SecretRef хранится без resolved secret; raw payload/response/signature/secret не попадают
в attempts, management responses или logs. Live rotation требует host authority.

Production accepts HTTPS:443 и canonical DNS host без userinfo/query/fragment/IP literals.
Каждый attempt проверяет весь A/AAAA set, bounded CNAME и deny-ranges, включая mixed sets,
IPv4-mapped IPv6 и organization ranges. Vetted socket address pinned до connect;
TLS hostname/SNI и peer verification сохраняются. Redirects/proxies/fallback DNS запрещены.
Timeouts 3/10 s; response body discarded ≤ 64 KiB, headers ≤ 16 KiB. Local-only TLS tests
отделены от production policy, которая не получает localhost exception.

Lease 30 s; 2xx success, 410 disable subscription, 3xx terminal; 408/425/429, 5xx
и network failures retry, остальные 4xx dead. Full jitter base 5 s / cap 3600 s / min 1 s,
Retry-After ≤ 24 h; retry cycle ≤ 12 attempts / 72 h. Unknown attempt остаётся unknown;
late ACK не меняет новую lease. Worker получает уже active trusted tenant,
multi-account scheduler отсутствует; remote delivery default off.

Replay — та же delivery, не новое business event (D84). Terminal-only CAS,
active lease конфликтует; succeeded replay требует explicit intent и audit reason.
До 3 cycles в абсолютном горизонте 7 d; attempt sequence монотонна. Range replay требует
subscription, UTC window ≤ 24 h, fixed upper bound, max 100 и scoped continuation;
idempotency возвращает прежний selection/outcome. Disabled/changed endpoint,
revoked policy, expired body или removed topic дают отказ.

Body TTL 7 d, history/attempts 30 d; shortened PII body TTL и replay horizon уменьшаются
вместе. Active deliveries не prune. Delivery dedup tombstone живёт весь горизонт
повторного поступления P5 event; webhook inbox нельзя удалить раньше tombstone.
Default indefinite P5 inbox остаётся границей корректности: старый outbox event
не создаёт нового send после уборки body. Prune ≤ 100, tenant-scoped, Clock-based;
expiry и отсутствие reservation никогда не разрешают refund.

Schema owner и retained classes — [table-ownership.md](table-ownership.md).
Current proof, grants, hashes и ограничения —
[P14 qualification receipt](../../plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P14-execution-validation.md).
