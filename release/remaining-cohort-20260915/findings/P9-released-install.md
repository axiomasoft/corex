# P9 released install receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.9

Released installed verification for frozen p9-cohort/1 via local admitted delivery endpoint (remote GitHub deferred D45).

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| RELEASE-01 | R45 | New independent consumer retrieves release bytes from declared real delivery endpoint with empty caches; candidate file URLs/path/vendor cannot substitute | `evidence:RELEASE-01` | PASS | P9.9 | PLAN-CLOSE-CI for remote endpoint if applicable |
| RELEASE-02 | R45 | Cold composer update and separate lock install verify whole released vector/upstreams/source refs/dist hashes/vendor path maps; all target runtime/profile cells covered | `evidence:RELEASE-02` | PASS | P9.9 | PLAN-CLOSE-CI for remote endpoint if applicable |
| RELEASE-03 | R45 R51 B11 AC-28/29/30 | Released non-CRM boxed/physical and each included contribution behavioral conformance repeated; same auth private/public separation retained | `evidence:RELEASE-03` | PASS | P9.9 | PLAN-CLOSE-CI for remote endpoint if applicable |
| RELEASE-04 | R45 | Released populated-baseline upgrade/refusal/recovery checks use unchanged accepted fixture/recipe; previous candidate receipt reused only for identical content semantics with explicit binding | `evidence:RELEASE-04` | PASS | P9.9 | PLAN-CLOSE-CI for remote endpoint if applicable |
| RELEASE-05 | R45 R51 | Compatibility passport distinguishes declared/locally executed/hosted/released installed and explicit exclusions; static report not speculative compatibility runtime generator | `evidence:RELEASE-05` | PASS | P9.9 | PLAN-CLOSE-CI for remote endpoint if applicable |
| RELEASE-06 | R45 R51 | Exact source/test/hosted/release/installed pins and accountable acceptance bound to every included R; G-API/G-DEPENDENCY/G-PRIVATE/G-RUNNER/G-RELEASE satisfied | `evidence:RELEASE-06` | PASS | P9.9 | PLAN-CLOSE-CI for remote endpoint if applicable |
| RELEASE-07 | R45 R51 | Final released-cohort seal producer edges P10/P11 retain every DI/AP/provider/timing/product owner; release not live deployment or aggregate consumer acceptance | `evidence:RELEASE-07` | PASS | P9.9 | PLAN-CLOSE-CI for remote endpoint if applicable |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/build/build-attestation.json` | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | build attestation |
| `delivery/remaining-cohort-20260915/publication/receipt.json` | `5dead06b0f6037941443846024aa59850f2e5b8bc3195b5d8ac3c830801327df` | publication receipt |
| `delivery/remaining-cohort-20260915/hosted/receipt.json` | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | hosted receipt |
| `delivery/remaining-cohort-20260915/private/receipt.json` | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | private receipt |
| `delivery/remaining-cohort-20260915/installed-candidate/receipt.json` | `ebf9fb5aefbbf98bdc1da7d1c804d59df860c67ca1b006d0a210564514ef6042` | installed candidate receipt |
| `delivery/remaining-cohort-20260915/released-installed/delivery-manifest.json` | `7abeb94ef277f8eb5ec74c6f8bf15c3448434d916354b2e7533d2e7c069821c4` | admitted delivery manifest |
| `delivery/remaining-cohort-20260915/released-installed/receipt.json` | `da1d0a565e06fadff192dc17269ad8a8c149bbf7bf840a257f2edc849373bb70` | released installed receipt |
| `delivery/remaining-cohort-20260915/public/released-cohort.json` | `f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee` | released cohort manifest |
| `scripts/distribution/remaining-cohort-released.py` | `9ef55f1a0d6528ab64d6135143f84736261390c202b263ead3b4e59c8c25044d` | released install recipe |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| RELEASE-01 | `delivery/remaining-cohort-20260915/evidence/release-01.json` | `02cba9c609f5303d10f5aad5793a632413c84243edaa8a405d8e027df051c444` | released-installed | p9-cohort/1 | released-cold-delivery | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-released.py --verify-release-01` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:47.260938+00:00` | `2026-09-18T18:24:47.930611+00:00` | 0 | PASS |
| RELEASE-02 | `delivery/remaining-cohort-20260915/evidence/release-02.json` | `d00b0a6b63ecc68d42e1449f739165a993943fbf32c744f535e0f74dda851790` | released-installed | p9-cohort/1 | released-vector-lock | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-released.py --verify-release-02` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:47.932064+00:00` | `2026-09-18T18:24:48.742563+00:00` | 0 | PASS |
| RELEASE-03 | `delivery/remaining-cohort-20260915/evidence/release-03.json` | `1c9637e24373aa46cf3edcee92d3b2d103a6938ebff3c27b3c99e1798e423ece` | released-installed | p9-cohort/1 | released-conformance | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-released.py --verify-release-03` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:48.744082+00:00` | `2026-09-18T18:24:49.386528+00:00` | 0 | PASS |
| RELEASE-04 | `delivery/remaining-cohort-20260915/evidence/release-04.json` | `9b037ec5491422022d8b5e4fe74f209c5f8cc3334fa93282b3847e64431a1a5e` | released-installed | p9-cohort/1 | released-upgrade-binding | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-released.py --verify-release-04` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:49.389023+00:00` | `2026-09-18T18:24:50.047644+00:00` | 0 | PASS |
| RELEASE-05 | `delivery/remaining-cohort-20260915/evidence/release-05.json` | `af649ac8b42d658e621b9a3996eaeb1af154a75f152f22787937e857710b5f92` | released-installed | p9-cohort/1 | compatibility-passport | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-released.py --verify-release-05` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:50.049792+00:00` | `2026-09-18T18:24:50.085144+00:00` | 0 | PASS |
| RELEASE-06 | `delivery/remaining-cohort-20260915/evidence/release-06.json` | `71244de2f53c1db392e5f405edb2ae07e3e3c770530a6ccc473754cfe1cb447d` | released-installed | p9-cohort/1 | cohort-requirements | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-released.py --verify-release-06` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:50.087314+00:00` | `2026-09-18T18:24:50.128807+00:00` | 0 | PASS |
| RELEASE-07 | `delivery/remaining-cohort-20260915/evidence/release-07.json` | `aa89f70da15a9893f09dd78c5cfc1d0f25b3b370cc2905e9285402710afe3ffd` | released-installed | p9-cohort/1 | producer-edges | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-released.py --verify-release-07` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:50.130865+00:00` | `2026-09-18T18:24:50.167148+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| released-installed/1 | G-RELEASE | `da1d0a565e06fadff192dc17269ad8a8c149bbf7bf840a257f2edc849373bb70` | P9.9 | PASS | P10 effective qualification |
| publication-receipt/1 | G-RELEASE | `5dead06b0f6037941443846024aa59850f2e5b8bc3195b5d8ac3c830801327df` | P9.8 | PARTIAL-D45 | PLAN-CLOSE-CI remote reconcile |
| p9-build-attestation/1 | G-RELEASE | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | P9.2 | PASS | unchanged build join |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
