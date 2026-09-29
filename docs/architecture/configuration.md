# Реестр конфигурации

Инварианты — статьи `CFG-001` … `CFG-006`. Процедура — `config-policy.md`.
Эта страница — каталог текущего состояния (P2.4). Типизированные объекты опций
появятся в P7; колонка `typed_options_field` сегодня пуста во всех строках, и это
измеренный факт, а не забытое поле.
## Каталог ключей

| key | owner | type | default | secret | mode | reloadable | validation | typed_options_field | deprecated_aliases | notes |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| auth.* | axiomasoft/corex-access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-api.require_physical_tenancy | axiomasoft/corex-operations | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.drivers.ddl | axiomasoft/corex-custom-fields | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.drivers.eav | axiomasoft/corex-custom-fields | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.drivers.jsonb | axiomasoft/corex-custom-fields | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.storage_driver | axiomasoft/corex-custom-fields | string | jsonb | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.documents | axiomasoft/corex-custom-fields | string | sys_attribute_documents | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.eav_records | axiomasoft/corex-custom-fields | string | sys_attribute_eav_records | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.eav_values | axiomasoft/corex-custom-fields | string | sys_attribute_eav_values | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.schema_operations | axiomasoft/corex-custom-fields | string | sys_attribute_schema_operations | no | product-policy | unobserved | unobserved | — |  |  |
| corex-attributes.tables.write_tokens | axiomasoft/corex-custom-fields | string | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-auth.azguard_pins.column_names.morph_type | axiomasoft/corex-access | string | uuid | no | product-policy | unobserved | use-site | — |  |  |
| corex-auth.azguard_pins.models.role | axiomasoft/corex-access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex-auth.azguard_pins.table_names.roles | axiomasoft/corex-access | string | az_guard_roles | no | product-policy | unobserved | use-site | — |  |  |
| corex-auth.bind_permission_layer | axiomasoft/corex-access | bool | true | no | deployment | no | use-site | — |  |  |
| corex-auth.cache.store | axiomasoft/corex-access | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| corex-auth.identity | axiomasoft/corex-access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-auth.identity.columns | axiomasoft/corex-access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-auth.identity.columns.active | axiomasoft/corex-access | string | is_active | no | product-policy | unobserved | unobserved | — |  |  |
| corex-auth.identity.columns.deleted_at | axiomasoft/corex-access | string | deleted_at | no | product-policy | unobserved | unobserved | — |  |  |
| corex-auth.identity.columns.email | axiomasoft/corex-access | string | email | no | product-policy | unobserved | unobserved | — |  |  |
| corex-auth.identity.columns.id | axiomasoft/corex-access | string | id | no | product-policy | unobserved | unobserved | — |  |  |
| corex-auth.identity.columns.password | axiomasoft/corex-access | string | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-auth.identity.connection | axiomasoft/corex-access | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| corex-auth.identity.enabled | axiomasoft/corex-access | bool | true | no | deployment | no | use-site | — |  |  |
| corex-auth.identity.table | axiomasoft/corex-access | string | users | no | deployment | no | use-site | — |  |  |
| corex-auth.panel | axiomasoft/corex-access | string | app | no | deployment | no | use-site | — |  |  |
| corex-auth.workspace_scope_class | axiomasoft/corex-access | string | CoreX\\Tenancy\\Laravel\\Scopes\\WorkspaceScope | no | product-policy | unobserved | use-site | — |  |  |
| corex-intl-search.enabled | axiomasoft/corex-search-intl | bool | false | no | deployment | no | unobserved | — |  | CFG-006 exception; tombstone after major of search-intl |
| corex-intl.formatting.icu_enabled | axiomasoft/corex-intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.allow_parent | axiomasoft/corex-intl | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.app_tag | axiomasoft/corex-intl | string | en | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.max_supported_tags | axiomasoft/corex-intl | int | 100 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.max_tag_length | axiomasoft/corex-intl | int | 128 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.locale.settings_key | axiomasoft/corex-intl | string | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-intl.locale.settings_namespace | axiomasoft/corex-intl | string | intl | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.money.batch_limit | axiomasoft/corex-intl | int | 1000 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.money.default_source | axiomasoft/corex-intl | string | manual | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.money.metadata_version | axiomasoft/corex-intl | string | brick/1 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.reference.cursor_secret | axiomasoft/corex-intl | string | *** | yes | secret | no | unobserved | — |  |  |
| corex-intl.reference.dataset_key | axiomasoft/corex-intl | string | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-intl.reference.providers.boxes.fixture | axiomasoft/corex-intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | — |  |  |
| corex-intl.reference.providers.compact.fixture | axiomasoft/corex-intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.allowed_mime_types | axiomasoft/corex-media | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-media.conversion.thumbnail.max_height | axiomasoft/corex-media | int | 4096 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.conversion.thumbnail.max_output_bytes | axiomasoft/corex-media | int | 10 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.conversion.thumbnail.max_pixels | axiomasoft/corex-media | int | 20_000_000 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.conversion.thumbnail.max_width | axiomasoft/corex-media | int | 4096 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.conversion_driver_tag | axiomasoft/corex-media | string | corex.media.conversion-drivers | no | product-policy | unobserved | unobserved | — |  |  |
| corex-media.max_image_pixels | axiomasoft/corex-media | int | 20_000_000 | no | product-policy | unobserved | use-site | — |  |  |
| corex-media.max_upload_bytes | axiomasoft/corex-media | int | 10 | no | product-policy | unobserved | use-site | — |  |  |
| corex-media.purposes | axiomasoft/corex-media | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-modules.cache.* | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-modules.cache.prefix | axiomasoft/corex | string | corex-modules | no | deployment | no | use-site | — |  |  |
| corex-modules.cache.store | axiomasoft/corex | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| corex-modules.cache.ttl | axiomasoft/corex | int | 3600 | no | deployment | no | use-site | — |  |  |
| corex-modules.connection | axiomasoft/corex | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| corex-notifications.delivery.claim_seconds | axiomasoft/corex-notifications | int | 60 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-notifications.delivery.max_attempts | axiomasoft/corex-notifications | int | 5 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-notifications.digest.enabled | axiomasoft/corex-notifications | bool | false | no | product-policy | unobserved | unobserved | — |  |  |
| corex-panels.provider_tag | axiomasoft/corex-panels | string | corex.panels.providers | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search-meilisearch.host | axiomasoft/corex-search-meilisearch | null | null | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search-meilisearch.index_uid | axiomasoft/corex-search-meilisearch | null | null | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search-meilisearch.key | axiomasoft/corex-search-meilisearch | null | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-search.cursor_secret | axiomasoft/corex-search | null | *** | yes | secret | unobserved | unobserved | — |  |  |
| corex-search.driver | axiomasoft/corex-search | string | null | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search.postgres.scan_budget | axiomasoft/corex-search | int | 1000 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-search.postgres.text_search_config | axiomasoft/corex-search | string | simple | no | product-policy | unobserved | unobserved | — |  |  |
| corex-task-recurrence.completion_capture.enabled | axiomasoft/corex-tasks-workflow | bool | false | no | product-policy | unobserved | unobserved | — |  |  |
| corex-task-recurrence.wakeup.enabled | axiomasoft/corex-tasks-workflow | bool | false | no | product-policy | unobserved | unobserved | — |  |  |
| corex-task-recurrence.wakeup.handler_kind | axiomasoft/corex-tasks-workflow | string | task-recurrence.materialize | no | product-policy | unobserved | unobserved | — |  |  |
| corex-tenancy.wakeup.driver | axiomasoft/corex-tenancy | string | local_sweep | no | deployment | no | use-site | — |  |  |
| corex-tenancy.wakeup.lease_seconds | axiomasoft/corex-tenancy | int | 60 | no | deployment | no | use-site | — |  |  |
| corex-tenancy.wakeup.root_connection | axiomasoft/corex-tenancy | string | pgsql | no | deployment | no | use-site | — |  |  |
| corex-transfer.limits.batch_rows | axiomasoft/corex-transfer | int | 500 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-transfer.limits.max_fields | axiomasoft/corex-transfer | int | 256 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-transfer.limits.max_record_bytes | axiomasoft/corex-transfer | int | 1_048_576 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-wakeup.lease_seconds | axiomasoft/corex-wakeup | int | 60 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-wakeup.max_attempts | axiomasoft/corex-wakeup | int | 5 | no | product-policy | unobserved | unobserved | — |  |  |
| corex-workflow.enforce_declarations | axiomasoft/corex-workflow | bool | true | no | product-policy | unobserved | unobserved | — |  |  |
| corex.* | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex.audit.enabled | axiomasoft/corex | bool | true | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.audit_logger | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.audit_retention | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.data_scope_authorizer | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.departments | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.feature_flags | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.setting_defaults | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.bindings.settings | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.ids.strategy | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.pubsub.driver | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.pubsub.octane_worker_opt_in | axiomasoft/corex | bool | false | no | product-policy | unobserved | use-site | — |  |  |
| corex.pubsub.poll_interval_microseconds | axiomasoft/corex | int | 50_000 | no | product-policy | unobserved | use-site | — |  |  |
| corex.pubsub.retention | axiomasoft/corex | string | 30 days | no | product-policy | unobserved | use-site | — |  |  |
| corex.settings.cache_prefix | axiomasoft/corex | string | corex.settings | no | product-policy | unobserved | use-site | — |  |  |
| corex.settings.cache_store | axiomasoft/corex | string | array | no | product-policy | unobserved | use-site | — |  |  |
| corex.settings.cache_ttl | axiomasoft/corex | int | 3600 | no | product-policy | unobserved | use-site | — |  |  |
| corex.storage.durable_root | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.storage.ephemeral_root | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.audit_logs | axiomasoft/corex | string | sys_audit_log | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.department_user | axiomasoft/corex | string | sys_department_user | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.departments | axiomasoft/corex | string | sys_departments | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.feature_flags | axiomasoft/corex | string | sys_feature_flags | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.feature_overrides | axiomasoft/corex | string | sys_feature_overrides | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.pubsub_cursors | axiomasoft/corex | string | sys_pubsub_cursors | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.pubsub_messages | axiomasoft/corex | string | sys_pubsub_messages | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.saved_view_scopes | axiomasoft/corex | string | sys_saved_view_scopes | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.saved_views | axiomasoft/corex | string | sys_saved_views | no | product-policy | unobserved | use-site | — |  |  |
| corex.tables.settings | axiomasoft/corex | string | sys_settings | no | product-policy | unobserved | use-site | — |  |  |
| corex.tenant_context.impersonation | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.tenant_context.manager | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.tenant_context.resolver | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| corex.views.connection | axiomasoft/corex | null | null | no | product-policy | unobserved | use-site | — |  |  |
| database.default | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| flex-crm.search_language | flex/crm | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.bootstrapper_order | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.cache_prefix | axiomasoft/corex-tenancy | string | tenant_%tenant%_ | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.central_connection | axiomasoft/corex-tenancy | string | pgsql | no | deployment | no | use-site | — |  |  |
| tenancy.domain_trust.challenge_ttl_seconds | axiomasoft/corex-tenancy | int | 300 | no | deployment | no | use-site | — |  |  |
| tenancy.domain_trust.enabled | axiomasoft/corex-tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.grace_days | axiomasoft/corex-tenancy | int | 30 | no | deployment | no | use-site | — |  |  |
| tenancy.host_identification.cache | axiomasoft/corex-tenancy | bool | true | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.host_identification.cache_store | axiomasoft/corex-tenancy | null | null | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.host_identification.cache_ttl | axiomasoft/corex-tenancy | int | 300 | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.host_identification.enabled | axiomasoft/corex-tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.id_generator | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.identification.middleware | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.membership_guard.guard | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.oidc.allowed_algs | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.oidc.client_id | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.client_secret | axiomasoft/corex-tenancy | unobserved | unobserved | yes | secret | no | unobserved | — |  |  |
| tenancy.oidc.discovery.cache_store | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | unobserved | — |  |  |
| tenancy.oidc.discovery.cache_ttl | axiomasoft/corex-tenancy | int | 3600 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.enabled | axiomasoft/corex-tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.guard | axiomasoft/corex-tenancy | string | web | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.impersonation.enabled | axiomasoft/corex-tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.impersonation.require_approved_by | axiomasoft/corex-tenancy | bool | false | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.issuer | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.jwks.cache_store | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | unobserved | — |  |  |
| tenancy.oidc.jwks.cache_ttl | axiomasoft/corex-tenancy | int | 3600 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.jwks.refetch_cooldown_seconds | axiomasoft/corex-tenancy | int | 60 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.leeway_seconds | axiomasoft/corex-tenancy | int | 30 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.max_session_lifetime_minutes | axiomasoft/corex-tenancy | int | 720 | no | deployment | no | use-site | — |  |  |
| tenancy.oidc.redirect_uri | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.platform_domain | axiomasoft/corex-tenancy | string | example.test | no | deployment | no | use-site | — |  |  |
| tenancy.provisioning.pipeline | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.stancl.jobpipeline_pin | axiomasoft/corex-tenancy | string | 2.0.0-rc7 | no | product-policy | unobserved | unobserved | — |  |  |
| tenancy.stancl.package | axiomasoft/corex-tenancy | string | stancl/tenancy | no | product-policy | unobserved | unobserved | — |  |  |
| tenancy.stancl.pin | axiomasoft/corex-tenancy | string | dev-master#76e5f9655924271c6c9da54e2d4ccb82c6f71ea0 as 4.0.0 | no | product-policy | unobserved | unobserved | — |  |  |
| tenancy.status_gate.export_route | axiomasoft/corex-tenancy | null | null | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.status_gate.payment_route | axiomasoft/corex-tenancy | null | null | no | product-policy | unobserved | unobserved | — |  |  |
| tenancy.status_gate.read_only_methods | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.storage.durable_driver | axiomasoft/corex-tenancy | string | local | no | deployment | no | use-site | — |  |  |
| tenancy.storage.durable_root | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.storage.ephemeral_root | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | — |  |  |
| tenancy.template_integrity.manifests | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| tenancy.template_integrity.trusted_keys | axiomasoft/corex-tenancy | unobserved | unobserved | yes | secret | unobserved | use-site | — |  | undeclared-read |
| tenancy.tenant_database_manager | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.tenant_model | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  |  |
| tenancy.trial_days | axiomasoft/corex-tenancy | int | 14 | no | deployment | no | use-site | — |  |  |
| tenancy.workspace.path_segment | axiomasoft/corex-tenancy | string | w | no | deployment | no | use-site | — |  |  |
