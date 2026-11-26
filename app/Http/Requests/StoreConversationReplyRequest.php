<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesConversation;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationReplyRequest extends FormRequest
{
    use ResolvesConversation;

    public function authorize(): bool
    {
        return $this->user()?->can('reply', $this->conversation()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'seen_message_id' => ['required', 'integer', 'min:0'],
            'confirm_send' => ['nullable', 'boolean'],
        ];
    }
}
