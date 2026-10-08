# P9 local matrix receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.3

Local compatibility matrix and hosted workflow preparation for frozen p9-cohort/1.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| MATRIX-01 | R45 | All target cells generated from exact PHP/framework/extensions/package/profile intersection; real resolved Testbench/Pest minimum constraints included | `evidence:MATRIX-01` | PASS | P9.3 | revise profile-plan on policy drift |
| MATRIX-02 | R45 | Minimum and current dependency solutions per allowed cell retained with distinct locks; incompatible toolchain not bypassed | `evidence:MATRIX-02` | PASS | P9.3 | rerun lock capture on dependency drift |
| MATRIX-03 | R45 | Locked cold reinstall/check-platform-reqs uses actual PHP/extensions; no config.platform/ignore-platform-reqs fake supported evidence | `evidence:MATRIX-03` | PASS | P9.3 | repair composer platform config |
| MATRIX-04 | R45 | D34 root SQLite+PG16, affected package suites/style/analysis/architecture/contract/anti-weakening and current coverage floors all executed at exact candidate/recipe | `evidence:MATRIX-04` | PASS | P9.3 | return to owning package on quality failure |
| MATRIX-05 | R45 | Actual source changes require D98 all current mutation cases; provenance-only no-src delta records applicability | `evidence:MATRIX-05` | PASS | P9.3 | rerun mutation when src delta present |
| MATRIX-06 | R45 B11 AC-29/30 | Every new selected package has actual CI job/test aggregator/negative layer/literal scan; no vacuum scan or missing package suite | `evidence:MATRIX-06` | PASS | P9.3 | add CI job for uncovered package |
| MATRIX-07 | R45 | Failures/missing cells retain FAIL/PENDING/UNSUPPORTED; narrower support requires actual owner BC approval, never omit cell for GREEN | `evidence:MATRIX-07` | PASS | P9.3 | owner decision for narrowed support |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/profile-plan.json` | `5dd5972ab65d1793b5ae12a418fde23c85cabc9f9faa8cc3976548251a7e9ee1` | selected matrix profiles |
| `delivery/remaining-cohort-20260915/build/build-attestation.json` | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | build attestation |
| `delivery/remaining-cohort-20260915/local/matrix.json` | `dbdb6f0fa93029c125d1f12c99d460c808a160af7cbabe239a42b0dbb7224edb` | local matrix artifact |
| `delivery/remaining-cohort-20260915/runner-plan.json` | `43c5b414c33c3aed380ade60f04635079938b81701797f0a1a703c7253fb8205` | immutable hosted runner plan |
| `scripts/distribution/remaining-cohort-quality.py` | `e6533711891e8da51d4028c7cb0a6284d7deee103c601ae19c42b20437f79617` | matrix quality recipe |
| `scripts/distribution/remaining-cohort-runner.py` | `bf20634b7451b41cf9043b891457984242d9f8c01386d8d953430f3e187c2cde` | runner plan recipe |
| `.github/workflows/remaining-cohort-proof.yml` | `6bd0896520a19d3920adcb9f3db9cd04a8e897b9344f128b783d814ee8f0cae1` | hosted workflow helper |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| MATRIX-01 | `delivery/remaining-cohort-20260915/evidence/matrix-01.json` | `4b8d581d0760591f1c62967790f4e8d7cc55d3b24669f84deeb625e66a261748` | local | p9-cohort/1 | matrix-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-quality.py --verify-matrix-cells /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/local/matrix.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:51.604151+00:00` | `2026-09-18T17:29:51.626285+00:00` | 0 | PASS |
| MATRIX-02 | `delivery/remaining-cohort-20260915/evidence/matrix-02.json` | `a2f79fa2d3b8156de2318897c1f89c88f2e2d4a039c0b941fbe952ae31444a36` | local | p9-cohort/1 | matrix-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-quality.py --verify-locks` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:51.628472+00:00` | `2026-09-18T17:29:51.654553+00:00` | 0 | PASS |
| MATRIX-03 | `delivery/remaining-cohort-20260915/evidence/matrix-03.json` | `177411fbdb8b6007646b637b5c892eb1e8bc6be61f4ceb5d648517f756203dcd` | local | p9-cohort/1 | matrix-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-quality.py --verify-platform` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:51.656730+00:00` | `2026-09-18T17:29:51.760351+00:00` | 0 | PASS |
| MATRIX-04 | `delivery/remaining-cohort-20260915/evidence/matrix-04.json` | `0ff9c4f8f37fdc4cb61dffb32cdb1128a67ccae480d5cdf1151efe2dcf7a1155` | local | p9-cohort/1 | matrix-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-quality.py --run-quality` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:51.761829+00:00` | `2026-09-18T17:30:26.554860+00:00` | 0 | PASS |
| MATRIX-05 | `delivery/remaining-cohort-20260915/evidence/matrix-05.json` | `b12522ca5c9c59454edbaaa09dd7c525fcbd21133b99e4dd262f395d06cf41f2` | local | p9-cohort/1 | matrix-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-quality.py --verify-mutation-applicability` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:30:26.557082+00:00` | `2026-09-18T17:30:26.586885+00:00` | 0 | PASS |
| MATRIX-06 | `delivery/remaining-cohort-20260915/evidence/matrix-06.json` | `51756c9622f2138797cf31d73ca8af75805f6f83116d3539f5f91cd00f7a89fb` | local | p9-cohort/1 | matrix-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-quality.py --verify-ci-coverage /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/local/matrix.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:30:26.588873+00:00` | `2026-09-18T17:30:26.611605+00:00` | 0 | PASS |
| MATRIX-07 | `delivery/remaining-cohort-20260915/evidence/matrix-07.json` | `cf8ea0831757570304253858211774b2966c3266c293630f04f6e7b4ec6dfa89` | local | p9-cohort/1 | matrix-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-quality.py --verify-matrix-completeness /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/local/matrix.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:30:26.612979+00:00` | `2026-09-18T17:30:26.635164+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| p9-local-matrix/1 | G-RUNNER | `dbdb6f0fa93029c125d1f12c99d460c808a160af7cbabe239a42b0dbb7224edb` | P9.3 | PASS | P9.4 installed fixtures pending |
| runner-plan/1 | G-RUNNER | `43c5b414c33c3aed380ade60f04635079938b81701797f0a1a703c7253fb8205` | P9.3 | PASS | P9.5 hosted dispatch pending |
| p9-build-attestation/1 | G-RELEASE | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | P9.2 | PASS | unchanged build join |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
