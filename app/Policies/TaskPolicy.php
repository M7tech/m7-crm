<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null || $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->canAccess($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->canAccess($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return false;
    }

    private function canAccess(User $user, Task $task): bool
    {
        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        if ($user->tenant_id === null || $user->tenant_id !== $task->tenant_id) {
            return false;
        }

        return $user->role !== UserRole::Salesperson
            || $task->assigned_to_id === $user->id
            || $task->created_by_id === $user->id;
    }
}
