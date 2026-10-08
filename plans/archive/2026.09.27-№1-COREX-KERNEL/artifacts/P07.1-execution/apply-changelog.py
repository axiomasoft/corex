#!/usr/bin/env python3
"""Insert the P07.1 entry under `## Unreleased` of packages/tenancy/CHANGELOG.md.

The agent session cannot read or edit CHANGELOG.md (owner permission rule), so the owner runs this once:

    python3 "plans/2026.09.27-№1-COREX-KERNEL/artifacts/P07.1-execution/apply-changelog.py"

It prints only a status line and is idempotent (a second run changes nothing).
"""
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[4]
PATH = ROOT / 'packages/tenancy/CHANGELOG.md'
MARK = '### PLAN3.P07.1 — own tenancy engine'

ENTRY = '''
### PLAN3.P07.1 — own tenancy engine (BC delta, unpublished; D95, D98, D100–D103)

- **Added:** `Runtime\\Contracts\\TenantBootstrapper` (SPI), `Runtime\\Events\\TenantContextInitialized` / `TenantContextEnded`,
  `Runtime\\Exceptions\\TenantContextUnavailable` (`tenancy.context_unavailable`, `TenancyErrorCode::ContextUnavailable`),
  `Http\\Middleware\\ScopeSessionToTenant`, `Support\\HostResolverCacheInvalidator`; internal `DatabaseTenancyManager` with
  connection / cache-prefix / session bootstrappers, per-job `QueueTenantContext`, `HostAccountResolver`.
- **Semantics:** every context switch (`initialize`, `end`, leaving `runFor`) forgets the container's scoped instances (the scope
  boundary is the tenant boundary); a failed revert poisons the runtime; queue payloads carry `corex_tenant` and `JobAttempted` is
  the single completion point; sessions are bound to the account (first binding invalidates earlier attributes; mismatch → 403).
- **Changed (BC):** `AccountStatusGate::__construct()` takes five required arguments (policy, `TenantContextResolver`,
  `AccountRead`, `AuthFactory`, central connection); `ResolveTenantFromHost` is a plain middleware (no stancl base class, new
  constructor) and ends the context after the response; `TenancyOptions` gains `bootstrappers`, `cacheStores`, `hostCache*`
  (replacing `bootstrapperOrder` and `hostIdentificationCache*`); `TenantCouldNotBeIdentifiedByHostException` no longer extends a
  stancl exception.
- **Configuration / security:** config key `tenancy.bootstrapper_order` → `tenancy.bootstrappers`; new `tenancy.cache_stores`
  (null = the default cache store; only redis, memcached, database and file stores are supported, `array`/`null`/`failover` are
  refused); the host cache store must be outside `cache_stores`; tenant HTTP sessions support the `database`, `redis`,
  `memcached` and `file` drivers (redis/memcached need a dedicated `session.store`), other drivers are refused before the first
  switch.
- **Removed:** `Bootstrappers\\WorkspaceContextBootstrapper`, `Resolvers\\HostTenantResolver`,
  `Listeners\\RevertTenancyOnJobException`; the stancl provider is no longer discovered (`extra.laravel.dont-discover`).
'''.split('\n')

text = PATH.read_text(encoding='utf-8')

if MARK in text:
    print('already present — nothing to do')
    sys.exit(0)

lines = text.split('\n')
index = next((i for i, line in enumerate(lines) if re.match(r'^##\s+\[?unreleased', line, re.I)), None)

if index is None:
    print('NO `## Unreleased` section found — add one, then rerun')
    sys.exit(1)

lines[index + 1:index + 1] = ENTRY
PATH.write_text('\n'.join(lines), encoding='utf-8')
print(f'inserted after line {index + 1}')
