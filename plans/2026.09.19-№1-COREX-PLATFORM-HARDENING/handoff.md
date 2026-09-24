# HANDOFF — 2026-09-24 — after P9.6

**Next:** `$ task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.7`

**Sources of truth:** `findings/P9-receipts.md` ·
`findings/P9-tenancy-map.md` · `findings/P9-compatibility-baseline.json` ·
`journal.jsonl`

| Параметр | Значение |
|:--|:--|
| Batch | E09e |
| Model | frontier |
| Thinking | high |
| Context | continue-root |
| Команда | `$ task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.7` |
| Суть | P9.7 переносит Wakeup/Internal в Integration/Wakeup и фиксирует package-graph allowlist. Review full. |

P9.6 закрыт GREEN: `Workspaces` → `Workspace`, модели и события
DomainTrust/Impersonation/Workspace сохраняют FQCN. E09d (P9.4–P9.6)
закрыт. Structure gate остаётся красным до P9.8.

**Done:** P9.6 GREEN. Workspace/DomainTrust/Impersonation trees, preserved FQCN maps, receipt.

**Remaining:** P9.7–P9.8 и P10–P17.

**Open risks:** D48 multi-account relay остаётся открытым до starvation,
SLO breach или manual fan-out. Structure gate не принимает пост-переносное
дерево до P9.8.

**Pointers:** `phases/P9/P9.7.md`; `findings/P9-receipts.md` ·
`journal.jsonl`.

**Native model:** `grok-4.7-high`

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.7'
```
