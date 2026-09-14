---
type: workflow
id: D3
date: 2026-09-12
status: accepted
item: P1.1
items: [P1.1]
supersedes: []
superseded_by: null
---

# D3 — Policy для modules-lint на package tests

**Date:** 2026-09-12
**Status:** accepted
**Actor:** plan-exec/implementation/high · gpt-5.6-terra
**Evidence:** RAG:— CoreX source/item SHA `8b11672a1e1f14df0157404d041ce4e8f0aa2a23`; P1.1 receipt below.
**Supersedes:** —
**Superseded By:** —

## Решение

Добавить `packages/{auth,crm,tasks,tenancy}/tests` в standalone `phpstan-tests-lint.neon`.
Package source directories сканируются для символов; private AzGuard autoloader берётся только из
уже установленного `packages/auth/vendor` локального package-test environment. Larastan не добавлен.

## Почему

Без package paths новые package tests обходили все девять AST/SQL rules. Корневой dependency graph
намеренно не устанавливает private AzGuard, поэтому source scan без package-local autoloader создаёт
нерелевантные type diagnostics и не даёт увидеть actual rule boundary.

Три намеренные fixture-записи остаются exact-count boundary:

| Identifier | Path | Count | Rationale |
|:--|:--|--:|:--|
| `corexModules.directSystemTableWrite` | `packages/auth/tests/Pg/ScopeResolverTest.php` | 1 | Raw `sys_departments` row задаёт ltree/cross-branch fixture, недостижимую owning contract. |
| `corexModules.directSystemTableWrite` | `packages/tenancy/tests/Feature/WorkspaceResolverTest.php` | 2 | Raw department and membership rows доказывают tenancy/auth seam. |

## Census и receipts

Baseline root-only pass: `composer analyse:tests-lint` → 0.
После добавления четырёх paths raw census: 431 diagnostics; после package-source scan — 42
`class.notFound`/dependent diagnostics из отсутствующего в root vendor private AzGuard; реальный
autoload из package vendor оставил ровно 3 findings, перечисленные в таблице. После exact-count
policy: `composer analyse:tests-lint` → 0.

Проверены четыре declared paths. New blanket excludes, baselines, rule/runtime/Contracts changes
отсутствуют. Local auth vendor — diagnostic input, а не portable installation proof; live CI не
запускался и не заявляется как green.

## Consequences

Новая package fixture или лишняя raw core-table write красит тот же standalone command. Исчезновение
любой из трёх sanctioned writes также красит его через `reportUnmatchedIgnoredErrors`.
