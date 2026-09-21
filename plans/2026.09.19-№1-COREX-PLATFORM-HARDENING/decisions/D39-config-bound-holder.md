---
id: D39
date: 2026-09-19
status: accepted
item: P7.1
items: [P7, P7.1]
supersedes: []
superseded_by: null
---
# D39 — `Support/Config` становится holder над `CoreOptions`, не читателем `config()`

**Actor:** plan-designer/cursor-grok-4.6/high
**Evidence:** RAG:— N12: 31 `config(` в `Support/Config.php`; вызывают
`Config::` модели, миграции, `HasConfiguredId`, `IdColumns`, audit, pubsub,
departments и `packages/crm`. RAG:✅ P7-Q1: `fromArray` не вызывает
`config()`; единственное чтение — composition root. RAG:✅ P7-Q3 / Q10:
deployment-конфиг процесса стабилен; статика, держащая immutable snapshot,
не является per-request мутацией.

## Решение

1. `CoreServiceProvider` читает `config('corex')` один раз, строит
   `CoreOptions::fromArray`, биндит singleton и вызывает `Config::bind`.
2. Методы `Config::*` читают только связанный `CoreOptions`. Вызов до
   `bind` — `LogicException`. `config()` из `Config.php` исчезает; N12
   закрыт; путь вычёркивается из allowlist P3.6.
3. Именованное исключение **E-CONFIG-HOLDER:** модели, миграции и трейты
   идентичности продолжают `Config::`. Новые сервисы получают `CoreOptions`
   конструктором. `SqlSavedViewRepository` переводится на конструктор в
   P7.1 — это единственный не-провайдерный `config()` ядра.
4. `unbind()` существует только для тестов харнесса.

## Почему

Удалить `Config` значит тронуть миграции и Eloquent-модели в одном item
с фабрикой — это выходит за Files и за поведение «валидная конфигурация
не меняется». Повторить N12 статическим чтением `config()` запрещено
эскизом. Holder над уже проверенным snapshot — ровно boot-адаптер.

## Consequences

P7.1 меняет `Config.php`, провайдер и `SqlSavedViewRepository`.
Остальные `Config::` не трогаются. Гейт P7.11 больше не исключает
`Config.php`.
