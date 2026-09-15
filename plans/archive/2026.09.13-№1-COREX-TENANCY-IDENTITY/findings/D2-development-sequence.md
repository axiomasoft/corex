# D2 — последовательность разработки и приёмки остатка

**Статус:** исполняемый дизайн остатка; код в этой сессии не выполняется. RAG:—. Основание: [D7](../decisions/D7-remaining-sequence-and-autonomy.md), [D6](../decisions/D6-d2-membership-profile.md), исходный D2 и qualification packet §2–8. **D2 NOT released for implementation. Effective DI:0/34; executed AP:0/13.** Эта последовательность не является новым owning receipt.

## 1. Что уже завершено и почему план зациклился

P1.1/P1.2 подготовили inventory и dossier; P2.1/P2.2 — boxed/local и tenancy local/fake-cloud storage; P3.1/P3.2 — schema/replay dossier и unbound proof-gated signup claim; P6.1 — MCP ownership map. Их исторические GREEN сохраняются только в исходном scope. Повторно открывать эти фазы, переснимать общий inventory или получать framework freshness для закрытия membership нельзя.

Остаток: три прежних P4-item, P5.1 и P6.2. Старый P4.7 одновременно требовал durable generation/epoch/fence, clock, expiry, restore, session/provider, cleanup и ingress, но исключал новый storage mapping. D6 уже заменил cache-lock/no-DDL предложение; item-body оставался несовместимым с этим профилем. SWITCHER-AUTHORITY не равен membership acceptance. Наконец, handoff просил нового owning manifest, хотя задачи подготовки такого результата не были частью продолжения.

Исправление: техническую готовность и local evidence производит исполнитель; owning acceptance и deployment доказываются отдельно, без подмены синтетическим окружением. Планирование нового producer не закрывает исходный DI/AP.

## 2. Рабочая последовательность

| Порядок | Item / результат | Предшественники | Доказательство завершения | Владелец |
|:--|:--|:--|:--|:--|
| 1 | P4.1: UUID safety; profile hydration не предоставляет membership authority | P1.1/P1.2 уже terminal; bounded local implementation admission | P4.1-identity-safety.md: unchanged profile, deny-state, no UUID rekey/root tenant write | CoreX implementer |
| 2 | P4.2: строгий transport/query/trigger parser и typed failures, без runtime registration | P4.1; exact local/API admission для применимой delta | P4.2-transport-proof.md: byte fixtures, no call on scope mismatch, no positive authority from trigger | CoreX implementer |
| 3 | P4.3: durable tenant pair store R/G/E/F/AF и точная арифметика | P4.2; local fixture/storage admission | P4.3-pair-store-proof.md + exact fixture mapping: crash/CAS/overflow/AF invariants | CoreX implementer/schema |
| 4 | P4.4: elapsed/domain contract, lease, priority expiry и remaining-COMMIT bound | P4.3 | P4.4-expiry-proof.md: expired/domain-lost deny, paused holders cancelled, no late positive COMMIT | CoreX implementer/ops fixture operator |
| 5 | P4.5: final session/remember transaction и typed denial до response/cookies | P4.3/P4.4 | P4.5-credential-proof.md: both-or-neither issuance; every guard/pair; old INSERT/UPDATE/recaller cannot resurrect | CoreX implementer/session-auth seam |
| 6 | P4.6: durable inbox, refresh job, reconciliation и crash/retry | P4.2/P4.3/P4.4 | P4.6-inbox-proof.md: ack only durable admission; retry retains logical ID; no event grant | CoreX implementer |
| 7 | P4.7: compose all seams и isolated A/B acceptance | P4.1/P4.2/P4.3/P4.4/P4.5/P4.6 | P4.7-session-proof.md: source-bound isolated integration, supported/unsupported entrances and local fault limits | CoreX implementer |
| После 2 | P5.1: accounts menu read; canonical cache-loss fixtures; no SSO fallback | P4.2; exact accounts read API admission | P5.1-switcher-proof.md: identity/product isolation; no old-list recovery; menu never auth | CoreX implementer |
| После local P4/P5 | P6.2: same-source local package delivery | P2.2/P3.2/P4.7/P5.1/P6.1 | P6.2-package-delivery.md: exact local proof refs, six delegate rows and external gap owners | CoreX delivery owner |
| Параллельная подготовка | P7.1: accepted central API/BC/storage/channel/event/source packet | D7; actual owning evidence для qualification | P7.1-central-qualification.md; only proved DI subconditions qualified | Central API/AS/ledger/PKI/edge + CoreX contract owners |
| После P7.1 acceptance и local P4/P5 | P7.2: accepted cloud target profile и adoption obligations | P4.7/P5.1/P7.1; actual cloud owning evidence | P7.2-cloud-qualification.md: approved target schema/auth/clock/witness/entry inventory; effective release remains UNFILLED until deployment | Consumer app/auth/session/schema/ops |
| После accepted target | P7.3: adopt accepted public facade/storage/profile | P7.1/P7.2/P4.7/P5.1; exact membership adoption grant | P7.3-adoption-proof.md: accepted API/BC/storage/config and changed local tests | CoreX implementer |
| После adoption и real bindings | P7.4: executed AP and timing/capacity packet | P7.1/P7.2/P7.3; accepted target environment и exact execution authority | P7.4-membership-acceptance.md: command/log digest per AP and real measured bounds | Central+consumer acceptance operators |
| После qualification | P7.5: release/pristine и foreign reconciliation handoff | P6.2/P7.4; independent distribution receipt | P7.5-release-readiness.md: exact release/ref or NO-GO with precise remaining conditions | CoreX release + APPROVED-SPECS + consumer reconciliation owners |

