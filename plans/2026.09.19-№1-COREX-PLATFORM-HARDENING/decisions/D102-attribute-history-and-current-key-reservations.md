---
id: D102
date: 2026-09-20
status: accepted
item: P10.3
items: [P10, P10.3, P10.4]
supersedes: []
superseded_by: null
---
# D102 — Attribute history is append-only; current keys live in reservations

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** `RAG:—` DA05 reproduces that a partial unique `(entity,key)` on the
history table prevents a second revision of the same attribute/key.

## Решение

`sys_attribute_definitions` keeps only unique `(attribute_id,revision)` and is append-
only. Mutable current ownership moves to `sys_attribute_key_reservations`, keyed by
`(entity,key)` and pointing to attribute id/current revision plus reservation role.
Publishing a revision and replacing its reservation set is one tenant transaction.
During rename both old alias and new key may reserve to the same attribute; contract
removes the old reservation only after verification and grace.

## Почему

History and current uniqueness have different lifecycles. Separating them permits many
revisions while still rejecting two current attributes claiming the same entity/key.

## Consequences

P10.3 owns three additive tables in one migration. P10.4 proves multiple same-key
revisions and the full expand→switch→contract reservation sequence.
