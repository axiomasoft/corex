# CoreX → FlexCRM: company/contact/M2M parity receipt

Дата: 2026-09-14. Исполнение: solo, Task Dev, без нового executable plan.
Последняя прямая инструкция владельца имеет приоритет над §5 вложенного старого
промпта. Проверенный COREX-SUCCESSOR/P3.3 явно исключает `flex/crm` code changes;
его tenancy/identity/release SSOT не изменялся. Выбор маршрута: Task Dev рекомендует
gpt-5.6-terra/high; активная модель этой сессии программно не переключалась.

## Вердикт и границы

Company/contact/M2M реализация и локальная квалификация: **GREEN** для свежей/пустой
account DB. Package suite: 64/64, 321 assertions, coverage 96.9% при floor 95%.
Установленный локальный архив: 2/2 integration tests, 44 assertions, PostgreSQL;
discovery → compile → полный enable → create/create/association → disable → re-enable.

Общая готовность к подключению FlexCRM / release: **NO-GO**. Причины ниже:
не-GREEN immutable core/modules baseline, несовпадение исторической sales-DDL с
полной approved схемой и отсутствие release grant/published selector. Наличие
работающего локального архива не отменяет эти blockers. Не установлено в FlexCRM,
не выпущено в production, нет push, split mirror, tag или Packagist publication.
App HTTP/UI/Policy и новые sales use cases не реализовывались.

Работа изолирована от чужого dirty worktree:

- Исходный workspace: `/home/vostrikov/projects/packages/corex`, HEAD
  `d83ef0a26a1b4939b4bf1fd9131533b3c244005a`; чужие правки core/modules/tenancy/plans
  оставлены как были, не включены в commit или архивы.
- Реализация: `/home/vostrikov/projects/packages/corex-crm-parity-20260914`.
- Ветка: `codex/crm-company-contact-parity`.
- Immutable source commit: `fb156f5897c1a703c57f59b37719e1efb1d822ff`.
- Изменения пока на этой ветке/worktree, не перенесены в исходную dirty ветку.
- Package name / namespace: `flex/crm` / `Flex\Crm`.
- CRM subtree: `c5f4d5d3131626f3d6fa47706b188037f7bfedd8`.

## Inputs / authority

FlexCRM input root: `/home/vostrikov/projects/flexcrm/plans/FLEX-2`.
Существующая P15.1 матрица использована как baseline; переквалифицированы изменённые
CRM исходники и реально потребляемые context/lifecycle contracts. Approved файлы
не менялись. SHA-256:

| Input | SHA-256 |
| --- | --- |
| findings/P16.1-crm-parity-spec.md | `388663989c69c36a0096bf8160444fcd13f03bafd3b00d798e4491b52d2b4f09` |
| findings/P15.1-core-crm-readiness.md | `2e6845717969bc34032fc8e58d1f87b4405d35c016af64b09840769904f09b43` |
| findings/P15.1-core-crm-gap-packet.md | `b38470627983e194c4a04e1a3f5887d5101e829878a37bd35321655acc84c2e7` |
| schema/DATABASE_SCHEMA.md | `1ef24ff57f3480829499ab621a9499adc9a96cbc5f618555d977a6f9f625e98f` |
| schema/approval.md (фактические bytes, не объявленный внутри hash) | `9ef95fec6ed4f97c1e3fff7523bbbe926827401d3211fdffb7eae5358e5a2fb9` |

ACTOR owner: `/home/vostrikov/Vaults/Brain/05-Projects/01-Products/FlexCRM/plans/flex/30_blueprints/B-13_crm.md`,
SHA-256 `e81c0f7ea57837efc66e86955563da567491943741bafb5275a883ed916d24ff`.
Норматив: `{source: user|agent|api|workflow|import|system, id, name}`; search — russian.
Это не audit ActorType `{type,id}` и не выдуманный system actor для старых данных.

## Исполненный дизайн

