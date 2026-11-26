# Webhook threat model

## Assets and trust boundaries

Protect tenant event payloads, signing keys, private networks, metadata endpoints and outbound connection capacity. The subscription API accepts tenant input. Workers send to external destinations. Operators can request replay.

| ID | Risk | Control | Evidence |
|---|---|---|---|
| T1 | A URL resolves to a private or metadata address | `WebhookUrlPolicy` requires HTTPS, canonical hostnames, checks all A/AAAA records, IPv4-mapped IPv6 addresses and organization CIDRs. | `WebhookUrlPolicyTest` |
| T2 | DNS changes between validation and connection | Pin the resolution using `CURLOPT_RESOLVE` and check the peer IP. | `WebhookTransportTest` |
| T3 | A redirect leads to a private address | Stop on 3xx and record `redirect_forbidden` as a terminal failure. | `WebhookFailureReasonTest` |
| T4 | A tenant sends to a destination outside policy | Enforce `WebhookEgressPolicy` at subscription and on each delivery attempt. | `WebhookEgressPolicyTest` and the delivery egress mutation |
| T5 | A recipient receives a forged or replayed delivery | Sign `id.timestamp.body` according to Standard Webhooks and support key rotation. | `WebhookSignatureTest` |
| T6 | A payload exposes internal context | Exclude CoreX extensions, `subject` and trace fields from external CloudEvents. | `CloudEventCodecTest`, `WebhookFanoutTest` |
| T7 | A tenant receives another tenant's payload | Bind the subscription, scope and event. Fan out within the tenant transaction. | `WebhookFanoutTest` |
| T8 | Unbounded retries amplify outbound load | Bound attempts and elapsed time, apply jitter and cap Retry-After. | `WebhookRetryBudgetTest` |
| T9 | Replay reaches a changed or forbidden destination | Replay re-enters pending delivery, applies the current checks and records the reason. | `WebhookEgressPolicyTest`, `WebhookReplayTest` |
| T10 | An oversized payload consumes bandwidth | Stop before sending and record a terminal `payload_too_large` result. | `WebhookFanoutTest` |

## Host responsibilities

The host supplies network isolation and outbound proxy or firewall policy.

The default egress policy permits destinations that pass the mandatory URL policy. Hosts can bind `WebhookEgressPolicy` to restrict tenant destinations; denial is checked before DNS and every send, including replay.

Transport details are bounded into `NetworkFailure`, `TransportFailure`, `PayloadTooLarge` and `AddressNotAllowed`. SQL records store enum values. Expired leases preserve an unknown acknowledgement even when the delivery budget is exhausted.
