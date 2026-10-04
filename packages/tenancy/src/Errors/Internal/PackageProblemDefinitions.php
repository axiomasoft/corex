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
        yield new ProblemDefinition(TenancyErrorCode::CentralProtocolViolation, 'urn:corex:problem:tenancy.central_protocol_violation', 500, 'Central protocol violation.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(TenancyErrorCode::CentralUnauthorized, 'urn:corex:problem:tenancy.central_unauthorized', 403, 'Central unauthorized.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(TenancyErrorCode::CentralUnavailable, 'urn:corex:problem:tenancy.central_unavailable', 503, 'Central unavailable.', true, ErrorCategory::Unavailable, []);
        yield new ProblemDefinition(TenancyErrorCode::ClaimConflict, 'urn:corex:problem:tenancy.claim_conflict', 409, 'Claim conflict.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(TenancyErrorCode::ClaimUnavailable, 'urn:corex:problem:tenancy.claim_unavailable', 503, 'Claim unavailable.', true, ErrorCategory::Unavailable, []);
        yield new ProblemDefinition(TenancyErrorCode::ContextUnavailable, 'urn:corex:problem:tenancy.context_unavailable', 503, 'Tenant context unavailable.', false, ErrorCategory::Unavailable, []);
        yield new ProblemDefinition(TenancyErrorCode::CredentialIssuanceDenied, 'urn:corex:problem:tenancy.credential_issuance_denied', 403, 'Credential issuance denied.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(TenancyErrorCode::DomainTrustDenied, 'urn:corex:problem:tenancy.domain_trust_denied', 403, 'Domain trust denied.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(TenancyErrorCode::DomainTrustDisabled, 'urn:corex:problem:tenancy.domain_trust_disabled', 503, 'Domain trust disabled.', false, ErrorCategory::Unavailable, []);
        yield new ProblemDefinition(TenancyErrorCode::InboxConflict, 'urn:corex:problem:tenancy.inbox_conflict', 409, 'Inbox conflict.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(TenancyErrorCode::InvitationNotAcceptableException, 'urn:corex:problem:tenancy.invitation_not_acceptable_exception', 409, 'Invitation not acceptable exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(TenancyErrorCode::NoDefaultWorkspaceException, 'urn:corex:problem:tenancy.no_default_workspace_exception', 404, 'No default workspace exception.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(TenancyErrorCode::OidcAuthenticationException, 'urn:corex:problem:tenancy.oidc_authentication_exception', 401, 'Oidc authentication exception.', false, ErrorCategory::Authentication, []);
        yield new ProblemDefinition(TenancyErrorCode::PairStoreViolation, 'urn:corex:problem:tenancy.pair_store_violation', 500, 'Pair store violation.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(TenancyErrorCode::ProjectionDenied, 'urn:corex:problem:tenancy.projection_denied', 403, 'Projection denied.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(TenancyErrorCode::RevokeUncertain, 'urn:corex:problem:tenancy.revoke_uncertain', 503, 'Revoke uncertain.', true, ErrorCategory::Unavailable, []);
        yield new ProblemDefinition(TenancyErrorCode::TemplateIntegrityException, 'urn:corex:problem:tenancy.template_integrity_exception', 500, 'Template integrity exception.', false, ErrorCategory::Internal, []);
        yield new ProblemDefinition(TenancyErrorCode::TenantCouldNotBeIdentifiedByHostException, 'urn:corex:problem:tenancy.tenant_could_not_be_identified_by_host_exception', 404, 'Tenant could not be identified by host exception.', false, ErrorCategory::NotFound, []);
    }
}
