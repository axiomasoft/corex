---
id: D3
date: 2026-09-19
status: accepted
item: P1
items: [P1, P2, P3]
supersedes: []
superseded_by: null
---
# D3 — Таксономия пакета объявляется в существующем `extra.corex`, без переноса каталогов

**Actor:** plan-designer/frontier/high
**Evidence:** RAG:— `extra.corex` присутствует в 9 из 21 `composer.json` и содержит
единственный ключ `module`; корневой `composer.json` держит path-репозитории на 20 пакетов;
CI имеет отдельные джобы, привязанные к путям пакетов.

## Решение

Semantic kind пакета объявляется расширением уже существующего ключа:

```json
{"extra": {"corex": {
  "module": "CoreX\\Media\\MediaModule",
  "package-type": "capability",
  "stability": "experimental",
  "public-api": ["CoreX\\Media\\Contracts", "CoreX\\Media\\Data", "CoreX\\Media\\Exceptions"]
}}}
```

`package-type` — закрытый набор `foundation | capability | integration | product | tooling | fixture`.
Метаданные заполняются во всех 20 живых пакетах в P1; гейт направления зависимостей по типу
пакета добавляется в P3 к существующему `scripts/package-graph-gate.php`.

Каталоги `packages/capabilities/`, `packages/integrations/`, `packages/kernel/` не создаются.

## Почему

Новый корневой ключ (`extra.corex-package` или подобный) создал бы два места объявления
метаданных CoreX в одном файле. Физический перенос каталогов потребовал бы правки 20
path-репозиториев, всех путей CI-джобов, PHPStan-конфигов и split-рецептов — при нулевом
изменении направления зависимостей, которое и так проверяется гейтом. Сам аудит называет
физический вариант организационным и необязательным.

## Consequences

P1 производит схему `extra.corex` и заполняет её; P2 использует `package-type` как колонку
инвентаризации и оси scorecard; P3 расширяет гейт графа правилом направления
`foundation ← capability ← integration ← product`.
