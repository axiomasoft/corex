# P10 monthly p99.9 / pair capacity readiness qualification

**Schema:** p10-qualification/1
**Item:** P10.7
**Mode:** readiness qualification — structural/tooling only; actual deployed DI/AP acceptance deferred until post-approval external imports.

Cold-start review: newcomer can locate monthly/pair import path, DI/AP obligations, producer packet, and validation CLI without external projects.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|:--|:--|:--|:--|:--|:--|:--|
| MONTH-01 | R39 R40 | DI-32/AP-12 pair inventory + read-start <=30s (readiness) | `evidence:MONTH-01` | PASS | P10.7 | import external receipts post-approval |
| MONTH-02 | R40 | Cold auth/JWKS/TLS/network/DB measured on deployment (readiness) | `evidence:MONTH-02` | PASS | P10.7 | cold-path envelopes after deploy |
| MONTH-03 | R46 | Closed monthly committed-event census reconciled (readiness) | `evidence:MONTH-03` | PASS | P10.7 | full ledger receipts after closed month |
| MONTH-04 | R39 | Clock uncertainty in latency upper bounds (readiness) | `evidence:MONTH-04` | PASS | P10.7 | timestamp error envelopes after deploy |
| MONTH-05 | R46 | p99.9 nearest-rank <=10s with per-account tails (readiness) | `evidence:MONTH-05` | PASS | P10.7 | monthly percentile receipts after closed month |
| MONTH-06 | R39 R46 | Empty/unfinished month fail-closed + waiting-external (readiness) | `evidence:MONTH-06` | PASS | P10.7 | rerun branch after imports |
| MONTH-07 | R46 | Pair capacity budgets independent of percentile (readiness) | `evidence:MONTH-07` | PASS | P10.7 | pair interval receipts after deploy |

## Conditions

| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |
|:--|:--|:--|:--|:--|:--|
| DI-32.1 | accepted+effective (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| DI-32.2 | accepted+effective (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| DI-32.3 | accepted+effective (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-12.1 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-12.2 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-12.3 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-12.4 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-12.5 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-12.6 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |
| AP-12.7 | accepted+effective+executed (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |

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
| MONTH-01 | `artifacts/P10-readiness/evidence/month-01.json` | `04568e09700930adbf888c5ebfc0fc57c16d9fd0b2ab2703b202f97484732b08` | source | p10-monthly-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-monthly-readiness --case MONTH-01` | 2026-09-19T03:53:28.540714+00:00 | 2026-09-19T03:53:28.540847+00:00 | PASS |
| MONTH-02 | `artifacts/P10-readiness/evidence/month-02.json` | `834d697ced00ad7c2de3043daa39609c63c88d6eedcff9559bdfe7789ced848d` | source | p10-monthly-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-monthly-readiness --case MONTH-02` | 2026-09-19T03:53:28.541322+00:00 | 2026-09-19T03:53:28.541435+00:00 | PASS |
| MONTH-03 | `artifacts/P10-readiness/evidence/month-03.json` | `4cb7a655d7b13572851d238b137e58b9f76ecb0e1693a5eb0cf2379964f75ecb` | source | p10-monthly-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-monthly-readiness --case MONTH-03` | 2026-09-19T03:53:28.541923+00:00 | 2026-09-19T03:53:28.542028+00:00 | PASS |
| MONTH-04 | `artifacts/P10-readiness/evidence/month-04.json` | `d1f7703fdcb2212470d6df6a1dbbb8ef004e24a163bac104c617e547c233d418` | source | p10-monthly-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-monthly-readiness --case MONTH-04` | 2026-09-19T03:53:28.542500+00:00 | 2026-09-19T03:53:28.542605+00:00 | PASS |
| MONTH-05 | `artifacts/P10-readiness/evidence/month-05.json` | `8b64dd6a65f8c48c247edbfd30874e70905b016f3597ac21fa039bb1ceed3818` | source | p10-monthly-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-monthly-readiness --case MONTH-05` | 2026-09-19T03:53:28.543078+00:00 | 2026-09-19T03:53:28.543389+00:00 | PASS |
| MONTH-06 | `artifacts/P10-readiness/evidence/month-06.json` | `792842a2b55a4fd71b2555cc56c196df65d8256e412c171b95804728ba843e60` | source | p10-monthly-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-monthly-readiness --case MONTH-06` | 2026-09-19T03:53:28.543967+00:00 | 2026-09-19T03:53:28.544088+00:00 | PASS |
| MONTH-07 | `artifacts/P10-readiness/evidence/month-07.json` | `3ad7b5719650c6f92e62e68d86608f8af4e0c8f1f72b62e23946c7b59b8edb53` | source | p10-monthly-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-monthly-readiness --case MONTH-07` | 2026-09-19T03:53:28.544525+00:00 | 2026-09-19T03:53:28.544634+00:00 | PASS |

## Acceptance

| Owner | Receipt | SHA256 | Covered | Result |
|:--|:--|:--|:--|:--|
| P10.7 | `artifacts/P10-readiness/monthly-receipt-selection.json` | `4ea69c4d584ac92709b4973f2468521c21a6300c57541d84b9d130d47a5df98f` | readiness-local | PASS |

## Remaining

| Condition | Owner | Exact receipt | Resume |
|:--|:--|:--|:--|
| DI-32 + AP-12 executed (10 subconditions) | Product/auth owners | `artifacts/P10-external/<sha>.json` | rerun P10.7 branch after post-approval imports |
| Closed monthly census + pair interval raw receipts | CoreX tenancy maintainer | sanitized external import | P10.7 full branch validation |
