<?php

namespace App\Http\Requests;

use App\Models\Integration;
use App\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMetaIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Integration::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'app_id' => ['required', 'string', 'max:100'],
            'app_secret' => ['required', 'string', 'max:255'],
            'graph_version' => ['required', 'regex:/^v\d+\.\d+$/'],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('tenant_id', $tenantId)],
            'pipeline_id' => ['required', 'integer', Rule::exists('pipelines', 'id')->where('tenant_id', $tenantId)],
            'stage_id' => ['required', 'integer', Rule::exists('pipeline_stages', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('type', 'open'))],
            'assigned_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))],
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
        }];
    }
}
