# P6 schema bridge receipt

**Schema:** schema-bridge/1; p6-data-receipts/1
**Item:** P6.3

`request()` is persistence only. The worker requires a current trusted scope, claims work in a short transaction, then runs PostgreSQL concurrent DDL outside it and verifies the managed catalog entry.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| DDL-01 | R11 | request path | add request | no DDL before worker | `003019ddad4ea19c5c3c9c2224be7eea024b200315614f482982d06e9b57556e` | PASS | HTTP must never call worker |
| DDL-02 | R11 | operation store | duplicate operation key | one durable operation | `003019ddad4ea19c5c3c9c2224be7eea024b200315614f482982d06e9b57556e` | PASS | caller chooses queue transport |
| DDL-03 | R11 | PG16 worker | managed add | concurrent index outside claim transaction | `1b898d79b6e4c0081a0679249af725299478aae7816378ecee06a10de9034b7d` | PASS | production load thresholds are separate |
| DDL-04 | R11 | worker recovery | stale running claim | reclaim then verify | `003019ddad4ea19c5c3c9c2224be7eea024b200315614f482982d06e9b57556e` | PASS | five-attempt cap is enforced |
| DDL-05 | R11 | restore safety | old restore epoch | deny before DDL | `003019ddad4ea19c5c3c9c2224be7eea024b200315614f482982d06e9b57556e` | PASS | epoch source remains tenancy context |
| DDL-06 | R11 | catalog verifier | manually absent owned index | drift report names missing mapping | `1ed594b692e40efb1818bbb90d976d4b09a4078788db6820d0c5b0cbede64824` | PASS | no auto-drop reconciliation |
| DDL-07 | R12 | destructive safety | managed drop | 30-day grace plus export digest | `1b898d79b6e4c0081a0679249af725299478aae7816378ecee06a10de9034b7d` | PASS | external backup/PITR is not simulated |
| DDL-08 | R11 | PG16 test DB | all bridge cases | isolated PostgreSQL suite passes | `003019ddad4ea19c5c3c9c2224be7eea024b200315614f482982d06e9b57556e` | PASS | live production proof remains P10-owned |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `packages/attributes/database/migrations/2026_09_16_000001_create_sys_attribute_documents.php` | `b0ea62ef52e31c0983727e863fe10e3b5e21bb0bfe2bcf07aa168a6ede26db33` | owned operation storage |
| `packages/attributes/src/Internal/Engine/QueuedSchemaOperations.php` | `1ed594b692e40efb1818bbb90d976d4b09a4078788db6820d0c5b0cbede64824` | request, inspect, drift bridge |
| `packages/attributes/src/Internal/Engine/SchemaOperationWorker.php` | `1b898d79b6e4c0081a0679249af725299478aae7816378ecee06a10de9034b7d` | async worker and CIC |
| `packages/attributes/tests/Pg/QueuedSchemaOperationsTest.php` | `003019ddad4ea19c5c3c9c2224be7eea024b200315614f482982d06e9b57556e` | request/retry/restore/drift proof |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| schema bridge | `2a2b3a02ab94da1a29a34e2b304c2190e761de9d087525c2145d9628d7cff769` | D39 | queued PostgreSQL operation worker | SchemaOperations | application owns asynchronous dispatch |
