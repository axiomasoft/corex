# P10 design RAG — neutral effective deployment/evidence assurance

**Perplexity:** successful
**Search artifact:** `artifacts/P10-design/perplexity-response.json`
**SHA256:** `542c7dea71b0683d9a30336bc684d69ff7a009234417a734207e525da82cdbfb`

Mandatory D4/D5/D6 retrieval: first broad reply ended mid-tail paragraph, owner immediately notified, incomplete raw retained `artifacts/P10-design/perplexity-initial-response.json` SHA256 `6d22e84676cf60ee420dcc4d2f1e7b1c0de1c3f964dc468cccf6575c560a6f15`. Supplementary short successful monthly/restore response closes that gap; its generic synthesis does not independently prove private project behavior. Ops query failed with No answer found/changed structure, owner immediately notified; `artifacts/P10-design/perplexity-ops-failed.json` SHA256 `ffa7f5611352ea13a79e7188e8447dcfd27cb41f80b74f1e47393d11606e1ac7` retained as failure, never successful. Short retry successful `artifacts/P10-design/perplexity-ops-response.json` SHA256 `b3139e3cf691abdaa046793b0920d1d5677ec5aac46e858e11288dc3593f76b2` completes IAM/DNS positive/negative best-practice retrieval. Queries include no internal project name/R/AC/paths/profile/schema. General public practices only. Successful response exists, but tool citations and unlinked summary text are not direct primary proof.

## Primary sources

| Origin | URL | Supported claim |
|:--|:--|:--|
| IETF | https://www.rfc-editor.org/rfc/rfc8705.html | Client certificate authentication and certificate-bound access tokens are distinct; resource checks presented certificate binding. TLS intermediary metadata security needs actual deployment mechanism. |
| IETF | https://www.rfc-editor.org/rfc/rfc9700.html | Reverse-proxy section4.13 requires authentic protected upstream and security-header sanitization; no forged forwarded peer identity accepted. |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html | Explicit deny and permission validation on every request, review chosen technology and negative tests; no grant from library default. |
| Google SRE | https://sre.google/sre-book/service-level-objectives/ | Specify exact SLI measurement/population/validity/time window; high percentiles and different workload tails require explicit interpretation. |
| Prometheus | https://prometheus.io/docs/practices/histograms/ | Distributions/counts aggregate; precomputed summaries/percentiles do not; bucket quantile error depends resolution. |
| PostgreSQL | https://www.postgresql.org/docs/16/continuous-archiving.html | Actual base backup/WAL/recovery target and logs determine recovered state; continuous recovery includes stopping at selected point. |
| PostgreSQL | https://www.postgresql.org/docs/16/backup-dump.html | Logical dump consistent within one DB, restoration and globals separate; successful restore alone not cross-system monotonic authorization proof. |
| NIST | https://nvlpubs.nist.gov/nistpubs/Legacy/SP/nistspecialpublication800-34r1.pdf | Contingency roles/plan testing/data integrity/reconstitution security and restoration metrics; no particular independent-witness implementation mandated. |
| Linux Kernel | https://www.kernel.org/doc/html/latest/timers/timekeeping.html | Clock sources/counters/frequency/drift are implementation facts; hardware-dependent qualification needed, not arbitrary cross-host ticks subtraction. |
| AWS | https://docs.aws.amazon.com/AmazonS3/latest/userguide/amazon-s3-policy-keys.html | Prefix condition restricts ListBucket separately from object resources; explicit deny and actual policy API test matter. Only AWS behavior documented, not another compatible vendor. |
| Let's Encrypt | https://letsencrypt.org/docs/challenge-types/ | DNS-01 supports automated/wildcard issuance, narrow DNS credentials/separate validation/delegation; propagation and cleanup need owning actual verification. |

## Choice / independent origin mapping

