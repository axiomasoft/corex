# P11 final reconciliation

p11-reconciliation-report/1
**Item:** P11.7

Final cross-repo seal of consumer dependency acceptance and app-only continuation.

## Cases

| ID | Result | Expected | Actual | Evidence |
|:--|:--|:--|:--|:--|
| SEAL-01 | PASS | consumer result matches packet | commit=929a7c3f7994edc4 | SEAL-01 |
| SEAL-02 | PASS | 52R/365/selected atoms complete | selected=146;missing=0 | SEAL-02 |
| SEAL-03 | PASS | product residuals retained | residuals=3 | SEAL-03 |
| SEAL-04 | PASS | app-only handoff present | handoff=41dbd4f7017ebe2e | SEAL-04 |
| SEAL-05 | PASS | inputs fresh after write | cohort=f44c0c6fe757aeb8 | SEAL-05 |
| SEAL-06 | PASS | successor boundary explicit | PLAN-CLOSE-CI deferred | SEAL-06 |

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
| `findings/P11-reconciliation-packet.md` | `f80bed8146910dd1605a62ec3012f678b2077256909fbc9258de2c24e652774c` |
| `artifacts/P11-packet/operations.md` | `da86cb5e35b3c2f11d936c4938178ebc344e32cbefe618694081ce7191ef274d` |
| `artifacts/P11-packet/acceptance.md` | `aa7852bb3d467df1ae6bde6d539d05b8662477a7e6fba143972dd289e02edc2d` |
| `artifacts/P11-packet/progress-addition.md` | `f3430f7b3c49543693797d4a8bacfb4fd00339cd94fc05922663bc991d993c77` |
| `artifacts/P11-packet/handoff.md` | `41dbd4f7017ebe2e466cd1e9747585a7c09121a78dff29134e2add15a882ffe9` |
| `artifacts/P11-packet/simulation.json` | `71d75fe9055b6cb461df0ea8b31312a6ecbf47266d193e8143999957f0baaae9` |
| `artifacts/P11-write/grant.json` | `a790c6d80c5af669a70c1605536609b0d2e116201959f0d12dfc5f0c9050ce76` |
| `findings/P11-consumer-write.md` | `c3033e3a302332ab6d4dabd432e18344dd4e35655968437fd6009698afd9741d` |
| `artifacts/P11-write/consumer-result.md` | `7be02c04794c21c643e484d2bee71b936a398a9cdc1718783035272c43dc5409` |

## Evidence

| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| SEAL-01 | flexcrm | 929a7c3f7994edc47f3d2bd75f7655cc60da9e4e | execution | owner-policy/p11-seal-lab | consumer-result | `artifacts/P11-evidence/seal-01.json` | `1e65c2d21ecbac76be58594ad6b766e65ffb4dbe5e64b9afd164f57465a2ea69` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-seal --case SEAL-01` | python=3.12 | 2026-09-19T05:37:52.957277+00:00 | 2026-09-19T05:37:52.957664+00:00 | 0 | PASS |
| SEAL-02 | flexcrm | 929a7c3f7994edc47f3d2bd75f7655cc60da9e4e | execution | owner-policy/p11-seal-lab | dependency-join | `artifacts/P11-evidence/seal-02.json` | `b4113f9964d80db3216f20221d6e0757565a42c67be5c77eb1fe44156e00cbcd` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-seal --case SEAL-02` | python=3.12 | 2026-09-19T05:37:52.958047+00:00 | 2026-09-19T05:37:52.958405+00:00 | 0 | PASS |
| SEAL-03 | flexcrm | 929a7c3f7994edc47f3d2bd75f7655cc60da9e4e | execution | owner-policy/p11-seal-lab | residual-owners | `artifacts/P11-evidence/seal-03.json` | `0196a7d6882c5e87f9de63fbba84c8f8a12da26b752f94c4ef60b73bcef574f4` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-seal --case SEAL-03` | python=3.12 | 2026-09-19T05:37:52.958654+00:00 | 2026-09-19T05:37:52.958990+00:00 | 0 | PASS |
| SEAL-04 | flexcrm | 929a7c3f7994edc47f3d2bd75f7655cc60da9e4e | execution | owner-policy/p11-seal-lab | app-only-handoff | `artifacts/P11-evidence/seal-04.json` | `773d231f977bb9c7686ff1e5cf136f3a7ee68cde6595838b289b84bb0cea70b2` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-seal --case SEAL-04` | python=3.12 | 2026-09-19T05:37:52.959235+00:00 | 2026-09-19T05:37:52.959574+00:00 | 0 | PASS |
| SEAL-05 | flexcrm | 929a7c3f7994edc47f3d2bd75f7655cc60da9e4e | execution | owner-policy/p11-seal-lab | freshness-recheck | `artifacts/P11-evidence/seal-05.json` | `bbac94a8b0787e28768a50be13bc5811b2d31aaf5ecfc36b79eebc5dd811ba4a` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-seal --case SEAL-05` | python=3.12 | 2026-09-19T05:37:52.959831+00:00 | 2026-09-19T05:37:52.960178+00:00 | 0 | PASS |
| SEAL-06 | flexcrm | 929a7c3f7994edc47f3d2bd75f7655cc60da9e4e | execution | owner-policy/p11-seal-lab | successor-boundary | `artifacts/P11-evidence/seal-06.json` | `5bb283887985a8fa675918ae9d29da5e91118760e839adace0c14c213c206f86` | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/reconcile-consumer.py --prepare-seal --case SEAL-06` | python=3.12 | 2026-09-19T05:37:52.960478+00:00 | 2026-09-19T05:37:52.960809+00:00 | 0 | PASS |

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
| Consumer commit | `929a7c3f7994edc47f3d2bd75f7655cc60da9e4e` |
| Packet digest | `f2aeaab0494f636131241fad1a3f526571ef11b408c60d5e66888d12d99b8999` |

