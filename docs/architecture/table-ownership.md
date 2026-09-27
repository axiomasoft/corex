# Владение таблицами

Инварианты — статьи `DATA-001` … `DATA-006`. Процедура — `data-ownership-policy.md`.
Эта страница — **каталог текущего состояния**, сгенерированный инвентарём плана
`2026.09.19-№1-COREX-PLATFORM-HARDENING` (P2.3). Она не вводит новых правил.

Поле со значением `unobserved` означает: дерево не дало факта. Это долг
документирования, а не разрешение считать поле пустым.
`tenant_scope=test` обозначает только disposable-таблицы reference consumer'ов:
их создают тестовые fixture, а пакеты не публикуют для них миграции.

AzGuard управляет схемой своих таблиц как зависимость `corex/access`; в этой
установке `corex/access` владеет их конфигурацией и прикладными записями.
Канонические ORM-модели для DATA-003 привязаны к следующим таблицам:

| ORM model | table | source |
|:--|:--|:--|
| `CoreX\Auth\Matrix\AzGuardRoleModel` | `az_guard_roles` | `corex-auth.azguard_pins.table_names.roles` |
| `AzGuard\Models\RolePermission` | `az_guard_role_permissions` | AzGuard `table_names.role_permissions` default |

## Разделённый префикс `sys_`

Префикс `sys_` не является владельцем. Сегодня его делят:

| Таблицы | Владелец |
|:--|:--|
| `sys_api_budget_buckets`, `sys_api_budget_reservations`, `sys_api_bulk_operations`, `sys_api_idempotency`, `sys_api_webhook_attempts`, `sys_api_webhook_deliveries`, `sys_api_webhook_subscriptions` | `corex/operations` |
| `sys_attribute_backfills`, `sys_attribute_definitions`, `sys_attribute_documents`, `sys_attribute_eav_records`, `sys_attribute_eav_values`, `sys_attribute_key_reservations`, `sys_attribute_schema_operations`, `sys_attribute_write_tokens` | `corex/attributes` |
| `sys_audit_log`, `sys_department_user`, `sys_departments`, `sys_feature_flags`, `sys_feature_overrides`, `sys_inbox_messages`, `sys_outbox_messages`, `sys_pubsub_cursors`, `sys_pubsub_messages`, `sys_saved_view_scopes`, `sys_saved_views`, `sys_settings` | `corex/core` |
| `sys_wakeup_requests` | `corex/wakeup` |
| `sys_workflow_intents` | `corex/workflow` |

Строки ниже — источник правды по таблице. Расхождение префикса со строкой — дефект сканера либо новой миграции, а не повод «починить» префикс.

## Каталог таблиц

