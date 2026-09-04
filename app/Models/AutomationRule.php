<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['stage_id', 'created_by_id', 'name', 'trigger_type', 'action_type', 'task_title', 'due_days', 'priority', 'assignee_strategy', 'is_active'])]
class AutomationRule extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected function casts(): array
    {
        return ['due_days' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<AutomationRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }
}
