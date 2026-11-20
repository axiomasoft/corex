<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\ProviderRecoveryAuthority;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;

final class BindingRestoreRecoveryAuthority implements ProviderRecoveryAuthority
{
    public const string Owner = 'scenario.binding-restore';

    public const string Provider = 'postgres-physical-session';

    public const string Key = 'provider-effect-key';

    public function authorize(ProviderEffectRef $effect): void
    {
        $control = Scenario::connection('control');
        $operator = $control->selectOne(
            'SELECT pg_get_userbyid(datdba) = current_user AND current_user = session_user AS allowed
            FROM pg_database WHERE datname = current_database()',
        );
        $incarnation = $control->table('corex_scope_routes')
            ->where('deployment_id', Scenario::DEPLOYMENT)
            ->where('account_id', Scenario::ACCOUNT_A)
            ->value('account_incarnation');

        if ($operator?->allowed !== true || $effect->accountId !== Scenario::ACCOUNT_A
            || $effect->workspaceId !== Scenario::W1 || $effect->accountIncarnation !== $incarnation
            || $effect->owner !== self::Owner || $effect->providerNamespace !== self::Provider
            || $effect->effectKey !== self::Key) {
            throw new ScopeContextMismatch('The host operator lacks authority for this physical isolation proof.');
        }
    }
}