| table | owning_package | tenant_scope | writers | readers | pii_class | cross_package_access | indexes | restore_policy | retention_policy | upgrade_strategy | object_class | notes |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| sys_api_idempotency | corex/operations | tenant | corex/operations | corex/operations | pii-pseudonymous | forbidden | unique(['scope_hash', 'key_hash']); index(['state', 'expires_at']) | tenant-backup | finite terminal retention; started rows require reconciliation | expand-migrate-contract (policy-default) | operation-state | вне `DB_SCHEMA.md` FROZEN v1.0; tenant-schema DDL (D104, G-DDL P13.5); raw idempotency keys are never stored |
| sys_api_budget_buckets | corex/operations | shared-budget | corex/operations | corex/operations | pii-pseudonymous | forbidden | primary(['key']) | shared-budget-backup | active bucket retained; opaque HMAC key only | expand-migrate-contract (policy-default) | operation-state | isolated shared connection; no tenant payload or raw identity keys (D76) |
| sys_api_budget_reservations | corex/operations | shared-budget | corex/operations | corex/operations | pii-pseudonymous | forbidden | primary(['id']); index(['state', 'expires_at']) | shared-budget-backup | expired terminal reservations pruned; held rows require reconciliation | expand-migrate-contract (policy-default) | operation-state | exactly four opaque HMAC budget keys; no tenant payload (D76) |
| sys_api_bulk_operations | corex/operations | tenant | corex/operations | corex/operations | pii-pseudonymous | forbidden | primary(['id']); unique(['major', 'operation_id', 'submission_id']); index(['status', 'next_attempt_at', 'lease_until']) | tenant-backup | terminal metadata and owned result refs retained until expires_at | expand-migrate-contract (policy-default) | operation-state | tenant-local resumable operation state and typed storage refs (D76) |
| sys_api_webhook_subscriptions | corex/operations | tenant | corex/operations | corex/operations | pii-indirect | forbidden | primary(['id']); index(['status', 'topic', 'major']) | tenant-backup | disabled subscription metadata retained by tenant policy | expand-migrate-contract (policy-default) | operation-state | endpoint snapshot policy and secret references; secret values are never stored (D76) |
| sys_api_webhook_deliveries | corex/operations | tenant | corex/operations | corex/operations | pii-direct | forbidden | primary(['id']); unique(['subscription_id', 'event_id']); index(['status', 'next_attempt_at', 'lease_until']) | tenant-backup | payload body 7 days; delivery metadata/replay authority 30 days | expand-migrate-contract (policy-default) | operation-state | subscription deletion does not cascade delivery evidence (D76, D84) |
| sys_api_webhook_attempts | corex/operations | tenant | corex/operations | corex/operations | pii-pseudonymous | forbidden | primary(['id']); unique(['delivery_id', 'attempt']); index(['delivery_id', 'started_at']) | tenant-backup | attempt metadata follows 30-day delivery retention | expand-migrate-contract (policy-default) | operation-state | transport outcome only; no response body or credentials (D76) |
| sys_attribute_documents | corex/attributes | tenant | corex/attributes |  | unobserved | forbidden | primary(['entity', 'record_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| sys_attribute_definitions | corex/attributes | tenant | corex/attributes |  | unobserved | forbidden | primary(['attribute_id', 'revision']); index(['entity', 'attribute_id', 'revision']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private | P10.3 immutable definition revisions |
| sys_attribute_key_reservations | corex/attributes | tenant | corex/attributes |  | unobserved | forbidden | primary(['entity', 'key']); index(['attribute_id', 'current_revision']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private | P10.3 key ownership |
| sys_attribute_backfills | corex/attributes | tenant | corex/attributes |  | unobserved | forbidden | primary(['id']); index(['attribute_id', 'to_revision']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private | P10.4 resumable migration ledger |
| sys_attribute_eav_records | corex/attributes | tenant | corex/attributes |  | unobserved | forbidden | primary(['entity', 'record_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| sys_attribute_eav_values | corex/attributes | tenant | corex/attributes |  | unobserved | forbidden | primary(['entity', 'record_id', 'field']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| sys_attribute_schema_operations | corex/attributes | tenant | corex/attributes |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| sys_attribute_write_tokens | corex/attributes | tenant | corex/attributes |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| aut_role_assignments | corex/access | tenant | corex/access |  | unobserved | exception |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private | D102 cross-package FK exception (DATA-004) |
| aut_role_matrix | corex/access | tenant | corex/access |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| aut_roles | corex/access | tenant | corex/access |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| az_guard_roles | corex/access | tenant | corex/access |  | unobserved | forbidden |  | unobserved | unobserved | vendor-managed | private | AzGuard migration; `corex/access` pins the role model/table |
| az_guard_role_permissions | corex/access | tenant | corex/access |  | unobserved | forbidden |  | unobserved | unobserved | vendor-managed | private | AzGuard migration; `corex/access` compiler writes this table |
| sys_audit_log | corex/core | tenant | corex/core |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private | table-name-via-config-default |
| sys_department_user | corex/core | tenant | corex/core |  | unobserved | forbidden | primary(['department_id', 'user_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private | table-name-via-config-default |
| sys_departments | corex/core | tenant | corex/core |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private | table-name-via-config-default |
| sys_feature_flags | corex/core | tenant | corex/core |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private | table-name-via-config-default |
| sys_feature_overrides | corex/core | tenant | corex/core |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private | table-name-via-config-default |
| sys_inbox_messages | corex/core | tenant | corex/core |  | unobserved | forbidden | primary(['consumer', 'message_id']); index(['processed_at'], 'sys_inbox_messages_processed_idx') | unobserved | unobserved | expand-migrate-contract (policy-default) | outbox | вне `DB_SCHEMA.md` FROZEN v1.0; tenant-schema DDL (D27, G-DDL P5.4) |
| sys_outbox_messages | corex/core | tenant | corex/core |  | unobserved | forbidden | partial indexes claim/reclaim/published/dead (P5.4) | unobserved | unobserved | expand-migrate-contract (policy-default) | outbox | вне `DB_SCHEMA.md` FROZEN v1.0; tenant-schema DDL (D27, G-DDL P5.4) |
| sys_pubsub_cursors | corex/core | tenant | corex/core |  | unobserved | forbidden | primary(['channel', 'subscriber']) | unobserved | unobserved | expand-migrate-contract (policy-default) | outbox | table-name-via-config-default |
| sys_pubsub_messages | corex/core | tenant | corex/core |  | unobserved | forbidden | index(['channel', 'id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | outbox | table-name-via-config-default |
| sys_saved_view_scopes | corex/core | tenant | corex/core |  | unobserved | forbidden | primary(['entity_code', 'scope_key']) | unobserved | unobserved | expand-migrate-contract (policy-default) | projection | table-name-via-config-default |
| sys_saved_views | corex/core | tenant | corex/core |  | unobserved | forbidden | unique(['entity_code', 'default_scope_key']) | unobserved | unobserved | expand-migrate-contract (policy-default) | projection | table-name-via-config-default |
| sys_settings | corex/core | tenant | corex/core |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private | table-name-via-config-default |
| corex_rate_batches | corex/intl | tenant | corex/intl |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_rate_current | corex/intl | tenant | corex/intl |  | unobserved | forbidden | primary(['base', 'quote', 'source']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_rate_snapshots | corex/intl | tenant | corex/intl |  | unobserved | forbidden | unique(['source', 'publication_id', 'publication_revision', 'base', 'quote', 'valid_on'], 'corex_rate_snapshots_publication_unique') | unobserved | unobserved | expand-migrate-contract (policy-default) | projection |  |
| corex_reference_aliases | corex/intl | tenant | corex/intl |  | unobserved | forbidden | primary(['snapshot_id', 'namespace', 'source_id', 'locale', 'alias']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_reference_current | corex/intl | tenant | corex/intl |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_reference_datasets | corex/intl | tenant | corex/intl |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_reference_records | corex/intl | tenant | corex/intl |  | unobserved | forbidden | index(['snapshot_id', 'kind', 'normalized_name'], 'corex_reference_records_scan_idx'); primary(['snapshot_id', 'namespace', 'source_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| med_conversions | corex/media | tenant | corex/media |  | unobserved | forbidden | unique(['file_id', 'profile_key', 'profile_digest', 'source_version']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| med_files | corex/media | tenant | corex/media |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| med_operations | corex/media | tenant | corex/media |  | unobserved | forbidden | index(['state', 'next_attempt_at']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| med_relations | corex/media | tenant | corex/media |  | unobserved | forbidden | unique(['file_id', 'entity_handle', 'entity_id', 'slot']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| mod_lifecycle_log | corex/modules | tenant | corex/modules |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| mod_modules | corex/modules | tenant | corex/modules |  | unobserved | forbidden | index('state', 'mod_modules_state_ix'); unique('name', 'mod_modules_name_uq') | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| mod_records | corex/modules | tenant | corex/modules |  | unobserved | forbidden | primary(['module_name', 'record_type', 'reference']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| ntf_deliveries | corex/notifications | tenant | corex/notifications |  | unobserved | forbidden | index(['state', 'next_attempt'], 'ntf_deliveries_scan_idx') | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| ntf_digest_items | corex/notifications | tenant | corex/notifications |  | unobserved | forbidden | unique(['notification_id', 'channel']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| ntf_digest_runs | corex/notifications | tenant | corex/notifications |  | unobserved | forbidden | unique(['recipient_kind', 'recipient_id', 'period', 'period_start', 'batch_seq']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| ntf_notifications | corex/notifications | tenant | corex/notifications |  | unobserved | forbidden | index(['recipient_kind', 'recipient_id', 'read_at', 'id'], 'ntf_notifications_scan_idx'); unique(['recipient_kind', 'recipient_id', 'request_key']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| ntf_recipient_state | corex/notifications | tenant | corex/notifications |  | unobserved | forbidden | primary(['kind', 'id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_search_dirty | corex/search | tenant | corex/search |  | unobserved | forbidden | index(['lane_key', 'submitted_at', 'sequence']); unique(['lane_key', 'record_id', 'revision']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_search_lanes | corex/search | tenant | corex/search |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_search_rebuilds | corex/search | tenant | corex/search |  | unobserved | forbidden | unique(['lane_key', 'to_generation']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_task_operations | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tsk_processes | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | unique(['code']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tsk_project_participants | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | unique(['project_id', 'user_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tsk_projects | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | unique(['code']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private | duplicate-create:packages/tasks/database/migrations/2026_09_11_000260_align_tasks_with_approved_schema.php |
| tsk_sprints | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tsk_stages | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | index(['process_id', 'position']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private | duplicate-create:packages/tasks/database/migrations/2026_09_11_000260_align_tasks_with_approved_schema.php |
| tsk_task_participants | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | unique(['task_id', 'user_id', 'role']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tsk_task_targets | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | index(['entity_type', 'entity_id'], 'tsk_tt_entity_ix'); primary(['task_id', 'entity_type', 'entity_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tsk_task_watchers | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | primary(['task_id', 'user_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tsk_tasks | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | index(['process_id']); index(['responsible_id', 'stage_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private | duplicate-create:packages/tasks/database/migrations/2026_09_11_000260_align_tasks_with_approved_schema.php |
| tsk_time_logs | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | index('task_id', 'tsk_tl_task_ix'); index(['user_id', 'spent_on'], 'tsk_tl_user_ix') | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tsk_timers | corex/tasks | tenant | corex/tasks |  | unobserved | forbidden | primary()->constrained('users')->cascadeOnDelete() | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_task_occurrences | corex/tasks-workflow | tenant | corex/tasks-workflow |  | unobserved | forbidden | unique(['series_key', 'immutable_revision', 'occurrence_key']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_task_series | corex/tasks-workflow | tenant | corex/tasks-workflow |  | unobserved | forbidden | unique(['series_key', 'immutable_revision']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_task_series_state | corex/tasks-workflow | tenant | corex/tasks-workflow |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_account_events | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_account_exports | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_account_products | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden | primary(['account_id', 'product']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_account_schema_state | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_accounts | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_clusters | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_domains | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_feature_flags | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_identities | corex/tenancy | root | corex/tenancy |  | pii-direct | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_identity_accounts | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden | unique(['identity_id', 'account_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_idp_sessions | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_impersonation_grants | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_migration_batches | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_oauth_clients | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_oauth_refresh_tokens | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_templates | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_usage_snapshots | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden | primary(['account_id', 'metric', 'period']) | unobserved | unobserved | expand-migrate-contract (policy-default) | projection |  |
| root_wakeup_hints | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden | index(['next_fire_at', 'leased_until'], 'root_wakeup_hints_scan_idx') | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| root_wakeup_reconciliation_state | corex/tenancy | root | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| sessions | corex/tenancy | tenant | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| tnt_membership_inbox | corex/tenancy | tenant | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private | table-name-from-const:InboxTable |
| tnt_membership_pairs | corex/tenancy | tenant | corex/tenancy |  | unobserved | forbidden | primary(['account_id', 'identity_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private | table-name-from-const:PairTable |
| membership_credential_remembers | corex/tenancy | test | corex/tenancy |  | pii-direct | forbidden | primary(['remember_token']) | disposable | test-only | fixture-only | private | Unregistered credential reference consumer; created by CredentialFixture, not production migration. |
| membership_credential_sessions | corex/tenancy | test | corex/tenancy |  | pii-direct | forbidden | primary(['session_id']) | disposable | test-only | fixture-only | private | Unregistered credential reference consumer; created by CredentialFixture, not production migration. |
| membership_pair_references | corex/tenancy | test | corex/tenancy |  | unobserved | forbidden | primary(['account_id', 'identity_id']) | disposable | test-only | fixture-only | private | Unregistered PairStore reference mapping; created by PairStoreFixture. |
| membership_trigger_inbox | corex/tenancy | test | corex/tenancy |  | unobserved | forbidden | primary(['event_id']) | disposable | test-only | fixture-only | private | Unregistered DurableInbox reference mapping; created by InboxFixture. |
| users | corex/tenancy | tenant | corex/tenancy |  | pii-direct | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| wsp_invitations | corex/tenancy | tenant | corex/tenancy |  | pii-direct | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| wsp_workspace_members | corex/tenancy | tenant | corex/tenancy |  | unobserved | forbidden | unique(['workspace_id', 'user_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| wsp_workspaces | corex/tenancy | tenant | corex/tenancy |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_export_manifests | corex/transfer | tenant | corex/transfer |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_export_parts | corex/transfer | tenant | corex/transfer |  | unobserved | forbidden | unique(['manifest_id', 'part_no']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_transfer_jobs | corex/transfer | tenant | corex/transfer |  | unobserved | forbidden | index(['state', 'updated_at']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_transfer_outbox | corex/transfer | tenant | corex/transfer |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | outbox |  |
| corex_transfer_refs | corex/transfer | tenant | corex/transfer |  | unobserved | forbidden | unique(['entity', 'external_system', 'external_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| corex_transfer_rows | corex/transfer | tenant | corex/transfer |  | unobserved | forbidden | unique(['job_id', 'ordinal']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| sys_wakeup_requests | corex/wakeup | tenant | corex/wakeup |  | unobserved | forbidden | index(['status', 'retry_at', 'fire_at'], 'sys_wakeup_requests_scan_idx'); unique(['workspace_scope_key', 'kind', 'request_key']); unique(['workspace_scope_key', 'request_key']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| sys_workflow_intents | corex/workflow | tenant | corex/workflow |  | unobserved | forbidden | index(['state', 'updated_at']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_activities | flex/crm | tenant | flex/crm |  | unobserved | forbidden | index(['subject_type', 'subject_id']); index(['type']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_activity_targets | flex/crm | tenant | flex/crm |  | unobserved | forbidden | index(['entity_type', 'entity_id'], 'crm_at_entity_ix'); primary(['activity_id', 'entity_type', 'entity_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_companies | flex/crm | tenant | flex/crm |  | pii-indirect | forbidden | index(['name']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_contact_company | flex/crm | tenant | flex/crm |  | pii-indirect | forbidden | unique(['contact_id', 'company_id']); unique(['contact_id']) WHERE is_primary | unobserved | unobserved | expand-migrate-contract (policy-default) | private | packages/crm/database/migrations/2026_09_14_000002_company_contact_parity.php |
| crm_contacts | flex/crm | tenant | flex/crm |  | pii-direct | forbidden | index(['email']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_deal_contacts | flex/crm | tenant | flex/crm |  | unobserved | forbidden | index('contact_id', 'crm_dc_contact_ix'); unique(['deal_id', 'contact_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_deals | flex/crm | tenant | flex/crm |  | unobserved | forbidden | index(['stage_id']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_leads | flex/crm | tenant | flex/crm |  | pii-direct | forbidden | index(['status']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_lost_reasons | flex/crm | tenant | flex/crm |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_pipelines | flex/crm | tenant | flex/crm |  | unobserved | forbidden |  | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
| crm_stages | flex/crm | tenant | flex/crm |  | unobserved | forbidden | index(['pipeline_id', 'position']) | unobserved | unobserved | expand-migrate-contract (policy-default) | private |  |
