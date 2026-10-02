---
id: D29
date: 2026-09-28
status: accepted
item: P00.1
items: [P00.1, P02, P03]
supersedes: []
superseded_by: null
---
# D29 — kernel-module-list (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6); frontier-вердикт P00.1 в пределах `research/P00-design.md` §2.
**Evidence:** RAG:— `artifacts/P00-design/facts/{import-matrix.txt,zone-consumers.tsv}`, `packages/*/composer.json`, `artifacts/P00/*.tsv`; `check-maps.py` (default/`--dependencies`) exit 0 на `b624936`+P00.1.

## Solution

Закрытый список модулей ядра: `Attributes, Audit, Config, Context, Data, Database, Departments, Errors, Features, Messaging, Modules, Observability, Settings, Storage, Time`; root symbol — только `CoreX\CoreServiceProvider`; reserved — `CoreX\Testing` (dev). Слоевые корзины ядра (`Contracts, Concerns, Data, Enums, Events, Exceptions, Models, Support, PHPStan`) растворяются пофайлово по модулям (`namespace-map.tsv`, заметка D29). Departments (F5) и Features/Settings/Audit/Storage/Messaging/Observability (F6) остаются в ядре. Имена: `Database` (BaseModel, id-стратегия, колонки ключей) вместо предложенного `Ids`; `DomainEvent`/`Cancellable` → Messaging; правило `DurablePayloadRule` → `CoreX\Messaging\PHPStan`.

## Why

- D04: FQCN ядра = закрытый список; корзины — запрет D05 (N5).
- Departments вплетён в scope Settings/Features — вынос дал бы ядро→пакет; F6 — платформенные сервисы каждого приложения.
- `Ids` не покрывает `BaseModel` (soft deletes, аудит); `Database` — назначение всего набора. `DomainEvent` — внутрипроцессная часть сообщений; отдельный модуль `Events` конфликтовал бы с категорией D05.

## Consequences

P02 переносы (одна фаза с Context/Money/SavedViews); ownership-гейт P01 читает §Kernel modules glossary.
