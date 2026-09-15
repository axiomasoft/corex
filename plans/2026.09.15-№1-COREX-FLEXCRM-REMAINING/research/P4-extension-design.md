# P4 headless extension contract design — PLAN1

RAG:— Local candidate design, not public API/schema adoption or runtime acceptance.
Author: plan-designer/gpt-5.6-sol/high; provider route artifacts/P4-design/route-evidence.json.
D4 full-plan-first, D6 neutral external queries and AGENTS major-only public BC policy remain.
P1/P2 actual equivalent qualification can yield hash-qualified no-delta only for the same observable
contract/cases. A material mismatch revises affected design/finish before implementation.

## Baseline and minimal placement

17 named source hashes and current commit are artifacts/P4-design/source-observations.json.
Existing EntityRegistry, ModuleRegistry, nine existing Capability enum cases, eight extenders,
Manifest/DI/listeners, API SPI, TenantStorage, Settings, DomainEvent and PubSub are retained.
Registry get/has/unscoped all are not active-tenant authorization: list through all(ctx)/
byCapability(existing case, ctx), confirm provider's ModuleRegistry activity separately, and apply
current consumer policy on every read/write. Morph resolution still retains disabled entity aliases.
No new enum case, extender, component compiler, module lifecycle, permission/event/query engine.
Manifest component validation is intentionally a no-op; P4 does not silently implement that engine.

Candidate packages: corex/panels (descriptor/policy adapter, depends core+modules), corex/media
(asset/reference/conversion adapters, core+modules), corex/notifications (intent/delivery/digest
adapter, core+modules). Each is one corex-module with standard manifest/DI; registrations occur in
code, never in a new database panel-type registry. Existing core owns saved views as B31 requests:
new interfaces in packages/core/src/Contracts, immutable data/internal implementation under Views.
It cannot import modules/api/panels/media/notifications. Corex/panels contains the EntityRegistry
adapter for core's schema port; core-only consumer supplies a trusted schema/policy directly.
New packages work without corex/tenancy, corex/auth, API transport, Redis, S3, mail/push credentials,
Vaulter/Chatom or any application domain. Physical DB-per-account via existing resolver or boxed
single account; no row-level tenant routing and no client-supplied database/disk selector.

Published namespace casing is CoreX, not legacy blueprint Corex. Media and panel references carry
opaque validated entity/actor IDs (UUIDv7/ULID/int accepted per producer policy); no universal UUID
rewrite. New package record IDs follow existing IdColumns UUIDv7 helpers after G-SCHEMA approval.
No user/workspace/domain foreign table FK; own media/notification internal FKs are allowed.

## R / AC ownership

| R | Producer | Cases / obligations | Consumer / gate |
|:--|:--|:--|:--|
| R14 | P4.1, P4.8 | PAN-01–07; B16 §8.1–4/20 generic descriptor/current policy slices; entity capability and absent provider | P8 discovery metadata; R37/R38 adapters own explicit guest/membership/role rules |
| R15 | P4.2, P4.3, P4.8 | MED-01–08, CON-01–06; B16 §8.12/13/20 generic usage/delete/no cross-package FK plus conversion fault cases | P6 file IO only when declared; P9 installed/released; G-SCHEMA/G-STORAGE |
| R16 | P4.4–P4.6, P4.8 | NTF-01–07, CHN-01–07, DIG-01–07; B16 §8.14/15/20 generic after-commit/dedup/preferences/digest/boxed slices | P5 wakeup only P4.6; P7 notification adapter when declared; P9/P10 source versus effective channel receipt |
| R28 | P4.7, P4.8 | VIEW-01–09; B31 AC-1 saved-view slice, AC-14 default scope, AC-15 generic boxed slice; §2.2/4.1 filter/projection ports | P6 filter/schema contributions; P8 metadata; R31 Studio remains product owned |

All 20 B16 §8 parents and all 15 B31 parents in research/01a-acceptance-index.md stay intact.
That index groups mixed criteria under several R: P1 atomic classification must retain an owner
and receipt for every slice. B16 messaging pagination/counters, inherited document ACL/Yjs/sidecar,
workflow signal, timeline UI, forgetting/retention and live partition performance are R37/R38/
R18/R09/external acceptance, not generic P4 GREEN. B31 graph CAS/publish/version/dry-run/template/
stage/SmartProcess/UI limits likewise R31/R09/R11/R35; they are not silently erased or counted PASS.
Generic no-FK media absence is tested with a neutral optional attachment consumer; actual Chatom
migration belongs to R38. In-app means durable readable inbox here; actual Reverb broadcast is an
optional app PubSub/channel binding whose effective receipt remains external. No local test proves
real guest/role precedence, provider delivery, p99, legal compliance or product aggregate acceptance.

## Frozen candidate public seams / v1

New ports below are proposed additions, not edits to accepted interfaces. Actual P2 catalogue
p2-contract-catalogue/1 must bind exact signatures/DTO/error/schema/defaults and applicable G receipts.
Every interface lives in owning src/Contracts; implementations final @internal; immutable data
final readonly. Omitted signatures are not permission to add a public method during implementation.

