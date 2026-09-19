# P10 race/restore AP readiness qualification

**Schema:** p10-qualification/1
**Item:** P10.5
**Mode:** readiness qualification — structural/tooling only; actual deployed AP acceptance deferred until post-approval external imports.

Cold-start review: newcomer can locate race/restore import path, AP obligations, producer packet, and validation CLI without external projects.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|:--|:--|:--|:--|:--|:--|:--|
| RACES-01 | R39 R40 | AP-06 inventory + cohort/producer binding (readiness) | `evidence:RACES-01` | PASS | P10.5 | import external receipts post-approval |
| RACES-02 | R40 | Final INSERT/UPDATE pause obligations documented (readiness) | `evidence:RACES-02` | PASS | P10.5 | actual race receipts after deploy |
| RACES-03 | R46 | Reverse lock order/unrelated B in producer packet (readiness) | `evidence:RACES-03` | PASS | P10.5 | lock-order envelopes after deploy |
| RACES-04 | R39 | Old recaller/late issuance path obligations (readiness) | `evidence:RACES-04` | PASS | P10.5 | entrance denial receipts after deploy |
| RACES-05 | R46 | Live lease+G/E and no-route inventory (readiness) | `evidence:RACES-05` | PASS | P10.5 | all-entry receipts after deploy |
| RACES-06 | R39 R46 | Nonrollback restore + witness obligations (readiness) | `evidence:RACES-06` | PASS | P10.5 | rerun branch after imports |

## Conditions

| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |
|:--|:--|:--|:--|:--|:--|
| AP-06.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-06.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-06.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-06.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-06.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-08.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-08.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-08.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-08.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-08.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-08.6 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-09.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-09.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-09.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-09.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-09.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-10.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-10.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-10.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-10.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-10.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |

## Inputs

| Path | SHA256 | Role |
|:--|:--|:--|
| `findings/P10-intake.md` | `1ecf5e610c657d853cfb0e84591a5307bf32326b6e14321b04659292354acd2c` | P10.1 intake predecessor |
| `findings/P10-central-qualification.md` | `52055d497be53d0b489eb4edf2684cbc9f848c5f12fbb158eb4805495d6104b7` | P10.2 central readiness predecessor |
| `findings/P10-cloud-qualification.md` | `52af710f470cc4b28310126ccc91e6d60ffea9bdf9972a2002ed2880d160d328` | P10.3 cloud readiness predecessor |
| `findings/P10-security-acceptance.md` | `92df804d2059e1db0a7088811c4968d84b35b4aab61eddc68d22cbf157e58cf7` | P10.4 security readiness predecessor |
| `artifacts/P10-intake/producer-requests.md` | `c730a579121061f7281af25b03ed28ced1e4f03f38a86fbaab0154f91ac56d0a` | producer packet |
| `artifacts/P10-intake/cohort-binding.json` | `52b384c0fcf9886f82bff2b972b105f9bea9e7c1897cbe0f84d6279bff3acc93` | P9 cohort binding |
| `artifacts/P10-external/README.md` | `80e778a863b0a3a387a941a337414f71e061781b1f5bd65cffafe85846812954` | import guide |
| `fixtures/P10-external-evidence/template.json` | `3c13a1e9eb2cddebb9ece48deaee50cc164278619af05424125d0f029b0bc61c` | external schema template |
| `research/P10-effective-design.md` | `3067c20f88fa2e8d00f729fd1c3cf9f01b3e4aeddc314afa1d26966f760a30ac` | effective design contract |
| `decisions/D15-effective-acceptance.md` | `2a069bf51368af81161ab959025b45ebda32fbe3350957910221e602ba5f7f62` | owner decision D15 |

## Evidence

| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| RACES-01 | `artifacts/P10-readiness/evidence/races-01.json` | `c49fbf02e4d1624f039396db2049cc560026ef6eb81b453fe7c53e12edc04392` | source | p10-race-restore-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-race-restore-readiness --case RACES-01` | 2026-09-19T03:53:28.371238+00:00 | 2026-09-19T03:53:28.371374+00:00 | PASS |
| RACES-02 | `artifacts/P10-readiness/evidence/races-02.json` | `4353055d955ee93726c935cd19eb96311d86fe0193864861b09f9266a4b1cf19` | source | p10-race-restore-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-race-restore-readiness --case RACES-02` | 2026-09-19T03:53:28.371877+00:00 | 2026-09-19T03:53:28.371989+00:00 | PASS |
| RACES-03 | `artifacts/P10-readiness/evidence/races-03.json` | `325e04eb1ce82e9eb8a01a12125f9f2102a663155e0ccb7302d4b06b2a3faa0d` | source | p10-race-restore-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-race-restore-readiness --case RACES-03` | 2026-09-19T03:53:28.372437+00:00 | 2026-09-19T03:53:28.372545+00:00 | PASS |
| RACES-04 | `artifacts/P10-readiness/evidence/races-04.json` | `e96d57ab70bdef49cd7b45e93ba82d5d32f83bc69f64ed31d2e392625d72460b` | source | p10-race-restore-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-race-restore-readiness --case RACES-04` | 2026-09-19T03:53:28.373018+00:00 | 2026-09-19T03:53:28.373130+00:00 | PASS |
| RACES-05 | `artifacts/P10-readiness/evidence/races-05.json` | `253bb1b604e60ac73e91541c90193863bcf2a7ae897c6921cc58280172186244` | source | p10-race-restore-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-race-restore-readiness --case RACES-05` | 2026-09-19T03:53:28.373635+00:00 | 2026-09-19T03:53:28.373749+00:00 | PASS |
| RACES-06 | `artifacts/P10-readiness/evidence/races-06.json` | `d7547d9842788e6aafac3ce3cb7daaf6379b80704337458f7927a34c41f6ebbb` | source | p10-race-restore-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-race-restore-readiness --case RACES-06` | 2026-09-19T03:53:28.374278+00:00 | 2026-09-19T03:53:28.374389+00:00 | PASS |

## Acceptance

| Owner | Receipt | SHA256 | Covered | Result |
|:--|:--|:--|:--|:--|
| P10.5 | `artifacts/P10-readiness/race-restore-receipt-selection.json` | `f6d75627108c71478c479df68178bc0437b219143bcac077b3c3a8b873085fff` | readiness-local | PASS |

## Remaining

| Condition | Owner | Exact receipt | Resume |
|:--|:--|:--|:--|
| AP-06/08/09/10 executed (21 subconditions) | Product/auth owners | `artifacts/P10-external/<sha>.json` | rerun P10.5 branch after post-approval imports |
| Independent witness outside rollback set | CoreX tenancy maintainer | sanitized external import | P10.5 full branch validation |
