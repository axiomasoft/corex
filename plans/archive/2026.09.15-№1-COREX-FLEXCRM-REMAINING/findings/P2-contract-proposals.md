# P2 — Exact reusable contract proposals

## Contracts

### 1 — Exact surface index

| Contract | R | Owner | Existing anchor | Proposed section | Public surface | BC and version | Schema or dependency | Approval | Producer |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| C-P2-001 | R01 | P3.1 | Full P1 ledger Requirements/R01 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-001 | existing public ports kept; hidden candidate only | tenant-storage/keep + object-adapter/1; additive proposal; existing contract change major | none new DDL; source qualification only; no upstream ref adopted | proposal:G-STORAGE | P3.1 |
| C-P2-002 | R02 R33 | P3.2 | Full P1 ledger Requirements/R02/R33 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-002 | old = new; none | claim-lifecycle/keep; old=new | none new DDL; none new dependency | no-delta | P3.2 |
| C-P2-003 | R03 R04 R40 R43 R46 | P3.3 | Full P1 ledger Requirements/R03/R04/R40/R43/R46 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-003 | old = new; none | corex-membership/1 keep; old=new | none new DDL; none new dependency | no-delta | P3.3 |
| C-P2-004 | R05 R06 R52 | P3.4/P3.5 | Full P1 ledger Requirements/R05/R06/R52 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-004 | existing public ports kept; hidden candidate only | pair-inbox-final-write/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.4; none new dependency | proposal:G-SCHEMA | P3.4/P3.5 |
| C-P2-005 | R07 | P3.6 | Full P1 ledger Requirements/R07 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-005 | existing public ports kept; hidden candidate only | accounts-list/1 internal + redis-display/1; additive proposal; existing contract change major | none new DDL; none new dependency | proposal:G-CENTRAL | P3.6 |
| C-P2-006 | R51 R52 | P9 | Full P1 ledger Requirements/R51/R52 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-006 | old = new; none | auth-policy/keep; old=new | none new DDL; none new dependency | no-delta | P9 |
| C-P2-007 | R14 | P4.1 | Full P1 ledger Requirements/R14 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-007 | PanelProvider, PanelCatalogue | panel/1; additive proposal; existing contract change major | none new DDL; none new dependency | proposal:G-API | P4.1 |
| C-P2-008 | R15 | P4.2/P4.3 | Full P1 ledger Requirements/R15 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-008 | MediaLibrary, RelationLookup, MediaPolicy, ConversionDriver | media/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.8; none new dependency | proposal:G-API | P4.2/P4.3 |
| C-P2-009 | R16 | P4.4/P4.5 | Full P1 ledger Requirements/R16 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-009 | NotificationCenter, NotificationPolicy, NotificationChannelDriver, RecipientResolver, PreferenceRepository | notification/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.9; none new dependency | proposal:G-API | P4.4/P4.5 |
| C-P2-010 | R16 R10 | P5.5/P4.6 | Full P1 ledger Requirements/R16/R10 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-010 | DigestBuilder | notification-digest/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.10; none new dependency | proposal:G-API | P5.5/P4.6 |
| C-P2-011 | R28 | P4.7 | Full P1 ledger Requirements/R28 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-011 | SavedViewRepository, ViewSchemaProvider, ViewAccessPolicy | saved-view/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.11; none new dependency | proposal:G-API | P4.7 |
| C-P2-012 | R10 | P5.2/P5.3 | Full P1 ledger Requirements/R10 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-012 | WakeupScheduler, WakeupHandler, DueSource, WakeupSweeper, WakeupHint | wakeup/1 + routing-hint/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.12; none new dependency | proposal:G-API | P5.2/P5.3 |
| C-P2-013 | R09 R10 | P5.1/P5.4 | Full P1 ledger Requirements/R09/R10 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-013 | WorkflowBinding, EngineAdapter | engine-binding/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.13; source qualification only; no upstream ref adopted | proposal:G-API | P5.1/P5.4 |
| C-P2-014 | R11 R12 R17 R24 R25 R32 R52 | first ready P6.2/P6.4/P6.6/P7.4/P7.6/P7.7 | Full P1 ledger Requirements/R11/R12/R17/R24/R25/R32/R52 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-014 | three shared DTOs only | shared-data-foundation/1; additive proposal; existing contract change major | none new DDL; none new dependency | proposal:G-API | first ready P6.2/P6.4/P6.6/P7.4/P7.6/P7.7 |
| C-P2-015 | R11 | P6.1/P6.2 | Full P1 ledger Requirements/R11 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-015 | FieldCatalogue, AttributeStore, AttributeFilter | field-binding/1; additive proposal; existing contract change major | none new DDL; source qualification only; no upstream ref adopted | proposal:G-API | P6.1/P6.2 |
| C-P2-016 | R11 | P6.3 | Full P1 ledger Requirements/R11 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-016 | SchemaOperations | schema-bridge/1; additive proposal; existing contract change major | none new DDL; source qualification only; no upstream ref adopted | proposal:G-API | P6.3 |
| C-P2-017 | R12 | P6.4 | Full P1 ledger Requirements/R12 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-017 | TransferSource, RecordMapper, ImportTarget, TransferRunner | transfer/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.17; none new dependency | proposal:G-API | P6.4 |
| C-P2-018 | R12 R33 | P6.5 | Full P1 ledger Requirements/R12/R33 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-018 | ExportSource | export/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.18; none new dependency | proposal:G-API | P6.5 |
| C-P2-019 | R32 | P6.6 | Full P1 ledger Requirements/R32 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-019 | SearchSchema, SearchProvider, SearchHydrator | search/1; additive proposal; existing contract change major | none new DDL; none new dependency | proposal:G-API | P6.6 |
| C-P2-020 | R32 | P6.6/P6.7 | Full P1 ledger Requirements/R32 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-020 | SearchDocuments, IndexDriver | index-lifecycle/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.20; source qualification only; no upstream ref adopted | proposal:G-API | P6.6/P6.7 |
| C-P2-021 | R17 R19 | P7.3 | Full P1 ledger Requirements/R17/R19 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-021 | TaskPlanningCommand, TaskPlanningAccess | task-planning/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.21; none new dependency | proposal:G-API | P7.3 |
| C-P2-022 | R20 | P7.1/P7.2 | Full P1 ledger Requirements/R20 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-022 | RecurrenceExpander | recurrence/1; additive proposal; existing contract change major | none new DDL; source qualification only; no upstream ref adopted | proposal:G-API | P7.1/P7.2 |
| C-P2-023 | R17 R19 R20 | P7.4 | Full P1 ledger Requirements/R17/R19/R20 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-023 | TaskRecurrenceCommand | task-materialization/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.23; none new dependency | proposal:G-API | P7.4 |
| C-P2-024 | R23 | P7.5 | Full P1 ledger Requirements/R23 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-024 | LocaleResolver, TranslationReader | locale/1; additive proposal; existing contract change major | none new DDL; none new dependency | proposal:G-API | P7.5 |
| C-P2-025 | R24 | P7.6 | Full P1 ledger Requirements/R24 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-025 | CurrencyMetadata, RateProvider, RateImporter, CurrencyConverter, MoneyFormatter | rate-snapshot/1 + money/keep; additive proposal; existing contract change major | exact neutral DDL variants in §2.25; none new dependency | proposal:G-API | P7.6 |
| C-P2-026 | R25 | P7.7 | Full P1 ledger Requirements/R25 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-026 | GeographyProvider, DatasetImporter | reference-data/1; additive proposal; existing contract change major | exact neutral DDL variants in §2.26; source qualification only; no upstream ref adopted | proposal:G-API | P7.7 |
| C-P2-027 | R23 R25 R32 | P7.8 | Full P1 ledger Requirements/R23/R25/R32 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-027 | AddressSuggestProvider | intl-optional-bridge/1; additive proposal; existing contract change major | none new DDL; none new dependency | proposal:G-API | P7.8 |
| C-P2-028 | R08 R36 | P8.1/P8.2/P8.3/P8.4/P8.6 | Full P1 ledger Requirements/R08/R36 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-028 | ToolCatalogue, ToolInvoker, ToolRequestContext, ToolPolicy, ToolSchemaValidator, ToolExecutionGuard | tool-descriptor/1 + tool-schema/1 + tool-name/1; additive proposal; existing contract change major | none new DDL; source qualification only; no upstream ref adopted | proposal:G-API | P8.1/P8.2/P8.3/P8.4/P8.6 |
| C-P2-029 | R50 | P8.5 | Full P1 ledger Requirements/R50 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-029 | RegistryIntrospection | registry-introspection/1; additive proposal; existing contract change major | none new DDL; none new dependency | proposal:G-API | P8.5 |
| C-P2-030 | R45 R47 R52 | P9/P10/P11 | Full P1 ledger Requirements/R45/R47/R52 @ ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section | findings/P2-contract-proposals.md#C-P2-030 | old = new; none | cohort-qualification/keep; old=new | none new DDL; none new dependency | no-delta | P9/P10/P11 |

## 0 — Local proposal qualification

P2.3 is a source-bound catalogue proposal, no package/runtime/migration/dependency/foreign write. Full P1 map52R/365parents/682operative+6delimiter/9foundations and ledger are frozen SHA 682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9 / ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87. Every R keeps disposition/owner/destination/resume and unresolved actual protected receipt. APP/EXTERNAL/EXCLUDED omitted surfaces mean none new CoreX port, never acceptance/drop. All G gates/C01–09/DI34/AP13 retain original owning receipts; no material source choice or approval forged. Exact upstream source selection remains a protected owning qualification before any source/dependency write, with candidate outward boundary fixed independently. Tests below are future case specifications, not executed conformance.

# 3.1 — Source-complete proposal read group

## C-P2-001

### 2.1 — tenant-storage/keep + object-adapter/1

```json
{
  "profile": "tenant-storage/keep + object-adapter/1",
  "contract": "C-P2-001",
  "requirement_sources": {
    "R01": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B33 AC-5.a1, B33 AC-6.a1, B33 AC-7.a1, B33 AC-14.a1; non-AC reusable/programme binding: brief/sources/b33.md §3.9; source SHA256 be6366fc074821e4d5cfd58e3b5c953875a45cce8f948da6f93a57b419d15a27; section SHA256 c67bdb8e82c08afa6604d87bd1602e414039ee2ea389a57188eba0e871e123e5",
      "destination": "P3 / P9 installed / P10 provider"
    }
  },
  "owning_design": {
    "path": "research/P3-adapter-design.md",
    "sha256": "05dc04b765f133b4779a173c534e15d0ce6eb7ea020f77b5987cf5e391bf5525"
  },
  "files": [
    "packages/tenancy/src/Storage/ObjectTenantStorage.php",
    "packages/core/src/Storage/ScopedFilesystem.php"
  ],
  "indexed_files": [
    {
      "item": "P3.1",
      "path": "phases/P3/P3.1.md",
      "sha256": "83fe7fd78824a0a5d3d823ec4d905bf244d0ae617c7a153cb83573a1e50960c6",
      "files_exact": "packages/core/src/Storage/ScopedFilesystem.php; packages/tenancy/src/Storage/ContextTenantStorage.php; packages/tenancy/src/Storage/ObjectTenantStorage.php; packages/tenancy/src/StanclTenancyManager.php; packages/tenancy/src/Bootstrappers/WorkspaceContextBootstrapper.php; packages/tenancy/src/Listeners/RevertTenancyOnJobException.php; packages/tenancy/composer.json; packages/core/CHANGELOG.md; packages/tenancy/CHANGELOG.md; packages/core/tests/Storage/**; packages/tenancy/tests/Feature/TenantStorageTest.php; packages/tenancy/tests/Feature/TenancyManagerTest.php; packages/tenancy/tests/Feature/StorageContextConformanceTest.php; packages/tenancy/tests/Pg/PhysicalContextConformanceTest.php; findings/P3-storage-context.md. Bookkeeping restricted to own state/journal/owned projections/handoff. No public src/Contracts changes or other repo/foreign writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "tenant-storage/keep + object-adapter/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-STORAGE",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

Object adapter config is trusted server-only native s3 config; prefix tenants/<canonical-account-uuid>/; durable local driver byte-compatible. ScopedFilesystem localPaths=false rejects path()/url(), retained handles reject context switch/traversal; ephemeral stays local. Object usage/export explicitly false => StorageCapabilityUnavailable; no signed URL feature. Optional SDK constraint league/flysystem-aws-s3-v3:^3.0 is a source request, no selected immutable upstream ref or composer write. P3.1 records real isolated SDK/service source/license/lock/ref/hash suite; P10 alone proves live provider/IAM. On absent optional adapter local/boxed boot never requests object credentials.

Normative original candidate, same source bytes (no protected adoption):

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


Exact hidden candidate signatures (fully qualified types, compatibility defaults):

```php
declare(strict_types=1);
// packages/tenancy/src/Storage/ObjectTenantStorage.php; @internal final
// constructor property promotion:
public function __construct(
    private readonly \CoreX\Contracts\TenantContextResolver $contexts,
    private readonly \Illuminate\Filesystem\FilesystemManager $filesystems,
    private readonly array $durableConfig,
    private readonly string $ephemeralRoot,
);
// ScopedFilesystem adds last parameter, retains all old four promoted parameters:
private readonly bool $localPaths = true;
```

## C-P2-002

### 2.2 — claim-lifecycle/keep

```json
{
  "profile": "claim-lifecycle/keep",
  "contract": "C-P2-002",
  "requirement_sources": {
    "R02": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "non-AC reusable/programme binding: brief/sources/b33.md §2.4; source SHA256 be6366fc074821e4d5cfd58e3b5c953875a45cce8f948da6f93a57b419d15a27; section SHA256 c60614c003925abdcb0aae6e03b412132a127545c413c68ed4eee659fad6f310 · brief/sources/b33.md §3.2; source SHA256 be6366fc074821e4d5cfd58e3b5c953875a45cce8f948da6f93a57b419d15a27; section SHA256 de563588cda17cd9b86f26b8aca300c8ad0267ec1129548c92aacc1b884e8294",
      "destination": "P3 / P9 / P10"
    },
    "R33": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-30.a1, B15 AC-30.a2, B15 AC-30.a3, B18.§8.21.a1; non-AC reusable/programme binding: brief/sources/b26.md §4; source SHA256 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d; section SHA256 c6251d1ce66c2a592173a1335bb2d87bedf7211f8af542e51c772246e15d6855 · brief/sources/b26.md §5; source SHA256 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d; section SHA256 dcd928918a4462c603ba4973c05f0d99d1525927f8f6d3bd603248ace31e8f40 · brief/sources/b26.md §6; source SHA256 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d; section SHA256 f087a3a9e00538080df309b8b492c81c306f0c36a970382cd4f8a5ad3c9406e8 · brief/sources/b26.md §7; source SHA256 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d; section SHA256 9c0f37e713e6ac73a53c79471d98198a8b6dbd610190f0ccbe53bb9718d3afa1",
      "destination": "P3 / P9 / P10"
    }
  },
  "owning_design": {
    "path": "research/P3-adapter-design.md",
    "sha256": "05dc04b765f133b4779a173c534e15d0ce6eb7ea020f77b5987cf5e391bf5525"
  },
  "files": [],
  "indexed_files": [
    {
      "item": "P3.2",
      "path": "phases/P3/P3.2.md",
      "sha256": "f7fdbe9b9e199444d6c7fe3d80c1775a7a725620fc78d38941c492c05b4d3f92",
      "files_exact": "packages/tenancy/src/Actions/ClaimAccountSignup.php; packages/tenancy/src/Lifecycle/AccountLifecycle.php; packages/tenancy/src/Lifecycle/AccountLifecycleFsm.php; packages/tenancy/src/Provisioning/StanclTenantDatabaseLifecycle.php; packages/tenancy/src/Database/TemplateIntegrityVerifier.php; packages/tenancy/CHANGELOG.md; packages/tenancy/tests/Feature/AccountLifecycleTest.php; packages/tenancy/tests/Feature/ClaimLifecycleConformanceTest.php; packages/tenancy/tests/Pg/ClaimLifecycleConcurrencyTest.php; findings/P3-claim-maintenance.md. Bookkeeping restricted to own state/journal/owned projections/handoff. No public src/Contracts changes or other repo/foreign writes."
    }
  ],
  "old": "byte-identical existing public ports/payloads; no new registrations",
  "new": "none",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "no-delta",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

Old/new public TenantLifecycle/ClaimAccountSignup/tenant maintenance ports and payloads identical. Root claim unique/advisory transaction lock, qualified template/pool reservation, same request digest replay/different digest conflict and bounded retry preserved. No new saga/status/engine/port. Two reference connections sequentially observed are not an actual race receipt. Export-before-drop refuses unsealed/no-writer/invalid archive, never converts verifier existence into writer proof. P3.2 qualifies real race/released template/writer+restore receipts; P6.5 maintenance hook only AFTER its independent implementation, not a P3.2 prerequisite.

## C-P2-003

### 2.3 — corex-membership/1 keep

```json
{
  "profile": "corex-membership/1 keep",
  "contract": "C-P2-003",
  "requirement_sources": {
    "R03": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B34 AC-2.a1, B34 AC-3.a1, B34 AC-4.a1; non-AC reusable/programme binding: brief/sources/b34.md §8.4; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 f9df10429c5197c9c22df74536510bab61c42e0a49ba09f9afa81782600d726d",
      "destination": "P3 / P10"
    },
    "R04": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "non-AC reusable/programme binding: brief/sources/b34.md §4.2; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 ed6a299bb4da9a29c78fe0bef57ed3a562145a28e350db13f66ec8f2d90cebe6",
      "destination": "P2 qualification / P3 / P9 / P10"
    },
    "R40": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B11 AC-14.a1, B11 AC-14.a2, B11 AC-14.a3, B11 AC-15.a1, B11 AC-15.a2, B11 AC-16.a1, B11 AC-16.a2, B11 AC-31.a1, B11 AC-31.a2, B11 AC-31.a3, B11 AC-31.a4, B11 AC-31.a5, B11 AC-31.a6, B34 AC-19.a2, B34 AC-19.a3, B34 AC-19.a4, B34 AC-19.a5, B34 AC-19.a6, B34 AC-19.a7, B34 AC-19.a8, B34 AC-1.a1, B34 AC-1.a2, B34 AC-1.a3, B34 AC-5.a3, B34 AC-8.a2, B34 AC-9.a1, B34 AC-11.a1, B34 AC-12.a2, B34 AC-13.a1, B34 AC-14.a1, B34 AC-15.a1",
      "destination": "P2 target / P10"
    },
    "R43": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B11 AC-14.a4, B11 AC-17.a1, B11 AC-17.a2, B11 AC-17.a3, B11 AC-18.a1, B11 AC-18.a2, B11 AC-18.a3, B11 AC-19.a1, B11 AC-19.a2, B11 AC-19.a3, B11 AC-20.a1, B11 AC-20.a2, B34 AC-19.a1, B34 AC-19.a9, B34 AC-19.a10, B34 AC-19.a11, B34 AC-19.a12, B34 AC-8.a1, B34 AC-12.a1",
      "destination": "external"
    },
    "R46": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B12 AC-13.a3, B34 AC-5.a2",
      "destination": "P10"
    }
  },
  "owning_design": {
    "path": "findings/P2-identity-profile.md",
    "sha256": "e9819018a40d8d1d99bf41690c6057722536e5b98f8d75d8cb8615a404c057d9"
  },
  "files": [],
  "indexed_files": [
    {
      "item": "P3.3",
      "path": "phases/P3/P3.3.md",
      "sha256": "8735318de9f71c70b334d5bea1a5ae0a014ac4947c4896d09c73d7435990f8db",
      "files_exact": "packages/tenancy/src/Membership/Internal/Transport/**; packages/tenancy/src/Membership/Internal/Identity/ManagementIdentityHydrator.php; packages/tenancy/src/Identity/DatabaseIdentitySyncer.php; packages/tenancy/CHANGELOG.md; packages/tenancy/tests/Feature/CentralApiClientTest.php; packages/tenancy/tests/Feature/ManagementIdentityHydratorTest.php; packages/tenancy/tests/Feature/IdentitySyncerTest.php; packages/tenancy/tests/Feature/IdentityTransportConformanceTest.php; packages/tenancy/tests/Support/Membership/TransportFixture.php; findings/P3-identity-transport.md. Bookkeeping restricted to own state/journal/owned projections/handoff. No public src/Contracts changes or other repo/foreign writes."
    }
  ],
  "old": "byte-identical existing public ports/payloads; no new registrations",
  "new": "none",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "no-delta",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

Exact existing read/revoke constructors/query/response/header/signature bytes remain corex-membership/1; all read/revoke response keys, parser qualification, error matrix and certificate/token/proof-versus-capability checks are fixed in P2-identity-profile.md sections1/2 SHA-current. request_id correlation is not authority/idempotency on read; revoke same logical request key/digest and unknown outcome retain the key, never blind new-key retry. TenantContext current trusted ingress, payload never selects principal/database/workspace. No guessed central URI/issuer/resource/registration or identity merge. Effective DI34/AP13 remain unfilled original owning obligations.

Normative exact wire bytes/parser/error qualification: `findings/P2-identity-profile.md` SHA256 `e9819018a40d8d1d99bf41690c6057722536e5b98f8d75d8cb8615a404c057d9`, §1/§2. Old public shape unchanged; no target registration here.

## C-P2-004

### 2.4 — pair-inbox-final-write/1

```json
{
  "profile": "pair-inbox-final-write/1",
  "contract": "C-P2-004",
  "requirement_sources": {
    "R05": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "non-AC reusable/programme binding: brief/sources/b34.md §3.3; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 6d52586edee25950b913dd82ed74ec78468f607f6246f64bee7a94735dcb3c1e · brief/sources/b34.md §4.1; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 0ba16b4056ababa84359e82f59257e62d792b9491e981a26a9ca91e4443d5cb0",
      "destination": "P3 / P10"
    },
    "R06": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B34 AC-5.a1; non-AC reusable/programme binding: brief/sources/b34.md §3.2; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 d284ba530947da5b94fc278aa0b1d3c43bea445f3b5878b9b26511a62ca91fc5 · brief/sources/b34.md §4.1; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 0ba16b4056ababa84359e82f59257e62d792b9491e981a26a9ca91e4443d5cb0",
      "destination": "P3 / P10"
    },
    "R52": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B10 AC-31.a1, B10 AC-32.a1, B11 AC-28.a2, B11 AC-29.a1, B11 AC-29.a2, B11 AC-30.a1, B11 AC-32.a1, B11 AC-32.a2, B11 AC-32.a3, B11 AC-32.a4, B34 AC-7.a1, B34 AC-7.a2, B34 AC-7.a3, B34 AC-7.a4, B35 AC-1.a2, B35 AC-11.a1; non-AC reusable/programme binding: brief/sources/b38.md §4.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 89cc87c056131d6c2b94705c65d359c654a473a57507a45cd7e16aa3bc56e4f2 · brief/sources/b38.md §7; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 f9cba28861b728a7b4a83d80c9fc1dcac587d84246d4f09093b3ca061af5ae9e",
      "destination": "P1–P11 invariant / P9 / P10 / P11"
    }
  },
  "owning_design": {
    "path": "research/P3-adapter-design.md",
    "sha256": "05dc04b765f133b4779a173c534e15d0ce6eb7ea020f77b5987cf5e391bf5525"
  },
  "files": [
    "packages/tenancy/src/Membership/Internal/Persistence/PairStore.php",
    "packages/tenancy/src/Membership/Internal/Persistence/PairState.php",
    "packages/tenancy/src/Membership/Internal/Inbox/DurableInbox.php",
    "packages/tenancy/src/Membership/Internal/Credentials/FinalWriteAdmission.php",
    "packages/tenancy/database/migrations/2026_09_15_000700_create_membership_adapter_tables.php"
  ],
  "indexed_files": [
    {
      "item": "P3.4",
      "path": "phases/P3/P3.4.md",
      "sha256": "110094dd528c3b655abd28a347be3ed8479130773ef4dac9f2f94acd861485fe",
      "files_exact": "packages/tenancy/src/Membership/Internal/Persistence/**; packages/tenancy/src/Membership/Internal/Inbox/**; packages/tenancy/src/Membership/Internal/Refresh/RefreshCoordinator.php; packages/tenancy/src/Membership/Internal/Projection/ReferenceMembershipProjection.php; packages/tenancy/database/migrations/2026_09_15_000700_create_membership_adapter_tables.php; packages/tenancy/CHANGELOG.md; packages/tenancy/tests/Feature/MembershipPairStoreTest.php; packages/tenancy/tests/Feature/MembershipInboxTest.php; packages/tenancy/tests/Pg/MembershipPairStoreTest.php; packages/tenancy/tests/Pg/MembershipInboxCrashTest.php; packages/tenancy/tests/Pg/ProductionPairStoreConformanceTest.php; packages/tenancy/tests/Support/Membership/PairStoreFixture.php; findings/P3-durable-projection.md. Bookkeeping restricted to own state/journal/owned projections/handoff. No public src/Contracts changes or other repo/foreign writes."
    },
    {
      "item": "P3.5",
      "path": "phases/P3/P3.5.md",
      "sha256": "572d36797a974d98005edd2fe7daedf15381ba9c5cd6286138a24ec635f424f8",
      "files_exact": "packages/tenancy/src/Membership/Internal/Credentials/**; packages/tenancy/src/Membership/Internal/Timing/**; packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php; packages/tenancy/src/Http/Middleware/EnforceSessionLifetime.php; packages/tenancy/CHANGELOG.md; packages/tenancy/tests/Feature/FencedCredentialIssuanceTest.php; packages/tenancy/tests/Feature/MembershipExpiryTest.php; packages/tenancy/tests/Pg/FencedCredentialRaceTest.php; packages/tenancy/tests/Pg/MembershipExpiryRaceTest.php; packages/tenancy/tests/Pg/FinalWriteAdmissionConformanceTest.php; packages/tenancy/tests/Support/Membership/Consumer/CredentialFixture.php; findings/P3-final-write.md. Bookkeeping restricted to own state/journal/owned projections/handoff. No public src/Contracts changes or other repo/foreign writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "pair-inbox-final-write/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-SCHEMA",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

Reference compatibility defaults membership_pair_references/membership_trigger_inbox; production accepts ONLY tnt_membership_pairs/tnt_membership_inbox on one named tenant connection and one bound product. First production row denied=true. snapshot digest hashes identity/account/product/membership/state/R/G, excluding refresh request/time; equal-R changed state/G/digest denies, uint64 overflow remains denied. Fresh per-operation ID/E/F/AF and independent monotone restore witness: restored import clears active IDs/AF, advances E, denies before fresh pull. Witness is outside rollback set. Inbox INSERT ON CONFLICT+locked logical digest; owner UUID per attempt, acknowledge only after applied fresh authority/current DB lease. FinalWriteAdmission takes same ConnectionInterface object, clock Closure():ClockReading and qualified remaining commit bound; write callback Closure(ConnectionInterface):void contains only same-DB local SQL. Fresh elapsed/lease/fence clock check AFTER lock wait and again before commit; expiry/unknown clock/budget/regression/connection mismatch aborts with CredentialIssuanceDenied. No cookie/network/cross-DB effect in callback, no fixture credential tables installed. Optional boot does not create tables; absent schema denies production capability.

Normative original candidate, same source bytes (no protected adoption):

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




Exact hidden PairStore/DurableInbox changes; old constructor had only connection and old apply had four parameters. Existing methods and named parameter semantics stay compatible:

```php
declare(strict_types=1);
// PairStore, final readonly, @internal:
public function __construct(
    private \Illuminate\Database\ConnectionInterface $connection,
    private string $table = 'membership_pair_references',
);
public function apply(\CoreX\Tenancy\Membership\Internal\Persistence\PairKey $key, string $operationId, string $revision, string $revokeGeneration, ?string $snapshotDigest = null): \CoreX\Tenancy\Membership\Internal\Persistence\PairState;
public function isFor(\Illuminate\Database\ConnectionInterface $connection): bool;
// DurableInbox, final readonly, @internal:
public function __construct(
    private \Illuminate\Database\ConnectionInterface $connection,
    private string $table = 'membership_trigger_inbox',
);
```

The production snapshotDigest is mandatory validated lowercase SHA64; null remains ONLY reference mode compatibility. Candidate PairState source declaration (existing guards kept, added property validation additionally mandatory by the normative profile):

```php
<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Internal\Persistence;

use CoreX\Tenancy\Membership\Internal\Transport\Value;

/** @internal Immutable view of the disposable pair-store mapping. */
final readonly class PairState
{
    public function __construct(
        public string $revision,
        public string $revokeGeneration,
        public string $localEpoch,
        public string $operationFence,
        public ?string $applicationFence,
        public ?string $operationId,
        public ?string $appliedOperationId,
        public ?string $barrierId,
        public bool $denied,
        public ?string $snapshotDigest = null,
    ) {
        Value::counter($revision);
        Value::counter($revokeGeneration);
        Value::counter($localEpoch);
        Value::counter($operationFence);

        if ($applicationFence !== null) {
            Value::counter($applicationFence);
        }

        if ($operationId !== null) {
            Value::uuid($operationId);
        }

        if ($appliedOperationId !== null) {
            Value::uuid($appliedOperationId);
        }

        if ($barrierId !== null) {
            Value::uuid($barrierId);
        }
    }
}
```

# 3.2 — Source-complete proposal read group

## C-P2-005

### 2.5 — accounts-list/1 internal + redis-display/1

```json
{
  "profile": "accounts-list/1 internal + redis-display/1",
  "contract": "C-P2-005",
  "requirement_sources": {
    "R07": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B34 AC-6.a1, B34 AC-6.a2, B34 AC-10.a1; non-AC reusable/programme binding: brief/sources/b34.md §3.4; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 d419a0c225037faf467c8efacfc070b987405c9ef14e39ab76dc1580ff6a8ceb · brief/sources/b34.md §4.1; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 0ba16b4056ababa84359e82f59257e62d792b9491e981a26a9ca91e4443d5cb0",
      "destination": "P3 / P10"
    }
  },
  "owning_design": {
    "path": "research/P3-adapter-design.md",
    "sha256": "05dc04b765f133b4779a173c534e15d0ce6eb7ea020f77b5987cf5e391bf5525"
  },
  "files": [
    "packages/tenancy/src/Membership/Internal/Transport/AccountsReadTransport.php",
    "packages/tenancy/src/Membership/Internal/Transport/StrictAccountsTransport.php",
    "packages/tenancy/src/Membership/Menu/RedisAccountMenuCache.php"
  ],
  "indexed_files": [
    {
      "item": "P3.6",
      "path": "phases/P3/P3.6.md",
      "sha256": "5fe60fc22c5b15b169a5b6a3aeeea9fa46fbdcec8de8411abe500e1f39424025",
      "files_exact": "packages/tenancy/src/Membership/Internal/Switcher/**; packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php; packages/tenancy/CHANGELOG.md; packages/tenancy/tests/Feature/AccountSwitcherTest.php; packages/tenancy/tests/Feature/RedisAccountMenuConformanceTest.php; packages/tenancy/tests/Support/Membership/AccountMenuFixture.php; findings/P3-account-list.md. Bookkeeping restricted to own state/journal/owned projections/handoff. No public src/Contracts changes or other repo/foreign writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "accounts-list/1 internal + redis-display/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-CENTRAL",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

P2-identity-profile.md section3.1 freezes GET query/response/account-list parser candidate; it is unregistered and no new public Accounts DTO is introduced. Authenticated current version source uses that read; membership_version opaque, never numeric/Redis-only authority. Redis display cache TTL300s is optional and untrusted display only; missing/corrupt/version-loss => source read/unavailable, not grant. Click always fresh strict membership/policy. C09 decision and exact externally owned target/profile/API compatibility receipt required before activation; no silent profile negotiation or guessed endpoint.

Normative exact wire bytes/parser/error qualification: `findings/P2-identity-profile.md` SHA256 `e9819018a40d8d1d99bf41690c6057722536e5b98f8d75d8cb8615a404c057d9`, §3/§3.1. Old public shape unchanged; no target registration here.

## C-P2-006

### 2.6 — auth-policy/keep

```json
{
  "profile": "auth-policy/keep",
  "contract": "C-P2-006",
  "requirement_sources": {
    "R51": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B11 AC-1.a2, B11 AC-1.a3, B11 AC-21.a1, B11 AC-21.a2, B11 AC-22.a1, B11 AC-22.a2, B11 AC-23.a1, B11 AC-23.a2, B11 AC-23.a3, B11 AC-24.a1, B11 AC-25.a1, B11 AC-25.a2, B11 AC-26.a1, B11 AC-27.a1, B11 AC-28.a3, B32 AC-8.a1, B32 AC-9.a1, B32 AC-10.a1, B32 AC-10.a2; non-AC reusable/programme binding: brief/sources/b38.md §2.3; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 1c7d22253a4d31723a51df16db2cbf1c16f2fa5815fa1cbe9ebb745ae8153cd1 · brief/sources/b38.md §3.3; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 a829858b570baa668adf1e48d4b8acabae1774e719501e6ae92ada058c5e84e8",
      "destination": "P2 qualification / P9"
    },
    "R52": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B10 AC-31.a1, B10 AC-32.a1, B11 AC-28.a2, B11 AC-29.a1, B11 AC-29.a2, B11 AC-30.a1, B11 AC-32.a1, B11 AC-32.a2, B11 AC-32.a3, B11 AC-32.a4, B34 AC-7.a1, B34 AC-7.a2, B34 AC-7.a3, B34 AC-7.a4, B35 AC-1.a2, B35 AC-11.a1; non-AC reusable/programme binding: brief/sources/b38.md §4.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 89cc87c056131d6c2b94705c65d359c654a473a57507a45cd7e16aa3bc56e4f2 · brief/sources/b38.md §7; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 f9cba28861b728a7b4a83d80c9fc1dcac587d84246d4f09093b3ca061af5ae9e",
      "destination": "P1–P11 invariant / P9 / P10 / P11"
    }
  },
  "owning_design": {
    "path": "findings/P2-auth-ownership.md",
    "sha256": "041196cc9db87c6f5141c73a0a26f224472082c8c6cf62acfc5af97320ff7af6"
  },
  "files": [],
  "indexed_files": [],
  "old": "byte-identical existing public ports/payloads; no new registrations",
  "new": "none",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "no-delta",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

No public auth-policy delta; preserve CoreAuthServiceProvider scoped principal/access/workspace composition and current AccessChecker/ScopeResolver seams. Missing policy default denies, including late API/job/record entry. Public core-only host omits auth and all private dependencies; explicitly installed corex/auth adapter retains its genuine AzGuard dependency. Role-writer C08 remains externally owned, no new role engine or self-attested authority. Restore scoped request/job context in finally; no auth.* global rewrite, singleton actor or payload connection switch.

## C-P2-007

### 2.7 — panel/1

```json
{
  "profile": "panel/1",
  "contract": "C-P2-007",
  "requirement_sources": {
    "R14": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B16.§8.1.a2, B16.§8.20.a1, B16.§8.20.a5; non-AC reusable/programme binding: brief/sources/b16.md §3.1; source SHA256 ed4096e7e7ac522a9a6d89d3c0870b0964ddd91e1ff5c29f3413072642afeaaf; section SHA256 077a7784fcc7c66085836ee852a1e38e551599b65b6591c32e99230a768e7159",
      "destination": "P2 / P4"
    }
  },
  "owning_design": {
    "path": "research/P4-extension-design.md",
    "sha256": "957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a"
  },
  "files": [
    "packages/panels/src/Contracts/PanelProvider.php",
    "packages/panels/src/Contracts/PanelCatalogue.php",
    "packages/panels/src/Data/PanelDescriptor.php",
    "packages/panels/src/Data/EntityReference.php",
    "packages/panels/src/Data/PanelReference.php",
    "packages/panels/src/Data/PanelContext.php",
    "packages/panels/src/Data/PanelAccess.php"
  ],
  "indexed_files": [
    {
      "item": "P4.1",
      "path": "phases/P4/P4.1.md",
      "sha256": "bc3bfebcab4feed496d2e815ce1d355c25fb11bec6303b9908123ba30c897bb0",
      "files_exact": "packages/panels/composer.json; packages/panels/src/PanelsModule.php; packages/panels/src/PanelsServiceProvider.php; packages/panels/src/Contracts/PanelProvider.php; packages/panels/src/Contracts/PanelCatalogue.php; packages/panels/src/Data/**; packages/panels/src/Internal/ModulePanelCatalogue.php; packages/panels/config/corex-panels.php; packages/panels/tests/**; packages/panels/phpunit.xml; packages/panels/phpstan.neon; packages/panels/pint.json; packages/panels/CHANGELOG.md; packages/panels/README.md; composer.json; composer.lock; .github/workflows/ci.yml; findings/P4-panels.md. Bookkeeping only own journal/state/owned projections/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "panel/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

PanelProvider descriptor is metadata, not permission. Descriptor.version=panel/1; key/provider/action/module keys from code registration; actorKind is trusted guest/system/user service identity, actorId null only when that kind allows it. Capability requiredCapability null explicitly means no capability claim, still current activation/entity/record/provider policy required. forEntity returns list<PanelDescriptor> visible under current context, unknown/disabled providers omitted; access unknown provider denies and invalid reference throws InvalidPanelReference. Guest parent metadata and action access checked independently; supportedActions only opaque registered strings. Optional Filament absent uses headless catalogue; no ninth extender/capability enum or new compiler.

Target `packages/panels/src/Contracts/PanelProvider.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Panels\Contracts;
interface PanelProvider {
    public function descriptor(): \CoreX\Panels\Data\PanelDescriptor;
    public function access(\CoreX\Panels\Data\PanelContext $context, \CoreX\Panels\Data\PanelReference $panel): \CoreX\Panels\Data\PanelAccess;
}
```

Target `packages/panels/src/Contracts/PanelCatalogue.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Panels\Contracts;
interface PanelCatalogue {
    /** @return list<PanelDescriptor> */
    public function forEntity(\CoreX\Panels\Data\PanelContext $context, \CoreX\Panels\Data\EntityReference $entity): array;
    public function access(\CoreX\Panels\Data\PanelContext $context, \CoreX\Panels\Data\PanelReference $panel): \CoreX\Panels\Data\PanelAccess;
}
```

Target `packages/panels/src/Data/PanelDescriptor.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Panels\Data;
final readonly class PanelDescriptor {
    /** @param list<string> $supportedActions */
    public function __construct(
        public string $version,
        public string $key,
        public string $owningModule,
        public ?string $requiredCapability,
        public array $supportedActions,
        public string $label,
    ) {}
}
```

Target `packages/panels/src/Data/EntityReference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Panels\Data;
final readonly class EntityReference {
    public function __construct(
        public string $handle,
        public string $id,
    ) {}
}
```

Target `packages/panels/src/Data/PanelReference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Panels\Data;
final readonly class PanelReference {
    public function __construct(
        public string $providerKey,
        public string $panelId,
        public ?\CoreX\Panels\Data\EntityReference $parent,
    ) {}
}
```

Target `packages/panels/src/Data/PanelContext.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Panels\Data;
final readonly class PanelContext {
    public function __construct(
        public \CoreX\Tenancy\TenantContext $tenant,
        public string $actorKind,
        public ?string $actorId,
    ) {}
}
```

Target `packages/panels/src/Data/PanelAccess.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Panels\Data;
final readonly class PanelAccess {
    /** @param list<string> $actions */
    public function __construct(
        public bool $view,
        public array $actions,
        public ?string $reason,
    ) {}
}
```

## C-P2-008

### 2.8 — media/1

```json
{
  "profile": "media/1",
  "contract": "C-P2-008",
  "requirement_sources": {
    "R15": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B16.§8.12.a1, B16.§8.12.a2; non-AC reusable/programme binding: brief/sources/b16.md §3.5; source SHA256 ed4096e7e7ac522a9a6d89d3c0870b0964ddd91e1ff5c29f3413072642afeaaf; section SHA256 87843cb152622de9315f36f69d5e73410afdb8bc8421102745e5ff049a8ca075",
      "destination": "P2 / P4"
    }
  },
  "owning_design": {
    "path": "research/P4-extension-design.md",
    "sha256": "957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a"
  },
  "files": [
    "packages/media/src/Contracts/MediaLibrary.php",
    "packages/media/src/Contracts/RelationLookup.php",
    "packages/media/src/Contracts/MediaPolicy.php",
    "packages/media/src/Contracts/ConversionDriver.php",
    "packages/media/src/Data/MediaContext.php",
    "packages/media/src/Data/UploadInput.php",
    "packages/media/src/Data/AttachmentInput.php",
    "packages/media/src/Data/AttachmentRef.php",
    "packages/media/src/Data/MediaFileRef.php",
    "packages/media/src/Data/MediaReadRef.php",
    "packages/media/src/Data/MediaConversionRef.php",
    "packages/media/src/Data/DeleteResult.php",
    "packages/media/src/Data/MediaSubject.php",
    "packages/media/src/Data/ConversionProfile.php",
    "packages/media/src/Data/ConversionInput.php",
    "packages/media/src/Data/ConversionOutput.php",
    "packages/media/src/Data/MediaAction.php"
  ],
  "indexed_files": [
    {
      "item": "P4.2",
      "path": "phases/P4/P4.2.md",
      "sha256": "938923b14e6db56e51f80489c8b21b5a2e7004670b5e381607c7c80ee7eed492",
      "files_exact": "packages/media/composer.json; packages/media/src/MediaModule.php; packages/media/src/MediaServiceProvider.php; packages/media/src/Contracts/MediaLibrary.php; packages/media/src/Contracts/RelationLookup.php; packages/media/src/Contracts/MediaPolicy.php; packages/media/src/Data/**; packages/media/src/Internal/SqlMediaLibrary.php; packages/media/src/Internal/SqlRelationLookup.php; packages/media/src/Internal/DenyMediaPolicy.php; packages/media/src/Internal/MediaOperationStore.php; packages/media/src/Jobs/PurgeMedia.php; packages/media/src/Commands/SweepMediaOperations.php; packages/media/config/corex-media.php; packages/media/database/migrations/2026_09_15_000000_create_med_files_table.php; packages/media/database/migrations/2026_09_15_000001_create_med_relations_table.php; packages/media/database/migrations/2026_09_15_000002_create_med_operations_table.php; packages/media/tests/**; packages/media/phpunit.xml; packages/media/phpstan.neon; packages/media/pint.json; packages/media/CHANGELOG.md; packages/media/README.md; composer.json; composer.lock; .github/workflows/ci.yml; findings/P4-media-lifecycle.md. Bookkeeping only own journal/state/owned projections/handoff; no foreign/product/live writes."
    },
    {
      "item": "P4.3",
      "path": "phases/P4/P4.3.md",
      "sha256": "008bb82892dbc63001096a8ebb750fd5b8747e6062124e8e06e38d0c6e479f6a",
      "files_exact": "packages/media/src/Contracts/ConversionDriver.php; packages/media/src/Data/**; packages/media/src/Internal/ConversionProfiles.php; packages/media/src/Internal/ConversionCoordinator.php; packages/media/src/Internal/GdThumbnailDriver.php; packages/media/src/Internal/MediaOperationStore.php; packages/media/src/Jobs/ConvertMedia.php; packages/media/src/Jobs/PurgeMedia.php; packages/media/src/Commands/SweepMediaOperations.php; packages/media/src/MediaServiceProvider.php; packages/media/src/MediaModule.php; packages/media/config/corex-media.php; packages/media/database/migrations/2026_09_15_000003_create_med_conversions_table.php; packages/media/tests/Conversion/**; packages/media/tests/Pg/ConversionConcurrencyTest.php; packages/media/composer.json; packages/media/CHANGELOG.md; packages/media/README.md; .github/workflows/ci.yml; findings/P4-media-conversions.md. Bookkeeping only own journal/state/owned projections/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "media/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

MediaAction enum store/read/attach/detach/delete; default deny; AttachmentInput.slot and purpose are code-registered, existing entity enabled/current target ACL required. UUID files are the owning media boundary, opaque consumer entityId is not a new global UUID rule. UploadInput contains trusted UploadedFile (no remote URL/path/disk selector); readonly does not make resources immutable. MediaReadRef/ConversionOutput openStream Closure():resource returns a current-context authorized bounded stream, consumer must close before context exit, never serialize/path/URL. File facts server detected; ready-only conversion or null=not ready, not provider success. Lock file then relations/operations; reserve staged request digest before IO, immutable attempt object, verify bytes/hash then finalize under current version/policy. Attach/deleting race shares file lock; in-use throws MediaFileInUse with policy-filtered summaries. DeleteResult distinguishes accepted SQL delete from purgePending. Conversion profile digest binds implementation/mime/limits maxBytes/pixels/dimension/seconds; context/version/delete rechecked after IO. Unknown storage/provider finalization reconciles immutable attempt facts, never overwrites newer ready output. Exceptions InvalidMediaInput,MediaAccessDenied,MediaFileInUse,MediaOperationConflict,StorageCapabilityUnavailable,ConversionUnavailable are typed internal exception classes outside Contracts; no error paths expose provider keys.

Target `packages/media/src/Contracts/MediaLibrary.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Contracts;
interface MediaLibrary {
    public function store(\CoreX\Media\Data\MediaContext $context, \CoreX\Media\Data\UploadInput $input): \CoreX\Media\Data\MediaFileRef;
    public function read(\CoreX\Media\Data\MediaContext $context, string $fileId): \CoreX\Media\Data\MediaReadRef;
    public function delete(\CoreX\Media\Data\MediaContext $context, string $fileId): \CoreX\Media\Data\DeleteResult;
    public function conversion(\CoreX\Media\Data\MediaContext $context, string $fileId, string $profile): ?\CoreX\Media\Data\MediaConversionRef;
}
```

Target `packages/media/src/Contracts/RelationLookup.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Contracts;
interface RelationLookup {
    public function attach(\CoreX\Media\Data\MediaContext $context, \CoreX\Media\Data\AttachmentInput $input): void;
    public function detach(\CoreX\Media\Data\MediaContext $context, \CoreX\Media\Data\AttachmentInput $input): void;
    /** @return list<AttachmentRef> */
    public function usages(\CoreX\Media\Data\MediaContext $context, string $fileId, int $limit = 50): array;
}
```

Target `packages/media/src/Contracts/MediaPolicy.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Contracts;
interface MediaPolicy {
    public function allows(\CoreX\Media\Data\MediaContext $context, \CoreX\Media\Data\MediaAction $action, \CoreX\Media\Data\MediaSubject $subject): bool;
}
```

Target `packages/media/src/Contracts/ConversionDriver.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Contracts;
interface ConversionDriver {
    public function profile(): \CoreX\Media\Data\ConversionProfile;
    public function convert(\CoreX\Media\Data\ConversionInput $input): \CoreX\Media\Data\ConversionOutput;
}
```

Target `packages/media/src/Data/MediaContext.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class MediaContext {
    public function __construct(
        public \CoreX\Tenancy\TenantContext $tenant,
        public string $actorKind,
        public ?string $actorId,
    ) {}
}
```

Target `packages/media/src/Data/UploadInput.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class UploadInput {
    public function __construct(
        public string $requestKey,
        public \Illuminate\Http\UploadedFile $file,
        public string $purpose,
    ) {}
}
```

Target `packages/media/src/Data/AttachmentInput.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class AttachmentInput {
    public function __construct(
        public string $fileId,
        public string $entityHandle,
        public string $entityId,
        public string $slot,
    ) {}
}
```

Target `packages/media/src/Data/AttachmentRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class AttachmentRef {
    public function __construct(
        public string $fileId,
        public string $entityHandle,
        public string $entityId,
        public string $slot,
    ) {}
}
```

Target `packages/media/src/Data/MediaFileRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class MediaFileRef {
    public function __construct(
        public string $id,
        public string $kind,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public string $state,
    ) {}
}
```

Target `packages/media/src/Data/MediaReadRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class MediaReadRef {
    public function __construct(
        public string $fileId,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public \Closure $openStream,
    ) {}
}
```

Target `packages/media/src/Data/MediaConversionRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class MediaConversionRef {
    public function __construct(
        public string $fileId,
        public string $profile,
        public string $profileDigest,
        public string $sourceVersion,
        public \CoreX\Media\Data\MediaReadRef $output,
    ) {}
}
```

Target `packages/media/src/Data/DeleteResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class DeleteResult {
    public function __construct(
        public bool $deleted,
        public bool $purgePending,
    ) {}
}
```

Target `packages/media/src/Data/MediaSubject.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class MediaSubject {
    /** @param array<string, null|bool|int|string|array> $metadata */
    public function __construct(
        public ?string $fileId,
        public ?\CoreX\Media\Data\AttachmentInput $entity,
        public array $metadata,
    ) {}
}
```

Target `packages/media/src/Data/ConversionProfile.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class ConversionProfile {
    /** @param list<string> $inputMimes */
    public function __construct(
        public string $key,
        public string $version,
        public string $digest,
        public array $inputMimes,
        public string $outputMime,
        public int $maxBytes,
        public int $maxPixels,
        public int $maxDimension,
        public int $maxSeconds,
    ) {}
}
```

Target `packages/media/src/Data/ConversionInput.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class ConversionInput {
    public function __construct(
        public \CoreX\Media\Data\MediaContext $context,
        public \CoreX\Media\Data\MediaReadRef $file,
        public \CoreX\Media\Data\ConversionProfile $profile,
        public string $sourceVersion,
        public string $attemptKey,
    ) {}
}
```

Target `packages/media/src/Data/ConversionOutput.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
final readonly class ConversionOutput {
    public function __construct(
        public \Closure $openStream,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public int $width,
        public int $height,
    ) {}
}
```

Target `packages/media/src/Data/MediaAction.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Media\Data;
enum MediaAction: string {
    case Store = 'store';
    case Read = 'read';
    case Attach = 'attach';
    case Detach = 'detach';
    case Delete = 'delete';
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `3b1f3848331d23cc0c4d06ba37d4c66d35ec49c41566fc6daa039c5fa6d9ed46`:

```sql
CREATE TABLE med_files (
 id UUID PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (request_digest ~ '^[a-f0-9]{64}$'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 version NUMERIC(20,0) NOT NULL CHECK (version BETWEEN 0 AND 18446744073709551615),
 object_key VARCHAR(1024) NOT NULL CHECK (length(object_key) <= 1024),
 mime_type VARCHAR(128) NOT NULL CHECK (length(mime_type) <= 128),
 kind VARCHAR(64) NOT NULL CHECK (length(kind) <= 64),
 bytes NUMERIC(20,0) NOT NULL CHECK (bytes BETWEEN 0 AND 18446744073709551615),
 checksum CHAR(64) NOT NULL CHECK (checksum ~ '^[a-f0-9]{64}$'),
 uploaded_actor VARCHAR(128) NOT NULL CHECK (length(uploaded_actor) <= 128),
 workspace_scope_key VARCHAR(255) CHECK (length(workspace_scope_key) <= 255),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 deleted_at TIMESTAMPTZ,
 UNIQUE(request_key),
 CHECK (state IN ('staged','ready','deleting','deleted','quarantined'))
);
CREATE TABLE med_relations (
 id UUID PRIMARY KEY NOT NULL,
 file_id UUID NOT NULL,
 entity_handle VARCHAR(128) NOT NULL CHECK (length(entity_handle) <= 128),
 entity_id VARCHAR(128) NOT NULL CHECK (length(entity_id) <= 128),
 slot VARCHAR(64) NOT NULL CHECK (length(slot) <= 64),
 UNIQUE(file_id,entity_handle,entity_id,slot),
 FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT
);
CREATE INDEX med_relations_scan_idx ON med_relations (file_id);
CREATE TABLE med_operations (
 id UUID PRIMARY KEY NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 kind VARCHAR(16) NOT NULL CHECK (length(kind) <= 16),
 file_id UUID NOT NULL,
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 source_version NUMERIC(20,0) NOT NULL CHECK (source_version BETWEEN 0 AND 18446744073709551615),
 profile_digest CHAR(64) CHECK (profile_digest ~ '^[a-f0-9]{64}$'),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 attempts INTEGER NOT NULL,
 next_attempt TIMESTAMPTZ,
 lease_until TIMESTAMPTZ,
 attempt_output_key VARCHAR(1024) CHECK (length(attempt_output_key) <= 1024),
 UNIQUE(operation_key),
 FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT,
 CHECK (kind IN ('conversion','purge')),
 CHECK (state IN ('pending','claimed','ready','failed','unknown','cancelled')),
 CHECK (attempts >= 0)
);
CREATE INDEX med_operations_scan_idx ON med_operations (state,next_attempt);
CREATE TABLE med_conversions (
 id UUID PRIMARY KEY NOT NULL,
 file_id UUID NOT NULL,
 profile_key VARCHAR(128) NOT NULL CHECK (length(profile_key) <= 128),
 profile_digest CHAR(64) NOT NULL CHECK (profile_digest ~ '^[a-f0-9]{64}$'),
 source_version NUMERIC(20,0) NOT NULL CHECK (source_version BETWEEN 0 AND 18446744073709551615),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 output_key VARCHAR(1024) CHECK (length(output_key) <= 1024),
 mime_type VARCHAR(128) CHECK (length(mime_type) <= 128),
 bytes NUMERIC(20,0) CHECK (bytes BETWEEN 0 AND 18446744073709551615),
 checksum CHAR(64) CHECK (checksum ~ '^[a-f0-9]{64}$'),
 width INTEGER,
 height INTEGER,
 UNIQUE(file_id,profile_key,profile_digest,source_version),
 FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT,
 CHECK (state IN ('pending','ready','failed'))
);
```

Profile `sqlite.uuid` SQL SHA256 `3e184a94f034a9bb78c472949de1c34f999339d6e7cb6a381d2a516e1bf034be`:

```sql
CREATE TABLE med_files (
 id TEXT PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (length(request_digest) = 64 AND request_digest NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 version CHAR(20) NOT NULL CHECK (length(version) = 20 AND version NOT GLOB '*[^0-9]*' AND version <= '18446744073709551615'),
 object_key VARCHAR(1024) NOT NULL CHECK (length(object_key) <= 1024),
 mime_type VARCHAR(128) NOT NULL CHECK (length(mime_type) <= 128),
 kind VARCHAR(64) NOT NULL CHECK (length(kind) <= 64),
 bytes CHAR(20) NOT NULL CHECK (length(bytes) = 20 AND bytes NOT GLOB '*[^0-9]*' AND bytes <= '18446744073709551615'),
 checksum CHAR(64) NOT NULL CHECK (length(checksum) = 64 AND checksum NOT GLOB '*[^a-f0-9]*'),
 uploaded_actor VARCHAR(128) NOT NULL CHECK (length(uploaded_actor) <= 128),
 workspace_scope_key VARCHAR(255) CHECK (length(workspace_scope_key) <= 255),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 deleted_at TEXT CHECK (length(deleted_at) = 27 AND substr(deleted_at,27,1) = 'Z'),
 UNIQUE(request_key),
 CHECK (state IN ('staged','ready','deleting','deleted','quarantined'))
);
CREATE TABLE med_relations (
 id TEXT PRIMARY KEY NOT NULL,
 file_id TEXT NOT NULL,
 entity_handle VARCHAR(128) NOT NULL CHECK (length(entity_handle) <= 128),
 entity_id VARCHAR(128) NOT NULL CHECK (length(entity_id) <= 128),
 slot VARCHAR(64) NOT NULL CHECK (length(slot) <= 64),
 UNIQUE(file_id,entity_handle,entity_id,slot),
 FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT
);
CREATE INDEX med_relations_scan_idx ON med_relations (file_id);
CREATE TABLE med_operations (
 id TEXT PRIMARY KEY NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 kind VARCHAR(16) NOT NULL CHECK (length(kind) <= 16),
 file_id TEXT NOT NULL,
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 source_version CHAR(20) NOT NULL CHECK (length(source_version) = 20 AND source_version NOT GLOB '*[^0-9]*' AND source_version <= '18446744073709551615'),
 profile_digest CHAR(64) CHECK (length(profile_digest) = 64 AND profile_digest NOT GLOB '*[^a-f0-9]*'),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 attempts INTEGER NOT NULL,
 next_attempt TEXT CHECK (length(next_attempt) = 27 AND substr(next_attempt,27,1) = 'Z'),
 lease_until TEXT CHECK (length(lease_until) = 27 AND substr(lease_until,27,1) = 'Z'),
 attempt_output_key VARCHAR(1024) CHECK (length(attempt_output_key) <= 1024),
 UNIQUE(operation_key),
 FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT,
 CHECK (kind IN ('conversion','purge')),
 CHECK (state IN ('pending','claimed','ready','failed','unknown','cancelled')),
 CHECK (attempts >= 0)
);
CREATE INDEX med_operations_scan_idx ON med_operations (state,next_attempt);
CREATE TABLE med_conversions (
 id TEXT PRIMARY KEY NOT NULL,
 file_id TEXT NOT NULL,
 profile_key VARCHAR(128) NOT NULL CHECK (length(profile_key) <= 128),
 profile_digest CHAR(64) NOT NULL CHECK (length(profile_digest) = 64 AND profile_digest NOT GLOB '*[^a-f0-9]*'),
 source_version CHAR(20) NOT NULL CHECK (length(source_version) = 20 AND source_version NOT GLOB '*[^0-9]*' AND source_version <= '18446744073709551615'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 output_key VARCHAR(1024) CHECK (length(output_key) <= 1024),
 mime_type VARCHAR(128) CHECK (length(mime_type) <= 128),
 bytes CHAR(20) CHECK (length(bytes) = 20 AND bytes NOT GLOB '*[^0-9]*' AND bytes <= '18446744073709551615'),
 checksum CHAR(64) CHECK (length(checksum) = 64 AND checksum NOT GLOB '*[^a-f0-9]*'),
 width INTEGER,
 height INTEGER,
 UNIQUE(file_id,profile_key,profile_digest,source_version),
 FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT,
 CHECK (state IN ('pending','ready','failed'))
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

# 3.3 — Source-complete proposal read group

## C-P2-009

### 2.9 — notification/1

```json
{
  "profile": "notification/1",
  "contract": "C-P2-009",
  "requirement_sources": {
    "R16": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B16.§8.14.a1, B16.§8.14.a2, B16.§8.15.a1, B16.§8.15.a2, B16.§8.20.a4; non-AC reusable/programme binding: brief/sources/b16.md §3.6; source SHA256 ed4096e7e7ac522a9a6d89d3c0870b0964ddd91e1ff5c29f3413072642afeaaf; section SHA256 c46bc79156f8e8f70fcbdf659471fcecc69d4ecd8c7e9d678e216d5540d4a2de",
      "destination": "P2 / P4"
    }
  },
  "owning_design": {
    "path": "research/P4-extension-design.md",
    "sha256": "957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a"
  },
  "files": [
    "packages/notifications/src/Contracts/NotificationCenter.php",
    "packages/notifications/src/Contracts/NotificationPolicy.php",
    "packages/notifications/src/Contracts/NotificationChannelDriver.php",
    "packages/notifications/src/Contracts/RecipientResolver.php",
    "packages/notifications/src/Contracts/PreferenceRepository.php",
    "packages/notifications/src/Data/NotificationContext.php",
    "packages/notifications/src/Data/RecipientReference.php",
    "packages/notifications/src/Data/EntityReference.php",
    "packages/notifications/src/Data/NotificationInput.php",
    "packages/notifications/src/Data/NotificationRef.php",
    "packages/notifications/src/Data/InboxQuery.php",
    "packages/notifications/src/Data/CancelResult.php",
    "packages/notifications/src/Data/NotificationSubject.php",
    "packages/notifications/src/Data/NotificationPreference.php",
    "packages/notifications/src/Data/PreferenceInput.php",
    "packages/notifications/src/Data/ChannelDescriptor.php",
    "packages/notifications/src/Data/DeliveryTarget.php",
    "packages/notifications/src/Data/DeliveryInput.php",
    "packages/notifications/src/Data/DeliveryOutcome.php",
    "packages/notifications/src/Data/NotificationAction.php"
  ],
  "indexed_files": [
    {
      "item": "P4.4",
      "path": "phases/P4/P4.4.md",
      "sha256": "ecc5eae1f4c85c5b9afdc11c5b1d05f69ffcf05f7bb62ca75c4ac4a1c147baed",
      "files_exact": "packages/notifications/src/Contracts/NotificationChannelDriver.php;  packages/notifications/composer.json; packages/notifications/src/NotificationsModule.php; packages/notifications/src/NotificationsServiceProvider.php; packages/notifications/src/Contracts/NotificationCenter.php; packages/notifications/src/Contracts/NotificationPolicy.php; packages/notifications/src/Data/**; packages/notifications/src/Internal/SqlNotificationCenter.php; packages/notifications/src/Internal/DeliveryStore.php; packages/notifications/src/Internal/DenyNotificationPolicy.php; packages/notifications/src/Internal/InAppChannelDriver.php; packages/notifications/src/Jobs/DeliverNotification.php; packages/notifications/src/Commands/SweepNotificationDeliveries.php; packages/notifications/config/corex-notifications.php; packages/notifications/database/migrations/2026_09_15_000000_create_ntf_notifications_table.php; packages/notifications/database/migrations/2026_09_15_000001_create_ntf_deliveries_table.php; packages/notifications/database/migrations/2026_09_15_000002_create_ntf_recipient_state_table.php; packages/notifications/tests/**; packages/notifications/phpunit.xml; packages/notifications/phpstan.neon; packages/notifications/pint.json; packages/notifications/CHANGELOG.md; packages/notifications/README.md; composer.json; composer.lock; .github/workflows/ci.yml; findings/P4-notification-outbox.md. Bookkeeping only own journal/state/owned projections/handoff; no foreign/product/live writes."
    },
    {
      "item": "P4.5",
      "path": "phases/P4/P4.5.md",
      "sha256": "7739d0df3557fc882101e1339a356227f1e502959adf9b798ee3a72ac720dba8",
      "files_exact": "packages/notifications/src/Contracts/RecipientResolver.php; packages/notifications/src/Contracts/PreferenceRepository.php; packages/notifications/src/Data/**; packages/notifications/src/Internal/SettingsPreferenceRepository.php; packages/notifications/src/Internal/ChannelDrivers.php; packages/notifications/src/Internal/DeliveryCoordinator.php; packages/notifications/src/Internal/InAppChannelDriver.php; packages/notifications/src/Internal/LaravelMailChannelDriver.php; packages/notifications/src/Internal/NullRecipientResolver.php; packages/notifications/src/Internal/SqlNotificationCenter.php; packages/notifications/src/Internal/DeliveryStore.php; packages/notifications/src/Jobs/DeliverNotification.php; packages/notifications/src/NotificationsServiceProvider.php; packages/notifications/config/corex-notifications.php; packages/notifications/tests/Channel/**; packages/notifications/tests/Preference/**; packages/notifications/tests/Pg/DeliveryAdmissionTest.php; packages/notifications/composer.json; packages/notifications/CHANGELOG.md; packages/notifications/README.md; .github/workflows/ci.yml; findings/P4-notification-channels.md. Bookkeeping only own journal/state/owned projections/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "notification/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

NotificationAction enum notify/read/cancel/preference. NotificationContext current trusted scope; RecipientReference is registered kind/opaque ID, not arbitrary address. Self recipient anchors InboxQuery UUIDv7 before cursor/limit1..100, current related visibility at inbox/read/send; disabled/unknown event/template/recipient/channel rejects. Payload json-map bounded64KiB/depth16/fields256, consumer immutable template/schema definition only; no URLs/HTML/client authority selectors. Preference version opaque, channels registered, digest instant/hourly/daily/off; Settings cascade reused with in_app+instant declared fallback. Recipient state lock→notification→delivery→digest; same-DB producer intent transaction, no network before commit. ChannelDescriptor says actual idempotency/reconciliation, absent mail/push skipped:provider-unconfigured, configured failure failed/unknown. DeliveryOutcome accepted/retryable/permanent/unknown; accepted has providerRef optional, retryable retryAfter positive<=300, unknown never auto retry without qualified stable provider lookup. DeliveryKey immutable notification/channel or digest-run/channel digest; admission binds message bytes/SHA/template/policy/driver versions, retry same bytes/key. Cancellation before admission prevents send, after dispatch cannot retract; returned alreadyDispatched discloses only authorized channels. Exceptions InvalidNotificationInput,NotificationAccessDenied,NotificationConflict,NotificationProviderUnavailable; retry5 max known transient, unknown quarantined.

Target `packages/notifications/src/Contracts/NotificationCenter.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Contracts;
interface NotificationCenter {
    public function notify(\CoreX\Notifications\Data\NotificationContext $context, \CoreX\Notifications\Data\NotificationInput $input): \CoreX\Notifications\Data\NotificationRef;
    public function cancel(\CoreX\Notifications\Data\NotificationContext $context, string $notificationId): \CoreX\Notifications\Data\CancelResult;
    public function markRead(\CoreX\Notifications\Data\NotificationContext $context, string $notificationId): void;
    /** @return list<NotificationRef> */
    public function inbox(\CoreX\Notifications\Data\NotificationContext $context, \CoreX\Notifications\Data\InboxQuery $query): array;
    public function unreadCount(\CoreX\Notifications\Data\NotificationContext $context): int;
}
```

Target `packages/notifications/src/Contracts/NotificationPolicy.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Contracts;
interface NotificationPolicy {
    public function allows(\CoreX\Notifications\Data\NotificationContext $context, \CoreX\Notifications\Data\NotificationAction $action, \CoreX\Notifications\Data\NotificationSubject $subject): bool;
}
```

Target `packages/notifications/src/Contracts/NotificationChannelDriver.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Contracts;
interface NotificationChannelDriver {
    public function descriptor(): \CoreX\Notifications\Data\ChannelDescriptor;
    public function send(\CoreX\Notifications\Data\DeliveryInput $input): \CoreX\Notifications\Data\DeliveryOutcome;
    public function reconcile(\CoreX\Notifications\Data\DeliveryInput $input): \CoreX\Notifications\Data\DeliveryOutcome;
}
```

Target `packages/notifications/src/Contracts/RecipientResolver.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Contracts;
interface RecipientResolver {
    public function resolve(\CoreX\Notifications\Data\NotificationContext $context, \CoreX\Notifications\Data\RecipientReference $recipient, string $channel): ?\CoreX\Notifications\Data\DeliveryTarget;
}
```

Target `packages/notifications/src/Contracts/PreferenceRepository.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Contracts;
interface PreferenceRepository {
    public function get(\CoreX\Notifications\Data\NotificationContext $context, string $eventType): \CoreX\Notifications\Data\NotificationPreference;
    public function set(\CoreX\Notifications\Data\NotificationContext $context, \CoreX\Notifications\Data\PreferenceInput $input): \CoreX\Notifications\Data\NotificationPreference;
}
```

Target `packages/notifications/src/Data/NotificationContext.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class NotificationContext {
    public function __construct(
        public \CoreX\Tenancy\TenantContext $tenant,
        public string $actorKind,
        public ?string $actorId,
    ) {}
}
```

Target `packages/notifications/src/Data/RecipientReference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class RecipientReference {
    public function __construct(
        public string $kind,
        public string $id,
    ) {}
}
```

Target `packages/notifications/src/Data/EntityReference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class EntityReference {
    public function __construct(
        public string $handle,
        public string $id,
    ) {}
}
```

Target `packages/notifications/src/Data/NotificationInput.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class NotificationInput {
    /** @param array<string, null|bool|int|string|array> $payload */
    public function __construct(
        public string $requestKey,
        public string $eventType,
        public \CoreX\Notifications\Data\RecipientReference $recipient,
        public string $templateKey,
        public array $payload,
        public ?\CoreX\Notifications\Data\EntityReference $related,
    ) {}
}
```

Target `packages/notifications/src/Data/NotificationRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class NotificationRef {
    public function __construct(
        public string $id,
        public \CoreX\Notifications\Data\RecipientReference $recipient,
        public string $eventType,
        public string $revision,
        public ?\DateTimeImmutable $readAt,
        public ?\DateTimeImmutable $cancelledAt,
        public ?\CoreX\Notifications\Data\EntityReference $related,
    ) {}
}
```

Target `packages/notifications/src/Data/InboxQuery.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class InboxQuery {
    public function __construct(
        public ?string $before,
        public int $limit = 100,
    ) {}
}
```

Target `packages/notifications/src/Data/CancelResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class CancelResult {
    /** @param list<string> $alreadyDispatched */
    public function __construct(
        public bool $cancelled,
        public array $alreadyDispatched,
    ) {}
}
```

Target `packages/notifications/src/Data/NotificationSubject.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class NotificationSubject {
    public function __construct(
        public ?string $notificationId,
        public \CoreX\Notifications\Data\RecipientReference $recipient,
        public ?\CoreX\Notifications\Data\EntityReference $related,
    ) {}
}
```

Target `packages/notifications/src/Data/NotificationPreference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class NotificationPreference {
    /** @param list<string> $channels */
    public function __construct(
        public string $version,
        public array $channels,
        public string $digest,
    ) {}
}
```

Target `packages/notifications/src/Data/PreferenceInput.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class PreferenceInput {
    /** @param list<string> $channels */
    public function __construct(
        public string $eventType,
        public array $channels,
        public string $digest,
        public string $expectedVersion,
    ) {}
}
```

Target `packages/notifications/src/Data/ChannelDescriptor.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class ChannelDescriptor {
    public function __construct(
        public string $key,
        public string $version,
        public bool $supportsIdempotency,
        public bool $supportsReconciliation,
        public ?int $dedupWindowSeconds,
        public string $dedupScope,
    ) {}
}
```

Target `packages/notifications/src/Data/DeliveryTarget.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class DeliveryTarget {
    public function __construct(
        public string $channel,
        public string $reference,
    ) {}
}
```

Target `packages/notifications/src/Data/DeliveryInput.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class DeliveryInput {
    /** @param array<string, null|bool|int|string|array> $message */
    public function __construct(
        public string $deliveryKey,
        public int $attemptEpoch,
        public \CoreX\Notifications\Data\DeliveryTarget $target,
        public array $message,
        public \CoreX\Notifications\Data\NotificationContext $context,
        public string $messageSha256,
        public string $templateVersion,
        public string $policyVersion,
        public string $driverVersion,
    ) {}
}
```

Target `packages/notifications/src/Data/DeliveryOutcome.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class DeliveryOutcome {
    public function __construct(
        public string $kind,
        public ?string $providerRef,
        public ?int $retryAfter,
        public string $reason,
    ) {}
}
```

Target `packages/notifications/src/Data/NotificationAction.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
enum NotificationAction: string {
    case Notify = 'notify';
    case Read = 'read';
    case Cancel = 'cancel';
    case Preference = 'preference';
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `1ff41807f4f4577f990b442e5a16e7a32ffc6071343c37fa25a7ab151fba3067`:

```sql
CREATE TABLE ntf_notifications (
 id UUID PRIMARY KEY NOT NULL,
 recipient_kind VARCHAR(64) NOT NULL CHECK (length(recipient_kind) <= 64),
 recipient_id VARCHAR(128) NOT NULL CHECK (length(recipient_id) <= 128),
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (request_digest ~ '^[a-f0-9]{64}$'),
 event_type VARCHAR(128) NOT NULL CHECK (length(event_type) <= 128),
 template_key VARCHAR(128) NOT NULL CHECK (length(template_key) <= 128),
 payload JSONB NOT NULL,
 related JSONB,
 read_at TIMESTAMPTZ,
 dismissed_at TIMESTAMPTZ,
 cancelled_at TIMESTAMPTZ,
 revision NUMERIC(20,0) NOT NULL CHECK (revision BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 UNIQUE(recipient_kind,recipient_id,request_key)
);
CREATE INDEX ntf_notifications_scan_idx ON ntf_notifications (recipient_kind,recipient_id,read_at,id);
CREATE TABLE ntf_recipient_state (
 kind VARCHAR(64) NOT NULL CHECK (length(kind) <= 64),
 id VARCHAR(128) NOT NULL CHECK (length(id) <= 128),
 preference_revision NUMERIC(20,0) NOT NULL CHECK (preference_revision BETWEEN 0 AND 18446744073709551615),
 PRIMARY KEY(kind,id)
);
CREATE TABLE ntf_deliveries (
 id UUID PRIMARY KEY NOT NULL,
 notification_id UUID,
 digest_run_id UUID,
 channel VARCHAR(64) NOT NULL CHECK (length(channel) <= 64),
 delivery_key CHAR(64) NOT NULL CHECK (delivery_key ~ '^[a-f0-9]{64}$'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 attempts INTEGER NOT NULL,
 next_attempt TIMESTAMPTZ,
 lease_until TIMESTAMPTZ,
 admitted_revision NUMERIC(20,0) NOT NULL CHECK (admitted_revision BETWEEN 0 AND 18446744073709551615),
 provider_ref VARCHAR(255) CHECK (length(provider_ref) <= 255),
 error JSONB,
 sent_at TIMESTAMPTZ,
 message JSONB,
 message_hash CHAR(64) CHECK (message_hash ~ '^[a-f0-9]{64}$'),
 template_version VARCHAR(128) CHECK (length(template_version) <= 128),
 policy_version VARCHAR(128) CHECK (length(policy_version) <= 128),
 driver_version VARCHAR(128) CHECK (length(driver_version) <= 128),
 UNIQUE(delivery_key),
 FOREIGN KEY(notification_id) REFERENCES ntf_notifications(id) ON DELETE RESTRICT,
 CHECK (((notification_id IS NULL) <> (digest_run_id IS NULL))),
 CHECK (state IN ('pending','claimed','sent','failed','skipped','cancelled','unknown')),
 CHECK (attempts >= 0)
);
CREATE INDEX ntf_deliveries_scan_idx ON ntf_deliveries (state,next_attempt);
```

Profile `sqlite.uuid` SQL SHA256 `da6e3e3af6949aeb9953673eae0c3b814f528f70b7e6964bbe585476b1caacda`:

```sql
CREATE TABLE ntf_notifications (
 id TEXT PRIMARY KEY NOT NULL,
 recipient_kind VARCHAR(64) NOT NULL CHECK (length(recipient_kind) <= 64),
 recipient_id VARCHAR(128) NOT NULL CHECK (length(recipient_id) <= 128),
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (length(request_digest) = 64 AND request_digest NOT GLOB '*[^a-f0-9]*'),
 event_type VARCHAR(128) NOT NULL CHECK (length(event_type) <= 128),
 template_key VARCHAR(128) NOT NULL CHECK (length(template_key) <= 128),
 payload TEXT NOT NULL CHECK (json_valid(payload)),
 related TEXT CHECK (json_valid(related)),
 read_at TEXT CHECK (length(read_at) = 27 AND substr(read_at,27,1) = 'Z'),
 dismissed_at TEXT CHECK (length(dismissed_at) = 27 AND substr(dismissed_at,27,1) = 'Z'),
 cancelled_at TEXT CHECK (length(cancelled_at) = 27 AND substr(cancelled_at,27,1) = 'Z'),
 revision CHAR(20) NOT NULL CHECK (length(revision) = 20 AND revision NOT GLOB '*[^0-9]*' AND revision <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 UNIQUE(recipient_kind,recipient_id,request_key)
);
CREATE INDEX ntf_notifications_scan_idx ON ntf_notifications (recipient_kind,recipient_id,read_at,id);
CREATE TABLE ntf_recipient_state (
 kind VARCHAR(64) NOT NULL CHECK (length(kind) <= 64),
 id VARCHAR(128) NOT NULL CHECK (length(id) <= 128),
 preference_revision CHAR(20) NOT NULL CHECK (length(preference_revision) = 20 AND preference_revision NOT GLOB '*[^0-9]*' AND preference_revision <= '18446744073709551615'),
 PRIMARY KEY(kind,id)
);
CREATE TABLE ntf_deliveries (
 id TEXT PRIMARY KEY NOT NULL,
 notification_id TEXT,
 digest_run_id TEXT,
 channel VARCHAR(64) NOT NULL CHECK (length(channel) <= 64),
 delivery_key CHAR(64) NOT NULL CHECK (length(delivery_key) = 64 AND delivery_key NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 attempts INTEGER NOT NULL,
 next_attempt TEXT CHECK (length(next_attempt) = 27 AND substr(next_attempt,27,1) = 'Z'),
 lease_until TEXT CHECK (length(lease_until) = 27 AND substr(lease_until,27,1) = 'Z'),
 admitted_revision CHAR(20) NOT NULL CHECK (length(admitted_revision) = 20 AND admitted_revision NOT GLOB '*[^0-9]*' AND admitted_revision <= '18446744073709551615'),
 provider_ref VARCHAR(255) CHECK (length(provider_ref) <= 255),
 error TEXT CHECK (json_valid(error)),
 sent_at TEXT CHECK (length(sent_at) = 27 AND substr(sent_at,27,1) = 'Z'),
 message TEXT CHECK (json_valid(message)),
 message_hash CHAR(64) CHECK (length(message_hash) = 64 AND message_hash NOT GLOB '*[^a-f0-9]*'),
 template_version VARCHAR(128) CHECK (length(template_version) <= 128),
 policy_version VARCHAR(128) CHECK (length(policy_version) <= 128),
 driver_version VARCHAR(128) CHECK (length(driver_version) <= 128),
 UNIQUE(delivery_key),
 FOREIGN KEY(notification_id) REFERENCES ntf_notifications(id) ON DELETE RESTRICT,
 CHECK (((notification_id IS NULL) <> (digest_run_id IS NULL))),
 CHECK (state IN ('pending','claimed','sent','failed','skipped','cancelled','unknown')),
 CHECK (attempts >= 0)
);
CREATE INDEX ntf_deliveries_scan_idx ON ntf_deliveries (state,next_attempt);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

## C-P2-010

### 2.10 — notification-digest/1

```json
{
  "profile": "notification-digest/1",
  "contract": "C-P2-010",
  "requirement_sources": {
    "R16": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B16.§8.14.a1, B16.§8.14.a2, B16.§8.15.a1, B16.§8.15.a2, B16.§8.20.a4; non-AC reusable/programme binding: brief/sources/b16.md §3.6; source SHA256 ed4096e7e7ac522a9a6d89d3c0870b0964ddd91e1ff5c29f3413072642afeaaf; section SHA256 c46bc79156f8e8f70fcbdf659471fcecc69d4ecd8c7e9d678e216d5540d4a2de",
      "destination": "P2 / P4"
    },
    "R10": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B12 AC-13.a1, B12 AC-13.a2, B12 AC-14.a1, B12 AC-14.a2, B12 AC-15.a1, B12 AC-15.a2, B16.§8.16.a1; non-AC reusable/programme binding: brief/sources/b12.md §3.5; source SHA256 4e74c72a0401b5c99fdd16720b5377d0c4f58b65ca24027cbd440670e88b48fd; section SHA256 89cfaa2f4a8c56ece5f0b377bbdb152acc8b339aa328b569e16b5c4627bed086 · brief/sources/b12.md §3.7; source SHA256 4e74c72a0401b5c99fdd16720b5377d0c4f58b65ca24027cbd440670e88b48fd; section SHA256 f20c43241f5efcc9f142b7626a36d35e6853dc00c82f7140410d9975f17f3106",
      "destination": "P2 contract choice / P5 / P10"
    }
  },
  "owning_design": {
    "path": "research/P5-wakeup-design.md",
    "sha256": "ac703e502544138dd6acebaf3778fe20968186b0aacec14049ed16a4f337f3fc"
  },
  "files": [
    "packages/notifications/src/Contracts/DigestBuilder.php",
    "packages/notifications/src/Data/DigestWindow.php",
    "packages/notifications/src/Data/DigestRef.php"
  ],
  "indexed_files": [
    {
      "item": "P4.6",
      "path": "phases/P4/P4.6.md",
      "sha256": "1950310f4ccd9892fdac85c3b85722c33bb35e10e7990de55bc35450e4eb818e",
      "files_exact": "packages/notifications/src/Internal/Digest/NotificationDigestDueSource.php; packages/notifications/database/migrations/2026_09_15_000005_add_ntf_deliveries_digest_run_foreign_key.php;  packages/notifications/src/Contracts/DigestBuilder.php; packages/notifications/src/Data/**; packages/notifications/src/Internal/SqlDigestBuilder.php; packages/notifications/src/Internal/DigestWakeupHandler.php; packages/notifications/src/Internal/DeliveryCoordinator.php; packages/notifications/src/Internal/DeliveryStore.php; packages/notifications/src/NotificationsModule.php; packages/notifications/src/NotificationsServiceProvider.php; packages/notifications/src/Jobs/DeliverDigest.php; packages/notifications/config/corex-notifications.php; packages/notifications/database/migrations/2026_09_15_000003_create_ntf_digest_runs_table.php; packages/notifications/database/migrations/2026_09_15_000004_create_ntf_digest_items_table.php; packages/notifications/tests/Digest/**; packages/notifications/tests/Pg/DigestConcurrencyTest.php; packages/notifications/composer.json; packages/notifications/CHANGELOG.md; packages/notifications/README.md; .github/workflows/ci.yml; findings/P4-notification-digest.md. Bookkeeping only own journal/state/owned projections/handoff; no foreign/product/live writes."
    },
    {
      "item": "P5.5",
      "path": "phases/P5/P5.5.md",
      "sha256": "453824bff9f6ff4c091ac9b9e8e6f716f24e8402365dc919de00aec316f8ff71",
      "files_exact": "packages/notifications/src/Internal/Digest/DigestWakeupAdapter.php; packages/notifications/src/Internal/Digest/DigestTick.php; packages/notifications/tests/Digest/WakeupBridgeTest.php; packages/notifications/tests/Pg/WakeupBridgeTest.php; packages/notifications/composer.json; packages/notifications/CHANGELOG.md; delivery/wakeup-consumer-qualification-20260915/fixtures/**; delivery/wakeup-consumer-qualification-20260915/evidence/**; findings/P5-digest-bridge.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "notification-digest/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

DigestWindow half-open UTC start<end, period hourly/daily only; DigestRef null means no eligible items. Freeze eligible pending notification/channel membership under recipient state→notification/delivery→digest order, UNIQUE(notification_id,channel) across batches and run identity recipient/period/start/batchSequence. Batch max100 items/64KiB, deterministic ID order and membershipHash; frozen message bytes/versions immutable across retries. Catch-up starts oldest incomplete period, never reset to today. P5.5 owns adapter/Tick/neutral bridge, consumes P5.2 and P4.4/5; P4.6 then owns real DigestWakeupHandler/NotificationDigestDueSource and last000005 FK. No P4.6→P5.5 prerequisite. External delivery unknown/cancel/channel-disable reconciliation retains stable run key; exactly-once external send is not promised. Optional wakeup absent explicit local sweep only, not imaginary timer acceptance.

Target `packages/notifications/src/Contracts/DigestBuilder.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Contracts;
interface DigestBuilder {
    public function build(\CoreX\Notifications\Data\NotificationContext $context, \CoreX\Notifications\Data\DigestWindow $window): ?\CoreX\Notifications\Data\DigestRef;
}
```

Target `packages/notifications/src/Data/DigestWindow.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class DigestWindow {
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public string $period,
    ) {}
}
```

Target `packages/notifications/src/Data/DigestRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Notifications\Data;
final readonly class DigestRef {
    public function __construct(
        public string $id,
        public \CoreX\Notifications\Data\DigestWindow $window,
        public string $membershipHash,
        public int $batchSequence,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `b35962dd5a1d6386d9462ca531f09fef1f163bb07a37a7477a72c9b03a42d1db`:

```sql
CREATE TABLE ntf_digest_runs (
 id UUID PRIMARY KEY NOT NULL,
 recipient_kind VARCHAR(64) NOT NULL CHECK (length(recipient_kind) <= 64),
 recipient_id VARCHAR(128) NOT NULL CHECK (length(recipient_id) <= 128),
 period VARCHAR(16) NOT NULL CHECK (length(period) <= 16),
 period_start TIMESTAMPTZ NOT NULL,
 period_end TIMESTAMPTZ NOT NULL,
 batch_seq INTEGER NOT NULL,
 membership_hash CHAR(64) NOT NULL CHECK (membership_hash ~ '^[a-f0-9]{64}$'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(recipient_kind,recipient_id,period,period_start,batch_seq),
 CHECK (period IN ('hourly','daily')),
 CHECK (period_start < period_end),
 CHECK (batch_seq >= 0),
 CHECK (state IN ('frozen','claimed','sent','unknown','cancelled'))
);
CREATE TABLE ntf_digest_items (
 id UUID PRIMARY KEY NOT NULL,
 run_id UUID NOT NULL,
 notification_id UUID NOT NULL,
 channel VARCHAR(64) NOT NULL CHECK (length(channel) <= 64),
 UNIQUE(notification_id,channel),
 FOREIGN KEY(run_id) REFERENCES ntf_digest_runs(id) ON DELETE RESTRICT,
 FOREIGN KEY(notification_id) REFERENCES ntf_notifications(id) ON DELETE RESTRICT
);
```

Profile `sqlite.uuid` SQL SHA256 `4ec0cef4d02254ad41b5d29f402d2d5e4bfb6b554b276dbc8d5f542abb89f34a`:

```sql
CREATE TABLE ntf_digest_runs (
 id TEXT PRIMARY KEY NOT NULL,
 recipient_kind VARCHAR(64) NOT NULL CHECK (length(recipient_kind) <= 64),
 recipient_id VARCHAR(128) NOT NULL CHECK (length(recipient_id) <= 128),
 period VARCHAR(16) NOT NULL CHECK (length(period) <= 16),
 period_start TEXT NOT NULL CHECK (length(period_start) = 27 AND substr(period_start,27,1) = 'Z'),
 period_end TEXT NOT NULL CHECK (length(period_end) = 27 AND substr(period_end,27,1) = 'Z'),
 batch_seq INTEGER NOT NULL,
 membership_hash CHAR(64) NOT NULL CHECK (length(membership_hash) = 64 AND membership_hash NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 UNIQUE(recipient_kind,recipient_id,period,period_start,batch_seq),
 CHECK (period IN ('hourly','daily')),
 CHECK (period_start < period_end),
 CHECK (batch_seq >= 0),
 CHECK (state IN ('frozen','claimed','sent','unknown','cancelled'))
);
CREATE TABLE ntf_digest_items (
 id TEXT PRIMARY KEY NOT NULL,
 run_id TEXT NOT NULL,
 notification_id TEXT NOT NULL,
 channel VARCHAR(64) NOT NULL CHECK (length(channel) <= 64),
 UNIQUE(notification_id,channel),
 FOREIGN KEY(run_id) REFERENCES ntf_digest_runs(id) ON DELETE RESTRICT,
 FOREIGN KEY(notification_id) REFERENCES ntf_notifications(id) ON DELETE RESTRICT
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

Final P4.6 migration `2026_09_15_000005_add_ntf_deliveries_digest_run_foreign_key.php` after runs/items exist. PG16 exact:

```sql
ALTER TABLE ntf_deliveries ADD CONSTRAINT ntf_deliveries_digest_run_fk FOREIGN KEY (digest_run_id) REFERENCES ntf_digest_runs(id) ON DELETE RESTRICT;
```

SQLite serial equivalent must rebuild deliveries with the identical C-P2-009 columns/checks/indexes and additional FK, inside explicit installed migration transaction; exact rebuilt table:

```sql
CREATE TABLE ntf_deliveries_new (
 id TEXT PRIMARY KEY NOT NULL,
 notification_id TEXT,
 digest_run_id TEXT,
 channel VARCHAR(64) NOT NULL CHECK (length(channel) <= 64),
 delivery_key CHAR(64) NOT NULL CHECK (length(delivery_key) = 64 AND delivery_key NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 attempts INTEGER NOT NULL,
 next_attempt TEXT CHECK (length(next_attempt) = 27 AND substr(next_attempt,27,1) = 'Z'),
 lease_until TEXT CHECK (length(lease_until) = 27 AND substr(lease_until,27,1) = 'Z'),
 admitted_revision CHAR(20) NOT NULL CHECK (length(admitted_revision) = 20 AND admitted_revision NOT GLOB '*[^0-9]*' AND admitted_revision <= '18446744073709551615'),
 provider_ref VARCHAR(255) CHECK (length(provider_ref) <= 255),
 error TEXT CHECK (json_valid(error)),
 sent_at TEXT CHECK (length(sent_at) = 27 AND substr(sent_at,27,1) = 'Z'),
 message TEXT CHECK (json_valid(message)),
 message_hash CHAR(64) CHECK (length(message_hash) = 64 AND message_hash NOT GLOB '*[^a-f0-9]*'),
 template_version VARCHAR(128) CHECK (length(template_version) <= 128),
 policy_version VARCHAR(128) CHECK (length(policy_version) <= 128),
 driver_version VARCHAR(128) CHECK (length(driver_version) <= 128),
 UNIQUE(delivery_key),
 FOREIGN KEY(notification_id) REFERENCES ntf_notifications(id) ON DELETE RESTRICT,
 FOREIGN KEY(digest_run_id) REFERENCES ntf_digest_runs(id) ON DELETE RESTRICT,
 CHECK (((notification_id IS NULL) <> (digest_run_id IS NULL))),
 CHECK (state IN ('pending','claimed','sent','failed','skipped','cancelled','unknown')),
 CHECK (attempts >= 0)
);


```

Copy exact named column list from C-P2-009 to new table, reject orphan digest ids before copy, verify row count/checks/hash, rename old table away and new table to ntf_deliveries, recreate exact scan index, drop old only under approved schema migration. Receipt pins copied columns/DDL/result; no boot rebuild.

Exact full SQLite final000005 migration transaction, foreign_keys=ON on the isolated installed migration connection; failed copy rolls back on any orphan/check/constraint violation. Before rename verify counts and selected fingerprint in the owning migration receipt, never provider boot:

```sql
BEGIN IMMEDIATE;
CREATE TABLE ntf_deliveries_new (
 id TEXT PRIMARY KEY NOT NULL,
 notification_id TEXT,
 digest_run_id TEXT,
 channel VARCHAR(64) NOT NULL CHECK (length(channel) <= 64),
 delivery_key CHAR(64) NOT NULL CHECK (length(delivery_key) = 64 AND delivery_key NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 attempts INTEGER NOT NULL,
 next_attempt TEXT CHECK (length(next_attempt) = 27 AND substr(next_attempt,27,1) = 'Z'),
 lease_until TEXT CHECK (length(lease_until) = 27 AND substr(lease_until,27,1) = 'Z'),
 admitted_revision CHAR(20) NOT NULL CHECK (length(admitted_revision) = 20 AND admitted_revision NOT GLOB '*[^0-9]*' AND admitted_revision <= '18446744073709551615'),
 provider_ref VARCHAR(255) CHECK (length(provider_ref) <= 255),
 error TEXT CHECK (json_valid(error)),
 sent_at TEXT CHECK (length(sent_at) = 27 AND substr(sent_at,27,1) = 'Z'),
 message TEXT CHECK (json_valid(message)),
 message_hash CHAR(64) CHECK (length(message_hash) = 64 AND message_hash NOT GLOB '*[^a-f0-9]*'),
 template_version VARCHAR(128) CHECK (length(template_version) <= 128),
 policy_version VARCHAR(128) CHECK (length(policy_version) <= 128),
 driver_version VARCHAR(128) CHECK (length(driver_version) <= 128),
 UNIQUE(delivery_key),
 FOREIGN KEY(notification_id) REFERENCES ntf_notifications(id) ON DELETE RESTRICT,
 FOREIGN KEY(digest_run_id) REFERENCES ntf_digest_runs(id) ON DELETE RESTRICT,
 CHECK (((notification_id IS NULL) <> (digest_run_id IS NULL))),
 CHECK (state IN ('pending','claimed','sent','failed','skipped','cancelled','unknown')),
 CHECK (attempts >= 0)
);
INSERT INTO ntf_deliveries_new (id,notification_id,digest_run_id,channel,delivery_key,state,claim_epoch,attempts,next_attempt,lease_until,admitted_revision,provider_ref,error,sent_at,message,message_hash,template_version,policy_version,driver_version) SELECT id,notification_id,digest_run_id,channel,delivery_key,state,claim_epoch,attempts,next_attempt,lease_until,admitted_revision,provider_ref,error,sent_at,message,message_hash,template_version,policy_version,driver_version FROM ntf_deliveries;
DROP TABLE ntf_deliveries;
ALTER TABLE ntf_deliveries_new RENAME TO ntf_deliveries;
CREATE INDEX ntf_deliveries_scan_idx ON ntf_deliveries (state,next_attempt);
COMMIT;
```

# 3.4 — Source-complete proposal read group

## C-P2-011

### 2.11 — saved-view/1

```json
{
  "profile": "saved-view/1",
  "contract": "C-P2-011",
  "requirement_sources": {
    "R28": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B31 AC-14.a1; non-AC reusable/programme binding: brief/sources/b31.md §2.2; source SHA256 8f0942012368d234011345852f359bad4eb627c9d611641119633ae97fe39059; section SHA256 3ad0cab6f7da0d18bb988deafb6e9d50d8b8fb62189acc3744b1bc867f207341",
      "destination": "P2 / P4"
    }
  },
  "owning_design": {
    "path": "research/P4-extension-design.md",
    "sha256": "957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a"
  },
  "files": [
    "packages/core/src/Contracts/SavedViewRepository.php",
    "packages/core/src/Contracts/ViewSchemaProvider.php",
    "packages/core/src/Contracts/ViewAccessPolicy.php",
    "packages/core/src/Views/Data/ViewContext.php",
    "packages/core/src/Views/Data/ViewScope.php",
    "packages/core/src/Views/Data/SaveViewInput.php",
    "packages/core/src/Views/Data/SavedViewRef.php",
    "packages/core/src/Views/Data/ViewField.php",
    "packages/core/src/Views/Data/ViewSchema.php",
    "packages/core/src/Views/Data/ViewSubject.php",
    "packages/core/src/Views/Data/SavedQuery.php",
    "packages/core/src/Views/Data/ViewAction.php"
  ],
  "indexed_files": [
    {
      "item": "P4.7",
      "path": "phases/P4/P4.7.md",
      "sha256": "29e6a498acffa03033cc203ab86af457cc77ac55b24924fbb387f12aa372b35b",
      "files_exact": "packages/core/src/Contracts/SavedViewRepository.php; packages/core/src/Contracts/ViewSchemaProvider.php; packages/core/src/Contracts/ViewAccessPolicy.php; packages/core/src/Views/Data/**; packages/core/src/Views/Internal/**; packages/core/src/CoreServiceProvider.php; packages/core/config/corex.php; packages/core/database/migrations/pg/2026_09_15_150000_create_sys_saved_views_table.php; packages/core/database/migrations/pg/2026_09_15_150001_create_sys_saved_view_scopes_table.php; packages/core/tests/Views/**; packages/core/CHANGELOG.md; packages/core/README.md; packages/panels/src/Internal/EntityViewSchemaProvider.php; packages/panels/src/PanelsServiceProvider.php; packages/panels/tests/Views/**; packages/panels/CHANGELOG.md; tests/database/migrations/2026_09_15_150000_create_sys_saved_views_table.php; tests/database/migrations/2026_09_15_150001_create_sys_saved_view_scopes_table.php; findings/P4-saved-views.md. Bookkeeping only own journal/state/owned projections/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "saved-view/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

ViewAction enum read/create/update/delete/setDefault; deny default even for shared scope. ViewScope ownerKind/id both null or both set, workspaceId null supported; physical account implicit. Canonical scope key length-prefixed UTF8 tagged tuple(entity,workspace nullable marker,ownerKind marker,ownerId marker), generated server-side, no UUID sentinel or concatenation ambiguity. Save expectedRevision is absent for new, canonical current immutable revision for update; input id null iff new. saved-view/1 config has exact filters/sorts/columns/groupBy/presentation; tree and/or args or leaf field/op eq/ne/lt/lte/gt/gte/in/is_null/value, server ViewField allowlists. Unknown keys/operators/fields/type/version reject without pruning; limits depth4/nodes50/in100/sorts5/columns50/64KiB/locales20/name255. SavedQuery projection/sort/filters is structural data, no arbitrary SQL/identifier; adapters bind values and recheck current row/field ACL, stale schema UnsupportedViewSchema, stale revision ViewConflict. Nonnullable scope lock then views ID order, first/default/delete replacement atomic; UNIQUE nullable default_scope_key+consistency proves at-most-one, lifecycle proves exactly-one for nonempty scope. Core contains no Modules import; optional panel/schema/data/search adapters conditional only. No existing B31 nullable COALESCE bytes claimed adequate.

Target `packages/core/src/Contracts/SavedViewRepository.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Contracts;
interface SavedViewRepository {
    /** @return list<SavedViewRef> */
    public function viewsFor(\CoreX\Views\Data\ViewContext $context, \CoreX\Views\Data\ViewScope $scope): array;
    public function save(\CoreX\Views\Data\ViewContext $context, \CoreX\Views\Data\SaveViewInput $input): \CoreX\Views\Data\SavedViewRef;
    public function delete(\CoreX\Views\Data\ViewContext $context, string $viewId): void;
    public function setDefault(\CoreX\Views\Data\ViewContext $context, string $viewId): \CoreX\Views\Data\SavedViewRef;
    public function compile(\CoreX\Views\Data\ViewContext $context, string $viewId): \CoreX\Views\Data\SavedQuery;
}
```

Target `packages/core/src/Contracts/ViewSchemaProvider.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Contracts;
interface ViewSchemaProvider {
    public function schema(\CoreX\Views\Data\ViewContext $context, string $entityHandle): \CoreX\Views\Data\ViewSchema;
}
```

Target `packages/core/src/Contracts/ViewAccessPolicy.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Contracts;
interface ViewAccessPolicy {
    public function allows(\CoreX\Views\Data\ViewContext $context, \CoreX\Views\Data\ViewAction $action, \CoreX\Views\Data\ViewSubject $subject): bool;
}
```

Target `packages/core/src/Views/Data/ViewContext.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
final readonly class ViewContext {
    public function __construct(
        public \CoreX\Tenancy\TenantContext $tenant,
        public string $actorKind,
        public ?string $actorId,
    ) {}
}
```

Target `packages/core/src/Views/Data/ViewScope.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
final readonly class ViewScope {
    public function __construct(
        public string $entityHandle,
        public ?string $workspaceId,
        public ?string $ownerKind,
        public ?string $ownerId,
    ) {}
}
```

Target `packages/core/src/Views/Data/SaveViewInput.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
final readonly class SaveViewInput {
    /** @param array<string,string> $localizedName | @param array<string, null|bool|int|string|array> $config */
    public function __construct(
        public ?string $id,
        public string $expectedRevision,
        public \CoreX\Views\Data\ViewScope $scope,
        public string $kind,
        public array $localizedName,
        public array $config,
    ) {}
}
```

Target `packages/core/src/Views/Data/SavedViewRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
final readonly class SavedViewRef {
    public function __construct(
        public string $id,
        public \CoreX\Views\Data\ViewScope $scope,
        public string $kind,
        public string $revision,
        public bool $isDefault,
    ) {}
}
```

Target `packages/core/src/Views/Data/ViewField.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
final readonly class ViewField {
    /** @param list<string> $operators */
    public function __construct(
        public string $key,
        public string $type,
        public array $operators,
        public bool $visible,
        public bool $filterable,
        public bool $sortable,
        public bool $projectable,
        public string $mappingKey,
    ) {}
}
```

Target `packages/core/src/Views/Data/ViewSchema.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
final readonly class ViewSchema {
    /** @param list<ViewField> $fields */
    public function __construct(
        public string $version,
        public string $entityHandle,
        public array $fields,
    ) {}
}
```

Target `packages/core/src/Views/Data/ViewSubject.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
final readonly class ViewSubject {
    public function __construct(
        public \CoreX\Views\Data\ViewScope $scope,
        public ?string $viewId,
    ) {}
}
```

Target `packages/core/src/Views/Data/SavedQuery.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
final readonly class SavedQuery {
    /** @param array<string, null|bool|int|string|array> $filters | @param list<json-map> $sort | @param list<string> $projection */
    public function __construct(
        public string $schemaVersion,
        public array $filters,
        public array $sort,
        public array $projection,
        public ?string $groupBy,
    ) {}
}
```

Target `packages/core/src/Views/Data/ViewAction.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Views\Data;
enum ViewAction: string {
    case Read = 'read';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case SetDefault = 'setDefault';
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `7dc3cdb2fcc2c6ae6e8526d12080116374f6d6683d65d7dc94b67e162b4e6fc9`:

```sql
CREATE TABLE sys_saved_view_scopes (
 entity_code VARCHAR(128) NOT NULL CHECK (length(entity_code) <= 128),
 scope_key VARCHAR(1024) NOT NULL CHECK (length(scope_key) <= 1024),
 PRIMARY KEY(entity_code,scope_key)
);
CREATE TABLE sys_saved_views (
 id UUID PRIMARY KEY NOT NULL,
 entity_code VARCHAR(128) NOT NULL CHECK (length(entity_code) <= 128),
 workspace_id VARCHAR(255) CHECK (length(workspace_id) <= 255),
 owner_kind VARCHAR(64) CHECK (length(owner_kind) <= 64),
 owner_id VARCHAR(255) CHECK (length(owner_id) <= 255),
 scope_key VARCHAR(1024) NOT NULL CHECK (length(scope_key) <= 1024),
 kind VARCHAR(64) NOT NULL CHECK (length(kind) <= 64),
 name JSONB NOT NULL,
 config JSONB NOT NULL,
 schema_version VARCHAR(128) NOT NULL CHECK (length(schema_version) <= 128),
 revision NUMERIC(20,0) NOT NULL CHECK (revision BETWEEN 0 AND 18446744073709551615),
 is_default BOOLEAN NOT NULL CHECK (is_default IN (TRUE,FALSE)),
 default_scope_key VARCHAR(1024) CHECK (length(default_scope_key) <= 1024),
 position INTEGER NOT NULL,
 created_by VARCHAR(128) NOT NULL CHECK (length(created_by) <= 128),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 UNIQUE(entity_code,default_scope_key),
 FOREIGN KEY(entity_code,scope_key) REFERENCES sys_saved_view_scopes(entity_code,scope_key) ON DELETE RESTRICT,
 CHECK (((owner_kind IS NULL) = (owner_id IS NULL))),
 CHECK (((is_default = TRUE AND default_scope_key = scope_key AND default_scope_key IS NOT NULL) OR (is_default = FALSE AND default_scope_key IS NULL)))
);
```

Profile `sqlite.uuid` SQL SHA256 `52d22fbc437f8e8653da70c69ba969e8a0d6b93c3c91a00e8687e2e120f0e03f`:

```sql
CREATE TABLE sys_saved_view_scopes (
 entity_code VARCHAR(128) NOT NULL CHECK (length(entity_code) <= 128),
 scope_key VARCHAR(1024) NOT NULL CHECK (length(scope_key) <= 1024),
 PRIMARY KEY(entity_code,scope_key)
);
CREATE TABLE sys_saved_views (
 id TEXT PRIMARY KEY NOT NULL,
 entity_code VARCHAR(128) NOT NULL CHECK (length(entity_code) <= 128),
 workspace_id VARCHAR(255) CHECK (length(workspace_id) <= 255),
 owner_kind VARCHAR(64) CHECK (length(owner_kind) <= 64),
 owner_id VARCHAR(255) CHECK (length(owner_id) <= 255),
 scope_key VARCHAR(1024) NOT NULL CHECK (length(scope_key) <= 1024),
 kind VARCHAR(64) NOT NULL CHECK (length(kind) <= 64),
 name TEXT NOT NULL CHECK (json_valid(name)),
 config TEXT NOT NULL CHECK (json_valid(config)),
 schema_version VARCHAR(128) NOT NULL CHECK (length(schema_version) <= 128),
 revision CHAR(20) NOT NULL CHECK (length(revision) = 20 AND revision NOT GLOB '*[^0-9]*' AND revision <= '18446744073709551615'),
 is_default BOOLEAN NOT NULL CHECK (is_default IN (TRUE,FALSE)),
 default_scope_key VARCHAR(1024) CHECK (length(default_scope_key) <= 1024),
 position INTEGER NOT NULL,
 created_by VARCHAR(128) NOT NULL CHECK (length(created_by) <= 128),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 UNIQUE(entity_code,default_scope_key),
 FOREIGN KEY(entity_code,scope_key) REFERENCES sys_saved_view_scopes(entity_code,scope_key) ON DELETE RESTRICT,
 CHECK (((owner_kind IS NULL) = (owner_id IS NULL))),
 CHECK (((is_default = TRUE AND default_scope_key = scope_key AND default_scope_key IS NOT NULL) OR (is_default = FALSE AND default_scope_key IS NULL)))
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

Finite P4.7 migration placement: existing allowed150000 file creates scope-lock table FIRST then views with the composite own FK using the exact SQL variant above; allowed150001 file verifies the already-created scope-table fingerprint and performs no second CREATE. No forward FK to a nonexistent scope table, no consumer FK. Both opt-in migration paths remain those indexed by P4.7; this proposal does not write their runtime code.

## C-P2-012

### 2.12 — wakeup/1 + routing-hint/1

```json
{
  "profile": "wakeup/1 + routing-hint/1",
  "contract": "C-P2-012",
  "requirement_sources": {
    "R10": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B12 AC-13.a1, B12 AC-13.a2, B12 AC-14.a1, B12 AC-14.a2, B12 AC-15.a1, B12 AC-15.a2, B16.§8.16.a1; non-AC reusable/programme binding: brief/sources/b12.md §3.5; source SHA256 4e74c72a0401b5c99fdd16720b5377d0c4f58b65ca24027cbd440670e88b48fd; section SHA256 89cfaa2f4a8c56ece5f0b377bbdb152acc8b339aa328b569e16b5c4627bed086 · brief/sources/b12.md §3.7; source SHA256 4e74c72a0401b5c99fdd16720b5377d0c4f58b65ca24027cbd440670e88b48fd; section SHA256 f20c43241f5efcc9f142b7626a36d35e6853dc00c82f7140410d9975f17f3106",
      "destination": "P2 contract choice / P5 / P10"
    }
  },
  "owning_design": {
    "path": "research/P5-wakeup-design.md",
    "sha256": "ac703e502544138dd6acebaf3778fe20968186b0aacec14049ed16a4f337f3fc"
  },
  "files": [
    "packages/wakeup/src/Contracts/WakeupScheduler.php",
    "packages/wakeup/src/Contracts/WakeupHandler.php",
    "packages/wakeup/src/Contracts/DueSource.php",
    "packages/wakeup/src/Contracts/WakeupSweeper.php",
    "packages/wakeup/src/Contracts/WakeupHint.php",
    "packages/wakeup/src/Data/WakeupRequest.php",
    "packages/wakeup/src/Data/WakeupWindow.php",
    "packages/wakeup/src/Data/WakeupDelivery.php",
    "packages/wakeup/src/Data/ScheduleResult.php",
    "packages/wakeup/src/Data/CancelResult.php",
    "packages/wakeup/src/Data/SweepResult.php"
  ],
  "indexed_files": [
    {
      "item": "P5.2",
      "path": "phases/P5/P5.2.md",
      "sha256": "a0b4b45c777376329bf674ba91540d2b8b0700fa4d2d261e5ad63880671fe41e",
      "files_exact": "packages/wakeup/src/Contracts/**; packages/wakeup/src/Data/**; packages/wakeup/src/Internal/**; packages/wakeup/src/WakeupServiceProvider.php; packages/wakeup/config/corex-wakeup.php; packages/wakeup/database/migrations/2026_09_15_000001_create_sys_wakeup_requests_table.php; packages/wakeup/tests/**; packages/wakeup/composer.json; packages/wakeup/phpunit.xml; packages/wakeup/phpstan.neon; packages/wakeup/CHANGELOG.md; packages/wakeup/README.md; composer.json; composer.lock; phpunit.xml; phpstan.neon; .github/workflows/ci.yml; findings/P5-local-wakeup.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    },
    {
      "item": "P5.3",
      "path": "phases/P5/P5.3.md",
      "sha256": "6867806ef177e03c7aae823de414551a009c92c46bcce5352c47e1bdfd403795",
      "files_exact": "packages/tenancy/src/Wakeup/**; packages/tenancy/config/corex-tenancy.php; packages/tenancy/database/migrations/2026_09_15_000003_create_root_wakeup_hints_table.php; packages/tenancy/tests/Wakeup/**; packages/tenancy/tests/Pg/WakeupRoutingTest.php; packages/tenancy/composer.json; packages/tenancy/CHANGELOG.md; packages/tenancy/README.md; .github/workflows/ci.yml; findings/P5-root-routing.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "wakeup/1 + routing-hint/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

wakeup/1 Window half-open UTC, hourly/daily/custom; payload json-map<=64KiB/depth16/no authority selectors; kind registered code and key kind-qualified. SQL key uniqueness workspace/kind/key AND workspace/key closes cancellation ambiguity. Same digest replay returns existing, changed digest ScheduleConflict. ScheduleResult created/existing; CancelResult cancelled/already-terminal/not-found. Trusted account scope account or workspace:<validated current id>. SweepResult counters nonnegative; nextFireAt null iff no known due source; remainingDue explicitly bounded. Lock ordered SKIP LOCKED PG, SQLite serial utility; claim increments generation/attempt,60s lease, DB fresh now AFTER lock wait before handler+commit. WakeupHandler local same-DB transaction, no IO/self-commit; rollback all, known transient retry capped300s max5, terminal failure visible. Restore/stale generation/context/unknown commit reconciles durable result before repeat. Root hint is advisory, native root account K mapping only, no root FK guess; upsert increments revision even equal/later, root claim/reconcile CAS both revision+epoch; root failure does not lose tenant request. Root reconciliation actual eligible catalogue bounded5–15min >100 account receipt P10. Optional root/Redis/engine absent local runner remains; boot never migrates/selects root.

Target `packages/wakeup/src/Contracts/WakeupScheduler.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Contracts;
interface WakeupScheduler {
    public function schedule(\CoreX\Wakeup\Data\WakeupRequest $request): \CoreX\Wakeup\Data\ScheduleResult;
    public function cancel(\CoreX\Tenancy\TenantContext $context, string $requestKey): \CoreX\Wakeup\Data\CancelResult;
}
```

Target `packages/wakeup/src/Contracts/WakeupHandler.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Contracts;
interface WakeupHandler {
    public function handle(\CoreX\Wakeup\Data\WakeupDelivery $delivery): void;
}
```

Target `packages/wakeup/src/Contracts/DueSource.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Contracts;
interface DueSource {
    public function nextDue(\CoreX\Tenancy\TenantContext $context): ?\DateTimeImmutable;
    public function sweep(\CoreX\Tenancy\TenantContext $context, \DateTimeImmutable $now, int $limit): \CoreX\Wakeup\Data\SweepResult;
}
```

Target `packages/wakeup/src/Contracts/WakeupSweeper.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Contracts;
interface WakeupSweeper {
    public function sweep(\CoreX\Tenancy\TenantContext $context, int $limit): \CoreX\Wakeup\Data\SweepResult;
    public function nextDue(\CoreX\Tenancy\TenantContext $context): ?\DateTimeImmutable;
}
```

Target `packages/wakeup/src/Contracts/WakeupHint.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Contracts;
interface WakeupHint {
    public function scheduled(\CoreX\Tenancy\TenantContext $context, \DateTimeImmutable $fireAt): void;
}
```

Target `packages/wakeup/src/Data/WakeupRequest.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Data;
final readonly class WakeupRequest {
    /** @param array<string, null|bool|int|string|array> $payload */
    public function __construct(
        public \CoreX\Tenancy\TenantContext $context,
        public string $kind,
        public string $requestKey,
        public \DateTimeImmutable $fireAt,
        public ?\CoreX\Wakeup\Data\WakeupWindow $window,
        public array $payload,
    ) {}
}
```

Target `packages/wakeup/src/Data/WakeupWindow.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Data;
final readonly class WakeupWindow {
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public string $period,
    ) {}
}
```

Target `packages/wakeup/src/Data/WakeupDelivery.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Data;
final readonly class WakeupDelivery {
    public function __construct(
        public \CoreX\Wakeup\Data\WakeupRequest $request,
        public int $generation,
        public int $attempt,
    ) {}
}
```

Target `packages/wakeup/src/Data/ScheduleResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Data;
final readonly class ScheduleResult {
    public function __construct(
        public string $status,
        public string $requestKey,
    ) {}
}
```

Target `packages/wakeup/src/Data/CancelResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Data;
final readonly class CancelResult {
    public function __construct(
        public string $status,
    ) {}
}
```

Target `packages/wakeup/src/Data/SweepResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Wakeup\Data;
final readonly class SweepResult {
    public function __construct(
        public int $claimed,
        public int $completed,
        public int $retried,
        public int $failed,
        public int $skipped,
        public bool $remainingDue,
        public ?\DateTimeImmutable $nextFireAt,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `52d0c1e781580ce768513c874af119b9c1d51cb178585a94cf19a833e44937f8`:

```sql
CREATE TABLE sys_wakeup_requests (
 id UUID PRIMARY KEY NOT NULL,
 workspace_scope_key VARCHAR(255) NOT NULL CHECK (length(workspace_scope_key) <= 255),
 kind VARCHAR(128) NOT NULL CHECK (length(kind) <= 128),
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (request_digest ~ '^[a-f0-9]{64}$'),
 fire_at TIMESTAMPTZ NOT NULL,
 window_start TIMESTAMPTZ,
 window_end TIMESTAMPTZ,
 period VARCHAR(16) CHECK (length(period) <= 16),
 payload JSONB NOT NULL,
 status VARCHAR(16) NOT NULL CHECK (length(status) <= 16),
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 attempt INTEGER NOT NULL,
 lease_until TIMESTAMPTZ,
 retry_at TIMESTAMPTZ,
 failure_code VARCHAR(64) CHECK (length(failure_code) <= 64),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 completed_at TIMESTAMPTZ,
 UNIQUE(workspace_scope_key,kind,request_key),
 UNIQUE(workspace_scope_key,request_key),
 CHECK (status IN ('pending','running','completed','cancelled','failed')),
 CHECK (((window_start IS NULL AND window_end IS NULL AND period IS NULL) OR (window_start IS NOT NULL AND window_end IS NOT NULL AND window_start < window_end AND period IN ('hourly','daily','custom')))),
 CHECK (((status = 'running') = (lease_until IS NOT NULL))),
 CHECK (attempt >= 0)
);
CREATE INDEX sys_wakeup_requests_scan_idx ON sys_wakeup_requests (status,retry_at,fire_at);
CREATE TABLE root_wakeup_hints (
 account_id UUID NOT NULL,
 next_fire_at TIMESTAMPTZ NOT NULL,
 hint_revision NUMERIC(20,0) NOT NULL CHECK (hint_revision BETWEEN 0 AND 18446744073709551615),
 leased_until TIMESTAMPTZ,
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 PRIMARY KEY(account_id)
);
CREATE INDEX root_wakeup_hints_scan_idx ON root_wakeup_hints (next_fire_at,leased_until);
```

Profile `pg16.ulid` SQL SHA256 `bb72742edb0884d2748ab0ad35172117fa3a0ad410d4c9773118980697e1561e`:

```sql
CREATE TABLE sys_wakeup_requests (
 id UUID PRIMARY KEY NOT NULL,
 workspace_scope_key VARCHAR(255) NOT NULL CHECK (length(workspace_scope_key) <= 255),
 kind VARCHAR(128) NOT NULL CHECK (length(kind) <= 128),
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (request_digest ~ '^[a-f0-9]{64}$'),
 fire_at TIMESTAMPTZ NOT NULL,
 window_start TIMESTAMPTZ,
 window_end TIMESTAMPTZ,
 period VARCHAR(16) CHECK (length(period) <= 16),
 payload JSONB NOT NULL,
 status VARCHAR(16) NOT NULL CHECK (length(status) <= 16),
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 attempt INTEGER NOT NULL,
 lease_until TIMESTAMPTZ,
 retry_at TIMESTAMPTZ,
 failure_code VARCHAR(64) CHECK (length(failure_code) <= 64),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 completed_at TIMESTAMPTZ,
 UNIQUE(workspace_scope_key,kind,request_key),
 UNIQUE(workspace_scope_key,request_key),
 CHECK (status IN ('pending','running','completed','cancelled','failed')),
 CHECK (((window_start IS NULL AND window_end IS NULL AND period IS NULL) OR (window_start IS NOT NULL AND window_end IS NOT NULL AND window_start < window_end AND period IN ('hourly','daily','custom')))),
 CHECK (((status = 'running') = (lease_until IS NOT NULL))),
 CHECK (attempt >= 0)
);
CREATE INDEX sys_wakeup_requests_scan_idx ON sys_wakeup_requests (status,retry_at,fire_at);
CREATE TABLE root_wakeup_hints (
 account_id CHAR(26) NOT NULL,
 next_fire_at TIMESTAMPTZ NOT NULL,
 hint_revision NUMERIC(20,0) NOT NULL CHECK (hint_revision BETWEEN 0 AND 18446744073709551615),
 leased_until TIMESTAMPTZ,
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 PRIMARY KEY(account_id)
);
CREATE INDEX root_wakeup_hints_scan_idx ON root_wakeup_hints (next_fire_at,leased_until);
```

Profile `pg16.int` SQL SHA256 `579159f9224ccb88e4a05148f478ee5f79e70a1ccea343b26600383ee896ceba`:

```sql
CREATE TABLE sys_wakeup_requests (
 id UUID PRIMARY KEY NOT NULL,
 workspace_scope_key VARCHAR(255) NOT NULL CHECK (length(workspace_scope_key) <= 255),
 kind VARCHAR(128) NOT NULL CHECK (length(kind) <= 128),
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (request_digest ~ '^[a-f0-9]{64}$'),
 fire_at TIMESTAMPTZ NOT NULL,
 window_start TIMESTAMPTZ,
 window_end TIMESTAMPTZ,
 period VARCHAR(16) CHECK (length(period) <= 16),
 payload JSONB NOT NULL,
 status VARCHAR(16) NOT NULL CHECK (length(status) <= 16),
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 attempt INTEGER NOT NULL,
 lease_until TIMESTAMPTZ,
 retry_at TIMESTAMPTZ,
 failure_code VARCHAR(64) CHECK (length(failure_code) <= 64),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 completed_at TIMESTAMPTZ,
 UNIQUE(workspace_scope_key,kind,request_key),
 UNIQUE(workspace_scope_key,request_key),
 CHECK (status IN ('pending','running','completed','cancelled','failed')),
 CHECK (((window_start IS NULL AND window_end IS NULL AND period IS NULL) OR (window_start IS NOT NULL AND window_end IS NOT NULL AND window_start < window_end AND period IN ('hourly','daily','custom')))),
 CHECK (((status = 'running') = (lease_until IS NOT NULL))),
 CHECK (attempt >= 0)
);
CREATE INDEX sys_wakeup_requests_scan_idx ON sys_wakeup_requests (status,retry_at,fire_at);
CREATE TABLE root_wakeup_hints (
 account_id BIGINT NOT NULL,
 next_fire_at TIMESTAMPTZ NOT NULL,
 hint_revision NUMERIC(20,0) NOT NULL CHECK (hint_revision BETWEEN 0 AND 18446744073709551615),
 leased_until TIMESTAMPTZ,
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 PRIMARY KEY(account_id)
);
CREATE INDEX root_wakeup_hints_scan_idx ON root_wakeup_hints (next_fire_at,leased_until);
```

Profile `sqlite.uuid` SQL SHA256 `06c12c479e47374acb7d52745db3740feed959ee8ca9c8712c92603ac43f7674`:

```sql
CREATE TABLE sys_wakeup_requests (
 id TEXT PRIMARY KEY NOT NULL,
 workspace_scope_key VARCHAR(255) NOT NULL CHECK (length(workspace_scope_key) <= 255),
 kind VARCHAR(128) NOT NULL CHECK (length(kind) <= 128),
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (length(request_digest) = 64 AND request_digest NOT GLOB '*[^a-f0-9]*'),
 fire_at TEXT NOT NULL CHECK (length(fire_at) = 27 AND substr(fire_at,27,1) = 'Z'),
 window_start TEXT CHECK (length(window_start) = 27 AND substr(window_start,27,1) = 'Z'),
 window_end TEXT CHECK (length(window_end) = 27 AND substr(window_end,27,1) = 'Z'),
 period VARCHAR(16) CHECK (length(period) <= 16),
 payload TEXT NOT NULL CHECK (json_valid(payload)),
 status VARCHAR(16) NOT NULL CHECK (length(status) <= 16),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 attempt INTEGER NOT NULL,
 lease_until TEXT CHECK (length(lease_until) = 27 AND substr(lease_until,27,1) = 'Z'),
 retry_at TEXT CHECK (length(retry_at) = 27 AND substr(retry_at,27,1) = 'Z'),
 failure_code VARCHAR(64) CHECK (length(failure_code) <= 64),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 completed_at TEXT CHECK (length(completed_at) = 27 AND substr(completed_at,27,1) = 'Z'),
 UNIQUE(workspace_scope_key,kind,request_key),
 UNIQUE(workspace_scope_key,request_key),
 CHECK (status IN ('pending','running','completed','cancelled','failed')),
 CHECK (((window_start IS NULL AND window_end IS NULL AND period IS NULL) OR (window_start IS NOT NULL AND window_end IS NOT NULL AND window_start < window_end AND period IN ('hourly','daily','custom')))),
 CHECK (((status = 'running') = (lease_until IS NOT NULL))),
 CHECK (attempt >= 0)
);
CREATE INDEX sys_wakeup_requests_scan_idx ON sys_wakeup_requests (status,retry_at,fire_at);
CREATE TABLE root_wakeup_hints (
 account_id TEXT NOT NULL,
 next_fire_at TEXT NOT NULL CHECK (length(next_fire_at) = 27 AND substr(next_fire_at,27,1) = 'Z'),
 hint_revision CHAR(20) NOT NULL CHECK (length(hint_revision) = 20 AND hint_revision NOT GLOB '*[^0-9]*' AND hint_revision <= '18446744073709551615'),
 leased_until TEXT CHECK (length(leased_until) = 27 AND substr(leased_until,27,1) = 'Z'),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 PRIMARY KEY(account_id)
);
CREATE INDEX root_wakeup_hints_scan_idx ON root_wakeup_hints (next_fire_at,leased_until);
```

Profile `sqlite.ulid` SQL SHA256 `b66d0641d8d4a395d7836842617a860d852cb8bdcff437f009acc96860b6347d`:

```sql
CREATE TABLE sys_wakeup_requests (
 id TEXT PRIMARY KEY NOT NULL,
 workspace_scope_key VARCHAR(255) NOT NULL CHECK (length(workspace_scope_key) <= 255),
 kind VARCHAR(128) NOT NULL CHECK (length(kind) <= 128),
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (length(request_digest) = 64 AND request_digest NOT GLOB '*[^a-f0-9]*'),
 fire_at TEXT NOT NULL CHECK (length(fire_at) = 27 AND substr(fire_at,27,1) = 'Z'),
 window_start TEXT CHECK (length(window_start) = 27 AND substr(window_start,27,1) = 'Z'),
 window_end TEXT CHECK (length(window_end) = 27 AND substr(window_end,27,1) = 'Z'),
 period VARCHAR(16) CHECK (length(period) <= 16),
 payload TEXT NOT NULL CHECK (json_valid(payload)),
 status VARCHAR(16) NOT NULL CHECK (length(status) <= 16),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 attempt INTEGER NOT NULL,
 lease_until TEXT CHECK (length(lease_until) = 27 AND substr(lease_until,27,1) = 'Z'),
 retry_at TEXT CHECK (length(retry_at) = 27 AND substr(retry_at,27,1) = 'Z'),
 failure_code VARCHAR(64) CHECK (length(failure_code) <= 64),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 completed_at TEXT CHECK (length(completed_at) = 27 AND substr(completed_at,27,1) = 'Z'),
 UNIQUE(workspace_scope_key,kind,request_key),
 UNIQUE(workspace_scope_key,request_key),
 CHECK (status IN ('pending','running','completed','cancelled','failed')),
 CHECK (((window_start IS NULL AND window_end IS NULL AND period IS NULL) OR (window_start IS NOT NULL AND window_end IS NOT NULL AND window_start < window_end AND period IN ('hourly','daily','custom')))),
 CHECK (((status = 'running') = (lease_until IS NOT NULL))),
 CHECK (attempt >= 0)
);
CREATE INDEX sys_wakeup_requests_scan_idx ON sys_wakeup_requests (status,retry_at,fire_at);
CREATE TABLE root_wakeup_hints (
 account_id CHAR(26) NOT NULL,
 next_fire_at TEXT NOT NULL CHECK (length(next_fire_at) = 27 AND substr(next_fire_at,27,1) = 'Z'),
 hint_revision CHAR(20) NOT NULL CHECK (length(hint_revision) = 20 AND hint_revision NOT GLOB '*[^0-9]*' AND hint_revision <= '18446744073709551615'),
 leased_until TEXT CHECK (length(leased_until) = 27 AND substr(leased_until,27,1) = 'Z'),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 PRIMARY KEY(account_id)
);
CREATE INDEX root_wakeup_hints_scan_idx ON root_wakeup_hints (next_fire_at,leased_until);
```

Profile `sqlite.int` SQL SHA256 `76708e5d0b9aa6eda7353f7c3c2f6f5bc53109a42af6e718d4e2c3bea297180c`:

```sql
CREATE TABLE sys_wakeup_requests (
 id TEXT PRIMARY KEY NOT NULL,
 workspace_scope_key VARCHAR(255) NOT NULL CHECK (length(workspace_scope_key) <= 255),
 kind VARCHAR(128) NOT NULL CHECK (length(kind) <= 128),
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 request_digest CHAR(64) NOT NULL CHECK (length(request_digest) = 64 AND request_digest NOT GLOB '*[^a-f0-9]*'),
 fire_at TEXT NOT NULL CHECK (length(fire_at) = 27 AND substr(fire_at,27,1) = 'Z'),
 window_start TEXT CHECK (length(window_start) = 27 AND substr(window_start,27,1) = 'Z'),
 window_end TEXT CHECK (length(window_end) = 27 AND substr(window_end,27,1) = 'Z'),
 period VARCHAR(16) CHECK (length(period) <= 16),
 payload TEXT NOT NULL CHECK (json_valid(payload)),
 status VARCHAR(16) NOT NULL CHECK (length(status) <= 16),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 attempt INTEGER NOT NULL,
 lease_until TEXT CHECK (length(lease_until) = 27 AND substr(lease_until,27,1) = 'Z'),
 retry_at TEXT CHECK (length(retry_at) = 27 AND substr(retry_at,27,1) = 'Z'),
 failure_code VARCHAR(64) CHECK (length(failure_code) <= 64),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 completed_at TEXT CHECK (length(completed_at) = 27 AND substr(completed_at,27,1) = 'Z'),
 UNIQUE(workspace_scope_key,kind,request_key),
 UNIQUE(workspace_scope_key,request_key),
 CHECK (status IN ('pending','running','completed','cancelled','failed')),
 CHECK (((window_start IS NULL AND window_end IS NULL AND period IS NULL) OR (window_start IS NOT NULL AND window_end IS NOT NULL AND window_start < window_end AND period IN ('hourly','daily','custom')))),
 CHECK (((status = 'running') = (lease_until IS NOT NULL))),
 CHECK (attempt >= 0)
);
CREATE INDEX sys_wakeup_requests_scan_idx ON sys_wakeup_requests (status,retry_at,fire_at);
CREATE TABLE root_wakeup_hints (
 account_id INTEGER NOT NULL,
 next_fire_at TEXT NOT NULL CHECK (length(next_fire_at) = 27 AND substr(next_fire_at,27,1) = 'Z'),
 hint_revision CHAR(20) NOT NULL CHECK (length(hint_revision) = 20 AND hint_revision NOT GLOB '*[^0-9]*' AND hint_revision <= '18446744073709551615'),
 leased_until TEXT CHECK (length(leased_until) = 27 AND substr(leased_until,27,1) = 'Z'),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 PRIMARY KEY(account_id)
);
CREATE INDEX root_wakeup_hints_scan_idx ON root_wakeup_hints (next_fire_at,leased_until);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

# 3.5 — Source-complete proposal read group

## C-P2-013

### 2.13 — engine-binding/1

```json
{
  "profile": "engine-binding/1",
  "contract": "C-P2-013",
  "requirement_sources": {
    "R09": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B12 AC-1.a1, B12 AC-1.a4, B12 AC-1.a5, B12 AC-2.a1, B12 AC-2.a2, B12 AC-3.a1, B12 AC-3.a2, B12 AC-4.a1, B12 AC-4.a2, B12 AC-5.a1, B12 AC-5.a2, B12 AC-6.a1, B12 AC-6.a2, B12 AC-7.a1, B12 AC-7.a2, B12 AC-8.a1, B12 AC-8.a2, B12 AC-9.a1, B12 AC-10.a1, B12 AC-10.a2, B12 AC-10.a3, B12 AC-10.a4, B12 AC-10.a5, B12 AC-10.a6, B12 AC-10.a7, B12 AC-10.a8, B12 AC-10.a9, B12 AC-10.a10, B12 AC-11.a1, B12 AC-11.a2, B12 AC-12.a1, B12 AC-12.a2, B12 AC-12.a3, B12 AC-16.a1, B12 AC-16.a2, B12 AC-17.a1, B12 AC-17.a2, B12 AC-18.a1, B12 AC-19.a1, B12 AC-19.a2, B12 AC-21.a1, B12 AC-22.a1, B12 AC-23.a1, B12 AC-24.a1, B12 AC-24.a2, B12 AC-25.a1, B12 AC-26.a1, B12 AC-26.a2, B12 AC-26.a3, B12 AC-27.a1, B12 AC-28.a1, B12 AC-28.a2, B12 AC-29.a1, B12 AC-29.a2, B12 AC-30.a1, B12 AC-30.a2; non-AC reusable/programme binding: brief/sources/b12.md §3.7; source SHA256 4e74c72a0401b5c99fdd16720b5377d0c4f58b65ca24027cbd440670e88b48fd; section SHA256 f20c43241f5efcc9f142b7626a36d35e6853dc00c82f7140410d9975f17f3106",
      "destination": "external engine / P5 binding"
    },
    "R10": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B12 AC-13.a1, B12 AC-13.a2, B12 AC-14.a1, B12 AC-14.a2, B12 AC-15.a1, B12 AC-15.a2, B16.§8.16.a1; non-AC reusable/programme binding: brief/sources/b12.md §3.5; source SHA256 4e74c72a0401b5c99fdd16720b5377d0c4f58b65ca24027cbd440670e88b48fd; section SHA256 89cfaa2f4a8c56ece5f0b377bbdb152acc8b339aa328b569e16b5c4627bed086 · brief/sources/b12.md §3.7; source SHA256 4e74c72a0401b5c99fdd16720b5377d0c4f58b65ca24027cbd440670e88b48fd; section SHA256 f20c43241f5efcc9f142b7626a36d35e6853dc00c82f7140410d9975f17f3106",
      "destination": "P2 contract choice / P5 / P10"
    }
  },
  "owning_design": {
    "path": "research/P5-wakeup-design.md",
    "sha256": "ac703e502544138dd6acebaf3778fe20968186b0aacec14049ed16a4f337f3fc"
  },
  "files": [
    "packages/workflow/src/Contracts/WorkflowBinding.php",
    "packages/workflow/src/Contracts/EngineAdapter.php",
    "packages/workflow/src/Data/TriggerRequest.php",
    "packages/workflow/src/Data/SignalRequest.php",
    "packages/workflow/src/Data/TransitionRequest.php",
    "packages/workflow/src/Data/CaptureResult.php",
    "packages/workflow/src/Data/StartResult.php",
    "packages/workflow/src/Data/SignalResult.php",
    "packages/workflow/src/Data/TransitionResult.php"
  ],
  "indexed_files": [
    {
      "item": "P5.1",
      "path": "phases/P5/P5.1.md",
      "sha256": "ae548e36e3a28eb8b802db453262329e985d9f362bdf9169a507003278256e34",
      "files_exact": "delivery/workflow-engine-qualification-20260915/fixtures/**; delivery/workflow-engine-qualification-20260915/evidence/**; delivery/workflow-engine-qualification-20260915/receipt.md; findings/P5-engine-qualification.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    },
    {
      "item": "P5.4",
      "path": "phases/P5/P5.4.md",
      "sha256": "608ee2c875a677583040277a9902d3b67502b475071f6e81b6e6aedded9ef146",
      "files_exact": "packages/workflow/src/Contracts/**; packages/workflow/src/Data/**; packages/workflow/src/Internal/**; packages/workflow/src/WorkflowServiceProvider.php; packages/workflow/config/corex-workflow.php; packages/workflow/database/migrations/2026_09_15_000001_create_sys_workflow_intents_table.php; packages/workflow/tests/**; packages/workflow/composer.json; packages/workflow/phpunit.xml; packages/workflow/phpstan.neon; packages/workflow/CHANGELOG.md; packages/workflow/README.md; composer.json; composer.lock; phpunit.xml; phpstan.neon; .github/workflows/ci.yml; findings/P5-workflow-binding.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "engine-binding/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

engine-binding/1 captures existing Workflow declarations/8 extender metadata, no graph/SEL/FSM/matcher interpreter. Trigger scalarSnapshot bounded64KiB/depth16 exact consumer declared schema; module/event/entity/action/definition and immutable published version are trusted registered references; no client FQN/SQL. capture/signal/fire recheck current scope/module/authority in same domain transaction+sys_workflow_intents. CaptureResult created/existing/conflict; StartResult started/existing/guard-rejected/unknown with nullable instanceRef/errorCode; SignalResult buffered/consumed/expired/existing/unknown; TransitionResult completed/guard-rejected/existing/unknown. Same request key+digest exact replay, changed conflict; unknown lookup same stable engine operation or remain unknown, not new-key retry. Signal expires using DB fresh clock, signal-before-wait only qualified upstream buffering. EngineAdapter outward shape fixed independently of unknown upstream ABI; dependency/source change none here, immutable source/license/dist/lock/hash/upstream suite and actual transaction/callback mapping required P5.1 before P5.4 adapter/DDL adoption. Unsupported engine capability typed UnsupportedWorkflowCapability, no fallback engine. P5.2 remains independent.

Target `packages/workflow/src/Contracts/WorkflowBinding.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Contracts;
interface WorkflowBinding {
    public function capture(\CoreX\Workflow\Data\TriggerRequest $request): \CoreX\Workflow\Data\CaptureResult;
    public function signal(\CoreX\Workflow\Data\SignalRequest $request): \CoreX\Workflow\Data\SignalResult;
    public function fire(\CoreX\Workflow\Data\TransitionRequest $request): \CoreX\Workflow\Data\TransitionResult;
}
```

Target `packages/workflow/src/Contracts/EngineAdapter.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Contracts;
interface EngineAdapter {
    public function start(\CoreX\Workflow\Data\TriggerRequest $request): \CoreX\Workflow\Data\StartResult;
    public function signal(\CoreX\Workflow\Data\SignalRequest $request): \CoreX\Workflow\Data\SignalResult;
    public function fire(\CoreX\Workflow\Data\TransitionRequest $request): \CoreX\Workflow\Data\TransitionResult;
}
```

Target `packages/workflow/src/Data/TriggerRequest.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Data;
final readonly class TriggerRequest {
    /** @param array<string, null|bool|int|string|array> $scalarSnapshot */
    public function __construct(
        public \CoreX\Tenancy\TenantContext $context,
        public string $module,
        public string $entityHandle,
        public string $eventId,
        public string $eventName,
        public string $subjectRef,
        public string $definitionRef,
        public string $versionRef,
        public array $scalarSnapshot,
        public string $requestKey,
    ) {}
}
```

Target `packages/workflow/src/Data/SignalRequest.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Data;
final readonly class SignalRequest {
    /** @param array<string, null|bool|int|string|array> $payload */
    public function __construct(
        public \CoreX\Tenancy\TenantContext $context,
        public string $name,
        public string $correlationKey,
        public array $payload,
        public string $requestKey,
        public \DateTimeImmutable $expiresAt,
    ) {}
}
```

Target `packages/workflow/src/Data/TransitionRequest.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Data;
final readonly class TransitionRequest {
    /** @param array<string, null|bool|int|string|array> $payload */
    public function __construct(
        public \CoreX\Tenancy\TenantContext $context,
        public string $subjectRef,
        public string $edge,
        public string $actorRef,
        public array $payload,
        public string $requestKey,
    ) {}
}
```

Target `packages/workflow/src/Data/CaptureResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Data;
final readonly class CaptureResult {
    public function __construct(
        public string $status,
    ) {}
}
```

Target `packages/workflow/src/Data/StartResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Data;
final readonly class StartResult {
    public function __construct(
        public string $status,
        public ?string $instanceRef,
        public ?string $errorCode,
    ) {}
}
```

Target `packages/workflow/src/Data/SignalResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Data;
final readonly class SignalResult {
    public function __construct(
        public string $status,
    ) {}
}
```

Target `packages/workflow/src/Data/TransitionResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Workflow\Data;
final readonly class TransitionResult {
    public function __construct(
        public string $status,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `21588264884f4a8c0a89c7332ffa187a0f4bce7bb663091e90df58c3a10d4f3d`:

```sql
CREATE TABLE sys_workflow_intents (
 id UUID PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 context_hash CHAR(64) NOT NULL CHECK (context_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 module VARCHAR(128) NOT NULL CHECK (length(module) <= 128),
 kind VARCHAR(16) NOT NULL CHECK (length(kind) <= 16),
 definition_ref VARCHAR(255) CHECK (length(definition_ref) <= 255),
 version_ref VARCHAR(255) CHECK (length(version_ref) <= 255),
 subject_ref VARCHAR(255) NOT NULL CHECK (length(subject_ref) <= 255),
 payload JSONB NOT NULL,
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 provider_instance VARCHAR(255) CHECK (length(provider_instance) <= 255),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 attempt INTEGER NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 UNIQUE(request_key),
 CHECK (kind IN ('trigger','signal','transition')),
 CHECK (state IN ('pending','claimed','started','noop','conflict','unknown','failed')),
 CHECK (attempt >= 0)
);
```

Profile `pg16.ulid` SQL SHA256 `f1c516fe6380b04fe3cc9601ca95e452458d2c46271322b1baa6cb41ab920a97`:

```sql
CREATE TABLE sys_workflow_intents (
 id CHAR(26) PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 context_hash CHAR(64) NOT NULL CHECK (context_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 module VARCHAR(128) NOT NULL CHECK (length(module) <= 128),
 kind VARCHAR(16) NOT NULL CHECK (length(kind) <= 16),
 definition_ref VARCHAR(255) CHECK (length(definition_ref) <= 255),
 version_ref VARCHAR(255) CHECK (length(version_ref) <= 255),
 subject_ref VARCHAR(255) NOT NULL CHECK (length(subject_ref) <= 255),
 payload JSONB NOT NULL,
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 provider_instance VARCHAR(255) CHECK (length(provider_instance) <= 255),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 attempt INTEGER NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 UNIQUE(request_key),
 CHECK (kind IN ('trigger','signal','transition')),
 CHECK (state IN ('pending','claimed','started','noop','conflict','unknown','failed')),
 CHECK (attempt >= 0)
);
```

Profile `pg16.int` SQL SHA256 `85f55be909d6198a76acd777ae14ec5cb8c0a23f82bb9051a109043cbec53c93`:

```sql
CREATE TABLE sys_workflow_intents (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 context_hash CHAR(64) NOT NULL CHECK (context_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 module VARCHAR(128) NOT NULL CHECK (length(module) <= 128),
 kind VARCHAR(16) NOT NULL CHECK (length(kind) <= 16),
 definition_ref VARCHAR(255) CHECK (length(definition_ref) <= 255),
 version_ref VARCHAR(255) CHECK (length(version_ref) <= 255),
 subject_ref VARCHAR(255) NOT NULL CHECK (length(subject_ref) <= 255),
 payload JSONB NOT NULL,
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 provider_instance VARCHAR(255) CHECK (length(provider_instance) <= 255),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 attempt INTEGER NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 UNIQUE(request_key),
 CHECK (kind IN ('trigger','signal','transition')),
 CHECK (state IN ('pending','claimed','started','noop','conflict','unknown','failed')),
 CHECK (attempt >= 0)
);
```

Profile `sqlite.uuid` SQL SHA256 `7316d372d6422334eb9a22ec1505f2bd18b366c227b7f475572b8c96cec73401`:

```sql
CREATE TABLE sys_workflow_intents (
 id TEXT PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 context_hash CHAR(64) NOT NULL CHECK (length(context_hash) = 64 AND context_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 module VARCHAR(128) NOT NULL CHECK (length(module) <= 128),
 kind VARCHAR(16) NOT NULL CHECK (length(kind) <= 16),
 definition_ref VARCHAR(255) CHECK (length(definition_ref) <= 255),
 version_ref VARCHAR(255) CHECK (length(version_ref) <= 255),
 subject_ref VARCHAR(255) NOT NULL CHECK (length(subject_ref) <= 255),
 payload TEXT NOT NULL CHECK (json_valid(payload)),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 provider_instance VARCHAR(255) CHECK (length(provider_instance) <= 255),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 attempt INTEGER NOT NULL,
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 UNIQUE(request_key),
 CHECK (kind IN ('trigger','signal','transition')),
 CHECK (state IN ('pending','claimed','started','noop','conflict','unknown','failed')),
 CHECK (attempt >= 0)
);
```

Profile `sqlite.ulid` SQL SHA256 `361686a0683eb478c40cf24998f370fed8ad19cad74fffb2b0839cccceff8b04`:

```sql
CREATE TABLE sys_workflow_intents (
 id CHAR(26) PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 context_hash CHAR(64) NOT NULL CHECK (length(context_hash) = 64 AND context_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 module VARCHAR(128) NOT NULL CHECK (length(module) <= 128),
 kind VARCHAR(16) NOT NULL CHECK (length(kind) <= 16),
 definition_ref VARCHAR(255) CHECK (length(definition_ref) <= 255),
 version_ref VARCHAR(255) CHECK (length(version_ref) <= 255),
 subject_ref VARCHAR(255) NOT NULL CHECK (length(subject_ref) <= 255),
 payload TEXT NOT NULL CHECK (json_valid(payload)),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 provider_instance VARCHAR(255) CHECK (length(provider_instance) <= 255),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 attempt INTEGER NOT NULL,
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 UNIQUE(request_key),
 CHECK (kind IN ('trigger','signal','transition')),
 CHECK (state IN ('pending','claimed','started','noop','conflict','unknown','failed')),
 CHECK (attempt >= 0)
);
```

Profile `sqlite.int` SQL SHA256 `bfe9b3fde2e88f930c25264d608998172158564e72033f8c00e04ac68cb658b0`:

```sql
CREATE TABLE sys_workflow_intents (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 context_hash CHAR(64) NOT NULL CHECK (length(context_hash) = 64 AND context_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 module VARCHAR(128) NOT NULL CHECK (length(module) <= 128),
 kind VARCHAR(16) NOT NULL CHECK (length(kind) <= 16),
 definition_ref VARCHAR(255) CHECK (length(definition_ref) <= 255),
 version_ref VARCHAR(255) CHECK (length(version_ref) <= 255),
 subject_ref VARCHAR(255) NOT NULL CHECK (length(subject_ref) <= 255),
 payload TEXT NOT NULL CHECK (json_valid(payload)),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 provider_instance VARCHAR(255) CHECK (length(provider_instance) <= 255),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 attempt INTEGER NOT NULL,
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 UNIQUE(request_key),
 CHECK (kind IN ('trigger','signal','transition')),
 CHECK (state IN ('pending','claimed','started','noop','conflict','unknown','failed')),
 CHECK (attempt >= 0)
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

## C-P2-014

### 2.14 — shared-data-foundation/1

```json
{
  "profile": "shared-data-foundation/1",
  "contract": "C-P2-014",
  "requirement_sources": {
    "R11": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-1.a1, B15 AC-2.a1, B15 AC-3.a1, B15 AC-4.a1, B15 AC-5.a1, B15 AC-6.a1, B15 AC-6.a2, B15 AC-7.a1, B15 AC-7.a2, B15 AC-7.a3, B15 AC-8.a1, B15 AC-9.a1, B15 AC-10.a1, B15 AC-11.a1, B15 AC-12.a1, B15 AC-12.a2, B15 AC-13.a1, B15 AC-14.a1, B15 AC-14.a2, B15 AC-14.a3, B15 AC-14.a4, B15 AC-15.a1, B15 AC-15.a2, B15 AC-16.a1, B15 AC-17.a1, B15 AC-18.a1, B15 AC-18.a2, B15 AC-18.a3, B15 AC-19.a1, B15 AC-20.a1, B15 AC-20.a2, B15 AC-20.a3, B15 AC-21.a1, B15 AC-21.a2, B15 AC-21.a3, B15 AC-22.a1, B15 AC-22.a2, B15 AC-23.a1, B15 AC-24.a1, B15 AC-25.a1, B15 AC-25.a2, B15 AC-26.a1, B15 AC-26.a2, B15 AC-27.a1, B15 AC-27.a2, B15 AC-27.a3, B15 AC-29.a1; non-AC reusable/programme binding: brief/sources/b15.md §3.3; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 b19f67058707c57b5018ca4b2dc68c8ac6fcc662cba1ba4d01324d0719e28f31 · brief/sources/b15.md §2.1; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 5ddcaa0b8f1aecd2f85306005b7dce52861f51ecfee55de4bb74a2ae3e5e1ea6",
      "destination": "P2 engine/contract choice / P6"
    },
    "R12": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B18.§8.1.a1, B18.§8.2.a1, B18.§8.2.a2, B18.§8.3.a1, B18.§8.4.a1, B18.§8.4.a2, B18.§8.5.a1, B18.§8.5.a2, B18.§8.6.a2, B18.§8.12.a1, B18.§8.21.a2, B18.§8.21.a3, B18.§8.25.a1; non-AC reusable/programme binding: brief/sources/b18.md §3.1; source SHA256 e5f9feddfd1151c25bb12e5f7600e5b439f507e784aaf714d6be6c4c1f05df34; section SHA256 2d91bea175c91232abce2b950ae520668ba2b65f188ff8288e67a24c191a4aed · brief/sources/b18.md §3.2; source SHA256 e5f9feddfd1151c25bb12e5f7600e5b439f507e784aaf714d6be6c4c1f05df34; section SHA256 4569e3dd44a345e341c95d12c9ba413e56846563b202c972c65792b401a9634a",
      "destination": "P2 / P6"
    },
    "R17": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B13 AC-20.a1, B13 AC-20.a2, B13 AC-20.a3, B13 AC-21.a1, B13 AC-21.a2, B13 AC-22.a1, B13 AC-22.a2, B13 AC-23.a1, B13 AC-23.a2, B13 AC-24.a1; non-AC reusable/programme binding: brief/sources/b13.md §3.4; source SHA256 e81c0f7ea57837efc66e86955563da567491943741bafb5275a883ed916d24ff; section SHA256 563804e9b13c18a65c60dfa947a32a57218c38fdab575289c2a35498aa49c19f · brief/sources/b38.md §2.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 d3d33f2b510d991e99da9580f1dadd975c6e33f6b1585fd233e05cc260ecf1ea",
      "destination": "P2 gap qualification / P7 / P9"
    },
    "R24": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B35 AC-12.a1; non-AC reusable/programme binding: brief/sources/b35.md §3.7; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 267439816cd09d3c8eb3ecba0caff9c270add098bec60cbc091927301ac1bb38 · brief/sources/b35.md §3.8; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 7709fee45586c94d3b6182a7617fdb6a297017429d025642420e5d0f3c450d87",
      "destination": "P2 qualification / P7"
    },
    "R25": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B35 AC-6.a1, B35 AC-6.a2, B35 AC-8.a1; non-AC reusable/programme binding: brief/sources/b35.md §3.5; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 a6e07cf9e9cc13a8b4c52706949b2c373120f05ea8f1525ac6b39f2852eca1bf · brief/sources/b35.md §3.6; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 57b39a1f553d76938543b13746d84db9d870b025d39995f0eec3c9b69de50a74",
      "destination": "P2 / P7 / P10 data"
    },
    "R32": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-28.a1, B15 AC-28.a2, B15 AC-28.a3, B15 AC-28.a4, B35 AC-5.a1, B35 AC-5.a2; non-AC reusable/programme binding: brief/sources/b38.md §2.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 d3d33f2b510d991e99da9580f1dadd975c6e33f6b1585fd233e05cc260ecf1ea · brief/sources/b15.md §3.3; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 b19f67058707c57b5018ca4b2dc68c8ac6fcc662cba1ba4d01324d0719e28f31",
      "destination": "P2 / P6"
    },
    "R52": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B10 AC-31.a1, B10 AC-32.a1, B11 AC-28.a2, B11 AC-29.a1, B11 AC-29.a2, B11 AC-30.a1, B11 AC-32.a1, B11 AC-32.a2, B11 AC-32.a3, B11 AC-32.a4, B34 AC-7.a1, B34 AC-7.a2, B34 AC-7.a3, B34 AC-7.a4, B35 AC-1.a2, B35 AC-11.a1; non-AC reusable/programme binding: brief/sources/b38.md §4.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 89cc87c056131d6c2b94705c65d359c654a473a57507a45cd7e16aa3bc56e4f2 · brief/sources/b38.md §7; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 f9cba28861b728a7b4a83d80c9fc1dcac587d84246d4f09093b3ca061af5ae9e",
      "destination": "P1–P11 invariant / P9 / P10 / P11"
    }
  },
  "owning_design": {
    "path": "research/P6-data-design.md",
    "sha256": "149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb"
  },
  "files": [
    "packages/core/src/Support/Data/DataScope.php",
    "packages/core/src/Support/Data/RecordRef.php",
    "packages/core/src/Support/Data/WriteToken.php"
  ],
  "indexed_files": [
    {
      "item": "P6.2",
      "path": "phases/P6/P6.2.md",
      "sha256": "d78560542eac026bbf72b7e03ff8ef10f6d301e5641bd3996ca4ff431c2142d1",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md;  packages/attributes/src/Contracts/FieldCatalogue.php; packages/attributes/src/Contracts/AttributeStore.php; packages/attributes/src/Contracts/AttributeFilter.php; packages/attributes/src/Support/**; packages/attributes/src/Internal/Engine/**; packages/attributes/src/Internal/Registry/**; packages/attributes/src/AttributesServiceProvider.php; packages/attributes/config/corex-attributes.php; packages/attributes/tests/**; packages/attributes/phpunit.xml; packages/attributes/phpstan.neon; packages/attributes/composer.json; packages/attributes/CHANGELOG.md; packages/attributes/README.md; findings/P6-field-binding.md; composer.json; phpunit.xml; tests/Arch/LayerTest.php; .github/workflows/ci.yml. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    },
    {
      "item": "P6.4",
      "path": "phases/P6/P6.4.md",
      "sha256": "b7ae58b4d59182c7fef9ec4ca17167d3b2b102d227971dd05cb3949f13746115",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md;  packages/import-export/src/Contracts/**; packages/import-export/src/Support/**; packages/import-export/src/Internal/Transfer/**; packages/import-export/src/Internal/Codecs/CsvCodec.php; packages/import-export/src/Internal/Codecs/NdjsonCodec.php; packages/import-export/src/ImportExportServiceProvider.php; packages/import-export/config/corex-import-export.php; packages/import-export/database/migrations/2026_09_15_000001_create_corex_transfer_tables.php; packages/import-export/tests/**; packages/import-export/phpunit.xml; packages/import-export/phpstan.neon; packages/import-export/composer.json; packages/import-export/CHANGELOG.md; packages/import-export/README.md; findings/P6-transfer-runner.md; composer.json; phpunit.xml; tests/Arch/LayerTest.php; .github/workflows/ci.yml. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    },
    {
      "item": "P6.6",
      "path": "phases/P6/P6.6.md",
      "sha256": "18bfda23310a989894ed4233f90db2615564bcde05c312321bf7d8e62512e4ff",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md;  packages/search/src/Contracts/**; packages/search/src/Support/**; packages/search/src/Internal/Query/**; packages/search/src/Internal/Postgres/**; packages/search/src/SearchServiceProvider.php; packages/search/config/corex-search.php; packages/search/tests/**; packages/search/phpunit.xml; packages/search/phpstan.neon; packages/search/composer.json; packages/search/CHANGELOG.md; packages/search/README.md; packages/search-meilisearch/src/MeilisearchServiceProvider.php; packages/search-meilisearch/src/Internal/MeilisearchQueryAdapter.php; packages/search-meilisearch/config/corex-search-meilisearch.php; packages/search-meilisearch/tests/**; packages/search-meilisearch/phpunit.xml; packages/search-meilisearch/phpstan.neon; packages/search-meilisearch/composer.json; packages/search-meilisearch/CHANGELOG.md; packages/search-meilisearch/README.md; findings/P6-search-query.md; composer.json; phpunit.xml; tests/Arch/LayerTest.php; .github/workflows/ci.yml. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    },
    {
      "item": "P7.4",
      "path": "phases/P7/P7.4.md",
      "sha256": "e11e137bb11cea2339890c7d94c76506b90b4412b9f67e3b1732d608fc009b13",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md; phpunit.xml; .github/workflows/ci.yml;  packages/tasks-workflow/src/Contracts/TaskRecurrenceCommand.php; packages/tasks-workflow/src/Data/**; packages/tasks-workflow/src/Internal/**; packages/tasks-workflow/src/TasksWorkflowServiceProvider.php; packages/tasks-workflow/config/corex-task-recurrence.php; packages/tasks-workflow/database/migrations/2026_09_15_000072_create_task_series_mapping.php; packages/tasks-workflow/tests/**; packages/tasks-workflow/composer.json; packages/tasks-workflow/phpunit.xml; packages/tasks-workflow/phpstan.neon; packages/tasks-workflow/README.md; packages/tasks-workflow/CHANGELOG.md; findings/P7-task-recurrence.md. Bookkeeping own journal/state/owned views/handoff."
    },
    {
      "item": "P7.6",
      "path": "phases/P7/P7.6.md",
      "sha256": "b5feae3eba5ee51d3f1f9b80812646413cbf6f8d1a80461a61d88c41cc6313a0",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md; phpunit.xml; .github/workflows/ci.yml;  packages/intl/src/Contracts/CurrencyMetadata.php; packages/intl/src/Contracts/RateProvider.php; packages/intl/src/Contracts/RateImporter.php; packages/intl/src/Contracts/CurrencyConverter.php; packages/intl/src/Contracts/MoneyFormatter.php; packages/intl/src/Data/**; packages/intl/src/Internal/Money/**; packages/intl/src/IntlServiceProvider.php; packages/intl/config/corex-intl.php; packages/intl/database/migrations/2026_09_15_000073_create_rate_snapshots.php; packages/intl/tests/Money/**; packages/intl/tests/Pg/RateImportConcurrencyTest.php; packages/intl/composer.json; packages/intl/phpunit.xml; packages/intl/tests/Pest.php; packages/intl/tests/TestCase.php; packages/intl/tests/PgTestCase.php; packages/intl/phpstan.neon; packages/intl/README.md; packages/intl/CHANGELOG.md; findings/P7-money-rates.md. Bookkeeping own journal/state/owned views/handoff."
    },
    {
      "item": "P7.7",
      "path": "phases/P7/P7.7.md",
      "sha256": "e488f4abf19961b08e21d7d8b6b403405f97cc60b71519a48c83508a8de3ee8d",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md; phpunit.xml; .github/workflows/ci.yml;  packages/intl/src/Contracts/GeographyProvider.php; packages/intl/src/Contracts/DatasetImporter.php; packages/intl/src/Data/**; packages/intl/src/Internal/Reference/**; packages/intl/src/IntlServiceProvider.php; packages/intl/config/corex-intl.php; packages/intl/database/migrations/2026_09_15_000074_create_reference_snapshots.php; packages/intl/tests/Reference/**; packages/intl/tests/Pg/ReferencePublicationTest.php; packages/intl/composer.json; packages/intl/phpunit.xml; packages/intl/tests/Pest.php; packages/intl/tests/TestCase.php; packages/intl/tests/PgTestCase.php; packages/intl/phpstan.neon; packages/intl/DATA-LICENSES.md; packages/intl/README.md; packages/intl/CHANGELOG.md; findings/P7-geography.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "shared-data-foundation/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

Exactly DataScope,RecordRef,WriteToken shared CoreX Support/Data, final readonly, no interface/registry/engine dependency. DataScope connectionIdentity server existing physical resolver or boxed constant; actorId audit identity separate from authorize Closure(string entity,string record,list<string> fields,string action):bool current policy default deny. Never deserialize context/callback/connection; authorization evaluated inside every locked write/query/output boundary, restoreEpoch current trusted lowercase SHA64. WriteToken operationKey bounded128 canonical string,payloadHash canonicalSHA64,expectedRecordVersion opaque bounded128 or literal absent only declared new row. Same key+hash replays authorized stored result, mismatch conflicts; token never grants current authority. RecordRef ID opaque current IdColumns strategy. Serial first eligible consumer P6.2/P6.4/P6.6/P7.4/P7.6/P7.7 produces exact three files+shared-data-foundation receipt before later same bytes reuse; no forced P6-engine dependency.

Target `packages/core/src/Support/Data/DataScope.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Support\Data;
final readonly class DataScope {
    public function __construct(
        public \CoreX\Tenancy\TenantContext $context,
        public string $connectionIdentity,
        public string $actorId,
        public \Closure $authorize,
        public string $restoreEpoch,
    ) {}
}
```

Target `packages/core/src/Support/Data/RecordRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Support\Data;
final readonly class RecordRef {
    public function __construct(
        public string $entity,
        public string $id,
    ) {}
}
```

Target `packages/core/src/Support/Data/WriteToken.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Support\Data;
final readonly class WriteToken {
    public function __construct(
        public string $operationKey,
        public string $payloadHash,
        public string $expectedRecordVersion,
    ) {}
}
```

## C-P2-015

### 2.15 — field-binding/1

```json
{
  "profile": "field-binding/1",
  "contract": "C-P2-015",
  "requirement_sources": {
    "R11": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-1.a1, B15 AC-2.a1, B15 AC-3.a1, B15 AC-4.a1, B15 AC-5.a1, B15 AC-6.a1, B15 AC-6.a2, B15 AC-7.a1, B15 AC-7.a2, B15 AC-7.a3, B15 AC-8.a1, B15 AC-9.a1, B15 AC-10.a1, B15 AC-11.a1, B15 AC-12.a1, B15 AC-12.a2, B15 AC-13.a1, B15 AC-14.a1, B15 AC-14.a2, B15 AC-14.a3, B15 AC-14.a4, B15 AC-15.a1, B15 AC-15.a2, B15 AC-16.a1, B15 AC-17.a1, B15 AC-18.a1, B15 AC-18.a2, B15 AC-18.a3, B15 AC-19.a1, B15 AC-20.a1, B15 AC-20.a2, B15 AC-20.a3, B15 AC-21.a1, B15 AC-21.a2, B15 AC-21.a3, B15 AC-22.a1, B15 AC-22.a2, B15 AC-23.a1, B15 AC-24.a1, B15 AC-25.a1, B15 AC-25.a2, B15 AC-26.a1, B15 AC-26.a2, B15 AC-27.a1, B15 AC-27.a2, B15 AC-27.a3, B15 AC-29.a1; non-AC reusable/programme binding: brief/sources/b15.md §3.3; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 b19f67058707c57b5018ca4b2dc68c8ac6fcc662cba1ba4d01324d0719e28f31 · brief/sources/b15.md §2.1; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 5ddcaa0b8f1aecd2f85306005b7dce52861f51ecfee55de4bb74a2ae3e5e1ea6",
      "destination": "P2 engine/contract choice / P6"
    }
  },
  "owning_design": {
    "path": "research/P6-data-design.md",
    "sha256": "149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb"
  },
  "files": [
    "packages/attributes/src/Contracts/FieldCatalogue.php",
    "packages/attributes/src/Contracts/AttributeStore.php",
    "packages/attributes/src/Contracts/AttributeFilter.php",
    "packages/attributes/src/Support/FieldSnapshot.php",
    "packages/attributes/src/Support/FieldSelection.php",
    "packages/attributes/src/Support/RecordSelection.php",
    "packages/attributes/src/Support/ValueBag.php",
    "packages/attributes/src/Support/ValuePatch.php",
    "packages/attributes/src/Support/ValueBatch.php",
    "packages/attributes/src/Support/WriteResult.php",
    "packages/attributes/src/Support/FilterNode.php",
    "packages/attributes/src/Support/FilterPlan.php"
  ],
  "indexed_files": [
    {
      "item": "P6.1",
      "path": "phases/P6/P6.1.md",
      "sha256": "b8e74d592f307825bcf86679cacb756eeb6b0cf612e1df201dca48df59105d2e",
      "files_exact": "delivery/data-engine-qualification-20260915/fixtures/**; delivery/data-engine-qualification-20260915/evidence/**; delivery/data-engine-qualification-20260915/receipt.md; findings/P6-engine-qualification.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    },
    {
      "item": "P6.2",
      "path": "phases/P6/P6.2.md",
      "sha256": "d78560542eac026bbf72b7e03ff8ef10f6d301e5641bd3996ca4ff431c2142d1",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md;  packages/attributes/src/Contracts/FieldCatalogue.php; packages/attributes/src/Contracts/AttributeStore.php; packages/attributes/src/Contracts/AttributeFilter.php; packages/attributes/src/Support/**; packages/attributes/src/Internal/Engine/**; packages/attributes/src/Internal/Registry/**; packages/attributes/src/AttributesServiceProvider.php; packages/attributes/config/corex-attributes.php; packages/attributes/tests/**; packages/attributes/phpunit.xml; packages/attributes/phpstan.neon; packages/attributes/composer.json; packages/attributes/CHANGELOG.md; packages/attributes/README.md; findings/P6-field-binding.md; composer.json; phpunit.xml; tests/Arch/LayerTest.php; .github/workflows/ci.yml. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "field-binding/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

FieldSnapshot activeFields exact 15-type consumer descriptor schema and driverCapabilities, schemaVersion opaque/hashSHA64; values canonical UTF8 JSON tree with explicit missing/null/value, no unknown→text, decimals strings/money minor+currency/current resolver for reference/file/options. FieldSelection<=30/RecordSelection<=50 unique registered keys, read missing absent/null explicit clear if schema nullable/empty distinct. ValueBatch records list ordered exactly RecordSelection, policy denied record yields explicit denied batch failure, no positional silent shift. write same tenant tx values+record version+dedup, current schema/module/field+row policy after lock; hash conflict/versionConflict typed. FilterNode trusted typed tree eq/in/range/isNull/isMissing/containsAny, per-field allowlist, bounded nodes64/depth8/in100; FilterPlan schema-bound structural AST no user SQL. Driver parity actual common cases ddl/eav/jsonb; unique onlyddl/variant axes eav/select unsupportedCapability otherwise. One EAV value-read query proof separate policy metadata queries. Exceptions invalidValue/unknownField/unsupportedCapability/schemaMismatch/scopeDenied/versionConflict/keyConflict. Existing EntityField retained, no fld tables or engine ABI guessed; P6.1 supplies immutable independent engine ABI/license/ref/suite before adapter use.

Target `packages/attributes/src/Contracts/FieldCatalogue.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Contracts;
interface FieldCatalogue {
    public function snapshot(\CoreX\Support\Data\DataScope $scope, string $entity): \CoreX\Attributes\Support\FieldSnapshot;
}
```

Target `packages/attributes/src/Contracts/AttributeStore.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Contracts;
interface AttributeStore {
    public function read(\CoreX\Support\Data\DataScope $scope, \CoreX\Support\Data\RecordRef $record, \CoreX\Attributes\Support\FieldSelection $fields): \CoreX\Attributes\Support\ValueBag;
    public function write(\CoreX\Support\Data\DataScope $scope, \CoreX\Support\Data\RecordRef $record, \CoreX\Attributes\Support\ValuePatch $patch, \CoreX\Support\Data\WriteToken $token): \CoreX\Attributes\Support\WriteResult;
    public function loadMany(\CoreX\Support\Data\DataScope $scope, \CoreX\Attributes\Support\RecordSelection $records, \CoreX\Attributes\Support\FieldSelection $fields): \CoreX\Attributes\Support\ValueBatch;
}
```

Target `packages/attributes/src/Contracts/AttributeFilter.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Contracts;
interface AttributeFilter {
    public function compile(\CoreX\Support\Data\DataScope $scope, \CoreX\Attributes\Support\FieldSnapshot $schema, \CoreX\Attributes\Support\FilterNode $filter): \CoreX\Attributes\Support\FilterPlan;
}
```

Target `packages/attributes/src/Support/FieldSnapshot.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class FieldSnapshot {
    /** @param list<json-map> $activeFields | @param array<string, null|bool|int|string|array> $driverCapabilities */
    public function __construct(
        public string $entity,
        public string $schemaVersion,
        public string $schemaHash,
        public array $activeFields,
        public array $driverCapabilities,
    ) {}
}
```

Target `packages/attributes/src/Support/FieldSelection.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class FieldSelection {
    /** @param list<string> $fields */
    public function __construct(
        public array $fields,
    ) {}
}
```

Target `packages/attributes/src/Support/RecordSelection.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class RecordSelection {
    /** @param list<CoreX\Support\Data\RecordRef> $records */
    public function __construct(
        public array $records,
    ) {}
}
```

Target `packages/attributes/src/Support/ValueBag.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class ValueBag {
    /** @param array<string, null|bool|int|string|array> $values */
    public function __construct(
        public array $values,
    ) {}
}
```

Target `packages/attributes/src/Support/ValuePatch.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class ValuePatch {
    /** @param array<string, null|bool|int|string|array> $values */
    public function __construct(
        public array $values,
    ) {}
}
```

Target `packages/attributes/src/Support/ValueBatch.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class ValueBatch {
    /** @param list<ValueBag> $records */
    public function __construct(
        public array $records,
    ) {}
}
```

Target `packages/attributes/src/Support/WriteResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class WriteResult {
    public function __construct(
        public string $status,
        public string $recordVersion,
        public string $payloadHash,
    ) {}
}
```

Target `packages/attributes/src/Support/FilterNode.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class FilterNode {
    /** @param array<string, null|bool|int|string|array> $tree */
    public function __construct(
        public array $tree,
    ) {}
}
```

Target `packages/attributes/src/Support/FilterPlan.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support;
final readonly class FilterPlan {
    /** @param array<string, null|bool|int|string|array> $tree */
    public function __construct(
        public string $schemaHash,
        public array $tree,
    ) {}
}
```

# 3.6 — Source-complete proposal read group

## C-P2-016

### 2.16 — schema-bridge/1

```json
{
  "profile": "schema-bridge/1",
  "contract": "C-P2-016",
  "requirement_sources": {
    "R11": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-1.a1, B15 AC-2.a1, B15 AC-3.a1, B15 AC-4.a1, B15 AC-5.a1, B15 AC-6.a1, B15 AC-6.a2, B15 AC-7.a1, B15 AC-7.a2, B15 AC-7.a3, B15 AC-8.a1, B15 AC-9.a1, B15 AC-10.a1, B15 AC-11.a1, B15 AC-12.a1, B15 AC-12.a2, B15 AC-13.a1, B15 AC-14.a1, B15 AC-14.a2, B15 AC-14.a3, B15 AC-14.a4, B15 AC-15.a1, B15 AC-15.a2, B15 AC-16.a1, B15 AC-17.a1, B15 AC-18.a1, B15 AC-18.a2, B15 AC-18.a3, B15 AC-19.a1, B15 AC-20.a1, B15 AC-20.a2, B15 AC-20.a3, B15 AC-21.a1, B15 AC-21.a2, B15 AC-21.a3, B15 AC-22.a1, B15 AC-22.a2, B15 AC-23.a1, B15 AC-24.a1, B15 AC-25.a1, B15 AC-25.a2, B15 AC-26.a1, B15 AC-26.a2, B15 AC-27.a1, B15 AC-27.a2, B15 AC-27.a3, B15 AC-29.a1; non-AC reusable/programme binding: brief/sources/b15.md §3.3; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 b19f67058707c57b5018ca4b2dc68c8ac6fcc662cba1ba4d01324d0719e28f31 · brief/sources/b15.md §2.1; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 5ddcaa0b8f1aecd2f85306005b7dce52861f51ecfee55de4bb74a2ae3e5e1ea6",
      "destination": "P2 engine/contract choice / P6"
    }
  },
  "owning_design": {
    "path": "research/P6-data-design.md",
    "sha256": "149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb"
  },
  "files": [
    "packages/attributes/src/Contracts/SchemaOperations.php",
    "packages/attributes/src/Support/Schema/SchemaChange.php",
    "packages/attributes/src/Support/Schema/OperationKey.php",
    "packages/attributes/src/Support/Schema/OperationRef.php",
    "packages/attributes/src/Support/Schema/SchemaReceipt.php",
    "packages/attributes/src/Support/Schema/DriftReport.php"
  ],
  "indexed_files": [
    {
      "item": "P6.3",
      "path": "phases/P6/P6.3.md",
      "sha256": "ae4378e7c5dd81e3fec72de4483b5b433416ec3c1b2a732eac5e1f868a706522",
      "files_exact": "packages/attributes/src/Contracts/SchemaOperations.php; packages/attributes/src/Support/Schema/**; packages/attributes/src/Internal/Engine/SchemaBridge.php; packages/attributes/src/AttributesServiceProvider.php; packages/attributes/tests/Feature/SchemaBridgeTest.php; packages/attributes/tests/Pg/SchemaBridgePgTest.php; packages/attributes/CHANGELOG.md; packages/attributes/README.md; findings/P6-schema-bridge.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "schema-bridge/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

SchemaChange kind code-allowlisted add/change/retire/drop; expectedSchemaHash+approvalDigest current accepted exact operation, definition bounded64KiB typed owner schema, no arbitrary identifier/SQL. SchemaOperations request persists/enqueues only, never DDL in HTTP/sync queue; inspect status pending/running/verified/failed/unknown; verify drift bounded owned catalogue fingerprints. OperationKey same key/hash idempotent conflict changed, unknown reconciles existing upstream operation/fingerprint not blind ALTER. Engine owns async DDL/EAV/jsonb operation log, PG16 dedicated connection/advisory ownership for CIC nontransactional phase, restore/owner epoch/partial fingerprints/retry<=5/grace prerequisites actualP6.1/3 source receipts. CoreX creates no upstream table/column/driver engine and no new scheduler. Optional absent engine explicit unsupportedCapability; migration/drop only accepted G-SCHEMA exact owner-specific bytes, no standalone generic DDL generated.

Target `packages/attributes/src/Contracts/SchemaOperations.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Contracts;
interface SchemaOperations {
    public function request(\CoreX\Support\Data\DataScope $scope, \CoreX\Attributes\Support\Schema\SchemaChange $change, \CoreX\Attributes\Support\Schema\OperationKey $key): \CoreX\Attributes\Support\Schema\SchemaReceipt;
    public function inspect(\CoreX\Support\Data\DataScope $scope, \CoreX\Attributes\Support\Schema\OperationRef $operation): \CoreX\Attributes\Support\Schema\SchemaReceipt;
    public function verify(\CoreX\Support\Data\DataScope $scope, string $entity): \CoreX\Attributes\Support\Schema\DriftReport;
}
```

Target `packages/attributes/src/Support/Schema/SchemaChange.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support\Schema;
final readonly class SchemaChange {
    /** @param array<string, null|bool|int|string|array> $definition */
    public function __construct(
        public string $entity,
        public string $kind,
        public string $expectedSchemaHash,
        public array $definition,
        public string $approvalDigest,
    ) {}
}
```

Target `packages/attributes/src/Support/Schema/OperationKey.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support\Schema;
final readonly class OperationKey {
    public function __construct(
        public string $key,
        public string $payloadHash,
    ) {}
}
```

Target `packages/attributes/src/Support/Schema/OperationRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support\Schema;
final readonly class OperationRef {
    public function __construct(
        public string $id,
    ) {}
}
```

Target `packages/attributes/src/Support/Schema/SchemaReceipt.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support\Schema;
final readonly class SchemaReceipt {
    public function __construct(
        public \CoreX\Attributes\Support\Schema\OperationRef $operation,
        public string $status,
        public string $schemaHash,
        public ?string $errorCode,
    ) {}
}
```

Target `packages/attributes/src/Support/Schema/DriftReport.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Attributes\Support\Schema;
final readonly class DriftReport {
    /** @param list<json-map> $drift */
    public function __construct(
        public string $entity,
        public string $schemaHash,
        public array $drift,
        public string $status,
    ) {}
}
```

## C-P2-017

### 2.17 — transfer/1

```json
{
  "profile": "transfer/1",
  "contract": "C-P2-017",
  "requirement_sources": {
    "R12": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B18.§8.1.a1, B18.§8.2.a1, B18.§8.2.a2, B18.§8.3.a1, B18.§8.4.a1, B18.§8.4.a2, B18.§8.5.a1, B18.§8.5.a2, B18.§8.6.a2, B18.§8.12.a1, B18.§8.21.a2, B18.§8.21.a3, B18.§8.25.a1; non-AC reusable/programme binding: brief/sources/b18.md §3.1; source SHA256 e5f9feddfd1151c25bb12e5f7600e5b439f507e784aaf714d6be6c4c1f05df34; section SHA256 2d91bea175c91232abce2b950ae520668ba2b65f188ff8288e67a24c191a4aed · brief/sources/b18.md §3.2; source SHA256 e5f9feddfd1151c25bb12e5f7600e5b439f507e784aaf714d6be6c4c1f05df34; section SHA256 4569e3dd44a345e341c95d12c9ba413e56846563b202c972c65792b401a9634a",
      "destination": "P2 / P6"
    }
  },
  "owning_design": {
    "path": "research/P6-data-design.md",
    "sha256": "149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb"
  },
  "files": [
    "packages/import-export/src/Contracts/TransferSource.php",
    "packages/import-export/src/Contracts/RecordMapper.php",
    "packages/import-export/src/Contracts/ImportTarget.php",
    "packages/import-export/src/Contracts/TransferRunner.php",
    "packages/import-export/src/Support/SourceManifest.php",
    "packages/import-export/src/Support/SourceCursor.php",
    "packages/import-export/src/Support/TransferLimits.php",
    "packages/import-export/src/Support/SourceRow.php",
    "packages/import-export/src/Support/RowBatch.php",
    "packages/import-export/src/Support/MappingSnapshot.php",
    "packages/import-export/src/Support/MappingResult.php",
    "packages/import-export/src/Support/CanonicalRecord.php",
    "packages/import-export/src/Support/ConflictRule.php",
    "packages/import-export/src/Support/ImportDecision.php",
    "packages/import-export/src/Support/TargetResult.php",
    "packages/import-export/src/Support/TransferRef.php",
    "packages/import-export/src/Support/StepToken.php",
    "packages/import-export/src/Support/TransferReceipt.php"
  ],
  "indexed_files": [
    {
      "item": "P6.4",
      "path": "phases/P6/P6.4.md",
      "sha256": "b7ae58b4d59182c7fef9ec4ca17167d3b2b102d227971dd05cb3949f13746115",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md;  packages/import-export/src/Contracts/**; packages/import-export/src/Support/**; packages/import-export/src/Internal/Transfer/**; packages/import-export/src/Internal/Codecs/CsvCodec.php; packages/import-export/src/Internal/Codecs/NdjsonCodec.php; packages/import-export/src/ImportExportServiceProvider.php; packages/import-export/config/corex-import-export.php; packages/import-export/database/migrations/2026_09_15_000001_create_corex_transfer_tables.php; packages/import-export/tests/**; packages/import-export/phpunit.xml; packages/import-export/phpstan.neon; packages/import-export/composer.json; packages/import-export/CHANGELOG.md; packages/import-export/README.md; findings/P6-transfer-runner.md; composer.json; phpunit.xml; tests/Arch/LayerTest.php; .github/workflows/ci.yml. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "transfer/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

Default CSV+NDJSON only immutable sourceSHA/length+parser/version/encoding/delimiter/enclosure/escape schemaManifest. CSV record bound before full multiline buffering, sourceCursor canonical decimal ordinal/boundary offset accepted parser boundaries; JSON array/XLSX not selected until qualified bounded source codec. TransferLimits defaults1GiB/1MiB/256fields/depth32/batch500/errors1000/runtime30s/memory128MiB. CanonicalRecord externalSystem/ID/sourceRevision opaque strings, updatedAt null allowed declared conflict rule version/hash/source timestamp; ConflictRule kind source-newer/skip-existing/overwrite-if-version/create-only (unknown denies). Mapping definition trusted registered normalizers, no SEL/FQN/SQL. preview/dryRun zero mutation/network/checkpoint. TargetResult applied/updated/noop/invalid/conflict/unknown/unsupported conditionaltarget/version/providerTask/error required peroutcome; unknown row does not advance contiguous high-watermark, persisted gaps mandatory. Lock job/checkpoint→external refs→target; cross-job unique(entity,externalSystem,externalId), compare all source/mapping/schema/restore/epoch/seq beforetarget+checkpoint+local outbox same tx. SQL transient whole tx3 retries; targetexternalintent stable key request lookup, no GC unknown/live sender. Errors sourceChanged,cursorInvalid,limitExceeded,keyConflict,scopeDenied/schemaMismatch; sourcechanged refuses resume. Storage optionalP3 only if immutable file adapter selected.

Target `packages/import-export/src/Contracts/TransferSource.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Contracts;
interface TransferSource {
    public function describe(\CoreX\Support\Data\DataScope $scope): \CoreX\ImportExport\Support\SourceManifest;
    public function read(\CoreX\ImportExport\Support\SourceManifest $source, \CoreX\ImportExport\Support\SourceCursor $after, \CoreX\ImportExport\Support\TransferLimits $limits): \CoreX\ImportExport\Support\RowBatch;
}
```

Target `packages/import-export/src/Contracts/RecordMapper.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Contracts;
interface RecordMapper {
    public function map(\CoreX\ImportExport\Support\SourceRow $row, \CoreX\ImportExport\Support\MappingSnapshot $mapping): \CoreX\ImportExport\Support\MappingResult;
}
```

Target `packages/import-export/src/Contracts/ImportTarget.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Contracts;
interface ImportTarget {
    public function preview(\CoreX\Support\Data\DataScope $scope, \CoreX\ImportExport\Support\CanonicalRecord $record, \CoreX\ImportExport\Support\ConflictRule $rule): \CoreX\ImportExport\Support\ImportDecision;
    public function apply(\CoreX\Support\Data\DataScope $scope, \CoreX\ImportExport\Support\CanonicalRecord $record, \CoreX\ImportExport\Support\ConflictRule $rule, \CoreX\Support\Data\WriteToken $token): \CoreX\ImportExport\Support\TargetResult;
    public function lookup(\CoreX\Support\Data\DataScope $scope, \CoreX\Support\Data\WriteToken $token): \CoreX\ImportExport\Support\TargetResult;
}
```

Target `packages/import-export/src/Contracts/TransferRunner.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Contracts;
interface TransferRunner {
    public function step(\CoreX\Support\Data\DataScope $scope, \CoreX\ImportExport\Support\TransferRef $transfer, \CoreX\ImportExport\Support\StepToken $token): \CoreX\ImportExport\Support\TransferReceipt;
}
```

Target `packages/import-export/src/Support/SourceManifest.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class SourceManifest {
    public function __construct(
        public string $immutableRef,
        public string $sha256,
        public int $length,
        public string $format,
        public string $parserVersion,
        public string $encoding,
        public string $delimiter,
        public string $enclosure,
        public string $escape,
        public string $schemaHash,
    ) {}
}
```

Target `packages/import-export/src/Support/SourceCursor.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class SourceCursor {
    public function __construct(
        public string $ordinal,
        public ?string $boundaryByteOffset,
        public string $sourceHash,
    ) {}
}
```

Target `packages/import-export/src/Support/TransferLimits.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class TransferLimits {
    public function __construct(
        public int $maxFileBytes = 1073741824,
        public int $maxRecordBytes = 1048576,
        public int $maxFields = 256,
        public int $maxDepth = 32,
        public int $batchRows = 500,
        public int $maxErrors = 1000,
        public int $maxRuntimeSeconds = 30,
        public int $maxMemoryMiB = 128,
    ) {}
}
```

Target `packages/import-export/src/Support/SourceRow.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class SourceRow {
    /** @param array<string, null|bool|int|string|array> $values */
    public function __construct(
        public string $ordinal,
        public array $values,
        public string $sourceHash,
    ) {}
}
```

Target `packages/import-export/src/Support/RowBatch.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class RowBatch {
    /** @param list<SourceRow> $rows */
    public function __construct(
        public array $rows,
        public \CoreX\ImportExport\Support\SourceCursor $next,
        public bool $remaining,
    ) {}
}
```

Target `packages/import-export/src/Support/MappingSnapshot.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class MappingSnapshot {
    /** @param array<string, null|bool|int|string|array> $definition */
    public function __construct(
        public string $version,
        public string $schemaHash,
        public array $definition,
        public string $sha256,
    ) {}
}
```

Target `packages/import-export/src/Support/MappingResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class MappingResult {
    public function __construct(
        public string $status,
        public ?\CoreX\ImportExport\Support\CanonicalRecord $record,
        public ?string $errorCode,
    ) {}
}
```

Target `packages/import-export/src/Support/CanonicalRecord.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class CanonicalRecord {
    /** @param array<string, null|bool|int|string|array> $values */
    public function __construct(
        public string $entity,
        public string $externalSystem,
        public string $externalId,
        public string $sourceRevision,
        public ?\DateTimeImmutable $sourceUpdatedAt,
        public array $values,
        public string $payloadHash,
    ) {}
}
```

Target `packages/import-export/src/Support/ConflictRule.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class ConflictRule {
    public function __construct(
        public string $kind,
    ) {}
}
```

Target `packages/import-export/src/Support/ImportDecision.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class ImportDecision {
    public function __construct(
        public string $status,
        public ?string $reason,
    ) {}
}
```

Target `packages/import-export/src/Support/TargetResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class TargetResult {
    public function __construct(
        public string $status,
        public ?\CoreX\Support\Data\RecordRef $target,
        public ?string $recordVersion,
        public ?string $providerTask,
        public ?string $errorCode,
    ) {}
}
```

Target `packages/import-export/src/Support/TransferRef.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class TransferRef {
    public function __construct(
        public string $id,
    ) {}
}
```

Target `packages/import-export/src/Support/StepToken.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class StepToken {
    public function __construct(
        public string $checkpointSequence,
        public string $epoch,
        public string $sourceHash,
        public string $mappingHash,
        public string $schemaHash,
        public string $restoreEpoch,
    ) {}
}
```

Target `packages/import-export/src/Support/TransferReceipt.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support;
final readonly class TransferReceipt {
    /** @param array<string, null|bool|int|string|array> $stats | @param list<string> $gaps */
    public function __construct(
        public string $state,
        public \CoreX\ImportExport\Support\SourceCursor $cursor,
        public string $checkpointSequence,
        public array $stats,
        public array $gaps,
        public ?string $errorCode,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `23ccb159d91986fe2871ae4550dcc1da6ceb8c26422dd81e80634883421f4028`:

```sql
CREATE TABLE corex_transfer_jobs (
 id UUID PRIMARY KEY NOT NULL,
 source_manifest JSONB NOT NULL,
 source_hash CHAR(64) NOT NULL CHECK (source_hash ~ '^[a-f0-9]{64}$'),
 mapping_snapshot JSONB NOT NULL,
 mapping_hash CHAR(64) NOT NULL CHECK (mapping_hash ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 mode VARCHAR(24) NOT NULL CHECK (length(mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 checkpoint_seq NUMERIC(20,0) NOT NULL CHECK (checkpoint_seq BETWEEN 0 AND 18446744073709551615),
 cursor JSONB NOT NULL,
 stats JSONB NOT NULL,
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 CHECK (state IN ('pending','running','unknown','completed','cancelled','failed')),
 CHECK (mode IN ('import','dryRun','export'))
);
CREATE TABLE corex_transfer_rows (
 id UUID PRIMARY KEY NOT NULL,
 job_id UUID NOT NULL,
 ordinal NUMERIC(20,0) NOT NULL CHECK (ordinal BETWEEN 0 AND 18446744073709551615),
 external_identity_hash CHAR(64) NOT NULL CHECK (external_identity_hash ~ '^[a-f0-9]{64}$'),
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 result VARCHAR(24) NOT NULL CHECK (length(result) <= 24),
 error JSONB,
 request JSONB,
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 epoch NUMERIC(20,0) NOT NULL CHECK (epoch BETWEEN 0 AND 18446744073709551615),
 UNIQUE(job_id,ordinal),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (result IN ('applied','updated','noop','invalid','conflict','unknown','unsupported'))
);
CREATE TABLE corex_transfer_refs (
 id UUID PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 external_system VARCHAR(128) NOT NULL CHECK (length(external_system) <= 128),
 external_id VARCHAR(255) NOT NULL CHECK (length(external_id) <= 255),
 target_id VARCHAR(255) CHECK (length(target_id) <= 255),
 source_revision VARCHAR(255) NOT NULL CHECK (length(source_revision) <= 255),
 source_updated_at TIMESTAMPTZ,
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 record_version NUMERIC(20,0) NOT NULL CHECK (record_version BETWEEN 0 AND 18446744073709551615),
 UNIQUE(entity,external_system,external_id)
);
CREATE TABLE corex_transfer_outbox (
 id UUID PRIMARY KEY NOT NULL,
 job_id UUID NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 request JSONB NOT NULL,
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 dedup_deadline TIMESTAMPTZ,
 UNIQUE(operation_key),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (state IN ('pending','submitted','committed','failed','unknown'))
);
```

Profile `pg16.ulid` SQL SHA256 `43410c5f8d4df4631def25dedc97a335e16eb150518a03da51e2a9511f94239f`:

```sql
CREATE TABLE corex_transfer_jobs (
 id CHAR(26) PRIMARY KEY NOT NULL,
 source_manifest JSONB NOT NULL,
 source_hash CHAR(64) NOT NULL CHECK (source_hash ~ '^[a-f0-9]{64}$'),
 mapping_snapshot JSONB NOT NULL,
 mapping_hash CHAR(64) NOT NULL CHECK (mapping_hash ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 mode VARCHAR(24) NOT NULL CHECK (length(mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 checkpoint_seq NUMERIC(20,0) NOT NULL CHECK (checkpoint_seq BETWEEN 0 AND 18446744073709551615),
 cursor JSONB NOT NULL,
 stats JSONB NOT NULL,
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 CHECK (state IN ('pending','running','unknown','completed','cancelled','failed')),
 CHECK (mode IN ('import','dryRun','export'))
);
CREATE TABLE corex_transfer_rows (
 id CHAR(26) PRIMARY KEY NOT NULL,
 job_id CHAR(26) NOT NULL,
 ordinal NUMERIC(20,0) NOT NULL CHECK (ordinal BETWEEN 0 AND 18446744073709551615),
 external_identity_hash CHAR(64) NOT NULL CHECK (external_identity_hash ~ '^[a-f0-9]{64}$'),
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 result VARCHAR(24) NOT NULL CHECK (length(result) <= 24),
 error JSONB,
 request JSONB,
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 epoch NUMERIC(20,0) NOT NULL CHECK (epoch BETWEEN 0 AND 18446744073709551615),
 UNIQUE(job_id,ordinal),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (result IN ('applied','updated','noop','invalid','conflict','unknown','unsupported'))
);
CREATE TABLE corex_transfer_refs (
 id CHAR(26) PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 external_system VARCHAR(128) NOT NULL CHECK (length(external_system) <= 128),
 external_id VARCHAR(255) NOT NULL CHECK (length(external_id) <= 255),
 target_id VARCHAR(255) CHECK (length(target_id) <= 255),
 source_revision VARCHAR(255) NOT NULL CHECK (length(source_revision) <= 255),
 source_updated_at TIMESTAMPTZ,
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 record_version NUMERIC(20,0) NOT NULL CHECK (record_version BETWEEN 0 AND 18446744073709551615),
 UNIQUE(entity,external_system,external_id)
);
CREATE TABLE corex_transfer_outbox (
 id CHAR(26) PRIMARY KEY NOT NULL,
 job_id CHAR(26) NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 request JSONB NOT NULL,
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 dedup_deadline TIMESTAMPTZ,
 UNIQUE(operation_key),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (state IN ('pending','submitted','committed','failed','unknown'))
);
```

Profile `pg16.int` SQL SHA256 `3cdacfd44b73469271e5dd23feeb9fffcbcf9bc9e74615c43809b89fad0abe66`:

```sql
CREATE TABLE corex_transfer_jobs (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 source_manifest JSONB NOT NULL,
 source_hash CHAR(64) NOT NULL CHECK (source_hash ~ '^[a-f0-9]{64}$'),
 mapping_snapshot JSONB NOT NULL,
 mapping_hash CHAR(64) NOT NULL CHECK (mapping_hash ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 mode VARCHAR(24) NOT NULL CHECK (length(mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 checkpoint_seq NUMERIC(20,0) NOT NULL CHECK (checkpoint_seq BETWEEN 0 AND 18446744073709551615),
 cursor JSONB NOT NULL,
 stats JSONB NOT NULL,
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 created_at TIMESTAMPTZ NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL,
 CHECK (state IN ('pending','running','unknown','completed','cancelled','failed')),
 CHECK (mode IN ('import','dryRun','export'))
);
CREATE TABLE corex_transfer_rows (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 job_id BIGINT NOT NULL,
 ordinal NUMERIC(20,0) NOT NULL CHECK (ordinal BETWEEN 0 AND 18446744073709551615),
 external_identity_hash CHAR(64) NOT NULL CHECK (external_identity_hash ~ '^[a-f0-9]{64}$'),
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 result VARCHAR(24) NOT NULL CHECK (length(result) <= 24),
 error JSONB,
 request JSONB,
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 epoch NUMERIC(20,0) NOT NULL CHECK (epoch BETWEEN 0 AND 18446744073709551615),
 UNIQUE(job_id,ordinal),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (result IN ('applied','updated','noop','invalid','conflict','unknown','unsupported'))
);
CREATE TABLE corex_transfer_refs (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 external_system VARCHAR(128) NOT NULL CHECK (length(external_system) <= 128),
 external_id VARCHAR(255) NOT NULL CHECK (length(external_id) <= 255),
 target_id VARCHAR(255) CHECK (length(target_id) <= 255),
 source_revision VARCHAR(255) NOT NULL CHECK (length(source_revision) <= 255),
 source_updated_at TIMESTAMPTZ,
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 record_version NUMERIC(20,0) NOT NULL CHECK (record_version BETWEEN 0 AND 18446744073709551615),
 UNIQUE(entity,external_system,external_id)
);
CREATE TABLE corex_transfer_outbox (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 job_id BIGINT NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 request JSONB NOT NULL,
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 dedup_deadline TIMESTAMPTZ,
 UNIQUE(operation_key),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (state IN ('pending','submitted','committed','failed','unknown'))
);
```

Profile `sqlite.uuid` SQL SHA256 `da0e2a029d1143b8faea5e0609c130e112fd51f0dd560538f7674cd0effc87bb`:

```sql
CREATE TABLE corex_transfer_jobs (
 id TEXT PRIMARY KEY NOT NULL,
 source_manifest TEXT NOT NULL CHECK (json_valid(source_manifest)),
 source_hash CHAR(64) NOT NULL CHECK (length(source_hash) = 64 AND source_hash NOT GLOB '*[^a-f0-9]*'),
 mapping_snapshot TEXT NOT NULL CHECK (json_valid(mapping_snapshot)),
 mapping_hash CHAR(64) NOT NULL CHECK (length(mapping_hash) = 64 AND mapping_hash NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 mode VARCHAR(24) NOT NULL CHECK (length(mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 checkpoint_seq CHAR(20) NOT NULL CHECK (length(checkpoint_seq) = 20 AND checkpoint_seq NOT GLOB '*[^0-9]*' AND checkpoint_seq <= '18446744073709551615'),
 cursor TEXT NOT NULL CHECK (json_valid(cursor)),
 stats TEXT NOT NULL CHECK (json_valid(stats)),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 CHECK (state IN ('pending','running','unknown','completed','cancelled','failed')),
 CHECK (mode IN ('import','dryRun','export'))
);
CREATE TABLE corex_transfer_rows (
 id TEXT PRIMARY KEY NOT NULL,
 job_id TEXT NOT NULL,
 ordinal CHAR(20) NOT NULL CHECK (length(ordinal) = 20 AND ordinal NOT GLOB '*[^0-9]*' AND ordinal <= '18446744073709551615'),
 external_identity_hash CHAR(64) NOT NULL CHECK (length(external_identity_hash) = 64 AND external_identity_hash NOT GLOB '*[^a-f0-9]*'),
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 result VARCHAR(24) NOT NULL CHECK (length(result) <= 24),
 error TEXT CHECK (json_valid(error)),
 request TEXT CHECK (json_valid(request)),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 epoch CHAR(20) NOT NULL CHECK (length(epoch) = 20 AND epoch NOT GLOB '*[^0-9]*' AND epoch <= '18446744073709551615'),
 UNIQUE(job_id,ordinal),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (result IN ('applied','updated','noop','invalid','conflict','unknown','unsupported'))
);
CREATE TABLE corex_transfer_refs (
 id TEXT PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 external_system VARCHAR(128) NOT NULL CHECK (length(external_system) <= 128),
 external_id VARCHAR(255) NOT NULL CHECK (length(external_id) <= 255),
 target_id VARCHAR(255) CHECK (length(target_id) <= 255),
 source_revision VARCHAR(255) NOT NULL CHECK (length(source_revision) <= 255),
 source_updated_at TEXT CHECK (length(source_updated_at) = 27 AND substr(source_updated_at,27,1) = 'Z'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 record_version CHAR(20) NOT NULL CHECK (length(record_version) = 20 AND record_version NOT GLOB '*[^0-9]*' AND record_version <= '18446744073709551615'),
 UNIQUE(entity,external_system,external_id)
);
CREATE TABLE corex_transfer_outbox (
 id TEXT PRIMARY KEY NOT NULL,
 job_id TEXT NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 request TEXT NOT NULL CHECK (json_valid(request)),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 dedup_deadline TEXT CHECK (length(dedup_deadline) = 27 AND substr(dedup_deadline,27,1) = 'Z'),
 UNIQUE(operation_key),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (state IN ('pending','submitted','committed','failed','unknown'))
);
```

Profile `sqlite.ulid` SQL SHA256 `f6949c700923238c1171d448a00e88bf13b4579c975742c2bebd91abe4432733`:

```sql
CREATE TABLE corex_transfer_jobs (
 id CHAR(26) PRIMARY KEY NOT NULL,
 source_manifest TEXT NOT NULL CHECK (json_valid(source_manifest)),
 source_hash CHAR(64) NOT NULL CHECK (length(source_hash) = 64 AND source_hash NOT GLOB '*[^a-f0-9]*'),
 mapping_snapshot TEXT NOT NULL CHECK (json_valid(mapping_snapshot)),
 mapping_hash CHAR(64) NOT NULL CHECK (length(mapping_hash) = 64 AND mapping_hash NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 mode VARCHAR(24) NOT NULL CHECK (length(mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 checkpoint_seq CHAR(20) NOT NULL CHECK (length(checkpoint_seq) = 20 AND checkpoint_seq NOT GLOB '*[^0-9]*' AND checkpoint_seq <= '18446744073709551615'),
 cursor TEXT NOT NULL CHECK (json_valid(cursor)),
 stats TEXT NOT NULL CHECK (json_valid(stats)),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 CHECK (state IN ('pending','running','unknown','completed','cancelled','failed')),
 CHECK (mode IN ('import','dryRun','export'))
);
CREATE TABLE corex_transfer_rows (
 id CHAR(26) PRIMARY KEY NOT NULL,
 job_id CHAR(26) NOT NULL,
 ordinal CHAR(20) NOT NULL CHECK (length(ordinal) = 20 AND ordinal NOT GLOB '*[^0-9]*' AND ordinal <= '18446744073709551615'),
 external_identity_hash CHAR(64) NOT NULL CHECK (length(external_identity_hash) = 64 AND external_identity_hash NOT GLOB '*[^a-f0-9]*'),
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 result VARCHAR(24) NOT NULL CHECK (length(result) <= 24),
 error TEXT CHECK (json_valid(error)),
 request TEXT CHECK (json_valid(request)),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 epoch CHAR(20) NOT NULL CHECK (length(epoch) = 20 AND epoch NOT GLOB '*[^0-9]*' AND epoch <= '18446744073709551615'),
 UNIQUE(job_id,ordinal),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (result IN ('applied','updated','noop','invalid','conflict','unknown','unsupported'))
);
CREATE TABLE corex_transfer_refs (
 id CHAR(26) PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 external_system VARCHAR(128) NOT NULL CHECK (length(external_system) <= 128),
 external_id VARCHAR(255) NOT NULL CHECK (length(external_id) <= 255),
 target_id VARCHAR(255) CHECK (length(target_id) <= 255),
 source_revision VARCHAR(255) NOT NULL CHECK (length(source_revision) <= 255),
 source_updated_at TEXT CHECK (length(source_updated_at) = 27 AND substr(source_updated_at,27,1) = 'Z'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 record_version CHAR(20) NOT NULL CHECK (length(record_version) = 20 AND record_version NOT GLOB '*[^0-9]*' AND record_version <= '18446744073709551615'),
 UNIQUE(entity,external_system,external_id)
);
CREATE TABLE corex_transfer_outbox (
 id CHAR(26) PRIMARY KEY NOT NULL,
 job_id CHAR(26) NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 request TEXT NOT NULL CHECK (json_valid(request)),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 dedup_deadline TEXT CHECK (length(dedup_deadline) = 27 AND substr(dedup_deadline,27,1) = 'Z'),
 UNIQUE(operation_key),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (state IN ('pending','submitted','committed','failed','unknown'))
);
```

Profile `sqlite.int` SQL SHA256 `d0972aa47c87979a91b8fe506c9b8dbc40dfe4befb44f5bbda9a400cedb49238`:

```sql
CREATE TABLE corex_transfer_jobs (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 source_manifest TEXT NOT NULL CHECK (json_valid(source_manifest)),
 source_hash CHAR(64) NOT NULL CHECK (length(source_hash) = 64 AND source_hash NOT GLOB '*[^a-f0-9]*'),
 mapping_snapshot TEXT NOT NULL CHECK (json_valid(mapping_snapshot)),
 mapping_hash CHAR(64) NOT NULL CHECK (length(mapping_hash) = 64 AND mapping_hash NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 mode VARCHAR(24) NOT NULL CHECK (length(mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 checkpoint_seq CHAR(20) NOT NULL CHECK (length(checkpoint_seq) = 20 AND checkpoint_seq NOT GLOB '*[^0-9]*' AND checkpoint_seq <= '18446744073709551615'),
 cursor TEXT NOT NULL CHECK (json_valid(cursor)),
 stats TEXT NOT NULL CHECK (json_valid(stats)),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 updated_at TEXT NOT NULL CHECK (length(updated_at) = 27 AND substr(updated_at,27,1) = 'Z'),
 CHECK (state IN ('pending','running','unknown','completed','cancelled','failed')),
 CHECK (mode IN ('import','dryRun','export'))
);
CREATE TABLE corex_transfer_rows (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 job_id INTEGER NOT NULL,
 ordinal CHAR(20) NOT NULL CHECK (length(ordinal) = 20 AND ordinal NOT GLOB '*[^0-9]*' AND ordinal <= '18446744073709551615'),
 external_identity_hash CHAR(64) NOT NULL CHECK (length(external_identity_hash) = 64 AND external_identity_hash NOT GLOB '*[^a-f0-9]*'),
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 result VARCHAR(24) NOT NULL CHECK (length(result) <= 24),
 error TEXT CHECK (json_valid(error)),
 request TEXT CHECK (json_valid(request)),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 epoch CHAR(20) NOT NULL CHECK (length(epoch) = 20 AND epoch NOT GLOB '*[^0-9]*' AND epoch <= '18446744073709551615'),
 UNIQUE(job_id,ordinal),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (result IN ('applied','updated','noop','invalid','conflict','unknown','unsupported'))
);
CREATE TABLE corex_transfer_refs (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 external_system VARCHAR(128) NOT NULL CHECK (length(external_system) <= 128),
 external_id VARCHAR(255) NOT NULL CHECK (length(external_id) <= 255),
 target_id VARCHAR(255) CHECK (length(target_id) <= 255),
 source_revision VARCHAR(255) NOT NULL CHECK (length(source_revision) <= 255),
 source_updated_at TEXT CHECK (length(source_updated_at) = 27 AND substr(source_updated_at,27,1) = 'Z'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 record_version CHAR(20) NOT NULL CHECK (length(record_version) = 20 AND record_version NOT GLOB '*[^0-9]*' AND record_version <= '18446744073709551615'),
 UNIQUE(entity,external_system,external_id)
);
CREATE TABLE corex_transfer_outbox (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 job_id INTEGER NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 request TEXT NOT NULL CHECK (json_valid(request)),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 provider_task VARCHAR(255) CHECK (length(provider_task) <= 255),
 dedup_deadline TEXT CHECK (length(dedup_deadline) = 27 AND substr(dedup_deadline,27,1) = 'Z'),
 UNIQUE(operation_key),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (state IN ('pending','submitted','committed','failed','unknown'))
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

# 3.7 — Source-complete proposal read group

## C-P2-018

### 2.18 — export/1

```json
{
  "profile": "export/1",
  "contract": "C-P2-018",
  "requirement_sources": {
    "R12": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B18.§8.1.a1, B18.§8.2.a1, B18.§8.2.a2, B18.§8.3.a1, B18.§8.4.a1, B18.§8.4.a2, B18.§8.5.a1, B18.§8.5.a2, B18.§8.6.a2, B18.§8.12.a1, B18.§8.21.a2, B18.§8.21.a3, B18.§8.25.a1; non-AC reusable/programme binding: brief/sources/b18.md §3.1; source SHA256 e5f9feddfd1151c25bb12e5f7600e5b439f507e784aaf714d6be6c4c1f05df34; section SHA256 2d91bea175c91232abce2b950ae520668ba2b65f188ff8288e67a24c191a4aed · brief/sources/b18.md §3.2; source SHA256 e5f9feddfd1151c25bb12e5f7600e5b439f507e784aaf714d6be6c4c1f05df34; section SHA256 4569e3dd44a345e341c95d12c9ba413e56846563b202c972c65792b401a9634a",
      "destination": "P2 / P6"
    },
    "R33": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-30.a1, B15 AC-30.a2, B15 AC-30.a3, B18.§8.21.a1; non-AC reusable/programme binding: brief/sources/b26.md §4; source SHA256 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d; section SHA256 c6251d1ce66c2a592173a1335bb2d87bedf7211f8af542e51c772246e15d6855 · brief/sources/b26.md §5; source SHA256 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d; section SHA256 dcd928918a4462c603ba4973c05f0d99d1525927f8f6d3bd603248ace31e8f40 · brief/sources/b26.md §6; source SHA256 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d; section SHA256 f087a3a9e00538080df309b8b492c81c306f0c36a970382cd4f8a5ad3c9406e8 · brief/sources/b26.md §7; source SHA256 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d; section SHA256 9c0f37e713e6ac73a53c79471d98198a8b6dbd610190f0ccbe53bb9718d3afa1",
      "destination": "P3 / P9 / P10"
    }
  },
  "owning_design": {
    "path": "research/P6-data-design.md",
    "sha256": "149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb"
  },
  "files": [
    "packages/import-export/src/Contracts/ExportSource.php",
    "packages/import-export/src/Support/Export/ExportManifest.php",
    "packages/import-export/src/Support/Export/ExportCursor.php",
    "packages/import-export/src/Support/Export/ExportPage.php"
  ],
  "indexed_files": [
    {
      "item": "P6.5",
      "path": "phases/P6/P6.5.md",
      "sha256": "97385b8dd7903e9d1b723ecb78de53c06818fc1671aa6134e2d819976e6be3b3",
      "files_exact": "packages/import-export/src/Contracts/ExportSource.php; packages/import-export/src/Support/Export/**; packages/import-export/src/Internal/Export/**; packages/import-export/src/Internal/Codecs/CsvCodec.php; packages/import-export/src/ImportExportServiceProvider.php; packages/import-export/database/migrations/2026_09_15_000002_create_corex_export_manifest_tables.php; packages/import-export/tests/Feature/ExportTest.php; packages/import-export/tests/Pg/ExportPgTest.php; packages/import-export/CHANGELOG.md; packages/import-export/README.md; findings/P6-export.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "export/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

ExportSource emits immutable sealed coherent ExportManifest roster/schema/restore/partsHash captured repeatable-read DB+immutable bounded parts; parts verified hashes/counts/ordinal, response only current output policy. liveBounded explicit noncoherent subset never acceptance of coherent backup. Failed unsealed capture aborted/restarted, cannot resume snapshotlost as same attempt; exact complete roster perincluded entity with exclusion reasons/gaps, searchable flag notexportpermission. ExportCursor binds manifestHash/part/ordinal, partial filefinalization lookup same immutable object+hash beforecheckpoint, no overwrite silently. Revoked current policy/schema/restore/account mismatch denies further output; emitted bytes irreversible consumer access-owned. Local source/schema absent explicit unsupported notempty success, ExportManifest snapshotMode coherent/liveBounded. No unconditional P3.2 exporthook dependency; only afteractual P6.5 hook qualification.

Target `packages/import-export/src/Contracts/ExportSource.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Contracts;
interface ExportSource {
    public function page(\CoreX\Support\Data\DataScope $scope, \CoreX\ImportExport\Support\Export\ExportManifest $manifest, \CoreX\ImportExport\Support\Export\ExportCursor $after, \CoreX\ImportExport\Support\TransferLimits $limits): \CoreX\ImportExport\Support\Export\ExportPage;
}
```

Target `packages/import-export/src/Support/Export/ExportManifest.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support\Export;
final readonly class ExportManifest {
    /** @param array<string, null|bool|int|string|array> $roster */
    public function __construct(
        public string $id,
        public string $snapshotMode,
        public string $schemaHash,
        public string $restoreEpoch,
        public array $roster,
        public string $partsHash,
    ) {}
}
```

Target `packages/import-export/src/Support/Export/ExportCursor.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support\Export;
final readonly class ExportCursor {
    public function __construct(
        public string $partNumber,
        public string $rowOrdinal,
        public string $manifestHash,
    ) {}
}
```

Target `packages/import-export/src/Support/Export/ExportPage.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\ImportExport\Support\Export;
final readonly class ExportPage {
    /** @param list<CanonicalRecord> $rows */
    public function __construct(
        public array $rows,
        public ?\CoreX\ImportExport\Support\Export\ExportCursor $next,
        public bool $complete,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `ab5f72116c4b2225b34057c91d807162e2a104701e9ee9942cf033d821b8f9d9`:

```sql
CREATE TABLE corex_export_manifests (
 id UUID PRIMARY KEY NOT NULL,
 job_id UUID NOT NULL,
 capture_attempt CHAR(64) NOT NULL CHECK (capture_attempt ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 roster JSONB NOT NULL,
 parts_hash CHAR(64) CHECK (parts_hash ~ '^[a-f0-9]{64}$'),
 snapshot_mode VARCHAR(24) NOT NULL CHECK (length(snapshot_mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(capture_attempt),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (snapshot_mode IN ('coherent','liveBounded')),
 CHECK (state IN ('capturing','sealed','emitting','completed','aborted'))
);
CREATE TABLE corex_export_parts (
 id UUID PRIMARY KEY NOT NULL,
 manifest_id UUID NOT NULL,
 part_no NUMERIC(20,0) NOT NULL CHECK (part_no BETWEEN 0 AND 18446744073709551615),
 immutable_ref VARCHAR(1024) NOT NULL CHECK (length(immutable_ref) <= 1024),
 hash CHAR(64) NOT NULL CHECK (hash ~ '^[a-f0-9]{64}$'),
 byte_length NUMERIC(20,0) NOT NULL CHECK (byte_length BETWEEN 0 AND 18446744073709551615),
 row_counts JSONB NOT NULL,
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(manifest_id,part_no),
 FOREIGN KEY(manifest_id) REFERENCES corex_export_manifests(id) ON DELETE RESTRICT,
 CHECK (state IN ('staged','verified','emitted'))
);
```

Profile `pg16.ulid` SQL SHA256 `365396f0a94e00fca98c7e64966645439356a1e1c382b2a5cdd63d7d79a2bc8e`:

```sql
CREATE TABLE corex_export_manifests (
 id CHAR(26) PRIMARY KEY NOT NULL,
 job_id CHAR(26) NOT NULL,
 capture_attempt CHAR(64) NOT NULL CHECK (capture_attempt ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 roster JSONB NOT NULL,
 parts_hash CHAR(64) CHECK (parts_hash ~ '^[a-f0-9]{64}$'),
 snapshot_mode VARCHAR(24) NOT NULL CHECK (length(snapshot_mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(capture_attempt),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (snapshot_mode IN ('coherent','liveBounded')),
 CHECK (state IN ('capturing','sealed','emitting','completed','aborted'))
);
CREATE TABLE corex_export_parts (
 id CHAR(26) PRIMARY KEY NOT NULL,
 manifest_id CHAR(26) NOT NULL,
 part_no NUMERIC(20,0) NOT NULL CHECK (part_no BETWEEN 0 AND 18446744073709551615),
 immutable_ref VARCHAR(1024) NOT NULL CHECK (length(immutable_ref) <= 1024),
 hash CHAR(64) NOT NULL CHECK (hash ~ '^[a-f0-9]{64}$'),
 byte_length NUMERIC(20,0) NOT NULL CHECK (byte_length BETWEEN 0 AND 18446744073709551615),
 row_counts JSONB NOT NULL,
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(manifest_id,part_no),
 FOREIGN KEY(manifest_id) REFERENCES corex_export_manifests(id) ON DELETE RESTRICT,
 CHECK (state IN ('staged','verified','emitted'))
);
```

Profile `pg16.int` SQL SHA256 `eb51a7a4621d2f24f681cb424516e7bfe9537ca7ae80ca92e21df89901f8554b`:

```sql
CREATE TABLE corex_export_manifests (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 job_id BIGINT NOT NULL,
 capture_attempt CHAR(64) NOT NULL CHECK (capture_attempt ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 roster JSONB NOT NULL,
 parts_hash CHAR(64) CHECK (parts_hash ~ '^[a-f0-9]{64}$'),
 snapshot_mode VARCHAR(24) NOT NULL CHECK (length(snapshot_mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(capture_attempt),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (snapshot_mode IN ('coherent','liveBounded')),
 CHECK (state IN ('capturing','sealed','emitting','completed','aborted'))
);
CREATE TABLE corex_export_parts (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 manifest_id BIGINT NOT NULL,
 part_no NUMERIC(20,0) NOT NULL CHECK (part_no BETWEEN 0 AND 18446744073709551615),
 immutable_ref VARCHAR(1024) NOT NULL CHECK (length(immutable_ref) <= 1024),
 hash CHAR(64) NOT NULL CHECK (hash ~ '^[a-f0-9]{64}$'),
 byte_length NUMERIC(20,0) NOT NULL CHECK (byte_length BETWEEN 0 AND 18446744073709551615),
 row_counts JSONB NOT NULL,
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(manifest_id,part_no),
 FOREIGN KEY(manifest_id) REFERENCES corex_export_manifests(id) ON DELETE RESTRICT,
 CHECK (state IN ('staged','verified','emitted'))
);
```

Profile `sqlite.uuid` SQL SHA256 `94aeb74d153565bcefedab5cd37a028023de61953047c9823f515735151877c9`:

```sql
CREATE TABLE corex_export_manifests (
 id TEXT PRIMARY KEY NOT NULL,
 job_id TEXT NOT NULL,
 capture_attempt CHAR(64) NOT NULL CHECK (length(capture_attempt) = 64 AND capture_attempt NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 roster TEXT NOT NULL CHECK (json_valid(roster)),
 parts_hash CHAR(64) CHECK (length(parts_hash) = 64 AND parts_hash NOT GLOB '*[^a-f0-9]*'),
 snapshot_mode VARCHAR(24) NOT NULL CHECK (length(snapshot_mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(capture_attempt),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (snapshot_mode IN ('coherent','liveBounded')),
 CHECK (state IN ('capturing','sealed','emitting','completed','aborted'))
);
CREATE TABLE corex_export_parts (
 id TEXT PRIMARY KEY NOT NULL,
 manifest_id TEXT NOT NULL,
 part_no CHAR(20) NOT NULL CHECK (length(part_no) = 20 AND part_no NOT GLOB '*[^0-9]*' AND part_no <= '18446744073709551615'),
 immutable_ref VARCHAR(1024) NOT NULL CHECK (length(immutable_ref) <= 1024),
 hash CHAR(64) NOT NULL CHECK (length(hash) = 64 AND hash NOT GLOB '*[^a-f0-9]*'),
 byte_length CHAR(20) NOT NULL CHECK (length(byte_length) = 20 AND byte_length NOT GLOB '*[^0-9]*' AND byte_length <= '18446744073709551615'),
 row_counts TEXT NOT NULL CHECK (json_valid(row_counts)),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(manifest_id,part_no),
 FOREIGN KEY(manifest_id) REFERENCES corex_export_manifests(id) ON DELETE RESTRICT,
 CHECK (state IN ('staged','verified','emitted'))
);
```

Profile `sqlite.ulid` SQL SHA256 `1c1663575d276ae26a18ff647cc24d8f58c4850d9b478d0f3d67e670f0a238b1`:

```sql
CREATE TABLE corex_export_manifests (
 id CHAR(26) PRIMARY KEY NOT NULL,
 job_id CHAR(26) NOT NULL,
 capture_attempt CHAR(64) NOT NULL CHECK (length(capture_attempt) = 64 AND capture_attempt NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 roster TEXT NOT NULL CHECK (json_valid(roster)),
 parts_hash CHAR(64) CHECK (length(parts_hash) = 64 AND parts_hash NOT GLOB '*[^a-f0-9]*'),
 snapshot_mode VARCHAR(24) NOT NULL CHECK (length(snapshot_mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(capture_attempt),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (snapshot_mode IN ('coherent','liveBounded')),
 CHECK (state IN ('capturing','sealed','emitting','completed','aborted'))
);
CREATE TABLE corex_export_parts (
 id CHAR(26) PRIMARY KEY NOT NULL,
 manifest_id CHAR(26) NOT NULL,
 part_no CHAR(20) NOT NULL CHECK (length(part_no) = 20 AND part_no NOT GLOB '*[^0-9]*' AND part_no <= '18446744073709551615'),
 immutable_ref VARCHAR(1024) NOT NULL CHECK (length(immutable_ref) <= 1024),
 hash CHAR(64) NOT NULL CHECK (length(hash) = 64 AND hash NOT GLOB '*[^a-f0-9]*'),
 byte_length CHAR(20) NOT NULL CHECK (length(byte_length) = 20 AND byte_length NOT GLOB '*[^0-9]*' AND byte_length <= '18446744073709551615'),
 row_counts TEXT NOT NULL CHECK (json_valid(row_counts)),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(manifest_id,part_no),
 FOREIGN KEY(manifest_id) REFERENCES corex_export_manifests(id) ON DELETE RESTRICT,
 CHECK (state IN ('staged','verified','emitted'))
);
```

Profile `sqlite.int` SQL SHA256 `143eaa7a418d5c089e27db2b734b4cb84410c0e297dc7b18c11ed03c49dcbfba`:

```sql
CREATE TABLE corex_export_manifests (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 job_id INTEGER NOT NULL,
 capture_attempt CHAR(64) NOT NULL CHECK (length(capture_attempt) = 64 AND capture_attempt NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 roster TEXT NOT NULL CHECK (json_valid(roster)),
 parts_hash CHAR(64) CHECK (length(parts_hash) = 64 AND parts_hash NOT GLOB '*[^a-f0-9]*'),
 snapshot_mode VARCHAR(24) NOT NULL CHECK (length(snapshot_mode) <= 24),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(capture_attempt),
 FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT,
 CHECK (snapshot_mode IN ('coherent','liveBounded')),
 CHECK (state IN ('capturing','sealed','emitting','completed','aborted'))
);
CREATE TABLE corex_export_parts (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 manifest_id INTEGER NOT NULL,
 part_no CHAR(20) NOT NULL CHECK (length(part_no) = 20 AND part_no NOT GLOB '*[^0-9]*' AND part_no <= '18446744073709551615'),
 immutable_ref VARCHAR(1024) NOT NULL CHECK (length(immutable_ref) <= 1024),
 hash CHAR(64) NOT NULL CHECK (length(hash) = 64 AND hash NOT GLOB '*[^a-f0-9]*'),
 byte_length CHAR(20) NOT NULL CHECK (length(byte_length) = 20 AND byte_length NOT GLOB '*[^0-9]*' AND byte_length <= '18446744073709551615'),
 row_counts TEXT NOT NULL CHECK (json_valid(row_counts)),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(manifest_id,part_no),
 FOREIGN KEY(manifest_id) REFERENCES corex_export_manifests(id) ON DELETE RESTRICT,
 CHECK (state IN ('staged','verified','emitted'))
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

## C-P2-019

### 2.19 — search/1

```json
{
  "profile": "search/1",
  "contract": "C-P2-019",
  "requirement_sources": {
    "R32": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-28.a1, B15 AC-28.a2, B15 AC-28.a3, B15 AC-28.a4, B35 AC-5.a1, B35 AC-5.a2; non-AC reusable/programme binding: brief/sources/b38.md §2.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 d3d33f2b510d991e99da9580f1dadd975c6e33f6b1585fd233e05cc260ecf1ea · brief/sources/b15.md §3.3; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 b19f67058707c57b5018ca4b2dc68c8ac6fcc662cba1ba4d01324d0719e28f31",
      "destination": "P2 / P6"
    }
  },
  "owning_design": {
    "path": "research/P6-data-design.md",
    "sha256": "149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb"
  },
  "files": [
    "packages/search/src/Contracts/SearchSchema.php",
    "packages/search/src/Contracts/SearchProvider.php",
    "packages/search/src/Contracts/SearchHydrator.php",
    "packages/search/src/Support/SearchSnapshot.php",
    "packages/search/src/Support/SearchCapabilities.php",
    "packages/search/src/Support/SearchQuery.php",
    "packages/search/src/Support/CandidatePage.php",
    "packages/search/src/Support/SearchPage.php"
  ],
  "indexed_files": [
    {
      "item": "P6.6",
      "path": "phases/P6/P6.6.md",
      "sha256": "18bfda23310a989894ed4233f90db2615564bcde05c312321bf7d8e62512e4ff",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md;  packages/search/src/Contracts/**; packages/search/src/Support/**; packages/search/src/Internal/Query/**; packages/search/src/Internal/Postgres/**; packages/search/src/SearchServiceProvider.php; packages/search/config/corex-search.php; packages/search/tests/**; packages/search/phpunit.xml; packages/search/phpstan.neon; packages/search/composer.json; packages/search/CHANGELOG.md; packages/search/README.md; packages/search-meilisearch/src/MeilisearchServiceProvider.php; packages/search-meilisearch/src/Internal/MeilisearchQueryAdapter.php; packages/search-meilisearch/config/corex-search-meilisearch.php; packages/search-meilisearch/tests/**; packages/search-meilisearch/phpunit.xml; packages/search-meilisearch/phpstan.neon; packages/search-meilisearch/composer.json; packages/search-meilisearch/CHANGELOG.md; packages/search-meilisearch/README.md; findings/P6-search-query.md; composer.json; phpunit.xml; tests/Arch/LayerTest.php; .github/workflows/ci.yml. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "search/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

SearchSnapshot definition trusted registered entity/field/operator projection, schemaHash+generation; query max4096textbytes/nodes64/depth8/in100/limit100/scan1000. Common PG authorized fulltext/filter subset, capability profile explicit; typo/facet/highlight denied if unsupported, no parity invention. Provider only candidateID/order/schema/generation crosses port, current SQL hydrator authorizes rows/fields and drops deleted/stale/denied before serialization; raw count/snippet/facet never emitted, SearchPage total null unless current authorized SQL aggregation. Cursor HMACscope/query/schema/provider/generation/order/position, wrongscope/stale rejects; bounded refill returns truncated opaque continuation, no unauthorized hasMore. Optional SDK absence explicitly PG configuredprofile/unsupported, provider authfailure never silent ACL-broadening fallback. No dynamic fields/search prerequisite for P4 ordinaryviews, P6static neutral fixture independentP7 locale.

Target `packages/search/src/Contracts/SearchSchema.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Contracts;
interface SearchSchema {
    public function describe(\CoreX\Support\Data\DataScope $scope, string $entity): \CoreX\Search\Support\SearchSnapshot;
}
```

Target `packages/search/src/Contracts/SearchProvider.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Contracts;
interface SearchProvider {
    public function capabilities(): \CoreX\Search\Support\SearchCapabilities;
    public function query(\CoreX\Support\Data\DataScope $scope, \CoreX\Search\Support\SearchQuery $query): \CoreX\Search\Support\CandidatePage;
}
```

Target `packages/search/src/Contracts/SearchHydrator.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Contracts;
interface SearchHydrator {
    public function authorize(\CoreX\Support\Data\DataScope $scope, \CoreX\Search\Support\CandidatePage $candidates, \CoreX\Search\Support\SearchSnapshot $schema): \CoreX\Search\Support\SearchPage;
}
```

Target `packages/search/src/Support/SearchSnapshot.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support;
final readonly class SearchSnapshot {
    /** @param array<string, null|bool|int|string|array> $definition */
    public function __construct(
        public string $entity,
        public string $schemaHash,
        public string $generation,
        public array $definition,
    ) {}
}
```

Target `packages/search/src/Support/SearchCapabilities.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support;
final readonly class SearchCapabilities {
    /** @param list<string> $features */
    public function __construct(
        public string $profile,
        public array $features,
    ) {}
}
```

Target `packages/search/src/Support/SearchQuery.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support;
final readonly class SearchQuery {
    /** @param array<string, null|bool|int|string|array> $filter | @param list<json-map> $sort */
    public function __construct(
        public string $entity,
        public string $schemaHash,
        public string $text,
        public array $filter,
        public array $sort,
        public int $limit,
        public int $scanBudget,
        public ?string $cursor,
    ) {}
}
```

Target `packages/search/src/Support/CandidatePage.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support;
final readonly class CandidatePage {
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
        public ?string $nextCursor,
        public bool $truncated,
        public string $schemaHash,
        public string $generation,
    ) {}
}
```

Target `packages/search/src/Support/SearchPage.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support;
final readonly class SearchPage {
    /** @param list<json-map> $records */
    public function __construct(
        public array $records,
        public ?string $nextCursor,
        public bool $truncated,
        public ?int $total,
    ) {}
}
```

## C-P2-020

### 2.20 — index-lifecycle/1

```json
{
  "profile": "index-lifecycle/1",
  "contract": "C-P2-020",
  "requirement_sources": {
    "R32": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-28.a1, B15 AC-28.a2, B15 AC-28.a3, B15 AC-28.a4, B35 AC-5.a1, B35 AC-5.a2; non-AC reusable/programme binding: brief/sources/b38.md §2.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 d3d33f2b510d991e99da9580f1dadd975c6e33f6b1585fd233e05cc260ecf1ea · brief/sources/b15.md §3.3; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 b19f67058707c57b5018ca4b2dc68c8ac6fcc662cba1ba4d01324d0719e28f31",
      "destination": "P2 / P6"
    }
  },
  "owning_design": {
    "path": "research/P6-data-design.md",
    "sha256": "149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb"
  },
  "files": [
    "packages/search/src/Contracts/SearchDocuments.php",
    "packages/search/src/Contracts/IndexDriver.php",
    "packages/search/src/Support/Index/DocumentCursor.php",
    "packages/search/src/Support/Index/DocumentBatch.php",
    "packages/search/src/Support/Index/IndexBatch.php",
    "packages/search/src/Support/Index/IndexSubmission.php",
    "packages/search/src/Support/Index/IndexOutcome.php",
    "packages/search/src/Support/Index/IndexSwap.php"
  ],
  "indexed_files": [
    {
      "item": "P6.6",
      "path": "phases/P6/P6.6.md",
      "sha256": "18bfda23310a989894ed4233f90db2615564bcde05c312321bf7d8e62512e4ff",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md;  packages/search/src/Contracts/**; packages/search/src/Support/**; packages/search/src/Internal/Query/**; packages/search/src/Internal/Postgres/**; packages/search/src/SearchServiceProvider.php; packages/search/config/corex-search.php; packages/search/tests/**; packages/search/phpunit.xml; packages/search/phpstan.neon; packages/search/composer.json; packages/search/CHANGELOG.md; packages/search/README.md; packages/search-meilisearch/src/MeilisearchServiceProvider.php; packages/search-meilisearch/src/Internal/MeilisearchQueryAdapter.php; packages/search-meilisearch/config/corex-search-meilisearch.php; packages/search-meilisearch/tests/**; packages/search-meilisearch/phpunit.xml; packages/search-meilisearch/phpstan.neon; packages/search-meilisearch/composer.json; packages/search-meilisearch/CHANGELOG.md; packages/search-meilisearch/README.md; findings/P6-search-query.md; composer.json; phpunit.xml; tests/Arch/LayerTest.php; .github/workflows/ci.yml. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    },
    {
      "item": "P6.7",
      "path": "phases/P6/P6.7.md",
      "sha256": "65ccaabeee6da73644efb3a176783a9e6cfe4c6a479b41aa78101b9f627bed6a",
      "files_exact": "packages/search/src/Internal/Index/**; packages/search/src/Support/Index/**; packages/search/src/SearchServiceProvider.php; packages/search/database/migrations/2026_09_15_000001_create_corex_search_lifecycle_tables.php; packages/search/tests/Feature/IndexLifecycleTest.php; packages/search/tests/Pg/IndexLifecyclePgTest.php; packages/search/CHANGELOG.md; packages/search/README.md; packages/search-meilisearch/src/Internal/MeilisearchIndexAdapter.php; packages/search-meilisearch/src/MeilisearchServiceProvider.php; packages/search-meilisearch/tests/Feature/IndexLifecycleTest.php; packages/search-meilisearch/CHANGELOG.md; packages/search-meilisearch/README.md; findings/P6-index-lifecycle.md. Bookkeeping only own journal/state/owned views/handoff; no foreign/product/live writes."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "index-lifecycle/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

index-lifecycle/1 pins document/schema/restore/generation cursor. IndexBatch max100 documents/1MiB, canonical payload hash. IndexSubmission status submitted/committed/unknown/failed; accepted asynchronous task is not applied proof. IndexOutcome pending/committed/failed/unknown; missing task after timeout remains unknown. One scoped SQL generation lane serializes delta/rebuild/swap; unknown quarantines the lane and fresh writes, never takes over a possibly alive sender. Dirty/outbox sourceRevision and checkpoint track a contiguous proved terminal gap-aware commit watermark, never ID allocation order. Rebuild captures an immutable source snapshot, catches gaps, fences every delta task until terminal, swaps the exact marker and verifies postcondition before selection CAS. Lost swap remains unknown; no blind inverse swap. Restore rejects old cursor/epoch. Qualified external SDK source/ABI/lock/ref/suite is P6.1/P6.7 obligation, no SDK adopted here; PG base query remains independent.

Target `packages/search/src/Contracts/SearchDocuments.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Contracts;
interface SearchDocuments {
    public function page(\CoreX\Support\Data\DataScope $scope, \CoreX\Search\Support\Index\DocumentCursor $after, int $limit): \CoreX\Search\Support\Index\DocumentBatch;
}
```

Target `packages/search/src/Contracts/IndexDriver.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Contracts;
interface IndexDriver {
    public function submit(\CoreX\Search\Support\Index\IndexBatch $batch): \CoreX\Search\Support\Index\IndexSubmission;
    public function status(\CoreX\Search\Support\Index\IndexSubmission $submission): \CoreX\Search\Support\Index\IndexOutcome;
    public function swap(\CoreX\Search\Support\Index\IndexSwap $swap): \CoreX\Search\Support\Index\IndexSubmission;
}
```

Target `packages/search/src/Support/Index/DocumentCursor.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support\Index;
final readonly class DocumentCursor {
    /** @param array<string, null|bool|int|string|array> $position */
    public function __construct(
        public string $schemaHash,
        public string $restoreEpoch,
        public string $generation,
        public array $position,
    ) {}
}
```

Target `packages/search/src/Support/Index/DocumentBatch.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support\Index;
final readonly class DocumentBatch {
    /** @param list<json-map> $documents */
    public function __construct(
        public array $documents,
        public ?\CoreX\Search\Support\Index\DocumentCursor $next,
    ) {}
}
```

Target `packages/search/src/Support/Index/IndexBatch.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support\Index;
final readonly class IndexBatch {
    /** @param list<json-map> $documents */
    public function __construct(
        public string $indexUid,
        public string $generation,
        public string $restoreEpoch,
        public string $schemaHash,
        public string $payloadHash,
        public array $documents,
    ) {}
}
```

Target `packages/search/src/Support/Index/IndexSubmission.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support\Index;
final readonly class IndexSubmission {
    public function __construct(
        public string $indexUid,
        public string $generation,
        public ?string $taskId,
        public string $requestHash,
        public string $status,
    ) {}
}
```

Target `packages/search/src/Support/Index/IndexOutcome.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support\Index;
final readonly class IndexOutcome {
    public function __construct(
        public string $status,
        public ?string $taskId,
        public ?string $errorCode,
    ) {}
}
```

Target `packages/search/src/Support/Index/IndexSwap.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Search\Support\Index;
final readonly class IndexSwap {
    public function __construct(
        public string $oldUid,
        public string $newUid,
        public string $generation,
        public string $markerHash,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `d74f89f8de19f52f387d2488fb5f1f3e79c6c7b6c7582a251bd1ff70aa54cae7`:

```sql
CREATE TABLE corex_search_dirty (
 id UUID PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 record_id VARCHAR(255) NOT NULL CHECK (length(record_id) <= 255),
 revision NUMERIC(20,0) NOT NULL CHECK (revision BETWEEN 0 AND 18446744073709551615),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 deleted BOOLEAN NOT NULL CHECK (deleted IN (TRUE,FALSE)),
 committed_event_ref VARCHAR(255) NOT NULL CHECK (length(committed_event_ref) <= 255),
 UNIQUE(entity,record_id,revision,restore_epoch)
);
CREATE INDEX corex_search_dirty_scan_idx ON corex_search_dirty (entity,record_id,revision);
CREATE TABLE corex_search_lanes (
 id UUID PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (scope_hash ~ '^[a-f0-9]{64}$'),
 generation CHAR(64) NOT NULL CHECK (generation ~ '^[a-f0-9]{64}$'),
 index_uid VARCHAR(255) NOT NULL CHECK (length(index_uid) <= 255),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 active_task VARCHAR(255) CHECK (length(active_task) <= 255),
 request_hash CHAR(64) CHECK (request_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('idle','claimed','submitted','unknown','quarantined','retired'))
);
CREATE TABLE corex_search_rebuilds (
 id UUID PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (scope_hash ~ '^[a-f0-9]{64}$'),
 generation CHAR(64) NOT NULL CHECK (generation ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 old_uid VARCHAR(255) NOT NULL CHECK (length(old_uid) <= 255),
 new_uid VARCHAR(255) NOT NULL CHECK (length(new_uid) <= 255),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 cursor JSONB NOT NULL,
 committed_watermark JSONB NOT NULL,
 swap_task VARCHAR(255) CHECK (length(swap_task) <= 255),
 marker_hash CHAR(64) NOT NULL CHECK (marker_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('capturing','catchingUp','fenced','swapping','verified','unknown','failed'))
);
```

Profile `pg16.ulid` SQL SHA256 `c312a17c27cecbc99bd799d8753bdb8636d5713fadbcaa1fd33be39087760a45`:

```sql
CREATE TABLE corex_search_dirty (
 id CHAR(26) PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 record_id VARCHAR(255) NOT NULL CHECK (length(record_id) <= 255),
 revision NUMERIC(20,0) NOT NULL CHECK (revision BETWEEN 0 AND 18446744073709551615),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 deleted BOOLEAN NOT NULL CHECK (deleted IN (TRUE,FALSE)),
 committed_event_ref VARCHAR(255) NOT NULL CHECK (length(committed_event_ref) <= 255),
 UNIQUE(entity,record_id,revision,restore_epoch)
);
CREATE INDEX corex_search_dirty_scan_idx ON corex_search_dirty (entity,record_id,revision);
CREATE TABLE corex_search_lanes (
 id CHAR(26) PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (scope_hash ~ '^[a-f0-9]{64}$'),
 generation CHAR(64) NOT NULL CHECK (generation ~ '^[a-f0-9]{64}$'),
 index_uid VARCHAR(255) NOT NULL CHECK (length(index_uid) <= 255),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 active_task VARCHAR(255) CHECK (length(active_task) <= 255),
 request_hash CHAR(64) CHECK (request_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('idle','claimed','submitted','unknown','quarantined','retired'))
);
CREATE TABLE corex_search_rebuilds (
 id CHAR(26) PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (scope_hash ~ '^[a-f0-9]{64}$'),
 generation CHAR(64) NOT NULL CHECK (generation ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 old_uid VARCHAR(255) NOT NULL CHECK (length(old_uid) <= 255),
 new_uid VARCHAR(255) NOT NULL CHECK (length(new_uid) <= 255),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 cursor JSONB NOT NULL,
 committed_watermark JSONB NOT NULL,
 swap_task VARCHAR(255) CHECK (length(swap_task) <= 255),
 marker_hash CHAR(64) NOT NULL CHECK (marker_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('capturing','catchingUp','fenced','swapping','verified','unknown','failed'))
);
```

Profile `pg16.int` SQL SHA256 `099194db1fd7ffd024e5b224057f1ec1c9e71c3f27b23514afcb6bfb84fc74d7`:

```sql
CREATE TABLE corex_search_dirty (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 record_id VARCHAR(255) NOT NULL CHECK (length(record_id) <= 255),
 revision NUMERIC(20,0) NOT NULL CHECK (revision BETWEEN 0 AND 18446744073709551615),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 deleted BOOLEAN NOT NULL CHECK (deleted IN (TRUE,FALSE)),
 committed_event_ref VARCHAR(255) NOT NULL CHECK (length(committed_event_ref) <= 255),
 UNIQUE(entity,record_id,revision,restore_epoch)
);
CREATE INDEX corex_search_dirty_scan_idx ON corex_search_dirty (entity,record_id,revision);
CREATE TABLE corex_search_lanes (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (scope_hash ~ '^[a-f0-9]{64}$'),
 generation CHAR(64) NOT NULL CHECK (generation ~ '^[a-f0-9]{64}$'),
 index_uid VARCHAR(255) NOT NULL CHECK (length(index_uid) <= 255),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch NUMERIC(20,0) NOT NULL CHECK (claim_epoch BETWEEN 0 AND 18446744073709551615),
 active_task VARCHAR(255) CHECK (length(active_task) <= 255),
 request_hash CHAR(64) CHECK (request_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('idle','claimed','submitted','unknown','quarantined','retired'))
);
CREATE TABLE corex_search_rebuilds (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (scope_hash ~ '^[a-f0-9]{64}$'),
 generation CHAR(64) NOT NULL CHECK (generation ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 schema_hash CHAR(64) NOT NULL CHECK (schema_hash ~ '^[a-f0-9]{64}$'),
 old_uid VARCHAR(255) NOT NULL CHECK (length(old_uid) <= 255),
 new_uid VARCHAR(255) NOT NULL CHECK (length(new_uid) <= 255),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 cursor JSONB NOT NULL,
 committed_watermark JSONB NOT NULL,
 swap_task VARCHAR(255) CHECK (length(swap_task) <= 255),
 marker_hash CHAR(64) NOT NULL CHECK (marker_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('capturing','catchingUp','fenced','swapping','verified','unknown','failed'))
);
```

Profile `sqlite.uuid` SQL SHA256 `8695d6c517e2696e7e36b1b110c14ac2e1e3fd082da9fe75e8ecbc2625f54fb0`:

```sql
CREATE TABLE corex_search_dirty (
 id TEXT PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 record_id VARCHAR(255) NOT NULL CHECK (length(record_id) <= 255),
 revision CHAR(20) NOT NULL CHECK (length(revision) = 20 AND revision NOT GLOB '*[^0-9]*' AND revision <= '18446744073709551615'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 deleted BOOLEAN NOT NULL CHECK (deleted IN (TRUE,FALSE)),
 committed_event_ref VARCHAR(255) NOT NULL CHECK (length(committed_event_ref) <= 255),
 UNIQUE(entity,record_id,revision,restore_epoch)
);
CREATE INDEX corex_search_dirty_scan_idx ON corex_search_dirty (entity,record_id,revision);
CREATE TABLE corex_search_lanes (
 id TEXT PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (length(scope_hash) = 64 AND scope_hash NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(64) NOT NULL CHECK (length(generation) = 64 AND generation NOT GLOB '*[^a-f0-9]*'),
 index_uid VARCHAR(255) NOT NULL CHECK (length(index_uid) <= 255),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 active_task VARCHAR(255) CHECK (length(active_task) <= 255),
 request_hash CHAR(64) CHECK (length(request_hash) = 64 AND request_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('idle','claimed','submitted','unknown','quarantined','retired'))
);
CREATE TABLE corex_search_rebuilds (
 id TEXT PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (length(scope_hash) = 64 AND scope_hash NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(64) NOT NULL CHECK (length(generation) = 64 AND generation NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 old_uid VARCHAR(255) NOT NULL CHECK (length(old_uid) <= 255),
 new_uid VARCHAR(255) NOT NULL CHECK (length(new_uid) <= 255),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 cursor TEXT NOT NULL CHECK (json_valid(cursor)),
 committed_watermark TEXT NOT NULL CHECK (json_valid(committed_watermark)),
 swap_task VARCHAR(255) CHECK (length(swap_task) <= 255),
 marker_hash CHAR(64) NOT NULL CHECK (length(marker_hash) = 64 AND marker_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('capturing','catchingUp','fenced','swapping','verified','unknown','failed'))
);
```

Profile `sqlite.ulid` SQL SHA256 `16b4639e0e624ce29e8a16c14f5a0335643e202a8e01a1ccd6be608691848aec`:

```sql
CREATE TABLE corex_search_dirty (
 id CHAR(26) PRIMARY KEY NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 record_id VARCHAR(255) NOT NULL CHECK (length(record_id) <= 255),
 revision CHAR(20) NOT NULL CHECK (length(revision) = 20 AND revision NOT GLOB '*[^0-9]*' AND revision <= '18446744073709551615'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 deleted BOOLEAN NOT NULL CHECK (deleted IN (TRUE,FALSE)),
 committed_event_ref VARCHAR(255) NOT NULL CHECK (length(committed_event_ref) <= 255),
 UNIQUE(entity,record_id,revision,restore_epoch)
);
CREATE INDEX corex_search_dirty_scan_idx ON corex_search_dirty (entity,record_id,revision);
CREATE TABLE corex_search_lanes (
 id CHAR(26) PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (length(scope_hash) = 64 AND scope_hash NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(64) NOT NULL CHECK (length(generation) = 64 AND generation NOT GLOB '*[^a-f0-9]*'),
 index_uid VARCHAR(255) NOT NULL CHECK (length(index_uid) <= 255),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 active_task VARCHAR(255) CHECK (length(active_task) <= 255),
 request_hash CHAR(64) CHECK (length(request_hash) = 64 AND request_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('idle','claimed','submitted','unknown','quarantined','retired'))
);
CREATE TABLE corex_search_rebuilds (
 id CHAR(26) PRIMARY KEY NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (length(scope_hash) = 64 AND scope_hash NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(64) NOT NULL CHECK (length(generation) = 64 AND generation NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 old_uid VARCHAR(255) NOT NULL CHECK (length(old_uid) <= 255),
 new_uid VARCHAR(255) NOT NULL CHECK (length(new_uid) <= 255),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 cursor TEXT NOT NULL CHECK (json_valid(cursor)),
 committed_watermark TEXT NOT NULL CHECK (json_valid(committed_watermark)),
 swap_task VARCHAR(255) CHECK (length(swap_task) <= 255),
 marker_hash CHAR(64) NOT NULL CHECK (length(marker_hash) = 64 AND marker_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('capturing','catchingUp','fenced','swapping','verified','unknown','failed'))
);
```

Profile `sqlite.int` SQL SHA256 `4612a655633cb3bdfbd51e8c67efe500d894195bd8a2c8b7eec8ea9628cbb947`:

```sql
CREATE TABLE corex_search_dirty (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 entity VARCHAR(128) NOT NULL CHECK (length(entity) <= 128),
 record_id VARCHAR(255) NOT NULL CHECK (length(record_id) <= 255),
 revision CHAR(20) NOT NULL CHECK (length(revision) = 20 AND revision NOT GLOB '*[^0-9]*' AND revision <= '18446744073709551615'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 deleted BOOLEAN NOT NULL CHECK (deleted IN (TRUE,FALSE)),
 committed_event_ref VARCHAR(255) NOT NULL CHECK (length(committed_event_ref) <= 255),
 UNIQUE(entity,record_id,revision,restore_epoch)
);
CREATE INDEX corex_search_dirty_scan_idx ON corex_search_dirty (entity,record_id,revision);
CREATE TABLE corex_search_lanes (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (length(scope_hash) = 64 AND scope_hash NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(64) NOT NULL CHECK (length(generation) = 64 AND generation NOT GLOB '*[^a-f0-9]*'),
 index_uid VARCHAR(255) NOT NULL CHECK (length(index_uid) <= 255),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 claim_epoch CHAR(20) NOT NULL CHECK (length(claim_epoch) = 20 AND claim_epoch NOT GLOB '*[^0-9]*' AND claim_epoch <= '18446744073709551615'),
 active_task VARCHAR(255) CHECK (length(active_task) <= 255),
 request_hash CHAR(64) CHECK (length(request_hash) = 64 AND request_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('idle','claimed','submitted','unknown','quarantined','retired'))
);
CREATE TABLE corex_search_rebuilds (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 scope_hash CHAR(64) NOT NULL CHECK (length(scope_hash) = 64 AND scope_hash NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(64) NOT NULL CHECK (length(generation) = 64 AND generation NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 schema_hash CHAR(64) NOT NULL CHECK (length(schema_hash) = 64 AND schema_hash NOT GLOB '*[^a-f0-9]*'),
 old_uid VARCHAR(255) NOT NULL CHECK (length(old_uid) <= 255),
 new_uid VARCHAR(255) NOT NULL CHECK (length(new_uid) <= 255),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 cursor TEXT NOT NULL CHECK (json_valid(cursor)),
 committed_watermark TEXT NOT NULL CHECK (json_valid(committed_watermark)),
 swap_task VARCHAR(255) CHECK (length(swap_task) <= 255),
 marker_hash CHAR(64) NOT NULL CHECK (length(marker_hash) = 64 AND marker_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(scope_hash,generation),
 CHECK (state IN ('capturing','catchingUp','fenced','swapping','verified','unknown','failed'))
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

# 3.8 — Source-complete proposal read group

## C-P2-021

### 2.21 — task-planning/1

```json
{
  "profile": "task-planning/1",
  "contract": "C-P2-021",
  "requirement_sources": {
    "R17": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B13 AC-20.a1, B13 AC-20.a2, B13 AC-20.a3, B13 AC-21.a1, B13 AC-21.a2, B13 AC-22.a1, B13 AC-22.a2, B13 AC-23.a1, B13 AC-23.a2, B13 AC-24.a1; non-AC reusable/programme binding: brief/sources/b13.md §3.4; source SHA256 e81c0f7ea57837efc66e86955563da567491943741bafb5275a883ed916d24ff; section SHA256 563804e9b13c18a65c60dfa947a32a57218c38fdab575289c2a35498aa49c19f · brief/sources/b38.md §2.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 d3d33f2b510d991e99da9580f1dadd975c6e33f6b1585fd233e05cc260ecf1ea",
      "destination": "P2 gap qualification / P7 / P9"
    },
    "R19": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B27 AC-1.a1, B27 AC-1.a2, B27 AC-2.a1, B27 AC-3.a1, B27 AC-4.a1, B27 AC-4.a2, B27 AC-5.a1, B27 AC-5.a2, B27 AC-6.a1, B27 AC-6.a2, B27 AC-7.a1, B27 AC-7.a2, B27 AC-8.a1, B27 AC-8.a2, B27 AC-9.a1, B27 AC-10.a1, B27 AC-10.a2, B27 AC-11.a1, B27 AC-12.a1, B27 AC-12.a2, B27 AC-13.a1, B27 AC-14.a1, B27 AC-14.a2, B27 AC-15.a1",
      "destination": "external + P7 demonstrated seam"
    }
  },
  "owning_design": {
    "path": "research/P7-utility-design.md",
    "sha256": "e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43"
  },
  "files": [
    "packages/tasks/src/Contracts/TaskPlanningCommand.php",
    "packages/tasks/src/Contracts/TaskPlanningAccess.php",
    "packages/tasks/src/Data/ProjectDraft.php",
    "packages/tasks/src/Data/SprintDraft.php",
    "packages/tasks/src/Data/TimeLogDraft.php",
    "packages/tasks/src/Data/PlanningToken.php",
    "packages/tasks/src/Data/PlanningSubject.php",
    "packages/tasks/src/Data/ProjectReference.php",
    "packages/tasks/src/Data/SprintReference.php",
    "packages/tasks/src/Data/TimeLogReference.php",
    "packages/tasks/src/Data/PlanningOperation.php"
  ],
  "indexed_files": [
    {
      "item": "P7.3",
      "path": "phases/P7/P7.3.md",
      "sha256": "6a04bc15451bdb2cc7e6cd5b5cd1d3a5a566eb9ee2046b5b5dca18de83762375",
      "files_exact": "packages/tasks/src/Contracts/TaskPlanningCommand.php; packages/tasks/src/Contracts/TaskPlanningAccess.php; packages/tasks/src/Data/**; packages/tasks/src/Internal/Planning/**; packages/tasks/src/TasksServiceProvider.php; packages/tasks/database/migrations/2026_09_15_000071_create_task_operation_receipts.php; packages/tasks/tests/Feature/PlanningCommandsTest.php; packages/tasks/tests/Pg/PlanningConcurrencyTest.php; packages/tasks/README.md; packages/tasks/CHANGELOG.md; findings/P7-task-primitives.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "task-planning/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

Only missing createProject/createSprint/recordTime surface; existing four commands,TaskDraft project/sprint and source schemas unchanged. PlanningOperation enum createProject/createSprint/recordTime; ProjectDraft.workspaceId existing qualified UUID mandatory; ProjectReference.type=tsk.project existing registry handle, SprintReference/TimeLogReference retain id/type/display shape. Their exact type tags sprint and time-log are DTO discriminators only, NOT morph registrations; ProjectReference.type=tsk.project is an existing registered handle. TimeLogSource existing manual/timer, minutes1..signedSQLrange/spentOnstrictDATE; project/sprint/date consistency+task lock spenttotal current. Operation→project/sprint/task lock/current TaskPlanningAccess+module scope, request key/hash/receipt+target/time totals same transaction, current policy onreplay; SQLSTATE40001/40P01 wholetransaction≤3. No new roles/KPI/status/timer/table/schema rewrite, optionaldurable enginecapture currentdomaintransaction only; same-key differenthash PlanningConflict, accessdenied TaskAccessDenied, invaliddraft InvalidPlanningDraft. Names trimmed1..190; TimeLog minutes1..2147483647, comment<=2000, task accumulated spent_minutes overflow refused. PlanningToken key<=190 bytes, canonical hash binds typed input+actor+scope+operation; Project active/Sprint planned defaults unchanged.

Target `packages/tasks/src/Contracts/TaskPlanningCommand.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Contracts;
interface TaskPlanningCommand {
    public function createProject(\CoreX\Tasks\Data\ProjectDraft $draft, \CoreX\Tasks\Data\PlanningToken $token): \CoreX\Tasks\Data\ProjectReference;
    public function createSprint(\CoreX\Tasks\Data\SprintDraft $draft, \CoreX\Tasks\Data\PlanningToken $token): \CoreX\Tasks\Data\SprintReference;
    public function recordTime(\CoreX\Tasks\Data\TimeLogDraft $draft, \CoreX\Tasks\Data\PlanningToken $token): \CoreX\Tasks\Data\TimeLogReference;
}
```

Target `packages/tasks/src/Contracts/TaskPlanningAccess.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Contracts;
interface TaskPlanningAccess {
    public function authorize(\CoreX\Tasks\Data\PlanningOperation $operation, \CoreX\Tasks\Data\PlanningSubject $subject, \CoreX\Tasks\Data\ActorData $actor, \CoreX\Tenancy\TenantContext $context): void;
}
```

Target `packages/tasks/src/Data/ProjectDraft.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
final readonly class ProjectDraft {
    public function __construct(
        public string $workspaceId,
        public string $name,
        public \CoreX\Tasks\Data\ActorData $actor,
    ) {}
}
```

Target `packages/tasks/src/Data/SprintDraft.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
final readonly class SprintDraft {
    public function __construct(
        public string $projectId,
        public string $name,
        public ?string $startDate,
        public ?string $endDate,
        public \CoreX\Tasks\Data\ActorData $actor,
    ) {}
}
```

Target `packages/tasks/src/Data/TimeLogDraft.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
final readonly class TimeLogDraft {
    public function __construct(
        public string $taskId,
        public string $userId,
        public int $minutes,
        public string $spentOn,
        public ?string $comment,
        public \CoreX\Tasks\Enums\TimeLogSource $source,
        public \CoreX\Tasks\Data\ActorData $actor,
    ) {}
}
```

Target `packages/tasks/src/Data/PlanningToken.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
final readonly class PlanningToken {
    public function __construct(
        public string $requestKey,
        public string $payloadHash,
    ) {}
}
```

Target `packages/tasks/src/Data/PlanningSubject.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
final readonly class PlanningSubject {
    public function __construct(
        public string $workspaceId,
        public ?string $projectId,
        public ?string $taskId,
        public ?string $userId,
    ) {}
}
```

Target `packages/tasks/src/Data/ProjectReference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
final readonly class ProjectReference {
    public function __construct(
        public string $id,
        public string $type,
        public string $display,
    ) {}
}
```

Target `packages/tasks/src/Data/SprintReference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
final readonly class SprintReference {
    public function __construct(
        public string $id,
        public string $type,
        public string $display,
    ) {}
}
```

Target `packages/tasks/src/Data/TimeLogReference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
final readonly class TimeLogReference {
    public function __construct(
        public string $id,
        public string $type,
        public string $display,
    ) {}
}
```

Target `packages/tasks/src/Data/PlanningOperation.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Tasks\Data;
enum PlanningOperation: string {
    case CreateProject = 'createProject';
    case CreateSprint = 'createSprint';
    case RecordTime = 'recordTime';
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `c3880033514c6a205c31f0818a7288ef3451e0e2f6cd1de25d178ed778445eca`:

```sql
CREATE TABLE corex_task_operations (
 id UUID PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 kind VARCHAR(32) NOT NULL CHECK (length(kind) <= 32),
 result_ref JSONB NOT NULL,
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(request_key),
 CHECK (kind IN ('createProject','createSprint','recordTime','materialize','completionCapture'))
);
```

Profile `pg16.ulid` SQL SHA256 `168a19b1aeace2d91bb7384f1a0b989d3ae430c738ac1d19ae45f34bbfbf000f`:

```sql
CREATE TABLE corex_task_operations (
 id CHAR(26) PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 kind VARCHAR(32) NOT NULL CHECK (length(kind) <= 32),
 result_ref JSONB NOT NULL,
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(request_key),
 CHECK (kind IN ('createProject','createSprint','recordTime','materialize','completionCapture'))
);
```

Profile `pg16.int` SQL SHA256 `ac141819e0763546ed5d59a4d04148bd6cb0647803eae0598ed774534f89a6fa`:

```sql
CREATE TABLE corex_task_operations (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 kind VARCHAR(32) NOT NULL CHECK (length(kind) <= 32),
 result_ref JSONB NOT NULL,
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(request_key),
 CHECK (kind IN ('createProject','createSprint','recordTime','materialize','completionCapture'))
);
```

Profile `sqlite.uuid` SQL SHA256 `c34eef3e047cfbfe23dadce5108ec13e6208a8948ea159a7d49428d053c399fc`:

```sql
CREATE TABLE corex_task_operations (
 id TEXT PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 kind VARCHAR(32) NOT NULL CHECK (length(kind) <= 32),
 result_ref TEXT NOT NULL CHECK (json_valid(result_ref)),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 UNIQUE(request_key),
 CHECK (kind IN ('createProject','createSprint','recordTime','materialize','completionCapture'))
);
```

Profile `sqlite.ulid` SQL SHA256 `72f00d524dc8574f530c51f1a2da3a8ba82209d0cd14df45e03a47ec62393449`:

```sql
CREATE TABLE corex_task_operations (
 id CHAR(26) PRIMARY KEY NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 kind VARCHAR(32) NOT NULL CHECK (length(kind) <= 32),
 result_ref TEXT NOT NULL CHECK (json_valid(result_ref)),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 UNIQUE(request_key),
 CHECK (kind IN ('createProject','createSprint','recordTime','materialize','completionCapture'))
);
```

Profile `sqlite.int` SQL SHA256 `d1eb0aaf84fda06271f85a5410ce48475cbf2ac67fe6d774ec05db9420636901`:

```sql
CREATE TABLE corex_task_operations (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 request_key VARCHAR(128) NOT NULL CHECK (length(request_key) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 kind VARCHAR(32) NOT NULL CHECK (length(kind) <= 32),
 result_ref TEXT NOT NULL CHECK (json_valid(result_ref)),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 UNIQUE(request_key),
 CHECK (kind IN ('createProject','createSprint','recordTime','materialize','completionCapture'))
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

## C-P2-022

### 2.22 — recurrence/1

```json
{
  "profile": "recurrence/1",
  "contract": "C-P2-022",
  "requirement_sources": {
    "R20": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B29 AC-5.a1; non-AC reusable/programme binding: brief/sources/b29.md §4.2; source SHA256 d0ea286ff35b82f8fb90638f8a6ca87826a18389b41f84375a24256a11235d1a; section SHA256 d701133d06250279339aee8d8ca21ea1081f7a6c53b1aa25df4dd045e5a09763 · brief/sources/b38.md §2.3; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 1c7d22253a4d31723a51df16db2cbf1c16f2fa5815fa1cbe9ebb745ae8153cd1",
      "destination": "P2 / P7"
    }
  },
  "owning_design": {
    "path": "research/P7-utility-design.md",
    "sha256": "e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43"
  },
  "files": [
    "packages/recurrence/src/Contracts/RecurrenceExpander.php",
    "packages/recurrence/src/Data/DateStart.php",
    "packages/recurrence/src/Data/RecurrenceOverride.php",
    "packages/recurrence/src/Data/RecurrenceSet.php",
    "packages/recurrence/src/Data/RecurrenceWindow.php",
    "packages/recurrence/src/Data/DateWindow.php",
    "packages/recurrence/src/Data/InstantWindow.php",
    "packages/recurrence/src/Data/ExpansionLimits.php",
    "packages/recurrence/src/Data/ExpansionCursor.php",
    "packages/recurrence/src/Data/Occurrence.php",
    "packages/recurrence/src/Data/OccurrencePage.php"
  ],
  "indexed_files": [
    {
      "item": "P7.1",
      "path": "phases/P7/P7.1.md",
      "sha256": "80b982ccc0735f20dbe2d11c5a0657c356248769e310cf7b318b1819ab66ce7e",
      "files_exact": "delivery/utility-source-qualification-20260915/fixtures/**; delivery/utility-source-qualification-20260915/evidence/**; delivery/utility-source-qualification-20260915/receipt.md; findings/P7-source-qualification.md. Bookkeeping own journal/state/owned views/handoff."
    },
    {
      "item": "P7.2",
      "path": "phases/P7/P7.2.md",
      "sha256": "1a909072934ae9449eb102c0bc758e3fe093f3952b7793fb9dd516431557d8f8",
      "files_exact": "packages/recurrence/src/Contracts/RecurrenceExpander.php; packages/recurrence/src/Data/**; packages/recurrence/src/Internal/**; packages/recurrence/tests/**; packages/recurrence/composer.json; packages/recurrence/phpunit.xml; packages/recurrence/phpstan.neon; packages/recurrence/README.md; packages/recurrence/CHANGELOG.md; findings/P7-recurrence.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "recurrence/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

recurrence/1 DTSTART is tagged DATE/TZID/UTC. DATE has a half-open date window; timed values use a UTC instant window after civil resolution, never midnight conversion. Final DateWindow/InstantWindow helpers convert by composition into RecurrenceWindow, not incompatible final subclasses. One RRULE and finite RDATE/EXDATE/overrides. EXRULE, multiple RRULE, custom VTIMEZONE, floating time, RANGE=THISANDFUTURE and leap second60 are unsupportedFeature. Defaults: range366days/emitted1000/candidateSteps100000/wall2s/memory32MiB/rule8192bytes/exceptions10000. Library source must qualify COUNT before query window, invalid civil times, DST gap/fold choice, moved override original identity and required BY combinations. Key is SHA256 length-prefixed seriesID/immutableRevision/valueType/original nominal/original TZID; no UTC/name/provider identity. Actual tzdbVersion required; unavailable pin is environmentMismatch. Cursor binds set/window/tzdb/library hashes; stale cursor refuses. OccurrencePage status complete/limited/invalid/unsupported; limited requires nextCursor, completeThrough only contiguous scanned prefix. No selected upstream source or new dependency here: P7.1 records exact immutable licensed ref/ABI/upstream suite before wrapper adoption, no handwritten RRULE engine.

Target `packages/recurrence/src/Contracts/RecurrenceExpander.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Contracts;
interface RecurrenceExpander {
    public function expand(\CoreX\Recurrence\Data\RecurrenceSet $set, \CoreX\Recurrence\Data\RecurrenceWindow $window, \CoreX\Recurrence\Data\ExpansionLimits $limits, ?\CoreX\Recurrence\Data\ExpansionCursor $after = null): \CoreX\Recurrence\Data\OccurrencePage;
}
```

Target `packages/recurrence/src/Data/DateStart.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class DateStart {
    public function __construct(
        public string $valueType,
        public string $nominalStart,
        public ?string $tzid,
    ) {}
}
```

Target `packages/recurrence/src/Data/RecurrenceOverride.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class RecurrenceOverride {
    public function __construct(
        public string $originalRecurrenceId,
        public ?string $effectiveStart,
        public bool $cancelled,
    ) {}
}
```

Target `packages/recurrence/src/Data/RecurrenceSet.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class RecurrenceSet {
    /** @param list<string> $rdates | @param list<string> $exdates | @param list<RecurrenceOverride> $overrides */
    public function __construct(
        public string $schemaVersion,
        public string $seriesId,
        public string $immutableRevision,
        public \CoreX\Recurrence\Data\DateStart $start,
        public ?string $rrule,
        public array $rdates,
        public array $exdates,
        public array $overrides,
        public string $tzdbVersion,
        public string $definitionHash,
    ) {}
}
```

Target `packages/recurrence/src/Data/RecurrenceWindow.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class RecurrenceWindow {
    public function __construct(
        public string $valueType,
        public string $start,
        public string $end,
    ) {}
}
```

Target `packages/recurrence/src/Data/DateWindow.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class DateWindow {
    public function __construct(
        public string $start,
        public string $end,
    ) {}
}
```

Target `packages/recurrence/src/Data/InstantWindow.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class InstantWindow {
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {}
}
```

Target `packages/recurrence/src/Data/ExpansionLimits.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class ExpansionLimits {
    public function __construct(
        public int $rangeDays = 366,
        public int $emitted = 1000,
        public int $candidateSteps = 100000,
        public int $wallSeconds = 2,
        public int $memoryMiB = 32,
        public int $ruleBytes = 8192,
        public int $exceptionCount = 10000,
    ) {}
}
```

Target `packages/recurrence/src/Data/ExpansionCursor.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class ExpansionCursor {
    /** @param array<string, null|bool|int|string|array> $resumePosition */
    public function __construct(
        public string $setHash,
        public string $windowHash,
        public string $tzdbVersion,
        public string $libraryVersion,
        public array $resumePosition,
    ) {}
}
```

Target `packages/recurrence/src/Data/Occurrence.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class Occurrence {
    public function __construct(
        public string $key,
        public string $originalRecurrenceId,
        public string $valueType,
        public ?string $tzid,
        public string $nominal,
        public string $effectiveDateOrInstant,
        public ?int $offset,
        public string $tzdbVersion,
        public string $definitionHash,
    ) {}
}
```

Target `packages/recurrence/src/Data/OccurrencePage.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Recurrence\Data;
final readonly class OccurrencePage {
    /** @param list<Occurrence> $occurrences */
    public function __construct(
        public array $occurrences,
        public string $status,
        public ?\CoreX\Recurrence\Data\ExpansionCursor $nextCursor,
        public ?string $completeThrough,
        public int $scanned,
        public int $emitted,
        public string $definitionHash,
    ) {}
}
```

## C-P2-023

### 2.23 — task-materialization/1

```json
{
  "profile": "task-materialization/1",
  "contract": "C-P2-023",
  "requirement_sources": {
    "R17": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B13 AC-20.a1, B13 AC-20.a2, B13 AC-20.a3, B13 AC-21.a1, B13 AC-21.a2, B13 AC-22.a1, B13 AC-22.a2, B13 AC-23.a1, B13 AC-23.a2, B13 AC-24.a1; non-AC reusable/programme binding: brief/sources/b13.md §3.4; source SHA256 e81c0f7ea57837efc66e86955563da567491943741bafb5275a883ed916d24ff; section SHA256 563804e9b13c18a65c60dfa947a32a57218c38fdab575289c2a35498aa49c19f · brief/sources/b38.md §2.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 d3d33f2b510d991e99da9580f1dadd975c6e33f6b1585fd233e05cc260ecf1ea",
      "destination": "P2 gap qualification / P7 / P9"
    },
    "R19": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B27 AC-1.a1, B27 AC-1.a2, B27 AC-2.a1, B27 AC-3.a1, B27 AC-4.a1, B27 AC-4.a2, B27 AC-5.a1, B27 AC-5.a2, B27 AC-6.a1, B27 AC-6.a2, B27 AC-7.a1, B27 AC-7.a2, B27 AC-8.a1, B27 AC-8.a2, B27 AC-9.a1, B27 AC-10.a1, B27 AC-10.a2, B27 AC-11.a1, B27 AC-12.a1, B27 AC-12.a2, B27 AC-13.a1, B27 AC-14.a1, B27 AC-14.a2, B27 AC-15.a1",
      "destination": "external + P7 demonstrated seam"
    },
    "R20": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B29 AC-5.a1; non-AC reusable/programme binding: brief/sources/b29.md §4.2; source SHA256 d0ea286ff35b82f8fb90638f8a6ca87826a18389b41f84375a24256a11235d1a; section SHA256 d701133d06250279339aee8d8ca21ea1081f7a6c53b1aa25df4dd045e5a09763 · brief/sources/b38.md §2.3; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 1c7d22253a4d31723a51df16db2cbf1c16f2fa5815fa1cbe9ebb745ae8153cd1",
      "destination": "P2 / P7"
    }
  },
  "owning_design": {
    "path": "research/P7-utility-design.md",
    "sha256": "e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43"
  },
  "files": [
    "packages/tasks-workflow/src/Contracts/TaskRecurrenceCommand.php",
    "packages/tasks-workflow/src/Data/SeriesReference.php",
    "packages/tasks-workflow/src/Data/MaterializationWindow.php",
    "packages/tasks-workflow/src/Data/MaterializationReceipt.php"
  ],
  "indexed_files": [
    {
      "item": "P7.4",
      "path": "phases/P7/P7.4.md",
      "sha256": "e11e137bb11cea2339890c7d94c76506b90b4412b9f67e3b1732d608fc009b13",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md; phpunit.xml; .github/workflows/ci.yml;  packages/tasks-workflow/src/Contracts/TaskRecurrenceCommand.php; packages/tasks-workflow/src/Data/**; packages/tasks-workflow/src/Internal/**; packages/tasks-workflow/src/TasksWorkflowServiceProvider.php; packages/tasks-workflow/config/corex-task-recurrence.php; packages/tasks-workflow/database/migrations/2026_09_15_000072_create_task_series_mapping.php; packages/tasks-workflow/tests/**; packages/tasks-workflow/composer.json; packages/tasks-workflow/phpunit.xml; packages/tasks-workflow/phpstan.neon; packages/tasks-workflow/README.md; packages/tasks-workflow/CHANGELOG.md; findings/P7-task-recurrence.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "task-materialization/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

task-materialization/1 uses immutable series revision/hash and WriteToken.expectedRecordVersion equal to the admitted series revision. Default ahead30days/page100/runtime5s. Lock series state, occurrence key, existing CreateTaskCommand on the same connection, then durable trigger/result/cursor; SQL uniqueness protects (series,revision,key) and operation key. Current actor/module/context/restore/generation/record policy checked after lock before every write. Unknown/conflict never advances unresolved prefix. Catch-up starts oldest incomplete prefix; consumer may explicitly cancel/archive missed occurrences with receipt. Edit cutover serializes retirement/CAS active revision with explicit replacement mapping, never duplicates every future timestamp or deletes committed history. CompletionCaptureObserver records a unique completion trigger INSIDE the actual task transaction before advisory afterCommit event. P5.2 local wakeup required for scheduling; P5.4 qualified engine optional only. Task refs reuse accepted TaskReference, old recurrence_rule_id remains untouched.

Target `packages/tasks-workflow/src/Contracts/TaskRecurrenceCommand.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\TasksWorkflow\Contracts;
interface TaskRecurrenceCommand {
    public function materialize(\CoreX\Support\Data\DataScope $scope, \CoreX\TasksWorkflow\Data\SeriesReference $series, \CoreX\TasksWorkflow\Data\MaterializationWindow $window, \CoreX\Support\Data\WriteToken $token): \CoreX\TasksWorkflow\Data\MaterializationReceipt;
}
```

Target `packages/tasks-workflow/src/Data/SeriesReference.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\TasksWorkflow\Data;
final readonly class SeriesReference {
    public function __construct(
        public string $id,
        public string $immutableRevision,
        public string $definitionHash,
    ) {}
}
```

Target `packages/tasks-workflow/src/Data/MaterializationWindow.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\TasksWorkflow\Data;
final readonly class MaterializationWindow {
    public function __construct(
        public string $start,
        public string $end,
        public int $aheadDays = 30,
        public int $pageLimit = 100,
        public int $maxSeconds = 5,
    ) {}
}
```

Target `packages/tasks-workflow/src/Data/MaterializationReceipt.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\TasksWorkflow\Data;
final readonly class MaterializationReceipt {
    /** @param list<CoreX\Tasks\Data\TaskReference> $taskRefs */
    public function __construct(
        public string $version,
        public array $taskRefs,
        public string $pageDigest,
        public ?string $committedPrefix,
        public ?\CoreX\Recurrence\Data\ExpansionCursor $cursor,
        public int $created,
        public int $noop,
        public int $conflict,
        public bool $retired,
        public bool $unknown,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `f63c8486be9a4ccbea6ef43125b09b42dd875186f1e4d20fa13a1d8764b2a1ad`:

```sql
CREATE TABLE corex_task_series (
 id UUID PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 definition_hash CHAR(64) NOT NULL CHECK (definition_hash ~ '^[a-f0-9]{64}$'),
 definition JSONB NOT NULL,
 template JSONB NOT NULL,
 actor JSONB NOT NULL,
 context_hash CHAR(64) NOT NULL CHECK (context_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 UNIQUE(series_key,immutable_revision)
);
CREATE TABLE corex_task_series_state (
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 active_revision VARCHAR(128) NOT NULL CHECK (length(active_revision) <= 128),
 retired BOOLEAN NOT NULL CHECK (retired IN (TRUE,FALSE)),
 complete_through VARCHAR(128) CHECK (length(complete_through) <= 128),
 cursor JSONB,
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 PRIMARY KEY(series_key),
 FOREIGN KEY(series_key,active_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT
);
CREATE TABLE corex_task_occurrences (
 id UUID PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 occurrence_key CHAR(64) NOT NULL CHECK (occurrence_key ~ '^[a-f0-9]{64}$'),
 original_identity JSONB NOT NULL,
 effective_at VARCHAR(128) NOT NULL CHECK (length(effective_at) <= 128),
 task_ref JSONB,
 operation_key VARCHAR(128) NOT NULL CHECK (length(operation_key) <= 128),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(series_key,immutable_revision,occurrence_key),
 UNIQUE(operation_key),
 FOREIGN KEY(series_key,immutable_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT,
 CHECK (state IN ('pending','committed','cancelled','retired','unknown'))
);
```

Profile `pg16.ulid` SQL SHA256 `df3a1b21430ce85d7d2bfa685aa391e8d3eb72a29db9739c14ba38b6e568b982`:

```sql
CREATE TABLE corex_task_series (
 id CHAR(26) PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 definition_hash CHAR(64) NOT NULL CHECK (definition_hash ~ '^[a-f0-9]{64}$'),
 definition JSONB NOT NULL,
 template JSONB NOT NULL,
 actor JSONB NOT NULL,
 context_hash CHAR(64) NOT NULL CHECK (context_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 UNIQUE(series_key,immutable_revision)
);
CREATE TABLE corex_task_series_state (
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 active_revision VARCHAR(128) NOT NULL CHECK (length(active_revision) <= 128),
 retired BOOLEAN NOT NULL CHECK (retired IN (TRUE,FALSE)),
 complete_through VARCHAR(128) CHECK (length(complete_through) <= 128),
 cursor JSONB,
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 PRIMARY KEY(series_key),
 FOREIGN KEY(series_key,active_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT
);
CREATE TABLE corex_task_occurrences (
 id CHAR(26) PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 occurrence_key CHAR(64) NOT NULL CHECK (occurrence_key ~ '^[a-f0-9]{64}$'),
 original_identity JSONB NOT NULL,
 effective_at VARCHAR(128) NOT NULL CHECK (length(effective_at) <= 128),
 task_ref JSONB,
 operation_key VARCHAR(128) NOT NULL CHECK (length(operation_key) <= 128),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(series_key,immutable_revision,occurrence_key),
 UNIQUE(operation_key),
 FOREIGN KEY(series_key,immutable_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT,
 CHECK (state IN ('pending','committed','cancelled','retired','unknown'))
);
```

Profile `pg16.int` SQL SHA256 `0bc4ec918bf3172278a2b0c1a5e9c6b84568890d7aa699efa635d5872caf8a60`:

```sql
CREATE TABLE corex_task_series (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 definition_hash CHAR(64) NOT NULL CHECK (definition_hash ~ '^[a-f0-9]{64}$'),
 definition JSONB NOT NULL,
 template JSONB NOT NULL,
 actor JSONB NOT NULL,
 context_hash CHAR(64) NOT NULL CHECK (context_hash ~ '^[a-f0-9]{64}$'),
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 UNIQUE(series_key,immutable_revision)
);
CREATE TABLE corex_task_series_state (
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 active_revision VARCHAR(128) NOT NULL CHECK (length(active_revision) <= 128),
 retired BOOLEAN NOT NULL CHECK (retired IN (TRUE,FALSE)),
 complete_through VARCHAR(128) CHECK (length(complete_through) <= 128),
 cursor JSONB,
 restore_epoch CHAR(64) NOT NULL CHECK (restore_epoch ~ '^[a-f0-9]{64}$'),
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 PRIMARY KEY(series_key),
 FOREIGN KEY(series_key,active_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT
);
CREATE TABLE corex_task_occurrences (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 occurrence_key CHAR(64) NOT NULL CHECK (occurrence_key ~ '^[a-f0-9]{64}$'),
 original_identity JSONB NOT NULL,
 effective_at VARCHAR(128) NOT NULL CHECK (length(effective_at) <= 128),
 task_ref JSONB,
 operation_key VARCHAR(128) NOT NULL CHECK (length(operation_key) <= 128),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(series_key,immutable_revision,occurrence_key),
 UNIQUE(operation_key),
 FOREIGN KEY(series_key,immutable_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT,
 CHECK (state IN ('pending','committed','cancelled','retired','unknown'))
);
```

Profile `sqlite.uuid` SQL SHA256 `832b3b8161f918dc3236e8fc652b1bd6c06dd453314934c4194a6840feb2368a`:

```sql
CREATE TABLE corex_task_series (
 id TEXT PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 definition_hash CHAR(64) NOT NULL CHECK (length(definition_hash) = 64 AND definition_hash NOT GLOB '*[^a-f0-9]*'),
 definition TEXT NOT NULL CHECK (json_valid(definition)),
 template TEXT NOT NULL CHECK (json_valid(template)),
 actor TEXT NOT NULL CHECK (json_valid(actor)),
 context_hash CHAR(64) NOT NULL CHECK (length(context_hash) = 64 AND context_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(series_key,immutable_revision)
);
CREATE TABLE corex_task_series_state (
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 active_revision VARCHAR(128) NOT NULL CHECK (length(active_revision) <= 128),
 retired BOOLEAN NOT NULL CHECK (retired IN (TRUE,FALSE)),
 complete_through VARCHAR(128) CHECK (length(complete_through) <= 128),
 cursor TEXT CHECK (json_valid(cursor)),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 PRIMARY KEY(series_key),
 FOREIGN KEY(series_key,active_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT
);
CREATE TABLE corex_task_occurrences (
 id TEXT PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 occurrence_key CHAR(64) NOT NULL CHECK (length(occurrence_key) = 64 AND occurrence_key NOT GLOB '*[^a-f0-9]*'),
 original_identity TEXT NOT NULL CHECK (json_valid(original_identity)),
 effective_at VARCHAR(128) NOT NULL CHECK (length(effective_at) <= 128),
 task_ref TEXT CHECK (json_valid(task_ref)),
 operation_key VARCHAR(128) NOT NULL CHECK (length(operation_key) <= 128),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(series_key,immutable_revision,occurrence_key),
 UNIQUE(operation_key),
 FOREIGN KEY(series_key,immutable_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT,
 CHECK (state IN ('pending','committed','cancelled','retired','unknown'))
);
```

Profile `sqlite.ulid` SQL SHA256 `6e9b83581d8a35df54c33d93de5d41c943fc092a214cd232fc22c13ecde8d3eb`:

```sql
CREATE TABLE corex_task_series (
 id CHAR(26) PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 definition_hash CHAR(64) NOT NULL CHECK (length(definition_hash) = 64 AND definition_hash NOT GLOB '*[^a-f0-9]*'),
 definition TEXT NOT NULL CHECK (json_valid(definition)),
 template TEXT NOT NULL CHECK (json_valid(template)),
 actor TEXT NOT NULL CHECK (json_valid(actor)),
 context_hash CHAR(64) NOT NULL CHECK (length(context_hash) = 64 AND context_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(series_key,immutable_revision)
);
CREATE TABLE corex_task_series_state (
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 active_revision VARCHAR(128) NOT NULL CHECK (length(active_revision) <= 128),
 retired BOOLEAN NOT NULL CHECK (retired IN (TRUE,FALSE)),
 complete_through VARCHAR(128) CHECK (length(complete_through) <= 128),
 cursor TEXT CHECK (json_valid(cursor)),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 PRIMARY KEY(series_key),
 FOREIGN KEY(series_key,active_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT
);
CREATE TABLE corex_task_occurrences (
 id CHAR(26) PRIMARY KEY NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 occurrence_key CHAR(64) NOT NULL CHECK (length(occurrence_key) = 64 AND occurrence_key NOT GLOB '*[^a-f0-9]*'),
 original_identity TEXT NOT NULL CHECK (json_valid(original_identity)),
 effective_at VARCHAR(128) NOT NULL CHECK (length(effective_at) <= 128),
 task_ref TEXT CHECK (json_valid(task_ref)),
 operation_key VARCHAR(128) NOT NULL CHECK (length(operation_key) <= 128),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(series_key,immutable_revision,occurrence_key),
 UNIQUE(operation_key),
 FOREIGN KEY(series_key,immutable_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT,
 CHECK (state IN ('pending','committed','cancelled','retired','unknown'))
);
```

Profile `sqlite.int` SQL SHA256 `21ca3a35665f6aea25be72b1dfccd1f56304a0acef935fc9b7827001286a55ea`:

```sql
CREATE TABLE corex_task_series (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 definition_hash CHAR(64) NOT NULL CHECK (length(definition_hash) = 64 AND definition_hash NOT GLOB '*[^a-f0-9]*'),
 definition TEXT NOT NULL CHECK (json_valid(definition)),
 template TEXT NOT NULL CHECK (json_valid(template)),
 actor TEXT NOT NULL CHECK (json_valid(actor)),
 context_hash CHAR(64) NOT NULL CHECK (length(context_hash) = 64 AND context_hash NOT GLOB '*[^a-f0-9]*'),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(series_key,immutable_revision)
);
CREATE TABLE corex_task_series_state (
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 active_revision VARCHAR(128) NOT NULL CHECK (length(active_revision) <= 128),
 retired BOOLEAN NOT NULL CHECK (retired IN (TRUE,FALSE)),
 complete_through VARCHAR(128) CHECK (length(complete_through) <= 128),
 cursor TEXT CHECK (json_valid(cursor)),
 restore_epoch CHAR(64) NOT NULL CHECK (length(restore_epoch) = 64 AND restore_epoch NOT GLOB '*[^a-f0-9]*'),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 PRIMARY KEY(series_key),
 FOREIGN KEY(series_key,active_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT
);
CREATE TABLE corex_task_occurrences (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 series_key VARCHAR(128) NOT NULL CHECK (length(series_key) <= 128),
 immutable_revision VARCHAR(128) NOT NULL CHECK (length(immutable_revision) <= 128),
 occurrence_key CHAR(64) NOT NULL CHECK (length(occurrence_key) = 64 AND occurrence_key NOT GLOB '*[^a-f0-9]*'),
 original_identity TEXT NOT NULL CHECK (json_valid(original_identity)),
 effective_at VARCHAR(128) NOT NULL CHECK (length(effective_at) <= 128),
 task_ref TEXT CHECK (json_valid(task_ref)),
 operation_key VARCHAR(128) NOT NULL CHECK (length(operation_key) <= 128),
 state VARCHAR(24) NOT NULL CHECK (length(state) <= 24),
 UNIQUE(series_key,immutable_revision,occurrence_key),
 UNIQUE(operation_key),
 FOREIGN KEY(series_key,immutable_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT,
 CHECK (state IN ('pending','committed','cancelled','retired','unknown'))
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

## C-P2-024

### 2.24 — locale/1

```json
{
  "profile": "locale/1",
  "contract": "C-P2-024",
  "requirement_sources": {
    "R23": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B35 AC-2.a1, B35 AC-7.a1, B35 AC-7.a2, B35 AC-10.a1; non-AC reusable/programme binding: brief/sources/b35.md §3.1; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 f170ce7a70d31cd9c2e89d038555959afae383d30bf92ffe49580b4be6d51621 · brief/sources/b35.md §3.2; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 4a8d85f9da038f26fc5d1e80a662fe0e1eff90b20d195d25c8f145d964901b73",
      "destination": "P2 / P7"
    }
  },
  "owning_design": {
    "path": "research/P7-utility-design.md",
    "sha256": "e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43"
  },
  "files": [
    "packages/intl/src/Contracts/LocaleResolver.php",
    "packages/intl/src/Contracts/TranslationReader.php",
    "packages/intl/src/Data/LocalePolicy.php",
    "packages/intl/src/Data/LocaleInputs.php",
    "packages/intl/src/Data/LocaleResolution.php",
    "packages/intl/src/Data/LocalizedText.php",
    "packages/intl/src/Data/TranslationResult.php"
  ],
  "indexed_files": [
    {
      "item": "P7.5",
      "path": "phases/P7/P7.5.md",
      "sha256": "dd2a573adfdda896818ba6f5a2dae194a4407531638d4da8559ebdd8bc0d6103",
      "files_exact": "packages/intl/src/Contracts/LocaleResolver.php; packages/intl/src/Contracts/TranslationReader.php; packages/intl/src/Data/**; packages/intl/src/Internal/Locale/**; packages/intl/src/IntlServiceProvider.php; packages/intl/config/corex-intl.php; packages/intl/tests/Locale/**; packages/intl/composer.json; packages/intl/phpunit.xml; packages/intl/tests/Pest.php; packages/intl/tests/TestCase.php; packages/intl/tests/PgTestCase.php; packages/intl/phpstan.neon; packages/intl/README.md; packages/intl/CHANGELOG.md; findings/P7-locale.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "locale/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

locale/1 supports <=100 allowlisted canonical BCP47 tags <=128 bytes. Trusted projected user, local cached Settings, app, then explicit fallback/allowParent policy; no root lookup or persistent Locale::setDefault. Inputs tags nullable, requestContextKey correlation is not authority. Resolution origin projected-user/account/app/fallback, used tag supported, fallback chain/policy hash and actual ICU version nullable. LocalizedText declaredField/schemaHash both null only for declared UI resource adapter, both present for qualified consumer field allowlist (C06 still external). Missing/null fall through, empty string is a value. TranslationResult found/missing/unsupported distinguishes null from empty. ICU absence supports explicit plain exact formatting or unsupportedFormatting. Existing read-only Translator/Settings reused; no translation store/contact localization. Invalid locale/schema/access typed failures, restore context in finally.

Target `packages/intl/src/Contracts/LocaleResolver.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface LocaleResolver {
    public function resolve(\CoreX\Intl\Data\LocaleInputs $inputs, \CoreX\Intl\Data\LocalePolicy $policy): \CoreX\Intl\Data\LocaleResolution;
}
```

Target `packages/intl/src/Contracts/TranslationReader.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface TranslationReader {
    public function read(\CoreX\Intl\Data\LocalizedText $text, \CoreX\Intl\Data\LocaleResolution $locale): \CoreX\Intl\Data\TranslationResult;
}
```

Target `packages/intl/src/Data/LocalePolicy.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class LocalePolicy {
    /** @param list<string> $supportedTags | @param list<string> $explicitFallbackTags */
    public function __construct(
        public array $supportedTags,
        public string $appTag,
        public array $explicitFallbackTags,
        public bool $allowParent,
        public string $policyHash,
    ) {}
}
```

Target `packages/intl/src/Data/LocaleInputs.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class LocaleInputs {
    public function __construct(
        public ?string $projectedUserTag,
        public ?string $accountSettingsTag,
        public string $requestContextKey,
    ) {}
}
```

Target `packages/intl/src/Data/LocaleResolution.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class LocaleResolution {
    /** @param list<string> $fallbackChain */
    public function __construct(
        public ?string $requested,
        public string $used,
        public string $origin,
        public array $fallbackChain,
        public bool $wasFallback,
        public string $policyHash,
        public ?string $icuVersion,
    ) {}
}
```

Target `packages/intl/src/Data/LocalizedText.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class LocalizedText {
    /** @param array<string,string|null> $map */
    public function __construct(
        public ?string $declaredField,
        public ?string $schemaHash,
        public array $map,
    ) {}
}
```

Target `packages/intl/src/Data/TranslationResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class TranslationResult {
    public function __construct(
        public string $status,
        public ?string $value,
        public ?string $usedLocale,
        public bool $wasFallback,
    ) {}
}
```

# 3.9 — Source-complete proposal read group

## C-P2-025

### 2.25 — rate-snapshot/1 + money/keep

```json
{
  "profile": "rate-snapshot/1 + money/keep",
  "contract": "C-P2-025",
  "requirement_sources": {
    "R24": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B35 AC-12.a1; non-AC reusable/programme binding: brief/sources/b35.md §3.7; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 267439816cd09d3c8eb3ecba0caff9c270add098bec60cbc091927301ac1bb38 · brief/sources/b35.md §3.8; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 7709fee45586c94d3b6182a7617fdb6a297017429d025642420e5d0f3c450d87",
      "destination": "P2 qualification / P7"
    }
  },
  "owning_design": {
    "path": "research/P7-utility-design.md",
    "sha256": "e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43"
  },
  "files": [
    "packages/intl/src/Contracts/CurrencyMetadata.php",
    "packages/intl/src/Contracts/RateProvider.php",
    "packages/intl/src/Contracts/RateImporter.php",
    "packages/intl/src/Contracts/CurrencyConverter.php",
    "packages/intl/src/Contracts/MoneyFormatter.php",
    "packages/intl/src/Data/CurrencyInfo.php",
    "packages/intl/src/Data/RateQuery.php",
    "packages/intl/src/Data/RateSnapshot.php",
    "packages/intl/src/Data/RateLookup.php",
    "packages/intl/src/Data/RateBatch.php",
    "packages/intl/src/Data/RateImportReceipt.php",
    "packages/intl/src/Data/ConversionPolicy.php",
    "packages/intl/src/Data/ConversionReceipt.php",
    "packages/intl/src/Data/FormatPolicy.php",
    "packages/intl/src/Data/FormattedMoney.php"
  ],
  "indexed_files": [
    {
      "item": "P7.6",
      "path": "phases/P7/P7.6.md",
      "sha256": "b5feae3eba5ee51d3f1f9b80812646413cbf6f8d1a80461a61d88c41cc6313a0",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md; phpunit.xml; .github/workflows/ci.yml;  packages/intl/src/Contracts/CurrencyMetadata.php; packages/intl/src/Contracts/RateProvider.php; packages/intl/src/Contracts/RateImporter.php; packages/intl/src/Contracts/CurrencyConverter.php; packages/intl/src/Contracts/MoneyFormatter.php; packages/intl/src/Data/**; packages/intl/src/Internal/Money/**; packages/intl/src/IntlServiceProvider.php; packages/intl/config/corex-intl.php; packages/intl/database/migrations/2026_09_15_000073_create_rate_snapshots.php; packages/intl/tests/Money/**; packages/intl/tests/Pg/RateImportConcurrencyTest.php; packages/intl/composer.json; packages/intl/phpunit.xml; packages/intl/tests/Pest.php; packages/intl/tests/TestCase.php; packages/intl/tests/PgTestCase.php; packages/intl/phpstan.neon; packages/intl/README.md; packages/intl/CHANGELOG.md; findings/P7-money-rates.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "rate-snapshot/1 + money/keep",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

rate-snapshot/1 leaves MoneyCast/Brick minor arithmetic and integer serialization unchanged. Quote per1base; base/quote actual Brick ISO metadata. Positive canonical rate string <=38 total/18 fractional digits, strict validOn DATE, observedAt UTC, immutable source/publication/revision/pair/effective-date unique. Same key/hash noop, changed bytes conflict, corrections new revision. RateLookup found/missing/stale/unsupported; stale admitted only by explicit policy, no future effective date/publication cutoff, triangulation, default vendor or tariff. RateBatch <=1000/1MiB validates before same-DB writes; WriteToken expected publication revision (absent only new) plus generation CAS selects immutable pointer. ConversionPolicy requires Brick RoundingMode, Context, policyId, allowStale. Exact RationalMoney multiplication then ONE explicit rounding; pair mismatch/nonrepresentability refuses. ConversionReceipt.requestedDate equals the chosen rate.validOn in this signature; caller retains original RateQuery.onDate separately, no fabricated queried date. Base=quote caller identity path bypasses provider without fake rate snapshot. FormatPolicy mode plainExact/icuVerified, fallback plainExact; plain exact ISO+decimal default at all magnitudes. ICU float-only formatting requires measured render-to-minor exponent/digit parity and conservative bound, else exact fallback. Manual source independent of live provider, no CBR/root/tariff/retention mandate. Typed invalidRate/unsupportedCapability/versionConflict/operationConflict/accessDenied failures.

Target `packages/intl/src/Contracts/CurrencyMetadata.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface CurrencyMetadata {
    public function get(string $currencyCode): \CoreX\Intl\Data\CurrencyInfo;
}
```

Target `packages/intl/src/Contracts/RateProvider.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface RateProvider {
    public function lookup(\CoreX\Intl\Data\RateQuery $query): \CoreX\Intl\Data\RateLookup;
}
```

Target `packages/intl/src/Contracts/RateImporter.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface RateImporter {
    public function import(\CoreX\Support\Data\DataScope $scope, \CoreX\Intl\Data\RateBatch $batch, \CoreX\Support\Data\WriteToken $token): \CoreX\Intl\Data\RateImportReceipt;
}
```

Target `packages/intl/src/Contracts/CurrencyConverter.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface CurrencyConverter {
    public function convert(\Brick\Money\Money $amount, \CoreX\Intl\Data\RateSnapshot $rate, \CoreX\Intl\Data\ConversionPolicy $policy): \CoreX\Intl\Data\ConversionReceipt;
}
```

Target `packages/intl/src/Contracts/MoneyFormatter.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface MoneyFormatter {
    public function format(\Brick\Money\Money $amount, \CoreX\Intl\Data\LocaleResolution $locale, \CoreX\Intl\Data\FormatPolicy $policy): \CoreX\Intl\Data\FormattedMoney;
}
```

Target `packages/intl/src/Data/CurrencyInfo.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class CurrencyInfo {
    public function __construct(
        public string $isoCode,
        public int $exponent,
        public ?string $name,
        public ?string $symbol,
        public string $metadataVersion,
        public string $metadataHash,
    ) {}
}
```

Target `packages/intl/src/Data/RateQuery.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class RateQuery {
    /** @param list<string> $sourceSelection */
    public function __construct(
        public string $base,
        public string $quote,
        public string $onDate,
        public array $sourceSelection,
        public ?\DateTimeImmutable $publicationCutoff,
        public int $maxAgeDays,
        public bool $allowStale = false,
    ) {}
}
```

Target `packages/intl/src/Data/RateSnapshot.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class RateSnapshot {
    public function __construct(
        public string $schema,
        public string $base,
        public string $quote,
        public string $rateDecimal,
        public string $validOn,
        public \DateTimeImmutable $observedAt,
        public string $source,
        public string $publicationId,
        public string $publicationRevision,
        public string $payloadSha256,
        public string $id,
    ) {}
}
```

Target `packages/intl/src/Data/RateLookup.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class RateLookup {
    public function __construct(
        public string $status,
        public ?\CoreX\Intl\Data\RateSnapshot $snapshot,
    ) {}
}
```

Target `packages/intl/src/Data/RateBatch.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class RateBatch {
    /** @param list<RateSnapshot> $snapshots | @param array<string, null|bool|int|string|array> $sourceManifest */
    public function __construct(
        public array $snapshots,
        public int $expectedGeneration,
        public string $formatHash,
        public array $sourceManifest,
    ) {}
}
```

Target `packages/intl/src/Data/RateImportReceipt.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class RateImportReceipt {
    public function __construct(
        public string $status,
        public int $generation,
        public int $imported,
        public int $noop,
        public int $conflicts,
    ) {}
}
```

Target `packages/intl/src/Data/ConversionPolicy.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class ConversionPolicy {
    public function __construct(
        public \Brick\Math\RoundingMode $roundingMode,
        public \Brick\Money\Context $targetContext,
        public string $policyId,
        public bool $allowStale,
    ) {}
}
```

Target `packages/intl/src/Data/ConversionReceipt.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class ConversionReceipt {
    public function __construct(
        public \Brick\Money\Money $source,
        public \Brick\Money\Money $result,
        public string $rateSnapshotId,
        public string $rateHash,
        public string $policyHash,
        public string $requestedDate,
    ) {}
}
```

Target `packages/intl/src/Data/FormatPolicy.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class FormatPolicy {
    public function __construct(
        public string $mode,
        public string $fallback,
    ) {}
}
```

Target `packages/intl/src/Data/FormattedMoney.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class FormattedMoney {
    public function __construct(
        public string $text,
        public string $exactDecimal,
        public string $currency,
        public string $locale,
        public string $method,
        public bool $wasFallback,
        public ?string $icuVersion,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `1fe219e43e819f4c30632403becd7b8cfa5ec0d2f112b3caa267cbaa99ff9e6e`:

```sql
CREATE TABLE corex_rate_snapshots (
 id UUID PRIMARY KEY NOT NULL,
 schema_version VARCHAR(32) NOT NULL CHECK (length(schema_version) <= 32),
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 rate_decimal VARCHAR(64) NOT NULL CHECK (length(rate_decimal) <= 64),
 valid_on VARCHAR(10) NOT NULL CHECK (length(valid_on) <= 10),
 observed_at TIMESTAMPTZ NOT NULL,
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 publication_id VARCHAR(128) NOT NULL CHECK (length(publication_id) <= 128),
 publication_revision VARCHAR(128) NOT NULL CHECK (length(publication_revision) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(source,publication_id,publication_revision,base,quote,valid_on)
);
CREATE TABLE corex_rate_batches (
 id UUID PRIMARY KEY NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 format_hash CHAR(64) NOT NULL CHECK (format_hash ~ '^[a-f0-9]{64}$'),
 source_manifest JSONB NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(operation_key),
 CHECK (state IN ('staged','published','conflict','failed'))
);
CREATE TABLE corex_rate_current (
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 snapshot_id UUID NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 PRIMARY KEY(base,quote,source),
 FOREIGN KEY(snapshot_id) REFERENCES corex_rate_snapshots(id) ON DELETE RESTRICT
);
```

Profile `pg16.ulid` SQL SHA256 `ab95d6b6219762ba64edabf0c5859980a4ef86039e4bdf9838dceb16c65bf418`:

```sql
CREATE TABLE corex_rate_snapshots (
 id CHAR(26) PRIMARY KEY NOT NULL,
 schema_version VARCHAR(32) NOT NULL CHECK (length(schema_version) <= 32),
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 rate_decimal VARCHAR(64) NOT NULL CHECK (length(rate_decimal) <= 64),
 valid_on VARCHAR(10) NOT NULL CHECK (length(valid_on) <= 10),
 observed_at TIMESTAMPTZ NOT NULL,
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 publication_id VARCHAR(128) NOT NULL CHECK (length(publication_id) <= 128),
 publication_revision VARCHAR(128) NOT NULL CHECK (length(publication_revision) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(source,publication_id,publication_revision,base,quote,valid_on)
);
CREATE TABLE corex_rate_batches (
 id CHAR(26) PRIMARY KEY NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 format_hash CHAR(64) NOT NULL CHECK (format_hash ~ '^[a-f0-9]{64}$'),
 source_manifest JSONB NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(operation_key),
 CHECK (state IN ('staged','published','conflict','failed'))
);
CREATE TABLE corex_rate_current (
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 snapshot_id CHAR(26) NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 PRIMARY KEY(base,quote,source),
 FOREIGN KEY(snapshot_id) REFERENCES corex_rate_snapshots(id) ON DELETE RESTRICT
);
```

Profile `pg16.int` SQL SHA256 `af293cf1be6443e259789762453caac39135f5b2317f09b855ce82d36aaa504c`:

```sql
CREATE TABLE corex_rate_snapshots (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 schema_version VARCHAR(32) NOT NULL CHECK (length(schema_version) <= 32),
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 rate_decimal VARCHAR(64) NOT NULL CHECK (length(rate_decimal) <= 64),
 valid_on VARCHAR(10) NOT NULL CHECK (length(valid_on) <= 10),
 observed_at TIMESTAMPTZ NOT NULL,
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 publication_id VARCHAR(128) NOT NULL CHECK (length(publication_id) <= 128),
 publication_revision VARCHAR(128) NOT NULL CHECK (length(publication_revision) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 UNIQUE(source,publication_id,publication_revision,base,quote,valid_on)
);
CREATE TABLE corex_rate_batches (
 id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (operation_key ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 format_hash CHAR(64) NOT NULL CHECK (format_hash ~ '^[a-f0-9]{64}$'),
 source_manifest JSONB NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(operation_key),
 CHECK (state IN ('staged','published','conflict','failed'))
);
CREATE TABLE corex_rate_current (
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 snapshot_id BIGINT NOT NULL,
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 PRIMARY KEY(base,quote,source),
 FOREIGN KEY(snapshot_id) REFERENCES corex_rate_snapshots(id) ON DELETE RESTRICT
);
```

Profile `sqlite.uuid` SQL SHA256 `51c24ffc247768d176a0418abc781cf579cf4fab45ae814a6f7184f7a7003b61`:

```sql
CREATE TABLE corex_rate_snapshots (
 id TEXT PRIMARY KEY NOT NULL,
 schema_version VARCHAR(32) NOT NULL CHECK (length(schema_version) <= 32),
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 rate_decimal VARCHAR(64) NOT NULL CHECK (length(rate_decimal) <= 64),
 valid_on VARCHAR(10) NOT NULL CHECK (length(valid_on) <= 10),
 observed_at TEXT NOT NULL CHECK (length(observed_at) = 27 AND substr(observed_at,27,1) = 'Z'),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 publication_id VARCHAR(128) NOT NULL CHECK (length(publication_id) <= 128),
 publication_revision VARCHAR(128) NOT NULL CHECK (length(publication_revision) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(source,publication_id,publication_revision,base,quote,valid_on)
);
CREATE TABLE corex_rate_batches (
 id TEXT PRIMARY KEY NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 format_hash CHAR(64) NOT NULL CHECK (length(format_hash) = 64 AND format_hash NOT GLOB '*[^a-f0-9]*'),
 source_manifest TEXT NOT NULL CHECK (json_valid(source_manifest)),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 UNIQUE(operation_key),
 CHECK (state IN ('staged','published','conflict','failed'))
);
CREATE TABLE corex_rate_current (
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 snapshot_id TEXT NOT NULL,
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 PRIMARY KEY(base,quote,source),
 FOREIGN KEY(snapshot_id) REFERENCES corex_rate_snapshots(id) ON DELETE RESTRICT
);
```

Profile `sqlite.ulid` SQL SHA256 `ae5801efae574c05fcd5273d680a1cd8904999b155193fc2d74430780a47c56b`:

```sql
CREATE TABLE corex_rate_snapshots (
 id CHAR(26) PRIMARY KEY NOT NULL,
 schema_version VARCHAR(32) NOT NULL CHECK (length(schema_version) <= 32),
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 rate_decimal VARCHAR(64) NOT NULL CHECK (length(rate_decimal) <= 64),
 valid_on VARCHAR(10) NOT NULL CHECK (length(valid_on) <= 10),
 observed_at TEXT NOT NULL CHECK (length(observed_at) = 27 AND substr(observed_at,27,1) = 'Z'),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 publication_id VARCHAR(128) NOT NULL CHECK (length(publication_id) <= 128),
 publication_revision VARCHAR(128) NOT NULL CHECK (length(publication_revision) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(source,publication_id,publication_revision,base,quote,valid_on)
);
CREATE TABLE corex_rate_batches (
 id CHAR(26) PRIMARY KEY NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 format_hash CHAR(64) NOT NULL CHECK (length(format_hash) = 64 AND format_hash NOT GLOB '*[^a-f0-9]*'),
 source_manifest TEXT NOT NULL CHECK (json_valid(source_manifest)),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 UNIQUE(operation_key),
 CHECK (state IN ('staged','published','conflict','failed'))
);
CREATE TABLE corex_rate_current (
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 snapshot_id CHAR(26) NOT NULL,
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 PRIMARY KEY(base,quote,source),
 FOREIGN KEY(snapshot_id) REFERENCES corex_rate_snapshots(id) ON DELETE RESTRICT
);
```

Profile `sqlite.int` SQL SHA256 `999cac66e5ab44492dca9acfb6571b2197bbde0899a3545d6687525386428e28`:

```sql
CREATE TABLE corex_rate_snapshots (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 schema_version VARCHAR(32) NOT NULL CHECK (length(schema_version) <= 32),
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 rate_decimal VARCHAR(64) NOT NULL CHECK (length(rate_decimal) <= 64),
 valid_on VARCHAR(10) NOT NULL CHECK (length(valid_on) <= 10),
 observed_at TEXT NOT NULL CHECK (length(observed_at) = 27 AND substr(observed_at,27,1) = 'Z'),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 publication_id VARCHAR(128) NOT NULL CHECK (length(publication_id) <= 128),
 publication_revision VARCHAR(128) NOT NULL CHECK (length(publication_revision) <= 128),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 UNIQUE(source,publication_id,publication_revision,base,quote,valid_on)
);
CREATE TABLE corex_rate_batches (
 id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 operation_key CHAR(64) NOT NULL CHECK (length(operation_key) = 64 AND operation_key NOT GLOB '*[^a-f0-9]*'),
 payload_hash CHAR(64) NOT NULL CHECK (length(payload_hash) = 64 AND payload_hash NOT GLOB '*[^a-f0-9]*'),
 format_hash CHAR(64) NOT NULL CHECK (length(format_hash) = 64 AND format_hash NOT GLOB '*[^a-f0-9]*'),
 source_manifest TEXT NOT NULL CHECK (json_valid(source_manifest)),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 UNIQUE(operation_key),
 CHECK (state IN ('staged','published','conflict','failed'))
);
CREATE TABLE corex_rate_current (
 base VARCHAR(3) NOT NULL CHECK (length(base) <= 3),
 quote VARCHAR(3) NOT NULL CHECK (length(quote) <= 3),
 source VARCHAR(128) NOT NULL CHECK (length(source) <= 128),
 snapshot_id INTEGER NOT NULL,
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 PRIMARY KEY(base,quote,source),
 FOREIGN KEY(snapshot_id) REFERENCES corex_rate_snapshots(id) ON DELETE RESTRICT
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

## C-P2-026

### 2.26 — reference-data/1

```json
{
  "profile": "reference-data/1",
  "contract": "C-P2-026",
  "requirement_sources": {
    "R25": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B35 AC-6.a1, B35 AC-6.a2, B35 AC-8.a1; non-AC reusable/programme binding: brief/sources/b35.md §3.5; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 a6e07cf9e9cc13a8b4c52706949b2c373120f05ea8f1525ac6b39f2852eca1bf · brief/sources/b35.md §3.6; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 57b39a1f553d76938543b13746d84db9d870b025d39995f0eec3c9b69de50a74",
      "destination": "P2 / P7 / P10 data"
    }
  },
  "owning_design": {
    "path": "research/P7-utility-design.md",
    "sha256": "e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43"
  },
  "files": [
    "packages/intl/src/Contracts/GeographyProvider.php",
    "packages/intl/src/Contracts/DatasetImporter.php",
    "packages/intl/src/Data/DatasetManifest.php",
    "packages/intl/src/Data/ReferenceIdentity.php",
    "packages/intl/src/Data/GeographyQuery.php",
    "packages/intl/src/Data/ReferenceCursor.php",
    "packages/intl/src/Data/ReferencePage.php",
    "packages/intl/src/Data/DatasetCandidate.php",
    "packages/intl/src/Data/DatasetGeneration.php",
    "packages/intl/src/Data/DatasetReceipt.php"
  ],
  "indexed_files": [
    {
      "item": "P7.7",
      "path": "phases/P7/P7.7.md",
      "sha256": "e488f4abf19961b08e21d7d8b6b403405f97cc60b71519a48c83508a8de3ee8d",
      "files_exact": "packages/core/src/Support/Data/DataScope.php; packages/core/src/Support/Data/RecordRef.php; packages/core/src/Support/Data/WriteToken.php; packages/core/tests/Unit/SupportDataTest.php; packages/core/CHANGELOG.md; findings/shared-data-foundation.md; phpunit.xml; .github/workflows/ci.yml;  packages/intl/src/Contracts/GeographyProvider.php; packages/intl/src/Contracts/DatasetImporter.php; packages/intl/src/Data/**; packages/intl/src/Internal/Reference/**; packages/intl/src/IntlServiceProvider.php; packages/intl/config/corex-intl.php; packages/intl/database/migrations/2026_09_15_000074_create_reference_snapshots.php; packages/intl/tests/Reference/**; packages/intl/tests/Pg/ReferencePublicationTest.php; packages/intl/composer.json; packages/intl/phpunit.xml; packages/intl/tests/Pest.php; packages/intl/tests/TestCase.php; packages/intl/tests/PgTestCase.php; packages/intl/phpstan.neon; packages/intl/DATA-LICENSES.md; packages/intl/README.md; packages/intl/CHANGELOG.md; findings/P7-geography.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "reference-data/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

reference-data/1 manifest pins sourceURI/licenseURI/attribution/source+importer versions/raw+normalized hashes/coverage/tzdb/counts/status. Namespace+sourceID are provider-scoped opaque stable keys, no merge by name or mandatory country/vendor. GeographyQuery kind country/subdivision/place/timezone, mode exact/prefix/fuzzy, limit1..100; optional country/parent/place/text fields validated by registered kind schema. Cursor pins snapshot/query/locale/order/position and consumer HMAC/server opaque proof. Page status available/notFound/ambiguous/unsupportedScope/snapshotExpired, records+provenance typed by declared dataset schema, no empty-success ambiguity. Immutable candidate <=512MiB/logical row64KiB/count2million/batch500/memory128MiB/runtime30s, checked before allocation; hierarchy identity/same-snapshot parent FK/license/zones/counts/hashes before atomic generation CAS pointer+manifest. Coordinates exact decimal strings lat±90/lon±180. Country does not imply one timezone, centroid not address proof. Manual neutral fixture SQLite exact/prefix supported, PG fuzzy only approved extension+actual EXPLAIN. Optional GeoNames has NO adopted artifact/ref: P7.1 actual source/license/attribution/hash/suite and P10 live coverage owning obligations. No boot download/root FK; old consumer pins retained and explicit expiry distinct.

Target `packages/intl/src/Contracts/GeographyProvider.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface GeographyProvider {
    public function describe(\CoreX\Support\Data\DataScope $scope): \CoreX\Intl\Data\DatasetManifest;
    public function query(\CoreX\Support\Data\DataScope $scope, \CoreX\Intl\Data\GeographyQuery $query, ?\CoreX\Intl\Data\ReferenceCursor $after = null): \CoreX\Intl\Data\ReferencePage;
}
```

Target `packages/intl/src/Contracts/DatasetImporter.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface DatasetImporter {
    public function publish(\CoreX\Support\Data\DataScope $scope, \CoreX\Intl\Data\DatasetCandidate $candidate, \CoreX\Intl\Data\DatasetGeneration $expected): \CoreX\Intl\Data\DatasetReceipt;
}
```

Target `packages/intl/src/Data/DatasetManifest.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class DatasetManifest {
    /** @param list<string> $coveredCountries | @param list<string> $types | @param array<string,int> $counts */
    public function __construct(
        public string $schema,
        public string $snapshotId,
        public string $provider,
        public string $sourceUri,
        public \DateTimeImmutable $retrievedAt,
        public string $rawHash,
        public string $normalizedHash,
        public string $sourceVersion,
        public string $importerVersion,
        public string $licenseUri,
        public string $attribution,
        public array $coveredCountries,
        public array $types,
        public array $counts,
        public string $coverageNotes,
        public string $tzdbVersion,
        public string $status,
    ) {}
}
```

Target `packages/intl/src/Data/ReferenceIdentity.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class ReferenceIdentity {
    public function __construct(
        public string $namespace,
        public string $sourceId,
    ) {}
}
```

Target `packages/intl/src/Data/GeographyQuery.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class GeographyQuery {
    public function __construct(
        public string $kind,
        public ?\CoreX\Intl\Data\ReferenceIdentity $countryKey,
        public ?\CoreX\Intl\Data\ReferenceIdentity $parentKey,
        public ?\CoreX\Intl\Data\ReferenceIdentity $placeKey,
        public ?string $query,
        public string $locale,
        public ?string $snapshotId,
        public string $mode,
        public int $limit,
    ) {}
}
```

Target `packages/intl/src/Data/ReferenceCursor.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class ReferenceCursor {
    public function __construct(
        public string $schema,
        public string $snapshotId,
        public string $queryHash,
        public string $lastNormalizedName,
        public string $lastNamespace,
        public string $lastSourceId,
        public string $proof,
    ) {}
}
```

Target `packages/intl/src/Data/ReferencePage.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class ReferencePage {
    /** @param list<json-map> $records | @param array<string, null|bool|int|string|array> $provenance */
    public function __construct(
        public string $status,
        public array $records,
        public ?\CoreX\Intl\Data\ReferenceCursor $next,
        public ?string $snapshotId,
        public array $provenance,
    ) {}
}
```

Target `packages/intl/src/Data/DatasetCandidate.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class DatasetCandidate {
    public function __construct(
        public \CoreX\Intl\Data\DatasetManifest $manifest,
        public string $immutableRef,
        public int $byteLength,
    ) {}
}
```

Target `packages/intl/src/Data/DatasetGeneration.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class DatasetGeneration {
    public function __construct(
        public ?string $expectedSnapshot,
        public int $generation,
    ) {}
}
```

Target `packages/intl/src/Data/DatasetReceipt.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class DatasetReceipt {
    public function __construct(
        public string $status,
        public ?string $snapshotId,
        public int $generation,
        public ?string $errorCode,
    ) {}
}
```

Exact new neutral DDL variants below. PG16 UUID/UUID7 share UUID SQL; ULID and int K variants map current IdColumns, never change config or existing key types. Explicit media/notification/wakeup UUID boundary stays UUID. SQLite serial counter codec is zero-padded20-digit CHAR, exact uint64 lexical compare (wire canonical unpadded); PG NUMERIC20 exact. SQLite UTC codec fixed27-byte yyyy-mm-ddThh:mm:ss.ffffffZ; PG UTC TIMESTAMPTZ. Both codecs require typed validated constructors and round-trip tests before installation. UUID/name/date/rate/JSON schemas receive the normative constructor guards; SQL checks are extra integrity, not full authentication. Counter overflow refuses all writes. New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection. Migration adapters must emit these exact selected SQL bytes, compare catalogue SHA and installed fingerprint before enabling; missing profile/engine ABI stays not-ready.

Profile `pg16.uuid` SQL SHA256 `52ebc025b1a7da937b307a40a6d652f89cdb1d63b3022121c6f6bb63b19fbb22`:

```sql
CREATE TABLE corex_reference_datasets (
 snapshot_id VARCHAR(128) NOT NULL CHECK (length(snapshot_id) <= 128),
 manifest JSONB NOT NULL,
 raw_hash CHAR(64) NOT NULL CHECK (raw_hash ~ '^[a-f0-9]{64}$'),
 normalized_hash CHAR(64) NOT NULL CHECK (normalized_hash ~ '^[a-f0-9]{64}$'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TIMESTAMPTZ NOT NULL,
 PRIMARY KEY(snapshot_id),
 CHECK (state IN ('staged','published','retired'))
);
CREATE TABLE corex_reference_records (
 snapshot_id VARCHAR(128) NOT NULL CHECK (length(snapshot_id) <= 128),
 namespace VARCHAR(128) NOT NULL CHECK (length(namespace) <= 128),
 source_id VARCHAR(255) NOT NULL CHECK (length(source_id) <= 255),
 kind VARCHAR(64) NOT NULL CHECK (length(kind) <= 64),
 parent_namespace VARCHAR(128) CHECK (length(parent_namespace) <= 128),
 parent_source_id VARCHAR(255) CHECK (length(parent_source_id) <= 255),
 names JSONB NOT NULL,
 normalized_name VARCHAR(255) NOT NULL CHECK (length(normalized_name) <= 255),
 country_key JSONB,
 coordinates JSONB,
 timezones JSONB NOT NULL,
 provenance JSONB NOT NULL,
 PRIMARY KEY(snapshot_id,namespace,source_id),
 FOREIGN KEY(snapshot_id) REFERENCES corex_reference_datasets(snapshot_id) ON DELETE RESTRICT,
 FOREIGN KEY(snapshot_id,parent_namespace,parent_source_id) REFERENCES corex_reference_records(snapshot_id,namespace,source_id) ON DELETE RESTRICT,
 CHECK (((parent_namespace IS NULL) = (parent_source_id IS NULL)))
);
CREATE INDEX corex_reference_records_scan_idx ON corex_reference_records (snapshot_id,kind,normalized_name);
CREATE TABLE corex_reference_aliases (
 snapshot_id VARCHAR(128) NOT NULL CHECK (length(snapshot_id) <= 128),
 namespace VARCHAR(128) NOT NULL CHECK (length(namespace) <= 128),
 source_id VARCHAR(255) NOT NULL CHECK (length(source_id) <= 255),
 locale VARCHAR(128) NOT NULL CHECK (length(locale) <= 128),
 alias VARCHAR(255) NOT NULL CHECK (length(alias) <= 255),
 normalized_alias VARCHAR(255) NOT NULL CHECK (length(normalized_alias) <= 255),
 PRIMARY KEY(snapshot_id,namespace,source_id,locale,alias),
 FOREIGN KEY(snapshot_id,namespace,source_id) REFERENCES corex_reference_records(snapshot_id,namespace,source_id) ON DELETE RESTRICT
);
CREATE TABLE corex_reference_current (
 dataset_key VARCHAR(128) NOT NULL CHECK (length(dataset_key) <= 128),
 snapshot_id VARCHAR(128) NOT NULL CHECK (length(snapshot_id) <= 128),
 generation NUMERIC(20,0) NOT NULL CHECK (generation BETWEEN 0 AND 18446744073709551615),
 PRIMARY KEY(dataset_key),
 FOREIGN KEY(snapshot_id) REFERENCES corex_reference_datasets(snapshot_id) ON DELETE RESTRICT
);
```

Profile `sqlite.uuid` SQL SHA256 `0acfe3e51ea17e559d7fef99ee4deb1c86da13f9d1c077efe3599e8a6e16d7c1`:

```sql
CREATE TABLE corex_reference_datasets (
 snapshot_id VARCHAR(128) NOT NULL CHECK (length(snapshot_id) <= 128),
 manifest TEXT NOT NULL CHECK (json_valid(manifest)),
 raw_hash CHAR(64) NOT NULL CHECK (length(raw_hash) = 64 AND raw_hash NOT GLOB '*[^a-f0-9]*'),
 normalized_hash CHAR(64) NOT NULL CHECK (length(normalized_hash) = 64 AND normalized_hash NOT GLOB '*[^a-f0-9]*'),
 state VARCHAR(16) NOT NULL CHECK (length(state) <= 16),
 created_at TEXT NOT NULL CHECK (length(created_at) = 27 AND substr(created_at,27,1) = 'Z'),
 PRIMARY KEY(snapshot_id),
 CHECK (state IN ('staged','published','retired'))
);
CREATE TABLE corex_reference_records (
 snapshot_id VARCHAR(128) NOT NULL CHECK (length(snapshot_id) <= 128),
 namespace VARCHAR(128) NOT NULL CHECK (length(namespace) <= 128),
 source_id VARCHAR(255) NOT NULL CHECK (length(source_id) <= 255),
 kind VARCHAR(64) NOT NULL CHECK (length(kind) <= 64),
 parent_namespace VARCHAR(128) CHECK (length(parent_namespace) <= 128),
 parent_source_id VARCHAR(255) CHECK (length(parent_source_id) <= 255),
 names TEXT NOT NULL CHECK (json_valid(names)),
 normalized_name VARCHAR(255) NOT NULL CHECK (length(normalized_name) <= 255),
 country_key TEXT CHECK (json_valid(country_key)),
 coordinates TEXT CHECK (json_valid(coordinates)),
 timezones TEXT NOT NULL CHECK (json_valid(timezones)),
 provenance TEXT NOT NULL CHECK (json_valid(provenance)),
 PRIMARY KEY(snapshot_id,namespace,source_id),
 FOREIGN KEY(snapshot_id) REFERENCES corex_reference_datasets(snapshot_id) ON DELETE RESTRICT,
 FOREIGN KEY(snapshot_id,parent_namespace,parent_source_id) REFERENCES corex_reference_records(snapshot_id,namespace,source_id) ON DELETE RESTRICT,
 CHECK (((parent_namespace IS NULL) = (parent_source_id IS NULL)))
);
CREATE INDEX corex_reference_records_scan_idx ON corex_reference_records (snapshot_id,kind,normalized_name);
CREATE TABLE corex_reference_aliases (
 snapshot_id VARCHAR(128) NOT NULL CHECK (length(snapshot_id) <= 128),
 namespace VARCHAR(128) NOT NULL CHECK (length(namespace) <= 128),
 source_id VARCHAR(255) NOT NULL CHECK (length(source_id) <= 255),
 locale VARCHAR(128) NOT NULL CHECK (length(locale) <= 128),
 alias VARCHAR(255) NOT NULL CHECK (length(alias) <= 255),
 normalized_alias VARCHAR(255) NOT NULL CHECK (length(normalized_alias) <= 255),
 PRIMARY KEY(snapshot_id,namespace,source_id,locale,alias),
 FOREIGN KEY(snapshot_id,namespace,source_id) REFERENCES corex_reference_records(snapshot_id,namespace,source_id) ON DELETE RESTRICT
);
CREATE TABLE corex_reference_current (
 dataset_key VARCHAR(128) NOT NULL CHECK (length(dataset_key) <= 128),
 snapshot_id VARCHAR(128) NOT NULL CHECK (length(snapshot_id) <= 128),
 generation CHAR(20) NOT NULL CHECK (length(generation) = 20 AND generation NOT GLOB '*[^0-9]*' AND generation <= '18446744073709551615'),
 PRIMARY KEY(dataset_key),
 FOREIGN KEY(snapshot_id) REFERENCES corex_reference_datasets(snapshot_id) ON DELETE RESTRICT
);
```

Rollback mapping: old state has none of these tables; keep existing domain schemas untouched. Before any down/export retirement, stop workers, inspect every owned table (all must be empty) AND prove no unknown/alive external sender or retained snapshot pin; otherwise throw MigrationRollbackDenied. Approved explicit quiesce/export/restore receipt required to proceed on populated state, no automatic drop. Empty rollback drops own tables in reverse creation order and own indexes; external restore witnesses never rolled back.

## C-P2-027

### 2.27 — intl-optional-bridge/1

```json
{
  "profile": "intl-optional-bridge/1",
  "contract": "C-P2-027",
  "requirement_sources": {
    "R23": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B35 AC-2.a1, B35 AC-7.a1, B35 AC-7.a2, B35 AC-10.a1; non-AC reusable/programme binding: brief/sources/b35.md §3.1; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 f170ce7a70d31cd9c2e89d038555959afae383d30bf92ffe49580b4be6d51621 · brief/sources/b35.md §3.2; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 4a8d85f9da038f26fc5d1e80a662fe0e1eff90b20d195d25c8f145d964901b73",
      "destination": "P2 / P7"
    },
    "R25": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B35 AC-6.a1, B35 AC-6.a2, B35 AC-8.a1; non-AC reusable/programme binding: brief/sources/b35.md §3.5; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 a6e07cf9e9cc13a8b4c52706949b2c373120f05ea8f1525ac6b39f2852eca1bf · brief/sources/b35.md §3.6; source SHA256 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b; section SHA256 57b39a1f553d76938543b13746d84db9d870b025d39995f0eec3c9b69de50a74",
      "destination": "P2 / P7 / P10 data"
    },
    "R32": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B15 AC-28.a1, B15 AC-28.a2, B15 AC-28.a3, B15 AC-28.a4, B35 AC-5.a1, B35 AC-5.a2; non-AC reusable/programme binding: brief/sources/b38.md §2.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 d3d33f2b510d991e99da9580f1dadd975c6e33f6b1585fd233e05cc260ecf1ea · brief/sources/b15.md §3.3; source SHA256 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447; section SHA256 b19f67058707c57b5018ca4b2dc68c8ac6fcc662cba1ba4d01324d0719e28f31",
      "destination": "P2 / P6"
    }
  },
  "owning_design": {
    "path": "research/P7-utility-design.md",
    "sha256": "e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43"
  },
  "files": [
    "packages/intl/src/Contracts/AddressSuggestProvider.php",
    "packages/intl/src/Data/AddressQuery.php",
    "packages/intl/src/Data/SuggestionLimits.php",
    "packages/intl/src/Data/SuggestionResult.php"
  ],
  "indexed_files": [
    {
      "item": "P7.8",
      "path": "phases/P7/P7.8.md",
      "sha256": "ba52375138e5ba536d647a7cdf43937ef11298b4d4aa97cfa8a4093131169f8d",
      "files_exact": "packages/intl/src/Contracts/AddressSuggestProvider.php; packages/intl/src/Data/**; packages/intl/src/Internal/Address/**; packages/intl/tests/Address/**; packages/intl/README.md; packages/intl/CHANGELOG.md; packages/intl-search/src/**; packages/intl-search/tests/**; packages/intl-search/composer.json; packages/intl-search/phpunit.xml; packages/intl-search/phpstan.neon; packages/intl-search/README.md; packages/intl-search/CHANGELOG.md; delivery/utility-optional-qualification-20260915/fixtures/**; delivery/utility-optional-qualification-20260915/evidence/**; delivery/utility-optional-qualification-20260915/receipt.md; findings/P7-optional-bridges.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "intl-optional-bridge/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

intl-optional-bridge/1 consumes actual P6.6 search and P6.7 index lifecycle only when installed. Current SQL row/field policy and one writer sourceRevision retained, PG never gets Meili-only flattened attributes. P4 localized labels metadata does not change filter/default/ACL grammar; locale remains consumer input until bridge, no P6 prerequisite on P7. Address suggest is optional explicit minimized text+consentReference only, timeout2s/results20/bytes65536. No automatic name/address/phone transmission, provider fallback, secret/PII logs. SuggestionResult available/notFound/unavailable/rateLimited/unsupported/invalid; available binds source/provenance, suggestions not authoritative IDs. Offline input valid when absent. Configured outage differs from missing provider; bounded rate-limit retry only consumer decision. No dependency/source adopted until integration owner immutable ref/API receipt.

Target `packages/intl/src/Contracts/AddressSuggestProvider.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Contracts;
interface AddressSuggestProvider {
    public function suggest(\CoreX\Intl\Data\AddressQuery $query, \CoreX\Intl\Data\SuggestionLimits $limits): \CoreX\Intl\Data\SuggestionResult;
}
```

Target `packages/intl/src/Data/AddressQuery.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class AddressQuery {
    public function __construct(
        public string $text,
        public string $locale,
        public ?string $country,
        public string $consentReference,
    ) {}
}
```

Target `packages/intl/src/Data/SuggestionLimits.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class SuggestionLimits {
    public function __construct(
        public int $timeoutSeconds = 2,
        public int $results = 20,
        public int $maxResponseBytes = 65536,
    ) {}
}
```

Target `packages/intl/src/Data/SuggestionResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Intl\Data;
final readonly class SuggestionResult {
    /** @param list<json-map> $suggestions | @param array<string, null|bool|int|string|array> $provenance */
    public function __construct(
        public string $status,
        public array $suggestions,
        public ?string $source,
        public array $provenance,
    ) {}
}
```

# 3.10 — Source-complete proposal read group

## C-P2-028

### 2.28 — tool-descriptor/1 + tool-schema/1 + tool-name/1

```json
{
  "profile": "tool-descriptor/1 + tool-schema/1 + tool-name/1",
  "contract": "C-P2-028",
  "requirement_sources": {
    "R08": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "non-AC reusable/programme binding: brief/sources/b33.md §7; source SHA256 be6366fc074821e4d5cfd58e3b5c953875a45cce8f948da6f93a57b419d15a27; section SHA256 37ec4ab0baf76f62af392af8d5fb374e34eb0d2501db35c2cc9a8c35de679c8c · brief/sources/b34.md §7; source SHA256 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6; section SHA256 4c9bfa54af7e55c340f87284de14e9ba75342acfe2aacc9a37c6ee836418fd08",
      "destination": "P8 / P9"
    },
    "R36": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B10 AC-34.a1, B15 AC-26.a3, B32 AC-12.a1, B35 AC-8.a2; non-AC reusable/programme binding: brief/sources/b32.md §3.3; source SHA256 443b67549c2c469a58ab990b3f48a072112bd8633279d43007d138e1e34cabb4; section SHA256 43dcae857d1c88269e89fa288e3471d4e30fcc5230d2b8e42d0bcf5eb31b73b6 · brief/sources/b38.md §2.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 d3d33f2b510d991e99da9580f1dadd975c6e33f6b1585fd233e05cc260ecf1ea",
      "destination": "P2 qualification / P8 / P9"
    }
  },
  "owning_design": {
    "path": "research/P8-tool-design.md",
    "sha256": "ded33e03fc260e632c06a4fec0e66e404cb29e6383e921e24c679ffadd8802ca"
  },
  "files": [
    "packages/api/src/Contracts/ToolCatalogue.php",
    "packages/api/src/Contracts/ToolInvoker.php",
    "packages/api/src/Contracts/ToolRequestContext.php",
    "packages/api/src/Contracts/ToolPolicy.php",
    "packages/api/src/Contracts/ToolSchemaValidator.php",
    "packages/api/src/Contracts/ToolExecutionGuard.php",
    "packages/api/src/Data/JsonValue.php",
    "packages/api/src/Data/JsonObject.php",
    "packages/api/src/Data/ToolDescriptor.php",
    "packages/api/src/Data/ToolCatalogueView.php",
    "packages/api/src/Data/ToolCall.php",
    "packages/api/src/Data/ToolResult.php",
    "packages/api/src/Data/SchemaHandle.php",
    "packages/api/src/Data/SchemaValidation.php"
  ],
  "indexed_files": [
    {
      "item": "P8.1",
      "path": "phases/P8/P8.1.md",
      "sha256": "ba5fac92cfc5420376ad6b7044dd987973aec29b91ab382fee62284244c0151f",
      "files_exact": "delivery/tool-source-qualification-20260915/fixtures/**; delivery/tool-source-qualification-20260915/evidence/**; delivery/tool-source-qualification-20260915/receipt.md; findings/P8-source-qualification.md. Bookkeeping own journal/state/owned views/handoff."
    },
    {
      "item": "P8.2",
      "path": "phases/P8/P8.2.md",
      "sha256": "18909044e5d3234480a4bae08e950c02562a0422dee37fca0c0f01b81f9034fb",
      "files_exact": "packages/modules/src/Extend/Api.php; packages/modules/src/Compiler/ApiOperationValidator.php; packages/modules/src/Compiler/ApiToolValidator.php; packages/modules/src/Compiler/RegistryCompiler.php; packages/modules/src/Compiler/CompiledRegistry.php; packages/modules/tests/Unit/ToolDeclarationTest.php; packages/modules/CHANGELOG.md; packages/modules/README.md; packages/api/src/Data/ToolDescriptor.php; packages/api/src/Data/JsonValue.php; packages/api/src/Data/JsonObject.php; packages/api/src/Data/SchemaHandle.php; packages/api/src/Data/SchemaValidation.php; packages/api/src/Contracts/ToolSchemaValidator.php; packages/api/src/Internal/OpisToolSchemaValidator.php; packages/api/src/Internal/ToolSchemaSupervisor.php; packages/api/src/Internal/ToolSchemaWorker.php; packages/api/tests/Unit/ToolSchemaTest.php; packages/api/composer.json; packages/api/phpstan.neon; packages/api/CHANGELOG.md; findings/P8-descriptors.md. Bookkeeping own journal/state/owned views/handoff."
    },
    {
      "item": "P8.3",
      "path": "phases/P8/P8.3.md",
      "sha256": "010e6166076f3cab8425d533ba06e8e067c50593c68607cebd32a5bc6f311b0e",
      "files_exact": "packages/api/src/Contracts/ToolCatalogue.php; packages/api/src/Contracts/ToolPolicy.php; packages/api/src/Data/ToolCatalogueView.php; packages/api/src/Internal/CompiledToolCatalogue.php; packages/api/src/Internal/DenyToolPolicy.php; packages/api/src/ToolServiceProvider.php; packages/api/tests/Feature/ToolCatalogueTest.php; packages/api/CHANGELOG.md; packages/api/README.md; findings/P8-catalogue-policy.md. Bookkeeping own journal/state/owned views/handoff."
    },
    {
      "item": "P8.4",
      "path": "phases/P8/P8.4.md",
      "sha256": "91967e0432eb5d921a5f3688e977aff29b7c9f65147c77357352a3f30ce65257",
      "files_exact": "packages/api/src/Contracts/ToolInvoker.php; packages/api/src/Contracts/ToolExecutionGuard.php; packages/api/src/Contracts/ToolRequestContext.php; packages/api/src/Data/ToolCall.php; packages/api/src/Data/ToolResult.php; packages/api/src/Internal/GuardedToolInvoker.php; packages/api/src/Internal/ReadOnlyToolExecutionGuard.php; packages/api/src/Internal/ToolRequestContext.php; packages/api/src/ToolServiceProvider.php; packages/api/tests/Feature/ToolInvocationTest.php; packages/api/tests/Feature/ToolGuardConcurrencyTest.php; packages/api/tests/Support/ToolGuardFixture.php; packages/api/CHANGELOG.md; packages/api/README.md; findings/P8-invocation.md. Bookkeeping own journal/state/owned views/handoff."
    },
    {
      "item": "P8.6",
      "path": "phases/P8/P8.6.md",
      "sha256": "57f66238ac62c3e3e38275d1dbec5bf17a5b4da4ce5c83a5fbc653a3bf067db7",
      "files_exact": "packages/api/docs/tool-integration.md; packages/api/tests/Feature/ToolContributionsTest.php; packages/api/tests/Support/ArchiveToolModule.php; packages/api/tests/Support/LabToolModule.php; delivery/tool-contribution-qualification-20260915/fixtures/**; delivery/tool-contribution-qualification-20260915/evidence/**; delivery/tool-contribution-qualification-20260915/receipt.md; findings/P8-contributions.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "tool-descriptor/1 + tool-schema/1 + tool-name/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

tool-descriptor/1 compiles explicit tuples in existing API slice, no ninth extender/compiler/HTTP endpoint/MCP transport. Tuple composerModule/code/toolVersion/hash; sideEffect read/draft/write/irreversible, idempotency read-only/domain-key/provider-key. Mutations require current consumer owner guard. Qualified canonical JSON preserves {} versus [], numeric-key objects and duplicate-key rejection; safe numeric integer bound ±9007199254740991, financial/large exact values strings. Mathematically integral1.0 accepted when representable. JsonObject/JsonValue are final composition wrappers; explicit lossless canonicalJson conversion, never assoc-array hash or final inheritance. tool-schema/1 validator uses source-qualified Draft2020-12 finite local resource closure/keyword profile with isolated validation-only supervisor limits; no remote $ref or handwritten validator. Optional Opis2.x immutable source/license/ref/lock/upstream suite required P8.1 before use. Invocation order current activation/discover policy, exact tuple/hash, input/schema, fresh invoke current record policy, owner guarded zero-arg Closure():ToolResult at most once inside current transaction/version boundary, handler, output validation, fresh final admission. Missing context/policy/mutation guard denies. Invalid local output rolls back same transaction; external committed+output_contract_failed or unknown cannot be claimed rolled back. clientProof is untrusted consumer-verified evidence; requestId correlation never operation key. ToolRequestContext additively extends accepted RequestContext for validated key/hash metadata only, legacy OperationHandler unchanged. Safe errors tool_unavailable,context_required,forbidden,input_invalid,schema_unavailable,schema_limit,catalogue_limit,tool_revision_conflict,approval_required,approval_invalid,idempotency_conflict,in_progress,unknown_effect,output_contract_failed,internal_error. ToolResult not-executed/committed/unknown, nullable structuredData only per outcome; replay rechecks current visibility, unknown keeps stable key/no blind retry. Explicit scoped ToolServiceProvider, no routes/migration/network/global actor.

Target `packages/api/src/Contracts/ToolCatalogue.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Contracts;
interface ToolCatalogue {
    public function discover(\CoreX\Api\Contracts\RequestContext $context): \CoreX\Api\Data\ToolCatalogueView;
}
```

Target `packages/api/src/Contracts/ToolInvoker.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Contracts;
interface ToolInvoker {
    public function invoke(\CoreX\Api\Contracts\RequestContext $context, \CoreX\Api\Data\ToolCall $call): \CoreX\Api\Data\ToolResult;
}
```

Target `packages/api/src/Contracts/ToolRequestContext.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Contracts;
interface ToolRequestContext extends \CoreX\Api\Contracts\RequestContext {
    public function operationKey(): ?string;
    public function toolContractHash(): string;
}
```

Target `packages/api/src/Contracts/ToolPolicy.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Contracts;
interface ToolPolicy {
    public function discover(\CoreX\Api\Data\ToolDescriptor $tool, \CoreX\Api\Contracts\RequestContext $context): bool;
    public function invoke(\CoreX\Api\Data\ToolDescriptor $tool, \CoreX\Api\Contracts\RequestContext $context, \CoreX\Api\Data\JsonObject $arguments): bool;
}
```

Target `packages/api/src/Contracts/ToolSchemaValidator.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Contracts;
interface ToolSchemaValidator {
    public function admit(\CoreX\Api\Data\JsonObject $schema, string $profile): \CoreX\Api\Data\SchemaHandle;
    public function validate(\CoreX\Api\Data\SchemaHandle $schema, \CoreX\Api\Data\JsonValue $value): \CoreX\Api\Data\SchemaValidation;
}
```

Target `packages/api/src/Contracts/ToolExecutionGuard.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Contracts;
interface ToolExecutionGuard {
    public function execute(\CoreX\Api\Data\ToolDescriptor $tool, \CoreX\Api\Contracts\RequestContext $context, \CoreX\Api\Data\ToolCall $call, \Closure $effect): \CoreX\Api\Data\ToolResult;
}
```

Target `packages/api/src/Data/JsonValue.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Data;
final readonly class JsonValue {
    public function __construct(
        public string $canonicalJson,
    ) {}
}
```

Target `packages/api/src/Data/JsonObject.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Data;
final readonly class JsonObject {
    public function __construct(
        public string $canonicalJson,
    ) {}
}
```

Target `packages/api/src/Data/ToolDescriptor.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Data;
final readonly class ToolDescriptor {
    /** @param array<string, null|bool|int|string|array> $availability */
    public function __construct(
        public string $module,
        public string $code,
        public string $toolVersion,
        public string $wireName,
        public string $title,
        public string $description,
        public string $ability,
        public string $sideEffect,
        public string $idempotency,
        public \CoreX\Api\Data\JsonObject $inputSchema,
        public \CoreX\Api\Data\JsonObject $outputSchema,
        public string $schemaProfile,
        public string $contractHash,
        public ?string $operationRef,
        public array $availability,
    ) {}
}
```

Target `packages/api/src/Data/ToolCatalogueView.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Data;
final readonly class ToolCatalogueView {
    /** @param list<ToolDescriptor> $descriptors */
    public function __construct(
        public string $revision,
        public array $descriptors,
    ) {}
}
```

Target `packages/api/src/Data/ToolCall.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Data;
final readonly class ToolCall {
    public function __construct(
        public string $code,
        public string $toolVersion,
        public string $expectedContractHash,
        public \CoreX\Api\Data\JsonObject $arguments,
        public string $requestId,
        public ?string $operationKey,
        public ?string $clientProof,
    ) {}
}
```

Target `packages/api/src/Data/ToolResult.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Data;
final readonly class ToolResult {
    public function __construct(
        public string $outcome,
        public ?\CoreX\Api\Data\JsonObject $structuredData,
        public ?string $errorCode,
        public ?string $operationKey,
        public bool $replayed,
    ) {}
}
```

Target `packages/api/src/Data/SchemaHandle.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Data;
final readonly class SchemaHandle {
    /** @param array<string,string> $localResources */
    public function __construct(
        public string $digest,
        public string $profile,
        public array $localResources,
    ) {}
}
```

Target `packages/api/src/Data/SchemaValidation.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Api\Data;
final readonly class SchemaValidation {
    /** @param list<json-map> $errors */
    public function __construct(
        public bool $valid,
        public array $errors,
    ) {}
}
```

Existing Api builder additive declaration bytes and guarded signatures, owning profile retained:

```text
- CoreX\Modules\Extend\Api::tool(string $code, string $handler, string $ability, array $inputSchema, array $outputSchema, string $sideEffect, string $idempotency, string $toolVersion = 'v1', array $availability = []): self. mode='tool'; private builder constructor retained, scalar/array properties only. availability contains consumer-owned opaque keys, no product edition vocabulary. sideEffect exactly read/draft/write/irreversible; idempotency exactly read-only/domain-key/provider-key. draft/write/irreversible require domain-key/provider-key. Permissions owner must be declared by owning manifest or explicit P2 bound existing owner; metadata capability flags are not permissions.
- Api::exposeTool(string $operationId, string $operationVersion, string $code, string $toolVersion, string $sideEffect, string $idempotency, array $argumentSchema, array $resultSchema, array $parameterMap = []): self. mode='tool', existing-operation reference exact same declaring module only. Explicit mapping binds validated argument fields to input/path parameters, no substitutions from authority fields, missing/extra mapping fail. No HTTP endpoint installed, methods/status remain in underlying REST catalogue. Headless tool handler still implements accepted Contracts\OperationHandler; wrapper calls owning Actions with explicit DTO and current authority.
- CoreX\Api\Data\ToolDescriptor final readonly: module, code, toolVersion, wireName, title, description, ability, sideEffect, idempotency, inputSchema, outputSchema, schemaProfile, contractHash, operationRef?, availability. Handler/absolute class refs exist internal only, never exposed catalogue JSON. Generic effects are owner-approved **server policy**, annotations derived only; no client override.
- Contracts\ToolCatalogue::discover(RequestContext $context): ToolCatalogueView; Contracts\ToolInvoker::invoke(RequestContext $context, ToolCall $call): ToolResult. ToolCall final readonly holds code, toolVersion, expectedContractHash, typed JSON object arguments, requestId and optional operationKey/clientProof. Trusted context passed separately, freshly resolved by application; proof and correlation remain untrusted evidence. Code/hash mandatory; adapter gets current tuple from catalogue before constructing call, no silent version selection.
- Contracts\ToolRequestContext extends accepted RequestContext, additive ::operationKey(): ?string and ::toolContractHash(): string only. Internal final wrapper is created inside guarded callback over the same verified current context; key is metadata validated and claimed by owner guard, never a permission. Explicit module OperationHandler wrapper reads this key to call its domain command; legacy handler signatures unchanged, correlation requestId never idempotency key. No opaque proof or invented approved actor leaked to handler.
- Contracts\ToolPolicy::discover(ToolDescriptor $tool, RequestContext $context): bool and ::invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool; default denies. It composes consumer activation/edition/per-tool availability and current capability/record rules, never new role engine. ModuleRegistry actual activation checked independently in catalogue/invoker.
- Contracts\ToolSchemaValidator::admit(JsonObject $schema, string $profile): SchemaHandle; ::validate(SchemaHandle $schema, JsonValue $value): SchemaValidation. Qualified existing library adapter, no handwritten general validator. Each SchemaHandle digest-bound to complete local resource closure; SchemaValidation bounded pointers/codes, no topology/raw arguments. Separate Internal\ToolSchemaSupervisor applies hard validation-only process/memory/deadline limits before returning an admitted handle/result; failed subprocess cannot fall back to unbounded in-process validation.
- Contracts\ToolExecutionGuard::execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult. Closure has exact zero-arg signature returning ToolResult; contains fresh policy+activation recheck, handler+output validation. Called **only** by invoker, at most once per local guarded attempt; default allows admitted reads only, all mutations denied. Consumer guard owns approval proof/claim/current-state/transaction/idempotency; it invokes callback while holding relevant local transaction/row/version boundary and checks authority again before commit. Invoker detects callback zero/multiple misuse; consumer implementation conformance is mandatory, a bool admitted before later effect is insufficient. Legitimate domain Actions retain direct non-tool entry; no claim that PHP internal provider is architecturally uncallable.
- CoreX\Modules\Contracts\RegistryIntrospection::extensionPoints(): RegistryMap; ::why(string $module, ?TenantContext $context): ModuleExplanation. Final DTOs in Modules\Data; implementation internal read-only over ModuleRegistry/compiled slices/current consumer activation adapter. No modules->api/corex sibling dependency. Local CLI corex:modules:why <composer-name> --json [explicit existing trusted account context option]; no request account/connection switch, no state changes. Transport registry.extension_points outside this package maps versioned read result after its own discover authorization.

ToolServiceProvider in corex/api explicitly installed separately, no auto-discovery/routes/migrations/network. Tool bindings transient or request/job scoped; no principal/context singleton. Existing ApiServiceProvider/Dispatcher/default envelopes/signatures/REST rules untouched. HTTP RequestContextProvider cannot magically work in headless invocation; exposed handler uses passed RequestContext or explicit module wrapper, never fabricated request. ToolExecutionGuard is a policy seam consuming existing owner approval/idempotency mechanics, not an AI confirmation ledger.
```

The complete schema/name/revision/invocation profiles and case inventory remain normative source `research/P8-tool-design.md` SHA256 `ded33e03fc260e632c06a4fec0e66e404cb29e6383e921e24c679ffadd8802ca`; finite JSON resources are code-owned local closure only.

## C-P2-029

### 2.29 — registry-introspection/1

```json
{
  "profile": "registry-introspection/1",
  "contract": "C-P2-029",
  "requirement_sources": {
    "R50": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B10 AC-1.a1, B10 AC-1.a2, B10 AC-1.a3, B10 AC-2.a1, B10 AC-3.a1, B10 AC-4.a1, B10 AC-4.a2, B10 AC-5.a1, B10 AC-5.a2, B10 AC-5.a3, B10 AC-5.a4, B10 AC-5.a5, B10 AC-5.a6, B10 AC-6.a1, B10 AC-6.a2, B10 AC-7.a1, B10 AC-7.a2, B10 AC-7.a3, B10 AC-7.a4, B10 AC-8.a1, B10 AC-8.a2, B10 AC-8.a3, B10 AC-9.a1, B10 AC-9.a2, B10 AC-9.a3, B10 AC-10.a1, B10 AC-10.a2, B10 AC-10.a3, B10 AC-10.a4, B10 AC-10.a5, B10 AC-11.a1, B10 AC-11.a2, B10 AC-12.a1, B10 AC-12.a2, B10 AC-13.a1, B10 AC-14.a1, B10 AC-14.a2, B10 AC-15.a1, B10 AC-15.a2, B10 AC-16.a1, B10 AC-16.a2, B10 AC-17.a1, B10 AC-17.a2, B10 AC-18.a1, B10 AC-18.a2, B10 AC-18.a3, B10 AC-19.a1, B10 AC-19.a2, B10 AC-20.a1, B10 AC-20.a2, B10 AC-21.a1, B10 AC-21.a2, B10 AC-22.a1, B10 AC-22.a2, B10 AC-23.a1, B10 AC-23.a2, B10 AC-23.a3, B10 AC-24.a1, B10 AC-24.a2, B10 AC-24.a3, B10 AC-24.a4, B10 AC-25.a1, B10 AC-25.a2, B10 AC-33.a1, B10 AC-33.a2; non-AC reusable/programme binding: brief/sources/b38.md §6; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 6fb4412f3e8a1c682b90eef7f4ecb4b25acd879989cc7c1404160525195947e3",
      "destination": "P8 qualification / P9"
    }
  },
  "owning_design": {
    "path": "research/P8-tool-design.md",
    "sha256": "ded33e03fc260e632c06a4fec0e66e404cb29e6383e921e24c679ffadd8802ca"
  },
  "files": [
    "packages/modules/src/Contracts/RegistryIntrospection.php",
    "packages/modules/src/Data/RegistryMap.php",
    "packages/modules/src/Data/ModuleExplanation.php"
  ],
  "indexed_files": [
    {
      "item": "P8.5",
      "path": "phases/P8/P8.5.md",
      "sha256": "5cbc88adbaf7d49f5a516d4c9214673b9123daa7789fe6ed5925c09c4056eec2",
      "files_exact": "packages/modules/src/Contracts/RegistryIntrospection.php; packages/modules/src/Data/RegistryMap.php; packages/modules/src/Data/ModuleExplanation.php; packages/modules/src/Internal/ReadRegistryIntrospection.php; packages/modules/src/Commands/WhyCommand.php; packages/modules/src/ModulesServiceProvider.php; packages/modules/tests/Feature/RegistryIntrospectionTest.php; packages/modules/README.md; packages/modules/CHANGELOG.md; findings/P8-introspection.md. Bookkeeping own journal/state/owned views/handoff."
    }
  ],
  "old": "existing public ports/payloads unchanged; candidate addition not yet registered",
  "new": "registry-introspection/1",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "proposal:G-API",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

registry-map/1 reads exactly eight existing extenders, source ownership and current compiled revision; no new registry/private capability engine. why uses bounded composer module name; null context means static declaration verdict hasTenantVerdict=false. Optional current trusted TenantContext resolves read-only activation, never payload database switch. Status active/inactive/unknown/invalid-declaration, bounded stable reasons, no topology/SQL. Missing/corrupt input throws RegistryInputUnavailable, distinct from valid empty map; unknown extender InvalidRegistryPoint, stale revision refuses. declaredConstraints are metadata, not installed compatibility passport; P9 actual immutable cohort proof remains separate. No Modules→Api dependency or generator.

Target `packages/modules/src/Contracts/RegistryIntrospection.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Modules\Contracts;
interface RegistryIntrospection {
    public function extensionPoints(): \CoreX\Modules\Data\RegistryMap;
    public function why(string $module, ?\CoreX\Tenancy\TenantContext $context): \CoreX\Modules\Data\ModuleExplanation;
}
```

Target `packages/modules/src/Data/RegistryMap.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Modules\Data;
final readonly class RegistryMap {
    /** @param list<json-map> $extensionPoints */
    public function __construct(
        public string $schemaVersion,
        public string $revision,
        public array $extensionPoints,
    ) {}
}
```

Target `packages/modules/src/Data/ModuleExplanation.php` — exact declaration manifest (invariants above are mandatory, bodies below do not implement them):

```php
declare(strict_types=1);
namespace CoreX\Modules\Data;
final readonly class ModuleExplanation {
    /** @param array<string, null|bool|int|string|array> $declaredConstraints | @param list<string> $reasons */
    public function __construct(
        public string $schemaVersion,
        public string $module,
        public string $status,
        public array $declaredConstraints,
        public array $reasons,
        public bool $hasTenantVerdict,
    ) {}
}
```

## C-P2-030

### 2.30 — cohort-qualification/keep

```json
{
  "profile": "cohort-qualification/keep",
  "contract": "C-P2-030",
  "requirement_sources": {
    "R45": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "non-AC reusable/programme binding: brief/sources/flex-p6.md P6.5; source SHA256 66bd8384e002f0ab3b3343e91f2120e6419e2de71458c2dba0827a5a40ad94e8",
      "destination": "P9"
    },
    "R47": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "non-AC reusable/programme binding: brief/sources/flex-p6.md P6.5; source SHA256 66bd8384e002f0ab3b3343e91f2120e6419e2de71458c2dba0827a5a40ad94e8",
      "destination": "P11"
    },
    "R52": {
      "full_map_sha256": "682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9",
      "full_ledger_sha256": "ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87",
      "frozen_source": "B10 AC-31.a1, B10 AC-32.a1, B11 AC-28.a2, B11 AC-29.a1, B11 AC-29.a2, B11 AC-30.a1, B11 AC-32.a1, B11 AC-32.a2, B11 AC-32.a3, B11 AC-32.a4, B34 AC-7.a1, B34 AC-7.a2, B34 AC-7.a3, B34 AC-7.a4, B35 AC-1.a2, B35 AC-11.a1; non-AC reusable/programme binding: brief/sources/b38.md §4.1; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 89cc87c056131d6c2b94705c65d359c654a473a57507a45cd7e16aa3bc56e4f2 · brief/sources/b38.md §7; source SHA256 ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4; section SHA256 f9cba28861b728a7b4a83d80c9fc1dcac587d84246d4f09093b3ca061af5ae9e",
      "destination": "P1–P11 invariant / P9 / P10 / P11"
    }
  },
  "owning_design": {
    "path": "research/05-integrated-design.md",
    "sha256": "6aca22518b510e17d108e0878baaa512b152596b56d75b185adc73494c117e9e"
  },
  "files": [],
  "indexed_files": [],
  "old": "byte-identical existing public ports/payloads; no new registrations",
  "new": "none",
  "upstream_dependency_change": "none adopted; finite source-qualification obligation in normative profile below",
  "schema_old": "none for new neutral tables; existing domain/identity/credential schemas preserved",
  "adoption": "no-delta",
  "public_contract_change_policy": "any change to an existing Contracts interface requires major owner receipt; internal additions do not grant authority",
  "runtime_or_live_proof": false
}
```

Typed PHP declarations below are proposal manifests, not constructor implementations: each promoted property is normative and every listed invariant MUST be enforced by its owning final implementation before adoption. Interfaces only Contracts; typed data/exception/enum classes outside Contracts. All PHP files strict_types=1, final readonly data with constructor promotion; Closure/resource handles are trusted ephemeral runtime values, never deserialized. No client array/JSON may contain a connection, SQL, FQN, actor capability, authority default or arbitrary path. Missing constructor field and unknown wire key reject; nullable means explicit null only, empty differs from missing/null. Strings UTF8, opaque keys 1..128 unless specified, all digests lowercase SHA256; lists preserve list shape/order with duplicates rejected where set semantics apply; maps preserve object shape including empty/numeric keys through qualified lossless codec. General json-map payload bound 64KiB/depth16/256 keys unless narrower owning profile, fields code/schema allowlisted; no resources/objects/callbacks/unsafe numeric conversion. Domain exact integers/decimals transmitted canonical strings; tool numeric profile separately permits safely representable JSON numbers. UTC DateTimeImmutable validated UTC +00:00 and strict wire parser, canonical DATE validated Gregorian yyyy-mm-dd (no overflow normalization). Context/policy current trusted ingress and current authority rechecked after locks/at serialization, audit actor metadata never grants. No automatic boot migration/provider secret lookup/network. Required absent schema/provider/policy returns explicit unavailable/unsupported/not-ready/denied, never empty success. Version /1 immutable profile: old installed operations stay identical; additive candidate needs exact G-API before registration. Any existing public contract change requires accepted major route; reject unsupported versions, no silent renegotiation. Down migration refuses ANY populated own table or possibly-live/unknown operation; quiesce+approved export/restore/retention receipt before destructive migration, rollback never lowers external monotone witness/counter. Runtime source-selected package/engine/library ABI adoption remains owning qualification+exact receipts, not an internal unknown outward type.

cohort-qualification/keep changes no runtime/release/effective receipt programme. Actual same released commit/lock/source/license/dist/hosted/runtime/schema/FK/1000switch/two independent non-CRM receipts remain owning P9–P11 obligations. Existing auth manifest Laravel12 does not prove13. Strict major/public split/MIT/no private root/agnostic architecture gates retained. Each P1 parent/atom/external delimiter/gap resumes original destination; DI34/AP13 unfilled stays unfilled. No port/table/dependency/template/preset added by this keep row.

