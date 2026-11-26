<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesConversation;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationNoteRequest extends FormRequest
{
    use ResolvesConversation;

    public function authorize(): bool
    {
        return $this->user()?->can('addNote', $this->conversation()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:3000']];
    }
}
