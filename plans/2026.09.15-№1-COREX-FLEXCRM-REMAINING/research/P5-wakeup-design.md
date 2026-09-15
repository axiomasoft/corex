# P5 — Frozen workflow binding / shared wakeup candidate

RAG:— Local proposal, plan-designer/gpt-5.6-sol/high. This document owns exact candidate
signatures, persistence and failure semantics consumed by P2.3. It grants no adoption authority.
Actual P1 equivalents, P2 catalogue and scoped source/schema/public approvals precede runtime.
Standalone engines remain independently owned; their internal correctness is not inferred from a binding.

## Baseline and placement

Committed `Extend\Workflow` already declares stepType/trigger. `ModuleRegistry::compiled()` and
`ManifestListeners` already expose compiled contributions and module-active dispatch; `DomainEvent`
already supplies eventId/occurredAt/tenant. Existing `TenantContext` is a value object, not an
unforgeable authority token: account/connection comes from trusted resolver, never decoded payload.
Existing membership ExpiryScheduler is fixture-only pair invalidation, not a durable timer service.
The package tree currently has no workflow binding/wakeup utility; this observation is not a
proof that all equivalent behavior is absent. P1/P2 qualify equivalents before any new implementation.

Choose optional `corex/wakeup` (core only) for SQL scheduled requests/shared sweep; optional
`corex/workflow` (core + modules + wakeup) for thin engine/FSM integration. Root hint/source routing
adapter belongs to tenancy, opt-in with wakeup installed; wakeup has no tenancy dependency. No
core→sibling dependency, no workflow dependency for notifications. No AzGuard/Filament/private
source requirement on headless boot. Engine/FSM adapter supplied by the independently owned
integration package through DI; its real package/ref/FQN and exact upstream method mapping are
qualified by P5.1, not invented here. Binding exposes no engine implementation or alternate graph
interpreter, signal matcher, SEL evaluator, transition topology/guard/policy implementation.

B12's product-prefixed standalone name, hard upward dependency in its illustrative binding,
engine-specific root table and Filament UI are not adopted as L0 API/DDL. D10 records this bounded
placement/schema proposal; source specs untouched. No ninth extender/new event bus/new registry.
Root table migration only after approved old→new mapping/export/rollback; never silently alias
or rename an existing engine/root table. UI and product permissions remain original external owners.

## R / AC ownership

| R | Producer | Cases / obligations | Consumer / gate |
|:--|:--|:--|:--|
| R09 | P5.1, P5.4, P5.6 | B12 AC29 standalone independent install and upstream owned suite; binding slice AC20/28/22; engine AC2–11/16–19/23 correctness remains upstream; UI/drafts/AI/product quotas/retention remain R31/external | P7 task integration, P8 contribution metadata, P9 immutable engine/source; G-DEPENDENCY/G-API |
| R10 | P5.2, P5.3, P5.5, P5.6 | B12 AC9/14/15 durable/local/after-commit slices; AC4/5/6/19 integration delegated to qualified engine; AC1 root generic schema proposal; AC13 actual cloud latency separate P10 | P4.6 exact digest seam; P7 declared workflow only; P9/P10; G-SCHEMA/G-CLOUD/G-TIMING |

All 30 B12 parent criteria retained in acceptance index. No binding GREEN claims full B12 engine,
Studio/approval/AI/product/load/retention acceptance. Preserve AC13 p95≤30s at 10% due accounts,
5–15s root tick, 5–30s boxed tick and 5–15min reconciliation as distinct required measured conditions;
any separate p99 target is a separate P10 row, never replace p95/population with it.

## Exact wakeup public candidate — wakeup/1

Paths `packages/wakeup/src/Contracts/{WakeupScheduler,WakeupHandler,DueSource,WakeupHint,WakeupSweeper}.php`;
namespace `CoreX\Wakeup\Contracts`. Five interfaces only, justified respectively by durable
producer, digest/reminder handler, engine/local source and local/root routing and shared boxed/physical runner seams. DTOs final
readonly in `CoreX\Wakeup\Data`; implementation classes internal/final, no public concrete SPI.

