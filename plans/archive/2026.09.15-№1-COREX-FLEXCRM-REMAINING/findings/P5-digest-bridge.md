# P5 digest bridge

**Schema:** p5-wakeup-receipts/1; wakeup/1
**Item:** P5.5
**Observed:** 2026-09-16

The digest bridge is an engine-free `WakeupHandler`: it converts the single shared wakeup delivery to `DigestTick` with the trusted tenant, explicit window and immutable request key.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| BRIDGE-01 | R10 R16 | boxed digest adapter | digest wakeup delivery | tenant/window/key map to `DigestTick` | `64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54` | PASS | no workflow/root/Redis import |
| BRIDGE-02 | R10 R16 | notification SQL outbox | restart after durable digest request | existing wakeup key is reused | `49cd590e53c26bc388a0ae34ffe915f5080f7bd79748ce8091c596deb4b1224f` | PASS | full late-item catch-up is P4.6 |
| BRIDGE-03 | R16 | PostgreSQL independent connections | frozen run row is concurrently locked | second tick cannot take stale lock | `2ae182f95bf2a94a1a7d6e5afdb064f8ff9743fada7701df51bfe89412796881` | PASS | preference/admission semantics remain P4.6 |
| BRIDGE-04 | R10 R16 | explicit UTC window | hourly half-open window | period/window/key remain stable | `64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54` | PASS | owner supplies DST calendar window |
| BRIDGE-05 | R10 | disabled neutral consumer | disabled digest configuration | no workflow engine is resolved | `49cd590e53c26bc388a0ae34ffe915f5080f7bd79748ce8091c596deb4b1224f` | PASS | independent reminder remains wakeup API consumer |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `packages/notifications/src/Internal/Digest/DigestWakeupAdapter.php` | `4df3250543b1cc61d003a4ea1db83ff89e310a0075aed6119a35c158acbe989e` | typed bridge implementation |
| `packages/notifications/tests/Digest/WakeupBridgeTest.php` | `64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54` | mapping test |
| `packages/notifications/tests/Pg/DigestConcurrencyTest.php` | `2ae182f95bf2a94a1a7d6e5afdb064f8ff9743fada7701df51bfe89412796881` | real PG lock test |
| `artifacts/qualification/p5/bridge-tests.md` | `49cd590e53c26bc388a0ae34ffe915f5080f7bd79748ce8091c596deb4b1224f` | executed checks |
| `findings/P5-local-wakeup.md` | `3e644aa885e31fc8f7b2a3d01f4d7292ac58d2321610562936e4e0212223242a` | shared producer receipt |
| `findings/P4-notification-outbox.md` | `cb349a921f949841c2c5544b387f663493d9c3ee7d1ae4a5dae9392d133de558` | SQL outbox producer receipt |
| `findings/P4-notification-channels.md` | `991d89ce20ee82a37316aa01b2f3c51588852cf55f068c60839b30e93245edeb` | channel/preference producer receipt |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| wakeup/1 digest consumer | `0c7651c6c8f152348184d3dc3e7b40039c87d5d0` | P5.2 local wakeup report | `DigestWakeupAdapter` / `DigestTick` | optional notifications digest handler | P4.6 owns full DIG admission/catch-up |
