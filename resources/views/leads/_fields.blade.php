<div class="grid gap-5 sm:grid-cols-2">
    <flux:input name="title" :label="__('Lead title')" :value="old('title', $lead?->title)" class="sm:col-span-2" required />

    <div>
        <label for="company_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Company</label>
        <select id="company_id" name="company_id" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="">Select a company</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((string) old('company_id', $lead?->company_id) === (string) $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
        @error('company_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="contact_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Contact <span class="font-normal text-zinc-500">(optional)</span></label>
        <select id="contact_id" name="contact_id" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="">No contact</option>
            @foreach ($contacts as $contact)
                <option value="{{ $contact->id }}" @selected((string) old('contact_id', $lead?->contact_id) === (string) $contact->id)>{{ $contact->full_name }} — {{ $contact->company->name ?? '' }}</option>
            @endforeach
        </select>
        @error('contact_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="pipeline_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Pipeline</label>
        <select id="pipeline_id" name="pipeline_id" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            @foreach ($pipelines as $pipeline)
                <option value="{{ $pipeline->id }}" @selected((string) old('pipeline_id', $lead?->pipeline_id ?? $pipelines->first()?->id) === (string) $pipeline->id)>{{ $pipeline->name }}</option>
            @endforeach
        </select>
        @error('pipeline_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="stage_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Stage</label>
        <select id="stage_id" name="stage_id" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            @foreach ($pipelines as $pipeline)
                <optgroup label="{{ $pipeline->name }}">
                    @foreach ($pipeline->stages as $stage)
                        <option value="{{ $stage->id }}" @selected((string) old('stage_id', $lead?->stage_id ?? $pipelines->first()?->stages->first()?->id) === (string) $stage->id)>{{ $stage->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('stage_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="assigned_to_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Assigned to</label>
        <select id="assigned_to_id" name="assigned_to_id" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="">Unassigned</option>
            @foreach ($members as $member)
                <option value="{{ $member->id }}" @selected((string) old('assigned_to_id', $lead?->assigned_to_id) === (string) $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        @error('assigned_to_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <flux:input name="source" :label="__('Source')" :value="old('source', $lead?->source)" placeholder="Referral, website, campaign…" />

    <flux:input name="expected_value" type="number" min="0" step="0.001" :label="__('Expected value')" :value="old('expected_value', $lead ? ($lead->expected_value_minor / ($lead->currency === 'USD' ? 100 : 1000)) : 0)" required />

    <div>
        <label for="currency" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Currency</label>
        <select id="currency" name="currency" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="IQD" @selected(old('currency', $lead?->currency ?? 'IQD') === 'IQD')>IQD — Iraqi dinar (3 decimals)</option>
            <option value="USD" @selected(old('currency', $lead?->currency) === 'USD')>USD — US dollar</option>
        </select>
        @error('currency') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <flux:input name="loss_reason" :label="__('Loss reason')" :value="old('loss_reason', $lead?->loss_reason)" placeholder="Required only when the stage is Lost" class="sm:col-span-2" />
    <flux:textarea name="notes" :label="__('Notes')" rows="5" class="sm:col-span-2">{{ old('notes', $lead?->notes) }}</flux:textarea>
</div>
