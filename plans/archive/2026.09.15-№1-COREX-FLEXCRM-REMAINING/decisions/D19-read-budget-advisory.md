---
id: D19
date: 2026-09-15
status: accepted
item: design
items: [design]
supersedes: []
superseded_by: null
---
# D19 — Read-budget как рекомендация для B-P1-QUALIFY

## Решение

Прямое сообщение владельца в текущем root: «надо сразу пометить, что это как бы рекомендация»; «если вываливаешься за лимит, надо держать ухо вастро»; «мне важно качество»; «не задаю жёсткие границы». Это разрешение advisory policy вместо ранее предложенного250000 hard cap. Исключение250000 не принимается и не применяется.

## Область и исполнение

Только B-P1-QUALIFY/P1.1–P1.4 этого плана. Threshold100000 остаётся ориентиром и единицей неизменённой калибровки; превышения печатаются WARNING вместе с native measured weight. plan-owned scripts/task-check.py использует публичный native lint API с собственным Diagnostics sink: только точная D2(a) диагностика этой группы, подтверждённая native measurement, получает advisory severity. Все прочие ошибки, отсутствующие/нечитаемые inputs, digest/producer/структурные проверки и per-read transport limits остаются обязательными. Новый policy version1.0.0 в research/09-read-budget-policy.json; native contract и Task runtime не меняются. Scope проверяется строго; будущие P2–P11 не получают исключение.

100sources/52R/365parentcriteria/все clauses/thresholds/G/DI/AP сохраняются. Cache, defer, дробление и summary не используются для ложного снижения веса. Измерения до/после и original hard-policy diagnostics сохраняются; PASS означает owner policy, а не отсутствие native превышения. Перед каждым пунктом пересчитать готовность/полный read path. При большом объёме читать последовательными chunks, сохранять checkpoint и отдельно проверять полноту. Объём не является поводом для костылей/пропуска проверки/фиктивного receipt.

P1.4 producer annotations в повторных Required Reads исправляются детерминированно. Waiting-owner прекращён решением владельца; продолжение в том же root в пределах P1.4. После группы D19 не применяется к P2; никаких P2 writes в этом invocation.

## Почему

Measured174212/143022/125689(before map/ledger), frozen100000. Native design-time lint не упражнял active-phase budget. Материальный новый policy choice утверждён владельцем; quality/completeness preserved. Альтернативы: ограниченное250000 hard exception (отклонено этим решением); реальное сужение read contracts с сохранением полного покрытия; redesign items с доказанным aggregate improvement. Ни один вариант не разрешает автоматически принять API/schema/release/live/consumer branches.

## Проверка

Named design/lint/item validators, native measurements, negative policy checks: only eligible exact threshold error may become warning; P2 and D2(b)/digest/unknown diagnostics remain errors. Stable finish reseal — только bookkeeping/owner policy/producer repair delta, без заявления нового независимого whole-plan review.
