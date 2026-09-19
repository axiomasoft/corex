# P11 consumer pins

p11-reconciliation-report/1
**Item:** P11.2

Actual consumer lock and installed identity reconciliation against the released selected vector.

## Cases

| ID | Result | Expected | Actual | Evidence |
|:--|:--|:--|:--|:--|
| PINS-01 | PASS | base lock/installed match released vector | cohort=f44c0c6fe757aeb8 | PINS-01 |
| PINS-02 | PASS | construction profile independently qualified | construction lock bound | PINS-02 |
| PINS-03 | PASS | property-management profile independently qualified | property-management lock bound | PINS-03 |
| PINS-04 | PASS | transitive/private/engine deps match passport | passport join verified | PINS-04 |
| PINS-05 | PASS | cold no-dev install without symlink/historical ZIP | current cohort fixture only | PINS-05 |
| PINS-06 | PASS | app commit/delta bind P10 targets | mode=readiness | PINS-06 |
| PINS-07 | PASS | input delta invalidates affected pins only | stable receipt reuse policy | PINS-07 |

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

## Evidence

| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| PINS-01 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-pins-lab | base-lock | `artifacts/P11-evidence/pins-01.json` | `fe7f6d306cffdff911456faf2094cc253846dcee0282cd7d1217d3cfadf587fd` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-pins --case PINS-01` | python=3.12 | 2026-09-19T04:20:25.236982+00:00 | 2026-09-19T04:20:25.237055+00:00 | 0 | PASS |
| PINS-02 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-pins-lab | construction-lock | `artifacts/P11-evidence/pins-02.json` | `a68aaf385159b837997d18679a69909333cdd2deb8303e5b80d79956eb45f734` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-pins --case PINS-02` | python=3.12 | 2026-09-19T04:20:25.237306+00:00 | 2026-09-19T04:20:25.237370+00:00 | 0 | PASS |
| PINS-03 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-pins-lab | property-management-lock | `artifacts/P11-evidence/pins-03.json` | `825800f614266af28ac5edc5e023c0049f95f1e76f82d9f95b1137cd15bfb88f` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-pins --case PINS-03` | python=3.12 | 2026-09-19T04:20:25.237597+00:00 | 2026-09-19T04:20:25.237652+00:00 | 0 | PASS |
| PINS-04 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-pins-lab | passport-join | `artifacts/P11-evidence/pins-04.json` | `9b34de6a40eed1e627e73baee636ba8bd0575cdd5435c0c8ed2bfd5d28cb33fa` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-pins --case PINS-04` | python=3.12 | 2026-09-19T04:20:25.237926+00:00 | 2026-09-19T04:20:25.237977+00:00 | 0 | PASS |
| PINS-05 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-pins-lab | cold-install | `artifacts/P11-evidence/pins-05.json` | `2348316213dbf86b09b4990ff147a957409b5a505bdcd7d56dd6882463cb35aa` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-pins --case PINS-05` | python=3.12 | 2026-09-19T04:20:25.238208+00:00 | 2026-09-19T04:20:25.238260+00:00 | 0 | PASS |
| PINS-06 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-pins-lab | app-binding | `artifacts/P11-evidence/pins-06.json` | `05caac960875f7da816c8fc7beea8e0e771c32ae0d3f3b8e0d8068a2bee3c7ee` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-pins --case PINS-06` | python=3.12 | 2026-09-19T04:20:25.238552+00:00 | 2026-09-19T04:20:25.238609+00:00 | 0 | PASS |
| PINS-07 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-pins-lab | delta-policy | `artifacts/P11-evidence/pins-07.json` | `0894f4b0a6cbc28cdd6979b82a8cf5fb3bf694169ab9273facd9fd7652005a6d` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-pins --case PINS-07` | python=3.12 | 2026-09-19T04:20:25.238896+00:00 | 2026-09-19T04:20:25.238976+00:00 | 0 | PASS |

## Acceptance

| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| consumer-pins | R41 R42 | — | p11-reconciliation-report/1 | FlexCRM consumer owner / crm-consumer-pins-20260919 | lock/installed join | P9 vector bound | current | observed PASS | FlexCRM consumer owner / crm-consumer-pins-20260919 | G-CONSUMER pending | PASS |

## Remaining

| Atom | Original residual owner | Missing receipt | Resume | Product boundary |
|:--|:--|:--|:--|:--|
| consumer integration tests | FlexCRM consumer owner | executed command/log receipts | P11.3 | FlexCRM app scope |
| G-CONSUMER-RECONCILE | FlexCRM owner | finite three-file grant | P11.6 manual authority | foreign write separately owned |
