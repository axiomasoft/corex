---
id: D114
date: 2026-09-22
status: accepted
item: P5
items: [P5, P5.13, P5.14, P5.15, P5.16, P5.17, P5.18, P6, P7, P8, P9, P10, P11, P12, P13, P14, P15, P16, P17]
supersedes: []
superseded_by: null
---
# D114 — Codex-native execution model pins

**Actor:** owner (Dmitry Vostrikov), `$ task:plan-design 2026.09.19-№1-COREX-PLATFORM-HARDENING roadmap`, 2026-09-22; recorded by plan-design.
**Evidence:** D112; Task 0.20.4 provider mapping; provider-attested design route
`gpt-5.6-sol/high`.

## Решение

D112's execution scopes, effort, review boundaries and owner gates remain unchanged. Its model
pins receive an exact Codex-native projection:

- Cursor `composer-2.5` maps to Codex `gpt-5.6-terra`;
- Cursor `cursor-grok-4.7-high` and the frontier/Sol scopes map to Codex `gpt-5.6-sol`;
- each launch uses only the selector of its active provider; cross-provider selectors are not
  copied into commands;
- unavailable exact model/effort remains `waiting-owner`, without silent fallback.

The authoritative per-scope projection is `plan.md` §3.3. This decision grants no pending
owner gate.

## Почему

D112 recorded Cursor-oriented selectors. They cannot be launched or attested as Codex model ids,
while Task 0.20.4 requires provider-specific route evidence before mutation. An explicit projection
preserves the chosen risk tier and effort without asking each executor to reinterpret the route.

## Consequences

- D112 remains accepted; D114 extends it with a second provider projection.
- The current frontier is P5.13 on Codex `gpt-5.6-terra/medium`.
- Handoff renders only the active provider's native command.
