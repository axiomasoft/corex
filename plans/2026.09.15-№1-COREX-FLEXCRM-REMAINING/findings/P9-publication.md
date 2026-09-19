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
| `delivery/remaining-cohort-20260915/publication-ledger.json` | `71cfe7212f90ff6fc9b7fecab13115c46c54e6494ce82d693331dd750d15842c` | publication ledger |
| `delivery/remaining-cohort-20260915/publication/receipt.json` | `ea38b603e7219f0b9e37b449c791c1a4f1c711089049c7eb58d4c68d663ea2ed` | publication receipt |
| `delivery/remaining-cohort-20260915/publication/preflight.json` | `aa86ee12572a52ea42fa8168bbd8cc9527517ff406867b64aadf1710eec08602` | publication preflight |
| `findings/P9-release-gate-request.md` | `a75aec97edafd218f80efff2a985620e49dc0a029696f1cb7a74bebf78f04837` | G-RELEASE gate request |
| `scripts/distribution/remaining-cohort-publish.py` | `26963675ebca736c66038acec7ca4a69a649be624a05c471ec1f074b1bd32ab6` | publication helper recipe |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PUB-01 | `delivery/remaining-cohort-20260915/evidence/pub-01.json` | `94d929d5cf143b438a6372da1fb1ba27949de9b0be1f2d0413bb4eca1651f99f` | release | p9-cohort/1 | publication-grant-boundary | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-01` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T09:32:28.394124+00:00` | `2026-09-19T09:32:28.462696+00:00` | 0 | PASS |
| PUB-02 | `delivery/remaining-cohort-20260915/evidence/pub-02.json` | `199dee8147da665e6eb23e7601e1ab3cc13742c1e92ce22307174a5317ab1bbe` | release | p9-cohort/1 | publication-historical-noop | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-02` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T09:32:28.466599+00:00` | `2026-09-19T09:32:28.518573+00:00` | 1 | FAIL |
| PUB-03 | `delivery/remaining-cohort-20260915/evidence/pub-03.json` | `a66e5b7e601ccf1fff0240a0b11986ee027108e2ee8a17bb07fdf6837e4a7d2d` | release | p9-cohort/1 | publication-split-tag | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-03` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T09:32:28.520658+00:00` | `2026-09-19T09:32:28.571279+00:00` | 1 | FAIL |
| PUB-04 | `delivery/remaining-cohort-20260915/evidence/pub-04.json` | `889209f56cfd1bcbd823da8d0de2dd58648acd0bec00097035807474babfcc2f` | release | p9-cohort/1 | publication-release-asset | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-04` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T09:32:28.573441+00:00` | `2026-09-19T09:32:28.620035+00:00` | 1 | FAIL |
| PUB-05 | `delivery/remaining-cohort-20260915/evidence/pub-05.json` | `7eaff0cbecf5ded774a73c265fc998787cc2a4d26a888381653b6284ede19513` | release | p9-cohort/1 | publication-metadata-index | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-05` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T09:32:28.622683+00:00` | `2026-09-19T09:32:28.674364+00:00` | 0 | PASS |
| PUB-06 | `delivery/remaining-cohort-20260915/evidence/pub-06.json` | `9ac057b309634ecfde2ff4b7f5f8c97a4caab5714330803f40950df8d60fa64f` | release | p9-cohort/1 | publication-query-before-retry | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-06` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T09:32:28.676639+00:00` | `2026-09-19T09:32:28.725431+00:00` | 1 | FAIL |
| PUB-07 | `delivery/remaining-cohort-20260915/evidence/pub-07.json` | `371d334e05e1411764e035dd0ac1fd067d3fc2a2cb5e5680cbe91e69bdf18157` | release | p9-cohort/1 | publication-collision-refusal | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-07` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T09:32:28.727166+00:00` | `2026-09-19T09:32:28.773947+00:00` | 1 | FAIL |
| PUB-08 | `delivery/remaining-cohort-20260915/evidence/pub-08.json` | `28634a0c9a1e732f0945007712dba49572571857d9c35bbfee4d221688c3c4f6` | release | p9-cohort/1 | publication-ledger-partial | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-08` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T09:32:28.776446+00:00` | `2026-09-19T09:32:28.824700+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| p9-publication-packet/1 | G-RELEASE | `1fcf165edfe6df4f281b6690321513c79e81138431e1c602194ab199b55796fc` | P9.7 | PASS | exact publication operations sealed |
| publication-receipt/1 | G-RELEASE | `ea38b603e7219f0b9e37b449c791c1a4f1c711089049c7eb58d4c68d663ea2ed` | P9.8 | PENDING | P9.9 released install pending |
| hosted-receipt/1 | G-RUNNER | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | P9.5 | PASS | unchanged hosted join |
| private-receipt/1 | G-PRIVATE | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | P9.6 | PASS | unchanged private join |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
