# P10 B33 provider/control-plane ops readiness qualification

**Schema:** p10-qualification/1
**Item:** P10.8
**Mode:** readiness qualification — structural/tooling only; actual deployed B33 ops acceptance deferred until post-approval external imports.

Cold-start review: newcomer can locate ops/provider import path, B33 obligations, producer packet, and validation CLI without external projects.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|:--|:--|:--|:--|:--|:--|:--|
| OPS-01 | R39 | B33 AC inventory + cohort/producer binding (readiness) | `evidence:OPS-01` | PASS | P10.8 | import external receipts post-approval |
| OPS-02 | R39 | Account provision/TLS/membership obligations documented (readiness) | `evidence:OPS-02` | PASS | P10.8 | provision receipts after deploy |
| OPS-03 | R39 | Seed compensation + transient resume<=15min (readiness) | `evidence:OPS-03` | PASS | P10.8 | compensation runbook receipts after deploy |
| OPS-04 | R39 | RU object provider IAM/limits/quota obligations (readiness) | `evidence:OPS-04` | PASS | P10.8 | provider SDK receipts after deploy |
| OPS-05 | R39 | Durable vs ephemeral storage binding documented (readiness) | `evidence:OPS-05` | PASS | P10.8 | consumer storage receipts after deploy |
| OPS-06 | R39 | ACME DNS-01 + cell operational alerts documented (readiness) | `evidence:OPS-06` | PASS | P10.8 | domain/custody receipts after deploy |
| OPS-07 | R39 | Export-before-drop manifest/restore obligations (readiness) | `evidence:OPS-07` | PASS | P10.8 | export/archive receipts after deploy |
| OPS-08 | R39 | Backup/restore/move<=50GB obligations documented (readiness) | `evidence:OPS-08` | PASS | P10.8 | backup/move receipts after deploy |
| OPS-09 | R39 | Fleet migration 200DB batch obligations documented (readiness) | `evidence:OPS-09` | PASS | P10.8 | fleet migration receipts after deploy |
| OPS-10 | R39 | Control-plane IAM/index approval obligations (readiness) | `evidence:OPS-10` | PASS | P10.8 | staff/IAM receipts after deploy |

## Inputs

| Path | SHA256 | Role |
|:--|:--|:--|
| `findings/P10-intake.md` | `1ecf5e610c657d853cfb0e84591a5307bf32326b6e14321b04659292354acd2c` | P10.1 intake predecessor |
| `artifacts/P10-intake/producer-requests.md` | `c730a579121061f7281af25b03ed28ced1e4f03f38a86fbaab0154f91ac56d0a` | producer packet |
| `artifacts/P10-intake/cohort-binding.json` | `52b384c0fcf9886f82bff2b972b105f9bea9e7c1897cbe0f84d6279bff3acc93` | P9 cohort binding |
| `artifacts/P10-external/README.md` | `80e778a863b0a3a387a941a337414f71e061781b1f5bd65cffafe85846812954` | import guide |
| `fixtures/P10-external-evidence/template.json` | `3c13a1e9eb2cddebb9ece48deaee50cc164278619af05424125d0f029b0bc61c` | external schema template |
| `research/P10-effective-design.md` | `3067c20f88fa2e8d00f729fd1c3cf9f01b3e4aeddc314afa1d26966f760a30ac` | effective design contract |
| `brief/sources/b33.md` | `be6366fc074821e4d5cfd58e3b5c953875a45cce8f948da6f93a57b419d15a27` | B33 source obligations |
| `decisions/D15-effective-acceptance.md` | `2a069bf51368af81161ab959025b45ebda32fbe3350957910221e602ba5f7f62` | owner decision D15 |

## Evidence

| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| OPS-01 | `artifacts/P10-readiness/evidence/ops-01.json` | `dede6285a0e6ba76283e0b602db33f3c932ce39cabe1bfc2e75fb41ebcaf604e` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-01` | 2026-09-19T03:53:28.621703+00:00 | 2026-09-19T03:53:28.621841+00:00 | PASS |
| OPS-02 | `artifacts/P10-readiness/evidence/ops-02.json` | `df5283a902f8f55ebfe511ba5bb8a8e6a2ea12d73219798cb8632805cb1be75b` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-02` | 2026-09-19T03:53:28.622326+00:00 | 2026-09-19T03:53:28.622437+00:00 | PASS |
| OPS-03 | `artifacts/P10-readiness/evidence/ops-03.json` | `eed3dcb992c4407671320a416a482e5e8cf5460bf2c17d9e01d337986390e400` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-03` | 2026-09-19T03:53:28.622928+00:00 | 2026-09-19T03:53:28.623045+00:00 | PASS |
| OPS-04 | `artifacts/P10-readiness/evidence/ops-04.json` | `8369cf052cca236556bc01ad2ec6da188711797742970e7846171e17035e860b` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-04` | 2026-09-19T03:53:28.623523+00:00 | 2026-09-19T03:53:28.623636+00:00 | PASS |
| OPS-05 | `artifacts/P10-readiness/evidence/ops-05.json` | `e7c173666afc5c4cdd3b8ff9edbe7758e2064c677a578961671da85c92ed78e5` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-05` | 2026-09-19T03:53:28.624072+00:00 | 2026-09-19T03:53:28.624183+00:00 | PASS |
| OPS-06 | `artifacts/P10-readiness/evidence/ops-06.json` | `10450f78429c95c3c617c2e3b97600e188feb49c8f1749512260904e2475a7de` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-06` | 2026-09-19T03:53:28.624715+00:00 | 2026-09-19T03:53:28.624832+00:00 | PASS |
| OPS-07 | `artifacts/P10-readiness/evidence/ops-07.json` | `02fe618611d3e8a06dee8956ce81e5cfbb01579706eb53d431ab97e84fbb841a` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-07` | 2026-09-19T03:53:28.625379+00:00 | 2026-09-19T03:53:28.625489+00:00 | PASS |
| OPS-08 | `artifacts/P10-readiness/evidence/ops-08.json` | `b5ad173bd71622198e0f82c28b600e30681ac3341132381ec5afa30837342a7d` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-08` | 2026-09-19T03:53:28.625962+00:00 | 2026-09-19T03:53:28.626076+00:00 | PASS |
| OPS-09 | `artifacts/P10-readiness/evidence/ops-09.json` | `12927bba3e580cbe91ae0498a479c9e6200388141a57b33e922dbee63176ee71` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-09` | 2026-09-19T03:53:28.626565+00:00 | 2026-09-19T03:53:28.626684+00:00 | PASS |
| OPS-10 | `artifacts/P10-readiness/evidence/ops-10.json` | `56b46a5ecb27daefe418c62232d80bc0edd3fe6c6301ddc8d3dbbeb1d42b9390` | source | p10-ops-readiness-lab | f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee | `/usr/bin/python3 -B /home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/effective-acceptance.py --prepare-ops-readiness --case OPS-10` | 2026-09-19T03:53:28.627293+00:00 | 2026-09-19T03:53:28.627425+00:00 | PASS |

## Acceptance

| Owner | Receipt | SHA256 | Covered | Result |
|:--|:--|:--|:--|:--|
| P10.8 | `artifacts/P10-readiness/ops-receipt-selection.json` | `8734e5fa894c6e3795ef9fa7c927bd80390fcfbdb6fa3b3150c1a47e91920690` | readiness-local | PASS |

## Remaining

| Condition | Owner | Exact receipt | Resume |
|:--|:--|:--|:--|
| B33 AC-1..15 actual provider/control-plane ops (15 parents) | Flex central control-plane owner | `artifacts/P10-external/<sha>.json` | rerun P10.8 branch after post-approval imports |
| Sanitized ops raw logs (provision/backup/fleet/IAM) | Flex central control-plane owner | sanitized external import | P10.8 full branch validation |