```php
interface WakeupScheduler {
    public function schedule(WakeupRequest $request): ScheduleResult;
    public function cancel(TenantContext $context, string $requestKey): CancelResult;
}
interface WakeupHandler {
    public function handle(WakeupDelivery $delivery): void;
}
interface DueSource {
    public function nextDue(TenantContext $context): ?DateTimeImmutable;
    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult;
}
interface WakeupSweeper {
    public function sweep(TenantContext $context, int $limit): SweepResult;
    public function nextDue(TenantContext $context): ?DateTimeImmutable;
}
interface WakeupHint {
    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void;
}
```

`WakeupRequest(context: TenantContext, kind: string, requestKey: string, fireAt: DateTimeImmutable,
window: ?WakeupWindow, payload: array)`; `WakeupWindow(start: DateTimeImmutable,end: DateTimeImmutable,
period: string)` supports period `hourly|daily|custom`. UTC instants only, start<end, half-open,
fireAt≥window.end when window supplied; closed-window catch-up allowed. No cron/parser/recurrence
policy in this utility. `WakeupDelivery` same immutable request plus integer generation and attempt;
trusted current context re-resolved before handler. Payload canonical scalar/null/nested arrays only,
≤64KiB serialized, depth≤8; sorted object keys/list order preserved, no floats/nonfinite/object/resource; kind≤64 allowlisted code, requestKey≤255 UTF-8, no class/SQL/connection
selector. ISO UTC microsecond wire encoding. RequestKey canonical SHA256 of schema version +
length-prefixed account/workspace/kind/logical occurrence ID/window bounds. Full request digest
also binds fireAt/payload. Neither key nor payload grants authority.

ScheduleResult is final readonly DTO with status `created|existing|conflict` and requestKey;
exact duplicate is existing, same scoped key/different immutable digest conflict. CancelResult status
`cancelled|already-completed|already-cancelled|not-found`; cancellation cannot retract committed
handler effect. SweepResult counts `claimed/completed/retried/failed/skipped`, remainingDue boolean and nextFireAt nullable UTC;
counts are local observations, not cloud throughput/latency proof. Invalid/unknown kind/schema or
cross-context request rejects before write. UTC test clock internal callable injection, no new global
clock/security timing contract. All external qualified adapters use wakeup/1 conformance suite.

## SQL authoritative requests / local_sweep

Proposed `sys_wakeup_requests` per physical account DB; boxed same single configured connection.
UUIDv7 app ID, normalized nonnullable workspace_scope_key (`account` or `workspace:<id>`), kind,
request_key, request_digest, immutable UTC fire_at/window_start/window_end/period/payload JSON,
status `pending|running|completed|cancelled|failed`, generation bigint≥0, attempt≥0,
lease_until nullable, retry_at nullable, failure_code nullable, created_at/updated_at/completed_at.
UNIQUE(workspace_scope_key,kind,request_key); requestKey globally kind-qualified for cancel lookup;
CHECK window both null or start<end; CHECK status/lease consistency; index(status,retry_at,fire_at).
Native PG16 and SQLite schema equivalents explicitly tested, no nullable UNIQUE assumption.
Known terminal key digest/tombstone retained; no GC that recreates duplicate logical occurrence.
Retention/product erasure selection is owner scope, no automatic timer hard delete in v1.

Schedule executes INSERT in current trusted account connection/producer transaction; outer rollback
leaves no request. Scheduling without outer transaction commits its own write. After-commit hint
only best-effort; persist-first/polling closes dispatch crash gap. Local hint is no-op. Shared
`Internal\SweepRunner` iterates DI-tagged `DueSource`s by code allowlist (SQL requests and selected
engine source), bounded per-source limit/time and round-robin cursor; no graph-specific/feature-specific
scheduler. One `corex:wakeup:sweep --limit=100` command, current boxed/explicit trusted operator
context only; root local processes use their trusted own database and no recursive root registry.
Application schedules this command every 10 seconds; configurable 5–30s supported, no process
resident state required. Tenancy routing invokes exactly the same runner for chosen account.

