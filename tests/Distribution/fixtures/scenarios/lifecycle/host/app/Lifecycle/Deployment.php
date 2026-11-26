<?php

declare(strict_types=1);

namespace App\Lifecycle;

use App\Scenario\Scenario;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\DeploymentOwners;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceExclusion;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Data\RemovalApproval;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use RuntimeException;

final class Deployment implements DeploymentOwners
{
    public static function digests(string $removing = Inventory::PROVIDER): array
    {
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true, flags: JSON_THROW_ON_ERROR);
        $packages = array_column($lock['packages'], null, 'name');
        ksort($packages);
        $host = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $installed = self::digest([$host, $packages]);

        if (! isset($packages[$removing])) {
            throw new RuntimeException('The provider to be removed is missing.');
        }
        unset($packages[$removing]);
        unset($host['require'][$removing]);

        return [new PlanDigest($installed), new PlanDigest(self::digest([$host, $packages]))];
    }

    public function freeze(ModuleRef $provider, PlanDigest $installed, PlanDigest $proposed): string
    {
        if (self::digests($provider->package) != [$installed, $proposed]) {
            throw new RuntimeException('Unknown deployment composition.');
        }
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true, flags: JSON_THROW_ON_ERROR);
        $dependents = [];
        foreach ($lock['packages'] as $package) {
            if ($package['name'] !== $provider->package && isset($package['require'][$provider->package])) {
                $dependents[$package['name']] = $package['require'][$provider->package];
            }
        }

        if ($dependents !== []) {
            throw new RuntimeException(json_encode(['required' => $provider->package, 'dependents' => $dependents], JSON_THROW_ON_ERROR));
        }

        if ($provider->package !== Inventory::PROVIDER) {
            throw new RuntimeException('Unknown provider removal authority.');
        }
        $db = Scenario::connection('control');

        return $db->transaction(function () use ($db, $installed): string {
            $row = $db->table('scenario_deployment')->lockForUpdate()->sole();

            if ($row->composition !== $installed->value) {
                throw new RuntimeException('Deployment already changed.');
            }
            $revision = $row->rollout ?? (string) Str::uuid7();
            $db->table('scenario_deployment')->update(['rollout' => $revision]);

            return $revision;
        });
    }

    public function owners(string $revision): array
    {
        $db = Scenario::connection('control');
        $db->table('scenario_deployment')->where('rollout', $revision)->sole();
        $sites = $db->table('scenario_binding_locations')->orderBy('role')->get();

        if ($sites->pluck('role')->all() !== ['a', 'b'] || $sites->pluck('account_id')->all() !== [Scenario::ACCOUNT_A, Scenario::ACCOUNT_B]) {
            throw new RuntimeException('Deployment owner directory incomplete.');
        }

        return $sites->pluck('account_id')->all();
    }

    public function certify(string $revision, string $owner, ModuleRef $provider): string
    {
        if (! in_array($owner, $this->owners($revision), true) || Scenario::connection('control')->table('scenario_unavailable')->where('account', $owner)->exists()) {
            throw new RuntimeException('Physical owner unavailable.');
        }

        return Inventory::enter($owner, null, function (DataScope $scope) use ($provider, $revision, $owner): string {
            $control = resolve(DatabaseScopeControl::class);
            $control->fenceModule($scope, $provider, $revision, null);

            if ($control->outstandingReferences($scope, $provider) !== []) {
                throw new RuntimeException('Admitted writers still hold the provider.');
            }
            $inventory = resolve(Inventory::class);
            $table = Scenario::connection('control')->table('scenario_owner_certificates');
            $old = $table->where(['rollout' => $revision, 'account' => $owner])->first();
            $original = $old === null ? [] : json_decode($old->proof, true, flags: JSON_THROW_ON_ERROR);
            $proof = ['composition' => self::digests(), 'catalogue' => resolve(ModuleRegistry::class)->compiled()->fingerprint,
                'revision' => $inventory->revision($scope, $provider), 'scope' => $scope, 'rollout' => $revision];
            foreach ($inventory->scopes($scope, $provider) as $subject) {
                $proof['pages'][] = $inventory->run($subject, static function () use ($provider, $subject, $revision, $original, &$proof): array {
                    $seen = [];
                    foreach (app()->tagged(ModuleReferences::class) as $probe) {
                        $key = $probe->owner();

                        if (isset($seen[$key])) {
                            throw new RuntimeException('Duplicate removal probe.');
                        }

                        if ($probe instanceof ReferenceExclusion) {
                            $resource = ($subject->workspaceId ?? 'account').'/'.$key;
                            $previous = $original === [] ? null : new OwnerLease(...$original['leases'][$resource]);
                            $lease = $probe->hold($subject, $provider, new LifecyclePlanRef($revision), $previous);
                            $probe->verify($subject, $lease);
                            $proof['leases'][$resource] = get_object_vars($lease);
                        }
                        $cursor = null;
                        $positions = [];
                        do {
                            $page = $probe->scan($provider, $subject, $cursor);

                            if ($page->blockers !== [] || $page->actions !== [] || $page->revision === ''
                                || (isset($seen[$key]) && $seen[$key] !== $page->revision)) {
                                throw new RuntimeException('Provider removal blocked by '.$key);
                            }
                            $seen[$key] = $page->revision;
                            $cursor = $page->next;

                            if ($cursor !== null && ($cursor->revision !== $page->revision || isset($positions[$cursor->position]))) {
                                throw new RuntimeException('Unstable removal cursor.');
                            }

                            if ($cursor !== null) {
                                $positions[$cursor->position] = true;
                            }
                        } while ($cursor !== null);
                    }
                    foreach (['axiomasoft/corex', 'axiomasoft/corex-tenancy', 'axiomasoft/corex-custom-fields',
                        'axiomasoft/corex-search', 'axiomasoft/packman-panels', ExternalResources::OWNER] as $required) {
                        if (! isset($seen[$required])) {
                            throw new RuntimeException('Missing owner '.$required);
                        }
                    }
                    ksort($seen);

                    return [$subject, $seen];
                });
            }
            $digest = self::digest($proof);
            $table = Scenario::connection('control')->table('scenario_owner_certificates');
            $table->insertOrIgnore(['rollout' => $revision, 'account' => $owner, 'digest' => $digest, 'proof' => json_encode($proof, JSON_THROW_ON_ERROR)]);

            if ($table->where(['rollout' => $revision, 'account' => $owner])->sole()->digest !== $digest) {
                throw new RuntimeException('Physical certificate changed during promotion.');
            }

            return $digest;
        });
    }

    public function seal(string $revision, ModuleRef $provider, PlanDigest $installed, PlanDigest $proposed, array $receipts): RemovalApproval
    {
        $owners = $this->owners($revision);
        $actual = Scenario::connection('control')->table('scenario_owner_certificates')->where('rollout', $revision)->orderBy('account')->pluck('digest', 'account')->all();

        if ($actual !== $receipts || array_keys($receipts) !== $owners) {
            throw new RuntimeException('Missing original physical owner certificates.');
        }

        return new RemovalApproval($provider, $installed, $proposed, self::digest([$revision, $provider, $installed, $proposed, $receipts]));
    }

    public function promote(RemovalApproval $approval, PlanDigest $installed): void
    {
        $db = Scenario::connection('control');
        $revision = $db->table('scenario_deployment')->where('composition', $installed->value)->sole()->rollout;
        foreach ($this->owners($revision) as $account) {
            $this->certify($revision, $account, $approval->provider);
        }
        $db->transaction(function () use ($approval, $installed, $db): void {
            $row = $db->table('scenario_deployment')->where('composition', $installed->value)->lockForUpdate()->sole();
            $receipts = $db->table('scenario_owner_certificates')->where('rollout', $row->rollout)->orderBy('account')->pluck('digest', 'account')->all();

            if ($approval != $this->seal($row->rollout, $approval->provider, $installed, $approval->proposed, $receipts)) {
                throw new RuntimeException('Promotion receipt changed.');
            }
            $db->table('scenario_deployment')->update(['composition' => $approval->proposed->value, 'promotion' => $approval->receipt]);
        });
    }

    private static function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
