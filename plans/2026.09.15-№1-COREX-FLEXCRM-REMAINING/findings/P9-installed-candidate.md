# P9 installed candidate receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.4

Independent cold install candidate and populated upgrade/refusal verification for frozen p9-cohort/1.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| INSTALL-01 | R45 | Independent lab/equipment Laravel consumer in empty home/cache/vendor with no path repository/symlink installs exact candidate dist and runtime check-platform-reqs | `evidence:INSTALL-01` | PASS | P9.4 | repair public dist metadata on drift |
| INSTALL-02 | R45 | Separate update → lock capture → empty cache/vendor install produces matching package file maps/autoload/providers; no-dev runtime proof separate dev suite | `evidence:INSTALL-02` | PASS | P9.4 | rerun lock capture on dependency drift |
| INSTALL-03 | R45 B11 AC-28/29/30 | Boxed boot without root DB/tenancy/private auth; physical PG16 A/B DBs and current context switch/authorization isolation independently checked | `evidence:INSTALL-03` | PASS | P9.4 | repair PG16 preflight on isolation failure |
| INSTALL-04 | R45 | Config/route/module cache, migration discovery/optional driver/queue startup and accepted API/tool dispatch tested from installed vendor; unavailable feature explicit deny | `evidence:INSTALL-04` | PASS | P9.4 | return to owning package on boot failure |
| INSTALL-05 | R45 | Populated released baseline → scoped composer update → accepted migration route preserves accepted IDs/morphs/minor-units/soft-delete/settings/audit and domain data | `evidence:INSTALL-05` | PASS | P9.4 | refresh populated baseline fixture on schema drift |
| INSTALL-06 | R45 | Unsupported baseline/schema/dependency/runtime/insufficient privilege preflight refuses before DDL/domain writes; before/after hashes and schema prove no mutation | `evidence:INSTALL-06` | PASS | P9.4 | tighten refusal predicates |
| INSTALL-07 | R45 | Fault after first migration/DDL is reconciled using actual migration table+schema/data; bounded retry/forward recovery only, no blind down/reset or assumed total SQL rollback | `evidence:INSTALL-07` | PASS | P9.4 | repair forward recovery fixture |
| INSTALL-08 | R45 | pg_dump custom backup actually restored to another _test DB with schema/data/constraints checked; ordinary restore never claims external nonrollback identity witness or provider recovery | `evidence:INSTALL-08` | PASS | P9.4 | rerun backup proof on PG drift |
| INSTALL-09 | R45 B11 AC-29/30 | Each included selected branch conformance rerun on dist with real supported local driver; fake external services do not prove deployed/vendor/performance acceptance | `evidence:INSTALL-09` | PASS | P9.4 | add conformance scan for new package |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/build/build-attestation.json` | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | build attestation |
| `delivery/remaining-cohort-20260915/local/matrix.json` | `dbdb6f0fa93029c125d1f12c99d460c808a160af7cbabe239a42b0dbb7224edb` | local matrix artifact |
| `delivery/remaining-cohort-20260915/runner-plan.json` | `43c5b414c33c3aed380ade60f04635079938b81701797f0a1a703c7253fb8205` | immutable hosted runner plan |
| `delivery/remaining-cohort-20260915/installed-candidate/receipt.json` | `ebf9fb5aefbbf98bdc1da7d1c804d59df860c67ca1b006d0a210564514ef6042` | installed candidate receipt |
| `delivery/remaining-cohort-20260915/private-read-packet.json` | `058cf1ee78e7f7878ac3a0a63011acf3bd87ad2183269d00e9302c6c686344f0` | private read packet |
| `delivery/remaining-cohort-20260915/runner-packet.json` | `b09528be89e13544d7e1b03d15b265d73101e9090489f7b3c7aadf38c79c985e` | final runner packet |
| `delivery/remaining-cohort-20260915/fixtures/lab-consumer-cohort.json` | `015ce46e58af29ed9081b17d710c6722ec09341ec6235560cb02f8a025a05454` | neutral lab consumer fixture |
| `scripts/distribution/remaining-cohort-installed.py` | `0cdc5aeeef787c32439b17189fcfc0072526a41bb3c53cd2c599220751dd7768` | installed proof recipe |
| `scripts/distribution/remaining-cohort-private.py` | `eab731d6480b81aff67019e827f553318144dd2f989faf01b3783dfc231edca1` | private read helper |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| INSTALL-01 | `delivery/remaining-cohort-20260915/evidence/install-01.json` | `0eaaf3f3577b2d4345ee69588f34f2a8a1f9f29910f090b405bd7840263a7280` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-01` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:04.866968+00:00` | `2026-09-18T18:20:05.512958+00:00` | 0 | PASS |
| INSTALL-02 | `delivery/remaining-cohort-20260915/evidence/install-02.json` | `79e66e779310ff3a0acd3b1110b32fcac3e0d230a9e48565a4cb0c3f62603542` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-02` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:05.514915+00:00` | `2026-09-18T18:20:06.383085+00:00` | 0 | PASS |
| INSTALL-03 | `delivery/remaining-cohort-20260915/evidence/install-03.json` | `be0b76893b66e4beca90dabebc8dc44e2e65729964d9028cc8106427ebfd234c` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-03` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:06.385107+00:00` | `2026-09-18T18:20:07.041451+00:00` | 0 | PASS |
| INSTALL-04 | `delivery/remaining-cohort-20260915/evidence/install-04.json` | `c3338a10f18c51e347edf879ec2353e9573d5becd13bd60a3cb72556d72b0b55` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-04` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:07.043756+00:00` | `2026-09-18T18:20:07.686605+00:00` | 0 | PASS |
| INSTALL-05 | `delivery/remaining-cohort-20260915/evidence/install-05.json` | `ed40fba95020e9135923bf9e6b447fde0cd8ad74ace23b903c2a4c4ea90ff4cd` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-05` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:07.689015+00:00` | `2026-09-18T18:20:08.262584+00:00` | 0 | PASS |
| INSTALL-06 | `delivery/remaining-cohort-20260915/evidence/install-06.json` | `f83466eac5f35f36b853f98a9e7d2090b3da90d94dda3131d0cfce353cd3b21b` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-06` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:08.264682+00:00` | `2026-09-18T18:20:08.359832+00:00` | 0 | PASS |
| INSTALL-07 | `delivery/remaining-cohort-20260915/evidence/install-07.json` | `098e56cee539b972839dd843410d29130bdfedc7dc213ce6b2f692a172e5a32b` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-07` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:08.361733+00:00` | `2026-09-18T18:20:08.406765+00:00` | 0 | PASS |
| INSTALL-08 | `delivery/remaining-cohort-20260915/evidence/install-08.json` | `ba8001c6f0afd96fc54913dc8f2e2b699d6d919c57f13522a21efd78ed587342` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-08` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:08.409041+00:00` | `2026-09-18T18:20:08.490362+00:00` | 0 | PASS |
| INSTALL-09 | `delivery/remaining-cohort-20260915/evidence/install-09.json` | `369193020d286feedf3ca607631df01a9c300ded78b891d074d8cc19a677fd65` | local | p9-cohort/1 | installed-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-installed.py --verify-install-09` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:20:08.492515+00:00` | `2026-09-18T18:20:09.431567+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| installed-candidate/1 | G-RUNNER | `ebf9fb5aefbbf98bdc1da7d1c804d59df860c67ca1b006d0a210564514ef6042` | P9.4 | PASS | P9.5 hosted dispatch pending |
| runner-packet/1 | G-RUNNER | `b09528be89e13544d7e1b03d15b265d73101e9090489f7b3c7aadf38c79c985e` | P9.4 | PASS | P9.5 exact packet dispatch pending |
| private-read-packet/1 | G-PRIVATE | `058cf1ee78e7f7878ac3a0a63011acf3bd87ad2183269d00e9302c6c686344f0` | P9.4 | PASS | P9.6 private read pending |
| p9-build-attestation/1 | G-RELEASE | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | P9.2 | PASS | unchanged build join |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
