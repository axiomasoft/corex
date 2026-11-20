# CoreX 2.0 · Границы совместимости

Статус — принято для реализации. База независимого сравнения — `1c11e0e35961409631cf68ebb28c7d2cd53ebb91`. SHA фактически установленного 1.x артефакта и digest его поставки фиксируются отдельно перед переносом.

## Решение

CoreX 2.0 перевыпускает 20 пакетов на общей Composer-линии. Мосты Search Custom Fields и Tasks Workflow увеличивают состав до 22 пакетов в этапах 5 и 6. FlexCRM сохраняет собственный мажор. Публичный distribution fixture устанавливает выпущенную версию Tasks 2.x с PHP `^8.4`, Illuminate `^13.0` и `minimum-stability: stable`. CoreX устанавливает схемы версии 2 в чистой целевой базе через Packman. FlexCRM ведёт версию своего набора отдельно. Старые сообщения, данные и журнал читаются одноразовым инструментом переноса.

Следующие границы входят в CoreX 2.0. Названия строк отсылают к карте замен §11.4 и уточнениям §§7, 12–14 плана. При реализации этапа соответствующие конкретные изменения и тесты записываются в блок доказательств.

| Этап | Строки плана и изменение для потребителя | Проверка |
| --- | --- | --- |
| 0 | §§11.1, 11.3, 15.3 · Новые Composer constraints, branch aliases и разрешение path repositories. CoreX schema sets версии 2, с прежними IDs и scopes. | Детерминированная split-сборка, standalone consumers, реестр и установщик schema sets. |
| 1 | Scope, restorer, authorization и wakeup (§12.1, §§14.1–14.2) · `DataScopeFactory`, `WorkScopeRunner`, компактный scope v3, typed `DataScopeAuthorizer` и wakeup stamp. | A→B→A в одном worker, вложенные scope, fencing после rebind, отказы старого scope и legacy wakeup. |
| 2 | Messaging, Notifications и Workflow (§12.2, §§14.2–14.3) · Envelope v2, явная connection у Inbox/Outbox и Workflow capture, типизированные inbox/count/delivery outcomes. | Wire round-trip, rollback на connection владельца и повтор доставки при потере ответа. |
| 3 | Custom Fields (§13.1–13.2) · `FieldValues`, `FieldSchemas`, `FieldCatalogue` и `FieldFilter` с типизированными DTO. JSONB вместо EAV, события Fields v2. | Объявление внешнего типа, write/read/filter, effective revision и публикация события после commit. |
| 4–5 | Search и Search Intl (§§13.3–13.5) · Прикладной `Search::query()`, отдельные SPI projection, candidate и index, новая cursor grammar. Fields подключаются мостом. | Драйверы PostgreSQL и Meilisearch, ACL фильтрации и полей, смена поколения и независимый install Search. |
| 6 | Tasks, Recurring Tasks, Workflow (§14.3) · Typed capture admission и scoped receipts, `TaskPlanning` и `transition()`. Completion contributor живёт в Tasks Workflow. | Три установочных состава, первый Done, повторный Done после reopen, конфликт и rollback. |
| 7 | Access/Core, Media и Bulk (§§14.1, 14.4–14.5) · FQCN `PrincipalIdentity` и scanner tooling, Packman discovery, scoped lifecycle, `MediaAttachments`, durable `requestConversion()` и host SPI `BulkExecutor`. | Fencing и lifecycle blockers, readiness обязательных интеграций, standalone Operations и host-адаптер Import/Export. |
| 8 | Operations и Tools (§14.4) · `HttpOperationDispatcher`, REST v2 с типизированными metadata, host-mounted MCP на общем `OperationInvoker`. Старые HTTP маршруты и произвольный version matcher удаляются. | API v2 carriers, маршруты и MCP transport, replay authorization, Equipment и FlexCRM из split archives. |

## Потребители и внешние владельцы

Equipment Registry, публичный Laravel consumer, Octane, пять extension fixtures и `products/flex-crm` проверяются в репозитории. Расширения Custom Fields, Search, Transfer и Workflow переходят на PHP `^8.4` вместе с пакетами CoreX 2.0. Перед production cutover владелец релиза получает у операторов текущий inventory установок, `composer.lock`, зарегистрированные Laravel providers и манифесты Packman, а также используемые REST/MCP SDK и webhook schemas. Эти артефакты разрешают актуальные PHP bindings, зависимости и runtime endpoints.

Production FlexCRM и Equipment deployments, частные расширения для Search, Workflow, Custom Fields, Panels и Import/Export, внешние REST/MCP клиенты, webhook consumers и конфигурации реальных worker недоступны в базовом checkout. Их владельцы сверяют соответствующие API/SPI с установленной версией и повторяют сценарии со своими декларациями. Packman Panels подтверждает SavedViews, хост Tenancy — physical tenant binding, Vaulter — CAS blobs и pins, AzGuard — решения авторизации. Результаты входят в release evidence до production cutover.

## Доказательства конкретных изменений

```bash
python3 scripts/distribution/compare_contracts.py \
  --base 1c11e0e35961409631cf68ebb28c7d2cd53ebb91 \
  --candidate HEAD --decisions docs/architecture/corex-2.md --format json
scripts/php84 php scripts/php-api.php --strict --check
```

Diff содержит SHA и digest каждого прочитанного носителя, прежние и новые значения, категорию, затронутых потребителей и решение для каждого обязательного изменения. PHP route и message sources, а также config PHP проверяются по digest с приложением исходного diff. Provider/worker probes и behavioral tests подтверждают семантику затронутых веток. Проверка candidate PHP snapshot через установленный код запускается отдельно.

`changes` связывает решение с точными fingerprints отдельных изменений. `group` фиксирует количество и SHA-256 канонического JSON-массива отсортированных fingerprints всех breaking changes данной категории. Любой добавленный или изменённый fingerprint требует обновлённого решения и проверки. `plan` указывает строку плана, `tests` указывает проверку из candidate tree.

```corex-contract-decisions
[
  {
    "id": "composer-cohort-2",
    "plan": "§§11.1, 11.3, 15.3 — CoreX cohort and external consumer install requirements",
    "tests": [
      "scripts/distribution/tests/test_release_candidate.py",
      "scripts/distribution/tests/test_profile_proof.py"
    ],
    "group": {
      "category": "install-edge",
      "count": 79,
      "digest": "b85097337ea9b480b65c33eb943866ad63268a41bfef1eabb989bdf77f561e59"
    }
  },
  {
    "id": "composer-resolution-2",
    "plan": "§§11.1, 15.3 — branch aliases, path versions and public fixture stability",
    "tests": [
      "scripts/distribution/tests/test_release_candidate.py",
      "scripts/distribution/tests/test_profile_proof.py"
    ],
    "group": {
      "category": "install-metadata",
      "count": 62,
      "digest": "be38371b103787701d7799dd9b6294fa621bf32ab9a4ee637515e1ab9d8ce48d"
    }
  },
  {
    "id": "clean-schema-sets-2",
    "plan": "§§11.1, 16.1 — clean target installation and v1 source journal",
    "tests": [
      "tests/Feature/SchemaSetsTest.php",
      "tests/Feature/SchemaSetsPgTest.php",
      "tests/Golden/SchemaGoldenTest.php"
    ],
    "group": {
      "category": "schema-set",
      "count": 18,
      "digest": "f906dbb380693fe74ee0611efc05fb220a7a1b640dd13d25146f596b0644f2cd"
    }
  }
]
```
