# P10 effective intake receipts

**Schema:** p10-qualification/1
**Item:** P10.1

Frozen receipt intake and local verifier preparation. This is not DI/AP acceptance.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|:--|:--|:--|:--|:--|:--|:--|
| INTAKE-01 | R39 R45 R46 | Released locks equal P9.9; candidate-only rejected | `evidence:INTAKE-01` | PASS | P10.1 | refresh on P9 seal drift |
| INTAKE-02 | R46 | 34 DI / 13 AP / B33/B34 originals retained in P1 lock | `evidence:INTAKE-02` | PASS | P10.1 | return to P1 on ownership drift |
| INTAKE-03 | R39 R40 | Inventory binds cohort; pending owners explicit | `evidence:INTAKE-03` | PASS | P10.1 | wait on external receipts |
| INTAKE-04 | R46 | Verifier recomputes digests and assertions | `evidence:INTAKE-04` | PASS | P10.1 | rerun on verifier delta |
| INTAKE-05 | R46 | Refusal fixtures reject stale/leak/owner defects | `evidence:INTAKE-05` | PASS | P10.1 | extend fixtures on new defect class |
| INTAKE-06 | R39 R40 | No external contact; producer packet actionable | `evidence:INTAKE-06` | PASS | P10.1 | reuse packet if unchanged |

## Inputs

| Path | SHA256 | Role |
|:--|:--|:--|
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` | P1 lock |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | P1 AC map |
| `findings/P1-evidence-ledger.md` | `ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87` | P1 ledger |
| `findings/P2-contract-catalogue.md` | `ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573` | P2 catalogue |
| `findings/P2-gate-requests.md` | `77b763002a9509b5ab995046ed838bbcc19f9e6c1786cc6da6a6c994172e5cdd` | P2 gates |
| `findings/P3-adapter-receipts.md` | `b048f8e58669845a684df013c198f4397a5d46cc2d021ff3f93bc1b7b48ede3c` | P3 adapter receipts |
| `findings/P9-cohort-receipt.md` | `c5ae759c8cd30f1404cb6c197194428b42ad4c0d6874f6f154258ba37718deff` | P9 released seal |
| `findings/P9-compatibility-passport.md` | `6ecf707ec8d153d52c8d7f85364ba550b471362aa1b13e98372735d343b65df6` | P9 passport |
| `delivery/remaining-cohort-20260915/public/released-cohort.json` | `f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee` | released vector |
| `research/P10-effective-design.md` | `3067c20f88fa2e8d00f729fd1c3cf9f01b3e4aeddc314afa1d26966f760a30ac` | effective design contract |
| `decisions/D15-effective-acceptance.md` | `2a069bf51368af81161ab959025b45ebda32fbe3350957910221e602ba5f7f62` | owner decision D15 |

## Evidence

| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| INTAKE-01 | `artifacts/P10-intake/evidence/intake-01.json` | `d56f257b0bbcfe3614b047c0a7b540ba9f057b216bea4cf0bce7576f689c25ab` | source | p10-intake-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-intake --case INTAKE-01` | 2026-09-19T03:53:28.052897+00:00 | 2026-09-19T03:53:28.052950+00:00 | PASS |
| INTAKE-02 | `artifacts/P10-intake/evidence/intake-02.json` | `4af3427f540ee484fcd7b33826119d23a14dee503cbc87f3e5cace6f2316db38` | source | p10-intake-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-intake --case INTAKE-02` | 2026-09-19T03:53:28.053615+00:00 | 2026-09-19T03:53:28.053654+00:00 | PASS |
| INTAKE-03 | `artifacts/P10-intake/evidence/intake-03.json` | `53edd6933618dc74cca66f691f88eca9f125996a9e6b73c23c23d6b8b071986d` | source | p10-intake-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-intake --case INTAKE-03` | 2026-09-19T03:53:28.054244+00:00 | 2026-09-19T03:53:28.054280+00:00 | PASS |
| INTAKE-04 | `artifacts/P10-intake/evidence/intake-04.json` | `648d25b291786206fc9afdd41429f19eebd869754fb105350d9908f1e926c2cc` | source | p10-intake-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-intake --case INTAKE-04` | 2026-09-19T03:53:28.054915+00:00 | 2026-09-19T03:53:28.054953+00:00 | PASS |
| INTAKE-05 | `artifacts/P10-intake/evidence/intake-05.json` | `ddbb801b74fcf63903a468b07dfc21792c14985bdf5094df338c8051096836dc` | source | p10-intake-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-intake --case INTAKE-05` | 2026-09-19T03:53:28.055528+00:00 | 2026-09-19T03:53:28.055564+00:00 | PASS |
| INTAKE-06 | `artifacts/P10-intake/evidence/intake-06.json` | `783e0f3772191a832cd595c0583ea66cf5d03b71c1e064bde625cfee58e4f7ed` | source | p10-intake-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-intake --case INTAKE-06` | 2026-09-19T03:53:28.056095+00:00 | 2026-09-19T03:53:28.056130+00:00 | PASS |

## Acceptance

| Owner | Receipt | SHA256 | Covered | Result |
|:--|:--|:--|:--|:--|
| P10.1 | `artifacts/P10-intake/receipt-selection.json` | `84f7cdb0dde45ed98ac6aa6a125c020cba9e4e9278045008e54de58ad7f16749` | intake-local | PASS |

## Remaining

| Condition | Owner | Exact receipt | Resume |
|:--|:--|:--|:--|
| DI-01..DI-34 effective | Central/cloud owners | `artifacts/P10-external/<sha>.json` | P10.2/P10.3 |
| AP-01..AP-13 executed | Product/auth owners | deployed security/race/FE receipts | P10.4..P10.7 |
| B33 ops selected | Control-plane owner | provider ops sanitized imports | P10.8 |
