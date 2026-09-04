<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['automation_rule_id', 'lead_id', 'lead_activity_id', 'task_id', 'status', 'error', 'started_at', 'completed_at'])]
class AutomationRun extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<AutomationRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id')->withTrashed();
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<LeadActivity, $this> */
    public function leadActivity(): BelongsTo
    {
        return $this->belongsTo(LeadActivity::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
