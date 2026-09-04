<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Services\PlanEntitlements;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Company::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $tenant = $this->user()?->tenant;
            $plans = app(PlanEntitlements::class);
            if ($tenant && ! $plans->hasCapacity($tenant, 'companies')) {
                $validator->errors()->add('name', $plans->limitMessage($tenant, 'companies'));
            }
        }];
    }
}
