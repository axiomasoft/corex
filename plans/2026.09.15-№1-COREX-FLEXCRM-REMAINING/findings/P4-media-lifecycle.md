# P4.2 media lifecycle receipts

Version: p4-extension-receipts/1. `corex/media` supplies a private, scoped
upload/reference/delete primitive. It owns only `med_*` tables and opaque
consumer edges; it neither selects an account connection nor exposes a public
URL, product entity, or storage-provider configuration.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| MED-01 | R15 | `SqlMediaLibrary` + `SqlRelationLookup` | Private text upload, same request key/digest replay, then attach/detach and read | One ready private record; replay returns its ID; opaque usage edge is removable | `MediaLifecycleTest.php` SHA-256 `6c9b00639131bce1e052fbde18542dfc74c0a4a70d8980ca15fb5adcea3ebcfa`; package Pest GREEN (16/51) | PASS | Consumer owns entity availability and authorization through `MediaPolicy` |
| MED-02 | R15 | configured `media/1` facts | NUL request key, unknown purpose, executable detected MIME, invalid context, oversize/staged input | Invalid facts do not reserve a ready object; staged data is never readable | `MediaLifecycleEdgeCasesTest.php` SHA-256 `2f706aecc9c03dd7d5ff59c99ca939f09d1dc3856f1a69c5c1dc35119a3b4db5`; package Pest GREEN (16/51) | PASS | Image dimensions are bounded by `max_image_pixels`; consumer narrows the allowlist where needed |
| MED-03 | R15 | existing `TenantStorage` scoped handles | Retained storage handle across physical A/B account context and context reset | Cross-context handle access is denied before backend I/O; media has no second storage adapter | P3.1 `physical-worker-reset` and `retained-handle-context`, `P3-storage-context.md` SHA-256 `7ef4c868f1ad94685c9a07fe3eaed5c5251a1377cf0694c586c03d7fd191635d`; `SqlMediaLibrary.php` SHA-256 `8139191b6b5135d5db8082450f3b8f03609dfd67e0c878f77fbfe052f8b21e24` | PASS | P3 tenancy owns physical connection selection; P9 must prove installed media composition |
| MED-04 | R15 | PostgreSQL file-row lock | Independent PostgreSQL connection contends for `med_files` row; relation usage exists before delete | Lock contention is observed; delete refuses authoritative usage | `ConversionConcurrencyTest.php` SHA-256 `291cb068b381d9aaba0e0c19045b29e2e5287682c3bd63e7a40efec27c591a1b`; `MediaLifecycleTest.php` SHA-256 `6c9b00639131bce1e052fbde18542dfc74c0a4a70d8980ca15fb5adcea3ebcfa`; package PG Pest GREEN (1/1) | PASS | SQLite is not used as a locking proof |
| MED-05 | R15 | `SqlRelationLookup` / deny-by-default policy | Duplicate attach/detach, absent file, and no consumer policy | Relation mutation is idempotent; unavailable target and missing policy deny | `MediaLifecycleEdgeCasesTest.php` SHA-256 `2f706aecc9c03dd7d5ff59c99ca939f09d1dc3856f1a69c5c1dc35119a3b4db5`; `MediaLifecycleTest.php` SHA-256 `6c9b00639131bce1e052fbde18542dfc74c0a4a70d8980ca15fb5adcea3ebcfa`; package Pest GREEN (16/51) | PASS | Enabled-target checks stay consumer-policy owned |
| MED-06 | R15 | staged reservation + durable purge | Write failure, post-I/O policy revoke, and same key with changed checksum | No orphan ready reference; failed/revoked write becomes deleting with a purge intent; changed digest conflicts | `MediaLifecycleEdgeCasesTest.php` SHA-256 `2f706aecc9c03dd7d5ff59c99ca939f09d1dc3856f1a69c5c1dc35119a3b4db5`; `MediaLifecycleTest.php` SHA-256 `6c9b00639131bce1e052fbde18542dfc74c0a4a70d8980ca15fb5adcea3ebcfa`; package Pest GREEN (16/51) | PASS | Provider-level write reconciliation remains G-STORAGE/P10 |
| MED-07 | R15 | `PurgeMedia` + own restrict FKs | Delete then failed physical delete, late attachment to deleting file, and repeat purge operation | Unknown physical result stays deleting/unknown; no false deletion or dangling media relation | `MediaLifecycleEdgeCasesTest.php` SHA-256 `2f706aecc9c03dd7d5ff59c99ca939f09d1dc3856f1a69c5c1dc35119a3b4db5`; `MediaOperationStore.php` SHA-256 `86c5456d6bb0b29a8385c54e78eacd4dcb4bf02cc8c165adba3b6abfc8698c01`; package Pest GREEN (16/51) | PASS | Retention/hold/backup and provider delete reconciliation are P10 obligations |
| MED-08 | R15 | boxed/optional consumer profile | Module boot and own migrations with no attachment-consumer schema or FK | Media remains an independent module; only `med_relations.file_id` has an own FK | `MediaLifecycleEdgeCasesTest.php` SHA-256 `2f706aecc9c03dd7d5ff59c99ca939f09d1dc3856f1a69c5c1dc35119a3b4db5`; `composer.json` SHA-256 `52ed1749963161fada7a1d7b369cbe70be3a34a14daa80ead6b74e81ac569145`; root Arch GREEN (6/6) | PASS | P9 owns installed consumer/release composition |

