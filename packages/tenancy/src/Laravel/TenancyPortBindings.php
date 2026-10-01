<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Account\Internal\SqlAccountRead;
use CoreX\Tenancy\Identity\Contracts\MembershipProjection;
use CoreX\Tenancy\Identity\Internal\Membership\ApplyMembershipProjection;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use Illuminate\Contracts\Foundation\Application;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TenancyPortBindings
{
    public static function register(Application $app): void
    {
        $app->bind(MembershipProjection::class, static fn (Application $app): MembershipProjection => $app->make(ApplyMembershipProjection::class));
        $app->bind(AccountRead::class, SqlAccountRead::class);
        $app->bind(TemplateIntegrityChecks::class, static fn (Application $app): TemplateIntegrityChecks => $app->make(TemplateIntegrityVerifier::class));
    }
}
