# P4.1 panels receipts

Version: p4-extension-receipts/1. `corex/panels` is a headless, read-only
projection of tagged provider contributions and the current module/entity
registries. It does not add a persistence registry, product command, cached
access token, parent-metadata grant, or a Flex dependency.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| PAN-01 | R14 | ModulePanelCatalogue / two neutral entities | Active `example.note` (auditable) and `example.asset` (documentable), with two capability-scoped providers | Each entity discovers only the descriptor whose existing capability it supports | `ModulePanelCatalogueTest.php` SHA-256 `094133e42943832b49ddc9861ed671968c27c20a745a45b79d053cfa4b14ec3a`; package Pest GREEN | PASS | Product-specific labels and actions remain provider-owned |
| PAN-02 | R14 | ModulePanelCatalogue / default deny | Unknown entity/provider/panel and a throwing policy; provider returns an unregistered action | No descriptor or a typed deny; policy failure and invalid output never allow | `ModulePanelCatalogueTest.php` SHA-256 `094133e42943832b49ddc9861ed671968c27c20a745a45b79d053cfa4b14ec3a`; package Pest GREEN | PASS | Panel-id/action vocabulary remains opaque and is validated by its provider |
| PAN-03 | R14 | DI-tagged providers / boxed no-provider profile | Empty tag and a currently disabled provider | No descriptor; construction/listing never invokes provider policy or optional secret lookup | `ModulePanelCatalogueTest.php` SHA-256 `094133e42943832b49ddc9861ed671968c27c20a745a45b79d053cfa4b14ec3a`; package Pest GREEN | PASS | Consumer supplies tagged providers explicitly |
| PAN-04 | R14 | ModulePanelCatalogue / current registry state | Descriptor was discovered, then owning entity or provider is disabled while the morph handle still resolves | Access is denied from current entity/provider state, never from a stale descriptor | `ModulePanelCatalogueTest.php` SHA-256 `094133e42943832b49ddc9861ed671968c27c20a745a45b79d053cfa4b14ec3a`; `ModulePanelCatalogue.php` SHA-256 `b83f7968b064d23080f21d46255cc2d9544e3d2263986bd027c03d2b9b53ea19` | PASS | EntityRegistry remains the owner of morph resolution; the catalogue is not a visibility authority |
| PAN-05 | R14 | Provider policy / derived-grant revocation | A previous grant allows, then the provider's current parent-read check is revoked | Fresh access re-evaluates policy and denies; no access result is cached or executable | `ModulePanelCatalogueTest.php` SHA-256 `094133e42943832b49ddc9861ed671968c27c20a745a45b79d053cfa4b14ec3a`; package Pest GREEN | PASS | Product write boundary must call its policy again; P4.1 deliberately exposes no command API |
| PAN-06 | R14 | Independent guest provider / platform deny precedence | Explicit provider grant for a guest panel without parent, followed by provider disable | Guest can view only the standalone panel; current platform disable wins | `ModulePanelCatalogueTest.php` SHA-256 `094133e42943832b49ddc9861ed671968c27c20a745a45b79d053cfa4b14ec3a`; package Pest GREEN | PASS | Parent metadata is neither looked up nor disclosed by this API |
| PAN-07 | R14 | Tagged DI / A-B reused worker | Duplicate provider key and one catalogue called A → B → A with current state differing by account | Duplicate key fails at boot; B cannot inherit A discovery/access state; A remains correct after reuse | `ModulePanelCatalogueTest.php` SHA-256 `094133e42943832b49ddc9861ed671968c27c20a745a45b79d053cfa4b14ec3a`; package Pest GREEN | PASS | Physical connection selection is owned by tenancy; this package only consumes trusted TenantContext |

Execution envelope: `composer --working-dir=packages/panels test` passed 10 tests / 44
assertions; Pint and PHPStan passed. With Xdebug enabled under system PHP 8.4,
`vendor/bin/pest --coverage --min=80` passed at 100.0% lines. Root lint,
PHPStan, non-PG (156), PG (110), Arch (6), contract guard, anti-weakening and
34 mutation checks also passed. PostgreSQL preflight confirmed the healthy
Docker service and the sole target `corex_test` before the PG lane.

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P4-extension-design.md | 957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a | frozen panel/1 seam and PAN invariants |
| findings/P2-contract-catalogue.md | ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573 | C-P2-007 catalogue status and P4.1 consumer boundary |
| packages/panels/composer.json | 6d96e2ddf1b3a3c72129138c7ccfbefc7fe509066c7d36e21905a4599af7eb13 | package identity and CoreX module registration |
| packages/panels/src/Contracts/PanelProvider.php | 2ab3847b8025bcae1c5fe98e516ddc89b99b33846597ade4d13715de1e0e6acc | provider public seam |
| packages/panels/src/Contracts/PanelCatalogue.php | 1373675c6a8e9cf7bffd516ec6282d6c06ac35243a248d7761ebea9d3b9fc726 | catalogue public seam |
| packages/panels/src/Data/PanelDescriptor.php | f8e90535634f3c2d06a75a27a04727e2c9f5c72369ebd87ab66af7e84e8a045e | immutable descriptor boundary |
| packages/panels/src/Data/PanelContext.php | 9bd86fb930a530fd59c1f17f0e79359dbaead63a97a0874df7c52743fa0ae1f5 | trusted context boundary |
| packages/panels/src/Data/PanelAccess.php | 787fecd0ce97c8874f4106e67cbc720a3b54392e2f1d7f59b6ff3c00b62132a4 | uncached access result boundary |
| packages/panels/src/Internal/ModulePanelCatalogue.php | b83f7968b064d23080f21d46255cc2d9544e3d2263986bd027c03d2b9b53ea19 | current-state, fail-closed projection |
| packages/panels/src/PanelsServiceProvider.php | 6c3346ca6e2a2de86cf237a258a1b1c310870986762393b553b2ffe6c86e4144 | tagged DI installation |
| packages/panels/tests/Unit/ModulePanelCatalogueTest.php | 094133e42943832b49ddc9861ed671968c27c20a745a45b79d053cfa4b14ec3a | executed PAN conformance |
| tests/Feature/ModuleDiscoveryTest.php | a1339b43d19627de3e86336776de34ddc61c80386f5483ba969a284b9ce3d84e | root discovery regression after adding a CoreX module |
| composer.lock | 3d681209d911835a63973a846d5711dc9e15e8b06efbcda6deda703b6357ba3c | root installation graph including corex/panels |
| .github/workflows/ci.yml | 1023abb451201365f7f62fd1dcf44ed1d4b7f5ffeb5f3073643031b945e2cfe9 | isolated package CI coverage lane |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `panel/1` provider/catalogue seam | C-P2-007 `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | This receipt binds the implemented source, test, config and CI hashes; P2 catalogue remains explicit that original G-API/G-RELEASE/G-CONSUMER evidence is not fabricated | `ModulePanelCatalogue`: tagged read-only providers + current `EntityRegistry`/`ModuleRegistry`, no persistence or command execution | `corex/panels` exports `PanelProvider` and `PanelCatalogue` at `panel/1`; provider registration tag is `corex.panels.providers` | CoreX contract/release and consumer owners: complete independent API/release/consumer evidence before a public stable release or product integration |
