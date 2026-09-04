<?php

namespace App\Http\Requests;

use App\Models\BusinessCardScan;
use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessCardScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BusinessCardScan::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'card_image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:10240',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'card_image.required' => 'Take a photo or choose a business-card image.',
            'card_image.mimes' => 'Use a JPG, PNG, or WebP image.',
            'card_image.mimetypes' => 'Use a valid JPG, PNG, or WebP image.',
            'card_image.max' => 'The business-card image must not exceed 10 MB.',
        ];
    }
}
