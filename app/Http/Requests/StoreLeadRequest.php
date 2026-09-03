<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'title' => ['required', 'string', 'max:180'],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('tenant_id', $tenantId)],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('tenant_id', $tenantId)],
            'pipeline_id' => ['required', 'integer', Rule::exists('pipelines', 'id')->where('tenant_id', $tenantId)],
            'stage_id' => ['required', 'integer', Rule::exists('pipeline_stages', 'id')->where('tenant_id', $tenantId)],
            'assigned_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))],
            'expected_value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'currency' => ['required', Rule::in(['IQD', 'USD'])],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'loss_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $stage = PipelineStage::query()->find($this->integer('stage_id'));
            if ($stage && $stage->pipeline_id !== $this->integer('pipeline_id')) {
                $validator->errors()->add('stage_id', 'The selected stage does not belong to this pipeline.');
            }
            if ($stage?->type === 'lost' && blank($this->input('loss_reason'))) {
                $validator->errors()->add('loss_reason', 'A loss reason is required for a lost lead.');
            }

            if ($this->filled('contact_id')) {
                $matches = \App\Models\Contact::query()
                    ->whereKey($this->integer('contact_id'))
                    ->where('company_id', $this->integer('company_id'))
                    ->exists();
                if (! $matches) {
                    $validator->errors()->add('contact_id', 'The contact must belong to the selected company.');
                }
            }
        }];
    }
}
