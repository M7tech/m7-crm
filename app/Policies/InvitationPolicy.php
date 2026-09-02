<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->role === UserRole::CompanyAdmin;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->viewAny($user) && $user->tenant_id === $invitation->tenant_id;
    }
}
