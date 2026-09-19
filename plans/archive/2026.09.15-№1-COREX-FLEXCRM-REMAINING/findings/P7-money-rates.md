# P7 money and rate snapshots

**Schema:** p7-utility-receipts/1
**Item:** P7.6

`corex/intl` ships frozen money/rate ports, immutable SQL snapshots, and FX-01..10 local conformance in `packages/intl`.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| FX-01 | R24 | pinned currency exponents, unknown denies | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |
| FX-02 | R24 | base→quote positive decimal orientation | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |
| FX-03 | R24 | immutable idempotent import | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |
| FX-04 | R24 | concurrent import one snapshot row | `bba9e57f2d9e330963981259600a03c83850b7e7133b6ed5c5b1af552c3029d5` | PASS | P7.6 | none |
| FX-05 | R24 | missing vs stale lookup distinct | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |
| FX-06 | R24 | explicit rounding deterministic receipts | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |
| FX-07 | R24 | same-currency identity preserved | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |
| FX-08 | R24 | signed amounts convert exactly | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |
| FX-09 | R24 | plain exact ISO fallback formatting | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |
| FX-10 | R24 | old snapshots remain after correction revision | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | PASS | P7.6 | none |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | frozen money profile |
| `findings/shared-data-foundation.md` | `e5462fc795dd7946b640eb253a28afffdd03a52339fc3ae503256ad1f33ad548` | shared DataScope reuse |
| `packages/core/src/Support/Data/DataScope.php` | `4f4da2f5a17ea56e0270fcf253b6e26475bbc3b4fe596821ff5ea6da45b7f284` | shared write scope DTO |
| `packages/core/src/Support/Data/WriteToken.php` | `e8f05733dfda14584a723795c96ddfdeced114ddf0302e22d4415561618c3ff2` | shared idempotency token |
| `packages/intl/src/Contracts/RateImporter.php` | `51f45443575d037758c669bd8c9366781097f1dd13a37c61302eb0d1cd94db8e` | public import port |
| `packages/intl/src/Internal/Money/SqlRateImporter.php` | `72aa139c36c8c3af0316494c790368b49c36b9346f889be7d96a85e15c6af7e2` | qualified SQL importer |
| `packages/intl/database/migrations/2026_09_15_000073_create_rate_snapshots.php` | `8509527d50f2e4413b029c30057ae6131b157c3eb86feca7ba2381d1dbc50cdb` | rate DDL |
| `packages/intl/tests/Money/MoneyRatesTest.php` | `a9039c13c9eff5b19ecce293ec6ce0604f28f2a676a2a400dacd19838f2ddfd9` | FX-01..03/05..10 sqlite proof |
| `packages/intl/tests/Pg/RateImportConcurrencyTest.php` | `bba9e57f2d9e330963981259600a03c83850b7e7133b6ed5c5b1af552c3029d5` | FX-04 PG proof |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| RateImporter | G-API | `51f45443575d037758c669bd8c9366781097f1dd13a37c61302eb0d1cd94db8e` | P7.6 | PASS | consumer wiring |
| rate-snapshot/1 DDL | G-SCHEMA | `8509527d50f2e4413b029c30057ae6131b157c3eb86feca7ba2381d1dbc50cdb` | P7.6 | PASS | opt-in migration |
