# Архитектурный scorecard пакетов (финальный)

Шкала 0/1/2 по двенадцати критериям. Это карта неоднородности, не рейтинг и не
KPI. Один сводный числовой показатель по критериям не строится: дыра в владении
данными не компенсируется чистыми именами. Пересчёт после гейтов P3–P16.

## Оценки по пакетам

| package | responsibility | standalone-install | public-api | config | extension | data-ownership | worker-safety | package-tests | naming | structure | observability | bc-policy |
|:--|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| axiomasoft/corex-access | 2 | 2 | 2 | 0 | 2 | 1 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-custom-fields | 2 | 2 | 2 | 1 | 2 | 1 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex | 2 | 2 | 2 | 0 | 1 | 1 | 1 | 2 | 0 | 2 | 2 | 2 |
| axiomasoft/corex-intl | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-media | 2 | 2 | 2 | 0 | 1 | 1 | 1 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex | 2 | 2 | 2 | 0 | 2 | 1 | 2 | 2 | 2 | 2 | 2 | 2 |
| axiomasoft/corex-notifications | 2 | 2 | 2 | 1 | 1 | 1 | 1 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-operations | 2 | 2 | 2 | 2 | 2 | 2 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-panels | 2 | 2 | 2 | 1 | 1 | 2 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-recurrence | 2 | 2 | 2 | 1 | 1 | 2 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-search | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-search-intl | 2 | 2 | 2 | 1 | 1 | 2 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-search-meilisearch | 2 | 2 | 2 | 1 | 0 | 2 | 2 | 2 | 2 | 2 | 0 | 1 |
| axiomasoft/corex-tasks | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 2 | 2 |
| axiomasoft/corex-tasks-workflow | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-tenancy | 2 | 2 | 2 | 0 | 1 | 1 | 1 | 2 | 1 | 1 | 2 | 2 |
| axiomasoft/corex-transfer | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-wakeup | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 2 |
| axiomasoft/corex-workflow | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 2 |
| flex/crm | 2 | 2 | 2 | 0 | 1 | 1 | 2 | 2 | 2 | 2 | 2 | 2 |

## Неоднородность

Критерии, где размах по живым пакетам ≥ 2 (есть и 0, и 2): `config`, `extension`, `naming`, `observability`.
Пакеты с ≥ 4 оценками 0: нет.
Критерии, где все 20 пакетов имеют одну и ту же оценку: `responsibility` (2), `standalone-install` (2), `public-api` (2), `package-tests` (2) — кандидат снять с базового круга как неразличающий.

## Ячейки ниже 2

