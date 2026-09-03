<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id')->where('tenant_id', $tenantId)],
            'assigned_to_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))],
            'due_at' => ['required', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->user()?->role === UserRole::Salesperson && $this->integer('assigned_to_id') !== $this->user()?->id) {
                $validator->errors()->add('assigned_to_id', 'Salespeople can assign tasks only to themselves.');
            }

            if ($this->filled('reminder_at') && $this->filled('due_at')) {
                if ($validator->errors()->has('reminder_at') || $validator->errors()->has('due_at')) {
                    return;
                }

                $reminder = CarbonImmutable::parse((string) $this->input('reminder_at'));
                $due = CarbonImmutable::parse((string) $this->input('due_at'));
                if ($reminder->greaterThanOrEqualTo($due)) {
                    $validator->errors()->add('reminder_at', 'The reminder must be before the due time.');
                }
            }
        }];
    }
}
