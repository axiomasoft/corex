# P11 reconciliation packet

p11-reconciliation-report/1
**Item:** P11.5

Exact three-file consumer acceptance/handoff packet and recovery simulation.

## Cases

| ID | Result | Expected | Actual | Evidence |
|:--|:--|:--|:--|:--|
| PACKET-01 | PASS | three-file packet prepared locally | acceptance=aa7852bb3d467df1 | PACKET-01 |
| PACKET-02 | PASS | three finite foreign operations | ops=3 | PACKET-02 |
| PACKET-03 | PASS | acceptance payload bindings | atoms=146 | PACKET-03 |
| PACKET-04 | PASS | app-only handoff statement | residuals=3 | PACKET-04 |
| PACKET-05 | PASS | forward/reconciliation simulation | scenarios=6 | PACKET-05 |
| PACKET-06 | PASS | G-CONSUMER-RECONCILE gate packet | digest=f2aeaab0494f6361 | PACKET-06 |

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
| `findings/P11-consumer-tests.md` | `be2c2811ee2a2f748eae4cb3a810e98701f3b7abf66bd29e557c04c8b5f9347c` |
| `findings/P11-dependency-ledger.md` | `d15dbfadf10fa5415872da43a48709b23ac04c6e94908479d7b906127e1b7a75` |
| `artifacts/P11-packet/operations.md` | `da86cb5e35b3c2f11d936c4938178ebc344e32cbefe618694081ce7191ef274d` |
| `artifacts/P11-packet/acceptance.md` | `aa7852bb3d467df1ae6bde6d539d05b8662477a7e6fba143972dd289e02edc2d` |
| `artifacts/P11-packet/progress-addition.md` | `f3430f7b3c49543693797d4a8bacfb4fd00339cd94fc05922663bc991d993c77` |
| `artifacts/P11-packet/handoff.md` | `41dbd4f7017ebe2e466cd1e9747585a7c09121a78dff29134e2add15a882ffe9` |
| `artifacts/P11-packet/simulation.json` | `71d75fe9055b6cb461df0ea8b31312a6ecbf47266d193e8143999957f0baaae9` |

## Evidence

| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| PACKET-01 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-packet-lab | three-file-packet | `artifacts/P11-evidence/packet-01.json` | `87aa4da1b8d895dc703202da81793dad0b4e6ebfda76a7b4be5e36b28d30b3ea` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-packet --case PACKET-01` | python=3.12 | 2026-09-19T05:22:04.965865+00:00 | 2026-09-19T05:22:04.966009+00:00 | 0 | PASS |
| PACKET-02 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-packet-lab | finite-ops | `artifacts/P11-evidence/packet-02.json` | `4909e1bf5e1ba288ba0869b732629a2df114159ed6d0f71ead48b2ad1bf5eb7f` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-packet --case PACKET-02` | python=3.12 | 2026-09-19T05:22:04.966685+00:00 | 2026-09-19T05:22:04.966818+00:00 | 0 | PASS |
| PACKET-03 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-packet-lab | acceptance-bind | `artifacts/P11-evidence/packet-03.json` | `b33c1b873389004412caf0a1349eed58b22b22ce62995e9f7eceb44dbee0094d` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-packet --case PACKET-03` | python=3.12 | 2026-09-19T05:22:04.967366+00:00 | 2026-09-19T05:22:04.967438+00:00 | 0 | PASS |
| PACKET-04 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-packet-lab | handoff-policy | `artifacts/P11-evidence/packet-04.json` | `cefa4b4ef04285d7f20caf1b360f36db2a5bb8e73176d3d93336206ce6d2ab9e` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-packet --case PACKET-04` | python=3.12 | 2026-09-19T05:22:04.967791+00:00 | 2026-09-19T05:22:04.967848+00:00 | 0 | PASS |
| PACKET-05 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-packet-lab | recovery-policy | `artifacts/P11-evidence/packet-05.json` | `00c4d00f2f4b26b3fb51633c352cf9042709a75d05db238ab02526d2d471c2fa` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-packet --case PACKET-05` | python=3.12 | 2026-09-19T05:22:04.968189+00:00 | 2026-09-19T05:22:04.968247+00:00 | 0 | PASS |
| PACKET-06 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-packet-lab | gate-packet | `artifacts/P11-evidence/packet-06.json` | `89abbd38b5152fe1b6bef478784c81ee5ea89986c04d703255fb7036f5ffe5b5` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-packet --case PACKET-06` | python=3.12 | 2026-09-19T05:22:04.968575+00:00 | 2026-09-19T05:22:04.968629+00:00 | 0 | PASS |

## Acceptance

| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| reconciliation-packet | R47 | — | p11-reconciliation-report/1 | ledger+pins+tests | packet prepare | P9 vector bound | current | observed PASS | FlexCRM consumer owner / crm-consumer-tests-20260919 | G-CONSUMER-RECONCILE pending | PASS |

## Remaining

| Atom | Original residual owner | Missing receipt | Resume | Product boundary |
|:--|:--|:--|:--|:--|
| G-CONSUMER-RECONCILE | FlexCRM owner | authenticated finite grant at `f2aeaab0494f636131241fad1a3f526571ef11b408c60d5e66888d12d99b8999` | P11.6 manual authority | foreign write separately owned |
| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |
| consumer write result | FlexCRM owner | owning three-file commit receipt | P11.6 after grant | foreign product boundary |
