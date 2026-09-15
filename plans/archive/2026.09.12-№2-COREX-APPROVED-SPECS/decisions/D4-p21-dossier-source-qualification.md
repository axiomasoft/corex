---
type: workflow
id: D4
date: 2026-09-14
status: accepted
item: P2.1
items: [P2.1]
supersedes: []
superseded_by: null
---
# D4 — P2.1: qualification of current read-only dossier inputs

**Date:** 2026-09-14
**Status:** accepted
**Actor:** gpt-5.6-sol/high · plan-run
**Evidence:** Exact current owner invocation `$ task:plan-run 2026.09.12-№2-COREX-APPROVED-SPECS P2.1`; provider-thread-state route; repository HEAD `23c99e2`; D2, D14 and current required reads.
**Supersedes:** —
**Superseded By:** —

## Решение

Qualify current bytes solely for the local P2.1 proposal and seam inventory. D2 remains byte-identical; its design pins are historical and are not silently replaced. No schema, source-owner, builder, key, fixture, release or implementation authority is granted by this observation record.

| Input | D2 design SHA256 | Current SHA256 | Meaning of delta |
|:--|:--|:--|:--|
| `packages/tenancy/src/Provisioning/PendingPool.php` | `7ce21dbba4bd60f75a85141a028190ffc362a4332484f8d8afc3b1aa0b8a2a42` | `4b036c5aa48d966bef58c47865a4936968dc897530e70c95b594e1955f33f439` | Cleanup claims before DROP and records a recoverable cleanup marker; claim and clone still have no semantic template-integrity verification. |
| `packages/tenancy/src/Lifecycle/AccountLifecycle.php` | `4eee3acce7adc8191e132005e1feec2426c23723c19f5aea9ba4044066d5113b` | `543e1c798c0824b09a73bb3affa2b910fe147e6f5bc16c435c37b9dcc9fefa7f` | Impersonation check now precedes pool claim; transitions lock account rows; export/purge use a distinct account-operation lock; events invalidate resolver cache after commit. None supplies the proposed template lock/hash proof. |
| FlexCRM `plans/flex/60_contracts/DB_SCHEMA.md` | `9768dfa40e72492f7863ee2a15f36157e20a0810f5eb998125401482a0f9ac98` | `f3959ef1061e9aab8b50311016bb47f02943f66bd62e9efc1d31e590c28ce536` | Current root_templates section still has no schema_hash; dossier patch binds current complete bytes after prior docs amendments, rather than the design-time whole-file hash. |

Other P2.1 required code/decision inputs match their source pins. Current bundle explicitly retains the cross-plan D14/D7 inputs.

## Почему

P2.1 explicitly allows missing operator bindings and prepares a proposal only. The observed deltas change the earliest-write map but do not change D14's selected branch or authorize DDL. Qualification permits an accurate proposal; implementation remains subject to TEMPLATE-SCHEMA and TEMPLATE-SEAL.

## Consequences

Record current source and candidate hashes in the two declared findings; verify no product/Brain/DDL/external delta. P2.2–P2.3 stay unqualified until exact proposal approval and non-secret operator/trust/storage/lock/fixture bindings are supplied. Tool repair is isolated under `/tmp`: recognize the literal № in plan paths and avoid interpreting an already declared path as a local bare D-record. Missing paths, bare D-ranges, schemas, source digests and all admission gates still fail closed; record reproducible repair details in the bindings dossier.

Closure metadata repair: P2.3 marks its existing future proof input as `(producer: P2.2)` in Inputs/Required Reads; no Files, Scope or validation is widened and no proof fabricated. Isolated Task compact Context parser recognizes the already canonical non-runnable waiting-owner outcome; typed reducer decision remains authoritative and persisted.
