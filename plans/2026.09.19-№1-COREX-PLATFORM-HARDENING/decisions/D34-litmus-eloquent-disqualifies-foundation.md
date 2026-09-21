---
id: D34
date: 2026-09-19
status: accepted
item: P6.3
items: [P6, P6.1, P6.3]
supersedes: []
superseded_by: null
---
# D34 — Условие 3 литмуса (нет Eloquent/HTTP/Application) дисквалифицирует Foundation; одноимённые зоны Features/Departments/Money добавляются в матрицу

**Actor:** plan-designer/cursor-grok-4.6/high
**Evidence:** RAG:✅ 2026-09-19 P6-Q5 Evans Shared Kernel, Microsoft modular
monolith, Drupal core vs modules — foundation это стабильный примитив с тем же
смыслом у всех потребителей; org-departments и финансовая политика — capability.
RAG:— эскиз `01-shapes-runtime.md` §3: «всё, что тянет Eloquent, HTTP или
lifecycle приложения, в Foundation не входит по определению». RAG:— P2-design.md
§7.1: один ложный пункт даёт `KEEP` + `litmus-warning`, два — `MOVE`. RAG:—
`BaseModel` uses `HasAuditLog` (Audit); `HasConfiguredId` и `IdColumns` тянут
Eloquent; `MoneyCast` реализует `CastsAttributes`; `DatabaseFeatureFlags` и
`Department` model — Eloquent. Эскиз не перечислял зоны Features, Departments,
Money.

## Решение

1. Для **размещения в зоне Foundation** условие 3 литмуса жёсткое: импорт
   `Illuminate\Database\Eloquent`, `Illuminate\Http` или
   `Illuminate\Foundation\Application` выводит тип из Foundation, даже если
   остальные пять пунктов истинны. Вердикт инвентаря P2.5 (`KEEP` + warning)
   этим не отменяется: это разные оси («удалять ли класс» vs «в какой зоне
   он живёт»).
2. К семи зонам эскиза добавляются три одноимённые: `Features`, `Departments`,
   `Money`. Направления как у `Settings`: только `Foundation` и
   `TenancyContracts`. Пакет из них не делается (D8).
3. Именованное исключение **E-BASEMODEL:** `Models/BaseModel.php` принадлежит
   Foundation с warning по условию 3 и **единственный** файл Foundation,
   которому разрешён импорт `CoreX\Concerns\HasAuditLog` (зона Audit). Обратное
   направление Audit → Foundation через `HasConfiguredId` разрешено матрицей.
   Цикл Foundation ⇄ Audit кроме этого файла запрещён.
4. `Departments` — кандидат уровня 1 (продуктовая оргструктура), не пакет 1.0.
   Условие возврата — в `roadmap.md` §7. `Money` — кандидат на пакет при
   потребителе, которому нужен `corex/core` без `brick/money`.

## Почему

Слепое применение P2.5 «один fail = оставить в Foundation» противоречило бы
эскизу и A29, который P6 обязан сделать исполняемым. Вынести BaseModel из
Foundation нельзя без смены семантики аудита (Scope Excluded). Три малых зоны
дешевле десяти пакетов и честнее, чем прятать Eloquent-возможности в Foundation.
Альтернатива «зона Persistence» отклонена: она плодит восьмую абстракцию ради
трёх файлов и всё равно требует исключения BaseModel → Audit.

## Consequences

P6.1 фиксирует десять зон плюс Composition и Testing. P6.3 кодирует условие 3
как fail-closed для Foundation, кроме E-BASEMODEL. P6.4 записывает кандидатов
в `roadmap.md` §7. Эскиз §3 принимается с этим расширением матрицы.
