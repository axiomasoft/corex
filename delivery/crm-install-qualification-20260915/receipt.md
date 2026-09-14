# CoreX / flex/crm installed delivery — 2026-09-15

schema_version=corex-crm-install-delivery/v1

COREX_CRM_DELIVERY_READY_FOR_FLEXCRM_REQUALIFICATION

G1 GREEN: совместимый committed dependency set. G2 GREEN: full enabled approved CRM
schema, generic lifecycle и physical context. G3 GREEN: доступная **локальная тестовая**
immutable distribution с текущими гейтами. Это не release/publication, populated
upgrade, production readiness или consumer G4 acceptance.

## Authority, actual refs и зачтённая работа

Начальный фактический HEAD: `ea5f01dd81c03b238bcb15c24036862adc756eb9`, не исторический
`0e2becf`. Existing API/principal SPI, семь CRM operations, company/contact/M2M Actions
и deterministic exporter переиспользованы. Старые reports/receipts остаются историческими.
Owning terminal P4.3/P4.4 `{core,modules,tasks,tenancy}` не переоткрыты: этот пяти-пакетный
set имеет отдельный explicit [candidate.json](candidate.json), не старую identity/policy.
Задача исполнена как разрешённая owning direct delta, без конкурирующего плана.

Approved schema 0.2.2 и approval/extract перепроверены до DDL и при sealing:

| Authority | SHA-256 |
|---|---|
| FLEX-2/schema/DATABASE_SCHEMA.md | `1ef24ff57f3480829499ab621a9499adc9a96cbc5f618555d977a6f9f625e98f` |
| FLEX-2/schema/approval.md | `9ef95fec6ed4f97c1e3fff7523bbbe926827401d3211fdffb7eae5358e5a2fb9` |
| FLEX-2/brief/crm-install-schema.md | `a65ed02ba8468a4cc90e7e8e54e6be0d0f72a5c1f06cb012030cb7de2b6615a5` |

Exact source commit для **всех** пяти local package ZIP:
`cb79f18095589124759fdd1107144764bb31d094`.
Feature/remedy commit: `e09b90d4230c00beea74f0d4e5a1c2453e10274a`, последующие bounded
qualification remedies включены в source. Final quality helper ref:
`063f165e1065053984751f2efc41c18caefe90ac`. После source изменились только delivery helper
и whitespace SQL test fixture; production src/config/migrations/Composer/CHANGELOG bytes
не изменились. Это проверяется [seal.json](seal.json); refs проверены `git rev-parse`.
Carrier commit этого receipt определяется `git log -1 --format=%H -- <receipt-path>`;
он не является другой package source identity.

Чужой dirty domain-trust/template/provisioning/private-auth/plans work сохранён,
не входит в наши owning commits/ZIP. Старые CRM migrations, API runtime, Contracts,
tenancy runtime/migrations/config и exporter/policy относительно начального HEAD
не изменены нашими commits. Tenancy delta — только generic static FK detector/tests.

## Immutable installed set

Version пяти local packages: `0.3.0-alpha.20260915`, pre-1.0 local candidate, не tag.
Selectors: `corex/core`, `corex/modules`, `corex/tenancy`, `corex/api`, `flex/crm`.
Composer metadata/type/provider/manifest/autoload/exact internal requires/dist SHA-1
и полный 88-package runtime lock/ref/dist set находятся в
[installed/packages.json](installed/packages.json),
[installed/evidence.json](installed/evidence.json) и
[installed-laravel12/evidence.json](installed-laravel12/evidence.json).

| Package | Exact subtree / sealed candidate ref | ZIP SHA-256 |
|---|---|---|
| corex/core | `305a21b885cde713909e0d6b9c2d6271f19b825f` | `e27f98e41d58d9612570a2b8ec5c0a9af345f206d31ed83e194fb371cbc27249` |
| corex/modules | `bc3596cc66006af88561d9518826290ccbbf13a0` | `f28aada9e494b12723c7e62f93e87cdc06a2bb21770d62f633f7064037e9e54a` |
| corex/tenancy | `094bc962e94514496df3565c3b2222bd8848873f` | `825208d4df16003e8f3abc312ae99c51faae8895c15cc530b96fee7de1838b4c` |
| corex/api | `98b7547a06754b3f0fb542062d7041e8f151bc63` | `28bcb9647040e3b7d8f221257a1df7fa8a9fdd63623951c9440bf106d10c3774` |
| flex/crm | `dc5365d5a22043b12ea490056aa87bda3b604fd5` | `5f3dbee7463704f9253430847f1a39aa9b818ca4e755d02eb7e9ce2b52efb4ec` |
| axiomasoft/stancl-tenancy 4.0.0-alpha1 | `7d596a145ee1e8251a64785e66ff40cf24c92078` | `48b6d7a9dc15ebd5d98dcab42844226421709113ff7c1347c10b01f876045d99` |
| axiomasoft/stancl-jobpipeline 2.0.0-alpha1 | `683de91ca7f80eb77f24b6b0b97733eecf726ac7` | `ab05cafca18259875771d9ccd8321b8a1058d048c6b0c0261effc2a1923ac546` |

