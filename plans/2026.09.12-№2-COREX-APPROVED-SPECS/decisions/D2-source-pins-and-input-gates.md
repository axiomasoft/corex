---
type: workflow
id: D2
date: 2026-09-12
status: accepted
item: P1.1
items: [P1.1, P1.2, P1.3, P1.4, P2.1, P2.2, P2.3, P3.1, P3.2, P4.1, P4.2, P4.3, P4.4, P4.5, P5.1, P5.2, P5.3]
supersedes: []
superseded_by: null
---
# D2 — Exact source pins и невыданные authority/input gates

**Date:** 2026-09-12
**Status:** accepted
**Actor:** plan-designer/frontier/high · gpt-5.6-sol
**Evidence:** RAG:— local repository + resolved Brain catalog, files SHA256 checked in this design turn; no external calls. Changing external premises remain [UNVERIFIED] until the named future boundary.
**Supersedes:** —
**Superseded By:** —

## Решение

Закрепить source inputs и gate ledger ниже. Это observations, не grants. Foreign addresses logical: resolve project via Brain catalog by repository/name, never assume a host path. Future bundle consumes committed portable qualification record; foreign originals rechecked by source-owning operator before writes. Hash mismatch stops only the affected amendment; successor D# must explain changed meaning. Never overwrite this record.

