---
id: D86
date: 2026-10-02
status: accepted
item: P05.1
items: [P05.1, P05.2]
supersedes: []
superseded_by: null
---
# D86 — Канонические английские заголовки и waiver design audit для P05.1/P05.2

**Actor:** repository owner; запись editor/claude-code/claude-sonnet-5-5/high.
**Evidence:** `artifacts/P05-header-fix/owner-direction.md` — дословное сообщение владельца в этой сессии; RAG:—.

## Solution

1. Task 0.26.2 принимает только английские имена структурных заголовков. Вручную приведены к каноническим: Routing `Почему` → `Why`; Meta `Поле | Значение` → `Field | Meaning` и префикс Title `Plan №1`; Exported/Consumed таблицы Phase Handoff; заголовки всех decision-файлов `Решение/Почему/Последствия` → `Solution/Why/Consequences`; Phase Handoff P10 заполнен фактами закрытия. Содержимое, порядок, маршруты и review не менялись.
2. Правка заголовка изменила design fingerprint при `**Design Audit:** required`. По прямому указанию владельца «исправить план вручную, чтобы не было препятствий для выполнения P05.1 P05.2» новый whole-plan design audit не назначается: используется штатный `design-audit-waiver/v1`, привязанный к fingerprint и сохранённому указанию. Независимый GREEN audit не заявляется.
3. Waiver покрывает только этот fingerprint; любое существенное изменение дизайна делает его stale. Validation, review (`light` для B05-A), зависимости, маршрут D85 и terminal admission P05.1/P05.2 не изменены.

## Why

Локализованные имена заголовков не должны блокировать исполнение; формальные заголовки всегда английские. Правка механическая и не меняет смысла плана.

## Consequences

Handoff ведёт к `task:plan-run 2026.09.27-№1-COREX-KERNEL P05.1 P05.2`. Продукт не менялся; skm roadmap фиксирует требование к инструменту.

```design-audit-waiver/v1
{
  "schema_version": "design-audit-waiver/v1",
  "plan_id": "2026.09.27-№1-COREX-KERNEL",
  "decision_id": "D86",
  "scope": "whole-plan-design-audit",
  "design_fingerprint": "85b30ed220d75c7bb0368336f77b30badcc4d5fb8add5be66fb2d82881f20da4",
  "owner_provenance": {
    "path": "artifacts/P05-header-fix/owner-direction.md",
    "sha256": "d907b23a2a2fb34c7299e3b716846d7f69db85231da25f69b835799fd71bde16",
    "message": "Исправь план в ручно режиме, чтобы не было припятствий для выполнения"
  },
  "reason": "Owner directs manual repair of plan headers so P05.1/P05.2 can run; no repeated design-audit round for this mechanical header change."
}
```
