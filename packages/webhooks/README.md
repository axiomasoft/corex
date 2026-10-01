# CoreX Webhooks

Webhook subscriptions, signing, delivery and replay for CoreX operations.

```bash
composer require axiomasoft/corex-webhooks
```

Requires PHP 8.4 and Laravel 12 or 13. Uses CoreX Operations through its public contracts.

## Configuration

Register `CoreX\Webhooks\WebhooksServiceProvider` alongside `CoreX\Operations\ApiServiceProvider`. Publish `corex-webhooks-config` and `corex-webhooks-migrations-tenant` for the tenant connection. Remote delivery requires both `delivery_enabled` and `worker_ready`, plus a host-owned key ring; both flags default to false.

## Public contracts

Configure explicit topics and payload projectors in `corex-webhooks`. Bind the package contracts for signing keys and guarded delivery in the host. Subscriptions and replay use the trusted operation context; see the [operation contract](../operations/README.md).

## Failure handling

Unavailable transport or signing capability refuses delivery. URL policy, subscription scope, replay horizon and lease checks remain mandatory. Failed attempts retain their recorded outcome; resolve the cause before replaying through the supported API.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.
