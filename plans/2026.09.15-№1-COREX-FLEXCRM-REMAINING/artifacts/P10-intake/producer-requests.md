# P10 external producer requests

Local preparation only. No message or write was sent to external operators.

## Как начать (cold start)

1. Прочитайте `artifacts/P10-external/README.md` — путь импорта и ограничения.
2. Скопируйте `fixtures/P10-external-evidence/template.json` и заполните поля под ваш deployed central/cloud.
3. Проверьте пакет локально: `python3 scripts/effective-acceptance.py --validate-import <path>.json`.
4. Положите санитизированные файлы в `artifacts/P10-external/<SHA256>.json` и `.log` (имя = SHA256 содержимого, lowercase hex).
5. Перезапустите `task:plan-exec … P10.2` (или P10.3/P10.4/P10.5/P10.6/P10.7) — квалификация только из импортов, без remote contact из CoreX.

## Central identity (P10.2)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Central IdP AS/resource/PKI | deployed central release bound to P9 vector | produce sanitized `p10-external-evidence/1` for DI-01..20 and DI-34 | revert to prior release tag | Central FLEX identity owner |
| Ledger/events | primary/failover read paths | include revoke-before-read and retry envelopes | disable new registrations | Central FLEX identity owner |

## Cloud tenancy (P10.3)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Cloud guards/entries | complete deployed route inventory | produce DI-21..30 + DI-33 evidence | restore previous deployment | FlexCRM auth owner |
| Timing/pair inventory | scheduler + pair witness store | include pair read-start ledger | pause scheduler | CoreX tenancy maintainer |

## Security AP (P10.4)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Membership/user denies | central+cloud deployed release | produce AP-01..05 + AP-13 executed evidence | revert to prior auth baseline | Product/auth owners |
| Key rotation / ledger | AS/resource edges + primary/failover reads | include 300s/600s rotation and revoke-before-read envelopes | disable compromised keys | Product/auth owners |
| Baseline adoption | published baseline→proposed DTOs | AP-13 sealed source carriers linked to DI-34 | restore prior major | Product/auth owners |

## Race/restore AP (P10.5)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Final-write races | deployed central+cloud release | produce AP-06/08/09 executed evidence (paused writes, cookie denial, all-entry live admission) | revert auth baseline | Product/auth owners |
| Nonrollback restore | central+tenant backups + witness outside rollback set | produce AP-10 executed evidence; R/G/E/F monotonic | cannot replay stale backup auth | Product/auth owners |
| Pair witness | allocated external witness store | include independent witness not in complete rollback set | witness retention policy | CoreX tenancy maintainer |

## FE/elapsed AP (P10.6)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Elapsed domains + FE-60 | deployed central+cloud release | produce DI-31 + AP-07/11 executed evidence (t0+45/tr+60, 5s/10s/60s bounds) | revert auth baseline | Product/auth owners |
| Clock/platform inventory | service/boot/process domains + capacity | include suspend/restart/cross-host/store-loss negatives with raw start/end/units | conservative deny on domain loss | CoreX tenancy maintainer |
| Cleanup priority | scheduler + physical cleanup paths | overbudget breach reported separately while auth denied | pause scheduler | FlexCRM auth owner |

## Monthly p99.9 / pair capacity (P10.7)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Pair read-start ledger | scheduler + pair witness store | produce DI-32 + AP-12 executed evidence (every pair <=30s incl lock/backlog) | pause scheduler | CoreX tenancy maintainer |
| Closed monthly census | deployed central+cloud release | full committed-event ledger count/IDs reconciled to observation census | revert auth baseline | Product/auth owners |
| p99.9 upper latency | measurement window + per-account tails | nearest-rank k=ceil(0.999*N), missing as +infinity, <=10s bound | cannot accept partial month | FlexCRM auth owner |

## Ops/provider (P10.8)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|
| Selected B33 control-plane | account provision + object store + backup | sanitized ops receipts OPS-01..10 | compensation runbook | Flex central control-plane owner |

## Resume

Repeated unchanged discovery reuses this packet. Relevant input change invalidates affected receipts only.
