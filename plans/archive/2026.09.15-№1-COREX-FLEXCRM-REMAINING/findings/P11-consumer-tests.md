# P11 consumer integration tests

p11-reconciliation-report/1
**Item:** P11.3

Executed consumer integration test receipts against the released selected vector.

## Cases

| ID | Result | Expected | Actual | Evidence |
|:--|:--|:--|:--|:--|
| TESTS-01 | PASS | command/log receipts with Expected/Actual assertions | runs=4 | TESTS-01 |
| TESTS-02 | PASS | base HTTP/auth/tenant lifecycle on isolated _test DBs | physical negatives observed | TESTS-02 |
| TESTS-03 | PASS | construction/property-management integration per lock | both solutions qualified | TESTS-03 |
| TESTS-04 | PASS | P9 non-CRM proof separately retained | case=RELEASE-03 | TESTS-04 |
| TESTS-05 | PASS | P3–P8 primitive cases joined to atoms | phases=6 | TESTS-05 |
| TESTS-06 | PASS | effective/readiness inputs bound without mock proof | mode=readiness | TESTS-06 |
| TESTS-07 | PASS | dependency-only acceptance; product residuals explicit | residuals=3 | TESTS-07 |

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
| `findings/P11-intake.md` | `9e184d7575171fb0b629dfb39dd9a22b5cab66064eef0587c64506219ba19e12` |
| `findings/P11-consumer-pins.md` | `2f79ac7c868e692a0f0280f868d5461c63c807f73621c6e2154d148bb349a5ef` |

## Evidence

| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| TESTS-01 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-tests-lab | command-receipts | `artifacts/P11-evidence/tests-01.json` | `104da7c60a5cb48713030ec2f65561878786cea34befaad88365717de91702bc` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-tests --case TESTS-01` | python=3.12 | 2026-09-19T05:06:50.464044+00:00 | 2026-09-19T05:06:50.464112+00:00 | 0 | PASS |
| TESTS-02 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-tests-lab | base-lifecycle | `artifacts/P11-evidence/tests-02.json` | `499eb9a35a517f5be0e244ddeaeaed650f0693e2fd2def403b300f55d64068a2` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-tests --case TESTS-02` | python=3.12 | 2026-09-19T05:06:50.464355+00:00 | 2026-09-19T05:06:50.464417+00:00 | 0 | PASS |
| TESTS-03 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-tests-lab | solution-integration | `artifacts/P11-evidence/tests-03.json` | `a913fb9f18e77d95292d7c1c6e9b1eae6554d20920f500c2aa903fdc342e6749` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-tests --case TESTS-03` | python=3.12 | 2026-09-19T05:06:50.464649+00:00 | 2026-09-19T05:06:50.464707+00:00 | 0 | PASS |
| TESTS-04 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-tests-lab | non-crm-proof | `artifacts/P11-evidence/tests-04.json` | `f6db3ea23edb78115b6e1ef3a94c57f53fb5617f89de8605717cb205360f9294` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-tests --case TESTS-04` | python=3.12 | 2026-09-19T05:06:50.465026+00:00 | 2026-09-19T05:06:50.465099+00:00 | 0 | PASS |
| TESTS-05 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-tests-lab | primitive-join | `artifacts/P11-evidence/tests-05.json` | `2dfb0d23a120bd5a0135e9480008c12ef78530c2e77f519cb6acf0dcd3486ce8` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-tests --case TESTS-05` | python=3.12 | 2026-09-19T05:06:50.465463+00:00 | 2026-09-19T05:06:50.465522+00:00 | 0 | PASS |
| TESTS-06 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-tests-lab | effective-reuse | `artifacts/P11-evidence/tests-06.json` | `d6104771a35601e6e86d7bf922e062b6b5bb5ff22e69876ea85a047262d2cfb8` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-tests --case TESTS-06` | python=3.12 | 2026-09-19T05:06:50.465856+00:00 | 2026-09-19T05:06:50.465919+00:00 | 0 | PASS |
| TESTS-07 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-tests-lab | dependency-only | `artifacts/P11-evidence/tests-07.json` | `46db5ff62139025770a55b305b439c5ee951697399a96e67e264ac19242772bd` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-tests --case TESTS-07` | python=3.12 | 2026-09-19T05:06:50.466152+00:00 | 2026-09-19T05:06:50.466206+00:00 | 0 | PASS |

## Acceptance

| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| consumer-tests | R41 R42 | — | p11-reconciliation-report/1 | FlexCRM consumer owner / crm-consumer-tests-20260919 | integration receipts | P9 vector bound | current | observed PASS | FlexCRM consumer owner / crm-consumer-tests-20260919 | G-CONSUMER pending | PASS |

## Remaining

| Atom | Original residual owner | Missing receipt | Resume | Product boundary |
|:--|:--|:--|:--|:--|
| dependency ledger | P11.4 | complete52R/365 atom join | P11.4 | CoreX plan scope |
| G-CONSUMER-RECONCILE | FlexCRM owner | finite three-file grant | P11.6 manual authority | foreign write separately owned |
