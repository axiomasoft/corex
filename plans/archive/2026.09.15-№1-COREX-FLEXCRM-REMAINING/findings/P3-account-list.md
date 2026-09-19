# P3.6 account-list receipts

Version: p3-adapter-receipts/1. `RedisAccountMenuCache` and
`StrictAccountsTransport` are hidden, explicitly constructed adapters. Neither
registers a cache store, endpoint, provider, guard, route, writer, or authority
decision; account-menu data remains display-only.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| redis-cold-loss | R07 | RedisAccountMenuCache / isolated native Redis db 15 | delete current result bytes after authoritative version is retained | fresh transport read; no stale surviving result | `RedisAccountMenuConformanceTest.php` SHA-256 `1201d91e6138a80d541725d47436d4028ef71b86e3a71bc247e9256996df0aad`; `P3_REDIS_PORT=6387`, temporary `redis:7-alpine`, target GREEN 4/4 | PASS | Temporary container and generated `corex:p3.6:<random>` only; installed Redis selection is P9/P10 |
| context-version-key-poison | R07 | RedisAccountMenuCache | duplicate-key malformed JSON plus same identity/product other-product lookup | poison is cache miss and product cannot cross-hit | `RedisAccountMenuConformanceTest.php` SHA-256 `1201d91e6138a80d541725d47436d4028ef71b86e3a71bc247e9256996df0aad`; native Redis target GREEN | PASS | Cache value never grants access; version reader remains authoritative |
| revoke-regrant-stale-list | R07 | AccountMenuReader + RedisAccountMenuCache | stale revoke-1 bytes survive while authoritative version becomes regrant-2 | new version hashes to distinct key and transport supplies regrant list | `RedisAccountMenuConformanceTest.php` SHA-256 `1201d91e6138a80d541725d47436d4028ef71b86e3a71bc247e9256996df0aad`; target GREEN | PASS | Real central version source is explicit consumer composition (P9/P10) |
| fresh-click-deny | R07 | AccountMenuReader + AccountClickAuthorizer | stale menu click with authorizer denial | one fresh central-boundary delegation returns deny | `AccountSwitcherTest.php` SHA-256 `c0349d5951225f44cedeb852a31df782a71540ca3af093d1336d75d6851d2eb0`; protected package suite GREEN | PASS | Consumer owns actual membership authorization binding |
| accounts-wire-mtls-negative | R04/R07 | StrictAccountsTransport / corex-membership/1 | valid authenticated GET then missing `accounts.read` and malformed profile envelope | exact URI/query/no-store profile succeeds only for valid mTLS/JWT scope; failures deny before menu | `RedisAccountMenuConformanceTest.php` SHA-256 `1201d91e6138a80d541725d47436d4028ef71b86e3a71bc247e9256996df0aad`; target GREEN 4/4 | PASS | No accepted deployed accounts endpoint/profile extension is claimed; G-CENTRAL owns it |
| cache-ttl-unavailable | R07 | RedisAccountMenuCache / native Redis | TTL=1 expiry and null authoritative version | TTL is bounded at <=300 and expires; unavailable version produces unavailable menu with zero transport fallback | `RedisAccountMenuConformanceTest.php` SHA-256 `1201d91e6138a80d541725d47436d4028ef71b86e3a71bc247e9256996df0aad`; native Redis target GREEN 4/4 | PASS | Connection/eviction operations are display misses; live cache SLO is P10 |
| archive-lab-account-list | R07 | two isolated Redis namespaces | archive and laboratory caches use same context/version | neither namespace observes the other result | `RedisAccountMenuConformanceTest.php` SHA-256 `1201d91e6138a80d541725d47436d4028ef71b86e3a71bc247e9256996df0aad`; target GREEN 4/4 | PASS | This is laboratory conformance only, not an installed consumer receipt |

Evidence envelope: cwd `/home/vostrikov/projects/packages/corex`; runtime PHP 8.5,
PostgreSQL `corex_test` healthy, temporary native `redis:7-alpine` at
`127.0.0.1:6387` database 15; no production Redis key or configuration was
used. Full package output is recorded by immutable pointer
`artifacts/B-P3-IDENTITY/logs/P3.6-tenancy-test.log` (its final post-source
delta SHA is recorded in the P3.6 check result). The initial full package
transcript lost its terminal tool payload; it was repeated once only to produce
that immutable terminal log after the material archive/laboratory source delta.

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| findings/P3-identity-transport.md | a35089c5b27e4e596779d902037bc498da21bbb30a9f65f4f953bca0dc1ae1ae | P3.3 strict transport protected baseline |
| findings/P3-durable-projection.md | 39b32858064f84ddc3e44b549f9ecd85b3ae59e1b61681666c12f961bcd8aa42 | P3.4 durable predecessor receipt |
| findings/P3-final-write.md | 60b551415462b65ba1f3522df96364140d0deea41be8021558b94ca632cdcfdd | P3.5 final-write predecessor receipt |
| decisions/D35-P3.6-switcher-local-adoption.md | 98ae21f96213001b7d82c79b03d37da196ee4586b485f4ead4073fdc4513c27f | scoped owner adoption receipt |
| packages/tenancy/src/Membership/Internal/Switcher/RedisAccountMenuCache.php | d5454251d076925bd96a54b7041dc1b64eb7fe33b50763ce1af14a534db33ae9 | hidden authoritative-version cache adapter |
| packages/tenancy/src/Membership/Internal/Switcher/StrictAccountsTransport.php | d5b26080159a5d05281925cf08c770a9878e768fca5421e178157be72fd7316b | hidden explicit transport composition |
| packages/tenancy/src/Membership/Internal/Switcher/AccountMenuReader.php | de994df7416a0c8e441a758ba3c6c736223db001e68f117a7c015b92a5d5a9ce | authoritative-version failure becomes unavailable display menu |
| packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php | 6728e236107ba7bcb20d25867508c388004ed381e4ef8f07d89016e61cccacdc | mTLS/JWT/no-store/profile boundary |
| packages/tenancy/tests/Feature/RedisAccountMenuConformanceTest.php | 1201d91e6138a80d541725d47436d4028ef71b86e3a71bc247e9256996df0aad | native Redis and strict wire conformance |
| packages/tenancy/CHANGELOG.md | dbcd243d97363caa359bef85574e072920501d776f0ebc6b4b6fa2bd61323e2b | public change note |
| artifacts/B-P3-IDENTITY/logs/P3.6-tenancy-test-styled.log | c4e4a7f5bbba820735fc3a7b16cb1e35fe69c1b8b9e7fd6a3bf96942f6fe6bd1 | terminal full package receipt: 472 passed, 3 skipped, 1503 assertions |

## Adoption

| Surface | Baseline/proposal digest | Local supported mode | Protected receipt | Registration | Remaining P10 gate |
|---|---|---|---|---|---|
| RedisAccountMenuCache | switcher-candidate/1; D35 `98ae21f96213001b7d82c79b03d37da196ee4586b485f4ead4073fdc4513c27f` | Explicit `Repository`, authoritative `Closure(AccountsQuery): ?string`, nonempty isolated namespace | D35 and native Redis 4/4 conformance | None | installed trusted Redis selection, eviction/availability SLO and consumer cache namespace |
| StrictAccountsTransport | accounts-wire-candidate/1; D35 | Explicit `StrictTransport` plus the already-negotiated binding read URI; `accounts.read` mTLS/JWT scope | D35 and strict wire negative conformance | None | G-CENTRAL exact endpoint/profile extension, consumer click authorization and deployed receipt |
