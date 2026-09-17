# Checkpoint — новая identity сессия по запросу владельца

Context: cold-start-root: owner-requested-new-session. Новая сессия запрошена владельцем явно.
B-P3-LOCAL закрыт: P3.1/P3.2 closed-green, исходный journal неизменён.
P3.3–P3.7 не начаты, фаза P3 открыта. Routing группы gpt-5.6-terra/high.
D30 — обязательная организация последующего исполнения; current design binding и P3.3 bundle обновлены.
Missing gate: strict identity estimated budget; D23 только P3.1/P3.2, нужен current замер и scoped owner choice до START.
G-STORAGE/G-DEPENDENCY/прочие receipts действуют только для matching actual delta.

Источники: plan.md; handoff.md; decisions/D30-rational-execution-requirement.md;
artifacts/execution-efficiency/D30-design-binding-delta.json; journal.jsonl;
artifacts/B-P3-LOCAL/checkpoint.md; required-read progress и owner-adoption receipts.
Чужие рабочие изменения сохранить. Новый root consumption автоматически не наследует;
changed plan.md/read selectors требуют допустимого current acquisition/receipt.
Следующая native команда и inline prompt: identity-codex-launch.md в этой папке.
До P3.7 включительно; P3 не закрывать, затем conditional handoff.