Items are ordered by Inputs, not numeric filenames. The default within-phase recommendation is the dependency-safe order above; only ready subsets execute. P5.1 can proceed after transport while P4 session work is ongoing. P7 receipt preparation never blocks independent local work. No automatic phase audit or new session is required merely because an item ends.

## 3. Target contract for local engineering

The local target is the D6 architecture and packet §2–4 proposal, with explicit fixture scope. It is an engineering specification, not an approved central API or production schema. Implementer freezes exact chosen source/DTO/storage/CHANGELOG bytes before a protected change; no owner interview on clocks, namespaces or implementation style.

- Identity association is canonical UUID; email/phone are profile attributes. Preserve existing IdentitySyncer signatures. Profile hydration never sets active=true by itself, refreshes a positive lease or rewrites G/E/AF. Deny/missing/deleted stays deny; regrant is the fenced projection's responsibility. Named management caller alone writes root bookkeeping; tenant caller is denied before root I/O.
- Transport target is packet §2 constructor order, enums and signatures. GET uses scalar identity_id/account_id/product/request_id query and no body; byte fixtures cover encoding/duplicate/array/empty/mismatch and profile negotiation. Central endpoints/issuer/client/SAN are injected public binding inputs with no guessed defaults. Signed event is a refresh trigger. Audit actor never grants permission. Unknown shape/account/product/error denies; uncertain revoke retries only the same accepted idempotency binding.
- Existing potentially distributed public surface is preserved until exact BC/major acceptance. New draft orchestration is hidden/internal and unregistered. Where the proposal changes an existing Contract/DTO, PUBLIC-API stays a finite pre-mutation boundary; implementer prepares the concrete old/new comparison itself. No claim of nondistribution from dirty files/local tags. Proposed major number remains UNFILLED with CoreX contract/release owner until the owner actually chooses it.
- Local fixture mapping proposal: canonical decimal strings for every R/G/E/F/AF; PostgreSQL numeric(20,0) fields with explicit 0..18446744073709551615 checks, exact parameter validation before DB cast, checked atomic increment/CAS, no float/rounding/coercion. naming/DDL/constraints and conversions must be emitted by P4.3 as exact fixture SQL and tested; numeric scale alone does not validate input. This selects a disposable reference representation only; accepted production storage remains DI-20/26/34 UNFILLED. Tick values have a separate unit/range contract and are never revision counters.
- Central ledger is a test fixture only in CoreX. Atomic R/G/state/idempotency/outbox transitions are simulated and verified; the actual central writer stays external. Tenant pair store binds committed AF to the published lease while a newer admitted F need not cancel the older valid AF lease. E/barrier IDs and cancelled F survive local fixture crash; deny/expiry clears lease and forbids delayed publication.
- Pair, user and credential locks use deterministic account/identity order on one trusted tenant connection. HTTP happens outside pair transactions. Final credential save compares original G/E plus committed AF/qualified lease, never relabels an old callback or session after regrant. All guards/pairs participate; unsupported cross-account/cross-connection combinations deny.
- Clock adapter supplies domain/process identity, tick units and a conservative elapsed upper bound. Missing/unqualified adapter or domain mismatch denies; no guessed shared hrtime epoch. Reference controllable clocks prove boundary logic; real clock/platform qualification belongs to P7.2. Require elapsed upper bound plus qualified remaining commit bound <45s or a proven equivalent. No local fake establishes a deployed 45/60s guarantee.
- Priority expiry must cancel paused holders including inside a pair transaction. Disposable fixture operator can terminate only fixture-owned backend/workers, confirm rollback and resume stale work to prove fencing. No production kill mechanism/value is selected here. If a real bound cannot be proved, record which condition failed, keep auth fail-closed and repair the local seam; do not label FE-60 GREEN or quietly expand its budget.
- Joint session+remember issuance uses one final transaction. The local reference consumer supplies fixed driver/provider/store/response composition through public extension points; no mandatory tenancy→auth dependency or edit to private auth. Every fault removes authenticated context and all relevant queued/prepared cookies before response release. Remember-off is an explicit supported profile: prohibit issuance and reject old recallers. Both remember-on/off are local variants to verify, not a renewed product interview.
- Restore fixture keeps its witness outside the restored central/tenant/credential set; covers allocated/issued and committed/acknowledged R/G/E/F/AF plus barriers, owners and credentials. Restore admission remains closed until nonreuse recovery, credential invalidation, cleanup and fresh authentication. Actual service/custody/deployment choice stays with P7.1/P7.2 owners.