### Panel descriptor/policy seam

Contracts under CoreX\Panels\Contracts, data under CoreX\Panels\Data:

```php
interface PanelProvider {
    public function descriptor(): PanelDescriptor;
    public function access(PanelContext $context, PanelReference $panel): PanelAccess;
}
interface PanelCatalogue {
    /** @return list<PanelDescriptor> */
    public function forEntity(PanelContext $context, EntityReference $entity): array;
    public function access(PanelContext $context, PanelReference $panel): PanelAccess;
}
```

PanelDescriptor(version='panel/1', key, owningModule composer name, ?requiredCapability existing
enum value, supportedActions list of opaque registered action keys, label translation key).
EntityReference(handle, id); PanelReference(providerKey, panelId, ?EntityReference parent);
PanelContext(trusted TenantContext, actorKind, actorId) is constructed by consumer auth adapter,
never JSON hydration of trusted principal/tenant. PanelAccess(view, actions set, ?reason) contains
no grant/membership cache. No method executes product commands or trusts previous access result.
Internal ModulePanelCatalogue receives iterable DI-tagged PanelProvider; duplicate key is boot
configuration error, unavailable provider absent, disabled provider/entity omitted. Unknown entity/
provider/panel action, missing current context or policy exception deny. Current module state is
read again on access. Direct standalone panels may have null parent only when explicitly supported
by provider; entity listing never invents a parent. Provider owns effective grant logic including
explicit independent guests, derived stale grants and role expansion. Core does not force parent
access AND guest role, nor permit a cached derived grant to bypass parent's revoked authority.
Platform/context/provider disable denies take precedence over provider allow. Parent entity
visibility and panel-only visibility are distinct; guest panel view does not reveal parent metadata.
No new persistent pnl_panel_types; catalogue is read projection of DI contributions + registries.
Product commands call current policy again inside their own protected write boundary.

### Media lifecycle seam

Contracts under CoreX\Media\Contracts; data under CoreX\Media\Data:

```php
interface MediaLibrary {
    public function store(MediaContext $context, UploadInput $input): MediaFileRef;
    public function read(MediaContext $context, string $fileId): MediaReadRef;
    public function delete(MediaContext $context, string $fileId): DeleteResult;
    public function conversion(MediaContext $context, string $fileId, string $profile): ?MediaConversionRef;
}
interface RelationLookup {
    public function attach(MediaContext $context, AttachmentInput $input): void;
    public function detach(MediaContext $context, AttachmentInput $input): void;
    /** @return list<AttachmentRef> */
    public function usages(MediaContext $context, string $fileId, int $limit = 50): array;
}
interface MediaPolicy {
    public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool;
}
interface ConversionDriver {
    public function profile(): ConversionProfile;
    public function convert(ConversionInput $input): ConversionOutput;
}
```

MediaContext(trusted TenantContext, actorKind, actorId); UploadInput(requestKey, UploadedFile,
purpose registered key) has no path/disk/provider/account from body. Stream path private to trusted
UploadedFile; no remote-URL fetch, SSRF or executable upload endpoint. AttachmentInput(fileId,
entityHandle, entityId, slot registered key). Library stores server-detected MIME/bytes/checksum,
originalName display only, private generated key, upload actor, immutable scope. MediaFileRef(id,
kind,mimeType,sizeBytes,sha256,state); MediaReadRef opaque authorized read handle consumed/closed
within current context, never public permanent path. ConversionRef only for ready output.
DeleteResult(deleted bool,purgePending bool) idempotent; in-use throws MediaFileInUse with bounded
policy-filtered usage summaries, unauthorized caller never sees other owners' usages.
MediaAction = store/read/attach/detach/delete; policy default denies. Require existing enabled entity
and current attach-target policy at relation mutation. MediaSubject includes file/target scope,
server-resolved metadata; no product role or arbitrary Eloquent model supplied by body.

Internal SqlMediaLibrary/SqlRelationLookup use TenantStorage::disk (same scoped retained-handle
semantics P3), own SQL connection resolved from trusted context. No direct Storage facade or hard
S3 default. Policy controls reuse of unbound uploads; knowing file ID/checksum is not authorization.
Do not deduplicate physical blobs across accounts or infer ownership from checksum equality.

### Media SQL / IO protocol

Frozen logical candidate med_files: UUID record id, request_key varchar(128) UNIQUE,
request_digest char(64), state staged/ready/deleting/deleted/quarantined, version bigint >=1,
private object_key, detected MIME, kind, bytes>=0, checksum, uploaded actor, optional immutable
workspace-scope key, created/updated/deleted timestamps. med_relations: own FK file_id RESTRICT,
entity_handle varchar(128), opaque entity_id varchar(128), slot varchar(64), UNIQUE(file_id,
entity_handle,entity_id,slot); no FK into consumer tables. Own conversion/purge jobs in
med_operations: key UNIQUE, kind conversion/purge, file FK RESTRICT, state pending/claimed/
ready/failed/unknown/cancelled, source_version/profile_digest/claim_epoch, attempts,next_attempt,
lease_until, private attempt output key. med_conversions UNIQUE(file_id,profile_key,profile_digest,
source_version), own FK, state pending/ready/failed, output facts; indexes file relations and due jobs.
No stored refcount required: authoritative usages query under media row lock; avoids counter drift.

