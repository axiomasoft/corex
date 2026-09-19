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
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/runner-packet.json` | `b09528be89e13544d7e1b03d15b265d73101e9090489f7b3c7aadf38c79c985e` | final runner packet |
| `delivery/remaining-cohort-20260915/runner-plan.json` | `43c5b414c33c3aed380ade60f04635079938b81701797f0a1a703c7253fb8205` | immutable hosted runner plan |
| `delivery/remaining-cohort-20260915/installed-candidate/receipt.json` | `ebf9fb5aefbbf98bdc1da7d1c804d59df860c67ca1b006d0a210564514ef6042` | installed candidate receipt |
| `.github/workflows/remaining-cohort-proof.yml` | `6bd0896520a19d3920adcb9f3db9cd04a8e897b9344f128b783d814ee8f0cae1` | hosted workflow helper |
| `delivery/remaining-cohort-20260915/hosted/receipt.json` | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | hosted receipt |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| RUN-07 | `delivery/remaining-cohort-20260915/evidence/run-07.json` | `66e532efc676d95432683179ba0747938c482022dceca6c563b01d0950a7f09a` | hosted | p9-cohort/1 | hosted-preflight | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-preflight-retained` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:39:59.687963+00:00` | `2026-09-19T05:39:59.713502+00:00` | 0 | PASS |
| RUN-01 | `delivery/remaining-cohort-20260915/evidence/run-01.json` | `6ad8cb37d94e88f08d2b4734608c42ca485c3fd526352c362b9d1b6c8e48800c` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-run-binding /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/hosted/run-35349147513.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:39:59.715770+00:00` | `2026-09-19T05:39:59.740788+00:00` | 0 | PASS |
| RUN-02 | `delivery/remaining-cohort-20260915/evidence/run-02.json` | `c01c4342600e633c1d24e6604bb18c2a03d9b387328f893e223396232cdd8032` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-jobs-success /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/hosted/run-35349147513.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:39:59.742760+00:00` | `2026-09-19T05:39:59.768506+00:00` | 0 | PASS |
| RUN-06 | `delivery/remaining-cohort-20260915/evidence/run-06.json` | `38a1d070fd05685fcaaa50496ed742ea9d89231e526a4185019831a363ac5a93` | hosted | p9-cohort/1 | hosted-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-query-before-repeat-cached` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:39:59.770525+00:00` | `2026-09-19T05:39:59.795527+00:00` | 0 | PASS |
| RUN-04 | `delivery/remaining-cohort-20260915/evidence/run-04.json` | `a6eaafa4bde641f0e7f2a1ca1f4df4a58d28f1be8c5d153adf0e703e745a5e98` | hosted | p9-cohort/1 | hosted-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-workflow-policy /home/vostrikov/projects/packages/corex/.github/workflows/remaining-cohort-proof.yml` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:39:59.797228+00:00` | `2026-09-19T05:39:59.820873+00:00` | 0 | PASS |
| RUN-05 | `delivery/remaining-cohort-20260915/evidence/run-05.json` | `a0b2ad7ef75c2a11c267734f448dd84cce930eeded4e71d12c7d4115433bd007` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-retained-logs /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/hosted/run-35349147513.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:39:59.822833+00:00` | `2026-09-19T05:39:59.848390+00:00` | 0 | PASS |
| RUN-03 | `delivery/remaining-cohort-20260915/evidence/run-03.json` | `f3d3450f74e1fab3a0d3e3985741ce5aaeee4739bc53435f48ed2c9ba5797f1c` | hosted | p9-cohort/1 | hosted-remote | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-hosted.py --verify-source-binding /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/hosted/run-35349147513.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:39:59.850277+00:00` | `2026-09-19T05:39:59.879803+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| runner-packet/1 | G-RUNNER | `b09528be89e13544d7e1b03d15b265d73101e9090489f7b3c7aadf38c79c985e` | P9.4 | PASS | dispatch exact packet jobs |
| hosted-receipt/1 | G-RUNNER | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | P9.5 | PASS | P9.6 private read pending |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
| hosted-execution-surface | G-RUNNER | `academici/corex-mirror` | P9.5 | PASS | mirror execution on `academici/corex-mirror` (canonical `axiomasoft/corex`) |
