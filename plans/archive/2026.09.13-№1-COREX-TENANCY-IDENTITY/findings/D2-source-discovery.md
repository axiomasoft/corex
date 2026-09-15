# D2 — source discovery receipt (2026-09-13)

Read-only search. Brain path resolved through the local topology map; project records resolved through the vault catalog. Foreign originals were not edited or synced. Paths below are portable provenance relative to the resolved vault, not deployment configuration. Observation time: 2026-09-13 14:21 UTC; CoreX candidate HEAD: 5e396e7210fc17e1dc0e6917e70bef10b74becab.

## Source precedence and immutable observations

- repo:docs/03_Dev/ADR/ADR-017 Central IdP.md — SHA-256 afa228e4bac135051169c7758b702711bb14c2495247c24969519c6acba99d16
- vault:05-Projects/01-Products/Flex/plans/2026.07.17-FLEX/plan.md — SHA-256 bc9fadf96bb35e81d72d29606e215d55773d36d9c93eae317cbce1d38e054de4
- vault:05-Projects/01-Products/Flex/plans/2026.07.17-FLEX/research/03-p2-oidc-as.md — SHA-256 0659b9d8583c37be4fe4f9b6c9c7de9745660eada2dafbca3c3a60bc9f00ebf4
- vault:05-Projects/01-Products/Flex/plans/2026.07.17-FLEX/phases/P2.md — SHA-256 d93c1ee058a48b5cc737b3e705b354a88f39f24b74dd4adde7b7fc9b4472b80e
- vault:05-Projects/01-Products/Flex/plans/2026.07.17-FLEX/phases/P3.md — SHA-256 d7677c2525ca52f209ff3723e40304ba90d8bcbb5d16348447e242f5238d81f8
- vault:05-Projects/01-Products/Flex/plans/2026.07.17-FLEX/handoff.md — SHA-256 e02168a95255eb04f5dd0cdbc080236f0323901e578f03e9f0e9b4ed523532e9
- vault:05-Projects/01-Products/Flex/Flex.md — SHA-256 990ed7839ba0dc78de2c42d61abb5cbca92edc9aa5e1725c5d8489abc777fbfe

CoreX ADR-017 still names id.flex.ru. The newer owning Flex plan explicitly resolves that placeholder in D13; D33 renames historical FLEXID to FLEX. The project note still calls issuer binding pending, so it is older/less precise than its own plan D13. Neither a design row nor a purchased domain proves deployment.

## Exact owning-plan excerpts

Source: vault:05-Projects/01-Products/Flex/plans/2026.07.17-FLEX/plan.md, whole-file hash above. Line numbers at observation time.

L95: | D2 | 2026-07-17 | issuer-host (наследует Q-6 оркестра): рекомендация — ЕДИНОЕ Laravel-приложение на `flexl.ru`, issuer=`flexl.ru` (портал ЛК + OIDC AS в одном приложении MVP — проще деплой); `id.flexl.ru` — будущий split при масштабе. Окончательный binding — детализация P2. RAG:— (repo-grounded: оркестр §Обсуждение Q-6; ADR-017 исходно `id.flex.ru`, D13 отменил placeholder) | MVP проще одним приложением; split AS — оптимизация масштаба, не входной барьер |

L106: | D13 | 2026-07-17 | issuer-host РЕШЁН (OQ-1 → Resolved, владелец): **единое приложение на `flexl.ru`, issuer=`https://flexl.ru`** (рекомендация D2). Портал ЛК (P3) + OIDC AS (P2) в одном Laravel-приложении, один деплой/секрет-контур; endpoints `/oauth/*`, discovery `/.well-known/openid-configuration`, cookie IdP-сессии на apex. `id.flexl.ru` (split) — post-MVP при масштабе. issuer — host-константа в config (не хардкод). RAG:— (repo-grounded: владелец AskUserQuestion 2026-07-17; D2/оркестр Q-6; research/03 §2) | MVP проще одним приложением; split AS — оптимизация масштаба, не входной барьер |

