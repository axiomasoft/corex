---
id: D56
date: 2026-09-20
status: accepted
item: P11
items: [P11, P11.1, P11.2, P11.3, P11.8]
supersedes: []
superseded_by: null
---
# D56 — Registry authority отделён от проекций и фабрик драйверов

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— `RegistryCompiler`/`CompiledRegistry` уже владеют module
declarations; `OperationRegistry`, `CompiledToolCatalogue` и `RegistryFieldCatalogue`
читают этот snapshot; `ModulePanelCatalogue` собирает typed providers в
конструкторе; `SearchManager` создаёт один драйвер по config и не несёт
declaration catalogue.

## Решение

Authority code-level реестра — один compiled module snapshot.

- `OperationRegistry`, tool/panel/attribute catalogues — typed projections. Они не
  получают параллельные registration/freeze/fingerprint lifecycles.
- P10 composite attributes catalogue сохраняет свой `schemaHash` и source
  fingerprint: это data schema, а не дубль module registry.
- `SearchManager` классифицируется как driver factory и исключается из
  registry compile/freeze policy.
- Compiler normal form разрешён только как internal IR. Он не становится
  runtime God Registry и не выходит в public API.

## Почему

Требование freeze от любого класса с именем `Manager`/`Catalogue`
порождает фальшивые layers и не даёт дополнительной гарантии. Один
authority и проекции с явными live policy inputs дают проверяемую границу.

## Consequences

P11.8 проверяет матрицу, но не переписывает search/panels/api/attributes.
P13 может строить API profile как проекцию над frozen declarations, а не
как второй источник операций.
