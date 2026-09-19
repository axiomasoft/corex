# P3 — adapter conformance design, p3-adapter-receipts/1

RAG:— Stable specification; actual producer reports do not exist yet. General practices verified
in P3-design-rag.md. A01–A03/A06/A09 preserved, DB-per-account + boxed separation unchanged.
P3 owns R01–R07/R33 only; R40/R43/R46/R51/R52 consumed as trust/absence invariants, not newly owned.

## Local observations

| Surface | Observed behavior | Bounded selection |
|:--|:--|:--|
| TenantStorage/ScopedFilesystem | disk/ephemeral/capabilities/usage/export; per-call account check, traversal and symlink checks | Preserve signatures; qualify every operation/retained handle. Local-only path() and URL refusal stay explicit |
| ContextTenantStorage | local and fake-s3 both build local adapter; usage true/export false | local supported candidate; fake-s3 remains test-only. Real object provider via optional native adapter, not rename of fake backend |
| ClaimAccountSignup | root_accounts named unique index, advisory xact lock, locked qualified template/pool, same digest replay, bounded retry | Preserve PG claim semantics and schema; no provisioning saga/IdP writer |
| StanclTenancyManager | initialize clears workspace; runFor finally restores prior workspace | Prove two physical DBs and failure/retry teardown; payload workspace never grants authority |
| StrictTransport | Supplied binding/token/context/permission, TLS verify, no redirects, no-store, strict echo/counters; uncertain revoke distinct | Keep typed client port/profile; scoped consumer construction only, no default URI or automatic provider binding |
| PairStore / DurableInbox | Hardcoded disposable tables; pair locks, numeric counters, restore witness; inbox admit select+insert | Qualify then optional approved physical mapping; missing-row race and equal-revision conflict must be covered |
| ReferenceMembershipProjection / issuer | Pre-grant elapsed integers; reference session/remember INSERT under pair commit; unregistered | Existing fixture proof cannot admit real auth driver. Add final-write admission with fresh elapsed samples inside same transaction |
| AccountMenuReader | cache version/result; 300s display TTL; injected click authorizer; no concrete Redis adapter | Supported opt-in cache implementation; durable current version check outside evictable cache, fresh click authority |
| Lifecycle / StanclTenantDatabaseLifecycle | Export writer absent refuses ready; purge requires ready latest export; DROP separated from transaction | Qualify existing hooks and physical export/restore; no new fleet/control-plane engine |

Source SHA observations: artifacts/P3-design/source-observations.json. No runtime tests performed in design.

## R / AC ownership

Original parents and atomic criteria remain lossless in research/01a-acceptance-index.md; actual frozen
mapping from P1 is mandatory at execution. Each receipt enumerates every corresponding atom/subcondition,
including external atoms that P3 does not close. No reduced checklist replaces original B33/B34 programme.

| R | Producer | Cases / obligations | Consumer / gate |
|:--|:--|:--|:--|
| R01 | P3.1 | durable/ephemeral A/B, all path/handle methods, stream lifetime, traversal/symlink, capability/usage/export refusal, real driver distinction | P4/P6 file IO; P9 installed; P10 IAM/backup. G-API/STORAGE/DEPENDENCY for delta |
| R02 | P3.2 | same key/same digest replay, changed digest conflict, unique-index contention, crash rollback/unknown commit/template/pool | P9/P10; G-SCHEMA for actual DDL delta |
| R03 | P3.3 | N1–N4 stable identity, no email/phone merge, root-write denial, principal/workspace mismatch | P9/P10 AP-01; G-CENTRAL |
| R04 | P3.3 | exact read/revoke wire/errors, JWT/mTLS/echo/cache/redirect failures, uncertain reconciliation | P3.4/P3.6/P9/P10; G-API/CENTRAL DI-01–20/34 |
| R05 | P3.4 | uint64/CAS/equal-R digest/G/E/F/AF, admission/takeover/retry/inbox crash, independent witness restore | P3.5/P9/P10 AP-04–07/10; G-SCHEMA/STORAGE |
| R06 | P3.5 | final INSERT/UPDATE/remember/cookie fences+fresh lease, absolute expiry, revoke/regrant/late writes, all-entry absence | P9/P10 AP-08/09/11; G-CLOUD/TIMING |
| R07 | P3.6 | current version/key/TTL/Redis loss/cold read/stale regrant/identity/product negatives; click fresh authorization | P9/P10 exact accounts profile; G-CENTRAL/CLOUD |
| R33 | P3.2 | ready-export hash/size/readability/restore, no export no drop, interrupted export/drop retries, integrity/maintenance refusal | P9/P10 real topology; G-SCHEMA/STORAGE/CLOUD |

