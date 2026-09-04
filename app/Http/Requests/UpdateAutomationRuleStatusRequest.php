<?php

namespace App\Http\Requests;

use App\Models\AutomationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationRuleStatusRequest extends FormRequest
{
    private ?AutomationRule $rule = null;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->automationRule()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }

    public function automationRule(): AutomationRule
    {
        return $this->rule ??= AutomationRule::query()->findOrFail((int) $this->route('automationRule'));
    }
}
