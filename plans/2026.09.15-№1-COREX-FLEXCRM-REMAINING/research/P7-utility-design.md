# P7 — Portable recurrence, task primitives, locale/money/reference — PLAN1

RAG:— Repository facts and frozen candidate; new Contracts/DDL/dependency adoption requires actual P2/G receipts. D12 is design-only. No producer output or executed suite invented.

## Committed baseline and minimal delta

Existing packages/tasks/src/Contracts/{CreateTaskCommand,UpdateTaskCommand,CompleteTaskCommand,MoveTaskToStageCommand,TaskAccess,TaskMutationAccess}.php and Data/{TaskDraft,TaskReference,ActorData,EntityReference}.php stay byte-compatible. Models Project/Sprint/Task/Timer/TimeLog, recurrence fields and Review enum already exist; existence not full collab/FSM acceptance. TaskCompletion sets primitive Done under current access policy; do not replace it with unqualified StateMachine. Project/sprint/time-log command surfaces and recurrence implementation absent from current src listing; P1/P2 actual semantic equivalents must be qualified before any creation. Existing MoneyCast/Core Settings/EntityRegistry/8 extenders/events/API SPI/ID profile retained. A06 baseline pointer src/Casts is stale; actual committed MoneyCast is packages/core/src/Money/MoneyCast.php, read it, do not move class for plan consistency.

New optional Laravel packages corex/recurrence (pure value/port+qualified library wrapper, no core/tasks/calendar/DB boot dependency), corex/intl (typed locale/money/rate/local reference lookup, core+existing Brick Money), corex/tasks-workflow (tasks+recurrence+P5 local wakeup glue; qualified FSM optional), corex/intl-search (intl+search optional glue). Address driver belongs consumer or independently qualified integration, no default network vendor dependency. Internal implementations not extension APIs; only interfaces in src/Contracts. New public interfaces versioned and immutable final DTOs in Data; trusted DataScope is P6 core Support/Data scope, physical resolver box profile owns SQL/disk, payload never picks connection.

## R / AC ownership
| R | Producer | Cases / obligations | Consumer / gate |
|:--|:--|:--|:--|
| R17 | P7.1/P7.3; P7.4 optional | ENG7-02; PRIM-01–08; generic Task reuse, no R19 ownership | P9/P11; original B13/B27 parent acceptance external slices |
| R20 | P7.1/P7.2; P7.4 | REC-01–12; MAT-01–09; immutable library and two consumers | P8/P9; B27 materialize generic, calendar/product R19/R22 |
| R23 | P7.5/P7.8 | LOC-01–09; OPT-02/03/06 | P4/P6 optional labels/projection; P8/P9/P11 |
| R24 | P7.6 | FX-01–10; MoneyCast KEEP | P9/P11; finalization/R26/tariff/vendor ops external |
| R25 | P7.7/P7.8 | GEO-01–10; OPT-01/04/05 | P9/P10/P11; exact licensed source/G-DEPENDENCY/G-STORAGE; R32 optional |


## Frozen public ports and DTOs

All below signatures are candidate exact outward ports, adopted only through P2.3/4 catalogue row SHA/signatures/defaults/errors+G-API/G-SCHEMA/G-DEPENDENCY. Namespace CoreX\\Recurrence\\Contracts:
- RecurrenceExpander::expand(RecurrenceSet $set, RecurrenceWindow $window, ExpansionLimits $limits, ?ExpansionCursor $after = null): OccurrencePage
CoreX\\Tasks\\Contracts:
- TaskPlanningCommand::createProject(ProjectDraft $draft, PlanningToken $token): ProjectReference
- TaskPlanningCommand::createSprint(SprintDraft $draft, PlanningToken $token): SprintReference
- TaskPlanningCommand::recordTime(TimeLogDraft $draft, PlanningToken $token): TimeLogReference
- TaskPlanningAccess::authorize(PlanningOperation $operation, PlanningSubject $subject, ActorData $actor, TenantContext $context): void
CoreX\\TasksWorkflow\\Contracts:
- TaskRecurrenceCommand::materialize(DataScope $scope, SeriesReference $series, MaterializationWindow $window, WriteToken $token): MaterializationReceipt
CoreX\\Intl\\Contracts:
- LocaleResolver::resolve(LocaleInputs $inputs, LocalePolicy $policy): LocaleResolution
- TranslationReader::read(LocalizedText $text, LocaleResolution $locale): TranslationResult
- CurrencyMetadata::get(string $currencyCode): CurrencyInfo
- RateProvider::lookup(RateQuery $query): RateLookup
- RateImporter::import(DataScope $scope, RateBatch $batch, WriteToken $token): RateImportReceipt
- CurrencyConverter::convert(Money $amount, RateSnapshot $rate, ConversionPolicy $policy): ConversionReceipt
- MoneyFormatter::format(Money $amount, LocaleResolution $locale, FormatPolicy $policy): FormattedMoney
- GeographyProvider::describe(DataScope $scope): DatasetManifest
- GeographyProvider::query(DataScope $scope, GeographyQuery $query, ?ReferenceCursor $after = null): ReferencePage
- DatasetImporter::publish(DataScope $scope, DatasetCandidate $candidate, DatasetGeneration $expected): DatasetReceipt
- AddressSuggestProvider::suggest(AddressQuery $query, SuggestionLimits $limits): SuggestionResult
Public methods use typed DTOs no free SQL/FQN/connection/enum from payload. Address/geo read ports return typed status, not empty-success ambiguity. Config and resource profiles are declared finite mappings; no interface per underlying service/class.