## Frozen candidate boundaries

These are concrete internal proposals for future P2 catalogue qualification, not effective authority
or a promise to change accepted public interfaces. Existing public TenantStorage, ProvisioningClaim,
CentralApiClient, PrincipalWorkspaceResolver signatures remain byte-identical. New DTO/classes hidden,
final, strict types, explicit FQN; no new interface outside Contracts. Historical internal interfaces
under Switcher are retained as existing source, never promoted as public Contracts by this design.
Any actual public delta must be revised through G-API/major before adoption. Internal signature/schema/
config changes still receive BC/CHANGELOG and scoped qualification. P2.3 selects exact no-delta when
P1 equivalent proves candidate unnecessary; a material different outcome revises affected design finish.

### Storage candidate

Add hidden CoreX\Tenancy\Storage\ObjectTenantStorage implementing existing TenantStorage with
constructor `(TenantContextResolver $contexts, FilesystemManager $filesystems, array $durableConfig,
string $ephemeralRoot)`; array is sanitized server-only native Laravel S3 config, never request data.
P3.1 allowlists driver=s3, trusted endpoint/bucket/credentials references and forces per-account root
prefix `tenants/<canonical-account-uuid>/`; creates on-demand adapter. Core ScopedFilesystem gains
optional final constructor parameter `bool $localPaths = true`; false disables local symlink probing,
refuses path()/url() rather than exposing raw URL/path, still validates every object key and context.
No public URL/temporary URL feature in this proposal. Existing methods/signatures unchanged.
Object usage/export capabilities false, methods throw StorageCapabilityUnavailable; real usage/export
writer owned externally where required. Ephemeral remains scoped local. Existing local driver unchanged.
Native SDK optional `league/flysystem-aws-s3-v3:^3.0` requires accepted immutable G-DEPENDENCY source;
public local/boxed consumers never require object credentials or that SDK. No composer require in
this design. Synthetic S3 backend never counted as real provider conformance. Production provider
receipt remains P10; P3 actual native adapter integration uses isolated S3-compatible service + real
SDK and reports that evidence class explicitly.

### Physical pair/inbox mapping proposal

Keep hidden PairStore/DurableInbox implementations; constructor adds `string $table` with current
reference name as compatibility default. Identifier allowlist only exact current reference or named
candidate table, not arbitrary SQL. Production construction explicitly selects tnt_membership_pairs /
tnt_membership_inbox on one named tenant connection, never default root DB. Consumer mapping binds
one product per physical account DB; cross-product query denied. No production installation of fixture
tables or credential table names. New rows production default denied=true; first ever grant needs a
fresh applied snapshot. PairState adds `?string $snapshotDigest = null` final constructor property;
PairStore::apply adds final `?string $snapshotDigest = null` parameter, mandatory canonical SHA in
production mode. Snapshot digest canonically hashes identity/account/product/membership ID/state/revision/G, excluding per-read request/timestamps; transport refresh IDs cannot make identical authority conflict. Equal revision with different digest/state/G denies, regressions deny; same snapshot
may renew only a new current operation under current E/F; operation IDs are never reused across E.
Initial reference behavior stays covered; production path has no permissive missing-digest fallback.

Migration proposal `packages/tenancy/database/migrations/2026_09_15_000700_create_membership_adapter_tables.php`
is tenant opt-in only (not auto central migrate); freezes candidate DDL below. Up/down/install mapping
needs G-SCHEMA/G-STORAGE before actual write. Down refuses populated credential/pair/inbox deletion;
quiesce+export+approved restore first, monotone witnesses outside rollback set. Reference fixtures stay
in tests, never provider boot. Account UUID columns are local adapter boundary, no BaseModel ID rewrite.

