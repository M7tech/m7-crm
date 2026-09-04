<?php

namespace App\Http\Requests;

use App\Models\Integration;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaIntegrationConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $integration = $this->route('integration');

        return $integration instanceof Integration
            && $integration->provider === 'meta_lead_ads'
            && ($this->user()?->can('update', $integration) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'configuration_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
        ];
    }
}
