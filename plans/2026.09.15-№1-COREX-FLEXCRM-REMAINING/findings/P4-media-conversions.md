# P4.3 media conversion receipts

Version: p4-extension-receipts/1. `corex/media` exposes only a bounded,
versioned thumbnail conversion seam. The optional GD driver processes JPEG/PNG;
there is no video, PDF, SVG, public-URL, external-binary, or storage-provider
promise.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| CON-01 | R15 | `GdThumbnailDriver` / `thumbnail` | Real generated JPEG is stored then converted; absent/unknown profile has no advertised result | Decodable bounded JPEG has server-checked MIME/dimensions; unsupported profile is absent | `ConversionTest.php` SHA-256 `f189b372baf99d4b710ba4ce2d8369a578d5ddb06b99f8da7e48cccf09b6f560`; `ConversionEdgeCasesTest.php` SHA-256 `5b4c7a5bac3ed7132bb555b3478a69d90ac4a97acf22a4981a18ee74d3382bf9`; package Pest GREEN (16/51) | PASS | GD is optional: without `ext-gd` the provider registers no thumbnail driver |
| CON-02 | R15 | profile digest + unique operation key | Same ready file/profile/version is requested repeatedly | One immutable profile digest/source-version conversion row and canonical operation key | `ConversionCoordinator.php` SHA-256 `6e0d8e7a79bb9073586190800ecfa6f9f5311f4a28f865c0866a6915364218db`; `MediaOperationStore.php` SHA-256 `86c5456d6bb0b29a8385c54e78eacd4dcb4bf02cc8c165adba3b6abfc8698c01`; package PG Pest GREEN (1/1 independent connection) | PASS | Queue dispatch is advisory; durable rows are the recovery source |
| CON-03 | R15 | claim epoch / temporary attempt key | Malformed source or bounded-output fault after claim | No ready output; operation becomes `unknown` for durable retry, and stale completion cannot publish | `ConversionEdgeCasesTest.php` SHA-256 `5b4c7a5bac3ed7132bb555b3478a69d90ac4a97acf22a4981a18ee74d3382bf9`; `MediaOperationStore.php` SHA-256 `86c5456d6bb0b29a8385c54e78eacd4dcb4bf02cc8c165adba3b6abfc8698c01`; package Pest GREEN (16/51) | PASS | Provider IO reconciliation stays P10 |
| CON-04 | R15 | shared `med_files` lock + purge | Ready derivative exists, original is deleted and purge runs | Derivative is tracked and removed with the deleting original; late ready completion is rejected by state/version/epoch checks | `ConversionTest.php` SHA-256 `f189b372baf99d4b710ba4ce2d8369a578d5ddb06b99f8da7e48cccf09b6f560`; `ConversionCoordinator.php` SHA-256 `6e0d8e7a79bb9073586190800ecfa6f9f5311f4a28f865c0866a6915364218db`; package Pest GREEN (16/51) | PASS | Actual row locking is proven in the isolated PostgreSQL lane, not SQLite |
| CON-05 | R15 | immutable profile facts | Malformed source and driver output exceeding declared bytes/pixels/dimensions | Typed conflict; conversion remains pending/unknown and no invalid output is advertised | `ConversionEdgeCasesTest.php` SHA-256 `5b4c7a5bac3ed7132bb555b3478a69d90ac4a97acf22a4981a18ee74d3382bf9`; `GdThumbnailDriver.php` SHA-256 `04edc06c0573a1be72f983d86d7bdfe67662710e0af25ec3dab803e78f6ef206`; package Pest GREEN (16/51) | PASS | Profile changes create a distinct digest; no stale output reuse |
| CON-06 | R15 | durable scan / scoped `TenantStorage` | Queue mode has no Redis dependency; conversion I/O uses the existing scoped private storage seam | Due operations can be scanned and dispatched; missing driver returns no conversion, and scoped storage remains the P3-qualified context guard | `SweepMediaOperations.php` SHA-256 `90bc7adee77bb2ee506cf0553d92d05fa2616a1796b717e6d6c921684d43d4a0`; `P3-storage-context.md` SHA-256 `7ef4c868f1ad94685c9a07fe3eaed5c5251a1377cf0694c586c03d7fd191635d`; package Pest GREEN (16/51) | PASS | P9 owns installed worker composition; P10 owns real provider-outage and operational proof |

