# P9 cohort distribution receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.2

Reproducible dist export and local split-tree verification for the frozen p9-cohort/1 intake.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| DIST-01 | R45 | Independent builds from committed source and frozen recipe yield identical uploaded ZIP SHA256 per package, file order/time/modes/extra fields fixed | `evidence:DIST-01` | PASS | P9.2 | rerun export on recipe/source drift |
| DIST-02 | R45 | Source subtree → filtered/rewritten release tree → local split commit verified by exact path/content/mode map; transformations only composer pins/version/root LICENSE/allowlist | `evidence:DIST-02` | PASS | P9.2 | revise candidate on overlay/source drift |
| DIST-03 | R45 | Composer source.reference resolves to split commit, dist.reference and per-channel digest independently bound; monorepo SHA is not falsely used as split ref | `evidence:DIST-03` | PASS | P9.2 | rebuild export-map/public metadata |
| DIST-04 | R45 | Public runtime/dev metadata no host path/file URLs, repositories in dependencies not assumed recursive; packages do not import flex or private auth | `evidence:DIST-04` | PASS | P9.2 | return to owning package on boundary violation |
| DIST-05 | R45 | Untracked files, tests/vendor/.env/auth.json/private keys, traversal/absolute paths/duplicate entries/symlinks are rejected or explicitly forbidden by export policy | `evidence:DIST-05` | PASS | P9.2 | tighten export policy and rerun |
| DIST-06 | R45 | Exact required config/migrations/resources/providers/LICENSE/README/CHANGELOG/UPGRADE retained; export omission injects RED | `evidence:DIST-06` | PASS | P9.2 | repair allowlist/required map |
| DIST-07 | R45 | Collision/wrong version/changed tree or checksum refuses without overwriting staging or modifying old export policy/receipt | `evidence:DIST-07` | PASS | P9.2 | new explicit revision on overlay/source change |
| DIST-08 | R45 | Provider/engine/SDK archives separately source/license/checksum qualified; already matching immutable upstream reused, not republished | `evidence:DIST-08` | PASS | P9.2 | upstream owner re-qualifies provider receipts |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `scripts/distribution/remaining-cohort-policy.json` | `07ee47018d701ea432bbcd2b2ff899fea3a921f9d5df548954b6d2568f24b0df` | cohort export allowlist |
| `scripts/distribution/remaining-cohort-export.py` | `6421ad5cf36611897cdab91dec475d484ab724377ca02306d644193e2955ceef` | cohort export recipe |
| `scripts/distribution/export.py` | `ac8bd43716af130705e5ee596129273bbd54bdfb17f591610b0b243b7eee6b74` | legacy exporter preserved |
| `delivery/remaining-cohort-20260915/build/build-attestation.json` | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | build attestation |
| `delivery/remaining-cohort-20260915/build/export-map.json` | `cac29e6114302637bf8b30eb798f254c54e5415ecd22fe6f8abe3bc0162f295c` | export map |
| `delivery/remaining-cohort-20260915/public/packages.json` | `1f2ea988aa5cb722d00d60bf43077efdf7dcc2ef6f301a9e58d06423da8886a6` | public composer metadata |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| DIST-01 | `delivery/remaining-cohort-20260915/evidence/dist-01.json` | `5f48f8901b80bc40923a44dfa58fa39c600ff97a0542d266d6cc74f104f493ac` | local | p9-cohort/1 | dist-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-export.py --verify-reproducible /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:28:54.371097+00:00` | `2026-09-18T17:28:57.146357+00:00` | 0 | PASS |
| DIST-02 | `delivery/remaining-cohort-20260915/evidence/dist-02.json` | `0cb2d6cfc018e701d4f5d8220d626b6d45856a6492d4fde07af7288d25c47bba` | local | p9-cohort/1 | dist-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-export.py --verify-split-map /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:28:57.148207+00:00` | `2026-09-18T17:28:59.979463+00:00` | 0 | PASS |
| DIST-03 | `delivery/remaining-cohort-20260915/evidence/dist-03.json` | `0930988b94da85b89ced9f457046b09179afde7a6aec701c1a72485f2702eeb4` | local | p9-cohort/1 | dist-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-export.py --verify-source-dist-binding /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:28:59.980826+00:00` | `2026-09-18T17:29:01.366926+00:00` | 0 | PASS |
| DIST-04 | `delivery/remaining-cohort-20260915/evidence/dist-04.json` | `aa7a34889b4773da9dbf39196f228882174ca3212b525cd839bd558fb60e9a91` | local | p9-cohort/1 | dist-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-export.py --verify-public-metadata /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:01.368657+00:00` | `2026-09-18T17:29:04.173919+00:00` | 0 | PASS |
| DIST-05 | `delivery/remaining-cohort-20260915/evidence/dist-05.json` | `1a72eed068e17a5e0bcb40842fc5f70d6f68a222dc891658605a0d66c90fe5d5` | local | p9-cohort/1 | dist-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-export.py --verify-export-policy /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:04.175636+00:00` | `2026-09-18T17:29:04.259990+00:00` | 0 | PASS |
| DIST-06 | `delivery/remaining-cohort-20260915/evidence/dist-06.json` | `b04586139fb402d09ee703b129a5fcd56321403e4b14f1cebf2c4c551d3fbc08` | local | p9-cohort/1 | dist-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-export.py --verify-required-files /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:04.261692+00:00` | `2026-09-18T17:29:05.665467+00:00` | 0 | PASS |
| DIST-07 | `delivery/remaining-cohort-20260915/evidence/dist-07.json` | `eb7bc26eec1b91922699b9d8c03325c83dc9d4c5d9861421ec1672ea552faed3` | local | p9-cohort/1 | dist-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-export.py --verify-collision-refusal /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:05.667207+00:00` | `2026-09-18T17:29:05.856086+00:00` | 0 | PASS |
| DIST-08 | `delivery/remaining-cohort-20260915/evidence/dist-08.json` | `55126827efa62b242ec459a20dd6d310b735928b1e0b8c1d49b0b1a71d39d1e3` | local | p9-cohort/1 | dist-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-export.py --verify-upstream-providers /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:29:05.857864+00:00` | `2026-09-18T17:29:07.280111+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| p9-build-attestation/1 | G-RELEASE | `59967cae16741ef0ad6bde92b5a60c6bf4552f925e79ad28b375ac0762e8a673` | P9.2 | PASS | P9.3 matrix execution pending |
| p9-export-map/1 | G-RELEASE | `cac29e6114302637bf8b30eb798f254c54e5415ecd22fe6f8abe3bc0162f295c` | P9.2 | PASS | publication packet pending |
| public/packages.json | G-RELEASE | `1f2ea988aa5cb722d00d60bf43077efdf7dcc2ef6f301a9e58d06423da8886a6` | P9.2 | PASS | hosted/released install pending |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
