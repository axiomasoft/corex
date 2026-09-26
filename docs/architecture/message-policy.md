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

Обязательные поля:

| Поле | Назначение |
|:--|:--|
| `id` | Идентификатор конкретного опубликованного сообщения; пара «источник + id» уникальна |
| `source` | Стабильный идентификатор публикующего контекста |
| `type` | Стабильное публичное имя контракта с версией |
| `envelope-version` | Версия формата конверта — отдельно от версии схемы содержимого |
| `occurred-at` | Момент наступления факта |
| `scope` | Скаляры, определяющие область данных (см. `RUN-002`) |

Необязательные: `subject` (затронутая сущность в терминах источника), `correlation-id`,
`causation-id`, класс персональных данных.

Имя `type` не равно имени PHP-класса: класс переименовывают при рефакторинге, а имя
контракта переименовать нельзя — на него завязаны маршрутизация, авторизация и наблюдение
у потребителя. Версия в имени контракта — не то же самое, что версия конверта.

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
   версией.

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
