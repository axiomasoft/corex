---
id: D80
date: 2026-10-01
status: accepted
item: P10
items: [P10]
supersedes: []
superseded_by: null
---
# D80 — Аудитор текущего дизайна: gpt-6.1-sol в новой сессии

**Actor:** repository owner; запись designer/codex/GPT-6.
**Evidence:** сообщение владельца «не astra, а gpt-6.1-sol» после команды аудита; требование новой сессии — D79; RAG:—.

## Solution

1. Текущий независимый design audit после D79 запускается в новой сессии Codex с selector gpt-6.1-sol вместо gpt-6-astra. Effort xhigh сохраняется. Это явный owner override конкретного provider selector для данного аудита.
2. Semantic route frontier/xhigh сохраняется; классы исполнения P10.1–P10.4 и их reviewers не меняются. Модель добавляется в session-routing.md только для текущего audit.
3. Авторские контракты, fingerprint finish и требование GREEN до P10.1 не меняются. Аудит этой правкой не запускается.

## Why

Владелец прямо выбрал модель для следующего запуска. Его указание приоритетнее default provider mapping Task.

## Consequences

Команда в handoff и актуальный delivery receipt используют gpt-6.1-sol. Исторические носители сохраняются.
