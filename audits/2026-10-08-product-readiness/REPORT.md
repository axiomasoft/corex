# Дополнительный аудит CoreX после интеграции hardening

Дата: 2026-10-08. Проверенный main:
`fb9acbe6826be0f1786bea5da4efa5a4a605fe43`.
Аудит начат после squash-интеграции improve/core-hardening-1.
Решения по самой ветке находятся в [REVIEW.md](../2026-10-08-hardening-review/REVIEW.md);
[integration.json](../2026-10-08-hardening-review/evidence/integration.json) связывает
base, tip бота, доработки ревьюера и итоговый main.

**Исходники hardening приняты. Стабильный релиз 1.0 пока не готов.**
Новые воспроизводимые сценарии: общая actor-квота у user/agent с одинаковым ID,
тихое принятие неверной структуры installed.json, успешный exit у пропущенной
проверки через старый plan marker. Дополнительно подтверждены уже известные
release/debt ограничения и несогласованная документация.

Это аудит выбранных рисков: identity/authorization, composition, lifecycle metadata,
границы пакетов, проверки качества, release tooling, секреты и locked dependencies.
Он не заявляет построчного ревью всего монорепо или текущего hosted-release proof.

## Приоритеты

P1 — закрыть перед стабильным релизом; P2 — исправление поведения/достоверности проверок;
P3 — чистка документации и metadata. Объём — ориентир следующей задачи, не оценка SLA.

| ID | Приоритет | Находка | Статус | Объём |
| --- | --- | --- | --- | --- |
| RDY-01 | P1 | Независимый PHP BC-дифф отсутствует | Принятое временное ограничение, блокер стабильного релиза | M |
| RDY-02 | P1 | Release receipts stale, candidate относится к старому commit | Подтверждено повторным DoD check | M |
| RDY-03 | P1 | Unused-public gate красный: 179 stale + 177 reachability findings | Подтверждено; это не список доказанно мёртвых методов | M–L |
| RDY-04 | P2 | Actor budget не различает principal type | Новый runtime reproduction | S–M |
| RDY-05 | P2 | Валидный JSON неверной формы тихо обнуляет installed-set metadata | Новый runtime reproduction; уточняет P-05 бота | S |
| RDY-06 | P2 | Arch-пробы и generated consumer мешают другим scanners | Наблюдавшаяся воспроизводимость/изоляция | S–M |
| RDY-07 | P1 | Ранее утёкший ключ остаётся в Git history | Известная SEC-01; HEAD чист, валидность ключа не проверена | Отдельная операция владельца |
| RDY-08 | P2 | Старый plan marker возвращает success, не запустив gate | Новый isolated reproduction | S |
| RDY-09 | P3 | Release README неверно описывает CI; старый publisher привязан к отсутствующему плану | Подтверждено исходниками | XS–S |
| RDY-10 | P3 | Synthetic Composer metadata теряет MIT license AzGuard | Metadata gap, не отсутствие лицензии в коде | XS |

## RDY-01 — независимый контроль PHP BC

В текущем composer.json и ci.yml нет bc-check. PHP API freshness сравнивает
код с snapshot внутри этого же checkout; snapshot можно обновить одновременно
с breaking signature. CHANGELOG и REBASELINE обеспечивают видимость изменения,
но сами не вычисляют совместимость. Это соответствует временному решению ревью,
а не является зелёным BC-результатом.

До 1.0 нужен отдельный CI BC-дифф against accepted release base, затем против
опубликованного тега. Он должен учитывать Api/Spi Implement/Spi Call, parameter names,
enum values, defaults и закрытый список deprecated aliases; исключения — точные,
review-bound, без пути в plans/. Negative fixture: добавить required parameter в
stable SPI и обновить snapshot — BC gate всё равно обязан отказать.

Не возвращать прежние 27 plan-bound разрешений как универсальный bypass.
Доктрина и [semver-policy](../../docs/architecture/semver-policy.md) уже требуют
закрыть это **перед**, а не после стабильного релиза.

