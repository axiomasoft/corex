# P11 dependency ledger

p11-reconciliation-report/1
**Item:** P11.4

Frozen R and atomic AC dependency coverage against the released selected vector.

## Cases

| ID | Result | Expected | Actual | Evidence |
|:--|:--|:--|:--|:--|
| LEDGER-01 | PASS | 52R requirements table once | rows=52 | LEDGER-01 |
| LEDGER-02 | PASS | 365 parents via classified map | parents=365;atoms=146 | LEDGER-02 |
| LEDGER-03 | PASS | positive selected atom set | selected=146 | LEDGER-03 |
| LEDGER-04 | PASS | R41/R42 dependency slices only | consumer pins/tests bound | LEDGER-04 |
| LEDGER-05 | PASS | residual owners preserved | residual_rs=24 | LEDGER-05 |
| LEDGER-06 | PASS | gate/receipt digests current | mode=readiness | LEDGER-06 |
| LEDGER-07 | PASS | R52 invariant + A01–A09 | non-CRM proof retained | LEDGER-07 |
| LEDGER-08 | PASS | all selected atoms PASS | pass=146;missing=0 | LEDGER-08 |

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

## Evidence

| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| LEDGER-01 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-ledger-lab | requirements-lock | `artifacts/P11-evidence/ledger-01.json` | `7f688efe2070551e71c5e2455a583231aff6802157108a9eee3d4e6376e6db59` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-ledger --case LEDGER-01` | python=3.12 | 2026-09-19T05:15:12.371045+00:00 | 2026-09-19T05:15:12.371263+00:00 | 0 | PASS |
| LEDGER-02 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-ledger-lab | parent-join | `artifacts/P11-evidence/ledger-02.json` | `50085bcd98cf32d68bc85c0aff056899c6334c0714712f033c0d720daeaad840` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-ledger --case LEDGER-02` | python=3.12 | 2026-09-19T05:15:12.372245+00:00 | 2026-09-19T05:15:12.372352+00:00 | 0 | PASS |
| LEDGER-03 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-ledger-lab | selected-set | `artifacts/P11-evidence/ledger-03.json` | `ac1b7b741f1bec57c0f3b1cef05dc136f9d06b4660cc82c04eccf60b54d3dd5b` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-ledger --case LEDGER-03` | python=3.12 | 2026-09-19T05:15:12.372859+00:00 | 2026-09-19T05:15:12.372936+00:00 | 0 | PASS |
| LEDGER-04 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-ledger-lab | r41-r42-slices | `artifacts/P11-evidence/ledger-04.json` | `b0095b000abb44b8749b6588e013d1470a2a55041a737ff30ea17b184265c9f5` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-ledger --case LEDGER-04` | python=3.12 | 2026-09-19T05:15:12.373472+00:00 | 2026-09-19T05:15:12.373546+00:00 | 0 | PASS |
| LEDGER-05 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-ledger-lab | residual-owners | `artifacts/P11-evidence/ledger-05.json` | `417419dc976138b36609f6e93c78350a27c82d2eaa632c6dfcda9336453b2222` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-ledger --case LEDGER-05` | python=3.12 | 2026-09-19T05:15:12.373935+00:00 | 2026-09-19T05:15:12.374007+00:00 | 0 | PASS |
| LEDGER-06 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-ledger-lab | gate-join | `artifacts/P11-evidence/ledger-06.json` | `2bfbe1b000ae0fa1c2c4094525ec1e8ad06027585b00992f619ffced27d21787` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-ledger --case LEDGER-06` | python=3.12 | 2026-09-19T05:15:12.374557+00:00 | 2026-09-19T05:15:12.374635+00:00 | 0 | PASS |
| LEDGER-07 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-ledger-lab | r52-invariant | `artifacts/P11-evidence/ledger-07.json` | `025b981f944ef06c0caad49ee7a3da7a71883cba3a2dc7c168c5adf287996691` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-ledger --case LEDGER-07` | python=3.12 | 2026-09-19T05:15:12.375065+00:00 | 2026-09-19T05:15:12.375131+00:00 | 0 | PASS |
| LEDGER-08 | corex | 0ca85d24edd631314c2ca410ea8b9fad4002419c | qualification | owner-policy/p11-ledger-lab | ledger-complete | `artifacts/P11-evidence/ledger-08.json` | `ec9639308badd4dacf7fc1e39c482316f22e80046cffd2b2fdc3ae4e5e8d35f3` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-ledger --case LEDGER-08` | python=3.12 | 2026-09-19T05:15:12.375477+00:00 | 2026-09-19T05:15:12.375541+00:00 | 0 | PASS |

## Requirements

