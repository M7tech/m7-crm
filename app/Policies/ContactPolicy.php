<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null || $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->sameTenantOrSuperAdmin($user, $contact);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && in_array($user->role, [
            UserRole::CompanyAdmin,
            UserRole::SalesManager,
            UserRole::Salesperson,
        ], true);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->sameTenantOrSuperAdmin($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->sameTenantOrSuperAdmin($user, $contact)
            && $user->role !== UserRole::Salesperson;
    }

    private function sameTenantOrSuperAdmin(User $user, Contact $contact): bool
    {
        return $user->role === UserRole::SuperAdmin
            || ($user->tenant_id !== null && $user->tenant_id === $contact->tenant_id);
    }
}
