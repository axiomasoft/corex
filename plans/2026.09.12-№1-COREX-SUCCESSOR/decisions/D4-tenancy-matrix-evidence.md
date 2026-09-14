---
type: workflow
id: D4
date: 2026-09-12
status: accepted
item: P1.2
items: [P1.2]
supersedes: []
superseded_by: null
---

# D4 — Local evidence tenancy Laravel 12/13 matrix

**Date:** 2026-09-12
**Status:** accepted
**Actor:** plan-exec/implementation/high · gpt-5.6-terra
**Evidence:** RAG:— isolated worktrees from source/item SHA `8b11672a1e1f14df0157404d041ce4e8f0aa2a23`; P1.2 receipt below.
**Supersedes:** —
**Superseded By:** —

## Решение

`tenancy-tests` uses PHP 8.4 and an exact `['12.*', '13.*']` Laravel matrix. Each leg constrains
`laravel/framework:${{ matrix.laravel }}` before its package-local Composer update; tests run on the
same PG16 job database. PHPStan remains deliberately L13-only.

## Почему

The package advertises both framework majors while its former job resolved only one unconstrained
dependency set. Isolated two-leg resolution proves the advertised runtime boundary without changing
the package's declared constraints or the root dependency graph.

## Isolated local receipts

Host preflight: Docker service `pgsql` healthy; `127.0.0.1:5434` accepted connections; target
`corex_test` passed the `*_test` guard and existed before tests.

| Leg | Resolved framework | Testbench | Result |
|:--|:--|:--|:--|
| 12.* | `laravel/framework v12.69.2` | `orchestra/testbench v10.11.0` | 176 passed, 496 assertions |
| 13.* | `laravel/framework v13.31.0` | `orchestra/testbench v11.2.0` | 176 passed, 496 assertions |

Both receipts came from disposable detached worktrees with `composer require
laravel/framework:<major> --no-update`, followed by package-local `composer update` and test.
L13 `composer lint:check` and `composer analyse` were green. In the disposable L13 worktree,
temporarily removing `@implements Scope<Model>` from `WorkspaceScope` produced exactly
`missingType.generics`; restoring it returned PHPStan to green.

## Limitation

No GitHub Actions workflow was dispatched or claimed. The matrix is local reproducible evidence;
live CI remains a release-authority gate.
