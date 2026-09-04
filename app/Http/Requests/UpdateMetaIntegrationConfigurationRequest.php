<?php

namespace App\Http\Requests;

use App\Models\Integration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ((string) $this->connection()->credentials['app_id'] === $this->string('configuration_id')->value()) {
                $validator->errors()->add('configuration_id', 'The Configuration ID must not be the same as the Meta App ID.');
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
