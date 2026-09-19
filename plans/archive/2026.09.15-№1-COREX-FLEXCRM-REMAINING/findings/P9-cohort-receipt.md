# P9 released cohort seal

**Schema:** p9-released-cohort/1
**Item:** P9.9

Final same-cohort source/test/hosted/released-installed seal for P10/P11.

## Requirements

| R | Packages | Source | Test | Hosted | Release | Installed | Gates | Residual owner |
|---|---|---|---|---|---|---|---|---|
| R45 | 18 selected public packages | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | local+hosted matrix | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | `5dead06b0f6037941443846024aa59850f2e5b8bc3195b5d8ac3c830801327df` partial D45 | `da1d0a565e06fadff192dc17269ad8a8c149bbf7bf840a257f2edc849373bb70` | G-RUNNER/G-PRIVATE PASS; G-RELEASE partial | P10/P11 deployment/consumer |
| R51 | public no-private + optional auth profile | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | PRIVATE-01..06 | hosted unchanged | public channel only | released boxed/profile retained | G-PRIVATE PASS | AzGuard/FlexCRM policy owner |

## Producer edges

| Producer | Contract/version | Consumer | Required receipt | Failure model |
|---|---|---|---|---|
| P9.1–P9.8 | p9-cohort-receipts/1 branch reports | P9.9 | nine branch findings | missing branch blocks seal |
| P9.9 | released-installed/1 | P10 | this seal + passport + released-cohort.json | deployment separately owned |
| P9.9 | p9-released-cohort/1 | P11 | this seal + passport + released-cohort.json | consumer acceptance separately owned |

## Residual owners (P10/P11)

| ID | Obligation | Consumer | Owner |
|---|---|---|---|
| DI01–DI34 | Central/cloud/timing/provider obligations | P10 effective gates | P10 owner |
| AP01–AP13 | Application/product acceptance parents | P11 consumer reconciliation | P11 owner |
| G-API/G-DEPENDENCY | Contract/runtime adoption outside release bytes | P10/P11 qualification | Release owner |
| Live deployment | No live deployed/consumer acceptance in P9 | P10 provider + P11 consumer | Product owner |

## Sealed inputs

| Path | SHA256 |
|---|---|
| `delivery/remaining-cohort-20260915/public/released-cohort.json` | `f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee` |
| `findings/P9-artifacts.md` | `582d5d71dc0afc706ce3910dda556689f6150e3c08f936d4be9aaf648f92852f` |
| `findings/P9-compatibility-passport.md` | `6ecf707ec8d153d52c8d7f85364ba550b471362aa1b13e98372735d343b65df6` |
| `findings/P9-hosted-runs.md` | `2206abf5023f713f81e108e210ef213eb3408496109100e5240e7f426b47de64` |
| `findings/P9-installed-candidate.md` | `02b9c9f0140780a6853934e87380d0bfb81321553a2334cd42cc4a138cf8e295` |
| `findings/P9-intake.md` | `4321e6c9725528df2e0d3a50d3740227d3559161aad774ee67cee30d629c004e` |
| `findings/P9-local-matrix.md` | `8952131b8cb5e1db316d47b9926dbab25d918b9d4284bbadc1b6bfd845c1adc4` |
| `findings/P9-private-auth.md` | `4b52afeb49dc7d8caf3443f0c898c2a13c9a06485e8ad869045b4e70c7a1055d` |
| `findings/P9-publication-packet.md` | `3a2720603b19be3533898f6e712c58174e8f91bfc6ba1c13d620ab3a34e0dd38` |
| `findings/P9-publication.md` | `81c92a2c597539a24a430c22491f2bef144f8517a27f2e7619c3876faa085e2a` |
| `findings/P9-released-install.md` | `0a4190768c0a19969da47ab1e20b7a07fe37f8dad796fedfcf7ebf39918885e4` |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` |

## Released cohort

- manifest: `delivery/remaining-cohort-20260915/public/released-cohort.json` (`f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee`)
- released-installed receipt: `da1d0a565e06fadff192dc17269ad8a8c149bbf7bf840a257f2edc849373bb70`
- compatibility passport: `6ecf707ec8d153d52c8d7f85364ba550b471362aa1b13e98372735d343b65df6`
- candidate pass: unchanged intake identity bound through AWR-08 joins