## RDY-02 — актуальность release candidate

Повторный check release profile на объединённом main дал **25 green / 2 stale**:

- DOD-01: freshness input drift, scripts/package-docs-gate.php.
- DOD-21: stale candidate digest, P17-upgrade-receipt.json.

Проверка использовала frozen release-cohort с source
`7ab843485577bf840cc0db6bd66081fbcafd3331`, тогда как текущий source — fb9acbe6.
[dod-release.json](evidence/dod-release.json) сохраняет оба SHA и точные diagnostics.
25 green не равны разрешению публикации нынешнего main.

Следующая задача: собрать candidate из текущего принятого source и выпустить receipts
через их owners/producers. Сохранить проверку source/digest/dirty-tree и refusal
boundaries. Не редактировать hashes вручную, не переносить stale receipt в статус green.
Проверки отказа preflight возвращены в CI при ревью; публикация не выполнялась.

## RDY-03 — gate неиспользуемой публичной поверхности

scripts/quality/unused-public.php --check выдаёт 179 ENTRYPOINT-STALE и 177
UNUSED-PUBLIC; вместе с завершающей строкой это 357 строк вывода.
[Полный результат](../2026-10-08-hardening-review/evidence/unused-public-final.log).
ci.yml действительно запускает этот gate, поэтому локально зелёные suites не
означают зелёный весь CI.

Stale source/root evidence лишает scanner доказательства framework callbacks;
часть UNUSED-PUBLIC может быть следствием этого, а не мёртвым API. Разбирать по
владельцу пакета: подтвердить entrypoint/receiver/контракт, переиздать только его
hash-bound evidence, затем удалить доказанно недостижимое. Массовая замена hashes
или разрешение всех framework methods скрыли бы незамеченную смену поведения.

Критерий приёмки: zero stale, каждый оставшийся method действительно достижим
либо удалён с соблюдением compatibility policy; planted unused method остаётся красным.

## RDY-04 — principal type отсутствует в actor budget

В [BudgetAdmission.php:118](../../packages/operations/src/Cost/Internal/BudgetAdmission.php)
scope получает только principalId(); в
[SqlOperationBudget.php:312](../../packages/operations/src/Cost/Internal/SqlOperationBudget.php)
actor key состоит из installation/account/id. Новый Principal определяет
идентичность парой (type,id), как уже исправленная идемпотентность.

Реальный reserve composition для user:5 и agent:5 передал actorId=5 в обоих
случаях; реальный SQL key builder дал одинаковые actor bucket keys.
[runtime-probes.json](evidence/runtime-probes.json), раздел principal_budget_collision.
Проба использует PDO sqlite::memory: и не пишет в PostgreSQL.

При включённом actor-level enforcement один субъект расходует квоту другого и
может вызвать его 429. При дефолтном global-only режиме отдельная actor-квота не
enforced; утечку ответов эта проба не доказывает.

Исправление: namespace actor identity по principal type или добавить тип в BudgetScope
и версионировать keys; учесть anonymous sentinel и миграцию существующих buckets.
Regression: одинаковые id разных типов изолированы, один субъект через разные
clients сохраняет account-wide actor bucket, конкурентные reserve остаются атомарными.

## RDY-05 — неверная форма installed.json принимается за пустую установку

JSON_THROW_ON_ERROR защищает синтаксис, но не форму. Все три читателя —
[ModuleDiscovery:58](../../packages/core/src/Modules/Internal/Discovery/ModuleDiscovery.php),
[InstalledPackages:87](../../packages/core/src/Modules/Internal/Compiler/InstalledPackages.php),
[LifecycleRegistrar:88](../../packages/core/src/Modules/Laravel/LifecycleRegistrar.php) —
принимают null, строку, packages:null и packages:"invalid-list" без ошибки.
Проба получает 0 modules, 0 packages, 0 codeVersions для каждого входа:
[runtime-probes.json](evidence/runtime-probes.json).

