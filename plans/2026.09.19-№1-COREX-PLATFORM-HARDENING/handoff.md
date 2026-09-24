# HANDOFF — 2026-09-24 — after P9.7

**Next:** `$ task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.8`

**Sources of truth:** `findings/P9-receipts.md` ·
`findings/P9-tenancy-map.md` · `findings/P9-compatibility-baseline.json` ·
`journal.jsonl`

| Параметр | Значение |
|:--|:--|
| Batch | E09f |
| Model | frontier |
| Thinking | xhigh |
| Context | continue-root |
| Команда | `$ task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.8` |
| Суть | P9.8 фиксирует 13-directory structure ratchet, compatibility proof и DATA-003. Review full. |

P9.7 закрыт GREEN: optional `corex/wakeup` adapter живёт в
`Integration/Wakeup`; allowlist называет только эту host-first boundary.
Негативная посадка из `Account/` даёт `[R1]`. Structure gate остаётся
красным до P9.8. Полный `package-graph-gate` несёт 64 чужих R4/R4b
после P9.2–P9.6 — владелец P9.8.

**Done:** P9.7 GREEN. Integration/Wakeup tree, exact allowlist, negative
fixture, receipt.

**Remaining:** P9.8 и P10–P17.

**Open risks:** D48 multi-account relay остаётся открытым до starvation,
SLO breach или manual fan-out. Structure gate не принимает пост-переносное
дерево до P9.8.

**Pointers:** `phases/P9/P9.8.md`; `findings/P9-receipts.md` ·
`journal.jsonl`.

**Native model:** `gpt-5.6-sol-xhigh`

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-xhigh --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.8'
```
