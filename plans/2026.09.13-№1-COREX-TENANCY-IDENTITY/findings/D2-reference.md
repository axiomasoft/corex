# D2 — primary-source verification

Checked 2026-09-13. External mechanisms below are verified in primary standards/framework sources. Numeric defaults, account policy, trigger-only semantics, FE-60 and lease/generation proof are local design decisions/inferences, not standardized deployment promises. No source proves the central application implements RFC8705 or that current monthly SLOs pass.

| Primary source | Version / exact anchor | Verified premise and limit |
|:--|:--|:--|
| [OAuth2 RFC6749](https://www.rfc-editor.org/rfc/rfc6749.html#section-4.4) | §§4.4–4.4.3 | client_credentials is a confidential-client service grant; client authentication required; refresh token SHOULD NOT be included. D2 makes absence mandatory. |
| [mTLS RFC8705](https://www.rfc-editor.org/rfc/rfc8705.html#section-2) | §§2,2.1.2,2.2.2,3.1,5,6.2–6.3 | Client authentication and token certificate binding are distinct. client_id identifies registration; PKI uses one expected DN/SAN, self-signed uses client jwks/jwks_uri with x5c. JWT cnf.x5t#S256 is base64url SHA256 of the DER certificate; resource compares actual TLS certificate. mTLS token alias may be published. Account scope and fingerprint retirement are additional D2 requirements; X.509 validity alone does not enforce overlap600. |
| [AS metadata RFC8414](https://www.rfc-editor.org/rfc/rfc8414.html#section-2) | §§2,3.1,3.3 | HTTPS issuer metadata publishes token_endpoint/jwks_uri and optionally registration_endpoint. Exact issuer equality required. OAuth well-known placement differs from OIDC for issuer paths; apex examples coincide. RFC metadata does not invent real URIs or require dynamic registration. |
| [OIDC Discovery1.0](https://openid.net/specs/openid-connect-discovery-1_0.html#ProviderConfig) | Errata set2, §§3,4.1,4.3 | Provider configuration obtained at issuer/.well-known/openid-configuration; returned issuer and trusted token issuer must match; jwks_uri comes from metadata. Historical CoreX RP discovery is not proof of service mTLS capability. |
| [JWT access profile RFC9068](https://www.rfc-editor.org/rfc/rfc9068.html#section-2.1) | §§2.1,2.2,3,4 | Signed access JWT with distinct access-token typ, RS256 supported, required iss/exp/aud/sub/client_id/iat/jti; service subject differs from acting human. Audience validation is required; resource indicator may determine audience. D2 fixes alg to RS256 and adds account binding. |
| [Resource indicators RFC8707](https://www.rfc-editor.org/rfc/rfc8707.html#section-2) | §2 | OAuth resource parameter identifies target resource using an absolute URI; audience/resource mapping is deployment policy. A planned API route is not the registered resource audience. |
| [RSA JWA RFC7518](https://www.rfc-editor.org/rfc/rfc7518.html#section-3.3) | §3.3 | RS256 is RSASSA-PKCS1-v1_5 with SHA256; RSA key size at least2048 bits. Key lifetime values are D2 policy. |
| [OIDC Core signing rollover](https://openid.net/specs/openid-connect-core-1_0.html#RotateSigKeys) | Errata set2, §10.1.1 | New public key publication/kid and refetch on unfamiliar kid support rollover; old verification keys retained for transition. Exact300/600s durations and CDN activation barrier are D2 decisions, not zero-latency guarantees in OIDC. |
| [SET RFC8417](https://www.rfc-editor.org/rfc/rfc8417.html#section-2.3) | §§2.2,2.3,4 | SET records events, separate token type; exp is NOT RECOMMENDED. D2 TTL300 trigger is explicitly a custom signed envelope, not asserted SET conformance. Current-state pull and fresh retry envelopes are D2 choices. |
| [Laravel authentication docs](https://github.com/laravel/docs/blob/13.x/authentication.md) | 13.x, Remembering Users / Invalidating Sessions on Other Devices | Remember token/provider and logoutOtherDevices are existing auth mechanisms; logoutOtherDevices requires current password and auth.session integration. They do not establish account revoke/generation handling or all-entry active checking. |
| [SessionGuard source](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/SessionGuard.php) | Laravel13.x, user/userFromRecaller/logout | user() caches a principal within a request and delegates session/recaller restoration to provider; no default is_active/lease check. Provider-selected live filtering must be qualified, not inferred from SessionGuard. |
| [DatabaseSessionHandler source](https://raw.githubusercontent.com/laravel/framework/12.x/src/Illuminate/Session/DatabaseSessionHandler.php) | Laravel12.x, write/performInsert/destroy | write inserts absent rows; DELETE can be followed by a late authenticated request INSERT. A final atomic credential-generation fence is a D2 requirement, not stock driver behavior. |
| [StartSession source](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Middleware/StartSession.php) | Laravel13.x, handleStatefulRequest/saveSession | Session save follows downstream request handling, creating a final-save race after middleware authorization. Exact deployed session handler integration remains unknown. |

Framework branch sources are changing external references, checked on this date; they are not immutable proof of the deployed version. Adoption must pin the actual framework/package source and recheck these methods. Local optional CoreX PrincipalUserProvider was inspected separately: live-user filtering and disabled remember retrieval are source observations only, not consumer install/runtime proof.

Retrieval: Context7 resolved Laravel and queried the official /laravel/docs corpus; load-bearing conclusions checked in official raw source/standards via web. One independent read-only protocol researcher supplied RFC/mathematical leads; the root independently opened primary references and checked the key source behavior. The resource indicator premise was additionally checked directly in RFC8707. Public deployment queries and source hashes are in D2-source-discovery.md, separate from protocol facts.

Derived conclusions: conservative lease anchored before read prevents pre-revoke snapshots extending beyond45s; JWT skew is unrelated; boolean-only state cannot preserve intervening revoke history; local expiry needs its own non-resetting credential epoch; late final writes require atomic fencing; FE-60 needs bounded local DB/worker availability; retry24h needs fresh300s envelopes. These are analyses of the accepted profile, not reported library/service capabilities.

## Qualification continuation: primary premises rechecked

2026-09-13. Direct primary-source retrieval was used; no second protocol interview. [Qualification packet](D2-qualification-packet.md) contains proposals/inferences, not observed deployment mechanisms.

| Primary source | Exact anchor / limit |
|:--|:--|
| [PostgreSQL18 row locking](https://www.postgresql.org/docs/18/explicit-locking.html#LOCKING-ROWS) | §13.3.2: FOR UPDATE serializes competing locked-row writers until transaction end; all final writers must participate. A lock on an absent row provides no pair fence, and ordinary nonlocking SELECT is not blocked. This supports the proposed same-transaction compare+write, not proof any candidate does it. |
| [PostgreSQL18 snapshot visibility](https://www.postgresql.org/docs/18/transaction-iso.html#XACT-READ-COMMITTED) | §13.2.1: READ COMMITTED SELECT sees commits before its query begins; a preopened REPEATABLE READ snapshot has a different boundary. This does not prove acknowledged-commit survival after failover or replica freshness. Actual deployed DB version is UNFILLED. |
| [PHP hrtime](https://www.php.net/manual/en/function.hrtime.php) | Description/return values: monotonic arbitrary origin, may fail;32-bit numeric mode can be float. No portable shared-host clock-domain or suspend guarantee. Proposal validates integer clock values and denies unqualified domains. |
| [Linux timekeeping](https://www.kernel.org/doc/html/latest/core-api/timekeeping.html) | Basic ktime interfaces: CLOCK_MONOTONIC stops during suspend; CLOCK_BOOTTIME includes suspend; CLOCK_REALTIME can move backwards. Platform adapter/drift/boot/process qualification remains UNFILLED; no FFI/extension installed or selected. |
| [Laravel13 DatabaseSessionHandler](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/DatabaseSessionHandler.php) | write()/performInsert()/performUpdate(): missing-row/exists=false path can INSERT; exists=true path updates and does not invariably reinsert a deleted row. The critical race includes absent/new/regenerated IDs and both final INSERT/UPDATE branches; no stock generation fence. |
| [Laravel13 EloquentUserProvider](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/EloquentUserProvider.php) | retrieveByToken()/updateRememberToken(): default token comparison and user save; no D2 generation/epoch. Consumer remember adapter must fence actual issuance and restoration. |
| [Laravel13 SessionGuard](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/SessionGuard.php) | user()/loginUsingId()/login()/updateSession()/queueRecallerCookie(): cached principal, direct login, regenerated session and recaller queue are separate consumer integration paths. |
| [Laravel13 StartSession](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Middleware/StartSession.php) | handleStatefulRequest()/saveSession(): save after downstream request, response cookie prepared before return; proposed denial integration must control response release and not assume a driver failure automatically clears cookies. |

Direct HTTPS200 raw-source byte receipts on observation date (changing13.x branch; hash pins observed content, not deployed framework):

~~~text
d8a2edbac91c7ab70e705c5d2ace67b5833fb58336d42c47d85a4d59e2116079  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/DatabaseSessionHandler.php
e14846c4d824ee4f412270c10a9f93a5d1d9d62b924d6ce01a5d8aa73bbc3264  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/SessionGuard.php
6c7268a984f2a4de0290cd69e4ca1d43e76a874c88b96c591dcd3134d9e2fc2c  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/EloquentUserProvider.php
ad054f3b21fcf67f6a088c13b4496c4e747db64acc38ccc2399323552793b5bc  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Middleware/StartSession.php
~~~

RFC8705, RFC9068 and RFC8414 were reopened directly in this continuation. Account_id/product field names, state enums, counter sizes, pair owner/priority expiry, restore witness, local credential DTOs and all AP receipts are **proposals/derived requirements** in the packet. They are not standard library APIs or discovered server behavior. The accepted numeric defaults remain D6 owner policy.

## New-receipts-only continuation

Recorded 2026-09-13T15:49:26.326Z. These are primary-source observations and derived acceptance gaps; no effective central/cloud deployment or AP execution was verified. See [gap receipt](D2-qualification-gap-receipt.md). Context7 resolved /laravel/docs and queried the13.x custom-session-driver topic; load-bearing behavior was checked against official source/manual pages.

| Primary source | Checked premise / qualification limit |
|:--|:--|
| [Laravel13 Store source](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Store.php) | save() calls handler->write() without inspecting its return. A false driver result alone does not stop normal save flow. Response-denial handling requires a consumer integration receipt. This is a newly recorded source premise, not deployed behavior. |
| [PHP SessionHandlerInterface](https://www.php.net/manual/en/class.sessionhandlerinterface.php) | Manual synopsis: open(string $path,string $name):bool; close():bool; read(string $id):string\|false; write(string $id,string $data):bool; destroy(string $id):bool; gc(int $max_lifetime):int\|false. It defines a handler surface, not an account/generation fence. Pin actual supported PHP/framework signatures at adoption; no CoreX public interface is approved here. |
| [PostgreSQL18 numeric types](https://www.postgresql.org/docs/18/datatype-numeric.html#DATATYPE-INT) | bigint maximum9223372036854775807 is below the proposed unsigned64 maximum18446744073709551615. Exact storage/increment/comparison mapping therefore needs approval; no column type is selected or changed. |
| [PostgreSQL18 row locks](https://www.postgresql.org/docs/18/explicit-locking.html#LOCKING-ROWS) | Competing row writers/lockers wait for transaction end. Derived limit: serialization alone is not an expiry-priority or bounded-holder timing proof; that operational obligation remains DI-22/31. |
| [Linux timekeeping](https://www.kernel.org/doc/html/latest/core-api/timekeeping.html) | CLOCK_MONOTONIC stops during suspend; CLOCK_BOOTTIME includes it. These facts do not qualify PHP clock binding, multi-process/domain equivalence, units or conservative drift/error. |
| [Laravel13 custom driver docs](https://github.com/laravel/docs/blob/13.x/session.md#adding-custom-session-drivers) | Supported extension is Session::extend returning a SessionHandlerInterface implementation; deployed consumer selection/provider order remains UNFILLED. |

Direct HTTPS200 retrieval receipts:

~~~text
61cf31f4f63716bb1331d9d46c8b92750f47532f69aa9176b08130baabceebb8  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Store.php  2026-09-13T15:48:06.944Z
d8a2edbac91c7ab70e705c5d2ace67b5833fb58336d42c47d85a4d59e2116079  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/DatabaseSessionHandler.php  2026-09-13T15:48:07.223Z
ad054f3b21fcf67f6a088c13b4496c4e747db64acc38ccc2399323552793b5bc  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Middleware/StartSession.php  2026-09-13T15:48:07.470Z
6c7268a984f2a4de0290cd69e4ca1d43e76a874c88b96c591dcd3134d9e2fc2c  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/EloquentUserProvider.php  2026-09-13T15:48:07.711Z
~~~

Store hash is newly recorded; the other three match the prior receipt. Branch URLs are mutable; hashes pin bytes observed from this retrieval, not immutable repository commit, installed package, sealed implementation or deployed artifact. Web-rendered branch line numbers can differ from a fresh raw retrieval; method names and byte receipts above are the source selectors used here.

Derived obligations in the gap receipt cover joint session+remember issuance final transaction, every represented guard/pair, final comparison/commit pauses, unsigned counter mapping, and an independent restore witness. They are proposals/proof requirements, not discovered APIs or measured mechanisms. No new standard numerical defaults or deployment URI/client/SAN/registration are asserted.

## Boundary verification continuation

Recorded 2026-09-13T15:55:53.445Z. Context7 /laravel/docs query covered13.x GET query arguments and session/remember integration; exact behavior checked in official raw framework sources plus primary RFC/PostgreSQL/PHP/Linux pages. **No central/cloud owning deployment or executed AP receipt obtained.** [Packet §8](D2-qualification-packet.md#8-exact-receipt-checks-for-the-remaining-boundaries) contains derived proof obligations, not approved APIs/mechanisms.

| Primary source / selector | Verified premise; qualification limit |
|:--|:--|
| [RFC9110 GET](https://www.rfc-editor.org/rfc/rfc9110.html#section-9.3.1) | §9.3.1: GET content has no generally defined semantics; intermediary support cannot be presumed from private agreement. Recommend explicit query transport for this proposed read; exact wire acceptance remains DI-07/34 UNFILLED. |
| [Laravel13 PendingRequest](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Http/Client/PendingRequest.php) | get(): second argument is the query option. This clarifies the unchanged candidate's GET mechanics; it does not supply accepted product/request_id/profile serialization or a deployed endpoint. |
| [PostgreSQL18 numeric types](https://www.postgresql.org/docs/18/datatype-numeric.html#DATATYPE-NUMERIC) | §8.1: bigint is signed64; numeric declared scale rounds fractional input and numeric supports special values. A scale-zero declaration alone cannot prove strict canonical unsigned input. Exact physical mapping/adapter/DDL remains UNFILLED. |
| [PHP integers](https://www.php.net/manual/en/language.types.integer.php) | Integer size is platform-dependent; unsigned integers are unsupported; integer overflow can produce float. Proposed unsigned64 decimal strings require qualified exact conversion/arithmetic, not implicit PHP int casts. |
| [PostgreSQL18 timeouts](https://www.postgresql.org/docs/18/runtime-config-client.html#RUNTIME-CONFIG-CLIENT-STATEMENT) | §19.11.2: lock_timeout bounds each lock acquisition wait, not a holder lifetime; idle_in_transaction_session_timeout targets idle open transactions; transaction_timeout limits transaction duration and excludes prepared transactions. Unspecified timeout units are milliseconds; defaults disable these timeouts. No value/configuration or priority guarantee selected. |
| [PostgreSQL18 row locks](https://www.postgresql.org/docs/18/explicit-locking.html#LOCKING-ROWS) | §13.3.2: conflicting lockers wait until transaction end. Derived expiry admission against a paused holder still needs qualified termination/rollback/commit timing and all-pair coverage. |
| [PHP hrtime](https://www.php.net/manual/en/function.hrtime.php) / [Linux timekeeping](https://www.kernel.org/doc/html/latest/core-api/timekeeping.html) | hrtime has arbitrary origin and may fail; numeric return can be float on32-bit. Linux monotonic excludes suspend and boottime includes it. These premises do not qualify the actual service/domain/tick/error mapping. |
| [Laravel13 SessionGuard](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/SessionGuard.php) / [EloquentUserProvider](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/EloquentUserProvider.php) | login() updates in-memory session, ensures a remember token and queues recaller before later session persistence; ensureRememberTokenIsSet()/cycleRememberToken() can invoke provider updateRememberToken(), which saves the user. Existing-token reuse is a distinct branch. Derived requirement: joint issuance needs owner-proven staging and one joint final transaction; stock ordering is inadequate proof. |
| [Laravel13 DatabaseSessionHandler](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/DatabaseSessionHandler.php) | write() does not inspect performUpdate()/performInsert() outcomes before reporting success; performInsert() catches QueryException and attempts UPDATE. addUserInformation()/userId() use the container Guard's ID. Derived requirements: typed failure propagation and secondary-guard cleanup cannot rely on the stock return or lone user_id. |
| [Laravel13 Store](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Store.php) / [StartSession](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Middleware/StartSession.php) | Previously observed save-return and late-save/cookie ordering unchanged in the byte receipts below. No consumer registration/response-denial guarantee inferred. |

Direct HTTPS200 raw retrievals (TLS verification retained):

~~~text
48adb87ac206cc08a883a51581ced833a8496cdaf5d4da35c5ec17b27a6e41ee  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Http/Client/PendingRequest.php  2026-09-13T15:54:52.268Z
e14846c4d824ee4f412270c10a9f93a5d1d9d62b924d6ce01a5d8aa73bbc3264  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/SessionGuard.php  2026-09-13T15:54:52.534Z
d8a2edbac91c7ab70e705c5d2ace67b5833fb58336d42c47d85a4d59e2116079  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/DatabaseSessionHandler.php  2026-09-13T15:54:52.827Z
61cf31f4f63716bb1331d9d46c8b92750f47532f69aa9176b08130baabceebb8  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Store.php  2026-09-13T15:54:53.111Z
ad054f3b21fcf67f6a088c13b4496c4e747db64acc38ccc2399323552793b5bc  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Session/Middleware/StartSession.php  2026-09-13T15:54:53.366Z
6c7268a984f2a4de0290cd69e4ca1d43e76a874c88b96c591dcd3134d9e2fc2c  https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/EloquentUserProvider.php  2026-09-13T15:54:53.650Z
~~~

PendingRequest bytes are newly recorded. The other five SHA-256 values match prior observations; inspecting additional methods is a premise refinement, not a new owning runtime receipt. Mutable branch URLs/hash-observed bytes do not pin a deployed release or sealed accepted API. Actual PHP/framework/DB/platform versions and configuration remain owning inputs UNFILLED.

Derived acceptance obligations include exact counter roundtrip/rejection at range boundaries; elapsed-upper-bound plus remaining comparison→COMMIT bound strictly below45s or qualified equivalent; priority expiry against paused DB holders; witness acknowledgment covering issued/allocated and committed authority; joint session/remember durable writes; every-guard/pair cleanup; and typed denial through Store/response/cookie queues and prepared headers. No theorem here claims production capability. D6 defaults remain unchanged; DI/AP disposition stays0 effective DI and0 executed AP. No failed central discovery was repeated.