Order: media file lock → relation/operation rows. Every attach/detach/delete/conversion finalization
uses same file row. Delete locks ready file, tests live relations, transitions deleting and enqueues
purge atomically; new attach refuses deleting/deleted. Soft delete at accepted delete, bytes retained
until purge succeeds. Repeated delete reports current outcome; unknown physical delete outcome
reconciles existence through supported adapter semantics before retry. Storage not transactional:
first reserve request key/digest staged SQL, then stream to server-generated immutable attempt key,
verify content, finally ready+conversion intent in SQL. Crash before/after IO/finalize recovered via
reservation+own operations; staged partial upload never downloadable; unknown write reconciled by
checksum/metadata before promoting. Same-key different digest fails conflict. Failed attempt objects
are tracked for cleanup; no claiming unsupported atomic rename on object storage. Ready publication
is SQL reference to complete immutable object; storage object completion/conformance requires G-STORAGE.
No network IO while SQL row lock held. Purge claims deleting file with no uses, repeats state/hold
check, deletes original and every tracked derivative/attempt idempotently outside transaction,
then records complete only if file version/claim still current. No undelete method in v1; retention/
backup/legal holds are consumer binding; missing required hold policy denies purge.

Conversions register drivers in code/DI, immutable profile key+digest includes implementation/
parameters/version. Base supported adapter: bounded PHP GD PNG/JPEG thumbnail with explicit
ext-gd capability (package suggests, not default hard boot requirement); no video/PDF/SVG promise.
No GD => unsupported conversion absent, upload/read still supported. Required profile proof includes
real GD lane and named max bytes/pixels/time limits; test-only echo driver is not real conversion.
Profile resources bounded (default upload 10 MiB, image decode 20 million pixels, target <=4096
per dimension, process 30s), configurable downward/upward only with qualified consumer receipt;
these are design safe defaults, not measured platform limits. Decode failures quarantine/refuse,
output content verified; original filename/exif never interpolated into command/shell/web HTML.
Worker claim epoch/canonical operation key makes stale completion unable to publish. AfterCommit
kick optional; restartable scan of med_operations provides durable recovery without Redis. Worker
outside trusted account context refuses processing. Conversion versus delete locks same file,
stale output never becomes downloadable; its tracked object remains purge-cleanable. A stale
worker can still finish IO after delete/purge; keep immutable attempt-key tombstones and cleanup
records through the qualified maximum worker IO/retry horizon. Re-scan tracked attempt keys
(including those uploaded after first purge) before declaring physical purge complete; absent
bounded worker termination/provider listing consistency cannot claim complete physical cleanup.

### Notification seams

Contracts under CoreX\Notifications\Contracts, data under CoreX\Notifications\Data:

```php
interface NotificationCenter {
    public function notify(NotificationContext $context, NotificationInput $input): NotificationRef;
    public function cancel(NotificationContext $context, string $notificationId): CancelResult;
    public function markRead(NotificationContext $context, string $notificationId): void;
    /** @return list<NotificationRef> */
    public function inbox(NotificationContext $context, InboxQuery $query): array;
    public function unreadCount(NotificationContext $context): int;
}
interface NotificationPolicy {
    public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool;
}
interface NotificationChannelDriver {
    public function descriptor(): ChannelDescriptor;
    public function send(DeliveryInput $input): DeliveryOutcome;
    public function reconcile(DeliveryInput $input): DeliveryOutcome;
}
interface RecipientResolver {
    public function resolve(NotificationContext $context, RecipientReference $recipient, string $channel): ?DeliveryTarget;
}
interface PreferenceRepository {
    public function get(NotificationContext $context, string $eventType): NotificationPreference;
    public function set(NotificationContext $context, PreferenceInput $input): NotificationPreference;
}
interface DigestBuilder {
    public function build(NotificationContext $context, DigestWindow $window): ?DigestRef;
}
```

NotificationContext(trusted TenantContext, actorKind, actorId); NotificationInput(requestKey,
eventType registered key, RecipientReference(kind,id), templateKey registered code, payload bounded
validated JSON, ?EntityReference related). Recipient identity not arbitrary target URL/mail address;
resolver gets current trusted policy/context and returns provider-specific target. No User FK,
permission engine, concrete application event subscriptions, HTML renderer or hardcoded roles.
NotificationAction notify/read/cancel/preference; deny default. Related-resource visibility checked
on inbox/read/send, revoked source redacts/skips per policy; server-safe translation template and
allowlisted action route resolver supplied by consumer, no arbitrary action URL/HTML in payload.
InboxQuery(before UUIDv7,int limit<=100) anchored by trusted self recipient, no user/account body
selector. list cursor (recipient_kind,recipient_id,id DESC), reads/count/cancel tenant scoped.

