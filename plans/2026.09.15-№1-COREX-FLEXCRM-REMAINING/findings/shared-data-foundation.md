# Shared data foundation receipt

**Schema:** shared-data-foundation/1
**Item:** P6.2 (serial first eligible producer)
**Observed:** 2026-09-16
**Status:** PASS — Core-only immutable foundation; no engine, schema, or provider authority added.

## Delivered boundary

`DataScope` carries only an existing trusted tenant context, physical connection identity,
audit actor identity, a runtime authorization closure, and a canonical restore epoch.
`RecordRef` preserves the existing opaque entity/record identity strategy. `WriteToken`
binds the idempotency key to a lowercase SHA-256 payload hash and an expected record version.
None is deserializable authority and none depends on `corex/attributes` or another sibling.

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `research/P6-data-design.md` | `149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb` | frozen boundary contract |
| `findings/P2-contract-proposals.md` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | C-P2-014 proposal manifest |

## Evidence

| Path | SHA256 | Observable result |
|---|---|---|
| `packages/core/src/Support/Data/DataScope.php` | `4f4da2f5a17ea56e0270fcf253b6e26475bbc3b4fe596821ff5ea6da45b7c284` | validates opaque trusted identities and canonical restore epoch |
| `packages/core/src/Support/Data/RecordRef.php` | `f8f301a81ba2caa866c0d9cfb84e2634c48dbe941394064402f6feee8b8aebdb` | validates bounded opaque record handle |
| `packages/core/src/Support/Data/WriteToken.php` | `e8f05733dfda14584a723795c96ddfdeced114ddf0302e22d4415561618c3ff2` | validates idempotency key, SHA-256 hash, and record version |
| `packages/core/tests/Unit/SupportDataTest.php` | `899477f41b0fb8b08d8356880cf06d88d96834a4209cf0822cde14cb1de2071d` | 13 tests / 19 assertions PASS |

Commands executed from the repository root:

- `vendor/bin/pest packages/core/tests/Unit/SupportDataTest.php` — PASS, 13 tests / 19 assertions.
- `vendor/bin/pint --test packages/core/src/Support/Data packages/core/tests/Unit/SupportDataTest.php` — PASS.

## Reuse rule

Later P6/P7 writers reuse these exact files after a byte/hash check. They must not recreate
tenant context, connection selection, ID generation, authorization defaults, or idempotency
semantics inside a consumer package.