## Sealed inputs

| Path | SHA256 |
|:--|:--|
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` |
| `findings/P1-evidence-ledger.md` | `ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87` |
| `findings/P1-downstream-gaps.md` | `6284a6d649f3f4bbbe5468dfb502e7915fe90b38ddf7f9a7441995baf0b11e3a` |
| `findings/P11-consumer-write.md` | `c3033e3a302332ab6d4dabd432e18344dd4e35655968437fd6009698afd9741d` |
| `artifacts/P11-write/consumer-result.md` | `7be02c04794c21c643e484d2bee71b936a398a9cdc1718783035272c43dc5409` |

## Consumer result

| Repo | Commit | Path | SHA256 | Grant packet | Outcome |
|:--|:--|:--|:--|:--|:--|
| flexcrm | `929a7c3f7994edc47f3d2bd75f7655cc60da9e4e` | `plans/FLEX-2/findings/P6.5-corex-remaining-dependency-acceptance.md` | `aa7852bb3d467df1ae6bde6d539d05b8662477a7e6fba143972dd289e02edc2d` | `f2aeaab0494f6361…` | reconciled |
| flexcrm | `929a7c3f7994edc47f3d2bd75f7655cc60da9e4e` | `plans/FLEX-2/findings/P6.5-delegate-progress.md` | `6ebbc048cd9660f56f3280772466c3c78d79fdf25da1e96a4bba653ca5a127f1` | `f2aeaab0494f6361…` | reconciled |
| flexcrm | `929a7c3f7994edc47f3d2bd75f7655cc60da9e4e` | `plans/FLEX-2/handoff.md` | `41dbd4f7017ebe2e466cd1e9747585a7c09121a78dff29134e2add15a882ffe9` | `f2aeaab0494f6361…` | reconciled |

## Dependency summary

| Field | Value |
|:--|:--|
| Requirements rows | 52R |
| Original parents | 365 |
| Selected dependency atoms | 146 |
| Required CoreX dependency gaps | 0 |
| Accepting actor | FlexCRM consumer owner / crm-consumer-tests-20260919 |
| G-CONSUMER-RECONCILE | exercised at `f2aeaab0494f6361…` |
| P10 effective mode | `readiness` (`required_gaps=169`) |

## App continuation

> Для frozen requirements-lock PLAN1 все CoreX-зависимости приняты на перечисленных immutable pins и закрытых acceptance receipts. Дальнейшая разработка выполняется только в FlexCRM. Возврат за доработками CoreX допускается лишь для будущих отдельно согласованных требований.

**Permitted successor:** `PLAN-CLOSE-CI` (deferred per D44; no automatic launch).

**Remaining app/product work:**
- CRM product milestones (P6.5/P6.17/P6.18) — FlexCRM owner
- Documents/calendar/frontend activation — FlexCRM owner
- Aggregate FLEX-2 milestone closure — FlexCRM program owner
- App DB migration/enable, production, publication, populated cutover, frontend activation remain outside dependency acceptance.

**Explicit non-grants:** Does not close FLEX-2 P6.5/P6.17/P6.18; does not spawn automatic successors; does not treat readiness-only P10 as effective seal; phase audit requires separate owner-bound admission.

## Acceptance

| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| final-reconciliation | R47 | — | p11-reconciliation-report/1 | write+ledger | cross-repo seal | P9 vector bound | current | observed PASS | FlexCRM consumer owner / crm-consumer-tests-20260919 | G-CONSUMER-RECONCILE exercised | PASS |

## Remaining

| Atom | Original residual owner | Missing receipt | Resume | Product boundary |
|:--|:--|:--|:--|:--|
| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |
| FLEX-2 product milestones | FlexCRM program owner | P6.5/P6.17/P6.18 closure | separate acceptance | foreign product boundary |
| PLAN-CLOSE-CI | CoreX release owner | D44 CI/workflows/package-graph gate | terminal after P11.7 | deferred remote ops |
