---
id: D20
date: 2026-09-19
status: accepted
item: P4.9
items: [P4, P4.9, P4.10, P5, P9]
supersedes: []
superseded_by: null
---
# D20 — Часы CoreX — это `Psr\Clock\ClockInterface`, а не собственный контракт

**Actor:** plan-designer/claude-opus-5/high

**Evidence:** `RAG:—` в `packages/core/src` нет ни `Clock`, ни `ClockInterface`
(находка N01); 151 вызов `now(` в `packages/*/src`. `RAG:—` `psr/clock` версии 1.0.0
уже присутствует в корневом `composer.lock` как транзитивная зависимость
`nesbot/carbon` и `symfony/clock`; проверка — секция `packages` файла `composer.lock`
и `require` пакета `nesbot/carbon`. `RAG:—` `Carbon\FactoryImmutable` в дереве
реализует `Symfony\Component\Clock\ClockInterface`, который наследует PSR-20.
`RAG:✅` P4-Q1 (`research/P4-design-rag.md` §1): PSR-20 — завершённый стандарт
PHP-FIG с одним методом `now(): \DateTimeImmutable`; собственный интерфейс оправдан
только при иной семантике (бизнес-календарь, монотонное время), и вводить локальный
`ClockInterface` ради мокабельности прямо не рекомендуется. `RAG:✅` тот же ответ:
`Carbon::setTestNow()` и хелперы `travelTo`/`freezeTime` не влияют на инъектированные
PSR-часы, и наоборот.

## Решение

`corex/core` не объявляет собственный интерфейс часов. Сервисы типизируются против
`Psr\Clock\ClockInterface`; `packages/core/composer.json` получает
`"psr/clock": "^1.0"` в `require` и `"psr/clock-implementation": "1.0"` в `provide`.

Ядро поставляет три реализации в `CoreX\Support\Time`: `SystemClock` (UTC, без
фреймворка), `FrozenClock` (`advance()`, для тестов потребителя без dev-зависимости) и
`LaravelClock` поверх `Illuminate\Support\DateFactory`. **Привязка по умолчанию —
`LaravelClock`.**

Эскиз `research/01-shapes-runtime.md` §1 в части `interface Clock { public function
now(): DateTimeImmutable; }` **отклонён**; формы `SystemClock`/`FrozenClock` из эскиза
приняты с заменой реализуемого интерфейса.

## Почему

Собственный интерфейс с той же сигнатурой, что у стандарта, даёт нулевую семантику и
ненулевую цену: потребитель, у которого уже есть `symfony/clock` или
`lcobucci/clock`, вынужден писать адаптер к контракту, который ничем не отличается от
PSR-20. Для пакета уровня 0, чья заявленная ценность — «полезен любому Laravel-проекту»,
это прямой антипаттерн.

Запрет плана на новые runtime-зависимости не нарушается: `psr/clock` физически уже
установлен транзитивно, объявление делает зависимость честной, а не добавляет пакет.
Именно это и проверяет item: `composer why psr/clock` до правки уже называет
`nesbot/carbon`.

`LaravelClock` как дефолт выбран из-за ловушки, названной внешним источником: если
дефолтом сделать `SystemClock`, то `travelTo()` в существующих тестах перестанет
влиять на код, переведённый на часы, и появится два несинхронных времени в одном
прогоне. `DateFactory` читает актуальное состояние Carbon в момент вызова, поэтому
существующие тесты, управляющие временем через фреймворк, продолжают работать без
правок.

Альтернатива «оставить `now()` и мокать Carbon» отклонена: она работает только внутри
Laravel-тестов, не выражает зависимость в сигнатуре и не даёт потребителю подменить
источник времени — ровно то, чего требует RUN-004.

## Consequences

`corex/core` становится поставщиком `psr/clock-implementation`; при разделении ядра на
зоны (P6) часы попадают в `Foundation`. P4.10 переводит durable-пути `packages/wakeup`
на инъектированные часы. P5 (outbox, `occurredAt`) и P9 (tenancy) получают готовый
контракт и не изобретают второй. Замена остальных вызовов `now()` за пределами
durable-путей в P4 не выполняется — граница названа в `research/P4-design.md` §7.4.