| Choice | Independent origins | What remains local inference |
|:--|:--|:--|
| C1 immutable stage-qualified same-cohort owner/raw subcondition envelope | IETF + OWASP + Google SRE | Local p10-external-evidence/1 field/hash/source-env/profile/actor and proof-stage policy; actual owner/source cannot be proved by web. |
| C2 complete edges/entries and certificate retirement | IETF + OWASP | Accepted profile300/600s, every open/resumed edge and actual guard inventory/handler negative receipts; do not treat general RFC as600s guarantee. |
| C3 nonrollback restore witness | PostgreSQL + NIST + OWASP | Allocated witness outside full rollback set/R/G/E/F and AF denial is local accepted stronger invariant, not NIST prescription or dumped DB equivalence. |
| C4 actual conservative FE-60 clocks/cleanup | Linux Kernel + Google SRE + OWASP | Local45/5/10/60s split, suspended-clock/platform/commit/error bounds and deny distinct physical cleanup must be measured by actual ops. |
| C5 complete closed monthly upper-bound percentile | Prometheus + Google SRE | Committed-event census/nearest-rank ceil(.999*N)/missing infinity/late/error/per-account/pair30s formal policy local; no scraped warm HTTP percentile substitutes. |
| C6 actual selected provider/control-plane acceptance | AWS + Let's Encrypt + PostgreSQL + OWASP | Actual RUvendor/region/SDK/IAM/limits/TLS/compensation/restore/export/fleet and custody selected/proved by independent owners; public docs no vendor equivalence warranty. |

## Alternatives

Source-only or deployment icon versus stage-qualified immutable raw/source/profile/env/owner evidence: select exact accepted/effective/executed coverage per subcondition, retain partial UNFILLED. Header-only ingress trust versus actual upstream peer/header sanitation and every-edge tests: select existing admitted topology evidence, no new PKI engine. Same restored witness versus independently allocated witness outside rollback domain: select actual local stronger monotonic security invariant, no web-imposed transparency service. Authorization deny versus physical cleanup conflation: select independent actual outcomes and all FE budget/retry boundaries. Warm/sample/mean-of-percentiles/open-month/no-data success versus complete monthly census: select conservative upper-bound rank and explicit missing/late/error, no blanket dropping unsuccessful events. API-compatible fake backend versus actual selected vendor/IAM/DNS/storage recovery: select primary vendor version/limits plus real positive/negative owning operations; no provider chosen during design. Inline external execution versus independent producer receipt: select local preparation/import/qualification with concrete requests, no external side effects/grants.

## Cross-phase impact

P1 frozen original requirement/selection→P2 accepted exact profile/API/physical mapping/adoption→P3 supported local conformance→P9 actual released cohort/hosted/cold lock→P10 actual effective same targets/raw all34DI13AP/FE/currentmonth/provider/ops receipt→P11 consumer locks/outcomes. No reverse runtime edge, no central/cloud code moved into reusable package, original B33/B34/product owners retained. Current verifier preparation and envelope structural tests do not execute AP or certify actual deployment. Full11phase/integrated semantic finish before every item including P1.1. Stable candidate/D15/checker included whole finish hashes.

## Unresolved premises / proof classes

General practices directly verified; accepted actual deployment source/endpoint/issuer/JWKS/client registration/PKI edges/custody/elapsed domains/store/witness/every entry/pair census/required current month/RU vendor/private source/adoption/grant/released vector are future owning execution inputs, not known facts filled by web. All current local observations RAG:— artifacts/P10-design/source-observations.json, source/tests/archived recorded receipts only. No current executed PHP/source/hosted/deployed/AP/provider/month result claimed. Initial incomplete synthesis, ops structure failure and raw NIST403 fetch retained; separate successful queries and direct verification resolve generic retrieval gaps. Raw direct compressed primary snapshots/digests in artifacts/P10-design/primary-source-retrieval.json; NIST PDF web successful independent verification, raw retry successfully retained PDF,13 direct raw primary snapshots/hash verification. No current product GREEN, external authority, phase-audit consent or whole design-finish.