| R | Original source | Disposition | Selected dependency atoms | Producer receipts | Consumer receipts | Result | Full original residual owner |
|:--|:--|:--|:--|:--|:--|:--|:--|
| R01 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R01; s | QUALIFY | B33 AC-5.a1, B33 AC-6.a1 | findings/P3-adapter-receipts.md; findings/P9-cohort-receipt.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX storage maintainer |
| R02 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R02; s | QUALIFY | none (outside dependency slice) | findings/P3-adapter-receipts.md; findings/P9-cohort-receipt.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX tenancy maintainer |
| R03 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R03; s | QUALIFY | none (outside dependency slice) | findings/P3-adapter-receipts.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX tenancy + Central identity owner |
| R04 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R04; s | QUALIFY | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P3-adapter-receipts.md; findings/P9-cohort-receipt.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX contract maintainer + Central API owner |
| R05 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R05; s | QUALIFY | none (outside dependency slice) | findings/P3-adapter-receipts.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX membership maintainer |
| R06 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R06; s | QUALIFY | none (outside dependency slice) | findings/P3-adapter-receipts.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX adapter maintainer + FlexCRM auth owner |
| R07 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R07; s | QUALIFY | none (outside dependency slice) | findings/P3-adapter-receipts.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX read-adapter + Central accounts API owner |
| R08 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R08; s | QUALIFY | none (outside dependency slice) | findings/P8-tool-receipts.md; findings/P9-cohort-receipt.md | findings/P11-intake.md | PASS | CoreX modules maintainer + FlexCRM MCP owner |
| R09 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R09; s | EXTERNAL | B12 AC-1.a1, B12 AC-1.a4, B12 AC-1.a5, B12 AC-10.a1, B12 AC-10.a10, B12 AC-10.a2, B12 AC-10.a3, B12 AC-10.a4, B12 AC-10.a5, B12 AC-10.a6, B12 AC-10.a7, B12 AC-10.a8, B12 AC-10.a9, B12 AC-11.a1, B12 AC-11.a2, B12 AC-12.a1, B12 AC-12.a2, B12 AC-12.a3, B12 AC-16.a1, B12 AC-16.a2, B12 AC-17.a1, B12 AC-17.a2, B12 AC-18.a1, B12 AC-19.a1, B12 AC-19.a2, B12 AC-2.a1, B12 AC-2.a2, B12 AC-21.a1, B12 AC-22.a1, B12 AC-23.a1, B12 AC-24.a1, B12 AC-24.a2, B12 AC-25.a1, B12 AC-26.a1, B12 AC-26.a2, B12 AC-26.a3, B12 AC-27.a1, B12 AC-28.a1, B12 AC-28.a2, B12 AC-29.a1, B12 AC-29.a2, B12 AC-3.a1, B12 AC-3.a2, B12 AC-30.a1, B12 AC-30.a2, B12 AC-4.a1, B12 AC-4.a2, B12 AC-5.a1, B12 AC-5.a2, B12 AC-6.a1, B12 AC-6.a2, B12 AC-7.a1, B12 AC-7.a2, B12 AC-8.a1, B12 AC-8.a2, B12 AC-9.a1 | findings/P5-wakeup-receipts.md | findings/P11-intake.md | PASS | Standalone workflow/statecraft owner |
| R10 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R10; s | DELTA | B12 AC-14.a1, B12 AC-14.a2 | findings/P2-contract-catalogue.md; findings/P5-wakeup-receipts.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX workflow/tenancy adapter maintainer |
| R11 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R11; s | QUALIFY | B15 AC-1.a1, B15 AC-10.a1, B15 AC-11.a1, B15 AC-12.a1, B15 AC-12.a2, B15 AC-13.a1, B15 AC-14.a1, B15 AC-14.a2, B15 AC-14.a3, B15 AC-14.a4, B15 AC-15.a1, B15 AC-15.a2, B15 AC-16.a1, B15 AC-17.a1, B15 AC-18.a1, B15 AC-18.a2, B15 AC-18.a3, B15 AC-19.a1, B15 AC-2.a1, B15 AC-20.a1, B15 AC-20.a2, B15 AC-20.a3, B15 AC-21.a1, B15 AC-21.a2, B15 AC-21.a3, B15 AC-22.a1, B15 AC-22.a2, B15 AC-23.a1, B15 AC-24.a1, B15 AC-25.a1, B15 AC-25.a2, B15 AC-26.a1, B15 AC-26.a2, B15 AC-27.a1, B15 AC-27.a3, B15 AC-29.a1, B15 AC-3.a1, B15 AC-4.a1, B15 AC-5.a1, B15 AC-6.a1, B15 AC-6.a2, B15 AC-7.a1, B15 AC-7.a2, B15 AC-7.a3, B15 AC-8.a1, B15 AC-9.a1 | findings/P2-contract-catalogue.md; findings/P6-data-receipts.md | findings/P11-intake.md | PASS | CoreX extension maintainer; attributes engine owner |
| R12 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R12; s | DELTA | B18.§8.1.a1, B18.§8.12.a1, B18.§8.2.a1, B18.§8.2.a2, B18.§8.21.a2, B18.§8.21.a3, B18.§8.25.a1, B18.§8.3.a1, B18.§8.4.a1, B18.§8.4.a2, B18.§8.5.a1, B18.§8.5.a2, B18.§8.6.a2 | findings/P2-contract-catalogue.md; findings/P6-data-receipts.md | findings/P11-intake.md | PASS | CoreX import adapter maintainer |
| R13 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R13; s | APP | B18.§8.12.a2 | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM migration owner; external connector maintainers |
| R14 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R14; s | QUALIFY | B16.§8.1.a2, B16.§8.20.a1, B16.§8.20.a5 | findings/P2-contract-catalogue.md; findings/P4-extension-receipts.md | findings/P11-intake.md | PASS | CoreX extension maintainer |
| R15 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R15; s | DELTA | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P4-extension-receipts.md | findings/P11-intake.md | PASS | CoreX media maintainer |
| R16 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R16; s | DELTA | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P4-extension-receipts.md | findings/P11-intake.md | PASS | CoreX notifications maintainer |
| R17 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R17; s | QUALIFY | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P7-utility-receipts.md; findings/P9-cohort-receipt.md | findings/P11-intake.md | PASS | CoreX tasks maintainer |
| R18 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R18; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | flex/crm maintainer + FlexCRM backend owner |
| R19 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R19; s | APP | none (outside dependency slice) | findings/P7-utility-receipts.md | findings/P11-intake.md | PASS | flex/tasks maintainer |
| R20 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R20; s | DELTA | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P7-utility-receipts.md | findings/P11-intake.md | PASS | CoreX recurrence maintainer |
| R21 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R21; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | flex/social maintainer |
| R22 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R22; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | flex/calendar maintainer + CalDAV adapter owner |
| R23 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R23; s | DELTA | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P7-utility-receipts.md | findings/P11-intake.md | PASS | CoreX intl maintainer |
| R24 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R24; s | QUALIFY | B35 AC-12.a1 | findings/P2-contract-catalogue.md; findings/P7-utility-receipts.md | findings/P11-intake.md | PASS | CoreX intl maintainer |
| R25 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R25; s | DELTA | B35 AC-6.a1, B35 AC-6.a2 | findings/P2-contract-catalogue.md; findings/P7-utility-receipts.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX intl/search maintainer |
| R26 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R26; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM finance owner + data/ops adapter owner |
| R27 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R27; s | EXTERNAL | B32 AC-1.a1, B32 AC-11.a1, B32 AC-11.a2, B32 AC-13.a1, B32 AC-14.a1, B32 AC-15.a1, B32 AC-2.a1, B32 AC-2.a2, B32 AC-3.a1, B32 AC-4.a1, B32 AC-4.a2, B32 AC-5.a1, B32 AC-6.a1 | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | Standalone AI owner + FlexCRM agent owner |
| R28 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R28; s | DELTA | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P4-extension-receipts.md | findings/P11-intake.md | PASS | CoreX query-extension maintainer |
| R29 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R29; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | flex/marketing maintainer |
| R30 | research/01a-acceptance-index.md; No acceptance under this scope; only separate explicit future owner approval can admit this prod | EXCLUDED | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexStore + commerce maintainer |
| R31 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R31; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM Studio owner |
| R32 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R32; s | DELTA | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P6-data-receipts.md | findings/P11-intake.md | PASS | CoreX search maintainer |
| R33 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R33; s | QUALIFY | B18.§8.21.a1 | findings/P3-adapter-receipts.md; findings/P9-cohort-receipt.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | CoreX tenancy/maintenance maintainer |
| R34 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R34; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | flex/attendance maintainer |
| R35 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R35; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM MCP owner |
| R36 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R36; s | QUALIFY | B32 AC-12.a1 | findings/P2-contract-catalogue.md; findings/P8-tool-receipts.md; findings/P9-cohort-receipt.md | findings/P11-intake.md | PASS | CoreX modules/API adapter maintainer |
| R37 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R37; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM integration + Vaulter owner |
| R38 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R38; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM integration + Chatom owner |
| R39 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R39; s | EXTERNAL | none (outside dependency slice) | findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | Flex central control-plane owner |
| R40 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R40; s | EXTERNAL | none (outside dependency slice) | findings/P2-contract-catalogue.md; findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | Central FLEX identity owner |
| R41 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R41; s | APP | none (outside dependency slice) | findings/P11-consumer-tests.md | findings/P11-consumer-pins.md; findings/P11-consumer-tests.md | PASS | FlexCRM application owner |
| R42 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R42; s | APP | none (outside dependency slice) | findings/P11-consumer-tests.md | findings/P11-consumer-pins.md; findings/P11-consumer-tests.md | PASS | FlexCRM application owner |
| R43 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R43; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM auth owner |
| R44 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R44; s | APP | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM foundation owner |
| R45 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R45; s | QUALIFY | none (outside dependency slice) | findings/P9-cohort-receipt.md | findings/P11-intake.md | PASS | CoreX release maintainer + source owners |
| R46 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R46; s | EXTERNAL | none (outside dependency slice) | findings/P10-effective-receipts.md | findings/P11-intake.md | PASS | Central/consumer/PKI/ops owners named in gates |
| R47 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R47; s | QUALIFY | none (outside dependency slice) | findings/P11-consumer-tests.md | findings/P11-intake.md | PASS | CoreX maintainer + FlexCRM reconciliation owner |
| R48 | research/01a-acceptance-index.md; No acceptance under this scope; only separate explicit future owner approval can admit this prod | EXCLUDED | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCMS/frontend owner |
| R49 | research/01a-acceptance-index.md; No acceptance under this scope; only separate explicit future owner approval can admit this prod | EXCLUDED | none (outside dependency slice) | findings/P1-downstream-gaps.md | findings/P11-intake.md | PASS | FlexCRM future product owner |
| R50 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R50; s | QUALIFY | none (outside dependency slice) | findings/P8-tool-receipts.md; findings/P9-cohort-receipt.md | findings/P11-intake.md | PASS | CoreX modules/release maintainer |
| R51 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R51; s | QUALIFY | B11 AC-1.a2 | findings/P2-contract-catalogue.md; findings/P9-cohort-receipt.md | findings/P11-intake.md | PASS | AzGuard owner + consumer policy owner |
| R52 | research/01a-acceptance-index.md; Exact original parent/operative-condition or non-AC source coverage from map Requirements/R52; s | INVARIANT | B11 AC-28.a2, B11 AC-29.a1, B11 AC-29.a2, B11 AC-30.a1 | findings/P9-cohort-receipt.md; findings/P10-effective-receipts.md; findings/P11-consumer-tests.md | findings/P9-cohort-receipt.md; findings/P11-consumer-tests.md | PASS | CoreX contract maintainer |

