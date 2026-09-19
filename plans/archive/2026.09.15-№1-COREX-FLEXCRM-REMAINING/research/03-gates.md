# Gate ledger: acceptance не заменяется подготовкой

RAG:— Все состояния ниже — квалификация **доступных evidence**, не утверждение об отсутствии
внешней системы. Никакой endpoint/credential/deployed registration не выдуман. G-* обозначают
proof obligations, а не выданные разрешения. Exact authority оформляется Sol при детализации
защищённого item после подготовки reviewable result. Current design её не выдаёт.

## Обязательные gates

| Gate | Владелец | Required receipt | Точное условие возобновления |
|:--|:--|:--|:--|
| G-SOURCES | CoreX maintainer + FlexCRM plan owner | Sealed requirements-lock R/AC; source repository/commit/path/blob+sha256; working delta disposition | Все использованные рабочие изменения получили source-owner binding или оставлены явным proposal; ни одного потерянного R |
| G-API | CoreX contract/release owner | Exact old/new signatures/DTO/enum/error/version, BC report, per-package CHANGELOG and major choice under AGENTS | Подписан exact digest-bound контракт для фактической delta; accepted old Contracts не перепроектируются |
| G-SCHEMA | Owning schema owner | Exact old/new DDL/hash, migration/rollback/export policy, approved physical mapping | Scoped approval совпадает с DDL bytes; FlexCRM 0.2.2 не даёт blanket authority на CoreX/central таблицы |
| G-DEPENDENCY | Package/source owner | License/source/channel/ref/checksum, compatibility and dependency closure | Доступен immutable источник и разрешена точная дельта; historical latest/dev alias не подходит |
| G-STORAGE | CoreX adapter + consumer schema/ops | Driver conformance, actual physical store/schema, retention/transaction/restore mapping | Отдельно принят local library adapter и реальный выбранный provider/store; fake S3 не реальный S3 |
| G-CENTRAL | Central FLEX API/AS/ledger/PKI/edge/events owners | DI-01–20/34 exact source/profile/issuer/resource/registration/edge/store receipts | Новый immutable receipt изменяет конкретную DI; квалифицировать только covered subconditions |
| G-CLOUD | Consumer app/auth/schema/ops + control-plane owner | DI-21–33 deployed entries, guards, clock, scheduler, witness, pair store/response receipts; B33 real ops outcomes | Named environment/release/registration и результат совпадают с принятым profile/source; local profile не deployment |
| G-TIMING | Central + consumer ops | AP-07/11/12; DI-31/32 raw measurements/population/error bounds/window | Закрыта реальная required window и все tail/missing observations учтены; no-data не GREEN |
| G-RUNNER | CoreX CI/release maintainer | Actual hosted run/job URL, SHA, matrix/runtime/config hash, complete result | Required jobs прошли на exact candidate; workflow файл и local logs не runner receipt |
| G-PRIVATE | AzGuard/source owner + CoreX release maintainer | Approved immutable private channel/ref + narrowly scoped read + cold private install receipt | Только если private auth входит в chosen profile; public set проверен без него, secrets не в артефактах |
| G-RELEASE | CoreX release owner | Sealed source/subtree/dist digest, public refs/artifact bytes, authorized publication receipt, changelog/BC, cold update/fresh install | Полный chosen cohort опубликован/доставлен через принятый канал; совпали release, source и installed bytes; E5 не покрывает новый cohort |
| G-CONSUMER | FlexCRM reconciliation owner | Same released cohort, app commit/locks/config/installed tests/result/deviations, signed covered R list | У каждого CoreX R закрыты source/test/release/consumer receipts и applicable DI/AP; 0 CoreX gaps |

Нельзя перепутать local-candidate install, public released install, hosted CI, deployed integration,
production acceptance и consumer bookkeeping. Они имеют разные owners и receipts. Архивное
«audit GREEN» не заменяет реальную acceptance; новый phase audit требует отдельного scoped consent.

## Полный DI ledger — 34 условия

