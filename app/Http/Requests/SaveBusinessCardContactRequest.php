<?php

namespace App\Http\Requests;

use App\Models\Contact;

class SaveBusinessCardContactRequest extends StoreContactRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contact::class) ?? false;
    }
}
