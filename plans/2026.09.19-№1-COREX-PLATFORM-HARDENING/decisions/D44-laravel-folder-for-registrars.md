---
id: D44
date: 2026-09-19
status: accepted
item: P8
items: [P8, P8.2, P8.3, P8.4, P8.5]
supersedes: []
superseded_by: null
---
# D44 — registrar'ы живут в `Laravel/`, P9 их не рассыпает по доменам

**Actor:** plan-designer/cursor-grok-4.6/high
**Evidence:** RAG:— скелет P8 уже называет `packages/tenancy/src/Laravel/**`;
скелет P9 планирует bounded context `Laravel` для bootstrappers, resolvers,
middleware и команд. RAG:✅ P8-Q1: composition helper — внутренний, не
публичный entry point.

## Решение

1. Новые классы tenancy: `CoreX\Tenancy\Laravel\*Registrar` в
   `packages/tenancy/src/Laravel/`. Каталог создаётся в P8, до дизайна P9.
2. Новые классы modules: `CoreX\Modules\Laravel\*Registrar` в
   `packages/modules/src/Laravel/`.
3. P9 **потребляет** `tenancy/src/Laravel/` как composition-root слой и
   не раскладывает registrar'ы по DomainTrust/Identity/Membership.
   Перенос registrar'а в доменный каталог — дефект P9.
4. FQCN registrar'ов — новые типы; публичные FQCN существующих классов
   не меняются. Переименования — P15.

## Почему

Ждать P9, чтобы выбрать папку, оставило бы P8 без места записи. Класть
registrar рядом с доменным кодом смешало бы сборку контейнера с моделью
и заставило бы P9 двигать те же файлы второй раз. Альтернатива
`Internal/Registrars/` отклонена: P9 всё равно заводит `Laravel/` под
тот же смысл.

## Consequences

P8.2–P8.4 создают `packages/tenancy/src/Laravel/`. P8.5 создаёт
`packages/modules/src/Laravel/`. Спека P9 обязана назвать этот каталог
во входных чтениях и в Scope Excluded для доменных переносов.
