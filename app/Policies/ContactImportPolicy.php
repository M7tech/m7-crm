<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ContactImport;
use App\Models\User;

class ContactImportPolicy
{
    public function create(User $user): bool
    {
        return $user->tenant_id !== null && in_array($user->role, [
            UserRole::CompanyAdmin,
            UserRole::SalesManager,
        ], true);
    }

    public function update(User $user, ContactImport $import): bool
    {
        return $this->create($user) && $user->tenant_id === $import->tenant_id;
    }
}
