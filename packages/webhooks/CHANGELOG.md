## Unreleased — thin providers and registrars (P05.2)

- `WebhooksServiceProvider` uses `WebhookBindingsRegistrar` and `WebhookHttpRegistrar`; `WebhookRegistrar` and `WebhookRouteRegistrar::register()` stay as deprecated shims.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- `WebhookOptions` is a lazy shared singleton resolved in `boot()`; `WebhookUrlPolicy` is built from it on first use.

## Unreleased — CI ownership and test isolation repair (P10.2)

- Webhook retention delegates inbox deletion to core's public `InboxRetention` API, preserving consumer scoping and delivery tombstones without writing directly to a foreign table.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): Extracted webhooks into their own CoreX\Webhooks package and provider; subscription/delivery/transport/signing ports exchange WebhookSubscription, WebhookDelivery and signed-header DTOs. WebhookOptions and package-owned errors define the supported configuration/failure surface.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Extracted from CoreX Operations with an independent PSR-4 root, service provider and test suite.
