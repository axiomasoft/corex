# Таксономия пакетов CoreX — блок `extra.corex`

**Версия:** 1.0.0 · **Статус:** нормативный · **Уровень:** 0 (`corex/*`)

Документ описывает архитектурные метаданные пакета в `composer.json` под ключом `extra.corex`.
Публичная поверхность пакета индексируется статьёй `API-001` конституции; граница пакетов —
`PKG-009`. Composer `type` и версия пакета этим блоком **не заменяются** (D11).

## Две ортогональные оси

У Composer и у CoreX есть оси с похожими словами; смешивать их — два ответа на один вопрос:

| Вопрос | Ось Composer | Ось CoreX |
|:--|:--|:--|
| Как пакет устанавливается и обнаруживается | `type` (`library`, `corex-module`) | — |
| Какая это ступень релиза | версия и суффикс, `minimum-stability` | — |
| Какую роль пакет играет в архитектуре уровня 0 | — | `extra.corex.package-type` |
| Насколько зрела публичная поверхность | — | `extra.corex.stability` |

Существующие значения composer `type` **не меняются**: их читает установка и discovery.

## Схема `extra.corex`

```json
{
  "extra": {
    "corex": {
      "schema-version": 1,
      "module": "CoreX\\Media\\MediaModule",
      "package-type": "capability",
      "stability": "experimental",
      "public-api": [
        "CoreX\\Media\\Contracts",
        "CoreX\\Media\\Data"
      ]
    }
  }
}
```

| Ключ | Обязателен | Тип | Значение |
|:--|:--|:--|:--|
| `schema-version` | да | целое | Версия контракта блока. Сегодня `1`. Неизвестная версия — явный отказ читателя. |
| `module` | нет | строка | Класс модуля. Отсутствие у немодульного пакета — не дефект. |
| `package-type` | да | строка из закрытого набора | Архитектурный вид. Не совпадает с composer `type`. |
| `stability` | да | строка из закрытого набора | Зрелость **публичной поверхности**, не релиз Composer. |
| `public-api` | да | массив строк | Публичные **неймспейсы** (не символы). `[]` — нет внешней поверхности. |

Неизвестные ключи внутри `corex` читатель обязан игнорировать, но обязан отличать
**отсутствие** блока от **невалидного** блока: первое — состояние, второе — ошибка.
Метаданные пакета — данные, а не полномочие: ни один ключ не разрешает поведение,
запрещённое конституцией.

### `package-type` — закрытый набор

| Значение | Что означает | Кому вправе быть нужен |
|:--|:--|:--|
| `foundation` | Примитивы и контракты, на которых стоят остальные пакеты | всем |
| `capability` | Самостоятельная предметная возможность уровня 0 | продукту и другим возможностям |
| `integration` | Адаптер между пакетами или к внешней системе | только своим хостам |
| `product` | Композиция уровня 2: продуктовая семантика | конечному приложению |
| `tooling` | Инструмент разработки, не runtime продукта | разработчику |
| `fixture` | Демо- или тестовый потребитель универсальности | приёмке |

### `stability` — закрытый набор

| Значение | Обещание потребителю |
|:--|:--|
| `stable` | Поверхность меняется по `api-policy.md`; ломающее — только в мажоре |
| `experimental` | Поверхность может измениться в миноре; изменение — в `CHANGELOG` пакета |
| `internal` | Пакет не для прямого потребления вне монорепозитория |
| `deprecated` | Только исправления; названа замена и версия удаления |

## Распределение по 20 живым пакетам

`stability` = `experimental` везде: релиз 1.0 не выпущен, BC-гейт ещё не включён.
Неймспейсы в `public-api` сверены с каталогами в `packages/<pkg>/src` на дату P1.2.

| Пакет | composer `name` | composer `type` | `package-type` | `module` (если есть) |
|:--|:--|:--|:--|:--|
| core | axiomasoft/corex | library | `foundation` | — |
| modules | axiomasoft/corex | library | `foundation` | — |
| tenancy | axiomasoft/corex-tenancy | library | `capability` | — |
| auth | axiomasoft/corex-access | library | `capability` | — |
| attributes | axiomasoft/corex-custom-fields | corex-module | `capability` | — |
| media | axiomasoft/corex-media | corex-module | `capability` | `MediaModule` |
| notifications | axiomasoft/corex-notifications | corex-module | `capability` | `NotificationsModule` |
| intl | axiomasoft/corex-intl | corex-module | `capability` | `IntlModule` |
| search | axiomasoft/corex-search | corex-module | `capability` | — |
| tasks | axiomasoft/corex-tasks | corex-module | `capability` | `TasksModule` |
| workflow | axiomasoft/corex-workflow | corex-module | `capability` | `WorkflowModule` |
| wakeup | axiomasoft/corex-wakeup | corex-module | `capability` | `WakeupModule` |
| transfer | axiomasoft/corex-transfer | corex-module | `capability` | — |
| recurrence | axiomasoft/corex-recurrence | library | `capability` | — |
| panels | axiomasoft/corex-panels | corex-module | `capability` | `PanelsModule` |
| api | axiomasoft/corex-operations | library | `capability` | — |
| search-intl | axiomasoft/corex-search-intl | corex-module | `integration` | — |
| search-meilisearch | axiomasoft/corex-search-meilisearch | corex-module | `integration` | — |
| tasks-workflow | axiomasoft/corex-tasks-workflow | corex-module | `integration` | `TaskWorkflowIntegration` |
| crm | flex/crm | corex-module | `product` | `CrmModule` |

**Почему `api` — `capability`, а не отдельный вид.** Вид `gateway` не вводится: ответственность
пакета замораживается в P13; расширение закрытого набора «на всякий случай» ломает набор.
Новый вид — вместе с повышением `schema-version`.

**Почему `recurrence` — `capability` при composer `type: library`.** Оси ортогональны:
`library` — способ установки Composer, `capability` — предметная роль в архитектуре.

**Тумбстоун.** `packages/transfer` метаданных не получает: нет `composer.json`
(PKG-009).
