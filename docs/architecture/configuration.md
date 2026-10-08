# Реестр конфигурации

Инварианты — статьи `CFG-001` … `CFG-006`. Процедура — `config-policy.md`.
Страница генерируется: `php scripts/configuration-reference.php --write`, сверка — `--check`
(composer `configuration-reference`, шаг CI). Набор ключей, тип и значение по умолчанию
читаются из `packages/*/config/*.php`; `typed_options_field` — свойство Options-класса,
которое принимает ключ; остальные колонки хранятся в `scripts/configuration-classes.json`.
Ключ пакетного config без поля Options — ошибка генератора.

## Каталог ключей

| key | owner | type | default | secret | mode | reloadable | validation | typed_options_field | deprecated_aliases | notes |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| auth.* | axiomasoft/corex-access | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| corex-access-azguard.azguard_pins.column_names.morph_type | axiomasoft/corex-access-azguard | string | uuid | no | product-policy | unobserved | boot | AccessAzguardOptions::$morphType |  |  |
| corex-access-azguard.azguard_pins.models.role | axiomasoft/corex-access-azguard | unobserved | unobserved | no | product-policy | unobserved | boot | AccessAzguardOptions::$roleModel |  |  |
| corex-access-azguard.azguard_pins.table_names.roles | axiomasoft/corex-access-azguard | string | az_guard_roles | no | product-policy | unobserved | boot | AccessAzguardOptions::$rolesTable |  |  |
| corex-access-azguard.bind_permission_layer | axiomasoft/corex-access-azguard | unobserved | unobserved | no | deployment | no | boot | AccessAzguardOptions::$bindPermissionLayer |  |  |
| corex-access.cache.store | axiomasoft/corex-access | unobserved | unobserved | no | deployment | no | use-site | AuthOptions::$cacheStore |  |  |
| corex-access.identity.columns.active | axiomasoft/corex-access | string | is_active | no | product-policy | unobserved | unobserved | AuthOptions::$identityColumns |  |  |
| corex-access.identity.columns.deleted_at | axiomasoft/corex-access | string | deleted_at | no | product-policy | unobserved | unobserved | AuthOptions::$identityColumns |  |  |
| corex-access.identity.columns.email | axiomasoft/corex-access | string | email | no | product-policy | unobserved | unobserved | AuthOptions::$identityColumns |  |  |
| corex-access.identity.columns.id | axiomasoft/corex-access | string | id | no | product-policy | unobserved | unobserved | AuthOptions::$identityColumns |  |  |
| corex-access.identity.columns.password | axiomasoft/corex-access | string | *** | yes | secret | unobserved | unobserved | AuthOptions::$identityColumns |  |  |
| corex-access.identity.connection | axiomasoft/corex-access | unobserved | unobserved | no | deployment | no | use-site | AuthOptions::$identityConnection |  |  |
| corex-access.identity.enabled | axiomasoft/corex-access | unobserved | unobserved | no | deployment | no | use-site | AuthOptions::$identityEnabled |  |  |
| corex-access.identity.table | axiomasoft/corex-access | unobserved | unobserved | no | deployment | no | use-site | AuthOptions::$identityTable |  |  |
| corex-access.panel | axiomasoft/corex-access | unobserved | unobserved | no | deployment | no | use-site | AuthOptions::$panel |  |  |
| corex-access.workspace_scope_class | axiomasoft/corex-access | null | null | no | product-policy | unobserved | boot | AuthOptions::$workspaceScopeClass |  |  |
| corex-custom-fields.definitions | axiomasoft/corex-custom-fields | array | [] | no | product-policy | unobserved | unobserved | CustomFieldsOptions::$composition |  |  |
| corex-custom-fields.drivers.ddl | axiomasoft/corex-custom-fields | bool | true | no | product-policy | unobserved | unobserved | CustomFieldsOptions::$composition |  |  |
| corex-custom-fields.drivers.eav | axiomasoft/corex-custom-fields | bool | true | no | product-policy | unobserved | unobserved | CustomFieldsOptions::$composition |  |  |
| corex-custom-fields.drivers.jsonb | axiomasoft/corex-custom-fields | bool | true | no | product-policy | unobserved | unobserved | CustomFieldsOptions::$composition |  |  |
| corex-custom-fields.sources | axiomasoft/corex-custom-fields | array | unobserved | no | product-policy | unobserved | unobserved | CustomFieldsOptions::$composition |  |  |
| corex-custom-fields.storage_by_entity | axiomasoft/corex-custom-fields | array | [] | no | product-policy | unobserved | unobserved | CustomFieldsOptions::$composition |  |  |
| corex-custom-fields.storage_driver | axiomasoft/corex-custom-fields | string | jsonb | no | product-policy | unobserved | unobserved | CustomFieldsOptions::$composition |  |  |
| corex-intl.formatting.icu_enabled | axiomasoft/corex-intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | IntlOptions::$formattingIcuEnabled |  |  |
| corex-intl.locale.allow_parent | axiomasoft/corex-intl | bool | true | no | product-policy | unobserved | unobserved | IntlOptions::$localeAllowParent |  |  |
| corex-intl.locale.app_tag | axiomasoft/corex-intl | string | en | no | product-policy | unobserved | unobserved | IntlOptions::$localeAppTag |  |  |
| corex-intl.locale.explicit_fallback_tags | axiomasoft/corex-intl | array | unobserved | no | product-policy | unobserved | unobserved | IntlOptions::$localeExplicitFallbackTags |  |  |
| corex-intl.locale.max_supported_tags | axiomasoft/corex-intl | int | 100 | no | product-policy | unobserved | unobserved | IntlOptions::$localeMaxSupportedTags |  |  |
| corex-intl.locale.max_tag_length | axiomasoft/corex-intl | int | 128 | no | product-policy | unobserved | unobserved | IntlOptions::$localeMaxTagLength |  |  |
| corex-intl.locale.settings_key | axiomasoft/corex-intl | string | *** | yes | secret | unobserved | unobserved | IntlOptions::$localeSettingsKey |  |  |
| corex-intl.locale.settings_namespace | axiomasoft/corex-intl | string | intl | no | product-policy | unobserved | unobserved | IntlOptions::$localeSettingsNamespace |  |  |
| corex-intl.locale.supported_tags | axiomasoft/corex-intl | array | unobserved | no | product-policy | unobserved | unobserved | IntlOptions::$localeSupportedTags |  |  |
| corex-intl.money.batch_limit | axiomasoft/corex-intl | int | 1000 | no | product-policy | unobserved | unobserved | IntlOptions::$moneyBatchLimit |  |  |
| corex-intl.money.default_source | axiomasoft/corex-intl | string | manual | no | product-policy | unobserved | unobserved | IntlOptions::$moneyDefaultSource |  |  |
| corex-intl.money.metadata_version | axiomasoft/corex-intl | string | brick/1 | no | product-policy | unobserved | unobserved | IntlOptions::$moneyMetadataVersion |  |  |
| corex-intl.reference.cursor_secret | axiomasoft/corex-intl | unobserved | unobserved | yes | secret | no | unobserved | IntlOptions::$referenceCursorSecret |  |  |
| corex-intl.reference.dataset_key | axiomasoft/corex-intl | string | *** | yes | secret | unobserved | unobserved | IntlOptions::$referenceDatasetKey |  |  |
| corex-intl.reference.providers.boxes.fixture | axiomasoft/corex-intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | IntlOptions::$referenceProviders |  |  |
| corex-intl.reference.providers.compact.fixture | axiomasoft/corex-intl | unobserved | unobserved | no | product-policy | unobserved | unobserved | IntlOptions::$referenceProviders |  |  |
| corex-media.allowed_mime_types | axiomasoft/corex-media | array | unobserved | no | product-policy | unobserved | use-site | MediaOptions::$allowedMimeTypes |  | undeclared-read |
| corex-media.conversion.thumbnail.max_height | axiomasoft/corex-media | int | 4096 | no | product-policy | unobserved | unobserved | MediaOptions::$conversion |  |  |
| corex-media.conversion.thumbnail.max_output_bytes | axiomasoft/corex-media | unobserved | unobserved | no | product-policy | unobserved | unobserved | MediaOptions::$conversion |  |  |
| corex-media.conversion.thumbnail.max_pixels | axiomasoft/corex-media | int | 20000000 | no | product-policy | unobserved | unobserved | MediaOptions::$conversion |  |  |
| corex-media.conversion.thumbnail.max_width | axiomasoft/corex-media | int | 4096 | no | product-policy | unobserved | unobserved | MediaOptions::$conversion |  |  |
| corex-media.conversion_driver_tag | axiomasoft/corex-media | string | corex.media.conversion-drivers | no | product-policy | unobserved | unobserved | MediaOptions::$conversionDriverTag |  |  |
| corex-media.max_image_pixels | axiomasoft/corex-media | int | 20000000 | no | product-policy | unobserved | use-site | MediaOptions::$maxImagePixels |  |  |
| corex-media.max_upload_bytes | axiomasoft/corex-media | unobserved | unobserved | no | product-policy | unobserved | use-site | MediaOptions::$maxUploadBytes |  |  |
| corex-media.purposes | axiomasoft/corex-media | array | unobserved | no | product-policy | unobserved | use-site | MediaOptions::$purposes |  | undeclared-read |
| corex-notifications.delivery.claim_seconds | axiomasoft/corex-notifications | int | 60 | no | product-policy | unobserved | unobserved | NotificationsOptions::$deliveryClaimSeconds |  |  |
| corex-notifications.delivery.max_attempts | axiomasoft/corex-notifications | int | 5 | no | product-policy | unobserved | unobserved | NotificationsOptions::$deliveryMaxAttempts |  |  |
| corex-notifications.digest.accept_legacy_unstamped | axiomasoft/corex-notifications | bool | false | no | product-policy | unobserved | unobserved | NotificationsOptions::$digestAcceptLegacyUnstamped |  |  |
| corex-notifications.digest.enabled | axiomasoft/corex-notifications | bool | false | no | product-policy | unobserved | unobserved | NotificationsOptions::$digestEnabled |  |  |
| corex-operations.build | axiomasoft/corex-operations | string | dev | no | product-policy | unobserved | unobserved | ApiOptions::$build |  |  |
| corex-operations.bulk.enabled | axiomasoft/corex-operations | bool | true | no | product-policy | unobserved | unobserved | BulkOptions::$enabled |  |  |
| corex-operations.bulk.idempotency_retention_seconds | axiomasoft/corex-operations | int | 86400 | no | product-policy | unobserved | unobserved | BulkOptions::$idempotencyRetentionSeconds |  |  |
| corex-operations.bulk.lease_seconds | axiomasoft/corex-operations | int | 30 | no | product-policy | unobserved | unobserved | BulkOptions::$leaseSeconds |  |  |
| corex-operations.bulk.metadata_retention_seconds | axiomasoft/corex-operations | int | 2592000 | no | product-policy | unobserved | unobserved | BulkOptions::$metadataRetentionSeconds |  |  |
| corex-operations.bulk.result_retention_seconds | axiomasoft/corex-operations | int | 86400 | no | product-policy | unobserved | unobserved | BulkOptions::$resultRetentionSeconds |  |  |
| corex-operations.bulk.retry_seconds | axiomasoft/corex-operations | int | 5 | no | product-policy | unobserved | unobserved | BulkOptions::$retrySeconds |  |  |
| corex-operations.bulk.worker | axiomasoft/corex-operations | string | api-bulk | no | deployment | no | unobserved | BulkOptions::$worker |  |  |
| corex-operations.capabilities | axiomasoft/corex-operations | array | [] | no | product-policy | unobserved | unobserved | ApiOptions::$capabilities |  |  |
| corex-operations.cost.capacity | axiomasoft/corex-operations | int | 10000 | no | product-policy | unobserved | unobserved | CostOptions::$capacity |  |  |
| corex-operations.cost.enabled | axiomasoft/corex-operations | bool | false | no | product-policy | unobserved | unobserved | CostOptions::$enabled |  |  |
| corex-operations.cost.enforced_levels | axiomasoft/corex-operations | array | unobserved | no | product-policy | unobserved | unobserved | CostOptions::$enforcedLevels |  |  |
| corex-operations.cost.installation_id | axiomasoft/corex-operations | string | corex | no | product-policy | unobserved | unobserved | CostOptions::$installationId |  |  |
| corex-operations.cost.reservation_ttl_seconds | axiomasoft/corex-operations | int | 3600 | no | product-policy | unobserved | unobserved | CostOptions::$reservationTtlSeconds |  |  |
| corex-operations.cost.restore_rate | axiomasoft/corex-operations | int | 1000 | no | product-policy | unobserved | unobserved | CostOptions::$restoreRate |  |  |
| corex-operations.cost.scope_hmac_secret | axiomasoft/corex-operations | string | '' | yes | secret | unobserved | unobserved | CostOptions::$scopeHmacSecret |  |  |
| corex-operations.cost.terminal_retention_seconds | axiomasoft/corex-operations | int | 2592000 | no | product-policy | unobserved | unobserved | CostOptions::$terminalRetentionSeconds |  |  |
| corex-operations.cursor.current_key_id | axiomasoft/corex-operations | string | primary | no | product-policy | unobserved | unobserved | ApiOptions::$cursorKeyId |  |  |
| corex-operations.cursor.keyring.primary | axiomasoft/corex-operations | string | '' | yes | secret | unobserved | unobserved | ApiOptions::$cursorKeyring |  |  |
| corex-operations.cursor.ttl_seconds | axiomasoft/corex-operations | int | 3600 | no | product-policy | unobserved | unobserved | ApiOptions::$cursorTtlSeconds |  |  |
| corex-operations.default_major | axiomasoft/corex-operations | string | v1 | no | product-policy | unobserved | unobserved | ApiOptions::$defaultMajor |  |  |
| corex-operations.discovery | axiomasoft/corex-operations | string | public | no | product-policy | unobserved | unobserved | ApiOptions::$discovery |  |  |
| corex-operations.idempotency.lease_seconds | axiomasoft/corex-operations | int | 30 | no | product-policy | unobserved | unobserved | ApiOptions::$idempotencyLeaseSeconds |  |  |
| corex-operations.idempotency.retention_seconds | axiomasoft/corex-operations | int | 86400 | no | product-policy | unobserved | unobserved | ApiOptions::$idempotencyRetentionSeconds |  |  |
| corex-operations.product | axiomasoft/corex-operations | string | corex | no | product-policy | unobserved | unobserved | ApiOptions::$product |  |  |
| corex-operations.require_physical_tenancy | axiomasoft/corex-operations | bool | true | no | product-policy | unobserved | unobserved | ApiOptions::$requirePhysicalTenancy |  |  |
| corex-operations.routes.enabled | axiomasoft/corex-operations | bool | true | no | deployment | no | unobserved | ApiOptions::$routesEnabled |  |  |
| corex-operations.routes.middleware | axiomasoft/corex-operations | array | [] | no | deployment | no | unobserved | ApiOptions::$routeMiddleware |  |  |
| corex-operations.routes.prefix | axiomasoft/corex-operations | string | api | no | deployment | no | unobserved | ApiOptions::$routePrefix |  |  |
| corex-operations.versions | axiomasoft/corex-operations | array | unobserved | no | product-policy | unobserved | unobserved | ApiOptions::$versions |  |  |
| corex-panels.provider_tag | axiomasoft/corex-panels | string | corex.panels.providers | no | product-policy | unobserved | unobserved | PanelsOptions::$providerTag |  |  |
| corex-search-intl.enabled | axiomasoft/corex-search-intl | unobserved | unobserved | no | deployment | no | unobserved | SearchIntlOptions::$enabled |  |  |
| corex-search-meilisearch.host | axiomasoft/corex-search-meilisearch | null | null | no | product-policy | unobserved | unobserved | MeilisearchOptions::$host |  |  |
| corex-search-meilisearch.index_uid | axiomasoft/corex-search-meilisearch | null | null | no | product-policy | unobserved | unobserved | MeilisearchOptions::$indexUid |  |  |
| corex-search-meilisearch.key | axiomasoft/corex-search-meilisearch | null | null | yes | secret | unobserved | unobserved | MeilisearchOptions::$key |  |  |
| corex-search.cursor_secret | axiomasoft/corex-search | null | null | yes | secret | unobserved | unobserved | SearchOptions::$cursorSecret |  |  |
| corex-search.driver | axiomasoft/corex-search | string | null | no | product-policy | unobserved | unobserved | SearchOptions::$driver |  |  |
| corex-search.postgres.scan_budget | axiomasoft/corex-search | int | 1000 | no | product-policy | unobserved | unobserved | SearchOptions::$scanBudget |  |  |
| corex-search.postgres.text_search_config | axiomasoft/corex-search | string | simple | no | product-policy | unobserved | unobserved | SearchOptions::$textSearchConfig |  |  |
| corex-tasks-workflow.completion_capture.definition_ref | axiomasoft/corex-tasks-workflow | string | task-completion/1 | no | product-policy | unobserved | unobserved | TasksWorkflowOptions::$completionCaptureDefinitionRef |  |  |
| corex-tasks-workflow.completion_capture.enabled | axiomasoft/corex-tasks-workflow | bool | false | no | product-policy | unobserved | unobserved | TasksWorkflowOptions::$completionCaptureEnabled |  |  |
| corex-tasks-workflow.completion_capture.version_ref | axiomasoft/corex-tasks-workflow | string | task-completion/1 | no | product-policy | unobserved | unobserved | TasksWorkflowOptions::$completionCaptureVersionRef |  |  |
| corex-tasks-workflow.wakeup.accept_legacy_unstamped | axiomasoft/corex-tasks-workflow | bool | false | no | product-policy | unobserved | unobserved | TasksWorkflowOptions::$wakeupAcceptLegacyUnstamped |  |  |
| corex-tasks-workflow.wakeup.enabled | axiomasoft/corex-tasks-workflow | bool | false | no | product-policy | unobserved | unobserved | TasksWorkflowOptions::$wakeupEnabled |  |  |
| corex-tasks-workflow.wakeup.handler | axiomasoft/corex-tasks-workflow | string | CoreX\TasksWorkflow\Internal\MaterializationWakeupHandler | no | product-policy | unobserved | unobserved | TasksWorkflowOptions::$wakeupHandler |  |  |
| corex-tasks-workflow.wakeup.handler_kind | axiomasoft/corex-tasks-workflow | string | task-recurrence.materialize | no | product-policy | unobserved | unobserved | TasksWorkflowOptions::$wakeupHandlerKind |  |  |
| corex-tasks-workflow.wakeup.handler_version | axiomasoft/corex-tasks-workflow | string | task-materialization/1 | no | product-policy | unobserved | unobserved | TasksWorkflowOptions::$wakeupHandlerVersion |  |  |
| corex-tenancy.bootstrappers | axiomasoft/corex-tenancy | array | unobserved | no | product-policy | unobserved | use-site | TenancyOptions::$bootstrappers |  |  |
| corex-tenancy.cache_prefix | axiomasoft/corex-tenancy | string | tenant_%tenant%_ | no | product-policy | unobserved | use-site | TenancyOptions::$cachePrefix |  |  |
| corex-tenancy.cache_stores | axiomasoft/corex-tenancy | null | null | no | product-policy | unobserved | use-site | TenancyOptions::$cacheStores |  |  |
| corex-tenancy.central_connection | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$centralConnection |  |  |
| corex-tenancy.domain_trust.challenge_ttl_seconds | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$domainTrustChallengeTtlSeconds |  |  |
| corex-tenancy.domain_trust.enabled | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$domainTrustEnabled |  |  |
| corex-tenancy.grace_days | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$lifecycle |  |  |
| corex-tenancy.host_identification.cache | axiomasoft/corex-tenancy | bool | true | no | product-policy | unobserved | use-site | TenancyOptions::$hostCache |  |  |
| corex-tenancy.host_identification.cache_store | axiomasoft/corex-tenancy | null | null | no | product-policy | unobserved | use-site | TenancyOptions::$hostCacheStore |  |  |
| corex-tenancy.host_identification.cache_ttl | axiomasoft/corex-tenancy | int | 300 | no | product-policy | unobserved | use-site | TenancyOptions::$hostCacheTtl |  |  |
| corex-tenancy.host_identification.enabled | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$hostIdentificationEnabled |  |  |
| corex-tenancy.impersonation.profile | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$impersonation |  |  |
| corex-tenancy.membership_guard.guard | axiomasoft/corex-tenancy | unobserved | unobserved | no | product-policy | unobserved | use-site | TenancyOptions::$membershipGuard |  | undeclared-read |
| corex-tenancy.oidc.allowed_algs | axiomasoft/corex-tenancy | array | unobserved | no | product-policy | unobserved | use-site | TenancyOptions::$oidcAllowedAlgs |  | undeclared-read |
| corex-tenancy.oidc.client_id | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcClientId |  |  |
| corex-tenancy.oidc.client_secret | axiomasoft/corex-tenancy | unobserved | unobserved | yes | secret | no | unobserved | TenancyOptions::$oidcClientSecret |  |  |
| corex-tenancy.oidc.discovery.cache_store | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | unobserved | TenancyOptions::$oidcDiscoveryCacheStore |  |  |
| corex-tenancy.oidc.discovery.cache_ttl | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcDiscoveryCacheTtl |  |  |
| corex-tenancy.oidc.enabled | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcEnabled |  |  |
| corex-tenancy.oidc.guard | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcGuard |  |  |
| corex-tenancy.oidc.impersonation.enabled | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$impersonation |  |  |
| corex-tenancy.oidc.issuer | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcIssuer |  |  |
| corex-tenancy.oidc.jwks.cache_store | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | unobserved | TenancyOptions::$oidcJwksCacheStore |  |  |
| corex-tenancy.oidc.jwks.cache_ttl | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcJwksCacheTtl |  |  |
| corex-tenancy.oidc.jwks.refetch_cooldown_seconds | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcJwksRefetchCooldownSeconds |  |  |
| corex-tenancy.oidc.leeway_seconds | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcLeewaySeconds |  |  |
| corex-tenancy.oidc.max_session_lifetime_minutes | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcMaxSessionLifetimeMinutes |  |  |
| corex-tenancy.oidc.redirect_uri | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$oidcRedirectUri |  |  |
| corex-tenancy.platform_domain | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$lifecycle |  |  |
| corex-tenancy.status_gate.export_route | axiomasoft/corex-tenancy | null | null | no | product-policy | unobserved | use-site | TenancyOptions::$statusGate |  |  |
| corex-tenancy.status_gate.payment_route | axiomasoft/corex-tenancy | null | null | no | product-policy | unobserved | unobserved | TenancyOptions::$statusGate |  |  |
| corex-tenancy.status_gate.read_only_methods | axiomasoft/corex-tenancy | array | unobserved | no | product-policy | unobserved | use-site | TenancyOptions::$statusGate |  | undeclared-read |
| corex-tenancy.storage.durable_driver | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$storageDurableDriver |  |  |
| corex-tenancy.storage.durable_root | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$storageDurableRoot |  |  |
| corex-tenancy.storage.ephemeral_root | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$storageEphemeralRoot |  |  |
| corex-tenancy.template_integrity.manifests | axiomasoft/corex-tenancy | array | [] | no | product-policy | unobserved | use-site | TenancyOptions::$templateIntegrityManifests |  | undeclared-read |
| corex-tenancy.template_integrity.trusted_keys | axiomasoft/corex-tenancy | array | *** | yes | secret | unobserved | use-site | TenancyOptions::$templateIntegrityTrustedKeys |  | undeclared-read |
| corex-tenancy.trial_days | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$lifecycle |  |  |
| corex-tenancy.wakeup.driver | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$wakeupDriver |  |  |
| corex-tenancy.wakeup.lease_seconds | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$wakeupLeaseSeconds |  |  |
| corex-tenancy.wakeup.root_connection | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$wakeupRootConnection |  |  |
| corex-tenancy.workspace.path_segment | axiomasoft/corex-tenancy | unobserved | unobserved | no | deployment | no | use-site | TenancyOptions::$workspacePathSegment |  |  |
| corex-transfer.limits.batch_rows | axiomasoft/corex-transfer | int | 500 | no | product-policy | unobserved | unobserved | TransferOptions::$limitsBatchRows |  |  |
| corex-transfer.limits.max_fields | axiomasoft/corex-transfer | int | 256 | no | product-policy | unobserved | unobserved | TransferOptions::$limitsMaxFields |  |  |
| corex-transfer.limits.max_record_bytes | axiomasoft/corex-transfer | int | 1048576 | no | product-policy | unobserved | unobserved | TransferOptions::$limitsMaxRecordBytes |  |  |
| corex-wakeup.handlers | axiomasoft/corex-wakeup | array | [] | no | product-policy | unobserved | unobserved | WakeupOptions::$handlers |  |  |
| corex-wakeup.lease_seconds | axiomasoft/corex-wakeup | int | 60 | no | product-policy | unobserved | unobserved | WakeupOptions::$leaseSeconds |  |  |
| corex-wakeup.max_attempts | axiomasoft/corex-wakeup | int | 5 | no | product-policy | unobserved | unobserved | WakeupOptions::$maxAttempts |  |  |
| corex-webhooks.body_retention_seconds | axiomasoft/corex-webhooks | int | 604800 | no | product-policy | unobserved | unobserved | WebhookOptions::$bodyRetentionSeconds |  |  |
| corex-webhooks.delivery_enabled | axiomasoft/corex-webhooks | bool | false | no | product-policy | unobserved | unobserved | WebhookOptions::$deliveryEnabled |  |  |
| corex-webhooks.history_retention_seconds | axiomasoft/corex-webhooks | int | 2592000 | no | product-policy | unobserved | unobserved | WebhookOptions::$historyRetentionSeconds |  |  |
| corex-webhooks.lease_seconds | axiomasoft/corex-webhooks | int | 30 | no | product-policy | unobserved | unobserved | WebhookOptions::$leaseSeconds |  |  |
| corex-webhooks.max_attempts | axiomasoft/corex-webhooks | int | 12 | no | product-policy | unobserved | boot | WebhookOptions::$maxAttempts |  |  |
| corex-webhooks.organization_denied_cidrs | axiomasoft/corex-webhooks | array | [] | no | product-policy | unobserved | unobserved | WebhookOptions::$organizationDeniedCidrs |  |  |
| corex-webhooks.projectors | axiomasoft/corex-webhooks | array | [] | no | product-policy | unobserved | unobserved | WebhookOptions::$projectors |  |  |
| corex-webhooks.replay_horizon_seconds | axiomasoft/corex-webhooks | int | 604800 | no | product-policy | unobserved | unobserved | WebhookOptions::$replayHorizonSeconds |  |  |
| corex-webhooks.retry_horizon_seconds | axiomasoft/corex-webhooks | int | 259200 | no | product-policy | unobserved | boot | WebhookOptions::$retryHorizonSeconds |  |  |
| corex-webhooks.topics | axiomasoft/corex-webhooks | array | [] | no | product-policy | unobserved | unobserved | WebhookOptions::$topics |  |  |
| corex-webhooks.worker_ready | axiomasoft/corex-webhooks | bool | false | no | deployment | no | unobserved | WebhookOptions::$workerReady |  |  |
| corex-workflow.enforce_declarations | axiomasoft/corex-workflow | bool | true | no | product-policy | unobserved | unobserved | WorkflowOptions::$enforceDeclarations |  |  |
| corex.acknowledged_dangerous_modes | axiomasoft/corex | array | [] | no | product-policy | unobserved | unobserved | CoreOptions::$acknowledgedDangerousModes |  |  |
| corex.audit.enabled | axiomasoft/corex | bool | true | no | product-policy | unobserved | use-site | CoreOptions::$auditEnabled |  |  |
| corex.bindings.audit_logger | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$auditLogger |  |  |
| corex.bindings.audit_retention | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$auditRetention |  |  |
| corex.bindings.data_scope_authorizer | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$dataScopeAuthorizer |  |  |
| corex.bindings.departments | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$departmentRepository |  |  |
| corex.bindings.feature_flags | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$featureFlags |  |  |
| corex.bindings.setting_defaults | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$settingDefaultsProvider |  |  |
| corex.bindings.settings | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$settingsRepository |  |  |
| corex.messaging.backoff_cap_seconds | axiomasoft/corex | int | 300 | no | product-policy | unobserved | unobserved | CoreOptions::$messagingBackoffCapSeconds |  |  |
| corex.messaging.batch_size | axiomasoft/corex | int | 100 | no | product-policy | unobserved | unobserved | CoreOptions::$messagingBatchSize |  |  |
| corex.messaging.dead_retention | axiomasoft/corex | string | 90 days | no | product-policy | unobserved | unobserved | CoreOptions::$messagingDeadRetention |  |  |
| corex.messaging.inbox_retention | axiomasoft/corex | null | null | no | product-policy | unobserved | unobserved | CoreOptions::$messagingInboxRetention |  |  |
| corex.messaging.lease_seconds | axiomasoft/corex | int | 120 | no | product-policy | unobserved | unobserved | CoreOptions::$messagingLeaseSeconds |  |  |
| corex.messaging.max_attempts | axiomasoft/corex | int | 8 | no | product-policy | unobserved | unobserved | CoreOptions::$messagingMaxAttempts |  |  |
| corex.messaging.outbox_retention | axiomasoft/corex | string | 14 days | no | product-policy | unobserved | unobserved | CoreOptions::$messagingOutboxRetention |  |  |
| corex.modules.cache.prefix | axiomasoft/corex | unobserved | unobserved | no | deployment | no | use-site | ModulesOptions::$cachePrefix |  |  |
| corex.modules.cache.store | axiomasoft/corex | unobserved | unobserved | no | deployment | no | use-site | ModulesOptions::$cacheStore |  |  |
| corex.modules.cache.ttl | axiomasoft/corex | unobserved | unobserved | no | deployment | no | use-site | ModulesOptions::$cacheTtl |  |  |
| corex.modules.connection | axiomasoft/corex | unobserved | unobserved | no | deployment | no | use-site | ModulesOptions::$connection |  |  |
| corex.pubsub.driver | axiomasoft/corex | const | PubSubDriver::Null | no | product-policy | unobserved | use-site | CoreOptions::$pubsubDriver |  |  |
| corex.pubsub.octane_worker_opt_in | axiomasoft/corex | bool | false | no | product-policy | unobserved | use-site | CoreOptions::$pubsubOctaneWorkerOptIn |  |  |
| corex.pubsub.poll_interval_microseconds | axiomasoft/corex | int | 50000 | no | product-policy | unobserved | use-site | CoreOptions::$pubsubPollIntervalMicroseconds |  |  |
| corex.pubsub.retention | axiomasoft/corex | string | 30 days | no | product-policy | unobserved | use-site | CoreOptions::$pubsubRetention |  |  |
| corex.settings.cache_prefix | axiomasoft/corex | string | corex.settings | no | product-policy | unobserved | use-site | CoreOptions::$settingsCachePrefix |  |  |
| corex.settings.cache_store | axiomasoft/corex | string | array | no | product-policy | unobserved | use-site | CoreOptions::$settingsCacheStore |  |  |
| corex.settings.cache_ttl | axiomasoft/corex | int | 3600 | no | product-policy | unobserved | use-site | CoreOptions::$settingsCacheTtl |  |  |
| corex.storage.durable_root | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$storageDurableRoot |  |  |
| corex.storage.ephemeral_root | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$storageEphemeralRoot |  |  |
| corex.tenant_context.impersonation | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$impersonationService |  |  |
| corex.tenant_context.manager | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$tenancyManager |  |  |
| corex.tenant_context.resolver | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | CoreOptions::$tenantContextResolver |  |  |
| corex.views.connection | axiomasoft/corex | null | null | no | product-policy | unobserved | use-site | CoreOptions::$viewsConnection |  |  |
| database.default | axiomasoft/corex | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
| flex-crm.search_language | flex/crm | unobserved | unobserved | no | product-policy | unobserved | use-site | — |  | undeclared-read |
