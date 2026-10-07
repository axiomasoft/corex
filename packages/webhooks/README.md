# CoreX Webhooks

Webhook subscriptions, signing, delivery and replay for CoreX operations.

```bash
composer require axiomasoft/corex-webhooks
```

Requires PHP 8.4 and Laravel 13. Uses CoreX Operations through its public contracts.

## Configuration

Register `CoreX\Webhooks\WebhooksServiceProvider` alongside `CoreX\Operations\ApiServiceProvider`. Publish `corex-webhooks-config`, then run `php artisan corex:schema:migrate --scope=tenant --database=<tenant>`. Remote delivery requires both `delivery_enabled` and `worker_ready`, plus a host-owned key ring; both flags default to false.

## Public contracts

Configure explicit topics and payload projectors in `corex-webhooks`. Bind the package contracts for signing keys and guarded delivery in the host. Subscriptions and replay use the trusted operation context; see the [operation contract](../operations/README.md).

## Failure handling

Unavailable transport or signing capability refuses delivery. Transport rejection takes precedence over an untrusted HTTP status; a rejected peer response cannot acknowledge a delivery or disable a subscription. Egress SPI and persistence failures propagate without being mapped to DNS errors. URL policy, subscription scope, replay horizon and lease checks remain mandatory. Failed attempts retain their recorded outcome; resolve the cause before replaying through the supported API.

Delivery defaults to 12 attempts over 259200 seconds. Set `max_attempts` (1–20) and `retry_horizon_seconds` (3600–604800, at most `replay_horizon_seconds`) to change the shared retry and expired-lease budget. HTTP 410 disables the subscription, redirects and other permanent failures become dead; transient failures retry with jitter and bounded Retry-After. Expired workers retain `ack_unknown` in attempt history; the delivery records any exhausted budget reason.

`WebhookHttpResult::$errorCode` is `?WebhookFailureReason`; SQL history persists its string values. Reasons expose `isPermanent()`, `isTerminal()` and `fromHttpStatus()`.

Bind `CoreX\Webhooks\Contracts\WebhookEgressPolicy` in the host to restrict tenant destinations. It receives `DataScope` and canonical `WebhookDestination` (`scheme`, `host`, `port`, `path`). The default permits destinations subject to the mandatory SSRF policy. The policy runs on subscription create/update and before DNS on every attempt, including retries and replay. Denial produces `webhooks.webhook_egress_denied` (422) at subscription time and `dead`/`egress_denied` at delivery time without transport I/O. See the [webhooks threat model](../../docs/architecture/webhooks-threat-model.md).

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
