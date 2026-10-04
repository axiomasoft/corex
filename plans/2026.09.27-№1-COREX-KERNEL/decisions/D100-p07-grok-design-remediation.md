---
id: D100
date: 2026-10-04
status: accepted
item: P07
items: [P07, P07.1, P07.3, P07.4, P07.5, P07.6]
supersedes: []
superseded_by: null
---
# D100 — p07-grok-design-remediation (accepted)

**Actor:** designer/codex, owner-authorized design repair via artifacts/P07-design-remediation/new-session-prompt.txt. Actual provider metadata is retained in this repair's provenance; no Task invocation or formal audit route is inferred.
**Evidence:** RAG:— findings/P07-grok-review.md findings 1–4, 6–7, installed Laravel/stancl, stock QueuedAuditLogger/RecordAuditEntry/AuditLog, SqlDigestBuilder, package-graph-gate/taxonomy; artifacts/P07-design-remediation/six-findings-proof.php and six-findings-proof.json. Baseline HEAD 4137d2412497aa9348cf19322b26f1d074b5ad2c.

## Solution

Scoped priority amendment of D95 p.2/4/6 and D96 p.1/4/5/7/10; D98/D99 otherwise remain effective. Historical decisions/findings are preserved. Product P07.1–P07.6 is not executed.

1. **Finding 1 / P07.1:** parse central connection with installed ConfigurationUrlParser in both CentralTemplateConnectionResolver and ConnectionBootstrapper, carry parsed password/options, remove url before authoritative tenant route overrides. Remove read/write overlays; stock template is a single route, replicas require a host resolver's explicit tenant-safe implementation. DatabaseConnectionConfig.options cannot carry url/driver/host/port/database/username/read/write. Acceptance includes URL credentials/query options, two tenant DBs plus central URL DB, host resolver override and negative routing options. Central config stays byte-identical.
2. **Finding 2 / P07.1:** SessionConnectionBootstrapper replaces already created handlers on the same Store, restores handlers/full config, and isolates file path/cache clone including Redis session.prefix. HTTP supports database/redis/memcached/file; redis/memcached use an explicit dedicated session.store separate from tenant stores and host store (driver match), Redis connection explicitly named. Unsupported session drivers fail options validation; stateless entry has no session middleware. HttpRegistrar priority: ResolveTenantFromHost before StartSession, ScopeSessionToTenant after it and before errors/auth middleware. StartSession save and session lock release precede end/revert. First account binding invalidates old attributes/auth and rotates token/id; mismatched marker denies. HTTP entry/exit flushes residual Store attributes and forgets auth guards for resident requests, without doing so during nested runFor/jobs. Preserve same-account login. Tests exercise live handlers, central cookie, A→B, same-A, response persistence, resident failure and actual route sorting.
3. **Finding 3 / P07.3:** add kernel SPI Audit Contracts SynchronousAuditWriter, record(AuditEntry, string connectionName): void, and stock DatabaseSynchronousAuditWriter. It writes through core's own AuditLog::saveQuietly с явным uniqueIds/newUniqueId in a committed transaction, requires snapshot/no ambient transaction, bypasses optional auditEnabled and queue, and has no dependency on impersonation or AuditLogger. Tenancy DualImpersonationAuditSink uses this public SPI; no foreign Internal. Central event is committed first, tenant mirror second, then session/login. Claim commits separately and remains consumed on failure. There is no distributed atomic rollback: a central intent survives failed/uncertain tenant commit, login is denied. Event outcome names retain attempt semantics and correlation. Stop clears login before audit; login failure clears session before failed event. New SPI/Golden/CHANGELOG, writer factory and real stock persistence/DI tests belong to P07.3, including disabled general audit and async queue. No-op/queued writer violates SPI.
4. **Finding 4 / P07.4:** preserve unreleased bridge semantics by extending both closed stability sets (package-graph-gate and taxonomy) from four to five values; schema-version remains 1. Manifest-check also validates closed enum and allows unreleased only for access-azguard. Add gate/taxonomy to Files and run both gates in P07.4, with mystery/foreign-unreleased negatives. P09 release exclusion/Q2 remains in force.
5. **Finding 6 / P07.3:** regulated atomic UPDATE requires independent approved_by plus correlated EXISTS root_identities.is_staff=true for approver, separately from claimant staff EXISTS. FK/null/self/non-staff cases and claimant demotion are explicit PG cells; no schema change.
6. **Finding 7 / P07.5:** SqlDigestBuilder accepts nullable WakeupScheduler; factory resolves it only when enabled. Enabled + null fails constructor before writes and RuntimeConfigCheck at boot. Disabled explicit build retains existing frozen-digest semantics and never schedules; tags remain enabled-only. Verify boot/resolve/nonempty build without wakeup installed/bound, disabled spy never resolved, enabled missing fails, enabled scheduler works. DigestBuilder SPI unchanged.
7. **Verification/gates:** execute current Markdown code with installed Laravel and stock baseline where possible, retain hashes/negative controls; use isolated in-memory SQLite only for schema-light persistence proofs, never project PostgreSQL. Existing 27 checks remain and do not replace six-defect proofs. Runtime PG/Redis/memcached/full provider acceptance stays in items. Task writers regenerate state/decisions/status; phase finalize-design and whole-plan finish bind current fingerprint. Grok 4.7/high/500k review is technical, not formal frontier/xhigh design audit. No GREEN admission; P06 closure and actual current design gate precede execution. P08/P09 unchanged.

## Why

The old examples could route tenants to central, preserve central auth, enqueue mandatory audit without durability, fail the stability enum, accept a non-staff approver or require an absent scheduler while disabled. These are concrete feasibility/security failures, not optional architecture preferences.

## Consequences

Specs, dossier, examples, Files, Validation and BC delta include these changes. New kernel audit SPI is an additive public SemVer surface; package schema gains one accepted stability value, no aliases. No product code, DB, release, commit or unrelated worktree changes are authorized by this repair. Formal independent frontier/xhigh admission is still outstanding.