Выбран минимальный набор, без универсального plugin/pipeline/workflow engine:

- Readonly `CompanyData`/`ContactData`: именованные typed approved поля без Request,
  actor, connection и произвольного workspace payload.
- Один интерфейс `CrmExecutionContext`: небольшая доверенная граница между любой
  auth системой приложения и обязательными package admission checks.
- `ContextGuard` + модельный scope/saving hook: fail-closed reads и Eloquent writes;
  app authorization может только сужать эти правила. CRM правила остались в `flex/crm`.
- Owning Actions: мутации и connection-local transaction; association сериализует
  один contact и опирается на authoritative DB unique constraints.
- Existing manifest listeners + after-commit events: композиция потребительских
  процессов без ещё одного extension engine.
- CoreX IdColumns/HasConfiguredId: uuid7 (FlexCRM), uuid, ulid, int сохранены;
  prerequisites обязаны иметь совместимые ID types. Не вводится config на каждое поле.

Perplexity применён для исследования; механизмы сверены с реальным Laravel/CoreX
кодом и первичными источниками: [Laravel after-commit events](https://laravel.com/framework/docs/13.x/events),
[PostgreSQL partial indexes](https://www.postgresql.org/docs/current/indexes-partial.html),
[PostgreSQL locking](https://www.postgresql.org/docs/current/explicit-locking.html),
[libphonenumber PHP](https://github.com/giggsey/libphonenumber-for-php).
Lite dependency добавляет валидацию/E.164 без geocoding/carrier/provider integration.
Её Composer conflict с полной libphonenumber явный; текущий inspected FlexCRM lock
не содержит ни одной из этих библиотек. Новый dependency — Apache-2.0, не смена MIT пакета.

## Публичный API и пример

```php
CreateCompany::handle(CompanyData $data): Company;
CreateContact::handle(ContactData $data): Contact;
AssociateContactCompany::handle(
    string|int $contactId, string|int $companyId,
    ?string $label = null, bool $isPrimary = false,
): ContactCompany;
CrmExecutionContext::current(): ExecutionContext;
```

```php
use Flex\Crm\Actions\{CreateCompany, CreateContact, AssociateContactCompany};
use Flex\Crm\Data\{CompanyData, ContactData};
use Flex\Crm\Models\{Company, Contact};

$company = app(CreateCompany::class)->handle(new CompanyData(
    name: 'Acme', domain: 'acme.test', inn: '7700000000',
));
$contact = app(CreateContact::class)->handle(new ContactData(
    nameFirst: 'Jane', nameLast: 'Doe',
    emails: [['value' => 'Jane@acme.test', 'type' => 'work', 'is_primary' => true]],
));
$association = app(AssociateContactCompany::class)->handle(
    contactId: $contact->id, companyId: $company->id,
    label: 'Decision maker', isPrimary: true,
);
$list = Company::query()->orderBy('name')->get();
$card = Contact::query()->with('companies')->findOrFail($contact->id);
```

Точные constructors, defaults, extension example и delivery guarantees:
[COMPANY_CONTACT_API.md](../../packages/crm/COMPANY_CONTACT_API.md).
Consumer до вызова binds scoped `CrmExecutionContext`, возвращающий verified
`ExecutionContext(TenantContext, connectionName, Actor, principalId, allowAccountLevel=false)`.
Также нужны существующие CoreX `TenantContextResolver`, `AccountConnectionResolver`,
`ModuleRegistry`. Adapter удостоверяет account admission и происхождение non-user actor
на каждом вызове; его нельзя создавать из пользовательского payload. В package нет
permissive default adapter. Non-user executions имеют authenticated active run-as user.
Нет зависимости от конкретной auth implementation или `App` namespace.

## Account / workspace / NULL / actor / owner

Проверяется active non-boxed account, совпадение execution tenant с CoreX current
account/workspace, разрешённая connection driver/host/port/database/user и фактический
PostgreSQL current_database, CRM enabled, active/non-deleted principal, существующий
non-deleted workspace и точная active membership. В Action эти проверки повторяются
внутри transaction с shared locks users/workspace/membership до commit. Missing context,
membership loss, disabled CRM и чужая physical connection отвергаются, не no-op.
Два физических PG database проверены установленным архивом.
Финальная проверка attributes перед INSERT/UPDATE выполняется после saving/creating/
updating observers, включая quiet saves; поздняя подмена actor/workspace/endpoints
не обходит инварианты. Observer veto приводит к ValidationException/rollback owning
Action, а не возврату unsaved model и ложному mutation success event.

Reads: текущий workspace; NULL дополнительно только с allowAccountLevel. Без workspace
допускается только explicit account-level grant и только NULL visibility. Writes:
workspace по умолчанию; `accountLevel: true` требует grant и сохраняет NULL. Association:
оба endpoints видимы, не soft-deleted, workspace IDs строго равны, включая NULL;
NULL↔workspace и cross-workspace reject. FK не подменяет membership/visibility checks.

`created_by` задаётся только verified context, не DTO; user actor ID равен principalId,
source из шести B-13 kinds, id/name непустые. На direct Eloquent create forged actor
перезаписывается verified actor, последующее изменение provenance/workspace reject.
Owner nullable, hard users deletion SET NULL; выбранный owner active в account DB и,
если workspace выбран, active member этого workspace. NULL sources общие для admitted
scope; scoped source совпадает с record workspace, всегда active. Soft-delete source/owner
не равен FK SET NULL; новое создание со soft-deleted owner отклоняется.

Display: Unicode whitespace collapse/trim; optional empty → NULL, invalid UTF-8 reject;
last/first/middle joined и deterministic Unicode truncation до 255. Без имени primary/first
email norm, иначе primary/first phone norm; без всех компонентов reject. Channels — list
objects с validated value/type/is_primary; supplied norm recomputed, malformed/duplicate
norm/multiple primary per kind reject. Phone только valid international +country → E.164,
без local guessing/extensions; email trim/validate/lowercase. Search пересчитывается на
saving через parameterized to_tsvector (`russian`, consumer regconfig override допускается).
INN не unique. Birthday — strict YYYY-MM-DD, widths/defaults/FK/indexes approved.

Pair unique + partial primary unique сохраняются при конкуренции; Action locks contact
FOR UPDATE, company FOR SHARE, duplicate/second primary reject без implicit replacement.
M2M UUIDv7 PK в FlexCRM профиле, hard endpoints deletion CASCADE; soft deletion сохраняет
pivot storage и скрывает удалённые endpoints из normal relations.

## Events / расширения / BC

CompanyCreated, ContactCreated, ContactCompanyAssociated — ShouldDispatchAfterCommit,
с verified Actor. Rollback outer transaction не публикует; successful composite регистрирует
events в command order. Sync listeners исполняются в Laravel registration order; exception
после commit распространяется, данные уже сохранены, последующие listeners/callbacks могут
не выполниться. Не retry create вслепую. Это best-effort in-process delivery, не durable
exactly-once/outbox. Independent integration — queued manifest listener с собственной
idempotency/retry policy. Mandatory шаги — composition Action в той же outer transaction,
не after-commit listener. Raw/bulk query mutations/scope removal — privileged maintenance,
не supported extension point.

Breaking pre-1.0 MINOR candidate после v0.2.0, НЕ patch и НЕ опубликованная v0.3.0.
Старые scalar names/email/phone/company_id/website/custom_fields не HTTP-алиасы.
Company contacts теперь BelongsToMany; Contact company заменена companies.
ConvertLead минимально адаптирован к DTO + primary association в прежней transaction,
с его idempotent retry/deal relations/concurrency tests. Trusted context обязателен;
малформированный legacy phone/invalid owner явно rollback, фиктивный actor не создаётся.
Подробности и безопасный upgrade: [UPGRADE.md](../../packages/crm/UPGRADE.md).

## Migration / module lifecycle

Users принадлежит account identity consumer, wsp_workspaces/wsp_workspace_members —
workspace owner, crm_sources — этому CRM migration set. Порядок: initialized isolated
account connection → compatible identity/workspace schema → core/modules own migrations
→ installed metadata discovery/compile → enable flex/crm. `corex/core` и `corex/modules`
библиотеки, не prerequisite enable-able modules: ложные manifest Dep.module edges не добавлены.
Фактические Composer dependency refs/pins ниже, prerequisites проверяются до parity DDL.
PG15+ нужен для NULLS NOT DISTINCT. FlexCRM config `corex.ids.strategy=uuid7` устанавливается
до любых owning migrations; смешение IDs с prerequisites запрещено.

Provider startup и обычный migrate не запускают CRM DDL. Enable запускает весь исторический
набор плюс additive parity/widen-activity migrations, не только две выбранные таблицы.
Все девять CRM tables реально создаются на PG source lane и из installed archives.
Disable preserves данные, re-enable/multiple enable идемпотентны. Purge не способ upgrade.

Fresh/empty legacy: parity rebuild пустых company/contact tables с approved columns;
в PG весь enable транзакционен. Populated legacy: guard останавливает ДО изменения таблиц
с CRM_PARITY_UPGRADE_REQUIRES_EXPORT. Тест доказывает preservation и missing prerequisite
preflight. Автоматический upgrade populated installations НЕ готов: нужен verified export
и provenance-preserving conversion в новую isolated DB, source backup сохраняется; custom
metadata не выбрасывать/old IDs не truncate/actor не выдумывать. `down()` отказывает в потере
target data; отдельно authorized verified backup restore вместо разрушительного rollback.

## Immutable dependencies / install candidate

Все три архива построены из commit `fb156f5897c1a703c57f59b37719e1efb1d822ff`,
а не из dirty filesystem. Exact archive selector:
`flex/crm:dev-crm-parity-fb156f5897c1`; corex/core и corex/modules pinned к тому же selector.

| Package | Immutable subtree | ZIP SHA-256 |
| --- | --- | --- |
| corex/core | `62ad7daeeb13d1274598816f01b88e4809bdf017` | `6c6681e22ab0f665095e29da9c4aceda5b5eb6eba03821c9a020f3d9134efa3d` |
| corex/modules | `e1465ee2257dbe1bab13a8f0e225096f2d9c4058` | `c57daae45de8c90594348dc0345b071c414b33e2534f5164837f61b6494f235a` |
| flex/crm | `c5f4d5d3131626f3d6fa47706b188037f7bfedd8` | `54e555cb53159457e5929dbd6d79b200120b98f9093e7bb1128150d8c0bb54ea` |

Artifact repository absolute directory:
`/home/vostrikov/projects/packages/corex-crm-parity-20260914/delivery/crm-parity-20260914/artifacts`.
Archive filenames: `corex-core-fb156f5897c1.zip`, `corex-modules-fb156f5897c1.zip`,
`flex-crm-fb156f5897c1.zip`. Local only, qualified scenario candidate, **not release-approved**.
Do not overwrite these archives; verify SHA before qualification. Builder adds synthetic
dev version/exact internal dependency pins and extra.corex-source commit/subtree; product
file bytes from Git unchanged, dev dependencies/test autoload removed. Not a VCS split release.

Fresh consumer definition: [consumer/composer.json](consumer/composer.json).
Require all three exact candidate pins if overriding existing `corex/core:dev-main` consumer
constraints; adding only CRM while retaining incompatible core pin will not resolve.
Consumer owns artifact repository declaration; dependency repositories are not relied upon.
Production implementation autoload resolves installed vendor/flex/crm, not sibling src/symlink.
Only test fixture namespace is autoload-dev linked to committed package tests; fixture trust
adapter is explicitly NOT production authorization. Fresh vendor directory, warm Composer
download cache allowed. Installed runtime: PHP8.4.25 / Laravel13.31.0 / Testbench11.2.0 / PG16.14.
`corex/tenancy` implementation not bundled or qualified by these archives; existing contract
subtree baseline `6575dfe4d5cc7ccebaad53e0ce8811325612ba5a` unchanged. Consumer's actual tenant
context/connection adapter admission remains its own integration gate, not the fixture's proof.
libphonenumber-lite9.0.39 source `35cd53ef595b709218756965a3fc22263ed9b24e`.

Root composer.lock SHA-256: `27d9ddfd8f30d61293b4250de6e7415cabe4d3e866862688099aae09244a10f6`.
Consumer composer.lock SHA-256: `417fe5b5396ab9b5ccb16c48ac98269bef8dc653b517b4ee6980c0190eb8b3d3`.
Root path lock refresh included stale metadata/branch versions of existing packages, not
changes to their source. Source @dev constraints still not a released compatibility matrix.

## Checks / exact commands / environment

Unless package/consumer CWD specified, CWD = isolated implementation worktree above.
Logs absolute root:
`/home/vostrikov/projects/packages/corex-crm-parity-20260914/delivery/crm-parity-20260914/logs`.
PG suites ran serially on dedicated `corex_crm_parity_test`; installed negative lane also
uses dedicated `corex_crm_foreign_test`. Both names checked `_test` before mutation.
Host127.0.0.1:5434, user/password corex (isolated test fixture only); Docker existing
corex_pgsql_test, postgres:16-alpine image
`sha256:de3a4eab8fdfa507ea92aac488b916b08089e515db49b055fe71dfa271ba3a28`, PG16.14.
Default Herd PHP8.4.1/Composer2.8.3 for lint/analysis/normal source lanes;
coverage PATH=/usr/bin uses PHP8.5.10/Xdebug3.5.3. Source root Laravel13.23.0.
Package source lane — Laravel13.31.0/Testbench11.2.0. Canonical logs below distinguish
CRM proof from failed baseline dependency gates; SOLO self-review, not independent review.

| Command | Result / log |
| --- | --- |
| `composer lint:check` | PASS [root-lint-final.log](logs/root-lint-final.log) |
| package CWD: `composer lint:check` | PASS [package-lint-final.log](logs/package-lint-final.log) |
| `composer analyse` | PASS 0 errors [root-analysis-final.log](logs/root-analysis-final.log) |
| package CWD: `composer analyse` | PASS 0 errors [package-analysis-final.log](logs/package-analysis-final.log); Herd extension startup warning nonfatal |
| `composer test` | PASS 110 tests /302 assertions [root-sqlite.log](logs/root-sqlite.log) |
| `composer test:arch` | PASS 5 tests /5 assertions [arch.log](logs/arch.log) |
| package CWD: `PGSQL_DATABASE=corex_crm_parity_test composer test:pg` | PASS 38 tests /213 assertions earlier lane [package-pg.log](logs/package-pg.log); final full suite below also includes added upgrade tests |
| package CWD: `PATH=/usr/bin:$PATH XDEBUG_MODE=coverage PGSQL_DATABASE=corex_crm_parity_test composer test:coverage -- --coverage-clover=../../delivery/crm-parity-20260914/logs/package-clover.xml` | PASS final 64 tests /321 assertions,96.9%, floor95 [package-coverage-final.log](logs/package-coverage-final.log), [package-clover.xml](logs/package-clover.xml) |
| `PGSQL_DATABASE=corex_crm_parity_test bash scripts/mutation-check.sh --all` | PASS 30/30 repository sabotage cases [mutation.log](logs/mutation.log); not a claim of CRM-specific Infection MSI |
| `/usr/bin/php8.4 delivery/crm-parity-20260914/build-artifacts.php fb156f5897c1a703c57f59b37719e1efb1d822ff` | PASS [artifacts.log](logs/artifacts.log) |
| consumer CWD: `composer update --prefer-dist --no-interaction --no-progress` | PASS fresh archives install, no security advisories reported [consumer-install.log](logs/consumer-install.log) |
| `composer --working-dir=delivery/crm-parity-20260914/consumer install --prefer-dist --no-interaction --no-progress` | PASS окончательный lock в вновь пустой vendor [consumer-fresh-install-final.log](logs/consumer-fresh-install-final.log); старый fixture vendor перемещён recoverably в build/crm-parity-consumer-vendor-superseded, не удалён |
| consumer CWD: `PGSQL_DATABASE=corex_crm_parity_test /usr/bin/php8.4 vendor/bin/phpunit` | PASS final 2 tests /44 assertions [consumer-runtime.log](logs/consumer-runtime.log) |
| `PGSQL_DATABASE=corex_crm_parity_test composer test:pg` | NOT GREEN: publisher baseline leaves a transaction/schema lock; stopped hung exact processes, no destructive cleanup [root-pg-debug.log](logs/root-pg-debug.log) |
| `PGSQL_DATABASE=corex_crm_parity_test vendor/bin/pest --group=pg --testsuite=Feature,ModulesPackage` | FAIL 4 unchanged modules tests, 101/105 pass,342 assertions [root-pg-unaffected.log](logs/root-pg-unaffected.log) |
| `PGSQL_DATABASE=corex_crm_parity_test vendor/bin/pest packages/core/tests/Feature/DeliveryRemediationTest.php --filter='serializes publishers'` | FAIL unchanged publisher test [core-publisher-baseline.log](logs/core-publisher-baseline.log) |
| `PATH=/usr/bin:$PATH XDEBUG_MODE=coverage PGSQL_DATABASE=corex_crm_parity_test composer test:coverage -- --testsuite=Unit,Feature,ModulesPackage,Arch,PHPStan --coverage-clover=delivery/crm-parity-20260914/logs/root-clover.xml` | FAIL same4 modules tests,205/209 pass,612 assertions [root-coverage.log](logs/root-coverage.log). CorePackage omitted to avoid known hang; NOT canonical root coverage80 GREEN |

Exploratory process-isolation option unsupported by Pest; one exploratory overlap of PG
suites was stopped and invalidated, followed by serial final reruns. Those diagnostic logs
are retained but NOT counted as acceptance. No promise of remote CI or full PHP8.3–8.5 /
Laravel12–13 compatibility matrix qualification. Existing crm-tests CI job already covers
package sqlite/PG/95% coverage; no remote run triggered.

Final acceptance covers approved field/default/type/FK/partial/GIN/jsonb_path_ops introspection,
nonunique INN, names/display/channels/malformed data, full values/search/UUID7, M2M constraints
and two real concurrent connections, workspace/NULL/membership/missing/foreign context,
six actor kinds/forgery, foreign source/owner UUID, rollback/commit/listener failure,
hard SET NULL/CASCADE versus soft-delete preservation, upgrade refusal/preflight,
all migrations lifecycle/preservation/idempotency and real ConvertLead caller behavior.

## Exact product Files

Inventory includes the original implementation plus the final observer-hardening commit;
reproduce the complete list with `git diff --name-status d83ef0a26a1b4939b4bf1fd9131533b3c244005a fb156f5897c1a703c57f59b37719e1efb1d822ff`.
Under `packages/crm/`, modified existing files:

```text
CHANGELOG.md README.md composer.json
src/Actions/ConvertLead.php src/Actions/CreateCompany.php src/Actions/CreateContact.php
src/CrmModule.php src/CrmServiceProvider.php
src/Events/CompanyCreated.php src/Events/ContactCreated.php
src/Models/Company.php src/Models/Contact.php
tests/Feature/ActivityTimelineTest.php tests/Feature/CompanyContactTest.php
tests/Feature/LeadConversionTest.php tests/Pest.php
tests/Pg/ActivityIdTest.php tests/Pg/ConversionConcurrencyTest.php
tests/Pg/ModuleLifecycleTest.php tests/TestCase.php
```

New files under `packages/crm/`:

```text
COMPANY_CONTACT_API.md UPGRADE.md
database/migrations/2026_09_14_000002_company_contact_parity.php
src/Actions/AssociateContactCompany.php src/Contracts/CrmExecutionContext.php
src/Data/Actor.php src/Data/CompanyData.php src/Data/ContactData.php src/Data/ExecutionContext.php
src/Events/ContactCompanyAssociated.php src/Models/Concerns/HasCrmContext.php
src/Models/ContactCompany.php src/Models/Source.php
src/Support/Channels.php src/Support/ContextGuard.php src/Support/CrmScope.php src/Support/RecordRules.php
tests/Feature/ContextSecurityTest.php tests/Feature/ParityTest.php
tests/Pg/ParityContractTest.php tests/Pg/UpgradeSafetyTest.php
tests/Support/AccountFixture.php tests/Support/TestContext.php
tests/database/2026_01_01_000000_account_fixture.php
```

Other product commit files: root `composer.lock` and this delivery's `build-artifacts.php`.
Delivery receipt/consumer test/locks/logs/archive checksums are qualification artifacts,
not another executable plan or release authority.

## Real blockers / owner-bound continuation

1. **Dependency immutable GREEN**: unchanged core publisher serialization fails; modules
   activation denial, dependency graph serialization, migration listener preservation,
   flag visibility/rollback fail. Original workspace has somebody else's dirty remedies
   in these areas, not admissible release inputs. Obtain owning committed core/modules
   refs + GREEN PG/coverage receipts, rebuild candidate exact pins, rerun CRM + installed
   lifecycle. Do not silently copy dirty work or label current baseline compatible GREEN.
2. **Whole enable vs approved full schema**: historical `crm_pipelines.name` varchar
   versus target JSONB and missing workspace/entity_type/code/settings/partial uniques;
   stages legacy is_won/is_lost versus approved semantic/code/required_fields/settings.
   Leads/deals/activities also remain historical shapes. Complete enable EXECUTES and
   preserves data, but full-target approved DDL acceptance DOES NOT PASS. Owner-bound
   choice: authorize a separate bounded sales-storage parity continuation (no app sales
   features), or explicitly approve a versioned consumer admission delta permitting those
   unused historical shapes. No consumer approval change made here. Do not hide this via
   selective migration or HTTP aliases. Company/contact approved shape itself unchanged.
3. **Distribution**: artifacts are available locally only; release/publish/install grant
   and split provider refs not supplied. Source commit cannot be required as monorepo-root
   `flex/crm` VCS selector. Local Composer artifact selector above is the actual candidate.
4. **Populated legacy upgrade**: intentionally guarded, not automatic GREEN. Existing
   populated installations require verified provenance-preserving migration qualification;
   fresh first FlexCRM slice is the qualified package scenario.

No deferred technical question remains inside the implemented create/read/association
contract. Remaining items are dependency/release/approved-scope gates, not choices for an
executor to invent. FlexCRM can prepare its adapter/Policy/HTTP composition against this
contract, but must not connect this NO-GO candidate to its app DB before these gates resolve.

## Пересылаемый итог

CoreX: company/contact/M2M parity готова к квалификации.
Receipt: /home/vostrikov/projects/packages/corex-crm-parity-20260914/delivery/crm-parity-20260914/receipt.md.
Package/ref: flex/crm:dev-crm-parity-fb156f5897c1; source fb156f5897c1a703c57f59b37719e1efb1d822ff.
Проверки: CRM 64/64, coverage96.9%; installed PG scenario2/2; общий PG/coverage gate не GREEN.
Release/install status: local candidate доступен; release не выполнен, app install NO-GO до dependency/full-DDL gates.
Следующий шаг FlexCRM: проверить receipt и совместимость; после снятия blockers подключить пакет в bounded app-срезе.