```sql
CREATE TABLE tnt_membership_pairs (
 account_id uuid NOT NULL, identity_id uuid NOT NULL,
 revision numeric(20,0) NOT NULL DEFAULT 0 CHECK (revision BETWEEN 0 AND 18446744073709551615),
 revoke_generation numeric(20,0) NOT NULL DEFAULT 0 CHECK (revoke_generation BETWEEN 0 AND 18446744073709551615),
 local_epoch numeric(20,0) NOT NULL DEFAULT 0 CHECK (local_epoch BETWEEN 0 AND 18446744073709551615),
 operation_fence numeric(20,0) NOT NULL DEFAULT 0 CHECK (operation_fence BETWEEN 0 AND 18446744073709551615),
 application_fence numeric(20,0) NULL CHECK (application_fence BETWEEN 0 AND 18446744073709551615),
 operation_id uuid NULL, applied_operation_id uuid NULL, barrier_id uuid NULL,
 snapshot_digest char(64) NULL CHECK (snapshot_digest ~ '^[a-f0-9]{64}$'),
 denied boolean NOT NULL DEFAULT true,
 PRIMARY KEY (account_id, identity_id)
);
CREATE TABLE tnt_membership_inbox (
 event_id uuid PRIMARY KEY, logical_digest char(64) NOT NULL CHECK (logical_digest ~ '^[a-f0-9]{64}$'),
 delivery_id uuid NOT NULL, status text NOT NULL CHECK (status IN ('pending','acknowledged')),
 attempts bigint NOT NULL DEFAULT 0 CHECK (attempts >= 0),
 owner_id uuid NULL, lease_until bigint NULL
);
CREATE INDEX tnt_membership_inbox_pending_idx ON tnt_membership_inbox (lease_until,event_id) WHERE status='pending';
```

Inbox admit uses INSERT ON CONFLICT + locked canonical logical digest reconciliation; concurrent
first insertion must not escape as unhandled duplicate or acknowledge unprocessed work. Claim caller
supplies fresh owner UUID per attempt, DB admission time not untrusted message wall time; expiry/
owner equality checked on acknowledge, failed ack leaves durable retry. Pull outside transaction;
ack only after fresh authority applied. Cleanup retains tombstone/high-watermark until accepted
retention/witness receipt, cannot delete counters then treat missing as new grant. Witness import
always denies and clears active operations/AF before fresh pull; old operations cleared on restore, including operation_id/applied_operation_id/barrier_id; increment local epoch on restored denial without decreasing witness E/F. Same event replay must not reuse stale owner; acknowledgement verifies owner UUID and current database lease. Epoch overflow stays denied.

### Final-write admission candidate

New hidden CoreX\Tenancy\Membership\Internal\Credentials\FinalWriteAdmission:
constructor `(ConnectionInterface $connection, PairStore $pairs, Closure $clock,
int $remainingCommitUpperSeconds)`; clock signature `Closure(): ClockReading` from existing Timing.
Method `write(PairKey $key, string $operationId, ClockReading $readStarted,
string $revokeGeneration, string $localEpoch, Closure $write): void`;
write callback signature `Closure(ConnectionInterface): void`, performs actual INSERT/UPDATE on
same physical connection. No network/external DB/cookie in callback. PairStore adds hidden `isFor(ConnectionInterface $connection): bool` with strict object-identity equality; constructor denies mismatch. It locks pair first, checks
current applied operation/F/AF/G/E and denied=false, samples clock after lock wait, computes conservative
Elapsed + commit upper budget against Lease. Runs credential SQL then resamples before commit;
expired/domain-lost/overflow/stale/rollback → CredentialIssuanceDenied and no committed write.
DB statement/lock/transaction timeout + platform/commit upper-bound receipt required for supported
mode; unqualified clock/budget throws, never uses wall-clock optimistic fallback. Callback cannot
create positive authority or silently use other connection. Production driver calls this at each
final save, including UPDATE, restoration and remember material. Existing FencedCredentialIssuer
reference remains reference; composed production path uses FinalWriteAdmission, not fixture tables.

Pair operations/projection/expiry share lock order pair→credentials; a held row lock does not stop
elapsed expiry, so failed fresh sample/budget aborts even if no concurrent revoke can acquire lock.
An expiry barrier that commits first advances E/F, clears AF and denies; admitted earlier save that
commits first is invalidated on subsequent barrier. Subsequent authentication checks current lease/G/E,
not mere presence of session. Cookies publish only after DB commit and current admission; stale output
cannot authorize. Restored/remember/PAT/OAuth/SSO/hydration/impersonation and callbacks either have
same final boundary or explicit unsupported/no registration. Generic library cannot promise to guard
arbitrary consumer Laravel driver; P10 inventories actual driver/route entry Files and wiring.
Clock domain/suspend/cross-host qualification and FE-60 physical cleanup are P10, not local timings.