RecurrenceSet {schemaVersion='recurrence/1',seriesId,immutableRevision,DateStart valueType DATE|TZID|UTC,nominalStart,TZID?,rrule?,rdates[],exdates[],overrides[],tzdbVersion,definitionHash}. Override {originalRecurrenceId,effectiveStart?,cancelled}; explicit exceptions independent properties, B27 shorthand EXDATE inside RRULE rejected/normalized by source owner, not accepted malformed RFC. One RRULE plus finite RDATE/EXDATE; no EXRULE/multiple RRULE/VTIMEZONE custom/floating/RANGE=THISANDFUTURE/leap second60 in default profile, explicit unsupportedFeature. All standard FREQ and qualified BY combinations; don't advertise full iCalendar interoperability. DTSTART matches pattern or invalidDefinition. DATE has DateWindow (half-open dates), timed has InstantWindow (UTC instants filtered after civil resolution), no mixed type/date-at-midnight fiction. Occurrence {key,originalRecurrenceId,valueType,TZID?,nominal,effectiveDateOrInstant,offset?,tzdbVersion,definitionHash}; key SHA256 length-prefixed canonical series+immutableRevision+valueType+original nominal+original TZID, never mutable UTC/rendered name/provider ID. Override moved keeps original key, same occurrence retired/cancelled metadata retained externally. tzdb actual runtime version captured; pinned version unavailable refuses, no assumed portable historical resolver.

ExpansionLimits default rangeDays=366,emitted=1000,candidateSteps=100000,wallSeconds=2,memoryMiB=32,ruleBytes=8192,exceptionCount=10000. Validate before material allocation. CandidateSteps includes library internal traversal before first emission; timeout watchdog/subprocess constrained engine where necessary, not lazy iterator after one unbounded call. Finite set vs exhausted window vs budgetExceeded distinct. OccurrencePage {occurrences[],status=complete|partial|unsupported|invalid,nextCursor?,completeThrough?,scanned,emitted,definitionHash}. Cursor digest binds set/window/tzdb/library version and upstream-qualified resume position; missing native seek/resume can support bounded replay only when actual consumed traversal fits limits. Never implement new RRULE engine to compensate. Dense/ancient source requiring unbounded replay returns budgetExceeded/unsupportedCapability; such a limitation cannot complete required R20 corpus until qualified alternative library supports it. CompleteThrough advances only fully traversed/persisted contiguous prefix; null at budget before first safe prefix. COUNT global RRULE valid generated occurrences including DTSTART, not post-EXDATE, gaps/invalid dates ignored; RDATE extras do not replenish COUNT. UNTIL inclusive proper type and UTC when TZID. First fold, explicit DTSTART gap pre-transition offset, generated gap omitted (RFC3.3.5 versus3.3.10). No monthly rollover.

ProjectDraft {workspaceId,name,ActorData}; SprintDraft {projectId,name,startDate?,endDate?,ActorData}; TimeLogDraft {taskId,userId,minutes positive integer,spentOn DATE,comment?,TimeLogSource,ActorData}. PlanningToken {requestKey,payloadHash}; refs reuse existing project/sprint/time-log ID helper and schemas. Authoritative current TaskPlanningAccess default deny, module guard/context and accepted IDs preserved. Project/Sprint FK/date consistency; task lock for time total; no timer executor/acceptance policy added. Source hardcoded UUID checks remain existing profile, no global ID rewrite. Planning request dedup neutral corex_task_operations unique(requestKey), hash conflict, result refs/current authorization rechecked before returning sensitive stored result. TimeLog+spent total+dedup+durable workflow/outbox on same task connection/transaction, no target network/DDL/self-commit; lock order operation→project/sprint/task, retry whole tx ≤3 SQLSTATE40001/40P01; afterCommit events unchanged plus trigger capture hook where enabled. No new status/role/KPI/template/coassignee/dependency graph tables.

MaterializationWindow default ahead30days, page≤100,runner≤5s; backfill starts durable incomplete prefix, not today after outage, consumer may explicitly cancel/archive missed occurrences with evidence. Series DB rows immutable revision, cursor/active mapping mutable with CAS. Neutral tables corex_task_series (immutable set/hash/template draft/actor/current context), corex_task_series_state(activeRevision,retired,completeThrough,cursor,restoreEpoch), corex_task_occurrences unique(series,revision,occurrenceKey), corex_task_operations. Actual old recurrence_rule_id schema has independent owner; no repurpose IDs/FKs or duplicate canonical registry. New mapping table links accepted TaskReference, scheduled effective instant and original civil identity; approved exact DDL P2/G-SCHEMA before creation. Series edit atomic retirement/cutover and consumer-specified explicit replacement mapping for pending projection, historical tasks/committed work never overwritten; default new revision not blanket duplicate same future timestamps. Row lock series-state→occurrence-key→existing CreateTaskCommand nested same connection→durable trigger/result/cursor; rollback all. All command effects/occurrence operation key mandatory SQL uniqueness, not queue cache. Disable/retirement serialized, stale epoch/generation denied after wait before write, fresh current TaskAccess per occurrence. Local task creation is same transaction; library key doesn't promise exactly-once external channels. Cancel future wakeup through P5 wakeup/1 generation; already committed tasks need consumer cancellation policy, admission receipt honest. P5.2 exact local producer findings/P5-local-wakeup.md; FSM/signal only optional P5.4 findings/P5-workflow-binding.md plus actual qualified source receipt. TaskCompletionCaptureObserver binds to existing Task saved Observer inside actual task transaction; checks current connection/context/module policy and records unique(taskId,completionKind,restoreEpoch) trigger before afterCommit TaskCompleted dispatch. No existing public Task command/TaskCompletion change, no post-commit listener claimed durable. Same-transaction completion trigger capture resolves signal-before-wait through qualified engine, afterCommit event alone insufficient. No graph/wakeup engine or direct engine state writes. P5 handler FQN from its accepted candidate, not invented scheduler ABI.