NotificationPreference(version,channels list registered keys,digest instant/hourly/daily/off).
SettingsPreferenceRepository adapts existing SettingsRepository cascade: event-specific→wildcard
user→consumer declared defaults, default in_app+instant only. No new ntf_preferences engine/table.
Facade validates limits/scope and writes Settings+recipient state revision in same physical SQL
transaction when durable cancellation is requested. Recipient-state row lock first → notification →
delivery → digest rows; no reverse order. Provider addresses/secrets absent from logs and stored payload.
ChannelDescriptor(key,version,supportsIdempotency,supportsReconciliation,
?dedupWindowSeconds, dedupScope registered key); channels are extensible
registered strings (not new fixed private provider enum). Base in_app driver writes durable SQL inbox;
optional PubSub broadcast sends advisory invalidation only, inbox remains authoritative if offline.
Optional mail/push unavailable => delivery skipped:provider-unconfigured, no credential lookup on boot.
Missing policy denies, configured provider outage failed/unknown, never silently substituted/skipped.
DeliveryInput(stable deliveryKey,attemptEpoch,recipient target,rendered bounded message,context).
DeliveryOutcome(kind accepted/retryable/permanent/unknown,?providerRef,?retryAfter,reason sanitized).
Unsupported reconcile returns unknown; plain Laravel mail adapter does not promise provider dedup. Template/policy/driver versions and
rendered message SHA are bound at admission; no replay under the same delivery key with changed
bytes. Bounded message template/key/source language policy belongs consumer, never remote HTML.
CancelResult(cancelled bool,alreadyDispatched list of channel keys): cancellation before send
admission prevents send; cancellation after external dispatch cannot retract sent/in-flight bytes.

### Durable notification SQL and delivery protocol

ntf_notifications: UUIDv7 id, recipient kind/id, request_key/digest, event_type/template_key/payload,
related opaque ref, read_at,dismissed_at,cancelled_at,revision,timestamps; UNIQUE(recipient_kind,
recipient_id,request_key). ntf_recipient_state: (kind,id) PK, preference_revision>=1. ntf_deliveries:
own FK notification (nullable only for digest-owned intent), own FK digest_run nullable, CHECK exactly
one owner; channel, delivery_key UNIQUE, state pending/claimed/sent/failed/skipped/cancelled/unknown,
claim_epoch, attempts,next_attempt,lease_until,admitted_revision,provider_ref/error sanitized,
sent_at. ntf_digest_runs: UUID, recipient kind/id, period hourly/daily, UTC period_start/end, batch
sequence, membership list via ntf_digest_items own FKs; UNIQUE(recipient_kind,recipient_id,period,
period_start,batch_seq); UNIQUE(notification_id,channel) membership in ntf_digest_items. References
and metadata immutable, same-account physically. Index unread, due pending, recipient windows.
Tables are scoped candidate G-SCHEMA additions, source blueprint not copied as approved DDL.

notify writes notification+all eligible channel intents in SAME connection/transaction as producer
business mutation when invoked in that transaction. Cross-DB caller cannot claim atomicity: must
supply its own durable producer outbox and retry notify(requestKey). DomainEvent listener after
commit alone cannot recover lost notification intent; integration must specify durable producer.
No provider/network dispatch before commit. Optional queue afterCommit kick accelerates scan;
commit-to-kick crash found by scanner. Local sync mode scans after commit; durable due rows survive
restart. NotificationCreated/Delivered/etc remain existing DomainEvent subclasses; package listens
through manifest only, no DB subscription store/second bus. Durable effect rows also keep event
publication pending if consumers require recoverable event delivery; raw dispatch alone is advisory.

Stable key = notification id/channel (digest key = run id/channel), retries retain it. Claim epoch
prevents stale worker writing terminal state. Expired admitted non-idempotent claim becomes
unknown, not a resendable pending row; a worker crash does not prove no effect. Provider dedup
window/scope is exact accepted contract, retries cannot assume same key works beyond retention.
Retry window measured from first admission; expired/unknown window requires authoritative
reconciliation or explicit disposition. A negative lookup from an eventual cache is not proof
that an earlier provider effect did not occur. Timeout/crash after provider effect → unknown, not
sent or blindly safe retry. Retry only if idempotent provider contract or reconciliation proves no
effect; otherwise explicit unknown pending provider/consumer disposition prevents claimed completion.
Default max attempts 5 exponential backoff 1/2/4/8/16 min with bounded jitter and configured provider
retry-after cap; exhausted recorded permanent failure and diagnostic; no infinite loop hidden GREEN.
At send admission re-read current context, policy, recipient validity, source visibility, preference,
read/cancel state under recipient+notification locks; record admission revision then release lock
before IO. Mutations linearize at this point: later cancel may return alreadyDispatched. No cached
capability/preference makes authorization; P10 verifies real wiring/timing if required by selected
provider. No exactly-once end-user delivery; accepted = provider accepted, not read or delivered.

