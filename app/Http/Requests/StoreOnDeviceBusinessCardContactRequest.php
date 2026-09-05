<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnDeviceBusinessCardContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('create', Contact::class) === true
            && (! $this->boolean('create_company') || $user->can('create', Company::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'create_company' => ['required', 'boolean'],
            'company_id' => [
                'nullable',
                'required_if:create_company,false',
                'integer',
                Rule::exists('companies', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'new_company_name' => ['nullable', 'required_if:create_company,true', 'string', 'max:160'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