LocalePolicy {supportedTags≤100,appTag,explicitFallbackTags[],allowParent=true,policyHash}; LocaleInputs {projectedUserTag?,accountSettingsTag?,requestContextKey}, trusted scope/version provided through existing Settings adapter; no root_accounts access/identity writer. Validate BCP47 bounded≤128chars and supported aliases before ICU; canonical hyphen tag outward/internal ICU format separate; reject arbitrary ICU keywords/path. resolve projected user→account local cached Settings→app→fallback; parent RFC4647 matching per tag, no passing full Accept-Language header to Locale::lookup, q parsing separate bounded consumer. Return requested/used/origin/fallbackChain/wasFallback/policyHash/ICUversion. Context request/job scoped restore on exception, no Locale::setDefault persistent singleton mutation. LocalizedText {declaredField?,schemaHash?,map<tag,string|null>} explicit missing/null fallback, empty is value, fallback order no implicit en/ru columns. UI files existing Laravel Translator read-only adapter, domain JSONB data consumer/qualified spatie adapter not new table/write engine. R11/R28 localized field/label contributions optional future accepted schema metadata; plain locale resolution independent. Formatting ICU absent reports unsupportedFormatting or plain exact fallback, never secretly changes user locale.

CurrencyInfo {ISOcode,exponent,name/symbol presentation?,metadataVersion/hash}; Brick current pinned currency metadata is arithmetic scale, runtime CLDR display may differ. CurrencyRegistry here provider port no second dynamic registry engine. RateQuery {base,quote,onDate,sourceSelection,publicationCutoff?,maxAgeDays,allowStale=false}, effective date never observed publication time; choose most recent effective≤date and revision published≤cutoff under explicit source choice. Missing/stale distinct, no future rate/triangulation/base tariff default. RateSnapshot {schema='rate-snapshot/1',base,quote,rateDecimal canonical positive string≤38digits/18fraction digits,validOn DATE,observedAt UTC,source,publicationId,publicationRevision,payloadSHA256,id}; rate orientation quote per1base. Immutable original publication key (source,publicationId,revision,base,quote,validOn), same bytes no-op, changed bytes conflict; vendor correction new revision. import batch≤1000/1MiB validate before tx, current selection pointer optimistic generation CAS, retired snapshot still addressable; manual provider installed no live sync promise. Neutral corex_rate_snapshots and corex_rate_batches/indexes exact DDL gated; not clone central fx_rates/fan-out. ConversionPolicy {roundingMode required,targetContext required,policyId}; exact Brick RationalMoney multiplication→single explicit target rounding, ConversionReceipt {source/result exact Money,rate snapshot ID/hash,policy hash,requested date}. Base=quote identity can bypass provider. Negative money valid by policy, no float rate or arithmetic; MoneyCast signed-integer range explicit unchanged; outside representability refuses write. FormattedMoney {text,exactDecimal,currency,locale,method,wasFallback,ICUversion}; default plain exact ISO+decimal supported all magnitudes. ICU float-only path requires render-to-minor parity actual exponent/digit matrix and conservative bound; otherwise exact fallback, a final float conversion alone is not accuracy proof. No financial finalization/backfill/source country/CBR schedule/tariff/billing/3-year retention invented in library.

DatasetManifest {schema='reference-data/1',snapshotId,provider,sourceURI/retrievedAt,rawHash,normalizedHash,sourceVersion,importerVersion,licenseURI,attribution,coveredCountries/types,counts,coverageNotes,tzdbVersion,status}. ReferenceIdentity {namespace,sourceId} provider-scoped opaque stable keys; Country ISO alpha2 optional typed explicit namespace; subdivision ISO3166-2 optional, no fabricated1:1 GeoNames link. GeographyQuery {kind,countryKey?,parentKey?,placeKey?,query?,locale,snapshotId?,mode=exact|prefix|fuzzy,limit≤100}; current snapshot resolved once/page cursor pinned snapshot/filter/locale/sort+last normalizedName/key, unsupportedScope/notFound/ambiguous/snapshotExpired distinct. Timezone returns candidate IANA IDs + mapping provenance, country not single zone/city centroid not address proof. Read-only current trusted physical DB/boxed offline provider; no eager downloads/root/FK to central schema.

