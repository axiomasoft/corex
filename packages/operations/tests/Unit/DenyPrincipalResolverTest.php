<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Operations\Authorization\Internal\DenyPrincipalResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class DenyPrincipalResolverTest extends TestCase
{
    public function test_default_resolver_never_promotes_request_claims_to_a_principal(): void
    {
        $request = Request::create('/operations', 'POST', ['principal_id' => 'claimed-user']);
        $request->setUserResolver(static fn (): object => (object) ['id' => 'claimed-user']);

        self::assertNull((new DenyPrincipalResolver)->resolve(request: $request));
    }
}
