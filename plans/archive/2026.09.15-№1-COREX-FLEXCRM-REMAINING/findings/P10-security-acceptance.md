# P10 security AP readiness qualification

**Schema:** p10-qualification/1
**Item:** P10.4
**Mode:** readiness qualification — structural/tooling only; actual deployed AP acceptance deferred until post-approval external imports.

Cold-start review: newcomer can locate security import path, AP obligations, producer packet, and validation CLI without external projects.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|:--|:--|:--|:--|:--|:--|:--|
| SECURITY-01 | R39 R40 | AP inventory + cohort/producer binding (readiness) | `evidence:SECURITY-01` | PASS | P10.4 | import external receipts post-approval |
| SECURITY-02 | R40 | Certificate/token/cnf denial obligations documented (readiness) | `evidence:SECURITY-02` | PASS | P10.4 | actual edge receipts after deploy |
| SECURITY-03 | R46 | Key rotation 300s/600s obligations documented (readiness) | `evidence:SECURITY-03` | PASS | P10.4 | rotation envelopes after deploy |
| SECURITY-04 | R39 | Projection/trigger obligations in producer packet (readiness) | `evidence:SECURITY-04` | PASS | P10.4 | trigger/projection receipts after deploy |
| SECURITY-05 | R46 | Revoke/replica/ledger scope in producer packet (readiness) | `evidence:SECURITY-05` | PASS | P10.4 | primary/failover envelopes after deploy |
| SECURITY-06 | R39 R46 | Stage separation: readiness vs effective AP (readiness) | `evidence:SECURITY-06` | PASS | P10.4 | rerun branch after imports |

## Conditions

| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |
|:--|:--|:--|:--|:--|:--|
| AP-01.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-01.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-01.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-01.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-02.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-02.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-02.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-02.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-02.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-03.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-03.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-03.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-03.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-03.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-04.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-04.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-04.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-04.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-04.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-05.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-05.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-05.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-05.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-05.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-13.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-13.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-13.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-13.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-13.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |

## Inputs

| Path | SHA256 | Role |
|:--|:--|:--|
| `findings/P10-intake.md` | `1ecf5e610c657d853cfb0e84591a5307bf32326b6e14321b04659292354acd2c` | P10.1 intake predecessor |
| `findings/P10-central-qualification.md` | `52055d497be53d0b489eb4edf2684cbc9f848c5f12fbb158eb4805495d6104b7` | P10.2 central readiness predecessor |
| `findings/P10-cloud-qualification.md` | `52af710f470cc4b28310126ccc91e6d60ffea9bdf9972a2002ed2880d160d328` | P10.3 cloud readiness predecessor |
| `artifacts/P10-intake/producer-requests.md` | `c730a579121061f7281af25b03ed28ced1e4f03f38a86fbaab0154f91ac56d0a` | producer packet |
| `artifacts/P10-intake/cohort-binding.json` | `52b384c0fcf9886f82bff2b972b105f9bea9e7c1897cbe0f84d6279bff3acc93` | P9 cohort binding |
| `artifacts/P10-external/README.md` | `80e778a863b0a3a387a941a337414f71e061781b1f5bd65cffafe85846812954` | import guide |
| `fixtures/P10-external-evidence/template.json` | `3c13a1e9eb2cddebb9ece48deaee50cc164278619af05424125d0f029b0bc61c` | external schema template |
| `research/P10-effective-design.md` | `3067c20f88fa2e8d00f729fd1c3cf9f01b3e4aeddc314afa1d26966f760a30ac` | effective design contract |
| `decisions/D15-effective-acceptance.md` | `2a069bf51368af81161ab959025b45ebda32fbe3350957910221e602ba5f7f62` | owner decision D15 |

## Evidence

| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| SECURITY-01 | `artifacts/P10-readiness/evidence/security-01.json` | `9aa16a821b54cbcb51351397512ad9e9670b49e9c6e7f7728b8e15257a817733` | source | p10-security-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-security-readiness --case SECURITY-01` | 2026-09-19T03:53:28.291377+00:00 | 2026-09-19T03:53:28.291512+00:00 | PASS |
| SECURITY-02 | `artifacts/P10-readiness/evidence/security-02.json` | `4bfae9ad7680db37f66dfe751745eed9952762e612d691e8b228c493bea3feaf` | source | p10-security-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-security-readiness --case SECURITY-02` | 2026-09-19T03:53:28.292015+00:00 | 2026-09-19T03:53:28.292136+00:00 | PASS |
| SECURITY-03 | `artifacts/P10-readiness/evidence/security-03.json` | `e87579de23d0b2eb20dd78848129e7eb4b3c30d33e977ef98f8456f547bad41a` | source | p10-security-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-security-readiness --case SECURITY-03` | 2026-09-19T03:53:28.292596+00:00 | 2026-09-19T03:53:28.292704+00:00 | PASS |
| SECURITY-04 | `artifacts/P10-readiness/evidence/security-04.json` | `d766906feb70063d6be2acc3f0da17f139f5fa3281cc8aa190ee3a1685d602ec` | source | p10-security-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-security-readiness --case SECURITY-04` | 2026-09-19T03:53:28.293153+00:00 | 2026-09-19T03:53:28.293266+00:00 | PASS |
| SECURITY-05 | `artifacts/P10-readiness/evidence/security-05.json` | `2afd4b1c987ad4bca22db4700524e68bf7047006b83edf054191444256cba90a` | source | p10-security-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-security-readiness --case SECURITY-05` | 2026-09-19T03:53:28.293768+00:00 | 2026-09-19T03:53:28.293886+00:00 | PASS |
| SECURITY-06 | `artifacts/P10-readiness/evidence/security-06.json` | `2c8aed4e3355079452af348ab8ed31b272b26564f01deb6242be1a3fc180a8b1` | source | p10-security-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-security-readiness --case SECURITY-06` | 2026-09-19T03:53:28.294422+00:00 | 2026-09-19T03:53:28.294532+00:00 | PASS |

## Acceptance

| Owner | Receipt | SHA256 | Covered | Result |
|:--|:--|:--|:--|:--|
| P10.4 | `artifacts/P10-readiness/security-receipt-selection.json` | `321b116a707c39e54e81e908bb481024fca4695f347be7890e2e3644e226413f` | readiness-local | PASS |

## Remaining

| Condition | Owner | Exact receipt | Resume |
|:--|:--|:--|:--|
| AP-01..AP-05 + AP-13 executed (29 subconditions) | Product/auth owners | `artifacts/P10-external/<sha>.json` | rerun P10.4 branch after post-approval imports |
| Actual baseline adoption receipt (AP-13 / DI-34) | Product/auth owners | sanitized external import | P10.4 full branch validation |