Neutral corex_reference_datasets,corex_reference_records,corex_reference_aliases,corex_reference_current pointer. Record PK(snapshot,namespace,sourceId), same-snapshot parent composite FK, names declared localized map/normalized index, coordinates bounded decimal strings not universal location assertion. Import source local immutable ref≤512MiB/logical row≤64KiB/count≤2million, record/page500/memory128MiB/time30s, overflow rejects before allocation. Candidate staging immutable generation manifest, duplicate identity/hierarchy/zone/type/coverage validate before publish; transaction CAS pointer(expectedSnapshot,generation) and published manifest, no partial live overwrite. Missing source license/version/artifact gates dataset subset; simple local provider/manual fixture independent. Consumer references store namespace+sourceId and original snapshot; provider switch never automatic merge by name or delete refs. Retain old snapshots by explicit pin/consumer retention policy; no purge without G-SCHEMA/storage permission, missing pin explicit expired. GeoNames optional qualified licensed source (CCBY4.0 attribution as actual download license), IANA only timezone rules not cities, GAR/DaData/country default external. SQLite exact/prefix supported; PG16 trigram feature approved extension/EXPLAIN actual, fuzzy unsupported otherwise. Local candidate perf each level50ms/autocomplete100ms ≥30samples+fixture size/environment raw; original200k/template3s, full vendor load/live P10, small sample not full acceptance.

## Optional bridges and downstream receipt contracts

corex/intl-search consumes actual P6 search/1 and index-lifecycle/1 only for declared localized fields. PG never invokes Meili-only flatten/localizedAttributes; no index names/language defaults in intl. Changing translations uses existing sourceRevision/dirty/outbox/single index writer lane and current row/field policy. P4 saved-view/1 labels supplied localized metadata without changing grammar/default/ACL. P6 locale is consumer-supplied until bridge, never P6 prerequisite on P7. AddressSuggestProvider is consumer DI optional HTTP adapter; explicitly admitted minimal text, no automatic address/phone/name transmission, timeout2s/results20/bytes64KiB, no secret/PII logs. Return status available|notFound|unavailable|rateLimited|unsupported|invalid with source/provenance; suggestions not authoritative ID and offline input remains valid. Dataset read ports not mutable per-user translator/importer exposure. P8 owns actual descriptors/invoke auth/tool metadata; read label not grant, no duplicate transport. P9 actual whole installed/released/hosted cohort, P10 source/vendor/perf/live sync, P11 exact same consumer cohort; no completed R from qualification-only NO-GO packet.

## Shared typed value and operation rules

Unqualified DTOs resolve to the owning package CoreX\Recurrence\Data, CoreX\Tasks\Data, CoreX\TasksWorkflow\Data, CoreX\Intl\Data; Money/rounding use existing Brick\Money/Brick\Math. Cross-domain DataScope/WriteToken are actual adopted CoreX\Support\Data candidates from P6/P2, never sibling package imports. All DTOs final readonly, constructor promotion with typed properties, defensive canonical lists/maps validated at construction; serialized version/hash methods not arbitrary object deserialization. Date value strict yyyy-mm-dd; Instant DateTimeImmutable UTC, LocaleTag string validated BCP47+allowlist, Scope/Actor only trusted ingress. Exceptions stable invalidDefinition/invalidLocale/invalidRate/unsupportedCapability/environmentMismatch/staleCursor/operationConflict/snapshotExpired/accessDenied; invalid/no-op/unknown results cannot be coerced into success.

PlanningOperation enum createProject|createSprint|recordTime; PlanningSubject {workspaceId:string,projectId:?string,taskId:?string,userId:?string} from resolved same-account rows, never caller assertion as access proof. Project/Sprint/TimeLog refs {id:string,type:string,display:string}; exact current type/ID mapping adopted P2, no product entity literals. ProjectDraft name trimmed1..190, SprintDraft name1..190/startDate/endDate map existing starts_on/ends_on, task title retains existing255. TimeLog minutes1..2^31-1, accumulated spent_minutes representability checked before save; comment≤2000/user actor authority current policy, no self-selected user impersonation. Existing Project active/Sprint planned defaults preserved. PlanningToken key≤190bytes and SHA256 canonical typed input+actor+scope+operation; same-key mismatch refuses and existing-key lookup authorizes current resolved subject first. Consumer policy passed by declared DI default deny, no new permission engine.

DatasetGeneration {expectedSnapshot:?string,generation:int>=0}; ReferenceCursor schema+snapshot+queryHash+last(normalizedName,namespace,sourceId), HMAC authenticated by consumer cursor key or server-stored opaque ref, decoded/canonical data still policy validated. RateBatch snapshot list≤1000/currentGeneration expected/formatHash/sourceManifest; RateQuery sourceSelection finite approved priorities source IDs (not provider URL selectors), optional publicationCutoff UTC explicit. RateLookup status found|missing|stale|unsupported; ConversionPolicy explicit RoundingMode+Context and snapshot staleness admission, no hidden HALF_UP/default currency. FormatPolicy mode plainExact|icuVerified and fallback=plainExact; pure formatter never loads rates. MaterializationReceipt version/taskRefs/pageDigest/committedPrefix/cursor/created/noop/conflict/retired/unknown; post-crash unknown SQL commit reconciles operation row before retry, durable state under actual same physical connection. No class/SQL/locale/provider selector expands authority.

