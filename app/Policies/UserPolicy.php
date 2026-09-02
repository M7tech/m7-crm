<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function update(User $actor, User $member): bool
    {
        return $actor->role === UserRole::CompanyAdmin
            && $actor->tenant_id !== null
            && $actor->tenant_id === $member->tenant_id
            && $actor->isNot($member)
            && $member->role !== UserRole::SuperAdmin;
    }
}
