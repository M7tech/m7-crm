<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendPublicContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'company' => ['nullable', 'string', 'max:160'],
            'topic' => ['required', Rule::in(['sales', 'support', 'privacy', 'other'])],
            'plan' => ['nullable', Rule::in(array_keys(config('plans.plans', [])))],
            'message' => ['required', 'string', 'max:4000'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
