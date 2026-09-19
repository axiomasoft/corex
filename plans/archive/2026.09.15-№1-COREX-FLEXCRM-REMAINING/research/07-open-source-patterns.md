# Публичные OSS-паттерны: новое исследование whole-plan review

RAG:✅ Проверенные публичные source facts; RAG:— применение к CoreX и owning design choices.

Дата retrieval/source verification: 2026-09-15. Новые реальные нейтральные Perplexity
запросы 01–07 плюс непосредственное чтение implementation/tests закреплённых commits.
В queries нет внутренних названий, R/AC, путей или частных схем. Это сравнительное
исследование; новые зависимости/версии этим документом не принимаются.

## Фактическая история Perplexity

| Запрос | Покрытие / проблема | Raw ответ и SHA-256 |
|:--|:--|:--|
| 01 | Modules/panels/permission/DTO: обрыв, неподтверждённые версии; неполный | `artifacts/astra-whole-plan-review/perplexity-response-01.json` — `cc7ed1c6d0a6248b45f75d3ca8ea9c878c7ddee348e14754897c0e35b98ff3ad` |
| 02 | Permission: таблица, пропущен Filament; неполный | `artifacts/astra-whole-plan-review/perplexity-response-02.json` — `c703400a375490aca2ab0a3ef3c17ee717c557911a78d1d17e1db2111ed4be91` |
| 03 | Filament: содержательный, но ошибочное утверждение duplicate rejection; отклонено source-check | `artifacts/astra-whole-plan-review/perplexity-response-03.json` — `868c3ef7e0def94c19d3112486c4c703e5c85ae59409b1c8a3cf1c708fed5d5f` |
| 04 | Tenancy/storage/media: таблица без требуемого lifecycle анализа; неполный | `artifacts/astra-whole-plan-review/perplexity-response-04.json` — `b87b351ec46a4e85c673ab9d3bd2f2da0a60dd53f8eae9e39e01bb44f4161c8f` |
| 05 | Supplement web: DB/default connection, prefix и late conversion; содержательно завершён | `artifacts/astra-whole-plan-review/perplexity-response-05.json` — `ff778dd6021e7c4c5f54bc7f3db014a8af2727928ac3692615caa5c562f81c42` |
| 06 | Supplement web: module priority/providers и DTO validation boundary; содержательно завершён | `artifacts/astra-whole-plan-review/perplexity-response-06.json` — `119bdffc58d8cc66c8779aeaa2de64b4f1b0bbf28628a6e68674f75a836d84c3` |
| 07 | Supplement web: Filament registration и permission team/cache responsibility; содержательно завершён | `artifacts/astra-whole-plan-review/perplexity-07-response.json` — `282853b30821548045b67c823be25be85eae682422c13905b39c0b7eda9a652c` |

Точные query carriers лежат рядом с ответами. Incomplete/error никогда не считается
полным подтверждением пропущенного предмета; 05 закрывает пробел 04, 06 — module/DTO
пробел 01, 07 — registration/team/cache пробел 02/03. Первичные corrections ниже
приоритетнее synthesis. В нынешней серии не наблюдались login/timeout/No-answer ошибки;
такие ошибки есть в **истории прежних фазовых** P1–P11 RAG и сохранены там отдельно.
Владелец уведомлялся о неполноте и ошибочных claims. Ни API success, ни закрытие browser
window не доказывают смысловую полноту ответа.

## Закреплённые источники и состояние проектов

`upstream/source-projects.json` содержит observed repo metadata, tag→commit, archive URLs,
SHA-256 и время commit/release. В compressed metadata retrievals поля sha256/bytes
относятся к decompressed response body; stored gzip SHA256 проверен отдельно в
checks/retrieval-digest-check.json и whole-plan finish seal. Archive SHA256 относится
к самому tar.gz. `upstream/source-trees.json` фиксирует полные Git trees
(truncated=false для всех семи). Release archives исключают test directories; нужные
тесты отдельно получены через raw URLs по **тому же commit**, список/hash в
`upstream/test-retrievals.json`. Исходники и тесты прочитаны; suites не запускались.

