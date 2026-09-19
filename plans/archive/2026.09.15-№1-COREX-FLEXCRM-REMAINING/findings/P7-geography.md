# P7 geography reference provider

**Schema:** p7-utility-receipts/1
**Item:** P7.7

`corex/intl` ships frozen geography/reference ports, CAS dataset publication, and GEO-01..10 local conformance in `packages/intl`.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| GEO-01 | R25 | two fixture providers equivalent coverage | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | none |
| GEO-02 | R25 | manifest provenance and hashes | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | none |
| GEO-03 | R25 | malformed candidate rejects staging | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | none |
| GEO-04 | R25 | CAS publish on PostgreSQL | `2dcbfdba4ac198b86693a8ed2149e7b029a0d1482393545d67007b877958dd08` | PASS | P7.7 | none |
| GEO-05 | R25 | pinned keyset cursor and expired snapshot | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | none |
| GEO-06 | R25 | bounded UTF-8 query normalization | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | none |
| GEO-07 | R25 | multiple timezone candidates, no single guess | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | none |
| GEO-08 | R25 | local sqlite prefix/exact performance smoke | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | P10 vendor corpus |
| GEO-09 | R25 | read-only provider surface | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | none |
| GEO-10 | R25 | sqlite exact/prefix explicit | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | PASS | P7.7 | none |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | frozen reference profile |
| `findings/P7-locale.md` | `9a34389149c33fbcf7827d5b58fba4c986b5ea75183061780adc9264778db2ff` | locale fallback adoption |
| `findings/shared-data-foundation.md` | `e5462fc795dd7946b640eb253a28afffdd03a52339fc3ae503256ad1f33ad548` | shared DataScope reuse |
| `packages/core/src/Support/Data/DataScope.php` | `4f4da2f5a17ea56e0270fcf253b6e26475bbc3b4fe596821ff5ea6da45b7f284` | shared write scope DTO |
| `packages/intl/src/Contracts/GeographyProvider.php` | `8503e7a1ddd011b2ca5407ccacbcb3a03b9025e9ceb6a539b09c9fe4bc984162` | public geography port |
| `packages/intl/src/Contracts/DatasetImporter.php` | `e207816d235fa1df24e18c78981970464f1c0a188302add171fe7b66eb3e43cc` | public publish port |
| `packages/intl/src/Internal/Reference/SqlGeographyProvider.php` | `a9e2e67ce92fc610f1ae1920a9bc5015429b21ce6f26ee5b13e22fa2a1f40824` | qualified SQL provider |
| `packages/intl/src/Internal/Reference/BoxesReferenceProvider.php` | `06daeb5189dd2a84df985a9f707d427dee434654b3f275aa0ebc9c28627e99c9` | fixture provider A |
| `packages/intl/src/Internal/Reference/CompactReferenceProvider.php` | `3178c9b4a169c8866dd193a835b2877b6cb169693fc32ac5cbd38181f7c86a34` | fixture provider B |
| `packages/intl/DATA-LICENSES.md` | `8f904091d12c60d3a81c8ae32e74034802daf5469841abab8a0de50f160730a3` | fixture license declaration |
| `packages/intl/tests/fixtures/reference/boxes.json` | `028d6e56ca7e2b4dc19ca397cf136979a0c92f2146d78db840243e1aad5d1506` | licensed minimal fixture |
| `packages/intl/database/migrations/2026_09_15_000074_create_reference_snapshots.php` | `483d0d45c15ce129c40806d51b4106e7bcfd19ce3ac83e25a9c459b5717858dd` | reference DDL |
| `packages/intl/tests/Reference/GeographyTest.php` | `b91fce0a1992cfe4a24804d54cda1a6bf8fef3e33f380eb684e96cb94d08b998` | GEO-01..03/05..10 sqlite proof |
| `packages/intl/tests/Pg/ReferencePublicationTest.php` | `2dcbfdba4ac198b86693a8ed2149e7b029a0d1482393545d67007b877958dd08` | GEO-04 PG proof |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| GeographyProvider | G-API | `8503e7a1ddd011b2ca5407ccacbcb3a03b9025e9ceb6a539b09c9fe4bc984162` | P7.7 | PASS | P7.8 optional bridges |
| reference-data/1 DDL | G-SCHEMA | `483d0d45c15ce129c40806d51b4106e7bcfd19ce3ac83e25a9c459b5717858dd` | P7.7 | PASS | opt-in migration |
