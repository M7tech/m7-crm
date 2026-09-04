<?php

namespace App\Http\Requests;

use App\Models\Integration;
use App\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMetaIntegrationRoutingRequest extends FormRequest
{
    private ?Integration $connection = null;

    public function authorize(): bool
    {
        $integration = $this->connection();

        return $integration->provider === 'meta_lead_ads'
            && ($this->user()?->can('update', $integration) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
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

    public function connection(): Integration
    {
        if ($this->connection instanceof Integration) {
            return $this->connection;
        }

        $publicId = $this->route('integration');
        abort_unless(is_string($publicId), 404);

        return $this->connection = Integration::query()->where('public_id', $publicId)->firstOrFail();
    }
}
