# P2 — minimal contract catalogue design, p2-contract-catalogue/1

RAG:— Это **стабильный design input**, не produced P2 acceptance catalogue. Исполнение P2.1–P2.4
потребляет actual sealed P1; оно сейчас не начато. Внешняя проверка общих практик — P2-design-rag.md;
CoreX — внутренняя разработка, внутренние факты не исследуются через Perplexity (D6).

## Локально проверенные boundaries

| Existing surface | Source observation | Design choice |
|:--|:--|:--|
| CentralApiClient | Contracts/CentralApiClient.php: revokeMembership(RevokeMembershipRequest): RevokeMembershipReceipt; membershipFor(MembershipQuery): MembershipSnapshot | Keep обе операции; extension только по proven frozen gap. Constructor/payload/error signatures и corex-membership/1 отдельно pin в actual P2.2. |
| StrictTransport | Internal constructor принимает ChannelBinding, token validator и clock/context/removalPermission closures. request проверяет trusted account/product, profile/token/TLS, disallows redirects, timeout/connectTimeout 5. | Это internal reference, не generic public facade; no automatic promotion. Missing channel = CentralUnavailable, context mismatch = CentralUnauthorized; uncertain revoke отдельно. Local source не deployed proof. |
| PrincipalWorkspaceResolver | resolveFor(string/int principalId, nullable slug): WorkspaceRef на initialized account; lookup не switches connection | Keep, no role/identity engine. UUID профиль на принятой boundary не меняет все модельные IDs. |
| API SPI | RequestContext::tenant/principal/metadata; OperationAuthorizer::allows(Operation, RequestContext): bool. ApiServiceProvider conditional deny defaults и stateless TrustedContextResolver. | Consumer owns authentication/context/policy, handlers scope records. Preserve envelope data/error/meta; arbitrary body never authority. No new serializer/auth framework. |
| Optional auth | CoreAuthServiceProvider scoped AccessChecker/ScopeAvailability/ScopeResolver, external AzGuard bindings и optional permission layer | Preserve A09, inventory scope before change. Public consumer without auth package tested in P9; installing auth itself needs accepted immutable private source. CoreX не owns independent role engine. |
| Registry/extensions | EntityRegistry и 8 Extend classes существуют | Contribution через existing extenders/events/DI. No ninth extender/bus/universal CRUD; new mechanism only RFC. |
| Headless named surfaces | Targeted symbol-name scan не нашёл ожидаемых имён SavedView/PanelDescriptor/NotificationChannel/Recurrence/TranslationProvider/SearchProvider | Это ограниченный observation, **не доказательство отсутствия equivalent behavior**. P1/P2.1 semantic inventory решает keep versus delta; package placement не задаётся старым B38 именем. |

Наблюдения сверены с source tree текущей сессии; precise source SHA/commit anchors сохранены в
artifacts/P2-design/source-observations.json. Test paths существуют; тесты сейчас не запускались.

## Решения по группам

Каждая группа потребляет exact frozen mechanism atoms; новые имена PHP/портов/DDL/SDK **здесь
не вводятся**. Exact proposed bytes выходят из полного immutable дизайна owning phase, сводятся
P2.3 и проверяются P2.4 до adoption. Это specification dependency, не execution cycle.

