# P1 — mandatory Perplexity design verification, 2026-09-15

**Perplexity:** successful
**Search artifact:** `artifacts/P1-design/perplexity-response.json`
**SHA256:** `17ba8102a806f994e75d9b430a10291da8f7e8c0d2cab7353d9fdfd239f388e9`

RAG:✅ Actual mcp__perplexity_web__search succeeded after owner reported automatic sign-in.
Initial login-required result was reported; it was not treated as a completed check.
Supplemental query explicitly closed that retrieval gap. Raw response retained; no provider summary
is substituted for direct primary verification. No runtime/API/version/dependency choice was made in P1.

## Query

Exact submitted query: [perplexity-query.txt](../artifacts/P1-design/perplexity-query.txt).

Use primary NASA SWE-052/W3C PROV-DM sources to review: source/provenance reconciliation;
lossless parent criterion IDs/text with mechanism/policy/provider decomposition; equivalent behavior
and bounded evidence classes; sealed fixed requirement set/downstream gaps. Explain supported
facts, alternatives/failure modes and limits; no ideal/uniquely optimal architecture claim.
Full observed response is the raw search artifact above.

## Primary sources

| Origin | URL | Supported claim |
|:--|:--|:--|
| NASA | https://swehb.nasa.gov/display/SWEHBVB/SWE-052+-+Bidirectional+Traceability+Between+Higher+Level+Requirements+and+Software+Requirements | Record and maintain bidirectional links, unique IDs/source lineage, named matrix owner, identify missing/extra functionality. Perplexity retrieved edition B; directly checked 2026-09-15. |
| W3C | https://www.w3.org/TR/2013/REC-prov-dm-20130430/ | Entity/activity/agent responsibility, derivation, versions and provenance of provenance; directly checked 2026-09-15. |
| W3C | https://www.w3.org/TR/prov-constraints/ | Provenance consistency is a separate concern from requirement satisfaction; directly checked 2026-09-15. Same publisher, not a third independent origin. |

NASA edition D also directly checked at https://swehb.nasa.gov/spaces/SWEHBVD/pages/102695427/SWE-052%2B-%2BBidirectional%2BTraceability.
Edition B is not represented as a current edition. W3C PROV-DM is the 2013 Recommendation.
Sources are independent NASA/W3C origins. Their project-specific application below is inference;
CoreX does not adopt NASA assurance classifications or implement a PROV runtime dependency.

## Alternatives

Inference against repository constraints: four bounded carriers keep source qualification, atomic
ownership, behavior/evidence qualification and final seal independently verifiable. One large item
hides producer/seal conflicts; one item per requirement repeats reads and scatters mixed clauses.
NASA/W3C support traceability/provenance principles, not the exact number of items or this taxonomy.
Retained IDs plus independently observable subconditions keep original meaning; changing original
criterion text would obscure lineage. Seal creates a versioned baseline; approved changes create a
new affected baseline/finish check, not a permanently unchangeable scope or silent future exclusion.

Existing behavior must be inspected by symbols/tests/receipts; the search cannot prove absence,
semantic equivalence or test adequacy. Sources do not prescribe our SHA mechanism, evidence classes,
mechanism/policy/provider split, non-CRM scenario or unique optimum. Those remain D2/D3/AGENTS decisions.

## Cross-phase impact

P1 produces bounded R/AC/owner/evidence/gap inputs for P2–P11; current source snapshots and declared
future contracts allow full-plan-first design without executing P1. All 52 R/365 parents are retained.
P2 signatures/ownership, P3–P8 implementations, P9 release cohort, P10 real DI/AP and P11 consumer
pins remain distinct linked outcomes. No local test/candidate/source provenance is upgraded to
released/live acceptance. Integrated design checks actual seams/versions/failure modes before finish.

## Failure modes and residual limits

Dropped clauses, unparented features, wrong domain owner, changed source/cohort and stale derived
claims: unique parent/R coverage, source/receipt anchors and explicit corrections mitigate them.
Correctness still requires semantic review and future executed tests. This RAG verifies the premises
of P1 structure; it does not establish complete clause classification, production acceptance or
an ideal architecture. P2–P11 RAG and the whole-plan finish remain pending.