## Parent coverage

| Parent | Atomic map SHA256 | Dependency atoms | Original residual owner |
|:--|:--|:--|:--|
| B10 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-16 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-17 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-18 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-19 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-20 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-21 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-22 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-23 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-24 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-25 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-26 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R48/excluded |
| B10 AC-27 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R48/excluded |
| B10 AC-28 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R48/excluded |
| B10 AC-29 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R48/excluded |
| B10 AC-30 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R48/excluded |
| B10 AC-31 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R52/app |
| B10 AC-32 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R52/app |
| B10 AC-33 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R50/app |
| B10 AC-34 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R36/app |
| B11 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B11 AC-1.a2 | R39/external-ops, R51/app, R51/standalone |
| B11 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B11 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops, R43/app |
| B11 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B11 AC-16 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B11 AC-17 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R43/app |
| B11 AC-18 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R43/app |
| B11 AC-19 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R43/app |
| B11 AC-20 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R43/app |
| B11 AC-21 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B11 AC-22 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B11 AC-23 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B11 AC-24 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B11 AC-25 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B11 AC-26 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B11 AC-27 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B11 AC-28 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B11 AC-28.a2 | R41/app, R51/app, R52/corex |
| B11 AC-29 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B11 AC-29.a1, B11 AC-29.a2 | R52/corex |
| B11 AC-30 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B11 AC-30.a1 | R52/corex |
| B11 AC-31 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B11 AC-32 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R52/external-ops |
| B12 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-1.a1, B12 AC-1.a4, B12 AC-1.a5 | R09/standalone, R31/app, R39/external-ops |
| B12 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-2.a1, B12 AC-2.a2 | R09/standalone |
| B12 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-3.a1, B12 AC-3.a2 | R09/standalone |
| B12 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-4.a1, B12 AC-4.a2 | R09/standalone |
| B12 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-5.a1, B12 AC-5.a2 | R09/standalone |
| B12 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-6.a1, B12 AC-6.a2 | R09/standalone |
| B12 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-7.a1, B12 AC-7.a2 | R09/standalone |
| B12 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-8.a1, B12 AC-8.a2 | R09/standalone |
| B12 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-9.a1 | R09/standalone |
| B12 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-10.a1, B12 AC-10.a10, B12 AC-10.a2, B12 AC-10.a3, B12 AC-10.a4, B12 AC-10.a5, B12 AC-10.a6, B12 AC-10.a7, B12 AC-10.a8, B12 AC-10.a9 | R09/standalone |
| B12 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-11.a1, B12 AC-11.a2 | R09/standalone |
| B12 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-12.a1, B12 AC-12.a2, B12 AC-12.a3 | R09/standalone |
| B12 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R10/external-ops, R46/external-ops |
| B12 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-14.a1, B12 AC-14.a2 | R10/standalone |
| B12 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R10/external-ops |
| B12 AC-16 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-16.a1, B12 AC-16.a2 | R09/standalone |
| B12 AC-17 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-17.a1, B12 AC-17.a2 | R09/standalone |
| B12 AC-18 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-18.a1 | R09/standalone |
| B12 AC-19 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-19.a1, B12 AC-19.a2 | R09/standalone |
| B12 AC-20 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B12 AC-21 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-21.a1 | R09/standalone |
| B12 AC-22 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-22.a1 | R09/standalone |
| B12 AC-23 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-23.a1 | R09/standalone |
| B12 AC-24 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-24.a1, B12 AC-24.a2 | R09/standalone |
| B12 AC-25 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-25.a1 | R09/standalone |
| B12 AC-26 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-26.a1, B12 AC-26.a2, B12 AC-26.a3 | R09/standalone |
| B12 AC-27 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-27.a1 | R09/standalone |
| B12 AC-28 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-28.a1, B12 AC-28.a2 | R09/standalone |
| B12 AC-29 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-29.a1, B12 AC-29.a2 | R09/standalone |
| B12 AC-30 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B12 AC-30.a1, B12 AC-30.a2 | R09/standalone |
| B13 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-16 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-17 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-18 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-19 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B13 AC-20 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R17/app |
| B13 AC-21 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R17/app |
| B13 AC-22 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R17/app |
| B13 AC-23 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R17/app |
| B13 AC-24 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R17/app |
| B13 AC-25 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R42/app |
| B13 AC-26 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R42/app |
| B13 AC-27 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R42/app |
| B13 AC-28 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R42/app |
| B13 AC-29 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R42/app |
| B13 AC-30 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-31 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-32 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-33 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-34 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-35 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-36 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-37 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-38 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B13 AC-39 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R30/app |
| B13 AC-40 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R41/app |
| B13 AC-41 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R42/app |
| B13 AC-42 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B15 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-1.a1 | R11/standalone |
| B15 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-2.a1 | R11/standalone |
| B15 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-3.a1 | R11/standalone |
| B15 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-4.a1 | R11/standalone |
| B15 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-5.a1 | R11/standalone |
| B15 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-6.a1, B15 AC-6.a2 | R11/standalone |
| B15 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-7.a1, B15 AC-7.a2, B15 AC-7.a3 | R11/standalone |
| B15 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-8.a1 | R11/standalone |
| B15 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-9.a1 | R11/standalone |
| B15 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-10.a1 | R11/standalone |
| B15 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-11.a1 | R11/standalone |
| B15 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-12.a1, B15 AC-12.a2 | R11/standalone |
| B15 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-13.a1 | R11/standalone |
| B15 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-14.a1, B15 AC-14.a2, B15 AC-14.a3, B15 AC-14.a4 | R11/standalone |
| B15 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-15.a1, B15 AC-15.a2 | R11/standalone |
| B15 AC-16 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-16.a1 | R11/standalone |
| B15 AC-17 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-17.a1 | R11/standalone |
| B15 AC-18 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-18.a1, B15 AC-18.a2, B15 AC-18.a3 | R11/standalone |
| B15 AC-19 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-19.a1 | R11/standalone |
| B15 AC-20 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-20.a1, B15 AC-20.a2, B15 AC-20.a3 | R11/standalone |
| B15 AC-21 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-21.a1, B15 AC-21.a2, B15 AC-21.a3 | R11/standalone |
| B15 AC-22 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-22.a1, B15 AC-22.a2 | R11/standalone |
| B15 AC-23 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-23.a1 | R11/standalone |
| B15 AC-24 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-24.a1 | R11/standalone |
| B15 AC-25 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-25.a1, B15 AC-25.a2 | R11/standalone |
| B15 AC-26 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-26.a1, B15 AC-26.a2 | R11/standalone, R36/app |
| B15 AC-27 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-27.a1, B15 AC-27.a3 | R11/app, R11/standalone |
| B15 AC-28 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R32/app |
| B15 AC-29 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B15 AC-29.a1 | R11/standalone |
| B15 AC-30 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R33/external-ops |
| B27 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B27 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R19/app |
| B28 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B28 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R21/app |
| B29 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R20/app |
| B29 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B29 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R22/app |
| B30 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B30 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R29/app |
| B31 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B31 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R28/app |
| B31 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R31/app |
| B32 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-1.a1 | R27/standalone |
| B32 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-2.a1, B32 AC-2.a2 | R27/standalone |
| B32 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-3.a1 | R27/standalone |
| B32 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-4.a1, B32 AC-4.a2 | R27/standalone |
| B32 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-5.a1 | R27/standalone |
| B32 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-6.a1 | R27/standalone |
| B32 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R35/app |
| B32 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B32 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B32 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R51/app |
| B32 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-11.a1, B32 AC-11.a2 | R27/standalone |
| B32 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-12.a1 | R35/app, R36/corex |
| B32 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-13.a1 | R27/standalone |
| B32 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-14.a1 | R27/standalone |
| B32 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B32 AC-15.a1 | R27/standalone |
| B33 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B33 AC-5.a1 | R01/corex |
| B33 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B33 AC-6.a1 | R01/corex |
| B33 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R01/app |
| B33 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B33 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R01/external-ops |
| B33 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R39/external-ops |
| B34 AC-19 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops, R43/app |
| B34 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B34 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R03/external-ops |
| B34 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R03/external-ops |
| B34 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R03/external-ops |
| B34 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R06/app, R40/external-ops, R46/external-ops |
| B34 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R07/external-ops |
| B34 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R52/app, R52/external-ops |
| B34 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops, R43/app |
| B34 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B34 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R07/external-ops |
| B34 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B34 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops, R43/app |
| B34 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B34 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B34 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R40/external-ops |
| B35 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R26/app, R52/external-ops |
| B35 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R23/app |
| B35 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R26/app |
| B35 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R26/app |
| B35 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R32/app |
| B35 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B35 AC-6.a1, B35 AC-6.a2 | R25/standalone |
| B35 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R23/app |
| B35 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R25/app, R36/app |
| B35 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R26/app |
| B35 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R23/app |
| B35 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R52/app |
| B35 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B35 AC-12.a1 | R24/corex |
| B35 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R26/app |
| B35 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R26/app |
| B35 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R26/app |
| B36 AC-1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B36 AC-16 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R34/app |
| B16.§8.1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B16.§8.1.a2 | R14/corex, R37/app |
| B16.§8.2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R38/app |
| B16.§8.3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R38/app |
| B16.§8.4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R38/app |
| B16.§8.5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R38/app |
| B16.§8.6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R38/app |
| B16.§8.7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R37/app |
| B16.§8.8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R37/app |
| B16.§8.9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R37/app |
| B16.§8.10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R37/app |
| B16.§8.11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R37/app |
| B16.§8.12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R15/app |
| B16.§8.13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R38/app |
| B16.§8.14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R16/app |
| B16.§8.15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R16/app |
| B16.§8.16 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R10/app |
| B16.§8.17 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R18/app |
| B16.§8.18 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R37/app, R38/app |
| B16.§8.19 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R38/app |
| B16.§8.20 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B16.§8.20.a1, B16.§8.20.a5 | R14/corex, R16/app, R37/app, R38/app |
| B18.§8.1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.1.a1 | R12/standalone |
| B18.§8.2 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.2.a1, B18.§8.2.a2 | R12/standalone |
| B18.§8.3 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.3.a1 | R12/standalone |
| B18.§8.4 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.4.a1, B18.§8.4.a2 | R12/standalone |
| B18.§8.5 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.5.a1, B18.§8.5.a2 | R12/standalone |
| B18.§8.6 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.6.a2 | R12/standalone, R13/app |
| B18.§8.7 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.8 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.9 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.10 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.11 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.12 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.12.a1, B18.§8.12.a2 | R12/standalone, R13/standalone |
| B18.§8.13 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.14 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.15 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.16 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R30/excluded |
| B18.§8.17 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R30/excluded |
| B18.§8.18 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R30/excluded |
| B18.§8.19 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R30/excluded |
| B18.§8.20 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.21 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.21.a1, B18.§8.21.a2, B18.§8.21.a3 | R12/standalone, R33/standalone |
| B18.§8.22 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.23 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R13/app |
| B18.§8.24 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | — | R30/excluded |
| B18.§8.25 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | B18.§8.25.a1 | R12/standalone |

