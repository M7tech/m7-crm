<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = User::query()
            ->where('tenant_id', $this->user()?->tenant_id)
            ->find((int) $this->route('user'));

        return $member !== null && ($this->user()?->can('update', $member) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)->only([
                UserRole::CompanyAdmin,
                UserRole::SalesManager,
                UserRole::Salesperson,
            ])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
