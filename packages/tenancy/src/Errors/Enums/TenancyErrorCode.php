<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum TenancyErrorCode: string implements ErrorCode
{
    case CentralProtocolViolation = 'tenancy.central_protocol_violation';
    case CentralUnauthorized = 'tenancy.central_unauthorized';
    case CentralUnavailable = 'tenancy.central_unavailable';
    case ClaimConflict = 'tenancy.claim_conflict';
    case ClaimUnavailable = 'tenancy.claim_unavailable';
    case ContextUnavailable = 'tenancy.context_unavailable';
    case CredentialIssuanceDenied = 'tenancy.credential_issuance_denied';
    case DomainTrustDenied = 'tenancy.domain_trust_denied';
    case DomainTrustDisabled = 'tenancy.domain_trust_disabled';
    case InboxConflict = 'tenancy.inbox_conflict';
    case InvitationNotAcceptableException = 'tenancy.invitation_not_acceptable_exception';
    case NoDefaultWorkspaceException = 'tenancy.no_default_workspace_exception';
    case OidcAuthenticationException = 'tenancy.oidc_authentication_exception';
    case PairStoreViolation = 'tenancy.pair_store_violation';
    case ProjectionDenied = 'tenancy.projection_denied';
    case RevokeUncertain = 'tenancy.revoke_uncertain';
    case TemplateIntegrityException = 'tenancy.template_integrity_exception';
    case TenantCouldNotBeIdentifiedByHostException = 'tenancy.tenant_could_not_be_identified_by_host_exception';

    public function value(): string
    {
        return $this->value;
    }
}
