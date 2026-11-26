<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesConversation;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationStatusRequest extends FormRequest
{
    use ResolvesConversation;

    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->conversation()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(Conversation::STATUSES)]];
    }
}
