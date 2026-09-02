<x-layouts::app :title="__('Import contacts')">
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <a href="{{ route('contacts.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to contacts</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Import contacts</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Upload a CSV, review every row, then choose how existing contacts are handled.</p>
        </div>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <h2 class="font-semibold text-zinc-950 dark:text-white">Upload CSV</h2>
            <p class="mt-1 text-sm text-zinc-500">Maximum 500 rows and 2 MB. Company names must match companies already in this workspace.</p>

            <div class="mt-4 overflow-x-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-100">
                <code>company,first_name,last_name,job_title,email,phone,status,notes</code>
            </div>

            <form method="POST" action="{{ route('contacts.import.preview') }}" enctype="multipart/form-data" class="mt-5 grid gap-4">
                @csrf
                <div>
                    <label for="csv_file" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">CSV file</label>
                    <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv,text/plain" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 file:me-3 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:font-medium file:text-emerald-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" />
                    @error('csv_file')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <flux:button type="submit" variant="primary" class="w-full sm:w-fit">Preview import</flux:button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Recent imports</h2>
            </div>
            @forelse ($imports as $import)
                <div class="grid gap-2 border-b border-zinc-100 px-5 py-4 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto] dark:border-zinc-800">
                    <div>
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $import->original_name }}</p>
                        <p class="mt-1 text-sm text-zinc-500">{{ $import->importedBy?->name ?? 'Former user' }} · {{ $import->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ ucfirst($import->status) }} · {{ $import->imported_rows }} added · {{ $import->updated_rows }} updated · {{ $import->skipped_rows }} skipped
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-zinc-500">No imports yet.</p>
            @endforelse
        </section>
    </div>
</x-layouts::app>
