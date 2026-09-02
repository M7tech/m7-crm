<?php

namespace App\Http\Requests;

use App\Models\Contact;

class UpdateContactRequest extends StoreContactRequest
{
    public function authorize(): bool
    {
        $contactId = (int) $this->route('contact');
        $contact = Contact::query()->find($contactId);

        return $contact !== null && ($this->user()?->can('update', $contact) ?? false);
    }
}
