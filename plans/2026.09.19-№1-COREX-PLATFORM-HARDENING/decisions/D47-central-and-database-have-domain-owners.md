---
id: D47
date: 2026-09-19
status: accepted
item: P9
items: [P9, P9.3, P9.4]
supersedes: []
superseded_by: null
---

# D47 — `Central` и `Database` получают доменных владельцев

**Actor:** plan-designer/codex-gpt-5.6-sol/high

**Evidence:** `RAG:—` листинг: 12 классов `Central` описывают
membership transport/protocol; 6 классов `Database` обслуживают template,
schema catalogue и provisioning. `RAG:✅` P9-Q1/Q2: context и domain должны
быть явными; infrastructure не становится доменом.

## Решение

1. `Central/**` переезжает в `Membership/Transport/**`.
2. `Database/**` переезжает в `Provisioning/Database/**`.
3. Слова Central/Database могут оставаться в имени класса, если это
   точный термин; корневым bounded context они быть не могут.

## Почему

Ось «где лежат данные» и ось «какая возможность ими владеет» не должны
соперничать в корне `src`. Пропуск этих 18 файлов оставил бы ту же
смешанную ось под новыми именами.

## Consequences

P9.3 и P9.4 вместе доказывают, что корневых `Central`/`Database` нет.
Миграции и имена таблиц не меняются.

