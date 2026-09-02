<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if ($model->tenant_id !== null) {
                return;
            }

            $tenantId = app(CurrentTenant::class)->id();

            if ($tenantId === null) {
                throw new LogicException('A tenant must be resolved before creating tenant-owned records.');
            }

            $model->tenant_id = $tenantId;
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
