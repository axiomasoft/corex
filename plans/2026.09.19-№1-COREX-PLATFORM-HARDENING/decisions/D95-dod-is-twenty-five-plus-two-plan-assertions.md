---
id: D95
date: 2026-09-20
status: accepted
item: P17
items: [P17, P17.5, P17.6, P17.9, P17.10]
supersedes: []
superseded_by: null
---
# D95 — DoD 1.0 contains the 25 audited assertions plus two plan assertions

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— `audits/2026-09-19-perplexity-3.md` contains 25 bullets although A111 and
the P17 skeleton say 27; `plan.md` and `docs/dev-doctrine.md` already require pristine profile
installation and usefulness without CRM/CMS/Store.

## Решение

The executable `dod-1.0.json` has exactly 27 stable IDs. DOD-01–DOD-25 preserve the audit's
25 meanings, with its CMS/CRM wording generalized to FlexCRM plus an independent non-CRM/CMS/Store
consumer over the same public lower-level contracts. DOD-26 proves pristine split-artifact install
profiles; DOD-27 proves the independent consumer's useful behavior. No condition may be represented
only by a checked box or prose claim.

## Почему

Inventing two hidden bullets would make the release gate unverifiable; reducing the announced count
to 25 would lose two promises already normative in this plan. The explicit 25+2 derivation preserves
provenance and makes every condition independently executable.

## Consequences

P17.9 owns the exact manifest and generated documentation. P17.10 fails on any missing, stale,
skipped or unknown row and includes a 26-row planted negative. DOD-19/DOD-27 do not create a CoreX
CMS product.
