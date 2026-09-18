# HANDOFF — 2026-09-18 — after P9.5

**Next:** exec-items: task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P9.6

| Параметр | Значение |
|---|---|
| Batch | solo:P9.6 |
| Model class | implementation |
| Effort | high |
| Executor | Cursor / Composer 2.5 |
| Capabilities | shell, FS, git, gh |
| Context | continue-root |
| Суть | Independent optional private auth install (G-PRIVATE); azguard публичный — секрет не нужен. |

**Done:** P9.5 closed-green — mirror run `35349147513` @ `a2783c1` on `academici/corex-mirror`; 11/11 jobs green; RUN-01–RUN-07 PASS; hosted receipt `6e9e83eb…`; `findings/P9-hosted-runs.md`; stancl staging + test stabilisation (`64789b1`).

**Ready:** P9.6 private-read packet (`remaining-cohort-private.py`).

**Remaining:** P9.6 → P9.7–P9.9, P10, P11.

**Open risks:**
- Hosted execution uses mirror (`academici/corex-mirror`); canonical authority remains `axiomasoft/corex` + frozen runner-packet.
- P9.6 G-PRIVATE — отдельный owner gate; azguard public, без AZGUARD_TOKEN.

**Sources of truth:** `findings/P9-hosted-runs.md`; `delivery/remaining-cohort-20260915/hosted/run-35349147513.json`; `delivery/remaining-cohort-20260915/hosted/receipt.json`; `delivery/remaining-cohort-20260915/runner-packet.json` (`a93f5923…`); `journal.jsonl`.

```bash
python3 scripts/distribution/remaining-cohort-private.py --preflight
exec-items: task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P9.6
```
