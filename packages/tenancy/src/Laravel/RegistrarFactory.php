<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Internal;
use Packman\Foundation\Contracts\PackageRegistrar;

#[Internal]
final class RegistrarFactory
{
    /** @return list<PackageRegistrar> */
    public static function forApplication(Application $app): array
    {
        $corexTenancy = $app->make(Repository::class)->get('corex-tenancy', []);
        $corexTenancy = is_array($corexTenancy) ? $corexTenancy : [];

        $registrars = [
            new RuntimeRegistrar,
            new ProvisioningRegistrar,
            new IdentityRegistrar,
            new WorkspaceRegistrar,
            new DomainTrustRegistrar,
            new ConsoleRegistrar,
            new HttpRegistrar,
            new EventsRegistrar,
        ];

        // TenancyOptions stays bind()-lazy: make() here would
        // validate OIDC before Testbench defineEnvironment fills issuer fields.
        $oidc = is_array($corexTenancy['oidc'] ?? null) ? $corexTenancy['oidc'] : [];
        $oidcEnabled = (bool) ($oidc['enabled'] ?? false);

        if ($oidcEnabled) {
            $registrars[] = new AuthenticationRegistrar;
        }

        $wakeup = is_array($corexTenancy['wakeup'] ?? null) ? $corexTenancy['wakeup'] : [];
        $wakeupDriver = is_string($wakeup['driver'] ?? null)
            ? $wakeup['driver']
            : 'local_sweep';

        // Contracts live in CoreX\Scheduling; the sweeper is resolved lazily from whichever backend binds it.
        // A missing backend is reported by TenancyConfigSection::adapters() and fails loudly on first use.
        if ($wakeupDriver === 'root_registry') {
            $registrars[] = new WakeupRegistrar;
        }

        return $registrars;
    }
}