### Digest / conditional wakeup protocol

Hourly/daily external channels wait; in_app instant unless channels explicitly disabled, regardless
of digest period including off. Digest off disables external delivery, not enabled in_app. Read or
cancel before digest admission removes item; source policy and latest preferences rechecked. Digest
window explicit half-open UTC [start,end), period kind; hourly UTC hour/daily UTC day defaults,
consumer local-time policy must supply exact windows (P7 recurrence if needed), no hidden DST guess.
Items ordered UUIDv7; configurable maximum 100 per batch, residual items in further batch_seq,
not dropped/truncated silently. Lock recipient state → source rows in id order → run/items;
freeze admitted membership/revision/digest+channel key before IO. Concurrent/duplicate wakeup
same window claims existing runs; different digest windows cannot duplicate item/channel due unique
membership. Failed/unknown run retries identical run/delivery key; new items in closed window get
next batch_seq via catch-up; preferences can cancel undispatched run/items and re-evaluate remaining
allowed set BEFORE new admission, never mutate already dispatched digest content under same key.
If content changes before admission, new run revision/key explicitly cancels prior undispatched
intent; frozen delivery bytes+digest recorded once admitted. Restart reconstructs due windows and
pending rows from durable state, not a volatile process clock/cache.

P4.6 consumes P5's future tested generic wakeup adapter only when scheduled digest enabled; no
binding to standalone workflow graph engine. Required local mode boxed local_sweep needs no root/
Redis/private credentials. P5 must expose accepted trigger/scheduled interval/request key/context/
restart behavior to DigestWakeupHandler; exact FQN/signature mapping now frozen in research/P5-wakeup-design.md (D10) and checked at full
finish, before P2 adoption. This is a future producer interface obligation, not an unapproved API
choice delegated to executor. Handler signature internal __invoke(DigestTick $tick): void with
DigestTick(trusted TenantContext,window,requestKey). P4 pure notify/channel branches don't wait P5.
P5.5 owns DigestWakeupAdapter/DigestTick glue with typed local closure and bounded neutral fixture proof; P4.6 owns real handler/provider wiring and notification DueSource catch-up. P5.5 depends P4.4/5 only, P4.6 consumes actual P5.2/P5.5 receipts; no cycle or full DIG proof inferred from glue fixture. Required lock order request→recipient→source→run/items, no reverse request acquisition under consumer state locks. Late closed-window source cannot reinsert completed wakeup key.

### Saved views in core / modules schema adapter

Core contracts in CoreX\Contracts, data in CoreX\Views\Data:

```php
interface SavedViewRepository {
    /** @return list<SavedViewRef> */
    public function viewsFor(ViewContext $context, ViewScope $scope): array;
    public function save(ViewContext $context, SaveViewInput $input): SavedViewRef;
    public function delete(ViewContext $context, string $viewId): void;
    public function setDefault(ViewContext $context, string $viewId): SavedViewRef;
    public function compile(ViewContext $context, string $viewId): SavedQuery;
}
interface ViewSchemaProvider {
    public function schema(ViewContext $context, string $entityHandle): ViewSchema;
}
interface ViewAccessPolicy {
    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool;
}
```

ViewContext(trusted TenantContext,actorKind,actorId); ViewScope(entityHandle,?workspaceId,
?ownerKind,?ownerId) nullable owner pair both null=shared, both set=personal; account implicit.
Scope validation requires current account/workspace visibility, explicit shared-management policy;
no global cross-account list. SaveViewInput(?id,expectedRevision,scope,kind registered supported key,
localizedName bounded map,config), SavedViewRef(id,scope,kind,revision,isDefault), ViewSchema(version,
entityHandle,list<ViewField> field keys/type/operators/visibility/filter/sort/project flags,
server-side mapping keys). Panel package Internal/EntityViewSchemaProvider projects existing enabled
EntityDefinition.fields plus explicit consumer-approved operator/mapping contributions; metadata
searchable does not imply readable/filterable. Dynamic field contribution R11 belongs P6, version
changes invalidate old unsafe config until explicit migration, no dropped unknown filter widening.
ViewSchemaProvider in core-only fixture uses its own neutral model; no import CoreX\Modules in core.

Canonical config version saved-view/1: filters typed tree {op:and|or,args:[node]} or leaf
{field,op:eq|ne|lt|lte|gt|gte|in|is_null,value}; sorts [{field,direction:asc|desc}],
columns list field keys, ?groupBy field key, kind-specific bounded presentation config. Unknown
keys/operators/field/type/version rejected, no raw SQL/class/expression/connection/identifier from
body. Depth<=4, nodes<=50, in-list<=100, sorts<=5, columns<=50, payload<=64KiB, localized name
<=255 chars per locale with <=20 locales; decimal/money values strings, dates canonical timezone
with schema rules. Null predicates explicit, absent value never expands all rows. Safe schema
mapping determines identifiers, values always bound. Sort appends stable approved record-id key
for deterministic order only if schema allows it; otherwise sorting request refused. Projection
redacts unreadable fields at compile. Filter on inaccessible field rejected to prevent inference.