### Switcher candidates

New hidden CoreX\Tenancy\Membership\Internal\Switcher\RedisAccountMenuCache implements
existing AccountMenuCache; constructor `(Illuminate\Contracts\Cache\Repository $cache,
Closure $versionReader, string $namespace)` with versionReader `Closure(AccountsQuery): ?string`.
versionFor reads authoritative current list version from injected source, never Redis-only head.
Repository chosen by trusted composition (Redis in supported mode); namespace nonempty.
Key = namespace + SHA256(canonical identity/product/version); TTL upper bound existing 300s;
result bytes strict typed AccountsResult roundtrip, only same context/version accepted. Cache errors
are misses/unavailable, never grants; regrant cannot revive stale version, cached denial not authority.
New hidden StrictAccountsTransport implements existing AccountsReadTransport; constructor
`(StrictTransport $transport, string $accountsUri)`; URI only from negotiated accepted profile.
StrictTransport gains internal `accountsFor(AccountsQuery $query, string $accountsUri): AccountsResult`
with same JWT/mTLS/context/no-store/error validation, exact list envelope candidate below, qualified by P2.2 target proposal before adoption.
No invented list server endpoint; unsupported/unknown wire mapping stays unavailable. Consumer owns
AccountClickAuthorizer, fresh CentralApiClient membership check mandatory; menu presence never authority.
P3.6 binding is explicit consumer construction, no default runtime registration or role writer.

## Receipt contract

Each future findings/P3-*.md report declares `p3-adapter-receipts/1`; ## Cases table:
`Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume`.
Every actual evidence uses research/03-gates.md envelope (SHA/runtime/cwd/environment/log/outcome),
no placeholder PASS. Results PASS/FAIL/UNSUPPORTED/PENDING; required local conformance is all PASS,
UNSUPPORTED only explicit excluded capability or unregistered entrance with negative proof; required
real-provider/deployment rows remain PENDING with owner/resume and cannot be counted as covered.
At least document archive + laboratory independent scenarios per adapter; P9 replays installed cohort.
## Inputs: `Path | SHA256 | Role`; exact declared source/test/config + consumed producer reports.
## Adoption: `Surface | Baseline/proposal digest | Local supported mode | Protected receipt | Registration | Remaining P10 gate`.
No-delta qualifies exact preserved source, not missing implementation. Accepted equivalents replace
only identical observable candidate after P2 catalogue qualification. All G gates and 34 DI/13 AP
remain in ledger; library local PASS never increments deployed counters.

P3.7 produces findings/P3-adapter-receipts.md with ## Requirements table
`R | Items | Covered local cases | Installed contract | Pending effective conditions | Owner | Resume`;
exact R01–R07/R33 once each; 0 lost atomic parent links. ## Producer edges table
`Producer | Contract/version | Consumer | Required receipt | Failure model`, explicitly P4/P6/P9/P10/P11.
It seals all six branch reports plus P2 catalogue, design, phase specs and gate ledger; self/journal/state
excluded. Source delta and Files ∩ digest-input collision separated before product/closure as required.
Seal/report GREEN = complete exact local adapter proofs, pending effective gates explicit. It is not
G-RELEASE/G-CONSUMER, full B33/B34 acceptance or phase audit admission.


## Accounts wire candidate / consumer profile qualification

The proposed internal transport uses authenticated GET accepted accountsUri, query fields
`identity_id`, `product`, fresh `request_id` canonical UUID, profile header existing corex-membership/1,
capability `accounts.read`; binding trusted account/product comes from server composition, never a
client list row. Exact accepted URI/issuer/resource/registration remain external G-CENTRAL request,
no guessed defaults. Profile extension versus old baseline requires scoped API/BC decision; if target
rejects this candidate revise owning design/catalogue/finish before runtime, not auto-negotiate.
Response HTTP200 JSON/no-store with no Age/ETag/Last-Modified/redirect, exact fields below:

