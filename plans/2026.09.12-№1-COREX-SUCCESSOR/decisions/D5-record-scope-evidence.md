---
type: workflow
id: D5
date: 2026-09-12
status: accepted
item: P2.1
items: [P2.1]
supersedes: []
superseded_by: null
---

# D5 — Evidence for real department record scopes

**Date:** 2026-09-12
**Status:** accepted
**Actor:** plan-exec/implementation/high · gpt-5.6-terra
**Evidence:** P2.1 execution on local isolated PG16 `corex_test`.
**Supersedes:** —
**Superseded By:** —

## Решение

Принять P2.1 как evidence-only test delta: positive and negative real-record
coverage closes the previously vacuous department record parity gap.

## Почему

`ScopePredicate::matchesDepartment()` uses a record SQL path that is distinct
from `applyDepartment()`. The former generic parity fixture had no real user
or department rows, so it could only establish matching denial for department
scopes.

## Fixture and assertions

`ScopeResolverTest` and `AccessCheckerTest` each migrate the real CoreX
`sys_departments` ltree migration through `migrateCoreDepartments()`. The
fixture creates a user principal in `record_scope` / `recordParity.parent`,
then creates owners in the same department, a descendant, an unrelated
sibling, no department, no users row, and a NULL owner record. IDs are UUIDv7
values generated in the isolated test transaction; the tests retain each exact
created ID and compare the Builder and record result sets by those IDs.

| Scope | Expected record labels / IDs retained by test |
|:--|:--|
| `dept` | `same` only |
| `dept_tree` | `same`, `child` |

The AccessChecker scenario compiles a real role-matrix grant and creates its
role assignment before calling the public `canOnRecord()` API. It explicitly
denies sibling, NULL owner, missing owner, owner without department, agent and
API-key principals, and missing `sys_departments` (the fixture drops it with
`CASCADE` only after the record is assembled). No direct grant is installed in
these scenarios.

## Receipts

PG preflight used host mode: Docker service `pgsql` was healthy, PostgreSQL
accepted `127.0.0.1:5434`, and `corex_test` existed. The package resets only
the `public` schema of that `_test` database per test.

| Command | Result |
|:--|:--|
| `composer --working-dir=packages/auth test:pg -- --filter='department scope|real department records'` | 10 passed, 18 assertions |
| temporary sabotage: expect `Dept` to include `child` | RED: actual label was `same`; restored immediately |
| `composer --working-dir=packages/auth test:pg` | 106 passed, 275 assertions |
| `composer --working-dir=packages/auth test` | 33 passed, 54 assertions |
| `composer --working-dir=packages/auth lint:check && composer --working-dir=packages/auth analyse` | green |
| `composer analyse:tests-lint` | green; exact AccessChecker fixture allowance is 2 (raw ltree row plus controlled missing-table DROP) |
| D34 root `lint:check`, `analyse`, `test`, `test:pg`, `test:arch`, anti-weakening, contract-guard | green |

## Scope boundary

This is record-versus-Builder regression evidence for `dept` and `dept_tree`.
It does not add a role-assignment writer, change runtime behavior or public
Contracts, claim Laravel 13 auth support, or claim completion of all auth
release gates.