| Project/source | Exact logical file | Design SHA256 |
|:--|:--|:--|
| CoreX | `docs/03_Dev/Architecture_CoreX.md` | `094ab1e395a5cf8f53ce181ee17ad91ad2d5a12972b0b3ad8188227321ff4f47` |
| CoreX | `docs/03_Dev/Development_Process.md` | `f14bfcd88dfcd2b5652521061092fa24ef55541716fb1dda069d3cd5ca759fb5` |
| CoreX | `docs/03_Dev/Package_Governance_and_Release_Policy.md` | `a9dc473ed6082d1e2970d7da9bf56843443d03d37fcca1f3736a81dfd5c82983` |
| CoreX | `docs/03_Dev/ADR/ADR-001 Stack.md` | `c3bd9e7f4e15249134e36faf48159e87987521e75566cde72120d27879812c44` |
| CoreX | `docs/03_Dev/ADR/ADR-003 ULID.md` | `624dcedb469f48c2903804552d15185b0db3db2769605ab5464783403056f9cb` |
| CoreX | `docs/03_Dev/ADR/ADR-004 Multi-Tenancy.md` | `8da48e8e47ff6c38a8e579ef953382260e3486559af29faf420707c9b2c74dd4` |
| CoreX | `docs/03_Dev/ADR/ADR-008 Versioning.md` | `23b0803da3080e73e831f06836e1ff8a47a0bd752ca88a7969824397a5e67262` |
| CoreX | `docs/03_Dev/ADR/ADR-002 Monorepo.md` | `d96b8ce451037f1ddc73f622ada352f6cd4c73a9157c003ce6c01ab53bab5e88` |
| FlexCRM | `plans/flex/60_contracts/DB_SCHEMA.md` | `9768dfa40e72492f7863ee2a15f36157e20a0810f5eb998125401482a0f9ac98` |
| FlexCRM | `plans/flex/30_blueprints/B-11_auth-tenancy.md` | `692165b86e2640a913e359653368ef6dd429ee0eb35db732c95cc4141a7cc2de` |
| repository | `docs/dev-doctrine.md` | `9cd2a620fe3eaf3c18ff925d416b9ceaf6090f70634f0c184d657c05708e002c` |
| repository | `composer.json` | `d4ca9f7e10737cafdd005d157e1c2054a8c50ee15a661a6a39bdb737639ec537` |
| repository | `composer.lock` | `eaeccb8f801ee031365213d45fdbe3f98e7b21008bcb3ede6931809c6dcacd38` |
| repository | `phpunit.xml` | `a93383e681bf5456cbb704cdf9d4f5b62b39def88f51c1f8e8a45ff3a6a25b91` |
| repository | `scripts/coverage-min-floor.txt` | `dbc844dbb3c147310bd56e04f349830505e62c932f4633a0e3a3d73182e0f18c` |
| repository | `.github/workflows/ci.yml` | `fbcb96120c93e6333a4e4156d3b04cbd503aed7ce5ad6711a9274ee43d0e4e58` |
| repository | `packages/core/src/Models/BaseModel.php` | `8eb03aa115048292e5def18b4bd5db6888ded97e933fd8a493e20c77b1d19544` |
| repository | `packages/core/src/Tenancy/ImpersonationGuard.php` | `51fb0caeb22c47b02e9f106f6a6cbf824eab903fa1bb8f5f50f91a6d32f2827f` |
| repository | `packages/core/src/Enums/ImpersonationRestrictedAction.php` | `772f26edf31ba1396c3c06575b467582d39d9d9bfd9ff1e1521c6374a9958fb3` |
| repository | `packages/tenancy/src/Provisioning/StanclTenantDatabaseProvisioner.php` | `2f3c3cc44c0c307594dd4e9a4412272ef964aec3a7e9042b53ad55be387d4300` |
| repository | `packages/tenancy/src/Provisioning/PendingPool.php` | `7ce21dbba4bd60f75a85141a028190ffc362a4332484f8d8afc3b1aa0b8a2a42` |
| repository | `packages/tenancy/src/Database/TemplateClonePostgreSQLManager.php` | `20b49d2ea30c8378fdb3dfc796fa695f84b109eadae5eb33ecd16a4bb5200c2d` |
| repository | `packages/tenancy/src/Jobs/CreateDatabaseFromTemplate.php` | `da8035452da3923162c6e96cbd11646b3a8f142a55c1f139e3c8b8e9cf9f11bb` |
| repository | `packages/tenancy/src/Lifecycle/AccountLifecycle.php` | `4eee3acce7adc8191e132005e1feec2426c23723c19f5aea9ba4044066d5113b` |
| repository | `packages/tenancy/src/Migrations/MigrationWaveOrchestrator.php` | `1c04cae5f371d6a77d86489af83d8dea676528a7d907268995058c821c7368f2` |
| repository | `packages/tenancy/src/TenancyServiceProvider.php` | `7f50f3a13710d3c9d85fed58423190575a05e5b0dc2e75a5de6ec6f47d2429d3` |
| repository | `packages/tenancy/src/Models/Domain.php` | `5b3940cbce38822feb9292ec267b74560d96d8d46e528852ca8d9ec264c2c1b4` |
| repository | `packages/tenancy/src/Resolvers/HostTenantResolver.php` | `44e062f05b3fa6c7d77208c951bda893fc39e12d3e6a6e7b368ff6136a6b85bd` |
| repository | `packages/auth/src/Models/RoleAssignment.php` | `5868b127eedfe26c5ef376fdf346607ac1298ef6a76a57a4ddec32b556dff334` |
| repository | `packages/tenancy/database/migrations/root/2026_07_18_170002_create_root_templates_table.php` | `8620edbf5907c05638bb94b2d65a6f7354b1ee7cce36aa276a1a60ae2892d70e` |
| repository | `packages/core/composer.json` | `966a1cd18bc21a5b0d95db311fc2715a7868105763e123c4a992871bc64ccc3f` |
| repository | `packages/core/README.md` | `bce12d4fb95f41be83ab211ce8d361416254c52e37ffeb62d67fec2a97833238` |
| repository | `packages/modules/composer.json` | `60ba7cb6a013be029d4897a7b27975d58cf01435f4360fc8696e87eed5bce73a` |
| repository | `packages/modules/README.md` | `68b73b965be98680e6f9bbc40a43941348b6911c328a6ef9ad6f87f46e36fffa` |
| repository | `packages/tasks/composer.json` | `b017b75acc50e376e101e7f5b3fe5d251751a8e85e52b5c16f3df84cc0a0633c` |
| repository | `packages/tasks/composer.lock` | `32efa6a2eefe06e057d3663c73268d322a9d2b7acaad14496dc98769b31b999b` |
| repository | `packages/tasks/phpunit.xml` | `440409f69d2915358c18db9f40321b027c5676bcad790376214960696da4be2c` |
| repository | `packages/tasks/README.md` | `88ad91a96dac4d2385fed43d0a99a2beacb3424b49c5772c38586a616ba263e9` |
| repository | `packages/tenancy/composer.json` | `25b2b754cc7069b6f7297683a3110a6dc0e822fe382138ba4d09e3f5b3ac19f8` |
| repository | `packages/tenancy/composer.lock` | `518ee0151af7d47d4711534a293dafb181f25faa9f364003e6661dd3dc50424a` |
| repository | `packages/tenancy/phpunit.xml` | `72102f10d6e3b930f6e81988d4ab9a4a8d5c33edaaff6974be566bd9546bd1a0` |
| repository | `packages/auth/composer.json` | `62c9dc1430316784351745248b73d1051941743d4658ffb5a9d3ff9fce77a4a5` |
| repository | `packages/auth/composer.lock` | `e2c93bdb71ae15401f0ac242d172a6cc905d72b90a59942d43d93b15e6da1048` |
| repository | `packages/auth/phpunit.xml` | `6288d49fc19be7a1c614d49de56f32f25b43eb31aabf328c12f3ed4ae34d5da8` |
| repository | `packages/auth/README.md` | `3ef71da3f8311ffa001128f3d8a9bb52d1fb186128ba2adf0173da6af2b1d6eb` |

