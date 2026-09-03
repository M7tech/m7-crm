<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadWorkflow
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Lead
    {
        return DB::transaction(function () use ($data, $actor): Lead {
            $stage = PipelineStage::query()->findOrFail((int) $data['stage_id']);
            $lead = Lead::create($this->normalized($data, $stage));
            $this->activity($lead, $actor, 'created', 'Lead created in '.$stage->name.'.', ['stage_id' => $stage->id]);

            return $lead;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Lead $lead, array $data, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $data, $actor): Lead {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);
            $stage = PipelineStage::query()->findOrFail((int) $data['stage_id']);
            $previousStage = $lead->stage;
            $lead->update($this->normalized($data, $stage));

            $stageChanged = $previousStage->id !== $stage->id;
            $this->activity($lead, $actor, $stageChanged ? 'stage_changed' : 'updated', $stageChanged
                ? 'Lead moved from '.$previousStage->name.' to '.$stage->name.'.'
                : 'Lead details updated.', [
                'from_stage_id' => $previousStage->id,
                'to_stage_id' => $stage->id,
            ]);

            return $lead;
        });
    }

    public function move(Lead $lead, PipelineStage $stage, ?string $lossReason, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $stage, $lossReason, $actor): Lead {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);

            if ($lead->stage_id === $stage->id) {
                return $lead;
            }

            $from = $lead->stage;
            $lead->update([
                'stage_id' => $stage->id,
                'closed_at' => $stage->type === 'open' ? null : now(),
                'loss_reason' => $stage->type === 'lost' ? $lossReason : null,
            ]);
            $this->activity($lead, $actor, 'stage_changed', 'Lead moved from '.$from->name.' to '.$stage->name.'.', [
                'from_stage_id' => $from->id,
                'to_stage_id' => $stage->id,
                'loss_reason' => $stage->type === 'lost' ? $lossReason : null,
            ]);

            return $lead;
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalized(array $data, PipelineStage $stage): array
    {
        $currency = (string) $data['currency'];
        $multiplier = $currency === 'USD' ? 100 : 1000;
        $data['expected_value_minor'] = (int) round((float) $data['expected_value'] * $multiplier);
        unset($data['expected_value']);
        $data['closed_at'] = $stage->type === 'open' ? null : now();
        $data['loss_reason'] = $stage->type === 'lost' ? ($data['loss_reason'] ?? null) : null;

        return $data;
    }

    /** @param array<string, mixed> $metadata */
    private function activity(Lead $lead, User $actor, string $type, string $description, array $metadata): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'actor_id' => $actor->id,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