Следствие повреждённой deployment metadata: discovery теряет модули/версии,
lifecycle может использовать fallback 0.0.0 вместо установленной версии.
Это операционный fail-open относительно достоверности manifest, а не доказанная
возможность внешнего клиента менять deployment-файл.

Сделать один строгий Internal reader с поддержкой Composer list/object formats:
packages обязан быть списком корректных package records; отличать отсутствующий
файл/injected Testbench seam от существующего файла неверной структуры. Tests:
обе законные формы, пустой список, неверный root/list/record, отсутствие файла.

## RDY-06 — scanners зависят от общего изменяемого дерева

PackageGraphTest и другие отрицательные Arch-пробы временно меняют packages/*/src.
Во время параллельного запуска Pint увидел временную подмену DigestTick, а API scanner
увидел RuntimeGlobalsProbe; после cleanup повторные проверки зелёные.
[Временный lint failure](../2026-10-08-hardening-review/evidence/lint.json),
[исходный API diagnostic](../2026-10-08-hardening-review/evidence/php-api.log),
[чистый Pint](../2026-10-08-hardening-review/evidence/lint-final.json).

Кроме того, PackageGraphTest пишет один общий /tmp/corex-package-graph-test-report.json;
два checkout/worker могут перезаписать отчёт друг друга. Нужен уникальный sandbox
на test/process и --root для gate; planted нарушения работают только в его копии.

Отдельная граница: deps/Rectор сканируют вложенный ignored vendor у tracked
packages/core/tests/Release/octane-consumer. Core scan захватил 7773 файла и выдал
посторонние dependency complaints; тот же gate на 436 tracked файлах чист.
[Полный local scan](../2026-10-08-hardening-review/evidence/deps.log),
[чистый срез](../2026-10-08-hardening-review/evidence/deps-core-clean.log).
Rector прошёл для всех 183 изменённых PHP-файлов; общий локальный запуск остановлен,
его результат не заявлен зелёным.

Вывести generated consumers за пределы source scan либо исключить именно их
generated dependencies, сохранив проверки committed consumer code. Acceptance:
cold checkout и дерево после consumer install дают одинаковый gate verdict;
Arch и source scanners можно запускать одновременно без чужих probes.

## RDY-07 — история SEC-01

HEAD и новый diff прошли secret-scan. 17 логов очищены, два удалены; provenance
и старые/новые digest сохранены в
[archive-redactions.json](../2026-10-08-hardening-review/evidence/archive-redactions.json).
Известные исторические fingerprints остаются в .gitleaksignore.

Бот написал, что ключ неактивен; этот аудит не проверял его у провайдера. Перед
публичным релизом владелец должен подтвердить отзыв/ротацию и решить вопрос
координированной очистки истории/форков/кэшей. Force-push всех веток и тегов не
выполнялся: обычное поручение интегрировать main не разрешает такую операцию.

## RDY-08 — legacy defer marker маскирует невыполненный gate

[ci-deferred-guard.sh:5](../../scripts/ci-deferred-guard.sh) при наличии
scripts/.plan-close-deferred возвращает 0 до exec. Проверено в изолированной
копии: команда, которая должна вернуть 47, не выполняется; guard возвращает 0
даже при CI=true/GITHUB_ACTIONS=true.
[deferred-guard-probe.json](evidence/deferred-guard-probe.json).

Этот wrapper используется root test/test:pg/test:arch и рядом package/config gates.
В текущем checkout marker отсутствует; приведённые проверки действительно выполнены.
Риск — оставленный старым plan workflow marker делает успешный status ложным.
В планах сейчас нет active плана, требующего этого режима.

Убрать legacy bypass либо сделать explicit deferred результат, который CI/release
считает отказом, а локальный пользователь видит отдельно от passed. Test: marker
при release/CI не запускает команду и обязательно даёт nonzero; отсутствие marker
прозрачно возвращает exit дочерней команды.

## RDY-09 — точность release tooling docs

[release/README.md:25:25](../../release/README.md) утверждает, что
scripts/distribution/*.py не выполняются в CI. distribution-proof.yml запускает
pristine-proof, verify clients, profile-proof, upgrade-proof и remaining export.
Это неверное разделение ответственности. Описать отдельно manual seal/publication,
CI qualification и frozen historical evidence.

publish_exact.py ссылается на отсутствующий неархивный plans/2026.09.12-№2...
и относится к старому D17 grant. Не включать его в текущий release path; сохранить
полезный transport отдельно, затем архивировать одноразовый executor. Не подменять
его старый grant разрешением публикации нового candidate.

Совместимость core/PACKAGE.md с Laravel ^13 исправлена при ревью. Удаление отдельных
Illuminate constraints и перенос Departments в другую ступень без доказательства
не приняты. Optional operations→transfer оставлен предметом отдельного port/adaptor
design; он задокументирован, guard присутствует, авария boxed install не доказана.

## RDY-10 — license metadata синтетического AzGuard package

В composer.lock для axioma-studio/azguard-core 0.3.0 license отсутствует,
поскольку embedded package repository в root/bridge composer.json его не указывает.
Installed vendor содержит MIT LICENSE и license:MIT в своём composer.json.
Это разрыв metadata, а не отсутствие лицензии в поставленном коде.

Добавить MIT к обоим синтетическим описаниям и переиздать lock metadata без
незапрошенного обновления остальных зависимостей. Unreleased bridge и необходимость
application-owned Composer channel уже честно описаны в его README; объявлять
этот opt-in пакет общедоступным релизом до cold consumer proof нельзя.

## Что подтвердилось и границы результата

На объединённом main:

- Anti-weakening и contract guard зелёные. Declarations sqlite 1404→1415,
  PG 668→669; squash соблюдает golden REBASELINE в том же коммите.
- Secret scan чист. Семь scanner boundary tests прошли при ревью, включая
  untracked/staged/unstaged/history и generic/provider key внутри audit directory.
- Release-quality all OK; PHP API diagnostics=[]; package graph/module layout/config
  composition прошли. Результаты ревью содержат 900 targeted SQLite tests, 225 PG,
  145 Arch, 7 golden, 78 access SQLite и 33 повторных materialization tests.
- После интеграции: media 21, flex-crm 56, tenancy composition/wakeup routing 16
  tests прошли. AzGuard: 49 passed через composer test, который явно использует
  root bootstrap; его package vendor runner нельзя смешивать с root PHPUnit.
- Composer audit --locked: advisories=[], abandoned=[] для 206 locked packages
  (98 runtime, 108 dev); это snapshot проверки, не обещание будущей безопасности.
  [dependency-audit.json](evidence/dependency-audit.json),
  [locked-components.json](evidence/locked-components.json) с license metadata.

Полный coverage/mutation score, вся PHP/Laravel matrix, hosted Actions, live
Meilisearch/SMTP/webhook network и все cold standalone consumers не прогонялись.
Состояние hosted CI после push не приравнивается к локальным проверкам.
Продуктовый rollback/upgrade по существующим внешним БД не доказан этим аудитом.

Не подтвердились гипотезы о незащищённом выборе production DB в обычных test harnesses:
package IsolatesDatabasePath проверяет _test до boot, notifications имеет отдельный
guard. Все наши PG-прогоны использовали только созданную corex_hardening_review_test.

## Следующий порядок работы

1. RDY-04/RDY-05/RDY-08: устранить три воспроизводимые проблемы и добавить behavior
   regressions, не меняя taxonomy или ядро ради косметики.
2. RDY-06: сделать проверки независимыми от probes/generated dependencies.
3. RDY-03/RDY-01: вернуть зелёный reachability gate и независимый BC-контроль.
4. RDY-02: собрать новый candidate, выпустить current receipts и полный release proof.
5. RDY-07: решение владельца по отзыву ключа и history purge; RDY-09/RDY-10 — точная
   документация и metadata перед публикацией.

После этих шагов можно заново принять решение о стабильном релизе. Автоматического
разрешения публикации, удаления frozen evidence или переписывания истории этот отчёт
не содержит.
