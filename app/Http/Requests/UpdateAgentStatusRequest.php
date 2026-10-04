<?php

namespace App\Http\Requests;

use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentStatusRequest extends FormRequest
{
    private ?Agent $agent = null;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->agent()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['active', 'inactive'])]];
    }

    public function agent(): Agent
    {
        return $this->agent ??= Agent::query()->findOrFail((int) $this->route('agent'));
    }
}
