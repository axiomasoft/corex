# P9 — Immutable released cohort candidate

RAG:— This document freezes release assurance only. Runtime packages/interfaces/DDL/engines remain owned by their P2–P8 producers. D14 is a design decision, not a publication, private-read, runner or schema grant. All report/case names below are future contracts: no test, split, ZIP, hosted job or released receipt has been produced during design.

## Cohort identity and transformations

`p9-cohort/1` has schema_version, cohort_id, frozen R/AC membership, package_source (repository/full commit), recipe (repository/full commit/tool hashes), package rows, upstream rows, profile cells and baseline rows. `cohort_id` is SHA256 of canonical UTF-8 JSON identity payload (sorted object keys, deterministic array order, no floats). No mutable journal/handoff/run URL is part of identity. Packages use a version vector, not an unproved identical version number. Every included package: name/path/source_commit/subtree_tree/composer_sha256/license/export-policy hash, transformed release file map with SHA256+Git mode, split repository/visibility/commit/tree/tag+tag-object, version and exact in-cohort requires, dist reference/endpoint/asset SHA256+Composer checksum, accepted P2 contract/schema/gate hashes. Upstreams have independent immutable refs/license/driver ABI; matching published providers reused without writes. Version choices are resolved from actual adopted P2+G-API/major decisions before P9.1 freeze; no invented concrete version or latest/dev substitute.

Do not equate original subtree tree and transformed split tree: Composer metadata version/dependency pins, removal of local repositories and injection of root LICENSE are declared byte transformations. All other included blob bytes and relevant modes must match. A stable release tree → split root tree equality is proved independently. source.reference is the split commit for VCS metadata; a monorepo SHA that does not exist in the split repository is invalid. Dist bytes have independent references/digests, ZIP checksum never inferred from Git object hash. Filtered dev/test fixture recipe remains separately hash-qualified; source package bytes may stay fixed while P9 tools/workflows are committed later, but both source and recipe commits are explicitly captured and tested. Bookkeeping-only commits do not pretend to be product source. Any changed package/export/recipe/profile/baseline bytes invalidate affected evidence and packet; no self-hashing receipt input.

ZIP implementation: extend existing deterministic exporter using fixed timestamps, sorted paths, fixed compression/tool version, explicit modes and no extra fields; build twice in independent empty staging roots. No raw `git archive <tree>` timestamp reproducibility assumption. Public delivery uses the verified uploaded assets; auto-generated host source archives get separate content/digest checks and are never promised byte-identical to uploaded ZIP. No host paths, .env/auth.json, private metadata or undeclared files. Add UPGRADE.md/include resources where package actually supplies them; no missing required files hidden by export filter.

## Exact package and write boundary

Candidate maximum public runtime set (only actual adopted included rows): corex/core, corex/modules, corex/api, corex/tasks, corex/tenancy, corex/panels, corex/media, corex/notifications, corex/wakeup, corex/workflow, corex/attributes, corex/import-export, corex/search, corex/search-meilisearch, corex/recurrence, corex/tasks-workflow, corex/intl, corex/intl-search. corex/auth is a separately selected private-source integration profile. Source paths are packages/<declared-name>. No flex/crm release or product code change belongs P9. CoreX public graph excludes required corex/auth/AzGuard/flex packages; optional auth profile opts in explicitly in the consumer root. Existing standalone engine/provider/SDK refs remain externally owned, not cloned/released by this phase. Not every candidate package must be invented: accepted equivalent/KEEP disposition retains the actual qualified producer package and version.

