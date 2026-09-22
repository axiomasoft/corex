# HANDOFF — 2026-09-22 — after P6.3

**Next:** exec-items: task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P6.4

| Параметр | Значение |
|:--|:--|
| Batch | solo |
| Model class | implementation/high |
| Effort | high |
| Native model | cursor-grok-4.7-high |
| Capabilities | — |
| Context | continue-root |
| Суть | Кандидаты Features, Departments и Money: вердикт и roadmap §7. |

```session-continuity-decision/v1
{"outcome":"continue-root","reason":"authorized-scope-complete","evidence":["batch:4a2f9c615d676d13fe2514fc809667c35700dd308a1380700272e2d55b029c9c","loaded-inputs:not-reusable","cold-start-cost:0","lifecycle:scope-complete","checkpoint:journal:P6.3:run:8e640128884e2841c18490ee0e14733cbc21afe8f2e7c8096ae2077f6ab6b0b5"],"runnable":true,"schema_version":"session-continuity-decision/v1"}
```

```text
$ task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P6.4
```

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P6.4'
```

**Done:** P6.3 закрыт GREEN. `tests/Arch/CoreLitmusTest` проверяет шесть условий
литмуса только для Foundation. Условие 3 закрыто R-L3 вне трёх путей
`LITMUS_ELOQUENT`. `vendor/bin/pest tests/Arch` — 44 passed.
`vendor/bin/pint --test` — exit 0.

**Remaining:** P6.4 и P6.5 готовы по данным. P6.6 последний.
Порядок фазы — `{P6.4, P6.5}` → `P6.6`.

**Sources of truth:** `phases/P6/P6.3.state.md` · `tests/Arch/CoreLitmusTest.php` · `docs/architecture/core-zones.md` · `decisions/D34-litmus-eloquent-disqualifies-foundation.md` · `findings/P6-receipts.md` · `journal.jsonl`

**Open risks:** Чужой diff в `packages/core/src` не входит в P6.3.
Снятие шести рёбер D116 — отдельный item под G-API.

**Workarounds/Deferred/Open questions:**

- workarounds: —
- deferred: перенос `DataScope::fromTenantContext` и поля `DomainEvent::$tenant`.
- open_questions: —
