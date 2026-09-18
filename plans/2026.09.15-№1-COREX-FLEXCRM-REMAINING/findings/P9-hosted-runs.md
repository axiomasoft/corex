# P9 hosted run receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.5

Actual hosted same-cohort workflow runs for frozen runner-packet/1.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| RUN-01 | R45 | Hosted runs bind source/artifact/recipe/workflow SHA, exact event/ref/runId/attempt/jobId/URLs, actual platform/image and locks, no local receipt substitution | evidence:RUN-01 | PASS | P9.5 | P9.6 private read |
| RUN-02 | R45 | Every mandatory matrix/test/coverage/mutation/installed-distribution job observed success; skipped/cancelled/conditional miss not green | evidence:RUN-02 | PASS | P9.5 | P9.6 private read |
| RUN-03 | R45 | Pull-request merge SHA or current branch differs from frozen source: verify exact exported package maps and recipe, or rerun matching candidate; badge insufficient | evidence:RUN-03 | PASS | P9.5 | P9.6 private read |
| RUN-04 | R45 | Workflow/action refs pinned immutable and permissions least privilege; private job separated/no credential propagation to public jobs or untrusted checkout | evidence:RUN-04 | PASS | P9.5 | P9.6 private read |
| RUN-05 | R45 | Raw hosted logs/results/locks/artifact hashes retained and independently verified against candidate; expired artifact without retained proof cannot pass | evidence:RUN-05 | PASS | P9.5 | P9.6 private read |
| RUN-06 | R45 | Rerun partial/unknown dispatch queried by exact candidate/run identity before any repeat; run attempt not conflated with earlier successful job | evidence:RUN-06 | PASS | P9.5 | P9.6 private read |
| RUN-07 | R45 | Missing runner capability/grant yields exact source/job/resume evidence; packet only never counts hosted acceptance | evidence:RUN-07 | PASS | P9.5 | P9.6 private read |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `cd764b9657b643eeccd324b4afefe6681078df6049a0e0640effbac332a9061c` | frozen intake identity |
| `delivery/remaining-cohort-20260915/runner-packet.json` | `a93f5923dc92fad9ebb619e332dd9af5f48d1e4999530d4e65e2c70772e7e4b9` | final runner packet |
| `delivery/remaining-cohort-20260915/runner-plan.json` | `0916cddd257456894402195ee682bbfdb4f7de576c8720208fdaccfd78f2c6e8` | immutable hosted runner plan |
| `delivery/remaining-cohort-20260915/installed-candidate/receipt.json` | `ac233beb0fa79e48b26c7936f9acc6e3c273f545455a8a035d7e008df3ae9035` | installed candidate receipt |
| `.github/workflows/remaining-cohort-proof.yml` | `12e6736bb63071cfe83fe094f526a3232c72d1241ab53099ab7966c516cc40fc` | hosted workflow helper |
| `delivery/remaining-cohort-20260915/hosted/receipt.json` | `6e9e83eb8f2521df7c0e8a681bc86190a0ff324627f1e70b4c2276a1b6cee974` | hosted receipt |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| RUN-07 | `delivery/remaining-cohort-20260915/evidence/run-07.json` | `9114521b4dc9fde2b277bd666ac5bae2ca5d446694e5270e31c63cdc63b10f23` | hosted | p9-cohort/1 | hosted-preflight | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --preflight` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T14:30:14.070597+00:00` | `2026-09-18T14:30:19.645312+00:00` | 0 | PASS |
| RUN-01 | `delivery/remaining-cohort-20260915/evidence/run-01.json` | `f00560c948205c7489545789b3dbb5d45554634c3503f230f98eb11ea2a1a069` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-run-binding /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/hosted/run-35349147513.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T14:30:19.647513+00:00` | `2026-09-18T14:30:19.672593+00:00` | 0 | PASS |
| RUN-02 | `delivery/remaining-cohort-20260915/evidence/run-02.json` | `36d11de357f23227a670c4704835f85b3ce402c1930398bc4fc081873d224bfe` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-jobs-success /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/hosted/run-35349147513.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T14:30:19.674429+00:00` | `2026-09-18T14:30:19.699869+00:00` | 0 | PASS |
| RUN-06 | `delivery/remaining-cohort-20260915/evidence/run-06.json` | `46575aa37a2fb39f9cd3ad57a8d4f437ada520e673e2f41290dca355dbe67f36` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-query-before-repeat` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T14:30:19.701663+00:00` | `2026-09-18T14:30:21.852425+00:00` | 0 | PASS |
| RUN-04 | `delivery/remaining-cohort-20260915/evidence/run-04.json` | `390bca08439fb1a7af6f9ae7575254f775d6d85dc80fecaaecc3a87aaaa6fd01` | hosted | p9-cohort/1 | hosted-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-workflow-policy /home/vostrikov/projects/packages/corex/.github/workflows/remaining-cohort-proof.yml` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T14:30:21.854705+00:00` | `2026-09-18T14:30:21.878034+00:00` | 0 | PASS |
| RUN-05 | `delivery/remaining-cohort-20260915/evidence/run-05.json` | `532f3552103c92daf46fa783c83a8c50427bf61587125c699a536154da802595` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-retained-logs /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/hosted/run-35349147513.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T14:30:21.879797+00:00` | `2026-09-18T14:30:21.904054+00:00` | 0 | PASS |
| RUN-03 | `delivery/remaining-cohort-20260915/evidence/run-03.json` | `06b877397ff9ea4a91de2999d661b2d2865b074111f26ed27024a346740c0309` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-source-binding /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/hosted/run-35349147513.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T14:30:21.905920+00:00` | `2026-09-18T14:30:21.931924+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| runner-packet/1 | G-RUNNER | `a93f5923dc92fad9ebb619e332dd9af5f48d1e4999530d4e65e2c70772e7e4b9` | P9.4 | PASS | dispatch exact packet jobs |
| hosted-receipt/1 | G-RUNNER | `6e9e83eb8f2521df7c0e8a681bc86190a0ff324627f1e70b4c2276a1b6cee974` | P9.5 | PASS | P9.6 private read pending |
| p9-cohort/1 candidate | G-RELEASE | `cd764b9657b643eeccd324b4afefe6681078df6049a0e0640effbac332a9061c` | P9.1 | PASS | unchanged intake identity |
| hosted-execution-surface | G-RUNNER | `academici/corex-mirror` | P9.5 | PASS | mirror execution on `academici/corex-mirror` (canonical `axiomasoft/corex`) |
