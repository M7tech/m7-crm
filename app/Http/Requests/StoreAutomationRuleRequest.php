<?php

namespace App\Http\Requests;

use App\Models\AutomationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AutomationRule::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'stage_id' => ['required', 'integer', Rule::exists('pipeline_stages', 'id')->where('tenant_id', $tenantId)],
            'task_title' => ['required', 'string', 'max:180'],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assignee_strategy' => ['required', Rule::in(['lead_owner', 'unassigned'])],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (AutomationRule::query()->count() >= 25) {
                $validator->errors()->add('name', 'This workspace has reached the limit of 25 automation rules.');
            }
        }];
    }
}