Stancl bytes переиспользованы из accepted owning P4.1 sealed candidate, не подготовлены
повторно. Source-map, upstream TAR hashes, candidate Composer/ZIP SHA-1/SHA-256 повторно
проверены. Proposed mirror source URLs **не опубликованы/не квалифицированы как доступные**:
current первоначальный remote install получил 404. Поставка использует доступные local
`file://` ZIP URLs, без private credentials. Local ZIP ref — настоящий monorepo commit;
поддельного опубликованного split source/ref нет. Никаких replaces/aliases/path deps для
consumed пяти packages. Stancl autoload helpers/class aliases — исходная metadata,
не dependency aliasing. LICENSE hashes и deterministic repeat ZIP equality — в evidence.

| Installed profile | Equal A/B composer.lock SHA-256 |
|---|---|
| Laravel 13.31.0 / PHP 8.4.1 | `cb9eaecccde55a063dbccffb180c38ee1b9fd0107f7113b4e8024ea19b1b88c0` |
| Laravel 12.69.2 / PHP 8.4.1 | `f478197b3ef7883439e7834c8aa1638a369c5b5b0086b5f0595a130b89bdadd5` |

Каждый profile: fresh consumer A `composer update --no-dev --no-scripts --prefer-dist`,
fresh consumer B `composer install` с тем же lock/flags; затем explicit package discovery →
compile → full enable. Production vendor без source symlinks/test autoload; все ZIP members
пяти packages совпадают с installed files; все 88 installed package-tree hashes совпадают
между A/B. App keys независимы (только хэши в evidence). Между Laravel profiles одинаковы
семь local ZIP hashes, не весь Laravel runtime lock. API provider opt-in явно включён в harness.
Default registration не включает CRM, не монтирует product routes, не запускает CRM DDL.

## Full actual enabled storage и lifecycle

Additive migration `2026_09_15_000001_full_storage_parity.php` следует за историческим set.
Она preflight'ит **все** affected legacy CRM tables и owning user/workspace/membership ID
prerequisites; на populated rows отказывает `CRM_PARITY_UPGRADE_REQUIRES_EXPORT` до изменения.
PG empty rebuild connection-local transactional, с ACCESS EXCLUSIVE и повторной проверкой
пустоты; нет guessed actor/metadata/enum conversion или compatibility columns. Down требует
отдельного backup/export lane, не удаляет populated storage. Tests сохраняют legacy rows
и catalogue при refusal (lead/pipeline/activity), проверяют fresh/empty и все ID profiles.

Все **12** реально enabled CRM tables сравниваются с independent approved SQL reference:
`crm_sources`, `crm_pipelines`, `crm_stages`, `crm_companies`, `crm_contacts`,
`crm_contact_company`, `crm_leads`, `crm_deals`, `crm_activities`, `crm_lost_reasons`,
`crm_activity_targets`, `crm_deal_contacts`.
Последняя — approved local closure **существующих** ConvertLead и Deal/Contact relationship,
взамен forbidden legacy deal.contact_id; не новая deal-items/history/timeline feature.

Catalogue в installed evidence включает exact column format_type/null/default,
CHECK, PK/UQ/FK и referential actions, index uniqueness/NULLS NOT DISTINCT,
partial predicates/opclasses/access method/GIN tsvector. Сравнение допускает equivalent
index/constraint names, schema qualification и CURRENT_TIMESTAMP/now(), не потерю constraints.
Canonical status/kind, provenance, amount/currency, decimal position и polymorphic activity
targets подтверждены. Full 12-table parity выполняется для **обеих account DB каждого consumer**.
Прочие approved CRM catalogue definitions не заказывались/не реализованы.