Следующая таблица взята из архивного TENANCY-IDENTITY D2 gap receipt. Статус UNFILLED сохраняется
как последний квалифицированный; будущая P10 проверяет новые receipts, а не заново ищет секреты.


Required receipt scope is retained from packet §1. No existing DI was closed by source inspection; observed/proposed content remains in that packet.

| ID | Effective status | Missing input | Owner / required receipt |
|:--|:--|:--|:--|
| DI-01 | **UNFILLED** | Current central source/artifact digest, deployed release and negotiated profile | Central Flex: immutable source selector+SHA, artifact/release-to-environment receipt and protocol version |
| DI-02 | **UNFILLED** | Byte-exact effective issuer | Central AS: TLS-verified metadata issuer, config/source selector and release binding |
| DI-03 | **UNFILLED** | Verified metadata URL/body/hash/time/trust chain | Central AS: successful HTTPS retrieval with issuer equality and deployed release; never disable TLS verification |
| DI-04 | **UNFILLED** | Effective jwks_uri, kids/purpose, cache/edge behavior | Central AS: emitted metadata+JWKS digest, key purpose and all-edge cache/activation receipt |
| DI-05 | **UNFILLED** | Effective endpoint/alias, client_credentials+tls_client_auth+bound token support | Central AS: metadata and immutable handler/negative integration proof, not Passport capability inference |
| DI-06 | **UNFILLED** | Resource URI and exact audience/resource mapping | Resource owner: registered absolute URI, allowed audience shape and AS/resource mapping receipt |
| DI-07 | **UNFILLED** | Effective read URI/method/accepted schema and no-cache contract | Ledger/API: immutable handler+protocol schema+read-start/cache/failover test receipt |
| DI-08 | **UNFILLED** | Effective revoke route/idempotency/errors | Ledger/API: accepted route/handler/schema, same-key conflict and uncertain-timeout receipt |
| DI-09 | **UNFILLED** | Actual client_id/product/account/capability registration | AS/ops: sanitized registration record digest and AS+resource exact one-account scope negative proof |
| DI-10 | **UNFILLED** | Accepted token field names/types/sub namespace/capability policy | AS/resource: wire-profile hash, registration mapping and forged actor/wrong-account rejection receipt |
| DI-11 | **UNFILLED** | Actual management URI/path/method/record/operator | AS/PKI: existing UI/API/runbook source selector+sanitized record digest+operator scope; no writes in this task |
| DI-12 | **UNFILLED** | CA/trust digest, actual SAN and custody references | PKI/ops: trust bundle digest+registered public subject+secret-store reference, no key/token bytes |
| DI-13 | **UNFILLED** | Registry and distributed activation/retirement/open-connection mechanism | AS/resource/edge: old/new fingerprints and timed per-request rejection across live/resumed TLS receipts |
| DI-14 | **UNFILLED** | Terminators/backend identity/header stripping | Edge/ops: immutable edge config selector and forged-forward-header negative test |
| DI-15 | **UNFILLED** | Enrollment authority/scheduler/overlap epoch/emergency runbook | PKI/ops: accepted runbook path+renewal/failure/emergency and activation receipts |
| DI-16 | **UNFILLED** | Signing custody/purpose/jobs/all-edge activation | AS/event signer: public key refs, last-signature retention and publication/refetch/compromise receipt |
| DI-17 | **UNFILLED** | Recipient URI/transport, accepted iss/aud/key/kind/reason schema | Events/consumer: endpoint/queue binding+wire schema hash+signature/routing negative receipt |
| DI-18 | **UNFILLED** | Durable storage/schedule/retention/capacity | Events/consumer: commit crash+failed ack/dedup+fresh retry envelope test and storage/retention approval |
| DI-19 | **UNFILLED** | Actual failover/read routing/cache prohibition | Ledger: primary/failover configuration selector+before-read commit visibility and stale replica/cache rejection proof |
| DI-20 | **UNFILLED** | Ledger durable storage/wire adoption and nonrollback restore witness | Ledger/consumer: schema/equivalent persistence approval, concurrent revoke/regrant+restore high-watermark receipt |
| DI-21 | **UNFILLED** | Effective management-only caller/projection deployment context | CoreX/consumer: entrypoint-to-root authority binding and tenant root-write denial receipt |
| DI-22 | **UNFILLED** | Real distributed store/timeouts/takeover | Consumer/ops: exact store/release binding, expired holder and priority expiry/takeover race receipt |
| DI-23 | **UNFILLED** | Qualified elapsed service/boot/process domains/drift and loss handling | Consumer/ops: implementation+platform digest, suspend/restart/cross-host/rollback and conservative elapsed error receipts |
| DI-24 | **UNFILLED** | Pair inventory/scheduler/capacity/budgets | Central/consumer ops: complete inventory+max real admission interval and cache-miss/network/DB/backlog measurements |
| DI-25 | **UNFILLED** | Effective D2 cloud resolver/driver/table/auth payload/two-DB fixture | Consumer/schema: versioned session registration/config selector and isolated A/B connection receipt |
| DI-26 | **UNFILLED** | Physical durable store/transaction/retention/restore adoption | Consumer/schema: approved storage mapping+atomicity+nonrollback restore and cleanup crash receipts |
| DI-27 | **UNFILLED** | Effective cloud guards/providers/SSO/login generation binding | Consumer auth: immutable auth/config/callback Files and every issuance/restoration negative receipt |
| DI-28 | **UNFILLED** | Complete deployed route/guard/job/ws/PAT/OAuth decision inventory | Consumer app/auth: route+middleware+job/channel manifest and per-decision enforcement receipt; source absence is not N/A proof |
| DI-29 | **UNFILLED** | Actual cloud remember support and all-guard issuance/restore/invalidation binding | Consumer auth: explicit enabled/unsupported receipt and old recaller/late issuance proof, without inferring cloud DDL |
| DI-30 | **UNFILLED** | Exact custom driver/provider/response integration | Consumer session/auth: Files+registration/source digest, final INSERT/UPDATE and remember race receipt |
| DI-31 | **UNFILLED** | Measured local expiry admission/DB recovery+commit behavior | Consumer ops: timed boundary/overbudget/failover/backlog fault packet, physical cleanup vs deny reported separately |
| DI-32 | **UNFILLED** | Instrumentation/time error/current month measurements | Central/consumer ops: all-commits population+missing/late inclusion, timestamp error and monthly/tail/no-data receipts |
| DI-33 | **UNFILLED** | Cloud removal permission adapter and central audit schema binding | Consumer/central audit: exact Action/policy+deny fixture and claimed-vs-authenticated actor record receipt |
| DI-34 | **UNFILLED** | Accepted API/version/BC/storage/dependency delta and sealed source carriers | CoreX contract/schema/source owners: exact signature/file/profile hashes, major/version decision, CHANGELOG/storage approval, sealed source receipt; no approval inferred |


