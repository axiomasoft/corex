# P9 publication packet receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.7

Read-only publication packet and G-RELEASE gate request for frozen remaining cohort.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| PACKET-01 | R45 R51 | Exact candidate digest and per-package/source/split/tag/dist/channel file set sealed after all local/install/hosted/private inputs, old publication excluded | evidence:PACKET-01 | PASS | P9.7 | P9.8 G-RELEASE publication |
| PACKET-02 | R45 R51 | Finite operation matrix enumerates repository visibility, immutable ref/object/asset/digest, expected old state, dependency ordering and credential scope; no repo admin/force/delete extras | evidence:PACKET-02 | PASS | P9.7 | P9.8 G-RELEASE publication |
| PACKET-03 | R45 R51 | BC/major/changelog/profile/DDL/private/source licenses approved for actual bytes; fixture schema approval does not grant production DDL | evidence:PACKET-03 | PASS | P9.7 | P9.8 G-RELEASE publication |
| PACKET-04 | R45 R51 | Draft/tag/assets/index plan differentiates partial states and query-before-retry; independent lookup/download/reconcile command for every effect | evidence:PACKET-04 | PASS | P9.7 | P9.8 G-RELEASE publication |
| PACKET-05 | R45 R51 | G-RELEASE request is digest-bound to full finite operations and fresh grants; preparer cannot mint consent, no publication in this item | evidence:PACKET-05 | PASS | P9.7 | P9.8 G-RELEASE publication |
| PACKET-06 | R45 R51 | P10/P11 same-cohort handoff includes full R/AC residual DI/AP/real provider/timing/consumer owners; NO-GO is not phase GREEN | evidence:PACKET-06 | PASS | P9.7 | P9.8 G-RELEASE publication |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/build/build-attestation.json` | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | build attestation |
| `delivery/remaining-cohort-20260915/build/export-map.json` | `cac29e6114302637bf8b30eb798f254c54e5415ecd22fe6f8abe3bc0162f295c` | export map |
| `delivery/remaining-cohort-20260915/public/packages.json` | `1f2ea988aa5cb722d00d60bf43077efdf7dcc2ef6f301a9e58d06423da8886a6` | public composer metadata |
| `delivery/remaining-cohort-20260915/hosted/receipt.json` | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | hosted receipt |
| `delivery/remaining-cohort-20260915/installed-candidate/receipt.json` | `ebf9fb5aefbbf98bdc1da7d1c804d59df860c67ca1b006d0a210564514ef6042` | installed candidate receipt |
| `delivery/remaining-cohort-20260915/private/receipt.json` | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | private receipt |
| `delivery/remaining-cohort-20260915/publication-packet.json` | `1fcf165edfe6df4f281b6690321513c79e81138431e1c602194ab199b55796fc` | publication packet |
| `delivery/remaining-cohort-20260915/publication-ledger.json` | `cb2f951a4fad095f9991c318ae6700690276dd387fc49cd3b7dfffd63c5ef675` | publication ledger |
| `findings/P9-release-gate-request.md` | `a75aec97edafd218f80efff2a985620e49dc0a029696f1cb7a74bebf78f04837` | G-RELEASE gate request |
| `scripts/distribution/remaining-cohort-publish.py` | `e6965bb6e10d640ea1b97c1ca3f0e9d334124e2a9b2d0a5bcc5970e54fd30205` | publication helper recipe |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PACKET-01 | `delivery/remaining-cohort-20260915/evidence/packet-01.json` | `30d1af361cd3cb448522eaa2b491896bf9f2e7813f4c0e3d958d9fe224138b1e` | packet | p9-cohort/1 | packet-seal | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-packet-01` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:40.347297+00:00` | `2026-09-19T05:40:40.406200+00:00` | 0 | PASS |
| PACKET-02 | `delivery/remaining-cohort-20260915/evidence/packet-02.json` | `7e9c04dfc828da84c0d4028ea13ed7ec047be7979008c71c57331bbc3f1bf827` | packet | p9-cohort/1 | packet-operations | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-packet-02` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:40.408685+00:00` | `2026-09-19T05:40:40.463621+00:00` | 0 | PASS |
| PACKET-03 | `delivery/remaining-cohort-20260915/evidence/packet-03.json` | `7bc44f6b52968c7a605c37c10c7d6cf124a63256d8515fa16359d2a04d50f5e3` | packet | p9-cohort/1 | packet-approvals | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-packet-03` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:40.466006+00:00` | `2026-09-19T05:40:40.523338+00:00` | 0 | PASS |
| PACKET-04 | `delivery/remaining-cohort-20260915/evidence/packet-04.json` | `4e22cce637bc2a46cf0243ff156e66d452ff67ec48f0b4f33e04c85f3614b73f` | packet | p9-cohort/1 | packet-reconcile | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-packet-04` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:40.525362+00:00` | `2026-09-19T05:40:40.576828+00:00` | 0 | PASS |
| PACKET-05 | `delivery/remaining-cohort-20260915/evidence/packet-05.json` | `857b8f4640f02e27776a67bd90c72d8c6bb92ccf5815622cf5f3c92604c332c8` | packet | p9-cohort/1 | packet-gate-request | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-packet-05` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:40.578921+00:00` | `2026-09-19T05:40:40.629145+00:00` | 0 | PASS |
| PACKET-06 | `delivery/remaining-cohort-20260915/evidence/packet-06.json` | `7eb2b0a2652363d3d5c84cdd046b1a1ec4dfa0c78199f0f93cbb37996dd33d99` | packet | p9-cohort/1 | packet-handoff | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-packet-06` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:40.632868+00:00` | `2026-09-19T05:40:40.684786+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| p9-publication-packet/1 | G-RELEASE | `1fcf165edfe6df4f281b6690321513c79e81138431e1c602194ab199b55796fc` | P9.7 | PASS | P9.8 G-RELEASE publication |
| hosted-receipt/1 | G-RUNNER | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | P9.5 | PASS | unchanged hosted join |
| private-receipt/1 | G-PRIVATE | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | P9.6 | PASS | unchanged private join |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
