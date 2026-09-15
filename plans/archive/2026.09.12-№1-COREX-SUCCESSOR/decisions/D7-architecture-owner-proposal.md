---
type: workflow
id: D7
date: 2026-09-12
status: accepted
item: P3.2
items: [P3.2]
supersedes: []
superseded_by: null
---

# D7 — Tenancy/auth: proposed responsibilities and authority boundaries

**Date:** 2026-09-12
**Status:** accepted
**Actor:** gpt-5.6-sol/high · plan-run
**Evidence:** RAG:— D1/D2/D5; current CoreX worktree on `8b11672a1e1f14df0157404d041ce4e8f0aa2a23`. D2 DB_SCHEMA hash `9768dfa40e72492f7863ee2a15f36157e20a0810f5eb998125401482a0f9ac98` reverified. B-11 `plans/flex/30_blueprints/B-11_auth-tenancy.md` hash `692165b86e2640a913e359653368ef6dd429ee0eb35db732c95cc4141a7cc2de`; relevant §5.7/§5.9/§6.1 meaning remains D2.
**Supersedes:** —
**Superseded By:** —

## Решение

Принять bounded proposal evidence. Все четыре выбора остаются у Dmitry Vostrikov; frozen-schema/public-contract/consumer/operator approval не получены. Ни одного assignment writer, boxed initializer, domain verifier или hash gate эта запись не доставляет.

## Почему

Read predicates и модель данных не доказывают enforcement на write boundaries. Предложение привязывает будущие проверки к точному владельцу изменения; app vocabulary не превращается в universal L0 roles/hosts.

| Seam | Accepted current surface | Proposed surface, pending | L0 verdict | Authority |
|:--|:--|:--|:--|:--|
| TEMPLATE-PARITY / OQ22 | TemplateClonePostgreSQLManager checks ready; no hash | Versioned build manifest and schema verifier before any provisioning/activation write | Generic integrity useful without CMS/CRM | Dmitry + FlexCRM frozen DB_SCHEMA owner; any public Contracts delta separate major |
| BOXED-INIT / OQ20 | Core SingleAccountResolver constant context; NullTenancyManager workspace no-op; tenancy overrides manager unconditionally | Optional generic profile adapter + explicit request/job init | Generic account/workspace usable by any Laravel app | Dmitry profile/spec choice; optional package placement; public semantic/API change major |
| DOMAIN-OWNER / OQ19 | HostTenantResolver consumes non-null verified_at, excludes deleted domain/account and missing slug | Trusted control-plane verifies ownership; tenant library consumes state | Reader/state seam generic; DNS/TLS operator work outside L0 | Dmitry + consuming control-plane/edge owner; separate external authority |
| ROLE-WRITE / OQ21 | RoleAssignment fillable model/readers; enum+ImpersonationGuard assertion available | App-owned writer calls guard before all privileged grants | Role policy app-specific; no universal owner/admin roles | Dmitry + actual consumer assignment-writer owner; no implied new public API |

## TEMPLATE-PARITY

Current `root_templates` columns exactly: `id uuid PK`, `version int UNIQUE`, `db_name varchar63`, `status building/ready/retired`, `embedding_model varchar64`, `embedding_dim smallint`, `git_ref varchar64 nullable`, `built_at timestamptz nullable`, `created_at/updated_at timestamptz`. Neither D2 frozen schema nor migration has `schema_hash`.

Exact question: «Одобряешь additive schema_hash amendment замороженного root_templates и versioned canonical build/verifier contract с отдельным owner-approved executable spec, либо явно принимаем ready-only residual risk и hash работу откладываем?» Рекомендация — amendment proposal. Foreign source owner approval обязателен до migration. Hash column НЕ считается accepted.