Execution envelope: package Pint and PHPStan passed; package Pest passed 16 tests / 51
assertions; the system-PHP 8.4 Xdebug run passed the 80% floor at 82.0%; the
isolated `corex_test` package PostgreSQL lane passed. Root lint, PHPStan,
non-PG (156), PG (110), Arch (6), contract guard, anti-weakening, and all 34
mutation checks passed. The `phpstan_turbo` extension emitted an environment
startup warning only; PHPStan completed with no errors.

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P4-extension-design.md | 957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a | frozen `media/1` seam and MED invariants |
| findings/P2-contract-catalogue.md | ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573 | C-P2-008 status and outstanding protected gates |
| findings/P3-storage-context.md | 7ef4c868f1ad94685c9a07fe3eaed5c5251a1377cf0694c586c03d7fd191635d | qualified retained-handle and physical-context evidence |
| packages/media/composer.json | 52ed1749963161fada7a1d7b369cbe70be3a34a14daa80ead6b74e81ac569145 | standalone CoreX module identity |
| packages/media/config/corex-media.php | 82edd1b2324de303a2415672bd2172bff47a1a6141f459ca2732b561e79ceab2 | bounded upload/purpose/MIME configuration |
| packages/media/src/Contracts/MediaLibrary.php | e0a860f1ac9bac1525d77c2f01b5b81a5fcedbe4cc1584458991d46682f5fa5d | public lifecycle seam |
| packages/media/src/Contracts/MediaPolicy.php | 86c94cac5b86098b8681b9fd45db809d06aa02bf7a44d30ca83c9c5798e10c6e | default-deny consumer authority seam |
| packages/media/src/Contracts/RelationLookup.php | 04760337f2ff531643628fd0aecc7ab1a31a9966ee025c1869231c4d6ccc590e | opaque relation seam |
| packages/media/src/Data/MediaAction.php | d7e01ac77fe3f1ce71efb91b5e94c0d7bfc3ac8d12c7a459efd7a5f1d07e34d3 | exact five-action policy vocabulary |
| packages/media/src/Data/MediaFileRef.php | 442421a90e33f32afea339837eeca76deaeac682ce2f120e16a767bd188a310a | server-derived file facts |
| packages/media/src/Data/MediaReadRef.php | 827bc147e258c757f35b5ee71fbd648bc07e282839bc17ce9b3bc1584ec8816e | ephemeral authorized read handle |
| packages/media/src/Internal/SqlMediaLibrary.php | 8139191b6b5135d5db8082450f3b8f03609dfd67e0c878f77fbfe052f8b21e24 | staged private lifecycle and current-policy rechecks |
| packages/media/src/Internal/SqlRelationLookup.php | 87d87443721455ef4a1b3df578d6345d83b666b262fcb42b6cc30a2b93b010f6 | serialized opaque relation edges |
| packages/media/src/Internal/MediaOperationStore.php | 86c5456d6bb0b29a8385c54e78eacd4dcb4bf02cc8c165adba3b6abfc8698c01 | durable purge intent and claims |
| packages/media/tests/Feature/MediaLifecycleTest.php | 6c9b00639131bce1e052fbde18542dfc74c0a4a70d8980ca15fb5adcea3ebcfa | lifecycle acceptance cases |
| packages/media/tests/Feature/MediaLifecycleEdgeCasesTest.php | 2f706aecc9c03dd7d5ff59c99ca939f09d1dc3856f1a69c5c1dc35119a3b4db5 | fault and recovery acceptance cases |
| packages/media/tests/Pg/ConversionConcurrencyTest.php | 291cb068b381d9aaba0e0c19045b29e2e5287682c3bd63e7a40efec27c591a1b | independent PostgreSQL connection proof |
| composer.lock | af4777c768d5c77c14a247a8369e2f07f185a754d37b061628124651af02f918 | root installation graph |
| .github/workflows/ci.yml | 3615b307ec6953ddb58cb52e2d99f47a69259725e71dcf78edcf795dbdadb8eb | package CI lane |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `media/1` lifecycle seam | C-P2-008 `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | This receipt binds source, tests, configuration, root checks, and the existing P3 scoped-storage proof. Q-P2-009/010/011 remain proposals and are not treated as authorization. | `SqlMediaLibrary`/`SqlRelationLookup`: private `TenantStorage`, staged SQL state, own `med_*` tables, default-deny policy | `corex/media` exports the lifecycle/policy/relation contracts at the frozen `media/1` shape | CoreX API/schema/storage/release and consumer owners must issue the exact Q-P2-009/010/011 receipts before stable public adoption, installed release, or product integration; P10 owns provider IAM/retention/backup proof |
