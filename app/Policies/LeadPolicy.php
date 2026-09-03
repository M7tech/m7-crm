<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null || $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->sameTenantOrSuperAdmin($user, $lead);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->sameTenantOrSuperAdmin($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return false;
    }

    private function sameTenantOrSuperAdmin(User $user, Lead $lead): bool
    {
        return $user->role === UserRole::SuperAdmin
            || ($user->tenant_id !== null && $user->tenant_id === $lead->tenant_id);
    }
}