## Selected atoms

| Atom | R | Parent | Disposition | Owner | Required stages | Contract | Evidence | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| B11 AC-1.a2 | R51 | B11 AC-1 | QUALIFY | AzGuard owner + consumer policy owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P2-contract-catalogue.md; findings/P9-cohort-receipt.md; findings/P11-intake.md | PASS |
| B11 AC-28.a2 | R52 | B11 AC-28 | INVARIANT | CoreX boxed tenant-context adapter maintainer | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P9-cohort-receipt.md; findings/P11-consumer-tests.md | PASS |
| B11 AC-29.a1 | R52 | B11 AC-29 | INVARIANT | CoreX contract/tenancy/layer-test maintainers | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P9-cohort-receipt.md; findings/P11-consumer-tests.md | PASS |
| B11 AC-29.a2 | R52 | B11 AC-29 | INVARIANT | CoreX contract/tenancy/layer-test maintainers | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P9-cohort-receipt.md; findings/P11-consumer-tests.md | PASS |
| B11 AC-30.a1 | R52 | B11 AC-30 | INVARIANT | CoreX contract/tenancy/layer-test maintainers | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P9-cohort-receipt.md; findings/P11-consumer-tests.md | PASS |
| B12 AC-1.a1 | R09 | B12 AC-1 | EXTERNAL | Workflow engine schema owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-1.a4 | R09 | B12 AC-1 | EXTERNAL | Workflow engine schema owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-1.a5 | R09 | B12 AC-1 | EXTERNAL | Workflow engine schema owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a1 | R09 | B12 AC-10 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a10 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a2 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a3 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a4 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a5 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a6 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a7 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a8 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-10.a9 | R09 | B12 AC-10 | EXTERNAL | Workflow approval engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-11.a1 | R09 | B12 AC-11 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-11.a2 | R09 | B12 AC-11 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-12.a1 | R09 | B12 AC-12 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-12.a2 | R09 | B12 AC-12 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-12.a3 | R09 | B12 AC-12 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-14.a1 | R10 | B12 AC-14 | DELTA | Workflow engine/local-sweep adapter owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P10-effective-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-14.a2 | R10 | B12 AC-14 | DELTA | Workflow engine/local-sweep adapter owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P10-effective-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-16.a1 | R09 | B12 AC-16 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-16.a2 | R09 | B12 AC-16 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-17.a1 | R09 | B12 AC-17 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-17.a2 | R09 | B12 AC-17 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-18.a1 | R09 | B12 AC-18 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-19.a1 | R09 | B12 AC-19 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-19.a2 | R09 | B12 AC-19 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-2.a1 | R09 | B12 AC-2 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-2.a2 | R09 | B12 AC-2 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-21.a1 | R09 | B12 AC-21 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-22.a1 | R09 | B12 AC-22 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-23.a1 | R09 | B12 AC-23 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-24.a1 | R09 | B12 AC-24 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-24.a2 | R09 | B12 AC-24 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-25.a1 | R09 | B12 AC-25 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-26.a1 | R09 | B12 AC-26 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-26.a2 | R09 | B12 AC-26 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-26.a3 | R09 | B12 AC-26 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-27.a1 | R09 | B12 AC-27 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-28.a1 | R09 | B12 AC-28 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-28.a2 | R09 | B12 AC-28 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-29.a1 | R09 | B12 AC-29 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-29.a2 | R09 | B12 AC-29 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-3.a1 | R09 | B12 AC-3 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-3.a2 | R09 | B12 AC-3 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-30.a1 | R09 | B12 AC-30 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-30.a2 | R09 | B12 AC-30 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-4.a1 | R09 | B12 AC-4 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-4.a2 | R09 | B12 AC-4 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-5.a1 | R09 | B12 AC-5 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-5.a2 | R09 | B12 AC-5 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-6.a1 | R09 | B12 AC-6 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-6.a2 | R09 | B12 AC-6 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-7.a1 | R09 | B12 AC-7 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-7.a2 | R09 | B12 AC-7 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-8.a1 | R09 | B12 AC-8 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-8.a2 | R09 | B12 AC-8 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B12 AC-9.a1 | R09 | B12 AC-9 | EXTERNAL | Standalone workflow/statecraft owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P5-wakeup-receipts.md; findings/P11-intake.md | PASS |
| B15 AC-1.a1 | R11 | B15 AC-1 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-10.a1 | R11 | B15 AC-10 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-11.a1 | R11 | B15 AC-11 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-12.a1 | R11 | B15 AC-12 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-12.a2 | R11 | B15 AC-12 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-13.a1 | R11 | B15 AC-13 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-14.a1 | R11 | B15 AC-14 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-14.a2 | R11 | B15 AC-14 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-14.a3 | R11 | B15 AC-14 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-14.a4 | R11 | B15 AC-14 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-15.a1 | R11 | B15 AC-15 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-15.a2 | R11 | B15 AC-15 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-16.a1 | R11 | B15 AC-16 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-17.a1 | R11 | B15 AC-17 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-18.a1 | R11 | B15 AC-18 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-18.a2 | R11 | B15 AC-18 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-18.a3 | R11 | B15 AC-18 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-19.a1 | R11 | B15 AC-19 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-2.a1 | R11 | B15 AC-2 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-20.a1 | R11 | B15 AC-20 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-20.a2 | R11 | B15 AC-20 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-20.a3 | R11 | B15 AC-20 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-21.a1 | R11 | B15 AC-21 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-21.a2 | R11 | B15 AC-21 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-21.a3 | R11 | B15 AC-21 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-22.a1 | R11 | B15 AC-22 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-22.a2 | R11 | B15 AC-22 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-23.a1 | R11 | B15 AC-23 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-24.a1 | R11 | B15 AC-24 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-25.a1 | R11 | B15 AC-25 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-25.a2 | R11 | B15 AC-25 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-26.a1 | R11 | B15 AC-26 | QUALIFY | Attributes API/metadata owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P6-data-receipts.md; findings/P11-intake.md | PASS |
| B15 AC-26.a2 | R11 | B15 AC-26 | QUALIFY | Attributes API/metadata owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P6-data-receipts.md; findings/P11-intake.md | PASS |
| B15 AC-27.a1 | R11 | B15 AC-27 | QUALIFY | Attributes engine/quota provider owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-27.a3 | R11 | B15 AC-27 | QUALIFY | Attributes engine/quota provider owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-29.a1 | R11 | B15 AC-29 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-3.a1 | R11 | B15 AC-3 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-4.a1 | R11 | B15 AC-4 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-5.a1 | R11 | B15 AC-5 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-6.a1 | R11 | B15 AC-6 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-6.a2 | R11 | B15 AC-6 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-7.a1 | R11 | B15 AC-7 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-7.a2 | R11 | B15 AC-7 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-7.a3 | R11 | B15 AC-7 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-8.a1 | R11 | B15 AC-8 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B15 AC-9.a1 | R11 | B15 AC-9 | QUALIFY | Attributes engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B16.§8.1.a2 | R14 | B16.§8.1 | QUALIFY | CoreX extension maintainer | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P4-extension-receipts.md; findings/P11-intake.md | PASS |
| B16.§8.20.a1 | R14 | B16.§8.20 | QUALIFY | CoreX extension maintainer | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P4-extension-receipts.md; findings/P11-intake.md | PASS |
| B16.§8.20.a5 | R14 | B16.§8.20 | QUALIFY | CoreX extension maintainer | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P4-extension-receipts.md; findings/P11-intake.md | PASS |
| B18.§8.1.a1 | R12 | B18.§8.1 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.12.a1 | R12 | B18.§8.12 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.12.a2 | R13 | B18.§8.12 | APP | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.2.a1 | R12 | B18.§8.2 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.2.a2 | R12 | B18.§8.2 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.21.a1 | R33 | B18.§8.21 | QUALIFY | Offboarding/export engine owner; CoreX maintenance/import port maintainers separately | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P3-adapter-receipts.md; findings/P10-effective-receipts.md; findings/P11-intake.md | PASS |
| B18.§8.21.a2 | R12 | B18.§8.21 | DELTA | Offboarding/export engine owner; CoreX maintenance/import port maintainers separately | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P6-data-receipts.md; findings/P11-intake.md | PASS |
| B18.§8.21.a3 | R12 | B18.§8.21 | DELTA | Offboarding/export engine owner; CoreX maintenance/import port maintainers separately | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P6-data-receipts.md; findings/P11-intake.md | PASS |
| B18.§8.25.a1 | R12 | B18.§8.25 | DELTA | Import engine contract maintainer; each aggregate provider owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P6-data-receipts.md; findings/P11-intake.md | PASS |
| B18.§8.3.a1 | R12 | B18.§8.3 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.4.a1 | R12 | B18.§8.4 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.4.a2 | R12 | B18.§8.4 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.5.a1 | R12 | B18.§8.5 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.5.a2 | R12 | B18.§8.5 | DELTA | Import/export engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P11-intake.md; findings/P11-intake.md | PASS |
| B18.§8.6.a2 | R12 | B18.§8.6 | DELTA | Import mapping/sample engine owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P6-data-receipts.md; findings/P11-intake.md | PASS |
| B32 AC-1.a1 | R27 | B32 AC-1 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-11.a1 | R27 | B32 AC-11 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-11.a2 | R27 | B32 AC-11 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-12.a1 | R36 | B32 AC-12 | QUALIFY | CoreX modules/API adapter maintainer | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P8-tool-receipts.md; findings/P11-intake.md | PASS |
| B32 AC-13.a1 | R27 | B32 AC-13 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-14.a1 | R27 | B32 AC-14 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-15.a1 | R27 | B32 AC-15 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-2.a1 | R27 | B32 AC-2 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-2.a2 | R27 | B32 AC-2 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-3.a1 | R27 | B32 AC-3 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-4.a1 | R27 | B32 AC-4 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-4.a2 | R27 | B32 AC-4 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-5.a1 | R27 | B32 AC-5 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B32 AC-6.a1 | R27 | B32 AC-6 | EXTERNAL | Standalone AI owner + FlexCRM agent owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P1-downstream-gaps.md; findings/P11-intake.md | PASS |
| B33 AC-5.a1 | R01 | B33 AC-5 | QUALIFY | CoreX storage maintainer | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P3-adapter-receipts.md; findings/P11-intake.md | PASS |
| B33 AC-6.a1 | R01 | B33 AC-6 | QUALIFY | CoreX storage maintainer | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P3-adapter-receipts.md; findings/P11-intake.md | PASS |
| B35 AC-12.a1 | R24 | B35 AC-12 | QUALIFY | CoreX intl maintainer | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P7-utility-receipts.md; findings/P11-intake.md | PASS |
| B35 AC-6.a1 | R25 | B35 AC-6 | DELTA | Installed geography dataset/lookup provider owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P7-utility-receipts.md; findings/P10-effective-receipts.md; findings/P11-intake.md | PASS |
| B35 AC-6.a2 | R25 | B35 AC-6 | DELTA | Installed geography dataset/lookup provider owner | intake; pins; tests; producer seals; P10-readiness | p11-reconciliation-report/1 | findings/P7-utility-receipts.md; findings/P10-effective-receipts.md; findings/P11-intake.md | PASS |

