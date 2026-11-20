<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeAuthorityUnavailable;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Tenancy\Runtime\Contracts\TenantBindingAuthority;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Packman\Attributes\Internal;
use Tenant\Contracts\Runtime\TenantRuntime;

#[Internal]
final readonly class TenantPhysicalBindingVerifier implements PhysicalBindingVerifier
{
    public function __construct(private TenantRuntime $runtime, private ?TenantBindingAuthority $authority = null) {}

    public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        $proof = ($this->authority ?? throw new ScopeAuthorityUnavailable('The current Tenant control owner is required.'))->verify($binding, $context);
        $expected = $this->runtime->current()->context?->binding;

        if ($expected === null || get_object_vars($expected) !== get_object_vars($proof->expected)) {
            throw new ScopeStale('The active tenant route differs from its physical owner.');
        }
        $marker = ['protocol' => 'tenant.binding/1', 'owner_id' => $proof->ownerId, 'resource_ref' => $expected->resourceRef,
            'incarnation' => $expected->incarnation, 'recovery_generation' => $proof->recoveryGeneration];
        foreach ([false, true] as $read) {
            try {
                $rows = $connection->select('SELECT protocol, owner_id, resource_ref, incarnation, recovery_generation FROM public.tenant_binding', [], $read);
            } catch (QueryException $error) {
                if ($error->getCode() === '42P01') {
                    throw new ScopeStale('The connection has no physical binding marker.');
                }

                throw new ScopeAuthorityUnavailable('The connection owner cannot be verified.');
            }

            if (count($rows) !== 1 || (array) $rows[0] !== $marker) {
                throw new ScopeStale('The active connection differs from its verified owner.');
            }
        }
    }
}