## 4. Gate placement and one prepared admission

This session edits plan/documents only. D6 is unchanged. The next code invocation needs a finite bounded local implementation grant; it does not need a central deployment manifest. Do not treat this design request as that grant. Before requesting any genuinely missing right, prepare the exact scope/files/digests, test-only environment, old/new public comparison, fixture mapping and recommended choice. Existing authorization persists across subsequent items within that exact scope. Missing setup is investigated by read-only preflight; the owner is not asked to select routine tools or type commands.

| Original gate | Local engineering treatment | Actual unresolved owner/action |
|:--|:--|:--|
| PLAN-SOURCE | Prepare source/file digests and qualified portable design inputs; preserve unrelated dirty baseline | CoreX operator seals a scoped ref only when commit is authorized; no commit by this design |
| PUBLIC-API | Preserve existing API; prepare exact comparison and proposed major/CHANGELOG before changed public mutation | CoreX contract/release accepts exact baseline/delta/major once; P7.1 carries proof |
| FS-DRIVERS | P2 terminal storage evidence reused only for unchanged relevant inputs | Owning adapter operator reruns affected driver delta if it changes; no fresh inventory |
| DEPENDENCY-SCOPE | Default no dependency addition; use installed supported extension points/reference fixtures | A real addition needs exact manifest/lock/adoption approval; investigate alternatives first |
| SIGNUP-DDL / CLAIM-OWNER / TEMPLATE-READY | P3.2 D5-bounded unbound claim remains terminal; do not reopen | Runtime central provisioner P6.25 supplies actual caller/template/recovery receipts before wiring |
| CENTRAL-TRUST | Fake protocol proves library behavior only; positive runtime registration stays absent | P7.1 central owners supply accepted profile/AS/PKI/edge/release bindings |
| PROJECTION-OWNER | Local management-only/tenant-only negatives supplied by P4.1/P4.7 | P7.2 consumer owner binds actual trusted deployment entrypoints |
| SESSION-BINDING | Reference consumer with explicit guards/driver/A/B fixture in P4.5/P4.7 | P7.2 consumer auth/session/schema supplies real registration and supported entrances |
| SWITCHER-AUTHORITY | P5.1 strict read/cache-loss reference fixture; no local SSO grant | P7.1 central switcher owner accepts exact response/cache recovery; canonical normative conflict gets one concrete decision |
| IMMUTABLE-PRIVATE | No private auth/install/read prerequisite for local composition | If actual private deployment needs proof, owning source operator supplies accepted channel/ref with separate read authority |
| DISTRIBUTION-RELEASE | P6.2 may close as faithful local packet with pending release entries | P7.5 references actual APPROVED-SPECS public/private/pristine release results |
| AUDIT-CONSENT | No phase audit dispatch inferred; existing focused item reviews remain scoped | Only a real unresolved material question can propose an exact consent/result |
| EXTERNAL-OPS | Preparation and read-only qualification only; no new remote calls/writes/private access | Named operator supplies exact finite action/environment authority after prepared result |

No gate is marked satisfied by changing its place in this graph. No whole-plan block is inferred from an external gap. A local public/storage authority gap stops only that protected mutation; continue nonmutating preparation or a genuinely independent already-authorized task.

## 5. Complete DI/AP producer map

Use gap receipt's exact owner per row. The ranges below allocate work and do not change its qualification disposition.

