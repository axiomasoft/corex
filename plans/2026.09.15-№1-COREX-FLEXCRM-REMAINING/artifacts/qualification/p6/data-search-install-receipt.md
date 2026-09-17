# P6 local installed qualification

**Schema:** installed-qualification/1
**Profile:** local source candidate only; no public release or hosted claim.

The package cohort was exercised through its independent package commands, a disposable PostgreSQL 16 `_test` database, and a disposable loopback Meilisearch v1.53.2 service. `corex/search` stayed usable with the provider absent (typed unsupported), while its explicitly configured PostgreSQL profile and optional SDK adapter were tested separately. This is local source qualification, not a `--no-dev` release artifact, public package publication, fleet, or consumer acceptance.

| Profile | Actual result | Boundary |
|---|---|---|
| core-only / provider absent | PASS: SearchServiceProvider returns typed unsupported | no SDK, endpoint, credential or network boot |
| PostgreSQL 16 physical A/B | PASS: cursors and rows cannot cross test databases | disposable `_test` databases only |
| optional Meilisearch | PASS: task submit, status and swap receipts on loopback D40 image | service removed after each run |
| coverage runner | PASS: PHP 8.4 PCOV container, search 80.3%, adapter 87.2% | ephemeral test runner, not hosted CI |

**Pending owners:** P9 release/hosted matrix, P10 fleet/live timing and P11 independently owned consumer pin/reconciliation.
