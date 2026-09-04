<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Services\PlanEntitlements;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)->only([
                UserRole::CompanyAdmin,
                UserRole::SalesManager,
                UserRole::Salesperson,
            ])],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $tenant = $this->user()?->tenant;
            $plans = app(PlanEntitlements::class);
            if ($tenant && ! $plans->hasCapacity($tenant, 'members', $this->string('email')->value())) {
                $validator->errors()->add('email', $plans->limitMessage($tenant, 'members'));
            }
        }];
    }
}
