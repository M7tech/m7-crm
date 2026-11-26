<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignConversationRequest extends FormRequest
{
    use ResolvesConversation;

    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->conversation()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'assigned_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $assigneeId = $this->filled('assigned_to_id') ? $this->integer('assigned_to_id') : null;

            if ($assigneeId !== null && $assigneeId !== $this->user()?->id && $this->user()?->can('assignOthers', $this->conversation()) !== true) {
                $validator->errors()->add('assigned_to_id', __('Claim the conversation before handing it to a teammate.'));
            }
        }];
    }
}
