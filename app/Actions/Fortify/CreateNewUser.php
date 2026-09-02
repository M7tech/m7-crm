<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'company_name' => ['required', 'string', 'max:160'],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $baseSlug = Str::slug($input['company_name']) ?: 'company';

            $tenant = Tenant::create([
                'name' => $input['company_name'],
                'slug' => $baseSlug.'-'.Str::lower(Str::random(8)),
                'status' => TenantStatus::Active,
                'plan' => 'starter',
                'timezone' => 'Asia/Baghdad',
                'locale' => 'en',
            ]);

            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'role' => UserRole::CompanyAdmin,
                'status' => 'active',
            ]);
        });
    }
}
