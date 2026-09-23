# HANDOFF — 2026-09-23 — after P7.1

**Next:** exec-items: task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.12

| Параметр | Значение |
|:--|:--|
| Batch | solo |
| Model class | implementation/medium |
| Effort | high |
| Native model | grok-4.7-high |
| Capabilities | — |
| Context | cold-start-root: capability-missing |
| Суть | P7.12 доказывает D100: missing/null/zero TTL и central→tenant→central на одном repository. |

```session-continuity-decision/v1
{"outcome":"cold-start-root","reason":"capability-missing","evidence":["batch:solo","loaded-inputs:not-reusable","cold-start-cost:0","checkpoint:journal:P7.1:run:590dc99b8a590f18cacc57887e9876462e7553d7cfe716b8ecbfaf89402aab51"],"runnable":true,"schema_version":"session-continuity-decision/v1"}
```

```text
$ task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.12
```

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.12'
```

**Done:** P7.1 GREEN. `CoreOptions`, `SecretRef`, `ViolationList` и
`InvalidConfiguration` введены. `Config` держит снимок `corex` и не вызывает
`config()`. Saved views получают options и database manager в конструкторе.
sqlite 84, pg 37, CoreZoneTest 7, anti-weakening OK.

**Remaining:** P7.12 — focused D100 witnesses. Затем P7.2. Receipt фазы
пишет P7.12, не P7.1.

**Sources of truth:** `phases/P7/P7.1.state.md` · `phases/P7/P7.1.md` ·
`research/P7-design.md` §3–§5, §15 · `decisions/D39-config-bound-holder.md` ·
`decisions/D40-secretref-and-boot-fail.md` ·
`decisions/D100-dynamic-default-connection-and-null-ttl.md` · `journal.jsonl` ·
`execution-batches.md`.

**Open risks:** Чужой diff в `packages/` и `tests/` был до P7.1 и сохранён.
`Config::options()` перечитывает репозиторий `corex` у загруженного
приложения, чтобы тесты, меняющие config после boot, сохранили поведение.
Override Octane живёт отдельно через `Config::enableOctaneWorkerOptIn()`.

**Workarounds/Deferred/Open questions:**

- workarounds: —
- deferred: D100 behavior witnesses → P7.12; Options других пакетов → P7.2+.
- open_questions: —
