# Вебхуки: модель угроз

## Активы
Payload событий арендатора; ключи подписи; внутренняя сеть и metadata-адреса; квота исходящих соединений.

## Границы доверия
Арендатор → API подписок; worker → внешняя сеть; оператор → replay.

## Угрозы и контроли
| ID | Угроза | Контроль | Доказательство |
|:--|:--|:--|:--|
| T1 | URL ведёт во внутреннюю сеть или metadata (SSRF) | `WebhookUrlPolicy`: https, канонический хост, проверка всех A/AAAA, IPv4-mapped IPv6, org CIDR | `WebhookUrlPolicyTest` |
| T2 | DNS rebinding между проверкой и соединением | DNS pinning `CURLOPT_RESOLVE`, проверка peer IP | `WebhookTransportTest` |
| T3 | Редирект на внутренний адрес | редиректы не выполняются; 3xx — `redirect_forbidden`, dead | `WebhookFailureReasonTest` |
| T4 | Арендатор шлёт данные на неразрешённый хост | `WebhookEgressPolicy` при подписке и на каждой попытке | `WebhookEgressPolicyTest`, mutation `P06-egress-skip-on-delivery` |
| T5 | Подмена или повтор доставки | подпись Standard Webhooks по `id.timestamp.body`, ротация ключей | `WebhookSignatureTest` |
| T6 | Утечка внутреннего контекста получателю | внешний профиль CloudEvents без `corex*`, `subject`, trace | `CloudEventCodecTest`, `WebhookFanoutTest` |
| T7 | Чужой арендатор получает payload | доставка привязана к subscription/scope/event; fan-out в транзакции арендатора | `WebhookFanoutTest` |
| T8 | Бесконечные ретраи, усиление нагрузки | бюджет попыток и горизонт, jitter, Retry-After с потолком | `WebhookRetryBudgetTest` |
| T9 | Replay на изменённый или запрещённый адрес | replay → pending → обычная попытка с текущими проверками; аудит причины | `WebhookEgressPolicyTest` (replay), `WebhookReplayTest` |
| T10 | Огромный payload | лимит тела, `payload_too_large`, dead без отправки | `WebhookFanoutTest` |

## Остаточные риски
Сетевой egress-фильтр (прокси, изоляция сети) — ответственность хоста; приложение его не заменяет.

The default egress policy permits destinations that pass the mandatory URL policy. Hosts can bind `WebhookEgressPolicy` to restrict tenant destinations; denial is checked before DNS and every send, including replay.

Transport details are bounded into `NetworkFailure`, `TransportFailure`, `PayloadTooLarge` and `AddressNotAllowed`. SQL records store enum values. Expired leases preserve an unknown acknowledgement even when the delivery budget is exhausted.
