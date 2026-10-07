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
        yield new ProblemDefinition(code: CoreErrorCode::SchemaInvalidSet, type: 'urn:corex:problem:core.schema_invalid_set', defaultStatus: 500, title: 'Invalid schema set.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::SchemaDuplicateSet, type: 'urn:corex:problem:core.schema_duplicate_set', defaultStatus: 500, title: 'Duplicate schema set.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::SchemaDuplicateMigration, type: 'urn:corex:problem:core.schema_duplicate_migration', defaultStatus: 500, title: 'Duplicate schema migration.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::SchemaUnknownSet, type: 'urn:corex:problem:core.schema_unknown_set', defaultStatus: 500, title: 'Unknown schema set.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::SchemaUnsupportedDriver, type: 'urn:corex:problem:core.schema_unsupported_driver', defaultStatus: 500, title: 'Unsupported schema driver.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);

        yield new ProblemDefinition(code: CoreErrorCode::CentralContextException, type: 'urn:corex:problem:core.central_context_exception', defaultStatus: 403, title: 'Central context exception.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::CompilationException, type: 'urn:corex:problem:core.compilation_exception', defaultStatus: 500, title: 'Compilation exception.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::CrossWorkspaceMoveException, type: 'urn:corex:problem:core.cross_workspace_move_exception', defaultStatus: 409, title: 'Cross workspace move exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::DepartmentCycleException, type: 'urn:corex:problem:core.department_cycle_exception', defaultStatus: 409, title: 'Department cycle exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::DuplicateCode, type: 'urn:corex:problem:core.duplicate_code', defaultStatus: 500, title: 'Duplicate code.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::DuplicateType, type: 'urn:corex:problem:core.duplicate_type', defaultStatus: 500, title: 'Duplicate type.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::EntityNotFound, type: 'urn:corex:problem:core.entity_not_found', defaultStatus: 404, title: 'Entity not found.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ForeignPrefix, type: 'urn:corex:problem:core.foreign_prefix', defaultStatus: 500, title: 'Foreign prefix.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::HasEnabledDependentsException, type: 'urn:corex:problem:core.has_enabled_dependents_exception', defaultStatus: 409, title: 'Has enabled dependents exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ImpersonationRestrictedException, type: 'urn:corex:problem:core.impersonation_restricted_exception', defaultStatus: 403, title: 'Impersonation restricted exception.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InboxRefused, type: 'urn:corex:problem:core.inbox_refused', defaultStatus: 409, title: 'Inbox refused.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InternalError, type: 'urn:corex:problem:core.internal_error', defaultStatus: 500, title: 'Internal error.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidConfiguration, type: 'urn:corex:problem:core.invalid_configuration', defaultStatus: 500, title: 'Invalid configuration.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidDefinition, type: 'urn:corex:problem:core.invalid_definition', defaultStatus: 500, title: 'Invalid definition.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidManifestException, type: 'urn:corex:problem:core.invalid_manifest_exception', defaultStatus: 422, title: 'Invalid manifest exception.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidMessage, type: 'urn:corex:problem:core.invalid_message', defaultStatus: 422, title: 'Invalid message.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidRegistryPoint, type: 'urn:corex:problem:core.invalid_registry_point', defaultStatus: 422, title: 'Invalid registry point.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidScopePayload, type: 'urn:corex:problem:core.invalid_scope_payload', defaultStatus: 422, title: 'Invalid scope payload.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidStateTransitionException, type: 'urn:corex:problem:core.invalid_state_transition_exception', defaultStatus: 409, title: 'Invalid state transition exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::InvalidStatus, type: 'urn:corex:problem:core.invalid_status', defaultStatus: 500, title: 'Invalid status.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::MissingDependencyException, type: 'urn:corex:problem:core.missing_dependency_exception', defaultStatus: 409, title: 'Missing dependency exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ModuleEnableFailedException, type: 'urn:corex:problem:core.module_enable_failed_exception', defaultStatus: 500, title: 'Module enable failed exception.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ModuleNotFound, type: 'urn:corex:problem:core.module_not_found', defaultStatus: 404, title: 'Module not found.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ModuleUpgradeFailedException, type: 'urn:corex:problem:core.module_upgrade_failed_exception', defaultStatus: 500, title: 'Module upgrade failed exception.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::OutboxLeaseLost, type: 'urn:corex:problem:core.outbox_lease_lost', defaultStatus: 409, title: 'Outbox lease lost.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::OutboxRefused, type: 'urn:corex:problem:core.outbox_refused', defaultStatus: 409, title: 'Outbox refused.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::RegistryFrozen, type: 'urn:corex:problem:core.registry_frozen', defaultStatus: 500, title: 'Registry frozen.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::RegistryInputUnavailable, type: 'urn:corex:problem:core.registry_input_unavailable', defaultStatus: 503, title: 'Registry input unavailable.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::ScopeContextMismatch, type: 'urn:corex:problem:core.scope_context_mismatch', defaultStatus: 403, title: 'Scope context mismatch.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::SettingValueTooLargeException, type: 'urn:corex:problem:core.setting_value_too_large_exception', defaultStatus: 422, title: 'Setting value too large exception.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::StaleRegistryRevision, type: 'urn:corex:problem:core.stale_registry_revision', defaultStatus: 409, title: 'Stale registry revision.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::StorageCapabilityUnavailable, type: 'urn:corex:problem:core.storage_capability_unavailable', defaultStatus: 503, title: 'Storage capability unavailable.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::StorageOperationFailed, type: 'urn:corex:problem:core.storage_operation_failed', defaultStatus: 503, title: 'Storage operation failed.', retryable: true, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::UnknownCode, type: 'urn:corex:problem:core.unknown_code', defaultStatus: 500, title: 'Unknown code.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::UnknownMessageContract, type: 'urn:corex:problem:core.unknown_message_contract', defaultStatus: 422, title: 'Unknown message contract.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: CoreErrorCode::UnsupportedScopeException, type: 'urn:corex:problem:core.unsupported_scope_exception', defaultStatus: 422, title: 'Unsupported scope exception.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