Schema ownership: existing task ID/FK/helper/schema stays unchanged. New neutral mapping rows use current project/TaskReference/ID helper exactly adopted P2 type; no SQL FK assuming UUID before actual ID profile. New CREATE migrations only add mapping/receipt/rate/reference storage. Backward migration/export refuses nonempty affected data absent explicit approved retention/export; no destructive legacy alignment rerun, no dropping consumer tables. New migrations publish opt-in with named tags/config and installed runner applies before enabling dependent capability; schema absent reports unsupported/not-ready, providers never auto migrate on request/boot. Composite reference parent FK includes snapshot, namespace, sourceId; null root allowed only declared country/root types, no cross-snapshot or name-based parent guessing. SQL unique requestKey for task ops, (series,revision,key) occurrences, immutable source publication tuple for rates, (snapshot,namespace,sourceId) reference; pointer CAS uses previous generation WHERE and affectedRows=1, loser conflict not silent overwrite. Effective/schema/runtime/content hashes in P2 exact adopted bytes and actual receipts mandatory before any migration/port adoption.

## Receipt schema

Branch report p7-utility-receipts/1: ## Inputs table Path / SHA256 / Role (actual immutable predecessor/source/schema/config/runtime/fixture and logs); ## Adoption table Surface / Gate / Exact digest / Owner / Result / Resume; ## Cases table Case / R/AC / Expected / Actual evidence / Result / Owner / Resume. Actual evidence includes command,cwd,exit,source/runtime/fixture/config/schema/profile/logSHA256; Expected/Actual differ observable result. Cases exact inventory below, Result PASS only actual conformance, no handwritten PASS or structural checker as runtime proof. Optional not-selected capability must be accepted P2 disposition with parent external owner/resume; cannot satisfy selected mandatory provider by skipped mock. Seal findings/P7-utility-receipts.md Requirements five R rows Items / Local case receipts / Installed receipts / Pending effective gates / Owner / Resume; Producer edges versioned →P8/P9/P10/P11 and conditional P6 bridges; Sealed inputs Path/SHA256 all8branch reports/design/specs/RAG/D12/P1/P2 gates/installed receipt/current cohort. Installed delivery/utility-install-qualification-20260915 source ZIP+lock/checksum cold isolated neutral maintenance/timetable/locale currency/reference boxed/core-only/physical consumers; source snapshot hash independent of volatile report so no self-invalidating input. All declared common provider cases must run against actual supported driver, no fake API acceptance. Missing effective gates remain pending original R, not Phase GREEN.

## Required local case IDs
| Report | Required Case IDs |
|:--|:--|
| findings/P7-source-qualification.md | ENG7-01; ENG7-02; ENG7-03; ENG7-04; ENG7-05 |
| findings/P7-recurrence.md | REC-01; REC-02; REC-03; REC-04; REC-05; REC-06; REC-07; REC-08; REC-09; REC-10; REC-11; REC-12 |
| findings/P7-task-primitives.md | PRIM-01; PRIM-02; PRIM-03; PRIM-04; PRIM-05; PRIM-06; PRIM-07; PRIM-08 |
| findings/P7-task-recurrence.md | MAT-01; MAT-02; MAT-03; MAT-04; MAT-05; MAT-06; MAT-07; MAT-08; MAT-09 |
| findings/P7-locale.md | LOC-01; LOC-02; LOC-03; LOC-04; LOC-05; LOC-06; LOC-07; LOC-08; LOC-09 |
| findings/P7-money-rates.md | FX-01; FX-02; FX-03; FX-04; FX-05; FX-06; FX-07; FX-08; FX-09; FX-10 |
| findings/P7-geography.md | GEO-01; GEO-02; GEO-03; GEO-04; GEO-05; GEO-06; GEO-07; GEO-08; GEO-09; GEO-10 |
| findings/P7-optional-bridges.md | OPT-01; OPT-02; OPT-03; OPT-04; OPT-05; OPT-06; OPT-07 |


