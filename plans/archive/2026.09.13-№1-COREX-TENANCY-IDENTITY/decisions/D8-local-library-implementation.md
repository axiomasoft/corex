---
type: owner-decision
id: D8
date: 2026-09-13
status: accepted
item: P4.1
items: [P4.1, P4.2, P4.3, P4.4, P4.5, P4.6, P4.7, P5.1]
supersedes: []
superseded_by: null
---
# D8 — actual scoped local implementation authorization

## Решение

[OWNER-GRANT:LOCAL-IMPLEMENTATION] branch=`local-library`; scope=`P4/P5 unregistered library and disposable fixtures; preserve existing public API; no production schema, runtime registration, dependency, commit, private access or external writes`; owner-message=`brief/02-local-implementation-owner-request.md#local-p4-p5-20260913`

## Почему

The current owner explicitly grants this finite local tranche. D6 architecture and numerical defaults remain unchanged. D2 remains unreleased for production membership implementation/registration. This grant permits unregistered internal code and disposable local fixtures only; no API/major, production schema, dependency, private access, commit or external write is accepted.

## Local source and scope

Qualify content-addressed source copies and read/bundle hashes before dispatch instead of committing. P4.1 uses a separate internal ManagementIdentityHydrator and local Feature fixture so the existing distributed DatabaseIdentitySyncer and its legacy regrant semantics remain byte-identical. This routine hidden composition is added to P4.1 Files; runtime caller/provider/configuration stays absent. No production behavior change is claimed. P4.2 preserves CentralApiClient and every existing public DTO/event constructor and adds only Internal/Transport types. Public baseline hashes are checked at closure.

Missing owning DI/AP/deployment receipts remain UNFILLED in P7 and do not gate these local items. Execution selection in this invocation is P4.1 then P4.2; other granted items are successors, not executed by this invocation.
