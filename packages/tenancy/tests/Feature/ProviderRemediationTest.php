<?php

declare(strict_types=1);

use CoreX\Tenancy\Models\Account;
use CoreX\Tenancy\TenancyServiceProvider;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;

final class RemediationProvisioningStep
{
    public static ?Account $seen = null;

    public function __construct(private Account $account) {}

    public function handle(): void
    {
        self::$seen = $this->account;
    }
}

it('executes the configured provisioning pipeline', function (): void {
    Event::swap(new Dispatcher(app()));
    config(['tenancy.provisioning.pipeline' => [RemediationProvisioningStep::class]]);
    (new TenancyServiceProvider(app()))->boot();
    $account = new Account;
    Event::dispatch(new TenantCreated($account));
    expect(RemediationProvisioningStep::$seen)->toBe($account);
});

it('publishes control plane migrations only to the documented root directory', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'tenancy-migrations', '--force' => true])->assertSuccessful();
    expect(glob(database_path('migrations/*.php')))->toBe([])
        ->and(glob(database_path('migrations/root/*.php')))->not->toBeEmpty();
});
