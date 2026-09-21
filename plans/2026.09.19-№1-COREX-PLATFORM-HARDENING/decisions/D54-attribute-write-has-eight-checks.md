---
id: D54
date: 2026-09-20
status: accepted
item: P10.6
items: [P10, P10.2, P10.4, P10.6, P13]
supersedes: []
superseded_by: null
---

# D54 — Attribute write проходит восемь независимых проверок

**Actor:** plan-designer/codex-gpt-5.6-sol/high

**Evidence:** `RAG:—` текущий validator проверяет existence/required/type, а stores
вызывают один общий authorizer action. Schema revision, subject ownership, view/write
и operation authority неразличимы.

## Решение

Порядок: existence; schema compatibility; subject ownership; field view; field write;
operation mutate; type; constraints. Переиспользуется `DataScopeAuthorizer` с
различными action и opaque resource. Batch fail atomic. Наружу ошибки нормализованы,
внутри сохраняется reason code.

## Почему

Одна общая проверка не доказывает ни tenant isolation, ни field-level authority.
Порядок existence→authority не должен раскрывать скрытое поле внешнему caller.

## Consequences

`ValuePatch` additively получает nullable `expectedSchemaHash`. Migrating/deprecated
field без precondition не пишется. P13 позднее связывает `attributes.mutate` с
operation policy; P10 не меняет контракт `onlyFields`.
