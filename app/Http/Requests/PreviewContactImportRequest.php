<?php

namespace App\Http\Requests;

use App\Models\ContactImport;
use Illuminate\Foundation\Http\FormRequest;

class PreviewContactImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ContactImport::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']];
    }
}
