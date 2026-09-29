<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Models;

use CoreX\Database\HasConfiguredId;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Context\ContextGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Override;

/**
 * @property string $id
 * @property string $contact_id
 * @property string $company_id
 * @property ?string $label
 * @property bool $is_primary
 */
final class ContactCompany extends Pivot
{
    use HasConfiguredId;

    protected $table = 'crm_contact_company';

    protected $guarded = ['id'];

    #[Override]
    protected static function booted(): void
    {
        self::addGlobalScope('crm-context', static function (Builder $builder): void {
            $ctx = app(ContextGuard::class)->current();

            if ($builder->getModel()->getConnection()->getName() !== $ctx->connectionName) {
                throw new AuthorizationException('CRM association query requires its context connection.');
            }
            $builder->whereHas('contact')->whereHas('company');
        });
        self::saving(static fn (self $link) => $link->assertContext());
    }

    private function assertContext(): void
    {
        $guard = app(ContextGuard::class);
        $ctx = $guard->current(lock: $this->getConnection()->transactionLevel() > 0);

        if ($this->getConnection()->getName() !== $ctx->connectionName) {
            throw new AuthorizationException('CRM association write requires its context connection.');
        }
        $contact = Contact::on($ctx->connectionName)->findOrFail($this->contact_id);
        $company = Company::on($ctx->connectionName)->findOrFail($this->company_id);

        if ($contact->workspace_id !== $company->workspace_id) {
            throw new AuthorizationException('CRM association requires identical workspace scopes.');
        }

        if ($contact->workspace_id === null && ! $ctx->allowAccountLevel) {
            throw new AuthorizationException('CRM account association requires account-level capability.');
        }
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getAttributesForInsert(): array
    {
        $this->assertContext();

        return parent::getAttributesForInsert();
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getDirtyForUpdate(): array
    {
        $this->assertContext();

        return parent::getDirtyForUpdate();
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    #[Override]
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