Generic remedies: activation gate до graph/migrations/hooks; graph-wide PG advisory xact
serialization; чужие migration listeners сохраняются на success/failure; feature definitions
transactional на lifecycle/default physical connection, видимы до after-commit enable event,
rollback атомарен; re-enable сохраняет admin overrides. Existing dirty feature flags savepoint
и per-channel PubSub ordering зачтены и committed. Нет CRM/product branching в corex/*.
Installed disable-after-warm → 404; disable сохраняет rows/catalogue; re-enable/repeated enable
idempotent. Outer rollback → 0 company events, commit → 1. Association race → 55P03 blocker,
после commit loser → 422 и ровно один primary; duplicate/second-primary → 422.

## Test targets и physical context

Dedicated serial suite DB: `corex_crm_install_20260915_test`, PostgreSQL 16.14 в healthy
`corex_pgsql_test`, host `127.0.0.1:5434`, user `corex`. [Preflight](logs/preflight.log)
зафиксировал имя `_test`, actual database/user и **0** public tables до destructive selectors.
Destructive PG suites не параллелились на этом target; consumer DB/production не использованы.

Laravel13 targets: `corex_crm_q15final{a,b}_{root,account_a,account_b}_test`.
Laravel12 targets: `corex_crm_q15l12{a,b}_{root,account_a,account_b}_test`.
Это notation; все **12 exact names**, account/workspace UUID, actual PDO backend/name/user
и effective config host/port/driver/database находятся в seal.json и installed evidence.
Probes отказывают при существующем target имени, создают только новые `_test` DB; fixtures
используют owning core/tenancy/modules schemas. На account — два authoritative workspace.
DB fixtures сохранены для inspection; старые unrelated DB/данные не удалены. Suite migration
preparation/rollback касается только выделенных test targets, не populated app upgrade.

Profile: PG ≥15, UUIDv7, PHP ≥8.4 для consumed tenancy. Existing boxed/ULID/UUID/int support
пакетов не изменён глобально; affected UUIDv7/UUID/ULID/int и enum ID conversion tests GREEN.
Physical installed implementations:

| Public SPI | Installed implementation |
|---|---|
| TenancyManager | StanclTenancyManager |
| TenantContextResolver | TenancyManagerContextResolver |
| AccountConnectionResolver | ClusterAccountConnectionResolver |
| PrincipalWorkspaceResolver | DatabaseWorkspaceResolver |
| ModuleRegistry | CompiledModuleRegistry |
| ModuleLifecycle | DatabaseModuleLifecycle |

Installed A → B → A подтверждает context/effective config/PDO совпадение. Negative checks:
uninitialized/missing physical/boxed → 403; unauthenticated → 401; default policy → 403;
revoked membership, inactive user, deleted/inactive owning workspace → 403; foreign account
IDs → 404; NULL/account-level и other-workspace visibility fail closed; forged actor/connection/
workspace payload отклонён; disabled module after warm → 404; inactive account после end/root
status update/**новой initialize** → 403 (не claim немедленной revocation live context).
Boxed отдельный installed app использует NullTenancyManager/SingleAccountResolver,
physical API → 403 и 0 SQLite tables. Это не второй tenancy/provisioning engine и не fleet proof.

## API, BC и verified gates

Сохранены ровно семь operations: company list/show/create, contact list/show/create,
contact-company create. Existing bounded lists (limit 1..100) и card projections переиспользованы;
не введены новые options/lead/deal/activity endpoints. Typed request/record/list/channel/
association schemas соответствуют actual payload; strict bool вместо lookalike TypeError.
Все 45 actual exchanges каждого из четырёх consumers независимо валидированы jsonschema
Draft 2020-12 против **того же installed compiled registry OpenAPI** (включая error envelopes).
Domain validation duplicate/second-primary — **422**, не SQL-text-derived 409.

Equal OpenAPI SHA-256 всех consumers:
`2a0566d5c930f94654f02e887bb368b08bb1805ade75cc5d447a7b18e4bc3f33`.
Public corex Contracts не менялись. Sales storage enum/signature/relation изменения — clean
pre-1.0 breaking MINOR candidate; [CRM CHANGELOG](../../packages/crm/CHANGELOG.md) и
[UPGRADE](../../packages/crm/UPGRADE.md) документируют неподдерживаемые старые statuses/
note/task/metadata и explicit owner versus trusted created_by. Company/contact/M2M DTO/context
семантика сохранена. Save-veto rollback регрессии и [bounded independent review](review.md) GREEN.

| Current exact-runtime gate | Result |
|---|---|
| Root SQLite / PG | 151 / 110 pass; 480 / 371 assertions |
| Root coverage (floor 80) | 261 pass, 851 assertions; **89.6%** |
| CRM SQLite / full incl. PG (floor 95) | 34 / 80 pass; full 404 assertions; **95.7%** |
| API full coverage (floor 80) | 40 pass, 177 assertions; **96.9%** |
| Tenancy full | 417 pass, 1095 assertions |
| PHPStan root / CRM / tenancy / tests-lint | 0 errors |
| Pint / architecture-layer / contract / anti-weakening | PASS; Arch 6 pass |
| Existing registered mutation gate | 34/34 killed, restoration verified |
| Installed 12 + 13 physical/boxed/conformance | 4 consumers GREEN |

Executed command selectors/options, cwd/env/results и точная классификация текущих baseline
failures находятся в [gates.json](gates.json); **SHA-256 каждого лога/config/lock/evidence/ZIP**
в [hashes.json](hashes.json). [seal.json](seal.json) повторно проверяет все refs/authority/ZIP/
88 runtime versions/source/dist/**actual installed trees** и отсутствие post-source production delta.
Quality snapshots сохраняют исходные configs/floors/исключения; source coverage paths лишь
перенесены на реально исполняемые immutable vendor copies. Mutation-only loader перед patch
проверял source=ZIP byte equality; затем восстановлен vendor loader. Tenancy full выполнен
на восстановленных equal source bytes с теми же exact runtime pins; это явно отмечено в ledger.
Existing Auth rules-test bootstrap прочитан из уже имеющегося local vendor: только static test
dependency, не production set/private-auth изменение или получение credentials.

Исторические четыре lifecycle/root/consumer diagnostics не объявлены новым прогоном.
Current начальный lifecycle baseline действительно воспроизведён (4 fail), existing core fixes
и extension/publish tests GREEN зачтены. Current randomized Audit fixture и generic FK detector
failures воспроизведены и устранены без отключения enforcement. Exploratory transport/harness/
instrumentation RED logs сохранены и классифицированы в ledger, не подставлены вместо current GREEN.

## Responsibility matrix и точный остаток FLEX-2 P17.1

| Owner | Delivered / remaining |
|---|---|
| corex/core + modules | Delivered generic flags/PubSub/lifecycle; contracts/platform agnostic, no Flex/CRM DDL/dependency-up |
| corex/tenancy + corex/api | Existing physical engine/context/principal SPI and default-deny dispatch/envelopes/OpenAPI reused; installed profile qualified |
| flex/crm | Delivered full canonical storage closure, existing sales Action adaptation, typed existing seven declarations; no App/product dependencies |
| FlexCRM / FLEX-2 P17.1 | **Remaining:** pin/install/reuse this exact distro/lock; compose approved base + solution; bind actual Laravel guard/session/OIDC/AzGuard principal and workspace/product policy through existing public SPI; set product routes/prefix/headers/security scheme; rerun consumer installed reuse/session/auth/policy/storage acceptance |

P17.1 continuation не требует новой API platform, exporter, семи CRM operations или
CRM DDL в приложении. Generic test PrincipalResolver/Principal/OperationAuthorizer adapters
не засчитывают product authentication/session/host policy acceptance. Существующий owning
phase ID остаётся **FLEX-2 P17.1**; P17.2 не создан. CoreX G1/G2/G3 residual: **none** в данном
qualified local-test profile; actual consumer G4 остаётся UNFILLED.

Excluded/not executed: production/app DB, public mirror/split/tag/Packagist/push/publication,
populated export/conversion/cutover, product UI/timeline/inbox/lead-deal-activity API,
domain-trust/template/provisioning roadmap, fleet/AP/SLO/deployment qualification,
product session/auth/policy/base/solution implementation и внешние сообщения.

Для повторного cold installed run использовать committed qualify.py с **новым** `--out`
и уникальным `--prefix`; старые receipt/targets не перезаписывать. Для проверки существующей
поставки: `python3 delivery/crm-install-qualification-20260915/seal-evidence.py --checkout
/tmp/corex-crm-qualified-l1hCwq/source` — без DB mutations/rebuild/publication.