| Receipt set | Local contributor | Owning qualification producer / concrete next action |
|:--|:--|:--|
| DI-01…06 | P4.2 validation logic | P7.1 Central AS/resource binds immutable source+release, TLS metadata/JWKS and actual resource/profile |
| DI-07…10 | P4.2 byte/API/idempotency fixture | P7.1 Ledger/API/AS accepts exact read/revoke/schema/registration; prove effective request and release |
| DI-11…16 | P4.2 rejection/key-purpose fixture | P7.1 PKI/AS/edge supplies sanitized public registration/custody/runbook/key and per-request retirement proof |
| DI-17…18 | P4.2/P4.6 parser/inbox/crash | P7.1 Events+consumer accepts recipient/schema/storage/retention and release-bound retry/ack proof |
| DI-19…20 | P4.3 restore/central reference | P7.1 Ledger+schema/ops supplies actual primary/failover/central durable mappings and independent witness |
| DI-21…26 | P4.1/P4.3/P4.4/P4.7 local authority/store/time/A-B | P7.2 Consumer/schema/ops accepts real root/tenant/store/clock/scheduler/session/storage/restore deployment |
| DI-27…30 | P4.5/P4.7 auth/session reference | P7.2 Consumer auth/session/app supplies every deployed guard/entry/driver/provider/response/remember profile |
| DI-31…32 | P4.4/P4.7 controlled fault logic | P7.4 Ops executes real capacity/expiry/cleanup/monthly timing with explicit domains/error/no-data |
| DI-33 | P4.2/P4.7 no-call/audit fixture | P7.2 Consumer permission adapter + central audit binds actual policy/action/audit schema and negative proof |
| DI-34 | All local items source/API fixture dossier | P7.1 CoreX contract/schema/source accepts exact BC/major/storage/dependency/source carriers; P7.5 seals release |
| AP-01 | P4.1/P4.7 UUID/deny | P7.4 CoreX+consumer executes real owning authority fixtures |
| AP-02…03 | P4.2 protocol negatives | P7.4 AS/resource/edge/signer executes actual mTLS/registration/key/open-connection retirement |
| AP-04…05 | P4.6/P4.3 crash/history | P7.4 Events/ledger executes actual durable retry/read-start/failover/idempotency/history |
| AP-06…07 | P4.3/P4.4 fencing/time | P7.4 Consumer/ops executes paused holders, domain/suspend/loss and conservative bound |
| AP-08…09 | P4.5/P4.7 final-save/entrances | P7.4 Session/auth/app executes every actual write/guard/recaller/callback decision and denial path |
| AP-10…12 | P4.3/P4.4/P4.7 restore/controlled load | P7.4 Ledger+consumer/schema/ops executes independent witness/real FE-60/capacity/monthly SLO |
| AP-13 | All local source/API/storage dossier | P7.4 CoreX owners verifies accepted major/CHANGELOG/storage/source; release result referenced by P7.5 |

## 6. Receipt production and resumption without owner busywork

Executor/qualifier computes the manifest from its declared outputs: receipt ID/path, owning role, acceptance actor/scope, DI/AP and exact subconditions, immutable selector/SHA, accepted target API/storage/profile, environment/release, sanitized public bindings, command/fixture/log digest/outcome and applicable timing units/domains/error. The owner does not create hashes/manifests by hand. For each field distinguish observed, proposed, accepted, effective and executed. Required absent fields remain exactly UNFILLED. Local hashes/logs are local observed/executed evidence, not central adoption.

Local acceptance: per-package new tests; root lint:check/analyse/test/test:pg, architecture and src mutation gate plus affected package checks per doctrine. Read-only PG/test-isolation preflight before disposable DDL; no dev/prod migrate:fresh. No test, log or quality receipt is fabricated by this design. Relevant changed source/test/tool/environment invalidates only affected evidence; bookkeeping alone regenerates views without rerunning PHP.

P7 lacks evidence: write one precise checkpoint in the owning P7 finding with receipt/subcondition, existing owner, exact next production action and resume predicate. Reuse it until source/scope changes; do not repeat failed central discovery/framework freshness. Do not ask the owner the same missing-input question. Proceed to another genuinely ready task. Qualification resume is triggered by the producer's actual accessible receipt and immutable binding; broad inventory and mtime do not trigger it.

Development completion = local P4/P5 acceptance + faithful P6.2 packet. Membership/deployment acceptance = all mandatory owning DI/AP subconditions proved, separately. Public/runtime release = actual P7.5 qualified rights/results. These milestones are distinct; local completion never promotes DI/AP or D6.

## 7. Current next action

The plan and target contracts are now written; no discovery-only design loop remains. First code task is P4.1, followed by the dependency-safe local order. Before its first protected mutation, the executing invocation binds the already-prepared finite local scope to actual implementation authority and sealed/qualified source. If authority is still absent, that exact admission is the sole issue; central deployment/acceptance manifests are not requested. No public API/DDL/release choice is inferred from the current plan-design request.
