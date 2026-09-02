<?php

namespace App\Models\Scopes;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<Model> */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentTenant = app(CurrentTenant::class);

        if ($currentTenant->hasGlobalAccess()) {
            return;
        }

        if ($tenantId = $currentTenant->id()) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);

            return;
        }

        // Tenant-owned models fail closed when no tenant has been resolved.
        $builder->whereRaw('1 = 0');
    }
}
