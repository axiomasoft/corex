# P9 publication receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.8

Actual publication reconciliation for frozen p9-publication-packet/1.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| PUB-01 | R45 R51 | At write boundary current accepted grant/operation/artifact/source/credential scope exactly match sealed packet; stale/revoked/wrong repository denied | evidence:PUB-01 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-02 | R45 R51 | Already matching immutable refs/assets reused as verified no-op with receipt; historical four-package publication never rerun | evidence:PUB-02 | FAIL | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-03 | R45 R51 | Local verified split refs pushed only exact admitted objects, tag points to expected split commit/tree; no force/move/delete | evidence:PUB-03 | FAIL | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-04 | R45 R51 | Exact dist assets staged draft then publication via approved channel; immutable-release availability observed, not assumed or enabled through ungranted admin action | evidence:PUB-04 | FAIL | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-05 | R45 R51 | Composer package metadata/index observed exact names/versions/source/dist refs and dependency closure in consumer-owned root repository list | evidence:PUB-05 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-06 | R45 R51 | Timeout/unknown upload/push/release queries remote state+downloads actual bytes before retry; unresolved unknown prevents completion and blind repeat | evidence:PUB-06 | FAIL | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-07 | R45 R51 | Version collision/different bytes fails closed; recovery creates separately approved new version, never silently replaces published identity | evidence:PUB-07 | FAIL | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-08 | R45 R51 | Every full cohort operation reconciled with actual public/approved private channel receipts; partial public result retains ledger without claiming release complete | evidence:PUB-08 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/publication-packet.json` | `1fcf165edfe6df4f281b6690321513c79e81138431e1c602194ab199b55796fc` | publication packet |
| `delivery/remaining-cohort-20260915/publication-ledger.json` | `04f3fdd2283e2f1454dd276259ae7529b8ff9f3af6265ff5465f0d622ddbb5db` | publication ledger |
| `delivery/remaining-cohort-20260915/publication/receipt.json` | `df91933fb1fb1b1ed8f80b323f1d61e4d972c0d27817c5b09da92464102a0def` | publication receipt |
| `delivery/remaining-cohort-20260915/publication/preflight.json` | `aa86ee12572a52ea42fa8168bbd8cc9527517ff406867b64aadf1710eec08602` | publication preflight |
| `findings/P9-release-gate-request.md` | `a75aec97edafd218f80efff2a985620e49dc0a029696f1cb7a74bebf78f04837` | G-RELEASE gate request |
| `scripts/distribution/remaining-cohort-publish.py` | `26963675ebca736c66038acec7ca4a69a649be624a05c471ec1f074b1bd32ab6` | publication helper recipe |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PUB-01 | `delivery/remaining-cohort-20260915/evidence/pub-01.json` | `3079fb4873c24d87a45aba645495adc107830d355a8518c26ee28bfb7ac54767` | release | p9-cohort/1 | publication-grant-boundary | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-01` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T10:36:09.090129+00:00` | `2026-09-19T10:36:09.140715+00:00` | 0 | PASS |
| PUB-02 | `delivery/remaining-cohort-20260915/evidence/pub-02.json` | `5e2e1844124c7de4bcb1c0c2d858d2e2e1eef844c67683a9061cb307d48ddd93` | release | p9-cohort/1 | publication-historical-noop | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-02` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T10:36:09.143357+00:00` | `2026-09-19T10:36:09.199146+00:00` | 1 | FAIL |
| PUB-03 | `delivery/remaining-cohort-20260915/evidence/pub-03.json` | `266ee88d25fb84c7778358d6b3f32ea73c037df63b689a2fca3681fb2b409007` | release | p9-cohort/1 | publication-split-tag | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-03` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T10:36:09.201403+00:00` | `2026-09-19T10:36:09.251537+00:00` | 1 | FAIL |
| PUB-04 | `delivery/remaining-cohort-20260915/evidence/pub-04.json` | `30fba9f8c8e56f328c58fcd382efccebbc1fbcc53556aea94eae2612ab8d90cb` | release | p9-cohort/1 | publication-release-asset | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-04` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T10:36:09.253938+00:00` | `2026-09-19T10:36:09.304234+00:00` | 1 | FAIL |
| PUB-05 | `delivery/remaining-cohort-20260915/evidence/pub-05.json` | `d12612c0f308242bf2f3ed2865f8985ae4b0032a9a7e9af9c4139939a41d229e` | release | p9-cohort/1 | publication-metadata-index | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-05` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T10:36:09.306443+00:00` | `2026-09-19T10:36:09.357166+00:00` | 0 | PASS |
| PUB-06 | `delivery/remaining-cohort-20260915/evidence/pub-06.json` | `0f1797a5848e43f92643a7a2d8af8cd58a38268a84435bb914f88c809ef486a0` | release | p9-cohort/1 | publication-query-before-retry | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-06` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T10:36:09.359168+00:00` | `2026-09-19T10:36:09.412647+00:00` | 1 | FAIL |
| PUB-07 | `delivery/remaining-cohort-20260915/evidence/pub-07.json` | `6e1c031841ee1450101d6d746c57356cd27164b2ced8e2121f8c4ba2f8ff6bda` | release | p9-cohort/1 | publication-collision-refusal | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-07` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T10:36:09.414232+00:00` | `2026-09-19T10:36:09.467784+00:00` | 1 | FAIL |
| PUB-08 | `delivery/remaining-cohort-20260915/evidence/pub-08.json` | `a79267366edc628145f6d479b38175fb12c290fa5a43a04ccb3ee1c540b57af4` | release | p9-cohort/1 | publication-ledger-partial | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-08` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T10:36:09.470065+00:00` | `2026-09-19T10:36:09.524402+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| p9-publication-packet/1 | G-RELEASE | `1fcf165edfe6df4f281b6690321513c79e81138431e1c602194ab199b55796fc` | P9.7 | PASS | exact publication operations sealed |
| publication-receipt/1 | G-RELEASE | `df91933fb1fb1b1ed8f80b323f1d61e4d972c0d27817c5b09da92464102a0def` | P9.8 | PENDING | P9.9 released install pending |
| hosted-receipt/1 | G-RUNNER | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | P9.5 | PASS | unchanged hosted join |
| private-receipt/1 | G-PRIVATE | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | P9.6 | PASS | unchanged private join |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