| R | Minimal boundary / selection | Owner / destination | Non-CRM scenario | Failure / version invariant |
|:--|:--|:--|:--|:--|
| R01/R02/R33 | Existing storage/claim/lifecycle first; only proven adapter/maintenance gap | CoreX, P3 → P9/P10 | Document archive export/restore; lab isolated job-key reservation | Retained handle scope, traversal, same-key conflict/unknown commit, export-before-drop; physical/boxed explicit, schema gates |
| R03/R04/R05/R06/R07/R40/R43/R46 | Existing identity/context/client/profile; external authority/profile explicit | CoreX facade P2/P3; central/app P10 | Archive access revocation; lab workspace switch/read list | No ID merge; forged payload denies; ordering/fence/replay/cache not authorization; pending target receipt guards adoption; all DI/AP retained |
| R51/R52 | Existing auth/API policy seam; optional private adapter separately installed | AzGuard/consumer owns policy; P2/P9, invariant all phases | Archive staff access; lab equipment booking permission | Missing policy denies, request/job reset; no product role writer, exact old/new API+major gate |
| R09/R10 | Standalone workflow engine; existing Workflow extender with narrow trigger/wakeup binding | Standalone engine; CoreX P5, live wakeup P10 | Archive retention timer; lab maintenance reminder | Bound retries, durable single-fire/restart/unknown commit; boxed sweep independent of root/Redis; engine ref/license/source gate |
| R11/R12 | Typed fields/schema ops/import checkpoints; selected engine not duplicated | CoreX bindings P6; product mapping external | Archive metadata import/export; lab equipment catalogue import | Allowlisted metadata, checkpoint CAS/duplicate/partial failure, DDL recovery; no arbitrary SQL or CRM fields |
| R14 | Headless entity/panel/capability contribution through registry/extenders | CoreX P4; app UI external | Archive attachment capability; lab inspection panel descriptor | Disabled/absent provider and unknown entity deny; permission intersection; no UI kit scaffold |
| R15 | Storage-backed attachment/media reference and driver lifecycle | CoreX P4, storage P3 conditional | Archive scan conversion; lab inspection photograph | Usage-before-delete, wrong scope denies, conversion retry/dedup; no Vaulter assumptions |
| R16 | Recipient/channel/preference/delivery primitive only | CoreX P4; app templates/policy and standalone transport | Archive hold notice; lab reservation reminder | After-commit, dedup/retry/cancel; absent channel cannot promise delivery; scheduled digest consumes P5 only if selected |
| R17/R19 | Existing command/access DTOs, minimal proven task mechanism gap | CoreX P7; flex/tasks policy external | Archive review work item; lab calibration task | Trusted actor/entity refs, command idempotency and per-record access; collab/KPI/roles remain product-owned |
| R20 | Pure bounded recurrence/VO/library adapter; no calendar/task models | CoreX P7; engine/source G-DEPENDENCY | Archive periodic review; lab recurring maintenance | DST fold/gap, exclusions/leap/bounded window/occurrence identity; no live central dependency |
| R23/R24/R25 | Locale/decimal rate/reference provider primitives, KEEP MoneyCast | CoreX P7; datasets/providers+ops separately owned | Archive translated metadata; lab currencies and location lookup | Fallback/source/time/no float/stale/missing rates, no hot-path root lookup; real data license/provider receipt distinct |
| R28/R32 | Allowlisted versioned saved-query/search descriptors and provider capabilities | CoreX P4/P6; product Studio external | Archive owner saved filter; lab equipment search | Entity/field/operator/cursor limits, no arbitrary classes/SQL/connections; wrong scope/unknown field denied, after-commit rebuild |
| R08/R36/R50 | Registry/API introspection/invocation contribution; existing handlers | CoreX P8/P9; app MCP transport external | Archive export tool; lab availability tool | Unknown/disabled tool denies, trusted context/idempotency class, versioned descriptor; no auto-publication from docs map |
| R45/R47 | One compatible immutable cohort+source/test/install/deploy/consumer chain | P9/P10/P11 owning release/consumer | Cold install archive and lab fixtures without private auth | Local candidate ≠ release/hosted/live; immutable pins, exact compatibility/source closure; zero unresolved CoreX R for P11 |
| R13/R18/R21/R22/R26/R27/R29/R31/R34/R35/R37/R38/R39/R41/R42/R44 | Keep APP/EXTERNAL owner; share only exact mechanism atoms mapped above | Consumer/flex/standalone/control-plane, no CoreX domain scaffold | Independent archive/lab show shared primitive only | Original product AC retained externally; generic tests do not close product acceptance |
| R30/R48/R49 | Explicit historical exclusion, no scope import | Separate future approved programme | Not applicable: exclusion preserved | No fictitious CoreX work/milestone closure; current known CoreX gap cannot be renamed future |

## Future report schemas and proposal rules

Item specifications own the exact table schemas; scripts/validate-p2.py checks them read-only.
Catalogue format is `p2-contract-catalogue/1`; schemas/contract IDs are report identifiers,
не новая package API или promise нового semantic version.

- Contract IDs C-P2-001 onward refer to a distinct observable surface, many R may share one ID.
  Full exact old/new PHP/DTO/enum/error/wire/DDL/source contents reside in ## C-P2-NNN fenced sections
  of findings/P2-contract-proposals.md; identity wire sections remain in identity-profile report.
  No missing target FQN/Files/type/version/null/unknown-field choice in a selected internal proposal.
