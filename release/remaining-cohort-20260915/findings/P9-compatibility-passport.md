# P9 compatibility passport

**Schema:** p9-compatibility-passport/1
**Item:** P9.9

Static compatibility report for frozen p9-cohort/1. Not a speculative runtime compatibility generator.

## Declared support

| Surface | Digest | Owner | Notes |
|---|---|---|---|
| p9-cohort/1 candidate | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | frozen intake identity |
| profile-plan/1 | `5dd5972ab65d1793b5ae12a418fde23c85cabc9f9faa8cc3976548251a7e9ee1` | P9.3 | Policy A public/private cells |
| build-attestation/1 | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | P9.2 | reproducible dist/split trees |

## Locally executed

| Surface | Digest | Cases | Notes |
|---|---|---|---|
| local matrix | `dbdb6f0fa93029c125d1f12c99d460c808a160af7cbabe239a42b0dbb7224edb` | MATRIX-01..07 | 106 cells local proof |
| installed-candidate/1 | `ebf9fb5aefbbf98bdc1da7d1c804d59df860c67ca1b006d0a210564514ef6042` | INSTALL-01..09 | neutral lab consumer, no path repo |

## Hosted

| Surface | Digest | Cases | Notes |
|---|---|---|---|
| hosted-receipt/1 | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | RUN-01..07 | actual workflow run bound |

## Released installed

| Surface | Digest | Cases | Notes |
|---|---|---|---|
| released-installed/1 | `da1d0a565e06fadff192dc17269ad8a8c149bbf7bf840a257f2edc849373bb70` | RELEASE-01..04 | local admitted delivery endpoint |
| released-cohort/1 | `f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee` | RELEASE-06 | joins publication/install results |

## Explicit exclusions

| Exclusion | Owner | Resume |
|---|---|---|
| Remote GitHub composer install against live split repositories | PLAN-CLOSE-CI | D45 deferral while `scripts/.plan-close-deferred` active |
| Full G-RELEASE publication reconcile (PUB-03..05) | PLAN-CLOSE-CI | owner grant + network at plan close |
| Live deployed / aggregate consumer acceptance | P10/P11 | release bytes ≠ deployment proof |
| corex/auth optional private profile runtime in public channel | P9.6/P10 | private envelope separately owned |

## Gate summary

| Gate | Receipt | Status | Resume |
|---|---|---|---|
| G-RUNNER | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | PASS | unchanged |
| G-PRIVATE | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | PASS | unchanged |
| G-RELEASE | `5dead06b0f6037941443846024aa59850f2e5b8bc3195b5d8ac3c830801327df` | PARTIAL-D45 | PLAN-CLOSE-CI remote reconcile |