## Case invariants
| Case | Required observable result |
|:--|:--|
| ENG7-01 | Qualify immutable recurrence source/license/ref/hash/ABI and actual upstream isolated install/suite, all required RFC cases; missing mandatory capability not completed R20. |
| ENG7-02 | Qualify existing Task commands/access/model/schema equivalents against sealed P1/P2; exact old signatures/DTO/status/schema retained and each real gap mapped. |
| ENG7-03 | Qualify current Brick Money/currency metadata and PHP/ICU/tzdb/SQLite/PG16 compatibility; no new unpinned dependency assumed. |
| ENG7-04 | Qualify two interchangeable reference providers with exact dataset scope/license/attribution/raw+normalized hashes; missing licensed source blocks dataset subset only. |
| ENG7-05 | Base core-only, recurrence-only and intl-only boot with no tasks/workflow/tenancy/private/Redis/remote credentials; optional search/address integrations do not instantiate absent drivers. |
| REC-01 | Monthly January31 COUNT4 produces Jan31/Mar31/May31/Jul31; invalid dates not rolled over or counted; leap-year Feb29 exact. |
| REC-02 | America/New_York weekly09:00 through spring/fall transitions retains wall time, UTC offsets change; Europe/Moscow baseline tested separately and never used as DST-transition proof. |
| REC-03 | Generated NewYork02:30 spring gap ignored and not counted; explicit DTSTART gap uses RFC pre-transition offset, identity stays nominal; PHP normalization cannot erase distinction. |
| REC-04 | NewYork autumn01:30 fold resolves first occurrence exactly once, nominal identity+TZID retained; no second-fold duplicate. |
| REC-05 | COUNT plus EXDATE reduces visible set without replacement; DTSTART excluded still retained master; RRULE/RDATE duplicates union once; EXDATE separate property. |
| REC-06 | UNTIL inclusive and correct DATE/UTC type, COUNT+UNTIL reject; incompatible TZID/value type, invalid DTSTART-pattern and unsupported BYSECOND60/VTIMEZONE/RANGE deny explicitly. |
| REC-07 | DATE all-day expansion uses DateWindow/calendar dates without midnight conversion; floating time rejected in this bounded profile unless explicit zone supplied. |
| REC-08 | Half-open InstantWindow, adjacent pages/windows and overlaps yield same occurrence key; moved override uses original recurrence key and changed effective instant, cancelled override absent. |
| REC-09 | Ancient DTSTART/dense SECONDLY/rare BY combinations stop within candidate/time/memory limits even before first emitted result; budgetExceeded is not exhausted or horizon-complete. |
| REC-10 | Cursor source/rule/set/override/zone version hash mismatch rejects; supported upstream seek/resume or bounded replay actual proven, no reimplemented RRULE algorithm or serialized arbitrary object. |
| REC-11 | Pinned old tzdb unavailable fails unsupportedEnvironment; current tzdb changed output cannot silently rewrite committed instances or dedup by UTC. |
| REC-12 | Two actual neutral consumers equipment-maintenance and community timetable share pure conformance, no task/calendar/model/database imports. |
| PRIM-01 | Existing Create/Update/Complete/Move commands run unchanged through existing access/module/context guard; unauthorized/disabled denies, committed signatures and DTOs identical. |
| PRIM-02 | New planning createProject/createSprint enforce trusted physical context/workspace/current policy; same ID in another account denied and no connection selected by payload. |
| PRIM-03 | Project/sprint parent and date ordering checked before writes; same request key/hash returns same refs, changed payload conflicts, no duplicate events. |
| PRIM-04 | RecordTime command explicit positive integer minutes/date/source on authorized task; request dedup, target+time row+spent_minutes+outbox same transaction, crash rollback all. |
| PRIM-05 | Concurrent time-log requests different keys serialize task total correctly; repeated key increments once; deadlock retries whole transaction bounded. |
| PRIM-06 | Existing completion/status policy preserved; no new acceptance roles/review status/StateMachine ownership, no direct workflow policy inferred from model enum. |
| PRIM-07 | Null/boxed context supported exactly as actual existing schema/profile; user/workspace/author validation does not weaken existing ID or access rules. |
| PRIM-08 | Neutral maintenance project/sprint/time-log and target entity installed fixture; existing Task/Project/Sprint/TimeLog schema equivalent kept, no redundant table/migration. |
| MAT-01 | Same rule immutable revision/original occurrence key twice or concurrent sessions creates one task and links one receipt, unique SQL final authority. |
| MAT-02 | Crash after occurrence insert/before task or cursor commit rolls back task+occurrence+cursor+durable trigger atomically; replay same key returns exact stored result. |
| MAT-03 | Partial expansion/budget stop commits only completely persisted contiguous prefix, never full30day horizon; invalid/cancelled candidates count traversal but no holes. |
| MAT-04 | Disable/revision edit serializes rule row→occurrence→task against materialize; retired generation cannot add after retirement commit; already committed tasks retained with consumer cancellation owner. |
| MAT-05 | Moved override retains original key and effective time; immutable revision edit cutover retires old future projection explicitly, no automatic duplicate replacement task or deletion of completed task. |
| MAT-06 | P5.2 local_sweep wakeup/1 actual exact bridge creates durable local trigger and same-DB materialization; no second scheduler, no network effects under SQL lock. |
| MAT-07 | Optional P5.4 qualified FSM/signal binding task completion capture in same task SQL transaction before afterCommit; signal-before-wait recovered by engine contract, no acceptance-role implementation. |
| MAT-08 | Cross-account reuse/restoreEpoch stale worker denies, current policy checked after lock before each task write; cancelled task remains historic and outbox dedup keyed immutable operation. |
| MAT-09 | Boxed queue=sync/root absent real recurrence/task flow plus claim retry and changed credentials policy; afterCommit-only/fake engine does not pass durability or FSM conformance. |
| LOC-01 | Supported allowlist nonempty/deterministic and bounded BCP47 validation; ICU canonicalize not validity/data-support proof; invalid/overlong tags explicit invalidLocale. |
| LOC-02 | User-projected preference→local Settings account default→app locale→explicit fallback; supported parent negotiation explicit and reports requested/used/origin/fallback. |
| LOC-03 | Two sequential requests/jobs accounts/users A/B in one long-lived process no locale/timezone/static Translator leakage; restoration after exception verified. |
| LOC-04 | Warm path root/central/remote calls0; Settings scope/version invalidation per account; no stale cross-account memo or identity-writer mutation. |
| LOC-05 | Translation exact/parent/configured fallback order dedup; missing/null falls through, empty string is value, escaped content not translated/unescaped by utility. |
| LOC-06 | Only module-declared localized fields and UI resource paths allowed; field/schema hash drift denies, no arbitrary filesystem path/key or fixed ru/en SQL columns. |
| LOC-07 | Existing Settings/Audit/file Translator and consumer JSONB storage reused; no new translations table or user contact/name automatic localization. |
| LOC-08 | ICU absent plain supported locale+translation behavior still boots; formatting capability unavailable/fallback explicit, no environment locale guessing. |
| LOC-09 | Actual generic user-facing library labels and localized reference metadata from two providers, deterministic fallback under actual recorded PHP/ICU versions. |
| FX-01 | Currency exponent from pinned metadata USD2/JPY0/KWD3/CLF4, unknown code denies; MoneyCast unchanged, integer storage overflow rejected not truncated. |
| FX-02 | Base→quote unit orientation amountBase×rate=amountQuote; rates exact decimal strings positive bounded digits/no float/NaN/Inf, rational inverse only explicit no triangulation default. |
| FX-03 | Immutable RateSnapshot source/publicationRevision/effectiveOn/observedAt/sha; same publication key/hash idempotent, changed payload conflicts and source correction new immutable revision. |
| FX-04 | Concurrent rate import duplicates one row; whole batch validate then SQL transaction, receipt/current pointer atomic; failed import retains old snapshot. |
| FX-05 | Weekend/date-specific missing versus stale explicit lookup result, maxAge selection supplied by consumer; no nearest future rate, stale not success without explicit admission. |
| FX-06 | Conversion snapshot base/quote match and explicit RoundingMode/context; no intermediate float rounding; repeating immutable input yields same result/receipt. |
| FX-07 | Same currency exact identity rate1 needs no provider/SQL/network and preserves amount/currency; does not enable product multicurrency feature/backfill. |
| FX-08 | Negative/zero/large signed amounts exact Money conversion and explicit target exponent; SQL/integer representability checked, arbitrary Money magnitude not MoneyCast capacity proof. |
| FX-09 | Presentation exact ISO-code+decimal fallback for all magnitudes; optional float-only ICU path used only with proven minor-unit render parity actual ICU corpus; digit corruption/failure falls back observably. |
| FX-10 | Old rate snapshot remains reproducible after corrected rates/provider swap; dataset refresh not financial finalization/revaluation/retention policy; optional live sync not implied by manual installed flow. |
| GEO-01 | Two actual provider implementations same scoped country/subdivision/place/timezone queries with provider+source keys; equivalent coverage documented not invented ISO subdivision mapping. |
| GEO-02 | Raw URI/retrieval/source provenance/hash, normalized manifest/importer/version/counts/license/attribution/coverage actual; checksum alone not origin/completeness proof. |
| GEO-03 | Malformed bounded file/invalid zone/duplicate source identity/broken parent cross-snapshot ref rejects staging and leaves active snapshot unchanged. |
| GEO-04 | Immutable staging→validated snapshot→CAS active pointer same SQL transaction; crash/concurrent publish one expected generation wins, committed readers never partial. |
| GEO-05 | Pinned keyset cursor snapshot/filter/locale/sort/hash; replacement publication cannot mix page generations, missing retired snapshot explicit snapshotExpired. |
| GEO-06 | Name/alias normalization locale aware bounded UTF8 query≤200chars/page≤100, parameterized predicates/allowlisted structure; same names ambiguous not merged by name. |
| GEO-07 | Countries with multiple zones/missing city-zone mapping never single guessed timezone; canonical IANA ID+actual tzdb version, offset calculated for supplied instant. |
| GEO-08 | Country→region→city query EXPLAIN BUFFERS on declared realistic source corpus and ≥30samples, local p95 each≤50ms/autocomplete≤100ms/no unexpected SeqScan; population/hardware/result raw, full vendor200k/clone budget P10 retained. |
| GEO-09 | Account-scoped read-only lookup no public importer/write capability; dataset retirement never deletes consumer references or silently maps retired IDs. |
| GEO-10 | Boxed SQLite supported indexed prefix capabilities explicit; PG16 trigram needs approved extension, absent rejects fuzzy while exact/prefix still work; no PG/Meili rank parity promise. |
| OPT-01 | Absent search/address provider/source/secrets base boot/query stays offline; integrations enabled only after actual accepted producer/capability/profile. |
| OPT-02 | Localized field search optional P6.6/7 search/1 typed current schema/policy projection; PG route never invokes Meili-only localizedAttributes; no P6↔P7 runtime cycle. |
| OPT-03 | Locale to P4.7 saved-view labels only declared translation metadata/read port; original field AST/schema/operator/current ACL and default uniqueness unchanged. |
| OPT-04 | Address suggestion consumer-supplied driver authorized minimised query, timeout≤2s/results≤20/response≤64KiB; invalid/unsupported/unavailable/rateLimited/notFound distinct, secrets/PII not in logs. |
| OPT-05 | Two interchangeable installed address drivers via same port; at least selected actual configured driver conformance if claimed, canned fake not real provider acceptance; offline addresses accepted independently. |
| OPT-06 | Changing translated metadata uses P6 existing dirty/outbox/index lane/current policy and revision; late response never changes authoritative place ID or unauthorized index result. |
| OPT-07 | Read descriptors for locale/currencies/rate/geo no mutation/config changes/network eager calls; P8 actual invoke authorizer/descriptor/wire namespace receipt future, read-only label not authority. |


