# CoreX 2.0 · Границы совместимости

Статус — принято для реализации. База независимого сравнения — `1c11e0e35961409631cf68ebb28c7d2cd53ebb91`. SHA фактически установленного 1.x артефакта и digest его поставки фиксируются отдельно перед переносом.

## Решение

CoreX 2.0 перевыпускает 20 пакетов на общей Composer-линии. Мосты Search Custom Fields и Tasks Workflow увеличивают состав до 22 пакетов в этапах 5 и 6. FlexCRM сохраняет собственный мажор. Публичный distribution fixture устанавливает выпущенную версию Tasks 2.x с PHP `^8.4`, Illuminate `^13.0` и `minimum-stability: stable`. CoreX устанавливает схемы версии 2 в чистой целевой базе через Packman. FlexCRM ведёт версию своего набора отдельно. Старые сообщения, данные и журнал читаются одноразовым инструментом переноса.

Следующие границы входят в CoreX 2.0. Названия строк отсылают к карте замен §11.4 и уточнениям §§7, 12–14 плана. При реализации этапа соответствующие конкретные изменения и тесты записываются в блок доказательств.

| Этап | Строки плана и изменение для потребителя | Проверка |
| --- | --- | --- |
| 0 | §§11.1, 11.3, 15.3 · Новые Composer constraints, branch aliases и разрешение path repositories. CoreX schema sets версии 2, с прежними IDs и scopes. | Детерминированная split-сборка, standalone consumers, реестр и установщик schema sets. |
| 1 | §§12.1–12.3, 14.1–14.2 · `DataScopeFactory` выдаёт scope v3 от текущего principal. `StoredWorkRunner` сверяет proof владельца и immutable reference. Хост хранит допуск, tickets и использованные epochs вне tenant backup. `DataScopeAuthorizer` проверяет typed use. Scheduler сохраняет scope, версию обработчика и digest запроса. | `worker-scope` проверяет A→B→A, вложенные jobs, root sweep и очистку контекста. `binding-restore` проверяет rebind, restore и ожидание активных tickets. `workspace-sweep` проверяет W1/W2, рестарт после claim и rollback на просроченном token. |
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
  }
]
```
