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
      "stability": "experimental"
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
| `unreleased` | Пакет ещё не входит в релизный набор; публикация требует отдельного решения |

## Текущий состав

Публичность определяется source атрибутами; `extra.corex.stability` остаётся независимой
осью зрелости. BC gate включён; live CI и опубликованный релиз этим не заявляются.

| Путь | Composer name | Composer type | Package type | Stability |
|:--|:--|:--|:--|:--|
| `packages/access` | axiomasoft/corex-access | library | capability | stable |
| `packages/access-azguard` | axiomasoft/corex-access-azguard | library | integration | unreleased |
| `packages/core` | axiomasoft/corex | library | foundation | stable |
| `packages/custom-fields` | axiomasoft/corex-custom-fields | corex-module | capability | stable |
| `packages/intl` | axiomasoft/corex-intl | corex-module | capability | stable |
| `packages/media` | axiomasoft/corex-media | corex-module | capability | stable |
| `packages/notifications` | axiomasoft/corex-notifications | corex-module | capability | stable |
| `packages/operations` | axiomasoft/corex-operations | library | capability | stable |
| `packages/panels` | axiomasoft/corex-panels | corex-module | capability | stable |
| `packages/recurrence` | axiomasoft/corex-recurrence | library | capability | stable |
| `packages/search` | axiomasoft/corex-search | corex-module | capability | stable |
| `packages/search-intl` | axiomasoft/corex-search-intl | corex-module | integration | stable |
| `packages/search-meilisearch` | axiomasoft/corex-search-meilisearch | corex-module | integration | stable |
| `packages/tasks` | axiomasoft/corex-tasks | corex-module | capability | stable |
| `packages/tasks-workflow` | axiomasoft/corex-tasks-workflow | corex-module | integration | stable |
| `packages/tenancy` | axiomasoft/corex-tenancy | library | capability | stable |
| `packages/tools` | axiomasoft/corex-tools | library | capability | stable |
| `packages/transfer` | axiomasoft/corex-transfer | corex-module | capability | stable |
| `packages/wakeup` | axiomasoft/corex-wakeup | corex-module | capability | stable |
| `packages/webhooks` | axiomasoft/corex-webhooks | library | capability | stable |
| `packages/workflow` | axiomasoft/corex-workflow | corex-module | capability | stable |
| `products/flex-crm` | flex/crm | corex-module | product | experimental |

Source-derived surface: [public-api](../reference/public-api.md).
