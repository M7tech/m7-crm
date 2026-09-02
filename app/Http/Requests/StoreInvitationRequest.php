<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
