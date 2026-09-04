<?php

namespace App\Http\Requests;

use App\Models\Integration;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaIntegrationConfigurationRequest extends FormRequest
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
        return [
            'configuration_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
        ];
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