## Source extracts и observability

Architecture §1: quality85, compatibility8.2–8.4/L11–12. Process §2 and DoD: old matrix and85. Governance §2: passport80 referring to Process, §4.9 tooling deferred. ADR001 July amendment says core8.4–8.5 while current core/modules/tasks manifests ^8.3. ADR003 already accepted UUIDv7; ADR004 only stale adjacent-ADR TODO needs correction. DB_SCHEMA §2 says18 root catalogue; §3.1 has20. Tenancy root migrations create17 distinct root tables. Missing creation paths: root_plans/root_subscriptions/root_wfl_wakeups; root_usage_snapshots created by tenancy but billing domain. driver-local sys_pubsub_messages is optional. No schema_hash in source or owning create migration. Current core/modules coverage source defined by root phpunit.xml; command min80; tasks test:coverage min92 (must not weaken). Historical88.3 is not current measurement.

D6 historical runner evidence stays attributed to run30329663500/SHA f95371624e90b921753295a3f58bb47f5d0b2af3: run failure/auth install failure/mutation skipped; no current runner GREEN. D4/D5 local evidence is predecessor proof, never pristine install proof. Prior local auth path reproduce failed before authentication; unique historical CI cause unknown. D8 Composer root-repository/stability/ref semantics are historical verified extracts; [UNVERIFIED] current external semantics/ref status, reverify against primary docs/API before future application. Selected refs stay stancl76e5f9655924271c6c9da54e2d4ccb82c6f71ea0; jobpipeline07dc54a49cf9e2df1238ee4a347af333260519a6 (rc7). Root/package lock discrepancy observed in D8; future reconciliation must inspect current lock, not inherit a presumed cause or silently move selected refs.

## Gate ledger (binding, not an owner queue)

