<div class="grid gap-5 sm:grid-cols-2">
    <flux:input name="title" :label="__('Task title')" :value="old('title', $task?->title)" class="sm:col-span-2" required />

    <div>
        <label for="lead_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Related lead <span class="font-normal text-zinc-500">(optional)</span></label>
        <select id="lead_id" name="lead_id" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="">No related lead</option>
            @foreach ($leads as $lead)
                <option value="{{ $lead->id }}" @selected((string) old('lead_id', $task?->lead_id ?? $selectedLeadId) === (string) $lead->id)>{{ $lead->title }} — {{ $lead->company->name }}</option>
            @endforeach
        </select>
        @error('lead_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="company_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __("Customer company") }} <span class="font-normal text-zinc-500">({{ __("optional") }})</span></label>
        <select id="company_id" name="company_id" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="">{{ __("No related company") }}</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((string) old("company_id", $task?->company_id) === (string) $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
        @error("company_id") <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="contact_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __("Contact") }} <span class="font-normal text-zinc-500">({{ __("optional") }})</span></label>
        <select id="contact_id" name="contact_id" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="">{{ __("No related contact") }}</option>
            @foreach ($contacts as $contact)
                <option value="{{ $contact->id }}" @selected((string) old("contact_id", $task?->contact_id ?? $selectedContactId) === (string) $contact->id)>{{ $contact->full_name }} — {{ $contact->company?->name }}</option>
            @endforeach
        </select>
        @error("contact_id") <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="assigned_to_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Assigned to</label>
        <select id="assigned_to_id" name="assigned_to_id" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            @foreach ($members as $member)
                <option value="{{ $member->id }}" @selected((string) old('assigned_to_id', $task?->assigned_to_id ?? auth()->id()) === (string) $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        @error('assigned_to_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <flux:input name="due_at" type="datetime-local" :label="__('Due date and time')" :value="old('due_at', $task?->due_at?->timezone($timezone)->format('Y-m-d\TH:i'))" required />
    <flux:input name="reminder_at" type="datetime-local" :label="__('Reminder time (optional)')" :value="old('reminder_at', $task?->reminder_at?->timezone($timezone)->format('Y-m-d\TH:i'))" />

    <div class="sm:col-span-2">
        <label for="priority" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Priority</label>
        <select id="priority" name="priority" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            @foreach (['low' => __('Low'), 'normal' => __('Normal'), 'high' => __('High'), 'urgent' => __('Urgent')] as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $task?->priority ?? 'normal') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('priority') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <flux:textarea name="description" :label="__('Notes')" rows="5" class="sm:col-span-2">{{ old('description', $task?->description) }}</flux:textarea>
</div>
