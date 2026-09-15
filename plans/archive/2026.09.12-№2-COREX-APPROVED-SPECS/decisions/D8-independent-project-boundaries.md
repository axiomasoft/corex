---
type: architecture
id: D8
date: 2026-09-14
status: accepted
item: P3.2
items: [P3.2]
supersedes: []
superseded_by: null
---
# D8 — CoreX, AzGuard and FlexCRM are independent projects

**Actor:** Dmitry Vostrikov · current owner clarification; recorded by plan-designer/frontier/high · gpt-5.6-sol.
**Evidence:** RAG:— `brief/P3.2-owner-clarification.md#independent-projects`; CoreX doctrine, auth README and manifest.

## Решение

AzGuard independently owns its role system. CoreX provides reusable modules for any Laravel
application. FlexCRM independently consumes both. Each keeps its own architecture, API,
philosophy and task scope; consuming or adapting a package does not transfer domain ownership.

D7's no-duplication principle remains. Its external-consumer dispatch prerequisite no longer
applies to revised P3.2: a CoreX plan must not carry implementation of an AzGuard or FlexCRM role
writer. Replace P3.2 with a documentation-only CoreX ownership-boundary contract. Historical D2
CONSUMER-ROLE and predecessor D17 writer requirements apply only to separately scoped external
work if requested later; they do not gate this revised item. Historical records stay unchanged.

## Почему

The previous spec mixed an independent role engine, reusable modules and an application consumer.
The owner's clarification requires a hard separation of project responsibilities.

## Consequences

P3.2 has exact CoreX documentation Files, implementation/high · plan-exec · light routing and no
foreign qualification prerequisite. Existing auth bridge code remains factual evidence; duplication
review, removal, reclassification and contract changes need separate scope. P3.1 remains Done,
P3.2 Not started and P3 open. This design does not implement or close the item or phase.