## Parent AC ownership — originals retained
| Parent | Generic owner | Generic slice / cases | Full original residual owner |
|:--|:--|:--|:--|
| B27 AC-1 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-2 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-3 | P7.4 | MAT-01/02/03 generic no duplicate | R19 original schema/template/full application flow |
| B27 AC-4 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-5 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-6 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-7 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-8 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-9 | P7.4 optional | MAT-07 current qualified signal-before-wait | R19 exact accepted task policy/engine app integration |
| B27 AC-10 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-11 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-12 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-13 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-14 | external | — no generic completion claimed | R19 product tasks-collab |
| B27 AC-15 | P7.9 | boxed Task recurrence installed | R19 original acceptance/roles/full-suite |
| B29 AC-1 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-2 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-3 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-4 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-5 | P7.2 | REC-02/03/04 civil-time DST plus literal Moscow baseline | R22 actual calendar integration; Moscow no DST-transition claim |
| B29 AC-6 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-7 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-8 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-9 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-10 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-11 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-12 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-13 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-14 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B29 AC-15 | external | — no generic completion claimed | R22 product calendar/sync/booking |
| B35 AC-1 | P7.5/6/7 partial | approved neutral schema/declared metadata/current existing events | Original exact geo_/fx_/translated column/event inventory consumer/schema owner; no verbatim schema adoption |
| B35 AC-2 | P7.5 | LOC-02/04 | Original B35 tables/tool/driver/vendor/performance full consumer criterion retained P10/P11/R26 |
| B35 AC-3 | P7.6 | FX-03/04 | Original B35 tables/tool/driver/vendor/performance full consumer criterion retained P10/P11/R26 |
| B35 AC-4 | external | — no generic completion claimed | R26/product finance/RTL/source fan-out/ops |
| B35 AC-5 | P7.8 | OPT-02 | Original B35 tables/tool/driver/vendor/performance full consumer criterion retained P10/P11/R26 |
| B35 AC-6 | P7.7 | GEO-08 | Original B35 tables/tool/driver/vendor/performance full consumer criterion retained P10/P11/R26 |
| B35 AC-7 | P7.5/6/7 partial | approved neutral schema/declared metadata/current existing events | Original exact geo_/fx_/translated column/event inventory consumer/schema owner; no verbatim schema adoption |
| B35 AC-8 | P7.7/P7.8 | GEO-09/OPT-07 | Original B35 tables/tool/driver/vendor/performance full consumer criterion retained P10/P11/R26 |
| B35 AC-9 | external | — no generic completion claimed | R26/product finance/RTL/source fan-out/ops |
| B35 AC-10 | external | — no generic completion claimed | R26/product finance/RTL/source fan-out/ops |
| B35 AC-11 | P7.5/6/7 partial | approved neutral schema/declared metadata/current existing events | Original exact geo_/fx_/translated column/event inventory consumer/schema owner; no verbatim schema adoption |
| B35 AC-12 | P7.6 | FX-01 | Original B35 tables/tool/driver/vendor/performance full consumer criterion retained P10/P11/R26 |
| B35 AC-13 | external | — no generic completion claimed | R26/product finance/RTL/source fan-out/ops |
| B35 AC-14 | external | — no generic completion claimed | R26/product finance/RTL/source fan-out/ops |
| B35 AC-15 | P7.9 | installed boxed/source manual | Original B35 tables/tool/driver/vendor/performance full consumer criterion retained P10/P11/R26 |


