# Реестр конфигурации

Инварианты — статьи `CFG-001` … `CFG-006`. Процедура — `config-policy.md`.
Эта страница — каталог текущего состояния (P2.4). Типизированные объекты опций
появятся в P7; колонка `typed_options_field` сегодня пуста во всех строках, и это
измеренный факт, а не забытое поле.
## Каталог ключей

| key | owner | type | default | secret | mode | reloadable | validation | typed_options_field | deprecated_aliases | notes |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| auth.* | corex/access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-api.require_physical_tenancy | corex/operations | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.drivers.ddl | corex/attributes | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.drivers.eav | corex/attributes | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.drivers.jsonb | corex/attributes | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.storage_driver | corex/attributes | string | jsonb | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.documents | corex/attributes | string | sys_attribute_documents | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.eav_records | corex/attributes | string | sys_attribute_eav_records | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.eav_values | corex/attributes | string | sys_attribute_eav_values | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.schema_operations | corex/attributes | string | sys_attribute_schema_operations | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.write_tokens | corex/attributes | string | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-auth.azguard_pins.column_names.morph_type | corex/access | string | uuid | no | product-policy | unobserved | use-site | — |  |  |
| corex-auth.azguard_pins.models.role | corex/access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex-auth.azguard_pins.table_names.roles | corex/access | string | az_guard_roles | no | product-policy | unobserved | use-site | — |  |  |
| corex-auth.bind_permission_layer | corex/access | bool | true | no | deployment | no | use-site | — |  |  |
| corex-auth.cache.store | corex/access | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| corex-auth.identity | corex/access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-auth.identity.columns | corex/access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-auth.identity.columns.active | corex/access | string | is_active | no | product-policy | unobserved | unobserved | — |  |  |
| corex-auth.identity.columns.deleted_at | corex/access | string | deleted_at | no | product-policy | unobserved | unobserved | — |  |  |
| corex-auth.identity.columns.email | corex/access | string | email | no | product-policy | unobserved | unobserved | — |  |  |
| corex-auth.identity.columns.id | corex/access | string | id | no | product-policy | unobserved | unobserved | — |  |  |
| corex-auth.identity.columns.password | corex/access | string | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-auth.identity.connection | corex/access | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| corex-auth.identity.enabled | corex/access | bool | true | no | deployment | no | use-site | — |  |  |
| corex-auth.identity.table | corex/access | string | users | no | deployment | no | use-site | — |  |  |
| corex-auth.panel | corex/access | string | app | no | deployment | no | use-site | — |  |  |
| corex-auth.workspace_scope_class | corex/access | string | CoreX\\Tenancy\\Scopes\\WorkspaceScope | no | product-policy | unobserved | use-site | — |  |  |
| corex-intl-search.enabled | corex/search-intl | bool | false | no | deployment | no | unobserved | — |  | CFG-006 exception; tombstone after major of search-intl |
| corex-intl.formatting.icu_enabled | corex/intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.allow_parent | corex/intl | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.app_tag | corex/intl | string | en | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.max_supported_tags | corex/intl | int | 100 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.max_tag_length | corex/intl | int | 128 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.settings_key | corex/intl | string | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-intl.locale.settings_namespace | corex/intl | string | intl | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.money.batch_limit | corex/intl | int | 1000 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.money.default_source | corex/intl | string | manual | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.money.metadata_version | corex/intl | string | brick/1 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.reference.cursor_secret | corex/intl | string | *** | yes | secret | no | unobserved | — |  |  |
| corex-intl.reference.dataset_key | corex/intl | string | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-intl.reference.providers.boxes.fixture | corex/intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.reference.providers.compact.fixture | corex/intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.allowed_mime_types | corex/media | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-media.conversion.thumbnail.max_height | corex/media | int | 4096 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.conversion.thumbnail.max_output_bytes | corex/media | int | 10 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.conversion.thumbnail.max_pixels | corex/media | int | 20_000_000 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.conversion.thumbnail.max_width | corex/media | int | 4096 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.conversion_driver_tag | corex/media | string | corex.media.conversion-drivers | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.max_image_pixels | corex/media | int | 20_000_000 | no | product-policy | unobserved | use-site | — |  |  |
| corex-media.max_upload_bytes | corex/media | int | 10 | no | product-policy | unobserved | use-site | — |  |  |
| corex-media.purposes | corex/media | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-modules.cache.* | corex/modules | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-modules.cache.prefix | corex/modules | string | corex-modules | no | deployment | no | use-site | — |  |  |
| corex-modules.cache.store | corex/modules | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| corex-modules.cache.ttl | corex/modules | int | 3600 | no | deployment | no | use-site | — |  |  |
| corex-modules.connection | corex/modules | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| corex-notifications.delivery.claim_seconds | corex/notifications | int | 60 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-notifications.delivery.max_attempts | corex/notifications | int | 5 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-notifications.digest.enabled | corex/notifications | bool | false | no | product-policy | unobserved | unobserved | — |  |  |
| corex-panels.provider_tag | corex/panels | string | corex.panels.providers | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search-meilisearch.host | corex/search-meilisearch | null | null | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search-meilisearch.index_uid | corex/search-meilisearch | null | null | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search-meilisearch.key | corex/search-meilisearch | null | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-search.cursor_secret | corex/search | null | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-search.driver | corex/search | string | null | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search.postgres.scan_budget | corex/search | int | 1000 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search.postgres.text_search_config | corex/search | string | simple | no | product-policy | unobserved | unobserved | — |  |  |
| corex-task-recurrence.completion_capture.enabled | corex/tasks-workflow | bool | false | no | product-policy | unobserved | unobserved | — |  |  |
| corex-task-recurrence.wakeup.enabled | corex/tasks-workflow | bool | false | no | product-policy | unobserved | unobserved | — |  |  |
| corex-task-recurrence.wakeup.handler_kind | corex/tasks-workflow | string | task-recurrence.materialize | no | product-policy | unobserved | unobserved | — |  |  |
| corex-tenancy.wakeup.driver | corex/tenancy | string | local_sweep | no | deployment | no | use-site | — |  |  |
| corex-tenancy.wakeup.lease_seconds | corex/tenancy | int | 60 | no | deployment | no | use-site | — |  |  |
| corex-tenancy.wakeup.root_connection | corex/tenancy | string | pgsql | no | deployment | no | use-site | — |  |  |
| corex-transfer.limits.batch_rows | corex/transfer | int | 500 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-transfer.limits.max_fields | corex/transfer | int | 256 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-transfer.limits.max_record_bytes | corex/transfer | int | 1_048_576 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-wakeup.lease_seconds | corex/wakeup | int | 60 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-wakeup.max_attempts | corex/wakeup | int | 5 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-workflow.enforce_declarations | corex/workflow | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex.* | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex.audit.enabled | corex/core | bool | true | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.audit_logger | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.audit_retention | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.data_scope_authorizer | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.departments | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.feature_flags | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.setting_defaults | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.settings | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.ids.strategy | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.pubsub.driver | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.pubsub.octane_worker_opt_in | corex/core | bool | false | no | product-policy | unobserved | use-site | — |  |  |
| corex.pubsub.poll_interval_microseconds | corex/core | int | 50_000 | no | product-policy | unobserved | use-site | — |  |  |
| corex.pubsub.retention | corex/core | string | 30 days | no | product-policy | unobserved | use-site | — |  |  |
| corex.settings.cache_prefix | corex/core | string | corex.settings | no | product-policy | unobserved | use-site | — |  |  |
| corex.settings.cache_store | corex/core | string | array | no | product-policy | unobserved | use-site | — |  |  |
| corex.settings.cache_ttl | corex/core | int | 3600 | no | product-policy | unobserved | use-site | — |  |  |
| corex.storage.durable_root | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.storage.ephemeral_root | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.audit_logs | corex/core | string | sys_audit_log | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.department_user | corex/core | string | sys_department_user | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.departments | corex/core | string | sys_departments | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.feature_flags | corex/core | string | sys_feature_flags | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.feature_overrides | corex/core | string | sys_feature_overrides | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.pubsub_cursors | corex/core | string | sys_pubsub_cursors | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.pubsub_messages | corex/core | string | sys_pubsub_messages | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.saved_view_scopes | corex/core | string | sys_saved_view_scopes | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.saved_views | corex/core | string | sys_saved_views | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.settings | corex/core | string | sys_settings | no | product-policy | unobserved | use-site | — |  |  |
| corex.tenant_context.impersonation | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.tenant_context.manager | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.tenant_context.resolver | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.views.connection | corex/core | null | null | no | product-policy | unobserved | use-site | — |  |  |
| database.default | corex/core | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| flex-crm.search_language | flex/crm | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.bootstrapper_order | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.cache_prefix | corex/tenancy | string | tenant_%tenant%_ | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.central_connection | corex/tenancy | string | pgsql | no | deployment | no | use-site | — |  |  |
| tenancy.domain_trust.challenge_ttl_seconds | corex/tenancy | int | 300 | no | deployment | no | use-site | — |  |  |
| tenancy.domain_trust.enabled | corex/tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.grace_days | corex/tenancy | int | 30 | no | deployment | no | use-site | — |  |  |
| tenancy.host_identification.cache | corex/tenancy | bool | true | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.host_identification.cache_store | corex/tenancy | null | null | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.host_identification.cache_ttl | corex/tenancy | int | 300 | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.host_identification.enabled | corex/tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.id_generator | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.identification.middleware | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.membership_guard.guard | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.oidc.allowed_algs | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.oidc.client_id | corex/tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.client_secret | corex/tenancy | unobserved | unobserved | yes | secret | no | unobserved | — |  |  |
| tenancy.oidc.discovery.cache_store | corex/tenancy | unobserved | unobserved | no | deployment | no | unobserved | — |  |  |
| tenancy.oidc.discovery.cache_ttl | corex/tenancy | int | 3600 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.enabled | corex/tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.guard | corex/tenancy | string | web | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.impersonation.enabled | corex/tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.impersonation.require_approved_by | corex/tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.issuer | corex/tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.jwks.cache_store | corex/tenancy | unobserved | unobserved | no | deployment | no | unobserved | — |  |  |
| tenancy.oidc.jwks.cache_ttl | corex/tenancy | int | 3600 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.jwks.refetch_cooldown_seconds | corex/tenancy | int | 60 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.leeway_seconds | corex/tenancy | int | 30 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.max_session_lifetime_minutes | corex/tenancy | int | 720 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.redirect_uri | corex/tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.platform_domain | corex/tenancy | string | example.test | no | deployment | no | use-site | — |  |  |
| tenancy.provisioning.pipeline | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.stancl.jobpipeline_pin | corex/tenancy | string | 2.0.0-rc7 | no | product-policy | unobserved | unobserved | — |  |  |
| tenancy.stancl.package | corex/tenancy | string | stancl/tenancy | no | product-policy | unobserved | unobserved | — |  |  |
| tenancy.stancl.pin | corex/tenancy | string | dev-master#76e5f9655924271c6c9da54e2d4ccb82c6f71ea0 as 4.0.0 | no | product-policy | unobserved | unobserved | — |  |  |
| tenancy.status_gate.export_route | corex/tenancy | null | null | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.status_gate.payment_route | corex/tenancy | null | null | no | product-policy | unobserved | unobserved | — |  |  |
| tenancy.status_gate.read_only_methods | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.storage.durable_driver | corex/tenancy | string | local | no | deployment | no | use-site | — |  |  |
| tenancy.storage.durable_root | corex/tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.storage.ephemeral_root | corex/tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.template_integrity.manifests | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.template_integrity.trusted_keys | corex/tenancy | unobserved | unobserved | yes | secret | unobserved | use-site | — |  | undeclared-read |
| tenancy.tenant_database_manager | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.tenant_model | corex/tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.trial_days | corex/tenancy | int | 14 | no | deployment | no | use-site | — |  |  |
| tenancy.workspace.path_segment | corex/tenancy | string | w | no | deployment | no | use-site | — |  |  |
