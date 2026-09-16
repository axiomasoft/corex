# P4.7 saved-view receipts

Version: p4-extension-receipts/1. `corex/core` stores typed saved-view state
only; a consumer owns field mapping, row/object authorization, and any UI.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| VIEW-01 | R28 | core-only `SqlSavedViewRepository` | Neutral schema and a new valid view | Typed record is persisted and first view is default | `SqlSavedViewRepositoryTest.php` SHA-256 `8d13e03b79cd32e30e6b68ff2b303c220691d3400cbfd040847968dced51526b` | PASS | Consumer supplies an entity schema |
| VIEW-02 | R28 | bounded AST validator | Raw SQL operator in filter | Unsafe operator is rejected before SQL mapping | `ViewConfigValidatorTest.php` SHA-256 `05ae01d704d6853309d9f69c781b5e9026c73dabb4e5b9cabf3f2ab9633647af` | PASS | Consumer applies the returned AST under its policy |
| VIEW-03 | R28 | default-deny policy/provider | No installed schema or explicit allow policy | Read/write cannot be authorized by core defaults | `DenyViewAccessPolicy.php` SHA-256 `1b71069fb328aaa62850f02a623491242e17dfd246f0e7f95e5c612bab35c417`; `UnavailableViewSchemaProvider.php` SHA-256 `cc5ed2af5b6a9996669c1e9dd417807f5bfc5acfd6b89ee3802eba2029d79223` | PASS | Host provides current policy and schema |
| VIEW-04 | R28 | `ViewScope` | Partial owner pair | Invalid scope is rejected at the typed boundary | `SqlSavedViewRepositoryTest.php` SHA-256 `8d13e03b79cd32e30e6b68ff2b303c220691d3400cbfd040847968dced51526b` | PASS | PG DDL is authoritative for persisted rows |
| VIEW-05 | R28 | scope lock + create | First view in a scope | First record receives the default atomically | `SqlSavedViewRepository.php` SHA-256 `6205cae7cb87952a437e142d3e87305c39785717e843f95d72637c3cb682400e` | PASS | PG concurrent-writer proof remains in the PG lane |
| VIEW-06 | R28 | default lifecycle | Change or remove the current default | One remaining view is selected as default | `SqlSavedViewRepository.php` SHA-256 `6205cae7cb87952a437e142d3e87305c39785717e843f95d72637c3cb682400e` | PASS | Empty scope has no default by definition |
| VIEW-07 | R28 | revision CAS | Update with stale revision | Conflict is fail-closed | `SqlSavedViewRepository.php` SHA-256 `6205cae7cb87952a437e142d3e87305c39785717e843f95d72637c3cb682400e` | PASS | Caller chooses conflict presentation |
| VIEW-08 | R28 | schema-version compile | Saved schema differs from current schema | Compile refuses stale schema | `SqlSavedViewRepository.php` SHA-256 `6205cae7cb87952a437e142d3e87305c39785717e843f95d72637c3cb682400e` | PASS | Consumer owns migration/re-save UX |
| VIEW-09 | R28 | PostgreSQL migration | Fresh tenant schema migration | Scope lock table and composite FK exist | `SavedViewPgSchemaTest.php` SHA-256 `67622b427a90cd5d321a076dfdb48b06f6324c517a418334457ab227d6be6301` | PASS | SQLite is not a locking proof |

Executed local checks: core Views Pest, root lint, PHPStan, non-PG, PG, Arch,
contract guard and anti-weakening gates are GREEN for the immutable candidate
`40d7d4cdb0ceee442f255b833ae231660b2d2013`.

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P4-extension-design.md | 957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a | frozen `saved-view/1` seam |
| findings/P2-contract-catalogue.md | ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573 | public-surface status |
| packages/core/src/Views/Internal/SqlSavedViewRepository.php | 6205cae7cb87952a437e142d3e87305c39785717e843f95d72637c3cb682400e | transactional persistence/default/CAS |
| packages/core/src/Views/Internal/ViewConfigValidator.php | 7d2820999492aa266a6acca63140a54beec935d3fa22554e864a93d9596007f5 | bounded safe compiler input |
| packages/core/database/migrations/pg/2026_09_15_150000_create_sys_saved_views_table.php | b27fa77854c6a457a2720d7370c20027c7e58d193beb05e6d5a0591667ecf0e0 | tenant DDL |
| packages/core/tests/Views/SavedViewPgSchemaTest.php | 67622b427a90cd5d321a076dfdb48b06f6324c517a418334457ab227d6be6301 | PG schema proof |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `saved-view/1` | C-P2 `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | This receipt binds source, DDL and tests without treating a design proposal as a release grant | Typed repository, schema provider, policy, AST validator and scoped SQL store | `corex/core` provides a neutral saved-view seam; panels optionally provides entity schema mapping | Consumer owns field-to-query mapping, row policy and product UI before integration |
