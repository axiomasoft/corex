---
id: D52
date: 2026-09-20
status: accepted
item: P10.4
items: [P10, P10.2, P10.3, P10.4, P10.6]
supersedes: []
superseded_by: null
---

# D52 — Эволюция атрибута — expand/sync/backfill/verify/contract

**Actor:** plan-designer/codex-gpt-5.6-sol/high

**Evidence:** `RAG:✅` GitLab operational guidance для concurrent rename/type change
разделяет создание нового представления, синхронизацию, перенос данных и поздний cleanup.

## Решение

Rename/type change публикует новую append-only revision той же identity. Переход
проходит dual-read/dual-write, resumable idempotent backfill, digest verification,
write switch, grace и только затем contract. Type change требует declared pure
transformer. Physical drop — отдельная verified schema operation.

## Почему

Одношаговая смена key/type теряет concurrent writes или делает rollback невозможным.
Checkpoint без повторной проверки record version также допускает drift.

## Consequences

До switch rollback возвращает предыдущую active revision; после switch — обратный
migrating режим. Каждая batch transaction двигает cursor только после commit. Прямой
`active→retired` запрещён.
