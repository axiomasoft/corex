---
type: architecture
id: D7
date: 2026-09-14
status: accepted
item: P3.2
items: [P3.2]
supersedes: []
superseded_by: null
---

# D7 — P3.2 requires an external Flex/AzGuard consumer binding

**Actor:** Dmitry Vostrikov · repository owner; current instruction.
**Evidence:** RAG:— owner states that AzGuard, not CoreX, implements the role system; `D17-owner-role-write.md`; `docs/dev-doctrine.md`.

## Решение

AzGuard owns role and privileged-grant semantics; P3.2 must be implemented in its named Flex
consumer, not in CoreX. CoreX does not add a role taxonomy, privileged-role policy, assignment
writer, grant endpoint, role-assignment audit protocol, or bulk-write guard. Its generic tenancy
context and `ImpersonationGuard` never classify a role or grant an assignment.

## Почему

Privilege classification and caller authority are product semantics. Duplicating them in CoreX
would make L0 own a second role system and cause AzGuard and CoreX to drift.

## Consequences

P3.2 cannot dispatch from this repository until a Flex consumer owner supplies its repository,
writer/policy paths, entrypoint inventory, audit authority and source hashes. Any AzGuard writer,
policy, endpoint, audit storage, or direct/raw/bulk enforcement is a separately owned external
task. Existing `packages/auth` code is not reclassified or removed by this decision; a possible
adapter/duplication audit is separate, explicitly scoped work.
