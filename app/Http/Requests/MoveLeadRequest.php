<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MoveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = Lead::query()->find((int) $this->route('lead'));

        return $lead !== null && ($this->user()?->can('update', $lead) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', Rule::exists('pipeline_stages', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'loss_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $lead = Lead::query()->find((int) $this->route('lead'));
            $stage = PipelineStage::query()->find($this->integer('stage_id'));
            if ($lead && $stage && $lead->pipeline_id !== $stage->pipeline_id) {
                $validator->errors()->add('stage_id', 'The selected stage does not belong to this pipeline.');
            }
            if ($stage?->type === 'lost' && blank($this->input('loss_reason'))) {
                $validator->errors()->add('loss_reason', 'A loss reason is required when marking a lead lost.');
            }
        }];
    }
}