Все семь canonical repositories на момент чтения имеют archived=false и обновления
в 2026 году. Это наблюдение активности, не обещание поддержки/security audit.
Лицензии MIT подтверждены actual LICENSE/LICENSE.md и composer.json; импорт кода не делался.

| Проект | Выбранный comparison tag / immutable commit | Объявленные constraints / предел |
|:--|:--|:--|
| [filamentphp/filament](https://github.com/filamentphp/filament/tree/2a8d99a775f837e3ba05b47deb1daa053da1a346) | v5.8.1 / `2a8d99a775f837e3ba05b47deb1daa053da1a346` | PHP ^8.2; UI package с self.version sibling closure, Laravel compatibility следует полному graph, не root composer |
| [nWidart/laravel-modules](https://github.com/nWidart/laravel-modules/tree/6aa5461e57ac89d0dc8377cdda074c10487f92f9) | v13.0.0 / `6aa5461e57ac89d0dc8377cdda074c10487f92f9` | PHP ^8.3; composer-merge-plugin ^2.1; версия пакета сама по себе не доказательство полного Laravel matrix |
| [spatie/laravel-permission](https://github.com/spatie/laravel-permission/tree/60e8ed5b2fbf043c2264433fc2680c76b8b66aa6) | 8.3.0 / `60e8ed5b2fbf043c2264433fc2680c76b8b66aa6` | PHP ^8.3; Illuminate ^12|^13 |
| [spatie/laravel-data](https://github.com/spatie/laravel-data/tree/230543769c996e407fec2873930626aed7dd0d3b) | 4.23.0 / `230543769c996e407fec2873930626aed7dd0d3b` | PHP ^8.1; Illuminate contracts ^10|^11|^12|^13 |
| [archtechx/tenancy](https://github.com/archtechx/tenancy/tree/c4cf5dc6d557fb7404feb11a6ddfe0985ddd9526) | v3.10.1 / `c4cf5dc6d557fb7404feb11a6ddfe0985ddd9526` | v3 comparator, PHP ^8.0; Illuminate support ^10|^11|^12|^13; CoreX target v4 сохраняется |
| [thephpleague/flysystem](https://github.com/thephpleague/flysystem/tree/f7fb152932f30072d573510cbd4dd657d6475b25) | 3.36.0 / `f7fb152932f30072d573510cbd4dd657d6475b25` | PHP ^8.0.2; filesystem abstraction, не Laravel/auth framework |
| [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary/tree/32bd3926b1065c4dc154789b9c6b17567acaaa15) | 11.23.8 / `32bd3926b1065c4dc154789b9c6b17567acaaa15` | PHP ^8.2; Illuminate ^10.2|^11|^12|^13; image/conversion зависимости требуют собственного runtime graph |

Flysystem выбран по tag3.36.0, commit date 2026-09-02; GitHub releases/latest вернул
старый3.16.0. Эти разные observations сохранены в metadata; release date3.16 не
приписывается3.36. CoreX installed/runtime compatibility не выводится из этих constraints.

## Непосредственно проверенное поведение

Все implementation/test пути ниже относительны соответствующему immutable source tree
из таблицы. Ссылка на commit + path обозначает точные проверяемые байты.

| Проект | Implementation | Test/source corroboration | Вывод и предел переноса |
|:--|:--|:--|:--|
| Filament | `packages/panels/src/PanelRegistry.php`; `Panel/Concerns/HasPlugins.php` | v5.8.1 source: panels[id]=panel; plugin->register до plugins[id]=plugin. Сравнительный3.x source тоже keyed assignment; `tests/src/Panels/Configuration/DarkModeTest.php` проверяет defaults/explicit overrides/closures (прочитан); прямой duplicate rejection test не найден в просмотренном наборе | Повторный ID перезаписывает lookup; registration side effects уже могли произойти. CoreX explicit duplicate rejection — собственный обязательный invariant, не заимствованная гарантия |
| laravel-modules | `src/FileRepository.php::scan/getOrdered/register/boot` | `tests/LaravelFileRepositoryTest.php`: enabled/disabled, lookup, macros; getOrdered source сортирует по priority, равные priority возвращают0 | Provider lifecycle и явное activation полезны; scan и static cache, macro extension, overwrite lowercase-name не заменяют существующий CoreX compiler/DAG/8 extenders |
| laravel-permission | `src/PermissionRegistrar.php::initializeCache/setPermissionsTeamId/forgetCachedPermissions`; `src/Traits/HasRoles.php::roles` | `tests/Traits/TeamScopeTest.php`, `tests/Integration/CacheTest.php`: team isolation и разные invalidation semantics role/permission definitions vs user assignments | Team selector не физический account resolver; loaded model relations и worker cache имеют отдельный lifecycle; cache не final authorization fence |
| laravel-data | `src/DataPipes/ValidatePropertiesDataPipe.php`; `config/data.php` OnlyRequests default | `tests/ValidationTest.php` L2479–2531 explicit validateAndCreate; L2790–2820 ordinary array accepts invalid In attribute until Always. L2822–2842 mapped attribute case skipped upstream | DTO construction не universal input validation; P6/P8 explicit validators/unknown fields/bounds обязательны. Не импортировать reflection mapping/coercion как typed tool-schema conformance |
| tenancy | `src/Bootstrappers/DatabaseTenancyBootstrapper.php`; `src/Database/DatabaseManager.php` | `tests/BootstrapperTest.php`: DB/cache/Redis/files separate when specific bootstrapper configured, A→B→A. connect purges/rebuilds tenant default and end reconnects central | DB default switching не меняет произвольное явное connection и не добавляет authorization. Отдельный filesystem bootstrapper нужен для filesystem; current CoreX Stancl v4 adapter квалифицируется самостоятельно |
| Flysystem | `src/PathPrefixer.php`; `src/WhitespacePathNormalizer.php` | `src/WhitespacePathNormalizerTest.php`: bounds escape rejected; internal a/../b allowed default, explicit relative traversal=false rejects it; corrupt input rejected | Prefixer просто соединяет путь; normalizer и authorization — разные слои. CoreX retained-handle/current scope checks сохраняются, SDK/provider capabilities проверяются отдельно |
| medialibrary | `src/Support/FileRemover/DefaultFileRemover.php`; `src/Conversions/Jobs/PerformConversionsJob.php` | `tests/Feature/FileAdder/MediaConversions/DeleteMediaTest.php`: clear/delete removes local files, preserving keeps them. Job deleteWhenMissingModels не останавливает уже запущенную конверсию | Правильный storage/path ownership полезен. Эти тесты не доказывают attach/delete race, late writer, unknown remote delete или CoreX usage authority; staged attempts/tombstones/horizon P4 остаются необходимыми |

Для Filament3.x прямой сравнительный source URL:
https://github.com/filamentphp/filament/blob/3.x/packages/panels/src/PanelRegistry.php.
Это mutable историческая ветка, не замена закреплённому v5 source. Ошибка query03
отклоняется по обоим наблюдениям; CoreX не получает UI dependency.

## Choices: независимые origins, alternatives, owning obligations

| Решение | Независимые primary origins | Alternatives и выбранное локальное применение | Owning proof |
|:--|:--|:--|:--|
| C1 Явные contribution/identity/collision | Filament + nWidart + AzGuard | Второй registry/discovery engine и macro-patching отклонены; используем existing compiler/DI, explicit duplicate deny | P4.1/P8.1/P8.5 current metadata/activation; P9 non-CRM installed |
| C2 Контекст и актуальная policy | AzGuard + Laravel + OWASP; Spatie corroboration | Singleton actor/cache-as-authority/nullable-any-account отклонены; explicit context, scoped state only where needed, finally restore, sameSQL final fence | P2 DI/gates, P3 final write, P4/P8 policy, P9 private adapter, P10 actual timing |
| C3 Typed input с отдельной validation | Spatie Data + JSON Schema + PHP | Construction/reflection/coercion как proof отклонены; explicit offline profile, typed shape/value limits и actual library conformance | P2 exact DTO defaults/BC, P6 codec/presence, P8 DESC-06–09 |
| C4 Physical storage/location≠authority | Stancl + Flysystem + Laravel | Row-level rewrite/доверие path prefix/произвольный connection из payload отклонены; existing TenantStorage и trusted physical context | P3 storage/context/retained handles, P4 media, P6 files, P9 installed, P10 real provider |
| C5 Durable effects и поздний worker | Spatie medialibrary + Laravel + AWS + PostgreSQL | afterCommit-only/cache uniqueness/queued missing-model deletion как cancellation guarantee отклонены; intent/claim, sameSQL state, immutable staged attempts и reconciliation | P4 media/notifications/digest, P5 wakeup, P6 index, P7 signals; P10 actual external effect |
| C6 Source/runtime/release/consumer evidence | Composer + Git + SLSA/W3C | version label/summary/test existence как acceptance отклонены; отдельные identities, exact immutable subjects и independently accepting actor | P1 provenance, P9 intake vs derived build, P10 actual environment, P11 verifier/consumer |

Origin independence не считается числом URLs: несколько Spatie repositories — один publisher;
Laravel12/13 — один publisher; два RFC — один IETF. AzGuard — локальный comparative source,
не независимая публичная проверка собственного CoreX. Дополнительные primary premises
для C2/C3/C5/C6 и всей программы проверены в сохранённых фазовых RAG:

- [Laravel container](https://laravel.com/framework/docs/13.x/container): scoped lifecycle;
  [OWASP authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html): текущие проверки и deny default.
- [JSON Schema validation2020-12](https://json-schema.org/draft/2020-12/json-schema-validation): type/value constraints;
  [PHP readonly properties](https://www.php.net/manual/en/language.oop5.properties.php): shallow readonly, не deep validation.
- [AWS transactional outbox](https://docs.aws.amazon.com/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html): durable dual-write mitigation и duplicate handling;
  [PostgreSQL row locks](https://www.postgresql.org/docs/16/explicit-locking.html): сериализация в transaction, не external IO fence.
- [Composer lock/install](https://getcomposer.org/doc/01-basic-usage.md),
  [SLSA provenance](https://slsa.dev/spec/v1.2/provenance),
  [W3C PROV-DM](https://www.w3.org/TR/2013/REC-prov-dm-20130430/): разные subject/activity/actor boundaries.

Здесь эти ссылки обозначены как **унаследованная primary verification**, а не новые
Perplexity ответы или новые текущие website reads. Raw фазовые carriers сохраняются;
whole-plan finish обязан связывать их hashes вместе с новым research.

## Итог и остаточные обязательства

Новое mandatory исследование содержательно закрывается дополнительными05–07 и прямым
source/test сравнением. Это не утверждение идеальности архитектуры, полноты upstream
тестов, установленной совместимости или production acceptance. Явные choices остаются
локальным выводом из принятых A01–A09, R/AC и кода CoreX, а не предписанием Perplexity.

Не принимаем dependency только из популярности или license metadata; если implementation
выбирает exact upstream, owning item проверяет actual immutable ABI/full graph/license,
source suite/required conformance и declared runtime matrix. Все перечисленные runtime
proofs ещё впереди. Никакие product item/DI/AP/публикация этим документом не выполнены.

### Хранение raw PHP tests

12 upstream test bodies сохранены как `.php.txt`: содержимое и SHA256 совпадают с
immutable upstream retrieval. Это evidence, поэтому оно не должно участвовать в
Pint/PHP source discovery монорепозитория. `upstream/test-retrievals.json` содержит
раздельные original source path/URL и local artifact path; upstream suites не запускались.
Первый downstream RED из-за raw `.php` discovery сохранён в checks, затем причина исправлена.
