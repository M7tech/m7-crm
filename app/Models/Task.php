<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lead_id', 'assigned_to_id', 'created_by_id', 'title', 'description', 'due_at', 'reminder_at', 'reminder_sent_at', 'priority', 'status', 'completed_at'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'reminder_at' => 'datetime', 'reminder_sent_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_at->isPast();
    }

    /** @param Builder<Task> $query @return Builder<Task> */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->role === UserRole::Salesperson,
            fn (Builder $builder) => $builder->where(fn (Builder $scope) => $scope
                ->where('assigned_to_id', $user->id)
                ->orWhere('created_by_id', $user->id)),
        );
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<TaskActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest('created_at');
    }
}
