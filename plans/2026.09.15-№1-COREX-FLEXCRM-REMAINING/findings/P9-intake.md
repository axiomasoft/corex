# P9 cohort intake receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.1

Immutable intake identity for the remaining public cohort plus optional private auth profile.
This freezes p9-cohort/1 only; recipe/split/dist/hosted/release attestations are downstream.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| INTAKE-01 | R45 R51 | Every included R has accepted P1 source/equivalent and P2 proposal/no-delta binding; missing row refuses freeze | `evidence:INTAKE-01` | PASS | P9.1 | refresh on P1/P2 seal drift |
| INTAKE-02 | R45 | Six selected P3–P8 seals are digest-current with included receipts; old E2/E3/E5 never substitute | `evidence:INTAKE-02` | PASS | P9.1 | rerun if producer seal changes |
| INTAKE-03 | R45 | Runtime dependency transitive closure has immutable per-package versions/refs/licenses; no @dev/latest aliases in released graph | `evidence:INTAKE-03` | PASS | P9.1 | revise candidate on dependency drift |
| INTAKE-04 | R45 R51 | Public and private profile membership explicit; public closure excludes auth/AzGuard/product layer | `evidence:INTAKE-04` | PASS | P9.1 | owner decision required to drop private profile |
| INTAKE-05 | R45 | Per-package BC/major/CHANGELOG/DDL mapping matches actual adopted P2 delta; unresolved scope refuses release | `evidence:INTAKE-05` | PASS | P9.1 | return to P2 on BC conflict |
| INTAKE-06 | R45 | Baseline released version/lock/schema and every target profile selected; intake rejects future-output fields | `evidence:INTAKE-06` | PASS | P9.1 | new explicit revision on overlay/source change |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` | producer or P1/P2 seal |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | producer or P1/P2 seal |
| `findings/P1-evidence-ledger.md` | `ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87` | producer or P1/P2 seal |
| `findings/P2-contract-catalogue.md` | `ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573` | producer or P1/P2 seal |
| `findings/P2-contract-proposals.md` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | producer or P1/P2 seal |
| `findings/P2-gate-requests.md` | `77b763002a9509b5ab995046ed838bbcc19f9e6c1786cc6da6a6c994172e5cdd` | producer or P1/P2 seal |
| `findings/P3-adapter-receipts.md` | `b048f8e58669845a684df013c198f4397a5d46cc2d021ff3f93bc1b7b48ede3c` | producer or P1/P2 seal |
| `findings/P4-extension-receipts.md` | `48c85be9e30f1cbdb6d2d5f869c67667af8de7d5352b64410f5b9e798fc64cae` | producer or P1/P2 seal |
| `findings/P5-wakeup-receipts.md` | `a88145a6cf68a8357b739a0a4b5e7dda181162e9b136ca4dbc505c1ac66c8f03` | producer or P1/P2 seal |
| `findings/P6-data-receipts.md` | `3168ed89da964064ff6c7f6556673405a98df692da1e8ff46462ad43713970e3` | producer or P1/P2 seal |
| `findings/P7-utility-receipts.md` | `279163d4d9bed471ef8fbb25465897307c4f5ae8144303b862312d6e549df036` | producer or P1/P2 seal |
| `findings/P8-tool-receipts.md` | `a8e3ead919761158fa88d3c1bf5df59182609d92641635afd73437ec5914b918` | producer or P1/P2 seal |
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/profile-plan.json` | `5dd5972ab65d1793b5ae12a418fde23c85cabc9f9faa8cc3976548251a7e9ee1` | selected matrix profiles |
| `delivery/remaining-cohort-20260915/baselines.json` | `fb57fcc53958499003e14e7aac23b4fea3e27ac0c21b4c1d79ae9e8d8055ae0a` | upgrade/first-install baselines |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |
| `decisions/D14-release-cohort.md` | `cfabaff5040c6d2c0a00a4c5e52c341ea5ab5d7ec2af2236bfe37e9264c4aa20` | owner cohort decision |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| INTAKE-01 | `delivery/remaining-cohort-20260915/evidence/intake-01.json` | `04eb99d95ee5f810f8c1c037484a5a1b03573a1d867db833ae9b0654c27fc185` | local | p9-cohort/1 | intake-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-intake.py --verify-p1-p2 /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:01:26.200399+00:00` | `2026-09-18T17:01:26.225969+00:00` | 0 | PASS |
| INTAKE-02 | `delivery/remaining-cohort-20260915/evidence/intake-02.json` | `bb59ce290885a2bd2682ac95420f1867c2762e927925cab4f792cf883417930b` | local | p9-cohort/1 | intake-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-intake.py --verify-producer-seals` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:01:26.226698+00:00` | `2026-09-18T17:01:26.251645+00:00` | 0 | PASS |
| INTAKE-03 | `delivery/remaining-cohort-20260915/evidence/intake-03.json` | `56024a6d82b0f9ffa1edb201f6bb1a3a60cf820e8347fafdd527a866b2c6cbd9` | local | p9-cohort/1 | intake-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-intake.py --verify-requires /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:01:26.252359+00:00` | `2026-09-18T17:01:26.275152+00:00` | 0 | PASS |
| INTAKE-04 | `delivery/remaining-cohort-20260915/evidence/intake-04.json` | `13e040d81b315c784585701851fc0b25bded0ad57abffe5c36a03d3b7f0b7ec2` | local | p9-cohort/1 | intake-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-intake.py --verify-profiles /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/profile-plan.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:01:26.275752+00:00` | `2026-09-18T17:01:26.294499+00:00` | 0 | PASS |
| INTAKE-05 | `delivery/remaining-cohort-20260915/evidence/intake-05.json` | `ea4a25268573b2380d98d65f92d9dbecdaeb2fd649f7594c20b7f4014a457701` | local | p9-cohort/1 | intake-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-intake.py --verify-p2-delta` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:01:26.295042+00:00` | `2026-09-18T17:01:26.319703+00:00` | 0 | PASS |
| INTAKE-06 | `delivery/remaining-cohort-20260915/evidence/intake-06.json` | `d95708ea3acffd0dbdeead25ba4899236fef7ec490538d2cb2d2a887f025909b` | local | p9-cohort/1 | intake-local | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-intake.py --verify-acyclic /home/vostrikov/projects/packages/corex/delivery/remaining-cohort-20260915/candidate.json` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T17:01:26.320268+00:00` | `2026-09-18T17:01:26.342810+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | P9.2 build/export only |
| p9-profile-plan/1 | G-RUNNER | `5dd5972ab65d1793b5ae12a418fde23c85cabc9f9faa8cc3976548251a7e9ee1` | P9.3 | not-applicable | matrix execution pending |
| p9-baselines/1 | G-RELEASE | `fb57fcc53958499003e14e7aac23b4fea3e27ac0c21b4c1d79ae9e8d8055ae0a` | P9.4 | not-applicable | installed upgrade pending |
| optional auth private profile | G-PRIVATE | `optional-auth-azguard` | P9.6 | not-applicable | exact private channel pending |
| historical E2/E3/E5 receipts | G-RELEASE | `ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87` | P1 ledger | not-applicable | never substitute current cohort proof |
