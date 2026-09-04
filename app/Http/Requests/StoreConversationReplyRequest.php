<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationReplyRequest extends FormRequest
{
    private ?Conversation $conversation = null;

    public function authorize(): bool
    {
        return $this->user()?->can('reply', $this->conversation()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    public function conversation(): Conversation
    {
        if ($this->conversation instanceof Conversation) {
            return $this->conversation;
        }

        return $this->conversation = Conversation::query()->findOrFail($this->route('conversation'));
    }
}
