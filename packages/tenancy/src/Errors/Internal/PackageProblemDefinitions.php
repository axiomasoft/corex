<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: TenancyErrorCode::CentralProtocolViolation, type: 'urn:corex:problem:tenancy.central_protocol_violation', defaultStatus: 500, title: 'Central protocol violation.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::CentralUnauthorized, type: 'urn:corex:problem:tenancy.central_unauthorized', defaultStatus: 403, title: 'Central unauthorized.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::CentralUnavailable, type: 'urn:corex:problem:tenancy.central_unavailable', defaultStatus: 503, title: 'Central unavailable.', retryable: true, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::ClaimConflict, type: 'urn:corex:problem:tenancy.claim_conflict', defaultStatus: 409, title: 'Claim conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::ClaimUnavailable, type: 'urn:corex:problem:tenancy.claim_unavailable', defaultStatus: 503, title: 'Claim unavailable.', retryable: true, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::ContextUnavailable, type: 'urn:corex:problem:tenancy.context_unavailable', defaultStatus: 503, title: 'Tenant context unavailable.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::CredentialIssuanceDenied, type: 'urn:corex:problem:tenancy.credential_issuance_denied', defaultStatus: 403, title: 'Credential issuance denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::DomainTrustDenied, type: 'urn:corex:problem:tenancy.domain_trust_denied', defaultStatus: 403, title: 'Domain trust denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::DomainTrustDisabled, type: 'urn:corex:problem:tenancy.domain_trust_disabled', defaultStatus: 503, title: 'Domain trust disabled.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::InboxConflict, type: 'urn:corex:problem:tenancy.inbox_conflict', defaultStatus: 409, title: 'Inbox conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::InvitationNotAcceptableException, type: 'urn:corex:problem:tenancy.invitation_not_acceptable_exception', defaultStatus: 409, title: 'Invitation not acceptable exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::NoDefaultWorkspaceException, type: 'urn:corex:problem:tenancy.no_default_workspace_exception', defaultStatus: 404, title: 'No default workspace exception.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::OidcAuthenticationException, type: 'urn:corex:problem:tenancy.oidc_authentication_exception', defaultStatus: 401, title: 'Oidc authentication exception.', retryable: false, category: ErrorCategory::Authentication, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::PairStoreViolation, type: 'urn:corex:problem:tenancy.pair_store_violation', defaultStatus: 500, title: 'Pair store violation.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::ProjectionDenied, type: 'urn:corex:problem:tenancy.projection_denied', defaultStatus: 403, title: 'Projection denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::RevokeUncertain, type: 'urn:corex:problem:tenancy.revoke_uncertain', defaultStatus: 503, title: 'Revoke uncertain.', retryable: true, category: ErrorCategory::Unavailable, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::TemplateIntegrityException, type: 'urn:corex:problem:tenancy.template_integrity_exception', defaultStatus: 500, title: 'Template integrity exception.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
        yield new ProblemDefinition(code: TenancyErrorCode::TenantCouldNotBeIdentifiedByHostException, type: 'urn:corex:problem:tenancy.tenant_could_not_be_identified_by_host_exception', defaultStatus: 404, title: 'Tenant could not be identified by host exception.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
    }
}
