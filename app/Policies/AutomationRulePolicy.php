<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AutomationRule;
use App\Models\User;

class AutomationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && in_array($user->role, [UserRole::CompanyAdmin, UserRole::SalesManager], true);
    }

    public function view(User $user, AutomationRule $rule): bool
    {
        return $this->viewAny($user) && $user->tenant_id === $rule->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AutomationRule $rule): bool
    {
        return $this->view($user, $rule);
    }

    public function delete(User $user, AutomationRule $rule): bool
    {
        return $this->view($user, $rule);
    }
}
