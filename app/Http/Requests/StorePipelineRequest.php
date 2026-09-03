<?php

namespace App\Http\Requests;

use App\Models\Pipeline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Pipeline::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'stages_text' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $stages = $this->stageNames();
            if (count($stages) < 1 || count($stages) > 10) {
                $validator->errors()->add('stages_text', 'Enter between 1 and 10 open stage names.');
            }
            if (count($stages) !== count(array_unique(array_map('mb_strtolower', $stages)))) {
                $validator->errors()->add('stages_text', 'Stage names must be unique.');
            }
        }];
    }

    /** @return array<int, string> */
    public function stageNames(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $this->input('stages_text')) ?: [])));
    }
}
