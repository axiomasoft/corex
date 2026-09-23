# HANDOFF — 2026-09-23 — after P8.5

**Next:** `exec-items: task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P8.6`

**Sources of truth:** `findings/P8-receipts.md` ·
`findings/P8-binding-catalog.md` · `execution-commands.md` · `journal.jsonl`

| Параметр | Значение |
|:--|:--|
| Batch | E08e |
| Model class | frontier/high |
| Effort | high |
| Native model | grok-4.7-high |
| Capabilities | — |
| Context | continue-root |
| Суть | Гейт порога размера провайдера: 20 + ratchet + посадка. |

P8.5 закрыт owner plan-run на Grok 4.6 (overshoot vs pin
implementation/medium; owner явно оставил этот маршрут). P8.6 — review
`full`, поэтому `frontier/high`.

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P8.6'
```

**Done:** P8.5 GREEN. Registry/Lifecycle/Settings/Boot registrar'ы.
Провайдер без call site биндингов. modules pest 33/178, phpstan 0, pint ok.

**Remaining:** P8.6.

**Open risks:** корневой `composer test:pg` по-прежнему красный на чужом
baseline: `MigrationPublishTest` 12 vs 10 и `ModuleLifecycleTest` /
`hashtext` на sqlite. Arch CoreZone R-Z0 `ValidateConfigCommand`.

**Pointers:** `phases/P8/P8.6.md`; `research/P8-design.md` §10;
`findings/P8-receipts.md` · `journal.jsonl`.