L113: | D20 | 2026-07-17 | Граница `CentralApiClient` (Q66): он **вызывается ИЗ tenant-приложения** (corex/tenancy, tenant удаляет сотрудника → зовёт central) — **Flex его НЕ реализует**; Flex даёт ПРИНИМАЮЩИЙ central-API эндпоинт (`POST /api/central/memberships/revoke`, P3.4), который CentralApiClient дёргает. Транспорт: first-party OAuth `client_credentials` (продуктовый OIDC-клиент, `root_oauth_clients.product`, P2) + acting-identity в теле (`acting_identity_id`); полномочие инициатора (`wsp.member.remove`) — tenant-сторона ДО вызова (НЕ кросс-БД JOIN, К1). RAG:— (repo-grounded: B-34 §4.2 Q66 L432-454 «вызывается ИЗ tenant-приложения»; §3.10 `wsp.member.remove`) | Смешать client (tenant) и endpoint (central) = реализовать tenant-код в central-app; К1 требует проверку полномочий tenant-стороной |

L118: | D25 | 2026-07-17 | Reconcile acceptance-критерия отзыва: скелет-DoR «revoke→kill ≤60с» — **ложная формулировка** (спутала auth-code Redis-TTL=60с с kill-моделью). Канон ADR-017: (a) auth-code+PKCE Redis TTL 60с (L28) = время жизни КОДА обмена, не kill; (b) global logout НЕ мгновенный tenant-side — осознанная задержка ≤12ч (L36/L52, не баг); (c) немедленный рычаг — SSO-block (revoke→`/authorize` 403 сразу, D22). Корректные критерии: **A6** (revoke→403 немедленно) + **A7** (каскад refresh — новый access не сминтить); остаточный access-JWT/tenant-сессия по TTL (документируется в deployment.md, НЕ «kill ≤60с»). RAG:— (repo-grounded: ADR-017 L28/L36/L52; D22; research/05 §5) | Скелет спутал TTL-кода с kill-доступа; kill-модель канона = немедленный SSO-block на выдаче + осознанный TTL-хвост; ложный «≤60с» дал бы недостижимый acceptance |

L124: | D31 | 2026-07-18 | **Домены куплены владельцем (факт, не решение):** `flexl.ru` — apex портала+AS (подтверждает D13, теперь реально принадлежит владельцу, не только план); `flexcrm.ru` — apex продукта FlexCRM (уже использовался как wildcard-паттерн `*.flexcrm.ru` в P2.1/P2.2, D26 — теперь подтверждён как реальный домен, не гипотетический пример). Конкретизирует `product→apex-домен map` P3.4/D27 (был абстрактным «из config», конкретное MVP-значение — `flexcrm => flexcrm.ru`); правок Scope/Files нет — только конкретное значение конфига. RAG:— (repo-grounded: владелец, регистрация доменов 2026-07-18; D13/D2 issuer-host; phases/P2.md P2.1 wildcard-паттерн `*.flexcrm.ru`) | Абстрактный `product-apex из config` в P3.4/D27 без реального значения оставлял exec-сессии гадать на MVP-конфиге; теперь конкретное значение — не изобретается на exec |