Execution envelope: P4.2’s final package checks remain current for the shared
module: Pint and PHPStan passed; package Pest passed 16 tests / 51 assertions;
the system-PHP 8.4 Xdebug run passed 82.0%; the isolated `corex_test`
PostgreSQL lane passed. Root lint, PHPStan, non-PG (156), PG (110), Arch (6),
contract guard, anti-weakening, and all 34 mutation checks passed.

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P4-extension-design.md | 957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a | frozen conversion seam and CON invariants |
| findings/P2-contract-catalogue.md | ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573 | C-P2-008 and protected-gate status |
| findings/P3-storage-context.md | 7ef4c868f1ad94685c9a07fe3eaed5c5251a1377cf0694c586c03d7fd191635d | qualified private scoped storage evidence |
| findings/P4-media-lifecycle.md | 1e5f8e4c435a1866c951d320f24f20ed943b06bd95bf0497c2337e512869c25f | P4.2 producer lifecycle receipt |
| packages/media/composer.json | 52ed1749963161fada7a1d7b369cbe70be3a34a14daa80ead6b74e81ac569145 | standalone module identity |
| packages/media/config/corex-media.php | 82edd1b2324de303a2415672bd2172bff47a1a6141f459ca2732b561e79ceab2 | immutable default resource limits |
| packages/media/src/Contracts/ConversionDriver.php | 91446dd334882575a1c238eb6b0e625ae37b245ffa3703c1fb3a87068f76e0bd | optional driver seam |
| packages/media/src/Data/ConversionProfile.php | 6acd4ae9979a173a7bb1a7e11b070f37bcab987ab0504ab19ac0a9c6b5b2a189 | versioned bounded profile facts |
| packages/media/src/Internal/ConversionProfiles.php | b948ee062269daba3f61e55aa2de4704590405fee7e64798c41682973433ff8e | deterministic driver/profile digest registry |
| packages/media/src/Internal/ConversionCoordinator.php | 6e0d8e7a79bb9073586190800ecfa6f9f5311f4a28f865c0866a6915364218db | durable request/process/publication protocol |
| packages/media/src/Internal/GdThumbnailDriver.php | 04edc06c0573a1be72f983d86d7bdfe67662710e0af25ec3dab803e78f6ef206 | bounded JPEG/PNG adapter |
| packages/media/src/Internal/MediaOperationStore.php | 86c5456d6bb0b29a8385c54e78eacd4dcb4bf02cc8c165adba3b6abfc8698c01 | claim epoch and durable scan |
| packages/media/src/Jobs/ConvertMedia.php | 2883f9a0ae18d3897e122308f0b8b06023258a18e01a90dd809920072b78f8af | queue worker adapter |
| packages/media/src/Jobs/PurgeMedia.php | 13026ff59e6d4fdee0c6e53286d99cf760d4d4f4cb75c016785e689e0e09ff69 | derivative cleanup worker |
| packages/media/src/Commands/SweepMediaOperations.php | 90bc7adee77bb2ee506cf0553d92d05fa2616a1796b717e6d6c921684d43d4a0 | restartable durable scan |
| packages/media/database/migrations/2026_09_15_000003_create_med_conversions_table.php | b461a391330e8067fbc2c1fd0ca4310e5538173f106036b5cd15d83c6c22e741 | own conversion schema and uniqueness |
| packages/media/tests/Conversion/ConversionTest.php | f189b372baf99d4b710ba4ce2d8369a578d5ddb06b99f8da7e48cccf09b6f560 | real GD/cleanup conformance |
| packages/media/tests/Conversion/ConversionEdgeCasesTest.php | 5b4c7a5bac3ed7132bb555b3478a69d90ac4a97acf22a4981a18ee74d3382bf9 | conversion fault conformance |
| packages/media/tests/Pg/ConversionConcurrencyTest.php | 291cb068b381d9aaba0e0c19045b29e2e5287682c3bd63e7a40efec27c591a1b | independent PostgreSQL connection proof |
| composer.lock | f02465efe95cfa7ca9ffe984570bdfa418edb81f7a8dd2d9c1fd8d585a21e610 | root installation graph |
| .github/workflows/ci.yml | 6106ed94183d95219caa587ecd4f143cee29aa941bf10ac80e38541e7f9f9478 | package CI lane |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `media/1` conversion seam | C-P2-008 `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | P4.2/P4.3 bind source, tests, configuration, and P3 scoped-storage facts; Q-P2-009/010/011 remain proposals, never adoption authority | Optional GD JPEG/PNG thumbnail with digest-bound limits and durable SQL operation records | `corex/media` exposes `ConversionDriver` plus the `thumbnail` profile only when GD exists | CoreX API/schema/storage/release and consumer owners must complete Q-P2-009/010/011 before stable adoption; P9 owns installed worker proof and P10 owns provider/retention/backup/outage proof |
