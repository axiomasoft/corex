# P6 — Frozen data/import/search candidate — PLAN1.D11

RAG:— Локальный design contract; future actual qualification/approvals/receipts не заявлены.
R11/R12/R32 и R52 invariant; исходные B15/B18/B38 неизменны. Eight exact specs.
Candidate schemas: field-binding/1 · schema-bridge/1 · transfer/1 · search/1 · index-lifecycle/1 · p6-data-receipts/1.

## Repository evidence and placement

Current committed evidence: packages/modules/src/Extend/EntityField.php explicitly delegates
storage to an external attributes package and forbids altering another module table;
packages/modules/src/Registry/FieldDefinition.php describes fields but is not a values engine;
packages/modules/src/Contracts/EntityRegistry.php and Registry/ArrayEntityRegistry.php provide
context-filtered active entities. all(null)/get() do NOT authorize active tenant access.
TenantStorage/StorageCapabilities remain filesystem boundary; no replacement filesystem.
Targeted source/dependency inventory found no attribute/import/search implementation to claim
qualified. This observation is not absence proof; P1/P2 equivalence receipts decide keep/delta.

Placement: corex/attributes thin optional standalone-engine binding, corex/import-export bounded
transfer orchestration and PHP CSV/NDJSON codecs, corex/search query/PG and index lifecycle,
corex/search-meilisearch separate SDK adapter. Namespace CoreX\\Attributes, CoreX\\ImportExport,
CoreX\\Search, CoreX\\SearchMeilisearch. No core dependency on siblings; adapters depend downward.
Existing engine must be independently owned, usable without CoreX/product imports; actual immutable
source/ABI selected by P6.1 from P1/P2 approved candidates, never latest or invented engine.
No new field registry/DDL/FSM engine inside CoreX. Missing required engine capability cannot be
marked complete by a qualification packet: named upstream owner/resume, engine subset not ready.
Independent transfer/static-field PG query continue without engine. No dependency on flex/*.

## Exact public candidate contracts

New interfaces only src/Contracts; immutable final DTOs Support; implementations Internal.
No accepted signature/enum/ID/MoneyCast change. P2 freezes actual old/new hashes and G-API/G-SCHEMA/
G-DEPENDENCY receipts BEFORE adoption; D11 is design, grants no protected operation.

CoreX\\Attributes\\Contracts:
- FieldCatalogue::snapshot(DataScope $scope, string $entity): FieldSnapshot
- AttributeStore::read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
- AttributeStore::write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): WriteResult
- AttributeStore::loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch
- AttributeFilter::compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan
- SchemaOperations::request(DataScope $scope, SchemaChange $change, OperationKey $key): SchemaReceipt
- SchemaOperations::inspect(DataScope $scope, OperationRef $operation): SchemaReceipt
- SchemaOperations::verify(DataScope $scope, string $entity): DriftReport

No raw SQL/connection/class/path selector parameters. FilterPlan internal structural compiler data,
not arbitrary executable SQL returned to request caller. Engine external FQNs/signatures remain
source-owned ABI map in findings/P6-engine-qualification.md; outward port semantics fixed here.
FieldSnapshot {entity,schemaVersion,schemaHash,activeFields,driverCapabilities}; trusted scope
contains account/connection resolved from existing context (boxed explicit), actor/policy callback,
active-module snapshot and restoreEpoch, NEVER payload-selected database. RecordRef entity+opaque
existing record key; no global UUID rewrite. WriteToken operationKey+payloadHash+expectedRecordVersion.
Value presence missing|null|value: missing means absent/unset, null explicit clear only if allowed,
empty string distinct, empty multivalue distinct; read preserves presence and set ordering semantics.
15 B15 types text,int,decimal,bool,date,datetime,select,multiselect,reference,money,url,email,phone,
file,json; decimals strings no float; money existing minor-units+currency; dates strict ISO,
UTC instants with declared timezone input; options/reference/file validated by consumer resolver.
Multiselect set unique deterministic order; ordered file/reference preserve declared order.
JSON structure/depth and locale rules pinned; no default type downgrade unknown→text.
Driver-specific unsupported combinations explicit unsupportedCapability (unique only ddl,
variant axes only eav/select), never silently ignored. Parity all common supported cases ×ddl/eav/
jsonb on actual PG16 engine. loadMany bounded selection≤50 records/30 fields; EAV B15 exact
one value-read query accounted independently of policy/metadata queries.
Declared field overrides allow label/position/group only; immutable code/type; sleeping/disabled
contributions not exposed or written. Existing 8 EntityField extender remains declaration mechanism.
Public query filters eq,in,range,isNull,isMissing,containsAny only by per-field allowlist; structural
field/operator/JSON path from trusted snapshot, scalar parameter bindings; unsupported denies.

CoreX\\ImportExport\\Contracts:
- TransferSource::describe(DataScope $scope): SourceManifest
- TransferSource::read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch
- RecordMapper::map(SourceRow $row, MappingSnapshot $mapping): MappingResult
- ImportTarget::preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision
- ImportTarget::apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult
- ImportTarget::lookup(DataScope $scope, WriteToken $token): TargetResult
- ExportSource::page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage
- TransferRunner::step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt

SourceManifest {immutableRef,sha256,length,format,parserVersion,encoding,delimiter,enclosure,escape,
schemaHash}; MappingSnapshot canonical sorted keys/version/normalizers/target field/schema/options
and SHA256, no closures/class names/SEL/SQL from user mapping. Trusted named mapper registry is
consumer DI, not new extension engine. CanonicalRecord {entity,externalSystem,externalId,
sourceRevision,sourceUpdatedAt,values,payloadHash}; external IDs interpreted by consumer.
TransferCursor complete logical-record ordinal + validated boundary byte offset where safe;
CSV quoted multiline counted as one, NDJSON byte offset only complete bounded line. JSON array
must use qualified streaming parser+state, otherwise unsupportedFormat, never whole-file decode.
Default codecs CSV+NDJSON; XLSX optional independently qualified codec (bounded ZIP entry/
decompression limits) owned external, original B18 UI/sample≥20 remains owned consumer.
TransferLimits maxFileBytes=1GiB,maxRecordBytes=1MiB,maxFields=256,maxDepth=32,batchRows=500,
maxErrors=1000,maxRuntimeSeconds=30,maxMemoryMiB=128; exact actual measured bound and reject oversize
BEFORE full allocation; PHP fgetcsv alone does not bound multiline record buffering, add bounded
logical-record stream parser/read limiter. Larger profiles explicit consumer config+proof, not promises.
Results applied|updated|noop|invalid|conflict|unknown|unsupported; unknown cannot advance the unresolved
row cursor, independent later rows may proceed only via persisted gap ledger contiguous high-watermark.

## Schema bridge and storage safety

CoreX bridge validates approved operation ownership and delegates to qualified external engine;
no runtime ALTER/CREATE INDEX in CoreX HTTP or bridge. Engine conformance covers typed DDL/EAV/
jsonb, async op-log metadata and transaction atomicity, bounded retries≤5, catalog fingerprint.
request merely persists/enqueues accepted engine operation and returns pending; sync queue cannot
execute DDL inside HTTP. Same tenant DB metadata+values/schema; external engine PG16 capability
qualified (PHP actual runtime floors never inferred from baseline). Worker durable owner/epoch,
migration coordination and actual advisory locking; CIC nontransactional phase needs dedicated
connection session advisory lock or proven durable operation ownership through completion, not
transaction advisory lock that disappears before concurrent build. lock_timeout=2s,
statement_timeout=30min candidate engine profile only, runtime approval/tuning actual.
CIC recovery inspect exact relation/definition/indisvalid/indisready/unique/predicate before repair;
IF NOT EXISTS/name match is not equality proof. Only managed engine-owned approved objects touched;
orphan drift quarantine no automatic drop. Disabled grace≥30days/restore; purge requires exact
G-SCHEMA approval/data export/retention and independently proven backup, no guessed repair of lost data.
Schema version stale write/filter/import denies and retains diagnostics. Restored DB invalidates old
leases/jobs by restoreEpoch and reconciles authoritative schema+op-log; actual backup/load tests P10.
B15 202≤200ms and 10M pgbench add p95<5s/zero error-rate, verify<3s, EAV exact load and expression
EXPLAIN remain retained; small fixtures not live load proof. Non-domain engine support all driver
requirements actual before R11 complete. No customer entity CRUD/DDL policy inherited B15 cst/crm.

## Local transfer atomicity and remote effects

Approved neutral transfer mapping tables candidate corex_transfer_jobs, corex_transfer_rows,
corex_transfer_refs, corex_transfer_outbox, corex_export_manifests, corex_export_parts; G-SCHEMA exact
DDL bytes required; existing ID helper/type unchanged. Tenant-local job PK and unique
(entity,externalSystem,externalId) ref; operation identity is external source revision+payload hash
and target, NOT job ID alone (second job same source must not duplicate target or Created event).
Per job row key also unique (job,source ordinal), checkpoint sequence monotonic CAS.
Target local mode actual same SQL connection transaction, no target self-commit/DDL/IO allowed.
Lock order job→external ref→target; ref uniqueness serializes first creates; result/ref/outbox/
row error/counters/checkpoint commit atomically. Target content change compare digest/revision,
no-op emits no Created/Updated. Required consumer adapter performs domain policy, no generic table
upsert. ImportTarget declares localAtomic vs externalReconciled capability before execution.
Per-row savepoint rolls back domain failure before writing bounded sanitized error and continues;
nonrollbackable/connection failures abort whole local batch, not partial success. Deadlock retries
only SQL-idempotent work no external IO. updateIfNewer compares source revision/time in the same
authoritative lock, manualReview no target write; default suppression consumer event policy applies
only declared import domain triggers, never blanket security/audit suppression. Audits preserved.
Dry run same parser/map/current policy/conflict code but zero target/ref/dedup/checkpoint/outbox/
schema writes and no external apply; outcomes prediction conditional on snapshot, concurrent later
changes revalidated on apply. Progress channel/provider optional; no UI/billing/credential schema.
External target: persist frozen request/key/hash before send; provider documented dedup scope/horizon,
lookup and notAccepted certainty required. timeout/cancel/expired lease after possible send→unknown;
do not claim exactly-once, no unsafe second request/different payload/key or eventual negative proof.
lookup unresolvable→named target owner manual reconciliation; job pending, not completed. Matching
same key replay only qualified guarantee/horizon; generation prevents stale SQL completion but cannot
fence a late external send. Writer lane remains quarantined until termination/outcome reconciled.
No-op second source import and concurrent jobs tested, not job-level dedup alone.

## Export and file IO

Two explicit modes liveBounded (documented changing rows; not coherent offboarding) and
immutableManifest (required coherent roundtrip/offboarding). immutableManifest captures authorized
entity/field roster, schema/version/count/hash and canonical row payloads into tenant manifest parts
under one repeatable-read source snapshot; bounded streaming to parts, no long snapshot across resumes.
Snapshot seal completed atomically only after source read+immutable part verification; interrupted
capture discards unsealed attempt and restarts a NEW snapshot. Resuming emission uses sealed part
manifest only, no rereading mutable tables. New policy/revoke before chunk emission aborts/no output;
preexisting files use consumer-controlled retention/access (cannot retract bytes already downloaded).
Export source and all imported fields apply fresh field/row/source policy; cross-tenant manifests deny.
Staging immutable attempt parts, hash/length/count persisted; SQL part checkpoint only after supported
storage publish/lookup verifies exact bytes; lost IO ack reconcile, never append twice. Object storage
rename not assumed; TenantStorage capabilities drive finalize strategy, no download SSRF/user disk.
CSV raw machine mode typed schema sidecar encoding presence/decimal/money/multivalue and explicit
escape empty per PHP; displaySafe distinct manifest transform for spreadsheet mitigation with named
target spreadsheet profile, not universal guarantee nor raw reversible output. NDJSON lossless typed.
Download path/expiry/current permission consumer. Account export optional P3 maintenance integration
only after its owner implements registry hook; P3.2 cannot depend on future P6.5 at execution.
B18 all-capable entity coverage reconciliation roster+per-entity counts; Searchable|Auditable not
automatically permission to export; no catalog omissions silently excluded as unauthorized success.

## Search query and optional adapter

CoreX\\Search\\Contracts:
- SearchSchema::describe(DataScope $scope, string $entity): SearchSnapshot
- SearchProvider::capabilities(): SearchCapabilities
- SearchProvider::query(DataScope $scope, SearchQuery $query): CandidatePage
- SearchHydrator::authorize(DataScope $scope, CandidatePage $candidates, SearchSnapshot $schema): SearchPage
- SearchDocuments::page(DataScope $scope, DocumentCursor $after, int $limit): DocumentBatch
- IndexDriver::submit(IndexBatch $batch): IndexSubmission
- IndexDriver::status(IndexSubmission $submission): IndexOutcome
- IndexDriver::swap(IndexSwap $swap): IndexSubmission

SearchQuery schemaHash+typed filter AST (saved-view/1 adapter uses same approved grammar no second
expression engine), maxTextBytes4096,filterNodes64,depth8,inValues100,limit≤100,scanBudget≤1000.
Sort stable key appended; cursor HMAC binds scope/query/schema/provider/generation/order and position;
wrong account/stale cursor denies. Do not promise rank/typo/facet parity: PG common fulltext/simple
filter intersection conformance; external capability flags for typo/facets/highlight and unsupported
request denies, no silent dropping. PG profile tsvector/plainto_tsquery or safe websearch, bound values
and allowlisted config/language; no SQL/user operators/classes or invented language dataset.
Plain local SQL fallback requires static consumer SearchSchema/owner projection; dynamic field
integration consumes P6.2/3 only where requested. Core-only boot no modules/search credentials.
Meilisearch SDK separate corex/search-meilisearch only; external endpoint identity secrets configured
by consumer, no auto-fallback-on-auth-error to broaden authority. provider absent selects explicit
PG configured capability profile or unsupported; no mock successful search.

External index stores MINIMAL approved searchable PUBLIC-to-account field projection, no sensitive
field ciphertext/snippet/facet assumed safe. Provider response candidate IDs/order only crosses public
port; actual IDs matched to tenant/entity/schema/revision with CURRENT authoritative SQL row+field
authorization. No denied metadata/count/facet/snippet emitted; raw counts discarded, exact total unknown
unless authorized SQL aggregation separately supports it. Non-sensitive snippets generated only from
authorized hydrated values; facets only trusted authorized SQL source or unsupported capability.
Allowed content not returned from stale external version. Paging refill bounded scan budget, partial
page/truncated and continuation explicit; no unauthorized hasMore disclosure, continuation opaque.
Fresh permission check at query/serialization, revoked/deleted row omitted, module current ctx required.
Original saved-view port stays P4, adapter conditional read P4.7; P4 has NO P6 runtime prerequisite.

## Durable index lifecycle and restore

Approved tenant SQL neutral corex_search_dirty + corex_search_lanes + corex_search_rebuilds mappings.
Owner same-transaction dirty write {entity,id,recordRevision,schemaHash,restoreEpoch,deleted,payloadHash};
tombstone/high-watermark durable, not afterCommit alone. afterCommit only dispatcher kick; periodic
authoritative sweep heals missed kick, gap with source mutation not same transaction unsupported
until consumer-owned durable outbox exists. Static PG on-demand fallback queries current source;
optional persisted PG projection updates same SQL transaction or durable rebuild flagged stale.
Schema changes force generation rebuild; only monotonic revision publish and superseded SQL receipt
CAS. Provider with no per-document version CAS (Meilisearch baseline) uses ONE durable index writer
lane per scoped generation, including rebuild/catchup/swap. No concurrent stale submits: claim under
SQL lock, persist task UID/frozen bytes BEFORE progressing, do not release lane for next version until
prior provider task terminal. Lease expiration does NOT prove old submitter terminated; unknown
submission/late writer quarantines lane, verified process/transport termination + task reconciliation
required. No generic provider idempotency assumed; irreconcilable→new never-used index generation,
retire old lane/credentials or prove it cannot target new generation; PG fallback remains authority.
Document removal+future recreate revisions preserve tombstone; old rebuild snapshot cannot resurrect.
Rebuild creates new immutable target UID, current schema+restore epoch, captures source snapshot
then replays durable ordered dirty stream to contiguous watermark; journal sequence must have
commit-order visibility or track gaps (sequence allocated before commit alone unsafe). Short SQL
publication fence closes old/new dispatcher admission, drain ALL provider tasks to terminal, consume
committed pending/gaps under writer lane, submit swap, wait succeeded and verify alias/settings/data
generation. Mutations during fence durable pending and replay to new UID after mapping committed.
Swap 202 is not success; unknown swap no blind second swap (would revert), inspect task/actual alias/
generation using immutable marker; unresolved mapping queries PG and keep receipt pending.
Meilisearch queued tasks retain target UID through swap; fence prevents hidden old task crossing.
No lost dirty/delete across snapshot/fence/catch-up or source-transaction rollback.
Restore retires old index/lane epochs, reindex from authoritative restored DB and fresh current policy;
external index never source of truth. Tombstone retention ≥verified outstanding-worker/task horizon;
unbounded unknown lane forbids reclaim. No real ranking/p99/fleet/load claim from small local tests.

## R / AC ownership

| R | Producer | Cases / obligations | Consumer / gate |
|:--|:--|:--|:--|
| R11 | P6.1/2/3 | All 30 B15 parent AC retained in P1; FIELD+DDL common drivers actual; engine own DDL/values and exact ABI | P2 catalogue; P7 locale; P8 metadata; P9 release; schema/source owner and P10 live load |
| R12 | P6.4/5 | All 25 B18 parent AC retained; generic transfer slice; TRN+EXP roundtrip/dedup/crash/policy | P8 tooling; P9 installed; P11 consumer; R13 mappings/live FSM/UI external |
| R32 | P6.6/7 | SRCH+IDX allowlist/current policy/common parity/index crash restore | P7 optional lookup; P8 tooling; P9/P10/P11; real provider owner |
| R52 | P6.8 | Non-domain core-only/physical/boxed cold installed consumers and no literals/upward deps | P9/P11 same immutable cohort, source/install/release/effective distinct |

B15 AC1 registry tables and 15 custom-entity auto REST AC15 upstream/product policy, not clone.
AC6–17 engine asynchronous/concurrency/drift/destructive/load kept upstream receipt plus current binding
proof. AC21 axes only qualified engine eav, product SKU rules external. AC25 event→workflow conditional
same-transaction consumer capture P5.4; afterCommit observation alone not durable engine trigger.
AC26 REST/MCP wiring P8, actual UI external. AC27 tariff policy external config quota adapter, technical
engine cap qualified. AC28 search slot reuse only after successful current rebuild; PG no slot need.
AC29 boxed queue sync no HTTP DDL inline; AC30 actual PITR P10 distinct engine fixture.
B18 AC7–11/13–20/24 connector/product graph/FSM/stock policy belongs R13, generic mechanisms retained.
AC6 XLSX/preview UI external optional codec; AC12 suppress/backfill explicit consumer policy, no clone.
AC21 generic export P6.5; offboarding destructive lifecycle P3/P10; AC22 secrets consumer vault, no
sys_settings root assumption; AC23 quota callback/config not billing; AC25 duplicate no Created common.
No parent AC removed: P1 atomic qualification reconciles every child owner, stricter original checks/
thresholds persisted as pending external obligations, do not substitute a smaller local fixture.

## Required local case IDs

| Report | Required Case IDs |
|:--|:--|
| findings/P6-engine-qualification.md | ENG6-01; ENG6-02; ENG6-03; ENG6-04; ENG6-05 |
| findings/P6-field-binding.md | FIELD-01; FIELD-02; FIELD-03; FIELD-04; FIELD-05; FIELD-06; FIELD-07; FIELD-08 |
| findings/P6-schema-bridge.md | DDL-01; DDL-02; DDL-03; DDL-04; DDL-05; DDL-06; DDL-07; DDL-08 |
| findings/P6-transfer-runner.md | TRN-01; TRN-02; TRN-03; TRN-04; TRN-05; TRN-06; TRN-07; TRN-08; TRN-09; TRN-10 |
| findings/P6-export.md | EXP-01; EXP-02; EXP-03; EXP-04; EXP-05; EXP-06; EXP-07; EXP-08 |
| findings/P6-search-query.md | SRCH-01; SRCH-02; SRCH-03; SRCH-04; SRCH-05; SRCH-06; SRCH-07; SRCH-08 |
| findings/P6-index-lifecycle.md | IDX-01; IDX-02; IDX-03; IDX-04; IDX-05; IDX-06; IDX-07; IDX-08; IDX-09; IDX-10 |

## Case invariants

| Case | Required observable result |
|:--|:--|
| ENG6-01 | Immutable source/ref/license/lock/ABI and no upward product/CoreX dependency; engine install independently clean |
| ENG6-02 | All 15 field types and ddl/eav/jsonb common parity with exact unsupported combination declarations |
| ENG6-03 | Actual engine async/CIC/lock/catalog/restore capabilities; no invented FQN/no upstream engine clone |
| ENG6-04 | Actual upstream suite and transaction/dedup/error evidence for all required engine capabilities |
| ENG6-05 | Transfer/search semantic equivalents kept; optional source/runtime compatibility exact floors, no unqualified SDK |
| FIELD-01 | 15 types ×3 drivers common supported normalization/read/write parity including exact money/decimal |
| FIELD-02 | Missing/null/empty/multivalue/options/reference/file distinction preserved and invalid values deny |
| FIELD-03 | Active/sleeping/disabled/module/policy/forged account checks independent physical A/B and boxed |
| FIELD-04 | Stale schema/record version and payload-key conflict deny, replay no duplicate event |
| FIELD-05 | Declared immutable code/type and allowed overrides only, no field registry duplication |
| FIELD-06 | Safe typed filter AST parity, unknown field/operator/SQL/path denies; bound scalar operands |
| FIELD-07 | Actual EAV loadMany 50×30 one value-read query and driver capability matrix truthful |
| FIELD-08 | Value changes+dirty/domain capture atomic when declared, rollback produces no durable effect |
| DDL-01 | HTTP request returns pending op without ANY DDL even boxed queue sync; original latency separate live |
| DDL-02 | Engine kill between ALTER+metadata commit no partial state, bounded recovery≤5 |
| DDL-03 | CIC kill leaves invalid index discovered/recovered exactly owned definition; wrong same-name index quarantine |
| DDL-04 | Two workers/migration coordination and CIC dedicated ownership no overlapping schema execution |
| DDL-05 | Volatile default/type/code/raw SQL/unapproved/destructive requests deny before enqueue |
| DDL-06 | Orphan drift quarantine no auto-drop; missing managed object exact approved repair only |
| DDL-07 | Disable/grace restore no lost values, purge denied absent approval/export; actual load/PITR owner retained |
| DDL-08 | Restored epoch invalidates stale job/claim/schema write; truthful local-vs-live restore receipt |
| TRN-01 | Identical source repeated in new jobs and concurrent jobs no second target/Created/ref |
| TRN-02 | Crash before/after target/ref/outbox/checkpoint commit atomic; lookup restores unknown local ack |
| TRN-03 | Per-row savepoint partial-continue, bounded safe error, connection-level fault rolls back batch |
| TRN-04 | Dry run same decisions under fixed snapshot, zero target/ref/checkpoint/dedup/outbox/schema/external effect |
| TRN-05 | Changed source/mapping/schema/parser hash or malformed cursor refuses resume |
| TRN-06 | External timeout/late submit/expired dedup horizon quarantines; qualified lookup only, no blind retry |
| TRN-07 | updateIfNewer/manualReview under lock, no direct consumer domain table write or silent type fallback |
| TRN-08 | CSV multiline/NDJSON logical boundary and oversize record rejected before allocation within fixed resources |
| TRN-09 | Fresh actor/row/field/source/tenant policy denies forged scope, no raw credentials/error-data disclosure |
| TRN-10 | Contiguous gap cursor+CAS/cancel/stale worker fencing, event suppression only approved domain trigger |
| EXP-01 | Raw CSV sidecar/NDJSON typed roundtrip presence/money/decimal/multivalue exact, PHP explicit escape |
| EXP-02 | DisplaySafe formula/delimiter/newline cases preserve profile safety label, never claim raw roundtrip |
| EXP-03 | Snapshot manifest covers all declared authorized entities/counts/hash; concurrent mutation not mixed |
| EXP-04 | Unsealed capture crash restarts new snapshot, sealed resume rereads only immutable parts |
| EXP-05 | Unknown storage finalization reconciles digest/length/count, no duplicate append or assumed object rename |
| EXP-06 | Fresh policy revoke/changed schema/forged A/B manifest denies further output, immutable access consumer-owned |
| EXP-07 | Streaming measured batch/row/file/runtime/memory/error limits; fixed source cursor is not snapshot |
| EXP-08 | Boxed/no media/no external storage credentials; optional TenantStorage supported actual disk finalize |
| SRCH-01 | Actual PG + Meilisearch common query/filter parity; capability-specific rank/typo not invented equality |
| SRCH-02 | Unknown identifiers/SQL/AST depths/value limits deny, safe query config/params only |
| SRCH-03 | Cross-tenant/disabled module/forged context/cursor HMAC denial in physical A/B+boxed |
| SRCH-04 | Current row/field policy after retrieval/serialization; denied snippet/count/facet not returned |
| SRCH-05 | Revoked/deleted/schema-stale candidate omitted despite stale provider ACL/document |
| SRCH-06 | Stable sort/cursor and bounded refill/truncated semantics no unauthorized total/hasMore |
| SRCH-07 | No external SDK/secrets boot with provider absent, explicit PG/unsupported capability profile |
| SRCH-08 | Optional saved-view/dynamic-field schema grammar uses actual P4/P6 contracts; core-only no sibling |
| IDX-01 | SQL rollback leaves no dirty, after-commit kick crash healed by durable sweep |
| IDX-02 | Reordered stale write/delete/recreate cannot resurrect with revision+tombstone+single actual lane |
| IDX-03 | Lease expires while old submitter alive: no takeover submission, lane quarantine until reconciled |
| IDX-04 | Provider 202/nonterminal/unknown task never marks indexed, next revision waits actual terminal task |
| IDX-05 | Rebuild snapshot+gap-aware commit watermark+catchup covers updates/deletes committed across fence |
| IDX-06 | Drain provider tasks before swap, queued target UID semantics cannot publish old bytes after swap |
| IDX-07 | Swap lost ack reconciles marker/task once; blind second swap prohibited; unresolved chooses PG |
| IDX-08 | Old generation worker/restoreEpoch denies, reindex restored DB current policy, old index not authority |
| IDX-09 | Schema/facet slot generation change only after successful rebuild; tombstone retention covers unknown horizon |
| IDX-10 | Actual SDK/service and PG test receipts, absent provider path works; no fake provider/live p99 proof |

## Receipt schemas and predecessor edges

Branch p6-data-receipts/1: ## Cases columns Case / R/AC / Adapter/profile / Given/fault / Expected /
Actual evidence / Result / Limit/owner/resume; every mandatory ID exactly once, PASS and actual
log hash, command/cwd/environment/source/test/schema/profile hashes, times. ## Inputs Path / SHA256 /
Role; ## Adoption Surface / Proposal hash / Protected receipt / Supported local adapter / Installed
contract / External owner/resume. Future report checker proves structure+hash only, semantics item-owned.
ENG qualification engine-qualification/1 adds actual upstream repo/ref/license/sha, ABI/capability
matrix, independent install/suite command/log hashes and read approval; no guessed signatures.

P6.8 seal findings/P6-data-receipts.md p6-data-receipts/1: ## Requirements R / Items / Local case receipts /
Installed receipts / Pending effective gates / Owner / Resume; ## Producer edges Producer /
Contract/version / Consumer / Required receipt / Failure model; ## Sealed inputs Path / SHA256.
All seven branch reports+stable candidate/RAG/D11/specs/P1 lock+AC/P2 catalogue+proposals/gates+
applicable P3 storage/P4 view conditional receipts and installed receipt hash-current.
Installed delivery/data-search-install-qualification-20260915/receipt.md includes immutable dist
source/ZIP/lock/runtime/config/DDL hashes, actual boot+driver cases in archive/lab neutral consumers
(static archive Records and lab Equipment), boxed and physical independent A/B, public core-only
without engine/SDK/media/root/auth/private secrets, engine/PG/Meilisearch enabled profiles separately.
Declared whole compatibility PHP8.3–8.5/L12–13 subject to actual engine qualification; narrower adapter
floor explicit, hosted complete matrix P9. No publication/release/effective result inferred.
P2 finished design→P2.3/4 proposals→P6 execution; no reverse runtime edge.
P6.1→P6.2→P6.3; P2→P6.4→P6.5; file-backed P6.4/5 consumes P3.1 only; memory streams independent.
P2→P6.6→P6.7, optional dynamic fields consumes P6.2/3; optional saved views consumes P4.7 only.
All seven→P6.8→P8/P9→P10/P11; no P3.2→P6 cycle, no P4/P5 mandatory engine dependency.
P7 R23 locale integration optional consumes FieldSnapshot/1 version projection (field-binding/1),
R25 lookup consumes search/1 capabilities only; pure utilities remain independent.
P8 metadata/query/transfer tool invocation current policy+limits, no raw runtime class discovery.

## DTO and neutral schema freeze

Shared new DTO constructors in CoreX\\Support\\Data (core has no sibling imports):
DataScope(TenantContext $context, string $connectionIdentity, string $actorId,
Closure $authorize, string $restoreEpoch); connectionIdentity from trusted existing resolver or
boxed constant ONLY, callback signature (string $entity,string $record,list<string> $fields,
string $action): bool, default deny; no serialized caller-supplied callback/connection.
RecordRef(string $entity,string $id); WriteToken(string $operationKey,string $payloadHash,
string $expectedRecordVersion); all final readonly. Authorizer evaluated by consumer inside each
write/query/output boundary, audit actor separate authority; opaque ID strategy preserved.
FieldSelection/list records/value patch immutable validated lists; JSON-like DTO payloads bounded
by declared type schemas, not mixed raw SQL/closures. All hashes lowercase SHA256 of canonical
sorted-key JSON with exact UTF8/decimal string/value-presence rules; versions opaque immutable
strings, no numeric coercion. Candidate errors invalidValue|unknownField|unsupportedCapability|
schemaMismatch|scopeDenied|versionConflict|keyConflict|sourceChanged|cursorInvalid|limitExceeded|
pending|unknown, never HTTP/status/permission domain engine leakage.

SQL table ownership only neutral transfer/index metadata. K means IdColumns primary/foreign matching
configured current strategy (no default UUID-only schema). Record IDs external/string varchar255
unless consumer immutable opaque encoding supplied. Account isolation physical connection, no
tenant_id security filter fallback. Timestamp columns UTC timestampsTz, never ID/time DEFAULT rewrite.
Epoch and schema/payload hashes char64; revisions decimal(20,0) nonnegative monotonic per record,
overflow refuses write, restore epoch binding separately prevents old-clock/version replay.
SQLite serial utility profile supported by Laravel json/decimal mappings; PG16 real concurrency.

| Table | Frozen essential columns / key / invariant |
|:--|:--|
| corex_transfer_jobs | id K PK; source_manifest json; source_hash char64; mapping_snapshot json; mapping_hash char64; schema_hash char64; restore_epoch char64; mode varchar24; state varchar24; checkpoint_seq decimal20 default0; cursor json; stats json; timestampsTz; CHECK state pending/running/unknown/completed/cancelled/failed; cursor only contiguous terminal row high-watermark; no secrets |
| corex_transfer_rows | id K PK; job_id matching FK RESTRICT; ordinal decimal20; external_identity_hash char64; operation_key char64; payload_hash char64; result varchar24; error json nullable; request json nullable; provider_task varchar255 nullable; epoch decimal20; UNIQUE(job_id,ordinal); terminal result immutability except exact reconciliation unknown→proved outcome |
| corex_transfer_refs | id K PK; entity varchar128; external_system varchar128; external_id varchar255; target_id varchar255 nullable; source_revision varchar255; source_updated_at timestampsTz nullable; payload_hash char64; UNIQUE(entity,external_system,external_id); locked before target creation/update; no job-only dedup |
| corex_transfer_outbox | id K PK; job_id matching FK RESTRICT; operation_key char64 UNIQUE; payload_hash char64; request json; state varchar24; claim_epoch decimal20; provider_task varchar255 nullable; dedup_deadline timestampsTz nullable; no GC unknown/possibly alive external sender |
| corex_export_manifests | id K PK; job_id matching FK RESTRICT; capture_attempt char64 UNIQUE; schema_hash/restore_epoch char64; roster json; parts_hash char64 nullable; snapshot_mode varchar24; state varchar24 capturing/sealed/emitting/completed/aborted; sealed digest after repeatable-read capture; no resume unsealed snapshot |
| corex_export_parts | id K PK; manifest_id matching FK RESTRICT; part_no decimal20; immutable_ref varchar1024; hash char64; byte_length decimal20; row_counts json; state varchar24 staged/verified/emitted; UNIQUE(manifest_id,part_no); immutable verified bytes, after lost finalize lookup before checkpoint |
| corex_search_dirty | id K PK; entity varchar128; record_id varchar255; revision decimal20; schema_hash/restore_epoch/payload_hash char64; deleted bool; committed_event_ref varchar255; UNIQUE(entity,record_id,revision,restore_epoch); durable gaps marked, commit-order not inferred from id sequence |
| corex_search_lanes | id K PK; scope_hash char64; generation char64; index_uid varchar255; restore_epoch char64; state varchar24 idle/claimed/submitted/unknown/quarantined/retired; claim_epoch decimal20; active_task varchar255 nullable; request_hash char64 nullable; UNIQUE(scope_hash,generation); all submits serialized, uncertain holder never automatic takeover |
| corex_search_rebuilds | id K PK; scope_hash/generation/restore_epoch/schema_hash char64; old_uid/new_uid varchar255; state varchar24 capturing/catchingUp/fenced/swapping/verified/unknown/failed; cursor json; committed_watermark json; swap_task varchar255 nullable; marker_hash char64; UNIQUE(scope_hash,generation); no pending task crosses fence and no blind inverse swap |

Transfer/local transaction uses compare checkpoint_seq+epoch+source/mapping/schema/restore hashes;
root tables/customer tables NOT altered. Neutral DDL exact concrete bytes materialized by P2 proposal
and scoped G-SCHEMA before runtime, K strategy variation explained in that proposal, not runtime choice.
CoreX creates no upstream fld tables. Candidate SQL metadata is new protected proposal, not approved
live DDL. Major gate stays for any existing contract changes; new public additions require exact G-API.

## Pre-mortem and alternatives

Clone attributes/DDL engine: rejected external ownership+EntityField invariant; qualify and bind.
HTTP sync queue DDL: rejected; durable op only and separate external worker.
Transaction advisory lock around CIC: rejected lock ends before nontransaction step; dedicated ownership.
Job-only dedup: rejected second job duplication; external-ref/payload/revision atomic consumer upsert.
Mutable source+offset: rejected; immutable source/mapping/parser and complete logical boundary.
Live cursor as offboarding snapshot: rejected; sealed canonical manifest with source snapshot capture.
Unsafe CSV as raw reversible spreadsheet file: rejected; distinct displaySafe profile/raw sidecar.
Search ACL as authority: rejected current SQL hydrate and no raw totals/facets; PG fallback explicit.
External document revision CAS invented: rejected serialized lane/no takeover across unknown send.
Swap request twice on timeout: rejected non-idempotent inverse; task/marker reconciliation and PG fallback.
Finite tombstone GC with unknown late worker: rejected missing termination horizon retains/quarantines.
Key risk owners: external engine/schema owner DDL-02–08; transfer/consumer domain TRN-01–10;
storage/export EXP-03–06; query/policy SRCH-03–06; provider/lifecycle IDX-02–09; P9 install/release;
P10 actual 10M/backup/fleet/provider timing; P11 immutable consumer. Detailed thresholds untouched.
