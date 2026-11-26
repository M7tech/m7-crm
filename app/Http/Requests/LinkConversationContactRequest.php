<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesConversation;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkConversationContactRequest extends FormRequest
{
    use ResolvesConversation;

    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->conversation()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('tenant_id', $this->user()?->tenant_id)],
        ];
    }

    public function contact(): Contact
    {
        $contact = Contact::query()->findOrFail($this->integer('contact_id'));
        abort_unless($this->user()?->can('view', $contact) === true, 403);

        return $contact;
    }
}
