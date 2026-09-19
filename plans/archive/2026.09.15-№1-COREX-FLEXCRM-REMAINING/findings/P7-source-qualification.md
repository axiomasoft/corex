# P7 source qualification

**Schema:** p7-utility-receipts/1
**Item:** P7.1

`rlanvin/php-rrule` v3.0.0 is the immutable recurrence source candidate. Its MIT source, PHP floor and bounded `getOccurrencesBetween()` ABI were verified in an isolated checkout. GeoNames (CC-BY) and OpenStreetMap (ODbL) are source alternatives only; P7.1 neither downloads nor publishes a dataset.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| ENG7-01 | R20 | pinned RFC recurrence source, license and upstream suite | `aadb3e1d989de631a7f0d8a5ea4e04af788c94968b00cbc5f9b44a580fbc397f` | PASS | P7.1 | requalify on source upgrade |
| ENG7-02 | R17 | existing task ABI remains external to recurrence package | `4c058949481230cbb989a69dad3883195e0a9c8a5b3ff346fee3986ec80da067` | PASS | P7.3 | bind only after its own gates |
| ENG7-03 | R23/R24 | current PHP, ICU, tzdb and Brick baseline is recorded | `658457441e4ec7c6ac373228a5396c19ffef4635db50affee02dc4d8daec9138` | PASS | P7.5/P7.6 | package-specific adapter proof |
| ENG7-04 | R25 | licensed source alternatives are explicit, not eager defaults | `39dea96b364aea45c996adeea80124917087dc864316919770115d5d74e6e58c` | PASS | P7.7 | acquire immutable dataset only through G-DEPENDENCY/G-STORAGE |
| ENG7-05 | R20 | recurrence package boots with no Laravel, DB or task dependency | `82f19db87772ed2134723f85d965944973ca587d205f1f4dbb62f216a330b6b1` | PASS | P7.2 | test base consumer profile |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `artifacts/qualification/p7/recurrence-source.lock` | `39dea96b364aea45c996adeea80124917087dc864316919770115d5d74e6e58c` | immutable source pin |
| `artifacts/qualification/p7/upstream-suite.md` | `aadb3e1d989de631a7f0d8a5ea4e04af788c94968b00cbc5f9b44a580fbc397f` | isolated suite receipt |
| `artifacts/qualification/p7/runtime.json` | `658457441e4ec7c6ac373228a5396c19ffef4635db50affee02dc4d8daec9138` | runtime profile |
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | frozen bounded profile |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| recurrence source | source/license qualification | `39dea96b364aea45c996adeea80124917087dc864316919770115d5d74e6e58c` | P7.2 | PASS | use only the bounded wrapper |
