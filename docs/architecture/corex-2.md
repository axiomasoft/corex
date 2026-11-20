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
    "changes": [
      "008c9e53b8d44c3733fbed5656e92ab3d29f99417dbc3670bc64b985eecacb8d",
      "017176ff43a823b365b2fc63f2165783bb60b8f2ca0def070a5231b8243dd0ba",
      "01774c8e94891fdfd242618278507e6d38a5f3d9c26f7c328c3a7c1780f0ebaa",
      "0933fcf81fb9a28722043af136c90157909b41810d42a2186e8c3ca0bdb6a75e",
      "09b188ace5a4fb7661e814e7794bde1ecada1c351ee34db1a8406cb41848ddbc",
      "0a0a234e77bc0c2805414500785d7a8fe1c8e076cf28dc131a9c74e4fe16b8b4",
      "0b76084a21a7f7a3419222c8ffb89cc5dde7d99f19c149471fc62a97e5e9cf1a",
      "115ce3ae3b4cc2ea9e3781cd948d111462f3c57a98130f841298a32ac39c4af8",
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
      "280683d4f27611b91597502e3640a2d0ebe110f26d74a4d46eaf0a5cbcf27d10",
      "382f4b68cc46fae0cbe80875af622ad1a039ef39564911b97a77d1832a174ed1",
      "3f2c8c1dee7d5171c5d78d928e33f275bf8790cae630c1681983469113a05516",
      "5b3e88417d9c36026df48ed0366ae29de50cc45f8875146585cf90ab144fc489",
      "6357b84f14bd7d630d21668f99e2b9b6d189916f404264cbd0c9567c79b10178",
      "68b2c46a3d603788495322b9ec3e5612596491b6bec3e462125b787471201e7d",
      "8a5864442e4e6294f875b559a74fbea6ef03ea3a663b1a5e1e825bc303356b0e",
      "8cf1cb3dc0ba637e512acc5c3ac735b9c200075452a00379ba8afcffc1d11048",
      "99f7202a914152d6dcc233d902f47389ba496138cebae2b40ed170c48f005d39",
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
      "071192966979ce5fbe4810ae7e4e40e84790ebbf1d2c8794434264fd93741dea",
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
    "id": "attribute-fact-publication",
    "plan": "§§12.1–12.2 and stage 2 — Custom Fields appends on the same connection with explicit causation",
    "tests": [
      "packages/custom-fields/tests/Pg/AttributeOutboxTest.php",
      "packages/custom-fields/tests/Unit/AttributeMessageTranslatorTest.php"
    ],
    "changes": [
      "1a85ea66f9a5cf1ea45b743a2860586b6f2b2a3d9919b94f7b8b343ceef93184",
      "657acb0209e5b8ee967aba5337e47e5286dbfbe86b31a222fe74919cd43ec4aa"
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
      "packages/recurring-tasks/tests/Feature/TaskCompletionCaptureTest.php",
      "tests/Distribution/fixtures/scenarios/provider-unknown/probe.php"
    ],
    "changes": [
      "02642169851c6cf38d19f90427b672fb665b9b9d01de789df074b3a12edb4861",
      "2847ee2e40634ca921dac678368dfc58ff5fd00b146e8bdce105c9d1150a0c8b",
      "360a8e8c8e2648a27d0c218a3c98638ce782dfe8936cf3aea22cb7b6751985e4",
      "377967f33a89ffbbc54ed95b7b35e84a7510a5b768746115f5ab5a49b1dc675a",
      "44f895d99aa28cae2f7017df7a3b14f5f8968b10bf855badf4aab3d83a1ffe44",
      "4bf855afcfd360b424345ef6378509052d0356aba6a7af02d98fb53b5c3e1959",
      "50e97b9aea14d4d9c040876d615f3c2d300dfe98e93d010f992d71b570995fde",
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
      "cc2bcd81eec5a6f4cb4e7cbd95d8f68ae8ba0b6a072ab0e07ff4eb24e53c81b0",
      "e1bb364c9b4d40fc7ad6ff593eedc7fd789fee194bf6dc99a2eb3e68513b17cf",
      "fcb6ab4614cde9b5288f404bc36871f9ca4fed6a7a30d94a1eca339df9d4fc69"
    ]
  }
]
```