## Полный AP ledger — 13 сценариев

Источник: TENANCY-IDENTITY P7.4. Контрольные числа — требования выбранного профиля,
не измеренные характеристики. CoreX generic API не hardcode-ит эти параметры для всех потребителей.

| AP | Required executed scenario / mandatory subconditions | Owning operator | Status |
|:--|:--|:--|:--:|
| AP-01 | Missing/invited/pending/blocked/revoked/deleted membership and missing/deleted user deny; shared phone/email never merges UUID; tenant sync cannot write root bookkeeping. | CoreX + consumer | **UNFILLED** |
| AP-02 | Cross-identity certificate/token, product/client/resource/claim/cnf failures, bearer-only and forged actor/header deny; retired fingerprint rejects on every AS/resource edge, including open and resumed TLS after 600s. | AS/resource/edge | **UNFILLED** |
| AP-03 | Unknown-kid bounded refetch; new key only after all-edge publication plus 300s; old key retention for 600s after last signature; current-key retry and compromise deny. | AS/event signer | **UNFILLED** |
| AP-04 | Invalid/expired trigger makes no projection write; signed stale trigger only pulls; failed refresh survives ack/crash; retry has same logical event and new delivery ID/time/signature. | Events + consumer | **UNFILLED** |
| AP-05 | Revoke committed before read start is visible through primary/failover; stale replica/cache/304 deny; revoke→regrant history preserves generation; replay is idempotent and regression/conflict denies. | Ledger | **UNFILLED** |
| AP-06 | A is paused after admission, expiry/takeover advances F, B commits deny/new snapshot, then A cannot apply/publish/resume; cover delayed publication and same-R renewal. | Consumer | **UNFILLED** |
| AP-07 | Pull/apply pauses still expire at `t0+45` without grace; outage denies new authorization by `tr+60`; suspend, restart, store loss and cross-host clock-domain cases deny. | Consumer + ops | **UNFILLED** |
| AP-08 | Pause authenticated final INSERT and UPDATE before save; revoke, cleanup, regrant/new login, then resumed old save cannot resurrect authentication/token; reverse lock order and unrelated B are covered. | Session/auth | **UNFILLED** |
| AP-09 | Old recaller, late remember issuance, pre-revoke callback/continuation/hydration/login/impersonation/PAT/OAuth all deny; every browser/API/channel/message/job/command entrance checks live lease+G/E; unsupported paths have explicit no-route/no-registration proof. | Consumer app/auth | **UNFILLED** |
| AP-10 | Restore old central+tenant backup while independent witness remains outside rollback set; R/G/E/F cannot decrease or resurrect credentials/bindings; authentication stays closed until fresh pull and cleanup. | Ledger + consumer/schema/ops | **UNFILLED** |
| AP-11 | FE-60: worst lease expiry `tr+45`; scan/priority admission total at most 5s; DB recovery, all cleanup and commit/retries total at most 10s; physical cleanup by `tr+60`. Over-budget result separately reports cleanup breach while authorization remains denied. | Consumer ops | **UNFILLED** |
| AP-12 | Every relevant pair has actual read starts within 30s, including lock/backlog; pull/apply include cold auth/JWKS/TLS misses and DB; monthly all-committed-event p99.9 at most 10s includes late/missing observations, timestamp error and per-account tails. Empty population is explicitly no-data. | Central + consumer ops | **UNFILLED** |
| AP-13 | Published baseline→proposed signatures/DTOs, owner-selected major and CHANGELOG, approved logical-to-physical mapping, exact dependency delta/no-delta, and sealed immutable source carriers. | CoreX contract/release owners | **UNFILLED** |


