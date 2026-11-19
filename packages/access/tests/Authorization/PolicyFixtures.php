<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Authorization;

use AzGuard\Contracts\Authorization\EvaluationContext;
use AzGuard\Contracts\Authorization\FiltersAccessQueries;
use AzGuard\Contracts\Scopes\ProvidesAccessScope;
use AzGuard\Kernel\Decision\AccessPredicate;
use AzGuard\Kernel\Decision\AccessRequest;
use AzGuard\Kernel\Decision\Grant;
use AzGuard\Kernel\Decision\RoleContribution;
use AzGuard\Kernel\Identity\AccessScope;
use AzGuard\Kernel\Identity\AssignmentScopeRef;
use AzGuard\Kernel\Identity\TenantRef;
use AzGuard\Panels\PanelBuilder;
use AzGuard\Panels\PanelProvider;
use AzGuard\Permissions\PolicyOnly;
use AzGuard\Policies\Decides;
use AzGuard\Policies\PolicyBinding;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable implements Principal
{
    public $timestamps = false;

    protected $guarded = [];

    public function getMorphClass(): string
    {
        return 'user';
    }

    public function principalType(): PrincipalType
    {
        return PrincipalType::User;
    }

    public function principalId(): string
    {
        return (string) $this->getKey();
    }
}

final class Project extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

#[PolicyOnly]
enum ProjectPermission: string
{
    case View = 'test.project.view';
}

final class ProjectPolicy implements FiltersAccessQueries
{
    public static bool $allowEverything = false;

    #[Decides(ProjectPermission::View)]
    public function view(?User $user, ?object $record): bool
    {
        return self::$allowEverything || $user !== null && ($record === null || ($record instanceof Project && (string) $record->getAttribute('owner_id') === (string) $user->getKey()));
    }

    public function predicate(AccessRequest $request, string $resourceType, EvaluationContext $context, Grant|RoleContribution|null $contribution = null): AccessPredicate
    {
        if (self::$allowEverything) {
            return AccessPredicate::policyResult(true);
        }
        $own = AccessPredicate::eq('owner_id', $request->subject()->id());

        return AccessPredicate::partition($own, AccessPredicate::not($own), AccessPredicate::deny());
    }
}

final class BridgePanel extends PanelProvider
{
    public static function getId(): string
    {
        return 'app';
    }

    public function panel(PanelBuilder $panel): PanelBuilder
    {
        return $panel->for(User::class)->resourcePrefix(false)
            ->permissions([ProjectPermission::class])
            ->policies([PolicyBinding::for(ProjectPermission::View, ProjectPolicy::class)]);
    }
}

final class ScopedProject extends Model implements ProvidesAccessScope
{
    protected $table = 'projects';

    protected $guarded = [];

    public function azguardScope(): AccessScope
    {
        return AccessScope::in(
            $this->getAttribute('account_id') === null
                ? TenantRef::global()
                : TenantRef::of('account', (string) $this->getAttribute('account_id')),
            $this->getAttribute('workspace_id') === null
                ? AssignmentScopeRef::global()
                : AssignmentScopeRef::of('workspace', (string) $this->getAttribute('workspace_id')),
        );
    }
}
