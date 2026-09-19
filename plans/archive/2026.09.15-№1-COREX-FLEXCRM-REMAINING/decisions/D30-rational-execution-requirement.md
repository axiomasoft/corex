---
id: D30
date: 2026-09-15
status: accepted
item: design
items: [design, P3.3, P3.4, P3.5, P3.6, P3.7]
supersedes: []
superseded_by: null
---
# D30 — Обязательное рациональное исполнение оставшегося плана

## Решение

По явному запросу владельца добавить в plan.md обязательное требование рационального
исполнения всех последующих items и подготовить Codex launch/prompt для B-P3-IDENTITY
(P3.3 → P3.4 → P3.5 → P3.6 → P3.7), gpt-5.6-terra/high по maxima существующего Routing.
Это точечное уточнение execution requirements, без продуктового исполнения в этой сессии,
нового master plan, phase audit, runtime change или нового grant.

Норма: один bounded prepare; source-complete qualified reuse; каждый обязательный check,
обоснованные дополнительные/повторные checks; упорядоченное закрытие/recovery;
раздельные current/future verdicts; compact output и сохранение реальных exits;
continuation внутри owner scope без промежуточных подтверждений. Точный текст —
plan.md, раздел «Рациональное исполнение — обязательное требование D30».

Не изменяются обязательные sources/selectors, отдельные read limits, Validation/Review,
физическая изоляция и fault proofs, scope receipts и действующие budget policies.
D23 остаётся только B-P3-LOCAL. Strict B-P3-IDENTITY budget пока BLOCKED; до будущего
START требуется matching решение этой группы либо другое разрешённое contract-complete
устранение gate. Этот decision не принимает G-STORAGE/G-DEPENDENCY/другие protected
изменения и не разрешает выход за P3.7 или закрытие P3 без отдельного owner scope.

Обновить owning decision projections, изменившиеся design bindings и следующий P3.3
bundle один раз после правки. Сохранить исходный design-finish и точный manifest delta.
Исторические acceptance receipts и чужие рабочие изменения не переписывать.

## Почему

Владелец прямо попросил команду «в формате CODEX с моделью и аргументами», промпт против
разобранных проблем и требование для плана в целом. Основание — полевой отчёт SKM:
`roadmap/2026.09.15-corex-p3-local-resource-efficiency.md` в repo swissknifeman.
Он выявил schema/path defects, повторные вычисления, dependent bookkeeping races,
шумный вывод и coupling future gates; обязательные реальные safety checks остаются нужны.
Это принятие организации работы, а не автоматическое внедрение roadmap предложений.