| package | criterion | score | measurement |
|:--|:--|:--:|:--|
| `axiomasoft/corex-access` | `config` | 0 | use-site keys: auth.*, corex-auth.azguard_pins.column_names.morph_type, corex-auth.azguard_pins.models.role, corex-auth.azguard_pins.table_names.roles, corex-auth.bind_permission_layer |
| `axiomasoft/corex-access` | `data-ownership` | 1 | unobserved PII/retention: aut_role_assignments:pii=unobserved,retention=unobserved; aut_role_matrix:pii=unobserved,retention=unobserved; aut_roles:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-access` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-custom-fields` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-custom-fields` | `data-ownership` | 1 | unobserved PII/retention: sys_attribute_documents:pii=unobserved,retention=unobserved; sys_attribute_eav_records:pii=unobserved,retention=unobserved; sys_attribute_eav_values:pii=unobserved,retention=unobserved; sys_attribute_schema_operations:pii=unobserved,retention=unobserved; sys_attribute_write_tokens:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-custom-fields` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex` | `config` | 0 | config_reads outside composition: packages/core/src/Database/HasConfiguredId.php, packages/core/src/Database/Schema/IdColumns.php, packages/core/src/Config/Commands/ValidateConfigCommand.php, packages/core/src/Messaging/SubscribeGuard.php; use-site keys: corex.*, corex.audit.enabled, corex.bindings.audit_logger, corex.bindings.audit_retention, corex.bindings.data_scope_authorizer |
| `axiomasoft/corex` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex` | `data-ownership` | 1 | unobserved PII/retention: sys_audit_log:pii=unobserved,retention=unobserved; sys_department_user:pii=unobserved,retention=unobserved; sys_departments:pii=unobserved,retention=unobserved; sys_feature_flags:pii=unobserved,retention=unobserved; sys_feature_overrides:pii=unobserved,retention=unobserved |
| `axiomasoft/corex` | `worker-safety` | 1 | ShouldQueue without Data Receipt/idempotency type: RecordAuditEntry |
| `axiomasoft/corex` | `naming` | 0 | 3 Manager/Helper/Util/Common types: NullTenancyManager, TenancyManager, PubSubManager |
| `axiomasoft/corex-intl` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-intl` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-intl` | `data-ownership` | 1 | unobserved PII/retention: corex_rate_batches:pii=unobserved,retention=unobserved; corex_rate_current:pii=unobserved,retention=unobserved; corex_rate_snapshots:pii=unobserved,retention=unobserved; corex_reference_aliases:pii=unobserved,retention=unobserved; corex_reference_current:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-intl` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-media` | `config` | 0 | use-site keys: corex-media.allowed_mime_types, corex-media.max_image_pixels, corex-media.max_upload_bytes, corex-media.purposes |
| `axiomasoft/corex-media` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-media` | `data-ownership` | 1 | unobserved PII/retention: med_conversions:pii=unobserved,retention=unobserved; med_files:pii=unobserved,retention=unobserved; med_operations:pii=unobserved,retention=unobserved; med_relations:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-media` | `worker-safety` | 1 | ShouldQueue without Data Receipt/idempotency type: ConvertMedia, PurgeMedia |
| `axiomasoft/corex-media` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex` | `config` | 0 | use-site keys: corex-modules.cache.*, corex-modules.cache.prefix, corex-modules.cache.store, corex-modules.cache.ttl, corex-modules.connection |
| `axiomasoft/corex` | `data-ownership` | 1 | unobserved PII/retention: mod_lifecycle_log:pii=unobserved,retention=unobserved; mod_modules:pii=unobserved,retention=unobserved; mod_records:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-notifications` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-notifications` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-notifications` | `data-ownership` | 1 | unobserved PII/retention: ntf_deliveries:pii=unobserved,retention=unobserved; ntf_digest_items:pii=unobserved,retention=unobserved; ntf_digest_runs:pii=unobserved,retention=unobserved; ntf_notifications:pii=unobserved,retention=unobserved; ntf_recipient_state:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-notifications` | `worker-safety` | 1 | ShouldQueue without Data Receipt/idempotency type: DeliverNotification, DeliverDigest |
| `axiomasoft/corex-notifications` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-operations` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-panels` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-panels` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-panels` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-recurrence` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-recurrence` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-recurrence` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-search` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-search` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-search` | `data-ownership` | 1 | unobserved PII/retention: corex_search_dirty:pii=unobserved,retention=unobserved; corex_search_lanes:pii=unobserved,retention=unobserved; corex_search_rebuilds:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-search` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-search-intl` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-search-intl` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-search-intl` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-search-meilisearch` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-search-meilisearch` | `extension` | 0 | no src/Contracts and no src/Extend |
| `axiomasoft/corex-search-meilisearch` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-search-meilisearch` | `bc-policy` | 1 | surface incomplete: src/Contracts |
| `axiomasoft/corex-tasks` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-tasks` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-tasks` | `data-ownership` | 1 | unobserved PII/retention: corex_task_operations:pii=unobserved,retention=unobserved; tsk_processes:pii=unobserved,retention=unobserved; tsk_project_participants:pii=unobserved,retention=unobserved; tsk_projects:pii=unobserved,retention=unobserved; tsk_sprints:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-tasks-workflow` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-tasks-workflow` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-tasks-workflow` | `data-ownership` | 1 | unobserved PII/retention: corex_task_occurrences:pii=unobserved,retention=unobserved; corex_task_series:pii=unobserved,retention=unobserved; corex_task_series_state:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-tasks-workflow` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-tenancy` | `config` | 0 | config_reads outside composition: packages/tenancy/src/Laravel/Commands/IdentitiesReconcileCommand.php, packages/tenancy/src/Laravel/Commands/PendingCreateCommand.php; use-site keys: corex-tenancy.wakeup.driver, corex-tenancy.wakeup.lease_seconds, corex-tenancy.wakeup.root_connection, tenancy.bootstrapper_order, tenancy.cache_prefix |
| `axiomasoft/corex-tenancy` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-tenancy` | `data-ownership` | 1 | unobserved PII/retention: root_account_events:pii=unobserved,retention=unobserved; root_account_exports:pii=unobserved,retention=unobserved; root_account_products:pii=unobserved,retention=unobserved; root_account_schema_state:pii=unobserved,retention=unobserved; root_accounts:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-tenancy` | `worker-safety` | 1 | ShouldQueue without Data Receipt/idempotency type: CreateDatabaseFromTemplate, SyncIdentityToAccount, RefreshAccountMembership |
| `axiomasoft/corex-tenancy` | `naming` | 1 | 2 Manager/Helper/Util/Common types: TemplateClonePostgreSQLManager, StanclTenancyManager |
| `axiomasoft/corex-tenancy` | `structure` | 1 | profile C, 175 src files, non-layer dirs: Account, Authentication, DomainTrust, Identity, Impersonation, Integration, Laravel, Membership, Provisioning, Runtime, Workspace; debt cap 1 |
| `axiomasoft/corex-transfer` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-transfer` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-transfer` | `data-ownership` | 1 | unobserved PII/retention: corex_export_manifests:pii=unobserved,retention=unobserved; corex_export_parts:pii=unobserved,retention=unobserved; corex_transfer_jobs:pii=unobserved,retention=unobserved; corex_transfer_outbox:pii=unobserved,retention=unobserved; corex_transfer_refs:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-transfer` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-wakeup` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-wakeup` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-wakeup` | `data-ownership` | 1 | unobserved PII/retention: sys_wakeup_requests:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-wakeup` | `observability` | 0 | no package Events and no Log facade |
| `axiomasoft/corex-workflow` | `config` | 1 | no typed *Options class in src |
| `axiomasoft/corex-workflow` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `axiomasoft/corex-workflow` | `data-ownership` | 1 | unobserved PII/retention: sys_workflow_intents:pii=unobserved,retention=unobserved |
| `axiomasoft/corex-workflow` | `observability` | 0 | no package Events and no Log facade |
| `flex/crm` | `config` | 0 | use-site keys: flex-crm.search_language |
| `flex/crm` | `extension` | 1 | Contracts/Extend present without extension-point test |
| `flex/crm` | `data-ownership` | 1 | unobserved PII/retention: crm_activities:pii=unobserved,retention=unobserved; crm_activity_targets:pii=unobserved,retention=unobserved; crm_companies:pii=pii-indirect,retention=unobserved; crm_contacts:pii=pii-direct,retention=unobserved; crm_deal_contacts:pii=unobserved,retention=unobserved |