Internal SqlSavedViewRepository persists validated immutable config/revision; compile revalidates
current schema/policy/scope and returns SavedQuery(schemaVersion,validated typed filters/sort/
projection), not data/SQL. Consumer applies its own row/object policy and uses approved mapping +
parameter binding; saved view is never permission token. No universal CRUD or second search engine.
P6 search imports/consumes this representation only when included, adapters can translate safe AST;
P4 is not dependent on future P6 search runtime for ordinary built-in field views.

sys_saved_views candidate: UUID id, entity_code varchar(128), workspace_id nullable opaque text,
owner_kind/id nullable opaque text, scope_key NONNULL canonical encoded tagged tuple excluding
account (physical boundary), kind,name/config JSON, schema_version, revision bigint>=1,is_default,
default_scope_key NULL when nondefault otherwise scope_key, position, created_by bounded actor,
timestamps; CHECK default marker consistency, owner pair consistency; UNIQUE(entity_code,
default_scope_key) permits multiple nondefaults but forbids two defaults including account/shared
nullable scopes on SQLite and PG. sys_saved_view_scopes (entity_code,scope_key) PK serializes first
create/setDefault/delete/reassign; no new tenancy engine. Scope_key generated from length-prefixed
UTF-8 canonical tuple, not ambiguous concatenation, client-supplied string or magic UUID sentinel.
CHECK/serialization enforced for every write; concurrent scope insert handled upsert then lock.

Scope lock first → view rows id order. First view becomes default; setDefault clears previous and
sets target same transaction; delete default chooses next lowest(position,id), last view deletion
leaves empty scope. Thus nonempty scope exactly one default under library lifecycle; unique index
alone proves only at-most-one. Conflict stale expectedRevision typed ViewConflict (API adapter maps
409 if installed); unknown schema typed UnsupportedViewSchema, denied maps 403/opaque 404 by
consumer, no silent config pruning. B31 COALESCE(owner) index leaves nullable workspace duplicates:
record proposed correction in P2 G-SCHEMA; never claim frozen B31 bytes already adequate or edit
Brain spec. A nullable-ID cross-user FK is not imported. PG raw index candidate / SQLite equivalent
plus rollback/export mapping frozen by P2 actual schema approval before adoption.

## Required local case IDs

| Report | Required Case IDs |
|:--|:--|
| findings/P4-panels.md | PAN-01; PAN-02; PAN-03; PAN-04; PAN-05; PAN-06; PAN-07 |
| findings/P4-media-lifecycle.md | MED-01; MED-02; MED-03; MED-04; MED-05; MED-06; MED-07; MED-08 |
| findings/P4-media-conversions.md | CON-01; CON-02; CON-03; CON-04; CON-05; CON-06 |
| findings/P4-notification-outbox.md | NTF-01; NTF-02; NTF-03; NTF-04; NTF-05; NTF-06; NTF-07 |
| findings/P4-notification-channels.md | CHN-01; CHN-02; CHN-03; CHN-04; CHN-05; CHN-06; CHN-07 |
| findings/P4-notification-digest.md | DIG-01; DIG-02; DIG-03; DIG-04; DIG-05; DIG-06; DIG-07 |
| findings/P4-saved-views.md | VIEW-01; VIEW-02; VIEW-03; VIEW-04; VIEW-05; VIEW-06; VIEW-07; VIEW-08; VIEW-09 |

## Local invariant definitions