L126: | D33 | 2026-07-27 | **Plan-ID переименован `2026.07.17-FLEXID` → `2026.07.17-FLEX`; понятие «FlexID» упразднено** (директива владельца 2026-07-27: «flexid больше нет такого понятия… есть портал flexl.ru (Flex проект) — узел учётных записей экосистемы»). Решение уровня программы принято оркестром — `2026.07.17-AXIOMA-ORCHESTRA` D28 (`supersedes` D20(а) в части «ID не переименовывается»); здесь — зеркалирование и каскад внутри плана: `git mv` папки + все внутренние ссылки (Meta, Execution Rules, launch-block'и, roadmap, handoff, фазы). Исторические D-записи и Update Log этого плана НЕ переписаны там, где называют прежний ID как факт истории. **Consequences:** ссылки на план в git-истории, в архиве и в исторических строках соседних планов указывают на прежний ID — при поиске провенанса нужно знать обе формы. RAG:— (repo-grounded: оркестр `plan.md` §5 D28) | Имя продукта — Flex; ID продолжал вводить в оборот упразднённое понятие. Переименование сделано до старта exec-волны и до появления внешних потребителей ID |

## Endpoint observations

Source: owning research/03-p2-oidc-as.md §2: issuer https://flexl.ru; authorize https://flexl.ru/oauth/authorize; token https://flexl.ru/oauth/token; discovery https://flexl.ru/.well-known/openid-configuration. JWKS explicitly offers two alternatives, /.well-known/jwks.json or /oauth/jwks; no selected emitted jwks_uri was found.

Owning phases/P2.md L222 requires a discovery test with issuer=https://flexl.ru. phases/P3.md L350 and D20 name POST /api/central/memberships/revoke; P3.2 names GET /api/me/accounts. These are planned routes, not inspected live handlers. No authoritative membership-state route, OAuth resource/audience URI, event recipient URI, mTLS endpoint alias or certificate registration endpoint/record was found in the inspected owning sources.

The CoreX P1.2 candidate and existing dirty HttpCentralApiClient instead use /api/memberships/revoke and /api/memberships/state. They are proposal/implementation candidates, not discovery evidence; revoke path disagrees with the owning plan. The historical accounts route under id.flex.ru must not override the later issuer-host decision.

## Availability and public lookup

Catalog/control note/owning handoff point at the local projects/flex repository and GitHub axiomasoft/flex. The catalogued local code directory is absent (ENOENT); current central source commit and deployed handlers could not be inspected. No authenticated private source access was attempted. Owning handoff records historical bootstrap commits aed0965/b6f1e3a; they are not current protocol/deployment proof.

Public web search for id.flex.ru OIDC/OAuth/JWKS and FLEXID certificate/OAuth returned no results. Web open of id.flex.ru root and metadata, and flexl.ru metadata/JWKS, returned tool safety/access errors, not HTTP metadata. Public GitHub web open yielded no readable source, not proof of repository visibility.

Read-only HTTPS GET with standard certificate validation from the available Node runtime:

- https://flexl.ru/.well-known/openid-configuration — ERR_TLS_CERT_ALTNAME_INVALID (fetch failed); no verified response body.
- https://flexl.ru/.well-known/oauth-authorization-server — ERR_TLS_CERT_ALTNAME_INVALID (fetch failed); no verified response body.
- https://flexl.ru/.well-known/jwks.json — ERR_TLS_CERT_ALTNAME_INVALID (fetch failed); no verified response body.

TLS verification was not disabled. ERR_TLS_CERT_ALTNAME_INVALID is this environment's observed transport failure; it does not establish the origin's deployment state or identify a certificate registration service. No JWKS value, key, metadata document or live certificate registration can be claimed discovered.

## Search coverage and honest gaps

Inspected local CoreX ADR-017/009, tenancy config and OIDC/central/projector/provider/middleware candidates, portable B34 and P1.1/P1.2 dossiers; linked FlexCRM D28, B34/B37 and T011; owning Flex control note, plan D13/D20/D25/D31/D33, P2/P3/P4, research/03 and handoff. Targeted issuer/jwks/mTLS/registration/resource searches found no certificate registry. No blanket search of secret .env files or credential material occurred.

Result: actual selected issuer URI recovered from the owner's recorded decision; effective deployment issuer, emitted metadata/JWKS/mTLS endpoints, authoritative resource URI and certificate registration remain exact unresolved inputs in D2-membership-profile.md §8. Do not invent them from Passport conventions or test URLs.

## Qualification continuation — linked consumer source receipt

2026-09-13. Independent read-only discovery resolved a readable FlexCRM checkout; central Flex checkout remains absent. Consumer HEAD 137aec094aaca17ad435d02e9e6de7160a4f5a7b; working tree already dirty. Exact method/File inventory and independently reread source SHA-256 receipts are in [D2-qualification-packet.md §5](D2-qualification-packet.md#5-consumer-files-and-exact-source-bindings). Source paths are consumer-relative provenance, not deployment configuration.

Observed consumer composer requires corex/core+tasks and flexcrm/auth, not corex/tenancy. Its web/session Eloquent provider, database session defaults, Fortify/Sanctum/Filament and collaboration server are readable candidates. SessionTenantContextResolver returns boxed:true; this inventory cannot qualify cloud account context or D2 installation. Its existing users migration includes remember_token, so optional CoreX PrincipalUserProvider's disabled remember behavior cannot be generalized to this consumer. Effective cloud guard/provider/session connection, remember status, complete entrances and final-write registrations remain UNFILLED with owners/receipts in the packet.

No new TLS-verified central metadata/JWKS/registration was obtained. Previous lookup failure is preserved as an observation; no TLS bypass, authenticated private source access, installs, foreign source writes, deployment or registration writes occurred.

## New-receipts-only qualification receipt

Recorded 2026-09-13T15:49:26.326Z; CoreX HEAD 5e396e7210fc17e1dc0e6917e70bef10b74becab. [Gap receipt](D2-qualification-gap-receipt.md) enumerates the inspected source/artifact scope, current candidate SHA-256 observations and exact DI/AP dispositions. Current source byte observations renew candidate provenance; none proves API adoption or deployed central/cloud behavior. Existing storage/fake-cloud/signup proofs are outside membership acceptance scope; older launch receipts do not supersede D6.

New primary Store::save() byte receipt and supporting interface/numeric/locking/clock premises are in [D2-reference.md](D2-reference.md#new-receipts-only-continuation). Other three raw framework byte hashes match the earlier observations. **No new effective central/cloud qualification or AP execution receipt was obtained.** All unavailable values remain UNFILLED with packet/gap-receipt owners. Boxed FlexCRM discovery is preserved without promotion to cloud consumer integration.

No new owning central source was supplied; prior central absence, public lookup and certificate-name failures were not probed again. No central metadata/JWKS request, public deployment/repository search, private source access/install, TLS bypass, foreign write, deployment or registration write occurred. This does not assert that those sources are globally absent or that the deployment state is unchanged; it records only the available evidence and this turn's scope.

## Boundary qualification continuation — no new owning source

Recorded 2026-09-13T15:55:53.445Z. Read-only inventory enumerated112 plan paths through rg --files --hidden (110 outside two .plan-cache paths). Later document carriers were inspected with exact observation hashes:

~~~text
fbc9d6abddaeec6c982fbad01f342aa38370797ba0059ddad2295c704911f11a  plan:bundles/P4.1.json
6e3d09da5a751371e474988d31441f8a578f2fc768fc4b8ff6c9bea1dd964576  plan:bundles/P4.2.json
9b4aa3ff55009dc0539ac93364f3a2ded91015a89bf308ae3c5f5f4130c2ab09  plan:findings/D2-design-validation.md
~~~

These hashes pin pre-edit document/bundle observations; bundles are source packaging only. They contain no new central/cloud owner acceptance/deployed release/registration/witness or executed membership receipt. D2 files written after the prior gap observation time belong to that earlier document continuation, not independently owned operational evidence. mtime alone does not prove novelty or acceptance. Six prior candidate source SHA-256 values match; CHANGELOG Unreleased and local tag names remain observations, not accepted API/major or release receipts.

Only new primary byte selector is Laravel13.x PendingRequest::get(); existing framework bytes are unchanged. Detailed primary/manual premises and limits are in [reference](D2-reference.md#boundary-verification-continuation), derived adequacy checks in [packet §8](D2-qualification-packet.md#8-exact-receipt-checks-for-the-remaining-boundaries), current all-DI/AP dispositions in [gap receipt](D2-qualification-gap-receipt.md). **0 effective DI;0 executed AP.** All missing effective values and artifacts remain UNFILLED with owning roles. Boxed FlexCRM Files and historical storage/signup/launch artifacts stay at their original scope.

No new central owning source was supplied. Prior directory absence/public lookup/TLS-name failures were not reprobed; no central metadata/JWKS GET, certificate bypass, client/SAN/URI inference, private source install/access, deployment/registration write or foreign write occurred. This is a local evidence result, not a claim that global sources/deployment are absent or unchanged. Further discovery requires a genuinely newly accessible owning source; primary premise rechecks alone are not that source.
