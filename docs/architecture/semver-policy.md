# PHP API and SPI compatibility

A type's source attribute declares its supported surface (PLAN3.D63/D64). The `since` value identifies the version that introduced the symbol. A published release requires separate verification.

| Layer | Consumer use | Examples of breaking changes |
|:--|:--|:--|
| Api | Calling, reading, catching exceptions | Removing a type or member, renaming a parameter, narrowing an input, widening an output, changing nullability or a default, requiring a new DTO field, removing an enum case or changing its backing value |
| Spi Implement | Implementing or extending the type | Adding a required interface or abstract member, changing a protected hook, construction contract or consumer signature |
| Spi Call | Calling, subscribing or integrating as documented | Changing a payload, constructor, default, declared subscription or data schema |
| Internal | Owning package only | External imports are prohibited. The owner makes no external compatibility commitment. |

A new optional parameter must preserve existing behavior through its default. An Api interface permits callers only. Adding a callable method imposes no new implementation requirement because external implementations are prohibited. Only Spi Implement guarantees protected hooks.

Named argument calls depend on parameter names. The `php-api/1` snapshots include native and nested PHPDoc types, properties, enum values, constants and defaults.

This repository currently has no automated Roave compatibility diff or `composer bc-check` command. Review public surface changes using `composer php-api` and the golden PHP surface. Record each change in the package CHANGELOG.

**Before a stable release**, run an independent compatibility comparison against the approved baseline. For subsequent releases, compare against the published tag. A historical report or a newly generated snapshot cannot establish compatibility by itself. Changing the snapshot and CHANGELOG alongside the code does not authorize a breaking change in a supported release.

Behavioral checks cover error semantics, pagination, wire and schema contracts, authorization and transactions. See the [API policy](api-policy.md).
