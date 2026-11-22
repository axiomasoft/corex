# CoreX 2.0 · Границы совместимости

Статус — принято для реализации. База независимого сравнения — `1c11e0e35961409631cf68ebb28c7d2cd53ebb91`. SHA фактически установленного 1.x артефакта и digest его поставки фиксируются отдельно перед переносом.

## Решение

CoreX 2.0 перевыпускает 20 пакетов на общей Composer-линии. Мосты Search Custom Fields и Tasks Workflow увеличивают состав до 22 пакетов в этапах 5 и 6. FlexCRM сохраняет собственный мажор. Публичный distribution fixture устанавливает выпущенную версию Tasks 2.x с PHP `^8.4`, Illuminate `^13.0` и `minimum-stability: stable`. CoreX устанавливает в чистой целевой базе через Packman точные версии из `scripts/schema-sets.json`; tenant-наборы Core, Media, Operations и ImportExport используют версию 3 после этапа 7. FlexCRM ведёт версию своего набора отдельно. Старые сообщения, данные и журнал читаются одноразовым инструментом переноса.

Следующие границы входят в CoreX 2.0. Названия строк отсылают к карте замен §11.4 и уточнениям §§7, 12–14 плана. При реализации этапа соответствующие конкретные изменения и тесты записываются в блок доказательств.

| Этап | Строки плана и изменение для потребителя | Проверка |
| --- | --- | --- |
| 0 | §§11.1, 11.3, 15.3 · Новые Composer constraints, branch aliases и разрешение path repositories. CoreX schema sets версии 2, с прежними IDs и scopes. | Детерминированная split-сборка, standalone consumers, реестр и установщик schema sets. |
| 1 | §§12.1–12.3, 14.1–14.2 · `DataScopeFactory` выдаёт scope v3 от текущего principal. `StoredWorkRunner` сверяет proof владельца и immutable reference. Хост хранит допуск, tickets и использованные epochs вне tenant backup. `DataScopeAuthorizer` проверяет typed use. Scheduler сохраняет scope, версию обработчика и digest запроса. | `worker-scope` проверяет A→B→A, вложенные jobs, root sweep и очистку контекста. `binding-restore` проверяет rebind, restore и ожидание активных tickets. `workspace-sweep` проверяет W1/W2, рестарт после claim и rollback на просроченном token. |
| 2 | §§12.1–12.3, 13.5, 14.2–14.3 · Envelope v2 с подтверждённым происхождением, явная connection у Inbox/Outbox и Workflow capture. Notifications возвращает typed page и exact/unavailable count. Webhook replay имеет собственный effect key. Внешний control store хранит provider evidence. | Same-connection rollback и дедупликация на PostgreSQL, отзыв доступа к related record, потеря подтверждения у провайдера и сверка исходного effect key. |
| 3 | Custom Fields (§§13.1–13.2, 13.5) · `FieldValues`, `FieldSchemas`, `FieldCatalogue`, `FieldFilter` и версионированный `FieldType`. Слоты JSONB по FieldId и storage revision. Три факта Fields v2. | Установленный `vendor.type`, scoped write/read/filter, backfill с конкурентной записью, смена head и восстановление CIC на PostgreSQL. |
| 4 | Search и Search Intl (§§13.3–13.5) · Прикладной `Search::query()`, отдельные SPI projection, candidate и index, opaque cursor, SQL-указатель поколения. | Драйверы PostgreSQL и Meilisearch, отдельные ACL поля и способа использования, барьер H/H+1 и независимый install Search. |
| 5 | Search Custom Fields (§§13.1–13.5) · Мост связывает факты и effective schema полей с Search projection. | Установочные профили с мостом и автономный Search, локализация, изменение поля и смена mapping. |
| 6 | Tasks, Recurring Tasks, Workflow (§14.3) · Typed capture admission и scoped receipts, `TaskPlanning` и `transition()`. Completion contributor живёт в Tasks Workflow. | Три установочных состава, первый Done, повторный Done после reopen, конфликт и rollback. |
| 7 | Access/Core, Media и Bulk (§§14.1, 14.4–14.5) · FQCN `PrincipalIdentity` и scanner tooling, Packman discovery, scoped lifecycle, `MediaAttachments`, durable `requestConversion()` и host SPI `BulkExecutor`. | Fencing и lifecycle blockers, readiness обязательных интеграций, standalone Operations и host-адаптер Import/Export. |
| 8 | Operations и Tools (§14.4) · `HttpOperationDispatcher`, REST v2 с типизированными metadata, host-mounted MCP на общем `OperationInvoker`. Старые HTTP маршруты и произвольный version matcher удаляются. | API v2 carriers, маршруты и MCP transport, replay authorization, Equipment и FlexCRM из split archives. |

## Потребители и внешние владельцы

Equipment Registry, публичный Laravel consumer, Octane, пять extension fixtures и `products/flex-crm` проверяются в репозитории. Расширения Custom Fields, Search, Transfer и Workflow используют PHP `^8.4` вместе с пакетами CoreX 2.0.

При подключении внешних расширений их владельцы сверяют API/SPI с установленным составом пакетов и проверяют собственные декларации. Packman Panels подтверждает SavedViews, хост Tenancy — physical tenant binding, Vaulter — CAS blobs и pins, AzGuard — решения авторизации.

## Доказательства конкретных изменений

HTTP major 2 использует буквальный маршрут `/api/v2` и одну точку исполнения
`OperationInvoker` для HTTP и MCP. Исходное состояние разработки доступно в истории Git.
`api/v2` генерируется из установленного Packman-каталога и реестра Problem
Definitions. Одна исходная tenant receipt связывает результат и расчёт бюджета.
Повтор запроса проверяет текущие права и восстанавливает расчёт по тому же
`BudgetReceipt`. За повтор отдельный резерв не создаётся. Это правило действует
и при смене транспорта между REST и MCP, и при входе через Bulk gateway.
`ScopeContextMismatch` и `ScopeStale` являются отдельными final-исключениями.
Они сохраняют собственные коды и политику повторов. Обработчики восстановления,
которым нужны обе причины отказа, перечисляют оба типа явно.

Custom Fields регистрирует версии codec и определения через Tagged-провайдеры. Для concurrent DDL хост предоставляет через `AccountConnectionResolver` прямой PostgreSQL writer и управляемую схему. Worker сверяет `corex_fields_ddl_transport=direct-v1`, `corex_fields_ddl_namespace`, физический scope, исходную Laravel-декларацию, роль и фактическое подключение. Он удерживает session advisory lock на проверенном `Pdo\Pgsql` по PID и времени старта backend через выполнение CIC и запись результата под актуальным claim. При потере writer или неподтверждённом снятии блокировки worker удаляет недействительный handle из менеджера соединений. Обычные pooled connections проверяет штатное правило PHPStan Packman.

Стабильные коды Custom Fields сериализуются по грамматике закреплённого Packman. Именам плана `schema-revision-mismatch`, `field-type-unavailable`, `idempotency-conflict` и `claim-lost` в публичном каталоге соответствуют `custom-fields.schema_revision_mismatch`, `custom-fields.field_type_unavailable`, `custom-fields.idempotency_conflict` и `custom-fields.claim_lost`.

Search хранит активное поколение и верхнюю границу барьера в SQL. `corex-search.cluster` связывает поколение с backend, а `corex-search.retention_seconds` задаёт окно для возврата к сохранённому поколению. Хост Meilisearch разрешает credential по проверенному `IndexTarget` и роли запроса, записи или сверки. Search проверяет актуальный witness каждого поля, участвующего в поиске по тексту, перед выдачей результата.

`search-custom-fields` подключается параметром `corex-search-custom-fields.enabled`. Владелец Fields передаёт стабильные ID, допустимые операции и fingerprint effective schema. Search проверяет права на предикаты до обращения к backend. Смена schema head закрывает несовместимый mapping до доставки события. Потребитель факта v2 использует транзакцию Inbox и сохраняет порядковую запись для значения или заявку на новый mapping для схемы. Search публикует текущий mapping и подтверждает заявку в одной транзакции. Для каждой поисковой линии действует одна ожидающая заявка, а receipts сохраняют исходные ревизии доставленных фактов.

Search Intl связывает stable field ID и canonical locale с детерминированным backend key. Fingerprint включает форму поля и fallback policy. Допуск к предикату проверяет исходное поле и локализованную форму. В fixture сохранённого представления Packman компилирует фильтр, сортировку и проекцию по стабильному ID после смены имени. Композиция сверяет текущую ревизию и закрывает masked field.

Tasks удерживает блокировку строки во время первого завершения. Workflow записывает intent через ту же connection, а Tasks сохраняет квитанцию и статус задачи одной транзакцией. После reopen повторное завершение использует квитанцию по стабильному ключу и сохраняет один intent за жизнь задачи. `tasks-workflow` подключается отдельно и фиксирует версию определения. Recurring Tasks сохраняет материализацию серий со своим Packman module ID. Синхронный Workflow-переход вызывается через `transition()`.

При включении Tasks мост сохраняет решение внешнего `ModuleActivationGate` и проверяет trigger, definition и version через scoped `EngineAdapter::provider(DefinitionRequest)`. Неподдерживаемый pin оставляет модуль available и не создаёт Task, receipt или intent. Холодные config cache и compile проверяют декларации до разрешения tenant policy и engine. Декоратор сохраняется после сброса scoped host policy.

```bash
python3 scripts/distribution/compare_contracts.py \
  --base 1c11e0e35961409631cf68ebb28c7d2cd53ebb91 \
  --candidate HEAD --decisions docs/architecture/corex-2.md --format json
scripts/php84 php scripts/php-api.php --strict --check
```

Diff содержит SHA и digest каждого прочитанного носителя, прежние и новые значения, категорию, затронутых потребителей и решение для каждого обязательного изменения. PHP route и message sources, а также config PHP проверяются по digest с приложением исходного diff. Provider/worker probes и behavioral tests подтверждают семантику затронутых веток. Проверка candidate PHP snapshot через установленный код запускается отдельно.

Lifecycle использует публичные `LifecyclePlans::plan/inspect/apply` и typed plan digest. План хранит blockers, checkpoints и cleanup receipts, охватывает физические владельцы и workspace inventory и проверяет текущие полномочия оператора. Независимый control store закрывает запись для целевых модулей до повторного сканирования. `ReferenceAdmission` удерживает допуск до завершения настоящей внешней транзакции; ссылка, появившаяся после предварительного сканирования, не обходит fence. Повторный запуск продолжает сохранённый план, включая переход Packman и подтверждённую очистку внешних ресурсов.

Media разделяет `Files`, `Attachments` и `Conversions`. Запрос конверсии возвращает durable reference; чтение готового результата проверяет текущую область и receipt. Удаление таблиц модуля сохраняет обязательства освобождения blob и квитанции внешних эффектов. Scanner tooling находится в `Foundation/Tooling` и вызывается инструментами разработки; `PrincipalIdentity` находится в `CoreX\Access\Laravel`.

Operations публикует нейтральный `BulkExecutor::step` и opaque owner reference. Его runtime не зависит от ImportExport. Host подключает optional адаптер, а владелец импорта сохраняет стабильный step token и receipt вместе с изменением строки. Экспорт удерживает согласованный snapshot и sealed manifest; текущие права и `ExportSchemaWitness` проверяются перед известным результатом и чтением private parts. Отдельный бюджетный владелец подтверждает списание после завершения бизнес-транзакции.

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
    "changes": [
      "01477fbf06dc8ff1daf5dc82f26b791646651c14f4690119ad76881d3b073e9a",
      "041bf4d0b79f0f97ce81ad7562dbb30f6848ddf87ae877c4ffb623de09e48d93",
      "0afdad0ac66c57f90abb959c356fb4e9805c9c6d077390a488f79b5c7390c706",
      "0b47325b9e3f5d8f2bbdff0536eaeafd3a7bbd3f59230099245dcc2ce673f45a",
      "0c26ffb0e51772af10b38289fa5cf961f72f873e540c3a7e6038e6ab890ee7c5",
      "1099f46878b0fc57914efc0d90286b221735042f37401cb9d84c0fc2db96bc4a",
      "130350d46b5f3ab7df6767a0e0444fc20e6dc3c72b88a6ee2393245f2964a11e",
      "1567d7edc61ad34e4c822bc56209f234494e7c364f1e6c1237409defd9b0bbfe",
      "166911d0169b93dd531bb7818246218dd6c4af2bb5259f9b651f4df028dd1fdf",
      "186753df2c03d95667837f68b149a309bc4ca85416d420725f5b6af707cb2e81",
      "1f962a58c2558728f1675c5a3ef02cc9b0e08afd3b513f9e0ebde4b484d479ea",
      "1ffa75dcbc6baebb380562cef50506009fe98a89e28615fd05596f1a5b289458",
      "204bb1ac12239741d0257efc9e14c9cc874a01e65c5b23b6086686efe4023737",
      "2265ef4cc52b2724931b9a55e7f4a20b5cd14ffdce4cef0ebfa6929769d09632",
      "25bd5b562dbddbbba0baa2fed5654585c22f6a424b4b60009ffaaaaa7017c999",
      "260bd6db895bc975d9d6e810f7963cb1537b54aa1ee126aa9d0baf6290c2fd41",
      "263dffc64e56dd40351a2d45de6035f5e4216f950a0ae3a711c04196531b3368",
      "2873669b9960d1625763eda8f94afe4e4db02753709d1815cb2e737ba48b64b6",
      "28e622b321b8f71a3d27a64aea1ddc95574470a524d87087ee8a2b51299f68af",
      "2a6da9b40b116f8fc37bb4436e9c7f3674ebece95905caf48136e9a664518fc4",
      "2ed44fb214e3842dcc526ab082dee7e8eb7527316955c0467cc05f97a05feceb",
      "2ff8fbb08b7f4c51f3b94dfdcbfad3593661da255b092528e2433d547bed4752",
      "30855e50540b57ba44a323e6f2402e6704221d225235ec421df081d0d475c080",
      "3a3c3be833195b89fc8d903dabf75047718542ad39016b8ccd8fd080f426e928",
      "3c3690fa01891caea137e8338951ab0a2fdc35e3812e0c11faf487db3e2f6bab",
      "3ccbada12bc5bd56f28046256ab9e9a9b277b63b7e3513b0aee089366a98d3ba",
      "476ef00f7a81a55c9298ee9b4a4ca63f93bf048d146a8279d44f8323c2849b5f",
      "4f7b854a980ff3ec0702b718b6098362434b4aaa5eccab889b5a927fe23ab123",
      "50c4e46b0646e33606d65f34f0f279082e698e785f1c33c0d9100e547d1ba993",
      "59aeba5df3eeff70a5e5a5a4865de7f840d8ddcde4d4f4c93999f263c3f74545",
      "5a5a72d6f67ed365ae61c4b159005e9cd7460b90471721d6427a4b019f87646f",
      "615a10b2adb60a284f1f23566951b4cb243f582fd2e0f11c707426a98f50723f",
      "67220a5f63f389cacbeeff6ae726820f82d1c8585b1417e52d78b6d27edf5b42",
      "681dc64ddca7cc7c93368d095c67c33ae13efb288c54cd8c0f14c44cb866e14d",
      "706a713815dd5778d70ab559d874c108db02f0bbea8871879c43b968bebf01d5",
      "7f05df30eccde3bfe55e50f1bd5a135dd8014568868a5502724034bcc5f209a1",
      "81600197d7371326cf56a2dc3d3cd55dafb7522fd42a0d1a9f24e06a443bf870",
      "8323e349b2c13fa4296d44ab0fc8380edb572ef2bd0b6946251031a673562235",
      "84ceec0887b1c137a7de5c66888fd77d83590c0170d475e04d3a460681c82c37",
      "85ff9f9dab18c792142f0505f9564bd35d310982ef37410680bbe7a03f7e342d",
      "893b04474683b7927a6e5e3c8da5be16ef05180ef2de463c2444d6904001261a",
      "8c6f7865df4e578398d9a63f0999216f0406abfe90c344dc62b936628b3294db",
      "8c7bc3ea97c30da3b91a6c9ad17d81987b58fafef90d71aaab36033415f80e1a",
      "8cc5d62cf70960bab2576b550f38ff65696e91bbe75c4e8642bb422da9863141",
      "8e122a9b3e060be279b9c268cbcb10d9675edcf65c1779bac4d68207b7693058",
      "8f82e458ade050c72682ebe3073dadeb623a2d464f67e419812d036b7f3e52d2",
      "96de510fd66a9e98ab86b6e7902293dbe9ef70049e58c81caf28a4697da5a139",
      "9722035e2bdecf3a90662cf64e2bf4e7e05861698ec37b151dafb796bf637937",
      "a1d4073ab94c5788529d00f48098df4d50c4ebd87d35030b1bef790f4fedf60b",
      "a280111fe3969c70d0e9f935599e2f103f160548addc765e0de3a97ea790f298",
      "a5292b01b68f429200c4dabd752b0dfd69558c5c709a2dd31b914f52abd68bb5",
      "b12bf7bcf813ecc778bb9a590c0f700d6146a5e2cb8a8f66840eae3f7362ef2d",
      "b1a1923cba271978edd512b1a6e9aac057a9c621d5c30a31fe6cd9702d7191a7",
      "b1deadefd96ea10c1c213471707f39aabd5d18e1f3f9e65ca5c5598bd62fe846",
      "b631fae64ca23b07e9e2ef4cef2c8dc01ccc367d6095f7f3f93ce16266424f28",
      "b7a0e373880258d8d799689af422617ea43855695b7af2dabab424c749fbe2e3",
      "b875f0d2f8777ee8df67258f1a1754cde1a73ccfbe3185d57c0c03aa1b937c9a",
      "bcd0b067f87e91091b7d5fdadc8f83ef2381a9c7ef1ea8098c104f5a2018bc64",
      "beb3956b03e601b1814341ecf7b8ac4f527a9bdba88427728bc08e4c557e0beb",
      "c0167cd6e6e42202ca7479068bd433e6a061fa55ae7a569b7ef38c609f34064f",
      "c1ff69d2e7540c116f13246e3fb264a3c729cbbad12b6ead75f1b064033f8409",
      "ce0f765469444d1c34d6b73df003cb9c17e916b529d963e21c3e3ae34d511fd1",
      "cf15b696879a5c83510f5aae6f9fb0338ce5fab5e431327891cd23cce890c3a6",
      "d1bc6685012343a6d5ef5225e3b47b9c60976ce38208093e1ce1892e04e5a334",
      "d61b0b567ddec31e1d734d78b7ef9194c097535db27e8e08a1edcd22db0514a4",
      "de1145de3c684d2bdc97773e09e5c30b362ca7ad9671c6f1300e748fa763838a",
      "dfca441c9bdfe7f5345d76b8e2301bb37383961dd516792de86181fa047d01a4",
      "e1148078432f9a90c57ffa35975422f91d25d16bd5397c740c388e8f3b0f39f1",
      "e1bd63d51fd1e842db652f2622c0f964eadfac329862dd787b5fa6627dbc5ed0",
      "e1d5c9995451868a0fd0a40d74130d99a6316cefbccf98818eea84ee946d1012",
      "e551d038bd1e454e3070a42e674cb9a4df43d5c8ab7e9f2c2f68a1ed1c6ccff9",
      "e6985cf62bdc660a1cf159cc34fa567bae81db3ef3349d8d7f6a6dc69f57bf48",
      "e7e46ede49aed03f4baaa19f82379f89856528517512b1783713719755f91322",
      "f352074c32645f5181984f1b0e5deddc1de16a3d168c6cbc68fd896ae8261c87",
      "f5f43f9b6f99fbff895f2dcb2bad1e755dfd243adf5b86f4c53c970352319a2b",
      "f9439ccc7808b8f9aa348c96843af66442d46bd67cf3939d4ddbef0ec7d07f6c",
      "fb9c1ddfce67e08826e59785beac81d47137bfac5d8adc905a7c0c1953173890"
    ]
  },
  {
    "id": "composer-resolution-2",
    "plan": "§§11.1, 15.3 — branch aliases, path versions and public fixture stability",
    "tests": [
      "scripts/distribution/tests/test_release_candidate.py",
      "scripts/distribution/tests/test_profile_proof.py"
    ],
    "changes": [
      "017176ff43a823b365b2fc63f2165783bb60b8f2ca0def070a5231b8243dd0ba",
      "01774c8e94891fdfd242618278507e6d38a5f3d9c26f7c328c3a7c1780f0ebaa",
      "0933fcf81fb9a28722043af136c90157909b41810d42a2186e8c3ca0bdb6a75e",
      "09b188ace5a4fb7661e814e7794bde1ecada1c351ee34db1a8406cb41848ddbc",
      "0a0a234e77bc0c2805414500785d7a8fe1c8e076cf28dc131a9c74e4fe16b8b4",
      "0b76084a21a7f7a3419222c8ffb89cc5dde7d99f19c149471fc62a97e5e9cf1a",
      "1a754aef283821112c7a9e46e4baef7618f9d26f0248228e85231ad671e40446",
      "265fd44189bf9e57ff3fc93bf1f763fe549f3004cb7d43bf3207f43a39eed9b7",
      "2774bbb6df5ece7e2c1172656a9c18cb13ff87e2974806622d1a2f9dba70919f",
      "34b1b0e09cd9c9c42572acbc9e8f41868e9d7275cd5a0a1b951b58be50c4787f",
      "37947221f96a699ce14424527190553019948c4cc8af636546e55294044ea7d8",
      "3a77e8c6be161a138d89d66fa93f157f68d894fcb6df2f3cb6588c3692b9a7fc",
      "3aa738753ea8d1b715baeb1fa66f639be2437e62fc65b244a051ea3b77eb8ae1",
      "3d99abef9c9813455882024006c1f60de1425b399182a9e91597ce2721fb6f9e",
      "447bc079100dbeeadc9829d4800e1aaf99fd41c2dccc6cf5a6bd14e67214fcb8",
      "44f06e25b49b0c083c31bef8b125582224ab7f472e6fcee259e3a4d1f0c25711",
      "4850afebc0c9bfec009c3d0c26a65f89aa6e13d15e545a3190e1fc3e43ebd018",
      "541cae13662906f48480972b8f74a66fa7d5743157cca71272cf6048b4b5885a",
      "54b60bd422de1c84ecc4c6380df369ce36cd7a909247672c116d64af7b02ad57",
      "592bfc8edcb165a3c60c5134dabd0d2d83ecca74fe3fcc45d03f68ff9eace803",
      "5fcb7822dcce05de50013b1b61529d97311e255d50cb9071835c5795aaf7fbd0",
      "674e5c09c1ea9ba2974b50e3ee29282e33fd1f7b0e9edf9209ad430a6fd78e2b",
      "6fa16b452501a2d3ca2550d10bdf113cd810a7126d10447028b7357d849ddbb0",
      "7322cae96979d356a1a525ebacbd86bed97d5adf82dacd6f69d9e3c06476c656",
      "7612d5d921e903152414caf4222843fb91495fbd93040d1e0f7c041c22050568",
      "782a746301fd5f1004539b8a470d1fe0e34ac350a9f4525c1470814c850979aa",
      "79d1eef147227ec23696dab518b40f61fec3bf66ad760a45de0feabd51fa56c1",
      "829f0e204b10d83ff5931dfb1c7aec545b03c96b67db85832297c374fb8dc24e",
      "8a85d4b172c0bf8be311ce852d8f897b24f67de7a5acc0a7f90bbcbffc712b15",
      "8a8bf79c60e314d6cd07aa9dc659c11163f18026ed2747470ff348c30e3ec76a",
      "8b07fbbedba427489c52227a440288a24d807688aa11c4c9186805ec83b7a266",
      "8c776918e05bb61155dd95ae3f09d8da60af9d768ab9cd0b4c7bce4946e598a7",
      "937026b4d085312ae864e10fa73b7200e5d0d0cd57db9411b65ce9e8af81a9ec",
      "95a321e053553ca56e872d6f8637fe01e38357044832770a0074f3d26e6acbcd",
      "974a6e452b78829a10d5565e1c7634be6c0bc6e77b95ed098c1a8f74b7093c1e",
      "975bd147a762bd45a27e731f02ab60c00b4a725d9ebe4feecbd75cf6778add2e",
      "9872a7588221b7828ae3e9769ee263f7d95741e51a220cf2d9b579ce6cccd9c7",
      "9ab70eef57e5077c0174c71adcec83fd042912e639e6f4b2f0c22dfcbd97b77d",
      "9e7448e7d3d01ceb847c94b2aef82ed6d703e6fdf639435a6e9ace4eaa93c033",
      "a2fd6316278e9d79da42c704b2ad39eafd30136cfc395469131176d8d93fa782",
      "a863fe93e7a6e9ad396534acd5010ceedda04d64d098a1e301ddf4ba7ee5bbc5",
      "a8a75eb065076148b2130da7a431ac4e74701d67bc4fc866175be62ac006d14d",
      "b6f1b5d4a484602ebf304b1146f7ac4b799a63a3d79eea96d9d30d88180834d7",
      "bb0a00947c92118b81b575b89b3cd3f6271b699609e90d0dc2ae05f7892f0897",
      "c00f08ae238281c875c8a149f5402f05ce8e272360229ac3c88e974254ff8ff6",
      "c210e1a9cd09b649bc46a552054057017d5d7e5d5e23341354e7a89f88bbb603",
      "c2d69097e282659e4846f41440529fae1aef0cbfbed5ecc024b4a5e61fc7edd0",
      "c7e293080e136575e7a76deb0d767790349bbdc68253a35a6df53b59441fa663",
      "ccb1d1db096848d642c74475cd41444febb519ca73e17d3416145ba690f8a614",
      "cd33bf4ba28f7744fdedd4fe4469c0790ef0382d65a79edab1e6d6a82efb1459",
      "d6773e9f1124847883d3b306c2eb3aead8ddc6c93656ed7e3390ea8bf1a37c46",
      "d7e10495157c39d2f6e3a2f95e30e18c8777bcca881cef2599d42dab8d522453",
      "dbb88b1c75b5858e7f6d1943bdc00d7be8f732775f772d3210807fb4b9220b4d",
      "e5bf976ab5456ecb3468ffcb5f378761bf284ee7bbda30ce5d5cb3d1419e1f0c",
      "e8d21f0543a5e0bd20a443d96c28afa1c5e29a0944d5798c9b9a84fef7a54e36",
      "eba7d2052c2cd7607e64f60d718d11859607bd0660195f7df3a139a4d9475ee7",
      "ecbef85348943a5d2d075e83442e49fb60643d75366da7b84a196578a722fe6a",
      "f0baa166bbc8d0d41cca9564afb874f481454cbc88615041e80eb9206421eb97",
      "f0ca85ab7e1b612ef110978b20d6e6e754e03cc2003836c8ec842bdde0cf5e88"
    ]
  },
  {
    "id": "clean-schema-sets-2",
    "plan": "§§11.1, 14.4–14.5, 16.1 — clean target schema sets; Core/Media/Operations/ImportExport tenant v3 retain lifecycle, conversion and transfer evidence; dedicated budget v2",
    "tests": [
      "tests/Feature/SchemaSetsTest.php",
      "tests/Feature/SchemaSetsPgTest.php",
      "tests/Golden/SchemaGoldenTest.php",
      "packages/core/tests/Modules/Feature/PackmanLifecyclePgTest.php",
      "packages/import-export/tests/Pg/ReleaseResumeTest.php",
      "tests/Distribution/fixtures/scenarios/owner-effects/probe.php"
    ],
    "group": {
      "category": "schema-set",
      "count": 18,
      "digest": "e7046df263092ed6b3d4466c0a09f9e727116361edd45b5f53d777bb26017378"
    }
  },
  {
    "id": "trusted-work-v3",
    "plan": "§§11.4, 12.1, 14.1 — DataScope v3, host binding admission and declared queue work",
    "tests": [
      "packages/core/tests/Unit/DataScopeCodecTest.php",
      "packages/core/tests/Unit/WorkScopeRunnerTest.php",
      "packages/core/tests/Release/OctaneLifecycleTest.php",
      "tests/Distribution/fixtures/scenarios/worker-scope/probe.php",
      "tests/Distribution/fixtures/scenarios/binding-restore/probe.php"
    ],
    "changes": [
      "11f6f0fd679f85d0f377ee42108f45f00621f864c568ddb774319a00937c6d97",
      "19e1cef2dc59a9ac7b44894ec2aab96225af099293bb56bfa64b1abca59742b1",
      "209e15eb2a1de8f5da1e70270171072a1b8d5a1a8b8408e37b319da8e3cd0d8f",
      "37534df398a59bf7124ef4d37f6d5eff5d20a107eeaa311e7974a87670610f82",
      "3a32901b71bb4c2e41663cb83362b04793ac8caafb545d134a55f613ef74202a",
      "3e8485604de972e6d09c885c50616c6185750004c4aa47c215ab90c45ab0f108",
      "3f8d78805073163d052d468f215d8b7ab7eef14fd50fb92d324dd4c56f475a58",
      "4cc1450ec5347e52cd40dea150f10585f94fa1cf5320db290ddc997b6805d5b0",
      "4dbaa4be72e0b0901090239e6e2c3eff4d3506c1961e4a5820ee5d3c4d8d8f5d",
      "59256f288ef24daeb389cd10bc3dc70929ea31a1db7704729db1a3cca7013aa6",
      "64d4963a463380eb23958a3c3e0fac46e9198b1747fa5a91fc773f1700cc3210",
      "6f6f6596905cf5ba70d3f6b8ee69e262f152d2bdf0fbd6bbe9815510a26256ff",
      "7c592cba768bb583614c1ffc504a803f50c133574df617b92be84db551010735",
      "7c9687b8b983ecf7309ae6c8b68e61ef62ca7898b54c65b21e8635508c5a04d4",
      "888f0fe72d66cf69af2b87de69d7a746749540450542f4bf58ad980e003bae54",
      "88a72563ca99bcb0887628000b1e822df48581383f3ab46cb65532b3109e99ab",
      "8d9d09f868a128a3efbac0c6e098a6a88457abc2b96e60503df3d1523c467902",
      "937ee9b41c44e69ba07136677b6eb90b5b32afe52f00448119159241ba885936",
      "ac335531cfd0e04bd40ea3955d4df82ddf2fcddd3b43c741f2ba4dcedbd0cf76",
      "bf5acae3357ae10a60491eb2323ab048b4a50a2cb9108223dd341b004e8c7104",
      "c8ade1f8f88b417f9c996613703831b3b31059e352e2631b1d9fcf01254d93fc",
      "c92926cefaf7a2c41e24d9bd09c2242673aa897b1f67ce3e9fde684f9da64c6a",
      "db68f062be9bc9a2171d8b5d2485e878ba47ad58cc824b9f57418b789add1056",
      "e5bdc770b1e32c1aed43ce85d2b92d4cafd34ad7c3359a2ee2149055aadcee23",
      "e67b94654acd19c602944feff9c03d1465639abb8b5068f512f3cc940faecb60",
      "ea66bf8e409829e62e77569963469ddf694f776ae18ccdc8372393f5b3ac8886",
      "ee0cd018b1477589390068dff83f917ccb4fdad94d7f506f7fcbcb80d3b67495",
      "f75908b9443d7fea769cb4fb4f30cb16ab66b983d704a320e48bfd71336b4eb4",
      "fda83d978b1786ba4a0949a67de58308be6d2cceffc62455c78a9b600036d5c7"
    ]
  },
  {
    "id": "verified-field-use",
    "plan": "§§11.4, 12.3, 14.1 — checked scope, field selection and use at owners",
    "tests": [
      "packages/custom-fields/tests/Pg/AttributeWriteAuthorizationTest.php",
      "packages/search/tests/Unit/PolicySearchHydratorTest.php",
      "tests/Distribution/fixtures/scenarios/worker-scope/probe.php"
    ],
    "changes": [
      "13cf04003ddc521b562ba5525b2e9960b031d6976ca3154842b9e313cd309724",
      "382f4b68cc46fae0cbe80875af622ad1a039ef39564911b97a77d1832a174ed1",
      "5b3e88417d9c36026df48ed0366ae29de50cc45f8875146585cf90ab144fc489",
      "6357b84f14bd7d630d21668f99e2b9b6d189916f404264cbd0c9567c79b10178",
      "8a5864442e4e6294f875b559a74fbea6ef03ea3a663b1a5e1e825bc303356b0e",
      "8cf1cb3dc0ba637e512acc5c3ac735b9c200075452a00379ba8afcffc1d11048",
      "9c2b56ff186608c693d3a5344bb1d58cb1cb18eee02754ca7a92a113db838ced",
      "c4451213b09f3af3d9e7de7401e78f5920a3c9a1f9283e312c6ed7027a94d0e5",
      "f8ce0f1c705ceb7cd05cdf9f2f7aa3231ea7b98df713ad98fba322f93dc72011"
    ]
  },
  {
    "id": "scoped-wakeup-admission",
    "plan": "§§11.4, 12.1–12.2, 14.2 — scoped Scheduler inputs, canonical window and stored fresh stamps",
    "tests": [
      "packages/scheduler/tests/Feature/WakeupSchedulerTest.php",
      "packages/scheduler/tests/Pg/WakeupClaimTest.php",
      "packages/tenancy/tests/Pg/WakeupRoutingTest.php",
      "tests/Distribution/fixtures/scenarios/binding-restore/probe.php",
      "tests/Distribution/fixtures/scenarios/workspace-sweep/probe.php"
    ],
    "changes": [
      "085641c665c9480c23b47c589ae47c10186b9a05d9c9f3058290a0e36b53267b",
      "102899e0b67621fe2b1bc026ba09463960fa55796dece5a58d161a9c7cd66d0b",
      "15938712450f0b7d0aa661638798e75bc6abeabaa9d6208faa4b7f23695eeed5",
      "17c558e16b9ae8933f6d5a7ebdbd9ff6f23e35242920c071c86698938ff4136e",
      "1cd039b9c102212dc79c9b2e5e852be6cf764bbc121b2ad2d52d10753e73ca3a",
      "2010b4af8980e09691f56615583c98c5570fff605364c51a698732b3518d9d54",
      "22f0993dc71f89f3f1aa8764ceb46faba256d8d6f1a6b43d4321a7dba13fe4bd",
      "2437a5aae1c7f9627b2c148a32b9c9e08c5a8b319b5c3c8620ed7d7874b426c0",
      "276b765b8a690784f467b563d5cca58a94df87cdec73c28cc572ca39bc9bec32",
      "3952f13a3603b6802e13ca5307c6a3594d111d1ee23f49c40b6a72663605f078",
      "41ddcb15817094dac21b0ccaeb4537a3f4400d35ee136e59d309f1dcf743d0c7",
      "4892b797d5f7231364a629dc75bc1ed248d1cf3283d573ea1915fe241c23930d",
      "5df6c3f50d9fd758691a99647b09aa56eeacbea2c53ac3090a6b7d35c472b4f4",
      "6027ca3de226f210981bbb186bd8cdbd1f4bb18dc621619f967a69c545c2af99",
      "634b1980f8865d9ed7dcbfc6d71e424c65a12197e744fea257f15888c125bd64",
      "6dc0a30dcaea4367bcb6128fd11ab1c462d66e768b616740f58fd9b2511dc937",
      "a72bad68a7bc98c42aa1cbf28179562c50e169e7575841bab293c8d1fa8b358c",
      "ab7dab9f84af9bc9ae5e0a5711528032658eaefff206a8e722729f3a581ec843",
      "c09644bb396e200065535c31e4121b43d927279430c372b06f0ef082b4d7283a",
      "d04f4d37cfbb58796d113e9ae4eb26d41e2ba669a0c47350a95d7825151e69a1",
      "da3e2bc4550bb443b385412f74352a7abfbca70aa33e628afd43200c7856b9e8",
      "dadd990fef11900be5008d35cf1647d32d2ac2dd585b73ab557adcd29bdd7d24",
      "de5d08bcd45bc8f66ce8de73056f202f87c0c48ec3a880720874c78e5f5bb08e",
      "e71f9bfe4d0b177524e615cf07c045e811186a8340ef4575a15c7964954b3c01",
      "ef0b4f059e31061c01f3d1f3508995b23b48d9c91a7c807911db1c8f10253f5b",
      "ff7507b86c54a63b4ee0c01ae40889beb16aff69b2ec2e4759d3d5aa8300c0c4"
    ]
  },
  {
    "id": "sealed-transactional-facts",
    "plan": "§§12.1–12.3, 13.5, 14.5 — sealed Envelope v2, scoped receipt identity and owner transaction",
    "tests": [
      "packages/core/tests/Unit/CloudEventCodecTest.php",
      "packages/core/tests/Pg/OutboxAppendPgTest.php",
      "packages/core/tests/Pg/OutboxRelayTest.php",
      "packages/core/tests/Pg/DeliveryGuaranteesTest.php",
      "packages/core/tests/Pg/ProviderEffectLedgerTest.php"
    ],
    "changes": [
      "04b636d75eb906fdeaf3877f1f1aef78fc4ff69ccbfea91eccc59f5b9c356431",
      "109b69610171d061e6baba9e5343abb4f9254fd14889a994592432929aeabca8",
      "19abe485864d1e6f5593dac79c88fee30c35a10db5928715b82055fe99d82e90",
      "1ba697225030b1493476f9472c034d562d41ec9efcc21b988edb9f81040bfd44",
      "279eadb720a4a5cceaf9aae38a266283c18aff5071bde8aa2e06e3c01b2a6803",
      "2c2baf0526add5da33a34821e0caab46d25d428629088161e7ef0a53523f9c41",
      "3043b5406f6c5b83281625421659420496ab87faa2e7264c316fc5d0ecde6edf",
      "30f4e569777a3910887f52d2e0f4ed53b5cce97ea41d9cc4989c1fc52878b35a",
      "35214ef5820b064d54bfa37f488ea97d421a0736b19e7170bb760466c0bc569a",
      "3f5f5c509d3f70967f753c50fa40f1ce2cc3a009f8ab32fa8f44e5a99fe6303b",
      "438cd7862f9ba75a1f539d597dce303f13f9ba153206666c2b3fb4047defdb2f",
      "46a7a76e01a7b3abf5231cb8ce7befc62898277b3a2cd7c055f7f64592200c6a",
      "48e5e4040bb46c2b96f2516ea15b9f668345f1b53b6a56876a4e4fe9bb47033b",
      "53887d13d5d071357dd097d37343d98d4b79308ecbf9c23362a3f96eb47a6b22",
      "58e6b510a543af14c4c38cd099f7e414f85d4bc3efce0c5212f813347a6b4f24",
      "597389c8f2e49fc8935e6d63c49362d90168bf25e7dfa3dccc7107d0b9feff80",
      "5b362e847a80e390d66d371df7414a43cc9a7865029e00743b41f0670c2495f0",
      "5f3962835a17171fd2e9b554e20e56d61101c0e1e0dc9cfb401201bedb856d94",
      "617510e1c1037c370ba3a1c78f18f6e93593cd7d019b9f1e378eb3cfaa475a34",
      "6339e21275079ec2f6a107ae39f52e81c44022e245951106d5bcd830d3bb4603",
      "7e60ee1bddaf777216519b1725c9993d2b4109f113128e1f46750f956fddeb89",
      "7ef42232d71f5fef4c89b4cabd4ca714d616e944f67b264c747969520279b3d3",
      "8310490ca4b98d9eaaec697b4fda3474558b921e85b8ab686f6fd28d9eb811e2",
      "87f5c2cd638373a73351598f14350a88a5c98cda65684572e81948db214c81f1",
      "8ab1b374c45d92c44133992e98fdcf371bc16c4948a719efc4a6488f35ba714b",
      "8b3699827b9f3845a8cb2ce2958c23b20f0451efa1d335738f3d14434f261bb9",
      "8cb5818b59f8fbec4519133b6b2c0d09293ddbea21439cffaa09761c64caa48f",
      "8e68663a84c258e88c52a30a2cecbb2d3b53b01b6a7537b8e9e505b34dc447e5",
      "9cc9fdc323af596bc53ffdeb025bc4720ff3ecdb21b5f783199ca1e3380af0a8",
      "9dcc94ffdf021a0d8b08286adda30ab2ab712386aa8fb01a90873cb435115013",
      "a558f21cb87dc224a51c34dd907bbe4bdcf87df2760511578b7d444cf35a6429",
      "a912180fb2785a178350ddcb422b21dc1d42eb2b88ee1423ad333ed73ac4b897",
      "ada831b019e25d035837e4e22998a3857412d9496afcf49dcd1aaba5193964cf",
      "adc9d5337061b6895ccc96653869ec241f1b9f8e1372df62fec251ce16361a60",
      "af046fb28edd89384adb58f13d5aefe6c6decf7154a5b388d0484eef58916a3f",
      "b1b4a13a74e9e435fe646fa0208950adc27b7153a88c46120e17c8fcea6598f5",
      "b22f3701bd54a3e5afbb998828997ab6e961b8afe8efd508a379119cb6b441b5",
      "b33c7aef732d14c19d1891dc9169d58dda7703dfda44ea03c40c78ca9c84bdd4",
      "b9449491a40e1b9c91d7ae1b106d61119a149cff474bcb72fc82d4f2e2a4fc8b",
      "c62addbb2a39a5e75c0e2bae89e0ddec1181623046afe6dce550f56749a5250b",
      "c6dfe09a4eb2aaf187d4fdc24e5786d65f2234256a0178dbfde891c895a8183e",
      "cc6a4cf4b49f309016616a8506a61ac57530563e03aa1699c8cb61c1c8ba69e4",
      "d312b230492c59feb8c08edaa0fa7fdcc254b251247db637036f1b5c5a2e1730",
      "d555641631620d2d1304384a3209bcf9eeab4bca83c3ede90ded45bcde44f204",
      "d685c5c96e9e13852b6c4883c76dbb5c922b124119db440db2a1a60038af618a",
      "d9c667d666ad9cb8aa4ee19313d7af8aa09643b986a33910e57176d747afce04",
      "def1161b265b552099865006decf0f00b76e6bd742a424fb4004ac9e1a579a37",
      "df2d4cd4c62778eacdf6af1921de8495cfa27b6e16776d0d4a2887f657e07b28",
      "e03cfcab91a99702ca29dcb2f87299fa2e2c04a9f1defdd3c05aca953849c1d6",
      "f41452798c9ce99e458e0400a47603b7f3598ff0e329bd91308ac3c1df2bdf40",
      "f7eda4dcc7ff2dc3f46135f55674562bf4a66c6fe15210bf64d78fda72a12e3e",
      "f82a4f0295b61ce7b0744fbb9c54d3da395542fe861c95049e954258bef2e552",
      "fb6d92de3cbd1d6931cbe1211a1a49b52c6eccf45149d318b2cfec0d2a9840d2",
      "fef36df5ba5959d1e4bfa3e58d467e0b43e5490905f2c94cd494ed09d657aa67"
    ]
  },
  {
    "id": "notification-receipt-visibility",
    "plan": "§§12.2–12.3, 13.5, 14.2 — common visibility, typed counts and provider reconciliation",
    "tests": [
      "packages/notifications/tests/Pg/DeliveryAdmissionTest.php",
      "tests/Distribution/fixtures/scenarios/notification-visibility/probe.php",
      "tests/Distribution/fixtures/scenarios/provider-unknown/probe.php"
    ],
    "changes": [
      "32c2d01ecb0cf733d8c32bea3240ba07854cbdc998da2ac3cc9ca879bd95b745",
      "5d66b16e55ccc88720433892020620fb47ff34e9aa06dcf7f38794345ffc6546",
      "697b0318e494e5991522cba8d0a9e7129ba8d0a4799443e8d8532187065ae9d8",
      "7c245d919515825f237b3b7c082cfb11d328f3bb776671e3528fb5eff8b7909a",
      "97980537233fdd3853ea40e20d788c99f7ce07beacd4f941c3a720fc147a48b2",
      "d5a3aad7a2ab8c5f899493661a0c1370f6d6f028ff8ac73b5a292d75516633e2",
      "e5775e66617b94af67f3e80257546658d3ceead9514535a161cb6ffc17c08fc4",
      "eb2f70419c32383cdc139cc8e1f95b3ce9f5b560c4def0d8a05b586bed593b45",
      "f0dd2d20ea25e76c2f7f1fd9e3ef3dac66bd88ecc4435d2e571bf4d32876650d",
      "fe0768cfc057d9079e6b3a513177cc12223bb4620bff06fc20b38915543bbacf"
    ]
  },
  {
    "id": "scoped-operation-receipts",
    "plan": "§§12.2, 13.5 — scoped replay authorization and recovery of the isolated budget settlement",
    "tests": [
      "packages/operations/tests/Feature/IdempotentInvocationTest.php",
      "packages/operations/tests/Feature/CostInvocationTest.php"
    ],
    "changes": [
      "026518175c7d458402834b70bc1f1b348e7e08a94e92fde61bdc7ae8d2512736",
      "0269f5a58a73d6d955f40b146fdf27152191a8d4a159de99d82f36ef753801e0",
      "0d45d1da35a9dceb55f085e8b40d5e1c656dc29b642d2385c0f7c9a848e99cdc",
      "0d728f13803178c21b4bf226df95c10426f996af869d412cc3fa199038ef89a1",
      "0d747df0e959f34cebb58098e0914fbdea93270ddf80b938c7386448da978731",
      "118cba7d5ab3efe5395195f7bea9aacbb8eca432820fcf71f824108717853c08",
      "221e7227c71926672ff00e4afd3fd82472e0500602d17b08e04465eea629f279",
      "6a74102ec11508fa21a2bbe2dc06bd71c4feb0cab4661d9ab22f6e231cdd95e8",
      "9a484169cf7c1f78fe8907cf86276d977e20e765b717d381e9f54d559729093e",
      "9b0f270938d919bfd370ae5823de7d936f3d19bbc60c94434b405e9c81babf5b",
      "aa0125d00b45913c9ecb78c2fc615dcaacaf1e369775cdc6942a7bd4d58e09ad",
      "c5b8cfae91293319b865b1696f1414104cdc48865ceab202bca05acb98c1cc2e",
      "d046728a46e8014282bb551e7ffbc2cc97d258a7eec50e8ab109c2eede097616",
      "e0dc3f6bef977944e228bfb0cc904852fa93f7bc36a0c1e52dd9d2985ecdad4a"
    ]
  },
  {
    "id": "workflow-capture-and-execution",
    "plan": "§§12.2–12.3, 13.5, 14.3 — same-connection capture, scoped engine receipts and explicit owned namespaces",
    "tests": [
      "packages/workflow/tests/Feature/WorkflowBindingTest.php",
      "packages/workflow/tests/Feature/WorkflowExecutionTest.php",
      "packages/tasks-workflow/tests/Feature/TaskCompletionCaptureTest.php",
      "tests/Distribution/fixtures/scenarios/provider-unknown/probe.php"
    ],
    "changes": [
      "02642169851c6cf38d19f90427b672fb665b9b9d01de789df074b3a12edb4861",
      "2847ee2e40634ca921dac678368dfc58ff5fd00b146e8bdce105c9d1150a0c8b",
      "360a8e8c8e2648a27d0c218a3c98638ce782dfe8936cf3aea22cb7b6751985e4",
      "377967f33a89ffbbc54ed95b7b35e84a7510a5b768746115f5ab5a49b1dc675a",
      "44f895d99aa28cae2f7017df7a3b14f5f8968b10bf855badf4aab3d83a1ffe44",
      "4bf855afcfd360b424345ef6378509052d0356aba6a7af02d98fb53b5c3e1959",
      "572e527c5bee2edef081ab19066a53a1b99e90255cff96c6c788ab8171abf6d7",
      "6c4d8e9a01b9c6d6234d9219005c870fa0eefc368e9f30822814be0d8fbe3909",
      "6c5b3c0579bba22b8de58a9cc2273697cae5eb6221a86310c371645dab5614cb",
      "7546cafc31e4ebaa4168fab9e4c48a6eba6fdaf2b8e82c5a2d0f9a04ac231d8f",
      "765faddf7c3913607acd852f1625863870b909fc7981edc8086b892dd034a5f9",
      "80b927bebe068454e0170d61c8c55caa8b59cf7fc117c6dc30d1aae2776f7777",
      "84a39eeb81f7f2431e4aad61cefcd1ee128ea8d4d8561c204030f255697feb60",
      "8dfab8d852b4abb3fc7ff0f7d3d4deb88979aa6aca5a043bb881c0536275fd21",
      "9c163db17d9574b36141a843db395a9456d8d9d3de51211200bca909dab88e1d",
      "c8fd50f1c6a37c25725c27dfc97161cc31e10bccc48a2624d0d6829d6fbfa866",
      "e1bb364c9b4d40fc7ad6ff593eedc7fd789fee194bf6dc99a2eb3e68513b17cf",
      "fcb6ab4614cde9b5288f404bc36871f9ca4fed6a7a30d94a1eca339df9d4fc69"
    ]
  },
  {
    "id": "canonical-field-values-and-types",
    "plan": "§§11.4, 13.1–13.2, 13.5 — typed FieldValues, versioned codecs and canonical JSONB",
    "tests": [
      "packages/custom-fields/tests/Pg/AttributeWriteAuthorizationTest.php",
      "tests/Distribution/fixtures/scenarios/field-write/probe.php",
      "fixtures/extensions/custom-attribute-source/src/InspectionCodeType.php"
    ],
    "changes": [
      "0626ef68b18a67c99141b0859519a7f7b0bd9ddbbb2c70b02f485384cd6e4d01",
      "07e78ea2b43409fee5ea0cc27059d07a95617990d0ecc5cbfa111da02d79e64a",
      "0ef64f931147de22d9c8bc86281403556ad99bc61bbc94f7d34a001adf82f334",
      "15a85dc8583d5754de88246ce4513aafd27b52dea74858fb30f1fbac31bd471d",
      "1ba9bbb1615eb31da877120bdcefc7632e2ace541c58b472a3f92de68017bd5a",
      "1c3bd7f3d8c04417af4b3f7ffe82d9bc0be618db701880ab3a445fd0ce921ec1",
      "25fe1ea2e95f33b89fb1cec3bf454a680ee32718a9ff65b6ed9fb8d91ef73037",
      "2bd0d05c575008c85fe96129e6bbb19d3f03b403eef2a7aa97c3e0d22bb9406a",
      "330ea3b96aae7de60c8644df1d26629112464b2315f10a9aa8966e8b25adbdce",
      "34ffbac89d031477c857579088aa02a5bc28fc61e4dcb4265a7c7d74c9ab544c",
      "385345daa35a97528e8bfa05807523b5275475538259847a5f6cf5108d47c8cb",
      "3c4251015fdc453790d55f5727fad800ec73f2f1291ace423e19622b11fb916e",
      "453b22192886b0958d876c269748a265be280e1dbaa0d6b831aaced7c5cf7349",
      "4a0a6d0e606e33951d98b7fd988620d4a98346d46593ac15ea4621f6e4f5793d",
      "50279add35d3dfb71fa35735dba18ea8427cf58849c5949d3d4cd4c079ca4232",
      "54cef4b7687593b706bc71fc558ba1da13dbd590810ab9fce84c5013a38adec3",
      "57aaca837c9551966a7c3518c53425b7b615344824c093daa2f1f17e034c5abb",
      "57eee1bd975fc83d4d0a836d7737cefb6e3e809cfd57480e09108da5c8d5a528",
      "593733ebbe22f58fda5dc92d2c8e5d4a92886b11535ea8ed0f3ff3907d35d25f",
      "5b7d329554740f33469adec7fd685d7e5167cc68b5fb73c268f9f6e553da0b71",
      "61f92504b86f83f17dc927095b79dddc5fc5a8a785adcba0e5deec5f0d407a86",
      "62cb85ad11c91161a6393102504b2a3fe3b12b4c25e1bf5ca40e2077f7c5fe7f",
      "6563732dfad04cb943f877f2a1156783a30cb4cc8e6261e0c23815bffa025706",
      "6a0841758e43736ac3e7e2e36e13e507c859247ececb7389541ec9956adff06a",
      "6c36f7eae93222269938a48dba877e3bba8fdba572b3d13677a21cbd660d3ac6",
      "6e39ac49b421fac4b925e144cc583e2e94cc9ae72e6f5d7ca4b65c46a8b4951d",
      "6f97d0bf1ef61637c86c352a1d13a740976a2ed4ac14f2e0fb1512bfee9fa122",
      "716be78b1d940b76b9c6db72862a474fd57ce1706981fad38bd30f1e6afc678d",
      "76246a2f940dd1a85992a3eee7164ce73a297873261bbfd9df5ee2b7f8d2536e",
      "773e36e3acdefb1ff9651733625b2fb480e8cc95ff7ff57842f18f559988f47e",
      "795f7118f6c593d63bab9c48b62effcf76855d686b06e4935b203463fbd321cf",
      "7c224e3cd6dc945c081cc3fd281c3679fe464c00a01d08983c3c76799837ae23",
      "849fb4650ff6504e23398aa81b7b53fca5b1c4c8fadd576b66dfbf54c111dab3",
      "8cc6c45a1a064b63da1239489f221072dcb795efac30aa17db0eb0dd6d119578",
      "8fc603e12d2cd60bffd437cdd6bc8a45e353045e106e3df14f5b71eaf3e7ff79",
      "91b5cf13ceb6f2c4f9f4b43cc328eac215671bac14d691f1775a51a8b287c3e1",
      "94d50e54de34b0ac580cf44d26999738729ddf1706ad8bc95bef4d32e5065100",
      "9c80d513be38df81eaa95dc22b397b799f7f79010ecc5f33a0f6abf3b8d2a660",
      "9dba704f04f08877f9bf3758f6f709641ae822221b2f50470636de276cc4512c",
      "a5b1d1213a8772d246fd4b3871a665746ee107719af66881a7631e5645c29471",
      "abf91cf6ba8a2b39f1a2f41c4c639230a21164a148a0f3e2673acc738ed0e877",
      "ac6ba44ee8b680e36666bd848dce5f27bb8d19002b947aea50b62f8909a6ffc5",
      "acece56feadb775d5f79f3bfc85395e3f231c3238aaf15652c40c86fae3d5564",
      "b7874d6e03c633502d79acce13b15fb8f968e049915639ff155656bcfd24ff40",
      "bd3ca261e78e43c980fdef35451a0a877147f375a9f5008f1d91831e3486407f",
      "c2ca66365282f4f7181fe7a232801c9630d3b10d52a5a4753bfab6383f481831",
      "c525ea6d162a017a917b3e39d083eb777e4997afe4992be8a8a8c4221fd247a1",
      "d94360967cd1c1a882658ee3f4000e7cafed33cfd5e89a969379edc26a1d1510",
      "dbf4e4d20a95632db532066944c3f4dc465bbb3d34191df11ce76526d1fe38a3",
      "e35fe261d9fdc57295c2b0c61393fc08700be700a3cecc6288c4ffcc57b34ba0",
      "f10424d5cfe4b60d965a0f8beee614c16cd306c1f0f3bce98669413095531e9c",
      "fefa62953d960941c08b4a5604fff58edea472c667f6551820392615420b5f9c",
      "ff529121082ef2b2d73eb1b1865f9478c09cba7ae7fa0289fb822aa81ce496b3"
    ]
  },
  {
    "id": "field-schema-operations",
    "plan": "§§13.1–13.2, 13.5 — immutable plans, scoped schema heads and PostgreSQL claim recovery",
    "tests": [
      "tests/Distribution/fixtures/scenarios/field-write/probe.php",
      "tests/Distribution/fixtures/scenarios/ddl-claim/probe.php",
      "tests/Golden/SchemaGoldenTest.php"
    ],
    "changes": [
      "2f0268e66776f4595ef60ee66a2b4997f68cf66c5abcb402f99a84926a7f65bd",
      "407b750edb19d6f16b20ba3fc2062f66e9b99bfbde6f9baf89d9a50331104c44",
      "51c41a255b9b9dc0e8fcde2c6c571021f0a8af5be55f4f1c4015c6aa866b0458",
      "5783124881d448f1b9c0d2ee3562ebe5fe19d976fc9d53e56e905d29764e3804",
      "5cfa285bbaec5525b0fdc71c719b6177b49113cc8c1f0df738ebc28c091b260b",
      "681cac7ceb8a435edfc63c5d1b07cb827f165905d348f813fa0cfd20d68d9182",
      "809d0bebfe8db145389cca4cb0c83349852d8ef482f849907c40b6ac3d985ea8",
      "88092a1aca1a7aa47fa6a44b6bebcf6b911ebd24d7d1766dfd03b68edd9b34f9",
      "c3939a6d3090effffd37b4678068d947cbf0e9e42a46ad12c72a564251d92fb0",
      "f03ee5eb3c6976b38e582b85bbaba1eea3e1b799326f71215d5f579efd3190b1",
      "f0fed5e0cb302f73bd53c5c2274a262df840c5f171215174f981c5e91a12878a",
      "fb0b2b992bed91e587d4cbdfcdda4d8997171f912b669570d0d7d39267ea485b"
    ]
  },
  {
    "id": "transactional-fields-v2-facts",
    "plan": "§§12.1–12.2, 13.2, 13.5 — same-connection, sealed Fields v2 facts",
    "tests": [
      "packages/custom-fields/tests/Pg/AttributeOutboxTest.php",
      "tests/Distribution/fixtures/scenarios/field-write/probe.php"
    ],
    "changes": [
      "71f3f96767795c3ce96613ed5823624d67429d4ce3e8e56933ff44627b7a92f5",
      "9dade62c59b2d2791db8e464a04a8bde36f0636b87a7b03e37d395a5ece85e7a"
    ]
  },
  {
    "id": "authorized-search-query",
    "plan": "§§7, 11.4, 12.3, 13.3 — typed Search query, per-use policy and authenticated cursors",
    "tests": [
      "packages/search/tests/Unit/PolicySearchHydratorTest.php",
      "packages/search/tests/Unit/AuthorizedPostgresTest.php",
      "tests/Distribution/fixtures/scenarios/search-rights/probe.php"
    ],
    "changes": [
      "017773ab7ce2632d38e69bc58c309e175356290b59d73400915519fd5003cb03",
      "1491732b61c1b45fd26ab8f49e3a45049c05f4a264a3893270a8e79d0ca4d961",
      "312dbeeddce6013b1fb196fcf30f1776cd8e2eb79dfc7e38713c5c12a460ce98",
      "3e079c2cfa25951c60e29c7b2207fb7149bdf574bd7d15f8e9e06d5a834705db",
      "4a0343a565dd6f47db4a4885ec5871fb4bbc4cf76edcd6f0d6fd0a82fb836c0f",
      "58b8bbcd91e88376cc9d85276d5f2cba92a66b806e2057c4272c1375f2959f1b",
      "5fe36448f976b51262c3d769a39059476a6294075db2ee9111a1cc4a9390d3c4",
      "6416c5b8dbf4c12fc95f80035afb9b8a36bd6c1f2b3937a2fbf1439216bc4d96",
      "686776e95fe4e217b6435431cc6a35e4b6bd740b0aa5ef772a9a36da62ee57e9",
      "6e86ba29a662ac15d1eef66edca7b63f0f617d2f9a5ae8cfa2714fb1d74bfc17",
      "706ed0d23f630deb36995fd208a666cb701f7b0463ad0b88fb18b057954701a5",
      "77513de8a82956de4b09d4760c8b98c86b144f10a3b93c0c9945a39c6158c4ee",
      "7a2a06dde6133c784a5fd2287c453856ba6c7f8768e2f439a2a8f39982f88a26",
      "7dfed9519af3f757cc3fa5996d863f17ab42f153857aa10a4aaee47df8372c66",
      "8303b1553a00491a18a78a52c6b913a975d3a8fc38b3f3c1683c450848bffbba",
      "84b32ca4e5f7f091eea0aa2cf13cb29a4660f494909cd60ce7115d6074d92e4c",
      "8f0a3b9446d3cc385edff572ce05e6441086ae9973803ec033a36cdc67739d83",
      "b647d678e51f2e34a96f48e22cab65949a0f359fc6ee6b63db96a52534c98992",
      "c30f81c0b56c235febb87b211f5963a34a95b39c3facd26c89af85877651e592",
      "c8ce88a4b8b51cbbfb6a216eb943b6d8af48b68e5813bf4d95daf95821cc4726",
      "cad759b7d5853636f2768084ac3e8af3b568a12bc44e5decee2077fffd27f7d4",
      "d449f68dc6c98ed23569765bf7443dbe7aaf2b055a217b14db90156433551b57",
      "ef8ad3c401d71172f9cb8afb8ab4b1e5626d99582d57190e7ca56f5ef5af4939",
      "eff15a1694a5b07f21e994b24265fba99fcc5322d09c6f658cdc6ea96890d5fd"
    ]
  },
  {
    "id": "durable-search-generations",
    "plan": "§§12.1–12.3, 13.4–13.5 — owner journal, immutable UIDs and SQL publication barrier",
    "tests": [
      "packages/search/tests/Pg/IndexLifecyclePgTest.php",
      "tests/Distribution/fixtures/scenarios/generation/probe.php"
    ],
    "changes": [
      "0b2b389f984a2ec783134d98f9cf4deea306509b3215e0ea72c34f57cb4620b4",
      "43112089074bb529bcb1548eda62e7608e18de2d32152b73e8de605e2a718fa1",
      "4e442bd2e6683298eb375ea0b97a6c59a4c7232df0117f9e6c403d0e0bdca072",
      "5877373dcccf93a2a665c4cc442449a038f75f2d48527b02fce3b4086c23c8fd",
      "62316bcd000deb4779f230b4c36ccbe43bae395ed3050dd047b138ddc32807e7",
      "be9c7ee4f786c5ff17d89586c28734487095402159c9c23823d72803f238cd63",
      "d52ac69934c8fb088f7117d87d23ec73706d19a35b3a161c47bba886587b11b8",
      "d86ca69a2395a4c5f208e2efe722cbf4608922455e2f37f70d28a11897e13db0",
      "ec67f4c4fd6acb95e88d955335e9ff7046e039d6ecf1ad413fceed227ecd3ef9"
    ]
  },
  {
    "id": "typed-search-drivers-and-locale",
    "plan": "§§7, 11.4, 13.3–13.5 — PostgreSQL and Meilisearch drivers and optional locale projection",
    "tests": [
      "packages/search/tests/Pg/PostgresSearchProviderTest.php",
      "packages/search-meilisearch/tests/Feature/IndexLifecycleTest.php",
      "packages/search-intl/tests/Unit/OptionalBridgesTest.php",
      "tests/Distribution/fixtures/scenarios/search-rights/probe.php",
      "tests/Distribution/fixtures/scenarios/generation/probe.php"
    ],
    "changes": [
      "0853d1baef521a4334148be21865a15a159475ed9f4ab30a8b7e7da1d10094f8",
      "0dc12c075aa480383d1c33973f36aa23e02f22f3141f06f9427278a7f9f1da2f",
      "138aa64fa10bc6d1419c93b5d8258b9b639e1673bde804a2d8a502ec210c7d2c",
      "1e671899ca864e53466903f2520f85932b9d4b6d3d6fcf68b4e2e599623a1082",
      "25a9b24d1983783ffc1243677183458b743f8eac721d713e2069457ea8c7dcac",
      "30b0f306e795c66be01430d5f422ffdea6a0efd959f4bb49c53fa2fc15aee439",
      "7a80af832e677f4516a8302262316d85f755fbff745a4c2811454e01549a18bc",
      "7b581ad42c39c51adae12e0438205b8a60c0800aee3051640ce749aa794f64fb",
      "b288b07785aad29e2535f1b910d4679ec31fea3c33905ec63b8d1b90f9302342",
      "f1310871652b2ce39f1d15ec0f18ac770959e4b707aa72b0c3748dee61ff8a69"
    ]
  },
  {
    "id": "localized-shapes-and-saved-field-identity",
    "plan": "§§7, 11.4, 13.3 and Stage 5 — canonical locale shapes and saved-view field IDs",
    "tests": [
      "packages/search-intl/tests/Unit/OptionalBridgesTest.php",
      "fixtures/extensions/custom-search-provider/bin/field-search-compile.php",
      "tests/Distribution/fixtures/scenarios/field-search/probe.php"
    ],
    "changes": [
      "7b4c91f3d8ebae52ef08f454dfad9e2657cb2c4dc91f218f61492da587c7a45f",
      "8133da70e4ff82b3ab32f92f63f183ffb1a87ebd17eed67192e264c08a4877cc",
      "e079acc542c16f0289ef3febd665e59d254df00552c28aba695372c269a9def2",
      "e434a95033bca308266d69d2a3ea62766b42845dbda8ac61ae3df1222b08399e"
    ]
  },
  {
    "id": "optional-search-custom-fields-package",
    "plan": "§§11.1–11.3, 15.3 and Stage 5 — optional independent participant in the split cohort",
    "tests": [
      "scripts/distribution/tests/test_release_candidate.py",
      "scripts/distribution/tests/test_profile_proof.py",
      "tests/Distribution/fixtures/scenarios/field-search/probe.php"
    ],
    "changes": [
      "efd59d20220f9c5853865ad6511caef62698dd0c955225f89aa8ccc1bdc1d083"
    ]
  },
  {
    "id": "tasks-completion-lifetime",
    "plan": "§§7, 11.4, 14.3, 14.5 and Stage 6 — typed completion admission and lifetime receipt",
    "tests": [
      "tests/Distribution/fixtures/scenarios/task-lifetime/probe.php",
      "packages/tasks/tests/Pg/PlanningConcurrencyTest.php"
    ],
    "changes": [
      "03c60052bbbf9dbfcdd4e693200f5df66db7c34c8194673b0617333494f57ebd",
      "053d6e2f9353c598a792b2a328eee670a9edf8038ed0dd8f11f3e4e0a5b14d85",
      "0d49703d232614560d38868847892ed9b4fd1c03516b09091d644c50727c2f7c",
      "0edcefab6babe67fc36d7e7c5ac05bc4b255f73ac1d56dd61bf5b80f67516117",
      "1630902807f5610f9c6d3f62b2a7ac6e349fda309a88af6c525de32650a82228",
      "1e4f1149e7590197f0e951fe55aa52aadec583415261d57ecc4ebd0350ccd554",
      "28166860845a3c59400025a630df390b2e771a42eccd4955607e0ec15b049481",
      "2da7e93f7754dfa2a5e18b1cd2742581df042faa39aca28132a58e5988d02684",
      "441cb7905d57340be6072286ff4ac626a42cdf2b6ed778088f2d94e3bbae4ab3",
      "46c09bb4990707d53e0440e9c4e43bebf89be75109761c360f4b77b3744f2681",
      "4d5d9c0728c01cf8d21c3e62c23c39a69297a21fc0cca66c386fa96410ae8879",
      "4d97ead36c0069434c1797b67008dbe597ba808ac4e993635f0f36fe256d2378",
      "54c9d8961bbd543ab77cd6a3b34531eb16351eda281f659f84b05fccd10c9537",
      "626488f9ec9ec4dc8adf230b4393ec40956e515ffef87093f803688352e46cfe",
      "74ac38eb4cd43894606fc6c44322cf197f46da60d1caf69aebcbf3d7c150c77b",
      "77689fd668ca6ef8dcf4c03be6030758340dae4bd54c9b52ddbf1790879e3f82",
      "796f0566dc2eec9bbd6c4c07c27d91ae2e8d02d39b46936eb04e5b8dc65a8218",
      "7a7c2d22a6fe656670ef35761947431df417476367d5a7cde3cfc0bde09bccc0",
      "8425eef687f0d9207199328865c3a5bd129362b4190ea4ddb6491ed272284e11",
      "97a60fe4cbdac62643e2bf760f80d957520ccd34b3c501263d4913c7208b027d",
      "aca44cfaf5ba792cec5f8db83731c9128b4864bb8a331c6f5155200c72172614",
      "adabc21e5361dda3ce2232e336f9e9e8fcf3d59b739f5e8d0dae4272bb3f6a36",
      "bf6e400284205aba76349a17f913ed7481c979127b0b168b11850dc45601163a",
      "d49842cd9bfd6edeabf446eee4f348cd16c60f41a5a37fbdd25a48a32f1c0e68",
      "d4b1b92bd8b41e029aeb854353d38c111adfa3b9b72d0790f1d061b523b4a886",
      "d8da6f9425b2f254f1e132801c65bde13fefde82996d653ac9d9237affa1a07d",
      "dd571db02ac4c5feedd0db72e3ba499f7da43b46d1d91ba26f4b4119d138caa8",
      "e2d5a323a1d4e90d3e4c061ae5cfad78a6cf011a6a4b28267232614a22794ae2",
      "fb1f9e3b011d8aa68ef14bf52101797d9a03de782b9365bf8040089a2177736f"
    ]
  },
  {
    "id": "recurring-tasks-without-workflow",
    "plan": "§§11.3–11.4, 14.3 and Stage 6 — separate RecurringTasks owner and its materialization receipt",
    "tests": [
      "tests/Distribution/fixtures/scenarios/task-lifetime/probe.php",
      "packages/recurring-tasks/tests/Pg/MaterializationPgTest.php"
    ],
    "changes": [
      "0089c58271e34c46f9335933729bf1fc9fafc8246360722c75580fc3f8c220e1",
      "01b28d28bb29b7166ce8cd81d420094b5ee3fcfa9350869491fbb9466cf06928",
      "1ca8dfbe86c4402e9fb331ad93afb754eca8050c5331e152a6260ccf87fb2370",
      "352c55bb4bed9f96b91babc9b2ab10c1974c045ebc04b803fb72b86b115b2237",
      "3f574333ba2c668e27d71d9b72641990f3392a4a6f9231c0037f2e0cf3e568b4",
      "4056a4b6f4fa778396bf2049abc8ca2cb79f5ac442417e9cb78cce02f0847947",
      "473a7aa6103c90f5d524e0d3bf114b5855e124b8e7a97b63a2a484cac30495b6",
      "a3828f62d22be9a325cf69cbed677f4d650b705adc2867a9faf00acd66bcc3ff",
      "ababe7a5f11ce9cfebb17d4dfbb1b7e3bd82cfabdfc299397198c6437a258b57",
      "b22b932b8c2a4fb2ea36bacb85104586f548452714c239aacfe6e4a944d269f1",
      "bf1f46d9ee941988969c74fb1d0dace401529070f651db408308691ee00686a4",
      "c4e404d61592e67bcbf399e75a51dc9ba241318fd057ec68781e717367c25d38"
    ]
  },
  {
    "id": "workflow-transition-port",
    "plan": "§§11.4, 14.3, 14.5 and Stage 6 — synchronous transition, durable capture and scoped definition validation before activation",
    "tests": [
      "tests/Distribution/fixtures/scenarios/task-lifetime/probe.php",
      "packages/workflow/tests/Feature/WorkflowBindingTest.php"
    ],
    "changes": [
      "4d60d5d4dbfd3ce94d691e73bb79b5fe67a113bd80c52958b33ce96fb77a8431",
      "774f16446bbacc1f8d6077a4746fa14d22ac93a359ad4cad2a96a68a28a2da2e",
      "db30a45bcbe4abd0e70510768d1ca347f36458a3c334901da4ef5a48f2d3cd07",
      "e03dfd5ff5159baebe5d00cfe29c2052e4fada7db6528b0d47d564d92f1ae5f6",
      "f857a9e311cab9141ac93fb6622e20a201097abbe14f49bb8af5d7e5b8267557"
    ]
  },
  {
    "id": "optional-tasks-workflow-package",
    "plan": "§§11.1–11.3, 15.3 and Stage 6 — optional independent Tasks Workflow package",
    "tests": [
      "tests/Distribution/fixtures/scenarios/task-lifetime/probe.php",
      "scripts/distribution/tests/test_release_candidate.py",
      "scripts/distribution/tests/test_profile_proof.py"
    ],
    "changes": [
      "7f823664610e9e71626a7366b604a87d509efc6c0541891e185516973f0424fe",
      "89430ae86370ae4fe7aa6205621be7963710fd0bfd595394bad24d905cd2aac6"
    ]
  },
  {
    "id": "foundation-discovery-and-access-identity",
    "plan": "§§3, 14.1 — canonical Access/Laravel PrincipalIdentity and config caller; Foundation/Tooling owns development scanner, Core drops Attributes ownership and retains native Packman discovery",
    "tests": [
      "packages/access/tests/Unit/PrincipalIdentityTest.php",
      "packages/access/tests/Feature/PrincipalUserProviderTest.php",
      "packages/core/tests/Foundation/Tooling/SurfaceScannerTest.php",
      "packages/core/tests/Modules/Feature/PackmanIntegrationTest.php"
    ],
    "changes": [
      "0d39fe4722d2535e1b32ddc4b76551d3c4b52e49405b30b1c4ae76df721240d9",
      "138496c55224b02422305c8995b96912646e46aabc2f2c0ec9f3c14f844fc1ff",
      "831389f2dc235cf10b3b730eae736c8c3c4fae4f8aa0a940cafcd36d5e5d6973"
    ]
  },
  {
    "id": "scoped-lifecycle-problem-codes",
    "plan": "§14.4 — explicit lifecycle_blocked and lifecycle_plan_conflict 409 codes for current-grant, closure, inventory and stale-plan refusal",
    "tests": [
      "packages/core/tests/Modules/Feature/PackmanLifecyclePgTest.php",
      "tests/fixtures/public-error-contract.php",
      "tests/Distribution/fixtures/scenarios/lifecycle/probe.php"
    ],
    "changes": [
      "689493e59aaee57badd64d812685598e775abd9c863517e80e15e5dcccd080f7",
      "6cd9068812334bbf8465a1f242e061b4711806f6f98af97b90b021ec8d6645be"
    ]
  },
  {
    "id": "media-owned-domains-and-conversion-intent",
    "plan": "§14.4 — move Files and Attachments ports to owners; replace ready-output conversion DTO with durable intent id/file/profile and inspect/read status; Vaulter keeps blob/pin ownership and retained purge receipts; native package metadata explicitly owns Files/Attachments/Conversions",
    "tests": [
      "packages/media/tests/Feature/MediaLifecycleTest.php",
      "packages/media/tests/Feature/MediaLifecycleEdgeCasesTest.php",
      "packages/media/tests/Conversion/ConversionTest.php",
      "tests/Distribution/fixtures/scenarios/owner-effects/probe.php"
    ],
    "changes": [
      "4f749e08be3be9c42a205404f39ea5407f8dbb0cf6bb17a03b819cf868b8b9b5",
      "6ab168da2023e212534301d1876bceb8d0bb92cd2421d6dfb81b7954607d74c3",
      "770479fbc6ed43bb67d68014a92477caa419590a62d86f5c8c6ae327ce49221e",
      "963b30427526cb511f1f4f9ba9667d2f4dea815d224abcbd2915eac18c8d4adc",
      "a028b47f66a71efa20354961eab399f901b8912da93e548073868157b0e81d3a",
      "a83c3d27138bf55cbb9c35b3e529edf6f9a0722f4f1e36cc49b74be558bcd998",
      "e8250e49916b1e50234a80e9d7ab59ec4906f0c5b38fd92527340e087b1dbc43",
      "fec773879513b7c1d5dbbb11e98702efe65d425011b13e005a2848bdf229bd58"
    ]
  },
  {
    "id": "neutral-bulk-and-optional-transfer-owner",
    "plan": "§14.5 — neutral BulkExecutor/BulkOwner, typed checkpoint and opaque owner result/failure refs, ownsClaim fencing, bounded rows/time; remove built-in TransferFactory/session and Operations ImportExport dev/path edges; equipment explicitly composes Operations and UUID helper",
    "tests": [
      "packages/operations/tests/Feature/BulkStoreTest.php",
      "packages/operations/tests/Feature/BulkTransferTest.php",
      "packages/operations/tests/Feature/BulkResultAccessTest.php",
      "packages/import-export/tests/Pg/ReleaseResumeTest.php",
      "tests/Distribution/fixtures/scenarios/bulk-transfer/probe.php"
    ],
    "changes": [
      "0cbc08781654e14113ff33216a578156d33332f0f1ad370ebc5a19801238b6ff",
      "16f532ce8efadb20d5ef73d72d04c3e66839b0a7bf611dda159d1628e47a26a2",
      "187544a2c2b62ed4c3b6b08e3f52f25640270057975a60d4f6c68a9a95cd16b0",
      "191b3d21de56fec514c66196618f02f4b7d19c44ae612484d34b64f212582788",
      "26f4eacae0f98614e36b3c09dc292c64b5c24dee99ddb920af7d96e1a432a690",
      "3176c6bd696fbd8c37027c5d24320fbc7f1fa7df1ff2f45f923cdf4aacc187c6",
      "32f4053fa81100652ddbd1d49dda30cc461f7d2c982e1f3d7f5c0cdb5d1561be",
      "3c9e9bda17fdc97a492f45693f3dfdfc910071d2d047ce18e15ce051666bdbb9",
      "3e4b385d2cffc35dc8939bbc0b5cccec42f511862dc1fe21af3c7c1bbb0981cd",
      "427ac1e3a5cc99e295587f6aa995ecc519307fec0b7e9e96607832920c7ff299",
      "443fc325f7c81bc2cd1d0c55509311e0bdadb9f781af3fb8d27541f78f98ae20",
      "47dae85bbcb3d2ca77ba8151cb5a1a827899fb9531e2eaa7fb9d5b7addbd155e",
      "48494db9dce397ab19d8e5038df7d62ef3c33d3014145199db3051f310742bae",
      "501ee6d2e9746148bb29abd9e0c6da5f0f507c5846fc67e09f2035cade268562",
      "5ad8d136d1de7b2d3d2e6a0579b4728e912b1b4c4ac321a7e16159e440ccb954",
      "62fc1810cda4cfccfe454dbdf26e4bd4045f78a970c33bb5c7b37c836d0e1446",
      "7101810b8f93e70d32e85a06280d8bc59f404ac88796a523814db2622bb8a8ae",
      "71044728f8ec8db93542ee4b6f0c0174145ae73daae54cac6d2efdf93b88c53f",
      "75cf17cb3713b5662cce856ccc6d9e635cce9e985220cc2cc668b62b9137f6b9",
      "7c2150cceb676ea8b2713aea8d48241cc9239f8de21e2159337c9d6dee7bf6cc",
      "7ed1da7cf3545c537c78ec0b31a1f7ba9d666dd3cf94d64112513d5c5ff4a66f",
      "81951e4c3c937f0e7a7b259c3e90995ee3dd77ea39d758a8005ba0721186ea3d",
      "87f72ad385920b45c3530c709a1d6cd08e23cb9678f9dbe14b8e5b9279487d71",
      "a4577200c21f52cd1687aa774125362638c53258011bd3ae8eb83e038f1bbe26",
      "a71d89ecfa014c5444b1c44334b17e74201cf608251ab4bf472c4b0627e81c86",
      "abf34823c75f14632d618c4c63d0bb1af6dea0ffd8e4f927c40867398761d93f",
      "b20c66dbaecb58663c7555d662511199e481dbde7da117e306e3338c73a706c8",
      "b60156248f2acdb86711ba92ce8196e3ec209b2676f61297b14de5276a42c518",
      "c0b878b042f17593364406ef2214185cfe8e6e77435c09cc7e1074cc10203508",
      "c40191ef0153a20f5788e64ec0b6244e70d35d7c2a1f6f90a8a1240b6406b6a1",
      "db90ed851b76c444b9a9dfec91245c84f9e39c18a0c9d446f01a2cb8c0ed80d5",
      "df7b50881a8f0874b5d99a8a94518314ac865ee6a00092b3a15c3ea21c5fb7bc",
      "e54865a4f6974e55668eaa7a023647506e3ff563f6927efac89a6da8a8ee10e5",
      "fff30c17631d8106eced250bb6296f34c60f6be8a4b9e37038a5f12ef0851119"
    ]
  },
  {
    "id": "reference-fenced-transactional-facts",
    "plan": "§§12.1–12.3, 14.4 — preserve the already adopted Envelope v2, logical receipt and owner transaction; enlist Inbox/Outbox producer package/subject/consumer targets through the actual outer transaction before SQL",
    "tests": [
      "packages/core/tests/Pg/OutboxAppendPgTest.php",
      "packages/core/tests/Pg/OutboxRelayTest.php",
      "packages/core/tests/Pg/DeliveryGuaranteesTest.php",
      "tests/Feature/PubSubDatabasePollTest.php",
      "packages/core/tests/Modules/Feature/PackmanLifecyclePgTest.php"
    ],
    "changes": [
      "712fe7f99a99eb2912434e2503a2ca01cc4934d7d75167ad8eb8616d0077df9a",
      "f349d00a3dd637811cc880f04da4afa841bc32bec1b9747a68c2b5a1c7aaefca"
    ]
  },
  {
    "id": "literal-http-v2",
    "plan": "§§1, 11.4, 14.4, 15 этап 8 — one installed HTTP major and five native declaration carriers",
    "tests": [
      "tests/Arch/ApiContractTest.php",
      "scripts/distribution/tests/test_compare_contracts.py",
      "tests/Distribution/fixtures/scenarios/transport/native.php"
    ],
    "changes": [
      "0053c1327a4542d5755dfc6515cd639c77419f3f8d4465bd2cdcff9072e418aa",
      "00e836dfa3a978906f53832e23aeab9f44ab0fd39de09294cfe76d97a2696ec8",
      "01ed9c9df81daedcea2fabdc7e1f19ec6e8b65a612ee9a89edbf11e8729a11dd",
      "03a03238fb44cd2cb902a1acb6eb690a5fcbaa691838d1c8771207e2981c53a1",
      "07c354ed773626672a950bb51db8e43e3ac9ca4802160b353836c451a628d829",
      "0849d637c1493747aaf81b64135ad7cf0bc1c2c2391ee2e7664a2b3a8440217b",
      "088f24bd625b84d4de7148ca07bd6dc1c3863aa8f239aa54aeca2730a8b77441",
      "0a053477a227f95fa769eaf3a6e866f0bf040beff11e23b65116f1298f04a470",
      "0c0e2140ef801c4f1e8a06e5aa3832c5433e36416ae8595a2b6e1c313fcad7b2",
      "0f76c2d02b6c95ee3e0e711305c7e1b806073080a903f7cb7152ee7186033679",
      "103382336c96eb31bbec264c81cd90e83c603434d258fbd51805b3b73dabbe94",
      "11afa1876bcd599e8755e6c1733eab16384d6d9df3430314565a7f2b99d9cb8e",
      "12d5fe2478e99bf766f6c1f483475962ab1fd967f08952d71414b0438cffebc0",
      "13b5802727517cad8e72f04a67bb2fbf199019db2be5bf019e93c470431490c5",
      "15035a0c71efbf7f78c1bab8d21c39942edd3f9e88bbfc71d4f8e633b0f5eb99",
      "162bd11e94072bd89bfafff133529111bd1ea59327588f1f2f231424e84921e0",
      "171486a5a8d5d986ea5fafd7c9f28c18fbd66de0a2e657a5e45027cc001cd534",
      "1bc709695b161eae6c9c01cc155cd39a811fdbff07a8234114a27692db9e3b17",
      "1c6ed0fd8a31dcf07587592ed0ed78bcc526164dff8bab5b1c5a8efff785d717",
      "1ff79172d80b04d2f1b8235d77baf9a5dd224b4fb22cceb95e06d56f6500c97f",
      "200c6a41b49bbd0b1b44f134d44fe8aba458866961917b733a7742a9951917f3",
      "20a4e77688dac245a02a5728f2b9ff9da83e3c169d5f3a8ba9122e92f5a5e787",
      "278140c391e11f1a52e719e4f2e9f0c4c7fcf63c4c5fd687828ced001991720f",
      "2a2bdc38fabd646a80799f28eaa28729aaaa0ab1c5311e516420902eb35551a6",
      "2afa1e04f224ea015e577593629bb2fd08174886bf6f6ab8dc4ffbe057cb80f0",
      "2e774750ff415ba6c6c4e11e06a651bf936d6a1ebdcb588ebbbb1878e7c83a2b",
      "3081791fe0f5a2a0795f2a43d4f152fd1c486333aa68512529c0f20ac71e0e28",
      "30aea8f2ec397454347fd2f7a836cfa819db8114f5239acba2762ffbda21e233",
      "35c07437dfabb4fb3942736eb8c482bd7d0436b6c0aecd5ea73576b6b132ebbe",
      "382871bfa75cec8516d8a34e8591507c6f8e284b641f267d245b2ad83972228c",
      "3a102c238eabc71b3afad92e69abe669ad782ccee7b9c07b8221e840b199d03b",
      "3a637b0dd71b0fdb107374b88fd5a98b9bca9b12b88b89eb35ab452869305548",
      "3b0d1e394d5b2d0b143223d95f115910d075af7ff45ba493c687df764f25b20f",
      "3b318cfd6d41896dec92a52f071c18059e2aceccbf8150c3f4dc294e01cb6f6b",
      "3d40ca8e64426307e814bb89aa17952695eaae711ca8603807c8bb161c044509",
      "3ea9bdb184fcd5f28f6249955eb0bcd97f7b772aaeba680c1edb0febb741f649",
      "3f7eae94239c09569672dc388c2da9eb26ccf75f720432e5131058ed6fd9026d",
      "40d7ab5bcc8f1aa52ef2096a8545b113faac6034e59902b5260cf7216d4f740c",
      "47dd03a951f2c77c6e6f135f20a6fbf27f8e5be2f7d7e4acb34176eb064a4f2d",
      "48c7cde17e4079b86e24d3055bf5a1195a077459b210b3d93ec5f218243964c4",
      "4abd4394fb010f5aad8cda014ebcc3201450cedd4e71997b21df2d2bbf4079cf",
      "4c458bd1d4951fec7795b8e392a690f5eb099d70ba8104d5d95012fce02adb2c",
      "4e6520045607e03f5298441753d57c5b7d3fcda7c4e24674ddede5b5eb5155ea",
      "54a4f359fef82aaa593cac1e0bd7be535991e10937560997d0bf31b4542ddaff",
      "5caa14f5f3659b30e4ddc78f0e91e3c4cbf2b268d0e652ecc5465c062c8ba554",
      "5cdfa6cbdb2364c00433c5898ea5c9cbd40e926e626d994d8caca2e738cb1b1b",
      "5e3bbb45efaa91b55032fad010058eb23442a758c5f212a0ec3cb61a77c82366",
      "5fc9fee5d6520ac1f824d6a5b49cb1d0017bb3eb58594db5b2044c5958ec4f07",
      "61015a87e7c35d2a0272075375039de8b0e30f4db5daec8dfb4b882b216b74f2",
      "626ea615268d5340200648a8aaad9fdd8d7c854dc9d94f105cf6b4b68c64a52e",
      "647edfb77bcb194ba9cf813c392e03d27aac5699fd39907b270ea5e8733a858d",
      "6504194b4ed7c66a0caa3349c7b141cec36fe2d26fa56293b8cc8ecb268e23eb",
      "673776c0ed336b0306e0d6a8ba03d1138e26639941fc2078062132b6622e6ec5",
      "7323d3a01ffbe75548e2eec21010904a30b73ffe1ff0f4394b4985052a27f46d",
      "7aaad8eeeb3775e8599283e7c0b57c568ccb28b512d21ff34dae661f8e51869a",
      "7d3117ab9bcd8f3f9db5a648cafed6dfce6ced13116dfff27f8f78000a8fc9e6",
      "7d67e64cc33ca188a82203c6b5e9de29271090ee170df07fe133406852856a76",
      "8039c44821d720665c0ec9a6b1799d0eef86e4fa878db4dd3fab1644e7d23f23",
      "815eec100bb2fec7d989d57b8a214e9d1c605c498892cf96a54fe6e2b9bee58f",
      "81981678239e18e625754e50b9741ab558d767f9b6bc6b27fc017ae17a0454e9",
      "82d26068b56be9147e1d23407fc769ca9cc8679d6f8854ac1c6fbbcf534194cd",
      "83287e0f66c3e7a00213665af329a59df0d9714437bdcaa0a402f5435c193cbf",
      "83dcd1e823cfba183d119c7ac66ba63b5052789289a7bc63dae1ddfe3dcffde7",
      "8550fc5fe1d6b5594440337070be2a654c2d37f7aac1eccd26140f6baf8f5e35",
      "85930a545545fa91d3ecf7b877d2d558efb616eced5035caa7453e5b60f513eb",
      "859905a506bec167c72fe24bf7dc1856d950e166c29b35590ce7c116e8e5c064",
      "889a43d47a0a9e37c5c055edadeaa228fb3af07454df99f7c2f24e468bb3d143",
      "8a4d6122beaef462cdf689e39c9589f28a9ebafba948444417e1ebcee59612c1",
      "8c2e3e8c34a303585a64ebc972a28d455233473d592c623262932495ad5e8a3e",
      "8d961378a7e920243642466b1384089044dd6ca704903a4fc419ab97a543fdc7",
      "8ec849ac7a8fdc538e05f9cfc0b29eea99a83a10e1797f362da0aea296a35ff8",
      "930d1406916900601f398a2f8b2646c54907bd2136c32b7ba07c3857c803d13c",
      "9c7a9bebe73e0a4991e2f71abf1716fe2d096c80b7e81ba03288fa55818e1b56",
      "9cb567f9f7bcd76bfd1e0b78d455aac2b7ec7162bd2a5376fd6f2939c53ad9c1",
      "9d73db18ff74dda933a9331ec2babf8c82821304e5c2a218277c40b642ce8deb",
      "9f973db69d0feb531b481e833d19bb28ddacb90cb1562d5722de758768ed5408",
      "a0ab3b73ec3540e9285d0b9318124f55a4165437a1572e2bfd60a570cfde5139",
      "a1d076d20d0447a58bc001eb7206753fd10e9d97769b1a01c98f5156a7d6ada4",
      "a1df5cee5239ceac67eebcc32bf28a5348636d1df61986371305cc044d9053d6",
      "a2fc8791b27e4879c6ab7c532edcdf80f3dab4125339fce1e677ca0a3d12c054",
      "a36663e3506188f1b065c5e6071b5609194d1c971ef24f26c71dfa84cb37c46e",
      "a455b910825399605d2b31b1ade2421599d3b86e6fb995383dfd317a294434f5",
      "a6f25eb4d25a1e50754ee322c171550e6908cf2296caadac3ef19068cae4d624",
      "a846cbde89cdd331ec79cc93e5e46c094f04324e56850fdf6e020f7886b41a55",
      "a89eed905eeb18ea673cc07543d247ffea16a8237f5db174c246aecb9bdf932b",
      "a97b65c6586f3317c58fa93d0abe82476b0e6c8789ef5bb05f5dfcf7a64086e9",
      "ad25185d3680bf93a1a7ec9583fa2d07a0381334d405126886aaada0a6a3e6e2",
      "ad5a62de50df3ac4708338502bee1d0e436d69b08edf6822ab6832ee82bd4982",
      "ae0a89362aa416f10b9ac07d16b05cfb4ee3b75ec38ef0dd21c2586bec3c12fe",
      "af06f0968ba2bb73e60e1efced6c74862e8606a6079cd88eaf55b2201e767ffd",
      "aff77eddf11c2e2a6001529fba4d230c46d1b881378f4d67dcac1a9cf90b8cac",
      "b512f3765ec5cb2002762166f02fe0816bc70265986207a86f772c8e8c212f98",
      "bcfde792f35ebbe9b9473a7660e98cbfd1ec5b276681c8a9e788ed9cdcb9f958",
      "bd1aa538cd2a25129f93dd7cb97e9669c947380bfe391e454c92b8e21ff90129",
      "bf26382ec2810f1155771f19c27353212c744b1f66106bac8448032fdfd6f727",
      "c23225ba71e3efc365f19fddef5de9e71407ea6add270bbb258da0e43f88e6f1",
      "c262a1b63336120f350394dc5a0e8bbf95ad728856bd118dfae6a957810fca66",
      "c27e9fdbc599a7e12f984988f96afd9820e10a24c8eec7b8b472ef9d49774472",
      "c48f5da2bf35e43b48de0f8aa96318cb0bf83ecd24154801fe2894184210adc7",
      "c4b2d9ea52a28ac440438bbe5e95f3500a87906b1f487bbcf382784c89e17e93",
      "c67c501f06c6a0186bbf1b1baee104db91fc63eddf64e98ad8e1f91b554ccb53",
      "ca25fd5a83cc686d5835eb2a34c7eb4336a3c135833a02a3895401593f1e9013",
      "cb357714ee363e0579eb8cd9a056c40df17b0fcf761575b18856fd0c96f69de9",
      "cc108a7c72f74781b84b24b391897fd496e9b91b4d471dd513580d0968d74b2c",
      "cd68ddb81ceade5290e949cded30723b5dbc46230d1e81f0989f8a6a3f6bb07f",
      "d24a453762e86d3ace969f5b0cfa392ff0d242f11f256a0c3c3c8ef0dddb7c03",
      "d261dc468d0ed99e151787c77bcf60ea78abbbecc51f88b2d91075ba3d3198b5",
      "d3168a949c3b2161f942ceb395055e8f03c7b2bdabd6ae107b52089a610a6433",
      "d31a6d0ee81ae3e2ce79ebb923f60dd9c09da908cf272f7d7aafdf655165f5ea",
      "d4e7272f2e2bcb9a30a24bf611f178c05fc8d80dec0a695dd72bc3a3688d72d5",
      "d50b70cef23534d4b76d93fb323adbd5981cd0fe17330c3eb8c98750b6df7ce4",
      "d7ada4609be89508be238ea2cb305b453a2cf9af83ac5acc5da2a487c4d26157",
      "dc84cfcd78c945d22337803d3ae8470df8167b4e5209763dba13cde967989b8f",
      "dd049765c28c6d0ad832c89d59caedea75d8ff9ac28739fac94523be8715eb5a",
      "dd07cbb1c458cb444227a5a9d09dd8914e75f53f1932c53d257efacae481b870",
      "dd53904d9462645d46f38e1117ddf13b223381018b6ab9540a515fa75af9be44",
      "dde387a61395120df5dcf01ae11c88db54ca72b0fc27f0394a2ec7e45322c165",
      "e046ba276c9a2f00d124219b20429685109787a3050c4bd5844fb8b4c92560dc",
      "e07adcb31e49d411447576c891a01e0da9a59320a71116f35603ee0a36ea0fdb",
      "e0ddfeeef1ebde206f015cc920f480565060bd36db8b5caa3610b58fc0c1cd8a",
      "e1b95707dfdf3f090fd8152f60e909589ba7407a892c3987bd2c54172b294c57",
      "e1c923f8d6c9a54b12e6933d36adc68b8af5c6dadb15e28fe65d50bd1d89512c",
      "e3e3d460e1f0fe27a80861175685a224afbeb20ce6aab8224c12825c2d902255",
      "e4679f2a3121575bc66f275b2c0ecf893154549137b5e669d3aaa4a601300bf0",
      "e798ec2a015b7687c1487e20e093b13dd21678040390addf26dc2d46191d40c7",
      "e87cefca5b2d3023ffa2e0d7f98dfd9e1e88f32dd95a5c58bddf9cf2ea5ecf8e",
      "e92a6a606bc6566db9300847b61c85fa30a06b4366fd56f449c42774d3ca1344",
      "e9772c7ec1bf01e923a438c2965442595418edb0f15717d446dd4057729f67cf",
      "e980069456fc09ba00e56d84b2e45691171cee08106e6c19c4e80afedf057258",
      "ea002ae28f0d01578b906ba84f596461edad65149e63db0969346ec5b6b04901",
      "f094f902879921fbcba06dc98764e674c342038f1638ba784ef9b5b6e1fda0e6",
      "f1dca8191da6499ff66f1b85ab3035c5c9d04c1d553e8beb6e6eba3a12c25f9f",
      "f244b609e81749bc879892cb70958c20c7dcb5a0b0a0491693704b83d7f05a55",
      "f28153ae8851031e109a35af6f88bac7c890466e983fed6119f6a485d922049d",
      "f4b098acb360e52ea96bf656666635be282da37e50463880bb9b9ac6622619dc",
      "f6cd44a99f2b0308729134bd6919cd0765db5ded912106af21af9c19d479a1ca",
      "f7b6dabd4a0566f730d50e9f3695940acef0ac68b351f78945505b9d62e1c9ce",
      "f8c8416cf6c40ccf4fefb48517334f40f6df1270f2c0e3d4d11859cf1dcef5d2",
      "fa271bccbe94a38d29d6ee4614945784904c47b97a88145569a210659a969df5",
      "fa7060c640119018565b7a0aa99deaffef8fd30e7da9ac3e63360f940eb5a4bc",
      "fe747f5683c7a00bbbb66e2eff04d25a55f58e0d83369074f170aec3d741c990",
      "feda0bb56bf31080b12a0ff12a06e7cd74716188c022027eef47bbc942b2561e",
      "fedd2d5d41cf260f42ad8e636e3e7dd693997f2eeec6f2cb5be62106e2b9a76d"
    ]
  },
  {
    "id": "transport-invocation-replay",
    "plan": "§§14.4–14.5, 15 этап 8 — typed transport admission and one scoped receipt and settlement",
    "tests": [
      "tests/Feature/OperationContractTest.php",
      "tests/Distribution/fixtures/scenarios/bulk-transfer/probe.php",
      "tests/Distribution/fixtures/scenarios/transport/native.php"
    ],
    "changes": [
      "031df92358839bb7bd72c1ca7f4bd3fe601dfd9918085f686a970968ac47e226",
      "0c2599b8694b6a525cecca152d2372f6146a28721c7a10fe1ef9b24b90120ec1",
      "143d6d1a0aa4df74aa3413927fb6157e74e03f88c8280eed69e83abcbfff16da",
      "1a78b6375854a8120030cd7603db93d4e8c32ce1df6d17bd48b609cc7d24a561",
      "1b49b0599a0729d4e511483130e60c7af55d70b646a9e377c4fd71ffbee770ef",
      "240edc3294859fde004fe865391b4a2c52c8b160e3ea6fe430f24dc5a2ab9325",
      "25e994040e4ff33aa5db6d54b0de67d26d22903ad9c278d63b165d1da793db56",
      "3bda81b2300cef77eb12c8a73bbba42d55d3d7122b6e0db5213ff40844909e3a",
      "4347d72347ebee2f7e5ac39276b88692a1a1e942308c579eaf401d285eded948",
      "5c53fa2ad6e26ec8ddfce68e8942104e94cd972d4e62accc6c846e589d534644",
      "5e2909638c29227248f4dbfa880471801bc8f1e92527af810a6eeeb07a5b63fb",
      "60ead321ff95c280781622ba59e9c75397632da5910e85c3a442459d4ef50a0a",
      "6367ebf9e6a4c02c19cb3f01a696c1ce4d255a4a278a43bcaa9c85ce28dc67c9",
      "6709df6ff1ad3db3b6954e93c922ce3a2390e0904c56e5fc6d760be6a66eb533",
      "687b403ffb786107068f16533fd110346df81145fdaf95ce90d2f617bbe9e8f0",
      "72ed7c101eebf5dfa3cf091664dab3e356020ac24141ba8ed6c19663d31345ee",
      "7974bc9a023e43448fa0b2cd109833c494271ca305e43edd2c71ac5644668cc5",
      "7cb6af5cf9618fa941b1448df1ca85ae1bd9df6c6bfa83fe350ac71757dfe2f8",
      "96ab41ff0692a103082b4091f1ef9edc9d956b10a4b9a60a4bf8faec09f419bb",
      "9b002076c50fadd595279b7b58663f0264062154c004feafc031df2a3e78753e",
      "c863d259d5405f95ac759e1438f4b88db0d74ee4af619b7a8fcf7cc04c6fcf69",
      "d06aebe99ba7568f18805c207329e6a9eebcb04a130b2fd065a30483ddf6a74e",
      "db53d8117bafb77abdc625f7ed7366657c8aaff1be5dbda00673aefc946e9dfe",
      "ef8fce514aa6b90904bbfdd213893b5b57ded463d7354d5f998d78ea73d9d668",
      "f8d449f1a7da9dda2eb5fc25b7985b0537765cf4740d30bbb2521a635e570969"
    ]
  },
  {
    "id": "authorized-search-metadata",
    "plan": "§§13.3, 14.4, 15 этап 8 — typed metadata and a current-policy denial",
    "tests": [
      "tests/Distribution/fixtures/scenarios/transport/equipment.php"
    ],
    "changes": [
      "c0045df9d18df6473799d1c40c55567ca24fd5e0f3f0a07124758d59f9dd9379"
    ]
  }
]
```
