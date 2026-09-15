# Authoring validation notes

Astra authored phase-level contracts only. No product item has been started or accepted.
Task v2 requires Routing/input carriers even for a skeleton. Every phase therefore has one
intentionally incomplete item marker; Sol determines the real decomposition and fills all
fields. Phase Context owns upper-layer scope/outcomes; no exact code spec was authored.

The current readiness checker treats a literal em dash as populated. Deliverables is therefore
left visibly empty in each marker (all 11 fields remain structurally present). This expresses
actual lack of an item-level deliverable; nothing is reported execution-ready. No runtime or
validation guard was changed. Focused lint and handoff receipt must both accept the final form.

Astra route attestation remains unavailable in the old model map; D1 records the explicit owner
choice. Handoff receipt carries adapter_attestation=null; no product receipt is minted.