Future deterministic input definition: `hash_format_version=1`; logical tenant schema assembled from selected package migration artifact refs, plus schema catalogue projection after applying them on disposable PG16. Sort schema/object/column identifiers by UTF-8 bytes; include column order/type/nullability/default, PK/FK/check definitions, indexes (including predicates/opclasses), sequences and extension names/versions. Remove database name, owners/ACLs, OIDs, timestamps, sample rows and environment paths. SQL expressions normalize only through one versioned catalogue serializer; never whitespace-strip arbitrary SQL. Include selected module set and embedding model/dim; keep exact source refs in signed build manifest, separate from semantic schema digest. Canonical JSON sorted keys/compact UTF-8 → SHA256 lowercase64. Serializer/tool version pinned; equivalent builds must produce identical bytes before adoption.

Producer: consumer release/template build in an isolated DB, publishes manifest after verification and marks template ready last. Verifier: provisioning control-plane compares approved expected manifest hash, recorded hash and read-only actual template catalogue before params save/pool claim/CREATE/grant/status transition. Existing `StanclTenantDatabaseProvisioner::provision()` saves params before firing clone pipeline: adding a check only inside clone manager would violate proposed no-write acceptance. Existing template entry is not proof of current schema. Activation must verify pool entries before pull/claim writes; missing/mismatch/stale hash denies with no application/DDL/activation writes. Verification access is restricted to build/verifier, never app hot-path connections to golden DB. Freshness/locking must prevent template alteration after verification; build seals template, mutation retires it.

Wave/pool policy: wave pins target manifest; changing it creates a new wave identity. Stale templates retire; stale pool shells quarantine and are not activated. Existing account remediation and physical DROP require separate authorized lifecycle plan; no silent rebuild/delete. Negative acceptance: ready+wrong/missing hash denied; reordered equivalent catalog same hash; changed type/index/check different hash; stale pool/wave blocked; mid-build status blocked; duplicate retry no extra DB; verifier failure produces zero params/status/pool/event/DDL writes. Affected: root_templates migration/frozen source, provisioning/clone/pool/lifecycle/waves, isolated tests. Rollback: stop provisioning of affected versions, retain old metadata, explicitly choose approved ready-only mode; never drop new column/data or live DB automatically.

## BOXED-INIT

Two current profiles must remain distinct. Without tenancy, core's manager is always initialized but ignores setWorkspace; resolver yields constant boxed account and no workspace. With tenancy installed and host/OIDC disabled, Stancl manager is bound but no request init runs; ResolveWorkspace rejects an uninitialized manager. Registering its alias does not initialize a request. Root boxed8.3 CI drops tenancy: it proves install/test compatibility, not workspaces.

Exact question: «Поддерживаем generic single-account + workspaces через optional adapter с явной account/connection configuration и lifecycle init, либо boxed workspaces объявляем unsupported до реального потребителя?» Рекомендация — поддержка как отдельно спроектированный optional profile, при подтверждённом потребителе. Core-only8.3 не должен зависеть от stancl или tenancy8.4. Возможное место: optional Laravel profile adapter above core; public interface additions/изменение frozen no-op semantics требуют major gate, без него spec non-runnable.

Future sequence: consumer installs approved adapter/migrations for its own single account DB; container binds generic manager/context resolver; request/job start initializes configured account on its explicit connection; verify session/principal account; resolve workspace through membership-aware resolver; setWorkspace and assert exact context; invoke consumer; finally clear workspace/account and restore original connection/cache/queue/fs state. Boot singleton binding itself must not choose account or open DB. Account-wide routes omit narrowing; workspace routes cannot silently pass no-init. No platform domains, product enum, IdP or root control-plane requirement. Negative acceptance: no config/no init denied, unknown or foreign workspace denied, no membership denied, job/request exception clears context, sequential requests cannot inherit workspace, core-only8.3 graph remains clean. Affected: optional provider/adapter, context and workspace integration; any Contracts touch separately approved. Rollback: opt out of adapter, restore core constant no-workspace profile; preserve consumer data, no migrations rollback automatically.

## DOMAIN-OWNER

`AccountLifecycle::startTrial()` creates a verified platform domain; it does not verify custom ownership. `HostTenantResolver::resolveWithoutCache()` consumes verified state; resolver success is not DNS proof. Verification token/dates in Domain are state, no CNAME/TXT/ask write service currently implemented.

