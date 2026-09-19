# P11 consumer reconciliation packet

**Schema:** p11-consumer-reconciliation/1
**Item:** P11.5

Finite three-file foreign write packet for repo:flexcrm FLEX-2 dependency bookkeeping.

## Operations

| ID | Repo | Path | Kind | Expected old SHA256 | New SHA256 | Payload | Recovery |
|:--|:--|:--|:--|:--|:--|:--|:--|
| OP-01 | flexcrm | `plans/FLEX-2/findings/P6.5-corex-remaining-dependency-acceptance.md` | create | `absent` | `aa7852bb3d467df1ae6bde6d539d05b8662477a7e6fba143972dd289e02edc2d` | artifacts/P11-packet/acceptance.md | no-op if identical bytes exist; reject on collision |
| OP-02 | flexcrm | `plans/FLEX-2/findings/P6.5-delegate-progress.md` | append | `a4e71cd130f0ffe741f64ae02e6aefa7c588b80579fb4fcb9a397db74d1d3fca` | `6ebbc048cd9660f56f3280772466c3c78d79fdf25da1e96a4bba653ca5a127f1` | artifacts/P11-packet/progress-addition.md | reconcile append boundary before retry |
| OP-03 | flexcrm | `plans/FLEX-2/handoff.md` | replace | `1d6805f348b348b89ad007e757df4b4976fb896a14cafe799f33a886562ee656` | `41dbd4f7017ebe2e466cd1e9747585a7c09121a78dff29134e2add15a882ffe9` | artifacts/P11-packet/handoff.md | query current SHA before replace; no blind overwrite |

## Frozen inputs

| Repo | Path | SHA256 |
|:--|:--|:--|
| flexcrm | `flexcrm@baf1423c` | `baf1423c3f711a03740fc3e9ba9f615e2920e8d3` |
| flexcrm | `plans/FLEX-2/findings/P6.5-corex-remaining-dependency-acceptance.md` | `absent` |
| flexcrm | `plans/FLEX-2/findings/P6.5-delegate-progress.md` | `a4e71cd130f0ffe741f64ae02e6aefa7c588b80579fb4fcb9a397db74d1d3fca` |
| flexcrm | `plans/FLEX-2/handoff.md` | `1d6805f348b348b89ad007e757df4b4976fb896a14cafe799f33a886562ee656` |

## Authority

| Gate | Branch | Finite scope | Packet SHA256 | Owner | Authenticated grant reference |
|:--|:--|:--|:--|:--|:--|
| G-CONSUMER-RECONCILE | `consumer-dependency-reconciliation` | `p11-consumer-reconciliation/1:repo-flexcrm:FLEX-2:three-file-packet` | `f2aeaab0494f636131241fad1a3f526571ef11b408c60d5e66888d12d99b8999` | FlexCRM owner | pending — D16/D41/D42/access not a grant |

## Assertions

- Receipt creation precedes handoff replacement; append-only progress preserves historic bytes.
- Identical immutable acceptance replay is a no-op; differing bytes must reject.
- Stale preimage, symlink escape, or partial owner delta => no-write / reconcile before retry.

## Remaining app work

| Area | Owner | Notes |
|:--|:--|:--|
| FLEX-2 P6.5/P6.17/P6.18 milestones | FlexCRM program owner | not closed by dependency acceptance |
| Documents/calendar/frontend activation | FlexCRM owner | product scope retained |
| P10 effective seal | P10 branch owners | readiness-only gaps remain |
