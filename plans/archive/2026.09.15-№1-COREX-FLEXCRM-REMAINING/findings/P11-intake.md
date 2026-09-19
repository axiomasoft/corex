# P11 consumer reconciliation intake

p11-reconciliation-report/1
**Item:** P11.1

Producer qualification and local verifier preparation. P10 consumer input is readiness-only (`required_gaps=169`); not actual effective seal or consumer acceptance.

## Cases

| ID | Result | Expected | Actual | Evidence |
|:--|:--|:--|:--|:--|
| INTAKE-01 | PASS | 52R/365 parents with hashes and accepted/proposal distinctions | r=52;parents=365;no fabricated future output | INTAKE-01 |
| INTAKE-02 | PASS | P2–P9 seals agree; source/split/dist identities distinct | cohort=f44c0c6fe757aeb8 | INTAKE-02 |
| INTAKE-03 | PASS | P10 consumer-input binds cohort; readiness gaps explicit | mode=readiness;gaps=169 | INTAKE-03 |
| INTAKE-04 | PASS | finite sanitized imports; owner policy authenticated | imports=1;owner-receipt bound | INTAKE-04 |
| INTAKE-05 | PASS | verifier refusal paths; synthetic labelled | self-test prepared | INTAKE-05 |
| INTAKE-06 | PASS | R47 self-edge excluded; R52 producer invariant | scope-closure verified | INTAKE-06 |

## Inputs

| Path | SHA256 |
|:--|:--|
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` |
| `findings/P1-evidence-ledger.md` | `ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87` |
| `findings/P1-downstream-gaps.md` | `6284a6d649f3f4bbbe5468dfb502e7915fe90b38ddf7f9a7441995baf0b11e3a` |
| `findings/P2-contract-catalogue.md` | `ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573` |
| `findings/P2-gate-requests.md` | `77b763002a9509b5ab995046ed838bbcc19f9e6c1786cc6da6a6c994172e5cdd` |
| `findings/P3-adapter-receipts.md` | `b048f8e58669845a684df013c198f4397a5d46cc2d021ff3f93bc1b7b48ede3c` |
| `findings/P4-extension-receipts.md` | `48c85be9e30f1cbdb6d2d5f869c67667af8de7d5352b64410f5b9e798fc64cae` |
| `findings/P5-wakeup-receipts.md` | `a88145a6cf68a8357b739a0a4b5e7dda181162e9b136ca4dbc505c1ac66c8f03` |
| `findings/P6-data-receipts.md` | `3168ed89da964064ff6c7f6556673405a98df692da1e8ff46462ad43713970e3` |
| `findings/P7-utility-receipts.md` | `279163d4d9bed471ef8fbb25465897307c4f5ae8144303b862312d6e549df036` |
| `findings/P8-tool-receipts.md` | `a8e3ead919761158fa88d3c1bf5df59182609d92641635afd73437ec5914b918` |
| `findings/P9-cohort-receipt.md` | `c5ae759c8cd30f1404cb6c197194428b42ad4c0d6874f6f154258ba37718deff` |
| `findings/P9-compatibility-passport.md` | `6ecf707ec8d153d52c8d7f85364ba550b471362aa1b13e98372735d343b65df6` |
| `delivery/remaining-cohort-20260915/public/released-cohort.json` | `f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee` |
| `findings/P10-effective-receipts.md` | `d4e4a30ae7dbb1e4e92252b3507433947fa66dbd66ad98c9f5744ec5fcf21ea7` |
| `artifacts/P10-effective/dependency-receipts.json` | `5278dd04c1fdac382ef97740ffbb48214d1368eec292e93bbea5ce52634618b7` |

## Evidence

| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| INTAKE-01 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-intake-lab | P1-lock | `artifacts/P11-intake/evidence/intake-01.json` | `dc3cc011bcd59f254514ae13a4d230bf20db53175b2c349cd4210b26a0c3b711` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-intake --case INTAKE-01` | python=3.12 | 2026-09-19T04:10:00.951256+00:00 | 2026-09-19T04:10:00.951358+00:00 | 0 | PASS |
| INTAKE-02 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-intake-lab | producer-seals | `artifacts/P11-intake/evidence/intake-02.json` | `95b37e4c459c9e6dae4f43049492d9b2fb108937fa6df69938ab06abca1965cf` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-intake --case INTAKE-02` | python=3.12 | 2026-09-19T04:10:00.952431+00:00 | 2026-09-19T04:10:00.952501+00:00 | 0 | PASS |
| INTAKE-03 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-intake-lab | P10-consumer-input | `artifacts/P11-intake/evidence/intake-03.json` | `80b352631a1b4f46c8101b46c605861a4bca3a6c0eafb8b038e43db626b81516` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-intake --case INTAKE-03` | python=3.12 | 2026-09-19T04:10:00.953018+00:00 | 2026-09-19T04:10:00.953085+00:00 | 0 | PASS |
| INTAKE-04 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-intake-lab | consumer-intake | `artifacts/P11-intake/evidence/intake-04.json` | `3f653c7dbe47bdffe4da626f9920529ff2b3347fa210de28c9199c7c16486f7d` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-intake --case INTAKE-04` | python=3.12 | 2026-09-19T04:10:00.953485+00:00 | 2026-09-19T04:10:00.953551+00:00 | 0 | PASS |
| INTAKE-05 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-intake-lab | verifier-self-test | `artifacts/P11-intake/evidence/intake-05.json` | `f8713f5da78bf53d3129b64a9f8c036f3a153f6686c2e0cdfef5113a2f9a686c` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-intake --case INTAKE-05` | python=3.12 | 2026-09-19T04:10:00.953900+00:00 | 2026-09-19T04:10:00.953963+00:00 | 0 | PASS |
| INTAKE-06 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-intake-lab | scope-closure | `artifacts/P11-intake/evidence/intake-06.json` | `4e49c5d317d3ae47e7f6ccfc021d1fb0655f80dfa884ea1948b46001ced5a13b` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-intake --case INTAKE-06` | python=3.12 | 2026-09-19T04:10:00.954432+00:00 | 2026-09-19T04:10:00.954491+00:00 | 0 | PASS |

## Acceptance

| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| intake-local | R47 | — | p11-reconciliation-report/1 | producer qualification | verifier prepared | P9 vector bound | pending post-approval | not executed | executor/P11.1 | G-CONSUMER pending | PASS |

## Remaining

| Atom | Original residual owner | Missing receipt | Resume | Product boundary |
|:--|:--|:--|:--|:--|
| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |
| consumer locks/tests | FlexCRM consumer owner | actual composer.lock/installed proof | P11.2 after effective seal | FlexCRM app scope |
| G-CONSUMER-RECONCILE | FlexCRM owner | finite three-file grant | P11.6 manual authority | foreign write separately owned |