## Единый receipt envelope

Каждый receipt: requirement/subcondition IDs; accountable accepting actor; owning repo/commit/tree;
artifact/config/profile/schema/fixture SHA-256; environment/deployed release/registration; exact
command+cwd+runtime; start/end; log URI+digest; outcome and covered/uncovered conditions. Timing
добавляет units/clock domain/error bounds/population/window/late/missing/account tails.
Никаких private keys/tokens в evidence. Одно доказательство может покрыть несколько rows только
при явном перечислении subconditions и одинаковой identity. Изменившийся input инвалидирует
затронутые доказательства, не всю историю. Envelope не является receipt пока поля не заполнены
фактическими источниками.

## Условия, сохраняемые буквально

- FE-60: lease ≤45s, expiry admission ≤5s, recovery/cleanup/commit ≤10s; deny и physical cleanup — разные outcomes.
- Pull admission ≤30s; monthly all-committed-event p99.9 ≤10s, без исключения late/missing.
- Key/certificate rotation: all-edge activation/retirement, 300s propagation и 600s applicable retention/retirement из AP-02/03.
- R/G/E/F/AF: unsigned64 canonical roundtrip/CAS/overflow; restore witness вне rollback set; no stale positive lease.
- B33 ops: account ready+valid TLS, compensation/retry, export-before-drop, real provider/IAM limits и backup/restore/move; не локальный clone fixture.

Если receipt отсутствует: сохранить named owner, exact required receipt и resume predicate;
продолжать независимые готовые фазы. Не завершать P10/P11 фиксацией одного NO-GO-документа.
