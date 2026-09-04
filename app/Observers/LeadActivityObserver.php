<?php

namespace App\Observers;

use App\Jobs\RunLeadAutomations;
use App\Models\LeadActivity;

class LeadActivityObserver
{
    public function created(LeadActivity $activity): void
    {
        if (! in_array($activity->type, ['created', 'stage_changed'], true)) {
            return;
        }

        RunLeadAutomations::dispatch($activity->id, $activity->tenant_id)->afterCommit();
    }
}
