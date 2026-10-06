<?php

declare(strict_types=1);

namespace CoreX\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(CoreErrorCode::SchemaInvalidSet, 'urn:corex:problem:core.schema_invalid_set', 500, 'Invalid schema set.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::SchemaDuplicateSet, 'urn:corex:problem:core.schema_duplicate_set', 500, 'Duplicate schema set.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::SchemaDuplicateMigration, 'urn:corex:problem:core.schema_duplicate_migration', 500, 'Duplicate schema migration.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::SchemaUnknownSet, 'urn:corex:problem:core.schema_unknown_set', 500, 'Unknown schema set.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::SchemaUnsupportedDriver, 'urn:corex:problem:core.schema_unsupported_driver', 500, 'Unsupported schema driver.', false, ErrorCategory::Internal, []);

        yield new ProblemDefinition(CoreErrorCode::CentralContextException, 'urn:corex:problem:core.central_context_exception', 403, 'Central context exception.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(CoreErrorCode::CompilationException, 'urn:corex:problem:core.compilation_exception', 500, 'Compilation exception.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::CrossWorkspaceMoveException, 'urn:corex:problem:core.cross_workspace_move_exception', 409, 'Cross workspace move exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::DepartmentCycleException, 'urn:corex:problem:core.department_cycle_exception', 409, 'Department cycle exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::DuplicateCode, 'urn:corex:problem:core.duplicate_code', 500, 'Duplicate code.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::DuplicateType, 'urn:corex:problem:core.duplicate_type', 500, 'Duplicate type.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::EntityNotFound, 'urn:corex:problem:core.entity_not_found', 404, 'Entity not found.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(CoreErrorCode::ForeignPrefix, 'urn:corex:problem:core.foreign_prefix', 500, 'Foreign prefix.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::HasEnabledDependentsException, 'urn:corex:problem:core.has_enabled_dependents_exception', 409, 'Has enabled dependents exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::ImpersonationRestrictedException, 'urn:corex:problem:core.impersonation_restricted_exception', 403, 'Impersonation restricted exception.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(CoreErrorCode::InboxRefused, 'urn:corex:problem:core.inbox_refused', 409, 'Inbox refused.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::InternalError, 'urn:corex:problem:core.internal_error', 500, 'Internal error.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::InvalidConfiguration, 'urn:corex:problem:core.invalid_configuration', 500, 'Invalid configuration.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::InvalidDefinition, 'urn:corex:problem:core.invalid_definition', 500, 'Invalid definition.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::InvalidManifestException, 'urn:corex:problem:core.invalid_manifest_exception', 422, 'Invalid manifest exception.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(CoreErrorCode::InvalidMessage, 'urn:corex:problem:core.invalid_message', 422, 'Invalid message.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(CoreErrorCode::InvalidRegistryPoint, 'urn:corex:problem:core.invalid_registry_point', 422, 'Invalid registry point.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(CoreErrorCode::InvalidScopePayload, 'urn:corex:problem:core.invalid_scope_payload', 422, 'Invalid scope payload.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(CoreErrorCode::InvalidStateTransitionException, 'urn:corex:problem:core.invalid_state_transition_exception', 409, 'Invalid state transition exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::InvalidStatus, 'urn:corex:problem:core.invalid_status', 500, 'Invalid status.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::MissingDependencyException, 'urn:corex:problem:core.missing_dependency_exception', 409, 'Missing dependency exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::ModuleEnableFailedException, 'urn:corex:problem:core.module_enable_failed_exception', 500, 'Module enable failed exception.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::ModuleNotFound, 'urn:corex:problem:core.module_not_found', 404, 'Module not found.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(CoreErrorCode::ModuleUpgradeFailedException, 'urn:corex:problem:core.module_upgrade_failed_exception', 500, 'Module upgrade failed exception.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::OutboxLeaseLost, 'urn:corex:problem:core.outbox_lease_lost', 409, 'Outbox lease lost.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::OutboxRefused, 'urn:corex:problem:core.outbox_refused', 409, 'Outbox refused.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::RegistryFrozen, 'urn:corex:problem:core.registry_frozen', 500, 'Registry frozen.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::RegistryInputUnavailable, 'urn:corex:problem:core.registry_input_unavailable', 503, 'Registry input unavailable.', false, ErrorCategory::Unavailable, []);
        yield new ProblemDefinition(CoreErrorCode::ScopeContextMismatch, 'urn:corex:problem:core.scope_context_mismatch', 403, 'Scope context mismatch.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(CoreErrorCode::SettingValueTooLargeException, 'urn:corex:problem:core.setting_value_too_large_exception', 422, 'Setting value too large exception.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(CoreErrorCode::StaleRegistryRevision, 'urn:corex:problem:core.stale_registry_revision', 409, 'Stale registry revision.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(CoreErrorCode::StorageCapabilityUnavailable, 'urn:corex:problem:core.storage_capability_unavailable', 503, 'Storage capability unavailable.', false, ErrorCategory::Unavailable, []);
        yield new ProblemDefinition(CoreErrorCode::StorageOperationFailed, 'urn:corex:problem:core.storage_operation_failed', 503, 'Storage operation failed.', true, ErrorCategory::Unavailable, []);
        yield new ProblemDefinition(CoreErrorCode::UnknownCode, 'urn:corex:problem:core.unknown_code', 500, 'Unknown code.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(CoreErrorCode::UnknownMessageContract, 'urn:corex:problem:core.unknown_message_contract', 422, 'Unknown message contract.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(CoreErrorCode::UnsupportedScopeException, 'urn:corex:problem:core.unsupported_scope_exception', 422, 'Unsupported scope exception.', false, ErrorCategory::Unsupported, []);
    }
}
