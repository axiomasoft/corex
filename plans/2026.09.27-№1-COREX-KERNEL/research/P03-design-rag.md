# P03 — внешние факты (RAG)

Индекс captures: `artifacts/P03-design-rag/README.md`. Остальное — факты кода (`RAG:—`), их собирает облачная сессия
по `artifacts/P03-design/cloud-brief.md` (выход — `artifacts/P03-design/cloud/`).

| # | Факт | Статус | Источник | Влияние на дизайн |
|:--|:--|:--|:--|:--|
| E1 | Pest 5 arch покрывает правила «классы namespace → тип/наследование/использование» (`toBeInterfaces`, `toBeEnums`, `toOnlyBeUsedIn` с фиксированным namespace, wildcard `X\*\Y` только в `expect()`), но **не** выражает раскладку каталогов (разрешённые дочерние каталоги корня, «категория ровно на уровень ниже модуля») и связь «тот же модуль» между источником и потребителем. Для этих правил рекомендуется собственная проверка по дереву файлов и namespace; PHPArkitect — для широких политик зависимостей с baseline. | `RAG:✅` (context7 Pest 5.x docs + perplexity, 2 источника) | `context7-01-pest-arch.md`, `pplx-01-pest-arch-vs-layout-gate.md` | Гейт раскладки P03 — собственный скрипт (как `scripts/tenancy-structure-gate.py`), не Pest arch и не новая зависимость. Pest arch допустим для простых типовых правил (`*\Contracts` — интерфейсы, `*\Enums` — enum). |
| E2 | PHPStan native `@internal` работает по первому сегменту namespace — в монорепо с общим `CoreX\` не отличает пакеты и модули. | `RAG:✅` (переиспользовано) | `artifacts/research-rag/pplx-10-phpstan-internal-api-attributes.md` | Граница «чужой `Internal`» остаётся за `scripts/package-graph-gate.php` (R2); правило «`Internal` модуля — только внутри модуля/пакета» решается собственным гейтом P03. |

Внешних фактов больше не требуется: решения P03 (карта модулей, судьба «корзин» однофункциональных пакетов, место
Eloquent-моделей и Laravel-моста) — по фактическому коду.