## Cross-phase edges and pre-mortem

P1 sealed52R/365parents→P2 adopted exact new candidate signatures/schema/equivalents/gates→P7 independent branches. Design P7 candidate is P2 planning input only, not execution reverse edge. .1→.2/.3/.5/.6/.7; .2+.3+P5.2→.4; optional P5.4 gates only FSM branch. .5+.7+actual P6 query/index/P4 label if selected→.8; .2–.8→.9. Address selected driver actual G-DEPENDENCY/read config; no engine clone. Pure recurrence/locale/rate/reference do not await central membership/workflow/Meili. .9→P8 actual metadata/P9 cohort→P10 actual source/10M/vendor/fleet/perf→P11 consumer. No cycle P5→P7 only, P6→P7 optional bridge only.

Mitigations tied to cases: Feb31/gap/count and cursor watchdog REC01/03/05/09/10; old tzdb REC11; UTC dedup/materialize edit/partial-watermark MAT01–05; afterCommit signal loss MAT02/07/P5 engine current source; task time total/dedup PRIM04/05; leaked locale LOC03/04; float display FX09; overwritten correction FX03/10; incomplete geography GEO01/02; mixed cursor generation GEO04/05; guessed timezone GEO07; API disclosure OPT04/06. Product B27 templates/DAG/acceptance/KPI and B29 booking/sync/calendar roles remain owning external criteria, not transformed into smaller generic tests. B35 finance finalization/tariffs/root fan-out/RTL/vendor backoff/retention remain R26/product/ops. New packages must join layer/literal scan inventories and per-package CI/aggregator/release matrix, plant violations to prevent vacuum green. Actual stack baseline PHP8.3–8.5/L12–13 policy qualified source/runtime/CI separately, optional extension/pin requirements explicit. No weakened coverage/root80 target85/per-package stricter floors.

## Whole-plan seam correction — AWR-06

`WriteToken` is the sole shared CoreX\Support\Data operation token; no separate
OperationToken class is produced. Its exact constructor is frozen in P6 data design.
For TaskRecurrenceCommand, expectedRecordVersion is the immutable admitted series revision;
for RateImporter it is the target publication revision (literal `absent` only when no
publication exists). Admission checks that expected revision under the same SQL boundary
as the operation claim/result. Same key + same canonical payload replays the recorded
result; different payload conflicts. A matching token never substitutes current scope/policy.

Shared DTO production/reuse follows the finite serial first-consumer protocol in
research/P6-data-design.md AWR-04/06. P7.4/6/7 can produce it after their own P2 gates
without waiting for P6 engine, transfer or search. Pure recurrence/locale stay independent.