## Cohort

| Field | Value |
|:--|:--|
| Released cohort SHA256 | `f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee` |
| Released vector | `0.4.0-alpha.20260918` |
| Released commit | `0ca85d24edd631314c2ca410ea8b9fad4002419c` |
| P10 consumer input mode | `readiness` |
| P10 required gaps | `169` |

## Sealed inputs

| Path | SHA256 |
|:--|:--|
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` |
| `findings/P1-evidence-ledger.md` | `ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87` |
| `findings/P1-downstream-gaps.md` | `6284a6d649f3f4bbbe5468dfb502e7915fe90b38ddf7f9a7441995baf0b11e3a` |

## Acceptance

| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| dependency-ledger | R47 | — | p11-reconciliation-report/1 | P1 lock/map/downstream | ledger join | P9 vector bound | current | observed PASS | executor/P11.4 qualification | G-CONSUMER pending | PASS |

## Remaining

| Atom | Original residual owner | Missing receipt | Resume | Product boundary |
|:--|:--|:--|:--|:--|
| reconciliation packet | P11.5 | exact three-file diff/simulation | P11.5 | CoreX plan scope |
| G-CONSUMER-RECONCILE | FlexCRM owner | finite three-file grant | P11.6 manual authority | foreign write separately owned |
| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |
