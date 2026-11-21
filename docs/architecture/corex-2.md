# CoreX 2.0 · Границы совместимости

Статус — принято для реализации. База независимого сравнения — `1c11e0e35961409631cf68ebb28c7d2cd53ebb91`. SHA фактически установленного 1.x артефакта и digest его поставки фиксируются отдельно перед переносом.

## Решение

CoreX 2.0 перевыпускает 20 пакетов на общей Composer-линии. Мосты Search Custom Fields и Tasks Workflow увеличивают состав до 22 пакетов в этапах 5 и 6. FlexCRM сохраняет собственный мажор. Публичный distribution fixture устанавливает выпущенную версию Tasks 2.x с PHP `^8.4`, Illuminate `^13.0` и `minimum-stability: stable`. CoreX устанавливает схемы версии 2 в чистой целевой базе через Packman. FlexCRM ведёт версию своего набора отдельно. Старые сообщения, данные и журнал читаются одноразовым инструментом переноса.

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

Equipment Registry, публичный Laravel consumer, Octane, пять extension fixtures и `products/flex-crm` проверяются в репозитории. Расширения Custom Fields, Search, Transfer и Workflow переходят на PHP `^8.4` вместе с пакетами CoreX 2.0. Перед production cutover владелец релиза получает у операторов текущий inventory установок, `composer.lock`, зарегистрированные Laravel providers и манифесты Packman, а также используемые REST/MCP SDK и webhook schemas. Эти артефакты разрешают актуальные PHP bindings, зависимости и runtime endpoints.

Production FlexCRM и Equipment deployments, частные расширения для Search, Workflow, Custom Fields, Panels и Import/Export, внешние REST/MCP клиенты, webhook consumers и конфигурации реальных worker недоступны в базовом checkout. Их владельцы сверяют соответствующие API/SPI с установленной версией и повторяют сценарии со своими декларациями. Packman Panels подтверждает SavedViews, хост Tenancy — physical tenant binding, Vaulter — CAS blobs и pins, AzGuard — решения авторизации. Результаты входят в release evidence до production cutover.

## Доказательства конкретных изменений

Custom Fields регистрирует версии codec и определения через Tagged-провайдеры. Хост, которому нужен concurrent DDL, подтверждает прямой PostgreSQL writer и управляемую схему через `AccountConnectionResolver`. Опции `corex_fields_ddl_transport=direct-v1` и `corex_fields_ddl_namespace` проверяются вместе с текущим физическим scope. `corex:custom-fields:work` выполняет одну ограниченную фазу по умолчанию. Владелец удерживает session advisory lock до записи результата под актуальным claim. В конфигурации PHPStan зафиксированы ровно два срабатывания правила для pooled connections в `PostgresDdlExecutor.php`.

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
      "dee992eb4d17f9b8aa4db9f8d319c185ba1b4f2ba1f4ddcc7998b64c52c8fb68",
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
      "d54eb754df0a8addd252cfb3fdef28aafaebb883f76c985e89a1c5f7cd14b20c",
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
      "215b5a72f7e6ae9640721a7557422f019949eff314ecb6d019fe9be3edd8d3e8",
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
      "88a72563ca99bcb0887628000b1e822df48581383f3ab46cb65532b3109e99ab",
      "8d9d09f868a128a3efbac0c6e098a6a88457abc2b96e60503df3d1523c467902",
      "937ee9b41c44e69ba07136677b6eb90b5b32afe52f00448119159241ba885936",
      "a3cf26743df7b6c0ab1b35a89d5a8e6669dc6b9d9e364b302880925cea396b16",
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
      "0ae94d1bc0f4de6100567d43a03b2af67c58d70b81557251d4d5b53c3b026a83",
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
      "58388f1944a375b89436d1211ebc7e3c2e52ae57d5cb46f5ca51a4e9ec15e92d",
      "58e6b510a543af14c4c38cd099f7e414f85d4bc3efce0c5212f813347a6b4f24",
      "5954762caaf4a791c66a8ed38ee41032118f1c6420ea1f831a9a8d9d3f6a686c",
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
      "8bc38f46f432dac4a123f67536756b1ada0737cd7adb2caa8c794f4827265ff9",
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
      "b9449491a40e1b9c91d7ae1b106d61119a149cff474bcb72fc82d4f2e2a4fc8b",
      "c62addbb2a39a5e75c0e2bae89e0ddec1181623046afe6dce550f56749a5250b",
      "c6dfe09a4eb2aaf187d4fdc24e5786d65f2234256a0178dbfde891c895a8183e",
      "c7e46273444491261d75efe48d3cb3317c50561d1d06a3b8b3630ee1d2c7ea12",
      "c9b11dca862d656375a154cc525f7f5a2dc22966383bc74c09c2bb12c8629423",
      "cc6a4cf4b49f309016616a8506a61ac57530563e03aa1699c8cb61c1c8ba69e4",
      "d312b230492c59feb8c08edaa0fa7fdcc254b251247db637036f1b5c5a2e1730",
      "d555641631620d2d1304384a3209bcf9eeab4bca83c3ede90ded45bcde44f204",
      "d685c5c96e9e13852b6c4883c76dbb5c922b124119db440db2a1a60038af618a",
      "d9c667d666ad9cb8aa4ee19313d7af8aa09643b986a33910e57176d747afce04",
      "def1161b265b552099865006decf0f00b76e6bd742a424fb4004ac9e1a579a37",
      "df2d4cd4c62778eacdf6af1921de8495cfa27b6e16776d0d4a2887f657e07b28",
      "e03cfcab91a99702ca29dcb2f87299fa2e2c04a9f1defdd3c05aca953849c1d6",
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
  }
]
```
