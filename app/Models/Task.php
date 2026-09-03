<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
