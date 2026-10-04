<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && in_array($user->role, [UserRole::CompanyAdmin, UserRole::SalesManager], true);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Agent $agent): bool
    {
        return $this->viewAny($user) && $user->tenant_id === $agent->tenant_id;
    }
}
