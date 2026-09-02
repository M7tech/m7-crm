<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'import_id' => ['required', 'integer'],
            'token' => ['required', 'string', 'size:64'],
            'duplicate_strategy' => ['required', Rule::in(['skip', 'update'])],
        ];
    }
}
