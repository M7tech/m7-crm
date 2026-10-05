<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class SaveConversationContactRequest extends FormRequest
{
    private ?Conversation $conversationModel = null;

    public function authorize(): bool
    {
        $conversation = $this->conversation();
        $contact = $conversation->contact;

        if ($this->user()?->can('view', $conversation) !== true) {
            return false;
        }

        return $contact
            ? $this->user()?->can('update', $contact) === true
            : $this->user()?->can('create', Contact::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function conversation(): Conversation
    {
        return $this->conversationModel ??= Conversation::query()
            ->with('contact')
            ->findOrFail((int) $this->route('conversation'));
    }
}