P9.1 freezes exact row membership+baseline+files. P9.3 prepares publication/runner helpers and immutable runner-plan/1; P9.4 prepares installed/private tools/fixtures and final runner-packet/1. All publication/private/runner tools and guard tests exist before any protected invocation. P9.7 seals exact publication operations using unchanged tested helpers, no post-hosted helper edits. P9.2 may edit only listed composer.json/CHANGELOG.md/UPGRADE.md metadata and declared export scripts. No package src/Contracts/config/migrations mutation; runtime issue returns its owning item, bounded repair under protocol rather than silently extending release allowlist. Existing export-policy.json, pristine-proof.py, private-auth-proof.py and publish_exact.py carry historical refs/authorizations: preserve them and their receipts; create current recipe/entry points using reusable export/publication guard, without invoking old publisher. Public metadata goes in delivery/remaining-cohort-20260915/public/**; private proof in delivery/remaining-cohort-20260915/private/**, never uploaded with public assets. No credential values in logs or manifests.

## Compatibility and evidence policy

Policy A: core/modules/tasks/api PHP8.3–8.5 × Laravel12–13 (boxed where physical driver not installed); tenancy PHP8.4–8.5 × Laravel12–13 + PG16; auth declared PHP^8.3 × Laravel12 only, installed effective floor further intersects AzGuard and actual dev tools. New selected package/engine/SDK profiles derive actual P2 constraints; no inheritance of sibling support. Product target PHP8.5/Laravel13 is a separate requirement: if its optional auth/engine is unsupported, exact owning delta/receipt must be qualified, never mark it supported or remove required R. Declared range is not proof of all patch versions. Enumerate named cells by package/profile/PHP minor/framework major/SQLite or PG16/required extensions/minimum or current dependency solution. Every permitted target cell has actual local+hosted+released-installed evidence; future versions require qualification. Local baseline receipts apply only their exact source/runtime.

`composer update --prefer-lowest --prefer-stable --prefer-dist` and separate `composer update --prefer-stable --prefer-dist` resolve accepted test cells; retain locks, installed metadata and real platform versions. `composer install --prefer-dist` from lock with clean vendor/home/cache verifies exact closure; separate no-dev boot `composer install --no-dev --prefer-dist` and `composer check-platform-reqs --no-dev`. No --ignore-platform-reqs, fake config.platform or minimum-stability inflation to make a cell pass. Actual Pest/Testbench minimums may exceed runtime minimum; qualify a separate compatible frozen harness if needed, no weakening behavior/coverage floor. No global library-lock requirement: fixture/application locks own resolver evidence. Root path repository is development only, installed proof has no symlinks/path/file delivery and no private credentials in public environment.

Hosted receipt includes source SHA and observed checked-out SHA, recipe/workflow SHA/hash, event/ref/runId/runAttempt/jobId/URL, matrix cell, exact PHP/framework/extensions/Composer/OS/container/PG/client versions, root manifest/lock/vendor/config/fixtures hash, commands/cwd/start/end/exit/raw log URI+SHA256 and covered cases. Merge-ref/branch drift must prove exact tested package trees or rerun. Every required job including new package suites/coverage/architecture/mutation/distribution succeeds; conditional/skipped/cancelled/expired artifacts are not success. Workflow files and local output alone never satisfy G-RUNNER. Actions pinned full immutable refs; default permissions read-only, scoped runner dispatch/upload branch permission finite, auth job isolated. No automatic change of repository settings, branch protections or immutable-release policy.

## Installed upgrade and refusal

Independent lab/equipment/measurement app owns neutral entities and explicit policies; no CRM/CMS/Store assumptions. Test boxed no rootDB/tenancy/private provider and physical A/B _test PostgreSQL16 DBs; installed optional providers cannot read absent secrets. Verify accepted selected P3–P8 conformance from vendor, provider discovery/config/route/module caches/queue startup and current API/tool/domain checks; tests/fake external drivers remain local proof, no production claims.

Populated upgrade starts from actual accepted immutable released baseline+lock+schema for each previously released selected package and separate first-install for new packages. Baseline rows cannot use E2/E3 local candidate as released receipt. Test existing IDs/morphs/money/data/soft-delete/settings/audit, preserved rows/constraints/permissions, migration discovery/durable progress, migration fault/retry and unsupported runtime/schema/privilege refusal before mutation. `migrate --pretend` is supplementary SQL evidence, not permission/preflight or proof that arbitrary PHP has no side effects. Restore backup into another disposable DB and verify domain/constraints; ordinary pg_dump/restore excludes cluster globals unless explicitly saved and does not prove synchronized A/B snapshots, distributed witness nonrollback, PITR/vendor/provider/fleet recovery. No migrate:fresh/reset/down on populated path; rollback only actually reversible approved operations, otherwise documented refused rollback and tested forward reconciliation. Generic release verifier may preflight known supported predicates; no new migration engine or production DDL.

## Optional auth and protected operations

R51 preserves A09: corex/auth is an adapter; AzGuard and application roles/permission compilation own independent domains. Public install+boot+adapter-missing safe denial is verified independently; private profile adds exact authorized source/license/ref/lock/vendor and real package positive/negative tenant/department/current authority tests. No fake role engine, auth declaration inferred from filenames or source exposure to public channel. PRIVATE proofs are required for selected R51 profile and cannot be replaced by public-only PASS. If owner actually removes that profile, an explicit accepted scope decision is needed; cannot self-skip P9.6 or erase R51 for GREEN. Auth L13 remains explicit refusal unless owning external source and BC/dependency grants supply proven support.

Three finite protected items: P9.5 G-RUNNER (candidate ref upload/dispatch only exact final P9.4 runner packet on axiomasoft/corex); P9.6 G-PRIVATE (exact private channel read/install, no private write/public upload); P9.8 G-RELEASE (exact publication operations prepared P9.7). Each Authority uses existing owner-bound reducer and digest-qualified operation rows. Read-only preflight and packet preparation are routine; grants only after concrete reviewable packet. Historical owner/token/remote access is not authority, and grant revocation/stale fingerprint checked at actual write boundary. Missing grants create exact resume predicate, not fake acceptance. This design requests no permissions and starts none of these operations.

## Publication reconciliation

Use `p9-publication-packet/1`: candidate digest, all proof inputs, finite Operations (ID/repository/visibility/kind/ref/expected previous state/target object or asset SHA256/credential ref+scope/grant receipt/verification query), allowed dependency order and per-operation durable state. Never publish private source/artifacts in public metadata. Each public package split → immutable version/tag → verified uploaded asset → observed Composer metadata/accepted delivery channel. Root consumer explicitly declares non-Packagist public/private repositories; dependent repositories are not inherited. Packagist is not required unless actual channel chosen requires it, and availability never inferred from GitHub release alone.

Use existing publish_guard semantics; compare remote object/asset/download bytes before write, already matching no-op, conflicting identity refuses. Persist intended operation before side effect; after timeout/unknown query actual state, do not automatically retry. Draft+all assets→publish when supported; GitHub immutable-release capability verified and used if already admitted, no invented signing/PKI/SLSA assurance level. Whole cohort is not atomic across repos/indexes: ledger may record partial externally visible state, phase remains pending until full reconciliation. Wrong published immutable bytes need new explicitly approved version; never force/move/delete old tag or overwrite asset. Already current upstream/source results verified and reused; old APPROVED-SPECS E5 ledger unchanged. A prepared packet/NO-GO is not P9 GREEN.

## Receipt schemas and freeze inputs

Each branch report `p9-cohort-receipts/1` has Cases (Case/R/AC/Expected/Actual evidence/Result/Owner/Resume), Inputs (Path/SHA256/Role), Evidence (ID/Path/SHA256/Kind/Cohort/Cell/Command/Cwd/Runtime/Start/End/Exit/Result), Adoption (Surface/Gate/Exact digest/Owner/Result/Resume). Actual evidence cells cite `evidence:<ID>` with retained digest-qualified logs/assertions; filenames/digests without verified body are not execution proof. Evidence artifacts contain source/recipe/candidate/platform/lock/config/fixture/case IDs and expected/actual outputs; structural checker checks carriers, executor verifies semantics. Cases mandatory PASS except explicit negative/refusal cases whose expected denial is actually observed PASS. Pending required real proof does not count PASS. Inputs cannot self-hash own report or mutable journal/view. Gates accepted/no-delta as actually qualified; effective DI/AP and real provider/performance residuals copied to P10/P11 with exact owner/resume, not globally green.

`p9-released-cohort/1` final Findings: Requirements(R/Packages/Source/Test/Hosted/Release/Installed/Gates/Residual owner), Producer edges, Sealed inputs and Released cohort/candidate pass. Public and private receipts separate with no source/token leakage. P10/P11 consume findings/P9-cohort-receipt.md + findings/P9-compatibility-passport.md + delivery/remaining-cohort-20260915/public/released-cohort.json and own approved private envelope if selected. No live deployed/consumer acceptance is asserted by P9. Include all stable current P9 inputs in whole design-finish seal including this candidate, D14 and checker if executable logic depends on it; no future missing producer fabricated to fill finish.

## R / AC ownership

| R | Producer | Cases / obligations | Consumer / gate |
|:--|:--|:--|:--|
| R45 | P9.1–P9.5/P9.7–P9.9 | Immutable whole selected release vector/local+hosted+release+upgrade+independent installed, all applicable upstreams | P10/P11; G-API/G-DEPENDENCY/G-RUNNER/G-RELEASE |
| R51 | P9.1/P9.4/P9.6/P9.9 | A09 public-no-private + real optional private direct/transitive auth profile; B11 originals retained | P2 ownership/P10/P11; G-PRIVATE and owning policy/engine |

## Required case IDs

| Report | Required Case IDs |
|:--|:--|
| findings/P9-intake.md | INTAKE-01; INTAKE-02; INTAKE-03; INTAKE-04; INTAKE-05; INTAKE-06 |
| findings/P9-artifacts.md | DIST-01; DIST-02; DIST-03; DIST-04; DIST-05; DIST-06; DIST-07; DIST-08 |
| findings/P9-local-matrix.md | MATRIX-01; MATRIX-02; MATRIX-03; MATRIX-04; MATRIX-05; MATRIX-06; MATRIX-07 |
| findings/P9-installed-candidate.md | INSTALL-01; INSTALL-02; INSTALL-03; INSTALL-04; INSTALL-05; INSTALL-06; INSTALL-07; INSTALL-08; INSTALL-09 |
| findings/P9-hosted-runs.md | RUN-01; RUN-02; RUN-03; RUN-04; RUN-05; RUN-06; RUN-07 |
| findings/P9-private-auth.md | PRIVATE-01; PRIVATE-02; PRIVATE-03; PRIVATE-04; PRIVATE-05; PRIVATE-06 |
| findings/P9-publication-packet.md | PACKET-01; PACKET-02; PACKET-03; PACKET-04; PACKET-05; PACKET-06 |
| findings/P9-publication.md | PUB-01; PUB-02; PUB-03; PUB-04; PUB-05; PUB-06; PUB-07; PUB-08 |
| findings/P9-released-install.md | RELEASE-01; RELEASE-02; RELEASE-03; RELEASE-04; RELEASE-05; RELEASE-06; RELEASE-07 |

## Case invariants

| Case | Required observable result |
|:--|:--|
| INTAKE-01 | Every included R has accepted P1 source/equivalent and P2 proposal/no-delta binding; missing row refuses freeze, not silently excluded. |
| INTAKE-02 | Six selected P3–P8 seals are digest-current with all included actual case/gate/installed receipts; old E2/E3/E5 never substitute. |
| INTAKE-03 | Runtime dependency transitive closure has immutable per-package versions/refs/licenses; optional engine/SDK needs independent source, no @dev/latest aliases in released graph. |
| INTAKE-04 | Public and private profile membership explicit; public closure excludes auth/AzGuard/product layer and needs no private credentials. |
| INTAKE-05 | Per-package BC/major/CHANGELOG/DDL mapping matches actual adopted P2 delta; unresolved scope refuses release. |
| INTAKE-06 | Baseline released version/lock/schema and every target profile selected; new package has explicit first-install status, unsupported combinations retained with reason/owner. |
| DIST-01 | Independent builds from committed source and frozen recipe yield identical uploaded ZIP SHA256 per package, file order/time/modes/extra fields fixed. |
| DIST-02 | Source subtree → filtered/rewritten release tree → local split commit verified by exact path/content/mode map; transformations only composer pins/version/root LICENSE/allowlist. |
| DIST-03 | Composer source.reference resolves to split commit, dist.reference and per-channel digest independently bound; monorepo SHA is not falsely used as split ref. |
| DIST-04 | Public runtime/dev metadata no host path/file URLs, repositories in dependencies not assumed recursive; packages do not import flex or private auth. |
| DIST-05 | Untracked files, tests/vendor/.env/auth.json/private keys, traversal/absolute paths/duplicate entries/symlinks are rejected or explicitly forbidden by export policy. |
| DIST-06 | Exact required config/migrations/resources/providers/LICENSE/README/CHANGELOG/UPGRADE retained; export omission injects RED. |
| DIST-07 | Collision/wrong version/changed tree or checksum refuses without overwriting staging or modifying old export policy/receipt. |
| DIST-08 | Provider/engine/SDK archives separately source/license/checksum qualified; already matching immutable upstream reused, not republished. |
| MATRIX-01 | All target cells generated from exact PHP/framework/extensions/package/profile intersection; real resolved Testbench/Pest minimum constraints included. |
| MATRIX-02 | Minimum and current dependency solutions per allowed cell retained with distinct locks; incompatible toolchain not bypassed, requires qualified separate harness. |
| MATRIX-03 | Locked cold reinstall/check-platform-reqs uses actual PHP/extensions; no config.platform/ignore-platform-reqs fake supported evidence. |
| MATRIX-04 | D34 root SQLite+PG16, affected package suites/style/analysis/architecture/contract/anti-weakening and current coverage floors all executed at exact candidate/recipe. |
| MATRIX-05 | Actual source changes require D98 all current mutation cases; provenance-only no-src delta records applicability, cannot replace prior producer code receipts. |
| MATRIX-06 | Every new selected package has actual CI job/test aggregator/negative layer/literal scan; no vacuum scan or missing package suite. |
| MATRIX-07 | Failures/missing cells retain FAIL/PENDING/UNSUPPORTED; narrower support requires actual owner BC approval, never omit cell for GREEN. |
| INSTALL-01 | Independent lab/equipment Laravel consumer in empty home/cache/vendor with no path repository/symlink installs exact candidate dist and runtime check-platform-reqs. |
| INSTALL-02 | Separate update → lock capture → empty cache/vendor install produces matching package file maps/autoload/providers; no-dev runtime proof separate dev suite. |
| INSTALL-03 | Boxed boot without root DB/tenancy/private auth; physical PG16 A/B DBs and current context switch/authorization isolation independently checked. |
| INSTALL-04 | Config/route/module cache, migration discovery/optional driver/queue startup and accepted API/tool dispatch tested from installed vendor; unavailable feature explicit deny. |
| INSTALL-05 | Populated released baseline → scoped composer update → accepted migration route preserves accepted IDs/morphs/minor-units/soft-delete/settings/audit and domain data. |
| INSTALL-06 | Unsupported baseline/schema/dependency/runtime/insufficient privilege preflight refuses before DDL/domain writes; before/after hashes and schema prove no mutation. |
| INSTALL-07 | Fault after first migration/DDL is reconciled using actual migration table+schema/data; bounded retry/forward recovery only, no blind down/reset or assumed total SQL rollback. |
| INSTALL-08 | pg_dump custom backup actually restored to another _test DB with schema/data/constraints checked; ordinary restore never claims external nonrollback identity witness or provider recovery. |
| INSTALL-09 | Each included selected branch conformance rerun on dist with real supported local driver; fake external services do not prove deployed/vendor/performance acceptance. |
| RUN-01 | Hosted runs bind source/artifact/recipe/workflow SHA, exact event/ref/runId/attempt/jobId/URLs, actual platform/image and locks, no local receipt substitution. |
| RUN-02 | Every mandatory matrix/test/coverage/mutation/installed-distribution job observed success; skipped/cancelled/conditional miss not green. |
| RUN-03 | Pull-request merge SHA or current branch differs from frozen source: verify exact exported package maps and recipe, or rerun matching candidate; badge insufficient. |
| RUN-04 | Workflow/action refs pinned immutable and permissions least privilege; private job separated/no credential propagation to public jobs or untrusted checkout. |
| RUN-05 | Raw hosted logs/results/locks/artifact hashes retained and independently verified against candidate; expired artifact without retained proof cannot pass. |
| RUN-06 | Rerun partial/unknown dispatch queried by exact candidate/run identity before any repeat; run attempt not conflated with earlier successful job. |
| RUN-07 | Missing runner capability/grant yields exact source/job/resume evidence; packet only never counts hosted acceptance. |
| PRIVATE-01 | Exact accepted G-PRIVATE source/channel/ref/license/checksum and narrow read scope verified before private fetch; no historical blanket D14 authority. |
| PRIVATE-02 | Cold direct corex/auth install plus transitive example consumer install from approved private channel, separate homes/caches, lock+vendor ref verified. |
| PRIVATE-03 | Real installed auth suite/default-deny missing grant/unknown resource and current tenant/department scope positive and negative evidence; no role engine duplicate. |
| PRIVATE-04 | Explicit adapter-missing host public boot denies protected abilities safely; public no-credential graph independently remains usable. |
| PRIVATE-05 | Laravel12 approved PHP intersection tested; Laravel13 refused unless independent engine owner source/compatibility+G-API/G-DEPENDENCY approval actually supplied. |
| PRIVATE-06 | Private source/token material isolated and redacted; receipts only hashes/secret refs, no upload of private dist/source or private metadata to public registry. |
| PACKET-01 | Exact candidate digest and per-package/source/split/tag/dist/channel file set sealed after all local/install/hosted/private inputs, old publication excluded. |
| PACKET-02 | Finite operation matrix enumerates repository visibility, immutable ref/object/asset/digest, expected old state, dependency ordering and credential scope; no repo admin/force/delete extras. |
| PACKET-03 | BC/major/changelog/profile/DDL/private/source licenses approved for actual bytes; fixture schema approval does not grant production DDL. |
| PACKET-04 | Draft/tag/assets/index plan differentiates partial states and query-before-retry; independent lookup/download/reconcile command for every effect. |
| PACKET-05 | G-RELEASE request is digest-bound to full finite operations and fresh grants; preparer cannot mint consent, no publication in this item. |
| PACKET-06 | P10/P11 same-cohort handoff includes full R/AC residual DI/AP/real provider/timing/consumer owners; NO-GO is not phase GREEN. |
| PUB-01 | At write boundary current accepted grant/operation/artifact/source/credential scope exactly match sealed packet; stale/revoked/wrong repository denied. |
| PUB-02 | Already matching immutable refs/assets reused as verified no-op with receipt; historical four-package publication never rerun. |
| PUB-03 | Local verified split refs pushed only exact admitted objects, tag points to expected split commit/tree; no force/move/delete. |
| PUB-04 | Exact dist assets staged draft then publication via approved channel; immutable-release availability observed, not assumed or enabled through ungranted admin action. |
| PUB-05 | Composer package metadata/index observed exact names/versions/source/dist refs and dependency closure in consumer-owned root repository list. |
| PUB-06 | Timeout/unknown upload/push/release queries remote state+downloads actual bytes before retry; unresolved unknown prevents completion and blind repeat. |
| PUB-07 | Version collision/different bytes fails closed; recovery creates separately approved new version, never silently replaces published identity. |
| PUB-08 | Every full cohort operation reconciled with actual public/approved private channel receipts; partial public result retains ledger without claiming release complete. |
| RELEASE-01 | New independent consumer retrieves release bytes from declared real delivery endpoint with empty caches; candidate file URLs/path/vendor cannot substitute. |
| RELEASE-02 | Cold composer update and separate lock install verify whole released vector/upstreams/source refs/dist hashes/vendor path maps; all target runtime/profile cells covered. |
| RELEASE-03 | Released non-CRM boxed/physical and each included contribution behavioral conformance repeated; same auth private/public separation retained. |
| RELEASE-04 | Released populated-baseline upgrade/refusal/recovery checks use unchanged accepted fixture/recipe; previous candidate receipt reused only for identical content semantics with explicit binding. |
| RELEASE-05 | Compatibility passport distinguishes declared/locally executed/hosted/released installed and explicit exclusions; static report not speculative compatibility runtime generator. |
| RELEASE-06 | Exact source/test/hosted/release/installed pins and accountable acceptance bound to every included R; G-API/G-DEPENDENCY/G-PRIVATE/G-RUNNER/G-RELEASE satisfied. |
| RELEASE-07 | Final released-cohort seal producer edges P10/P11 retain every DI/AP/provider/timing/product owner; release not live deployment or aggregate consumer acceptance. |

## Parent AC ownership — originals retained

| Parent | Generic owner | Generic slice / cases | Full original residual owner |
|:--|:--|:--|:--|
| B11 AC-1 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-2 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-3 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-4 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-5 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-6 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-7 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-8 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-9 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-10 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-11 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-12 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-13 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-14 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-15 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-16 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-17 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-18 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-19 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-20 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-21 | P9.6 real auth adapter/P9.9 released bind | PRIVATE-02/03/05; consumer policies retained, no role writer | AzGuard owner + FlexCRM policy/schema owner full role/compiler/scope/lifecycle criteria |
| B11 AC-22 | P9.6 real auth adapter/P9.9 released bind | PRIVATE-02/03/05; consumer policies retained, no role writer | AzGuard owner + FlexCRM policy/schema owner full role/compiler/scope/lifecycle criteria |
| B11 AC-23 | P9.6 real auth adapter/P9.9 released bind | PRIVATE-02/03/05; consumer policies retained, no role writer | AzGuard owner + FlexCRM policy/schema owner full role/compiler/scope/lifecycle criteria |
| B11 AC-24 | P9.6 real auth adapter/P9.9 released bind | PRIVATE-02/03/05; consumer policies retained, no role writer | AzGuard owner + FlexCRM policy/schema owner full role/compiler/scope/lifecycle criteria |
| B11 AC-25 | P9.6 real auth adapter/P9.9 released bind | PRIVATE-02/03/05; consumer policies retained, no role writer | AzGuard owner + FlexCRM policy/schema owner full role/compiler/scope/lifecycle criteria |
| B11 AC-26 | P9.6 real auth adapter/P9.9 released bind | PRIVATE-02/03/05; consumer policies retained, no role writer | AzGuard owner + FlexCRM policy/schema owner full role/compiler/scope/lifecycle criteria |
| B11 AC-27 | P9.6 real auth adapter/P9.9 released bind | PRIVATE-02/03/05; consumer policies retained, no role writer | AzGuard owner + FlexCRM policy/schema owner full role/compiler/scope/lifecycle criteria |
| B11 AC-28 | P9.4/P9.6/P9.9 | INSTALL-03/04; PRIVATE-04; RELEASE-03 public boxed boot/auth profile | FlexCMS/FlexCRM application owner full composition and boxed feature acceptance |
| B11 AC-29 | P3/P9.3/P9.4/P9.9 | MATRIX-06; INSTALL-03/09; RELEASE-03 generic installed isolation | Consumer app/auth/ops full 1000-switch entrance/cache/session/fs/queue + arch enforcement |
| B11 AC-30 | P3/P9.3/P9.4/P9.9 | MATRIX-06; INSTALL-03/09; RELEASE-03 generic installed isolation | Consumer app/auth/ops full 1000-switch entrance/cache/session/fs/queue + arch enforcement |
| B11 AC-31 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |
| B11 AC-32 | P9 verifies only selected P3 source/release/install envelope | INTAKE-02; INSTALL-09; RELEASE-06/07 no new schema/runtime ownership | P3 plus central/cloud/consumer schema/auth/ops/P10 full original subconditions; P11 consumer outcome |

## Future producer contracts

| Path | Contract/version | Consumption |
|:--|:--|:--|
| findings/P1-requirements-lock.md | p1 lock | P9.1 membership/provenance |
| findings/P1-acceptance-map.md | P1 AC map | P9.1 all R/AC slices |
| findings/P1-evidence-ledger.md | P1 source/equivalent proof | P9.1 provenance |
| findings/P2-contract-catalogue.md | p2-contract-catalogue/1 | P9.1 actual adopted source/API/profiles |
| findings/P2-contract-proposals.md | P2 signatures/BC/schema | P9.1 accepted delta/no-delta |
| findings/P2-consumer-cases.md | P2 cases | P9.4 neutral conformance |
| findings/P2-gate-requests.md | P2 actual gate requests | P9.1/7 protected receipts |
| findings/P3-adapter-receipts.md | p3-adapter-receipts/1 | P9.1–9 included P3 |
| findings/P4-extension-receipts.md | p4-extension-receipts/1 | P9.1–9 included P4 |
| findings/P5-wakeup-receipts.md | p5-wakeup-receipts/1 | P9.1–9 included P5 |
| findings/P6-data-receipts.md | p6-data-receipts/1 | P9.1–9 included P6 |
| findings/P7-utility-receipts.md | p7-utility-receipts/1 | P9.1–9 included P7 |
| findings/P8-tool-receipts.md | p8-tool-receipts/1 | P9.1–9 included P8 |

## Evidence JSON companion — p9-evidence/1

Each Evidence table Path resolves to retained JSON with schema_version=p9-evidence/1, proof_class=executed, id/cohort/cell/command/cwd/runtime/start/end/exit/result matching table cells; source_commit/recipe_commit/candidate_sha256/lock_sha256/config_sha256/fixture_sha256/log_path/log_sha256/cases are mandatory. log_path is a retained repository-relative plan evidence artifact whose bytes match log_sha256. cases names actual assertions, not document-generated outcomes; expected/actual/check commands and source/platform hashes in logs remain executor-verified. A expected refusal may have nonzero exit with PASS assertion; exit never conflated with case verdict. Source-only qualification records retain actual verification command and source/fixture hashes; unavailable execution cannot mint wrapper.

Hosted evidence adds run_url/run_id/run_attempt/job_id/workflow_sha and Kind=hosted; private adds grant_sha256 and Kind=private; publication adds grant_sha256/operations and Kind=release; delivered install adds delivery_urls/download_sha256 and Kind=released-installed. Source/config fixture no-delta uses explicit hashed applicability document rather than empty sentinel. Candidate/environment identifiers bind whole supported cell set; full final package-row matrix/gate/source/version validation stays semantic owning acceptance, structural wrapper cannot prove real remote/provider behavior. No report or volatile journal/self hash in inputs.
