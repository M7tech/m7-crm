<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="company_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Company</label>
        <select id="company_id" name="company_id" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="">Select a company</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((string) old('company_id', $contact?->company_id) === (string) $company->id)>
                    {{ $company->name }}
                </option>
            @endforeach
        </select>
        @error('company_id')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <flux:input name="first_name" :label="__('First name')" :value="old('first_name', $contact?->first_name)" required />
    <flux:input name="last_name" :label="__('Last name')" :value="old('last_name', $contact?->last_name)" />
    <flux:input name="job_title" :label="__('Job title')" :value="old('job_title', $contact?->job_title)" />
    <flux:input name="phone" :label="__('Phone')" :value="old('phone', $contact?->phone)" />
    <flux:input name="email" type="email" :label="__('Email')" :value="old('email', $contact?->email)" class="sm:col-span-2" />

    <div class="sm:col-span-2">
        <label for="status" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Status</label>
        <select id="status" name="status" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="active" @selected(old('status', $contact?->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $contact?->status) === 'inactive')>Inactive</option>
        </select>
        @error('status')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <flux:textarea name="notes" :label="__('Notes')" rows="4" class="sm:col-span-2">{{ old('notes', $contact?->notes) }}</flux:textarea>
</div>