Claim due pending rows in ordered bounded transaction with PG FOR UPDATE SKIP LOCKED; increment
attempt+generation and set running/lease_until (default 60s). SQLite serial equivalent; PG race proof
needs real independent sessions. Worker reloads/locks request, checks current generation/status,
DB fresh UTC now/lease AFTER lock wait, current handler/module/context admission. Handler performs
bounded local SQL work in the SAME connection/transaction while row lock held; completion CAS
matches generation and commits atomically with effects. Exception rolls back effects and completion;
known transient failure gets retry_at exponential capped 300s, max 5 attempts, then failed with
visible code. Scheduler canonical requestKey validation mandatory; UNIQUE(workspace_scope_key,request_key) additionally prevents cross-kind cancel ambiguity. Unknown exception never reported completed. Recovery claims expired running under
same lock and bumps generation; resumed stale worker cannot mutate or complete. DB commit response
unknown reconciles persisted generation/status and handler's durable writes; never blind duplicate IO.

WakeupHandler must not perform external IO; local reminder/digest handler creates durable outbox
intent and returns. External send belongs to existing channel/engine, with explicit stable key,
unknown-outcome and provider-specific dedup horizon. No exactly-once external effect promise.
Cancel locks same request: if wins before handler admission effect prevented, if waits behind admitted
handler committed effect remains. Authorization/preference revocation requiring same serialization
must lock consumer state in documented order; timer lease alone is not authorization. In local handler,
request→recipient→source→digest run/items lock order; never acquire request from a transaction already
holding recipient/source locks. P4 digest production schedule after releasing such locks or before
acquiring them; tests demonstrate no reverse acquisition deadlock.

## Root routing hint — routing-hint/1

`CoreX\Tenancy\Wakeup\Internal\RootRegistryWakeupHint` implements WakeupHint; opt-in
`corex-tenancy.wakeup.driver=local_sweep|root_registry`, default local_sweep. Same runner,
trusted AccountConnectionResolver; no body-selected account, DSN or root credentials on boxed.
Approved neutral `root_wakeup_hints` mapping: existing account key type preserved (no global UUID
migration), next_fire_at UTC, hint_revision bigint≥1, leased_until nullable, claim_epoch bigint≥0,
created/updated. UNIQUE account key; indexes on next_fire_at and leased_until. Foreign account
mapping comes from actual approved tenancy schema, not assumed root_accounts name/FK.

After tenant commit upsert next_fire_at=LEAST(old,new) and increment hint_revision EVERY time,
including equal/later hint, to defeat concurrent-hint deletion race. Root adapter calls WakeupSweeper port (same runner binding), never imports hidden implementation. Root claim bumps claim_epoch,
returns (account,revision,epoch); calls trusted account runner, then deletes/advances hint only by
CAS of BOTH claimed revision and epoch. A concurrent tenant hint always survives. Before installing
next minimum, recompute all registered sources; stale minimum is early/spurious, never authority.
Root outage logs bounded best-effort failure; no failure of authoritative tenant schedule. Cancel
never depends on root hint deletion. Crashed root worker lease expires; reconciliation enumerates
actual approved eligible-account catalogue (policy supplied by operator), sweeps EVERY included
account/source in bounded pages at 5–15min cadence with persisted cursor/error retry. No silent
SKIP LOCKED starvation/drop; proof includes >100 accounts and continuously locked hints.
Root tick 10s within 5–15s config; fleet rate/quota/cell policy, daemon deployment/IAM and measured
coverage/latency owned P10, not central scheduler implementation in P5.

## External engines / engine-binding/1

