<?php

namespace App\Http\Requests;

use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Agent::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('agents')->where('tenant_id', $tenantId)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('agents')->where('tenant_id', $tenantId)],
        ];
    }
}
