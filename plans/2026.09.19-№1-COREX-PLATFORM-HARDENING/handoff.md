# HANDOFF — 2026-09-23 — after P7.5

**Next:** run-items: task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.6-P7.7

| Параметр | Значение |
|:--|:--|
| Batch | E07e |
| Model class | implementation/medium |
| Effort | medium |
| Native model | composer-2.5 |
| Capabilities | — |
| Context | continue-root |
| Суть | P7.6 инъекция AuthOptions в access/matrix readers. |

```text
$ task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.6-P7.7
```

**Done:** P7.5 GREEN. Workspace and OIDC controllers take
`ImpersonationPolicy` / `TenancyOptions`. Remainder in tenancy `src`:
two Commands (CFG-002) and provider stancl projection. pest 476/1513,
pg 228/865, analyse 0, pint ok.

**Remaining:** P7.6 → P7.7 (AuthOptions), затем P7.8+.

**Pointers:** `research/P7-design.md` §8; `phases/P7/P7.6.md`;
`findings/P7-receipts.md` · `journal.jsonl` · `execution-batches.md`.