- New interface in src/Contracts only when existing port insufficient; data/VO classes final immutable,
  separate, promoted properties/strict types per AGENTS. Mutable nested objects are not made immutable
  by readonly alone. Runtime implementations hidden; contract/DTO/config changes all BC-scoped.
- no-delta = exact current surface and bounded receipt; proposal = exact candidate plus finite owner gate.
  External negotiated values remain unknown with owner/request/resume; no placeholder issuer accepted.
  Protection requests do not authorize old-contract break or schema/dependency/private mutation.
- Consumer cases are Given/Expected/negative/setup/receipt requirements, not synthetic passed results.
  At least two different domain scenarios with ≥1 non-CRM per generic surface; selected adapters
  run same meaningful invariants, explicitly document capability differences. P9 replays installed bytes.
- All R retained in intake/delta map/catalogue, excluded/foreign rows explicitly preserve owner.
  G-* and all DI/AP retained by pinning full gate ledger. P2 local catalogue GREEN can contain pending
  exact protected/external requests; P3 affected adoption and P10/P11 acceptance remain gated.

## Sealed inputs

P2.4 pins exactly these 12 plan-relative inputs **after** reports/request finalization:

| Path | Role |
|:--|:--|
| findings/P1-requirements-lock.md | Frozen parent/R/evidence scope |
| findings/P2-intake.md | Existing versus smallest gap |
| findings/P2-identity-profile.md | Exact identity wire proposal |
| findings/P2-auth-ownership.md | Trusted context/auth boundaries |
| findings/P2-delta-map.md | Same 52 R and contract IDs |
| findings/P2-contract-proposals.md | Exact candidate public/DTO/schema/source bytes |
| findings/P2-consumer-cases.md | Independent observable conformance scenarios |
| findings/P2-gate-requests.md | Exact protected owner requests |
| research/P2-contract-design.md | Stable format/ownership choices |
| research/03-gates.md | Complete G-*/34 DI/13 AP ledger |
| research/05-integrated-design.md | Compatible phase edges/versions/failure model |
| decisions/D7.md | P2 adopted design boundary |

Self/volatile state/journal/handoff are excluded. Whole-plan design finish separately pins all phase
specs/RAG/plan/D4 (and D6 policy through plan); P2 source observations cannot replace future intake.

## Cross-phase pre-mortem

| Failure | Mitigation / owning check |
|:--|:--|
| P1 qualification conflicts with proposed internal API | Reproduce exact changed requirement/anchor, revise affected immutable design and finish before runtime; P2.1/P2.3 owner |
| P2 waiting for runtime P3 while runtime P3 waits P2 | P2 reads finished P3–P8 specs, not runtime output; P2.4 edge review; full-plan-first prevents spec absence at execution |
| Unaccepted central target exposed as supported capability | P2 exact proposal + request; P3 registration requires scoped negotiated target receipt; deployed acceptance P10 separately |
| Cache/actor survives worker or forged input routes DB | Stateless resolver or scoped state/explicit lifecycle, payload negatives and request/job reset; P3/P8/P9 conformance |
| Retry of unknown external revoke duplicates side effect | Distinct uncertain outcome + reconciliation/fence/idempotency; exact wire owner P2.2/P3 and AP P10 |
| Saved/query/tool descriptor enables arbitrary scope/SQL/class | Allowlist/limits/trusted context/default deny; P4/P6/P8 negatives; consumer policy owner |
| Heavy/private integration accidentally required by public consumer | Optional boot/install no absent secrets/capability; G-DEPENDENCY/G-PRIVATE profile-separated P9 cold install |
| Green catalogue mistaken for release/live/product AC | Receipt classes and scoped adoption in every row; P9/P10/P11 gates and all original external atoms retained |

## P5 stable candidate binding — D10

P2.3 reads research/P5-wakeup-design.md before proposals: exact wakeup/1/routing-hint/1/engine-binding/1 ports, authoritative requests/intent SQL, optional external engine qualification, root revision/CAS and exact digest adapter/tick/window/key. Actual upstream names/FQN are source-owned qualification receipts, not guessed ABI. No reverse execution edge; P5 actual adoption still consumes P2 catalogue/gates.
