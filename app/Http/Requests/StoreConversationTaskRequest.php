<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesConversation;

/**
 * A follow-up task created from the inbox. Customer links are derived from
 * the conversation on the server; only task fields come from the form.
 */
class StoreConversationTaskRequest extends StoreTaskRequest
{
    use ResolvesConversation;

    public function authorize(): bool
    {
        return parent::authorize() && ($this->user()?->can('view', $this->conversation()) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['company_id'], $rules['contact_id']);

        return $rules;
    }
}
