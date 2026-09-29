---
id: D54
date: 2026-09-29
status: accepted
item: P02.4
items: [P02.4]
supersedes: []
superseded_by: null
---
# D54 — Исторические ключи проекции ratchet

**Actor:** executor/codex/gpt-6-sol/high по мандату B6, plan §2.
**Evidence:** RAG:— полный rename-completeness даёт ровно 19 package-токенов в `scripts/ratchet-successors.json`; `scripts/anti-weakening-gate.sh` читает `packages` как отображение идентификаторов предыдущего git-дерева в текущих владельцев. D44/D51 требуют сохранить эту проверку без ослабления.

## Решение

Дополнить разрешённые исключения D45 единственным точным файлом `scripts/ratchet-successors.json`. Его прежние package-ключи — входная историческая сторона преобразования, а не живые зависимости. Не менять ключи, значения, groups или source_transfers. Исключение не распространяется на другие scripts; негатив прежнего package-токена в живой документации остаётся RED.

## Почему

Удаление либо переименование исходных ключей лишает anti-weakening корректной проекции предыдущего дерева. Это та же историческая семантика, что у уже исключённых Golden rename-map и package-projection. Точный файл содержит только закрытую карту, не продуктовый код.