Exact question: «Назначаем CNAME+TXT ownership verification/ask/TLS control-plane и edge владельцу потребителя, сохраняя generic verified-state reader в tenancy, либо сначала проектируем reusable optional verifier seam с отдельным public-major gate при необходимости?» Рекомендация — consumer/control-plane owner; CoreX не управляет DNS/TLS. Naming of that owner and permitted write credentials is part of future spec, not assumed here.

Future consumer verifier binds unpredictable short-lived single-use challenge to normalized host AND account; checks configured edge CNAME and exact TXT challenge; trusted writer commits verified_at and invalidates resolver cache. Edge ask reads only verified, live domain and permitted account status; allow200/deny404 policy and ca_used recording owned by edge/control-plane, not HostTenantResolver. Domain reassignment/revocation clears verification and invalidates cache. All hostnames/config provided by consumer.

Future negatives: CNAME alone denied; stale/wrong/replayed token denied; proof for account A cannot verify B; deleted/reassigned domain denied; revoked domain denied even with warm resolver/ask cache; suspended/deleted account cannot get TLS when policy denies; malformed host denied; duplicate-host race has one owner; unauthenticated writer denied. No DNS queries or TLS operations run now. Affected: consumer verifier/writer/ask, root_domains state, resolver invalidation; any schema/Contracts change owner-gated. Rollback: disable custom-domain verification/issuance, clear only authorized verification state and caches; operator handles edge changes separately, no automated DNS deletion.

## ROLE-WRITE

Exact question: «Назначаем privileged assignment writes приложению-потребителю с обязательным ImpersonationGuard перед grant, либо нужен generic L0 writer с consumer-supplied role policy и отдельным public-major/spec gate?» Рекомендация — app-owned writer until reusable consumer need demonstrated. No universal role names added to L0.

Actual storage seam is `aut_role_assignments`: Eloquent RoleAssignment save/create, relationships, query-builder insert/upsert/update and any consumer job/admin API importing grants. Package source currently contains model/readers, no privileged-assignment writer. D5 creates a test assignment and proves scoped reads, not write enforcement. `GrantPrivilegedRole` enum plus `assertAllowed()` availability is not a production call-site proof. Model-saving guard cannot protect raw SQL or a deliberately allowed write route by itself.

Future app writer classifies privileges from consumer-owned role/capability policy, derives trusted actor and account/workspace, calls `assertAllowed(GrantPrivilegedRole)` before any transaction/write/event, checks caller grant authority and scope, commits assignment/audit atomically. Support impersonation context must be correctly initialized for HTTP/jobs/CLI; boxed NullImpersonationService gives no support-session proof. Caller-supplied granted_by or HTTP allow-write override cannot bypass semantic guard. App owns prohibiting direct/bulk writes and naming every entrypoint; reusable writer alternative requires public API approval first.

Future negatives: support grant denied via API/job/CLI/allowed-write route with zero assignment/audit writes; raw/bulk path cannot bypass owning seam; escalation by changing role/principal/workspace/TTL denied; cross-account grant denied; unprivileged actor denied; trusted authorized normal grant succeeds; revoked/expired support state handled before write; transactional failure creates no partial grant. Affected: consumer writer/policy/context/audit/integration tests, not current read predicates. Rollback: disable grant entrypoints and revert approved writer code; retain audit trail/assignments, no automatic destructive cleanup.

## Consumer-triggered workspace operations

Move/Rename remains deferred until concrete consumer requests tree mutation. Rename of label differs from slug/path identity; Move needs owning parent/path updates, membership/scope/cache effects and CrossWorkspaceMove safeguards. No automatic new CoreX feature or request for runtime approval is created by this classification. Future approved item must pin affected consumers and transaction/negative tests; current workspaces/readers remain delivered as before.

## Acceptance and residuals

Four distinct owner questions are pending in queue. Self-review traced proposed guards to the earliest write sites and checked L0 agnosticity, schema column parity, boxed no-init, domain takeover negatives and writer bypasses. No schema/public interface/runtime source changed. Proposal acceptance is not owner acceptance; implementation requires owner-provenance D# plus separately detailed executable scope.
