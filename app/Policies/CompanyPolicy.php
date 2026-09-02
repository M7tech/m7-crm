<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null || $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, Company $company): bool
    {
        return $this->sameTenantOrSuperAdmin($user, $company);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SuperAdmin,
            UserRole::CompanyAdmin,
            UserRole::SalesManager,
            UserRole::Salesperson,
        ], true);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->sameTenantOrSuperAdmin($user, $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->sameTenantOrSuperAdmin($user, $company)
            && $user->role !== UserRole::Salesperson;
    }

    private function sameTenantOrSuperAdmin(User $user, Company $company): bool
    {
        return $user->role === UserRole::SuperAdmin
            || ($user->tenant_id !== null && $user->tenant_id === $company->tenant_id);
    }
}