P5.1 produces immutable `findings/P5-engine-qualification.md` engine-qualification/1:
actual independently owned engine and FSM package names, exact source/dist refs/hashes/license,
composer lock/dependency closure and runtime matrix, upstream suite commands/logs, actual upstream
port/event/FQN mapping, transaction/context entry, idempotency/replay/expiry/version constraints,
due-source sweep method/hint callback, exact supported disable-own-scheduler switch for binding installs, owner/resume. No mutable latest/dev alias. Proposed engine
baseline B12 definition-as-data/checkpoints, immutable published version, buffered signals and
synchronous FSM remains; Temporal generator replay is an alternative, not silently chosen.
Engine and FSM require clean Laravel install with ZERO corex/* dependency; engine's product-prefixed
legacy vendor is not an allowed corex/* dependency. Owner-provided agnostic integration/engine
artifact must prove actual dependency graph/literal gate. No source copying/renaming as shortcut. Standalone clean install keeps its own default sweep; binding install disables upstream auto-scheduler by qualified supported option and registers its DueSource with ONE CoreX runner. If no safe upstream opt-out, required shared-runner acceptance remains unsatisfied, not silently two scheduler paths.
If immutable source or required capability is absent, exact source owner receipt remains prerequisite
for P5.4 and full P5/P9 R09 acceptance, while P5.2/P5.3/P5.5 can proceed. Qualification cannot mark
unsupported required engine behavior PASS or provide full-engine acceptance by fake adapter.

New `CoreX\Workflow\Contracts\WorkflowBinding` in packages/workflow/src/Contracts/WorkflowBinding.php:
`capture(TriggerRequest $request): CaptureResult`, `signal(SignalRequest $request): SignalResult`,
`fire(TransitionRequest $request): TransitionResult`. Integration port
`CoreX\Workflow\Contracts\EngineAdapter`: `start(TriggerRequest $request): StartResult`,
`signal(SignalRequest $request): SignalResult`, `fire(TransitionRequest $request): TransitionResult`;
selected independent adapter implements it, plus `DueSource`/`WakeupHint` mapping if needed.
No method signature copied from unknown upstream library. Concrete map frozen by qualified receipt
BEFORE use; unsupported capability typed refuses, never falls back to own interpreter/matcher.

All final readonly DTOs in CoreX\Workflow\Data. TriggerRequest(trusted context, module,entityHandle,
eventId,eventName,subjectRef,definitionRef,versionRef,scalarSnapshot,requestKey); immutable version
reference comes from accepted engine current-published lookup BEFORE intent capture, stored forever.
SignalRequest(context,name,correlationKey,payload,requestKey,expiresAt); TransitionRequest(context,
subjectRef,edge,actorRef,payload,requestKey). Refs validated bounded strings, context/body mismatch
rejects; actor current authorization via consumer policy/engine, no built-in role writer.
CaptureResult status created|existing|conflict; StartResult started|existing|guard-rejected|unknown
with nullable instanceRef/error code; SignalResult buffered|consumed|expired|existing|unknown;
TransitionResult completed|guard-rejected|existing|unknown. Same key binds exact immutable digest;
unknown resolves by qualified engine operation lookup or remains unknown, no blind retry with
new key. GuardRejected nonretryable, no direct status UPDATE or bypass Guard/Policy/Action.

Thin binding reads existing compiled Workflow step/trigger declarations/manifest listeners; rechecks
active module, entity/event/action allowlist, current tenant context and current authorization at
capture and effect admission. No arbitrary class name from client; no persistently registered
subscription/event bus in CoreX. Sole `sys_workflow_intents` inbox/outbox local table with unique
scoped requestKey, immutable digest/pinned refs/status/attempt/generation/unknown and timestamps.
Capture called by producer in same DB transaction as domain write; after-commit DomainEvent listener
may accelerate lookup, cannot guarantee delivery of an intent never persisted. Event-only adapter
explicit best-effort and cannot satisfy durable trigger acceptance. Independent producers may use
durable upstream event source; required replay evidence before acceptance. P7 task producer must
persist command result+capture before commit; unowned product event producers are external owner.
Engine start same transaction OR engine operation-key dedup/lookup across separate transaction;
capability proof required by qualification. Binding worker relays pending intents via shared due
source/sweep; no second feature-specific schedule. Disable module cancels pending admission,
already running engine instances follow engine policy; no implicit cancellation/graph migration.

Signal-before-wait and simultaneous subscribe/signal are tested against ACTUAL qualified engine:
engine owns atomic matching lock/order and buffer expiry; forwarding same key may be retried only
with proved engine dedup/lookup. Two separate locked tables by themselves do not prove absence
of missed wakeup: must inspect upstream serialization point and execute pause/interleave tests.
Timer/timeout versus signal resolved once by engine CAS, cancelled/expired cannot revive. FSM
emits completed transition only after successful committed guard/policy/action; rollback emits no
trigger. Transition operation-key receipt and single-write-path proof mandatory. SEL hardened
validation/evaluation, no object context/method calls/compile and pinned version tests are upstream
proofs; binding scalar snapshot is an extra boundary, not substitute certification.

## Exact P4.6 digest bridge

Producer utility P5.2: packages/wakeup/src/Contracts/WakeupScheduler.php wakeup/1 and
Internal/SweepRunner.php, boxed actual receipt findings/P5-local-wakeup.md. Consumer bridge P5.5:
packages/notifications/src/Internal/Digest/DigestWakeupAdapter.php implementing WakeupHandler,
kind `notification.digest.v1`, config opt-in `corex-notifications.digest.enabled=false` by default.
Constructor-injected typed local closure invokes the proposed `DigestWakeupHandler::__invoke(DigestTick $tick): void` using
`DigestTick(tenant: trusted current TenantContext, window: explicit WakeupWindow,
requestKey: delivery.requestKey)`. Tick/window field mapping checked against P4 stable candidate;
no graph engine import, no root/Redis/tenancy/private source. Disabled digest doesn't resolve port.

Bridge requests via WakeupScheduler::schedule(request: WakeupRequest(...)); default windows explicit
hourly UTC hour/daily UTC day, fireAt=window.end; supplied timezone/DST windows converted by owner
before request, no cron recurrence here. First durable digest intent/upcoming window creates request
in SAME account transaction subject to lock order; run for closed window idempotently reuses same
key while residual/new items are discovered by DIG producer's own durable source. Persistent
`ntf_digest_items/runs` are authoritative for catch-up: P4.6 notifications registers a DueSource which
scans remaining closed-window items/runs, invokes same bridge/handler with canonical key and polls
pending revision/batch until quiescent. No completed sys_wakeup row reinsert or completed-key mutation
for late arrivals. This source is a SQL discovery adapter, not a new scheduler; uses same runner,
round-robin budgets and own receipt. Restart rebuilds from rows, not now-only volatile clock.
P5.5 owns adapter+DigestTick DTO and tests actual glue with real P4.4/P4.5 SQL outbox and a neutral transactional fixture handler. P4.6 owns actual DigestWakeupHandler implementation, provider closure wiring and notification DueSource catch-up. The fixture proves wakeup/1 mapping/local recovery, not full DIG acceptance;
P4.6 owns full DIG-01–07 implementation. P5.5 DOES NOT depend on P4.6 or P4.8 seal. P4.6 then reads
P5.5 seal. Thus P2→P5.2 + P4.4/5→P5.5→P4.6→P4.8; no P4/P5 phase-level execution cycle.

## Required local case IDs

| Report | Required Case IDs |
|:--|:--|
| findings/P5-engine-qualification.md | ENG-01; ENG-02; ENG-03; ENG-04; ENG-05 |
| findings/P5-local-wakeup.md | WAK-01; WAK-02; WAK-03; WAK-04; WAK-05; WAK-06; WAK-07; WAK-08 |
| findings/P5-root-routing.md | ROOT-01; ROOT-02; ROOT-03; ROOT-04; ROOT-05; ROOT-06 |
| findings/P5-workflow-binding.md | BIND-01; BIND-02; BIND-03; BIND-04; BIND-05; BIND-06; BIND-07; BIND-08 |
| findings/P5-digest-bridge.md | BRIDGE-01; BRIDGE-02; BRIDGE-03; BRIDGE-04; BRIDGE-05 |

## Local case invariants

| Case | Required observed behavior |
|:--|:--|
| ENG-01 | Engine/FSM clean install without corex; immutable lock/dist/license/closure and independent upstream suite actual logs |
| ENG-02 | Exact ABI/FQN/context/transaction/event/hint/due-source map and explicit unsupported refuses, no renamed/copied engine |
| ENG-03 | Actual upstream immutable version/pinned instance and hardened expression suite; no binding-only certification |
| ENG-04 | Actual engine CAS duplicate/worker crash/restart+Redis wipe/wait-before-signal/interleaving/expiry receipt |
| ENG-05 | Actual FSM guard/policy/action/single-write/transition-key unknown lookup; public source/compatibility owner |
| WAK-01 | Same-transaction schedule rollback/no hint and exact duplicate/key conflict/context forged/unknown handler reject |
| WAK-02 | Boxed SQL restart/Redis absence/no root boot; two tenant DBs/workspace scope can't cross |
| WAK-03 | Real simultaneous PG claims and completion effects once; >100 residual/locked source fair next sweep |
| WAK-04 | Kill after claim/before commit, rollback local effects; expiry bumps epoch, resumed stale worker cannot write |
| WAK-05 | Handler exception/known retries/max exhausted visible failed; unknown DB commit reconciled; no IO in handler |
| WAK-06 | Cancel-before-admission wins; cancel-after-completion explicit outcome; current authorization/state lock order |
| WAK-07 | Half-open UTC/end boundary/custom DST 23h/25h supplied windows; stable key/digest; late discovery no key mutation |
| WAK-08 | Fresh time after lock wait/lease check, source fair cursor/context reset between tenants/disabled provider no imports |
| ROOT-01 | Root hint only after commit; LEAST plus every-upsert revision, outage doesn't lose tenant authoritative request |
| ROOT-02 | Concurrent hint while root sweep deletes/advances: revision+epoch CAS preserves new hint |
| ROOT-03 | Root claim crash/expiry/reclaim; stale root worker cannot delete hint; spurious/cancelled hint harmless |
| ROOT-04 | Hint table deleted/lost: every approved account/source reconciled with >100/cursor/locked rows, no Redis need |
| ROOT-05 | Same runner boxed/physical/root-local; no recursive registry/body DSN/cross-context; absent optional package boot |
| ROOT-06 | Approved root schema mapping/old-new/rollback/export/capacity probes; local measurements do not assert live AC13 |
| BIND-01 | Existing compiled Workflow declarations used; duplicate listener/module off/disallowed event/class/context denied |
| BIND-02 | Domain write+intent capture atomic rollback and kill after commit before dispatch recovered by shared sweep |
| BIND-03 | Duplicate replay/exact digest conflict/version pin stable; actual engine dedup/unknown lookup keeps one logical start |
| BIND-04 | Signal before wait AND concurrent entry/signal and timeout race against actual qualified engine; one outcome/expiry |
| BIND-05 | GuardRejected nonretryable no status write/no trigger; committed FSM completed transition starts once |
| BIND-06 | Engine action retries/checkpoint restart delegates; bound stable key and scalar snapshot deny object/class/expression bypass |
| BIND-07 | Local/physical/engine source uses same runner/hint path; no cloned matcher/FSM/interpreter or upward dependency |
| BIND-08 | Unknown engine/FSM outcome durable/reconciled; no blind retry/new key; current module/context/authorization admission |
| BRIDGE-01 | Exact WakeupRequest→WakeupDelivery→DigestTick context/window/key mapping; no workflow/root/Redis on boxed |
| BRIDGE-02 | Request+digest intent atomic rollback/restart; pending closed-window discovery resumes after sys request completion |
| BRIDGE-03 | Simultaneous tick/cancel/preference change, prescribed lock order, no stale admitted digest content/new-key blind send |
| BRIDGE-04 | Hourly/daily/explicit DST half-open windows/>100 residual/late arrivals use stable key/source catch-up |
| BRIDGE-05 | Digest disabled boot resolves no utility/engine; independent neutral reminder consumes same API |

## Reports / immutable installed seal

Branch schema `p5-wakeup-receipts/1`. Each report has `## Cases` table:
Case / R/AC / Adapter/profile / Given/fault / Expected / Actual evidence / Result / Limit/owner/resume.
PASS requires exact command+cold/warm environment+source/test/config/runtime hashes+log SHA256,
Expected/Actual outcome and case-specific proof; structural checker cannot validate semantics.
Other labels FAIL/UNSUPPORTED/PENDING; required case not PASS keeps branch incomplete.
`## Inputs`: Path / SHA256 / Role; `## Adoption`: Surface / Proposal hash / Protected receipt /
Supported local adapter / Installed contract / External owner/resume. Relative repository-contained
inputs only. Engine upstream actual source/output locked in delivery fixture, no foreign/tmp path in bundle.

P5.6 findings/P5-wakeup-receipts.md same schema, `## Requirements`: R / Items / Local case receipts /
Installed receipts / Pending effective gates / Owner / Resume, exact R09/R10 rows. `## Producer edges`:
Producer / Contract/version / Consumer / Required receipt / Failure model. `## Sealed inputs`: Path /
SHA256, all five branch reports, P1 lock/map, P2 catalogue/proposals, P5 stable specs/index/design/RAG,
D10, gates/integrated design, immutable installation receipt/manifests/logs. Installed independent
archive-approval and equipment-reminder consumers: cold dist no symlinks/shared vendor, boxed base
wakeup without engine, qualified engine/FSM with binding, separate physical DBs and root-loss replay.
Local engine timer+generic reminder same source runner, ZERO feature-specific schedules; disabled
notification digest and absent tenancy/auth imports checked. PHP8.3/L12 and PHP8.5/L13 boxed declared
probes; physical tenancy PHP8.4/8.5 L12/13 follows actual supported matrix. Dev test-runner/runtime
constraints separate. P9 owns full declared matrix/current hosted runner/release, P10 real deployment.

## Cross-phase impact and pre-mortem

P2 consumes this stable document, no execution reverse edge. Engine qualification input→binding only,
not pure utility. Root adapter depends P5.2+approved P3 physical context, not membership source seal.
Digest bridge depends P5.2+P4.4/P4.5, not P4.6; P4.6 consumes actual P5.5 report. P5 final seal can wait
binding/root while P4 digest and P7 pure utilities proceed. P8 only included metadata, P9 actual all
included seals/source cohort, P10 cloud reconciliation/AC13 windows, P11 released consumer pins.
No original R09/10 or product parent AC silently deleted/reclassified to acceptance.

| Failure | Mitigation/check | Owner |
|:--|:--|:--|
| External engine unknown/mutable/forbidden upward dependency | Actual ENG qualification prerequisite, no clone or fake FQN; independently deliver utility | Standalone source / P5.1 |
| afterCommit-only lost domain event | Same-transaction capture and durable source replay BIND-02; unowned producer explicit gap | P5.4 / P7 / external app |
| lease mistaken for external exactly-once | Local handler atomic SQL, external outbox and unknown lookup WAK-04/05 BIND-08 | P5 / engine/channel |
| SKIP LOCKED starvation or root hint delete race | Fair sources/all-account reconciliation; per-upsert revision+epoch CAS ROOT-02/04 | P5.2/3 / P10 ops |
| P4 digest runtime cycle/late items/DST/lock inversion | Exact bridge edge plus SQL discovery, explicit windows and lock order BRIDGE-02–04 | P5.5 / P4.6 |
| Generic root proposal silently rewrites B12 | D10 explicit approved mapping/BC gates; untouched source; no global ID migration | P2 / schema owner |
| Installed label mistaken for live engine/timing proof | Branch→installed→release→effective separate required receipts and p95 population | P9/P10/P11 |
