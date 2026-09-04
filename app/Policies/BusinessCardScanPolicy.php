<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BusinessCardScan;
use App\Models\Contact;
use App\Models\User;

class BusinessCardScanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('create', Contact::class);
    }

    public function view(User $user, BusinessCardScan $scan): bool
    {
        return $user->role === UserRole::SuperAdmin
            || ($user->tenant_id !== null && $user->tenant_id === $scan->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('create', Contact::class);
    }

    public function update(User $user, BusinessCardScan $scan): bool
    {
        return $this->view($user, $scan) && $user->can('create', Contact::class);
    }

    public function delete(User $user, BusinessCardScan $scan): bool
    {
        return $this->update($user, $scan);
    }
}