| Case | Required invariant / fault |
|:--|:--|
| PAN-01 | two neutral entities discover only existing supported capabilities from active ctx |
| PAN-02 | unknown entity/provider/action and missing policy deny |
| PAN-03 | absent/disabled provider hides descriptors, boot has no optional secret lookup |
| PAN-04 | disable owning entity/provider after descriptor then access denies, morph alias retained |
| PAN-05 | stale derived grant cannot bypass revoked parent read; execute rechecks |
| PAN-06 | explicit independent panel guest can see panel per provider policy, cannot see parent; platform deny wins |
| PAN-07 | duplicate provider key rejected; A/B/boxed/reused worker context cannot leak catalogue/access |
| MED-01 | safe upload/read/attach/detach and same-key same-digest replay |
| MED-02 | MIME/name/traversal/NUL/oversize/image-bomb denied, staged/quarantine not readable |
| MED-03 | real A/B DB and retained storage handle context switches deny cross-boundary read/use |
| MED-04 | simultaneous attach/delete serializes on media row; in-use delete refused and summary filtered |
| MED-05 | duplicate attach/detach idempotent, unknown/disabled entity/policy denies |
| MED-06 | crash reserve/partial IO/finalize recovers without orphan ready refs; differing digest conflict |
| MED-07 | delete/purge IO timeout/unknown reconciles; own FKs/late attach/deletion state prevent dangling link |
| MED-08 | optional attachment consumer absent media boot/migration no cross-package FK; local and declared object driver conformance distinct |
| CON-01 | real GD bounded thumbnail correct decoded pixels/MIME/dimensions; no GD profile absent |
| CON-02 | identical version/profile concurrent request one canonical conversion operation/output |
| CON-03 | crash before/after output IO/commit scanner recovers; stale epoch cannot publish |
| CON-04 | delete during conversion denies late ready/read and tracked derivative purged |
| CON-05 | changed profile/source version never reuses stale output, malformed/decode/limit fault typed |
| CON-06 | queue sync/no Redis restart scan valid; A/B context/storage capabilities and provider outage deny/fail correctly |
| NTF-01 | domain mutation + intent same SQL transaction, rollback emits no inbox/channel/event effect |
| NTF-02 | crash after commit before afterCommit kick recovered by durable scan |
| NTF-03 | concurrent notify stable recipient/request key dedups; different digest conflict |
| NTF-04 | worker duplicate/expired claim stale epoch cannot overwrite later result |
| NTF-05 | crash after provider accepted unknown stays unknown unless idempotent/reconciled; no false exactly-once |
| NTF-06 | A/B physical DB/reused worker forged recipient/context and missing policy deny |
| NTF-07 | self inbox/count/read cursor bounded; source revoke redacts/denies, unsafe URL/HTML/payload rejected |
| CHN-01 | event→wildcard→default cascade via Settings; forged preference owner denied |
| CHN-02 | daily/hourly/off defer external while enabled in_app instant; channel disable explicit |
| CHN-03 | absent mail/push no secrets and skipped; configured outage retry/unknown distinct |
| CHN-04 | send admission fresh actor/recipient/source/preference; revoke/change after enqueue before admission cancels |
| CHN-05 | cancellation before admission no effect, after admission reported in-flight/accepted cannot retract |
| CHN-06 | real local inbox + Laravel log-mail adapter + test idempotent/reconciling driver obey stable key/error mapping |
| CHN-07 | bounded retry exhaustion/backoff and provider-ref sanitized; Redis/PubSub loss doesn't lose durable inbox |
| DIG-01 | hourly/daily half-open UTC boundary items exactly covered, in_app unaffected |
| DIG-02 | duplicate/concurrent ticks/restart identical run/key, no duplicate item/channel membership |
| DIG-03 | read/cancel/preference/source revoke before admission removes item; frozen admitted content not mutated |
| DIG-04 | crash before/after provider effect/retry reconciles same run/key/claim epoch |
| DIG-05 | >100 items paginate batches, late items catch-up, no silent loss or cross-window duplicates |
| DIG-06 | boxed accepted P5 local_sweep no root/Redis/workflow graph; optional disabled digest branch boot valid |
| DIG-07 | A/B account/recipient isolation; supplied timezone windows incl DST accepted exactly, implicit local guess rejected |
| VIEW-01 | core-only + modules adapter neutral fields save/list/compile with active ctx and no core sibling import |
| VIEW-02 | unknown/disallowed field/operator/version/raw SQL/identifier/class/connection denied; values bound |
| VIEW-03 | inaccessible filter denied and columns redacted on current schema/policy after privilege change |
| VIEW-04 | scope ownership/shared write policy and physical A/B/boxed checks deny forged principal/workspace |
| VIEW-05 | concurrent first create/default switch all four nullable workspace/owner scope classes exactly one when nonempty |
| VIEW-06 | default delete reassigns deterministically; last delete empty; rollback retains valid previous default |
| VIEW-07 | stale revision CAS conflict, invalid config/name/complexity/in-list/decimal/null boundaries rejected |
| VIEW-08 | dynamic field removal/version change fails closed until explicit migration, no silent widening |
| VIEW-09 | SQL and SQLite defaults CHECK/unique negative direct insert; compiled query consumer applies own row policy |

These 51 IDs are concrete future tests, not executed design results. A test source isn't a receipt.
Fault conformance uses real isolated PG connections for state races; SQLite cannot prove row locks.
Declared native object/channel profile requires actual adapter suite; local/log/fake doesn't prove live.

## Report schemas / acceptance

Every seven branch report uses p4-extension-receipts/1:
## Cases table columns Case / R/AC / Adapter/profile / Given/fault / Expected / Actual evidence /
Result / Limit/owner/resume. R/AC points to frozen atomic P1 owner slices. Required local case IDs
above all PASS, each actual log/command/runtime/config/source hash included. Additional outcomes
PASS/FAIL/UNSUPPORTED/PENDING preserved; required supported case never waived as optional.
## Inputs table Path / SHA256 / Role includes actual P1/P2 catalogue and relevant product/tests/
configuration/tool environment, version-qualified source. ## Adoption table Surface / Proposal
hash / Protected receipt / Supported local adapter / Installed contract / External owner/resume.
New dependencies/API/schema must match actual accepted hashes; approval is not inferred from D9.
Deliver source delta or exact qualified equivalent/no-delta. No deterministic empty R row.

