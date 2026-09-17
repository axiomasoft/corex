# Codex — B-P3-IDENTITY, новая сессия по запросу владельца

| Параметр | Значение |
|:--|:--|
| Batch | B-P3-IDENTITY: P3.3 → P3.7 |
| Model | gpt-5.6-terra |
| Thinking | high |
| Context | cold-start-root: owner-requested-new-session |

**Native Codex command:**

```bash
codex -C /home/vostrikov/projects/packages/corex -m gpt-5.6-terra -c 'model_reasoning_effort="high"' -a never -s danger-full-access \
'$ task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P3.3 P3.4 P3.5 P3.6 P3.7

Выполни B-P3-IDENTITY последовательно P3.3 → P3.7 в одном root, без дочерних агентов. Сохраняй рабочие изменения. Следуй plan.md, handoff.md, item contracts и обязательному требованию D30 «Рациональное исполнение». Качество важнее числа проверок.

До START проверь полное admission и matching owner receipts. Strict identity budget пока BLOCKED: подготовь конкретный scoped запрос и спроси только недостающее решение владельца. D23 и прежние G receipts за их scope не расширяй; P2 proposals разрешениями не считать.

Один ранний bounded prepare: native grammar затронутых reads/paths/decisions и реальные product anchors. Все обязательные sources, selectors, per-read limits, Validation и Review сохраняй. Новый root не считать освоившим прежний corpus; reuse только по допустимым consumption receipts, не одному SHA. После compaction восстанови checkpoint и читай delta.

Каждый обязательный check выполни; дополнительные и повторные — только с материальной причиной changed input/environment, новой гипотезой, stale proof или live boundary. После GREEN неизменные product checks ради bookkeeping не повторяй. Safety/concurrency/crash proofs сохраняй. Зависимые edits/checks/projections/seal выполняй последовательно, независимые проверки объединяй с изоляцией mutating targets.

Перед terminal проверь изменённые decision schema и closure inputs. После каждого закрытия один раз обнови affected views/bundle и admission следующего ready item, продолжай без промежуточного подтверждения. Recovery — исходный run/attempt, без повторных START/terminal/tests. Current closure и future gates разделяй; global RED/exit сохраняй, stderr для обхода gates не фильтруй.

Короткий outcome/errors, full immutable logs по pointer/SHA. Общий runtime SKM, будущие consumers, whole-plan audit и fleet health не трогай. При нехватке контекста сохрани checkpoint. За P3.7 не выходи, P3 не закрывай; подготовь следующий conditional handoff и local delivery.'
```

Prompt встроен в аргумент команды, без cat и resume. Route — maxima существующего
Routing: P3.3–P3.6 implementation/high, P3.7 implementation/medium.
До START остаётся strict identity budget gate; launch не принимает missing owner decision.
