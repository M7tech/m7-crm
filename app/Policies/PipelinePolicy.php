<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Pipeline;
use App\Models\User;

class PipelinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null || $user->role === UserRole::SuperAdmin;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && in_array($user->role, [UserRole::CompanyAdmin, UserRole::SalesManager], true);
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return $this->create($user) && $user->tenant_id === $pipeline->tenant_id;
    }
}
