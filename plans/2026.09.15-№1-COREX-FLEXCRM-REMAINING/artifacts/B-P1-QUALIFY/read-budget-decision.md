# B-P1-QUALIFY — read-budget decision packet

P1.1 closed GREEN commit7aa0fb0. P1.2 native ready-subset admission ALLOW, but no P1.2 start or product mutation has occurred. Owner scope remains P1.1–P1.4 in this root.

Native lint reproduced RED after the phase became In progress. The design session had no active phase, so its recorded GREEN lint did not exercise this gate. Current P1.2 bundle already selects named acceptance sections (not entire blueprints): this is not a whole-file selector bug. Fixed mandatory inputs alone exceed the frozen item cap.

| Item | Current estimated mandatory tokens | Frozen item cap |
|:--|--:|--:|
| P1.2 |174211|100000|
| P1.3 |143022|100000|
| P1.4 |125689 before produced map/ledger|100000|

P1.4 missing future outputs are named in Inputs, but its duplicate Required Reads lack producer annotations; this is separately repairable bookkeeping. Annotating them is not a budget exception.

Proposal for review: finite exception for B-P1-QUALIFY only,250000 estimated tokens/item; per-read262144bytes/25000tokens stay unchanged using contiguous tool chunks and complete numbered table sections for oversized produced reports. Preserve100sourceIDs/52R/365parenttexts/allclauses/allG/DI/AP, all item receipts and semantic checks. Restore shared100000 before any P2 execution. No runtime/API/DDL/dependency/release/private/foreign writes. No shared Task runtime edit. The proposal is unapplied; if actual measured graph remains above250000, do not widen it silently.

Changing the threshold requires owner decision under configured Task docs/reference/read-budget-contract.md, section Изменение контракта: «Изменение порога, единицы, применимости или anti-displacement semantics требует решения владельца». Without that decision no threshold change or P1.2 product write proceeds.

Alternative: owner approves narrower mandatory reading contracts while preserving all actual source/semantic work and anti-displacement measurement. New item IDs or scope removal are not authorized by the current request.

Evidence: read-budget-lint.red.txt;read-budget-measurement.json;read-budget-repair-decision.json;read-budget-repair-result.json. Reducer:design-required,nonterminal; no blocked item or fresh design agent launched.

Checkpoint bookkeeping adds1estimated token to current P1.2 measure (174212). Native delivery verify PASS for typed waiting-owner; native lint also exposes incompatible compact Context parsing of that supported waiting outcome. Exact later log read-budget-waiting-lint.red.txt. No runtime patch or green claim.
