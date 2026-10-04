---
id: D99
date: 2026-10-04
status: accepted
item: P07.1
items: [P07, P07.1, P07.3]
supersedes: []
superseded_by: null
---
# D99 — p07-g2-scan-correction (accepted)

**Actor:** designer/codex/gpt-6.1-sol/high, continuing owner-authorized P07 design correction after owner-requested Grok review.
**Evidence:** RAG:— `findings/P07-grok-review.md` finding 5; `scripts/runtime-globals.json` helpers list, current baseline and service source at 4137d241.

## Solution

Scoped amendment with priority over the numeric claim in D98 p.7. P07.1 removes the two tenancy() calls and the Stancl Account type through TenantContextResolver plus factory wiring as designed; the helper grep is empty. `tenancy` is absent from the G2 scanner's helper set, so this change does **not** reduce G2: the service baseline remains 19 in P07.1. No baseline file edit belongs to P07.1. P07.3 removes actual counted helpers/facades/static state and shrinks the full baseline to {} as already required. Actual current counts: 23 findings in the service, 25 across the three baseline files; dossier/phase factual summaries use these counts. Existing rules and scanner configuration are unchanged.

## Why

The previous 19→17 promise counted textual helper occurrences rather than the configured scanner. Grok independently caught this error. Lowering the baseline would make valid P07.1 acceptance unreachable; keeping it at 19 matches the unchanged counted findings and does not weaken any gate.

## Consequences

Item, dossier, example and self-check align with the actual scanner. Original Grok report is preserved on its reviewed fingerprint. New findings 1–4, 6–7 remain open; no product phase acceptance or formal GREEN is inferred. A new finish records the narrow correction.