| Gate | Applies | Current state / missing exact input | Release condition / authority owner |
|:--|:--|:--|:--|
| EXEC-SCOPE | all product/operator items | new plan design delivered only; no invocation of implementation | owner launches approved exact spec; no grant inferred from D11–D21 |
| SOURCE-DOCS | P1.2–P1.4 | hashes now checked; existing repo/vault docs divergence not reconciled | P1.1 per-file old/new hash+bounded patch+brain status/conflict scope; Dmitry/CoreX source owner, FlexCRM docs-only D13 scope retained |
| TEMPLATE-SCHEMA | P2.2–P2.3 | concrete root_templates DDL patch not approved | frozen DB_SCHEMA owner approves exact nullable64 hash/check amendment + deployment/backfill; broader DDL separately |
| TEMPLATE-SEAL | P2.2–P2.3 | consumer builder/operator, manifest trust binding, signing key identity, lock/seal permissions and template DB target not named | P2.1 exact non-secret bindings and capability proof; operator grants only isolated test/build rights; live adoption separate |
| PUBLIC-CONTRACT | any src/Contracts delta or changed public semantics | no major/owner approval | prohibit current delta; if unavoidable, separate owner-major/RFC contract decision and re-seal affected spec before implementation |
| CONSUMER-DOMAIN | P3.1 | no concrete project/operator/root schema authority, HTTP/job/ask entrypoints, edge host target, challenge store/expiry policy or cache API binding | consumer owner signs exact Files/entrypoint/source hashes and state/cache transaction protocol; no inferred FlexCRM binding |
| CONSUMER-ROLE | P3.2 | no named consumer writer/policy, grant entrypoints, job/CLI principal/context init, audit storage transaction or DB privilege bypass control | consumer owner supplies exact bindings/source hashes and authorized caller policy; no universal L0 roles |
| EXTERNAL-DNS-TLS | P3.1/live operator follow-up | no DNS/TLS/edge resource or credential authority | separate exact host/account/edge action authorization after local fake proofs; no DNS deletion or live issuance by local tests |
| STANCL-OWNER | P4.1–P4.2, tenancy P4.4 | fixed-metadata maintainer/org/repo/package version/tag, license/ownership permission and mirrored artifact not supplied | D20-selected upstream refs reverified; named maintainer approves metadata-only diff and immutable source mapping; creation/fork/tag authority separately |
| DEPENDENCY-SCOPE | P4.2, P4.5 | new manifest/lock edits not implemented or approved as exact executable result | scoped spec invocation + exact source/artifact availability; no branch fallback or changed upstream commit |
| PRIVATE-AZGUARD | P4.5 | candidate https://github.com/axiomasoft/azguard-private.git unconfirmed; compatible 0.2 immutable ref/metadata/artifact digest absent | AzGuard source owner confirms canonical channel, license/test use and immutable metadata/ref; never export private contents |
| PRIVATE-READ | P4.5/private runner | no selected-repository credential authority/injection approved | source owner/operator provides narrowly scoped metadata/contents read credential with lifetime; authenticated install explicitly authorized; never record token |
| EXPORT-INPUT | P4.3–P4.4 | exact committed candidate source SHA, package versions/refs, exporter tool digest, sealed upstream dist inputs and runtime image digests absent | approved release candidate bindings with deterministic export map; current dirty worktree cannot be an artifact source |
| RUNNER-PROOF | P4.4–P5.2 | no pristine public/private local or current runner receipts | package/framework exact tests; local/historical/current runner separate; external dispatch/rerun/secrets separately authorized |
| PUBLICATION | P5.3 | axiomasoft + four names approved topology; actual target availability/rights, version/tag map, source/tree/artifact refs and operation scope missing | P5.2 GREEN exact dossier + separate per-resource create/push/tag/release/Packagist authorization; secrets creation/injection separately scoped |
| BOXED-CONSUMER | no runtime item | named consumer/use-case absent; D15 deferred accepted | new consumer-triggered adapter design only; never invoke old BOXED proposal |

## Почему

Inputs отсутствуют независимо от принятого branch choice. Source correction approval D13 не расширяет concrete DDL права D14. Source-owner private channel и credential authority — разные gates. External authorities нельзя выводить из org topology, accepted agent proposal или local GREEN.

## Consequences

P1.1 допускает локальную подготовку portable patches после отдельного запуска. Остальные specs имеют полный алгоритм/negatives/rollback, но dispatch gated указанными inputs. Qualification fills bindings through new committed findings/accepted decision; placeholder consumer Files are not executable write authority. Generated decisions/_gates.md owns only actual queue requests (queue empty here); this ledger owns source/authority prerequisites and is referenced by open questions/phase DoR. Не исправлять pre-existing 2026.09.12-№1-COREX-SUCCESSOR P2.1 write-site defect или historical archive; scoped new-plan validation only. No Brain sync performed by design.
