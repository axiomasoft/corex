---
type: architecture
id: D6
date: 2026-09-14
status: accepted
item: P3.1
items: [P3.1]
supersedes: []
superseded_by: null
---

# D6 — Domain trust: лёгкий L0 SPI, policy и transport принадлежат адаптеру

**Actor:** Dmitry Vostrikov · repository owner; current plan-design instruction.
**Evidence:** owner constraints in the current session; `docs/dev-doctrine.md` §§1,4,5;
P3.1 design record; Laravel Cache/Events documentation; RFC 1034 and RFC 4086.

## Решение

P3.1 changes the previous consumer-only executable placeholder into an exact L0 design contract.
`corex/tenancy` receives only portable domain-trust SPI, value objects, coordinator,
transactional state writer and lifecycle events. It contains no DNS client, HTTP/CLI routes,
edge target, TLS issuer, secrets, product host allow-list or product-specific cache keys.

A consumer supplies policy, challenge storage and proof adapter through contracts/DI; its own
HTTP/job/CLI layer owns authentication, authorisation and external DNS/TLS execution. Default
CoreX profile has custom-domain verification disabled: no binding means no verification workflow
and no silent fallback to raw `verified_at` writes. `HostTenantResolver` remains a generic reader
of committed trusted state.

No ninth modules extender is introduced. Extensibility uses the existing Contract/DI and event
mechanisms. A separate consumer adapter is an implementation concern after this L0 item; it is
not a prerequisite for making the CoreX API executable and testable with fakes.

## Почему

A product-owned CNAME/TXT workflow is necessary only at the outer adapter, while the durable
state transition, generation and cache invalidation are reusable tenancy concerns. Keeping these
seams small makes a consumer implementation replaceable without making a DNS vendor, edge policy
or product host vocabulary part of the CoreX public API.

## Consequences

P3.1 can be executed in this repository without guessing a product. A later consumer selects
CNAME+TXT or another proof mechanism solely in its adapter and provides only configuration and
adapter tests, not a second architecture decision. Public contract additions are new interfaces
under `packages/tenancy/src/Contracts/`, documented in that package changelog and protected by
the existing contract gate. The root-domain migration is restricted to an internal generation
counter and is rejected if the current root schema differs from the pinned implementation input.

Live DNS/TLS remains forbidden. The implementation validates through a fake proof adapter and
fake challenge store only.