```json
{"profile_version":"corex-membership/1","request_id":"<canonical UUID>","identity_id":"<canonical UUID>","product":"<validated product>","membership_version":"<nonempty opaque string>","observed_at":"<UTC RFC3339 timestamp>","accounts":[{"account_id":"<canonical UUID>","name":"<nonempty string>","slug":"<validated slug>","primary_host":"<validated DNS host>","status":"<nonempty display string>"}]}
```

Angle-bracket values are schema variables, not effective registration. Missing/unknown/duplicate keys,
wrong types/echo/product/UUID/host, duplicate account IDs, invalid UTC or unsupported profile deny.
AccountsResult/AccountListEntry existing constructor FQNs preserved; parsing verifies correlation
before constructing result, no new public DTO/field. HTTP401/403 → CentralUnauthorized, unavailable/
429/5xx → CentralUnavailable, malformed/cache/unknown → CentralProtocolViolation. Menu catches as
unavailable and never grants. versionReader fetches authenticated current membership_version using
same accounts read, no Redis-only pointer; no assumed version monotonic numeric order. Concurrent
version change can affect display only; click always fresh authority. One fetch per cold read can be
reused within same scoped request, no cross-job source caching.

## Seal inputs

P3.7 ## Sealed inputs pins exactly all rows below after branch report finalization. Per-branch reports
add their own affected source/test/config/G proof hashes; those reports recursively pin exact product
without globally invalidating unrelated branch receipts.

| Path | Role |
|:--|:--|
| findings/P3-storage-context.md | R01 physical/storage conformance |
| findings/P3-claim-maintenance.md | R02/R33 claim/export proof |
| findings/P3-identity-transport.md | R03/R04 identity/wire proof |
| findings/P3-durable-projection.md | R05 physical pair/inbox/witness |
| findings/P3-final-write.md | R06 final SQL/lease proof |
| findings/P3-account-list.md | R07 native Redis/list proof |
| findings/P2-contract-catalogue.md | Actual accepted proposal catalogue |
| research/P3-adapter-design.md | Frozen exact adapter choices |
| research/03-gates.md | All G/34 DI/13 AP obligations |
| research/05-integrated-design.md | Compatible downstream contracts |
| decisions/D8.md | P3 bounded design decision |
| phases/P3/P3.md | Phase scope/assurance/routing |
| phases/P3/P3.1.md | Storage item |
| phases/P3/P3.2.md | Claim/lifecycle item |
| phases/P3/P3.3.md | Identity transport item |
| phases/P3/P3.4.md | Durable store item |
| phases/P3/P3.5.md | Final write item |
| phases/P3/P3.6.md | List/cache item |
| phases/P3/P3.7.md | Seal item |


## Required local case IDs

Branch ## Cases must contain each following ID exactly once with Result PASS and real corresponding
local proof; additional cases retain original atomic obligations. Mandatory object/SQL/Redis cases
cannot be UNSUPPORTED/PENDING to close local branch. Other effective provider/DI/AP cases explicitly
PENDING with owner/resume, not part of local PASS substitution. Case IDs describe conformance, not
implementation-shaped test assertions; one executed case may cover multiple original atomic IDs.

| Report | Required Case IDs |
|:--|:--|
| findings/P3-storage-context.md | storage-all-methods; retained-handle-context; traversal-symlink; capability-refusal; native-object-sdk-isolation; physical-worker-reset; archive-lab-storage |
| findings/P3-claim-maintenance.md | claim-concurrent-key; claim-digest-conflict; claim-uncertain-replay; template-pool-deny; export-before-drop; interrupted-export-drop-restore; archive-lab-maintenance |
| findings/P3-identity-transport.md | identity-n1-n4; identity-no-merge-root-write; jwt-mtls-negative; response-wire-cache-negative; revoke-uncertain-reconcile; transport-worker-reset; archive-lab-identity |
| findings/P3-durable-projection.md | counters-overflow-cas; equal-revision-conflict; stale-fence-takeover; concurrent-inbox-retry; invalid-trigger-no-write; independent-witness-restore; populated-migration-refusal |
| findings/P3-final-write.md | final-insert-update-revoke-regrant; lock-held-lease-expiry; clock-domain-loss-deny; remember-cookie-rollback; all-entry-enforced-or-absent; absolute-session-boundary; archive-lab-final-write |
| findings/P3-account-list.md | redis-cold-loss; context-version-key-poison; revoke-regrant-stale-list; fresh-click-deny; accounts-wire-mtls-negative; cache-ttl-unavailable; archive-lab-account-list |
