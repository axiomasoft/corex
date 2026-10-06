<?php

declare(strict_types=1);

namespace CoreX\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum CoreErrorCode: string implements ErrorCode
{
    case CentralContextException = 'core.central_context_exception';
    case CompilationException = 'core.compilation_exception';
    case CrossWorkspaceMoveException = 'core.cross_workspace_move_exception';
    case DepartmentCycleException = 'core.department_cycle_exception';
    case DuplicateCode = 'core.duplicate_code';
    case DuplicateType = 'core.duplicate_type';
    case EntityNotFound = 'core.entity_not_found';
    case ForeignPrefix = 'core.foreign_prefix';
    case HasEnabledDependentsException = 'core.has_enabled_dependents_exception';
    case ImpersonationRestrictedException = 'core.impersonation_restricted_exception';
    case InboxRefused = 'core.inbox_refused';
    case InternalError = 'core.internal_error';
    case InvalidConfiguration = 'core.invalid_configuration';
    case InvalidDefinition = 'core.invalid_definition';
    case InvalidManifestException = 'core.invalid_manifest_exception';
    case InvalidMessage = 'core.invalid_message';
    case InvalidRegistryPoint = 'core.invalid_registry_point';
    case InvalidScopePayload = 'core.invalid_scope_payload';
    case InvalidStateTransitionException = 'core.invalid_state_transition_exception';
    case InvalidStatus = 'core.invalid_status';
    case MissingDependencyException = 'core.missing_dependency_exception';
    case ModuleEnableFailedException = 'core.module_enable_failed_exception';
    case ModuleNotFound = 'core.module_not_found';
    case ModuleUpgradeFailedException = 'core.module_upgrade_failed_exception';
    case OutboxLeaseLost = 'core.outbox_lease_lost';
    case OutboxRefused = 'core.outbox_refused';
    case RegistryFrozen = 'core.registry_frozen';
    case RegistryInputUnavailable = 'core.registry_input_unavailable';
    case ScopeContextMismatch = 'core.scope_context_mismatch';
    case SettingValueTooLargeException = 'core.setting_value_too_large_exception';
    case StaleRegistryRevision = 'core.stale_registry_revision';
    case StorageCapabilityUnavailable = 'core.storage_capability_unavailable';
    case StorageOperationFailed = 'core.storage_operation_failed';
    case UnknownCode = 'core.unknown_code';
    case UnknownMessageContract = 'core.unknown_message_contract';
    case UnsupportedScopeException = 'core.unsupported_scope_exception';

    case SchemaInvalidSet = 'core.schema_invalid_set';
    case SchemaDuplicateSet = 'core.schema_duplicate_set';
    case SchemaDuplicateMigration = 'core.schema_duplicate_migration';
    case SchemaUnknownSet = 'core.schema_unknown_set';
    case SchemaUnsupportedDriver = 'core.schema_unsupported_driver';

    public function value(): string
    {
        return $this->value;
    }
}
