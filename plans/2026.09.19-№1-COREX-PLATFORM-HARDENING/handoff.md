# HANDOFF — 2026-09-24 — after P9.3

**Next:** `$ task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.4-P9.6`

**Sources of truth:** `findings/P9-receipts.md` ·
`findings/P9-tenancy-map.md` · `findings/P9-compatibility-baseline.json` ·
`journal.jsonl`

| Параметр | Значение |
|:--|:--|
| Batch | E09d |
| Model | frontier |
| Thinking | high |
| Context | continue-root |
| Команда | `$ task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.4-P9.6` |
| Суть | P9.4–P9.6 переносят Account/Provisioning, Identity/Authentication и Workspace/DomainTrust/Impersonation. Review full. |

P9.3 закрыт GREEN: Membership без `Internal`, `Central` в `Membership/Transport`,
события и `RefreshAccountMembership` сохраняют FQCN. Structure gate остаётся
красным до P9.8: он сверяет pre-move baseline P9.1.

**Done:** P9.3 GREEN. Membership tree, transport merge, preserved FQCN maps, receipt.

**Remaining:** P9.4–P9.8 и P10–P17.

**Open risks:** D48 multi-account relay остаётся открытым до starvation,
SLO breach или manual fan-out. Structure gate не принимает пост-переносное
дерево до P9.8.

**Pointers:** `phases/P9/P9.4.md`; `findings/P9-receipts.md` ·
`journal.jsonl`.

**Native model:** `cursor-grok-4.6-high`

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.4-P9.6'
```
