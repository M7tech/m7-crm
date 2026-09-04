<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Integration;
use App\Models\User;

class IntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    public function view(User $user, Integration $integration): bool
    {
        return $this->create($user) && $user->tenant_id === $integration->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->role === UserRole::CompanyAdmin;
    }

    public function update(User $user, Integration $integration): bool
    {
        return $this->create($user) && $user->tenant_id === $integration->tenant_id;
    }

    public function delete(User $user, Integration $integration): bool
    {
        return $this->update($user, $integration);
    }
}