P4.8 findings/P4-extension-receipts.md same version, ## Requirements table R / Items / Local case
receipts / Installed receipts / Pending effective gates / Owner / Resume: exactly R14/R15/R16/R28.
## Producer edges Producer / Contract/version / Consumer / Required receipt / Failure model.
## Sealed inputs Path / SHA256: exact list below plus all actual source/test/config/installed fixture
and lock/archive manifests used; exclude own output and volatile journal/state/handoff. Coverage of
all declared supported modes required; a legitimate external live obligation stays explicit P10,
not counted as P4 installed proof. P4.8 cannot be GREEN if required local/install case failed/unknown.

## Seal inputs

| Path | Role |
|:--|:--|
| findings/P1-requirements-lock.md | exact requirement population |
| findings/P1-acceptance-map.md | atomic ownership |
| findings/P2-contract-catalogue.md | accepted p2-contract-catalogue/1 |
| findings/P3-storage-context.md | supported storage p3-adapter-receipts/1 |
| findings/P4-panels.md | PAN branch |
| findings/P4-media-lifecycle.md | MED branch |
| findings/P4-media-conversions.md | CON branch |
| findings/P4-notification-outbox.md | NTF branch |
| findings/P4-notification-channels.md | CHN branch |
| findings/P4-notification-digest.md | DIG branch plus actual accepted P5 wakeup path |
| findings/P4-saved-views.md | VIEW branch |
| research/P4-extension-design.md | frozen design |
| research/P4-design-rag.md | verified external practices |
| research/03-gates.md | protected scope and external gates |
| research/05-integrated-design.md | compatible cross-phase bindings |
| decisions/D9-headless-extensions.md | chosen candidate, no protected adoption grant |
| phases/P4/P4.md | phase acceptance |
| phases/P4/P4.1.md | item spec |
| phases/P4/P4.2.md | item spec |
| phases/P4/P4.3.md | item spec |
| phases/P4/P4.4.md | item spec |
| phases/P4/P4.5.md | item spec |
| phases/P4/P4.6.md | item spec |
| phases/P4/P4.7.md | item spec |
| phases/P4/P4.8.md | final seal spec |

## Installed non-CRM proof and compatibility

P4.8 produces reproducible Composer archive manifest/ref/SHA and two neutral fixtures (lab sample
and volunteer equipment), each independent fresh Laravel app with distinct entity handles/fields/
policy/template/provider setup, no Flex code/private source/shared vendor/path symlink back to repo.
Profiles: Laravel12/PHP8.3 and Laravel13/PHP8.5 installed publicly from candidate immutable archives,
plus core-only saved-view fixture and full headless boxed profile without tenancy/auth/Redis/S3/
mail/push; physical two-account PG conformance separately uses accepted tenancy package. L12/PHP8.3
Pest4 development runner incompatibility, if applicable, handled by installed probe script/runtime
outside dev suite; do not claim package test support from it. P9 owns declared full 8.3–8.5×L12–13
matrix and published cohort; candidate install isn't release or hosted CI. Log mail sink adapter
installed and exercised without remote provider credentials, actual mailbox/push receipt P10 only
if included. Real GD conversions included one installed profile; no GD absence another. Replay all
applicable negative/capability/scope/dedup/default and boxed digest local_sweep cases through installed
bytes. Fixture non-CRM example does not give external integration ownership to CoreX.

## Cross-phase obligations / pre-mortem

P2.3 reads this stable design/specs for exact generic proposals; P4 execution reads actual sealed P2.
P3.1 storage branch→P4.2/P4.3, not identity/P3.7 seal; required file capabilities declared and
unsupported object operation never inferred. P5 tested local_sweep wakeup→P4.6 only; missing exact
P5 design binding is a finish obligation, no cycle. P4.1/P4.7→P8 stable read metadata only; tool
invocation still consumer's current policy, no auto-export command handlers. P4→P6/P7 only explicitly
included media/notification/view contributions; P4 does not need their search/recurrence engines.
P4.8 installed source seal→P9 cohort includes new packages in dependency/BC/split/install/runner
and literal scans; P10 effective required channel/storage/timing/provider outcomes distinct; P11
same immutable cohort plus every R/AC and actual consumer receipt. P11 cannot relabel missing P4
source as future requirement. Full finish must freeze P5 path/signature and all affected consumers.

Pre-mortem: stale descriptor grants (PAN-04–06; provider owner); media attach/delete/late conversion
race (MED-04/CON-04; media maintainer); commit-to-dispatch crash (NTF-02; notification maintainer);
external timeout unknown (NTF-05/CHN-06/DIG-04; provider+consumer); preference versus in-flight effect
(CHN-04/05; consumer accepted boundary); nullable default duplicates and stale field widening
(VIEW-05/08/09; schema/query owner); optional provider boot secrets (PAN-03/CHN-03; package owner);
missed fixed-list arch/CI scan (P4.8 negative probes; quality owner); unbound P5 producer
(D4 finish denies until stable exact binding; P5 designer). None produces fake production acceptance.
