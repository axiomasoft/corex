<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Internal\TrustedContextResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class TrustedContextResolverTest extends TestCase
{
    public function test_rebuilt_equal_contexts_keep_value_comparison_semantics(): void
    {
        $current = new TenantContext(new AccountRef('account', 'account', 'active', ['api'], ['max' => 1]));
        $physical = new TenantContext(new AccountRef('account', 'account', 'active', ['api'], ['max' => '1']));
        $resolver = $this->resolver($current, $physical);

        self::assertNotSame($current, $physical);
        self::assertSame($current, $resolver->resolve(new Request, $this->createStub(Principal::class), new InvocationMetadata('request', 'v1', 'operation', 'GET'))->tenant());
    }

    public function test_different_physical_account_context_is_rejected(): void
    {
        $current = new TenantContext(new AccountRef('account', 'account', 'active', [], []));
        $physical = new TenantContext(new AccountRef('other', 'other', 'active', [], []));
        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('An initialized physical account context is required.');
        $this->resolver($current, $physical)->resolve(new Request, $this->createStub(Principal::class), new InvocationMetadata('request', 'v1', 'operation', 'GET'));
    }

    private function resolver(TenantContext $current, TenantContext $physical): TrustedContextResolver
    {
        $tenants = $this->createStub(TenantContextResolver::class);
        $tenants->method('current')->willReturn($current);
        $manager = $this->createStub(TenancyManager::class);
        $manager->method('initialized')->willReturn(true);
        $manager->method('context')->willReturn($physical);

        return new TrustedContextResolver($tenants, $manager);
    }
}
